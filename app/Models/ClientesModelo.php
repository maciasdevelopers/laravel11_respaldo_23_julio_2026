<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesModelo extends Model
{
    use HasFactory;
    protected $table = 'ingr_catalogo_clientes';

    protected $hidden = [
        'id'
    ];
}
