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
 * Syllabus resource - observer for various events
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Event observer for mod_syllabus
 */
class mod_syllabus_observer {

    /**
     * Triggered via course_module_updated event
     * @param \mod_syllabus\event\course_module_updated $event
     */
    public static function syllabus_updated(\mod_syllabus\event\course_module_updated $event) {
        // To be added later.
    }

    /**
     * Triggered via course_category_deleted event. Removes the deleted category
     * from the catstocheck config setting if it is present.
     * @param \core\event\course_category_deleted $event
     */
    public static function course_category_deleted(\core\event\course_category_deleted $event) {
        $catid = (string)$event->objectid;

        $categories = get_config('syllabus', 'catstocheck');
        if (empty($categories)) {
            return;
        }

        $allcats = explode(',', $categories);
        if (!in_array($catid, $allcats)) {
            return;
        }

        $filtered = array_values(array_filter($allcats, function($cat) use ($catid) {
            return strlen($cat) && $cat !== $catid;
        }));

        set_config('catstocheck', implode(',', $filtered), 'syllabus');
    }

}
