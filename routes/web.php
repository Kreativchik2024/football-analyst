<?php

use Illuminate\Support\Facades\Route;
use App\Models\News;
use App\Models\Fixture;
use App\Models\ValueBet;
use App\Models\Video;
use App\Http\Middleware\CheckAiAccess;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\FixtureController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\AiPredictionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CapperCornerController;
 use App\Http\Controllers\ChatController;
 use App\Http\Controllers\MLController;
 use App\Http\Controllers\AboutController;


/*
|--------------------------------------------------------------------------
| Главная страница
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    $latestNews = News::orderBy('published_at', 'desc')->limit(3)->get();
    $pastFixtures = Fixture::with('homeTeam', 'awayTeam')
        ->where('starting_at', '<', now())
        ->whereIn('status', ['FT', 'AET', 'PEN'])
        ->orderBy('starting_at', 'desc')
        ->limit(3)
        ->get();
    $upcomingFixtures = Fixture::with('homeTeam', 'awayTeam')
        ->where('starting_at', '>=', now())
        ->whereIn('status', ['NS', 'TBD', 'LIVE', '1H', '2H', 'HT'])
        ->orderBy('starting_at')
        ->limit(3)
        ->get();

    $recentBets = ValueBet::with('fixture.homeTeam', 'fixture.awayTeam', 'odd')
        ->whereNotNull('profit')
        ->latest()
        ->limit(5)
        ->get();

    $totalBets = ValueBet::whereNotNull('profit')->count();
    $wonBets = ValueBet::where('profit', '>', 0)->count();
    $winRate = $totalBets > 0 ? round(($wonBets / $totalBets) * 100) : 0;

    $activeVideo = Video::where('is_active', true)->latest()->first();
    $embedCode = $activeVideo?->embed_code;

    return view('index', compact(
        'latestNews', 'pastFixtures', 'upcomingFixtures',
        'recentBets', 'winRate', 'embedCode'
    ));
})->name('home');

/*
|--------------------------------------------------------------------------
| Новости (с пагинацией)
|--------------------------------------------------------------------------
*/
Route::get('/news', function () {
    $latestNews = News::orderBy('published_at', 'desc')->paginate(6);
    return view('news', compact('latestNews'));
})->name('news');

/*
|--------------------------------------------------------------------------
| Предстоящие матчи
|--------------------------------------------------------------------------
*/
Route::get('/fixtures/upcoming', [FixtureController::class, 'upcoming'])->name('fixtures.upcoming');

/*
|--------------------------------------------------------------------------
| Прошедшие матчи
|--------------------------------------------------------------------------
*/
Route::get('/fixtures/past', [FixtureController::class, 'past'])->name('fixtures.past');

/*
|--------------------------------------------------------------------------
| AI Прогнозы (защищены авторизацией и ролью)
|--------------------------------------------------------------------------
*/


Route::get('/ai-predictions', [AiPredictionController::class, 'index'])
    ->middleware(['auth', CheckAiAccess::class])
    ->name('ai.predictions');

/*
|--------------------------------------------------------------------------
| Личный кабинет (Dashboard)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $user = auth()->user();
    $betsCount = 0;

    return view('dashboard', compact('user', 'betsCount'));
})->middleware('auth')->name('dashboard');

/*
|--------------------------------------------------------------------------
| Управление видео (только для администраторов)
|--------------------------------------------------------------------------
*/
Route::resource('videos', VideoController::class)->middleware('auth');

/*
|--------------------------------------------------------------------------
| Переключение языка
|--------------------------------------------------------------------------
*/
Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

/*
|--------------------------------------------------------------------------
| Тестовые маршруты (можно удалить после отладки)
|--------------------------------------------------------------------------
*/
Route::get('/test-login', function () {
    return 'Маршрут работает';
});

Route::get('/test-video', function () {
    $activeVideo = Video::where('is_active', true)->latest()->first();
    return response()->json([
        'embed_code' => $activeVideo?->embed_code,
    ]);
});

/*
|--------------------------------------------------------------------------
| Маршруты аутентификации (Breeze)
|--------------------------------------------------------------------------
*/


use App\Services\ApiFootballService;

Route::get('/fetch-bookmakers', function (ApiFootballService $api) {
    $data = $api->getBookmakers();         // теперь это массив
    return response()->json($data);
});

/*
|--------------------------------------------------------------------------
| Уголок капера (только для авторизованных)
|--------------------------------------------------------------------------
*/
Route::get('/capper-corner', [CapperCornerController::class, 'index'])
    ->middleware('auth')
    ->name('capper.corner');

Route::post('/capper-corner/bet', [CapperCornerController::class, 'placeBet'])
    ->middleware('auth')
    ->name('capper.bet');
   

Route::post('/chat/ask', [ChatController::class, 'ask'])->name('chat.ask');
Route::get('/chat/test', function () {
    return 'Chat route works';
});


Route::get('/contact', [ContactController::class, 'index'])->name('contact');



Route::get('/about', [AboutController::class, 'index'])->name('about');

// ML Training Data
Route::get('/api/ml/training-data', [App\Http\Controllers\MLController::class, 'getTrainingData']);



Route::get('/api/ml/status', [MLController::class, 'status']);
Route::post('/api/ml/store-prediction', [MLController::class, 'storePrediction']);
Route::get('/api/ml/training-data', [MLController::class, 'getTrainingData']);
Route::post('/api/ml/features', [MLController::class, 'getFeatures']);
Route::post('/api/ml/upcoming-features', [MLController::class, 'getUpcomingFeatures']);
require __DIR__.'/auth.php';