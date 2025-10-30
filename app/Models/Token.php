<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

    protected $table = 'tokens';
    protected $fillable = [
        'id_Usuario',
        'refresh_token',
        'refresh_expires_at',
        'access_token',
        'access_expires_at',
        'ip_address',
        'device',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_Usuario');
    }
}
