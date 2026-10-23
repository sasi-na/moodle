/**
 * Simple JavaScript to add custom text after "Additional" or "Required by" in plugin list
 *
 * @module local_edwiserpagebuilder/plugin-customization
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str'], function($, Str) {
    'use strict';

    // Mirror pageslistsettings.js pattern for language handling
    const strings = [
        {key: 'upgrade_to_pro', component: 'local_edwiserpagebuilder'}
    ];
    var LANGS; // Global-like cache for resolved strings
    const UPGRADE_URL = 'https://edwiser.org/page-builder-for-moodle/';

    const fetchLanguages = () => {
        return Str.get_strings(strings).then(function(results) {
            LANGS = results;
            return results;
        });
    };

    const ensureLangs = () => {
        return LANGS ? Promise.resolve(LANGS) : fetchLanguages();
    };

    function renderUpgradeButtonInto(notesCell) {
        var label = LANGS && LANGS[0] ? LANGS[0] : 'Upgrade to Pro';
        var customInfo = '<span>You\'re using FREE version, to unlock full potentials of page builder</span><a href="' + UPGRADE_URL + '" target="_blank" class="upgrade-to-pro-btn" title="' + label + '">' +
            '<span>' + label + '</span>' +
            '</a>';
        notesCell.append(customInfo);
    }

    /**
     * Append Upgrade button on the plugins page row for local_edwiserpagebuilder.
     */
    const addPluginUpgradeButton = function() {
        $(document).ready(async function() {
            await ensureLangs();
            var pluginRow = $('.name-local_edwiserpagebuilder');
            if (!pluginRow.length) {
                return;
            }
            var notesCell = pluginRow.find('td:last-child');
            if (!notesCell.length) {
                return;
            }
            renderUpgradeButtonInto(notesCell);
        });
    };

    return {
        addPluginUpgradeButton: addPluginUpgradeButton
    };
});
