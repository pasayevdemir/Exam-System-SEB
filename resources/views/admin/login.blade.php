{{--
    Peerstack Exam System

    Author    : Damir Pashayev <pashayevdamir@gmail.com>
    Copyright : 2026 Damir Pashayev. All rights reserved.
    Link      : https://github.com/pasayevdemir
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SITC Exam</title>
    @vite(['resources/css/app.css'])
</head>
<body class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="ps-login-logo mb-4">
                <img src="/peerstacklogo.webp" alt="Peerstack Academy" style="height:40px;width:auto;">
            </div>
            <h1>Admin Login</h1>
            <p>Exam Management System</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.authenticate') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn login-btn">Login to Admin Panel</button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ url('/') }}" class="text-decoration-none">← Back to Home</a>
        </div>
    </div>
</body>
</html>
