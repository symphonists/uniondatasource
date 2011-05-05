jQuery(document).ready(function() {
	(function($) {
		var $duplicator = $('ol.union-duplicator'),
			$sort = $('select.sort-by'),
			$order = $('select.sort-order');

		// Create our duplicator with ordering
		$duplicator.symphonyDuplicator({
			orderable: true,
		});

		// Update the Sort By/Order
		var update = function() {
			$duplicator.find('li:first').each(function() {
				var $self = $(this),
					sort = $self.find('input[name*=union-sort]').val(),
					order = $self.find('input[name*=union-order]').val();

				// Sort
				var $option = $('<option />')
								.attr('selected', 'selected')
								.val(sort)
								.text(sort);

				$sort.append($option);

				// Order
				$order.append(
					$option.clone().val(order).text(order)
				);

			});
		}

		// Trigger inital Sort By/Order population
		update();

		// Listen for when the duplicator changes
		$duplicator.bind('destruct.duplicator orderstop.duplicator', function() {
			update();
		});

	})(jQuery.noConflict());
});