<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterColumn extends Model
{
    protected $fillable = ['title', 'enabled', 'position'];
    protected function casts(): array { return ['enabled' => 'boolean']; }
    public function links() { return $this->hasMany(FooterLink::class)->orderBy('position')->orderBy('id'); }
}
