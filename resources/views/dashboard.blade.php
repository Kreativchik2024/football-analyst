@extends('layouts.app')

@section('title', 'Личный кабинет — DeepOdds')

@section('content')
<div class="container py-5">
    {{-- Приветствие с градиентом --}}
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold bg-gradient bg-primary text-white d-inline-block px-4 py-2 rounded-4 shadow">
            👤 Личный кабинет
        </h1>
        <p class="text-muted mt-2">Управляйте своим профилем и статистикой</p>
    </div>

    <div class="row g-4">
        {{-- Карточка профиля --}}
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient bg-dark text-white py-3">
                    <h5 class="card-title mb-0"><i class="bi bi-person-circle me-2"></i> Профиль</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.8rem;">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        <div class="ms-3">
                            <h4 class="mb-0">{{ $user->name }}</h4>
                            <span class="badge bg-info text-dark mt-1">{{ $user->role ?? 'user' }}</span>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush bg-transparent">
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-envelope me-2"></i> Email</span>
                            <strong>{{ $user->email }}</strong>
                        </li>
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-calendar3 me-2"></i> Регистрация</span>
                            <strong>{{ $user->created_at->format('d.m.Y') }}</strong>
                        </li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent border-0 pb-4">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100 rounded-pill">
                            <i class="bi bi-box-arrow-right me-2"></i>Выйти
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Карточка статистики ставок --}}
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient bg-success text-white py-3">
                    <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i> Статистика ставок</h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                    <div class="display-1 fw-bold text-success">{{ $betsCount ?? 0 }}</div>
                    <p class="text-muted">всего рассчитано ставок</p>
                    <hr class="w-50">
                    <div class="mt-3 w-100">
                        <div class="d-flex justify-content-between">
                            <span>Winrate:</span>
                            <strong class="text-success">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span>Прибыль:</span>
                            <strong class="text-success">—</strong>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 0%"></div>
                        </div>
                        <small class="text-muted">*данные скоро появятся</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Карточка подписки и доступа --}}
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient bg-warning text-dark py-3">
                    <h5 class="card-title mb-0"><i class="bi bi-star-fill me-2"></i> Подписка</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <span class="badge bg-secondary fs-6 px-3 py-2 rounded-pill">Бесплатный тариф</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span>AI‑прогнозы:</span>
                        @if($user->canAccessAiPredictions())
                            <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i> Открыт</span>
                        @else
                            <span class="badge bg-secondary rounded-pill"><i class="bi bi-lock"></i> Закрыт</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span>Доступ к углу капера:</span>
                        <span class="badge bg-secondary rounded-pill"><i class="bi bi-lock"></i> Закрыт</span>
                    </div>
                    <div class="mt-4 text-center">
                        <button class="btn btn-outline-warning rounded-pill w-100" disabled>
                            <i class="bi bi-arrow-up-circle me-2"></i>Расширить подписку
                        </button>
                        <small class="text-muted d-block mt-2">Больше функций — в платной версии</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Дополнительный блок: недавние действия (заглушка) --}}
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-header bg-gradient bg-dark text-white py-3">
                    <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i> Недавние действия</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light text-center mb-0">
                        <i class="bi bi-info-circle me-2"></i> История ваших действий будет отображаться здесь.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Кастомные стили */
    .bg-gradient {
        background: linear-gradient(135deg, #1e2a3a 0%, #0f172a 100%);
    }
    .bg-gradient.bg-primary {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
    }
    .bg-gradient.bg-success {
        background: linear-gradient(135deg, #198754, #157347);
    }
    .bg-gradient.bg-warning {
        background: linear-gradient(135deg, #ffc107, #ffb700);
    }
    .bg-gradient.bg-dark {
        background: linear-gradient(135deg, #212529, #1a1e21);
    }
    .avatar {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
        font-weight: bold;
    }
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 2rem rgba(0,0,0,0.15) !important;
    }
    .list-group-item {
        border-color: rgba(0,0,0,0.05);
    }
    .btn-outline-danger {
        border-width: 2px;
    }
    .progress {
        background-color: #e9ecef;
    }
    @media (max-width: 768px) {
        .display-1 { font-size: 3rem; }
        .avatar { width: 50px; height: 50px; font-size: 1.5rem; }
    }
</style>

{{-- Bootstrap Icons (если не подключены глобально) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
@endsection