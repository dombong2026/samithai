<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = ['user_id', 'title', 'kind', 'mime', 'file_path', 'bytes',
        'checksum', 'youtube_id', 'color_hex'];

    public function owner() { return $this->belongsTo(User::class, 'user_id'); }

    public function isImage(): bool { return $this->kind === 'image'; }

    public function isVideo(): bool { return $this->kind === 'video'; }
}
