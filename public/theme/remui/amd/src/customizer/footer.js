/* eslint-disable no-console, no-unused-vars */
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
 * Theme customizer footer js
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Yogesh Shirsath
 */

import $ from "jquery";
import Templates from "core/templates";
import Utils from "theme_remui/customizer/utils";
import Ajax from "core/ajax";
/**
 * Selectors
 */
var SELECTOR = {
    BASE: "customizer-footer",
    BACKGROUNDCOLOR: '[name="footer-background-color"]',
    MAINBACKGROUNDCOLOR: '[name="main-footer-area-background-color"]',
    BOTTOMBACKGROUNDCOLOR: '[name="bottom-footer-area-background-color"]',
    TEXTCOLOR: '[name="footer-text-color"]',
    MAINAREATEXTCOLOR: '[name="main-footer-area-text-color"]',
    BOTTOMAREATEXTCOLOR: '[name="bottom-footer-area-text-color"]',
    BACKGROUNDIMG: '[name="backgroundimgurl"]',
    BACKGROUNDIMGPOS: '[name="backgroundimg-position"]',
    BACKGROUNDIMGREPEAT: '[name="backgroundimg-repeat"]',
    BACKGROUNDIMGSIZE: '[name="backgroundimg-size"]',
    BACKGROUNDIMGOPACITY: '[name="backgroundimg-opacity"]',
    LINKTEXT: '[name="footer-link-text"]',
    LINKHOVERTEXT: '[name="footer-link-hover-text"]',
    COLUMN: "footercolumn",
    COLUMNSIZE: "footercolumnsize",
    COLUMNSHEADING: "#heading_footer-advance-column",
    MENULIST: ".footer-menu-list",
    SHOWLOGO: '[name="footershowlogo"]',
    SHOWFOOTERWIDGETLOGO: '[name="showfooterwidgetlogo"]',
    FOOTERWIDGETLOGO: '[name="footerwidgetlogo"]',
    SHOWEMAILNEWLETTER: '[name="toggle_email_subscribe_settings"]',
    TERMSANDCONDITIONSSHOW: '[name="footertermsandconditionsshow"]',
    TERMSANDCONDITIONS: '[name="footertermsandconditions"]',
    PRIVACYPOLICYSHOW: '[name="footerprivacypolicyshow"]',
    PRIVACYPOLICY: '[name="footerprivacypolicy"]',
    COPYRIGHTSHOW: '[name="footercopyrightsshow"]',
    COPYRIGHT: '[name="footercopyrights"]',
    SETTINGITEM: ".setting-item",
    DNONE: "d-none",
    DIVIDERCOLOR: '[name="footer-divider-color"]',
    ICONDEFAULTCOLOR: '[name="footer-icon-color"]',
    ICONDEFAULTBGCOLOR: '[name="footer-icon-bg-color"]',
    ICONHOVERCOLOR: '[name="footer-icon-hover-color"]',
    FOOTERDESIGNSELECTOR: 'input[name="footer-design-selector"]',
    FOOTERFONTFAMILY: '[name="footerfontfamily"]',
    FOOTERFONTWEIGHT: '[name="footerfontweight"]',
    FOOTERTEXTTRANSFORM: '[name="footerfonttext-transform"]',
    FOOTERFONTSIZE: '[name="footerfontsize"]',
    FOOTERFONTLINEHEIGHT: '[name="footerfontlineheight"]',
    FOOTERFONTLTRSPACE: '[name="footerfontltrspace"]',
    FOOTERCOLUMNTITLEFONTFAMILY: '[name="footer-columntitle-fontfamily"]',
    FOOTERCOLUMMTITLEFONTSIZE: '[name="footer-columntitle-fontsize"]',
    FOOTERCOLUMNTITLEFONTWEIGHT: '[name="footer-columntitle-fontweight"]',
    FOOTERCOLUMMTITLETEXTTRANSFORM: '[name="footer-columntitle-textransform"]',
    FOOTERCOLUMMTITLELINEHEIGHT: '[name="footer-columntitle-lineheight"]',
    FOOTERCOLUMMTITLELTRSPACE: '[name="footer-columntitle-ltrspace"]',
    FOOTERCOLUMMTITLECOLOR: '[name="footer-columntitle-color"]',
    USEHEADERLOGO: '[name="useheaderlogo"]',
    SECONDARYFOOTERLOGO: '[name="secondaryfooterlogo"]',
    SECONDARYFOOTERLOGODARKMODE: '[name="secondaryfooterlogodarkmode"]',
    FOOTERLOGOCOLOR: '[name="footer-logo-color"]',
    FOOTERMAINSECTIONWRAPPER: '.footer-mainsection-wrapper',
    POWEREDBY: '[name="poweredbyedwiser"]',
    PRIVACYPOLICYNEWTAB: '[name=privacypolicynewtab]',
    TERMSANDCONDITIONSNEWTAB: '[name=termsandconditionewtab]',

    SOCIALICONS: `
        [name="facebooksetting"],
        [name="twittersetting"],
        [name="linkedinsetting"],
        [name="youtubesetting"],
        [name="instagramsetting"],
        [name="pinterestsetting"],
        [name="quorasetting"],
        [name="whatsappsetting"],
        [name="telegramsetting"]
    `,
    // Header selectors.
    HEADER: {
        LOGOORSITENAME: '[name="logoorsitename"]',
        LOGO: '[name="logo"]',
        LOGOMINI: '[name="logomini"]',
        ICON: '[name="siteicon"]',
        DARKMODELOGO: '[name="darkmodelogo"]',
        DARKMODELOGOMINI: '[name="darkmodelogomini"]',
    }
};

var CONSTANTS = {
    NIGHTEYESTATE: 'nighteyewState',
    CURRNIGHTEYESTATE: 'currnighteyewState'
};
/**
 * Social icon details.
 */
let socialList = {
    'facebook': {
        'class': "social-facebook",
        'icon': "icon edw-icon edw-icon-Facebook",
        'title': M.util.get_string('follometext', 'theme_remui', 'facebook')
    },
    'twitter': {
        'class': "social-twitter",
        'icon': "icon edw-icon edw-icon-Twitter",
        'title': M.util.get_string('follometext', 'theme_remui', 'twitter')

    },
    'linkedin': {
        'class': "social-linkedin",
        'icon': "icon edw-icon edw-icon-Linkedin",
        'title': M.util.get_string('follometext', 'theme_remui', 'linkedin')
    },
    'youtube': {
        'class': "social-youtube",
        'icon': "icon fa fa-youtube",
        'title': M.util.get_string('follometext', 'theme_remui', 'youtube')
    },
    'instagram': {
        'class': "social-instagram",
        'icon': "icon fa fa-instagram",
        'title': M.util.get_string('follometext', 'theme_remui', 'instagram')
    },
    'pinterest': {
        'class': "social-pinterest",
        'icon': "icon fa fa-pinterest",
        'title': M.util.get_string('follometext', 'theme_remui', 'pinterest')
    },
    'quora': {
        'class': "social-quora",
        'icon': "icon fa fa-quora",
        'title': M.util.get_string('follometext', 'theme_remui', 'quore')
    },
    'whatsapp': {
        'class': "social-whatsapp",
        'icon': "icon fa fa-whatsapp",
        'title': M.util.get_string('follometext', 'theme_remui', 'whatsapp')
    },
    'telegram': {
        'class': "social-telegram",
        'icon': "icon fa fa-telegram",
        'title': M.util.get_string('follometext', 'theme_remui', 'telegram')
    }
};

/**
 * Resize class for widget width.
 * @param {Event}    event    Resize start event
 */
function resize(event) {
    let drag = {};
    drag.iframeDocument = Utils.getDocument();
    drag.column = $(event.target.parentElement);
    drag.index = $(drag.column).index();
    drag.sibling = $(drag.column).next();
    drag.parent = $(drag.column).closest(`.resizer`);
    drag.widths = $(`[name="${SELECTOR.COLUMNSIZE}"]`).val().split(",");
    $(drag.column).closest(".resizer").addClass("resizing");

    if (event.type === "touchstart") {
        drag.startX = event.touches[0].clientX;
    } else {
        drag.startX = event.clientX;
    }

    drag.colStartWidth = $(drag.column).outerWidth();
    drag.sibStartWidth = $(drag.sibling).outerWidth();
    drag.parentWidth = $(drag.parent).outerWidth();

    drag.move = (evt) => {
        let clientX;
        if (evt.type === "touchmove") {
            clientX = evt.touches[0].clientX;
        } else {
            clientX = evt.clientX;
        }
        let newColWidth = drag.colStartWidth + clientX - drag.startX;
        let newSibWidth = drag.sibStartWidth - clientX + drag.startX;

        let percent = function(val, total) {
            return (val / total) * 100;
        };
        let colWidthPercent = parseFloat(
            percent(newColWidth, drag.parentWidth)
        ).toFixed(1);
        if (colWidthPercent < 15) {
            return;
        }
        let sibWidthPercent = parseFloat(
            percent(newSibWidth, drag.parentWidth)
        ).toFixed(1);
        if (sibWidthPercent < 15) {
            return;
        }

        // Main div width.
        $(drag.column)
            .css("width", `${colWidthPercent}%`)
            .find("label")
            .text(`${colWidthPercent}%`);
        $(drag.iframeDocument)
            .find(`#footer-column-${drag.index + 1}`)
            .css("flex", `0 0 ${colWidthPercent}%`);
        drag.widths[drag.index] = colWidthPercent;

        // Sibling div width.
        $(drag.sibling)
            .css("width", `${sibWidthPercent}%`)
            .find("label")
            .text(`${sibWidthPercent}%`);
        $(drag.iframeDocument)
            .find(`#footer-column-${drag.index + 2}`)
            .css("flex", `0 0 ${sibWidthPercent}%`);
        drag.widths[drag.index + 1] = sibWidthPercent;
    };

    drag.stop = () => {
        window.removeEventListener("mouseup", drag.stop);
        window.removeEventListener("touchend", drag.stop);
        window.removeEventListener("mousemove", drag.move);
        window.removeEventListener("touchmove", drag.move);
        $(`[name="${SELECTOR.COLUMNSIZE}"]`).val(drag.widths.join(","));
        $(drag.column).closest(".resizer").removeClass("resizing");
    };

    window.addEventListener("mouseup", drag.stop);
    window.addEventListener("touchend", drag.stop);
    window.addEventListener("mousemove", drag.move);
    window.addEventListener("touchmove", drag.move);
}

/**
 * Toggle footer primary is empty.
 */
function isFooterPrimaryVisible() {
    let type;
    let content;
    let hasSocial;
    let hasContent;
    let hasMenu;
    let widgetSocials;
    let visible = false;
    let emptySocial = true;
    let stripHtml = (html) => $(`<div>${html}</div>`).text().trim();
    let columns = $(`[name="${SELECTOR.COLUMN}"]`).val();
    let iframeDocument = Utils.getDocument();
    let socials = [];

    // Check if any social media link is visible.
    $(SELECTOR.SOCIALICONS).each(function() {
        if ($(this).val() != "") {
            emptySocial = false;
        }
        socials[$(this).attr("name").replace("setting", "")] = $(this).val();
    });
    for (let i = 1; i <= columns; i++) {
        type = $(`[name="${SELECTOR.COLUMN + i}type"]`).val();
        hasSocial = hasContent = hasMenu = false;
        switch (type) {
            case "social":
                if (emptySocial) {
                    break;
                }
                widgetSocials = $(`[name="${SELECTOR.COLUMN + i}social"]`).val();
                if (widgetSocials.length == 0) {
                    break;
                }
                // eslint-disable-next-line no-loop-func
                widgetSocials.forEach((social) => {
                    if (socials[social] != "") {
                        hasSocial = visible = true;
                    }
                });
                break;
            case "customhtml":
                content = $(`[name="${SELECTOR.COLUMN + i}customhtml"]`).val();
                if (stripHtml(content) !== "" || content.indexOf("img") !== -1) {
                    hasContent = visible = true;
                }
                break;
            case "menu":
                if ($(`[name="${SELECTOR.COLUMN + i}menu"]`).val() != "[]") {
                    hasMenu = visible = true;
                }
                break;
        }
        // $(iframeDocument).find(`#page-footer #footer-column-${i} .custom-html`).toggleClass('invisible', !hasContent);
        // $(iframeDocument).find(`#page-footer #footer-column-${i} .social-links`).toggleClass('invisible', !hasSocial);
        // $(iframeDocument).find(`#page-footer #footer-column-${i} .footer-menu`).toggleClass('invisible', !hasMenu);
    }
    $(iframeDocument)
        .find(`#page-footer .footer-primary`)
        .toggleClass("d-none", !visible);
}

/**
 * Toggle number of columns
 */
function toggleColumns() {
    // Check which column setting is active (footercolumn or footercolumn5)
    let columns;
    // if (!$(`[name="footercolumn5"]`).closest(SELECTOR.SETTINGITEM).hasClass(SELECTOR.DNONE)) {
    //     // footercolumn5 is visible (not hidden), use it
    //     columns = $(`[name="footercolumn5"]`).val();
    // } else {
    //     // footercolumn5 is hidden, use footercolumn
    //     columns = $(`[name="${SELECTOR.COLUMN}"]`).val();
    // }
    if(getActiveFooterDesign() == 'footerdesign2'){
        columns = $(`[name="footercolumn5"]`).val();
        $("#id_footercolumn5-range-value").text(columns);
    }else{
        columns = $(`[name="${SELECTOR.COLUMN}"]`).val();
        $("#id_footercolumn-range-value").text(columns);
    }

    let iframeDocument = Utils.getDocument();
    // if(getActiveFooterDesign() === 'footerdesign0') {
        let i = 1;
        for (; i <= columns; i++) {
            $(SELECTOR.COLUMNSHEADING + i).show();
            $(iframeDocument).find(`#footer-column-${i}`).removeClass(SELECTOR.DNONE);
        }
        for (; i <= 5; i++) {
            $(SELECTOR.COLUMNSHEADING + i).hide();
            $(iframeDocument).find(`#footer-column-${i}`).addClass(SELECTOR.DNONE);
        }
    // } else {
    //     let i = 1;
    //     for (; i <= columns; i++) {
    //         $(SELECTOR.COLUMNSHEADING + i).show();
    //         if($(`[name="${SELECTOR.COLUMN + i}type"]`).val() == 'customhtml') {
    //             $(iframeDocument).find(`#footer-column-${i} .custom-html`).removeClass(SELECTOR.DNONE);
    //         }
    //         else {
    //             $(iframeDocument).find(`#footer-column-${i} .footer-menu`).removeClass(SELECTOR.DNONE);
    //         }
    //     }
    //     for (; i <= 5; i++) {
    //         $(SELECTOR.COLUMNSHEADING + i).hide();
    //         if($(`[name="${SELECTOR.COLUMN + i}type"]`).val() == 'customhtml') {
    //             $(iframeDocument).find(`#footer-column-${i} .custom-html`).addClass(SELECTOR.DNONE);
    //         }
    //         else {
    //             $(iframeDocument).find(`#footer-column-${i} .footer-menu`).addClass(SELECTOR.DNONE);
    //         }
    //     }
    // }
}

/**
 * Toggle column type.
 * @param {Integer} index Footer column index
 */
function toggleType(index) {
    let type = $(`[name="${SELECTOR.COLUMN + index}type"]`).val();
    let showSocial = $(`[name="socialmediaiconcol${index}"]`).is(":checked");
    let iframeDocument = Utils.getDocument();

    // Toggle custom html.
    $(`[name="footercolumn${index}customhtml"]`)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, type != "customhtml");

    // Toggle menu.
    $(`[name="footercolumn${index}menu"]`)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, type == 'customhtml');

    // Toggle Social menu toggler.
    $(`[name="socialmediaiconcol${index}"]`)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, type == 'menu');

    // Toggle social icons.
    $('[name="footercolumn' + index + 'social"]')
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, type == "menu" || !showSocial);
    $('.footercolumn' + index + 'social-note')
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, type == "menu" || !showSocial);

    // Show social icon selection when type is customhtml.
    $(iframeDocument)
        .find(`#footer-column-${index} .custom-html`)
        .toggleClass(SELECTOR.DNONE, type != "customhtml");

    // Toggle Menu.
    $(iframeDocument)
        .find(`#footer-column-${index} .footer-menu`)
        .toggleClass(SELECTOR.DNONE, type != "menu");

    // Toggle column type.
    $(iframeDocument)
        .find(`#footer-column-${index}`)
        .removeClass("column-type-customhtml column-type-social column-type-menu")
        .addClass("column-type-" + type);
}

