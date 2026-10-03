/*
 * Optional UI helper for the existing Reception registration form.
 * It only marks the form as an NHIF visit when a payment-mode select contains NHIF.
 * The server-side reception_after_save.php remains the authoritative trigger.
 */
(function () {
    function locatePaymentField() {
        var selectors = [
            'select[name="payment_mode"]',
            'select[name="mode_of_payment"]',
            'select[name="paymentmode"]',
            'select[name="payment_type"]',
            'select[name="payment"]'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var field = document.querySelector(selectors[i]);
            if (field) return field;
        }
        return null;
    }

    function updateNhifState(field) {
        var value = String(field.value || '').trim().toUpperCase();
        var active = value === 'NHIF' || value === 'N.H.I.F' ||
            value === 'NHIF INSURANCE' || value === 'INSURANCE-NHIF';

        var form = field.form;
        if (!form) return;

        var marker = form.querySelector('input[name="nhif_selected"]');
        if (!marker) {
            marker = document.createElement('input');
            marker.type = 'hidden';
            marker.name = 'nhif_selected';
            form.appendChild(marker);
        }
        marker.value = active ? '1' : '0';

        var note = document.getElementById('nhif-payment-note');
        if (!note) {
            note = document.createElement('div');
            note.id = 'nhif-payment-note';
            note.style.marginTop = '6px';
            note.style.fontWeight = 'bold';
            field.parentNode.appendChild(note);
        }

        note.textContent = active
            ? 'NHIF selected - beneficiary verification will open after registration is saved.'
            : '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var field = locatePaymentField();
        if (!field) return;
        field.addEventListener('change', function () { updateNhifState(field); });
        updateNhifState(field);
    });
})();
