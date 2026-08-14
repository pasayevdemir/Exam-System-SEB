<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Services\QuestionImportService;

function parser(): QuestionImportService
{
    return new QuestionImportService();
}

/** Builds a CSV with the canonical header and the given data lines. */
function csv(array $lines, string $delimiter = ','): string
{
    $header = implode($delimiter, QuestionImportService::CSV_HEADERS);

    return $header . "\n" . implode("\n", $lines) . "\n";
}

function parseCsv(array $lines, array $existing = [], bool $skip = false): array
{
    return parser()->parse(csv($lines), 'csv', $existing, $skip);
}

function parseJson(string $json, array $existing = [], bool $skip = false): array
{
    return parser()->parse($json, 'json', $existing, $skip);
}

/* -------------------------------------------------------------------------- */
/* CSV happy paths                                                            */
/* -------------------------------------------------------------------------- */

it('parses a minimal csv row into a single choice question', function () {
    $result = parseCsv(['What is 2+2?,easy,single,3,4,,,,,2']);

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(1);

    $row = $result['rows'][0];
    expect($row['question_text'])->toBe('What is 2+2?')
        ->and($row['difficulty'])->toBe('easy')
        ->and($row['question_type'])->toBe('single')
        ->and($row['options'])->toBe(['3', '4']);
});

it('converts correct option numbers into zero-based indexes', function () {
    // The template speaks 1-based; the database wants positions.
    $result = parseCsv(['Q,easy,single,A,B,C,D,,,3']);

    expect($result['rows'][0]['correct'])->toBe([2]);
});

it('accepts letters and one-based numbers in the correct column', function () {
    foreach (['B' => [1], '2' => [1], 'b' => [1]] as $token => $expected) {
        $result = parseCsv(["Q,easy,single,Alpha,Beta,Gamma,,,,{$token}"]);

        expect($result['errors']->all())->toBe([]);
        expect($result['rows'][0]['correct'])->toBe($expected);
    }
});

it('accepts several correct options separated by a pipe', function () {
    $result = parseCsv(['Q,easy,multiple,A,B,C,D,,,"1|3"']);

    expect($result['rows'][0]['correct'])->toBe([0, 2]);
});

it('infers multiple choice when more than one option is correct', function () {
    $result = parseCsv(['Q,easy,,A,B,C,D,,,"1|3"']);

    expect($result['rows'][0]['question_type'])->toBe('multiple');
});

it('infers single choice when exactly one option is correct', function () {
    $result = parseCsv(['Q,easy,,A,B,C,D,,,2']);

    expect($result['rows'][0]['question_type'])->toBe('single');
});

it('defaults a blank difficulty to medium', function () {
    $result = parseCsv(['Q,,single,A,B,,,,,1']);

    expect($result['rows'][0]['difficulty'])->toBe('medium');
});

it('accepts two to six options and ignores trailing blanks', function () {
    $two = parseCsv(['Q,easy,single,A,B,,,,,1']);
    $six = parseCsv(['Q,easy,single,A,B,C,D,E,F,6']);

    expect($two['rows'][0]['options'])->toHaveCount(2);
    expect($six['rows'][0]['options'])->toHaveCount(6);
    expect($six['rows'][0]['correct'])->toBe([5]);
});

it('skips blank lines in the middle of a csv', function () {
    $result = parseCsv(['Q1,easy,single,A,B,,,,,1', '', 'Q2,easy,single,A,B,,,,,2']);

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(2);
});

/* -------------------------------------------------------------------------- */
/* CSV rejections                                                             */
/* -------------------------------------------------------------------------- */

it('rejects a gap in the option columns', function () {
    // Compacting silently would shift every later index and mark the wrong
    // option correct, so this has to be an error.
    $result = parseCsv(['Q,easy,single,A,,C,,,,1']);

    expect($result['rows'])->toBe([]);
    expect($result['errors']->first())->toContain('empty option before a filled one');
});

it('rejects a row with fewer than two options', function () {
    $result = parseCsv(['Q,easy,single,A,,,,,,1']);

    expect($result['errors']->first())->toContain('at least 2 options');
});

it('rejects a correct reference that names no option', function () {
    $result = parseCsv(['Q,easy,single,A,B,,,,,9']);

    expect($result['errors']->first())->toContain('does not match any');
});

it('rejects a single choice row with two correct options', function () {
    $result = parseCsv(['Q,easy,single,A,B,C,,,,"1|2"']);

    expect($result['errors']->first())->toContain('exactly one correct option');
});

it('rejects an unknown difficulty', function () {
    $result = parseCsv(['Q,impossible,single,A,B,,,,,1']);

    expect($result['errors']->first())->toContain('difficulty');
});

