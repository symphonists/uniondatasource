(function($, Symphony) {

	Symphony.Extensions.UnionDatasource = function() {

		var init = function() {
			var	context = $('#ds-context'),
				settings = $('.settings'),
				conditions = settings.has('[name="fields[required_url_param]"]'),
				pagination = settings.has('[name="fields[page_number]"]'),
				datasources = $('.union-datasources');

			// Relocate Union Data Source settings
			datasources.parents('.settings').insertBefore(pagination);

			// Create Data Source selector
			datasources.symphonyDuplicator({
				orderable: true
			});

			// Set context
			conditions.attr('data-context', conditions.attr('data-context') + ' union-datasource');
			pagination.attr('data-context', pagination.attr('data-context') + ' union-datasource');

			// Update context
			context.trigger('change.admin');
		}

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
