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
 * Unit tests for mod_syllabus observer
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_syllabus;

/**
 * Unit tests for mod_syllabus observer
 *
 * @package    mod_syllabus
 * @copyright  2021 Marty Gilbert <martygilbert@gmail>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer_test extends \advanced_testcase {

    /**
     * Test that when a course category is deleted, it is removed from the
     * catstocheck config setting.
     * @covers \mod_syllabus_observer::course_category_deleted
     */
    public function test_course_category_deleted_removes_from_catstocheck() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create two categories.
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();

        // Set both categories in catstocheck.
        set_config('catstocheck', $cat1->id . ',' . $cat2->id, 'syllabus');

        // Delete the first category (this triggers the course_category_deleted event).
        $cat1->delete_full(false);

        // The first category should have been removed from catstocheck.
        $catstocheck = get_config('syllabus', 'catstocheck');
        $remaining = array_filter(explode(',', $catstocheck));

        $this->assertNotContains((string)$cat1->id, $remaining);
        $this->assertContains((string)$cat2->id, $remaining);
    }

    /**
     * Test that deleting the only category in catstocheck leaves catstocheck empty.
     * @covers \mod_syllabus_observer::course_category_deleted
     */
    public function test_course_category_deleted_single_category() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create one category and set it in catstocheck.
        $cat = $this->getDataGenerator()->create_category();
        set_config('catstocheck', (string)$cat->id, 'syllabus');

        // Delete the category.
        $cat->delete_full(false);

        // catstocheck should now be empty.
        $catstocheck = get_config('syllabus', 'catstocheck');
        $remaining = array_filter(explode(',', $catstocheck));

        $this->assertEmpty($remaining);
    }

    /**
     * Test that deleting a category not in catstocheck leaves catstocheck unchanged.
     * @covers \mod_syllabus_observer::course_category_deleted
     */
    public function test_course_category_deleted_not_in_catstocheck() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create two categories.
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();

        // Only put cat1 in catstocheck.
        set_config('catstocheck', (string)$cat1->id, 'syllabus');

        // Delete cat2 (not in catstocheck).
        $cat2->delete_full(false);

        // catstocheck should still contain cat1.
        $catstocheck = get_config('syllabus', 'catstocheck');
        $remaining = array_filter(explode(',', $catstocheck));

        $this->assertContains((string)$cat1->id, $remaining);
    }

    /**
     * Test that deleting a category when catstocheck is empty does nothing.
     * @covers \mod_syllabus_observer::course_category_deleted
     */
    public function test_course_category_deleted_empty_catstocheck() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a category but don't add it to catstocheck.
        $cat = $this->getDataGenerator()->create_category();
        set_config('catstocheck', '', 'syllabus');

        // Delete the category - should not cause any errors.
        $cat->delete_full(false);

        // catstocheck should still be empty.
        $catstocheck = get_config('syllabus', 'catstocheck');
        $this->assertEmpty($catstocheck);
    }
}
