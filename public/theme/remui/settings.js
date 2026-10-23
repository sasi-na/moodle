define('theme_remui/settings', ['jquery', 'core_form/changechecker'], function($, FormChangeChecker) {

    /**
     * Toggle visibility of element
     * @param  {Array}   elements   Elements/Settings list
     * @param  {Boolean} visibility True to show Element and false to hide
     */
    function toggle_elements(elements, visibility) {
        if (elements == undefined) {
            return;
        }
        elements.forEach(function(element) {
            $('#admin-' + element).toggle(visibility == 1);
            $('#id_s_theme_remui_' + element).trigger('change');
        });
    }

    /**
     * Get value of triggering element
     * @param  {String} name Name of element/setting
     * @return {String}      Current/Changes value of element
     */
    function get_value(name) {
        var element = $('#id_s_theme_remui_' + name);
        if (element.is('input[type="checkbox"]')) {
            return element.is(':checked');
        }
        return element.val();
    }

    /**
     * Check current value of element and apply visibility of dependent element
     * @param  {String}  name       Name of triggered element
     * @param  {Object}  options    Object containing
     * @param  {Boolean} hide       If true then elements will be hidden forcebly. User if triggering element is hidden.
     */
    function check_settings(name, options, hide) {
        if (!Array.isArray(options)) {
            options = [options];
        }
        var value = get_value(name);
        options.forEach(function(condition) {
            if (value == condition.value) {
                if (Object.prototype.hasOwnProperty.call(condition, 'show')) {
                    toggle_elements(condition.show, true ^ hide);
                }
                if (Object.prototype.hasOwnProperty.call(condition, 'hide')) {
                    toggle_elements(condition.hide, false);
                }
            }
        });
    }

    /**
     * Initialize accordion functionality
     */
    function init_accordion() {
        // Wrap content after each heading in admin-panel-content div
        if(M.cfg.theme !== 'remui') {
            $('.remui-setting-tabs-action').addClass('d-none');
        }
        $('.remui-setting-heading').each(function() {
            var heading = $(this);
            var content = heading.nextUntil('.remui-setting-heading');

            if (content.length > 0) {
                content.wrapAll('<div class="admin-panel-content hidden"></div>');
            }
        });

        // Set initial states
        $('.remui-setting-heading').addClass('collapsed');
        $('.admin-panel-content').addClass('hidden');

        // Ensure the correct action button is visible on load for each tab
        $('.tab-pane').each(function() {
            updateTabActionButtons(this);
        });
    }

    /**
     * Update the Expand all / Collapse all action buttons based on current tab state
     * @param {HTMLElement|jQuery} refEl Element inside the tab (heading or tab-pane)
     */
    function updateTabActionButtons(refEl) {
        var currentTab = $(refEl).closest('.tab-pane');
        if (currentTab.length === 0) {
            currentTab = $('body');
        }

        var headings = currentTab.find('.remui-setting-heading');
        if (headings.length === 0) {
            return;
        }

        var expandedCount = headings.filter('.expanded').length;
        var collapsedCount = headings.filter('.collapsed').length;
        var actionBar = currentTab.find('.remui-setting-tabs-action');

        if (expandedCount === headings.length) {
            actionBar.find('.collapseall').removeClass('d-none');
            actionBar.find('.expandall').addClass('d-none');
        } else if (collapsedCount === headings.length) {
            actionBar.find('.expandall').removeClass('d-none');
            actionBar.find('.collapseall').addClass('d-none');
        } else {
            // Mixed state: prefer showing "Expand all"
            actionBar.find('.expandall').removeClass('d-none');
            actionBar.find('.collapseall').addClass('d-none');
        }
    }

    /**
     * Attach change listener to element
     */
    function attach_listener() {
        Object.keys(remuisettings).forEach(function(name) {
            $('#id_s_theme_remui_' + name).on('change', function() {
                if ($('#admin-' + name).css('display') == 'none') {
                    check_settings(name, remuisettings[name], true);
                    return;
                }
                check_settings(name, remuisettings[name], false);
            })
        });
        Object.keys(remuisettings).forEach(function(name) {
            check_settings(name, remuisettings[name], false);
            $('#id_s_theme_remui_' + name).trigger('change');
        });

        // Settings update on change.
        $(`#id_s_theme_remui_frontpagechooser`).change(function() {
            FormChangeChecker.disableAllChecks();
            this.form.submit();
        });
        // Expand/Collapse all functionality - scoped to current active tab only
        $('.remui-setting-tabs-action .expandall').click(function(e) {
            e.preventDefault();
            var currentTab = $(this).closest('.tab-pane');
            var currentTabAction = $(this).closest('.remui-setting-tabs-action');

            // Toggle button visibility for current tab only
            currentTabAction.find('.collapseall').removeClass('d-none');
            currentTabAction.find('.expandall').addClass('d-none');

            // Expand all sections in current tab only using CSS classes
            currentTab.find('.remui-setting-heading').each(function() {
                var heading = $(this);
                var content = heading.nextUntil('.remui-setting-heading');
                if (content.length === 0) {
                    content = heading.siblings('.admin-panel-content');
                }

                // Toggle CSS classes for expanded state
                heading.removeClass('collapsed').addClass('expanded');
                heading.find('h3.main').addClass('active');
                content.removeClass('hidden').addClass('visible');

                // Update individual expand/collapse icons
                heading.find('.expand-setting-action').addClass('d-none');
                heading.find('.collapse-setting-action').removeClass('d-none');
            });

            // Sync action buttons with state
            updateTabActionButtons(currentTab);
        });

        $('.remui-setting-tabs-action .collapseall').click(function(e) {
            e.preventDefault();
            var currentTab = $(this).closest('.tab-pane');
            var currentTabAction = $(this).closest('.remui-setting-tabs-action');

            // Toggle button visibility for current tab only
            currentTabAction.find('.expandall').removeClass('d-none');
            currentTabAction.find('.collapseall').addClass('d-none');

            // Collapse all sections in current tab only using CSS classes
            currentTab.find('.remui-setting-heading').each(function() {
                var heading = $(this);
                var content = heading.nextUntil('.remui-setting-heading');
                if (content.length === 0) {
                    content = heading.siblings('.admin-panel-content');
                }

                // Toggle CSS classes for collapsed state
                heading.removeClass('expanded').addClass('collapsed');
                heading.find('h3.main').removeClass('active');
                content.removeClass('visible').addClass('hidden');

                // Update individual expand/collapse icons
                heading.find('.collapse-setting-action').addClass('d-none');
                heading.find('.expand-setting-action').removeClass('d-none');
            });

            // Sync action buttons with state
            updateTabActionButtons(currentTab);
        });

        // Individual setting expand/collapse functionality using CSS classes
        $('.expand-setting-action').click(function(e) {
            e.preventDefault();
            var heading = $(this).closest('.remui-setting-heading');
            var content = heading.nextUntil('.remui-setting-heading');
            if (content.length === 0) {
                content = heading.siblings('.admin-panel-content');
            }

            // Toggle CSS classes for expanded state
            heading.removeClass('collapsed').addClass('expanded');
            heading.find('h3.main').addClass('active');
            content.removeClass('hidden').addClass('visible');

            // Update individual expand/collapse icons
            $(this).addClass('d-none');
            heading.find('.collapse-setting-action').removeClass('d-none');

            // Sync action buttons with state
            updateTabActionButtons(heading);
        });

        $('.collapse-setting-action').click(function(e) {
            e.preventDefault();
            var heading = $(this).closest('.remui-setting-heading');
            var content = heading.nextUntil('.remui-setting-heading');
            if (content.length === 0) {
                content = heading.siblings('.admin-panel-content');
            }

            // Toggle CSS classes for collapsed state
            heading.removeClass('expanded').addClass('collapsed');
            heading.find('h3.main').removeClass('active');
            content.removeClass('visible').addClass('hidden');

            // Update individual expand/collapse icons
            $(this).addClass('d-none');
            heading.find('.expand-setting-action').removeClass('d-none');

            // Sync action buttons with state
            updateTabActionButtons(heading);
        });

        // Make entire heading clickable for expand/collapse
        $('.remui-setting-heading').click(function(e) {
            // Don't trigger if clicking on the action buttons
            if ($(e.target).closest('.remui-setting-heading-action').length > 0) {
                return;
            }

            var heading = $(this);
            var content = heading.nextUntil('.remui-setting-heading');
            if (content.length === 0) {
                content = heading.siblings('.admin-panel-content');
            }

            // Toggle between expanded and collapsed states
            if (heading.hasClass('collapsed')) {
                // Expand
                heading.removeClass('collapsed').addClass('expanded');
                heading.find('h3.main').addClass('active');
                content.removeClass('hidden').addClass('visible');

                // Update individual expand/collapse icons
                heading.find('.expand-setting-action').addClass('d-none');
                heading.find('.collapse-setting-action').removeClass('d-none');
            } else {
                // Collapse
                heading.removeClass('expanded').addClass('collapsed');
                heading.find('h3.main').removeClass('active');
                content.removeClass('visible').addClass('hidden');

                // Update individual expand/collapse icons
                heading.find('.collapse-setting-action').addClass('d-none');
                heading.find('.expand-setting-action').removeClass('d-none');
            }

            // Sync action buttons with state
            updateTabActionButtons(heading);
        });
    }

    function checkEnableSignup() {
        if ($('.enableSignUpDescNotice').length > 0) {
            $('#id_s_theme_remui_enablesignup')
                .prop('checked', false)
                .prop('disabled', true);
        }
    }

    return {
        init: function() {
            init_accordion();
            attach_listener('');
            checkEnableSignup();
        }
    };
});
