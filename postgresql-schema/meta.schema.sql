--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: meta; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA meta;


--
-- Name: SCHEMA meta; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA meta IS 'The meta schema contains information about tables and columns in the public schema. This information is used by the codebase to perform validation, formatting, labeling, etc. of form data. ';


SET search_path = meta, pg_catalog;

--
-- Name: column_description(text, text, text); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION column_description("table" text, "column" text, schema text DEFAULT 'public'::text) RETURNS text
    LANGUAGE sql STRICT
    AS $_$
SELECT 
    pg_catalog.col_description(a.attrelid, a.attnum)
FROM
	pg_catalog.pg_class c
	JOIN pg_catalog.pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
	LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
WHERE 
	a.attname = $2
    AND c.relname = $1
	AND n.nspname = $3
$_$;


--
-- Name: create_audit_table(text, text, text); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION create_audit_table("table" text, schema text DEFAULT 'audit'::text, from_schema text DEFAULT 'public'::text) RETURNS boolean
    LANGUAGE plpgsql STRICT
    AS $$
BEGIN
	
    PERFORM * FROM information_schema.tables WHERE table_schema = schema AND table_name = table;
    IF FOUND THEN
    	RAISE EXCEPTION 'Audit table "%.%" already exists.', schema, table;
    END IF;
    
    --If we're still here, create the audit table
    EXECUTE 'CREATE TABLE '||schema||'.'||table||' AS 
    	SELECT 1::integer as tuple_id,
        NOW()::timestamp without time zone as tuple_start,
        NOW()::timestamp without time zone as tuple_stop, 
        ''''::text as tuple_start_action,
        ''''::text as tuple_stop_action,
        1::integer as tuple_revision,
        * FROM '||table||' WHERE FALSE';
    EXECUTE 'CREATE SEQUENCE '||schema||'.'||table||'_tuple_id_seq';
    EXECUTE 'ALTER TABLE '||schema||'.'||table||' ALTER COLUMN tuple_id SET DEFAULT nextval('''||schema||'.'||table||'_tuple_id_seq'')';
    EXECUTE 'UPDATE '||schema||'.'||table||' SET tuple_id = nextval('''||schema||'.'||table||'_tuple_id_seq'')';
    EXECUTE 'ALTER SEQUENCE '||schema||'.'||table||'_tuple_id_seq OWNED BY '||schema||'.'||table||'.tuple_id';
    EXECUTE 'ALTER TABLE '||schema||'.'||table||' ADD PRIMARY KEY (tuple_id)';
    
    --Create the auditing trigger
    EXECUTE 'CREATE TRIGGER "'||table||'_tr_audit" AFTER INSERT OR UPDATE OR DELETE ON '||from_schema||'.'||table||' FOR EACH ROW EXECUTE PROCEDURE "public"."trigger_audit"();';
    
    RETURN TRUE;
    
END;
$$;


--
-- Name: FUNCTION create_audit_table("table" text, schema text, from_schema text); Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON FUNCTION create_audit_table("table" text, schema text, from_schema text) IS 'Creates an auditing table in the specified schema. Takes 3 arguments:
1. The name of the table to be audited (required)
2. The name of the schema to create the audit table in (default ''audit'')
3. The name of the schema the audited table resides in (default ''public'')';


--
-- Name: ensure_auditing_fields(); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION ensure_auditing_fields() RETURNS boolean
    LANGUAGE plpgsql
    AS $$
DECLARE
	r RECORD;
