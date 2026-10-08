<?php
/**
 * Weather block - shows current weather and a 3-day forecast for a city.
 *
 * @package    block_weather
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_weather extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_weather');
    }

    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        }
    }

    public function applicable_formats() {
        return ['all' => true];
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object)['text' => '', 'footer' => ''];

        $this->content->text = \block_weather\api::render(trim($this->config->city ?? ''), 'block_weather/content');
        return $this->content;
    }
}
