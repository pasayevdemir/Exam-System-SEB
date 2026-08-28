{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('page', 'exam-banks')

@section('title', 'Banks - ' . $exam->exam_name)

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $exam->exam_name }}</h1>
        <p class="text-muted mb-0">Exam ID: {{ $exam->exam_id }} &mdash; Question Banks &amp; Quotas</p>
    </div>
    <span class="badge {{ $exam->is_active ? 'bg-success' : 'bg-secondary' }} fs-6">
        {{ $exam->is_active ? 'Active' : 'Inactive' }}
    </span>
</div>

@if($exam->examQuestionBanks->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Attached Banks</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Bank</th>
                            <th>Easy</th>
                            <th>Medium</th>
                            <th>Hard</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Pool >= quota lets an exam generate, but a pool only barely
                            // above quota means most students see nearly the same
                            // questions - randomization in name only.
                            $poolTier = function (?int $available, int $quota) {
                                if ($quota === 0 || $available === null) {
                                    return null;
                                }
                                $ratio = $available / $quota;
                                return match (true) {
                                    $ratio < 2 => ['label' => 'ZƏİF', 'class' => 'bg-danger'],
                                    $ratio < 4 => ['label' => 'ORTA', 'class' => 'bg-warning text-dark'],
                                    default => ['label' => 'YAXŞI', 'class' => 'bg-success'],
                                };
                            };
                        @endphp
                        @foreach($exam->examQuestionBanks as $eqb)
                            @php $counts = $bankCounts[$eqb->question_bank_id] ?? null; @endphp
                            <tr>
                                <td>{{ $eqb->questionBank->name }}</td>
                                <td>
                                    {{ $eqb->quota_easy }}
                                    @if($counts && $eqb->quota_easy > $counts->easy_count)
                                        <span class="badge bg-warning text-dark ms-1" title="Only {{ $counts->easy_count }} available">!</span>
                                    @endif
                                    @if($counts && ($tier = $poolTier($counts->easy_count, $eqb->quota_easy)))
                                        <span class="badge {{ $tier['class'] }} ms-1" title="{{ $counts->easy_count }} available / {{ $eqb->quota_easy }} needed">{{ $tier['label'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $eqb->quota_medium }}
                                    @if($counts && $eqb->quota_medium > $counts->medium_count)
                                        <span class="badge bg-warning text-dark ms-1" title="Only {{ $counts->medium_count }} available">!</span>
                                    @endif
                                    @if($counts && ($tier = $poolTier($counts->medium_count, $eqb->quota_medium)))
                                        <span class="badge {{ $tier['class'] }} ms-1" title="{{ $counts->medium_count }} available / {{ $eqb->quota_medium }} needed">{{ $tier['label'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $eqb->quota_hard }}
                                    @if($counts && $eqb->quota_hard > $counts->hard_count)
                                        <span class="badge bg-warning text-dark ms-1" title="Only {{ $counts->hard_count }} available">!</span>
                                    @endif
                                    @if($counts && ($tier = $poolTier($counts->hard_count, $eqb->quota_hard)))
                                        <span class="badge {{ $tier['class'] }} ms-1" title="{{ $counts->hard_count }} available / {{ $eqb->quota_hard }} needed">{{ $tier['label'] }}</span>
                                    @endif
                                </td>
                                <td><strong>{{ $eqb->quota_easy + $eqb->quota_medium + $eqb->quota_hard }}</strong></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editQuota{{ $eqb->id }}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="{{ route('admin.detach-bank', [$exam->id, $eqb->id]) }}" method="POST"
                                              data-confirm="Detach this bank from the exam?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            {{-- Bootstrap collapses by animating height with overflow:hidden, which a
                                 table-row cannot honour - putting .collapse on the <tr> made the panel
                                 snap shut again the moment it opened. The row stays put and a plain
                                 block element inside the cell does the collapsing. --}}
                            <tr>
                                <td colspan="6" class="p-0 border-0">
                                    <div class="collapse" id="editQuota{{ $eqb->id }}">
                                        <div class="bg-light p-3">
                                            <form action="{{ route('admin.update-bank-quota', [$exam->id, $eqb->id]) }}" method="POST" class="row g-2 align-items-end mb-0">
                                                @csrf
                                                @method('PUT')
                                                <div class="col-auto">
                                                    <label class="form-label small mb-0">Easy</label>
                                                    <input type="number" min="0" class="form-control form-control-sm" name="quota_easy" value="{{ $eqb->quota_easy }}">
                                                </div>
                                                <div class="col-auto">
                                                    <label class="form-label small mb-0">Medium</label>
                                                    <input type="number" min="0" class="form-control form-control-sm" name="quota_medium" value="{{ $eqb->quota_medium }}">
                                                </div>
                                                <div class="col-auto">
                                                    <label class="form-label small mb-0">Hard</label>
                                                    <input type="number" min="0" class="form-control form-control-sm" name="quota_hard" value="{{ $eqb->quota_hard }}">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        No banks attached yet. Attach a bank below and set its per-difficulty quota.
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Attach a Bank</h5>
    </div>
    <div class="card-body">
        @if($availableBanks->isEmpty())
            <p class="text-muted mb-0">
                All existing banks are already attached to this exam.
                <a href="{{ route('admin.create-bank') }}">Create a new bank</a> to attach more.
            </p>
        @else
            <form action="{{ route('admin.attach-bank', $exam->id) }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label for="question_bank_id" class="form-label">Bank</label>
                    <select class="form-select @error('question_bank_id') is-invalid @enderror" id="question_bank_id" name="question_bank_id" required>
                        <option value="">Select a bank...</option>
                        @foreach($availableBanks as $bank)
                            @php $c = $bankCounts[$bank->id] ?? null; @endphp
                            <option value="{{ $bank->id }}"
                                    data-easy="{{ $c->easy_count ?? 0 }}"
                                    data-medium="{{ $c->medium_count ?? 0 }}"
                                    data-hard="{{ $c->hard_count ?? 0 }}"
                                    {{ old('question_bank_id') == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    @error('question_bank_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="quota_easy" class="form-label">Easy <span id="easy-count-hint" class="text-muted small"></span></label>
                    <input type="number" min="0" class="form-control" id="quota_easy" name="quota_easy" value="{{ old('quota_easy', 0) }}" required>
                </div>
                <div class="col-md-2">
                    <label for="quota_medium" class="form-label">Medium <span id="medium-count-hint" class="text-muted small"></span></label>
                    <input type="number" min="0" class="form-control" id="quota_medium" name="quota_medium" value="{{ old('quota_medium', 0) }}" required>
                </div>
                <div class="col-md-2">
                    <label for="quota_hard" class="form-label">Hard <span id="hard-count-hint" class="text-muted small"></span></label>
                    <input type="number" min="0" class="form-control" id="quota_hard" name="quota_hard" value="{{ old('quota_hard', 0) }}" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-link me-1"></i>Attach
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection

