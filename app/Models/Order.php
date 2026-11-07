<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'number', 'status', 'currency', 'total'];

    public function user() { return $this->belongsTo(User::class); }

    public function transactions() { return $this->hasMany(PaymentTransaction::class); }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
