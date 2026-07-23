<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MovimientosBancariosModelo;

class FNZS_EstadoMovFinanProveedorController extends Controller{
  private function saldoInicialProveedorByToken($token_cat_proveedores,$empresa,$fechaInicio){
    $saldo_inicial_progresivo = 0;
    $movimientosProvByCompraOrdenPago = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_pagos_pago AS pay", "fnzs_actividad_movimientos.pago", "=", "pay.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "pay.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
    ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
    ->where([
      "catprov.token_cat_proveedores" => $token_cat_proveedores,
      "emp.empresa_token" => $empresa
    ])      
    ->where('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->select([
      "fnzs_actividad_movimientos.id AS mov_id",
      DB::raw("'orden' AS medio_pago"),
      "fnzs_actividad_movimientos.fecha_contabilizacion_movimiento",
      "fnzs_actividad_movimientos.monto_aplicado",
      "fnzs_actividad_movimientos.tipo_cambio_movimiento",
      "fnzs_actividad_movimientos.tipo_movimiento",
      "fnzs_actividad_movimientos.pago AS pago_ref_id"
    ]);

    $movimientosProvByAnticipoOrdenPago = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_pagos_pago AS pay", "fnzs_actividad_movimientos.pago", "=", "pay.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "pay.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.anticipo_proveedor", "=", "ant.uuid_anticipo")
    ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "=", "catprov.id")
    ->where([
      "catprov.token_cat_proveedores" => $token_cat_proveedores,
      "emp.empresa_token" => $empresa
    ])      
    ->where('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->select([
      "fnzs_actividad_movimientos.id AS mov_id",
      DB::raw("'orden' AS medio_pago"),
      "fnzs_actividad_movimientos.fecha_contabilizacion_movimiento",
      "fnzs_actividad_movimientos.monto_aplicado",
      "fnzs_actividad_movimientos.tipo_cambio_movimiento",
      "fnzs_actividad_movimientos.tipo_movimiento",
      "fnzs_actividad_movimientos.pago AS pago_ref_id"
    ]);

    $movimientosProvDirecto = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_pagos_pago AS pay", "fnzs_actividad_movimientos.pago", "=", "pay.id")
    ->join("eegr_catalogo_proveedores AS catprov", "pay.vinc_proveedor", "=", "catprov.id")
    ->whereNotIn('pay.id', function($queryCFDI) {
      $queryCFDI->select('pago_realizado')->from('fnzs_pagos_pago_ordenes_vinculadas');
    })
    ->where([
      "catprov.token_cat_proveedores" => $token_cat_proveedores,
      "emp.empresa_token" => $empresa
    ])
    ->where('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->select([
      "fnzs_actividad_movimientos.id AS mov_id",
      DB::raw("'directo' AS medio_pago"),
      "fnzs_actividad_movimientos.fecha_contabilizacion_movimiento",
      "fnzs_actividad_movimientos.monto_aplicado",
      "fnzs_actividad_movimientos.tipo_cambio_movimiento",
      "fnzs_actividad_movimientos.tipo_movimiento",
      "fnzs_actividad_movimientos.pago AS pago_ref_id"
    ]);
    
    $moviProvBuyNotPag = DB::table('fnzs_pagos_orden as order')
    ->join('main_empresas as emp', 'order.empresa', '=', 'emp.id')
    ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
    ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
    ->where([
      "order.status_ordenPago" => TRUE,
      "catprov.token_cat_proveedores" => $token_cat_proveedores,
      "emp.empresa_token" => $empresa
    ])      
    ->where('buy.fecha_contabilizacion', '<', $fechaInicio)
    ->select([
      "buy.id AS mov_id",
      DB::raw("'compra_registrada' AS medio_pago"),
      "buy.fecha_contabilizacion AS fecha_contabilizacion_movimiento",
      DB::raw("'1.00' AS monto_aplicado"),
      "buy.tipo_de_cambio AS tipo_cambio_movimiento",
      DB::raw("'S' AS tipo_movimiento"),
      DB::raw("'---' AS pago_ref_id")
    ]);

    $querySaldoInicial = $movimientosProvByCompraOrdenPago->unionAll($movimientosProvByAnticipoOrdenPago)->unionAll($movimientosProvDirecto)->unionAll($moviProvBuyNotPag)
    ->orderBy('fecha_contabilizacion_movimiento', 'ASC')
    ->get();
    
    $idCompras = $querySaldoInicial->pluck('mov_id')->filter()->unique()->toArray();
    $comprasMap = DB::table('eegr_compras')->whereIn('id', $idCompras)->get()->keyBy('id');
    $detalleCompraMap = DB::table("eegr_compras_detalle AS detcomp")
    ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
    ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
    ->whereIn('comp.token_compras', $comprasMap->pluck('token_compras')->unique())
    ->where('emp.empresa_token',$empresa)
    ->select(
      'comp.token_compras AS id_compras',
      'detcomp.precio_unitario','detcomp.cantidad','detcomp.descuento','detcomp.traslados_total','detcomp.retenciones_total',
      'detcomp.tipo_de_cambio_detalle_compra'
    )
    ->get()->groupBy('id_compras');

    foreach ($querySaldoInicial as $iMov) {
      $oBuy = null;
      
      if ($iMov->medio_pago == 'compra_registrada') {
        $oBuy = $comprasMap->get($iMov->mov_id);
      }
      
      $monto_applc = (float)$iMov->monto_aplicado * ($iMov->tipo_cambio_movimiento ? $iMov->tipo_cambio_movimiento : 1);
      //$movimiento_i_debe = $iMov->tipo_movimiento == 'S' ? $monto_applc : 0;
      
      $movimiento_i_debe = 0;
      if ($iMov->tipo_movimiento == 'S') {
        if ( $iMov->medio_pago == 'compra_registrada' && $oBuy) {
          $detalleCompraLista = $detalleCompraMap->get($oBuy->token_compras) ?? collect([]);
          foreach ($detalleCompraLista as $vDetBuy) {
            $subtotal_convert = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
            $importe_concepto_convert = $subtotal_convert - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
            $movimiento_i_debe += $importe_concepto_convert;
          }
          $movimiento_i_debe -= $oBuy->anticipo;
        } else {
          $movimiento_i_debe = $monto_applc;
        }
      }

      $movimiento_i_haber = $iMov->tipo_movimiento == 'R' ? $monto_applc : 0;
      
      $saldo_inicial_progresivo += ($movimiento_i_debe - $movimiento_i_haber);
    }
    return $saldo_inicial_progresivo;
  }

  private function parteRelMovPagoDirecto($pago,$JwtAuth){
    $vPago = DB::table("fnzs_pagos_pago AS pag")

    ->leftJoin("eegr_catalogo_proveedores AS catprov", "pag.vinc_proveedor", "=", "catprov.id")
    ->leftJoin("sos_personas AS prov_data", "catprov.proveedor", "=", "prov_data.id")

    ->leftJoin("ingr_catalogo_clientes AS catcli", "pag.vinc_cliente", "=", "catcli.id")
    ->leftJoin("sos_personas AS client_data", "catcli.cliente", "=", "client_data.id")
    
    ->leftJoin("vhum_empleados_catalogo AS trab", "pag.vinc_empleado", "=", "trab.id")
    ->leftJoin("sos_personas AS trab_data", "trab.empleado_name", "=", "trab_data.id")

    ->leftJoin("vhum_nominas_main AS nomiMain", "pag.vinc_nomina", "=", "nomiMain.id")
    ->leftJoin("main_empresas AS nomi_emp", "nomiMain.nomina_empresa", "=", "nomi_emp.id")
    ->leftJoin("sos_personas AS nomi_data", "nomi_emp.persona", "=", "nomi_data.id")

    ->leftJoin("vhum_nominas_especie AS nomiEsp", "pag.vinc_nomina_especie", "=", "nomiEsp.id")
    ->leftJoin("main_empresas AS nm_esp_emp", "nomiEsp.nomina_esp_empresa", "=", "nm_esp_emp.id")
    ->leftJoin("sos_personas AS nm_esp_data", "nm_esp_emp.persona", "=", "nm_esp_data.id")

    ->where('pag.id',$pago)

    ->select([
      'prov_data.nombre_extendido AS prvName',
      
      'client_data.nombre_extendido AS cliName',
      
      'trab_data.paterno AS trab_paterno',
      'trab_data.materno AS trab_materno',
      'trab_data.nombre AS trab_nombre',

      'nomi_data.abrev_nombre AS nomiAbrevNombre',
      'nomi_data.denominacion_rs AS nomiRazonSocial', 
      'nomi_data.paterno AS nomiPaterno',
      'nomi_data.materno AS nomiMaterno',
      'nomi_data.nombre AS nomiNombre',

      'nm_esp_data.abrev_nombre AS espAbrevNombre',
      'nm_esp_data.denominacion_rs AS espRazonSocial', 
      'nm_esp_data.paterno AS espPaterno',
      'nm_esp_data.materno AS espMaterno',
      'nm_esp_data.nombre AS espNombre',
    ])
    
    ->first();

    if (!$vPago) return "";

    if ($vPago->prvName) {
      return $JwtAuth->desencriptar($vPago->prvName);
    } 
    if ($vPago->cliName) {
      return $JwtAuth->desencriptar($vPago->cliName);
    } 
    if ($vPago->trab_nombre) {
      $paterno = $JwtAuth->desencriptar($vPago->trab_paterno);
      $materno = $JwtAuth->desencriptar($vPago->trab_materno);
      $nombre  = $JwtAuth->desencriptar($vPago->trab_nombre);
      return trim("$paterno $materno $nombre");
    } 
    if ($vPago->nomiAbrevNombre || $vPago->nomiRazonSocial || $vPago->nomiNombre) {
      $nomiEmpresa = $vPago->nomiRazonSocial == '' ? $JwtAuth->desencriptarNombres($vPago->nomiPaterno,$vPago->nomiMaterno,$vPago->nomiNombre) : $JwtAuth->desencriptar($vPago->nomiRazonSocial);
      return trim("Nomina " . ($vPago->nomiAbrevNombre ?? "") . " " . $nomiEmpresa);
    } 
    if ($vPago->espAbrevNombre || $vPago->espRazonSocial || $vPago->espNombre) {
      $espEmpresa = $vPago->espRazonSocial == '' ? $JwtAuth->desencriptarNombres($vPago->espPaterno,$vPago->espMaterno,$vPago->espNombre) : $JwtAuth->desencriptar($vPago->espRazonSocial);
      return trim("Nomina en especie " . ($vPago->espAbrevNombre ?? "") . " " . $espEmpresa);
    }
  }

  private function parteRelMovPagoByOrden($pago,$JwtAuth){
    $vOrdenPago = DB::table("fnzs_pagos_orden AS order")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "order.id", "=", "ordpv.orden_pago_vinculada")
    ->join("fnzs_pagos_pago AS pay", "ordpv.pago_realizado", "=", "pay.id")

    ->leftJoin("eegr_catalogo_proveedores AS catprov", "order.ord_proveedor", "=", "catprov.id")
    ->leftJoin("sos_personas AS prov_data", "catprov.proveedor", "=", "prov_data.id")

    ->leftJoin("ingr_catalogo_clientes AS catcli", "order.ord_cliente", "=", "catcli.id")
    ->leftJoin("sos_personas AS client_data", "catcli.cliente", "=", "client_data.id")
    
    ->leftJoin("fnzs_catalogo_deudores AS ord_deu", "order.ord_anticipo", "=", "ord_deu.id")

    ->leftJoin("terc_reembolso_main AS reemMain", "order.reembolso_main", "=", "reemMain.id")
    ->leftJoin("fnzs_catalogo_acreedores AS catAcree", "reemMain.user_acreedor", "=", "catAcree.id")

    ->leftJoin("vhum_nominas_main AS nomiMain", "order.nomina_main", "=", "nomiMain.id")
    ->leftJoin("main_empresas AS nomi_emp", "nomiMain.nomina_empresa", "=", "nomi_emp.id")
    ->leftJoin("sos_personas AS nomi_data", "nomi_emp.persona", "=", "nomi_data.id")

    ->leftJoin("vhum_nominas_especie AS nomiEsp", "order.nomina_en_especie", "=", "nomiEsp.id")
    ->leftJoin("main_empresas AS nm_esp_emp", "nomiEsp.nomina_esp_empresa", "=", "nm_esp_emp.id")
    ->leftJoin("sos_personas AS nm_esp_data", "nm_esp_emp.persona", "=", "nm_esp_data.id")

    ->leftJoin("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
    ->leftJoin("fnzs_catalogos_fed_estados_municipios AS fedEstImp", "nomImp.nomi_imp_estado", "=", "fedEstImp.id")

    ->leftJoin("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "social_main.id")
    ->leftJoin("fnzs_catalogos_fed_estados_municipios AS fedEstSocial", "social_main.proveedor_imss", "=", "fedEstSocial.id")

    ->leftJoin("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
    ->leftJoin("fnzs_catalogos_fed_estados_municipios AS fedEstImpFed", "fedMain.proveedor_sat", "=", "fedEstImpFed.id")

    ->where("pay.id",$pago)

    ->select([
      'prov_data.nombre_extendido AS prvName',
      
      'client_data.nombre_extendido AS cliName',

      'ord_deu.deu_titular AS deu_titular',

      'catAcree.acr_titular AS acr_titular',

      'nomi_data.abrev_nombre AS nomiAbrevNombre',
      'nomi_data.denominacion_rs AS nomiRazonSocial', 
      'nomi_data.paterno AS nomiPaterno',
      'nomi_data.materno AS nomiMaterno',
      'nomi_data.nombre AS nomiNombre',

      'nm_esp_data.abrev_nombre AS espAbrevNombre',
      'nm_esp_data.denominacion_rs AS espRazonSocial', 
      'nm_esp_data.paterno AS espPaterno',
      'nm_esp_data.materno AS espMaterno',
      'nm_esp_data.nombre AS espNombre',

      'fedEstImp.fed_est_mun_folio AS fei_folio',
      'fedEstImp.fed_est_mun_subfolio AS fei_subfolio',
      'fedEstImp.fed_est_mun_entidad AS fei_entidad',

      'fedEstSocial.fed_est_mun_folio AS fes_folio',
      'fedEstSocial.fed_est_mun_subfolio AS fes_subfolio',
      'fedEstSocial.fed_est_mun_entidad AS fes_entidad',

      'fedEstImpFed.fed_est_mun_folio AS fdf_folio',
      'fedEstImpFed.fed_est_mun_subfolio AS fdf_subfolio',
      'fedEstImpFed.fed_est_mun_entidad AS fdf_entidad',
    ])

    ->first();

    if (!$vOrdenPago) return "";

    if ($vOrdenPago->prvName) {
      return $JwtAuth->desencriptar($vOrdenPago->prvName);
    } 
    if ($vOrdenPago->cliName) {
      return $JwtAuth->desencriptar($vOrdenPago->cliName);
    } 
    if ($vOrdenPago->deu_titular) {
      return !is_null($vOrdenPago->deu_titular) ? $JwtAuth->desencriptar($vOrdenPago->deu_titular) : 'N/A';
    } 
    if ($vOrdenPago->acr_titular) {
      return !is_null($vOrdenPago->acr_titular) ? $JwtAuth->desencriptar($vOrdenPago->acr_titular) : 'N/A';
    } 
    if ($vOrdenPago->nomiAbrevNombre || $vOrdenPago->nomiRazonSocial || $vOrdenPago->nomiNombre) {
      $nomiEmpresa = $vOrdenPago->nomiRazonSocial == '' ? $JwtAuth->desencriptarNombres($vOrdenPago->nomiPaterno,$vOrdenPago->nomiMaterno,$vOrdenPago->nomiNombre) : $JwtAuth->desencriptar($vOrdenPago->nomiRazonSocial);
      return trim("Nomina " . ($vOrdenPago->nomiAbrevNombre ?? "") . " " . $nomiEmpresa);
    } 
    if ($vOrdenPago->espAbrevNombre || $vOrdenPago->espRazonSocial || $vOrdenPago->espNombre) {
      $espEmpresa = $vOrdenPago->espRazonSocial == '' ? $JwtAuth->desencriptarNombres($vOrdenPago->espPaterno,$vOrdenPago->espMaterno,$vOrdenPago->espNombre) : $JwtAuth->desencriptar($vOrdenPago->espRazonSocial);
      return trim("Nomina en especie " . ($vOrdenPago->espAbrevNombre ?? "") . " " . $espEmpresa);
    }
    if ($vOrdenPago->fei_folio && $vOrdenPago->fei_entidad) {//$vOrdenPago->fei_folio && $vOrdenPago->fei_subfolio && $vOrdenPago->fei_entidad
      $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vOrdenPago->fei_folio).(!is_null($vOrdenPago->fei_subfolio) ? '-'.$vOrdenPago->fei_subfolio : '');
      $fed_est_mun_entidad = $JwtAuth->desencriptar($vOrdenPago->fei_entidad);
      return "$fed_est_mun_folio $fed_est_mun_entidad";
    } 
    if ($vOrdenPago->fes_folio && $vOrdenPago->fes_entidad) {//$vOrdenPago->fes_folio && $vOrdenPago->fes_subfolio && $vOrdenPago->fes_entidad
      $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vOrdenPago->fes_folio).(!is_null($vOrdenPago->fes_subfolio) ? '-'.$vOrdenPago->fes_subfolio : '');
      $fed_est_mun_entidad = $JwtAuth->desencriptar($vOrdenPago->fes_entidad);
      return "$fed_est_mun_folio $fed_est_mun_entidad";
    } 
    if ($vOrdenPago->fdf_folio && $vOrdenPago->fdf_entidad) {//$vOrdenPago->fdf_folio && $vOrdenPago->fdf_subfolio && $vOrdenPago->fdf_entidad
      $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vOrdenPago->fdf_folio).(!is_null($vOrdenPago->fdf_subfolio) ? '-'.$vOrdenPago->fdf_subfolio : '');
      $fed_est_mun_entidad = $JwtAuth->desencriptar($vOrdenPago->fdf_entidad);
      return "$fed_est_mun_folio $fed_est_mun_entidad";
    }
    //return $parte_relacionada;
  }

  public function movimientosFinancierosProveedor(Request $request){
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
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
      $fechaFin = strtotime($periodo_fin . " 23:59:59");

      $movimientosProvByCompraOrdenPago = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("fnzs_pagos_pago AS pay", "fnzs_actividad_movimientos.pago", "=", "pay.id")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "pay.id", "=", "ordpv.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
      ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
      ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
      ->where([
        "catprov.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])      
      ->whereBetween("fnzs_actividad_movimientos.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "fnzs_actividad_movimientos.id AS mov_id",
        "fnzs_actividad_movimientos.token_movimiento",
        "fnzs_actividad_movimientos.folio_movimiento",
        "fnzs_actividad_movimientos.concepto_movimiento",
        "fnzs_actividad_movimientos.fecha_contabilizacion_movimiento",
        "catprov.token_cat_proveedores",
        "fnzs_actividad_movimientos.monto_aplicado",
        "fnzs_actividad_movimientos.tipo_cambio_movimiento",
        "pay.folio_pagos AS folio_doc_ant_asociado",
        DB::raw("'orden' AS medio_pago"),
        "fnzs_actividad_movimientos.pago AS pago_ref_id",
        "fnzs_actividad_movimientos.tipo_movimiento",
        "fnzs_actividad_movimientos.movimiento_asociado",
        "fnzs_actividad_movimientos.observaciones_movimiento"
      ]);

      $movimientosProvByAnticipoOrdenPago = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("fnzs_pagos_pago AS pay", "fnzs_actividad_movimientos.pago", "=", "pay.id")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "pay.id", "=", "ordpv.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
      ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.anticipo_proveedor", "=", "ant.uuid_anticipo")
      ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "=", "catprov.id")
      ->where([
        "catprov.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])      
      ->whereBetween("fnzs_actividad_movimientos.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "fnzs_actividad_movimientos.id AS mov_id",
        "fnzs_actividad_movimientos.token_movimiento",
        "fnzs_actividad_movimientos.folio_movimiento",
        "fnzs_actividad_movimientos.concepto_movimiento",
        "fnzs_actividad_movimientos.fecha_contabilizacion_movimiento",
        "catprov.token_cat_proveedores",
        "fnzs_actividad_movimientos.monto_aplicado",
        "fnzs_actividad_movimientos.tipo_cambio_movimiento",
        "pay.folio_pagos AS folio_doc_ant_asociado",
        DB::raw("'orden' AS medio_pago"),
        "fnzs_actividad_movimientos.pago AS pago_ref_id",
        "fnzs_actividad_movimientos.tipo_movimiento",
        "fnzs_actividad_movimientos.movimiento_asociado",
        "fnzs_actividad_movimientos.observaciones_movimiento"
      ]);

      $movimientosProvDirecto = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("fnzs_pagos_pago AS pay", "fnzs_actividad_movimientos.pago", "=", "pay.id")
      ->join("eegr_catalogo_proveedores AS catprov", "pay.vinc_proveedor", "=", "catprov.id")
      ->whereNotIn('pay.id', function($queryCFDI) {
        $queryCFDI->select('pago_realizado')->from('fnzs_pagos_pago_ordenes_vinculadas');
      })
      ->where([
        "catprov.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])
      ->whereBetween("fnzs_actividad_movimientos.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "fnzs_actividad_movimientos.id AS mov_id",
        "fnzs_actividad_movimientos.token_movimiento",
        "fnzs_actividad_movimientos.folio_movimiento",
        "fnzs_actividad_movimientos.concepto_movimiento",
        "fnzs_actividad_movimientos.fecha_contabilizacion_movimiento",
        "catprov.token_cat_proveedores",
        "fnzs_actividad_movimientos.monto_aplicado",
        "fnzs_actividad_movimientos.tipo_cambio_movimiento",
        "pay.folio_pagos AS folio_doc_ant_asociado",
        DB::raw("'directo' AS medio_pago"),
        "fnzs_actividad_movimientos.pago AS pago_ref_id",
        "fnzs_actividad_movimientos.tipo_movimiento",
        "fnzs_actividad_movimientos.movimiento_asociado",
        "fnzs_actividad_movimientos.observaciones_movimiento"
      ]);
  
      $moviProvBuyNotPag = DB::table('fnzs_pagos_orden as order')
      ->join('main_empresas as emp', 'order.empresa', '=', 'emp.id')
      ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
      ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
      ->where([
        "order.status_ordenPago" => TRUE,
        "catprov.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])      
      ->whereBetween("buy.fecha_contabilizacion", [$fechaInicio, $fechaFin])
      ->select([
        "emp.zona_horaria",
        "buy.id AS mov_id",
        "order.token_ordenPago AS token_movimiento",//anticipo
        "order.folio_ordenPago AS folio_movimiento",
        "buy.folio_compra AS concepto_movimiento",
        "buy.fecha_contabilizacion AS fecha_contabilizacion_movimiento",
        "catprov.token_cat_proveedores",
        DB::raw("'1.00' AS monto_aplicado"),
        "buy.tipo_de_cambio AS tipo_cambio_movimiento",
        "buy.folio_compra AS folio_doc_ant_asociado",
        DB::raw("'compra_registrada' AS medio_pago"),
        DB::raw("'---' AS pago_ref_id"),
        DB::raw("'S' AS tipo_movimiento"),
        DB::raw("NULL AS movimiento_asociado"),
        "buy.observaciones_compra AS observaciones_movimiento"
      ]);

      $queryMovimientos = $movimientosProvByCompraOrdenPago->unionAll($movimientosProvByAnticipoOrdenPago)
      ->unionAll($movimientosProvDirecto)->unionAll($moviProvBuyNotPag)
      ->orderBy('fecha_contabilizacion_movimiento', 'ASC')
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
        
        $saldo_inicial_prov = $this->saldoInicialProveedorByToken($token_cat_proveedores,$empresa,$fechaInicio);
        $saldo_acumulado_depositos = 0;
        $saldo_acumulado_retiros = 0;
        $saldo_acumulado_progresivo = $saldo_inicial_prov;
        $contador = 0;
        
        $idCompras = $queryMovimientos->pluck('mov_id')->filter()->unique()->toArray();
        $comprasMap = DB::table('eegr_compras')->whereIn('id', $idCompras)->get()->keyBy('id');
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
        
        $mapCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
        ->whereIn('buy.token_compras',$comprasMap->pluck('token_compras')->unique())
        ->select(
          'buy.token_compras AS id_compras',
          'cfdi.cfdi_comprobante_version',
          'cfdi.cfdi_comprobante_serie',
          'cfdi.cfdi_comprobante_folio',
          'cfdi.cfdi_comprobante_fecha',
          'cfdi.cfdi_comprobante_forma_de_pago',
          'cfdi.cfdi_comprobante_metodo_de_pago',
          'cfdi.cfdi_comprobante_subtotal',
          'cfdi.cfdi_comprobante_moneda',
          'cfdi.cfdi_comprobante_tipo_de_cambio',
          'cfdi.cfdi_comprobante_total',
          'cfdi.cfdi_comprobante_confirmacion',
          'cfdi.cfdi_comprobante_tipo_de_comprobante',
          'cfdi.cfdi_comprobante_lugar_de_expedicion',
          'cfdi.cfdi_comprobante_no_de_certificado',
          'cfdi.cfdi_comprobante_sello',
          'cfdi.cfdi_comprobante_certificado',
          'cfdi.cfdi_complementoFechaTimbrado',
          'cfdi.cfdi_complementoUUID',
        )
        ->get()->keyBy('id_compras');
        
        foreach ($queryMovimientos as $vMov) {
          $oBuy = null;
          $queryCFDIEstructura = null;
          
          if ($vMov->medio_pago == 'compra_registrada') {
            $oBuy = $comprasMap->get($vMov->mov_id);
            if ($oBuy) {
              $queryCFDIEstructura = $mapCFDIEstructura->get($oBuy->token_compras);
            }
          }

          //da_te_default_timezone_set($vMov->zona_horaria);
          $token_movimiento = $vMov->token_movimiento;
          $folio_movimiento = ($vMov->medio_pago == 'compra_registrada' ? 'ORDP-' : 'MOV-').$JwtAuth->generarFolio($vMov->folio_movimiento);
          $concepto_movimiento = !is_null($vMov->concepto_movimiento) && $vMov->concepto_movimiento != '' ? $JwtAuth->desencriptar($vMov->concepto_movimiento) : '';
          $mov_f_cont = $vMov->fecha_contabilizacion_movimiento;
          $fecha_movimiento = !is_null($mov_f_cont) && $mov_f_cont != '' ? $JwtAuth->mostrarUnixAFechaMexico($mov_f_cont) : '';
          $fecha_movimiento_excel = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('d/m/Y',$mov_f_cont) : '';

          $token_proveedor = $vMov->token_cat_proveedores;
          $monto_applc = (float)$vMov->monto_aplicado * ($vMov->tipo_cambio_movimiento ? $vMov->tipo_cambio_movimiento : 1);
          $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio($vMov->folio_doc_ant_asociado);

          switch ($vMov->medio_pago) {
            case 'orden':
              $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio($vMov->folio_doc_ant_asociado);
              break;
            case 'directo':
              $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio($vMov->folio_doc_ant_asociado);
              break;
            case 'compra_registrada':
              $documento_anterior_asociado = "COMP-".$JwtAuth->generarFolio($vMov->folio_doc_ant_asociado);
              break;
            default:
              $documento_anterior_asociado = "---";
              break;
          }

          switch ($vMov->medio_pago) {
            case 'orden':
              $parte_relacionada = $this->parteRelMovPagoByOrden($vMov->pago_ref_id,$JwtAuth);
              break;
            case 'directo':
              $parte_relacionada = $this->parteRelMovPagoDirecto($vMov->pago_ref_id,$JwtAuth);
              break;
            case 'compra_registrada':
              $parte_relacionada = $queryCFDIEstructura ? "Fact: ".$queryCFDIEstructura->cfdi_comprobante_folio." UIDD ".$queryCFDIEstructura->cfdi_complementoUUID : "---"; 
              break;
            default:
              $parte_relacionada = '';
              break;
          }

          //$parte_relacionada = $vMov->medio_pago == 'orden' ? $this->parteRelMovPagoByOrden($vMov->pago_ref_id,$JwtAuth) : $this->parteRelMovPagoDirecto($vMov->pago_ref_id,$JwtAuth);

          $movimiento_debe = 0;
          if ($vMov->tipo_movimiento == 'S') {
            if ( $vMov->medio_pago == 'compra_registrada' && $oBuy) {
              $detalleCompraLista = $detalleCompraMap->get($oBuy->token_compras) ?? collect([]);
              foreach ($detalleCompraLista as $vDetBuy) {
                $subtotal_convert = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
                $importe_concepto_convert = $subtotal_convert - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
                $movimiento_debe += $importe_concepto_convert;
              }
              $movimiento_debe -= $oBuy->anticipo;
            } else {
              $movimiento_debe = $monto_applc;
            }
          }
          
          $movimiento_haber = $vMov->tipo_movimiento == 'R' ? $monto_applc : 0;

          if (!is_null($vMov->movimiento_asociado)) {
            $folio_mov_asociado = 'MOV-'.$JwtAuth->generarFolio(DB::table("fnzs_actividad_movimientos")->where("id",$vMov->movimiento_asociado)->value("folio_movimiento"));
            $concepto_movimiento = 'CANCELACIÓN DE MOVIMIENTO - '.$folio_mov_asociado;
          }

          $saldo_acumulado_depositos += $movimiento_debe;
          $saldo_acumulado_retiros += $movimiento_haber;
          $saldo_acumulado_progresivo += ($movimiento_debe - $movimiento_haber);

          $arrayMovimientos[] = [
            "token_movimiento" => $token_movimiento,
            "folio_movimiento" => $folio_movimiento,
            "concepto_movimiento" => $concepto_movimiento,
            "fecha_movimiento" => $fecha_movimiento,
            "fecha_movimiento_excel" => $fecha_movimiento_excel,
            "tipo_movimiento" => $vMov->tipo_movimiento,
            //movimientos
            "parte_relacionada" => $parte_relacionada,
            //movimientos
            "mov_monto_debe" => $movimiento_debe,
            "mov_monto_debe_format" => $movimiento_debe > 0 ? "$".number_format($movimiento_debe,$decimalesMoneda,'.', ',')." $codeMoneda" : '',
            "mov_monto_haber" => $movimiento_haber,
            "mov_monto_haber_format" => $movimiento_haber > 0 ? "$".number_format($movimiento_haber,$decimalesMoneda,'.', ',')." $codeMoneda" : '',
            "mov_monto_saldo" => $saldo_acumulado_progresivo,
            "mov_monto_saldo_format" => "$".number_format($saldo_acumulado_progresivo,$decimalesMoneda,'.', ',')." $codeMoneda",
            "documento_anterior_asociado" => $documento_anterior_asociado,
            "observaciones_movimiento" => $JwtAuth->desencriptar($vMov->observaciones_movimiento),
          ];
          ++$contador;
        }

        $prov_result_saldo = ($saldo_inicial_prov + $saldo_acumulado_depositos) - $saldo_acumulado_retiros;

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "total_movimientos" => count($arrayMovimientos),
          "mov_moneda" => $codeMoneda,
          "mov_moneda_decimales" => $decimalesMoneda,
          "movimientos_saldo_inicial" => "$".number_format($saldo_inicial_prov,$decimalesMoneda,'.', ','),
          "movimientos_deposito" => "$".number_format($saldo_acumulado_depositos,$decimalesMoneda,'.', ','),
          "movimientos_retiro" => "$".number_format($saldo_acumulado_retiros,$decimalesMoneda,'.', ','),
          "saldo_final" => "$".number_format($prov_result_saldo,$decimalesMoneda,'.', ','),
          "movimientos" => $arrayMovimientos,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}