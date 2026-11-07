<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalVersion extends Model
{
    public const TYPES=['regular','extended','marketplace_terms','privacy','refund'];
    protected $fillable=['kind','version','title','body_html','status',
        'review_reference','reviewed_by','reviewed_at','effective_at'];

    protected function casts(): array
    {
        return ['reviewed_at'=>'datetime','effective_at'=>'datetime'];
    }

    public static function current(string $kind): ?self
    {
        return static::where('kind',$kind)->where('status','published')
            ->where('effective_at','<=',now())->latest('effective_at')->first();
    }
}
