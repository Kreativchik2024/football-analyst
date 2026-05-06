@forelse($latestNews as $news)
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            @if($news->image_url)
                <img src="{{ $news->image_url }}" class="card-img-top" alt="{{ $news->title }}" style="max-height: 200px; object-fit: cover;">
            @endif
            <div class="card-body">
                <h5 class="card-title">
                    <a href="{{ $news->url }}" target="_blank" style="text-decoration: none; color: inherit;">
                        {{ Str::limit($news->title, 80) }}
                    </a>
                </h5>
                <p class="card-text text-muted">{{ Str::limit($news->content, 120) }}</p>
            </div>
            <div class="card-footer text-muted">
                <small>
                    {{ $news->published_at->diffForHumans() }} | {{ $news->source }}
                </small>
            </div>
        </div>
    </div>
@empty
    <div class="alert alert-info">Новостей пока нет.</div>
@endforelse