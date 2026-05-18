@extends('layouts.app')

@section('title', 'AI‑прогнозы | Машинное обучение')

@section('content')
<div class="bg-gray-50 min-h-screen py-8 px-4 sm:px-6 lg:px-8 font-sans antialiased">
    <div class="max-w-7xl mx-auto">
        {{-- Заголовок и кнопка обновления --}}
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    🤖 AI‑прогнозы
                </h1>
                <p class="text-gray-500 mt-1 text-sm">
                    Машинное обучение · Ансамбль моделей · Статистика на завершённых матчах
                </p>
            </div>
            <button id="refreshPredictionsBtn"
                class="inline-flex items-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl shadow-sm transition-all duration-200 gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Обновить прогнозы
            </button>
        </div>

        {{-- Статистика моделей --}}
        @php
            $myAgents = ['xgboost', 'orchestrator', 'ml_model', 'sarimax', 'openai_news'];
            $filteredStats = array_intersect_key($agentStats, array_flip($myAgents));
        @endphp

        <div class="mb-12">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2 mb-5">
                📈 Точность моделей
                <span class="text-xs font-normal text-gray-400">(по завершённым матчам)</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                @foreach($filteredStats as $agent => $stat)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 transition hover:shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-xs uppercase tracking-wide font-mono text-indigo-600 font-semibold">{{ $agent }}</div>
                                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stat['accuracy'] }}<span class="text-base font-normal text-gray-400">%</span></div>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                ✅ {{ $stat['correct'] }} / {{ $stat['total'] }}
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $stat['accuracy'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Матчи --}}
        <div class="space-y-6">
            @forelse($upcomingFixtures as $fixture)
                @php
                    $predictions = $fixture->predictions->whereIn('agent_type', $myAgents);
                    $ensemble = $fixture->ensemblePrediction;
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition hover:shadow-md">
                    {{-- Хедер матча --}}
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex flex-wrap justify-between items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">⚽</span>
                            <span class="font-bold text-lg text-gray-800">{{ $fixture->homeTeam->name }}</span>
                            <span class="text-gray-400">vs</span>
                            <span class="font-bold text-lg text-gray-800">{{ $fixture->awayTeam->name }}</span>
                        </div>
                        <div class="text-sm text-gray-500 font-mono">
                            {{ $fixture->starting_at->format('d.m.Y H:i') }}
                        </div>
                    </div>

                    {{-- Прогнозы моделей --}}
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($predictions as $pred)
                                @php
                                    $probs = [
                                        'home' => $pred->home_probability,
                                        'draw' => $pred->draw_probability,
                                        'away' => $pred->away_probability,
                                    ];
                                    $maxProb = max($probs);
                                    $predictedOutcome = array_keys($probs, $maxProb)[0];
                                    $outcomeText = $predictedOutcome === 'home' ? $fixture->homeTeam->name : ($predictedOutcome === 'away' ? $fixture->awayTeam->name : 'Ничья');
                                @endphp
                                <div class="bg-gray-50 rounded-xl p-4 border-l-4 {{ $maxProb >= 0.6 ? 'border-indigo-400' : 'border-transparent' }}">
                                    <div class="text-xs font-mono text-indigo-600 font-semibold uppercase tracking-wide">{{ $pred->agent_type }}</div>
                                    <div class="mt-3 space-y-2">
                                        <div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-700">🏠 П1</span>
                                                <span class="font-medium text-gray-800">{{ round($probs['home']*100) }}%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $probs['home']*100 }}%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-700">🤝 X</span>
                                                <span class="font-medium text-gray-800">{{ round($probs['draw']*100) }}%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                                <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ $probs['draw']*100 }}%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-700">✈️ П2</span>
                                                <span class="font-medium text-gray-800">{{ round($probs['away']*100) }}%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                                <div class="bg-rose-500 h-1.5 rounded-full" style="width: {{ $probs['away']*100 }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 pt-2 border-t border-gray-200 text-center">
                                        <span class="font-semibold text-indigo-700">🎯 {{ $outcomeText }} ({{ round($maxProb*100) }}%)</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Ансамбль (оркестратор) --}}
                        @if($ensemble)
                            @php
                                $ensProbs = [
                                    'home' => $ensemble->home_probability,
                                    'draw' => $ensemble->draw_probability,
                                    'away' => $ensemble->away_probability,
                                ];
                                $ensMax = max($ensProbs);
                                $ensOutcome = array_keys($ensProbs, $ensMax)[0];
                                $ensOutcomeText = $ensOutcome === 'home' ? $fixture->homeTeam->name : ($ensOutcome === 'away' ? $fixture->awayTeam->name : 'Ничья');
                            @endphp
                            <div class="mt-6 p-5 rounded-xl bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-xl">⭐</span>
                                    <span class="font-bold text-indigo-800">Финальный прогноз (ансамбль моделей)</span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center text-sm font-medium">
                                    <div>
                                        <div class="text-gray-600">{{ $fixture->homeTeam->name }}</div>
                                        <div class="text-lg font-bold text-gray-800">{{ round($ensProbs['home']*100) }}%</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-600">Ничья</div>
                                        <div class="text-lg font-bold text-gray-800">{{ round($ensProbs['draw']*100) }}%</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-600">{{ $fixture->awayTeam->name }}</div>
                                        <div class="text-lg font-bold text-gray-800">{{ round($ensProbs['away']*100) }}%</div>
                                    </div>
                                </div>
                                <div class="mt-3 text-center font-semibold text-indigo-800">
                                    → {{ $ensOutcomeText }} (уверенность {{ round($ensMax*100) }}%)
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="mt-2 text-gray-500">Нет предстоящих матчей в следующие 7 дней</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<script>
    (function() {
        const refreshBtn = document.getElementById('refreshPredictionsBtn');
        if (!refreshBtn) return;
        refreshBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            const originalHtml = refreshBtn.innerHTML;
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Обновление...';
            try {
                const response = await fetch('/ai/refresh-predictions');
                const data = await response.json();
                if (data.saved !== undefined || data.message) {
                    window.location.reload();
                } else {
                    alert('Не удалось обновить прогнозы');
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = originalHtml;
                }
            } catch (err) {
                console.error(err);
                alert('Ошибка соединения с сервером');
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = originalHtml;
            }
        });
    })();
</script>
@endsection