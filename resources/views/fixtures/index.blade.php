<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Исторические матчи</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>📋 Исторические матчи</h2>

        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <label for="league_id" class="form-label">Лига</label>
                <select name="league_id" id="league_id" class="form-select" required>
                    <option value="">-- Выберите лигу --</option>
                    @foreach($leagues as $league)
                        <option value="{{ $league['external_id'] }}"
                            {{ $selectedLeague == $league['external_id'] ? 'selected' : '' }}>
                            {{ $league['country'] }} — {{ $league['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="from_date" class="form-label">Дата с</label>
                <input type="date" name="from_date" id="from_date" class="form-control"
                       value="{{ $fromDate }}">
            </div>
            <div class="col-md-3">
                <label for="to_date" class="form-label">Дата по</label>
                <input type="date" name="to_date" id="to_date" class="form-control"
                       value="{{ $toDate }}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Показать</button>
            </div>
        </form>

        @if(!empty($fixtures))
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Хозяева</th>
                            <th>Гости</th>
                            <th>Счет</th>
                            <th>xG (хозяева)</th>
                            <th>xG (гости)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fixtures as $item)
                            @php
                                $fixture = $item['fixture'];
                                $teams = $item['teams'];
                                $goals = $item['goals'];
                                $xg = $item['xg'] ?? ['home' => null, 'away' => null];
                            @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($fixture['date'])->format('d.m.Y H:i') }}</td>
                                <td>{{ $teams['home']['name'] }}</td>
                                <td>{{ $teams['away']['name'] }}</td>
                                <td><strong>{{ $goals['home'] ?? 0 }} – {{ $goals['away'] ?? 0 }}</strong></td>
                                <td>{{ $xg['home'] ?? '—' }}</td>
                                <td>{{ $xg['away'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($selectedLeague)
            <div class="alert alert-info">За выбранный период матчей не найдено.</div>
        @endif
    </div>
</body>
</html>