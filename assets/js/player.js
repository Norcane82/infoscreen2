(function () {
    'use strict';

    const config = window.APP_CONFIG || {};
    const playlist = window.APP_PLAYLIST || {};
    const slides = Array.isArray(playlist.slides) ? playlist.slides.slice() : [];

    const layerA = document.getElementById('slide-layer-a');
    const layerB = document.getElementById('slide-layer-b');
    const rootOverlay = document.getElementById('transition-overlay-root');

    if (!layerA || !layerB || !rootOverlay) {
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
    let videoEndHandler = null;
    let clockInterval = null;
    let transitionToken = 0;
    let isTransitioning = false;

    function trace(message) {
        const line = '[player] ' + message;
        console.log(line);
    }

    function cleanupLayer(layer) {
        if (!layer) {
            return;
        }

        const video = layer.querySelector('video');
        if (video && videoEndHandler) {
            video.removeEventListener('ended', videoEndHandler);
        }
        videoEndHandler = null;

        if (clockInterval) {
            clearInterval(clockInterval);
            clockInterval = null;
        }

        layer.innerHTML = '';
        layer.classList.remove('slide-layer--cover', 'slide-layer--contain', 'is-next');
    }

    function clearSlideTimer() {
        if (slideTimer) {
            clearTimeout(slideTimer);
            slideTimer = null;
        }
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
        clockInterval = window.setInterval(updateClock, 1000);

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

        videoEndHandler = function () {
            trace('Video ended');
            nextSlide();
        };

        video.addEventListener('ended', videoEndHandler);
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

    function activateLayer(layer) {
        layerA.classList.remove('is-active', 'is-next');
        layerB.classList.remove('is-active', 'is-next');

        layer.classList.add('is-active');

        if (layer === layerA) {
            activeLayer = layerA;
            standbyLayer = layerB;
        } else {
            activeLayer = layerB;
            standbyLayer = layerA;
        }
    }

    function setOverlayPhase(phase) {
        rootOverlay.classList.remove('is-visible', 'is-hold');
        if (phase === 'fade-in') {
            rootOverlay.classList.add('is-visible');
            trace('Overlay visible ON (root fade-in)');
        } else if (phase === 'hold') {
            rootOverlay.classList.add('is-visible', 'is-hold');
            trace('Overlay hold (root)');
        } else if (phase === 'fade-out') {
            trace('Overlay visible OFF (root fade-out)');
        }
    }

    function scheduleNextSlide(slide) {
        clearSlideTimer();

        if (!slide) {
            return;
        }

        const type = resolveSlideType(slide);
        if (type === 'video') {
            return;
        }

        const durationMs = Math.max(1000, normalizeDuration(slide) * 1000);
        slideTimer = window.setTimeout(nextSlide, durationMs);
    }

    function performTransition(nextSlideData) {
        const myToken = ++transitionToken;
        clearSlideTimer();
        isTransitioning = true;

        const totalFadeMs = Math.max(180, Math.round(normalizeFade(nextSlideData) * 1000));
        const fadeInMs = Math.max(80, Math.round(totalFadeMs * 0.38));
        const holdMs = Math.max(40, Math.round(totalFadeMs * 0.18));
        const fadeOutMs = Math.max(80, totalFadeMs - fadeInMs - holdMs);

        trace(
            'Overlay transition start total=' +
            totalFadeMs +
            ' in=' +
            fadeInMs +
            ' hold=' +
            holdMs +
            ' out=' +
            fadeOutMs
        );

        renderSlideIntoLayer(standbyLayer, nextSlideData);

        window.setTimeout(() => {
            if (myToken !== transitionToken) {
                return;
            }

            setOverlayPhase('fade-in');

            window.setTimeout(() => {
                if (myToken !== transitionToken) {
                    return;
                }

                setOverlayPhase('hold');
                standbyLayer.classList.add('is-next');

                window.setTimeout(() => {
                    if (myToken !== transitionToken) {
                        return;
                    }

                    activateLayer(standbyLayer);

                    window.setTimeout(() => {
                        if (myToken !== transitionToken) {
                            return;
                        }

                        setOverlayPhase('fade-out');

                        window.setTimeout(() => {
                            if (myToken !== transitionToken) {
                                return;
                            }

                            cleanupLayer(standbyLayer);
                            isTransitioning = false;
                            trace('Overlay transition complete');
                            scheduleNextSlide(nextSlideData);
                        }, fadeOutMs);
                    }, 30);
                }, holdMs);
            }, fadeInMs);
        }, 20);
    }

    function nextSlide() {
        if (isTransitioning) {
            trace('Skip nextSlide because transition is active');
            return;
        }

        if (!enabledSlides.length) {
            cleanupLayer(activeLayer);
            cleanupLayer(standbyLayer);
            buildMessageSlide(activeLayer, 'Keine aktiven Slides vorhanden');
            activeLayer.classList.add('is-active');
            return;
        }

        currentIndex = (currentIndex + 1) % enabledSlides.length;
        const slide = enabledSlides[currentIndex];
        performTransition(slide);
    }

    function start() {
        if (!enabledSlides.length) {
            buildMessageSlide(activeLayer, 'Keine aktiven Slides vorhanden');
            activeLayer.classList.add('is-active');
            return;
        }

        const firstSlide = enabledSlides[0];
        currentIndex = 0;

        trace('Player start');
        renderSlideIntoLayer(activeLayer, firstSlide);
        activeLayer.classList.add('is-active');
        scheduleNextSlide(firstSlide);
    }

    document.addEventListener('visibilitychange', function () {
        trace('Visibility changed: ' + document.visibilityState);
    });

    window.addEventListener('beforeunload', function () {
        clearSlideTimer();

        if (clockInterval) {
            clearInterval(clockInterval);
            clockInterval = null;
        }
    });

    start();
})();
