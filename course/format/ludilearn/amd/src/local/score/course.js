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
 * Score course component.
 *
 * @module     format_ludilearn/local/score/course
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';

export default class extends BaseComponent {

    create(descriptor) {
        this.courseid = descriptor.data.courseid;

        this.selectors = {
            SCORE: `[data-for='ludilearn-course-section-score']`,
            MAXSCORE: `[data-for='ludilearn-course-section-maxscore']`,
        };
    }

    static init(target, reactive, data) {
        return new this({
            element: document.getElementById(target),
            reactive: reactive,
            data: data,
        });
    }

    stateReady() {
        // Dispach auto refresh.
        this.reactive.dispatch('syncCourseAuto', this.courseid);
    }

    getWatchers() {
        return [
            {watch: `sections.score:updated`, handler: this._refreshScore},
            {watch: `sections.maxscore:updated`, handler: this._refreshMaxScore}
        ];
    }

    _refreshScore({element}) {
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.SCORE, element.id);
        if (!target) {
            return;
        }

        // Update the score
        target.innerHTML = element.score;
    }

    _refreshMaxScore({element}) {
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.MAXSCORE, element.id);
        if (!target) {
            return;
        }

        // Update the score
        target.innerHTML = element.score;
    }

}