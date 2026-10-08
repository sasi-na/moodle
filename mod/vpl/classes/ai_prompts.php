<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prompt templates for the browser-facing AI features.
 *
 * These used to be assembled in JavaScript and sent to Google from the
 * student's browser. They live here so the proxy decides what is asked, and
 * the caller only supplies the material to ask about.
 *
 * @package    mod_vpl
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vpl;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the prompts used by the AI hints, grading and test case features.
 */
class ai_prompts {

    /**
     * Coding hints for a student working on a problem.
     *
     * @param string $description problem description, already stripped of markup
     * @return string
     */
    public static function hints(string $description): string {
        return 'You are a programming tutor. Based on this problem, provide TECHNICAL coding '
            . "hints with SPECIFIC code patterns.\n\n"
            . 'Problem: ' . $description . "\n\n"
            . "Provide 4-6 TECHNICAL hints including:\n"
            . "1. Specific function/method signatures to use (e.g., \"Use split() method\", \"Declare int array[]\")\n"
            . "2. Exact data structures needed (e.g., \"Create HashMap<String, Integer>\", \"Use ArrayList\")\n"
            . "3. Key loop patterns or algorithms (e.g., \"Use for loop: for(int i=0; i<n; i++)\")\n"
            . "4. Critical conditional logic patterns (e.g., \"Check: if(x > 0 && x < 10)\")\n"
            . "5. Variable declarations needed (e.g., \"int count = 0;\", \"String result = \\\"\\\";\")\n"
            . "6. Important method calls or operations (e.g., \"Call .length() method\", \"Use parseInt()\")\n\n"
            . 'Format: Numbered list with code syntax in backticks. Be SPECIFIC with actual code '
            . 'patterns, not general theory.';
    }

    /**
     * Ten VPL-format test cases for a problem.
     *
     * @param string $description problem description, already stripped of markup
     * @return string
     */
    public static function testcases(string $description): string {
        return 'Based on this programming problem, generate exactly 10 test cases in the '
            . "VPL test case format.\n\n"
            . 'Problem: ' . $description . "\n\n"
            . "Format each test case as follows:\n"
            . "case=Test case N\n"
            . "input=<input values>\n"
            . "output=<expected output>\n\n"
            . "Requirements:\n"
            . "1. Generate EXACTLY 10 test cases\n"
            . "2. Include edge cases (empty input, zero, negative numbers, etc.)\n"
            . "3. Include normal cases with various inputs\n"
            . "4. Each test case must follow the exact format shown above\n"
            . "5. Separate test cases with a blank line\n"
            . "6. For numeric inputs, use space-separated values\n"
            . "7. Make outputs match the expected format from the problem\n\n"
            . 'Output ONLY the test cases in the format specified, nothing else.';
    }

    /**
     * Narrative grading feedback shown in the editor, with no machine-read grade.
     *
     * @param string $code
     * @param string $compilation
     * @param string $tests
     * @param bool $ispractice
     * @param int $maxgrade the activity's maximum, not a figure supplied by the browser
     * @return string
     */
    public static function ide_grading(string $code, string $compilation, string $tests,
            bool $ispractice, int $maxgrade): string {
        if ($ispractice) {
            return "Please grade this programming assignment and provide a numeric grade out of {$maxgrade} Marks.

**GRADING RULES for PRACTICE:**
1. If the student has submitted any code (at least one line), give at least 1 mark.
2. If at least one test case has passed, give the FULL marks ({$maxgrade} Marks).

**STUDENT CODE:**
```
{$code}
```

**COMPILATION RESULTS:**
{$compilation}

**TEST CASE RESULTS:**
{$tests}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness and provide feedback.
2. If any code is present, ensure the final grade is at least 1 mark.
3. If at least one test case passed in the TEST CASE RESULTS, the final grade MUST be {$maxgrade} Marks.
4. Provide specific feedback for the student.
5. **IMPORTANT: End your response with a clear summary: \"Final Marks: X/{$maxgrade}\" where X is the numeric grade.**

Please provide detailed feedback followed by the final numeric marks.";
        }

        $logic = (int) floor($maxgrade * 0.7);
        $exec = (int) ceil($maxgrade * 0.3);

        return "Please grade this programming assignment based on the following criteria:

**GRADING CRITERIA (Total: {$maxgrade} Marks)**

1. **Logic and Correctness ({$logic} Marks)**
   - Code completeness and implementation
   - Memory allocation and management
   - Code readability and structure
   - Performance and efficiency

2. **Execution and Output ({$exec} Marks)**
   - Correct output
   - Partially correct output scores proportionally
   - Incorrect output or no execution scores zero

**STUDENT CODE:**
```
{$code}
```

**COMPILATION RESULTS:**
{$compilation}

**TEST CASE RESULTS:**
{$tests}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness, completeness, memory management, readability, and performance
2. Check compilation and test results for execution quality
3. Provide specific marks for each criteria with detailed justification
4. Give constructive feedback for improvement
5. Format your response with clear sections for marks and feedback

Please provide a detailed grading breakdown with specific marks and reasoning.";
    }

    /**
     * Grading feedback for the grade form, ending in a grade the form can read.
     *
     * Shares its wording with ai_grader so the figure a grader is offered here
     * matches what background grading would have written.
     *
     * @param string $code
     * @param string $compilation
     * @param string $tests
     * @param bool $ispractice
     * @param int $maxgrade the activity's maximum, not a figure supplied by the browser
     * @return string
     */
    public static function form_grading(string $code, string $compilation, string $tests,
            bool $ispractice, int $maxgrade): string {
        if ($ispractice) {
            return "Please grade this programming assignment and provide a numeric grade out of {$maxgrade} points.

**GRADING RULES for PRACTICE:**
1. If the student has submitted any code (at least one line), give at least 1 mark.
2. If at least one test case has passed, give the FULL marks ({$maxgrade}).

**STUDENT CODE:**
{$code}

**COMPILATION RESULTS:**
{$compilation}

**TEST CASE RESULTS:**
{$tests}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness and provide feedback.
2. If any code is present, ensure the final grade is at least 1.
3. If at least one test case passed in the TEST CASE RESULTS, the final grade MUST be {$maxgrade}.
4. Provide specific feedback for the student.
5. **IMPORTANT: End your response with \"FINAL GRADE: X/{$maxgrade}\" where X is the numeric grade based on the above rules**

Please provide detailed feedback followed by the final numeric grade.";
        }

        $logic = (int) floor($maxgrade * 0.7);
        $exec = (int) ceil($maxgrade * 0.3);

        return "Please grade this programming assignment and provide a numeric grade out of {$maxgrade} points.

**GRADING CRITERIA (Total: {$maxgrade} Points)**

1. **Logic and Correctness ({$logic} Points)**
   - Code completeness and implementation
   - Memory allocation and management
   - Code readability and structure
   - Performance and efficiency

2. **Execution and Output ({$exec} Points)**
   - Correct output and functionality
   - Handles edge cases properly
   - No runtime errors

**STUDENT CODE:**
{$code}

**COMPILATION RESULTS:**
{$compilation}

**TEST CASE RESULTS:**
{$tests}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness, completeness, readability, and performance
2. Check compilation and test results for execution quality
3. Provide specific feedback for each criteria
4. Give constructive suggestions for improvement
5. **IMPORTANT: End your response with \"FINAL GRADE: X/{$maxgrade}\" where X is the numeric grade**

Please provide detailed feedback followed by the final numeric grade.";
    }
}
