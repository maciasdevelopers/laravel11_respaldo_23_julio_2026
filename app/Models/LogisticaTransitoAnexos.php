<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticaTransitoAnexos extends Model{
  protected $table = 'logistica_transito_documentos';

  protected $fillable = [
    'token_documento',
    'fecha_carga',
    'modulo',
    'folio_modulo',
    'tipo_documento',
    'nombre_documento',
    'extension_documento',
    'transito_compra_id',
    'status_documento',
    'fecha_delete_documento',
  ];
}