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
 * Faculty analytics charts — mean-by-exam on the dashboard, and distribution /
 * item facility / topic charts on the per-exam analysis view.
 *
 * @module     local_exams/faculty
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
            var d = readData();
            if (d.means && d.means.labels && d.means.labels.length) {
                Charts.bar('exui-means', d.means.labels, [{label: 'Mean %', data: d.means.data}],
                    {plugins: {legend: {display: false}}, scales: {y: {min: 0, max: 100}}});
            }
        },

        initExam: function() {
            var d = readData();
            if (d.distribution && d.distribution.length) {
                Charts.bar('exui-distribution',
                    d.distribution.map(function(b) {
                        return b.label;
                    }),
                    [{label: 'Students', data: d.distribution.map(function(b) {
                        return b.count;
                    })}],
                    {plugins: {legend: {display: false}}});
            }
            if (d.items && d.items.labels && d.items.labels.length) {
                Charts.bar('exui-items', d.items.labels, [{label: 'Facility %', data: d.items.data}],
                    {plugins: {legend: {display: false}}, scales: {y: {min: 0, max: 100}}});
            }
        }
    };
});