/**
 * Update title in iframe.
 * @param {Integer} index Footer column index
 */
function titleChange(index) {
    let title = $(`[name="${SELECTOR.COLUMN}${index}title"]`).val();
    $(Utils.getDocument())
        .find(`#footer-column-${index} .custom-html .ftr-column-title`)
        .text(title);
    $(Utils.getDocument())
        .find(`#footer-column-${index} .footer-menu .ftr-column-title`)
        .text(title);
}

/**
 * Update title in iframe.
 * @param {Integer} index Footer column index
 */
function contentChange(index) {
    let content = $(`[name="${SELECTOR.COLUMN}${index}customhtml"]`).val();
    $(Utils.getDocument())
        .find(`#footer-column-${index} .custom-html .section-html-content`)
        .html(content);
}

/**
 * Content change with specific content (for TinyMCE integration)
 * @param {Integer} index Footer column index
 * @param {String} content HTML content to set
 */
function contentChangeWithContent(index, content) {
    $(Utils.getDocument())
        .find(`#footer-column-${index} .custom-html .section-html-content`)
        .html(content);
}

/**
 * Apply footer colors.
 */
function footerColors() {
    let backgroundColor = $(SELECTOR.BACKGROUNDCOLOR).spectrum("get").toString();
    let mainbackgroundColor = $(SELECTOR.MAINBACKGROUNDCOLOR).spectrum("get").toString();
    let bottombackgroundColor = $(SELECTOR.BOTTOMBACKGROUNDCOLOR).spectrum("get").toString();
    let textColor = $(SELECTOR.TEXTCOLOR).spectrum("get").toString();
    let mainareatextColor = $(SELECTOR.MAINAREATEXTCOLOR).spectrum("get").toString();
    let bottomareatextColor = $(SELECTOR.BOTTOMAREATEXTCOLOR).spectrum("get").toString();
    let linkText = $(SELECTOR.LINKTEXT).spectrum("get").toString();
    let linkHoverText = $(SELECTOR.LINKHOVERTEXT).spectrum("get").toString();
    let dividerColor = $(SELECTOR.DIVIDERCOLOR).spectrum("get").toString();
    let icondefaultColor = $(SELECTOR.ICONDEFAULTCOLOR).spectrum("get").toString();
    let icondefaultBgColor = $(SELECTOR.ICONDEFAULTBGCOLOR).spectrum("get").toString();
    let iconhoverColor = $(SELECTOR.ICONHOVERCOLOR).spectrum("get").toString();
    let footercolumntitlecolor = $(SELECTOR.FOOTERCOLUMMTITLECOLOR).spectrum("get").toString();
    let footerlogocolor = $(SELECTOR.FOOTERLOGOCOLOR).spectrum("get").toString();

    let footerdesign = getActiveFooterDesign();
    if(footerdesign == 'footerdesign1' || footerdesign == 'footerdesign2') {
        backgroundColor = bottombackgroundColor;
        textColor = mainareatextColor;
    }

    let content = `
            #page-footer {
                background: ${backgroundColor} !important;
            }
            #page-footer:has(.section-footer-design-2) .footer-mainsection-wrapper,
            #page-footer:has(.section-footer-design-3) .footer-mainsection-wrapper {
                background: ${mainbackgroundColor} !important;
            }
            @media screen and (max-width: 767.5px)  {
                #page-footer:has(.section-footer-design-2) .footer-content-popover,
                #page-footer:has(.section-footer-design-3) .footer-content-popover {
                    background: ${mainbackgroundColor} !important;
                    color: ${mainareatextColor} !important;
                }

                #page-footer:has(.section-footer-design-4) .footer-content-popover {
                    background: ${backgroundColor} !important;
                    color: ${textColor} !important;
                }

                #page-footer:has(.section-footer-design-2) .footer-content-popover .footer-section a,
                #page-footer:has(.section-footer-design-3) .footer-content-popover .footer-section a {
                    color: ${mainareatextColor} !important;
                }

                #page-footer:has(.section-footer-design-3) .footer-content-popover .icon {
                    color: ${icondefaultColor} !important;
                }
            }
            #page-footer:has(.section-footer-design-2) .footer-secondarysection-wrapper,
            #page-footer:has(.section-footer-design-3) .footer-secondarysection-wrapper {
                background: ${bottombackgroundColor} !important;
            }
            #page-footer .h1,
            #page-footer .h2,
            #page-footer .h3,
            #page-footer .h4,
            #page-footer .h5,
            #page-footer .h6,
            #page-footer h1,
            #page-footer h2,
            #page-footer h3,
            #page-footer h4,
            #page-footer h5,
            #page-footer h6,
            #page-footer p,
            #page-footer .footer-content-debugging-wrapper,.section-html-content,[id $=reactive-debugpanel],
            .footer-secondarysection-wrapper p,
            #page-footer .subscribe-box input {
                color: ${textColor} !important;
            }
            #page-footer:has(.section-footer-design-2) .footer-mainsection-wrapper {
                color: ${mainareatextColor} !important;
            }
            #page-footer:has(.section-footer-design-2) .footer-secondarysection-wrapper p {
                color: ${bottomareatextColor} !important;
            }
            #page-footer:has(.section-footer-design-3) .footer-secondarysection-wrapper p {
                color: ${bottomareatextColor} !important;
            }
            #page-footer .footer-content-debugging-wrapper,[id $=reactive-debugpanel] {
                color: ${bottomareatextColor} !important;
            }
            #page-footer .footer-mainsection-wrapper a,
            #page-footer .footer-secondarysection-wrapper a,
            #page-footer .purgecaches a {
                color: ${linkText} !important;
            }
            #page-footer:has(.section-footer-design-2) .email-text::after {
                background-color: ${linkText} !important;
            }
            #page-footer:has(.section-footer-design-2) .email-text:hover::after {
                background-color: ${linkHoverText} !important;
            }
            #page-footer .footer-mainsection-wrapper a:hover,
            #page-footer .footer-secondarysection-wrapper a:hover,
            #page-footer .purgecaches a:hover {
                color: ${linkHoverText} !important;
            }
            #page-footer hr{
                border-color: ${dividerColor} !important;
            }
            #page-footer:has(.section-footer-design-4) .footer-mainsection-wrapper {
                border-bottom: 1px solid ${dividerColor} !important;
            }
            #page-footer:has(.section-footer-design-5) .footer-secondarysection-wrapper,
            #page-footer:has(.section-footer-design-6) .divider,
            #page-footer:has(.section-footer-design-7) .footer-secondarysection-wrapper {
                border-top: 1px solid ${dividerColor} !important;
            }
            #page-footer:has(.section-footer-design-5) .hr-vertical.first:not(:last-child) {
                border-right: 1px solid ${dividerColor} !important;
            }
            #page-footer:has(.section-footer-design-4) .footer-secondarysection-wrapper .social-links-wrapper .contentsocial.social-links a {
                border: 1px solid ${dividerColor} !important;
            }
            #page-footer .footer-mainsection-wrapper .edw-icon,
            #page-footer .footer-mainsection-wrapper i,
            #page-footer .footer-secondarysection-wrapper i {
                color: ${icondefaultColor} !important;
            }
            #page-footer:has(.section-footer-design-2) .social-links a,
            #page-footer:has(.section-footer-design-4) .social-links a {
                background-color: ${icondefaultBgColor} !important;
            }
            #page-footer:has(.section-footer-design-2) .social-links a:hover,
            #page-footer:has(.section-footer-design-4) .social-links a:hover {
                background-color: ${icondefaultBgColor} !important;
            }

            #page-footer .footer-mainsection-wrapper .edw-icon:hover,#page-footer .footer-mainsection-wrapper i:hover,
            #page-footer .footer-secondarysection-wrapper i:hover {
                color: ${iconhoverColor} !important;
            }

            #page-footer .footer-secondarysection-wrapper .iconsitename i:hover,
            #page-footer .footer-secondarysection-wrapper .icononly i:hover  {
                color: ${footerlogocolor} !important;
            }

            #page-footer:has(.section-footer-design-7) .section-html-content .edw-icon {
                color: ${textColor} !important;
            }

            #page-footer .ftr-column-title {
                color: ${footercolumntitlecolor} !important;
            }
            #page-footer .navbar-brand-logo,
            #page-footer .navbar-brand-logo i {
                color: ${footerlogocolor} !important;
            }
        `;
    Utils.putStyle("customizer-footer-colors", content);
}

/**
 * Apply footer colors.
 */
function footerFonts() {
    let fontfamily = $(SELECTOR.FOOTERFONTFAMILY).val();
    let fontweight = $(SELECTOR.FOOTERFONTWEIGHT).val();
    let texttransformvalue = $(SELECTOR.FOOTERTEXTTRANSFORM).val();
    let fontsize = $(SELECTOR.FOOTERFONTSIZE).val();
    if (fontsize !== '') {
        fontsize = `font-size: ${fontsize}rem !important;`;
    }
    let footerfontlineheight = $(SELECTOR.FOOTERFONTLINEHEIGHT).val();
    if (footerfontlineheight) {
        footerfontlineheight = `line-height: ${footerfontlineheight}rem !important;`;
    }
    let footerfontltrspace = $(SELECTOR.FOOTERFONTLTRSPACE).val();
    if (footerfontltrspace) {
        footerfontltrspace = `letter-spacing: ${footerfontltrspace}px !important;`;
    }

    let columntitlefontfamily = $(SELECTOR.FOOTERCOLUMNTITLEFONTFAMILY).val();
    let columntitlefontweight = $(SELECTOR.FOOTERCOLUMNTITLEFONTWEIGHT).val();
    let columntitletexttransformvalue = $(SELECTOR.FOOTERCOLUMMTITLETEXTTRANSFORM).val();
    let columntitlefontsize = $(SELECTOR.FOOTERCOLUMMTITLEFONTSIZE).val();
    if (columntitlefontsize) {
        columntitlefontsize = `font-size:${columntitlefontsize}rem !important;`;
    }
    let columntitlefontlineheight = $(SELECTOR.FOOTERCOLUMMTITLELINEHEIGHT).val();
    if (columntitlefontlineheight) {
        columntitlefontlineheight = `line-height: ${columntitlefontlineheight}rem !important;`;
    }
    let columntitlefontltrspace = $(SELECTOR.FOOTERCOLUMMTITLELTRSPACE).val();
    if (columntitlefontltrspace) {
        columntitlefontltrspace = `letter-spacing: ${columntitlefontltrspace}px !important;`;
    }

    var fontcontent = "";
    if (fontfamily.toLowerCase() == "inherit") {
        fontcontent = "inherit";
    } else {
        fontcontent = fontfamily;
        Utils.loadFont(fontcontent);
    }

    var fontTitleContent = "";
    if (columntitlefontfamily.toLowerCase() == "inherit") {
        fontTitleContent = "inherit";
    } else {
        fontTitleContent = columntitlefontfamily;
        Utils.loadFont(fontTitleContent);
    }

    let content = `
            #page-footer,
            #page-footer .h1,
            #page-footer .h2,
            #page-footer .h3,
            #page-footer .h4,
            #page-footer .h5,
            #page-footer .h6,
            #page-footer h1,
            #page-footer h2,
            #page-footer h3,
            #page-footer h4,
            #page-footer h5,
            #page-footer .h-regular-6,
            #page-footer p,
            #page-footer a,
            #page-footer .footer-content-debugging-wrapper {
                ${fontsize}
                font-weight: ${fontweight} !important;
                ${footerfontlineheight}
                ${footerfontltrspace}

            }
            #page-footer {
                font-family: ${fontcontent} !important;
                text-transform:${texttransformvalue} !important;
            }
            .ftr-column-title {
                font-family: ${fontTitleContent} !important;
                ${columntitlefontsize}
                font-weight: ${columntitlefontweight} !important;
                ${columntitlefontlineheight}
                text-transform: ${columntitletexttransformvalue} !important;
                ${columntitlefontltrspace}
            }
        `;
    Utils.putStyle("customizer-footer-fonts", content);
}

/**
 * Observe column size change.
 */
function columnSizeChange() {
    let widths = $(`[name="${SELECTOR.COLUMNSIZE}"]`).val().split(",");
    let iframeDocument = Utils.getDocument();
    let content = '';
    widths.forEach((width, index) => {
        $(iframeDocument)
            .find(`#footer-column-${index + 1}`)
            .css("flex", `0 0 ${width}%`);
    });
    content += `
            [class*="section-footer-design-"] .footer-section-wrapper.d-none {
                display: block !important;
            }
            [class*="section-footer-design-"] .footer-section-wrapper.d-none > *{
                display: none !important;
            }
            `;
    Utils.putStyle("customizer-footer-column-size", content);
}


function applyBackgroundImgURL(defaultImg = true){
    let backgroundimg = $(SELECTOR.BACKGROUNDIMG).val();
    if(!defaultImg){
        backgroundimg = '';
        $(SELECTOR.BACKGROUNDIMG).val(123456789);
    }

    // If background image is empty, set default based on footer design
    // Dirty code to fix the db related issue with the background images
    if(backgroundimg == ''){
        let response = '';
        if(getActiveFooterDesign() === 'footerdesign1') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg1.svg";
        }
        else if(getActiveFooterDesign() === 'footerdesign2') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg2.svg";
        }
        else if(getActiveFooterDesign() === 'footerdesign3') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg3.svg";
        }
        else if(getActiveFooterDesign() === 'footerdesign4') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg4.svg";
        }
        else if(getActiveFooterDesign() === 'footerdesign6') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg6.svg";
        }

        let content = `
        #page-footer:not(:has(.section-footer-design-1)) .footer-mainsection-wrapper:before {
            background-image: url('${response}') !important;
        }
        `;
        Utils.putStyle("customizer-footer-backgroundimg", content);
    } else {
        // Only call web service if background image is not empty
        Utils.getFileURL(backgroundimg).done(function(response) {
            if (response == "") {
                // Fallback to default if web service returns empty
                if(getActiveFooterDesign() === 'footerdesign1') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg1.svg";
                }
                else if(getActiveFooterDesign() === 'footerdesign2') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg2.svg";
                }
                else if(getActiveFooterDesign() === 'footerdesign3') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg3.svg";
                }
                else if(getActiveFooterDesign() === 'footerdesign4') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg4.svg";
                }
                else if(getActiveFooterDesign() === 'footerdesign6') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/FooterDesignBg6.svg";
                }
            }

            let content = `
            #page-footer:not(:has(.section-footer-design-1)) .footer-mainsection-wrapper:before {
                background-image: url('${response}') !important;
            }
            `;
            Utils.putStyle("customizer-footer-backgroundimg", content);
        });
    }
}

function footerBackgroundImg() {
    // let backgroundimg = $(SELECTOR.BACKGROUNDIMG).val();
    let backgroundimgposition = $(SELECTOR.BACKGROUNDIMGPOS).val();
    let backgroundimgrepeat = $(SELECTOR.BACKGROUNDIMGREPEAT).val();
    let backgroundimgsize = $(SELECTOR.BACKGROUNDIMGSIZE).val();
    let backgroundimgopacity = $(SELECTOR.BACKGROUNDIMGOPACITY).val();

    let content = `
    #page-footer:not(:has(.section-footer-design-1)) .footer-mainsection-wrapper:before {
        background-position: ${backgroundimgposition} !important;
        background-repeat: ${backgroundimgrepeat} !important;
        background-size:  ${backgroundimgsize} !important;
        opacity: ${backgroundimgopacity} !important;
    }
    `;
    Utils.putStyle("customizer-footer-backgroundimgproperties", content);
}

