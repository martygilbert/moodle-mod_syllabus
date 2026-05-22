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
 * The purpose of this script is to download all of the syllabi in a category, recursively.
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$usage = get_string('cliusage', 'syllabus');

list ($options, $unrecognized) = cli_get_params([
    'help' => false,
    'path' => null,
    'catid' => null,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode(PHP_EOL . ' ', $unrecognized);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognized));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

if (empty($options['catid'])) {
    cli_error(get_string('climissingrequiredarg', 'syllabus', 'catid'), 3);
} else if (empty($options['path'])) {
    cli_error(get_string('climissingrequiredarg', 'syllabus', 'path'), 3);
}

$dest = $options['path'];
$catid = $options['catid'];

global $CFG, $DB;

// Run these as admin user.
\core\cron::setup_user();

$category = $DB->get_record('course_categories', ['id' => $catid]);

if (!$category) {
    cli_error(get_string('clicatidnotfound', 'syllabus', $catid), 4);
}

make_path($dest);

$coursecat = \core_course_category::get($category->id, MUST_EXIST, true);
$courses = $coursecat->get_courses(['recursive' => true, 'idonly' => true]);

$fs = get_file_storage();

foreach ($courses as $cid) {
    $course = get_course($cid);

    $thiscoursecat = \core_course_category::get($course->category);
    $catpath = $thiscoursecat->get_nested_name(false);
    $catpath = preg_replace("/[^A-Za-z0-9\/]/", '', $catpath);

    $syllabi = get_all_instances_in_course('syllabus', $course, null, true);

    $newpath = $dest . '/' . $catpath . '/' . $course->shortname;

    $coursecon = context_course::instance($cid);
    $teachers = get_users_by_capability($coursecon, 'mod/assign:grade');

    $teacherdisp = get_string('clidispteacher', 'syllabus')."\n";
    foreach ($teachers as $teacher) {
        $teacherdisp .= $teacher->firstname .' '.$teacher->lastname.','.$teacher->email."\n";
    }

    $counter = 0;
    foreach ($syllabi as $syllabus) {
        make_path($newpath);

        $modcon = context_module::instance($syllabus->coursemodule);
        $files = $fs->get_area_files($modcon->id, 'mod_syllabus', 'content', 0,
            'sortorder DESC, id ASC', false);

        // Convert desc to text.
        $intro = html_to_text($syllabus->intro);

        file_put_contents($newpath . '/' . 'description.txt', $teacherdisp."\n\n".$intro);

        foreach ($files as $file) {
            $file = reset($files);

            $content = $file->get_content();

            $fn = preg_replace("/[^A-Za-z0-9\.-]/", '', $file->get_filename());
            $fs->get_file_system()->copy_content_from_storedfile($file, $newpath .'/'. $fn);
            $counter++;
        }
    }
}

/**
 * This will make a directory on the given path, if it doesn't exist
 * @param string $newpath The path/dir to create.
 */
function make_path($newpath) {
    if (!file_exists($newpath)) {
        if (!mkdir ($newpath, 0755, true)) {
            cli_error( "Error making directory $newpath. Exiting.", 5);
        }
    }
}
