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
 * Private syllabus module utility functions
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/syllabus/lib.php");

/**
 * Redirected to migrated syllabus if needed,
 * return if incorrect parameters specified
 * @param int $oldid
 * @param int $cmid
 * @return void
 */

/**
 * Display embedded syllabus file.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function syllabus_display_embed($syllabus, $cm, $course, $file) {
    global $CFG, $PAGE, $OUTPUT;

    $clicktoopen = syllabus_get_clicktoopen($file, $syllabus->revision);

    $context = context_module::instance($cm->id);
    $moodleurl = moodle_url::make_pluginfile_url($context->id, 'mod_syllabus', 'content', $syllabus->revision,
            $file->get_filepath(), $file->get_filename());

    $mimetype = $file->get_mimetype();
    $title    = $syllabus->name;

    $extension = resourcelib_get_extension($file->get_filename());

    $mediamanager = core_media_manager::instance($PAGE);
    $embedoptions = array(
        core_media_manager::OPTION_TRUSTED => true,
        core_media_manager::OPTION_BLOCK => true,
    );

    if (file_mimetype_in_typegroup($mimetype, 'web_image')) {  // It's an image.
        $code = resourcelib_embed_image($moodleurl->out(), $title);

    } else if ($mimetype === 'application/pdf') {
        // PDF document.
        $code = resourcelib_embed_pdf($moodleurl->out(), $title, $clicktoopen);

    } else if ($mediamanager->can_embed_url($moodleurl, $embedoptions)) {
        // Media (audio/video) file.
        $code = $mediamanager->embed_url($moodleurl, $title, 0, 0, $embedoptions);

    } else {
        // We need a way to discover if we are loading remote docs inside an iframe.
        $moodleurl->param('embed', 1);

        // Anything else - just try object tag enlarged as much as possible.
        $code = resourcelib_embed_general($moodleurl, $title, $clicktoopen, $mimetype);
    }

    syllabus_print_header($syllabus, $cm, $course);
    syllabus_print_heading($syllabus, $cm, $course);

    echo $code;

    syllabus_print_intro($syllabus, $cm, $course);

    echo $OUTPUT->footer();
    die;
}

/**
 * Display syllabus frames.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function syllabus_display_frame($syllabus, $cm, $course, $file) {
    global $PAGE, $OUTPUT, $CFG;

    $frame = optional_param('frameset', 'main', PARAM_ALPHA);

    if ($frame === 'top') {
        $PAGE->set_pagelayout('frametop');
        syllabus_print_header($syllabus, $cm, $course);
        syllabus_print_heading($syllabus, $cm, $course);
        syllabus_print_intro($syllabus, $cm, $course);
        echo $OUTPUT->footer();
        die;

    } else {
        $config = get_config('syllabus');
        $context = context_module::instance($cm->id);
        $path = '/'.$context->id.'/mod_syllabus/content/'.$syllabus->revision.$file->get_filepath().$file->get_filename();
        $fileurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
        $navurl = "$CFG->wwwroot/mod/syllabus/view.php?id=$cm->id&amp;frameset=top";
        $title = strip_tags(format_string($course->shortname.': '.$syllabus->name));
        $framesize = $config->framesize;
        $contentframetitle = s(format_string($syllabus->name));
        $modulename = s(get_string('modulename', 'syllabus'));
        $dir = get_string('thisdirection', 'langconfig');

        $file = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
<html dir="$dir">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <title>$title</title>
  </head>
  <frameset rows="$framesize,*">
    <frame src="$navurl" title="$modulename" />
    <frame src="$fileurl" title="$contentframetitle" />
  </frameset>
</html>
EOF;

        @header('Content-Type: text/html; charset=utf-8');
        echo $file;
        die;
    }
}

/**
 * Internal function - create click to open text with link.
 * Unsure of some of the parameter values.
 * @param stored_file $file main file
 * @param string $revision from the Syllabus object
 * @param string $extra options to the <a> tag
 * @return string
 */
function syllabus_get_clicktoopen($file, $revision, $extra='') {
    global $CFG;

    $filename = $file->get_filename();
    $path = '/'.$file->get_contextid().'/mod_syllabus/content/'.$revision.$file->get_filepath().$file->get_filename();
    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);

    $string = get_string('clicktoopen2', 'syllabus', "<a href=\"$fullurl\" $extra>$filename</a>");

    return $string;
}

/**
 * Internal function - create click to open text with link.
 * @param stored_file $file object
 * @param string $revision from the Syllabus object
 * @return string
 */
function syllabus_get_clicktodownload($file, $revision) {
    global $CFG;

    $filename = $file->get_filename();
    $path = '/'.$file->get_contextid().'/mod_syllabus/content/'.$revision.$file->get_filepath().$file->get_filename();
    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, true);

    $string = get_string('clicktodownload', 'syllabus', "<a href=\"$fullurl\">$filename</a>");

    return $string;
}

