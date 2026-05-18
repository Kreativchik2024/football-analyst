{{-- resources/views/about.blade.php --}}
@extends('layouts.app')

@section('title', 'О проекте | AI‑прогнозы')

@section('content')
<div class="bg-gradient-to-b from-white to-gray-50 dark:from-gray-900 dark:to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        {{-- Заголовок --}}
        <div class="text-center mb-12">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                О проекте
            </h1>
            <div class="mt-3 max-w-2xl mx-auto">
                <p class="text-xl text-gray-500 dark:text-gray-400">
                    Математика, страсть к футболу и немного магии машинного обучения
                </p>
            </div>
        </div>

        {{-- Основная история --}}
        <div class="prose prose-lg prose-indigo dark:prose-invert mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 mb-8">
                <p class="leading-relaxed">
                    Этот проект родился из простой идеи: что если объединить футбольную статистику, современные ML-модели и честный анализ, чтобы давать максимально объективные прогнозы? Без шума, без «инсайдов от экспертов» – только цифры, проверенные алгоритмами.
                </p>
                <p class="leading-relaxed mt-4">
                    На данный момент проект развивается <strong class="text-indigo-600 dark:text-indigo-400">одним человеком</strong> – инженером, который верит, что хороший прогноз может быть одновременно точным и понятным. Все серверы, базы данных и вычислительные мощности – арендованные, на свои средства.
                </p>
            </div>

            {{-- Честный блок про инфраструктуру --}}
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-950/30 dark:to-blue-950/30 rounded-2xl p-6 md:p-8 mb-8 border border-indigo-100 dark:border-indigo-800/30">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">⚙️ Как это работает (сейчас)</h2>
                <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                    <li>✅ Данные от API‑Football (Pro‑тариф, но без коэффициентов)</li>
                    <li>✅ Собственный ансамбль ML‑моделей (XGBoost, LightGBM, CatBoost + стэкер)</li>
                    <li>✅ Анализ формы, травм, ожидаемых голов (xG) и истории личных встреч</li>
                    <li>✅ Вся инфраструктура – на временных арендованных серверах</li>
                </ul>
            </div>

            {{-- Куда хотим прийти --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">🎯 Куда мы движемся</h2>
                <p class="leading-relaxed">
                    Чтобы прогнозы стали ещё точнее, а функционал сайта – шире, необходимы <strong>собственные вычислительные мощности (GPU‑серверы)</strong> и доступ к премиальным футбольным данным. Это позволит:
                </p>
                <ul class="list-disc pl-6 mt-3 space-y-1 text-gray-700 dark:text-gray-300">
                    <li>Обучать модели в реальном времени без оглядки на чужие лимиты</li>
                    <li>Запустить DeepSeek для анализа новостей и мотивации команд</li>
                    <li>Хранить полную историю матчей без потери качества</li>
                    <li>Добавить интерактивные графики, live‑прогнозы и расширенную аналитику</li>
                </ul>
                <p class="leading-relaxed mt-4">
                    Покупка собственного железа и подключение дополнительных API‑источников – следующий логический шаг. Но это требует бюджета, поэтому мы не скрываем, что будем рады любой поддержке.
                </p>
            </div>

            {{-- Призыв к поддержке (без навязчивости) --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 text-center">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">🤝 Как вы можете помочь</h2>
                <p class="text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    Если вам нравится проект и вы хотите видеть его развитие – мы будем искренне благодарны за любую форму поддержки. Это может быть:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 max-w-2xl mx-auto">
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4">
                        <div class="text-3xl mb-2">💸</div>
                        <h3 class="font-semibold">Финансовая помощь</h3>
                        <p class="text-sm text-gray-500">На аренду GPU, API и развитие инфраструктуры</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4">
                        <div class="text-3xl mb-2">🧠</div>
                        <h3 class="font-semibold">Экспертиза</h3>
                        <p class="text-sm text-gray-500">Советы по ML, дизайну или футбольной аналитике</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4">
                        <div class="text-3xl mb-2">📢</div>
                        <h3 class="font-semibold">Ретвит и обратная связь</h3>
                        <p class="text-sm text-gray-500">Расскажите о нас – это очень помогает!</p>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        💎 Если вы хотите поддержать проект финансово – напишите нам на <a href="mailto:support@nb-bet.com" class="text-indigo-600 hover:underline">support@nb-bet.com</a> (пока нет отдельной кнопки доната, но мы её скоро добавим).
                    </p>
                    <p class="text-xs text-gray-400 mt-3">
                        Спасибо, что дочитали до конца. Даже ваш визит на сайт – уже поддержка 🙌
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection