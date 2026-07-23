<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormaPagoModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_forma_pago';
    protected $hidden = [
        'id'
    ];
}
