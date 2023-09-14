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
 * Syllabus module admin settings and defaults
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $displayoptions = resourcelib_get_displayoptions(array(RESOURCELIB_DISPLAY_AUTO,
                                                           RESOURCELIB_DISPLAY_EMBED,
                                                           RESOURCELIB_DISPLAY_FRAME,
                                                           RESOURCELIB_DISPLAY_DOWNLOAD,
                                                           RESOURCELIB_DISPLAY_OPEN,
                                                           RESOURCELIB_DISPLAY_NEW,
                                                           RESOURCELIB_DISPLAY_POPUP,
                                                          ));
    $defaultdisplayoptions = array(RESOURCELIB_DISPLAY_AUTO,
                                   RESOURCELIB_DISPLAY_EMBED,
                                   RESOURCELIB_DISPLAY_DOWNLOAD,
                                   RESOURCELIB_DISPLAY_OPEN,
                                   RESOURCELIB_DISPLAY_POPUP,
                                  );


    // General settings.
    $settings->add(new admin_setting_configtext('syllabus/framesize',
        get_string('framesize', 'syllabus'), get_string('configframesize', 'syllabus'), 130, PARAM_INT));
    $settings->add(new admin_setting_configmultiselect('syllabus/displayoptions',
        get_string('displayoptions', 'syllabus'), get_string('configdisplayoptions', 'syllabus'),
        $defaultdisplayoptions, $displayoptions));

    // Reminder email task settings.
    $settings->add(new admin_setting_heading('syllabusmodtaskreminderemail',
        get_string('taskreminderemailsettings', 'syllabus'), get_string('confreminderemail', 'syllabus')));

    // Task enabled?
    $settings->add(new admin_setting_configcheckbox('syllabus/remindersenabled',
        get_string('enablereminders', 'syllabus'), get_string('configenablereminders', 'syllabus'), 1));
    // Emails to hidden?
    $settings->add(new admin_setting_configcheckbox('syllabus/emailstohidden',
        get_string('emailstohidden', 'syllabus'), get_string('configemailstohidden', 'syllabus'), 1));

    // Categories to process.
    $categories = core_course_category::make_categories_list();
    $settings->add(new admin_setting_configmultiselect('syllabus/catstocheck',
        get_string('catstocheck', 'syllabus'), get_string('configcatstocheck', 'syllabus'), array(), $categories));

    // Link to HowTo Add A Syllabus documentation.
    $settings->add(new admin_setting_configtext('syllabus/addsyllabuslink',
        get_string('addsyllabuslink', 'syllabus'), get_string('configaddsyllabuslink', 'syllabus'), '', PARAM_URL));
    // Exclude courses whose shortnames match this RegEx.
    $settings->add(new admin_setting_configtext('syllabus/excluderegex',
        get_string('excluderegex', 'syllabus'), get_string('configexcluderegex', 'syllabus'), '', PARAM_RAW));


    // Modedit defaults.
    $settings->add(new admin_setting_heading('syllabusmodeditdefaults',
        get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));

    $settings->add(new admin_setting_configcheckbox('syllabus/printintro',
        get_string('printintro', 'syllabus'), get_string('printintroexplain', 'syllabus'), 1));
    $settings->add(new admin_setting_configselect('syllabus/display',
        get_string('displayselect', 'syllabus'), get_string('displayselectexplain', 'syllabus'), RESOURCELIB_DISPLAY_AUTO,
        $displayoptions));
    $settings->add(new admin_setting_configcheckbox('syllabus/showsize',
        get_string('showsize', 'syllabus'), get_string('showsize_desc', 'syllabus'), 0));
    $settings->add(new admin_setting_configcheckbox('syllabus/showtype',
        get_string('showtype', 'syllabus'), get_string('showtype_desc', 'syllabus'), 0));
    $settings->add(new admin_setting_configcheckbox('syllabus/showdate',
        get_string('showdate', 'syllabus'), get_string('showdate_desc', 'syllabus'), 0));
    $settings->add(new admin_setting_configtext('syllabus/popupwidth',
        get_string('popupwidth', 'syllabus'), get_string('popupwidthexplain', 'syllabus'), 620, PARAM_INT, 7));
    $settings->add(new admin_setting_configtext('syllabus/popupheight',
        get_string('popupheight', 'syllabus'), get_string('popupheightexplain', 'syllabus'), 450, PARAM_INT, 7));
    $options = array('0' => get_string('none'), '1' => get_string('allfiles'), '2' => get_string('htmlfilesonly'));
    $settings->add(new admin_setting_configselect('syllabus/filterfiles',
        get_string('filterfiles', 'syllabus'), get_string('filterfilesexplain', 'syllabus'), 0, $options));
}
