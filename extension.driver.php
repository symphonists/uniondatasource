<?php

	Class Extension_UnionDatasource extends Extension {

		public function about(){
			return array(
				'name' => 'Union Datasource',
				'version' => '0.2',
				'release-date' => '2011-04-04',
				'author' => array(
					'name' => 'Brendan Abbott',
					'website' => 'http://bloodbone.ws',
					'email' => 'brendan@bloodbone.ws'
				),
				'description' => 'A union datasources allows you to combine multiple datasources to output a single datasource for the
				primary purpose of a unified pagination.'
			);
		}

	}

?>