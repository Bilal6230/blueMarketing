/* Shared verified-bank narration for BOR/BOP, BD/BW and original PPR.
 * JQuery-only UI integration; source identity/eligibility is enforced by PHP.
 */
(function ($) {
    'use strict';
    var sourcePattern = /\n?\[BANK SOURCE\][\s\S]*?\[\/BANK SOURCE\]|\n?SOURCE (?:Cheque|Online) \| [^\r\n]*/g;
    function manualNarration(value) {
        return $.trim(String(value || '').replace(sourcePattern, ''));
    }
    function evidence(source) {
        if (!source) return '';
        if (source.method === 'online') {
            return [
                'SOURCE Online',
                'Transaction ID / Reference: ' + String(source.reference || ''),
                'Sending Bank: ' + String(source.sending_bank || ''),
                'Receiving Bank: ' + String(source.receiving_bank || ''),
                'Clearing Date: ' + String(source.passing_date || '')
            ].join(' | ');
        }
        if (source.method === 'cheque') {
            return [
                'SOURCE Cheque',
                'Title: ' + String(source.cheque_title || ''),
                'Cheque Number: ****' + String(source.cheque_last4 || String(source.reference || '').slice(-4)),
                'Bank Name: ' + String(source.bank || ''),
                'Clearing Date: ' + String(source.passing_date || '')
            ].join(' | ');
        }
        return '';
    }
    function set($field, source) {
        if (!$field || !$field.length) return {ok: false, message: 'Narration field is unavailable.'};
        var manual = manualNarration($field.val()), block = evidence(source);
        if (source && !block) return {ok: false, message: 'Select a verified Online or Cheque transaction.'};
        var next = manual && block ? manual + '\n' + block : manual + block;
        var max = Number($field.attr('maxlength')) || 0;
        if (max > 0 && next.length > max) {
            return {ok: false, message: 'Verified bank details require ' + block.length +
                ' characters. Shorten the manual narration to stay within ' + max + ' characters.'};
        }
        $field.val(next).trigger('input');
        return {ok: true, value: next};
    }
    $.financeVerifiedSourceNarration = {
        set: set,
        evidence: evidence,
        manual: manualNarration
    };
})(jQuery);