it('rejects a file upload type with a message pointing at the form', function () {
    $result = parseCsv(['Q,easy,file_upload,A,B,,,,,1']);

    expect($result['errors']->first())->toContain('file upload questions cannot be imported');
});

it('rejects an empty question text', function () {
    $result = parseCsv([',easy,single,A,B,,,,,1']);

    expect($result['errors']->first())->toContain('question text is empty');
});

it('rejects duplicate option text inside one question', function () {
    $result = parseCsv(['Q,easy,single,Same,Same,,,,,1']);

    expect($result['errors']->first())->toContain('appears twice');
});

it('accepts options that differ only by letter case', function () {
    // A question about .upper()/.toUpperCase() legitimately offers "Hello",
    // "HELLO" and "hello" as three distinct outputs — that IS the question.
    $json = '[{"question":"What does s.upper() print?",'
        . '"variant":["Hello","HELLO","hello","Error"],"correct_answer":"HELLO"}]';

    $result = parseJson($json);

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'][0]['options'])->toBe(['Hello', 'HELLO', 'hello', 'Error']);
    // Exact match wins, so the answer lands on the uppercase option, not the first.
    expect($result['rows'][0]['correct'])->toBe([1]);
});

it('accepts case-varying options from a csv too', function () {
    $result = parseCsv(['Q,easy,single,Hello,HELLO,hello,Error,,,2']);

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'][0]['options'])->toBe(['Hello', 'HELLO', 'hello', 'Error']);
});

it('refuses to guess when a correct answer matches several options only by case', function () {
    // No exact match: picking the first case-insensitive hit would silently mark
    // the wrong option correct, so this has to be reported instead.
    $json = '[{"question":"Q","variant":["Hello","HELLO"],"correct_answer":"hello"}]';

    $result = parseJson($json);

    expect($result['rows'])->toBe([]);
    expect($result['errors']->first())->toContain('does not match any');
});

it('still resolves a single case-insensitive match', function () {
    $json = '[{"question":"Q","variant":["Alpha","Beta"],"correct_answer":"beta"}]';

    expect(parseJson($json)['rows'][0]['correct'])->toBe([1]);
});

it('rejects duplicate question text inside the same file', function () {
    $result = parseCsv([
        'Repeated question,easy,single,A,B,,,,,1',
        'Repeated question,easy,single,C,D,,,,,2',
    ]);

    expect($result['errors']->first())->toContain('duplicates the question on row 2');
});

it('reports the row number on every error', function () {
    // Header is line 1, so the second data row is line 3 — the number Excel shows.
    $result = parseCsv([
        'Good,easy,single,A,B,,,,,1',
        'Bad,easy,single,A,B,,,,,9',
    ]);

    expect($result['errors']->first())->toStartWith('Row 3:');
});

it('rejects a csv missing the question column', function () {
    $result = parser()->parse("difficulty,correct\neasy,1\n", 'csv');

    expect($result['errors']->first())->toContain('missing the "question" column');
});

it('rejects a file with more rows than the import limit', function () {
    $lines = array_fill(0, QuestionImportService::MAX_ROWS + 1, 'Q,easy,single,A,B,,,,,1');

    $result = parseCsv($lines);

    expect($result['errors']->first())->toContain('more than the ' . QuestionImportService::MAX_ROWS);
});

/* -------------------------------------------------------------------------- */
/* Encoding and delimiters                                                    */
/* -------------------------------------------------------------------------- */

it('strips a byte order mark from the header row', function () {
    $result = parser()->parse("\xEF\xBB\xBF" . csv(['Q,easy,single,A,B,,,,,1']), 'csv');

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(1);
});

it('accepts a semicolon delimited csv', function () {
    $contents = implode(';', QuestionImportService::CSV_HEADERS) . "\n"
        . "Q;easy;single;A;B;;;;;1\n";

    $result = parser()->parse($contents, 'csv');

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'][0]['options'])->toBe(['A', 'B']);
});

it('rejects a file that is not utf-8', function () {
    $latin1 = mb_convert_encoding('Suál,easy,single,A,B,,,,,1', 'ISO-8859-1', 'UTF-8');

    $result = parser()->parse(csv([$latin1]), 'csv');

    expect($result['errors']->first())->toContain('not UTF-8');
});

it('rejects an empty file', function () {
    expect(parser()->parse('   ', 'csv')['errors']->first())->toContain('empty');
});

/* -------------------------------------------------------------------------- */
/* JSON — the shape an existing question file already uses                    */
/* -------------------------------------------------------------------------- */

