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
 * Syllabus module version information
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/syllabus/lib.php');
require_once($CFG->dirroot.'/mod/syllabus/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id       = optional_param('id', 0, PARAM_INT); // Course Module ID.
$r        = optional_param('r', 0, PARAM_INT);  // Resource instance ID.
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($r) {
    if (!$syllabus = $DB->get_record('syllabus', array('id' => $r))) {
        throw new \moodle_exception('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $syllabus->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('syllabus', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $syllabus = $DB->get_record('syllabus', array('id' => $cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/syllabus:view', $context);

// Completion and trigger events.
syllabus_view($syllabus, $course, $cm, $context);

$PAGE->set_url('/mod/syllabus/view.php', array('id' => $cm->id));

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', 0,
    'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
if (count($files) < 1) {
    syllabus_print_filenotfound($syllabus, $cm, $course);
    die;
} else {
    $file = reset($files);
    unset($files);
}

$syllabus->mainfile = $file->get_filename();
$displaytype = syllabus_get_final_display_type($syllabus);
if ($displaytype == RESOURCELIB_DISPLAY_OPEN || $displaytype == RESOURCELIB_DISPLAY_DOWNLOAD) {
    $redirect = true;
}

// Don't redirect teachers, otherwise they can not access course or module settings.
if ($redirect && !course_get_format($course)->has_view_page() &&
        (has_capability('moodle/course:manageactivities', $context) ||
        has_capability('moodle/course:update', context_course::instance($course->id)))) {
    $redirect = false;
}

if ($redirect && !$forceview) {
    // Coming from course page or url index page.
    // This redirect trick solves caching problems when tracking views.
    $path = '/'.$context->id.'/mod_syllabus/content/'.$syllabus->revision.$file->get_filepath().$file->get_filename();
    $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, $displaytype == RESOURCELIB_DISPLAY_DOWNLOAD);
    redirect($fullurl);
}

switch ($displaytype) {
    case RESOURCELIB_DISPLAY_EMBED:
        syllabus_display_embed($syllabus, $cm, $course, $file);
        break;
    case RESOURCELIB_DISPLAY_FRAME:
        syllabus_display_frame($syllabus, $cm, $course, $file);
        break;
    default:
        syllabus_print_workaround($syllabus, $cm, $course, $file);
        break;
}

