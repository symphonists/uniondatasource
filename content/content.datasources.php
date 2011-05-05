<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');

	Class contentExtensionUnionDatasourceDatasources extends AdministrationPage {

		protected static $dsm = null;

		public function __construct(Administration &$parent){
			parent::__construct($parent);

			self::$dsm = new DatasourceManager(Administration::instance());
		}

		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Union Datasources'))));

			$this->appendSubheading(__('Union Datasources'), Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL().'new/', __('Create a Union Datasource'), 'create button', NULL, array('accesskey' => 'c')
			));

			$aTableHead = array(
				array(__('Name'), 'col'),
				array(__('Datasources'), 'col'),
				array(__('Order By'), 'col')
			);

			$aTableBody = array();

			$datasources = self::$dsm->listAll();
			if(empty($datasources)){
				$this->pageAlert(
					__('Symphony has no data-sources to union. <a href="%s">Create Datasource?</a>',
					array(
						SYMPHONY_URL . '/blueprints/datasources/new/'
					)),
					Alert::ERROR
				);
			}

			$union_datasources = array();
			foreach($datasources as $key => $datasource) {
				$ds = self::$dsm->create($datasource['handle'], array());

				if($ds instanceof UnionDatasource) {
					$union_datasources[] = $ds;
				}

				unset($datasources[$key]);
				$datasources[$datasource['handle']] = array(
					'about' => $ds->about(),
					'ds' => $ds
				);
			}

			if(empty($union_datasources)) {
				$aTableBody = array(Widget::TableRow(
					array(Widget::TableData(__('None found.'), 'inactive', NULL, count($aTableHead)))
				));
			}

			else{
				foreach($union_datasources as $datasource) {
					$about = $datasource->about();

					// Setup each cell
					$td1 = Widget::TableData(Widget::Anchor(
						$about['name'], Administration::instance()->getCurrentPageURL().'edit/' . str_replace('-','_',$datasource->dsParamROOTELEMENT) . '/', null, 'content'
					));

					$td1->appendChild(Widget::Input("items[{$about['handle']}]", null, 'checkbox'));

					// Show Datasources
					$union = array();
					foreach($datasource->dsParamUNION as $handle) {
						$handle = str_replace('-','_',$handle);
						if(array_key_exists($handle, $datasources)) {
							$union[] = Widget::Anchor(
								$datasources[$handle]['about']['name'], SYMPHONY_URL . '/blueprints/datasources/edit/' . $handle . '/'
							)->generate();
						}
					}

					$td2 = Widget::TableData(
						implode(', ', $union)
					);

					// Show UNION
					$sort = $datasources[str_replace('-','_',$datasource->dsParamUNION[0])]['ds'];
					$td3 = Widget::TableData(
						ucwords($sort->dsParamSORT) . " (" . $sort->dsParamORDER . ")"
					);

					// Add cells to a row
					$aTableBody[] = Widget::TableRow(array(
						$td1, $td2, $td3
					));
				}
			}

			$table = Widget::Table(
				Widget::TableHead($aTableHead),
				NULL,
				Widget::TableBody($aTableBody),
				'selectable'
			);

			$this->Form->appendChild($table);
		}

		public function __viewNew() {
			$this->__viewEdit();
		}

		public function __viewEdit() {
			$isNew = true;
			// Verify role exists
			if($this->_context[0] == 'edit') {
				$isNew = false;

				if(!$datasource = $this->_context[1]) redirect(SYMPHONY_URL . 'extensions/uniondatasource/datasources/');

				if(!$existing = self::$dsm->create($datasource, array(), false)) {
					throw new SymphonyErrorPage(__('The datasource you requested to edit does not exist.'), __('Datasource not found'), 'error');
				}

				$sort_ds = self::$dsm->create(str_replace('-','_',$existing->dsParamUNION[0]), array(), false);
				$sort_about = $sort_ds->about();
			}

			// Add in custom assets
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/uniondatasource/assets/uniondatasource.datasources.css', 'screen', 101);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/uniondatasource/assets/uniondatasource.datasources.js', 104);

			// Append any Page Alerts from the form's
			if(isset($this->_context[2])){
				switch($this->_context[2]){
					case 'saved':
						$this->pageAlert(
							__(
								'Union Datasource updated at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Union Datasources</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									SYMPHONY_URL . 'extensions/uniondatasource/datasources/new/',
									SYMPHONY_URL . 'extensions/uniondatasource/datasources/',
								)
							),
							Alert::SUCCESS);
						break;

					case 'created':
						$this->pageAlert(
							__(
								'Union Datasource created at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Union Datasources</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									SYMPHONY_URL . 'extensions/uniondatasource/datasources/new/',
									SYMPHONY_URL . 'extensions/uniondatasource/datasources/',
								)
							),
							Alert::SUCCESS);
						break;
				}
			}

			// Has the form got any errors?
			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

			if($formHasErrors) $this->pageAlert(
				__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR
			);

			$this->setPageType('form');

			if($isNew) {
				$this->setTitle(__('Symphony &ndash; Union Datasources'));

				$fields['paginate_results'] = 'yes';
				$fields['max_records'] = '20';
				$fields['page_number'] = '1';
			}
			else {
				$about = $existing->about();
				$this->setTitle(__('Symphony &ndash; Union Datasources &ndash; ') . $about['name']);
				$this->appendSubheading($about['name']);

				if(isset($_POST['fields'])){
					$fields = $_POST['fields'];
					$fields['paginate_results'] = ($fields['paginate_results'] == 'on') ? 'yes' : 'no';
				}
				else {
					$fields['name'] = $about['name'];
					$fields['union'] = $existing->dsParamUNION;
					$fields['paginate_results'] = (isset($existing->dsParamPAGINATERESULTS) ? $existing->dsParamPAGINATERESULTS : 'yes');
					$fields['page_number'] = $existing->dsParamSTARTPAGE;
					$fields['max_records'] = $existing->dsParamLIMIT;
				}
			}

			$datasources = self::$dsm->listAll();

			$sm = new SectionManager(Administration::instance());
			$fm = new FieldManager(Administration::instance());

			foreach($datasources as $handle => $ds) {
				$datasource = self::$dsm->create($handle, array(), false);

				unset($datasources[$handle]);

				if(!$datasource instanceof UnionDatasource) {
					$datasources[str_replace('_','-',$handle)] = $datasource;
				}
			}
		// Name
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$div = new XMLElement('div');
			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($fields['name'])));

			if(isset($this->_errors['name'])) $div->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['name']));
			else $div->appendChild($label);
			$group->appendChild($div);

			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);

		// Add Datasources
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Datasources')));

			$p = new XMLElement('p', __('These datasources will have their output combined into a single datasource.'));
			$p->setAttribute('class', 'help contextual');
			$fieldset->appendChild($p);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'contextual');
			$p = new XMLElement('p', __('Add datasources to union'), array('class' => 'label'));
			$div->appendChild($p);

			$ol = new XMLElement('ol');
			$ol->setAttribute('class', 'union-duplicator');

			foreach($datasources as $handle => $datasource) {
				$about = $datasource->about();

				if(isset($existing->dsParamUNION) && in_array($handle, $existing->dsParamUNION)) {
					// Instance
					$wrapper = new XMLElement('li');
					$wrapper->setAttribute('class', 'unique');
					$wrapper->setAttribute('data-type', $handle);
					$wrapper->appendChild(new XMLElement('h4', $about['name']));
					$wrapper->appendChild(
						Widget::Input('fields[union][]', $handle, 'hidden')
					);
					$wrapper->appendChild(
						Widget::Input('fields[union-sort][]', $datasource->dsParamSORT, 'hidden')
					);
					$wrapper->appendChild(
						Widget::Input('fields[union-order][]', $datasource->dsParamORDER, 'hidden')
					);
					$ol->appendChild($wrapper);
				}

				// Template
				$wrapper = new XMLElement('li');
				$wrapper->setAttribute('class', 'unique template');
				$wrapper->setAttribute('data-type', $handle);
				$wrapper->appendChild(new XMLElement('h4', $about['name']));
				$wrapper->appendChild(
					Widget::Input('fields[union][]', $handle, 'hidden')
				);
				$wrapper->appendChild(
					Widget::Input('fields[union-sort][]', $datasource->dsParamSORT, 'hidden')
				);
				$wrapper->appendChild(
					Widget::Input('fields[union-order][]', $datasource->dsParamORDER, 'hidden')
				);
				$ol->appendChild($wrapper);
			}

			$div->appendChild($ol);
			$fieldset->appendChild($div);

			$this->Form->appendChild($fieldset);

		// Add Sorting/Pagination
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual');
			$fieldset->appendChild(new XMLElement('legend', __('Sorting and Limiting')));

			$p = new XMLElement('p', __('Use <code>{$param}</code> syntax to limit by page parameters. All sorting is defined by the first datasource in the union.'));
			$p->setAttribute('class', 'help contextual');
			$fieldset->appendChild($p);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group contextual');

			$label = Widget::Label(__('Sort By'));

			$options = array();
