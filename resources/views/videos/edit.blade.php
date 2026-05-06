@extends('layouts.app')

@section('title', 'Редактировать видео')

@section('content')
<div class="container">
    <h2>Редактировать видео</h2>
    <form method="POST" action="{{ route('videos.update', $video) }}">
        @csrf @method('PUT')
        <div class="mb-3">
            <label for="title" class="form-label">Название</label>
            <input type="text" name="title" class="form-control" value="{{ $video->title }}">
        </div>
        <div class="mb-3">
            <label for="embed_code" class="form-label">Embed-код</label>
            <textarea name="embed_code" class="form-control" rows="5" required>{{ $video->embed_code }}</textarea>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ $video->is_active ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Активно</label>
        </div>
        <button type="submit" class="btn btn-success">Сохранить</button>
        <a href="{{ route('videos.index') }}" class="btn btn-secondary">Назад</a>
    </form>
</div>
@endsection