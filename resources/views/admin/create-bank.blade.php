@extends('layouts.app')

@section('title', 'Create Question Bank')

@section('nav-items')
    @include('admin.partials.nav')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Create Question Bank
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.store-bank') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-tag me-1"></i>
                            Bank Name
                        </label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Chapter 3 - Loops"
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
                                  rows="3"
                                  placeholder="Enter bank description">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>
                            Next - Add Questions
                        </button>
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
