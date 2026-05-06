@foreach($news as $newsItem)
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-3">
        @if($newsItem->image_url)
            <img src="{{ $newsItem->image_url }}" 
                 class="card-img-top" 
                 alt="{{ $newsItem->title }}"
                 style="height: 240px; object-fit: cover;">
        @endif
        <div class="card-body">
            <h5 class="card-title">
                <a href="{{ $newsItem->url }}" target="_blank" 
                   class="text-decoration-none text-dark stretched-link">
                    {{ Str::limit($newsItem->title, 60) }}
                </a>
            </h5>
            <p class="card-text text-muted small">
                {{ Str::limit($newsItem->content, 80) }}
            </p>
        </div>
        <div class="card-footer bg-white text-muted small border-0">
            {{ $newsItem->published_at->diffForHumans() }} | {{ $newsItem->source }}
        </div>
    </div>
@endforeach