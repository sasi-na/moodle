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
 * Reactive class and state.
 *
 * @module     format_ludilearn/local/reactive
 * @copyright  2025 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import {mutations} from 'format_ludilearn/local/mutations';
import {eventTypes, notifyFormatLudilearnStateUpdated} from 'format_ludilearn/local/events';


// This is out initial state. For more complex aplications this state will be loaded
// using a webservice.
const state = {
    sections: [],
    currentsection: {},
    cms: [],
    currentcm: {}
};

// It is recommended to use your own reactive class extending the base one.
// By having your own class you will be able to add global methods to you application
// because all components will inherit the main reactive instance.
class Ludilearn extends Reactive {
    getStateManager() {
        return this.stateManager;
    }
}

// The reactive instance requires an event (eventNamer and eventDispatch method)
export const ludilearn = new Ludilearn({
    name: 'format_ludilearn',
    eventName: eventTypes.formatLudilearnStateUpdated,
    eventDispatch: notifyFormatLudilearnStateUpdated,
    state
});

// Set the mutations to the reactive instance.
ludilearn.setMutations(mutations);

/**
 * Load the initial state.
 *
 * For now the state only exists in the frontend but in more complex scenarios
 * the state data will be generated using a webservice.
 */
export const init = () => {
    // In this example we don't need to set anything because it is only a frontend
    // aplication. In future examples the initial state will be loaded asynchronous
    // and the init method will to all the initializing work.
};