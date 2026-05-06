@extends('layouts.app')

@section('title', 'Добавить видео')

@section('content')
<div class="container">
    <h2>Добавить видео</h2>
    <form method="POST" action="{{ route('videos.store') }}">
        @csrf
        <div class="mb-3">
            <label for="title" class="form-label">Название (необязательно)</label>
            <input type="text" name="title" class="form-control">
        </div>
        <div class="mb-3">
            <label for="embed_code" class="form-label">Embed-код</label>
            <textarea name="embed_code" class="form-control" rows="5" required></textarea>
            <div class="form-text">Вставьте iframe с YouTube, Rutube или другого видеохостинга.</div>
        </div>
        <button type="submit" class="btn btn-success">Сохранить</button>
        <a href="{{ route('videos.index') }}" class="btn btn-secondary">Назад</a>
    </form>
</div>
@endsection