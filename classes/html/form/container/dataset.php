<?php
namespace HTML\Form\Container;
use HTML\Form\Field\Link;

use HTML\Form\Field\Hidden;
use Format\Format;
use HTML\Form\Layout\Recordset;
use HTML\Form\Field\FormField;
use HTML\Form\iContainable;
use HTML\Form\Container\Query;
use \QueryString;

/**
 * DataSet is a special form of repeater that transforms all children into
 * first-generation elements.
 *
 * Due to its specialized nature, DataSet will use only two LayoutManagers -
 * RecordSet and Export - designed for rendering HTML tables and CSV exports,
 * respectively.
 */
class DataSet extends Repeater implements \Countable {

	//Pagination configuration
	protected static $num_end = 2; //The number of pages on each side
	protected static $num_before = 5; //The number of pages before the current one
	protected static $num_after = 4; //The number of pages after the current one

	public $child_indent = 2;

	protected $caption = "";

	protected $show_default = 0;

	/**
	 * Tracks whether or not to provide multi-sort capabilities
	 * @var boolean
	 */
	protected $enable_multisort = false;

	/**
	 * Tracks whether or not to provide CSV export capabilities
	 * @var mixed false or export file name
	 */
	protected $enable_export = false;

	/**
	 * Name to use for file export
	 * @var string
	 */
	protected $export_filename = null;

	/**
	 * Infromation used in order to paginate the dataset
	 * @var array
	 */
	public $pagination_info = array();

	/**
	 * Whether or not the dataset is paginated
	 * @var bool
	 */
	public $is_paginated = false;

	public $format_mode = Format::HTML;

	/**
	 * Tracks whether or not rows can be dynamically added or removed in form view
	 * @var boolean
	 */
	protected $dynamic_add = true;

	function __construct(Container $child = null, $namespace = null) {
		$this->addClass(array('table', 'dataset'));
		parent::__construct(null, $namespace);
		$this->addChild($child);
		$this->proxy->setLayoutManager(new Recordset());
	}

	/**
	 * This class flattens containers and adds all FormField elements as
	 * first-generation children.
	 */
	function addChild($child) {
		if($child instanceof FormField) parent::addChild($child);
		if($child instanceof Container):
			foreach($child->getChildren() as $c) $this->addChild($c);
		endif;
		return $this;
	}

	/**
	 * Sets the contents of the HTML <caption> element
	 * @param string $caption
	 */
	function setCaption($caption){
		$this->caption = $caption;
		return $this;
	}

	/**
	 * Sets the filename to be used for exports
	 * @param string $filename
	 */
	function setExportFilename($filename){
		$this->export_filename = $filename;
		return $this;
	}

	/**
	 * Determines whether multi-sort should be enabled
	 * @param boolean $enabled
	 */
	function enableMultisort($enabled){
		$this->enable_multisort = (bool) $enabled;
		return $this;
	}

	/**
	 * Determines whether exporting should be enabled
	 * @param boolean
	 * @param string $filename if provided, sets the export filename
	 */
	function enableExporting($enabled, $filename = null){
		$this->enable_export = (boolean) $enabled;
		if(!is_null($filename)) $this->setExportFilename($filename);
		return $this;
	}