/**
 * Generate column size elements.
 */
function generateColumnSize() {
    // Check which column setting is active (footercolumn or footercolumn5)
    let numberOfColumns;
    if(getActiveFooterDesign() == 'footerdesign2'){
        numberOfColumns = $(`[name="footercolumn5"]`).val();
    }else{
        numberOfColumns = $(`[name="${SELECTOR.COLUMN}"]`).val();
    }

    let widths = $(`[name="${SELECTOR.COLUMNSIZE}"]`)
        .val()
        .split(",")
        .slice(0, numberOfColumns);
    let parent = $(`[name="${SELECTOR.COLUMNSIZE}"]`).closest(".felement");
    toggleColumns();
    Templates.render("theme_remui/customizer/footer_widget_size", {
        widget: widths,
    }).done(function(html, js) {
        parent.find(".resizer-wrapper").remove();
        Templates.appendNodeContents(parent, html, js);
    });
}

/**
 * Social media link change
 * @param {String} name name of social setting
 * @param {String} link link of social setting
 */
function socialMediaLinks(name, link) {
    // Skip footer-secondary social media inputs (they are handled by updateSocialMediaPreview)
    if (name.includes('setting') && !name.match(/\d+$/)) {
        // This is a footer-secondary input (like "facebooksetting"), skip it
        return;
    }

    name = name.replace("setting", "");
    name = "social-" + name;
    let iframeDocument = Utils.getDocument();
    $(iframeDocument)
        .find(`#page-footer .social-links .${name}`)
        .attr("href", link)
        .toggleClass(SELECTOR.DNONE, link == "");
}

/**
 * Toggle social icons based on selections.
 * @param {Integer} index Footer column index
 */
function socialSelectionChanges(index) {
    let selection = $(`[name="${SELECTOR.COLUMN}${index}social"]`).val();
    let iframeDocument = Utils.getDocument();
    let link, additionalClass;
    $(iframeDocument)
        .find(`#footer-column-${index} .social-links a`).remove();
    selection.forEach((name) => {
        link = $(`[name="${name}setting"]`).val();
        additionalClass = link == '' ? SELECTOR.DNONE : '';
        $(iframeDocument)
            .find(`#footer-column-${index} .social-links`)
            .append(`<a href="${link}" class="${socialList[name].class} ${additionalClass}" text-decoration-none" title="${socialList[name].title}"><i class="${socialList[name].icon}"></i></a>`);
    });
}

/**
 * Update changed menu to column
 * @param {Integer} index Footer column index
 */
function menuChange(index) {
    let menu = $(`[name="${SELECTOR.COLUMN}${index}menu"]`).val();
    let iframeDocument = Utils.getDocument();
    try {
        menu = JSON.parse(menu);
    } catch (exception) {
        menu = [];
    }
    $(iframeDocument)
        .find(`#footer-column-${index} ${SELECTOR.MENULIST}`)
        .html("");
    menu.forEach((menuitem) => {
        $(iframeDocument)
            .find(`#footer-column-${index} ${SELECTOR.MENULIST}`)
            .append(
                `<a target="_blank" href="${menuitem.address}">${menuitem.text}</a>`
            );
    });
}

/**
 * Update menu orientation.
 * @param {Integer} index Footer column index
 */
function menuOrientationChange(index) {
    let orientation = $(
        `[name="${SELECTOR.COLUMN}${index}menuorientation"]`
    ).val();
    let iframeDocument = Utils.getDocument();
    $(iframeDocument)
        .find(`#footer-column-${index} .footer-menu`)
        .removeClass("menu-vertical menu-horizontal")
        .addClass("menu-" + orientation);
}

/**
 * Use different logo for footer.
 */
function useDifferentLogo() {
    if (!$(SELECTOR.SHOWLOGO).is(":checked")) {
        return;
    }
    let iframeDocument = Utils.getDocument();
    let useHeader = $(SELECTOR.USEHEADERLOGO).is(":checked");
    $(SELECTOR.SECONDARYFOOTERLOGO)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, useHeader);
    $(SELECTOR.SECONDARYFOOTERLOGODARKMODE)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, useHeader);

    $(SELECTOR.FOOTERLOGOCOLOR)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !useHeader);

    let itemid;
    if (!useHeader) {
        itemid = $(SELECTOR.SECONDARYFOOTERLOGO).val();

        if(localStorage.getItem(CONSTANTS.NIGHTEYESTATE) == 1) {
            itemid = $(SELECTOR.SECONDARYFOOTERLOGODARKMODE).val();
        }

        Utils.getFileURL(itemid).done(function(response) {
            if (response == "") {
                response = M.cfg.wwwroot + "/theme/remui/pix/logo.png";
            }
            $(iframeDocument)
                .find("#page-footer .footer-diff-logo .navbar-brand-logo")
                .attr("src", response);
        });
    } else {
        switch ($(SELECTOR.HEADER.LOGOORSITENAME).val()) {
            case 'logo':
                itemid = $(SELECTOR.HEADER.LOGO).val();
                if(localStorage.getItem(CONSTANTS.NIGHTEYESTATE) == 1) {
                    itemid = $(SELECTOR.HEADER.DARKMODELOGO).val();
                }

                Utils.getFileURL(itemid).done(function(response) {
                    if (response == "") {
                        response = M.cfg.wwwroot + "/theme/remui/pix/logo.png";
                    }
                    $(iframeDocument)
                        .find("#page-footer .navbar-logo-footer-wrapper .navbar-brand")
                        .html(`<img src="${response}" class="navbar-brand-logo logo"></img>`);
                });
                break;
            case 'logomini':
                itemid = $(SELECTOR.HEADER.LOGOMINI).val();
                if(localStorage.getItem(CONSTANTS.NIGHTEYESTATE) == 1) {
                    itemid = $(SELECTOR.HEADER.DARKMODELOGOMINI).val();
                }
                Utils.getFileURL(itemid).done(function(response) {
                    if (response == "") {
                        response = M.cfg.wwwroot + "/theme/remui/pix/logomini.png";
                    }
                    $(iframeDocument)
                        .find("#page-footer .navbar-logo-footer-wrapper .navbar-brand")
                        .html(`<img src="${response}" class="navbar-brand-logo logomini"></img>`);
                });
                break;
            case 'icononly':
                $(iframeDocument)
                .find("#page-footer .navbar-logo-footer-wrapper .navbar-brand")
                .html(`<span class="navbar-brand-logo icononly"><i class="fa fa-${$(SELECTOR.HEADER.ICON).val()}"></i></span>`);
                break;
            case 'iconsitename':
                $(iframeDocument)
                .find("#page-footer .navbar-logo-footer-wrapper .navbar-brand")
                .html(`<span class="navbar-brand-logo font-weight-bolder iconsitename">
                    <i class="fa fa-${$(SELECTOR.HEADER.ICON).val()}"></i>
                    &nbsp;
                    ${$('#customizer').data('sitename')}
                </span>`);
                break;
        }
    }
    $(iframeDocument)
        .find(".footer-diff-logo")
        .toggleClass(SELECTOR.DNONE, useHeader);
    $(iframeDocument)
        .find(".navbar-logo-footer-wrapper")
        .toggleClass(SELECTOR.DNONE, !useHeader);
}

/**
 * Show logo in secondary footer.
 */
function showLogo() {
    let iframeDocument = Utils.getDocument();
    let show = $(SELECTOR.SHOWLOGO).is(":checked");
    $(iframeDocument).find(".secondary-footer-logo").toggleClass(SELECTOR.DNONE, !show);
    $(`${SELECTOR.SECONDARYFOOTERLOGO}, ${SELECTOR.USEHEADERLOGO}, ${SELECTOR.FOOTERLOGOCOLOR}, ${SELECTOR.SECONDARYFOOTERLOGODARKMODE}`)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);
    if (show) {
        useDifferentLogo();
        return;
    }
}
function displayemailnewsletter() {
    let iframeDocument = Utils.getDocument();

    // Check the main toggle (only 1 input/btn in the footer)

    let show = false;
    for (let i = 0; i <= 5; i++) { // or i=1; i<=4; i++ depending on your IDs
        if ($(`[name="toggle_email_subscribe_settings${i}"]`).is(":checked")) {
            show = true;
            $(`#fitem_id_subscribetargetlink${i}`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);
            $(`#fitem_id_emailinputbordercolor${i}`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
            $(`#fitem_id_focusedemailinputoutlinecolor${i}`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebuttontextcolor${i}`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebuttontexthovercolor${i}`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebtnbgcolor${i}`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebtnbghovercolor${i}`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
        } else {
            $(`#fitem_id_subscribetargetlink${i}`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
            $(`#fitem_id_emailinputbordercolor${i}`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
            $(`#fitem_id_focusedemailinputoutlinecolor${i}`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebuttontextcolor${i}`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebuttontexthovercolor${i}`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebtnbgcolor${i}`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
            $(`#fitem_id_subscribebtnbghovercolor${i}`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
        }
    }

    // Apply toggle to global footer elements once
    // $(iframeDocument)
    //     .find("#page-footer .subscribe-box input")
    //     .toggleClass(SELECTOR.DNONE, !show)
    //     .toggleClass("d-flex", show);

    // $(iframeDocument)
    //     .find("#page-footer .subscribe-box .subscribe-btn")
    //     .toggleClass(SELECTOR.DNONE, !show)
    //     .toggleClass("d-flex", show);


    // let show = $(`[name="toggle_email_subscribe_settings0"]`).is(":checked");
    // $(iframeDocument)
    //     .find("#page-footer .subscribe-box input")
    //     .toggleClass(SELECTOR.DNONE, !show)
    //     .toggleClass("d-block", show);

    // $(iframeDocument)
    //     .find("#page-footer .subscribe-box .subscribe-btn")
    //     .toggleClass(SELECTOR.DNONE, !show)
    //     .toggleClass("d-block", show);
    // if(show) {
    //     $(`#fitem_id_subscribetargetlink0`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);
    //     $(`#fitem_id_emailinputbordercolor0`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    //     $(`#fitem_id_focusedemailinputoutlinecolor0`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebuttontextcolor0`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebuttontexthovercolor0`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebtnbgcolor0`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebtnbghovercolor0`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    // }else {

    //     $(`#fitem_id_subscribetargetlink0`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    //     $(`#fitem_id_emailinputbordercolor0`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    //     $(`#fitem_id_focusedemailinputoutlinecolor0`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebuttontextcolor0`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebuttontexthovercolor0`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebtnbgcolor0`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    //     $(`#fitem_id_subscribebtnbghovercolor0`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    // }
}

/**
 * Show terms and conditions link in the footer.
 */
function termsAndConditionsShow() {
    let iframeDocument = Utils.getDocument();
    let show = $(SELECTOR.TERMSANDCONDITIONSSHOW).is(":checked");
    $(iframeDocument)
        .find(".footer-terms-and-conditions")
        .toggleClass(SELECTOR.DNONE, !show)
        .toggleClass("d-block", show);
    $(SELECTOR.TERMSANDCONDITIONS)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);
    $(SELECTOR.TERMSANDCONDITIONSNEWTAB)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);


}

/**
 * Handler terms and conditions.
 */
function termsAndConditions() {
    let iframeDocument = Utils.getDocument();
    let termsAndConditions = $(SELECTOR.TERMSANDCONDITIONS).val();
    $(iframeDocument)
        .find(".footer-terms-and-conditions")
        .attr("href", termsAndConditions);
}

/**
 * Show privacy policy link in the footer.
 */
function privacyPolicyShow() {
    let iframeDocument = Utils.getDocument();
    let show = $(SELECTOR.PRIVACYPOLICYSHOW).is(":checked");
    $(iframeDocument)
        .find(".footer-privacy-policy")
        .toggleClass(SELECTOR.DNONE, !show)
        .toggleClass("d-block", show);
    $(SELECTOR.PRIVACYPOLICY)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);
    $(SELECTOR.PRIVACYPOLICYNEWTAB)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);
}

function footerWidgetLogoShow() {
    let iframeDocument = Utils.getDocument();
    let show = $(SELECTOR.SHOWFOOTERWIDGETLOGO).is(":checked");
    $(iframeDocument)
        .find("#fitem_id_footerwidgetlogo")
        .toggleClass(SELECTOR.DNONE, !show)
        .toggleClass("d-block", show);
    $(SELECTOR.FOOTERWIDGETLOGO)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);
    $(iframeDocument)
        .find("#footer-column-1 .footerwidgetlogo")
        .toggleClass(SELECTOR.DNONE, !show)
        .toggleClass("d-block", show);
}
/**
 * Handle privacy policy link.
 */
function privacyPolicy() {
    let iframeDocument = Utils.getDocument();
    let privacyPolicy = $(SELECTOR.PRIVACYPOLICY).val();
    $(iframeDocument).find(".footer-privacy-policy").attr("href", privacyPolicy);
}

/**
 * Show copyright in the footer.
 */
function copyrightShow() {
    let iframeDocument = Utils.getDocument();
    let show = $(SELECTOR.COPYRIGHTSHOW).is(":checked");
    $(iframeDocument)
        .find(".secondary-footer-copyright")
        .toggleClass(SELECTOR.DNONE, !show);
    $(SELECTOR.COPYRIGHT)
        .closest(SELECTOR.SETTINGITEM)
        .toggleClass(SELECTOR.DNONE, !show);
}
/**
 * Show poweredby in the footer.
 */
function togglePoweredBy() {

    const coreHTML = `
        ${M.util.get_string('poweredby', 'theme_remui')} <a href="https://moodle.com">Moodle</a>
    `;

    const edwHTML = `
        ${M.util.get_string('poweredby', 'theme_remui')}
        <a href="https://edwiser.org/remui/" rel="nofollow" target="_blank">
            Edwiser RemUI
        </a>
    `;

    let iframeDocument = Utils.getDocument();

    if ($(SELECTOR.POWEREDBY).is(":checked")) {
        $(iframeDocument).find(".footer-poweredby").empty().append(edwHTML);
    } else {
        $(iframeDocument).find(".footer-poweredby").empty().append(coreHTML);
    }
}
/**
 * Handler copyright content.
 */
function copyright() {
    let iframeDocument = Utils.getDocument();
    let copyright = $(SELECTOR.COPYRIGHT)
        .val()
        .replaceAll(
            "[site]",
            $(iframeDocument).find(".secondary-footer-copyright").data("site")
        )
        .replaceAll("[year]", new Date().getFullYear());
    // $(iframeDocument).find(".secondary-footer-copyright").html('<p class=" mb-0">' + copyright + '</p>');
    $(iframeDocument).find(".secondary-footer-copyright .copyright-text").text(copyright);
}

/**
 * Apply settings.
 */
function apply() {
    footerColors();
    footerFonts();
    generateColumnSize();
    columnSizeChange();
    isFooterPrimaryVisible();
    showLogo();
    displayemailnewsletter();
    termsAndConditionsShow();
    termsAndConditions();
    privacyPolicyShow();
    privacyPolicy();
    copyrightShow();
    copyright();
    footerWidgetLogoShow();

    for (let i = 1; i <= 5; i++) {
        titleChange(i);
        // Use TinyMCE content if available, otherwise fallback to textarea
        const editorId = `id_footercolumn${i}customhtml`;
        const $editorElement = $(`#${editorId}`);

        if ($editorElement.siblings('.tox-tinymce').length > 0) {
            // TinyMCE editor is present, get content and use contentChangeWithContent
            console.log("TinyMCE editor is present");
            const content = getTinyMCEContent(editorId);
            contentChangeWithContent(i, content);
        } else {
            // No TinyMCE editor, use regular contentChange
            console.log("No TinyMCE editor, use regular contentChange");
            contentChange(i);
        }
        toggleType(i);
        menuChange(i);
        // socialSelectionChanges(i);
    }
    // Initialize social media settings visibility on page load
    initializeSocialSettingsVisibility();

    // Add event listeners for social media input fields
    initializeSocialMediaInputListeners();
    // Add event listeners for email subscribe settings
    initializeEmailSubscribeListeners();

    // Initialize email subscribe settings visibility on page load
    initializeEmailSubscribeSettingsVisibility();
}

