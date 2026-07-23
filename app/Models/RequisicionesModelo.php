<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisicionesModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras_requisicion';
    protected $hidden = [ 
        'id',
        'empresa',
        'usuario_requisita',
        'emp_token',
        'apePaterno',
        'apeMaterno',
        'nombre',
        'denominacion_rs',
        'nombre_com',
        'sitio_web',
        'redes_soc',
        'nacionalidad',
        'fecha_nac_const',
        'zona_horaria',
        'rfc',
        'curp',
        'clasificacion',
        'logotipo',
        'usuario_administrador',
        'personal',
        'pers_token',
        'paterno',
        'materno',
        'persNombre',
        'area',
        'cargo',
        'img_perfil',
        'usuario',
        'fecha_delete',
        'user_token',
        'codigo_acceso',
        'email',
        'password',
        'token_usuario',
        'tipo',
        'registro'
    ];
}
