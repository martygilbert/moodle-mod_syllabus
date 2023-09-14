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

namespace mod_syllabus;

/**
 * PHPUnit data generator testcase.
 *
 * @package    mod_syllabus
 * @covers \mod_syllabus_generator
 * @category   external
 * @copyright  2020 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.5
 */
class generator_test extends \advanced_testcase {

    public function test_generator() {
        global $DB, $SITE;

        $this->resetAfterTest(true);

        // Must be a non-guest user to create syllabi.
        $this->setAdminUser();

        // There are 0 syllabi initially.
        $this->assertEquals(0, $DB->count_records('syllabus'));

        // Create the generator object and do standard checks.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_syllabus');
        $this->assertInstanceOf('mod_syllabus_generator', $generator);
        $this->assertEquals('syllabus', $generator->get_modulename());

        // Create three instances in the site course.
        $generator->create_instance(array('course' => $SITE->id));
        $generator->create_instance(array('course' => $SITE->id));
        $syllabus = $generator->create_instance(array('course' => $SITE->id));
        $this->assertEquals(3, $DB->count_records('syllabus'));

        // Check the course-module is correct.
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id);
        $this->assertEquals($syllabus->id, $cm->instance);
        $this->assertEquals('syllabus', $cm->modname);
        $this->assertEquals($SITE->id, $cm->course);

        // Check the context is correct.
        $context = \context_module::instance($cm->id);
        $this->assertEquals($syllabus->cmid, $context->instanceid);

        // Check that generated syllabus module contains a file.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', false, '', false);
        $file = array_values($files)[0];
        $this->assertCount(1, $files);
        $this->assertEquals('syllabus3.txt', $file->get_filename());
        $this->assertEquals('Test syllabus syllabus3.txt file', $file->get_content());

        // Create a new syllabus specifying the file name.
        $syllabus = $generator->create_instance(['course' => $SITE->id, 'defaultfilename' => 'myfile.pdf']);

        // Check that generated syllabus module contains a file with the specified name.
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id);
        $context = \context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', false, '', false);
        $file = array_values($files)[0];
        $this->assertCount(1, $files);
        $this->assertEquals('myfile.pdf', $file->get_filename());
        $this->assertEquals('Test syllabus myfile.pdf file', $file->get_content());

        // Create a new syllabus uploading a file.
        $syllabus = $generator->create_instance([
            'course' => $SITE->id,
            'uploaded' => true,
            'defaultfilename' => 'mod/syllabus/tests/fixtures/samplefile.txt',
        ]);

        // Check that generated syllabus module contains the uploaded samplefile.txt.
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id);
        $context = \context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_syllabus', 'content', false, '', false);
        $file = array_values($files)[0];
        $this->assertCount(1, $files);
        $this->assertEquals('samplefile.txt', $file->get_filename());
        $this->assertEquals('Hello!', $file->get_content());

        // Try to generate a syllabus with uploaded file without specifying the file.
        try {
            $syllabus = $generator->create_instance([
                'course' => $SITE->id,
                'uploaded' => true,
            ]);
            $this->assertTrue(false, 'coding_exception expected, defaultfilename is required');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\coding_exception::class, $e);
            $this->assertStringContainsString('defaultfilename option is required', $e->getMessage());
        }

        // Try to generate a syllabus with uploaded file pointing to non-existing file.
        try {
            $syllabus = $generator->create_instance([
                'course' => $SITE->id,
                'uploaded' => true,
                'defaultfilename' => 'mod/syllabus/tests/fixtures/doesnotexist.txt',
            ]);
            $this->assertTrue(false, 'coding_exception expected, defaultfilename must point to an existing file');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\coding_exception::class, $e);
            $this->assertStringContainsString('defaultfilename option must point to an existing file', $e->getMessage());
        }
    }
}
