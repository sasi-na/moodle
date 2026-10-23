// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Theme customizer Quick setup
 *
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Yogesh Shirsath
 */

import $ from 'jquery';

/**
 * MAIN settings list.
 */
var MAIN = {
    PRIMARY: '[name="sitecolorhex"]',
    SECONDARY: '[name="secondarycolor"]',
    TEXT: '[name="themecolors-textcolor"]',
    BORDER: '[name="themecolors-bordercolor"]'
};

/**
 * Secondary colors list.
 */
var SECONDARY = [
    {key: 'bg', target: '[name="global-colors-pagebackgroundcolor"]'},
    {key: 'ascentbg', target: '[name="global-colors-ascentbackgroundcolor"]'},
    {key: 'elementbg', target: '[name="global-colors-elementbackgroundcolor"]'},

    // Border color.
    {key: 'lightborder', target: '[name="themecolors-lightbordercolor"]'},
    {key: 'mediumborder', target: '[name="themecolors-mediumbordercolor"]'},

    // Link colors.
    {key: 'link', target: '[name="global-typography-body-linkcolor"]'},
    {key: 'linkhover', target: '[name="global-typography-body-linkhovercolor"]'},

    // Heading colors.
    {key: 'headingstext', target: '[name="typography-heading-all-textcolor"]'},
    {key: 'headingstext', target: '[name="typography-heading-h1-textcolor"]'},
    {key: 'headingstext', target: '[name="typography-heading-h2-textcolor"]'},
    {key: 'headingstext', target: '[name="typography-heading-h3-textcolor"]'},
    {key: 'headingstext', target: '[name="typography-heading-h4-textcolor"]'},
    {key: 'headingstext', target: '[name="typography-heading-h5-textcolor"]'},
    {key: 'headingstext', target: '[name="typography-heading-h6-textcolor"]'},

    // Primary button colors.
    {key: 'primarybuttonbg', target: '[name="button-primary-color-background"]'},
    {key: 'primarybuttonbghover', target: '[name="button-primary-color-background-hover"]'},
    {key: 'primarybuttonborder', target: '[name="button-primary-border-color"]'},
    {key: 'primarybuttonborderhover', target: '[name="button-primary-border-color-hover"]'},
    {key: 'primarybuttontext', target: '[name="button-primary-color-text"]'},
    {key: 'primarybuttontext', target: '[name="button-primary-color-text-hover"]'},
    {key: 'primarybuttonicon', target: '[name="button-primary-color-icon"]'},
    {key: 'primarybuttonicon', target: '[name="button-primary-color-icon-hover"]'},

    // Secondary button colors.
    {key: 'secondarybuttontext', target: '[name="button-secondary-color-text"]'},
    {key: 'secondarybuttontexthover', target: '[name="button-secondary-color-text-hover"]'},
    {key: 'secondarybuttonborder', target: '[name="button-secondary-border-color"]'},
    {key: 'secondarybuttonborderhover', target: '[name="button-secondary-border-color-hover"]'},
    {key: 'secondarybuttonicon', target: '[name="button-secondary-color-icon"]'},
    {key: 'secondarybuttoniconhover', target: '[name="button-secondary-color-icon-hover"]'},
    {key: 'secondarybuttonbg', target: '[name="button-secondary-color-background"]'},
    {key: 'secondarybuttonbg', target: '[name="button-secondary-color-background-hover"]'},

    // Header colors.
    {key: 'headerbg', target: '[name="logo-bg-color"]'},
    {key: 'primary', target: '[name="sitenamecolor"]'},
    {key: 'headerbg', target: '[name="header-menu-background-color"]'},
    {key: 'headertext', target: '[name="header-menu-text-color"]'},
    {key: 'headertexthover', target: '[name="header-menu-text-hover-color"]'},
    {key: 'headertextactive', target: '[name="header-menu-text-active-color"]'},
    {key: 'headerelementbg', target: '[name="header-menu-element-bg-color"]'},
    {key: 'headerdividercolordark', target: '[name="header-menu-divider-bg-color"]'},
    {key: 'headericons', target: '[name="hds-icon-color"]'},
    {key: 'headericonshover', target: '[name="hds-icon-hover-color"]'},
    {key: 'headericonsactive', target: '[name="hds-icon-active-color"]'},

    // Footer colors.
    {key: 'footerbg', target: '[name="footer-background-color"]'},
    {key: 'footertext', target: '[name="footer-text-color"]'},
    {key: 'footertext', target: '[name="footer-columntitle-color"]'},
    {key: 'footerlinktext', target: '[name="footer-link-text"]'},
    {key: 'footerlinktext', target: '[name="footer-link-hover-text"]'},
    {key: 'footerdivider', target: '[name="footer-divider-color"]'},
    {key: 'footericons', target: '[name="footer-icon-color"]'},
    {key: 'footericonshover', target: '[name="footer-icon-hover-color"]'},

    // Login page colors.
    {key: 'loginbg', target: '[name="loginpanelbackgroundcolor"]'},
    {key: 'loginpaneltextcolor', target: '[name="loginpaneltextcolor"]'},
    {key: 'loginpanelcontentcolor', target: '[name="loginpanelcontentcolor"]'},
    {key: 'loginpanellinkcolor', target: '[name="loginpanellinkcolor"]'},
    {key: 'loginpanellinkhovercolor', target: '[name="loginpanellinkhovercolor"]'},
    {key: 'loginpagebackgroundcolor', target: '[name="signuptextcolor"]'}
];

