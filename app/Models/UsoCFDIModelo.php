<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsoCFDIModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_uso_cfdi AS cfdi_uso';

    protected $hidden = [
        'id'
    ];

}
