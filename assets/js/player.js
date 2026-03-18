(() => {
    const config = window.INFOSCREEN_CONFIG || {};
    const slides = Array.isArray(window.INFOSCREEN_SLIDES) ? window.INFOSCREEN_SLIDES : [];
    const stage = document.getElementById('slide-stage');
    const screen = document.getElementById('screen');

    const screenConfig = config.screen || {};
    const clockConfig = config.clock || {};

    const defaultDuration = Number(screenConfig.defaultDuration || 8);
    const defaultFade = Number(screenConfig.defaultFade || 1);
    const defaultFit = screenConfig.fit || 'contain';
    const defaultBackground = screenConfig.background || '#ffffff';
    const clockEnabled = clockConfig.enabled !== false;
    const clockTimezone = clockConfig.timezone || 'Europe/Vienna';
    const defaultClockDuration = Number(clockConfig.defaultDuration || 10);

    let currentIndex = -1;
    let currentNode = null;
    let currentTimer = null;
    let clockInterval = null;

    if (screen) {
        screen.style.background = defaultBackground;
    }

    function normalizeSlide(slide, index) {
        const type = String(slide.type || '').toLowerCase();

        return {
            id: slide.id || `slide_${index + 1}`,
            type,
            title: slide.title || `Slide ${index + 1}`,
            enabled: slide.enabled !== false,
            duration: Number(slide.duration || 0) || (
                type === 'clock' ? defaultClockDuration : defaultDuration
            ),
            fade: Number(slide.fade || 0) || defaultFade,
            sort: Number(slide.sort || 0),
            file: slide.file || '',
            url: slide.url || '',
            bg: slide.bg || defaultBackground,
            fit: slide.fit || defaultFit,
            clock: slide.clock || {}
        };
    }

    const preparedSlides = slides
        .map(normalizeSlide)
        .filter(slide => slide.enabled);

    function clearTimers() {
        if (currentTimer) {
            clearTimeout(currentTimer);
            currentTimer = null;
        }

        if (clockInterval) {
            clearInterval(clockInterval);
            clockInterval = null;
        }
    }

    function applyFade(node, seconds) {
        node.style.transition = `opacity ${seconds}s ease`;
    }

    function makeBaseSlide(slide) {
        const wrapper = document.createElement('div');
        wrapper.className = 'slide';
        wrapper.dataset.id = slide.id;
        wrapper.dataset.type = slide.type;
        wrapper.style.background = slide.bg || defaultBackground;
        applyFade(wrapper, slide.fade);

        const inner = document.createElement('div');
        inner.className = 'slide-inner';
        wrapper.appendChild(inner);

        return { wrapper, inner };
    }

    function renderImageSlide(slide) {
        const { wrapper, inner } = makeBaseSlide(slide);
        inner.classList.add(slide.fit === 'cover' ? 'media-cover' : 'media-contain');

        const img = document.createElement('img');
        img.src = slide.file;
        img.alt = slide.title || '';
        img.loading = 'eager';

        inner.appendChild(img);
        return wrapper;
    }

    function renderVideoSlide(slide) {
        const { wrapper, inner } = makeBaseSlide(slide);
        inner.classList.add(slide.fit === 'cover' ? 'media-cover' : 'media-contain');

        const video = document.createElement('video');
        video.src = slide.file;
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';

        inner.appendChild(video);
        return wrapper;
    }

    function renderPdfSlide(slide) {
        const { wrapper, inner } = makeBaseSlide(slide);

        const embed = document.createElement('embed');
        embed.src = slide.file;
        embed.type = 'application/pdf';
        embed.className = 'pdf-frame';

        inner.appendChild(embed);
        return wrapper;
    }

    function renderWebsiteSlide(slide) {
        const { wrapper, inner } = makeBaseSlide(slide);

        const iframe = document.createElement('iframe');
        iframe.src = slide.url;
        iframe.className = 'website-frame';
        iframe.loading = 'eager';
        iframe.referrerPolicy = 'no-referrer';

        inner.appendChild(iframe);
        return wrapper;
    }

    function renderClockSlide(slide) {
        const { wrapper, inner } = makeBaseSlide(slide);

        inner.classList.add('clock-slide');

        const logoPath = slide.clock?.logo || '';
        const showLogo = slide.clock?.showLogo === true && !!logoPath;

        if (showLogo) {
            const logo = document.createElement('img');
            logo.src = logoPath;
            logo.alt = 'Logo';
            logo.className = 'clock-logo';
            inner.appendChild(logo);
        }

        const timeEl = document.createElement('div');
        timeEl.className = 'clock-time';

        const dateEl = document.createElement('div');
        dateEl.className = 'clock-date';

        inner.appendChild(timeEl);
        inner.appendChild(dateEl);

        wrapper._clockElements = { timeEl, dateEl };
        return wrapper;
    }

    function renderFallbackSlide(slide) {
        const { wrapper, inner } = makeBaseSlide(slide);

        const note = document.createElement('div');
        note.className = 'fallback-note';
        note.textContent = `Unbekannter oder unvollständiger Slide-Typ: ${slide.type}`;

        inner.appendChild(note);
        return wrapper;
    }

    function createSlideNode(slide) {
        switch (slide.type) {
            case 'image':
                return renderImageSlide(slide);
            case 'video':
                return renderVideoSlide(slide);
            case 'pdf':
                return renderPdfSlide(slide);
            case 'website':
                return renderWebsiteSlide(slide);
            case 'clock':
                return clockEnabled ? renderClockSlide(slide) : renderFallbackSlide(slide);
            default:
                return renderFallbackSlide(slide);
        }
    }

    function updateClock(node) {
        if (!node || !node._clockElements) {
            return;
        }

        const now = new Date();

        const timeText = new Intl.DateTimeFormat('de-AT', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: clockTimezone
        }).format(now);

        const dateText = new Intl.DateTimeFormat('de-AT', {
            weekday: 'long',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: clockTimezone
        }).format(now);

        node._clockElements.timeEl.textContent = timeText;
        node._clockElements.dateEl.textContent = dateText;
    }

    function activateNode(node, slide) {
        stage.appendChild(node);

        requestAnimationFrame(() => {
            node.classList.add('active');
        });

        if (slide.type === 'clock') {
            updateClock(node);
            clockInterval = setInterval(() => updateClock(node), 1000);
        }

        if (slide.type === 'video') {
            const video = node.querySelector('video');
            if (video) {
                const playPromise = video.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(() => {});
                }
            }
        }
    }

    function deactivateOldNode(oldNode, fadeSeconds) {
        if (!oldNode) {
            return;
        }

        oldNode.classList.remove('active');
        oldNode.classList.add('leaving');

        window.setTimeout(() => {
            oldNode.remove();
        }, Math.max(300, fadeSeconds * 1000 + 50));
    }

    function showSlide(index) {
        clearTimers();

        if (!preparedSlides.length || !stage) {
            if (stage) {
                stage.innerHTML = '<div class="fallback-note">Keine aktiven Slides vorhanden.</div>';
            }
            return;
        }

        const slide = preparedSlides[index];
        const node = createSlideNode(slide);
        const oldNode = currentNode;

        activateNode(node, slide);
        deactivateOldNode(oldNode, slide.fade);

        currentNode = node;
        currentIndex = index;

        const nextDelay = Math.max(1, slide.duration) * 1000;
        currentTimer = window.setTimeout(() => {
            const nextIndex = (currentIndex + 1) % preparedSlides.length;
            showSlide(nextIndex);
        }, nextDelay);
    }

    function startPlayer() {
        if (!preparedSlides.length) {
            if (stage) {
                stage.innerHTML = '<div class="fallback-note">Keine aktiven Slides vorhanden.</div>';
            }
            return;
        }

        showSlide(0);
    }

    startPlayer();
})();
