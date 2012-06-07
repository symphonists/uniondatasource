<?php

	require_once(EXTENSIONS . '/uniondatasource/data-sources/datasource.union.php');

	Class datasource<!-- CLASS NAME --> extends UnionDatasource{

		public $dsParamROOTELEMENT = '%s';
		public $dsParamPAGINATERESULTS = '%s';
		public $dsParamSTARTPAGE = '%s';
		public $dsParamLIMIT = '%s';
		public $dsParamREDIRECTONEMPTY = '%s';
		public $dsParamREQUIREDPARAM = '%s';

		<!-- UNION -->

		public $dsParamINCLUDEDELEMENTS = array(
			'system:pagination'
		);

		public function __construct($env=NULL, $process_params=true){
			parent::__construct($env, $process_params);
			$this->_dependencies = array(<!-- DS DEPENDENCY LIST -->);
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

		public function allowEditorToParse(){
			return true;
		}

	}
