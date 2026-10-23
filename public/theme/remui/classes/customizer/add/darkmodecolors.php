<?php
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
 * Theme customizer dark mode colors trait
 *
 * @package   theme_remui
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_remui\customizer\add;

trait darkmodecolors {

    /**
     * Add dark mode color settings.
     *
     * @return void
     */
    private function add_darkmode_colors() {
        $panel = 'darkmodecolors';
        $this->add_panel('darkmodecolors', get_string('darkmodecolors', 'theme_remui'), 'global');

        $this->add_darkmode_background_colors($panel);
        $this->add_darkmode_foreground_colors($panel);
    }

    /**
     * Add dark mode background color settings.
     * @param string $panel Panel id
     * @return void
     */
    private function add_darkmode_background_colors($panel) {
        $this->add_setting(
            'heading_start',
            'darkmode-bg-heading',
            get_string('darkmode-bg-heading', 'theme_remui'),
            $panel,
            [
                'collapsed' => false
            ]
        );

        // Background color mappings.
        $bgcolors = [
            'white'  => ['default' => '#181a1b', 'label' => 'darkmode-bg-white'],
            'grey'   => ['default' => '#1f2122', 'label' => 'darkmode-bg-grey'],
            'black'  => ['default' => '#e8e6e3', 'label' => 'darkmode-bg-black'],
            'blue'   => ['default' => '#1a3a5c', 'label' => 'darkmode-bg-blue'],
            'red'    => ['default' => 'red',     'label' => 'darkmode-bg-red'],
            'green'  => ['default' => 'green',   'label' => 'darkmode-bg-green'],
            'yellow' => ['default' => 'yellow',  'label' => 'darkmode-bg-yellow'],
            'pink'   => ['default' => 'pink',    'label' => 'darkmode-bg-pink'],
            'purple' => ['default' => 'purple',  'label' => 'darkmode-bg-purple'],
            'orange' => ['default' => 'orange',  'label' => 'darkmode-bg-orange'],
            'brown'  => ['default' => 'brown',   'label' => 'darkmode-bg-brown'],
        ];

        foreach ($bgcolors as $key => $config) {
            $label = get_string($config['label'], 'theme_remui');
            $name = 'darkmode-bg-' . $key;
            $this->add_setting(
                'color',
                $name,
                $label,
                $panel,
                [
                    'help' => get_string('darkmode-bg-color_help', 'theme_remui', $key),
                    'default' => $config['default']
                ]
            );
        }

        $this->add_setting(
            'heading_end',
            'darkmode-bg-heading',
            '',
            $panel
        );
    }

    /**
     * Add dark mode foreground (text) color settings.
     * @param string $panel Panel id
     * @return void
     */
    private function add_darkmode_foreground_colors($panel) {
        $this->add_setting(
            'heading_start',
            'darkmode-fg-heading',
            get_string('darkmode-fg-heading', 'theme_remui'),
            $panel,
            [
                'collapsed' => true
            ]
        );

        // Foreground color mappings.
        $fgcolors = [
            'black'  => ['default' => '#e8e6e3', 'label' => 'darkmode-fg-black'],
            'white'  => ['default' => '#181a1b', 'label' => 'darkmode-fg-white'],
            'grey'   => ['default' => '#b5b1ab', 'label' => 'darkmode-fg-grey'],
            'blue'   => ['default' => '#6ca4e8', 'label' => 'darkmode-fg-blue'],
            'red'    => ['default' => 'red',     'label' => 'darkmode-fg-red'],
            'green'  => ['default' => 'green',   'label' => 'darkmode-fg-green'],
            'yellow' => ['default' => 'yellow',  'label' => 'darkmode-fg-yellow'],
            'pink'   => ['default' => 'pink',    'label' => 'darkmode-fg-pink'],
            'purple' => ['default' => 'purple',  'label' => 'darkmode-fg-purple'],
            'orange' => ['default' => 'orange',  'label' => 'darkmode-fg-orange'],
            'brown'  => ['default' => 'brown',   'label' => 'darkmode-fg-brown'],
        ];

        foreach ($fgcolors as $key => $config) {
            $label = get_string($config['label'], 'theme_remui');
            $name = 'darkmode-fg-' . $key;
            $this->add_setting(
                'color',
                $name,
                $label,
                $panel,
                [
                    'help' => get_string('darkmode-fg-color_help', 'theme_remui', $key),
                    'default' => $config['default']
                ]
            );
        }

        $this->add_setting(
            'heading_end',
            'darkmode-fg-heading',
            '',
            $panel
        );
    }
}

