@forelse($fixtures as $fixture)
    @php
        $statusShort = $fixture->status;
        $homeWinner = $fixture->home_score > $fixture->away_score;
        $awayWinner = $fixture->away_score > $fixture->home_score;
    @endphp
    <div class="api-widget-fixtures__row">
        <div class="api-widget-fixtures__status">
            <span class="badge
                @if(in_array($statusShort, ['LIVE','1H','2H','HT'])) bg-danger live-blink
                @elseif(in_array($statusShort, ['FT','AET','PEN'])) bg-success
                @else bg-secondary @endif">
                @if($statusShort == 'NS')
                    {{ \Carbon\Carbon::parse($fixture->starting_at)->format('H:i') }}
                @elseif(in_array($statusShort, ['LIVE','1H','2H','HT']))
                    LIVE
                @else
                    Завершён
                @endif
            </span>
        </div>
        <div class="api-widget-fixtures__match">
            <span class="fw-bold @if($homeWinner) fw-bold @endif">{{ $fixture->homeTeam->name ?? '—' }}</span>
            <span class="mx-2">
                @if($statusShort == 'NS')
                    vs
                @else
                    <strong>{{ $fixture->home_score ?? '0' }}</strong> – <strong>{{ $fixture->away_score ?? '0' }}</strong>
                @endif
            </span>
            <span class="fw-bold @if($awayWinner) fw-bold @endif">{{ $fixture->awayTeam->name ?? '—' }}</span>
        </div>
        <div class="api-widget-fixtures__extra">
            <small>{{ \Carbon\Carbon::parse($fixture->starting_at)->format('d.m H:i') }}</small>
        </div>
    </div>
@empty
    <div class="alert alert-info">Нет матчей.</div>
@endforelse