	/**
	 * Outputs the DataSet in table format
	 */
	function __toString(){

		//Handle data export requests
		if($this->enable_export && !empty($_GET['export'])):

			$this->exportData();
			exit;

		endif;

		$t = $this->indent();

		$out = "";
		$num_cols = 0;

		//Output buttons
		$out .= $this->renderSortableButton();
		$out .= $this->renderExportButton();

		$page = $this->pagination_info;

		//Start the table
		$out.= $t . "<table" . $this->outputAttributes() . ">";
		if($this->caption) $out.= $t. "\t<caption>" . $this->caption . "</caption>";

		//Add a remove link in form mode
		if($this->format_mode == Format::FORM && $this->dynamic_add):

			$remove_link = new Link(null, "Remove");
			$remove_link
				->setValue("Remove")
				->setUrl("#")
				->addClass("icon remove event")
				->setAttribute("data-click-handler", "datasetRemoveRow")
			;
			$this->addChild($remove_link);

		endif;

		$out.= $t . "\t<thead>";
		foreach($this->proxy->getChildren() as $child):

			if($child instanceof Hidden) continue;

			$num_cols++;

			$tag = "<th>";
			if(!empty($child->ordinal_position) && !empty($child->is_sortable)):
				$tag = "<th data-column-position='".$child->ordinal_position."'>";
			endif;

			$out .= $t . "\t\t" . $tag . $this->format_header($child) . "</th>";
		endforeach;
		$out.= $t . "\t</thead>";

		//Add a replication row
		if($this->format_mode == Format::FORM && $this->dynamic_add):

			$out.= $t . "<tbody id='".$this->ensureElementID()."-add-item' class='hidden move-me'>";

			$this->name_prefix[] = '$|$'.$this->ensureElementID().'$|$';

			$value = $this->value;
			$this->value = array();

			$this->cascadeProperties();

			$out .= Recordset::render($this, true);

			array_pop($this->name_prefix);
			$this->cascadeProperties();

			$this->value = $value;

			$out.= $t . "</tbody>";

		endif;


		$out.= $t . "\t<tfoot>";
		$s = "";

		//Recalculate the total number of records
		if($this->format_mode == Format::FORM && $this->dynamic_add):
			$this->value = array_values($this->value);
			$page['total_records'] = count($this->value);
		endif;

		//Pagination info must be set when creating the Dataset in order for pagination to work
		if(!empty($page) && $page['total_records'] != 1) $s = "s";

		if($this->is_paginated && $page['total_pages'] > 1){
			$out.= "\n\t\t\t<td colspan='".$num_cols."'>Record{$s} "
			. number_format($page['first_record']) . " - "
			. number_format($page['last_record']) . " of "
			. number_format($page['total_records']) . "</td>";
			$count = $page['total_records'];
		}elseif(!empty($page)){
			$out.= "\n\t\t\t<td colspan='".$num_cols."'>"
			. number_format($page['total_records']) . " Record{$s}</td>";
			$count = $page['total_records'];
		}

		$out.= $t . "\t</tfoot>";
		$out.= $t . "\t<tbody>";
		$dynamic_add = $this->dynamic_add;
		$this->dynamic_add = false;
		$out.= parent::__toString();
		$this->dynamic_add = $dynamic_add;
		$out.= $t . "\t</tbody>";

		$out.= $t . "</table>";

		//Kill the remove link and add the new row button
		if($this->format_mode == Format::FORM && $this->dynamic_add):
			$this->removeChild($remove_link);
			$out .= $t . '<button class="add-row event" data-count="'.$count.'" data-which="'.$this->ensureElementID().'" data-click-handler="datasetAddRow" data-icons=\'{"primary": "ui-icon-plus"}\'>Add Row</button>';
		endif;

		$out .= $this->outputPages();
		return $out;
	}

	/**
	 * Implements count from the Countable interface
	 */
	function count(){
		return count($this->value);
	}


	/**
	* Outputs the pagination
	*/
	function outputPages(){

		$out = "";

		$info = $this->pagination_info;

		if(empty($info['total_pages']) || $info['total_pages'] < 2) return;

		//Output the prev link
		$out.= "\n" . '<ul class="pagination">';
		if($info['current_page'] == 1):
			$out.= '<li><span class="disabled">&laquo; Previous</span></li>';
		else:
			$p = $info['current_page'] - 1;
		if($p <= 1) $p = 1;
			$out.= '<li><a href="'.$this->link($p).'">&laquo; Previous</a></li>';
		endif;

		$pages = range(1, $info['total_pages']);
		$links = array();

		//Figure out what pages are displayed in the middle
		$mid = self::$num_before + self::$num_after;
		$end = self::$num_after + self::$num_end;
		if($info['current_page'] < $mid):
			//We are near the beginning
			$slice = range(1, $info['total_pages'] > $mid + 1 ? $mid + 1 : $info['total_pages']);
		elseif($info['total_pages'] - $info['current_page'] < $end && $info['total_pages'] > $mid):
			//We are near the end
			$slice = range($info['total_pages'] - $mid, $info['total_pages']);
		elseif($info['current_page'] < $end):
			//We are very close to the beginning
			$slice = range(1, $info['total_pages'] > $mid + 1 ? $info['current_page'] + self::$num_after : $info['total_pages']);
		else:
			//We are somewhere in the beginning
			$slice = array_slice($pages, $info['current_page'] - self::$num_before - 1, $mid + 1);
		endif;

		//Build an array of links
		$links = array();

		if(self::$num_end) $links = array_merge($links, range(0, self::$num_end));

		$links = array_merge($links, $slice);

		if(self::$num_end) $links = array_merge($links, range($info['total_pages'] - self::$num_end + 1, $info['total_pages']));

		$links = array_unique($links);
		asort($links);

		//Output the main pagination
		$index = $links[0] - 1;
		foreach($links as $i):

			if(!$i):
				$index = 0;
				continue;
			endif;

			if($index != $i - 1) $out.= '<li><span class="separator">&hellip;</span></li>';

			if($i == $info['current_page']):
				$out.= '<li><span class="selected">' . $i . '</span></li>';
			else:
				$out.= '<li><a href="'.$this->link($i).'">' . $i . '</a></li>';
			endif;

			$index = $i;

		endforeach;

		//Output the next link
		if($info['current_page'] == $info['total_pages'] || $info['total_pages'] < 2):
			$out.= '<li><span class="disabled">Next &raquo;</span></li>';
		else:
			$out.= '<li><a href="'.$this->link($info['current_page'] + 1).'">Next &raquo;</a></li>';
		endif;

		$out.= "\n" . '</ul>' . "\n";

		return $out;

	}

