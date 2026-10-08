<?php
/**
 * Weather block - per-block settings (city, title).
 *
 * @package    block_weather
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_weather_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $mform->addElement('text', 'config_title', get_string('config_title', 'block_weather'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addElement('text', 'config_city', get_string('config_city', 'block_weather'));
        $mform->setType('config_city', PARAM_TEXT);
        $mform->addHelpButton('config_city', 'config_city', 'block_weather');
    }
}
