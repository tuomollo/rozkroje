<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'material_type_id',
        'has_grain',
    ];
 
    public function type()
    {
        return $this->belongsTo(MaterialType::class, 'material_type_id');
    }
}
