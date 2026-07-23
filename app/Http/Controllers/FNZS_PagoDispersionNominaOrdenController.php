<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use App\Models\PersonalModelo;

class FNZS_PagoDispersionNominaOrdenController extends Controller{
  private function eachGeneralDisper($listOrdenes,$empresa,$usuario,$JwtAuth){
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
    foreach ($listOrdenes as $nomOrd) {
      date_default_timezone_set($nomOrd->zona_horaria);
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
      
      $lista_pagos_realizados = $JwtAuth->pagosDoneBYOrden($nomOrd->token_ordenPago);

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

      $pago_orden_cancel_user = "";
      if ($nomOrd->op_cancel) {
        $queryUserCancel = $UsuarioCancelaMap->get($nomOrd->pago_orden_cancel_user);
        $pago_orden_cancel_user = $queryUserCancel ? $JwtAuth->desencriptarNombres($queryUserCancel->paterno, $queryUserCancel->materno, $queryUserCancel->nombre) : '';
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
        //cancelacion op_cancel
        "op_cancel" => (bool)$nomOrd->op_cancel,
        "pago_orden_cancel_user" => $nomOrd->op_cancel ? $pago_orden_cancel_user : '',
        "pago_orden_cancel_fecha_cont" => $nomOrd->op_cancel ? date('Y-m-d', $nomOrd->pago_orden_cancel_fecha_cont) : '',
        "pago_orden_cancel_comentarios" => $nomOrd->op_cancel ? $JwtAuth->desencriptar($nomOrd->pago_orden_cancel_comentarios) : ''
      );
      $ordenes_pago[] = $row_ordenPay;
      ++$id_list;
    }
    return $ordenes_pago;
  }

