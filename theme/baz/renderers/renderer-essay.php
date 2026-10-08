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
 * Essay question renderer: preview student attachments instead of downloading them.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The essay renderer does not pull in its own base class, and renderers.php is included while the
// theme is still being constructed - long before the question engine has loaded anything. Without
// this, extending qtype_essay_renderer fatals with "Class qtype_renderer not found" on every page
// that builds the theme before a question, which includes theme/styles.php.
require_once($CFG->dirroot . '/question/type/rendererbase.php');
require_once($CFG->dirroot . '/question/type/essay/renderer.php');

/**
 * Attachments on an essay answer open in a preview rather than downloading.
 *
 * core builds every attachment link with file_encode_url(..., true) - the third argument is
 * $forcedownload - so a marker clicking a submitted file always gets a download, even for the
 * PDFs that make up the overwhelming majority of what students attach. This overrides the one
 * method that renders those links.
 *
 * Two things change. The href drops forcedownload, so a middle-click or a JS-less browser opens
 * previewable types in a new tab instead of saving them. And where the file can be shown in a
 * modal, the link carries the data attributes that mod_assign's file_preview module already
 * understands, so a plain click opens the same preview faculty get on the assignment grading
 * screen - including the Office Online viewer for Word documents and Video.js for media. The
 * modal offers a download button, and the force-download URL is what it points at.
 *
 * Deliberately reuses mod_assign's module, template and admin settings rather than growing a
 * parallel preview path; see the enablepreview / enableofficepreview settings under
 * assignsubmission_file, which gate both screens together.
 *
 * Access is unaffected. qtype_essay\question::check_file_access() ignores $forcedownload - the
 * decision is the question's own attachments setting plus the review capability that
 * quiz_question_pluginfile() already enforced.
 */
class theme_baz_qtype_essay_renderer extends qtype_essay_renderer {

    /** @var string|null Cached user key for tokenpluginfile.php URLs. */
    protected $fileaccesstoken = null;

