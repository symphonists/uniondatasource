jQuery(document).ready(function() {
	(function($) {
		var $union_container = $('.UnionDatasource'),
			$duplicator = $union_container.find('ol.union-duplicator'),
			$sort = $union_container.find('select.sort-by'),
			$order = $union_container.find('select.sort-order');

		// Create our duplicator with ordering
		$duplicator.symphonyDuplicator({
			orderable: true,
		});

		// Update the Sort By/Order
		var update = function() {
			$duplicator.find('li:first').each(function() {
				var $self = $(this),
					sort = $self.find('input[name*=union-sort]').val(),
					order = $self.find('input[name*=union-order]').val(),
					$existing_sort = $sort.find('option').filter(function() {
						return $(this).text() === sort;
					}),
					$existing_order = $order.find('option').filter(function() {
						return $(this).text() === order;
					});

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

		$duplicator.closest('.frame')
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