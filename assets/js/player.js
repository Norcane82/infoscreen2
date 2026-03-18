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
  const clockBackground = clockConfig.background || '#ffffff';
  const clockTextColor = clockConfig.textColor || '#111111';
  const clockShowSeconds = clockConfig.showSeconds === true;
  const clockLogo = clockConfig.logo || '';
  const clockLogoHeight = Number(clockConfig.logoHeight || 100);

  const websiteDefaults = config.website || {};
  const defaultWebsiteTimeout = Math.max(1, Number(websiteDefaults.timeout || 8));

  let currentIndex = -1;
  let currentNode = null;
  let currentTimer = null;
  let clockInterval = null;

  if (screen) {
    screen.style.background = defaultBackground;
  }

  function logDebug(message, extra = null) {
    if (extra !== null) {
      console.info(`[infoscreen2] ${message}`, extra);
      return;
    }
    console.info(`[infoscreen2] ${message}`);
  }

  function normalizePositiveNumber(value, fallback) {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
  }

  function normalizeSlide(slide, index) {
    const type = String(slide.type || '').toLowerCase();

    return {
      id: slide.id || `slide_${index + 1}`,
      type,
      title: slide.title || `Slide ${index + 1}`,
      enabled: slide.enabled !== false,
      duration: Number(slide.duration || 0) || (type === 'clock' ? defaultClockDuration : defaultDuration),
      fade: Number(slide.fade || 0) || defaultFade,
      sort: Number(slide.sort || 0),
      file: slide.file || '',
      url: slide.url || '',
      bg: slide.bg || defaultBackground,
      fit: slide.fit || defaultFit,
      clock: slide.clock || {},
      timeout: normalizePositiveNumber(
        slide.timeout || slide.websiteTimeout || 0,
        defaultWebsiteTimeout
      ),
      refreshSeconds: normalizePositiveNumber(slide.refreshSeconds || 0, 0)
    };
  }

  const preparedSlides = slides
    .map(normalizeSlide)
    .filter((slide) => slide.enabled);

  function clearNodeRuntime(node) {
    if (!node) {
      return;
    }

    if (node._websiteState?.timeoutHandle) {
      clearTimeout(node._websiteState.timeoutHandle);
      node._websiteState.timeoutHandle = null;
    }

    if (node._websiteState?.refreshHandle) {
      clearInterval(node._websiteState.refreshHandle);
      node._websiteState.refreshHandle = null;
    }
  }

  function clearTimers() {
    if (currentTimer) {
      clearTimeout(currentTimer);
      currentTimer = null;
    }

    if (clockInterval) {
      clearInterval(clockInterval);
      clockInterval = null;
    }

    clearNodeRuntime(currentNode);
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

  function withCacheBuster(url) {
    if (!url) {
      return '';
    }

    try {
      const parsed = new URL(url, window.location.href);
      parsed.searchParams.set('_ifsr', String(Date.now()));
      return parsed.toString();
    } catch (error) {
      const separator = url.includes('?') ? '&' : '?';
      return `${url}${separator}_ifsr=${Date.now()}`;
    }
  }

  function renderWebsiteSlide(slide) {
    const { wrapper, inner } = makeBaseSlide(slide);

    const iframe = document.createElement('iframe');
    iframe.src = slide.url || 'about:blank';
    iframe.className = 'website-frame';
    iframe.loading = 'eager';
    iframe.referrerPolicy = 'no-referrer';

    wrapper._websiteState = {
      iframe,
      loaded: false,
      timedOut: false,
      timeoutHandle: null,
      refreshHandle: null,
      timeoutSeconds: slide.timeout,
      refreshSeconds: slide.refreshSeconds,
      originalUrl: slide.url || ''
    };

    inner.appendChild(iframe);

    return wrapper;
  }

  function renderClockSlide(slide) {
    const { wrapper, inner } = makeBaseSlide(slide);

    inner.classList.add('clock-slide');
    inner.style.background = clockBackground;
    inner.style.color = clockTextColor;

    const logoPath = slide.clock?.logo || clockLogo || '';
    const showLogo = slide.clock?.showLogo === true && !!logoPath;

    if (showLogo) {
      const logo = document.createElement('img');
      logo.src = logoPath;
      logo.alt = 'Logo';
      logo.className = 'clock-logo';
      logo.style.height = `${clockLogoHeight}px`;
      logo.style.maxHeight = `${clockLogoHeight}px`;
      logo.style.width = 'auto';
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
    const timeOptions = {
      hour: '2-digit',
      minute: '2-digit',
      timeZone: clockTimezone
    };

    if (clockShowSeconds) {
      timeOptions.second = '2-digit';
    }

    const timeText = new Intl.DateTimeFormat('de-AT', timeOptions).format(now);
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

  function advanceToNextSlide() {
    if (!preparedSlides.length) {
      return;
    }

    const nextIndex = (currentIndex + 1) % preparedSlides.length;
    showSlide(nextIndex);
  }

  function setupWebsiteRuntime(node, slide) {
    const websiteState = node?._websiteState;
    if (!websiteState) {
      return;
    }

    const { iframe } = websiteState;

    if (!websiteState.originalUrl) {
      logDebug('Website-Slide ohne URL wird übersprungen.', {
        slideId: slide.id,
        title: slide.title
      });
      advanceToNextSlide();
      return;
    }

    const markLoaded = () => {
      if (websiteState.timedOut) {
        return;
      }

      websiteState.loaded = true;

      if (websiteState.timeoutHandle) {
        clearTimeout(websiteState.timeoutHandle);
        websiteState.timeoutHandle = null;
      }

      logDebug('Website-Slide geladen.', {
        slideId: slide.id,
        title: slide.title,
        url: websiteState.originalUrl
      });
    };

    const markFailedAndSkip = (reason) => {
      if (websiteState.timedOut) {
        return;
      }

      websiteState.timedOut = true;

      if (websiteState.timeoutHandle) {
        clearTimeout(websiteState.timeoutHandle);
        websiteState.timeoutHandle = null;
      }

      if (websiteState.refreshHandle) {
        clearInterval(websiteState.refreshHandle);
        websiteState.refreshHandle = null;
      }

      node.dataset.websiteState = reason;

      logDebug('Website-Slide fehlgeschlagen, nächster Slide wird geladen.', {
        slideId: slide.id,
        title: slide.title,
        url: websiteState.originalUrl,
        reason
      });

      advanceToNextSlide();
    };

    iframe.addEventListener(
      'load',
      () => {
        markLoaded();
      },
      { once: true }
    );

    iframe.addEventListener(
      'error',
      () => {
        markFailedAndSkip('error');
      },
      { once: true }
    );

    websiteState.timeoutHandle = window.setTimeout(() => {
      if (!websiteState.loaded) {
        markFailedAndSkip('timeout');
      }
    }, websiteState.timeoutSeconds * 1000);

    if (websiteState.refreshSeconds > 0) {
      websiteState.refreshHandle = window.setInterval(() => {
        if (websiteState.timedOut || !node.classList.contains('active')) {
          return;
        }

        logDebug('Website-Slide wird neu geladen.', {
          slideId: slide.id,
          title: slide.title,
          url: websiteState.originalUrl,
          refreshSeconds: websiteState.refreshSeconds
        });

        websiteState.loaded = false;

        if (websiteState.timeoutHandle) {
          clearTimeout(websiteState.timeoutHandle);
        }

        websiteState.timeoutHandle = window.setTimeout(() => {
          if (!websiteState.loaded) {
            markFailedAndSkip('timeout_after_refresh');
          }
        }, websiteState.timeoutSeconds * 1000);

        iframe.src = withCacheBuster(websiteState.originalUrl);
      }, websiteState.refreshSeconds * 1000);
    }
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

    if (slide.type === 'website') {
      setupWebsiteRuntime(node, slide);
    }
  }

  function deactivateOldNode(oldNode, fadeSeconds) {
    if (!oldNode) {
      return;
    }

    clearNodeRuntime(oldNode);

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
        stage.innerHTML = 'Keine aktiven Slides vorhanden.';
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
      advanceToNextSlide();
    }, nextDelay);
  }

  function startPlayer() {
    if (!preparedSlides.length) {
      if (stage) {
        stage.innerHTML = 'Keine aktiven Slides vorhanden.';
      }
      return;
    }

    showSlide(0);
  }

  startPlayer();
})();
