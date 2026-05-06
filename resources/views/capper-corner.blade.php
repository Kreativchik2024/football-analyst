@extends('layouts.app')

@section('title', 'Уголок капера')

@section('content')
<div class="container-fluid py-3">
    <h2 class="mb-4">🎯 Уголок капера</h2>

    <div class="row g-3">
        {{-- Топ пользователей --}}
        <div class="col-md-3">
            <div class="card bg-dark text-white rounded-4">
                <div class="card-header">🏆 Топ-10 каперов</div>
                <ul class="list-group list-group-flush">
                    @foreach($topUsers as $item)
                        <li class="list-group-item bg-dark text-white d-flex justify-content-between">
                            <span>{{ optional($item->user)->name ?? 'Аноним' }}</span>
                            <span class="badge bg-warning text-dark">💰 {{ number_format($item->balance, 2, ',', ' ') }} ₽</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Предстоящие матчи + форма ставки --}}
        <div class="col-md-6">
            <h5>📅 Предстоящие матчи</h5>
            @foreach($upcomingFixtures as $fixture)
                <div class="card mb-2 bg-light rounded-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $fixture->homeTeam->name ?? '?' }}</strong>
                            vs
                            <strong>{{ $fixture->awayTeam->name ?? '?' }}</strong>
                            <div class="small text-muted">
                                {{ \Carbon\Carbon::parse($fixture->starting_at)->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <div>
                            @auth
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#betModal" 
                                    data-fixture-id="{{ $fixture->id }}"
                                    data-home="{{ $fixture->homeTeam->name }}" data-away="{{ $fixture->awayTeam->name }}">
                                    Сделать ставку
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Войти для ставки</a>
                            @endauth
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Ставки AI --}}
        <div class="col-md-3">
            {{-- Баланс AI --}}
            <div class="card bg-dark text-white rounded-4 mb-2">
                <div class="card-header">🤖 Баланс AI: {{ number_format($aiBalance, 2, ',', ' ') }} ₽</div>
            </div>

            <div class="card bg-dark text-white rounded-4">
                <div class="card-header">🤖 Ставки AI</div>
                <div class="card-body p-2">
                    @foreach($aiBets as $bet)
                        <div class="border-bottom py-1 small">
                            <strong>{{ $bet->fixture->homeTeam->name ?? '?' }} vs {{ $bet->fixture->awayTeam->name ?? '?' }}</strong><br>
                            {{ $bet->bet_type == 'home' ? 'П1' : ($bet->bet_type == 'draw' ? 'X' : 'П2') }}
                            @ Кэф {{ $bet->odd->value ?? '-' }}
                            <span class="text-success">EV {{ number_format($bet->expected_value, 3, ',', ' ') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Последние ставки пользователей --}}
            <div class="card bg-dark text-white rounded-4 mt-2">
                <div class="card-header">👥 Последние ставки</div>
                <div class="card-body p-2 small">
                    @foreach($latestUserBets as $bet)
                        <div class="border-bottom py-1">
                            <strong>{{ $bet->user->name }}</strong>
                            поставил {{ $bet->stake }} на
                            {{ $bet->bet_type == 'home' ? 'П1' : ($bet->bet_type == 'draw' ? 'X' : 'П2') }}
                            в матче {{ $bet->fixture->homeTeam->name ?? '?' }} vs {{ $bet->fixture->awayTeam->name ?? '?' }}
                            <span class="text-{{ $bet->status == 'won' ? 'success' : ($bet->status == 'lost' ? 'danger' : 'muted') }}">
                                {{ $bet->status == 'pending' ? 'ожидает' : $bet->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Модальное окно для ставки --}}
@auth
<div class="modal fade" id="betModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('capper.bet') }}" class="modal-content">
            @csrf
            <input type="hidden" name="fixture_id" id="modalFixtureId">
            <div class="modal-header">
                <h5 class="modal-title">Ставка на матч</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="modalMatchName"></p>
                <select name="bet_type" class="form-select mb-2">
                    <option value="home">П1</option>
                    <option value="draw">X</option>
                    <option value="away">П2</option>
                </select>
                <input type="number" name="stake" class="form-control" placeholder="Сумма" min="1" required>
                <div class="small mt-1">Ваш баланс: {{ number_format($userBalance ?? 100000, 2, ',', ' ') }} ₽</div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Разместить</button>
            </div>
        </form>
    </div>
</div>

<script>
    const betModal = document.getElementById('betModal');
    betModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        document.getElementById('modalFixtureId').value = button.dataset.fixtureId;
        document.getElementById('modalMatchName').textContent = button.dataset.home + ' vs ' + button.dataset.away;
    });
</script>
@endauth
@endsection