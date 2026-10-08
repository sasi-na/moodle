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
 * Avatar section component.
 *
 * @module     format_ludilearn/local/avatar/section
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
            ITEM_EQUIPED_1: `[data-for='ludilearn-section-itemequiped1']`,
            ITEM_EQUIPED_2: `[data-for='ludilearn-section-itemequiped2']`,
            ITEM_EQUIPED_3: `[data-for='ludilearn-section-itemequiped3']`,
            ITEM_EQUIPED_4: `[data-for='ludilearn-section-itemequiped4']`,
            ITEM_EQUIPED_5: `[data-for='ludilearn-section-itemequiped5']`,
            ITEM_EQUIPED_6: `[data-for='ludilearn-section-itemequiped6']`,
            SUMMARY: `[data-for='ludilearn-section-summary']`,
            TOTAL_ITEMS_OWNED: `[data-for='ludilearn-section-totalitemsowned']`,
            TOTAL_ITEMS: `[data-for='ludilearn-section-totalitems']`,
            ITEM_OWNED_COUNT: `[data-for='ludilearn-section-itemsownedcount']`,
            ITEM_OWNABLE_COUNT: `[data-for='ludilearn-section-itemsownablecount']`,
            CM_THRESHOLD_EXCEEDED: `[data-for='ludilearn-section-cm-thresholdexceeded']`,
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
            {watch: `currentsection.item-equiped-1:updated`, handler: this._refreshItemEquiped1},
            {watch: `currentsection.item-equiped-2:updated`, handler: this._refreshItemEquiped2},
            {watch: `currentsection.item-equiped-3:updated`, handler: this._refreshItemEquiped3},
            {watch: `currentsection.item-equiped-4:updated`, handler: this._refreshItemEquiped4},
            {watch: `currentsection.item-equiped-5:updated`, handler: this._refreshItemEquiped5},
            {watch: `currentsection.item-equiped-6:updated`, handler: this._refreshItemEquiped6},
            {watch: `currentsection.summary:updated`, handler: this._refreshSummary},
            {watch: `currentsection.totalitemsowned:updated`, handler: this._refreshTotalItemsOwned},
            {watch: `currentsection.totalitems:updated`, handler: this._refreshTotalItems},
            {watch: `currentsection.itemsownedcount:updated`, handler: this._refreshItemOwnedCount},
            {watch: `currentsection.itemsownablecount:updated`, handler: this._refreshItemOwnableCount},
            {watch: `cms.thresholdexceeded:updated`, handler: this._refreshThresholdExceeded},
        ];
    }

    _refreshItemEquiped1({element}) {
        const target = this.getElement(this.selectors.ITEM_EQUIPED_1);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        let src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar';
        if (element['item-equiped-1']) {
            target.className = 'avatar-slot slot-1 item-equiped';
            target.src = src + '/items/images/image-01-' + element['item-equiped-1'] + '.svg';
        } else {
            target.className = 'avatar-slot slot-1';
            target.src = src + '/item_img_default.svg';
        }
    }

    _refreshItemEquiped2({element}) {
        const target = this.getElement(this.selectors.ITEM_EQUIPED_2);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        let src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar';
        if (element['item-equiped-2']) {
            target.className = 'avatar-slot slot-2 item-equiped';
            target.src = src + '/items/images/image-02-' + element['item-equiped-2'] + '.svg';
        } else {
            target.className = 'avatar-slot slot-2';
            target.src = src + '/item_img_default.svg';
        }
    }

    _refreshItemEquiped3({element}) {
        const target = this.getElement(this.selectors.ITEM_EQUIPED_3);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        let src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar';
        if (element['item-equiped-3']) {
            target.className = 'avatar-slot slot-3 item-equiped';
            target.src = src + '/items/images/image-03-' + element['item-equiped-3'] + '.svg';
        } else {
            target.className = 'avatar-slot slot-3';
            target.src = src + '/item_img_default.svg';
        }
    }

    _refreshItemEquiped4({element}) {
        const target = this.getElement(this.selectors.ITEM_EQUIPED_4);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        let src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar';
        if (element['item-equiped-4']) {
            target.className = 'avatar-slot slot-4 item-equiped';
            target.src = src + '/items/images/image-04-' + element['item-equiped-4'] + '.svg';
        } else {
            target.className = 'avatar-slot slot-4';
            target.src = src + '/item_img_default.svg';
        }
    }

    _refreshItemEquiped5({element}) {
        const target = this.getElement(this.selectors.ITEM_EQUIPED_5);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        let src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar';
        if (element['item-equiped-5']) {
            target.className = 'avatar-slot slot-5 item-equiped';
            target.src = src + '/items/images/image-05-' + element['item-equiped-5'] + '.svg';
        } else {
            target.className = 'avatar-slot slot-5';
            target.src = src + '/item_img_default.svg';
        }
    }

    _refreshItemEquiped6({element}) {
        const target = this.getElement(this.selectors.ITEM_EQUIPED_6);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        let src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar';
        if (element['item-equiped-6']) {
            target.className = 'avatar-slot slot-6 item-equiped';
            target.src = src + '/items/images/image-06-' + element['item-equiped-6'] + '.svg';
        } else {
            target.className = 'avatar-slot slot-6';
            target.src = src + '/item_img_default.svg';
        }
    }

    _refreshSummary({element}) {
        const target = this.getElement(this.selectors.SUMMARY);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        // Update the summary.
        target.innerHTML = element.summary;
    }

    _refreshTotalItemsOwned({element}) {
        const target = this.getElement(this.selectors.TOTAL_ITEMS_OWNED);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        // Update the owned item count.
        target.innerHTML = element.totalitemsowned;

        // Update target class based on count.
        if (element.itemsownedcounttwodigits) {
            target.className = 'numerator two-digits';
        } else {
            target.className = 'numerator';
        }
    }

    _refreshTotalItems({element}) {
        const target = this.getElement(this.selectors.TOTAL_ITEMS);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        // Update the ownable item count.
        target.innerHTML = element.totalitems;
    }

    _refreshItemOwnedCount({element}) {
        const target = this.getElement(this.selectors.ITEM_OWNED_COUNT);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        // Update the owned item count.
        target.innerHTML = element.itemsownedcount;
    }

    _refreshItemOwnableCount({element}) {
        const target = this.getElement(this.selectors.ITEM_OWNABLE_COUNT);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        // Update the ownable item count.
        target.innerHTML = element.itemsownablecount;
    }

    _refreshThresholdExceeded({element}) {
        const target = this.getElement(this.selectors.CM_THRESHOLD_EXCEEDED, element.id);
        if (!target) {
            return;
        }
        if (!element.gamified) {
            return;
        }

        // Update the threshold cm image src
        if (element.thresholdexceeded) {
            target.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar/mod_completed.svg';
        } else {
            target.src = config.wwwroot + '/course/format/ludilearn/pix/' + element.world + '/avatar/mod_default.svg';
        }
    }
}