@extends('layouts.app')

@section('title', 'Управление видео')

@section('content')
<div class="container">
    <h2>🎬 Управление видео</h2>
    <a href="{{ route('videos.create') }}" class="btn btn-primary mb-3">Добавить видео</a>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-dark">
        <thead>
            <tr>
                <th>Название</th>
                <th>Активно</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @foreach($videos as $video)
            <tr>
                <td>{{ $video->title ?? 'Без названия' }}</td>
                <td>{{ $video->is_active ? 'Да' : 'Нет' }}</td>
                <td>
                    <a href="{{ route('videos.edit', $video) }}" class="btn btn-sm btn-warning">Ред.</a>
                    <form action="{{ route('videos.destroy', $video) }}" method="POST" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Удалить видео?')">Уд.</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection