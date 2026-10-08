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
 * The create-a-share-link form.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_fileshare\form;

use local_fileshare\manager;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Expiry, download cap, optional IP range and who it is for.
 */
class share_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $max = manager::max_expiry_days();

        $mform->addElement('static', 'file', get_string('file', 'local_fileshare'),
            \html_writer::tag('code', s($this->_customdata['filename'])) . ' &middot; ' .
            display_size((int) $this->_customdata['filesize']));

        $mform->addElement('duration', 'expiry', get_string('expiry', 'local_fileshare'),
            ['units' => [DAYSECS, HOURSECS], 'defaultunit' => DAYSECS]);
        $mform->setDefault('expiry', manager::default_expiry_days() * DAYSECS);
        $mform->addHelpButton('expiry', 'expiry', 'local_fileshare');

        $mform->addElement('text', 'maxdownloads', get_string('maxdownloads', 'local_fileshare'), ['size' => 6]);
        $mform->setType('maxdownloads', PARAM_INT);
        $mform->setDefault('maxdownloads', 0);
        $mform->addHelpButton('maxdownloads', 'maxdownloads', 'local_fileshare');

        $mform->addElement('text', 'iprestriction', get_string('iprestriction', 'local_fileshare'), ['size' => 40]);
        $mform->setType('iprestriction', PARAM_TEXT);
        $mform->addHelpButton('iprestriction', 'iprestriction', 'local_fileshare');

        $mform->addElement('text', 'recipient', get_string('recipient', 'local_fileshare'), ['size' => 50]);
        $mform->setType('recipient', PARAM_TEXT);
        $mform->addRule('recipient', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('recipient', 'recipient', 'local_fileshare');

        $mform->addElement('static', 'maxnote', '', get_string('expirymax', 'local_fileshare', $max));

        $this->add_action_buttons(true, get_string('createlink', 'local_fileshare'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $max = manager::max_expiry_days();

        if (empty($data['expiry']) || $data['expiry'] < HOURSECS) {
            $errors['expiry'] = get_string('expirytooshort', 'local_fileshare');
        } else if ($data['expiry'] > $max * DAYSECS) {
            $errors['expiry'] = get_string('expirytoolong', 'local_fileshare', $max);
        }
        if (!empty($data['maxdownloads']) && $data['maxdownloads'] < 0) {
            $errors['maxdownloads'] = get_string('maxdownloadsinvalid', 'local_fileshare');
        }
        if (!empty($data['iprestriction']) && !$this->valid_subnet_list($data['iprestriction'])) {
            $errors['iprestriction'] = get_string('iprestrictioninvalid', 'local_fileshare');
        }
        if (trim((string) ($data['recipient'] ?? '')) === '') {
            $errors['recipient'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Is this a subnet list address_in_subnet() will understand?
     *
     * Validated here rather than at access time, where a malformed value would silently deny
     * every request and look like an expired link.
     *
     * @param string $list
     * @return bool
     */
    protected function valid_subnet_list(string $list): bool {
        foreach (explode(',', $list) as $subnet) {
            $subnet = trim($subnet);
            if ($subnet === '') {
                return false;
            }
            // address_in_subnet() accepts a.b.c.d, a.b.c.d/n and partial a.b. prefixes.
            if (!preg_match('#^[0-9a-fA-F:\.]+(/\d{1,3})?$#', $subnet)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Convert the duration element into whole days for the manager.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->expirydays = max(1, (int) ceil($data->expiry / DAYSECS));
        }
        return $data;
    }
}
