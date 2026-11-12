<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentEntry extends Model
{
    protected $fillable = ['user_id', 'kind', 'title', 'slug', 'excerpt', 'body_html', 'status',
        'published_at', 'featured_media_id', 'editorial_status', 'reviewer_id',
        'editorial_approved_at', 'editorial_approved_hash', 'scheduled_for'];

    protected function casts(): array {
        return [
            'published_at' => 'datetime',
            'editorial_approved_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    public function author() { return $this->belongsTo(User::class, 'user_id'); }

    public function translations() { return $this->hasMany(ContentTranslation::class); }

    public function media() { return $this->belongsTo(MediaAsset::class, 'featured_media_id'); }

    public function seo() { return $this->hasOne(SeoMetadata::class); }

    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }

    public function editorialRevisions() { return $this->hasMany(ContentEditorialRevision::class); }
}
