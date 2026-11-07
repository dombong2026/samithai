<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    protected $fillable = ['content_entry_id','locale','title','excerpt','body_html',
        'status','reviewed_at','reviewed_by','published_at'];

    protected function casts(): array
    {
        return ['reviewed_at'=>'datetime','published_at'=>'datetime'];
    }

    public function entry() { return $this->belongsTo(ContentEntry::class,'content_entry_id'); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }
}
