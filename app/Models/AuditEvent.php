<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'ip_hash', 'details', 'created_at'];
    protected function casts(): array { return ['details' => 'array', 'created_at' => 'datetime']; }
}
