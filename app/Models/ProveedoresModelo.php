<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProveedoresModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_catalogo_proveedores';
    protected $hidden = [
        'id',	
        'administrador'
    ];
}
