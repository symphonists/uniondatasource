<?php

	Class Extension_UnionDatasource extends Extension {

		public function about(){
			return array(
				'name' => 'Union Datasource',
				'version' => '0.3',
				'release-date' => '2011-04-12',
				'author' => array(
					'name' => 'Brendan Abbott',
					'website' => 'http://bloodbone.ws',
					'email' => 'brendan@bloodbone.ws'
				),
				'description' => 'A union datasource allows you to combine multiple datasources to output as a single datasource for the
				primary purpose of a unified pagination.'
			);
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' 	=> __('Blueprints'),
					'name' 		=> __('Union Datasources'),
					'link' 		=> '/datasources/'
				)
			);
		}

	}

?>