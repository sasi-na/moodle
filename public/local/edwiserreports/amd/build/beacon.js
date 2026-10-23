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
"use strict";

define([], function() {
    const BEACON_SCRIPT_URL = 'https://beacon-v2.helpscout.net';
    let initialized = false;

    function ensureStub() {
        if (window.Beacon && typeof window.Beacon === 'function' && window.Beacon.readyQueue) {
            return;
        }

        const beacon = function(method, options, data) {
            beacon.readyQueue.push({method: method, options: options, data: data});
        };

        beacon.readyQueue = [];
        window.Beacon = beacon;
    }

    function injectScript() {
        if (document.querySelector('script[data-helpscout-beacon="true"]')) {
            return;
        }

        const firstScript = document.getElementsByTagName('script')[0];
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.src = BEACON_SCRIPT_URL;
        script.dataset.helpscoutBeacon = 'true';

        if (firstScript && firstScript.parentNode) {
            firstScript.parentNode.insertBefore(script, firstScript);
        } else {
            document.head.appendChild(script);
        }
    }

    function init(beaconId) {
        if (!beaconId || initialized || typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        ensureStub();
        injectScript();
        window.Beacon('init', beaconId);
        initialized = true;
    }

    return {
        init: init
    };
});
