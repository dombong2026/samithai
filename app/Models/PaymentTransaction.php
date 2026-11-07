<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $guarded = ['*'];
    protected function casts(): array { return ['verified_at'=>'datetime','amount'=>'decimal:2']; }
    public function order() { return $this->belongsTo(Order::class); }
}
