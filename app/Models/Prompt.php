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

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }
}
