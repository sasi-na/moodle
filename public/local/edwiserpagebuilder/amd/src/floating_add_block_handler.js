/* eslint-disable no-undef */
/* eslint-disable no-unused-vars */
/* eslint-disable no-console */
/* eslint-disable jsdoc/require-jsdoc*/
/* eslint-disable jsdoc/require-jsdoc*/
/* eslint-disable jsdoc/require-jsdoc*/
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
 * @module     local_edwiserpagebuilder/floating_add_block_handler
 * @copyright (c) 2020 WisdmLabs (https://wisdmlabs.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/toast', 'core/str'], function($, Ajax, Toast, Str) {
    /**
     * Selectors
     */
    var SELECTOR = {
        ADDBLOCKFLOATMENU: '#add-block-float-menu ',
        ADDBLOCKSPLITICONBTN: '#add-block-float-menu .add-block-split-icon-btn',
        FLOATADDBLOCKBUTTON: '#add-block-float-menu .floating-add-block-button',
        DNONE: 'd-none'
    };

    // ****** IMPORTANT ******
    // Do not change the sequence.
    // If you want to add new strings here, add it at the bottom.
    // Do not remove any string from the array.
    // There is no way we can revert back if sequence is changed.
    // ****** IMPORTANT ******
    const strings = [
        {key: 'floataddblockbtnregionselectionmsg', component: 'local_edwiserpagebuilder'},
    ];

    var LANGS; // Gloabl variable to store languages.

    // Functionality to fetch strings.
    const fetchLanguages = () => {
        Str.get_strings(strings).then(function(results) {
            LANGS = results;
            return results;
        });
    };


    var elements = ['.side-pre-link', '.side-bottom-link', '.side-top-link', '.content-link', '.full-width-top-link', '.full-bottom-link'];
    var activeregionlink = '.full-width-top-link';
    var blockregions = [];
    var blockinviewport = [];
    var getSelectedregion = (blocksectiondata, currentitem) => {
        var data = '';
        blocksectiondata.forEach(element => {
            if ($(currentitem).attr('data-region') == element.regionid) {
                data = element;
                return false;
            }
        });
        return data;
    };
    var callAllFunctions = (blocksectiondata, currentclickedelement, scroll = false) => {
        var currentsectionlink = getSelectedregion(blocksectiondata, currentclickedelement);
        // Safety check: ensure we have a valid region before proceeding
        if (!currentsectionlink || typeof currentsectionlink !== 'object' || !currentsectionlink.regionaddblockbutton) {
            console.warn('callAllFunctions: Invalid region data, skipping');
            return;
        }
        if (scroll && currentsectionlink) { //changes
            scrolltoelement(currentsectionlink);
        }
        activeregionclassaddition(currentclickedelement);
        getAaddblockData(currentsectionlink.regionaddblockbutton);
    };

    var getAaddblockData = (currentactiveregoninfo) => {
        var html = $(currentactiveregoninfo).html();
        $(SELECTOR.FLOATADDBLOCKBUTTON).attr({
            'href': $(html).attr('href'),
            // 'data-key': $(html).attr('data-key'),    // changes
            'data-url': $(html).attr('data-url'),
            'id': $(html).attr('id'),
        });
    };

    var activeregionclassaddition = (currentactiveregionlink) => {
        $(SELECTOR.ADDBLOCKFLOATMENU).find('.dropdown-item').removeClass('activeregion');
        $(currentactiveregionlink).addClass('activeregion');
    };
    var scrolltoelement = (element) => {
        var targetOffset = '';
        if (element.regionid == '#block-region-side-pre' || element.regionid == '#side-post') {
            if (!$('.drawer-right').hasClass('show')) {
                $('.drawer-toggler.drawer-right-toggle [data-action="toggle"]').click();
            }
        } else {
            var $target = $(`[data-blockregion="${element.region}"]`);

            // Check if target element exists
            if ($target.length === 0) {
                return;
            }
            // Check if target is already visible in viewport - if yes, don't scroll
            if (isElementInViewport($target[0])) {
                return;
            }
            // Target is not visible, proceed with scrolling
            targetOffset = $target.offset()?.top;
            var scrollPosition = targetOffset - ($(window).height() / 2) + ($target.height() / 2);
            $("#page").animate({ scrollTop: scrollPosition }, 1000);
        }
        var msg = M.util.get_string('floataddblockbtnregionselectionmsg', 'local_edwiserpagebuilder', element.regionname);
        Toast.add(msg, {
            delay: 3000,
            closeButton: true,
            type: 'warning edw_toast edw_regionselection_toast'
        });
        // $(SELECTOR.FLOATADDBLOCKBUTTON).tooltip('hide').attr('data-original-title', 'block will be added in' + ' ' + element.regionname + ' ' + 'region').tooltip('show');
    };

    // Function to check if the element is in the viewport
    function isElementInViewport(elem) {
        var $elem = $(elem);
        var $window = $(window);

        var docViewTop = $window.scrollTop();
        var docViewBottom = docViewTop + $window.height();

        var elemTop = $elem.offset().top;
        var elemBottom = elemTop + $elem.height();

        return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
    }

    // Function to calculate the distance between two points
    function getDistance(x1, y1, x2, y2) {
        var dx = x1 - x2;
        var dy = y1 - y2;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function isViewportAtTop() {
        var scrollPosition = $(window).scrollTop();
        var threshold = 10; // Adjust this threshold as needed

        return scrollPosition <= threshold;
    }

    // Function to find the ID closest to the viewport for a given selector
    function findClosestID(ids) {
        var viewportCenterX = $(window).scrollLeft() + $(window).width() / 2;
        var viewportCenterY = $(window).scrollTop() + $(window).height() / 2;

        var closestID = null;
        var closestDistance = Infinity;

        // Loop through each ID in the array
        $.each(ids, function (index, id) {
            var $element = $(id);

            // Check if the element exists
            if ($element.length > 0) {
                var elementOffset = $element.offset();
                var elementCenterX = elementOffset.left + $element.width() / 2;
                var elementCenterY = elementOffset.top + $element.height() / 2;

                var distance = getDistance(
                    viewportCenterX,
                    viewportCenterY,
                    elementCenterX,
                    elementCenterY
                );

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestID = id;
                }
            }
        });

        return closestID;
    }
    const registerCommonEvents = () => {
        $(SELECTOR.ADDBLOCKSPLITICONBTN).click(function () {
            $(`${SELECTOR.ADDBLOCKSPLITICONBTN} .edw-icon-Down-Arrow`).toggleClass(SELECTOR.DNONE);
            $(`${SELECTOR.ADDBLOCKSPLITICONBTN} .edw-icon-UpArrow`).toggleClass(SELECTOR.DNONE);
        });

        // $(document).on("click", elements[0], function () {   // changes
        //     callAllFunctions(blocksectiondata, this, true);
        // });
        // $(document).on("click", elements[1], function () {
        //     callAllFunctions(blocksectiondata, this, true);
        // });
        // $(document).on("click", elements[2], function () {
        //     callAllFunctions(blocksectiondata, this, true);
        // });
        // $(document).on("click", elements[3], function () {
        //     callAllFunctions(blocksectiondata, this, true);
        // });
        // $(document).on("click", elements[4], function () {
        //     callAllFunctions(blocksectiondata, this, true);
        // });
        // $(document).on("click", elements[5], function () {
        //     callAllFunctions(blocksectiondata, this, true);
        // });

        // $(document).on("click", elements.join(","), function() {
        //     callAllFunctions(blocksectiondata, this, true);
        // });
        $(document).on("click", ".edw-theme-region-link", function(event) {
            event.preventDefault();
            event.stopPropagation();
            callAllFunctions(blocksectiondata, this, true);
        });

        // $(window).on('resize scroll', function () {
        //     blockinviewport = [];
        //     var closestID = findClosestID(blockregions);
        //     blockinviewport.push(closestID);
        //     if (blockinviewport.length > 0) {
        //         callAllFunctions(blocksectiondata, `a[data-region="${blockinviewport[0]}"]`);
        //     }
        // });
    };

    return {
        init: function () {
            $(document).ready(function () {
                fetchLanguages();

                // Blocksectiondata is comming from data_for_js
                registerCommonEvents();
                // Filter blocksectiondata to only include regions that exist in the DOM
                blocksectiondata = blocksectiondata.filter(function(regionData) {
                    var regionExists = false;

                    // Check if element with data-blockregion attribute exists (matches the region value)
                    if (regionData.region) {
                        regionExists = $('[data-blockregion="' + regionData.region + '"]').length > 0;
                    }

                    // Also check if element with regionid exists (fallback check)
                    if (!regionExists && regionData.regionid) {
                        // Remove # from regionid if present and check by ID
                        var regionId = regionData.regionid.replace(/^#/, '');
                        regionExists = $('#' + regionId).length > 0;

                        // Also try direct selector with regionid
                        if (!regionExists) {
                            regionExists = $(regionData.regionid).length > 0;
                        }
                    }

                    return regionExists;
                });

                if(blocksectiondata.length === 0) {
                    $('#add-block-float-menu').addClass('d-none');
                }
                // Create array of valid regionids from filtered blocksectiondata
                var validRegionIds = [];
                blocksectiondata.forEach(function(regionData) {
                    if (regionData.regionid) {
                        validRegionIds.push(regionData.regionid);
                    }
                });

                // Hide .edw-theme-region-link elements whose data-region doesn't exist in filtered data
                $('.edw-theme-region-link').each(function() {
                    var dataRegion = $(this).attr('data-region');
                    if (dataRegion && validRegionIds.indexOf(dataRegion) === -1) {
                        $(this).addClass('d-none');
                    }
                });
                // Check if each div is in the viewport
                blocksectiondata.forEach(function (divID) {
                    var id = divID.regionid;
                    blockregions.push(id);
                });
                var closestID = findClosestID(blockregions);
                var selectedRegion = null;

                // Ensure at least one region is selected
                if (closestID !== null && closestID !== undefined) {
                    // Use the closest region found
                    selectedRegion = closestID;
                } else if (blocksectiondata.length > 0) {
                    // If no closest region found, use the first available region
                    selectedRegion = blocksectiondata[0].regionid;
                } else {
                    // Fallback to default region if no regions available
                    selectedRegion = '#region-fullwidthtop-blocks';
                }

                // Verify the selected region link exists in DOM before using it
                var regionSelector = `a[data-region="${selectedRegion}"]`;
                var regionLink = $(regionSelector);

                // If the selected region link doesn't exist, try to find the first available one
                if (regionLink.length === 0 && blocksectiondata.length > 0) {
                    // Try each region until we find one that exists
                    for (var i = 0; i < blocksectiondata.length; i++) {
                        var testSelector = `a[data-region="${blocksectiondata[i].regionid}"]`;
                        if ($(testSelector).length > 0) {
                            selectedRegion = blocksectiondata[i].regionid;
                            regionSelector = testSelector;
                            break;
                        }
                    }
                }

                // Final fallback if still no region found
                if ($(regionSelector).length === 0) {
                    selectedRegion = '#region-fullwidthtop-blocks';
                    regionSelector = `a[data-region="${selectedRegion}"]`;
                }

                blockinviewport.push(selectedRegion);

                // Always call with a valid region element (not selector string)
                // Get the actual DOM element from the selector
                var regionElement = $(regionSelector);
                if (regionElement.length > 0) {
                    // Pass the actual DOM element, not the selector string
                    callAllFunctions(blocksectiondata, regionElement[0], false);
                } else {
                    // If still no element found, try to get first available region link
                    var firstAvailableLink = $('.edw-theme-region-link:not(.d-none)').first();
                    if (firstAvailableLink.length > 0) {
                        callAllFunctions(blocksectiondata, firstAvailableLink[0], false);
                    }
                }
            });
        },
    };
});
