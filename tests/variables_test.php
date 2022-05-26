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
 * Unit tests for the formulas qtype_formulas_variables class.
 *
 * @package    qtype_formulas
 * @copyright  2018 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');


/**
 * Unit tests for the formulas question variables class.
 *
 * @copyright  2018 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class variables_test extends advanced_testcase {

    /**
     * Test 1: get_expressions_in_bracket() test.
     */
    public function test_get_expressions_in_bracket() {
        $qv = new qtype_formulas_variables;
        $brackettest = [
            [true, '8+sum([1,2+2])', '(', 5, 13, ["[1,2+2]"]],
            [true, '8+[1,2+2,3+sin(3),sum([1,2,3])]', '[', 2, 30, ["1", "2+2", "3+sin(3)", "sum([1,2,3])"]],
            [true, 'a=0; for x in [1,2,3] { a=a+sum([1,x]); }', '{', 22, 40, [" a=a+sum([1,x]); "]]];
        foreach ($brackettest as $b) {
            $errmsg = null;
            try {
                $result = $qv->get_expressions_in_bracket($b[1], 0, $b[2]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            $eval = $errmsg === null;
            $this->assertEquals($b[0], $eval);
            $this->assertEquals($b[3], $result->openloc);
            $this->assertEquals($b[4], $result->closeloc);
            $this->assertEquals($b[5], $result->expressions);
        }
    }

    /**
     * Test 2: evaluate_general_expression() test.
     */
    public function test_evaluate_general_expression() {
        $qv = new qtype_formulas_variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_general_expression($v, 'sin(4) + exp(cos(4+5))');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);
        $this->assertEquals(-0.35473297204849, $result->value);
        $this->assertEquals('n', $result->type);
    }

    /**
     * Test 3.1: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_1() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            [true, '#--------- basic operation ---------#', []],
            [true, 'a = 1;', ['a' => (object) ['type' => 'n', 'value' => 1]]],
            [true, 'a = 1; b = 4;',
                ['a' => (object) ['type' => 'n', 'value' => 1], 'b' => (object) ['type' => 'n', 'value' => 4]]],
            [true, 'a = 1; # This is comment! So it will be skipped. ', ['a' => (object) ['type' => 'n', 'value' => 1]]],
            [true, 'c = cos(0)+3.14;', ['c' => (object) ['type' => 'n', 'value' => 4.1400000000000006]]],
            [true, 'd = "Hello!";', ['d' => (object) ['type' => 's', 'value' => "Hello!"]]],
            [true, 'e =[1,2,3,4];', ['e' => (object) ['type' => 'ln', 'value' => [1, 2 , 3 , 4]]]],
            [true, 'f =["A", "B", "C"];', ['f' => (object) ['type' => 'ls', 'value' => ["A", "B", "C"]]]],
            [true, 'a = 1; b = 4; c = a*b; g= [1,2+45, cos(0)+1,exp(a),b*c];', [
                    'a' => (object) ['type' => 'n', 'value' => 1],
                    'b' => (object) ['type' => 'n', 'value' => 4],
                    'c' => (object) ['type' => 'n', 'value' => 4],
                    'g' => (object) ['type' => 'ln', 'value' => [1, 47 , 2 , 2.718281828459, 16]]]],
            [true, 'h = [1,2+3,sin(4),5]; j=h[1];', [
                    'h' => (object) ['type' => 'ln', 'value' => [1, 5 , -0.7568024953079282, 5]],
                    'j' => (object) ['type' => 'n', 'value' => 5]]],
            [true, 'e = [1,2,3,4][1];', ['e' => (object) ['type' => 'n', 'value' => 2]]],
            [true, 'e = [1,2,3,4]; e[2]=111;', ['e' => (object) ['type' => 'ln', 'value' => [1, 2 , 111 , 4]]]],
            [true, 'e = [1,2,3,4]; a=1; e[a]=111;', [
                    'e' => (object) ['type' => 'ln', 'value' => [1, 111 , 3 , 4]],
                    'a' => (object) ['type' => 'n', 'value' => 1]]],
            [true, 'e = [1,2,3,4]; a=1-1; e[a]=111;', [
                    'e' => (object) ['type' => 'ln', 'value' => [111, 2 , 3 , 4]],
                    'a' => (object) ['type' => 'n', 'value' => 0]]],
            [true, 'g = [3][0];', ['g' => (object) ['type' => 'n', 'value' => 3]]],
            [true, 'a = [7,8,9]; g = [a[1]][0];', [
                    'a' => (object) ['type' => 'ln', 'value' => [7, 8 , 9]],
                    'g' => (object) ['type' => 'n', 'value' => 8]]],
            [true, 'h = [0:10]; k=[4:8:1]; m=[-20:-10:1.5];', [
                    'h' => (object) ['type' => 'ln', 'value' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]],
                    'k' => (object) ['type' => 'ln', 'value' => [4, 5, 6, 7]],
                    'm' => (object) ['type' => 'ln', 'value' => [-20, -18.5, -17, -15.5, -14, -12.5, -11]]]],
            [true, 'a = [1,2,3]; s=[2,0,1]; n=[3*a[s[0]], 3*a[s[1]], 3*a[s[2]]*9];', [
                    'a' => (object) ['type' => 'ln', 'value' => [1, 2 , 3]],
                    's' => (object) ['type' => 'ln', 'value' => [2, 0 , 1]],
                    'n' => (object) ['type' => 'ln', 'value' => [9, 3 , 54]]]],
            [false, 'a=3 6;', '1: Some expressions cannot be evaluated numerically.'],
            [false, 'a=3`6;', 'Formula or expression contains forbidden characters or operators.'],
            [false, 'f=1; g=f[1];', '2: Variable is unsubscriptable.'],
            [false, 'e=[];', '1: A subexpression is empty.'],
            [true, 'a=3**8;', ['a' => (object) ['type' => 'n', 'value' => 6561]]]];
        foreach ($testcases as $testcase) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $result = $qv->evaluate_assignments($v, $testcase[1]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($testcase[0]) {
                // Test that no exception is thrown
                // and that correct result is returned.
                $this->assertNull($errmsg);
                $this->assertEquals(0, $result->idcounter);
                if ($testcase[2] != '') {
                    $this->assertEquals($testcase[2], $result->all);
                }
                $this->assertEquals($testcase[2], $result->all);

            } else {
                // Test that the correct exception message is returned.
                $this->assertEquals($testcase[2], $errmsg);
            }
        }
    }

    /**
     * Test 3.2: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_2() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            [false, 'e=[1,2,3,4]; a=1-1; e[a]="A";',
                '3: Element in the same list must be of the same type, either number or string.'],
            [false, 'e=[1,2,"A"];', '1: Element in the same list must be of the same type, either number or string.'],
            [false, 'e=[1,2,3][4,5];', '1: Non-numeric value cannot be used as list index.'],
            [false, 'e=[1,2,3]; f=e[4,5]', '2: Non-numeric value cannot be used as list index.'],
            [false, 'e=[1,2,3,4]; f=e*2;', '2: Some expressions cannot be evaluated numerically.'],
            [false, 'e=[1,2,3][1][4,5,6][2];', '1: Variable is unsubscriptable.'],
            [false, 'e=[0:10,"k"];', 'Syntax error of a fixed range.'],
            [false, 'e=[[1,2],[3,4]];', '1: Element in the same list must be of the same type, either number or string.'],
            [false, 'e=[[[1,2],[3,4]]];', '1: Element in the same list must be of the same type, either number or string.'],
            [false,
                'e=[1,2,3]; e[0] = [8,9];', '2: Element in the same list must be of the same type, either number or string.'],
            [true, '#--------- additional function (correct) ---------#', []],
            [true, 'a=4; A = fill(2,0); B= fill ( 3,"Hello"); C=fill(a,4);', [
                    'a' => (object) ['type' => 'n', 'value' => 4],
                    'A' => (object) ['type' => 'ln', 'value' => [0, 0]],
                    'B' => (object) ['type' => 'ls', 'value' => ['Hello', 'Hello', 'Hello']],
                    'C' => (object) ['type' => 'ln', 'value' => [4, 4, 4, 4]]]],
            [true, 'a=[1,2,3,4]; b=len(a); c=fill(len(a),"rr")', [
                    'a' => (object) ['type' => 'ln', 'value' => [1, 2 , 3 , 4]],
                    'b' => (object) ['type' => 'n', 'value' => 4],
                    'c' => (object) ['type' => 'ls', 'value' => ['rr', 'rr', 'rr', 'rr']]]],
            [true, 'p1=pick(4,[2,3,5,7,11]);', ['p1' => (object) ['type' => 'n', 'value' => 2]]],
            [true, 'p1=pick(3.1,[2,3,5,7,11]);', ['p1' => (object) ['type' => 'n', 'value' => 2]]],
            [true, 'p1=pick(1000,[2,3,5,7,11]);', ['p1' => (object) ['type' => 'n', 'value' => 2]]],
            [true, 'p1=pick(2,[2,3],[4,5],[6,7]);', ['p1' => (object) ['type' => 'ln', 'value' => [6, 7]]]],
            [true, 's=sort([7,5,3,11,2]);', ['s' => (object) ['type' => 'ln', 'value' => [2, 3, 5, 7, 11]]]],
            [true, 's=sort(["B","A2","A1"]);', ['s' => (object) ['type' => 'ls', 'value' => ['A1', 'A2', 'B']]]],
            [true, 's=sort(["B","A2","A1"],[2,4,1]);', ['s' => (object) ['type' => 'ls', 'value' => ['A1', 'B', 'A2']]]],
            [true, 's=sublist(["A","B","C","D"],[1,3]);', ['s' => (object) ['type' => 'ls', 'value' => ['B', 'D']]]],
            [true, 's=sublist(["A","B","C","D"],[0,0,2,3]);', ['s' => (object) ['type' => 'ls', 'value' => ['A', 'A', 'C', 'D']]]],
            [true, 's=inv([2,0,3,1]);', ['s' => (object) ['type' => 'ln', 'value' => [1, 3, 0, 2]]]],
            [true, 's=inv(inv([2,0,3,1]));', ['s' => (object) ['type' => 'ln', 'value' => [2, 0, 3, 1]]]]];
        foreach ($testcases as $testcase) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $result = $qv->evaluate_assignments($v, $testcase[1]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($testcase[0]) {
                // Test that no exception is thrown and that correct result is returned.
                $this->assertNull($errmsg);
                $this->assertEquals(0, $result->idcounter);
                if ($testcase[2] != '') {
                    // For now we don't test result with some randomness.
                    $this->assertEquals($testcase[2], $result->all);
                }
            } else {
                // Test that the correct exception message is returned.
                $this->assertEquals($testcase[2], $errmsg);
            }
        }
    }

    /**
     * Test 3.3: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_3() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            [true, 'A=["A","B","C","D"]; B=[2,0,3,1]; s=sublist(sublist(A,B),inv(B));', [
                    'A' => (object) ['type' => 'ls', 'value' => ['A', 'B', 'C', 'D']],
                    'B' => (object) ['type' => 'ln', 'value' => [2, 0, 3, 1]],
                    's' => (object) ['type' => 'ls', 'value' => ['A', 'B', 'C', 'D']]]],
            [true, 'a=[1,2,3]; A=map("exp",a);', [
                    'a' => (object) ['type' => 'ln', 'value' => [1, 2, 3]],
                    'A' => (object) ['type' => 'ln', 'value' => [2.718281828459, 7.3890560989307, 20.085536923188]]]],
            [true, 'a=[1,2,3]; A=map("+",a,2.3);', [
                    'a' => (object) ['type' => 'ln', 'value' => [1, 2, 3]],
                    'A' => (object) ['type' => 'ln', 'value' => [3.3, 4.3, 5.3]]]],
            [true, 'a=[1,2,3]; b=[4,5,6]; A=map("+",a,b);', [
                    'a' => (object) ['type' => 'ln', 'value' => [1, 2, 3]],
                    'b' => (object) ['type' => 'ln', 'value' => [4, 5, 6]],
                    'A' => (object) ['type' => 'ln', 'value' => [5, 7, 9]]]],
            [true, 'a=[1,2,3]; b=[4,5,6]; A=map("pow",a,b);', [
                    'a' => (object) ['type' => 'ln', 'value' => [1, 2, 3]],
                    'b' => (object) ['type' => 'ln', 'value' => [4, 5, 6]],
                    'A' => (object) ['type' => 'ln', 'value' => [1, 32, 729]]]],
            [true, 'r=sum([4,5,6]);', ['r' => (object) ['type' => 'n', 'value' => 15]]],
            [true, 'r=3+sum(fill(10,-1))+3;', ['r' => (object) ['type' => 'n', 'value' => -4]]],
            [true, 's=concat([1,2,3], [4,5,6], [7,8]);', ['s' => (object) ['type' => 'ln', 'value' => [1, 2, 3, 4, 5, 6, 7, 8]]]],
            [true, 's=concat(["A","B"],["X","Y","Z"],["Hello"]);',
                    ['s' => (object) ['type' => 'ls', 'value' => ['A', 'B', 'X', 'Y', 'Z', 'Hello']]]],
            [true, 's=join("~", [1,2,3]);', ['s' => (object) ['type' => 's', 'value' => '1~2~3']]],
            [true, 's=str(45);', ['s' => (object) ['type' => 's', 'value' => '45']]],
            [true, 'a=[4,5]; s = join(",","A","B", [ 1 , a  [1]], 3, [join("+",a,"?"),"9"]);', [
                    'a' => (object) ['type' => 'ln', 'value' => [4, 5]],
                    's' => (object) ['type' => 's', 'value' => "A,B,1,5,3,4+5+?,9"]]],
            [true, '#--------- additional function (incorrect) ---------#', []],
            [false, 'c=fill(0,"rr")', '1: Size of list must be within 1 to 1000.'],
            [false, 'c=fill(10000,"rr")', '1: Size of list must be within 1 to 1000.'],
            [false, 's=fill);', 'Function fill() is reserved and cannot be used as variable.'],
            [false, 's=fill(10,"rr";', '1: Bracket mismatch.'],
            [false, 'a=1; l=len(a);', '2: Wrong number or wrong type of parameters for the function len()'],
            [false, 'a=[1,2,3,4]; c=fill(len(a)+1,"rr")', '2: Wrong number or wrong type of parameters for the function fill()'],
            [false, 'p1=pick("r",[2,3,5,7,11]);', '1: Wrong number or wrong type of parameters for the function pick()'],
            [false, 'p1=pick(2,[2,3],[4,5],["a","b"]);', '1: Wrong number or wrong type of parameters for the function pick()'],
            [false, 's=concat(0, [1,2,3], [5,6], 100);', '1: Wrong number or wrong type of parameters for the function concat()'],
            [false, 's=concat([1,2,3], ["A","B"]);', '1: Wrong number or wrong type of parameters for the function concat()'],
            [true, '#--------- for loop ---------#', []],
            [true, 'A = 1; Z = A + 3; Y = "Hello!"; X = sum([4:12:2]) + 3;', [
                    'A' => (object) ['type' => 'n', 'value' => 1],
                    'Z' => (object) ['type' => 'n', 'value' => 4],
                    'Y' => (object) ['type' => 's', 'value' => "Hello!"],
                    'X' => (object) ['type' => 'n', 'value' => 31]]],
            [true, 'for(i:[1,2,3]){};', ['i' => (object) ['type' => 'n', 'value' => 3]]],
            [true, 'for ( i : [1,2,3] ) {};', ['i' => (object) ['type' => 'n', 'value' => 3]]],
            [true, 'z = 0; A=[1,2,3]; for(i:A) z=z+i;', [
                    'z' => (object) ['type' => 'n', 'value' => 6],
                    'A' => (object) ['type' => 'ln', 'value' => [1, 2, 3]],
                    'i' => (object) ['type' => 'n', 'value' => 3]]],
            [true, 'z = 0; for(i: [0:5]){z = z + i;}', [
                    'z' => (object) ['type' => 'n', 'value' => 10],
                    'i' => (object) ['type' => 'n', 'value' => 4]]],
            [true, 's = ""; for(i: ["A","B","C"]) { s=join("",s,[i]); }', [
                    's' => (object) ['type' => 's', 'value' => "ABC"],
                    'i' => (object) ['type' => 's', 'value' => "C"]]],
            [true, 'z = 0; for(i: [0:5]) for(j: [0:3]) z=z+i;', [
                    'z' => (object) ['type' => 'n', 'value' => 30],
                    'i' => (object) ['type' => 'n', 'value' => 4],
                    'j' => (object) ['type' => 'n', 'value' => 2]]],
            [false, 'z = 0; for(: [0:5]) z=z+i;', '2: Variable of the for loop has some errors.'],
            [false, 'z = 0; for(i:) z=z+i;', '2: A subexpression is empty.'],
            [false, 'z = 0; for(i: [0:5]) ', ''],
            [false, 'z = 0; for(i: [0:5]) for(j [0:3]) z=z+i;', '4: Syntax error of the for loop.'],
            [false, 'z = 0; for(i: [0:5]) z=z+i; b=[1,"b"];',
                '5: Element in the same list must be of the same type, either number or string.'],
            [true, '#--------- algebraic variable ---------#', []],
            [true, 'x = {1,2,3};', ['x' => (object) ['type' => 'zn', 'value' => (object) [
                'numelement' => 3, 'elements' => [[1, 1.5, 1], [2, 2.5, 1], [3, 3.5, 1]]]]]],
            [true, 'x = { 1 , 2 , 3 };', ['x' => (object) ['type' => 'zn', 'value' => (object) [
                'numelement' => 3, 'elements' => [[1, 1.5, 1], [2, 2.5, 1], [3, 3.5, 1]]]]]],
            [true, 'x = {1:3, 4:5:0.1 , 8:10:0.5 };', [
                'x' => (object) ['type' => 'zn',
                'value' => (object) ['numelement' => 16, 'elements' => [[1, 3, 1], [4, 5, 0.1], [8, 10, 0.5]]]]]],
            [true, 's=diff([3*3+3],[3*4]);', ['s' => (object)['type' => 'ln', 'value' => [0]]]],
            [true, 'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x^2+y^2"],50);', [
                'x' => (object) ['type' => 'zn', 'value' => (object) ['numelement' => 9, 'elements' => [[1, 10, 1]]]],
                'y' => (object) ['type' => 'zn', 'value' => (object) ['numelement' => 9, 'elements' => [[1, 10, 1]]]],
                's' => (object) ['type' => 'ln', 'value' => [0]]]],
            [true, 'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x+y^2"],50)[0];', ''],
            [false, 's=diff([3*3+3,0],[3*4]);', '1: Wrong number or wrong type of parameters for the function diff()'],
            [false, 'x = {"A", "B"};', '1: Syntax error of defining algebraic variable.'],
        ];
        foreach ($testcases as $testcase) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $result = $qv->evaluate_assignments($v, $testcase[1]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($testcase[0]) {
                // Test that no exception is thrown
                // and that correct result is returned.
                $this->assertNull($errmsg);
                $this->assertEquals(0, $result->idcounter);
                if ($testcase[2] != '') {
                    // For now we don't test result with some randomness.
                    $this->assertEquals($testcase[2], $result->all);
                }
            } else {
                // Test that the correct exception message is returned.
                $this->assertEquals($testcase[2], $errmsg);
            }
        }
    }

    /**
     * Test 4: parse_random_variables(), instantiate_random_variables().
     */
    public function test_parse_random_variables() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            [true, 'a = shuffle ( ["A","B", "C" ])', ''],
            [true, 'a = {1,2,3}', ['a' => (object) ['type' => 'n', 'value' => 2]]],
            [true, 'a = {[1,2], [3,4]}', ['a' => (object) ['type' => 'ln', 'value' => [3, 4]]]],
            [true, 'a = {"A","B","C"}', ['a' => (object) ['type' => 's', 'value' => "B"]]],
            [true, 'a = {["A","B"],["C","D"]}', ['a' => (object) ['type' => 'ls', 'value' => ['C', 'D']]]],
            [true, 'a = {0, 1:3:0.1, 10:30, 100}', ['a' => (object) ['type' => 'n', 'value' => 10]]],
            [true, 'a = {1:3:0.1}; b={"A","B","C"};', [
                'a' => (object) ['type' => 'n', 'value' => 2],
                'b' => (object) ['type' => 's', 'value' => "B"]]],
            [false, 'a = {10:1:1}', '1: a: Syntax error.'],
            [false, 'a = {1:10,}', '1: a: Uninitialized string offset 0'],
            [false, 'a = {1:10?}', '1: a: Formula or expression contains forbidden characters or operators.'],
            [false, 'a = {0, 1:3:0.1, 10:30, 100}*3', '1: a: Syntax error.'],
            [false, 'a = {1:3:0.1}; b={a,12,13};', '2: b: Formula or expression contains forbidden characters or operators.'],
            [false, 'a = {[1,2],[3,4,5]}', '1: a: All elements in the set must have exactly the same type and size.'],
            [false, 'a = {[1,2],["A","B"]}', '1: a: All elements in the set must have exactly the same type and size.'],
            [false, 'a = {[1,2],["A","B","C"]}', '1: a: All elements in the set must have exactly the same type and size.'],
        ];
        foreach ($testcases as $testcase) {
            $errmsg = null;
            $var = (object)['all' => null];
            try {
                $var = $qv->parse_random_variables($testcase[1]);
                // To predict the result we choose the dataset rather than having it at random.
                $dataset = (int) ($qv->vstack_get_number_of_dataset($var) / 2);
                $inst = $qv->instantiate_random_variables($var, $dataset);
                $serialized = $qv->vstack_get_serialization($inst);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($testcase[0]) {
                // Test that no exception is thrown
                // and that correct result is returned.
                $this->assertNull($errmsg);
                $this->assertEquals(0, $inst->idcounter);
                if ($testcase[2] != '') {
                    // For now we don't test variables with shuffle.
                    $this->assertEquals($testcase[2], $inst->all);
                }
            } else {
                // Test that the correct exception message is returned.
                $this->assertEquals($testcase[2], $errmsg);
            }
        }
    }
    /**
     * Test 5: substitute_variables_in_text.
     */
    public function test_substitute_variables_in_text() {
        $qv = new qtype_formulas_variables;
        $vstack = $qv->vstack_create();
        $variablestring = 'a=1; b=[2,3,4];';
        $vstack = $qv->evaluate_assignments($vstack, $variablestring);
        $text =
            '{a}, {a }, { a}, {b}, {b[0]}, {b[0] }, { b[0]}, {b [0]}, {=a*100}, {=b[0]*b[1]}, {= b[1] * b[2] }, {=100+[4:8][1]} ';
        $newtext = $qv->substitute_variables_in_text($vstack, $text);
        $expected = '1, {a }, { a}, {b}, 2, {b[0] }, { b[0]}, {b [0]}, 100, 6, 12, 105 ';
        $this->assertEquals($expected, $newtext);
    }

    /**
     * Test 6.1: Numerical formula.
     */
    public function test_numerical_formula_1() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            [true, 0, '3', 3],
            [true, 0, '3.', 3],
            [true, 0, '.3', 0.3],
            [true, 0, '3.1', 3.1],
            [true, 0, '3.1e-10', 3.1e-10],
            [true, 0, '3.e10', 30000000000],
            [true, 0, '.3e10', 3000000000],
            [true, 0, '-3', -3],
            [true, 0, '+3', 3],
            [true, 0, '-3.e10', -30000000000],
            [true, 0, '-.3e10', -3000000000],
            [true, 0, 'pi', 0],
            [false, 0, '- 3'],
            [false, 0, '+ 3'],
            [false, 0, '3 e10'],
            [false, 0, '3e 10'],
            [false, 0, '3e8e8'],
            [false, 0, '3+10*4'],
            [false, 0, '3+10^4'],
            [false, 0, 'sin(3)'],
            [false, 0, '3+exp(4)'],
            [false, 0, '3*4*5'],
            [false, 0, '3 4 5'],
            [false, 0, 'a*b'],
            [false, 0, '#']];
        foreach ($testcases as $testcase) {
            $result = $qv->compute_numerical_formula_value($testcase[2], $testcase[1]);
            $eval = $result !== null;
            $this->assertEquals($testcase[0], $eval);
            if ($testcase[0]) {
                $this->assertEquals($testcase[3], $result);
            }
        }
    }

    /**
     * Test 6.2: Numerical formula.
     */
    public function test_numerical_formula_2() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            // Numeric is basically a subset of 10al formula.
            [true, 10, '3+10*4/10^4', 3.004],
            [false, 10, 'sin(3)'],
            [false, 10, '3+exp(4)'],

            // Numerical formula is basically a subset of algebraic formula, so test below together.
            [true, 100, '3.1e-10', 3.1e-10],
            [true, 100, '- 3', -3], // It is valid for this type.
            [false, 100, '3 e10'],
            [false, 100, '3e 10'],
            [false, 100, '3e8e8'],
            [false, 100, '3e8e8e8'],

            [true, 100, '3+10*4/10^4', 3.004],
            [true, 100, 'sin(3)-3+exp(4)', 51.739270041204],
            [true, 100, '3*4*5', 60],
            [true, 100, '3 4 5', 60],
            [true, 100, '3e8 4.e8 .5e8', 6.0E+24],
            [true, 100, '3e8(4.e8+2)(.5e8/2)5', 1.5000000075E+25],
            [true, 100, '3e8(4.e8+2) (.5e8/2)5',  1.5000000075E+25],
            [true, 100, '3e8 (4.e8+2)(.5e8/2) 5', 1.5000000075E+25],
            [true, 100, '3e8 (4.e8+2) (.5e8/2) 5', 1.5000000075E+25],
            [true, 100, '3(4.e8+2)3e8(.5e8/2)5', 4.5000000225E+25],
            [true, 100, '3+4^9', 262147],
            [true, 100, '3+(4+5)^9', 387420492],
            [true, 100, '3+(4+5)^(6+7)', 2541865828332],
            [true, 100, '3+sin(4+5)^(6+7)', 3.0000098920712],
            [true, 100, '3+exp(4+5)^sin(6+7)', 46.881961305748],
            [true, 100, '3+4^-(9)', 3.0000038146973],
            [true, 100, '3+4^-9', 3.0000038146973],
            [true, 100, '3+exp(4+5)^-sin(6+7)', 3.0227884071323],
            [true, 100, '1+ln(3)', 2.0986122886681],
            [true, 100, '1+log10(3)', 1.4771212547197],
            [true, 100, 'pi', 3.1415926535898],
            [false, 100, 'pi()']];
        foreach ($testcases as $testcase) {
            $result = $qv->compute_numerical_formula_value($testcase[2], $testcase[1]);
            $eval = $result !== null;
            $this->assertEquals($testcase[0], $eval);
            if ($testcase[0]) {
                $this->assertEquals($testcase[3], $result);
            }
        }
    }

    /**
     * Test 7: Algebraic formula.
     */
    public function test_algebraic_formula() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            [true, '- 3'],
            [false, '3 e10'],
            [true, '3e 10'],
            [false, '3e8e8'],
            [false, '3e8e8e8'],
            [true, 'sin(3)-3+exp(4)'],
            [true, '3e8 4.e8 .5e8'],
            [true, '3e8(4.e8+2)(.5e8/2)5'],
            [true, '3+exp(4+5)^sin(6+7)'],
            [true, '3+4^-(9)'],
            [true, 'sin(a)-a+exp(b)'],
            [true, 'a*b*c'],
            [true, 'a b c'],
            [true, 'a(b+c)(x/y)d'],
            [true, 'a(b+c) (x/y)d'],
            [true, 'a (b+c)(x/y) d'],
            [true, 'a (b+c) (x/y) d'],
            [true, 'a(4.e8+2)3e8(.5e8/2)d'],
            [true, 'pi'],
            [true, 'a+x^y'],
            [true, '3+x^-(y)'],
            [true, '3+x^-y'],
            [true, '3+(u+v)^x'],
            [true, '3+(u+v)^(x+y)'],
            [true, '3+sin(u+v)^(x+y)'],
            [true, '3+exp(u+v)^sin(x+y)'],
            [true, 'a+exp(a)(u+v)^sin(1+2)(b+c)'],
            [true, 'a+exp(u+v)^-sin(x+y)'],
            [true, 'a+b^c^d+f'],
            [true, 'a+b^(c^d)+f'],
            [true, 'a+(b^c)^d+f'],
            [true, 'a+b^c^-d'],
            [true, '1+ln(a)+log10(b)'],
            [true, 'asin(w t)'],
            [true, 'a sin(w t)+ b cos(w t)'],
            [true, '2 (3) a sin(b)^c - (sin(x+y)+x^y)^-sin(z)c tan(z)(x^2)'],
            [false, 'a-'],
            [false, '*a'],
            [true, 'a**b'],
            [false, 'a+^c+f'],
            [false, 'a+b^^+f'],
            [false, 'a+(b^c)^+f'],
            [false, 'a+((b^c)^d+f'],
            [false, 'a+(b^c+f'],
            [false, 'a+b^c)+f'],
            [false, 'a+b^(c+f'],
            [false, 'a+b)^c+f'],
            [false, 'pi()'],
            [false, 'sin 3'],
            [false, '1+sin*(3)+2'],
            [false, '1+sin^(3)+2'],
            [false, 'a sin w t'],
            [false, '1==2?3:4'],
            [false, 'a=b'],
            [false, '3&4'],
            [false, '3==4'],
            [false, '3&&4'],
            [false, '3!'],
            [false, '@']];
        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v,
            'a={1:10}; b={1:10}; c={1:10}; d={1:10}; e={1:10}; f={1:10}; t={1:10};' .
            ' u={1:10}; v={1:10}; w={1:10}; x={1:10}; y={1:10}; z={1:10};');
        foreach ($testcases as $testcase) {
            try {
                $result = $qv->compute_algebraic_formula_difference($v, [$testcase[1]], [$testcase[1]], 100);
            } catch (Exception $e) {
                $result = null;
            }
            $eval = $result !== null;
            $this->assertEquals($testcase[0], $eval);
        }

        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v, 'x={-10:11:1}; y={-10:-5, 6:11};');
        $result = $qv->compute_algebraic_formula_difference($v, ['x', '1+x+y+3', '(1+sqrt(x))^2'], ['0', '2+x+y+2', '1+x'], 100);
        $this->assertEquals($result[1], 0);
        $this->assertEquals($result[2], INF);
        $result = $qv->compute_algebraic_formula_difference($v, ['x', '(x+y)^2'], ['0', 'x^2+2*x*y+y^2'], 100);
        $this->assertEquals($result[1], 0);
    }

    /**
     * Test 8: Split formula unit.
     */
    public function test_split_formula_unit() {
        $qv = new qtype_formulas_variables;
        $testcases = [
            // Check for simple number and unit.
            ['.3', ['.3', '']],
            ['3.1', ['3.1', '']],
            ['3.1e-10', ['3.1e-10', '']],
            ['3m', ['3', 'm']],
            ['3kg m/s', ['3', 'kg m/s']],
            ['3.m/s',  ['3.', 'm/s']],
            ['3.e-10m/s',  ['3.e-10', 'm/s']],
            ['- 3m/s', ['- 3', 'm/s']],
            ['3 e10 m/s', ['3 ', 'e10 m/s']],
            ['3e 10 m/s', ['3', 'e 10 m/s']],
            ['3e8e8 m/s', ['3e8', 'e8 m/s']],
            ['3+10*4 m/s', ['3+10*4 ', 'm/s']],
            ['3+10^4 m/s', ['3+10^4 ', 'm/s']],
            ['sin(3) m/s', ['sin(3) ', 'm/s']],
            ['3+exp(4) m/s', ['3+exp(4) ', 'm/s']],
            ['3*4*5 m/s', ['3*4*5 ', 'm/s']],
            ['3 4 5 m/s', ['3 4 5 ', 'm/s']],
            ['m/s', ['', 'm/s']],
            ['#', ['', '#']],

            // Numeric and unit.
            ['3+4 5+10^4kg m/s', ['3+4 5+10^4', 'kg m/s']],
            ['sin(3)kg m/s', ['sin(3)', 'kg m/s']],

            // Numerical formula and unit.
            ['3.1e-10kg m/s', ['3.1e-10', 'kg m/s']],
            ['-3kg m/s', ['-3', 'kg m/s']],
            ['- 3kg m/s', ['- 3', 'kg m/s']],
            ['3e', ['3', 'e']],
            ['3e8', ['3e8', '']],
            ['3e8e', ['3e8', 'e']],
            ['3+4 5+10^4kg m/s', ['3+4 5+10^4', 'kg m/s']],
            ['sin(3)kg m/s', ['sin(3)', 'kg m/s']],
            ['3*4*5 kg m/s', ['3*4*5 ', 'kg m/s']],
            ['3 4 5 kg m/s', ['3 4 5 ', 'kg m/s']],
            ['3e8(4.e8+2)(.5e8/2)5kg m/s', ['3e8(4.e8+2)(.5e8/2)5', 'kg m/s']],
            ['3+exp(4+5)^sin(6+7)kg m/s', ['3+exp(4+5)^sin(6+7)', 'kg m/s']],
            ['3+exp(4+5)^-sin(6+7)kg m/s', ['3+exp(4+5)^-sin(6+7)', 'kg m/s']],
            ['3exp^2', ['3', 'exp^2']], // Note the unit is exp to the power 2.
            ['3 e8', ['3 ', 'e8']],
            ['3e 8', ['3', 'e 8']],
            ['3e8e8', ['3e8', 'e8']],
            ['3e8e8e8', ['3e8', 'e8e8']],
            ['3+exp(4+5).m/s', ['3+exp(4+5)', '.m/s']],
            ['3+(4.m/s', ['3+(4.', 'm/s']],
            ['3+4.)m/s', ['3+4.)', 'm/s']],
            ['3 m^', ['3 ', 'm^']],
            ['3 m/', ['3 ', 'm/']],
            ['3 /s', ['3 /', 's']],
            ['3 m+s', ['3 ', 'm+s']],
            ['1==2?3:4', ['1', '==2?3:4']],
            ['a=b', ['', 'a=b']],
            ['3&4', ['3', '&4']],
            ['3==4', ['3', '==4']],
            ['3&&4', ['3', '&&4']],
            ['3!', ['3', '!']],
            ['@', ['', '@']]];
        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v,
            'a={1:10}; b={1:10}; c={1:10}; d={1:10}; e={1:10}; f={1:10}; t={1:10};' .
            'u={1:10}; v={1:10}; w={1:10}; x={1:10}; y={1:10}; z={1:10};');
        foreach ($testcases as $testcase) {
            $result = $qv->split_formula_unit($testcase[0]);
            $this->assertEquals($testcase[1][0], $result[0]);
            $this->assertEquals($testcase[1][1], $result[1]);
        }
    }

    /**
     * Test 8: Sigfig function.
     */
    public function test_sigfig() {
        $number = .012345;
        $this->assertSame(sigfig($number, 3), '0.0123');
        $this->assertSame(sigfig($number, 4), '0.01235');
        $this->assertSame(sigfig($number, 6), '0.0123450');
        $number = -.012345;
        $this->assertSame(sigfig($number, 3), '-0.0123');
        $this->assertSame(sigfig($number, 4), '-0.01235');
        $this->assertSame(sigfig($number, 6), '-0.0123450');
        $number = 123.45;
        $this->assertSame(sigfig($number, 2), '120');
        $this->assertSame(sigfig($number, 4), '123.5');
        $this->assertSame(sigfig($number, 6), '123.450');
        $number = -123.45;
        $this->assertSame(sigfig($number, 2), '-120');
        $this->assertSame(sigfig($number, 4), '-123.5');
        $this->assertSame(sigfig($number, 6), '-123.450');
        $number = .005;
        $this->assertSame(sigfig($number, 1), '0.005');
        $this->assertSame(sigfig($number, 2), '0.0050');
        $this->assertSame(sigfig($number, 3), '0.00500');
        $number = -.005;
        $this->assertSame(sigfig($number, 1), '-0.005');
        $this->assertSame(sigfig($number, 2), '-0.0050');
        $this->assertSame(sigfig($number, 3), '-0.00500');
    }
}
