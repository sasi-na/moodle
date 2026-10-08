define(['core/notification'], function(Notification) {

    /**
     * Show the "Access Denied" popup.
     */
    function denied() {
        Notification.alert(
            'Access Denied',
            'Unable to open the Reflective Journal. Please try again later.'
        );
    }

    return {
        init: function() {

            var buttons = document.querySelectorAll('.reflectivejournal-btn');

            buttons.forEach(function(btn) {

                btn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Open the tab synchronously inside the click handler so it is
                    // not blocked by the browser; we point it at the URL once we have it.
                    var tab = window.open('', '_blank');

                    fetch(M.cfg.wwwroot + '/blocks/reflectivejournal/ajax.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'sesskey=' + encodeURIComponent(M.cfg.sesskey)
                    }).then(function(response) {
                        return response.json();
                    }).then(function(data) {
                        if (data && data.success && data.login_url) {
                            tab.location.href = data.login_url;
                        } else {
                            if (tab) {
                                tab.close();
                            }
                            denied();
                        }
                    }).catch(function() {
                        if (tab) {
                            tab.close();
                        }
                        denied();
                    });
                });
            });
        }
    };
});
