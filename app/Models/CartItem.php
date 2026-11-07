<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'prompt_id'];

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }
}
