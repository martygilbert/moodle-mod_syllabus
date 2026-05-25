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
 * Unit tests for mod_syllabus send_summary_email task
 *
 * @package    mod_syllabus
 * @category   external
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_syllabus;
/**
 * Unit tests for mod_syllabus send_summary_email task
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary_email_test extends \advanced_testcase {

    /**
     * @var \phpunit_message_sink
     */
    protected $messagesink;

    /**
     * @var \phpunit_mailer_sink
     */
    protected $mailsink;

    /**
     * @var \mod_syllabus\task\send_summary_email
     */
    protected $summarytask;

    /**
     * Setup the tests.
     * @return void
     */
    public function setUp(): void {
        // Messaging is not compatible with transactions.
        $this->preventResetByRollback();

        // Catch all messages.
        $this->messagesink = $this->redirectMessages();
        $this->mailsink    = $this->redirectEmails();
    }

    /**
     * Tear down.
     */
    public function tearDown(): void {
        $this->messagesink->clear();
        $this->messagesink->close();
        unset($this->messagesink);

        $this->mailsink->clear();
        $this->mailsink->close();
        unset($this->mailsink);
    }

    /**
     * Make sure the summary task does nothing when summaryenabled is false.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_not_sent_when_disabled() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();
        $admin = get_admin();
        set_config('catstocheck', $course->category, 'syllabus');
        set_config('summaryenabled', '0', 'syllabus');
        set_config('summaryemails', $admin->username, 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();
        $this->assertEquals(0, count($messages));
    }

    /**
     * Make sure the summary task does nothing when no email addresses are configured.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_not_sent_without_recipients() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();
        set_config('catstocheck', $course->category, 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', '', 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();
        $this->assertEquals(0, count($messages));
    }

    /**
     * Make sure the summary task sends one email per configured username.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_sent_to_each_recipient() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();
        $admin  = get_admin();
        $second = $this->getDataGenerator()->create_user();
        set_config('catstocheck', $course->category, 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', $admin->username . ', ' . $second->username, 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // One email per recipient.
        $this->assertEquals(2, count($messages));
    }

    /**
     * Make sure the summary email subject contains the expected string.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_email_subject() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();
        $admin = get_admin();
        set_config('catstocheck', $course->category, 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', $admin->username, 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        $this->assertEquals(1, count($messages));
        $this->assertMatchesRegularExpression(
            '/' . get_string('summaryemailsubj', 'mod_syllabus') . '/',
            $messages[0]->subject
        );
    }

    /**
     * Make sure the summary email body contains the instructor name for a course without a syllabus.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_email_includes_course_without_syllabus() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();
        $admin = get_admin();
        set_config('catstocheck', $course->category, 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', $admin->username, 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        $this->assertEquals(1, count($messages));
        // The teacher's name should appear in the top-10 section of the email body.
        // The raw MIME body may be quoted-printable encoded, so decode before matching.
        $this->assertMatchesRegularExpression(
            '/' . preg_quote(fullname($teacher), '/') . '/',
            quoted_printable_decode($messages[0]->body)
        );
    }

    /**
     * Make sure a course WITH a syllabus is NOT listed in the without-syllabus section,
     * and that it is reflected in the with-syllabus count.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_course_with_syllabus_not_in_list() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();
        $admin = get_admin();
        set_config('catstocheck', $course->category, 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', $admin->username, 'syllabus');

        // Add a Syllabus activity to the course.
        $this->setUser($teacher);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_syllabus');
        $generator->create_instance(['course' => $course->id]);

        // get_category_data should report 1 with syllabus, 0 without.
        $task = new \mod_syllabus\task\send_summary_email();
        $info = $task->get_category_data($course->category);

        $this->assertEquals(1, $info['eligible']);
        $this->assertEquals(1, $info['withsyllabus']);
        $this->assertEquals(0, $info['withoutsyllabus']);
        $this->assertEmpty($info['teachercounts']);
    }

    /**
     * Make sure category data counts are correct when a category has a mix
     * of courses with and without syllabi.
     * @covers \mod_syllabus\task\send_summary_email::get_category_data
     */
    public function test_summary_category_data_counts() {
        global $DB;
        $this->resetAfterTest(true);

        // Create two courses in the same category.
        list($course1, $teacher1, $student1) = $this->create_valid_course_with_teacher_student();
        list($course2, $teacher2, $student2) = $this->create_valid_course_with_teacher_student();

        // Move course2 into the same category as course1.
        $course2->category = $course1->category;
        $DB->update_record('course', $course2);

        // Add a syllabus to course1 only.
        $this->setUser($teacher1);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_syllabus');
        $generator->create_instance(['course' => $course1->id]);

        $task = new \mod_syllabus\task\send_summary_email();
        $info = $task->get_category_data($course1->category);

        $this->assertEquals(2, $info['eligible']);
        $this->assertEquals(1, $info['withsyllabus']);
        $this->assertEquals(1, $info['withoutsyllabus']);
        // Teacher 2's name should appear in the teacher counts for this category.
        $this->assertNotEmpty($info['teachercounts']);
    }

    /**
     * Make sure the summary task does nothing when catstocheck is empty.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_no_categories() {
        $this->resetAfterTest(true);

        set_config('catstocheck', '', 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', get_admin()->username, 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();
        $this->assertEquals(0, count($messages));
    }

    /**
     * Make sure courses with no enrolled students are not included in the summary.
     * When all courses in a category are ineligible the method should return null.
     * @covers \mod_syllabus\task\send_summary_email::get_category_data
     */
    public function test_summary_excludes_courses_without_students() {
        $this->resetAfterTest(true);

        list($course, $teacher) = $this->create_valid_course_with_teacher();

        $task = new \mod_syllabus\task\send_summary_email();
        $info = $task->get_category_data($course->category);

        // No students – no eligible courses – category should be skipped entirely.
        $this->assertNull($info);
    }

    /**
     * Make sure hidden courses are excluded from the summary when emailstohidden is false.
     * When all courses are hidden and emailstohidden is off, the category has no eligible
     * courses and get_category_data should return null.
     * @covers \mod_syllabus\task\send_summary_email::get_category_data
     */
    public function test_summary_excludes_hidden_courses_when_not_configured() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student(0);
        set_config('emailstohidden', '0', 'syllabus');

        $task = new \mod_syllabus\task\send_summary_email();
        $info = $task->get_category_data($course->category);

        // Hidden course excluded and no eligible courses remain – category is skipped.
        $this->assertNull($info);
    }

    /**
     * Make sure hidden courses ARE included when emailstohidden is true.
     * @covers \mod_syllabus\task\send_summary_email::get_category_data
     */
    public function test_summary_includes_hidden_courses_when_configured() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student(0);
        set_config('emailstohidden', '1', 'syllabus');

        $task = new \mod_syllabus\task\send_summary_email();
        $info = $task->get_category_data($course->category);

        $this->assertEquals(1, $info['eligible']);
        $this->assertEquals(0, $info['withsyllabus']);
        $this->assertEquals(1, $info['withoutsyllabus']);
    }

    /**
     * Make sure courses matching the excluderegex are skipped.
     * When all courses are excluded the category has no eligible courses and
     * get_category_data should return null.
     * @covers \mod_syllabus\task\send_summary_email::get_category_data
     */
    public function test_summary_excluderegex() {
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();

        // Set a regex that matches this course shortname.
        set_config('excluderegex', '/./', 'syllabus');

        $task = new \mod_syllabus\task\send_summary_email();
        $info = $task->get_category_data($course->category);

        // All courses excluded – category is skipped.
        $this->assertNull($info);
    }

    /**
     * Make sure get_category_data returns null for a non-existent category.
     * @covers \mod_syllabus\task\send_summary_email::get_category_data
     */
    public function test_summary_invalid_category() {
        $this->resetAfterTest(true);

        $task = new \mod_syllabus\task\send_summary_email();
        ob_start();
        $info = $task->get_category_data(99999);
        ob_end_clean();

        $this->assertNull($info);
    }

    /**
     * Make sure the top-10 teachers list in the email is ranked by course count.
     * @covers \mod_syllabus\task\send_summary_email
     */
    public function test_summary_top10_teachers_ordered() {
        global $DB;
        $this->resetAfterTest(true);

        // Teacher A has 2 courses without a syllabus; teacher B has 1.
        list($course1, $teacherA, $student1) = $this->create_valid_course_with_teacher_student();
        list($course2, $teacherAref, $student2) = $this->create_valid_course_with_teacher_student(
            1, $teacherA->id
        );
        list($course3, $teacherB, $student3) = $this->create_valid_course_with_teacher_student();

        // Move all courses into the same category.
        $course2->category = $course1->category;
        $course3->category = $course1->category;
        $DB->update_record('course', $course2);
        $DB->update_record('course', $course3);

        set_config('catstocheck', $course1->category, 'syllabus');
        set_config('summaryenabled', '1', 'syllabus');
        set_config('summaryemails', get_admin()->username, 'syllabus');

        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        $this->assertEquals(1, count($messages));
        // The raw MIME body may be quoted-printable encoded, so decode before searching.
        $body = quoted_printable_decode($messages[0]->body);

        // Teacher A's name must appear before teacher B's name in the email.
        $posA = strpos($body, fullname($teacherA));
        $posB = strpos($body, fullname($teacherB));
        $this->assertNotFalse($posA, 'Teacher A not found in email body');
        $this->assertNotFalse($posB, 'Teacher B not found in email body');
        $this->assertLessThan($posB, $posA, 'Teacher A should appear before Teacher B');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a course with valid course start/end times.
     * @param int $visible Whether or not the course should be visible (0, 1 - default).
     * @return \stdClass course
     */
    private function create_valid_course($visible = 1) {
        $record = [
            'startdate' => time() - 86400,
            'enddate'   => time() + 86400,
            'visible'   => $visible,
        ];
        return $this->getDataGenerator()->create_course($record);
    }

    /**
     * Create a course with a teacher enrolled.
     * @param int $visible Whether or not the course should be visible (0, 1 - default).
     * @param int|null $teacherid id of an existing teacher, or null to create a new one.
     * @return array [$course, $teacher]
     */
    private function create_valid_course_with_teacher($visible = 1, $teacherid = null) {
        global $DB;

        $course = $this->create_valid_course($visible);

        if (!$teacherid) {
            $teacher   = $this->getDataGenerator()->create_user();
            $teacherid = $teacher->id;
        } else {
            $teacher = $DB->get_record('user', ['id' => $teacherid]);
        }

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacherid, $course->id, $teacherroleid);

        return [$course, $teacher];
    }

    /**
     * Create a course with a teacher and a student enrolled.
     * @param int $visible Whether or not the course should be visible (0, 1 - default).
     * @param int|null $teacherid id of an existing teacher, or null to create a new one.
     * @return array [$course, $teacher, $student]
     */
    private function create_valid_course_with_teacher_student($visible = 1, $teacherid = null) {
        global $DB;

        list($course, $teacher) = $this->create_valid_course_with_teacher($visible, $teacherid);

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $student       = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentroleid);

        return [$course, $teacher, $student];
    }

    /**
     * Execute the \mod_syllabus\task\send_summary_email task.
     */
    private function execute_task() {
        if (!$this->summarytask) {
            $this->summarytask = new \mod_syllabus\task\send_summary_email();
        }
        ob_start();
        $this->summarytask->execute();
        ob_end_clean();
    }
}
