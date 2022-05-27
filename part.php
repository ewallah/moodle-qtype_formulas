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
 * Moodle formulas part definition class.
 *
 * @package    qtype_formulas
 * @copyright  2010-2011 Hon Wai, Lau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');

/**
 * Class to represent a question subpart, loaded from the question_answers table in the database.
 *
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_part {
    /** @var integer the answer id. */
    public $id;

    /** @var part index */
    public $partindex;

    /** @var placeholder */
    public $placeholder;

    /** @var answermark */
    public $answermark;

    /** @var answertype */
    public $answertype;

    /** @var numbox */
    public $numbox;

    /** @var vars1 */
    public $vars1;

    /** @var vars1 */
    public $vars2;

    /** @var vars1 */
    public $answer;

    /** @var vars2 */
    public $correctness;

    /** @var unitpenalty */
    public $unitpenalty;

    /** @var postunit */
    public $postunit;

    /** @var ruleid */
    public $ruleid;

    /** @var otherrule */
    public $otherrule;

    /** @var subqtext */
    public $subqtext;

    /** @var subqtextformat */
    public $subqtextformat;

    /** @var feedback */
    public $feedback;

    /** @var feedbackformat */
    public $feedbackformat;

    /** @var partcorrectfb */
    public $partcorrectfb;

    /** @var partcorrectfbformat */
    public $partcorrectfbformat;

    /** @var parpartcorrectfb */
    public $partpartiallycorrectfb;

    /** @var partpartiallycorrectfbformat */
    public $partpartiallycorrectfbformat;

    /** @var partincorrectfb */
    public $partincorrectfb;

    /** @var partincorrectfbformat */
    public $partincorrectfbformat;


    /**
     * Constructor.
     */
    public function __construct() {
    }

    /**
     * Part has unit field
     *
     * @return boolean
     */
    public function part_has_unit() {
        return strlen($this->postunit) != 0;
    }

    /**
     * Part has separate unit field
     *
     * @return boolean
     */
    public function part_has_separate_unit_field() {
        return strlen($this->postunit) != 0 && $this->part_has_combined_unit_field() == false;
    }

    /**
     * Part has conbined unit field
     *
     * @return boolean
     */
    public function part_has_combined_unit_field() {
        return strlen($this->postunit) != 0 && $this->numbox == 1 && $this->answertype != 1000
            && (strpos($this->subqtext, "{_0}{_u}") !== false
            || (strpos($this->subqtext, "{_0}") === false && strpos($this->subqtext, "{_u}") === false));
    }

    /**
     * Are two responses the same insofar as this part is concerned.
     *
     * @param array $prevresponse the responses previously recorded for this question
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same for the given part.
     */
    public function part_is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->part_get_expected_data() as $name => $type) {
            if (!question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, $name)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Part get expected data
     *
     * @return array
     */
    public function part_get_expected_data() {
        $expected = [];
        $i = $this->partindex;
        if ($this->part_has_combined_unit_field()) {
            $expected["${i}_"] = PARAM_RAW;
        } else {
            foreach (range(0, $this->numbox - 1) as $j) {
                $expected["${i}_$j"] = PARAM_RAW;
            }
            if ($this->part_has_separate_unit_field()) {
                $expected["${i}_{$this->numbox}"] = PARAM_RAW;
            }
        }
        return $expected;
    }

    /**
     * Parse a string with placeholders and return the corresponding array of answer boxes.
     *
     * Each box is an object with 3 strings properties pattern, options and stype.
     * pattern is the placeholder as _0, _1, ..., _u
     * options is empty except for multichoice answers
     * where it is the name of a variable containing the list of choices
     * stype is empty for radio buttons or :MCE for drop down
     * select menu.
     *
     * @param string $text string to be parsed.
     * @return array.
     */
    public function part_answer_boxes($text) {
        $pattern = '\{(_[0-9u][0-9]*)(:[^{}:]+)?(:[^{}:]+)?\}';
        preg_match_all('/'.$pattern.'/', $text, $matches);
        $boxes = [];
        foreach ($matches[1] as $j => $match) {
            if (!array_key_exists($match, $boxes)) {  // If there is duplication, it will be skipped.
                $boxes[$match] = (object)['pattern' => $matches[0][$j], 'options' => $matches[2][$j], 'stype' => $matches[3][$j]];
            }
        }
        return $boxes;
    }

    /**
     * Part has multichoice coordinate
     *
     * @return boolean
     */
    public function part_has_multichoice_coordinate() {
        $boxes = $this->part_answer_boxes($this->subqtext);
        foreach ($boxes as $box) {
            if (strlen($box->options) != 0) { // Multichoice.
                return true;
            }
        }
        return false;
    }

    /**
     * Produce a plain text summary of a response for the part.
     *
     * @param array $response a response
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function part_summarise_response(array $response) {
        $summary = [];
        foreach ($this->part_get_expected_data() as $name => $type) {
            $summary[] = (array_key_exists($name, $response)) ? $response[$name] : '';
        }
        return implode(', ', $summary);
    }

    /**
     * Part is gradable response
     *
     * @param array $response a response
     */
    public function part_is_gradable_response(array $response) {
        // TODO and after that use in is_gradable_response.

    }

    /**
     * Part is complete response
     *
     * @param array $response a response
     */
    public function part_is_complete_response(array $response) {
        // TODO and after that use it in is_complete_response.

    }

    /**
     * Part is unanwered
     *
     * @param array $response a response
     * @return bool
     */
    public function part_is_unanswered(array $response) {
        $i = $this->partindex;
        if (array_key_exists("${i}_", $response) && $response["${i}_"] != '') {
            return false;
        }
        foreach (range(0, $this->numbox) as $j) {
            if (array_key_exists("${i}_$j", $response) && $response["${i}_$j"] != '') {
                return false;
            }
        }
        return true;
    }
}
