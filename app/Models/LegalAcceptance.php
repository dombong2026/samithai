<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalAcceptance extends Model
{
    public $timestamps=false;
    protected $fillable=['user_id','order_id','legal_version_id','ip_hash','accepted_at'];
    protected function casts(): array { return ['accepted_at'=>'datetime']; }
    public function version() { return $this->belongsTo(LegalVersion::class,'legal_version_id'); }
}
