{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
@extends('layouts.app')

@section('title', 'Parol Sıfırlama Sorğusu')

@section('nav-items')
    <span class="nav-link text-white">
        <i class="fas fa-user-graduate me-1"></i>Tələbə Portalı
    </span>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-center bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-key me-2"></i>
                    Parol Sıfırlama Sorğusu
                </h4>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Parollar administrator tərəfindən sıfırlanır. Sorğunuzu aşağıda göndərin,
                    sonra yeni parolu almaq üçün imtahan administratoru ilə əlaqə saxlayın.
                </div>

                <form action="{{ route('student.password-request.store') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>
                            E-poçt ünvanı
                        </label>
                        <input type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>
                            Sorğu göndər
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center text-muted">
                <small>
                    Yadınıza düşdü?
                    <a href="{{ route('student.login') }}">Girişə qayıt</a>
                </small>
            </div>
        </div>
    </div>
</div>
@endsection
