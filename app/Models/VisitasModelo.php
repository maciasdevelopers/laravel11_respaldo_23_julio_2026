<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitasModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_page_visitas as vis';
    protected $hidden = [
        'id'
    ];

}
