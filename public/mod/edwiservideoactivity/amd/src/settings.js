/* eslint-disable no-console */
define('theme_remui/settings', ['jquery'], function($) {
    return {
        validateCompletionPercent: function() {
            var inputField = $('#id_completionpercent');
            var errorDiv = $('#fgroup_id_error_completionpercentgroup');

            if (inputField.length === 0 || errorDiv.length === 0) {
                return;
            }

            var value = inputField.val().trim();

            // Check if value is empty
            if (value === '') {
                errorDiv.removeClass('d-block').text('');
                return;
            }

            // Check if value is negative, greater than 100, or contains non-numeric characters
            var numericValue = parseInt(value, 10);
            var isNegative = numericValue < 0;
            var isGreaterThan100 = numericValue > 100;
            var containsNonNumeric = !/^\d+$/.test(value);

            if (isNegative || isGreaterThan100 || containsNonNumeric) {
                errorDiv.addClass('d-block').text(M.util.get_string('onlyintegervalueallowed', 'mod_edwiservideoactivity'));
            } else {
                errorDiv.removeClass('d-block').text('');
            }
        },
        init: function() {
            console.log('init');
            if ($('#id_unlockcompletion').length > 0) {
                $('#fitem_id_mediasource').hide();
                $('#fitem_id_mediaurlmessage').hide();
                $('#fitem_id_mediaurl').hide();
                $('#fitem_id_embeddmessage').hide();
                $('#fitem_id_embedcode').hide();
                $('#fitem_id_uploadbandwidthnotice').hide();
                $('#fitem_id_mediafile').hide();

                var customhtml = `
                        <div id="completionNotice">
                            <div class="d-flex header align-items-center">⚠️
                                <h6 class="header-text">Important</h6>
                            </div>
                            <div class="notice-text">
                                Since the completion condition has already been met, access to change the media file is restricted.
                                To update the media file, go to the ‘Completion Condition’ tab below and click
                                ‘Unlock Completion Settings’.
                            </div>
                        </div>`;
                var target = document.getElementById('id_error_name');
                if (target) {
                    target.insertAdjacentHTML('afterend', customhtml);
                }
            }
            // Validate completion percent on init
            this.validateCompletionPercent();

            // Add input event listener for completion percent field
            var self = this;
            $('#id_completionpercent').on('input', function() {
                self.validateCompletionPercent();
            });
        }
    };
});

