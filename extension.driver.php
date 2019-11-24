<?php

	require_once EXTENSIONS . '/uniondatasource/data-sources/datasource.union.php';

	Class Extension_UnionDatasource extends Extension {

		protected static $provides = array();

		public static function registerProviders() {
			self::$provides = array(
				'data-sources' => array(
					'UnionDatasource' => UnionDatasource::getName()
				)
			);

			return true;
		}

		public static function providerOf($type = null) {
			self::registerProviders();

			if(is_null($type)) return self::$provides;

			if(!isset(self::$provides[$type])) return array();

			return self::$provides[$type];
		}

	}
