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
use App\Models\InventariosSeriesModelo;
use QRCode;

class INVENT_SeriesController extends Controller{
  public function listaSeriesRegistro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'serie_code' => 'required|string',
			'uso_unico' => 'required|boolean',
			'comentarios' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea registrar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $serie_code = $request->input('serie_code');
      $uso_unico = $request->input('uso_unico');
      $comentarios = $request->input('comentarios');
      
      if (isset($serie_code) && !empty($serie_code) && preg_match($JwtAuth->filtroAlfaNumerico(),$serie_code)) {
        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,people.materno,people.nombre,
        people.denominacion_rs,people.sitio_web FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
        AND empuser.usuario = users.id AND users.usuario_token= ?",[$empresa,$usuario]);
        foreach ($selectEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          $fecha_registro = time();
          $newSerie = new InventariosSeriesModelo();
          $newSerie->serie_token = $JwtAuth->encriptarToken($fecha_registro,$serie_code);
          $newSerie->serie_fecha_registro = $fecha_registro;
          $newSerie->serie_codigo = $JwtAuth->encriptar($serie_code);
          $newSerie->uso_unico = $uso_unico == true ? TRUE : FALSE;
          $newSerie->comentarios = $comentarios != "" ? $JwtAuth->encriptar($comentarios) : NULL;
          $newSerie->empresa = $vEmp->id;
          $newSerie->status_serie = TRUE;
          $savednewServ = $newSerie->save();
          if ($savednewServ) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este serie ha sido registrado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Registro de serie incompleto, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      } else {
        $dataMensaje = array('status' => 'error','code' => 200,'message' => "Error al registrar número de serie, intentelo nuevamente o comuniquese a soporte");
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaSeriesRegistradas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $serieList = InventariosSeriesModelo::join("main_empresas AS emp","inventarios_catalogo_series.empresa","=","emp.id")
    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    ->where([
      "inventarios_catalogo_series.status_serie" => TRUE,
      "emp.empresa_token" => $empresa,"users.usuario_token" => $usuario
    ])
    ->get();

    if ($serieList->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron series registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arraySeries = array();
      
      foreach ($serieList as $vSer) {
        //da_te_default_timezone_set($vSer->zona_horaria);
        $row = array(
          "serie_token" => $vSer->serie_token,
          "serie_fecha_registro" => date('d-m-Y H:i:s',$vSer->serie_fecha_registro),
          "serie_codigo" => $JwtAuth->desencriptar($vSer->serie_codigo),	
          "uso_unico" => $vSer->uso_unico == TRUE ? true : false,	
          "comentarios" => $vSer->comentarios != NULL && $vSer->comentarios != "" ? $JwtAuth->desencriptar($vSer->comentarios) : null,	
          "detalle" => [],	
        );
        $arraySeries[] = $row; 
      }
      $dataMensaje = array('series' => $arraySeries,'code' => 200,'status' => 'success');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaSeriesSeguimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'serie_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $serie_token = $request->input('serie_token');
      
      $serieList = InventariosSeriesModelo::join("main_empresas AS emp","inventarios_catalogo_series.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        "inventarios_catalogo_series.serie_token" => $serie_token,
        "inventarios_catalogo_series.status_serie" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($serieList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron series registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $dataSerie = array();
        foreach ($serieList as $vSer) {
          //da_te_default_timezone_set($vSer->zona_horaria);
          $row = array(
            "serie_token" => $vSer->serie_token,
            "serie_fecha_registro" => date('d-m-Y H:i:s',$vSer->serie_fecha_registro),
            "serie_codigo" => $JwtAuth->desencriptar($vSer->serie_codigo),	
            "uso_unico" => $vSer->uso_unico == TRUE ? true : false,	
            "comentarios" => $vSer->comentarios != NULL && $vSer->comentarios != "" ? $JwtAuth->desencriptar($vSer->comentarios) : null,	
          );
          $dataSerie[] = $row; 
        }
        $dataMensaje = array('serie' => $dataSerie,'code' => 200,'status' => 'success');
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaSeriesMoveToPapelera(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'serie_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $serie_token = $request->input('serie_token');
      
      $vSer = InventariosSeriesModelo::join("main_empresas AS emp","inventarios_catalogo_series.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        "inventarios_catalogo_series.serie_token" => $serie_token,
        "inventarios_catalogo_series.status_serie" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select('inventarios_catalogo_series.serie_codigo','inventarios_catalogo_series.serie_token')
      ->first();
      
      if ($vSer->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron series registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $serie_codigo = $JwtAuth->desencriptar($vSer->serie_codigo);

        $serieDelete = InventariosSeriesModelo::where(["serie_token" => $vSer->serie_token])->limit(1)->update(array('status_serie' => FALSE,'fecha_delete_serie' => time()));
        if ($serieDelete) {
            $dataMensaje = array('status' => 'success','code' => 200,'message' => "La serie $serie_codigo ha sido eliminada");
        } else {
            $dataMensaje = array('status' => 'success','code' => 200,'message' => "La serie $serie_codigo no ha sido eliminada, intentelo nuevamente o comuniquese a soporte");
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaSeriesEliminadas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $serieList = InventariosSeriesModelo::join("main_empresas AS emp","inventarios_catalogo_series.empresa","=","emp.id")
    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    ->where([
      "inventarios_catalogo_series.status_serie" => FALSE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])->get();

    if ($serieList->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron series registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arraySeries = array();
      
      foreach ($serieList as $vSer) {
        //da_te_default_timezone_set($vSer->zona_horaria);
        $row = array(
          "serie_token" => $vSer->serie_token,
          "serie_fecha_registro" => date('d-m-Y H:i:s',$vSer->serie_fecha_registro),
          "serie_codigo" => $JwtAuth->desencriptar($vSer->serie_codigo),	
        );
        $arraySeries[] = $row; 
      }
      $dataMensaje = array('series' => $arraySeries,'code' => 200,'status' => 'success');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function listaSeriesRestaurar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'serie_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $serie_token = $request->input('serie_token');
      
      $vSer = InventariosSeriesModelo::join("main_empresas AS emp","inventarios_catalogo_series.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        "inventarios_catalogo_series.serie_token" => $serie_token,
        "inventarios_catalogo_series.status_serie" => FALSE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select('inventarios_catalogo_series.serie_codigo','inventarios_catalogo_series.serie_token')
      ->get();

      if ($vSer->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron series registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $serie_codigo = $JwtAuth->desencriptar($vSer->serie_codigo);

        $serieDelete = InventariosSeriesModelo::where(["serie_token" => $vSer->serie_token])->limit(1)->update(array('status_serie' => TRUE,'fecha_delete_serie' => NULL));
        if ($serieDelete) {
          $dataMensaje = array('status' => 'success','code' => 200,'message' => "La serie $serie_codigo ha sido restaurada");
        } else {
          $dataMensaje = array('status' => 'success','code' => 200,'message' => "La serie $serie_codigo no ha sido restaurada, intentelo nuevamente o comuniquese a soporte");
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaSeriesEliminar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'serie_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $serie_token = $request->input('serie_token');
      
      $vSer = InventariosSeriesModelo::join("main_empresas AS emp","inventarios_catalogo_series.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        "inventarios_catalogo_series.serie_token" => $serie_token,
        "inventarios_catalogo_series.status_serie" => FALSE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select('inventarios_catalogo_series.serie_codigo','inventarios_catalogo_series.serie_token')
      ->get();

      if ($vSer->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron series registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $serie_codigo = $JwtAuth->desencriptar($vSer->serie_codigo);

        $serieDelete = InventariosSeriesModelo::where(["serie_token" => $vSer->serie_token])->limit(1)->delete();
        if ($serieDelete) {
          $dataMensaje = array('status' => 'success','code' => 200,'message' => "La serie $serie_codigo ha sido eliminada permanentemente");
        } else {
          $dataMensaje = array('status' => 'success','code' => 200,'message' => "La serie $serie_codigo no ha sido eliminada, intentelo nuevamente o comuniquese a soporte");
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}