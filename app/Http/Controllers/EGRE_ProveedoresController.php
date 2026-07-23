<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\DireccionesModelo;
use App\Models\ProveedoresModelo;
use App\Models\FormaPagoModelo;
use App\Models\MonedasModelo;
use App\Models\CuentasContablesModelo;
use App\Models\User;
use PDF;
use QRCode;

class EGRE_ProveedoresController extends Controller{
  public function proveedoresCatGeneral(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(), [
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
          $fechaInicio = strtotime(date($lunes . ' 00:00:00'));
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

      $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
        ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "catprov.status" => TRUE,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("catprov.fechaAlta", [$fechaInicio, $fechaFin]);
        })
        ->get();

      if ($listaProveedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron proveedores registrados'
        );
      } else {
        $arrayProveedores = array();
        foreach ($listaProveedores as $resListProv) {
          $autorizado = $resListProv->authorized == TRUE ? true : false;
          $auth_fecha = $resListProv->authorized == TRUE ? gmdate('Y-m-d H:i:s', $resListProv->authorized_fecha) : null;
          $utilizado = $resListProv->utilizado == TRUE ? true : false;

          $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

          $rfc_generico = $resListProv->rfc_generico;
          $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
          $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

          if ($resListProv->folio != NULL && $resListProv->folio != "") {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
            if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
          } else {
            $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
          }

          $lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

          $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
            ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
            ->where(["catprv.token_cat_proveedores" => $resListProv->token_cat_proveedores])->value("cred.aceptacredito");

          $arrayForeach = array(
            "token_cat_proveedores" => $resListProv->token_cat_proveedores,
            "folio" => $folio_prov,
            "pais" => $resListProv->pais,
            "rfc_generico" => $rfc_generico,
            "rfc_prov" => $rfc_prov,
            "tax_id_prov" => $tax_id_prov,
            "nombre" => $nombreProv,
            "nombre_comercial" => !is_null($resListProv->nombre_com) ? $JwtAuth->desencriptar($resListProv->nombre_com) : '',
            "listaPrecios" => $lista_precios,
            "tiene_clave" => "",
            "encendido" => false,
            "utilizado" => $utilizado,
            "autorizado" => $autorizado,
            "autorizado_translate" => $resListProv->authorized ? 'yes_auth' : 'not_auth',
            "auth_fecha" => $auth_fecha,
            "receptFactura" => $resListProv->receptFactura ? true : false,
            "aceptacredito" => $credProveedor ? true : false,
            "cuenta_contable" => !empty($resListProv->cuenta_contable) ? $JwtAuth->desencriptar($resListProv->cuenta_contable) : '',
            "infoCollapsed" => true,
            "comprasCollapsed" => true,
            "data_detalle_vista" => false,
            "data_detalle" => [],
            "data_para_compras" => [],
            "data_anticipos" => [],
          );
          $arrayProveedores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'total_lista' => count($listaProveedores),
          'proveedores' => $arrayProveedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function proveedoresCatMx(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(), [
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
          $fechaInicio = strtotime(date($lunes . ' 00:00:00'));
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

      $queryProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
        ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "prov.nacionalidad" => 118,
          "catprov.status" => TRUE,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("catprov.fechaAlta", [$fechaInicio, $fechaFin]);
        })
        ->get();

      if ($queryProveedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron proveedores registrados'
        );
      } else {
        $listaProveedores = array();
        foreach ($queryProveedores as $resListProv) {
          $autorizado = $resListProv->authorized == TRUE ? true : false;
          $auth_fecha = $resListProv->authorized == TRUE ? gmdate('Y-m-d H:i:s', $resListProv->authorized_fecha) : null;
          $utilizado = $resListProv->utilizado == TRUE ? true : false;

          $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

          $rfc_generico = $resListProv->rfc_generico;
          $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
          $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

          if ($resListProv->folio != NULL && $resListProv->folio != "") {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
            if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
          } else {
            $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
          }

          $lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

          $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
            ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
            ->where(["catprv.token_cat_proveedores" => $resListProv->token_cat_proveedores])->value("cred.aceptacredito");

          $arrayForeach = array(
            "token_cat_proveedores" => $resListProv->token_cat_proveedores,
            "folio" => $folio_prov,
            "pais" => $resListProv->pais,
            "rfc_generico" => $rfc_generico,
            "rfc_prov" => $rfc_prov,
            "tax_id_prov" => $tax_id_prov,
            "nombre" => $nombreProv,
            "nombre_comercial" => !is_null($resListProv->nombre_com) ? $JwtAuth->desencriptar($resListProv->nombre_com) : '',
            "listaPrecios" => $lista_precios,
            "tiene_clave" => "",
            "encendido" => false,
            "utilizado" => $utilizado,
            "autorizado" => $autorizado,
            "auth_fecha" => $auth_fecha,
            "receptFactura" => $resListProv->receptFactura ? true : false,
            "aceptacredito" => $credProveedor ? true : false,
            "cuenta_contable" => !empty($resListProv->cuenta_contable) ? $JwtAuth->desencriptar($resListProv->cuenta_contable) : '',
            "infoCollapsed" => true,
            "comprasCollapsed" => true,
            "data_detalle_vista" => false,
            "data_detalle" => [],
            "data_para_compras" => [],
            "data_anticipos" => [],
          );
          $listaProveedores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'proveedores' => $listaProveedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function proveedoresCatExtranjeros(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(), [
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
          $fechaInicio = strtotime(date($lunes . ' 00:00:00'));
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

      $queryProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
        ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereNot("prov.nacionalidad", 118)
        ->where([
          "catprov.status" => TRUE,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("catprov.fechaAlta", [$fechaInicio, $fechaFin]);
        })
        ->get();

      if ($queryProveedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron proveedores registrados'
        );
      } else {
        $listaProveedores = array();
        foreach ($queryProveedores as $resListProv) {
          $autorizado = $resListProv->authorized == TRUE ? true : false;
          $auth_fecha = $resListProv->authorized == TRUE ? gmdate('Y-m-d H:i:s', $resListProv->authorized_fecha) : null;
          $utilizado = $resListProv->utilizado == TRUE ? true : false;

          $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

          $rfc_generico = $resListProv->rfc_generico;
          $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
          $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

          if ($resListProv->folio != NULL && $resListProv->folio != "") {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
            if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
          } else {
            $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
          }

          $lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

          $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
            ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
            ->where(["catprv.token_cat_proveedores" => $resListProv->token_cat_proveedores])->value("cred.aceptacredito");

          $arrayForeach = array(
            "token_cat_proveedores" => $resListProv->token_cat_proveedores,
            "folio" => $folio_prov,
            "pais" => $resListProv->pais,
            "rfc_generico" => $rfc_generico,
            "rfc_prov" => $rfc_prov,
            "tax_id_prov" => $tax_id_prov,
            "nombre" => $nombreProv,
            "nombre_comercial" => !is_null($resListProv->nombre_com) ? $JwtAuth->desencriptar($resListProv->nombre_com) : '',
            "listaPrecios" => $lista_precios,
            "tiene_clave" => "",
            "encendido" => false,
            "utilizado" => $utilizado,
            "autorizado" => $autorizado,
            "auth_fecha" => $auth_fecha,
            "receptFactura" => $resListProv->receptFactura ? true : false,
            "aceptacredito" => $credProveedor ? true : false,
            "cuenta_contable" => !empty($resListProv->cuenta_contable) ? $JwtAuth->desencriptar($resListProv->cuenta_contable) : '',
            "infoCollapsed" => true,
            "comprasCollapsed" => true,
            "data_detalle_vista" => false,
            "data_detalle" => [],
            "data_para_compras" => [],
            "data_anticipos" => [],
          );
          $listaProveedores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'proveedores' => $listaProveedores
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function proveedoresCatPersonasFisicas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(), [
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
          $fechaInicio = strtotime(date($lunes . ' 00:00:00'));
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

      $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
      ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
      ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
      ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catprov.subClase" => "PF",
        "catprov.status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catprov.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($listaProveedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron proveedores registrados'
        );
      } else {
        $arrayProveedores = array();
        foreach ($listaProveedores as $resListProv) {
          $autorizado = $resListProv->authorized == TRUE ? true : false;
          $auth_fecha = $resListProv->authorized == TRUE ? gmdate('Y-m-d H:i:s', $resListProv->authorized_fecha) : null;
          $utilizado = $resListProv->utilizado == TRUE ? true : false;

          $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

          $rfc_generico = $resListProv->rfc_generico;
          $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
          $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

          if ($resListProv->folio != NULL && $resListProv->folio != "") {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
            if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
          } else {
            $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
          }

          $lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

          $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
            ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
            ->where(["catprv.token_cat_proveedores" => $resListProv->token_cat_proveedores])->value("cred.aceptacredito");

          $vRegf = DB::table("sos_regimen_fiscal AS regf")
          ->join("eegr_catalogo_proveedores AS catprv", "regf.id", "=", "catprv.regimen_fiscal")
          ->select("regf.token_regimen_fiscal","regf.clave","regf.descripcion")
          ->first();
          
          $regimen_fiscal_token = $vRegf ? $vRegf->token_regimen_fiscal: '';
          $regimen_fiscal_descripcion = $vRegf ? $vRegf->clave . "-" . $vRegf->descripcion: '';

          $arrayForeach = array(
            "token_cat_proveedores" => $resListProv->token_cat_proveedores,
            "folio" => $folio_prov,
            "pais" => $resListProv->pais,
            "rfc_generico" => $rfc_generico,
            "rfc_prov" => $rfc_prov,
            "tax_id_prov" => $tax_id_prov,
            "nombre" => $nombreProv,
            "nombre_comercial" => !is_null($resListProv->nombre_com) ? $JwtAuth->desencriptar($resListProv->nombre_com) : '',
            "regimen_fiscal_token" => $regimen_fiscal_token,
            "regimen_fiscal_descripcion" => $regimen_fiscal_descripcion,
            "listaPrecios" => $lista_precios,
            "tiene_clave" => "",
            "encendido" => false,
            "utilizado" => $utilizado,
            "autorizado" => $autorizado,
            "autorizado_translate" => $resListProv->authorized ? 'yes_auth' : 'not_auth',
            "auth_fecha" => $auth_fecha,
            "receptFactura" => $resListProv->receptFactura ? true : false,
            "aceptacredito" => $credProveedor ? true : false,
            "cuenta_contable" => !empty($resListProv->cuenta_contable) ? $JwtAuth->desencriptar($resListProv->cuenta_contable) : '',
            "infoCollapsed" => true,
            "comprasCollapsed" => true,
            "data_detalle_vista" => false,
            "data_detalle" => [],
            "data_para_compras" => [],
            "data_anticipos" => [],
          );
          $arrayProveedores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'total_lista' => count($listaProveedores),
          'proveedores' => $arrayProveedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function proveedoresBitacora(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "emp.empresa_token" => $empresa, 
      "users.usuario_token" => $usuario, 
      "catprov.status" => TRUE
    ])->get();

    if ($listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron proveedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'proveedores', $empresa, $usuario),
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function proveedoresParaClaves(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "emp.empresa_token" => $empresa, 
      "users.usuario_token" => $usuario, 
      "catprov.status" => TRUE
    ])
    ->get();

    if ($listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron proveedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayProveedores = array();
      
      foreach ($listaProveedores as $resListProv) {
        $autorizado = $resListProv->authorized == TRUE ? true : false;
        $auth_fecha = $resListProv->authorized == TRUE ? gmdate('Y-m-d H:i:s', $resListProv->authorized_fecha) : null;
        $utilizado = $resListProv->utilizado == TRUE ? true : false;

        $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

        $rfc_generico = $resListProv->rfc_generico;
        $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
        $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

        if ($resListProv->folio != NULL && $resListProv->folio != "") {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
          if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
        } else {
          $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
        }

        $lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

        $arrayForeach = array(
          "token_cat_proveedores" => $resListProv->token_cat_proveedores,
          "folio" => $folio_prov,
          "pais" => $resListProv->pais,
          "rfc_generico" => $rfc_generico,
          "rfc_prov" => $rfc_prov,
          "tax_id_prov" => $tax_id_prov,
          "nombre" => $nombreProv,
          "listaPrecios" => $lista_precios,
          "encendido" => false,
          "tiene_clave" => "",
          "asigned_clave" => "",
        );

        $arrayProveedores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'proveedores' => $arrayProveedores
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoProvAutorizados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "catprov.status" => TRUE,
      "catprov.authorized" => TRUE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])->get();

    if ($listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron proveedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayProveedores = array();
      
      foreach ($listaProveedores as $resListProv) {
        $utilizado = false;
        if ($resListProv->utilizado == TRUE) $utilizado = true;

        $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

        $rfc_generico = $resListProv->rfc_generico;

        if ($resListProv->rfc != NULL) {
          $rfc_prov = $JwtAuth->desencriptar($resListProv->rfc);
        } else {
          $rfc_prov = '---';
        }

        if ($resListProv->tax_id != NULL) {
          $tax_id_prov = $JwtAuth->desencriptar($resListProv->tax_id);
        } else {
          $tax_id_prov = '---';
        }

        $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
        if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;

        $lista_precios = "";
        if ($resListProv->lista_precios != NULL) {
          $lista_precios = "A";
        }

        $arrayForeach = array(
          "token_cat_proveedores" => $resListProv->token_cat_proveedores,
          "folio" => $folio_prov,
          "pais" => $resListProv->pais,
          "rfc_generico" => $rfc_generico,
          "rfc_prov" => $rfc_prov,
          "tax_id_prov" => $tax_id_prov,
          "nombre" => $nombreProv,
          "listaPrecios" => $lista_precios,
          "tiene_clave" => "",
          "encendido" => false,
          "utilizado" => $utilizado,
          "auth_fecha" => gmdate('Y-m-d H:i:s', $resListProv->authorized_fecha),
        );

        $arrayProveedores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listado' => $arrayProveedores,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoProvNotAutorizados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "catprov.status" => TRUE,
      "catprov.authorized" => FALSE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])->get();

    if ($listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron proveedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayProveedores = array();
      
      foreach ($listaProveedores as $resListProv) {
        $utilizado = $resListProv->utilizado == TRUE ? true : false;
        $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);
        $rfc_generico = $resListProv->rfc_generico;
        $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
        $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';
        $lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

        if ($resListProv->temp_folio != NULL) {
          $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
        } else {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
          if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
        }

        $soliValidate = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("eegr_catalogo_proveedores_soli_auth AS soli_auth", "catprov.id", "=", "soli_auth.proveedor")
          ->where(["soli_auth.soli_aprobada" => FALSE, "catprov.token_cat_proveedores" => $resListProv->token_cat_proveedores])->get();

        $arrayForeach = array(
          "token_cat_proveedores" => $resListProv->token_cat_proveedores,
          "folio" => $folio_prov,
          "pais" => $resListProv->pais,
          "rfc_generico" => $rfc_generico,
          "rfc_prov" => $rfc_prov,
          "tax_id_prov" => $tax_id_prov,
          "nombre" => $nombreProv,
          "listaPrecios" => $lista_precios,
          "tiene_clave" => "",
          "encendido" => false,
          "utilizado" => $utilizado,
          "solicitudes" => count($soliValidate),
          //"auth_fecha" => date('d-m-Y H:i:s',$resListProv->authorized_fecha),
        );

        $arrayProveedores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listado' => $arrayProveedores
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function requestValidacionProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_proveedor = $request->input('token_proveedor');
      $observaciones = "permiso de prueba";

      $queryProveedor = DB::table("eegr_catalogo_proveedores AS catprov")
      ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
      ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
      ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catprov.token_cat_proveedores" => $token_proveedor,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($queryProveedor->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        foreach ($queryProveedor as $vProv) {
          //da_te_default_timezone_set($vProv->zona_horaria);

          $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($vProv->temp_folio);
          $nombre_prov = strtolower($JwtAuth->desencriptar($vProv->nombre_extendido));

          $select_id_prov = DB::select(
            "SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?",
            [$vProv->token_cat_proveedores]
          );

          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                          WHERE people.id = emp.persona AND emp.empresa_token = ?", [$empresa]);

          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                          JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                          AND users.usuario_token = ?", [$usuario]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
          $folioSistema = DB::select("SELECT max(soli_auth.folio_proveedores_soli_auth) AS folio_permiso FROM eegr_catalogo_proveedores_soli_auth AS soli_auth 
                          JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?", [$empresa]);

          if (count($folioSistema) == 0) {
            $sql_folio = 1;
          } else {
            $sql_folio = end($folioSistema)->folio_permiso + 1;
          }

          $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $observaciones . time() - 500);

          $insertSoliPerm = DB::table("eegr_catalogo_proveedores_soli_auth")
            ->insert(
              array(
                "token_proveedores_soli_auth" => $token_auth,
                "folio_proveedores_soli_auth" => $sql_folio,
                "fecha_proveedores_soli_auth" => time(),
                "user_emp" => end($select_empresa)->id,
                "user_user" => end($select_usuario)->id,
                "proveedor" => end($select_id_prov)->id,
                "observaciones" => $JwtAuth->encriptar($observaciones),
                "receptor" => 3,
                "solicitud_prov_status" => TRUE,
              )
            );

          if ($insertSoliPerm) {
            $tkn_user = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY";
            $titulo_ = "Validación de proveedor";
            $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado validación para el proveedor con el folio " . $folio_prov . " y nombre " . $nombre_prov;
            $JwtAuth->notificacionPushDevices($tkn_user, $titulo_, $mensaje_user);
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
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validacionProcesoProveedores(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_proveedor = $request->input('token_proveedor');
      
      $queryProveedor = DB::table("eegr_catalogo_proveedores AS catprov")
      ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
      ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
      ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catprov.token_cat_proveedores" => $token_proveedor,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])->get();

      if ($queryProveedor->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        foreach ($queryProveedor as $vProv) {
          //da_te_default_timezone_set($vProv->zona_horaria);

          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                          WHERE people.id = emp.persona AND emp.empresa_token = ?", [$empresa]);

          $select_usuario = DB::select("SELECT pers.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                          JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                          AND users.usuario_token = ?", [$usuario]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);

          $nombre_prov = strtolower($JwtAuth->desencriptar($vProv->nombre_extendido));

          $folio_prov_temp = 'PRV-TEMP-' . $JwtAuth->generarFolio($vProv->temp_folio);

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                          FROM sos_last_folders AS fold JOIN main_empresas AS emp
                          WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id 
                          AND emp.empresa_token = ?", [$empresa]);

          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
              $post_folio_db = DB::select("SELECT post_folio FROM eegr_catalogo_proveedores 
                                  WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
                                  JOIN main_empresas AS emp WHERE catprov.administrador = emp.id 
                                  AND emp.empresa_token = ?)", [$empresa]);

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

          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . ($post_folio != NULL ? '-' . $post_folio : '');

          $updateProvValid = DB::table("eegr_catalogo_proveedores")
            ->where(["token_cat_proveedores" => $vProv->token_cat_proveedores])
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
            $soliValidate = DB::table("eegr_catalogo_proveedores AS catprov")
              ->join("eegr_catalogo_proveedores_soli_auth AS soli_auth", "catprov.id", "=", "soli_auth.proveedor")
              ->join("teci_usuarios_catalogo AS users", "soli_auth.user_user", "=", "users.id")
              ->where(["soli_auth.soli_aprobada" => FALSE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

            if (count($soliValidate) > 0) {
              $titulo_ = "Validación de proveedor";
              $mensaje_user = "El proveedor con folio temporal " . $folio_prov_temp . " y nombre " . $nombre_prov . " ha sido validado con el folio " . $folio_prov;
              foreach ($soliValidate as $mSoli) {
                $soliValidAprob = DB::table("eegr_catalogo_proveedores_soli_auth")
                  ->where(["token_proveedores_soli_auth" => $mSoli->token_proveedores_soli_auth])
                  ->limit(1)->update(array("soli_aprobada" => TRUE));

                $JwtAuth->notificacionPushDevices($mSoli->usuario_token, $titulo_, $mensaje_user);
              }
            }

            if (count($folioSistema) == 0) {
              $insertSistema = DB::table("sos_last_folders")
                ->insert(array("egr_proveedores" => TRUE, "folder" => 1, "post_folder" => $post_folio, "empresa" => $select_empresa[0]->id));
            } else {
              $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                ->where(["lastf.egr_proveedores" => TRUE, "emp.empresa_token" => $empresa])
                ->limit(1)->update(array("lastf.folder" => $folio_nuevo, "lastf.post_folder" => $post_folio));
            }

            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "Proveedor validado con el folio " . $folio_prov,
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Validación de proveedor no registrada, intentelo nuevamente o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoProvNotVincUser(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores_soli_vinc_usuario AS prvVuser")
    ->join("eegr_catalogo_proveedores AS catprov", "prvVuser.proveedor", "catprov.id")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("main_empresas AS emp", "prvVuser.empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "prvVuser.aprobada" => FALSE,
      "catprov.status" => TRUE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])
    ->get();

    if ($listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron proveedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayProveedores = array();
      
      foreach ($listaProveedores as $vVProv) {
        //da_te_default_timezone_set('UTC');
        $folio_prov = 'PRV-' . $JwtAuth->generarFolio($vVProv->folio) . (!is_null($vVProv->post_folio) ? '-' . $vVProv->post_folio : '');
        $usersVinculados = array();
        $queryVincProvs = DB::table("teci_usuarios_catalogo")
          ->where("acceso_email", $vVProv->info_comparativa)
          ->get();

        foreach ($queryVincProvs as $users) {
          $rowUser = array(
            "usuario_token" => $users->usuario_token,
            "usuario_folio" => 'USER-' . $JwtAuth->generarFolio($users->usuario_folio),
            "usuario_alias" => $JwtAuth->desencriptar($users->usuario_alias)
          );
          $usersVinculados[] = $rowUser;
        }

        $arrayForeach = array(
          "soli_vinculo_token" => $vVProv->token_soli_vinculo,
          "soli_vinculo_folio" => 'SOLI-' . $JwtAuth->generarFolio($vVProv->folio_soli_vinculo),
          "soli_vinculo_fecha" => gmdate('Y-m-d H:i:s', $vVProv->fecha_soli_vinculo),
          "soli_vinculo_email" => $JwtAuth->desencriptar($vVProv->email_vinculo),
          "soli_usersVinculadosTotal" => count($usersVinculados),
          "soli_usersVinculados" => $usersVinculados,
          "proveedor_token" => $vVProv->token_cat_proveedores,
          "proveedor_folio" => $folio_prov,
          "proveedor_nombre" => $JwtAuth->desencriptar($vVProv->nombre_extendido),
          "proveedor_nombre_comercial" => !is_null($vVProv->nombre_com) ? $JwtAuth->desencriptar($vVProv->nombre_com) : '',
          "proveedor_rfc_generico" => $vVProv->rfc_generico,
          "proveedor_rfc" => $vVProv->rfc != NULL ? $JwtAuth->desencriptar($vVProv->rfc) : '---',
          "proveedor_tax_id" => $vVProv->tax_id != NULL ? $JwtAuth->desencriptar($vVProv->tax_id) : '---',
        );
        $arrayProveedores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listado' => $arrayProveedores
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoProvVincularExistentUsuario(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'soli_vinculo_token' => 'required|string',
      'token_proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $soli_vinculo_token = $request->input('soli_vinculo_token');
      $token_proveedor = $request->input('token_proveedor');

      $listaProveedores = DB::table("eegr_catalogo_proveedores_soli_vinc_usuario AS prvVuser")
      ->join("eegr_catalogo_proveedores AS catprov", "prvVuser.proveedor", "catprov.id")
      ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
      ->join("main_empresas AS emp", "prvVuser.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "prvVuser.token_soli_vinculo" => $soli_vinculo_token,
        "catprov.token_cat_proveedores" => $token_proveedor,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($listaProveedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron proveedores registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();

        foreach ($listaProveedores as $vVProv) {
          //da_te_default_timezone_set('UTC');
          $folio_prov = 'PRV-'.$JwtAuth->generarFolio($vVProv->folio).(!is_null($vVProv->post_folio) ? '-'.$vVProv->post_folio : '');
          $prov_id = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$vVProv->token_cat_proveedores)->value("id");
          $usuario_alias = "";

          $queryVincProvs = DB::table("teci_usuarios_catalogo")
          ->where("acceso_email",$vVProv->info_comparativa)
          ->get();

          foreach ($queryVincProvs as $users) {
            $usuario_alias = $JwtAuth->desencriptar($users->usuario_alias);
            $user_id = DB::table("teci_usuarios_catalogo")->where("usuario_token",$users->usuario_token)->value("id");

            //create table main_proveedor_usuario(
              //id int(10) primary key not null AUTO_INCREMENT,
              //proveedor int(10),
              //usuario int(10),
              //vinculacion_estado boolean,
              //vinculacion_apagado varchar(10),
              //foreign key (proveedor) references eegr_catalogo_proveedores (id),
              //foreign key (usuario) references teci_usuarios_catalogo (id)
            //);

            $insertSoliPerm = DB::table("main_proveedor_usuario")
            ->insert(
              array(
                "proveedor" => $prov_id,
                "usuario" => $user_id,
                "vinculacion_estado" => TRUE,
              )
            );
          }
          
          $updateCredenciales = DB::table("eegr_catalogo_proveedores_soli_vinc_usuario")
          ->where('token_soli_vinculo',$vVProv->token_soli_vinculo)
          ->limit(1)->update(array('aprobada' => TRUE,'fecha_aprobacion' => time()));

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Proveedor con folio $folio_prov ha sido ha sido vinculado al usuario con alias $usuario_alias"
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoProvVincularNewUsuario(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'soli_vinculo_token' => 'required|string',
      'token_proveedor' => 'required|string',
      'access_code' => 'required|string',
      'password_code' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $soli_vinculo_token = $request->input('soli_vinculo_token');
      $token_proveedor = $request->input('token_proveedor');
      $access_code = $request->input('access_code');
      $password_code = $request->input('password_code');

      $valida_access_code = isset($access_code) && !empty($access_code);
      $valida_password_code = isset($password_code) && !empty($password_code);
      if ($valida_access_code && $valida_password_code) {
        $listaProveedores = DB::table("eegr_catalogo_proveedores_soli_vinc_usuario AS prvVuser")
        ->join("eegr_catalogo_proveedores AS catprov", "prvVuser.proveedor", "catprov.id")
        ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
        ->join("main_empresas AS emp", "prvVuser.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("prvVuser.token_soli_vinculo",$soli_vinculo_token)
        ->where("catprov.token_cat_proveedores",$token_proveedor)
        ->where("emp.empresa_token",$empresa)
        ->where("users.usuario_token",$usuario)
        ->get();

        foreach ($listaProveedores as $vVProv) {
          //da_te_default_timezone_set('UTC');
          $folio_prov = 'PRV-'.$JwtAuth->generarFolio($vVProv->folio).(!is_null($vVProv->post_folio) ? '-'.$vVProv->post_folio : '');
          $prov_id = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$vVProv->token_cat_proveedores)->value("id");
          $emp_id = DB::table("main_empresas")->where("empresa_token",$empresa)->value("id");
          $usuario_alias = $JwtAuth->desencriptar($vVProv->email_vinculo);
          
          $select_fol_users = DB::select("SELECT MAX(usuario_folio)+1 AS fol_max FROM teci_usuarios_catalogo");
          foreach ($select_fol_users as $vFUser) {
            $folio_nuevo = $vFUser->fol_max;
            $folio_nuevo_extend = 'USER-' . $JwtAuth->generarFolio($vFUser->fol_max);
          }

          $tokenUserNew = $JwtAuth->encriptarToken($folio_prov,$vVProv->email_vinculo,$vVProv->info_comparativa,"P");
          $dataUser = new User();
          $dataUser->usuario_token = $tokenUserNew;
          $dataUser->usuario_folio = $folio_nuevo;	
          $dataUser->usuario_fecha_registro = time();
          $dataUser->usuario_alias = $vVProv->email_vinculo;
          
          $dataUser->usuario_imagen_perfil = $JwtAuth->encriptar("default-profile.png");
          $dataUser->acceso_email = $vVProv->info_comparativa;
          $dataUser->acceso_codigo = $JwtAuth->encriptar($access_code);
          $dataUser->acceso_password = $JwtAuth->encriptar($password_code);
          $dataUser->login_permission = TRUE;
          $dataUser->jerarquia_main = "P";
          $dataUser->tipo = 8;
          $dataUser->empresa = $emp_id;
          $savednewProv = $dataUser->save();

          $insertUnion = DB::table('main_empresa_usuario')->insert(array("empresa" => $emp_id,"usuario" => $dataUser->id));

          //egresos
          $insertConfigEegr = DB::table('configuracion_systema_eegr')->insert(array(
            "acceso" => TRUE,
            "catalogos" => FALSE,
            "cat_prod" => FALSE,
            "cat_serv" => FALSE,
            "cat_actf" => FALSE,
            "cat_acti" => FALSE,
            "cat_prov" => FALSE,
            "cat_esta" => FALSE,
            "compras" => FALSE,
            "comp_req" => FALSE,
            "comp_cot" => FALSE,
            "comp_dir" => FALSE,
            "comp_seg" => FALSE,
            "reembolsos" => FALSE,
            "justificaciones" => FALSE,
            "reportes" => FALSE,
            "jerarquia" => "D",
            "privilegio_crear" => FALSE,
            "privilegio_editar" => FALSE,
            "privilegio_consulta" => FALSE,
            "privilegio_elimina" => FALSE,
            "privilegio_ver_docs" => FALSE,
            "empresa" => $emp_id,
            "usuario" => $dataUser->id,
          ));

          $insertUserSettings = DB::table('teci_user_settings')->insert(array(
            "usuario" => $dataUser->id,
            "lenguaje" => "es",
            "privilegio_crear" => TRUE,
            "privilegio_editar" => TRUE,
            "privilegio_consulta" => TRUE,
            "privilegio_elimina" => TRUE,
            "privilegio_ver_docs" => TRUE,
          ));

          $insertSoliPerm = DB::table("main_proveedor_usuario")
          ->insert(
            array(
              "proveedor" => $prov_id,
              "usuario" => $dataUser->id,
              "vinculacion_estado" => TRUE,
            )
          );
          
          $updateCredenciales = DB::table("eegr_catalogo_proveedores_soli_vinc_usuario")
          ->where('token_soli_vinculo',$vVProv->token_soli_vinculo)
          ->limit(1)->update(array('aprobada' => TRUE,'fecha_aprobacion' => time()));

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Proveedor con folio $folio_prov ha sido ha sido vinculado al usuario con alias $usuario_alias"
          );
        }
      } else {
        if (!$valida_access_code) {$mensaje_error = 'Error en código de acceso de usuario seleccionado, intentelo nuevamente o comuniquese a soporte';}
        if (!$valida_password_code) {$mensaje_error = 'Error en password de usuario seleccionado, intentelo nuevamente o comuniquese a soporte';}
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getCatalogoProvDel(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_catalogo_proveedores.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->get();

    if ($listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron proveedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayProv = array();
      
      foreach ($listaProveedores as $resListprov) {
        $nombreProv = $JwtAuth->desencriptar($resListprov->nombre_extendido);
  
        $rfc_generico = $resListprov->rfc_generico;
        $rfc_prov = !is_null($resListprov->rfc) ? $JwtAuth->desencriptar($resListprov->rfc) : '---';
        $tax_id_prov = !is_null($resListprov->tax_id) ? $JwtAuth->desencriptar($resListprov->tax_id) : '---';
  
        $fechaDelete = $resListprov->fecha_delete_prov;
        //da_te_default_timezone_set('America/Mexico_City');
        $fecha = gmdate('Y-m-d H:i:s', $fechaDelete);
  
        $folio_prov = 'PRV-'.$JwtAuth->generarFolio($resListprov->folio).(!is_null($resListprov->post_folio) ? '-'.$resListprov->post_folio : '');
  
        $arrayForeach = array(
          "fecha_delete" => $fecha,
          "token_cat_proveedores" => $resListprov->token_cat_proveedores,
          "folio" => $folio_prov,
          "pais" => $resListprov->pais,
          "rfc_generico" => $rfc_generico,
          "rfc_prov" => $rfc_prov,
          "tax_id_prov" => $tax_id_prov,
          "nombre" => $nombreProv,
        );
  
        $arrayProv[] = $arrayForeach;
      }
  
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'proveedor' => $arrayProv
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function verDetalleProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_proveedor = $request->input('token_proveedor');
      
      $selectProveedor = ProveedoresModelo::join("sos_personas AS perns", "eegr_catalogo_proveedores.proveedor", "=", "perns.id")
      ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "eegr_catalogo_proveedores.token_cat_proveedores" => $token_proveedor, 
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])
      ->get();
      
      if ($selectProveedor->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayProveedor = array();
        
        foreach ($selectProveedor as $vProv) {
          //da_te_default_timezone_set($vProv->zona_horaria);
          $listaUbicacion = array();
          $arrayActividad = array();

          $folio_prov = $vProv->authorized == TRUE ? "PRV-" . $JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : "") : "PRV-TEMP-" . $JwtAuth->generarFolio($vProv->temp_folio);
          $proveedor_name = $vProv->nombre_extendido == "" || $vProv->nombre_extendido == NULL ?
            ($vProv->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vProv->paterno, $vProv->materno, $vProv->nombre) : $JwtAuth->desencriptar($vProv->denominacion_rs)) :
            $JwtAuth->desencriptar($vProv->nombre_extendido);
          $proveedor_name_header = $vProv->nombre_com != '' && $vProv->nombre_com != '-' ? $JwtAuth->desencriptar($vProv->nombre_com) : $proveedor_name;

          $proveedor_nacionalidad = $vProv->nacionalidad;
          $pais_token = $vProv->token_pais;
          $pais_name = $vProv->pais;

          $rfc_generico = $vProv->rfc_generico;
          $rfc_prov = $vProv->rfc != NULL ? $JwtAuth->desencriptar($vProv->rfc) : "---";
          $tax_id_prov = $vProv->tax_id != NULL ? $JwtAuth->desencriptar($vProv->tax_id) : "---";

          $nombre_com = $vProv->nombre_com != '' && $vProv->nombre_com != '-' ? $JwtAuth->desencriptar($vProv->nombre_com) : "";
          $ccontable = !is_null($vProv->cuenta_contable) && $vProv->cuenta_contable != '-' ? $JwtAuth->desencriptar($vProv->cuenta_contable) : "";
          $sitio_web = $vProv->sitio_web != '' && $vProv->sitio_web != '-' ? $JwtAuth->desencriptar($vProv->sitio_web) : "";
          $listaPrecios = $vProv->lista_precios != '' ? $vProv->lista_precios : "";

          $regimen_fiscal_token = "";
          $regimen_fiscal_descripcion = "";
          $queryRegimenFiscal = ProveedoresModelo::join("sos_regimen_fiscal AS regf", "eegr_catalogo_proveedores.regimen_fiscal", "=", "regf.id")
            ->where(["eegr_catalogo_proveedores.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

          foreach ($queryRegimenFiscal as $vRegf) {
            $regimen_fiscal_token = $vRegf->token_regimen_fiscal;
            $regimen_fiscal_descripcion = $vRegf->clave . "-" . $vRegf->descripcion;
          }

          //contacto actual
          $personalContacto = array();
          $queryContProv = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
            ->join("sos_personas AS people", "empleado.nombre", "=", "people.id")
            ->join("eegr_catalogo_proveedores AS catprov", "empleado.cat_proveedores", "=", "catprov.id")
            ->where(["empleado.status" => TRUE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

          if (count($queryContProv) > 0) {
            foreach ($queryContProv as $valContProv) {
              $arrayTelefono = array();
              $arrayTelefonoDeleted = array();
              $telefonoProv = DB::table("sos_personas_telefonos AS tel")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.contacto_cliente_prov", "=", "empleado.id")
                ->where(["tel.status_telefono" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($telefonoProv) > 0) {
                foreach ($telefonoProv as $valueTelPers) {
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

              $telefonoProvDeleted = DB::table("sos_personas_telefonos AS tel")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.contacto_cliente_prov", "=", "empleado.id")
                ->where(["tel.status_telefono" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($telefonoProvDeleted) > 0) {
                foreach ($telefonoProvDeleted as $vTelPers) {
                  $arrateleach = array(
                    "token_telefono" => $vTelPers->token_telefono,
                    "etiqueta" => $vTelPers->etiqueta,
                    "telefono" => $JwtAuth->desencriptar($vTelPers->telefono),
                    "extension" => $vTelPers->extension != NULL && $vTelPers->extension != "" ? $JwtAuth->desencriptar($vTelPers->extension) : "---",
                  );
                  $arrayTelefonoDeleted[] = $arrateleach;
                }
              }

              $arrayCorreo = array();
              $arrayCorreoDel = array();

              $queryMailProv = DB::table("sos_personas_correos AS mailpers")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.contacto_cliente_prov", "=", "empleado.id")
                ->where(["mailpers.status_correo" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($queryMailProv) > 0) {
                foreach ($queryMailProv as $vMailPers) {
                  $arrateleach = array(
                    "token_correo" => $vMailPers->token_correo,
                    "correo" => $JwtAuth->desencriptar($vMailPers->correo),
                    "correo_edit" => $JwtAuth->desencriptar($vMailPers->correo)
                  );
                  $arrayCorreo[] = $arrateleach;
                }
              }

              $queryMailPrvD = DB::table("sos_personas_correos AS mailpers")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.contacto_cliente_prov", "=", "empleado.id")
                ->where(["mailpers.status_correo" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($queryMailPrvD) > 0) {
                foreach ($queryMailPrvD as $vMailPers) {
                  $arrateleach = array(
                    'token_correo' => $vMailPers->token_correo,
                    'correo' => $JwtAuth->desencriptar($vMailPers->correo),
                    'fechaDelete' => gmdate('Y-m-d H:i:s', $vMailPers->fecha_delete_correo),
                  );
                  $arrayCorreoDel[] = $arrateleach;
                }
              }

              $proveVig = array(
                "token_contacto" => $valContProv->token_contacto,
                "paterno" => $JwtAuth->desencriptar($valContProv->paterno),
                "paterno_edit" => $JwtAuth->desencriptar($valContProv->paterno),
                "materno" => $JwtAuth->desencriptar($valContProv->materno),
                "materno_edit" => $JwtAuth->desencriptar($valContProv->materno),
                "nombre" => $JwtAuth->desencriptar($valContProv->nombre),
                "nombre_edit" => $JwtAuth->desencriptar($valContProv->nombre),
                "area_contacto" => $JwtAuth->desencriptar($valContProv->area_contacto),
                "area_contacto_edit" => $JwtAuth->desencriptar($valContProv->area_contacto),
                "cargo_contacto" => $JwtAuth->desencriptar($valContProv->cargo_contacto),
                "cargo_contacto_edit" => $JwtAuth->desencriptar($valContProv->cargo_contacto),
                "telefonos" => $arrayTelefono,
                "telefonosDeleted" => $arrayTelefonoDeleted,
                "filtro_telefonos" => null,
                "correos" => $arrayCorreo,
                "correosDeleted" => $arrayCorreoDel,
                "filtro_correos" => null,
              );
              $personalContacto[] = $proveVig;
            }
          }

          //eliminado
          $personalContactoDel = array();
          $queryContProvDel = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
            ->join("sos_personas AS people", "empleado.nombre", "=", "people.id")
            ->join("eegr_catalogo_proveedores AS catprov", "empleado.cat_proveedores", "=", "catprov.id")
            ->where(["empleado.status" => FALSE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

          if (count($queryContProvDel) > 0) {
            foreach ($queryContProvDel as $valContProv) {
              $arrayTelefono = array();
              $arrayTelefonoDeleted = array();
              $telefonoProv = DB::table("sos_personas_telefonos AS tel")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
                ->where(["tel.status_telefono" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($telefonoProv) > 0) {
                foreach ($telefonoProv as $valueTelPers) {
                  $telExtension = '';
                  if ($valueTelPers->extension != '') {
                    $telExtension = $JwtAuth->desencriptar($valueTelPers->extension);
                  }
                  $arrateleach = array(
                    'token_telefono' => $valueTelPers->token_telefono,
                    'telefono' => $JwtAuth->desencriptar($valueTelPers->telefono),
                    'extension' => $telExtension,
                    'icono' => $valueTelPers->icono,
                    'etiqueta' => $valueTelPers->etiqueta,
                    'validate' => false,
                  );
                  $arrayTelefono[] = $arrateleach;
                }
              }

              $telefonoProvDeleted = DB::table("sos_personas_telefonos AS tel")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
                ->where(["tel.status_telefono" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($telefonoProvDeleted) > 0) {
                foreach ($telefonoProvDeleted as $vTelPers) {
                  $telExtension = '';
                  if ($vTelPers->extension != '') {
                    $telExtension = $JwtAuth->desencriptar($vTelPers->extension);
                  }
                  $arrateleach = array(
                    'token_telefono' => $vTelPers->token_telefono,
                    'telefono' => $JwtAuth->desencriptar($vTelPers->telefono),
                    'extension' => $telExtension,
                    'icono' => $vTelPers->icono,
                    'etiqueta' => $vTelPers->etiqueta,
                    'validate' => false,
                  );
                  $arrayTelefonoDeleted[] = $arrateleach;
                }
              }

              $arrayCorreo = array();
              $arrayCorreoDel = array();

              $queryMailProv = DB::table("sos_personas_correos AS mailpers")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.empleado_name", "=", "empleado.id")
                ->where(["mailpers.status_correo" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($queryMailProv) > 0) {
                foreach ($queryMailProv as $valueMailPers) {
                  $arrateleach = array(
                    'token_correo' => $valueMailPers->token_correo,
                    'correo' => $JwtAuth->desencriptar($valueMailPers->correo)
                  );
                  $arrayCorreo[] = $arrateleach;
                }
              }

              $queryMailPrvD = DB::table("sos_personas_correos AS mailpers")
                ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.empleado_name", "=", "empleado.id")
                ->where(["mailpers.status_correo" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

              if (count($queryMailPrvD) > 0) {
                foreach ($queryMailPrvD as $vMailPers) {
                  $arrateleach = array(
                    'token_correo' => $vMailPers->token_correo,
                    'correo' => $JwtAuth->desencriptar($vMailPers->correo),
                    'fechaDelete' => gmdate('Y-m-d H:i:s', $vMailPers->fecha_delete_correo),
                  );
                  $arrayCorreoDel[] = $arrateleach;
                }
              }

              $proveVig = array(
                "token_contacto" => $valContProv->token_contacto,
                "paterno" => strtolower($JwtAuth->desencriptar($valContProv->paterno)),
                "materno" => strtolower($JwtAuth->desencriptar($valContProv->materno)),
                "nombre" => strtolower($JwtAuth->desencriptar($valContProv->nombre)),
                "areaemp" => strtolower($JwtAuth->desencriptar($valContProv->areaemp)),
                "cargo" => strtolower($JwtAuth->desencriptar($valContProv->cargo)),
                "telefono" => $arrayTelefono,
                "telefonoDeleted" => $arrayTelefonoDeleted,
                "correo" => $arrayCorreo,
                "arrayCorreoDel" => $arrayCorreoDel
              );
              $personalContactoDel[] = $proveVig;
            }
          }

          $docs_adjuntos = array();
          if ($vProv->tiene_docs_fiscales == TRUE) {
            //echo $JwtAuth->desencriptar("VEpIMk44MlAwK2NXNy9mSVF4bzI0RjNwQnFPdGRaRVAyUWtnQ05TWGUxdXFMakFYOU4wWnltNGxLempiQWsrejo6MTIzNDU2NzgxMjM0NTY3OA==");
            $selectSitFisDoc = DB::table("sos_documentos AS docs")
              ->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
              ->where([
                "docs.tipo_documento" => "fcsf",
                "docs.status_documento" => TRUE,
                "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
              ])->get();
            if (count($selectSitFisDoc) > 0) {
              foreach ($selectSitFisDoc as $vDoc) {
                $fcsf = "Constancia de situación fiscal";
                $cuof = "Constancia de cumplimiento de obligaciones fiscales";
                $fcnt = "Contratos";
                $anex = "Anexos";
                $ecue = "Estado de cuenta";

                $url_fcsf = "https://downloads.sos-mexico.com.mx/proveedores_sit_fiscal_by_token/";
                $url_cuof = "https://downloads.sos-mexico.com.mx/proveedores_opinion_cumplimiento_by_token/";
                $url_fcnt = "https://downloads.sos-mexico.com.mx/proveedores_contratos_by_token/";
                $url_anex = "https://downloads.sos-mexico.com.mx/proveedores_anexos_by_token/";
                $url_ecue = "https://downloads.sos-mexico.com.mx/proveedores_estado_de_cuenta_by_token/";

                $rowDocs = array(
                  "token_documento" => $vDoc->token_documento,
                  "tipo_documento" => $vDoc->tipo_documento == "fcsf" ? $fcsf : ($vDoc->tipo_documento == "cuof" ? $cuof : ($vDoc->tipo_documento == "fcnt" ? $fcnt : ($vDoc->tipo_documento == "anex" ? $anex : $ecue))),
                  "ext_doc" => $vDoc->extension_documento,
                  "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                  "url" => $vDoc->tipo_documento == "fcsf" ? $url_fcsf : ($vDoc->tipo_documento == "cuof" ? $url_cuof : ($vDoc->tipo_documento == "fcnt" ? $url_fcnt : ($vDoc->tipo_documento == "anex" ? $url_anex : $url_ecue))) . $vDoc->token_documento,
                );
                $docs_adjuntos[] = $rowDocs;
              }
            }
          }

          $no_cuenta_fiscales = $vProv->no_cuenta_fiscales != NULL && $vProv->no_cuenta_fiscales != '' ? $JwtAuth->desencriptar($vProv->no_cuenta_fiscales) : "-";

          //creditos
          $token_moneda = "";
          $arregloCreditos = array();
          $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
            ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
            ->where(["catprv.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();


          $creditos_token = "";
          $creditos_acepta = false;
          $creditos_moneda_code = "";
          $creditos_moneda_decimales = "";
          $creditos_limite = "$" . number_format(0, 2, '.', ',');
          $creditos_dias = "";
          $creditos_fechalimite = "";
          $creditos_comienza = "";
          if (count($credProveedor) > 0) {
            foreach ($credProveedor as $vCred) {
              $creditos_token = $vCred->token_creditos;
              $creditos_acepta = $vCred->aceptacredito == TRUE ? true : false;
              $creditos_moneda_code = $vCred->aceptacredito == TRUE ? $vCred->moneda_code : '';
              $creditos_moneda_decimales = $vCred->aceptacredito == TRUE ? $vCred->moneda_decimales : '';
              $creditos_limite = $vCred->aceptacredito == TRUE ? $vCred->limite : '';
              $creditos_dias = $vCred->aceptacredito == TRUE ? $vCred->dias : '';
              $creditos_fechalimite = $vCred->aceptacredito == TRUE ? date("d-m-Y", time() + (86400 * $vCred->dias)) : '';
              $creditos_comienza = $vCred->aceptacredito == TRUE ? $vCred->comienza : '';

              $comienza_credito_text = "---";
              if ($vCred->aceptacredito == TRUE && $vCred->comienza == "cada.inicio.mes") $comienza_credito_text = "Cada inicio de mes";
              if ($vCred->aceptacredito == TRUE && $vCred->comienza == "sistem.emite.orden.pago") $comienza_credito_text = "Se emite/envía orden de pago";
              if ($vCred->aceptacredito == TRUE && $vCred->comienza == "serecibe.facturadel.proveedor") $comienza_credito_text = "Se recibe factura del proveedor";
              if ($vCred->aceptacredito == TRUE && $vCred->comienza == "producto.sale.bodegas.proveedor") $comienza_credito_text = "El producto salga de las bodegas del proveedor";
              if ($vCred->aceptacredito == TRUE && $vCred->comienza == "producto.recibido.nuestras.bodegas") $comienza_credito_text = "El producto es recibido en nuestras bodegas";
              $class_disabled = $vCred->aceptacredito == FALSE ? "disabledContentWhite" : "";

              $creditosEach = array(
                "token_creditos" => $vCred->token_creditos,
                "acepta" => $vCred->aceptacredito == TRUE ? true : false,
                "acepta_resp" => $vCred->aceptacredito == TRUE ? true : false,
                "moneda_code" => $vCred->aceptacredito == TRUE ? $vCred->moneda_code : '',
                "moneda_code_resp" => $vCred->aceptacredito == TRUE ? $vCred->moneda_code : '',
                "moneda_decimales" => $vCred->aceptacredito == TRUE ? $vCred->moneda_decimales : '',
                "moneda_decimales_resp" => $vCred->aceptacredito == TRUE ? $vCred->moneda_decimales : '',
                "limite" => $vCred->aceptacredito == TRUE ? $vCred->limite : "---",
                "limite_resp" => $vCred->aceptacredito == TRUE ? $vCred->limite : "---",
                "dias" => $vCred->aceptacredito == TRUE ? $vCred->dias : "---",
                "dias_resp" => $vCred->aceptacredito == TRUE ? $vCred->dias : "---",
                "fechalimite" => $vCred->aceptacredito == TRUE ? date("d-m-Y", time() + (86400 * $vCred->dias)) : '',
                "fechalimite_resp" => $vCred->aceptacredito == TRUE ? date("d-m-Y", time() + (86400 * $vCred->dias)) : '',
                "comienza" => $vCred->aceptacredito == TRUE ? $vCred->comienza : '',
                "comienza_resp" => $vCred->aceptacredito == TRUE ? $vCred->comienza : '',
                "comienza_credito_text" => $comienza_credito_text,
                "class_disabled" => $class_disabled,
              );
              $arregloCreditos[] = $creditosEach;
            }
          }

          $arrayMonedas = array();
          $catMonedas = MonedasModelo::get();
          foreach ($catMonedas as $valMonedas) {
            $arrayMon = array(
              "token_monedas" => $valMonedas->token_monedas,
              "codigo" => $valMonedas->codigo,
              "moneda" => $valMonedas->moneda,
              "decimales" => $valMonedas->decimales,
              "selected" => $valMonedas->token_monedas == $token_moneda ? true : false,
            );
            $arrayMonedas[] = $arrayMon;
          }

          //forma de pago
          $formaPagoTiene = false;
          $formaPagoToken = "";
          $formaPagoClave = "";
          $formaPagoConcepto = "";
          $formaPagoTipoReferencia = "";
          $formaPagoInsideClass = "";
          if (isset($vProv->forma_pago)) {
            $tieneformaPago = true;
            $queryFPagoProv = ProveedoresModelo::join("teci_forma_pago AS pfor", "eegr_catalogo_proveedores.forma_pago", "=", "pfor.id")
              ->where(["eegr_catalogo_proveedores.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();
            foreach ($queryFPagoProv as $vFPbP) {
              $formaPagoToken = $vFPbP->token_formapago;
              $formaPagoClave = $vFPbP->clave;
              $formaPagoConcepto = $vFPbP->forma;
              $formaPagoTipoReferencia = $vProv->tipo_referencia_pago != NULL ? ($vProv->tipo_referencia_pago == "ci" ? "clabeInterbancaria" : ($vProv->tipo_referencia_pago == "co" ? "convenio" : "lineaCaptura")) : "";
              $formaPagoInsideClass = $vProv->token_formapago == "RkxGMTRidG44ZWJJYVh0dUlDK1o4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4" ? "transferencia" : "noneView";
            }
          }

          //ubicacion
          $arrayUbicacion = array();
          $arrayUbicacionDel = array();

          $listaUbicacion = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("teci_direcciones AS ubica", "catprov.id", "ubica.proveedor")
            ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
            ->where(["catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();
          if (count($listaUbicacion) > 0) {
            foreach ($listaUbicacion as $vUbica) {
              //echo $vUbica->pais;
              $status_ubica = DB::table("teci_direcciones")->where("token_direccion", $vUbica->token_direccion)->value("status");

              $new_direccion_estado = $vUbica->estado_edit != NULL ? $JwtAuth->desencriptar($vUbica->estado_edit) : "";
              $new_direccion_municipio = $vUbica->municipio_edit != NULL ? $JwtAuth->desencriptar($vUbica->municipio_edit) : "";
              $new_direccion_c_postal = $vUbica->c_postal_edit != NULL ? $vUbica->c_postal_edit : "";
              $new_direccion_colonia = $vUbica->colonia_edit != NULL ? $JwtAuth->desencriptar($vUbica->colonia_edit) : "";
              $new_direccion_adicional = $vUbica->adicional != NULL ? $vUbica->adicional : "";
              if ($vProv->nacionalidad == 118) {
                $eachUbicacion = array(
                  "token_direccion" => $vUbica->token_direccion,
                  "tipo_direccion" => $vUbica->tipo_direccion,
                  "clase" => $JwtAuth->desencriptar($vUbica->clase),
                  "pais" => "México",
                  "estado_main" => !empty($vUbica->estado_edit) ? $JwtAuth->desencriptar($vUbica->estado_edit) : '',
                  "estado_edit" => !empty($vUbica->estado_edit) ? $JwtAuth->desencriptar($vUbica->estado_edit) : '',
                  "municipio_main" => !empty($vUbica->municipio_edit) ? $JwtAuth->desencriptar($vUbica->municipio_edit) : '',
                  "municipio_edit" => !empty($vUbica->municipio_edit) ? $JwtAuth->desencriptar($vUbica->municipio_edit) : '',
                  "c_postal_main" => !empty($vUbica->c_postal_edit) ? $vUbica->c_postal_edit : '',
                  "c_postal_edit" => !empty($vUbica->c_postal_edit) ? $vUbica->c_postal_edit : '',
                  "colonia_main" => !empty($vUbica->colonia_edit) ? $JwtAuth->desencriptar($vUbica->colonia_edit) : '',
                  "colonia_edit" => !empty($vUbica->colonia_edit) ? $JwtAuth->desencriptar($vUbica->colonia_edit) : '',
                );
              } else {
                $eachUbicacion = array(
                  "token_direccion" => $vUbica->token_direccion,
                  "tipo_direccion" => $vUbica->tipo_direccion,
                  "clase" => $JwtAuth->desencriptar($vUbica->clase),
                  "pais" => $vUbica->pais_code,
                  "cod_postalext" => $JwtAuth->desencriptar($vUbica->cod_postalext),
                );
              }
              //if ($status_ubica == TRUE) {
              //	$arrayUbicacion[] = $eachUbicacion;
              //} else {
              //	$arrayUbicacionDel[] = $eachUbicacion;
              //}
              $status_ubica == TRUE ? $arrayUbicacion[] = $eachUbicacion : $arrayUbicacionDel[] = $eachUbicacion;
            }
          }

          //cuentas contables 
          $arrayCuentasCont = array();
          $getCuentasContables = DB::table("cont_catalogo_cuentas_contables AS cuent")
            ->join("eegr_catalogo_proveedores AS catprov", "cuent.egresos_proveedores", "=", "catprov.id")
            ->where(["catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();
          if (count($getCuentasContables) > 0) {
            foreach ($getCuentasContables as $valContables) {
              $alist = array(
                "token_cuenta_contable" => $valContables->token_cuenta_contable,
                "contable" => $valContables->contable,
              );
              $arrayCuentasCont[] = $alist;
            }
          }

          $select_bitacora = DB::select("SELECT bit_prv.*,people.paterno,people.materno,people.nombre FROM eegr_catalogo_proveedores_bitacora AS bit_prv JOIN eegr_catalogo_proveedores AS catprov 
            JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people WHERE bit_prv.proveedor = catprov.id 
            AND catprov.token_cat_proveedores = ? AND bit_prv.empresa = emp.id AND emp.empresa_token = ? AND bit_prv.usuario = users.id 
            AND users.usuario_token = ? AND users.empleado = pers.id AND pers.empleado_name = people.id", [$vProv->token_cat_proveedores, $empresa, $usuario]);

          foreach ($select_bitacora as $valBit) {
            $aeachFor = array(
              "token_bitacora" => $valBit->token_bitacora_prov,
              "fecha_bitacora" => gmdate('Y-m-d H:i:s', $valBit->fecha_bitacora_prov),
              "folio_bitacora" => $JwtAuth->generarFolio($valBit->folio_bitacora_prov),
              "actividad" => $valBit->actividad,
              "usuario_relacionado" => $JwtAuth->desencriptarNombres($valBit->paterno, $valBit->materno, $valBit->nombre),
            );
            $arrayActividad[] = $aeachFor;
          }

          $pfmx = "Persona física (México)";
          $pfext = "Persona física Extranjero";
          $pmmx = "Persona moral (México)";
          $pmext = "Persona moral Extranjero";
          $clasificacion = $vProv->nacionalidad == 118 ? "nacional" : "extranjero";
          $subClasificacionAll = $vProv->nacionalidad == 118 ? ($vProv->subClase == "PM" ? $pmmx : $pfmx) : ($vProv->subClase == "PM" ? $pmext : $pfext);
          $proveedor_identif = $vProv->rfc != NULL ? $JwtAuth->desencriptar($vProv->rfc) : ($vProv->tax_id != NULL ? $JwtAuth->desencriptar($vProv->tax_id) : $vProv->rfc_generico);

          $arrayForeachProv = array(
            "token_proveedor" => $vProv->token_cat_proveedores,
            "folio_proveedor" => $folio_prov,
            "rfc_generico" => $rfc_generico,
            "nombre_proveedor_header" => $proveedor_name_header,
            "nombre_proveedor" => $proveedor_name,
            "nombre_proveedor_edit" => $proveedor_name,
            "rfc_prov" => $rfc_prov,
            "rfc_prov_edit" => $rfc_prov,
            "tax_id_prov" => $tax_id_prov,
            "tax_id_prov_edit" => $tax_id_prov,
            "clasificacion" => $clasificacion,
            "subClasificacionSimple" => $vProv->subClase,
            "subClasificacionAll" => $subClasificacionAll,
            "identificador" => $proveedor_identif,
            "nationality" => $proveedor_nacionalidad,
            "pais_ident" => $pais_token,
            "pais_name" => $pais_name,
            "nombre_comercial" => $nombre_com,
            "nombre_comercial_edit" => $nombre_com,
            "cuenta_contable" => $ccontable,
            "cuenta_contable_edit" => $ccontable,
            "sitio_web" => $sitio_web,
            "sitio_web_edit" => $sitio_web,
            "regimen_fiscal_token" => $regimen_fiscal_token,
            "regimen_fiscal_token_edit" => $regimen_fiscal_token,
            "regimen_fiscal_descripcion" => $regimen_fiscal_descripcion,
            "habilitado_para_reembolsos" => $vProv->habilitado_para_reembolsos ? true : false,
            "lista_precios" => $listaPrecios,
            "tiene_contacto_registrado" => count($personalContacto) > 0 ? true : false,
            "tiene_contacto_registrado_edit" => count($personalContacto) > 0 ? true : false,
            "contacto_registrado" => $personalContacto,
            "contacto_deleted" => $personalContactoDel,
            "tiene_docs_fiscales" => $vProv->tiene_docs_fiscales == TRUE ? true : false,
            "docs_adjuntos" => $docs_adjuntos,
            //"docs_sit_fiscal" => $docs_sit_fiscal,
            //"docs_obl_fiscal" => $docs_obl_fiscal,
            //"docs_contratos" => $docs_contratos,
            //"docs_anexos" => $docs_anexos,
            "noCargaDocsFiscalesRazon" => $no_cuenta_fiscales,

            "tieneCreditoAsignado" => $creditos_token != "" ? true : false,
            "tieneCreditoAsignado_edit" => $creditos_token != "" ? true : false,
            "creditos" => $arregloCreditos,
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
            //"arrayMonedas" => $arrayMonedas,

            "forma_pago_tiene" => $formaPagoTiene,
            "forma_pago_tiene_edit" => $formaPagoTiene,
            "forma_pago_token" => $formaPagoToken,
            "forma_pago_token_edit" => $formaPagoToken,
            "forma_pago_clave" => $formaPagoClave,
            "forma_pago_concepto" => $formaPagoConcepto,
            "forma_pago_tipo_referencia" => $formaPagoTipoReferencia,
            "forma_pago_inside_class" => $formaPagoInsideClass,

            "receptFacturaConcept" => $vProv->receptFactura == TRUE ? "Antes" : "Despues",
            "receptFacturaBool" => $vProv->receptFactura == TRUE ? true : false,
            "classRecibeArtPagoConcept" => $vProv->classRecibeArtPago == TRUE ? "Antes" : "Despues",
            "classRecibeArtPagoBool" => $vProv->classRecibeArtPago == TRUE ? true : false,
            "arrayUbicacion" => $arrayUbicacion,
            "arrayUbicacionDel" => $arrayUbicacionDel,
            "arrayCuentasCont" => $arrayCuentasCont,
            "bitacora" => $arrayActividad,
          );
          $arrayProveedor[] = $arrayForeachProv;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'proveedor' => $arrayProveedor
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function provHabilitaParaReembolsos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $queryProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
      ->where([
        "eegr_catalogo_proveedores.status" => true,
        "eegr_catalogo_proveedores.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])
      ->get();

      if ($queryProv->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        foreach ($queryProv as $vPrv) {
          $regGenerales = DB::table("eegr_catalogo_proveedores")
            ->where(['token_cat_proveedores' => $vPrv->token_cat_proveedores])
            ->limit(1)->update(
              array(
                "habilitado_para_reembolsos" => TRUE,
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
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function provCancelaParaReembolsos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $queryProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
      ->where([
        "eegr_catalogo_proveedores.status" => true,
        "eegr_catalogo_proveedores.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])
      ->get();

      if ($queryProv->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        foreach ($queryProv as $vPrv) {
          $regGenerales = DB::table("eegr_catalogo_proveedores")
            ->where(['token_cat_proveedores' => $vPrv->token_cat_proveedores])
            ->limit(1)->update(
              array(
                "habilitado_para_reembolsos" => FALSE,
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
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function saldosProveedorList(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $proveedor_saldo_total = 0;

      $catalogSaldos = DB::table("fnzs_pagos_saldo_a_favor AS saldo")
      ->join("eegr_catalogo_proveedores AS catprv", "saldo.proveedor", "catprv.id")
      ->join("fnzs_pagos_pago AS pay", "saldo.pago_realizado", "pay.id")
      ->join("main_empresas AS emp", "catprv.administrador", "emp.id")
      ->where([
        "saldo.status_saldo" => TRUE, 
        "catprv.token_cat_proveedores" => $token_cat_proveedores, 
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("saldo.fecha_de_registro", "DESC")
      ->get();

      if ($catalogSaldos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaSaldos = array();

        foreach ($catalogSaldos as $vSal) {
          //da_te_default_timezone_set($vSal->zona_horaria);
          $queryApplicacionSaldo = DB::table("fnzs_pagos_saldo_a_favor_aplicaciones")->where("saldo_registrado", $vSal->uuid_saldo)->get();
          $saldoTotalAplicado = $queryApplicacionSaldo->sum('saldo_monto');
          $monto_real = $vSal->saldo_monto - $saldoTotalAplicado;
          $proveedor_saldo_total += $monto_real * (!empty($vSal->tipo_cambio) ? $vSal->tipo_cambio : 1.00);
          $row = array(
            "uuid_saldo" => $vSal->uuid_saldo,
            "fecha_de_registro" => gmdate('Y-m-d H:i:s', $vSal->fecha_de_registro),
            //"folio" => $JwtAuth->generarFolio($vSal->folio_anticipo), 
            "fecha_aplicacion" => count($queryApplicacionSaldo) == 1 ? gmdate('Y-m-d H:i:s', $queryApplicacionSaldo[0]->fecha_de_aplicacion) : '',
            "monto_saldo" => $vSal->saldo_monto,
            "monto_saldo_format" => "$" . number_format($vSal->saldo_monto * (!empty($vSal->tipo_cambio) ? $vSal->tipo_cambio : 1.00), $JwtAuth->getMonedaAPI($vSal->p_moneda), '.', ',') . ' ' . $vSal->p_moneda,
            "monto_real" => $monto_real,
            "monto_real_format" => "$" . number_format($monto_real * (!empty($vSal->tipo_cambio) ? $vSal->tipo_cambio : 1.00), $JwtAuth->getMonedaAPI($vSal->p_moneda), '.', ',') . ' ' . $vSal->p_moneda,
            "monto_aplicar" => 0,
            "monto_aplicar_format" => 0,
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
            "disponible" => $vSal->disponible ? true : false,
          );
          $listaSaldos[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'saldo_total' => "$" . number_format($proveedor_saldo_total, $JwtAuth->getMonedaAPI("MXN"), '.', ',') . ' MXN',
          'saldos_registrados' => $listaSaldos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function saldosProveedorDisponibleList(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $proveedor_saldo_total = 0;

      $catalogSaldos = DB::table("fnzs_pagos_saldo_a_favor AS saldo")
      ->join("eegr_catalogo_proveedores AS catprv", "saldo.proveedor", "catprv.id")
      ->join("fnzs_pagos_pago AS pay", "saldo.pago_realizado", "pay.id")
      ->join("main_empresas AS emp", "catprv.administrador", "emp.id")
      ->where([
        "saldo.status_saldo" => TRUE, 
        "saldo.disponible" => TRUE, 
        "catprv.token_cat_proveedores" => $token_cat_proveedores, 
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("saldo.fecha_de_registro", "DESC")
      ->get();

      if ($catalogSaldos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaSaldos = array();

        foreach ($catalogSaldos as $vSal) {
          //da_te_default_timezone_set($vSal->zona_horaria);
          $queryApplicacionSaldo = DB::table("fnzs_pagos_saldo_a_favor_aplicaciones")->where("saldo_registrado", $vSal->uuid_saldo)->get();
          $saldoTotalAplicado = $queryApplicacionSaldo->sum('saldo_monto');
          $monto_real = $vSal->saldo_monto - $saldoTotalAplicado;
          $proveedor_saldo_total += $monto_real * (!empty($vSal->tipo_cambio) ? $vSal->tipo_cambio : 1.00);
          $row = array(
            "uuid_saldo" => $vSal->uuid_saldo,
            "fecha_de_registro" => gmdate('Y-m-d H:i:s', $vSal->fecha_de_registro),
            //"folio" => $JwtAuth->generarFolio($vSal->folio_anticipo), 
            "fecha_aplicacion" => count($queryApplicacionSaldo) == 1 ? gmdate('Y-m-d H:i:s', $queryApplicacionSaldo[0]->fecha_de_aplicacion) : '',
            "monto_saldo" => $vSal->saldo_monto,
            "monto_saldo_format" => "$" . number_format($vSal->saldo_monto * (!empty($vSal->tipo_cambio) ? $vSal->tipo_cambio : 1.00), $JwtAuth->getMonedaAPI($vSal->p_moneda), '.', ',') . ' ' . $vSal->p_moneda,
            "monto_real" => $monto_real,
            "monto_real_format" => "$" . number_format($monto_real * (!empty($vSal->tipo_cambio) ? $vSal->tipo_cambio : 1.00), $JwtAuth->getMonedaAPI($vSal->p_moneda), '.', ',') . ' ' . $vSal->p_moneda,
            "monto_aplicar" => 0,
            "monto_aplicar_format" => 0,
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
          );
          $listaSaldos[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'saldo_total' => "$" . number_format($proveedor_saldo_total, $JwtAuth->getMonedaAPI("MXN"), '.', ',') . ' MXN',
          'saldos_registrados' => $listaSaldos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function createCuentaContableProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_proveedor = $request->input('token_proveedor');
      
      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      $selectProv = DB::select(
        "SELECT catprov.id FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
        [$token_proveedor, $empresa, $usuario]
      );

      $folioCuentaCont = DB::select("SELECT IF (max(cuent.folio) IS NOT NULL,(max(cuent.folio)+1),1) AS folio FROM cuentas_contables AS cuent JOIN main_empresas AS emp 
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE cuent.empresa = emp.id AND emp.empresa_token = ?
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

      $token_cuenta_contable = $JwtAuth->encriptarToken(
        $empresa,
        $usuario,
        $token_proveedor,
        $folioCuentaCont[0]->folio
      );

      $creaCuentaCont = new CuentasContablesModelo();
      $creaCuentaCont->token_cuenta_contable = $token_cuenta_contable;
      $creaCuentaCont->fecha_alta  = time();
      $creaCuentaCont->folio = $folioCuentaCont[0]->folio;
      $creaCuentaCont->proveedor = $selectProv[0]->id;
      $creaCuentaCont->cliente = NULL;
      $creaCuentaCont->contable = NULL;
      $creaCuentaCont->act_pas_cap = NULL;
      $creaCuentaCont->al_pl = NULL;
      $creaCuentaCont->acum = NULL;
      $creaCuentaCont->empresa = $selectEmp[0]->id;
      $savednewCuentaCont = $creaCuentaCont->save();

      if ($savednewCuentaCont) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Cuenta contable registrada'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cuenta contable no registrada, intente nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaRfcProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'rfc_generico' => 'required|string',
      'rfc_prov' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_proveedor = $request->input('token_proveedor');
      $rfc_generico = $request->input('rfc_generico');
      $rfc_prov = $request->input('rfc_prov');

      $patronRfc = '/[aA0-zZ9]/';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
        WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", 
        [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $validateRfc = 'false';
      $valida_rfc_generico = isset($rfc_generico) && !empty($rfc_generico) && preg_match($patronRfc, $rfc_generico);
      $valida_rfc_prov = isset($rfc_prov) && !empty($rfc_prov) && preg_match($patronRfc, $rfc_prov);

      if ($valida_rfc_generico && $valida_rfc_prov) {

        if (strlen($rfc_generico) == 13) {
          if (strlen($rfc_prov) == 13) {
            $validateRfc = 'true';
          } else {
            $validateRfc = 'false';
          }
        } else if (strlen($rfc_generico) == 12) {
          if (strlen($rfc_prov) == 12) {
            $validateRfc = 'true';
          } else {
            $validateRfc = 'false';
          }
        }

        if ($validateRfc == 'true') {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("personas AS people", "catprov.proveedor", "=", "people.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'people.rfc' => $JwtAuth->encriptar($rfc_prov),
              )
            );

          if ($updatePaterno) {
            $folio_db_prov = DB::select("SELECT folio,post_folio FROM eegr_catalogo_proveedores
                WHERE token_cat_proveedores = ?", [$token_proveedor]);
            if ($folio_db_prov[0]->post_folio == NULL) {
              $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
            } else {
              $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
            }

            $JwtAuth->insertBitacoraActividad(
              'egresos',
              'catalogos',
              'proveedores',
              $folio_prov,
              'registro de rfc de proveedor',
              $empresa,
              $usuario
            );
            $JwtAuth->insertBitacoraProv(
              $token_proveedor,
              'registro de rfc de proveedor',
              $empresa,
              $usuario
            );

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Rfc del proveedor actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Rfc del proveedor no fue actualizado, intente nuevamente o comuniquese a soporte para resolver este problema'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en rfc de su proveedor, revise su información o comuniquese a soporte'
          );
        }
      } else {
        if (!isset($rfc_generico) || empty($rfc_generico) || !preg_match($patronRfc, $rfc_generico)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en rfc generico de su proveedor, revise su información o comuniquese a soporte'
          );
        }
        if (!isset($rfc_prov) || empty($rfc_prov) || !preg_match($patronRfc, $rfc_prov)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en rfc de su proveedor, revise su información o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaIdTaxProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'tax_id_prov' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $tax_id_prov = $request->input('tax_id_prov');
      $patronRfc = '/[aA0-zZ9]/';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      if (isset($tax_id_prov) && !empty($tax_id_prov) && preg_match($patronRfc, $tax_id_prov)) {
        $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("personas AS people", "catprov.proveedor", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'people.tax_id' => $JwtAuth->encriptar($tax_id_prov),
            )
          );

        if ($updatePaterno) {
          $folio_db_prov = DB::select("SELECT folio,post_folio FROM eegr_catalogo_proveedores
            WHERE token_cat_proveedores = ?", [$token_proveedor]);
          if ($folio_db_prov[0]->post_folio == NULL) {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
          } else {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
          }

          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'registro de IDTax de proveedor',
            $empresa,
            $usuario
          );
          $JwtAuth->insertBitacoraProv(
            $token_proveedor,
            'registro de IDTax de proveedor',
            $empresa,
            $usuario
          );

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'IDTax del proveedor actualizado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'IDTax del proveedor no fue actualizado, intente nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Error en rfc de su proveedor, revise su información o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaGeneralesPF(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'paternoPersonales' => 'nullable|string',
      'maternoPersonales' => 'nullable|string',
      'nombrePersonales' => 'nullable|string',
      'nombreComPersonales' => 'nullable|string',
      'curpTaxPersonales' => 'nullable|string',
      'selectPaisPersonales' => 'nullable|string',
      'sitWebPersonales' => 'nullable|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $paternoPersonales = $request->input('paternoPersonales');
      $maternoPersonales = $request->input('maternoPersonales');
      $nombrePersonales = $request->input('nombrePersonales');
      $nombreComPersonales = $request->input('nombreComPersonales');
      $curpTaxPersonales = $request->input('curpTaxPersonales');
      $selectPaisPersonales = $request->input('selectPaisPersonales');
      $sitWebPersonales = $request->input('sitWebPersonales');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      if (
        isset($paternoPersonales) && !empty($paternoPersonales) && preg_match($patron, $paternoPersonales) &&
        isset($maternoPersonales) && !empty($maternoPersonales) && preg_match($patron, $maternoPersonales) &&
        isset($nombrePersonales) && !empty($nombrePersonales) && preg_match($patron, $nombrePersonales)
      ) {

        if (isset($nombreComPersonales) && !empty($nombreComPersonales) && preg_match($patron, $nombreComPersonales)) {
          $nameComPersonales = $JwtAuth->encriptar($nombreComPersonales);
        } else {
          $nameComPersonales = NULL;
        }

        if (isset($curpTaxPersonales) && !empty($curpTaxPersonales) && preg_match($patron, $curpTaxPersonales)) {
          $curptaxPersonales = $JwtAuth->encriptar($curpTaxPersonales);
        } else {
          $curptaxPersonales = NULL;
        }

        if (isset($selectPaisPersonales) && !empty($selectPaisPersonales)) {
          $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$selectPaisPersonales]);
          $selectpaisPersonales = $selectPais[0]->id;
        } else {
          $selectpaisPersonales = NULL;
        }

        if (isset($sitWebPersonales) && !empty($sitWebPersonales) && preg_match($patronUrl, $sitWebPersonales)) {
          $sitwebPersonales = $JwtAuth->encriptar($sitWebPersonales);
        } else {
          $sitwebPersonales = NULL;
        }

        $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("personas AS people", "catprov.proveedor", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'people.paterno' => $JwtAuth->encriptar($paternoPersonales),
              'people.materno' => $JwtAuth->encriptar($maternoPersonales),
              'people.nombre' => $JwtAuth->encriptar($nombrePersonales),
              'people.nombre_com' => $nameComPersonales,
              'people.curp' => $curptaxPersonales,
              'people.nacionalidad' => $selectpaisPersonales,
              'people.sitio_web' => $sitwebPersonales,
            )
          );

        if ($updatePaterno) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Datos generales del proveedor actualizados'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Datos generales del proveedor no fueron actualizados, intente nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        if (!isset($paternoPersonales) || empty($paternoPersonales) || !preg_match($patron, $paternoPersonales)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en apellido paterno de su proveedor, revise su información o comuniquese a soporte'
          );
        }
        if (!isset($maternoPersonales) || empty($maternoPersonales) || !preg_match($patron, $maternoPersonales)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en apellido materno de su proveedor, revise su información o comuniquese a soporte'
          );
        }
        if (!isset($nombrePersonales) || empty($nombrePersonales) || preg_match($patron, $nombrePersonales)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en nombre de su proveedor, revise su información o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaGeneralesPM(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'empresaPersonales' => 'nullable|string',
      'nombreComPersonales' => 'nullable|string',
      'curpTaxPersonales' => 'nullable|string',
      'selectPaisPersonales' => 'nullable|string',
      'sitWebPersonales' => 'nullable|string',
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
      $token_proveedor = $request->input('token_proveedor');
      $empresaPersonales = $request->input('empresaPersonales');
      $nombreComPersonales = $request->input('nombreComPersonales');
      $curpTaxPersonales = $request->input('curpTaxPersonales');
      $selectPaisPersonales = $request->input('selectPaisPersonales');
      $sitWebPersonales = $request->input('sitWebPersonales');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      if (isset($empresaPersonales) && !empty($empresaPersonales) && preg_match($patron, $empresaPersonales)) {

        if (isset($nombreComPersonales) && !empty($nombreComPersonales) && preg_match($patron, $nombreComPersonales)) {
          $nameComPersonales = $JwtAuth->encriptar($nombreComPersonales);
        } else {
          $nameComPersonales = NULL;
        }

        if (isset($curpTaxPersonales) && !empty($curpTaxPersonales) && preg_match($patron, $curpTaxPersonales)) {
          $curptaxPersonales = $JwtAuth->encriptar($curpTaxPersonales);
        } else {
          $curptaxPersonales = NULL;
        }

        if (isset($selectPaisPersonales) && !empty($selectPaisPersonales)) {
          $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$selectPaisPersonales]);
          $selectpaisPersonales = $selectPais[0]->id;
        } else {
          $selectpaisPersonales = NULL;
        }

        if (isset($sitWebPersonales) && !empty($sitWebPersonales) && preg_match($patronUrl, $sitWebPersonales)) {
          $sitwebPersonales = $JwtAuth->encriptar($sitWebPersonales);
        } else {
          $sitwebPersonales = NULL;
        }

        $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("personas AS people", "catprov.proveedor", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'people.nombre_extendido' => $JwtAuth->encriptar($empresaPersonales),
              'people.nombre_com' => $nameComPersonales,
              'people.curp' => $curptaxPersonales,
              'people.nacionalidad' => $selectpaisPersonales,
              'people.sitio_web' => $sitwebPersonales,
            )
          );

        if ($updatePaterno) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Datos generales del proveedor actualizados'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Datos generales del proveedor no fueron actualizados, intente nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        if (!isset($empresaPersonales) || empty($empresaPersonales) || preg_match($patron, $empresaPersonales)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en razön social/empresa de su proveedor, revise su información o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaRedes(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'redes_sociales' => 'required|array'
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
      $token_proveedor = $request->input('token_proveedor');
      $redes_sociales = $request->input('redes_sociales');
      
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $arrayredesSoc = $redes_sociales;
      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($arrayredesSoc) && !empty($arrayredesSoc)) {
        for ($i = 0; $i < count($arrayredesSoc); $i++) {
          if ($arrayredesSoc[$i] == '') {
            $countarrayredesSoc++;
            $countRedesVacias++;
          } else {
            if (preg_match($patronUrl, $arrayredesSoc[$i])) {
              $countarrayredesSoc++;
            } else {
              break;
              if ($i == 0) {
                $red_soc_error = 'facebook';
              } else if ($i == 1) {
                $red_soc_error = 'twitter';
              } else if ($i == 2) {
                $red_soc_error = 'instagram';
              } else {
                $red_soc_error = 'youtube';
              }

              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'codeProvGenError' => 'redSoc',
                'message' => 'Error en red social ' . $red_soc_error . ' de su proveedor'
              );
            }
          }
        }
      }

      if ($countarrayredesSoc == count($arrayredesSoc)) {
        if ($countRedesVacias == $countarrayredesSoc) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("personas AS people", "catprov.proveedor", "=", "people.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'people.redes_soc' => NULL,
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'las redes sociales de su proveedor han sido actualizadas'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'nomcom',
              'message' => 'las redes sociales de su proveedor no fueron actualizadas, intentelo nuevamente o comuniquese a soporte para resolver este problema'
            );
          }
        }

        if ($countRedesVacias != $countarrayredesSoc) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("personas AS people", "catprov.proveedor", "=", "people.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'people.redes_soc' => $JwtAuth->encriptar(json_encode($arrayredesSoc)),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'las redes sociales de su proveedor han sido actualizadas'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'nomcom',
              'message' => 'las redes sociales de su proveedor no fueron actualizadas, intentelo nuevamente o comuniquese a soporte para resolver este problema'
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletePersonalProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal)) {
        $countPers = DB::select(
          "SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_proveedor, $empresa]
        );

        $idenfiticaPers = DB::select(
          "SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_personal, $token_proveedor, $empresa]
        );

        if (count($idenfiticaPers) == 1) {
          if (count($countPers) > 1) {
            $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
              ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
              ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
              ->where([
                'pers.pers_token' => $token_personal,
                'catprov.token_cat_proveedores' => $token_proveedor,
                'emp.empresa_token' => $empresa,
              ])
              ->limit(1)->update(
                array(
                  'pers.status' => FALSE,
                  'pers.fecha_delete' => time(),
                )
              );

            if ($updatePaterno) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'personal eliminado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'personal identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'personal invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function ingresaPersonalProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'list_contacto' => 'required|array'
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
      $token_proveedor = $request->input('token_proveedor');
      $arrayContactoPersonal = $request->input('list_contacto');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $contadorContacto = 0;

      for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
        if (
          preg_match($patron, $arrayContactoPersonal[$i]['paterno']) && preg_match($patron, $arrayContactoPersonal[$i]['materno']) &&
          preg_match($patron, $arrayContactoPersonal[$i]['nombre']) && preg_match($patron, $arrayContactoPersonal[$i]['area']) &&
          preg_match($patron, $arrayContactoPersonal[$i]['cargo'])
        ) {

          if (count($arrayContactoPersonal[$i]['emails']) == 0 && count($arrayContactoPersonal[$i]['telefonos']) == 0) {
            $contadorContacto++;
          } else {
            $contadorMails = 0;
            $personalEmails = $arrayContactoPersonal[$i]['emails'];
            for ($m = 0; $m < count($personalEmails); $m++) {
              if (preg_match($patronMail, $personalEmails[$m])) {
                $contadorMails++;
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'positionErrorCode' => $m,
                  'message' => 'Error en correo electrónico de personal de contacto'
                );
                break;
              }
            }

            $contadorTelefonos = 0;
            $personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
            for ($t = 0; $t < count($personalTelefonos); $t++) {
              if (
                preg_match($patron, $personalTelefonos[$t]['icon']) &&
                preg_match($patron, $personalTelefonos[$t]['etiqueta']) &&
                preg_match($patronNum, $personalTelefonos[$t]['telefono']) &&
                preg_match($patronCpostal, $personalTelefonos[$t]['extension'])
              ) {
                $contadorTelefonos++;
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'positionErrorCode' => $m,
                  'message' => 'Error en teléfono de personal de contacto'
                );
                break;
              }
            }

            if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
              $contadorContacto++;
            }
          }
        } else {
          if (!preg_match($patron, $arrayContactoPersonal[$i]['paterno'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'positionErrorCode' => $i,
              'message' => 'Error en apellido paterno de personal de contacto'
            );
          }
          if (!preg_match($patron, $arrayContactoPersonal[$i]['materno'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'positionErrorCode' => $i,
              'message' => 'Error en apellido materno de personal de contacto'
            );
          }
          if (!preg_match($patron, $arrayContactoPersonal[$i]['nombre'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'positionErrorCode' => $i,
              'message' => 'Error en nombre de personal de contacto'
            );
          }
          if (!preg_match($patron, $arrayContactoPersonal[$i]['area'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'positionErrorCode' => $i,
              'message' => 'Error en area de trabajo de personal de contacto'
            );
          }
          if (!preg_match($patron, $arrayContactoPersonal[$i]['cargo'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'positionErrorCode' => $i,
              'message' => 'Error en cargo de trabajo de personal de contacto'
            );
          }
        }
      }

      if ($contadorContacto == count($arrayContactoPersonal)) {
        $selectProvCat = DB::select("SELECT id,folio,post_folio FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$token_proveedor]);
        $contadorInsertContacto = 0;
        $fechaAlta = time();
        for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
          $contArea = $JwtAuth->encriptar($arrayContactoPersonal[$i]['area']);

          $insertArea = DB::table('area')->insert(array("areaemp" => $contArea));
          $selectNewArea = DB::select("SELECT id FROM area WHERE areaemp = ?", [$contArea]);

          $contCargo = $JwtAuth->encriptar($arrayContactoPersonal[$i]['cargo']);
          $insertCargo = DB::table('cargo')->insert(array(
            "cargo" => $contCargo,
            "area" => $selectNewArea[0]->id,
          ));
          $selectNewCargo = DB::select("SELECT id FROM cargo WHERE cargo = ? AND area = ?", [$contCargo, $selectNewArea[0]->id]);

          $contApePaterno = $JwtAuth->encriptar($arrayContactoPersonal[$i]['paterno']);
          $contApeMaterno = $JwtAuth->encriptar($arrayContactoPersonal[$i]['materno']);
          $contNombre = $JwtAuth->encriptar($arrayContactoPersonal[$i]['nombre']);

          $tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' .
            $contApePaterno . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);

          $insertapersonalpersonas = DB::table('personas')
            ->insert(array(
              "token_personas" => $tokenPersonasPersonal,
              "paterno" => $contApePaterno,
              "materno" => $contApeMaterno,
              "nombre" => $contNombre,
            ));

          $selectpersonalpersonas = DB::select(
            "SELECT id FROM personas WHERE 
            token_personas = ? AND paterno = ? AND materno = ? AND nombre = ?",
            [$tokenPersonasPersonal, $contApePaterno, $contApeMaterno, $contNombre]
          );

          $tokenPersonal = $JwtAuth->encriptarToken($arrayContactoPersonal[$i]['paterno'] . '/' . $arrayContactoPersonal[$i]['nombre'] .
            '/' . $arrayContactoPersonal[$i]['materno'] . '/' . $selectEmp[0]->id . '/' . $contArea);

          $insertapersonal = DB::table('personal')
            ->insert(array(
              "pers_token" => $tokenPersonal,
              "fecha_alta_pers" => $fechaAlta,
              "folio_pers" => $i,
              "area" => $selectNewArea[0]->id,
              "cargo" => $selectNewCargo[0]->id,
              "personal" => $selectpersonalpersonas[0]->id,
              "usuario" => NULL,
              "cat_clientes" => NULL,
              "cat_proveedores" => $selectProvCat[0]->id,
              "status" => TRUE,
              "fecha_delete" => NULL
            ));

          $selectpersonal = DB::select("SELECT id FROM personal WHERE pers_token = ?", [$tokenPersonal]);

          $countInsertTel = 0;
          $personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
          if (count($personalTelefonos) != 0) {
            for ($t = 0; $t < count($personalTelefonos); $t++) {
              $contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono']);

              if ($personalTelefonos[$t]['extension'] == '') {
                $contExtension = NULL;
              } else {
                $contExtension = $JwtAuth->encriptar($personalTelefonos[$t]['extension']);
              }

              $tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);

              $insertatelefonos_personal = DB::table('telefonos_personal')
                ->insert(array(
                  "token_telefono" => $tokentel,
                  "personal" => $selectpersonal[0]->id,
                  "icono" => $personalTelefonos[$t]['icon'],
                  "etiqueta" => $personalTelefonos[$t]['etiqueta'],
                  "telefono" => $contTelefono,
                  "extension" => $contExtension,
                  "status_telefono" => TRUE,
                  "fecha_delete_tel" => NULL,
                ));

              if ($insertatelefonos_personal) {
                $countInsertTel++;
              }
            }
          }

          $countInsertMails = 0;
          $personalEmails = $arrayContactoPersonal[$i]['emails'];
          if (count($personalEmails) != 0) {
            for ($m = 0; $m < count($personalEmails); $m++) {
              $contEmail = $JwtAuth->encriptar($personalEmails[$m]);
              $tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectpersonal[0]->id);

              $insertacorreos_personal = DB::table('correos_personal')
                ->insert(array(
                  "token_correo" => $tokenEmail,
                  "personal" => $selectpersonal[0]->id,
                  "correo" => $contEmail,
                  "status_correo" => TRUE,
                  "fecha_delete_correo" => NULL,
                ));
              if ($insertacorreos_personal) {
                $countInsertMails++;
              }
            }
          }

          if (
            $insertArea && $insertCargo && $insertapersonalpersonas && $insertapersonal &&
            $countInsertTel == count($personalTelefonos) && $countInsertMails == count($personalEmails)
          ) {
            $contadorInsertContacto++;
          }
        }

        if ($contadorInsertContacto == count($arrayContactoPersonal)) {
          if ($selectProvCat[0]->post_folio == NULL) {
            $folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
          } else {
            $folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
          }
          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'registro de personal para proveedor',
            $empresa,
            $usuario
          );
          $JwtAuth->insertBitacoraProv(
            $token_proveedor,
            'registro de personal para proveedor',
            $empresa,
            $usuario
          );
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'personal registrado'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'no concuerda la cantidad de información recibida'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaGeneralesPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'personal_cont_paterno' => 'nullable|string',
      'personal_cont_materno' => 'nullable|string',
      'personal_cont_nombre' => 'nullable|string',
      'personal_cont_area' => 'nullable|string',
      'personal_cont_cargo' => 'nullable|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $personal_cont_paterno = $request->input('personal_cont_paterno');
      $personal_cont_materno = $request->input('personal_cont_materno');
      $personal_cont_nombre = $request->input('personal_cont_nombre');
      $personal_cont_area = $request->input('personal_cont_area');
      $personal_cont_cargo = $request->input('personal_cont_cargo');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $validateInsertpersonal_cont_paterno = 'false';
      $validateInsertpersonal_cont_materno = 'false';
      $validateInsertpersonal_cont_nombre = 'false';
      $validateInsertpersonal_cont_area = 'false';
      $validateInsertpersonal_cont_cargo = 'false';

      if (isset($personal_cont_paterno) && !empty($personal_cont_paterno)) {
        if (preg_match($patron, $personal_cont_paterno)) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
            ->join("personas AS people", "pers.empleado_name", "=", "people.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->where([
              'pers.pers_token' => $token_personal,
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
            ])
            ->limit(1)->update(
              array(
                'people.paterno' => $JwtAuth->encriptar($personal_cont_paterno),
              )
            );

          if ($updatePaterno) {
            $validateInsertpersonal_cont_paterno = 'true';
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 200,
              'message' => 'No fue actualizado el apellido paterno del personal de su proveedor, comuniquese a soporte para resolver este problema'
            ]);
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'codeProvGenError' => 'nomcom',
            'message' => 'Error en apellido paterno del personal de su proveedor, revise su información o comuniquese a soporte'
          ]);
        }
      } else {
        $validateInsertpersonal_cont_paterno = 'true';
      }

      if (isset($personal_cont_materno) && !empty($personal_cont_materno)) {
        if (preg_match($patron, $personal_cont_materno)) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
            ->join("personas AS people", "pers.empleado_name", "=", "people.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->where([
              'pers.pers_token' => $token_personal,
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
            ])
            ->limit(1)->update(
              array(
                'people.materno' => $JwtAuth->encriptar($personal_cont_materno),
              )
            );

          if ($updatePaterno) {
            $validateInsertpersonal_cont_materno = 'true';
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'nomcom',
              'message' => 'No fue actualizado el apellido materno del personal de su proveedor, comuniquese a soporte para resolver este problema'
            ]);
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'codeProvGenError' => 'nomcom',
            'message' => 'Error en apellido materno del personal de su proveedor, revise su información o comuniquese a soporte'
          ]);
        }
      } else {
        $validateInsertpersonal_cont_materno = 'true';
      }

      if (isset($personal_cont_nombre) && !empty($personal_cont_nombre)) {
        if (preg_match($patron, $personal_cont_nombre)) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
            ->join("personas AS people", "pers.empleado_name", "=", "people.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->where([
              'pers.pers_token' => $token_personal,
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
            ])
            ->limit(1)->update(
              array(
                'people.nombre' => $JwtAuth->encriptar($personal_cont_nombre),
              )
            );

          if ($updatePaterno) {
            $validateInsertpersonal_cont_nombre = 'true';
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'nomcom',
              'message' => 'No fue actualizado el nombre del personal de su proveedor, comuniquese a soporte para resolver este problema'
            ]);
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'codeProvGenError' => 'nomcom',
            'message' => 'Error en nombre del personal de su proveedor, revise su información o comuniquese a soporte'
          ]);
        }
      } else {
        $validateInsertpersonal_cont_nombre = 'true';
      }

      if (isset($personal_cont_area) && !empty($personal_cont_area)) {
        if (preg_match($patron, $personal_cont_area)) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
            ->join("area AS areaemp", "pers.area", "=", "areaemp.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->where([
              'pers.pers_token' => $token_personal,
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
            ])
            ->limit(1)->update(
              array(
                'areaemp.areaemp' => $JwtAuth->encriptar($personal_cont_area),
              )
            );

          if ($updatePaterno) {
            $validateInsertpersonal_cont_area = 'true';
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'nomcom',
              'message' => 'No fue actualizada el área de trabajo del personal de su proveedor, comuniquese a soporte para resolver este problema'
            ]);
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'codeProvGenError' => 'nomcom',
            'message' => 'Error en área de trabajo del personal de su proveedor, revise su información o comuniquese a soporte'
          ]);
        }
      } else {
        $validateInsertpersonal_cont_area = 'true';
      }

      if (isset($personal_cont_cargo) && !empty($personal_cont_cargo)) {
        if (preg_match($patron, $personal_cont_cargo)) {
          $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
            ->join("cargo AS ocup", "pers.cargo", "=", "ocup.id")
            ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
            ->where([
              'pers.pers_token' => $token_personal,
              'catprov.token_cat_proveedores' => $token_proveedor,
              'emp.empresa_token' => $empresa,
            ])
            ->limit(1)->update(
              array(
                'ocup.cargo' => $JwtAuth->encriptar($personal_cont_cargo),
              )
            );

          if ($updatePaterno) {
            $validateInsertpersonal_cont_cargo = 'true';
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'nomcom',
              'message' => 'No fue actualizado el cargo de trabajo del personal de su proveedor, comuniquese a soporte para resolver este problema'
            ]);
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'codeProvGenError' => 'nomcom',
            'message' => 'Error en cargo de trabajo del personal de su proveedor, revise su información o comuniquese a soporte'
          ]);
        }
      } else {
        $validateInsertpersonal_cont_cargo = 'true';
      }

      if (
        $validateInsertpersonal_cont_paterno == 'true' && $validateInsertpersonal_cont_materno == 'true' &&
        $validateInsertpersonal_cont_nombre == 'true' && $validateInsertpersonal_cont_area == 'true' &&
        $validateInsertpersonal_cont_cargo == 'true'
      ) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'datos generales de su proveedor han sido actualizados'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nuevoTelefonoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'personal_etiqueta' => 'required|string',
      'personal_icon' => 'required|string',
      'personal_telefono' => 'required|numeric',
      'personal_extension' => 'nullable|numeric'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $personal_etiqueta = $request->input('personal_etiqueta');
      $personal_icon = $request->input('personal_icon');
      $personal_telefono = $request->input('personal_telefono');
      $personal_extension = $request->input('personal_extension');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';

      $valida_etiqueta = isset($personal_etiqueta) && !empty($personal_etiqueta) && preg_match($patron, $personal_etiqueta);
      $valida_icon = isset($personal_icon) && !empty($personal_icon) && preg_match($patron, $personal_icon);
      $valida_telefono = isset($personal_telefono) && !empty($personal_telefono) && preg_match($patronNum, $personal_telefono);
      if ($valida_etiqueta && $valida_icon && $valida_telefono) {
        $txtpersonal_telefono = $JwtAuth->encriptar($personal_telefono);
        $idenfiticaPers = DB::select("SELECT id FROM telefonos_personal WHERE telefono = ?", [$txtpersonal_telefono]);
        //echo count($idenfiticaPers);
        if (count($idenfiticaPers) == 0) {
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
          $validateInsertpersonal_extension = 'true';
          $txtpersonal_extension = '';

          if (isset($personal_extension)) {
            if (!empty($personal_extension)) {
              if (preg_match($patronNum, $personal_extension)) {
                $validateInsertpersonal_extension = 'true';
                $txtpersonal_extension = $JwtAuth->encriptar($personal_extension);
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Error en extension del telefono del personal de su proveedor, revise su información o comuniquese a soporte'
                );
              }
            } else {
              $validateInsertpersonal_extension = 'true';
            }
          } else {
            $validateInsertpersonal_extension = 'true';
          }

          if ($validateInsertpersonal_extension == 'true') {
            $selectPers = DB::select(
              "SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $tokenPerTel = $JwtAuth->encriptarToken($token_personal . $token_proveedor .
              $txtpersonal_telefono, $txtpersonal_extension);
            $insertatelefonos_personal = DB::table('telefonos_personal')
              ->insert(array(
                "personal" => $selectPers[0]->id,
                "token_telefono" => $tokenPerTel,
                "icono" => $personal_icon,
                "etiqueta" => $personal_etiqueta,
                "telefono" => $txtpersonal_telefono,
                "extension" => $txtpersonal_extension,
                "status_telefono" => TRUE,
                "fecha_delete_tel" => ''
              ));
            if ($insertatelefonos_personal) {

              $folio_db_prov = DB::select("SELECT folio,post_folio FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$token_proveedor]);

              if ($folio_db_prov[0]->post_folio == NULL) {
                $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
              } else {
                $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
              }

              $JwtAuth->insertBitacoraActividad(
                'egresos',
                'catalogos',
                'proveedores',
                $folio_prov,
                'registro de telefono para personal de proveedor',
                $empresa,
                $usuario
              );
              $JwtAuth->insertBitacoraProv(
                $token_proveedor,
                'registro de telefono para personal de proveedor',
                $empresa,
                $usuario
              );

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'el nuevo telefono del personal de su proveedor ha sido registrado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No fue registrado el nuevo telefono del personal de su proveedor, comuniquese a soporte para resolver este problema'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'El telefono ' . $personal_telefono . ' ya ha sido registrado enteriormente, revise su información o comuniquese a soporte'
          );
        }
      } else {
        if (!$valida_etiqueta) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en etiqueta del telefono que intenta registrar, revise su información o comuniquese a soporte'
          );
        }
        if (!$valida_icon) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en icono del telefono que intenta registrar, revise su información o comuniquese a soporte'
          );
        }
        if (!$valida_telefono) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en telefono del personal de su proveedor, revise su información o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaTelefonoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_telefono' => 'required|string',
      'personal_etiqueta' => 'required|string',
      'personal_icon' => 'required|string',
      'personal_telefono' => 'required|numeric',
      'personal_extension' => 'nullable|numeric'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_telefono = $request->input('token_telefono');
      $personal_etiqueta = $request->input('personal_etiqueta');
      $personal_icon = $request->input('personal_icon');
      $personal_telefono = $request->input('personal_telefono');
      $personal_extension = $request->input('personal_extension');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';

      $valida_etiqueta = isset($personal_etiqueta) && !empty($personal_etiqueta) && preg_match($patron, $personal_etiqueta);
      $valida_icon = isset($personal_icon) && !empty($personal_icon) && preg_match($patron, $personal_icon);
      $valida_telefono = isset($personal_telefono) && !empty($personal_telefono) && preg_match($patronNum, $personal_telefono);

      if ($valida_etiqueta && $valida_icon && $valida_telefono) {
        $txtpersonal_telefono = $JwtAuth->encriptar($personal_telefono);
        if (isset($personal_extension) && !empty($personal_extension)) {
          if (preg_match($patron, $personal_extension)) {
            $personal_extension = $JwtAuth->encriptar($personal_extension);
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en extension del telefono del personal de su proveedor, revise su información o comuniquese a soporte'
            );
          }
        } else {
          $personal_extension = '';
        }

        $updateTelefono = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
          ->join("telefonos_personal AS telpers", "pers.id", "=", "telpers.empleado_name")
          ->join("personas AS people", "pers.empleado_name", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'pers.pers_token' => $token_personal,
            'telpers.token_telefono' => $token_telefono,
            'emp.empresa_token' => $empresa,
          ])
          ->limit(1)->update(
            array(
              'telpers.icono' => $personal_icon,
              'telpers.etiqueta' => $personal_etiqueta,
              'telpers.telefono' => $txtpersonal_telefono,
              'telpers.extension' => $personal_extension,
            )
          );

        if ($updateTelefono) {
          $folio_db_prov = DB::select("SELECT folio,post_folio FROM eegr_catalogo_proveedores
            WHERE token_cat_proveedores = ?", [$token_proveedor]);

          if ($folio_db_prov[0]->post_folio == NULL) {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
          } else {
            $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
          }

          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'actualización de telefono para personal de proveedor',
            $empresa,
            $usuario
          );
          $JwtAuth->insertBitacoraProv(
            $token_proveedor,
            'actualización de telefono para personal de proveedor',
            $empresa,
            $usuario
          );

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'telefono del personal de su proveedor han sido actualizados'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'No fue actualizado el telefono del personal de su proveedor, comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        if (!isset($personal_etiqueta) || empty($personal_etiqueta) || !preg_match($patron, $personal_etiqueta)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en etiqueta del telefono que intenta actualizar, revise su información o comuniquese a soporte'
          );
        }
        if (!isset($personal_icon) || empty($personal_icon) || !preg_match($patron, $personal_icon)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en icono del telefono que intenta actualizar, revise su información o comuniquese a soporte'
          );
        }
        if (!isset($personal_telefono) || empty($personal_telefono) || !preg_match($patronNum, $personal_telefono)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en telefono del personal de su proveedor, revise su información o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function eliminaTelefonoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_telefono' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_telefono = $request->input('token_telefono');

      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal) && isset($token_telefono) && !empty($token_telefono)) {
        $countPers = DB::select(
          "SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE telpers.status_telefono = TRUE AND telpers.empleado_name = pers.id AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_proveedor, $empresa]
        );

        $idenfiticaPers = DB::select(
          "SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE telpers.token_telefono = ? AND telpers.status_telefono = TRUE AND telpers.empleado_name = pers.id AND pers.pers_token = ? 
          AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_telefono, $token_personal, $token_proveedor, $empresa]
        );

        if (count($idenfiticaPers) == 1) {
          if (count($countPers) > 1) {
            $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
              ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
              ->join("telefonos_personal AS telpers", "pers.id", "=", "telpers.empleado_name")
              ->join("personas AS people", "pers.empleado_name", "=", "people.id")
              ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
              ->where([
                'catprov.token_cat_proveedores' => $token_proveedor,
                'pers.pers_token' => $token_personal,
                'telpers.token_telefono' => $token_telefono,
                'emp.empresa_token' => $empresa,
              ])
              ->limit(1)->update(
                array(
                  'telpers.status_telefono' => FALSE,
                  'telpers.fecha_delete_tel' => time(),
                )
              );

            if ($updatePaterno) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'telefono eliminado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'telefono del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'telefono identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'telefono invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restartTelefonoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_telefono' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_telefono = $request->input('token_telefono');
      
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal) && isset($token_telefono) && !empty($token_telefono)) {
        $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
          ->join("telefonos_personal AS telpers", "pers.id", "=", "telpers.empleado_name")
          ->join("personas AS people", "pers.empleado_name", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'pers.pers_token' => $token_personal,
            'telpers.token_telefono' => $token_telefono,
            'emp.empresa_token' => $empresa,
          ])
          ->limit(1)->update(
            array(
              'telpers.status_telefono' => TRUE,
              'telpers.fecha_delete_tel' => '',
            )
          );

        if ($updatePaterno) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'telefono restaurado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'telefono del personal de su proveedor no fue restaurado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'telefono invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaTelefonoPersonalPermanente(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_telefono' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_telefono = $request->input('token_telefono');
      
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal) && isset($token_telefono) && !empty($token_telefono)) {
        $idenfiticaPers = DB::select(
          "SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE telpers.token_telefono = ? AND telpers.status_telefono = FALSE AND telpers.empleado_name = pers.id AND pers.pers_token = ? 
          AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_telefono, $token_personal, $token_proveedor, $empresa]
        );

        if (count($idenfiticaPers) == 1) {
          $deleteTelefono = DB::select(
            "DELETE telpers.* FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers 
            JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = telpers.empleado_name 
            AND telpers.token_telefono = ? AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
            AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
            [$token_telefono, $token_personal, $token_proveedor, $empresa]
          );

          $selectProhne = DB::select(
            "SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers 
            JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = telpers.empleado_name 
            AND telpers.token_telefono = ? AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
            AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
            [$token_telefono, $token_personal, $token_proveedor, $empresa]
          );

          if (count($selectProhne) == 0) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'telefono eliminado permanentemente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'telefono del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'telefono identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'telefono invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nuevoCorreoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'personal_correo' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $personal_correo = $request->input('personal_correo');
      
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
        WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", 
        [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $valida_correo = isset($personal_correo) && !empty($personal_correo) && preg_match($patronMail, $personal_correo);
      $txtpersonal_correo = $JwtAuth->encriptar($personal_correo);
      $idenfiticaPers = DB::select("SELECT id FROM correos_personal WHERE correo = ?", [$txtpersonal_correo]);

      if ($valida_correo && count($idenfiticaPers) == 0) {
        $selectPers = DB::select(
          "SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_personal, $token_proveedor, $empresa]
        );

        $tokenPerMail = $JwtAuth->encriptarToken($token_personal . $token_proveedor . $txtpersonal_correo);
        $insertacorreos_personal = DB::table('correos_personal')
          ->insert(array(
            "personal" => $selectPers[0]->id,
            "token_correo" => $tokenPerMail,
            "correo" => $txtpersonal_correo,
            'status_correo' => TRUE,
            'fecha_delete_correo' => '',
          ));

        if ($insertacorreos_personal) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'el nuevo correo electrónico del personal de su proveedor ha sido registrado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'No fue registrado el nuevo correo electrónico del personal de su proveedor, comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        if (!$valida_correo) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico del personal de su proveedor, revise su información o comuniquese a soporte'
          );
        }

        if (count($idenfiticaPers) > 0) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'El correo electrónico ' . $personal_correo . ' ya ha sido registrado enteriormente, revise su información o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaCorreoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_correo' => 'required|string',
      'personal_correo' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_correo = $request->input('token_correo');
      $personal_correo = $request->input('personal_correo');

      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';

      $OKCorreo = isset($personal_correo) && !empty($personal_correo) && preg_match($patronMail, $personal_correo);
      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      if ($OKCorreo) {
        $updateCorreo = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
          ->join("correos_personal AS mailpers", "pers.id", "=", "mailpers.empleado_name")
          ->join("personas AS people", "pers.empleado_name", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'pers.pers_token' => $token_personal,
            'mailpers.token_correo' => $token_correo,
            'emp.empresa_token' => $empresa,
          ])
          ->limit(1)->update(
            array(
              'mailpers.correo' => $JwtAuth->encriptar(strtolower($personal_correo)),
            )
          );

        if ($updateCorreo) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'correo electrónico del personal de su proveedor ha sido actualizado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'No fue actualizado el correo electrónico del personal de su proveedor, comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Error en correo electrónico del personal de su proveedor, revise su información o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaCorreoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_correo' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_correo = $request->input('token_correo');
      
      $OKPersonal = isset($token_personal) && !empty($token_personal);
      $OKCorreo = isset($token_correo) && !empty($token_correo);

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      if ($OKPersonal && $OKCorreo) {
        $countPers = DB::select(
          "SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE mailpers.status_correo = TRUE AND mailpers.empleado_name = pers.id AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_proveedor, $empresa]
        );

        $idenfiticaPers = DB::select(
          "SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND mailpers.status_correo = TRUE AND mailpers.empleado_name = pers.id AND pers.pers_token = ? 
          AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_correo, $token_personal, $token_proveedor, $empresa]
        );

        if (count($idenfiticaPers) == 1) {
          if (count($countPers) > 1) {
            $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
              ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
              ->join("correos_personal AS mailpers", "pers.id", "=", "mailpers.empleado_name")
              ->join("personas AS people", "pers.empleado_name", "=", "people.id")
              ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
              ->where([
                'catprov.token_cat_proveedores' => $token_proveedor,
                'pers.pers_token' => $token_personal,
                'mailpers.token_correo' => $token_correo,
                'emp.empresa_token' => $empresa,
              ])
              ->limit(1)->update(
                array(
                  'mailpers.status_correo' => FALSE,
                  'mailpers.fecha_delete_correo' => time(),
                )
              );

            if ($updatePaterno) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'correo electrónico eliminado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'correo electrónico del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'correo electrónico identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'correo electrónico invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restartCorreoPersonal(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_correo' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_correo = $request->input('token_correo');

      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';
      
      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal) && isset($token_correo) && !empty($token_correo)) {
        $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
          ->join("correos_personal AS mailpers", "pers.id", "=", "mailpers.empleado_name")
          ->join("personas AS people", "pers.empleado_name", "=", "people.id")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_proveedor,
            'pers.pers_token' => $token_personal,
            'mailpers.token_correo' => $token_correo,
            'emp.empresa_token' => $empresa,
          ])
          ->limit(1)->update(
            array(
              'mailpers.status_correo' => TRUE,
              'mailpers.fecha_delete_correo' => '',
            )
          );

        if ($updatePaterno) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'correo electrónico restaurado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'correo electrónico del personal de su proveedor no fue restaurado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'correo electrónico invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaCorreoPersonalPermanente(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string',
      'token_correo' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      $token_correo = $request->input('token_correo');

      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal) && isset($token_correo) && !empty($token_correo)) {
        $countPers = DB::select(
          "SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE mailpers.status_telefono = TRUE AND mailpers.empleado_name = pers.id AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_proveedor, $empresa]
        );

        $idenfiticaPers = DB::select(
          "SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND mailpers.status_telefono = TRUE AND mailpers.empleado_name = pers.id AND pers.pers_token = ? 
          AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_correo, $token_personal, $token_proveedor, $empresa]
        );

        if (count($idenfiticaPers) == 1) {
          $deleteCorreo = DB::select(
            "DELETE mailpers.* FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers 
            JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND 
            pers.id = mailpers.empleado_name AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
            AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
            [$token_correo, $token_personal, $token_proveedor, $empresa]
          );

          $selectCorreo = DB::select(
            "SELECT mailpers.* FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers 
            JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND 
            pers.id = mailpers.empleado_name AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
            AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
            [$token_correo, $token_personal, $token_proveedor, $empresa]
          );

          if (count($selectCorreo) == 0) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'correo electrónico eliminado permanentemente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'correo electrónico del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'correo electrónico identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'correo electrónico invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restartPersonalProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal)) {
        $updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
          ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
          ->where([
            'pers.pers_token' => $token_personal,
            'catprov.token_cat_proveedores' => $token_proveedor,
            'emp.empresa_token' => $empresa,
          ])
          ->limit(1)->update(
            array(
              'pers.status' => TRUE,
              'pers.fecha_delete' => '',
            )
          );

        if ($updatePaterno) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'personal restaurado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'personal de su proveedor no fue restaurado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'personal invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletePersonalProvPermanente(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_proveedor' => 'required|string',
      'token_personal' => 'required|string'
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
      $token_proveedor = $request->input('token_proveedor');
      $token_personal = $request->input('token_personal');
      
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $countarrayredesSoc = 0;
      $countRedesVacias = 0;

      if (isset($token_personal) && !empty($token_personal)) {
        $countPers = DB::select(
          "SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_proveedor, $empresa]
        );

        $idenfiticaPers = DB::select(
          "SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
          JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
          AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
          [$token_personal, $token_proveedor, $empresa]
        );

        if (count($idenfiticaPers) == 1) {
          if (count($countPers) > 1) {

            $deletePersonas = DB::select(
              "DELETE people.* FROM personas AS people JOIN vhum_empleados_catalogo AS pers 
              JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE people.id = pers.empleado_name AND 
              pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $deleteArea = DB::select(
              "DELETE are.* FROM area AS aree JOIN vhum_empleados_catalogo AS pers 
              JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE are.id = pers.area AND 
              pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $deleteCargo = DB::select(
              "DELETE ocup.* FROM cargo AS ocup JOIN vhum_empleados_catalogo AS pers 
              JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE ocup.id = pers.cargo AND 
              pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $deleteTelefono = DB::select(
              "DELETE telpers.* FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers 
              JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = telpers.empleado_name AND 
              pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $deleteCorreo = DB::select(
              "DELETE mailpers.* FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers 
              JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = mailpers.empleado_name AND 
              pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $deletePersonal = DB::select(
              "DELETE pers.* FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp WHERE pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            $selectPersonal = DB::select(
              "SELECT pers.* FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp WHERE pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
              AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
              [$token_personal, $token_proveedor, $empresa]
            );

            if (count($selectPersonal)) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'personal eliminado permanentemente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'personal identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'personal invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updatecontanciafiscalsitload(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');

      $selectFolio = DB::select("SELECT catprov.const_sit_fiscal,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
        AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_cat_proveedores, $empresa, $usuario]);

      $filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

      if (!file_exists(storage_path("/root/" . $filepath))) {
        Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
      }

      if (file_exists($request->file('imagenAltaPdfFiscal'))) {
        //eliminar imagen anterior
        $nombre_imagen = $request->file('imagenAltaPdfFiscal')->getClientOriginalName();
        Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfFiscal'), $nombre_imagen);

        $updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'catprov.const_sit_fiscal' => $JwtAuth->encriptar($nombre_imagen),
            )
          );

        if ($updateImg) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Constancia de situación fiscal actualizada'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Constancia de situación fiscal no ha sido actualizada, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updatecontanciafiscalsitbase64(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');
      $token_cat_proveedores = $request->input('token_cat_proveedores');

      $selectFolio = DB::select("SELECT catprov.const_sit_fiscal,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
        AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_cat_proveedores, $empresa, $usuario]);

      $filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

      if (!file_exists(storage_path("/root/" . $filepath))) {
        Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
      }

      if (file_exists($request->file('imagenAltaPdfFiscal'))) {
        //eliminar imagen anterior
        $nombre_imagen = $request->file('imagenAltaPdfFiscal')->getClientOriginalName();
        Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfFiscal'), $nombre_imagen);

        $updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'catprov.const_sit_fiscal' => $JwtAuth->encriptar($nombre_imagen),
            )
          );

        if ($updateImg) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Constancia de situación fiscal actualizada'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Constancia de situación fiscal no ha sido actualizada, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updatecumplimientoload(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $selectFolio = DB::select("SELECT catprov.opinion_cumplimiento,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp 
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_cat_proveedores, $empresa, $usuario]);

      $filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

      if (!file_exists(storage_path("/root/" . $filepath))) {
        Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
      }

      if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
        //eliminar imagen anterior
        $nombre_imagen = $request->file('imagenAltaPdfCumplimientoObFiscales')->getClientOriginalName();
        Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfCumplimientoObFiscales'), $nombre_imagen);

        $updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'catprov.opinion_cumplimiento' => $JwtAuth->encriptar($nombre_imagen),
            )
          );

        if ($updateImg) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Constancia de cumplimiento de obligaciones fiscales actualizada'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Constancia de cumplimiento de obligaciones fiscales no ha sido actualizada, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updatecumplimientobase64(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');

      $selectFolio = DB::select("SELECT catprov.opinion_cumplimiento,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp 
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_cat_proveedores, $empresa, $usuario]);

      $filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

      if (!file_exists(storage_path("/root/" . $filepath))) {
        Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
      }

      if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
        //eliminar imagen anterior
        $nombre_imagen = $request->file('imagenAltaPdfCumplimientoObFiscales')->getClientOriginalName();
        Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfCumplimientoObFiscales'), $nombre_imagen);

        $updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'catprov.opinion_cumplimiento' => $JwtAuth->encriptar($nombre_imagen),
            )
          );

        if ($updateImg) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Constancia de cumplimiento de obligaciones fiscales actualizada'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Constancia de cumplimiento de obligaciones fiscales no ha sido actualizada, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateCreditosProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'token_creditos' => 'required|string',
      'aceptcredito' => 'required|boolean',
      'data_moneda_code' => 'required|string',
      'data_moneda_decimales' => 'required|string',
      'txtlimiteCredito' => 'required|string',
      'txtdiasCobroCredit' => 'required|numeric',
      'selectComienzaCobroClient' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $token_creditos = $request->input('token_creditos');
      $aceptcredito = $request->input('aceptcredito');
      $data_moneda_code = $request->input('data_moneda_code');
      //echo $data_moneda_code;
      $data_moneda_decimales = $request->input('data_moneda_decimales');
      //echo $data_moneda_decimales;
      $txtlimiteCredito = $request->input('txtlimiteCredito');
      //echo $txtlimiteCredito;
      $txtdiasCobroCredit = $request->input('txtdiasCobroCredit');
      //echo $txtdiasCobroCredit;
      $selectComienzaCobro = $request->input('selectComienzaCobroClient');
      //echo $selectComienzaCobro;

      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $provCat = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $token_cat_proveedores)->value("id");
      $valida_prv = isset($token_cat_proveedores) && !empty($token_cat_proveedores) && $provCat != "";
      $credID = DB::table("in_egr_creditos")->where("token_creditos", $token_creditos)->value("id");
      $valida_crd = isset($token_creditos) && !empty($token_creditos) && $credID != "";
      $valida_act = isset($aceptcredito) && is_bool($aceptcredito);
      $valida_mnc = isset($data_moneda_code) && !empty($data_moneda_code);
      $valida_mnd = isset($data_moneda_decimales) && !empty($data_moneda_decimales);
      $valida_lim = isset($txtlimiteCredito) && !empty($txtlimiteCredito) && preg_match($patronNumCred, $txtlimiteCredito);
      $valida_dia = isset($txtdiasCobroCredit) && !empty($txtdiasCobroCredit) && preg_match($patronNum, $txtdiasCobroCredit);
      $valida_com = isset($selectComienzaCobro) && !empty($selectComienzaCobro) && preg_match($patron, $selectComienzaCobro);

      if ($valida_act) {
        if ($aceptcredito) {
          if ($valida_mnc && $valida_mnd && $valida_lim && $valida_dia && $valida_com) {
            $updateCreditoProv = DB::table('in_egr_creditos AS cred')
              ->join("eegr_catalogo_proveedores AS catprov", "cred.proveedor", "catprov.id")
              ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
              ->where([
                'catprov.token_cat_proveedores' => $token_cat_proveedores,
                'cred.token_creditos' => $token_creditos,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])
              ->update(array(
                "cred.aceptacredito" => TRUE,
                "cred.moneda_code" => $data_moneda_code,
                "cred.limite" => $txtlimiteCredito,
                "cred.dias" => $txtdiasCobroCredit,
                "cred.comienza" => $selectComienzaCobro,
              ));

            if ($updateCreditoProv) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Crédito del proveedor actualizado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Actualización de crédito del proveedor no realizada, intente nuevamente o comuniquese a soporte'
              );
            }
          } else {
            $mensaje_alerta_error = '';
            if (!$valida_mnc || $valida_mnd) {
              $mensaje_alerta_error = 'Error en seleccion de moneda';
            }
            if (!$valida_lim) {
              $mensaje_alerta_error = 'Error en limite de credito';
            }
            if (!$valida_dia) {
              $mensaje_alerta_error = 'Error en seleccion de dias de pago';
            }
            if (!$valida_com) {
              $mensaje_alerta_error = 'Error en seleccion de comienzo de pago';
            }
            $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_alerta_error);
          }
        } else {
          $updateCreditoProv = DB::table('in_egr_creditos AS cred')
            ->join("eegr_catalogo_proveedores AS catprov", "cred.proveedor", "catprov.id")
            ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $token_cat_proveedores,
              'cred.token_creditos' => $token_creditos,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->update(array(
              "cred.aceptacredito" => FALSE,
              "cred.moneda_code" => NULL,
              "cred.limite" => NULL,
              "cred.dias" => NULL,
              "cred.comienza" => NULL,
            ));

          if ($updateCreditoProv) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Crédito del proveedor actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Actualización de crédito del proveedor no realizada, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Actualización de crédito del proveedor no realizada, intente nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateFormaPagoProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'formaPago' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $formaPago = $request->input('formaPago');
      
      $selectFpago = DB::select("SELECT id FROM forma_pago WHERE token_formapago	= ?", [$formaPago]);
      $updateFormaPago = DB::table("eegr_catalogo_proveedores AS catprov")
        ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'catprov.token_cat_proveedores' => $token_cat_proveedores,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
        ])
        ->limit(1)->update(array('catprov.forma_pago' => $selectFpago[0]->id));

      if ($updateFormaPago) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Forma de pago actualizada'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Forma de pago no ha sido actualizada, intente nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updatefPagoProveedorEstCuenta(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');

      $selectFolio = DB::select("SELECT catprov.const_sit_fiscal,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp 
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_cat_proveedores, $empresa, $usuario]);

      $filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";
      if (file_exists($request->file('imagenPerfilPdfEstCuenta'))) {
        $doc_estado_cuenta = $JwtAuth->encriptar($JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . '-' . $request->file('imagenPerfilPdfEstCuenta')->getClientOriginalName());

        $updateFormaPago = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'catprov.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(array('catprov.estado_cuenta' => $doc_estado_cuenta,));

        if ($updateFormaPago) {
          Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenPerfilPdfEstCuenta'), $JwtAuth->desencriptar($doc_estado_cuenta));
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Forma de pago actualizada'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Forma de pago no ha sido actualizada, intente nuevamente o comuniquese a soporte'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'El archivo que intenta cargar es invalido o vacio'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateClabeInterbPagoProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'clabe_Interbancaria' => 'required|string',
      'formaPago' => 'required|array'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $clabe_Interbancaria = $request->input('clabe_Interbancaria');
      $forma_pago = $request->input('formaPago');
      
      $patronNumCred = '/^[0-9$,.-]*$/';

      for ($i = 0; $i < count($forma_pago); $i++) {
        if (isset($forma_pago[$i]['tipo_referencia_pago']) && !empty($forma_pago[$i]['tipo_referencia_pago'])) {
          if ($forma_pago[$i]['tipo_referencia_pago'] == 'clabeInterbancaria') {
            $tipoReferenciaPago = 'ci';
          } else if ($forma_pago[$i]['tipo_referencia_pago'] == 'convenio') {
            $tipoReferenciaPago = 'co';
          } else if ($forma_pago[$i]['tipo_referencia_pago'] == 'lineaCaptura') {
            $tipoReferenciaPago = 'lc';
          }

          if ($forma_pago[$i]['tipo_referencia_pago'] == 'clabeInterbancaria') {
            if (preg_match($patronNumCred, $forma_pago[$i]['clabe_Interbancaria'])) {
              $formaPagoClabeInterbank = $clabe_Interbancaria;
            } else {
              $formaPagoClabeInterbank = NULL;
            }
          } else {
            $formaPagoClabeInterbank = NULL;
          }

          $updateFormaPago = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $token_cat_proveedores,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'catprov.tipo_referencia_pago' => $tipoReferenciaPago,
                'catprov.clabe_interbancaria' => $formaPagoClabeInterbank,
              )
            );
          if ($updateFormaPago) {
            $selectProvCat = DB::select("SELECT folio,post_folio FROM eegr_catalogo_proveedores 
              WHERE token_cat_proveedores = ?", [$token_cat_proveedores]);
            if ($selectProvCat[0]->post_folio == NULL) {
              $folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
            } else {
              $folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
            }

            $JwtAuth->insertBitacoraActividad(
              'egresos',
              'catalogos',
              'proveedores',
              $folio_prov,
              'actualizacion de forma de pago de proveedor',
              $empresa,
              $usuario
            );
            $JwtAuth->insertBitacoraProv(
              $token_cat_proveedores,
              'actualizacion de forma de pago de proveedor',
              $empresa,
              $usuario
            );
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Forma de pago actualizada'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Forma de pago no ha sido actualizada, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Selecciona opcion de tipo de referencia de pago'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraNuevaUbicacionExtranjeroProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'pais' => 'required|string',
      'arrayubicacionExtranjeroProvv_reg' => 'required|array'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $pais = $request->input('pais');
      $ubicacionExtranjeraProvv_reg = $request->input('arrayubicacionExtranjeroProvv_reg');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      $fechaAlta = time();

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $contadorUbicaciones = 0;
      if (count($ubicacionExtranjeraProvv_reg) != 0) {
        for ($i = 0; $i < count($ubicacionExtranjeraProvv_reg); $i++) {
          if (
            preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['clasificacion']) &&
            preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['alias']) &&
            preg_match($patronCpostal, $ubicacionExtranjeraProvv_reg[$i]['codigo_postal'])
          ) {
            if ($ubicacionExtranjeraProvv_reg[$i]['direccion'] != '' && $ubicacionExtranjeraProvv_reg[$i]['direccion'] != '-') {
              if (preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['direccion'])) {
                $contadorUbicaciones++;
              } else {
                $contadorUbicaciones = 0;
                break;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'positionErrorCodeSucursalExt' => $i,
                  'message' => 'Error en dirección completa de dirección extranjera'
                );
              }
            } else {
              $contadorUbicaciones++;
            }
          } else {
            if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['clasificacion'])) {
              break;
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'positionErrorCodeSucursalExt' => $i,
                'message' => 'Error en clasificacion de dirección extranjera'
              );
            }
            if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['alias'])) {
              break;
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'positionErrorCodeSucursalExt' => $i,
                'message' => 'Error en alias de dirección extranjera'
              );
            }
            if (!preg_match($patronCpostal, $ubicacionExtranjeraProvv_reg[$i]['codigo_postal'])) {
              break;
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'positionErrorCodeSucursalExt' => $i,
                'message' => 'Error en código postal de dirección extranjera'
              );
            }
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Ingrese la dirección extranjera de su proveedor'
        );
      }

      if ($contadorUbicaciones == count($ubicacionExtranjeraProvv_reg)) {
        $selectProvCat = DB::select("SELECT id,folio,post_folio FROM eegr_catalogo_proveedores 
          WHERE token_cat_proveedores = ?", [$token_cat_proveedores]);
        $contadorInsertUbicaciones = 0;
        for ($i = 0; $i < count($ubicacionExtranjeraProvv_reg); $i++) {
          $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$pais]);
          if ($i == 0) {
            $tipo_direccion = 'dirección fiscal';
          } else {
            $tipo_direccion = 'dirección sucursal';
          }

          $clasificacionDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['clasificacion']);
          $aliasDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['alias']);
          $cpostalDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['codigo_postal']);
          $calleDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['direccion']);
          if (
            $ubicacionExtranjeraProvv_reg[$i]['direccion'] != '' &&
            $ubicacionExtranjeraProvv_reg[$i]['direccion'] != '-' &&
            preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['direccion'])
          ) {
            $calleDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['direccion']);
          } else {
            $calleDir = NULL;
          }
          $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $clasificacionDir, $aliasDir, $cpostalDir, $calleDir);

          $fisinsertDir = DB::table('direcciones')
            ->insert(array(
              "token_direccion" => $tokenCDir,
              "tipo_direccion" => $tipo_direccion,
              "clase" => $clasificacionDir,
              "alias" => $aliasDir,
              "calle" => $calleDir,
              "cod_postalext" => $cpostalDir,
              "pais" => $selectPais[0]->id,
              "proveedor" => $selectProvCat[0]->id,
              "status" => TRUE,
              "administrador" => $selectEmp[0]->id,
            ));
          if ($fisinsertDir) {
            $contadorInsertUbicaciones++;
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Dirección con alias' . $ubicacionExtranjeraProvv_reg[$i]['alias'] . ' no registrada, intente nuevamente o comuniquese a soporte'
            );
          }
        }

        if ($contadorInsertUbicaciones == count($ubicacionExtranjeraProvv_reg)) {

          if ($selectProvCat[0]->post_folio == NULL) {
            $folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
          } else {
            $folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
          }
          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'registro de direcciones de proveedor',
            $empresa,
            $usuario
          );
          $JwtAuth->insertBitacoraProv(
            $token_cat_proveedores,
            'registro de direcciones de proveedor',
            $empresa,
            $usuario
          );

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Direcciones registradas'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Direcciones no registradas, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateUbicacionNacionalProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'token_direccion' => 'required|string',
      'estado' => 'required|string',
      'municipio' => 'required|string',
      'codigo_postal' => 'required|string',
      'colonia' => 'required|string',
      'api' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $token_direccion = $request->input('token_direccion');
      $estado = $request->input('estado');
      $municipio = $request->input('municipio');
      $codigo_postal = $request->input('codigo_postal');
      $colonia = $request->input('colonia');
      $api = $request->input('api');
      
      $provCat = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $token_cat_proveedores)->value("id");
      $valida_proveedor = isset($token_cat_proveedores) && !empty($token_cat_proveedores) && $provCat != "";
      $dirID = DB::table("teci_direcciones")->where("token_direccion", $token_direccion)->value("id");
      $valida_direccion = isset($token_direccion) && !empty($token_direccion) && $dirID != "";
      $valida_estado = isset($estado) && !empty($estado);
      $valida_municipio = isset($municipio) && !empty($municipio);
      $valida_codigo_postal = isset($codigo_postal) && !empty($codigo_postal);
      $valida_colonia = isset($colonia) && !empty($colonia);
      $valida_api = isset($api) && !empty($api);

      if ($valida_proveedor && $valida_direccion && $valida_estado && $valida_municipio && $valida_codigo_postal && $valida_colonia && $valida_api) {
        $fisinsertDir = DB::table("teci_direcciones AS dir")
          ->join("eegr_catalogo_proveedores AS prv", "dir.proveedor", "=", "prv.id")
          ->where("prv.token_cat_proveedores", $token_cat_proveedores)
          ->limit(1)->update(
            array(
              "dir.pais" => 118,
              "dir.pais_code" => "MEX",
              "dir.estado_edit" => $JwtAuth->encriptar($estado),
              "dir.municipio_edit" => $JwtAuth->encriptar($municipio),
              "dir.c_postal_edit" => $codigo_postal,
              "dir.colonia_edit" => $JwtAuth->encriptar($colonia),
              "dir.adicional" => "api",
            )
          );
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Ubicación actualizada'
        );
      } else {
        $mensaje_error = '';
        if (!$valida_proveedor) {
          $mensaje_error = 'Error en proveedor seleccionado, verifique su información';
        }
        if (!$valida_direccion || !$valida_estado || !$valida_municipio || !$valida_codigo_postal || !$valida_colonia || !$valida_api) {
          $mensaje_error = 'Error en dirección seleccionada, verifique su información';
        }
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateUbicacionExtranjeroProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'dirUbicacion' => 'required|array'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $forUbica = $request->input('dirUbicacion');
      
      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';

      for ($i = 0; $i < count($forUbica); $i++) {
        if (
          preg_match($patron, $forUbica[$i]['clasificacion']) && preg_match($patron, $forUbica[$i]['alias']) &&
          preg_match($patronCpostal, $forUbica[$i]['cod_postalext']) && preg_match($patron, $forUbica[$i]['dir_completa'])
        ) {
          $fisinsertDir = DB::table('direcciones AS dirfis')
            ->join("eegr_catalogo_proveedores AS catprov", "dirfis.proveedor", "catprov.id")
            ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $token_cat_proveedores,
              'dirfis.token_direccion' => $forUbica[$i]['token_direccion'],
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->update(array(
              "clase" => $JwtAuth->encriptar($forUbica[$i]['clasificacion']),
              "alias" => $JwtAuth->encriptar($forUbica[$i]['alias']),
              "calle" => $JwtAuth->encriptar($forUbica[$i]['dir_completa']),
              "cod_postalext" => $JwtAuth->encriptar($forUbica[$i]['cod_postalext']),
            ));
          if ($fisinsertDir) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Dirección actualizada'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Actualización de dirección no realizada, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          if (!preg_match($patron, $forUbica[$i]['clasificacion'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en clasificacion de dirección de sucursal'
            );
          }
          if (!preg_match($patron, $forUbica[$i]['alias'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en alias de dirección de sucursal'
            );
          }
          if (!preg_match($patronCpostal, $forUbica[$i]['cod_postalext'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en código postal de dirección de sucursal'
            );
          }
          if (!preg_match($patron, $forUbica[$i]['dir_completa'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en dirección de dirección de sucursal'
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteUbicacionProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'token_direccion' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $token_direccion = $request->input('token_direccion');

      $deleteUbica = DB::table('teci_direcciones AS ubica')
        ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
        ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'catprov.token_cat_proveedores' => $token_cat_proveedores,
          'ubica.token_direccion' => $token_direccion,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
        ])
        ->update(array(
          "ubica.status" => FALSE,
          "ubica.fecha_delete_dir" => time(),
        ));

      if ($deleteUbica) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Dirección eliminada'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Eliminación de dirección no realizada, intente nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarUbicacionProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'token_direccion' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $token_direccion = $request->input('token_direccion');
      
      $restDir = DB::table('teci_direcciones AS ubica')
        ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
        ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'catprov.token_cat_proveedores' => $token_cat_proveedores,
          'ubica.token_direccion' => $token_direccion,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
        ])
        ->update(array(
          "ubica.status" => TRUE,
          "ubica.fecha_delete_dir" => '',
        ));
      if ($restDir) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Dirección restaurada'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Restauración de dirección no realizada, intente nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletePermUbicacionProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'token_direccion' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $token_direccion = $request->input('token_direccion');
      
      $deleteDir = DB::table('teci_direcciones AS ubica')
        ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
        ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'catprov.token_cat_proveedores' => $token_cat_proveedores,
          'ubica.token_direccion' => $token_direccion,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
        ])
        ->delete();

      if ($deleteDir) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Dirección eliminada permanentemente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Eliminación permanente de dirección no realizada, intente nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $utilizado = DB::select("SELECT folio,post_folio,utilizado FROM eegr_catalogo_proveedores 
          WHERE token_cat_proveedores = ?", [$token_cat_proveedores]);

      if ($utilizado[0]->utilizado == FALSE) {
        if ($utilizado[0]->post_folio == NULL) {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($utilizado[0]->folio);
        } else {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($utilizado[0]->folio) . '-' . $utilizado[0]->post_folio;
        }

        $deleteProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'eegr_catalogo_proveedores.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'eegr_catalogo_proveedores.status' => FALSE,
              'eegr_catalogo_proveedores.fecha_delete_prov' => time(),
            )
          );
        if ($deleteProv) {
          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'eliminación de proveedor',
            $empresa,
            $usuario
          );

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Proveedor eliminado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Eliminación de proveedor no realizada, intente nuevamente o comuniquese a soporte'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Eliminación de proveedor no realizada, proveedor vinculado a operaciones realizadas'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $contadorFolio = DB::select(
        "SELECT COUNT(folio) AS contador FROM eegr_catalogo_proveedores 
                  WHERE folio = (SELECT folio FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?)",
        [$token_cat_proveedores]
      );

      if ($contadorFolio[0]->contador == 1) {
        $getFolio = DB::select(
          "SELECT folio FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?",
          [$token_cat_proveedores]
        );
        $post_folio_db = DB::select("SELECT post_folio FROM eegr_catalogo_proveedores 
              WHERE token_cat_proveedores = ?", [$token_cat_proveedores]);
        $post_folio = $post_folio_db[0]->post_folio;
        $folio_nuevo = $getFolio[0]->folio;

        if ($post_folio == NULL) {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo);
        } else {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
        }

        $restaurarProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'eegr_catalogo_proveedores.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'eegr_catalogo_proveedores.folio' => $folio_nuevo,
              'eegr_catalogo_proveedores.post_folio' => $post_folio,
              'eegr_catalogo_proveedores.status' => TRUE,
              'eegr_catalogo_proveedores.fecha_delete_prov' => '',
            )
          );
        if ($restaurarProv) {
          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'recuperación de proveedor eliminado',
            $empresa,
            $usuario
          );
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Proveedor restaurado con el folio ' . $folio_prov
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Restauración de proveedor no realizada, intente nuevamente o comuniquese a soporte'
          );
        }
      } else {
        $newFolio = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        if (count($newFolio) == 1) {
          if ($newFolio[0]->folio == 1000000000) {
            $post_folio_db = DB::select("SELECT MAX(catprov.post_folio)+1 AS folio FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                      JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

            $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folder);
            $folio_nuevo = 1;
          } else {
            $post_folio_db = DB::select("SELECT post_folio FROM eegr_catalogo_proveedores 
                      WHERE token_cat_proveedores = ?", [$token_cat_proveedores]);
            $post_folio = $post_folio_db[0]->post_folio;
            $folio_nuevo = $newFolio[0]->folio;
          }
        } else {
          $post_folio = NULL;
          $folio_nuevo = 1;
        }

        if ($post_folio == NULL) {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo);
        } else {
          $folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
        }

        $restaurarProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'eegr_catalogo_proveedores.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'eegr_catalogo_proveedores.folio' => $folio_nuevo,
              'eegr_catalogo_proveedores.post_folio' => $post_folio,
              'eegr_catalogo_proveedores.status' => TRUE,
              'eegr_catalogo_proveedores.fecha_delete_prov' => '',
            )
          );
        if ($restaurarProv) {
          $JwtAuth->insertBitacoraActividad(
            'egresos',
            'catalogos',
            'proveedores',
            $folio_prov,
            'recuperación de proveedor eliminado',
            $empresa,
            $usuario
          );
          $regFolder = DB::table("sos_last_folders")->join("main_empresas AS emp", "last_folders.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              "sos_last_folders.egr_proveedores" => TRUE,
              "emp.empresa_token" => $empresa,
              "users.usuario_token" => $usuario,
            ])
            ->limit(1)->update(
              array(
                "sos_last_folders.folder" => $folio_nuevo,
                "sos_last_folders.post_folder" => $post_folio,
              )
            );

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Proveedor restaurado con el folio ' . $folio_prov
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Restauración de proveedor no realizada, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletePermProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $utilizado = DB::select("SELECT utilizado FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$token_cat_proveedores]);

      if ($utilizado[0]->utilizado == FALSE) {
        $buscaProveedor = ProveedoresModelo::join("personas AS perns", "eegr_catalogo_proveedores.proveedor", "=", "perns.id")
          ->join("pais AS country", "perns.nacionalidad", "=", "country.id")
          ->join("forma_pago AS fpago", "eegr_catalogo_proveedores.forma_pago", "=", "fpago.id")
          ->join("creditos AS cred", "eegr_catalogo_proveedores.id", "=", "cred.proveedor")
          ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_catalogo_proveedores.token_cat_proveedores' => $token_cat_proveedores,
            'emp.empresa_token' => $empresa,
            'users.user_token' => $usuario,
          ])->get();
        //echo count($buscaProveedor);
        if (count($buscaProveedor) == 1) {
          foreach ($buscaProveedor as $valViewProv) {
            //creditos
            $deleteCreditos = DB::table("creditos AS cred")
              ->join("eegr_catalogo_proveedores AS catprov", "cred.proveedor", "=", "catprov.id")
              ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
              ->where([
                'cred.token_creditos' => $valViewProv->token_creditos,
                'catprov.token_cat_proveedores' => $token_cat_proveedores,
                'emp.empresa_token' => $empresa,
                'users.user_token' => $usuario,
              ])->limit(1)->delete();
            //echo 'token_creditos '.$deleteCreditos[0]->token_creditos;

            //contacto
            $queryContProv = DB::select(
              "SELECT empleado.token_contacto,areapers.areaemp,cargopers.cargo,people.token_personas,people.paterno,people.materno,people.nombre
                FROM vhum_empleados_catalogo AS empleado JOIN vhum_empleados_catalogo_area AS areapers JOIN vhum_empleados_catalogo_cargo AS cargopers 
                JOIN sos_personas AS people JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN teci_usuarios_catalogo AS users WHERE empleado.area = areapers.id AND empleado.cargo = cargopers.id AND empleado.empleado_name = people.id 
                AND empleado.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.id = empuser.empresa
                AND emp.empresa_token = ? AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$token_cat_proveedores, $empresa, $usuario]
            );

            if (count($queryContProv) > 0) {
              foreach ($queryContProv as $valContProv) {
                $telefonoProv = DB::select("SELECT tel.token_telefono,tel.telefono,tel.extension FROM telefonos_personal AS tel 
                    JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE tel.personal = empleado.id AND empleado.token_contacto = ?", [$valContProv->token_contacto]);

                if (count($telefonoProv) > 0) {
                  foreach ($telefonoProv as $valueTelPers) {
                    $deleteTelefono = DB::table("telefonos_personal AS tel")
                      ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
                      ->where(['tel.token_telefono' => $valueTelPers->token_telefono, 'empleado.token_contacto' => $valContProv->token_contacto])->limit(1)->delete();
                  }
                }

                $queryContProv = DB::select("SELECT mailpers.token_correo,mailpers.correo FROM correos_personal AS mailpers JOIN in_egr_contacto_cliente_proveedor AS empleado 
                  WHERE mailpers.empleado_name = empleado.id AND empleado.pers_token = ?", [$valContProv->pers_token]);

                if (count($queryContProv) > 0) {
                  foreach ($queryContProv as $valueMailPers) {
                    $deleteCorreo = DB::table("correos_personal AS mailpers")
                      ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.personal", "=", "empleado.id")
                      ->where(['mailpers.token_correo' => $valueMailPers->token_correo, 'empleado.token_contacto' => $valContProv->token_contacto,])->limit(1)->delete();
                  }
                }

                $deletePersonas = DB::table("personas AS people")
                  ->join("in_egr_contacto_cliente_proveedor AS empleado", "people.id", "=", "empleado.nombre")
                  ->where(['people.token_personas' => $valContProv->token_personas, 'empleado.token_contacto' => $valContProv->token_contacto])->limit(1)->delete();
                //echo $valContProv->token_personas;
                $deletePersonal = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
                  ->join("eegr_catalogo_proveedores AS catprov", "empleado.cat_proveedores", "=", "catprov.id")
                  ->where(['empleado.token_contacto' => $valContProv->token_contacto, 'catprov.token_cat_proveedores' => $token_cat_proveedores,])->limit(1)->delete();
              }
            }

            //direcciones 
            $listaUbicacion = DB::table('teci_direcciones AS ubica')
              ->join('eegr_catalogo_proveedores AS catprov', 'ubica.proveedor', 'catprov.id')
              ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
              ->where([
                'catprov.token_cat_proveedores' => $token_cat_proveedores,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario
              ])->limit(1)->delete();

            //nombre del proveedor 
            $listaUbicacion = DB::table('personas AS people')
              ->join('eegr_catalogo_proveedores AS catprov', 'people.id', 'catprov.proveedor')
              ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
              ->where([
                'catprov.token_cat_proveedores' => $token_cat_proveedores,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario
              ])->limit(1)->delete();

            if ($valViewProv->post_folio == NULL) {
              $folio_prov = 'prv-' . $JwtAuth->generarFolio($valViewProv->folio);
            } else {
              $folio_prov = 'prv-' . $JwtAuth->generarFolio($valViewProv->folio) . '-' . $valViewProv->post_folio;
            }

            $deleteProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
              ->where([
                'eegr_catalogo_proveedores.token_cat_proveedores' => $token_cat_proveedores,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])->limit(1)->delete();

            if ($deleteProv) {
              $JwtAuth->insertBitacoraActividad(
                'egresos',
                'catalogos',
                'proveedores',
                $folio_prov,
                'eliminación permanente de proveedor',
                $empresa,
                $usuario
              );

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Proveedor eliminado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Eliminación de proveedor no realizada, intente nuevamente o comuniquese a soporte'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'El proveedor seleccionado no esta registrado, por favor intente mas tarde'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Eliminación de proveedor no realizada, proveedor vinculado a operaciones realizadas'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function buscaRFProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      //'radioProv' => 'required|string',
      //'subtipoProv' => 'required|string',
      'rfc_generico' => 'required|string',
      'prov_rfc' => 'string',
      'id_tax' => 'string',
      'nombre' => 'required|string'
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
      //$radioProv = $request->input('radioProv');
      //$subtipoProv = $request->input('subtipoProv');
      $rfc_generico = $request->input('rfc_generico');
      $prov_rfc = $request->input('prov_rfc');
      $id_tax = $request->input('id_tax');
      $nombre = $request->input('nombre');

      $paramProvRfcGenerico = strtolower($rfc_generico);
      $paramProvRfc = strtolower($prov_rfc);
      $paramIdTax = strtolower($id_tax);
      $paramNombreProv = strtolower($nombre);
      
      $listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
        ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
          'eegr_catalogo_proveedores.status' => true
        ])->get();

      $countVerifica = 0;
      $invalidName = '';
      foreach ($listaProveedores as $resListProv) {
        $nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

        $rfc_generico = strtolower($resListProv->rfc_generico);

        if ($resListProv->rfc != NULL) {
          $rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));
        } else {
          $rfc_prov = '';
        }

        if ($resListProv->tax_id != NULL) {
          $tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
        } else {
          $tax_id_prov = '';
        }

        if ($paramProvRfc != '') {
          if ($rfc_prov == $paramProvRfc) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        } else if ($paramIdTax != '') {
          if ($tax_id_prov == $paramIdTax) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        } else if ($rfc_generico == $paramProvRfcGenerico && $nombreProv == $paramNombreProv) {
          ++$countVerifica;
          $invalidName = $nombreProv;
        } else {
          if ($nombreProv == $paramNombreProv) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        }
      }

      if ($countVerifica >= 1) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
        );
      } else {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function buscaProveedorByRFC(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(), [
      'prov_rfc' => 'required|string'
    ]);

    if ($validate->fails()) {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La infomación que ha intantado registrar es invalida',
        'errors' => $validate->errors()
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $rfc_prov = $request->input('prov_rfc');

      if (isset($rfc_prov) && !empty($rfc_prov) && preg_match($JwtAuth->filtroRfc(), $rfc_prov)) {
        $rfc_prov_cifrado = strtoupper(trim($rfc_prov));
        $proveedorQuery = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
          ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
          ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->where([
            'emp.empresa_token' => $empresa,
            'eegr_catalogo_proveedores.status' => TRUE
          ])
          ->select([
            'prov.rfc',
            'eegr_catalogo_proveedores.token_cat_proveedores'
          ])
          ->get();

        $proveedorExiste = $proveedorQuery->first(function ($vProv) use ($JwtAuth, $rfc_prov_cifrado) {
          if (!$vProv->rfc) {
            return false;
          }

          $rfc_prv = strtoupper(trim($JwtAuth->desencriptar($vProv->rfc)));
          return $rfc_prv === $rfc_prov_cifrado;
        });

        if (!$proveedorExiste) {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'existe' => false,
            'message' => 'No existe un proveedor con el RFC ' . strtoupper($rfc_prov)
          ], 401);
        }

        $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
          ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
          ->where("catprv.token_cat_proveedores", $proveedorExiste->token_cat_proveedores)
          ->value("cred.aceptacredito");

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'existe' => true,
          'message' => 'El proveedor con el RFC ' . strtoupper($rfc_prov) . ' ya se encuentra registrado y puede ser utilizado para sus procesos de compra',
          'rfc'   => $JwtAuth->desencriptar($proveedorExiste->rfc),
          'token' => $proveedorExiste->token_cat_proveedores,
          "aceptacredito" => $credProveedor ? true : false,
        );
      } else {
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Nombre ó rfc generico del proveedor son incorrectos');
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function buscaRfcAllProveedorOut(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'radioProv' => 'required|string',
      'nombre' => 'required|string',
      'rfc_generico' => 'required|string',
      'prov_rfc' => 'nullable|string',
      'id_tax' => 'nullable|string',
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
      $paramradioProv = $request->input('radioProv');
      $paramNombreProv = strtolower($request->input('nombre'));
      $paramProvRfcGenerico = strtolower($request->input('rfc_generico'));
      $paramProvRfc = strtolower($request->input('prov_rfc'));
      $paramIdTax = strtolower($request->input('id_tax'));
      
      if ($empresa == "") {
        $empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
      } else {
        $empresa = $empresa;
      }

      if (
        isset($paramradioProv) && !empty($paramradioProv) && preg_match($JwtAuth->filtroRfc(), $paramradioProv) &&
        isset($paramProvRfcGenerico) && !empty($paramProvRfcGenerico) && preg_match($JwtAuth->filtroRfc(), $paramProvRfcGenerico) &&
        isset($paramNombreProv) && !empty($paramNombreProv) && preg_match($JwtAuth->filtroAlfabetico(), $paramNombreProv)
      ) {

        $arrayProveedores = array();
        $listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
          ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
          ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->where(['emp.empresa_token' => $empresa, 'eegr_catalogo_proveedores.status' => true])
          ->get();

        $countVerifica = 0;
        $invalidName = '';

        foreach ($listaProveedores as $resListProv) {
          $rfc_prov = '';
          $tax_id_prov = '';
          $nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

          $rfc_generico = strtolower($resListProv->rfc_generico);

          if ($resListProv->rfc != NULL) $rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));

          if ($resListProv->tax_id != NULL) $tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));

          $row_prov = array(
            "nombre_prov" => $nombreProv,
            "rfc_generico" => $rfc_generico,
            "rfc_prov" => $rfc_prov,
            "tax_id_prov" => $tax_id_prov,
          );
          $arrayProveedores[] = $row_prov;
        }
        $search_by_nombre = array_column($arrayProveedores, "nombre_prov");
        $search_by_rfc_generico = array_column($arrayProveedores, "rfc_generico");
        $search_by_rfc_prov = array_column($arrayProveedores, "rfc_prov");
        $search_by_tax_id_prov = array_column($arrayProveedores, "tax_id_prov");

        if ($paramradioProv == "nacional") {
          //array_search($paramNombreProv, $search_by_nombre) == ""
          if (array_search($paramProvRfc, $search_by_rfc_prov) == "") {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
            );
          } else {
            $invalidName = $arrayProveedores[array_search($paramProvRfc, array_column($arrayProveedores, "rfc_prov"))]["nombre_prov"];
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
            );
            return response()->json($dataMensaje, $dataMensaje['code']);
          }


          if (array_search($paramNombreProv, $search_by_nombre) == "") {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
            );
          } else {
            $invalidRfc = $arrayProveedores[array_search($paramNombreProv, array_column($arrayProveedores, "nombre_prov"))]["rfc_prov"];
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'El proveedor verificado ya ha sido registrado con rfc ' . strtoupper($invalidRfc)
            );
            return response()->json($dataMensaje, $dataMensaje['code']);
          }
        } else if ($paramradioProv == "extranjero") {
          if (array_search($paramNombreProv, $search_by_nombre) == "") {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
            );
          } else {
            $invalidName = $arrayProveedores[array_search($paramNombreProv, array_column($arrayProveedores, "nombre_prov"))]["nombre_prov"];
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
            );
          }
        }
      } else {
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Nombre ó rfc generico del proveedor son incorrectos');
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function verifyProveedorExistPerfil(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'empresa_token' => 'string',
      'token_cat_proveedores' => 'required|string',
      'prov_rfc' => 'string',
      'id_tax' => 'string',
      'nombre' => 'required|string',
      'nombre_comercial' => 'string',
      'sitio_web' => 'string',
      'regimen_fiscal' => 'string',
      'cuenta_contable' => 'string'
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $prov_rfc = $request->input('prov_rfc');
      $prov_idTax = $request->input('id_tax');
      $nombreProv = $request->input('nombre');
      $nombre_comercial = $request->input('nombre_comercial');
      $sitio_web = $request->input('sitio_web');
      $regimen_fiscal = $request->input('regimen_fiscal');
      $cuenta_contable = $request->input('cuenta_contable');
      
      $queryProvs = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
        ->where([
          "eegr_catalogo_proveedores.token_cat_proveedores" => $token_cat_proveedores,
          "emp.empresa_token" => $empresa,
          "eegr_catalogo_proveedores.status" => true
        ])->get();

      $countVerifica = 0;
      $invalidName = "";
      foreach ($queryProvs as $vPrv) {
        $nombre_cliente = $vPrv->nombre_extendido == "" || $vPrv->nombre_extendido == NULL ? ($vPrv->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vPrv->paterno, $vPrv->materno, $vPrv->nombre) :
          $JwtAuth->desencriptar($vPrv->denominacion_rs)) : $JwtAuth->desencriptar($vPrv->nombre_extendido);

        $rfc_generico = strtolower($vPrv->rfc_generico);
        $rfc_client = $vPrv->rfc != NULL ? strtolower($JwtAuth->desencriptar($vPrv->rfc)) : "---";
        //$rfc_client = $vPrv->rfc != NULL ? strtolower("hgf900802ht9") : "---";
        $taxId_client = $vPrv->tax_id != NULL ? strtolower($JwtAuth->desencriptar($vPrv->tax_id)) : "---";

        $sql_prov_rfc = $prov_rfc != $rfc_client ? ($prov_rfc != "" && $prov_rfc != "---" ? $JwtAuth->encriptar(strtoupper($prov_rfc)) : NULL) : $vPrv->rfc;
        $sql_prov_idTax = $prov_idTax != $taxId_client ? ($prov_idTax != "" && $prov_idTax != "---" ? $JwtAuth->encriptar(strtoupper($prov_idTax)) : NULL) : $vPrv->tax_id;
        $sql_client_nombre = $nombreProv != $nombre_cliente ? ($nombreProv != "" ? $JwtAuth->encriptar(strtolower($nombreProv)) : NULL) : $JwtAuth->encriptar($nombre_cliente);
        $validacion_cuenta_contable = isset($cuenta_contable) && !empty($cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(), $cuenta_contable);
        $selectRegFisc = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $regimen_fiscal)->value("id");

        $regGenerales = DB::table("eegr_catalogo_proveedores AS catprv")
          ->join("sos_personas AS prov", "catprv.proveedor", "prov.id")
          ->where(['catprv.token_cat_proveedores' => $vPrv->token_cat_proveedores])
          ->limit(1)->update(
            array(
              "prov.nombre_extendido" => $sql_client_nombre,
              "prov.rfc" => $sql_prov_rfc,
              "prov.tax_id" => $sql_prov_idTax,
              "prov.nombre_com" => $nombre_comercial != "---" ? $JwtAuth->encriptar($nombre_comercial) : NULL,
              "prov.sitio_web" => $sitio_web != "---" ? $JwtAuth->encriptar($sitio_web) : NULL,
              "catprv.regimen_fiscal" => $selectRegFisc,
              "catprv.cuenta_contable" => $validacion_cuenta_contable ? $JwtAuth->encriptar($cuenta_contable) : NULL,
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

        if ($prov_rfc != "") {
          if ($rfc_client == $prov_rfc && $nombreProv == $nombre_cliente) {
            ++$countVerifica;
            $invalidName = $nombre_cliente;
          }
        } else if ($prov_idTax != "") {
          if ($taxId_client == $prov_idTax && $nombreProv == $nombre_cliente) {
            ++$countVerifica;
            $invalidName = $nombre_cliente;
          }
        } else if ($nombreProv == $nombre_cliente) {
          ++$countVerifica;
          $invalidName = $nombre_cliente;
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function buscaRfcAllProveedorOutBack(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'rfc_generico' => 'required|string',
      'prov_rfc' => 'string',
      'id_tax' => 'string',
      'nombre' => 'required|string'
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
      $paramProvRfcGenerico = strtolower($request->input('rfc_generico'));
      $paramProvRfc = strtolower($request->input('prov_rfc'));
      $paramIdTax = strtolower($request->input('id_tax'));
      $paramNombreProv = strtolower($request->input('nombre'));
      
      if ($empresa == "") {
        $empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
      } else {
        $empresa = $empresa;
      }

      $listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
        ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'eegr_catalogo_proveedores.status' => true
        ])->get();

      $countVerifica = 0;
      $invalidName = '';
      foreach ($listaProveedores as $resListProv) {
        $nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

        $rfc_generico = strtolower($resListProv->rfc_generico);

        if ($resListProv->rfc != NULL) {
          $rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));
        } else {
          $rfc_prov = '';
        }

        if ($resListProv->tax_id != NULL) {
          $tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
        } else {
          $tax_id_prov = '';
        }

        if ($paramProvRfc != '') {
          if ($rfc_prov == $paramProvRfc) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        } else if ($paramIdTax != '') {
          if ($tax_id_prov == $paramIdTax) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        } else if ($rfc_generico == $paramProvRfcGenerico && $nombreProv == $paramNombreProv) {
          ++$countVerifica;
          $invalidName = $nombreProv;
        } else {
          if ($nombreProv == $paramNombreProv) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        }
      }

      if ($countVerifica >= 1) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
        );
      } else {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function buscaRfcAllProveedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'empresa_token' => 'required|string',
      'rfc_generico' => 'required|string',
      'prov_rfc' => 'string',
      'id_tax' => 'string',
      'nombre' => 'required|string',
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
      $empresa_token = $request->input('empresa_token');
      $paramProvRfcGenerico = strtolower($request->input('rfc_generico'));
      $paramProvRfc = strtolower($request->input('prov_rfc'));
      $paramIdTax = strtolower($request->input('id_tax'));
      $paramNombreProv = strtolower($request->input('nombre'));
      
      if ($empresa_token == "") {
        $empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
      } else {
        $empresa = $empresa_token;
      }
      
      $listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
        ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
        ->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
        ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'eegr_catalogo_proveedores.status' => true
        ])->get();

      $countVerifica = 0;
      $invalidName = '';
      foreach ($listaProveedores as $resListProv) {
        $nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

        $rfc_generico = strtolower($resListProv->rfc_generico);

        if ($resListProv->rfc != NULL) {
          $rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));
        } else {
          $rfc_prov = '';
        }

        if ($resListProv->tax_id != NULL) {
          $tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
        } else {
          $tax_id_prov = '';
        }

        if ($paramProvRfc != '') {
          if ($rfc_prov == $paramProvRfc) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        } else if ($paramIdTax != '') {
          if ($tax_id_prov == $paramIdTax) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        } else if ($rfc_generico == $paramProvRfcGenerico && $nombreProv == $paramNombreProv) {
          ++$countVerifica;
          $invalidName = $nombreProv;
        } else {
          if ($nombreProv == $paramNombreProv) {
            ++$countVerifica;
            $invalidName = $nombreProv;
          }
        }
      }

      if ($countVerifica >= 1) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
        );
      } else {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraProveedorMModulosExternos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'rfc_generico' => 'required|string',
			'prov_rfc' => 'string',
			'id_tax' => 'string',
			'radioProv' => 'required|string',
			'subtipoProv' => 'required|string',
			'name_prov' => 'string',
			'comercial_nombre' => 'string',
			'curp' => 'string',
			'paistoken' => 'string',
			'sitio_web' => 'string',
			'tknRegimenFiscal' => 'string',
			'cod_postal' => 'string',
			'dipomex_cod_postal_estado' => 'string',
			'dipomex_cod_postal_municipio' => 'string',
			'dipomex_cod_postal_cp' => 'string',
			'dipomex_cod_postal_colonia_vinculada' => 'string',
			'listnewdireccionNac' => 'array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $fechaAlta = time();
      $JwtAuth = new \App\Helpers\JwtAuth();
      $rfc_generico = $request->input('rfc_generico');
      $prov_rfc = $request->input('prov_rfc');
      $rfc_prov = NULL;
      $id_tax = $request->input('id_tax');
      $idtax = NULL;
      $radioProv = $request->input('radioProv');
      $subtipoProv = $request->input('subtipoProv');
      $name_prov = $request->input('name_prov');
      $comercial_nombre = $request->input('comercial_nombre');
      $curp = $request->input('curp');
      $paistoken = $request->input('paistoken');
      $sitio_web = $request->input('sitio_web');
      $tknRegimenFiscal = $request->input('tknRegimenFiscal');
      $cod_postal = $request->input('cod_postal');
      //echo "razon_social ".$JwtAuth->desencriptar("cnlZSktiM1FlMHlqbk13ZFhkS0ozUT09OjoxMjM0NTY3ODEyMzQ1Njc4");exit;
      $dipomex_cod_postal_estado = $request->input('dipomex_cod_postal_estado');
      $dipomex_cod_postal_municipio = $request->input('dipomex_cod_postal_municipio');
      $dipomex_cod_postal_cp = $request->input('dipomex_cod_postal_cp');
      $dipomex_cod_postal_colonia_vinculada = $request->input('dipomex_cod_postal_colonia_vinculada');
      $listnewdireccionNac = $request->input('listnewdireccionNac');

      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp WHERE emp.empresa_token = ?", [$empresa]);
      if (count($queryEmp) > 0) {
        foreach ($queryEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);

          $folio_nuevo = NULL;
          $post_folio = NULL;

          $folioSistemaTemp = DB::select("SELECT temp_folio FROM eegr_catalogo_proveedores WHERE temp_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$empresa]);
          if (count($folioSistemaTemp) > 0) {
            $queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temp_folio FROM eegr_catalogo_proveedores 
              WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp WHERE temp_folio IS NOT NULL AND catprov.administrador = emp.id 
              AND emp.empresa_token = ?)", [$empresa]);

            foreach ($queryFolioTmpPrv as $vTemp) {
              $folio_temporal = $vTemp->temp_folio;
            }
          } else {
            $folio_temporal = 1;
          }

          $folio_prov_temp = 'PRV-TEMP-' . $JwtAuth->generarFolio($folio_temporal);

          if ($prov_rfc != "") {
            if (isset($prov_rfc) && isset($prov_rfc) && preg_match($patronRfc, $prov_rfc)) {
              $rfc_prov = $JwtAuth->encriptar($prov_rfc);
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'error al registrar rfc del proveedor'
              );
            }
          } else {
            $rfc_prov = NULL;
          }

          if ($id_tax != "") {
            if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
              $idtax = $JwtAuth->encriptar($id_tax);
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'error al registrar idtax del proveedor'
              );
            }
          } else {
            $idtax = NULL;
          }

          $sql_proveedor = NULL;
          $comercial_nombre_txt = NULL;
          $curp_txt = NULL;
          $pais_txt = NULL;
          $sitio_web_txt = NULL;
          $regimen_fiscal_txt = NULL;

          if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
            if ($radioProv == "extranjero") {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == "provMoral") {
                  //return response()->json(['message' => 'pais','codigo' => 200,'status' => 'error']);
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov) && isset($paistoken) && !empty($paistoken)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);
                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                    if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($patron, $name_prov)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "nomemp",
                        "message" => "Error en nombre de empresa de su proveedor"
                      );
                    }
                    if (!isset($paistoken) || empty($paistoken)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "pais",
                        "message" => "Error en pais de su proveedor"
                      );
                    }
                  }
                }
                //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
                if ($subtipoProv == 'provFisica') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov) && isset($paistoken) && !empty($paistoken)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);

                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }

                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nombrePF',
                        'message' => 'Error en nombre de su proveedor'
                      );
                    }
                    if (!isset($paistoken) || empty($paistoken)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'paisPF',
                        'message' => 'Error en pais de su proveedor'
                      );
                    }
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }

            if ($radioProv == 'nacional') {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == 'provMoral') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);
                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $nombre_comercial = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitioweb = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $pais_txt = '118';
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "nomemp",
                        "message" => "Error en nombre de su proveedor"
                      );
                    }
                  }
                }

                if ($subtipoProv == 'provFisica') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);

                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($curp) && !empty($curp) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }

                      if (preg_match($patronRfc, $curp)) {
                        $curp_txt = $JwtAuth->encriptar($curp);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'clbint',
                          'message' => 'Error en curp de su proveedor'
                        );
                      }

                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $curp_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $pais_txt = '118';
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nombrePF',
                        'message' => 'Error en nombre de su proveedor'
                      );
                    }
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'clbint',
              'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
            );
          }

          $proveedorExiste = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
            ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
            ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
            ->where('emp.empresa_token', $empresa)
            ->where('eegr_catalogo_proveedores.status', TRUE)
            ->where(function ($query) use ($rfc_prov, $idtax, $sql_proveedor) {
              if ($rfc_prov) {
                $query->orWhere('prov.rfc', $rfc_prov);
              }
              if (!empty($idtax)) {
                $query->orWhere('prov.tax_id', $idtax);
              }
              if (!empty($sql_proveedor)) {
                $query->orWhereRaw('LOWER(prov.nombre_extendido) = ?', [strtolower($sql_proveedor)]);
              }
            })->exists();
          if (!$proveedorExiste) {
            $tkn_people_prov = $JwtAuth->encriptarToken($fechaAlta, $name_prov, $comercial_nombre_txt, $sitio_web_txt, $pais_txt, $rfc_prov);
            $sql_regimen_fiscal = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $tknRegimenFiscal)->value("id");
            //echo $name_prov." $sql_proveedor";
            $insertProv = DB::table("sos_personas")
              ->insert(array(
                "token_personas" => $tkn_people_prov,
                "nombre_extendido" => $sql_proveedor,
                "nombre_com" => $comercial_nombre_txt,
                "sitio_web" => $sitio_web_txt,
                "nacionalidad" => $pais_txt,
                "rfc_generico" => $rfc_generico,
                "rfc" => $rfc_prov,
                "tax_id" => $idtax,
                "curp" => $curp_txt,
              ));

            if ($insertProv) {
              $selecProvNames = DB::table("sos_personas")->where("token_personas", $tkn_people_prov)->value("id");
              $tokenProv = $JwtAuth->encriptarToken($folio_prov_temp . $selecProvNames . $tkn_people_prov . $folio_nuevo . $post_folio . $folio_temporal . $folio_prov_temp . $vEmp->id);

              $creaCatProv = new ProveedoresModelo();
              $creaCatProv->token_cat_proveedores  = $tokenProv;
              $creaCatProv->folio  = $folio_nuevo;
              $creaCatProv->post_folio = $post_folio;
              $creaCatProv->fechaAlta = $fechaAlta;
              $creaCatProv->proveedor = $selecProvNames;
              //$creaCatProv->lista_precios = NULL;
              $creaCatProv->subClase = $subtipoProv == "provMoral" ? "PM" : "PF";
              $creaCatProv->regimen_fiscal = $sql_regimen_fiscal;
              $creaCatProv->temp_folio = $folio_temporal;
              $creaCatProv->authorized = FALSE;
              $creaCatProv->status = TRUE;
              $creaCatProv->fecha_delete_prov = "";
              $creaCatProv->administrador = $vEmp->id;
              $savednewProv = $creaCatProv->save();

              if ($savednewProv) {
                $selectProvCat = $creaCatProv->id;

                $contadorInsertUbicaciones = 0;

                if ($radioProv == 'extranjero') {
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
                      "proveedor" => $selectProvCat,
                      "status" => TRUE,
                      "administrador" => $vEmp->id,
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
                          "proveedor" => $selectProvCat,
                          "status" => TRUE,
                          "administrador" => $vEmp->id,
                        ));
                    }
                  } else {
                    $tipo_direccion = "dirección fiscal";
                    $clasificacionDir = $JwtAuth->encriptar("matriz");
                    $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
                    $listnewdireccionNac = $request->input('listnewdireccionNac');
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
                        "proveedor" => $selectProvCat,
                        "status" => TRUE,
                        "administrador" => $vEmp->id,
                      ));
                  }

                  if ($fisinsertDir) {
                    $contadorInsertUbicaciones++;
                  }
                }

                //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
                if ($contadorInsertUbicaciones == 1) {
                  //return response()->json(["message" => "prueba26","code" => 200,"status" => "error"]);
                  $filepath = $vEmp->root_tkn . "/0002-cpp/catalogos/proveedores/" . $folio_prov_temp . "-" . $fechaAlta . "/";
                  //return response()->json(["message" => "prueba27","code" => 200,"status" => "error"]);
                  if (!file_exists(storage_path("/root/" . $filepath))) {
                    Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
                  }
                  //return response()->json(["message" => "prueba28","code" => 200,"status" => "error"]);
                  //QRCode::text($tokenProv)->setOutfile(Storage::path("public/root/" . $filepath . $folio_prov_temp . " - " . $fechaAlta . "-QRCode.png"))->png();
                  //$cumplimiento;
                  //$formaPagoClabeInterbank;
                  //return response()->json(["message" => "prueba32","code" => 200,"status" => "error"]);
                  $JwtAuth->insertBitacoraActividad(
                    "egresos",
                    "catalogos",
                    "proveedores",
                    $folio_prov_temp,
                    "registro en el catalogo de proveedores",
                    $empresa,
                    $usuario
                  );
                  //return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);
                  $dataMensaje = array(
                    "status" => 'success',
                    "code" => 200,
                    "message" => "Proveedor registrado satisfactoriamente con el folio $folio_prov_temp"
                  );
                } else {
                  $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Datos de personal de contacto/direcciones/creditos de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
                  );
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'ya existe un proveedor con esta información'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La empresa seleccionada es invalida'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraProveedorMax(Request $request){
    $imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');
    //formData.append('base64AltaPdfFiscal',base64AltaPdfFiscal);
    $imagenAltaPdfCumplimientoObFiscales = $request->file('imagenAltaPdfCumplimientoObFiscales');
    //formData.append('base64AltaPdfCumplimientoObFiscales',base64AltaPdfCumplimientoObFiscales);
    $imagenAltaPdfEstCuenta = $request->file('imagenAltaPdfEstCuenta');
    //formData.append('base64AltaPdfEstCuenta',base64AltaPdfEstCuenta);

    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'rfc_generico' => 'required|string',
      'prov_rfc' => 'nullable|string',
      'id_tax' => 'nullable|string',
      'radioProv' => 'required|string',
      'subtipoProv' => 'required|string',
      'name_prov' => 'required|string',
      'habilitado_para_reembolsos' => 'required|string',
      'email_para_reembolsos' => 'nullable|string',
      'info_comparativa' => 'nullable|string',
      'comercial_nombre' => 'nullable|string',
      'curp' => 'nullable|string',
      'paistoken' => 'nullable|string',
      'sitio_web' => 'nullable|string',
      'tknRegimenFiscal' => 'nullable|string',
      'cuenta_contable' => 'nullable|string',
      'decideinfocontacto' => 'required|string',
      'arrayContactoPersonalProvv_reg' => 'nullable|array',
      'tiene_docs_fiscales' => 'required|string',
      'valnoCargaDocsFiscalesRazon' => 'nullable|string',
      'aceptaCredito' => 'required|string',
      'txtMoneda' => 'nullable|string',
      'txtlimiteCredito' => 'nullable|string',
      'txtdiaspagoCredit' => 'nullable|numeric',
      'selectComienzaPagoProv' => 'nullable|string',
      'decideformapago' => 'nullable|string',
      'formaPagoAltaProv' => 'nullable|string', //"token_forma_pago" => "string",
      'tipoReferenciaPago' => 'nullable|string',
      'clabeIntBanc' => 'nullable|string',
      'receptFactura' => 'nullable|string',
      'classRecibeArtPago' => 'nullable|string',
      'cod_postal' => 'nullable|string',
      'dipomex_cod_postal_estado' => 'nullable|string',
      'dipomex_cod_postal_municipio' => 'nullable|string',
      'dipomex_cod_postal_cp' => 'nullable|string',
      'dipomex_cod_postal_colonia_vinculada' => 'nullable|string',
      'listnewdireccionNac' => 'nullable|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido'.$validate->errors(),
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $rfc_generico = $request->input('rfc_generico');
      $prov_rfc = $request->input('prov_rfc');
      $id_tax = $request->input('id_tax');
      $radioProv = $request->input('radioProv');
      $subtipoProv = $request->input('subtipoProv');
      $name_prov = $request->input('name_prov');
      $habilitado_para_reembolsos = $request->input('habilitado_para_reembolsos');
      $email_para_reembolsos = $request->input('email_para_reembolsos');
      $info_comparativa = $request->input('info_comparativa');
      $comercial_nombre = $request->input('comercial_nombre');
      $curp = $request->input('curp');
      $paistoken = $request->input('paistoken');
      $sitio_web = $request->input('sitio_web');
      $tknRegimenFiscal = $request->input('tknRegimenFiscal');
      $cuenta_contable = $request->input('cuenta_contable');
      $decideinfocontacto = $request->input('decideinfocontacto');
      $arrayContactoPersonal = $request->input('arrayContactoPersonalProvv_reg');
      $tiene_docs_fiscales = $request->input('tiene_docs_fiscales');
      $valnoCargaDocsFiscalesRazon = $request->input('valnoCargaDocsFiscalesRazon');
      $aceptaCredito = $request->input('aceptaCredito');
      $txtMoneda = $request->input('txtMoneda');
      $txtlimiteCredito = $request->input('txtlimiteCredito');
      $txtdiaspagoCredit = $request->input('txtdiaspagoCredit');
      $comienzaPagoProv = $request->input('selectComienzaPagoProv');
      $decideformapago = $request->input('decideformapago');
      $token_forma_pago = $request->input('formaPagoAltaProv'); //"token_forma_pago" => "string",
      $tipoReferenciaPago = $request->input('tipoReferenciaPago');
      $clabeIntBanc = $request->input('clabeIntBanc');
      $receptFactura = $request->input('receptFactura');
      $classRecibeArtPago = $request->input('classRecibeArtPago');
      $cod_postal = $request->input('cod_postal');
      $dipomex_cod_postal_estado = $request->input('dipomex_cod_postal_estado');
      $dipomex_cod_postal_municipio = $request->input('dipomex_cod_postal_municipio');
      $dipomex_cod_postal_cp = $request->input('dipomex_cod_postal_cp');
      $dipomex_cod_postal_colonia_vinculada = $request->input('dipomex_cod_postal_colonia_vinculada');
      $listnewdireccionNac = $request->input('listnewdireccionNac');

      $validacion_cuenta_contable = isset($cuenta_contable) && !empty($cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(), $cuenta_contable);

      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.jerarquia_main,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
        AND users.usuario_token = ?", [$empresa, $usuario]);

      if (count($queryEmp) > 0) {
        foreach ($queryEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          $autorizado = FALSE;
          $autorizacion_fecha = NULL;
          $autorizacion_user = NULL;
          $folio_nuevo = NULL;
          $post_folio =  NULL;
          $folio_temporal = NULL;
          $folio_prov = NULL;

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id 
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]
          );

          if ($vEmp->jerarquia_main == 'P') {
            $post_folio_db = DB::select(
              "SELECT post_folio FROM eegr_catalogo_proveedores WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.administrador = emp.id 
              AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
              [$empresa, $usuario]
            );

            $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
            $folio_prov = $post_folio == NULL ? 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) : 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
            $autorizado = TRUE;
            $autorizacion_fecha = time();
            $autorizacion_user = $vEmp->userr;
          } else {
            $folioSistemaTemp = DB::select("SELECT temp_folio FROM eegr_catalogo_proveedores WHERE temp_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$empresa]);
            if (count($folioSistemaTemp) > 0) {
              $queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temp_folio FROM eegr_catalogo_proveedores 
                WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
                JOIN main_empresas AS emp WHERE temp_folio IS NOT NULL AND catprov.administrador = emp.id 
                AND emp.empresa_token = ?)", [$empresa]);

              foreach ($queryFolioTmpPrv as $vTemp) {
                $folio_temporal = $vTemp->temp_folio;
              }
            } else {
              $folio_temporal = 1;
            }
            $folio_prov = 'PROV-TEMP-' . $JwtAuth->generarFolio($folio_temporal);
            $autorizado = FALSE;
          }

          $fechaAlta = time();

          if ($prov_rfc != "") {
            if (isset($prov_rfc) && isset($prov_rfc) && preg_match($patronRfc, $prov_rfc)) {
              $rfc_prov = $JwtAuth->encriptar(strtoupper($prov_rfc));
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'error al registrar rfc del proveedor'
              );
            }
          } else {
            $rfc_prov = NULL;
          }

          if ($id_tax != "") {
            if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
              $idtax = $JwtAuth->encriptar(strtoupper($id_tax));
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'error al registrar idtax del proveedor'
              );
            }
          } else {
            $idtax = NULL;
          }

          $sql_proveedor = NULL;
          $comercial_nombre_txt = NULL;
          $curp_txt = NULL;
          $pais_txt = NULL;
          $sitio_web_txt = NULL;

          if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
            if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
              $sql_proveedor = $JwtAuth->encriptar($name_prov);
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "codeProvGenError" => "nomemp",
                "message" => "Error en nombre de empresa de su proveedor"
              );
            }
            
            if (isset($comercial_nombre) && !empty($comercial_nombre)) {
              if (preg_match($patron, $comercial_nombre)) {
                $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'nomcom',
                  'message' => 'Error en nombre comercial de su proveedor'
                );
              }
            } else {
              $comercial_nombre_txt = NULL;
            }
            
            if ($radioProv == "extranjero") {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == "provMoral") {
                  //return response()->json(['message' => 'pais','codigo' => 200,'status' => 'error']);
                  if (isset($paistoken) && !empty($paistoken)) {
                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                    if (isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $sitio_web_txt = NULL;
                    }
                  } else {
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "codeProvGenError" => "pais",
                      "message" => "Error en pais de su proveedor"
                    );
                  }
                }
                //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
                if ($subtipoProv == 'provFisica') {
                  if (isset($paistoken) && !empty($paistoken)) {
                    if (isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $sitio_web_txt = NULL;
                    }

                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                  } else {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'codeProvGenError' => 'paisPF',
                      'message' => 'Error en pais de su proveedor'
                    );
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }

            if ($radioProv == 'nacional') {
              $pais_txt = '118';
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == 'provMoral') {
                  if (isset($sitio_web) && !empty($sitio_web)) {
                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitioweb = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'websitio',
                        'message' => 'Error en sitio web de su proveedor'
                      );
                    }
                  } else {
                    $sitio_web_txt = NULL;
                  }
                }

                if ($subtipoProv == 'provFisica') {
                  if (isset($curp) && !empty($curp) && preg_match($patronRfc, $curp)) {
                    $curp_txt = $JwtAuth->encriptar($curp);
                  } else {
                    $curp_txt = NULL;
                  }

                  if (isset($sitio_web) && !empty($sitio_web) && preg_match($patronUrl, $sitio_web)) {
                    $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                  } else {
                    $sitio_web_txt = NULL;
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'clbint',
              'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
            );
          }

          $sql_regimen_fiscal = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $tknRegimenFiscal)->value("id");

          $contadorContacto = 0;
          if ($decideinfocontacto == 'true') {
            for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
              if (
                preg_match($patron, $arrayContactoPersonal[$i]['paterno']) && preg_match($patron, $arrayContactoPersonal[$i]['materno']) &&
                preg_match($patron, $arrayContactoPersonal[$i]['nombre']) && preg_match($patron, $arrayContactoPersonal[$i]['area']) &&
                preg_match($patron, $arrayContactoPersonal[$i]['cargo'])
              ) {

                if (count($arrayContactoPersonal[$i]['emails']) == 0 && count($arrayContactoPersonal[$i]['telefonos']) == 0) {
                  $contadorContacto++;
                } else {
                  $contadorMails = 0;
                  $personalEmails = $arrayContactoPersonal[$i]['emails'];
                  for ($m = 0; $m < count($personalEmails); $m++) {
                    if (preg_match($patronMail, $personalEmails[$m])) {
                      $contadorMails++;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'positionErrorCode' => $m,
                        'message' => 'Error en correo electrónico de personal de contacto'
                      );
                      break;
                    }
                  }

                  $contadorTelefonos = 0;
                  $personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
                  for ($t = 0; $t < count($personalTelefonos); $t++) {
                    if (
                      preg_match($patron, $personalTelefonos[$t]['etiqueta']) &&
                      preg_match($patronNum, $personalTelefonos[$t]['telefono_complete']) &&
                      preg_match($patronCpostal, $personalTelefonos[$t]['extension'])
                    ) {
                      $contadorTelefonos++;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'positionErrorCode' => $m,
                        'message' => 'Error en teléfono de personal de contacto'
                      );
                      break;
                    }
                  }

                  if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
                    $contadorContacto++;
                  }
                }
              } else {
                if (!preg_match($patron, $arrayContactoPersonal[$i]['paterno'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en apellido paterno de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['materno'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en apellido materno de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['nombre'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en nombre de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['area'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en area de trabajo de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['cargo'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en cargo de trabajo de personal de contacto'
                  );
                }
              }
            }
          }

          $aceptCredProv = false;
          $selectMoneda = NULL;
          $selectlimiteCredito = NULL;
          $selectdiaspagoCredit = NULL;
          $selectComienzaPagoProv = NULL;

          if ($aceptaCredito == 'true') {
            if (!isset($txtMoneda)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en seleccion de moneda'
              );
            }
            if (!preg_match($patronNumCred, $txtlimiteCredito)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en limite de credito'
              );
            }

            if (!preg_match($patronNum, $txtdiaspagoCredit)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en seleccion de dias de pago'
              );
            }
            if (!preg_match($patron, $comienzaPagoProv)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en seleccion de comienzo de pago'
              );
            }
          }

          $forma_pago_ident = $decideformapago == 'true' ? DB::table("teci_forma_pago")->where("token_formapago", $token_forma_pago)->value("id") : NULL;

          if ($radioProv == 'nacional') {
            $proveedorExiste = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
            ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
            ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
            ->where('emp.empresa_token', $empresa)
            ->where('eegr_catalogo_proveedores.status', TRUE)
            ->where(function ($query) use ($rfc_prov, $idtax, $sql_proveedor) {
              if ($rfc_prov) {
                $query->orWhere('prov.rfc', $rfc_prov);
              }
              if (!empty($sql_proveedor)) {
                $query->orWhereRaw('LOWER(prov.nombre_extendido) = ?', [strtolower($sql_proveedor)]);
              }
            })->exists();
          } else {
            $proveedorExiste = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
            ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
            ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
            ->where('emp.empresa_token', $empresa)
            ->where('eegr_catalogo_proveedores.status', TRUE)
            ->where(function ($query) use ($rfc_prov, $idtax, $sql_proveedor) {
              if (!empty($idtax)) {
                $query->orWhere('prov.tax_id', $idtax);
              }
              if (!empty($sql_proveedor)) {
                $query->orWhereRaw('LOWER(prov.nombre_extendido) = ?', [strtolower($sql_proveedor)]);
              }
            })->exists();
          }
          
          if (!$proveedorExiste) {
            $tkn_people_prov = $JwtAuth->encriptarToken($fechaAlta, $name_prov, $comercial_nombre_txt, $sitio_web_txt, $pais_txt, $rfc_prov);

            $insertProv = DB::table("sos_personas")
              ->insert(array(
                "token_personas" => $tkn_people_prov,
                "nombre_extendido" => $sql_proveedor,
                "nombre_com" => $comercial_nombre_txt,
                "sitio_web" => $sitio_web_txt,
                "nacionalidad" => $pais_txt,
                "rfc_generico" => $rfc_generico,
                "rfc" => $rfc_prov,
                "tax_id" => $idtax,
                "curp" => $curp_txt,
              ));
            if ($insertProv) {
              $selecProvNames = DB::table("sos_personas")->where("token_personas", $tkn_people_prov)->value("id");
              $tokenProv = $JwtAuth->encriptarToken($selecProvNames . substr($tkn_people_prov, 0, 20) . $autorizado . $autorizacion_fecha . $autorizacion_user . $folio_nuevo . $post_folio . $folio_temporal . $folio_prov . $vEmp->id);
              $creaCatProv = new ProveedoresModelo();
              $creaCatProv->token_cat_proveedores  = $tokenProv;
              $creaCatProv->folio = $folio_nuevo;
              $creaCatProv->post_folio  = $post_folio;
              $creaCatProv->temp_folio  = $folio_temporal;
              $creaCatProv->authorized  = $autorizado;
              $creaCatProv->authorized_fecha  = $autorizacion_fecha;
              $creaCatProv->authorized_by  = $autorizacion_user;
              $creaCatProv->fechaAlta = $fechaAlta;
              $creaCatProv->proveedor = $selecProvNames;
              $creaCatProv->subClase = $subtipoProv == "provMoral" ? "PM" : "PF";
              $creaCatProv->regimen_fiscal = $sql_regimen_fiscal;
              $creaCatProv->habilitado_para_reembolsos = $subtipoProv == "provFisica" && $habilitado_para_reembolsos == 'true' ? TRUE : FALSE;
              $creaCatProv->lista_precios = NULL;
              $creaCatProv->tiene_docs_fiscales = $tiene_docs_fiscales == 'true' ? TRUE : FALSE;
              $creaCatProv->no_cuenta_fiscales = $tiene_docs_fiscales == 'true' ? $valnoCargaDocsFiscalesRazon : NULL;
              $creaCatProv->forma_pago = $forma_pago_ident;
              $creaCatProv->tipo_referencia_pago = $decideformapago == 'true' ? $tipoReferenciaPago : NULL;
              $creaCatProv->clabe_interbancaria = $decideformapago == 'true' ? $clabeIntBanc : NULL;
              $creaCatProv->receptFactura = $receptFactura == 'true' ? TRUE : FALSE;
              $creaCatProv->classRecibeArtPago = $classRecibeArtPago == 'true' ? TRUE : FALSE;
              $creaCatProv->cuenta_contable = $validacion_cuenta_contable ? $JwtAuth->encriptar($cuenta_contable) : NULL;
              $creaCatProv->utilizado = FALSE;
              $creaCatProv->status = TRUE;
              $creaCatProv->administrador = $vEmp->id;
              $savednewProv = $creaCatProv->save();
              if ($savednewProv) {
                $selectProvCat = $creaCatProv->id;

                if ($subtipoProv == "provFisica" && $habilitado_para_reembolsos == 'true') {
                  $select_fol_emp = DB::selectOne("SELECT COALESCE(MAX(folio_soli_vinculo) + 1, 1) AS folio FROM eegr_catalogo_proveedores_soli_vinc_usuario where empresa = ?", [1]);
                  DB::table("eegr_catalogo_proveedores_soli_vinc_usuario")
                    ->insert(array(
                      "token_soli_vinculo" => $JwtAuth->encriptarToken($selectProvCat, $select_fol_emp->folio, $email_para_reembolsos),
                      "folio_soli_vinculo" => $select_fol_emp->folio,
                      "fecha_soli_vinculo" => $fechaAlta,
                      "proveedor" => $selectProvCat,
                      "email_vinculo" => $JwtAuth->encriptar($email_para_reembolsos),
                      "info_comparativa" => $JwtAuth->encriptar($info_comparativa),
                      "empresa" => $vEmp->id,
                      "aprobada" => FALSE
                    ));
                  //create table eegr_catalogo_proveedores_soli_vinc_usuario(
                  //  id int(10) primary key not null auto_increment,
                  //  token_soli_vinculo text,
                  //  folio_soli_vinculo text,
                  //  fecha_soli_vinculo varchar(10),
                  //  proveedor int(10),
                  //  email_vinculo text,
                  //  empresa int(10),
                  //  aprobada int(10),
                  //  folio_aprobacion text,
                  //  usuario int(10) DEFAULT NULL,
                  //  foreign key (proveedor) references eegr_catalogo_proveedores (id),
                  //  foreign key (empresa) references main_empresas (id),
                  //  foreign key (usuario) references teci_usuarios_catalogo (id)
                  //);
                }

                if ($radioProv == 'extranjero') {
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
                      "pais_code" => $pais_txt,
                      "proveedor" => $selectProvCat,
                      "status" => TRUE,
                      "administrador" => $vEmp->id,
                    ));
                } else {
                  $tipo_direccion = "dirección fiscal";
                  $clasificacionDir = $JwtAuth->encriptar("matriz");
                  $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
                  $listnewdireccionNac = $request->input('listnewdireccionNac');
                  $fisinsertDir = DB::table("teci_direcciones")
                    ->insert(array(
                      "token_direccion" => $tokenCDir,
                      "tipo_direccion" => $tipo_direccion,
                      "clase" => $clasificacionDir,
                      "pais" => 118,
                      "pais_code" => "MEX",
                      "estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
                      "municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
                      "c_postal_edit" => $dipomex_cod_postal_cp,
                      "colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
                      "adicional" => "api",
                      "proveedor" => $selectProvCat,
                      "status" => TRUE,
                      "administrador" => $vEmp->id,
                    ));
                }

                if ($decideinfocontacto == 'true') {
                  for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
                    $contArea = $arrayContactoPersonal[$i]['area']; //area
                    $contCargo = $arrayContactoPersonal[$i]['cargo'];
                    $contApePaterno = $arrayContactoPersonal[$i]['paterno'];
                    $contApeMaterno = $arrayContactoPersonal[$i]['materno'];
                    $contNombre = $arrayContactoPersonal[$i]['nombre'];

                    $tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' . $contApePaterno . '/' . $contApeMaterno . '/' . $vEmp->id . '/' . $contArea);
                    $insertapersonalpersonas = DB::table('sos_personas')
                      ->insert(array(
                        "token_personas" => $tokenPersonasPersonal,
                        "paterno" => $JwtAuth->encriptar($contApePaterno),
                        "materno" => $JwtAuth->encriptar($contApeMaterno),
                        "nombre" => $JwtAuth->encriptar($contNombre)
                      ));

                    $tokenPersonal = $JwtAuth->encriptarToken($contApePaterno . '/' . $contNombre . '/' . $contApeMaterno . '/' . $vEmp->id . '/' . $contArea);
                    $insertapersonal = DB::table('in_egr_contacto_cliente_proveedor')->insert(
                      array(
                        "token_contacto" => $tokenPersonal,
                        "fecha_alta_contacto" => time(),
                        "folio_contacto" => $i,
                        "area_contacto" => $JwtAuth->encriptar($contArea),
                        "cargo_contacto" => $JwtAuth->encriptar($contCargo),
                        "nombre" => DB::table("sos_personas")->where("token_personas", $tokenPersonasPersonal)->value("id"),
                        "cat_proveedores" => $selectProvCat,
                        "status" => TRUE,
                        "fecha_delete" => NULL
                      )
                    );
                    $selectContacto = DB::table("in_egr_contacto_cliente_proveedor")->where("token_contacto", $tokenPersonal)->value("id");
                    $personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
                    if (count($personalTelefonos) != 0) {
                      for ($t = 0; $t < count($personalTelefonos); $t++) {
                        $contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono_complete']);
                        $contExtension = $personalTelefonos[$t]['extension'] != '' ? $JwtAuth->encriptar($personalTelefonos[$t]['extension']) : NULL;
                        $tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);
                        $principal = $t == 0 ? TRUE : FALSE;
                        //return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
                        $insertatelefonos_personal = DB::table('sos_personas_telefonos')
                          ->insert(array(
                            "token_telefono" => $tokentel,
                            "contacto_cliente_prov" => $selectContacto,
                            //"icono" => $personalTelefonos[$t]["icon"],	
                            "etiqueta" => $personalTelefonos[$t]["etiqueta"],
                            "cod_pais" => 52,
                            "telefono" => $contTelefono,
                            "principal" => $principal,
                            "extension" => $contExtension,
                            "status_telefono" => TRUE,
                            "fecha_delete_tel" => NULL,

                          ));
                      }
                    }

                    $personalEmails = $arrayContactoPersonal[$i]['emails'];
                    if (count($personalEmails) != 0) {
                      for ($m = 0; $m < count($personalEmails); $m++) {
                        $contEmail = $JwtAuth->encriptar($personalEmails[$m]);
                        $tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectContacto);
                        $insertacorreos_personal = DB::table('sos_personas_correos')
                          ->insert(array(
                            "token_correo" => $tokenEmail,
                            "contacto_cliente_prov" => $selectContacto,
                            "correo" => $contEmail,
                            "status_correo" => TRUE,
                            "fecha_delete_correo" => NULL,
                          ));
                      }
                    }
                  }
                }

                $tokenCreditos = $JwtAuth->encriptarToken($selectProvCat . $fechaAlta . $aceptaCredito);
                $insertaCreditoProv = DB::table('in_egr_creditos')
                  ->insert(array(
                    "token_creditos" => $tokenCreditos,
                    "proveedor" => $selectProvCat,
                    "aceptacredito" => $aceptaCredito == 'true' ? TRUE : FALSE,
                    "moneda_code" => $aceptaCredito == 'true' ? $txtMoneda : NULL,
                    "limite" => $aceptaCredito == 'true' ? $txtlimiteCredito : NULL,
                    "dias" => $aceptaCredito == 'true' ? $txtdiaspagoCredit : NULL,
                    "comienza" => $aceptaCredito == 'true' ? $comienzaPagoProv : NULL,
                  ));

                $filepath = $vEmp->root_tkn . "/0002-cpp/catalogos/proveedores/$fechaAlta/";
                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
                }
                if (file_exists($request->file('docSituacionFiscal'))) {
                  $namesitfiscal = $fechaAlta . '-' . $request->file('docSituacionFiscal')->getClientOriginalName();
                  $typesitfiscal = $JwtAuth->getExtensionDoc($request->file('docSituacionFiscal')->getClientMimeType());
                  $tkn_doc_sitfiscal = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $namesitfiscal);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_sitfiscal, "fcsf", $namesitfiscal, $typesitfiscal);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docSituacionFiscal'), $namesitfiscal);
                }

                if (file_exists($request->file('docCumplimientoObFiscales'))) {
                  $namecumplimiento = $fechaAlta . '-' . $request->file('docCumplimientoObFiscales')->getClientOriginalName();
                  $typecumplimiento = $JwtAuth->getExtensionDoc($request->file('docCumplimientoObFiscales')->getClientMimeType());
                  $tkn_doc_cumplimiento = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $namecumplimiento);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_cumplimiento, "cuof", $namecumplimiento, $typecumplimiento);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docCumplimientoObFiscales'), $namecumplimiento);
                }

                if (file_exists($request->file('docContrato'))) {
                  $namecontrato = $fechaAlta . '-' . $request->file('docContrato')->getClientOriginalName();
                  $typecontrato = $JwtAuth->getExtensionDoc($request->file('docContrato')->getClientMimeType());
                  $tkn_doc_contrato = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $namecontrato);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_contrato, "fcnt", $namecontrato, $typecontrato);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docContrato'), $namecontrato);
                }

                if (isset($_FILES['files_anexos']) && !empty($_FILES['files_anexos'])) {
                  $anexo_archivos = $_FILES["files_anexos"];
                  $anexo_archivos_strings = json_decode(json_encode($_FILES["files_anexos"]["name"]));
                  if (count($anexo_archivos_strings) > 0) {
                    for ($i = 0; $i < count($anexo_archivos_strings); $i++) {
                      $docAnexo_temporal = $anexo_archivos["tmp_name"][$i];
                      $docAnexo_name = "anexos/" . $anexo_archivos["name"][$i];
                      $docAnexo_type = $JwtAuth->getExtensionDoc($anexo_archivos["type"][$i]);
                      $docAnexo_tknn = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $docAnexo_name);
                      $JwtAuth->registraDocsProveedor($tokenProv, $docAnexo_tknn, "anex", $docAnexo_name, $docAnexo_type);
                      Storage::putFileAs("/public/root/" . $filepath, $docAnexo_temporal, $docAnexo_name);
                    }
                  }
                }

                if (file_exists($request->file('docEstadoCuenta'))) {
                  $nameestadocuenta = $fechaAlta . '-' . $request->file('docEstadoCuenta')->getClientOriginalName();
                  $typeestadocuenta = $JwtAuth->getExtensionDoc($request->file('docEstadoCuenta')->getClientMimeType());
                  $tkn_doc_estadocuenta = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $nameestadocuenta);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_estadocuenta, "ecue", $nameestadocuenta, $typeestadocuenta);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docEstadoCuenta'), $nameestadocuenta);
                }

                $JwtAuth->insertBitacoraActividad(
                  "egresos",
                  "catalogos",
                  "proveedores",
                  $folio_prov,
                  "registro en el catalogo de proveedores",
                  $empresa,
                  $usuario
                );
                //return response()->json(['message' => 'gantt_diagram','code' => 200,'status' => 'error']);
                //return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);

                if ($vEmp->jerarquia_main == 'P') {
                  if (count($folioSistema) == 0) {
                    $insertSistema = DB::table("sos_last_folders")
                      ->insert(
                        array(
                          "egr_proveedores" => TRUE,
                          "folder" => 1,
                          "post_folder" => $post_folio,
                          "empresa" => $vEmp->id,
                        )
                      );
                  } else {
                    $regFolder = DB::table("sos_last_folders AS fold")
                      ->join("main_empresas AS emp", "fold.empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                      ->where([
                        "fold.egr_proveedores" => TRUE,
                        "emp.empresa_token" => $empresa,
                        "users.usuario_token" => $usuario,
                      ])
                      ->limit(1)->update(
                        array(
                          "fold.folder" => $folio_nuevo,
                          "fold.post_folder" => $post_folio,
                        )
                      );
                  }
                }

                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'Proveedor registrado satisfactoriamente con el folio ' . $folio_prov
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'ya existe un proveedor con esta información'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La empresa seleccionada es invalida'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
