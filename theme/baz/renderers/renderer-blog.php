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

defined('MOODLE_INTERNAL') || die();

// Blog.
require_once($CFG->dirroot . "/blog/renderer.php");
class theme_baz_core_blog_renderer extends core_blog_renderer {

    /**
     * Renders a blog entry
     *
     * @param blog_entry $entry
     * @return string The table HTML
     */
    public function render_blog_entry(blog_entry $entry) {

        global $CFG;

        if (\theme_baz\local\experience::is_on('blog')) {
            return $this->baz_render_blog_entry($entry);
        }

        $syscontext = context_system::instance();

        // $stredit = get_string('edit');
        // $strdelete = get_string('delete');

        $permalinkclass = '';
        if (strpos($this->page->url, 'entryid') == true) {
            $permalinkclass = 'wrapper-fw ';
        }

        // Header.
        $mainclass = 'blog blog-entry ' . $permalinkclass;
        if ($entry->renderable->unassociatedentry) {
            $mainclass .= 'draft';
        } else {
            $mainclass .= $entry->publishstate;
        }
        $o = $this->output->container_start($mainclass, 'b' . $entry->id);
        $o .= $this->output->container_start('blog-entry-author');

        // User picture.
        $o .= $this->output->container_start('d-inline-flex align-items-center justify-content-between w-100');

        // Post by.
        $by = new stdClass();
        $fullname = fullname($entry->renderable->user, has_capability('moodle/site:viewfullnames', $syscontext));
        $userurlparams = array('id' => $entry->renderable->user->id, 'course' => $this->page->course->id);
        $by->name = html_writer::link(new moodle_url('/user/view.php', $userurlparams), $fullname);

        $postcreated = userdate($entry->created);

        $o .= $this->output->container_start('blog-entry-date-box d-inline-flex flex-wrap align-items-center justify-content-between');

        $o .= html_writer::start_div('blog-entry-date');
        $o .= $postcreated;
        $o .= html_writer::end_div();

        // Last modification.
        if ($entry->created != $entry->lastmodified) {
            $o .= $this->output->container(
                    get_string('modified') .
                    ': ' .
                    userdate($entry->lastmodified),
                    'blog-entry-date--mod badge badge-light'
                );
        }
        $o .= $this->output->container_end(); // Blog-entry-date-box end.

        $o .= $this->output->user_picture($entry->renderable->user);
        $o .= $this->output->container_end(); // User picture container end.
        $o .= $this->output->container_end(); // User picture and date container end.

        // Title.
        $titlelink = html_writer::link(
            new moodle_url(
                '/blog/index.php',
                array('entryid' => $entry->id)
            ),
            format_string($entry->subject)
        );
        $o .= $this->output->container_start('blog-entry-topic');
        $o .= $this->output->container($titlelink, 'subject wrapper-fw');

        // Attachments.
        $attachmentsoutputs = array();
        if ($entry->renderable->attachments) {
            foreach ($entry->renderable->attachments as $attachment) {
                $o .= $this->render($attachment, false);
            }
        }

        $o .= $this->output->container_end();

        // Post content.
        $o .= $this->output->container_start('blog-entry-content-box');

        // Entry.
        $o .= $this->output->container_start('blog-entry');

        // Determine text for publish state.
        switch ($entry->publishstate) {
            case 'draft':
                $blogtype = get_string('publishtonoone', 'blog');
                break;
            case 'site':
                $blogtype = get_string('publishtosite', 'blog');
                break;
            case 'public':
                $blogtype = get_string('publishtoworld', 'blog');
                break;
            default:
                $blogtype = '';
                break;
        }

        // Body.
        if (strpos($this->page->url, 'entryid') == true) {
            $o .= format_text($entry->summary, $entry->summaryformat, array('overflowdiv' => true));
        } else {
            $o .= substr(format_string($entry->summary), 0, 300) . '...';
        }

        if (!empty($entry->uniquehash)) {
            // Uniquehash is used as a link to an external blog.
            $url = clean_param($entry->uniquehash, PARAM_URL);
            if (!empty($url)) {
                $o .= $this->output->container_start('externalblog badge badge-secondary badge-link');
                $o .= html_writer::link($url, get_string('linktooriginalentry', 'blog'));
                $o .= $this->output->container_end();
            }
        }

        // Links to tags.
        $o .= $this->output->tag_list(core_tag_tag::get_item_tags('core', 'post', $entry->id));

        // Add associations.
        if (!empty($CFG->useblogassociations) && !empty($entry->renderable->blogassociations)) {

            // First find and show the associated course.
            $assocstr = '';
            $coursesarray = array();
            foreach ($entry->renderable->blogassociations as $assocrec) {
                if ($assocrec->contextlevel == CONTEXT_COURSE) {
                    $coursesarray[] = $this->output->action_icon($assocrec->url, $assocrec->icon, null, array(), true);
                }
            }
            if (!empty($coursesarray)) {
                $assocstr .= get_string('associated', 'blog', get_string('course')) . ': ' . implode(', ', $coursesarray);
            }

            // Now show mod association.
            $modulesarray = array();
            foreach ($entry->renderable->blogassociations as $assocrec) {
                if ($assocrec->contextlevel == CONTEXT_MODULE) {
                    $str = get_string('associated', 'blog', $assocrec->type) . ': ';
                    $str .= $this->output->action_icon($assocrec->url, $assocrec->icon, null, array(), true);
                    $modulesarray[] = $str;
                }
            }
            if (!empty($modulesarray)) {
                if (!empty($coursesarray)) {
                    $assocstr .= '<br/>';
                }
                $assocstr .= implode('<br/>', $modulesarray);
            }

            // Adding the asociations to the output.
            $o .= $this->output->container($assocstr, 'tags');
        }

        if ($entry->renderable->unassociatedentry) {
            $o .= $this->output->container(get_string('associationunviewable', 'blog'), 'noticebox');
        }

        // Commands.
        $o .= $this->output->container_start('blog-entry-footer');
        if ($entry->renderable->usercanedit) {

            // External blog entries should not be edited.
            if (empty($entry->uniquehash)) {
                $icon = '
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
                    <path
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M4.75 19.25L9 18.25L18.2929 8.95711C18.6834 8.56658 18.6834 7.93342 18.2929
                        7.54289L16.4571 5.70711C16.0666 5.31658 15.4334 5.31658 15.0429
                        5.70711L5.75 15L4.75 19.25Z"
                        ></path>
                    <path
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M19.25 19.25H13.75"
                    >
                    </path>
                </svg>';
                $o .= html_writer::link(
                    new moodle_url(
                        '/blog/edit.php',
                        array('action' => 'edit', 'entryid' => $entry->id)
                    ),
                    $icon,
                    array('class' => 'btn btn-icon btn-secondary')
                );
            }
            $icon = '<svg width="18" height="18" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M6.75 7.75L7.59115 17.4233C7.68102 18.4568 8.54622 19.25 9.58363
                19.25H14.4164C15.4538 19.25 16.319 18.4568 16.4088
                17.4233L17.25 7.75"></path>
            <path stroke="currentColor"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M9.75 7.5V6.75C9.75 5.64543 10.6454 4.75 11.75 4.75H12.25C13.3546
                4.75 14.25 5.64543 14.25 6.75V7.5"></path>
            <path stroke="currentColor"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M5 7.75H19"></path>
            </svg>';
            $o .= html_writer::link(
                new moodle_url(
                    '/blog/edit.php',
                    array('action' => 'delete', 'entryid' => $entry->id)
                ),
                $icon,
                array('class' => 'btn btn-icon btn-danger')
            );
        }

        if (strpos($this->page->url, 'entryid') == false) {
            $entryurl = new moodle_url('/blog/index.php', array('entryid' => $entry->id));
            $icon = '
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M13.75 6.75L19.25 12L13.75 17.25"
                >
                </path>
                <path
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M19 12H4.75"
                >
                </path>
            </svg>';
            $o .= html_writer::link($entryurl, $icon, array('class' => 'btn btn-icon btn-secondary'));
        }
        $o .= $this->output->container_end();

        // Adding external blog link.
        if (!empty($entry->renderable->externalblogtext)) {
            $o .= $this->output->container($entry->renderable->externalblogtext, 'externalblog badge badge-light badge-link mt-1');
        }

        // Determine text for publish state.
        switch ($entry->publishstate) {
            case 'draft':
                $blogtype = get_string('publishtonoone', 'blog');
                break;
            case 'site':
                $blogtype = get_string('publishtosite', 'blog');
                break;
            case 'public':
                $blogtype = get_string('publishtoworld', 'blog');
                break;
            default:
                $blogtype = '';
                break;
        }
        $o .= $this->output->container($blogtype, 'audience badge badge-light mt-2');

        // Comments.
        if (strpos($this->page->url, 'entryid') == true) {
            if (!empty($entry->renderable->comment)) {
                $o .= $entry->renderable->comment->output(true);
            }
        }

        $o .= $this->output->container_end();

        // Closing maincontent div.
        $o .= $this->output->container('', 'side options');
        $o .= $this->output->container_end();

        $o .= $this->output->container_end();

        return $o;
    }