BEGIN

    FOR r IN 
        SELECT
            t.table_schema,
            t.table_name,
            count(CASE WHEN c.column_name = 'created_timestamp' THEN 1 ELSE NULL END) as has_created_timestamp,
            count(CASE WHEN c.column_name = 'modified_timestamp' THEN 1 ELSE NULL END) as has_modified_timestamp,
            count(CASE WHEN c.column_name = 'modified_by' THEN 1 ELSE NULL END) as has_modified_by,
            count(CASE WHEN c.column_name = 'created_by' THEN 1 ELSE NULL END) as has_created_by
        FROM 
            information_schema.tables t
            JOIN information_schema.columns c USING (table_schema, table_name)
        WHERE
            t.table_schema IN ('public', 'pricing_model')
            AND t.table_type = 'BASE TABLE'
        GROUP BY
            1, 2
    LOOP
    	IF r.has_created_timestamp = 0 THEN
        	RAISE NOTICE '%.% - Adding created_timestamp', r.table_schema, r.table_name;
            EXECUTE 'ALTER TABLE '||r.table_schema||'.'||r.table_name||' ADD COLUMN created_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        END IF;
        IF r.has_modified_timestamp = 0 THEN
        	RAISE NOTICE '%.% - Adding modified_timestamp', r.table_schema, r.table_name;
            EXECUTE 'ALTER TABLE '||r.table_schema||'.'||r.table_name||' ADD COLUMN modified_timestamp TIMESTAMP';
            EXECUTE 'CREATE TRIGGER '||r.table_name||'_tr_modified_timestamp BEFORE UPDATE 
ON '||r.table_schema||'.'||r.table_name||' FOR EACH ROW 
EXECUTE PROCEDURE public.trigger_modified_timestamp()';
        END IF;
        IF r.has_modified_by = 0 THEN
        	RAISE NOTICE '%.% - Adding modified_by', r.table_schema, r.table_name;
            EXECUTE 'ALTER TABLE '||r.table_schema||'.'||r.table_name||' ADD COLUMN modified_by INTEGER';
            EXECUTE 'ALTER TABLE '||r.table_schema||'.'||r.table_name||' ADD CONSTRAINT
                '||r.table_name||'_fk_modified_by FOREIGN KEY (modified_by)
                REFERENCES public.users(id)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION
                DEFERRABLE INITIALLY IMMEDIATE';
        END IF;
        IF r.has_created_by = 0 THEN
        	RAISE NOTICE '%.% - Adding created_by', r.table_schema, r.table_name;
            EXECUTE 'ALTER TABLE '||r.table_schema||'.'||r.table_name||' ADD COLUMN created_by INTEGER DEFAULT meta.session_get(''user_id'')::integer';
            EXECUTE 'ALTER TABLE '||r.table_schema||'.'||r.table_name||' ADD CONSTRAINT
                '||r.table_name||'_fk_created_by FOREIGN KEY (created_by)
                REFERENCES public.users(id)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION
                DEFERRABLE INITIALLY IMMEDIATE';
        END IF;
        
    END LOOP;

	RETURN TRUE;

END;
$$;


--
-- Name: FUNCTION ensure_auditing_fields(); Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON FUNCTION ensure_auditing_fields() IS 'Ensures that created_timestamp, modified_timestamp, and modified_by exist on
every table.';


--
-- Name: ensure_deferrable_fks(); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION ensure_deferrable_fks() RETURNS SETOF text
    LANGUAGE plpgsql
    AS $$
DECLARE
	r meta.constraints_view%ROWTYPE;
BEGIN
FOR r IN 
	SELECT 
    	*
	FROM 
        meta.constraints_view 
    WHERE 
        constraint_type = 'FOREIGN KEY'
        AND is_deferrable = 'NO'
LOOP

	RETURN NEXT 
    	'ALTER TABLE ' || r.table_schema || '.' || r.table_name || 
        ' DROP CONSTRAINT ' || r.constraint_name || ' RESTRICT;';
      
    RETURN NEXT 
    	'ALTER TABLE ' || r.table_schema || '.' || r.table_name || 
        ' ADD CONSTRAINT ' || r.constraint_name || 
        ' FOREIGN KEY (' || r.column_name || ')' ||
        ' REFERENCES ' || r.references_schema || '.' || r.references_table ||  
        '(' || r.references_field || ')' ||
        ' ON DELETE ' || r.on_delete || 
        ' ON UPDATE ' || r.on_update || 
        ' DEFERRABLE INITIALLY IMMEDIATE;';
    
    

END LOOP;

END;
$$;


--
-- Name: ensure_session_table(); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION ensure_session_table() RETURNS boolean
    LANGUAGE plpgsql
    AS $$
BEGIN

	PERFORM *
    FROM pg_catalog.pg_class
    WHERE relname = '_session_variables' AND relnamespace = pg_my_temp_schema();
    
    IF NOT FOUND THEN
    	CREATE TEMPORARY TABLE _session_variables (
        	"key" TEXT PRIMARY KEY,
            "value" TEXT
        );
    END IF;
    
    RETURN TRUE;

