$(document).ready(function () {
    $('.search-result__refresh-form').on('submit', function (event) {
        // Disable refresh buttons while refreshing to prevent double submit crashes.
        $('.search-result__refresh-button').prop('disabled', true);
    });

    //click on a version sug to display an info box row
    $('.js-version').on('click', function (event) {
        event.preventDefault();

        var $element = $(this),
            $parentRow = $element.closest('tr'),
            name = $parentRow.find('[data-name]').data('name'),
            type = $parentRow.find('[data-type]').data('type'),
            version = $element.data('version'),
            copyString = '"wpackagist-' + type + '/' + name + '":"' + version + '"';

        //remove any existing info boxes
        $('.js-composer-info').remove();

        //add info box in next row
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

        //select text for copying
        $('.js-composer-info .js-copy').val(copyString).select();
    });

    //extra versions toggle
    $('.js-toggle-more').on('click', function (event) {
        event.preventDefault();

        var $element = $(this),
            $siblingsToToggle = $element.siblings('[data-hide]');

        $siblingsToToggle.toggleClass('hide');
    });
});
