<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticaTransitoArticulo extends Model{
  protected $table = 'logistica_transito_articulos';

  protected $fillable = [
    'transito_unidad_id',
    'articulo_detcompra',
    'articulo_descripcion',
    'cantidad_asignada',
    'unidad_medida'
  ];
}