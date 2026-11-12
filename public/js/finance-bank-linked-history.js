/* Shared read-only rendering for Cheque / Online verified source history.
 * First-party jQuery only. All external text is rendered with .text().
 */
(function ($) {
    'use strict';

    function formatAmount(amount) {
        var value = Number(amount);
        return isFinite(value) ? value.toLocaleString('en-PK', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) : '—';
    }

    function label(value) {
        var text = String(value == null ? '' : value).trim();
        return text || '—';
    }

    function render($target, linked) {
        if (!$target || !$target.length) return;
        $target.empty();
        if (!$.isArray(linked) || !linked.length) {
            $('<tr>').append($('<td>', {
                colspan: 5, 'class': 'text-center text-muted'
            }).text('No voucher allocations are linked to this source.')).appendTo($target);
            return;
        }

        $.each(linked, function (_, item) {
            var $row = $('<tr>');
            var voucher = label(item.voucher_number);
            if (voucher === '—') {
                voucher = label(item.voucher_type) + ' / #' + label(item.voucher_id);
            }
            $('<td>').append($('<strong>').text(voucher)).appendTo($row);
            $('<td>').addClass('text-right').text(formatAmount(item.amount)).appendTo($row);
            $('<td>').text(label(item.approval_status) === '—'
                ? 'Not tracked' : label(item.approval_status)).appendTo($row);
            $('<td>').text(label(item.allocated_at)).appendTo($row);
            var $release = $('<td>');
            if (item.released) {
                $('<span>').addClass('badge badge-warning').text('Released').appendTo($release);
                $('<div>').addClass('small mt-1').text(
                    [item.release_action, item.released_at, item.release_reason]
                        .filter(Boolean).join(' | ')
                ).appendTo($release);
            } else {
                $('<span>').addClass('badge badge-success').text('Not released').appendTo($release);
            }
            $release.appendTo($row);
            $target.append($row);
        });
    }

    $.bankLinkedHistory = {
        render: render,
        loading: function ($target) {
            $target.empty().append($('<tr>').append(
                $('<td>', {colspan: 5, 'class': 'text-center text-muted'})
                    .text('Loading linked vouchers...')
            ));
        },
        failed: function ($target) {
            $target.empty().append($('<tr>').append(
                $('<td>', {colspan: 5, 'class': 'text-center text-danger'})
                    .text('Could not load linked vouchers.')
            ));
        }
    };
})(jQuery);
