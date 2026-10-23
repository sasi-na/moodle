/* eslint-disable */
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
 * Multi-platform video progress tracker for Edwiser Video Activity.
 * Supports HTML5/Plyr, YouTube, and Vimeo videos.
 *
 * @module     mod_edwiservideoactivity/progress_tracker
 * @copyright  2025 Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, Ajax) {
    'use strict';

    return {
        // ==================== INITIALIZATION ====================

        /**
         * Initialize progress tracking based on video source type
         * @param {number} cmid - Course module ID
         * @param {number} sourcetype - Video source type (1=upload, 2=url, 3=embed)
         * @param {string} sourcepath - Video URL or path
         * @param {string} elementId - Video element ID
         * @param {number} completionpercent - Completion percentage required (default: 100)
         * @param {boolean} isLoggedIn - Whether user is logged in (true) or guest (false)
         */
        init: function(cmid, sourcetype, sourcepath, elementId, completionpercent, isLoggedIn) {
            // Normalize cmid to string for consistent localStorage key usage
            this.cmid = String(cmid);
            this.sourcetype = sourcetype;
            this.sourcepath = sourcepath;
            this.elementId = elementId;
            this.completionpercent = completionpercent || 0;
            // Default to true for backward compatibility if parameter not provided
            this.isLoggedIn = (isLoggedIn !== undefined && isLoggedIn !== null) ? isLoggedIn : true;
            this.provider = this.detectProvider(sourcetype, sourcepath);
            this.lastSaveTime = Date.now();
            this.player = null;
            this.progressInterval = null;
            this.isCompleted = false;
            // Progress save interval in milliseconds (change this value to adjust save frequency)
            this.progressSaveInterval = 2000; // 2 seconds
            // Store cached progress data to restore after Moodle clears localStorage
            this.cachedProgressData = null;
            // Flag to prevent auto-redirect when resuming from 100% progress on page load
            // If user interacts (plays/seeks), this flag is reset to allow redirect on completion
            this.isResumingFromComplete = false;

            // console.log('[PROGRESS_TRACKER] Initializing progress tracking:', {
            //     cmid: cmid,
            //     cmidNormalized: this.cmid,
            //     cmidType: typeof this.cmid,
            //     sourcetype: sourcetype,
            //     provider: this.provider,
            //     isLoggedIn: this.isLoggedIn,
            //     storageType: this.isLoggedIn ? 'DATABASE' : 'LOCALSTORAGE'
            // });

            // Check localStorage immediately on init (before player is ready)
            // Use window.localStorage directly to ensure we're checking the right storage
            // Moodle's storage_validation will clear non-prefixed keys, so we need to cache and restore
            if (!this.isLoggedIn) {
                var storage = window.localStorage;
                var checkKey = 'edwiservideoactivity_progress_' + this.cmid;
                var existingData = storage.getItem(checkKey);
                if (existingData) {
                    try {
                        var parsed = JSON.parse(existingData);
                        // Cache the data so we can restore it after Moodle clears localStorage
                        this.cachedProgressData = parsed;
                    } catch (e) {
                        // console.error('[PROGRESS_TRACKER] Error parsing existing data:', e);
                    }
                }
            }

            // Initialize appropriate tracker
            this.initializeTracker();
        },

        /**
         * Detect video provider from source type and URL
         */
        detectProvider: function(sourcetype, sourcepath) {
            // Convert to integer if string
            var sourceTypeInt = parseInt(sourcetype, 10);

            // console.log('[DETECT] Source type:', sourcetype, '(parsed:', sourceTypeInt + ')');
            // console.log('[DETECT] Source path:', sourcepath);

            if (sourceTypeInt === 1 || sourceTypeInt === 2) {
                // console.log('[DETECT] Detected provider: html5');
                return 'html5'; // Upload or URL videos use HTML5/Plyr
            }

            // Detect embed platform from URL
            if (sourcepath && sourcepath.match(/youtube\.com|youtu\.be/)) {
                // console.log('[DETECT] Detected provider: youtube');
                return 'youtube';
            } else if (sourcepath && sourcepath.match(/vimeo\.com/)) {
                // console.log('[DETECT] Detected provider: vimeo');
                return 'vimeo';
            } else if (sourcepath && sourcepath.match(/loom\.com/)) {
                // console.log('[DETECT] Detected provider: loom');
                return 'loom';
            }

            // console.log('[DETECT] Detected provider: unknown');
            return 'unknown';
        },

        /**
         * Initialize appropriate tracker based on provider
         */
        initializeTracker: function() {
            if (this.provider === 'html5') {
                this.initHTML5Tracker();
            } else if (this.provider === 'youtube') {
                this.initYouTubeTracker();
            } else if (this.provider === 'vimeo') {
                this.initVimeoTracker();
            } else {
                // console.warn('No tracker available for provider:', this.provider);
            }
        },

        // ==================== HTML5/PLYR TRACKING ====================

        initHTML5Tracker: function() {
            this.waitForPlyrPlayer();
        },

        waitForPlyrPlayer: function() {
            var self = this;
            var videoElement = document.querySelector('#' + this.elementId);

            if (videoElement && videoElement.plyr) {
                // Plyr is initialized
                // console.log('[HTML5] Plyr player found and initialized');
                this.player = videoElement.plyr;
                this.savedProgressLoaded = false;
                this.setupPlyrListeners();
                // Progress will be loaded in 'loadedmetadata' event handler
            } else {
                // Wait for Plyr to initialize
                // console.log('[HTML5] Waiting for Plyr to initialize...');
                setTimeout(function() {
                    self.waitForPlyrPlayer();
                }, 500);
            }
        },

        setupPlyrListeners: function() {
            var self = this;

            // console.log('[HTML5] Setting up Plyr event listeners');

            // Check if metadata is already loaded (readyState >= 2 means metadata loaded)
            // If so, load saved progress immediately. Otherwise, wait for loadedmetadata event.
            if (this.player.media && this.player.media.readyState >= 2) {
                // Metadata already loaded, load saved progress immediately
                if (!self.savedProgressLoaded) {
                    self.savedProgressLoaded = true;
                    self.loadSavedProgress();
                }
            } else {
                // Wait for video metadata to load before resuming playback
                this.player.on('loadedmetadata', function() {
                    // Load saved progress once metadata is ready
                    if (!self.savedProgressLoaded) {
                        self.savedProgressLoaded = true;
                        self.loadSavedProgress();
                    }
                });
                // Fallback: if metadata takes time to load, try loading progress after a delay
                setTimeout(function() {
                    if (!self.savedProgressLoaded && self.player && self.player.media && self.player.media.readyState >= 2) {
                        self.savedProgressLoaded = true;
                        self.loadSavedProgress();
                    }
                }, 1000);
            }

            // Reset isResumingFromComplete flag when user interacts (plays or seeks)
            // This allows redirect if user watches to completion again
            this.player.on('play', function() {
                self.isResumingFromComplete = false;
            });

            this.player.on('seeked', function() {
                self.isResumingFromComplete = false;
            });

            this.player.on('timeupdate', function() {
                var currentTime = self.player.currentTime;
                var duration = self.player.duration;
                var percent = (currentTime / duration) * 100;

                // Save progress based on configured interval
                if (Date.now() - self.lastSaveTime >= self.progressSaveInterval) {
                    // console.log('[HTML5] Timeupdate - Saving progress:', Math.floor(percent) + '%',
                    //             'Time:', Math.floor(currentTime) + 's/' + Math.floor(duration) + 's');
                    self.saveProgress(currentTime, duration, percent);
                    self.lastSaveTime = Date.now();
                }

                // Check if completion percentage has been reached
                if (self.completionpercent > 0 && percent >= self.completionpercent && !self.isCompleted) {
                    // console.log('[HTML5] Completion percentage reached:', Math.floor(percent) + '% >= ' + self.completionpercent + '%');
                    // Update lastSavedPercent immediately to avoid race condition
                    self.lastSavedPercent = Math.floor(percent);
                    self.isCompleted = true;
                    // Save progress first, then mark complete after save completes
                    // This ensures database is updated before Moodle re-evaluates completion
                    self.saveProgress(currentTime, duration, percent).then(function() {
                        self.markActivityComplete();
                    });
                    // Reset flag when completion is reached during active playback
                    self.isResumingFromComplete = false;
                }
            });

            this.player.on('ended', function() {
                // console.log('[HTML5] Video ended - Saving final progress and marking complete');
                // Save progress first, then mark complete after save completes
                self.saveProgress(self.player.duration, self.player.duration, 100).then(function() {
                    self.markActivityComplete();
                    // Reset flag before redirecting (user actively watched to completion)
                    self.isResumingFromComplete = false;
                    self.autoPlayNextVideo();
                });
            });

            // Save progress when user leaves page
            window.addEventListener('beforeunload', function() {
                if (self.player && self.player.currentTime) {
                    var currentTime = self.player.currentTime;
                    var duration = self.player.duration;
                    var percent = (currentTime / duration) * 100;
                    // console.log('[HTML5] Page unload - Saving progress before exit');
                    // self.saveProgress(currentTime, duration, percent);
                }
            });
        },

        // ==================== YOUTUBE TRACKING ====================

        initYouTubeTracker: function() {
            this.loadYouTubeAPI();
        },

        loadYouTubeAPI: function() {
            var self = this;

            if (typeof YT !== 'undefined' && YT.Player) {
                // console.log('[YouTube] YouTube API already loaded');
                this.initializeYouTubePlayer();
                return;
            }

            // console.log('[YouTube] Loading YouTube IFrame API...');
            // Load YouTube API script
            if (!window.youtubeAPILoading) {
                window.youtubeAPILoading = true;
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                document.head.appendChild(tag);
            }

            // Wait for API to load
            var originalCallback = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function() {
                // console.log('[YouTube] YouTube API loaded successfully');
                if (originalCallback) {
                    originalCallback();
                }
                self.initializeYouTubePlayer();
            };
        },

        initializeYouTubePlayer: function() {
            var self = this;
            var iframe = document.getElementById(this.elementId);

            if (!iframe) {
                // console.error('[YouTube] YouTube iframe not found:', this.elementId);
                return;
            }

            // console.log('[YouTube] Initializing YouTube player for iframe:', this.elementId);
            // console.log('[YouTube] iframe src:', iframe.src);

            // YouTube IFrame API can work with existing iframes that have enablejsapi=1
            // Extract video ID from the iframe src
            var videoId = this.extractYouTubeId(iframe.src);
            if (!videoId) {
                // console.error('[YouTube] Could not extract video ID from:', iframe.src);
                return;
            }
            // console.log('[YouTube] Extracted video ID:', videoId);

            // Initialize player on the existing iframe
            this.player = new YT.Player(this.elementId, {
                videoId: videoId,
                events: {
                    'onReady': function(event) {
                        self.loadSavedProgress();
                    },
                    'onStateChange': function(event) {
                        // YT.PlayerState.PLAYING = 1
                        if (event.data === 1) {
                            // console.log('[YouTube] State: PLAYING - Starting progress tracking');
                            // Reset flag when user plays (interaction detected)
                            self.isResumingFromComplete = false;
                            self.startYouTubeProgressTracking();
                        }
                        // YT.PlayerState.PAUSED = 2, YT.PlayerState.ENDED = 0
                        else if (event.data === 2 || event.data === 0) {
                            if (event.data === 2) {
                                // console.log('[YouTube] State: PAUSED - Stopping progress tracking');
                            } else {
                                // console.log('[YouTube] State: ENDED - Video finished');
                            }
                            self.stopYouTubeProgressTracking();

                            if (event.data === 0) {
                                // Video ended
                                var duration = self.player.getDuration();
                                // console.log('[YouTube] Saving final progress and marking complete');
                                // Save progress first, then mark complete after save completes
                                self.saveProgress(duration, duration, 100).then(function() {
                                    self.markActivityComplete();
                                    // Only redirect if user actively watched to completion (not resuming from 100% on page load)
                                    // Check flag before resetting - if resuming from complete, don't redirect
                                    if (self.isResumingFromComplete) {
                                        // User loaded page with 100% progress and video ended automatically
                                        // Don't redirect - allow user to rewatch
                                        return;
                                    }
                                    // Reset flag before redirecting (user actively watched to completion)
                                    self.isResumingFromComplete = false;
                                    self.autoPlayNextVideo();
                                });
                            }
                        }
                    }
                }
            });
        },

        startYouTubeProgressTracking: function() {
            var self = this;

            if (this.progressInterval) {
                return; // Already tracking
            }

            // Track last known time to detect seeks (large jumps indicate user seeking)
            var lastTrackedTime = self.player ? self.player.getCurrentTime() : 0;

            // console.log('[YouTube] Started progress tracking interval (' + (this.progressSaveInterval / 1000) + ' seconds)');

            this.progressInterval = setInterval(function() {
                if (self.player && self.player.getCurrentTime) {
                    var currentTime = self.player.getCurrentTime();
                    var duration = self.player.getDuration();
                    var percent = (currentTime / duration) * 100;

                    // Detect seek by checking for large time jumps (more than 3 seconds in one interval)
                    // This indicates user scrubbed the timeline
                    var timeJump = Math.abs(currentTime - lastTrackedTime);
                    if (timeJump > 3 && lastTrackedTime > 0) {
                        // User seeked - reset flag to allow redirect if they complete again
                        self.isResumingFromComplete = false;
                    }
                    lastTrackedTime = currentTime;

                    // console.log('[YouTube] Progress tracking - Saving progress:', Math.floor(percent) + '%',
                    //             'Time:', Math.floor(currentTime) + 's/' + Math.floor(duration) + 's');
                    self.saveProgress(currentTime, duration, percent);

                    // Check if completion percentage has been reached
                    if (self.completionpercent > 0 && percent >= self.completionpercent && !self.isCompleted) {
                               // console.log('[YouTube] Completion percentage reached:', Math.floor(percent) + '% >= ' + self.completionpercent + '%');
                               // Update lastSavedPercent immediately to avoid race condition
                               self.lastSavedPercent = Math.floor(percent);
                        self.isCompleted = true;
                        // Save progress first, then mark complete after save completes
                        // This ensures database is updated before Moodle re-evaluates completion
                        self.saveProgress(currentTime, duration, percent).then(function() {
                            self.markActivityComplete();
                        });
                               // Reset flag when completion is reached during active playback
                               self.isResumingFromComplete = false;
                    }
                }
            }, this.progressSaveInterval); // Save based on configured interval
        },

        stopYouTubeProgressTracking: function() {
            if (this.progressInterval) {
                // console.log('[YouTube] Stopping progress tracking interval');
                clearInterval(this.progressInterval);
                this.progressInterval = null;

                // Save final progress
                if (this.player && this.player.getCurrentTime) {
                    var currentTime = this.player.getCurrentTime();
                    var duration = this.player.getDuration();
                    var percent = (currentTime / duration) * 100;
                    // console.log('[YouTube] Saving final progress before stop');
                    this.saveProgress(currentTime, duration, percent);
                }
            }
        },

        extractYouTubeId: function(url) {
            // Handle various YouTube URL formats
            // https://www.youtube.com/watch?v=VIDEO_ID
            // https://youtu.be/VIDEO_ID
            // https://www.youtube.com/embed/VIDEO_ID
            // https://www.youtube.com/embed/VIDEO_ID?si=XXXXX

            var regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
            var match = url.match(regExp);

            if (match && match[7] && match[7].length === 11) {
                return match[7];
            }

            // Fallback: try to extract from embed URL directly
            var embedMatch = url.match(/embed\/([a-zA-Z0-9_-]{11})/);
            if (embedMatch && embedMatch[1]) {
                return embedMatch[1];
            }

            return null;
        },

        // ==================== VIMEO TRACKING ====================

        initVimeoTracker: function() {
            this.loadVimeoAPI();
        },

        loadVimeoAPI: function() {
            var self = this;

            // console.log('[Vimeo] Loading Vimeo Player API via RequireJS...');

            // Use RequireJS to load Vimeo API (handles AMD properly)
            require.config({
                paths: {
                    'vimeo-player': 'https://player.vimeo.com/api/player'
                }
            });

            require(['vimeo-player'], function(Player) {
                // console.log('[Vimeo] Vimeo API loaded successfully via RequireJS');
                // Store in window for consistency
                if (!window.Vimeo) {
                    window.Vimeo = {};
                }
                window.Vimeo.Player = Player || window.Vimeo.Player;
                self.initializeVimeoPlayer();
            }, function(err) {
                // console.error('[Vimeo] Failed to load Vimeo Player API:', err);
            });
        },

        initializeVimeoPlayer: function() {
            var self = this;
            var iframe = document.getElementById(this.elementId);

            if (!iframe) {
                // console.error('[Vimeo] Vimeo iframe not found:', this.elementId);
                return;
            }

            if (typeof window.Vimeo === 'undefined' || !window.Vimeo.Player) {
                // console.error('[Vimeo] Vimeo.Player is not available');
                return;
            }

            // console.log('[Vimeo] Initializing Vimeo player for iframe:', this.elementId);

            this.player = new window.Vimeo.Player(iframe);

            // Setup event listeners
            this.player.on('play', function() {
                       // console.log('[Vimeo] Video playing');
                       // Reset flag when user plays (interaction detected)
                       self.isResumingFromComplete = false;
            });

            this.player.on('pause', function() {
                       // console.log('[Vimeo] Video paused');
                   });

                   this.player.on('seeked', function() {
                       // Reset flag when user seeks (interaction detected)
                       self.isResumingFromComplete = false;
            });

            this.player.on('timeupdate', function(data) {
                var currentTime = data.seconds;
                var duration = data.duration;
                var percent = data.percent * 100;

                // Save progress based on configured interval
                if (Date.now() - self.lastSaveTime >= self.progressSaveInterval) {
                    // console.log('[Vimeo] Timeupdate - Saving progress:', Math.floor(percent) + '%',
                    //             'Time:', Math.floor(currentTime) + 's/' + Math.floor(duration) + 's');
                    self.saveProgress(currentTime, duration, percent);
                    self.lastSaveTime = Date.now();
                }

                       // Check if completion percentage has been reached
                       if (self.completionpercent > 0 && percent >= self.completionpercent && !self.isCompleted) {
                           // console.log('[Vimeo] Completion percentage reached:', Math.floor(percent) + '% >= ' + self.completionpercent + '%');
                           // Update lastSavedPercent immediately to avoid race condition
                           self.lastSavedPercent = Math.floor(percent);
                    self.isCompleted = true;
                    // Save progress first, then mark complete after save completes
                    // This ensures database is updated before Moodle re-evaluates completion
                    self.saveProgress(currentTime, duration, percent).then(function() {
                        self.markActivityComplete();
                    });
                           // Reset flag when completion is reached during active playback
                           self.isResumingFromComplete = false;
                       }
            });

                   this.player.on('ended', function() {
                       // console.log('[Vimeo] Video ended - Saving final progress and marking complete');
                self.player.getDuration().then(function(duration) {
                    // Save progress first, then mark complete after save completes
                    self.saveProgress(duration, duration, 100).then(function() {
                        self.markActivityComplete();
                        // Reset flag before redirecting (user actively watched to completion)
                        self.isResumingFromComplete = false;
                        self.autoPlayNextVideo();
                    });
                });
            });

                    // Load saved progress after player is ready
                    // Wait for video to be ready by checking duration
                    this.player.ready().then(function() {
                        self.loadSavedProgress();
                    }).catch(function(error) {
                        // console.error('[PROGRESS_TRACKER] Vimeo ready() failed:', error);
                        // If ready() fails, try loading progress anyway after a short delay
                        setTimeout(function() {
                            self.loadSavedProgress();
                        }, 1000);
                    });
        },

        // ==================== SHARED METHODS ====================

        /**
         * Save progress to localStorage (for guest users)
         * @param {number} currentTime - Current playback time in seconds
         * @param {number} duration - Total video duration in seconds
         * @param {number} percent - Playback percentage (0-100)
         * @param {string} provider - Video provider (html5, youtube, vimeo)
         */
        saveProgressToLocalStorage: function(currentTime, duration, percent, provider) {
            if (typeof(Storage) === 'undefined') {
                // console.warn('[PROGRESS_TRACKER] localStorage not available (private browsing mode)');
                return false;
            }

            try {
                // Ensure cmid is string for consistency
                var cmidStr = String(this.cmid);
                var progressData = {
                    cmid: cmidStr,
                    currenttime: Math.floor(currentTime),
                    duration: Math.floor(duration),
                    percent: Math.floor(percent),
                    provider: provider || this.provider,
                    timestamp: Date.now()
                };

                // Use window.localStorage directly to bypass any Moodle wrapper that might interfere
                var storage = window.localStorage;
                var storageKey = 'edwiservideoactivity_progress_' + cmidStr;

                // Save using raw window.localStorage to ensure it persists
                storage.setItem(storageKey, JSON.stringify(progressData));
                // Also update cached data in memory (in case Moodle clears localStorage)
                this.cachedProgressData = progressData;

                // Verify it was saved using the same storage reference
                var verifyData = storage.getItem(storageKey);
                if (!verifyData) {
                    // console.error('[PROGRESS_TRACKER] ERROR: Failed to verify save - data not found in localStorage after saving!');
                }
                return true;
            } catch (e) {
                // console.error('[PROGRESS_TRACKER] Error saving to localStorage:', e);
                return false;
            }
        },

        /**
         * Load progress from localStorage (for guest users)
         * @returns {Object|null} Progress data or null if not found
         */
        loadProgressFromLocalStorage: function() {
            if (typeof(Storage) === 'undefined') {
                // console.warn('[PROGRESS_TRACKER] localStorage not available');
                return null;
            }

            try {
                // Use window.localStorage directly to bypass any Moodle wrapper
                var storage = window.localStorage;

                // Ensure cmid is string for consistency
                var cmidStr = String(this.cmid);
                var storageKey = 'edwiservideoactivity_progress_' + cmidStr;

                // First check if we have cached data (from before Moodle cleared localStorage)
                if (this.cachedProgressData) {
                    // Restore the data to localStorage
                    storage.setItem(storageKey, JSON.stringify(this.cachedProgressData));
                    // Use cached data
                    var progressData = this.cachedProgressData;
                    // Clear the cache since we've restored it
                    this.cachedProgressData = null;
                    // Return the cached data
                    if (progressData && typeof progressData.currenttime === 'number' && progressData.currenttime > 0) {
                        this.lastSavedPercent = progressData.percent;
                        return progressData;
                    }
                }

                var storedData = storage.getItem(storageKey);

                if (!storedData) {
                    return null;
                }

                var progressData = JSON.parse(storedData);

                // Validate data structure
                if (progressData && typeof progressData.currenttime === 'number' && progressData.currenttime > 0) {
                    return progressData;
                }

                // console.warn('[PROGRESS_TRACKER] Invalid progress data structure:', progressData);
                return null;
            } catch (e) {
                // console.error('[PROGRESS_TRACKER] Error loading from localStorage:', e);
                return null;
            }
        },

        /**
         * Save progress to server or localStorage based on login status
         */
        saveProgress: function(currentTime, duration, percent) {
            var self = this;

            // For guest users, save to localStorage instead of database
            if (!this.isLoggedIn) {
                var saved = this.saveProgressToLocalStorage(currentTime, duration, percent, this.provider);
                if (saved) {
                    // Track the saved percent for completion checking
                    this.lastSavedPercent = Math.floor(percent);
                }
                // Return resolved promise for guest users
                return Promise.resolve();
            }

            // For logged-in users, save to database via AJAX (existing behavior)
            // console.log('[PROGRESS_TRACKER] Logged-in user - saving to database via AJAX');
            var data = JSON.stringify({
                cmid: this.cmid,
                currenttime: Math.floor(currentTime),
                duration: Math.floor(duration),
                percent: Math.floor(percent),
                provider: this.provider
            });

            // Return promise so caller can wait for save to complete
            return Ajax.call([{
                methodname: 'mod_edwiservideoactivity_save_progress',
                args: {
                    data: data
                }
            }])[0].then(function(jsonResponse) {
                var response = JSON.parse(jsonResponse);
                // console.log('[PROGRESS_TRACKER] Progress saved to database:', response.progress + '%');
                // Track the last saved percent for completion checking
                self.lastSavedPercent = response.progress;
                return response;
            }).catch(function(error) {
                // console.error('[PROGRESS_TRACKER] Error saving progress to database:', error);
                throw error;
            });
        },

        /**
         * Load saved progress from server or localStorage based on login status
         */
        loadSavedProgress: function() {
            var self = this;

            // For guest users, load from localStorage
            if (!this.isLoggedIn) {
                var progressData = this.loadProgressFromLocalStorage();

                if (progressData && progressData.currenttime > 0 && this.player) {
                    // Track the last saved percent for completion checking
                    this.lastSavedPercent = progressData.percent;

                    // If saved progress is 100%, set flag to prevent auto-redirect on page load
                    // This allows user to rewatch the video. Flag will be reset if user interacts.
                    if (progressData.percent >= 100) {
                        this.isResumingFromComplete = true;
                    }

                    // Resume playback based on provider
                    if (this.provider === 'html5') {
                        // Ensure video is ready before setting currentTime
                        if (this.player.media && this.player.media.readyState >= 2) {
                            // Metadata loaded, safe to set currentTime
                            this.player.currentTime = progressData.currenttime;
                        } else {
                            // Wait for metadata to load
                            var setTimeHandler = function() {
                                if (self.player && self.player.media && self.player.media.readyState >= 2) {
                                    self.player.currentTime = progressData.currenttime;
                                    self.player.off('loadedmetadata', setTimeHandler);
                                }
                            };
                            this.player.on('loadedmetadata', setTimeHandler);
                            // Fallback: try after a short delay
                            setTimeout(function() {
                                if (self.player && self.player.media && self.player.media.readyState >= 2) {
                                    self.player.currentTime = progressData.currenttime;
                                }
                            }, 500);
                        }
                    } else if (this.provider === 'youtube') {
                        // YouTube player is ready (onReady callback ensures this)
                        this.player.seekTo(progressData.currenttime, true);
                    } else if (this.provider === 'vimeo') {
                        // Vimeo player should be ready (ready() promise ensures this)
                        this.player.setCurrentTime(progressData.currenttime).then(function() {
                            // Successfully set currentTime
                        }).catch(function(error) {
                            // console.error('[PROGRESS_TRACKER] Error setting Vimeo currentTime:', error);
                            // If setCurrentTime fails, try again after a short delay
                            setTimeout(function() {
                                if (self.player) {
                                    self.player.setCurrentTime(progressData.currenttime);
                                }
                            }, 500);
                        });
                    }
                } else {
                    this.lastSavedPercent = 0;
                }
                return;
            }

            // For logged-in users, load from database via AJAX (existing behavior)

            var data = JSON.stringify({
                cmid: this.cmid
            });

            Ajax.call([{
                methodname: 'mod_edwiservideoactivity_get_progress',
                args: {
                    data: data
                }
            }])[0].then(function(jsonResponse) {
                // Parse JSON response
                var response = JSON.parse(jsonResponse);
                // console.log('[' + self.provider.toUpperCase() + '] Response received:', response);

                if (response.progress && response.progress.currenttime > 0 && self.player) {
                    // console.log('[' + self.provider.toUpperCase() + '] Found saved progress:',
                    //             Math.floor(response.progress.percent) + '%',
                    //             '(Time: ' + Math.floor(response.progress.currenttime) + 's)');
                    // Track the last saved percent for completion checking
                    self.lastSavedPercent = response.progress.percent;

                    // If saved progress is 100%, set flag to prevent auto-redirect on page load
                    // This allows user to rewatch the video. Flag will be reset if user interacts.
                    if (response.progress.percent >= 100) {
                        self.isResumingFromComplete = true;
                    }

                    // Resume playback based on provider
                    if (self.provider === 'html5') {
                        // Ensure media element is ready before setting currentTime
                        if (self.player.media && self.player.media.readyState >= 2) {
                            // Metadata loaded, safe to set currentTime
                            self.player.currentTime = response.progress.currenttime;
                        } else {
                            // Wait for metadata to load
                            var setTimeHandler = function() {
                                if (self.player && self.player.media && self.player.media.readyState >= 2) {
                                    self.player.currentTime = response.progress.currenttime;
                                    self.player.off('loadedmetadata', setTimeHandler);
                                }
                            };
                            self.player.on('loadedmetadata', setTimeHandler);
                            // Fallback: try after a short delay
                            setTimeout(function() {
                                if (self.player && self.player.media && self.player.media.readyState >= 2) {
                        self.player.currentTime = response.progress.currenttime;
                                }
                            }, 500);
                        }
                        // console.log('[HTML5] Resumed playback at:', response.progress.currenttime + 's');
                    } else if (self.provider === 'youtube') {
                        self.player.seekTo(response.progress.currenttime, true);
                        // console.log('[YouTube] Seeked to:', response.progress.currenttime + 's');
                    } else if (self.provider === 'vimeo') {
                        self.player.setCurrentTime(response.progress.currenttime);
                        // console.log('[Vimeo] Set current time to:', response.progress.currenttime + 's');
                    }
                } else {
                    // console.log('[' + self.provider.toUpperCase() + '] No saved progress found - Starting from beginning');
                    self.lastSavedPercent = 0;
                }
            }).catch(function(error) {
                // console.error('[' + self.provider.toUpperCase() + '] Error loading saved progress:', error);
            });
        },

        /**
         * Mark activity as complete
         */
        markActivityComplete: function() {
            var self = this;

            // Skip completion marking for guest users
            if (!this.isLoggedIn) {
                return;
            }

            // console.log('[' + this.provider.toUpperCase() + '] Checking completion requirements...');

            // If no custom completion percentage is set, mark as complete (default behavior)
            if (this.completionpercent <= 0) {
                // console.log('[' + this.provider.toUpperCase() + '] No custom completion percentage set, marking as complete...');

                var data = JSON.stringify({
                    cmid: this.cmid
                });

                Ajax.call([{
                    methodname: 'mod_edwiservideoactivity_mark_complete',
                    args: {
                        data: data
                    }
                }])[0].then(function(jsonResponse) {
                    var response = JSON.parse(jsonResponse);
                    if (response.success) {
                        // console.log('[' + self.provider.toUpperCase() + '] ✓ Activity marked as complete successfully!');
                    } else {
                        // console.warn('[' + self.provider.toUpperCase() + '] Activity completion failed:', response.message);
                    }
                }).catch(function(error) {
                    // console.error('[' + self.provider.toUpperCase() + '] Error marking activity complete:', error);
                });
                return;
            }

            // Get current progress percentage
            var currentPercent = this.getCurrentProgressPercent();
            var requiredPercent = this.completionpercent;

            // console.log('[' + this.provider.toUpperCase() + '] Current progress:', currentPercent + '%');
            // console.log('[' + this.provider.toUpperCase() + '] Required progress:', requiredPercent + '%');

            if (currentPercent >= requiredPercent) {
                // console.log('[' + this.provider.toUpperCase() + '] Progress requirement met, marking as complete...');

                var data = JSON.stringify({
                    cmid: this.cmid
                });

                Ajax.call([{
                    methodname: 'mod_edwiservideoactivity_mark_complete',
                    args: {
                        data: data
                    }
                }])[0].then(function(jsonResponse) {
                    var response = JSON.parse(jsonResponse);
                    if (response.success) {
                        // console.log('[' + self.provider.toUpperCase() + '] ✓ Activity marked as complete successfully!');
                    } else {
                        // console.warn('[' + self.provider.toUpperCase() + '] Activity completion failed:', response.message);
                    }
                }).catch(function(error) {
                    // console.error('[' + self.provider.toUpperCase() + '] Error marking activity complete:', error);
                });
            } else {
                // console.log('[' + this.provider.toUpperCase() + '] Progress requirement not met (' + currentPercent + '% < ' + requiredPercent + '%)');
            }
        },

        /**
         * Get current progress percentage
         * @returns {number} Current progress percentage
         */
        getCurrentProgressPercent: function() {
            // Get the highest progress percentage from saved data
            // This would need to be implemented based on current progress tracking
            return this.lastSavedPercent || 0;
        },

        /**
         * Auto-play next video functionality
         * Uses existing navigation URL from activity.mustache
         */
        autoPlayNextVideo: function() {
            var self = this;
            // console.log('[' + this.provider.toUpperCase() + '] Checking for next video...');

            // Don't redirect if user is resuming from 100% progress on page load
            // This allows user to rewatch the video. Flag is reset when user interacts.
            if (this.isResumingFromComplete) {
                return;
            }

            // Get next button URL from existing navigation
            var nextBtn = document.querySelector('.edwactivity-nextbtn');

            if (nextBtn && nextBtn.href) {
                // console.log('[' + this.provider.toUpperCase() + '] Next video found, redirecting to:', nextBtn.href);

                // Small delay to ensure completion is saved
                var redirectDelayMs = 5000;
                var secs = Math.ceil(redirectDelayMs / 1000);
                var timerEl = document.querySelector('.nextactivitytimer');
                var countdownInterval = null;

                document.getElementById("overlay").classList.remove("d-none");

                var updateTimerText = function(secondsLeft) {
                    if (!timerEl) {
                        return;
                    }
                    timerEl.textContent = 'Starts in ' + secondsLeft + (secondsLeft === 1 ? ' Second' : ' Seconds');
                };

                // Initialize timer text immediately
                updateTimerText(secs);

                // Start countdown to update every second
                countdownInterval = setInterval(function() {
                    secs -= 1;
                    if (secs <= 0) {
                        clearInterval(countdownInterval);
                        updateTimerText(0);
                        return;
                    }
                    updateTimerText(secs);
                }, 1000);
                setTimeout(function() {
                    if (countdownInterval) {
                        clearInterval(countdownInterval);
                    }
                    window.location.href = nextBtn.href;
                }, redirectDelayMs);
            } else {
                // console.log('[' + this.provider.toUpperCase() + '] No next video available');
            }
        }
    };
});

