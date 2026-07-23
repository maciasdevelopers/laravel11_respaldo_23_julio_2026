<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\ClientesModelo;
use PDF;
use QRCode;

class INGR_ClientesController extends Controller{
  public function clientesCatGeneral(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }
      
      $listaClientes = DB::table("ingr_catalogo_clientes AS catklient")
      ->join("sos_personas AS client", "catklient.cliente", "client.id")
      ->join("main_empresas AS emp", "catklient.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catklient.status" => TRUE,
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario, 
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catklient.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($listaClientes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron clientes registrados'
        );
      } else {
        $arrayClientes = array();
        foreach ($listaClientes as $vRowClient) {
          //da_te_default_timezone_set($vRowClient->zona_horaria);
          //$fecha = date('d-m-Y H:i:s',$fechaDelete);
          $folio_client = $vRowClient->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vRowClient->folio) . ($vRowClient->post_folio != NULL ? '-' . $vRowClient->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vRowClient->temp_folio);
    
          if ($vRowClient->regimen_fiscal != NULL) {
            $clientRegFisc = DB::table("ingr_catalogo_clientes AS catklient")
              ->join("sos_regimen_fiscal AS regfis", "catklient.regimen_fiscal", "regfis.id")
              ->where([
                "catklient.token_cat_clientes" => $vRowClient->token_cat_clientes,
                "catklient.status" => true
              ])->get();
            $tkn_reg_fis = $clientRegFisc[0]->token_regimen_fiscal;
            $reg_fis = $clientRegFisc[0]->clave . "-" . $clientRegFisc[0]->descripcion;
          } else {
            $tkn_reg_fis = null;
            $reg_fis = null;
          }
    
          //if ($vRowClient->nombre_extendido == "" || $vRowClient->nombre_extendido == NULL) {
          //    if ($vRowClient->denominacion_rs == "") {
          //        $oldNombreCliente = $JwtAuth->desencriptarNombres($vRowClient->paterno,$vRowClient->materno,$vRowClient->nombre);
          //    } else {
          //        $oldNombreCliente = $JwtAuth->desencriptar($vRowClient->denominacion_rs);
          //    }
          //    
          //    $regFolder = DB::table("ingr_catalogo_clientes AS catklient")
          //    ->join("sos_personas AS client","catklient.cliente","client.id")
          //    ->where(['catklient.token_cat_clientes' => $vRowClient->token_cat_clientes])
          //    ->limit(1)->update(
          //        array(
          //            'client.nombre_extendido' => $JwtAuth->encriptar($oldNombreCliente),
          //            'client.paterno' => NULL,
          //            'client.materno' => NULL,
          //            'client.nombre' => NULL,	
          //            'client.denominacion_rs' => NULL,	
          //            'client.abrev_nombre' => NULL,	
          //        )
          //    );
          //}
    
          $nombre_cliente = $vRowClient->nombre_extendido == "" || $vRowClient->nombre_extendido == NULL ?
            ($vRowClient->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vRowClient->paterno, $vRowClient->materno, $vRowClient->nombre) : $JwtAuth->desencriptar($vRowClient->denominacion_rs)) :
            $JwtAuth->desencriptar($vRowClient->nombre_extendido);
          //echo $nombre_cliente;
          $arrayForeach = array(
            "token_cat_clientes" => $vRowClient->token_cat_clientes,
            "folio_client" => $folio_client,
            "authorized" => $vRowClient->authorized == TRUE ? true : false,
            "auth_fecha" => $vRowClient->authorized == TRUE ? gmdate('Y-m-d H:i:s', $vRowClient->authorized_fecha) : "---",
            "publico_general" => $vRowClient->publico_general == TRUE ? true : false,
            //"listaPrecios" => $vRowClient->listaPrecios,
            "tkn_reg_fis" => $tkn_reg_fis,
            "reg_fis" => $reg_fis,
            "nombre" => $nombre_cliente, //$JwtAuth->desencriptar($vRowClient->nombre_extendido),
            "nombre_comercial" => !is_null($vRowClient->nombre_com) ? $JwtAuth->desencriptar($vRowClient->nombre_com) : '',
            "rfc_generico" => $vRowClient->rfc_generico,
            "rfc" => $vRowClient->rfc != NULL ? $JwtAuth->desencriptar($vRowClient->rfc) : "---",
            "tax_id" => $vRowClient->tax_id != NULL ? $JwtAuth->desencriptar($vRowClient->tax_id) : "---",
            //"pais" => $vRowClient->pais,
          );
          $arrayClientes[] = $arrayForeach;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          "clientes" => $arrayClientes
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function clientesCatMx(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }
      
      $listaClientes = DB::table("ingr_catalogo_clientes AS catklient")
      ->join("sos_personas AS client", "catklient.cliente", "client.id")
      ->join("main_empresas AS emp", "catklient.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catklient.nacionalidad" => 118,
        "catklient.status" => TRUE,
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario, 
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catklient.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($listaClientes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron clientes registrados'
        );
      } else {
        $arrayClientes = array();
        foreach ($listaClientes as $vRowClient) {
          //da_te_default_timezone_set($vRowClient->zona_horaria);
          //$fecha = date('d-m-Y H:i:s',$fechaDelete);
          $folio_client = $vRowClient->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vRowClient->folio) . ($vRowClient->post_folio != NULL ? '-' . $vRowClient->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vRowClient->temp_folio);
    
          if ($vRowClient->regimen_fiscal != NULL) {
            $clientRegFisc = DB::table("ingr_catalogo_clientes AS catklient")
              ->join("sos_regimen_fiscal AS regfis", "catklient.regimen_fiscal", "regfis.id")
              ->where([
                "catklient.token_cat_clientes" => $vRowClient->token_cat_clientes,
                "catklient.status" => true
              ])->get();
            $tkn_reg_fis = $clientRegFisc[0]->token_regimen_fiscal;
            $reg_fis = $clientRegFisc[0]->clave . "-" . $clientRegFisc[0]->descripcion;
          } else {
            $tkn_reg_fis = null;
            $reg_fis = null;
          }
    
          //if ($vRowClient->nombre_extendido == "" || $vRowClient->nombre_extendido == NULL) {
          //    if ($vRowClient->denominacion_rs == "") {
          //        $oldNombreCliente = $JwtAuth->desencriptarNombres($vRowClient->paterno,$vRowClient->materno,$vRowClient->nombre);
          //    } else {
          //        $oldNombreCliente = $JwtAuth->desencriptar($vRowClient->denominacion_rs);
          //    }
          //    
          //    $regFolder = DB::table("ingr_catalogo_clientes AS catklient")
          //    ->join("sos_personas AS client","catklient.cliente","client.id")
          //    ->where(['catklient.token_cat_clientes' => $vRowClient->token_cat_clientes])
          //    ->limit(1)->update(
          //        array(
          //            'client.nombre_extendido' => $JwtAuth->encriptar($oldNombreCliente),
          //            'client.paterno' => NULL,
          //            'client.materno' => NULL,
          //            'client.nombre' => NULL,	
          //            'client.denominacion_rs' => NULL,	
          //            'client.abrev_nombre' => NULL,	
          //        )
          //    );
          //}
    
          $nombre_cliente = $vRowClient->nombre_extendido == "" || $vRowClient->nombre_extendido == NULL ?
            ($vRowClient->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vRowClient->paterno, $vRowClient->materno, $vRowClient->nombre) : $JwtAuth->desencriptar($vRowClient->denominacion_rs)) :
            $JwtAuth->desencriptar($vRowClient->nombre_extendido);
          //echo $nombre_cliente;
          $arrayForeach = array(
            "token_cat_clientes" => $vRowClient->token_cat_clientes,
            "folio_client" => $folio_client,
            "authorized" => $vRowClient->authorized == TRUE ? true : false,
            "auth_fecha" => $vRowClient->authorized == TRUE ? gmdate('Y-m-d H:i:s', $vRowClient->authorized_fecha) : "---",
            "publico_general" => $vRowClient->publico_general == TRUE ? true : false,
            //"listaPrecios" => $vRowClient->listaPrecios,
            "tkn_reg_fis" => $tkn_reg_fis,
            "reg_fis" => $reg_fis,
            "nombre" => $nombre_cliente, //$JwtAuth->desencriptar($vRowClient->nombre_extendido),
            "nombre_comercial" => !is_null($vRowClient->nombre_com) ? $JwtAuth->desencriptar($vRowClient->nombre_com) : '',
            "rfc_generico" => $vRowClient->rfc_generico,
            "rfc" => $vRowClient->rfc != NULL ? $JwtAuth->desencriptar($vRowClient->rfc) : "---",
            "tax_id" => $vRowClient->tax_id != NULL ? $JwtAuth->desencriptar($vRowClient->tax_id) : "---",
            //"pais" => $vRowClient->pais,
          );
    
          $arrayClientes[] = $arrayForeach;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          "clientes" => $arrayClientes
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function clientesCatExtranjeros(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }
      
