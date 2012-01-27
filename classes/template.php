<?php
/**
 * Simple, scoped template rendering engine
 *
 * Variables may be passed to templates by setting dynamic
 * properties of this class or by passing them at call time.
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
use HTML\Form\Field\Submit;
use HTML\Form\Container\DataSet;
use HTML\Form\Container\TabSet;
use HTML\Form\Container\Repeater;
use HTML\Form\Container\Table;
use Format\Format;
use HTML\Form\Container\Fieldset;
class Template {

	protected $vars = array();

	function __get($key){
		return $this->vars[$key];
	}

	function __set($key, $val){
		$this->vars[$key] = $val;
	}

	function __isset($key){
		return array_key_exists($key, $this->vars);
	}

	function __unset($key){
		unset($this->vars[$key]);
	}

	/**
	 * Renders a template via includes
	 * The template being included will have its own scope with the variables array
	 * imported into it.
	 * @param string $template Name of the template to render
	 * @param array $vars Array of variables to be passed to the template
	 * @return mixed Whatever the included template decides it wants to return
	 */
	function render($template, $vars = array()){

		//Make sure the template exists
		if(!file_exists("templates/".$template.".php")):
			user_error("Template not found: templates/" . $template . ".php", E_USER_ERROR);
		endif;

		//Import the template's variables
		$vars = array_merge($this->vars, (array) $vars);
		extract($vars, EXTR_REFS|EXTR_OVERWRITE);

		//Load the template
		return include "templates/" . $template . ".php";

	}

	/**
	 * Generates a 2-column HTML table based on input values.
	 * All config parameters are optional.
	 * @param array $config
	 * 	- legend			- The legend to use for the fieldset
	 *  - fields			- Array of fields to be used by the table. If not provided, uses fields from 'query'.
	 *  - query				- Instance of Query used to define fields (if not defined in 'fields') and set value.
	 *  - repeatable		- Whether or not this dataset should be treated as repeatable. Default is false, but may depend on the number of rows found when 'query' is proved.
	 *  - use_tabs			- Whether or not to use tabs when displaying data. Implies repeatable.
	 *  - use_dataset		- Whether or not to use a dataset instead of a table for displaying data.
	 *  - use_form			- Whether or not to use Form rendering mode.
	 *  - tooltips			- Whether or not to display tooltips. Default false.
	 *  - edit_link			- The url to be used for an edit link. Default null.
	 *  - format_mode		- The format mode. Defaults to Format::HTML.
	 *  - table_classes		- List of classes to set on the table element. Defaults to "data".
	 *  - preserve_required	- Fields are by default not required, unless this is set.
	 *  - namespace			- Sets the namespace to be used
	 */
	static function dataTable(array $config){

		//Build the fieldset
		$fs = new Fieldset();

		//Legend for the fieldset
		if(!empty($config['legend'])) $fs->setLegend($config['legend']);

		//Build the table / dataset
		$table = empty($config['use_dataset']) ? new Table() : new DataSet();

		//Set the fields
		$fields = array();
		if(!empty($config['fields'])):

			//From the field array
			$fields = $config['fields'];

		elseif(!empty($config['query'])):

			//Use the fields from the query
			$fields = $config['query']->getFormFields();
			$recordset = $config['query']->execute();

		endif;

		//Remove the first field when in tab mode
		if(!empty($config['use_tabs'])):
			array_shift($fields);
		endif;

		//Add children to the table
		$table->addChildren($fields);

		//Set table classes
		$classes = "data";
		if(!empty($config['use_dataset'])) $classes = null;
		if(!empty($config['table_classes'])) $classes = $config['table_classes'];
		if($classes) $table->addClass($classes);

		//Determine repeatable status
		$repeatable = false;
		if(!empty($config['use_tabs']) || !empty($config['use_dataset'])):
			$repeatable = false;
		elseif(isset($config['repeatable'])):
			$repeatable = $config['repeatable'];
		elseif(!empty($config['query'])):
			$repeatable = count($recordset) > 1;
		endif;

		//Build a repeater?
		if($repeatable):
			$table = with(new Repeater)
				->setDefaultNumber(0)
				->addChild(with(new Fieldset)
					->addChild($table)
				)
			;
		endif;

		//Append contents to the fieldset
		$fs->addChild($table);

		//Form mode settings
		if(!empty($config['use_form'])):

			//Default tooltips to true
			if(!isset($config['tooltips'])) $config['tooltips'] = true;

			//Add thin class
			$table->addClass("thin");

			//Preserve required fields
			$config['preserve_required'] = true;

			//Form formatting mode
			$config['format_mode'] = Format::FORM;

			//Add a submit button
			$table->addChild(new Submit);

		endif;

		//Configure tooltips
		$fs->showTooltip(isset($config['tooltips']) && $config['tooltips']);

		//Configure the edit link
		if(!empty($config['edit_link'])) $fs->setEditLink($config['edit_link']);

		//Set the formatting mode
		$fs->setFormatMode(!empty($config['format_mode']) ? $config['format_mode'] : Format::HTML);

		//Set the required mode
		if(empty($config['preserve_required'])) $fs->setRequired(false, true);

		//Set the value
		if(!empty($config['use_dataset']) && !empty($config['query'])):

			//Configure the dataset
			$table->pagination_info = $config['query']->getPaginationInfo();
			$table->is_paginated = $config['query']->isPaginated();
			$table->setValue($recordset);

		elseif(!empty($config['use_tabs']) && !empty($config['query'])):

			//Build a tab set
			$tabs = new TabSet;
			foreach($recordset as $row):
				$t = clone($table);
				$t->setLabel(array_shift($row))->setValue($row);
				$tabs->addChild($t);
			endforeach;
			$fs->removeChild($table)->addChild($tabs);

		elseif(!empty($config['query'])):

			//Set a normal value depending on whether recordset was repeated
			$value = $repeatable ? $recordset->getAll() : $recordset->getRow();
			if($value) $fs->setValue($value);

		endif;

		//Set the namespace
		if(isset($config['namespace'])) $fs->setNamespace($config['namespace']);

		//Return the output
		return $fs;

	}

}