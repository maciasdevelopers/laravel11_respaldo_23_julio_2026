<?php

namespace App\Services;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserConfigService{
  public function getModulos(){
    $listadoModulos = DB::table('sos_modulos_sistemas')
    ->orderBy("orden_listado","ASC")
    ->get()
    ->map(fn($mod) => [
      "modulo_token" => $mod->token_modulo,
      "modulo_nombre" => $mod->modulo,
      "modulo_mantenimiento" => (bool) $mod->mantenimiento,
      "modulo_acceso" => (bool) $mod->acceso,
    ]);

    if ($listadoModulos->isEmpty()) {
      return response()->json(['status' => 'error','message' => 'Acceso no permitido, módulos en construcción o en mantenimiento'], 400);
    }

    return $listadoModulos;
  }

  public function getUserSettings($usuario){
    $settingsUser = User::join("teci_user_settings AS sett", "teci_usuarios_catalogo.id", "=", "sett.usuario")
    ->where('teci_usuarios_catalogo.usuario_token',$usuario)
    ->select(
      'teci_usuarios_catalogo.jerarquia_main',
      'sett.lenguaje',
      'sett.privilegio_crear',
      'sett.privilegio_editar',
      'sett.privilegio_consulta',
      'sett.privilegio_elimina',
      'sett.privilegio_ver_docs'
    )
    ->first();
    
    if (!$settingsUser) {
      return response()->json(['status' => 'error','message' => 'Permisos no registrados'], 400);
    }
    
    return $settingsUser;
  }
}
