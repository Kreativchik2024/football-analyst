@extends('layouts.guest')

@section('content')
<div class="d-flex justify-content-center align-items-center min-vh-100 p-3">
    <div class="bg-white rounded-4 shadow-lg p-4 p-md-5 w-100" style="max-width: 450px;">

        <div class="text-center mb-4">
            <h2 class="fw-bold text-dark">Регистрация</h2>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2">
                @foreach ($errors->all() as $error)
                    <p class="mb-0 small">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label small fw-semibold text-secondary">Имя</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                       class="form-control rounded-3 py-2 px-3 border border-secondary-subtle">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                       class="form-control rounded-3 py-2 px-3 border border-secondary-subtle">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Пароль</label>
                <input id="password" type="password" name="password" required
                       class="form-control rounded-3 py-2 px-3 border border-secondary-subtle">
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Подтверждение пароля</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                       class="form-control rounded-3 py-2 px-3 border border-secondary-subtle">
            </div>

            <button type="submit" class="btn w-100 text-white fw-semibold rounded-3 py-2" style="background-color: #4f46e5;">
                Зарегистрироваться
            </button>
        </form>

        <div class="text-center mt-3 small text-secondary">
            Уже есть аккаунт? <a href="{{ route('login') }}" class="text-decoration-none" style="color: #4f46e5;">Войти</a>
        </div>

    </div>
</div>
@endsection