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
 * Badge course component.
 *
 * @module     format_ludilearn/local/badge/course
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
            BRONZE_COUNT: `[data-for='ludilearn-course-section-bronzecount']`,
            BRONZE_IMG: `[data-for='ludilearn-course-section-bronzeimg']`,
            SILVER_COUNT: `[data-for='ludilearn-course-section-silvercount']`,
            SILVER_IMG: `[data-for='ludilearn-course-section-silverimg']`,
            GOLD_COUNT: `[data-for='ludilearn-course-section-goldcount']`,
            GOLD_IMG: `[data-for='ludilearn-course-section-goldimg']`,
            COMPLETION_COUNT: `[data-for='ludilearn-course-section-completioncount']`,
            COMPLETION_IMG: `[data-for='ludilearn-course-section-completionimg']`,
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
            {watch: `sections.bronzecount:updated`, handler: this._refreshBronzeCount},
            {watch: `sections.silvercount:updated`, handler: this._refreshSilverCount},
            {watch: `sections.goldcount:updated`, handler: this._refreshGoldCount},
            {watch: `sections.completioncount:updated`, handler: this._refreshCompletionCount},
        ];
    }

    _refreshBronzeCount({element}) {
        const target = this.getElement(this.selectors.BRONZE_COUNT, element.id);
        const targetimg = this.getElement(this.selectors.BRONZE_IMG, element.id);
        if (!target) {
            return;
        }

        // Update bronze badge.
        if (element.bronzecount > 0) {
            target.innerHTML = element.bronzecount;
            target.className = 'text-center mb-1 badge-number badge-number-bronze spinning';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_bronze.svg';
        } else {
            target.innerHTML = 0;
            target.className = 'text-center mb-1 badge-number';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_bronze_none.svg';
        }
    }

    _refreshSilverCount({element}) {
        const target = this.getElement(this.selectors.SILVER_COUNT, element.id);
        const targetimg = this.getElement(this.selectors.SILVER_IMG, element.id);
        if (!target) {
            return;
        }

        // Update silver badge.
        if (element.silvercount > 0) {
            target.innerHTML = element.silvercount;
            target.className = 'text-center mb-1 badge-number badge-number-silver spinning';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_silver.svg';
        } else {
            target.innerHTML = 0;
            target.className = 'text-center mb-1 badge-number';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_silver_none.svg';
        }
    }

    _refreshGoldCount({element}) {
        const target = this.getElement(this.selectors.GOLD_COUNT, element.id);
        const targetimg = this.getElement(this.selectors.GOLD_IMG, element.id);
        if (!target) {
            return;
        }

        // Update gold badge.
        if (element.goldcount > 0) {
            target.innerHTML = element.goldcount;
            target.className = 'text-center mb-1 badge-number badge-number-gold spinning';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_gold.svg';
        } else {
            target.innerHTML = 0;
            target.className = 'text-center mb-1 badge-number';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_gold_none.svg';
        }
    }

    _refreshCompletionCount({element}) {
        const target = this.getElement(this.selectors.COMPLETION_COUNT, element.id);
        const targetimg = this.getElement(this.selectors.COMPLETION_IMG, element.id);
        if (!target) {
            return;
        }

        // Update completion badge.
        if (element.completioncount > 0) {
            target.innerHTML = element.completioncount;
            target.className = 'text-center mb-1 badge-number badge-number-bronze spinning';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_completion.svg';
        } else {
            target.innerHTML = 0;
            target.className = 'text-center mb-1 badge-number';
            targetimg.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_completion_none.svg';
        }
    }
}