END;
$$;


--
-- Name: FUNCTION ensure_session_table(); Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON FUNCTION ensure_session_table() IS 'Creates a temporary session table if one doesn''t exist already';


--
-- Name: object_description(text, text); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION object_description("table" text, schema text DEFAULT 'public'::text) RETURNS text
    LANGUAGE sql STABLE STRICT
    AS $_$
SELECT 
  pg_catalog.obj_description(c.oid, 'pg_class')
FROM pg_catalog.pg_class c
     LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
WHERE c.relname = $1
  AND n.nspname = $2
$_$;


--
-- Name: session_get(text); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION session_get(text) RETURNS text
    LANGUAGE plpgsql STRICT
    AS $_$
DECLARE
	xValue TEXT;
BEGIN
  
  PERFORM meta.ensure_session_table();
    
  SELECT "value" INTO xValue FROM _session_variables WHERE "key" = $1;
    
  RETURN xValue;
    
END;
$_$;


--
-- Name: session_set(text, text); Type: FUNCTION; Schema: meta; Owner: -
--

CREATE FUNCTION session_set(text, INOUT text) RETURNS text
    LANGUAGE plpgsql
    AS $_$
BEGIN

	PERFORM meta.ensure_session_table();
    
    UPDATE _session_variables
    SET "value" = $2 
    WHERE "key" = $1;
    
    IF NOT FOUND THEN
    	INSERT INTO _session_variables VALUES ($1, $2);
    END IF;
    
    RETURN;
    
END;
$_$;


--
-- Name: constraints_view; Type: VIEW; Schema: meta; Owner: -
--

CREATE VIEW constraints_view AS
    SELECT tc.constraint_name, tc.constraint_type, tc.table_schema, tc.table_name, kcu.column_name, tc.is_deferrable, tc.initially_deferred, rc.match_option AS match_type, rc.update_rule AS on_update, rc.delete_rule AS on_delete, ccu.table_schema AS references_schema, ccu.table_name AS references_table, ccu.column_name AS references_field FROM (((information_schema.table_constraints tc LEFT JOIN information_schema.key_column_usage kcu ON (((((tc.constraint_catalog)::text = (kcu.constraint_catalog)::text) AND ((tc.constraint_schema)::text = (kcu.constraint_schema)::text)) AND ((tc.constraint_name)::text = (kcu.constraint_name)::text)))) LEFT JOIN information_schema.referential_constraints rc ON (((((tc.constraint_catalog)::text = (rc.constraint_catalog)::text) AND ((tc.constraint_schema)::text = (rc.constraint_schema)::text)) AND ((tc.constraint_name)::text = (rc.constraint_name)::text)))) LEFT JOIN information_schema.constraint_column_usage ccu ON (((((rc.unique_constraint_catalog)::text = (ccu.constraint_catalog)::text) AND ((rc.unique_constraint_schema)::text = (ccu.constraint_schema)::text)) AND ((rc.unique_constraint_name)::text = (ccu.constraint_name)::text))));


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: field_classes; Type: TABLE; Schema: meta; Owner: -; Tablespace: 
--

CREATE TABLE field_classes (
    id integer NOT NULL,
    name text NOT NULL
);


--
-- Name: TABLE field_classes; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON TABLE field_classes IS 'Keeps a list of Form Field classes that may be used to render fields.';


--
-- Name: field_classes_id_seq; Type: SEQUENCE; Schema: meta; Owner: -
--

CREATE SEQUENCE field_classes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: field_classes_id_seq; Type: SEQUENCE OWNED BY; Schema: meta; Owner: -
--

ALTER SEQUENCE field_classes_id_seq OWNED BY field_classes.id;


--
-- Name: field_has_validators; Type: TABLE; Schema: meta; Owner: -; Tablespace: 
--

CREATE TABLE field_has_validators (
    id integer NOT NULL,
    field_id integer NOT NULL,
    validator_class_id integer NOT NULL,
    ordering integer DEFAULT 0 NOT NULL
);


