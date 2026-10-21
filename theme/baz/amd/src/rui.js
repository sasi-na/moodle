//
// This file is part of baz theme for moodle
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
//
//
// baz main JS file
//
// @package    theme_baz
// @copyright  Copyright © 2021 onwards Marcin Czaja Rosea Themes
//
// @license    Commercial

/* jshint ignore:start */
define(['jquery', 'core/aria', 'core_user/repository'], function ($, setUserPreference, UserRepository) {
    "use strict"; // ...jshint ;_; !!!

    // (debug log removed: 'log' was never loaded and stopped this module.)

    return {
        init: function () {
            $(document).ready(function ($) {
                var trigger = $(document.getElementById("darkModeBtn"));
                var preference = trigger.attr('data-preference');

                $('#darkModeBtn').click(function () {
                    if ($('body').hasClass('theme-dark')) {
                        $('body').removeClass('theme-dark');
                        $('html').removeClass('dark-mode');
                        trigger.attr('aria-checked', 'false');
                        UserRepository.setUserPreference(preference, false);
                    } else {
                        $('body').addClass('theme-dark');
                        $('html').addClass('dark-mode');
                        trigger.attr('aria-checked', 'true');
                        UserRepository.setUserPreference(preference, true);
                    }
                });

                $('.btn-drawer--left').click(function () {
                    if ($('body').hasClass('drawer-open-index--open')) {
                        $('body').removeClass('drawer-courseindex--open');
                        $('body').removeClass('drawer-open-index--open');
                    } else {
                        $('body').toggleClass('drawer-courseindex--open');
                    }
                });

                $('.drawertoggle').click(function () {
                    if ($('body').hasClass('drawer-open-index--open')) {
                        $('body').removeClass('drawer-courseindex--open');
                        $('body').removeClass('drawer-open-index--open');
                    } else {
                        $('body').toggleClass('drawer-courseindex--open');
                    }
                });

                $('#courseindexbtn, .bulkEnable').click(function () {
                    $(function () {
                        $('#course-tab2').tab('show');
                    });                    
                });

                $( document ).ready(function() {
                    if ($("body.drawer-open-index--open, body.drawer-courseindex--open").length) {
                        $('#course-tab2').tab('show');
                    }
                });

                $('#mobileNav, .rui-mobile-nav-btn-close').click(function () {
                    $('#topBar').toggleClass('opened');
                });

                // $( ".editing .editmode-switch-form" ).on( "mouseover", function() {
                //     $('body').addClass('rui-edit-areas');
                // });

                // $( ".editing .editmode-switch-form" ).on( "mouseout", function() {
                //     $('body').removeClass('rui-edit-areas');
                // });

                $("#showBlockArea").on("click", function () {
                    $('body').toggleClass('rui-edit-areas');
                    $('#showBlockArea').toggleClass('active');
                });

                // Remove unnecessary string "..." from default moodle blocks
                // $('.block .footer').each(function () {
                //     var text = $(this).html();
                //     $(this).html(text.replace('...', ''));
                // });

                $('.rui-nav--admin .nav-link').each(function () {
                    var text = $(this).html();
                    $(this).html(text.replace('(', '<span class="mt-1 small d-block">'));
                });
                $('.rui-nav--admin .nav-link').each(function () {
                    var text = $(this).html();
                    $(this).html(text.replace(')', '</span>'));
                });
            });

        }
    };
});
/* jshint ignore:end */
