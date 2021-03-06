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
 * Create a new course from a course template.
 *
 * @package      blocks
 * @subpackage   course_template
 * @copyright    2012 Catalyst-IT Europe
 * @author       Joby Harding <joby.harding@catalyst-eu.net>
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('course_form.php');
require_once($CFG->dirroot . '/blocks/course_template/locallib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

require_login();

$templateid = optional_param('t', 0, PARAM_INT);
$courseid = optional_param('c', 0, PARAM_INT);
$referer = optional_param('referer', get_referer(false), PARAM_URL);
$setchannel = optional_param('setchannel', 0, PARAM_BOOL); // New course should be a learning channel.
$systemcontext = context_system::instance();

if ($courseid === 0) {
    $context = $systemcontext;
    $coursecategories = coursecat::make_categories_list('block/course_template:createcourse');
    if (empty($coursecategories)) {
        print_error('error:nocategoryperms', 'block_course_template');
    }
    $insert = false;
} else {
    if ($courseid == 1) {
        totara_set_notification(get_string('error:sitecourse', 'block_course_template'), $referer);
    }
    $context = context_course::instance($courseid);
    require_capability('block/course_template:import', $context);
    $course = $DB->get_record('course', array('id' => $courseid));
    $insert = true;
}

if (!$DB->record_exists('block_course_template', array())) {
    totara_set_notification(get_string('error:notemplates', 'block_course_template'), $referer);
}

$PAGE->set_url('/blocks/course_template/newcourse.php');
$PAGE->set_context($context);
if ($insert) {
    $PAGE->set_pagelayout('admin');
    $PAGE->set_course($course);
} else {
    $PAGE->set_pagelayout('course');
}
if ($insert != 1) {
    $headingstr = get_string('newcoursefromtemp', 'block_course_template');
} else {
    $headingstr  = get_string('importintocourse', 'block_course_template');
    // Using format_string() before page context set causes error.
    $headingstr .= " '" . format_string($course->fullname) . "'";
}
$PAGE->set_title($headingstr);
$PAGE->set_heading($headingstr);
$PAGE->navbar->add(get_string('coursetemplates', 'block_course_template'));
$PAGE->navbar->add($headingstr);

$mform = new block_course_template_course_form(
    null,
    array(
        'template' => $templateid,
        'referer' => $referer,
        'courseid' => $courseid,
        'setchannel' => $setchannel,
    )
);

if ($mform->is_cancelled()) {
    redirect($referer);
}

if ($data = $mform->get_data()) {
    require_sesskey();

    if (!$coursetemplate = $DB->get_record('block_course_template', array('id' => $data->template))) {
        print_error(get_string('error:notemplate', 'block_course_template', $data->template));
    }

    // If the template file is missing, create it now
    if (empty($coursetemplate->filename)) {
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $backupfile = course_template_create_archive($coursetemplate, $USER->id);
        $coursetemplate->filename = $backupfile->get_filename();
        $DB->set_field(
            'block_course_template',
            'filename',
            $coursetemplate->filename,
            array('id' => $coursetemplate->id)
        );
    }

    $fs = get_file_storage();
    $restorefile = $fs->get_file_by_hash(
        sha1("/$systemcontext->id/block_course_template/backupfile/$coursetemplate->id/$coursetemplate->filename")
    );

    if (empty($restorefile)) {
        error_log(get_string('error:processerror', 'block_course_template'));
        totara_set_notification(get_string('error:processerror', 'block_course_template'), $referer);
    }

    $tmpcopyname = md5($coursetemplate->filename);
    if (!$tmpcopy = $restorefile->copy_content_to($CFG->tempdir . '/backup/' . $tmpcopyname)) {
        print_error('error:movearchive', 'block_course_template');
    }

    if (!$insert) {
        $courseid = restore_dbops::create_new_course($data->fullname, $data->shortname, $data->category);
        // Copy audience visibility
        if ($course = get_course($courseid)) {
            if (!empty($CFG->audiencevisibility) && ($CFG->audiencevisibility === '1')) {
                $visiblecohorts = totara_cohort_get_visible_learning($coursetemplate->course);
                // Add new cohort associations.
                foreach ($visiblecohorts as $cohort) {
                    totara_cohort_add_association($cohort->id, $courseid, COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);
                }
                // Set audience visibility.
                $course->audiencevisible = $DB->get_field('course', 'audiencevisible', array('id' => $coursetemplate->course));

                update_course($course);
            }
        }
    }

    $fb = get_file_packer('application/vnd.moodle.backup');
    $tmpdirnewname = restore_controller::get_tempdir_name($context->id, $USER->id);
    $tmpdirpath =  $CFG->tempdir . '/backup/' . $tmpdirnewname . '/';
    $outcome = $fb->extract_to_pathname($CFG->tempdir . '/backup/' . $tmpcopyname, $tmpdirpath);

    if ($outcome) {
        fulldelete($tmpcopyname);
    } else {
        print_error('error:extractarchive', 'block_course_template');
    }

    $tempdestination = $tmpdirpath;
    if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
        print_error('error:nodirectory');
    }

    $restoretarget = $insert != 1 ? backup::TARGET_NEW_COURSE : backup::TARGET_EXISTING_ADDING;

    $rc = new restore_controller(
        $tmpdirnewname,
        $courseid,
        backup::INTERACTIVE_YES,
        backup::MODE_IMPORT,
        $USER->id,
        $restoretarget
    );

    if (!$insert) {
        $plan = $rc->get_plan();
        $tasks = $plan->get_tasks();

        foreach ($tasks as &$task) {
            if (!($task instanceof restore_root_task)) {
                $settings = $task->get_settings();
                foreach ($settings as &$setting) {
                    $name = $setting->get_ui_name();

                    switch ($name) {
                        case 'setting_course_course_fullname' :
                            $setting->set_value($data->fullname);
                            break;
                        case 'setting_course_course_shortname' :
                            $setting->set_value($data->shortname);
                            break;
                        case 'setting_course_course_id' :
                            $setting->set_value($data->idnumber);
                            break;
                        case 'setting_course_course_startdate' :
                            $setting->set_value($data->startdate);
                            break;
                    }
                }
            }
        }
    }

    if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
        $rc->convert();
    }

    $rc->finish_ui();

    $rc->execute_precheck();

    $rc->execute_plan();

    fulldelete($tempdestination);

    if (!$insert) {
        $updaterec = new stdClass();
        $updaterec->id = $courseid;
        $updaterec->idnumber = $data->idnumber;

        update_course($updaterec);
    }

    if ($insert) {
        $message = get_string('importedsuccessfully', 'block_course_template');
    } else {
        $message = get_string('createdsuccessfully', 'block_course_template');
    }

    /** Set enrolment methods */
    $instances = enrol_get_instances($coursetemplate->course, false);
    foreach ($instances as $instance) {
        $fields = (array)$instance;
        $plugin = enrol_get_plugin($fields['enrol']);
        unset($fields['id']);
        $course = new stdClass();
        $course->id = $courseid;
        $plugin->add_instance($course, $fields);
    }

    // Update course summary.
    if (!empty($data->summary_editor['text'])) {
        $updaterec = new stdClass();
        $updaterec->id = $courseid;
        $updaterec->summary = $data->summary_editor['text'];

        update_course($updaterec);
    }

    // Save course custom field data.
    if (isset($data->setchannel)) {
        // Just insert into db directly rather than using the clunky API.
        // First clear the data copied from the template.
        $DB->delete_records('course_info_data', array('courseid' => $courseid));

        // Set course custom field for course heading.
        $todb = new stdClass();
        $todb->courseid = $courseid;
        $todb->fieldid = get_config('local_catalystlms', 'customcourseheading');
        $todb->data = $data->customcourseheading['text'];
        $DB->insert_record('course_info_data', $todb);

        // Set classification for catalogue search.
        require_once($CFG->dirroot . '/local/search/lib.php');
        $formatid = $DB->get_field('local_search_contentformats', 'id', array('format' => 'learningchannel'));
        local_content_save_course_formats($courseid, array($formatid));
    }

    totara_set_notification($message, new moodle_url('/course/view.php', array('id' => $courseid)), array('class' => 'notifysuccess'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($headingstr);

$mform->display();

echo $OUTPUT->footer();
