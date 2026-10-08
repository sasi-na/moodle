<?php
namespace local_eventreg;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps a stored form field to a moodleform element, and reads / validates /
 * formats the value that comes back. Shared by the learner submission form
 * (rendering + saving) and the report/export (formatting).
 *
 * Each field renders under the element name "f{fieldid}". Choice values are
 * stored as the chosen option label(s); a checkbox field stores a JSON array of
 * the selected labels in a single answer value.
 *
 * A file field keeps the uploaded files in the standard file API — system
 * context, component local_eventreg, area "answer", itemid = the answer record
 * id — and stores a JSON array of their filenames in the answer value so the
 * report and export need no extra file query per row.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_builder {

    /** File area holding learner attachments; itemid is the answer record id. */
    const FILE_AREA = 'answer';

    /** Component owning that file area. */
    const FILE_COMPONENT = 'local_eventreg';

    /** All supported field types => lang label. */
    public static function field_types(): array {
        return [
            'text'     => get_string('type_text', 'local_eventreg'),
            'textarea' => get_string('type_textarea', 'local_eventreg'),
            'radio'    => get_string('type_radio', 'local_eventreg'),
            'checkbox' => get_string('type_checkbox', 'local_eventreg'),
            'select'   => get_string('type_select', 'local_eventreg'),
            'date'     => get_string('type_date', 'local_eventreg'),
            'email'    => get_string('type_email', 'local_eventreg'),
            'number'   => get_string('type_number', 'local_eventreg'),
            'phone'    => get_string('type_phone', 'local_eventreg'),
            'rating'   => get_string('type_rating', 'local_eventreg'),
            'file'     => get_string('type_file', 'local_eventreg'),
            'description' => get_string('type_description', 'local_eventreg'),
        ];
    }

    /**
     * Whether a type only displays copy and takes no answer.
     *
     * A description is a block of text sitting between questions - payment
     * instructions, bank details, a consent notice. It asks nothing, so it stores no
     * answer row, is never required, and never becomes a report or export column.
     * Before it existed that copy had to be crammed into the label of whichever
     * question happened to sit next to it, where it read as part of the question.
     */
    public static function is_display_type(string $type): bool {
        return $type === 'description';
    }

    /**
     * Resolve a stored text format, defaulting to FORMAT_MOODLE.
     *
     * FORMAT_MOODLE and not FORMAT_HTML is the deliberate default: a row written before
     * the editor existed holds plain text with real newlines in it, and only
     * FORMAT_MOODLE turns those into line breaks. Defaulting to HTML would leave every
     * label authored before this change rendering as one flattened line.
     *
     * @param int|string|null $format
     * @return int
     */
    public static function text_format($format): int {
        return ($format === null || $format === '') ? FORMAT_MOODLE : (int) $format;
    }

    /**
     * A field's label as plain one-line text.
     *
     * For the places a label has to be a string rather than markup: a table heading, a
     * CSV column name, a delete confirmation, the builder's own list. Since the label
     * became an editor those would otherwise print raw tags, and a heading carrying a
     * <p> would break the table layout.
     *
     * @param \stdClass $field
     * @return string
     */
    public static function plain_label(\stdClass $field): string {
        $text = format_text($field->label, self::text_format($field->labelformat ?? null));
        // html_to_text puts real newlines back in; a heading wants one line.
        $text = preg_replace('/\s+/u', ' ', html_to_text($text, 0, false));

        return trim((string) $text);
    }

    /**
     * Only the fields that hold an answer.
     *
     * Reports, exports and the answer-saving loop all work per answer, so a display
     * field has no place in any of them: it would add an always-empty report column and
     * an empty answer row per registration. Filtering where the fields are fetched keeps
     * that decision in one place instead of a guard inside every loop.
     *
     * @param \stdClass[] $fields
     * @return \stdClass[] re-indexed
     */
    public static function answerable(array $fields): array {
        return array_values(array_filter($fields, fn($f) => !self::is_display_type($f->type)));
    }

    /** Types that use the options (choices) list. */
    public static function is_choice_type(string $type): bool {
        return in_array($type, ['radio', 'checkbox', 'select'], true);
    }

    /** Whether a type stores its answer as uploaded files. */
    public static function is_file_type(string $type): bool {
        return $type === 'file';
    }

    /** Types that have no use for the choices list (drives the builder's hideIf). */
    public static function non_choice_types(): array {
        return array_values(array_diff(array_keys(self::field_types()), ['radio', 'checkbox', 'select']));
    }

    /**
     * Filemanager options for a file field, resolved from its settings JSON.
     *
     * A stored maxbytes of 0 means "whatever the site allows", so it is resolved
     * to a real byte count here — both the file picker and the draft-area save
     * read 0 as "no limit at all", which is not what the admin picked.
     */
    public static function file_options(\stdClass $field): array {
        global $CFG;

        $s = self::settings($field);
        $accept = trim((string) ($s['accept'] ?? '*'));
        return [
            'subdirs'        => 0,
            'maxfiles'       => max(1, (int) ($s['maxfiles'] ?? 1)),
            'maxbytes'       => get_user_max_upload_file_size(\context_system::instance(),
                (int) $CFG->maxbytes, 0, (int) ($s['maxbytes'] ?? 0)),
            'accepted_types' => $accept === '' ? '*' : $accept,
            // Learner uploads must be real copies, never repository references.
            'return_types'   => FILE_INTERNAL,
        ];
    }

    /** Decode a field's options JSON to a plain array of labels. */
    public static function options(\stdClass $field): array {
        $opts = json_decode((string) $field->options, true);
        return is_array($opts) ? array_values(array_filter(array_map('strval', $opts), fn($v) => $v !== '')) : [];
    }

    /** Decode a field's settings JSON to an assoc array. */
    public static function settings(\stdClass $field): array {
        $s = json_decode((string) $field->settings, true);
        return is_array($s) ? $s : [];
    }

    /** The element name for a field. */
    public static function element_name(\stdClass $field): string {
        return 'f' . (int) $field->id;
    }

    // ---- rendering ----------------------------------------------------------

    /**
     * Add the element(s) for one field to a moodleform.
     *
     * @param \MoodleQuickForm $mform
     * @param \stdClass $field
     */
    public static function add_field($mform, \stdClass $field): void {
        $name = self::element_name($field);
        // format_text, not format_string: the label is authored in an editor and may
        // carry paragraphs, lists and line breaks. format_string escapes and leaves
        // newlines as whitespace for the browser to collapse, which silently flattened
        // multi-line labels into one run of text.
        $label = format_text($field->label, self::text_format($field->labelformat ?? null));
        $required = !empty($field->required);
        $settings = self::settings($field);

        // Display-only: the label IS the content, so it is rendered as a block on its
        // own rather than as the caption of an input. Returns before the required rule
        // and the help row, neither of which means anything without a control.
        if (self::is_display_type($field->type)) {
            $mform->addElement('static', $name . '_desc', '',
                \html_writer::div($label, 'eventreg-fielddesc'));
            return;
        }

        switch ($field->type) {
            case 'textarea':
                $mform->addElement('textarea', $name, $label, ['rows' => 4, 'style' => 'width:100%;']);
                $mform->setType($name, PARAM_TEXT);
                if ($required) {
                    $mform->addRule($name, get_string('required', 'local_eventreg'), 'required', null, 'client');
                }
                break;

            case 'radio':
                $radios = [];
                foreach (self::options($field) as $opt) {
                    $radios[] = $mform->createElement('radio', $name, '', format_string($opt), $opt);
                }
                $mform->addGroup($radios, $name . '_grp', $label, '<br/>', false);
                break;

            case 'checkbox':
                $boxes = [];
                foreach (self::options($field) as $k => $opt) {
                    $boxes[] = $mform->createElement('advcheckbox', $name . '_' . $k, '', format_string($opt),
                        ['group' => 1], [0, $opt]);
                }
                $mform->addGroup($boxes, $name . '_grp', $label, '<br/>', false);
                break;

            case 'select':
                $choices = ['' => get_string('choosedots', 'local_eventreg')];
                foreach (self::options($field) as $opt) {
                    $choices[$opt] = format_string($opt);
                }
                $mform->addElement('select', $name, $label, $choices);
                $mform->setType($name, PARAM_TEXT);
                if ($required) {
                    $mform->addRule($name, get_string('required', 'local_eventreg'), 'required', null, 'client');
                }
                break;

            case 'date':
                $mform->addElement('date_selector', $name, $label, ['optional' => !$required]);
                break;

            case 'rating':
                $min = isset($settings['min']) ? (int) $settings['min'] : 1;
                $max = isset($settings['max']) ? (int) $settings['max'] : 5;
                if ($max < $min) {
                    $max = $min;
                }
                $radios = [];
                for ($i = $min; $i <= $max; $i++) {
                    $radios[] = $mform->createElement('radio', $name, '', (string) $i, (string) $i);
                }
                $grouplabel = $label;
                if (!empty($settings['lowlabel']) || !empty($settings['highlabel'])) {
                    $grouplabel .= ' ' . \html_writer::tag('small',
                        '(' . s($settings['min'] ?? $min) . ' = ' . format_string($settings['lowlabel'] ?? '')
                        . ', ' . s($settings['max'] ?? $max) . ' = ' . format_string($settings['highlabel'] ?? '') . ')',
                        ['class' => 'text-muted']);
                }
                $mform->addGroup($radios, $name . '_grp', $grouplabel, ' ', false);
                break;

            case 'file':
                // The element mints its own draft item id when the value is empty,
                // so a fresh submission form needs no file_prepare_draft_area().
                $mform->addElement('filemanager', $name, $label, null, self::file_options($field));
                if ($required) {
                    // Server-side only: an empty filemanager still submits a draft
                    // item id, so the client rule would always pass. moodleform's
                    // validate_draft_files() checks the draft area itself (and also
                    // enforces maxfiles on every filemanager element).
                    $mform->addRule($name, get_string('requiredfile', 'local_eventreg'), 'required');
                }
                break;

            case 'email':
            case 'number':
            case 'phone':
            case 'text':
            default:
                $mform->addElement('text', $name, $label, ['style' => 'width:100%;max-width:32rem;']);
                $mform->setType($name, PARAM_RAW_TRIMMED);
                if (!empty($settings['maxlength'])) {
                    $mform->addRule($name, null, 'maxlength', (int) $settings['maxlength'], 'client');
                }
                if ($required) {
                    $mform->addRule($name, get_string('required', 'local_eventreg'), 'required', null, 'client');
                }
                break;
        }

        if (!empty($field->helptext)) {
            $mform->addElement('static', $name . '_help', '',
                \html_writer::tag('small',
                    format_text($field->helptext, self::text_format($field->helptextformat ?? null)),
                    ['class' => 'text-muted']));
        }
    }

    // ---- reading + validation ----------------------------------------------

    /**
     * Extract the storable string value for a field from submitted form data.
     * Checkbox fields return a JSON array of selected labels.
     *
     * @param \stdClass $field
     * @param \stdClass $data Submitted moodleform data.
     * @return string
     */
    public static function extract_value(\stdClass $field, \stdClass $data): string {
        $name = self::element_name($field);

        // Nothing was asked, so there is nothing to store. Callers skip display fields
        // before they get here; this is the belt to that braces.
        if (self::is_display_type($field->type)) {
            return '';
        }

        if ($field->type === 'checkbox') {
            $selected = [];
            foreach (self::options($field) as $k => $opt) {
                $key = $name . '_' . $k;
                if (!empty($data->$key)) {
                    $selected[] = (string) $data->$key;
                }
            }
            return json_encode(array_values($selected));
        }

        if ($field->type === 'date') {
            // date_selector returns a unix timestamp (0 when optional + disabled).
            return isset($data->$name) ? (string) (int) $data->$name : '';
        }

        if (self::is_file_type($field->type)) {
            // The submitted value is only a draft item id. Files are moved into
            // place by save_answer_files() once the answer row exists, and that
            // writes the real filename list.
            return '';
        }

        return isset($data->$name) ? trim((string) $data->$name) : '';
    }

    // ---- file answers -------------------------------------------------------

    /**
     * Move a file field's uploaded files out of the user's draft area and into the
     * answer's file area. Must run after the answer row is committed, and outside
     * any DB transaction (the file API writes to disk).
     *
     * @param \stdClass $field
     * @param \stdClass $data Submitted moodleform data.
     * @param int $answerid The answer record the files belong to.
     * @param int $contextid System context id.
     * @return string JSON array of the stored filenames, for the answer value.
     */
    public static function save_answer_files(\stdClass $field, \stdClass $data, int $answerid, int $contextid): string {
        $name = self::element_name($field);
        $draftitemid = (int) ($data->$name ?? 0);
        if ($draftitemid) {
            file_save_draft_area_files($draftitemid, $contextid, self::FILE_COMPONENT, self::FILE_AREA,
                $answerid, self::file_options($field));
        }
        return json_encode(self::stored_filenames($answerid, $contextid));
    }

    /**
     * The filenames actually held in an answer's file area.
     *
     * @param int $answerid
     * @param int|null $contextid Defaults to the system context.
     * @return string[]
     */
    public static function stored_filenames(int $answerid, ?int $contextid = null): array {
        $contextid = $contextid ?? \context_system::instance()->id;
        $files = get_file_storage()->get_area_files($contextid, self::FILE_COMPONENT, self::FILE_AREA,
            $answerid, 'filename', false);
        return array_values(array_map(fn($f) => $f->get_filename(), $files));
    }

    /** The filenames recorded in a file answer's value. */
    public static function value_filenames(?string $value): array {
        $arr = json_decode((string) $value, true);
        return is_array($arr) ? array_values(array_map('strval', $arr)) : [];
    }

    /** Download URL for one file of an answer. */
    public static function file_url(int $answerid, string $filename): \moodle_url {
        return \moodle_url::make_pluginfile_url(\context_system::instance()->id, self::FILE_COMPONENT,
            self::FILE_AREA, $answerid, '/', $filename, true);
    }

    /**
     * A file answer as download links (report / confirmation page). Returns HTML.
     */
    public static function file_links(int $answerid, ?string $value): string {
        $links = [];
        foreach (self::value_filenames($value) as $filename) {
            $links[] = \html_writer::link(self::file_url($answerid, $filename), s($filename));
        }
        return implode(\html_writer::empty_tag('br'), $links);
    }

    /**
     * A file answer for a spreadsheet cell: one "name — url" line per file, so the
     * download link survives the export.
     */
    public static function file_export_value(int $answerid, ?string $value): string {
        $lines = [];
        foreach (self::value_filenames($value) as $filename) {
            $lines[] = $filename . ' — ' . self::file_url($answerid, $filename)->out(false);
        }
        return implode("\n", $lines);
    }

    /**
     * Server-side validation for one field. Returns [elementname => error].
     *
     * @param \stdClass $field
     * @param \stdClass $data
     * @return array
     */
    public static function validate_field(\stdClass $field, $data): array {
        $errors = [];
        $name = self::element_name($field);
        $required = !empty($field->required);
        $settings = self::settings($field);
        $data = (object) $data;

        // A display field has no control to validate, and can never be "required".
        if (self::is_display_type($field->type)) {
            return $errors;
        }

        // Nothing to check here for file fields: the submitted value is only a draft
        // item id. Required-ness and the file count are validated by moodleform's
        // validate_draft_files(), and the extensions by the element's own
        // validateSubmitValue().
        if (self::is_file_type($field->type)) {
            return $errors;
        }

        // Required checks for the group / date types not covered by addRule.
        if ($required) {
            if ($field->type === 'radio' || $field->type === 'rating') {
                if (!isset($data->$name) || $data->$name === '') {
                    $errors[$name . '_grp'] = get_string('required', 'local_eventreg');
                }
            } else if ($field->type === 'checkbox') {
                $any = false;
                foreach (self::options($field) as $k => $opt) {
                    $key = $name . '_' . $k;
                    if (!empty($data->$key)) {
                        $any = true;
                        break;
                    }
                }
                if (!$any) {
                    $errors[$name . '_grp'] = get_string('required', 'local_eventreg');
                }
            }
        }

        $value = isset($data->$name) ? trim((string) $data->$name) : '';
        if ($value === '') {
            return $errors; // Nothing more to validate on an empty optional field.
        }

        switch ($field->type) {
            case 'email':
                if (!validate_email($value)) {
                    $errors[$name] = get_string('invalidemail', 'local_eventreg');
                }
                break;

            case 'phone':
                if (!preg_match('/^[0-9+()\-\s]{6,20}$/', $value)) {
                    $errors[$name] = get_string('invalidphone', 'local_eventreg');
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[$name] = get_string('invalidnumber', 'local_eventreg');
                } else {
                    $num = (float) $value;
                    if (isset($settings['min']) && $settings['min'] !== '' && $num < (float) $settings['min']) {
                        $errors[$name] = get_string('numbertoolow', 'local_eventreg', $settings['min']);
                    } else if (isset($settings['max']) && $settings['max'] !== '' && $num > (float) $settings['max']) {
                        $errors[$name] = get_string('numbertoohigh', 'local_eventreg', $settings['max']);
                    }
                }
                break;
        }

        return $errors;
    }

    // ---- formatting ---------------------------------------------------------

    /**
     * Format a stored answer value for display in the report / export.
     *
     * @param \stdClass $field
     * @param string|null $value
     * @return string Plain text.
     */
    public static function format_value(\stdClass $field, ?string $value): string {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        switch ($field->type) {
            case 'checkbox':
                $arr = json_decode($value, true);
                return is_array($arr) ? implode(', ', $arr) : $value;

            case 'date':
                $ts = (int) $value;
                return $ts > 0 ? userdate($ts, get_string('strftimedate', 'core_langconfig')) : '';

            case 'file':
                return implode(', ', self::value_filenames($value));

            default:
                return $value;
        }
    }
}
