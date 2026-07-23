<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FedEstadosMunicipiosModelo extends Model{
  use HasFactory;
  protected $table = 'fnzs_catalogos_fed_estados_municipios';
  protected $hidden = ['id'];
  protected $fillable = [
    'fed_est_mun_token',
    'fed_est_mun_folio',
    'fed_est_mun_subfolio',
    'fed_est_mun_fecha_contabilizacion',
    'fed_est_mun_entidad',
    'fed_est_mun_rfc',
    'fed_est_mun_observaciones',
    'fed_est_mun_empresa',
    'fed_est_mun_status'
  ];
}