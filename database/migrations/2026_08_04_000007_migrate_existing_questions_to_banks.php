<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $exams = DB::table('exams')->get();

            foreach ($exams as $exam) {
                $questions = DB::table('questions')->where('exam_id', $exam->id)->get();

                if ($questions->isEmpty()) {
                    continue;
                }

                $bankId = DB::table('question_banks')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => "{$exam->exam_name} (auto-migrated bank)",
                    'description' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('questions')
                    ->where('exam_id', $exam->id)
                    ->update(['question_bank_id' => $bankId]);

                DB::table('exam_question_bank')->insert([
                    'exam_id' => $exam->id,
                    'question_bank_id' => $bankId,
                    'quota_easy' => 0,
                    'quota_medium' => $questions->count(),
                    'quota_hard' => 0,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('questions')->update(['question_bank_id' => null]);
            DB::table('exam_question_bank')->delete();
            DB::table('question_banks')->delete();
        });
    }
};
