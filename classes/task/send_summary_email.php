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
 * A scheduled task to send a weekly admin summary of missing syllabi.
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_syllabus\task;

/**
 * This class handles sending the weekly admin summary email listing
 * eligible courses that do not have a Syllabus activity.
 */
class send_summary_email extends \core\task\scheduled_task {
    /**
     * Returns the name of this task.
     * @return string
     */
    public function get_name() {
        return get_string('summaryemail', 'mod_syllabus');
    }

    /**
     * Executes the task.
     */
    public function execute() {
        $val = get_config('syllabus', 'summaryenabled');
        if (!$val) {
            mtrace("Not sending summary email - summaryenabled is not set");
            return;
        }

        $emails = get_config('syllabus', 'summaryemails');
        if (empty(trim((string)$emails))) {
            mtrace("Not sending summary email - no email addresses configured");
            return;
        }

        $cats = get_config('syllabus', 'catstocheck');
        if (empty($cats)) {
            mtrace("No categories selected to process. Exiting.");
            return;
        }

        $cats = explode(',', $cats);
        $catdata = [];

        foreach ($cats as $catid) {
            $catid = trim($catid);
            if (empty($catid)) {
                continue;
            }
            $info = $this->get_category_data($catid);
            if ($info !== null) {
                $catdata[] = $info;
            }
        }

        if (empty($catdata)) {
            mtrace("No valid category data found. Exiting.");
            return;
        }

        $this->send_summary_emails($catdata, $emails);
    }

    /**
     * Build summary data for a single category: counts courses with/without
     * a syllabus and lists courses without a syllabus along with their teachers.
     *
     * The same filtering rules used by the reminder email task are applied
     * (exclude regex, student enrolment, active date range, visibility).
     *
     * @param int $catid The course category id.
     * @return array|null Summary data for the category, or null if category does not exist.
     */
    public function get_category_data($catid) {
        global $DB;

        if (!$catid || !$DB->record_exists('course_categories', ['id' => $catid])) {
            mtrace("Category ID of $catid does not exist...skipping and removing from config.");

            $categories = get_config('syllabus', 'catstocheck');
            if (!empty($categories)) {
                $allcats = array_filter(array_map('trim', explode(',', $categories)));
                $allcats = array_values(array_diff($allcats, [(string)$catid]));
                set_config('catstocheck', implode(',', $allcats), 'syllabus');
            }

            return null;
        }

        $category = $DB->get_record('course_categories', ['id' => $catid]);

        $regex    = get_config('syllabus', 'excluderegex');
        $tohidden = get_config('syllabus', 'emailstohidden');
        $now      = time();

        $courseids = array_keys($DB->get_records('course', ['category' => $catid], '', 'id'));

        $withsyllabus        = 0;
        $courseswithoutsyllabus = [];

        foreach ($courseids as $courseid) {
            $course = get_course($courseid);

            // Skip courses whose shortname matches the exclude regex.
            if ($regex && preg_match($regex, $course->shortname)) {
                continue;
            }

            // Skip courses with no enrolled students.
            $coursecon        = \context_course::instance($courseid);
            $enrolledstudents = count_enrolled_users($coursecon, 'mod/assign:submit');
            if ($enrolledstudents == 0) {
                continue;
            }

            // Skip courses outside the active date range.
            if ($course->startdate >= $now || ($course->enddate != 0 && $course->enddate <= $now)) {
                continue;
            }

            // Skip hidden courses when not configured to include them.
            if (!$course->visible && !$tohidden) {
                continue;
            }

            // This is an eligible course – check whether it has a syllabus.
            $syllabi = get_all_instances_in_course('syllabus', $course, null, true);

            if (count($syllabi) > 0) {
                $withsyllabus++;
            } else {
                // Collect teachers who can actually see this course.
                $teachers = get_users_by_capability(
                    $coursecon,
                    'mod/syllabus:addinstance',
                    'u.id, u.firstname, u.lastname'
                );

                $teachernames = [];
                foreach ($teachers as $teacher) {
                    if (has_capability('moodle/course:viewhiddencourses', $coursecon, $teacher->id)) {
                        $teachernames[] = fullname($teacher);
                    }
                }

                $courseswithoutsyllabus[] = [
                    'name'         => $course->fullname,
                    'url'          => (string) new \moodle_url('/course/view.php', ['id' => $course->id]),
                    'teachernames' => implode(', ', $teachernames),
                ];
            }
        }

        return [
            'catname'         => $category->name,
            'withsyllabus'    => $withsyllabus,
            'withoutsyllabus' => count($courseswithoutsyllabus),
            'courses'         => $courseswithoutsyllabus,
        ];
    }

    /**
     * Render the summary email template and send it to each configured address.
     *
     * @param array  $catdata  Array of category summary data produced by get_category_data().
     * @param string $emails   Comma-separated list of recipient email addresses.
     */
    public function send_summary_emails($catdata, $emails) {
        global $OUTPUT;

        $now     = time();
        $datestr = userdate($now, get_string('strftimedatefullshort', 'core_langconfig'));

        $data = [
            'datestr'    => $datestr,
            'categories' => $catdata,
        ];

        $msg     = $OUTPUT->render_from_template('mod_syllabus/email_summary', $data);
        $subject = get_string('summaryemailsubj', 'mod_syllabus') . ' - ' . $datestr;

        $emaillist = array_filter(array_map('trim', explode(',', $emails)));

        foreach ($emaillist as $email) {
            if (!validate_email($email)) {
                mtrace("Invalid email address: $email - skipping.");
                continue;
            }

            $recipient = $this->make_recipient($email);
            $admin     = get_admin();

            mtrace("Sending summary email to $email");
            $result = email_to_user($recipient, $admin, $subject, html_to_text($msg), $msg);
            if (!$result) {
                mtrace("Error sending summary email to $email");
            }
        }
    }

    /**
     * Build a minimal user object suitable for use as an email recipient.
     *
     * @param  string    $email The recipient email address.
     * @return \stdClass        A user-like object accepted by email_to_user().
     */
    public function make_recipient($email) {
        $recipient            = clone \core_user::get_noreply_user();
        $recipient->email     = $email;
        $recipient->firstname = '';
        $recipient->lastname  = '';
        return $recipient;
    }
}
