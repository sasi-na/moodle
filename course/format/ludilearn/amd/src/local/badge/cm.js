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
 * Badge cm component.
 *
 * @module     format_ludilearn/local/badge/cm
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';
import * as config from 'core/config';

export default class extends BaseComponent {

    create(descriptor) {
        this.cmid = descriptor.data.cmid;
        this.selectors = {
            COMPLETION: `[data-for='ludilearn-cm-completion']`,
            BADGE: `[data-for='ludilearn-cm-badge']`,
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
        this.reactive.dispatch('syncCmAuto', this.cmid);
    }

    getWatchers() {
        return [
            {watch: `currentcm.completion:updated`, handler: this._refreshCompletion},
            {watch: `currentcm.badge:updated`, handler: this._refreshBadge},
        ];
    }

    _refreshBadge({element}) {
        // If the cm is not gamified, skip.
        if (!element.gamified) {
            return;
        }

        const target = this.getElement(this.selectors.BADGE);
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
        const target = this.getElement(this.selectors.COMPLETION);

        // Bonus completion was at 0 and is now greater than 0, we need to create the bonuscompletion element.
        if (!target && element.completion) {
            this._createBadgeCompletion({element});
            return;
        }

        // If completion is not found, skip.
        if (!element.completion && target) {
            target.remove();
        }
    }

    _createBadgeCompletion({element}) {
        // If the cm is not gamified, skip.
        if (!element.gamified) {
            return;
        }

        /*
        Create the following HTML structure:
        <div data-for="ludilearn-cm-completion" class="badgecompletion badgecompletion-cm">
            <img class="ludilearn-img img-responsive d-block mx-auto"
                 src="{{config.wwwroot}}/course/format/ludilearn/pix/{{ world }}/badge/badge_completion.svg"
                 alt="Image badge">
        </div>
         */

        const badgecompletion = document.createElement('div');
        badgecompletion.className = 'badgecompletion badgecompletion-cm';
        badgecompletion.setAttribute('data-for', 'ludilearn-cm-completion');

        const imgcompletion = document.createElement('img');
        imgcompletion.className = 'ludilearn-img img-responsive d-block mx-auto';
        imgcompletion.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/badge/badge_completion.svg';
        imgcompletion.alt = 'Image badge';
        badgecompletion.appendChild(imgcompletion);
        const target = this.getElement(this.selectors.BADGE);
        target.before(badgecompletion);
    }
}