--
-- Name: field_has_validators_id_seq; Type: SEQUENCE; Schema: meta; Owner: -
--

CREATE SEQUENCE field_has_validators_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: field_has_validators_id_seq; Type: SEQUENCE OWNED BY; Schema: meta; Owner: -
--

ALTER SEQUENCE field_has_validators_id_seq OWNED BY field_has_validators.id;


--
-- Name: fields; Type: TABLE; Schema: meta; Owner: -; Tablespace: 
--

CREATE TABLE fields (
    id integer NOT NULL,
    table_id integer NOT NULL,
    name text NOT NULL,
    data_type text,
    field_class_id integer,
    is_required boolean DEFAULT false NOT NULL,
    label text,
    tooltip text,
    ordering integer DEFAULT 0 NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    datasource text,
    fk_table_id integer
);


--
-- Name: COLUMN fields.table_id; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.table_id IS 'Table this column belongs to';


--
-- Name: COLUMN fields.name; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.name IS 'Database name of the column';


--
-- Name: COLUMN fields.data_type; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.data_type IS 'Type in database';


--
-- Name: COLUMN fields.field_class_id; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.field_class_id IS 'The class that will be used to render this field. If NULL, this field will not be rendered.';


--
-- Name: COLUMN fields.is_required; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.is_required IS 'Whether or not this field is required by default';


--
-- Name: COLUMN fields.label; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.label IS 'The text label for this field';


--
-- Name: COLUMN fields.tooltip; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.tooltip IS 'The tooltip to be displayed with the field';


--
-- Name: COLUMN fields.ordering; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.ordering IS 'The order in which these fields will be displayed in the form.';


--
-- Name: COLUMN fields.is_visible; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.is_visible IS 'Whether or not this field is visible in data set outputs. Used by the DataSet class.';


--
-- Name: COLUMN fields.datasource; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.datasource IS 'Name of the data source to use for fields that use setOptions().';


--
-- Name: COLUMN fields.fk_table_id; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON COLUMN fields.fk_table_id IS 'A link to the table that sources the foreign key for this field';


--
-- Name: fields_id_seq; Type: SEQUENCE; Schema: meta; Owner: -
--

CREATE SEQUENCE fields_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fields_id_seq; Type: SEQUENCE OWNED BY; Schema: meta; Owner: -
--

ALTER SEQUENCE fields_id_seq OWNED BY fields.id;


--
-- Name: foreign_keys_view; Type: VIEW; Schema: meta; Owner: -
--

CREATE VIEW foreign_keys_view AS
    SELECT tc.constraint_name, tc.table_schema, tc.table_name, kcu.column_name, ccu.table_schema AS foreign_table_schema, ccu.table_name AS foreign_table_name, ccu.column_name AS foreign_column_name FROM ((information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON (((tc.constraint_name)::text = (kcu.constraint_name)::text))) JOIN information_schema.constraint_column_usage ccu ON (((ccu.constraint_name)::text = (tc.constraint_name)::text))) WHERE ((tc.constraint_type)::text = 'FOREIGN KEY'::text);


--
-- Name: tables; Type: TABLE; Schema: meta; Owner: -; Tablespace: 
--

CREATE TABLE tables (
    id integer NOT NULL,
    name text NOT NULL,
    schema text DEFAULT 'public'::text NOT NULL
);


--
-- Name: tables_id_seq; Type: SEQUENCE; Schema: meta; Owner: -
--

CREATE SEQUENCE tables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tables_id_seq; Type: SEQUENCE OWNED BY; Schema: meta; Owner: -
--

ALTER SEQUENCE tables_id_seq OWNED BY tables.id;


--
-- Name: validator_classes; Type: TABLE; Schema: meta; Owner: -; Tablespace: 
--

CREATE TABLE validator_classes (
    id integer NOT NULL,
    name text NOT NULL
);


--
-- Name: TABLE validator_classes; Type: COMMENT; Schema: meta; Owner: -
--

COMMENT ON TABLE validator_classes IS 'Tracks the validator classes that can be used to validate fields.';


--
-- Name: validator_classes_id_seq; Type: SEQUENCE; Schema: meta; Owner: -
--

