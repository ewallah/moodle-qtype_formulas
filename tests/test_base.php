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
 * Base class for formulas unit tests.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Base class for formulas unit tests.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_formulas_testcase extends advanced_testcase {
}


/**
 * Base class for formulas walkthrough tests.
 *
 * Provides some additional asserts.
 *
 * @package    qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_formulas_walkthrough_test_base extends qbehaviour_walkthrough_test_base {

    /** @var current output **/
    protected $currentoutput = null;

    /**
     * Render
     */
    protected function render() {
        $this->currentoutput = $this->quba->render_question($this->slot, $this->displayoptions);
    }

    /**
     * Get tag matcher
     * @param string $tag
     * @param string $attributes
     * @return array
     */
    protected function get_tag_matcher($tag, $attributes) {
        return ['tag' => $tag, 'attributes' => $attributes];
    }

    /**
     * Check output
     * @param string $name
     * @param string $value
     * @param bool $enabled
     */
    protected function check_output_contains_text_input($name, $value = null, $enabled = true) {
        $attributes = ['type' => 'text', 'name' => $this->quba->get_field_prefix($this->slot) . $name];
        if (!is_null($value)) {
            $attributes['value'] = $value;
        }
        if (!$enabled) {
            $attributes['readonly'] = 'readonly';
        }
        $matcher = $this->get_tag_matcher('input', $attributes);
        $this->assertTag($matcher, $this->currentoutput,
                'Looking for an input with attributes ' . html_writer::attributes($attributes) . ' in ' . $this->currentoutput);

        if ($enabled) {
            $matcher['attributes']['readonly'] = 'readonly';
            $this->assertNotTag($matcher, $this->currentoutput,
                    'input with attributes ' . html_writer::attributes($attributes) .
                    ' should not be read-only in ' . $this->currentoutput);
        }
    }

    /**
     * Check output contains part feedback
     * @param string $name
     */
    protected function check_output_contains_part_feedback($name = null) {
        $class = 'formulaspartfeedback';
        if ($name) {
            $class .= ' formulaspartfeedback-' . $name;
        }
        $this->assertTag(['tag' => 'div', 'attributes' => ['class' => $class]],
            $this->currentoutput, 'part feedback for ' . $name . ' not found in ' . $this->currentoutput);
    }

    /**
     * Check output not contains part feedback
     * @param string $name
     */
    protected function check_output_does_not_contain_part_feedback($name = null) {
        $class = 'formulaspartfeedback';
        if ($name) {
            $class .= ' formulaspartfeedback-' . $name;
        }
        $this->assertNotTag(['tag' => 'div', 'attributes' => ['class' => $class]], $this->currentoutput,
                'part feedback for ' . $name . ' should not be present in ' . $this->currentoutput);
    }

    /**
     * Check output not contains placeholders
     */
    protected function check_output_does_not_contain_stray_placeholders() {
        $this->assertDoesNotMatchRegularExpression('~\[\[|\]\]~', $this->currentoutput, 'Not all placehoders were replaced.');
    }

    /**
     * Check output contains language string
     * @param string $identifier
     * @param string $component
     * @param string $a
     */
    protected function check_output_contains_lang_string($identifier, $component = '', $a = null) {
        $string = get_string($identifier, $component, $a);
        $this->assertNotContains($string, $this->currentoutput,
                'Expected string ' . $string . ' not found in ' . $this->currentoutput);
    }

    /**
     * Check output not contains language string
     * @param string $identifier
     * @param string $component
     * @param string $a
     */
    protected function check_output_does_not_contain_lang_string($identifier, $component = '', $a = null) {
        $string = get_string($identifier, $component, $a);
        $this->assertContains($string, $this->currentoutput,
                'The string ' . $string . ' should not be present in ' . $this->currentoutput);
    }
}