it('parses the existing json question file unchanged', function () {
    // This is the compatibility guarantee: a file written for the previous bulk
    // load must import with no edits at all, "id" key and everything.
    $json = <<<'JSON'
    [
      {
        "id": 1,
        "difficulty": "easy",
        "question": "What does the HTTP 404 status code represent?",
        "variant": ["200 OK", "404 Not Found", "500 Internal Server Error", "401 Unauthorized"],
        "correct_answer": "404 Not Found"
      }
    ]
    JSON;

    $result = parseJson($json);

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(1);

    $row = $result['rows'][0];
    expect($row['question_type'])->toBe('single')
        ->and($row['difficulty'])->toBe('easy')
        ->and($row['options'])->toHaveCount(4)
        ->and($row['correct'])->toBe([1]);
});

it('ignores unknown json keys such as id', function () {
    $json = '[{"id":99,"nonsense":true,"question":"Q","variant":["A","B"],"correct_answer":"A"}]';

    expect(parseJson($json)['errors']->all())->toBe([]);
});

it('treats a json correct_answer array as a multiple choice question', function () {
    $json = '[{"question":"Q","variant":["GET","FETCH","POST"],"correct_answer":["GET","POST"]}]';

    $row = parseJson($json)['rows'][0];

    expect($row['question_type'])->toBe('multiple')
        ->and($row['correct'])->toBe([0, 2]);
});

it('falls back to a one-based index when correct_answer matches no option text', function () {
    $json = '[{"question":"Q","variant":["Alpha","Beta"],"correct_answer":"2"}]';

    expect(parseJson($json)['rows'][0]['correct'])->toBe([1]);
});

it('prefers an exact option match over reading the value as an index', function () {
    // An option literally named "2" must win over position 2.
    $json = '[{"question":"Q","variant":["2","Beta","Gamma"],"correct_answer":"2"}]';

    expect(parseJson($json)['rows'][0]['correct'])->toBe([0]);
});

it('accepts options and answers as aliases for variant', function () {
    $withOptions = '[{"question":"Q","options":["A","B"],"correct_answer":"A"}]';
    $withAnswers = '[{"question":"Q","answers":["A","B"],"correct_answer":"A"}]';

    expect(parseJson($withOptions)['rows'])->toHaveCount(1);
    expect(parseJson($withAnswers)['rows'])->toHaveCount(1);
});

it('accepts a questions wrapper object', function () {
    $json = '{"questions":[{"question":"Q","variant":["A","B"],"correct_answer":"A"}]}';

    expect(parseJson($json)['rows'])->toHaveCount(1);
});

it('defaults a json question with no difficulty to medium', function () {
    $json = '[{"question":"Q","variant":["A","B"],"correct_answer":"A"}]';

    expect(parseJson($json)['rows'][0]['difficulty'])->toBe('medium');
});

it('rejects malformed json', function () {
    expect(parseJson('{not json')['errors']->first())->toContain('not valid JSON');
});

it('rejects json that is not a list of questions', function () {
    expect(parseJson('{"a":1}')['errors']->first())->toContain('must be a list');
});

it('numbers json errors by position in the list', function () {
    $json = '[{"question":"Good","variant":["A","B"],"correct_answer":"A"},'
        . '{"question":"Bad","variant":["A","B"],"correct_answer":"Nope"}]';

    expect(parseJson($json)['errors']->first())->toStartWith('Row 2:');
});

/* -------------------------------------------------------------------------- */
/* Duplicates against the target bank                                         */
/* -------------------------------------------------------------------------- */

it('rejects a question that already exists in the bank', function () {
    $result = parseCsv(['Already there,easy,single,A,B,,,,,1'], ['Already there']);

    expect($result['rows'])->toBe([]);
    expect($result['errors']->first())->toContain('already exists in this bank');
});

it('compares questions ignoring case and extra whitespace', function () {
    $result = parseCsv(['already   THERE,easy,single,A,B,,,,,1'], ['Already there']);

    expect($result['errors']->first())->toContain('already exists in this bank');
});

it('skips existing questions when asked to', function () {
    $result = parseCsv([
        'Already there,easy,single,A,B,,,,,1',
        'Brand new,easy,single,A,B,,,,,1',
    ], ['Already there'], true);

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(1);
    expect($result['skipped'])->toBe(1);
    expect($result['rows'][0]['question_text'])->toBe('Brand new');
});

/* -------------------------------------------------------------------------- */
/* Templates                                                                  */
/* -------------------------------------------------------------------------- */

it('generates a csv template the parser accepts', function () {
    // Generating from the same constant is what keeps template and parser in
    // step; this asserts the round trip actually holds.
    $result = parser()->parse(parser()->csvTemplate(), 'csv');

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(2);
    expect($result['rows'][1]['question_type'])->toBe('multiple');
});

it('generates a json template the parser accepts', function () {
    $result = parser()->parse(parser()->jsonTemplate(), 'json');

    expect($result['errors']->all())->toBe([]);
    expect($result['rows'])->toHaveCount(2);
});
