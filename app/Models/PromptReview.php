<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptReview extends Model
{
    protected $fillable = ['prompt_id', 'actor_id', 'action', 'note'];

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