    // ------------------------------------------------------------------
    // Bespoke student "experience" blog entry (theme_baz).
    // Rendered only for eligible student-only users (experience::is_on('blog'));
    // everyone else keeps the standard output above. All action URLs (edit /
    // delete / permalink), comments and tag links mirror the standard renderer.
    // ------------------------------------------------------------------

    /**
     * A single blog entry as a navy/gold journal card.
     *
     * @param blog_entry $entry
     * @return string
     */
    protected function baz_render_blog_entry(blog_entry $entry) {
        global $CFG;
        $syscontext = context_system::instance();
        $user = $entry->renderable->user;
        $ispermalink = (bool) strpos((string) $this->page->url, 'entryid');

        // Publish-state chip.
        $states = [
            'draft'  => get_string('publishtonoone', 'blog'),
            'site'   => get_string('publishtosite', 'blog'),
            'public' => get_string('publishtoworld', 'blog'),
        ];
        $statecls = $entry->renderable->unassociatedentry ? 'draft' : $entry->publishstate;
        $statelabel = $states[$entry->publishstate] ?? '';

        // Header: avatar + author + dates + state.
        $fullname = fullname($user, has_capability('moodle/site:viewfullnames', $syscontext));
        $authorlink = html_writer::link(
            new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $this->page->course->id]),
            $fullname, ['class' => 'nm']);
        $dates = userdate($entry->created);
        if ($entry->created != $entry->lastmodified) {
            $dates .= ' · ' . get_string('modified') . ' ' . userdate($entry->lastmodified);
        }
        $head = html_writer::div(
            html_writer::div($this->output->user_picture($user, ['size' => 42]), 'baz-blog-av') .
            html_writer::div($authorlink . html_writer::div($dates, 'dt'), 'baz-blog-by') .
            ($statelabel !== '' ? html_writer::span($statelabel, 'baz-blog-state ' . $statecls) : ''),
            'baz-blog-head');

        // Title (links to permalink).
        $title = html_writer::link(
            new moodle_url('/blog/index.php', ['entryid' => $entry->id]),
            format_string($entry->subject), ['class' => 'baz-blog-title']);

        // Body: full on the permalink page, excerpt in the list.
        if ($ispermalink) {
            $body = format_text($entry->summary, $entry->summaryformat, ['overflowdiv' => true]);
        } else {
            $plain = trim(html_to_text(format_text($entry->summary, $entry->summaryformat), 0, false));
            $body = shorten_text($plain, 300);
        }
        $bodyhtml = html_writer::div($body, 'baz-blog-body');

        // Attachments.
        $attach = '';
        if ($entry->renderable->attachments) {
            foreach ($entry->renderable->attachments as $attachment) {
                $attach .= $this->render($attachment, false);
            }
        }
        if ($attach !== '') {
            $attach = html_writer::div($attach, 'baz-blog-attachments');
        }

        // External blog link.
        $external = '';
        if (!empty($entry->uniquehash)) {
            $url = clean_param($entry->uniquehash, PARAM_URL);
            if (!empty($url)) {
                $external = html_writer::div(
                    html_writer::link($url, get_string('linktooriginalentry', 'blog')),
                    'baz-blog-external');
            }
        }

        // Tags.
        $tags = $this->output->tag_list(
            core_tag_tag::get_item_tags('core', 'post', $entry->id), '', 'baz-blog-tags');

        // Associations (course / module), preserved from the standard renderer.
        $assoc = '';
        if (!empty($CFG->useblogassociations) && !empty($entry->renderable->blogassociations)) {
            $parts = [];
            foreach ($entry->renderable->blogassociations as $assocrec) {
                if ($assocrec->contextlevel == CONTEXT_COURSE) {
                    $parts[] = $this->output->action_icon($assocrec->url, $assocrec->icon, null, [], true);
                }
            }
            foreach ($entry->renderable->blogassociations as $assocrec) {
                if ($assocrec->contextlevel == CONTEXT_MODULE) {
                    $parts[] = $this->output->action_icon($assocrec->url, $assocrec->icon, null, [], true);
                }
            }
            if ($parts) {
                $assoc = html_writer::div(implode(' ', $parts), 'baz-blog-assoc');
            }
        }

        // Footer commands: edit / delete (own entries) + permalink + comments.
        $foot = '';
        $editpen = '<svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
        $delbin = '<svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>';
        $linkico = '<svg viewBox="0 0 24 24"><path d="M9 15l6-6M10 6l1-1a4 4 0 1 1 6 6l-1 1M14 18l-1 1a4 4 0 1 1-6-6l1-1"/></svg>';
        if ($entry->renderable->usercanedit && empty($entry->uniquehash)) {
            $foot .= html_writer::link(
                new moodle_url('/blog/edit.php', ['action' => 'edit', 'entryid' => $entry->id]),
                $editpen . get_string('edit'), ['class' => 'baz-blog-act']);
            $foot .= html_writer::link(
                new moodle_url('/blog/edit.php', ['action' => 'delete', 'entryid' => $entry->id]),
                $delbin . get_string('delete'), ['class' => 'baz-blog-act']);
        }
        if (!$ispermalink) {
            $foot .= html_writer::link(
                new moodle_url('/blog/index.php', ['entryid' => $entry->id]),
                $linkico . get_string('view'), ['class' => 'baz-blog-act']);
        }
        $footer = $foot !== '' ? html_writer::div($foot, 'baz-blog-foot') : '';

        // Comments (permalink view).
        $comments = '';
        if (!empty($CFG->usecomments) && $entry->renderable->comment && $ispermalink) {
            $comments = html_writer::div($entry->renderable->comment->output(true), 'baz-blog-comments');
        }

        $unviewable = $entry->renderable->unassociatedentry
            ? html_writer::div(get_string('associationunviewable', 'blog'), 'baz-blog-notice') : '';

        return html_writer::tag('article',
            $head . $title . $bodyhtml . $attach . $external . $tags . $assoc . $unviewable . $footer . $comments,
            ['class' => 'baz-blog-card ' . $statecls . ($ispermalink ? ' is-single' : ''), 'id' => 'b' . $entry->id]);
    }

    /**
     * Renders an entry attachment
     *
     * Print link for non-images and returns images as HTML
     *
     * @param blog_entry_attachment $attachment
     * @return string List of attachments depending on the $return input
     */
    public function render_blog_entry_attachment(blog_entry_attachment $attachment) {

        $syscontext = context_system::instance();

        // Image attachments don't get printed as links.
        if (file_mimetype_in_typegroup($attachment->file->get_mimetype(), 'web_image')) {
            $attrs = array('src' => $attachment->url, 'alt' => '');
            $o = html_writer::empty_tag('img', $attrs);
            $class = 'blog-entry-cover';
        } else {
            $image = $this->output->pix_icon(
                file_file_icon($attachment->file),
                $attachment->filename,
                'moodle',
                array('class' => 'icon')
            );
            $o = html_writer::link($attachment->url, $image);
            $o .= format_text(
                html_writer::link($attachment->url, $attachment->filename),
                FORMAT_HTML,
                array('context' => $syscontext)
            );
            $class = 'attachments';
        }

        return $this->output->container($o, $class);
    }
}
