<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distribution extends Model
{
    use HasFactory;

    protected $fillable = ['partner_id', 'volume_liter', 'destination', 'distributed_at', 'notes'];

    protected function casts(): array
    {
        return ['distributed_at' => 'date'];
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
