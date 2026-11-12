/* Globally shared adapter for original voucher registers with nested action groups.
 * Existing authorization, event delegation and financial mutations remain intact. */
(function ($) {
  'use strict';
  if (!$ || $.blueVoucherRegisterActions) return;
  $.blueVoucherRegisterActions = {
    enhance: function ($table) {
      if (!$.blueActionMenu) return;
      $table = $($table);
      if (!$table.length || !/actions?/i.test($.trim($table.find('thead tr:first th:last').text()))) return;
      $.blueActionMenu.close();
      $table.find('tbody tr').each(function () {
        var $cell = $(this).children('td').last();
        if (!$cell.length || $cell.children('.blue-actions-toggle').length) return;
        var $groups = $cell.children('.jv-actions,.btn-group');
        if (!$groups.length) $groups = $cell.find('.btn-group').first();
        var $items = $groups.length ? $groups.find('a,button') : $cell.children('a,button');
        $items = $items.filter(function () {
          return !$(this).closest('.blue-actions-source').length;
        });
        if (!$items.length) return;
        var $originals = $items.detach();
        $groups.each(function () { if (!$.trim($(this).text()) && !$(this).children().length) $(this).remove(); });
        $cell.addClass('blue-actions-cell').append($originals);
      });
      $.blueActionMenu.enhance($table.find('tbody td.blue-actions-cell'));
    }
  };
  // Booking receipt / extra charge original tables are server-rendered and
  // may also be re-rendered by the existing DataTable. Re-enhance on draw.
  $(document).on('draw.dt', '.bm-booking-vouchers #example1', function () {
    $.blueVoucherRegisterActions.enhance($(this));
  });
  $(function () {
    $('.bm-booking-vouchers #example1').each(function () {
      $.blueVoucherRegisterActions.enhance($(this));
    });
  });
})(window.jQuery);
