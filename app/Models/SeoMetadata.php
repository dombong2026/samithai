<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    protected $fillable = [
        'content_entry_id','prompt_id','seo_title','meta_description',
        'canonical_path','og_title','og_description','og_media_id','robots_index','structured_data_enabled',
    ];

    protected function casts(): array
    {
        return ['robots_index' => 'boolean', 'structured_data_enabled' => 'boolean'];
    }

    public function contentEntry() { return $this->belongsTo(ContentEntry::class); }
    public function prompt() { return $this->belongsTo(Prompt::class); }
    public function ogMedia() { return $this->belongsTo(MediaAsset::class, 'og_media_id'); }
}
