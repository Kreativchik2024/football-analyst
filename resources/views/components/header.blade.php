{{-- resources/views/components/header.blade.php --}}
<header class="main-header bg-white shadow-sm">
    <div class="container-fluid">
        <div class="row align-items-center">
            {{-- Логотип --}}
            <div class="col-auto">
                <a href="{{ url('/') }}" class="logo">
                    <span class="logo-text">DeepOdds</span>
                </a>
            </div>

            {{-- Навигация --}}
            <div class="col">
                <nav class="main-nav d-flex justify-content-center">
                    <a href="{{ route('news') }}" class="nav-link {{ request()->routeIs('news') ? 'active' : '' }}">
                        {{ __('ui.news') }}
                    </a>
                    <a href="{{ route('fixtures.upcoming') }}" class="nav-link {{ request()->routeIs('fixtures.upcoming') ? 'active' : '' }}">
                        {{ __('ui.upcoming') }}
                    </a>
                    <a href="{{ route('fixtures.past') }}" class="nav-link {{ request()->routeIs('fixtures.past') ? 'active' : '' }}">
                        {{ __('ui.past') }}
                    </a>

                    @auth
                        @if(Auth::user()->canAccessAiPredictions())
                            <a href="{{ route('ai.predictions') }}" class="nav-link {{ request()->routeIs('ai.predictions') ? 'active' : '' }}">
                                {{ __('ui.ai_predictions') }}
                            </a>
                        @else
                            <span class="nav-link disabled" title="{{ __('ui.not_available') }}">
                                {{ __('ui.ai_predictions') }} 🔒
                            </span>
                        @endif
                    @else
                        <span class="nav-link disabled" title="{{ __('ui.login_required') }}">
                            {{ __('ui.ai_predictions') }} 🔒
                        </span>
                    @endauth
                </nav>
            </div>

            {{-- Переключатель языка --}}
            <div class="col-auto me-2">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu">
                        @foreach(['ru','en','es','de','fr','it','pt','ar','zh','ja','ko','tr','hi'] as $lang)
                            <li>
                                <a class="dropdown-item {{ app()->getLocale() == $lang ? 'active' : '' }}"
                                   href="{{ route('language.switch', $lang) }}">
                                    {{ strtoupper($lang) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Авторизация --}}
            <div class="col-auto">
                @auth
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            {{ Auth::user()->name }}
                            @if(Auth::user()->role !== 'user')
                                <span class="badge bg-info">{{ Auth::user()->role }}</span>
                            @endif
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('dashboard') }}">{{ __('ui.dashboard') }}</a></li>
                            @if(Auth::user()->isAdmin())
                                <li><a class="dropdown-item" href="{{ route('videos.index') }}">{{ __('ui.manage_videos') }}</a></li>
                            @endif
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">{{ __('ui.logout') }}</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <div class="auth-buttons">
                        <a href="{{ route('login') }}" class="btn btn-outline-primary me-2">{{ __('ui.login') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">{{ __('ui.register') }}</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</header>

<style>
    .main-header { border-bottom: 1px solid #eee; padding: 0.5rem 0; }
    .logo-text { font-size: 1.5rem; font-weight: bold; color: #2c3e50; text-decoration: none; }
    .main-nav .nav-link { color: #555; margin: 0 0.5rem; font-weight: 500; padding: 0.5rem 0.75rem; border-radius: 4px; transition: all 0.2s; }
    .main-nav .nav-link:hover, .main-nav .nav-link.active { color: #0d6efd; background: #f4f7fc; }
    .main-nav .nav-link.disabled { color: #aaa; pointer-events: none; }
    .badge { font-size: 0.7rem; vertical-align: middle; }
    .auth-buttons .btn { font-weight: 500; }
</style>