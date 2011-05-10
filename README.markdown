# Union Datasource

* Version: 0.4.1
* Author: Brendan Abbott
* Build Date: 2011-05-10
* Requirements: Symphony 2.2

A union datasource allows you to combine multiple datasources to output as a single datasource for the
primary purpose of a unified pagination.

## How do I use it?

1. Install this extension.

2. Create your datasources as usual

3. Navigate to Blueprints -> Union Datasources to create your Union Datasource

4. Add Union Datasource to pages as you normally would

## Notes

* Grouping doesn't work, and it's probably a long way off too
* Output parameters work, but are named according to the original datasource,
not the Union Datasource. For example, a Union DS called `read-all-news` that
uses `read-news` and `read-twitter` would have output params of `read-news-*`
and `read-twitter-*`
* Datasource dependencies are not taken care of, this may cause unpredictable
results if you are trying to union chained datasources

## Changelog

* 0.4.1, 10 May 2011
	* Don't `$process_param` in the backend context, fixes issue with required parameters
	* Ensure SectionManager and FieldManager classes are included

* 0.4, 09 May 2011
	* Implemented an interface to build Union Datasources without having to hack modify any PHP
	* Implement `DatasourceEntriesBuilt` delegate
	* Support RAND() ordering
	* Add support for Associated Entry Counts
	* Display correct XML result when there are no entries found
	* Sorting by `system:id` and `system:date` should work correctly

* 0.3, 12 April 2011
	* Fixed a critical bug that rendered the extension near useless. Thanks tonyarnold for discovering and help debug.

* 0.2, 04 April 2011
	* Near complete rewrite to move all pagination and sorting into the SQL instead of using PHP.

* 0.1, 23 March 2011
	* Initial release

## Credits

Credit to Nick Dunn as I pretty much extended his README.
