<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Exports;

use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The results of one exam as a two-sheet workbook.
 *
 * Sheet one is a row per student; sheet two is a row per answer, which is what
 * an invigilator actually reads when a mark is disputed. Building it is a
 * decision about what belongs in an exam report, not about HTTP, so it happens
 * here and the controller is left holding nothing but the response.
 *
 * Nothing in here writes to output. The caller gets a Spreadsheet and decides
 * how to send it, which is also what makes the whole thing testable by reading
 * cells back rather than by parsing a downloaded file.
 */
class ExamResultsWorkbook
{
    private const SUMMARY_HEADERS = [
        'A' => 'Full Name',
        'B' => 'FIN Code',
        'C' => 'Score',
        'D' => 'Max Score',
        'E' => 'Correct Answers',
        'F' => 'Total Questions',
        'G' => 'Percentage',
        'H' => 'Submitted At',
    ];

    private const DETAIL_HEADERS = [
        'A' => 'Full Name',
        'B' => 'FIN Code',
        'C' => 'Question #',
        'D' => 'Question Text',
        'E' => 'Question Type',
        'F' => 'Student Answer',
        'G' => 'Correct Answer',
        'H' => 'Is Correct',
        'I' => 'File Submission',
        'J' => 'File Size',
        'K' => 'Grading Status',
        'L' => 'Graded Score',
        'M' => 'Grading Notes',
    ];

    /** The summary table starts here, leaving room for the exam info block. */
    private const SUMMARY_HEADER_ROW = 7;

    /** What a hand-marked file submission has to reach to read as Passed. */
    private const PASS_MARK = ExamResult::PASS_MARK;

    /** What a question is worth when its attempt row is gone - see ExamResult. */
    private const FALLBACK_WEIGHT = 1.0;

    private const GREEN = 'C6EFCE';

    private const RED = 'FFC7CE';

    private const AMBER = 'FFEB9C';

    /**
     * @param  Collection<int, ExamResult>  $results  with user, studentAnswers.question.answers,
     *                                                studentAnswers.answer and
     *                                                examAttempt.attemptQuestions loaded
     */
    public function __construct(
        private readonly Exam $exam,
        private readonly Collection $results,
    ) {}

    /**
     * exam_id is free text an admin types, and "2024/Fall" is an ordinary thing
     * to call an exam - but a Content-Disposition filename may not carry a path
     * separator, so anything outside a safe set becomes a dash here.
     */
    public function filename(): string
    {
        $examId = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $this->exam->exam_id);

        return 'exam_results_'.trim((string) $examId, '-').'_'.now()->format('Y-m-d_H-i-s').'.xlsx';
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        // Nothing written here is a formula, and leaving the engine on made it
        // try to evaluate cell text that merely looked like one.
        $spreadsheet->getCalculationEngine()->disableCalculationCache();
        Calculation::getInstance($spreadsheet)->setCalculationCacheEnabled(false);

        $spreadsheet->getProperties()
            ->setCreator('SITC Exam System')
            ->setTitle($this->exam->exam_name.' - Results')
            ->setSubject('Exam Results')
            ->setDescription('Detailed exam results for '.$this->exam->exam_name);

        $this->writeSummary($spreadsheet->getActiveSheet());
        $this->writeDetails($spreadsheet->createSheet(1));

