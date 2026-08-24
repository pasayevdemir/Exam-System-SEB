<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Services;

use App\Models\Answer;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

/**
 * Bulk import of plain text multiple-choice questions from CSV or JSON.
 *
 * All-or-nothing: parse() validates the whole file and returns rows only when
 * every one of them is good, so a bank is never left half-filled.
 *
 * parse() is deliberately pure — it takes the file contents and the list of
 * question texts already in the target bank as arguments, touching neither the
 * request nor the database. That is what lets the bulk of this logic be tested
 * without a database at all.
 *
 * No maatwebsite/excel here on purpose: its model is streaming row-at-a-time
 * import, which fights an all-or-nothing design, and reading the file whole
 * through PhpSpreadsheet would cost the memory without buying anything. CSV and
 * JSON are the whole requirement; .xlsx was never asked for.
 */
class QuestionImportService
{
    public const CSV_HEADERS = [
        'question',
        'difficulty',
        'type',
        'option_1',
        'option_2',
        'option_3',
        'option_4',
        'option_5',
        'option_6',
        'correct',
    ];

    /** Bounds the work a single upload can cause in a memory-capped container. */
    public const MAX_ROWS = 500;

    public const MAX_OPTIONS = 6;

    public const MIN_OPTIONS = 2;

    /** Beyond this the flashed error list would bloat the database-backed session. */
    private const MAX_REPORTED_ERRORS = 50;

    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    /**
     * @param  string[]  $existingQuestionTexts  question_text values already in the target bank
     * @return array{rows: array<int, array>, errors: MessageBag, skipped: int}
     */
    public function parse(
        string $contents,
        string $format,
        array $existingQuestionTexts = [],
        bool $skipDuplicates = false
    ): array {
        $errors = new MessageBag;

        // Excel on Windows always writes a BOM and it corrupts the first header.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        if (trim($contents) === '') {
            return $this->fail($errors, 'The file is empty.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            return $this->fail($errors, 'The file is not UTF-8. Re-save it from Excel as "CSV UTF-8".');
        }

        $candidates = $format === 'json'
            ? $this->readJson($contents, $errors)
            : $this->readCsv($contents, $errors);

        if ($errors->isNotEmpty()) {
            return ['rows' => [], 'errors' => $errors, 'skipped' => 0];
        }

        if ($candidates === []) {
            return $this->fail($errors, 'The file contains no questions.');
        }

        if (count($candidates) > self::MAX_ROWS) {
            return $this->fail($errors, 'The file has '.count($candidates)
                .' questions, which is more than the '.self::MAX_ROWS.' allowed in one import.');
        }

        $existing = [];
        foreach ($existingQuestionTexts as $text) {
            $existing[$this->normaliseForComparison($text)] = true;
        }

        $rows = [];
        $seen = [];
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $label = $candidate['label'];

            $row = $this->validateCandidate($candidate, $errors);

            if ($row === null) {
                continue;
            }

            $key = $this->normaliseForComparison($row['question_text']);

            // A repeat inside one file always signals a broken source file.
            if (isset($seen[$key])) {
                $errors->add('row.'.$label, "Row {$label}: duplicates the question on row {$seen[$key]}.");

                continue;
            }
            $seen[$key] = $label;

            if (isset($existing[$key])) {
                if ($skipDuplicates) {
                    $skipped++;

                    continue;
                }

                $errors->add('row.'.$label, "Row {$label}: this question already exists in this bank.");

                continue;
            }

            $rows[] = $row;
        }

        if ($errors->isNotEmpty()) {
            return ['rows' => [], 'errors' => $this->capErrors($errors), 'skipped' => 0];
        }

        if ($rows === []) {
            return $this->fail($errors, $skipped > 0
                ? 'Every question in the file is already in this bank.'
                : 'The file contains no questions.');
        }

