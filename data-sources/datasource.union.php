<?php

	require_once TOOLKIT . '/class.datasource.php';
	require_once FACE . '/interface.datasource.php';

	Class UnionDatasource extends SectionDataSource implements iDatasource {

		/**
		 * An array of Field objects, used to stop unnecessary creation of field objects
		 * @var array
		 */
		public static $field_pool = array();

		public static $system_parameters = array(
			'system:id',
			'system:author',
			'system:date'
		);

		/**
		 * An associative array of the datasources that should become one.
		 * This has two keys, `datasource` and `entries`
		 * @var array
		 */
		public $datasources = array();

		public static function getName() {
			return __('Union Datasource');
		}

		public static function getClass() {
			return __CLASS__;
		}

		public function getSource() {
			return self::getClass();
		}

		public static function getTemplate(){
			return EXTENSIONS . '/uniondatasource/templates/blueprints.datasource.tpl';
		}

		public function settings() {
			$settings = array();
			$class = self::getClass();

			$settings[$class]['union'] = $this->dsParamUNION;
			$settings[$class]['paginate_results'] = isset($this->dsParamPAGINATERESULTS) ? $this->dsParamPAGINATERESULTS : 'yes';
			$settings[$class]['page_number'] = $this->dsParamSTARTPAGE;
			$settings[$class]['max_records'] = $this->dsParamLIMIT;
			$settings[$class]['redirect_on_empty'] = isset($this->dsParamREDIRECTONEMPTY) ? $this->dsParamREDIRECTONEMPTY : 'no';
			$settings[$class]['required_url_param'] = $this->dsParamREQUIREDPARAM;

			return $settings;
		}

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		/**
		 * Returns the source value for display in the Datasources index
		 *
		 * @param string $file
		 *  The path to the Datasource file
		 * @return string
		 */
		public function getSourceColumn($handle) {
			$datasources = DatasourceManager::listAll();
			$datasource = DatasourceManager::create($handle, array(), false);

			if(isset($datasource->dsParamUNION)) {
				$union = array();
				foreach($datasource->dsParamUNION as $handle) {
					$handle = str_replace('-','_',$handle);
					if(array_key_exists($handle, $datasources)) {
						$union[] = Widget::Anchor(
							$datasources[$handle]['name'], SYMPHONY_URL . '/blueprints/datasources/edit/' . $handle . '/'
						)->generate();
					}
				}

				return implode(', ', $union);
			}
			else {
				return 'Union Datasource';
			}
		}

		/**
		 * Returns an associative array of all the datasources that can be
		 * used in a Union.
		 *
		 * @see isValidDatasource
		 * @return array
		 *  An associative array, key is the handle of the datasource, value
		 *  is the object
		 */
		protected static function getValidDatasources() {
			$datasources = DatasourceManager::listAll();
			foreach($datasources as $handle => $ds) {
				$datasource = DatasourceManager::create($handle, array(), false);

				unset($datasources[$handle]);

				if(!self::isValidDatasource($datasource)) continue;

				$datasources[str_replace('_','-',$handle)] = $datasource;
			}

			return $datasources;
		}

		/**
		 * Returns boolean if the Datasource is available for UNION.
		 * This means that they extend `DataSource` class, have a `getSource`
		 * function and the return of that function is an integer
		 *
		 * @param Datasource $datasource
		 * @return boolean
		 */
		protected static function isValidDatasource(Datasource $datasource) {
			// Rules out CacheableDatasource/UnionDatasource etc.
			$valid_class = (get_parent_class($datasource) == "DataSource" || get_parent_class($datasource) == "SectionDatasource");

			// Rules out custom Datasources
			// Rules out DynamicXML, Static, Navigation and Author datasources
			if(method_exists($datasource, 'getSource')) {
				$source = $datasource->getSource();
				$source = is_numeric($source);
			}

			return $valid_class && $source;
		}

		/**
		 * Given the datasources that will form the union, find any dependencies
		 * that they might have and add them to the `$template`
		 *
		 * @param array $union
		 * @param string $template
		 */
		protected static function parseDependencies(array $union, &$template) {
			$dependencies = array();
			foreach($union as $ds) {
				$datasource = DatasourceManager::create(str_replace('-','_',$ds), array(), false);
				$dependencies = array_merge($dependencies, $datasource->getDependencies());
			}

			if(empty($dependencies)) return;

			$dependencies = General::array_remove_duplicates($dependencies);
			$template = str_replace('<!-- DS DEPENDENCY LIST -->', "'" . implode("', '", $dependencies) . "'", $template);
		}

		/**
		 * Builds the datasources that are required for this union so they can
		 * be saved in the Datasource file
		 *
		 * @param array $union
		 *  An associative array of where the key is the union handle prefix
		 *  and the value is the union datasource name.
		 * @param string $template
		 *  The template file, as defined by `getTemplate()`
		 * @return string
		 *  The template injected with the Union (if any).
		 */
		public function injectUnion(array $union, &$template){
			if(empty($union)) return;

			$placeholder = '<!-- UNION -->';
			$string = 'public $dsParamUNION = array(' . PHP_EOL;

			foreach($union as $val){
				if(trim($val) == '') continue;
				$string .= "\t\t\t'" . addslashes($val) . "'," . PHP_EOL;
			}

			$string .= "\t\t);" . PHP_EOL . "\t\t" . $placeholder;
			$template = str_replace($placeholder, trim($string), $template);
		}

		private static function __isValidPageString($string){
			return (bool)preg_match('/^(?:\{\$[\w-]+(?::\$[\w-]+)*(?::\d+)?}|\d+)$/', $string);
		}

	/*-------------------------------------------------------------------------
		Editor
	-------------------------------------------------------------------------*/

		public static function buildEditor(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null) {
			$class = self::getClass();
			$settings = $settings[$class];

			if(!is_null($handle) and isset($settings['union'])) {
				$sort_ds = DatasourceManager::create(str_replace('-','_', $settings['union'][0]), array(), false);
				$sort_about = $sort_ds->about();
			}

			// Add in custom assets
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/uniondatasource/assets/uniondatasource.datasources.css', 'screen', 101);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/uniondatasource/assets/uniondatasource.datasources.js', 104);

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . $class);
			$fieldset->appendChild(new XMLElement('legend', self::getName()));

			$p = new XMLElement('p', __('These data sources will have their output combined into a single data source and executed in this order.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);

			// Datasources
			$div = new XMLElement('div');
			$p = new XMLElement('p', __('Add data sources to union'));
			$p->appendChild(new XMLElement('i', __('Optional')));
			$p->setAttribute('class', 'label');
			$div->appendChild($p);

			$ol = new XMLElement('ol');
			$ol->setAttribute('class', 'union-duplicator');
			$ol->setAttribute('data-add', __('Add data source'));
			$ol->setAttribute('data-remove', __('Remove data source'));

			$datasources = self::getValidDatasources();
			$i = 0;
			foreach($datasources as $handle => $datasource) {
				$about = $datasource->about();
				$source = SectionManager::fetch($datasources[$handle]->getSource());

				if(($source instanceof Section) === false) continue;

				// Template
				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique template');
				$li->setAttribute('data-type', $handle);

				// Header
				$header = new XMLElement('header');
				$header->setAttribute('data-name', $about['name'] . ' (' . $source->get('name') . ')');
				$header->appendChild(
					new XMLElement('h4', Widget::Anchor($about['name'], SYMPHONY_URL . '/blueprints/datasources/edit/'. str_replace('-', '_', $handle) .'/', __('View the %s Data Source', array($about['name']))))
				);
				$header->appendChild(
					new XMLElement('span', $source->get('name'), array('class' => 'type'))
				);
				$header->appendChild(
					Widget::Input('fields[' . $class . '][union][' . $i . ']', $handle, 'hidden')
				);
				$header->appendChild(
					Widget::Input('fields[' . $class . '][union-sort][' . $i . ']', $datasource->dsParamSORT, 'hidden')
				);
				$header->appendChild(
					Widget::Input('fields[' . $class . '][union-order][' . $i . ']', $datasource->dsParamORDER, 'hidden')
				);

				$li->appendChild($header);
				$ol->appendChild($li);
				$i++;
			}

			if(is_array($settings['union']) && !empty($settings['union'])){
				foreach($settings['union'] as $key => $handle) {
					if(!isset($datasources[$handle])) continue;

					$about = $datasources[$handle]->about();
					$source = SectionManager::fetch($datasources[$handle]->getSource());

					if(($source instanceof Section) === false) continue;

					// Instance
					$li = new XMLElement('li');
					$li->setAttribute('class', 'unique');
					$li->setAttribute('data-type', $handle);

					// Header
					$header = new XMLElement('header');
					$header->setAttribute('data-name', $about['name'] . ' (' . $source->get('name') . ')');
					$header->appendChild(
						new XMLElement('h4', Widget::Anchor($about['name'], SYMPHONY_URL . '/blueprints/datasources/edit/'. str_replace('-', '_', $handle) .'/', __('View the %s Data Source', array($about['name']))))
					);
					$header->appendChild(
						new XMLElement('span', $source->get('name'), array('class' => 'type'))
					);

					$header->appendChild(
						Widget::Input('fields[' . $class . '][union][' . $key . ']', $handle, 'hidden')
					);
					$header->appendChild(
						Widget::Input('fields[' . $class . '][union-sort][' . $key . ']', $datasources[$handle]->dsParamSORT, 'hidden')
					);
					$header->appendChild(
						Widget::Input('fields[' . $class . '][union-order][' . $key . ']', $datasources[$handle]->dsParamORDER, 'hidden')
					);
					$li->appendChild($header);
					$ol->appendChild($li);
				}
			}

			if(isset($errors[$class]['union'])) {
				$div->appendChild(
					Widget::Error($ol, $errors[$class]['union'])
				);
			}
			else {
				$div->appendChild($ol);
			}

			$fieldset->appendChild($div);
			$wrapper->appendChild($fieldset);

		// Add Sorting/Pagination
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . $class);
			$fieldset->appendChild(new XMLElement('legend', __('Sorting and Limiting')));

			$p = new XMLElement('p', __('All sorting is defined by the first data source in the union. Use <code>{$param}</code> syntax to limit pagination by page parameters.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');

			$label = Widget::Label(__('Sort By'));
			$options = array();

			$label->appendChild(Widget::Select('fields[sort]', $options, array(
				'class' => 'sort-by',
				'disabled' => 'disabled'
			)));
			$div->appendChild($label);

			$label = Widget::Label(__('Sort Order'));

			$options = array(
				array('asc', ('asc' == $sort_ds->dsParamORDER), __('ascending')),
				array('desc', ('desc' == $sort_ds->dsParamORDER), __('descending')),
				array('random', ('random' == $sort_ds->dsParamORDER), __('random')),
			);

			$label->appendChild(Widget::Select('fields[' . $class . '][order]', $options, array(
				'class' => 'sort-order',
				'disabled' => 'disabled'
			)));
			$div->appendChild($label);

			$fieldset->appendChild($div);

			$label = Widget::Label();
			$input = array(
				Widget::Input('fields[' . $class . '][paginate_results]', NULL, 'checkbox', ($settings['paginate_results'] == 'yes' ? array('checked' => 'checked') : NULL)),
				Widget::Input('fields[' . $class . '][max_records]', $settings['max_records'], NULL, array('size' => '6', 'type' => 'text')),
				Widget::Input('fields[' . $class . '][page_number]', $settings['page_number'], NULL, array('size' => '6', 'type' => 'text'))
			);
			$label->setValue(__('%s Paginate results, limiting to %s entries per page. Return page %s', array($input[0]->generate(false), $input[1]->generate(false), $input[2]->generate(false))));

			if(isset($errors[self::getClass()]['max_records'])) $fieldset->appendChild(Widget::Error($label, $errors[$class]['max_records']));
			else if(isset($errors[self::getClass()]['page_number'])) $fieldset->appendChild(Widget::Error($label, $errors[$class]['page_number']));
			else $fieldset->appendChild($label);

			$p = new XMLElement('p', __('Failing to paginate may degrade performance if the number of entries returned is very high.'), array('class' => 'help'));
			$fieldset->appendChild($p);

			$wrapper->appendChild($fieldset);

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . $class);
			$fieldset->appendChild(new XMLElement('legend', __('Output Options')));

			$label = Widget::Label(__('Required URL Parameter'));
			$label->appendChild(new XMLElement('i', __('Optional')));
			$label->appendChild(Widget::Input('fields[' . $class . '][required_url_param]', trim($settings['required_url_param'])));
			$fieldset->appendChild($label);

			$p = new XMLElement('p', __('An empty result will be returned when this parameter does not have a value. Do not wrap the parameter with curly-braces.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);

			$label = Widget::Label();
			$input = Widget::Input('fields[' . $class . '][redirect_on_empty]', 'yes', 'checkbox', (isset($settings['redirect_on_empty']) && $settings['redirect_on_empty'] == 'yes') ? array('checked' => 'checked') : NULL);
			$label->setValue(__('%s Redirect to 404 page when no results are found', array($input->generate(false))));
			$fieldset->appendChild($label);

			$wrapper->appendChild($fieldset);
		}

		public static function validate(array &$settings, array &$errors) {
			$settings = $settings[self::getClass()];

			if(!is_array($settings['union']) || empty($settings['union'])) {
				$errors[self::getClass()]['union'] = __('At least one datasource is required to build a Union Datasource');
			}

			if(strlen(trim($settings['page_number'])) == 0 || (is_numeric($settings['page_number']) && $settings['page_number'] < 1)){
				if (isset($settings['paginate_results'])) {
					$errors[self::getClass()]['page_number'] = __('A page number must be set');
				}
			}
			else if(!self::__isValidPageString($settings['page_number'])){
				$errors[self::getClass()]['page_number'] = __('Must be a valid number or parameter');
			}

			if(strlen(trim($settings['max_records'])) == 0 || (is_numeric($settings['max_records']) && $settings['max_records'] < 1)){
				if (isset($settings['paginate_results'])) {
					$errors[self::getClass()]['max_records'] = __('A result limit must be set');
				}
			}
			else if(!self::__isValidPageString($settings['max_records'])){
				$errors[self::getClass()]['max_records'] = __('Must be a valid number or parameter');
			}

			return empty($errors[self::getClass()]);
		}

		public static function prepare(array $settings, array $params, $template) {
			$class = self::getClass();
			self::injectUnion($settings[$class]['union'], $template);
			self::parseDependencies($settings[$class]['union'], $template);

			$settings[$class]['paginate_results'] = ($settings[$class]['paginate_results'] == 'on') ? 'yes' : 'no';
			$settings[$class]['redirect_on_empty'] = isset($settings[$class]['redirect_on_empty']) ? 'yes' : 'no';

			return sprintf($template,
				$params['rootelement'], // rootelement
				$settings[$class]['paginate_results'],
				$settings[$class]['page_number'],
				$settings[$class]['max_records'],
				$settings[$class]['redirect_on_empty'],
				$settings[$class]['required_url_param']
			);
		}

	/*-------------------------------------------------------------------------
		Execution
	-------------------------------------------------------------------------*/

		public function execute(array &$param_pool = null) {
			return $this->grab($param_pool);
		}

		/**
		 * Called from the Datasource, this function will loop over `dsParamUNION`
		 * and create new Datasource objects.
		 *
		 * @param array $param_pool
		 * @return XMLElement
		 */
		public function grab(array &$param_pool = null) {
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$this->_param_pool = $param_pool;
			$this->data = array();

			// Loop over all the unions and get a Datasource object
			foreach($this->dsParamUNION as $handle) {
				try {
					$this->datasources[$handle]['datasource'] = DatasourceManager::create(
						str_replace('-','_', $handle), $this->_env, true
					);

					$this->datasources[$handle]['section'] = SectionManager::fetch(
						$this->datasources[$handle]['datasource']->getSource()
					);

					$result->appendChild(
						new XMLElement('section', $this->datasources[$handle]['section']->get('name'), array(
							'id' => $this->datasources[$handle]['section']->get('id'),
							'handle' => $this->datasources[$handle]['section']->get('handle')
						))
					);
				}
				catch(FrontendPageNotFoundException $ex) {
					throw $ex;
				}
				catch(Exception $ex) {
					// #13. Datasource may have been renamed or deleted
					// TODO: Update when 2.3.1 is out to automatically rename UD files
					// when Datasources are deleted or renamed.
					$result->appendChild(
						new XMLElement('error', __('The %s data source is missing and has been ignored.', array('<code>' . $handle . '</code>')))
					);
				}
			}

			// If the Datasource has $this->dsParamREQUIREDPARAM set and that param
			// doesn't exist, then _force_empty_result will be set to true before this
			// Datasource is executed (this happens in Frontend Page)
			if($this->_force_empty_result == true){
				$this->emptyXMLSet($result);
				return $result;
			}

			// Loop over all the datasource objects, getting the Entry ID's
			foreach($this->datasources as $handle => $datasource) {
				$data = $this->grab_sql($datasource['datasource']);

				if(!isset($data['section'])) continue;

				$this->data['section'][key($data['section'])] = current($data['section']);
				$this->data['sort'][] = $data['sort'];
				$this->data['sql'][] = $data['sql'];
			}

			$entries = $this->fetchByPage(
				($this->dsParamSTARTPAGE == 1) ? 0 : ($this->dsParamSTARTPAGE - 1) * $this->dsParamLIMIT,
				$this->dsParamLIMIT
			);

			/**
			 * Immediately after building entries allow modification of the Data Source entry list
			 *
			 * @delegate DataSourceEntriesBuilt
			 * @param string $context
			 * '/frontend/'
			 * @param Datasource $datasource
			 * @param array $entries
			 * @param array $filters
			 */
			Symphony::ExtensionManager()->notifyMembers('DataSourceEntriesBuilt', '/frontend/', array(
				'datasource' => &$this,
				'entries' => &$entries,
				'filters' => $this->dsParamFILTERS
			));

			return $this->output($result, $entries, $param_pool);
		}

	/*-------------------------------------------------------------------------
		Union Datasource functions
	-------------------------------------------------------------------------*/

		/**
		 * Given a Datasource, return an array of Entry objects. This takes into account
		 * all filtering, sorting of each of the datasources before the union takes place.
		 * The majority of this code is sliced from Symphony's `datasource.section.php` file.
		 *
		 * @param Datasource $datasource
		 * @return array
		 *  An array of Entry objects for the given `$datasource`
		 */
		public function grab_sql(Datasource $datasource) {
			$where = NULL;
			$joins = NULL;
			$group = false;

			include_once(TOOLKIT . '/class.entrymanager.php');

			if(!$section = SectionManager::fetch((int)$datasource->getSource())){
				$about = $datasource->about();
				trigger_error(__('The section associated with the data source %s could not be found.', array('<code>' . $about['name'] . '</code>')), E_USER_ERROR);
			}

			if(is_array($datasource->dsParamINCLUDEDELEMENTS)) {
				$include_pagination_element = in_array('system:pagination', $datasource->dsParamINCLUDEDELEMENTS);
			}
			else {
				$datasource->dsParamINCLUDEDELEMENTS = array();
			}

			if(isset($datasource->dsParamPARAMOUTPUT) && !is_array($datasource->dsParamPARAMOUTPUT)) {
				$datasource->dsParamPARAMOUTPUT = array($datasource->dsParamPARAMOUTPUT);
			}

			// Process Filters
			$this->processFilters($datasource, $where, $joins, $group);

			/**
			 * Instead of building Entries individually, build the where and join statements
			 * and return them. We'll make a custom `fetchByPage` function that can return
			 * the entry ID's and the values of the sort field.
			 */
			$data = array(
				'section' => array(
					$datasource->getSource() => array()
				),
				'sort' => ''
			);

			// SORTING
			$sort_field = null;

			// Handle random
			if($datasource->dsParamORDER == 'random') {
				$data['sort'] = 'ORDER BY RAND()';
			}
			// Handle 'system:id' or 'system:date' psuedo fields
			else if($datasource->dsParamSORT == 'system:id') {
				$data['sort'] = 'ORDER BY id ' . $datasource->dsParamORDER;
			}
			else if($datasource->dsParamSORT == 'system:date') {
				$data['sort'] = 'ORDER BY creation_date ' . $datasource->dsParamORDER;
			}
			// Handle real field instances
			else {
				$field = FieldManager::fetch(
					 FieldManager::fetchFieldIDFromElementName($datasource->dsParamSORT, $datasource->getSource())
				);

				$field->buildSortingSQL($joins, $where, $data['sort'], $datasource->dsParamORDER);

				if(!empty($data['sort'])) {
					// We just want the column that the field uses internally to sort by with MySQL
					// We'll use this field and sort in PHP instead
					preg_match('/ORDER BY[\s\S]*(`.*`\..*)[\s\S]*(ASC|DESC)$/i', $data['sort'], $sort_field);

					// The new ORDER BY syntax in Symphony 2.2.2 isn't compatible with what
					// we want for the purposes of UNION, so lets rewrite the ORDER BY to
					// what we do want (that is, if we have to)
					if(preg_match('/\(+/i', $data['sort'])) {
						$data['sort'] = 'ORDER BY ' . $sort_field[1] . ' ' . $sort_field[2];
					}

					$data['sort'] = preg_replace('/`(.*)`\./', '', $data['sort']);

					// New changes to sorting mean that there possibly wont't be a join
					// on the entry data table. If the join is omitted from `$joins`,
					// we'll add the default join ourselves
					preg_match('/^`(.*)`\./i', $sort_field[1], $tbl_alias);
					if(!preg_match('/`' . $tbl_alias[1] . '`/', $joins)) {
						$joins .= sprintf('
								LEFT OUTER JOIN `tbl_entries_data_%1$d` AS `%2$s`
								ON (`e`.`id` = `%2$s`.`entry_id`)
							',
							$field->get('id'),
							$tbl_alias[1]
						);
					}
				}
			}

			// combine `INCLUDEDELEMENTS`, `PARAMOUTPUT` and `GROUP` into an
			// array of field handles to optimise the `EntryManager` queries
			$datasource_schema = $datasource->dsParamINCLUDEDELEMENTS;
			if (is_array($datasource->dsParamPARAMOUTPUT)) {
				$datasource_schema = array_merge($datasource_schema, $datasource->dsParamPARAMOUTPUT);
			}
			if ($datasource->dsParamGROUP) {
				$datasource_schema[] = FieldManager::fetchHandleFromID($datasource->dsParamGROUP);
			}

			$data['section'][$datasource->getSource()] = $datasource_schema;
			$data['sql'] = sprintf('
					SELECT `e`.id as id, `e`.section_id, e.`author_id`, UNIX_TIMESTAMP(e.`creation_date`) AS `creation_date`%s
					FROM `tbl_entries` AS `e`
					%s
					WHERE `e`.`section_id` = %d
					%s
				',
				(is_array($sort_field) ? ', ' . $sort_field[1] : ''),
				$joins,
				$datasource->getSource(),
				$where
			);

			return $data;
		}

		/**
		 * This function `UNION ALL`'s the datasource SQL and then applies sorting
		 * and pagination to the query. Returns an array of Entry objects, with
		 * pagination given the number of Entry's to return and the current starting
		 * offset. eg. if there are 60 entries in a section and the pagination
		 * dictates that 15 entries per page are to be returned, by passing 2 to
		 * the `$page` parameter you could return entries 15-30.
		 *
		 * @param integer $page
		 *  The page to return, defaults to 1
		 * @param integer $entriesPerPage
		 *  The number of entries to return per page.
		 * @return array
		 *  Either an array of Entry objects, or an associative array containing
		 *  the total entries, the start position, the entries per page and the
		 *  Entry objects
		 */
		public function fetchByPage($page = 1, $entriesPerPage) {
			if(empty($this->data['sql'])) return array();

			$sql = trim(implode(" UNION ALL ", $this->data['sql']));

			// Add SQL_CALC_FOUND_ROWS to the first SELECT.
			$sql = preg_replace('/^SELECT `e`.id/', 'SELECT SQL_CALC_FOUND_ROWS `e`.id', $sql, 1);

			// Add the ORDER BY clause
			$sql = $sql . $this->data['sort'][0];

			// Apply Pagination
			if($this->dsParamPAGINATERESULTS == 'yes') {
				$sql = $sql . sprintf(' LIMIT %d, %d', $page, $entriesPerPage);
			}

			$rows = Symphony::Database()->fetch($sql);

			// Get the total rows for this query
			$total_rows = Symphony::Database()->fetchCol('total_rows', 'SELECT FOUND_ROWS() AS `total_rows`');

			$entries = array(
				'total-entries' => $total_rows[0],
				// Build Entry objects
				'records' => $this->buildEntries($rows, $this->data['section'])
			);

			return $entries;
		}

		/**
		 * Given an array of Entry data from `tbl_entries` and a section ID, return an
		 * array of Entry objects. For performance reasons, it's possible to pass an array
		 * of field handles via `$element_names`, so that only a subset of the section schema
		 * will be queried. This function currently only supports Entry from one section at a
		 * time.
		 *
		 * @param array $rows
		 *  An array of Entry data from `tbl_entries` including the Entry ID, Entry section,
		 *  the ID of the Author who created the Entry, and a Unix timestamp of creation
		 * @param array $element_names
		 *  Choose whether to get data from a subset of fields or all fields in a section,
		 *  by providing an array of field names. Defaults to null, which will load data
		 *  from all fields in a section.
		 * @return array
		 */
		public function buildEntries(array $rows, $element_names = null){
			$entries = array();
			if (empty($rows)) return $entries;

			$schema = array();

			// choose whether to get data from a subset of fields or all fields in a section
			if (!is_null($element_names) && is_array($element_names)) {
				foreach ($element_names as $section_id => $fields) {
					$field_ids = FieldManager::fetchFieldIDFromElementName($fields, $section_id);
					if(is_int($field_ids)) $field_ids = array($field_ids);

					$schema = array_merge($schema, $field_ids);
				}
			}

			$raw = array();
			$id_list_string = '';

			// Append meta data:
			foreach ($rows as $row) {
				$raw[$row['id']]['meta'] = $row;
				$id_list_string .= $row['id'] . ',';
			}
			$id_list_string = trim($id_list_string, ',');

			// Append field data:
			foreach ($schema as $field_id) {
				try{
					$row = Symphony::Database()->fetch("SELECT * FROM `tbl_entries_data_{$field_id}` WHERE `entry_id` IN ($id_list_string) ORDER BY `id` ASC");
				}
				catch(Exception $e){
					// No data due to error
					continue;
				}

				if (!is_array($row) || empty($row)) continue;

				foreach ($row as $r) {
					$entry_id = $r['entry_id'];

					unset($r['id']);
					unset($r['entry_id']);

					if (!isset($raw[$entry_id]['fields'][$field_id])) {
						$raw[$entry_id]['fields'][$field_id] = $r;
					}

					else {
						foreach ($r as $key => $value) {
							if (isset($raw[$entry_id]['fields'][$field_id][$key]) && !is_array($raw[$entry_id]['fields'][$field_id][$key])) {
								$raw[$entry_id]['fields'][$field_id][$key] = array($raw[$entry_id]['fields'][$field_id][$key], $value);
							}

							else if (!isset($raw[$entry_id]['fields'][$field_id][$key])) {
								$raw[$entry_id]['fields'][$field_id] = array($value);
							}

							else {
								$raw[$entry_id]['fields'][$field_id][$key][] = $value;
							}
						}
					}
				}
			}

			foreach ($raw as $entry) {
				$obj = EntryManager::create();

				$obj->creationDate = DateTimeObj::get('c', $entry['meta']['creation_date']);
				$obj->set('id', $entry['meta']['id']);
				$obj->set('author_id', $entry['meta']['author_id']);
				$obj->set('section_id', $entry['meta']['section_id']);

				if(isset($entry['fields']) && is_array($entry['fields'])){
					foreach ($entry['fields'] as $field_id => $data) $obj->setData($field_id, $data);
				}

				$entries[] = $obj;
			}

			return $entries;
		}

		/**
		 * Given an associative array containing the total rows that could be found
		 * for these datasources, and an array of Entries for the current pagination
		 * settings, output the Entries as XML. This uses the Entry datasources, not
		 * this datasource to create Parameters and uses their `dsParamINCLUDEDELEMENTS`.
		 * The majority of this code is sliced from Symphony's `datasource.section.php` file.
		 *
		 * @param array $entries
		 * @param array $param_pool
		 * @todo Grouping
		 *  Grouping will be very difficult with UnionDS. The current Grouping works on an
		 *  array level, rather then the springs to mind SQL GROUP BY. Grouping calls the
		 *  grouped field's `groupRecords` function, which loops over the `$entries` array
		 *  getting the data for the grouped field. The problem is that this uses the field_id,
		 *  so it cannot apply to other entries who are in different sections. This code untouchable
		 *  inside the Field class, so it would require something fairly crude I'd imagine to
		 *  replicate. Perhaps the `$entries` would have to be dissolved into same section groups and
		 *  run a group on each section before merging the array together... which has it's own set
		 *  of problems, not happening anytime soon unfortunately.
		 */
		public function output(XMLElement &$result, $entries, &$param_pool) {
			if(!isset($entries['records']) or empty($entries['records'])) {
				if($this->dsParamREDIRECTONEMPTY == 'yes'){
					throw new FrontendPageNotFoundException;
				}
			}

			// Add Pagination
			if(is_array($this->dsParamINCLUDEDELEMENTS) && in_array('system:pagination', $this->dsParamINCLUDEDELEMENTS)) {
				$pagination_element = General::buildPaginationElement(
					$entries['total-entries'],
					($this->dsParamPAGINATERESULTS == 'yes') ? max(1, ceil($entries['total-entries'] * (1 / $this->dsParamLIMIT))) : 1,
					($this->dsParamPAGINATERESULTS == 'yes' && isset($this->dsParamLIMIT) && $this->dsParamLIMIT >= 0 ? $this->dsParamLIMIT : $entries['total-entries']),
					($this->dsParamPAGINATERESULTS == 'yes' && $this->dsParamSTARTPAGE > 0 ? $this->dsParamSTARTPAGE : 1)
				);

				if($pagination_element instanceof XMLElement && $result instanceof XMLElement){
					$result->prependChild($pagination_element);
				}
			}

			// If there is no records, return early
			if(!isset($entries['records']) or empty($entries['records'])) {
				$this->emptyXMLSet($result);
				return $result;
			}

			foreach($entries['records'] as $entry) {
				$datasource = null;
				$data = $entry->getData();

				$xEntry = new XMLElement('entry');
				$xEntry->setAttribute('id', $entry->get('id'));

				// Set the appropriate datasource for this entry
				foreach($this->datasources as $ds) {
					if($entry->get('section_id') !== $ds['datasource']->getSource()) continue;

					$datasource = $ds['datasource'];
					$section = $ds['section'];

					// Setup any datasources variables ONCE.
					if(!isset($ds['datasource']->_param_pool)) {
						$ds['datasource']->_param_pool = $param_pool;
						$pool = FieldManager::fetch(array_keys($data));
						self::$field_pool += $pool;

						if (!isset($datasource->dsParamASSOCIATEDENTRYCOUNTS) || $datasource->dsParamASSOCIATEDENTRYCOUNTS == 'yes') {
							$ds['datasource']->_associated_sections = $section->fetchAssociatedSections();
						}
					}
				}

				$xEntry->setAttribute('section-handle', $section->get('handle'));
				$key = 'ds-' . $datasource->dsParamROOTELEMENT;

				// Add Associated Entry counts to the entry
				if (!empty($datasource->_associated_sections)) {
					$this->setAssociatedEntryCounts($datasource, $xEntry, $entry);
				}

				// Add the Symphony 'system:*' parameters to the param pool
				if($this->canProcessSystemParameters($datasource)) {
					$this->processSystemParameters($datasource, $entry);
				}

				foreach($data as $field_id => $values) {
					// Check to see if we have a Field object already, if not create one
					if(!isset(self::$field_pool[$field_id]) || !self::$field_pool[$field_id] instanceof Field) {
						self::$field_pool[$field_id] =& FieldManager::fetch($field_id);
					}

					// Process output parameters
					$this->processOutputParameters($datasource, $entry, $field_id, $values);

					if (!$datasource->_param_output_only) foreach ($datasource->dsParamINCLUDEDELEMENTS as $handle) {
						list($handle, $mode) = preg_split('/\s*:\s*/', $handle, 2);
						if(self::$field_pool[$field_id]->get('element_name') == $handle) {
							self::$field_pool[$field_id]->appendFormattedElement($xEntry, $values, ($datasource->dsParamHTMLENCODE ? true : false), $mode, $entry->get('id'));
						}
					}
				}

				if($datasource->_param_output_only) return true;

				// Add in the system:date to the output
				if(in_array('system:date', $datasource->dsParamINCLUDEDELEMENTS)){
					$xEntry->appendChild(
						General::createXMLDateObject(
							DateTimeObj::get('U', $entry->creationDate),
							'system-date'
						)
					);
				}

				$result->appendChild($xEntry);
			}

			return $result;
		}

	/*-------------------------------------------------------------------------
		Legacy functions for Datasources that don't inherit SectionsDatasource
	-------------------------------------------------------------------------*/

		public function processFilters(Datasource $datasource, &$where, &$joins, &$group) {
			if(!is_array($datasource->dsParamFILTERS) || empty($datasource->dsParamFILTERS)) return;

			$pool = FieldManager::fetch(array_filter(array_keys($datasource->dsParamFILTERS), 'is_int'));
			self::$field_pool += $pool;

			foreach($datasource->dsParamFILTERS as $field_id => $filter){

				if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;

				if(!is_array($filter)){
					$filter_type = $datasource->__determineFilterType($filter);

					$value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);
					$value = array_map('trim', $value);
					$value = array_map(array('Datasource', 'removeEscapedCommas'), $value);
				}

				else $value = $filter;

				if($field_id != 'id' && $field_id != 'system:date' && !(self::$field_pool[$field_id] instanceof Field)){
					throw new Exception(
						__(
							'Error creating field object with id %1$d, for filtering in data source %2$s. Check this field exists.',
							array($field_id, '<code>' . $this->dsParamROOTELEMENT . '</code>')
						)
					);
				}

				if($field_id == 'id') {
					$c = 'IN';
					if(stripos($value[0], 'not:') === 0) {
						$value[0] = preg_replace('/^not:\s*/', null, $value[0]);
						$c = 'NOT IN';
					}

					$where = " AND `e`.id " . $c . " ('".implode("', '", $value)."') ";
				}
				else if($field_id == 'system:date') {
					require_once(TOOLKIT . '/fields/field.date.php');
					$date = new fieldDate();
					$date->buildDSRetrievalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false));

					$where = preg_replace('/`t\d+`.value/', '`e`.creation_date', $where);
				}
				else {
					// For deprecated reasons, call the old, typo'd function name until the switch to the
					// properly named buildDSRetrievalSQL function.
					if(!self::$field_pool[$field_id]->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false))){ $datasource->_force_empty_result = true; return; }
					if(!$group) $group = self::$field_pool[$field_id]->requiresSQLGrouping();
				}
			}
		}

		public function setAssociatedEntryCounts(Datasource $datasource, XMLElement &$xEntry, Entry $entry) {
			$associated_entry_counts = $entry->fetchAllAssociatedEntryCounts($datasource->_associated_sections);
			if(!empty($associated_entry_counts)){
				foreach($associated_entry_counts as $section_id => $count){
					foreach($datasource->_associated_sections as $section) {
						if ($section['id'] == $section_id) $xEntry->setAttribute($section['handle'], (string)$count);
					}
				}
			}
		}

		public function canProcessSystemParameters(Datasource $datasource) {
			if(!is_array($datasource->dsParamPARAMOUTPUT)) return false;

			foreach(self::$system_parameters as $system_parameter) {
				if(in_array($system_parameter, $datasource->dsParamPARAMOUTPUT) === true) {
					return true;
				}
			}

			return false;
		}

		public function processSystemParameters(Datasource $datasource, Entry $entry) {
			if(!isset($datasource->dsParamPARAMOUTPUT)) return;

			// Support the legacy parameter `ds-datasource-handle`
			$key = 'ds-' . $datasource->dsParamROOTELEMENT;
			$singleParam = count($datasource->dsParamPARAMOUTPUT) == 1;

			foreach($datasource->dsParamPARAMOUTPUT as $param) {
				// The new style of paramater is `ds-datasource-handle.field-handle`
				$param_key = $key . '.' . str_replace(':', '-', $param);

				if($param == 'system:id') {
					$datasource->_param_pool[$param_key][] = $entry->get('id');
					if($singleParam) $datasource->_param_pool[$key][] = $entry->get('id');
				}
				else if($param == 'system:author') {
					$datasource->_param_pool[$param_key][] = $entry->get('author_id');
					if($singleParam) $datasource->_param_pool[$key][] = $entry->get('author_id');
				}
				else if($param == 'system:date') {
					$datasource->_param_pool[$param_key][] = DateTimeObj::get('c', $entry->creationDate);
					if($singleParam) $datasource->_param_pool[$key][] = DateTimeObj::get('c', $entry->creationDate);
				}
			}
		}

		public function processOutputParameters(Datasource $datasource, Entry $entry, $field_id, array $data) {
			if(!isset($datasource->dsParamPARAMOUTPUT)) return;

			// Support the legacy parameter `ds-datasource-handle`
			$key = 'ds-' . $datasource->dsParamROOTELEMENT;
			$singleParam = count($datasource->dsParamPARAMOUTPUT) == 1;
			if($singleParam && (!isset($datasource->_param_pool[$key]) || !is_array($datasource->_param_pool[$key]))) {
				$datasource->_param_pool[$key] = array();
			}

			foreach($datasource->dsParamPARAMOUTPUT as $param) {
				if(self::$field_pool[$field_id]->get('element_name') !== $param) continue;

				// The new style of paramater is `ds-datasource-handle.field-handle`
				$param_key = $key . '.' . str_replace(':', '-', $param);

				if(!isset($datasource->_param_pool[$param_key]) || !is_array($datasource->_param_pool[$param_key])) {
					$datasource->_param_pool[$param_key] = array();
				}

				$param_pool_values = self::$field_pool[$field_id]->getParameterPoolValue($data, $entry->get('id'));

				if(is_array($param_pool_values)){
					$datasource->_param_pool[$param_key] = array_merge($param_pool_values, $datasource->_param_pool[$param_key]);
					if($singleParam) $datasource->_param_pool[$key] = array_merge($param_pool_values, $datasource->_param_pool[$key]);
				}
				else {
					$datasource->_param_pool[$param_key][] = $param_pool_values;
					if($singleParam) $datasource->_param_pool[$key][] = $param_pool_values;
				}
			}
		}
	}

	return 'UnionDatasource';
