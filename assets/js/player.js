(function () {
    'use strict';

    const config = window.APP_CONFIG || {};
    const playlist = window.APP_PLAYLIST || {};
    const slides = Array.isArray(playlist.slides) ? playlist.slides.slice() : [];

    const layerA = document.getElementById('slide-layer-a');
    const layerB = document.getElementById('slide-layer-b');

    if (!layerA || !layerB) {
        console.error('[player] missing required DOM nodes');
        return;
    }

    const clockConfig = config.clock || {};
    const clockEnabled = clockConfig.enabled !== false;
    const clockBackground = typeof clockConfig.background === 'string' ? clockConfig.background : '#ffffff';
    const clockTextColor = typeof clockConfig.textColor === 'string' ? clockConfig.textColor : '#111111';
    const clockShowSeconds = clockConfig.showSeconds === true;
    const clockLogo = typeof clockConfig.logo === 'string' ? clockConfig.logo : '';
    const clockLogoHeight = Math.max(20, Math.min(400, Number(clockConfig.logoHeight || 100)));
    const clockTimezone = typeof clockConfig.timezone === 'string' && clockConfig.timezone !== ''
        ? clockConfig.timezone
        : 'Europe/Vienna';

    const enabledSlides = slides
        .filter((slide) => slide && slide.enabled !== false)
        .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0));

    let activeLayer = layerA;
    let standbyLayer = layerB;
    let currentIndex = -1;
    let slideTimer = null;
    let videoEndHandler = null;
    let clockIntervals = new WeakMap();
    let transitionToken = 0;
    let isTransitioning = false;
    const logCooldowns = new Map();

    function sendLog(level, message, context = {}, cooldownKey = '', cooldownMs = 0) {
        try {
            const now = Date.now();

            if (cooldownKey && cooldownMs > 0) {
                const lastAt = logCooldowns.get(cooldownKey) || 0;
                if (now - lastAt < cooldownMs) {
                    return;
                }
                logCooldowns.set(cooldownKey, now);
            }

            fetch('client_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    level,
                    message,
                    context
                }),
                keepalive: true,
                cache: 'no-store'
            }).catch(() => {});
        } catch (error) {
            // logging must never break playback
        }
    }

    function trace(message, context = {}, level = 'DEBUG', cooldownKey = '', cooldownMs = 0) {
        console.log('[player] ' + message, context);
        sendLog(level, message, context, cooldownKey, cooldownMs);
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

    function cleanupLayer(layer) {
        if (!layer) {
            return;
        }

        clearLayerClock(layer);

        const videos = layer.querySelectorAll('video');
        videos.forEach((video) => {
            try {
                video.pause();
            } catch (err) {
                // ignore
            }

            if (videoEndHandler) {
                video.removeEventListener('ended', videoEndHandler);
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
            'is-active',
            'is-entering',
            'is-exiting'
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
                year: 'numeric',
                timeZone: clockTimezone
            }).format(date);
        } catch (err) {
            return date.toLocaleDateString();
        }
    }

    function formatTime(date) {
        try {
            const options = {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: clockTimezone
            };

            if (clockShowSeconds) {
                options.second = '2-digit';
            }

            return new Intl.DateTimeFormat('de-AT', options).format(date);
        } catch (err) {
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: clockShowSeconds ? '2-digit' : undefined
            });
        }
    }

    function buildClockSlide(layer) {
        const wrapper = document.createElement('div');
        wrapper.className = 'slide-clock';
        wrapper.style.background = clockBackground;
        wrapper.style.color = clockTextColor;

        const inner = document.createElement('div');
        inner.className = 'slide-clock__inner';
        inner.style.color = clockTextColor;

        if (clockLogo) {
            const logo = document.createElement('img');
            logo.className = 'clock-logo';
            logo.src = clockLogo;
            logo.alt = 'Logo';
            logo.style.height = String(clockLogoHeight) + 'px';
            logo.style.maxHeight = String(clockLogoHeight) + 'px';
            logo.style.width = 'auto';
            wrapper.appendChild(logo);
        }

        const timeEl = document.createElement('div');
        timeEl.className = 'slide-clock__time';
        timeEl.style.color = clockTextColor;

        const dateEl = document.createElement('div');
        dateEl.className = 'slide-clock__date';
        dateEl.style.color = clockTextColor;

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

        trace('Clock slide built', {
            background: clockBackground,
            textColor: clockTextColor,
            logo: clockLogo,
            logoHeight: clockLogoHeight,
            showSeconds: clockShowSeconds
        }, 'INFO', 'clock-built', 5000);
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
            trace('Video ended', {
                id: slide.id || '',
                title: slide.title || ''
            }, 'INFO', 'video-ended-' + String(slide.id || ''), 1000);
            nextSlide();
        };

        video.addEventListener('ended', videoEndHandler);
        layer.appendChild(video);

        const playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch((err) => {
                trace('Video autoplay failed', {
                    id: slide.id || '',
                    title: slide.title || '',
                    error: err && err.message ? err.message : 'unknown'
                }, 'WARN', 'video-play-failed-' + String(slide.id || ''), 3000);
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

        trace('Render slide', {
            id: slide.id || '',
            title: slide.title || '',
            type,
            duration: normalizeDuration(slide),
            fade: normalizeFade(slide)
        }, 'INFO', 'render-' + String(slide.id || ''), 300);

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
                if (clockEnabled) {
                    buildClockSlide(layer);
                } else {
                    buildMessageSlide(layer, 'Uhr ist deaktiviert');
                }
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

    function completeTransition(token, oldLayer, newLayer, slide, fadeMs) {
        window.setTimeout(() => {
            if (token !== transitionToken) {
                return;
            }

            cleanupLayer(oldLayer);

            newLayer.classList.remove('is-entering');
            newLayer.classList.add('is-active');

            activeLayer = newLayer;
            standbyLayer = oldLayer;
            isTransitioning = false;

            trace('Crossfade transition complete', {
                id: slide.id || '',
                title: slide.title || '',
                fadeMs
            }, 'INFO', 'transition-complete-' + String(slide.id || ''), 300);

            scheduleNextSlide(slide);
        }, fadeMs + 80);
    }

    function performTransition(nextSlideData) {
        const token = ++transitionToken;
        isTransitioning = true;
        clearSlideTimer();

        const oldLayer = activeLayer;
        const newLayer = standbyLayer;
        const fadeMs = Math.max(180, Math.round(normalizeFade(nextSlideData) * 1000));

        trace('Crossfade transition start', {
            id: nextSlideData.id || '',
            title: nextSlideData.title || '',
            fadeMs
        }, 'INFO', 'transition-start-' + String(nextSlideData.id || ''), 300);

        renderSlideIntoLayer(newLayer, nextSlideData);

        oldLayer.classList.remove('is-entering', 'is-exiting');
        oldLayer.classList.add('is-active');

        newLayer.classList.remove('is-active', 'is-entering', 'is-exiting');

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (token !== transitionToken) {
                    return;
                }

                newLayer.classList.add('is-entering');
                oldLayer.classList.remove('is-active');
                oldLayer.classList.add('is-exiting');

                completeTransition(token, oldLayer, newLayer, nextSlideData, fadeMs);
            });
        });
    }

    function nextSlide() {
        if (isTransitioning) {
            trace('Skip nextSlide because transition is active', {}, 'DEBUG', 'skip-next-slide', 500);
            return;
        }

        if (!enabledSlides.length) {
            cleanupLayer(layerA);
            cleanupLayer(layerB);
            buildMessageSlide(activeLayer, 'Keine aktiven Slides vorhanden');
            activeLayer.classList.add('is-active');

            trace('No active slides available', {}, 'WARN', 'no-active-slides', 5000);
            return;
        }

        currentIndex = (currentIndex + 1) % enabledSlides.length;
        const slide = enabledSlides[currentIndex];

        if (currentIndex === 0 && !activeLayer.classList.contains('is-active')) {
            renderSlideIntoLayer(activeLayer, slide);
            activeLayer.classList.add('is-active');

            trace('Initial slide shown', {
                id: slide.id || '',
                title: slide.title || ''
            }, 'INFO', 'initial-slide', 1000);

            scheduleNextSlide(slide);
            return;
        }

        performTransition(slide);
    }

    function start() {
        if (!enabledSlides.length) {
            buildMessageSlide(activeLayer, 'Keine aktiven Slides vorhanden');
            activeLayer.classList.add('is-active');
            trace('Player start without slides', {}, 'WARN', 'player-start-empty', 5000);
            return;
        }

        trace('Player start', {
            slides_total: enabledSlides.length
        }, 'INFO', 'player-start', 2000);

        currentIndex = -1;
        nextSlide();
    }

    document.addEventListener('visibilitychange', function () {
        trace('Visibility changed', {
            visibilityState: document.visibilityState
        }, 'DEBUG', 'visibility-' + String(document.visibilityState), 1000);
    });

    window.addEventListener('beforeunload', function () {
        clearSlideTimer();
        clearLayerClock(layerA);
        clearLayerClock(layerB);
    });

    start();
})();