    /**
     * Displays any attachments on a read-only essay answer.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function files_read_only(question_attempt $qa, question_display_options $options) {
        global $CFG;

        $files = $qa->get_last_qt_files('attachments', $options->context->id);
        $filelist = [];
        $haspreview = false;

        $step = $qa->get_last_step_with_qt_var('attachments');

        foreach ($files as $file) {
            $linkattrs = [];
            if ($previewattrs = $this->get_file_preview_attributes($qa, $file)) {
                $linkattrs = $previewattrs;
                $haspreview = true;
            } else {
                // Nothing to preview, so let the browser do what it can with the file itself.
                $linkattrs['target'] = '_blank';
                $linkattrs['rel'] = 'noopener';
            }

            $out = html_writer::link($this->response_file_url($qa, $file),
                $this->output->pix_icon(file_file_icon($file), get_mimetype_description($file),
                    'moodle', ['class' => 'icon']) . ' ' . s($file->get_filename()), $linkattrs);

            $out .= $this->share_action($qa, $file);

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir . '/plagiarismlib.php');

                $out .= plagiarism_get_links([
                    'context' => $options->context->id,
                    'component' => $qa->get_question()->qtype->plugin_name(),
                    'area' => $qa->get_usage_id(),
                    'itemid' => $qa->get_slot(),
                    'userid' => $step->get_user_id(),
                    'file' => $file]);
            }
            $filelist[] = html_writer::tag('li', $out, ['class' => 'mb-2']);
        }

        if ($haspreview) {
            $this->page->requires->js_call_amd('mod_assign/file_preview', 'init');
        }

        $labelbyid = $qa->get_qt_field_name('attachments') . '_label';

        $fileslabel = $options->add_question_identifier_to_label(get_string('answerfiles', 'qtype_essay'));
        $output = html_writer::tag('h4', $fileslabel, ['id' => $labelbyid, 'class' => 'sr-only']);
        $output .= html_writer::tag('ul', implode($filelist), [
            'aria-labelledby' => $labelbyid,
            'class' => 'list-unstyled m-0',
        ]);

        return $output;
    }

    /**
     * A "Share link" action beside the file, when local_fileshare is installed and permits it.
     *
     * Guarded by class_exists so the theme keeps working with the plugin absent - the same
     * pattern the theme already uses for local_studentid.
     *
     * @param question_attempt $qa
     * @param stored_file $file
     * @return string HTML fragment, empty when sharing is unavailable
     */
    protected function share_action(question_attempt $qa, stored_file $file): string {
        if (!class_exists('\local_fileshare\manager')) {
            return '';
        }
        try {
            if (!\local_fileshare\manager::can_share($qa->get_usage_id())) {
                return '';
            }
            $url = new moodle_url('/local/fileshare/share.php', [
                'qubaid' => $qa->get_usage_id(),
                'slot' => $qa->get_slot(),
                'file' => $file->get_pathnamehash(),
            ]);
            return ' ' . html_writer::link($url, get_string('createlink', 'local_fileshare'),
                ['class' => 'small ml-2']);
        } catch (\Throwable $e) {
            debugging('theme_baz share action: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * The pluginfile URL for a response attachment.
     *
     * Same path core's question_attempt::get_response_file_url() builds, with forcedownload as a
     * parameter rather than hardcoded on.
     *
     * @param question_attempt $qa
     * @param stored_file $file
     * @param bool $forcedownload
     * @return string
     */
    protected function response_file_url(question_attempt $qa, stored_file $file, bool $forcedownload = false): string {
        return file_encode_url(new moodle_url('/pluginfile.php'), '/' . implode('/', [
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $qa->get_usage_id(),
                $qa->get_slot(),
                $file->get_itemid()]) .
                $file->get_filepath() . $file->get_filename(), $forcedownload);
    }

    /**
     * Data attributes that turn the file name into a mod_assign/file_preview trigger.
     *
     * Mirrors mod_assign's rules about what is previewable so the two screens stay in step.
     *
     * @param question_attempt $qa
     * @param stored_file $file
     * @return array|null Attributes for the trigger, or null when the file cannot be previewed.
     */
    protected function get_file_preview_attributes(question_attempt $qa, stored_file $file): ?array {
        if (!get_config('assignsubmission_file', 'enablepreview')) {
            return null;
        }

        $mimetype = (string) $file->get_mimetype();
        $officemimetypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $inlinepreviewable = ($mimetype === 'application/pdf')
            || (strpos($mimetype, 'image/') === 0)
            || ($mimetype === 'text/plain');
        $officeenabled = (bool) get_config('assignsubmission_file', 'enableofficepreview');
        $isoffice = in_array($mimetype, $officemimetypes, true);
        $ismedia = str_starts_with($mimetype, 'video/') || str_starts_with($mimetype, 'audio/');

        if (!$inlinepreviewable && !$ismedia && !($isoffice && $officeenabled)) {
            return null;
        }

        $previewurl = $this->response_file_url($qa, $file);
        $attributes = [
            'data-action' => 'assign-preview-file',
            'data-filename' => $file->get_filename(),
            'data-mimetype' => $mimetype,
            'data-previewurl' => $previewurl,
            'data-downloadurl' => $this->response_file_url($qa, $file, true),
        ];
        if ($isoffice && $officeenabled && ($officeurl = $this->get_office_preview_url($qa, $file))) {
            $attributes['data-officeurl'] = $officeurl;
        }
        if ($ismedia) {
            $attributes['data-ismedia'] = '1';
            // Building the player here is also what registers the media_videojs loader on the
            // page; the modal relies on it to bind Video.js to the markup it injects.
            $mediamanager = core_media_manager::instance($this->page);
            $embedoptions = [
                core_media_manager::OPTION_TRUSTED => true,
                core_media_manager::OPTION_BLOCK => true,
            ];
            $embedurl = new moodle_url($previewurl);
            if ($mediamanager->can_embed_url($embedurl, $embedoptions)) {
                $attributes['data-embed'] = $mediamanager->embed_url(
                    $embedurl, $file->get_filename(), 0, 0, $embedoptions);
            } else {
                $attributes['data-nativemedia'] = str_starts_with($mimetype, 'audio/') ? 'audio' : 'video';
            }
        }

        return $attributes;
    }

    /**
     * A Microsoft Office Online viewer URL for an Office document.
     *
     * The viewer fetches the document over the internet, so the file is exposed through a
     * tokenised tokenpluginfile.php URL - the token authenticates as the current viewer and
     * pluginfile.php still runs quiz_question_pluginfile()'s capability checks. The attachment
     * therefore transits Microsoft, which is what assignsubmission_file/enableofficepreview gates.
     *
     * @param question_attempt $qa
     * @param stored_file $file
     * @return string|null The viewer URL, or null if a token could not be created.
     */
    protected function get_office_preview_url(question_attempt $qa, stored_file $file): ?string {
        global $CFG, $USER;

        if ($this->fileaccesstoken === null) {
            $this->fileaccesstoken = get_user_key('core_files', $USER->id);
        }
        if (empty($this->fileaccesstoken)) {
            return null;
        }

        $relativepath = '/' . $this->fileaccesstoken .
            '/' . $file->get_contextid() .
            '/' . $file->get_component() .
            '/' . $file->get_filearea() .
            '/' . $qa->get_usage_id() .
            '/' . $qa->get_slot() .
            '/' . $file->get_itemid() .
            $file->get_filepath() .
            $file->get_filename();

        if (!empty($CFG->slasharguments)) {
            $fileurl = $CFG->wwwroot . '/tokenpluginfile.php' . $relativepath;
        } else {
            $fileurl = $CFG->wwwroot . '/tokenpluginfile.php?file=' . rawurlencode($relativepath);
        }

        return 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($fileurl);
    }
}