/*
			foreach($datasources as $handle => $datasource) {
				if(isset($existing->dsParamUNION) && !in_array($handle, $existing->dsParamUNION)) continue;

				$about = $datasource->about();

				$optoptions = array(
					array('system:id', 'system:id' == $sort_ds->dsParamSORT , __('System ID')),
					array('system:date', 'system:date' == $sort_ds->dsParamSORT, __('System Date')),
				);

				if(method_exists($datasource, 'getSource')) {
					$section = $sm->fetch($datasource->getSource());

					if(!$section instanceof Section) continue;

					$section_fields = $section->fetchFields();

					foreach($section_fields as $field) {
						$optoptions[] = array($field->handle(), (method_exists($sort_ds, 'getSource') && $field->get('parent_section') == $sort_ds->getSource() && $field->handle() == $sort_ds->dsParamSORT), $field->name() . " (" . $about['name'] . ")");
					}
				}

				$options[] = array(
					'label' => $about['name'],
					'options' => $optoptions
				);
			}
*/
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

			$label->appendChild(Widget::Select('fields[order]', $options, array(
				'class' => 'sort-order',
				'disabled' => 'disabled'
			)));
			$div->appendChild($label);

			$fieldset->appendChild($div);

			$label = Widget::Label();
			$input = array(
				Widget::Input('fields[paginate_results]', NULL, 'checkbox', ($fields['paginate_results'] == 'yes' ? array('checked' => 'checked') : NULL)),
				Widget::Input('fields[max_records]', $fields['max_records'], NULL, array('size' => '6')),
				Widget::Input('fields[page_number]', $fields['page_number'], NULL, array('size' => '6'))
			);
			$label->setValue(__('%s Paginate results, limiting to %s entries per page. Return page %s', array($input[0]->generate(false), $input[1]->generate(false), $input[2]->generate(false))));

			if(isset($this->_errors['max_records'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['max_records']));
			else if(isset($this->_errors['page_number'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['page_number']));
			else $fieldset->appendChild($label);

			$p = new XMLElement('p', __('Failing to paginate may degrade performance if the number of entries returned is very high.'), array('class' => 'help'));
			$fieldset->appendChild($p);

			$this->Form->appendChild($fieldset);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			if(!$isNew) {
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this Role'), 'type' => 'submit', 'accesskey' => 'd'));
				$div->appendChild($button);
			}

			$this->Form->appendChild($div);

		}

		public function __actionNew() {
			return $this->__actionEdit();
		}

		public function __actionEdit(){
			if(array_key_exists('save', $_POST['action'])) return $this->__formAction();
			elseif(array_key_exists('delete', $_POST['action'])){
				if(!General::deleteFile(DATASOURCES . '/data.' . $this->_context[1] . '.php')){
					$this->pageAlert(__('Failed to delete <code>%s</code>. Please check permissions.', array($this->_context[1])), Alert::ERROR);
				}
				else{

					$pages = Symphony::Database()->fetch("SELECT * FROM `tbl_pages` WHERE `data_sources` REGEXP '[[:<:]]".$this->_context[1]."[[:>:]]' ");

					if(is_array($pages) && !empty($pages)){
						foreach($pages as $page){

							$data_sources = preg_split('/\s*,\s*/', $page['data_sources'], -1, PREG_SPLIT_NO_EMPTY);
							$data_sources = array_flip($data_sources);
							unset($data_sources[$this->_context[1]]);

							$page['data_sources'] = implode(',', array_flip($data_sources));

							Symphony::Database()->update($page, 'tbl_pages', "`id` = '".$page['id']."'");
						}
					}
					redirect(SYMPHONY_URL . '/blueprints/components/');
				}
			}
		}


		public function __formAction(){
			$fields = $_POST['fields'];

			$this->_errors = array();

			if(trim($fields['name']) == '') $this->_errors['name'] = __('This is a required field');

			if(strlen(trim($fields['max_records'])) == 0 || (is_numeric($fields['max_records']) && $fields['max_records'] < 1)){
				if (isset($fields['paginate_results'])) $this->_errors['max_records'] = __('A result limit must be set');
			}
			else if(!self::__isValidPageString($fields['max_records'])){
				$this->_errors['max_records'] = __('Must be a valid number or parameter');
			}

			if(strlen(trim($fields['page_number'])) == 0 || (is_numeric($fields['page_number']) && $fields['page_number'] < 1)){
				if (isset($fields['paginate_results'])) $this->_errors['page_number'] = __('A page number must be set');
			}
			else if(!self::__isValidPageString($fields['page_number'])){
				$this->_errors['page_number'] = __('Must be a valid number or parameter');
			}

			$classname = Lang::createHandle($fields['name'], NULL, '_', false, true, array('@^[^a-z]+@i' => '', '/[^\w-\.]/i' => ''));
			$rootelement = str_replace('_', '-', $classname);

			$file = DATASOURCES . '/data.' . $classname . '.php';

			$isDuplicate = false;
			$queueForDeletion = NULL;

			if($this->_context[0] == 'new' && is_file($file)) $isDuplicate = true;
			elseif($this->_context[0] == 'edit'){
				$existing_handle = $this->_context[1];
				if($classname != $existing_handle && is_file($file)) $isDuplicate = true;
				elseif($classname != $existing_handle) $queueForDeletion = DATASOURCES . '/data.' . $existing_handle . '.php';
			}

			##Duplicate
			if($isDuplicate) $this->_errors['name'] = __('A Data source with the name <code>%s</code> name already exists', array($classname));

			if(empty($this->_errors)){
				$dsShell = file_get_contents(EXTENSIONS . '/uniondatasource/template/uniondatasource.tpl');

				$params = array(
					'rootelement' => $rootelement,
				);

				$about = array(
					'name' => $fields['name'],
					'version' => '1.0',
					'release date' => DateTimeObj::getGMT('c'),
					'author name' => Administration::instance()->Author->getFullName(),
					'author website' => URL,
					'author email' => Administration::instance()->Author->get('email')
				);
				$this->__injectAboutInformation($dsShell, $about);

				$union = $fields['union'];
				$this->__injectUnion($dsShell, $union);

				$params['paginateresults'] = (isset($fields['paginate_results']) ? 'yes' : 'no');
				$params['limit'] = $fields['max_records'];
				$params['startpage'] = $fields['page_number'];
				$this->__injectVarList($dsShell, $params);

				$dsShell = str_replace('<!-- CLASS NAME -->', $classname, $dsShell);
				$dsShell = str_replace('<!-- SOURCE -->', $source, $dsShell);

				## Remove left over placeholders
				$dsShell = preg_replace(array('/<!--[\w ]++-->/', '/(\r\n){2,}/', '/(\t+[\r\n]){2,}/'), '', $dsShell);

				// Write the file
				if(!is_writable(dirname($file)) || !$write = General::writeFile($file, $dsShell, Symphony::Configuration()->get('write_mode', 'file')))
					$this->pageAlert(__('Failed to write Data source to <code>%s</code>. Please check permissions.', array(DATASOURCES)), Alert::ERROR);

				// Write Successful, add record to the database
				else{

					if($queueForDeletion){
						General::deleteFile($queueForDeletion);

						## Update pages that use this DS
						$sql = "SELECT * FROM `tbl_pages` WHERE `data_sources` REGEXP '[[:<:]]".$existing_handle."[[:>:]]' ";
						$pages = Symphony::Database()->fetch($sql);

						if(is_array($pages) && !empty($pages)){
							foreach($pages as $page){

								$page['data_sources'] = preg_replace('/\b'.$existing_handle.'\b/i', $classname, $page['data_sources']);

								Symphony::Database()->update($page, 'tbl_pages', "`id` = '".$page['id']."'");
							}
						}
					}

					redirect(SYMPHONY_URL . '/extension/uniondatasource/datasources/edit/'.$classname.'/'.($this->_context[0] == 'new' ? 'created' : 'saved') . '/');

				}
			}
		}

		public function __injectAboutInformation(&$shell, $details){
			if(!is_array($details) || empty($details)) return;

			foreach($details as $key => $val) $shell = str_replace('<!-- ' . strtoupper($key) . ' -->', addslashes($val), $shell);
		}

		public function __injectVarList(&$shell, $vars){
			if(!is_array($vars) || empty($vars)) return;

			$var_list = NULL;
			foreach($vars as $key => $val){
				if(trim($val) == '') continue;
				$var_list .= '		public $dsParam' . strtoupper($key) . " = '" . addslashes($val) . "';" . PHP_EOL;
			}

			$shell = str_replace('<!-- VAR LIST -->', trim($var_list), $shell);
		}

		public function __injectUnion(&$shell, $union){
			if(!is_array($union) || empty($union)) return;

			$string = 'public $dsParamUNION = array(' . self::CRLF;

			foreach($union as $key => $val){
				if(trim($val) == '') continue;
				$string .= "\t\t\t\t'$key' => '" . addslashes($val) . "'," . self::CRLF;
			}

			$string .= '		);' . self::CRLF;

			$shell = str_replace('<!-- UNION -->', trim($string), $shell);
		}

		private static function __isValidPageString($string){
			return (bool)preg_match('/^(?:\{\$[\w-]+(?::\$[\w-]+)*(?::\d+)?}|\d+)$/', $string);
		}
	}
