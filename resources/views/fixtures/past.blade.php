@extends('layouts.app')

@section('title', 'Прошедшие матчи')

@section('content')
<div class="container-fluid py-3">
    <h2 class="mb-4">⏪ Прошедшие матчи</h2>

    {{-- Форма фильтров --}}
    <form method="GET" action="{{ route('fixtures.past') }}" class="row g-3 mb-4 bg-white p-3 rounded-4 shadow-sm">
        <div class="col-md-2">
            <label class="form-label small fw-bold">Страна</label>
            <select name="country" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Все страны</option>
                @foreach($countries as $c)
                    <option value="{{ $c }}" {{ $country == $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">Лига</label>
            <select name="league_id" class="form-select form-select-sm" {{ $country ? '' : 'disabled' }}>
                <option value="">Все лиги</option>
                @foreach($leagues as $league)
                    <option value="{{ $league->id }}" {{ $leagueId == $league->id ? 'selected' : '' }}>{{ $league->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">С даты</label>
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">По дату</label>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">Команда</label>
            <input type="text" name="team" class="form-control form-control-sm" placeholder="Название..." value="{{ $teamName }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm w-100">Применить</button>
        </div>
    </form>

    {{-- Таблица с детальной статистикой, коэффициентами и событиями --}}
    <div class="table-responsive bg-white rounded-4 shadow-sm">
        <table class="table table-hover align-middle mb-0 small text-nowrap">
            <thead class="table-light">
                <tr>
                    <th>Дата</th>
                    <th>Лига</th>
                    <th>Хозяева</th>
                    <th>Гости</th>
                    <th class="text-center">Счёт</th>
                    <th class="text-center">xG</th>
                    <th class="text-center">Влад.</th>
                    <th class="text-center">Удары</th>
                    <th class="text-center">Удары в створ</th>
                    <th class="text-center">Угловые</th>
                    <th class="text-center">Фолы</th>
                    <th class="text-center">Офсайды</th>
                    <th class="text-center">Пасы</th>
                    <th class="text-center">Коэф. (П1 / X / П2)</th>
                    <th class="text-center">События</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fixtures as $fixture)
                    @php
                        $stats = $fixture->matchStatistics->keyBy('stat_type');
                        // Лучшие коэффициенты (максимальные для каждого исхода)
                        $bestOdds = $fixture->odds
                            ->where('market', '1x2')
                            ->groupBy('outcome')
                            ->map(fn($group) => $group->max('value'));

                        // События: подсчёт замен, голов, карточек
                        $substitutions = $fixture->matchEvents->where('event_type', 'subst')->count();
                        $goals = $fixture->matchEvents->where('event_type', 'Goal')->count();
                        $cards = $fixture->matchEvents->where('event_type', 'Card')->count();
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($fixture->starting_at)->format('d.m.Y H:i') }}</td>
                        <td>{{ $fixture->league->name ?? '—' }}</td>
                        <td>{{ $fixture->homeTeam->name ?? '—' }}</td>
                        <td>{{ $fixture->awayTeam->name ?? '—' }}</td>
                        <td class="text-center fw-bold">{{ $fixture->home_score }} – {{ $fixture->away_score }}</td>
                        <td class="text-center">{{ $fixture->home_xg ?? '—' }} / {{ $fixture->away_xg ?? '—' }}</td>
                        <td class="text-center">{{ round($fixture->home_possession ?? 0) }}% / {{ round($fixture->away_possession ?? 0) }}%</td>
                        <td class="text-center">{{ round($stats['Total Shots']->home_value ?? 0) }} / {{ round($stats['Total Shots']->away_value ?? 0) }}</td>
                        <td class="text-center">{{ round($stats['Shots on Goal']->home_value ?? 0) }} / {{ round($stats['Shots on Goal']->away_value ?? 0) }}</td>
                        <td class="text-center">{{ round($stats['Corner Kicks']->home_value ?? 0) }} / {{ round($stats['Corner Kicks']->away_value ?? 0) }}</td>
                        <td class="text-center">{{ round($stats['Fouls']->home_value ?? 0) }} / {{ round($stats['Fouls']->away_value ?? 0) }}</td>
                        <td class="text-center">{{ round($stats['Offsides']->home_value ?? 0) }} / {{ round($stats['Offsides']->away_value ?? 0) }}</td>
                        <td class="text-center">{{ round($stats['Total passes']->home_value ?? 0) }} / {{ round($stats['Total passes']->away_value ?? 0) }}</td>
                        <td class="text-center">
                            {{ $bestOdds['home'] ?? '—' }} / {{ $bestOdds['draw'] ?? '—' }} / {{ $bestOdds['away'] ?? '—' }}
                        </td>
                        <td class="text-center">
                            <span title="Замены">🔄{{ $substitutions }}</span>
                            <span title="Голы">⚽{{ $goals }}</span>
                            <span title="Карточки">🟨{{ $cards }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="text-center text-muted py-4">Матчи не найдены</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {{ $fixtures->links() }}
    </div>
</div>
@endsection