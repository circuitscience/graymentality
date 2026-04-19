(function () {
    const config = window.GM_SESSION_GUARD || {};
    const idleTimeoutMs = Math.max(60000, Number(config.idleTimeoutSeconds || 900) * 1000);
    const warningMs = Math.max(15000, Number(config.warningSeconds || 60) * 1000);
    const keepaliveMs = Math.max(60000, Number(config.keepaliveSeconds || 300) * 1000);
    const keepaliveUrl = String(config.keepaliveUrl || '/session_ping.php');
    const logoutUrl = String(config.logoutUrl || '/logout.php?reason=timeout');
    const loginUrl = String(config.loginUrl || '/login.php');
    const storageActivityKey = 'gm.session.lastActivity';
    const storageLogoutKey = 'gm.session.logout';
    const modal = document.getElementById('gm-session-timeout');
    const storage = (() => {
        try {
            return window.localStorage;
        } catch (error) {
            return null;
        }
    })();

    if (!modal) {
        return;
    }

    const countdownNode = modal.querySelector('[data-gm-session-countdown]');
    const stayButton = modal.querySelector('[data-gm-session-stay]');
    const logoutButton = modal.querySelector('[data-gm-session-logout]');

    let lastActivity = readTimestamp(storageGet(storageActivityKey)) || Date.now();
    let lastKeepalive = Date.now();
    let logoutStarted = false;

    writeActivity(lastActivity, false);

    function readTimestamp(value) {
        const parsed = Number(value || 0);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function storageGet(key) {
        if (!storage) {
            return null;
        }

        try {
            return storage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function storageSet(key, value) {
        if (!storage) {
            return;
        }

        try {
            storage.setItem(key, value);
        } catch (error) {
            // Ignore storage failures and keep the in-memory timer running.
        }
    }

    function setModalVisible(isVisible) {
        modal.classList.toggle('is-visible', isVisible);
        modal.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
    }

    function secondsRemaining(now) {
        const remainingMs = Math.max(0, idleTimeoutMs - (now - lastActivity));
        return Math.ceil(remainingMs / 1000);
    }

    function updateCountdown(now) {
        if (!countdownNode) {
            return;
        }

        countdownNode.textContent = String(secondsRemaining(now));
    }

    function forceLogout() {
        if (logoutStarted) {
            return;
        }

        logoutStarted = true;
        storageSet(storageLogoutKey, String(Date.now()));

        fetch(logoutUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                window.location.replace(String(data.redirect || loginUrl));
            })
            .catch(() => {
                window.location.replace(loginUrl);
            });
    }

    function sendKeepalive() {
        if (logoutStarted) {
            return;
        }

        lastKeepalive = Date.now();
        fetch(keepaliveUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(async (response) => {
                if (response.ok) {
                    return;
                }

                const data = await response.json().catch(() => ({}));
                if (response.status === 401) {
                    logoutStarted = true;
                    window.location.replace(String(data.redirect || loginUrl));
                }
            })
            .catch(() => {
                // Network hiccups should not force a logout on their own.
            });
    }

    function maybeKeepalive() {
        if (document.hidden) {
            return;
        }

        if ((Date.now() - lastKeepalive) >= keepaliveMs) {
            sendKeepalive();
        }
    }

    function writeActivity(timestamp, syncStorage = true) {
        lastActivity = timestamp;
        setModalVisible(false);
        updateCountdown(Date.now());

        if (syncStorage) {
            storageSet(storageActivityKey, String(timestamp));
        }

        maybeKeepalive();
    }

    function handleActivity() {
        if (logoutStarted) {
            return;
        }

        const now = Date.now();
        if ((now - lastActivity) < 1000) {
            return;
        }

        writeActivity(now, true);
    }

    function tick() {
        if (logoutStarted) {
            return;
        }

        const now = Date.now();
        const idleFor = now - lastActivity;
        const showWarning = idleFor >= (idleTimeoutMs - warningMs);

        setModalVisible(showWarning);
        updateCountdown(now);

        if (idleFor >= idleTimeoutMs) {
            forceLogout();
        }
    }

    if (storage) {
        window.addEventListener('storage', (event) => {
            if (event.key === storageActivityKey && event.newValue) {
                writeActivity(readTimestamp(event.newValue), false);
            }

            if (event.key === storageLogoutKey && event.newValue) {
                logoutStarted = true;
                window.location.replace(loginUrl);
            }
        });
    }

    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart', 'click'].forEach((eventName) => {
        window.addEventListener(eventName, handleActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            handleActivity();
        }
    });

    if (stayButton) {
        stayButton.addEventListener('click', () => {
            writeActivity(Date.now(), true);
            sendKeepalive();
        });
    }

    if (logoutButton) {
        logoutButton.addEventListener('click', forceLogout);
    }

    setInterval(tick, 1000);
    tick();
})();
