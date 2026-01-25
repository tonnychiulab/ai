jQuery(document).ready(function ($) {

    // Pagination Logic for Constants Tab
    $('.mts-list').each(function () {
        var $list = $(this);
        var $items = $list.find('li');
        var totalItems = $items.length;
        var itemsPerPage = 10;
        var totalPages = Math.ceil(totalItems / itemsPerPage);

        // Only paginate if we have more items than the limit
        if (totalItems <= itemsPerPage) {
            return;
        }

        var currentPage = 1;

        // Create Controls
        var $controls = $('<div class="mts-pagination"></div>');
        var $prevBtn = $('<button type="button" class="button button-small disabled">&laquo; Prev</button>');
        var $nextBtn = $('<button type="button" class="button button-small">Next &raquo;</button>');
        var $pageInfo = $('<span class="mts-page-info">Page 1 of ' + totalPages + '</span>');

        $controls.append($prevBtn).append($pageInfo).append($nextBtn);
        $list.after($controls);

        function showPage(page) {
            var start = (page - 1) * itemsPerPage;
            var end = start + itemsPerPage;

            $items.hide().slice(start, end).show();

            // Update UI
            $pageInfo.text('Page ' + page + ' of ' + totalPages);

            if (page === 1) $prevBtn.addClass('disabled').prop('disabled', true);
            else $prevBtn.removeClass('disabled').prop('disabled', false);

            if (page === totalPages) $nextBtn.addClass('disabled').prop('disabled', true);
            else $nextBtn.removeClass('disabled').prop('disabled', false);

            currentPage = page;
        }

        // Init
        showPage(1);

        // Events
        $prevBtn.click(function () {
            if (currentPage > 1) showPage(currentPage - 1);
        });

        $nextBtn.click(function () {
            if (currentPage < totalPages) showPage(currentPage + 1);
        });
    });

});
