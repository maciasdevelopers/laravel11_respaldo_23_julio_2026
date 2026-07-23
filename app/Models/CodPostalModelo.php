<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodPostalModelo extends Model
{
    use HasFactory;
    protected $table = "codpostal";
    //protected $table = "codigos_postales";
    protected $hidden = [
        'id'
    ];
}
