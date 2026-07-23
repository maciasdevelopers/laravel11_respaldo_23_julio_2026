<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use Carbon\Carbon;

class FNZS_SolicitudesCancelacionController extends Controller{
  //funciones para solicitudes de cancelación realizadas
	public function solicitudesCancelacion(Request $request){
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
      $periodo = $request->input('periodo');
      //da_te_default_timezone_set('America/Mexico_City');

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
      
      $soliCancelPago = DB::table("fnzs_pagos_soli_cancelacion AS pcanc")
      ->join("main_empresas AS emp","pcanc.pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('pcanc.pago_cancel', function ($query) {
        $query->select('pay.id')->from('fnzs_pagos_pago AS pay')
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc", "pay.id", "=","ord_vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
        ->whereNull('ord_pag.nomina_main');
      })
      ->where([
        "pcanc.pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'PAGO' AS tipo_solicitud"),
        "pcanc.token_cancel_solip AS token_soli",
        "pcanc.folio_cancel_solip AS folio_soli",
        "pcanc.pago_cancel AS doc_anterior",
        "pcanc.fecha_cont_cancel_solip AS fecha_contabilizacion",
        "pcanc.pago_cancel_observaciones_mov AS observaciones",
        "pcanc.pago_cancel_realizada AS cancel_realizada"
      ]);
      
      $soliCancelNominaPago = DB::table("fnzs_pagos_soli_cancelacion AS pcanc")
      ->join("main_empresas AS emp","pcanc.pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('pcanc.pago_cancel', function ($query) {
        $query->select('pay.id')->from('fnzs_pagos_pago AS pay')
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc", "pay.id", "=","ord_vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
        ->whereNotNull('ord_pag.nomina_main');
      })
      ->where([
        "pcanc.pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'PAGO-NOMINA' AS tipo_solicitud"),
        "pcanc.token_cancel_solip AS token_soli",
        "pcanc.folio_cancel_solip AS folio_soli",
        "pcanc.pago_cancel AS doc_anterior",
        "pcanc.fecha_cont_cancel_solip AS fecha_contabilizacion",
        "pcanc.pago_cancel_observaciones_mov AS observaciones",
        "pcanc.pago_cancel_realizada AS cancel_realizada"
      ]);
  
      $soliCancelOrdenPago = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      //->join("fnzs_pagos_pago AS pago","ordcanc.pago_cancel","pago.id")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')->from('fnzs_pagos_orden')->whereNull('nomina_main');
      })
      ->where([
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE PAGO' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);

      $soliCancelOrdenDisperNominaEfectivo = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      //->join("fnzs_pagos_pago AS pago","ordcanc.pago_cancel","pago.id")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      //->whereNotNull('nomina_main')
      //->whereNull('nomina_en_especie')
      ->whereIn('ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')
        ->from('fnzs_pagos_orden')
        ->whereNotNull('nomina_main')
        ->whereNull('nomina_en_especie');
      })
      ->where([
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE DISPERSION DE NOMINA EN EFECTIVO' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);

      $soliCancelOrdenDisperNominaEspecie = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')
        ->from('fnzs_pagos_orden')
        ->whereNotNull('nomina_main')
        ->whereNotNull('nomina_en_especie');
      })
      ->where([
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE DISPERSION DE NOMINA EN ESPECIE' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);

      $soliCancelReemPago = DB::table("terc_reembolsos_cancelaciones AS rcanc")
      ->join("main_empresas AS emp","rcanc.reem_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->where([
        "rcanc.reem_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'REEMBOLSO' AS tipo_solicitud"),
        "rcanc.token_cancel_reem AS token_soli",
        "rcanc.folio_cancel_reem AS folio_soli",
        "rcanc.reem_cancel_main AS doc_anterior",
        "rcanc.fecha_cancel_reem AS fecha_contabilizacion",
        "rcanc.reem_cancel_observaciones_mov AS observaciones",
        "rcanc.reem_cancel_realizada AS cancel_realizada"
      ]);
      
      $soliCancelMCP = DB::table("fnzs_mov_cuent_propias_cancelacion AS mcpCanc")
      ->join("main_empresas AS emp","mcpCanc.mcp_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->where([
        "mcpCanc.mcp_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'CUENTAS PROPIAS' AS tipo_solicitud"),
        "mcpCanc.token_cancel_mcp AS token_soli",
        "mcpCanc.folio_cancel_mcp AS folio_soli",
        "mcpCanc.mcp_cancel AS doc_anterior",
        "mcpCanc.fecha_cont_cancel_mcp AS fecha_contabilizacion",
        "mcpCanc.mcp_cancel_observaciones_mov AS observaciones",
        "mcpCanc.mcp_cancel_realizada AS cancel_realizada"
      ]);
  
      $soliCancelAnticipo = DB::table("fnzs_anticipos_soli_cancelacion AS antcanc")
      ->join("main_empresas AS emp","antcanc.anticipo_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('antcanc.anticipo_cancel_uuid', function ($query) {
        $query->select('uuid_anticipo')->from('eegr_catalogo_proveedores_anticipo');
      })
      ->where([
        "antcanc.anticipo_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ANTICIPO' AS tipo_solicitud"),
        "antcanc.token_cancel_soliant AS token_soli",
        "antcanc.folio_cancel_soliant AS folio_soli",
        "antcanc.anticipo_cancel_uuid AS doc_anterior",
        "antcanc.fecha_cont_cancel_soliant AS fecha_contabilizacion",
        "antcanc.anticipo_cancel_observaciones_mov AS observaciones",
        "antcanc.anticipo_cancel_realizada AS cancel_realizada"
      ]);

      $unionCancelSoli = $soliCancelPago
      ->unionAll($soliCancelNominaPago)
      ->unionAll($soliCancelOrdenPago)
      ->unionAll($soliCancelOrdenDisperNominaEfectivo)
      ->unionAll($soliCancelOrdenDisperNominaEspecie)
      //->unionAll($soliCancelReemPago)
      ->unionAll($soliCancelMCP)
      ->unionAll($soliCancelAnticipo);
  
      $querySoliCancel = DB::table(DB::raw("({$unionCancelSoli->toSql()}) as cancelacion_soli"))
      ->mergeBindings($unionCancelSoli) // Importante para no perder los parámetros del WHERE
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("fecha_contabilizacion", "desc")
      ->get();

      if ($querySoliCancel->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron solicitudes de cancelación registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $lista_solicitudes = array();
        
        $idDocAnterior = $querySoliCancel->pluck('doc_anterior')->filter()->unique()->toArray();
        $pagoMap = DB::table('fnzs_pagos_pago')->whereIn('id',$idDocAnterior)->get()->keyBy('id');
        $ordenPagoMap = DB::table('fnzs_pagos_orden')->whereIn('id',$idDocAnterior)->get()->keyBy('id');
        $reembolsoMap = DB::table("terc_reembolso_main")->whereIn('id',$idDocAnterior)->get()->keyBy('id');
        $cuentasPropiasMap = DB::table("fnzs_movimientos_cuentas_propias")->whereIn('id',$idDocAnterior)->get()->keyBy('id');
        $anticipoMap = DB::table("eegr_catalogo_proveedores_anticipo")->whereIn('uuid_anticipo',$idDocAnterior)->get()->keyBy('uuid_anticipo');
  
        $idSoliCanc = $querySoliCancel->pluck('token_soli')->filter()->unique()->toArray();
        $reemSoliMap = DB::table("terc_reembolso_solicitud AS reem_soli")
        ->join("terc_reembolsos_cancelaciones AS canc", "reem_soli.id", "=", "canc.reem_cancel_soli")
        ->whereIn("canc.token_cancel_reem",$idSoliCanc)
        ->select('canc.token_cancel_reem AS token_soli','reem_soli.token_solicitud_reem','reem_soli.folio_solicitud')
        ->get()->keyBy('token_soli');
  
        $reemCompraMap = DB::table("eegr_compras AS comp")
        ->join("terc_reembolsos_cancelaciones AS canc", "comp.id", "=", "canc.reem_cancel_compra_vinc")
        ->whereIn("canc.token_cancel_reem",$idSoliCanc)
        ->select('canc.token_cancel_reem AS token_soli','comp.token_compras','comp.folio_compra','comp.post_folio')
        ->get()->keyBy('token_soli');
  
        foreach ($querySoliCancel as $cSoli) {
          $pay = $pagoMap->get($cSoli->doc_anterior);
          $ordPag = $ordenPagoMap->get($cSoli->doc_anterior);
          $reem_data = $reembolsoMap->get($cSoli->doc_anterior);
          $reem_soli_data = $reemSoliMap->get($cSoli->token_soli);
          $mcp_data = $cuentasPropiasMap->get($cSoli->doc_anterior);
          $ant_data = $anticipoMap->get($cSoli->doc_anterior);
          $solicitud_folio = "";
          $doc_anterior_token = "";
          $doc_anterior_folio = "";
  
          $soli_reem_token_ = "";
          $soli_reem_folio_ = "";
  
          $compras_token_ = "";
          $compras_folio_ = "";
  
          switch ($cSoli->tipo_solicitud) {
            case 'PAGO':
              $solicitud_folio = 'PAG-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $pay->token_pagos;
              $doc_anterior_folio = "PAGO-".$JwtAuth->generarFolio($pay->folio_pagos);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            case 'PAGO-NOMINA':
              $solicitud_folio = 'PAG-DISP-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $pay->token_pagos;
              $doc_anterior_folio = "PAGO-".$JwtAuth->generarFolio($pay->folio_pagos);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            case 'ORDEN DE PAGO':
              $solicitud_folio = 'ORDPAG-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $ordPag->token_ordenPago;
              $doc_anterior_folio = "ORDP-".$JwtAuth->generarFolio($ordPag->folio_ordenPago);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            case 'ORDEN DE DISPERSION DE NOMINA EN EFECTIVO':
              $solicitud_folio = 'ORD-DISPER-EF-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $ordPag->token_ordenPago;
              $doc_anterior_folio = "ORDP-".$JwtAuth->generarFolio($ordPag->folio_ordenPago);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            case 'ORDEN DE DISPERSION DE NOMINA EN ESPECIE':
              $solicitud_folio = 'ORD-DISPER-ES-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $ordPag->token_ordenPago;
              $doc_anterior_folio = "ORDP-".$JwtAuth->generarFolio($ordPag->folio_ordenPago);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            case 'REEMBOLSO':
              $compras_data = $reemCompraMap->get($cSoli->token_soli);
              $solicitud_folio = 'REEM-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $reem_data->token_reem;
              $doc_anterior_folio = 'REEM-'.$JwtAuth->generarFolio($reem_data->folio_reem).(!is_null($reem_data->post_folio_reem) ? '-'.$reem_data->post_folio_reem : '');
              $soli_reem_token_ = $reem_soli_data->token_solicitud_reem;
              $soli_reem_folio_ = $JwtAuth->generarFolio($reem_soli_data->folio_solicitud);
              $compras_token_ = $compras_data->token_compras;
              $compras_folio_ = 'COMP-'.$JwtAuth->generarFolio($compras_data->folio_compra).(!is_null($compras_data->post_folio) ? '-'.$compras_data->post_folio : '');
              break;
            case 'CUENTAS PROPIAS':
              $solicitud_folio = 'MCP-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $mcp_data->movimiento_cp_token;
              $doc_anterior_folio = 'MCP-'.$JwtAuth->generarFolio($mcp_data->movimiento_cp_folio);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            case 'ANTICIPO':
              $solicitud_folio = 'ANT-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $ant_data->uuid_anticipo;
              $doc_anterior_folio = 'ANT-'.$JwtAuth->generarFolio($ant_data->folio_anticipo);
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
            default:
              $solicitud_folio = "";
              $doc_anterior_token = "";
              $doc_anterior_folio = "";
              $soli_reem_token_ = "";
              $soli_reem_folio_ = "";
              $compras_token_ = "";
              $compras_folio_ = "";
              break;
          }
          
          //dd([
          //  $cSoli->fecha_contabilizacion,
          //  date('Y-m-d H:i:s', $cSoli->fecha_contabilizacion),
          //  gmdate('Y-m-d H:i:s', $cSoli->fecha_contabilizacion),
          //]);
          //echo "date ".date('Y-m-d H:i:s', $cSoli->fecha_contabilizacion)." gmdate ".gmdate('Y-m-d H:i:s', $cSoli->fecha_contabilizacion);

          $fecha_contabilizacion = Carbon::createFromTimestamp($cSoli->fecha_contabilizacion)->toDateString();//, 'America/Mexico_City'
          $row_pay = array(
            "cancel_soli_token" => $cSoli->token_soli,
            "tipo_solicitud" => $cSoli->tipo_solicitud,
            "cancel_soli_folio" => $solicitud_folio,
            "doc_anterior_token" => $doc_anterior_token,
            "doc_anterior_folio" => $doc_anterior_folio,
            "soli_reem_token" => $soli_reem_token_,
            "soli_reem_folio" => $soli_reem_folio_,
            "compras_token" => $compras_token_,
            "compras_folio" => $compras_folio_,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $cSoli->fecha_contabilizacion)." ".$fecha_contabilizacion,
            "fecha_contabilizacion_date" => date('Y-m-d H:i:s', $cSoli->fecha_contabilizacion),
            "fecha_contabilizacion_gmdate" => gmdate('Y-m-d H:i:s', $cSoli->fecha_contabilizacion),
            "cancel_soli_observaciones" => $JwtAuth->desencriptar($cSoli->observaciones),
            "cancel_soli_cancel_realizada" => (bool)$cSoli->cancel_realizada,
            "comentarios_confirma_cancelacion" => "",
            "f_contab_confirma_cancelacion" => ""
          );
          $lista_solicitudes[] = $row_pay;
        }
        $dataMensaje = array("status" => "success", "code" => 200, "solicitudes" => $lista_solicitudes);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}
  
	public function recargaSolicitudCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cancel_soli_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $cancel_soli_token = $request->input('cancel_soli_token');
      
      $soliCancelPago = DB::table("fnzs_pagos_soli_cancelacion AS pcanc")
      ->join("main_empresas AS emp","pcanc.pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('pcanc.pago_cancel', function ($query) {
        $query->select('pay.id')->from('fnzs_pagos_pago AS pay')
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc", "pay.id", "=","ord_vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
        ->whereNull('ord_pag.nomina_main');
      })
      ->where([
        "pcanc.token_cancel_solip" => $cancel_soli_token,
        "pcanc.pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'PAGO' AS tipo_solicitud"),
        "pcanc.token_cancel_solip AS token_soli",
        "pcanc.folio_cancel_solip AS folio_soli",
        "pcanc.pago_cancel AS doc_anterior",
        "pcanc.fecha_cont_cancel_solip AS fecha_contabilizacion",
        "pcanc.pago_cancel_observaciones_mov AS observaciones",
        "pcanc.pago_cancel_realizada AS cancel_realizada"
      ]);
      
      $soliCancelNominaPago = DB::table("fnzs_pagos_soli_cancelacion AS pcanc")
      ->join("main_empresas AS emp","pcanc.pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('pcanc.pago_cancel', function ($query) {
        $query->select('pay.id')->from('fnzs_pagos_pago AS pay')
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc", "pay.id", "=","ord_vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
        ->whereNotNull('ord_pag.nomina_main');
      })
      ->where([
        "pcanc.token_cancel_solip" => $cancel_soli_token,
        "pcanc.pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'PAGO-NOMINA' AS tipo_solicitud"),
        "pcanc.token_cancel_solip AS token_soli",
        "pcanc.folio_cancel_solip AS folio_soli",
        "pcanc.pago_cancel AS doc_anterior",
        "pcanc.fecha_cont_cancel_solip AS fecha_contabilizacion",
        "pcanc.pago_cancel_observaciones_mov AS observaciones",
        "pcanc.pago_cancel_realizada AS cancel_realizada"
      ]);
  
      $soliCancelOrdenPago = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      //->join("fnzs_pagos_pago AS pago","ordcanc.pago_cancel","pago.id")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')->from('fnzs_pagos_orden')->whereNull('nomina_main');
      })
      ->where([
        "ordcanc.token_cancel_soliordp" => $cancel_soli_token,
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE PAGO' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);

      $soliCancelOrdenDisperNominaEfectivo = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      //->join("fnzs_pagos_pago AS pago","ordcanc.pago_cancel","pago.id")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      //->whereNotNull('nomina_main')
      //->whereNull('nomina_en_especie')
      ->whereIn('ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')
        ->from('fnzs_pagos_orden')
        ->whereNotNull('nomina_main')
        ->whereNull('nomina_en_especie');
      })
      ->where([
        "ordcanc.token_cancel_soliordp" => $cancel_soli_token,
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE DISPERSION DE NOMINA EN EFECTIVO' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);

      $soliCancelOrdenDisperNominaEspecie = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')
        ->from('fnzs_pagos_orden')
        ->whereNotNull('nomina_main')
        ->whereNotNull('nomina_en_especie');
      })
      ->where([
        "ordcanc.token_cancel_soliordp" => $cancel_soli_token,
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE DISPERSION DE NOMINA EN ESPECIE' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);

      $soliCancelReemPago = DB::table("terc_reembolsos_cancelaciones AS rcanc")
      ->join("main_empresas AS emp","rcanc.reem_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->where([
        "rcanc.token_cancel_reem" => $cancel_soli_token,
        "rcanc.reem_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'REEMBOLSO' AS tipo_solicitud"),
        "rcanc.token_cancel_reem AS token_soli",
        "rcanc.folio_cancel_reem AS folio_soli",
        "rcanc.reem_cancel_main AS doc_anterior",
        "rcanc.fecha_cancel_reem AS fecha_contabilizacion",
        "rcanc.reem_cancel_observaciones_mov AS observaciones",
        "rcanc.reem_cancel_realizada AS cancel_realizada"
      ]);
      
      $soliCancelMCP = DB::table("fnzs_mov_cuent_propias_cancelacion AS mcpCanc")
      ->join("main_empresas AS emp","mcpCanc.mcp_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->where([
        "mcpCanc.token_cancel_mcp" => $cancel_soli_token,
        "mcpCanc.mcp_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'CUENTAS PROPIAS' AS tipo_solicitud"),
        "mcpCanc.token_cancel_mcp AS token_soli",
        "mcpCanc.folio_cancel_mcp AS folio_soli",
        "mcpCanc.mcp_cancel AS doc_anterior",
        "mcpCanc.fecha_cont_cancel_mcp AS fecha_contabilizacion",
        "mcpCanc.mcp_cancel_observaciones_mov AS observaciones",
        "mcpCanc.mcp_cancel_realizada AS cancel_realizada"
      ]);
  
      $soliCancelAnticipo = DB::table("fnzs_anticipos_soli_cancelacion AS antcanc")
      ->join("main_empresas AS emp","antcanc.anticipo_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->whereIn('antcanc.anticipo_cancel_uuid', function ($query) {
        $query->select('uuid_anticipo')->from('eegr_catalogo_proveedores_anticipo');
      })
      ->where([
        "antcanc.token_cancel_soliant" => $cancel_soli_token,
        "antcanc.anticipo_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ANTICIPO' AS tipo_solicitud"),
        "antcanc.token_cancel_soliant AS token_soli",
        "antcanc.folio_cancel_soliant AS folio_soli",
        "antcanc.anticipo_cancel_uuid AS doc_anterior",
        "antcanc.fecha_cont_cancel_soliant AS fecha_contabilizacion",
        "antcanc.anticipo_cancel_observaciones_mov AS observaciones",
        "antcanc.anticipo_cancel_realizada AS cancel_realizada"
      ]);

      $unionCancelSoli = $soliCancelPago
      ->unionAll($soliCancelNominaPago)
      ->unionAll($soliCancelOrdenPago)
      ->unionAll($soliCancelOrdenDisperNominaEfectivo)
      ->unionAll($soliCancelOrdenDisperNominaEspecie)
      //->unionAll($soliCancelReemPago)
      ->unionAll($soliCancelMCP)
      ->unionAll($soliCancelAnticipo);
  
      $cSoli = DB::table(DB::raw("({$unionCancelSoli->toSql()}) as cancelacion_soli"))
      ->mergeBindings($unionCancelSoli)
      //->where("token_soli",$cancel_soli_token)
      ->first();

      if (!$cSoli) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron solicitudes de cancelación registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $idDocAnterior = $cSoli->doc_anterior;
  
        $pay = DB::table('fnzs_pagos_pago')->where('id',$idDocAnterior)->first();
        $ordPag = DB::table('fnzs_pagos_orden')->where('id',$idDocAnterior)->first();
        $reem_data = DB::table("terc_reembolso_main")->where('id',$idDocAnterior)->first();

        $reem_soli_data = DB::table("terc_reembolso_solicitud AS reem_soli")
        ->join("terc_reembolsos_cancelaciones AS canc", "reem_soli.id", "=", "canc.reem_cancel_soli")
        ->where("canc.token_cancel_reem",$cSoli->token_soli)
        ->select('canc.token_cancel_reem AS token_soli','reem_soli.token_solicitud_reem','reem_soli.folio_solicitud')
        ->first();

        $mcp_data = DB::table("fnzs_movimientos_cuentas_propias")->where('id',$idDocAnterior)->first();
        $ant_data = DB::table("eegr_catalogo_proveedores_anticipo")->where('uuid_anticipo',$idDocAnterior)->first();
        $solicitud_folio = "";
        $doc_anterior_token = "";
        $doc_anterior_folio = "";

        $soli_reem_token_ = "";
        $soli_reem_folio_ = "";

        $compras_token_ = "";
        $compras_folio_ = "";

        switch ($cSoli->tipo_solicitud) {
          case 'PAGO':
            $solicitud_folio = 'PAG-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $pay->token_pagos;
            $doc_anterior_folio = "PAGO-".$JwtAuth->generarFolio($pay->folio_pagos);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          case 'PAGO-NOMINA':
            $solicitud_folio = 'PAG-DISP-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $pay->token_pagos;
            $doc_anterior_folio = "PAGO-".$JwtAuth->generarFolio($pay->folio_pagos);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          case 'ORDEN DE PAGO':
            $solicitud_folio = 'ORDPAG-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $ordPag->token_ordenPago;
            $doc_anterior_folio = "ORDP-".$JwtAuth->generarFolio($ordPag->folio_ordenPago);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          case 'ORDEN DE DISPERSION DE NOMINA EN EFECTIVO':
            $solicitud_folio = 'ORD-DISPER-EF-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $ordPag->token_ordenPago;
            $doc_anterior_folio = "ORDP-".$JwtAuth->generarFolio($ordPag->folio_ordenPago);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          case 'ORDEN DE DISPERSION DE NOMINA EN ESPECIE':
            $solicitud_folio = 'ORD-DISPER-ES-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $ordPag->token_ordenPago;
            $doc_anterior_folio = "ORDP-".$JwtAuth->generarFolio($ordPag->folio_ordenPago);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          case 'REEMBOLSO':
            $compras_data = DB::table("eegr_compras AS comp")
            ->join("terc_reembolsos_cancelaciones AS canc", "comp.id", "=", "canc.reem_cancel_compra_vinc")
            ->where("canc.token_cancel_reem",$cSoli->token_soli)
            ->select('canc.token_cancel_reem AS token_soli','comp.token_compras','comp.folio_compra','comp.post_folio')
            ->first();

            $solicitud_folio = 'REEM-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $reem_data->token_reem;
            $doc_anterior_folio = 'REEM-'.$JwtAuth->generarFolio($reem_data->folio_reem).(!is_null($reem_data->post_folio_reem) ? '-'.$reem_data->post_folio_reem : '');
            $soli_reem_token_ = $reem_soli_data->token_solicitud_reem;
            $soli_reem_folio_ = $JwtAuth->generarFolio($reem_soli_data->folio_solicitud);
            $compras_token_ = $compras_data->token_compras;
            $compras_folio_ = 'COMP-'.$JwtAuth->generarFolio($compras_data->folio_compra).(!is_null($compras_data->post_folio) ? '-'.$compras_data->post_folio : '');
            break;
          case 'CUENTAS PROPIAS':
            $solicitud_folio = 'MCP-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $mcp_data->movimiento_cp_token;
            $doc_anterior_folio = 'MCP-'.$JwtAuth->generarFolio($mcp_data->movimiento_cp_folio);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          case 'ANTICIPO':
            $solicitud_folio = 'ANT-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
            $doc_anterior_token = $ant_data->uuid_anticipo;
            $doc_anterior_folio = 'ANT-'.$JwtAuth->generarFolio($ant_data->folio_anticipo);
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
          default:
            $solicitud_folio = "";
            $doc_anterior_token = "";
            $doc_anterior_folio = "";
            $soli_reem_token_ = "";
            $soli_reem_folio_ = "";
            $compras_token_ = "";
            $compras_folio_ = "";
            break;
        }

        $fecha_contabilizacion = Carbon::createFromTimestamp($cSoli->fecha_contabilizacion)->toDateString();//, 'America/Mexico_City'
        
        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "cancel_soli_token" => $cSoli->token_soli,
          "tipo_solicitud" => $cSoli->tipo_solicitud,
          "cancel_soli_folio" => $solicitud_folio,
          "doc_anterior_token" => $doc_anterior_token,
          "doc_anterior_folio" => $doc_anterior_folio,
          "soli_reem_token" => $soli_reem_token_,
          "soli_reem_folio" => $soli_reem_folio_,
          "compras_token" => $compras_token_,
          "compras_folio" => $compras_folio_,
          "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $cSoli->fecha_contabilizacion)." ".$fecha_contabilizacion,
          "fecha_contabilizacion_date" => date('Y-m-d H:i:s', $cSoli->fecha_contabilizacion),
          "fecha_contabilizacion_gmdate" => gmdate('Y-m-d H:i:s', $cSoli->fecha_contabilizacion),
          "cancel_soli_observaciones" => $JwtAuth->desencriptar($cSoli->observaciones),
          "cancel_soli_cancel_realizada" => (bool)$cSoli->cancel_realizada,
          "comentarios_confirma_cancelacion" => "",
          "f_contab_confirma_cancelacion" => ""
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //anticipos
	public function solicitudCancelacionAnticipo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliant' => 'required|string',
      'anticipo_uuid' => 'required|string'
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
      $token_cancel_soliant = $request->input('token_cancel_soliant');
      $anticipo_uuid = $request->input('anticipo_uuid');
      
			$queryAnticipo = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
      ->join("fnzs_anticipos_soli_cancelacion AS antCanc", "ant.uuid_anticipo", "=","antCanc.anticipo_cancel_uuid")
			->join("main_empresas AS emp", "ant.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
			->where([
				"antCanc.token_cancel_soliant" => $token_cancel_soliant,
				"ant.uuid_anticipo" => $anticipo_uuid,
				"ant.estatus_anticipo" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'antCanc.token_cancel_soliant',
        'antCanc.folio_cancel_soliant',
        'antCanc.fecha_cont_cancel_soliant',
        'antCanc.anticipo_cancel_observaciones_mov',
        'ant.anticipo_cancelado As ant_cancel',
        'ant.*',
        'emp.empresa_token', 
        'emp.zona_horaria'
      )
			->get();

      if ($queryAnticipo->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $solicitud_desglose = [];
        foreach ($queryAnticipo as $vAnt) {
          $solicitud_desglose[] = [
            "token_cancel_soliant" => $vAnt->token_cancel_soliant,
            "folio_cancel_soliant" => 'ANT-SOLI-CANC-'.$JwtAuth->generarFolio($vAnt->folio_cancel_soliant),
            "anticipo_cancel_observaciones_mov" => $JwtAuth->desencriptar($vAnt->anticipo_cancel_observaciones_mov),
          ];
        }
        
        $queryPagoOrden = DB::table("fnzs_pagos_orden AS order")
        ->where([
			  	"order.ord_anticipo" => $anticipo_uuid,
			  	"order.status_ordenPago" => TRUE
			  ])
        ->select(
          'order.id As id_orden_pago',
          'order.pago_orden_cancelada As op_cancel',
          'order.*'
        )
			  ->orderBy("order.folio_ordenPago", "DESC")->get();
        //$orderpay->ord_anticipo

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "solicitud_desglose" => $solicitud_desglose,
          "orden_pago" => $this->eachGeneralOrdenesPago($queryPagoOrden,$empresa,$usuario,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionAnticipo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliant' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
			$token_cancel_soliant = $request->input('token_cancel_soliant');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_token_cancel_soliant = isset($token_cancel_soliant) && !empty($token_cancel_soliant);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_token_cancel_soliant && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.id AS userr','users.jerarquia_main')
				->first();
				
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
					$anticipoData = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
          ->join("fnzs_anticipos_soli_cancelacion AS antCanc", "ant.uuid_anticipo", "=","antCanc.anticipo_cancel_uuid")
          ->join("main_empresas AS emp", "ant.empresa", "emp.id")
          ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
          ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
          ->where([
            "antCanc.token_cancel_soliant" => $token_cancel_soliant,
            "ant.estatus_anticipo" => TRUE,
            "emp.empresa_token" => $empresa,
            "users.usuario_token" => $usuario
          ])
          ->select('ant.uuid_anticipo')
          ->lockForUpdate()->first();
          //if (!$ordenData) continue;

					$queryOrdenesData = DB::table("fnzs_pagos_orden")
          ->where([
            "ord_anticipo" => $anticipoData->uuid_anticipo,
            "status_ordenPago" => TRUE
          ])
          ->select('id As id_orden_pago','token_ordenPago')
          ->lockForUpdate()
          ->get();
          //if (!$ordenData) continue;

          foreach ($queryOrdenesData as $ordenData) {
            DB::table("fnzs_pagos_pago_ordenes_vinculadas")
            ->where("orden_pago_vinculada",$ordenData->id_orden_pago)
            ->update(array("vinculo_cancelado" => TRUE));
  
            $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL; 
  
            $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->where([
              "pay.pago_cancelado" => FALSE,
              "pay.status_pagos" => TRUE,
              "vinc.orden_pago_vinculada" => $ordenData->id_orden_pago
            ])
            ->select("pay.id AS pay_id", "pay.token_pagos")
            ->get();
  
            foreach ($queryPagosDone as $vPayDone) {
              $maxFolioPacoCancel = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');
              $folioCancelPagos = $maxFolioPacoCancel ? $maxFolioPacoCancel + 1 : 1;
  
              DB::table("fnzs_pagos_pago")->where("id",$vPayDone->pay_id)
              ->limit(1)->update(array(
                "pago_cancelado" => TRUE,
                "pago_cancelado_user" => $user_jerarquia,
                "pago_folio_cancelacion" => $folioCancelPagos,
                "pago_fecha_cancelacion" => $ahora,
                "pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
                "pago_comentarios_cancelacion" => $comentarios_encriptados
              ));
  
              $queryActiMovAcree = DB::table("fnzs_actividad_movimientos AS act_mov")
              ->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
              ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
              ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
              ->where("pag.id",$vPayDone->pay_id)
              ->where("pag.status_pagos",TRUE)
              ->select(
                "act_mov.id AS idMov",
                "act_mov.token_movimiento",
                "act_mov.folio_movimiento",
                "act_mov.fecha_sistema",
                "act_mov.fecha_contabilizacion_movimiento",
                "act_mov.movimiento_cancelado",
                "act_mov.folio_cancelacion",
                "act_mov.fecha_cancelacion",
                "act_mov.movimiento_asociado",
                "act_mov.tipo_movimiento",
                "act_mov.subtipo_movimiento",
                "act_mov.concepto_movimiento",
                "act_mov.responsable",
                "act_mov.caja",
                "act_mov.cuenta_bancaria",
                "act_mov.cuenta_monedero",
                "act_mov.monto_aplicado",
                "act_mov.moneda_movimiento",
                "act_mov.tipo_cambio_movimiento",
                "act_mov.observaciones_movimiento",
                "act_mov.pago",
                "act_mov.acreedor_movimiento",
                "act_mov.ajuste",
                "act_mov.empresa",
                "acr_mov.condicion_acree_mov",
                "acr_mov.acre_tipo_cambio",
                "acr_mov.acre_mov_moneda",
                "acr_mov.vinc_acreedor"
              )
              ->get();
              
              if (!$queryActiMovAcree->isEmpty()) {
                $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMovAcree,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
              }
  
              $queryActiMovDeu = DB::table("fnzs_actividad_movimientos AS act_mov")
              ->join("fnzs_catalogo_deudores_movimientos AS deu_mov", "act_mov.deudor_movimiento", "=","deu_mov.id")
              ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deu_mov.id", "=","vinc.mov_realizado")
              ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
              ->where("pag.id",$vPayDone->pay_id)
              ->where("pag.status_pagos",TRUE)
              ->select(
                "act_mov.id AS idMov",
                "act_mov.token_movimiento",
                "act_mov.folio_movimiento",
                "act_mov.fecha_sistema",
                "act_mov.fecha_contabilizacion_movimiento",
                "act_mov.movimiento_cancelado",
                "act_mov.folio_cancelacion",
                "act_mov.fecha_cancelacion",
                "act_mov.movimiento_asociado",
                "act_mov.tipo_movimiento",
                "act_mov.subtipo_movimiento",
                "act_mov.concepto_movimiento",
                "act_mov.responsable",
                "act_mov.caja",
                "act_mov.cuenta_bancaria",
                "act_mov.cuenta_monedero",
                "act_mov.monto_aplicado",
                "act_mov.moneda_movimiento",
                "act_mov.tipo_cambio_movimiento",
                "act_mov.observaciones_movimiento",
                "act_mov.pago",
                "act_mov.deudor_movimiento",
                "act_mov.ajuste",
                "act_mov.empresa",
                "deu_mov.condicion_deu_mov",
                "deu_mov.deu_tipo_cambio",
                "deu_mov.deu_mov_moneda",
                "deu_mov.vinc_deudor"
              )
              ->get();
  
              if (!$queryActiMovDeu->isEmpty()) {
                $this->pagoDeudoresMovimientos($JwtAuth,$queryActiMovDeu,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
              }
  
              $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS act_mov")
              ->join("fnzs_pagos_pago AS pay", "act_mov.pago", "=", "pay.id")
              ->where("pay.id",$vPayDone->pay_id)
              ->where("pay.status_pagos",TRUE)
              ->select(
                "act_mov.id AS idMov",
                "act_mov.token_movimiento",
                "act_mov.folio_movimiento",
                "act_mov.fecha_sistema",
                "act_mov.seccion_movimiento",
                "act_mov.fecha_contabilizacion_movimiento",
                "act_mov.movimiento_cancelado",
                "act_mov.folio_cancelacion",
                "act_mov.fecha_cancelacion",
                "act_mov.fecha_contabilizacion_cancelacion",
                "act_mov.movimiento_asociado",
                "act_mov.tipo_movimiento",
                "act_mov.descripcion_tipo_movimiento",
                "act_mov.subtipo_movimiento",
                "act_mov.concepto_movimiento",
                "act_mov.responsable",
                "act_mov.caja",
                "act_mov.cuenta_bancaria",
                "act_mov.cuenta_monedero",
                "act_mov.monto_aplicado",
                "act_mov.moneda_movimiento",
                "act_mov.tipo_cambio_movimiento",
                "act_mov.observaciones_movimiento",
                "act_mov.pago",
                "act_mov.cobro",
                "act_mov.acreedor_movimiento",
                "act_mov.deudor_movimiento",
                "act_mov.ajuste",
                "act_mov.empresa"
              )
              ->get();
  
              if (!$queryActivMovimDone->isEmpty()) {
                $this->pagoActMovimientos($JwtAuth,$queryActivMovimDone,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
              }
            }
  
            DB::table("fnzs_pagos_orden")->where("id",$ordenData->id_orden_pago)
            ->update(array(
              "orden_bloqueada" => TRUE,
              "autorizacion_pay" => FALSE,
              "fecha_autorizacion_pay" => NULL,
              "tentativa_pago" => NULL,	
              "orden_terminada_bool" => FALSE,	
              "orden_terminada_fecha" => NULL,
              "fecha_contabilizacion_ordenPago" => NULL,
              "pago_orden_cancelada" => TRUE,
              "pago_orden_cancel_user" => $user_jerarquia,
              "pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
              "pago_orden_cancel_comentarios" => $comentarios_encriptados
            ));
          }

          $maxFolioCancelAnticipo = DB::table('eegr_catalogo_proveedores_anticipo')->where('empresa', $vEmp->id)->lockForUpdate()->max('anticipo_folio_cancelacion');
          $folioCancelAnticipo = $maxFolioCancelAnticipo ? $maxFolioCancelAnticipo + 1 : 1;
          DB::table("eegr_catalogo_proveedores_anticipo")->where("uuid_anticipo",$anticipoData->uuid_anticipo)
          ->update(array(
            "anticipo_folio_cancelacion" => $folioCancelAnticipo,
            "anticipo_cancelado" => TRUE,
            "anticipo_cancel_user" => $user_jerarquia,
            "anticipo_cancel_fecha_cont" => $fechaContabilizacionUnix,
            "anticipo_cancel_comentarios" => $comentarios_encriptados
          ));
					
					DB::table("fnzs_anticipos_soli_cancelacion")
					->where("token_cancel_soliant",$token_cancel_soliant)
					->limit(1)->update(array("anticipo_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_token_cancel_soliant) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //pago
	public function solicitudCancelacionPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_solip' => 'required|string',
      'token_pagos' => 'required|string'
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
      $token_cancel_solip = $request->input('token_cancel_solip');
      $token_pagos = $request->input('token_pagos');

			$queryPagosDone = DB::table("fnzs_pagos_pago AS payment")
      ->join("fnzs_pagos_soli_cancelacion AS pcanc", "payment.id", "=","pcanc.pago_cancel")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "=","vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
			->join("main_empresas AS emp", "payment.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
			->where([
				"pcanc.token_cancel_solip" => $token_cancel_solip,
				"payment.token_pagos" => $token_pagos,
				"payment.pago_autorizado" => TRUE,
				"payment.status_pagos" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'pcanc.token_cancel_solip',
        'pcanc.folio_cancel_solip',
        'pcanc.fecha_cont_cancel_solip',
        'pcanc.pago_cancel_observaciones_mov',
        'payment.id As id_pago',
        'payment.*',
        'emp.*'
      )
			->orderBy("payment.folio_pagos", "DESC")->get();

      if ($queryPagosDone->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $lista_pagos = [];
        foreach ($queryPagosDone as $vPayDone) {
          $solicitud_folio = 'PAG-SOLI-CANC-'.$JwtAuth->generarFolio($vPayDone->folio_cancel_solip);
          $lista_movim_acreedor = [];
          $queryAcredorMovimDone = DB::table("fnzs_catalogo_acreedores_movimientos AS acrmov")
          ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acrmov.id", "=","vinc.mov_realizado")
          ->join("fnzs_pagos_pago AS pay", "vinc.pago_vinculado", "=", "pay.id")
          ->where("pay.status_pagos",TRUE)
          ->where("pay.token_pagos",$vPayDone->token_pagos)
          ->get();

          foreach ($queryAcredorMovimDone as $vMovDone) {
            $queryPersResponsable = DB::table("fnzs_catalogo_acreedores_movimientos AS movim")
            ->join("vhum_empleados_catalogo AS pers", "movim.acre_personal_mov", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
            ->first();
            $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
            $pers_responsmov_folio = $queryPersResponsable ? "TRB-".$JwtAuth->generarFolio($queryPersResponsable->folio_pers) : "";
            $pers_responsmov_name = $queryPersResponsable ? $JwtAuth->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

            $queryCaja = DB::table("fnzs_catalogos_caja AS caj")
            ->join("fnzs_catalogo_acreedores_movimientos_cajas AS mov_caj", "caj.id", "mov_caj.caja_relacionada")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_caj.mov_realizado", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('caj.token_caja','caj.no_caja','caj.alias_caja')
            ->first();

            $queryCuenta = DB::table("fnzs_catalogos_cuentas AS cuent")
            ->join("teci_bancos AS bank", "cuent.banco", "bank.id")
            ->join("fnzs_catalogo_acreedores_movimientos_cuentas AS mov_cuent", "cuent.id", "mov_cuent.cuenta_relacionada")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_cuent.mov_realizado", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('cuent.token_cuenta','cuent.folio_cuenta','cuent.cuenta')
            ->first();

            $queryMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
            //->join("teci_plataformas_digitales AS pdig", "moned.monedero", "pdig.id")
            ->join("fnzs_catalogo_acreedores_movimientos_monederos AS mov_mon", "moned.id", "mov_mon.moned_relacionado")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_mon.mov_realizado", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
            ->first();

            if ($queryCaja) {
              $movimiento_tipo = "caja";
              $movimiento_token = $queryCaja->token_caja;
              $movimiento_folio = "CAJ-" . $JwtAuth->generarFolio($queryCaja->no_caja);
              $movimiento_name = $JwtAuth->desencriptar($queryCaja->alias_caja);
            } elseif ($queryCuenta) {
              $movimiento_tipo = "banco";
              $movimiento_token = $queryCuenta->token_cuenta;
              $movimiento_folio = 'CUENT-'.$JwtAuth->generarFolio($queryCuenta->folio_cuenta);
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($queryCuenta->cuenta);
              $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
              $movimiento_name = "**** **** **** $cuenta_descifrada_substr";
            } elseif ($queryMonedero) {
              $movimiento_tipo = "monedero";
              $movimiento_token = $queryMonedero->token_cuentamonedero;
              $movimiento_folio = "CUENTM-" . $JwtAuth->generarFolio($queryMonedero->folio_cuentmon);
              $movimiento_name = $queryMonedero->cuenta;
            } else {
              $movimiento_tipo = "N/A";
              $movimiento_token = "N/A";
              $movimiento_folio = "N/A";
              $movimiento_name = "N/A";
            }

            $mainMovs = DB::table("fnzs_actividad_movimientos AS movAct")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "movAct.acreedor_movimiento", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('movAct.token_movimiento','movAct.tipo_movimiento','movAct.subtipo_movimiento')
            ->first();

            $lista_movim_acreedor[] = [
              "token_acre_mov" => $vMovDone->token_acre_mov,
              "folio_acre_mov" => "ACRMOV-".$JwtAuth->generarFolio($vMovDone->folio_acre_mov),
              "acre_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vMovDone->acre_fecha_contabilizacion),
              "act_token_movimiento" => $mainMovs ? $mainMovs->token_movimiento : '',
              "tipo_movimiento" => $mainMovs ? $mainMovs->tipo_movimiento : '',
              "subtipo_movimiento" => $mainMovs ? $mainMovs->subtipo_movimiento : '',
              //"responsable" => $vEmp->userr,
              "responsable_token" => $pers_responsmov_token,
              "responsable_folio" => $pers_responsmov_folio,
              "responsable_name" => $pers_responsmov_name,
              //"cuenta_monedero" => $sql_cuenta_monedero,
              "movimiento_tipo" => $movimiento_tipo,
              "movimiento_token" => $movimiento_token,
              "movimiento_folio" => $movimiento_folio,
              "movimiento_name" => $movimiento_name,
              "monto_aplicado" => "$".number_format($vMovDone->acre_monto_mov,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
            ];
          }

          $lista_actividad_movim = $this->movimientos_realizados_by_pago($vPayDone->token_pagos,$vPayDone->p_moneda,$JwtAuth);

          $lista_pagos[] = [
            "token_pagos" => $vPayDone->token_pagos,
            "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($vPayDone->folio_pagos),
            "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago),
            "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vPayDone->fecha_contabilizacion) : "",
            "observacionesPago" => !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '',
            "monto_pago" => "$".number_format($vPayDone->monto_pago,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
            "tipo_cambio" => "$".number_format($vPayDone->tipo_cambio,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.',',')." $vPayDone->p_moneda",
            "p_moneda" => $vPayDone->p_moneda,
            "movimientos_acreedor" => $lista_movim_acreedor,
            "lista_actividad_movim" => $lista_actividad_movim,
            "token_cancel_solip" => $vPayDone->token_cancel_solip,
            "folio_cancel_solip" => $solicitud_folio,
            "pago_cancel_observaciones_mov" => $JwtAuth->desencriptar($vPayDone->pago_cancel_observaciones_mov),
          ];
        }

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "pagos_realizados" => $lista_pagos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cancel_soli_token' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
			$cancel_soli_token = $request->input('cancel_soli_token');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_cancel_soli_token = isset($cancel_soli_token) && !empty($cancel_soli_token);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_cancel_soli_token && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
				->first();
        
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_soli_cancelacion AS pcanc", "pay.id", "=","pcanc.pago_cancel")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->where("pcanc.token_cancel_solip",$cancel_soli_token)
          ->where("pay.status_pagos",TRUE)
          ->select("pay.id AS pay_id", "pay.token_pagos")
          ->get();

          foreach ($queryPagosDone as $vPayDone) {
            $maxFolio = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');

            DB::table("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc")
            ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
            ->where("ord_vinc.pago_realizado",$vPayDone->pay_id)
            ->update(array(
              "ord_vinc.vinculo_cancelado" => TRUE,
              "ord_pag.orden_terminada_bool" => FALSE,
              "ord_pag.orden_terminada_fecha" => NULL,
              "ord_pag.fecha_contabilizacion_ordenPago" => NULL,
              //"ord_pag.pago_orden_cancelada" => TRUE,
              //"pago_orden_cancel_user" => $user_jerarquia,
              //"ord_pag.pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
              //"ord_pag.pago_orden_cancel_comentarios" => $comentarios_encriptados
            ));
            
            $folioCancelPagos = $maxFolio ? $maxFolio + 1 : 1;

            DB::table("fnzs_pagos_pago")->where("id",$vPayDone->pay_id)
            ->limit(1)->update(array(
              "pago_cancelado" => TRUE,
              "pago_cancelado_user" => $user_jerarquia,
              "pago_folio_cancelacion" => $folioCancelPagos,
              "pago_fecha_cancelacion" => $ahora,
              "pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
              "pago_comentarios_cancelacion" => $comentarios_encriptados
            ));

            $queryActiMovAcree = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.acreedor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "acr_mov.condicion_acree_mov",
              "acr_mov.acre_tipo_cambio",
              "acr_mov.acre_mov_moneda",
              "acr_mov.vinc_acreedor"
            )
            ->get();

            if (!$queryActiMovAcree->isEmpty()) {
              $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMovAcree,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActiMovDeu = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_deudores_movimientos AS deu_mov", "act_mov.deudor_movimiento", "=","deu_mov.id")
            ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deu_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "deu_mov.condicion_deu_mov",
              "deu_mov.deu_tipo_cambio",
              "deu_mov.deu_mov_moneda",
              "deu_mov.vinc_deudor"
            )
            ->get();

            if (!$queryActiMovDeu->isEmpty()) {
              $this->pagoDeudoresMovimientos($JwtAuth,$queryActiMovDeu,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_pagos_pago AS pay", "act_mov.pago", "=", "pay.id")
            ->where("pay.id",$vPayDone->pay_id)
            ->where("pay.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.seccion_movimiento",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.fecha_contabilizacion_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.descripcion_tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.cobro",
              "act_mov.acreedor_movimiento",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa"
            )
            ->get();
            if (!$queryActivMovimDone->isEmpty()) {
              $this->pagoActMovimientos($JwtAuth,$queryActivMovimDone,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }
          }
					
					DB::table("fnzs_pagos_soli_cancelacion")
					->where("token_cancel_solip",$cancel_soli_token)
					->limit(1)->update(array("pago_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_cancel_soli_token) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //orden de pago
	public function solicitudCancelacionOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliordp' => 'required|string',
      'token_orden_pago' => 'required|string'
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
      $token_cancel_soliordp = $request->input('token_cancel_soliordp');
      $token_orden_pago = $request->input('token_orden_pago');
      
			$queryPagoOrden = DB::table("fnzs_pagos_orden AS order")
      ->join("fnzs_orden_pagos_soli_cancelacion AS p_ordcanc", "order.id", "=","p_ordcanc.orden_pago_cancel")
			->join("main_empresas AS emp", "order.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->whereIn('p_ordcanc.orden_pago_cancel', function ($query) {
        $query->select('id')->from('fnzs_pagos_orden')->whereNull('nomina_main');
      })
			->where([
				"p_ordcanc.token_cancel_soliordp" => $token_cancel_soliordp,
				"order.token_ordenPago" => $token_orden_pago,
				"order.status_ordenPago" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'p_ordcanc.token_cancel_soliordp',
        'p_ordcanc.folio_cancel_soliordp',
        'p_ordcanc.fecha_cont_cancel_soliordp',
        'p_ordcanc.orden_pago_cancel_observaciones_mov',
        'order.id As id_orden_pago',
        'order.pago_orden_cancelada As op_cancel',
        'order.*',
        'emp.empresa_token', 
        'emp.zona_horaria'
      )
			->orderBy("order.folio_ordenPago", "DESC")->get();

      if ($queryPagoOrden->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $solicitud_desglose = [];
        foreach ($queryPagoOrden as $rOrdPag) {
          $solicitud_folio = 'ORDPAG-SOLI-CANC-'.$JwtAuth->generarFolio($rOrdPag->folio_cancel_soliordp);

          $solicitud_desglose[] = [
            "token_cancel_soliordp" => $rOrdPag->token_cancel_soliordp,
            "folio_cancel_soliordp" => $solicitud_folio,
            "orden_pago_cancel_observaciones_mov" => $JwtAuth->desencriptar($rOrdPag->orden_pago_cancel_observaciones_mov),
          ];
        }
        $orden_pago = $this->eachGeneralOrdenesPago($queryPagoOrden,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "solicitud_desglose" => $solicitud_desglose,
          "orden_pago" => $orden_pago
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliordp' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
			$token_cancel_soliordp = $request->input('token_cancel_soliordp');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_token_cancel_soliordp = isset($token_cancel_soliordp) && !empty($token_cancel_soliordp);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_token_cancel_soliordp && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.id AS userr','users.jerarquia_main')
				->first();
				
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
					$ordenData = DB::table("fnzs_pagos_orden AS order")
          ->join("fnzs_orden_pagos_soli_cancelacion AS p_ordcanc", "order.id", "=","p_ordcanc.orden_pago_cancel")
          ->join("main_empresas AS emp", "order.empresa", "emp.id")
          ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
          ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
          ->where([
            "p_ordcanc.token_cancel_soliordp" => $token_cancel_soliordp,
            "order.status_ordenPago" => TRUE,
            "emp.empresa_token" => $empresa,
            "users.usuario_token" => $usuario
          ])
          ->select('order.id As id_orden_pago','order.token_ordenPago')
          ->lockForUpdate()->first();
          //if (!$ordenData) continue;

          DB::table("fnzs_pagos_pago_ordenes_vinculadas")
          ->where("orden_pago_vinculada",$ordenData->id_orden_pago)
          ->update(array("vinculo_cancelado" => TRUE));

          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL; 

          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->where("vinc.orden_pago_vinculada",$ordenData->id_orden_pago)
          ->where("pay.status_pagos",TRUE)
          ->select("pay.id AS pay_id", "pay.token_pagos")
          ->get();

          foreach ($queryPagosDone as $vPayDone) {
            $maxFolio = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');
            $folioCancelPagos = $maxFolio ? $maxFolio + 1 : 1;

            DB::table("fnzs_pagos_pago")->where("id",$vPayDone->pay_id)
            ->limit(1)->update(array(
              "pago_cancelado" => TRUE,
              "pago_cancelado_user" => $user_jerarquia,
              "pago_folio_cancelacion" => $folioCancelPagos,
              "pago_fecha_cancelacion" => $ahora,
              "pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
              "pago_comentarios_cancelacion" => $comentarios_encriptados
            ));

            $queryActiMovAcree = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.acreedor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "acr_mov.condicion_acree_mov",
              "acr_mov.acre_tipo_cambio",
              "acr_mov.acre_mov_moneda",
              "acr_mov.vinc_acreedor"
            )
            ->get();
            
            if (!$queryActiMovAcree->isEmpty()) {
              $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMovAcree,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActiMovDeu = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_deudores_movimientos AS deu_mov", "act_mov.deudor_movimiento", "=","deu_mov.id")
            ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deu_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "deu_mov.condicion_deu_mov",
              "deu_mov.deu_tipo_cambio",
              "deu_mov.deu_mov_moneda",
              "deu_mov.vinc_deudor"
            )
            ->get();

            if (!$queryActiMovDeu->isEmpty()) {
              $this->pagoDeudoresMovimientos($JwtAuth,$queryActiMovDeu,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_pagos_pago AS pay", "act_mov.pago", "=", "pay.id")
            ->where("pay.id",$vPayDone->pay_id)
            ->where("pay.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.seccion_movimiento",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.fecha_contabilizacion_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.descripcion_tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.cobro",
              "act_mov.acreedor_movimiento",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa"
            )
            ->get();

            if (!$queryActivMovimDone->isEmpty()) {
              $this->pagoActMovimientos($JwtAuth,$queryActivMovimDone,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }
          }

          DB::table("fnzs_pagos_orden")->where("id",$ordenData->id_orden_pago)
          ->update(array(
            "orden_bloqueada" => TRUE,
            "autorizacion_pay" => FALSE,
            "fecha_autorizacion_pay" => NULL,
            "tentativa_pago" => NULL,	
            "orden_terminada_bool" => FALSE,	
            "orden_terminada_fecha" => NULL,
            "fecha_contabilizacion_ordenPago" => NULL,
            "pago_orden_cancelada" => TRUE,
            "pago_orden_cancel_user" => $user_jerarquia,
            "pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
            "pago_orden_cancel_comentarios" => $comentarios_encriptados
          ));
					
					DB::table("fnzs_orden_pagos_soli_cancelacion")
					->where("token_cancel_soliordp",$token_cancel_soliordp)
					->limit(1)->update(array("orden_pago_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_token_cancel_soliordp) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //reembolsos
	public function solicitudCancelacionReembolsoOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_reem' => 'required|string',
      'reem_token' => 'required|string',
      'reem_soli_token' => 'required|string',
      'compra_token' => 'required|string'
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
      $token_cancel_reem = $request->input('token_cancel_reem');
      $reem_token = $request->input('reem_token');
      $reem_soli_token = $request->input('reem_soli_token');
      $compra_token = $request->input('compra_token');
      
			$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
			->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
			->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
			->where("reem_main.token_reem",$reem_token)
      ->get();
      
      $listaCompras = DB::table("eegr_compras AS buy")
      ->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
      ->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->whereIn('buy.id', function ($query) {
        $query->select('numero_compra')->from('eegr_compras_detalle');
      })
      ->where([
        'buy.status_autorizacion' => TRUE,
        'buy.token_compras' => $compra_token,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();
      
      $queryOrdenPago = DB::table("eegr_compras AS buy")
      ->join("fnzs_pagos_orden AS order", "buy.id", "=", "order.factura_compra")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->whereIn('buy.id', function ($query) {
        $query->select('numero_compra')->from('eegr_compras_detalle');
      })
      ->where([
        'buy.status_autorizacion' => TRUE,
        'buy.token_compras' => $compra_token,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($selectPersEmpEmi->isEmpty() || $listaCompras->isEmpty() || $queryOrdenPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $info_acreedor = array();
				foreach ($selectPersEmpEmi as $vPemi) {
					$row_acr = array(
            "acreedor_token" => $vPemi->token_cat_acreedores,
            "acreedor_nombre" => $JwtAuth->desencriptar($vPemi->acr_titular),
          );
          $info_acreedor[] = $row_acr;
				}

        $info_compras = array();
        foreach ($listaCompras as $vBuy) {
          $row_orde_pay = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compras" => 'COMP-'.$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "total_compras" => "$".number_format($vBuy->cfdi_comprobante_total * $vBuy->cfdi_comprobante_tipo_de_cambio, $JwtAuth->getMonedaAPI($vBuy->cfdi_comprobante_moneda), '.', ',')." ".$vBuy->cfdi_comprobante_moneda
          );
          $info_compras[] = $row_orde_pay;
        }

		    $info_orden_pago = array();
        foreach ($queryOrdenPago as $rOrdPag) {
          $lista_pagos_realizados = array();
          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where("pay.status_pagos",TRUE)
          ->where("order.token_ordenPago",$rOrdPag->token_ordenPago)
          ->get();
          
          foreach ($queryPagosDone as $vPayDone) {
            $lista_movimientos_realizados = [];
            $queryMovimientosDone = DB::table("fnzs_catalogo_acreedores_movimientos AS acrmov")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acrmov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pay", "vinc.pago_vinculado", "=", "pay.id")
            ->where("pay.status_pagos",TRUE)
            ->where("pay.token_pagos",$vPayDone->token_pagos)
            ->get();

            foreach ($queryMovimientosDone as $vMovDone) {
              $queryPersResponsable = DB::table("fnzs_catalogo_acreedores_movimientos AS movim")
  					  ->join("vhum_empleados_catalogo AS pers", "movim.acre_personal_mov", "pers.id")
  					  ->join("sos_personas AS people", "pers.empleado_name", "people.id")
  					  ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
  					  ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
  					  ->first();
  					  $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
              $pers_responsmov_folio = $queryPersResponsable ? "TRB-".$JwtAuth->generarFolio($queryPersResponsable->folio_pers) : "";
  					  $pers_responsmov_name = $queryPersResponsable ? $JwtAuth->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

              $queryCaja = DB::table("fnzs_catalogos_caja AS caj")
              ->join("fnzs_catalogo_acreedores_movimientos_cajas AS mov_caj", "caj.id", "mov_caj.caja_relacionada")
              ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_caj.mov_realizado", "movim.id")
              ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
              ->select('caj.token_caja','caj.no_caja','caj.alias_caja')
              ->first();

              $queryCuenta = DB::table("fnzs_catalogos_cuentas AS cuent")
              ->join("teci_bancos AS bank", "cuent.banco", "bank.id")
              ->join("fnzs_catalogo_acreedores_movimientos_cuentas AS mov_cuent", "cuent.id", "mov_cuent.cuenta_relacionada")
              ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_cuent.mov_realizado", "movim.id")
              ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
              ->select('cuent.token_cuenta','cuent.folio_cuenta','cuent.cuenta')
              ->first();

              $queryMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
              //->join("teci_plataformas_digitales AS pdig", "moned.monedero", "pdig.id")
              ->join("fnzs_catalogo_acreedores_movimientos_monederos AS mov_mon", "moned.id", "mov_mon.moned_relacionado")
              ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_mon.mov_realizado", "movim.id")
              ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
              ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
              ->first();

              if ($queryCaja) {
                $movimiento_tipo = "caja";
                $movimiento_token = $queryCaja->token_caja;
                $movimiento_folio = "CAJ-" . $JwtAuth->generarFolio($queryCaja->no_caja);
                $movimiento_name = $JwtAuth->desencriptar($queryCaja->alias_caja);
              } elseif ($queryCuenta) {
                $movimiento_tipo = "banco";
                $movimiento_token = $queryCuenta->token_cuenta;
                $movimiento_folio = 'CUENT-'.$JwtAuth->generarFolio($queryCuenta->folio_cuenta);
                $cuenta_descifrada = $JwtAuth->decryptBankAccount($queryCuenta->cuenta);
                $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
                $movimiento_name = "**** **** **** $cuenta_descifrada_substr";
              } elseif ($queryMonedero) {
                $movimiento_tipo = "monedero";
                $movimiento_token = $queryMonedero->token_cuentamonedero;
                $movimiento_folio = "CUENTM-" . $JwtAuth->generarFolio($queryMonedero->folio_cuentmon);
                $movimiento_name = $queryMonedero->cuenta;
              } else {
                $movimiento_tipo = "N/A";
                $movimiento_token = "N/A";
                $movimiento_folio = "N/A";
                $movimiento_name = "N/A";
              }

              $mainMovs = DB::table("fnzs_actividad_movimientos AS movAct")
              ->join("fnzs_catalogo_acreedores_movimientos AS movim", "movAct.acreedor_movimiento", "movim.id")
              ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
              ->select('movAct.token_movimiento','movAct.tipo_movimiento','movAct.subtipo_movimiento')
              ->first();

              $row_mov_acr = array(
                "token_acre_mov" => $vMovDone->token_acre_mov,
                "folio_acre_mov" => "ACRMOV-".$JwtAuth->generarFolio($vMovDone->folio_acre_mov),
                "acre_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vMovDone->acre_fecha_contabilizacion),
                "act_token_movimiento" => $mainMovs ? $mainMovs->token_movimiento : '',
                "tipo_movimiento" => $mainMovs ? $mainMovs->tipo_movimiento : '',
                "subtipo_movimiento" => $mainMovs ? $mainMovs->subtipo_movimiento : '',
                //"responsable" => $vEmp->userr,
                "responsable_token" => $pers_responsmov_token,
  					    "responsable_folio" => $pers_responsmov_folio,
  					    "responsable_name" => $pers_responsmov_name,
                //"cuenta_monedero" => $sql_cuenta_monedero,
                "movimiento_tipo" => $movimiento_tipo,
                "movimiento_token" => $movimiento_token,
                "movimiento_folio" => $movimiento_folio,
                "movimiento_name" => $movimiento_name,
                "monto_aplicado" => "$".number_format($vMovDone->acre_monto_mov,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
              );
              $lista_movimientos_realizados[] = $row_mov_acr;
            }

            $row_pagos_realizados = array(
              "token_pagos" => $vPayDone->token_pagos,
              "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($vPayDone->folio_pagos),
              "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago),
					  	"fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vPayDone->fecha_contabilizacion) : "",
              "observacionesPago" => !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '',
              "monto_pago" => "$".number_format($vPayDone->monto_pago,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
              "tipo_cambio" => "$".number_format($vPayDone->tipo_cambio,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.',',')." $vPayDone->p_moneda",
              "p_moneda" => $vPayDone->p_moneda,
              "movimientos_realizados" => $lista_movimientos_realizados,
            );
            $lista_pagos_realizados[] = $row_pagos_realizados;
          }

          //fnzs_catalogo_acreedores_movimientos
	        //fnzs_catalogo_acreedores_movimientos_cajas
	        //fnzs_catalogo_acreedores_movimientos_cuentas
	        //fnzs_catalogo_acreedores_movimientos_monederos
	        //fnzs_catalogo_acreedores_movimientos_pagos_vinculados	

          $row_orde_pay = array(
            "orden_pago_token" => $rOrdPag->token_ordenPago,
            "orden_pago_folio" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
						"orden_pago_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$rOrdPag->fecha_contabilizacion_ordenPago),
						"orden_pago_fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
						"pagos_realizados" => $lista_pagos_realizados
          );
          $info_orden_pago[] = $row_orde_pay;
        }

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "info_acreedor" => $info_acreedor,
          "info_compras" => $info_compras, 
          "info_orden_pago" => $info_orden_pago
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionReembolsoOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'ordenes_de_pago' => 'required|array',
      'token_cancel_reem' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
      $ordenes_de_pago = $request->input('ordenes_de_pago');
			$token_cancel_reem = $request->input('token_cancel_reem');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_ordenes_de_pago = isset($ordenes_de_pago) && count($ordenes_de_pago) > 0;
			$valide_token_cancel_reem = isset($token_cancel_reem) && !empty($token_cancel_reem);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_ordenes_de_pago && $valide_token_cancel_reem && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
				->first();
				
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
					$successCount = 0;
          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL; 
					foreach ($ordenes_de_pago as $ordp) {
						$tokenOrden = $ordp['orden_pago_token'];
            $ordenData = DB::table("fnzs_pagos_orden")->where("token_ordenPago", $tokenOrden)->lockForUpdate()->first();
						if (!$ordenData) continue;

						DB::table("fnzs_pagos_pago_ordenes_vinculadas")->where("orden_pago_vinculada",$ordenData->id)->update(array("vinculo_cancelado" => TRUE));
	
						$queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
						->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
						->where("vinc.orden_pago_vinculada",$ordenData->id)
						->where("pay.status_pagos",TRUE)
						->select("pay.id", "pay.token_pagos")
						->get();

						foreach ($queryPagosDone as $vPayDone) {
							$maxFolio = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');
							$folioCancelPagos = $maxFolio ? $maxFolio + 1 : 1;
	
							DB::table("fnzs_pagos_pago")->where("id",$vPayDone->id)
							->limit(1)->update(array(
								"pago_cancelado" => TRUE,
                "pago_cancelado_user" => $user_jerarquia,
								"pago_folio_cancelacion" => $folioCancelPagos,
								"pago_fecha_cancelacion" => $ahora,
								"pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
								"pago_comentarios_cancelacion" => $comentarios_encriptados
							));
	
							$queryActiMov = DB::table("fnzs_actividad_movimientos AS act_mov")
							->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
							->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
							->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
							->where("pag.id",$vPayDone->id)
							->where("pag.status_pagos",TRUE)
							->select(
								"act_mov.id AS idMov",
								"act_mov.token_movimiento",
								"act_mov.folio_movimiento",
								"act_mov.fecha_sistema",
								"act_mov.fecha_contabilizacion_movimiento",
								"act_mov.movimiento_cancelado",
								"act_mov.folio_cancelacion",
								"act_mov.fecha_cancelacion",
								"act_mov.movimiento_asociado",
								"act_mov.tipo_movimiento",
								"act_mov.subtipo_movimiento",
								"act_mov.concepto_movimiento",
								"act_mov.responsable",
								"act_mov.caja",
								"act_mov.cuenta_bancaria",
								"act_mov.cuenta_monedero",
								"act_mov.monto_aplicado",
								"act_mov.moneda_movimiento",
								"act_mov.tipo_cambio_movimiento",
								"act_mov.observaciones_movimiento",
								"act_mov.pago",
								"act_mov.acreedor_movimiento",
								"act_mov.ajuste",
								"act_mov.empresa",
								"acr_mov.condicion_acree_mov",
								"acr_mov.acre_tipo_cambio",
								"acr_mov.acre_mov_moneda",
								"acr_mov.vinc_acreedor"
							)
							->get();
              
              if (!$queryActiMov->isEmpty()) {
                $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMov,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
              }
							//echo "queryActiMov ".count($queryActiMov);
							/*foreach ($queryActiMov as $vActMov) {
								$maxFolAcrMov = DB::table('fnzs_catalogo_acreedores_movimientos')->where('acre_empresa', $vEmp->id)->lockForUpdate()->max('folio_acre_mov');
								$folioAcrMov = $maxFolAcrMov ? $maxFolAcrMov + 1 : 1;
								$folio_pago_generar = "ACRMOV-".$JwtAuth->generarFolio($folioAcrMov);
								
								$tokenMov = $JwtAuth->encriptarToken($folioAcrMov.$comentarios_confi.$ahora,$folio_pago_generar);
								
								DB::table("fnzs_catalogo_acreedores_movimientos")
								->insert(array(
									"token_acre_mov" => $tokenMov,
									"folio_acre_mov" => $folioAcrMov,
									"acre_fecha_registro" => $ahora,
									"acre_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
									"acre_monto_mov" => $vActMov->monto_aplicado,
									"condicion_acree_mov" => $vActMov->condicion_acree_mov == "S" ? "R" : "S",
									"acre_observaciones_mov" => $comentarios_encriptados,
									"acre_tipo_cambio" => $vActMov->acre_tipo_cambio,
									"acre_mov_moneda" => $vActMov->acre_mov_moneda,
									"vinc_acreedor" => $vActMov->vinc_acreedor,
									"acre_personal_mov" => $vEmp->userr,
									"acre_mov_autorizado" => TRUE,
									"acre_fecha_mov_auth" => $ahora,
									"acre_personal_autoriza" => $vEmp->userr,
									"acre_empresa" => $vEmp->id,
									"acre_status_mov" => TRUE
								));

								$maxFolioCancelMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $vEmp->id)->lockForUpdate()->max('folio_cancelacion');
								$folioActCancelMov = $maxFolioCancelMov ? $maxFolioCancelMov + 1 : 1;
								
								DB::table("fnzs_actividad_movimientos")->where("id",$vActMov->idMov)
								->limit(1)->update(array(
									"movimiento_cancelado" => TRUE,
									"folio_cancelacion" => $folioActCancelMov,
									"fecha_cancelacion" => $ahora,
									"fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
								));
	
								$maxFolioNewMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $vEmp->id)->lockForUpdate()->max('folio_movimiento');
								$folioNewMov = $maxFolioNewMov ? $maxFolioNewMov + 1 : 1;
	
								$token_movimiento = $JwtAuth->encriptarToken($vActMov->acreedor_movimiento,$vActMov->folio_movimiento,$folioNewMov);
	
								DB::table("fnzs_actividad_movimientos")
								->insert(array(
									"token_movimiento" => $token_movimiento,
									"folio_movimiento" => $folioNewMov,
									"fecha_sistema" => $ahora,
									"fecha_contabilizacion_movimiento" => $fechaContabilizacionUnix,
									"movimiento_asociado" => $vActMov->idMov,
									"tipo_movimiento" => "S",
									"subtipo_movimiento" => "C",
									"concepto_movimiento" => $vActMov->concepto_movimiento,
									"responsable" => $vActMov->responsable,
									"caja" => $vActMov->caja,	
									"cuenta_bancaria" => $vActMov->cuenta_bancaria,
									"cuenta_monedero" => $vActMov->cuenta_monedero,
									"monto_aplicado" => $vActMov->monto_aplicado,
									"moneda_movimiento" => $vActMov->moneda_movimiento,
									"tipo_cambio_movimiento" => $vActMov->tipo_cambio_movimiento,
									"observaciones_movimiento" => $comentarios_encriptados,
									"pago" => $vActMov->pago,
									"acreedor_movimiento" => $vActMov->acreedor_movimiento,
									"ajuste" => $vActMov->ajuste,
									"empresa" => $vActMov->empresa,
								)); 
							}*/
						}
	
						DB::table("fnzs_pagos_orden")->where("id",$ordenData->id)
						->update(array(
							"orden_bloqueada" => TRUE,
							"autorizacion_pay" => FALSE,
							"fecha_autorizacion_pay" => NULL,
							"tentativa_pago" => NULL,	
							"orden_terminada_bool" => FALSE,	
							"orden_terminada_fecha" => NULL,
              "fecha_contabilizacion_ordenPago" => NULL,
              "pago_orden_cancelada" => TRUE,
              "pago_orden_cancel_user" => $user_jerarquia,
              "pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
              "pago_orden_cancel_comentarios" => $comentarios_encriptados
						));
	
						$fact_compra = DB::table("eegr_compras")->where("id",$ordenData->factura_compra)->first();
						if ($fact_compra) {
							DB::table("terc_reembolso_solicitud AS rsoli")
							->join("terc_reembolso_main AS rmain", "rsoli.reembolso_main", "=","rmain.id")
							->join("eegr_compras AS buy", "rsoli.id", "=", "buy.reembolso_vinculado_soli")
							->where("buy.id",$ordenData->factura_compra)
							->limit(1)->update(array(
								"rsoli.autorizacion_egr" => "D"
							));
	
							$token_auth = $JwtAuth->encriptarToken(time(),$fact_compra->reembolso_vinculado_main.$fact_compra->reembolso_vinculado_soli."Autorización rechazada por cancelación de pagos". time() - 500);
		
							$folioAuthEgr = DB::table('terc_reembolso_autorizacion_egr')
							->where([
								'reembolso_main' => $fact_compra->reembolso_vinculado_main,
								'reembolso_solicitud' => $fact_compra->reembolso_vinculado_soli,
							])
							->lockForUpdate()->max('folio_auth_reem');
							$folio_auth = $folioAuthEgr ? $folioAuthEgr + 1 : 1;
	
							DB::table('terc_reembolso_autorizacion_egr')
							->insert(array(
								"token_auth_reem" => $token_auth,
								"folio_auth_reem" => $folio_auth,
								"fecha_registro" => time(),
								"reembolso_main" => $fact_compra->reembolso_vinculado_main,
								"reembolso_solicitud" => $fact_compra->reembolso_vinculado_soli,
								"autorizacion_egr" => "D",
								"comentarios" => $JwtAuth->encriptar("autorización rechazada por caneclación de pagos"),
							));
		
							$update_compra_unvinc = DB::table("eegr_compras")
							->where("id",$ordenData->factura_compra)
							->limit(1)->update(array(
								"reembolso_vinculado_main" => NULL,
								"reembolso_vinculado_soli" => NULL
							));
						}
						$successCount++;
					}
					
					DB::table("terc_reembolsos_cancelaciones")
					->where("token_cancel_reem",$token_cancel_reem)
					->limit(1)->update(array("reem_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_ordenes_de_pago) $mensaje_error = 'Error en orden de pago vinculada, verifique su información o comuníquese a soporte';
				if (!$valide_token_cancel_reem) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //movimientos ebtre cuentas propias
	public function solicitudCancelacionMCP(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_mcp' => 'required|string',
      'movimiento_cp_token' => 'required|string'
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
      $token_cancel_mcp = $request->input('token_cancel_mcp');
      $movimiento_cp_token = $request->input('movimiento_cp_token');

			$queryMCPCancelSoli = DB::table("fnzs_movimientos_cuentas_propias AS mcp")
      ->join("fnzs_mov_cuent_propias_cancelacion AS mcpCanc", "mcp.id", "=","mcpCanc.mcp_cancel")
      ->join("main_empresas AS emp", "mcp.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
			->where([
        "mcp.movimiento_cp_token" => $movimiento_cp_token,
				"mcpCanc.token_cancel_mcp" => $token_cancel_mcp,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'mcpCanc.token_cancel_mcp',
        'mcpCanc.folio_cancel_mcp',
        'mcpCanc.fecha_cont_cancel_mcp',
        'mcpCanc.mcp_cancel_observaciones_mov',
        'mcp.id As id_mcp',
        'mcp.*',
        'emp.*'
      )
			->orderBy("mcp.movimiento_cp_folio", "DESC")->get();

      if ($queryMCPCancelSoli->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $movimiento_relacionado = [];
        foreach ($queryMCPCancelSoli as $vMov) {
          $solicitud_folio = 'MCP-SOLI-CANC-'.$JwtAuth->generarFolio($vMov->folio_cancel_mcp);
          $movimiento_concepto = "";
          $movimiento_observaciones = "";

          $origen_catalogo_tipo = "";
          $origen_catalogo_token = "";
          $origen_catalogo_folio = "";
          $origen_catalogo_name = "";

          $destino_catalogo_tipo = "";
          $destino_catalogo_token = "";
          $destino_catalogo_folio = "";
          $destino_catalogo_name = "";
          $movimiento_monto = "";
          $movimiento_moneda = "";
          $movimiento_tipo_cambio = "";

          //$movimiento_origen = array();
          $queryCPMovimientoOrigen = DB::table("fnzs_actividad_movimientos AS mov")
          ->join("fnzs_movimientos_cuentas_propias AS mcp", "mov.id", "=", "mcp.movimiento_cp_origen")
          ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where('mcp.movimiento_cp_token',$vMov->movimiento_cp_token)
          ->where('emp.empresa_token',$empresa)
          ->where('users.usuario_token',$usuario)
          ->get();
          foreach ($queryCPMovimientoOrigen as $origen) {
            //caja
            $movCaja = DB::table("fnzs_catalogos_caja AS caj")
            ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
            ->where('mov.token_movimiento',$origen->token_movimiento)
            ->where('caj.status',TRUE)
            ->select("caj.token_caja","caj.no_caja","caj.alias_caja")
            ->first();

            //banco
            $movCuentas = DB::table("fnzs_catalogos_cuentas AS account")
            ->join("fnzs_actividad_movimientos AS mov", "account.id", "mov.cuenta_bancaria")
            ->where('mov.token_movimiento',$origen->token_movimiento)
            ->where('account.status',TRUE)
            ->select('account.token_cuenta','account.folio_cuenta','account.cuenta')
            ->first();

            //monederos
            $movMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
            ->join("fnzs_actividad_movimientos AS mov","moned.id","mov.cuenta_monedero")
            ->where('mov.token_movimiento',$origen->token_movimiento)
            ->where('moned.status',TRUE)
            ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
            ->first();

            if ($movCaja) {
              $origen_catalogo_tipo = "caja";
              $origen_catalogo_token = $movCaja->token_caja;
              $origen_catalogo_folio = "CAJ-" . $JwtAuth->generarFolio($movCaja->no_caja);
              $origen_catalogo_name = $JwtAuth->desencriptar($movCaja->alias_caja);
            } elseif ($movCuentas) {
              $origen_catalogo_tipo = "banco";
              $origen_catalogo_token = $movCuentas->token_cuenta;
              $origen_catalogo_folio = 'CUENT-'.$JwtAuth->generarFolio($movCuentas->folio_cuenta);
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($movCuentas->cuenta);
              $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
              $origen_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            } elseif ($movMonedero) {
              $origen_catalogo_tipo = "monedero";
              $origen_catalogo_token = $movMonedero->token_cuentamonedero;
              $origen_catalogo_folio = "CUENTM-" . $JwtAuth->generarFolio($movMonedero->folio_cuentmon);
              $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($movMonedero->cuenta), -4), -4);
              $origen_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            }

            $movimiento_concepto = $JwtAuth->desencriptar($origen->concepto_movimiento);
            $movimiento_observaciones = $JwtAuth->desencriptar($origen->observaciones_movimiento);
            
            //$row_origen = array(
            //  "token_movimiento" => $origen->token_movimiento,
            //  "folio_movimiento" => "MOV-".$JwtAuth->generarFolio($origen->folio_movimiento),
            //  "fecha_contabilizacion_movimiento" => gmdate('Y-m-d H:i:s',$origen->fecha_contabilizacion_movimiento),
            //  "tipo_movimiento" => $origen->tipo_movimiento,
            //  "subtipo_movimiento" => $origen->subtipo_movimiento,
            //  "concepto_movimiento" => $JwtAuth->desencriptar($origen->concepto_movimiento),
            //  //"responsable" => $origen-> $vEmp->userr,
            //  "origen_catalogo_tipo" => $origen_catalogo_tipo,
            //  "origen_catalogo_token" => $origen_catalogo_token,
            //  "origen_catalogo_folio" => $origen_catalogo_folio,
            //  "origen_catalogo_name" => $origen_catalogo_name,
            //  "monto_aplicado" => "$".number_format($origen->monto_aplicado * $origen->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($origen->moneda_movimiento), '.', ','),
            //  "moneda_movimiento" => $origen->moneda_movimiento,
            //  "tipo_cambio_movimiento" => "$".number_format($origen->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($origen->moneda_movimiento), '.', ','),
            //  "observaciones_movimiento" => $JwtAuth->desencriptar($origen->observaciones_movimiento),
            //);
            //$movimiento_origen[] = $row_origen;
          }

          //$movimiento_destino = array();
          $queryCPMovimientoDestino = DB::table("fnzs_actividad_movimientos AS mov")
          ->join("fnzs_movimientos_cuentas_propias AS mcp", "mov.id", "=", "mcp.movimiento_cp_destino")
          ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where('mcp.movimiento_cp_token',$vMov->movimiento_cp_token)
          ->where('emp.empresa_token',$empresa)
          ->where('users.usuario_token',$usuario)
          ->get();
          foreach ($queryCPMovimientoDestino as $final) {
            //caja
            $movCaja = DB::table("fnzs_catalogos_caja AS caj")
            ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
            ->where('mov.token_movimiento',$final->token_movimiento)
            ->where('caj.status',TRUE)
            ->select("caj.token_caja","caj.no_caja","caj.alias_caja")
            ->first();

            //banco
            $movCuentas = DB::table("fnzs_catalogos_cuentas AS account")
            ->join("fnzs_actividad_movimientos AS mov", "account.id", "mov.cuenta_bancaria")
            ->where('mov.token_movimiento',$final->token_movimiento)
            ->where('account.status',TRUE)
            ->select('account.token_cuenta','account.folio_cuenta','account.cuenta')
            ->first();

            //monederos
            $movMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
            ->join("fnzs_actividad_movimientos AS mov","moned.id","mov.cuenta_monedero")
            ->where('mov.token_movimiento',$final->token_movimiento)
            ->where('moned.status',TRUE)
            ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
            ->first();

            if ($movCaja) {
              $destino_catalogo_tipo = "caja";
              $destino_catalogo_token = $movCaja->token_caja;
              $destino_catalogo_folio = "CAJ-" . $JwtAuth->generarFolio($movCaja->no_caja);
              $destino_catalogo_name = $JwtAuth->desencriptar($movCaja->alias_caja);
            } elseif ($movCuentas) {
              $destino_catalogo_tipo = "banco";
              $destino_catalogo_token = $movCuentas->token_cuenta;
              $destino_catalogo_folio = 'CUENT-'.$JwtAuth->generarFolio($movCuentas->folio_cuenta);
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($movCuentas->cuenta);
              $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
              $destino_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            } elseif ($movMonedero) {
              $destino_catalogo_tipo = "monedero";
              $destino_catalogo_token = $movMonedero->token_cuentamonedero;
              $destino_catalogo_folio = "CUENTM-" . $JwtAuth->generarFolio($movMonedero->folio_cuentmon);
              $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($movMonedero->cuenta), -4), -4);
              $destino_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            }

            $movimiento_monto = "$".number_format($final->monto_aplicado * $final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ',');
            $movimiento_moneda = $final->moneda_movimiento;
            $movimiento_tipo_cambio = "$".number_format($final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ',');

            //$row_destino = array(
            //  "token_movimiento" => $final->token_movimiento,
            //  "folio_movimiento" => "MOV-".$JwtAuth->generarFolio($final->folio_movimiento),
            //  "fecha_contabilizacion_movimiento" => gmdate('Y-m-d H:i:s',$final->fecha_contabilizacion_movimiento),
            //  "tipo_movimiento" => $final->tipo_movimiento,
            //  "subtipo_movimiento" => $final->subtipo_movimiento,
            //  "concepto_movimiento" => $JwtAuth->desencriptar($final->concepto_movimiento),
            //  //"responsable" => $final-> $vEmp->userr,
            //  "destino_catalogo_tipo" => $destino_catalogo_tipo,
            //  "destino_catalogo_token" => $destino_catalogo_token,
            //  "destino_catalogo_folio" => $destino_catalogo_folio,
            //  "destino_catalogo_name" => $destino_catalogo_name,
            //  "monto_aplicado" => "$".number_format($final->monto_aplicado * $final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ','),
            //  "moneda_movimiento" => $final->moneda_movimiento,
            //  "tipo_cambio_movimiento" => "$".number_format($final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ','),
            //  "observaciones_movimiento" => $JwtAuth->desencriptar($final->observaciones_movimiento),
            //);
            //$movimiento_destino[] = $row_destino;
          }

          $movimiento_relacionado[] = [
            "movimiento_cp_token" => $vMov->movimiento_cp_token,
            "movimiento_cp_folio" => $vMov->movimiento_cp_folio ? "MCP-" . $JwtAuth->generarFolio($vMov->movimiento_cp_folio) : '',
            "movimiento_cp_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vMov->movimiento_cp_fecha_contabilizacion),
            //"movimiento_cp_origen" => $movimiento_origen,
            "origen_catalogo_tipo" => $origen_catalogo_tipo,
            "origen_catalogo_token" => $origen_catalogo_token,
            "origen_catalogo_folio" => $origen_catalogo_folio,
            "origen_catalogo_name" => $origen_catalogo_name,
            "origen_catalogo_complete" => "$origen_catalogo_folio $origen_catalogo_name",
            "movimiento_concepto" => $movimiento_concepto,
            "movimiento_observaciones" => $movimiento_observaciones,
            //"movimiento_cp_destino" => $movimiento_destino,
            "destino_catalogo_tipo" => $destino_catalogo_tipo,
            "destino_catalogo_token" => $destino_catalogo_token,
            "destino_catalogo_folio" => $destino_catalogo_folio,
            "destino_catalogo_name" => $destino_catalogo_name,
            "destino_catalogo_complete" => "$destino_catalogo_folio $destino_catalogo_name",
            //montos
            "movimiento_monto" => $movimiento_monto,
            "movimiento_moneda" => $movimiento_moneda,
            "movimiento_tipo_cambio" => $movimiento_tipo_cambio,
            "movimiento_cp_observaciones" => $JwtAuth->desencriptar($vMov->movimiento_cp_observaciones),

            "token_cancel_mcp" => $vMov->token_cancel_mcp,
            "folio_cancel_mcp" => $solicitud_folio,
            "mcp_cancel_observaciones_mov" => $JwtAuth->desencriptar($vMov->mcp_cancel_observaciones_mov),
          ];
        }

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "movimiento_relacionado" => $movimiento_relacionado
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionMCP(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_mcp' => 'required|string',
      'movimiento_cp_token' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'observaciones' => 'required|string',
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
      $token_cancel_mcp = $request->input('token_cancel_mcp');
      $movimiento_cp_token = $request->input('movimiento_cp_token');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $observaciones = $request->input('observaciones');
      
      $OKCPMToken = isset($movimiento_cp_token) && !empty($movimiento_cp_token);
      $OKfechaContabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKObservaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if (!$OKCPMToken) {
        return response()->json([
          'status' => 'error',
          'message' => 'Movimento entre cuentas propias no registrado.',
          'data' => null
        ], 200);
      }
      
      if (!$OKfechaContabilizacion) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error en fecha de contabilización, verifique su información.',
          'data' => null
        ], 200);
      }

      if (!$OKObservaciones) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error en observaciones de movimiento, verifique su información.',
          'data' => null
        ], 200);
      }
      
      $vEmp = DB::table("main_empresas AS emp")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
      ->first();

      $queryCPropia = DB::table("fnzs_movimientos_cuentas_propias AS mcp")
      //->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
      //->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      //->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "mcp.movimiento_cp_token" => $movimiento_cp_token,
        "mcp.movimiento_cp_cancelado" => FALSE,
        //"emp.empresa_token" => $empresa,
        //"users.usuario_token" => $usuario
      ])
      ->select('mcp.id AS id_mcp','mcp.*')
      ->first();

      if (!$queryCPropia) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron compras registradas'
        );
      } else {
        $folio_movimiento_cp_cancelado = "MCP-" . $JwtAuth->generarFolio($queryCPropia->movimiento_cp_folio);
        $fecha_registro = time();

        $old_movimiento_destino = DB::table('fnzs_actividad_movimientos')->where('id', $queryCPropia->movimiento_cp_destino)->first();
        $new_movimiento_origen = (array) $old_movimiento_destino;
        unset($new_movimiento_origen['id']); // Quitamos el ID para el AUTO_INCREMENT
        $new_movimiento_origen['movimiento_asociado'] = $queryCPropia->movimiento_cp_destino;
        if ($old_movimiento_destino->tipo_movimiento === 'S') {
          $new_movimiento_origen['tipo_movimiento'] = 'R';
        } elseif ($old_movimiento_destino->tipo_movimiento === 'R') {
          $new_movimiento_origen['tipo_movimiento'] = 'S';
        }

        $folioMovimOrigen = DB::select("SELECT IF (max(movim.folio_movimiento) IS NOT NULL,(max(movim.folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS movim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
          JOIN teci_usuarios_catalogo AS users WHERE movim.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", 
          [$empresa, $usuario]);
        $new_movimiento_origen['token_movimiento'] = Str::uuid()->toString();
        $new_movimiento_origen['folio_movimiento'] = $folioMovimOrigen[0]->folio;
        $new_movimiento_origen['fecha_sistema'] = $fecha_registro;
        $id_nuevo_origen_registrado = DB::table('fnzs_actividad_movimientos')->insertGetId($new_movimiento_origen);

        //cancelando movimiento de destino
        DB::table('fnzs_actividad_movimientos')->where('id', $queryCPropia->movimiento_cp_destino)
        ->update(['movimiento_cancelado' => TRUE,'fecha_contabilizacion_cancelacion' => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL]);

        $old_movimiento_origen = DB::table('fnzs_actividad_movimientos')->where('id', $queryCPropia->movimiento_cp_origen)->first();
        $new_movimiento_destino = (array) $old_movimiento_origen;
        unset($new_movimiento_destino['id']); // Quitamos el ID para el AUTO_INCREMENT
        $new_movimiento_destino['movimiento_asociado'] = $queryCPropia->movimiento_cp_origen;
        if ($old_movimiento_origen->tipo_movimiento === 'S') {
          $new_movimiento_destino['tipo_movimiento'] = 'R';
        } elseif ($old_movimiento_origen->tipo_movimiento === 'R') {
          $new_movimiento_destino['tipo_movimiento'] = 'S';
        }

        $folioMovimDestino = DB::select("SELECT IF (max(movim.folio_movimiento) IS NOT NULL,(max(movim.folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS movim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
          JOIN teci_usuarios_catalogo AS users WHERE movim.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", 
          [$empresa, $usuario]);
        $new_movimiento_destino['token_movimiento'] = Str::uuid()->toString();
        $new_movimiento_destino['folio_movimiento'] = $folioMovimDestino[0]->folio;
        $new_movimiento_destino['fecha_sistema'] = $fecha_registro;
        $id_nuevo_destino_registrado = DB::table('fnzs_actividad_movimientos')->insertGetId($new_movimiento_destino);

        //cancelando movimiento de origen
        DB::table('fnzs_actividad_movimientos')->where('id', $queryCPropia->movimiento_cp_origen)
        ->update(['movimiento_cancelado' => TRUE,'fecha_contabilizacion_cancelacion' => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL]);

        DB::table('fnzs_movimientos_cuentas_propias')->where('id', $queryCPropia->id_mcp)
        ->update([
          'movimiento_cp_cancelado' => TRUE,
          'movimiento_cp_canceled_fecha' => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
          'movimiento_cp_canceled_observaciones' => $JwtAuth->encriptar($observaciones),
          'movimiento_cp_canceled_user_cancela' => $vEmp->userr
        ]);
        
        $folioMovimCP = DB::select("SELECT IF (max(mcp.movimiento_cp_folio) IS NOT NULL,(max(mcp.movimiento_cp_folio)+1),1) AS folio FROM fnzs_movimientos_cuentas_propias AS mcp JOIN main_empresas AS emp 
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mcp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token = ?",[$empresa, $usuario]);
        $token_movimiento_cp = $JwtAuth->encriptarToken($fecha_contabilizacion.$id_nuevo_origen_registrado.$id_nuevo_destino_registrado);
        $insertCPMovimientos = DB::table("fnzs_movimientos_cuentas_propias")->insert(
          array(
            "movimiento_cp_token" => $token_movimiento_cp,
            "movimiento_cp_folio" => $folioMovimCP[0]->folio,
            "movimiento_cp_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
            "movimiento_cp_origen" => $id_nuevo_origen_registrado,
            "movimiento_cp_destino" => $id_nuevo_destino_registrado,
            "movimiento_cp_observaciones" => $JwtAuth->encriptar("Movimiento relacionado a la cancelación del folio $folio_movimiento_cp_cancelado"),
            "movimiento_asociado_cancelado" => $queryCPropia->id_mcp,
            "empresa" => $vEmp->id
          )
        );
        $new_folio_movimiento_cp = "MCP-" . $JwtAuth->generarFolio($folioMovimCP[0]->folio);

				DB::table("fnzs_mov_cuent_propias_cancelacion")
				->where("token_cancel_mcp",$token_cancel_mcp)
				->limit(1)->update(array("mcp_cancel_realizada" => TRUE));

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => "Movimiento entre cuentas propias con folio $folio_movimiento_cp_cancelado ha sido cancelado, segenero un nuevo registro con el folio $new_folio_movimiento_cp"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //pago de nominas
	public function solicitudCancelacionDispersionPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_solip' => 'required|string',
      'token_pagos' => 'required|string'
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
      $token_cancel_solip = $request->input('token_cancel_solip');
      $token_pagos = $request->input('token_pagos');

			$queryPagosDone = DB::table("fnzs_pagos_pago AS payment")
      ->join("fnzs_pagos_soli_cancelacion AS pcanc", "payment.id", "=","pcanc.pago_cancel")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "=","vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
			->join("main_empresas AS emp", "payment.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->whereNotNull('order.nomina_main')
			->where([
				"pcanc.token_cancel_solip" => $token_cancel_solip,
				"payment.token_pagos" => $token_pagos,
				"payment.pago_autorizado" => TRUE,
				"payment.status_pagos" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'pcanc.token_cancel_solip',
        'pcanc.folio_cancel_solip',
        'pcanc.fecha_cont_cancel_solip',
        'pcanc.pago_cancel_observaciones_mov',
        'payment.id As id_pago',
        'payment.*',
        'emp.*',
        'order.nomina_main',
        'order.nomina_en_especie'
      )
			->orderBy("payment.folio_pagos", "DESC")->get();

      if ($queryPagosDone->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $lista_pagos = [];
        foreach ($queryPagosDone as $vPayDone) {
          $solicitud_folio = 'PAG-DISP-SOLI-CANC-'.$JwtAuth->generarFolio($vPayDone->folio_cancel_solip);
          $lista_movim_acreedor = [];
          $queryAcredorMovimDone = DB::table("fnzs_catalogo_acreedores_movimientos AS acrmov")
          ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acrmov.id", "=","vinc.mov_realizado")
          ->join("fnzs_pagos_pago AS pay", "vinc.pago_vinculado", "=", "pay.id")
          ->where("pay.status_pagos",TRUE)
          ->where("pay.token_pagos",$vPayDone->token_pagos)
          ->get();

          foreach ($queryAcredorMovimDone as $vMovDone) {
            $queryPersResponsable = DB::table("fnzs_catalogo_acreedores_movimientos AS movim")
            ->join("vhum_empleados_catalogo AS pers", "movim.acre_personal_mov", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
            ->first();
            $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
            $pers_responsmov_folio = $queryPersResponsable ? "TRB-".$JwtAuth->generarFolio($queryPersResponsable->folio_pers) : "";
            $pers_responsmov_name = $queryPersResponsable ? $JwtAuth->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

            $queryCaja = DB::table("fnzs_catalogos_caja AS caj")
            ->join("fnzs_catalogo_acreedores_movimientos_cajas AS mov_caj", "caj.id", "mov_caj.caja_relacionada")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_caj.mov_realizado", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('caj.token_caja','caj.no_caja','caj.alias_caja')
            ->first();

            $queryCuenta = DB::table("fnzs_catalogos_cuentas AS cuent")
            ->join("teci_bancos AS bank", "cuent.banco", "bank.id")
            ->join("fnzs_catalogo_acreedores_movimientos_cuentas AS mov_cuent", "cuent.id", "mov_cuent.cuenta_relacionada")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_cuent.mov_realizado", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('cuent.token_cuenta','cuent.folio_cuenta','cuent.cuenta')
            ->first();

            $queryMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
            //->join("teci_plataformas_digitales AS pdig", "moned.monedero", "pdig.id")
            ->join("fnzs_catalogo_acreedores_movimientos_monederos AS mov_mon", "moned.id", "mov_mon.moned_relacionado")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "mov_mon.mov_realizado", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
            ->first();

            if ($queryCaja) {
              $movimiento_tipo = "caja";
              $movimiento_token = $queryCaja->token_caja;
              $movimiento_folio = "CAJ-" . $JwtAuth->generarFolio($queryCaja->no_caja);
              $movimiento_name = $JwtAuth->desencriptar($queryCaja->alias_caja);
            } elseif ($queryCuenta) {
              $movimiento_tipo = "banco";
              $movimiento_token = $queryCuenta->token_cuenta;
              $movimiento_folio = 'CUENT-'.$JwtAuth->generarFolio($queryCuenta->folio_cuenta);
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($queryCuenta->cuenta);
              $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
              $movimiento_name = "**** **** **** $cuenta_descifrada_substr";
            } elseif ($queryMonedero) {
              $movimiento_tipo = "monedero";
              $movimiento_token = $queryMonedero->token_cuentamonedero;
              $movimiento_folio = "CUENTM-" . $JwtAuth->generarFolio($queryMonedero->folio_cuentmon);
              $movimiento_name = $queryMonedero->cuenta;
            } else {
              $movimiento_tipo = "N/A";
              $movimiento_token = "N/A";
              $movimiento_folio = "N/A";
              $movimiento_name = "N/A";
            }

            $mainMovs = DB::table("fnzs_actividad_movimientos AS movAct")
            ->join("fnzs_catalogo_acreedores_movimientos AS movim", "movAct.acreedor_movimiento", "movim.id")
            ->where('movim.token_acre_mov',$vMovDone->token_acre_mov)
            ->select('movAct.token_movimiento','movAct.tipo_movimiento','movAct.subtipo_movimiento')
            ->first();

            $lista_movim_acreedor[] = [
              "token_acre_mov" => $vMovDone->token_acre_mov,
              "folio_acre_mov" => "ACRMOV-".$JwtAuth->generarFolio($vMovDone->folio_acre_mov),
              "acre_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vMovDone->acre_fecha_contabilizacion),
              "act_token_movimiento" => $mainMovs ? $mainMovs->token_movimiento : '',
              "tipo_movimiento" => $mainMovs ? $mainMovs->tipo_movimiento : '',
              "subtipo_movimiento" => $mainMovs ? $mainMovs->subtipo_movimiento : '',
              //"responsable" => $vEmp->userr,
              "responsable_token" => $pers_responsmov_token,
              "responsable_folio" => $pers_responsmov_folio,
              "responsable_name" => $pers_responsmov_name,
              //"cuenta_monedero" => $sql_cuenta_monedero,
              "movimiento_tipo" => $movimiento_tipo,
              "movimiento_token" => $movimiento_token,
              "movimiento_folio" => $movimiento_folio,
              "movimiento_name" => $movimiento_name,
              "monto_aplicado" => "$".number_format($vMovDone->acre_monto_mov,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
            ];
          }

          $lista_actividad_movim = $this->movimientos_realizados_by_pago($vPayDone->token_pagos,$vPayDone->p_moneda,$JwtAuth);
          $lista_trabajadores_dispersados = is_null($vPayDone->nomina_en_especie) ? $this->trabajadores_dispersados_by_pago($vPayDone->token_pagos,$JwtAuth) : $this->trabajadores_dispersados_especie_by_pago($vPayDone->token_pagos,$JwtAuth);

          $lista_pagos[] = [
            "token_pagos" => $vPayDone->token_pagos,
            "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($vPayDone->folio_pagos),
            "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago),
            "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vPayDone->fecha_contabilizacion) : "",
            "observacionesPago" => !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '',
            "monto_pago" => "$".number_format($vPayDone->monto_pago,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
            "tipo_cambio" => "$".number_format($vPayDone->tipo_cambio,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.',',')." $vPayDone->p_moneda",
            "p_moneda" => $vPayDone->p_moneda,
            "movimientos_acreedor" => $lista_movim_acreedor,
            "lista_actividad_movim" => $lista_actividad_movim,
            "token_cancel_solip" => $vPayDone->token_cancel_solip,
            "folio_cancel_solip" => $solicitud_folio,
            "pago_cancel_observaciones_mov" => $JwtAuth->desencriptar($vPayDone->pago_cancel_observaciones_mov),
            "lista_trabajadores_dispersados" => $lista_trabajadores_dispersados,
          ];
        }

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "pagos_realizados" => $lista_pagos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionDispersionPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cancel_soli_token' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
			$cancel_soli_token = $request->input('cancel_soli_token');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_cancel_soli_token = isset($cancel_soli_token) && !empty($cancel_soli_token);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_cancel_soli_token && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
				->first();
        
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_soli_cancelacion AS pcanc", "pay.id", "=","pcanc.pago_cancel")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->where("pcanc.token_cancel_solip",$cancel_soli_token)
          ->where("pay.status_pagos",TRUE)
          ->select("pay.id AS pay_id", "pay.token_pagos")
          ->get();

          foreach ($queryPagosDone as $vPayDone) {
            $maxFolio = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');

            DB::table("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc")
            ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
            ->whereNotNull('ord_pag.nomina_main')
            ->where("ord_vinc.pago_realizado",$vPayDone->pay_id)
            ->update(array(
              "ord_vinc.vinculo_cancelado" => TRUE,
              "ord_pag.orden_terminada_bool" => FALSE,
              "ord_pag.orden_terminada_fecha" => NULL,
              "ord_pag.fecha_contabilizacion_ordenPago" => NULL,
              //"ord_pag.pago_orden_cancelada" => TRUE,
              //"pago_orden_cancel_user" => $user_jerarquia,
              //"ord_pag.pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
              //"ord_pag.pago_orden_cancel_comentarios" => $comentarios_encriptados
            ));
            
            $folioCancelPagos = $maxFolio ? $maxFolio + 1 : 1;

            DB::table("fnzs_pagos_pago")->where("id",$vPayDone->pay_id)
            ->limit(1)->update(array(
              "pago_cancelado" => TRUE,
              "pago_cancelado_user" => $user_jerarquia,
              "pago_folio_cancelacion" => $folioCancelPagos,
              "pago_fecha_cancelacion" => $ahora,
              "pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
              "pago_comentarios_cancelacion" => $comentarios_encriptados
            ));

            $queryActiMovAcree = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.acreedor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "acr_mov.condicion_acree_mov",
              "acr_mov.acre_tipo_cambio",
              "acr_mov.acre_mov_moneda",
              "acr_mov.vinc_acreedor"
            )
            ->get();

            if (!$queryActiMovAcree->isEmpty()) {
              $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMovAcree,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActiMovDeu = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_deudores_movimientos AS deu_mov", "act_mov.deudor_movimiento", "=","deu_mov.id")
            ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deu_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "deu_mov.condicion_deu_mov",
              "deu_mov.deu_tipo_cambio",
              "deu_mov.deu_mov_moneda",
              "deu_mov.vinc_deudor"
            )
            ->get();

            if (!$queryActiMovDeu->isEmpty()) {
              $this->pagoDeudoresMovimientos($JwtAuth,$queryActiMovDeu,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_pagos_pago AS pay", "act_mov.pago", "=", "pay.id")
            ->where("pay.id",$vPayDone->pay_id)
            ->where("pay.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.seccion_movimiento",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.fecha_contabilizacion_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.descripcion_tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.cobro",
              "act_mov.acreedor_movimiento",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa"
            )
            ->get();
            if (!$queryActivMovimDone->isEmpty()) {
              $this->pagoActMovimientos($JwtAuth,$queryActivMovimDone,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }
          }
					
					DB::table("fnzs_pagos_soli_cancelacion")
					->where("token_cancel_solip",$cancel_soli_token)
					->limit(1)->update(array("pago_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_cancel_soli_token) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //nomina en efectivo
	public function solicitudCancelacionOrdenDispersionNominaEfectivo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliordp' => 'required|string',
      'token_orden_pago' => 'required|string'
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
      $token_cancel_soliordp = $request->input('token_cancel_soliordp');
      $token_orden_pago = $request->input('token_orden_pago');
      
			$queryPagoOrden = DB::table("fnzs_pagos_orden AS order")
      ->join("fnzs_orden_pagos_soli_cancelacion AS p_ordcanc", "order.id", "=","p_ordcanc.orden_pago_cancel")
      ->join("vhum_nominas_main AS nomi", "order.nomina_main", "=", "nomi.id")
			->join("main_empresas AS emp", "order.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->whereIn('nomi.id', function ($query) {
        $query->select('nomina_main')->from('vhum_nominas_recibos');
      })
      ->whereNull('order.nomina_en_especie')
			->where([
				"p_ordcanc.token_cancel_soliordp" => $token_cancel_soliordp,
				"order.token_ordenPago" => $token_orden_pago,
				"order.status_ordenPago" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'nomi.*',
        'p_ordcanc.token_cancel_soliordp',
        'p_ordcanc.folio_cancel_soliordp',
        'p_ordcanc.fecha_cont_cancel_soliordp',
        'p_ordcanc.orden_pago_cancel_observaciones_mov',
        'order.id As id_orden_pago',
        'order.pago_orden_cancelada As op_cancel',
        'order.*',
        'emp.empresa_token', 
        'emp.zona_horaria'
      )
			->orderBy("order.folio_ordenPago", "DESC")->get();

      if ($queryPagoOrden->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $solicitud_desglose = [];
        foreach ($queryPagoOrden as $rOrdPag) {
          $solicitud_folio = 'ORDPAG-SOLI-CANC-'.$JwtAuth->generarFolio($rOrdPag->folio_cancel_soliordp);

          $solicitud_desglose[] = [
            "token_cancel_soliordp" => $rOrdPag->token_cancel_soliordp,
            "folio_cancel_soliordp" => $solicitud_folio,
            "orden_pago_cancel_observaciones_mov" => $JwtAuth->desencriptar($rOrdPag->orden_pago_cancel_observaciones_mov),
          ];
        }
        $orden_pago = $this->eachGeneralDisper($queryPagoOrden,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "solicitud_desglose" => $solicitud_desglose,
          "orden_pago" => $orden_pago
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionOrdenDispersionNominaEfectivo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliordp' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
			$token_cancel_soliordp = $request->input('token_cancel_soliordp');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_token_cancel_soliordp = isset($token_cancel_soliordp) && !empty($token_cancel_soliordp);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_token_cancel_soliordp && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.id AS userr','users.jerarquia_main')
				->first();
				
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
					$ordenData = DB::table("fnzs_pagos_orden AS order")
          ->join("fnzs_orden_pagos_soli_cancelacion AS p_ordcanc", "order.id", "=","p_ordcanc.orden_pago_cancel")
          ->join("main_empresas AS emp", "order.empresa", "emp.id")
          ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
          ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
          ->where([
            "p_ordcanc.token_cancel_soliordp" => $token_cancel_soliordp,
            "order.status_ordenPago" => TRUE,
            "emp.empresa_token" => $empresa,
            "users.usuario_token" => $usuario
          ])
          ->select('order.id As id_orden_pago','order.token_ordenPago','order.nomina_main AS ord_nomina_main')
          ->lockForUpdate()->first();
          //if (!$ordenData) continue;

          DB::table("fnzs_pagos_pago_ordenes_vinculadas")
          ->where("orden_pago_vinculada",$ordenData->id_orden_pago)
          ->update(array("vinculo_cancelado" => TRUE));

          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL; 

          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->where("vinc.orden_pago_vinculada",$ordenData->id_orden_pago)
          ->where("pay.status_pagos",TRUE)
          ->select("pay.id AS pay_id", "pay.token_pagos")
          ->get();

          foreach ($queryPagosDone as $vPayDone) {
            $maxFolio = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');
            $folioCancelPagos = $maxFolio ? $maxFolio + 1 : 1;

            DB::table("fnzs_pagos_pago")->where("id",$vPayDone->pay_id)
            ->limit(1)->update(array(
              "pago_cancelado" => TRUE,
              "pago_cancelado_user" => $user_jerarquia,
              "pago_folio_cancelacion" => $folioCancelPagos,
              "pago_fecha_cancelacion" => $ahora,
              "pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
              "pago_comentarios_cancelacion" => $comentarios_encriptados
            ));

            $queryActiMovAcree = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.acreedor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "acr_mov.condicion_acree_mov",
              "acr_mov.acre_tipo_cambio",
              "acr_mov.acre_mov_moneda",
              "acr_mov.vinc_acreedor"
            )
            ->get();
            
            if (!$queryActiMovAcree->isEmpty()) {
              $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMovAcree,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActiMovDeu = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_deudores_movimientos AS deu_mov", "act_mov.deudor_movimiento", "=","deu_mov.id")
            ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deu_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "deu_mov.condicion_deu_mov",
              "deu_mov.deu_tipo_cambio",
              "deu_mov.deu_mov_moneda",
              "deu_mov.vinc_deudor"
            )
            ->get();

            if (!$queryActiMovDeu->isEmpty()) {
              $this->pagoDeudoresMovimientos($JwtAuth,$queryActiMovDeu,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_pagos_pago AS pay", "act_mov.pago", "=", "pay.id")
            ->where("pay.id",$vPayDone->pay_id)
            ->where("pay.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.seccion_movimiento",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.fecha_contabilizacion_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.descripcion_tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.cobro",
              "act_mov.acreedor_movimiento",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa"
            )
            ->get();

            if (!$queryActivMovimDone->isEmpty()) {
              $this->pagoActMovimientos($JwtAuth,$queryActivMovimDone,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }
          }

          DB::table("fnzs_pagos_orden")
          ->where("id",$ordenData->id_orden_pago)
          ->where("nomina_main",$ordenData->ord_nomina_main)
          ->update(array(
            "orden_bloqueada" => TRUE,
            "autorizacion_pay" => FALSE,
            "fecha_autorizacion_pay" => NULL,
            "tentativa_pago" => NULL,	
            "orden_terminada_bool" => FALSE,	
            "orden_terminada_fecha" => NULL,
            "fecha_contabilizacion_ordenPago" => NULL,
            "pago_orden_cancelada" => TRUE,
            "pago_orden_cancel_user" => $user_jerarquia,
            "pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
            "pago_orden_cancel_comentarios" => $comentarios_encriptados
          ));
					
					DB::table("fnzs_orden_pagos_soli_cancelacion")
					->where("token_cancel_soliordp",$token_cancel_soliordp)
					->limit(1)->update(array("orden_pago_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_token_cancel_soliordp) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //nomina en especie
	public function solicitudCancelacionOrdenDispersionNominaEspecie(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliordp' => 'required|string',
      'token_orden_pago' => 'required|string'
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
      $token_cancel_soliordp = $request->input('token_cancel_soliordp');
      $token_orden_pago = $request->input('token_orden_pago');
      
			$queryPagoOrden = DB::table("fnzs_pagos_orden AS order")
      ->join("fnzs_orden_pagos_soli_cancelacion AS p_ordcanc", "order.id", "=","p_ordcanc.orden_pago_cancel")
      ->join("vhum_nominas_main AS nomi", "order.nomina_main", "=", "nomi.id")
			->join("main_empresas AS emp", "order.empresa", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->whereIn('nomi.id', function ($query) {
        $query->select('nomina_main')->from('vhum_nominas_recibos');
      })
      ->whereNotNull('order.nomina_en_especie')
			->where([
				"p_ordcanc.token_cancel_soliordp" => $token_cancel_soliordp,
				"order.token_ordenPago" => $token_orden_pago,
				"order.status_ordenPago" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'nomi.*',
        'p_ordcanc.token_cancel_soliordp',
        'p_ordcanc.folio_cancel_soliordp',
        'p_ordcanc.fecha_cont_cancel_soliordp',
        'p_ordcanc.orden_pago_cancel_observaciones_mov',
        'order.id As id_orden_pago',
        'order.pago_orden_cancelada As op_cancel',
        'order.*',
        'emp.empresa_token', 
        'emp.zona_horaria'
      )
			->orderBy("order.folio_ordenPago", "DESC")->get();

      if ($queryPagoOrden->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $solicitud_desglose = [];
        foreach ($queryPagoOrden as $rOrdPag) {
          $solicitud_folio = 'ORDPAG-SOLI-CANC-'.$JwtAuth->generarFolio($rOrdPag->folio_cancel_soliordp);

          $solicitud_desglose[] = [
            "token_cancel_soliordp" => $rOrdPag->token_cancel_soliordp,
            "folio_cancel_soliordp" => $solicitud_folio,
            "orden_pago_cancel_observaciones_mov" => $JwtAuth->desencriptar($rOrdPag->orden_pago_cancel_observaciones_mov),
          ];
        }
        $orden_pago = $this->eachGeneralDisper($queryPagoOrden,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "solicitud_desglose" => $solicitud_desglose,
          "orden_pago" => $orden_pago
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionOrdenDispersionNominaEspecie(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_soliordp' => 'required|string',
			'fecha_contabilizacion' => 'required|string',
      'comentarios_confirma_cancelacion' => 'required|string'
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
			$token_cancel_soliordp = $request->input('token_cancel_soliordp');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_token_cancel_soliordp = isset($token_cancel_soliordp) && !empty($token_cancel_soliordp);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_token_cancel_soliordp && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.id AS userr','users.jerarquia_main')
				->first();
				
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//da_te_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
				$ahora = time();

				DB::beginTransaction();
				try {
					$ordenData = DB::table("fnzs_pagos_orden AS order")
          ->join("fnzs_orden_pagos_soli_cancelacion AS p_ordcanc", "order.id", "=","p_ordcanc.orden_pago_cancel")
          ->join("main_empresas AS emp", "order.empresa", "emp.id")
          ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
          ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
          ->where([
            "p_ordcanc.token_cancel_soliordp" => $token_cancel_soliordp,
            "order.status_ordenPago" => TRUE,
            "emp.empresa_token" => $empresa,
            "users.usuario_token" => $usuario
          ])
          ->select('order.id As id_orden_pago','order.token_ordenPago','order.nomina_main AS ord_nomina_main','order.nomina_en_especie AS ord_nomina_en_especie')
          ->lockForUpdate()->first();
          //if (!$ordenData) continue;

          DB::table("fnzs_pagos_pago_ordenes_vinculadas")
          ->where("orden_pago_vinculada",$ordenData->id_orden_pago)
          ->update(array("vinculo_cancelado" => TRUE));

          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL; 

          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->where("vinc.orden_pago_vinculada",$ordenData->id_orden_pago)
          ->where("pay.status_pagos",TRUE)
          ->select("pay.id AS pay_id", "pay.token_pagos")
          ->get();

          foreach ($queryPagosDone as $vPayDone) {
            $maxFolio = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('pago_folio_cancelacion');
            $folioCancelPagos = $maxFolio ? $maxFolio + 1 : 1;

            DB::table("fnzs_pagos_pago")->where("id",$vPayDone->pay_id)
            ->limit(1)->update(array(
              "pago_cancelado" => TRUE,
              "pago_cancelado_user" => $user_jerarquia,
              "pago_folio_cancelacion" => $folioCancelPagos,
              "pago_fecha_cancelacion" => $ahora,
              "pago_fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
              "pago_comentarios_cancelacion" => $comentarios_encriptados
            ));

            $queryActiMovAcree = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_acreedores_movimientos AS acr_mov", "act_mov.acreedor_movimiento", "=","acr_mov.id")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acr_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.acreedor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "acr_mov.condicion_acree_mov",
              "acr_mov.acre_tipo_cambio",
              "acr_mov.acre_mov_moneda",
              "acr_mov.vinc_acreedor"
            )
            ->get();
            
            if (!$queryActiMovAcree->isEmpty()) {
              $this->pagoAcreedoresMovimientos($JwtAuth,$queryActiMovAcree,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActiMovDeu = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_catalogo_deudores_movimientos AS deu_mov", "act_mov.deudor_movimiento", "=","deu_mov.id")
            ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deu_mov.id", "=","vinc.mov_realizado")
            ->join("fnzs_pagos_pago AS pag", "vinc.pago_vinculado", "=","pag.id")
            ->where("pag.id",$vPayDone->pay_id)
            ->where("pag.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa",
              "deu_mov.condicion_deu_mov",
              "deu_mov.deu_tipo_cambio",
              "deu_mov.deu_mov_moneda",
              "deu_mov.vinc_deudor"
            )
            ->get();

            if (!$queryActiMovDeu->isEmpty()) {
              $this->pagoDeudoresMovimientos($JwtAuth,$queryActiMovDeu,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }

            $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS act_mov")
            ->join("fnzs_pagos_pago AS pay", "act_mov.pago", "=", "pay.id")
            ->where("pay.id",$vPayDone->pay_id)
            ->where("pay.status_pagos",TRUE)
            ->select(
              "act_mov.id AS idMov",
              "act_mov.token_movimiento",
              "act_mov.folio_movimiento",
              "act_mov.fecha_sistema",
              "act_mov.seccion_movimiento",
              "act_mov.fecha_contabilizacion_movimiento",
              "act_mov.movimiento_cancelado",
              "act_mov.folio_cancelacion",
              "act_mov.fecha_cancelacion",
              "act_mov.fecha_contabilizacion_cancelacion",
              "act_mov.movimiento_asociado",
              "act_mov.tipo_movimiento",
              "act_mov.descripcion_tipo_movimiento",
              "act_mov.subtipo_movimiento",
              "act_mov.concepto_movimiento",
              "act_mov.responsable",
              "act_mov.caja",
              "act_mov.cuenta_bancaria",
              "act_mov.cuenta_monedero",
              "act_mov.monto_aplicado",
              "act_mov.moneda_movimiento",
              "act_mov.tipo_cambio_movimiento",
              "act_mov.observaciones_movimiento",
              "act_mov.pago",
              "act_mov.cobro",
              "act_mov.acreedor_movimiento",
              "act_mov.deudor_movimiento",
              "act_mov.ajuste",
              "act_mov.empresa"
            )
            ->get();

            if (!$queryActivMovimDone->isEmpty()) {
              $this->pagoActMovimientos($JwtAuth,$queryActivMovimDone,$vEmp->id,$comentarios_confi,$ahora,$fecha_contabilizacion,$vEmp->userr);
            }
          }

          DB::table("fnzs_pagos_orden")
          ->where("id",$ordenData->id_orden_pago)
          ->where("nomina_main",$ordenData->ord_nomina_main)
          ->where("nomina_en_especie",$ordenData->ord_nomina_en_especie)
          ->update(array(
            "orden_bloqueada" => TRUE,
            "autorizacion_pay" => FALSE,
            "fecha_autorizacion_pay" => NULL,
            "tentativa_pago" => NULL,	
            "orden_terminada_bool" => FALSE,	
            "orden_terminada_fecha" => NULL,
            "fecha_contabilizacion_ordenPago" => NULL,
            "pago_orden_cancelada" => TRUE,
            "pago_orden_cancel_user" => $user_jerarquia,
            "pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
            "pago_orden_cancel_comentarios" => $comentarios_encriptados
          ));
					
					DB::table("fnzs_orden_pagos_soli_cancelacion")
					->where("token_cancel_soliordp",$token_cancel_soliordp)
					->limit(1)->update(array("orden_pago_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_token_cancel_soliordp) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //funciones para solicitar cancelación
	public function pagoRealizadoSolicitarCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'pago_realizado' => 'required|string',
      'solicitud_fecha_contabilizacion' => 'required|string',
      'solicitud_observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $pago_realizado = $request->input('pago_realizado');
      $solicitud_fecha_contabilizacion = $request->input('solicitud_fecha_contabilizacion');
      $solicitud_observaciones = $request->input('solicitud_observaciones');
      
      $OKPagoRealizado = isset($pago_realizado) && !empty($pago_realizado);
			$OKFechaContabilizacion = isset($solicitud_fecha_contabilizacion) && !empty($solicitud_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(), $solicitud_fecha_contabilizacion);
			$OKObservacionesSolicitud = isset($solicitud_observaciones) && !empty($solicitud_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $solicitud_observaciones);

      if ($OKPagoRealizado && $OKFechaContabilizacion && $OKObservacionesSolicitud) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            $id_pago = DB::table('fnzs_pagos_pago')->where('token_pagos',$pago_realizado)->value('id');

            $maxFolio = DB::table('fnzs_pagos_soli_cancelacion')
            ->where('pago_cancel_empresa', $vEmp->id)
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            ->max('folio_cancel_solip');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'PAG-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);
            
            DB::table("fnzs_pagos_soli_cancelacion")
            ->insert(
              array(
                "token_cancel_solip" => Str::uuid()->toString(),
                "folio_cancel_solip" => $folioSoliNuevo,
                "fecha_cancel_solip" => time(),
                "fecha_cont_cancel_solip" => $JwtAuth->convierteFechaEpoc($solicitud_fecha_contabilizacion),
                "pago_cancel" => $id_pago,
                "pago_cancel_observaciones_mov" => $JwtAuth->encriptar($solicitud_observaciones),
                "pago_cancel_realizada" => FALSE,
                "pago_cancel_empresa" => $vEmp->id,
                "pago_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de pago ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKPagoRealizado) $mensaje_error = 'Error en acreedor seleccionado, verifique su información';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservacionesSolicitud) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function pagoRealizadoDispersionSolicitarCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'pago_realizado' => 'required|string',
      'solicitud_fecha_contabilizacion' => 'required|string',
      'solicitud_observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $pago_realizado = $request->input('pago_realizado');
      $solicitud_fecha_contabilizacion = $request->input('solicitud_fecha_contabilizacion');
      $solicitud_observaciones = $request->input('solicitud_observaciones');
      
      $OKPagoRealizado = isset($pago_realizado) && !empty($pago_realizado);
			$OKFechaContabilizacion = isset($solicitud_fecha_contabilizacion) && !empty($solicitud_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(), $solicitud_fecha_contabilizacion);
			$OKObservacionesSolicitud = isset($solicitud_observaciones) && !empty($solicitud_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $solicitud_observaciones);

      if ($OKPagoRealizado && $OKFechaContabilizacion && $OKObservacionesSolicitud) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            $id_pago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc", "pay.id", "=","ord_vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
            ->whereNotNull('ord_pag.nomina_main')
            ->where('pay.token_pagos',$pago_realizado)
            ->value('pay.id');

            $maxFolio = DB::table('fnzs_pagos_soli_cancelacion')
            ->where('pago_cancel_empresa', $vEmp->id)
            ->whereIn('pago_cancel', function ($query) {
              $query->select('ord_vinc.pago_realizado')->from('fnzs_pagos_pago_ordenes_vinculadas AS ord_vinc')
              ->join("fnzs_pagos_orden AS ord_pag", "ord_vinc.orden_pago_vinculada", "=","ord_pag.id")
              ->whereNotNull('ord_pag.nomina_main');
            })
            ->orderByDesc('folio_cancel_solip')
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            //->max('folio_cancel_solip');
            ->value('folio_cancel_solip');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'PAG-DISP-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);
            
            DB::table("fnzs_pagos_soli_cancelacion")
            ->insert(
              array(
                "token_cancel_solip" => Str::uuid()->toString(),
                "folio_cancel_solip" => $folioSoliNuevo,
                "fecha_cancel_solip" => time(),
                "fecha_cont_cancel_solip" => $JwtAuth->convierteFechaEpoc($solicitud_fecha_contabilizacion),
                "pago_cancel" => $id_pago,
                "pago_cancel_observaciones_mov" => $JwtAuth->encriptar($solicitud_observaciones),
                "pago_cancel_realizada" => FALSE,
                "pago_cancel_empresa" => $vEmp->id,
                "pago_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de pago ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKPagoRealizado) $mensaje_error = 'Error en acreedor seleccionado, verifique su información';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservacionesSolicitud) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function ordenPagoSolicitarCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_pago' => 'required|string',
      'solicitud_fecha_contabilizacion' => 'required|string',
      'solicitud_observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $orden_pago = $request->input('orden_pago');
      $solicitud_fecha_contabilizacion = $request->input('solicitud_fecha_contabilizacion');
      $solicitud_observaciones = $request->input('solicitud_observaciones');
      
      $OKPagoRealizado = isset($orden_pago) && !empty($orden_pago);
			$OKFechaContabilizacion = isset($solicitud_fecha_contabilizacion) && !empty($solicitud_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(), $solicitud_fecha_contabilizacion);
			$OKObservacionesSolicitud = isset($solicitud_observaciones) && !empty($solicitud_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $solicitud_observaciones);

      if ($OKPagoRealizado && $OKFechaContabilizacion && $OKObservacionesSolicitud) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            $id_orden_pago = DB::table('fnzs_pagos_orden')->where('token_ordenPago',$orden_pago)->value('id');

            $maxFolio = DB::table('fnzs_orden_pagos_soli_cancelacion')
            ->where('orden_pago_cancel_empresa', $vEmp->id)
            ->whereIn('orden_pago_cancel', function ($query) {
              $query->select('id')
              ->from('fnzs_pagos_orden')
              ->whereNull('nomina_main');
            })
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            ->max('folio_cancel_soliordp');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'ORDPAG-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);
            
            DB::table("fnzs_orden_pagos_soli_cancelacion")
            ->insert(
              array(
                "token_cancel_soliordp" => Str::uuid()->toString(),
                "folio_cancel_soliordp" => $folioSoliNuevo,
                "fecha_cancel_soliordp" => time(),
                "fecha_cont_cancel_soliordp" => $JwtAuth->convierteFechaEpoc($solicitud_fecha_contabilizacion),
                "orden_pago_cancel" => $id_orden_pago,
                "orden_pago_cancel_observaciones_mov" => $JwtAuth->encriptar($solicitud_observaciones),
                "orden_pago_cancel_realizada" => FALSE,
                "orden_pago_cancel_empresa" => $vEmp->id,
                "orden_pago_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de orden de pago ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKPagoRealizado) $mensaje_error = 'Error en acreedor seleccionado, verifique su información';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservacionesSolicitud) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  public function disperNominaEfectSolicitaCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_dispersion' => 'required|string',
      'solicitud_fecha_contabilizacion' => 'nullable|string',
      'solicitud_observaciones' => 'nullable|string',
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
      $orden_dispersion = $request->input('orden_dispersion');
      $solicitud_fecha_contabilizacion = $request->input('solicitud_fecha_contabilizacion');
      $solicitud_observaciones = $request->input('solicitud_observaciones');
      
      $OKDispersionRealizada = isset($orden_dispersion) && !empty($orden_dispersion);
			$OKFechaContabilizacion = isset($solicitud_fecha_contabilizacion) && !empty($solicitud_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(), $solicitud_fecha_contabilizacion);
			$OKObservacionesSolicitud = isset($solicitud_observaciones) && !empty($solicitud_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $solicitud_observaciones);

      if ($OKDispersionRealizada && $OKFechaContabilizacion && $OKObservacionesSolicitud) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            $id_orden_pago = DB::table('fnzs_pagos_orden')->where('token_ordenPago',$orden_dispersion)->value('id');

            $maxFolio = DB::table('fnzs_orden_pagos_soli_cancelacion')
            ->whereIn('orden_pago_cancel', function ($query) {
              $query->select('id')
              ->from('fnzs_pagos_orden')
              ->whereNotNull('nomina_main')
              ->whereNull('nomina_en_especie');
            })
            ->where('orden_pago_cancel_empresa', $vEmp->id)
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            ->max('folio_cancel_soliordp');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'ORD-DISPER-EF-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);
            
            DB::table("fnzs_orden_pagos_soli_cancelacion")
            ->insert(
              array(
                "token_cancel_soliordp" => Str::uuid()->toString(),
                "folio_cancel_soliordp" => $folioSoliNuevo,
                "fecha_cancel_soliordp" => time(),
                "fecha_cont_cancel_soliordp" => $JwtAuth->convierteFechaEpoc($solicitud_fecha_contabilizacion),
                "orden_pago_cancel" => $id_orden_pago,
                "orden_pago_cancel_observaciones_mov" => $JwtAuth->encriptar($solicitud_observaciones),
                "orden_pago_cancel_realizada" => FALSE,
                "orden_pago_cancel_empresa" => $vEmp->id,
                "orden_pago_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de orden de dispersión de nómina ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKDispersionRealizada) $mensaje_error = 'Error en acreedor seleccionado, verifique su información';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservacionesSolicitud) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function dispeNnominaEspecieSolicitaCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_dispersion' => 'required|string',
      'solicitud_fecha_contabilizacion' => 'nullable|string',
      'solicitud_observaciones' => 'nullable|string',
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
      $orden_dispersion = $request->input('orden_dispersion');
      $solicitud_fecha_contabilizacion = $request->input('solicitud_fecha_contabilizacion');
      $solicitud_observaciones = $request->input('solicitud_observaciones');
      
      $OKDispersionRealizada = isset($orden_dispersion) && !empty($orden_dispersion);
			$OKFechaContabilizacion = isset($solicitud_fecha_contabilizacion) && !empty($solicitud_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(), $solicitud_fecha_contabilizacion);
			$OKObservacionesSolicitud = isset($solicitud_observaciones) && !empty($solicitud_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $solicitud_observaciones);

      if ($OKDispersionRealizada && $OKFechaContabilizacion && $OKObservacionesSolicitud) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            $id_orden_pago = DB::table('fnzs_pagos_orden')->where('token_ordenPago',$orden_dispersion)->value('id');

            $maxFolio = DB::table('fnzs_orden_pagos_soli_cancelacion')
            ->whereIn('orden_pago_cancel', function ($query) {
              $query->select('id')
              ->from('fnzs_pagos_orden')
              ->whereNotNull('nomina_main')
              ->whereNotNull('nomina_en_especie');
            })
            ->where('orden_pago_cancel_empresa', $vEmp->id)
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            ->max('folio_cancel_soliordp');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'ORD-DISPER-ES-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);
            
            DB::table("fnzs_orden_pagos_soli_cancelacion")
            ->insert(
              array(
                "token_cancel_soliordp" => Str::uuid()->toString(),
                "folio_cancel_soliordp" => $folioSoliNuevo,
                "fecha_cancel_soliordp" => time(),
                "fecha_cont_cancel_soliordp" => $JwtAuth->convierteFechaEpoc($solicitud_fecha_contabilizacion),
                "orden_pago_cancel" => $id_orden_pago,
                "orden_pago_cancel_observaciones_mov" => $JwtAuth->encriptar($solicitud_observaciones),
                "orden_pago_cancel_realizada" => FALSE,
                "orden_pago_cancel_empresa" => $vEmp->id,
                "orden_pago_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de orden de dispersión de nómina ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKDispersionRealizada) $mensaje_error = 'Error en acreedor seleccionado, verifique su información';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservacionesSolicitud) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

	public function anticipoSolicitarCancelacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'anticipo_uuid' => 'required|string',
      'solicitud_fecha_contabilizacion' => 'required|string',
      'solicitud_observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $anticipo_uuid = $request->input('anticipo_uuid');
      $solicitud_fecha_contabilizacion = $request->input('solicitud_fecha_contabilizacion');
      $solicitud_observaciones = $request->input('solicitud_observaciones');
      
      $OKPagoRealizado = isset($anticipo_uuid) && !empty($anticipo_uuid);
			$OKFechaContabilizacion = isset($solicitud_fecha_contabilizacion) && !empty($solicitud_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(), $solicitud_fecha_contabilizacion);
			$OKObservacionesSolicitud = isset($solicitud_observaciones) && !empty($solicitud_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $solicitud_observaciones);

      if ($OKPagoRealizado && $OKFechaContabilizacion && $OKObservacionesSolicitud) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            $maxFolio = DB::table('fnzs_anticipos_soli_cancelacion')
            ->where('anticipo_cancel_empresa', $vEmp->id)
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            ->max('folio_cancel_soliant');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'ANT-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);

            //CREATE TABLE fnzs_anticipos_soli_cancelacion (
            //  id int(10) primary key NOT NULL auto_increment,
            //  token_cancel_soliant text DEFAULT NULL,
            //  folio_cancel_soliant int(5) NOT NULL,
            //  fecha_cancel_soliant int(10) UNSIGNED DEFAULT NULL,
            //  fecha_cont_cancel_soliant int(10) UNSIGNED NOT NULL,
            //  anticipo_cancel_uuid varchar(255) NOT NULL,
            //  anticipo_cancel_observaciones_mov text DEFAULT NULL,
            //  anticipo_cancel_realizada boolean DEFAULT 0,
            //  anticipo_cancel_empresa int(10) DEFAULT NULL,
            //  anticipo_cancel_status boolean NOT NULL,
            //  anticipo_cancel_fecha_delete text DEFAULT NULL,
            //  FOREIGN KEY (anticipo_cancel_uuid) REFERENCES eegr_catalogo_proveedores_anticipo (uuid_anticipo),
            //  FOREIGN KEY (anticipo_cancel_empresa) REFERENCES main_empresas (id)
            //);
            
            DB::table("fnzs_anticipos_soli_cancelacion")
            ->insert(
              array(
                "token_cancel_soliant" => Str::uuid()->toString(),
                "folio_cancel_soliant" => $folioSoliNuevo,
                "fecha_cancel_soliant" => time(),
                "fecha_cont_cancel_soliant" => $JwtAuth->convierteFechaEpoc($solicitud_fecha_contabilizacion),
                "anticipo_cancel_uuid" => $anticipo_uuid,
                "anticipo_cancel_observaciones_mov" => $JwtAuth->encriptar($solicitud_observaciones),
                "anticipo_cancel_realizada" => FALSE,
                "anticipo_cancel_empresa" => $vEmp->id,
                "anticipo_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de anticipo ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKPagoRealizado) $mensaje_error = 'Error en acreedor seleccionado, verifique su información';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservacionesSolicitud) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //funciones secundarias
	private function trabajadores_dispersados_by_pago($token_pagos,$JwtAuth){
    $lista_actividad_movim = [];

    $queryTrabDispersados = DB::table("sos_personas AS people")
    ->join("vhum_empleados_catalogo AS trab", "people.id", "=", "trab.empleado_name")
    ->join("fnzs_pagos_nomina_empleado_dispersion AS disper_trab", "trab.id", "=", "disper_trab.empleado_referenciado")
    ->join("fnzs_pagos_pago AS pay", "disper_trab.pago_referenciado", "=", "pay.id")
    ->where("pay.status_pagos",TRUE)
    ->where("pay.token_pagos",$token_pagos)
    ->select(
      'disper_trab.folio_dispersion',
      'trab.folio_pers',
      'trab.post_folio_pers',
      'people.paterno',
      'people.materno',
      'people.nombre',
      'disper_trab.monto',
      'disper_trab.moneda',
    )
    ->get();
    //echo count($queryTrabDispersados);

    foreach ($queryTrabDispersados as $vTrabDisp) {
      $folio_dispersion = 'DISPER-'.$JwtAuth->generarFolio($vTrabDisp->folio_dispersion);
      $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vTrabDisp->folio_pers).(!is_null($vTrabDisp->post_folio_pers) ? '-'.$vTrabDisp->post_folio_pers : '');
      $nombre_completo = ucwords($JwtAuth->desencriptar($vTrabDisp->paterno)). " " .ucwords($JwtAuth->desencriptar($vTrabDisp->materno)). " " .ucwords($JwtAuth->desencriptar($vTrabDisp->nombre));

      $lista_actividad_movim[] = [
        "folio_dispersion" => $folio_dispersion,
        "nombre_completo" => "$folio_empleado $nombre_completo",
        "monto" => "$".number_format($vTrabDisp->monto,$JwtAuth->getMonedaAPI($vTrabDisp->moneda), '.', ',')." $vTrabDisp->moneda",
      ];
    }
    return $lista_actividad_movim;
	}

	private function trabajadores_dispersados_especie_by_pago($token_pagos,$JwtAuth){
    //echo $token_pagos;
    $lista_actividad_movim = [];

    $queryTrabDispersados = DB::table("sos_personas AS people")
    ->join("vhum_empleados_catalogo AS trab", "people.id", "=", "trab.empleado_name")
    ->join("fnzs_pagos_nomina_empleado_especie AS disper_esp_trab", "trab.id", "=", "disper_esp_trab.empleado_referenciado")
    ->join("fnzs_pagos_pago AS pay", "disper_esp_trab.pago_referenciado", "=", "pay.id")
    ->where("pay.status_pagos",TRUE)
    ->where("pay.token_pagos",$token_pagos)
    ->select(
      'disper_esp_trab.folio_pago_especie',
      'trab.folio_pers',
      'trab.post_folio_pers',
      'people.paterno',
      'people.materno',
      'people.nombre',
      'disper_esp_trab.monto',
      'disper_esp_trab.moneda',
    )
    ->get();
    //echo count($queryTrabDispersados);

    foreach ($queryTrabDispersados as $vTrabDisp) {
      $folio_pago_especie = 'DISPER-'.$JwtAuth->generarFolio($vTrabDisp->folio_pago_especie);
      $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vTrabDisp->folio_pers).(!is_null($vTrabDisp->post_folio_pers) ? '-'.$vTrabDisp->post_folio_pers : '');
      $nombre_completo = ucwords($JwtAuth->desencriptar($vTrabDisp->paterno)). " " .ucwords($JwtAuth->desencriptar($vTrabDisp->materno)). " " .ucwords($JwtAuth->desencriptar($vTrabDisp->nombre));

      $lista_actividad_movim[] = [
        "folio_pago_especie" => $folio_pago_especie,
        "nombre_completo" => "$folio_empleado $nombre_completo",
        "monto" => "$".number_format($vTrabDisp->monto,$JwtAuth->getMonedaAPI($vTrabDisp->moneda), '.', ',')." $vTrabDisp->moneda",
      ];
    }
    return $lista_actividad_movim;
	}

	private function movimientos_realizados_by_pago($token_pagos,$p_moneda,$JwtAuth){
    $lista_actividad_movim = [];
    $queryActivMovimDone = DB::table("fnzs_actividad_movimientos AS mov")
    ->join("fnzs_pagos_pago AS pay", "mov.pago", "=", "pay.id")
    ->where("pay.status_pagos",TRUE)
    ->where("pay.token_pagos",$token_pagos)
    ->get();
    //echo count($queryActivMovimDone);

    foreach ($queryActivMovimDone as $vActMov) {
      $token_movimiento = $vActMov->token_movimiento;
      $folio_movimiento = 'MOV-'.$JwtAuth->generarFolio($vActMov->folio_movimiento);
      $concepto_movimiento = !is_null($vActMov->concepto_movimiento) && $vActMov->concepto_movimiento != '' ? $JwtAuth->desencriptar($vActMov->concepto_movimiento) : '';
      $mov_f_cont = $vActMov->fecha_contabilizacion_movimiento;
      $fecha_movimiento = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('Y-m-d',$mov_f_cont) : '';
      $fecha_movimiento_excel = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('d/m/Y',$mov_f_cont) : '';
      $monto_applc = (float)$vActMov->monto_aplicado;

      $queryCaja = DB::table("fnzs_catalogos_caja AS caj")
      ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
      ->where('mov.token_movimiento',$token_movimiento)
      ->select('caj.token_caja','caj.no_caja','caj.alias_caja')
      ->first();

      $queryCuenta = DB::table("fnzs_catalogos_cuentas AS cuent")
      ->join("teci_bancos AS bank", "cuent.banco", "bank.id")
      ->join("fnzs_actividad_movimientos AS mov", "cuent.id", "mov.cuenta_bancaria")
      ->where('mov.token_movimiento',$token_movimiento)
      ->select('cuent.token_cuenta','cuent.folio_cuenta','cuent.cuenta')
      ->first();

      $queryMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
      ->join("fnzs_actividad_movimientos AS mov", "moned.id", "mov.cuenta_monedero")
      ->where('mov.token_movimiento',$token_movimiento)
      ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
      ->first();

      if ($queryCaja) {
        $movimiento_tipo = "caja";
        $movimiento_token = $queryCaja->token_caja;
        $movimiento_folio = "CAJ-" . $JwtAuth->generarFolio($queryCaja->no_caja);
        $movimiento_name = $JwtAuth->desencriptar($queryCaja->alias_caja);
      } elseif ($queryCuenta) {
        $movimiento_tipo = "banco";
        $movimiento_token = $queryCuenta->token_cuenta;
        $movimiento_folio = 'CUENT-'.$JwtAuth->generarFolio($queryCuenta->folio_cuenta);
        $cuenta_descifrada = $JwtAuth->decryptBankAccount($queryCuenta->cuenta);
        $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
        $movimiento_name = "**** **** **** $cuenta_descifrada_substr";
      } elseif ($queryMonedero) {
        $movimiento_tipo = "monedero";
        $movimiento_token = $queryMonedero->token_cuentamonedero;
        $movimiento_folio = "CUENTM-" . $JwtAuth->generarFolio($queryMonedero->folio_cuentmon);
        $movimiento_name = $queryMonedero->cuenta;
      } else {
        $movimiento_tipo = "N/A";
        $movimiento_token = "N/A";
        $movimiento_folio = "N/A";
        $movimiento_name = "N/A";
      }

      $lista_actividad_movim[] = [
        "token_movimiento" => $vActMov->token_movimiento,
        "folio_movimiento" => $folio_movimiento,
        "fecha_contabilizacion_movimiento" => date('Y-m-d', $vActMov->fecha_contabilizacion_movimiento),

        "concepto_movimiento" => $concepto_movimiento,
        "fecha_movimiento" => $fecha_movimiento,
        "fecha_movimiento_excel" => $fecha_movimiento_excel,
        "tipo_movimiento" => $vActMov->tipo_movimiento,
        //"cuenta_monedero" => $sql_cuenta_monedero,
        "movimiento_tipo" => $movimiento_tipo,
        "movimiento_token" => $movimiento_token,
        "movimiento_folio" => $movimiento_folio,
        "movimiento_name" => $movimiento_name,
        "monto_aplicado" => "$".number_format($monto_applc,$JwtAuth->getMonedaAPI($p_moneda),'.', ',')." $p_moneda",
      ];
    }
    return $lista_actividad_movim;
	}

  public function pagosDoneBYOrden($orden_de_pago,$JwtAuth){
    $lista_pagos_realizados = array();
    $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
    ->where([
      "vinc.vinculo_cancelado" => FALSE,
      "order.token_ordenPago" => $orden_de_pago
    ])->get();

    foreach ($queryPagosDone as $vPayDone) {
      $payment_observaciones = !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '';

      if (!is_null($vPayDone->vinc_proveedor)) {
        $destino = "proveedor";
      } elseif (!is_null($vPayDone->vinc_cliente)) {
        $destino = "cliente";
      } elseif (!is_null($vPayDone->vinc_empleado)) {
        $destino = "empleado";
      } elseif (!is_null($vPayDone->vinc_acreedor)) {
        $destino = "acreedor";
      } elseif (!is_null($vPayDone->vinc_deudor)) {
        $destino = "deudor";
      } elseif (!is_null($vPayDone->vinc_nomina)) {
        $destino = "nómina en efectivo";
      } elseif (!is_null($vPayDone->vinc_nomina_especie)) {
        $destino = "nómina en especie";
      } elseif (!is_null($vPayDone->impuesto_sobre_nomina)) {
        $destino = "impuestos sobre nómina";
      } elseif (!is_null($vPayDone->aportacion_seguridad_social)) {
        $destino = "aportaciones de seguridad social";
      } elseif (!is_null($vPayDone->declaracion_imp_federales)) {
        $destino = "declaraciones de impuestos federales";
      }

      $forma_pago_registrada = $vPayDone->forma_pago_pago;

      $queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
        ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
        ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
        ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
        ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->get();
      //echo count($queryOrvVincReembolsosPago);
      if (count($queryOrvVincReembolsosPago) > 0) {
        $forma_pago_registrada = $vPayDone->forma_pago_pago;
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
          ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $JwtAuth->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
      } else {
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $JwtAuth->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
      }

      //cliente
      $queryCliente = DB::table("fnzs_pagos_pago AS payment")
        ->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
        ->join("sos_personas AS people", "catclient.cliente", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('catclient.token_cat_clientes', 'catclient.folio', 'catclient.post_folio', 'people.nombre_extendido')
        ->first();
      $cliente_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
      $cliente_folio = $queryCliente ? ('CLI-' . $JwtAuth->generarFolio($queryCliente->folio) . (!is_null($queryCliente->post_folio) ? '-' . $queryCliente->post_folio : '')) : "";
      $cliente_name = $queryCliente ? $JwtAuth->desencriptar($queryCliente->nombre_extendido) : "";
      //empleado
      $queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $empleado_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
      $empleado_folio = $queryEmpleado ? "TRB-" . $JwtAuth->generarFolio($queryEmpleado->folio_pers) : "";
      $empleado_name = $queryEmpleado ? $JwtAuth->desencriptarNombres($queryEmpleado->paterno, $queryEmpleado->materno, $queryEmpleado->nombre) : "";
      //acreedor
      $queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
        //->join("sos_personas AS people", "acr.acreedor", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('acr.token_cat_acreedores', 'acr.acr_folio', 'acr.acr_post_folio', 'acr.acr_titular')
        ->first();
      $acreedor_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
      $acreedor_folio = $queryAcreedor ? ('ACREE-' . $JwtAuth->generarFolio($queryAcreedor->acr_folio) . (!is_null($queryAcreedor->acr_post_folio) ? '-' . $queryAcreedor->acr_post_folio : '')) : "";
      $acreedor_name = $queryAcreedor ? $JwtAuth->desencriptar($queryAcreedor->acr_titular) : "";

      //personal_pago
      $queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
      $p_paga_folio = $queryPersPaga ? "TRB-" . $JwtAuth->generarFolio($queryPersPaga->folio_pers) : "";
      $p_paga_paterno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->paterno)) : "";
      $p_paga_materno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->materno)) : "";
      $p_paga_nombre = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->nombre)) : "";
      $p_paga_name = $queryPersPaga ? "$p_paga_paterno $p_paga_materno $p_paga_nombre" : "";

      $queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
      $p_autoriza_folio = $queryPersAuth ? "TRB-" . $JwtAuth->generarFolio($queryPersAuth->folio_pers) : "";
      $p_autoriza_paterno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->paterno)) : "";
      $p_autoriza_materno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->materno)) : "";
      $p_autoriza_nombre = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->nombre)) : "";
      $p_autoriza_name = $queryPersAuth ? "$p_autoriza_paterno $p_autoriza_materno $p_autoriza_nombre" : "";

      $forma_pago_vinculada = "";
      $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("r_caj.*", "r_cuent.*", "r_moned.*")->get();
      //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();

      foreach ($queryFormasDePago as $vFPagoVinc) {
        if ($vFPagoVinc->token_caja !== null) {
          $forma_pago_vinculada = "Caja CAJ-" . $JwtAuth->generarFolio($vFPagoVinc->no_caja);
        } elseif ($vFPagoVinc->token_cuenta !== null) {
          $forma_pago_vinculada = "Banco CUENT-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
        } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
          $forma_pago_vinculada = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
        }
      }

      $cfdi_comprobante_metodo_de_pago = "";
      $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
        ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

      $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";
      $lista_actividad_movim = $this->movimientos_realizados_by_pago($vPayDone->token_pagos,$vPayDone->p_moneda,$JwtAuth);

      $row_pagos_realizados = array(
        "token_pagos" => $vPayDone->token_pagos,
        "folio_pagos" => "PAGO-" . $JwtAuth->generarFolio($vPayDone->folio_pagos),
        "status_pago" => $vPayDone->status_pagos ? true : false,
        "folio_operacion" => $vPayDone->folio_operacion,
        "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago),
        "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? date('Y-m-d', $vPayDone->fecha_contabilizacion) : "",
        "monto_pago" => "$" . number_format($vPayDone->monto_pago, $JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "observacionesPago" => $payment_observaciones,
        "tipo_cambio" => "$" . number_format($vPayDone->tipo_cambio, $JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "p_moneda" => $vPayDone->p_moneda,
        "destino" => $destino,
        "concepto" => !empty($vPayDone->concepto) ? $JwtAuth->desencriptar($vPayDone->concepto) : '',
        //forma_pago
        "forma_pago_vinculada" => $forma_pago_vinculada,
        "forma_pago_cfdi" => !is_null($forma_pago_registrada) && $forma_pago_registrada != '' ? $forma_pago_registrada . " - " . $JwtAuth->getFormasPagoAPI($forma_pago_registrada) : '',
        "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
        //proveedor
        "proveedor_token" => $proveedor_token != '' ? $proveedor_token : '',
        "proveedor_name" => $proveedor_folio != '' && $proveedor_name ? "$proveedor_folio - $proveedor_name" : '',
        //cliente
        "cliente_token" => $cliente_token != '' ? $cliente_token : '',
        "cliente_name" => $cliente_folio != '' && $cliente_folio != '' ? "$cliente_folio - $cliente_name" : '',
        //empleado
        "empleado_token" => $empleado_token != '' ? $empleado_token : '',
        "empleado_name" => $empleado_folio != '' && $acreedor_name != '' ? "$empleado_folio - $empleado_name" : '',
        //acreedor
        "acreedor_token" => $acreedor_token != '' ? $acreedor_token : '',
        "acreedor_name" => $acreedor_folio != '' && $acreedor_name != '' ? "PXT $acreedor_folio - $acreedor_name" : '',
        //personal_pago
        "personal_pago_token" => $p_paga_token,
        "personal_pago_folio" => $p_paga_folio,
        "personal_pago_name" => $p_paga_name,
        "pago_autorizado" => $vPayDone->pago_autorizado ? true : false,
        "fecha_pago_auth" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago_auth),
        //personal_autoriza
        "personal_autoriza_token" => $p_autoriza_token,
        "personal_autoriza_folio" => $p_autoriza_folio,
        "personal_autoriza_name" => $p_autoriza_name,
        "lista_actividad_movim" => $lista_actividad_movim,
      );
      $lista_pagos_realizados[] = $row_pagos_realizados;
    }

    return $lista_pagos_realizados;
  }

  public function pagosDoneBYDispersionEfectivoOrden($orden_de_pago,$JwtAuth){
    $lista_pagos_realizados = array();
    $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
    ->where([
      "vinc.vinculo_cancelado" => FALSE,
      "order.token_ordenPago" => $orden_de_pago
    ])->get();

    foreach ($queryPagosDone as $vPayDone) {
      $payment_observaciones = !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '';

      if (!is_null($vPayDone->vinc_proveedor)) {
        $destino = "proveedor";
      } elseif (!is_null($vPayDone->vinc_cliente)) {
        $destino = "cliente";
      } elseif (!is_null($vPayDone->vinc_empleado)) {
        $destino = "empleado";
      } elseif (!is_null($vPayDone->vinc_acreedor)) {
        $destino = "acreedor";
      } elseif (!is_null($vPayDone->vinc_deudor)) {
        $destino = "deudor";
      } elseif (!is_null($vPayDone->vinc_nomina)) {
        $destino = "nómina en efectivo";
      } elseif (!is_null($vPayDone->vinc_nomina_especie)) {
        $destino = "nómina en especie";
      } elseif (!is_null($vPayDone->impuesto_sobre_nomina)) {
        $destino = "impuestos sobre nómina";
      } elseif (!is_null($vPayDone->aportacion_seguridad_social)) {
        $destino = "aportaciones de seguridad social";
      } elseif (!is_null($vPayDone->declaracion_imp_federales)) {
        $destino = "declaraciones de impuestos federales";
      }

      $forma_pago_registrada = $vPayDone->forma_pago_pago;

      $queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
        ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
        ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
        ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
        ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->get();
      //echo count($queryOrvVincReembolsosPago);
      if (count($queryOrvVincReembolsosPago) > 0) {
        $forma_pago_registrada = $vPayDone->forma_pago_pago;
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
          ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $JwtAuth->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
      } else {
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $JwtAuth->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
      }

      //cliente
      $queryCliente = DB::table("fnzs_pagos_pago AS payment")
        ->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
        ->join("sos_personas AS people", "catclient.cliente", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('catclient.token_cat_clientes', 'catclient.folio', 'catclient.post_folio', 'people.nombre_extendido')
        ->first();
      $cliente_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
      $cliente_folio = $queryCliente ? ('CLI-' . $JwtAuth->generarFolio($queryCliente->folio) . (!is_null($queryCliente->post_folio) ? '-' . $queryCliente->post_folio : '')) : "";
      $cliente_name = $queryCliente ? $JwtAuth->desencriptar($queryCliente->nombre_extendido) : "";
      //empleado
      $queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $empleado_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
      $empleado_folio = $queryEmpleado ? "TRB-" . $JwtAuth->generarFolio($queryEmpleado->folio_pers) : "";
      $empleado_name = $queryEmpleado ? $JwtAuth->desencriptarNombres($queryEmpleado->paterno, $queryEmpleado->materno, $queryEmpleado->nombre) : "";
      //acreedor
      $queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
        //->join("sos_personas AS people", "acr.acreedor", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('acr.token_cat_acreedores', 'acr.acr_folio', 'acr.acr_post_folio', 'acr.acr_titular')
        ->first();
      $acreedor_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
      $acreedor_folio = $queryAcreedor ? ('ACREE-' . $JwtAuth->generarFolio($queryAcreedor->acr_folio) . (!is_null($queryAcreedor->acr_post_folio) ? '-' . $queryAcreedor->acr_post_folio : '')) : "";
      $acreedor_name = $queryAcreedor ? $JwtAuth->desencriptar($queryAcreedor->acr_titular) : "";

      //personal_pago
      $queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
      $p_paga_folio = $queryPersPaga ? "TRB-" . $JwtAuth->generarFolio($queryPersPaga->folio_pers) : "";
      $p_paga_paterno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->paterno)) : "";
      $p_paga_materno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->materno)) : "";
      $p_paga_nombre = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->nombre)) : "";
      $p_paga_name = $queryPersPaga ? "$p_paga_paterno $p_paga_materno $p_paga_nombre" : "";

      $queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
      $p_autoriza_folio = $queryPersAuth ? "TRB-" . $JwtAuth->generarFolio($queryPersAuth->folio_pers) : "";
      $p_autoriza_paterno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->paterno)) : "";
      $p_autoriza_materno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->materno)) : "";
      $p_autoriza_nombre = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->nombre)) : "";
      $p_autoriza_name = $queryPersAuth ? "$p_autoriza_paterno $p_autoriza_materno $p_autoriza_nombre" : "";

      $forma_pago_vinculada = "";
      $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("r_caj.*", "r_cuent.*", "r_moned.*")->get();
      //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();

      foreach ($queryFormasDePago as $vFPagoVinc) {
        if ($vFPagoVinc->token_caja !== null) {
          $forma_pago_vinculada = "Caja CAJ-" . $JwtAuth->generarFolio($vFPagoVinc->no_caja);
        } elseif ($vFPagoVinc->token_cuenta !== null) {
          $forma_pago_vinculada = "Banco CUENT-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
        } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
          $forma_pago_vinculada = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
        }
      }

      $cfdi_comprobante_metodo_de_pago = "";
      $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
        ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

      $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";
      $lista_actividad_movim = $this->movimientos_realizados_by_pago($vPayDone->token_pagos,$vPayDone->p_moneda,$JwtAuth);
      $lista_trabajadores_dispersados = $this->trabajadores_dispersados_by_pago($vPayDone->token_pagos,$JwtAuth);

      $row_pagos_realizados = array(
        "token_pagos" => $vPayDone->token_pagos,
        "folio_pagos" => "PAGO-" . $JwtAuth->generarFolio($vPayDone->folio_pagos),
        "status_pago" => $vPayDone->status_pagos ? true : false,
        "folio_operacion" => $vPayDone->folio_operacion,
        "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago),
        "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? date('Y-m-d', $vPayDone->fecha_contabilizacion) : "",
        "monto_pago" => "$" . number_format($vPayDone->monto_pago, $JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "observacionesPago" => $payment_observaciones,
        "tipo_cambio" => "$" . number_format($vPayDone->tipo_cambio, $JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "p_moneda" => $vPayDone->p_moneda,
        "destino" => $destino,
        "concepto" => !empty($vPayDone->concepto) ? $JwtAuth->desencriptar($vPayDone->concepto) : '',
        //forma_pago
        "forma_pago_vinculada" => $forma_pago_vinculada,
        "forma_pago_cfdi" => !is_null($forma_pago_registrada) && $forma_pago_registrada != '' ? $forma_pago_registrada . " - " . $JwtAuth->getFormasPagoAPI($forma_pago_registrada) : '',
        "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
        //proveedor
        "proveedor_token" => $proveedor_token != '' ? $proveedor_token : '',
        "proveedor_name" => $proveedor_folio != '' && $proveedor_name ? "$proveedor_folio - $proveedor_name" : '',
        //cliente
        "cliente_token" => $cliente_token != '' ? $cliente_token : '',
        "cliente_name" => $cliente_folio != '' && $cliente_folio != '' ? "$cliente_folio - $cliente_name" : '',
        //empleado
        "empleado_token" => $empleado_token != '' ? $empleado_token : '',
        "empleado_name" => $empleado_folio != '' && $acreedor_name != '' ? "$empleado_folio - $empleado_name" : '',
        //acreedor
        "acreedor_token" => $acreedor_token != '' ? $acreedor_token : '',
        "acreedor_name" => $acreedor_folio != '' && $acreedor_name != '' ? "PXT $acreedor_folio - $acreedor_name" : '',
        //personal_pago
        "personal_pago_token" => $p_paga_token,
        "personal_pago_folio" => $p_paga_folio,
        "personal_pago_name" => $p_paga_name,
        "pago_autorizado" => $vPayDone->pago_autorizado ? true : false,
        "fecha_pago_auth" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago_auth),
        //personal_autoriza
        "personal_autoriza_token" => $p_autoriza_token,
        "personal_autoriza_folio" => $p_autoriza_folio,
        "personal_autoriza_name" => $p_autoriza_name,
        "mostrarTipo" => "",
        "lista_actividad_movim" => $lista_actividad_movim,
        "lista_trabajadores_dispersados" => $lista_trabajadores_dispersados,
      );
      $lista_pagos_realizados[] = $row_pagos_realizados;
    }

    return $lista_pagos_realizados;
  }

  public function pagosDoneBYDispersionEspecieOrden($orden_de_pago,$JwtAuth){
    $lista_pagos_realizados = array();
    $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
    ->where([
      "vinc.vinculo_cancelado" => FALSE,
      "order.token_ordenPago" => $orden_de_pago
    ])->get();

    foreach ($queryPagosDone as $vPayDone) {
      $payment_observaciones = !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '';

      if (!is_null($vPayDone->vinc_proveedor)) {
        $destino = "proveedor";
      } elseif (!is_null($vPayDone->vinc_cliente)) {
        $destino = "cliente";
      } elseif (!is_null($vPayDone->vinc_empleado)) {
        $destino = "empleado";
      } elseif (!is_null($vPayDone->vinc_acreedor)) {
        $destino = "acreedor";
      } elseif (!is_null($vPayDone->vinc_deudor)) {
        $destino = "deudor";
      } elseif (!is_null($vPayDone->vinc_nomina)) {
        $destino = "nómina en efectivo";
      } elseif (!is_null($vPayDone->vinc_nomina_especie)) {
        $destino = "nómina en especie";
      } elseif (!is_null($vPayDone->impuesto_sobre_nomina)) {
        $destino = "impuestos sobre nómina";
      } elseif (!is_null($vPayDone->aportacion_seguridad_social)) {
        $destino = "aportaciones de seguridad social";
      } elseif (!is_null($vPayDone->declaracion_imp_federales)) {
        $destino = "declaraciones de impuestos federales";
      }

      $forma_pago_registrada = $vPayDone->forma_pago_pago;

      $queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
        ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
        ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
        ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
        ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->get();
      //echo count($queryOrvVincReembolsosPago);
      if (count($queryOrvVincReembolsosPago) > 0) {
        $forma_pago_registrada = $vPayDone->forma_pago_pago;
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
          ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $JwtAuth->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
      } else {
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $JwtAuth->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
      }

      //cliente
      $queryCliente = DB::table("fnzs_pagos_pago AS payment")
        ->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
        ->join("sos_personas AS people", "catclient.cliente", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('catclient.token_cat_clientes', 'catclient.folio', 'catclient.post_folio', 'people.nombre_extendido')
        ->first();
      $cliente_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
      $cliente_folio = $queryCliente ? ('CLI-' . $JwtAuth->generarFolio($queryCliente->folio) . (!is_null($queryCliente->post_folio) ? '-' . $queryCliente->post_folio : '')) : "";
      $cliente_name = $queryCliente ? $JwtAuth->desencriptar($queryCliente->nombre_extendido) : "";
      //empleado
      $queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $empleado_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
      $empleado_folio = $queryEmpleado ? "TRB-" . $JwtAuth->generarFolio($queryEmpleado->folio_pers) : "";
      $empleado_name = $queryEmpleado ? $JwtAuth->desencriptarNombres($queryEmpleado->paterno, $queryEmpleado->materno, $queryEmpleado->nombre) : "";
      //acreedor
      $queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
        //->join("sos_personas AS people", "acr.acreedor", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('acr.token_cat_acreedores', 'acr.acr_folio', 'acr.acr_post_folio', 'acr.acr_titular')
        ->first();
      $acreedor_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
      $acreedor_folio = $queryAcreedor ? ('ACREE-' . $JwtAuth->generarFolio($queryAcreedor->acr_folio) . (!is_null($queryAcreedor->acr_post_folio) ? '-' . $queryAcreedor->acr_post_folio : '')) : "";
      $acreedor_name = $queryAcreedor ? $JwtAuth->desencriptar($queryAcreedor->acr_titular) : "";

      //personal_pago
      $queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
      $p_paga_folio = $queryPersPaga ? "TRB-" . $JwtAuth->generarFolio($queryPersPaga->folio_pers) : "";
      $p_paga_paterno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->paterno)) : "";
      $p_paga_materno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->materno)) : "";
      $p_paga_nombre = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->nombre)) : "";
      $p_paga_name = $queryPersPaga ? "$p_paga_paterno $p_paga_materno $p_paga_nombre" : "";

      $queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
      $p_autoriza_folio = $queryPersAuth ? "TRB-" . $JwtAuth->generarFolio($queryPersAuth->folio_pers) : "";
      $p_autoriza_paterno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->paterno)) : "";
      $p_autoriza_materno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->materno)) : "";
      $p_autoriza_nombre = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->nombre)) : "";
      $p_autoriza_name = $queryPersAuth ? "$p_autoriza_paterno $p_autoriza_materno $p_autoriza_nombre" : "";

      $forma_pago_vinculada = "";
      $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("r_caj.*", "r_cuent.*", "r_moned.*")->get();
      //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();

      foreach ($queryFormasDePago as $vFPagoVinc) {
        if ($vFPagoVinc->token_caja !== null) {
          $forma_pago_vinculada = "Caja CAJ-" . $JwtAuth->generarFolio($vFPagoVinc->no_caja);
        } elseif ($vFPagoVinc->token_cuenta !== null) {
          $forma_pago_vinculada = "Banco CUENT-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
        } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
          $forma_pago_vinculada = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
        }
      }

      $cfdi_comprobante_metodo_de_pago = "";
      $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
        ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

      $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";
      $lista_actividad_movim = $this->movimientos_realizados_by_pago($vPayDone->token_pagos,$vPayDone->p_moneda,$JwtAuth);
      $lista_trabajadores_dispersados = $this->trabajadores_dispersados_especie_by_pago($vPayDone->token_pagos,$JwtAuth);

      $row_pagos_realizados = array(
        "token_pagos" => $vPayDone->token_pagos,
        "folio_pagos" => "PAGO-" . $JwtAuth->generarFolio($vPayDone->folio_pagos),
        "status_pago" => $vPayDone->status_pagos ? true : false,
        "folio_operacion" => $vPayDone->folio_operacion,
        "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago),
        "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? date('Y-m-d', $vPayDone->fecha_contabilizacion) : "",
        "monto_pago" => "$" . number_format($vPayDone->monto_pago, $JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "observacionesPago" => $payment_observaciones,
        "tipo_cambio" => "$" . number_format($vPayDone->tipo_cambio, $JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "p_moneda" => $vPayDone->p_moneda,
        "destino" => $destino,
        "concepto" => !empty($vPayDone->concepto) ? $JwtAuth->desencriptar($vPayDone->concepto) : '',
        //forma_pago
        "forma_pago_vinculada" => $forma_pago_vinculada,
        "forma_pago_cfdi" => !is_null($forma_pago_registrada) && $forma_pago_registrada != '' ? $forma_pago_registrada . " - " . $JwtAuth->getFormasPagoAPI($forma_pago_registrada) : '',
        "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
        //proveedor
        "proveedor_token" => $proveedor_token != '' ? $proveedor_token : '',
        "proveedor_name" => $proveedor_folio != '' && $proveedor_name ? "$proveedor_folio - $proveedor_name" : '',
        //cliente
        "cliente_token" => $cliente_token != '' ? $cliente_token : '',
        "cliente_name" => $cliente_folio != '' && $cliente_folio != '' ? "$cliente_folio - $cliente_name" : '',
        //empleado
        "empleado_token" => $empleado_token != '' ? $empleado_token : '',
        "empleado_name" => $empleado_folio != '' && $acreedor_name != '' ? "$empleado_folio - $empleado_name" : '',
        //acreedor
        "acreedor_token" => $acreedor_token != '' ? $acreedor_token : '',
        "acreedor_name" => $acreedor_folio != '' && $acreedor_name != '' ? "PXT $acreedor_folio - $acreedor_name" : '',
        //personal_pago
        "personal_pago_token" => $p_paga_token,
        "personal_pago_folio" => $p_paga_folio,
        "personal_pago_name" => $p_paga_name,
        "pago_autorizado" => $vPayDone->pago_autorizado ? true : false,
        "fecha_pago_auth" => gmdate('Y-m-d H:i:s', $vPayDone->fecha_pago_auth),
        //personal_autoriza
        "personal_autoriza_token" => $p_autoriza_token,
        "personal_autoriza_folio" => $p_autoriza_folio,
        "personal_autoriza_name" => $p_autoriza_name,
        "mostrarTipo" => "",
        "lista_actividad_movim" => $lista_actividad_movim,
        "lista_trabajadores_dispersados" => $lista_trabajadores_dispersados,
      );
      $lista_pagos_realizados[] = $row_pagos_realizados;
    }

    return $lista_pagos_realizados;
  }

  public function muestraCantidadesConMoneda($orden_importe,$orden_moneda_code,$orden_moneda_decimales){
		return $orden_importe > 0 && $orden_moneda_code != '---' ? "$".number_format($orden_importe, $orden_moneda_decimales, '.', ',')." $orden_moneda_code" : '$0.00 MXN';
	}

	public function eachGeneralOrdenesPago($listOrdenes,$empresa,$usuario,$JwtAuth){
		//factura_compra
    $idCompras = $listOrdenes->pluck('factura_compra')->filter()->unique()->toArray();
    $comprasMap = DB::table('eegr_compras')->whereIn('id', $idCompras)->get()->keyBy('id');
    
    $compraProveedorMap = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
    ->whereIn('catprov.id', $comprasMap->pluck('proveedor')->unique())
    ->select('catprov.*', 'people.nombre_extendido', 'people.nombre_com')
    ->get()->keyBy('id');
    
    $compraCompradorEmpMap = DB::table("main_empresas AS emp")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->whereIn('emp.id', $comprasMap->pluck('comprador')->unique())
    ->get()->keyBy('id');
    
    $detalleCompraMap = DB::table("eegr_compras_detalle AS detcomp")
    ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
    ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereIn('comp.token_compras', $comprasMap->pluck('token_compras')->unique())
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->select(
      'comp.token_compras AS id_compras',
      'detcomp.precio_unitario','detcomp.cantidad','detcomp.descuento','detcomp.traslados_total','detcomp.retenciones_total',
      'detcomp.tipo_de_cambio_detalle_compra'
    )
    ->get()->groupBy('id_compras');

		//factura_venta
    $idVentas = $listOrdenes->pluck('factura_venta')->filter()->unique()->toArray();
    $ventasMap = DB::table("ingr_ventas")->whereIn('id', $idVentas)->get()->keyBy('id');

    $ventasVendedorEmpMap = DB::table("main_empresas AS emp")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->whereIn('emp.id', $ventasMap->pluck('vendedor')->unique())
    ->get()->keyBy('id');

    $ventasVendedorPersMap = DB::table("vhum_empleados_catalogo AS vhum_pers")
    ->join("sos_personas AS people", "vhum_pers.empleado_name", "=", "people.id")
    ->whereIn('vhum_pers.id', $ventasMap->pluck('user_vendedor')->unique())
    ->get()->keyBy('id');

    //reembolso_main
    $idReembolsoMain = $listOrdenes->pluck('reembolso_main')->filter()->unique()->toArray();
    $reembolsosMap = DB::table("terc_reembolso_main AS reem_main")
    ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
    //->join("terc_reembolso_solicitud AS reem_soli","order.reembolso_solicitud","=","reem_soli.id")
    ->where("emp.empresa_token",$empresa)
    ->whereIn('reem_main.id', function ($query) {
      $query->select('reembolso_main')->from('terc_reembolso_solicitud');
    })
		->whereIn('reem_main.id', $idReembolsoMain)
		->get()->keyBy('id');

    $reembolsoEmisorEmpMap = DB::table("main_empresas AS emp")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->whereIn('emp.id', $reembolsosMap->pluck('emisor')->unique())
    ->get()->keyBy('id');

    $reembolsoEmisorPersMap = DB::table("fnzs_catalogo_acreedores")->whereIn('id', $reembolsosMap->pluck('user_acreedor')->unique())->get()->keyBy('id');

    $reembolsoSoliMap = DB::table("terc_reembolso_solicitud AS reem_soli")
    ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
    ->whereIn('reem_soli.reembolso_main', $idReembolsoMain)
		->orderBy('reem_soli.folio_solicitud', 'DESC')
    ->select(
      'reem_soli.reembolso_main AS id_reem_main',
      'reem_soli.moneda_entrante','reem_soli.importe_entrante'
    )
    ->get()->groupBy('id_reem_main');

    $reembolsoSoliAuthMap = DB::table("terc_reembolso_solicitud AS reem_soli")
    ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
    ->where("reem_soli.autorizacion_egr","A")
    ->whereIn('reem_soli.reembolso_main', $idReembolsoMain)
		->orderBy('reem_soli.folio_solicitud', 'DESC')
    ->select(
      'reem_soli.reembolso_main AS id_reem_main',
      'reem_soli.moneda_entrante','reem_soli.importe_entrante','reem_soli.tipo_cambio'
    )
    ->get()->groupBy('id_reem_main');

    //anticipo_proveedor
    $idAnticipoProveedor = $listOrdenes->pluck('anticipo_proveedor')->filter()->unique()->toArray();
    $anticiposMap = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
    ->join("main_empresas AS emp", "ant.empresa", "=", "emp.id")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->where("emp.empresa_token",$empresa)
		->whereIn('ant.uuid_anticipo', $idAnticipoProveedor)
		->get()->keyBy('id');

    $anticiposProveedorMap = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
    ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "=", "catprov.id")
    ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
    ->whereIn('ant.uuid_anticipo', $anticiposMap->pluck('uuid_anticipo')->unique())
    ->get()->keyBy('id');

    //$rOrdPag->ord_anticipo
    $idAnticipoORD = $listOrdenes->pluck('ord_anticipo')->filter()->unique()->toArray();
    $anticiposORDMap = DB::table("eegr_catalogo_proveedores_anticipo")->whereIn('uuid_anticipo', $idAnticipoORD)->get()->keyBy('uuid_anticipo');

    $anticiposORDEmpMap = DB::table("main_empresas AS emp")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->whereIn("emp.id", $anticiposORDMap->pluck('empresa')->unique())
    ->get()->keyBy('id');

    $anticiposORDDeudorMap = DB::table("fnzs_catalogo_deudores")
    ->whereIn("id", $anticiposORDMap->pluck('ant_deudor_vinculado')->unique())
    ->get()->keyBy('id');

    //$rOrdPag->nomina_main $rOrdPag->nomina_en_especie
    $idNominaEspecie = $listOrdenes->pluck('nomina_en_especie')->filter()->unique()->toArray();
    $nominaEspecieMap = DB::table("vhum_nominas_especie")
		->whereIn('id', function ($query) {
			$query->select('nomina_especie')->from('vhum_nominas_especie_desglose');
		})
		->whereIn('id', $idNominaEspecie)->get()->keyBy('id');

    $empEnviaEspecieNominaMap = DB::table("main_empresas AS emp")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->whereIn("emp.id", $nominaEspecieMap->pluck('nomina_esp_empresa')->unique())
		->get()->keyBy('id');

    $detailEspNominaMap = DB::table("vhum_nominas_especie_desglose AS desg_esp")
    ->join("vhum_nominas_especie AS nomi_esp", "desg_esp.nomina_especie", "=", "nomi_esp.id")
    ->whereIn("nomi_esp.token_nominas_especie", $nominaEspecieMap->pluck('token_nominas_especie')->unique())
    ->select(
      'nomi_esp.token_nominas_especie AS esp_tkn',
      'desg_esp.nomina_esp_moneda',
      'desg_esp.total_en_especie'
    )
    ->get()->groupBy('esp_tkn');

    //$rOrdPag->nomina_main
    $idNominaMain = $listOrdenes->pluck('nomina_main')->filter()->unique()->toArray();
		$nominaMainMap = DB::table("vhum_nominas_main")
		->whereIn('id', function ($query) {
			$query->select('nomina_main')->from('vhum_nominas_recibos');
		})
		->whereIn('id', $idNominaMain)->get()->keyBy('id');
		
		$empEnviaNominaMainMap = DB::table("main_empresas AS emp")
		->join("sos_personas AS people", "emp.persona", "=", "people.id")
		->whereIn("emp.id", $nominaMainMap->pluck('nomina_empresa')->unique())
		->get()->keyBy('id');
		
		$detalleNominaListaMap = DB::table("vhum_nominas_recibos AS recibos")
    ->join("vhum_nominas_main AS nomi_main", "recibos.nomina_main", "=", "nomi_main.id")
    ->whereIn("nomi_main.token_nominas_periodos", $nominaMainMap->pluck('token_nominas_periodos')->unique())
    ->select(
      'nomi_main.token_nominas_periodos AS nomi_tkn',
      'recibos.nomina_moneda',
      'recibos.total_efectivo'
    )
    ->get()->groupBy('nomi_tkn');

    //$rOrdPag->impuesto_sobre_nomina
    $idISNomina = $listOrdenes->pluck('impuesto_sobre_nomina')->filter()->unique()->toArray();
		$isNominaMap = DB::table("vhum_nominas_impuestos")->whereIn('id', $idISNomina)->get()->keyBy('id');
		
		$isNominaEstadoMap = DB::table("fnzs_catalogos_fed_estados_municipios")
		->whereIn("id", $isNominaMap->pluck('nomi_imp_estado')->unique())
		->get()->keyBy('id');
		
		$isNominaEmpMap = DB::table("main_empresas AS emp")
		->join("sos_personas AS people", "emp.persona", "=", "people.id")
		->whereIn("emp.id", $isNominaMap->pluck('nomina_empresa')->unique())
		->get()->keyBy('id');

    //$rOrdPag->aportacion_seguridad_social
    $idAportacionesSSOCIAL = $listOrdenes->pluck('aportacion_seguridad_social')->filter()->unique()->toArray();
    $aportSSocialMap = DB::table("vhum_aportaciones_seguridad_social_main")->whereIn("id", $idAportacionesSSOCIAL)->get()->keyBy('id');
		
		$ssocialEstMuniMap = DB::table("fnzs_catalogos_fed_estados_municipios")
		->whereIn("id", $aportSSocialMap->pluck('proveedor_imss')->unique())
		->get()->keyBy('id');
		
		$ssocialEmpMap = DB::table("main_empresas AS emp")
		->join("sos_personas AS people", "emp.persona", "=", "people.id")
		->whereIn("emp.id", $aportSSocialMap->pluck('aport_ssocial_empresa')->unique())
		->get()->keyBy('id');

    //$rOrdPag->declaracion_imp_federales
    $idDeclaImpFed = $listOrdenes->pluck('declaracion_imp_federales')->filter()->unique()->toArray();
    $declaracionesImpFederalesMap = DB::table("cont_reg_fisc_declaraciones_imp_federales")->whereIn("id", $idDeclaImpFed)->get()->keyBy('id');

    $decFedEstMuniMap = DB::table("fnzs_catalogos_fed_estados_municipios")
		->whereIn("id", $declaracionesImpFederalesMap->pluck('proveedor_sat')->unique())
		->get()->keyBy('id');

    $decFedEmpMap = DB::table("main_empresas AS emp")
    ->join("sos_personas AS people", "emp.persona", "=", "people.id")
		->whereIn("emp.id", $declaracionesImpFederalesMap->pluck('declaracion_empresa')->unique())
		->get()->keyBy('id');

    //$asimilados_reporte
    $idAsimiladosMain = $listOrdenes->pluck('asimilados_reporte')->filter()->unique()->toArray();
		$asimiladosMainMap = DB::table("vhum_reporte_asimilados_main")
		->whereIn('id', function ($query) {
			$query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
		})
		->whereIn('id', $idAsimiladosMain)->get()->keyBy('id');

    $asimiladosReceptorMap = DB::table("sos_personas AS prov")
    ->join("eegr_catalogo_proveedores AS catprov", "prov.id", "catprov.proveedor")
    ->join("vhum_reporte_asimilados_desglose AS asim_desg", "catprov.id", "asim_desg.desglose_asim_receptor")
    ->join("vhum_reporte_asimilados_main AS asim_main", "asim_desg.asim_reporte", "=", "asim_main.id")
		->whereIn('asim_main.token_reporte_asim', $asimiladosMainMap->pluck('token_reporte_asim')->unique())
    ->select(
      'catprov.*', 
      'prov.nombre_extendido',
      'prov.nombre_com',
      'asim_main.token_reporte_asim'
    )
    ->get()->keyBy('token_reporte_asim');

		$asimEmpMap = DB::table("main_empresas AS emp")
		->join("sos_personas AS people", "emp.persona", "=", "people.id")
		->whereIn("emp.id", $asimiladosMainMap->pluck('asim_empresa')->unique())
		->get()->keyBy('id');

		$asimiladosTotalMap = DB::table("cfdi_comprobantes_fiscales AS cfd_info")
    ->join("cfdi_vinculacion_asimilados_reporte AS vinc_asim", "cfd_info.id", "=", "vinc_asim.comprobante_fiscal")
    ->join("vhum_reporte_asimilados_main AS asim_main", "vinc_asim.asimilados_reporte_vinculado", "=", "asim_main.id")
		->whereIn('asim_main.token_reporte_asim', $asimiladosMainMap->pluck('token_reporte_asim')->unique())
    ->select(
      'cfd_info.id AS cfdi_id', 
      'cfd_info.cfdi_comprobante_total', // O idealmente, solo los campos que necesites
      'asim_main.token_reporte_asim'
    )
    ->get()->keyBy('token_reporte_asim');
		
		$detalleAsimiladosListaMap = DB::table("vhum_reporte_asimilados_desglose AS desg")
    ->join("vhum_reporte_asimilados_main AS asim_main", "desg.asim_reporte", "=", "asim_main.id")
    ->whereIn("asim_main.token_reporte_asim", $nominaMainMap->pluck('token_reporte_asim')->unique())
    ->select(
      'asim_main.token_reporte_asim AS asim_tkn',
      'desg.desglose_asim_moneda',
      'desg.total_deducciones',
      'desg.total_percepciones'
    )
    ->get()->groupBy('nomi_tkn');

    $idCancela = $listOrdenes->pluck('pago_orden_cancel_user')->filter()->unique()->toArray();
    $UsuarioCancelaMap = DB::table("teci_usuarios_catalogo AS users")
    ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
    ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
    ->whereIn("users.id",$idCancela)
    ->select(
      'users.id AS auth_user',
      'people.paterno',
      'people.materno',
      'people.nombre'
    )
    ->get()->keyBy('auth_user');

		$ordenes_pago = array();
    $id_list = 1;
    foreach ($listOrdenes as $rOrdPag) {
      //da_te_default_timezone_set($rOrdPag->zona_horaria);
      $fecha_contabilizacion_doc_anterior = "";
      $autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
      $fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
      $status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

      $factura_relacionada_typo = "---";
      $factura_relacionada_link = "";
      $factura_relacionada_token = "---";
      $factura_relacionada_string = "---";

      $orden_emisor_emp = "---";

      $orden_emisor_personal_token = "";
      $orden_emisor_personal_folio = "";
      $orden_emisor_personal_nombre = "";
      $orden_emisor_personal_nombre_comercial = "";

      $importe_total_anticipo = 0;
      $importe_total_inicial = 0;
      $orden_moneda_inicial_name = "---";
      $orden_moneda_inicial_decimales = 0;

      $importe_autorizado_inicial = 0;
      $orden_moneda_autorizado_inicial_tkn = "---";
      $orden_moneda_autorizado_inicial_name = "---";
      $orden_moneda_autorizado_inicial_decimales = 0;

      $importe_autorizado_final = 0;
      $orden_moneda_autorizado_final_name = "---";
      $orden_moneda_autorizado_final_decimales = 0;

      $mostrar_partida = false;
      if (!is_null($rOrdPag->factura_compra)) {
        $factura_relacionada_typo = "compras";
        $oBuy = $comprasMap->get($rOrdPag->factura_compra);
        $mostrar_partida = $oBuy ? true : false;
        if ($oBuy) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oBuy->fecha_contabilizacion);
          $vpComp = $compraProveedorMap->get($oBuy->proveedor);
  
          if ($vpComp) {
            $orden_emisor_personal_token = $vpComp->token_cat_proveedores;
            $orden_emisor_personal_folio = 'PRV-'.$JwtAuth->generarFolio($vpComp->folio) . ($vpComp->post_folio != NULL ? '-'.$vpComp->post_folio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vpComp->nombre_extendido);
            $orden_emisor_personal_nombre_comercial = !is_null($vpComp->nombre_com) ? $JwtAuth->desencriptar($vpComp->nombre_com) : '';
          }
  
          $orden_moneda_inicial_name = $oBuy->moneda;
          $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);
          $orden_moneda_autorizado_inicial_name = $oBuy->moneda;
          $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);
          $orden_moneda_autorizado_final_name = $oBuy->moneda;
          $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);
  
          $factura_relacionada_token = $oBuy->token_compras;
          $factura_relacionada_string = "COMP-" . $JwtAuth->generarFolio($oBuy->folio_compra);
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/compras_pdf/".$factura_relacionada_token;
  
          $vpComp = $compraCompradorEmpMap->get($oBuy->comprador);
          if ($vpComp) {
            $orden_emisor_emp = $vpComp->abrev_nombre;
          }
  
          $detalleCompraLista = $detalleCompraMap->get($oBuy->token_compras) ?? collect([]);
          //var_dump($detalleCompraLista);
          foreach ($detalleCompraLista as $vDetBuy) {
            $subtotal_simple = floatval($vDetBuy->precio_unitario) * $vDetBuy->cantidad;
            
            $importe_concepto_simple = $subtotal_simple - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
            $importe_total_inicial += $importe_concepto_simple;
            $importe_autorizado_inicial += $importe_concepto_simple;
  
            $subtotal_convert = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
            $importe_concepto_convert = $subtotal_convert - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
            $importe_autorizado_final += $importe_concepto_convert;
  
            //$totalDetComp = number_format($subtotal,$moneda_decimales,'.', ',');
            //$totalDetCompFormat = number_format($subtotal,$moneda_decimales,'.', ',');
            //$format_precio_unitario = number_format($vDetBuy->precio_unitario,$moneda_decimales,'.', ',');
            //$format_descuento = number_format($vDetBuy->descuento,$moneda_decimales,'.', ',');
            //$format_retenciones = number_format($vDetBuy->retenciones_total,$moneda_decimales,'.', ',');
            //$format_traslados = number_format($vDetBuy->traslados_total,$moneda_decimales,'.', ',');
          }

          $importe_total_anticipo += $oBuy->anticipo;
          $importe_total_inicial -= $oBuy->anticipo;
          $importe_autorizado_inicial -= $oBuy->anticipo;
          $importe_autorizado_final -= $oBuy->anticipo;
        }
      }

      if (!is_null($rOrdPag->factura_venta)) {
        $factura_relacionada_typo = "ventas";
				$oSell = $ventasMap->get($rOrdPag->factura_venta);
				$mostrar_partida = $oSell ? true : false;

        if ($oSell) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oSell->fecha_contabilizacion);
          $factura_relacionada_token = $oSell->token_ventas;
          $factura_relacionada_string = "VENT-" . $JwtAuth->generarFolio($oSell->numero_venta);
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/ventas_pdf/".$factura_relacionada_token;

					$empVend = $ventasVendedorEmpMap->get($oSell->vendedor);
          if ($empVend) {
            $orden_emisor_emp = $empVend->abrev_nombre;
          }

					$persVend = $ventasVendedorPersMap->get($oSell->vendedor);
          if ($persVend) {
            $orden_emisor_personal_nombre = $JwtAuth->desencriptarNombres($persVend->paterno,$persVend->materno,$persVend->nombre);
          }
        }
      }

      if (!is_null($rOrdPag->reembolso_main)) {
        $factura_relacionada_typo = "reembolsos";
				$rReem = $reembolsosMap->get($rOrdPag->reembolso_main);
        $mostrar_partida = $rReem ? true : false;

        if ($rReem) {
          //$fecha_contabilizacion_doc_anterior = gmdate('Y-m-d H:i:s',$rReem->fecha_contabilizacion);
          $factura_relacionada_token = $rReem->token_reem;
          $factura_relacionada_string = 'REEM-'.$JwtAuth->generarFolio($rReem->folio_reem).(!is_null($rReem->post_folio_reem) ? '-'.$rReem->post_folio_reem : '');
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/reembolso_pdf/".$factura_relacionada_token;

					$vEmi = $reembolsoEmisorEmpMap->get($rReem->emisor);
          if ($vEmi) {
            $orden_emisor_emp = $vEmi->abrev_nombre;
          }

          $vpEmi = $reembolsoEmisorPersMap->get($rReem->user_acreedor);
          if ($vpEmi) {
            $orden_emisor_personal_token = $vpEmi->token_cat_acreedores;
            $orden_emisor_personal_folio = 'ACREE-'.$JwtAuth->generarFolio($vpEmi->acr_folio).(!is_null($vpEmi->acr_post_folio) ? '-'.$vpEmi->acr_post_folio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vpEmi->acr_titular);
            $orden_emisor_personal_nombre_comercial = !is_null($vpEmi->acr_nombre_comercial) ? $JwtAuth->desencriptar($vpEmi->acr_nombre_comercial) : '';
          }

          $soli_reem = $reembolsoSoliMap->get($rOrdPag->reembolso_main) ?? collect([]);
          foreach ($soli_reem as $vSoliR) {
            $orden_moneda_inicial_name = $vSoliR->moneda_entrante;
            $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);
            $importe_total_inicial += $vSoliR->importe_entrante;
          }

          $soli_reem_auth = $reembolsoSoliAuthMap->get($rOrdPag->reembolso_main) ?? collect([]);
          foreach ($soli_reem_auth as $vSoliA) {
            $orden_moneda_autorizado_inicial_tkn = $vSoliA->moneda_entrante;
            $orden_moneda_autorizado_inicial_name = $vSoliA->moneda_entrante;
            $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vSoliA->moneda_entrante);

            $importe_autorizado_inicial = $importe_autorizado_inicial + $vSoliA->importe_entrante;
            $importe_autorizado_final = $importe_autorizado_inicial * $vSoliA->tipo_cambio;

            $orden_moneda_autorizado_final_name = $vSoliA->moneda_entrante;
            $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vSoliA->moneda_entrante);
          }
        }
      }

      if (!is_null($rOrdPag->anticipo_proveedor)) {
				$factura_relacionada_typo = "anticipos";
				$oAnt = $anticiposMap->get($rOrdPag->anticipo_proveedor);
        $mostrar_partida = $oAnt ? true : false;

        if ($oAnt) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oAnt->ant_fecha_contabilizacion);
          $factura_relacionada_token = $oAnt->uuid_anticipo;
          $factura_relacionada_string = 'ANT-'.$JwtAuth->generarFolio($oAnt->folio_anticipo);
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/anticipo_pdf/".$factura_relacionada_token;

          $vopAnt = $anticiposProveedorMap->get($oAnt->uuid_anticipo);
          if ($vopAnt) {
            $orden_emisor_personal_folio = 'PRV-'.$JwtAuth->generarFolio($vopAnt->folio) . ($vopAnt->post_folio != NULL ? '-'.$vopAnt->post_folio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vopAnt->nombre_extendido);
            $orden_emisor_personal_nombre_comercial = !is_null($vopAnt->nombre_com) ? $JwtAuth->desencriptar($vopAnt->nombre_com) : '';
          }

          $orden_moneda_inicial_name = $oAnt->moneda_code;
          $orden_moneda_inicial_decimales = $oAnt->moneda_decimales;
          $importe_total_inicial += ($oAnt->monto_total * $oAnt->tipo_cambio);

          $orden_moneda_autorizado_inicial_tkn = $oAnt->moneda_code;
          $orden_moneda_autorizado_inicial_name = $oAnt->moneda_code;
          $orden_moneda_autorizado_inicial_decimales = $oAnt->moneda_decimales;

          $importe_autorizado_inicial += $oAnt->monto_total;
          $importe_autorizado_final = $importe_autorizado_inicial * $oAnt->tipo_cambio;

          $orden_moneda_autorizado_final_name = $oAnt->moneda_code;
          $orden_moneda_autorizado_final_decimales = $oAnt->moneda_decimales;
        }
      }
      //$rOrdPag->ord_deudor
      if (!is_null($rOrdPag->ord_anticipo)) {
        $factura_relacionada_typo = "anticipos";
        $ordAnt = $anticiposORDMap->get($rOrdPag->ord_anticipo);
        $mostrar_partida = $ordAnt ? true : false;

        if ($ordAnt) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$ordAnt->ant_fecha_contabilizacion);
          $factura_relacionada_token = $ordAnt->uuid_anticipo;
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/anticipo_pdf/".$factura_relacionada_token;
          $factura_relacionada_string = 'ANT-'.$JwtAuth->generarFolio($ordAnt->folio_anticipo);

          $vEmpAnt = $anticiposORDEmpMap->get($ordAnt->empresa);
          if ($vEmpAnt) {
            $orden_emisor_emp = $vEmpAnt->abrev_nombre;
          }

          $oDeu = $anticiposORDDeudorMap->get($ordAnt->ant_deudor_vinculado);
          if ($oDeu) {
            $orden_emisor_personal_token = $oDeu->token_cat_deudores;
            $folio_deu = 'DEU-'.$JwtAuth->generarFolio($oDeu->deu_folio).(!is_null($oDeu->deu_post_folio) ? '-'.$oDeu->deu_post_folio : '');
            $orden_emisor_personal_folio = $folio_deu;
            $orden_emisor_personal_nombre = !is_null($oDeu->deu_titular) && $oDeu->deu_titular != '' ? $JwtAuth->desencriptar($oDeu->deu_titular) : 'N/A';
          }

          $orden_moneda_inicial_name = $ordAnt->moneda_code;
          $orden_moneda_inicial_decimales = $ordAnt->moneda_decimales;
          $importe_total_inicial += ($ordAnt->monto_total * $ordAnt->tipo_cambio);

          $orden_moneda_autorizado_inicial_tkn = $ordAnt->moneda_code;
          $orden_moneda_autorizado_inicial_name = $ordAnt->moneda_code;
          $orden_moneda_autorizado_inicial_decimales = $ordAnt->moneda_decimales;

          $importe_autorizado_inicial += $ordAnt->monto_total;
          $importe_autorizado_final = $importe_autorizado_inicial * $ordAnt->tipo_cambio;

          $orden_moneda_autorizado_final_name = $ordAnt->moneda_code;
          $orden_moneda_autorizado_final_decimales = $ordAnt->moneda_decimales;
        }
      }

      if (!is_null($rOrdPag->nomina_main)) {
        if (!is_null($rOrdPag->nomina_en_especie)) {
          $factura_relacionada_typo = "nominas_especie";
          $vEspNom = $nominaEspecieMap->get($rOrdPag->nomina_en_especie);
          $mostrar_partida = $vEspNom ? true : false;
          if ($vEspNom) {
						$vEspEmpNom = $empEnviaEspecieNominaMap->get($vEspNom->nomina_esp_empresa);
            if ($vEspEmpNom) {
              $orden_emisor_emp = $vEspEmpNom->abrev_nombre;
            }

            $fecha_contabilizacion_doc_anterior = date('Y-m-d',$vEspNom->nomina_esp_fecha_contabilizacion);
            $factura_relacionada_token = $vEspNom->token_nominas_especie;
            $factura_relacionada_string = 'NOM-ES-'.$JwtAuth->generarFolio($vEspNom->nomina_esp_folio_interior).(!is_null($vEspNom->nomina_esp_subfolio) ? '-'.$vEspNom->nomina_esp_subfolio : '');
            $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/nomina_en_especie_pdf/".$factura_relacionada_token;
            
						$detailEspNominaLista = $detailEspNominaMap->get($vEspNom->token_nominas_especie) ?? collect([]);
            foreach ($detailEspNominaLista as $vNomDetEsp) {
              $orden_moneda_inicial_name = $vNomDetEsp->nomina_esp_moneda;
              $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
              $orden_moneda_autorizado_inicial_name = $vNomDetEsp->nomina_esp_moneda;
              $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
              $orden_moneda_autorizado_final_name = $vNomDetEsp->nomina_esp_moneda;
              $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
              $importe_concepto_simple = floatval($vNomDetEsp->total_en_especie);
              $importe_total_inicial += $importe_concepto_simple;
              $importe_autorizado_inicial += $importe_concepto_simple;

              $importe_autorizado_final += floatval($vNomDetEsp->total_en_especie);
            }
          }
        } else {
          $factura_relacionada_typo = "nominas";
					$vNom = $nominaMainMap->get($rOrdPag->nomina_main);

          $mostrar_partida = $vNom ? true : false;
          if ($vNom) {
						$vEmpNom = $empEnviaNominaMainMap->get($vNom->nomina_empresa);
            if ($vEmpNom) {
              $orden_emisor_emp = $vEmpNom->abrev_nombre;
            }
            
            $fecha_contabilizacion_doc_anterior = date('Y-m-d',$vNom->nomina_fecha_contabilizacion);
            $factura_relacionada_token = $vNom->token_nominas_periodos;
            $factura_relacionada_string = 'NOM-EF-'.$JwtAuth->generarFolio($vNom->nomina_folio_interior).(!is_null($vNom->nomina_subfolio) ? '-'.$vNom->nomina_subfolio : '');
            $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/nomina_en_efectivo_pdf/".$factura_relacionada_token;
            
						$detalleNominaLista = $detalleNominaListaMap->get($vNom->token_nominas_periodos);
            foreach ($detalleNominaLista as $vNomDetMain) {
              $orden_moneda_inicial_name = $vNomDetMain->nomina_moneda;
              $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
              $orden_moneda_autorizado_inicial_name = $vNomDetMain->nomina_moneda;
              $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
              $orden_moneda_autorizado_final_name = $vNomDetMain->nomina_moneda;
              $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);

              $importe_concepto_simple = floatval($vNomDetMain->total_efectivo);
              $importe_total_inicial += $importe_concepto_simple;
              $importe_autorizado_inicial += $importe_concepto_simple;

              $importe_autorizado_final += floatval($vNomDetMain->total_efectivo);
            }
          }
        }
      }

      if (!is_null($rOrdPag->impuesto_sobre_nomina)) {
        $factura_relacionada_typo = "impuestos sobre nómina";
				$oIsn = $isNominaMap->get($rOrdPag->impuesto_sobre_nomina);
        $mostrar_partida = $oIsn ? true : false;

        if ($oIsn) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oIsn->nomi_imp_fecha_contabilizacion);
					
					$vIsnEst = $isNominaEstadoMap->get($oIsn->nomi_imp_estado);
          if ($vIsnEst) {
            $orden_emisor_personal_token = $vIsnEst->fed_est_mun_token;
            $orden_emisor_personal_folio = 'FEM-'.$JwtAuth->generarFolio($vIsnEst->fed_est_mun_folio).(!is_null($vIsnEst->fed_est_mun_subfolio) ? '-'.$vIsnEst->fed_est_mun_subfolio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vIsnEst->fed_est_mun_entidad);
          }

          $orden_moneda_inicial_name = $oIsn->nomi_imp_moneda;
          $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oIsn->nomi_imp_moneda);
          $orden_moneda_autorizado_inicial_name = $oIsn->nomi_imp_moneda;
          $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($oIsn->nomi_imp_moneda);
          $orden_moneda_autorizado_final_name = $oIsn->nomi_imp_moneda;
          $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($oIsn->nomi_imp_moneda);

          $factura_relacionada_token = $oIsn->nomi_imp_token;
          $folio_nomina = $oIsn->nomi_imp_folio_interior;
          $post_folio_nomina = $oIsn->nomi_imp_subfolio;
          $factura_relacionada_string = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/impuestos_sobre_nomina_pdf/".$factura_relacionada_token;

					$vIsnEmp = $isNominaEmpMap->get($oIsn->nomina_empresa);
          if ($vIsnEmp) {
            $orden_emisor_emp = $vIsnEmp->abrev_nombre;
          }

          $importe_total_inicial = $oIsn->nomi_imp_impuesto_total_a_pagar;
          $importe_autorizado_inicial = $oIsn->nomi_imp_impuesto_total_a_pagar;
          $importe_autorizado_final = $oIsn->nomi_imp_impuesto_total_a_pagar;
        }
      }

      if (!is_null($rOrdPag->aportacion_seguridad_social)) {
        $factura_relacionada_typo = "aportaciones de seguridad social";
				$oIMMS = $aportSSocialMap->get($rOrdPag->aportacion_seguridad_social);
        $mostrar_partida = $oIMMS ? true : false;

        if ($oIMMS) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oIMMS->aport_ssocial_fecha_contabilizacion);
					$vFed = $ssocialEstMuniMap->get($oIMMS->proveedor_imss);
          if ($vFed) {
            $orden_emisor_personal_token = $vFed->fed_est_mun_token;
            $orden_emisor_personal_folio = 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vFed->fed_est_mun_entidad);
          }

          $orden_moneda_inicial_name = $oIMMS->aport_ssocial_moneda;
          $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oIMMS->aport_ssocial_moneda);
          $orden_moneda_autorizado_inicial_name = $oIMMS->aport_ssocial_moneda;
          $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($oIMMS->aport_ssocial_moneda);
          $orden_moneda_autorizado_final_name = $oIMMS->aport_ssocial_moneda;
          $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($oIMMS->aport_ssocial_moneda);

          $factura_relacionada_token = $oIMMS->aport_ssocial_token;
          $aport_ssocial_folio = $oIMMS->aport_ssocial_folio_interior;
          $aport_ssocial_post_folio = $oIMMS->aport_ssocial_subfolio;
          $factura_relacionada_string = 'APORT-IMSS-'.$JwtAuth->generarFolio($aport_ssocial_folio).(!is_null($aport_ssocial_post_folio) ? '-'.$aport_ssocial_post_folio : '');
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/aportaciones_de_seguridad_social_pdf/".$factura_relacionada_token;
					
					$vSocialEmp = $ssocialEmpMap->get($oIMMS->aport_ssocial_empresa);
          if ($vSocialEmp) {
            $orden_emisor_emp = $vSocialEmp->abrev_nombre;
          }
					
					$ssocialTotales = DB::table("imss_cuotas_detalle")
					->where("aportaciones_main", $oIMMS->id)
					->whereNotIn('label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
					->sum('total');
					if ($ssocialTotales) {
						$importe_total_inicial = $ssocialTotales;
						$importe_autorizado_inicial = $ssocialTotales;
						$importe_autorizado_final = $ssocialTotales;
					}
        }
      }

      if (!is_null($rOrdPag->declaracion_imp_federales)) {
        $factura_relacionada_typo = "declaraciones de impuestos federales";
				$oDecFed = $declaracionesImpFederalesMap->get($rOrdPag->declaracion_imp_federales);
        $mostrar_partida = $oDecFed ? true : false;

        if ($oDecFed) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oDecFed->declaracion_fecha_contabilizacion);
					$vDecEstMuni = $decFedEstMuniMap->get($oDecFed->proveedor_sat);
          if ($vDecEstMuni) {
            $orden_emisor_personal_token = $vDecEstMuni->fed_est_mun_token;
            $orden_emisor_personal_folio = 'FEM-'.$JwtAuth->generarFolio($vDecEstMuni->fed_est_mun_folio).(!is_null($vDecEstMuni->fed_est_mun_subfolio) ? '-'.$vDecEstMuni->fed_est_mun_subfolio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vDecEstMuni->fed_est_mun_entidad);
          }

          $orden_moneda_inicial_name = $oDecFed->declaracion_moneda;
          $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oDecFed->declaracion_moneda);
          $orden_moneda_autorizado_inicial_name = $oDecFed->declaracion_moneda;
          $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($oDecFed->declaracion_moneda);
          $orden_moneda_autorizado_final_name = $oDecFed->declaracion_moneda;
          $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($oDecFed->declaracion_moneda);

          $factura_relacionada_token = $oDecFed->declaracion_token;
          $factura_relacionada_string = 'DEC-IMPFED-'.$JwtAuth->generarFolio($oDecFed->declaracion_folio_interior).(!is_null($oDecFed->declaracion_subfolio) ? '-'.$oDecFed->declaracion_subfolio : '');
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/declaraciones_de_impuestos_federales_pdf/".$factura_relacionada_token;

					$vDecEmp = $decFedEmpMap->get($oDecFed->declaracion_empresa);
          if ($vDecEmp) {
            $orden_emisor_emp = $vDecEmp->abrev_nombre;
          }
					
					$decFedCantidadAPagar = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose")
					->where("declaracion", $oDecFed->id)
					->sum('dec_desglose_impuesto_cantidad_a_pagar');
					if ($decFedCantidadAPagar) {
						$importe_total_inicial = $decFedCantidadAPagar;
						$importe_autorizado_inicial = $decFedCantidadAPagar;
						$importe_autorizado_final = $decFedCantidadAPagar;
					}
        }
      }
      
      if (!is_null($rOrdPag->asimilados_reporte)) {
        $factura_relacionada_typo = "reporte de asimilados";
				$oAsim = $asimiladosMainMap->get($rOrdPag->asimilados_reporte);
        $mostrar_partida = $oAsim ? true : false;

        if ($oAsim) {
          $fecha_contabilizacion_doc_anterior = date('Y-m-d',$oAsim->asim_fecha_contabilizacion);

          $orden_moneda_inicial_name = $oAsim->asim_main_moneda;
          $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oAsim->asim_main_moneda);
          $orden_moneda_autorizado_inicial_name = $oAsim->asim_main_moneda;
          $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($oAsim->asim_main_moneda);
          $orden_moneda_autorizado_final_name = $oAsim->asim_main_moneda;
          $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($oAsim->asim_main_moneda);

          $factura_relacionada_token = $oAsim->token_reporte_asim;
          $factura_relacionada_string = 'ASIM-'.$JwtAuth->generarFolio($oAsim->asim_folio_interior).(!is_null($oAsim->asim_subfolio) ? '-'.$oAsim->asim_subfolio : '');
          $factura_relacionada_link = "https://downloads.sos-mexico.com.mx/reporte_de_asimilados_pdf/".$factura_relacionada_token;

          $asRecept = $asimiladosReceptorMap->get($oAsim->token_reporte_asim);
          if ($asRecept) {
            $orden_emisor_personal_token = $asRecept->token_cat_proveedores;
            $orden_emisor_personal_folio = 'PRV-'.$JwtAuth->generarFolio($asRecept->folio) . ($asRecept->post_folio != NULL ? '-'.$asRecept->post_folio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($asRecept->nombre_extendido);
            $orden_emisor_personal_nombre_comercial = !is_null($asRecept->nombre_com) ? $JwtAuth->desencriptar($asRecept->nombre_com) : '';
          }

					$vAsimEmp = $asimEmpMap->get($oAsim->asim_empresa);
          if ($vAsimEmp) {
            $orden_emisor_emp = $vAsimEmp->abrev_nombre;
          }
					
          $vAsmTotal = $asimiladosTotalMap->get($oAsim->token_reporte_asim);
          //echo $rOrdPag->asimilados_reporte;
          //var_dump($vAsmTotal);
          //cfdi_comprobante_total" => $cfdi_comprobante_total,
					$decFedCantidadAPagar = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose")
					->where("declaracion", $oAsim->id)
					->sum('dec_desglose_impuesto_cantidad_a_pagar');
					if ($vAsmTotal) {
						$importe_total_inicial = $vAsmTotal->cfdi_comprobante_total;
						$importe_autorizado_inicial = $vAsmTotal->cfdi_comprobante_total;
						$importe_autorizado_final = $vAsmTotal->cfdi_comprobante_total;
					}
        }
      }
      //pagos_realizados
      $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? gmdate('Y-m-d H:i:s', $rOrdPag->orden_terminada_fecha) : "---";
      $pagos_realizados = DB::table("fnzs_pagos_pago AS pay")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
      ->where([
				"vinc.vinculo_cancelado" => FALSE,
				"order.token_ordenPago" => $rOrdPag->token_ordenPago
			])
      ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])
      ->sum('vinc.orden_pago_monto');

      $lista_pagos_realizados = $this->pagosDoneBYOrden($rOrdPag->token_ordenPago,$JwtAuth);

      $pago_restante = count($lista_pagos_realizados) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

      $pago_orden_cancel_user = "";
      if ($rOrdPag->op_cancel) {
        $queryUserCancel = $UsuarioCancelaMap->get($rOrdPag->pago_orden_cancel_user);
        $pago_orden_cancel_user = $queryUserCancel ? $JwtAuth->desencriptarNombres($queryUserCancel->paterno, $queryUserCancel->materno, $queryUserCancel->nombre) : '';
      }

      if ($mostrar_partida) {
        $lpr = $lista_pagos_realizados;
        $pago_rr_forma_metodo_pago_cfdi = '';
        if (count($lpr) > 0) {
          if ($lpr[0]['forma_pago_cfdi'] != '' && $lpr[0]['metodo_pago_cfdi'] != '') {
            $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['forma_pago_cfdi']." / ".$lpr[0]['metodo_pago_cfdi'];
          } elseif ($lpr[0]['forma_pago_cfdi'] != '' && $lpr[0]['metodo_pago_cfdi'] == '') {
            $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['forma_pago_cfdi'];
          } elseif ($lpr[0]['forma_pago_cfdi'] == '' && $lpr[0]['metodo_pago_cfdi'] != '') {
            $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['metodo_pago_cfdi'];
          } else {
            $pago_rr_forma_metodo_pago_cfdi = '';
          }
        }
        
        $row_ordenPay = array(
          "id" => $id_list,
          "token_ordenPago" => $rOrdPag->token_ordenPago,
          "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
          "fecha_contabilizacion_doc_anterior" => $fecha_contabilizacion_doc_anterior,
          "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? date('Y-m-d',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
          "fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
          "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
          "autorizacion_pay" => $autorizacion_pay,
          "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
          "autorizacion_pay_text" => "",
          "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
          "factura_relacionada_typo" => $factura_relacionada_typo,
          "factura_relacionada_token" => $factura_relacionada_token,
          "factura_relacionada_string" => $factura_relacionada_string,
          "factura_relacionada_link" => $factura_relacionada_link,
          "orden_emisor_emp" => $orden_emisor_emp,

          "orden_emisor_personal_token" => $orden_emisor_personal_token,
          "orden_emisor_personal_folio" => $orden_emisor_personal_folio,
          "orden_emisor_personal_nombre" => $orden_emisor_personal_nombre,
          "orden_emisor_personal_nombre_comercial" => $orden_emisor_personal_nombre_comercial,

          "importe_total_inicial_simple" => $importe_total_inicial,
          "orden_moneda_inicial_name" => $orden_moneda_inicial_name,
          "importe_total_inicial" => $this->muestraCantidadesConMoneda($importe_total_inicial,$orden_moneda_inicial_name,$orden_moneda_inicial_decimales),
          "importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
          "orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
          "orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
          "importe_autorizado_inicial_format" => $this->muestraCantidadesConMoneda($importe_autorizado_inicial,$orden_moneda_autorizado_inicial_name,$orden_moneda_autorizado_inicial_decimales),
          //$orden_moneda_inicial_decimales = 0;
          "importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
          "importe_autorizado_final" => $this->muestraCantidadesConMoneda($importe_autorizado_final,$orden_moneda_autorizado_final_name,$orden_moneda_autorizado_final_decimales),
          "orden_moneda_final_autorizada_name" => $orden_moneda_autorizado_final_name,
          "importe_restante" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
          "importe_restante_format" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "importe_por_pagar" => "0.00",
          "debe_simple" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
          "debe_format" => "$".number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "pago_anticipado" => "$".number_format($importe_total_anticipo, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          //$orden_moneda_final_decimales = 0;
          "status_pago" => $status_pay_bool,
          "status_pago_date" => $status_pay_date,
          "empresa" => "", //empresa
          "comprador" => "", //comprador
          "open_inside" => false, //comprador
          "detail_orden" => [], //comprador
          "autorizacion_proceso" => false, //comprador
          //pagos_realizados
          "lista_pagos_realizados" => $lista_pagos_realizados,
          "pago_realizado_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['token_pagos'] : '',
          "pago_realizado_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['folio_pagos'] : '',
          "pago_realizado_status" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['status_pago'] : '',
          "pago_realizado_folio_operacion" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['folio_operacion'] : '',
          "pago_realizado_fecha_pago" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_pago'] : '',
          "pago_realizado_fecha_contabilizacion" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_contabilizacion'] : '',
          "pago_realizado_monto" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['monto_pago'] : '',
          "pago_realizado_observaciones" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['observacionesPago'] : '',
          "pago_realizado_tipo_cambio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['tipo_cambio'] : '',
          "pago_realizado_moneda" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['p_moneda'] : '',
          "pago_realizado_destino" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['destino'] : '',
          "pago_realizado_concepto" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['concepto'] : '',
          //forma_pago
          "pago_realizado_forma_pago_vinculada" => count($lista_pagos_realizados) > 0 ? ($lista_pagos_realizados[0]['acreedor_name'] == '' ? $lista_pagos_realizados[0]['forma_pago_vinculada'] : '') : '',
          "pago_realizado_forma_pago_cfdi" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['forma_pago_cfdi'] : '',
          "pago_realizado_metodo_pago_cfdi" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['metodo_pago_cfdi'] : '',
          "pago_realizado_forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
          //proveedor
          "pago_realizado_proveedor_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['proveedor_token'] : '',
          "pago_realizado_proveedor_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['proveedor_name'] : '',
          //cliente
          "pago_realizado_cliente_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['cliente_token'] : '',
          "pago_realizado_cliente_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['cliente_name'] : '',
          //empleado
          "pago_realizado_empleado_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['empleado_token'] : '',
          "pago_realizado_empleado_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['empleado_name'] : '',
          //acreedor
          "pago_realizado_acreedor_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['acreedor_token'] : '',
          "pago_realizado_acreedor_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['acreedor_name'] : '',
          //personal_pago
          "pago_realizado_personal_pago_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_token'] : '',
          "pago_realizado_personal_pago_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_folio'] : '',
          "pago_realizado_personal_pago_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_name'] : '',
          "pago_realizado_pago_autorizado" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['pago_autorizado'] : '',
          "pago_realizado_fecha_pago_auth" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_pago_auth'] : '',
          //personal_autoriza
          "pago_realizado_personal_autoriza_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_token'] : '',
          "pago_realizado_personal_autoriza_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_folio'] : '',
          "pago_realizado_personal_autoriza_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_name'] : '',
          //cancelacion op_cancel
          "op_cancel" => (bool)$rOrdPag->op_cancel,
          "pago_orden_cancel_user" => $rOrdPag->op_cancel ? $pago_orden_cancel_user : '',
          "pago_orden_cancel_fecha_cont" => $rOrdPag->op_cancel ? date('Y-m-d', $rOrdPag->pago_orden_cancel_fecha_cont) : '',
          "pago_orden_cancel_comentarios" => $rOrdPag->op_cancel ? $JwtAuth->desencriptar($rOrdPag->pago_orden_cancel_comentarios) : ''
        );
        $ordenes_pago[] = $row_ordenPay;
        ++$id_list;
      }
    }
    return $ordenes_pago;
	}

  private function eachGeneralDisper($listOrdenes,$empresa,$usuario,$JwtAuth){
    $ordenes_pago = array();
    $id_list = 1;
    foreach ($listOrdenes as $nomOrd) {
      //da_te_default_timezone_set($nomOrd->zona_horaria);
      $autorizacion_pay = $nomOrd->autorizacion_pay ? true : false;
      $fecha_autorizacion_pay = $nomOrd->autorizacion_pay ? gmdate('Y-m-d H:i:s', $nomOrd->fecha_autorizacion_pay) : "---";
      $status_pay_bool = $nomOrd->autorizacion_pay && $nomOrd->orden_terminada_bool ? true : false;
      
      $factura_relacionada_typo = "nominas";
      $factura_relacionada_token = "---";
      $factura_relacionada_string = "---";
      
      $factura_relacionada_token = $nomOrd->token_nominas_periodos;
      $factura_relacionada_string = 'NOM-EF-' . $JwtAuth->generarFolio($nomOrd->nomina_folio_interior) . (!is_null($nomOrd->nomina_subfolio) ? '-' . $nomOrd->nomina_subfolio : '');
      $fecha_contabilizacion_doc_anterior = gmdate('Y-m-d H:i:s', $nomOrd->nomina_fecha_contabilizacion);

      $orden_emisor_emp = DB::table("vhum_nominas_main AS nomi")
      ->join("main_empresas AS emp", "nomi.nomina_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("nomi.token_nominas_periodos", $nomOrd->token_nominas_periodos)
      ->value("people.abrev_nombre");

      $importe_total_anticipo = 0;
      $importe_total_inicial = 0;
      $orden_moneda_inicial_name = "---";
      $orden_moneda_inicial_decimales = 0;

      $importe_autorizado_inicial = 0;
      $orden_moneda_autorizado_inicial_tkn = "---";
      $orden_moneda_autorizado_inicial_name = "---";
      $orden_moneda_autorizado_inicial_decimales = 0;

      $importe_autorizado_final = 0;
      $orden_moneda_autorizado_final_name = "---";
      $orden_moneda_autorizado_final_decimales = 0;
      
      $importe_concepto_simple = floatval(DB::table("vhum_nominas_recibos AS recibos")
      ->join("vhum_nominas_main AS nomi", "recibos.nomina_main", "=", "nomi.id")
      ->join("main_empresas AS emp", "nomi.nomina_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'nomi.token_nominas_periodos' => $nomOrd->token_nominas_periodos,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->sum('recibos.total_efectivo'));

      $importe_total_inicial = $importe_concepto_simple;
      $importe_autorizado_inicial = $importe_concepto_simple;
      $importe_autorizado_final = $importe_concepto_simple;

      $detalleNominaLista = DB::table("vhum_nominas_recibos AS recibos")
      ->join("vhum_nominas_main AS nomi", "recibos.nomina_main", "=", "nomi.id")
      ->join("main_empresas AS emp", "nomi.nomina_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'nomi.token_nominas_periodos' => $nomOrd->token_nominas_periodos,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      foreach ($detalleNominaLista as $vNomBuy) {
        $orden_moneda_inicial_name = $vNomBuy->nomina_moneda;
        $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomBuy->nomina_moneda);
        $orden_moneda_autorizado_inicial_name = $vNomBuy->nomina_moneda;
        $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomBuy->nomina_moneda);
        $orden_moneda_autorizado_final_name = $vNomBuy->nomina_moneda;
        $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomBuy->nomina_moneda);
      }

      if ($nomOrd->nomina_en_especie != NULL) {
        $factura_relacionada_typo = "nominas_especie";
        $query_nomina_especie = DB::table("fnzs_pagos_orden AS order")
        ->join("vhum_nominas_especie AS nomesp", "order.nomina_en_especie", "=", "nomesp.id")
        ->whereIn('nomesp.id', function ($query) {
          $query->select('nomina_especie')->from('vhum_nominas_especie_desglose');
        })
        ->where(["order.token_ordenPago" => $nomOrd->token_ordenPago])->get();
        foreach ($query_nomina_especie as $vEspNom) {
          $factura_relacionada_token = $vEspNom->token_nominas_especie;
          $factura_relacionada_string = 'NOM-ES-' . $JwtAuth->generarFolio($vEspNom->nomina_esp_folio_interior) . (!is_null($vEspNom->nomina_esp_subfolio) ? '-' . $vEspNom->nomina_esp_subfolio : '');

          $importe_concepto_simple = floatval(DB::table("vhum_nominas_especie_desglose AS desg_nom")
          ->join("vhum_nominas_especie AS nomesp", "desg_nom.nomina_especie", "=", "nomesp.id")
          ->join("main_empresas AS emp", "nomesp.nomina_esp_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'nomesp.token_nominas_especie' => $vEspNom->token_nominas_especie,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->sum('desg_nom.total_en_especie'));
          $importe_total_inicial = $importe_concepto_simple;
          $importe_autorizado_inicial = $importe_concepto_simple;
          $importe_autorizado_final = $importe_concepto_simple;

          $detailEspNominaLista = DB::table("vhum_nominas_especie_desglose AS desg_nom")
          ->join("vhum_nominas_especie AS nomesp", "desg_nom.nomina_especie", "=", "nomesp.id")
          ->join("main_empresas AS emp", "nomesp.nomina_esp_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'nomesp.token_nominas_especie' => $vEspNom->token_nominas_especie,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])->get();

          foreach ($detailEspNominaLista as $vNomDetEsp) {
            $orden_moneda_inicial_name = $vNomDetEsp->nomina_esp_moneda;
            $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
            $orden_moneda_autorizado_inicial_name = $vNomDetEsp->nomina_esp_moneda;
            $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
            $orden_moneda_autorizado_final_name = $vNomDetEsp->nomina_esp_moneda;
            $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
          }
        }
      }

      //pagos_realizados
      $status_pay_date = $nomOrd->autorizacion_pay && $nomOrd->orden_terminada_bool ? gmdate('Y-m-d H:i:s', $nomOrd->orden_terminada_fecha) : "---";
      $pagos_realizados = DB::table("fnzs_pagos_pago AS pay")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
      ->where(["order.token_ordenPago" => $nomOrd->token_ordenPago])
      ->sum('vinc.orden_pago_monto');
      
      $lista_pagos_realizados = is_null($nomOrd->nomina_en_especie) ? $this->pagosDoneBYDispersionEfectivoOrden($nomOrd->token_ordenPago,$JwtAuth) :  $this->pagosDoneBYDispersionEspecieOrden($nomOrd->token_ordenPago,$JwtAuth);

      $pago_restante = count($lista_pagos_realizados) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

      $lpr = $lista_pagos_realizados;
      $pago_rr_forma_metodo_pago_cfdi = '';
      if (count($lpr) > 0) {
        if ($lpr[0]['forma_pago_cfdi'] != '' && $lpr[0]['metodo_pago_cfdi'] != '') {
          $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['forma_pago_cfdi'] . " / " . $lpr[0]['metodo_pago_cfdi'];
        } elseif ($lpr[0]['forma_pago_cfdi'] != '' && $lpr[0]['metodo_pago_cfdi'] == '') {
          $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['forma_pago_cfdi'];
        } elseif ($lpr[0]['forma_pago_cfdi'] == '' && $lpr[0]['metodo_pago_cfdi'] != '') {
          $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['metodo_pago_cfdi'];
        } else {
          $pago_rr_forma_metodo_pago_cfdi = '';
        }
      }

      $row_ordenPay = array(
        "id" => $id_list,
        "token_ordenPago" => $nomOrd->token_ordenPago,
        "folio_ordenPago" => "ORDP-" . $JwtAuth->generarFolio($nomOrd->folio_ordenPago),
        "fecha_contabilizacion_doc_anterior" => $fecha_contabilizacion_doc_anterior,
        "fecha_contabilizacion_orden_pago" => $nomOrd->fecha_contabilizacion_ordenPago ? gmdate('Y-m-d H:i:s', $nomOrd->fecha_contabilizacion_ordenPago) : '',
        "fecha_registro" => gmdate('Y-m-d H:i:s', $nomOrd->fecha_sistema_ordenp),
        "orden_bloqueada" => $nomOrd->orden_bloqueada ? true : false,
        "autorizacion_pay" => $autorizacion_pay,
        "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
        "autorizacion_pay_text" => "",
        "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
        "factura_relacionada_typo" => $factura_relacionada_typo,
        "factura_relacionada_token" => $factura_relacionada_token,
        "factura_relacionada_string" => $factura_relacionada_string,
        "orden_emisor_emp" => $orden_emisor_emp,

        "importe_total_inicial_simple" => $importe_total_inicial,
        "orden_moneda_inicial_name" => $orden_moneda_inicial_name,
        "importe_total_inicial" => $JwtAuth->muestraCantidadesConMoneda($importe_total_inicial, $orden_moneda_inicial_name, $orden_moneda_inicial_decimales),
        "importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
        "orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
        "orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
        "importe_autorizado_inicial_format" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_name, $orden_moneda_autorizado_inicial_decimales),
        //$orden_moneda_inicial_decimales = 0;
        "importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
        "importe_autorizado_final" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_final, $orden_moneda_autorizado_final_name, $orden_moneda_autorizado_final_decimales),
        "orden_moneda_final_autorizada_name" => $orden_moneda_autorizado_final_name,
        "importe_restante" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
        "importe_restante_format" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',') . " $orden_moneda_autorizado_final_name",
        "importe_por_pagar" => "0.00",
        "debe_simple" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
        "debe_format" => "$" . number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',') . " $orden_moneda_autorizado_final_name",
        "pago_anticipado" => "$" . number_format($importe_total_anticipo, $orden_moneda_autorizado_final_decimales, '.', ',') . " $orden_moneda_autorizado_final_name",
        //$orden_moneda_final_decimales = 0;
        "status_pago" => $status_pay_bool,
        "status_pago_date" => $status_pay_date,
        "empresa" => "", //empresa
        "comprador" => "", //comprador
        "open_inside" => false, //comprador
        "detail_orden" => [], //comprador
        "autorizacion_proceso" => false, //comprador
        //pagos_realizados
        "lista_pagos_realizados" => $lista_pagos_realizados,
        "pago_realizado_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['token_pagos'] : '',
        "pago_realizado_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['folio_pagos'] : '',
        "pago_realizado_status" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['status_pago'] : '',
        "pago_realizado_folio_operacion" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['folio_operacion'] : '',
        "pago_realizado_fecha_pago" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_pago'] : '',
        "pago_realizado_fecha_contabilizacion" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_contabilizacion'] : '',
        "pago_realizado_monto" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['monto_pago'] : '',
        "pago_realizado_observaciones" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['observacionesPago'] : '',
        "pago_realizado_tipo_cambio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['tipo_cambio'] : '',
        "pago_realizado_moneda" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['p_moneda'] : '',
        "pago_realizado_destino" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['destino'] : '',
        "pago_realizado_concepto" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['concepto'] : '',
        //forma_pago
        "pago_realizado_forma_pago_vinculada" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['forma_pago_vinculada'] : '',
        "pago_realizado_forma_pago_cfdi" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['forma_pago_cfdi'] : '',
        "pago_realizado_metodo_pago_cfdi" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['metodo_pago_cfdi'] : '',
        "pago_realizado_forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
        //proveedor
        "pago_realizado_proveedor_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['proveedor_token'] : '',
        "pago_realizado_proveedor_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['proveedor_name'] : '',
        //cliente
        "pago_realizado_cliente_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['cliente_token'] : '',
        "pago_realizado_cliente_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['cliente_name'] : '',
        //empleado
        "pago_realizado_empleado_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['empleado_token'] : '',
        "pago_realizado_empleado_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['empleado_name'] : '',
        //acreedor
        "pago_realizado_acreedor_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['acreedor_token'] : '',
        "pago_realizado_acreedor_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['acreedor_name'] : '',
        //personal_pago
        "pago_realizado_personal_pago_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_token'] : '',
        "pago_realizado_personal_pago_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_folio'] : '',
        "pago_realizado_personal_pago_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_name'] : '',
        "pago_realizado_pago_autorizado" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['pago_autorizado'] : '',
        "pago_realizado_fecha_pago_auth" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_pago_auth'] : '',
        //personal_autoriza
        "pago_realizado_personal_autoriza_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_token'] : '',
        "pago_realizado_personal_autoriza_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_folio'] : '',
        "pago_realizado_personal_autoriza_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_name'] : '',
      );
      $ordenes_pago[] = $row_ordenPay;
      ++$id_list;
    }
    return $ordenes_pago;
  }

	private function pagoAcreedoresMovimientos($JwtAuth,$queryActiMov,$empresa,$comentarios_confi,$ahora,$fecha_contabilizacion,$emp_userr){
    $fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
    $comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
    foreach ($queryActiMov as $vActMov) {
      $maxFolAcrMov = DB::table('fnzs_catalogo_acreedores_movimientos')->where('acre_empresa', $empresa)->lockForUpdate()->max('folio_acre_mov');
      $folioAcrMov = $maxFolAcrMov ? $maxFolAcrMov + 1 : 1;
      $folio_pago_generar = "ACRMOV-".$JwtAuth->generarFolio($folioAcrMov);
      
      $tokenMov = $JwtAuth->encriptarToken($folioAcrMov.$comentarios_confi.$ahora,$folio_pago_generar);
      
      DB::table("fnzs_catalogo_acreedores_movimientos")
      ->insert(array(
        "token_acre_mov" => $tokenMov,
        "folio_acre_mov" => $folioAcrMov,
        "acre_fecha_registro" => $ahora,
        "acre_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
        "acre_monto_mov" => $vActMov->monto_aplicado,
        "condicion_acree_mov" => $vActMov->condicion_acree_mov == "S" ? "R" : "S",
        "acre_observaciones_mov" => $comentarios_encriptados,
        "acre_tipo_cambio" => $vActMov->acre_tipo_cambio,
        "acre_mov_moneda" => $vActMov->acre_mov_moneda,
        "vinc_acreedor" => $vActMov->vinc_acreedor,
        "acre_personal_mov" => $emp_userr,
        "acre_mov_autorizado" => TRUE,
        "acre_fecha_mov_auth" => $ahora,
        "acre_personal_autoriza" => $emp_userr,
        "acre_empresa" => $empresa,
        "acre_status_mov" => TRUE
      ));

      $maxFolioCancelMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $empresa)->lockForUpdate()->max('folio_cancelacion');
      $folioActCancelMov = $maxFolioCancelMov ? $maxFolioCancelMov + 1 : 1;
      
      DB::table("fnzs_actividad_movimientos")->where("id",$vActMov->idMov)
      ->limit(1)->update(array(
        "movimiento_cancelado" => TRUE,
        "folio_cancelacion" => $folioActCancelMov,
        "fecha_cancelacion" => $ahora,
        "fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
      ));

      $maxFolioNewMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $empresa)->lockForUpdate()->max('folio_movimiento');
      $folioNewMov = $maxFolioNewMov ? $maxFolioNewMov + 1 : 1;

      $token_movimiento = $JwtAuth->encriptarToken($vActMov->acreedor_movimiento,$vActMov->folio_movimiento,$folioNewMov);

      DB::table("fnzs_actividad_movimientos")
      ->insert(array(
        "token_movimiento" => $token_movimiento,
        "folio_movimiento" => $folioNewMov,
        "fecha_sistema" => $ahora,
        "fecha_contabilizacion_movimiento" => $fechaContabilizacionUnix,
        "movimiento_asociado" => $vActMov->idMov,
        "tipo_movimiento" => "S",
        "subtipo_movimiento" => "C",
        "concepto_movimiento" => $vActMov->concepto_movimiento,
        "responsable" => $vActMov->responsable,
        "caja" => $vActMov->caja,	
        "cuenta_bancaria" => $vActMov->cuenta_bancaria,
        "cuenta_monedero" => $vActMov->cuenta_monedero,
        "monto_aplicado" => $vActMov->monto_aplicado,
        "moneda_movimiento" => $vActMov->moneda_movimiento,
        "tipo_cambio_movimiento" => $vActMov->tipo_cambio_movimiento,
        "observaciones_movimiento" => $comentarios_encriptados,
        "pago" => $vActMov->pago,
        "acreedor_movimiento" => $vActMov->acreedor_movimiento,
        "ajuste" => $vActMov->ajuste,
        "empresa" => $vActMov->empresa,
      )); 
    }
	}

	private function pagoDeudoresMovimientos($JwtAuth,$queryActiMov,$empresa,$comentarios_confi,$ahora,$fecha_contabilizacion,$emp_userr){
    $fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
    $comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
    foreach ($queryActiMov as $vActMov) {
      $maxFolDeuMov = DB::table('fnzs_catalogo_deudores_movimientos')->where('deu_empresa', $empresa)->lockForUpdate()->max('folio_deu_mov');
      $folioDeuMov = $maxFolDeuMov ? $maxFolDeuMov + 1 : 1;
      $folio_pago_generar = "DEUMOV-".$JwtAuth->generarFolio($folioDeuMov);
      
      $tokenMov = $JwtAuth->encriptarToken($folioDeuMov.$comentarios_confi.$ahora,$folio_pago_generar);
      
      DB::table("fnzs_catalogo_deudores_movimientos")
      ->insert(array(
        "token_deu_mov" => $tokenMov,
        "folio_deu_mov" => $folioDeuMov,
        "deu_fecha_registro" => $ahora,
        "deu_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
        "deu_monto_mov" => $vActMov->monto_aplicado,
        "condicion_deu_mov" => $vActMov->condicion_deu_mov == "S" ? "R" : "S",
        "deu_observaciones_mov" => $comentarios_encriptados,
        "deu_tipo_cambio" => $vActMov->deu_tipo_cambio,
        "deu_mov_moneda" => $vActMov->deu_mov_moneda,
        "vinc_deudor" => $vActMov->vinc_deudor,
        "deu_personal_mov" => $emp_userr,
        "deu_mov_autorizado" => TRUE,
        "deu_fecha_mov_auth" => $ahora,
        "deu_personal_autoriza" => $emp_userr,
        "deu_empresa" => $empresa,
        "deu_status_mov" => TRUE
      ));

      $maxFolioCancelMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $empresa)->lockForUpdate()->max('folio_cancelacion');
      $folioActCancelMov = $maxFolioCancelMov ? $maxFolioCancelMov + 1 : 1;
      
      DB::table("fnzs_actividad_movimientos")->where("id",$vActMov->idMov)
      ->limit(1)->update(array(
        "movimiento_cancelado" => TRUE,
        "folio_cancelacion" => $folioActCancelMov,
        "fecha_cancelacion" => $ahora,
        "fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
      ));

      $maxFolioNewMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $empresa)->lockForUpdate()->max('folio_movimiento');
      $folioNewMov = $maxFolioNewMov ? $maxFolioNewMov + 1 : 1;

      $token_movimiento = $JwtAuth->encriptarToken($vActMov->deudor_movimiento,$vActMov->folio_movimiento,$folioNewMov);

      DB::table("fnzs_actividad_movimientos")
      ->insert(array(
        "token_movimiento" => $token_movimiento,
        "folio_movimiento" => $folioNewMov,
        "fecha_sistema" => $ahora,
        "fecha_contabilizacion_movimiento" => $fechaContabilizacionUnix,
        "movimiento_asociado" => $vActMov->idMov,
        "tipo_movimiento" => "S",
        "subtipo_movimiento" => "C",
        "concepto_movimiento" => $vActMov->concepto_movimiento,
        "responsable" => $vActMov->responsable,
        "caja" => $vActMov->caja,	
        "cuenta_bancaria" => $vActMov->cuenta_bancaria,
        "cuenta_monedero" => $vActMov->cuenta_monedero,
        "monto_aplicado" => $vActMov->monto_aplicado,
        "moneda_movimiento" => $vActMov->moneda_movimiento,
        "tipo_cambio_movimiento" => $vActMov->tipo_cambio_movimiento,
        "observaciones_movimiento" => $comentarios_encriptados,
        "pago" => $vActMov->pago,
        "deudor_movimiento" => $vActMov->deudor_movimiento,
        "ajuste" => $vActMov->ajuste,
        "empresa" => $vActMov->empresa,
      ));
    }
	}

	private function pagoActMovimientos($JwtAuth,$queryActivMovimDone,$empresa,$comentarios_confi,$ahora,$fecha_contabilizacion,$emp_userr){
    $fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
    $comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);
    foreach ($queryActivMovimDone as $vActMov) {
      $maxFolioCancelMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $empresa)->lockForUpdate()->max('folio_cancelacion');
      $folioActCancelMov = $maxFolioCancelMov ? $maxFolioCancelMov + 1 : 1;
      
      DB::table("fnzs_actividad_movimientos")->where("id",$vActMov->idMov)
      ->limit(1)->update(array(
        "movimiento_cancelado" => TRUE,
        "folio_cancelacion" => $folioActCancelMov,
        "fecha_cancelacion" => $ahora,
        "fecha_contabilizacion_cancelacion" => $fechaContabilizacionUnix,
      ));

      $maxFolioNewMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $empresa)->lockForUpdate()->max('folio_movimiento');
      $folioNewMov = $maxFolioNewMov ? $maxFolioNewMov + 1 : 1;

      $token_movimiento = $JwtAuth->encriptarToken($vActMov->acreedor_movimiento,$vActMov->folio_movimiento,$folioNewMov);

      DB::table("fnzs_actividad_movimientos")
      ->insert(array(
        "token_movimiento" => $token_movimiento,
        "folio_movimiento" => $folioNewMov,
        "fecha_sistema" => $ahora,
        "fecha_contabilizacion_movimiento" => $fechaContabilizacionUnix,
        "movimiento_asociado" => $vActMov->idMov,
        "tipo_movimiento" => "S",
        "subtipo_movimiento" => "C",
        "concepto_movimiento" => $vActMov->concepto_movimiento,
        "responsable" => $vActMov->responsable,
        "caja" => $vActMov->caja,	
        "cuenta_bancaria" => $vActMov->cuenta_bancaria,
        "cuenta_monedero" => $vActMov->cuenta_monedero,
        "monto_aplicado" => $vActMov->monto_aplicado,
        "moneda_movimiento" => $vActMov->moneda_movimiento,
        "tipo_cambio_movimiento" => $vActMov->tipo_cambio_movimiento,
        "observaciones_movimiento" => $comentarios_encriptados,
        "pago" => $vActMov->pago,
        "acreedor_movimiento" => $vActMov->acreedor_movimiento,
        "ajuste" => $vActMov->ajuste,
        "empresa" => $vActMov->empresa,
      )); 
    }
	}
}