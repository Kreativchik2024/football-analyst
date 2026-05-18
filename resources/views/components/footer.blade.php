<footer class="bg-white border-top py-3 mt-auto">
    <div class="container text-center">
        <span class="text-muted small">
            © {{ date('Y') }} <strong>DeepOdds</strong> — AI-аналитика футбольных ставок.
        <br>
        <span class="text-muted small">
            Стартап
        </span>
        <div class="mt-2">
            <!-- <a href="https://github.com/Kreativchik2024" target="_blank" class="text-muted small me-2 text-decoration-none" title="GitHub">
                <i class="bi bi-github"></i> GitHub
            </a> -->
            <a href="{{ route('contact') }}" class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}">
    {{ __('Контакты') ?? 'Контакты' }}
</a>
            </a>
        </div>
    </div>
</footer>