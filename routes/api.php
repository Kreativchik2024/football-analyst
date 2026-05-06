<?php

use App\Models\News;
use Illuminate\Http\Request;

Route::get('/news/load-more', function (Request $request) {
    $page = $request->input('page', 1);
    $perPage = 6;

    $news = News::orderBy('published_at', 'desc')
        ->skip(6 + ($page - 1) * $perPage)
        ->take($perPage)
        ->get();

    $hasMore = $news->count() === $perPage;

    return response()->json([
        'html' => view('partials.news_cards', compact('news'))->render(),
        'has_more' => $hasMore,
    ]);
})->name('api.news.loadMore');