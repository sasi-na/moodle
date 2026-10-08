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

declare(strict_types=1);

namespace local_cagrades;

defined('MOODLE_INTERNAL') || die();

/**
 * The AI Skills for Future marking scheme.
 *
 * Four components of equal weight, adding to 100:
 *
 *   CA 1, CA 2, CA 3        25 each
 *   Reflective Assessment   25
 *
 * The catch is that the CA quizzes are built out of 30 in the LMS, not 25, so each CA has to be
 * weighted down to its 25 before the four are added: a learner scoring 27 out of 30 contributes
 * 22.5. The quizzes themselves are deliberately left alone at /30 - rescaling them would rewrite
 * marks that learners have already been shown.
 *
 * Kept apart from {@see ca_items}, which holds an entirely different rule for the taught courses
 * (best two of three, out of 30). Two schemes that both talk about "CA" are exactly the sort of thing
 * that should not share a class.
 *
 * The Reflective Assessment is not in this course at all - it runs as separate faculty-wise courses -
 * so this scheme can only ever be reported, never computed by the gradebook. See the entity in
 * reportbuilder/local/entities/ca_grade.php.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiskills_scheme {

    /** @var float What each of the four components is worth. */
    public const COMPONENT = 25.0;

    /**
     * One CA's contribution: its mark weighted from whatever it was sat out of down to the component.
     *
     * @param float|null $grade the learner's mark, null when unattempted
     * @param float|null $max what the CA was marked out of
     * @param float $component what the CA is worth in this scheme
     * @return float|null null when there is no mark, or no maximum to weight against
     */
    public static function weighted(?float $grade, ?float $max, float $component = self::COMPONENT): ?float {
        if ($grade === null || $max === null || $max <= 0) {
            return null;
        }

        return ($grade / $max) * $component;
    }

    /**
     * The four-component total.
     *
     * Each CA is weighted to the component mark first; the Reflective Assessment already is one, so it
     * is added as it stands. Null - rather than zero - when the learner has none of the four, so that
     * somebody simply not assessed yet is not reported as having scored nothing.
     *
     * @param array $grades CA number => mark or null
     * @param array $maxima CA number => maximum
     * @param float|null $ra the Reflective Assessment mark
     * @return float|null
     */
    public static function total(array $grades, array $maxima, ?float $ra): ?float {
        $total = 0.0;
        $any = false;

        foreach ($maxima as $canum => $max) {
            $weighted = self::weighted($grades[$canum] ?? null, $max === null ? null : (float) $max);
            if ($weighted !== null) {
                $any = true;
                $total += $weighted;
            }
        }
        if ($ra !== null) {
            $any = true;
            $total += $ra;
        }

        return $any ? $total : null;
    }

    /**
     * What that total is out of: one component per CA the course runs, plus the Reflective Assessment.
     *
     * Driven by the course's own structure rather than hard-coded to 100, so a course carrying two CAs
     * reports out of 75 instead of quietly claiming 100.
     *
     * @param array $maxima CA number => maximum (a null or zero maximum means the course has no such CA)
     * @param float|null $ramax the Reflective Assessment maximum, per course
     * @return float|null
     */
    public static function total_maximum(array $maxima, ?float $ramax): ?float {
        $total = 0.0;
        $any = false;

        foreach ($maxima as $max) {
            if ($max !== null && (float) $max > 0) {
                $any = true;
                $total += self::COMPONENT;
            }
        }
        if ($ramax !== null && $ramax > 0) {
            $any = true;
            $total += $ramax;
        }

        return $any ? $total : null;
    }
}
