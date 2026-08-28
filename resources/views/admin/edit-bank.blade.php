{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('title', 'Edit Bank - ' . $bank->name)

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Edit Question Bank
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.update-bank', $bank->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-tag me-1"></i>
                            Bank Name
                        </label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name', $bank->name) }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left me-1"></i>
                            Description (Optional)
                        </label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="description"
                                  name="description"
                                  rows="3">{{ old('description', $bank->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Update Bank
                        </button>
                        <a href="{{ route('admin.bank-questions', $bank->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-question-circle me-2"></i>
                            Manage Questions
                        </a>
                        <a href="{{ route('admin.banks') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
