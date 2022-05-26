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
 * Conversion rules class
 *
 * @package   qtype_formulas
 * @copyright 2013 Jean-Michel Vedrine
 * @author    Hon Wai, Lau <lau65536@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Conversion rules class
 *
 * Each entry of $basicunitconvrule is a pair:
 *  - The first string is the name of the rule, which is used when editing the form
 *  - The second string is the actual rule that will be parsed and used as unit conversion
 *  - The array index is the unique id for the rule, which will be stored in the database
 * Note: the id from 0 to 99 are reserved, please do not use to create you own rules
 *
 * @package   qtype_formulas
 * @copyright 2013 Jean-Michel Vedrine
 * @author    Hon Wai, Lau <lau65536@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */
class unit_conversion_rules {
    /** @var basicunitconvrule **/
    private $basicunitconvrule = [];

    /**
     * Initialize the internal conversion rule.
     */
    public function __construct() {
        $this->basicunitconvrule[0] = [get_string('none', 'qtype_formulas'), ''];
        $this->basicunitconvrule[1] = [get_string('commonsiunit', 'qtype_formulas'), '
m: k c d m u n p f;
s: m u n p f;
g: k m u n p f;
mol: m u n p;
N: k m u n p f;
A: m u n p f;
J: k M G T P m u n p f;
J = 6.24150947e+18 eV;
eV: k M G T P m u;
W: k M G T P m u n p f;
Pa: k M G T P;
Hz: k M G T P E;
C: k m u n p f;
V: k M G m u n p f;
ohm: m k M G T P;
F: m u n p f;
T: k m u n p;
H: k m u n p;
'];
    }

    /**
     * Entry
     *
     * @param int $n
     * @return array
     */
    public function entry($n) {
        return $this->basicunitconvrule[$n];
    }

    /**
     * All rules
     *
     * @return array
     */
    public function allrules() {
        return $this->basicunitconvrule;
    }
}
