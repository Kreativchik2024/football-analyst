import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { FiRefreshCw, FiTrendingUp, FiBarChart2, FiCalendar, FiCpu, FiZap } from 'react-icons/fi';
import { FaRobot, FaBrain, FaChartLine, FaNewspaper, FaStar } from 'react-icons/fa';

// ========== Иконки для моделей ==========
const agentIcons = {
    xgboost: <FaChartLine className="text-blue-500" />,
    orchestrator: <FaBrain className="text-purple-500" />,
    ml_model: <FaRobot className="text-green-500" />,
    sarimax: <FiTrendingUp className="text-orange-500" />,
    openai_news: <FaNewspaper className="text-pink-500" />,
};

// ========== Компонент статистики модели ==========
const ModelStatCard = ({ agent, stat }) => {
    const accuracy = stat.accuracy;
    const isHigh = accuracy >= 70;
    return (
        <div className="group bg-white rounded-2xl shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-300 p-5 hover:-translate-y-1">
            <div className="flex justify-between items-start">
                <div className="flex items-center gap-2">
                    <div className="text-2xl">{agentIcons[agent] || <FiCpu />}</div>
                    <div>
                        <div className="text-xs uppercase tracking-wider font-bold text-indigo-600">{agent}</div>
                        <div className="text-3xl font-extrabold text-gray-900 mt-1">{accuracy}<span className="text-base font-normal text-gray-400">%</span></div>
                    </div>
                </div>
                <div className="text-right text-sm font-medium text-gray-500">
                    ✅ {stat.correct}/{stat.total}
                </div>
            </div>
            <div className="mt-4">
                <div className="relative w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                    <div
                        className={`absolute left-0 top-0 h-full rounded-full transition-all duration-700 ${
                            isHigh ? 'bg-gradient-to-r from-indigo-500 to-purple-500' : 'bg-indigo-400'
                        }`}
                        style={{ width: `${accuracy}%` }}
                    />
                </div>
            </div>
        </div>
    );
};

