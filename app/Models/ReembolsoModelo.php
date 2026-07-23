<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReembolsoModelo extends Model
{
    use HasFactory;
    protected $table = 'terc_reembolso_main';

    protected $hidden = [
        'id'
    ];
}
