@extends('layouts.app')

@section('title', 'Question Banks')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-layer-group me-2"></i>
        Question Banks
    </h1>
    <a href="{{ route('admin.create-bank') }}" class="btn btn-primary btn-custom">
        <i class="fas fa-plus me-2"></i>
        New Bank
    </a>
</div>

@if($banks->count() > 0)
    <div class="row">
        @foreach($banks as $bank)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ $bank->name }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <strong>Questions:</strong> {{ $bank->questions_count }}
                        </p>
                        @if($bank->description)
                            <p class="card-text text-muted">{{ Str::limit($bank->description, 80) }}</p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.bank-questions', $bank->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-question-circle me-1"></i>Questions
                            </a>
                            <a href="{{ route('admin.edit-bank', $bank->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#deleteBankModal"
                                    data-action="{{ route('admin.delete-bank', $bank->id) }}"
                                    data-item="{{ $bank->name }} ({{ $bank->questions_count }} question(s))">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $banks->links() }}
    </div>
@else
    <div class="text-center py-5">
        <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
        <h4 class="text-muted">No Question Banks Yet</h4>
        <p class="text-muted">Click "New Bank" to create your first question bank.</p>
        <a href="{{ route('admin.create-bank') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Bank
        </a>
    </div>
@endif

@include('admin.partials.password-confirm-modal', [
    'id' => 'deleteBankModal',
    'title' => 'Confirm Bank Deletion',
    'heading' => 'Delete this question bank?',
    'buttonLabel' => 'Delete Bank',
    'warnings' => [
        'The bank and every question inside it will be permanently deleted.',
        'The bank will be unregistered from any exam it is attached to.',
        'If its questions were already served in an exam attempt or answered by a student, deletion is refused to keep those results intact.',
    ],
])
@endsection
