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
 * Moodle formulas question type class.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @author     Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once("$CFG->dirroot/question/type/formulas/variables.php");
require_once("$CFG->dirroot/question/type/formulas/answer_unit.php");
require_once("$CFG->dirroot/question/type/formulas/conversion_rules.php");
require_once($CFG->dirroot . '/question/type/formulas/question.php');

/**
 * The formulas question class
 *
 * TODO give an overview of how the class works here.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @author     Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas extends question_type {

    /** @var question formalas variables  **/
    private $qv;

    /**
     * Construct class
     */
    public function __construct() {
        $this->qv = new qtype_formulas_variables();
    }

    /**
     * Column names of qtype_formulas_answers table (apart from id and questionid)
     *
     * WARNING qtype_formulas_answers is NOT an extension of answers table
     * so we can't use extra_answer_fields here.
     * subqtext, subqtextformat, feedback and feedbackformat and all part's combined
     * feedback fields are not included as their handling is special
     * @return array.
     */
    public function part_tags() {
        return ['placeholder', 'answermark', 'answertype', 'numbox', 'vars1', 'answer', 'vars2',
                'correctness', 'unitpenalty', 'postunit', 'ruleid', 'otherrule'];
    }

    /**
     * Extra question column name
     *
     * return string
     */
    public function questionid_column_name() {
        return 'questionid';
    }

    /**
     * Extra fields in the table
     *
     * @return array
     */
    public function extra_question_fields() {
        return ['qtype_formulas_options', 'varsrandom', 'varsglobal', 'answernumbering'];
    }

    /**
     * Move all the files belonging to this question from one context to another.
     *
     * @param int $questionid the question being moved.
     * @param int $oldcontextid the context it is moving from.
     * @param int $newcontextid the context it is moving to.
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_formulas', 'answersubqtext', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_formulas', 'answerfeedback', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_formulas', 'partcorrectfb', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_formulas', 'partpartiallycorrectfb', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_formulas', 'partincorrectfb', $questionid);
        $this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Delete all the files belonging to this question.
     *
     * @param int $questionid the question being deleted.
     * @param int $contextid the context the question is in.
     */
    protected function delete_files($questionid, $contextid) {
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
        $fs->delete_area_files($contextid, 'question', 'correctfeedback', $questionid);
        $fs->delete_area_files($contextid, 'question', 'partiallycorrectfeedback', $questionid);
        $fs->delete_area_files($contextid, 'question', 'incorrectfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'answersubqtext', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'answerfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'partcorrectfb', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'partpartiallycorrectfb', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'partincorrectfb', $questionid);
    }

    /**
     * Loads the question type specific options for the question.
     *
     * @param object $question The question object for the question.
     * @return bool indicates success or failure.
     */
    public function get_question_options($question) {
        global $DB;

        $question->options = $DB->get_record('qtype_formulas_options', ['questionid' => $question->id]);

        if ($question->options === false) {
            // If this has happened, then we have a problem.
            // For the user to be able to edit or delete this question, we need options.
            debugging("Formulas question ID {$question->id} was missing an options record. Using default.", DEBUG_DEVELOPER);

            $question->options = $this->create_default_options($question);
        }

        parent::get_question_options($question);
        $question->options->answers =
            $DB->get_records('qtype_formulas_answers', ['questionid' => $question->id], 'partindex ASC');
        $question->options->numpart = count($question->options->answers);
        $question->options->answers = array_values($question->options->answers);
        return true;
    }

    /**
     * Create a default options object for the provided question.
     *
     * @param object $question The question we are working with.
     * @return object The options object.
     */
    protected function create_default_options($question) {
        // Create a default question options record.
        $options = new stdClass();
        $options->questionid = $question->id;
        $options->varsrandom = '';
        $options->varsglobal = '';

        // Get the default strings and just set the format.
        $options->correctfeedback = get_string('correctfeedbackdefault', 'question');
        $options->correctfeedbackformat = FORMAT_HTML;
        $options->partiallycorrectfeedback = get_string('partiallycorrectfeedbackdefault', 'question');;
        $options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $options->incorrectfeedback = get_string('incorrectfeedbackdefault', 'question');
        $options->incorrectfeedbackformat = FORMAT_HTML;

        $options->answernumbering = 'none';
        $options->shownumcorrect = 0;

        return $options;
    }

    /**
     * Saves question-type specific options
     *
     * @param object $question  This holds the information from the editing form, it is not a standard question object.
     * @return object $result->error or $result->noticeyesno or $result->notice
     */
    public function save_question_options($question) {
        global $DB;

        $context = $question->context;
        $oldanswers = $DB->get_records('qtype_formulas_answers', ['questionid' => $question->id], 'partindex ASC');
        try {
            $filtered = $this->validate($question); // Data from the web input interface should be validated.
            if (count($filtered->errors) > 0) {
                // There may be errors from import or restore.
                throw new Exception('Format error! Probably imported/restored formulas questions have been damaged.');
            }
            $answersorder = $this->reorder_answers($question->questiontext, $filtered->answers);
            // Reorder the answers, so that answer's order is the same as the placeholder's order in questiontext.
            $newanswers = [];
            foreach ($answersorder as $newloc) {
                $newanswers[] = $filtered->answers[$newloc];
            }
            foreach ($newanswers as $i => $ans) {
                $ans->partindex = $i;
                $ans->questionid = $question->id;
                // Save all editors content (arrays).
                $subqtextarr = $ans->subqtext;
                $ans->subqtext = $subqtextarr['text'];
                $ans->subqtextformat = $subqtextarr['format'];
                $feedbackarr = $ans->feedback;
                $ans->feedback = $feedbackarr['text'];
                $ans->feedbackformat = $feedbackarr['format'];
                $correctfbarr = $ans->partcorrectfb;
                $ans->partcorrectfb = $correctfbarr['text'];
                $ans->partcorrectfbformat = $correctfbarr['format'];
                $partiallycorrectfbarr = $ans->partpartiallycorrectfb;
                $ans->partpartiallycorrectfb = $partiallycorrectfbarr['text'];
                $ans->partpartiallycorrectfbformat = $partiallycorrectfbarr['format'];
                $incorrectfbarr = $ans->partincorrectfb;
                $ans->partincorrectfb = $incorrectfbarr['text'];
                $ans->partincorrectfbformat = $incorrectfbarr['format'];
                // Update an existing answer if possible.
                $answer = array_shift($oldanswers);
                if (!$answer) {
                    $answer = new stdClass();
                    $answer->questionid = $question->id;
                    $answer->answermark = 1;
                    $answer->numbox = 1;
                    $answer->answer = '';
                    $answer->correctness = '';
                    $answer->ruleid = 1;
                    $answer->trialmarkseq = '';
                    $answer->subqtextformat = 0;
                    $answer->feedbackformat = 0;
                    $answer->partcorrectfb = '';
                    $answer->partcorrectfbformat = 0;
                    $answer->partpartiallycorrectfb = '';
                    $answer->partpartiallycorrectfbformat = 0;
                    $answer->partincorrectfb = '';
                    $answer->partincorrectfbformat = 0;

                    $ans->id = $DB->insert_record('qtype_formulas_answers', $answer);
                } else {
                    $ans->id = $answer->id;
                }
                $ans->subqtext = $this->import_or_save_files($subqtextarr, $context, 'qtype_formulas', 'answersubqtext', $ans->id);
                $ans->feedback = $this->import_or_save_files($feedbackarr, $context, 'qtype_formulas', 'answerfeedback', $ans->id);
                $ans->partcorrectfb =
                    $this->import_or_save_files($correctfbarr, $context, 'qtype_formulas', 'partcorrectfb', $ans->id);
                $ans->partpartiallycorrectfb = $this->import_or_save_files(
                    $partiallycorrectfbarr, $context, 'qtype_formulas', 'partpartiallycorrectfb', $ans->id);
                $ans->partincorrectfb =
                    $this->import_or_save_files($incorrectfbarr, $context, 'qtype_formulas', 'partincorrectfb', $ans->id);
                $DB->update_record('qtype_formulas_answers', $ans);
            }

            // Delete any left over old answer records.
            $fs = get_file_storage();
            foreach ($oldanswers as $oldanswer) {
                $fs->delete_area_files($context->id, 'qtype_formulas', 'answersubqtext', $oldanswer->id);
                $fs->delete_area_files($context->id, 'qtype_formulas', 'answerfeedback', $oldanswer->id);
                $fs->delete_area_files($context->id, 'qtype_formulas', 'partcorrectfb', $oldanswer->id);
                $fs->delete_area_files($context->id, 'qtype_formulas', 'partpartiallycorrectfb', $oldanswer->id);
                $fs->delete_area_files($context->id, 'qtype_formulas', 'partincorrectfb', $oldanswer->id);
                $DB->delete_records('qtype_formulas_answers', ['id' => $oldanswer->id]);
            }
        } catch (Exception $e) {
            return (object)['error' => $e->getMessage()];
        }
        // Save the question options.
        $options = $DB->get_record('qtype_formulas_options', ['questionid' => $question->id]);
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->answernumbering = 'none';
            $options->id = $DB->insert_record('qtype_formulas_options', $options);
        }
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $extra) {
            if (property_exists($question, $extra)) {
                $options->$extra = $question->$extra;
            }
        }

        $options = $this->save_combined_feedback_helper($options, $question, $context, true);

        $DB->update_record('qtype_formulas_options', $options);

        $this->save_hints($question, true);
        return (object)[];
    }


    /**
     * Override the parent save_question in order to change the defaultmark.
     *
     * @param object $question
     * @param object $form
     * @return object
     */
    public function save_question($question, $form) {
        $form->defaultmark = 0;
        foreach ($form->answermark as $key => $data) {
            if (trim($form->answermark[$key]) == '' || trim($form->answer[$key]) == '') {
                continue;
            }
            // Question's default mark is the total of all non empty parts's marks.
            $form->defaultmark += $form->answermark[$key];
        }
        $question = parent::save_question($question, $form);
        return $question;
    }

    /**
     * Override the make hint
     *
     * @param object $hint the DB row from the question hints table.
     * @return question_hint
     */
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    /**
     * Deletes the question-type specific data when a question is deleted.
     * @param int $questionid the question being deleted.
     * @param int $contextid the context this quesiotn belongs to.
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        // The qtype_formulas records are deleted in parent as extra_question_fields records.
        // But we need to take care of qtype_formulas_answers as they are not real answers.
        $DB->delete_records('qtype_formulas_answers', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    /**
     * Split the main question text according to the placeholders.
     *
     * @param string $questiontext The main question text containing all placeholders.
     * @param array $answers array of answers with placeholder (can be empty string)
     * @return  array of text fragments with count($answers) + 1 elements.
     */
    public function split_questiontext($questiontext, $answers) {
        // TODO Simplify this now that answers are in right order in data structure.
        // Store the (scaled) location of the *named* placeholder in the main text.
        $locations = [];
        foreach ($answers as $idx => $answer) {
            if (strlen($answer->placeholder) != 0) {
                // Store the pair (location, idx).
                $locations[] = 1000 * strpos($questiontext, '{'.$answer->placeholder.'}') + $idx;
            }
        }
        // Performs stable sort of location and answerorder pairs.
        sort($locations);

        $fragments = [];
        foreach ($locations as $i => $location) {
            $answerorder = $location % 1000;
            list($fragments[$i], $questiontext) = explode('{'.$answers[$answerorder]->placeholder.'}', $questiontext);
        }
        foreach ($answers as $answer) {
            if (strlen($answer->placeholder) == 0) {
                // Add the parts with empty placeholder at the end.
                $fragments[] = $questiontext;
                $questiontext = '';
            }
        }
        // Add the post-question text, if any.
        $fragments[] = $questiontext;
        return $fragments;
    }

    /**
     * Initialise the question_definition fields.
     * @param question_definition $question the question_definition we are creating.
     * @param object $questiondata the question data loaded from the database.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->parts = [];
        if (!empty($questiondata->options->answers)) {
            foreach ($questiondata->options->answers as $ans) {
                $part = new qtype_formulas_part();
                foreach ($ans as $key => $value) {
                    $part->{$key} = $value;
                }
                $question->parts[$part->partindex] = $part;
            }
        }
        $question->varsrandom = $questiondata->options->varsrandom;
        $question->varsglobal = $questiondata->options->varsglobal;
        $question->answernumbering = $questiondata->options->answernumbering;
        $question->qv = new qtype_formulas_variables();
        $question->numpart = $questiondata->options->numpart;
        if ($question->numpart != 0) {
            $question->fractions = array_fill(0, $question->numpart, 0);
            $question->anscorrs = array_fill(0, $question->numpart, 0);
            $question->unitcorrs = array_fill(0, $question->numpart, 0);
        }
        $question->textfragments = $this->split_questiontext($question->questiontext, $question->parts);
        $this->initialise_combined_feedback($question, $questiondata, true);
    }

    /**
     * This method should return all the possible types of response that are
     * recognised for this question.
     *
     * @param object $questiondata the question definition data.
     * @return array keys are part partindex, values are arrays of possible responses to that question part.
     */
    public function get_possible_responses($questiondata) {
        $resp = [];

        $q = $this->make_question($questiondata);

        foreach ($q->parts as $part) {
            if ($part->postunit == '') {
                $resp[$part->partindex] = [
                    'wrong' => new question_possible_response('Wrong', 0),
                    'right' => new question_possible_response('Right', 1),
                    null => question_possible_response::no_response()];
            } else {
                 $resp[$part->partindex] = [
                    'wrong' => new question_possible_response('Wrong', 0),
                    'right' => new question_possible_response('Right', 1),
                    'wrongvalue' => new question_possible_response('Wrong value right unit', 0),
                    'wrongunit' => new question_possible_response('Right value wrong unit', 1 - $part->unitpenalty),
                    null => question_possible_response::no_response()];
            }
        }

        return $resp;
    }
    /**
     * Imports the question from Moodle XML format.
     *
     * @param object $xml structure containing the XML data
     * @param object $fromform question object to fill: ignored by this function (assumed to be null)
     * @param qformat_xml $format format class exporting the question
     * @param array $extra extra information (not required for importing this question in this format)
     */
    public function import_from_xml($xml, $fromform, qformat_xml $format, $extra = null) {
        // Return if data type is not our own one.
        if (!isset($xml['@']['type']) || $xml['@']['type'] != $this->name()) {
            return false;
        }

        // Import the common question headers and set the corresponding field,
        // Unfortunately we can't use the parent method because it will try to import answers,
        // and fails as formulas "answers" are not real answers but formulas question parts !!
        $fromform = $format->import_headers($xml);
        $fromform->qtype = $this->name();
        $format->import_combined_feedback($fromform, $xml, true);
        $format->import_hints($fromform, $xml, true);

        $fromform->varsrandom = $format->getpath($xml, ['#', 'varsrandom', 0, '#', 'text', 0, '#'], '', true);
        $fromform->varsglobal = $format->getpath($xml, ['#', 'varsglobal', 0, '#', 'text', 0, '#'], '', true);
        $fromform->answernumbering = $format->getpath($xml, ['#', 'answernumbering', 0, '#', 'text', 0, '#'], 'none', true);

        // Loop over each answer block found in the XML.
        $tags = $this->part_tags();
        $anscount = 0;
        foreach ($xml['#']['answers'] as $answer) {
            $partindex = $format->getpath($answer, ['#', 'partindex', 0 , '#' , 'text' , 0 , '#'], false);
            if ($partindex) {
                $fromform->partindex[$anscount] = $partindex;
            }
            foreach ($tags as $tag) {
                $fromform->{$tag}[$anscount] =
                    $format->getpath($answer, ['#', $tag, 0 , '#' , 'text' , 0 , '#'], '0', false, 'error');
            }

            $subqxml = $format->getpath($answer, ['#', 'subqtext', 0], []);
            $fromform->subqtext[$anscount] = $format->import_text_with_files($subqxml,
                        [], '', $format->get_format($fromform->questiontextformat));

            $feedbackxml = $format->getpath($answer, ['#', 'feedback', 0], []);
            $fromform->feedback[$anscount] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($fromform->questiontextformat));

            $feedbackxml = $format->getpath($answer, ['#', 'correctfeedback', 0], []);
            $fromform->partcorrectfb[$anscount] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($fromform->questiontextformat));
            $feedbackxml = $format->getpath($answer, ['#', 'partiallycorrectfeedback', 0], []);
            $fromform->partpartiallycorrectfb[$anscount] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($fromform->questiontextformat));
            $feedbackxml = $format->getpath($answer, ['#', 'incorrectfeedback', 0], []);
            $fromform->partincorrectfb[$anscount] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($fromform->questiontextformat));
            ++$anscount;
        }
        $fromform->defaultmark = array_sum($fromform->answermark); // Make the defaultmark consistent if not specified.

        return $fromform;
    }


    /**
     * Exports the question to Moodle XML format.
     *
     * @param object $question question to be exported into XML format
     * @param qformat_xml $format format class exporting the question
     * @param array $extra extra information (not required for exporting this question in this format)
     * @return string text containing the question data in XML format
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $expout = '';
        $fs = get_file_storage();
        $contextid = $question->contextid;
        $expout .= $format->write_combined_feedback($question->options,
                                                    $question->id,
                                                    $question->contextid);
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $extra) {
            $expout .= "<$extra>".$format->writetext($question->options->$extra)."</$extra>\n";
        }

        $tags = $this->part_tags();
        foreach ($question->options->answers as $answer) {
            $expout .= "<answers>\n";
            $expout .= " <partindex>\n  ".$format->writetext($answer->partindex)." </partindex>\n";
            foreach ($tags as $tag) {
                $expout .= " <$tag>\n  ".$format->writetext($answer->$tag)." </$tag>\n";
            }

            $subqfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'answersubqtext', $answer->id);
            $subqtextformat = $format->get_format($answer->subqtextformat);
            $expout .= " <subqtext format=\"$subqtextformat\">\n";
            $expout .= $format->writetext($answer->subqtext);
            $expout .= $format->write_files($subqfiles);
            $expout .= " </subqtext>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'answerfeedback', $answer->id);
            $feedbackformat = $format->get_format($answer->feedbackformat);
            $expout .= " <feedback format=\"$feedbackformat\">\n";
            $expout .= $format->writetext($answer->feedback);
            $expout .= $format->write_files($fbfiles);
            $expout .= " </feedback>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'partcorrectfb', $answer->id);
            $feedbackformat = $format->get_format($answer->partcorrectfbformat);
            $expout .= " <correctfeedback format=\"$feedbackformat\">\n";
            $expout .= $format->writetext($answer->partcorrectfb);
            $expout .= $format->write_files($fbfiles);
            $expout .= " </correctfeedback>\n";
            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'partpartiallycorrectfb', $answer->id);
            $feedbackformat = $format->get_format($answer->partpartiallycorrectfbformat);
            $expout .= " <partiallycorrectfeedback format=\"$feedbackformat\">\n";
            $expout .= $format->writetext($answer->partpartiallycorrectfb);
            $expout .= $format->write_files($fbfiles);
            $expout .= " </partiallycorrectfeedback>\n";
            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'partincorrectfb', $answer->id);
            $feedbackformat = $format->get_format($answer->partincorrectfbformat);
            $expout .= " <incorrectfeedback format=\"$feedbackformat\">\n";
            $expout .= $format->writetext($answer->partincorrectfb);
            $expout .= $format->write_files($fbfiles);
            $expout .= " </incorrectfeedback>\n";

            $expout .= "</answers>\n";
        }
        return $expout;
    }

    /**
     * Check if placeholders in answers are correct and compatible with questiontext.
     *
     * @param string $questiontext string text of the main question
     * @param array $answers array of objects only the placeholder is used
     * @return array of errors
     */
    public function check_placeholder($questiontext, $answers) {
        $placeholderformat = '#\w+';
        $placeholders = [];
        foreach ($answers as $idx => $answer) {
            if ( strlen($answer->placeholder) == 0 ) {
                continue; // No error if answer's placeholder is empty.
            }
            $errstr = [];
            if ( strlen($answer->placeholder) >= 40 ) {
                $errstr[] = get_string('error_placeholder_too_long', 'qtype_formulas');
            }
            if ( !preg_match('/^'.$placeholderformat.'$/', $answer->placeholder) ) {
                $errstr[] = get_string('error_placeholder_format', 'qtype_formulas');
            }
            if ( array_key_exists($answer->placeholder, $placeholders) ) {
                $errstr[] = get_string('error_placeholder_sub_duplicate', 'qtype_formulas');
            }
            $placeholders[$answer->placeholder] = true;
            $count = substr_count($questiontext, '{'.$answer->placeholder.'}');
            if ($count < 1) {
                $errstr[] = get_string('error_placeholder_missing', 'qtype_formulas');
            }
            if ($count > 1) {
                $errstr[] = get_string('error_placeholder_main_duplicate', 'qtype_formulas');
            }
            if (!empty($errstr)) {
                $errors["placeholder[$idx]"] = implode(' ', $errstr);
            }
        }
        return isset($errors) ? $errors : [];
    }

    /**
     * Check that all required fields have been filled and return the filtered classes of the answers.
     *
     * @param object $form all the input form data
     * @return object with a field 'answers' containing valid answers. Otherwise, the 'errors' field will be set
     */
    public function check_and_filter_answers($form) {
        $tags = $this->part_tags();
        $res = (object)['answers' => []];
        foreach ($form->answermark as $i => $unused) {
            if ((strlen(trim($form->answermark[$i])) == 0 || strlen(trim($form->answer[$i])) == 0)
                    && (strlen(trim($form->subqtext[$i]['text'])) != 0
                    || strlen(trim($form->feedback[$i]['text'])) != 0
                    || strlen(trim($form->vars1[$i])) != 0
                    )
                ) {
                $res->errors["answer[$i]"] = get_string('error_answer_missing', 'qtype_formulas');
                $skip = true;
            }
            if (strlen(trim($form->answermark[$i])) == 0 || strlen(trim($form->answer[$i])) == 0) {
                // If no mark or no answer, then skip this answer.
                continue;
            }
            if (floatval($form->answermark[$i]) <= 0) {
                $res->errors["answermark[$i]"] = get_string('error_mark', 'qtype_formulas');
            }
            $skip = false;
            if (strlen(trim($form->correctness[$i])) == 0) {
                $res->errors["correctness[$i]"] = get_string('error_criterion', 'qtype_formulas');
                $skip = true;
            }
            if ($skip) {
                // If no answer or correctness conditions, it cannot check other parts, so skip.
                continue;
            }
            $res->answers[$i] = new stdClass();
            $res->answers[$i]->questionid = $form->id;
            foreach ($tags as $tag) {
                $res->answers[$i]->{$tag} = trim($form->{$tag}[$i]);
            }

            $subqtext = [];
            $subqtext['text'] = $form->subqtext[$i]['text'];
            $subqtext['format'] = $form->subqtext[$i]['format'];
            if (isset($form->subqtext[$i]['itemid'])) {
                $subqtext['itemid'] = $form->subqtext[$i]['itemid'];
            }
            $res->answers[$i]->subqtext = $subqtext;

            $fb = [];
            $fb['text'] = $form->feedback[$i]['text'];
            $fb['format'] = $form->feedback[$i]['format'];
            if (isset($form->feedback[$i]['itemid'])) {
                $fb['itemid'] = $form->feedback[$i]['itemid'];
            }
            $res->answers[$i]->feedback = $fb;

            $fb = [];
            $fb['text'] = $form->partcorrectfb[$i]['text'];
            $fb['format'] = $form->partcorrectfb[$i]['format'];
            if (isset($form->partcorrectfb[$i]['itemid'])) {
                $fb['itemid'] = $form->partcorrectfb[$i]['itemid'];
            }
            $res->answers[$i]->partcorrectfb = $fb;

            $fb = [];
            $fb['text'] = $form->partpartiallycorrectfb[$i]['text'];
            $fb['format'] = $form->partpartiallycorrectfb[$i]['format'];
            if (isset($form->partpartiallycorrectfb[$i]['itemid'])) {
                $fb['itemid'] = $form->partpartiallycorrectfb[$i]['itemid'];
            }
            $res->answers[$i]->partpartiallycorrectfb = $fb;

            $fb = [];
            $fb['text'] = $form->partincorrectfb[$i]['text'];
            $fb['format'] = $form->partincorrectfb[$i]['format'];
            if (isset($form->partincorrectfb[$i]['itemid'])) {
                $fb['itemid'] = $form->partincorrectfb[$i]['itemid'];
            }
            $res->answers[$i]->partincorrectfb = $fb;
        }
        if (count($res->answers) == 0) {
            $res->errors["answermark[0]"] = get_string('error_no_answer', 'qtype_formulas');
        }

        return $res;
    }

     /**
      * It checks basic errors as well as formula errors by evaluating one instantiation.
      * @param object $form
      * @return object
      */
    public function validate($form) {
        $errors = [];
        $answerschecked = $this->check_and_filter_answers($form);
        if (isset($answerschecked->errors)) {
            $errors = array_merge($errors, $answerschecked->errors);
        }
        $validanswers = $answerschecked->answers;
        foreach ($validanswers as $idx => $part) {
            if ($part->unitpenalty < 0 || $part->unitpenalty > 1) {
                $errors["unitpenalty[$idx]"] = get_string('error_unitpenalty', 'qtype_formulas');
            }
            try {
                $pattern = '\{(_[0-9u][0-9]*)(:[^{}]+)?\}';
                preg_match_all('/'.$pattern.'/', $part->subqtext['text'], $matches);
                $boxes = [];
                foreach ($matches[1] as $unuaed => $match) {
                    if (array_key_exists($match, $boxes)) {
                        throw new Exception(get_string('error_answerbox_duplicate', 'qtype_formulas'));
                    } else {
                        $boxes[$match] = 1;
                    }
                }
            } catch (Exception $e) {
                $errors["subqtext[$idx]"] = $e->getMessage();
            }
        }
        $pla = is_string($form->questiontext) ? $form->questiontext : $form->questiontext['text'];
        $placeholdererrors = $this->check_placeholder($pla, $validanswers);
        $errors = array_merge($errors, $placeholdererrors);

        $instantiationerrors = $this->validate_instantiation($form, $validanswers);
        $errors = array_merge($errors, $instantiationerrors);

        return (object)['errors' => $errors, 'answers' => $validanswers];
    }


     /**
      * Validating the data from the client, and return errors.
      * @param object $form
      * @param array $validanswers
      * @return array
      */
    public function validate_instantiation($form, &$validanswers) {

        $errors = [];

        // Create a formulas question so we can use its methods for validation.
        $qo = new qtype_formulas_question;
        foreach ($form as $key => $value) {
            $qo->$key = $value;
        }
        $tags = $this->part_tags();

        $qo->options = new stdClass();
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $field) {
            if (isset($form->{$field})) {
                $qo->{$field} = $form->{$field};
                $qo->options->{$field} = $form->{$field};
            }
        }

        if (count($form->answer)) {
            foreach ($form->answer as $key => $unused) {
                $ans = new stdClass();
                foreach ($tags as $tag) {
                    $ans->{$tag} = $form->{$tag}[$key];
                }
                $ans->subqtext = $form->subqtext[$key];
                $ans->feedback = $form->feedback[$key];
                $ans->partcorrectfb = $form->partcorrectfb[$key];
                $ans->partpartiallycorrectfb = $form->partpartiallycorrectfb[$key];
                $ans->partincorrectfb = $form->partincorrectfb[$key];
                $qo->options->answers[] = $ans;
            }
        }
        $qo->parts = [];
        if (!empty($qo->options->answers)) {
            foreach ($qo->options->answers as $i => $ans) {
                $ans->partindex = $i;
                $ans->subqtextformat = $ans->subqtext['format'];
                $ans->subqtext = $ans->subqtext['text'];
                $ans->feedbackformat = $ans->feedback['format'];
                $ans->feedback = $ans->feedback['text'];
                $ans->partcorrectfbformat = $ans->partcorrectfb['format'];
                $ans->partcorrectfb = $ans->partcorrectfb['text'];
                $ans->partpartiallycorrectfbformat = $ans->partpartiallycorrectfb['format'];
                $ans->partpartiallycorrectfb = $ans->partpartiallycorrectfb['text'];
                $ans->partincorrectfbformat = $ans->partincorrectfb['format'];
                $ans->partincorrectfb = $ans->partincorrectfb['text'];

                $qo->parts[$i] = new qtype_formulas_part();
                foreach ($ans as $key => $value) {
                    $qo->parts[$i]->$key = $value;
                    // TODO verify if part id is set (but do we actually need it here?).
                }
            }
        }
        $qo->qv = new qtype_formulas_variables();
        $qo->options->numpart = count($qo->options->answers);
        $qo->numpart = $qo->options->numpart;
        $qo->fractions = array_fill(0, $qo->numpart, 0);
        $qo->anscorrs = array_fill(0, $qo->numpart, 0);
        $qo->unitcorrs = array_fill(0, $qo->numpart, 0);

        try {
            $vstack = $qo->qv->parse_random_variables($qo->varsrandom);
            // Instantiate a set of random variables.
            $qo->randomsvars = $qo->qv->instantiate_random_variables($vstack);
        } catch (Exception $e) {
            $errors["varsrandom"] = $e->getMessage();
            return $errors;
        }

        try {
            $qo->globalvars = $qo->qv->evaluate_assignments($qo->randomsvars, $qo->varsglobal);
        } catch (Exception $e) {
            $errors["varsglobal"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
            return $errors;
        }

        // Attempt to compute answers to see if they are wrong or not.
        foreach ($validanswers as $idx => $ans) {
            $ans->partindex = $idx;
            $unitcheck = new answer_unit_conversion;

            try {
                $unitcheck->parse_targets($ans->postunit);
            } catch (Exception $e) {
                $errors["postunit[$idx]"] = get_string('error_unit', 'qtype_formulas') . $e->getMessage();
            }

            try {
                $unitcheck->assign_additional_rules($ans->otherrule);
                $unitcheck->reparse_all_rules();
            } catch (Exception $e) {
                $errors["otherrule[$idx]"] = get_string('error_rule', 'qtype_formulas') . $e->getMessage();
            }

            try {
                $conversionrules = new unit_conversion_rules;
                $entry = $conversionrules->entry($ans->ruleid);
                if ($entry === null || $entry[1] === null) {
                    throw new Exception(get_string('error_ruleid', 'qtype_formulas'));
                }
                $unitcheck->assign_default_rules($ans->ruleid, $entry[1]);
                $unitcheck->reparse_all_rules();
            } catch (Exception $e) {
                $errors["ruleid[$idx]"] = $e->getMessage();
            }

            try {
                $vars = $qo->qv->evaluate_assignments($qo->globalvars, $ans->vars1);
            } catch (Exception $e) {
                $errors["vars1[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

            try {
                $modelanswers = $qo->get_evaluated_answer($ans);
                $cloneanswers = $modelanswers;
                // Set the number of 'coordinates' which is used to display all answer boxes.
                $ans->numbox = count($modelanswers);
                $gradingtype = $ans->answertype;
            } catch (Exception $e) {
                $errors["answer[$idx]"] = $e->getMessage();
                continue;
            }

            try {
                $dres = $qo->compute_response_difference($vars, $modelanswers, $cloneanswers, 1, $gradingtype);
                if ($dres === null) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                $errors["answer[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

            try {
                $qo->add_special_correctness_variables($vars, $modelanswers, $cloneanswers, $dres->diff, $dres->is_number);
                $qo->qv->evaluate_assignments($vars, $ans->vars2);
            } catch (Exception $e) {
                $errors["vars2[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

            try {
                $responses = $qo->get_correct_responses_individually($ans);
                $correctness = $qo->grade_responses_individually($ans, $responses, $unitcheck);
            } catch (Exception $e) {
                $errors["correctness[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

        }
        return $errors;
    }

    /**
     * Reorder the answers according to the order of placeholders in main question text.
     *
     * The check_placeholder() function should be called before.
     *
     * @param string $questiontext The main question text containing the placeholders.
     * @param array $answers array of answers, containing the placeholder name  (must not be empty).
     * @return array answersorder.
     */
    public function reorder_answers($questiontext, $answers) {
        // Store the (scaled) location of the *named* placeholder in the main text.
        $locations = [];
        foreach ($answers as $idx => $answer) {
            if (strlen($answer->placeholder) != 0) {
                // Store the pair (location, idx).
                $locations[] = 1000 * strpos($questiontext, '{'.$answer->placeholder.'}') + $idx;
            }
        }
        // Performs stable sort of locations.
        sort($locations);

        $answersorder = [];
        foreach ($locations as $unused => $location) {
            // Store the new location of the answer in the main text.
            $answersorder[] = $location % 1000;
        }
        foreach ($answers as $idx => $answer) {
            if (strlen($answer->placeholder) == 0) {
                // Add the empty placeholder at the end.
                $answersorder[] = $idx;
            }
        }
        return $answersorder;
    }
}
