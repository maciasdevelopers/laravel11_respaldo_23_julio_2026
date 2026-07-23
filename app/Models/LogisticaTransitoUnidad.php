<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticaTransitoUnidad extends Model{
  protected $table = 'logistica_transito_unidades';

  public $timestamps = false; 

  protected $fillable = [
    'token_seguimiento_unidad',
    'folio_seguimiento_unidad',
    'tipo_trayecto',
    'transito_main',
    'tipo_transporte',
    'operador_nombre',
    'operador_telefono',
    'identificador_principal',
    'identificador_secundario',
    'permiso_autorizacion',
    'direccion_origen',
    'direccion_destino_especifica',
    'cfdi_relacionado',
    'cfdi_pdf_url',
    'estado_consumo',
    'unidad_fecha_salida',
    'unidad_fecha_tentativa_arribo',
    'unidad_fecha_real_arribo',
    'unidad_arribo_autorizado',
    'unidad_fecha_auth_arribo',
    'unidad_observaciones_arribo',
    'punto_transbordo_salida',
  ];

  public function articulos(): HasMany{
    return $this->hasMany(LogisticaTransitoArticulo::class, 'transito_unidad_id');
  }
}