CREATE SEQUENCE validator_classes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: validator_classes_id_seq; Type: SEQUENCE OWNED BY; Schema: meta; Owner: -
--

ALTER SEQUENCE validator_classes_id_seq OWNED BY validator_classes.id;


--
-- Name: id; Type: DEFAULT; Schema: meta; Owner: -
--

ALTER TABLE field_classes ALTER COLUMN id SET DEFAULT nextval('field_classes_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: meta; Owner: -
--

ALTER TABLE field_has_validators ALTER COLUMN id SET DEFAULT nextval('field_has_validators_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: meta; Owner: -
--

ALTER TABLE fields ALTER COLUMN id SET DEFAULT nextval('fields_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: meta; Owner: -
--

ALTER TABLE tables ALTER COLUMN id SET DEFAULT nextval('tables_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: meta; Owner: -
--

ALTER TABLE validator_classes ALTER COLUMN id SET DEFAULT nextval('validator_classes_id_seq'::regclass);


--
-- Name: field_classes_pkey; Type: CONSTRAINT; Schema: meta; Owner: -; Tablespace: 
--

ALTER TABLE ONLY field_classes
    ADD CONSTRAINT field_classes_pkey PRIMARY KEY (id);


--
-- Name: field_has_validators_pkey; Type: CONSTRAINT; Schema: meta; Owner: -; Tablespace: 
--

ALTER TABLE ONLY field_has_validators
    ADD CONSTRAINT field_has_validators_pkey PRIMARY KEY (id);


--
-- Name: fields_pkey; Type: CONSTRAINT; Schema: meta; Owner: -; Tablespace: 
--

ALTER TABLE ONLY fields
    ADD CONSTRAINT fields_pkey PRIMARY KEY (id);


--
-- Name: tables_pkey; Type: CONSTRAINT; Schema: meta; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tables
    ADD CONSTRAINT tables_pkey PRIMARY KEY (id);


--
-- Name: validator_classes_pkey; Type: CONSTRAINT; Schema: meta; Owner: -; Tablespace: 
--

ALTER TABLE ONLY validator_classes
    ADD CONSTRAINT validator_classes_pkey PRIMARY KEY (id);


--
-- Name: field_classes_idx_name; Type: INDEX; Schema: meta; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX field_classes_idx_name ON field_classes USING btree (name);


--
-- Name: field_has_validators_idx_unique; Type: INDEX; Schema: meta; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX field_has_validators_idx_unique ON field_has_validators USING btree (field_id, validator_class_id);


--
-- Name: fields_idx_name; Type: INDEX; Schema: meta; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX fields_idx_name ON fields USING btree (table_id, name);


--
-- Name: field_has_validators_fk_field_id; Type: FK CONSTRAINT; Schema: meta; Owner: -
--

ALTER TABLE ONLY field_has_validators
    ADD CONSTRAINT field_has_validators_fk_field_id FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE DEFERRABLE;


--
-- Name: field_has_validators_fk_validator_id; Type: FK CONSTRAINT; Schema: meta; Owner: -
--

ALTER TABLE ONLY field_has_validators
    ADD CONSTRAINT field_has_validators_fk_validator_id FOREIGN KEY (validator_class_id) REFERENCES validator_classes(id) ON DELETE CASCADE DEFERRABLE;


--
-- Name: fields_fk_field_class_id; Type: FK CONSTRAINT; Schema: meta; Owner: -
--

ALTER TABLE ONLY fields
    ADD CONSTRAINT fields_fk_field_class_id FOREIGN KEY (field_class_id) REFERENCES field_classes(id) DEFERRABLE;


--
-- Name: fields_fk_fk_table_id; Type: FK CONSTRAINT; Schema: meta; Owner: -
--

ALTER TABLE ONLY fields
    ADD CONSTRAINT fields_fk_fk_table_id FOREIGN KEY (fk_table_id) REFERENCES tables(id) ON DELETE SET NULL DEFERRABLE;


--
-- Name: fields_fk_table_id; Type: FK CONSTRAINT; Schema: meta; Owner: -
--

ALTER TABLE ONLY fields
    ADD CONSTRAINT fields_fk_table_id FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE DEFERRABLE;


--
-- PostgreSQL database dump complete
--

