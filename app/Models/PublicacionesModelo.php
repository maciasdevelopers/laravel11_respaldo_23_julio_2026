<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicacionesModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_page_publicaciones';
    protected $hidden = [
        'id'
    ];

}