/**
 * Print syllabus info and workaround link when JS not available.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function syllabus_print_workaround($syllabus, $cm, $course, $file) {
    global $CFG, $OUTPUT;

    syllabus_print_header($syllabus, $cm, $course);
    syllabus_print_heading($syllabus, $cm, $course, true);
    syllabus_print_intro($syllabus, $cm, $course, true);

    $syllabus->mainfile = $file->get_filename();
    echo '<div class="resourceworkaround">';
    switch (syllabus_get_final_display_type($syllabus)) {
        case RESOURCELIB_DISPLAY_POPUP:
            $path = '/'.$file->get_contextid().'/mod_syllabus/content/'.$syllabus->revision.
                $file->get_filepath().$file->get_filename();
            $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
            $options = empty($syllabus->displayoptions) ? array() : unserialize($syllabus->displayoptions);
            $width  = empty($options['popupwidth']) ? 620 : $options['popupwidth'];
            $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
            $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,".
                "copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
            $extra = "onclick=\"window.open('$fullurl', '', '$wh'); return false;\"";
            echo syllabus_get_clicktoopen($file, $syllabus->revision, $extra);
            break;

        case RESOURCELIB_DISPLAY_NEW:
            $extra = 'onclick="this.target=\'_blank\'"';
            echo syllabus_get_clicktoopen($file, $syllabus->revision, $extra);
            break;

        case RESOURCELIB_DISPLAY_DOWNLOAD:
            echo syllabus_get_clicktodownload($file, $syllabus->revision);
            break;

        case RESOURCELIB_DISPLAY_OPEN:
        default:
            echo syllabus_get_clicktoopen($file, $syllabus->revision);
            break;
    }
    echo '</div>';

    echo $OUTPUT->footer();
    die;
}

/**
 * Print syllabus header.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @return void
 */
function syllabus_print_header($syllabus, $cm, $course) {
    global $PAGE, $OUTPUT;

    $PAGE->set_title($course->shortname.': '.$syllabus->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_activity_record($syllabus);
    echo $OUTPUT->header();
}

/**
 * Print syllabus heading.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @param bool $notused This variable is no longer used
 * @return void
 */
function syllabus_print_heading($syllabus, $cm, $course, $notused = false) {
    global $OUTPUT;
    echo $OUTPUT->heading(format_string($syllabus->name), 2);
}


/**
 * Gets details of the file to cache in course cache to be displayed using syllabus_get_optional_details()
 *
 * @param object $syllabus Resource table row (only property 'displayoptions' is used here)
 * @param object $cm Course-module table row
 * @return string Size and type or empty string if show options are not enabled
 */
function syllabus_get_file_details($syllabus, $cm) {
    $options = empty($syllabus->displayoptions) ? array() : @unserialize($syllabus->displayoptions);
    $filedetails = array();
    if (!empty($options['showsize']) || !empty($options['showtype']) || !empty($options['showdate'])) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', 0, 'sortorder DESC, id ASC', false);
        // For a typical file syllabus, the sortorder is 1 for the main file
        // and 0 for all other files. This sort approach is used just in case
        // there are situations where the file has a different sort order.
        $mainfile = $files ? reset($files) : null;
        if (!empty($options['showsize'])) {
            $filedetails['size'] = 0;
            foreach ($files as $file) {
                // This will also synchronize the file size for external files if needed.
                $filedetails['size'] += $file->get_filesize();
                if ($file->get_repository_id()) {
                    // If file is a reference the 'size' attribute can not be cached.
                    $filedetails['isref'] = true;
                }
            }
        }
        if (!empty($options['showtype'])) {
            if ($mainfile) {
                $filedetails['type'] = get_mimetype_description($mainfile);
                $filedetails['mimetype'] = $mainfile->get_mimetype();
                // Only show type if it is not unknown.
                if ($filedetails['type'] === get_mimetype_description('document/unknown')) {
                    $filedetails['type'] = '';
                }
            } else {
                $filedetails['type'] = '';
            }
        }
        if (!empty($options['showdate'])) {
            if ($mainfile) {
                // Modified date may be up to several minutes later than uploaded date just because
                // teacher did not submit the form promptly. Give teacher up to 5 minutes to do it.
                if ($mainfile->get_timemodified() > $mainfile->get_timecreated() + 5 * MINSECS) {
                    $filedetails['modifieddate'] = $mainfile->get_timemodified();
                } else {
                    $filedetails['uploadeddate'] = $mainfile->get_timecreated();
                }
                if ($mainfile->get_repository_id()) {
                    // If main file is a reference the 'date' attribute can not be cached.
                    $filedetails['isref'] = true;
                }
            } else {
                $filedetails['uploadeddate'] = '';
            }
        }
    }
    return $filedetails;
}

/**
 * Gets optional details for a syllabus, depending on syllabus settings.
 *
 * Result may include the file size and type if those settings are chosen,
 * or blank if none.
 *
 * @param object $syllabus Resource table row (only property 'displayoptions' is used here)
 * @param object $cm Course-module table row
 * @return string Size and type or empty string if show options are not enabled
 */
