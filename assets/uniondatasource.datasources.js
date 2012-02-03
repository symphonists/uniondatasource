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
					$existing_sort = $sort.find('option[value=' + sort + ']'),
					order = $self.find('input[name*=union-order]').val()
					$existing_order = $order.find('option[value=' + order + ']');

				// If this sort field already exists, select it
				if($existing_sort.length) {
					$existing_sort.attr('selected', 'selected');
				}
				// Otherwise add a new option and select it
				else {
					$sort.append(
						$('<option />').attr('selected', 'selected').val(sort).text(sort)
					);
				}

				// If this order already exists, select it
				if($existing_order.length) {
					$existing_order.attr('selected', 'selected');
				}
				// Otherwise add a new option and select it
				else {
					$order.append(
						$('<option />').attr('selected', 'selected').val(order).text(order)
					);
				}
			});
		}

		// Trigger inital Sort By/Order population
		update();

		$duplicator
			// Listen for when the duplicator changes
			// Update the sort options
			.bind('orderstop.duplicator click.duplicator', update)

			// When removing a duplicator item
			.bind('destruct.duplicator', function() {
				if($duplicator.find('li').length) {
					update();
				}
				else {
					$sort.add($order).find('option').remove();
				}
			});

	})(jQuery.noConflict());
});