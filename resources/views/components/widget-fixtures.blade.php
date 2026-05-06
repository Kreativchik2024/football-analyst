{{-- resources/views/components/widget-fixtures.blade.php --}}
@props(['title' => 'Матчи', 'fixtures' => [], 'type' => 'scheduled']) {{-- type: scheduled / finished --}}

@php
    $headerBg = '#1a1a2e'; // Тёмно-синий, как у API-SPORTS (можно заменить на #2c3e50)
@endphp

<div class="api-widget-fixtures">
    <div class="api-widget-fixtures__header" style="background: {{ $headerBg }};">
        <h4 class="api-widget-fixtures__title">{{ $title }}</h4>
    </div>
    <div class="api-widget-fixtures__body">
        @if($fixtures->isEmpty())
            <div class="api-widget-fixtures__empty">
                <p>Нет доступных матчей.</p>
            </div>
        @else
            @foreach($fixtures as $fixture)
                @php
                    // Определяем статус матча (короткий код)
                    $statusShort = $fixture->status;
                    $statusClass = match($statusShort) {
                        'NS' => 'badge bg-secondary', // Not Started
                        'LIVE','1H','2H','HT' => 'badge bg-danger live-blink', // Live с анимацией
                        'FT','AET','PEN' => 'badge bg-success', // Finished
                        default => 'badge bg-info'
                    };
                    $statusText = match($statusShort) {
                        'NS' => \Carbon\Carbon::parse($fixture->starting_at)->format('H:i'),
                        'LIVE','1H','2H','HT' => 'LIVE',
                        'FT' => 'Завершён',
                        'AET' => 'Доп. вр.',
                        'PEN' => 'Пен.',
                        default => $statusShort
                    };

                    // Определяем, кто победил (для жирного счёта)
                    $homeWinner = $fixture->home_score > $fixture->away_score;
                    $awayWinner = $fixture->away_score > $fixture->home_score;
                @endphp
                <div class="api-widget-fixtures__row">
                    <div class="api-widget-fixtures__status">
                        <span class="{{ $statusClass }}">{{ $statusText }}</span>
                    </div>
                    <div class="api-widget-fixtures__match">
                        <span class="api-widget-fixtures__team api-widget-fixtures__team--home
                            @if($homeWinner) fw-bold @endif">
                            {{ $fixture->homeTeam->name ?? '—' }}
                        </span>
                        <span class="api-widget-fixtures__score">
                            @if($statusShort == 'NS')
                                <span class="text-muted">vs</span>
                            @else
                                <span @if($homeWinner) class="fw-bold" @endif>{{ $fixture->home_score ?? '0' }}</span>
                                <span class="mx-1">–</span>
                                <span @if($awayWinner) class="fw-bold" @endif>{{ $fixture->away_score ?? '0' }}</span>
                            @endif
                        </span>
                        <span class="api-widget-fixtures__team api-widget-fixtures__team--away
                            @if($awayWinner) fw-bold @endif">
                            {{ $fixture->awayTeam->name ?? '—' }}
                        </span>
                    </div>
                    <div class="api-widget-fixtures__extra">
                        @if($statusShort == 'NS')
                            <small class="text-muted">{{ \Carbon\Carbon::parse($fixture->starting_at)->format('d.m H:i') }}</small>
                        @endif
                        @if($fixture->home_xg !== null && $fixture->away_xg !== null)
                            <small class="text-muted ms-1">xG {{ $fixture->home_xg }}–{{ $fixture->away_xg }}</small>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>

<style>
    /* Основной контейнер виджета */
    .api-widget-fixtures {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    /* Заголовок */
    .api-widget-fixtures__header {
        padding: 12px 16px;
        color: #ffffff;
    }
    .api-widget-fixtures__title {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }
    /* Тело виджета */
    .api-widget-fixtures__body {
        background: #fff;
    }
    /* Сообщение о пустом списке */
    .api-widget-fixtures__empty {
        padding: 16px;
        color: #888;
    }
    /* Строка одного матча */
    .api-widget-fixtures__row {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.15s;
    }
    .api-widget-fixtures__row:nth-child(even) {
        background: #fbfbfb;
    }
    .api-widget-fixtures__row:hover {
        background: #f4f7fc;
    }
    /* Статус */
    .api-widget-fixtures__status {
        width: 60px;
        flex-shrink: 0;
    }
    .badge {
        font-size: 12px;
        padding: 3px 8px;
        border-radius: 4px;
    }
    /* Анимация мигания для LIVE */
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    .live-blink {
        animation: blink 1.5s infinite;
    }
    /* Секция матча (названия и счёт) */
    .api-widget-fixtures__match {
        flex-grow: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 0 12px;
    }
    .api-widget-fixtures__team {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 140px;
    }
    .api-widget-fixtures__team--home {
        text-align: right;
        flex: 1;
    }
    .api-widget-fixtures__team--away {
        text-align: left;
        flex: 1;
    }
    .api-widget-fixtures__score {
        font-weight: 600;
        font-size: 15px;
        white-space: nowrap;
        margin: 0 8px;
    }
    /* Дополнительная информация (дата/время) */
    .api-widget-fixtures__extra {
        width: 70px;
        flex-shrink: 0;
        text-align: right;
    }
    /* Адаптивность */
    @media (max-width: 576px) {
        .api-widget-fixtures__team {
            max-width: 90px;
        }
        .api-widget-fixtures__extra {
            display: none;
        }
    }
</style>