        return ['rows' => $rows, 'errors' => $errors, 'skipped' => $skipped];
    }

    /**
     * Write validated rows. Pre-validation already guarantees all-or-nothing
     * logically; the transaction covers the residual (deadlock, a concurrent
     * bank deletion, a value the column rejects after multibyte handling).
     *
     * @param  array<int, array>  $rows
     */
    public function import(QuestionBank $bank, array $rows): int
    {
        return DB::transaction(function () use ($bank, $rows) {
            foreach ($rows as $row) {
                $question = Question::create([
                    'question_bank_id' => $bank->id,
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'],
                    'difficulty' => $row['difficulty'],
                ]);

                foreach ($row['options'] as $index => $optionText) {
                    Answer::create([
                        'question_id' => $question->id,
                        'answer_text' => $optionText,
                        'is_correct' => in_array($index, $row['correct'], true),
                    ]);
                }
            }

            return count($rows);
        });
    }

    /* ---------------------------------------------------------------------- */
    /* Templates — generated from the same constant the parser consumes, so */
    /* they cannot drift out of sync the way a checked-in static file would. */
    /* ---------------------------------------------------------------------- */

    public function csvTemplate(): string
    {
        $rows = [
            self::CSV_HEADERS,
            [
                'What does HTTP 404 mean?',
                'easy',
                'single',
                '200 OK',
                'Not Found',
                'Server Error',
                'Unauthorized',
                '',
                '',
                '2',
            ],
            [
                'Which of these are HTTP methods?',
                'medium',
                'multiple',
                'GET',
                'FETCH',
                'POST',
                'SEND',
                '',
                '',
                'A|C',
            ],
        ];

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function jsonTemplate(): string
    {
        return json_encode([
            [
                'difficulty' => 'easy',
                'question' => 'What does HTTP 404 mean?',
                'variant' => ['200 OK', 'Not Found', 'Server Error', 'Unauthorized'],
                'correct_answer' => 'Not Found',
            ],
            [
                'difficulty' => 'medium',
                'question' => 'Which of these are HTTP methods?',
                'variant' => ['GET', 'FETCH', 'POST', 'SEND'],
                'correct_answer' => ['GET', 'POST'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ---------------------------------------------------------------------- */
    /* Readers */
    /* ---------------------------------------------------------------------- */

    /**
     * @return array<int, array{label: int|string, question: mixed, difficulty: mixed, type: mixed, options: array, correct: mixed, correctIsList: bool}>
     */
    private function readCsv(string $contents, MessageBag $errors): array
    {
        $delimiter = $this->sniffDelimiter($contents);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');

        if ($header === false || $header === null) {
            fclose($handle);
            $errors->add('file', 'The file could not be read as CSV.');

            return [];
        }

        $header = array_map(fn ($name) => $this->normaliseHeader((string) $name), $header);

        foreach (['question', 'correct'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);
                $errors->add('file', "The CSV is missing the \"{$required}\" column. "
                    .'Download the template to see the expected columns.');

                return [];
            }
        }

        $candidates = [];
        $line = 1; // the header occupies line 1, so data starts at 2

        while (($record = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            // fgetcsv yields [null] for a blank line.
            if ($record === [null] || $this->isBlankRecord($record)) {
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $name) {
                $assoc[$name] = isset($record[$index]) ? trim((string) $record[$index]) : '';
            }

            $options = [];
            for ($i = 1; $i <= self::MAX_OPTIONS; $i++) {
                $options[] = $assoc['option_'.$i] ?? '';
            }

            $candidates[] = [
                'label' => $line,
                'question' => $assoc['question'] ?? '',
                'difficulty' => $assoc['difficulty'] ?? '',
                'type' => $assoc['type'] ?? '',
                'options' => $options,
                // One CSV cell holds every correct option, so it is split here.
                // JSON says the same thing with a list and must NOT be split —
                // "404 Not Found" is one answer, not three.
                'correct' => preg_split('/[\s|,;]+/', trim((string) ($assoc['correct'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            ];
        }

        fclose($handle);

        return $candidates;
    }

    private function readJson(string $contents, MessageBag $errors): array
    {
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors->add('file', 'The file is not valid JSON: '.json_last_error_msg());

            return [];
        }

        // Accept both a bare list and the common {"questions": [...]} wrapper.
        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
            $decoded = $decoded['questions'];
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            $errors->add('file', 'The JSON must be a list of questions.');

            return [];
        }

        $candidates = [];

        foreach ($decoded as $index => $item) {
            $label = $index + 1;

            if (! is_array($item)) {
                $errors->add('row.'.$label, "Question {$label}: expected an object.");

                continue;
            }

            // Aliases so a file written against either naming works. Unknown keys
            // such as "id" are ignored rather than rejected — that is what lets an
            // existing question file import without a single edit.
            $options = $item['variant'] ?? $item['options'] ?? $item['answers'] ?? [];
            $correct = $item['correct_answer'] ?? $item['correct'] ?? null;

            $candidates[] = [
                'label' => $label,
                'question' => $item['question'] ?? $item['question_text'] ?? '',
                'difficulty' => $item['difficulty'] ?? 'medium',
                'type' => $item['type'] ?? $item['question_type'] ?? '',
                'options' => is_array($options) ? array_values($options) : [],
                // A scalar is ONE answer, however many words it has. Several
                // correct answers are expressed as a list, never as a delimited
                // string — otherwise "404 Not Found" would read as three tokens.
                'correct' => is_array($correct) ? array_values($correct) : ($correct === null ? [] : [$correct]),
            ];
        }

        return $candidates;
    }

    /* ---------------------------------------------------------------------- */
    /* Validation */
    /* ---------------------------------------------------------------------- */

    private function validateCandidate(array $candidate, MessageBag $errors): ?array
    {
        $label = $candidate['label'];
        $ok = true;

        $questionText = is_string($candidate['question']) ? trim($candidate['question']) : '';
        if ($questionText === '') {
            $errors->add('row.'.$label, "Row {$label}: the question text is empty.");
            $ok = false;
        }

        $difficulty = strtolower(trim((string) $candidate['difficulty']));
        if ($difficulty === '') {
            $difficulty = 'medium';
        }
        if (! in_array($difficulty, self::DIFFICULTIES, true)) {
            $errors->add('row.'.$label, "Row {$label}: difficulty \"{$candidate['difficulty']}\" is not one of easy, medium, hard.");
            $ok = false;
        }

        $type = strtolower(trim((string) $candidate['type']));
        if ($type !== '' && ! in_array($type, ['single', 'multiple'], true)) {
            $message = $type === 'file_upload'
                ? "Row {$label}: file upload questions cannot be imported — add them from the Add New Question form."
                : "Row {$label}: type \"{$candidate['type']}\" is not one of single, multiple.";
            $errors->add('row.'.$label, $message);
            $ok = false;
        }

        $options = $this->collectOptions($candidate, $label, $errors, $ok);

        if (! $ok || $options === null) {
            return null;
        }

        $correct = $this->resolveCorrect($candidate, $options, $label, $errors);

        if ($correct === null) {
            return null;
        }

        // Inferred when the file does not say: more than one correct means multiple.
        if ($type === '') {
            $type = count($correct) > 1 ? 'multiple' : 'single';
        }

        if ($type === 'single' && count($correct) !== 1) {
            $errors->add('row.'.$label, "Row {$label}: a single choice question needs exactly one correct option, found ".count($correct).'.');

            return null;
        }

        return [
            'question_text' => $questionText,
            'difficulty' => $difficulty,
            'question_type' => $type,
            'options' => $options,
            'correct' => $correct,
        ];
    }

    /** @return string[]|null */
    private function collectOptions(array $candidate, $label, MessageBag $errors, bool &$ok): ?array
    {
        $raw = array_map(fn ($o) => is_scalar($o) ? trim((string) $o) : '', $candidate['options']);

        $options = [];
        $gapSeen = false;

        foreach ($raw as $value) {
            if ($value === '') {
                $gapSeen = true;

                continue;
            }

            // A gap would shift every later index and silently mark the wrong
            // option correct, so it is an error rather than a quiet compaction.
            if ($gapSeen) {
                $errors->add('row.'.$label, "Row {$label}: there is an empty option before a filled one. Fill the options in order, without gaps.");
                $ok = false;

                return null;
            }

            $options[] = $value;
        }

        if (count($options) < self::MIN_OPTIONS) {
            $errors->add('row.'.$label, "Row {$label}: at least ".self::MIN_OPTIONS.' options are required, found '.count($options).'.');
            $ok = false;

            return null;
        }

        if (count($options) > self::MAX_OPTIONS) {
            $errors->add('row.'.$label, "Row {$label}: at most ".self::MAX_OPTIONS.' options are allowed, found '.count($options).'.');
            $ok = false;

            return null;
        }

        // Compared exactly, NOT case-folded. Options that differ only in case are
        // legitimately distinct here: a question about .upper()/.toUpperCase()
        // offers "Hello", "HELLO" and "hello" as three different outputs, and
        // telling the admin those are duplicates would reject a correct file.
        $seen = [];
        foreach ($options as $option) {
            if (isset($seen[$option])) {
                $errors->add('row.'.$label, "Row {$label}: the option \"{$option}\" appears twice.");
                $ok = false;

                return null;
            }
            $seen[$option] = true;
        }

        return $options;
    }

    /**
     * Turn the human-facing "correct" reference into 0-based positions.
     *
     * The template speaks 1-based numbers and letters because that is what a
     * person reading a spreadsheet counts; the database wants positional
     * indexes. That conversion happens here and nowhere else, and a 0-based
     * index is never shown back to the admin.
     *
     * @return int[]|null
     */
    private function resolveCorrect(array $candidate, array $options, $label, MessageBag $errors): ?array
    {
        // Each reader has already applied its own splitting convention.
        $tokens = array_map(fn ($t) => is_scalar($t) ? trim((string) $t) : '', $candidate['correct']);
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));

        if ($tokens === []) {
            $errors->add('row.'.$label, "Row {$label}: no correct option is given.");

            return null;
        }

        $correct = [];

        foreach ($tokens as $token) {
            $index = $this->resolveToken($token, $options);

            if ($index === null) {
                $errors->add('row.'.$label, "Row {$label}: \"{$token}\" does not match any of this question's options.");

                return null;
            }

            $correct[$index] = true;
        }

        $correct = array_keys($correct);
        sort($correct);

        return $correct;
    }

    private function resolveToken(string $token, array $options): ?int
    {
        // Exact option text first, so an option literally named "2" is never
        // mistaken for a position.
        $position = array_search($token, $options, true);
        if ($position !== false) {
            return (int) $position;
        }

        // Case-insensitive only as a fallback, and only when it is unambiguous.
        // A question whose options are "Hello"/"HELLO"/"hello" would otherwise
        // silently resolve to whichever happened to come first, marking the
        // wrong answer correct — better to reject and have the file corrected.
        $caseMatches = [];
        foreach ($options as $index => $option) {
            if (mb_strtolower($option) === mb_strtolower($token)) {
                $caseMatches[] = $index;
            }
        }
        if (count($caseMatches) === 1) {
            return $caseMatches[0];
        }
        if (count($caseMatches) > 1) {
            return null;
        }

        // A single letter: A, B, C...
        if (preg_match('/^[a-zA-Z]$/', $token)) {
            $index = ord(strtoupper($token)) - ord('A');

            return $index >= 0 && $index < count($options) ? $index : null;
        }

        // A 1-based number.
        if (preg_match('/^\d+$/', $token)) {
            $index = (int) $token - 1;

            return $index >= 0 && $index < count($options) ? $index : null;
        }

        return null;
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------------- */

    /** Excel writes ";" in several locales, and tab-separated exports are common. */
    private function sniffDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\n") ?: '';

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    private function normaliseHeader(string $name): string
    {
        $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
        $name = strtolower(trim($name));
        $name = preg_replace('/[\s\-]+/', '_', $name);

        return preg_replace('/_+/', '_', $name);
    }

    private function normaliseForComparison(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function isBlankRecord(array $record): bool
    {
        foreach ($record as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function fail(MessageBag $errors, string $message): array
    {
        $errors->add('file', $message);

        return ['rows' => [], 'errors' => $errors, 'skipped' => 0];
    }

    private function capErrors(MessageBag $errors): MessageBag
    {
        $all = $errors->all();

        if (count($all) <= self::MAX_REPORTED_ERRORS) {
            return $errors;
        }

        $capped = new MessageBag;
        foreach (array_slice($all, 0, self::MAX_REPORTED_ERRORS) as $index => $message) {
            $capped->add('row.'.$index, $message);
        }
        $capped->add('file', '...and '.(count($all) - self::MAX_REPORTED_ERRORS).' more problems.');

        return $capped;
    }
}
