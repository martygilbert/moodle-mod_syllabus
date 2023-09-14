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
 * Define all the backup steps that will be used by the backup_syllabus_activity_task
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete syllabus structure for backup, with file and id annotations
 */
class backup_syllabus_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the syllabus activity
     * @return void
     */
    protected function define_structure() {

        // Define each element separated.
        $syllabus = new backup_nested_element('syllabus', array('id'), array(
            'name', 'intro', 'introformat', 'tobemigrated',
            'legacyfiles', 'legacyfileslast', 'display',
            'displayoptions', 'filterfiles', 'revision', 'timemodified'));

        // Build the tree.

        // Define sources.
        $syllabus->set_source_table('syllabus', array('id' => backup::VAR_ACTIVITYID));

        // Define id annotations (none).

        // Define file annotations.
        $syllabus->annotate_files('mod_syllabus', 'intro', null); // This file areas haven't itemid.
        $syllabus->annotate_files('mod_syllabus', 'content', null); // This file areas haven't itemid.

        // Return the root element (syllabus), wrapped into standard activity structure.
        return $this->prepare_activity_structure($syllabus);
    }
}
