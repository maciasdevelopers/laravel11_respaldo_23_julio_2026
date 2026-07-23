<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticaTransitoAutorizacion extends Model{
  protected $table = 'logistica_transito_autorizaciones';

  protected $fillable = [
    'transito_autorizacion_token',
    'transito_main',
    'transito_unidad_id',
    'tipo_autorizacion',
    'origen_autorizacion',
    'autorizador_nombre',
    'usuario_id',
    'observaciones',
  ];
}