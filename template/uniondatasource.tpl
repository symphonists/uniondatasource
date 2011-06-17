<?php

	require_once(EXTENSIONS . '/uniondatasource/lib/class.uniondatasource.php');

	Class datasource<!-- CLASS NAME --> extends UnionDatasource{

		<!-- VAR LIST -->

		<!-- UNION -->

		public $dsParamINCLUDEDELEMENTS = array(
			'system:pagination'
		);

		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
			$this->_dependencies = array();
		}

		public function about(){
			return array(
				'name' => '<!-- NAME -->',
				'author' => array(
					'name' => '<!-- AUTHOR NAME -->',
					'website' => '<!-- AUTHOR WEBSITE -->',
					'email' => '<!-- AUTHOR EMAIL -->'),
				'version' => 'Union Datasource <!-- VERSION -->',
				'release-date' => '<!-- RELEASE DATE -->'
			);
		}

	}
