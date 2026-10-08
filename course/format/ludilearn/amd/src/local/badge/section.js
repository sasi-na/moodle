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
 * Badge section component.
 *
 * @module     format_ludilearn/local/badge/section
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';
import * as config from 'core/config';

export default class extends BaseComponent {

    create(descriptor) {
        this.sectionid = descriptor.data.sectionid;
        this.cms = descriptor.data.cms;
        this.selectors = {
            BRONZE_COUNT: `[data-for='ludilearn-section-bronzecount']`,
            BRONZE_IMG: `[data-for='ludilearn-section-bronzeimg']`,
            SILVER_COUNT: `[data-for='ludilearn-section-silvercount']`,
            SILVER_IMG: `[data-for='ludilearn-section-silverimg']`,
            GOLD_COUNT: `[data-for='ludilearn-section-goldcount']`,
            GOLD_IMG: `[data-for='ludilearn-section-goldimg']`,
            COMPLETION_COUNT: `[data-for='ludilearn-section-completioncount']`,
            COMPLETION_IMG: `[data-for='ludilearn-section-completionimg']`,
            SUMMARY: `[data-for='ludilearn-section-summary']`,
            CM: `[data-for='ludilearn-section-cm']`,
            CM_LABELTEXT: `[data-for='ludilearn-section-cm-labeltext']`,
            CM_NAME: `[data-for='ludilearn-section-cm-name']`,
            CM_BADGE: `[data-for='ludilearn-section-cm-badge']`,
            CM_COMPLETION: `[data-for='ludilearn-section-cm-completion']`,
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
        this.reactive.dispatch('syncSectionAuto', this.sectionid);
    }

    getWatchers() {
        return [
            {watch: 'currentsection.bronzecount:updated', handler: this._refreshBronzeCount},
            {watch: 'currentsection.silvercount:updated', handler: this._refreshSilverCount},
            {watch: 'currentsection.goldcount:updated', handler: this._refreshGoldCount},
            {watch: 'currentsection.completioncount:updated', handler: this._refreshCompletionCount},
            {watch: 'currentsection.summary:updated', handler: this._refreshSummary},
            {watch: `cms.labeltext:updated`, handler: this._refreshCMLabelText},
            {watch: `cms.badge:updated`, handler: this._refreshCMBadge},
            {watch: `cms.name:updated`, handler: this._refreshCMName},
            {watch: `cms.completion:updated`, handler: this._refreshCompletion},
        ];
    }

    _refreshBronzeCount({element}) {
        const target = this.getElement(this.selectors.BRONZE_COUNT);
        const targetimg = this.getElement(this.selectors.BRONZE_IMG);
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
        const target = this.getElement(this.selectors.SILVER_COUNT);
        const targetimg = this.getElement(this.selectors.SILVER_IMG);
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
        const target = this.getElement(this.selectors.GOLD_COUNT);
        const targetimg = this.getElement(this.selectors.GOLD_IMG);
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
        const target = this.getElement(this.selectors.COMPLETION_COUNT);
        const targetimg = this.getElement(this.selectors.COMPLETION_IMG);
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

    _refreshSummary({element}) {
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.SUMMARY);
        if (!target) {
            return;
        }

        // Update the summary.
        target.innerHTML = element.summary;
    }

    _refreshCMLabelText({element}) {
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.CM_LABELTEXT, element.id);
        if (!target) {
            return;
        }

        // Update the label text.
        target.innerHTML = element.labeltext;
    }

    _refreshCMName({element}) {
        // If the cm is not gamified, skip.
        if (!element.gamified) {
            return;
        }
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.CM_NAME, element.id);
        if (!target) {
            return;
        }

        // Update the name.
        target.innerHTML = element.name;
    }

    _refreshCMBadge({element}) {
        // If the cm is not gamified, skip.
        if (!element.gamified) {
            return;
        }

        const target = this.getElement(this.selectors.CM_BADGE, element.id);
        if (!target) {
            return;
        }

        // Update the badge image.
        target.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_' + element.badge + '.svg';
    }

    _refreshCompletion({element}) {
        // If the cm is not gamified, skip.
        if (!element.gamified) {
            return;
        }
        // We have a convenience method to locate elements inside the component.
        const target = this.getElement(this.selectors.CM_COMPLETION, element.id);

        if (!target && element.completion) {
            this._createCMBadgeCompletion({element});
            return;
        }

        if (!element.completion && target) {
            target.remove();
        }
    }

    _createCMBadgeCompletion({element}) {
        // If the cm is not gamified, skip.
        if (!element.gamified) {
            return;
        }
        const target = this.getElement(this.selectors.CM, element.id);
        if (!target) {
            return;
        }

        /*
        Create the following HTML structure:
        <div class="badgecompletion">
            <img src="{{config.wwwroot}}/course/format/ludilearn/pix/{{ world }}/badge/badge_completion.svg" alt="Icon completion">
        </div>
         */
        const badgecompletion = document.createElement('div');
        badgecompletion.className = 'badgecompletion';
        badgecompletion.setAttribute('data-for', 'ludilearn-section-cm-completion');
        badgecompletion.setAttribute('data-id', element.id);

        const imgcompletion = document.createElement('img');
        imgcompletion.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_completion.svg';
        imgcompletion.alt = 'Icon completion';
        badgecompletion.appendChild(imgcompletion);
        target.querySelector(this.selectors.CM_BADGE).after(badgecompletion);
    }
}