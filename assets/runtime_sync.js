(function () {
    'use strict';

    var runtime = window.APP_RUNTIME || {};
    var statusUrl = String(runtime.statusUrl || 'status.php');
    var currentView = String(runtime.currentView || 'index');
    var handledReloadAt = Number(runtime.reloadRequestedAt || 0);
    var pollTimer = null;
    var isBusy = false;

    function targetUrlForView(viewName) {
        return viewName === 'fallback' ? 'fallback.php' : 'index.php';
    }

    function safeNumber(value) {
        var n = Number(value || 0);
        return isNaN(n) ? 0 : n;
    }

    function navigateTo(viewName, reloadAt) {
        handledReloadAt = safeNumber(reloadAt);
        window.location.replace(targetUrlForView(viewName) + '?_=' + handledReloadAt);
    }

    function handleStatusPayload(data) {
        if (!data || data.ok !== true) {
            return;
        }

        var requestedView = String(data.requested_view || 'index');
        var reloadRequestedAt = safeNumber(data.reload_requested_at);

        if (requestedView !== currentView) {
            navigateTo(requestedView, reloadRequestedAt);
            return;
        }

        if (reloadRequestedAt > handledReloadAt) {
            navigateTo(requestedView, reloadRequestedAt);
        }
    }

    function fetchStatus() {
        var xhr;

        if (isBusy) {
            return;
        }

        isBusy = true;
        xhr = new XMLHttpRequest();

        xhr.onreadystatechange = function () {
            var data;

            if (xhr.readyState !== 4) {
                return;
            }

            isBusy = false;

            if (xhr.status < 200 || xhr.status >= 300) {
                return;
            }

            try {
                data = JSON.parse(xhr.responseText);
            } catch (err) {
                return;
            }

            handleStatusPayload(data);
        };

        xhr.onerror = function () {
            isBusy = false;
        };

        try {
            xhr.open('GET', statusUrl + '?_=' + Date.now(), true);
            xhr.setRequestHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            xhr.setRequestHeader('Pragma', 'no-cache');
            xhr.send();
        } catch (err) {
            isBusy = false;
        }
    }

    function startPolling() {
        pollTimer = window.setInterval(fetchStatus, 3000);
        window.setTimeout(fetchStatus, 1500);
    }

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    });

    
    startPolling();
})();
