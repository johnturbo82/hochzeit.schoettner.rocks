(function () {
    var grid = document.getElementById('galerie-grid');
    var lightbox = document.getElementById('galerie-lightbox');
    var full = document.getElementById('galerie-full');
    var caption = document.getElementById('galerie-caption');
    var closeButton = document.getElementById('galerie-close');
    var downloadLink = document.getElementById('galerie-download');

    if (!grid || !lightbox || !full || !caption || !closeButton || !downloadLink) {
        return;
    }

    // Defensive Initialisierung: Overlay beim ersten Laden sicher verborgen halten.
    lightbox.hidden = true;

    var previousOverflow = '';

    function getTriggerFromTarget(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        return target.closest('.galerie-item');
    }

    function closeLightbox() {
        lightbox.hidden = true;
        full.removeAttribute('src');
        caption.textContent = '';
        downloadLink.setAttribute('href', '#');
        downloadLink.removeAttribute('download');
        document.body.style.overflow = previousOverflow;
    }

    function openLightbox(src, filename) {
        full.src = src;
        caption.textContent = filename || '';
        downloadLink.href = src;
        downloadLink.setAttribute('download', filename || 'foto');
        lightbox.hidden = false;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    }

    grid.addEventListener('click', function (event) {
        var trigger = getTriggerFromTarget(event.target);
        if (!trigger) {
            return;
        }

        var src = trigger.getAttribute('data-fullsrc');
        var filename = trigger.getAttribute('data-filename');
        if (!src) {
            return;
        }

        openLightbox(src, filename);
    });

    closeButton.addEventListener('click', closeLightbox);

    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !lightbox.hidden) {
            closeLightbox();
        }
    });
})();