/**
 * Initialize TinyMCE event listeners for footer column editors
 */
function initializeTinyMCEEvents() {
    // Wait for TinyMCE to be ready
    if (typeof window.tinymce !== 'undefined') {
        // Listen for when TinyMCE editors are added
        window.tinymce.on('AddEditor', function(e) {
            const editorId = e.editor.id;

            // Check if this is a footer column editor
            if (editorId.includes('footercolumn') && editorId.includes('customhtml')) {
                // Extract column number from editor ID
                const columnMatch = editorId.match(/footercolumn(\d+)customhtml/);
                if (columnMatch) {
                    const columnNumber = columnMatch[1];

                    // Add event listeners for content changes
                    e.editor.on('input change keyup', function() {
                        updateFooterColumnContent(columnNumber, e.editor.getContent());
                    });

                    // Also listen for when editor is ready
                    e.editor.on('init', function() {
                        // Initial content load
                        updateFooterColumnContent(columnNumber, e.editor.getContent());
                    });
                }
            }
        });
    } else {
        // Retry if TinyMCE not ready yet
        setTimeout(initializeTinyMCEEvents, 100);
    }
}

/**
 * Update footer preview with TinyMCE content
 * @param {String} columnNumber Footer column number
 * @param {String} content HTML content to set
 */
function updateFooterColumnContent(columnNumber, content) {
    const iframeDocument = Utils.getDocument();
    const selector = `#footer-column-${columnNumber} .custom-html .section-html-content`;

    // Update the preview with new content
    $(iframeDocument).find(selector).html(content);

    // Trigger any other updates that depend on content
    updateSocialMediaPreview(columnNumber);
    if (columnNumber === '1') {
        useFooterWidgetLogo(); // If it's column 1
    }
}

/**
 * Get TinyMCE content safely with fallback
 * @param {String} editorId TinyMCE editor ID
 * @return {String} Editor content or textarea fallback
 */
function getTinyMCEContent(editorId) {
    try {
        if (typeof window.tinymce !== 'undefined' && window.tinymce.get(editorId)) {
            return window.tinymce.get(editorId).getContent();
        }
    } catch (e) {
        console.warn('TinyMCE not available for', editorId);
    }

    // Fallback to textarea
    return $(`[name="${editorId.replace('id_', '')}"]`).val() || '';
}

/**
 * Initialize
 */
function init() {
    // Advance footer column size observe
    // $(`[name="${SELECTOR.COLUMNSIZE}"]`).closest('.felement')
    //     .append(`<label>${M.util.get_string('footercolumnsizenote', 'theme_remui')}</label>`);
    generateColumnSize();
    showLogo();
    displayemailnewsletter();
    $(`[name="${SELECTOR.COLUMNSIZE}"]`).hide();
    $(`[name="${SELECTOR.COLUMNSIZE}"]`).on("change", function() {
        let widths = $(`[name="${SELECTOR.COLUMNSIZE}"]`).val().split(",");
        let widget = $(`[name="${SELECTOR.COLUMN}"]`).val();

        // Validating wigets count.
        if (widget != widths.length) {
            $(`[name="${SELECTOR.COLUMN}"]`).val(widths.length);
        }
        generateColumnSize();
        columnSizeChange();
    });

    // Observer column size change using on drag and touch.
    $("body").on(
        "mousedown touchstart",
        `#fitem_id_${SELECTOR.COLUMNSIZE} .resizer .resize-x-handle`,
        resize
    );

    // Listen number of columns toggler.
    $(`[name="${SELECTOR.COLUMN}"]`).on("change", function() {
        let width = [];
        for (let i = 1; i <= $(this).val(); i++) {
            width.push((100 / $(this).val()).toFixed(0));
        }
        $(`[name="${SELECTOR.COLUMNSIZE}"]`).val(width.join(","));
        generateColumnSize();
        columnSizeChange();
        isFooterPrimaryVisible();
    });

    // Listen number of columns toggler for footercolumn5 (1-5 columns).
    $(`[name="footercolumn5"]`).on("change", function() {
        let width = [];
        for (let i = 1; i <= $(this).val(); i++) {
            width.push((100 / $(this).val()).toFixed(0));
        }
        $(`[name="${SELECTOR.COLUMNSIZE}"]`).val(width.join(","));
        generateColumnSize();
        columnSizeChange();
        isFooterPrimaryVisible();
    });

    // Listen footer colors.
    $(`
            ${SELECTOR.BACKGROUNDCOLOR},
            ${SELECTOR.MAINBACKGROUNDCOLOR},
            ${SELECTOR.BOTTOMBACKGROUNDCOLOR},
            ${SELECTOR.TEXTCOLOR},
            ${SELECTOR.MAINAREATEXTCOLOR},
            ${SELECTOR.BOTTOMAREATEXTCOLOR},
            ${SELECTOR.LINKTEXT},
            ${SELECTOR.LINKHOVERTEXT},
            ${SELECTOR.DIVIDERCOLOR},
            ${SELECTOR.ICONDEFAULTCOLOR},
            ${SELECTOR.ICONDEFAULTBGCOLOR},
            ${SELECTOR.ICONHOVERCOLOR},
            ${SELECTOR.FOOTERCOLUMMTITLECOLOR},
            ${SELECTOR.FOOTERLOGOCOLOR}
        `).on("color.changed", footerColors);

    $(`
        ${SELECTOR.BACKGROUNDCOLOR}
    `).on("color.changed", function() {
        // Get the current background color value
        let backgroundColor = $(this).spectrum("get").toString();

        // Trigger main area background color change
        $(SELECTOR.MAINBACKGROUNDCOLOR).spectrum('set', backgroundColor).trigger('color.changed');

        // Trigger bottom background color change
        $(SELECTOR.BOTTOMBACKGROUNDCOLOR).spectrum('set', backgroundColor).trigger('color.changed');
    });

    $(`
        ${SELECTOR.TEXTCOLOR}
    `).on("color.changed", function() {
        let textColor = $(this).spectrum("get").toString();

        // Trigger main area text color change
        $(SELECTOR.MAINAREATEXTCOLOR).spectrum('set', textColor).trigger('color.changed');

        // Trigger bottom area text color change
        $(SELECTOR.BOTTOMAREATEXTCOLOR).spectrum('set', textColor).trigger('color.changed');
    });

    // Listen footer font settings.
    $(`
            ${SELECTOR.FOOTERFONTFAMILY},
            ${SELECTOR.FOOTERFONTWEIGHT},
            ${SELECTOR.FOOTERTEXTTRANSFORM},
            ${SELECTOR.FOOTERCOLUMNTITLEFONTFAMILY},
            ${SELECTOR.FOOTERCOLUMNTITLEFONTWEIGHT},
            ${SELECTOR.FOOTERCOLUMMTITLETEXTTRANSFORM}
        `).on("change", footerFonts);

    // Listen footer font input settings.
    $(`
            ${SELECTOR.FOOTERFONTSIZE},
            ${SELECTOR.FOOTERFONTLTRSPACE},
            ${SELECTOR.FOOTERFONTLINEHEIGHT},
            ${SELECTOR.FOOTERCOLUMMTITLEFONTSIZE},
            ${SELECTOR.FOOTERCOLUMMTITLELINEHEIGHT},
            ${SELECTOR.FOOTERCOLUMMTITLELTRSPACE}
        `).on("input", footerFonts);

    $(`
        ${SELECTOR.BACKGROUNDIMGOPACITY},
        ${SELECTOR.BACKGROUNDIMGPOS},
        ${SELECTOR.BACKGROUNDIMGREPEAT},
        ${SELECTOR.BACKGROUNDIMGSIZE}
        `).on("change",footerBackgroundImg);

    $(SELECTOR.BACKGROUNDIMG).on("change", applyBackgroundImgURL);

    Utils.fileObserver(
        $(SELECTOR.BACKGROUNDIMG).siblings(".filemanager")[0],
        function() {
            $(SELECTOR.BACKGROUNDIMG).val(window.backgroundimgitemid);
            applyBackgroundImgURL();
        }
    );

    // Observe social media links (footer-primary only - exclude footer-secondary inputs).
    $(SELECTOR.SOCIALICONS).on("input", function() {
        let inputName = $(this).attr("name");
        // Skip footer-secondary inputs (they don't end with numbers and are handled by updateSocialMediaPreview)
        if (!inputName.match(/\d+$/)) {
            return; // Skip footer-secondary inputs
        }
        socialMediaLinks(inputName, $(this).val());
        isFooterPrimaryVisible();
    });

    // Listen column type.
    $(`[name*="${SELECTOR.COLUMN}"][name*="type"]`).on("change", function() {
        let index = $(this)
            .attr("name")
            .replace(SELECTOR.COLUMN, "")
            .replace("type", "");
        toggleType(index);
        isFooterPrimaryVisible();
    });

    // Listen title change.
    $(`[name*="${SELECTOR.COLUMN}"][name*="title"]`).on("input", function() {
        let index = $(this)
            .attr("name")
            .replace(SELECTOR.COLUMN, "")
            .replace("title", "");
        titleChange(index);
    });

    // Listen content change.
    $(`[name*="${SELECTOR.COLUMN}"][name*="customhtml"]`).on(
        "input",
        function() {
            let index = $(this)
                .attr("name")
                .replace(SELECTOR.COLUMN, "")
                .replace("customhtml", "");

            // Get content from TinyMCE if available, otherwise use textarea
            const editorId = `id_footercolumn${index}customhtml`;
            const content = getTinyMCEContent(editorId);

            // Update the content change function to use the retrieved content
            contentChangeWithContent(index, content);
            isFooterPrimaryVisible();
        }
    );

    // Listen content change. change for atto editor
    $(`[name*="${SELECTOR.COLUMN}"][name*="customhtml"]`).on(
        "change",
        function() {
            let index = $(this)
                .attr("name")
                .replace(SELECTOR.COLUMN, "")
                .replace("customhtml", "");
            contentChange(index);
            isFooterPrimaryVisible();
        }
    );

    // Listen menu change.
    $(`[name*="${SELECTOR.COLUMN}"][name*="menu"]:not([name*="orientation"])`).on(
        "change",
        function() {
            let index = $(this)
                .attr("name")
                .replace(SELECTOR.COLUMN, "")
                .replace("menu", "");
            menuChange(index);
            isFooterPrimaryVisible();
        }
    );

    // Listen menu orientation change.
    $(`[name*="${SELECTOR.COLUMN}"][name*="menuorientation"]`).on(
        "change",
        function() {
            let index = $(this)
                .attr("name")
                .replace(SELECTOR.COLUMN, "")
                .replace("menuorientation", "");
            menuOrientationChange(index);
        }
    );

    // Listen social selection change.
    $(`[name*="${SELECTOR.COLUMN}"][name*="social"]`).on("change", function() {
        let index = $(this)
            .attr("name")
            .replace(SELECTOR.COLUMN, "")
            .replace("social", "");
        // socialSelectionChanges(index);
        isFooterPrimaryVisible();
    });

    // Show/hide social media settings based on socialmediaiconcol toggle
    $(`[name*="socialmediaiconcol"]`).on("change", function() {
        let index = $(this)
            .attr("name")
            .replace(SELECTOR.COLUMN, "")
            .replace("socialmediaiconcol", "");
        let iframeDocument = Utils.getDocument();
        let show = $(this).is(":checked");
        // Show/hide all social media input fields for this column
        $(`[name*="setting${index}"]`).each(function() {
            let $socialSetting = $(this);
            if ($socialSetting.attr('name').includes('setting') &&
                $socialSetting.attr('name').endsWith(index.toString())) {
                $socialSetting.closest(SELECTOR.SETTINGITEM).toggleClass(SELECTOR.DNONE, !show);
            }
        });
        initializeSocialSettingsVisibility();

        // Show/hide social media note
        $(`.${SELECTOR.COLUMN}${index}social-note`)
            .closest(SELECTOR.SETTINGITEM)
            .toggleClass(SELECTOR.DNONE, !show);
        // Show/hide social icons in iframe preview
        $(iframeDocument)
            .find(`#footer-column-${index} .contentsocial`)
            .toggleClass(SELECTOR.DNONE, !show);

        // Show/hide social-links container in iframe preview
        $(iframeDocument)
            .find(`#footer-column-${index} .social-links`)
            .toggleClass(SELECTOR.DNONE, !show);
    });
    // Show/hide footer-secondary social media settings based on footersocialmediaicons toggle
    $(`[name="footersocialmediaicons"]`).on("change", function() {
        let show = $(this).is(":checked");

        // Show/hide all social media input fields in footer-secondary
        $(SELECTOR.SOCIALICONS).each(function() {
            let $socialSetting = $(this);
            $socialSetting.closest(SELECTOR.SETTINGITEM).toggleClass(SELECTOR.DNONE, !show);
        });

        // Update iframe preview visibility for footer-secondary
        let iframeDocument = Utils.getDocument();
        if (iframeDocument) {
            let socialLinksContainer = $(iframeDocument).find('.footer-secondarysection-wrapper .social-links');
            let socialLinksmainContainer = $(iframeDocument).find('.footer-secondarysection-wrapper .social-links-wrapper');
            if (socialLinksContainer.length > 0) {
                if (show) {
                    // Show social media container
                    socialLinksContainer.removeClass(SELECTOR.DNONE);
                    if(socialLinksmainContainer) {
                        socialLinksmainContainer.removeClass(SELECTOR.DNONE);
                    }
                    // Show social media in preview by calling updateSocialMediaPreview with existing values
                    let socialTypes = ['facebook', 'twitter', 'linkedin', 'youtube', 'instagram', 'pinterest', 'quora', 'whatsapp', 'telegram'];
                    socialTypes.forEach(socialType => {
                        let $socialInput = $(`[name="${socialType}setting"]`);
                        if ($socialInput.length > 0 && $socialInput.val()) {
                            updateSocialMediaPreview('secondary', socialType, $socialInput.val());
                        }
                    });
                } else {
                    // Hide social media container
                    socialLinksContainer.addClass(SELECTOR.DNONE);
                    if(socialLinksmainContainer) {
                        socialLinksmainContainer.addClass(SELECTOR.DNONE);
                    }
                }
            }
        }
    });

    // Secondary footer.
    // Show logo in the footer.
    $(SELECTOR.SHOWLOGO).on("change", showLogo);

    // Initialize email subscribe event listeners
    initializeEmailSubscribeListeners();
    // Show terms ans condition link in the footer.
    $(SELECTOR.TERMSANDCONDITIONSSHOW).on("change", termsAndConditionsShow);

    // Handle terms and condition link change.
    $(SELECTOR.TERMSANDCONDITIONS).on("input", termsAndConditions);

    // Show privacy policy link in the footer.
    $(SELECTOR.PRIVACYPOLICYSHOW).on("change", privacyPolicyShow);

    $(SELECTOR.SHOWFOOTERWIDGETLOGO).on("change", footerWidgetLogoShow);
    // Handle privacy policy link change.
    $(SELECTOR.PRIVACYPOLICY).on("input", privacyPolicy);

    // Show copyright in the footer.
    $(SELECTOR.COPYRIGHTSHOW).on("change", copyrightShow);

    // Handle copyright content change.
    $(SELECTOR.COPYRIGHT).closest(".felement")
        .append(M.util.get_string("footercopyrightstags", "theme_remui"));
    $(SELECTOR.COPYRIGHT).on("input", copyright);

    // Handle same logo from header toggle.
    $(SELECTOR.USEHEADERLOGO).on("change", useDifferentLogo);

    // Handle same logo from header toggle.
    $(SELECTOR.FOOTERWIDGETLOGO).on("change", useFooterWidgetLogo);
    // Footer icon image observer.
    Utils.fileObserver(
        $(SELECTOR.SECONDARYFOOTERLOGO).siblings(".filemanager")[0],
        useDifferentLogo
    );

    // Footer icon image observer.
    Utils.fileObserver(
        $(SELECTOR.FOOTERWIDGETLOGO).siblings(".filemanager")[0],
        function() {
            $(SELECTOR.FOOTERWIDGETLOGO).val(window.footerwidgetlogoitemid);
            useFooterWidgetLogo();
        }
    );

    window.footerSettingsDownloaded = true;
    $(SELECTOR.POWEREDBY).on('change', togglePoweredBy);

    let footerdesignselector = $(SELECTOR.FOOTERDESIGNSELECTOR+':checked');

    window.currentFooterDesign = footerdesignselector.data('flayout');

    $(SELECTOR.FOOTERDESIGNSELECTOR).on('input', () => {

        // Reset download flag when footer design changes
        window.footerSettingsDownloaded = false;

        const footerdesignselector = $(SELECTOR.FOOTERDESIGNSELECTOR+':checked');

        if (footerdesignselector.length) {
            const selectedDesign = footerdesignselector.data('flayout');
            console.log("Current:", window.currentFooterDesign, "Selected:", selectedDesign);

            // Check if this is the same design that was previously applied
            if (window.currentFooterDesign === selectedDesign) {
                handleDesignSpecificSettings(selectedDesign);
                if (window.originalFooterContent) {
                    let iframeDocument = Utils.getDocument();
                    footerdesignloader(true, iframeDocument);
                    setTimeout(() => {
                        restoreUserCustomData()
                            .then(() => {
                                footerdesignloader(false, iframeDocument);
                            })
                            .catch(err => {
                                footerdesignloader(false, iframeDocument);
                                console.error("Failed restoring footer:", err);
                            });
                    }, 0);
                }

                applyBackgroundImgURL();
                useFooterWidgetLogo();
            } else {
                console.log("New design selected - fetching from server");
                InitiateNewFooterDesign(selectedDesign);
            }
        } else {
            console.log("No footer design selected");
        }
    });

    $("[name='top-area-header-text']").on("input", () => {
        let iframeDocument = Utils.getDocument();
        let headertext = $("[name='top-area-header-text']").val();
        $(iframeDocument).find(".footer-top-area-section-7 .section-heading").html(headertext);
    });

    $("[name='footerbottomtext'], [name='footerbottomlink']").on("input", () => {
        let iframeDocument = Utils.getDocument();
        let footerbottomtext = $("[name='footerbottomtext']").val();
        let footerbottomlink = $("[name='footerbottomlink']").val();
        $(iframeDocument).find(".footer-bottomtext").html(footerbottomtext);
        $(iframeDocument).find(".footer-bottomtext").attr("href", footerbottomlink);
    });

    $("[name^='footer-template-next-btn-']").on("click", () => {
        const footerdesignselector = $(SELECTOR.FOOTERDESIGNSELECTOR+':checked');
        const selectedDesign = footerdesignselector.data('flayout');
        handleDesignSpecificSettings(selectedDesign);
    });

    window.backgroundimgitemid = $(SELECTOR.BACKGROUNDIMG).val();
    window.footerwidgetlogoitemid = $(SELECTOR.FOOTERWIDGETLOGO).val();

    // Initialize TinyMCE event listeners
    initializeTinyMCEEvents();
}
function useFooterWidgetLogo(defaultImg = true) {
    let iframeDocument = Utils.getDocument();
    let show = $(SELECTOR.SHOWFOOTERWIDGETLOGO).is(":checked");

    if (!show) {
        // Hide the footer widget logo
        $(iframeDocument).find("#page-footer #footer-column-1 .footerwidgetlogo").addClass(SELECTOR.DNONE);
        return;
    }

    let itemid = $(SELECTOR.FOOTERWIDGETLOGO).val();
    if(!defaultImg){
        itemid = '';
        $(SELECTOR.FOOTERWIDGETLOGO).val(234567891);
    }

    let response = '';
    if(itemid == ''){
        if(getActiveFooterDesign() === 'footerdesign1') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option1.png";
        }
        else if(getActiveFooterDesign() === 'footerdesign2') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option2.png";
        }
        else if(getActiveFooterDesign() === 'footerdesign3') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option3.png";
        }
        else if(getActiveFooterDesign() === 'footerdesign4') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option4.png";
        }
        else if(getActiveFooterDesign() === 'footerdesign6') {
            response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option6.svg";
        }

        // Only update the image source, don't replace the entire content
        let $logoImg = $(iframeDocument).find("#page-footer #footer-column-1 .footerwidgetlogo");
        if ($logoImg.length > 0) {
            // Image exists, just update the source
            $logoImg.attr("src", response).removeClass(SELECTOR.DNONE);
        } else {
            // Image doesn't exist, check if we should add it
            let $contentHtml = $(iframeDocument).find("#page-footer #footer-column-1 .contenthtml");
            if ($contentHtml.length > 0) {
                // Only add if there's no existing footerwidgetlogo in the entire column
                let $existingLogo = $contentHtml.find('.footerwidgetlogo');
                if ($existingLogo.length === 0) {
                    $contentHtml.prepend(`<img src="${response}" class="footerwidgetlogo">`);
                }
            }
        }
    }else{
        Utils.getFileURL(itemid).done(function(response) {
            if (response == "") {
                // response = M.cfg.wwwroot + "/theme/remui/pix/logomini.png";
                if(getActiveFooterDesign() === 'footerdesign1') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option1.png";
                }
                else if(getActiveFooterDesign() === 'footerdesign2') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option2.png";
                }
                else if(getActiveFooterDesign() === 'footerdesign3') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option3.png";
                }
                else if(getActiveFooterDesign() === 'footerdesign4') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option4.png";
                }
                else if(getActiveFooterDesign() === 'footerdesign6') {
                    response = "https://qastaticcdn.edwiser.org/theme_remuiassets/footerassets/images/option6.svg";
                }
            }

            // Only update the image source, don't replace the entire content
            let $logoImg = $(iframeDocument).find("#page-footer #footer-column-1 .footerwidgetlogo");
            if ($logoImg.length > 0) {
                // Image exists, just update the source
                $logoImg.attr("src", response).removeClass(SELECTOR.DNONE);
            } else {
                // Image doesn't exist, check if we should add it
                let $contentHtml = $(iframeDocument).find("#page-footer #footer-column-1 .contenthtml");
                if ($contentHtml.length > 0) {
                    // Only add if there's no existing footerwidgetlogo in the entire column
                    let $existingLogo = $contentHtml.find('.footerwidgetlogo');
                    if ($existingLogo.length === 0) {
                        $contentHtml.prepend(`<img src="${response}" class="footerwidgetlogo">`);
                    }
                }
            }
        });
    }


}
/**
 * Initialize social media settings visibility based on current checkbox states
 */
