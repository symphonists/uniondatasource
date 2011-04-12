# Union Datasource

* Version: 0.3
* Author: Brendan Abbott
* Build Date: 2011-04-12
* Requirements: Symphony 2.2

A union datasource allows you to combine multiple datasources to output as a single datasource for the
primary purpose of a unified pagination. It requires a little bit of PHP knowledge as you'll have to
replace some lines in the generated datasource file to make it work as you'd like.

## How do I use it?

Install this extension. Actual installation/enabling isn't strictly required, just having it in your /extensions/ folder is sufficient.

You now need to customise each datasource you want to cache. This will render the DS un-editable through the Data Source Editor (Blueprints > Components) but it's a small price to pay. If you're technically advanced to be using this extension in the first place, I'm assuming you're comfortable editing data sources by hand anyway.

1. Create your normal datasources as usual, you can apply any filtering and sorting here as you please. Don't forget to select your included elements. Any pagination on these datasources will be ignored.

2. Create another datasource which will become the 'union' of the other datasources you previously created. On the Datasource Editor the only values that a Union Datasource will use is the Name and the Pagination (including the `system:pagination` element). Once you create this datasource, open it up in your editor and include the `UnionDatasource` class at the top of your data source:

		require_once(EXTENSIONS . '/uniondatasource/lib/class.uniondatasource.php');

2. Change your data source class to extend `UnionDatasource` instead of `Datasource`

		Class datasourceread_stream extends UnionDatasource {

3. Set the datasources you will to join. Note this extension doesn't take into account dependencies between this datasources. To get the handle, take the part after datasource in your class name, and substitute _ for -, ie. `datasourceread_twitter` becomes `read-twitter`

		public $dsParamUNION = array(
			'datasource-handle',
			'datasource-handle2'
		);

4. Remove the grab() function from your Union datasource.

5. Make the `allowEditorToParse()` function `return false` (or remove it altogether)

6. Add this datasource to the page that you want to output it on as normal. You do not have to add the other datasources to this page.

## Changelog

* 0.3, 12 April 2011
	* Fixed a critical bug that rendered the extension near useless. Thanks tonyarnold for discovering and help debug.

* 0.2, 04 April 2011
	* Near complete rewrite to move all pagination and sorting into the SQL instead of using PHP.

* 0.1, 23 March 2011
	* Initial release

## Credits

Credit to Nick Dunn as I pretty much extended his README.