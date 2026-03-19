(function () {
    'use strict';

    const config = window.APP_CONFIG || {};
    const playlist = window.APP_PLAYLIST || {};
    const slides = Array.isArray(playlist.slides) ? playlist.slides.slice() : [];

    const layerA = document.getElementById('slide-layer-a');
    const layerB = document.getElementById('slide-layer-b');
    const rootOverlay = document.getElementById('transition-overlay-root');

    if (!layerA || !layerB) {
        console.error('[player] missing required DOM nodes');
        return;
    }

    const enabledSlides = slides
        .filter((slide) => slide && slide.enabled !== false)
        .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0));

    let activeLayer = layerA;
    let standbyLayer = layerB;
    let currentIndex = -1;
    let slideTimer = null;
    let clockIntervals = new WeakMap();
    let videoHandlers = new WeakMap();
    let transitionToken = 0;
    let isTransitioning = false;
    let hasStartedPlayback = false;

    function trace(message) {
        console.log('[player] ' + message);
    }

    function clearSlideTimer() {
        if (slideTimer) {
            clearTimeout(slideTimer);
            slideTimer = null;
        }
    }

    function clearLayerClock(layer) {
        const intervalId = clockIntervals.get(layer);
        if (intervalId) {
            clearInterval(intervalId);
            clockIntervals.delete(layer);
        }
    }

    function clearLayerVideoHandlers(layer) {
        const handlers = videoHandlers.get(layer);
        if (!handlers) {
            return;
        }

        const videos = layer.querySelectorAll('video');
        videos.forEach((video) => {
            if (handlers.ended) {
                video.removeEventListener('ended', handlers.ended);
            }
        });

        videoHandlers.delete(layer);
    }

    function cleanupLayer(layer) {
        if (!layer) {
            return;
        }

        clearLayerClock(layer);
        clearLayerVideoHandlers(layer);

        const videos = layer.querySelectorAll('video');
        videos.forEach((video) => {
            try {
                video.pause();
            } catch (err) {
                // ignore
            }

            video.removeAttribute('src');

            try {
                video.load();
            } catch (err) {
                // ignore
            }
        });

        layer.innerHTML = '';
        layer.classList.remove(
            'slide-layer--cover',
            'slide-layer--contain',
            'is-next',
            'is-active',
            'is-leaving'
        );
        layer.style.background = '#000000';
        layer.style.transitionDuration = '';
    }

    function normalizeDuration(slide) {
        const value = Number(slide && slide.duration);
        if (Number.isFinite(value) && value > 0) {
            return value;
        }
        return 10;
    }

    function normalizeFade(slide) {
        const value = Number(slide && slide.fade);
        if (Number.isFinite(value) && value >= 0) {
            return value;
        }
        return 1.2;
    }

    function getFitClass(slide) {
        const fit = String((slide && slide.fit) || 'contain').toLowerCase();
        return fit === 'cover' ? 'slide-layer--cover' : 'slide-layer--contain';
    }

    function resolveSlideType(slide) {
        const type = String((slide && slide.type) || '').toLowerCase();
        if (type) {
            return type;
        }

        const file = String((slide && slide.file) || '').toLowerCase();
        if (/\.(png|jpe?g|gif|webp|bmp|svg)$/.test(file)) {
            return 'image';
        }
        if (/\.(mp4|webm|ogg|mov|m4v)$/.test(file)) {
            return 'video';
        }
        if (/\.pdf$/.test(file)) {
            return 'pdf';
        }
        return 'website';
    }

    function setLayerBackground(layer, slide) {
        const bg = String((slide && slide.bg) || '#000000');
        layer.style.background = bg;
    }

    function setLayerFade(layer, fadeSeconds) {
        const duration = Math.max(0.12, Number(fadeSeconds) || 0.6);
        layer.style.transitionDuration = duration + 's';
    }

    function formatDate(date) {
        try {
            return new Intl.DateTimeFormat('de-AT', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }).format(date);
        } catch (err) {
            return date.toLocaleDateString();
        }
    }

    function formatTime(date) {
        try {
            return new Intl.DateTimeFormat('de-AT', {
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        } catch (err) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }

    function setOverlayVisible(visible) {
        if (!rootOverlay) {
            return;
        }

        rootOverlay.classList.toggle('is-visible', !!visible);
        trace(visible ? 'Overlay visible ON (disabled mode)' : 'Overlay visible OFF (disabled mode)');
    }

    function buildClockSlide(layer) {
        const wrapper = document.createElement('div');
        wrapper.className = 'slide-clock';

        const inner = document.createElement('div');
        inner.className = 'slide-clock__inner';

        const timeEl = document.createElement('div');
        timeEl.className = 'slide-clock__time';

        const dateEl = document.createElement('div');
        dateEl.className = 'slide-clock__date';

        function updateClock() {
            const now = new Date();
            timeEl.textContent = formatTime(now);
            dateEl.textContent = formatDate(now);
        }

        updateClock();
        const intervalId = window.setInterval(updateClock, 1000);
        clockIntervals.set(layer, intervalId);

        inner.appendChild(timeEl);
        inner.appendChild(dateEl);
        wrapper.appendChild(inner);
        layer.appendChild(wrapper);
    }

    function buildMessageSlide(layer, text) {
        const message = document.createElement('div');
        message.className = 'slide-message';
        message.textContent = text;
        layer.appendChild(message);
    }

    function renderImageSlide(layer, slide) {
        const img = document.createElement('img');
        img.src = slide.file || '';
        img.alt = slide.title || '';
        img.loading = 'eager';
        layer.appendChild(img);
    }

    function renderVideoSlide(layer, slide) {
        const video = document.createElement('video');
        video.src = slide.file || '';
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';

        const endedHandler = function () {
            trace('Video ended');
            nextSlide();
        };

        video.addEventListener('ended', endedHandler);
        videoHandlers.set(layer, { ended: endedHandler });

        layer.appendChild(video);

        const playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch((err) => {
                trace('Video autoplay failed: ' + err.message);
            });
        }
    }

    function renderWebsiteSlide(layer, slide) {
        const iframe = document.createElement('iframe');
        iframe.src = slide.url || slide.file || '';
        iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
        iframe.setAttribute('allow', 'autoplay; fullscreen');
        iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox');
        layer.appendChild(iframe);
    }

    function renderPdfSlide(layer, slide) {
        const iframe = document.createElement('iframe');
        iframe.src = slide.file || '';
        iframe.setAttribute('title', slide.title || 'PDF');
        layer.appendChild(iframe);
    }

    function renderSlideIntoLayer(layer, slide) {
        cleanupLayer(layer);
        setLayerBackground(layer, slide);
        setLayerFade(layer, normalizeFade(slide));
        layer.classList.add(getFitClass(slide));

        const type = resolveSlideType(slide);
        trace('Render slide type=' + type + ' title=' + String(slide.title || ''));

        switch (type) {
            case 'image':
                renderImageSlide(layer, slide);
                break;
            case 'video':
                renderVideoSlide(layer, slide);
                break;
            case 'website':
                renderWebsiteSlide(layer, slide);
                break;
            case 'pdf':
                renderPdfSlide(layer, slide);
                break;
            case 'clock':
                buildClockSlide(layer);
                break;
            default:
                buildMessageSlide(layer, 'Unbekannter Slide-Typ: ' + type);
                break;
        }
    }

    function scheduleNextSlide(slide) {
        clearSlideTimer();

        if (!slide) {
            return;
        }

        if (resolveSlideType(slide) === 'video') {
            return;
        }

        const durationMs = Math.max(1000, normalizeDuration(slide) * 1000);
        slideTimer = window.setTimeout(nextSlide, durationMs);
    }

    function finishCrossfade(myToken, oldLayer, nextLayer, nextSlideData, fadeMs) {
        window.setTimeout(() => {
            if (myToken !== transitionToken) {
                return;
            }

            cleanupLayer(oldLayer);

            nextLayer.classList.remove('is-next');
            nextLayer.classList.remove('is-leaving');
            nextLayer.classList.add('is-active');

            activeLayer = nextLayer;
            standbyLayer = oldLayer;
            isTransitioning = false;

            trace('Crossfade transition complete');
            scheduleNextSlide(nextSlideData);
        }, fadeMs + 60);
    }

    function performTransition(nextSlideData) {
        const myToken = ++transitionToken;
        isTransitioning = true;
        clearSlideTimer();

        const oldLayer = activeLayer;
        const nextLayer = standbyLayer;
        const fadeMs = Math.max(180, Math.round(normalizeFade(nextSlideData) * 1000));

        trace('Crossfade transition start fade=' + fadeMs);

        renderSlideIntoLayer(nextLayer, nextSlideData);

        oldLayer.classList.remove('is-next');
        oldLayer.classList.remove('is-leaving');
        oldLayer.classList.add('is-active');

        nextLayer.classList.remove('is-active');
        nextLayer.classList.remove('is-leaving');
        nextLayer.classList.remove('is-next');

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (myToken !== transitionToken) {
                    return;
                }

                setOverlayVisible(false);

                nextLayer.classList.add('is-next');
                oldLayer.classList.remove('is-active');
                oldLayer.classList.add('is-leaving');

                finishCrossfade(myToken, oldLayer, nextLayer, nextSlideData, fadeMs);
            });
        });
    }

    function nextSlide() {
        if (isTransitioning) {
            trace('Skip nextSlide because transition is active');
            return;
        }

        if (!enabledSlides.length) {
            cleanupLayer(layerA);
            cleanupLayer(layerB);
            buildMessageSlide(activeLayer, 'Keine aktiven Slides vorhanden');
            activeLayer.classList.add('is-active');
            return;
        }

        currentIndex = (currentIndex + 1) % enabledSlides.length;
        const slide = enabledSlides[currentIndex];

        if (!hasStartedPlayback) {
            renderSlideIntoLayer(activeLayer, slide);
            activeLayer.classList.add('is-active');
            hasStartedPlayback = true;
            trace('Initial slide shown');
            scheduleNextSlide(slide);
            return;
        }

        performTransition(slide);
    }

    function start() {
        if (!enabledSlides.length) {
            buildMessageSlide(activeLayer, 'Keine aktiven Slides vorhanden');
            activeLayer.classList.add('is-active');
            return;
        }

        trace('Player start');
        setOverlayVisible(false);
        nextSlide();
    }

    document.addEventListener('visibilitychange', function () {
        trace('Visibility changed: ' + document.visibilityState);
    });

    window.addEventListener('beforeunload', function () {
        clearSlideTimer();
        clearLayerClock(layerA);
        clearLayerClock(layerB);
        clearLayerVideoHandlers(layerA);
        clearLayerVideoHandlers(layerB);
    });

    start();
})();
