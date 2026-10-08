<?php

namespace local_journalgrader;

defined('MOODLE_INTERNAL') || die();

class grader {

    /**
     * Load assignment-specific answer key JSON.
     */
    public static function load_answer_key(
        int $cmid
    ): ?array {

        $context =
            \context_module::instance($cmid);

        $fs =
            get_file_storage();

        $files =
            $fs->get_area_files(

                $context->id,

                'local_journalgrader',

                'answerkey',

                0,

                'id',

                false
            );

        foreach ($files as $file) {

            $json =
                json_decode(
                    $file->get_content(),
                    true
                );

            if (
                json_last_error()
                === JSON_ERROR_NONE
            ) {

                return $json;
            }
        }

        return null;
    }

    /**
     * Main grading dispatcher.
     */
    public static function grade_csv(
        string $csvpath,
        int $cmid
    ): array {

        $answerkey =
            self::load_answer_key(
                $cmid
            );

        if (!$answerkey) {

            return [
                'grade' => 0,
                'feedback' =>
                    'Answer key missing'
            ];
        }

        $type =
            strtolower(
                $answerkey['type']
                ?? 'journal'
            );

        switch ($type) {

            case 'ledger':

                return self::grade_ledger(
                    $csvpath,
                    $answerkey
                );

            case 'profit_and_loss':

                return self::grade_profit_and_loss(
                    $csvpath,
                    $answerkey
                );

            case 'balance_sheet':

                return self::grade_balance_sheet(
                    $csvpath,
                    $answerkey
                );

            case 'journal':

            default:

                return self::grade_journal(
                    $csvpath,
                    $answerkey
                );
        }
    }

    /**
     * Grade journal-entry format.
     */
    private static function grade_journal(
        string $csvpath,
        array $answerkey
    ): array {

        $entries =
            $answerkey['entries']
            ?? [];

        $totalmarks =
            (float) (
                $answerkey['totalmarks']
                ?? 10
            );

        $rows =
            self::parse_journal_csv(
                $csvpath
            );

        if ($rows === false) {

            return [
                'grade' => 0,
                'feedback' =>
                    'Invalid journal CSV'
            ];
        }

        $score = 0;

        $usedrows = [];

        foreach ($entries as $expected) {

            foreach ($rows as $index => $row) {

                if (
                    in_array(
                        $index,
                        $usedrows,
                        true
                    )
                ) {
                    continue;
                }

                $keyword =
                    strtolower(
                        trim(
                            $expected['keyword']
                            ?? ''
                        )
                    );

                $expecteddr =
                    (float) (
                        $expected['dr']
                        ?? 0
                    );

                $expectedcr =
                    (float) (
                        $expected['cr']
                        ?? 0
                    );

                $match =

                    strpos(
                        $row['particulars'],
                        $keyword
                    ) !== false

                    &&

                    abs(
                        $row['dr']
                        - $expecteddr
                    ) < 0.01

                    &&

                    abs(
                        $row['cr']
                        - $expectedcr
                    ) < 0.01;

                if ($match) {

                    $score++;

                    $usedrows[] = $index;

                    break;
                }
            }
        }

        return self::build_result(
            $score,
            count($entries),
            $totalmarks
        );
    }

