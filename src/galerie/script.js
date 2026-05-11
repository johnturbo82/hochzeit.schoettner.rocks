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

    lightbox.hidden = true;

    // Stage: clip-Container, der current und neighbor nebeneinander haelt
    var stage = document.createElement('div');
    stage.id = 'galerie-stage';
    full.parentNode.insertBefore(stage, full);
    stage.appendChild(full);

    var neighborImg = document.createElement('img');
    neighborImg.className = 'galerie-neighbor';
    neighborImg.alt = '';
    stage.appendChild(neighborImg);

    var galleryItems = Array.prototype.slice.call(grid.querySelectorAll('.galerie-item'));
    var currentIndex = -1;
    var previousOverflow = '';
    var preloadCache = {};
    var swipeStartX = 0;
    var swipeStartY = 0;
    var isSwiping = false;
    var isAnimating = false;
    var swipeDirection = null;
    var minSwipeDistance = 45;

    function wrapIndex(index) {
        if (galleryItems.length === 0) { return 0; }
        return ((index % galleryItems.length) + galleryItems.length) % galleryItems.length;
    }

    function infoAtIndex(index) {
        if (galleryItems.length === 0) { return null; }
        var item = galleryItems[wrapIndex(index)];
        return {
            src: item.getAttribute('data-fullsrc') || '',
            filename: item.getAttribute('data-filename') || ''
        };
    }

    function srcAtIndex(index) {
        var info = infoAtIndex(index);
        return info ? info.src : null;
    }

    function preloadSrc(src) {
        if (!src || preloadCache[src]) { return; }
        var img = new Image();
        img.src = src;
        preloadCache[src] = img;
    }

    function preloadAround(index) {
        if (galleryItems.length === 0) { return; }
        preloadSrc(srcAtIndex(index));
        preloadSrc(srcAtIndex(index + 1));
        preloadSrc(srcAtIndex(index - 1));
    }

    function getStageWidth() {
        return Math.max(1, stage.offsetWidth || lightbox.clientWidth || window.innerWidth);
    }

    function getTriggerFromTarget(target) {
        if (!target || typeof target.closest !== 'function') { return null; }
        return target.closest('.galerie-item');
    }

    function tx(el, x) {
        el.style.transform = 'translateX(' + x + 'px)';
    }

    function txScaled(el, x) {
        var sw = getStageWidth();
        var scale = 1 - Math.min(1, Math.abs(x) / sw) * 0.05;
        el.style.transform = 'translateX(' + x + 'px) scale(' + scale.toFixed(3) + ')';
    }

    function resetNeighbor() {
        neighborImg.src = '';
        neighborImg.style.opacity = '0';
        neighborImg.style.transition = 'none';
        neighborImg.style.transform = 'translateX(0)';
    }

    function updateMeta(info) {
        caption.textContent = info.filename || '';
        downloadLink.href = info.src;
        downloadLink.setAttribute('download', info.filename || 'foto');
    }

    function closeLightbox() {
        if (isAnimating) { return; }
        lightbox.hidden = true;
        full.removeAttribute('src');
        full.style.transition = 'none';
        full.style.transform = '';
        resetNeighbor();
        caption.textContent = '';
        downloadLink.setAttribute('href', '#');
        downloadLink.removeAttribute('download');
        currentIndex = -1;
        document.body.style.overflow = previousOverflow;
    }

    function openLightbox(index) {
        var si = wrapIndex(index);
        var info = infoAtIndex(si);
        if (!info || !info.src) { return; }
        currentIndex = si;
        full.src = info.src;
        full.style.transition = 'none';
        full.style.transform = 'translateX(0)';
        resetNeighbor();
        updateMeta(info);
        lightbox.hidden = false;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        preloadAround(currentIndex);
    }

    // Keyboard/Pfeil-Navigation: beide Bilder gleichzeitig animieren
    function animateToIndex(nextIndex, direction) {
        if (isAnimating) { return; }
        var si = wrapIndex(nextIndex);
        var nextInfo = infoAtIndex(si);
        if (!nextInfo || !nextInfo.src) { return; }

        isAnimating = true;
        var sw = getStageWidth();
        var neighborStart = direction === 'next' ? sw : -sw;
        var outX = direction === 'next' ? -sw : sw;

        neighborImg.src = nextInfo.src;
        neighborImg.style.opacity = '1';
        neighborImg.style.transition = 'none';
        tx(neighborImg, neighborStart);

        window.requestAnimationFrame(function () {
            full.style.transition = 'transform 240ms cubic-bezier(0.22, 1, 0.36, 1)';
            tx(full, outX);
            neighborImg.style.transition = 'transform 240ms cubic-bezier(0.22, 1, 0.36, 1)';
            tx(neighborImg, 0);

            window.setTimeout(function () {
                currentIndex = si;
                full.src = nextInfo.src;
                full.style.transition = 'none';
                tx(full, 0);
                resetNeighbor();
                updateMeta(nextInfo);
                isAnimating = false;
                preloadSrc(direction === 'next' ? srcAtIndex(currentIndex + 1) : srcAtIndex(currentIndex - 1));
            }, 260);
        });
    }

    function showNextImage() {
        if (currentIndex < 0) { return; }
        animateToIndex(currentIndex + 1, 'next');
    }

    function showPreviousImage() {
        if (currentIndex < 0) { return; }
        animateToIndex(currentIndex - 1, 'previous');
    }

    // Touch: finishSwipe nach touchend/cancel
    function finishSwipe(deltaX, deltaY) {
        if (!isSwiping) { return; }
        isSwiping = false;

        var sw = getStageWidth();
        var dir = swipeDirection;

        if (!dir || Math.abs(deltaX) < minSwipeDistance || Math.abs(deltaX) <= Math.abs(deltaY)) {
            full.style.transition = 'transform 220ms cubic-bezier(0.22, 1, 0.36, 1)';
            full.style.transform = 'translateX(0) scale(1)';
            window.setTimeout(function () { full.style.transition = 'none'; }, 240);
            resetNeighbor();
            swipeDirection = null;
            return;
        }

        isAnimating = true;
        swipeDirection = null;
        var si = wrapIndex(dir === 'next' ? currentIndex + 1 : currentIndex - 1);
        var nextInfo = infoAtIndex(si);
        var outX = dir === 'next' ? -sw : sw;

        full.style.transition = 'transform 200ms cubic-bezier(0.22, 1, 0.36, 1)';
        tx(full, outX);
        neighborImg.style.transition = 'transform 200ms cubic-bezier(0.22, 1, 0.36, 1)';
        tx(neighborImg, 0);

        window.setTimeout(function () {
            currentIndex = si;
            full.src = nextInfo.src;
            full.style.transition = 'none';
            tx(full, 0);
            resetNeighbor();
            updateMeta(nextInfo);
            isAnimating = false;
            preloadSrc(dir === 'next' ? srcAtIndex(currentIndex + 1) : srcAtIndex(currentIndex - 1));
        }, 220);
    }

    // Grid
    grid.addEventListener('click', function (event) {
        var trigger = getTriggerFromTarget(event.target);
        if (!trigger) { return; }
        var src = trigger.getAttribute('data-fullsrc');
        if (!src) { return; }
        var index = parseInt(trigger.getAttribute('data-index'), 10);
        if (Number.isNaN(index)) { index = galleryItems.indexOf(trigger); }
        openLightbox(index);
    });

    closeButton.addEventListener('click', closeLightbox);

    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) { closeLightbox(); }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !lightbox.hidden) { closeLightbox(); return; }
        if (lightbox.hidden) { return; }
        if (event.key === 'ArrowRight') { showNextImage(); }
        else if (event.key === 'ArrowLeft') { showPreviousImage(); }
    });

    lightbox.addEventListener('touchstart', function (event) {
        if (lightbox.hidden || isAnimating || !event.touches || event.touches.length !== 1) { return; }
        swipeStartX = event.touches[0].clientX;
        swipeStartY = event.touches[0].clientY;
        isSwiping = true;
        swipeDirection = null;
    }, { passive: true });

    lightbox.addEventListener('touchmove', function (event) {
        if (!isSwiping || lightbox.hidden || isAnimating || !event.touches || event.touches.length !== 1) { return; }

        var deltaX = event.touches[0].clientX - swipeStartX;
        var deltaY = event.touches[0].clientY - swipeStartY;
        if (Math.abs(deltaX) <= Math.abs(deltaY)) { return; }

        event.preventDefault();

        var sw = getStageWidth();
        var newDir = deltaX < 0 ? 'next' : 'previous';

        if (swipeDirection && newDir !== swipeDirection) {
            resetNeighbor();
            swipeDirection = null;
        }

        if (!swipeDirection) {
            swipeDirection = newDir;
            var neighborSrc = srcAtIndex(swipeDirection === 'next' ? currentIndex + 1 : currentIndex - 1);
            if (neighborSrc) {
                neighborImg.src = neighborSrc;
                neighborImg.style.opacity = '1';
                neighborImg.style.transition = 'none';
                tx(neighborImg, swipeDirection === 'next' ? sw : -sw);
            }
        }

        full.style.transition = 'none';
        txScaled(full, deltaX);
        neighborImg.style.transition = 'none';
        txScaled(neighborImg, (swipeDirection === 'next' ? sw : -sw) + deltaX);
    }, { passive: false });

    lightbox.addEventListener('touchcancel', function () {
        finishSwipe(0, 0);
    }, { passive: true });

    lightbox.addEventListener('touchend', function (event) {
        if (!isSwiping || lightbox.hidden || isAnimating || !event.changedTouches || event.changedTouches.length !== 1) { return; }
        finishSwipe(
            event.changedTouches[0].clientX - swipeStartX,
            event.changedTouches[0].clientY - swipeStartY
        );
    }, { passive: true });
})();
