<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'client_name',
        'created_by',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uploadSessions()
    {
        return $this->hasMany(UploadSession::class);
    }
}
