<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'prompt_id', 'title', 'unit_price', 'license_type', 'content_snapshot'];
    public function prompt() { return $this->belongsTo(Prompt::class); }
}
