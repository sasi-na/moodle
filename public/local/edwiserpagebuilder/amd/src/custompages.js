/* eslint-disable promise/catch-or-return */
define([
    'local_edwiserpagebuilder/jquery',
    'core/ajax',
    'core/templates',
    'core/modal_factory',
    'core/modal_events',
    'core/str',
    'core/notification',
], function($, Ajax, Templates, ModalFactory, ModalEvents, str, Notification) {

    const SELECTORS = {
        'ADDNEWPAGESEL': '.epb-addnewpage',
        'TITLEINPUT': 'epbpagetitle',
        'TITLEINPUTSEL': '#epbpagetitle',
        'NEXTBTN': 'activatepagecreation',
        'NEXTBTNSEL': '.activatepagecreation',
        'MODALSAVEACTION': '.layoutchooser.save',
        'MODALBACKACTION': '.layoutchooser.back',
        'PAGELAYOUTWRAPPER': 'page-layouts-wrapper',
        'PAGELAYOUTWRAPPERSEL': '.page-layouts-wrapper',
        'LAYOUTSRADIOBTN': 'layoutsradiobtn',
        'LAYOUTSRADIOBTNSEL': 'input[name="layoutsradiobtn"]',
        'PREVIEWLAYOUTBTNSEL': '.btn_view_icon',
        'PREVIEWLAYOUT': '.layout-preview',
        'CLOSELAYOUTBTNSEL': '.layout_close_btn',
        'CHOOSEDEFAULTLAYOUTSEL': '.default .btn_add_block',
        'LAYOUTBODYSEL': '.modal_card_body',
        'LAYOUTCARDSEL': '.modal_card[data-layoutid]'
    };

    // ****** IMPORTANT ******
    // Do not change the sequence.
    // If you want to add new strings here, add it at the bottom.
    // Do not remove any string from the array.
    // There is no way we can revert back if sequence is changed.
    // ****** IMPORTANT ******
    const strings = [
        {key: 'addnewpage', component: 'local_edwiserpagebuilder'},
        {key: 'next', component: 'local_edwiserpagebuilder'},
        {key: 'pagetitle', component: 'local_edwiserpagebuilder'},
        {key: 'back', component: 'local_edwiserpagebuilder'},
        {key: 'create', component: 'local_edwiserpagebuilder'},
        {key: 'select', component: 'local_edwiserpagebuilder'},
        {key: 'chooselayout', component: 'local_edwiserpagebuilder'}
    ];

    var LANGS; // Gloabl variable to store languages.
    var titlemodal; // Variable to store reference of Title Modal.
    var layoutmodal; // Variable to store reference of Layout Modal.

    // Functionality to fetch strings.
    const fetchLanguages = () => {
        str.get_strings(strings).then(function(results) {
            LANGS = results;
            return results;
        });
    };

    // Add functionality to create page from Scratch.
    const createpagefromscratch = (title, layoutid) => {
        Ajax.call([{
            methodname: 'local_edwiserpagebuilder_do_page_action',
            args: {
                action: "add_new_page_with_layoutid",
                config: JSON.stringify({
                    'title': title,
                    'layoutid': layoutid
                })
            },
            done: function(response) {
                id = JSON.parse(response);

                // if (layoutid == 0) {
                //     // Setting the config if the page has no pagelayout.
                //     setConfig("show-layout-" + id, true, "local_edwiserpagebuilder");
                // }

                // Redirect to newly created page.
                let newUrl = M.cfg.wwwroot + '/local/edwiserpagebuilder/pagedraft.php?id=' + id;

                window.location.href = newUrl;
            },
            fail: function (ex) {
                Notification.exception(ex);
            }
        }]);

    };


    // // Add functionality to add layout on exisiting page
    function addBlankLayoutOnExistingPage(pageid, layoutid) {
        Ajax.call([{
            methodname: 'local_edwiserpagebuilder_do_page_action',
            args: {
                action: "add_layout_on_exisiting_page",
                config: JSON.stringify({
                    'pageid': pageid,
                    'layoutid': layoutid
                })
            },
            done: function (response) {
                id = JSON.parse(response);

                // if (layoutid == 0) {
                //     // Setting the config if the page has no pagelayout.
                //     setConfig("show-layout-" + id, true, "local_edwiserpagebuilder");
                // }

                // Redirect to newly created page.
                let newUrl = M.cfg.wwwroot + '/local/edwiserpagebuilder/pagedraft.php?id=' + id;

                window.location.href = newUrl;
            },
            fail: function (ex) {
                Notification.exception(ex);
            }
        }]);
    }

    // Layout Chooser Modal.
    const openLayoutSelector = async(callback, onlylayout = false) => {
        var loaderurl = M.cfg.wwwroot + '/local/edwiserpagebuilder/pix/siteinnerloader.svg';
        layoutmodal = await ModalFactory.create({
            title: onlylayout ? LANGS[6] : LANGS[0],
            type: ModalFactory.types.SAVE_CANCEL,
            body: `
            <div class="page-layouts-wrapper section-modal-body">
                <div class="addnewpageform d-flex justify-content-center align-items-center">
                    <img src="${loaderurl}" alt="spinner"/>
                </div>
            </div>
            `
        }).done(function(modal) {

            if (onlylayout) {

                // Change Save button text.
                modal.setButtonText('save', LANGS[5]);
            } else {
                // Change cancel button styling.
                modal.setButtonText('cancel', LANGS[3]);

                $(modal.getActionSelector('cancel')).addClass("border-0");

                // Change Save button text.
                modal.setButtonText('save', LANGS[4]);
            }

            // Add full width class to modal.
            var extraclasses = 'fullwidth-modal layout-chooser';

            extraclasses += (onlylayout) ? ' onlylayout' : "";

            modal.getModal().parent().addClass(extraclasses);

            modal.show();
        });

        $(layoutmodal.getActionSelector('save')).addClass("btn-disabled");

        // Handle clicks on radio buttons and their parent containers - redirect if locked
        // Note: Locked layouts don't have radio buttons (they're removed in template)
        $(document).on('click', '.template_name, .template_name_text, ' + SELECTORS.LAYOUTSRADIOBTNSEL, function(e) {
            let layoutCard = $(this).closest('.modal_card[data-layoutid]');

            // Check if layout is locked by checking for locked-layout class or missing radio button
            let isLocked = layoutCard.hasClass('locked-layout');
            let radioButton = layoutCard.find('input[type="radio"]');

            // If no radio button exists or layout has locked-layout class, it's locked
            if (isLocked || radioButton.length === 0) {
                e.preventDefault();
                e.stopPropagation();
                let layoutid = layoutCard.data('layoutid');
                let upgradeUrl = layoutCard.find('.upgrade-to-pro-btn').data('upgrade');
                if (upgradeUrl) {
                    window.open(upgradeUrl, '_blank');
                } else {
                    // Fallback to default upgrade URL
                    window.open('https://edwiser.org/page-builder-for-moodle/', '_blank');
                }
                return false;
            }
        });

        // This code handle checkbox, show that only one checkbox can be checked
        $(document).on('change', SELECTORS.LAYOUTSRADIOBTNSEL, function() {
            // Only enable save if the selected radio is not disabled (not locked)
            if (!$(this).prop('disabled')) {
                $(layoutmodal.getActionSelector('save')).removeClass("btn-disabled");
            }
        });

        // Handle upgrade button clicks in lock overlay
        $(document).on('click', '.upgrade-to-pro-btn[data-upgrade]', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent triggering parent click handlers
            let upgradeUrl = $(this).data('upgrade');
            if (upgradeUrl) {
                window.open(upgradeUrl, '_blank');
            }
            return false;
        });

        // This event trigger when we click anywhere on the layout card wrapper
        $(document).on('click', SELECTORS.LAYOUTCARDSEL, function(e) {
            // Don't handle clicks on specific interactive elements (they have their own handlers)
            if ($(e.target).closest('.upgrade-to-pro-btn, .btn_view_icon, .template_name, input[type="radio"], .layout-preview, .layout_close_btn').length > 0) {
                return;
            }

            let layoutid = $(this).data("layoutid");
            let targetInput = $(`${SELECTORS.LAYOUTSRADIOBTNSEL}[value=${layoutid}]`);

            // Check if layout is locked (has locked-layout class or radio button doesn't exist)
            let isLocked = $(this).hasClass('locked-layout') || targetInput.length === 0;
            if (isLocked) {
                e.preventDefault();
                e.stopPropagation();
                // Find upgrade button and get URL from data-upgrade attribute
                let upgradeUrl = $(this).find('.upgrade-to-pro-btn').data('upgrade');
                if (upgradeUrl) {
                    window.open(upgradeUrl, '_blank');
                } else {
                    // Fallback to default upgrade URL
                    window.open('https://edwiser.org/page-builder-for-moodle/', '_blank');
                }
                return false; // Prevent selection
            }

            // If not locked, allow normal selection behavior
            targetInput.prop('checked', true);
            $(layoutmodal.getActionSelector('save')).removeClass("btn-disabled");
        });

        // Trigger when blank layout select
        $(document).on('click', SELECTORS.CHOOSEDEFAULTLAYOUTSEL, function() {

            var title = $(SELECTORS.TITLEINPUTSEL).val();
            var bodyClasses = $("body").attr("class");
            var pageclassdata = bodyClasses.match(/epb-showlayout-(\d+)/);

            if ( pageclassdata != null && pageclassdata.length > 0) {

                var pageid = pageclassdata[1];

                addBlankLayoutOnExistingPage(pageid, 0);
            } else {
                createpagefromscratch(title, 0);
            }

        });

        // Things to be done on layout modal destruction.
        layoutmodal.getRoot().on(ModalEvents.destroyed, () => {
            titlemodal.destroy();
        });

        // Things to be done on layout save.
        layoutmodal.getRoot().on(ModalEvents.save, (e) => {
            // Get chosen layout id.
            var layoutid = $(SELECTORS.LAYOUTSRADIOBTNSEL + ':checked').val();

            // Safety check: ensure selected layout is not locked
            var selectedInput = $(SELECTORS.LAYOUTSRADIOBTNSEL + ':checked');
            if (selectedInput.length > 0) {
                let layoutCard = selectedInput.closest('.modal_card[data-layoutid]');
                let isLocked = layoutCard.hasClass('locked-layout');
                if (isLocked) {
                    e.preventDefault();
                    Notification.addNotification({
                        message: 'Cannot select locked layout. Please upgrade to Pro.',
                        type: 'error'
                    });
                    return false;
                }
            }

            // Trigger the callback.
            callback(layoutid);

            if (onlylayout) {
                // Destroy title modal as well.
                titlemodal.destroy();
            }
        });

        // This function will work asynchronously.
        // This will fetch all the available layouts and render it to created layout modal.
        fetchLayoutTemplates('local_edwiserpagebuilder/select_page_layout')
        .then((layouts) => {
            $(SELECTORS.PAGELAYOUTWRAPPERSEL).empty().append(layouts.pagelayoutjson);
            return;
        });
    };

    /**
     * JS version of set_config core function.
     * @param {String} name
     * @param {Raw} value
     * @param {String} plugin
     */
    // const setConfig = (name, value, plugin = "") => {
    //     Ajax.call([{
    //         methodname: 'local_edwiserpagebuilder_do_page_action',
    //         args: {
    //             action: "set_config",
    //             config: JSON.stringify({
    //                 'name': name,
    //                 'value': value,
    //                 'plugin': plugin
    //             })
    //         },
    //         fail: function(ex) {
    //             Notification.exception(ex);
    //         }
    //     }]);
    // };

    /**
     * JS version of unset_config core function.
     * @param {String} name
     * @param {String} plugin
     */
    // const unsetConfig = (name, plugin = "") => {
    //     Ajax.call([{
    //         methodname: 'local_edwiserpagebuilder_do_page_action',
    //         args: {
    //             action: "set_config",
    //             config: JSON.stringify({
    //                 'name': name,
    //                 'value': value,
    //                 'plugin': plugin
    //             })
    //         },
    //         fail: function(ex) {
    //             Notification.exception(ex);
    //         }
    //     }]);
    // };

    /**
     * Fetch all available page layout templates.
     * templatename if set this function will return rendered html or
     * it will return encoded Layout list.
     * @param {String} templatename
     * @returns {String} Encoded layout list/ Layout cards rendered from templatename
     */
    const fetchLayoutTemplates = async(templatename = "") => {

        var param = {templatename: templatename};

        const request = {
            methodname: 'local_edwiserpagebuilder_epb_fetch_layout_templates',
            args: param
        };

        return Ajax.call([request])[0];
    };

    // Create page title modal.
    const createPageTitleModal = async() => {
        titlemodal = await ModalFactory.create({
            title: LANGS[0],
            body: `
            <div class="addnewpageform">
                <div class="form-group">
                    <label for="${SELECTORS.TITLEINPUT}" class="label h-semibold-6">${LANGS[2]}</label>
                    <input id="${SELECTORS.TITLEINPUT}" name="${SELECTORS.TITLEINPUT}" class="form-control"/>
                </div>
                <button class="btn btn-primary btn-disabled float-right ${SELECTORS.NEXTBTN}" disabled="true">
                    ${LANGS[1]}
                    <span class="edw-icon edw-icon-Right-Arrow font-size-16">
                </button>
            </div>
            `,
        });
        titlemodal.show();
    };

    const init = () => {
        fetchLanguages();
        registerEvents();
    };

    const initLayoutSelector = (pageid) => {
        LANGS = fetchLanguages();
        var onlylayout = true;
        setTimeout(() => {
            openLayoutSelector(
                function(layoutid) {
                    addLayoutOnPage(pageid, layoutid);
                },
                onlylayout
            );
        }, "1000");

    };

    const addLayoutOnPage = (pageid, layoutid) => {
        Ajax.call([{
            methodname: 'local_edwiserpagebuilder_do_page_action',
            args: {
                action: "edw_publish_page_on_pageid",
                config: JSON.stringify({
                    'pageid': pageid,
                    'layoutid': layoutid
                })
            },
            done: function(response) {
                id = JSON.parse(response);

                // Redirect to newly created page.
                let newUrl = M.cfg.wwwroot + '/local/edwiserpagebuilder/pagedraft.php?id=' + id;
                window.location.href = newUrl;
            },
            fail: function(ex) {
                Notification.exception(ex);
            }
        }]);
    };

    const registerEvents = () => {

        // Trigger Modal Creation.
        $(document).on('click', SELECTORS.ADDNEWPAGESEL, function() {
            createPageTitleModal();
        });

        $(document).on('click', SELECTORS.NEXTBTNSEL, function() {
            var title = $(SELECTORS.TITLEINPUTSEL).val();

            openLayoutSelector(function(layoutid) {
                createpagefromscratch(title, layoutid);
            });
        });



        // Trigger on keyup for input text field.
        $(document).on('keyup', SELECTORS.TITLEINPUTSEL, function() {
            if ($(this).val().length == 0) {
                $(SELECTORS.NEXTBTNSEL).addClass("btn-disabled").prop('disabled', true);
            } else {
                $(SELECTORS.NEXTBTNSEL).removeClass("btn-disabled").prop('disabled', false);
            }
        });

        // Tirgger on eye icon for showing preview of layout
        $(document).on('click', SELECTORS.PREVIEWLAYOUTBTNSEL, function(e) {
            let layoutid = $(this).data("layoutid");
            let layoutCard = $(this).closest('.modal_card[data-layoutid]');
            let targetInput = $(`${SELECTORS.LAYOUTSRADIOBTNSEL}[value=${layoutid}]`);

            // Check if layout is locked (has locked-layout class or radio button doesn't exist)
            let isLocked = layoutCard.hasClass('locked-layout') || targetInput.length === 0;
            if (isLocked) {
                e.preventDefault();
                // Find upgrade button and get URL from data-upgrade attribute
                let upgradeUrl = layoutCard.find('.upgrade-to-pro-btn').data('upgrade');
                if (upgradeUrl) {
                    window.open(upgradeUrl, '_blank');
                } else {
                    // Fallback to default upgrade URL
                    window.open('https://edwiser.org/page-builder-for-moodle/', '_blank');
                }
                return false;
            }

            $(`${SELECTORS.PREVIEWLAYOUT}-${layoutid}`).removeClass("d-none");
        });

        // Trigger on back button of preview
        $(document).on('click', SELECTORS.CLOSELAYOUTBTNSEL, function() {
            let layoutid = $(this).data("layoutid");
            $(`${SELECTORS.PREVIEWLAYOUT}-${layoutid}`).addClass("d-none");
        });

    };

    return {
        init: init,
        initLayoutSelector: initLayoutSelector
    };
});
