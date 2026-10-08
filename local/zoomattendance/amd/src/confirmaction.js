define(['jquery', 'core/notification'], function($, Notification) {

    /**
     * Intercept submission of moderation forms (Dismiss session / Mark all
     * present) and show a themed Moodle confirmation modal. The form only
     * submits for real once the user confirms.
     */
    function init() {

        $(document).on('submit', 'form.lzs-confirm', function(e) {

            var form = this;

            // Re-entry after the user has confirmed: let it submit normally.
            if (form.getAttribute('data-lzs-confirmed') === '1') {
                return true;
            }

            e.preventDefault();

            var title = form.getAttribute('data-confirm-title') || '';
            var body  = form.getAttribute('data-confirm-body') || '';
            var yes   = form.getAttribute('data-confirm-yes') || '';
            var no    = form.getAttribute('data-confirm-no') || '';

            Notification.saveCancel(title, body, yes, function() {
                form.setAttribute('data-lzs-confirmed', '1');
                form.submit();
            }, no);

            return false;
        });
    }

    return {
        init: init
    };
});
