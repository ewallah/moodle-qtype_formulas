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
 * Moodle formulas question answer units.
 *
 * @package   qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @author    Hon Wai, Lau <lau65536@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */

/**
 * This class provides methods to check whether an input unit is convertible to a unit in a list.
 *
 * A unit is a combination of the 'base units' and its exponents. For the International System of Units
 * (SI), there are 7 base units and some derived units. In comparison, the 'base units' here represents
 * the unit that is not 'compound units', i.e. 'base units' is a string without space.
 * In order to compare whether two string represent the same unit, the method employed here is to
 * decompose both string into 'base units' and exponents and then compare one by one.
 *
 * In addition, different units can represent the same dimension linked by a conversion factor.
 * All those units are acceptable, so there is a need for a conversion method. To solve this problem,
 * for the same dimensional quantity, user can specify conversion rules between several 'base units'.
 * Also, user are allow to specify one (and only one) conversion rule between different 'compound units'
 * known as the $target variable in the check_convertibility().
 *
 * Example format of rules, for 'compound unit': "J = N m = kg m^2/s^2, W = J/s = V A, Pa = N m^(-2)"
 * For 'base unit': "1 m = 1e-3 km = 100 cm; 1 cm = 0.3937 inch; 1024 B = 1 KiB; 1024 KiB = 1 MiB"
 * The scale of a unit without a prefix is assumed to be 1. For convenience of using SI prefix, an
 * alternative rules format for 'base unit' is that a string with a unit and colon, then followed by
 * a list of SI prefix separated by a space, e.g. "W: M k m" equal to "W = 1e3 mW = 1e-3kW = 1e-6MW"
 *
 * @package   qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @author    Hon Wai, Lau <lau65536@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */
class answer_unit_conversion {
    /** @var Mapping of the unit to the (dimension class, scale) **/
    private $mapping;

    /** @var Additional rules other than the default rules. **/
    private $additionalrules;

    /** @var Default mapping of a user selected rules, usually Common SI prefix. **/
    private $defaultmapping;

    /** @var Dimension class id counter. **/
    private $defaultlastid;

    /** @var Id of the default rule. **/
    private $defaultid;

    /** @var String of the default rule in a particular format. **/
    private $defaultrules;

    /** @var unit exclusion symbols **/
    public static $unitexcludesymbols = '][)(}{><0-9.,:;`~!@#^&*\/?|_=+ -';

    /** @var prefix scale factors, u is used for micro-, rather than mu, which has multiple similar UTF representations. **/
    public static $prefixscalefactors = ['d' => 1e-1, 'c' => 1e-2, 'da' => 1e1, 'h' => 1e2,
        'm' => 1e-3, 'u' => 1e-6, 'n' => 1e-9, 'p' => 1e-12, 'f' => 1e-15, 'a' => 1e-18, 'z' => 1e-21, 'y' => 1e-24,
        'k' => 1e3,  'M' => 1e6,  'G' => 1e9,  'T' => 1e12,  'P' => 1e15,  'E' => 1e18,  'Z' => 1e21,  'Y' => 1e24];

    /**
     * Initialize the internal conversion rule to empty.
     *
     */
    public function __construct() {
        $this->defaultid = 0;
        $this->defaultrules = '';
        $this->defaultmapping = null;
        $this->mapping = null;
        $this->additionalrules = '';
    }

    /**
     * It assign default rules to this class. It will also reset the mapping. No exception raised.
     *
     * @param string $defaultid id of the default rule. Use to avoid reinitialization same rule set
     * @param string $defaultrules default rules
     */
    public function assign_default_rules($defaultid, $defaultrules) {
        if ($this->defaultid == $defaultid) {
            // Do nothing if the rules are unchanged.
            return;
        }
        $this->defaultid = $defaultid;
        $this->defaultrules = $defaultrules;
        $this->defaultmapping = null;
        $this->mapping = null;
        // Always remove the additional rule.
        $this->additionalrules = '';
    }

    /**
     * Add the additional rule other than the default. Note the previous additional rule will be erased.
     *
     * @param string $additionalrules the additional rule string
     */
    public function assign_additional_rules($additionalrules) {
        $this->additionalrules = $additionalrules;
        $this->mapping = null;
    }

    /**
     * Parse all defined rules. It is designed to avoid unnecessary reparsing. Exception on parsing error
     */
    public function reparse_all_rules() {
        if ($this->defaultmapping === null) {
            $tmpmapping = [];
            $tmpcounter = 0;
            $this->parse_rules($tmpmapping, $tmpcounter, $this->defaultrules);
            $this->defaultmapping = $tmpmapping;
            $this->defaultlastid = $tmpcounter;
        }
        if ($this->mapping === null) {
            $tmpmapping = $this->defaultmapping;
            $tmpcounter = $this->defaultlastid;
            $this->parse_rules($tmpmapping, $tmpcounter, $this->additionalrules);
            $this->mapping = $tmpmapping;
        }
    }

    /**
     * Check whether an input unit is equivalent, under conversion rules, to target units. May throw
     *
     * @param string $ipunit The input unit string
     * @param string $targets The list of unit separated by "=", such as "N = kg m/s^2"
     * @return object with three field:
     *   (1) convertible: true if the input unit is equivalent to the list of unit, otherwise false
     *   (2) cfactor: the number before ipunit has to multiply by this factor to convert a target unit.
     *     If the ipunit is not match to any one of target, the conversion factor is always set to 1
     *   (3) target: indicate the location of the matching in the $targets, if they are convertible
     */
    public function check_convertibility($ipunit, $targets) {
        $l1 = strlen(trim($ipunit)) == 0;
        $l2 = strlen(trim($targets)) == 0;
        if ($l1 && $l2) {
            // If both of them are empty, no unit check is required. i.e. they are equal.
            return (object) ['convertible' => true,  'cfactor' => 1, 'target' => 0];
        } else if (($l1 && !$l2) || (!$l1 && $l2)) {
            // If one of them is empty, they must not equal.
            return (object) ['convertible' => false, 'cfactor' => 1, 'target' => null];
        }
        // Parsing error for $ipunit is counted as not equal because it cannot match any $targets.
        $ip = $this->parse_unit($ipunit);
        if ($ip === null) {
            return (object) ['convertible' => false, 'cfactor' => 1, 'target' => null];
        }
        $this->reparse_all_rules();   // Reparse if the any rules have been updated.
        $targetslist = $this->parse_targets($targets);
        $res = $this->check_convertibility_parsed($ip, $targetslist);
        if ($res === null) {
            return (object) ['convertible' => false, 'cfactor' => 1, 'target' => null];
        } else {
            // For the input successfully converted to one of the unit in the $targets list.
            return (object) ['convertible' => true,  'cfactor' => $res[0], 'target' => $res[1]];
        }
    }

    /**
     * Parse the $targets into an array of target units. Throw on parsing error
     *
     * @param string $targets The "=" separated list of unit, such as "N = kg m/s^2"
     * @return an array of parsed unit, parsed by the parse_unit().
     */
    public function parse_targets($targets) {
        $targetslist = [];
        if (strlen(trim($targets)) == 0) {
            return $targetslist;
        }
        $units = explode('=', $targets);
        foreach ($units as $unit) {
            if (strlen(trim($unit) ) == 0) {
                throw new Exception('""');
            }
            $parsedunit = $this->parse_unit($unit);
            if ($parsedunit === null) {
                throw new Exception('"'.$unit.'"');
            }
            $targetslist[] = $parsedunit;
        }
        return $targetslist;
    }

