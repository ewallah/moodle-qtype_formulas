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
 * Other tests for formulas unit tests.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @author     Renaat Debleu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use \context_course;

/**
 * Other tests.
 *
 * @package    qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @author    Renaat Debleu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class other_test extends \advanced_testcase {

    /**
     * Test privacy.
     */
    public function test_privacy() {
        $privacy = new privacy\provider();
        $this->assertEquals($privacy->get_reason(), 'privacy:metadata');
    }

    /**
     * Test backup - restore.
     */
    public function test_backup() {
        global $CFG, $USER;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');
        set_config('backup_general_users', 0, 'backup');
        set_config('backup_general_logs', 0, 'backup');
        $dg = $this->getDataGenerator();
        $courseid = $dg->create_course()->id;
        $userid = $dg->create_user()->id;
        $dg->enrol_user($userid, $courseid);

        $contexts = new \core_question\local\bank\question_edit_contexts(context_course::instance($courseid));
        $category = question_make_default_categories($contexts->all());
        $questiongenerator = $dg->get_plugin_generator('core_question');
        $questiongenerator->create_question('formulas', null, ['category' => $category->id]);

        $this->setAdminUser();
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $courseid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        unset($bc);
        $courseid = $dg->create_course()->id;
        $rc = new \restore_controller($backupid, $courseid, \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT, $USER->id, \backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        unset($rc);
    }

    /**
     * Test units.
     */
    public function test_units() {
        global $CFG;
        require_once("$CFG->dirroot/question/type/formulas/answer_unit.php");
        $aunit = new \answer_unit_conversion;
        $aunit->check_convertibility('', 'c');
    }
}