// ========== Компонент прогноза одной модели ==========
const PredictionCard = ({ prediction, homeTeam, awayTeam }) => {
    const { home_probability, draw_probability, away_probability, agent_type } = prediction;
    const probs = { home: home_probability, draw: draw_probability, away: away_probability };
    const maxKey = Object.keys(probs).reduce((a, b) => (probs[a] > probs[b] ? a : b));
    const outcomeText = maxKey === 'home' ? homeTeam : (maxKey === 'away' ? awayTeam : 'Ничья');
    const maxProb = probs[maxKey];
    const confidence = Math.round(maxProb * 100);
    const isConfident = maxProb >= 0.6;

    return (
        <div className={`bg-gray-50 rounded-xl p-4 border-l-4 transition-all hover:shadow-md ${
            isConfident ? 'border-indigo-400' : 'border-transparent'
        }`}>
            <div className="flex items-center gap-2 text-xs font-mono font-bold text-indigo-600 uppercase tracking-wide">
                {agentIcons[agent_type] || <FiCpu size={12} />}
                {agent_type}
            </div>
            <div className="mt-3 space-y-3">
                {[
                    { label: '🏠 П1', prob: home_probability, color: 'bg-blue-500' },
                    { label: '🤝 X', prob: draw_probability, color: 'bg-amber-500' },
                    { label: '✈️ П2', prob: away_probability, color: 'bg-rose-500' },
                ].map(({ label, prob, color }) => (
                    <div key={label}>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-700">{label}</span>
                            <span className="font-semibold text-gray-800">{Math.round(prob * 100)}%</span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-1.5 mt-1 overflow-hidden">
                            <div className={`${color} h-full rounded-full transition-all duration-500`} style={{ width: `${prob * 100}%` }} />
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-4 pt-2 border-t border-gray-200 text-center">
                <span className={`font-bold ${isConfident ? 'text-indigo-700' : 'text-gray-600'}`}>
                    🎯 {outcomeText} ({confidence}%)
                </span>
            </div>
        </div>
    );
};

// ========== Компонент ансамбля ==========
const EnsembleCard = ({ ensemble, homeTeam, awayTeam }) => {
    if (!ensemble) return null;
    const { home_probability, draw_probability, away_probability } = ensemble;
    const probs = { home: home_probability, draw: draw_probability, away: away_probability };
    const maxKey = Object.keys(probs).reduce((a, b) => (probs[a] > probs[b] ? a : b));
    const outcomeText = maxKey === 'home' ? homeTeam : (maxKey === 'away' ? awayTeam : 'Ничья');
    const confidence = Math.round(probs[maxKey] * 100);

    return (
        <div className="mt-6 p-5 rounded-xl bg-gradient-to-r from-indigo-50 via-white to-blue-50 border border-indigo-100 shadow-inner">
            <div className="flex items-center gap-2 mb-4">
                <FaStar className="text-yellow-500 text-xl" />
                <span className="font-bold text-indigo-800 text-lg">Ансамбль моделей</span>
                <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">финальный прогноз</span>
            </div>
            <div className="grid grid-cols-3 gap-3 text-center">
                {[
                    { label: homeTeam, prob: home_probability, color: 'from-blue-500 to-blue-600' },
                    { label: 'Ничья', prob: draw_probability, color: 'from-amber-500 to-amber-600' },
                    { label: awayTeam, prob: away_probability, color: 'from-rose-500 to-rose-600' },
                ].map((item) => (
                    <div key={item.label} className="bg-white/50 rounded-lg p-2 backdrop-blur-sm">
                        <div className="text-sm font-medium text-gray-600">{item.label}</div>
                        <div className="text-2xl font-extrabold text-gray-800">{Math.round(item.prob * 100)}%</div>
                    </div>
                ))}
            </div>
            <div className="mt-4 text-center">
                <div className="inline-flex items-center gap-2 bg-indigo-100 px-4 py-1.5 rounded-full">
                    <FiZap className="text-indigo-600" />
                    <span className="font-semibold text-indigo-800">Прогноз: {outcomeText} (уверенность {confidence}%)</span>
                </div>
            </div>
        </div>
    );
};

// ========== Карточка матча ==========
const MatchCard = ({ fixture }) => {
    const { home_team, away_team, starting_at, predictions, ensemble } = fixture;
    const hasPredictions = predictions && predictions.length > 0;

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
            <div className="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100 flex flex-wrap justify-between items-center gap-3">
                <div className="flex items-center gap-2">
                    <span className="text-2xl">⚽</span>
                    <span className="font-bold text-lg text-gray-800">{home_team.name}</span>
                    <span className="text-gray-400 text-sm font-medium">vs</span>
                    <span className="font-bold text-lg text-gray-800">{away_team.name}</span>
                </div>
                <div className="flex items-center gap-1 text-sm text-gray-500 font-mono bg-gray-100 px-3 py-1 rounded-full">
                    <FiCalendar size={14} />
                    {starting_at}
                </div>
            </div>

            <div className="p-6">
                {!hasPredictions ? (
                    <div className="text-center py-8 text-gray-400">Нет прогнозов для этого матча</div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {predictions.map((pred, idx) => (
                                <PredictionCard
                                    key={idx}
                                    prediction={pred}
                                    homeTeam={home_team.name}
                                    awayTeam={away_team.name}
                                />
                            ))}
                        </div>
                        {ensemble && <EnsembleCard ensemble={ensemble} homeTeam={home_team.name} awayTeam={away_team.name} />}
                    </>
                )}
            </div>
        </div>
    );
};

// ========== Основная страница ==========
export default function Predictions() {
    const { agentStats, upcomingFixtures } = usePage().props;
    const [refreshing, setRefreshing] = useState(false);
    const [localStats, setLocalStats] = useState(agentStats);
    const [localFixtures, setLocalFixtures] = useState(upcomingFixtures);

    const handleRefresh = async () => {
        setRefreshing(true);
        try {
            const response = await fetch('/ai/refresh-predictions', { method: 'POST' });
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();
            setLocalStats(data.agentStats);
            setLocalFixtures(data.upcomingFixtures);
            // Альтернатива: router.reload() – если не хотите писать API
        } catch (error) {
            console.error(error);
            alert('Не удалось обновить прогнозы. Попробуйте позже.');
        } finally {
            setRefreshing(false);
        }
    };

    const statsArray = Object.entries(localStats || {});

    return (
        <div className="bg-gradient-to-br from-gray-50 via-white to-gray-100 min-h-screen py-8 px-4 sm:px-6 lg:px-8 font-sans antialiased">
            <div className="max-w-7xl mx-auto">
                {/* Заголовок */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-12">
                    <div>
                        <h1 className="text-4xl font-black tracking-tight bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                            🤖 AI‑прогнозы
                        </h1>
                        <p className="text-gray-500 mt-1 flex items-center gap-1">
                            <FiBarChart2 className="inline" />
                            Машинное обучение · Ансамбль моделей · Статистика на завершённых матчах
                        </p>
                    </div>
                    <button
                        onClick={handleRefresh}
                        disabled={refreshing}
                        className={`inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-medium shadow-md transition-all duration-200 ${
                            refreshing
                                ? 'bg-gray-300 cursor-not-allowed text-gray-500'
                                : 'bg-indigo-600 hover:bg-indigo-700 text-white hover:shadow-lg active:scale-95'
                        }`}
                    >
                        <FiRefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
                        {refreshing ? 'Обновление...' : 'Обновить прогнозы'}
                    </button>
                </div>

                {/* Статистика моделей */}
                {statsArray.length > 0 && (
                    <div className="mb-14">
                        <h2 className="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
                            <FiTrendingUp className="text-indigo-500" />
                            Точность моделей
                            <span className="text-sm font-normal text-gray-400">(по завершённым матчам)</span>
                        </h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                            {statsArray.map(([agent, stat]) => (
                                <ModelStatCard key={agent} agent={agent} stat={stat} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Список матчей */}
                {localFixtures?.length === 0 ? (
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 py-16 text-center">
                        <div className="text-6xl mb-4">📅</div>
                        <p className="text-gray-500 text-lg">Нет предстоящих матчей в следующие 7 дней</p>
                    </div>
                ) : (
                    <div className="space-y-8">
                        {localFixtures.map((fixture) => (
                            <MatchCard key={fixture.id} fixture={fixture} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}