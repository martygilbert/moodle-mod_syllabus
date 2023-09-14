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
 * Resource external API
 *
 * @package    mod_syllabus
 * @category   external
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.9
 */

use core_course\external\helper_for_get_mods_by_courses;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * Resource external functions
 *
 * @package    mod_syllabus
 * @category   external
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.9
 */
class mod_syllabus_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.9
     */
    public static function view_syllabus_parameters() {
        return new external_function_parameters(
            array(
                'syllabusid' => new external_value(PARAM_INT, 'syllabus instance id')
            )
        );
    }

    /**
     * Simulate the syllabus/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $syllabusid the syllabus instance id
     * @return array of warnings and status result
     * @since Moodle 3.9
     * @throws moodle_exception
     */
    public static function view_syllabus($syllabusid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/syllabus/lib.php");

        $params = self::validate_parameters(self::view_syllabus_parameters(),
                                            array(
                                                'syllabusid' => $syllabusid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $syllabus = $DB->get_record('syllabus', array('id' => $params['syllabusid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($syllabus, 'syllabus');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/syllabus:view', $context);

        // Call the syllabus/lib API.
        syllabus_view($syllabus, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.9
     */
    public static function view_syllabus_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_syllabus_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.9
     */
    public static function get_syllabus_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of files in a provided list of courses.
     * If no list is provided all files that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and files
     * @since Moodle 3.9
     */
    public static function get_syllabus_by_courses($courseids = array()) {

        $warnings = array();
        $returnedsyllabi = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_syllabus_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the syllabi in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $syllabi = get_all_instances_in_courses("syllabus", $courses);
            foreach ($syllabi as $syllabus) {
                $context = context_module::instance($syllabus->coursemodule);

                helper_for_get_mods_by_courses::format_name_and_intro($syllabus, 'mod_syllabus');
                $syllabus->contentfiles = external_util::get_area_files($context->id, 'mod_syllabus', 'content');

                $returnedsyllabi[] = $syllabus;
            }
        }

        $result = array(
            'syllabus' => $returnedsyllabi,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_syllabus_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.9
     */
    public static function get_syllabus_by_courses_returns() {
        return new external_single_structure(
            array(
                'syllabus' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                        helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(),
                        [
                            'contentfiles' => new external_files('Files in the content'),
                            'tobemigrated' => new external_value(PARAM_INT, 'Whether this syllabus was migrated'),
                            'legacyfiles' => new external_value(PARAM_INT, 'Legacy files flag'),
                            'legacyfileslast' => new external_value(PARAM_INT, 'Legacy files last control flag'),
                            'display' => new external_value(PARAM_INT, 'How to display the syllabu'),
                            'displayoptions' => new external_value(PARAM_RAW, 'Display options (width, height)'),
                            'filterfiles' => new external_value(PARAM_INT, 'If filters should be applied to the syllabus content'),
                            'revision' => new external_value(PARAM_INT, 'Incremented when after each file changes, to avoid cache'),
                            'timemodified' => new external_value(PARAM_INT, 'Last time the syllabus was modified'),
                        ]
                    ))
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
