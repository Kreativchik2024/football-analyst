@extends('layouts.app')

@section('title', 'AI Прогнозы')

@section('content')
<div class="container-fluid py-3">
    <h2 class="mb-4">🤖 AI Прогнозы на ближайшие матчи</h2>

    {{-- Сводка агентов --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-dark text-white rounded-top-4">
                    <h5 class="mb-0">📊 Статистика агентов</h5>
                </div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Агент</th>
                                    <th class="text-center">Прогнозов</th>
                                    <th class="text-center">Верных</th>
                                    <th class="text-center">Точность</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agentStats as $type => $stat)
                                    <tr>
                                        <td>
                                            @switch($type)
                                                @case('api_football') API‑Football @break
                                                @case('market') Рыночный @break
                                                @case('ml_model') ML‑XGBoost @break
                                                @case('sarimax') SARIMAX @break
                                                @case('openai_news') OpenAI‑News @break
                                                @default {{ $type }}
                                            @endswitch
                                        </td>
                                        <td class="text-center">{{ $stat['total'] }}</td>
                                        <td class="text-center">{{ $stat['correct'] }}</td>
                                        <td class="text-center fw-bold">{{ $stat['accuracy'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Прогнозы по матчам --}}
    @if($upcomingFixtures->isEmpty())
        <div class="alert alert-info">Нет предстоящих матчей.</div>
    @else
        @foreach($upcomingFixtures as $fixture)
            <div class="card shadow-sm border-0 rounded-4 mb-3">
                <div class="card-header bg-secondary text-white">
                    <strong>{{ $fixture->homeTeam->name ?? '?' }}</strong>
                    vs
                    <strong>{{ $fixture->awayTeam->name ?? '?' }}</strong>
                    <span class="float-end">{{ \Carbon\Carbon::parse($fixture->starting_at)->format('d.m.Y H:i') }}</span>
                </div>
                <div class="card-body p-2">

                    {{-- КОНСЕНСУС-ПРОГНОЗ --}}
                    @if($fixture->ensemblePrediction)
                        <div class="border rounded-3 p-2 bg-info bg-opacity-10 mb-2">
                            <strong>🎯 Консенсус-прогноз</strong>
                            <div class="mt-1">
                                <span class="text-primary">{{ round($fixture->ensemblePrediction->home_probability * 100) }}%</span>
                                <span class="text-muted">/</span>
                                <span class="text-secondary">{{ round($fixture->ensemblePrediction->draw_probability * 100) }}%</span>
                                <span class="text-muted">/</span>
                                <span class="text-danger">{{ round($fixture->ensemblePrediction->away_probability * 100) }}%</span>
                            </div>
                        </div>
                    @endif

                    {{-- Прогнозы отдельных агентов --}}
                    @if($fixture->predictions->isEmpty())
                        <p class="text-muted small mb-0">Прогнозы ещё не сгенерированы.</p>
                    @else
                        <div class="row g-2">
                            @foreach($fixture->predictions->groupBy('agent_type') as $agentType => $agentPredictions)
                                @php $pred = $agentPredictions->first(); @endphp
                                <div class="col-md-4 col-lg-2">
                                    <div class="border rounded-3 p-2 h-100">
                                        <small class="text-muted">
                                            @switch($agentType)
                                                @case('api_football') API‑Football @break
                                                @case('market') Рыночный @break
                                                @case('ml_model') ML‑XGBoost @break
                                                @case('sarimax') SARIMAX @break
                                                @case('openai_news') OpenAI‑News @break
                                                @default {{ $agentType }}
                                            @endswitch
                                        </small>
                                        <div class="mt-1">
                                            <span class="text-primary">{{ round($pred->home_probability * 100) }}%</span>
                                            <span class="text-muted">/</span>
                                            <span class="text-secondary">{{ round($pred->draw_probability * 100) }}%</span>
                                            <span class="text-muted">/</span>
                                            <span class="text-danger">{{ round($pred->away_probability * 100) }}%</span>
                                        </div>
                                        @if($agentType === 'openai_news' && $pred->features_used)
                                            @php $features = json_decode($pred->features_used, true); @endphp
                                            @if(isset($features['rationale']))
                                                <p class="small mt-1 mb-0 text-muted">{{ Str::limit($features['rationale'], 60) }}</p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection