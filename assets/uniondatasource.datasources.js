(function($, Symphony) {

	Symphony.Language.add({
		'The first source determines sort order and direction': false,
		'Sorted by {$field}, {$direction}': false,
		'descending': false,
		'ascending': false
	});

	Symphony.Extensions.UnionDatasource = function() {
		var context, settings, conditions, pagination, datasources, help;

		var init = function() {
			context = $('#ds-context');
			settings = $('.settings');
			conditions = settings.has('[name="fields[required_url_param]"]');
			pagination = settings.has('[name="fields[page_number]"]');
			datasources = $('.union-datasources'),
			fieldset = datasources.parents('.settings');
			help = fieldset.find('i');

			// Relocate Union Data Source settings
			fieldset.insertBefore(pagination);

			// Create Data Source selector
			datasources.symphonyDuplicator({
				orderable: true
			});

			// Set context
			conditions.attr('data-context', conditions.attr('data-context') + ' union-datasource');
			pagination.attr('data-context', pagination.attr('data-context') + ' union-datasource');

			// Update context
			context.trigger('change.admin');

			// Display sort order and and direction
			datasources.on('destructstop.duplicator', '.instance', displaySorting);
			datasources.on('constructstop.duplicator', '.instance', displaySorting);
			datasources.on('orderstop.orderable', displaySorting);
			datasources.on('stats.uniondatasource', displaySorting).trigger('stats.uniondatasource');
		};

		var displaySorting = function() {
			var first = datasources.find('.instance:visible');

			if(!datasources.is('.empty')) {
				var field = first.find('[name="fields[UnionDatasource][union-sort][0]"]'),
					direction = first.find('[name="fields[UnionDatasource][union-order][0]"]');

				help.html(Symphony.Language.get('Sorted by {$field}, {$direction}', {
					field: '<code>' + field.val() + '</code>',
					direction: (direction.val() == 'asc' ? Symphony.Language.get('ascending') : Symphony.Language.get('descending'))
				}))
			}
			else {
				help.text(Symphony.Language.get('The first source determines sort order and direction'));
			}
		};

		// API
		return {
			init: init
		};
	}();

	// Initialise backend
	$(document).ready(function() {
		Symphony.Extensions.UnionDatasource.init();
	});

})(window.jQuery, window.Symphony);
