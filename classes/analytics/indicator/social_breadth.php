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
 * Social breadth indicator - syllabus.
 *
 * @package   mod_syllabus
 * @copyright 2021 Marty Gilbert <martygilbert@gmail>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\analytics\indicator;

/**
 * Social breadth indicator - syllabus.
 *
 * @package   mod_syllabus
 * @copyright 2021 Marty Gilbert <martygilbert@gmail>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class social_breadth extends activity_base {

    /**
     * Returns the name.
     *
     * If there is a corresponding '_help' string this will be shown as well.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:socialbreadth', 'mod_syllabus');
    }

    /**
     * Get the indicator type
     * @return const int
     */
    public function get_indicator_type() {
        return self::INDICATOR_SOCIAL;
    }

    /**
     * Get the social breadth level
     * @param cm_info $cm the course module info
     * @return const int
     */
    public function get_social_breadth_level(\cm_info $cm) {
        return self::SOCIAL_LEVEL_1;
    }
}
