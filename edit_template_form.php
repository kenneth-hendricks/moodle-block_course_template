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
 * <one-line description>
 *
 * <longer description [optional]>
 *
 * @package      <package name>
 * @subpackage   <subpackage name>
 * @copyright    2012 Catalyst-IT Europe
 * @author       Joby Harding <joby.harding@catalyst-eu.net>
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// no direct script access
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class block_course_template_add_template_form extends moodleform {

    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
        $courseid = $this->_customdata['basecourseid'];
        $coursename = $DB->get_field('course', 'fullname', array('id' => $courseid));

        //
        // Details fieldset
        //
        $mform->addElement('header', 'detailsheading', get_string('details', 'block_course_template'));

        // Based on
        $basedontxt  = html_writer::start_tag('p');
        $basedontxt .= get_string('basedoncourse', 'block_course_template') . ' ';
        $basedontxt .= html_writer::link("{$CFG->wwwroot}/course/view.php?id={$courseid}", $coursename);
        $basedontxt .= html_writer::end_tag('p');
        $mform->addElement('html', $basedontxt);

        // Name
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', 'client');

        // Description
        $mform->addElement('editor', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        // Screenshot
        $mform->addElement('filepicker', 'screenshot', get_string('screenshot', 'block_course_template'), array('maxbytes' => get_max_upload_file_size($CFG->maxbytes), 'accepted_types' => 'image'));

        //
        // Tags fieldset
        //
        $mform->addElement('header', 'tagsheading', get_string('tags'));
        // Existing tags
        $tagtxt = html_writer::tag('p', get_string('existingtags', 'block_course_template') . ': ' . block_course_template_get_tag_list());
        $mform->addElement('html', $tagtxt);
        // Tags
        $mform->addElement('text', 'tags', get_string('tags'));
        $mform->setType('tags', PARAM_TEXT);
        $mform->addHelpButton('tags', 'tagshelp', 'block_course_template');

        //
        // Hidden fields
        //
        $mform->addElement('hidden', 'course', $this->_customdata['basecourseid']);
        $mform->addElement('hidden', 'template', $this->_customdata['templateid']);
        $mform->addElement('hidden', 'referer', $this->_customdata['referer']);

        //
        // Action buttons
        //
        $actiontxt = ($this->_customdata['templateid'] !== -1) ? get_string('updatetemplate', 'block_course_template') : get_string('createtemplate', 'block_course_template');
        $this->add_action_buttons(true, $actiontxt);
    }
}
