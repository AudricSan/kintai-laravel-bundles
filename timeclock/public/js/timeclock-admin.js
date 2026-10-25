/**
 * Timeclock admin : modal d'édition d'une entrée de pointage.
 */
(function () {
    'use strict';

    var modal    = document.getElementById('tc-edit-modal');
    var form     = document.getElementById('tc-edit-form');
    var inInput  = document.getElementById('tc-in');
    var outInput = document.getElementById('tc-out');
    var meta     = document.getElementById('tc-admin-meta');

    if (!modal || !form || !meta) return;

    var BASE = meta.dataset.baseUrl || '';

    document.querySelectorAll('.js-tc-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id       = this.dataset.id;
            var clockIn  = this.dataset.clockIn;
            var clockOut = this.dataset.clockOut;

            form.action    = BASE + '/admin/timeclocks/' + id + '/edit';
            inInput.value  = clockIn  ? clockIn.replace(' ', 'T')  : '';
            outInput.value = clockOut ? clockOut.replace(' ', 'T') : '';

            modal.classList.add('open');
        });
    });

    document.querySelectorAll('.js-tc-close').forEach(function (el) {
        el.addEventListener('click', function () { modal.classList.remove('open'); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') modal.classList.remove('open');
    });
})();
