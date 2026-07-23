<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromocionesModelo extends Model
{
    use HasFactory;
    protected $table = 'ingr_catalogo_promociones';
    protected $fillable = [
        'c_token',	
        'folio',	
        'alias',	
        'concepto',	
        'cou_porc',	
        'cantidad_base',	
        'aplicacion',	
        'fecha_inicio',	
        'fecha_fin',	
        'fecha_activacion',	
        'status_activacion',	
        'fecha_delete',	
        'status',	
        'empresa',
    ];

    protected $hidden = [
        'id',	
    ];
}
