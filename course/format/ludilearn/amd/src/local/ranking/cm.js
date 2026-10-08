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
 * Ranking cm component.
 *
 * @module     format_ludilearn/local/ranking/cm
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';
import Templates from 'core/templates';
import Notification from "core/notification";

export default class extends BaseComponent {

    create(descriptor) {
        this.cmid = descriptor.data.cmid;
        this.selectors = {
            GROUPEVIEW_TEMPLATE: `[data-for='groupviewtemplate']`,
            SCORE: `[data-for='ludilearn-cm-score']`,
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
            {watch: `currentcm.score:updated`, handler: this._refreshScore},
            {watch: `currentcm.crowned:updated`, handler: this._refreshGroupeViewTemplate},
            {watch: `currentcm.ranking:updated`, handler: this._refreshGroupeViewTemplate},
        ];
    }

    _refreshScore({element}) {
        const target = this.getElement(this.selectors.SCORE);
        if (!target) {
            return;
        }

        // Update the score.
        target.innerHTML = element.score + ' pts';
    }

    _refreshGroupeViewTemplate({element}) {
        const target = this.getElement(this.selectors.GROUPEVIEW_TEMPLATE);
        if (!target) {
            return;
        }
        if (!element.gamified || element.noscore || !element.lastaccess) {
            return;
        }
        const data = {parameters: element};
        Templates.render('format_ludilearn/ranking/' + element.world + '/group_view', data).then((html) => {
            target.innerHTML = html;
            return;
        }).catch(Notification.exception);
    }

}