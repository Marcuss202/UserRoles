(function () {
    var CHECK_INTERVAL_MS = 5000;
    var isChecking = false;

    function redirectToLogin(reason) {
        var target = 'login.php';
        if (reason === 'account_deleted') {
            target += '?deleted=1';
        }
        window.location.href = target;
    }

    function checkSession() {
        if (isChecking) {
            return;
        }
        isChecking = true;

        fetch('session_status.php', { cache: 'no-store' })
            .then(function (response) {
                if (response.ok) {
                    return response.json();
                }
                return response.json().then(function (data) {
                    throw data;
                });
            })
            .then(function (data) {
                if (!data || data.ok !== true) {
                    redirectToLogin(data && data.reason ? data.reason : 'not_logged_in');
                }
            })
            .catch(function (err) {
                if (err && err.reason) {
                    redirectToLogin(err.reason);
                } else {
                    redirectToLogin('not_logged_in');
                }
            })
            .finally(function () {
                isChecking = false;
            });
    }

    setInterval(checkSession, CHECK_INTERVAL_MS);
})();
