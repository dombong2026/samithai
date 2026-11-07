<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptEntitlement extends Model
{
    protected $fillable = ['user_id', 'prompt_id', 'order_id', 'license_type', 'content_snapshot'];

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }
}