  public function listaGeneralDispersion(Request $request){
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
      
      $listOrdenes = DB::table('fnzs_pagos_orden as orden')
      ->join("vhum_nominas_main AS nomi", "orden.nomina_main", "=", "nomi.id")
      ->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
      ->whereIn('nomi.id', function ($query) {
        $query->select('nomina_main')->from('vhum_nominas_recibos');
      })
      ->where('orden.status_ordenPago',TRUE)
      ->where('emp.empresa_token', $empresa)
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('orden.id', 'desc')
      ->select('nomi.*','orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de dispersión de nómina registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $ordenes_pago_lista_general = $this->eachGeneralDisper($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista_general)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaPendientesDispersion(Request $request){
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
      
      $listOrdenes = DB::table('fnzs_pagos_orden as orden')
      ->join("vhum_nominas_main AS nomi", "orden.nomina_main", "=", "nomi.id")
    	->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
      ->whereIn('nomi.id', function ($query) {
        $query->select('nomina_main')->from('vhum_nominas_recibos');
      })
    	->where('orden.status_ordenPago',TRUE)
    	->where('orden.autorizacion_pay',FALSE)
    	->where('emp.empresa_token', $empresa)
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
    	->orderBy('orden.id', 'desc')
    	//->select('nomi.*','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->select('nomi.*','orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de dispersión de nómina registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $ordenes_pago_lista_general = $this->eachGeneralDisper($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista_general)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaLiberadasDispersion(Request $request){
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
      
      $listOrdenes = DB::table('fnzs_pagos_orden as orden')
      ->join("vhum_nominas_main AS nomi", "orden.nomina_main", "=", "nomi.id")
      ->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
      ->whereIn('nomi.id', function ($query) {
        $query->select('nomina_main')->from('vhum_nominas_recibos');
      })
      ->where('orden.status_ordenPago',TRUE)
      ->where('orden.autorizacion_pay',TRUE)
      ->where('orden.orden_terminada_bool',FALSE)
      ->where('emp.empresa_token', $empresa)
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('orden.id', 'desc')
      //->select('nomi.*','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->select('nomi.*','orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de dispersión de nómina registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $ordenes_pago_lista_general = $this->eachGeneralDisper($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista_general)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

	public function nominaDesgloseOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'token_ordenPago' => 'required|string',
      'token_nominas_periodos' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La información del periodo de nómina es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      date_default_timezone_set('America/Mexico_City');
      $token_ordenPago = $request->input('token_ordenPago');
			$token_nominas_periodos = $request->input('token_nominas_periodos');

			$queryNominaPagoOrden = DB::table("fnzs_pagos_orden AS order")
      ->join("vhum_nominas_main AS nomi", "order.nomina_main", "=", "nomi.id")
      ->join("vhum_nominas_recibos AS recibos", "nomi.id", "=", "recibos.nomina_main")
      ->join("main_empresas AS emp", "order.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "order.token_ordenPago" => $token_ordenPago,
        "nomi.token_nominas_periodos" => $token_nominas_periodos,
        "emp.empresa_token" => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select(
        "nomi.id AS nomi_id",
        "nomi.token_nominas_periodos",
        'recibos.trabajador AS nomi_trab',
        'recibos.token_nomina_recibo AS nomiRecId',
        'recibos.nomina_moneda',
        'recibos.total_efectivo',
        'order.autorizacion_pay',
        'order.orden_terminada_bool'
      )
      ->get();
  
      if ($queryNominaPagoOrden->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
		    $detalleNominaLista = array();
        $vhumNominasMap = $queryNominaPagoOrden->pluck('nomi_id')->filter()->unique()->toArray();
        $vhumNomiRecMap = $queryNominaPagoOrden->pluck('nomiRecId')->filter()->unique()->toArray();
        $idTrabajador = $queryNominaPagoOrden->pluck('nomi_trab')->filter()->unique()->toArray();
        
        $detNominaTrabMap = DB::table("vhum_empleados_catalogo")->whereIn('id',$idTrabajador)
        ->get()->keyBy('id');

        $detTrabNamesMap = DB::table("sos_personas")->whereIn('id',$detNominaTrabMap->pluck('empleado_name')->unique())
        ->get()->keyBy('id');

        $detTrabBankMap = DB::table("teci_bancos")->whereIn('id',$detNominaTrabMap->pluck('trabcuentabanc_banco')->unique())
        ->get()->keyBy('id');

        $pagosDoneMap = DB::table("fnzs_pagos_nomina_empleado_dispersion AS pay_nomi_disp")
        ->join("vhum_nominas_recibos AS recibos", "pay_nomi_disp.nomina_recibo", "=", "recibos.id")
        ->join("fnzs_pagos_pago AS pay", "pay_nomi_disp.pago_referenciado", "=", "pay.id")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->where("order.token_ordenPago",$token_ordenPago)
        ->where("vinc.vinculo_cancelado",FALSE)
        ->whereIn("recibos.token_nomina_recibo",$vhumNomiRecMap)
        ->whereIn("pay_nomi_disp.empleado_referenciado",$idTrabajador)
        ->whereIn("pay.vinc_nomina",$vhumNominasMap)
        ->select(
          'pay.token_pagos','pay.folio_pagos','pay.status_pagos','pay.folio_operacion','pay.fecha_pago','pay.fecha_contabilizacion',
          'pay.observacionesPago','pay.forma_pago_pago','pay.personal_pago','pay.personal_autoriza','pay.monto_pago','pay.p_moneda',
          'pay.tipo_cambio','pay.pago_autorizado','pay.fecha_pago_auth','pay.vinc_nomina AS nomi_id',

          'pay_nomi_disp.monto AS monto_dispersion','pay_nomi_disp.moneda AS moneda_dispersion',
          'pay_nomi_disp.empleado_referenciado AS nomi_trab',
          'recibos.token_nomina_recibo AS nomiRecId','recibos.*'
        )
        ->get()
        ->groupBy(function ($item) {
          return $item->nomiRecId.'_'.$item->nomi_trab.'_'.$item->nomi_id;
        });
        
        $pagPersonalIds = $pagosDoneMap->flatten()->pluck('personal_pago')
        ->merge($pagosDoneMap->flatten()->pluck('personal_autoriza'))
        ->unique()->filter();

        $mapPersPagoRel = DB::table("vhum_empleados_catalogo AS pers")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->whereIn('pers.id',$pagPersonalIds)
        ->select('pers.id','pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
        ->get()
        ->keyBy('id');
        
        // 2. Mapa de Formas de Pago (Cajas, Cuentas, Monederos)
        $pagosTokens = $pagosDoneMap->flatten()->pluck('token_pagos')->unique();
        
        $mapFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->whereIn("payment.token_pagos", $pagosTokens)
        ->select("r_caj.*","r_cuent.*","r_moned.*")
        ->get()
        ->groupBy('token_pagos');
        
        $mapMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_nomina AS vinc_nomi", "cfdi.id", "=", "vinc_nomi.comprobante_fiscal")
        ->join("vhum_nominas_main AS nomi", "vinc_nomi.nomina_main", "nomi.id")
        ->join("fnzs_pagos_orden AS order", "nomi.id", "order.nomina_main")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->whereNull("order.nomina_en_especie")
        ->whereIn("payment.token_pagos", $pagosTokens)
        ->select("cfdi.cfdi_comprobante_metodo_de_pago")
        ->get()
        ->keyBy('id');

        foreach ($queryNominaPagoOrden as $vNomRec) {
          $nomina_moneda_name = $vNomRec->nomina_moneda;
          $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vNomRec->nomina_moneda);
          $nomina_empleado_token = '';
          $folio_empleado = '';
          $trabajador_name = '';
          
          $nomina_empleado_cbankBancoToken = '';
          $nomina_empleado_cbankBancoNombre = '';
          $cuenta_descifrada = '';
          $cuenta_descifrada_last_digitos = '';
          $clabe_descifrada = '';
          $clabe_descifrada_last_digitos = '';
          $pagos_realizados_trabajador = 0;
          $pagosRealizadosTrabajadorNomina = [];

          $vNomiTrab = $detNominaTrabMap->get($vNomRec->nomi_trab);
          if ($vNomiTrab) {
            $nomina_empleado_token = $vNomiTrab->empleado_token;
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vNomiTrab->folio_pers).(!is_null($vNomiTrab->post_folio_pers) ? '-'.$vNomiTrab->post_folio_pers : '');
            
            $vTrabNames = $detTrabNamesMap->get($vNomiTrab->empleado_name);
            $trabajador_name = $vTrabNames ? ucwords($JwtAuth->desencriptar($vTrabNames->paterno))." ".
              ucwords($JwtAuth->desencriptar($vTrabNames->materno))." ".
              ucwords($JwtAuth->desencriptar($vTrabNames->nombre)) : '';

            $vTrabBank = $detTrabBankMap->get($vNomiTrab->trabcuentabanc_banco);
            $nomina_empleado_cbankBancoToken = $vTrabBank ? $vTrabBank->token_bancos : '';
            $nomina_empleado_cbankBancoNombre = $vTrabBank ? $vTrabBank->clave." ".$vTrabBank->nombre_comercial : '';

            if (!is_null($vNomiTrab->trabcuentabanc_cuenta) && $vNomiTrab->trabcuentabanc_cuenta != '') {
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_cuenta);
              $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_cuenta), -4);
              $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
            }
            
            if (!is_null($vNomiTrab->trabcuentabanc_clabe) && $vNomiTrab->trabcuentabanc_clabe != '') {
              $clabe_descifrada = $JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_clabe);
              $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_clabe), -4);
              $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
            }
          }

          $key_pag_done = $vNomRec->nomiRecId.'_'.$vNomRec->nomi_trab.'_'.$vNomRec->nomi_id;
          $queryPagosDone = $pagosDoneMap->get($key_pag_done) ?? collect([]);

          foreach ($queryPagosDone as $vPayNTrab) {
            $payment_observaciones = !is_null($vPayNTrab->observacionesPago) ? $JwtAuth->desencriptar($vPayNTrab->observacionesPago) : '';
            $status_pay_date = $vNomRec->autorizacion_pay && $vNomRec->orden_terminada_bool ? gmdate('Y-m-d H:i:s', $vNomRec->orden_terminada_fecha) : "---";
            $pagos_realizados_trabajador += $vPayNTrab->monto_dispersion;
            $forma_pago_registrada = $vPayNTrab->forma_pago_pago;
            
            //personal_pago
            $pPersPaga = $mapPersPagoRel->get($vPayNTrab->personal_pago);
            $p_paga_token = $pPersPaga ? $pPersPaga->empleado_token : "";
            $p_paga_folio = $pPersPaga ? "TRB-".$JwtAuth->generarFolio($pPersPaga->folio_pers) : "";
            $p_paga_name = $pPersPaga ? $JwtAuth->desencriptarNombres($pPersPaga->paterno,$pPersPaga->materno,$pPersPaga->nombre) : "";

            $pPersAuth = $mapPersPagoRel->get($vPayNTrab->personal_autoriza);
            $p_autoriza_token = $pPersAuth ? $pPersAuth->empleado_token : "";
            $p_autoriza_folio = $pPersAuth ? "TRB-".$JwtAuth->generarFolio($pPersAuth->folio_pers) : "";
            $p_autoriza_name = $pPersAuth ? $JwtAuth->desencriptarNombres($pPersAuth->paterno,$pPersAuth->materno,$pPersAuth->nombre) : "";

            $forma_pago_vinculada = "";

            //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();
            $queryFormasDePago = $mapFormasDePago->get($vPayNTrab->token_pagos) ?? collect([]);
            foreach ($queryFormasDePago as $vFPagoVinc) {
              if ($vFPagoVinc->token_caja !== null) {
                $forma_pago_vinculada = "Caja CAJ-".$JwtAuth->generarFolio($vFPagoVinc->no_caja);
              } elseif ($vFPagoVinc->token_cuenta !== null) {
                $forma_pago_vinculada = "Banco CUENT-".$JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
              } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
                $forma_pago_vinculada = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
              }
            }
            
            $cfdi_comprobante_metodo_de_pago = "";
            $queryMetodoPago = $mapMetodoPago->get($vPayNTrab->token_pagos);
            $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

            $row_pagos_realizados = array(
              "token_pagos" => $vPayNTrab->token_pagos,
              "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($vPayNTrab->folio_pagos),
              "status_pago" => $vPayNTrab->status_pagos ? true : false,
              "folio_operacion" => $vPayNTrab->folio_operacion,
              "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayNTrab->fecha_pago),
              "fecha_contabilizacion" => !empty($vPayNTrab->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vPayNTrab->fecha_contabilizacion) : "",
              "monto_pago_simple" => number_format($vPayNTrab->monto_pago,$JwtAuth->getMonedaAPI($vPayNTrab->p_moneda),'.', ''),
              "monto_pago" => "$".number_format($vPayNTrab->monto_pago,$JwtAuth->getMonedaAPI($vPayNTrab->p_moneda),'.', ',')." $vPayNTrab->p_moneda",
              "observacionesPago" => $payment_observaciones,
              "tipo_cambio" => "$".number_format($vPayNTrab->tipo_cambio,$JwtAuth->getMonedaAPI($vPayNTrab->p_moneda),'.',',')." $vPayNTrab->p_moneda",
              "p_moneda" => $vPayNTrab->p_moneda,
              //"destino" => $destino,
              "concepto" => !empty($vPayNTrab->concepto) ? $JwtAuth->desencriptar($vPayNTrab->concepto) : '',
              //forma_pago
              "forma_pago_vinculada" => $forma_pago_vinculada,
              "forma_pago_cfdi" => $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada),
              "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
              "personal_pago_token" => $p_paga_token,
              "personal_pago_folio" => $p_paga_folio,
              "personal_pago_name" => $p_paga_name,
              "pago_autorizado" => $vPayNTrab->pago_autorizado ? true : false,
              "fecha_pago_auth" => gmdate('Y-m-d H:i:s', $vPayNTrab->fecha_pago_auth),
              //personal_autoriza
              "personal_autoriza_token" => $p_autoriza_token,
              "personal_autoriza_folio" => $p_autoriza_folio,
              "personal_autoriza_name" => $p_autoriza_name,
            );
            $pagosRealizadosTrabajadorNomina[] = $row_pagos_realizados;
          }
          
          $pago_restante = count($pagosRealizadosTrabajadorNomina) > 0 ? $vNomRec->total_efectivo - $pagos_realizados_trabajador : $vNomRec->total_efectivo;

          $detNomRow = array(
            "token_nomina_periodos" => $vNomRec->token_nominas_periodos,
            "token_nomina_recibo" => $vNomRec->nomiRecId,
            "nomina_moneda_name" => $nomina_moneda_name,
            "nomina_empleado_token" => $nomina_empleado_token,
            "nomina_empleado_nombre" => "$folio_empleado $trabajador_name",
            "nomina_total_efectivo_simple" => $vNomRec->total_efectivo,
            "nomina_total_efectivo" => "$".number_format($vNomRec->total_efectivo,$nomina_moneda_decimales,'.',','),
            "nomina_empleado_cbankBancoToken" => $nomina_empleado_cbankBancoToken,
            "nomina_empleado_cbankBancoNombre" => $nomina_empleado_cbankBancoNombre,

            "nomina_empleado_cbankCuenta" => $cuenta_descifrada,
            "nomina_empleado_cbankCuentaMin" => $cuenta_descifrada_last_digitos,
            "cuenta_view" => false,
            "nomina_empleado_cbankCuentaClabeInter" => $clabe_descifrada,
            "nomina_empleado_cbankCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
            "clabe_inter_view" => false,
            "pagos_realizados" => number_format($pagos_realizados_trabajador, $nomina_moneda_decimales, '.', ''),
            "pagos_realizados_format" => number_format($pagos_realizados_trabajador, $nomina_moneda_decimales, '.', ',')." $nomina_moneda_name",
            "importe_restante" => number_format($pago_restante, $nomina_moneda_decimales, '.', ''),
            "importe_restante_format" => number_format($pago_restante, $nomina_moneda_decimales, '.', ',')." $nomina_moneda_name",
            "importe_por_pagar" => "0.00",
            "debe_simple" => number_format($pago_restante, $nomina_moneda_decimales, '.', ''),
            "debe_format" => "$".number_format($pago_restante, $nomina_moneda_decimales, '.', ',')." $nomina_moneda_name",
            "lista_pagos_realizados" => $pagosRealizadosTrabajadorNomina,
          );
          $detalleNominaLista[] = $detNomRow;
        }

				$dataMensaje = array(
					'desglose' => $detalleNominaLista,
					'code' => 200,
					'status' => 'success'
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function generaPagoNominaDispersion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'orden_pago_token' => 'required|string',
			'nomina_periodo_token' => 'required|string',
			'order_importe' => 'required|numeric',
			'fecha_contabilizacion' => 'required|string',
			'order_caja' => 'array',
			'order_cuenta_bancaria' => 'array',
			'order_monedero_electronico' => 'array',
			'saldo_a_favor' => 'required|string',
			'order_moneda' => 'required|string',
			'order_tipo_cambio' => 'required|numeric',
			'order_forma_pago' => 'required|string',
			'trabajadores_dispersados' => 'required|array',
			'order_observacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos recibidos para la dispersión de la nómina',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $orden_pago_token = $request->input('orden_pago_token');
      $nomina_periodo_token = $request->input('nomina_periodo_token');
      $order_importe = $request->input('order_importe');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $order_caja = $request->input('order_caja');
      $order_cuenta_bancaria = $request->input('order_cuenta_bancaria');
      $order_cuenta_monedero = $request->input('order_monedero_electronico');
      $saldo_a_favor = $request->input('saldo_a_favor');
      $order_moneda = $request->input('order_moneda');
      $order_tipo_cambio = $request->input('order_tipo_cambio');
      $order_forma_pago = $request->input('order_forma_pago');
      $trabajadores_dispersados = $request->input('trabajadores_dispersados');
      $order_observacion = $request->input('order_observacion');

      $valide_orden_pago = isset($orden_pago_token) && !empty($orden_pago_token);
      $valide_nomina_periodo = isset($nomina_periodo_token) && !empty($nomina_periodo_token);
      $valide_order_importe = isset($order_importe) && !empty($order_importe) && preg_match($JwtAuth->filtroCostoPrecio(),$order_importe);
      $valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $valide_order_caja = isset($order_caja) && !empty($order_caja);
      $valide_order_cuenta_bancaria = isset($order_cuenta_bancaria) && !empty($order_cuenta_bancaria);
      $valide_order_cuenta_monedero = isset($order_cuenta_monedero) && !empty($order_cuenta_monedero);
      $valide_saldo_a_favor = isset($saldo_a_favor) && !empty($saldo_a_favor) && preg_match($JwtAuth->filtroAlfaNumerico(),$saldo_a_favor);
      $valide_order_moneda = isset($order_moneda) && !empty($order_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$order_moneda);
      $valide_order_tipo_cambio = isset($order_tipo_cambio) && !empty($order_tipo_cambio) && preg_match($JwtAuth->filtroCostoPrecio(),$order_tipo_cambio);
      $valide_order_forma_pago = isset($order_forma_pago) && !empty($order_forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$order_forma_pago);
      $valide_trabajadores_dispersados = isset($trabajadores_dispersados) && !empty($trabajadores_dispersados) && count($trabajadores_dispersados) > 0;
      $valide_order_observacion = isset($order_observacion) && !empty($order_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $order_observacion);
      $fechaSistema = time();
      //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r'.$saldo_a_favor]);->whereNotnull

      if ($valide_orden_pago && $valide_nomina_periodo && $valide_order_importe && $valide_fecha_contabilizacion && $valide_saldo_a_favor && $valide_order_moneda && $valide_order_tipo_cambio && $valide_order_forma_pago && $valide_trabajadores_dispersados && $valide_order_observacion) {
        
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$empresa, $usuario]);
        foreach ($queryEmp as $vEmp) {
          date_default_timezone_set($vEmp->zona_horaria);

          $folioPagos = DB::select("SELECT IF (max(folio_pagos) IS NOT NULL,(max(folio_pagos)+1),1) AS folio FROM fnzs_pagos_pago AS payment JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE payment.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]
          );
          
          $tokenPago = $JwtAuth->encriptarToken($order_importe.$order_observacion.$fechaSistema);
          $folio_pago_generar = "PAY-".$JwtAuth->generarFolio($folioPagos[0]->folio);

          $idNomina = DB::table("vhum_nominas_main")->where("token_nominas_periodos",$nomina_periodo_token)->value("id");

          $concepto_pago = $JwtAuth->encriptar("Dispersión de nómina a trabajadores");

          $insertPagoMon = DB::table("fnzs_pagos_pago")
          ->insert(array(
            "token_pagos" => $tokenPago,
            "folio_pagos" => $folioPagos[0]->folio,
            "folio_operacion" => "",
            "fecha_sistema" => $fechaSistema,
            "fecha_pago" => time(),
            "fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
            "monto_pago" => $order_importe,
            "observacionesPago" => $JwtAuth->encriptar($order_observacion),
            "tipo_cambio" => $order_tipo_cambio,
            "p_moneda" => $order_moneda,
            "vinc_nomina" => $idNomina != "" ? $idNomina : NULL,
            "concepto" => $concepto_pago,
            "personal_pago" => $vEmp->userr,
            "pago_autorizado" => TRUE,
            "fecha_pago_auth" => time(),
            "personal_autoriza" => $vEmp->userr,
            "empresa" => $vEmp->id,
            "status_pagos" => TRUE,
            "fecha_deletePagos" => ''
          ));
          
          $id_pago_realizado = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("id");

          $insertPagoVinc = DB::table("fnzs_pagos_pago_ordenes_vinculadas")
          ->insert(array(
            "pago_realizado" => $id_pago_realizado,
            "orden_pago_vinculada" => DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_pago_token)->value("id"),
            "orden_pago_monto" => $order_importe
          ));

          $nomina_total_restante = collect($trabajadores_dispersados)->sum('debe_simple');

          if ($nomina_total_restante == 0) {
            DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_pago_token)->limit(1)->update(array(
              "orden_terminada_bool" => TRUE,
              "orden_terminada_fecha" => time(),
              "fecha_contabilizacion_ordenPago" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
            ));
          }

          if ($valide_order_caja && count($order_caja) > 0) {
            for ($i=0; $i < count($order_caja); $i++) { 
              $token_caja = $order_caja[$i]["token_caja"];
              $monto_aplicar = $order_caja[$i]["monto_aplicar"];
              $sql_caja = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
              $insertPagoCaja = DB::table("fnzs_pagos_cajas_pago")
              ->insert(
                array(
                  "pago_realizado" => $id_pago_realizado,
                  "caja_relacionada" => $sql_caja
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_caja,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => "R",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "caja" => $sql_caja,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $order_tipo_cambio,
                  "moneda_movimiento" => $order_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($order_observacion),
                  "pago" => $id_pago_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }

          if ($valide_order_cuenta_bancaria && count($order_cuenta_bancaria) > 0) {
            for ($i=0; $i < count($order_cuenta_bancaria); $i++) { 
              $token_cuenta = $order_cuenta_bancaria[$i]["token_cuenta"];
              $monto_aplicar = $order_cuenta_bancaria[$i]["monto_aplicar"];
              $sql_cuenta_bancaria = DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuenta)->value("id");
              $insertPagoCuenta = DB::table("fnzs_pagos_cuentas_pago")
              ->insert(
                array(
                  "pago_realizado" => $id_pago_realizado,
                  "cuenta_relacionada" => $sql_cuenta_bancaria
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_cuenta_bancaria,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => "R",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_bancaria" => $sql_cuenta_bancaria,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $order_tipo_cambio,
                  "moneda_movimiento" => $order_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($order_observacion),
                  "pago" => $id_pago_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }

          if ($valide_order_cuenta_monedero && count($order_cuenta_monedero) > 0) {
            for ($i=0; $i < count($order_cuenta_monedero); $i++) { 
              $token_cuentaMon = $order_cuenta_monedero[$i]["token_cuentaMon"];
              $monto_aplicar = $order_cuenta_monedero[$i]["monto_aplicar"];
              $sql_cuenta_monedero = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");

              $insertPagoCuenta = DB::table("fnzs_pagos_monederos_pago")
              ->insert(
                array(
                  "pago_realizado" => $id_pago_realizado,
                  "cuenta_relacionada" => $sql_cuenta_monedero
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_caja,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => "R",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_monedero" => $sql_cuenta_monedero,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $order_tipo_cambio,
                  "moneda_movimiento" => $order_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($order_observacion),
                  "pago" => $id_pago_realizado,
                  "empresa" => $vEmp->id
                )
              ); 
            }
          }

          if (count($trabajadores_dispersados) > 0) {
            for ($i=0; $i < count($trabajadores_dispersados); $i++) { 
              $token_nomina_recibo = $trabajadores_dispersados[$i]["token_nomina_recibo"];
              $nomina_moneda_name = $trabajadores_dispersados[$i]["nomina_moneda_name"];
              $nomina_empleado_token = $trabajadores_dispersados[$i]["nomina_empleado_token"];
              $importe_por_pagar = $trabajadores_dispersados[$i]["importe_por_pagar"];
              $importe_restante = $trabajadores_dispersados[$i]["debe_simple"];
              
              $insertDispersion = DB::table("fnzs_pagos_nomina_empleado_dispersion")
              ->insert(array(
                "folio_dispersion" => $i+1, 
                "pago_referenciado" => $id_pago_realizado, 
                "empleado_referenciado" => DB::table("vhum_empleados_catalogo")->where("empleado_token",$nomina_empleado_token)->value("id"), 
                "nomina_recibo" => DB::table("vhum_nominas_recibos")->where("token_nomina_recibo",$token_nomina_recibo)->value("id"), 
                "monto" => $importe_por_pagar, 
                "moneda" => $nomina_moneda_name, 
              ));
            }
          }

          $fecha_sistema_ordenp = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("fecha_sistema");
          $filepath = $vEmp->root_tkn . "/0003-fnzs/ordenes_pagos/$fecha_sistema_ordenp-$folio_pago_generar/pago_evidencias/";
          if (!file_exists(storage_path("/root/$filepath"))) {
            Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
          }

          if (!empty($_FILES['evidencias_pagos'])) {
            $evidencias = $_FILES["evidencias_pagos"];
            //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
            //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
            $string_name_evid = json_encode($_FILES["evidencias_pagos"]["name"]);
            if (count(json_decode($string_name_evid)) != 0) {
              $evidencia_nombre = json_decode($string_name_evid);
              for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                $temporal = $evidencias["tmp_name"][$doc];
                $doc_name = $evidencias["name"][$doc];
                Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PAY-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($id_pago_realizado,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(array(
                  "token_documento" => $token_documento,
                  "fecha_carga" => time(),
                  "modulo" => "pagos",
                  "folio_modulo" => "PAY-EVID" . $select_folio_doc[0]->folio,
                  "tipo_documento" => "an",
                  "nombre_documento" => $JwtAuth->encriptar($doc_name),
                  "pago" => $id_pago_realizado,
                  "status_documento" => TRUE,
                ));
              }
            }
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => '¡Pago realizado existosamente, revise su información y comuníquese con al área correspondiente al pago realizado!'
          );
        }
      } else {
        if (!$valide_orden_pago) $mensaje_error = "Error en orden de pago seleccionada seleccionada para pago, verifique su información";
        if (!$valide_nomina_periodo) $mensaje_error = "Error en nomina seleccionada para pago, verifique su información";
        if (!$valide_order_importe) $mensaje_error = "Error en importe de pago, verifique su información";
        if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
        if (!$valide_saldo_a_favor) $mensaje_error = "Error en saldo a favor de pago, verifique su información";
        if (!$valide_order_moneda) $mensaje_error = "Error en moneda seleccionada, verifique su información";
        if (!$valide_order_tipo_cambio) $mensaje_error = "Error en tipo de cambio, verifique su información";
        if (!$valide_order_forma_pago) $mensaje_error = "Error en forma de pago seleccionada, verifique su información";
        if (!$valide_trabajadores_dispersados) $mensaje_error = "Error en trabajadores seleccionados, verifique su información";
        if (!$valide_order_observacion) $mensaje_error = "Error en observaciones finales, verifique su información";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function nominaEspecieDesgloseOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_ordenPago' => 'required|string',
      'token_nominas_especie' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La información recibida es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      date_default_timezone_set('America/Mexico_City');
			$token_ordenPago = $request->input('token_ordenPago');
			$token_nominas_especie = $request->input('token_nominas_especie');
      
      $queryNominaPagoOrden = DB::table("fnzs_pagos_orden AS order")
      ->join("vhum_nominas_especie AS nesp", "order.nomina_en_especie", "=", "nesp.id")
      ->join("vhum_nominas_especie_desglose AS desg_nesp", "nesp.id", "=", "desg_nesp.nomina_especie")
      ->join("main_empresas AS emp", "order.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "order.token_ordenPago" => $token_ordenPago,
        "nesp.token_nominas_especie" => $token_nominas_especie,
        "emp.empresa_token" => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select(
        "nesp.id AS nesp_id",
        "nesp.token_nominas_especie",
        'desg_nesp.trabajador AS desg_trab',
        'desg_nesp.token_especie_desglose AS nomiEspId',
        'desg_nesp.nomina_esp_moneda',
        'desg_nesp.total_en_especie',
        'order.autorizacion_pay',
        'order.orden_terminada_bool'
      )
      ->get();  
  
      if ($queryNominaPagoOrden->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $detalleNominaLista = array();
        $vhumNominasMap = $queryNominaPagoOrden->pluck('nesp_id')->filter()->unique()->toArray();
        $vhumNomiEspMap = $queryNominaPagoOrden->pluck('nomiEspId')->filter()->unique()->toArray();
        $idTrabajador = $queryNominaPagoOrden->pluck('desg_trab')->filter()->unique()->toArray();

        $detNominaTrabMap = DB::table("vhum_empleados_catalogo")->whereIn('id',$idTrabajador)
        ->get()->keyBy('id');

        $detTrabNamesMap = DB::table("sos_personas")->whereIn('id',$detNominaTrabMap->pluck('empleado_name')->unique())
        ->get()->keyBy('id');

        $detTrabBankMap = DB::table("teci_bancos")->whereIn('id',$detNominaTrabMap->pluck('trabcuentabanc_banco')->unique())
        ->get()->keyBy('id');
        
        $pagosDoneMap = DB::table("fnzs_pagos_nomina_empleado_especie AS pay_nomi_espe")
        ->join("vhum_nominas_especie_desglose AS desg_nesp", "pay_nomi_espe.nomina_especie", "=", "desg_nesp.id")
        ->join("fnzs_pagos_pago AS pay", "pay_nomi_espe.pago_referenciado", "=", "pay.id")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->where("order.token_ordenPago",$token_ordenPago)
        ->whereIn("desg_nesp.token_especie_desglose",$vhumNomiEspMap)
        ->whereIn("pay_nomi_espe.empleado_referenciado",$idTrabajador)
        ->whereIn("pay.vinc_nomina_especie",$vhumNominasMap)
        ->select(
          'pay.token_pagos','pay.folio_pagos','pay.status_pagos','pay.folio_operacion','pay.fecha_pago','pay.fecha_contabilizacion',
          'pay.observacionesPago','pay.forma_pago_pago','pay.personal_pago','pay.personal_autoriza','pay.monto_pago','pay.p_moneda',
          'pay.tipo_cambio','pay.pago_autorizado','pay.fecha_pago_auth','pay.vinc_nomina_especie AS nomi_id',

          'pay_nomi_espe.monto AS monto_especie',
          'pay_nomi_espe.moneda AS moneda_especie',
          'pay_nomi_espe.empleado_referenciado AS nomi_trab',
          'desg_nesp.token_especie_desglose AS nomiEspId',
          'vinc.orden_pago_monto',
          'desg_nesp.*'
        )
        ->get()
        ->groupBy(function ($item) {
          return $item->nomiEspId.'_'.$item->nomi_trab.'_'.$item->nomi_id;
        });

        $pagPersonalIds = $pagosDoneMap->flatten()->pluck('personal_pago')
        ->merge($pagosDoneMap->flatten()->pluck('personal_autoriza'))
        ->unique()->filter();

        $mapPersPagoRel = DB::table("vhum_empleados_catalogo AS pers")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->whereIn('pers.id',$pagPersonalIds)
        ->select('pers.id','pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
        ->get()
        ->keyBy('id');
        
        // 2. Mapa de Formas de Pago (Cajas, Cuentas, Monederos)
        $pagosTokens = $pagosDoneMap->flatten()->pluck('token_pagos')->unique();
        
        $mapFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->whereIn("payment.token_pagos", $pagosTokens)
        ->select("r_caj.*","r_cuent.*","r_moned.*")
        ->get()
        ->groupBy('token_pagos');
        
        $mapMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_nomina AS vinc_nomi", "cfdi.id", "=", "vinc_nomi.comprobante_fiscal")
        ->join("vhum_nominas_main AS nomi", "vinc_nomi.nomina_main", "nomi.id")
        ->join("fnzs_pagos_orden AS order", "nomi.id", "order.nomina_main")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->whereNotNull("order.nomina_en_especie")
        ->whereIn("payment.token_pagos", $pagosTokens)
        ->select("cfdi.cfdi_comprobante_metodo_de_pago")
        ->get()
        ->keyBy('id');

        foreach ($queryNominaPagoOrden as $vNomDesg) {
          $nomina_esp_moneda_name = $vNomDesg->nomina_esp_moneda;
          $nomina_esp_moneda_decimales = $JwtAuth->getMonedaAPI($vNomDesg->nomina_esp_moneda);
          $nomina_empleado_token = '';
          $folio_empleado = '';
          $trabajador_name = '';

          $nomina_empleado_cbankBancoToken = '';
          $nomina_empleado_cbankBancoNombre = '';
          $cuenta_descifrada = '';
          $cuenta_descifrada_last_digitos = '';
          $clabe_descifrada = '';
          $clabe_descifrada_last_digitos = '';
          $pagos_realizados_trabajador = 0;
          $pagosRealizadosTrabajadorNomina = [];

          $vNomiTrab = $detNominaTrabMap->get($vNomDesg->desg_trab);
          if ($vNomiTrab) {
            $nomina_empleado_token = $vNomiTrab->empleado_token;
            //echo "nomina_empleado_token ".$nomina_empleado_token;
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vNomiTrab->folio_pers).(!is_null($vNomiTrab->post_folio_pers) ? '-'.$vNomiTrab->post_folio_pers : '');

            $vTrabNames = $detTrabNamesMap->get($vNomiTrab->empleado_name);
            $trabajador_name = $vTrabNames ? ucwords($JwtAuth->desencriptar($vTrabNames->paterno))." ".
              ucwords($JwtAuth->desencriptar($vTrabNames->materno))." ".
              ucwords($JwtAuth->desencriptar($vTrabNames->nombre)) : '';
            
            $vTrabBank = $detTrabBankMap->get($vNomiTrab->trabcuentabanc_banco);
            $nomina_empleado_cbankBancoToken = $vTrabBank ? $vTrabBank->token_bancos : '';
            $nomina_empleado_cbankBancoNombre = $vTrabBank ? $vTrabBank->clave." ".$vTrabBank->nombre_comercial : '';

            if (!is_null($vNomiTrab->trabcuentabanc_cuenta) && $vNomiTrab->trabcuentabanc_cuenta != '') {
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_cuenta);
              $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_cuenta), -4);
              $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
            }
            
            if (!is_null($vNomiTrab->trabcuentabanc_clabe) && $vNomiTrab->trabcuentabanc_clabe != '') {
              $clabe_descifrada = $JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_clabe);
              $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomiTrab->trabcuentabanc_clabe), -4);
              $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
            }
          }

          $key_pag_done = $vNomDesg->nomiEspId.'_'.$vNomDesg->desg_trab.'_'.$vNomDesg->nesp_id;
          $queryPagosDone = $pagosDoneMap->get($key_pag_done) ?? collect([]);

          foreach ($queryPagosDone as $vPayNTrab) {
            $payment_observaciones = !is_null($vPayNTrab->observacionesPago) ? $JwtAuth->desencriptar($vPayNTrab->observacionesPago) : '';
            $status_pay_date = $vNomDesg->autorizacion_pay && $vNomDesg->orden_terminada_bool ? gmdate('Y-m-d H:i:s', $vNomDesg->orden_terminada_fecha) : "---";
            $pagos_realizados_trabajador += $vPayNTrab->orden_pago_monto;

            $forma_pago_registrada = $vPayNTrab->forma_pago_pago;
            
            //personal_pago
            $pPersPaga = $mapPersPagoRel->get($vPayNTrab->personal_pago);
            $p_paga_token = $pPersPaga ? $pPersPaga->empleado_token : "";
            $p_paga_folio = $pPersPaga ? "TRB-".$JwtAuth->generarFolio($pPersPaga->folio_pers) : "";
            $p_paga_name = $pPersPaga ? $JwtAuth->desencriptarNombres($pPersPaga->paterno,$pPersPaga->materno,$pPersPaga->nombre) : "";

            $pPersAuth = $mapPersPagoRel->get($vPayNTrab->personal_autoriza);
            $p_autoriza_token = $pPersAuth ? $pPersAuth->empleado_token : "";
            $p_autoriza_folio = $pPersAuth ? "TRB-".$JwtAuth->generarFolio($pPersAuth->folio_pers) : "";
            $p_autoriza_name = $pPersAuth ? $JwtAuth->desencriptarNombres($pPersAuth->paterno,$pPersAuth->materno,$pPersAuth->nombre) : "";

            $forma_pago_vinculada = "";
            //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();
            $queryFormasDePago = $mapFormasDePago->get($vPayNTrab->token_pagos) ?? collect([]);
            foreach ($queryFormasDePago as $vFPagoVinc) {
              if ($vFPagoVinc->token_caja !== null) {
                $forma_pago_vinculada = "Caja CAJ-".$JwtAuth->generarFolio($vFPagoVinc->no_caja);
              } elseif ($vFPagoVinc->token_cuenta !== null) {
                $forma_pago_vinculada = "Banco CUENT-".$JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
              } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
                $forma_pago_vinculada = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
              }
            }
            
            $cfdi_comprobante_metodo_de_pago = "";
            $queryMetodoPago = $mapMetodoPago->get($vPayNTrab->token_pagos);
            $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

            $row_pagos_realizados = array(
              "token_pagos" => $vPayNTrab->token_pagos,
              "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($vPayNTrab->folio_pagos),
              "status_pago" => $vPayNTrab->status_pagos ? true : false,
              "folio_operacion" => $vPayNTrab->folio_operacion,
              "fecha_pago" => gmdate('Y-m-d H:i:s', $vPayNTrab->fecha_pago),
              "fecha_contabilizacion" => !empty($vPayNTrab->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vPayNTrab->fecha_contabilizacion) : "",
              "monto_pago_simple" => number_format($vPayNTrab->monto_pago,$JwtAuth->getMonedaAPI($vPayNTrab->p_moneda),'.', ''),
              "monto_pago" => "$".number_format($vPayNTrab->monto_pago,$JwtAuth->getMonedaAPI($vPayNTrab->p_moneda),'.', ',')." $vPayNTrab->p_moneda",
              "observacionesPago" => $payment_observaciones,
              "tipo_cambio" => "$".number_format($vPayNTrab->tipo_cambio,$JwtAuth->getMonedaAPI($vPayNTrab->p_moneda),'.',',')." $vPayNTrab->p_moneda",
              "p_moneda" => $vPayNTrab->p_moneda,
              //"destino" => $destino,
              "concepto" => !empty($vPayNTrab->concepto) ? $JwtAuth->desencriptar($vPayNTrab->concepto) : '',
              //forma_pago
              "forma_pago_vinculada" => $forma_pago_vinculada,
              "forma_pago_cfdi" => $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada),
              "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
              ////proveedor
              //"proveedor_token" => $proveedor_token,
              //"proveedor_name" => "$proveedor_folio - $proveedor_name",
              ////cliente
              //"cliente_token" => $cliente_token,
              //"cliente_name" => "$cliente_folio - $cliente_name",
              ////empleado
              //"empleado_token" => $empleado_token,
              //"empleado_name" => "$empleado_folio - $empleado_name",
              ////acreedor
              //"acreedor_token" => $acreedor_token,
              //"acreedor_name" => "$acreedor_folio - $acreedor_name",
              //personal_pago
              "personal_pago_token" => $p_paga_token,
              "personal_pago_folio" => $p_paga_folio,
              "personal_pago_name" => $p_paga_name,
              "pago_autorizado" => $vPayNTrab->pago_autorizado ? true : false,
              "fecha_pago_auth" => gmdate('Y-m-d H:i:s', $vPayNTrab->fecha_pago_auth),
              //personal_autoriza
              "personal_autoriza_token" => $p_autoriza_token,
              "personal_autoriza_folio" => $p_autoriza_folio,
              "personal_autoriza_name" => $p_autoriza_name,
            );
            $pagosRealizadosTrabajadorNomina[] = $row_pagos_realizados;
          }

          $pago_restante = count($queryPagosDone) > 0 ? $vNomDesg->total_en_especie - $pagos_realizados_trabajador : $vNomDesg->total_en_especie;

          $detNomRow = array(
            "token_nomina_especie" => $vNomDesg->token_nominas_especie,
            "token_especie_desglose" => $vNomDesg->nomiEspId,
            "nomina_esp_moneda_name" => $nomina_esp_moneda_name,
            "nomina_empleado_token" => $nomina_empleado_token,
            "nomina_empleado_nombre" => "$folio_empleado $trabajador_name",
            "nomina_total_en_especie_simple" => $vNomDesg->total_en_especie,
            "nomina_total_en_especie" => "$".number_format($vNomDesg->total_en_especie,$nomina_esp_moneda_decimales,'.',','),
            "nomina_empleado_cbankBancoToken" => $nomina_empleado_cbankBancoToken,
            "nomina_empleado_cbankBancoNombre" => $nomina_empleado_cbankBancoNombre,

            "nomina_empleado_cbankCuenta" => $cuenta_descifrada,
            "nomina_empleado_cbankCuentaMin" => $cuenta_descifrada_last_digitos,
            "cuenta_view" => false,
            "nomina_empleado_cbankCuentaClabeInter" => $clabe_descifrada,
            "nomina_empleado_cbankCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
            "clabe_inter_view" => false,
            "pagos_realizados" => number_format($pagos_realizados_trabajador, $nomina_esp_moneda_decimales, '.', ''),
            "pagos_realizados_format" => number_format($pagos_realizados_trabajador, $nomina_esp_moneda_decimales, '.', ',')." $nomina_esp_moneda_name",
            "importe_restante" => number_format($pago_restante, $nomina_esp_moneda_decimales, '.', ''),
            "importe_restante_format" => number_format($pago_restante, $nomina_esp_moneda_decimales, '.', ',')." $nomina_esp_moneda_name",
            "importe_por_pagar" => "0.00",
            "debe_simple" => number_format($pago_restante, $nomina_esp_moneda_decimales, '.', ''),
            "debe_format" => "$".number_format($pago_restante, $nomina_esp_moneda_decimales, '.', ',')." $nomina_esp_moneda_name",
            "lista_pagos_realizados" => $pagosRealizadosTrabajadorNomina,
          );
          $detalleNominaLista[] = $detNomRow;
        }

				$dataMensaje = array(
					'desglose' => $detalleNominaLista,
					'code' => 200,
					'status' => 'success'
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function generaPagoNominaEspecie(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'orden_pago_token' => 'required|string',
			'nomina_especie_token' => 'required|string',
			'order_importe' => 'required|numeric',
			'fecha_contabilizacion' => 'required|string',
			'order_caja' => 'array',
			'order_cuenta_bancaria' => 'array',
			'order_monedero_electronico' => 'array',
			'saldo_a_favor' => 'required|string',
			'order_moneda' => 'required|string',
			'order_tipo_cambio' => 'required|numeric',
			'order_forma_pago' => 'required|string',
			'trabajadores_dispersados' => 'required|array',
			'order_observacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La información recibida es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $orden_pago_token = $request->input('orden_pago_token');
      $nomina_especie_token = $request->input('nomina_especie_token');
      $order_importe = $request->input('order_importe');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $order_caja = $request->input('order_caja');
      $order_cuenta_bancaria = $request->input('order_cuenta_bancaria');
      $order_cuenta_monedero = $request->input('order_monedero_electronico');
      $saldo_a_favor = $request->input('saldo_a_favor');
      $order_moneda = $request->input('order_moneda');
      $order_tipo_cambio = $request->input('order_tipo_cambio');
      $order_forma_pago = $request->input('order_forma_pago');
      $trabajadores_dispersados = $request->input('trabajadores_dispersados');
      $order_observacion = $request->input('order_observacion');

      $valide_orden_pago = isset($orden_pago_token) && !empty($orden_pago_token);
      $valide_nomina_especie = isset($nomina_especie_token) && !empty($nomina_especie_token);
      $valide_order_importe = isset($order_importe) && !empty($order_importe) && preg_match($JwtAuth->filtroCostoPrecio(),$order_importe);
      $valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $valide_order_caja = isset($order_caja) && !empty($order_caja);
      $valide_order_cuenta_bancaria = isset($order_cuenta_bancaria) && !empty($order_cuenta_bancaria);
      $valide_order_cuenta_monedero = isset($order_cuenta_monedero) && !empty($order_cuenta_monedero);
      $valide_saldo_a_favor = isset($saldo_a_favor) && !empty($saldo_a_favor) && preg_match($JwtAuth->filtroAlfaNumerico(),$saldo_a_favor);
      $valide_order_moneda = isset($order_moneda) && !empty($order_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$order_moneda);
      $valide_order_tipo_cambio = isset($order_tipo_cambio) && !empty($order_tipo_cambio) && preg_match($JwtAuth->filtroCostoPrecio(),$order_tipo_cambio);
      $valide_order_forma_pago = isset($order_forma_pago) && !empty($order_forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$order_forma_pago);
      $valide_trabajadores_dispersados = isset($trabajadores_dispersados) && !empty($trabajadores_dispersados) && count($trabajadores_dispersados) > 0;
      $valide_order_observacion = isset($order_observacion) && !empty($order_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $order_observacion);
      $fechaSistema = time();
      //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r']);

      if ($valide_orden_pago && $valide_nomina_especie && $valide_order_importe && $valide_fecha_contabilizacion && $valide_saldo_a_favor && $valide_order_moneda && $valide_order_tipo_cambio && $valide_order_forma_pago && $valide_trabajadores_dispersados && $valide_order_observacion) {
        
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$empresa, $usuario]);
        foreach ($queryEmp as $vEmp) {
          DB::beginTransaction();
          try {
            date_default_timezone_set($vEmp->zona_horaria);
  
            $folioPagos = DB::select("SELECT IF (max(folio_pagos) IS NOT NULL,(max(folio_pagos)+1),1) AS folio FROM fnzs_pagos_pago AS payment JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE payment.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$empresa, $usuario]
            );
            
            $tokenPago = $JwtAuth->encriptarToken($order_importe.$order_observacion.$fechaSistema);
            $folio_pago_generar = "PAY-".$JwtAuth->generarFolio($folioPagos[0]->folio);
  
            $idNominaEspecie = DB::table("vhum_nominas_especie")->where("token_nominas_especie",$nomina_especie_token)->value("id");
  
            $concepto_pago = $JwtAuth->encriptar("Pago de nómina en especie a trabajadores");
            //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.2r']);
  
            $insertPagoMon = DB::table("fnzs_pagos_pago")
            ->insert(array(
              "token_pagos" => $tokenPago,
              "folio_pagos" => $folioPagos[0]->folio,
              "folio_operacion" => "",
              "fecha_sistema" => $fechaSistema,
              "fecha_pago" => time(),
              "fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              "monto_pago" => $order_importe,
              "observacionesPago" => $JwtAuth->encriptar($order_observacion),
              "tipo_cambio" => $order_tipo_cambio,
              "p_moneda" => $order_moneda,
              "vinc_nomina_especie" => $idNominaEspecie != "" ? $idNominaEspecie : NULL,
              "concepto" => $concepto_pago,
              "personal_pago" => $vEmp->userr,
              "pago_autorizado" => TRUE,
              "fecha_pago_auth" => time(),
              "personal_autoriza" => $vEmp->userr,
              "empresa" => $vEmp->id,
              "status_pagos" => TRUE,
              "fecha_deletePagos" => ''
            ));
            
            $id_pago_realizado = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("id");
  
            $insertPagoVinc = DB::table("fnzs_pagos_pago_ordenes_vinculadas")
            ->insert(array(
              "pago_realizado" => $id_pago_realizado,
              "orden_pago_vinculada" => DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_pago_token)->value("id"),
              "orden_pago_monto" => $order_importe
            ));
            
            $nomina_total_restante = collect($trabajadores_dispersados)->sum('debe_simple');
  
            if ($nomina_total_restante == 0) {
              $terminaReembolso = DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_pago_token)->limit(1)->update(array(
                "orden_terminada_bool" => TRUE,
                "orden_terminada_fecha" => time(),
                "fecha_contabilizacion_ordenPago" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              ));
            }
  
            if ($valide_order_caja && count($order_caja) > 0) {
              for ($cja=0; $cja < count($order_caja); $cja++) { 
                $token_caja = $order_caja[$cja]["token_caja"];
                $monto_aplicar = $order_caja[$cja]["monto_aplicar"];
                $sql_caja = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
                $insertPagoCaja = DB::table("fnzs_pagos_cajas_pago")
                ->insert(
                  array(
                    "pago_realizado" => $id_pago_realizado,
                    "caja_relacionada" => $sql_caja
                  )
                );
  
                $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                  AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
  
                $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_caja,$folioMovimiento[0]->folio);
  
                $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
                ->insert(
                  array(
                    "token_movimiento" => $token_movimiento,
                    "folio_movimiento" => $folioMovimiento[0]->folio,
                    "fecha_sistema" => time(),
                    "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "tipo_movimiento" => "R",
                    "subtipo_movimiento" => "C",
                    "responsable" => $vEmp->userr,
                    "caja" => $sql_caja,
                    "monto_aplicado" => $monto_aplicar,
                    "tipo_cambio_movimiento" => $order_tipo_cambio,
                    "moneda_movimiento" => $order_moneda,
                    "observaciones_movimiento" => $JwtAuth->encriptar($order_observacion),
                    "pago" => $id_pago_realizado,
                    "empresa" => $vEmp->id
                  )
                );
  
              }
            }
  
            if ($valide_order_cuenta_bancaria && count($order_cuenta_bancaria) > 0) {
              for ($cbk=0; $cbk < count($order_cuenta_bancaria); $cbk++) { 
                $token_cuenta = $order_cuenta_bancaria[$cbk]["token_cuenta"];
                $monto_aplicar = $order_cuenta_bancaria[$cbk]["monto_aplicar"];
                $sql_cuenta_bancaria = DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuenta)->value("id");
                $insertPagoCuenta = DB::table("fnzs_pagos_cuentas_pago")
                ->insert(
                  array(
                    "pago_realizado" => $id_pago_realizado,
                    "cuenta_relacionada" => $sql_cuenta_bancaria
                  )
                );
  
                $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                  AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
  
                $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_cuenta_bancaria,$folioMovimiento[0]->folio);
  
                $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
                ->insert(
                  array(
                    "token_movimiento" => $token_movimiento,
                    "folio_movimiento" => $folioMovimiento[0]->folio,
                    "fecha_sistema" => time(),
                    "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "tipo_movimiento" => "R",
                    "subtipo_movimiento" => "C",
                    "responsable" => $vEmp->userr,
                    "cuenta_bancaria" => $sql_cuenta_bancaria,
                    "monto_aplicado" => $monto_aplicar,
                    "tipo_cambio_movimiento" => $order_tipo_cambio,
                    "moneda_movimiento" => $order_moneda,
                    "observaciones_movimiento" => $JwtAuth->encriptar($order_observacion),
                    "pago" => $id_pago_realizado,
                    "empresa" => $vEmp->id
                  )
                );
              }
            }
  
            if ($valide_order_cuenta_monedero && count($order_cuenta_monedero) > 0) {
              for ($cMnd=0; $cMnd < count($order_cuenta_monedero); $cMnd++) { 
                $token_cuentaMon = $order_cuenta_monedero[$cMnd]["token_cuentaMon"];
                $monto_aplicar = $order_cuenta_monedero[$cMnd]["monto_aplicar"];
                $sql_cuenta_monedero = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");
  
                $insertPagoCuenta = DB::table("fnzs_pagos_monederos_pago")
                ->insert(
                  array(
                    "pago_realizado" => $id_pago_realizado,
                    "cuenta_relacionada" => $sql_cuenta_monedero
                  )
                );
  
                $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                  AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
  
                $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_cuenta_monedero,$folioMovimiento[0]->folio);
  
                $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
                ->insert(
                  array(
                    "token_movimiento" => $token_movimiento,
                    "folio_movimiento" => $folioMovimiento[0]->folio,
                    "fecha_sistema" => time(),
                    "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "tipo_movimiento" => "R",
                    "subtipo_movimiento" => "C",
                    "responsable" => $vEmp->userr,
                    "cuenta_monedero" => $sql_cuenta_monedero,
                    "monto_aplicado" => $monto_aplicar,
                    "tipo_cambio_movimiento" => $order_tipo_cambio,
                    "moneda_movimiento" => $order_moneda,
                    "observaciones_movimiento" => $JwtAuth->encriptar($order_observacion),
                    "pago" => $id_pago_realizado,
                    "empresa" => $vEmp->id
                  )
                ); 
              }
            }
  
            if (count($trabajadores_dispersados) > 0) {
              for ($trd=0; $trd < count($trabajadores_dispersados); $trd++) { 
                //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.2r'.$trabajadores_dispersados[$trd]["token_especie_desglose"]]);
                $token_especie_desglose = $trabajadores_dispersados[$trd]["token_especie_desglose"];//"OVJrMHdjNEtJZG1hTWhDY1doS1hIQT09OjoxMjM0NTY3ODEyMzQ1Njc4";//
                $nomina_esp_moneda_name = $trabajadores_dispersados[$trd]["nomina_esp_moneda_name"];
                $nomina_empleado_token = $trabajadores_dispersados[$trd]["nomina_empleado_token"];
                $importe_por_pagar = $trabajadores_dispersados[$trd]["importe_por_pagar"];
                $importe_restante = $trabajadores_dispersados[$trd]["debe_simple"];
                
                $queryNominaRecibo = DB::table("vhum_nominas_recibos AS recibos")
                ->join("vhum_empleados_catalogo AS trab", "recibos.trabajador", "=", "trab.id")
                ->join("vhum_nominas_main AS nomi", "recibos.nomina_main", "=", "nomi.id")
                ->join("fnzs_pagos_orden AS order", "nomi.id", "=", "order.nomina_main")
                ->join("vhum_nominas_especie AS nesp", "order.nomina_en_especie", "=", "nesp.id")
                ->join("vhum_nominas_especie_desglose AS desg_esp", "nesp.id", "=", "desg_esp.nomina_especie")
                ->where('trab.empleado_token',$nomina_empleado_token)
                ->where('order.token_ordenPago',$orden_pago_token)
                ->where('nesp.token_nominas_especie',$nomina_especie_token)
                ->where('desg_esp.token_especie_desglose',$token_especie_desglose)
                ->select('recibos.id')
                ->first();
  
                $insertDispersion = DB::table("fnzs_pagos_nomina_empleado_especie")
                ->insert(array(
                  "folio_pago_especie" => $trd+1,
                  "pago_referenciado" => $id_pago_realizado,
                  "empleado_referenciado" => DB::table("vhum_empleados_catalogo")->where("empleado_token",$nomina_empleado_token)->value("id"), 
                  "nomina_recibo" => $queryNominaRecibo->id,
                  "nomina_especie" => DB::table("vhum_nominas_especie_desglose")->where("token_especie_desglose",$token_especie_desglose)->value("id"),
                  "monto" => $importe_por_pagar, 
                  "moneda" => $nomina_esp_moneda_name, 
                ));
              }
            }
  
            $fecha_sistema_ordenp = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("fecha_sistema");
            $filepath = $vEmp->root_tkn . "/0003-fnzs/ordenes_pagos/$fecha_sistema_ordenp-$folio_pago_generar/pago_evidencias/";
            if (!file_exists(storage_path("/root/$filepath"))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }
  
            if (!empty($_FILES['evidencias_pagos'])) {
              $evidencias = $_FILES["evidencias_pagos"];
              //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
              $string_name_evid = json_encode($_FILES["evidencias_pagos"]["name"]);
              if (count(json_decode($string_name_evid)) != 0) {
                $evidencia_nombre = json_decode($string_name_evid);
                for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                  $temporal = $evidencias["tmp_name"][$doc];
                  $doc_name = $evidencias["name"][$doc];
                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PAY-EVID%'");
                  $token_documento = $JwtAuth->encriptarToken($id_pago_realizado,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                  $insertDocSoli = DB::table("sos_documentos")->insert(array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "PAY-EVID" . $select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "pago" => $id_pago_realizado,
                    "status_documento" => TRUE,
                  ));
                }
              }
            }
              
            DB::commit();

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => '¡Pago realizado existosamente, revise su información y comuníquese con al área correspondiente al pago realizado!'
            );
          } catch (\Exception $e) {
            // 7. Si algo falla, revertimos TODO en la BD
            DB::rollBack();
            // Opcional: Borrar carpetas físicas creadas en este intento
            // Storage::disk('root')->deleteDirectory($filepath);
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en el registro: ' . $e->getMessage(),
              'line' => $e->getLine()
            );
          }
        }
      } else {
        if (!$valide_orden_pago) $mensaje_error = "Error en orden de pago seleccionada seleccionada para pago, verifique su información";
        if (!$valide_nomina_especie) $mensaje_error = "Error en nomina seleccionada para pago, verifique su información";
        if (!$valide_order_importe) $mensaje_error = "Error en importe de pago, verifique su información";
        if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
        if (!$valide_saldo_a_favor) $mensaje_error = "Error en saldo a favor de pago, verifique su información";
        if (!$valide_order_moneda) $mensaje_error = "Error en moneda seleccionada, verifique su información";
        if (!$valide_order_tipo_cambio) $mensaje_error = "Error en tipo de cambio, verifique su información";
        if (!$valide_order_forma_pago) $mensaje_error = "Error en forma de pago seleccionada, verifique su información";
        if (!$valide_trabajadores_dispersados) $mensaje_error = "Error en trabajadores seleccionados, verifique su información";
        if (!$valide_order_observacion) $mensaje_error = "Error en observaciones finales, verifique su información";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  public function listaConcluidasDispersion(Request $request){
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
      
      $listOrdenes = DB::table('fnzs_pagos_orden as orden')
      ->join("vhum_nominas_main AS nomi", "orden.nomina_main", "=", "nomi.id")
      ->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
      ->whereIn('nomi.id', function ($query) {
        $query->select('nomina_main')->from('vhum_nominas_recibos');
      })
      ->where('orden.status_ordenPago',TRUE)
      ->where('orden.autorizacion_pay',TRUE)
      ->where('orden.orden_terminada_bool',TRUE)
      ->where('orden.orden_bloqueada',FALSE)
      ->where('emp.empresa_token', $empresa)
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('orden.id', 'desc')
      //->select('nomi.*','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->select('nomi.*','orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de dispersión de nómina registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $ordenes_pago_lista_general = $this->eachGeneralDisper($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista_general)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

	public function catalogoPagosDone(Request $request){
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
      date_default_timezone_set('America/Mexico_City');
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
  
			$queryPagos = DB::table("fnzs_pagos_pago AS payment")
			->join("main_empresas AS emp", "payment.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
			->where(function ($n){
        $n->whereNotNull("payment.vinc_nomina")
        ->orwhereNotNull("payment.vinc_nomina_especie");
      })
			->where([
        "payment.pago_autorizado" => TRUE,
			  "payment.status_pagos" => TRUE,
			  "emp.empresa_token" => $empresa,
			  "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("payment.fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("payment.folio_pagos", "DESC")
      ->get();

      if ($queryPagos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $lista_pagos = array();
				foreach ($queryPagos as $pay) {
					$queryDocAnterior = DB::table("fnzs_pagos_orden AS order")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
          ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select("order.folio_ordenPago","order.fecha_contabilizacion_ordenPago")
          ->first();
          $doc_anterior_folio = $queryDocAnterior ? "ORDP-".$JwtAuth->generarFolio($queryDocAnterior->folio_ordenPago) : '';
          $doc_anterior_fecha_contabilizacion = $queryDocAnterior ? gmdate('Y-m-d H:i:s',$queryDocAnterior->fecha_contabilizacion_ordenPago) : '';

					if (!is_null($pay->vinc_proveedor)) {
						$destino = "proveedor";
					} elseif (!is_null($pay->vinc_cliente)) {
						$destino = "cliente";
					} elseif (!is_null($pay->vinc_empleado)) {
						$destino = "empleado";
					} elseif (!is_null($pay->vinc_nomina)) {
						$destino = "nomina";
					} elseif (!is_null($pay->vinc_nomina_especie)) {
						$destino = "nomina en especie";
					} elseif (!is_null($pay->vinc_acreedor)) {
						$destino = "acreedor";
					} elseif (!is_null($pay->vinc_deudor)) {
						$destino = "deudor";
					}

					$tercero_token = "";
          $tercero_folio = "";
					$tercero_name = "";
					$tercero_comercial_name = "";
					
          $prov_token = "";
          $prov_folio = "";
					$prov_name = "";
					$prov_comercial_name = "";

          $financeadoa_token = "";
          $financeadoa_folio = "";
					$financeadoa_name = "";
					$financeadoa_comercial_name = "";
					if (!is_null($pay->vinc_proveedor)) {
          	//proveedor
						$queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
          	->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          	->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          	->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          	->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          	->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
						->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          	->where("payment.token_pagos", $pay->token_pagos)
          	->get();
          	//echo count($queryOrvVincReembolsosPago);
          	if (count($queryOrvVincReembolsosPago) > 0) {
						  $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          	  ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          	  ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          	  ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          	  ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          	  ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
						  ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          	  ->join("sos_personas AS people", "catprov.proveedor", "people.id")
						  ->where("payment.token_pagos",$pay->token_pagos)
						  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
						  ->first();
						  $tercero_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
          	  $tercero_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
						  $tercero_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
							$tercero_comercial_name = !is_null($queryProveedor->nombre_com) ? $JwtAuth->desencriptar($queryProveedor->nombre_com) : '';
          	} else {
          	  $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
						  ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
						  ->join("sos_personas AS people", "catprov.proveedor", "people.id")
						  ->where("payment.token_pagos",$pay->token_pagos)
						  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
						  ->first();
						  $tercero_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
          	  $tercero_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
						  $tercero_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
							$tercero_comercial_name = !is_null($queryProveedor->nombre_com) ? $JwtAuth->desencriptar($queryProveedor->nombre_com) : '';
          	}
					} elseif (!is_null($pay->vinc_cliente)) {
          	//cliente
						$queryCliente = DB::table("fnzs_pagos_pago AS payment")
						->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
						->join("sos_personas AS people", "catclient.cliente", "people.id")
						->where("payment.token_pagos",$pay->token_pagos)
						->select('catclient.token_cat_clientes','catclient.folio','catclient.post_folio','people.nombre_extendido','people.nombre_com')
						->first();
						$tercero_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
          	$tercero_folio = $queryCliente ? ('CLI-'.$JwtAuth->generarFolio($queryCliente->folio).(!is_null($queryCliente->post_folio) ? '-'.$queryCliente->post_folio : '')) : "";
						$tercero_name = $queryCliente ? $JwtAuth->desencriptar($queryCliente->nombre_extendido) : "";
						$tercero_comercial_name = !is_null($queryCliente->nombre_com) ? $JwtAuth->desencriptar($queryCliente->nombre_com) : '';
					} elseif (!is_null($pay->vinc_empleado)) {
          	//empleado
						$queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
						->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
						->join("sos_personas AS people", "pers.empleado_name", "people.id")
						->where("payment.token_pagos",$pay->token_pagos)
						->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
						->first();
						$tercero_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
          	$tercero_folio = $queryEmpleado ? "TRB-".$JwtAuth->generarFolio($queryEmpleado->folio_pers) : "";
						$tercero_name = $queryEmpleado ? $JwtAuth->desencriptarNombres($queryEmpleado->paterno,$queryEmpleado->materno,$queryEmpleado->nombre) : "";
					} elseif (!is_null($pay->vinc_acreedor)) {
						//acreedor
						$queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
						->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
						//->join("sos_personas AS people", "acr.acreedor", "people.id")
						->where("payment.token_pagos",$pay->token_pagos)
						->select('acr.token_cat_acreedores','acr.acr_folio','acr.acr_post_folio','acr.acr_titular')
						->first();
						$tercero_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
          	$tercero_folio = $queryAcreedor ? ('ACREE-'.$JwtAuth->generarFolio($queryAcreedor->acr_folio).(!is_null($queryAcreedor->acr_post_folio) ? '-'.$queryAcreedor->acr_post_folio : '')) : "";
						$tercero_name = $queryAcreedor ? $JwtAuth->desencriptar($queryAcreedor->acr_titular) : "";
					} elseif (!is_null($pay->vinc_deudor)) {
          	$queryDeudor = DB::table("fnzs_pagos_pago AS payment")
						->join("fnzs_catalogo_deudores AS deu", "payment.vinc_deudor", "deu.id")
						->where("payment.token_pagos",$pay->token_pagos)
						->select('deu.token_cat_deudores','deu.deu_folio','deu.deu_post_folio','deu.deu_titular','deu.deu_nombre_comercial')
						->get();
						foreach ($queryDeudor as $vDeuP) {
							$tercero_token = $vDeuP->token_cat_deudores;
          		$tercero_folio = 'DEU-'.$JwtAuth->generarFolio($vDeuP->deu_folio).(!is_null($vDeuP->deu_post_folio) ? '-'.$vDeuP->deu_post_folio : '');
              $tercero_name = !is_null($vDeuP->deu_titular) && $vDeuP->deu_titular != '' ? $JwtAuth->desencriptar($vDeuP->deu_titular) : 'N/A';
              $tercero_comercial_name = !is_null($vDeuP->deu_nombre_comercial) && $vDeuP->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeuP->deu_nombre_comercial) : 'N/A';

							$financeadoa_token = $vDeuP->token_cat_deudores;
          		$financeadoa_folio = 'DEU-'.$JwtAuth->generarFolio($vDeuP->deu_folio).(!is_null($vDeuP->deu_post_folio) ? '-'.$vDeuP->deu_post_folio : '');
              $financeadoa_name = !is_null($vDeuP->deu_titular) && $vDeuP->deu_titular != '' ? $JwtAuth->desencriptar($vDeuP->deu_titular) : 'N/A';
              $financeadoa_comercial_name = !is_null($vDeuP->deu_nombre_comercial) && $vDeuP->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeuP->deu_nombre_comercial) : 'N/A';
						}
					}

          //personal_pago
					$queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
					->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where('payment.token_pagos',$pay->token_pagos)
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
          $p_paga_folio = $queryPersPaga ? "TRB-".$JwtAuth->generarFolio($queryPersPaga->folio_pers) : "";
          $p_paga_paterno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->paterno)) : "";
          $p_paga_materno = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->materno)) : "";
          $p_paga_nombre = $queryPersPaga ? ucwords($JwtAuth->desencriptar($queryPersPaga->nombre)) : "";
					$p_paga_name = $queryPersPaga ? "$p_paga_paterno $p_paga_materno $p_paga_nombre" : "";

					$queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
					->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where('payment.token_pagos',$pay->token_pagos)
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
          $p_autoriza_folio = $queryPersAuth ? "TRB-".$JwtAuth->generarFolio($queryPersAuth->folio_pers) : "";
          $p_autoriza_paterno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->paterno)) : "";
          $p_autoriza_materno = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->materno)) : "";
          $p_autoriza_nombre = $queryPersAuth ? ucwords($JwtAuth->desencriptar($queryPersAuth->nombre)) : "";
					$p_autoriza_name = $queryPersAuth ? "$p_autoriza_paterno $p_autoriza_materno $p_autoriza_nombre" : "";

          $ordenes_relacionadas_lista = array();
          $factura_relacionada_typo = "---";
          $factura_relacionada_token = "---";
          $factura_relacionada_string = "---";
          $pago_rr_forma_metodo_pago_cfdi = "";
					$queryOrdenesPago = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->leftJoin("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->leftJoin("ingr_ventas AS sell", "order.factura_venta", "=", "sell.id")
          ->leftJoin("terc_reembolso_main AS reem", "order.reembolso_main", "=", "reem.id")
          ->leftJoin("eegr_catalogo_proveedores_anticipo AS ant", "order.ord_anticipo", "=", "ant.uuid_anticipo")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select("order.*","vinc.*","buy.token_compras","buy.folio_compra","sell.token_ventas",
          "sell.folio_venta","reem.token_reem","reem.folio_reem","reem.post_folio_reem","ant.uuid_anticipo","ant.folio_anticipo")->get();

          foreach ($queryOrdenesPago as $vOrdp) {
            $orden_pago_monto = $vOrdp->orden_pago_monto;

            $row_ord = array(
              "token_ordenPago" => $vOrdp->token_ordenPago,
              "orden_pago_monto" => "$".number_format($orden_pago_monto * $pay->tipo_cambio,$JwtAuth->getMonedaAPI($pay->p_moneda),'.',','),
              "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago),
							"fecha_contabilizacion_ordenPago" => gmdate('Y-m-d H:i:s',$vOrdp->fecha_contabilizacion_ordenPago),
              "fecha_registro" => gmdate('Y-m-d H:i:s', $vOrdp->fecha_sistema_ordenp),
              "autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,
              "pago_cancelado" => $pay->pago_cancelado ? true : false,
              //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_folio_cancelacion
              //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_fecha_cancelacion
              //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_fecha_contabilizacion_cancelacion

              "fecha_autorizacion_pay" => $vOrdp->autorizacion_pay ? gmdate('Y-m-d H:i:s', $vOrdp->fecha_autorizacion_pay) : "---",
							"factura_relacionada_typo" => $factura_relacionada_typo,
							"factura_relacionada_token" => $factura_relacionada_token,
							"factura_relacionada_string" => $factura_relacionada_string,
            );
            $ordenes_relacionadas_lista[] = $row_ord;
          }

          $desglose_pagos_medio = array();
					$queryPagoMovimiento = DB::table("fnzs_actividad_movimientos AS movim")
          ->join("fnzs_pagos_pago AS payment","movim.pago","payment.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->get();
          foreach ($queryPagoMovimiento as $vMov) {

					  $queryPersResponsable = DB::table("fnzs_actividad_movimientos AS movim")
					  ->join("vhum_empleados_catalogo AS pers", "movim.responsable", "pers.id")
					  ->join("sos_personas AS people", "pers.empleado_name", "people.id")
					  ->where('movim.token_movimiento',$vMov->token_movimiento)
					  ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					  ->first();
					  $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
            $pers_responsmov_folio = $queryPersResponsable ? "TRB-".$JwtAuth->generarFolio($queryPersResponsable->folio_pers) : "";
            $p_responsmov_paterno = $queryPersResponsable ? ucwords($JwtAuth->desencriptar($queryPersResponsable->paterno)) : "";
            $p_responsmov_materno = $queryPersResponsable ? ucwords($JwtAuth->desencriptar($queryPersResponsable->materno)) : "";
            $p_responsmov_nombre = $queryPersResponsable ? ucwords($JwtAuth->desencriptar($queryPersResponsable->nombre)) : "";
					  $pers_responsmov_name = $queryPersResponsable ? "$p_responsmov_paterno $p_responsmov_materno $p_responsmov_nombre" : "";

            $queryCaja = CajaModelo::join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_caja.id", "movim.caja")
            ->select('fnzs_catalogos_caja.token_caja','fnzs_catalogos_caja.no_caja','fnzs_catalogos_caja.alias_caja')
            ->where('movim.token_movimiento',$vMov->token_movimiento)
            ->first();
					  
            $queryCuenta = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
            ->join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_cuentas.id", "movim.cuenta_bancaria")
            ->select('fnzs_catalogos_cuentas.token_cuenta','fnzs_catalogos_cuentas.folio_cuenta','fnzs_catalogos_cuentas.cuenta')
            ->where('movim.token_movimiento',$vMov->token_movimiento)
            ->first();
					  
            $queryMonedero = CuentaMonederoModelo::join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_cuentas_monedero.id", "movim.cuenta_monedero")
            ->select('fnzs_catalogos_cuentas_monedero.token_cuentamonedero','fnzs_catalogos_cuentas_monedero.folio_cuentmon','fnzs_catalogos_cuentas_monedero.cuenta')
            ->where('movim.token_movimiento',$vMov->token_movimiento)
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

            $row_mov = array(
              "token_movimiento" => $vMov->token_movimiento,
              "folio_movimiento" => $JwtAuth->generarFolio($vMov->folio_movimiento),
              "fecha_sistema" => gmdate('Y-m-d H:i:s', $vMov->fecha_sistema),
              "tipo_movimiento" => $vMov->tipo_movimiento,
              "subtipo_movimiento" => $vMov->subtipo_movimiento,
              //"responsable" => $vEmp->userr,
              "responsable_token" => $pers_responsmov_token,
						  "responsable_folio" => $pers_responsmov_folio,
						  "responsable_name" => $pers_responsmov_name,
              //"cuenta_monedero" => $sql_cuenta_monedero,
              "movimiento_tipo" => $movimiento_tipo,
              "movimiento_token" => $movimiento_token,
              "movimiento_folio" => $movimiento_folio,
              "movimiento_name" => $movimiento_name,
              "monto_aplicado" => "$".number_format($vMov->monto_aplicado,$JwtAuth->getMonedaAPI($pay->p_moneda),'.', ',')." $pay->p_moneda",
            );
            $desglose_pagos_medio[] = $row_mov;
          }

          $medio_pago_vinculado = "";
          $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
          ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
          ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
          ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
          ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
          ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
          ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select("r_caj.*","r_cuent.*","r_moned.*")->get();
          //echo count($queryFormasDePago);
          //var_dump($queryFormasDePago);
          foreach ($queryFormasDePago as $vFPagoVinc) {
            if ($vFPagoVinc->token_caja !== null) {
					    $medio_pago_vinculado = "Caja CAJ-".$JwtAuth->generarFolio($vFPagoVinc->no_caja);
						} elseif ($vFPagoVinc->token_cuenta !== null) {
              $medio_pago_vinculado = "Banco CUENT-".$JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
              //echo "Banco CUENT-".$JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
						} elseif ($vFPagoVinc->token_cuentamonedero !== null) {
              $medio_pago_vinculado = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
						}
          }
          //echo $medio_pago_vinculado;
          //if ($forma_pago_registrada != '' && $cfdi_comprobante_metodo_de_pago != '') {
          //  $pago_rr_forma_metodo_pago_cfdi = $forma_pago_registrada." / ".$cfdi_comprobante_metodo_de_pago;
          //} elseif ($forma_pago_registrada != '' && $cfdi_comprobante_metodo_de_pago == '') {
          //  $pago_rr_forma_metodo_pago_cfdi = $forma_pago_registrada;
          //} elseif ($forma_pago_registrada == '' && $cfdi_comprobante_metodo_de_pago != '') {
          //  $pago_rr_forma_metodo_pago_cfdi = $cfdi_comprobante_metodo_de_pago;
          //} else {
          //  $pago_rr_forma_metodo_pago_cfdi = '';
          //}

          $row = array(
            "token_pagos" => $pay->token_pagos,
            "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($pay->folio_pagos),
            //"folio_operacion" => $pay->folio_operacion,
            //"fecha_pago" => gmdate('Y-m-d H:i:s', $pay->fecha_pago),
						"fecha_contabilizacion" => !empty($pay->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $pay->fecha_contabilizacion) : "",
            //cancelado
            "pago_cancelado" => $pay->pago_cancelado ? true : false,	
            "pago_cancelado_translate" => $pay->pago_cancelado ? 'canceled_reg' : 'approved_reg',
            "pago_folio_cancelacion" => $pay->pago_cancelado ? "PCAN-".$JwtAuth->generarFolio($pay->pago_folio_cancelacion) : "",
            "pago_fecha_cancelacion" => $pay->pago_cancelado ? gmdate('Y-m-d H:i:s', $pay->pago_fecha_cancelacion) : "",
            "pago_fecha_contabilizacion_cancelacion" => $pay->pago_cancelado ? gmdate('Y-m-d H:i:s', $pay->pago_fecha_contabilizacion_cancelacion) : "",
            "monto_pago" => $pay->monto_pago,
            "monto_pago_format" => "$".number_format($pay->monto_pago,$JwtAuth->getMonedaAPI($pay->p_moneda),'.', ',')." $pay->p_moneda",
						"monto_pago_resultant" => "$".number_format($pay->monto_pago * $pay->tipo_cambio,$JwtAuth->getMonedaAPI($pay->p_moneda),'.', ',')." $pay->p_moneda",
            "observacionesPago" => !is_null($pay->observacionesPago) ? $JwtAuth->desencriptar($pay->observacionesPago) : '',
            "tipo_cambio" => $pay->tipo_cambio,
            "tipo_cambio_format" => "$".number_format($pay->tipo_cambio,$JwtAuth->getMonedaAPI($pay->p_moneda),'.',',')." $pay->p_moneda",
            "p_moneda" => $pay->p_moneda,
            //forma_pago
            "forma_pago_pago" => !is_null($pay->forma_pago_pago) ? $pay->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($pay->forma_pago_pago) : '',
            "forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
            ////tercero
            "destino" => $destino,

						"tercero_token" => $factura_relacionada_typo == 'anticipos' ? $prov_token : $tercero_token,
						"tercero_folio" => $factura_relacionada_typo == 'anticipos' ? $prov_folio : $tercero_folio,
						"tercero_name" => $factura_relacionada_typo == 'anticipos' ? $prov_name : $tercero_name,
            "tercero_comercial_name" => $factura_relacionada_typo == 'anticipos' ? $prov_comercial_name : $tercero_comercial_name,

            //"ant_prov_folio" => $prov_folio,
            //"ant_prov_token" => $prov_token,
            //"ant_prov_name" => $prov_name,
            //"ant_prov_comercial_name" => $prov_comercial_name,

						"financeadoa_token" => $financeadoa_token,
						"financeadoa_folio" => $financeadoa_folio,
						"financeadoa_name" => $financeadoa_name,
            "financeadoa_comercial_name" => $financeadoa_comercial_name,

            "concepto" => !empty($pay->concepto) ? $JwtAuth->desencriptar($pay->concepto) : '',
            //personal_pago
            "personal_pago_token" => $p_paga_token,
            "personal_pago_folio" => $p_paga_folio,
            "personal_pago_name" => $p_paga_name,
            "pago_autorizado" => $pay->pago_autorizado ? true : false,
            "fecha_pago_auth" => gmdate('Y-m-d H:i:s', $pay->fecha_pago_auth),
            //personal_autoriza
            "personal_autoriza_token" => $p_autoriza_token,
            "personal_autoriza_folio" => $p_autoriza_folio,
            "personal_autoriza_name" => $p_autoriza_name,
            //ordenes_relacionadas
            "ordenes_relacionadas_lista" => $ordenes_relacionadas_lista,
            //desglose_pagos_medio

            "orden_factura_relacionada_typo" => $factura_relacionada_typo,
            "orden_factura_relacionada_token" => $factura_relacionada_token,
            "orden_factura_relacionada_string" => $factura_relacionada_string,

            "desglose_pagos_medio" => $desglose_pagos_medio,
            "medio_pago_vinculado" => $medio_pago_vinculado,
            "doc_anterior_folio" => $doc_anterior_folio,
            "doc_anterior_fecha_contabilizacion" => $doc_anterior_fecha_contabilizacion,
          );
					$lista_pagos[] = $row;
				}

				$dataMensaje = array(
					"status" => "success",
					"code" => 200,
					'lista_pagos_general' => collect($lista_pagos)->sortBy('id')->values(),
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function desglosePagosNominaDispersion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'pago_realizado' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La información recibida es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $pago_realizado = $request->input('pago_realizado');

      $queryPago = DB::table("fnzs_pagos_pago AS payment")
      ->join("main_empresas AS emp", "payment.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        "payment.token_pagos" => $pago_realizado,
        "payment.pago_autorizado" => TRUE,
        "payment.status_pagos" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->orderBy("payment.folio_pagos", "DESC")
      ->select("payment.id AS id_pago_done","payment.*","emp.*")
      ->get();

      if ($queryPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        $pagos_realizados_monto = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
        ->where([
          "pay.token_pagos" => $pago_realizado,
          "vinc.vinculo_cancelado" => FALSE
        ])
        ->sum('vinc.orden_pago_monto');

        $lista_pagos_realizados = array();
        foreach ($queryPago as $vPago) {
          $pago_folio = "PAGO-".$JwtAuth->generarFolio($vPago->folio_pagos);
          $forma_pago_registrada = $vPago->forma_pago_pago;

          //personal_pago
					$queryPersPaga = DB::table("vhum_empleados_catalogo AS pers")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where('pers.id',$vPago->personal_pago)
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
          $p_paga_folio = $queryPersPaga ? "TRB-".$JwtAuth->generarFolio($queryPersPaga->folio_pers) : "";
					$p_paga_name = $queryPersPaga ? $JwtAuth->desencriptarNombres($queryPersPaga->paterno,$queryPersPaga->materno,$queryPersPaga->nombre) : "";

					$queryPersAuth = DB::table("vhum_empleados_catalogo AS pers")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where('pers.id',$vPago->personal_autoriza)
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
          $p_autoriza_folio = $queryPersAuth ? "TRB-".$JwtAuth->generarFolio($queryPersAuth->folio_pers) : "";
					$p_autoriza_name = $queryPersAuth ? $JwtAuth->desencriptarNombres($queryPersAuth->paterno,$queryPersAuth->materno,$queryPersAuth->nombre) : "";

          $ordenes_relacionadas_lista = array();
					$queryOrdenesPago = DB::table("fnzs_pagos_pago_ordenes_vinculadas AS vinc")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->where("vinc.pago_realizado", $vPago->id_pago_done)
          ->select("order.*","vinc.*")
          ->get();
          
          //$rOrdPag->nomina_main
          $idNominaMain = $queryOrdenesPago->pluck('nomina_main')->filter()->unique()->toArray();
          $nominaMainMap = DB::table("vhum_nominas_main")
          ->whereIn('id', function ($query) {
            $query->select('nomina_main')->from('vhum_nominas_recibos');
          })
          ->whereIn('id', $idNominaMain)->get()->keyBy('id');
          
          $empEnviaNominaMainMap = DB::table("main_empresas AS emp")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->whereIn("emp.id", $nominaMainMap->pluck('nomina_empresa')->unique())
          ->get()->keyBy('id');
          
          $detalleNominaListaMap = DB::table("vhum_nominas_recibos")
          ->whereIn("nomina_main", $idNominaMain)
          ->get()
          ->groupBy('nomina_main');
          
          //$rOrdPag->nomina_en_especie
          $idNominaEspecie = $queryOrdenesPago->pluck('nomina_en_especie')->filter()->unique()->toArray();
          $nominaEspecieMap = DB::table("vhum_nominas_especie")
          ->whereIn('id', function ($query) {
            $query->select('nomina_especie')->from('vhum_nominas_especie_desglose');
          })
          ->whereIn('id', $idNominaEspecie)->get()->keyBy('id');
      
          $empEnviaEspecieNominaMap = DB::table("main_empresas AS emp")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->whereIn("emp.id", $nominaEspecieMap->pluck('nomina_esp_empresa')->unique())
          ->get()->keyBy('id');
      
          $detailEspNominaMap = DB::table("vhum_nominas_especie_desglose")
          ->whereIn("nomina_especie", $idNominaEspecie)
          ->get()
          ->groupBy('nomina_especie');

          foreach ($queryOrdenesPago as $vOrdp) {
            $orden_pago_monto = $vOrdp->orden_pago_monto;
            $fecha_contabilizacion_doc_anterior = "";
					  $factura_relacionada_typo = "nominas";
					  $factura_relacionada_token = "---";
					  $factura_relacionada_string = "---";

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

            //if ($vOrdp->token_compras !== null)
            if (!is_null($vOrdp->nomina_main) && !is_null($vOrdp->nomina_en_especie)) {
              $factura_relacionada_typo = "nominas_especie";
              $vEspNom = $nominaEspecieMap->get($vOrdp->nomina_en_especie);
              if ($vEspNom) {
                $vEspEmpNom = $empEnviaEspecieNominaMap->get($vEspNom->nomina_esp_empresa);
                if ($vEspEmpNom) {
                  $orden_emisor_emp = $vEspEmpNom->abrev_nombre;
                }
    
                $fecha_contabilizacion_doc_anterior = date('Y-m-d',$vEspNom->nomina_esp_fecha_contabilizacion);
                $factura_relacionada_token = $vEspNom->token_nominas_especie;
                $factura_relacionada_string = 'NOM-ES-'.$JwtAuth->generarFolio($vEspNom->nomina_esp_folio_interior).(!is_null($vEspNom->nomina_esp_subfolio) ? '-'.$vEspNom->nomina_esp_subfolio : '');
                
                $v_nomi_especie_detail = $detailEspNominaMap->get($vOrdp->nomina_en_especie);
                if ($v_nomi_especie_detail) {
                  foreach ($v_nomi_especie_detail as $vNomDetEsp) {
                    $orden_moneda_inicial_name = $vNomDetEsp->nomina_esp_moneda;
                    $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
                    $orden_moneda_autorizado_inicial_name = $vNomDetEsp->nomina_esp_moneda;
                    $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
                    $orden_moneda_autorizado_final_name = $vNomDetEsp->nomina_esp_moneda;
                    $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
                    $importe_concepto_simple = floatval($vNomDetEsp->total_en_especie);
                    $importe_total_inicial += $importe_concepto_simple;
                    $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
      
                    $importe_autorizado_final += floatval($vNomDetEsp->total_en_especie);
                  }
                }
              }
            } else {
              $factura_relacionada_typo = "nominas";
              $vNom = $nominaMainMap->get($vOrdp->nomina_main);
    
              if ($vNom) {
                $vEmpNom = $empEnviaNominaMainMap->get($vNom->nomina_empresa);
                if ($vEmpNom) {
                  $orden_emisor_emp = $vEmpNom->abrev_nombre;
                }
                
                $fecha_contabilizacion_doc_anterior = date('Y-m-d',$vNom->nomina_fecha_contabilizacion);
                $factura_relacionada_token = $vNom->token_nominas_periodos;
                $factura_relacionada_string = 'NOM-EF-'.$JwtAuth->generarFolio($vNom->nomina_folio_interior).(!is_null($vNom->nomina_subfolio) ? '-'.$vNom->nomina_subfolio : '');
                
                $v_nomina_main_detalle = $detalleNominaListaMap->get($vOrdp->nomina_main);
                if ($v_nomina_main_detalle) {
                  foreach ($v_nomina_main_detalle as $vNomDetMain) {
                    $orden_moneda_inicial_name = $vNomDetMain->nomina_moneda;
                    $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
                    $orden_moneda_autorizado_inicial_name = $vNomDetMain->nomina_moneda;
                    $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
                    $orden_moneda_autorizado_final_name = $vNomDetMain->nomina_moneda;
                    $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
      
                    $importe_concepto_simple = floatval($vNomDetMain->total_efectivo);
                    $importe_total_inicial += $importe_concepto_simple;
                    $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
      
                    $importe_autorizado_final += floatval($vNomDetMain->total_efectivo);
                  }
                }
              }
            }

            $pago_restante = $pagos_realizados_monto > 0 ? $importe_autorizado_final - $pagos_realizados_monto : $importe_autorizado_final;

            $row_ord = array(
              "token_ordenPago" => $vOrdp->token_ordenPago,
              "orden_pago_monto" => "$".number_format($orden_pago_monto * $vPago->tipo_cambio,$JwtAuth->getMonedaAPI($vPago->p_moneda),'.',','),
              "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago),
							"fecha_contabilizacion_ordenPago" => gmdate('Y-m-d H:i:s',$vOrdp->fecha_contabilizacion_ordenPago),
              "fecha_registro" => gmdate('Y-m-d H:i:s', $vOrdp->fecha_sistema_ordenp),
              "autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,
              "fecha_autorizacion_pay" => $vOrdp->autorizacion_pay ? gmdate('Y-m-d H:i:s', $vOrdp->fecha_autorizacion_pay) : "---",
							"factura_relacionada_typo" => $factura_relacionada_typo,
							"factura_relacionada_token" => $factura_relacionada_token,
							"factura_relacionada_string" => $factura_relacionada_string,
							//"orden_emisor_emp" => $orden_emisor_emp,
							//"orden_emisor_personal" => $orden_emisor_personal,
							"importe_total_inicial_simple" => $importe_total_inicial,
							"orden_moneda_inicial_name" => $orden_moneda_inicial_name,
							"importe_total_inicial" => $JwtAuth->muestraCantidadesConMoneda($importe_total_inicial,$orden_moneda_inicial_name,$orden_moneda_inicial_decimales),
							"importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
							"orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
							"orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
							"importe_autorizado_inicial_format" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_inicial,$orden_moneda_autorizado_inicial_name,$orden_moneda_autorizado_inicial_decimales),
							"importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
							"importe_autorizado_final" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_final,$orden_moneda_autorizado_final_name,$orden_moneda_autorizado_final_decimales),
							"orden_moneda_final_autorizada_name" => $orden_moneda_autorizado_final_name,
              "importe_restante" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
              "importe_restante_format" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
							"importe_por_pagar" => "0.00",

              "debe_simple" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
              "debe_format" => "$".number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
            );
            $ordenes_relacionadas_lista[] = $row_ord;
          }

          $dispersiones_lista = array();
					$queryDisperTrabPago = DB::table("fnzs_pagos_nomina_empleado_dispersion AS dispTrab")
          ->join("vhum_empleados_catalogo AS pers", "dispTrab.empleado_referenciado", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "people.id")
          ->join("vhum_nominas_recibos AS nomi", "dispTrab.nomina_recibo", "nomi.id")
          ->where("dispTrab.pago_referenciado", $vPago->id_pago_done)
          ->select('dispTrab.*','pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre','nomi.total_efectivo')
          ->get();

          foreach ($queryDisperTrabPago as $vDiTrabPag) {
            $pago_restante = $vDiTrabPag->total_efectivo - $vDiTrabPag->monto;
            $row_disper_trab = array(
              "folio_dispersion" => 'DISP-'.$JwtAuth->generarFolio($vDiTrabPag->folio_dispersion), 
              "empleado_referenciado_token" => $vDiTrabPag->empleado_token, 
              "empleado_referenciado" => $JwtAuth->desencriptarNombres($vDiTrabPag->paterno,$vDiTrabPag->materno,$vDiTrabPag->nombre), 
              "monto_aplicado" => $JwtAuth->muestraCantidadesConMoneda($vDiTrabPag->monto,$vDiTrabPag->moneda,$JwtAuth->getMonedaAPI($vDiTrabPag->moneda)),
              "monto_restante" => $JwtAuth->muestraCantidadesConMoneda($pago_restante,$vDiTrabPag->moneda,$JwtAuth->getMonedaAPI($vDiTrabPag->moneda)),
            );
            $dispersiones_lista[] = $row_disper_trab;
          }

          $desglose_pagos_medio = array();
					$queryPagoMovimiento = DB::table("fnzs_actividad_movimientos AS movim")
          ->join("fnzs_pagos_pago AS payment","movim.pago","payment.id")
          ->where("payment.token_pagos", $vPago->token_pagos)
          ->get();
          foreach ($queryPagoMovimiento as $vMov) {

					  $queryPersResponsable = DB::table("fnzs_actividad_movimientos AS movim")
					  ->join("vhum_empleados_catalogo AS pers", "movim.responsable", "pers.id")
					  ->join("sos_personas AS people", "pers.empleado_name", "people.id")
					  ->where('movim.token_movimiento',$vMov->token_movimiento)
					  ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					  ->first();
					  $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
            $pers_responsmov_folio = $queryPersResponsable ? "TRB-".$JwtAuth->generarFolio($queryPersResponsable->folio_pers) : "";
					  $pers_responsmov_name = $queryPersResponsable ? $JwtAuth->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

            $queryCaja = CajaModelo::join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_caja.id", "movim.caja")
            ->select('fnzs_catalogos_caja.token_caja','fnzs_catalogos_caja.no_caja','fnzs_catalogos_caja.alias_caja')
            ->where('movim.token_movimiento',$vMov->token_movimiento)
            ->first();

            $queryCuenta = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
            ->join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_cuentas.id", "movim.cuenta_bancaria")
            ->select('fnzs_catalogos_cuentas.token_cuenta','fnzs_catalogos_cuentas.folio_cuenta','fnzs_catalogos_cuentas.cuenta')
            ->where('movim.token_movimiento',$vMov->token_movimiento)
            ->first();

            $queryMonedero = CuentaMonederoModelo::join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_cuentas_monedero.id", "movim.cuenta_monedero")
            ->select('fnzs_catalogos_cuentas_monedero.token_cuentamonedero','fnzs_catalogos_cuentas_monedero.folio_cuentmon','fnzs_catalogos_cuentas_monedero.cuenta')
            ->where('movim.token_movimiento',$vMov->token_movimiento)
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
            
            $row_mov = array(
              "token_movimiento" => $vMov->token_movimiento,
              "folio_movimiento" => $JwtAuth->generarFolio($vMov->folio_movimiento),
              "fecha_sistema" => gmdate('Y-m-d H:i:s', $vMov->fecha_sistema),
              "tipo_movimiento" => $vMov->tipo_movimiento,
              "subtipo_movimiento" => $vMov->subtipo_movimiento,
              //"responsable" => $vEmp->userr,
              "responsable_token" => $pers_responsmov_token,
						  "responsable_folio" => $pers_responsmov_folio,
						  "responsable_name" => $pers_responsmov_name,
              //"cuenta_monedero" => $sql_cuenta_monedero,
              "movimiento_tipo" => $movimiento_tipo,
              "movimiento_token" => $movimiento_token,
              "movimiento_folio" => $movimiento_folio,
              "movimiento_name" => $movimiento_name,
              "monto_aplicado" => "$".number_format($vMov->monto_aplicado,$JwtAuth->getMonedaAPI($vPago->p_moneda),'.', ',')." $vPago->p_moneda",
            );
            $desglose_pagos_medio[] = $row_mov;
          }

          $cfdi_comprobante_metodo_de_pago = "";
					$queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
          ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
          ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
          ->where("payment.token_pagos", $vPago->token_pagos)
          ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

          $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

          $forma_pago_vinculada = "";
          $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
          ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
          ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
          ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
          ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
          ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
          ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
          ->where("payment.token_pagos", $vPago->token_pagos)
          ->select("r_caj.*","r_cuent.*","r_moned.*")->get();

          foreach ($queryFormasDePago as $vFPagoVinc) {
            if ($vFPagoVinc->token_caja !== null) {
					    $forma_pago_vinculada = "Caja CAJ-".$JwtAuth->generarFolio($vFPagoVinc->no_caja);
						} elseif ($vFPagoVinc->token_cuenta !== null) {
              $forma_pago_vinculada = "Banco CUENT-".$JwtAuth->generarFolio($vFPagoVinc->folio_cuenta);
						} elseif ($vFPagoVinc->token_cuentamonedero !== null) {
              $forma_pago_vinculada = "Monedero CUENTM-" . $JwtAuth->generarFolio($vFPagoVinc->folio_cuentmon);
						}
          }

          $row_pay = array(
            "token_pagos" => $vPago->token_pagos,
            "folio_pagos" => $pago_folio,
            
            "folio_operacion" => $vPago->folio_operacion,
            "fecha_sistema" => date('d-m-Y H:i:s',$vPago->fecha_sistema),
            "fecha_pago" => date('d-m-Y H:i:s',$vPago->fecha_pago),
            "fecha_contabilizacion" => $vPago->fecha_contabilizacion ? gmdate('Y-m-d H:i:s',$vPago->fecha_contabilizacion) : '',
            "monto_pago" => "$".number_format($vPago->monto_pago * $vPago->tipo_cambio,$JwtAuth->getMonedaAPI($vPago->p_moneda), '.', ','),
            "observacionesPago" => $vPago->observacionesPago ? $JwtAuth->desencriptar($vPago->observacionesPago) : '',
            "tipo_cambio" => "$".number_format($vPago->tipo_cambio,$JwtAuth->getMonedaAPI($vPago->p_moneda), '.', ','),
            "p_moneda" => $vPago->p_moneda,
            "forma_pago_vinculada" => $forma_pago_vinculada,
            "forma_pago_cfdi" => $forma_pago_registrada ? $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada) : '',
            "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
            //"reembolso_solicitud" => $vPago->reembolso_solicitud,
            "concepto" => $vPago->concepto ? $JwtAuth->desencriptar($vPago->concepto) : '',
            //personal_pago
            "personal_pago_token" => $p_paga_token,
            "personal_pago_folio" => $p_paga_folio,
            "personal_pago_name" => $p_paga_name,

            "pago_autorizado" => $vPago->pago_autorizado ? true : false,
            "fecha_pago_auth" => $vPago->fecha_pago_auth ? gmdate('Y-m-d H:i:s',$vPago->fecha_pago_auth) : '',
            //personal_autoriza
            "personal_autoriza_token" => $p_autoriza_token,
            "personal_autoriza_folio" => $p_autoriza_folio,
            "personal_autoriza_name" => $p_autoriza_name,
            //ordenes_relacionadas
            "ordenes_relacionadas_lista" => $ordenes_relacionadas_lista,
            //dispersiones_relacionadas
            "dispersiones_lista" => $dispersiones_lista,
            //desglose_pagos_medio
            "desglose_pagos_medio" => $desglose_pagos_medio,
          );
          $lista_pagos_realizados[] = $row_pay;
        }
        $dataMensaje = array("status" => "success", "code" => 200, "pagos_realizados" => $lista_pagos_realizados);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  public function catalogo_nomina_trabajadores(Request $request){
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
      date_default_timezone_set('America/Mexico_City');
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
      
      $queryEmpleados = PersonalModelo::join('sos_personas AS people', 'vhum_empleados_catalogo.empleado_name', 'people.id')
      ->join('main_empresas AS emp', 'vhum_empleados_catalogo.empleado_empresa', 'emp.id')
      ->join('main_empresa_usuario AS empuser', 'emp.id', 'empuser.empresa')
      ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', 'users.id')
      ->where('vhum_empleados_catalogo.folio_pers','!=', 0)
      ->where([
        'vhum_empleados_catalogo.causa_baja'=> FALSE,
        'vhum_empleados_catalogo.status'=> TRUE,
        'emp.empresa_token'=> $empresa,
        'users.usuario_token'=> $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween('vhum_empleados_catalogo.fecha_alta_pers', [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($queryEmpleados->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron trabajadores registrados'
        );
      } else {
        $arrayEmpleados = array();
        foreach ($queryEmpleados as $vEmploy) {
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          //$forma_pago_registrada = $vPayNTrab->forma_pago_pago;
          $nomina_efectivo_total = DB::table("vhum_nominas_recibos AS nom")
          ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
          ->where('trab.empleado_token',$vEmploy->empleado_token)
          ->sum("nom.total_efectivo");

          $nomina_especie_total = DB::table("vhum_nominas_especie_desglose AS esp")
          ->join("vhum_empleados_catalogo AS trab", "esp.trabajador", "trab.id")
          ->where('trab.empleado_token',$vEmploy->empleado_token)
          ->sum("esp.total_en_especie");

          $nomina_total = $nomina_efectivo_total + $nomina_especie_total;

          $pagado_efectivo_total = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_nomina_empleado_dispersion AS pdisp", "pay.id", "=", "pdisp.pago_referenciado")
          ->join("vhum_nominas_recibos AS recibos", "pdisp.nomina_recibo", "=", "recibos.id")
          ->join("vhum_empleados_catalogo AS trab", "recibos.trabajador", "=", "trab.id")
          ->where([
            "trab.empleado_token" => $vEmploy->empleado_token,
          ])
          ->sum("pdisp.monto");

          $pagado_especie_total = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_nomina_empleado_especie AS pespe", "pay.id", "=", "pespe.pago_referenciado")
          ->join("vhum_nominas_especie_desglose AS nespe", "pespe.nomina_especie", "=", "nespe.id")
          ->join("vhum_empleados_catalogo AS trab", "nespe.trabajador", "=", "trab.id")
          ->where([
            "trab.empleado_token" => $vEmploy->empleado_token,
          ])
          ->sum("pespe.monto");

          $neto_pagado_total = $pagado_efectivo_total + $pagado_especie_total;
          $neto_restante_total = $nomina_total - $neto_pagado_total;

          $rowEmpleado = array(
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => $folio_empleado,
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '',
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "nombre_completo" => $nombre_completo,
            "nacionalidad" => $vEmploy->nacionalidad,
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "nomina" => "$".number_format($nomina_total,$JwtAuth->getMonedaAPI($vEmploy->e_moneda_code), '.', ',')." $vEmploy->e_moneda_code",
            "pagado" => "$".number_format($neto_pagado_total,$JwtAuth->getMonedaAPI($vEmploy->e_moneda_code), '.', ',')." $vEmploy->e_moneda_code",
            "deuda" => "$".number_format($neto_restante_total,$JwtAuth->getMonedaAPI($vEmploy->e_moneda_code), '.', ',')." $vEmploy->e_moneda_code",
            "selected" => false,
            "ver_trabajador_info" => false,
            "trabajador_detail" => [],
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_desglose_pagos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_empleado_vhum' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La información recibida es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_empleado_vhum = $request->input('token_empleado_vhum');
      
      $queryEmpleados = PersonalModelo::join('sos_personas AS people', 'vhum_empleados_catalogo.empleado_name', 'people.id')
      ->join('main_empresas AS emp', 'vhum_empleados_catalogo.empleado_empresa', 'emp.id')
      ->join('main_empresa_usuario AS empuser', 'emp.id', 'empuser.empresa')
      ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', 'users.id')
      ->where([
        'vhum_empleados_catalogo.empleado_token' => $token_empleado_vhum,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();
  
      if ($queryEmpleados->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron trabajadores registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayEmpleados = array();

        foreach ($queryEmpleados as $vEmploy) {
          date_default_timezone_set('UTC');
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          $estado_cuenta_acreedor = array();
          $nominas_registradas = DB::table("vhum_nominas_recibos AS recibos")
          ->join("vhum_nominas_main AS nom", "recibos.nomina_main", "=", "nom.id")
          ->join("vhum_empleados_catalogo AS trab", "recibos.trabajador", "=", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->select([
            DB::raw("'NOMINA' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            "recibos.token_nomina_recibo AS token_movimiento",
            "nom.nomina_folio_interior AS folio_movimiento",
            "nom.nomina_subfolio AS sub_folio_movimiento",
            "nom.nomina_fecha_contabilizacion AS fecha_contabilizacion",
            "nom.nomina_observaciones AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "recibos.total_efectivo AS monto_movimiento",
            DB::raw("1 AS tipo_cambio_movimiento"),
            "recibos.nomina_moneda AS moneda_movimiento",
            "nom.id AS movimiento_id",
          ]);
          
          $nominas_especie_registradas = DB::table("vhum_nominas_especie_desglose AS desg")
          ->join("vhum_nominas_especie AS esp_main", "desg.nomina_especie", "=", "esp_main.id")
          ->join("vhum_nominas_main AS nom", "esp_main.nomina_main", "=", "nom.id")
          ->join("vhum_empleados_catalogo AS trab", "desg.trabajador", "=", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->select([
            DB::raw("'ESPECIE' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            "desg.token_especie_desglose AS token_movimiento",
            "esp_main.nomina_esp_folio_interior AS folio_movimiento",
            "esp_main.nomina_esp_subfolio AS sub_folio_movimiento",
            "esp_main.nomina_esp_fecha_contabilizacion AS fecha_contabilizacion",
            "nom.nomina_observaciones AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "desg.total_en_especie AS monto_movimiento",
            DB::raw("1 AS tipo_cambio_movimiento"),
            "desg.nomina_esp_moneda AS moneda_movimiento",
            "esp_main.id AS movimiento_id",
          ]);

          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_pagos_nomina_empleado_dispersion AS pdisp", "pago.id", "=", "pdisp.pago_referenciado")
          ->join("vhum_nominas_recibos AS recibos", "pdisp.nomina_recibo", "=", "recibos.id")
          ->join("vhum_empleados_catalogo AS trab", "recibos.trabajador", "=", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->where("pago.pago_cancelado", FALSE)
          ->select([
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            "pago.token_pagos AS token_movimiento",
            "pago.folio_pagos AS folio_movimiento",
            DB::raw("'' AS sub_folio_movimiento"),
            "pago.fecha_contabilizacion AS fecha_contabilizacion",
            "pago.observacionesPago AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $pagos_especie = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_pagos_nomina_empleado_especie AS pespe", "pago.id", "=", "pespe.pago_referenciado")
          ->join("vhum_nominas_especie_desglose AS nespe", "pespe.nomina_especie", "=", "nespe.id")
          ->join("vhum_empleados_catalogo AS trab", "nespe.trabajador", "=", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->where("pago.pago_cancelado", FALSE)
          ->select([
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            "pago.token_pagos AS token_movimiento",
            "pago.folio_pagos AS folio_movimiento",
            DB::raw("'' AS sub_folio_movimiento"),
            "pago.fecha_contabilizacion AS fecha_contabilizacion",
            "pago.observacionesPago AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $cancelaciones = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_pagos_nomina_empleado_dispersion AS pdisp", "pago.id", "=", "pdisp.pago_referenciado")
          ->join("vhum_nominas_recibos AS recibos", "pdisp.nomina_recibo", "=", "recibos.id")
          ->join("vhum_empleados_catalogo AS trab", "recibos.trabajador", "=", "trab.id")
          ->where([
            "trab.empleado_token" => $vEmploy->empleado_token,
          ])
          ->where("pago.pago_cancelado", TRUE)
          ->select([
            DB::raw("'PAGO-CANCELADO' AS tipo_registro_e_cuenta"),
            "pago.id AS doc_asociado",
            "pago.token_pagos AS token_movimiento",
            "pago.pago_folio_cancelacion AS folio_movimiento",
            DB::raw("'' AS sub_folio_movimiento"),
            "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
            "pago.pago_comentarios_cancelacion AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $cancelaciones_especie = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_pagos_nomina_empleado_especie AS pespe", "pago.id", "=", "pespe.pago_referenciado")
          ->join("vhum_nominas_especie_desglose AS nespe", "pespe.nomina_especie", "=", "nespe.id")
          ->join("vhum_empleados_catalogo AS trab", "nespe.trabajador", "=", "trab.id")
          ->where([
            "trab.empleado_token" => $vEmploy->empleado_token,
          ])
          ->where("pago.pago_cancelado", TRUE)
          ->select([
            DB::raw("'PAGO-ESP-CANCELADO' AS tipo_registro_e_cuenta"),
            "pago.id AS doc_asociado",
            "pago.token_pagos AS token_movimiento",
            "pago.pago_folio_cancelacion AS folio_movimiento",
            DB::raw("'' AS sub_folio_movimiento"),
            "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
            "pago.pago_comentarios_cancelacion AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $unionEstadoDeCuenta = $nominas_registradas->unionAll($nominas_especie_registradas)->unionAll($pagos)->unionAll($pagos_especie)->unionAll($cancelaciones)->unionAll($cancelaciones_especie);

          $queryEstadoDeCuenta = DB::table(DB::raw("({$unionEstadoDeCuenta->toSql()}) as estado_cuenta"))
          ->mergeBindings($unionEstadoDeCuenta) // Importante para no perder los parámetros del WHERE
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();

          $idPagos = $queryEstadoDeCuenta->pluck('token_movimiento')->filter()->unique()->toArray();
				  $mapMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
          ->join("cfdi_vinculacion_nomina AS vinc_nomi", "cfdi.id", "=", "vinc_nomi.comprobante_fiscal")
          ->join("vhum_nominas_main AS nomi", "vinc_nomi.nomina_main", "nomi.id")
          ->join("fnzs_pagos_orden AS order", "nomi.id", "order.nomina_main")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
          ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
          ->whereIn("payment.token_pagos", $idPagos)
          ->select(
            "payment.token_pagos AS id_pagos",
            "cfdi.cfdi_comprobante_metodo_de_pago"
          )
          ->get()->keyBy('id');

          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_movimiento = $vECuenta->token_movimiento;
            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';
            $f_m_pago_cfdi = "";
					  $forma_pago_registrada = $vECuenta->forma_pago_pago !== '---' ? $vECuenta->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($vECuenta->forma_pago_pago) : "";

					  $cfdi_comprobante_metodo_de_pago = "";
            if ($vECuenta->tipo_registro_e_cuenta == "PAGO" || $vECuenta->tipo_registro_e_cuenta == "PAGO-CANCELADO" || $vECuenta->tipo_registro_e_cuenta == "PAGO-ESP-CANCELADO") {
              $queryMetodoPago = $mapMetodoPago->get($token_movimiento);
              if ($queryMetodoPago) {
                $cfdi_comprobante_metodo_de_pago = $queryMetodoPago->cfdi_comprobante_metodo_de_pago;
              }
            }

            if ($forma_pago_registrada != "" && $cfdi_comprobante_metodo_de_pago != "") {
              $f_m_pago_cfdi = $forma_pago_registrada." / ".$cfdi_comprobante_metodo_de_pago;
            } else {
              if ($forma_pago_registrada != "") {
                $f_m_pago_cfdi = $forma_pago_registrada;
              } else {
                $f_m_pago_cfdi = $cfdi_comprobante_metodo_de_pago;
              }
            }

            $folio_e_cuenta = "";
            switch ($vECuenta->tipo_registro_e_cuenta) {
              case 'NOMINA':
                $folio_e_cuenta = "NOM-EF-".$JwtAuth->generarFolio($vECuenta->folio_movimiento).(!is_null($vECuenta->sub_folio_movimiento) ? '-'.$vECuenta->sub_folio_movimiento : '');
                break;
              case 'ESPECIE':
                $folio_e_cuenta = "NOM-ES-".$JwtAuth->generarFolio($vECuenta->folio_movimiento).(!is_null($vECuenta->sub_folio_movimiento) ? '-'.$vECuenta->sub_folio_movimiento : '');
                break;
              case 'PAGO':
                $folio_e_cuenta = "PAGO-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              case 'PAGO-CANCELADO':
                $folio_e_cuenta = "PCAN-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              case 'PAGO-ESP-CANCELADO':
                $folio_e_cuenta = "PCAN-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              default:
                $folio_e_cuenta = "";
                break;
            }

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "NOMINA" || $vECuenta->tipo_registro_e_cuenta == "ESPECIE" || $vECuenta->tipo_registro_e_cuenta == "CANCELACION" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_acreedor) == 0 ? $e_cuenta_haber - $e_cuenta_debe : ($estado_cuenta_acreedor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_haber) - $e_cuenta_debe;

            $cancelacion_doc_anterior = "";
            switch ($vECuenta->tipo_registro_e_cuenta) {
              case 'PAGO-CANCELADO':
                $cancelacion_doc_anterior = "PAGO-".$JwtAuth->generarFolio(
                  DB::table("fnzs_pagos_pago")
                  ->where("id",$vECuenta->doc_asociado)
                  ->value("folio_pagos")
                );
                break;
              case 'PAGO-ESP-CANCELADO':
                $cancelacion_doc_anterior = "PAGO-".$JwtAuth->generarFolio(
                  DB::table("fnzs_pagos_pago")
                  ->where("id",$vECuenta->doc_asociado)
                  ->value("folio_pagos")
                );
                break;
              case 'MOVIMIENTO-CANCELADO':
                $cancelacion_doc_anterior = "ACRMOV-".$JwtAuth->generarFolio(
                  DB::table("fnzs_catalogo_acreedores_movimientos")
                  ->where("id",$vECuenta->doc_asociado)
                  ->value("folio_acre_mov")
                );
                break;
              default:
                $cancelacion_doc_anterior = "";
                break;
            }

            $row_cuenta_estado = array(
              "contador" => $contador, 
              "tipo_registro_e_cuenta" => $vECuenta->tipo_registro_e_cuenta,
              "folio_e_cuenta" => $folio_e_cuenta,
              "movimiento_token" => $token_movimiento,
              //cancelaciones
              "cancelacion_doc_anterior" => $cancelacion_doc_anterior,

              //neutrales
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => "---",
              "forma_pago_cfdi" => $forma_pago_registrada,
              "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
              "f_m_pago_cfdi" => $f_m_pago_cfdi,
              "observacionesPago" => $payment_observaciones,
              "pago_moneda" => $vECuenta->moneda_movimiento,
							"pago_moneda_decimales" =>$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),
              "monto_pago" => "$".number_format($vECuenta->monto_movimiento * $vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_debe" => $e_cuenta_debe,
              "estado_cuenta_debe_format" => "$".number_format($e_cuenta_debe,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_haber" => $e_cuenta_haber,
              "estado_cuenta_haber_format" => "$".number_format($e_cuenta_haber,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_saldo" => $e_cuenta_saldo,
              "estado_cuenta_saldo_format" => "$".number_format($e_cuenta_saldo,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento"
            );
            $estado_cuenta_acreedor[] = $row_cuenta_estado;
            ++$contador;
          }

          $acreedor_deuda_total = 0;
          $acreedor_deuda_restante = 0;
          $acreedor_deuda_debe = 0;
          $acreedor_deuda_haber = 0;
          $acreedor_deuda_saldo = 0;
          $pagos_acreedor_moneda = "";

          for ($i=0; $i < count($estado_cuenta_acreedor); $i++) { 
            $pagos_acreedor_moneda = $vECuenta->moneda_movimiento;
            $acreedor_deuda_debe = $acreedor_deuda_debe + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_debe"] ?? 0);
            $acreedor_deuda_haber = $acreedor_deuda_haber + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $acreedor_deuda_saldo = floatval($acreedor_deuda_haber ?? 0) - floatval($acreedor_deuda_debe ?? 0);
          //echo $acreedor_deuda_haber." ".$acreedor_deuda_debe." ".$acreedor_deuda_saldo;

          $rowEmpleado = array(
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "folio_empleado" => $folio_empleado,
            "nombre_completo" => $nombre_completo,
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? $JwtAuth->convierteEpocFechaHtml('UTC',$vEmploy->fecha_alta_en_empresa) : '',

            "deuda_al_acreedor" => "$".number_format($acreedor_deuda_total,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_debe" => "$".number_format($acreedor_deuda_debe,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_haber" => "$".number_format($acreedor_deuda_haber,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_saldo_simple" => $acreedor_deuda_saldo,
            "acr_total_saldo" => "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_saldo_aplicar" => 0,
            "acr_total_saldo_restante_simple" => $acreedor_deuda_saldo > 0 ? $acreedor_deuda_saldo : 0,
            "acr_total_saldo_restante" => "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "estado_de_cuenta" => $estado_cuenta_acreedor
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleado_info" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}