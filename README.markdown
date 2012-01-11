# Union Datasource

* Version: 0.6
* Author: Brendan Abbott
* Build Date: 2012-01-11
* Requirements: Symphony 2.2

A union datasource allows you to combine multiple datasources to output as a single datasource for the primary purpose of a unified pagination.

## How do I use it?

1. Install this extension.

2. Create your datasources as usual

3. Navigate to Blueprints -> Union Datasources to create your Union Datasource

4. Add Union Datasource to pages as you normally would

## Caveats

* Grouping doesn't work, and it's probably a long way off too
* Output parameters work, but are named according to the original datasource,
not the Union Datasource. For example, a Union DS called `read-all-news` that
uses `read-news` and `read-twitter` would have output params of `read-news-*`
and `read-twitter-*`

## Credits

Credit to Nick Dunn as I pretty much extended his README.