function initializeSocialSettingsVisibility() {
    // Initialize column social settings
    for (let i = 1; i <= 5; i++) {
        let $checkbox = $(`[name="socialmediaiconcol${i}"]`);
        if ($checkbox.length > 0) {
            let show = $checkbox.is(":checked");

            // Show/hide all social media input fields for this column
            $(`[name*="setting${i}"]`).each(function() {
                let $socialSetting = $(this);
                if ($socialSetting.attr('name').includes('setting') &&
                    $socialSetting.attr('name').endsWith(i.toString())) {
                    $socialSetting.closest(SELECTOR.SETTINGITEM).toggleClass(SELECTOR.DNONE, !show);
                }
            });

            // Show/hide social media note
            $(`.${SELECTOR.COLUMN}${i}social-note`)
                .closest(SELECTOR.SETTINGITEM)
                .toggleClass(SELECTOR.DNONE, !show);

            // Update iframe preview visibility for this column
            if (show) {
                // Show social media in preview by calling updateSocialMediaPreview with existing values
                let socialTypes = ['facebook', 'twitter', 'linkedin', 'youtube', 'instagram', 'pinterest', 'quora', 'whatsapp', 'telegram'];
                socialTypes.forEach(socialType => {
                    let $socialInput = $(`[name="${socialType}setting${i}"]`);
                    if ($socialInput.length > 0 && $socialInput.val()) {
                        updateSocialMediaPreview(i, socialType, $socialInput.val());
                    }
                });
            } else {
                // Hide social media in preview by calling updateSocialMediaPreview with empty value
                updateSocialMediaPreview(i, 'visibility', '');
            }
        }
    }

    // Initialize footer-secondary social settings
    let $secondaryCheckbox = $(`[name="footersocialmediaicons"]`);
    if ($secondaryCheckbox.length > 0) {
        let show = $secondaryCheckbox.is(":checked");

        // Show/hide all social media input fields in footer-secondary
        $(SELECTOR.SOCIALICONS).each(function() {
            let $socialSetting = $(this);
            $socialSetting.closest(SELECTOR.SETTINGITEM).toggleClass(SELECTOR.DNONE, !show);
        });

        // Don't update iframe preview here - let individual input listeners handle it
        // This prevents duplicate calls when footer design is applied
    }
}

/**
 * Initialize email subscribe settings visibility on page load
 */
function initializeEmailSubscribeSettingsVisibility() {
    const emailSubscribeColumns = [0, 1, 4, 5];
    const allSettings = ['subscribetargetlink', 'emailinputbordercolor', 'focusedemailinputoutlinecolor', 'subscribebuttontextcolor', 'subscribebuttontexthovercolor', 'subscribebtnbgcolor', 'subscribebtnbghovercolor'];

    emailSubscribeColumns.forEach(columnIndex => {
        const $toggle = $(`[name="toggle_email_subscribe_settings${columnIndex}"]`);
        if ($toggle.length > 0) {
            const show = $toggle.is(":checked");
            const iframeDocument = Utils.getDocument();
            const selector = columnIndex === 0 ? "#page-footer .subscribe-box" : `#footer-column-${columnIndex} .subscribe-box`;

            // Show/hide settings based on current toggle state
            allSettings.forEach(settingName => {
                $(`#fitem_id_${settingName}${columnIndex}`).closest(".setting-type-text, .setting-type-color")
                    .toggleClass(SELECTOR.DNONE, !show);
            });

            // Update iframe preview based on current toggle state
            $(iframeDocument).find(selector).toggleClass(SELECTOR.DNONE, !show).toggleClass("d-flex", show);
        }
    });
}

/**
 * Initialize email subscribe event listeners and handlers
 */
function initializeEmailSubscribeListeners() {
    const emailSubscribeColumns = [0, 1, 4, 5];
    const styleSettings = ['emailinputbordercolor', 'focusedemailinputoutlinecolor', 'subscribebuttontextcolor', 'subscribebuttontexthovercolor', 'subscribebtnbgcolor', 'subscribebtnbghovercolor'];
    const allSettings = ['subscribetargetlink', ...styleSettings];

    emailSubscribeColumns.forEach(columnIndex => {
        // Toggle change listener
        $(`[name="toggle_email_subscribe_settings${columnIndex}"]`).on("change", function() {
            const show = $(this).is(":checked");
            const iframeDocument = Utils.getDocument();
            const selector = columnIndex === 0 ? "#page-footer .subscribe-box" : `#footer-column-${columnIndex} .subscribe-box`;

            // Show/hide settings
            allSettings.forEach(settingName => {
                $(`#fitem_id_${settingName}${columnIndex}`).closest(".setting-type-text, .setting-type-color")
                    .toggleClass(SELECTOR.DNONE, !show);
            });

            if (columnIndex === 0) {
                $(iframeDocument)
                    .find("#page-footer .footer-container .footer-top-area-section-7")
                    .toggleClass(SELECTOR.DNONE, !show);
            }

            // Update iframe preview
            $(iframeDocument).find(selector).toggleClass(SELECTOR.DNONE, !show).toggleClass("d-flex", show);
        });

        // Style change listeners
        styleSettings.forEach(settingName => {
            $(`[name="${settingName}${columnIndex}"]`).on("color.changed", function() {
                const styles = {
                    inputBorder: $(`[name="emailinputbordercolor${columnIndex}"]`).spectrum("get").toString(),
                    inputOutline: $(`[name="focusedemailinputoutlinecolor${columnIndex}"]`).spectrum("get").toString(),
                    btnText: $(`[name="subscribebuttontextcolor${columnIndex}"]`).spectrum("get").toString(),
                    btnTextHover: $(`[name="subscribebuttontexthovercolor${columnIndex}"]`).spectrum("get").toString(),
                    btnBg: $(`[name="subscribebtnbgcolor${columnIndex}"]`).spectrum("get").toString(),
                    btnBgHover: $(`[name="subscribebtnbghovercolor${columnIndex}"]`).spectrum("get").toString()
                };

                const selector = columnIndex === 0 ? "#page-footer .subscribe-box" : `#footer-column-${columnIndex} .subscribe-box`;
                let additionalcss = '';
                if(columnIndex === 0){
                    additionalcss = `${selector} .edw-icon-Email { color: ${styles.inputBorder} !important; }`;
                }
                const css = `
                    ${additionalcss}
                    ${selector} input { border-color: ${styles.inputBorder} !important; }
                    ${selector} input:focus { border: 1px solid ${styles.inputOutline} !important; }
                    ${selector} input:focus ~ .edw-icon-Email { color: ${styles.inputOutline} !important; }
                    ${selector} .subscribe-btn { color: ${styles.btnText} !important; background-color: ${styles.btnBg} !important; }
                    ${selector} .subscribe-btn:hover { color: ${styles.btnTextHover} !important; background-color: ${styles.btnBgHover} !important; }
                `;
                Utils.putStyle(`email-subscribe-styles-${columnIndex}`, css);
            });
        });

        // Target link change listener
        $(`[name="subscribetargetlink${columnIndex}"]`).on("input", function() {
            const iframeDocument = Utils.getDocument();
            const selector = columnIndex === 0 ? "#page-footer .subscribe-btn" : `#footer-column-${columnIndex} .subscribe-btn`;
            $(iframeDocument).find(selector).attr("href", $(this).val());
        });
    });
}

/**
 * Initialize event listeners for social media input fields
 */
function initializeSocialMediaInputListeners() {
    // Add listeners for column social media inputs (footer-primary)
    for (let i = 1; i <= 5; i++) {
        $(`[name*="setting${i}"]`).each(function() {
            let $input = $(this);
            if ($input.attr('name').includes('setting') &&
                $input.attr('name').endsWith(i.toString())) {

                // Get the social media type from the input name
                let socialType = $input.attr('name').replace(`setting${i}`, '');

                // Add input event listener for footer-primary columns
                $input.on('input', function() {
                    updateSocialMediaPreview(i, socialType, $(this).val());
                });
            }
        });
    }

    // Add listeners for footer-secondary social media inputs
    $(SELECTOR.SOCIALICONS).each(function() {
        let $input = $(this);
        let inputName = $input.attr('name');

        // Only handle footer-secondary inputs (those without numbers at the end)
        if (inputName.includes('setting') && !inputName.match(/\d+$/)) {
            // Get the social media type from the input name
            let socialType = inputName.replace('setting', '');

            // Add input event listener for footer-secondary
            $input.on('input', function() {
                updateSocialMediaPreview('secondary', socialType, $(this).val());

                if ($(".social-links-wrapper .contentsocial.social-links").children().length === 0) {
                    $(".followustext").addClass(SELECTOR.DNONE);
                } else {
                    $(".followustext").removeClass(SELECTOR.DNONE);
                }
            });
        }
    });
}

/**
 * Update social media preview in iframe based on input changes
 * @param {string|number} column Column number or 'secondary' for footer-secondary
 * @param {string} socialType Social media type (facebook, twitter, etc.)
 * @param {string} value Input value
 */
