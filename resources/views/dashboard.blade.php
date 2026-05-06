@extends('layouts.app')

@section('title', 'Личный кабинет')

@section('content')
<div class="container py-4">
   <h2 class="mb-4 text-light">👤 Личный кабинет — DeepOdds</h2>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card bg-dark text-white border-0 rounded-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">{{ $user->name }}</h5>
                    <p class="card-text mb-1"><strong>Email:</strong> {{ $user->email }}</p>
                    <p class="card-text mb-1"><strong>Роль:</strong> 
                        <span class="badge bg-info text-dark">{{ $user->role ?? 'user' }}</span>
                    </p>
                    <p class="card-text"><strong>Дата регистрации:</strong> {{ $user->created_at->format('d.m.Y') }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Выйти</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white border-0 rounded-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Статистика ставок</h5>
                    <p class="card-text">Рассчитано ставок (всего): <strong>{{ $betsCount ?? 0 }}</strong></p>
                    <!-- Можно добавить winrate и другие показатели, если есть связь с пользователем -->
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white border-0 rounded-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Подписка</h5>
                    <p class="card-text">Текущий тариф: <strong>Бесплатный</strong></p>
                    <p class="card-text">Доступ к AI-прогнозам: 
                        @if($user->canAccessAiPredictions())
                            <span class="badge bg-success">Открыт</span>
                        @else
                            <span class="badge bg-secondary">Закрыт</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection