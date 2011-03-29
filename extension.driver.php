<?php

	Class Extension_UnionDatasource extends Extension {

		public function about(){
			return array(
				'name' => 'Union Datasource',
				'version' => '0.1',
				'release-date' => '2011-03-29',
				'author' => array(
					'name' => 'Brendan Abbott',
					'website' => 'http://bloodbone.ws',
					'email' => 'brendan@bloodbone.ws'
				),
				'description' => 'Create custom Data Sources that implement output caching'
			);
		}

	}

?>