/**
 * Selectors list.
 */
var SELECTOR = {
    BASE: 'quicksetup',
    PALLETAPPLY: '[name="pallet-apply"]',
    FONTSELECTOR: '.font-selector',
    FONTAPPLY: '[name="font-apply"]',
    FONTSETTING: '[name="global-typography-body-fontfamily"]',
    PALLET: '[name="radio_colorpallet"]',
    CURRENTPALLET: '.current-pallete',
    CURRENTFONT: '.current-font',
    NAVBARINVERSE: '[name="navbarinverse"]',
    FONTVIEWER: '.current-pallete.font-pallet',
    EDITPALLET: '.current-pallete .edw-icon-Edit',
    RESETPALLET: '.current-pallete .edw-icon-Refresh',
    BRANDCOLORS: '#quicksetup #heading_brandcolors-heading',
    BRANDCOLORSCANCEL: '#heading_brandcolors-heading .edw-icon-Cancel',
    SETTINGTYPEHTML: '#quicksetup .setting-type-html'
};

/**
 * Apply settings.
 */
function apply() {
    // Dummy method.
    // This method has settings which will be applied to related settings.
    // So we don't need to apply it on every iframe refresh or link change.
}

function applyColors(colors) {
    $(MAIN.PRIMARY).spectrum('set', colors.primary).trigger('color.changed');
    $(MAIN.SECONDARY).spectrum('set', colors.secondary).trigger('color.changed');
    $(MAIN.TEXT).spectrum('set', colors.text).trigger('color.changed');
    $(MAIN.BORDER).spectrum('set', colors.border).trigger('color.changed');
    SECONDARY.forEach(setting => {
        $(setting.target).spectrum('set', colors[setting.key]).trigger('color.changed');
    });
}

/**
 * Sync color on load.
 */
function syncColor() {
    $($(SELECTOR.CURRENTPALLET).find('span').get(0)).css(
        'background',
        $(MAIN.PRIMARY).spectrum('get').toString()
    );
    $($(SELECTOR.CURRENTPALLET).find('span').get(1)).css(
        'background',
        $(MAIN.SECONDARY).spectrum('get').toString()
    );
    $($(SELECTOR.CURRENTPALLET).find('span').get(2)).css(
        'background',
        $(MAIN.TEXT).spectrum('get').toString()
    );
    $($(SELECTOR.CURRENTPALLET).find('span').get(3)).css(
        'background',
        $(MAIN.BORDER).spectrum('get').toString()
    );
}


/**
 * Restore pallet colors.
 */
