<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'summary',
        'content', 'price', 'status', 'license_type', 'published_at', 'featured_media_id',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'published_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function featuredMedia() { return $this->belongsTo(MediaAsset::class, 'featured_media_id'); }

    public function reviewEvents()
    {
        return $this->hasMany(PromptReview::class);
    }

    public function latestReview()
    {
        return $this->hasOne(PromptReview::class)->latestOfMany();
    }

    public function savers()
    {
        return $this->belongsToMany(User::class, 'prompt_saves')->withTimestamps();
    }

    public function entitlements()
    {
        return $this->hasMany(PromptEntitlement::class);
    }

    /**
     * Adds one viewer-specific save flag with a correlated subquery.
     * No lookup runs for unauthenticated visitors.
     */
    public function scopeWithViewerSave($query, ?int $viewerId)
    {
        if ($viewerId !== null) {
            $query->withExists([
                'savers as is_saved' => fn ($users) => $users->where('users.id', $viewerId),
            ]);
        }

        return $query;
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }
}
