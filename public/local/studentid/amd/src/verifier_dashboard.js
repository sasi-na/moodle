// AMD module for verifier dashboard and review page.
define(['jquery', 'core/str'], function($, Str) {

    var SELECTORS = {
        REJECT_PRESET: '#sid-reject-preset',
        REJECT_COMMENT: '#reject-comment',
        REJECT_FORM:   '#reject-form',
    };

    /**
     *
     */
    function initActionPanels() {
        // Approve button — require all 4 photo checklist checkboxes, then show panel.
        $(document).on('click', '#sid-approve-btn', function() {
            var allChecked = $('#chk1').is(':checked') && $('#chk2').is(':checked') &&
                             $('#chk3').is(':checked') && $('#chk4').is(':checked');
            if (!allChecked) {
                // Remove any old warning first.
                $('#sid-checklist-warn').remove();
                var $warn = $('<div id="sid-checklist-warn" class="alert alert-warning alert-dismissible mt-2 mb-0" role="alert">' +
                    '<span class="fa fa-exclamation-triangle mr-1"></span>' +
                    'Please confirm all 4 photo checklist items before approving.' +
                    '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                    '<span aria-hidden="true">&times;</span></button></div>');
                $(this).closest('.card-body').append($warn);
                return;
            }
            $('#sid-checklist-warn').remove();
            $('#approve-panel').removeClass('d-none');
            $('#reject-panel').addClass('d-none');
            $('#approve-comment').focus();
        });

        // Reject button — show reject panel, hide approve panel.
        $(document).on('click', '#sid-reject-btn', function() {
            $('#reject-panel').removeClass('d-none');
            $('#approve-panel').addClass('d-none');
            $('#sid-reject-preset').focus();
        });

        // Cancel buttons.
        $(document).on('click', '#sid-approve-cancel', function() {
            $('#approve-panel').addClass('d-none');
        });
        $(document).on('click', '#sid-reject-cancel', function() {
            $('#reject-panel').addClass('d-none');
        });
    }

    /**
     *
     */
    function initPhotoZoom() {
        var $lb = $('#sid-lightbox');
        var $lbImg = $('#sid-lightbox-img');

        /**
         * Open the lightbox showing the given image.
         *
         * @param {String} src The image URL to display.
         */
        function openLightbox(src) {
            $lbImg.attr('src', src);
            $lb.css('display', 'flex');
            $('body').css('overflow', 'hidden');
        }

        /**
         *
         */
        function closeLightbox() {
            $lb.hide();
            $('body').css('overflow', '');
        }

        // Click any zoomable image (profile pic or aadhaar).
        $(document).on('click', '.sid-zoomable', function() {
            var src = $(this).data('fullsrc') || $(this).attr('src');
            if (src) {
                openLightbox(src);
            }
        });

        // Close on backdrop click or × button.
        $(document).on('click', '#sid-lightbox', function(e) {
            if ($(e.target).is('#sid-lightbox') || $(e.target).is('#sid-lightbox-close')) {
                closeLightbox();
            }
        });

        // Close on Escape key.
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    }

    /**
     *
     */
    function initRejectionPreset() {
        $(document).on('change', SELECTORS.REJECT_PRESET, function() {
            var val = $(this).val();
            if (val) {
                $(SELECTORS.REJECT_COMMENT).val(val);
            }
        });
    }

    /**
     *
     */
    function initRejectFormValidation() {
        $(document).on('submit', SELECTORS.REJECT_FORM, function(e) {
            var comment = $(SELECTORS.REJECT_COMMENT).val().trim();
            if (comment) {
                return true;
            }
            e.preventDefault();
            Str.get_string('errorcommentrequired', 'local_studentid').then(function(s) {
                var msg = $('<div class="text-danger small mt-1 sid-reject-err">' +
                    '<span class="fa fa-exclamation-triangle mr-1"></span>' + s + '</div>');
                $('.sid-reject-err').remove();
                $(SELECTORS.REJECT_COMMENT).after(msg);
                $(SELECTORS.REJECT_COMMENT)[0].focus();
                return s;
            }).catch(function() {
                return;
            });
            return false;
        });
    }

    /**
     *
     */
    function initSearchDebounce() {
        var searchInput = $('input[name="search"]');
        var debounceTimer;
        searchInput.on('input', function() {
            clearTimeout(debounceTimer);
            var form = $(this).closest('form');
            debounceTimer = setTimeout(function() {
                form.submit();
            }, 600);
        });
    }

    /**
     *
     */
    function highlightOverdueRows() {
        // Highlight rows where days_queued > 2.
        $('tbody tr').each(function() {
            var daysTxt = $(this).find('td:nth-child(5) small').text();
            var days = parseInt(daysTxt, 10);
            if (!isNaN(days) && days > 2) {
                $(this).find('td:nth-child(5) small').addClass('text-danger font-weight-bold');
            }
        });
    }

    return {
        init: function() {
            initSearchDebounce();
            highlightOverdueRows();
        },

        initReview: function() {
            initActionPanels();
            initPhotoZoom();
            initRejectionPreset();
            initRejectFormValidation();
        }
    };
});
