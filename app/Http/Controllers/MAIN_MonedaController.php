<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\MonedasModelo;
use Illuminate\Support\Facades\DB;

class MAIN_MonedaController extends Controller{
  public function catalogoMonedas(){
    $catMonedas = MonedasModelo::all();
    return $catMonedas;
  }

  public function monedaEmpresa(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $catMonedas = DB::table("main_empresas AS emp")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->select('emp.e_moneda_code','emp.e_moneda_decimales')
    ->first();

    if (!$catMonedas) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron monedas vinculadas a la empresa seleccionada'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $dataMensaje = array(
        'moneda' => $catMonedas->e_moneda_code,
        'decimales' => $catMonedas->e_moneda_decimales,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
