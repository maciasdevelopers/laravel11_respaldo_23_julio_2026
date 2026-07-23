<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticaTransitoMain extends Model{
  protected $table = 'logistica_transito_main';
    
  protected $fillable = [
    'token_seguimiento_transito',
    'folio_seguimiento_transito',
    'logistica_fecha_contabilizacion',
    'estado_alcanzado',
    'fecha_real_salida',
    'observaciones_salida',
    'arribo_final_fecha_tentativa',
    'arribo_final_fecha_real',
    'arribo_final_observaciones',
    'arribo_final_autorizado',
    'arribo_final_fecha_auth',
    'empresa_vinculada',
    'usuario_registra'
  ];

  public function compras(): HasMany{
    return $this->hasMany(LogisticaTransitoCompra::class, 'transito_main');
  }

  public function unidades(): HasMany{
    return $this->hasMany(LogisticaTransitoUnidad::class, 'transito_main');
  }

  public function autorizar(): HasMany{
    return $this->hasMany(LogisticaTransitoAutorizacion::class, 'transito_main');
  }

  public function anexos(): HasMany{
    return $this->hasMany(LogisticaTransitoAnexos::class, 'transito_main');
  }
}