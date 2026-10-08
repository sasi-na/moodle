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
 * Thin charting helpers over Moodle's bundled Chart.js (core/chartjs).
 *
 * Theme-independent: colours are read from the .exui CSS custom properties on
 * the host element so charts match light/dark mode automatically.
 *
 * @module     local_exams/chart_factory
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs'], function(Chart) {

    /**
     * Read a CSS custom property resolved on the document root (or .exui scope).
     *
     * @param {String} name The custom property name, e.g. '--exui-primary'.
     * @param {String} fallback Value to use if unresolved.
     * @return {String} The resolved colour.
     */
    var token = function(name, fallback) {
        var scope = document.querySelector('.exui') || document.documentElement;
        var v = getComputedStyle(scope).getPropertyValue(name);
        return (v && v.trim()) || fallback;
    };

    var palette = function() {
        return [
            token('--exui-primary', '#4f46e5'),
            token('--exui-success', '#16a34a'),
            token('--exui-warn', '#d97706'),
            token('--exui-danger', '#dc2626'),
            token('--exui-info', '#0ea5e9')
        ];
    };

    var baseOptions = function(extra) {
        var grid = token('--exui-border', '#e6e9f0');
        var text = token('--exui-text-muted', '#5b6676');
        var opts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {legend: {labels: {color: text}}},
            scales: {
                x: {ticks: {color: text}, grid: {color: grid}},
                y: {ticks: {color: text}, grid: {color: grid}}
            }
        };
        return Object.assign(opts, extra || {});
    };

    var ctx = function(elOrId) {
        var el = (typeof elOrId === 'string') ? document.getElementById(elOrId) : elOrId;
        return el ? el.getContext('2d') : null;
    };

    return {
        /**
         * Gauge rendered as a half-doughnut (no extra library needed).
         *
         * @param {String|HTMLElement} el Canvas element or its id.
         * @param {Number} value 0-100 value to display.
         * @param {String} label Centre label text.
         * @return {Object|null} The Chart instance.
         */
        gauge: function(el, value, label) {
            var c = ctx(el);
            if (!c) {
                return null;
            }
            var v = Math.max(0, Math.min(100, value));
            return new Chart(c, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [v, 100 - v],
                        backgroundColor: [token('--exui-primary', '#4f46e5'), token('--exui-border', '#e6e9f0')],
                        borderWidth: 0,
                        circumference: 180,
                        rotation: 270
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {legend: {display: false}, tooltip: {enabled: false}}
                },
                plugins: [{
                    id: 'centerlabel',
                    afterDraw: function(chart) {
                        var x = chart.getDatasetMeta(0).data[0];
                        if (!x) {
                            return;
                        }
                        var cx = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                        var cy = chart.chartArea.bottom;
                        var d = chart.ctx;
                        d.save();
                        d.textAlign = 'center';
                        d.fillStyle = token('--exui-text', '#1b2330');
                        d.font = '700 22px Inter, system-ui, sans-serif';
                        d.fillText(Math.round(v) + (label ? '' : '%'), cx, cy - 6);
                        if (label) {
                            d.fillStyle = token('--exui-text-muted', '#5b6676');
                            d.font = '500 11px Inter, system-ui, sans-serif';
                            d.fillText(label, cx, cy + 10);
                        }
                        d.restore();
                    }
                }]
            });
        },

        bar: function(el, labels, datasets, opts) {
            var c = ctx(el);
            if (!c) {
                return null;
            }
            var cols = palette();
            datasets.forEach(function(ds, i) {
                ds.backgroundColor = ds.backgroundColor || cols[i % cols.length];
                ds.borderRadius = 6;
            });
            return new Chart(c, {type: 'bar', data: {labels: labels, datasets: datasets}, options: baseOptions(opts)});
        },

        line: function(el, labels, datasets, opts) {
            var c = ctx(el);
            if (!c) {
                return null;
            }
            var cols = palette();
            datasets.forEach(function(ds, i) {
                ds.borderColor = ds.borderColor || cols[i % cols.length];
                ds.backgroundColor = ds.backgroundColor || cols[i % cols.length];
                ds.tension = (ds.tension === null || ds.tension === undefined) ? 0.35 : ds.tension;
                ds.pointRadius = (ds.pointRadius === null || ds.pointRadius === undefined) ? 3 : ds.pointRadius;
            });
            return new Chart(c, {type: 'line', data: {labels: labels, datasets: datasets}, options: baseOptions(opts)});
        },

        radar: function(el, labels, datasets) {
            var c = ctx(el);
            if (!c) {
                return null;
            }
            var grid = token('--exui-border', '#e6e9f0');
            var text = token('--exui-text-muted', '#5b6676');
            var cols = palette();
            datasets.forEach(function(ds, i) {
                ds.borderColor = ds.borderColor || cols[i % cols.length];
                ds.backgroundColor = ds.backgroundColor || (cols[i % cols.length] + '33');
            });
            return new Chart(c, {
                type: 'radar',
                data: {labels: labels, datasets: datasets},
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {labels: {color: text}}},
                    scales: {r: {angleLines: {color: grid}, grid: {color: grid},
                        pointLabels: {color: text}, ticks: {display: false}}}
                }
            });
        },

        doughnut: function(el, labels, data, colors) {
            var c = ctx(el);
            if (!c) {
                return null;
            }
            return new Chart(c, {
                type: 'doughnut',
                data: {labels: labels, datasets: [{data: data, backgroundColor: colors || palette(), borderWidth: 0}]},
                options: {responsive: true, maintainAspectRatio: false, cutout: '62%',
                    plugins: {legend: {position: 'bottom', labels: {color: token('--exui-text-muted', '#5b6676')}}}}
            });
        },

        scatter: function(el, points, opts) {
            var c = ctx(el);
            if (!c) {
                return null;
            }
            return new Chart(c, {
                type: 'scatter',
                data: {datasets: [{data: points, backgroundColor: token('--exui-primary', '#4f46e5')}]},
                options: baseOptions(opts)
            });
        }
    };
});
