@extends('layouts.app')

@section('title', 'Контакты')

@section('content')
<div class="bg-gradient-to-b from-white to-gray-50 dark:from-gray-900 dark:to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        {{-- Заголовок --}}
        <div class="text-center mb-12">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                Контакты
            </h1>
            <p class="mt-3 text-xl text-gray-500 dark:text-gray-400">
                Свяжитесь с нами любым удобным способом
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            {{-- Карточка с контактной информацией --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-gray-700">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">📬 Контактные данные</h2>
                
                <div class="space-y-5">
                    {{-- Email --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-xl">
                            ✉️
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Email</div>
                            <a href="mailto:kreativchik69@gmail.com" class="text-lg font-medium text-gray-800 dark:text-white hover:text-indigo-600 transition">
                                kreativchik69@gmail.com
                            </a>
                        </div>
                    </div>

                    {{-- Телефон --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-xl">
                            📞
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Телефон (WhatsApp, Telegram)</div>
                            <a href="tel:+79806239616" class="text-lg font-medium text-gray-800 dark:text-white hover:text-indigo-600 transition">
                                8 (980) 623-96-16
                            </a>
                        </div>
                    </div>

                    {{-- Можно добавить соцсети, если нужно --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-xl">
                            💬
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Telegram / WhatsApp</div>
                            <div class="text-lg font-medium text-gray-800 dark:text-white">
                                +7 (980) 623-96-16
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- {{-- Карточка с формой обратной связи (опционально) --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 border border-gray-100 dark:border-gray-700">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">✍️ Написать нам</h2>
                <form action="#" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ваше имя</label>
                        <input type="text" name="name" id="name" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Иван Иванов">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" id="email" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500" placeholder="ivan@example.com">
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Сообщение</label>
                        <textarea name="message" id="message" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ваше сообщение..."></textarea>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                        Отправить
                    </button>
                </form>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-4 text-center">
                    Форма пока не отправляет сообщения (будет доработана), но вы можете написать нам на почту или в мессенджеры.
                </p>
            </div> -->
        </div>

        {{-- Дополнительный блок поддержки (как на странице "О проекте") --}}
        <div class="mt-12 text-center bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 border border-gray-100 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white">💡 Поддержать проект</h3>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Если вам нравится то, что мы делаем, вы можете поддержать нас финансово или просто рассказать о проекте друзьям. Любая помощь важна!
            </p>
            <div class="mt-4 flex justify-center gap-4">
                <a href="/about" class="text-indigo-600 hover:underline">👉 Подробнее о проекте</a>
            </div>
        </div>
    </div>
</div>
@endsection