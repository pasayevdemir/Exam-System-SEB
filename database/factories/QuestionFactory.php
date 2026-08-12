<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace Database\Factories;

use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_bank_id' => QuestionBank::factory(),
            'question_text' => fake()->sentence() . '?',
            'question_type' => 'single',
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'file_upload_settings' => null,
        ];
    }

    public function easy(): static
    {
        return $this->state(fn (array $attributes) => ['difficulty' => 'easy']);
    }

    public function medium(): static
    {
        return $this->state(fn (array $attributes) => ['difficulty' => 'medium']);
    }

    public function hard(): static
    {
        return $this->state(fn (array $attributes) => ['difficulty' => 'hard']);
    }

    public function multiple(): static
    {
        return $this->state(fn (array $attributes) => ['question_type' => 'multiple']);
    }

    public function fileUpload(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'file_upload',
            'file_upload_settings' => [
                'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'max_size_mb' => 10,
            ],
        ]);
    }
}
