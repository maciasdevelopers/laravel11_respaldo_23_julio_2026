<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatModelo extends Model
{
    use HasFactory;
    protected $table = 'chat_users';
    protected $hidden = [ 
        'id'
    ];
}
