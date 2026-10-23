<?php
defined('MOODLE_INTERNAL') || die();

class block_sessionattendance extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_sessionattendance');
    }

    public function applicable_formats() {
        return ['my' => true];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $PAGE->requires->css('/blocks/sessionattendance/styles.css');

        $courses = [
            ['name' => 'AI Tools for Business Productivity', 'total' => 23, 'present' => 2, 'absent' => 21, 'percent' => 8.7],
            ['name' => 'Data Analytics and Visualization', 'total' => 7, 'present' => 0, 'absent' => 7, 'percent' => 0.0],
            ['name' => 'Financial and Management Accounting with AI Tools', 'total' => 7, 'present' => 1, 'absent' => 6, 'percent' => 14.3],
            ['name' => 'Managerial Economics in the Age of AI', 'total' => 7, 'present' => 2, 'absent' => 5, 'percent' => 28.6],
            ['name' => 'Marketing Principles with Generative AI', 'total' => 7, 'present' => 1, 'absent' => 6, 'percent' => 14.3],
            ['name' => 'Orientation | MBA AI - JAIN Online', 'total' => 1, 'present' => 0, 'absent' => 1, 'percent' => 0.0],
        ];

        $details = [
            'AI Tools for Business Productivity' => [
                ['name' => 'AI Tools for Business Productivity - Teams Session 01', 'date' => 'Mar 03, 2026 07:00 PM', 'spent' => '18M 20S', 'duration' => '1H 00M 00S', 'percent' => '30.6%', 'status' => 'present', 'feedback' => '', 'type' => 'Teams Session'],
                ['name' => 'AI Tools for Business Productivity - Teams Session 02', 'date' => 'Mar 10, 2026 07:00 PM', 'spent' => '00M 00S', 'duration' => '56M 14S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
            ],
            'Data Analytics and Visualization' => [
                ['name' => 'Data Analytics and Visualization - Teams Session 01', 'date' => 'Feb 05, 2026 11:00 AM', 'spent' => '00M 00S', 'duration' => '52M 18S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
                ['name' => 'Data Analytics and Visualization - Teams Session 02', 'date' => 'Feb 12, 2026 11:00 AM', 'spent' => '00M 00S', 'duration' => '49M 40S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
            ],
            'Financial and Management Accounting with AI Tools' => [
                ['name' => 'Financial and Management Accounting with AI Tools - Business Session 7', 'date' => 'Feb 07, 2026 11:00 AM', 'spent' => '0M 0S', 'duration' => '1H 50M 33S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
                ['name' => 'Financial and Management Accounting with AI Tools - Business Session 6', 'date' => 'Jan 25, 2026 11:00 AM', 'spent' => '0M 0S', 'duration' => '43M 57S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
                ['name' => 'Financial and Management Accounting with AI Tools - Business Session 5', 'date' => 'Jan 18, 2026 11:00 AM', 'spent' => '08M 21S', 'duration' => '1H 02M 10S', 'percent' => '13.4%', 'status' => 'present', 'feedback' => '', 'type' => 'Teams Session'],
            ],
            'Managerial Economics in the Age of AI' => [
                ['name' => 'Managerial Economics in the Age of AI - Teams Session 01', 'date' => 'Feb 14, 2026 09:30 AM', 'spent' => '17M 08S', 'duration' => '59M 50S', 'percent' => '28.6%', 'status' => 'present', 'feedback' => '', 'type' => 'Teams Session'],
                ['name' => 'Managerial Economics in the Age of AI - Teams Session 02', 'date' => 'Feb 21, 2026 09:30 AM', 'spent' => '00M 00S', 'duration' => '51M 12S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
            ],
            'Marketing Principles with Generative AI' => [
                ['name' => 'Marketing Principles with Generative AI - Teams Session 01', 'date' => 'Feb 11, 2026 04:00 PM', 'spent' => '09M 12S', 'duration' => '1H 04M 09S', 'percent' => '14.3%', 'status' => 'present', 'feedback' => '', 'type' => 'Teams Session'],
                ['name' => 'Marketing Principles with Generative AI - Teams Session 02', 'date' => 'Feb 18, 2026 04:00 PM', 'spent' => '00M 00S', 'duration' => '47M 52S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
            ],
            'Orientation | MBA AI - JAIN Online' => [
                ['name' => 'Orientation | MBA AI - JAIN Online - Welcome Session', 'date' => 'Jan 06, 2026 06:00 PM', 'spent' => '00M 00S', 'duration' => '38M 40S', 'percent' => '0%', 'status' => 'absent', 'feedback' => '', 'type' => 'Teams Session'],
            ],
        ];

        $total = array_sum(array_column($courses, 'total'));
        $present = array_sum(array_column($courses, 'present'));
        $absent = array_sum(array_column($courses, 'absent'));
        $overall = $total ? round(($present / $total) * 100, 1) : 0;
        $angle = $total ? round(($present / $total) * 360, 1) : 0;
        $detailsjson = htmlspecialchars(json_encode($details), ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($courses as $course) {
            $courseurl = new moodle_url('/my/courses.php');
            $rows .= '<tr>';
            $rows .= '<td><a href="' . $courseurl . '">' . format_string($course['name']) . '</a></td>';
            $rows .= '<td><span class="sa-pill sa-pill-total">' . $course['total'] . '</span></td>';
            $rows .= '<td><span class="sa-pill sa-pill-present">' . $course['present'] . '</span></td>';
            $rows .= '<td><span class="sa-pill sa-pill-absent">' . $course['absent'] . '</span></td>';
            $rows .= '<td><span class="sa-pill sa-pill-percent">' . rtrim(rtrim(number_format($course['percent'], 1), '0'), '.') . '%</span></td>';
            $rows .= '<td><button type="button" class="sa-detail-btn" data-course="' . s($course['name']) . '">View Details</button></td>';
            $rows .= '</tr>';
        }

        $html = '';
        $html .= '<div class="session-attendance-block" data-details="' . $detailsjson . '">';
        $html .= '<div class="sa-card"><div class="sa-body">';
        $html .= '<div class="sa-grid"><div class="sa-table-wrap"><table class="sa-table"><thead><tr>';
        $html .= '<th>Course</th><th>Total</th><th>Present</th><th>Absent</th><th>%</th><th>Detail</th>';
        $html .= '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        $html .= '<div class="sa-summary"><div class="sa-donut" style="background:conic-gradient(#2ea44f 0deg ' . $angle . 'deg,#e33143 ' . $angle . 'deg 360deg)">';
        $html .= '<div class="sa-donut-center"><strong>' . $total . '</strong><span>Total</span><em>' . $overall . '%</em></div></div>';
        $html .= '<div class="sa-legend"><span><i class="sa-dot sa-dot-present"></i>Attended (' . $present . ')</span><span><i class="sa-dot sa-dot-absent"></i>Absent (' . $absent . ')</span></div></div></div>';
        $html .= '<div class="sa-overall">Overall: <span>' . $present . '/' . $total . ' (' . $overall . '%)</span></div>';
        $html .= '</div></div>';
        $html .= '<div class="sa-modal" hidden><div class="sa-modal-backdrop"></div><div class="sa-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sa-modal-title">';
        $html .= '<div class="sa-modal-header"><h3 id="sa-modal-title">Activity Details</h3><button type="button" class="sa-modal-close" aria-label="Close">×</button></div>';
        $html .= '<div class="sa-modal-body"><div class="sa-modal-table-wrap"><table class="sa-modal-table"><thead><tr><th>Activity Name</th><th>Date</th><th>Time Spent</th><th>Total Duration</th><th>Percentage</th><th>Status</th><th>Feedback</th></tr></thead><tbody class="sa-modal-rows"></tbody></table></div></div>';
        $html .= '<div class="sa-modal-footer"><span>Showing Teams session attendance data with corrected calculations</span><button type="button" class="sa-close-btn">Close</button></div>';
        $html .= '</div></div></div>';

        $this->page->requires->js_init_code("(function() {
            var root = document.querySelector('.session-attendance-block');
            if (!root || root.dataset.initialized === '1') { return; }
            root.dataset.initialized = '1';
            var details = JSON.parse(root.getAttribute('data-details') || '{}');
            var modal = root.querySelector('.sa-modal');
            var title = root.querySelector('#sa-modal-title');
            var rows = root.querySelector('.sa-modal-rows');
            var activeCourse = '';
            var escapeHtml = function(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };
            var renderRows = function(course) {
                var items = details[course] || [];
                rows.innerHTML = items.map(function(item, index) {
                    var statusclass = item.status === 'present' ? 'sa-status-present' : 'sa-status-absent';
                    var statustext = item.status === 'present' ? 'Present' : 'Absent';
                    var feedback = item.feedback || '';
                    var buttontext = feedback ? 'Edit Feedback' : 'Give Feedback';
                    var feedbacktext = feedback ? '<div class=\"sa-feedback-text\">' + escapeHtml(feedback) + '</div>' : '';
                    return '<tr>' +
                        '<td><div class=\"sa-activity-name\">' + escapeHtml(item.name) + '<small>' + escapeHtml(item.type) + '</small></div></td>' +
                        '<td><span class=\"sa-chip sa-chip-date\">' + escapeHtml(item.date) + '</span></td>' +
                        '<td><span class=\"sa-chip sa-chip-time\">' + escapeHtml(item.spent) + '</span></td>' +
                        '<td><span class=\"sa-chip sa-chip-duration\">' + escapeHtml(item.duration) + '</span></td>' +
                        '<td><span class=\"sa-chip sa-chip-percent\">' + escapeHtml(item.percent) + '</span></td>' +
                        '<td><span class=\"sa-chip ' + statusclass + '\">' + statustext + '</span></td>' +
                        '<td><div class=\"sa-feedback-cell\"><button type=\"button\" class=\"sa-detail-btn sa-feedback-btn\" data-index=\"' + index + '\">' + buttontext + '</button>' + feedbacktext + '</div></td>' +
                    '</tr>';
                }).join('');
            };
            var close = function() { modal.setAttribute('hidden', 'hidden'); document.body.classList.remove('sa-modal-open'); };
            root.querySelectorAll('.sa-detail-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    var course = this.getAttribute('data-course');
                    activeCourse = course;
                    title.textContent = course + ' - Activity Details';
                    renderRows(course);
                    modal.removeAttribute('hidden');
                    document.body.classList.add('sa-modal-open');
                });
            });
            rows.addEventListener('click', function(e) {
                if (!e.target.classList || !e.target.classList.contains('sa-feedback-btn') || !activeCourse) { return; }
                var items = details[activeCourse] || [];
                var index = parseInt(e.target.getAttribute('data-index'), 10);
                if (isNaN(index) || !items[index]) { return; }
                var item = items[index];
                var feedback = window.prompt('Enter feedback for ' + item.name, item.feedback || '');
                if (feedback === null) { return; }
                item.feedback = feedback.trim();
                renderRows(activeCourse);
            });
            root.querySelectorAll('.sa-modal-close, .sa-close-btn, .sa-modal-backdrop').forEach(function(el) {
                el.addEventListener('click', close);
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.hasAttribute('hidden')) { close(); }
            });
        })();");

        $this->content = new stdClass();
        $this->content->text = $html;
        $this->content->footer = '';
        return $this->content;
    }
}