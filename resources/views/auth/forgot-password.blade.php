@extends('layouts.guest')

@section('content')
<div class="d-flex justify-content-center align-items-center min-vh-100 p-3">
    <div class="bg-white rounded-4 shadow-lg p-4 p-md-5 w-100" style="max-width: 450px;">

<div class="w-100">
    <h2 class="text-center mb-4">Восстановление пароля</h2>

    <p class="text-muted mb-4">
        Забыли пароль? Укажите ваш email, и мы отправим вам ссылку для сброса пароля.
    </p>

    @if (session('status'))
        <div class="alert alert-success mb-4">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">Отправить ссылку для сброса пароля</button>
    </form>

    <div class="text-center mt-3">
        <a href="{{ route('login') }}">Вернуться ко входу</a>
    </div>
</div>
    </div>
</div>
@endsection
