<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkspaceTask extends Model
{
    protected $fillable = ['title', 'description', 'status', 'priority', 'due_on'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'done' && $this->due_on !== null
            && $this->due_on->startOfDay()->lessThan(today());
    }
}
