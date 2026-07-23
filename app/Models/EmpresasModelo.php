<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresasModelo extends Model
{
    use HasFactory;
    protected $table = 'main_empresas AS emp';
    protected $hidden = [
        'id'
    ];
}