      $listaClientes = DB::table("ingr_catalogo_clientes AS catklient")
      ->join("sos_personas AS client", "catklient.cliente", "client.id")
      ->join("main_empresas AS emp", "catklient.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->whereNot("catklient.nacionalidad", 118)
      ->where([
        "catklient.status" => TRUE,
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catklient.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($listaClientes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron clientes registrados'
        );
      } else {
        $arrayClientes = array();
        foreach ($listaClientes as $vRowClient) {
          //da_te_default_timezone_set($vRowClient->zona_horaria);
          //$fecha = date('d-m-Y H:i:s',$fechaDelete);
          $folio_client = $vRowClient->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vRowClient->folio) . ($vRowClient->post_folio != NULL ? '-' . $vRowClient->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vRowClient->temp_folio);
    
          if ($vRowClient->regimen_fiscal != NULL) {
            $clientRegFisc = DB::table("ingr_catalogo_clientes AS catklient")
              ->join("sos_regimen_fiscal AS regfis", "catklient.regimen_fiscal", "regfis.id")
              ->where([
                "catklient.token_cat_clientes" => $vRowClient->token_cat_clientes,
                "catklient.status" => true
              ])->get();
            $tkn_reg_fis = $clientRegFisc[0]->token_regimen_fiscal;
            $reg_fis = $clientRegFisc[0]->clave . "-" . $clientRegFisc[0]->descripcion;
          } else {
            $tkn_reg_fis = null;
            $reg_fis = null;
          }
    
          //if ($vRowClient->nombre_extendido == "" || $vRowClient->nombre_extendido == NULL) {
          //    if ($vRowClient->denominacion_rs == "") {
          //        $oldNombreCliente = $JwtAuth->desencriptarNombres($vRowClient->paterno,$vRowClient->materno,$vRowClient->nombre);
          //    } else {
          //        $oldNombreCliente = $JwtAuth->desencriptar($vRowClient->denominacion_rs);
          //    }
          //    
          //    $regFolder = DB::table("ingr_catalogo_clientes AS catklient")
          //    ->join("sos_personas AS client","catklient.cliente","client.id")
          //    ->where(['catklient.token_cat_clientes' => $vRowClient->token_cat_clientes])
          //    ->limit(1)->update(
          //        array(
          //            'client.nombre_extendido' => $JwtAuth->encriptar($oldNombreCliente),
          //            'client.paterno' => NULL,
          //            'client.materno' => NULL,
          //            'client.nombre' => NULL,	
          //            'client.denominacion_rs' => NULL,	
          //            'client.abrev_nombre' => NULL,	
          //        )
          //    );
          //}
    
          $nombre_cliente = $vRowClient->nombre_extendido == "" || $vRowClient->nombre_extendido == NULL ?
            ($vRowClient->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vRowClient->paterno, $vRowClient->materno, $vRowClient->nombre) : $JwtAuth->desencriptar($vRowClient->denominacion_rs)) :
            $JwtAuth->desencriptar($vRowClient->nombre_extendido);
          //echo $nombre_cliente;
          $arrayForeach = array(
            "token_cat_clientes" => $vRowClient->token_cat_clientes,
            "folio_client" => $folio_client,
            "authorized" => $vRowClient->authorized == TRUE ? true : false,
            "auth_fecha" => $vRowClient->authorized == TRUE ? gmdate('Y-m-d H:i:s', $vRowClient->authorized_fecha) : "---",
            "publico_general" => $vRowClient->publico_general == TRUE ? true : false,
            //"listaPrecios" => $vRowClient->listaPrecios,
            "tkn_reg_fis" => $tkn_reg_fis,
            "reg_fis" => $reg_fis,
            "nombre" => $nombre_cliente, //$JwtAuth->desencriptar($vRowClient->nombre_extendido),
            "nombre_comercial" => !is_null($vRowClient->nombre_com) ? $JwtAuth->desencriptar($vRowClient->nombre_com) : '',
            "rfc_generico" => $vRowClient->rfc_generico,
            "rfc" => $vRowClient->rfc != NULL ? $JwtAuth->desencriptar($vRowClient->rfc) : "---",
            "tax_id" => $vRowClient->tax_id != NULL ? $JwtAuth->desencriptar($vRowClient->tax_id) : "---",
            //"pais" => $vRowClient->pais,
          );
    
          $arrayClientes[] = $arrayForeach;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          "clientes" => $arrayClientes
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoClientesPublicoGeneral(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, ["user_token" => "required|string"]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.status" => true, "ingr_catalogo_clientes.publico_general" => true, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();

        foreach ($queryData as $vRow) {
          $folio_client = $vRow->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vRow->folio) . ($vRow->post_folio != NULL ? '-' . $vRow->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vRow->temp_folio);

          $row_cl = array(
            "token_cat_clientes" => $vRow->token_cat_clientes,
            "folio_client" => $folio_client,
            "authorized" => $vRow->authorized == TRUE ? true : false,
            //"listaPrecios" => $vRow->listaPrecios,
            //"tkn_reg_fis" => $tkn_reg_fis,
            //"reg_fis" => $reg_fis,
            "nombre" => $JwtAuth->desencriptar($vRow->nombre_extendido),
            "rfc_generico" => $vRow->rfc_generico,
          );
          $listaClientes[] = $row_cl;
        }

        $dataMensaje = array(
          "status" => 'success',
          "code" => 200,
          "clientes" => $listaClientes,
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

  public function catalogoClientesPublicoGeneralVentasMostrador(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, ["user_token" => "required|string"]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "ingr_catalogo_clientes.status" => true,
            "ingr_catalogo_clientes.publico_general" => true,
            "ingr_catalogo_clientes.destino_cliente" => "K29xUlN6N3loMUxibmd2dE8vT3lEMHF3QXlDKzQzY3JCMWY2VmZEQUNPTT06OjEyMzQ1Njc4MTIzNDU2Nzg=",
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token
          ])->get();

        foreach ($queryData as $vRow) {
          $folio_client = $vRow->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vRow->folio) . ($vRow->post_folio != NULL ? '-' . $vRow->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vRow->temp_folio);
          $row_cl = array(
            "token_cat_clientes" => $vRow->token_cat_clientes,
            "folio_client" => $folio_client,
            "authorized" => $vRow->authorized == TRUE ? true : false,
            //"listaPrecios" => $vRow->listaPrecios,
            //"tkn_reg_fis" => $tkn_reg_fis,
            //"reg_fis" => $reg_fis,
            "nombre" => $JwtAuth->desencriptar($vRow->nombre_extendido),
            "rfc_generico" => $vRow->rfc_generico,
          );
          $listaClientes[] = $row_cl;
        }

        $dataMensaje = array(
          "status" => 'success',
          "code" => 200,
          "clientes" => $listaClientes,
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

  public function requestValidacionCliente(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
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
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        //validaciones
        if (isset($token_cat_clientes) && !empty($token_cat_clientes)) {
          $observaciones = "permiso de prueba";

          $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();

          if (count($queryData) == 1) {
            foreach ($queryData as $vClient) {
              //da_te_default_timezone_set($vClient->zona_horaria);

              $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vClient->temp_folio);
              $nombre_client = strtolower($JwtAuth->desencriptar($vClient->nombre_extendido));

              $select_id_client = DB::select("SELECT id FROM ingr_catalogo_clientes WHERE token_cat_clientes = ?", [$vClient->token_cat_clientes]);

              $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                                WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

              $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                                AND users.usuario_token = ?", [$usuario->user_token]);

              $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
              $folioSistema = DB::select("SELECT max(soli_auth.folio_clientes_soli_auth) AS folio_permiso FROM ingr_catalogo_clientes_soli_auth AS soli_auth 
                                JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

              if (count($folioSistema) == 0) {
                $sql_folio = 1;
              } else {
                $sql_folio = end($folioSistema)->folio_permiso + 1;
              }

              $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $observaciones . time() - 500);

              $insertSoliPerm = DB::table("ingr_catalogo_clientes_soli_auth")
                ->insert(
                  array(
                    "token_clientes_soli_auth" => $token_auth,
                    "folio_clientes_soli_auth" => $sql_folio,
                    "fecha_clientes_soli_auth" => time(),
                    "user_emp" => end($select_empresa)->id,
                    "user_user" => end($select_usuario)->id,
                    "cliente" => end($select_id_client)->id,
                    "observaciones" => $JwtAuth->encriptar($observaciones),
                    "receptor" => 3,
                    "solicitud_client_status" => TRUE,
                  )
                );

              if ($insertSoliPerm) {
                $titulo_ = "Validación de cliente";
                $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado validación para el cliente con el folio " . $folio_client . " y nombre " . $nombre_client;

                $receptorMovil = DB::select("SELECT device.dispositivo_token FROM teci_usuarios_dispositivos AS device JOIN teci_usuarios_catalogo AS users
                                    WHERE device.dispositivo_tipo = 'movil' AND device.usuario = users.id AND users.usuario_token = ?", ["ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY"]);

                if (count($receptorMovil) > 0) {
                  foreach ($receptorMovil as $devMov) {
                    //echo $devMov->dispositivo_token;
                    $JwtAuth->notificacionPushDevices($devMov->dispositivo_token, $titulo_, $mensaje_user);
                  }
                }

                $receptorWeb = DB::table("teci_usuarios_dispositivos AS device")
                  ->join("teci_usuarios_catalogo AS users", "device.usuario", "=", "users.id")
                  ->where(["device.dispositivo_tipo" => "web", "users.usuario_token" => "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY"])->get();

                if (count($receptorWeb) > 0) {
                  foreach ($receptorWeb as $devWeb) {
                    //echo $devWeb->dispositivo_token;
                    $JwtAuth->notificacionPushDevices($devWeb->dispositivo_token, $titulo_, $mensaje_user);
                  }
                }

                $dataMensaje = array(
                  "status" => "success",
                  "code" => 200,
                  "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio),
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'el cliente buscado no existe'
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error al obtener información del cliente registrado, por favor verifique su información o comuniquese a soporte"
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

  public function validacionProcesoClientes(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
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
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        //validaciones
        if (isset($token_cat_clientes) && !empty($token_cat_clientes)) {
          $observaciones = "permiso de prueba";

          $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();

          if (count($queryData) == 1) {
            foreach ($queryData as $vClient) {
              //da_te_default_timezone_set($vClient->zona_horaria);
              $folio_client_temp = "CLI-TEMP-" . $JwtAuth->generarFolio($vClient->temp_folio);

              $nombre_client = strtolower($JwtAuth->desencriptar($vClient->nombre_extendido));

              $select_id_client = DB::select("SELECT id FROM ingr_catalogo_clientes WHERE token_cat_clientes = ?", [$vClient->token_cat_clientes]);

              $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                                WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

              $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                                AND users.usuario_token = ?", [$usuario->user_token]);

              $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);

              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                                FROM sos_last_folders AS fold JOIN main_empresas AS emp
                                WHERE fold.ing_clientes = TRUE AND fold.empresa = emp.id 
                                AND emp.empresa_token = ?", [$usuario->empresa_token]);

              if (count($folioSistema) == 1) {
                if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT post_folio FROM ingr_catalogo_clientes 
                                        WHERE id = (SELECT Max(catclient.id) FROM ingr_catalogo_clientes AS catclient 
                                        JOIN main_empresas AS emp WHERE catclient.administrador = emp.id 
                                        AND emp.empresa_token = ?)", [$usuario->empresa_token]);

                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
                  $folio_nuevo = 1;
                } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
                }
              } else {
                $post_folio = NULL;
                $folio_nuevo = 1;
              }

              if ($post_folio == NULL) {
                $folio_client = 'CLI-' . $JwtAuth->generarFolio($folio_nuevo);
              } else {
                $folio_client = 'CLI-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
              }

              $updateProvValid = DB::table("ingr_catalogo_clientes")
                ->where(["token_cat_clientes" => $vClient->token_cat_clientes])
                ->limit(1)->update(
                  array(
                    "folio" => $folio_nuevo,
                    "post_folio" => $post_folio,
                    "authorized" => TRUE,
                    "authorized_fecha" => time(),
                    "authorized_by" => end($select_usuario)->id,
                  )
                );

              if ($updateProvValid) {
                $soliValidate = DB::table("ingr_catalogo_clientes AS catclient")
                  ->join("ingr_catalogo_clientes_soli_auth AS soli_auth", "catclient.id", "=", "soli_auth.cliente")
                  ->join("teci_usuarios_catalogo AS users", "soli_auth.user_user", "=", "users.id")
                  ->join("teci_usuarios_dispositivos AS device", "users.id", "=", "device.usuario")
                  ->where(["soli_auth.soli_aprobada" => FALSE, "catclient.token_cat_clientes" => $vClient->token_cat_clientes])->get();

                if (count($soliValidate) > 0) {
                  $titulo_ = "Validación de cliente";
                  $mensaje_user = "El cliente con folio temporal " . $folio_client_temp . " y nombre " . $nombre_client . " ha sido validado con el folio " . $folio_client;
                  foreach ($soliValidate as $mSoli) {

                    $soliValidAprob = DB::table("ingr_catalogo_clientes_soli_auth")
                      ->where(["token_clientes_soli_auth" => $mSoli->token_clientes_soli_auth])
                      ->limit(1)->update(array("soli_aprobada" => TRUE));

                    if ($mSoli->dispositivo_tipo == "movil") {
                      $JwtAuth->notificacionPushDevices($mSoli->dispositivo_token, $titulo_, $mensaje_user);
                    }

                    if ($mSoli->dispositivo_tipo == "web") {
                      $JwtAuth->notificacionPushDevices($mSoli->dispositivo_token, $titulo_, $mensaje_user);
                    }
                  }
                }

                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table("sos_last_folders")
                    ->insert(array("ing_clientes" => TRUE, "folder" => 1, "post_folder" => $post_folio, "empresa" => $select_empresa[0]->id));
                } else {
                  $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                    ->where(["lastf.ing_clientes" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
                    ->limit(1)->update(array("lastf.folder" => $folio_nuevo, "lastf.post_folder" => $post_folio));
                }

                $dataMensaje = array(
                  "status" => "success",
                  "code" => 200,
                  "message" => "Cliente validado con el folio " . $folio_client,
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'el cliente buscado no existe'
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error al obtener información del cliente registrado, por favor verifique su información o comuniquese a soporte"
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

  public function ClientesGenDos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $arrayClientes = array();
    $arrayClientNacional = array();
    $arrayClientExtranjeto = array();
    $listaClientes = ClientesModelo::join("sos_personas AS client", "catklient.cliente", "client.id")
      ->join("teci_pais AS ps", "client.nacionalidad", "ps.id")
      ->join("forma_cobro_preferencial AS pago", "catklient.forma_cobro", "pago.id")
      ->join("main_empresas AS emp", "catklient.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
      ->where([
        "emp.empresa_token" => $usuario->empresa_token,
        "users.usuario_token" => $usuario->user_token,
        "catklient.status" => true
      ])->get();

    foreach ($listaClientes as $vRowClient) {
      //da_te_default_timezone_set($vRowClient->zona_horaria);
      //$fecha = date('d-m-Y H:i:s',$fechaDelete);
      if ($vRowClient->authorized == TRUE) {
        if ($vRowClient->post_folio == NULL) {
          $folio_client = "CLI-" . $JwtAuth->generarFolio($vRowClient->folio);
        } else {
          $folio_client = "CLI-" . $JwtAuth->generarFolio($vRowClient->folio) . '-' . $vRowClient->post_folio;
        }
      } else {
        $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vRowClient->temp_folio);
      }

      if ($vRowClient->authorized == TRUE) {
        $authorized = true;
      } else {
        $authorized = false;
      }

      if ($vRowClient->denominacion_rs != "") {
        $nombreCl = $JwtAuth->desencriptar($vRowClient->denominacion_rs);
      } else {
        $nombreCl = $JwtAuth->desencriptar($vRowClient->paterno) . " " .
          $JwtAuth->desencriptar($vRowClient->materno) . " " .
          $JwtAuth->desencriptar($vRowClient->nombre);
      }

      if ($vRowClient->nombre_com == "" || $vRowClient->nombre_com == "-") {
        $nombre_com = "---";
        //$nombre_com = "este cliente no cuenta con nombre comercial";
      } else {
        $nombre_com = $JwtAuth->desencriptar($vRowClient->nombre_com);
      }

      if ($vRowClient->sitio_web == "" || $vRowClient->sitio_web == "-") {
        $sitio_web = "---";
        //$sitio_web = "Este cliente no tiene un sitio web";
      } else {
        $sitio_web = $JwtAuth->desencriptar($vRowClient->sitio_web);
      }

      //echo $JwtAuth->desencriptar($vRowClient->redes_soc);
      if ($vRowClient->redes_soc != "" && $vRowClient->redes_soc != "-") {
        $arrayRedes = array();
        $separaRedes = json_decode($JwtAuth->desencriptar($vRowClient->redes_soc));
        for ($i = 0; $i < count($separaRedes); $i++) {
          if ((strpos($separaRedes[$i], "www.facebook.com")) !== false) {
            $arrayRedes[$i] = $separaRedes[$i];
          }

          if ((strpos($separaRedes[$i], "www.instagram.com")) !== false) {
            $arrayRedes[$i] = $separaRedes[$i];
          }

          if ((strpos($separaRedes[$i], "www.twitter.com")) !== false) {
            $arrayRedes[$i] = $separaRedes[$i];
          }

          if ((strpos($separaRedes[$i], "www.youtube.com")) !== false) {
            $arrayRedes[$i] = $separaRedes[$i];
          }
        }
      } else {
        $arrayRedes = array();
      }

      $rfc_generic_k = $vRowClient->rfc_generico;

      if ($vRowClient->rfc != NULL) {
        $rfc_klient = $JwtAuth->desencriptar($vRowClient->rfc);
      } else {
        $rfc_klient = "---";
      }

      if ($vRowClient->tax_id != NULL) {
        $tax_id_klient = $JwtAuth->desencriptar($vRowClient->tax_id);
      } else {
        $tax_id_klient = "---";
      }

      $arrayForeach = array(
        "token_client" => $vRowClient->token_cat_clientes,
        "folio_client" => $folio_client,
        "authorized" => $authorized,
        "listaPrecios" => $vRowClient->listaPrecios,
        "nombre" => $nombreCl,
        "nombre_com" => $nombre_com,
        "sitio_web" => $sitio_web,
        "redes_soc" => $arrayRedes,
        "rfc_generico" => $rfc_generic_k,
        "rfc" => $rfc_klient,
        "tax_id" => $tax_id_klient,
        "forma_cobro_preferencial" => $vRowClient->forma,
        "clabe_interbancaria" => $vRowClient->clabe_interbancaria,
        "estado_cuenta" => $vRowClient->estado_cuenta,
        "pais" => $vRowClient->pais,
      );

      $arrayClientes[] = $arrayForeach;

      if ($vRowClient->nacionalidad == 118) {
        $arrayClientNacional[] = $arrayForeach;
      } else {
        $arrayClientExtranjeto[] = $arrayForeach;
      }
    }

    return response()->json([
      "clientes" => $arrayClientes,
      "clientenac" => $arrayClientNacional,
      "clienteext" => $arrayClientExtranjeto,
      "code" => 200,
      "status" => "success"
    ]);
  }

  public function verCliente(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCliente = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_clientes" => "required|string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_clientes = $parametrosArray["token_clientes"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $personalContacto = array();
            $personalContactoDel = array();
            $listaUbicacion = array();

            $folio_cliente = $vCli->authorized == TRUE ? "CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '') : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);
            $nombre_cliente = $vCli->nombre_extendido == "" || $vCli->nombre_extendido == NULL ?
              ($vCli->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vCli->paterno, $vCli->materno, $vCli->nombre) : $JwtAuth->desencriptar($vCli->denominacion_rs)) :
              $JwtAuth->desencriptar($vCli->nombre_extendido);
            $nombre_cliente_header = $vCli->nombre_com != '' && $vCli->nombre_com != '-' ? $JwtAuth->desencriptar($vCli->nombre_com) : $nombre_cliente;

            $rfc_generico = $vCli->rfc_generico;
            $rfc_client = $vCli->rfc != NULL ? $JwtAuth->desencriptar($vCli->rfc) : '---';
            $tax_id_client = $vCli->tax_id != NULL ? $JwtAuth->desencriptar($vCli->tax_id) : '---';
            $nombre_com = $vCli->nombre_com != NULL && $vCli->nombre_com != "" ? $JwtAuth->desencriptar($vCli->nombre_com) : "---";
            $sitio_web = $vCli->sitio_web != NULL && $vCli->sitio_web != "" ? $JwtAuth->desencriptar($vCli->sitio_web) : "---";

            $regimen_fiscal_token = "";
            $regimen_fiscal_descripcion = "";
            $queryRegimenFiscal = ClientesModelo::join("sos_regimen_fiscal AS regf", "ingr_catalogo_clientes.regimen_fiscal", "=", "regf.id")
              ->where(["ingr_catalogo_clientes.token_cat_clientes" => $vCli->token_cat_clientes])->get();

            foreach ($queryRegimenFiscal as $vRegf) {
              $regimen_fiscal_token = $vRegf->token_regimen_fiscal;
              $regimen_fiscal_descripcion = $vRegf->clave . "-" . $vRegf->descripcion;
            }

            $queryContClient = DB::select(
              "SELECT empleado.token_contacto,empleado.area_contacto,empleado.cargo_contacto,people.paterno,people.materno,people.nombre
                            FROM in_egr_contacto_cliente_proveedor AS empleado JOIN sos_personas AS people JOIN ingr_catalogo_clientes AS catclient 
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE empleado.nombre = people.id AND empleado.status = TRUE AND empleado.cat_clientes = catclient.id
                            AND catclient.token_cat_clientes = ? AND catclient.administrador = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$parametrosArray["token_clientes"], $usuario->empresa_token, $usuario->user_token]
            );

            if (count($queryContClient) > 0) {
              foreach ($queryContClient as $vContCl) {
                $arrayTelefono = array();
                $queryPhone = DB::select("SELECT tel.token_telefono,tel.etiqueta,tel.telefono,tel.extension
                                FROM sos_personas_telefonos AS tel JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE tel.contacto_cliente_prov = empleado.id
                                AND empleado.token_contacto = ?", [$vContCl->token_contacto]);

                if (count($queryPhone) > 0) {
                  foreach ($queryPhone as $valueTelPers) {
                    $arrateleach = array(
                      "token_telefono" => $valueTelPers->token_telefono,
                      "etiqueta" => $valueTelPers->etiqueta,
                      "etiqueta_edit" => $valueTelPers->etiqueta,
                      "telefono" => $JwtAuth->desencriptar($valueTelPers->telefono),
                      "telefono_edit" => $JwtAuth->desencriptar($valueTelPers->telefono),
                      "extension" => $valueTelPers->extension != NULL && $valueTelPers->extension != "" ? $JwtAuth->desencriptar($valueTelPers->extension) : "---",
                      "extension_edit" => $valueTelPers->extension != NULL && $valueTelPers->extension != "" ? $JwtAuth->desencriptar($valueTelPers->extension) : "---",
                      "preferredCountries" => [],
                      "initialValue" => "",
                      "phoneForm" => ""
                    );
                    $arrayTelefono[] = $arrateleach;
                  }
                }

                $arrayCorreo = array();
                $queryMail = DB::select("SELECT mailpers.token_correo,mailpers.correo
                                FROM sos_personas_correos AS mailpers JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE mailpers.contacto_cliente_prov = empleado.id
                                AND empleado.token_contacto = ?", [$vContCl->token_contacto]);

                if (count($queryMail) > 0) {
                  foreach ($queryMail as $vMailPers) {
                    $arrateleach = array(
                      "token_correo" => $vMailPers->token_correo,
                      "correo" => $JwtAuth->desencriptar($vMailPers->correo),
                      "correo_edit" => $JwtAuth->desencriptar($vMailPers->correo)
                    );
                    $arrayCorreo[] = $arrateleach;
                  }
                }

                $persData = array(
                  "token_contacto" => $vContCl->token_contacto,
                  "paterno" => $JwtAuth->desencriptar($vContCl->paterno),
                  "paterno_edit" => $JwtAuth->desencriptar($vContCl->paterno),
                  "materno" => $JwtAuth->desencriptar($vContCl->materno),
                  "materno_edit" => $JwtAuth->desencriptar($vContCl->materno),
                  "nombre" => $JwtAuth->desencriptar($vContCl->nombre),
                  "nombre_edit" => $JwtAuth->desencriptar($vContCl->nombre),
                  "area_contacto" => $JwtAuth->desencriptar($vContCl->area_contacto),
                  "area_contacto_edit" => $JwtAuth->desencriptar($vContCl->area_contacto),
                  "cargo_contacto" => $JwtAuth->desencriptar($vContCl->cargo_contacto),
                  "cargo_contacto_edit" => $JwtAuth->desencriptar($vContCl->cargo_contacto),
                  "telefonos" => $arrayTelefono,
                  "filtro_telefonos" => null,
                  "correos" => $arrayCorreo,
                  "filtro_correos" => null,
                );
                $personalContacto[] = $persData;
              }
            }

            $queryContClientDel = DB::select(
              "SELECT empleado.token_contacto,empleado.area_contacto,empleado.cargo_contacto,people.paterno,people.materno,people.nombre,empleado.fecha_delete
                            FROM in_egr_contacto_cliente_proveedor AS empleado JOIN sos_personas AS people JOIN ingr_catalogo_clientes AS catclient 
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE empleado.nombre = people.id AND empleado.status = FALSE AND empleado.cat_clientes = catclient.id
                            AND catclient.token_cat_clientes = ? AND catclient.administrador = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$parametrosArray["token_clientes"], $usuario->empresa_token, $usuario->user_token]
            );

            if (count($queryContClientDel) > 0) {
              foreach ($queryContClientDel as $vContClDel) {
                $fecha_delete_pers = date("d-m-Y H:i:s", $vContClDel->fecha_delete);
                $clientDelete = array(
                  "token_contacto" => $vContClDel->token_contacto,
                  "folio" => $JwtAuth->generar($vContClDel->token_contacto),
                  "nombre_completo" => $JwtAuth->desencriptar($vContClDel->paterno) . " " . $JwtAuth->desencriptar($vContClDel->materno) . " " . $JwtAuth->desencriptar($vContClDel->nombre),
                  "areaemp" => $JwtAuth->desencriptar($vContClDel->areaemp),
                  "cargo" => $JwtAuth->desencriptar($vContClDel->cargo),
                  "fecha_delete" => $fecha_delete_pers
                );
                $personalContactoDel[] = $clientDelete;
              }
            }

            $queryAsignCreditos = ClientesModelo::join("in_egr_creditos AS cred", "ingr_catalogo_clientes.id", "=", "cred.cliente")
              ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_clientes])->get();

            $creditos_token = "";
            $creditos_acepta = false;
            $creditos_moneda_code = "";
            $creditos_moneda_decimales = "";
            $creditos_limite = "$" . number_format(0, 2, '.', ',');
            $creditos_dias = "";
            $creditos_fechalimite = "";
            $creditos_comienza = "";

            if (count($queryAsignCreditos) > 0) {
              foreach ($queryAsignCreditos as $vCred) {
                $creditos_token = $vCred->token_creditos;
                $creditos_acepta = $vCred->aceptacredito == TRUE ? true : false;
                $creditos_moneda_code = $vCred->aceptacredito == TRUE ? $vCred->moneda_code : '';
                $creditos_moneda_decimales = $vCred->aceptacredito == TRUE ? $vCred->moneda_decimales : '';
                $creditos_limite = $vCred->aceptacredito == TRUE ? $vCred->limite : '';
                $creditos_dias = $vCred->aceptacredito == TRUE ? $vCred->dias : '';
                $creditos_fechalimite = $vCred->aceptacredito == TRUE ? date("d-m-Y", time() + (86400 * $vCred->dias)) : '';
                $creditos_comienza = $vCred->aceptacredito == TRUE ? $vCred->comienza : '';
              }
            }

            $tieneformaCobro = false;
            $formaCobroToken = "";
            $formaCobroClave = "";
            $formaCobroConcepto = "";

            if (isset($vCli->forma_cobro)) {
              $tieneformaCobro = true;
              $queryFCbC = ClientesModelo::join("teci_forma_pago AS pfor", "ingr_catalogo_clientes.forma_cobro", "=", "pfor.id")
                ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_clientes])->get();
              foreach ($queryFCbC as $vFCbC) {
                $formaCobroToken = $vFCbC->token_formapago;
                $formaCobroClave = $vFCbC->clave;
                $formaCobroConcepto = $vFCbC->forma;
              }
            }

            $docs_adjuntos = array();
            $selectIdEvid = DB::table("sos_documentos AS docs")
              ->join("ingr_catalogo_clientes AS catclient", "docs.cliente", "=", "catclient.id")
              ->where([
                "status_documento" => TRUE,
                "catclient.token_cat_clientes" => $vCli->token_cat_clientes,
              ])->get();
            if (count($selectIdEvid) > 0) {
              foreach ($selectIdEvid as $vDoc) {
                $fcsf = "Constancia de situación fiscal";
                $cuof = "Constancia de cumplimiento de obligaciones fiscales";
                $fcnt = "Contratos";
                $anex = "anexos";
                $rowDocs = array(
                  "token_documento" => $vDoc->token_documento,
                  "tipo_documento" => $vDoc->tipo_documento == "fcsf" ? $fcsf : ($vDoc->tipo_documento == "cuof" ? $cuof : ($vDoc->tipo_documento == "fcnt" ? $fcnt : $anex)),
                  "ext_doc" => $vDoc->extension_documento,
                  "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                  "url" => "https://downloads.sos-mexico.com.mx/clientes/" . $folio_cliente . "/" . $vDoc->token_documento,
                );
                $docs_adjuntos[] = $rowDocs;
              }
            }

            //echo $vCli->nacionalidad;
            if ($vCli->nacionalidad == 118) {
              $queryUbicaTRUE = ClientesModelo::join("teci_direcciones AS dirfis", "dirfis.cliente", "ingr_catalogo_clientes.id")
                ->join("teci_pais AS detpais", "dirfis.pais", "detpais.id")
                ->where(["dirfis.status" => TRUE, "ingr_catalogo_clientes.token_cat_clientes" => $vCli->token_cat_clientes])->get();
              //echo count($direccionEntregas);
              if (count($queryUbicaTRUE) > 0) {
                foreach ($queryUbicaTRUE as $valUbi) {
                  $dirRow = array(
                    "token_direccion" => $valUbi->token_direccion,
                    "tipo_direccion" => $valUbi->tipo_direccion,
                    "clase" => $JwtAuth->desencriptar($valUbi->clase),
                    "pais" => $valUbi->pais,
                    "estado_main" => $JwtAuth->desencriptar($valUbi->estado_edit),
                    "estado_edit" => $JwtAuth->desencriptar($valUbi->estado_edit),
                    "municipio_main" => $JwtAuth->desencriptar($valUbi->municipio_edit),
                    "municipio_edit" => $JwtAuth->desencriptar($valUbi->municipio_edit),
                    "c_postal_main" => $valUbi->c_postal_edit,
                    "c_postal_edit" => $valUbi->c_postal_edit,
                    "colonia_main" => $JwtAuth->desencriptar($valUbi->colonia_edit),
                    "colonia_edit" => $JwtAuth->desencriptar($valUbi->colonia_edit),
                    "adicional" => $valUbi->adicional
                  );
                  $listaUbicacion[] = $dirRow;
                }
              }
            } else {
              $queryUbicaTRUE = ClientesModelo::join("teci_direcciones AS dirfis", "dirfis.cliente", "ingr_catalogo_clientes.id")
                ->join("teci_pais AS detpais", "dirfis.pais", "detpais.id")
                ->where(["dirfis.status" => TRUE, "ingr_catalogo_clientes.token_cat_clientes" => $vCli->token_cat_clientes])->get();

              if (count($queryUbicaTRUE) > 0) {
                foreach ($queryUbicaTRUE as $valUbi) {
                  $dirRow = array(
                    "token_direccion" => $valUbi->token_direccion,
                    "tipo_direccion" => $valUbi->tipo_direccion,
                    "clase" => $JwtAuth->desencriptar($valUbi->clase),
                    "cod_postalext" => $JwtAuth->desencriptar($valUbi->cod_postalext),
                    "cod_postalext_main" => $JwtAuth->desencriptar($valUbi->cod_postalext),
                    "pais" => $valUbi->pais
                  );
                  $listaUbicacion[] = $dirRow;
                }
              }
            }

            $pfmx = "Persona física (México)";
            $pfext = "Persona física Extranjero";
            $pmmx = "Persona moral (México)";
            $pmext = "Persona moral Extranjero";
            $clasificacion = $vCli->nacionalidad == 118 ? "nacional" : "extranjero";
            $subClasificacionAll = $vCli->nacionalidad == 118 ? ($vCli->subClase == "PM" ? $pmmx : $pfmx) : ($vCli->subClase == "PM" ? $pmext : $pfext);
            $cliente_identif = $vCli->rfc != NULL ? $JwtAuth->desencriptar($vCli->rfc) : ($vCli->tax_id != NULL ? $JwtAuth->desencriptar($vCli->tax_id) : $vCli->rfc_generico);

            $arrayForeachClient = array(
              "token_cliente" => $vCli->token_cat_clientes,
              "folio_cliente" => $folio_cliente,
              "rfc_generico" => $rfc_generico,
              "nombre_cliente_header" => $nombre_cliente_header,
              "nombre_cliente" => $nombre_cliente,
              "nombre_cliente_edit" => $nombre_cliente,
              "rfc_client" => $rfc_client,
              "rfc_client_edit" => $rfc_client,
              "tax_id_client" => $tax_id_client,
              "tax_id_client_edit" => $tax_id_client,
              "clasificacion" => $clasificacion,
              "subClasificacionSimple" => $vCli->subClase,
              "subClasificacionAll" => $subClasificacionAll,
              "identificador" => $cliente_identif,
              "nationality" => $vCli->nacionalidad,
              "nombre_comercial" => $nombre_com,
              "nombre_comercial_edit" => $nombre_com,
              "sitio_web" => $sitio_web,
              "sitio_web_edit" => $sitio_web,
              "regimen_fiscal_token" => $regimen_fiscal_token,
              "regimen_fiscal_token_edit" => $regimen_fiscal_token,
              "regimen_fiscal_descripcion" => $regimen_fiscal_descripcion,
              //"rfc" => $dataResRfc,
              "token_lista_precios" => $vCli->token_lista_precios,
              //"lista_precios" => $vCli->listaPrecios,"lista_precios" => $vCli->nombre_lista$JwtAuth->desencriptar($vCli->nombre_lista),
              "tiene_contacto_registrado" => count($personalContacto) > 0 ? true : false,
              "tiene_contacto_registrado_edit" => count($personalContacto) > 0 ? true : false,
              "contacto_registrado" => $personalContacto,
              "contacto_deleted" => $personalContactoDel,
              "tiene_docs_fiscales" => $vCli->tiene_docs_fiscales == TRUE ? true : false,
              "docs_adjuntos" => $docs_adjuntos,
              "tieneCreditoAsignado" => $creditos_token != "" ? true : false,
              "tieneCreditoAsignado_edit" => $creditos_token != "" ? true : false,
              //"creditos" => $arregloCreditos,

              "creditos_token_creditos" => $creditos_token,
              "creditos_acepta" => $creditos_acepta,
              "creditos_acepta_edit" => $creditos_acepta,
              "creditos_moneda_code" => $creditos_moneda_code,
              "creditos_moneda_code_edit" => $creditos_moneda_code,
              "creditos_moneda_decimales" => $creditos_moneda_decimales,
              "creditos_moneda_decimales_edit" => $creditos_moneda_decimales,
              "creditos_limite" => $creditos_limite,
              "creditos_limite_edit" => $creditos_limite,
              "creditos_dias" => $creditos_dias,
              "creditos_dias_edit" => $creditos_dias,
              "creditos_fechalimite" => $creditos_fechalimite,
              "creditos_comienza" => $creditos_comienza,
              "creditos_comienza_edit" => $creditos_comienza,


              "forma_cobro_pref_tiene" => $tieneformaCobro,
              "forma_cobro_pref_tiene_edit" => $tieneformaCobro,
              "forma_cobro_pref_token" => $formaCobroToken,
              "forma_cobro_pref_token_edit" => $formaCobroToken,
              "forma_cobro_pref_clave" => $formaCobroClave,
              "forma_cobro_pref_concepto" => $formaCobroConcepto,
              "emisionFacturas" => $vCli->emisionFacturas == TRUE ? true : false,
              "envioArtAfterCobro" => $vCli->envioArtAfterCobro == TRUE ? true : false,
              "ubicaciones" => $listaUbicacion,
            );

            $arrayCliente[] = $arrayForeachClient;

            $dataMensaje = array(
              "datosCliente" => $arrayCliente,
              "code" => 200,
              "status" => "success"
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }

    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteRegistraNuevoContacto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "paterno" => "string",
        "materno" => "string",
        "nombre" => "string",
        "area_contacto" => "string",
        "cargo_contacto" => "string",
        "emails_contacto" => "array",
        "telefonos_contacto" => "array",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $paterno = $parametrosArray["paterno"];
        $materno = $parametrosArray["materno"];
        $nombre = $parametrosArray["nombre"];
        $area_contacto = $parametrosArray["area_contacto"];
        $cargo_contacto = $parametrosArray["cargo_contacto"];
        $emails_contacto = $parametrosArray["emails_contacto"];
        $telefonos_contacto = $parametrosArray["telefonos_contacto"];

        $valida_paterno = isset($paterno) && !empty($paterno) && preg_match($JwtAuth->filtroAlfaNumerico(), $paterno);
        $valida_materno = isset($materno) && !empty($materno) && preg_match($JwtAuth->filtroAlfaNumerico(), $materno);
        $valida_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(), $nombre);
        $valida_area_contacto = isset($area_contacto) && !empty($area_contacto) && preg_match($JwtAuth->filtroAlfaNumerico(), $area_contacto);
        $valida_cargo_contacto = isset($cargo_contacto) && !empty($cargo_contacto) && preg_match($JwtAuth->filtroAlfaNumerico(), $cargo_contacto);

        if ($valida_paterno && $valida_materno && $valida_nombre && $valida_area_contacto && $valida_cargo_contacto) {
          $emails_invalidos = array_filter($emails_contacto, fn($email) => !preg_match($JwtAuth->filtroAlfaNumerico(), $email));
          if (count($emails_contacto) > 0 && count($emails_invalidos) > 0) {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Error en correo electrónico de personal de contacto");
          }
          $telefonos_invalidos = array_filter($telefonos_contacto, fn($phone) => !preg_match($JwtAuth->filtroAlfaNumerico(), $phone["etiqueta"]) || !preg_match($JwtAuth->filtroTelefonico(), $phone["telefono_complete"]));
          if (count($telefonos_contacto) > 0 && count($telefonos_invalidos) > 0) {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Error en teléfono de personal de contacto");
          }

          $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
          //echo count($queryMainCliente);
          if (count($queryMainCliente) == 1) {
            foreach ($queryMainCliente as $vCli) {
              //da_te_default_timezone_set($vCli->zona_horaria);
              $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);
              $selectEmp = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
              $selectClientCat = DB::table("ingr_catalogo_clientes")->where("token_cat_clientes", $vCli->token_cat_clientes)->value("id");

              $tokenPersonaCont = $JwtAuth->encriptarToken($area_contacto . '/' . $cargo_contacto . '/' . $paterno . '/' . $materno . '/' . $selectEmp . '/' . $area_contacto);
              $insertNombreCont = DB::table('sos_personas')->insert(
                array(
                  "token_personas" => $tokenPersonaCont,
                  "paterno" => $JwtAuth->encriptar($paterno),
                  "materno" => $JwtAuth->encriptar($materno),
                  "nombre" => $JwtAuth->encriptar($nombre)
                )
              );

              if ($insertNombreCont) {
                $selectNombreCont = DB::table("sos_personas")->where("token_personas", $tokenPersonaCont)->value("id");
                $tokenPersonal = $JwtAuth->encriptarToken($paterno . '/' . $selectNombreCont . '/' . $nombre . '/' . $materno . '/' . $selectEmp . '/' . $area_contacto);
                $insertaContacto = DB::table('in_egr_contacto_cliente_proveedor')->insert(
                  array(
                    "token_contacto" => $tokenPersonal,
                    "fecha_alta_contacto" => time(),
                    //"folio_contacto" => $i,
                    "area_contacto" => $JwtAuth->encriptar($area_contacto),
                    "cargo_contacto" => $JwtAuth->encriptar($cargo_contacto),
                    "nombre" => $selectNombreCont,
                    "cat_clientes" => $selectClientCat,
                    "status" => TRUE,
                    "fecha_delete" => NULL
                  )
                );

                if ($insertaContacto) {
                  $selectContacto = DB::table("in_egr_contacto_cliente_proveedor")->where("token_contacto", $tokenPersonal)->value("id");
                  if (count($telefonos_contacto) != 0) {
                    for ($t = 0; $t < count($telefonos_contacto); $t++) {
                      $tel_etiqueta = $telefonos_contacto[$t]["etiqueta"];
                      $tel_numero = $telefonos_contacto[$t]['telefono_complete'];
                      $contExtension = $telefonos_contacto[$t]['extension'] != '' ? $JwtAuth->encriptar($telefonos_contacto[$t]['extension']) : NULL;
                      $tokentel = $JwtAuth->encriptarToken($selectContacto . $tel_etiqueta . $tel_numero);
                      $principal = $t == 0 ? TRUE : FALSE;
                      //return response()->json(['message' => $telefonos_contacto[$t]["etiqueta"],'code' => 200,'status' => 'error']);
                      $insertaPhone = DB::table('sos_personas_telefonos')
                        ->insert(array(
                          "token_telefono" => $tokentel,
                          "contacto_cliente_prov" => $selectContacto,
                          //"icono" => $telefonos_contacto[$t]["icon"],	
                          "etiqueta" => $telefonos_contacto[$t]["etiqueta"],
                          "cod_pais" => 52,
                          "telefono" => $JwtAuth->encriptar($tel_numero),
                          "principal" => $principal,
                          "extension" => $contExtension,
                          "status_telefono" => TRUE,
                          "fecha_delete_tel" => NULL,
                        ));
                    }
                  }

                  if (count($emails_contacto) != 0) {
                    for ($m = 0; $m < count($emails_contacto); $m++) {
                      $tokenEmail = $JwtAuth->encriptarToken($emails_contacto[$m], $m, $selectContacto);
                      $contEmail = $JwtAuth->encriptar($emails_contacto[$m]);
                      $insertaMails = DB::table('sos_personas_correos')
                        ->insert(array(
                          "token_correo" => $tokenEmail,
                          "contacto_cliente_prov" => $selectContacto,
                          "correo" => $contEmail,
                          "status_correo" => TRUE,
                          "fecha_delete_correo" => NULL,
                        ));
                    }
                  }
                  $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información del nuevo personal de contacto del cliente con folio $folio_cliente ha sido registrada");
                } else {
                  $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información del nuevo personal de contacto del cliente con folio $folio_cliente no fue registrada debido a errores internos, intente nuevamente o comuniquese a soporte");
                }
              } else {
                $dataMensaje = array("status" => "error", "code" => 200, "message" => "Error al registrar nombre del contacto");
              }
            }
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "El cliente que busca no existe");
          }
        } else {
          $mensaje_error = "";
          if (!$valida_paterno) $mensaje_error = "Error en apellido paterno de personal de contacto";
          if (!$valida_materno) $mensaje_error = "Error en apellido materno de personal de contacto";
          if (!$valida_nombre) $mensaje_error = "Error en nombre de personal de contacto";
          if (!$valida_area_contacto) $mensaje_error = "Error en area de trabajo de personal de contacto";
          if (!$valida_cargo_contacto) $mensaje_error = "Error en cargo de trabajo de personal de contacto";
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }

    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoGenerales(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCliente = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "paterno" => "string",
        "materno" => "string",
        "nombre" => "string",
        "area_contacto" => "string",
        "cargo_contacto" => "string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $paterno = $parametrosArray["paterno"];
        $materno = $parametrosArray["materno"];
        $nombre = $parametrosArray["nombre"];
        $area_contacto = $parametrosArray["area_contacto"];
        $cargo_contacto = $parametrosArray["cargo_contacto"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $updateGeneralesContacto = DB::table("ingr_catalogo_clientes AS catclient")
              ->join("in_egr_contacto_cliente_proveedor AS cont", "catclient.id", "=", "cont.cat_clientes")
              ->join("sos_personas AS people", "cont.nombre", "=", "people.id")
              ->where([
                "catclient.token_cat_clientes" => $vCli->token_cat_clientes,
                "cont.token_contacto" => $token_contacto
              ])
              ->limit(1)->update(
                array(
                  'people.paterno' => $JwtAuth->encriptar($paterno),
                  'people.materno' => $JwtAuth->encriptar($materno),
                  'people.nombre' => $JwtAuth->encriptar($nombre),
                  'cont.area_contacto' => $JwtAuth->encriptar($area_contacto),
                  'cont.cargo_contacto' => $JwtAuth->encriptar($cargo_contacto),
                )
              );

            if ($updateGeneralesContacto) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }

    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoAddPhone(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCliente = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "etiqueta" => "required|string",
        "numero_telefono" => "required|string",
        "extension" => "string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $etiqueta = $parametrosArray["etiqueta"];
        $numero_telefono = $parametrosArray["numero_telefono"];
        $extension = $parametrosArray["extension"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);
            $identClient = DB::table("ingr_catalogo_clientes")->where("token_cat_clientes", $vCli->token_cat_clientes)->value("id");
            $identCont = DB::table("ingr_catalogo_clientes AS client")->join("in_egr_contacto_cliente_proveedor AS cont", "client.id", "=", "cont.cat_clientes")
              ->where("cont.token_contacto", $token_contacto)->where("client.token_cat_clientes", $vCli->token_cat_clientes)->value("cont.id");

            //return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
            $insertContPhones = DB::table('sos_personas_telefonos')
              ->insert(array(
                "token_telefono" => $JwtAuth->encriptarToken($identClient . $identCont . $JwtAuth->encriptar($numero_telefono)),
                "contacto_cliente_prov" => $identCont,
                //"icono" => $personalTelefonos[$t]["icon"],	
                "etiqueta" => $etiqueta,
                "cod_pais" => 52,
                "telefono" => $JwtAuth->encriptar($numero_telefono),
                "principal" => FALSE,
                "extension" => $extension != '' ? $JwtAuth->encriptar($extension) : NULL,
                "status_telefono" => TRUE,
                "fecha_delete_tel" => NULL,
              ));

            if ($insertContPhones) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }

    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoUpdatePhone(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "token_telefono" => "required|string",
        "etiqueta" => "required|string",
        "numero_telefono" => "required|string",
        "extension" => "string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $token_telefono = $parametrosArray["token_telefono"];
        $etiqueta = $parametrosArray["etiqueta"];
        $numero_telefono = $parametrosArray["numero_telefono"];
        $extension = $parametrosArray["extension"];
        //echo "desencriptar ".$JwtAuth->desencriptar($JwtAuth->encriptar($numero_telefono));exit;

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);
            //return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
            $updateContPhones = DB::table("sos_personas_telefonos AS tels")
              ->join("in_egr_contacto_cliente_proveedor AS cont", "tels.contacto_cliente_prov", "=", "cont.id")
              ->join("ingr_catalogo_clientes AS client", "cont.cat_clientes", "=", "client.id")
              ->where(["tels.token_telefono" => $token_telefono, "cont.token_contacto" => $token_contacto, "client.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(
                array(
                  "tels.etiqueta" => $etiqueta,
                  "tels.telefono" => $JwtAuth->encriptar($numero_telefono),
                  "tels.extension" => $extension != '' && $extension != '---' ? $JwtAuth->encriptar($extension) : NULL
                )
              );

            if ($updateContPhones) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de teléfonos del personal de contacto del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de teléfonos del personal de contacto del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }

    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoDeletePhone(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCliente = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "token_telefono" => "required|string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $token_telefono = $parametrosArray["token_telefono"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);
            $identClient = DB::table("ingr_catalogo_clientes")->where("token_cat_clientes", $vCli->token_cat_clientes)->value("id");
            //return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
            $deleteContPhones = DB::table("sos_personas_telefonos AS tels")
              ->join("in_egr_contacto_cliente_proveedor AS cont", "tels.contacto_cliente_prov", "=", "cont.id")
              ->join("ingr_catalogo_clientes AS client", "cont.cat_clientes", "=", "client.id")
              ->where("tels.token_telefono", $token_telefono)
              ->where("cont.token_contacto", $token_contacto)
              ->where("client.token_cat_clientes", $vCli->token_cat_clientes)->delete();

            if ($deleteContPhones) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de teléfonos del personal de contacto del cliente con folio $folio_cliente ha sido eliminada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de teléfonos del personal de contacto del cliente con folio $folio_cliente no fue eliminada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }

    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoAddEmail(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCliente = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "correo" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $correo = $parametrosArray["correo"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);
            $identClient = DB::table("ingr_catalogo_clientes")->where("token_cat_clientes", $vCli->token_cat_clientes)->value("id");
            $identCont = DB::table("ingr_catalogo_clientes AS client")->join("in_egr_contacto_cliente_proveedor AS cont", "client.id", "=", "cont.cat_clientes")
              ->where("cont.token_contacto", $token_contacto)->where("client.token_cat_clientes", $vCli->token_cat_clientes)->value("cont.id");

            $tokenEmail = $JwtAuth->encriptarToken($identClient, $correo, $identCont);
            $insertContEmail = DB::table('sos_personas_correos')
              ->insert(array(
                "token_correo" => $tokenEmail,
                "contacto_cliente_prov" => $identCont,
                "correo" => $JwtAuth->encriptar($correo),
                "status_correo" => TRUE,
                "fecha_delete_correo" => NULL,
              ));

            if ($insertContEmail) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoUpdateEmail(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCliente = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "token_correo" => "required|string",
        "correo" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $token_correo = $parametrosArray["token_correo"];
        $correo = $parametrosArray["correo"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $updateContEmail = DB::table("sos_personas_correos AS mails")
              ->join("in_egr_contacto_cliente_proveedor AS cont", "mails.contacto_cliente_prov", "=", "cont.id")
              ->join("ingr_catalogo_clientes AS client", "cont.cat_clientes", "=", "client.id")
              ->where(["mails.token_correo" => $token_correo, "cont.token_contacto" => $token_contacto, "client.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(array("mails.correo" => $JwtAuth->encriptar($correo)));

            if ($updateContEmail) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información del personal de contacto del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateContactoDeleteEmail(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_contacto" => "required|string",
        "token_correo" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_contacto = $parametrosArray["token_contacto"];
        $token_correo = $parametrosArray["token_correo"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $deleteContEmail = DB::table("sos_personas_correos AS mails")
              ->join("in_egr_contacto_cliente_proveedor AS cont", "mails.contacto_cliente_prov", "=", "cont.id")
              ->join("ingr_catalogo_clientes AS client", "cont.cat_clientes", "=", "client.id")
              ->where(["mails.token_correo" => $token_correo, "cont.token_contacto" => $token_contacto, "client.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->delete();

            if ($deleteContEmail) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de correos del personal de contacto del cliente con folio $folio_cliente ha sido eliminada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de correos del personal de contacto del cliente con folio $folio_cliente no fue eliminada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateCreditosUpdate(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_creditos" => "required|string",
        "data_moneda_code" => "required|string",
        "data_moneda_decimales" => "required|string",
        "txtlimiteCredito" => "required|string",
        "txtdiasCobroCredit" => "required|string",
        "selectComienzaCobroClient" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_creditos = $parametrosArray["token_creditos"];
        $data_moneda_code = $parametrosArray["data_moneda_code"];
        $data_moneda_decimales = $parametrosArray["data_moneda_decimales"];
        $txtlimiteCredito = $parametrosArray["txtlimiteCredito"];
        $txtdiasCobroCredit = $parametrosArray["txtdiasCobroCredit"];
        $selectComienzaCobroClient = $parametrosArray["selectComienzaCobroClient"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $updateCredClient = DB::table("in_egr_creditos AS cred")
              ->join("ingr_catalogo_clientes AS client", "cred.cliente", "=", "client.id")
              ->where(["cred.token_creditos" => $token_creditos, "client.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(
                array(
                  "cred.moneda_code" => $data_moneda_code,
                  "cred.moneda_decimales" => $data_moneda_decimales,
                  "cred.limite" => $txtlimiteCredito,
                  "cred.dias" => $txtdiasCobroCredit,
                  "cred.comienza" => $selectComienzaCobroClient,
                )
              );

            if ($updateCredClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de créditos del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de créditos del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateCreditosDelete(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "token_creditos" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $token_creditos = $parametrosArray["token_creditos"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $updateCredClient = DB::table("in_egr_creditos AS cred")
              ->join("ingr_catalogo_clientes AS client", "cred.cliente", "=", "client.id")
              ->where(["cred.token_creditos" => $token_creditos, "client.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->delete();

            if ($updateCredClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de créditos del cliente con folio $folio_cliente ha sido eliminada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de créditos del cliente con folio $folio_cliente no fue eliminada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateFormaCobroUpdate(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cliente" => "required|string",
        "tiene_forma_cobro" => "required|boolean",
        "formaCobroAltaClient" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = $parametrosArray["token_cliente"];
        $tiene_forma_cobro = $parametrosArray["tiene_forma_cobro"];
        $formaCobroAltaClient = $parametrosArray["formaCobroAltaClient"];

        $queryMainCliente = ClientesModelo::join("sos_personas AS perns", "ingr_catalogo_clientes.cliente", "=", "perns.id")
          ->join("in_egr_contacto_cliente_proveedor AS cont", "ingr_catalogo_clientes.id", "=", "cont.cat_clientes")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cliente, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $selectFCobro = $tiene_forma_cobro == true ? DB::table("teci_forma_pago")->where("token_formapago", $formaCobroAltaClient)->value("id") : NULL;
            $updateFCobroClient = DB::table("ingr_catalogo_clientes")
              ->where(["token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(array("forma_cobro" => $selectFCobro));

            if ($updateFCobroClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de forma de cobro del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de forma de cobro del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateHabilitaEmitirFactAntesCobro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $emitFactClient = DB::table("ingr_catalogo_clientes")
              ->where(["token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(array("emisionFacturas" => TRUE));

            if ($emitFactClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de emisión de facturas del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de emisión de facturas del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateCancelaEmitirFactAntesCobro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $cancelEmitFactClient = DB::table("ingr_catalogo_clientes")
              ->where(["token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(array("emisionFacturas" => FALSE));

            if ($cancelEmitFactClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de emisión de facturas del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de emisión de facturas del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateEntregaProdAntesCobro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $envioAfterCobClient = DB::table("ingr_catalogo_clientes")
              ->where(["token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(array("envioArtAfterCobro" => TRUE));

            if ($envioAfterCobClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de entregas antes de cobro del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de entregas antes de cobro del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateCancelaEntregaProdAntesCobro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $cancelEnvioAfterCobClient = DB::table("ingr_catalogo_clientes")
              ->where(["token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(array("envioArtAfterCobro" => FALSE));

            if ($cancelEnvioAfterCobClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de entregas antes de cobro del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de entregas antes de cobro del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateUpdateUbicacionDipoMex(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
        "token_direccion" => "required|string",
        "estado" => "required|string",
        "municipio" => "required|string",
        "codigo_postal" => "required|string",
        "colonia" => "required|string",
        "api" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        $token_direccion = $parametrosArray["token_direccion"];
        $estado = $parametrosArray["estado"];
        $municipio = $parametrosArray["municipio"];
        $codigo_postal = $parametrosArray["codigo_postal"];
        $colonia = $parametrosArray["colonia"];
        $api = $parametrosArray["api"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $cancelEnvioAfterCobClient = DB::table("teci_direcciones AS dir")
              ->join("ingr_catalogo_clientes AS cli", "dir.cliente", "=", "cli.id")
              ->where(["dir.token_direccion" => $token_direccion, "cli.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(
                array(
                  "dir.estado_edit" => $JwtAuth->encriptar($estado),
                  "dir.municipio_edit" => $JwtAuth->encriptar($municipio),
                  "dir.c_postal_edit" => $codigo_postal,
                  "dir.colonia_edit" => $JwtAuth->encriptar($colonia),
                  "dir.adicional" => $api,
                )
              );

            if ($cancelEnvioAfterCobClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de ubicación del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de ubicación del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteUpdateUpdateUbicacionNoApi(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_clientes" => "required|string",
        "token_direccion" => "required|string",
        "estado" => "required|string",
        "municipio" => "required|string",
        "codigo_postal" => "required|string",
        "colonia" => "required|string",
        "api" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        $token_direccion = $parametrosArray["token_direccion"];
        $estado = $parametrosArray["estado"];
        $municipio = $parametrosArray["municipio"];
        $codigo_postal = $parametrosArray["codigo_postal"];
        $colonia = $parametrosArray["colonia"];
        $api = $parametrosArray["api"];

        $queryMainCliente = ClientesModelo::join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        //echo count($queryMainCliente);
        if (count($queryMainCliente) == 1) {
          foreach ($queryMainCliente as $vCli) {
            //da_te_default_timezone_set($vCli->zona_horaria);
            $folio_cliente = $vCli->authorized == TRUE ? ("CLI-" . $JwtAuth->generarFolio($vCli->folio) . ($vCli->post_folio != NULL ? '-' . $vCli->post_folio : '')) : "CLI-TEMP-" . $JwtAuth->generarFolio($vCli->temp_folio);

            $cancelEnvioAfterCobClient = DB::table("teci_direcciones AS dir")
              ->join("ingr_catalogo_clientes AS cli", "dir.cliente", "=", "cli.id")
              ->where(["dir.token_direccion" => $token_direccion, "cli.token_cat_clientes" => $vCli->token_cat_clientes])
              ->limit(1)->update(
                array(
                  "dir.estado_edit" => $JwtAuth->encriptar($estado),
                  "dir.municipio_edit" => $JwtAuth->encriptar($municipio),
                  "dir.c_postal_edit" => $codigo_postal,
                  "dir.colonia_edit" => $JwtAuth->encriptar($colonia),
                  "dir.adicional" => $api,
                )
              );

            if ($cancelEnvioAfterCobClient) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "La información de ubicación del cliente con folio $folio_cliente ha sido actualizada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "La información de ubicación del cliente con folio $folio_cliente no fue actualizada debido a errores internos, intente nuevamente o comuniquese a soporte");
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "El cliente que busca no existe"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 400,
        "message" => "No fue posible procesar los datos recibidos"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clientePapeleraSave(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, ["user_token" => "required|string", "token_cat_clientes" => "required|string"]);
      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        //validaciones
        if (isset($token_cat_clientes) && !empty($token_cat_clientes)) {
          $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "ingr_catalogo_clientes.status" => TRUE,
              "ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token
            ])->get();

          if (count($queryData) > 0 && count($queryData) == 1) {
            foreach ($queryData as $vClient) {
              if ($vClient->authorized == TRUE) {
                if ($vClient->post_folio == NULL) {
                  $folio_client = "CLI-" . $JwtAuth->generarFolio($vClient->folio);
                } else {
                  $folio_client = "CLI-" . $JwtAuth->generarFolio($vClient->folio) . '-' . $vClient->post_folio;
                }
              } else {
                $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vClient->temp_folio);
              }

              $querySoli = ClientesModelo::join("ingr_catalogo_clientes_soli_auth AS soli", "ingr_catalogo_clientes.id", "soli.cliente")
                ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "soli.soli_aprobada" => TRUE])->get();

              if (count($querySoli) == 0) {
                $clientUpdate = ClientesModelo::find(1);
                $clientUpdate->where("token_cat_clientes", $vClient->token_cat_clientes)->update(["status" => FALSE, "fecha_delete_client" => time()]);

                if ($clientUpdate) {
                  $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "message" => "El cliente con folio " . $folio_client . " ha sido eliminado satisfactoriamente"
                  );
                } else {
                  $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Error en eliminación de cliente, por favor verifique su información o comuniquese a soporte"
                  );
                }
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Eliminación de cliente no completada, está relacionado con otros catalogos y registros, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'el cliente buscado no existe'
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error al obtener información del cliente registrado, por favor verifique su información o comuniquese a soporte"
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

  public function catalogoClientesEliminados(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, ["user_token" => "required|string"]);
      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $queryClientes = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
          //->join("teci_pais AS ps","client.nacionalidad","ps.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
            "ingr_catalogo_clientes.status" => FALSE
          ])->get();

        foreach ($queryClientes as $vRowClient) {
          //da_te_default_timezone_set($vRowClient->zona_horaria);
          //$fecha = date('d-m-Y H:i:s',$fechaDelete);
          if ($vRowClient->authorized == TRUE) {
            if ($vRowClient->post_folio == NULL) {
              $folio_client = "CLI-" . $JwtAuth->generarFolio($vRowClient->folio);
            } else {
              $folio_client = "CLI-" . $JwtAuth->generarFolio($vRowClient->folio) . '-' . $vRowClient->post_folio;
            }
          } else {
            $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vRowClient->temp_folio);
          }

          if ($vRowClient->regimen_fiscal != NULL) {
            $clientRegFisc = DB::table("ingr_catalogo_clientes AS catklient")
              ->join("sos_regimen_fiscal AS regfis", "catklient.regimen_fiscal", "regfis.id")
              ->where([
                "catklient.token_cat_clientes" => $vRowClient->token_cat_clientes,
                "catklient.status" => true
              ])->get();
            $tkn_reg_fis = $clientRegFisc[0]->token_regimen_fiscal;
            $reg_fis = $clientRegFisc[0]->clave . "-" . $clientRegFisc[0]->descripcion;
          } else {
            $tkn_reg_fis = null;
            $reg_fis = null;
          }

          $arrayForeach = array(
            "token_cat_clientes" => $vRowClient->token_cat_clientes,
            "folio_client" => $folio_client,
            "authorized" => $vRowClient->authorized == TRUE ? true : false,
            "auth_fecha" => $vRowClient->authorized == TRUE ? gmdate('Y-m-d H:i:s', $vRowClient->authorized_fecha) : "---",
            "publico_general" => $vRowClient->publico_general == TRUE ? true : false,
            //"listaPrecios" => $vRowClient->listaPrecios,
            "tkn_reg_fis" => $tkn_reg_fis,
            "reg_fis" => $reg_fis,
            "nombre" => $JwtAuth->desencriptar($vRowClient->nombre_extendido),
            "rfc_generico" => $vRowClient->rfc_generico,
            "rfc" => $vRowClient->rfc != NULL ? $JwtAuth->desencriptar($vRowClient->rfc) : "---",
            "tax_id" => $vRowClient->tax_id != NULL ? $JwtAuth->desencriptar($vRowClient->tax_id) : "---",
            "fecha_delete" => date("d-m-Y H:i:s", $vRowClient->fecha_delete_client),
            //"pais" => $vRowClient->pais,
          );
          $listaClientes[] = $arrayForeach;
        }
        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "clientes" => $listaClientes,
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

  public function clienteRestaurar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, ["user_token" => "required|string", "token_cat_clientes" => "required|string"]);
      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        //validaciones
        if (isset($token_cat_clientes) && !empty($token_cat_clientes)) {
          $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "ingr_catalogo_clientes.status" => FALSE,
              "ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token
            ])->get();

          if (count($queryData) > 0 && count($queryData) == 1) {
            foreach ($queryData as $vClient) {
              if ($vClient->authorized == TRUE) {
                if ($vClient->post_folio == NULL) {
                  $folio_client = "CLI-" . $JwtAuth->generarFolio($vClient->folio);
                } else {
                  $folio_client = "CLI-" . $JwtAuth->generarFolio($vClient->folio) . '-' . $vClient->post_folio;
                }
              } else {
                $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vClient->temp_folio);
              }

              $clientUpdate = ClientesModelo::find(1);
              $clientUpdate->where("token_cat_clientes", $vClient->token_cat_clientes)->update(["status" => TRUE, "fecha_delete_client" => NULL]);

              if ($clientUpdate) {
                $dataMensaje = array(
                  "status" => "success",
                  "code" => 200,
                  "message" => "El cliente con folio " . $folio_client . " ha sido restaurado satisfactoriamente"
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Error en restauración de cliente, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'el cliente buscado no existe'
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error al obtener información del cliente registrado, por favor verifique su información o comuniquese a soporte"
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

  public function clienteEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $listaClientes = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, ["user_token" => "required|string", "token_cat_clientes" => "required|string"]);
      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_clientes = $parametrosArray["token_cat_clientes"];
        //validaciones
        if (isset($token_cat_clientes) && !empty($token_cat_clientes)) {
          $queryData = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "ingr_catalogo_clientes.status" => FALSE,
              "ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token
            ])->get();

          if (count($queryData) > 0 && count($queryData) == 1) {
            foreach ($queryData as $vClient) {
              if ($vClient->authorized == TRUE) {
                if ($vClient->post_folio == NULL) {
                  $folio_client = "CLI-" . $JwtAuth->generarFolio($vClient->folio);
                } else {
                  $folio_client = "CLI-" . $JwtAuth->generarFolio($vClient->folio) . '-' . $vClient->post_folio;
                }
              } else {
                $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vClient->temp_folio);
              }

              $querySoli = ClientesModelo::join("ingr_catalogo_clientes_soli_auth AS soli", "ingr_catalogo_clientes.id", "soli.cliente")
                ->where(["ingr_catalogo_clientes.token_cat_clientes" => $token_cat_clientes, "soli.soli_aprobada" => TRUE])->get();
              //echo count($querySoli);
              if (count($querySoli) == 0) {
                $clientUpdate = ClientesModelo::find(1);
                $clientUpdate->where("token_cat_clientes", $vClient->token_cat_clientes)->delete();

                if ($clientUpdate) {
                  $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "message" => "El cliente con folio " . $folio_client . " ha sido eliminado satisfactoriamente"
                  );
                } else {
                  $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Error en eliminación de cliente, por favor verifique su información o comuniquese a soporte"
                  );
                }
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Eliminación de cliente no completada, está relacionado con otros catalogos y registros, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'el cliente buscado no existe'
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error al obtener información del cliente registrado, por favor verifique su información o comuniquese a soporte"
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

  public function verifyClienteExist(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "empresa_token" => "string",
        "rfc_generico" => "required|string",
        "client_rfc" => "string",
        "id_tax" => "string",
        "nombre" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "La infomación que ha intantado registrar es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $client_rfcGenerico = strtolower($parametrosArray["rfc_generico"]);
        $client_rfc = strtolower($parametrosArray["client_rfc"]);
        $client_idTax = strtolower($parametrosArray["id_tax"]);
        $nombreClient = strtolower($parametrosArray["nombre"]);

        $queryClientes = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
          ->join("teci_pais AS ps", "client.nacionalidad", "ps.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->where([
            "emp.empresa_token" => $usuario->empresa_token,
            "ingr_catalogo_clientes.status" => true
          ])->get();

        if (count($queryClientes) != 0) {
          $countVerifica = 0;
          $invalidName = "";
          foreach ($queryClientes as $vCli) {
            $nombre_cliente = $vCli->nombre_extendido == "" || $vCli->nombre_extendido == NULL ?
              ($vCli->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vCli->paterno, $vCli->materno, $vCli->nombre) :
                $JwtAuth->desencriptar($vCli->denominacion_rs)) : $JwtAuth->desencriptar($vCli->nombre_extendido);
            $rfc_generico = strtolower($vCli->rfc_generico);
            $rfc_client = $vCli->rfc != NULL ? strtolower($JwtAuth->desencriptar($vCli->rfc)) : "";
            $taxId_client = $vCli->tax_id != NULL ? strtolower($JwtAuth->desencriptar($vCli->tax_id)) : "";

            if ($client_rfc != "") {
              if ($rfc_client == $client_rfc) {
                ++$countVerifica;
                $invalidName = $nombre_cliente;
              }
            } else if ($client_idTax != "") {
              if ($taxId_client == $client_idTax) {
                ++$countVerifica;
                $invalidName = $nombre_cliente;
              }
            } else if ($rfc_generico == $client_rfcGenerico && $nombre_cliente == $nombreClient) {
              ++$countVerifica;
              $invalidName = $nombre_cliente;
            } else {
              if ($nombreClient == $nombre_cliente) {
                ++$countVerifica;
                $invalidName = $nombre_cliente;
              }
            }
          }

          if ($countVerifica > 0) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "El cliente verificado ya ha sido registrado con nombre " . strtoupper($invalidName)
            );
          } else {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "El cliente con el nombre " . strtoupper($nombreClient) . " no ha sido registrado"
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "success",
            "code" => 200,
            "message" => "El cliente con el nombre " . strtoupper($nombreClient) . " no ha sido registrado"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los informacion que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function verifyClienteExistPerfil(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "empresa_token" => "string",
        "token_cliente" => "required|string",
        "client_rfc" => "string",
        "id_tax" => "string",
        "nombre" => "required|string",
        "nombre_comercial" => "string",
        "sitio_web" => "string",
        "regimen_fiscal" => "string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "La infomación que ha intantado registrar es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cliente = strtolower($parametrosArray["token_cliente"]);
        $client_rfc = $parametrosArray["client_rfc"];
        $client_idTax = $parametrosArray["id_tax"];
        $nombreClient = $parametrosArray["nombre"];
        $nombre_comercial = $parametrosArray["nombre_comercial"];
        $sitio_web = $parametrosArray["sitio_web"];
        $regimen_fiscal = $parametrosArray["regimen_fiscal"];

        $queryClientes = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
          ->join("teci_pais AS ps", "client.nacionalidad", "ps.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->where([
            "ingr_catalogo_clientes.token_cat_clientes" => $token_cliente,
            "emp.empresa_token" => $usuario->empresa_token,
            "ingr_catalogo_clientes.status" => true
          ])->get();

        $countVerifica = 0;
        $invalidName = "";
        foreach ($queryClientes as $vCli) {
          $nombre_cliente = $vCli->nombre_extendido == "" || $vCli->nombre_extendido == NULL ? ($vCli->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vCli->paterno, $vCli->materno, $vCli->nombre) :
            $JwtAuth->desencriptar($vCli->denominacion_rs)) : $JwtAuth->desencriptar($vCli->nombre_extendido);

          $rfc_generico = strtolower($vCli->rfc_generico);
          $rfc_client = $vCli->rfc != NULL ? strtolower($JwtAuth->desencriptar($vCli->rfc)) : "---";
          $taxId_client = $vCli->tax_id != NULL ? strtolower($JwtAuth->desencriptar($vCli->tax_id)) : "---";

          $sql_client_rfc = $client_rfc != $rfc_client ? ($client_rfc != "" && $client_rfc != "---" ? $JwtAuth->encriptar(strtolower($client_rfc)) : NULL) : $vCli->rfc;
          $sql_client_idTax = $client_idTax != $taxId_client ? ($client_idTax != "" && $client_idTax != "---" ? $JwtAuth->encriptar(strtolower($client_idTax)) : NULL) : $vCli->tax_id;
          $sql_client_nombre = $nombreClient != $nombre_cliente ? ($nombreClient != "" ? $JwtAuth->encriptar(strtolower($nombreClient)) : NULL) : $JwtAuth->encriptar($nombre_cliente);
          $selectRegFisc = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $regimen_fiscal)->value("id");

          $regGenerales = DB::table("ingr_catalogo_clientes AS catklient")
            ->join("sos_personas AS client", "catklient.cliente", "client.id")
            ->where(['catklient.token_cat_clientes' => $vCli->token_cat_clientes])
            ->limit(1)->update(
              array(
                "client.nombre_extendido" => $sql_client_nombre,
                "client.rfc" => $sql_client_rfc,
                "client.tax_id" => $sql_client_idTax,
                "client.nombre_com" => $nombre_comercial != "---" ? $JwtAuth->encriptar($nombre_comercial) : NULL,
                "client.sitio_web" => $sitio_web != "---" ? $JwtAuth->encriptar($sitio_web) : NULL,
                "catklient.regimen_fiscal" => $selectRegFisc,
              )
            );

          if ($regGenerales) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "La información recibida ha sido actualizada"
            );
          } else {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "La información recibida no fue actualizada, por favor intente mas tarde"
            );
          }

          if ($client_rfc != "") {
            if ($rfc_client == $client_rfc && $nombreClient == $nombre_cliente) {
              ++$countVerifica;
              $invalidName = $nombre_cliente;
            }
          } else if ($client_idTax != "") {
            if ($taxId_client == $client_idTax && $nombreClient == $nombre_cliente) {
              ++$countVerifica;
              $invalidName = $nombre_cliente;
            }
          } else if ($nombreClient == $nombre_cliente) {
            ++$countVerifica;
            $invalidName = $nombre_cliente;
          }
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los informacion que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function clienteSolicitudRegistro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "rfc_generico" => "required|string",
        "client_rfc" => "string",
        "id_tax" => "string",
        "radioClient" => "required|string",
        "subtipoClient" => "required|string",
        "paterno" => "string",
        "materno" => "string",
        "nombres" => "string",
        "razon_social" => "string",
        "comercial_nombre" => "string",
        "curp" => "string",
        "paistoken" => "string",
        "sitio_web" => "string",
        "tknRegimenFiscal" => "string",
        "cod_postal" => "string",
        "dipomex_cod_postal_estado" => "string",
        "dipomex_cod_postal_municipio" => "string",
        "dipomex_cod_postal_cp" => "string",
        "dipomex_cod_postal_colonia_vinculada" => "string",
        "listnewdireccionNac" => "array",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        //return response()->json(["message" => "prueba2","code" => 200,"status" => "error"]);
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $fechaAlta = time();
        $user_token = $parametrosArray["user_token"];
        $rfc_generico = $parametrosArray["rfc_generico"];
        $client_rfc = $parametrosArray["client_rfc"];
        $rfc_client = NULL;
        $id_tax = $parametrosArray["id_tax"];
        $idtax = NULL;
        $radioClient = $parametrosArray["radioClient"];
        $subtipoClient = $parametrosArray["subtipoClient"];
        $paterno = $parametrosArray["paterno"];
        $materno = $parametrosArray["materno"];
        $nombres = $parametrosArray["nombres"];
        $razon_social = $parametrosArray["razon_social"];
        $comercial_nombre = $parametrosArray["comercial_nombre"];
        $curp = $parametrosArray["curp"];
        $paistoken = $parametrosArray["paistoken"];
        $sitio_web = $parametrosArray["sitio_web"];
        $tknRegimenFiscal = $parametrosArray["tknRegimenFiscal"];
        $cod_postal = $parametrosArray["cod_postal"];

        $dipomex_cod_postal_estado = $parametrosArray["dipomex_cod_postal_estado"];
        $dipomex_cod_postal_municipio = $parametrosArray["dipomex_cod_postal_municipio"];
        $dipomex_cod_postal_cp = $parametrosArray["dipomex_cod_postal_cp"];
        $dipomex_cod_postal_colonia_vinculada = $parametrosArray["dipomex_cod_postal_colonia_vinculada"];
        $listnewdireccionNac = $parametrosArray["listnewdireccionNac"];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        $patronNum = '/^[1-9][0-9]*$/';
        $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
        $patronNumCred = '/^[0-9$,.-]*$/';
        $patronRfc = '/[aA0-zZ9]/';
        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

        //return response()->json(["message" => "prueba3","code" => 200,"status" => "error"]);
        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp
                    WHERE emp.empresa_token = ?", [$usuario->empresa_token]);
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
        //return response()->json(["message" => "prueba5","code" => 200,"status" => "error"]);

        $folio_nuevo = NULL;
        $post_folio = NULL;

        $select_fol_temp = DB::select("SELECT MAX(temp_folio)+1 AS fol_max FROM ingr_catalogo_clientes where administrador = 
                    (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
        foreach ($select_fol_temp as $vTemp) {
          //$folio_temporal = $vTemp->fol_max;
          //return response()->json(["message" => "prueba8","code" => 200,"status" => "error"]);
          $folio_temp = $vTemp->fol_max;
          $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($vTemp->fol_max);
        }

        if ($client_rfc != "") {
          if (isset($client_rfc) && isset($client_rfc) && preg_match($patronRfc, $client_rfc)) {
            $rfc_client = $JwtAuth->encriptar($client_rfc);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "error al registrar rfc del cliente"
            );
          }
        }

        if ($id_tax != "") {
          if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
            $idtax = $JwtAuth->encriptar($id_tax);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "error al registrar idtax del cliente"
            );
          }
        }

        $paterno_txt = NULL;
        $materno_txt = NULL;
        $nombres_txt = NULL;
        $razon_social_txt = NULL;
        $empresa_txt = NULL;
        $comercial_nombre_txt = NULL;
        $curp_txt = NULL;
        $pais_txt = NULL;
        $sitio_web_txt = NULL;
        $regimen_fiscal_txt = NULL;

        if (preg_match($patron, $radioClient)) {
          if ($radioClient == "extranjero") {
            if (preg_match($patron, $subtipoClient)) {
              if ($subtipoClient == "clientMoral") {
                //return response()->json(["message' => $parametrosArray["pais"],"code" => 200,"status" => "error"]);
                if (
                  isset($razon_social) && !empty($razon_social) &&
                  preg_match($patron, $razon_social) && isset($paistoken) && !empty($paistoken)
                ) {
                  //return response()->json(["message' => 'error',"code" => 200,"status" => "error"]);
                  $razon_social_txt = $JwtAuth->encriptar($razon_social);
                  $empresa_txt = $razon_social;
                  //return response()->json(["message' => 'error',"code" => 200,"status" => "error"]);
                  $selectPais = DB::select("SELECT id FROM pais WHERE token_pais = ?", [$paistoken]);
                  $pais_txt = $selectPais[0]->id;
                  //return response()->json(["message' => 'error',"code" => 200,"status" => "error"]);
                  if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "nomcom",
                        "message" => "Error en nombre comercial de su cliente"
                      );
                    }
                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "websitio",
                        "message" => "Error en sitio web de su cliente"
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $sitio_web_txt = NULL;
                  }
                } else {
                  if (!isset($razon_social) || empty($razon_social) || !preg_match($patron, $razon_social)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "nomemp",
                      "message" => "Error en nombre de empresa de su cliente"
                    );
                  }
                  if (!isset($paistoken) || empty($paistoken)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "pais",
                      "message" => "Error en pais de su cliente"
                    );
                  }
                }
              }
              //return response()->json(["message' => 'error',"code" => 200,"status" => "error"]);
              if ($subtipoClient == "clientFisica") {
                if (
                  isset($paterno) && !empty($paterno) && preg_match($patron, $paterno) &&
                  isset($materno) && !empty($materno) && preg_match($patron, $materno) &&
                  isset($nombres) && !empty($nombres) && preg_match($patron, $nombres) &&
                  isset($paistoken) && !empty($paistoken)
                ) {

                  $paterno_txt = $JwtAuth->encriptar($paterno);
                  $materno_txt = $JwtAuth->encriptar($materno);
                  $nombres_txt = $JwtAuth->encriptar($nombres);
                  $empresa_txt = $paterno . ' ' . $materno . ' ' . $nombres;

                  if (
                    isset($comercial_nombre) && !empty($comercial_nombre) &&
                    isset($sitio_web) && !empty($sitio_web)
                  ) {

                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "nomcom",
                        "message" => "Error en nombre comercial de su cliente"
                      );
                    }

                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "websitio",
                        "message" => "Error en sitio web de su cliente"
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $sitio_web_txt = NULL;
                  }

                  $selectPais = DB::select("SELECT id FROM pais WHERE token_pais = ?", [$paistoken]);
                  $pais_txt = $selectPais[0]->id;
                } else {
                  if (!isset($paterno) || empty($paterno) || !preg_match($patron, $paterno)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "paternoPF",
                      "message" => "Error en apellido paterno de su cliente"
                    );
                  }
                  if (!isset($materno) || empty($materno) || !preg_match($patron, $materno)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "maternoPF",
                      "message" => "Error en apellido materno de su cliente"
                    );
                  }
                  if (!isset($nombres) || empty($nombres) || !preg_match($patron, $nombres)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "nombrePF",
                      "message" => "Error en nombre de su cliente"
                    );
                  }
                  if (!isset($paistoken) || empty($paistoken)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "paisPF",
                      "message" => "Error en pais de su cliente"
                    );
                  }
                }
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "codeClientGenError" => "clbint",
                "message" => "Seleccione subtipo de cliente (persona física o moral)"
              );
            }
          }

          if ($radioClient == "nacional") {
            if (preg_match($patron, $subtipoClient)) {
              if ($subtipoClient == "clientMoral") {
                if (isset($razon_social) && !empty($razon_social) && preg_match($patron, $razon_social)) {
                  $razon_social_txt = $JwtAuth->encriptar($razon_social);
                  $empresa_txt = $razon_social;
                  if (
                    isset($comercial_nombre) && !empty($comercial_nombre) &&
                    isset($sitio_web) && !empty($sitio_web)
                  ) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "nomcom",
                        "message" => "Error en nombre comercial de su cliente"
                      );
                    }
                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "websitio",
                        "message" => "Error en sitio web de su cliente"
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $sitio_web_txt = NULL;
                  }
                  $pais_txt = '118';
                } else {
                  if (empty($razon_social) || !preg_match($patron, $razon_social)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "nomemp",
                      "message" => "Error en nombre de empresa de su cliente"
                    );
                  }
                }
              }

              if ($subtipoClient == "clientFisica") {
                if (
                  isset($paterno) && !empty($paterno) && preg_match($patron, $paterno) &&
                  isset($materno) && !empty($materno) && preg_match($patron, $materno) &&
                  isset($nombres) && !empty($nombres) && preg_match($patron, $nombres)
                ) {

                  $paterno_txt = $JwtAuth->encriptar($paterno);
                  $materno_txt = $JwtAuth->encriptar($materno);
                  $nombres_txt = $JwtAuth->encriptar($nombres);
                  $empresa_txt = $paterno . ' ' . $materno . ' ' . $nombres;

                  if (
                    isset($comercial_nombre) && !empty($comercial_nombre) &&
                    isset($curp) && !empty($curp) &&
                    isset($sitio_web) && !empty($sitio_web)
                  ) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "nomcom",
                        "message" => "Error en nombre comercial de su cliente"
                      );
                    }
                    if (preg_match($patronRfc, $curp)) {
                      $curp_txt = $JwtAuth->encriptar($curp);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "clbint",
                        "message" => "Error en curp de su cliente"
                      );
                    }
                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeClientGenError" => "websitio",
                        "message" => "Error en sitio web de su cliente"
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $curp_txt = NULL;
                    $sitio_web_txt = NULL;
                  }
                  $pais_txt = '118';
                } else {
                  if (!isset($paterno) || empty($paterno) || !preg_match($patron, $paterno)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "paternoPF",
                      "message" => "Error en apellido paterno de su cliente"
                    );
                  }
                  if (!isset($materno) || empty($materno) || !preg_match($patron, $materno)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "maternoPF",
                      "message" => "Error en apellido materno de su cliente"
                    );
                  }
                  if (!isset($nombres) || empty($nombres) || !preg_match($patron, $nombres)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeClientGenError" => "nombrePF",
                      "message" => "Error en nombre de su cliente"
                    );
                  }
                }
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "codeClientGenError" => "clbint",
                "message" => "Seleccione subtipo de cliente (persona física o moral)"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "codeClientGenError" => "clbint",
            "message" => "Seleccione tipo de cliente (nacional o extranjero)"
          );
        }

        $selectRfisc = DB::select("SELECT id FROM sos_regimen_fiscal 
                    WHERE token_regimen_fiscal = ?", [$tknRegimenFiscal]);
        $regimen_fiscal_txt = $selectRfisc[0]->id;

        $listaClientes = DB::table("ingr_catalogo_clientes AS catklient")
          ->join("sos_personas AS client", "catklient.cliente", "client.id")
          ->join("teci_pais AS ps", "client.nacionalidad", "ps.id")
          ->join("teci_forma_pago AS pago", "catklient.forma_cobro", "pago.id")
          ->join("main_empresas AS emp", "catklient.administrador", "=", "emp.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'catklient.status' => true
          ])->get();

        $countVerifica = 0;
        $invalidName = "";

        foreach ($listaClientes as $vListCli) {
          if ($vListCli->denominacion_rs != "") {
            $nameClient = strtolower($JwtAuth->desencriptar($vListCli->denominacion_rs));
          } else {
            $nameClient = strtolower($JwtAuth->desencriptar($vListCli->paterno) . " " .
              $JwtAuth->desencriptar($vListCli->materno) . " " .
              $JwtAuth->desencriptar($vListCli->nombre));
          }
          $rfc_generico_f = strtolower($vListCli->rfc_generico);
          //return response()->json(["message' => $nombre_txt,"code" => 200,"status" => "error"]);
          if ($rfc_client != NULL) {
            if ($vListCli->rfc == $rfc_client) {
              ++$countVerifica;
              $invalidName = $nameClient;
            }
          } else if ($idtax != "") {
            if ($vListCli->tax_id == $idtax) {
              ++$countVerifica;
              $invalidName = $nameClient;
            }
          } else if ($vListCli->rfc_generico == $rfc_generico && $nameClient == $nombre_txt) {
            ++$countVerifica;
            $invalidName = $nameClient;
          } else {
            if ($nameClient == $nombre_txt) {
              ++$countVerifica;
              $invalidName = $nameClient;
            }
          }
        }
        if ($countVerifica == 0) {
          $tknPClient = $JwtAuth->encriptarToken(
            $fechaAlta,
            $paterno_txt,
            $materno_txt,
            $nombres_txt,
            $razon_social_txt,
            $comercial_nombre_txt,
            $sitio_web_txt,
            $pais_txt,
            $rfc_client
          );

          $insertClient = DB::table("sos_personas")
            ->insert(array(
              "token_personas" => $tknPClient,
              "paterno" => $paterno_txt,
              "materno" => $materno_txt,
              "nombre" => $nombres_txt,
              "denominacion_rs" => $razon_social_txt,
              "nombre_com" => $comercial_nombre_txt,
              "sitio_web" => $sitio_web_txt,
              "nacionalidad" => $pais_txt,
              "rfc_generico" => $rfc_generico,
              "rfc" => $rfc_client,
              "tax_id" => $idtax,
              "curp" => $curp_txt,
            ));
          //return response()->json(["message" => "prueba21","code" => 200,"status" => "error"]);
          if ($insertClient) {
            $selectClient = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$tknPClient]);
            $tokenClient = $JwtAuth->encriptarToken($folio_client . $tknPClient . $selectEmp[0]->id);

            $creaKliente = new ClientesModelo();
            $creaKliente->token_cat_clientes = $tokenClient;
            $creaKliente->folio = NULL;
            $creaKliente->post_folio = NULL;
            $creaKliente->fechaAlta = $fechaAlta;
            $creaKliente->cliente = $selectClient[0]->id;
            $creaKliente->lista_precios = NULL;
            $creaKliente->regimen_fiscal = $regimen_fiscal_txt;
            $creaKliente->temp_folio = $folio_temp;
            $creaKliente->authorized = FALSE;
            $creaKliente->status = TRUE;
            $creaKliente->fecha_delete_client = "";
            $creaKliente->administrador = $selectEmp[0]->id;
            $saveClient = $creaKliente->save();

            if ($saveClient) {
              $selectClientCat = DB::select("SELECT id FROM ingr_catalogo_clientes WHERE token_cat_clientes = ?", [$tokenClient]);

              $contadorInsertUbicaciones = 0;

              if ($parametrosArray["radioClient"] == "extranjero") {
                $tipo_direccion = "dirección fiscal";
                //$cod_postal = $parametrosArray["cod_postal"];
                //$tkn_cod_postal = $parametrosArray["tkn_cod_postal"];
                $cpostalDir = $JwtAuth->encriptar($cod_postal);
                $clasificacionDir = $JwtAuth->encriptar("matriz");
                $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $cpostalDir, $clasificacionDir);

                $fisinsertDir = DB::table("direcciones")
                  ->insert(array(
                    "token_direccion" => $tokenCDir,
                    "tipo_direccion" => $tipo_direccion,
                    "clase" => $clasificacionDir,
                    "cod_postalext" => $cpostalDir,
                    "pais" => $pais_txt,
                    "cliente" => $selectClientCat[0]->id,
                    "status" => TRUE,
                    "administrador" => $selectEmp[0]->id,
                  ));
                if ($fisinsertDir) {
                  $contadorInsertUbicaciones++;
                }
              } else {
                if (count($listnewdireccionNac) != 0) {
                  for ($nd = 0; $nd < count($listnewdireccionNac); $nd++) {
                    $listnew_estado = $listnewdireccionNac[$nd]["estado"];
                    $listnew_municipio = $listnewdireccionNac[$nd]["municipio"];
                    $listnew_codigo_postal = $listnewdireccionNac[$nd]["codigo_postal"];
                    $listnew_colonia = $listnewdireccionNac[$nd]["colonia"];

                    $tipo_direccion = "dirección fiscal";
                    $clasificacionDir = $JwtAuth->encriptar("matriz");
                    $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $listnew_estado, $listnew_municipio, $listnew_codigo_postal, $listnew_colonia, $clasificacionDir);
                    $fisinsertDir = DB::table("teci_direcciones")
                      ->insert(array(
                        "token_direccion" => $tokenCDir,
                        "tipo_direccion" => $tipo_direccion,
                        "clase" => $clasificacionDir,
                        "pais" => 118,
                        "estado_edit" => $JwtAuth->encriptar($listnew_estado),
                        "municipio_edit" => $JwtAuth->encriptar($listnew_municipio),
                        "c_postal_edit" => $listnew_codigo_postal,
                        "colonia_edit" => $JwtAuth->encriptar($listnew_colonia),
                        "adicional" => "no_api_found",
                        "cliente" => $selectClientCat[0]->id,
                        "status" => TRUE,
                        "administrador" => $selectEmp[0]->id,
                      ));
                  }
                } else {
                  $tipo_direccion = "dirección fiscal";
                  $clasificacionDir = $JwtAuth->encriptar("matriz");
                  $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
                  $listnewdireccionNac = $parametrosArray["listnewdireccionNac"];
                  $fisinsertDir = DB::table("teci_direcciones")
                    ->insert(array(
                      "token_direccion" => $tokenCDir,
                      "tipo_direccion" => $tipo_direccion,
                      "clase" => $clasificacionDir,
                      "pais" => 118,
                      "estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
                      "municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
                      "c_postal_edit" => $dipomex_cod_postal_cp,
                      "colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
                      "adicional" => "api",
                      "cliente" => $selectClientCat[0]->id,
                      "status" => TRUE,
                      "administrador" => $selectEmp[0]->id,
                    ));
                }

                if ($fisinsertDir) {
                  $contadorInsertUbicaciones++;
                }
              }

              //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
              if ($contadorInsertUbicaciones == 1) {
                //return response()->json(["message" => "prueba26","code" => 200,"status" => "error"]);
                $filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/clientes/" . $folio_client . "-" . $fechaAlta . "/";
                //return response()->json(["message" => "prueba27","code" => 200,"status" => "error"]);
                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
                }
                //return response()->json(["message" => "prueba28","code" => 200,"status" => "error"]);
                QRCode::text($tokenClient)->setOutfile(
                  Storage::path("public/root/" . $filepath . $folio_client . " - " . $fechaAlta . "-QRCode.png")
                )->png();
                //$cumplimiento;
                //$formaPagoClabeInterbank;
                //return response()->json(["message" => "prueba32","code" => 200,"status" => "error"]);
                $JwtAuth->insertBitacoraActividad(
                  "egresos",
                  "catalogos",
                  "clientes",
                  $folio_client,
                  "registro en el catalogo de clientes",
                  $usuario->empresa_token,
                  $usuario->user_token
                );
                //return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);
                $dataMensaje = array(
                  "status" => 'success',
                  "code" => 200,
                  "message" => "Cliente registrado satisfactoriamente con el folio " . $folio_client
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Datos de personal de contacto/direcciones/creditos de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
                );
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Datos generales de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
              );
            }
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Datos generales de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "ya existe un cliente con esta información"
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

  public function registrarCliente(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("cliente");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "rfc_generico" => "required|string",
        "client_rfc" => "string",
        "id_tax" => "string",
        "radioClient" => "required|string",
        "subtipoClient" => "required|string",
        "name_cliente" => "string",
        "comercial_nombre" => "string",
        "curp" => "string",
        "paistoken" => "string",
        "sitio_web" => "string",
        "tknRegimenFiscal" => "string",
        "decideinfocontacto" => "required|boolean",
        "arrayContactoPersonal" => "array",
        "tiene_docs_fiscales" => "required|boolean",
        "valnoCargaDocsFiscalesRazon" => "string",
        "creditoAsignado" => "required|boolean",
        "data_moneda_code" => "string",
        "data_moneda_decimales" => "string",
        "txtlimiteCredito" => "string",
        "txtdiasCobroCredit" => "numeric",
        "selectComienzaCobroClient" => "string",
        "decideformaCobro" => "required|boolean",
        "formaCobroAltaClient" => "string",
        "receptFactura" => "boolean",
        "classRecibeArtcobro" => "boolean",
        "cod_postal" => "string",
        "dipomex_cod_postal_estado" => "string",
        "dipomex_cod_postal_municipio" => "string",
        "dipomex_cod_postal_cp" => "string",
        "dipomex_cod_postal_colonia_vinculada" => "string",
        "listnewdireccionNac" => "array",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Cliente invalido" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        //return response()->json(["message" => "prueba2","code" => 200,"status" => "error"]);
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $rfc_generico = $parametrosArray["rfc_generico"];
        $client_rfc = $parametrosArray["client_rfc"];
        $rfc_client = NULL;
        $id_tax = $parametrosArray["id_tax"];
        $idtax = NULL;
        $radioClient = $parametrosArray["radioClient"];
        $subtipoClient = $parametrosArray["subtipoClient"];
        $name_cliente = $parametrosArray["name_cliente"];
        $comercial_nombre = $parametrosArray["comercial_nombre"];
        $curp = $parametrosArray["curp"];
        $paistoken = $parametrosArray["paistoken"];
        $sitio_web = $parametrosArray["sitio_web"];
        $tknRegimenFiscal = $parametrosArray["tknRegimenFiscal"];

        $decideinfocontacto = $parametrosArray["decideinfocontacto"];
        $listaContactoPersonal = $parametrosArray["arrayContactoPersonal"];
        $tiene_docs_fiscales = $parametrosArray["tiene_docs_fiscales"];
        $valnoCargaDocsFiscalesRazon = $parametrosArray["valnoCargaDocsFiscalesRazon"];
        $creditoAsignado = $parametrosArray["creditoAsignado"];
        $data_moneda_code = $parametrosArray["data_moneda_code"];
        $data_moneda_decimales = $parametrosArray["data_moneda_decimales"];
        $txtlimiteCredito = $parametrosArray["txtlimiteCredito"];
        $txtdiasCobroCredit = $parametrosArray["txtdiasCobroCredit"];
        $selectComienzaCobroClient = $parametrosArray["selectComienzaCobroClient"];
        $decideformaCobro = $parametrosArray["decideformaCobro"];
        $formaCobroAltaClient = $parametrosArray["formaCobroAltaClient"];
        $emisionFacturas = $parametrosArray["receptFactura"];
        $envioArtAfterCobro = $parametrosArray["classRecibeArtcobro"];

        $cod_postal = $parametrosArray["cod_postal"];
        $dipomex_cod_postal_estado = $parametrosArray["dipomex_cod_postal_estado"];
        $dipomex_cod_postal_municipio = $parametrosArray["dipomex_cod_postal_municipio"];
        $dipomex_cod_postal_cp = $parametrosArray["dipomex_cod_postal_cp"];
        $dipomex_cod_postal_colonia_vinculada = $parametrosArray["dipomex_cod_postal_colonia_vinculada"];
        $listnewdireccionNac = $parametrosArray["listnewdireccionNac"]; //return response()->json(["message" => "prueba3","code" => 200,"status" => "error"]);

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        $patronNum = '/^[1-9][0-9]*$/';
        $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
        $patronNumCred = '/^[0-9$,.-]*$/';
        $patronRfc = '/[aA0-zZ9]/';
        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp
                    WHERE emp.empresa_token = ?", [$usuario->empresa_token]);
        //return response()->json(["message" => "prueba4","code" => 200,"status" => "error"]);
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
        //return response()->json(["message" => "prueba5","code" => 200,"status" => "error"]);

        $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                    FROM sos_last_folders AS fold JOIN main_empresas AS emp
                    WHERE fold.ing_clientes = TRUE AND fold.empresa = emp.id
                    AND emp.empresa_token = ?", [$usuario->empresa_token]);
        //return response()->json(["message" => "prueba6","code" => 200,"status" => "error"]);
        if (count($folioSistema) == 1) {
          if ($folioSistema[0]->folio == 1000000000) {
            $post_folio_db = DB::select("SELECT post_folio FROM ingr_catalogo_clientes
                            WHERE id = (SELECT MAX(catkli.id) FROM ingr_catalogo_clientes AS catkli
                            JOIN main_empresas AS emp WHERE catkli.administrador = emp.id
                            AND emp.empresa_token = ?)", [$usuario->empresa_token]);

            $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
            $folio_nuevo = 1;
            //return response()->json(["message" => "prueba7","code" => 200,"status" => "error"]);
          } else {
            $post_folio = NULL;
            $folio_nuevo = $folioSistema[0]->folio;
            //return response()->json(["message" => "prueba7","code" => 200,"status" => "error"]);
          }
        } else {
          $post_folio = NULL;
          $folio_nuevo = 1;
        }
        //return response()->json(["message" => "prueba8","code" => 200,"status" => "error"]);
        $folio_client = "CLI-" . $JwtAuth->generarFolio($folio_nuevo) . ($post_folio != NULL ? '-' . $post_folio : '');
        //return response()->json(["message" => "prueba9","code" => 200,"status" => "error"]);
        $fechaAlta = time();

        $rfc_generico = $parametrosArray["rfc_generico"];
        if ($client_rfc != "") {
          if (isset($client_rfc) && isset($client_rfc) && preg_match($patronRfc, $client_rfc)) {
            $rfc_client = $JwtAuth->encriptar($client_rfc);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "error al registrar rfc del cliente"
            );
          }
        } else {
          $rfc_client = NULL;
        }
        //return response()->json(["message" => "prueba17","code" => 200,"status" => "error"]);
        if ($id_tax != "") {
          if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
            $idtax = $JwtAuth->encriptar($id_tax);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "error al registrar idtax del cliente"
            );
          }
        } else {
          $idtax = NULL;
        }

        $empresa_txt = NULL;
        $nombre_comercial_txt = NULL;
        $curp_txt = NULL;
        $pais_txt = NULL;
        $sitio_web_txt = NULL;
        $regimen_fiscal_txt = NULL;

        if (isset($radioClient) && !empty($radioClient) && preg_match($patron, $radioClient)) {
          if ($radioClient == "extranjero") {
            if (preg_match($patron, $subtipoClient)) {
              if ($subtipoClient == "clientMoral") {
                //return response()->json(["message' => $parametrosArray["pais"],"code" => 200,"status" => "error"]);
                if (
                  isset($name_cliente) && !empty($name_cliente) && preg_match($patron, $name_cliente) &&
                  isset($paistoken) && !empty($paistoken)
                ) {

                  //return response()->json(["message' => 'error',"code" => 200,"status" => "error"]);
                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (!preg_match($patron, $comercial_nombre)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "nomcom", "message" => "Error en nombre comercial de su cliente");
                    }
                    if (!preg_match($patronUrl, $sitio_web)) {
                      $dataMensaje = array('status' => 'error', 'code' => 200, 'codeProvGenError' => 'websitio', 'message' => 'Error en sitio web de su cliente');
                    }
                  }
                } else {
                  if (!isset($name_cliente) || empty($name_cliente) || !preg_match($patron, $name_cliente)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeProvGenError" => "nomemp",
                      "message" => "Error en nombre de empresa de su cliente"
                    );
                  }

                  if (!isset($paistoken) || empty($paistoken)) {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeProvGenError" => "pais",
                      "message" => "Error en pais de su cliente"
                    );
                  }
                }
              }
              //return response()->json(["message' => 'error',"code" => 200,"status" => "error"]);
              if ($subtipoClient == "clientFisica") {
                if (
                  isset($name_cliente) && !empty($name_cliente) && preg_match($patron, $name_cliente) &&
                  isset($paistoken) && !empty($paistoken)
                ) {
                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (!preg_match($patron, $comercial_nombre)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "nomcom", "message" => "Error en nombre comercial de su cliente");
                    }

                    if (!preg_match($patronUrl, $sitio_web)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "websitio", "message" => "Error en sitio web de su cliente");
                    }
                  }
                } else {
                  if (!isset($name_cliente) || empty($name_cliente) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_cliente)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'codeProvGenError' => 'nombrePF',
                      'message' => 'Error en nombre de su cliente'
                    );
                  }
                  if (!isset($paistoken) || empty($paistoken)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'codeProvGenError' => 'paisPF',
                      'message' => 'Error en pais de su cliente'
                    );
                  }
                }
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "codeClientGenError" => "clbint",
                "message" => "Seleccione subtipo de cliente (persona física o moral)"
              );
            }
          }

          if ($radioClient == "nacional") {
            if (preg_match($patron, $subtipoClient)) {
              if ($subtipoClient == "clientMoral") {
                if (isset($name_cliente) && !empty($name_cliente) && preg_match($patron, $name_cliente)) {
                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (!preg_match($patron, $comercial_nombre)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "nomcom", "message" => "Error en nombre comercial de su cliente");
                    }
                    if (!preg_match($patronUrl, $sitio_web)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "websitio", "message" => "Error en sitio web de su cliente");
                    }
                  }
                } else {
                  $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "nomemp", "message" => "Error en nombre de empresa de su cliente");
                }
              }

              if ($subtipoClient == "clientFisica") {
                if (isset($name_cliente) && !empty($name_client) && preg_match($JwtAuth->filtroAlfaNumerico(), $name_cliente)) {
                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($curp) && !empty($curp) && isset($sitio_web) && !empty($sitio_web)) {
                    if (!preg_match($patron, $comercial_nombre)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "nomcom", "message" => "Error en nombre comercial de su cliente");
                    }
                    if (!preg_match($patronRfc, $curp)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "clbint", "message" => "Error en curp de su cliente");
                    }
                    if (preg_match($patronUrl, $sitio_web)) {
                      $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "websitio", "message" => "Error en sitio web de su cliente");
                    }
                  }
                } else {
                  $dataMensaje = array("status" => "error", "code" => 200, "codeClientGenError" => "nombrePF", "message" => "Error en nombre de su cliente");
                }
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "codeClientGenError" => "clbint",
                "message" => "Seleccione subtipo de cliente (persona física o moral)"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "codeClientGenError" => "clbint",
            "message" => "Seleccione tipo de cliente (nacional o extranjero)"
          );
        }

        $contadorContacto = 0;
        //return response()->json(["message" => "prueba10","code" => 200,"status" => "error"]);
        if ($parametrosArray["decideinfocontacto"] == "true") {
          for ($i = 0; $i < count($listaContactoPersonal); $i++) {
            if (
              preg_match($patron, $listaContactoPersonal[$i]["paterno"]) &&
              preg_match($patron, $listaContactoPersonal[$i]["materno"]) &&
              preg_match($patron, $listaContactoPersonal[$i]["nombre"]) &&
              preg_match($patron, $listaContactoPersonal[$i]["area"]) &&
              preg_match($patron, $listaContactoPersonal[$i]["cargo"])
            ) {
              if (
                count($listaContactoPersonal[$i]["emails"]) == 0 &&
                count($listaContactoPersonal[$i]["telefonos"]) == 0
              ) {
                $contadorContacto++;
              } else {
                $contadorMails = 0;
                $personalEmails = $listaContactoPersonal[$i]["emails"];
                for ($m = 0; $m < count($personalEmails); $m++) {
                  if (preg_match($patronMail, $personalEmails[$m])) {
                    $contadorMails++;
                  } else {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "positionErrorCode" => $m,
                      "message" => "Error en correo electrónico de personal de contacto"
                    );
                    break;
                  }
                }

                $contadorTelefonos = 0;
                $personalTelefonos = $listaContactoPersonal[$i]["telefonos"];
                for ($t = 0; $t < count($personalTelefonos); $t++) {
                  if (
                    preg_match($patron, $personalTelefonos[$t]["etiqueta"]) &&
                    preg_match($patronNum, $personalTelefonos[$t]["telefono_complete"]) &&
                    preg_match($patronCpostal, $personalTelefonos[$t]["extension"])
                  ) {
                    $contadorTelefonos++;
                  } else {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "positionErrorCode" => $m,
                      "message" => "Error en teléfono de personal de contacto"
                    );
                    break;
                  }
                }

                if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
                  $contadorContacto++;
                }
              }
            } else {
              if (!preg_match($patron, $listaContactoPersonal[$i]["paterno"])) {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "positionErrorCode" => $i,
                  "message" => "Error en apellido paterno de personal de contacto"
                );
              }
              if (!preg_match($patron, $listaContactoPersonal[$i]["materno"])) {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "positionErrorCode" => $i,
                  "message" => "Error en apellido materno de personal de contacto"
                );
              }
              if (!preg_match($patron, $listaContactoPersonal[$i]["nombre"])) {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "positionErrorCode" => $i,
                  "message" => "Error en nombre de personal de contacto"
                );
              }
              if (!preg_match($patron, $listaContactoPersonal[$i]["area"])) {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "positionErrorCode" => $i,
                  "message" => "Error en area de trabajo de personal de contacto"
                );
              }
              if (!preg_match($patron, $listaContactoPersonal[$i]["cargo"])) {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "positionErrorCode" => $i,
                  "message" => "Error en cargo de trabajo de personal de contacto"
                );
              }
            }
          }
        }
        //return response()->json(["message" => "prueba11","code" => 200,"status" => "error"]);
        if (preg_match($patron, $creditoAsignado)) {
          if ($creditoAsignado == true) {
            $mensajeError = "";
            if (!isset($data_moneda_code)) {
              $mensajeError = "Error en seleccion de moneda (Código)";
            }
            if (!isset($data_moneda_decimales)) {
              $mensajeError = "Error en seleccion de moneda (Decimales)";
            }
            if (!preg_match($patronNumCred, $parametrosArray["txtlimiteCredito"])) {
              $mensajeError = "Error en limite de credito";
            }
            if (!preg_match($patronNum, $parametrosArray["txtdiaspagoCredit"])) {
              $mensajeError = "Error en seleccion de dias de pago";
            }
            if (!preg_match($patron, $parametrosArray["comienzaPago"])) {
              $mensajeError = "Error en seleccion de comienzo de pago";
            }
            $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensajeError);
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Selecciona opcion de aceptacion de creditos"
          );
        }

        //return response()->json(["message" => "prueba14","code" => 200,"status" => "error"]);
        $listaClientes = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
          ->join("teci_pais AS ps", "client.nacionalidad", "ps.id")
          ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'ingr_catalogo_clientes.status' => true
          ])->get();
        //return response()->json(["message" => "prueba20","code" => 200,"status" => "error"]);

        $countVerifica = 0;
        $invalidName = "";

        foreach ($listaClientes as $vListCli) {
          $nameClient_f = strtolower($JwtAuth->desencriptar($vListCli->nombre_extendido));
          $rfc_generico_f = strtolower($vListCli->rfc_generico);
          //return response()->json(["message' => $nombre_txt,"code" => 200,"status" => "error"]);
          if ($rfc_client != NULL) {
            if ($vListCli->rfc == $rfc_client) {
              ++$countVerifica;
              $invalidName = $nameClient_f;
            }
          } else if ($idtax != "") {
            if ($vListCli->tax_id == $idtax) {
              ++$countVerifica;
              $invalidName = $nameClient_f;
            }
          } else if ($vListCli->rfc_generico == $rfc_generico && $nameClient_f == $name_cliente) {
            ++$countVerifica;
            $invalidName = $nameClient_f;
          } else {
            if ($nameClient_f == $name_cliente) {
              ++$countVerifica;
              $invalidName = $nameClient_f;
            }
          }
        }
        //return response()->json(["message' => 'prueba 3',"code" => 200,"status" => "error"]);
        //if ($countVerifica == 0) {
        $countVerif = true;
        if ($countVerif == true) {
          $tknPClient = $JwtAuth->encriptarToken(
            $fechaAlta,
            $name_cliente,
            $comercial_nombre,
            $sitio_web,
            $pais_txt,
            $rfc_client
          );

          $pais_txt = NULL;
          if ($radioClient == "extranjero") {
            $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
            $pais_txt = $selectPais[0]->id;
          } else if ($radioClient == "nacional") {
            $pais_txt = "118";
          }


          $insertClient = DB::table("sos_personas")
            ->insert(array(
              "token_personas" => $tknPClient,
              "nombre_extendido" => $JwtAuth->encriptar($name_cliente),
              "nombre_com" => $comercial_nombre != "" ? $JwtAuth->encriptar($comercial_nombre) : NULL,
              "sitio_web" => $sitio_web != "" ? $JwtAuth->encriptar($sitio_web) : NULL,
              "nacionalidad" => $pais_txt,
              "rfc_generico" => $rfc_generico,
              "rfc" => $rfc_client,
              "tax_id" => $idtax,
              "curp" => $curp != "" ? $JwtAuth->encriptar($curp) : NULL,
            ));
          //return response()->json(["message" => "prueba21","code" => 200,"status" => "error"]);
          if ($insertClient) {
            $identClient = DB::table("sos_personas")->where("token_personas", $tknPClient)->value("id");
            $selectFCobro = DB::table("teci_forma_pago")->where("token_formapago", $formaCobroAltaClient)->value("id");
            $formaCobroAsignada =  $decideformaCobro == true ? $selectFCobro : NULL;
            $selectRegFisc = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $tknRegimenFiscal)->value("id");
            $tokenClient = $JwtAuth->encriptarToken($formaCobroAltaClient, $folio_client . $identClient . $formaCobroAsignada . $selectEmp[0]->id);

            $tiene_docs_fiscales = $parametrosArray["tiene_docs_fiscales"] == true ? TRUE : FALSE;
            $constsitfiscal = file_exists($request->file("docSitFiscal")) ? $JwtAuth->encriptar($folio_client . "-" . $fechaAlta . '-' . $request->file("docSitFiscal")->getClientOriginalName()) : "";
            $cumplimiento = file_exists($request->file("docCumpObFisc")) ? $JwtAuth->encriptar($folio_client . "-" . $fechaAlta . '-' . $request->file("docCumpObFisc")->getClientOriginalName()) : "";
            $valnoCargaDocsFiscalesRazon = $parametrosArray["valnoCargaDocsFiscalesRazon"] != "" ? $JwtAuth->encriptar($parametrosArray["valnoCargaDocsFiscalesRazon"]) : NULL;

            //return response()->json(["message" => "prueba22","code" => 200,"status" => "error"]);
            $creaKliente = new ClientesModelo();
            $creaKliente->token_cat_clientes = $tokenClient;
            $creaKliente->folio = $folio_nuevo;
            $creaKliente->post_folio = $post_folio;
            $creaKliente->temp_folio = NULL;
            $creaKliente->authorized = TRUE;
            $creaKliente->fechaAlta = $fechaAlta;
            $creaKliente->cliente = $identClient;
            $creaKliente->subClase = $subtipoClient == "clientMoral" ? "PM" : "PF";
            $creaKliente->lista_precios = NULL;
            $creaKliente->tiene_docs_fiscales = $tiene_docs_fiscales;
            $creaKliente->const_sit_fiscal = $constsitfiscal;
            $creaKliente->opinion_cumplimiento = $cumplimiento;
            $creaKliente->no_cuenta_fiscales = $valnoCargaDocsFiscalesRazon;
            $creaKliente->regimen_fiscal = $selectRegFisc;
            $creaKliente->forma_cobro = $formaCobroAsignada;
            $creaKliente->emisionFacturas = $emisionFacturas;
            $creaKliente->envioArtAfterCobro = $envioArtAfterCobro;
            $creaKliente->status = TRUE;
            $creaKliente->fecha_delete_client = "";
            $creaKliente->administrador = $selectEmp[0]->id;
            $saveClient = $creaKliente->save();

            if ($saveClient) {
              $selectClientCat = DB::select("SELECT id FROM ingr_catalogo_clientes WHERE token_cat_clientes = ?", [$tokenClient]);
              $contadorInsertUbicaciones = 0;

              if ($radioClient == 'extranjero') {
                $tipo_direccion = 'dirección fiscal';
                $cpostalDir = $JwtAuth->encriptar($cod_postal);
                $clasificacionDir = $JwtAuth->encriptar("matriz");
                $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $cpostalDir, $clasificacionDir);

                $fisinsertDir = DB::table("teci_direcciones")
                  ->insert(array(
                    "token_direccion" => $tokenCDir,
                    "tipo_direccion" => $tipo_direccion,
                    "clase" => $clasificacionDir,
                    "cod_postalext" => $cpostalDir,
                    "pais" => $pais_txt,
                    "cliente" => $selectClientCat[0]->id,
                    "status" => TRUE,
                    "administrador" => $selectEmp[0]->id,
                  ));
                if ($fisinsertDir) {
                  $contadorInsertUbicaciones++;
                }
              } else {
                if (count($listnewdireccionNac) != 0) {
                  for ($nd = 0; $nd < count($listnewdireccionNac); $nd++) {
                    $listnew_estado = $listnewdireccionNac[$nd]["estado"];
                    $listnew_municipio = $listnewdireccionNac[$nd]["municipio"];
                    $listnew_codigo_postal = $listnewdireccionNac[$nd]["codigo_postal"];
                    $listnew_colonia = $listnewdireccionNac[$nd]["colonia"];

                    $tipo_direccion = "dirección fiscal";
                    $clasificacionDir = $JwtAuth->encriptar("matriz");
                    $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $listnew_estado, $listnew_municipio, $listnew_codigo_postal, $listnew_colonia, $clasificacionDir);
                    $fisinsertDir = DB::table("teci_direcciones")
                      ->insert(array(
                        "token_direccion" => $tokenCDir,
                        "tipo_direccion" => $tipo_direccion,
                        "clase" => $clasificacionDir,
                        "pais" => 118,
                        "estado_edit" => $JwtAuth->encriptar($listnew_estado),
                        "municipio_edit" => $JwtAuth->encriptar($listnew_municipio),
                        "c_postal_edit" => $listnew_codigo_postal,
                        "colonia_edit" => $JwtAuth->encriptar($listnew_colonia),
                        "adicional" => "no_api_found",
                        "cliente" => $selectClientCat[0]->id,
                        "status" => TRUE,
                        "administrador" => $selectEmp[0]->id,
                      ));
                  }
                } else {
                  $tipo_direccion = "dirección fiscal";
                  $clasificacionDir = $JwtAuth->encriptar("matriz");
                  $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
                  $listnewdireccionNac = $parametrosArray["listnewdireccionNac"];
                  $fisinsertDir = DB::table("teci_direcciones")
                    ->insert(array(
                      "token_direccion" => $tokenCDir,
                      "tipo_direccion" => $tipo_direccion,
                      "clase" => $clasificacionDir,
                      "pais" => 118,
                      "estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
                      "municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
                      "c_postal_edit" => $dipomex_cod_postal_cp,
                      "colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
                      "adicional" => "api",
                      "cliente" => $selectClientCat[0]->id,
                      "status" => TRUE,
                      "administrador" => $selectEmp[0]->id,
                    ));
                }

                if ($fisinsertDir) {
                  $contadorInsertUbicaciones++;
                }
              }

              $contadorInsertContacto = 0;
              //return response()->json(["message" => "prueba24","code" => 200,"status" => "error"]);
              if ($decideinfocontacto == true) {
                for ($i = 0; $i < count($listaContactoPersonal); $i++) {
                  $contArea = $JwtAuth->encriptar($listaContactoPersonal[$i]['area']); //area
                  $contCargo = $JwtAuth->encriptar($listaContactoPersonal[$i]['cargo']);
                  $contApePaterno = $JwtAuth->encriptar($listaContactoPersonal[$i]['paterno']);
                  $contApeMaterno = $JwtAuth->encriptar($listaContactoPersonal[$i]['materno']);
                  $contNombre = $JwtAuth->encriptar($listaContactoPersonal[$i]['nombre']);

                  $tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' . $contApePaterno . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);
                  $insertapersonalpersonas = DB::table('sos_personas')
                    ->insert(array("token_personas" => $tokenPersonasPersonal, "paterno" => $contApePaterno, "materno" => $contApeMaterno, "nombre" => $contNombre));

                  $selectpersonalpersonas = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$tokenPersonasPersonal]);
                  $tokenPersonal = $JwtAuth->encriptarToken($contApePaterno . '/' . $contNombre . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);
                  $insertapersonal = DB::table('in_egr_contacto_cliente_proveedor')->insert(
                    array(
                      "token_contacto" => $tokenPersonal,
                      "fecha_alta_contacto" => time(),
                      "folio_contacto" => $i,
                      "area_contacto" => $contArea,
                      "cargo_contacto" => $contCargo,
                      "nombre" => $selectpersonalpersonas[0]->id,
                      "cat_clientes" => $selectClientCat[0]->id,
                      "status" => TRUE,
                      "fecha_delete" => NULL
                    )
                  );
                  $selectContacto = DB::select("SELECT id FROM in_egr_contacto_cliente_proveedor WHERE token_contacto = ?", [$tokenPersonal]);
                  $cont_list_phone = 0;
                  $personalTelefonos = $listaContactoPersonal[$i]['telefonos'];
                  if (count($personalTelefonos) != 0) {
                    for ($t = 0; $t < count($personalTelefonos); $t++) {
                      $contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono_complete']);
                      $contExtension = $personalTelefonos[$t]['extension'] != '' ? $JwtAuth->encriptar($personalTelefonos[$t]['extension']) : NULL;
                      $tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);
                      $principal = $t == 0 ? TRUE : FALSE;
                      //return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
                      $insertasos_personas_telefonos = DB::table('sos_personas_telefonos')
                        ->insert(array(
                          "token_telefono" => $tokentel,
                          "contacto_cliente_prov" => $selectContacto[0]->id,
                          //"icono" => $personalTelefonos[$t]["icon"],	
                          "etiqueta" => $personalTelefonos[$t]["etiqueta"],
                          "cod_pais" => 52,
                          "telefono" => $contTelefono,
                          "principal" => $principal,
                          "extension" => $contExtension,
                          "status_telefono" => TRUE,
                          "fecha_delete_tel" => NULL,
                        ));
                      ++$cont_list_phone;
                    }
                  }

                  $personalEmails = $listaContactoPersonal[$i]['emails'];
                  $cont_list_mails = 0;
                  if (count($personalEmails) != 0) {
                    for ($m = 0; $m < count($personalEmails); $m++) {
                      $contEmail = $JwtAuth->encriptar($personalEmails[$m]);
                      $tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectContacto[0]->id);
                      $insertasos_personas_correos = DB::table('sos_personas_correos')
                        ->insert(array(
                          "token_correo" => $tokenEmail,
                          "contacto_cliente_prov" => $selectContacto[0]->id,
                          "correo" => $contEmail,
                          "status_correo" => TRUE,
                          "fecha_delete_correo" => NULL,
                        ));
                      ++$cont_list_mails;
                    }
                  }

                  if ($cont_list_phone > 0 || $cont_list_mails > 0) {
                    ++$contadorInsertContacto;
                  }
                }
              }

              $tokenCreditos = $JwtAuth->encriptarToken($selectClientCat[0]->id . $fechaAlta . $creditoAsignado);
              $cred_registrado = "";
              if ($creditoAsignado == true) {
                $insertaCredito = DB::table("in_egr_creditos")
                  ->insert(array(
                    "token_creditos" => $tokenCreditos,
                    "cliente" => $selectClientCat[0]->id,
                    "aceptacredito" => TRUE,
                    "moneda_code" => $data_moneda_code,
                    "moneda_decimales" => $data_moneda_decimales,
                    "limite" => $txtlimiteCredito,
                    "dias" => $txtdiasCobroCredit,
                    "comienza" => $selectComienzaCobroClient,
                  ));
                $cred_registrado = "reg_true";
              } else {
                $cred_registrado = "reg_false";
              }

              //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
              if ($contadorInsertUbicaciones > 0 && $contadorInsertContacto == count($listaContactoPersonal) && ($cred_registrado == "reg_true" || $cred_registrado == "reg_false")) {
                //return response()->json(["message" => "prueba26","code" => 200,"status" => "error"]);
                $filepath = $selectEmp[0]->root_tkn . "/0001-cpc/catalogos/clientes/" . $folio_client . "-" . $fechaAlta . "/";
                //return response()->json(["message" => "prueba27","code" => 200,"status" => "error"]);
                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
                }
                if (file_exists($request->file('imagenAltaPdfFiscal'))) {
                  $namesitfiscal = $fechaAlta . '-' . $request->file('imagenAltaPdfFiscal')->getClientOriginalName();
                  $typesitfiscal = $JwtAuth->getExtensionDoc($request->file('imagenAltaPdfFiscal')->getClientMimeType());
                  $tkn_doc_sitfiscal = $JwtAuth->encriptarToken($tokenClient, $usuario->user_token, $usuario->empresa_token, $namesitfiscal);
                  $JwtAuth->registraDocsCliente($tokenClient, $tkn_doc_sitfiscal, "fcsf", $namesitfiscal, $typesitfiscal);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfFiscal'), $namesitfiscal);
                }

                if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
                  $namecumplimiento = $fechaAlta . '-' . $request->file('imagenAltaPdfCumplimientoObFiscales')->getClientOriginalName();
                  $typecumplimiento = $JwtAuth->getExtensionDoc($request->file('imagenAltaPdfCumplimientoObFiscales')->getClientMimeType());
                  $tkn_doc_cumplimiento = $JwtAuth->encriptarToken($tokenClient, $usuario->user_token, $usuario->empresa_token, $namecumplimiento);
                  $JwtAuth->registraDocsCliente($tokenClient, $tkn_doc_cumplimiento, "cuof", $namecumplimiento, $typecumplimiento);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfCumplimientoObFiscales'), $namecumplimiento);
                }

                if (file_exists($request->file('imagenAltaContratos'))) {
                  $namecontrato = $fechaAlta . '-' . $request->file('imagenAltaContratos')->getClientOriginalName();
                  $typecontrato = $JwtAuth->getExtensionDoc($request->file('imagenAltaContratos')->getClientMimeType());
                  $tkn_doc_contrato = $JwtAuth->encriptarToken($tokenClient, $usuario->user_token, $usuario->empresa_token, $namecontrato);
                  $JwtAuth->registraDocsCliente($tokenClient, $tkn_doc_contrato, "fcnt", $namecontrato, $typecontrato);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaContratos'), $namecontrato);
                }

                if (isset($_FILES['files_anexos']) && !empty($_FILES['files_anexos'])) {
                  $anexo_archivos = $_FILES["files_anexos"];
                  $anexo_archivos_strings = json_decode(json_encode($_FILES["files_anexos"]["name"]));
                  if (count($anexo_archivos_strings) > 0) {
                    for ($i = 0; $i < count($anexo_archivos_strings); $i++) {
                      $docAnexo_temporal = $anexo_archivos["tmp_name"][$i];
                      $docAnexo_name = "anexos/" . $anexo_archivos["name"][$i];
                      $docAnexo_type = $JwtAuth->getExtensionDoc($anexo_archivos["type"][$i]);
                      $docAnexo_tknn = $JwtAuth->encriptarToken($tokenClient, $usuario->user_token, $usuario->empresa_token, $docAnexo_name);
                      $JwtAuth->registraDocsCliente($tokenClient, $docAnexo_tknn, "anex", $docAnexo_name, $docAnexo_type);
                      Storage::putFileAs("/public/root/" . $filepath, $docAnexo_temporal, $docAnexo_name);
                    }
                  }
                }
                //return response()->json(["message" => "prueba31","code" => 200,"status" => "error"]);
                QRCode::text($tokenClient)->setOutfile(
                  Storage::path("public/root/" . $filepath . $folio_client . " - " . $fechaAlta . "-QRCode.png")
                )->png();
                //$cumplimiento;
                //$formaPagoClabeInterbank;
                //return response()->json(["message" => "prueba32","code" => 200,"status" => "error"]);
                $JwtAuth->insertBitacoraActividad(
                  "egresos",
                  "catalogos",
                  "clientes",
                  $folio_client,
                  "registro en el catalogo de clientes",
                  $usuario->empresa_token,
                  $usuario->user_token
                );
                //return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);
                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table("sos_last_folders")
                    ->insert(array(
                      "ing_clientes" => TRUE,
                      "folder" => 1,
                      "post_folder" => $post_folio,
                      "empresa" => $selectEmp[0]->id,
                    ));
                } else {
                  $regFolder = DB::table("sos_last_folders AS last")
                    ->join("main_empresas AS emp", "last.empresa", "=", "emp.id")
                    ->where([
                      'last.ing_clientes' => TRUE,
                      'emp.empresa_token' => $usuario->empresa_token,
                    ])
                    ->limit(1)->update(
                      array(
                        'last.folder' => $folio_nuevo,
                        'last.post_folder' => $post_folio,
                      )
                    );
                }
                //return response()->json(["message" => "prueba34","code" => 200,"status" => "error"]);
                $dataMensaje = array(
                  "status" => 'success',
                  "code" => 200,
                  "message" => "Cliente registrado satisfactoriamente con el folio " . $folio_client
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Datos de personal de contacto/direcciones/creditos de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
                );
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Datos generales de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
              );
            }
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Datos generales de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "ya existe un cliente con esta información"
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

  public function clientePublicoGeneralRegistro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonServ = $request->input("json");
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    //return response()->json(["message" => "prueba1","code" => 200,"status" => "error"]);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "abrev_cliente" => "required|string",
        "nombre_cliente" => "required|string",
        "destino_cliente" => "required|string",
        "vinculo_publico_general" => "required|boolean", //is_bool()
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
        $fechaAlta = time();
        $abrev_cliente = $parametrosArray["abrev_cliente"];
        $nombre_cliente = $parametrosArray["nombre_cliente"];
        $destino_cliente = $parametrosArray["destino_cliente"];
        $vinculo_publico_general = $parametrosArray["vinculo_publico_general"];
        //validaciones
        if (
          isset($abrev_cliente) && !empty($abrev_cliente) && preg_match($JwtAuth->filtroAlfaNumerico(), $abrev_cliente) &&
          isset($nombre_cliente) && !empty($nombre_cliente) && preg_match($JwtAuth->filtroAlfaNumerico(), $nombre_cliente) &&
          isset($destino_cliente) && !empty($destino_cliente) && preg_match($JwtAuth->filtroAlfaNumerico(), $destino_cliente) &&
          isset($vinculo_publico_general) && is_bool($vinculo_publico_general)
        ) {

          $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,
                        people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                        JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                        WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          $folioSistemaTemp = DB::select("SELECT temp_folio FROM ingr_catalogo_clientes WHERE temps_folio IS NOT NULL 
                        AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
          if (count($folioSistemaTemp) > 0) {
            $queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temps_folio FROM ingr_catalogo_clientes 
                            WHERE id = (SELECT Max(catprod.id) FROM ingr_catalogo_clientes AS catclient 
                            JOIN main_empresas AS emp WHERE temps_folio IS NOT NULL AND catclient.administrador = emp.id 
                            AND emp.empresa_token = ?)", [$usuario->empresa_token]);

            foreach ($queryFolioTmpPrv as $vTemp) {
              $folio_temporal = $vTemp->temp_folio;
            }
          } else {
            $folio_temporal = 1;
          }

          //return response()->json(["message" => "prueba8","code" => 200,"status" => "error"]);
          $folio_client = "CLI-TEMP-" . $JwtAuth->generarFolio($folio_temporal);

          $rfc_generico = "XAXX010101000";

          $listaClientes = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->where(['emp.empresa_token' => $usuario->empresa_token, 'ingr_catalogo_clientes.status' => true])->get();

          if (count($listaClientes) == 0) {
            $clienteNameTkn = $JwtAuth->encriptarToken($abrev_cliente, $nombre_cliente, $rfc_generico);

            $insertClient = DB::table("sos_personas")
              ->insert(array("token_personas" => $clienteNameTkn, "abrev_nombre" => $abrev_cliente, "nombre_extendido" => $JwtAuth->encriptar($nombre_cliente), "rfc_generico" => $rfc_generico));

            //$abrev_cliente
            //$nombre_cliente
            //$vinculo_publico_general

            if ($insertClient) {
              $selectClient = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$clienteNameTkn]);
              $creaKliente = new ClientesModelo();
              $creaKliente->token_cat_clientes = $JwtAuth->encriptarToken($folio_client . $abrev_cliente . $nombre_cliente . $selectClient[0]->id . $selectEmp[0]->id);
              $creaKliente->fechaAlta = $fechaAlta;
              $creaKliente->temp_folio = $folio_temporal;
              $creaKliente->authorized = false;
              $creaKliente->cliente = $selectClient[0]->id;
              $creaKliente->destino_cliente = $JwtAuth->encriptar($destino_cliente);
              $creaKliente->publico_general = $vinculo_publico_general == true ? TRUE : FALSE;
              $creaKliente->status = TRUE;
              $creaKliente->administrador = $selectEmp[0]->id;
              $saveClient = $creaKliente->save();

              if ($saveClient) {
                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table("sos_last_folders")
                    ->insert(array(
                      "ing_clientes" => TRUE,
                      "folder" => 1,
                      "post_folder" => $post_folio,
                      "empresa" => $selectEmp[0]->id,
                    ));
                } else {
                  $regFolder = DB::table("sos_last_folders AS fold")
                    ->join("main_empresas AS emp", "fold.empresa", "=", "emp.id")
                    ->where([
                      'fold.ing_clientes' => TRUE,
                      'emp.empresa_token' => $usuario->empresa_token,
                    ])
                    ->limit(1)->update(
                      array(
                        'fold.folder' => $folio_nuevo,
                        'fold.post_folder' => $post_folio,
                      )
                    );
                }
                //return response()->json(["message" => "prueba34","code" => 200,"status" => "error"]);
                $dataMensaje = array(
                  "status" => 'success',
                  "code" => 200,
                  "message" => "Cliente registrado satisfactoriamente con el folio " . $folio_client
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Datos generales de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
                );
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Datos generales de este cliente no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
              );
            }
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "ya existe un cliente con esta información"
            );
          }
        } else {
          $mensaje_error = "";
          if (!isset($abrev_cliente) || empty($abrev_cliente) || !preg_match($JwtAuth->filtroAlfaNumerico(), $abrev_cliente)) $mensaje_error = "Error en abreviacion de nombre de cliente, por favor verifique su información o comuniquese a soporte";
          if (!isset($nombre_cliente) || empty($nombre_cliente) || !preg_match($JwtAuth->filtroAlfaNumerico(), $nombre_cliente)) $mensaje_error = "Error en nombre de cliente, por favor verifique su información o comuniquese a soporte";
          if (!isset($destino_cliente) || empty($destino_cliente) || !preg_match($JwtAuth->filtroAlfaNumerico(), $destino_cliente)) $mensaje_error = "Error en destino de cliente, por favor verifique su información o comuniquese a soporte";
          if (!isset($vinculo_publico_general) || !is_bool($vinculo_publico_general)) $mensaje_error = "Error al decidir si el cliente a registrar sera tomado como cliente publico general, por favor verifique su información o comuniquese a soporte";
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
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
}