    /**
     * Check whether an parsed input unit $a is the same as one of the parsed unit in $target_units. No throw
     *
     * @param array $a the an array of (base unit => exponent) parsed by the parse_unit() function
     * @param array $targetslist an array of parsed units.
     * @return the array of (conversion factor, location in target list) if convertible, otherwise null
     */
    private function check_convertibility_parsed($a, $targetslist) {
        // Use exclusion method to check whether there is one match.
        foreach ($targetslist as $i => $t) {
            if (count($a) != count($t)) {
                // If they have different number of base unit, skip.
                continue;
            }
            $cfactor = 1.;
            $isallmatches = true;
            foreach ($a as $name => $exponent) {
                $unitfound = isset($t[$name]);
                if ($unitfound) {
                    $f = 1;
                    // Exponent of the target base unit.
                    $e = $t[$name];
                } else {
                    // If the base unit not match directly, try conversion.
                    list($f, $e) = $this->attempt_conversion($name, $t);
                    $unitfound = isset($f);
                }
                // If unit is not found or the exponent of this dimension is wrong.
                if (!$unitfound || abs($exponent - $e) > 0) {
                    $isallmatches = false;
                    break;
                }
                $cfactor *= pow($f, $e);
            }
            if ($isallmatches) {
                // All unit name and their dimension matches.
                return [$cfactor, $i];
            }
        }
        return null;   // None of the possible units match, so they are not the same.
    }

    /**
     * Attempt to convert the $test_unit_name to one of the unit in the $base_unit_array,
     * using any of the conversion rule added in this class earlier. No throw
     *
     * @param string $testunitname the name of the test unit
     * @param array $baseunitarray in the format of array(unit => exponent, ...)
     * @return array(conversion factor, unit exponent) if it can be converted, otherwise null.
     */
    private function attempt_conversion($testunitname, $baseunitarray) {
        $oclass = $this->mapping[$testunitname];
        if (!isset($oclass)) {
            // It does not exist in the mapping implies it is not convertible.
            return null;
        }
        foreach ($baseunitarray as $u => $e) {
            // Try to match the dimension class of each base unit.
            $tclass = $this->mapping[$u];
            if (isset($tclass) && $oclass[0] == $tclass[0]) {
                return [$oclass[1] / $tclass[1], $e];
            }
        }
        return null;
    }

    /**
     * Split the input into the number and unit. No exception
     *
     * @param string $input physical quantity with number and unit, assume 1 if number is missing
     * @return object with number and unit as the field name. null if input is empty
     */
    private function split_number_unit($input) {
        $input = trim($input);
        if (strlen($input) == 0) {
            return null;
        }
        $ex = explode(' ', $input, 2);
        $number = $ex[0];
        $unit = count($ex) > 1 ? $ex[1] : null;
        if (is_numeric($number)) {
            return (object) ['number' => floatval($number), 'unit' => $unit];
        } else {
            return (object) ['number' => 1, 'unit' => $input];
        }
    }

    /**
     * Parse the unit string into a simpler pair of base unit and its exponent. No exception
     *
     * @param string $unitexpression The input unit string
     * @param bool $nodivisor whether divisor '/' is acceptable. It is used to parse unit recursively
     * @return an array of the form (base unit name => exponent), null on error
     */
    public function parse_unit($unitexpression, $nodivisor = false) {
        if (strlen(trim($unitexpression)) == 0) {
            return [];
        }

        $pos = strpos($unitexpression, '/');
        if ($pos !== false) {
            if ($nodivisor || $pos == 0 || $pos >= strlen($unitexpression) - 1) {
                // Only one '/' is allowed.
                return null;
            }
            $left = trim(substr($unitexpression, 0, $pos));
            $right = trim(substr($unitexpression, $pos + 1));
            if ($right[0] == '(' && $right[strlen($right) - 1] == ')') {
                $right = substr($right, 1, strlen($right) - 2);
            }
            $uleft = $this->parse_unit($left, true);
            $uright = $this->parse_unit($right, true);
            if ($uleft == null || $uright == null) {
                // If either part contains error.
                return null;
            }
            foreach ($uright as $u => $exponent) {
                if (array_key_exists($u, $uleft)) {
                    // No duplication.
                    return null;
                }
                // Take opposite of the exponent.
                $uleft[$u] = -$exponent;
            }
            return $uleft;
        }

        $unit = [];
        $unitelementname = '([^'.self::$unitexcludesymbols.']+)';
        $unitexpression = preg_replace('/\s*\^\s*/', '^', $unitexpression);
        $candidates = explode(' ', $unitexpression);
        foreach ($candidates as $candidate) {
            $ex = explode('^', $candidate);
             // There should be no space remaining.
            $name = $ex[0];
            if (count($ex) > 1 && (strlen($name) == 0 || strlen($ex[1]) == 0)) {
                return null;
            }
            if (strlen($name) == 0) {
                // If it is an empty space.
                continue;
            }
            if (!preg_match('/^'.$unitelementname.'$/', $name)) {
                return null;
            }
            $exponent = null;
            if (count($ex) > 1) {
                // Get the number of exponent.
                if (!preg_match('/(.*)([0-9]+)(.*)/', $ex[1], $matches)) {
                    return null;
                }
                if ($matches[1] == '' && $matches[3] == '') {
                    $exponent = intval($matches[2]);
                }
                if ($matches[1] == '-' && $matches[3] == '') {
                    $exponent = -intval($matches[2]);
                }
                if ($matches[1] == '(-' && $matches[3] == ')') {
                    $exponent = -intval($matches[2]);
                }
                // No pattern matched.
                if ($exponent == null) {
                    return null;
                }
            } else {
                $exponent = 1;
            }
            // No duplication.
            if (array_key_exists($name, $unit)) {
                return null;
            }
            $unit[$name] = $exponent;
        }
        return $unit;
    }

