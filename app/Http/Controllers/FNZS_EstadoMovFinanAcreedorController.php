<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FNZS_EstadoMovFinanAcreedorController extends Controller{
  private function saldoInicialAcreedorByToken($token_cat_acreedores,$empresa,$fechaInicio){
    $saldo_inicial_progresivo = 0;
    $pagosRealizados = DB::table("fnzs_pagos_pago AS pago")
    ->join("main_empresas AS emp", "pago.empresa", "emp.id")
    ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
    ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
    ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
    ->where([
      "acreedor.token_cat_acreedores" => $token_cat_acreedores,
      "emp.empresa_token" => $empresa
    ])
    ->where("pago.fecha_contabilizacion", '<', $fechaInicio)
    ->select([
      "emp.zona_horaria",
      "pago.token_pagos AS token_movimiento",
      DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
      "pago.folio_pagos AS folio_movimiento",
      DB::raw("'---' AS doc_asociado"),
      "pago.token_pagos AS id_registro",
      "pago.fecha_contabilizacion AS fecha_contabilizacion",
      "pago.observacionesPago AS observaciones",
      "pago.forma_pago_pago AS forma_pago_pago",
      "pago.monto_pago AS monto_movimiento",
      "pago.tipo_cambio AS tipo_cambio_movimiento",
      "pago.p_moneda AS moneda_movimiento",
      DB::raw("NULL AS movimiento_id"),
    ]);

    $pagosCancelados = DB::table("fnzs_pagos_pago AS pago")
    ->join("main_empresas AS emp", "pago.empresa", "emp.id")
    ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
    ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
    ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
    ->where([
      "pago.pago_cancelado" => TRUE,
      "acreedor.token_cat_acreedores" => $token_cat_acreedores,
      "emp.empresa_token" => $empresa
    ])
    ->where("pago.pago_fecha_contabilizacion_cancelacion", '<', $fechaInicio)
    ->select([
      "emp.zona_horaria",
      "pago.token_pagos AS token_movimiento",
      DB::raw("'PAGO-CANCELADO' AS tipo_registro_e_cuenta"),
      "pago.pago_folio_cancelacion AS folio_movimiento",
      "pago.id AS doc_asociado",
      "pago.token_pagos AS id_registro",
      "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
      "pago.pago_comentarios_cancelacion AS observaciones",
      "pago.forma_pago_pago AS forma_pago_pago",
      "pago.monto_pago AS monto_movimiento",
      "pago.tipo_cambio AS tipo_cambio_movimiento",
      "pago.p_moneda AS moneda_movimiento",
      DB::raw("NULL AS movimiento_id"),
    ]);

    $movimientosRealizados = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
    ->join("main_empresas AS emp", "mov.acre_empresa", "emp.id")
    ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
    ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
    ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
    ->whereNull("mov.acre_mov_asociado")
    ->where([
      "acreedor.token_cat_acreedores" => $token_cat_acreedores,
      "emp.empresa_token" => $empresa
    ])
    ->where("mov.acre_fecha_contabilizacion", '<', $fechaInicio)
    ->select([
      "emp.zona_horaria",
      "mov.token_acre_mov AS token_movimiento",
      DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
      "mov.folio_acre_mov AS folio_movimiento",
      DB::raw("'---' AS doc_asociado"),
      "mov.token_acre_mov AS id_registro",
      "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
      "mov.acre_observaciones_mov AS observaciones",
      DB::raw("'---' AS forma_pago_pago"),
      "mov.acre_monto_mov AS monto_movimiento",
      "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
      "mov.acre_mov_moneda AS moneda_movimiento",
      "mov.id AS movimiento_id",
    ]);

    $movimientosCancelados = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
    ->join("main_empresas AS emp", "mov.acre_empresa", "emp.id")
    ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
    ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
    ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
    ->where([
      "mov.acre_mov_cancelado" => FALSE,
      "acreedor.token_cat_acreedores" => $token_cat_acreedores,
      "emp.empresa_token" => $empresa
    ])
    ->whereNotNull("mov.acre_mov_asociado")
    ->where("mov.acre_fecha_contabilizacion", '<', $fechaInicio)
    ->whereIn('mov.acre_mov_asociado', function ($query) {
      $query->select('id')->from('fnzs_catalogo_acreedores_movimientos')
      ->where("acre_mov_cancelado", TRUE);
    })
    ->select([
      "emp.zona_horaria",
      "mov.token_acre_mov AS token_movimiento",
      DB::raw("'MOVIMIENTO-CANCELADO' AS tipo_registro_e_cuenta"),
      "mov.folio_acre_mov AS folio_movimiento",
      "mov.acre_mov_asociado AS doc_asociado",
      "mov.token_acre_mov AS id_registro",
      "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
      "mov.acre_observaciones_mov AS observaciones",
      DB::raw("'---' AS forma_pago_pago"),
      "mov.acre_monto_mov AS monto_movimiento",
      "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
      "mov.acre_mov_moneda AS moneda_movimiento",
      "mov.id AS movimiento_id",
    ]);
    
    $unionEstadoDeCuenta = $pagosRealizados->unionAll($pagosCancelados)->unionAll($movimientosRealizados)->unionAll($movimientosCancelados);
    
    $queryMovimientos = DB::table(DB::raw("({$unionEstadoDeCuenta->toSql()}) as estado_cuenta"))
    ->mergeBindings($unionEstadoDeCuenta) // Importante para no perder los parámetros del WHERE
    ->orderBy("fecha_contabilizacion", "asc")
    ->get();

    foreach ($queryMovimientos as $vMov) {
      $monto_applc = (float)$vMov->monto_movimiento * ($vMov->tipo_cambio_movimiento ? $vMov->tipo_cambio_movimiento : 1);
      $movimiento_debe = $vMov->tipo_registro_e_cuenta == "MOVIMIENTO" || $vMov->tipo_registro_e_cuenta == "MOVIMIENTO-CANCELADO" ? $monto_applc : 0;
      $movimiento_haber = $vMov->tipo_registro_e_cuenta == "PAGO" || $vMov->tipo_registro_e_cuenta == "PAGO-CANCELADO" ? $monto_applc : 0;

      $saldo_inicial_progresivo += ($movimiento_debe - $movimiento_haber);
    }
    return $saldo_inicial_progresivo;
  }

  public function movimientosFinancierosAcreedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_acreedores' => 'required|string',
      'periodo_inicio' => 'required|date_format:Y-m-d',
      'periodo_fin' => 'required|date_format:Y-m-d',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
        'message' => 'La infomación que ha intantado consultar es invalida',
        'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $token_cat_acreedores = $request->input('token_cat_acreedores');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
      $fechaFin = strtotime($periodo_fin . " 23:59:59");

      $pagosRealizados = DB::table("fnzs_pagos_pago AS pago")
      ->join("main_empresas AS emp", "pago.empresa", "emp.id")
      ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
      ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
      ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
      ->where([
        "acreedor.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa
      ])
      ->whereBetween("pago.fecha_contabilizacion", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "pago.token_pagos AS token_movimiento",
        DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
        "pago.folio_pagos AS folio_movimiento",
        DB::raw("'---' AS doc_asociado"),
        "pago.token_pagos AS id_registro",
        "pago.fecha_contabilizacion AS fecha_contabilizacion",
        "pago.observacionesPago AS observaciones",
        "pago.forma_pago_pago AS forma_pago_pago",
        "pago.monto_pago AS monto_movimiento",
        "pago.tipo_cambio AS tipo_cambio_movimiento",
        "pago.p_moneda AS moneda_movimiento",
        DB::raw("NULL AS movimiento_id"),
      ]);

      $pagosCancelados = DB::table("fnzs_pagos_pago AS pago")
      ->join("main_empresas AS emp", "pago.empresa", "emp.id")
      ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
      ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
      ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
      ->where([
        "pago.pago_cancelado" => TRUE,
        "acreedor.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa
      ])
      ->whereBetween("pago.pago_fecha_contabilizacion_cancelacion", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "pago.token_pagos AS token_movimiento",
        DB::raw("'PAGO-CANCELADO' AS tipo_registro_e_cuenta"),
        "pago.pago_folio_cancelacion AS folio_movimiento",
        "pago.id AS doc_asociado",
        "pago.token_pagos AS id_registro",
        "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
        "pago.pago_comentarios_cancelacion AS observaciones",
        "pago.forma_pago_pago AS forma_pago_pago",
        "pago.monto_pago AS monto_movimiento",
        "pago.tipo_cambio AS tipo_cambio_movimiento",
        "pago.p_moneda AS moneda_movimiento",
        DB::raw("NULL AS movimiento_id"),
      ]);

      $movimientosRealizados = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
      ->join("main_empresas AS emp", "mov.acre_empresa", "emp.id")
      ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
      ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
      ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
      ->whereNull("mov.acre_mov_asociado")
      ->where([
        "acreedor.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa
      ])
      ->whereBetween("mov.acre_fecha_contabilizacion", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "mov.token_acre_mov AS token_movimiento",
        DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
        "mov.folio_acre_mov AS folio_movimiento",
        DB::raw("'---' AS doc_asociado"),
        "mov.token_acre_mov AS id_registro",
        "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
        "mov.acre_observaciones_mov AS observaciones",
        DB::raw("'---' AS forma_pago_pago"),
        "mov.acre_monto_mov AS monto_movimiento",
        "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
        "mov.acre_mov_moneda AS moneda_movimiento",
        "mov.id AS movimiento_id",
      ]);

      $movimientosCancelados = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
      ->join("main_empresas AS emp", "mov.acre_empresa", "emp.id")
      ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
      ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
      ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
      ->where([
        "mov.acre_mov_cancelado" => FALSE,
        "acreedor.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa
      ])
      ->whereNotNull("mov.acre_mov_asociado")
      ->whereBetween("mov.acre_fecha_contabilizacion", [$fechaInicio, $fechaFin])
      ->whereIn('mov.acre_mov_asociado', function ($query) {
        $query->select('id')->from('fnzs_catalogo_acreedores_movimientos')
        ->where("acre_mov_cancelado", TRUE);
      })
      ->select([
        "emp.zona_horaria",
        "mov.token_acre_mov AS token_movimiento",
        DB::raw("'MOVIMIENTO-CANCELADO' AS tipo_registro_e_cuenta"),
        "mov.folio_acre_mov AS folio_movimiento",
        "mov.acre_mov_asociado AS doc_asociado",
        "mov.token_acre_mov AS id_registro",
        "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
        "mov.acre_observaciones_mov AS observaciones",
        DB::raw("'---' AS forma_pago_pago"),
        "mov.acre_monto_mov AS monto_movimiento",
        "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
        "mov.acre_mov_moneda AS moneda_movimiento",
        "mov.id AS movimiento_id",
      ]);
      
      $unionEstadoDeCuenta = $pagosRealizados->unionAll($pagosCancelados)->unionAll($movimientosRealizados)->unionAll($movimientosCancelados);
      
      $queryMovimientos = DB::table(DB::raw("({$unionEstadoDeCuenta->toSql()}) as estado_cuenta"))
      ->mergeBindings($unionEstadoDeCuenta) // Importante para no perder los parámetros del WHERE
      ->orderBy("fecha_contabilizacion", "asc")
      ->get();

      if ($queryMovimientos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron movimientos financieros registrados'
        );
      } else {
        $empresaData = DB::table("main_empresas")->where("empresa_token",$empresa)->first();
        $codeMoneda = $empresaData->e_moneda_code;
        $decimalesMoneda = $empresaData->e_moneda_decimales;
        $arrayMovimientos = [];
        
        $saldo_inicial_acree = $this->saldoInicialAcreedorByToken($token_cat_acreedores,$empresa,$fechaInicio);
        $saldo_acumulado_depositos = 0;
        $saldo_acumulado_retiros = 0;
        $saldo_acumulado_progresivo = $saldo_inicial_acree;
        $contador = 0;
        
        $idPagos = $queryMovimientos->pluck('token_movimiento')->filter()->unique()->toArray();
        $mapMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
        ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->whereIn("payment.token_pagos", $idPagos)
        ->select(
          "payment.token_pagos AS id_pagos",
          "cfdi.cfdi_comprobante_metodo_de_pago"
        )
        ->get()->keyBy('id');
        
        foreach ($queryMovimientos as $vMov) {
          //da_te_default_timezone_set($vMov->zona_horaria);
          $token_movimiento = $vMov->token_movimiento;
          $f_m_pago_cfdi = "";
          $forma_pago_registrada = $vMov->forma_pago_pago !== '---' ? $vMov->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($vMov->forma_pago_pago) : '';

          $cfdi_comprobante_metodo_de_pago = "";
          if ($vMov->tipo_registro_e_cuenta == "PAGO" || $vMov->tipo_registro_e_cuenta == "PAGO-CANCELADO") {
            $queryMetodoPago = $mapMetodoPago->get($vMov->token_movimiento);
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
          $cancelacion_doc_anterior = "";
          switch ($vMov->tipo_registro_e_cuenta) {
            case 'PAGO':
              $folio_e_cuenta = "PAGO-".$JwtAuth->generarFolio($vMov->folio_movimiento);
              break;
            case 'PAGO-CANCELADO':
              $folio_e_cuenta = "PCAN-".$JwtAuth->generarFolio($vMov->folio_movimiento);
              $cancelacion_doc_anterior = "PAGO-".$JwtAuth->generarFolio(DB::table("fnzs_pagos_pago")->where("id",$vMov->doc_asociado)->value("folio_pagos"));
              break;
            case 'MOVIMIENTO':
              $folio_e_cuenta = "ACRMOV-".$JwtAuth->generarFolio($vMov->folio_movimiento);
              break;
            case 'MOVIMIENTO-CANCELADO':
              $folio_e_cuenta = "ACRMOV-".$JwtAuth->generarFolio($vMov->folio_movimiento);
              $cancelacion_doc_anterior = "ACRMOV-".$JwtAuth->generarFolio(DB::table("fnzs_catalogo_acreedores_movimientos")->where("id",$vMov->doc_asociado)->value("folio_acre_mov"));
              break;
            default:
              $folio_e_cuenta = "";
              break;
          }
          
          $payment_observaciones = !is_null($vMov->observaciones) ? $JwtAuth->desencriptar($vMov->observaciones) : '';

          $mov_f_cont = $vMov->fecha_contabilizacion;
          $fecha_movimiento = !is_null($mov_f_cont) && $mov_f_cont != '' ? $JwtAuth->mostrarUnixAFechaMexico($mov_f_cont) : '';
          $fecha_movimiento_excel = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('d/m/Y',$mov_f_cont) : '';

          $monto_applc = (float)$vMov->monto_movimiento * ($vMov->tipo_cambio_movimiento ? $vMov->tipo_cambio_movimiento : 1);
          $movimiento_debe = $vMov->tipo_registro_e_cuenta == "MOVIMIENTO" || $vMov->tipo_registro_e_cuenta == "MOVIMIENTO-CANCELADO" ? $monto_applc : 0;
          $movimiento_haber = $vMov->tipo_registro_e_cuenta == "PAGO" || $vMov->tipo_registro_e_cuenta == "PAGO-CANCELADO" ? $monto_applc : 0;

          $saldo_acumulado_depositos += $movimiento_debe;
          $saldo_acumulado_retiros += $movimiento_haber;
          $saldo_acumulado_progresivo += ($movimiento_debe - $movimiento_haber);

          $arrayMovimientos[] = [
            "contador" => $contador,
            "tipo_registro_e_cuenta" => $vMov->tipo_registro_e_cuenta,
            "token_movimiento" => $token_movimiento,
            "folio_e_cuenta" => $folio_e_cuenta,
            "cancelacion_doc_anterior" => $cancelacion_doc_anterior,
            "fecha_contabilizacion" => $fecha_movimiento,
            "fecha_contabilizacion_excel" => $fecha_movimiento_excel,
            "tipo_cambio_movimiento" => "$".number_format($vMov->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vMov->moneda_movimiento),'.',',')." $vMov->moneda_movimiento",
            "forma_pago_vinculada" => "---",
            "forma_pago_cfdi" => $forma_pago_registrada,
            "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
            "f_m_pago_cfdi" => $f_m_pago_cfdi,
            "observacionesPago" => $payment_observaciones,
            "pago_moneda" => $vMov->moneda_movimiento,
            "pago_moneda_decimales" => $decimalesMoneda,

            "monto_movimiento" => "$".number_format($monto_applc,$decimalesMoneda,'.', ',')." $codeMoneda",
            "mov_monto_debe" => $movimiento_debe,
            "mov_monto_debe_format" => "$".number_format($movimiento_debe,$decimalesMoneda,'.', ',')." $codeMoneda",
            "mov_monto_haber" => $movimiento_haber,
            "mov_monto_haber_format" => "$".number_format($movimiento_haber,$decimalesMoneda,'.', ',')." $codeMoneda",
            "mov_monto_saldo" => $saldo_acumulado_progresivo,
            "mov_monto_saldo_format" => "$".number_format($saldo_acumulado_progresivo,$decimalesMoneda,'.', ',')." $codeMoneda"
          ];
          ++$contador;
        }

        $acree_result_saldo = ($saldo_inicial_acree + $saldo_acumulado_depositos) - $saldo_acumulado_retiros;

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "total_movimientos" => count($arrayMovimientos),
          "mov_moneda" => $codeMoneda,
          "mov_moneda_decimales" => $decimalesMoneda,
          "movimientos_saldo_inicial" => "$".number_format($saldo_inicial_acree,$decimalesMoneda,'.', ','),
          "movimientos_deposito" => "$".number_format($saldo_acumulado_depositos,$decimalesMoneda,'.', ','),
          "movimientos_retiro" => "$".number_format($saldo_acumulado_retiros,$decimalesMoneda,'.', ','),
          "saldo_final" => "$".number_format($acree_result_saldo,$decimalesMoneda,'.', ','),
          "movimientos" => $arrayMovimientos,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}