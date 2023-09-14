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
 * Mobile output class for mod_syllabus
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use context_module;
use mod_syllabus_external;

/**
 * Mobile output class for syllabus
 * @package      mod_syllabus
 * @copyright    2021 Marty Gilbert <martygilbert@gmail>
 * @license      http://www.gnu.org/copyleft/gpl.html BNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the syllabus course view for the mobile app.
     *
     * @param mixed $args
     * @return array HTML, javascript and other data.
     */
    public static function mobile_syllabus_view($args) {
        global $OUTPUT;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('syllabus', $args->cmid);

        // Capabilities check.
        $context = \context_module::instance($cm->id);

        require_capability ('mod/syllabus:view', $context);

        try {
            // Mark it as viewed.
            $syllabi = mod_syllabus_external::view_syllabus($cm->instance);
        } catch (Exception $e) {
            $issues = array();
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', 0,
            'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
        $file = reset($files);

        $fileurl = \moodle_url::make_webservice_pluginfile_url(
                                        $context->id, 'mod_syllabus', 'content', 0,
                                        $file->get_filepath(), $file->get_filename())->out(false);

        $thisfile               = new \stdClass;
        $thisfile->filename     = $file->get_filename();
        $thisfile->fileurl      = $fileurl;
        $thisfile->timemodified = $file->get_timemodified();
        $thisfile->size         = $file->get_filesize();

        $data = array(
            'file'  => $thisfile,
        );

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_syllabus/mobile_view_syllabus', $data),
                ],
            ],
            'javascript' => '',
            'files' => '',
        ];
    }
}