    /**
     * Parse rules into an mapping that will be used for fast lookup of unit. Exception on parsing error
     *
     * @param array $mapping an empty array, or array of unit => array(dimension class, conversion factor)
     * @param int $dimidcount current number of dimension class. It will be incremented for new class
     * @param string $rulesstring a comma separated list of rules
     */
    private function parse_rules(&$mapping, &$dimidcount, $rulesstring) {
        $rules = explode(';', $rulesstring);
        foreach ($rules as $rule) {
            if (strlen(trim($rule)) > 0) {
                $unitscales = [];
                $e = explode(':', $rule);
                if (count($e) > 3) {
                    throw new Exception('Syntax error of SI prefix');
                } else if (count($e) == 2) {
                    $unitname = trim($e[0]);
                    if (preg_match('/['.self::$unitexcludesymbols.']+/', $unitname)) {
                        throw new Exception('"'.$unitname.'" unit contains unaccepted character.');
                    }
                    // The original unit.
                    $unitscales[$unitname] = 1.0;
                    $siprefixes = explode(' ', $e[1]);
                    foreach ($siprefixes as $prefix) {
                        if (strlen($prefix) != 0) {
                            $f = self::$prefixscalefactors[$prefix];
                            if (!isset($f)) {
                                throw new Exception('"'.$prefix.'" is not SI prefix.');
                            }
                            $unitscales[$prefix.$unitname] = $f;
                        }
                    }
                } else {
                    $data = explode('=', $rule);
                    foreach ($data as $d) {
                        $splitted = $this->split_number_unit($d);
                        if ($splitted === null || preg_match('/[' . self::$unitexcludesymbols . ']+/', $splitted->unit)) {
                            throw new Exception("$splitted->unit unit contains unaccepted character.");
                        }
                        $unitscales[trim($splitted->unit)] = 1. / floatval($splitted->number);
                    }
                }
                // Is the first unit already defined?
                if (array_key_exists(key($unitscales), $mapping)) {
                    // If yes, use the existing id of the same dimension class.
                    $m = $mapping[key($unitscales)];
                    // This can automatically join all the previously defined unit scales.
                    $dimid = $m[0];
                    // Define the relative scale.
                    $factor = $m[1] / current($unitscales);
                } else {
                    // Otherwise use a new id and define the relative scale to 1.
                    $dimid = $dimidcount++;
                    $factor = 1;
                }
                foreach ($unitscales as $unit => $scale) {
                    // Join the new unit scale to old one, if any.
                    $mapping[$unit] = [$dimid, $factor * $scale];
                }
            }
        }
    }
}