function syllabus_get_optional_details($syllabus, $cm) {
    global $DB;

    $details = '';

    $options = empty($syllabus->displayoptions) ? array() : @unserialize($syllabus->displayoptions);
    if (!empty($options['showsize']) || !empty($options['showtype']) || !empty($options['showdate'])) {
        if (!array_key_exists('filedetails', $options)) {
            $filedetails = syllabus_get_file_details($syllabus, $cm);
        } else {
            $filedetails = $options['filedetails'];
        }
        $size = '';
        $type = '';
        $date = '';
        $langstring = '';
        $infodisplayed = 0;
        if (!empty($options['showsize'])) {
            if (!empty($filedetails['size'])) {
                $size = display_size($filedetails['size']);
                $langstring .= 'size';
                $infodisplayed += 1;
            }
        }
        if (!empty($options['showtype'])) {
            if (!empty($filedetails['type'])) {
                $type = $filedetails['type'];
                $langstring .= 'type';
                $infodisplayed += 1;
            }
        }
        if (!empty($options['showdate']) && (!empty($filedetails['modifieddate']) || !empty($filedetails['uploadeddate']))) {
            if (!empty($filedetails['modifieddate'])) {
                $date = get_string('modifieddate', 'mod_syllabus', userdate($filedetails['modifieddate'],
                    get_string('strftimedatetimeshort', 'langconfig')));
            } else if (!empty($filedetails['uploadeddate'])) {
                $date = get_string('uploadeddate', 'mod_syllabus', userdate($filedetails['uploadeddate'],
                    get_string('strftimedatetimeshort', 'langconfig')));
            }
            $langstring .= 'date';
            $infodisplayed += 1;
        }

        if ($infodisplayed > 1) {
            $details = get_string("syllabusdetails_{$langstring}", 'syllabus',
                    (object)array('size' => $size, 'type' => $type, 'date' => $date));
        } else {
            // Only one of size, type and date is set, so just append.
            $details = $size . $type . $date;
        }
    }

    return $details;
}

/**
 * Print syllabus introduction.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @param bool $ignoresettings print even if not specified in modedit
 * @return void
 */
function syllabus_print_intro($syllabus, $cm, $course, $ignoresettings=false) {
    global $OUTPUT;

    $options = empty($syllabus->displayoptions) ? array() : unserialize($syllabus->displayoptions);

    $extraintro = syllabus_get_optional_details($syllabus, $cm);
    if ($extraintro) {
        // Put a paragaph tag around the details.
        $extraintro = html_writer::tag('p', $extraintro, array('class' => 'syllabusdetails'));
    }

    if ($ignoresettings || !empty($options['printintro']) || $extraintro) {
        $gotintro = trim(strip_tags($syllabus->intro));
        if ($gotintro || $extraintro) {
            echo $OUTPUT->box_start('mod_introbox', 'resourceintro');
            if ($gotintro) {
                echo format_module_intro('syllabus', $syllabus, $cm->id);
            }
            echo $extraintro;
            echo $OUTPUT->box_end();
        }
    }
}

/**
 * Print warning that file can not be found.
 * @param object $syllabus
 * @param object $cm
 * @param object $course
 * @return void, does not return
 */
function syllabus_print_filenotfound($syllabus, $cm, $course) {
    global $DB, $OUTPUT;

    syllabus_print_header($syllabus, $cm, $course);
    syllabus_print_heading($syllabus, $cm, $course);
    syllabus_print_intro($syllabus, $cm, $course);

    echo $OUTPUT->notification(get_string('filenotfound', 'syllabus'));
    echo $OUTPUT->footer();
    die;
}

/**
 * Decide the best display format.
 * @param object $syllabus
 * @return int display type constant
 */
function syllabus_get_final_display_type($syllabus) {
    global $CFG, $PAGE;

    if ($syllabus->display != RESOURCELIB_DISPLAY_AUTO) {
        return $syllabus->display;
    }

    if (empty($syllabus->mainfile)) {
        return RESOURCELIB_DISPLAY_DOWNLOAD;
    } else {
        $mimetype = mimeinfo('type', $syllabus->mainfile);
    }

    if (file_mimetype_in_typegroup($mimetype, 'archive')) {
        return RESOURCELIB_DISPLAY_DOWNLOAD;
    }
    if (file_mimetype_in_typegroup($mimetype, array('web_image', '.htm', 'web_video', 'web_audio'))) {
        return RESOURCELIB_DISPLAY_EMBED;
    }

    // Let the browser deal with it somehow.
    return RESOURCELIB_DISPLAY_OPEN;
}

/**
 * File browsing support class
 */
class syllabus_content_file_info extends file_info_stored {
    /**
     * Returns parent file_info instance
     * @return file_info or null for root
     */
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }

    /**
     * Returns localised visible name.
     * @return string
     */
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

/**
 * Set the Syllabus main file
 * @param stdClass $data the data needed to set the main file
 */
function syllabus_set_mainfile($data) {
    global $DB;
    $fs = get_file_storage();
    $cmid = $data->coursemodule;
    $draftitemid = $data->files;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $options = array('subdirs' => true, 'embed' => false);
        if ($data->display == RESOURCELIB_DISPLAY_EMBED) {
            $options['embed'] = true;
        }
        file_save_draft_area_files($draftitemid, $context->id, 'mod_syllabus', 'content', 0, $options);
    }
    $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        // Only one file attached, set it as main file automatically.
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_syllabus', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}
