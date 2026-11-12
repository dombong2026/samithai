<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentEditorialRevision extends Model
{
    public $timestamps = false;
    protected $fillable = ['content_entry_id','actor_id','action','content_sha256','snapshot','created_at'];

    protected function casts(): array { return ['created_at' => 'datetime']; }

    public function content() { return $this->belongsTo(ContentEntry::class, 'content_entry_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
