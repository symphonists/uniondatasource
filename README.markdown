# Union Datasource

The Union Datasource extension allows you to combine multiple Data Sources to output as a single Data Source for the primary purpose of a unified pagination.

## How do I use it?

1. Install this extension by copying `/uniondatasource` folder to your `/extensions` folder. Then navigation to the System > Extensions page in the Symphony backend page, select the Union Datasource extension and then apply the "Enable/Install".

2. Create your Data Sources as usual through the Datasource Editor

3. To Create your Union Datasource, create a new Datasource via the Datasource Editor, but choose Union Datasource as the Source. You can now add the datasources you created in Step 2.

4. Add the resulting Union Datasource to your frontend pages as you normally would

5. Dance!

## Use case

You may have two sections, News and Tweets that you'd like to display as a single stream on the frontend. At the moment, this is difficult as the pagination for both these datasources is different and will start to lead to unpredictable results as you paginate through. Not to mention the complexity in XSLT to merge the two datasources together and sort the result.

This extension allows you to create your two datasources as you normally would, say 'Read News by Date' and 'Read Tweets by Date' complete with their own Filtering and Included Elements and then combine the two datasources together. A third datasource, which is the Union Datasource, is created which will control your Pagination and use it's name as the datasource root element in your XML.

## Caveats

* Grouping doesn't work, and it's probably a long way off too
* Output parameters work, but are named according to the original datasource,
not the Union Datasource. For example, a Union DS called `read-all-news` that
uses `read-news` and `read-twitter` would have output params of `read-news-*`
and `read-twitter-*`

## Credits

Credit to Nick Dunn as I pretty much extended his README.