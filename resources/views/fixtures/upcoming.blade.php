@extends('layouts.app')

@section('title', 'Предстоящие матчи')

@section('content')
<div class="container-fluid py-3">
    <h2 class="mb-4">📅 Предстоящие матчи</h2>

    {{-- Форма фильтров --}}
    <form method="GET" action="{{ route('fixtures.upcoming') }}" class="row g-3 mb-4 bg-white p-3 rounded-4 shadow-sm">
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

    {{-- Таблица --}}
    <div class="table-responsive bg-white rounded-4 shadow-sm">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Дата</th>
                    <th>Лига</th>
                    <th>Хозяева</th>
                    <th>Гости</th>
                    <th class="text-center">Статус</th>
                    <th class="text-center">Коэф. (П1 / X / П2)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fixtures as $fixture)
                    @php
                        $bestOdds = $fixture->odds
                            ->where('market', '1x2')
                            ->groupBy('outcome')
                            ->map(fn($group) => $group->max('value'));
                        $statusShort = $fixture->status;
                        $statusText = match($statusShort) {
                            'NS' => \Carbon\Carbon::parse($fixture->starting_at)->format('H:i'),
                            'TBD' => 'Время уточняется',
                            'LIVE','1H','2H','HT' => 'LIVE',
                            default => $statusShort,
                        };
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($fixture->starting_at)->format('d.m.Y H:i') }}</td>
                        <td>{{ $fixture->league->name ?? '—' }}</td>
                        <td>{{ $fixture->homeTeam->name ?? '—' }}</td>
                        <td>{{ $fixture->awayTeam->name ?? '—' }}</td>
                        <td class="text-center">
                            @if(in_array($statusShort, ['LIVE','1H','2H','HT']))
                                <span class="badge bg-danger live-blink">LIVE</span>
                            @else
                                {{ $statusText }}
                            @endif
                        </td>
                        <td class="text-center">
                            {{ $bestOdds['home'] ?? '—' }} / {{ $bestOdds['draw'] ?? '—' }} / {{ $bestOdds['away'] ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Матчи не найдены</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {{ $fixtures->links() }}
    </div>
</div>
@endsection