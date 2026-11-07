<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentEntry extends Model
{
    protected $fillable = ['user_id', 'kind', 'title', 'slug', 'excerpt', 'body_html', 'status',
        'published_at', 'featured_media_id'];

    protected function casts(): array { return ['published_at' => 'datetime']; }

    public function author() { return $this->belongsTo(User::class, 'user_id'); }

    public function translations() { return $this->hasMany(ContentTranslation::class); }

    public function media() { return $this->belongsTo(MediaAsset::class, 'featured_media_id'); }
}
