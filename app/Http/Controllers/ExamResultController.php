<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Finished attempts from the admin side: the results table, the spreadsheet
 * export of it, and letting a student sit an exam again.
 */
class ExamResultController extends Controller
{
    public function examResults(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $query = ExamResult::where('exam_id', $exam->id)
            ->with('user', 'studentAnswers.question', 'studentAnswers.answer', 'examAttempt.events');

        // Apply search filter if provided - search by student name or FIN code
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('last_name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('fin_code', 'like', '%'.$searchTerm.'%');
            });
        }

        // Get total count for all data (without pagination)
        $totalCount = ExamResult::where('exam_id', $exam->id)->count();

        // Get filtered count for search results
        $filteredCount = $query->count();

        // Calculate average score for all data (not just current page)
        $averageScore = ExamResult::where('exam_id', $exam->id)->avg('score');

        // Apply pagination - 20 items per page
        $results = $query->orderBy('submitted_at', 'desc')->paginate(20);

        // Append search parameters to pagination links
        $results->appends($request->query());

        // Keep search value for the form
        $searchData = [
            'search' => $request->search,
        ];

        return view('admin.exam-results', compact('exam', 'results', 'searchData', 'totalCount', 'filteredCount', 'averageScore'));
    }

    public function allowRetake(ExamResult $examResult)
    {
        $attempt = $examResult->examAttempt;

        if (! $attempt) {
            return back()->with('error', 'This result has no linked exam attempt to reset.');
        }

        if ($attempt->superseded_at !== null) {
            return back()->with('error', 'A retake has already been allowed for this attempt.');
        }

        if ($attempt->completed_at === null) {
            return back()->with('error', "Cannot allow a retake while the student's attempt is still in progress.");
        }

        $attempt->update(['superseded_at' => now()]);

        return back()->with('success', "Retake allowed for {$examResult->user?->name}. They can now restart this exam.");
    }

    public function downloadResults($examId)
    {
        try {
            $exam = Exam::findOrFail($examId);

            // Get all results for this exam
            $results = ExamResult::where('exam_id', $exam->id)
                ->with('user', 'studentAnswers.question', 'studentAnswers.answer')
                ->orderBy('submitted_at', 'desc')
                ->get();

            if ($results->isEmpty()) {
                return redirect()->back()->with('error', 'No results found for this exam.');
            }

            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Disable automatic calculation to prevent formula issues
            $spreadsheet->getCalculationEngine()->disableCalculationCache();
            Calculation::getInstance($spreadsheet)->setCalculationCacheEnabled(false);

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('SITC Exam System')
                ->setTitle($exam->exam_name.' - Results')
                ->setSubject('Exam Results')
                ->setDescription('Detailed exam results for '.$exam->exam_name);

            // Sheet title
            $sheet->setTitle('Exam Results');

            // Header styling
            $headerStyle = [
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

            // Exam info section
            $this->setSafeStringValue($sheet, 'A1', 'Exam Name:');
            $this->setSafeStringValue($sheet, 'B1', $exam->exam_name);
            $this->setSafeStringValue($sheet, 'A2', 'Exam ID:');
            $this->setSafeStringValue($sheet, 'B2', $exam->exam_id);
            $this->setSafeStringValue($sheet, 'A3', 'Total Submissions:');
            $sheet->setCellValue('B3', $results->count());
            $this->setSafeStringValue($sheet, 'A4', 'Average Score:');
            $sheet->setCellValue('B4', round($results->avg('score'), 2));
            $this->setSafeStringValue($sheet, 'A5', 'Generated On:');
            $this->setSafeStringValue($sheet, 'B5', now()->format('Y-m-d H:i:s'));

            // Style exam info
            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            $sheet->getStyle('A1:B5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Summary header row (starting from row 7)
            $summaryRow = 7;
            $headers = [
                'A' => 'Full Name',
                'B' => 'FIN Code',
                'C' => 'Score',
                'D' => 'Correct Answers',
                'E' => 'Total Questions',
                'F' => 'Percentage',
                'G' => 'Submitted At',
            ];

            foreach ($headers as $col => $header) {
                $this->setSafeStringValue($sheet, $col.$summaryRow, $header);
            }

            $sheet->getStyle('A'.$summaryRow.':G'.$summaryRow)->applyFromArray($headerStyle);

            // Fill summary data
            $row = $summaryRow + 1;
            foreach ($results as $result) {
                $percentage = $result->total_questions > 0 ? round(($result->correct_answers / $result->total_questions) * 100, 1) : 0;

                $this->setSafeStringValue($sheet, 'A'.$row, $result->user?->name ?? 'N/A');
                $this->setSafeStringValue($sheet, 'B'.$row, $result->user?->fin_code ?? 'N/A');
                $sheet->setCellValue('C'.$row, $result->score);
                $sheet->setCellValue('D'.$row, $result->correct_answers);
                $sheet->setCellValue('E'.$row, $result->total_questions);
                $this->setSafeStringValue($sheet, 'F'.$row, $percentage.'%');
                $this->setSafeStringValue($sheet, 'G'.$row, $result->submitted_at->format('Y-m-d H:i:s'));

                // Style data rows
                $sheet->getStyle('A'.$row.':G'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                if ($row % 2 == 0) {
                    $sheet->getStyle('A'.$row.':G'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                }

                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Add detailed answers sheet
            $detailSheet = $spreadsheet->createSheet(1);
            $detailSheet->setTitle('Detailed Answers');

            $detailRow = 1;

            // Detailed header
            $detailHeaders = [
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

            foreach ($detailHeaders as $col => $header) {
                $this->setSafeStringValue($detailSheet, $col.$detailRow, $header);
            }

            $detailSheet->getStyle('A'.$detailRow.':M'.$detailRow)->applyFromArray($headerStyle);

            $detailRow++;

            // Fill detailed data
            foreach ($results as $result) {
                foreach ($result->studentAnswers as $index => $studentAnswer) {
                    $question = $studentAnswer->question;
                    $isFileUpload = $question->isFileUpload();

                    $this->setSafeStringValue($detailSheet, 'A'.$detailRow, $result->user?->name ?? 'N/A');
                    $this->setSafeStringValue($detailSheet, 'B'.$detailRow, $result->user?->fin_code ?? 'N/A');
                    $detailSheet->setCellValue('C'.$detailRow, $index + 1);
                    $this->setSafeStringValue($detailSheet, 'D'.$detailRow, $question->question_text);
                    $this->setSafeStringValue($detailSheet, 'E'.$detailRow, $isFileUpload ? 'File Upload' : 'Multiple Choice');

                    if ($isFileUpload) {
                        // Handle file upload questions
                        $this->setSafeStringValue($detailSheet, 'F'.$detailRow, 'File Uploaded');
                        $this->setSafeStringValue($detailSheet, 'G'.$detailRow, 'Manual Grading Required');
                        $this->setSafeStringValue($detailSheet, 'H'.$detailRow, $studentAnswer->is_graded ?
                            ($studentAnswer->manual_score >= 50 ? 'Passed' : 'Failed') : 'Pending');

                        // File submission details
                        if ($studentAnswer->file_path) {
                            $fileUrl = url('storage/'.$studentAnswer->file_path);
                            $this->setSafeStringValue($detailSheet, 'I'.$detailRow, $fileUrl);
                            $this->setSafeStringValue($detailSheet, 'J'.$detailRow, $studentAnswer->getFormattedFileSize());
                        } else {
                            $this->setSafeStringValue($detailSheet, 'I'.$detailRow, 'No file submitted');
                            $this->setSafeStringValue($detailSheet, 'J'.$detailRow, '');
                        }

                        $this->setSafeStringValue($detailSheet, 'K'.$detailRow,
                            $studentAnswer->is_graded ? 'Graded' : 'Pending');
                        $this->setSafeStringValue($detailSheet, 'L'.$detailRow,
                            $studentAnswer->manual_score !== null ? $studentAnswer->manual_score : '');
                        $this->setSafeStringValue($detailSheet, 'M'.$detailRow,
                            $studentAnswer->admin_feedback ?? '');
                    } else {
                        // Handle MCQ questions
                        $correctAnswer = $question->answers->where('is_correct', true)->first();
                        $isCorrect = $studentAnswer->answer && $studentAnswer->answer->is_correct;

                        $this->setSafeStringValue($detailSheet, 'F'.$detailRow,
                            $studentAnswer->answer ? $studentAnswer->answer->answer_text : 'No answer');
                        $this->setSafeStringValue($detailSheet, 'G'.$detailRow,
                            $correctAnswer ? $correctAnswer->answer_text : 'N/A');
                        $this->setSafeStringValue($detailSheet, 'H'.$detailRow,
                            $isCorrect ? 'Correct' : 'Incorrect');

                        // Empty file-related columns for MCQ
                        $this->setSafeStringValue($detailSheet, 'I'.$detailRow, '');
                        $this->setSafeStringValue($detailSheet, 'J'.$detailRow, '');
                        $this->setSafeStringValue($detailSheet, 'K'.$detailRow, 'Auto-graded');
                        $this->setSafeStringValue($detailSheet, 'L'.$detailRow, $studentAnswer->is_correct ? '1' : '0');
                        $this->setSafeStringValue($detailSheet, 'M'.$detailRow, '');
                    }

                    // Style detail rows
                    $detailSheet->getStyle('A'.$detailRow.':M'.$detailRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    // Color code correct/incorrect answers
                    if ($isFileUpload) {
                        if ($studentAnswer->is_graded) {
                            if ($studentAnswer->is_correct) {
                                $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');
                            } else {
                                $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                            }
                        } else {
                            $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C');
                        }
                    } else {
                        if ($studentAnswer->is_correct) {
                            $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');
                        } else {
                            $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                        }
                    }

                    if ($detailRow % 2 == 0) {
                        $detailSheet->getStyle('A'.$detailRow.':M'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9F9F9');
                    }

                    $detailRow++;
                }
            }

            // Auto-size columns for detail sheet
            foreach (range('A', 'M') as $column) {
                $detailSheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Generate filename
            $filename = 'exam_results_'.$exam->exam_id.'_'.now()->format('Y-m-d_H-i-s').'.xlsx';

            // Create writer and save to output
            $writer = new Xlsx($spreadsheet);

            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');

            // Save to php://output
            $writer->save('php://output');

            exit;

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Excel download error for exam ID '.$examId.': '.$e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while generating the Excel file. Please try again.');
        }
    }

    private function setSafeStringValue($sheet, $cell, $value)
    {
        // Handle null or empty values
        if ($value === null) {
            $value = '';
        }

        // Convert value to string and escape any potential formula characters
        $safeValue = (string) $value;

        // Trim whitespace
        $safeValue = trim($safeValue);

        // If the value starts with =, +, -, @ or has formula-like content, prepend with single quote
        if (preg_match('/^[=+\-@]/', $safeValue) || strpos($safeValue, '=') !== false) {
            $safeValue = "'".$safeValue;
        }

        // Remove or escape any problematic characters that might cause issues
        $safeValue = str_replace(["\r\n", "\r", "\n"], ' ', $safeValue); // Replace line breaks with spaces

        $sheet->setCellValueExplicit($cell, $safeValue, DataType::TYPE_STRING);
    }
}