    /**
     * Grade ledger format.
     */
    private static function grade_ledger(
        string $csvpath,
        array $answerkey
    ): array {

        $entries =
            $answerkey['entries']
            ?? [];

        $totalmarks =
            (float) (
                $answerkey['totalmarks']
                ?? 10
            );

        $rows =
            self::parse_ledger_csv(
                $csvpath
            );

        if ($rows === false) {

            return [
                'grade' => 0,
                'feedback' =>
                    'Invalid ledger CSV'
            ];
        }

        $score = 0;

        $totalexpected = 0;

        foreach (
            ['debit', 'credit']
            as $side
        ) {

            $expectedrows =
                $entries[$side]
                ?? [];

            $totalexpected +=
                count($expectedrows);

            $usedrows = [];

            foreach (
                $expectedrows
                as $expected
            ) {

                foreach (
                    $rows[$side]
                    as $index => $row
                ) {

                    if (
                        in_array(
                            $index,
                            $usedrows,
                            true
                        )
                    ) {
                        continue;
                    }

                    $keyword =
                        strtolower(
                            trim(
                                $expected['keyword']
                                ?? ''
                            )
                        );

                    $expectedamount =
                        (float) (
                            $expected['amount']
                            ?? 0
                        );

                    $match =

                        strpos(
                            $row['particulars'],
                            $keyword
                        ) !== false

                        &&

                        abs(
                            $row['amount']
                            - $expectedamount
                        ) < 0.01;

                    if ($match) {

                        $score++;

                        $usedrows[] = $index;

                        break;
                    }
                }
            }
        }

        if (
            !empty(
                $answerkey['validate_totals']
            )
            &&
            !empty(
                $answerkey['totals']
            )
        ) {

            $expecteddebit =
                (float) (
                    $answerkey['totals']['debit']
                    ?? 0
                );

            $expectedcredit =
                (float) (
                    $answerkey['totals']['credit']
                    ?? 0
                );

            $actualdebit = 0;

            foreach (
                $rows['debit']
                as $row
            ) {

                $actualdebit +=
                    $row['amount'];
            }

            $actualcredit = 0;

            foreach (
                $rows['credit']
                as $row
            ) {

                $actualcredit +=
                    $row['amount'];
            }

            $totalsmatch =

                abs(
                    $actualdebit
                    - $expecteddebit
                ) < 0.01

                &&

                abs(
                    $actualcredit
                    - $expectedcredit
                ) < 0.01;

            if ($totalsmatch) {

                $score++;

                $totalexpected++;
            }
        }

        return self::build_result(
            $score,
            $totalexpected,
            $totalmarks
        );
    }

    /**
     * Grade profit & loss format.
     */
    /**
 * Grade Profit & Loss format.
 */
    private static function grade_profit_and_loss(
        string $csvpath,
        array $answerkey
    ): array {

        $entries =
            $answerkey['entries']
            ?? [];

        $totalmarks =
            (float) (
                $answerkey['totalmarks']
                ?? 10
            );

        $rows =
            self::parse_profit_and_loss_csv(
                $csvpath
            );

        if ($rows === false) {

            return [
                'grade' => 0,
                'feedback' =>
                    'Invalid profit and loss CSV'
            ];
        }

        $score = 0;

        $usedrows = [];

        foreach ($entries as $expected) {

            $keyword =
                strtolower(
                    trim(
                        $expected['keyword']
                        ?? ''
                    )
                );

            if ($keyword === '') {
                continue;
            }

            $expectedamount =
                (float) (
                    $expected['amount']
                    ?? 0
                );

            foreach ($rows as $index => $row) {

                if (
                    in_array(
                        $index,
                        $usedrows,
                        true
                    )
                ) {
                    continue;
                }

                /*
                * Flexible keyword matching.
                */

                $keywordmatch =

                    strpos(
                        $row['particulars'],
                        $keyword
                    ) !== false;

                /*
                * Numeric matching.
                */

                $amountmatch =

                    abs(
                        $row['amount']
                        - $expectedamount
                    ) < 0.01;

                if (
                    $keywordmatch
                    &&
                    $amountmatch
                ) {

                    $score++;

                    $usedrows[] = $index;

                    break;
                }
            }
        }

        return self::build_result(
            $score,
            count($entries),
            $totalmarks
        );
    }

