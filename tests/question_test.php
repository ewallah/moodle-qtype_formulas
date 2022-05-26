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
 * Unit tests for the OU multiple response question class.
 *
 * @package   qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');

/**
 * Unit tests for (some of) question/type/formulas/question.php.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_test extends basic_testcase {

    /**
     * Get test formula question object
     * @param string $which
     * @return qtype_formulas_question the requested question object.
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    /**
     * Test get expected data1
     */
    public function test_get_expected_data_test0() {
        $q = $this->get_test_formulas_question('test0');
        $this->assertEquals(['0_0' => PARAM_RAW], $q->get_expected_data());
    }

    /**
     * Test get expected data2
     */
    public function test_get_expected_data_test1() {
        $q = $this->get_test_formulas_question('test1');
        $this->assertEquals(['0_0' => PARAM_RAW, '1_0' => PARAM_RAW, '2_0' => PARAM_RAW], $q->get_expected_data());
    }

    /**
     * Test get expected data3
     */
    public function test_get_expected_data_test2() {
        $q = $this->get_test_formulas_question('test4');
        $this->assertEquals(['0_' => PARAM_RAW, '1_0' => PARAM_RAW, '1_1' => PARAM_RAW, '2_0' => PARAM_RAW, '3_0' => PARAM_RAW],
            $q->get_expected_data());
    }

    /**
     * Test is complete response0
     */
    public function test_is_complete_response_test0() {
        $q = $this->get_test_formulas_question('test0');

        $this->assertFalse($q->is_complete_response([]));
        $this->assertTrue($q->is_complete_response(['0_0' => '0']));
        $this->assertTrue($q->is_complete_response(['0_0' => 0]));
        $this->assertTrue($q->is_complete_response(['0_0' => 'test']));
    }

    /**
     * Test is complete response1
     */
    public function test_is_complete_response_test1() {
        $q = $this->get_test_formulas_question('test1');

        $this->assertFalse($q->is_complete_response([]));
        $this->assertFalse($q->is_complete_response(['0_0' => '1']));
        $this->assertFalse($q->is_complete_response(['0_0' => '1', '1_0' => '1']));
        $this->assertTrue($q->is_complete_response(['0_0' => '1', '1_0' => '1', '2_0' => '1']));
    }

    /**
     * Test is complete response2
     */
    public function test_is_complete_response_test2() {
        $q = $this->get_test_formulas_question('test2');

        $this->assertFalse($q->is_complete_response([]));
        $this->assertFalse($q->is_complete_response(['0_0' => '1']));
        $this->assertFalse($q->is_complete_response(['0_0' => '1', '1_0' => '1']));
        $this->assertFalse($q->is_complete_response(['0_0' => '1', '1_0' => '1', '2_0' => '1']));
    }

    /**
     * Test get question summary0
     */
    public function test_get_question_summary_test0() {
        $q = $this->get_test_formulas_question('test0');
        $q->start_attempt(new question_attempt_step(), 1);
        $this->assertEquals("Minimal question : For a minimal question, you must define a part with (1) mark, (2) answer," .
                " (3) grading criteria, and optionally (4) question text.\n", $q->get_question_summary());
    }

    /**
     * Test get question summary1
     */
    public function test_get_question_summary_test1() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);
        $this->assertEquals("Multiple parts : --This is first part.--This is second part.--This is third part.\n",
                $q->get_question_summary());
    }

    /**
     * Test get question summary2
     */
    public function test_get_question_summary_test2() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $globalvars = $q->get_global_variables();
        $s = $globalvars->all['s']->value;
        $dt = $globalvars->all['dt']->value;

        $this->assertEquals("This question shows different display methods of the answer and unit box.\n"
                            . "If a car travel $s m in $dt s, what is the speed of the car? {_0}{_u}\n"
                            . "If a car travel $s m in $dt s, what is the speed of the car? {_0} {_u}\n"
                            . "If a car travel $s m in $dt s, what is the speed of the car? {_0} {_u}\n"
                            . "If a car travel $s m in $dt s, what is the speed of the car? speed = {_0}{_u}\n",
                                    $q->get_question_summary());
    }

    /**
     * Test get correct response0
     */
    public function test_get_correct_response_test0() {
        $q = $this->get_test_formulas_question('test0');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(['0_0' => '5'], $q->get_correct_response());
    }

    /**
     * Test get correct response1
     */
    public function test_get_correct_response_test1() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(['0_0' => '5', '1_0' => '6', '2_0' => '7'], $q->get_correct_response());
        $this->assertEquals('5', $q->correct_response_formatted($q->parts[0]));
        $this->assertEquals('6', $q->correct_response_formatted($q->parts[1]));
        $this->assertEquals('7', $q->correct_response_formatted($q->parts[2]));
    }

    /**
     * Test get correct response2
     */
    public function test_get_correct_response_test2() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $globalvars = $q->get_global_variables();
        $v = $globalvars->all['v']->value;

        $this->assertEquals(['0_' => "{$v}m/s", '1_0' => "$v", '1_1' => 'm/s', '2_0' => "$v", '3_0' => "$v"],
                $q->get_correct_response());
        $this->assertEquals("{$v} m/s", $q->correct_response_formatted($q->parts[0]));
        $this->assertEquals("{$v}, m/s", $q->correct_response_formatted($q->parts[1]));
        $this->assertEquals("$v", $q->correct_response_formatted($q->parts[2]));
        $this->assertEquals("$v", $q->correct_response_formatted($q->parts[3]));
    }

    /**
     * Test get correct response3
     */
    public function test_get_correct_response_test3() {
        $q = $this->get_test_formulas_question('test5');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(['0_0' => '1'], $q->get_correct_response());
        $this->assertEquals('Cat', $q->correct_response_formatted($q->parts[0]));
    }

    /**
     * Test is same response for part
     */
    public function test_get_is_same_response_for_part_test2() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertTrue($q->is_same_response_for_part('1', ['1_0' => 'x'], ['1_0' => 'x']));
        $this->assertTrue($q->is_same_response_for_part('1', ['1_0' => 'x', '2_0' => 'x'], ['1_0' => 'x', '2_0' => 'y']));
        $this->assertFalse($q->is_same_response_for_part('1', ['1_0' => 'x'], ['1_0' => 'y']));
    }

    /**
     * Test grade parts1
     */
    public function test_grade_parts_that_can_be_graded_test1() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '8'];
        $lastgradedresponses = ['0' => ['0_0' => '5', '1_0' => '', '2_0' => ''], '1' => ['0_0' => '6', '1_0' => '6', '2_0' => '']];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);
        $expected = ['2' => new qbehaviour_adaptivemultipart_part_result('2', 0, 0.3)];
        $this->assertEquals($expected, $partscores);
    }

    /**
     * Test grade parts2
     */
    public function test_grade_parts_that_can_be_graded_test2() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = ['0' => ['0_0' => '5', '1_0' => '', '2_0' => ''], '1' => ['0_0' => '6', '1_0' => '6', '2_0' => '']];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);
        $expected = ['2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3)];
        $this->assertEquals($expected, $partscores);
    }

    /**
     * Test grade parts3
     */
    public function test_grade_parts_that_can_be_graded_test3() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '4', '2_0' => ''],
            '1' => ['0_0' => '6', '1_0' => '6', '2_0' => ''],
            '2' => ['0_0' => '6', '1_0' => '6', '2_0' => '7']];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);
        $expected = [];
        $this->assertEquals($expected, $partscores);
    }

    /**
     * Test grade parts4
     */
    public function test_grade_parts_that_can_be_graded_test4() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = ['0' => ['0_0' => '5', '1_0' => '', '2_0' => '']];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);
        $expected = [
            '1' => new qbehaviour_adaptivemultipart_part_result('1', 1, 0.3),
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3)];
        $this->assertEquals($expected, $partscores);
    }

    /**
     * Test grade parts5
     */
    public function test_grade_parts_that_can_be_graded_test5() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '', '2_0' => ''];
        $lastgradedresponses = [];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);
        $expected = ['0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3)];
        $this->assertEquals($expected, $partscores);
    }

    /**
     * Test part and weights0
     */
    public function test_get_parts_and_weights_test0() {
        $q = $this->get_test_formulas_question('test0');
        $this->assertEquals(['0' => 1], $q->get_parts_and_weights());
    }

    /**
     * Test part and weights1
     */
    public function test_get_parts_and_weights_test1() {
        $q = $this->get_test_formulas_question('test1');
        $this->assertEquals(['0' => 1 / 3, '1' => 1 / 3, '2' => 1 / 3], $q->get_parts_and_weights());
    }

    /**
     * Test part and weights2
     */
    public function test_get_parts_and_weights_test2() {
        $q = $this->get_test_formulas_question('test4');

        $this->assertEquals(['0' => .25, '1' => .25, '2' => .25, '3' => .25], $q->get_parts_and_weights());
    }

    /**
     * Test final grade0
     */
    public function test_compute_final_grade_test0() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = [0 => ['0_0' => '5', '1_0' => '7', '2_0' => '6'],
                      1 => ['0_0' => '5', '1_0' => '7', '2_0' => '7'],
                      2 => ['0_0' => '5', '1_0' => '6', '2_0' => '7']];
        $finalgrade = $q->compute_final_grade($responses, 1);
        $this->assertEquals((2 + 2 * (1 - 2 * 0.3) + 2 * (1 - 0.3)) / 6, $finalgrade);

    }

    /**
     * Test final grade1
     */
    public function test_compute_final_grade_test1() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = [0 => ['0_0' => '5', '1_0' => '7', '2_0' => '6'],
                      1 => ['0_0' => '5', '1_0' => '8', '2_0' => '6'],
                      2 => ['0_0' => '5', '1_0' => '6', '2_0' => '6']];
        $finalgrade = $q->compute_final_grade($responses, 1);
        $this->assertEquals((2 + 2 * (1 - 2 * 0.3)) / 6, $finalgrade);
    }

    /**
     * Test part has multichoice0
     */
    public function test_part_has_multichoice_coordinate0() {
        $p = new qtype_formulas_part;
        $p->subqtext = '{_0} - {_1:choices} - {_2}';
        $this->assertTrue($p->part_has_multichoice_coordinate());
    }

    /**
     * Test pat has multichoice1
     */
    public function test_part_has_multichoice_coordinate1() {
        $p = new qtype_formulas_part;
        $p->subqtext = '{_0} - {_1} - {_2}';
        $this->assertFalse($p->part_has_multichoice_coordinate());
    }

    /**
     * Test summarise response0
     */
    public function test_summarise_response_test0() {
        $q = $this->get_test_formulas_question('test1');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $summary0 = $q->parts[0]->part_summarise_response($response);
        $this->assertEquals('5', $summary0);
        $summary1 = $q->parts[1]->part_summarise_response($response);
        $this->assertEquals('6', $summary1);
        $summary2 = $q->parts[2]->part_summarise_response($response);
        $this->assertEquals('7', $summary2);
        $summary = $q->summarise_response($response);
        $this->assertEquals('5, 6, 7', $summary);
    }

    /**
     * Test summarise response1
     */
    public function test_summarise_response_test1() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_' => '30m/s', '1_0' => '20', '1_1' => 'm/s', '2_0' => '40', '3_0' => '50'];
        $summary0 = $q->parts[0]->part_summarise_response($response);
        $this->assertEquals('30m/s', $summary0);
        $summary1 = $q->parts[1]->part_summarise_response($response);
        $this->assertEquals('20, m/s', $summary1);
        $summary2 = $q->parts[2]->part_summarise_response($response);
        $this->assertEquals('40', $summary2);
        $summary3 = $q->parts[3]->part_summarise_response($response);
        $this->assertEquals('50', $summary3);
        $summary = $q->summarise_response($response);
        $this->assertEquals('30m/s, 20, m/s, 40, 50', $summary);
    }

    /**
     * Test is complete3
     */
    public function test_is_complete_response_test3() {
        $q = $this->get_test_formulas_question('test3');

        $this->assertFalse($q->is_complete_response([]));
        $this->assertTrue($q->is_complete_response(['0_0' => '0']));
        $this->assertTrue($q->is_complete_response(['0_0' => 0]));
        $this->assertTrue($q->is_complete_response(['0_0' => 'test']));
    }

    /**
     * Test is gradable3
     */
    public function test_is_gradable_response_test3() {
        $q = $this->get_test_formulas_question('test3');

        $this->assertFalse($q->is_gradable_response([]));
        $this->assertTrue($q->is_gradable_response(['0_0' => '0']));
        $this->assertTrue($q->is_gradable_response(['0_0' => 0]));
        $this->assertTrue($q->is_gradable_response(['0_0' => '0.0']));
        $this->assertTrue($q->is_gradable_response(['0_0' => '5']));
        $this->assertTrue($q->is_gradable_response(['0_0' => 5]));
    }
}
