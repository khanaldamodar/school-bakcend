<?php

namespace App\Models\Gov;

use App\Models\LocalBody;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Government extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

     protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'local_body_id',
    ];

      protected $hidden = [
        'password',
    ];

     public function localBody()
    {
        return $this->belongsTo(LocalBody::class, 'local_body_id');
    }

    
}
