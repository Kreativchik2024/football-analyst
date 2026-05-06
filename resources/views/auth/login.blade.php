@extends('layouts.guest')

@section('content')
<div class="d-flex justify-content-center align-items-center min-vh-100 p-3">
    <div class="bg-white rounded-4 shadow-lg p-4 p-md-5 w-100" style="max-width: 450px;">

        <div class="text-center mb-4">
            <h2 class="fw-bold text-dark">Вход</h2>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2">
                @foreach ($errors->all() as $error)
                    <p class="mb-0 small">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="form-control rounded-3 py-2 px-3 border border-secondary-subtle">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Пароль</label>
                <input id="password" type="password" name="password" required
                       class="form-control rounded-3 py-2 px-3 border border-secondary-subtle">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 small">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label text-secondary" for="remember">Запомнить</label>
                </div>
                <a href="{{ route('password.request') }}" class="text-decoration-none" style="color: #4f46e5;">Забыли пароль?</a>
            </div>

            <button type="submit" class="btn w-100 text-white fw-semibold rounded-3 py-2" style="background-color: #4f46e5;">
                Войти
            </button>
        </form>

        <div class="text-center mt-3 small text-secondary">
            Нет аккаунта? <a href="{{ route('register') }}" class="text-decoration-none" style="color: #4f46e5;">Зарегистрироваться</a>
        </div>

    </div>
</div>
@endsection