@extends('layouts.app')

@section('title', 'Новости футбола')

@section('content')
<div class="container-fluid py-3">
    <h2 class="mb-4">📰 Последние новости футбола</h2>

    @if($latestNews->isEmpty())
        <div class="alert alert-info">Новостей пока нет.</div>
    @else
        <div class="row g-3">
            @foreach($latestNews as $news)
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                        @if($news->image_url)
                            <img src="{{ $news->image_url }}" 
                                 class="card-img-top" 
                                 alt="{{ $news->title }}"
                                 style="height: 240px; object-fit: cover;">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="{{ $news->url }}" target="_blank" 
                                   class="text-decoration-none text-dark stretched-link">
                                    {{ Str::limit($news->title, 60) }}
                                </a>
                            </h5>
                            <p class="card-text text-muted small">
                                {{ Str::limit($news->content, 80) }}
                            </p>
                        </div>
                        <div class="card-footer bg-white text-muted small border-0">
                            {{ $news->published_at->diffForHumans() }} | {{ $news->source }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-center mt-3">
            {{ $latestNews->links() }}
        </div>
    @endif
</div>
@endsection