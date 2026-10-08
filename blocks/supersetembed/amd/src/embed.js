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
 * Superset Dashboard Embed Module
 *
 * This module loads the Superset embedded SDK and embeds the dashboard
 *
 * @module     block_supersetembed/embed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification'], function($, Notification) {
    'use strict';

    /**
     * Fetch guest token from API Gateway
     * @param {string} apiUrl - The API Gateway URL
     * @param {string} dashboardId - The Superset dashboard ID
     * @returns {Promise<string>} - The guest token
     */
    const fetchGuestToken = async(apiUrl, dashboardId) => {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                resources: [
                    {
                        type: 'dashboard',
                        id: dashboardId
                    }
                ]
            })
        });

        if (!response.ok) {
            throw new Error('Failed to fetch guest token');
        }

        const data = await response.json();
        return data.token;
    };

    /**
     * Load Superset Embedded SDK dynamically
     * @param {string} supersetUrl - The Superset base URL
     * @returns {Promise} - Resolves when SDK is loaded
     */
    const loadSupersetSDK = (supersetUrl) => {
        return new Promise((resolve, reject) => {
            // Check if already loaded
            if (window.supersetEmbeddedSdk) {
                resolve(window.supersetEmbeddedSdk);
                return;
            }

            // Create and load script
            const script = document.createElement('script');
            script.src = supersetUrl + '/static/assets/embedded.bundle.js';
            script.async = true;
            script.onload = () => {
                if (window.supersetEmbeddedSdk) {
                    resolve(window.supersetEmbeddedSdk);
                } else {
                    reject(new Error('Superset SDK not found after loading'));
                }
            };
            script.onerror = () => {
                reject(new Error('Failed to load Superset SDK'));
            };
            document.head.appendChild(script);
        });
    };

    /**
     * Embed the Superset dashboard
     * @param {object} config - Configuration object
     */
    const embedDashboard = async(config) => {
        const {embedId, apiUrl, supersetUrl, dashboardId} = config;

        // eslint-disable-next-line no-console
        console.log('Superset: embedDashboard called with ID:', embedId);

        // Try multiple ways to find the element
        let container = document.getElementById(embedId);

        if (!container) {
            // eslint-disable-next-line no-console
            console.log('Superset: Element not found by ID, trying querySelector');
            container = document.querySelector('#' + embedId);
        }

        if (!container) {
            // eslint-disable-next-line no-console
            console.log('Superset: Still not found, trying by class');
            container = document.querySelector('.superset-embed-container');
        }

        if (!container) {
            // eslint-disable-next-line no-console
            console.error('Superset: No container found at all!');
            Notification.exception({message: 'Cannot find Superset container element'});
            return;
        }

        // eslint-disable-next-line no-console
        console.log('Superset: Found container:', container.id, container);

        try {
            // Show loading state
            container.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status">' +
                '<span class="sr-only">Loading dashboard...</span></div><p class="mt-2">Fetching dashboard...</p></div>';

            // eslint-disable-next-line no-console
            console.log('Superset: Loading SDK from:', supersetUrl);

            // Load Superset SDK
            const sdk = await loadSupersetSDK(supersetUrl);

            // eslint-disable-next-line no-console
            console.log('Superset: SDK loaded, fetching token from:', apiUrl);

            // Fetch guest token
            const guestToken = await fetchGuestToken(apiUrl, dashboardId);

            // eslint-disable-next-line no-console
            console.log('Superset: Token received, embedding dashboard');

            // Clear loading state
            container.innerHTML = '';

            // Embed dashboard
            sdk.embedDashboard({
                id: dashboardId,
                supersetDomain: supersetUrl,
                mountPoint: container,
                fetchGuestToken: () => guestToken,
                dashboardUiConfig: {
                    hideTitle: false,
                    hideChartControls: false,
                    hideTab: false,
                    filters: {
                        expanded: false,
                    },
                },
            });

            // eslint-disable-next-line no-console
            console.log('Superset: Dashboard embedded successfully');

        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Superset: Error during embedding:', error);
            container.innerHTML = '<div class="alert alert-danger">' +
                '<strong>Error:</strong> ' + error.message + '</div>';
            Notification.exception({message: 'Failed to embed Superset dashboard: ' + error.message});
        }
    };

    return {
        /**
         * Initialize the Superset embed
         * @param {object} config - Configuration object with embedId, apiUrl, supersetUrl, dashboardId
         */
        init: function(config) {
            // eslint-disable-next-line no-console
            console.log('Superset embed init called with config:', config);

            // Check if element already exists
            const checkAndEmbed = () => {
                const element = document.getElementById(config.embedId);
                if (element) {
                    // eslint-disable-next-line no-console
                    console.log('Superset: Element found, embedding immediately');
                    // Element exists, embed immediately
                    embedDashboard(config);
                } else {
                    // eslint-disable-next-line no-console
                    console.log('Superset: Element not found yet, waiting for DOM ready');
                    // Wait for DOM and try again
                    $(document).ready(function() {
                        setTimeout(function() {
                            embedDashboard(config);
                        }, 100);
                    });
                }
            };

            // Try immediately or wait for ready
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(checkAndEmbed, 50);
            } else {
                $(document).ready(checkAndEmbed);
            }
        }
    };
});
