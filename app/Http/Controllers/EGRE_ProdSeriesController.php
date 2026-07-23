<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\ProdSeriesModelo;
use QRCode;

class INVENTSeriesController extends Controller
{
  public function listaSeriesVigentes(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arraySeries = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $serieList = ProdSeriesModelo::join("main_empresas AS emp", "inventarios_catalogo_series.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["eegr_catalogo_productos_series.status_serie" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        foreach ($serieList as $vSer) {
          //da_te_default_timezone_set($vSer->zona_horaria);
          //echo $JwtAuth->encriptarToken($vSer->token_serie.$JwtAuth->generar($vSer->folio_serie).$vSer->numero_serie.$vSer->fecha_sistema);
          $row = array(
            "serie_token" => $vSer->token_serie,
            "serie_folio" => $JwtAuth->generar($vSer->folio_serie),
            "serie_numero" => $JwtAuth->desencriptar($vSer->numero_serie),
            "serie_fecha" => gmdate('Y-m-d H:i:s', $vSer->fecha_sistema),
          );
          $arraySeries[] = $row;
        }
        $dataMensaje = array('series' => $arraySeries, 'code' => 200, 'status' => 'success');
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