        return $spreadsheet;
    }

    private function writeSummary(Worksheet $sheet): void
    {
        $sheet->setTitle('Exam Results');

        $this->text($sheet, 'A1', 'Exam Name:');
        $this->text($sheet, 'B1', $this->exam->exam_name);
        $this->text($sheet, 'A2', 'Exam ID:');
        $this->text($sheet, 'B2', $this->exam->exam_id);
        $this->text($sheet, 'A3', 'Total Submissions:');
        $sheet->setCellValue('B3', $this->results->count());
        $this->text($sheet, 'A4', 'Average Score:');
        $sheet->setCellValue('B4', round((float) $this->results->avg('score'), 2));
        $this->text($sheet, 'A5', 'Generated On:');
        $this->text($sheet, 'B5', now()->format('Y-m-d H:i:s'));

        $sheet->getStyle('A1:A5')->getFont()->setBold(true);
        $sheet->getStyle('A1:B5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row = self::SUMMARY_HEADER_ROW;

        foreach (self::SUMMARY_HEADERS as $column => $header) {
            $this->text($sheet, $column.$row, $header);
        }

        $sheet->getStyle('A'.$row.':H'.$row)->applyFromArray($this->headerStyle());

        foreach ($this->results as $result) {
            $row++;

            $maxScore = $result->maxScore();

            // Out of the marks available rather than the question count, and
            // from the model so this sheet cannot drift from the page it is
            // exported from.
            $percentage = $result->percentage();

            $this->text($sheet, 'A'.$row, $result->user?->name ?? 'N/A');
            $this->text($sheet, 'B'.$row, $result->user?->fin_code ?? 'N/A');
            $sheet->setCellValue('C'.$row, (float) $result->score);
            $sheet->setCellValue('D'.$row, $maxScore);
            $sheet->setCellValue('E'.$row, $result->correct_answers);
            $sheet->setCellValue('F'.$row, $result->total_questions);
            $this->text($sheet, 'G'.$row, $percentage.'%');
            $this->text($sheet, 'H'.$row, $result->submitted_at->format('Y-m-d H:i:s'));

            $this->banded($sheet, 'A'.$row.':H'.$row, $row);
        }

        $this->autoSize($sheet, 'A', 'H');
    }

    private function writeDetails(Worksheet $sheet): void
    {
        $sheet->setTitle('Detailed Answers');

        foreach (self::DETAIL_HEADERS as $column => $header) {
            $this->text($sheet, $column.'1', $header);
        }

        $sheet->getStyle('A1:M1')->applyFromArray($this->headerStyle());

        $row = 1;

        foreach ($this->results as $result) {
            // One multiple-choice question answered with two options is two rows
            // here, so numbering by row made the second option read as the next
            // question and pushed every question after it off by one.
            $numbers = [];
            $weights = $this->weightsFor($result);

            foreach ($result->studentAnswers as $studentAnswer) {
                $row++;
                $question = $studentAnswer->question;
                $isFileUpload = $question->isFileUpload();
                $numbers[$question->id] ??= count($numbers) + 1;

                $this->text($sheet, 'A'.$row, $result->user?->name ?? 'N/A');
                $this->text($sheet, 'B'.$row, $result->user?->fin_code ?? 'N/A');
                $sheet->setCellValue('C'.$row, $numbers[$question->id]);
                $this->text($sheet, 'D'.$row, $question->question_text);
                $this->text($sheet, 'E'.$row, $isFileUpload ? 'File Upload' : 'Multiple Choice');

                // Each writer returns the colour for its own verdict, so the
                // "Is Correct" cell and the fill behind it cannot disagree.
                $verdict = $isFileUpload
                    ? $this->writeFileUploadAnswer($sheet, $row, $studentAnswer)
                    : $this->writeMcqAnswer(
                        $sheet,
                        $row,
                        $studentAnswer,
                        $question,
                        $weights[$question->id] ?? self::FALLBACK_WEIGHT
                    );

                // Banding first: it covers A..M, so applying it after the
                // verdict fill painted over that cell on every even row and left
                // the colour coding showing on half the sheet.
                $this->banded($sheet, 'A'.$row.':M'.$row, $row, 'F9F9F9');

                $sheet->getStyle('H'.$row)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($verdict);
            }
        }

        $this->autoSize($sheet, 'A', 'M');
    }

    /**
     * @return string the fill colour for this row's verdict
     */
    private function writeFileUploadAnswer(Worksheet $sheet, int $row, $studentAnswer): string
    {
        $passed = $studentAnswer->manual_score >= self::PASS_MARK;

        $this->text($sheet, 'F'.$row, 'File Uploaded');
        $this->text($sheet, 'G'.$row, 'Manual Grading Required');
        $this->text($sheet, 'H'.$row, $studentAnswer->is_graded
            ? ($passed ? 'Passed' : 'Failed')
            : 'Pending');

        // getFileUrl() rather than a /storage/ path: submissions are kept on the
        // private disk precisely so they are not reachable without signing in,
        // so a public URL for one has nothing behind it.
        $this->text($sheet, 'I'.$row, $studentAnswer->getFileUrl() ?? 'No file submitted');
        $this->text($sheet, 'J'.$row, $studentAnswer->getFormattedFileSize() ?? '');

        $this->text($sheet, 'K'.$row, $studentAnswer->is_graded ? 'Graded' : 'Pending');
        $this->text($sheet, 'L'.$row, $studentAnswer->manual_score ?? '');
        $this->text($sheet, 'M'.$row, $studentAnswer->admin_feedback ?? '');

        if (! $studentAnswer->is_graded) {
            return self::AMBER;
        }

        return $passed ? self::GREEN : self::RED;
    }

    /**
     * @return string the fill colour for this row's verdict
     */
    private function writeMcqAnswer(Worksheet $sheet, int $row, $studentAnswer, $question, float $weight): string
    {
        $correct = $question->answers->firstWhere('is_correct', true);

        // Correctness of the option this row holds. It used to be read from
        // student_answers.is_correct, a column that has never existed, so it was
        // null on every row: the verdict cell said "Correct" while the fill
        // behind it and the score column both said otherwise.
        $isCorrect = (bool) $studentAnswer->answer?->is_correct;

        $this->text($sheet, 'F'.$row, $studentAnswer->answer ? $studentAnswer->answer->answer_text : 'No answer');
        $this->text($sheet, 'G'.$row, $correct ? $correct->answer_text : 'N/A');
        $this->text($sheet, 'H'.$row, $isCorrect ? 'Correct' : 'Incorrect');

        // Kept empty rather than skipped so every row has the same shape and the
        // sheet can be filtered on any column.
        $this->text($sheet, 'I'.$row, '');
        $this->text($sheet, 'J'.$row, '');
        $this->text($sheet, 'K'.$row, 'Auto-graded');
        // The marks this option carries, so the sheet reads in the same currency
        // as the score on the summary sheet rather than in flat points.
        $this->text($sheet, 'L'.$row, $isCorrect ? ExamResult::formatPoints($weight) : '0');
        $this->text($sheet, 'M'.$row, '');

        return $isCorrect ? self::GREEN : self::RED;
    }

    /**
     * weight_at_generation per question id for one result, so a question's marks
     * are the ones its paper was generated with rather than whatever an admin
     * has since made that question worth.
     *
     * @return array<int, float>
     */
    private function weightsFor(ExamResult $result): array
    {
        $attempt = $result->examAttempt;

        if ($attempt === null) {
            return [];
        }

        return $attempt->attemptQuestions
            ->mapWithKeys(fn ($aq) => [(int) $aq->question_id => (float) $aq->weight_at_generation])
            ->all();
    }

    /**
     * Write a cell as text, never as something Excel might evaluate.
     *
     * Two separate protections, because they answer different things. Writing
     * explicitly as TYPE_STRING is what stops a spreadsheet treating an answer
     * of "=cmd|'/c calc'!A1" as a formula. The leading apostrophe is for the
     * programs that ignore the declared type on import - most of all a CSV
     * re-export of this file, where the type is gone and only the quote is left.
     */
    private function text(Worksheet $sheet, string $cell, mixed $value): void
    {
        $safe = trim((string) ($value ?? ''));

        if (preg_match('/^[=+\-@]/', $safe) || str_contains($safe, '=')) {
            $safe = "'".$safe;
        }

        // A cell holding a line break breaks the row apart on CSV re-export.
        $safe = str_replace(["\r\n", "\r", "\n"], ' ', $safe);

        $sheet->setCellValueExplicit($cell, $safe, DataType::TYPE_STRING);
    }

    private function banded(Worksheet $sheet, string $range, int $row, string $rgb = 'F2F2F2'): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        if ($row % 2 === 0) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        }
    }

    private function autoSize(Worksheet $sheet, string $from, string $to): void
    {
        foreach (range($from, $to) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function headerStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
    }
}