	/**
	 * Generates a table link for pagination
	 */
	protected function link($page){
		return $this->injector->qs->replace(array("page" => $page));
	}

	/**
	 * Formats a cell header
	 *
	 * When sortable, assumes that content is sorted by the first column by default
	 */
	protected function format_header(FormField $child){

		$out = "";

		//Figure out the column label
		$name = $child->getLabel() ?: $child->getName();

		//Do not modify the header when exporting
		if($this->format_mode != Format::HTML) return $name;

		$qs = $this->injector->qs;

		//Is this a sortable column?
		if(!empty($child->is_sortable)) {
			$out.= '<a href="' . $qs->sort($child->ordinal_position) . '">';
			$out.= \Helpers::entify($name);
			$out.= '</a>';
		}else{
			$out.= \Helpers::entify($name);
		}

		return $out;
	}

	/**
	 * Output a button for multiple sorting when child columns are sortable
	 */
	protected function renderSortableButton(){
		if(!$this->enable_multisort) return;
		$output = false;
		foreach($this->proxy->getChildren() as $child):
			if(!empty($child->is_sortable)):
				$output = true;
				break;
			endif;
		endforeach;
		if(!$output) return;
		ob_start();
?>
<button style="margin-bottom: 0.5em" class="event" data-click-handler="sortDataSet" data-click-args='{"which": "<?=$this->ensureElementID();?>"}' data-icons='{"primary": "ui-icon-carat-2-n-s"}'>Sorting Options</button>

<table id="sort-row-clone" class="hidden">
	<tr class="event" data-click-handler="function(){$(this).find('INPUT:radio').attr('checked', 'checked');}">
		<td class="center"><input name="dialog-sort-sel" type="radio"></td>
		<td class="center">Sort By</td>
		<td><select class="dialog-sort-col" name="sort[]"></select></td>
		<td>
			<select class="dialog-sort-order" name="order[]">
				<option value="asc">Ascending</option>
				<option value="desc">Descending</option>
			</select>
		</td>
	</tr>
</table>

<div id="sort" title="Sorting Options" class="dialog" data-dialog-ok="sortApply" data-modal="1" data-min-width="460" data-min-height="460" data-buttons="[btnOK, btnCancel]">
	<div class="menu center">
		<button class="event" data-click-handler="sortAddRow" data-icons='{"primary": "ui-icon-plus"}'>Add Level</button>
		<button class="event" data-click-handler="sortRemoveRow" data-icons='{"primary": "ui-icon-minus"}'>Delete Level</button>
		<button class="event" data-click-handler="sortMoveUp" data-icons='{"primary": "ui-icon-triangle-1-n"}'>Move Up</button>
		<button class="event" data-click-handler="sortMoveDown" data-icons='{"primary": "ui-icon-triangle-1-s"}'>Move Down</button>
	</div>
	<table class="table">
		<thead>
			<tr>
				<th></th>
				<th></th>
				<th>Column</th>
				<th>Sort Direction</th>
			</tr>
		</thead>
		<tbody>
		</tbody>
	</table>
</div>
<?
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	protected function renderExportButton(){
		if(!$this->enable_export) return;
		$export_qs = new QueryString();
		$export_qs->export = 1;
		ob_start();
		?>
		<button style="margin-bottom: 0.5em" class="event" data-click-handler="function(){window.location='<?=$export_qs;?>';}">
			<img src="<?=WEB_PATH;?>images/16x16/export.png" /> Export to Excel
		</button>
		<?
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	/**
	 * Outputs the dataset to a file.
	 * @var string $filename the filename to use for the download
	 * @var boolean $download whether or not to force a download of the exported file
	 */
	function exportData($filename = null, $download = true){

		$filename = $filename ?: $this->export_filename ?: "export.csv";

		//Prepare the file download
		ob_end_clean();
		header("Content-Type: text/csv");
		header("Content-Disposition: ".($download ? 'attachment' : 'inline')."; filename=\"".$filename."\"");
		$handle = fopen("php://output", "w");

		//Ouput headers
		$data = array();
		foreach($this->proxy->getChildren() as $child) $data[] = $child->getLabel(false);
		fputcsv($handle, $data);

		foreach($this->getValue(Format::EXPORT) as $row) fputcsv($handle, $row);

		//Close the file
		fclose($handle);
		exit;


	}

}