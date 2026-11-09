<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspacePreference extends Model
{
    protected $fillable = ['user_id', 'context', 'shortcuts'];

    protected function casts(): array
    {
        return ['shortcuts' => 'array'];
    }
}
