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
 * Unit tests for mod_syllabus tasks
 *
 * @package    mod_syllabus
 * @category   external
 * @copyright  2020 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.5
 */
namespace mod_syllabus;

/**
 * Unit tests for mod_syllabus tasks
 *
 * @package    mod_syllabus
 * @category   external
 * @copyright  2019 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.10
 */
class reminder_email_test extends \advanced_testcase {

    /**
     * @var \phpunit_message_sink
     */
    protected $messagesink;

    /**
     * @var \phpunit_mailer_sink
     */
    protected $mailsink;

    /**
     * @var \mod_syllabus\task\send_reminder_email
     */
    protected $remindermailtask;

    /**
     * Setup the tests.
     * @return void
     */
    public function setUp(): void {
        global $CFG;

        // Messaging is not compatible with transactions...
        $this->preventResetByRollback();

        // Catch all messages.
        $this->messagesink = $this->redirectMessages();
        $this->mailsink = $this->redirectEmails();
    }

    public function tearDown(): void {

        $this->messagesink->clear();
        $this->messagesink->close();
        unset($this->messagesink);

        $this->mailsink->clear();
        $this->mailsink->close();
        unset($this->mailsink);

    }

    /**
     * This test will make sure that the reminder task does nothing if remindersenabled is
     * false.
     * @covers \mod_syllabus\task\send_reminder_email
     */
    public function test_remindersendabled() {
        global $DB;
        $this->resetAfterTest(true);

        // Create a valid course with a teacher, student.
        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();

        // Make sure the cron job will process this category.
        set_config('catstocheck', $course->category, 'syllabus');

        // Make sure remindersenabled is off.
        set_config('remindersenabled', '0', 'syllabus');

        // Execute the scheduled task to send a reminder email. Should stop due to remindersenabled = false.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be no messages.
        $this->assertEquals(0, count($messages));

        // Now test with remindersenabled on.
        set_config('remindersenabled', '1', 'syllabus');

        // Clear previous (non-exisiting) message(s).
        $this->mailsink->clear();
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be one message.
        $this->assertEquals(1, count($messages));
    }

    /**
     * This test will make sure that teachers receive don't receive and email for a course
     * that has a Syllabus activity.
     * @covers \mod_syllabus\task\send_reminder_email
     */
    public function test_reminder_syllabus_exists() {
        global $DB;
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student();

        // Make sure the cron job will process this category.
        set_config('catstocheck', $course->category, 'syllabus');

        // Execute the scheduled task to send a reminder email.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be one message.
        $this->assertEquals(1, count($messages));

        // Check for the links to each course
        // Message should have the correct subject.
        foreach ($messages as $message) {
            $this->assertMatchesRegularExpression('/'. get_string('emailsubj', 'mod_syllabus').'/', $message->subject);
            $this->assertMatchesRegularExpression('/'.$course->fullname.'/', $message->body);
        }

        // Now add a Syllabus activity and retry.
        $options = ['course' => $course->id];
        $this->setUser($teacher);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_syllabus');
        $syllabus = $generator->create_instance($options);

        // Clear the previous messages.
        $this->mailsink->clear();
        $this->execute_task();

        $messages = $this->mailsink->get_messages();

        // Should be zero messages b/c syllabus exists.
        $this->assertEquals(0, count($messages));
    }

    /**
     * This test will make sure that teachers receive only one
     * email reminder, even if they have multiple courses across
     * multiple categories.
     * @covers \mod_syllabus\task\send_reminder_email
     */
    public function test_reminder_email_single_email() {
        global $DB;
        $this->resetAfterTest(true);

        // Create a course, without a syllabus with valid start/end times.
        $record = [
            'startdate' => time() - 86400,
            'enddate' => time() + 86400,
        ];

        list($course1, $teacher1, $student1) = $this->create_valid_course_with_teacher_student();
        list($course2, $teacher1, $student2) = $this->create_valid_course_with_teacher_student(1, $teacher1->id);

        $newcat = $this->getDataGenerator()->create_category();

        $course2->category = $newcat->id;
        $DB->update_record('course', $course2);

        // Make sure the cron job will process these categories.
        $catstocheck = $course1->category.','.$course2->category;
        set_config('catstocheck', $catstocheck, 'syllabus');

        // Execute the scheduled task to send a reminder email.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be one message, even though it's two courses.
        $this->assertEquals(1, count($messages));

        // Check for the links to each course
        // Message should have the correct subject.
        foreach ($messages as $message) {
            $this->assertMatchesRegularExpression('/'. get_string('emailsubj', 'mod_syllabus').'/', $message->subject);
            $this->assertMatchesRegularExpression('/'.$course1->fullname.'|'.$course2->fullname.'/', $message->body);
        }
    }

    /**
     * This test will make sure that reminder emails are not sent if
     * if teacher can't view course.
     * @covers \mod_syllabus\task\send_reminder_email
     */
    public function test_reminder_email_invisible_to_teacher() {
        global $DB;
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student(0);

        // Make sure the cron job will process this category.
        set_config('catstocheck', $course->category, 'syllabus');

        // Process hidden courses.
        set_config('emailstohidden', '1', 'syllabus');

        // Execute the scheduled task to send a reminder email.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be one messages because they have permission to viewhiddencourses.
        $this->assertEquals(1, count($messages));

        // Message should have the correct subject.
        foreach ($messages as $message) {
            $this->assertMatchesRegularExpression('/'. get_string('emailsubj', 'mod_syllabus').'/', $message->subject);
            $this->assertMatchesRegularExpression('/'.$course->fullname.'/', $message->body);
        }

        $this->mailsink->clear();

        // Now, remove the permission to see hidden courses.
        $coursecon = \context_course::instance($course->id);
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:viewhiddencourses', CAP_PREVENT, $teacherroleid, $coursecon);

        // Run the reminder email task again.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be zero because of permission change.
        $this->assertEquals(0, count($messages));
    }

