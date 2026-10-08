define(['jquery'], function($) {

    /**
     * Initialize popup handler
     */
    function init() {

        $(document).on('click',
            'a[data-key="livesessionattendance"]',
            function(e) {

                e.preventDefault();

                var url = $(this).attr('href');

                window.open(
                    url,
                    'LiveSessionAttendance',
                    'width=1000,height=650,scrollbars=yes,resizable=yes'
                );
            }
        );
    }

    return {
        init: init
    };
});
