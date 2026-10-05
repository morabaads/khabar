/* global jQuery */
jQuery(function ($) {
	$(document).on('click', '.khabar-reset', function () {
		$('#' + $(this).data('target')).val($(this).data('default'));
	});
	if ($.fn.wpColorPicker) { $('.khabar-color').wpColorPicker(); }
});
