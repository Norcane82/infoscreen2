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

    function shouldRedirect(nextView) {
        return nextView !== currentView;
    }

    function fetchStatus() {
        if (isBusy) {
            return;
        }

        isBusy = true;

        fetch(statusUrl + '?_=' + Date.now(), {
            cache: 'no-store',
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data || data.ok !== true) {
                    return;
                }

                var requestedView = String(data.requested_view || 'index');
                var reloadRequestedAt = Number(data.reload_requested_at || 0);

                if (shouldRedirect(requestedView)) {
                    handledReloadAt = reloadRequestedAt;
                    window.location.replace(targetUrlForView(requestedView) + '?_=' + reloadRequestedAt);
                    return;
                }

                if (reloadRequestedAt > handledReloadAt) {
                    handledReloadAt = reloadRequestedAt;
                    window.location.replace(targetUrlForView(requestedView) + '?_=' + reloadRequestedAt);
                }
            })
            .catch(function () {
                // absichtlich still: der Player soll bei kurzem Statusfehler weiterlaufen
            })
            .finally(function () {
                isBusy = false;
            });
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
