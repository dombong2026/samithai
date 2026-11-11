<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptQualityReview extends Model
{
    protected $fillable = [
        'prompt_id', 'reviewer_id', 'result', 'model_name', 'model_version',
        'test_scenario', 'findings', 'checks', 'content_sha256',
    ];

    protected function casts(): array
    {
        return ['checks' => 'array'];
    }

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
