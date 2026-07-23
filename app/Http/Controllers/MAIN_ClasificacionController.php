<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\ClasificacionModelo;

class MAIN_ClasificacionController extends Controller{
  public function getClasificacionProductos(){
    $JwtAuth = new \JwtAuth();
    $categorias = array();
    $queryClass = ClasificacionModelo::where('codigo', '!=', '6')->get();

    foreach ($queryClass as $vClass) {
      $subcategorias = array();
      $querySubClass = DB::table("sos_ps_clasificacion AS class")
        ->join("sos_ps_genero AS gen", "class.id", "gen.clasificacion")
        ->where('class.token_clasificacion', $vClass->token_clasificacion)->get();

      foreach ($querySubClass as $vSub) {
        $rowSub = array(
          "token_genero" => $vSub->token_genero,
          "label" => $JwtAuth->generarFolio($vSub->folio_genero)." ".$vSub->concepto,
          "codigo" => $JwtAuth->generar($vSub->codigo),
        );
        $subcategorias[] = $rowSub;
      }

      $rowMain = array(
        "token_clasificacion" => $vClass->token_clasificacion,
        "label" => $vClass->concepto,
        "codigo" => $vClass->codigo,
        "children" => $subcategorias
      );
      $categorias[] = $rowMain;
    }

    return response()->json([
      'categorias' => $categorias,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function newClasificacionProductos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "string",
        "clasificacion" => "string",
        "subclasificacion" => "string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $categoria = $parametrosArray["clasificacion"];
        $sub_categoria = $parametrosArray["subclasificacion"];

        $token_auth = $JwtAuth->encriptarToken(time(),$categoria,$sub_categoria);

        $insertSoliPerm = DB::table("sos_ps_clasificacion")
        ->insert(
          array(
            "token_clasificacion" => $token_auth,
            "concepto" => $categoria,
            "codigo" => 7
          )
        );

      if ($insertSoliPerm) {
        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "message" => "Clasificaciòn registrada",
        );
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Clasificaciòn no registrada",
        );
      }

      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los datos no son correctos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }


  public function getClasificacionProductosComplete(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $categorias = array();
        $querySub = DB::table("sos_ps_genero")->where('clasificacion', '!=', '6')->get();

        foreach ($querySub as $vSub) {
          //"clasificacion": 1
          $clasificacion_token = "";
          $clasificacion_concepto = "";
          $clasificacion_codigo = "";
          $queryClass = DB::table("sos_ps_clasificacion")->where('id',$vSub->clasificacion)->get();
          foreach ($queryClass as $vClass) {
            $clasificacion_token = $vClass->token_clasificacion;
            $clasificacion_concepto = $vClass->concepto;
            $clasificacion_codigo = $vClass->codigo;
          }

          $rowSub = array(
            //sub_categorias
            "token_genero" => $vSub->token_genero,
            "concepto" => $vSub->concepto,
            "folio_genero" => $JwtAuth->generarFolio($vSub->folio_genero),
            //categorias
            "clasificacion_token" => $clasificacion_token,
            "clasificacion_concepto" => $clasificacion_concepto,
            "clasificacion_codigo" => $clasificacion_codigo,
          );
          $categorias[] = $rowSub;
        }

        $dataMensaje = array(
          "status" => 'success',
          "code" => 200,
          "categorias" => $categorias,
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los datos no son correctos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function getGeneroProductos(Request $request){
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "clasificacion" => "required|string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $clasificacion = $parametrosArray["clasificacion"];
        $listClass = DB::table("sos_ps_clasificacion AS class")
          ->join("sos_ps_genero AS gen", "class.id", "gen.clasificacion")
          ->where('class.token_clasificacion', '=', $clasificacion)->get();
        $dataMensaje = array(
          "status" => 'success',
          "code" => 200,
          "genero" => $listClass,
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los datos no son correctos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function getGeneroProductosValidado(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGenero = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'clasificacion' => 'required|string',
        'token_genero' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación del usuario invalida',
          'errors' => $validate->errors()
        );
      } else {
        $listClass = ClasificacionModelo::join("sos_ps_genero AS gen", "clasificacion.id", "gen.clasificacion")
          ->where('clasificacion.token_clascificacion', '=', $parametrosArray['clasificacion'])->get();

        foreach ($listClass as $classVal) {
          if ($classVal->token_genero == $parametrosArray['token_genero']) {
            $selected = true;
          } else {
            $selected = false;
          }

          $eachList = array(
            "token_clascificacion" => $classVal->token_clascificacion,
            "concepto" => $classVal->concepto,
            "codigo" => $classVal->codigo,
            "token_genero" => $classVal->token_genero,
            "folio_genero" => $classVal->folio_genero,
            "clasificacion" => $classVal->clasificacion,
            "selected" => $selected,
          );
          $arrayGenero[] = $eachList;
        }

        return response()->json([
          'genero' => $arrayGenero,
          'codigo' => 200,
          'status' => 'success'
        ]);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function setClasificacionFull(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "clasificacion" => "required|string",
        "genero" => "required|string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $clasificacion = $parametrosArray["clasificacion"];
        $genero = $parametrosArray["genero"];
        $clasificacionFolio = DB::table("sos_ps_clasificacion")->where("token_clasificacion", $clasificacion)->value("codigo");
        $generoFolio = DB::table("sos_ps_genero")->where("token_genero", $genero)->value("folio_genero");

        $folio = DB::select('SELECT COUNT(catprod.id) AS folio FROM in_egr_catalogo_productos AS catprod JOIN sos_ps_genero as gen JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                  JOIN teci_usuarios_catalogo AS users WHERE catprod.genero = gen.id AND gen.token_genero = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa
                  AND empuser.usuario = users.id AND users.usuario_token = ?', [$genero, $usuario->empresa_token, $usuario->user_token]);

        $dataMensaje = array(
          "status" => 'success',
          "code" => 200,
          "FullClass" => $JwtAuth->generar($clasificacionFolio) . "-" . $JwtAuth->generar($generoFolio) . "-" . $JwtAuth->generar($folio[0]->folio + 1),
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los datos no son correctos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function getClasificacionServicios(){
    $listClass = ClasificacionModelo::join("sos_ps_genero AS gen", "sos_ps_clasificacion.id", "gen.clasificacion")->where('sos_ps_clasificacion.codigo', '=', '6')->get();
    return response()->json([
      'listClass' => $listClass,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function fullClasifServicios(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    $genero = DB::select('SELECT folio_genero FROM genero WHERE token_genero = ?', [$parametros->genero]);
    //return $listClass; $parametros->clasificacion." ".$resServ[0]->codigo
    //[$parametros->clasificacion,$parametros->genero]

    $folio = DB::select('SELECT COUNT(catserv.id) AS folio
            FROM catalogo_servicios AS catserv 
            JOIN servicios AS listaserv
            JOIN genero as gen
            JOIN empresas AS emp
            JOIN empresapersonal AS emppers
            JOIN personal AS pers       
            JOIN usuarios AS users       
            WHERE catserv.servicio = listaserv.id  
            AND listaserv.genero = gen.id
            AND gen.token_genero = ?
            AND catserv.admin_empresa = emp.id
            AND emp.empresa_token = ?
            AND emp.id = emppers.empresa
            AND emppers.personal = pers.id 
            AND pers.usuario = users.id
            AND users.usuario_token = ?', [$parametros->genero, $usuario->empresa_token, $usuario->user_token]);

    return response()->json([
      'FullClass' => $JwtAuth->generar($parametros->clasificacion) . "-" .
        $JwtAuth->generar($genero[0]->folio_genero) . "-" .
        $JwtAuth->generar($folio[0]->folio + 1),
      'codigo' => 200,
      'status' => 'success'
    ]);
  }
}
