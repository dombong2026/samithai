<article class="prompt-card">
@if(auth()->check())
<form method="post" action="{{ ($prompt->is_saved ?? false) ? route('prompts.unsave', $prompt) : route('prompts.save', $prompt) }}" class="prompt-save-form" aria-label="Saved prompts">
@csrf
@if($prompt->is_saved ?? false) @method('DELETE') @endif
<button class="save-toggle {{ ($prompt->is_saved ?? false) ? 'is-saved' : '' }}" type="submit" aria-label="{{ ($prompt->is_saved ?? false) ? 'Remove from saved prompts' : 'Save prompt for later' }}" title="{{ ($prompt->is_saved ?? false) ? 'Remove saved prompt' : 'Save for later' }}">{{ ($prompt->is_saved ?? false) ? '♥' : '♡' }}</button>
</form>
@else
<a class="save-toggle" href="{{ route('login') }}" aria-label="Sign in to save this prompt" title="Sign in to save">♡</a>
@endif
<a class="prompt-art" href="{{ route('prompts.show', $prompt) }}" aria-label="View {{ $prompt->title }}">@if($prompt->featuredMedia?->kind === 'image')
<img src="{{ route('media.serve', $prompt->featuredMedia) }}" alt="{{ $prompt->title }}" loading="lazy" style="width:100%;height:100%;object-fit:cover">
@elseif($prompt->featuredMedia?->kind === 'color')
<span style="width:100%;height:100%;display:grid;place-items:center;background:{{ $prompt->featuredMedia->color_hex }}">{{ strtoupper(substr($prompt->category?->name ?? 'AI',0,1)) }}</span>
@else<span>{{ strtoupper(substr($prompt->category?->name ?? 'AI', 0, 1)) }}</span>@endif</a>
<div class="prompt-body">
<span class="tag">{{ $prompt->category?->name ?? 'AI prompt' }}</span>
<a href="{{ route('prompts.show', $prompt) }}"><h3>{{ $prompt->title }}</h3></a>
<p class="tiny summary">{{ $prompt->summary }}</p>
<div class="prompt-bottom"><span class="price">{{ (float) $prompt->price === 0.0 ? 'Free' : '$'.number_format((float) $prompt->price, 2) }}</span>
<a class="btn small" href="{{ route('prompts.show', $prompt) }}">View</a></div>
</div></article>