function updateSocialMediaPreview(column, socialType, value) {
    let iframeDocument = Utils.getDocument();
    let selector, socialLinksContainer;

    if (column === 'secondary') {
        // Handle footer-secondary social media
        selector = '.footer-secondarysection-wrapper .social-links';
        socialLinksContainer = $(iframeDocument).find(selector);
    } else {
        // Handle column social media
        selector = `#footer-column-${column} .social-links`;
        socialLinksContainer = $(iframeDocument).find(selector);
    }

    if (socialLinksContainer.length === 0) {
        console.log(`Social links container not found for selector: ${selector}`);
        return; // Social links container doesn't exist
    }


    // Find existing social media link for this type
    let existingLink = socialLinksContainer.find(`a[data-social="${socialType}"]`);
    console.log(`Found existing link: ${existingLink.length}`);

    // Clear container and rebuild all social media links
    socialLinksContainer.empty();

    // Rebuild all social media links from current input values
    let socialTypes = ['facebook', 'twitter', 'linkedin', 'youtube', 'instagram', 'pinterest', 'quora', 'whatsapp', 'telegram'];
    socialTypes.forEach(type => {
        let $input;
        if (column === 'secondary') {
            // For footer-secondary, use inputs without column numbers
            $input = $(`[name="${type}setting"]`);
        } else {
            // For columns, use inputs with column numbers
            $input = $(`[name="${type}setting${column}"]`);
        }
        if ($input.length > 0 && $input.val() && $input.val().trim() !== '') {
            let socialData = socialList[type];
            if (socialData) {
                let linkHtml = `<a href="${$input.val()}" class="${socialData.class}" data-social="${type}" title="${socialData.title}"><i class="${socialData.icon}"></i></a>`;
                socialLinksContainer.append(linkHtml);
            }
        }
    });
}
export default {
    init,
    apply,
};


/**
 * Call personalization action web service
 * @param {string} action Action type to perform
 * @param {Object} config Configuration data
 * @return {Promise} Promise that resolves with response
 */
function callPersonalizationAction(action, config) {
    return Ajax.call([{
        methodname: 'theme_remui_do_personalization_action',
        args: {
            action: action,
            config: JSON.stringify(config)
        }
    }])[0];
}


/**
 * Apply footer design and show/hide appropriate settings
 * @param {string} design The footer design to apply (e.g., 'footerdesign1', 'footerdesign2', etc.)
 */
/**
 * Apply footer design and show/hide appropriate settings
 * @param {string} design The footer design to apply (e.g., 'footerdesign1', 'footerdesign2', etc.)
 */