    /**
     * Grade balance sheet format.
     */
    private static function grade_balance_sheet(
        string $csvpath,
        array $answerkey
    ): array {

        $entries =
            $answerkey['entries']
            ?? [];

        $totalmarks =
            (float) (
                $answerkey['totalmarks']
                ?? 10
            );

        $rows =
            self::parse_balance_sheet_csv(
                $csvpath
            );

        if ($rows === false) {

            return [
                'grade' => 0,
                'feedback' =>
                    'Invalid balance sheet CSV'
            ];
        }

        $score = 0;

        $usedrows = [];

        foreach ($entries as $expected) {

            $keyword =
                self::normalize_balance_sheet_text(
                    $expected['keyword']
                    ?? ''
                );

                if ($keyword === '') {
                    continue;
                }

            $expectedamount =
                (float) (
                    $expected['amount']
                    ?? 0
                );

            foreach ($rows as $index => $row) {

                if (
                    in_array(
                        $index,
                        $usedrows,
                        true
                    )
                ) {
                    continue;
                }

                $particulars =
                    self::normalize_balance_sheet_text(
                        $row['particulars']
                        ?? ''
                    );

                if ($particulars === '') {
                    continue;
                }

                $amountmatch =
                    abs(
                        $row['amount']
                        - $expectedamount
                    ) < 0.01;

                if (!$amountmatch) {
                    continue;
                }

                /*
                * Flexible keyword matching.
                */

                $keywordmatch =

                    strpos($particulars, $keyword) !== false

                    ||

                    strpos($keyword, $particulars) !== false

                    ||

                    levenshtein($particulars, $keyword) <= 3;

                if ($keywordmatch) {

                    $score++;

                    $usedrows[] = $index;

                    break;
                }
            }
        }

        return self::build_result(
            $score,
            count($entries),
            $totalmarks
        );
    }



    /**
     * Build grading response.
     */
    private static function build_result(
        int $score,
        int $total,
        float $marks
    ): array {

        if ($total <= 0) {

            return [
                'grade' => 0,
                'feedback' =>
                    'No expected entries'
            ];
        }

        return [

            'grade' => round(
                (
                    $score / $total
                ) * $marks,
                2
            ),

            'feedback' =>

                'Matched '

                .

                $score

                .

                '/'

                .

                $total
        ];
    }

