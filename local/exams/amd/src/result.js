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
 * Single-exam result charts: percentile gauge, comparison, outcome donut,
 * topic radar, time-per-question, speed-vs-accuracy scatter, distribution.
 *
 * @module     local_exams/result
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

            if (d.percentile !== null && d.percentile !== undefined) {
                Charts.gauge('exui-percentile', d.percentile, 'percentile');
            }

            if (d.comparison) {
                var c = d.comparison;
                Charts.bar('exui-comparison',
                    ['You', 'Average', 'Median', 'Topper'],
                    [{label: 'Marks', data: [c.you, c.mean, c.median, c.topper]}],
                    {plugins: {legend: {display: false}}}
                );
            }

            if (d.outcome) {
                Charts.doughnut('exui-outcome',
                    ['Correct', 'Partial', 'Incorrect', 'Unanswered'],
                    d.outcome,
                    ['#16a34a', '#d97706', '#dc2626', '#94a3b8']
                );
            }

            if (d.topics && d.topics.labels && d.topics.labels.length) {
                Charts.radar('exui-topics', d.topics.labels, [{label: 'Score %', data: d.topics.data}]);
            }

            if (d.qtime && d.qtime.labels && d.qtime.labels.length) {
                Charts.bar('exui-qtime', d.qtime.labels, [{label: 'Seconds', data: d.qtime.data}],
                    {plugins: {legend: {display: false}}});
            }

            if (d.scatter && d.scatter.length) {
                Charts.scatter('exui-scatter', d.scatter, {
                    scales: {
                        x: {title: {display: true, text: 'Time (s)'}},
                        y: {title: {display: true, text: 'Score %'}, min: 0, max: 100}
                    },
                    plugins: {legend: {display: false}}
                });
            }

            if (d.distribution && d.distribution.length) {
                Charts.bar('exui-distribution',
                    d.distribution.map(function(b) {
                        return b.label;
                    }),
                    [{label: 'Students', data: d.distribution.map(function(b) {
                        return b.count;
                    })}],
                    {plugins: {legend: {display: false}}}
                );
            }
        }
    };
});
