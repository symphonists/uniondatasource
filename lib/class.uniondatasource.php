<?php

require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.datasourcemanager.php');

Class UnionDatasource extends Datasource {

	/**
	 * An array of Field objects, used to stop unnecessary creation of field objects
	 * @var array
	 */
	public static $field_pool = array();

	/**
	 * @var DatasourceManager
	 */
	public static $datasourceManager = null;

	/**
	 * @var EntryManager
	 */
	public static $entryManager = null;

	/**
	 * An associative array of the datasources that should become one.
	 * This has two keys, `datasource` and `entries`
	 * @var array
	 */
	protected $datasources = array();

	/**
	 * An array containing all the `Entry` objects and the total number of
	 * entries considered for the union.
	 * @var array
	 */
	protected $entry_objects = array(
		'entries' => array(),
		'total-entries' => 0
	);

	/**
	 * An associative array, with the key being the Sort `field_id` and the value
	 * being the column name that the Field internally uses to sort on in the SQL
	 * @var array
	 */
	protected $sort = array();

	/**
	 * Called from the Datasource, this function will loop over `dsParamUNION`
	 * and create new Datasource objects. The `dsParamSORT` for each of these
	 * datasources will be evaluated to get the `field_id` and the internal column
	 * that the field uses to do sorting.
	 *
	 * @param array $param_pool
	 * @return XMLElement
	 */
	public function grab(&$param_pool = null) {
		if(!isset(self::$datasourceManager)) {
			self::$datasourceManager = new DatasourceManager(Symphony::Engine());
		}

		if(!isset(self::$entryManager)) {
			self::$entryManager = new EntryManager(Symphony::Engine());
		}

		// Loop over all the unions and get a Datasource object
		foreach($this->dsParamUNION as $handle) {
			$this->datasources[$handle] = array(
				'datasource' => self::$datasourceManager->create(str_replace('-','_', $handle), array(), true),
				'entries' => array()
			);

			$ds = $this->datasources[$handle]['datasource'];
			$sort_field_id = self::$entryManager->fieldManager->fetchFieldIDFromElementName($ds->dsParamSORT, $ds->getSource());

			if(!isset(self::$field_pool[$sort_field_id]) || !self::$field_pool[$sort_field_id] instanceof Field) {
				self::$field_pool[$sort_field_id] = self::$entryManager->fieldManager->fetch($sort_field_id);
			}

			$joins = $where = $sort = "";
			self::$field_pool[$sort_field_id]->buildSortingSQL($joins, $where, $sort);

			// We just want the column that the field uses internally to sort by with MySQL
			// We'll use this field and sort in PHP instead
			preg_match('/ORDER BY `ed`\.(.*) (ASC|DESC)$/', $sort, $matches);

			$this->sort[$sort_field_id] = str_replace('`', '', $matches[1]);
		}

		// Loop over all the datasource objects, getting the Entry ID's
		foreach($this->datasources as $handle => $datasource) {
			$entries = $this->grab_entries($datasource['datasource']);
			if(is_array($entries) && !empty($entries)) {
				$this->datasources[$handle]['entries'] = $this->getEntryIDs($entries['records']);

				$this->entry_objects['entries'] = array_merge($this->entry_objects['entries'], $entries['records']);
				$this->entry_objects['total-entries'] = $this->entry_objects['total-entries'] + $entries['total-entries'];
			}
		}

		// Get the SORT field that should be used
		usort($this->entry_objects['entries'], array($this, 'sortEntries'));

		// Apply the pagination of this datasource
		if($this->dsParamPAGINATERESULTS == 'yes') {
			$this->entry_objects['entries'] = array_slice(
				$this->entry_objects['entries'],
				($this->dsParamSTARTPAGE == 1) ? 0 : ($this->dsParamSTARTPAGE - 1) * $this->dsParamLIMIT,
				$this->dsParamLIMIT,
				true
			);
		}

		return $this->output($param_pool);
	}

	/**
	 * Given a Datasource, return an array of Entry objects. This takes into account
	 * all filtering, sorting of each of the datasources before the union takes place.
	 * The majority of this code is sliced from Symphony's `datasource.section.php` file.
	 *
	 * @todo Check Grouping
	 * @todo Check Pagination
	 * @todo Check Filtering
	 *
	 * @param Datasource $datasource
	 * @return array
	 *  An array of Entry objects for the given `$datasource`
	 */
	public function grab_entries(Datasource $datasource) {
		$where = NULL;
		$joins = NULL;
		$group = false;

		if(!$section = self::$entryManager->sectionManager->fetch($datasource->getSource())){
			$about = $datasource->about();
			trigger_error(__('The section associated with the data source <code>%s</code> could not be found.', array($about['name'])), E_USER_ERROR);
		}

		if(is_array($datasource->dsParamFILTERS) && !empty($datasource->dsParamFILTERS)){
			foreach($datasource->dsParamFILTERS as $field_id => $filter){
				if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;

				if(!is_array($filter)){
					$filter_type = $this->__determineFilterType($filter);

					$value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);
					$value = array_map('trim', $value);

					$value = array_map(array('Datasource', 'removeEscapedCommas'), $value);
				}

				else $value = $filter;

				if(!isset(self::$field_pool[$field_id]) || !is_object(self::$field_pool[$field_id]))
					self::$field_pool[$field_id] =& self::$entryManager->fieldManager->fetch($field_id);

				if($field_id != 'id' && $field_id != 'system:date' && !(self::$field_pool[$field_id] instanceof Field)){
					throw new Exception(
						__(
							'Error creating field object with id %1$d, for filtering in data source "%2$s". Check this field exists.',
							array($field_id, $datasource->dsParamROOTELEMENT)
						)
					);
				}

				if($field_id == 'id') {
					$where = " AND `e`.id IN ('".implode("', '", $value)."') ";
				}
				else if($field_id == 'system:date') {
					require_once(TOOLKIT . '/fields/field.date.php');
					$date = new fieldDate(Frontend::instance());

					// Create an empty string, we don't care about the Joins, we just want the WHERE clause.
					$empty = "";
					$date->buildDSRetrievalSQL($value, $empty, $where, ($filter_type == DS_FILTER_AND ? true : false));

					$where = preg_replace('/`t\d+`.value/', '`e`.creation_date', $where);
				}
				else{
					// For deprecated reasons, call the old, typo'd function name until the switch to the
					// properly named buildDSRetrievalSQL function.
					if(!self::$field_pool[$field_id]->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false))){ $this->_force_empty_result = true; return; }
					if(!$group) $group = self::$field_pool[$field_id]->requiresSQLGrouping();
				}
			}
		}

		if($datasource->dsParamSORT == 'system:id') self::$entryManager->setFetchSorting('id', $datasource->dsParamORDER);
		elseif($this->dsParamSORT == 'system:date') self::$entryManager->setFetchSorting('date', $datasource->dsParamORDER);
		else self::$entryManager->setFetchSorting(self::$entryManager->fieldManager->fetchFieldIDFromElementName($datasource->dsParamSORT, $datasource->getSource()), $datasource->dsParamORDER);

		// combine INCLUDEDELEMENTS and PARAMOUTPUT into an array of field names
		$datasource_schema = $datasource->dsParamINCLUDEDELEMENTS;
		if (!is_array($datasource_schema)) $datasource_schema = array();
		if ($datasource->dsParamPARAMOUTPUT) $datasource_schema[] = $datasource->dsParamPARAMOUTPUT;
		if ($datasource->dsParamGROUP) $datasource_schema[] = self::$entryManager->fieldManager->fetchHandleFromID($datasource->dsParamGROUP);

		$entries = self::$entryManager->fetchByPage(
			1,
			$datasource->getSource(),
			NULL,
			$where, $joins, $group,
			false,
			true,
			$datasource_schema
		);

		return $entries;
	}

	/**
	 * Given an array of Entry objects, this function will
	 * return an array of Entry ID's
	 *
	 * @param array $entries
	 *  An array of Entry objects
	 * @return array
	 *  An array of Entry ID's
	 */
	public function getEntryIDs(Array $entries) {
		$ids = array();

		foreach($entries as $entry) {
			$ids[] = $entry->get('id');
		}

		return $ids;
	}

	/**
	 * Given two `Entry` objects, this function will preform sorting on the
	 * data for the `Entry` objects using either < or `strnatcmp`. This function
	 * takes into account the current datasource's sort direction
	 *
	 * @param Entry $a
	 * @param Entry $b
	 * @return 0, -1 or 1
	 */
	public function sortEntries($a, $b) {
		$a = $a->getData();
		$b = $b->getData();

		$x = $y = null;

		foreach($this->sort as $sort_field_id => $sort_column) {
			if(array_key_exists($sort_field_id, $a)) {
				$x = $a[$sort_field_id][$sort_column];
			}

			if(array_key_exists($sort_field_id, $b)) {
				$y = $b[$sort_field_id][$sort_column];
			}
		}

		// This is horrible...
		if($this->dsParamORDER == "desc") {
			if(ctype_digit($x)) {
				return $x < $y;
			}
			else return strnatcmp($x, $y);
		}
		else {
			if(ctype_digit($x)) {
				return $x > $y;
			}
			else return strnatcmp($y, $x);
		}
	}

	/**
	 * Called to take the `$this->entry_objects` and output them as XML. This uses
	 * the Entry datasources, not this datasource to create Parameters and loop over
	 * `dsParamINCLUDEDELEMENTS`. The majority of this code is sliced from Symphony's
	 * `datasource.section.php` file.
	 *
	 * @param array $param_pool
	 */
	public function output(&$param_pool) {
		$result = new XMLElement($this->dsParamROOTELEMENT);

		// Add Pagination
		if(is_array($this->dsParamINCLUDEDELEMENTS) && in_array('system:pagination', $this->dsParamINCLUDEDELEMENTS)) {
			$pagination_element = General::buildPaginationElement(
				$this->entry_objects['total-entries'],
				max(1, ceil($this->entry_objects['total-entries'] * (1 / $this->dsParamLIMIT))),
				$this->dsParamLIMIT,
				$this->dsParamSTARTPAGE
			);

			if($pagination_element instanceof XMLElement && $result instanceof XMLElement){
				$result->prependChild($pagination_element);
			}
		}

		foreach($this->datasources as $handle => $ds) {
			if(!$section = self::$entryManager->sectionManager->fetch($ds['datasource']->getSource())){
				$about = $ds['datasource']->about();
				trigger_error(__('The section associated with the data source <code>%s</code> could not be found.', array($about['name'])), E_USER_ERROR);
			}

			$this->datasources[$handle]['section'] = $section;

			$result->appendChild(
				new XMLElement('section', $section->get('name'), array('id' => $section->get('id'), 'handle' => $section->get('handle')))
			);
		}

		foreach($this->entry_objects['entries'] as $entry) {
			$datasource = null;
			$data = $entry->getData();

			$xEntry = new XMLElement('entry');
			$xEntry->setAttribute('id', $entry->get('id'));

			// Set the appropriate datasource for this entry
			foreach($this->datasources as $ds) {
				if(!in_array($entry->get('id'), $ds['entries'])) continue;

				$datasource = $ds['datasource'];
				$section = $ds['section'];
			}

			$xEntry->setAttribute('section-handle', $section->get('handle'));

			if(isset($datasource->dsParamPARAMOUTPUT)){
				if($datasource->dsParamPARAMOUTPUT == 'system:id') $param_pool[$key][] = $entry->get('id');
				elseif($datasource->dsParamPARAMOUTPUT == 'system:date') $param_pool[$key][] = DateTimeObj::get('c', $entry->creationDate);
				elseif($datasource->dsParamPARAMOUTPUT == 'system:author') $param_pool[$key][] = $entry->get('author_id');
			}

			foreach($data as $field_id => $values){
				if(!isset(self::$field_pool[$field_id]) || !self::$field_pool[$field_id] instanceof Field) {
					self::$field_pool[$field_id] =& self::$entryManager->fieldManager->fetch($field_id);
				}

				if(isset($datasource->dsParamPARAMOUTPUT) && $datasource->dsParamPARAMOUTPUT == self::$field_pool[$field_id]->get('element_name')){
					if(!isset($param_pool[$key]) || !is_array($param_pool[$key])) $param_pool[$key] = array();

					$param_pool_values = self::$field_pool[$field_id]->getParameterPoolValue($values);

					if(is_array($param_pool_values)){
						$param_pool[$key] = array_merge($param_pool_values, $param_pool[$key]);
					}
					else{
						$param_pool[$key][] = $param_pool_values;
					}
				}

				foreach ($datasource->dsParamINCLUDEDELEMENTS as $handle) {
					list($handle, $mode) = preg_split('/\s*:\s*/', $handle, 2);
					if(self::$field_pool[$field_id]->get('element_name') == $handle) {
						self::$field_pool[$field_id]->appendFormattedElement($xEntry, $values, ($datasource->dsParamHTMLENCODE ? true : false), $mode, $entry->get('id'));
					}
				}
			}

			$result->appendChild($xEntry);

			if(in_array('system:date', $datasource->dsParamINCLUDEDELEMENTS)){
				$xEntry->appendChild(
					General::createXMLDateObject(
						DateTimeObj::get('U', $entry->creationDate),
						'system-date'
					)
				);
			}
		}

		return $result;
	}
}