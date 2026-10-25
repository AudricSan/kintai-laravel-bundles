'use strict';
/**
 * Carousel photo : ouvre en grand une photo d'un envoi et permet de naviguer
 * entre toutes les photos du même envoi (bash). Expose photoCarouselOpen/Close/Prev/Next.
 */
(function () {
    var _dataEl = document.getElementById('photo-carousel-data');
    var images  = _dataEl ? JSON.parse(_dataEl.textContent) : [];
    var index   = 0;

    function render() {
        if (!images.length) return;
        var img = images[index];
        document.getElementById('photo-carousel-img').src = img.src;
        document.getElementById('photo-carousel-img').alt = img.filename || '';
        document.getElementById('photo-carousel-filename').textContent = img.filename || '';
        document.getElementById('photo-carousel-counter').textContent  = (index + 1) + ' / ' + images.length;
    }

    window.photoCarouselOpen = function (i) {
        if (!images.length) return;
        index = i;
        render();
        document.getElementById('photo-carousel-overlay').classList.add('open');
    };

    window.photoCarouselClose = function (e) {
        if (e) e.stopPropagation();
        document.getElementById('photo-carousel-overlay').classList.remove('open');
    };

    window.photoCarouselPrev = function (e) {
        if (e) e.stopPropagation();
        if (!images.length) return;
        index = (index - 1 + images.length) % images.length;
        render();
    };

    window.photoCarouselNext = function (e) {
        if (e) e.stopPropagation();
        if (!images.length) return;
        index = (index + 1) % images.length;
        render();
    };

    document.addEventListener('keydown', function (e) {
        var overlay = document.getElementById('photo-carousel-overlay');
        if (!overlay || !overlay.classList.contains('open')) return;
        if (e.key === 'Escape')      window.photoCarouselClose();
        else if (e.key === 'ArrowLeft')  window.photoCarouselPrev();
        else if (e.key === 'ArrowRight') window.photoCarouselNext();
    });
}());
