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
 * Student dashboard charts (trend + attendance) and theme toggle.
 *
 * @module     local_exams/dashboard
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_exams/chart_factory'], function(Charts) {

    var readData = function() {
        var el = document.getElementById('local-exams-data');
        if (!el) {
            return {};
        }
        try {
            return JSON.parse(el.textContent) || {};
        } catch (e) {
            return {};
        }
    };

    return {
        init: function() {
            var data = readData();

            if (data.trend && data.trend.labels && data.trend.labels.length) {
                Charts.line('exui-trend', data.trend.labels, [{
                    label: 'Percentage',
                    data: data.trend.data
                }], {scales: {y: {min: 0, max: 100}}});
            }

            if (data.attendance) {
                Charts.doughnut('exui-attendance',
                    ['Appeared', 'Not appeared'],
                    [data.attendance.present, data.attendance.absent]
                );
            }
        }
    };
});
