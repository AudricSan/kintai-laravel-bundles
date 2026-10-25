/**
 * Rapport de démission : popup de suppression proposant soit de réactiver
 * l'employé (et supprimer uniquement le rapport), soit de supprimer
 * définitivement l'employé et le rapport associé.
 */
(function () {
    'use strict';

    var modal            = document.getElementById('rr-delete-modal');
    var reactivateForm   = document.getElementById('rr-reactivate-form');
    var deletePermForm   = document.getElementById('rr-delete-permanently-form');
    var employeeNameEl   = document.getElementById('rr-delete-employee-name');

    if (!modal || !reactivateForm || !deletePermForm) return;

    document.querySelectorAll('.js-rr-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            reactivateForm.action = this.dataset.reactivateUrl || '';
            deletePermForm.action = this.dataset.deleteUrl || '';
            if (employeeNameEl) {
                employeeNameEl.textContent = this.dataset.employeeName || '';
            }
            modal.classList.add('open');
        });
    });

    document.querySelectorAll('.js-rr-delete-close').forEach(function (el) {
        el.addEventListener('click', function () { modal.classList.remove('open'); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') modal.classList.remove('open');
    });
})();
