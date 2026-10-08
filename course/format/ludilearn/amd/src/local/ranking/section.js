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
 * Ranking section component.
 *
 * @module     format_ludilearn/local/ranking/section
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {BaseComponent} from 'core/reactive';
import Templates from 'core/templates';
import Notification from "core/notification";

export default class extends BaseComponent {

    create(descriptor) {
        this.sectionid = descriptor.data.sectionid;
        this.cms = descriptor.data.cms;
        this.selectors = {
            SUMMARY: `[data-for='ludilearn-section-summary']`,
            SUMMARYTEMPLATE: `[data-for='ludilearn-section-summarytemplate']`,
            RANKING_TABLE: `[data-for='ludilearn-section-rankingtable']`,
            CM_SCORE: `[data-for='ludilearn-section-cm-score']`,
            CM_SUMMARYTEMPLATE: `[data-for='ludilearn-section-cm-summarytemplate']`,
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
            {watch: `currentsection.summary:updated`, handler: this._refreshSummary},
            {watch: `currentsection.crowned:updated`, handler: this._refreshSummaryTemplate},
            {watch: `currentsection.ranking:updated`, handler: this._refreshSummaryTemplate},
            {watch: `cms.score:updated`, handler: this._refreshCMScore},
            {watch: `cms.crowned:updated`, handler: this._refreshCMSummaryTemplate},
            {watch: `cms.ranking:updated`, handler: this._refreshCMSummaryTemplate},
        ];
    }

    _refreshSummary({element}) {
        const target = this.getElement(this.selectors.SUMMARY);
        if (!target) {
            return;
        }

        // Update the score.
        target.innerHTML = element.summary;
    }

    _refreshSummaryTemplate({element}) {
        const target = this.getElement(this.selectors.SUMMARYTEMPLATE);
        if (!target) {
            return;
        }
        if (!element.gamified || element.noscore || !element.lastaccess) {
            return;
        }

        const data = {parameters: element};
        Templates.render('format_ludilearn/ranking/' + element.world + '/summary', data).then((html) => {
            target.innerHTML = html;
            return;
        }).catch(Notification.exception);
        this._refreshRankingTable({element});
    }

    _refreshRankingTable({element}) {
        const target = this.getElement(this.selectors.RANKING_TABLE);
        if (!target) {
            return;
        }
        if (!element.gamified || element.noscore || !element.lastaccess) {
            return;
        }
        const data = {parameters: element};
        Templates.render('format_ludilearn/ranking/rankingtable', data).then((html) => {
            target.innerHTML = html;
            return;
        }).catch(Notification.exception);
    }

    _refreshCMScore({element}) {
        const target = this.getElement(this.selectors.CM_SCORE, element.id);
        if (!target) {
            return;
        }

        // Update the score.
        target.innerHTML = element.score + ' pts';
    }

    _refreshCMSummaryTemplate({element}) {
        const target = this.getElement(this.selectors.CM_SUMMARYTEMPLATE, element.id);
        if (!target) {
            return;
        }
        if (!element.gamified || element.noscore || !element.lastaccess) {
            return;
        }
        const data = {parameters: element};
        Templates.render('format_ludilearn/ranking/' + element.world + '/summary', data).then((html) => {
            target.innerHTML = html;
            return;
        }).catch(Notification.exception);
    }
}