    /**
     * This test will make sure that:
     * 1. Reminder emails are NOT sent if course hidden and not emailstohidden
     * 2. Reminder emails ARE sent if course hidden and emailstohidden
     * @covers \mod_syllabus\task\send_reminder_email
     */
    public function test_reminder_email_emailstohidden() {
        global $DB;
        $this->resetAfterTest(true);

        list($course, $teacher, $student) = $this->create_valid_course_with_teacher_student(0);

        // Make sure the cron job will process this category.
        set_config('catstocheck', $course->category, 'syllabus');

        // Don't process hidden courses.
        set_config('emailstohidden', '0', 'syllabus');

        // Test if course is hidden and emailstohidden is false.

        // Execute the scheduled task to send a reminder email.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be zero messages because emailstohidden is false.
        $this->assertEquals(0, count($messages));
        $this->mailsink->clear();

        // Test if course is hidden and emailstohidden is true.
        set_config('emailstohidden', '1', 'syllabus');

        // Run the reminder email task again.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be one because emailstohidden is true.
        $this->assertEquals(1, count($messages));

        // Message should have the subject.
        foreach ($messages as $message) {
            $this->assertMatchesRegularExpression('/'. get_string('emailsubj', 'mod_syllabus').'/', $message->subject);
            $this->assertMatchesRegularExpression('/'.$course->fullname.'/', $message->body);
        }
    }

    /**
     * This test will make sure that:
     * 1. Reminder emails are NOT sent if no students are enrolled
     * 2. Reminder emails ARE sent if >= 1 student is enrolled
     * @covers \mod_syllabus\task\send_reminder_email
     */
    public function test_reminder_email_enrollment_based() {
        global $DB;
        $this->resetAfterTest(true);

        // Create a course, without a syllabus.
        list($course, $teacher) = $this->create_valid_course_with_teacher();

        // Make sure the cron job will process this category.
        set_config('catstocheck', $course->category, 'syllabus');

        // Test if course has 0 students - no email should be sent.

        // Execute the scheduled task to send a reminder email.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be zero messages because of no students.
        $this->assertEquals(0, count($messages));
        $this->mailsink->clear();

        // Test if course has student(s) enrolled - should send an email.

        // Create a user enrolled in the course as a student.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentroleid);

        // Run the reminder email task again.
        $this->execute_task();
        $messages = $this->mailsink->get_messages();

        // Should be one and only one email sent.
        $this->assertEquals(1, count($messages));

        // Message should have the subject.
        foreach ($messages as $message) {
            $this->assertMatchesRegularExpression('/'. get_string('emailsubj', 'mod_syllabus').'/', $message->subject);
            $this->assertMatchesRegularExpression('/'.$course->fullname.'/', $message->body);
        }
    }

    /**
     * Create a course with valid course start/end times.
     * @param int $visible Whether or not the course should be visible (0, 1 - default)
     * @return stdClass course
     */
    private function create_valid_course($visible = 1) {
        global $DB;

        // Create a course, without a syllabus with valid start/end times.
        $record = [
            'startdate' => time() - 86400,
            'enddate' => time() + 86400,
            'visible' => $visible,
        ];

        $course = $this->getDataGenerator()->create_course($record);

        return $course;
    }

    /**
     * Create a course with a teacher enrolled. Course start/end times
     * are valid for receiving a syllabus email.
     * @param int $visible Whether or not the course should be visible (0, 1 - default)
     * @param int $teacherid id of the teacher, or null for adding new teacher.
     * @return array [$course, $teacher]
     */
    private function create_valid_course_with_teacher($visible = 1, $teacherid = null) {
        global $DB;

        $course = $this->create_valid_course($visible);

        // Create a user enrolled in the course as a teacher.
        if (!$teacherid) {
            $teacher = $this->getDataGenerator()->create_user();
            $teacherid = $teacher->id;
        } else {
            $teacher = $DB->get_record('user', ['id' => $teacherid]);
        }

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacherid, $course->id, $teacherroleid);

        return [$course, $teacher];
    }

    /**
     * Create a course with a teacher, student enrolled. Course start/end times
     * are valid for receiving a syllabus emaile
     * @param int $visible Whether or not the course should be visible (0, 1 - default)
     * @param int $teacherid id of the teacher, or null for adding new teacher.
     * @return array [$course, $teacher, $student]
     */
    private function create_valid_course_with_teacher_student($visible = 1, $teacherid = null) {
        global $DB;

        list($course, $teacher) = $this->create_valid_course_with_teacher($visible, $teacherid);

        // Create a user enrolled in both courses as a student - needed or no email sent.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentroleid);

        return [$course, $teacher, $student];
    }

    /**
     * Execute the \mod_syllabus\task\send_reminder_email
     */
    private function execute_task() {
        // Execute the scheduled task to send a reminder email.
        if (!$this->remindermailtask) {
            $this->remindermailtask = new \mod_syllabus\task\send_reminder_email();
        }
        ob_start();
        $this->remindermailtask->execute();
        ob_end_clean();
    }
}
