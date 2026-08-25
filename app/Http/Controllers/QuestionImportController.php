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

use App\Http\Requests\Admin\ImportQuestionsRequest;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\QuestionImportService;

/**
 * Filling a bank from a CSV or JSON file, and handing out the templates that
 * describe what those files must look like.
 *
 * The parsing itself lives in QuestionImportService; this only moves the upload
 * between the browser and that service.
 */
class QuestionImportController extends Controller
{
    /**
     * The bulk-import page for one bank.
     *
     * Deliberately not sharing a layer with BankQuestionController::storeQuestion:
     * that action takes a wide flat form payload, branches into a file_upload
     * arm, and leans on QuestionRequest to drop blank option boxes and re-map
     * correct_answers against them — none of which an import has or wants. A
     * common abstraction over the two shapes would be more branches than the
     * handful of lines it saves.
     */
    public function importQuestions($bankId)
    {
        $bank = QuestionBank::withCount('questions')->findOrFail($bankId);

        return view('admin.import-questions', compact('bank'));
    }

    public function storeImportedQuestions(ImportQuestionsRequest $request, $bankId, QuestionImportService $importer)
    {
        $bank = QuestionBank::findOrFail($bankId);

        $file = $request->file('file');
        $format = strtolower($file->getClientOriginalExtension()) === 'json' ? 'json' : 'csv';

        // Read straight from PHP's upload temp path — never ->store(). The only
        // persistent volume in production is storage_data, and spooled imports
        // would accumulate there with nothing to clean them up.
        $contents = (string) $file->get();

        if (trim($contents) === '') {
            return back()->withErrors(['file' => 'The uploaded file is empty.']);
        }

        $result = $importer->parse(
            $contents,
            $format,
            Question::where('question_bank_id', $bank->id)->pluck('question_text')->all(),
            $request->boolean('skip_duplicates')
        );

        if ($result['errors']->isNotEmpty()) {
            return back()->withErrors($result['errors'])->withInput();
        }

        $created = $importer->import($bank, $result['rows']);

        $message = "Imported {$created} question(s) into \"{$bank->name}\".";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} already in this bank.";
        }

        return redirect()->route('admin.bank-questions', $bank->id)->with('success', $message);
    }

    public function importTemplate($format, QuestionImportService $importer)
    {
        if ($format === 'json') {
            return response($importer->jsonTemplate(), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="question-import-template.json"',
            ]);
        }

        // The BOM is what makes Excel open non-ASCII sample text correctly.
        return response("\xEF\xBB\xBF".$importer->csvTemplate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="question-import-template.csv"',
        ]);
    }
}