    /**
     * Parse journal CSV.
     */
    private static function parse_journal_csv(
        string $path
    ) {

        $handle =
            fopen($path, 'r');

        if (!$handle) {
            return false;
        }

        $rows = [];

        while (
            ($data = fgetcsv($handle))
            !== false
        ) {

            $nonempty =
                array_filter(
                    $data,
                    function($v) {
                        return trim($v) !== '';
                    }
                );

            if (empty($nonempty)) {
                continue;
            }

            $data =
                array_map(
                    function($v) {

                        return trim(
                            str_replace(
                                ["\xC2\xA0"],
                                ' ',
                                $v
                            )
                        );
                    },
                    $data
                );

            $firsttext = '';

            foreach ($data as $cell) {

                if ($cell !== '') {

                    $firsttext = $cell;
                    break;
                }
            }

            if (
                strpos($firsttext, '(') === 0
            ) {
                continue;
            }

            $particulars = '';

            foreach ($data as $cell) {

                $lower =
                    strtolower($cell);

                if (

                    strpos($lower, 'a/c') !== false

                    ||

                    strpos($lower, 'capital') !== false

                    ||

                    strpos($lower, 'bank') !== false

                    ||

                    strpos($lower, 'cash') !== false

                    ||

                    strpos($lower, 'sales') !== false

                    ||

                    strpos($lower, 'purchase') !== false

                    ||

                    strpos($lower, 'discount') !== false

                    ||

                    strpos($lower, 'share') !== false

                    ||

                    strpos($lower, 'profit') !== false

                    ||

                    strpos($lower, 'drawings') !== false

                    ||

                    strpos($lower, 'rent') !== false

                    ||

                    strpos($lower, 'salary') !== false

                    ||

                    strpos($lower, 'insurance') !== false

                    ||

                    strpos($lower, 'machinery') !== false

                    ||

                    strpos($lower, 'depreciation') !== false

                    ||

                    strpos($lower, 'bad debts') !== false

                    ||

                    strpos($lower, 'provision') !== false

                ) {

                    $particulars = $lower;
                    break;
                }
            }

            if ($particulars === '') {
                continue;
            }

            $numbers = [];

            foreach ($data as $cell) {

                $clean =
                    str_replace(
                        [',', '₹', ' '],
                        '',
                        $cell
                    );

                if (
                    is_numeric($clean)
                ) {

                    $numbers[] =
                        (float)$clean;
                }
            }

            $dr = 0;
            $cr = 0;

            if (
                strpos(
                    strtolower($particulars),
                    'to '
                ) === 0
            ) {

                $cr =
                    $numbers[0]
                    ?? 0;

            } else {

                $dr =
                    $numbers[0]
                    ?? 0;
            }

            $particulars =
                preg_replace(
                    '/^to\s+/i',
                    '',
                    $particulars
                );

            $rows[] = [

                'particulars' =>
                    strtolower(
                        trim($particulars)
                    ),

                'dr' => $dr,

                'cr' => $cr
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse ledger CSV.
     */
    private static function parse_ledger_csv(
        string $path
    ) {

        $handle =
            fopen($path, 'r');

        if (!$handle) {
            return false;
        }

        $rows = [

            'debit' => [],
            'credit' => []
        ];

        while (
            ($data = fgetcsv($handle))
            !== false
        ) {

            $nonempty =
                array_filter(
                    $data,
                    function($v) {
                        return trim($v) !== '';
                    }
                );

            if (empty($nonempty)) {
                continue;
            }

            $data =
                array_map(
                    function($v) {

                        return trim(
                            str_replace(
                                ["\xC2\xA0"],
                                ' ',
                                $v
                            )
                        );
                    },
                    $data
                );

            $firsttext = '';

            foreach ($data as $cell) {

                if ($cell !== '') {

                    $firsttext = $cell;
                    break;
                }
            }

            if (
                strpos($firsttext, '(') === 0
            ) {
                continue;
            }

            $accounts = [];

            foreach ($data as $index => $cell) {

                $lower =
                    strtolower($cell);

                if (

                    strpos($lower, 'a/c') !== false

                    ||

                    strpos($lower, 'capital') !== false

                    ||

                    strpos($lower, 'bank') !== false

                    ||

                    strpos($lower, 'cash') !== false

                    ||

                    strpos($lower, 'sales') !== false

                    ||

                    strpos($lower, 'purchase') !== false

                    ||

                    strpos($lower, 'discount') !== false

                    ||

                    strpos($lower, 'share') !== false

                    ||

                    strpos($lower, 'profit') !== false

                    ||

                    strpos($lower, 'drawings') !== false

                    ||

                    strpos($lower, 'rent') !== false

                    ||

                    strpos($lower, 'salary') !== false

                    ||

                    strpos($lower, 'insurance') !== false

                    ||

                    strpos($lower, 'machinery') !== false

                    ||

                    strpos($lower, 'depreciation') !== false

                    ||

                    strpos($lower, 'bad debts') !== false

                    ||

                    strpos($lower, 'provision') !== false

                    ||

                    strpos($lower, 'balance') !== false

                    ||

                    strpos($lower, 'trading') !== false

                ) {

                    $accounts[] = [
                        'index' => $index,
                        'text' => $lower
                    ];
                }
            }

            if (empty($accounts)) {
                continue;
            }

            $numbers = [];

            foreach ($data as $cell) {

                $clean =
                    str_replace(
                        [',', '₹', ' '],
                        '',
                        $cell
                    );

                if (
                    is_numeric($clean)
                ) {

                    $numbers[] =
                        (float)$clean;
                }
            }

            if (empty($numbers)) {
                continue;
            }

            if (isset($accounts[0])) {

                $rows['debit'][] = [

                    'particulars' =>

                        trim(
                            preg_replace(
                                '/^to\s+/i',
                                '',
                                $accounts[0]['text']
                            )
                        ),

                    'amount' =>
                        $numbers[0]
                ];
            }

            if (
                isset($accounts[1])
                &&
                isset($numbers[1])
            ) {

                $rows['credit'][] = [

                    'particulars' =>

                        trim(
                            preg_replace(
                                '/^to\s+/i',
                                '',
                                $accounts[1]['text']
                            )
                        ),

                    'amount' =>
                        $numbers[1]
                ];
            }

            if (
                count($accounts) === 1
                &&
                count($numbers) === 1
            ) {

                $text =
                    $accounts[0]['text'];

                $amount =
                    $numbers[0];

                if (
                    strpos($text, 'to ') === 0
                ) {

                    $rows['credit'][] = [

                        'particulars' =>

                            trim(
                                preg_replace(
                                    '/^to\s+/i',
                                    '',
                                    $text
                                )
                            ),

                        'amount' => $amount
                    ];

                } else {

                    $rows['debit'][] = [

                        'particulars' =>
                            trim($text),

                        'amount' => $amount
                    ];
                }
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse profit & loss CSV.
     */
    /**
 * Parse Profit & Loss CSV.
 *
 * Expected:
 * Particulars | Note No | Current Year | Previous Year
 */
private static function parse_profit_and_loss_csv(
    string $path
    ) {

        $handle =
            fopen($path, 'r');

        if (!$handle) {
            return false;
        }

        $rows = [];

        /*
        * Scan for header row (tolerates company name / title rows above it).
        */

        $particularsindex = null;

        $currentyearindex = null;

        $headerfound = false;

        while (($header = fgetcsv($handle)) !== false) {

            foreach ($header as $index => $column) {

                $column =
                    strtolower(
                        trim(
                            str_replace(
                                ["\xEF\xBB\xBF", "\xC2\xA0"],
                                ' ',
                                $column
                            )
                        )
                    );

                if (
                    strpos(
                        $column,
                        'particular'
                    ) !== false
                ) {

                    $particularsindex = $index;
                }

                if (
                    strpos(
                        $column,
                        'current'
                    ) !== false
                ) {

                    $currentyearindex = $index;
                }
            }

            if (
                $particularsindex !== null
                &&
                $currentyearindex !== null
            ) {

                $headerfound = true;
                break;
            }
        }

        if (!$headerfound) {

            fclose($handle);

            return false;
        }

        /*
        * Parse rows.
        */

        while (
            ($data = fgetcsv($handle))
            !== false
        ) {

            /*
            * Skip empty rows.
            */

            $nonempty =
                array_filter(
                    $data,
                    function($v) {
                        return trim($v) !== '';
                    }
                );

            if (empty($nonempty)) {
                continue;
            }

            /*
            * Normalize cells.
            */

            $data =
                array_map(
                    function($v) {

                        return trim(
                            strtolower(
                                str_replace(
                                    ["\xC2\xA0"],
                                    ' ',
                                    $v
                                )
                            )
                        );
                    },
                    $data
                );

            /*
            * Get particulars.
            */

            $particulars =
                trim(
                    $data[$particularsindex]
                    ?? ''
                );

            if ($particulars === '') {
                continue;
            }

            /*
            * Skip section headings.
            */

            if (

                $particulars === 'i. income'

                ||

                $particulars === 'ii. expenses'

            ) {
                continue;
            }

            /*
            * Get current year amount.
            */

            $rawamount =
                trim(
                    $data[$currentyearindex]
                    ?? ''
                );

            /*
            * Handle dash values.
            */

            if (

                $rawamount === '—'

                ||

                $rawamount === '-'

                ||

                $rawamount === ''

            ) {

                $amount = 0;

            } else {

                $clean =
                    str_replace(
                        [',', '₹', ' '],
                        '',
                        $rawamount
                    );

                $clean =
                    str_replace(
                        ['(', ')'],
                        ['-', ''],
                        $clean
                    );

                $amount =
                    is_numeric($clean)
                    ? (float)$clean
                    : 0;
            }

            $rows[] = [

                'particulars' =>
                    $particulars,

                'amount' =>
                    $amount
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse balance sheet CSV.
     */
    private static function parse_balance_sheet_csv(
        string $path
    ) {

        $handle = fopen($path, 'r');

        if (!$handle) {
            return false;
        }

        $rows = [];

        $headerfound = false;

        $particularsindex = null;

        $currentyearindex = null;

        /*
        * Find actual header row.
        */

        while (($data = fgetcsv($handle)) !== false) {

            $normalized = array_map(
                function($v) {

                    return strtolower(
                        trim(
                            str_replace(
                                [
                                    "\xEF\xBB\xBF",
                                    "\xC2\xA0"
                                ],
                                ' ',
                                $v
                            )
                        )
                    );
                },
                $data
            );

            foreach ($normalized as $index => $column) {

                if (
                    strpos($column, 'particular')
                    !== false
                ) {

                    $particularsindex = $index;
                }

                if (
                    strpos($column, 'current')
                    !== false
                ) {

                    $currentyearindex = $index;
                }
            }

            if (
                $particularsindex !== null
                &&
                $currentyearindex !== null
            ) {

                $headerfound = true;
                break;
            }
        }

        if (!$headerfound) {

            fclose($handle);

            return false;
        }

        /*
        * Parse actual rows.
        */

        while (($data = fgetcsv($handle)) !== false) {

            if (
                empty(
                    array_filter(
                        $data,
                        function($v) {
                            return trim($v) !== '';
                        }
                    )
                )
            ) {
                continue;
            }

            $particulars =
                strtolower(
                    trim(
                        str_replace(
                            [
                                "\xEF\xBB\xBF",
                                "\xC2\xA0"
                            ],
                            ' ',
                            $data[$particularsindex]
                            ?? ''
                        )
                    )
                );

            if ($particulars === '') {
                continue;
            }

            /*
            * Ignore blank section headings (no amounts, skipped by amount check anyway).
            */

            /*
            * Remove leading numbering only.
            */

            $particulars =
                preg_replace(
                    '/^\s*\(?[a-z0-9]{1,3}\)?\.?\s+/i',
                    '',
                    $particulars
                );

            /*
            * Keep meaningful brackets like (net).
            * Remove only trailing note references like:
            * share capital 1
            */

            $particulars =
                preg_replace(
                    '/\s+\d+$/',
                    '',
                    $particulars
                );

            /*
            * Normalize ampersand.
            */

            $particulars =
                str_replace(
                    '&',
                    'and',
                    $particulars
                );

            /*
            * Normalize spaces.
            */

            $particulars =
                preg_replace(
                    '/\s+/',
                    ' ',
                    $particulars
                );

            $particulars = trim($particulars);

            if ($particulars === '') {
                continue;
            }

            /*
            * Parse amount.
            */

            $rawamount =
                trim(
                    $data[$currentyearindex]
                    ?? ''
                );

            if (

                $rawamount === ''

                ||

                $rawamount === '-'

                ||

                $rawamount === '—'

            ) {
                continue;
            }

            $cleanamount = str_replace([',', '₹', ' '], '', $rawamount);
            $cleanamount = str_replace(['(', ')'], ['-', ''], $cleanamount);

            if (!is_numeric($cleanamount)) {
                continue;
            }

            $rows[] = [

                'particulars' =>
                    $particulars,

                'amount' =>
                    (float)$cleanamount
            ];
        }

        fclose($handle);

        return $rows;
    }

/**
 * Normalize balance sheet text.
 */
    private static function normalize_balance_sheet_text(
        string $text
    ): string {

        $text = strtolower($text);

        $text = str_replace(
            ['—', '–'],
            '-',
            $text
        );

        $text = str_replace(
            '&',
            'and',
            $text
        );

        $text = preg_replace(
            '/\([^)]+\)/',
            '',
            $text
        );

        $text = preg_replace(
            '/[^a-z0-9\s\-]/',
            '',
            $text
        );

        $text = preg_replace(
            '/\s+/',
            ' ',
            $text
        );

        return trim($text);
    }

}