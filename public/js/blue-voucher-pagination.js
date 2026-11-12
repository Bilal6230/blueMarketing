/* Shared voucher pagination renderer; does not fetch or mutate financial data. */
(function ($) {
    'use strict';
    if (!$ || $.blueVoucherPagination) return;
    $.blueVoucherPagination = {
        render: function (options) {
            var $nav = $(options.container).empty();
            var total = Math.max(0, Number(options.total) || 0);
            var length = Math.max(1, Number(options.length) || 25);
            var start = Math.max(0, Number(options.start) || 0);
            var pages = Math.ceil(total / length);
            var current = pages ? Math.min(pages, Math.floor(start / length) + 1) : 1;
            var $summary = $(options.summary);
            var css = options.buttonClass || 'dv-page-btn';
            var add = function (label, page, disabled, active) {
                var $button = $('<button>', {
                    type: 'button', 'class': css + (active ? ' active' : ''),
                    text: label, disabled: !!disabled,
                    'aria-label': label === 'Previous' || label === 'Next' ? label + ' page' : 'Page ' + label
                });
                if (active) $button.attr('aria-current', 'page');
                if (!disabled) $button.on('click', function () {
                    options.onPage((page - 1) * length, page);
                });
                $nav.append($button);
            };
            add('Previous', current - 1, current <= 1, false);
            var shown = {};
            [1, 2, 3, 4, 5, 6, pages - 1, pages].forEach(function (n) { shown[n] = true; });
            for (var offset = -2; offset <= 2; offset++) shown[current + offset] = true;
            var previous = 0;
            Object.keys(shown).map(Number).filter(function (n) { return n >= 1 && n <= pages; })
                .sort(function (a, b) { return a - b; }).forEach(function (page) {
                    if (previous && page > previous + 1) {
                        $nav.append($('<span>', { 'class': 'dv-page-ellipsis', text: '…' }));
                    }
                    add(String(page), page, false, page === current);
                    previous = page;
                });
            add('Next', current + 1, pages === 0 || current >= pages, false);
            var first = total ? start + 1 : 0;
            var last = Math.min(start + length, total);
            var text = 'Showing ' + first + '–' + last + ' of ' + total.toLocaleString() + ' records';
            if (options.allTotal != null && Number(options.allTotal) !== total) {
                text += ' (from ' + Number(options.allTotal).toLocaleString() + ' total)';
            }
            $summary.text(text);
        }
    };
})(window.jQuery);
