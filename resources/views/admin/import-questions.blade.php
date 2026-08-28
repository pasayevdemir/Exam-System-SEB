{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('title', 'Import Questions - ' . $bank->name)

@section('nav-items')
    <a class="nav-link text-white" href="{{ route('admin.bank-questions', $bank->id) }}">
        <i class="fas fa-arrow-left me-1"></i>Back to Questions
    </a>
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-file-import me-2"></i>
        Import Questions
    </h1>
    <small class="text-muted">into <strong>{{ $bank->name }}</strong> ({{ $bank->questions_count }} question(s) so far)</small>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload a file</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.store-imported-questions', $bank->id) }}"
                      method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="file" class="form-label">CSV or JSON file</label>
                        <input type="file" class="form-control @error('file') is-invalid @enderror"
                               id="file" name="file" accept=".csv,.txt,.json" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Up to 2&nbsp;MB and {{ \App\Services\QuestionImportService::MAX_ROWS }} questions per file.</div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1"
                               id="skip_duplicates" name="skip_duplicates"
                               @checked(old('skip_duplicates'))>
                        <label class="form-check-label" for="skip_duplicates">
                            Skip questions already in this bank
                        </label>
                        <div class="form-text">
                            Without this, a question that already exists stops the whole import.
                        </div>
                    </div>

                    <div class="alert alert-info small" role="alert">
                        <i class="fas fa-circle-info me-1"></i>
                        Nothing is saved unless every question in the file is valid.
                        If any row has a problem you get a list of them and the bank is left untouched.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-import me-2"></i>Import
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>Templates</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Start from a template so the columns and keys already match.</p>
                <a href="{{ route('admin.import-template', 'csv') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-file-csv me-1"></i>CSV template
                </a>
                <a href="{{ route('admin.import-template', 'json') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-file-code me-1"></i>JSON template
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>File format</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning small" role="alert">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Only plain text multiple-choice questions can be imported.
                    Questions that ask for a file upload have to be added from the
                    <a href="{{ route('admin.bank-questions', $bank->id) }}" class="alert-link">Add New Question</a> form.
                </div>

                <h6 class="mt-3">CSV columns</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Column</th><th>Required</th><th>Value</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code>question</code></td><td>Yes</td><td>The question text.</td></tr>
                            <tr><td><code>difficulty</code></td><td>No</td><td><code>easy</code>, <code>medium</code> or <code>hard</code>. Defaults to <code>medium</code>.</td></tr>
                            <tr><td><code>type</code></td><td>No</td><td><code>single</code> or <code>multiple</code>. Worked out from the number of correct options if left blank.</td></tr>
                            <tr><td><code>option_1</code> … <code>option_6</code></td><td>2 to 6</td><td>Fill them in order, with no blank gaps between.</td></tr>
                            <tr><td><code>correct</code></td><td>Yes</td><td>Which option is right — a number (<code>2</code>), a letter (<code>B</code>), or several separated by <code>|</code> (<code>1|3</code>).</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="small text-muted">
                    Save from Excel as <strong>CSV UTF-8</strong>. Semicolon-separated files are handled too.
                </p>

                <h6 class="mt-4">JSON shape</h6>
                <pre class="bg-light p-3 rounded small mb-2" style="overflow-x: auto;"><code>[
  {
    "difficulty": "easy",
    "question": "What does HTTP 404 mean?",
    "variant": ["200 OK", "Not Found", "Server Error", "Unauthorized"],
    "correct_answer": "Not Found"
  }
]</code></pre>
                <ul class="small text-muted">
                    <li><code>correct_answer</code> can be the option text itself, or a number like <code>2</code>.</li>
                    <li>Give it a list (<code>["GET", "POST"]</code>) for a multiple-choice question.</li>
                    <li>Extra keys such as <code>id</code> are ignored, so an existing question file works as-is.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
