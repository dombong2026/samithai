<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterLink extends Model
{
    protected $fillable = ['footer_column_id', 'label', 'url', 'position'];
    public function column() { return $this->belongsTo(FooterColumn::class, 'footer_column_id'); }
}
