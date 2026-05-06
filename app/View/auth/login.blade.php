@extends('layouts.guest')

@section('content')
<div class="w-100">
    <h2 class="text-center mb-4">Вход</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Пароль</label>
            <input id="password" type="password" class="form-control" name="password" required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label class="form-check-label" for="remember">Запомнить меня</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Войти</button>
    </form>

    <div class="text-center mt-3">
        <a href="{{ route('register') }}">Нет аккаунта? Зарегистрироваться</a>
    </div>
</div>
@endsection