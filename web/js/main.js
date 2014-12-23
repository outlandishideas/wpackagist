$(document).ready(function() {
	$('.js-version').on('click', function(event) {
		event.preventDefault();
		var $element = $(this),
			$parentRow = $element.closest('tr'),
			name = $parentRow.find('[data-name]').data('name'),
			type = $parentRow.find('[data-type]').data('type'),
			version = $element.data('version'),
			copyString = '"wpackagist-' + type + '/' + name + '": "' + version + '"';

		if (!$parentRow.next('tr').hasClass('js-composer-info')) {
			$('.js-composer-info').remove();
			$parentRow.after("<tr class='js-composer-info'> \
								<td colspan='6'><div class='row'> \
									<div class='small-5 columns'> \
										<label for='copy-field' class='right inline'>Press Control-C or Command-C to copy to your clipboard:</label> \
									</div> \
									<div class='small-7 columns'> \
										<input id='copy-field' class='js-copy' type='text' value=''> \
									</div> \
						      	</div></td> \
					      	</tr>");
			$('.js-composer-info .js-copy').val(copyString).select();
		} else {
			$('.js-composer-info .js-copy').val(copyString).select();
		}
	});
	
	$('.js-toggle-more').on('click', function(event) {
		event.preventDefault();
		var $element = $(this),
			$siblingsToToggle = $element.siblings('[data-hide]');
		$siblingsToToggle.toggleClass('hide');
	});
});	