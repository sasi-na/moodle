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
 * Progress course component.
 *
 * @module     format_ludilearn/local/progress/course
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';
import * as config from 'core/config';

export default class extends BaseComponent {

    create(descriptor) {
        this.courseid = descriptor.data.courseid;

        this.selectors = {
            PROGRESSION: `[data-for='ludilearn-course-section-progression']`,
            IMG_PLANET: `[data-for='ludilearn-course-section-img-planet']`
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
            {watch: `sections.progression:updated`, handler: this._refreshProgression},
            {watch: `sections.completed:updated`, handler: this._refreshCompleted},
        ];
    }

    _refreshProgression({element}) {
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.PROGRESSION, element.id);
        if (!target) {
            return;
        }

        // Update the score
        target.innerHTML = element.progression + '%';
    }

    _refreshCompleted({element}) {
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.IMG_PLANET, element.id);
        if (!target) {
            return;
        }
        let namefile = '';
        if (element.completed) {
            namefile = 'progression-step-' + element.planetenumber + '.svg';
            target.className = "ludilearn-img img-responsive mb-1 d-block mx-auto progression-planet progression-planet-max";
        } else {
            namefile = 'course-planet-max-0' + element.planetenumber + '.svg';
            target.className = "ludilearn-img img-responsive mb-1 d-block mx-auto progression-planet progression-planet-inprogress";
        }
        // Change img src.
        target.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/progress/' + namefile;
    }
}