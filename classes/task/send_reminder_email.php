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
 * A scheduled task for forum cron.
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_syllabus\task;

/**
 * This class will handle sending out the reminder emails
 */
class send_reminder_email extends \core\task\scheduled_task {

    /**
     * Returns the name of this task
     * @return string
     */
    public function get_name() {
        return get_string('reminderemail', 'mod_syllabus');
    }

    /**
     * Executes the task
     */
    public function execute() {
        $val = get_config('syllabus', 'remindersenabled');
        if (!$val) {
            mtrace("Not sending reminder emails - remindersenabled is not set");
            return;
        }

        $cats = get_config('syllabus', 'catstocheck');
        $courses = array();

        if (!empty($cats)) {
            $cats = explode(',', $cats);
            foreach ($cats as $catid) {
                $newcourses = $this->get_valid_courses($catid);
                if ($newcourses) {
                    $courses = array_merge($newcourses, $courses);
                }
            }
            $this->process_courses($courses);
        } else {
            mtrace("No categories selected to process. Exiting.");
        }
    }

    /**
     * All of the categories to check are listed in the config_plugins table
     * `catstocheck`. If a category no longer exists, this function will
     * remove the category id from `catstocheck` so it isn't checked again.
     * @param int $toremove the category id to remove from `catstocheck`
     */
    public function update_config($toremove) {
        $categories = get_config('syllabus', 'catstocheck');
        $allcats = explode(',', $categories);

        $idx = array_search($toremove, $allcats);
        if ($idx) {
            unset($allcats[$idx]);
        }

        set_config('catstocheck', implode(',', $allcats), 'syllabus');
    }

    /**
     * Get all of the courses in a category that need a reminder
     * @param int $catid the id of the category to analyze
     * @return array|null
     */
    public function get_valid_courses($catid) {
        global $DB;
        mtrace("Finding courses in category id $catid to be processed.");

        if (!$catid || !$DB->record_exists('course_categories', ['id' => $catid])) {
            mtrace("Category ID of $catid does not exist...skipping and removing from config.");
            $this->update_config($catid);
            return null;
        }

        $coursestoprocess = $DB->get_records('course', ['category' => $catid], '', 'id');
        $coursestoprocess = array_keys($coursestoprocess);

        return $coursestoprocess;
    }

    /**
     * Process the courses, compiling a list of each instructor's
     * courses that lack a Syllabus, then email them.
     * @param array $courses list of courses without a Syllabus
     */
    public function process_courses($courses) {
        global $OUTPUT;

        // First index is instructor; second is course->shortname.
        $regex      = get_config('syllabus', 'excluderegex');
        $tohidden   = get_config('syllabus', 'emailstohidden');

        $now = time();
        $coursestoprocess = array();
        foreach ($courses as $courseid) {
            $course = get_course($courseid);
            if ($regex) {
                if (preg_match($regex, $course->shortname)) {
                    mtrace("Skipping course $course->shortname because it matches exclude regex.");
                    continue;
                }
            }

            // Fix #10 - Skip courses with no students.
            $coursecon = \context_course::instance($courseid);
            $enrolledstudents = count_enrolled_users($coursecon, 'mod/assign:submit');
            if ($enrolledstudents == 0) {
                mtrace("Skipping course $course->shortname because it has no students.");
                continue;
            }

            $syllabi = get_all_instances_in_course('syllabus', $course, null, true);

            if (count($syllabi) == 0 && $course->startdate < $now
                && ($course->enddate == 0 || $course->enddate > $now)) {

                if ($course->visible || (!$course->visible && $tohidden)) {
                    // Get instructor(s) to notify.
                    $teachers = get_users_by_capability($coursecon, 'mod/syllabus:addinstance', 'u.id');

                    foreach ($teachers as $teacher) {
                        // Don't add a teacher if they can't view the course currently?
                        // Like it's a hidden course in a category where they can't view hidden courses.
                        if (has_capability('moodle/course:viewhiddencourses', $coursecon, $teacher->id)) {
                            $coursestoprocess[$teacher->id][$course->shortname]['name'] = $course->fullname;
                            $coursestoprocess[$teacher->id][$course->shortname]['url'] = (string)
                                new \moodle_url('/course/view.php', array('id' => $course->id));
                        } else {
                            mtrace("Skipping course $course->shortname because course is not visible to teacher.");
                        }
                    }
                }
            }
        }

        $numfaculty = count($coursestoprocess);
        mtrace("Sending emails to $numfaculty faculty member(s)");

        $datestr = userdate($now, get_string('strftimedatefullshort', 'core_langconfig'));
        $docurl = get_config('syllabus', 'addsyllabuslink');
        $data = array();
        foreach ($coursestoprocess as $teacherid => $courses) {
            $data['courses'] = array_values($courses);
            $data['docurl'] = $docurl;
            $msg = $OUTPUT->render_from_template('mod_syllabus/email_reminder', $data);
            $this->email_teacher($teacherid, $msg, $datestr);
        }

    }

    /**
     * Email teacher and let them know they're missing a Syllabus activity.
     * @param int $teacherid The mdl_user id of the teacher of the course.
     * @param string $msg The message to send, in HTML.
     * @param string $datestr The date in m/dd/yy format.
     */
    public function email_teacher($teacherid, $msg, $datestr) {
        global $DB;
        $teacher = $DB->get_record('user', array('id' => $teacherid));

        mtrace("Sending reminder email to $teacher->firstname $teacher->lastname");

        $admin = get_admin();

        $result = email_to_user($teacher, $admin,
                get_string('emailsubj', 'mod_syllabus') . ' - ' . $datestr . ' - ' . $teacher->username, html_to_text($msg), $msg);
        if (!$result) {
            mtrace("Error emailing $teacher->firstname $teacher->lastname");
        }
    }

}
