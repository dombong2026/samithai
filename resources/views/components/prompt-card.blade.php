<article class="prompt-card">
<a class="prompt-art" href="{{ route('prompts.show', $prompt) }}" aria-label="View {{ $prompt->title }}"><span>{{ strtoupper(substr($prompt->category?->name ?? 'AI', 0, 1)) }}</span></a>
<div class="prompt-body">
<span class="tag">{{ $prompt->category?->name ?? 'AI prompt' }}</span>
<a href="{{ route('prompts.show', $prompt) }}"><h3>{{ $prompt->title }}</h3></a>
<p class="tiny summary">{{ $prompt->summary }}</p>
<div class="prompt-bottom"><span class="price">{{ (float) $prompt->price === 0.0 ? 'Free' : '$'.number_format((float) $prompt->price, 2) }}</span>
<a class="btn small" href="{{ route('prompts.show', $prompt) }}">View</a></div>
</div></article>