function restorePalletColors() {
    const $container = $('#quicksetup #heading_brandcolors-heading .heading-content');
    const $themecolorscontainer = $('#themecolors #heading_brandcolors-heading .heading-content');

    if (!$container.length) {
        return;
    }

    // Track if any of the four watched settings needed to be reset (value !== default)
    let anyWatchedSettingNeededReset = false;

    // Process reset buttons in quicksetup container
    $container.find('.color-reset').each((index, button) => {
        const neededReset = handleColorResetClick(button);
        if (neededReset) {
            anyWatchedSettingNeededReset = true;
        }
    });

    // Process reset buttons in themecolors container
    $themecolorscontainer.find('.color-reset').each((index, button) => {
        const neededReset = handleColorResetClick(button);
        if (neededReset) {
            anyWatchedSettingNeededReset = true;
        }
    });

    // Only trigger smart-colors-button if at least one of the four watched settings needed resetting
    if (anyWatchedSettingNeededReset) {
        $container.find('[name="smart-colors-button"]').trigger('click');
    }

    setTimeout(function () {
        $('[data-region="modal-container"] [data-region="body"]')
            .text('Color palette reset successfully.');
    }, 200);
}

/**
 * Handle reset button click for color pickers, but avoid triggering the reset
 * when the current value already equals `data-default` for specific fields.
 *
 * Affects only these four settings:
 * - sitecolorhex
 * - secondarycolor
 * - themecolors-textcolor
 * - themecolors-bordercolor
 *
 * For all other color fields, the reset button is always triggered.
 *
 * @param {HTMLElement} button The reset button element (div with class "color-reset")
 * @returns {boolean} Returns true if this was a watched setting that needed resetting (value !== default), false otherwise
 */
function handleColorResetClick(button) {
    const $button = $(button);
    const $formGroup = $button.closest('.form-group');
    const $input = $formGroup.find('input[type="hidden"]');

    if (!$input.length) {
        // No input found, trigger reset anyway (not one of the watched settings)
        $button.trigger('click');
        return false;
    }

    const name = $input.attr('name');
    const value = $input.val();
    const defaultValue = $button.data('default');

    const watchedNames = [
        'sitecolorhex',
        'secondarycolor',
        'themecolors-textcolor',
        'themecolors-bordercolor'
    ];

    const isWatchedSetting = watchedNames.indexOf(name) !== -1;

    // If this is one of the four watched fields and the value already equals
    // the default, skip triggering the reset handler.
    if (isWatchedSetting && defaultValue && value === defaultValue) {
        return false; // Skipped because value equals default
    }

    // Trigger the reset button click
    $button.trigger('click');
    // Return true if this was a watched setting that needed resetting (value !== default)
    return isWatchedSetting;
}

/**
 * Sync font selected with current font item.
 */
function syncFont() {
    let font = $(SELECTOR.FONTSETTING).val();
    if (font.toLowerCase() == 'standard') {
        font = 'Inter';
    }
    // let url = "https://staging.edwiser.org/remuifonts/images/";
    // $(SELECTOR.CURRENTFONT).find('img').attr('src', url + font + '.png');

    $(SELECTOR.FONTVIEWER).find('.font-sample .font-name').text(font);
    $(SELECTOR.FONTVIEWER).find('.font-sample').css('font-family', font);
}

/**
 * Load font on quick setup when page is loaded.
 */
function onLoadFont() {
    // First time font sync.
    let font = $(SELECTOR.FONTSETTING).val();
    if (font.toLowerCase() == 'standard') {
        font = 'Inter';
    }
    $('select' + SELECTOR.FONTSELECTOR).val(font).trigger('change');
    $(SELECTOR.FONTAPPLY).attr('disabled', false);
    loadDemoFont(font);
}

/**
 * Initialize events.
 */
