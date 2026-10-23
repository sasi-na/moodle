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
 * subcategory_select - dynamically filters sub-category dropdown based on selected category.
 *
 * @package   local_helpdesk
 * @copyright 2026 Helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery"], function($) {
    return {
        /**
         * Initialise subcategory selector.
         *
         * @param {Object} subcategoryData  Object mapping parentid => [{id, name}, ...]
         * @param {number} selectedSubcatId Currently selected subcategory id (for edit mode)
         */
        init: function(subcategoryData, selectedSubcatId) {
            var categorySelect = $("select[name='categoryid']");
            var subcategorySelect = $("select[name='subcategoryid']");
            var subcategoryWrapper = subcategorySelect.closest(".fitem");

            selectedSubcatId = selectedSubcatId || 0;

            /**
             * Rebuild the sub-category <select> options based on the selected category.
             */
            function updateSubcategories() {
                var selectedCategoryId = parseInt(categorySelect.val(), 10);
                var subcats = subcategoryData[selectedCategoryId] || [];

                // Remove all options except the first placeholder.
                subcategorySelect.find("option:not(:first)").remove();

                if (subcats.length > 0) {
                    $.each(subcats, function(i, sub) {
                        var option = $("<option>").val(sub.id).text(sub.name);
                        if (sub.id === selectedSubcatId) {
                            option.prop("selected", true);
                        }
                        subcategorySelect.append(option);
                    });
                    subcategoryWrapper.show();
                } else {
                    subcategoryWrapper.hide();
                    subcategorySelect.val(0);
                }
            }

            categorySelect.on("change", function() {
                selectedSubcatId = 0; // Reset selection on category change.
                updateSubcategories();
            });

            // Initialise on page load.
            updateSubcategories();
        }
    };
});