function InitiateNewFooterDesign(design) {
    let iframeDocument = Utils.getDocument();
    if(window.originalFooterContent === undefined && window.currentFooterDesign === 'footerdesign6') {
        let footerTop = iframeDocument.querySelector('#page-footer .footer-top-area-section-7');
        if (footerTop) {
            window.footerTop = footerTop;
        }
    }

    // Handle design-specific settings
    handleDesignSpecificSettings(design);

    //Show Loader
    footerdesignloader(true,iframeDocument);
    // $(`#publish-settings`).prop('disabled', true);
    // $(`#reset-settings`).prop('disabled', true);
    // $(iframeDocument).find("#page-loader-wrapper").toggleClass("d-flex");

    // Call the personalization action to get footer design
    callPersonalizationAction('get_footer_design_dummy_data', { design: design })
        .done(function(response) {
            try {
                let data = JSON.parse(response, true);

                if (data.success && data.data) {
                    // Parse the footer design data
                    let footerConfigData;

                    // Handle different response formats
                    if (typeof data.data === 'string') {
                        footerConfigData = JSON.parse(data.data, true);
                    } else {
                        footerConfigData = data.data;
                    }

                    console.log('Footer design data structure:', footerConfigData);

                    // Apply the footer design data using new structure
                    applyFooterDesignData(footerConfigData);

                    applyBackgroundImgURL(false);

                    useFooterWidgetLogo(false);

                    // // Handle design-specific settings
                    handleDesignSpecificSettings(design);

                    console.log('Footer design applied successfully:', design);
                } else {
                    console.error('Failed to fetch footer design:', data.error || 'Unknown error');
                    //Hide the Loader
                    footerdesignloader(false,iframeDocument);
                    // $(iframeDocument).find("#page-loader-wrapper").toggleClass("d-flex");
                    // $(`#publish-settings`).prop('disabled', false);
                    // $(`#reset-settings`).prop('disabled', false);
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                footerdesignloader(false,iframeDocument);
                // $(iframeDocument).find("#page-loader-wrapper").toggleClass("d-flex");
                // $(`#reset-settings`).prop('disabled', false);
            }
        })
        .fail(function(error) {
            console.error('Error calling personalization service:', error);
            // $(iframeDocument).find("#page-loader-wrapper").toggleClass("d-flex");
            footerdesignloader(false,iframeDocument);
        });
}

function footerdesignloader(value,iframeDocument) {
    $(iframeDocument).find("#page-loader-wrapper").toggleClass("d-flex");
    $(`#publish-settings`).prop('disabled', value);
    $(`#reset-settings`).prop('disabled', value);
    $(`button[name^='footer-template-next-btn-']`).prop('disabled', value);
}

/**
 * Hide specific settings based on the selected footer design
 * @param {string} design The footer design to hide settings for
 */
function handleDesignSpecificSettings(design) {
    // Remove section-footer-design-7 from DOM if any design other than footerdesign6 is active
    if (design !== 'footerdesign6') {
        let iframeDocument = Utils.getDocument();
        let design7Element = iframeDocument.querySelector('.section-footer-design-7');
        if (design7Element) {
            design7Element.remove();
            console.log('Removed section-footer-design-7 element for design:', design);
        }
    }

    switch (design) {
        case 'footerdesign0':
            handleDesign0SpecificSettings();
            break;
        case 'footerdesign1':
            handleDesign1SpecificSettings();
            break;
        case 'footerdesign2':
            handleDesign2SpecificSettings();
            break;
        case 'footerdesign3':
            handleDesign3SpecificSettings();
            break;
        case 'footerdesign4':
            handleDesign4SpecificSettings();
            break;
        case 'footerdesign5':
            handleDesign5SpecificSettings();
            break;
        case 'footerdesign6':
            handleDesign6SpecificSettings();
            break;
        default:
            // Default behavior - no hiding
            break;
    }
}

/**
 * Hide settings specific to Footer Design 7
 */
function handleDesign0SpecificSettings() {
    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    $('[name="main-footer-area-text-color"]').closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`[name="main-footer-area-background-color"]`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-background-color"]`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`[name="footersocialmediaicons"]`).closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);
    $(`[name="footer-icon-bg-color"]`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    $(`[name="showfooterwidgetlogo"]`).prop('checked',false).trigger('change');
    $(`[name="showfooterwidgetlogo"]`).closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    toggleSocialSettings({ showCol: 'all'});

    $(`#fitem_id_footercolumn`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);
    $(`[name="footer-divider-color"]`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    $('[name="toggle_email_subscribe_settings1"]').prop('checked', false).trigger('change');
    $('[name="toggle_email_subscribe_settings4"]').prop('checked', false).trigger('change');

    $('[name="socialmediaiconcol1"]').prop('checked', true).trigger('change');
    $('[name="socialmediaiconcol4"]').prop('checked', false).trigger('change');

    $('[name="footersocialmediaicons"]').prop('checked', false).trigger('change');

    $('[name="footershowlogo"]').closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
}

const socials = ['facebook', 'twitter', 'linkedin', 'youtube', 'instagram', 'pinterest', 'quora', 'whatsapp', 'telegram'];

function toggleSocialSettings({showCol = null, showEmail = false, showEmailCol = null, hideSocialInputs = true} = {}) {
    for (let i = 1; i <= 5; i++) {
        if (showCol === i || showCol === 'all') {
            $(`#fitem_id_socialmediaiconcol${i}`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);
        } else {
            $(`#fitem_id_socialmediaiconcol${i}`).closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);
        }
        if (hideSocialInputs) {
            for (const social of socials) {
                $(`#fitem_id_${social}setting${i}`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
            }
        }
        if (showEmail && i == showEmailCol) {
            $(`#fitem_id_toggle_email_subscribe_settings${i}`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);
        } else {
            $(`#fitem_id_toggle_email_subscribe_settings${i}`).closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);
        }
    }
}

/**
 * Hide settings specific to Footer Design 1
 */
function handleDesign1SpecificSettings() {
    // Design 1 shows all settings - nothing to hide

    $(`[name="footercolumn1type"]`).val('customhtml').trigger('change');
    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").removeClass(SELECTOR.DNONE);

    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").addClass(SELECTOR.DNONE);
    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    toggleSocialSettings({showCol: 4, showEmail: true, showEmailCol: 4});

    $(`#fitem_id_footersocialmediaicons`).closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-text-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_footer-divider-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    $(`[name="showfooterwidgetlogo"]`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-background-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_bottom-footer-area-background-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    $('[name="footersocialmediaicons"]').prop('checked', false).trigger('change');

    $('[name="footershowlogo"]').prop('checked', false).trigger('change');
    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1title"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="socialmediaiconcol1"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="socialmediaiconcol2"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="socialmediaiconcol3"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerprivacypolicyshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footertermsandconditionsshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="facebooksetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="twittersetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="linkedinsetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="youtubesetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="instagramsetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="pinterestsetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="quorasetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="whatsappsetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="telegramsetting4"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);

    $('[name="toggle_email_subscribe_settings4"]').prop('checked', true).trigger('change');

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);


    // $(`[name="footercolumn4menu"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    // // Hide custom HTML for column 3
    // $(`[name="footercolumn3customhtml"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    // $(`[name="socialmediaiconcol3"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    // $(`[name="footercolumn3social"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    // $(`.footercolumn3social-note`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    // Set default column types for design 4
    // $(`[name="footercolumn2type"]`).val('customhtml').trigger('change');
    // $(`[name="footercolumn3type"]`).val('menu').trigger('change');
    // $(`[name="footercolumn4type"]`).val('customhtml').trigger('change');
}

/**
 * Hide settings specific to Footer Design 2
 */
function handleDesign2SpecificSettings() {
    // Design 2 shows all settings - nothing to hide
    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footersocialmediaicons`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").removeClass(SELECTOR.DNONE);

    $(`[name="showfooterwidgetlogo"]`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    toggleSocialSettings();
    $(`#fitem_id_main-footer-area-text-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footer-divider-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-background-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_bottom-footer-area-background-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    // $('[name="footersocialmediaicons"]').prop('checked', true).trigger('change');

    $('[name="footershowlogo"]').prop('checked', false).trigger('change');
    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    $('[name="toggle_email_subscribe_settings1"]').prop('checked', false).trigger('change');
    $('[name="toggle_email_subscribe_settings4"]').prop('checked', false).trigger('change');
    $('[name="toggle_email_subscribe_settings5"]').prop('checked', false).trigger('change');

    $(`[name="footerprivacypolicyshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footertermsandconditionsshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);


    $(`[name="footercolumn1title"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn5type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="socialmediaiconcol1"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="socialmediaiconcol2"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="socialmediaiconcol3"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="socialmediaiconcol4"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="socialmediaiconcol5"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footer-icon-bg-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
}

/**
 * Hide settings specific to Footer Design 3
 */
function handleDesign3SpecificSettings() {
    // Design 3 shows all settings - nothing to hide
    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footersocialmediaicons`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);
    toggleSocialSettings({showCol: null, showEmail: true, showEmailCol: 1});
    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").removeClass(SELECTOR.DNONE);

    $(`[name="showfooterwidgetlogo"]`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-text-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footer-divider-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`#fitem_id_bottom-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    // $('[name="footersocialmediaicons"]').prop('checked', true).trigger('change');

    $(`[name="footercolumn1title"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $('[name="footershowlogo"]').prop('checked', false).trigger('change');
    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);
    $(`[name="footerprivacypolicyshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footertermsandconditionsshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
}

/**
 * Hide settings specific to Footer Design 4
 */
function handleDesign4SpecificSettings() {
    // Design 4 has mixed column types - hide menu for columns 1,2,4

    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footersocialmediaicons`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);
    toggleSocialSettings();
    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`#fitem_id_bottom-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    // $('[name="footersocialmediaicons"]').prop('checked', true).trigger('change');

    $('[name="footershowlogo"]').prop('checked', false).trigger('change');
    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);
    $(`[name="footerprivacypolicyshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footertermsandconditionsshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);


    $(`[name="footer-icon-bg-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn1title"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="socialmediaiconcol1"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="main-footer-area-text-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="showfooterwidgetlogo"]`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    $('[name="toggle_email_subscribe_settings4"]').prop('checked', false).trigger('change');

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").addClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);


}

/**
 * Hide settings specific to Footer Design 5
 */
function handleDesign5SpecificSettings() {
    // Design 5 shows all settings but has special email subscribe in column 1
    // Nothing to hide, but set default column types
    // for (let i = 1; i <= 4; i++) {
    //     $(`[name="footercolumn${i}type"]`).val('customhtml').trigger('change');
    // }
    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").addClass(SELECTOR.DNONE);
    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footersocialmediaicons`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);
    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").removeClass(SELECTOR.DNONE);
    toggleSocialSettings({showCol: null, showEmail: true, showEmailCol: 1});

    $(`#fitem_id_main-footer-area-text-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`#fitem_id_bottom-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    $(`[name="footerprivacypolicyshow"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    $(`[name="footertermsandconditionsshow"]`).closest(SELECTOR.SETTINGITEM).removeClass(SELECTOR.DNONE);
    // $('[name="footersocialmediaicons"]').prop('checked', true).trigger('change');

    $('[name="footershowlogo"]').prop('checked', false).trigger('change');
    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    $('[name="showfooterwidgetlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);

    $(`[name="footer-icon-bg-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="socialmediaiconcol1"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="main-footer-area-text-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $('[name="toggle_email_subscribe_settings4"]').prop('checked', false).trigger('change');

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
}

/**
 * Hide settings specific to Footer Design 6
 */
function handleDesign6SpecificSettings() {
    // Design 6 shows all settings - nothing to hide
    // Set default column types
    // for (let i = 1; i <= 4; i++) {
    //     $(`[name="footercolumn${i}type"]`).val('customhtml').trigger('change');
    // }
    $(`[data-panel-id="footer-top-area"]`).closest(".group-item").removeClass(SELECTOR.DNONE);
    $(`#heading_footer-advance-column5`).closest(".heading-wrapper").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumnsize`).closest(".setting-type-text").addClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn`).closest(".setting-type-range").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footercolumn5`).closest(".setting-type-range").addClass(SELECTOR.DNONE);
    $(`[data-panel-id="footer-template-background-image"]`).closest(".group-item").removeClass(SELECTOR.DNONE);
    $(`#fitem_id_footersocialmediaicons`).closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);
    toggleSocialSettings();
    $(`#fitem_id_main-footer-area-text-color`).closest(".setting-type-color").removeClass(SELECTOR.DNONE);

    $(`#fitem_id_main-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);
    $(`#fitem_id_bottom-footer-area-background-color`).closest(".setting-type-color").addClass(SELECTOR.DNONE);

    // $('[name="footersocialmediaicons"]').prop('checked', true).trigger('change');

    $('[name="footershowlogo"]').prop('checked', false).trigger('change');
    $('[name="footershowlogo"]').closest(".setting-type-checkbox").addClass(SELECTOR.DNONE);
    $(`[name="footerprivacypolicyshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footertermsandconditionsshow"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $('[name="showfooterwidgetlogo"]').closest(".setting-type-checkbox").removeClass(SELECTOR.DNONE);

    $(`[name="footer-icon-bg-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn2type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn3type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footercolumn4type"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="footercolumn1title"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $(`[name="socialmediaiconcol1"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="main-footer-area-text-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="bottom-footer-area-text-color"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);

    $('[name="toggle_email_subscribe_settings4"]').prop('checked', false).trigger('change');

    $(`[name="footercolumn4title"]`).closest(".setting-type-text").removeClass(SELECTOR.DNONE);

    $(`[name="footerbottomtext"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
    $(`[name="footerbottomlink"]`).closest(SELECTOR.SETTINGITEM).addClass(SELECTOR.DNONE);
}

/**
 * Store original footer content and clear sections for new content
 * @param {Document} iframeDocument The iframe document
 * @return {Object} Object containing original footer content
 */
function storeAndClearFooterSections(iframeDocument) {
    let originalContent = {};

    // Store footer top section content if it exists
    // let footerTop = iframeDocument.querySelector('#page-footer .footer-top-area-section-7');
    let footerTop = window.footerTop;
    if (footerTop) {
        originalContent.footerTop = footerTop.innerHTML;
        footerTop.style.display = 'none';
    }

    // Store footer main section content and clear it
    let footerMain = iframeDocument.querySelector('#page-footer .footer-mainsection-wrapper');
    if (footerMain) {
        originalContent.footerMain = footerMain.innerHTML;
        // Clear content but keep the wrapper element
        footerMain.innerHTML = '';
        footerMain.style.display = 'block';
        console.log('Footer main wrapper found and cleared:', footerMain);
    } else {
        console.log('Footer main wrapper NOT found!');
    }

    // Store footer secondary section content and clear it
    let footerSecondary = iframeDocument.querySelector('#page-footer .footer-secondarysection-wrapper');
    if (footerSecondary) {
        originalContent.footerSecondary = footerSecondary.innerHTML;
        // Clear content but keep the wrapper element
        footerSecondary.innerHTML = '';
        footerSecondary.style.display = 'block';
        console.log('Footer secondary wrapper found and cleared:', footerSecondary);
    } else {
        console.log('Footer secondary wrapper NOT found!');
    }

    return originalContent;
}

/**
 * Apply footer design data to the preview using hybrid approach
 * @param {Object} data Footer design data with setting names and values + HTML content
 */
function applyFooterDesignData(data) {
    console.log('Inside applyFooterDesignData - Hybrid Structure');
    console.log('Data received:', data);

    // Store original content and settings globally for potential restoration
    let iframeDocument = Utils.getDocument();
    let originalContent = storeAndClearFooterSections(iframeDocument);
    if(window.originalFooterContent === undefined) {
        window.originalFooterContent = originalContent;
        window.originalFooterSettings = backupFooterSettings();
    }

    // HYBRID APPROACH: Apply HTML structure first, then settings

    // 1. Apply HTML content structure (like old logic)
    if (data.htmlcontent && typeof data.htmlcontent === 'object') {
        console.log('Applying HTML content structure');

        // Apply footer top HTML
        if (data.htmlcontent.tophtml) {
            let footerTop = iframeDocument.querySelector('#page-footer .footer-top-area-section-7');
            if (footerTop) {
                footerTop.style.display = 'block';
                footerTop.innerHTML = data.htmlcontent.tophtml;
            } else {
                // Create new footer top section
                let footerMain = iframeDocument.querySelector('#page-footer .footer-mainsection-wrapper');
                if (footerMain && footerMain.parentNode) {
                    let newFooterTop = iframeDocument.createElement('div');
                    newFooterTop.className = 'section-footer-design-7 footer-top-area-section-7';
                    newFooterTop.innerHTML = data.htmlcontent.tophtml;
                    footerMain.parentNode.insertBefore(newFooterTop, footerMain);
                    console.log('Created new footer top section');
                }
            }
        }

        // Apply footer main HTML content
        if (data.htmlcontent.maincontenthtml) {
            let footerMain = iframeDocument.querySelector('#page-footer .footer-mainsection-wrapper');
            if (footerMain) {
                footerMain.innerHTML = data.htmlcontent.maincontenthtml;
                console.log('Applied main content HTML');
            }
        }

        // Apply footer bottom HTML
        if (data.htmlcontent.bottomhtml) {
            let footerSecondary = iframeDocument.querySelector('#page-footer .footer-secondarysection-wrapper');
            if (footerSecondary) {
                footerSecondary.innerHTML = data.htmlcontent.bottomhtml;
                console.log('Applied bottom content HTML');
            }
        }
    }

    // 2. Handle footertop settings (if needed)
    if (!data.footertop || data.footertop === false) {
        console.log('TODO: Handle empty footertop case - footertop is disabled or empty');
        // TODO: Implement footertop handling when needed
    } else {
        // Handle footertop settings if they exist
        if (typeof data.footertop === 'object') {
            Object.keys(data.footertop).forEach(key => {
                applySettingValue(key, data.footertop[key]);
            });
        }
    }

    // Handle footerbottom settings
    if (data.footerbottom && typeof data.footerbottom === 'object') {
        Object.keys(data.footerbottom).forEach(key => {
            applySettingValue(key, data.footerbottom[key]);
        });
    }

    // Handle configData array - should contain 4 column objects
    if (data.configData && Array.isArray(data.configData)) {
        console.log(`Processing configData array with ${data.configData.length} columns`);

        data.configData.forEach((columnData, index) => {
            console.log(`Processing column ${index + 1}:`, columnData);

            // Apply each setting
            Object.keys(columnData).forEach(key => {
                let value = columnData[key];
                console.log(`Column ${index + 1} - Key: ${key}, Value:`, value, 'Type:', typeof value, 'Is Array:', Array.isArray(value));

                // If this is the type setting and it's 'menu', apply type and continue to process menu data
                if (/^footercolumn\d+type$/.test(key) && value === 'menu') {
                    console.log(`Setting column type to 'menu' for column ${index + 1}`);
                    applySettingValue(key, value);
                    // Continue processing other settings including menu data
                }

                // Apply any other setting normally
                console.log(`Calling applySettingValue for: ${key} =`, value);
                applySettingValue(key, value);
            });
        });
    }

    // Handle footer colors using existing color logic
    if (data.footercolors && typeof data.footercolors === 'object') {
        console.log('Applying footer colors');
        applyFooterColors(data.footercolors);
    }


    // Scroll to footer to make it visible
    scrollToFooter(iframeDocument);

    initializeSocialSettingsVisibility();

    // Apply all footer settings to sync the iframe with form controls
    apply();

    // Sync TinyMCE content after design application (run after apply())
    syncTinyMCEContentAfterDesign();

    console.log('Footer design data applied successfully');
    footerdesignloader(false,iframeDocument);
    // $(iframeDocument).find("#page-loader-wrapper").toggleClass("d-flex");
    // $(`#publish-settings`).prop('disabled', false);
    // $(`#reset-settings`).prop('disabled', false);
}

/**
 * Sync TinyMCE content after design application
 */
function syncTinyMCEContentAfterDesign() {
    // Wait a bit for TinyMCE to be ready after design application
    setTimeout(() => {
        for (let i = 1; i <= 5; i++) {
            const editorId = `id_footercolumn${i}customhtml`;
            if (typeof window.tinymce !== 'undefined' && window.tinymce.get(editorId)) {
                // Get content from textarea and set it in TinyMCE
                const textareaContent = $(`[name="footercolumn${i}customhtml"]`).val();

                if (textareaContent) {
                    window.tinymce.get(editorId).setContent(textareaContent);
                    // Also update the iframe preview with the content
                    updateFooterColumnContent(i, textareaContent);
                }
            } else {
                // If TinyMCE not available, still update iframe with textarea content
                const textareaContent = $(`[name="footercolumn${i}customhtml"]`).val();
                if (textareaContent) {
                    updateFooterColumnContent(i, textareaContent);
                }
            }
        }
    }, 1000); // Wait 1 second for everything to be ready
}

/**
 * Apply a single setting value to its corresponding form control
 * @param {string} settingKey The setting name (used as selector)
 * @param {*} value The value to set
 */
function applySettingValue(settingKey, value) {
    try {
        let $element = $(`[name="${settingKey}"]`);

        if ($element.length === 0) {
            console.warn(`Setting element not found for key: ${settingKey}`);
            return;
        }

        console.log(`Applying setting: ${settingKey} = ${value}`);

        // Handle different input types
        let inputType = $element.attr('type');
        let tagName = $element.prop('tagName').toLowerCase();

        if (inputType === 'checkbox') {
            // Handle checkbox inputs
            let isChecked = value === true || value === 'true' || value === '1' || value === 1;
            $element.prop('checked', isChecked).trigger('change');

        } else if (inputType === 'color' || $element.hasClass('spectrum')) {
            // Handle color inputs (Spectrum color pickers)
            if ($element.spectrum) {
                $element.spectrum('set', value).trigger('color.changed');
            } else {
                $element.val(value).trigger('change');
            }

        } else if (tagName === 'select') {
            // Handle select dropdowns
            $element.val(value).trigger('change');

        } else if (settingKey.includes('menu') && value && typeof value === 'string') {
            // Handle menu setting as JSON string - use Templates.render approach
            // Set the JSON string directly to the textarea
            $element.val(value).trigger('change');

            // Parse the JSON to get menu items for regeneration
            try {
                let menuItems = JSON.parse(value);
                if (Array.isArray(menuItems)) {
                    // Regenerate menu DOM items using template approach
                    if (settingKey.match(/^footercolumn(\d+)menu$/)) {
                        let columnIndex = settingKey.match(/^footercolumn(\d+)menu$/)[1];

                        // Find the menu element container
                        let menuRoot = `#fitem_id_${settingKey}`;
                        let menuElement = $(menuRoot);
                        if (menuElement.length) {
                            // Find the menu item list container
                            let menuItemList = menuElement.find('.customizer-menu-item-list');
                            if (menuItemList.length > 0) {
                                // Clear existing menu items
                                menuItemList.empty();

                                // Use Templates.render to generate menu items using the Mustache template
                                // Process menu items one by one using the existing template system
                                let processNextMenuItem = function(items, index) {
                                    if (index >= items.length) {
                                        // Update iframe preview with the new menu
                                        setTimeout(() => {
                                            menuChange(columnIndex);
                                        }, 100);
                                        return;
                                    }

                                    let item = items[index];

                                    Templates.render('theme_remui/customizer/elements/menu/menu-item', item)
                                    .done(function(html, js) {
                                        Templates.appendNodeContents(menuItemList, html, js);
                                        // Process next item
                                        processNextMenuItem(items, index + 1);
                                    })
                                    .fail(function(error) {
                                        // Continue with next item even if this one fails
                                        processNextMenuItem(items, index + 1);
                                    });
                                };

                                // Start processing menu items
                                processNextMenuItem(menuItems, 0);

                            }
                        }
                    }
                }
            } catch (parseError) {
                // Silent fail - continue processing other settings
            }

        } else if (tagName === 'textarea' || inputType === 'text' || inputType === 'number') {
            // Handle text inputs, textareas, and number inputs
            $element.val(value).trigger('change');
            $element.val(value).trigger('input');

        } else if (settingKey.includes('menu') && Array.isArray(value)) {
            // Handle menu arrays - convert to JSON string
            $element.val(JSON.stringify(value)).trigger('change');

        } else {
            // Default handling for other input types
            $element.val(value).trigger('change');
        }

        // Additional event triggers for specific settings
        if (settingKey.includes('customhtml')) {
            // For HTML editor content, also trigger input event
            $element.trigger('input');
        }

    } catch (error) {
        console.error(`Error applying setting ${settingKey}:`, error);
    }
}

/**
 * Backup all current footer settings before applying new design
 * @return {Object} Object containing all current footer settings
 */
function backupFooterSettings() {
    let settings = {};

    try {
        // Backup footer colors
        settings.colors = {
            'footer-background-color': getSettingValue(SELECTOR.BACKGROUNDCOLOR),
            'main-footer-area-background-color': getSettingValue(SELECTOR.MAINBACKGROUNDCOLOR),
            'bottom-footer-area-background-color': getSettingValue(SELECTOR.BOTTOMBACKGROUNDCOLOR),
            'footer-text-color': getSettingValue(SELECTOR.TEXTCOLOR),
            'main-footer-area-text-color': getSettingValue(SELECTOR.MAINAREATEXTCOLOR),
            'footer-divider-color': getSettingValue(SELECTOR.DIVIDERCOLOR),
            'footer-link-text': getSettingValue(SELECTOR.LINKTEXT),
            'footer-link-hover-text': getSettingValue(SELECTOR.LINKHOVERTEXT),
            'footer-icon-color': getSettingValue(SELECTOR.ICONDEFAULTCOLOR),
            'footer-icon-hover-color': getSettingValue(SELECTOR.ICONHOVERCOLOR),
            'footer-columntitle-color': getSettingValue(SELECTOR.FOOTERCOLUMMTITLECOLOR),
            'footer-logo-color': getSettingValue(SELECTOR.FOOTERLOGOCOLOR),
            'footer-icon-bg-color': getSettingValue(SELECTOR.ICONDEFAULTBGCOLOR)
        };

        // Backup footer fonts
        settings.fonts = {
            'footerfontfamily': getSettingValue(SELECTOR.FOOTERFONTFAMILY),
            'footerfontsize': getSettingValue(SELECTOR.FOOTERFONTSIZE),
            'footerfontweight': getSettingValue(SELECTOR.FOOTERFONTWEIGHT),
            'footerfonttext-transform': getSettingValue(SELECTOR.FOOTERTEXTTRANSFORM),
            'footerfontlineheight': getSettingValue(SELECTOR.FOOTERFONTLINEHEIGHT),
            'footerfontltrspace': getSettingValue(SELECTOR.FOOTERFONTLTRSPACE),
            'footer-columntitle-fontfamily': getSettingValue(SELECTOR.FOOTERCOLUMNTITLEFONTFAMILY),
            'footer-columntitle-fontsize': getSettingValue(SELECTOR.FOOTERCOLUMMTITLEFONTSIZE),
            'footer-columntitle-fontweight': getSettingValue(SELECTOR.FOOTERCOLUMNTITLEFONTWEIGHT),
            'footer-columntitle-textransform': getSettingValue(SELECTOR.FOOTERCOLUMMTITLETEXTTRANSFORM),
            'footer-columntitle-lineheight': getSettingValue(SELECTOR.FOOTERCOLUMMTITLELINEHEIGHT),
            'footer-columntitle-ltrspace': getSettingValue(SELECTOR.FOOTERCOLUMMTITLELTRSPACE)
        };

        // Backup footer social media settings (global)
        settings.social = {
            'facebooksetting': getSettingValue('facebooksetting'),
            'twittersetting': getSettingValue('twittersetting'),
            'linkedinsetting': getSettingValue('linkedinsetting'),
            'youtubesetting': getSettingValue('youtubesetting'),
            'instagramsetting': getSettingValue('instagramsetting'),
            'pinterestsetting': getSettingValue('pinterestsetting'),
            'quorasetting': getSettingValue('quorasetting'),
            'whatsappsetting': getSettingValue('whatsappsetting'),
            'telegramsetting': getSettingValue('telegramsetting')
        };

        // Backup footer social media settings for each column
        for (let i = 1; i <= 5; i++) {
            settings[`socialColumn${i}`] = {
                'facebooksetting': getSettingValue(`facebooksetting${i}`),
                'twittersetting': getSettingValue(`twittersetting${i}`),
                'linkedinsetting': getSettingValue(`linkedinsetting${i}`),
                'youtubesetting': getSettingValue(`youtubesetting${i}`),
                'instagramsetting': getSettingValue(`instagramsetting${i}`),
                'pinterestsetting': getSettingValue(`pinterestsetting${i}`),
                'quorasetting': getSettingValue(`quorasetting${i}`),
                'whatsappsetting': getSettingValue(`whatsappsetting${i}`),
                'telegramsetting': getSettingValue(`telegramsetting${i}`)
            };
        }

        // Backup footer-secondary social media settings
        settings.socialSecondary = {
            'facebooksetting': getSettingValue('facebooksetting'),
            'twittersetting': getSettingValue('twittersetting'),
            'linkedinsetting': getSettingValue('linkedinsetting'),
            'youtubesetting': getSettingValue('youtubesetting'),
            'instagramsetting': getSettingValue('instagramsetting'),
            'pinterestsetting': getSettingValue('pinterestsetting'),
            'quorasetting': getSettingValue('quorasetting'),
            'whatsappsetting': getSettingValue('whatsappsetting'),
            'telegramsetting': getSettingValue('telegramsetting')
        };

        // Backup footer advance settings
        settings.advance = {
            [SELECTOR.COLUMN]: getSettingValue(SELECTOR.COLUMN),
            'footercolumn5': getSettingValue('footercolumn5'),
            [SELECTOR.COLUMNSIZE]: getSettingValue(SELECTOR.COLUMNSIZE)
        };

        // Backup footer column settings (1-5)
        for (let i = 1; i <= 5; i++) {
            settings[`column${i}`] = {
                [`${SELECTOR.COLUMN}${i}type`]: getSettingValue(`${SELECTOR.COLUMN}${i}type`),
                [`${SELECTOR.COLUMN}${i}title`]: getSettingValue(`${SELECTOR.COLUMN}${i}title`),
                [`${SELECTOR.COLUMN}${i}customhtml`]: getSettingValue(`${SELECTOR.COLUMN}${i}customhtml`),
                [`socialmediaiconcol${i}`]: getSettingValue(`socialmediaiconcol${i}`),
                [`${SELECTOR.COLUMN}${i}social`]: getSettingValue(`${SELECTOR.COLUMN}${i}social`),
                [`${SELECTOR.COLUMN}${i}menu`]: getSettingValue(`${SELECTOR.COLUMN}${i}menu`)
            };
        }

        // Backup footer secondary settings
        settings.secondary = {
            'footershowlogo': getSettingValue(SELECTOR.SHOWLOGO),
            'useheaderlogo': getSettingValue(SELECTOR.USEHEADERLOGO),
            'footerprivacypolicyshow': getSettingValue(SELECTOR.PRIVACYPOLICYSHOW),
            'footerprivacypolicy': getSettingValue(SELECTOR.PRIVACYPOLICY),
            'privacypolicynewtab': getSettingValue(SELECTOR.PRIVACYPOLICYNEWTAB),
            'footertermsandconditionsshow': getSettingValue(SELECTOR.TERMSANDCONDITIONSSHOW),
            'footertermsandconditions': getSettingValue(SELECTOR.TERMSANDCONDITIONS),
            'termsandconditionewtab': getSettingValue(SELECTOR.TERMSANDCONDITIONSNEWTAB),
            'footercopyrightsshow': getSettingValue(SELECTOR.COPYRIGHTSHOW),
            'footercopyrights': getSettingValue(SELECTOR.COPYRIGHT),
            'poweredbyedwiser': getSettingValue(SELECTOR.POWEREDBY)
        };

        // Backup email subscribe settings for all columns (0-5)
        settings.emailSubscribe = {};
        for (let i = 0; i <= 5; i++) {
            settings.emailSubscribe[`toggle_email_subscribe_settings${i}`] = getSettingValue(`toggle_email_subscribe_settings${i}`);
            settings.emailSubscribe[`subscribetargetlink${i}`] = getSettingValue(`subscribetargetlink${i}`);
            settings.emailSubscribe[`emailinputbordercolor${i}`] = getSettingValue(`emailinputbordercolor${i}`);
            settings.emailSubscribe[`focusedemailinputoutlinecolor${i}`] = getSettingValue(`focusedemailinputoutlinecolor${i}`);
            settings.emailSubscribe[`subscribebuttontextcolor${i}`] = getSettingValue(`subscribebuttontextcolor${i}`);
            settings.emailSubscribe[`subscribebuttontexthovercolor${i}`] = getSettingValue(`subscribebuttontexthovercolor${i}`);
            settings.emailSubscribe[`subscribebtnbgcolor${i}`] = getSettingValue(`subscribebtnbgcolor${i}`);
            settings.emailSubscribe[`subscribebtnbghovercolor${i}`] = getSettingValue(`subscribebtnbghovercolor${i}`);
        }

        // Backup footer widget logo settings
        settings.footerWidgetLogo = {
            'showfooterwidgetlogo': getSettingValue(SELECTOR.SHOWFOOTERWIDGETLOGO),
            'footerwidgetlogo': getSettingValue(SELECTOR.FOOTERWIDGETLOGO)
        };

        let backgroundimagevalue = getSettingValue(SELECTOR.BACKGROUNDIMG);
        if ($('#fitem_id_backgroundimgurl .fm-empty-container').css('display') == 'block') {
            backgroundimagevalue = '';
        }

        settings.footerbackground = {
            'backgroundimg': backgroundimagevalue,
            'backgroundimg-opacity': getSettingValue(SELECTOR.BACKGROUNDIMGOPACITY),
            'backgroundimg-position': getSettingValue(SELECTOR.BACKGROUNDIMGPOS),
            'backgroundimg-repeat': getSettingValue(SELECTOR.BACKGROUNDIMGREPEAT),
            'backgroundimg-size': getSettingValue(SELECTOR.BACKGROUNDIMGSIZE)
        };

        console.log('Footer settings backup created:', settings);

    } catch (error) {
        console.error('Error creating footer settings backup:', error);
        settings = {};
    }

    return settings;
}

/**
 * Restore user's custom footer data when same design is reselected
 */
function restoreUserCustomData() {
    return new Promise((resolve, reject) => {
        let iframeDocument = Utils.getDocument();
        if (!iframeDocument) {
            console.error('Cannot restore data - iframe document not found');
            reject(new Error('Iframe document not found'));
            return;
        }

        try {
            console.log('Restoring user custom footer data...');

            // Restore footer content
            if (window.originalFooterContent) {
                // Restore footer top section
                if (window.originalFooterContent.footerTop) {
                    let footerTop = iframeDocument.querySelector('#page-footer .footer-top-area-section-7');
                    if (footerTop) {
                        footerTop.style.display = 'block';
                        footerTop.innerHTML = window.originalFooterContent.footerTop;
                    } else {
                        // Create new footer top section
                        let footerMain = iframeDocument.querySelector('#page-footer .footer-mainsection-wrapper');
                        if (footerMain && footerMain.parentNode) {
                            let newFooterTop = iframeDocument.createElement('div');
                            newFooterTop.className = 'section-footer-design-7 footer-top-area-section-7';
                            newFooterTop.innerHTML = window.originalFooterContent.footerTop;
                            footerMain.parentNode.insertBefore(newFooterTop, footerMain);
                        }
                    }
                }

                // Restore footer main section
                if (window.originalFooterContent.footerMain) {
                    let footerMain = iframeDocument.querySelector('#page-footer .footer-mainsection-wrapper');
                    if (footerMain) {
                        footerMain.style.display = 'block';
                        footerMain.innerHTML = window.originalFooterContent.footerMain;
                    }
                }

                // Restore footer secondary section
                if (window.originalFooterContent.footerSecondary) {
                    let footerSecondary = iframeDocument.querySelector('#page-footer .footer-secondarysection-wrapper');
                    if (footerSecondary) {
                        footerSecondary.style.display = 'block';
                        footerSecondary.innerHTML = window.originalFooterContent.footerSecondary;
                    }
                }
                console.log('Footer content restored successfully');
            }

            // Restore footer settings
            if (window.originalFooterSettings) {
                restoreFooterSettings(window.originalFooterSettings);
                console.log('Footer settings restored successfully');
            }

            // $(SELECTOR.BACKGROUNDIMG).val(window.backgroundimgitemid);

            // Apply background image to preview after restoration
            // applyBackgroundImgURL();

            // Scroll to footer to make it visible
            scrollToFooter(iframeDocument);

            // Sync TinyMCE content after restoration
            syncTinyMCEContentAfterDesign();

            console.log('User custom footer data restored completely');
            resolve();
        } catch (error) {
            console.error('Error restoring user custom data:', error);
            reject(error);
        }
    });
}

/**
 * Restore footer settings from backup
 * @param {Object} settings Backup settings object
 */
function restoreFooterSettings(settings) {
    try {
        // Restore colors
        if (settings.colors) {
            Object.keys(settings.colors).forEach(colorKey => {
                const colorValue = settings.colors[colorKey];
                if (colorValue) {
                    const $target = $(`[name="${colorKey}"]`);
                    if ($target.length > 0) {
                        $target.spectrum('set', colorValue).trigger('color.changed');
                    }
                }
            });
        }

        // Restore fonts
        if (settings.fonts) {
            Object.keys(settings.fonts).forEach(fontKey => {
                const fontValue = settings.fonts[fontKey];
                if (fontValue) {
                    const $target = $(`[name="${fontKey}"]`);
                    if ($target.length > 0) {
                        $target.val(fontValue).trigger('change');
                    }
                }
            });
        }

        // Restore social media settings (global)
        if (settings.social) {
            Object.keys(settings.social).forEach(socialKey => {
                const socialValue = settings.social[socialKey];
                if (socialValue) {
                    const $target = $(`[name="${socialKey}"]`);
                    if ($target.length > 0) {
                        $target.val(socialValue).trigger('change');
                    }
                }
            });
        }

        // Restore social media settings for each column
        for (let i = 1; i <= 5; i++) {
            const columnKey = `socialColumn${i}`;
            if (settings[columnKey]) {
                const columnSocialSettings = settings[columnKey];
                Object.keys(columnSocialSettings).forEach(socialKey => {
                    const socialValue = columnSocialSettings[socialKey];
                    if (socialValue) {
                        const $target = $(`[name="${socialKey}${i}"]`);
                        if ($target.length > 0) {
                            $target.val(socialValue).trigger('change');
                        }
                    }
                });
            }
        }

        // Restore footer-secondary social media settings
        if (settings.socialSecondary) {
            Object.keys(settings.socialSecondary).forEach(socialKey => {
                const socialValue = settings.socialSecondary[socialKey];
                if (socialValue) {
                    const $target = $(`[name="${socialKey}"]`);
                    if ($target.length > 0) {
                        $target.val(socialValue).trigger('change');
                    }
                }
            });
        }

        // Restore advance settings
        if (settings.advance) {
            Object.keys(settings.advance).forEach(advanceKey => {
                const advanceValue = settings.advance[advanceKey];
                if (advanceValue) {
                    const $target = $(`[name="${advanceKey}"]`);
                    if ($target.length > 0) {
                        $target.val(advanceValue).trigger('change');
                    }
                }
            });
        }

        // Restore column settings
        for (let i = 1; i <= 5; i++) {
            const columnKey = `column${i}`;
            if (settings[columnKey]) {
                const columnSettings = settings[columnKey];
                Object.keys(columnSettings).forEach(settingKey => {
                    const settingValue = columnSettings[settingKey];
                    if (settingValue) {
                        const $target = $(`[name="${settingKey}"]`);
                        if ($target.length > 0) {
                            if (settingKey === 'socialmediaicon') {
                                // Handle checkbox
                                $target.prop('checked', settingValue).trigger('change');
                            } else if (settingKey === 'social') {
                                // Handle multiple select
                                try {
                                    const socialArray = JSON.parse(settingValue);
                                    $target.val(socialArray).trigger('change');
                                } catch (e) {
                                    $target.val(settingValue).trigger('change');
                                }
                            } else if (settingKey.includes('menu')) {
                                applySettingValue(settingKey, settingValue);
                            } else {
                                $target.val(settingValue).trigger('change');
                            }
                        }
                    }
                });
            }
        }

        // Restore secondary settings
        if (settings.secondary) {
            Object.keys(settings.secondary).forEach(secondaryKey => {
                const secondaryValue = settings.secondary[secondaryKey];
                if (secondaryValue !== null && secondaryValue !== undefined) {
                    const $target = $(`[name="${secondaryKey}"]`);
                    if ($target.length > 0) {
                        if ($target.attr('type') === 'checkbox') {
                            $target.prop('checked', secondaryValue).trigger('change');
                        } else {
                            $target.val(secondaryValue).trigger('change');
                        }
                    }
                }
            });
        }

        // Restore email subscribe settings
        if (settings.emailSubscribe) {
            Object.keys(settings.emailSubscribe).forEach(emailKey => {
                const emailValue = settings.emailSubscribe[emailKey];
                if (emailValue) {
                    const $target = $(`[name="${emailKey}"]`);
                    if ($target.length > 0) {
                        if (emailKey.includes('color')) {
                            $target.spectrum('set', emailValue).trigger('color.changed');
                        } else {
                            $target.val(emailValue).trigger('change');
                        }
                    }
                }
            });
        }

        // Restore footer background image settings
        if (settings.footerbackground) {
            Object.keys(settings.footerbackground).forEach(backgroundKey => {
                const backgroundValue = settings.footerbackground[backgroundKey];
                if (backgroundValue !== null && backgroundValue !== undefined) {
                    const $target = $(`[name="${backgroundKey}"]`);
                    if ($target.length > 0) {
                        if ($target.attr('type') === 'checkbox') {
                            $target.prop('checked', backgroundValue).trigger('change');
                        } else {
                            $target.val(backgroundValue).trigger('change');
                        }
                    }
                }
            });
        }

        // Restore footer widget logo settings
        if (settings.footerWidgetLogo) {
            Object.keys(settings.footerWidgetLogo).forEach(logoKey => {
                const logoValue = settings.footerWidgetLogo[logoKey];
                if (logoValue !== null && logoValue !== undefined) {
                    const $target = $(`[name="${logoKey}"]`);
                    if ($target.length > 0) {
                        if ($target.attr('type') === 'checkbox') {
                            $target.prop('checked', logoValue).trigger('change');
                        } else {
                            $target.val(logoValue).trigger('change');
                        }
                    }
                }
            });
        }

    } catch (error) {
        console.error('Error restoring footer settings:', error);
    }
}



/**
 * Helper function to get setting value safely
 * @param {string} settingNameOrSelector Name of the setting or selector
 * @return {*} Setting value or null if not found
 */
function getSettingValue(settingNameOrSelector) {
    try {
        // Check if it's a selector (starts with [name=)
        const selector = settingNameOrSelector.startsWith('[name=') ? settingNameOrSelector : `[name="${settingNameOrSelector}"]`;
        const $element = $(selector);

        if ($element.length > 0) {
            // Check if it's a spectrum color picker
            if ($element.hasClass('spectrum') || $element.data('spectrum')) {
                return $element.spectrum('get').toString();
            }
            // Check if it's a checkbox
            if ($element.attr('type') === 'checkbox') {
                return $element.is(':checked');
            }
            // For other input types
            return $element.val();
        }
        return null;
    } catch (error) {
        console.log(`Could not get value for setting: ${settingNameOrSelector}`, error);
        return null;
    }
}

/**
 * Apply footer colors from design data using smartcolor.js logic
 * @param {Object} colors Footer color configuration
 */
function applyFooterColors(colors) {

    // Apply each color directly from JSON to its corresponding setting
    // Following the exact same pattern as smartcolor.js
    Object.keys(colors).forEach(colorKey => {
        const colorValue = colors[colorKey];
        if (colorValue) {
            const targetSetting = `[name="${colorKey}"]`;
            const $target = $(targetSetting);

            if ($target.length > 0) {
                console.log(`Setting ${colorKey} to ${colorValue}`);

                // Use the exact same method as smartcolor.js
                // All color elements should use spectrum('set') and trigger 'color.changed'
                $target.spectrum('set', colorValue).trigger('color.changed');

            } else {
                console.log(`Setting ${colorKey} not found in DOM`);
            }
        }
    });

}


/**
 * Scroll the iframe content to make the footer visible
 * @param {Document} iframeDocument The iframe document
 */
function scrollToFooter(iframeDocument) {
    try {
        // Find the footer element
        let footer = iframeDocument.querySelector('#page-footer');
        if (footer) {
            // Scroll the footer into view with smooth animation
            footer.scrollIntoView({
                behavior: 'smooth',
                block: 'end',
                inline: 'nearest'
            });

            // Alternative: Scroll to bottom of the page if scrollIntoView doesn't work
            setTimeout(() => {
                if (iframeDocument.documentElement) {
                    iframeDocument.documentElement.scrollTop = iframeDocument.documentElement.scrollHeight;
                } else if (iframeDocument.body) {
                    iframeDocument.body.scrollTop = iframeDocument.body.scrollHeight;
                }
            }, 100);

            console.log('Scrolled to footer successfully');
        } else {
            console.log('Footer element not found for scrolling');
        }
    } catch (error) {
        console.error('Error scrolling to footer:', error);

        // Fallback: Try to scroll the iframe itself
        try {
            let iframe = document.querySelector('iframe[src*="customizer"]');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.scrollTo(0, iframe.contentWindow.document.body.scrollHeight);
            }
        } catch (fallbackError) {
            console.error('Fallback scrolling also failed:', fallbackError);
        }
    }
}



function getActiveFooterDesign() {
    return $(SELECTOR.FOOTERDESIGNSELECTOR+':checked').data('flayout');
}