function init() {
    syncColor();
    syncFont();
    onLoadFont();
    $(SELECTOR.PALLET).on('change', function() {
        $(SELECTOR.PALLET).each((index, input) => {
            if (input.checked) {
                $(SELECTOR.PALLETAPPLY).attr('disabled', false);
            }
        });
    });
    $(SELECTOR.PALLETAPPLY).on('click', function() {
        $(this).attr('disabled', true);
        $(SELECTOR.PALLET).each((index, input) => {
            if (input.checked) {
                $(input).prop('checked', false).trigger('change');
                applyColors($(input).data('colors'));
            }
        });
    });


    $(SELECTOR.BRANDCOLORS).addClass('d-none');

    // Handling current pallet colors.
    $(MAIN.PRIMARY).on('color.changed', function() {
        $($(SELECTOR.CURRENTPALLET).find('span').get(0)).css('background', $(this).spectrum('get').toString());
    });
    $(MAIN.SECONDARY).on('color.changed', function() {
        $($(SELECTOR.CURRENTPALLET).find('span').get(1)).css('background', $(this).spectrum('get').toString());
    });
    $(MAIN.TEXT).on('color.changed', function() {
        $($(SELECTOR.CURRENTPALLET).find('span').get(2)).css('background', $(this).spectrum('get').toString());
    });
    $(MAIN.BORDER).on('color.changed', function() {
        $($(SELECTOR.CURRENTPALLET).find('span').get(3)).css('background', $(this).spectrum('get').toString());
    });

    // Observ font settings change.
    $(SELECTOR.FONTSETTING).on('change', syncFont);

    // Observer font.
    $(SELECTOR.FONTSELECTOR).on('change', function() {
        $(SELECTOR.FONTAPPLY).attr('disabled', false);
    });

    // Apply font.
    $(SELECTOR.FONTAPPLY).on('click', function() {
        var selectedfont = $(SELECTOR.FONTSELECTOR).selectpicker('val') + '';
        $(SELECTOR.FONTSETTING).val(selectedfont).trigger('input').trigger('change');
        loadDemoFont(selectedfont);
        // $(SELECTOR.FONTVIEWER).find('.option-label').css('font-family', selectedfont);
        // $(SELECTOR.FONTVIEWER).find('.sample-text').css('font-family', selectedfont);
        syncFont();
        let inheritFont = [
            '[name="global-typography-smallpara-fontfamily"]',
            '[name="global-typography-smallinfo-fontfamily"]',
            '[name="button-common-fontfamily"]',
            '[name="hds-menu-font-family"]',
            '[name="footerfontfamily"]',
            '[name="footer-columntitle-fontfamily"]'
        ];
        ['all', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']
        .forEach(heading => {
            inheritFont.push('[name="typography-heading-' + heading + '-fontfamily"]');
        });
        inheritFont.forEach(id => {
            if ($(id).find('option[value="Inherit"]').length) {
                $(id).val("Inherit").trigger('input').trigger('change');
            } else if ($(id).find('option[value="inherit"]').length) {
                $(id).val("inherit").trigger('input').trigger('change');
            }
        });
        $(this).attr('disabled', true);
    });

    $(SELECTOR.EDITPALLET).on('click', function() {
        $(SELECTOR.BRANDCOLORS).removeClass('d-none');
        $(SELECTOR.BRANDCOLORS).find(".group-item .edw-icon")
            .removeClass("edw-icon-Down-Arrow")
            .addClass("edw-icon-Cancel");

        $(SELECTOR.SETTINGTYPEHTML+":has(.color-pallet)").addClass('d-none');
        $(SELECTOR.SETTINGTYPEHTML+":has(#id_pallet-apply)").addClass('d-none');
        $(".setting-type-radio:has(#fitem_id_colorpallet)").addClass('d-none');
    });

    $('#quicksetup').on('click', SELECTOR.BRANDCOLORSCANCEL, function() {
        $(SELECTOR.BRANDCOLORS).addClass('d-none');

        $(SELECTOR.SETTINGTYPEHTML+":has(.color-pallet)").removeClass('d-none');
        $(SELECTOR.SETTINGTYPEHTML+":has(#id_pallet-apply)").removeClass('d-none');
        $(".setting-type-radio:has(#fitem_id_colorpallet)").removeClass('d-none');
    });

    $(SELECTOR.RESETPALLET).on('click', restorePalletColors);
}

/**
 * Load font on iframe.
 * @param {string} fontName Font name
 */
function loadDemoFont(fontName) {
    let id = fontName.replace(' ', '');
    id += '_js';
    if ($('body').find('#' + id).length != 0) {
        return;
    }
    let js = document.createElement('script');
    js.type = 'text/javascript';
    js.id = id;
    js.textContent = `require(['theme_remui/webfont'], function(webFont) {
        webFont.load({
            google: {
                families: ['${fontName}:100,200,300,400,500,600,700,800,900']
            }
        });
    });`;
    $('body').append(js);
}

export default {
    init,
    apply
};
