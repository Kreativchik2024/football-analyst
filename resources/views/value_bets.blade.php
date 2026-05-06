<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Валуйные ставки</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        h2 { margin-top: 20px; margin-bottom: 25px; color: #2c3e50; font-weight: 600; }
        .table thead { background: #2c3e50; color: white; }
        .badge-ev { background: #27ae60; color: white; padding: 4px 8px; border-radius: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>⚽ Найденные валуйные ставки (Value Bets)</h2>
        
        @if($bets->isEmpty())
            <div class="alert alert-info">На данный момент валуйных ставок не найдено.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <!-- В заголовке таблицы -->
                            <th>Страна</th>
                            <th>Матч</th>
                            <th>Ставка</th>
                            <th>Коэффициент</th>
                            <th>Вероятность<br><small class="fw-normal">(по версии API-Football)</small></th>
                            <th>EV<br><small class="fw-normal">(ожидаемая ценность)</small></th>
                            <th>Перевес</th>
                            <th>Букмекер</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bets as $bet)
                        <tr>
                            <!-- В теле таблицы -->
<td>
    @if($bet->odd->bookmaker->country)
        🇷🇺 {{ $bet->odd->bookmaker->country }}
    @else
        —
    @endif
</td>
                            <td>
                                <strong>{{ $bet->fixture->homeTeam->name }}</strong> 
                                vs 
                                <strong>{{ $bet->fixture->awayTeam->name }}</strong>
                                <br>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($bet->fixture->starting_at)->translatedFormat('d M Y, H:i') }}</small>
                            </td>
                            <td>
                                @if($bet->bet_type == 'home')
                                    <span class="badge bg-primary">Победа хозяев</span>
                                @elseif($bet->bet_type == 'draw')
                                    <span class="badge bg-secondary">Ничья</span>
                                @else
                                    <span class="badge bg-info text-dark">Победа гостей</span>
                                @endif
                            </td>
                            <td><strong>{{ $bet->odd->value }}</strong></td>
                            <td>
                                <div class="progress" style="height: 20px; width: 100px;">
                                    <div class="progress-bar bg-success" 
                                         role="progressbar" 
                                         style="width: {{ round($bet->prediction->{$bet->bet_type . '_probability'} * 100) }}%;" 
                                         aria-valuenow="{{ round($bet->prediction->{$bet->bet_type . '_probability'} * 100) }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        {{ round($bet->prediction->{$bet->bet_type . '_probability'} * 100) }}%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-ev">
                                    {{ $bet->expected_value > 0 ? '+' : '' }}{{ round($bet->expected_value, 3) }}
                                </span>
                            </td>
                            <td>
                                <span class="text-success fw-bold">
                                    +{{ round($bet->edge_percent, 1) }}%
                                </span>
                            </td>
                            <td>{{ $bet->odd->bookmaker->name ?? 'Неизвестно' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="alert alert-light border mt-3">
                <i class="bi bi-info-circle"></i> 
                <strong>Всего найдено ставок:</strong> {{ $bets->count() }}. 
                Отображаются только ставки с положительным ожиданием (EV > 0).
            </div>
        @endif
    </div>
</body>
</html>