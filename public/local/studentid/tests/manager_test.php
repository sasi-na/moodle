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

namespace local_studentid;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

/**
 * Unit tests for the student ID card workflow manager.
 *
 * @package    local_studentid
 * @covers     \local_studentid\manager
 */
final class manager_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Keep the workflow off the mail path: send_notification() early-returns
        // when this is disabled, so tests never touch email_to_user().
        set_config('emailnotifications', 0, 'local_studentid');
    }

    /** Build a valid form-data object for save_request(). */
    private function form(string $blood = 'O+', string $city = 'Bengaluru', string $state = 'Karnataka'): \stdClass {
        $f = new \stdClass();
        $f->blood_group = $blood;
        $f->city = $city;
        $f->state = $state;
        $f->requestid = 0;
        return $f;
    }

    /** Mark the four upload path columns as present so submit_request() passes its document gate. */
    private function attach_documents(int $requestid): void {
        global $DB;
        $DB->set_field('local_studentid_requests', 'photo_path', 'photo.jpg', ['id' => $requestid]);
        $DB->set_field('local_studentid_requests', 'aadhaar_front_path', 'front.jpg', ['id' => $requestid]);
        $DB->set_field('local_studentid_requests', 'aadhaar_back_path', 'back.jpg', ['id' => $requestid]);
        $DB->set_field('local_studentid_requests', 'abc_id_path', 'abc.pdf', ['id' => $requestid]);
    }

    /** Insert a request directly in a given status (bypassing the workflow). */
    private function make_request(int $userid, string $status): int {
        global $DB;
        $now = time();
        $rec = (object) [
            'userid' => $userid,
            'usn' => 'USN' . $userid,
            'status' => $status,
            'blood_group' => 'O+',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int) $DB->insert_record('local_studentid_requests', $rec);
    }

    /** True when python3 + PyMuPDF are available so the real PDF generator can run. */
    private function generator_available(): bool {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $out = shell_exec('python3 -c "import fitz" 2>&1');
        return $out !== null && trim((string) $out) === '';
    }

    // ──────────────────────────────────────────────────────────────────────
    // save_request()
    // ──────────────────────────────────────────────────────────────────────

    public function test_save_request_creates_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $id = manager::save_request($user->id, $this->form('A+', 'Mysuru', 'Karnataka'));

        $rec = $DB->get_record('local_studentid_requests', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(manager::STATUS_DRAFT, $rec->status);
        $this->assertSame('A+', $rec->blood_group);
        $this->assertSame('Mysuru', $rec->city);
        $this->assertSame('Karnataka', $rec->state);
        $this->assertEquals($user->id, $rec->userid);
        $this->assertTrue($DB->record_exists('local_studentid_logs',
            ['userid' => $user->id, 'action' => manager::LOG_CREATE]));
    }

    public function test_save_request_updates_existing_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $first = manager::save_request($user->id, $this->form('A+', 'Mysuru'));
        $second = manager::save_request($user->id, $this->form('B-', 'Hubballi'));

        $this->assertSame($first, $second, 'A second save on an open draft must update, not insert.');
        $this->assertSame(1, $DB->count_records('local_studentid_requests', ['userid' => $user->id]));
        $rec = $DB->get_record('local_studentid_requests', ['id' => $first]);
        $this->assertSame('B-', $rec->blood_group);
        $this->assertSame('Hubballi', $rec->city);
    }

    public function test_rejected_request_is_replaced_and_files_removed(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $old = $this->make_request($user->id, manager::STATUS_REJECTED);
        $this->store_file('photo', $old, 'photo.jpg');
        $this->store_file('abc_id', $old, 'abc.pdf');

        $new = manager::save_request($user->id, $this->form());

        $this->assertNotSame($old, $new);
        $this->assertSame(manager::STATUS_REPLACED,
            $DB->get_field('local_studentid_requests', 'status', ['id' => $old]));
        $this->assertSame(manager::STATUS_DRAFT,
            $DB->get_field('local_studentid_requests', 'status', ['id' => $new]));

        // Superseded request's uploads must be gone.
        $fs = get_file_storage();
        $ctx = \context_system::instance();
        $this->assertTrue($fs->is_area_empty($ctx->id, 'local_studentid', 'photo', $old));
        $this->assertTrue($fs->is_area_empty($ctx->id, 'local_studentid', 'abc_id', $old));
        $this->assertTrue($DB->record_exists('local_studentid_logs',
            ['userid' => $user->id, 'action' => manager::LOG_REPLACE]));
    }

    // ──────────────────────────────────────────────────────────────────────
    // submit_request()
    // ──────────────────────────────────────────────────────────────────────

    public function test_submit_requires_each_document(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = manager::save_request($user->id, $this->form());

        $this->assert_submit_throws($id, $user->id, 'errornophoto');

        global $DB;
        $DB->set_field('local_studentid_requests', 'photo_path', 'photo.jpg', ['id' => $id]);
        $this->assert_submit_throws($id, $user->id, 'errornodocument');

        $DB->set_field('local_studentid_requests', 'aadhaar_front_path', 'f.jpg', ['id' => $id]);
        $DB->set_field('local_studentid_requests', 'aadhaar_back_path', 'b.jpg', ['id' => $id]);
        $this->assert_submit_throws($id, $user->id, 'errornoabcid');
    }

    public function test_submit_success_sets_status_and_timestamp(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = manager::save_request($user->id, $this->form());
        $this->attach_documents($id);

        manager::submit_request($id, $user->id);

        $rec = $DB->get_record('local_studentid_requests', ['id' => $id]);
        $this->assertSame(manager::STATUS_SUBMITTED, $rec->status);
        $this->assertNotEmpty($rec->timesubmitted);
        $this->assertTrue($DB->record_exists('local_studentid_logs',
            ['userid' => $user->id, 'action' => manager::LOG_SUBMIT]));
    }

    public function test_submit_rejects_non_draft(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_SUBMITTED);
        $this->attach_documents($id);
        $this->assert_submit_throws($id, $user->id, 'errorinvalidstatus');
    }

    private function assert_submit_throws(int $id, int $userid, string $errorcode): void {
        try {
            manager::submit_request($id, $userid);
            $this->fail("Expected moodle_exception ($errorcode) was not thrown.");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // approve / reject / under_review
    // ──────────────────────────────────────────────────────────────────────

    public function test_approve_request(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $verifier = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_SUBMITTED);

        manager::approve_request($id, 'Looks good', $verifier->id);

        $rec = $DB->get_record('local_studentid_requests', ['id' => $id]);
        $this->assertSame(manager::STATUS_APPROVED, $rec->status);
        $this->assertSame('Looks good', $rec->review_comment);
        $this->assertEquals($verifier->id, $rec->reviewed_by);
    }

    public function test_approve_rejects_non_reviewable_status(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_DRAFT);
        $this->expectException(\moodle_exception::class);
        manager::approve_request($id, 'x', $user->id);
    }

    public function test_reject_requires_comment(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_SUBMITTED);
        try {
            manager::reject_request($id, '   ', $user->id);
            $this->fail('Empty comment should be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorcommentrequired', $e->errorcode);
        }
    }

    public function test_reject_request(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $verifier = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_SUBMITTED);

        manager::reject_request($id, 'Photo blurry', $verifier->id);

        $rec = $DB->get_record('local_studentid_requests', ['id' => $id]);
        $this->assertSame(manager::STATUS_REJECTED, $rec->status);
        $this->assertSame('Photo blurry', $rec->review_comment);
    }

    public function test_set_under_review_only_from_submitted(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $verifier = $this->getDataGenerator()->create_user();

        $id = $this->make_request($user->id, manager::STATUS_SUBMITTED);
        manager::set_under_review($id, $verifier->id);
        $this->assertSame(manager::STATUS_UNDER_REVIEW,
            $DB->get_field('local_studentid_requests', 'status', ['id' => $id]));

        $draft = $this->make_request($user->id, manager::STATUS_DRAFT);
        $this->expectException(\moodle_exception::class);
        manager::set_under_review($draft, $verifier->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // get_active_request()
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_active_request_excludes_replaced(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->make_request($user->id, manager::STATUS_REPLACED);
        $active = $this->make_request($user->id, manager::STATUS_SUBMITTED);

        $found = manager::get_active_request($user->id);
        $this->assertNotNull($found);
        $this->assertEquals($active, $found->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // get_dashboard_stats() — today counts come from the audit log
    // ──────────────────────────────────────────────────────────────────────

    public function test_dashboard_today_counts_use_log_not_timemodified(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $verifier = $this->getDataGenerator()->create_user();

        // One submitted (pending), one approved, one rejected.
        $this->make_request($user->id, manager::STATUS_SUBMITTED);
        $approved = $this->make_request($user->id, manager::STATUS_SUBMITTED);
        manager::approve_request($approved, 'ok', $verifier->id);
        $rejected = $this->make_request($user->id, manager::STATUS_SUBMITTED);
        manager::reject_request($rejected, 'no', $verifier->id);

        $stats = manager::get_dashboard_stats();
        $this->assertSame(1, $stats['total_pending']);
        $this->assertSame(1, $stats['approved_today']);
        $this->assertSame(1, $stats['rejected_today']);
        $this->assertSame(1, $stats['total_approved']);
        $this->assertSame(1, $stats['total_rejected']);

        // Regression guard: bumping timemodified must NOT change today's counts,
        // because they are derived from the log, not the request row.
        $DB->set_field('local_studentid_requests', 'timemodified', time() + 100, ['id' => $approved]);
        $stats2 = manager::get_dashboard_stats();
        $this->assertSame(1, $stats2['approved_today']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // generate_id_card()
    // ──────────────────────────────────────────────────────────────────────

    public function test_generate_rejects_non_approved(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_SUBMITTED);
        $this->expectException(\moodle_exception::class);
        manager::generate_id_card($id, $user->id);
    }

    public function test_generate_is_idempotent_when_card_exists(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_APPROVED);

        // Pre-seed a card so generate_id_card() short-circuits without invoking
        // the PDF generator at all.
        $existing = (int) $DB->insert_record('local_studentid_cards', (object) [
            'requestid' => $id,
            'userid' => $user->id,
            'file_path' => 'SID-2026-000001.pdf',
            'idcard_number' => 'SID-2026-000001',
            'generated_by' => $user->id,
            'timegenerated' => time(),
        ]);

        $returned = manager::generate_id_card($id, $user->id);
        $this->assertSame($existing, $returned);
        $this->assertSame(1, $DB->count_records('local_studentid_cards', ['requestid' => $id]));
    }

    public function test_generated_card_numbers_are_unique_and_well_formed(): void {
        global $DB;
        $this->resetAfterTest();

        if (!$this->generator_available()) {
            $this->markTestSkipped('python3 + PyMuPDF not available; skipping real PDF generation.');
        }

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $r1 = $this->make_request($u1->id, manager::STATUS_APPROVED);
        $r2 = $this->make_request($u2->id, manager::STATUS_APPROVED);

        $c1 = manager::generate_id_card($r1, $u1->id);
        $c2 = manager::generate_id_card($r2, $u2->id);

        $card1 = $DB->get_record('local_studentid_cards', ['id' => $c1]);
        $card2 = $DB->get_record('local_studentid_cards', ['id' => $c2]);

        // Distinct, non-empty, well-formed numbers.
        $this->assertNotEmpty($card1->idcard_number);
        $this->assertNotSame($card1->idcard_number, $card2->idcard_number);
        $this->assertMatchesRegularExpression('/^SID-\d{4}-\d{6}$/', $card1->idcard_number);

        // The number is seeded from the card row id (collision-free by construction).
        $this->assertSame('SID-' . date('Y') . '-' . str_pad((string) $c1, 6, '0', STR_PAD_LEFT),
            $card1->idcard_number);
        $this->assertSame($card1->idcard_number . '.pdf', $card1->file_path);

        // The PDF file was actually stored.
        $fs = get_file_storage();
        $ctx = \context_system::instance();
        $this->assertFalse($fs->is_area_empty($ctx->id, 'local_studentid', 'idcard', $c1));
    }

    // ──────────────────────────────────────────────────────────────────────
    // delete_request_files()
    // ──────────────────────────────────────────────────────────────────────

    public function test_delete_request_files_clears_all_areas(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->make_request($user->id, manager::STATUS_DRAFT);

        foreach (['photo', 'aadhaar_front', 'aadhaar_back', 'abc_id'] as $area) {
            $this->store_file($area, $id, $area . '.dat');
        }

        manager::delete_request_files($id);

        $fs = get_file_storage();
        $ctx = \context_system::instance();
        foreach (['photo', 'aadhaar_front', 'aadhaar_back', 'abc_id'] as $area) {
            $this->assertTrue($fs->is_area_empty($ctx->id, 'local_studentid', $area, $id),
                "File area '$area' should be empty after delete_request_files().");
        }
    }

    /** Store a tiny file in a plugin file area against the system context. */
    private function store_file(string $filearea, int $itemid, string $filename): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_studentid',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'test-content');
    }
}
