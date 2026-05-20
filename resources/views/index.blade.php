@extends('layouts.app')

@section('title', 'DeepOdds — Главная')

@section('content')
<div class="container-fluid px-3 py-2">

    {{-- Hero-блок --}}
    <div class="row mb-4">
        <div class="col-12 text-center py-4 rounded-4 shadow-sm" style="background: linear-gradient(135deg, #ffffff, #f1f3f5);">
            <h1 class="display-5 fw-bold text-dark mb-2">⚽ DeepOdds</h1>
            <p class="lead text-secondary mb-3">Глубинная аналитика футбольных матчей и поиск валуйных ставок с помощью AI</p>
            <div class="d-flex justify-content-center gap-4">
                <div class="text-center">
                    <span class="fs-4 fw-bold text-warning">{{ $winRate }}%</span>
                    <small class="d-block text-secondary">Винрейт AI</small>
                </div>
                <div class="text-center">
                    <span class="fs-4 fw-bold text-primary">{{ $upcomingFixtures->count() }}</span>
                    <small class="d-block text-secondary">Матчей сегодня</small>
                </div>
                <div class="text-center">
                    <span class="fs-4 fw-bold text-success">{{ $recentBets->count() }}</span>
                    <small class="d-block text-secondary">Активных ставок</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Основная сетка --}}
    <div class="row g-3">
        {{-- Левая колонка: Матчи (по 3) --}}
        <div class="col-lg-4 d-flex flex-column" style="max-height: calc(100vh - 200px);">
            <div class="card bg-white shadow-sm border-0 rounded-4 mb-3 flex-grow-1 overflow-auto">
                <div class="card-header bg-transparent border-0 pt-3 pb-1">
                    <h6 class="text-primary small fw-bold mb-0">📅 Предстоящие матчи (24ч)</h6>
                </div>
                <div class="card-body pt-0 px-2 pb-2">
                    @include('partials.fixtures_list', ['fixtures' => $upcomingFixtures])
                </div>
            </div>
            <div class="card bg-white shadow-sm border-0 rounded-4 flex-grow-1 overflow-auto">
                <div class="card-header bg-transparent border-0 pt-3 pb-1">
                    <h6 class="text-success small fw-bold mb-0">⏪ Прошедшие матчи (24ч)</h6>
                </div>
                <div class="card-body pt-0 px-2 pb-2">
                    @include('partials.fixtures_list', ['fixtures' => $pastFixtures])
                </div>
            </div>
        </div>

        {{-- Центральная колонка: AI-ассистент + Новости --}}
        <div class="col-lg-4 d-flex flex-column" style="max-height: calc(100vh - 200px);">
            {{-- AI-ассистент --}}
            <div class="card bg-white shadow-sm border-0 rounded-4 mb-3 flex-grow-1 overflow-auto">
                <div class="card-header bg-transparent border-0 pt-3 pb-1">
                    <h6 class="text-warning small fw-bold mb-0">📊 AI-ассистент (винрейт: {{ $winRate }}%)</h6>
                </div>
                <div class="card-body pt-0 px-2 pb-2">
                    <div class="list-group list-group-flush small">
                        @forelse($recentBets as $bet)
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-2 border-0">
                                <div>
                                    <span class="fw-semibold">
                                        {{ $bet->fixture->homeTeam->name ?? '?' }} — {{ $bet->fixture->awayTeam->name ?? '?' }}
                                    </span>
                                    <br>
                                    <span class="badge bg-info text-dark">
                                        {{ $bet->bet_type == 'home' ? 'П1' : ($bet->bet_type == 'draw' ? 'X' : 'П2') }}
                                    </span>
                                    <small class="text-muted ms-1">Кэф {{ $bet->odd->value ?? '-' }}</small>
                                </div>
                                <span class="badge {{ $bet->profit > 0 ? 'bg-success' : 'bg-danger' }}">
                                    {{ $bet->profit > 0 ? '+' . number_format($bet->profit, 2) : number_format($bet->profit, 2) }}
                                </span>
                            </div>
                        @empty
                            <p class="text-muted text-center my-2">Нет рассчитанных ставок</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Новости (сетка 3x2) --}}
            <div class="card bg-white shadow-sm border-0 rounded-4 flex-grow-1 overflow-auto">
                <div class="card-header bg-transparent border-0 pt-3 pb-1">
                    <h6 class="text-info small fw-bold mb-0">📰 Новости футбола</h6>
                </div>
                <div class="card-body pt-0 px-2 pb-2">
                    <div class="row g-2">
                        @forelse($latestNews as $news)
                            <div class="col-md-6">
                                <div class="card border-1 shadow-sm h-100">
                                    <div class="card-body p-2">
                                        <a href="{{ $news->url }}" target="_blank" class="text-decoration-none fw-semibold text-dark small d-block">
                                            {{ Str::limit($news->title, 50) }}
                                        </a>
                                        <p class="text-muted mb-1 small" style="font-size:0.75rem;">{{ Str::limit($news->content, 80) }}</p>
                                        <div class="text-muted" style="font-size:0.65rem;">{{ $news->published_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-center my-2 small">Новостей пока нет</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Правая колонка: Видео (увеличенное) --}}
        <div class="col-lg-4 d-flex flex-column" style="max-height: calc(100vh - 200px);">
            <div class="card bg-white shadow-sm border-0 rounded-4 flex-grow-1 overflow-hidden">
                <div class="card-header bg-transparent border-0 pt-3 pb-1">
                    <h6 class="text-danger small fw-bold mb-0">🎬 Видео</h6>
                </div>
                <div class="card-body p-2 d-flex align-items-center justify-content-center" style="background: #000;">
            <div class="ratio ratio-16x9 overflow-hidden rounded-3" style="background: #000; border-radius: 12px;">
    @if(!empty($embedCode))
        {{-- Для пользовательского видео (может быть Rutube) --}}
        {!! $embedCode !!}
    @else
        <iframe src="https://www.youtube.com/embed/videoseries?list=PLQ_voP4Q3cfc-2j2QfHkC-5FjYJZ9p0vO&autoplay=1&mute=1" 
                frameborder="0" allow="autoplay; encrypted-media" allowfullscreen>
        </iframe>
    @endif
</div>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
    // Функция для автозапуска видео через API Rutube
    function autoPlayRutube() {
        // Ищем iframe с rutube
        const iframe = document.querySelector('iframe[src*="rutube.ru"]');
        if (iframe && iframe.contentWindow) {
            // Отправляем сообщение плееру: начать воспроизведение с muted
            iframe.contentWindow.postMessage(JSON.stringify({
                type: 'player:play',
                data: {
                    mute: true
                }
            }), '*');
        }
    }

    // Пытаемся запустить после полной загрузки страницы
    window.addEventListener('load', function() {
        // Rutube может инициализироваться с задержкой, поэтому пробуем несколько раз
        setTimeout(autoPlayRutube, 1000);
        setTimeout(autoPlayRutube, 2000);
        setTimeout(autoPlayRutube, 3000);
    });
</script>
@endpush
@endsection