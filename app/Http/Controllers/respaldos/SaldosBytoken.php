<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MovimientosBancariosModelo;

class FNZS_EstadoMovFinanCuentController extends Controller{
  private function saldoInicialCuenta($token_cuenta,$empresa,$fechaInicio){
    $saldo_inicial_progresivo = 0;
    $querySaldoInicial = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
    ->join("teci_bancos AS bank", "count_cat.banco", "=", "bank.id")
    ->where([
      "count_cat.token_cuenta" => $token_cuenta,
      "emp.empresa_token" => $empresa
    ])
    ->where('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'ASC')
    ->get();

    foreach ($querySaldoInicial as $iMov) {
      $monto_applc = (float)$iMov->monto_aplicado * ($iMov->tipo_cambio_movimiento ? $iMov->tipo_cambio_movimiento : 1);
      $movimiento_i_debe = $iMov->tipo_movimiento == 'S' ? $monto_applc : 0;
      $movimiento_i_haber = $iMov->tipo_movimiento == 'R' ? $monto_applc : 0;
      
      if (!is_null($iMov->pago)) {
        $idPagoByMovAcreedor = DB::table("fnzs_catalogo_acreedores_movimientos AS acrMov")
        ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS ampv", "acrMov.id", "=", "ampv.mov_realizado")
        ->where("ampv.pago_vinculado",$iMov->pago)
        ->exists();

        $idPagoByMovDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS deuMov")
        ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS dmpv", "deuMov.id", "=", "dmpv.mov_realizado")
        ->where("dmpv.pago_vinculado",$iMov->pago)
        ->exists();

        if ($idPagoByMovAcreedor || $idPagoByMovDeudor) {
          continue;
        }
      }
      
      if (!is_null($iMov->acreedor_movimiento)) {
        $movimiento_i_debe = $iMov->tipo_movimiento == 'R' ? $iMov->monto_aplicado : 0;
        $movimiento_i_haber = $iMov->tipo_movimiento == 'S' ? $iMov->monto_aplicado : 0;
      }

      $saldo_inicial_progresivo += ($movimiento_i_debe - $movimiento_i_haber);
    }
    return $saldo_inicial_progresivo;
  }

  private function parteRelMovAjuste($ajuste,$JwtAuth){
    $vAjus = DB::table("fnzs_catalogos_cuentas_ajustes AS ajust")
    ->leftJoin("eegr_catalogo_proveedores AS catprov", "ajust.aj_proveedor", "=", "catprov.id")
    ->leftJoin("sos_personas AS prov_data", "catprov.proveedor", "=", "prov_data.id")
    ->leftJoin("ingr_catalogo_clientes AS catcli", "ajust.aj_cliente", "=", "catcli.id")
    ->leftJoin("sos_personas AS client_data", "catcli.cliente", "=", "client_data.id")
    ->leftJoin("vhum_empleados_catalogo AS trab", "ajust.aj_empleado", "=", "trab.id")
    ->leftJoin("sos_personas AS trab_data", "trab.personal", "=", "trab_data.id")
    ->where('ajust.id',$ajuste)
    ->select([
      'prov_data.nombre_extendido AS prvName',
      'client_data.nombre_extendido AS cliName',
      'trab_data.paterno AS trab_paterno',
      'trab_data.materno AS trab_materno',
      'trab_data.nombre AS trab_nombre',
    ])
    ->first();
    
    if (!$vAjus) return "";

    if ($vAjus->prvName) {
      return $JwtAuth->desencriptar($vAjus->prvName);
    } elseif ($vAjus->cliName) {
      return $JwtAuth->desencriptar($vAjus->cliName);
    } elseif ($vAjus->trab_nombre) {
      $paterno = $JwtAuth->desencriptar($vAjus->trab_paterno);
      $materno = $JwtAuth->desencriptar($vAjus->trab_materno);
      $nombre  = $JwtAuth->desencriptar($vAjus->trab_nombre);
      return trim("$paterno $materno $nombre");
    }
  }

  private function parteRelMovPagoDirecto($pago,$JwtAuth){
    $vPago = DB::table("fnzs_pagos_pago AS pag")
    ->leftJoin("eegr_catalogo_proveedores AS catprov", "pag.vinc_proveedor", "=", "catprov.id")
    ->leftJoin("sos_personas AS prov_data", "catprov.proveedor", "=", "prov_data.id")
    ->leftJoin("ingr_catalogo_clientes AS catcli", "pag.vinc_cliente", "=", "catcli.id")
    ->leftJoin("sos_personas AS client_data", "catcli.cliente", "=", "client_data.id")
    ->leftJoin("vhum_empleados_catalogo AS trab", "pag.vinc_empleado", "=", "trab.id")
    ->leftJoin("sos_personas AS trab_data", "trab.personal", "=", "trab_data.id")
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
    if ($vOrdenPago->fei_folio && $vOrdenPago->fei_entidad) {
      $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vOrdenPago->fei_folio).(!is_null($vOrdenPago->fei_subfolio) ? '-'.$vOrdenPago->fei_subfolio : '');
      $fed_est_mun_entidad = $JwtAuth->desencriptar($vOrdenPago->fei_entidad);
      return "$fed_est_mun_folio $fed_est_mun_entidad";
    } 
    if ($vOrdenPago->fes_folio && $vOrdenPago->fes_entidad) {
      $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vOrdenPago->fes_folio).(!is_null($vOrdenPago->fes_subfolio) ? '-'.$vOrdenPago->fes_subfolio : '');
      $fed_est_mun_entidad = $JwtAuth->desencriptar($vOrdenPago->fes_entidad);
      return "$fed_est_mun_folio $fed_est_mun_entidad";
    } 
    if ($vOrdenPago->fdf_folio && $vOrdenPago->fdf_entidad) {
      $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vOrdenPago->fdf_folio).(!is_null($vOrdenPago->fdf_subfolio) ? '-'.$vOrdenPago->fdf_subfolio : '');
      $fed_est_mun_entidad = $JwtAuth->desencriptar($vOrdenPago->fdf_entidad);
      return "$fed_est_mun_folio $fed_est_mun_entidad";
    }
  }

  private function parteRelMovAcreedor($acreedor,$JwtAuth){
    $voAcreedor = DB::table("fnzs_catalogo_acreedores AS catAcree")
    ->join("fnzs_catalogo_acreedores_movimientos AS acreeMov", "catAcree.id", "=", "acreeMov.vinc_acreedor")
    ->where("acreeMov.id",$acreedor)
    ->select('catAcree.acr_titular')
    ->first();

    if (!$voAcreedor || is_null($voAcreedor->acr_titular)) {
      return 'N/A';
    }
    return $JwtAuth->desencriptar($voAcreedor->acr_titular);
  }

  private function parteRelMovDeudor($deudor,$JwtAuth){
    $voDeudor = DB::table("fnzs_catalogo_deudores AS catDeu")
    ->join("fnzs_catalogo_deudores_movimientos AS deuMov", "catDeu.id", "=", "deuMov.vinc_deudor")
    ->where("deuMov.id",$deudor)
    ->select('catDeu.deu_titular')
    ->first();

    if (!$voDeudor || is_null($voDeudor->deu_titular)) {
      return 'N/A';
    }
    return $JwtAuth->desencriptar($voDeudor->deu_titular);
  }

  public function movimientosFinancierosCuentaBancaria(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cuenta' => 'required|string',
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
      date_default_timezone_set('America/Mexico_City');
      $token_cuenta = $request->input('token_cuenta');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
      $fechaFin = strtotime($periodo_fin . " 23:59:59");

      $queryMovimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
      ->join("teci_bancos AS bank", "count_cat.banco", "=", "bank.id")
      ->where([
        "count_cat.token_cuenta" => $token_cuenta,
        "emp.empresa_token" => $empresa
      ])
      ->whereBetween("fnzs_actividad_movimientos.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
      ->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'ASC')
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
        
        $saldo_inicial_cuenta = $this->saldoInicialCuenta($token_cuenta,$empresa,$fechaInicio);
        $saldo_acumulado_depositos = 0;
        $saldo_acumulado_retiros = 0;
        $saldo_acumulado_progresivo = $saldo_inicial_cuenta;
        $contador = 0;
        
        foreach ($queryMovimientos as $vMov) {
          date_default_timezone_set($vMov->zona_horaria);
          $token_movimiento = $vMov->token_movimiento;
          $folio_movimiento = 'MOV-'.$JwtAuth->generarFolio($vMov->folio_movimiento);
          $concepto_movimiento = !is_null($vMov->concepto_movimiento) && $vMov->concepto_movimiento != '' ? $JwtAuth->desencriptar($vMov->concepto_movimiento) : '';
          $mov_f_cont = $vMov->fecha_contabilizacion_movimiento;
          $fecha_movimiento = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('Y-m-d',$mov_f_cont) : '';
          $fecha_movimiento_excel = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('d/m/Y',$mov_f_cont) : '';

          $token_cuenta = $vMov->token_cuenta;
          $monto_applc = (float)$vMov->monto_aplicado * ($vMov->tipo_cambio_movimiento ? $vMov->tipo_cambio_movimiento : 1);
          $documento_anterior_asociado = "";
          $parte_relacionada = "";

          $movimiento_debe = $vMov->tipo_movimiento == 'S' ? $monto_applc : 0;
          $movimiento_haber = $vMov->tipo_movimiento == 'R' ? $monto_applc : 0;

          if ($vMov->subtipo_movimiento == "P") {
            if ($vMov->tipo_movimiento == 'R') {
              $documento_anterior_asociado = "MCP-".$JwtAuth->generarFolio(
                DB::table("fnzs_movimientos_cuentas_propias AS mcp")
                ->join("fnzs_actividad_movimientos AS actMov", "mcp.movimiento_cp_origen", "=", "actMov.id")
                ->where("actMov.token_movimiento",$vMov->token_movimiento)
                ->value('mcp.movimiento_cp_folio')
              );
            } else {
              $documento_anterior_asociado = "MCP-".$JwtAuth->generarFolio(
                DB::table("fnzs_movimientos_cuentas_propias AS mcp")
                ->join("fnzs_actividad_movimientos AS actMov", "mcp.movimiento_cp_detino", "=", "actMov.id")
                ->where("actMov.token_movimiento",$vMov->token_movimiento)
                ->value('mcp.movimiento_cp_folio')
              );
            }
            
            $empData = DB::table("main_empresas AS emp")
            ->join("sos_personas AS people", "emp.persona", "=", "people.id")
            ->where("emp.empresa_token",$empresa)
            ->first();
        
            $nombreEmpresa = $empData->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($empData->paterno, $empData->materno, $empData->nombre) : $JwtAuth->desencriptar($empData->denominacion_rs);
            $name_abrev = $empData->abrev_nombre;

            $parte_relacionada = "$name_abrev $nombreEmpresa";
          }
          if (!is_null($vMov->ajuste)) {
            $documento_anterior_asociado = "AJUST-".$JwtAuth->generarFolio(DB::table("fnzs_catalogos_cuentas_ajustes")->where("id",$vMov->ajuste)->value('folio_ajuste'));
            $parte_relacionada = $this->parteRelMovAjuste($vMov->ajuste,$JwtAuth);
          }
          if (!is_null($vMov->pago)) {
            $idPagoByMovAcreedor = DB::table("fnzs_catalogo_acreedores_movimientos AS acrMov")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS ampv", "acrMov.id", "=", "ampv.mov_realizado")
            ->where("ampv.pago_vinculado",$vMov->pago)
            ->exists();

            $idPagoByMovDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS deuMov")
            ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS dmpv", "deuMov.id", "=", "dmpv.mov_realizado")
            ->where("dmpv.pago_vinculado",$vMov->pago)
            ->exists();

            if (!$idPagoByMovAcreedor && !$idPagoByMovDeudor) {
              $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio(DB::table("fnzs_pagos_pago")->where("id",$vMov->pago)->value('folio_pagos'));
              
              $byOrdenPago = DB::table("fnzs_pagos_orden AS order")
              ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "order.id", "=", "ordpv.orden_pago_vinculada")
              ->join("fnzs_pagos_pago AS pay", "ordpv.pago_realizado", "=", "pay.id")
              ->where("pay.id",$vMov->pago)
              ->exists();
  
              $parte_relacionada = $byOrdenPago ? $this->parteRelMovPagoByOrden($vMov->pago,$JwtAuth) : $this->parteRelMovPagoDirecto($vMov->pago,$JwtAuth);
            } else {
              continue;
            }
          }
          if (!is_null($vMov->cobro)) {
            $documento_anterior_asociado = "COBRO-".$JwtAuth->generarFolio(DB::table("fnzs_cobros_cobro")->where("id",$vMov->cobro)->value('folio_cobros'));
          }
          if (!is_null($vMov->acreedor_movimiento)) {
            $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio(
              DB::table("fnzs_catalogo_acreedores_movimientos AS acrMov")
              ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS ampv", "acrMov.id", "=", "ampv.mov_realizado")
              ->join("fnzs_pagos_pago AS pag", "ampv.pago_vinculado", "=", "pag.id")
              ->where("acrMov.id",$vMov->acreedor_movimiento)
              ->value('pag.folio_pagos')
            );
            $parte_relacionada = $this->parteRelMovAcreedor($vMov->acreedor_movimiento,$JwtAuth);
            $movimiento_debe = $vMov->tipo_movimiento == 'R' ? $vMov->monto_aplicado : 0;

            $movimiento_haber = $vMov->tipo_movimiento == 'S' ? $vMov->monto_aplicado : 0;
          }
          if (!is_null($vMov->deudor_movimiento)) {
            $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio(
              DB::table("fnzs_catalogo_deudores_movimientos AS deuMov")
              ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS dmpv", "deuMov.id", "=", "dmpv.mov_realizado")
              ->join("fnzs_pagos_pago AS pag", "dmpv.pago_vinculado", "=", "pag.id")
              ->where("deuMov.id",$vMov->deudor_movimiento)
              ->value('pag.folio_pagos')
            );

            $parte_relacionada = $this->parteRelMovDeudor($vMov->deudor_movimiento,$JwtAuth);
          }

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
            "mov_monto_debe_format" => $movimiento_debe > 0 ? "$".number_format($movimiento_debe,$decimalesMoneda,'.', ',') : '',
            "mov_monto_haber" => $movimiento_haber,
            "mov_monto_haber_format" => $movimiento_haber > 0 ? "$".number_format($movimiento_haber,$decimalesMoneda,'.', ',') : '',
            "mov_monto_saldo" => $saldo_acumulado_progresivo,
            "mov_monto_saldo_format" => "$".number_format($saldo_acumulado_progresivo,$decimalesMoneda,'.', ','),
            "documento_anterior_asociado" => $documento_anterior_asociado,
            "observaciones_movimiento" => $JwtAuth->desencriptar($vMov->observaciones_movimiento),
          ];
          ++$contador;
        }

        $cuenta_result_saldo = ($saldo_inicial_cuenta + $saldo_acumulado_depositos) - $saldo_acumulado_retiros;

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "total_movimientos" => count($arrayMovimientos),
          "mov_moneda" => $codeMoneda,
          "mov_moneda_decimales" => $decimalesMoneda,
          "movimientos_saldo_inicial" => "$".number_format($saldo_inicial_cuenta,$decimalesMoneda,'.', ','),
          "movimientos_deposito" => "$".number_format($saldo_acumulado_depositos,$decimalesMoneda,'.', ','),
          "movimientos_retiro" => "$".number_format($saldo_acumulado_retiros,$decimalesMoneda,'.', ','),
          "saldo_final" => "$".number_format($cuenta_result_saldo,$decimalesMoneda,'.', ','),
          "movimientos" => $arrayMovimientos,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}


<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\CajaModelo;
use App\Models\AlmacenModelo;
use App\Models\PersonalModelo;

class FNZS_CajaController extends Controller{
  public function folioCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $folioCaja = DB::select("SELECT 
      IF (max(no_caja) IS NOT NULL,(max(no_caja)+1),1) AS folio
      FROM fnzs_catalogos_caja AS caj JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
      JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
      WHERE caj.empresa = emp.id AND emp.empresa_token = ?
      AND emp.id = empuser.empresa AND empuser.personal = pers.id
      AND pers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

    return response()->json(['caja' => $JwtAuth->generar($folioCaja[0]->folio), 'codigo' => 200, 'status' => 'success']);
  }

  public function catalogoCajasActual(Request $request){
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
      
      $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
      ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fnzs_catalogos_caja.fecha_alta_caja", [$fechaInicio, $fechaFin]);
      })
      ->orderby('fnzs_catalogos_caja.id', 'desc')
      ->get();

      if ($queryCaja->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cajas registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaCajas = array();

        foreach ($queryCaja as $resCaja) {
          $moneda_decimales = $JwtAuth->getMonedaAPI($resCaja->e_moneda_code);

          $saldoCaja = DB::table('fnzs_actividad_movimientos AS movim')
          ->leftJoin('fnzs_cobros_cobro AS cobrar', 'movim.cobro', '=', 'cobrar.id')
          ->leftJoin('fnzs_pagos_pago AS payment', 'movim.pago', '=', 'payment.id')
          ->join('fnzs_catalogos_caja AS caj', 'movim.caja', '=', 'caj.id')
          ->where('caj.token_caja', $resCaja->token_caja)
          //->selectRaw(
          //  "ROUND((
          //  SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'V' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN cobrar.monto_cobro / cobrar.tipo_cambio ELSE cobrar.monto_cobro * cobrar.tipo_cambio END ELSE 0 END) +
          //  SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN cobrar.monto_cobro / cobrar.tipo_cambio ELSE cobrar.monto_cobro * cobrar.tipo_cambio END ELSE 0 END) -
          //  SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'C' THEN CASE WHEN payment.p_moneda = 'MXN' THEN payment.monto_pago / payment.tipo_cambio ELSE payment.monto_pago * payment.tipo_cambio END ELSE 0 END) -
          //  SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN payment.p_moneda = 'MXN' THEN payment.monto_pago / payment.tipo_cambio ELSE payment.monto_pago * payment.tipo_cambio END ELSE 0 END) -
          //  SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'R' THEN CASE WHEN payment.p_moneda = 'MXN' THEN payment.monto_pago / payment.tipo_cambio ELSE payment.monto_pago * payment.tipo_cambio END ELSE 0 END)
          //), ?) AS saldoRound, FORMAT((
          //  SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'V' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN cobrar.monto_cobro / cobrar.tipo_cambio ELSE cobrar.monto_cobro * cobrar.tipo_cambio END ELSE 0 END) +
          //  SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN cobrar.monto_cobro / cobrar.tipo_cambio ELSE cobrar.monto_cobro * cobrar.tipo_cambio END ELSE 0 END) -
          //  SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'C' THEN CASE WHEN payment.p_moneda = 'MXN' THEN payment.monto_pago / payment.tipo_cambio ELSE payment.monto_pago * payment.tipo_cambio END ELSE 0 END) -
          //  SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN payment.p_moneda = 'MXN' THEN payment.monto_pago / payment.tipo_cambio ELSE payment.monto_pago * payment.tipo_cambio END ELSE 0 END) -
          //  SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'R' THEN CASE WHEN payment.p_moneda = 'MXN' THEN payment.monto_pago / payment.tipo_cambio ELSE payment.monto_pago * payment.tipo_cambio END ELSE 0 END)
          //), ?) AS saldoFormat",[$moneda_decimales, $moneda_decimales])->first();
          ->selectRaw(
            "ROUND((
            SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'V' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN movim.monto_aplicado / cobrar.tipo_cambio ELSE movim.monto_aplicado * cobrar.tipo_cambio END ELSE 0 END) +
            SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN movim.monto_aplicado / cobrar.tipo_cambio ELSE movim.monto_aplicado * cobrar.tipo_cambio END ELSE 0 END) -
            SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'C' THEN CASE WHEN payment.p_moneda = 'MXN' THEN movim.monto_aplicado / payment.tipo_cambio ELSE movim.monto_aplicado * payment.tipo_cambio END ELSE 0 END) -
            SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN payment.p_moneda = 'MXN' THEN movim.monto_aplicado / payment.tipo_cambio ELSE movim.monto_aplicado * payment.tipo_cambio END ELSE 0 END) -
            SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'R' THEN CASE WHEN payment.p_moneda = 'MXN' THEN movim.monto_aplicado / payment.tipo_cambio ELSE movim.monto_aplicado * payment.tipo_cambio END ELSE 0 END)
          ), ?) AS saldoRound, FORMAT((
            SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'V' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN movim.monto_aplicado / cobrar.tipo_cambio ELSE movim.monto_aplicado * cobrar.tipo_cambio END ELSE 0 END) +
            SUM(CASE WHEN movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN cobrar.moneda_clave = 'MXN' THEN movim.monto_aplicado / cobrar.tipo_cambio ELSE movim.monto_aplicado * cobrar.tipo_cambio END ELSE 0 END) -
            SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'C' THEN CASE WHEN payment.p_moneda = 'MXN' THEN movim.monto_aplicado / payment.tipo_cambio ELSE movim.monto_aplicado * payment.tipo_cambio END ELSE 0 END) -
            SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'D' THEN CASE WHEN payment.p_moneda = 'MXN' THEN movim.monto_aplicado / payment.tipo_cambio ELSE movim.monto_aplicado * payment.tipo_cambio END ELSE 0 END) -
            SUM(CASE WHEN movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'R' THEN CASE WHEN payment.p_moneda = 'MXN' THEN movim.monto_aplicado / payment.tipo_cambio ELSE movim.monto_aplicado * payment.tipo_cambio END ELSE 0 END)
          ), ?) AS saldoFormat",[$moneda_decimales, $moneda_decimales])->first();
          $caja_folio = "CAJ-".$JwtAuth->generarFolio($resCaja->no_caja);
          $caja_alias = $JwtAuth->desencriptar($resCaja->alias_caja);

          $row = array(
            "token_caja" => $resCaja->token_caja,
            "caja_folio" => $caja_folio,
            "caja_alias" => $caja_alias,
            "establecimiento" => $JwtAuth->desencriptar($resCaja->alias_establecimiento),
            //"usuario" => $JwtAuth->desencriptar('N2FXYXMwR0syOEVZNTZSV2svMHhvZz09OjoxMjM0NTY3ODEyMzQ1Njc4')
            "saldofloat" => $saldoCaja->saldoRound ? $saldoCaja->saldoRound : 0,
            "salDoCaja" => "$".($saldoCaja->saldoFormat ? $saldoCaja->saldoFormat : '0.00')." $resCaja->moneda_caja",
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
            //"disponible" => $vSal->disponible ? true : false,
            "monto_aplicar" => 0,
            "_filtro_busqueda" => "$caja_folio $caja_alias",
          );
          $listaCajas[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'caja' => $listaCajas,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function saldoCajaByToken($token_moneda, $token_caja, $empresa_token, $user_token){
    $moneda_datos = DB::select("SELECT id,decimales FROM teci_catalogo_monedas WHERE token_monedas = ?", [$token_moneda]);
    //SUMAN    
    $cobroVentaCaja = DB::select(
      "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_cobros_cobro AS cobrar JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE
    AND movim.subtipo_movimiento = 'V' AND movim.caja = caj.id AND caj.token_caja = ? AND movim.cobro = cobrar.id
    AND cobrar.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $token_caja, $empresa_token, $user_token]
    );

    $devolucionCompraCaja = DB::select(
      "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_cobros_cobro AS cobrar JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE
    AND movim.subtipo_movimiento = 'D' AND movim.caja = caj.id AND caj.token_caja = ? AND movim.cobro = cobrar.id
    AND cobrar.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $token_caja, $empresa_token, $user_token]
    );

    $suman_caja = $cobroVentaCaja[0]->total + $devolucionCompraCaja[0]->total;

    //RESTAN
    $pagoCompraCaja = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_pagos_pago AS payment JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE
    AND movim.subtipo_movimiento = 'C' AND movim.caja = caj.id AND caj.token_caja = ? AND movim.pago = payment.id
    AND payment.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $token_caja, $empresa_token, $user_token]
    );

    $devolucionVentaCaja = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_pagos_pago AS payment JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE
    AND movim.subtipo_movimiento = 'D' AND movim.caja = caj.id AND caj.token_caja = ? AND movim.pago = payment.id
    AND payment.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $token_caja, $empresa_token, $user_token]
    );

    $reembolsoEmpleado = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
    JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
    WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'R' AND movim.pago = payment.id 
    AND movim.caja = caj.id AND payment.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
    AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $token_caja, $empresa_token, $user_token]
    );

    $justificacionEmpleado = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
    JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
    WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'J' AND movim.pago = payment.id 
    AND movim.caja = caj.id AND payment.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
    AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $token_caja, $empresa_token, $user_token]
    );

    $restan_caja = $pagoCompraCaja[0]->total + $devolucionVentaCaja[0]->total + $reembolsoEmpleado[0]->total + $justificacionEmpleado[0]->total;
    $resultsalDoCaja = $suman_caja - $restan_caja;
    return $resultsalDoCaja;
  }

  public function saldoCajaById($token_moneda, $id_caja, $empresa_token, $user_token){
    $moneda_datos = DB::select("SELECT id,decimales FROM teci_catalogo_monedas WHERE token_monedas = ?", [$token_moneda]);
    //SUMAN    
    $cobroVentaCaja = DB::select(
      "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_cobros_cobro AS cobrar JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE
    AND movim.subtipo_movimiento = 'V' AND movim.caja = caj.id AND caj.id = ? AND movim.cobro = cobrar.id
    AND cobrar.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $id_caja, $empresa_token, $user_token]
    );

    $devolucionCompraCaja = DB::select(
      "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_cobros_cobro AS cobrar JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE
    AND movim.subtipo_movimiento = 'D' AND movim.caja = caj.id AND caj.id = ? AND movim.cobro = cobrar.id
    AND cobrar.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $id_caja, $empresa_token, $user_token]
    );

    $suman_caja = $cobroVentaCaja[0]->total + $devolucionCompraCaja[0]->total;

    //RESTAN
    $pagoCompraCaja = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_pagos_pago AS payment JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE
    AND movim.subtipo_movimiento = 'C' AND movim.caja = caj.id AND caj.id = ? AND movim.pago = payment.id
    AND payment.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $id_caja, $empresa_token, $user_token]
    );

    $devolucionVentaCaja = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim
    JOIN fnzs_catalogos_caja AS caj JOIN fnzs_pagos_pago AS payment JOIN in_egr_establecimientos_responsables AS alm_resp
    JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE
    AND movim.subtipo_movimiento = 'D' AND movim.caja = caj.id AND caj.id = ? AND movim.pago = payment.id
    AND payment.caja = caj.id AND caj.id = alm_resp.caja AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $id_caja, $empresa_token, $user_token]
    );

    $reembolsoEmpleado = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
    JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
    WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'R' AND movim.pago = payment.id 
    AND movim.caja = caj.id AND payment.caja = caj.id AND caj.id = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
    AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $id_caja, $empresa_token, $user_token]
    );

    $justificacionEmpleado = DB::select(
      "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
    JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
    JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
    WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'J' AND movim.pago = payment.id 
    AND movim.caja = caj.id AND payment.caja = caj.id AND caj.id = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
    AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
    AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
    AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
      [$moneda_datos[0]->decimales, $id_caja, $empresa_token, $user_token]
    );

    $restan_caja = $pagoCompraCaja[0]->total + $devolucionVentaCaja[0]->total + $reembolsoEmpleado[0]->total + $justificacionEmpleado[0]->total;
    $resultsalDoCaja = $suman_caja - $restan_caja;

    return $resultsalDoCaja;
  }

  public function catalogoCajasDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
    ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
    ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
    ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
    ->where([
      'fnzs_catalogos_caja.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])->orderby('fnzs_catalogos_caja.fecha_delete_caja', 'DESC')
    ->get();

    if ($queryCaja->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cajas registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaCajas = array();

      foreach ($queryCaja as $resCaja) {
        date_default_timezone_set('America/Mexico_City');
        $row = array(
          "token_caja" => $resCaja->token_caja,
          "caja_folio" => "CAJ-" . $JwtAuth->generarFolio($resCaja->no_caja),
          "caja_alias" => $JwtAuth->desencriptar($resCaja->alias_caja),
          "establecimiento" => $JwtAuth->desencriptar($resCaja->alias_establecimiento),
          "fecha_delete" => date('d-m-Y H:i:s', $resCaja->fecha_delete_caja)
        );
        $listaCajas[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'caja' => $listaCajas,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleCajaVig(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      date_default_timezone_set('America/Mexico_City');
      $token_caja = $request->input('token_caja');

      $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
      ->join("teci_direcciones AS dir", "alm.id", "dir.establecimiento")
      ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => TRUE,
        'dir.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();

      if ($queryCaja->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La caja no existe'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $detalleCaja = array();
        $arrayResponsable = array();
        $arrayCorteCaja = array();
        
        foreach ($queryCaja as $resCaja) {
          $tknEstablecimiento = $resCaja->token_establecimiento;
          $cajEstab = DB::table('in_egr_establecimientos_catalogo')->where('token_establecimiento',$tknEstablecimiento)->get();
          $establecimiento_folio = 'ESTAB-'.$JwtAuth->generarFolio($cajEstab[0]->folio_establecimiento).($cajEstab[0]->post_folio != NULL ? '-'.$cajEstab[0]->post_folio : '');
          $establecimiento_alias = $JwtAuth->desencriptar($cajEstab[0]->alias_establecimiento);
          //Direccion de almacen seleccionada en el alta
          if ($resCaja->pais_code == 'MEX') {
            $dir_completa = "Colonia " . $JwtAuth->desencriptar($resCaja->colonia_edit).", C.P. ".$resCaja->c_postal_edit.", ".$JwtAuth->desencriptar($resCaja->municipio_edit).", ".$JwtAuth->desencriptar($resCaja->estado_edit).", Mexico/México";
          } else {
            $pais_en = "";
            $pais_es = "";
            $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaPaises');
            if ($response->successful()) {
              $datos = $response->json();
              $cantidadRegistros = is_array($datos) ? count($datos) : 0;
              $indice = array_search($resCaja->pais_code, array_column($datos["paises"], "code"));
              $pais_en = $datos["paises"][$indice]["langEN"];
              $pais_es = $datos["paises"][$indice]["langES"];
              //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
            }
            $dir_completa = "Address " . $JwtAuth->desencriptar($resCaja->calle)
              . ", C.P. " . $JwtAuth->desencriptar($resCaja->cod_postalext) . ", $pais_en/$pais_es";
          }

          //Personal seleccionado en el alta
          $responsable = DB::select(
            "SELECT caj.token_caja,respAlm.token_responsables,respAlm.ocupacion,respAlm.turno_inicio,respAlm.turno_fin,people.paterno,people.materno,people.nombre,
            people.img_perfil,pers.folio_pers,pers.fecha_alta_pers,pers.empleado_token FROM in_egr_establecimientos_responsables AS respAlm JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_catalogo AS alm 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers
            JOIN sos_personas AS people JOIN teci_usuarios_catalogo AS users
            WHERE respAlm.almacen = alm.id AND alm.token_establecimiento = ? AND respAlm.caja = caj.id AND caj.token_caja = ? 
            AND respAlm.responsable = pers.id AND pers.empleado_name = people.id
            AND respAlm.administrador = emp.id AND emp.empresa_token = ?
            AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token = ?",
            [$tknEstablecimiento, $token_caja, $empresa, $usuario]
          );

          foreach ($responsable as $vResp) {
            $statusAsigned = $token_caja == $vResp->token_caja ? true : false;
            $user_logo_text = $JwtAuth->desencriptar($vResp->img_perfil);
            $user_logo_path = 'public/root/main_users/' . $JwtAuth->generar($vResp->folio_pers) . '-' . $vResp->fecha_alta_pers . '/';
            $avatar = $JwtAuth->encriptaBase64(Storage::path($user_logo_text != 'default-profile.png' ? $user_logo_path . $user_logo_text . '-profile.png' : 'public/settings/default-profile.png'));

            $arrayRes = array(
              "token_responsables" => $vResp->token_responsables,
              "empleado_token" => $vResp->empleado_token,
              "ocupacion" => $vResp->ocupacion,
              "turno_inicio" => $vResp->turno_inicio,
              "turno_fin" => $vResp->turno_fin,
              "nombre_completo" => $JwtAuth->desencriptar($vResp->paterno) . " " . $JwtAuth->desencriptar($vResp->materno) . " " . $JwtAuth->desencriptar($vResp->nombre),
              "img_perfil" => $avatar,
              "statusAsigned" => $statusAsigned
            );
            $arrayResponsable[] = $arrayRes;
          }

          //Corte de caja seleccionada en el alta
          $corteCaja = DB::table('fnzs_catalogos_caja_corte_catalogo AS cort')
            ->join("fnzs_catalogos_caja AS caj", "cort.caja", "caj.id")
            ->join("main_empresas AS emp", "caj.empresa", "emp.id")
            ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
            ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
            ->where([
              'caj.token_caja' => $resCaja->token_caja,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])->get();

          foreach ($corteCaja as $resCorteCaja) {
            $arrayCorte = array(
              "token_cortecaja" => $resCorteCaja->token_cortecaja,
              "horario_corte" => $JwtAuth->desencriptar($resCorteCaja->horario_corte)
            );
            $arrayCorteCaja[] = $arrayCorte;
          }

          //Detalle de caja
          $arrayCaja = array(
            "token_caja" => $resCaja->token_caja,
            "caja_folio" => "CAJ-" . $JwtAuth->generarFolio($resCaja->no_caja),
            "alias" => $JwtAuth->desencriptar($resCaja->alias_caja),
            "moneda" => $resCaja->moneda_caja,
            "cuenta_contable" => !is_null($resCaja->cuenta_contable_caja) && $resCaja->cuenta_contable_caja != '' ? $resCaja->cuenta_contable_caja : '',
            "serv_egresos" => $resCaja->serv_egresos ? true : false,
            "serv_ingresos" => $resCaja->serv_ingresos ? true : false,
            "serv_interno" => $resCaja->serv_interno ? true : false,
            "capt_cliente" => $resCaja->capt_cliente ? true : false,
            "capt_precio_x_articulo" => $resCaja->capt_precio_x_articulo ? true : false,
            "capt_primero_cantidad" => $resCaja->capt_primero_cantidad ? true : false,
            "responsable" => !is_null($resCaja->encargado_principal) && $resCaja->encargado_principal != '' ? $JwtAuth->desencriptar($resCaja->encargado_principal) : '',
            "establecimiento_token" => $tknEstablecimiento,
            "establecimiento_folio" => $establecimiento_folio,
            "establecimiento_alias" => $establecimiento_alias,
            "establecimiento_direccion" => $dir_completa,
            "corte_caja" => $arrayCorteCaja
          );
          $detalleCaja[] = $arrayCaja;
        }

        $dataMensaje = array(
          'caja' => $detalleCaja,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function respCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,people.nacionalidad FROM main_empresas AS emp JOIN sos_personas AS people 
      JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
      WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empusers.empresa 
      AND empusers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

    $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
    ->join("teci_direcciones AS dirubica","alm.id","dirubica.establecimiento")
    ->join("in_egr_establecimientos_responsables AS respons", "fnzs_catalogos_caja.id", "respons.caja")
    ->join("vhum_empleados_catalogo AS persnl", "respons.responsable", "persnl.id")
    ->join("sos_personas AS people", "persnl.empleado_name", "people.id")
    ->join("teci_usuarios_catalogo AS users", "persnl.id", "users.empleado")
    ->where([
      "fnzs_catalogos_caja.serv_ingresos" => TRUE,
      "fnzs_catalogos_caja.empresa" => $selectEmp[0]->id,
      'users.usuario_token' => $usuario
    ])->get();

    if ($queryCaja->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cajas registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $direccion = array();
      $caja = array();
      
      foreach ($queryCaja as $resCaja) {
        //echo $selectEmp[0]->nacionalidad." ".$resCaja->token_direccion;
        if ($selectEmp[0]->nacionalidad = 118) {
          $direccionAlmacen = DB::table('teci_direcciones AS diralm')
            ->join('teci_direcciones_codigos_postales AS cpostal', 'diralm.codigo_postal', 'cpostal.id')
            //->join('colonias AS col','diralm.cod_postal','col.id')
            //->join('deleg_mun AS delmun','diralm.delegacion_municipio','delmun.id')
            //->join('entidad_federativa AS entfed','diralm.ent_federativa','entfed.id')
            ->join('teci_pais AS detpais', 'diralm.pais', 'detpais.id')
            ->join("main_empresas AS emp", "diralm.administrador", "emp.id")
            ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
            ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
            ->where([
              'diralm.status' => TRUE,
              'diralm.tipo_direccion' => 'almacen',
              'diralm.token_direccion' => $resCaja->token_direccion,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])->get();

          $tknDireccion = $direccionAlmacen[0]->token_direccion;
          $tipoDireccion = $direccionAlmacen[0]->tipo_direccion;
          $clasifDireccion = $JwtAuth->desencriptar($direccionAlmacen[0]->clase);
          $aliasDireccion =  $JwtAuth->desencriptar($direccionAlmacen[0]->alias);

          if ($direccionAlmacen[0]->calle != '' && $direccionAlmacen[0]->calle != NULL) {
            $calle = $JwtAuth->desencriptar($direccionAlmacen[0]->calle);
          } else {
            $calle = 's/c';
          }

          if ($direccionAlmacen[0]->num_ext != '' && $direccionAlmacen[0]->num_ext != NULL) {
            $num_ext = $JwtAuth->desencriptar($direccionAlmacen[0]->num_ext);
          } else {
            $num_ext = 's/n';
          }

          if ($direccionAlmacen[0]->num_int != '' && $direccionAlmacen[0]->num_int != NULL) {
            $num_int = $JwtAuth->desencriptar($direccionAlmacen[0]->num_int);
          } else {
            $num_int = 's/n';
          }

          if ($direccionAlmacen[0]->calle1 != '' && $direccionAlmacen[0]->calle1 != NULL) {
            $calle1 = $JwtAuth->desencriptar($direccionAlmacen[0]->calle1);
          } else {
            $calle1 = 's/c';
          }

          if ($direccionAlmacen[0]->calle2 != '' && $direccionAlmacen[0]->calle2 != NULL) {
            $calle2 = $JwtAuth->desencriptar($direccionAlmacen[0]->calle2);
          } else {
            $calle2 = 's/c';
          }

          if ($direccionAlmacen[0]->referencia != '' && $direccionAlmacen[0]->referencia != NULL) {
            $referencia = $JwtAuth->desencriptar($direccionAlmacen[0]->referencia);
          } else {
            $referencia = 's/reg';
          }

          $dir_completa = "Calle " . $calle . " No. " . $num_ext . " Int." . $num_int .
            ", C.P. " . $direccionAlmacen[0]->codigo_postal .
            $direccionAlmacen[0]->tipo_asentamiento . " " .
            $direccionAlmacen[0]->asentamiento . ", " .
            $direccionAlmacen[0]->deleg_mun . ", " . $direccionAlmacen[0]->estado .
            ", ciudad " . $direccionAlmacen[0]->ciudad .
            ", " . $direccionAlmacen[0]->pais .
            ", entre " . $JwtAuth->desencriptar($direccionAlmacen[0]->calle1) .
            " y " . $JwtAuth->desencriptar($direccionAlmacen[0]->calle2) .
            " referencia " . $JwtAuth->desencriptar($direccionAlmacen[0]->referencia);
        } else {
          $queryDirAlmacenExt = DB::table('teci_direcciones AS diralm')
            ->join('teci_pais AS detpais', 'diralm.pais', 'detpais.id')
            ->join('main_empresas AS emp', 'diralm.administrador', 'emp.id')
            ->join('main_empresa_usuario AS empusers', 'emp.id', 'empusers.empresa')
            ->join('vhum_empleados_catalogo AS pers', 'empusers.personal', 'pers.id')
            ->join('teci_usuarios_catalogo AS users', 'pers.usuario', 'users.id')
            ->where([
              'diralm.status' => TRUE,
              'diralm.tipo_direccion' => 'almacen',
              'diralm.token_direccion' => $resCaja->token_direccion,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])
            ->get();

          $tknDireccion = $queryDirAlmacenExt[0]->token_direccion;
          $tipoDireccion = $direccionAlmacen[0]->tipo_direccion;
          $clasifDireccion = $JwtAuth->desencriptar($direccionAlmacen[0]->clase);
          $aliasDireccion =  $JwtAuth->desencriptar($direccionAlmacen[0]->alias);

          $dir_completa = "Alias: " . $JwtAuth->desencriptar($queryDirAlmacenExt[0]->alias)
            . ", Calle " . $JwtAuth->desencriptar($queryDirAlmacenExt[0]->calle)
            . ", C.P. " . $JwtAuth->desencriptar($queryDirAlmacenExt[0]->cod_postalext) . ", " . $queryDirAlmacenExt[0]->pais;
        }

        if ($JwtAuth->desencriptar($resCaja->img_perfil) == 'default-profile.png') {
          $avatar = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resCaja->img_perfil)));
        } else {
          $avatar = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $selectEmp[0]->root_tkn .
            '/0004-vhm/catalogos/employees/' . $JwtAuth->generar($resCaja->folio_pers) . '-' .
            $resCaja->fecha_alta_pers . '/' . $JwtAuth->desencriptar($resCaja->img_perfil) . '-profile.png'));
        }

        $decimalesMoneda = DB::select(
          "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
                        AND emp.id = empusers.empresa AND empusers.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
          [$empresa, $usuario]
        );

        //suman
        $cobroVenta = DB::select("SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = TRUE 
                            AND movim.subtipo_movimiento = 'V' 
                            AND movim.cobro = cobrar.id 
                            AND movim.caja = caj.id 
                            AND cobrar.caja = caj.id 
                            AND caj.token_caja = ?
                            AND movim.empresa = emp.id 
                            AND cobrar.empresa = emp.id
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        $devolucionCompra = DB::select("SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp  
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'D' AND movim.cobro = cobrar.id 
                            AND movim.caja = caj.id AND cobrar.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND cobrar.empresa = emp.id 
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        //restan
        $pagoCompra = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'C' AND movim.pago = payment.id 
                            AND movim.caja = caj.id AND payment.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        $devolucionVenta = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = TRUE AND movim.subtipo_movimiento = 'D' AND movim.pago = payment.id 
                            AND movim.caja = caj.id AND payment.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        $resultsalDoCaja = $cobroVenta[0]->total + $devolucionCompra[0]->total - $pagoCompra[0]->total - $devolucionVenta[0]->total;
        $salDoCaja = DB::select("SELECT FORMAT(?,?) AS saldo", [$resultsalDoCaja, $decimalesMoneda[0]->decimales]);

        $arrayCaja = array(
          "token_establecimiento" => $resCaja->token_establecimiento,
          "token_almacen" => $resCaja->token_establecimiento_almacen,
          "alias_almacen" => $JwtAuth->desencriptar($resCaja->alias),
          "token_direccion" => $tknDireccion,
          "tipoDireccion" => $tipoDireccion,
          "clasifDireccion" => $clasifDireccion,
          "aliasDireccion" => $aliasDireccion,
          "dir_completa" => $dir_completa,
          "latitud" => $resCaja->latitud,
          "longitud" => $resCaja->longitud,
          "pers_token" => $resCaja->token_responsables,
          "img_resp" =>  $avatar,
          "nombre" => $JwtAuth->desencriptar($resCaja->paterno) . " " . $JwtAuth->desencriptar($resCaja->materno) . " " . $JwtAuth->desencriptar($resCaja->nombre),

          "token_caja" => $resCaja->token_caja,
          "alias_caja" => $JwtAuth->desencriptar($resCaja->alias_caja),
          "caja" => $JwtAuth->generar($resCaja->no_caja),
          "saldofloat" => $salDoCaja[0]->saldo,
          "salDoCaja" => "$" . $salDoCaja[0]->saldo,
        );

        $caja[] = $arrayCaja;
      }

      $dataMensaje = array(
        'caja' => $caja,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'moneda' => 'required|string',
      'establecimiento_token' => 'required|string',
      'descripcion' => 'required|string',
      'cuenta_contable' => 'required|string',
      'servegresos' => 'required|boolean',
      'servingresos' => 'required|boolean',
      'servpropias' => 'required|boolean',
      'capt_cliente' => 'required|boolean',
      'capt_precio_x_articulo' => 'required|boolean',
      'capt_primero_cantidad' => 'required|boolean',
      'vendedor' => 'string',
      //'turnos' => 'string'
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
      $moneda = $request->input('moneda');
      $establecimiento_token = $request->input('establecimiento_token');
      $descripcion = $request->input('descripcion');
      $cuenta_contable = $request->input('cuenta_contable');
      $servegresos = $request->input('servegresos');
      $servingresos = $request->input('servingresos');
      $servpropias = $request->input('servpropias');
      $capt_cliente = $request->input('capt_cliente');
      $capt_precio_x_articulo = $request->input('capt_precio_x_articulo');
      $capt_primero_cantidad = $request->input('capt_primero_cantidad');
      $vendedor = $request->input('vendedor');

      $queryEmp = DB::table('main_empresas AS emp')
      ->join('main_empresa_usuario AS empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', '=', 'users.id')
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id','emp.zona_horaria')
      ->get();
        
      if ($queryEmp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontro la empresa seleccionada'
        );
      } else {
        foreach ($queryEmp as $vEmp) {
          $fecha_registro = time();
          date_default_timezone_set($vEmp->zona_horaria);

          $listaDirAlmacen = DB::select("SELECT alm.id FROM in_egr_establecimientos_catalogo AS alm JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers 
            JOIN teci_usuarios_catalogo AS users WHERE alm.token_establecimiento = ? AND alm.empresa = emp.id AND emp.empresa_token = ?
            AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token = ?",
            [$establecimiento_token, $empresa, $usuario]);
          //echo $listaDirAlmacen[0]->id;

          $folioCaja = DB::selectOne("SELECT COALESCE(MAX(fold.folder) + 1, 1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp ON fold.empresa = emp.id
            JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
            WHERE fold.fnzs_caja = TRUE AND emp.empresa_token = ? AND users.usuario_token = ?",
          [$empresa,$usuario]);
          $new_caja_folio = "CAJ-".$JwtAuth->generarFolio($folioCaja->folio);
          $tokenCaja = $JwtAuth->encriptarToken(time(), $listaDirAlmacen[0]->id, $descripcion);

          $caja = new CajaModelo();
          $caja->fecha_alta_caja = $fecha_registro;
          $caja->token_caja = $tokenCaja;
          $caja->no_caja = $folioCaja->folio;
          $caja->alias_caja = $JwtAuth->encriptar($descripcion);
          $caja->moneda_caja = $moneda;
          $caja->cuenta_contable_caja = $cuenta_contable;
          $caja->serv_egresos = $servegresos ? TRUE : FALSE;
          $caja->serv_ingresos = $servingresos ? TRUE : FALSE;
          $caja->serv_interno = $servpropias ? TRUE : FALSE;
          $caja->capt_cliente = $capt_cliente ? TRUE : FALSE;
          $caja->capt_precio_x_articulo = $capt_precio_x_articulo ? TRUE : FALSE;
          $caja->capt_primero_cantidad = $capt_primero_cantidad ? TRUE : FALSE;
          $caja->saldo_actual = '0.00';
          $caja->almacen = $listaDirAlmacen[0]->id;
          $caja->encargado_principal = !empty($vendedor) ? $JwtAuth->encriptar($vendedor) : NULL;
          $caja->fecha_delete_caja = '';
          $caja->status = TRUE;
          $caja->empresa = $vEmp->id;
          $savedCaja = $caja->save();
          if ($savedCaja) {
            $obtenCaja = $caja->id;

            if ($folioCaja->folio == 1) {
              $insertSistema = DB::table('sos_last_folders')
              ->insert(
                  array(
                    "fnzs_caja" => TRUE, 
                    "folder" => 1, 
                    "empresa" => $vEmp->id,
                  )
              );
            } else {
              $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp","sos_last_folders.empresa","=","emp.id")
              ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
              ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
              ->where([
                'sos_last_folders.fnzs_caja' => TRUE,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])
              ->limit(1)->update(
                array(
                  'sos_last_folders.folder' => $folioCaja->folio,
                )
              );
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Esta caja ha sido registrada satisfactoriamente con el folio $new_caja_folio"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Caja no registrada, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateAlmacenCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_diralmacen' => 'required|string',
      'token_responsable' => 'required|string',
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      date_default_timezone_set('America/Mexico_City');
      $token_diralmacen = $request->input('token_diralmacen');
      $token_responsable = $request->input('token_responsable');
      $token_caja = $request->input('token_caja');
      
      $selectValidCajaRespons = DB::select("SELECT caja FROM responsables_almacen WHERE token_responsables = ?",[$token_responsable]);

      if ($selectValidCajaRespons[0]->caja != '' && $selectValidCajaRespons[0]->caja != NULL) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Este empleado ya esta vinculado con la caja ' . $selectValidCajaRespons[0]->caja
        );
      } else {
        $selectTknCaja = DB::select("SELECT id FROM fnzs_catalogos_caja WHERE token_caja = ?", [$token_caja]);

        $selectTknDirAlm = DB::select("SELECT id FROM almacen WHERE token_almacen = ?", [$token_diralmacen]);

        $selectTknRespons = DB::select(
          "SELECT id FROM responsables_almacen WHERE token_responsables = ?",
          [$token_responsable]
        );

        $updateRepons = DB::table('responsables_almacen')
          ->where(
            [
              'token_responsables' => $token_responsable,
              'almacen' => $selectTknDirAlm[0]->id
            ]
          )
          ->limit(1)
          ->update(array('caja' => $selectTknCaja[0]->id));

        if ($updateRepons) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Actualización incorrecta'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateAlmacenNewCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'token_almacenOld' => 'required|string',
      'token_almacenNew' => 'required|string',
      'token_responsables' => 'required'
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
      $token_caja = $request->input('token_caja');
      $token_almacenOld = $request->input('token_almacenOld');
      $token_almacenNew = $request->input('token_almacenNew');
      $token_responsables = $request->input('token_responsables');
      
      $selectAlmacenOld = DB::select("SELECT id FROM almacen WHERE token_almacen = ?", [$token_almacenOld]);
      $selectAlmacenNew = DB::select("SELECT id,alias_almacen FROM almacen WHERE token_almacen = ?", [$token_almacenNew]);
      $selectTknCaja = DB::select("SELECT id,alias_caja FROM caja WHERE token_caja = ?", [$token_caja]);

      $contadorValidacion = 0;
      for ($i = 0; $i < count($token_responsables); $i++) {
        $countResponCaja = DB::table('responsables_almacen AS respAlm')
        ->join("fnzs_catalogos_caja AS caj", "respAlm.caja", "caj.id")
        ->where([
          'respAlm.responsable' => $token_responsables[$i],
          'caj.token_caja' => $token_caja
        ])
        ->count();

        if ($countResponCaja == 0) {
          $contadorValidacion++;
        }
      }

      if ($contadorValidacion == count($token_responsables) && count($selectAlmacenOld) == 1 && count($selectAlmacenNew) == 1 && count($selectTknCaja) == 1) {
        $updatCajalmOld = DB::table('caja')
        ->where([
          'almacen' => $selectAlmacenOld[0]->id,
          'token_caja' => $token_caja
        ])
        ->limit(1)
        ->update(array('almacen' => NULL));

        if ($updatCajalmOld) {
          $updateRespalmOld = DB::table('responsables_almacen')
          ->where([
            'almacen' => $selectAlmacenOld[0]->id,
            'caja' => $selectTknCaja[0]->id
          ])
          //->limit(1)
          ->update(array('caja' => NULL));

          if ($updateRespalmOld) {
            $updateCajalmNew = DB::table('caja')
              ->where(
                [
                  'token_caja' => $parametrosArray['token_caja']
                ]
              )
              ->limit(1)
              ->update(array('almacen' => $selectAlmacenNew[0]->id));

            if ($updateCajalmNew) {
              $contadorAlmacen = 0;
              for ($i = 0; $i < count($token_responsables); $i++) {
                $updateRespalmNew = DB::table('responsables_almacen')
                ->where([
                  'token_responsables' => $token_responsables[$i],
                  'caja' => NULL
                ])
                //->limit(1)
                ->update(array('caja' => $selectTknCaja[0]->id));
                if ($updateRespalmNew) {
                  $contadorAlmacen++;
                }
              }

              if ($contadorAlmacen == count($token_responsables)) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'La caja ' . $selectTknCaja[0]->alias_caja . ' ha sido vinculada al almacen ' . $JwtAuth->desencriptar($selectAlmacenNew[0]->alias_almacen) . '    satisfactoriamente'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 400,
                  'message' => 'Personal de almacen no valido'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Actualización incorrecta1'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Actualización incorrecta2'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Actualización incorrecta3'
          );
        }
      } else {
        if ($contadorValidacion < count($token_responsables)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'errorConfig' => 'INF-001',
            'message' => 'La información que intenta modificar presenta errores de configuración ó no se encuentra registrada'
          );
        }
        if (count($selectAlmacenOld) != 1 || count($selectAlmacenNew) != 1 || count($selectTknCaja) != 1) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'errorConfig' => 'INF-002',
            'message' => 'La información que intenta modificar presenta errores de configuración ó no se encuentra registrada'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desvincRespCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_diralmacen' => 'required|string',
      'token_responsable' => 'required|string',
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_diralmacen = $request->input('token_diralmacen');
      $token_caja = $request->input('token_caja');
      $token_responsable = $request->input('token_responsable');
      
      $selectTknDirAlm = DB::select("SELECT id FROM almacen WHERE token_almacen = ?", [$token_diralmacen]);
      //echo $selectTknDirAlm[0]->id; exit;

      $countRespon = DB::table('responsables_almacen AS respAlm')
      ->join("fnzs_catalogos_caja AS caj", "respAlm.caja", "caj.id")
      ->where('caj.token_caja',$token_caja)
      ->count();
      if ($countRespon == 1) {
        $dataMensaje = array(
          'code' => 400,
          'status' => 'error',
          'message' => 'No se puede desvincular, porqué no existe otro personal asignado'
        );
      } else if ($countRespon > 1) {
        $updateCajaRepons = DB::table('responsables_almacen')
        ->where([
          'token_responsables' => $token_responsable,
          'almacen' => $selectTknDirAlm[0]->id
        ])
        ->limit(1)
        ->update(array('caja' => NULL));

        if ($updateCajaRepons) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Actualización incorrecta'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function vinculaRespCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_diralmacen' => 'required|string',
      'token_responsable' => 'required|string',
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_diralmacen = $request->input('token_diralmacen');
      $token_responsable = $request->input('token_responsable');
      $token_caja = $request->input('token_caja');
      
      $selectTknCaja = DB::select("SELECT id FROM fnzs_catalogos_caja WHERE token_caja = ?", [$token_caja]);

      $countRespon = DB::table('responsables_almacen AS respAlm')
      ->join("in_egr_establecimientos_catalogo AS alm", "respAlm.almacen", "alm.id")
      ->where([
        'alm.token_almacen' => $token_diralmacen,
        'respAlm.token_responsables' => $token_responsable,
        'respAlm.caja' => NULL
      ])->count();
      //echo $countRespon; exit;
      if ($countRespon == 0) {
        $updateCajaRepons = DB::table('responsables_almacen AS respAlm')
        ->join("in_egr_establecimientos_catalogo AS alm", "respAlm.almacen", "alm.id")
        ->where([
          'respAlm.token_responsables' => $token_responsable,
          'alm.token_almacen' => $token_diralmacen
        ])
        ->limit(1)
        ->update(array('respAlm.caja' => $selectTknCaja[0]->id));
        //echo $updateCajaRepons; exit;

        if ($updateCajaRepons) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Actualización no realizada'
          );
        }
      } else {
        $personal = DB::select("SELECT caj.no_caja FROM responsables_almacen AS resp JOIN fnzs_catalogos_caja AS caj WHERE resp.caja = caj.id");

        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'El personal que intenta vincular a esta caja ya se encuentra vinculado a la caja' .
            $JwtAuth->generar($personal[0]->no_caja)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'moneda' => 'required|string',
      'establecimiento_token' => 'required|string',
      'descripcion' => 'required|string',
      'cuenta_contable' => 'required|string',
      'servegresos' => 'required|boolean',
      'servingresos' => 'required|boolean',
      'servpropias' => 'required|boolean',
      'capt_cliente' => 'required|boolean',
      'capt_precio_x_articulo' => 'required|boolean',
      'capt_primero_cantidad' => 'required|boolean',
      'vendedor' => 'required|string',
      //'turnos' => 'string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_caja = $request->input('token_caja');
      $monedaCaja = $request->input('moneda');
      $establecimiento_token = $request->input('establecimiento_token');
      $descripcion = $request->input('descripcion');
      $cuenta_contable = $request->input('cuenta_contable');
      $servegresos = $request->input('servegresos');
      $servingresos = $request->input('servingresos');
      $servpropias = $request->input('servpropias');
      $capt_cliente = $request->input('capt_cliente');
      $capt_precio_x_articulo = $request->input('capt_precio_x_articulo');
      $capt_primero_cantidad = $request->input('capt_primero_cantidad');
      $vendedor = $request->input('vendedor');

      $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
      ->join("teci_direcciones AS dir", "alm.id", "dir.establecimiento")
      ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();

      foreach ($queryCaja as $vCaja) {
        $obten_vendedor = DB::table("vhum_empleados_catalogo")->where("empleado_token", $vendedor)->value("id");

        //$listaDirAlmacen = DB::select("SELECT alm.id FROM in_egr_establecimientos_catalogo AS alm JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers 
        //              JOIN teci_usuarios_catalogo AS users WHERE alm.token_establecimiento = ? AND alm.empresa = emp.id AND emp.empresa_token = ?
        //              AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token = ?",
        //  [$establecimiento_token, $empresa, $usuario]
        //);

        $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS alm")
        ->join("main_empresas AS emp", "alm.empresa", "emp.id")
        ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
        ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
        ->where([
          'alm.token_establecimiento' => $establecimiento_token,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])->value("alm.id");

        $updatCaja = DB::table('fnzs_catalogos_caja')
        ->where('token_caja',$vCaja->token_caja)->limit(1)
        ->update(
          array(
            'alias_caja' => $JwtAuth->encriptar($descripcion),
            'moneda_caja' => $monedaCaja,
            'cuenta_contable_caja' => $cuenta_contable,
            'serv_egresos' => $servegresos ? TRUE : FALSE,
            'serv_ingresos' => $servingresos ? TRUE : FALSE,
            'serv_interno' => $servpropias ? TRUE : FALSE,
            'capt_cliente' => $capt_cliente ? TRUE : FALSE,
            'capt_precio_x_articulo' => $capt_precio_x_articulo ? TRUE : FALSE,
            'capt_primero_cantidad' => $capt_primero_cantidad ? TRUE : FALSE,
            'almacen' => $listaDirAlmacen,
            'encargado_principal' => !empty($vendedor) ? $JwtAuth->encriptar($vendedor) : NULL,
          )
        );
        if ($updatCaja) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización de caja completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Actualización no realizada'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function editaCorteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'token_cortecaja' => 'required|string',
      'horario_cortecaja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $horario_cortecaja = $request->input('horario_cortecaja');
      $token_cortecaja = $request->input('token_cortecaja');
      $token_caja = $request->input('token_caja');
      
      $horario_corte = $JwtAuth->encriptar($horario_cortecaja);

      $updateHorCrtCaja = DB::table('corte_caja')
      ->join("fnzs_catalogos_caja AS caj", "corte_caja.caja", "caj.id")
      ->where([
        "corte_caja.token_cortecaja" => $token_cortecaja,
        "caj.token_caja" => $token_caja
      ])
      ->limit(1)
      ->update(array('corte_caja.horario_corte' => $horario_corte));

      if ($updateHorCrtCaja) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El corte caja se ha actualizado correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Error al actualizado el corte caja, comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteCorteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'token_cortecaja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_caja = $request->input('token_caja');
      $token_cortecaja = $request->input('token_cortecaja');
      
      $cajaID = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");

      $insertNewCorte = DB::table('corte_caja')
      ->where([
        "token_cortecaja" => $token_cortecaja,
        "caja" => $cajaID
      ])
      ->delete();

      if ($insertNewCorte) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El corte caja se ha eliminado correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Error al eliminado el corte caja, comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function agregaNewCorteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'horario_cortecaja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_caja = $request->input('token_caja');
      $horario_cortecaja = $request->input('horario_cortecaja');
      
      $cajaID = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
      $empresaID = DB::table("main_empresas")->where("empresa_token",$empresa)->value("id");
      $token_cortecaja = $JwtAuth->encriptarToken(time(), $cajaID, $horario_cortecaja, $empresaID);

      $insertNewCorte = DB::table('corte_caja')
      ->insert(array(
        "token_cortecaja" => $token_cortecaja,
        "caja" => $cajaID,
        "horario_corte" => $horario_cortecaja,
        "empresa" => $empresaID
      ));

      if ($insertNewCorte) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El corte caja se ha guardado correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Error al guardar el corte caja, comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_caja = $request->input('token_caja');
      
      $consultCaja = CajaModelo::join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();

      if ($consultCaja == 1) {
        $consultCajaCompr = CajaModelo::join("eegr_compras AS comp", "fnzs_catalogos_caja.id", "comp.caja_paga")
        ->where('fnzs_catalogos_caja.token_caja', $token_caja)
        ->count();
        
        $consultCajaVentas = CajaModelo::join("ingr_ventas AS vent", "fnzs_catalogos_caja.id", "vent.caja")
        ->where('fnzs_catalogos_caja.token_caja', $token_caja)
        ->count();

        $consultCajaDisp = CajaModelo::join("teci_dispositivos AS disp", "fnzs_catalogos_caja.id", "disp.caja")
        ->where('fnzs_catalogos_caja.token_caja', $token_caja)
        ->count();

        //echo $consultCajaVentas;
        if ($consultCajaCompr == 0 && $consultCajaVentas == 0 && $consultCajaDisp == 0) {
          $updateStatusCaja = DB::table('fnzs_catalogos_caja')
          ->where(['token_caja' => $token_caja])
          ->limit(1)->update(array(
            'fecha_delete_caja' => time(), 
            'status' => FALSE
          ));

          if ($updateStatusCaja) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'La caja se ha eliminado correctamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 500,
              'message' => 'Error al eliminar caja, comuniquese a soporte'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 409,
            'message' => 'La caja que intenta eliminar esta vinvulada a compras o ventas realizadas'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'La caja que intenta eliminar no existe'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_caja = $request->input('token_caja');
      
      $consultCaja = CajaModelo::join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->count();

      if ($consultCaja == 1) {
        $updateStatusCaja = DB::table('fnzs_catalogos_caja')
        ->where('token_caja',$token_caja)
        ->limit(1)->update(array(
          'fecha_delete_caja' => NULL,
          'status' => TRUE
        ));

        if ($updateStatusCaja) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La caja se ha restaurado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La caja que intenta restaurar es incorrecta'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La caja que intenta restaurar no existe'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPrmannteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_caja = $request->input('token_caja');
      
      $consultCaja = CajaModelo::join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();

      if ($consultCaja == 1) {
        $consultCajaRepALm = DB::table('in_egr_establecimientos_responsables AS resp')
        ->join("fnzs_catalogos_caja AS caj", "resp.caja", "caj.id")
        ->where("caj.token_caja",$token_caja)->count();

        if ($consultCajaRepALm >= 1) {
          $updateCajaRepons = DB::table('in_egr_establecimientos_responsables AS resp')
            ->join("fnzs_catalogos_caja AS caj", "resp.caja", "caj.id")
            ->where("caj.token_caja",$token_caja)
            ->update(array('resp.caja' => NULL));

          if (!$updateCajaRepons) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'La caja que intenta eliminar esta vinculada con algun personal'
            );
          }
        }

        $deleteCaja = DB::table('fnzs_catalogos_caja')->where('token_caja',$token_caja)->limit(1)->delete();

        if ($deleteCaja) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La caja se ha eliminado permanentemente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La caja que intenta eliminar es incorrecta'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La caja que intenta eliminar no existe'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MonedElectModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;

class FNZS_MonedElectController extends Controller{
  public function monederosElectronicos(){
    $arrayMonElectr = array();
    $monederoElectr = MonedElectModelo::all();
    foreach ($monederoElectr as  $valMonElect) {
      $arrayMonedero = array(
        "token_monelectronico" => $valMonElect->token_monelectronico,
        "nombre" => $valMonElect->nombre
      );
      $arrayMonElectr[] = $arrayMonedero;
    }

    return response()->json([
      'monedero' => $arrayMonElectr,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function responsableMonedero(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $selectEmp = DB::table("main_empresas AS emp")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->select('emp.id AS id_emp','emp.zona_horaria')
    ->first();
    
    $respMonedero = CuentaMonederoModelo::join("teci_plataformas_digitales AS pdig", "fnzs_catalogos_cuentas_monedero.monedero", "pdig.id")
    ->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas_monedero.responsable", "pers.id")
    ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
    ->where([
      'fnzs_catalogos_cuentas_monedero.status' => TRUE,
      'fnzs_catalogos_cuentas_monedero.empresa' => $selectEmp->id_emp,
      'users.usuario_token' => $usuario
    ])
    ->where([
      'fnzs_catalogos_cuentas_monedero.egresos' => TRUE
    ])
    ->orwhere([
      'fnzs_catalogos_cuentas_monedero.v_humano' => TRUE
    ])->get();

    if ($respMonedero->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No existe cuenta de monedero electrónico asociada a este usuario'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $detalleMonedero = array();
      $arrayOpcionAdicional = array();
      
      foreach ($respMonedero as $resMonedero) {
        $cuenta_bancaria = '';
        $name_cuenta = '';
        $token_caja = '';
        $folio_caja = '';
        $alias_caja = '';

        date_default_timezone_set($selectEmp->zona_horaria);

        if ($resMonedero->cuenta_banco != '') {
          $tknCount = DB::select("SELECT token_cuenta FROM cuenta WHERE id = ? ", [$resMonedero->cuenta_banco]);
          $cuentaBancoMon = CuentBancModelo::join("main_empresas AS emp", "cuenta.empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where([
              'cuenta.status' => TRUE,
              'cuenta.token_cuenta' => $tknCount[0]->token_cuenta,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])->get();
          foreach ($cuentaBancoMon as $resCuentaMon) {
            $cuenta_bancaria = $resCuentaMon->token_cuenta;
            $name_cuenta = $JwtAuth->desencriptar($resCuentaMon->cuenta);
          }
        }

        if ($resMonedero->caja != '') {
          $tokenCaja = DB::select("SELECT token_caja FROM caja WHERE id = ? ", [$resMonedero->caja]);
          $cajaMonedero = CajaModelo::join("main_empresas AS emp", "caja.empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where([
              'caja.status' => TRUE,
              'caja.token_caja' => $tokenCaja[0]->token_caja,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])->get();

          foreach ($cajaMonedero as $resCajaMon) {
            $token_caja = $resCajaMon->token_caja;
            $folio_caja = $JwtAuth->generar($resCajaMon->no_caja);
            $alias_caja = $JwtAuth->desencriptar($resCajaMon->alias_caja);
          }
        }

        $titular = $JwtAuth->desencriptar($resMonedero->titular);

        $moneda = DB::select("SELECT codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resMonedero->moneda]);
        $resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

        $egresos = (bool)$resMonedero->egresos;
        $v_humano = (bool)$resMonedero->v_humano;

        $selectManejCuenta = DB::table('fnzs_catalogos_cuentas_manejo')
          ->join("fnzs_catalogos_cuentas_monedero AS countMon", "fnzs_catalogos_cuentas_manejo.cuenta_monedero", "countMon.id")
          ->join("main_empresas AS emp", "fnzs_catalogos_cuentas_manejo.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
          ->join("sos_personas AS people", "pers.personal", "people.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where([
            'fnzs_catalogos_cuentas_manejo.cuenta_bancaria' => NULL,
            'countMon.token_cuentamonedero' => $resMonedero->token_cuentamonedero,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario
          ])->get();

        foreach ($selectManejCuenta as $resOpciones) {
          $chequera = (bool)$resOpciones->chequera;
          $credito = (bool)$resOpciones->credito;
          $debito = (bool)$resOpciones->debito;

          $arrayOptions = array(
            "token_manejocuentas" => $resOpciones->token_manejocuentas,
            "chequera" => $chequera,
            "credito" => $credito,
            "debito" => $debito,
            "valorManejo" => $resOpciones->clave_referencia,
            "token_personal" => $resOpciones->pers_token,
            "nombre_completo" => $JwtAuth->desencriptar($resOpciones->paterno)." ".$JwtAuth->desencriptar($resOpciones->materno)." ".$JwtAuth->desencriptar($resOpciones->nombre),
          );
          $arrayOpcionAdicional[] = $arrayOptions;
        }

        $decimalesMoneda = DB::select(
          "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
          [$empresa, $usuario]
        );

        //suman
        $cobroVenta = DB::select(
          "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE 
                            AND movim.subtipo_movimiento = 'V' AND movim.cobro = cobrar.id AND movim.cuenta_monedero = moned.id 
                            AND cobrar.cuenta_monedero = moned.id AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id 
                            AND cobrar.empresa = emp.id AND moned.empresa = emp.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        $devolucionCompra = DB::select(
          "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE 
                            AND movim.subtipo_movimiento = 'D' AND movim.cobro = cobrar.id AND movim.cuenta_monedero = moned.id 
                            AND cobrar.cuenta_monedero = moned.id AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id 
                            AND cobrar.empresa = emp.id AND moned.empresa = emp.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        //restan
        $pagoCompra = DB::select(
          "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'C' 
                            AND movim.pago = payment.id AND movim.cuenta_monedero = moned.id AND payment.cuenta_monedero = moned.id
                            AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id AND payment.empresa = emp.id AND moned.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        $devolucionVenta = DB::select(
          "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE AND movim.subtipo_movimiento = 'D' 
                            AND movim.pago = payment.id AND movim.cuenta_monedero = moned.id AND payment.cuenta_monedero = moned.id
                            AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id AND payment.empresa = emp.id AND moned.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        $resultsalDoCuenta = $cobroVenta[0]->total + $devolucionCompra[0]->total - $pagoCompra[0]->total - $devolucionVenta[0]->total;
        $salDoCuenta = DB::select(
          "SELECT ROUND(?,?) AS saldoRound,FORMAT(?,?) AS saldoFormat",
          [$resultsalDoCuenta, $decimalesMoneda[0]->decimales, $resultsalDoCuenta, $decimalesMoneda[0]->decimales]
        );

        $arrayMonedero = array(
          'token_cuentaMon' => $resMonedero->token_cuentamonedero,
          'fecha_alta_cuentamoned' => date('d-m-Y H:i:s', $resMonedero->fecha_alta_cuentamoned),
          'folio' => $JwtAuth->generar($resMonedero->folio_cuentmon),

          'cuenta_bancaria' =>  $cuenta_bancaria,
          'name_cuenta_bancaria' =>  $name_cuenta,

          'token_caja' => $token_caja,
          'folio_caja' => $folio_caja,
          'alias_caja' => $alias_caja,

          'referencia_encrypt' => $resMonedero->referencia,
          'referencia' => $resMonedero->referencia,
          'cuenta_monedero_encrypt' => $resMonedero->cuenta,
          'cuenta_monedero' => $resMonedero->cuenta,
          'clabe_inter_encrypt' => $resMonedero->clabe_inter,
          'clabe_inter' => $resMonedero->clabe_inter,
          'titular' => $titular,
          'moneda' => $resMoneda,
          'egresos' => $egresos,
          'v_humano' => $v_humano,
          'vigencia' => date('d-m-Y H:i:s', $resMonedero->vigencia),
          'opciones_adicionales' => $arrayOpcionAdicional,
          'saldofloat' => $salDoCuenta[0]->saldoRound,
          'salDoCuenta' => "$" . $salDoCuenta[0]->saldoFormat,
        );

        $detalleMonedero[] = $arrayMonedero;
      }
      $dataMensaje = array(
        'monedero' => $detalleMonedero,
        'code' => 200,
        'status' => 'success'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);


    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);



    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Monedero electrónico invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);




        //echo 'coun caja '.count($respMonedero); 
        if (count($respMonedero) != 0) {

        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'No existe cuenta de monedero electrónico asociada a este usuario',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'Los datos no son correctos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function folioMonederoElectronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $folioMonedero = DB::select("SELECT IF (max(folio_cuentmon) IS NOT NULL,(max(folio_cuentmon)+1),1) AS folio FROM cuenta_monedero AS monedero 
      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE monedero.empresa = emp.id 
      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$empresa, $usuario]
    );

    return response()->json([
      'monedero' => $JwtAuth->generar($folioMonedero[0]->folio),
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function ListaMonederoVig(Request $request){
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
      
      $queryMonederos = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fnzs_catalogos_cuentas_monedero.fecha_alta_cuentamoned", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('fnzs_catalogos_cuentas_monedero.id', 'DESC')
      ->get();

      if ($queryMonederos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron monederos electrónicos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaMonedero = array();

        foreach ($queryMonederos  as $vMoned) {
          $moneda_decimales = $JwtAuth->getMonedaAPI($vMoned->moneda);
          $cuenta_result_saldo = $this->saldoMonederoByToken($moneda_decimales, $vMoned->token_cuentamonedero, $empresa, $usuario);
          $folio_cuenta = "CUENTM-" . $JwtAuth->generarFolio($vMoned->folio_cuentmon);
          $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($vMoned->cuenta), -4), -4);
          $arrayMonedero = array(
            "folio_cuenta" => $folio_cuenta,
            "token_cuentaMon" => $vMoned->token_cuentamonedero,
            "cuenta_monedero" => "**** **** **** $cuenta_descifrada_substr",
            "egresos" => $vMoned->egresos ? true : false,
            "ingresos" => $vMoned->ingresos ? true : false,
            "v_humano" => $vMoned->v_humano ? true : false,
            "plataforma_electronica" => $JwtAuth->desencriptar($vMoned->plataforma_electronica),
            "saldo_cuenta" => $cuenta_result_saldo,
            "saldo_cuenta_format" => "$" . number_format($cuenta_result_saldo, $moneda_decimales, '.', ',') . " $vMoned->moneda",
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
            //"disponible" => $vSal->disponible ? true : false,
            "monto_aplicar" => 0,
            "_filtro_busqueda" => "$folio_cuenta **** **** **** $cuenta_descifrada_substr",
          );
          $listaMonedero[] = $arrayMonedero;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'monedero' => $listaMonedero,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function saldoMonederoByToken($moneda_decimales, $token_monedero, $empresa_token){
    //create table fnzs_cobros_monederos_cobro(
    //  cobro_realizado int(10),
    //  cuenta_relacionada int(10),
    //  foreign key (cobro_realizado) references fnzs_cobros_cobro (id),
    //  foreign key (cuenta_relacionada) references fnzs_catalogos_cuentas_monedero (id)
    //);
    //SUMAN    
    $cobroVentaMon = DB::select("SELECT TRUNCATE(SUM(movim.monto_aplicado * cobrar.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_cobros_monederos_cobro AS cobmon JOIN fnzs_cobros_cobro AS cobrar JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'V' 
        AND movim.cuenta_monedero = mon.id AND mon.token_cuentamonedero = ? AND movim.cobro = cobrar.id AND mon.id = cobmon.cuenta_relacionada AND cobmon.cobro_realizado = cobrar.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $token_monedero, $empresa_token]);

    $devolucionCompraMon = DB::select("SELECT TRUNCATE(SUM(movim.monto_aplicado * cobrar.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_cobros_monederos_cobro AS cobmon JOIN fnzs_cobros_cobro AS cobrar JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'D' 
        AND movim.cuenta_monedero = mon.id AND mon.token_cuentamonedero = ? AND movim.cobro = cobrar.id AND mon.id = cobmon.cuenta_relacionada AND cobmon.cobro_realizado = cobrar.id
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $token_monedero, $empresa_token]);

    //$ajusteDeposito = DB::select("SELECT TRUNCATE(SUM(ajus.saldo_ajuste * ajus.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
    //JOIN fnzs_catalogos_cuentas_ajustes AS ajus JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'A' AND movim.cuenta_monedero = mon.id 
    //AND mon.token_cuentamonedero = ? AND movim.ajuste = ajus.id AND ajus.cuenta_bancaria = mon.id AND mon.empresa = emp.id AND emp.empresa_token = ?",
    //[$moneda_decimales,$token_monedero,$empresa_token]);

    $suman_mon = $cobroVentaMon[0]->total + $devolucionCompraMon[0]->total; // + $ajusteDeposito[0]->total;

    //create table fnzs_pagos_monederos_pago(
    //  pago_realizado int(10),
    //  cuenta_relacionada int(10),
    //  foreign key (pago_realizado) references fnzs_pagos_pago (id),
    //  foreign key (cuenta_relacionada) references fnzs_catalogos_cuentas_monedero (id)
    //);
    //RESTAN
    $pagoCompraMon = DB::select("SELECT TRUNCATE(SUM(movim.monto_aplicado * payment.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_pagos_monederos_pago AS pagmon JOIN fnzs_pagos_pago AS payment JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'C' 
        AND movim.cuenta_monedero = mon.id AND mon.token_cuentamonedero = ? AND movim.pago = payment.id AND mon.id = pagmon.cuenta_relacionada AND pagmon.pago_realizado = payment.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $token_monedero, $empresa_token]);

    $devolucionVentaMon = DB::select("SELECT TRUNCATE(SUM(movim.monto_aplicado * payment.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_pagos_monederos_pago AS pagmon JOIN fnzs_pagos_pago AS payment JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'D' 
        AND movim.cuenta_monedero = mon.id AND mon.token_cuentamonedero = ? AND movim.pago = payment.id AND mon.id = pagmon.cuenta_relacionada AND pagmon.pago_realizado = payment.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $token_monedero, $empresa_token]);

    $reembEmpleadoMon = DB::select("SELECT TRUNCATE(SUM(movim.monto_aplicado * payment.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_pagos_monederos_pago AS pagmon JOIN fnzs_pagos_pago AS payment JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'R' 
        AND movim.cuenta_monedero = mon.id AND mon.token_cuentamonedero = ? AND movim.pago = payment.id AND mon.id = pagmon.cuenta_relacionada AND pagmon.pago_realizado = payment.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $token_monedero, $empresa_token]);

    $restan_mon = $pagoCompraMon[0]->total + $devolucionVentaMon[0]->total + $reembEmpleadoMon[0]->total;
    $resultsaldoMON = $suman_mon - $restan_mon;
    return $resultsaldoMON;
  }

  private function saldoMonederoById($token_moneda, $id_monedero, $empresa_token){
    //SUMAN mon.id = ? $id_monedero
    //create table fnzs_cobros_monederos_cobro(
    //  cobro_realizado int(10),
    //  cuenta_relacionada int(10),
    //  foreign key (cobro_realizado) references fnzs_cobros_cobro (id),
    //  foreign key (cuenta_relacionada) references fnzs_catalogos_cuentas_monedero (id)
    //);
    //SUMAN    
    $cobroVentaMon = DB::select("SELECT TRUNCATE(SUM(cobrar.monto_cobro * cobrar.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_cobros_monederos_cobro AS cobmon JOIN fnzs_cobros_cobro AS cobrar JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'V' 
        AND movim.cuenta_monedero = mon.id AND mon.id = ? AND movim.cobro = cobrar.id AND mon.id = cobmon.cuenta_relacionada AND cobmon.cobro_realizado = cobrar.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $id_monedero, $empresa_token]);

    $devolucionCompraMon = DB::select("SELECT TRUNCATE(SUM(cobrar.monto_cobro * cobrar.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_cobros_monederos_cobro AS cobmon JOIN fnzs_cobros_cobro AS cobrar JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'D' 
        AND movim.cuenta_monedero = mon.id AND mon.id = ? AND movim.cobro = cobrar.id AND mon.id = cobmon.cuenta_relacionada AND cobmon.cobro_realizado = cobrar.id
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $id_monedero, $empresa_token]);

    //$ajusteDeposito = DB::select("SELECT TRUNCATE(SUM(ajus.saldo_ajuste * ajus.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
    //JOIN fnzs_catalogos_cuentas_ajustes AS ajus JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'S' AND movim.subtipo_movimiento = 'A' AND movim.cuenta_monedero = mon.id 
    //AND mon.id = ? AND movim.ajuste = ajus.id AND ajus.cuenta_bancaria = mon.id AND mon.empresa = emp.id AND emp.empresa_token = ?",
    //[$moneda_decimales,$id_monedero,$empresa_token]);

    $suman_mon = $cobroVentaMon[0]->total + $devolucionCompraMon[0]->total; // + $ajusteDeposito[0]->total;

    //create table fnzs_pagos_monederos_pago(
    //  pago_realizado int(10),
    //  cuenta_relacionada int(10),
    //  foreign key (pago_realizado) references fnzs_pagos_pago (id),
    //  foreign key (cuenta_relacionada) references fnzs_catalogos_cuentas_monedero (id)
    //);
    //RESTAN
    $pagoCompraMon = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago * payment.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_pagos_monederos_pago AS pagmon JOIN fnzs_pagos_pago AS payment JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'C' 
        AND movim.cuenta_monedero = mon.id AND mon.id = ? AND movim.pago = payment.id AND mon.id = pagmon.cuenta_relacionada AND pagmon.pago_realizado = payment.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $id_monedero, $empresa_token]);

    $devolucionVentaMon = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago * payment.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_pagos_monederos_pago AS pagmon JOIN fnzs_pagos_pago AS payment JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'D' 
        AND movim.cuenta_monedero = mon.id AND mon.id = ? AND movim.pago = payment.id AND mon.id = pagmon.cuenta_relacionada AND pagmon.pago_realizado = payment.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $id_monedero, $empresa_token]);

    $reembEmpleadoMon = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago * payment.tipo_cambio),?) AS total FROM fnzs_actividad_movimientos AS movim JOIN fnzs_catalogos_cuentas_monedero AS mon 
        JOIN fnzs_pagos_monederos_pago AS pagmon JOIN fnzs_pagos_pago AS payment JOIN main_empresas AS emp WHERE movim.tipo_movimiento = 'R' AND movim.subtipo_movimiento = 'R' 
        AND movim.cuenta_monedero = mon.id AND mon.id = ? AND movim.pago = payment.id AND mon.id = pagmon.cuenta_relacionada AND pagmon.pago_realizado = payment.id 
        AND mon.empresa = emp.id AND emp.empresa_token = ?", [$moneda_decimales, $id_monedero, $empresa_token]);

    $restan_mon = $pagoCompraMon[0]->total + $devolucionVentaMon[0]->total + $reembEmpleadoMon[0]->total;
    $resultsaldoMON = $suman_mon - $restan_mon;
    return $resultsaldoMON;
  }

  public function ListaMonederoDel(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryMonedero = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'fnzs_catalogos_cuentas_monedero.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->orderBy('fnzs_catalogos_cuentas_monedero.id', 'DESC')
    ->get();

    if ($queryMonedero->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron monederos electrónicos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaMonedero = array();
      
      foreach ($queryMonedero as $vMoned) {
        date_default_timezone_set($vMoned->zona_horaria);
        $arrayMonedero = array(
          "folio_cuenta" => "CUENTM-" . $JwtAuth->generarFolio($vMoned->folio_cuentmon),
          "token_cuentaMon" => $vMoned->token_cuentamonedero,
          "cuenta_backend" => $vMoned->cuenta,
          "cuenta_frontend" => $vMoned->cuenta,
          "egresos" => $vMoned->egresos ? true : false,
          "ingresos" => $vMoned->ingresos ? true : false,
          "v_humano" => $vMoned->v_humano ? true : false,
          "plataforma_electronica" => $JwtAuth->desencriptar($vMoned->plataforma_electronica),
          "fecha_delete" => date('d-m-Y H:i:s', $vMoned->fecha_delete_mon)
        );
        $listaMonedero[] = $arrayMonedero;
      }
      
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'monedero' => $listaMonedero,
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleMonederoVig(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');
      
      $queryMonedero = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.status' => TRUE,
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($queryMonedero->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron monederos electrónicos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $detalleMonedero = array();

        foreach ($queryMonedero as $vMoned) {
          $arrayOpcionAdicional = array();

          $selectManejo = DB::table("fnzs_catalogos_cuentas_medios_operacion AS medOper")
          ->join("fnzs_catalogos_cuentas_monedero AS countMon", "medOper.cuenta_monedero", "countMon.id")
          ->where('countMon.token_cuentamonedero', $vMoned->token_cuentamonedero)
          ->get();

          $num_lista = 1;
          foreach ($selectManejo as $vMan) {
            $optionAddc = array(
              "token_medio_operacion" => $vMan->token_medio_operacion,
              "num_lista" => $num_lista,
              "clave" => $vMan->medio_operacion,
              "valor" => $vMan->referencia_operacion,
              "vigencia" => !empty($vMan->vigencia) ? $vMan->vigencia : '---',
              "proceso_eliminacion" => false,
            );
            ++$num_lista;
            $arrayOpcionAdicional[] = $optionAddc;
          }

          $queryEmpResp = DB::table("sos_personas AS people")
          ->join("vhum_empleados_catalogo AS pers", "people.id", "pers.empleado_name")
          ->join("fnzs_catalogos_cuentas_monedero AS cmoned", "pers.id", "cmoned.responsable")
          ->where('cmoned.token_cuentamonedero',$vMoned->token_cuentamonedero)
          ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno', 'people.nombre')
          ->first();

          $p_responsable_folio = $queryEmpResp ? "TRB-" . $JwtAuth->generarFolio($queryEmpResp->folio_pers) : "";
          $p_responsable_paterno = $queryEmpResp ? ucwords($JwtAuth->desencriptar($queryEmpResp->paterno)) : "";
          $p_responsable_materno = $queryEmpResp ? ucwords($JwtAuth->desencriptar($queryEmpResp->materno)) : "";
          $p_responsable_nombre = $queryEmpResp ? ucwords($JwtAuth->desencriptar($queryEmpResp->nombre)) : "";
          $responsable_name = $queryEmpResp ? "$p_responsable_folio $p_responsable_paterno $p_responsable_materno $p_responsable_nombre" : "";

          $caja_folio = $vMoned->caja ? "CAJ-" . $JwtAuth->generarFolio(DB::table("fnzs_catalogos_caja")->where("id", $vMoned->caja)->value("no_caja")) : '';
          $caja_alias = $vMoned->caja ? $JwtAuth->desencriptar(DB::table("fnzs_catalogos_caja")->where("id", $vMoned->caja)->value("alias_caja")) : '';

          $cuenta_folio = $vMoned->cuenta_banco ? 'CUENT-' . $JwtAuth->generarFolio(DB::table("fnzs_catalogos_cuentas")->where("id", $vMoned->cuenta_banco)->value("folio_cuenta")) : '';
          $banco_nombre_comercial = $vMoned->cuenta_banco ? DB::table("teci_bancos AS bank")->join("fnzs_catalogos_cuentas AS acc", "bank.id", "acc.banco")->where("acc.id", $vMoned->cuenta_banco)->value("bank.nombre_comercial") : '';
          $cuenta_descifrada = $vMoned->cuenta_banco ? substr($JwtAuth->decryptBankAccount(DB::table("fnzs_catalogos_cuentas")->where("id", $vMoned->cuenta_banco)->value("cuenta")), -4) : '';

          $arrayMonedero = array(
            'token_cuentaMon' => $vMoned->token_cuentamonedero,
            'folio' => $JwtAuth->generar($vMoned->folio_cuentmon),
            'plataforma_electronica' => $JwtAuth->desencriptar($vMoned->plataforma_electronica),
            'referencia' => $JwtAuth->decryptBankAccount($vMoned->referencia),
            'cuenta' => $JwtAuth->decryptBankAccount($vMoned->cuenta),
            'clabe_inter' => $JwtAuth->decryptBankAccount($vMoned->clabe_inter),
            'titular' => $JwtAuth->decryptBankAccount($vMoned->titular),
            'cuenta_contable' => $vMoned->mon_cuenta_contable,
            'moneda' => $vMoned->moneda,
            'mon_egresos' => $vMoned->egresos ? true : false,
            'mon_ingresos' => $vMoned->ingresos ? true : false,
            'mon_v_humano' => $vMoned->v_humano ? true : false,
            'vigencia' => date('Y-m', $vMoned->vigencia),
            'medios_operacion' => $arrayOpcionAdicional,
            'responsable_token' => $queryEmpResp ? $queryEmpResp->empleado_token : '',
            'responsable_name' => $responsable_name,
            'caja_token' => $vMoned->caja ? DB::table("fnzs_catalogos_caja")->where("id", $vMoned->caja)->value("token_caja") : '',
            'caja_alias' => "$caja_folio $caja_alias",
            'cuenta_banco_token' => $vMoned->cuenta_banco ? DB::table("fnzs_catalogos_cuentas")->where("id", $vMoned->cuenta_banco)->value("token_cuenta") : '',
            'cuenta_banco_numero' => $vMoned->cuenta_banco ? "**** **** **** $cuenta_descifrada" : '',
            'cuenta_filtro' => $vMoned->cuenta_banco ? "$banco_nombre_comercial $cuenta_folio **** **** **** $cuenta_descifrada" : ''
          );
          $detalleMonedero[] = $arrayMonedero;
        }

        $dataMensaje = array(
          'monedero' => $detalleMonedero,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarMonederoElectronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'plataforma_electronica' => 'required|string',
      'no_referencia' => 'required|string',
      'cuenta' => 'required|string',
      'clabe_inter' => 'required|string',
      'titularCuenta' => 'required|string',
      'cuenta_contable' => 'required|string',
      'moneda' => 'required|string',
      'egresos' => 'required|boolean',
      'ingresos' => 'required|boolean',
      'v_Humano' => 'required|boolean',
      'mediosOperacion' => 'array',
      'token_responsable' => 'string',
      'token_cuenta_bancaria' => 'string',
      'caja' => 'string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $plataforma_electronica = $request->input('plataforma_electronica');
      $no_referencia = $request->input('no_referencia');
      $cuenta = $request->input('cuenta');
      $clabe_inter = $request->input('clabe_inter');
      $titularCuenta = $request->input('titularCuenta');
      $cuenta_contable = $request->input('cuenta_contable');
      $moneda = $request->input('moneda');
      $egresos = $request->input('egresos');
      $ingresos = $request->input('ingresos');
      $v_Humano = $request->input('v_Humano');
      $medios_operacion = $request->input('mediosOperacion');
      $token_responsable = $request->input('token_responsable');
      $token_cuentaBanc = $request->input('token_cuenta_bancaria');
      $token_caja = $request->input('caja');
      
      $selectEmp = DB::table("main_empresas AS emp")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id AS id_emp','emp.zona_horaria')
      ->first();

      date_default_timezone_set($selectEmp->zona_horaria);

      $folioMonedero = DB::select("SELECT IF (max(folio_cuentmon) IS NOT NULL,(max(folio_cuentmon)+1),1) AS folio 
        FROM fnzs_catalogos_cuentas_monedero AS monedero JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE monedero.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
        AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
      $folio_cuenta = "CUENTM-".$JwtAuth->generarFolio($folioMonedero[0]->folio);

      $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token", $token_responsable)->value("id");
      $cuenta_banco = !empty($token_cuentaBanc) ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta", $token_cuentaBanc)->value("id") : NULL;
      $caja = !empty($token_caja) ? DB::table("fnzs_catalogos_caja")->where("token_caja", $token_caja)->value("id") : NULL;

      if (count($medios_operacion) != 0) {
        for ($i = 0; $i < count($medios_operacion); $i++) {
          $error_medios_operacion = "";
          if ($medios_operacion[$i]['clave'] == '') $error_medios_operacion = "Error en manejo de medios de operación";
          if ($medios_operacion[$i]['valor'] == '') $error_medios_operacion = "Error en la referencia de opciones adicionales";
          $dataMensaje = array('status' => 'error', 'code' => 400, 'message' => $error_medios_operacion);
        }
      }

      $tokenMonedero = $JwtAuth->encriptarToken(time(), $plataforma_electronica, $cuenta, $folioMonedero[0]->folio, $moneda, $egresos, $ingresos, $v_Humano, $pers_responsable, $cuenta_banco);
      $newMonedero = new CuentaMonederoModelo();
      $newMonedero->token_cuentamonedero = $tokenMonedero;
      $newMonedero->folio_cuentmon = $folioMonedero[0]->folio;
      $newMonedero->fecha_alta_cuentamoned = time();
      $newMonedero->plataforma_electronica = $JwtAuth->encriptar($plataforma_electronica);
      $newMonedero->referencia = $JwtAuth->encryptBankAccount($no_referencia);
      $newMonedero->cuenta = $JwtAuth->encryptBankAccount($cuenta);
      $newMonedero->clabe_inter = $JwtAuth->encryptBankAccount($clabe_inter);
      $newMonedero->titular = $JwtAuth->encryptBankAccount($titularCuenta);
      $newMonedero->mon_cuenta_contable = $cuenta_contable;
      $newMonedero->moneda = $moneda;
      $newMonedero->egresos = $egresos;
      $newMonedero->ingresos = $ingresos;
      $newMonedero->v_humano = $v_Humano;
      $newMonedero->responsable = $pers_responsable;
      $newMonedero->cuenta_banco = $cuenta_banco;
      $newMonedero->caja = $caja;
      $newMonedero->status = TRUE;
      $newMonedero->fecha_delete_mon = '';
      $newMonedero->empresa = $selectEmp[0]->id;
      $savedMonedero = $newMonedero->save();

      if ($savedMonedero) {
        $cuentaMon = $newMonedero->id;
        if (count($medios_operacion) != 0) {
          for ($i = 0; $i < count($medios_operacion); $i++) {
            $clave = $medios_operacion[$i]['clave'];
            $valor = $medios_operacion[$i]['valor'];
            $vigencia = isset($medios_operacion[$i]['vigencia']) && !empty($medios_operacion[$i]['vigencia']) ? $medios_operacion[$i]['vigencia'] : NULL;

            DB::table('fnzs_catalogos_cuentas_medios_operacion')
            ->insert(array(
              "token_medio_operacion" => $JwtAuth->encriptarToken($cuentaMon . $clave . $valor . $vigencia),
              "cuenta_monedero" => $cuentaMon,
              "medio_operacion" => $clave,
              "referencia_operacion" => $valor,
              "vigencia" => !empty($vigencia) ? $vigencia : NULL,
              "empresa" => $selectEmp[0]->id,
            ));
          }
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => "Monedero electrónico registrado correctamente con el folio $folio_cuenta"
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Los datos del monedero electrónico no son correctos, error al intentar registrar'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateMonederoElectronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cuentaMon' => 'required|string',
      'plataforma_electronica' => 'required|string',
      'no_referencia' => 'required|string',
      'cuenta' => 'required|string',
      'clabe_inter' => 'required|string',
      'titularCuenta' => 'required|string',
      'cuenta_contable' => 'required|string',
      'moneda' => 'required|string',
      'mediosOperacionNuevos' => 'array',
      'mediosOperacionDelete' => 'array',
      'egresos' => 'required|boolean',
      'ingresos' => 'required|boolean',
      'v_Humano' => 'required|boolean',
      'token_responsable' => 'string',
      'token_cuenta_bancaria' => 'string',
      'caja' => 'string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cuentaMon = $request->input('token_cuentaMon');
      $plataforma_electronica = $request->input('plataforma_electronica');
      $no_referencia = $request->input('no_referencia');
      $cuenta = $request->input('cuenta');
      $clabe_inter = $request->input('clabe_inter');
      $titularCuenta = $request->input('titularCuenta');
      $cuenta_contable = $request->input('cuenta_contable');
      $moneda = $request->input('moneda');
      $mediosOperacionNuevos = $request->input('mediosOperacionNuevos');
      $mediosOperacionDelete = $request->input('mediosOperacionDelete');
      $egresos = $request->input('egresos');
      $ingresos = $request->input('ingresos');
      $v_Humano = $request->input('v_Humano');
      $token_responsable = $request->input('token_responsable');
      $token_cuenta_bancaria = $request->input('token_cuenta_bancaria');
      $caja = $request->input('caja');

      $OKCuentaMon = isset($token_cuentaMon) && !empty($token_cuentaMon);
      $OKPlatElect = isset($plataforma_electronica) && !empty($plataforma_electronica) && preg_match($JwtAuth->filtroAlfaNumerico(),$plataforma_electronica);
      $OKNoReferen = isset($no_referencia) && !empty($no_referencia) && preg_match($JwtAuth->filtroNumericoSimple(),$no_referencia);
      $OKCuenta = isset($cuenta) && !empty($cuenta) && preg_match($JwtAuth->filtroNumericoSimple(),$cuenta);
      $OKClabeInte = isset($clabe_inter) && !empty($clabe_inter) && preg_match($JwtAuth->filtroNumericoSimple(),$clabe_inter);
      $OKTitularCu = isset($titularCuenta) && !empty($titularCuenta) && preg_match($JwtAuth->filtroAlfaNumerico(),$titularCuenta);
      $OKMoneda = isset($moneda) && !empty($moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda);
      $OKMedOpeNew = isset($mediosOperacionNuevos) && is_array($mediosOperacionNuevos) && count($mediosOperacionNuevos) > 0;
      $OKMedOpeDel = isset($mediosOperacionDelete) && is_array($mediosOperacionDelete) && count($mediosOperacionDelete) > 0;
      $OKMedOperac = $OKMedOpeNew || $OKMedOpeDel;
      $OKEgresos = isset($egresos) && is_bool($egresos);
      $OKIngresos = isset($ingresos) && is_bool($ingresos);
      $OKV_Humano = isset($v_Humano) && is_bool($v_Humano);
      $OKResponsab = isset($token_responsable) && !empty($token_responsable);
      $OKCuentaBan = isset($token_cuenta_bancaria) && !empty($token_cuenta_bancaria);
      $OKCaja = isset($caja) && !empty($caja);

      if ($OKCuentaMon || $OKPlatElect || $OKNoReferen || $OKCuenta || $OKClabeInte || $OKTitularCu || $OKMoneda || $OKMedOperac || $OKEgresos || $OKIngresos || $OKV_Humano || $OKResponsab || $OKCuentaBan || $OKCaja) {
        $consultCuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_cuentaMon,
            'fnzs_catalogos_cuentas_monedero.status' => TRUE,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario
          ])->count();

        if ($consultCuentaMon == 1) {
          $cuentaMon = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero", $token_cuentaMon)->value("id");
          $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token", $token_responsable)->value("id");
          $cuenta_banco_id = $OKCuentaBan ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta", $token_cuenta_bancaria)->value("id") : NULL;
          $caja_id = $OKCaja ? DB::table("fnzs_catalogos_caja")->where("token_caja", $caja)->value("id") : NULL;
          DB::table('fnzs_catalogos_cuentas_monedero')
          ->where('token_cuentamonedero',$token_cuentaMon)
          ->limit(1)->update(array(
            'plataforma_electronica' => $JwtAuth->encriptar($plataforma_electronica),
            'referencia' => $JwtAuth->encryptBankAccount($no_referencia),
            'cuenta' => $JwtAuth->encryptBankAccount($cuenta),
            'clabe_inter' => $JwtAuth->encryptBankAccount($clabe_inter),
            'titular' => $JwtAuth->encryptBankAccount($titularCuenta),
            'mon_cuenta_contable' => $cuenta_contable,
            'moneda' => $moneda,
            'egresos' => $egresos,
            'ingresos' => $ingresos,
            'v_humano' => $v_Humano,
            'responsable' => $pers_responsable,
            'cuenta_banco' => $cuenta_banco_id,
            'caja' => $caja_id,
          ));
            
          if ($OKMedOpeNew) {
            for ($i = 0; $i < count($mediosOperacionNuevos); $i++) {
              $clave = $mediosOperacionNuevos[$i]['clave'];
              $valor = $mediosOperacionNuevos[$i]['valor'];
              $vigencia = isset($mediosOperacionNuevos[$i]['vigencia']) && !empty($mediosOperacionNuevos[$i]['vigencia']) ? $mediosOperacionNuevos[$i]['vigencia'] : NULL;

              DB::table('fnzs_catalogos_cuentas_medios_operacion')
              ->insert(array(
                "token_medio_operacion" => $JwtAuth->encriptarToken($cuentaMon . $clave . $valor . $vigencia),
                "cuenta_monedero" => $cuentaMon,
                "medio_operacion" => $clave,
                "referencia_operacion" => $valor,
                "vigencia" => !empty($vigencia) ? $vigencia : NULL,
                "empresa" => DB::table("main_empresas")->where("empresa_token", $empresa)->value("id"),
              ));
            }
          }

          if ($OKMedOpeDel) {
            for ($i = 0; $i < count($mediosOperacionDelete); $i++) {
              $token_medio_operacion = $mediosOperacionDelete[$i]['token_medio_operacion'];
              DB::table('fnzs_catalogos_cuentas_medios_operacion')
              ->where("token_medio_operacion",$token_medio_operacion)
              ->limit(1)->delete();
            }
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Monedero electrónico actualizado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'El monedero electrónico que intenta modificar no existe'
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKCuentaMon) $mensaje_error = "Error al seleccionar monedero electrónico, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPlatElect) $mensaje_error = "Error al registrar plataforma electrónica, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKNoReferen) $mensaje_error = "Error al registrar número de referencia, intentelo nuevamente o comuniquese a soporte"; 

        if (!$OKCuenta) $mensaje_error = "Error al registrar número de cuenta, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKClabeInte) $mensaje_error = "Error al registrar clabe interbancaria, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKTitularCu) $mensaje_error = "Error al registrar titular, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKMoneda) $mensaje_error = "Error al seleccionar moneda, intentelo nuevamente o comuniquese a soporte";
        if (!$OKMedOpeNew) $mensaje_error = "Error al registrar nuevos medios de operación, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKMedOpeDel) $mensaje_error = "Error al seleccionar medios de operación para eliminar, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKEgresos) $mensaje_error = "Error al seleccionar si este monedero estara destinado al sector de egresos, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKIngresos) $mensaje_error = "Error al seleccionar si este monedero estara destinado al sector de ingresos, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKV_Humano) $mensaje_error = "Error al seleccionar si este monedero estara destinado al sector de valor humano, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKResponsab) $mensaje_error = "Error al seleccionar responsable vinculado, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKCuentaBan) $mensaje_error = "Error al seleccionar cuenta bancaria vinculada, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKCaja) $mensaje_error = "Error al seleccionar caja vinculada, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarNewManejoCuentasMon(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'user_token' => 'required|string',
      'token_cuentaMon' => 'required|string',
      'arrayManejo' => 'array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cuentaMon = $request->input('token_cuentaMon');
      $arrayManejo = $request->input('arrayManejo');
      $idCuentaMonedero = DB::table("cuenta_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");
      
      $selectEmp = DB::table("main_empresas AS emp")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id AS id_emp','emp.zona_horaria')
      ->first();

      date_default_timezone_set($selectEmp->zona_horaria);

      $token_manejo = $JwtAuth->encriptarToken(time(),$empresa,$arrayManejo,$token_cuentaMon);

      if ($arrayManejo != '') {
        $contador = 0;

        for ($i = 0; $i < count($arrayManejo); $i++) {
          if ($arrayManejo[$i]['clave'] != '' && $arrayManejo[$i]['valor'] != '' && $arrayManejo[$i]['responsable'] != '') {
            $chequera = $arrayManejo[$i]['clave'] == 'chequera' ? true : false;
            $credito = $arrayManejo[$i]['clave'] == 'Tarjetas de credito' ? true : false;
            $debito = $arrayManejo[$i]['clave'] == 'Tarjetas de debito' ? true : false;

            $encriptRef = $JwtAuth->encriptar($arrayManejo[$i]['valor']);
            $encriptRespons = $JwtAuth->encriptar($arrayManejo[$i]['responsable']);

            $contador++;
          } else {
            if ($arrayManejo[$i]['clave'] == '') {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en manejo de opciones adicionales'
              );
            }

            if ($arrayManejo[$i]['valor'] == '') {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en la referencia de opciones adicionales'
              );
            }

            if ($arrayManejo[$i]['responsable'] == '') {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en el responsable de opciones adicionales'
              );
            }
          }

          $insertManejo = DB::table('fnzs_catalogos_cuentas_manejo')
            ->insert(array(
              "token_manejocuentas" => $token_manejo,
              "cuenta_bancaria" => NULL,
              "cuenta_monedero" => $idCuentaMonedero[0]->id,
              "chequera" => $chequera,
              "credito" => $credito,
              "debito" => $debito,
              "referencia" => $encriptRef,
              "responsable" => $encriptRespons,
              "empresa" => $selectEmp[0]->id,
            ));

          if ($insertManejo) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Opción adicional registrada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Los datos de la opción adicional no son correctos, error al intentar registrar'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'El contenido de las opciones adicionales esta vacio'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);


    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cuentaMon' => 'required|string',
        'arrayManejo' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Monedero electrónico invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametros->user_token, true);


      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'Los datos no son correctos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminarMonederoElctronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');
      
      $consultcuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'fnzs_catalogos_cuentas_monedero.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($consultcuentaMon->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta de monedero electrónico que intenta eliminar no existe'
        );
      } else {
        $updateStatusMonedero = DB::table('fnzs_catalogos_cuentas_monedero')
        ->where('token_cuentamonedero', $token_monedero)
        ->limit(1)->update(array(
          'fecha_delete_mon' => time(),
          'status' => FALSE
        ));

        if ($updateStatusMonedero) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta de monedero electrónico se ha eliminado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al eliminar la cuenta de monedero electrónico, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarMonederoElctronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');
      
      $consultcuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'fnzs_catalogos_cuentas_monedero.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($consultcuentaMon->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta de monedero electrónico que intenta restaurar no existe'
        );
      } else {
        $updateStatusMonedero = DB::table('fnzs_catalogos_cuentas_monedero')
        ->where('token_cuentamonedero', $token_monedero)
        ->limit(1)->update(array(
          'fecha_delete_mon' => '',
          'status' => TRUE
        ));

        if ($updateStatusMonedero) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta de monedero electrónico se ha restaurado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al restaurar la cuenta de monedero electrónico, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletPermMonederoElctronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');

      $consultcuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'fnzs_catalogos_cuentas_monedero.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();
      
      if ($consultcuentaMon->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta de monedero electrónico que intenta eliminar no existe'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $updateStatusMonedero = DB::table('fnzs_catalogos_cuentas_monedero')
        ->where('token_cuentamonedero', $token_monedero)
        ->limit(1)->delete();

        if ($updateStatusMonedero) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta de monedero electrónico se ha eliminado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al eliminar la cuenta de monedero electrónico, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MovimientosBancariosModelo;

class FNZS_EstadoMovFinanProveedorController extends Controller{
  private function saldoInicialProveedorByToken($token_cat_proveedores,$fechaInicio){
    $saldoProvCompraOPago = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "payment.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
    ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->where('movim.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'S' THEN 1  /* S es DEBE (+) */
          WHEN movim.tipo_movimiento = 'R' THEN -1 /* R es HABER (-) */
          ELSE 0
        END
        
        * /* 2. Definimos el monto (priorizamos monto_aplicado) */
        COALESCE(movim.monto_aplicado,0)

        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as saldo_inicial
    "))
    ->value('saldo_inicial') ?? 0;

    $saldoProvAnticipoOPago = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "payment.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.anticipo_proveedor", "=", "ant.uuid_anticipo")
    ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "=", "catprov.id")
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->where('movim.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'S' THEN 1  /* S es DEBE (+) */
          WHEN movim.tipo_movimiento = 'R' THEN -1 /* R es HABER (-) */
          ELSE 0
        END
        
        * /* 2. Definimos el monto (priorizamos monto_aplicado) */
        COALESCE(movim.monto_aplicado,0)

        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as saldo_inicial
    "))
    ->value('saldo_inicial') ?? 0;

    $saldoProvPagoDirecto = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "=", "catprov.id")
    ->whereNotIn('payment.id', function($queryCFDI) {
      $queryCFDI->select('pago_realizado')->from('fnzs_pagos_pago_ordenes_vinculadas');
    })
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->where('movim.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'S' THEN 1  /* S es DEBE (+) */
          WHEN movim.tipo_movimiento = 'R' THEN -1 /* R es HABER (-) */
          ELSE 0
        END
        
        * /* 2. Definimos el monto (priorizamos monto_aplicado) */
        COALESCE(movim.monto_aplicado,0)

        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as saldo_inicial
    "))
    ->value('saldo_inicial') ?? 0;
    $saldo_inicial = $saldoProvCompraOPago + $saldoProvAnticipoOPago + $saldoProvPagoDirecto;
    return $saldo_inicial;
  }

  private function depositosProveedorByToken($token_cat_proveedores,$fechaInicio,$fechaFin){
    $depositoProvCompraOPago = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "payment.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
    ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    //->whereBetween("movim.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'S' THEN COALESCE(movim.monto_aplicado, 0) ELSE 0
        END 
        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as deposito_final
    "))
    ->value('deposito_final') ?? 0;

    $depositoProvAnticipoOPago = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "payment.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.anticipo_proveedor", "=", "ant.uuid_anticipo")
    ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "=", "catprov.id")
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->whereBetween("movim.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'S' THEN COALESCE(movim.monto_aplicado, 0) ELSE 0
        END 
        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as deposito_final
    "))
    ->value('deposito_final') ?? 0;

    $depositoProvPagoDirecto = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "=", "catprov.id")
    ->whereNotIn('payment.id', function($queryCFDI) {
      $queryCFDI->select('pago_realizado')->from('fnzs_pagos_pago_ordenes_vinculadas');
    })
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->whereBetween("movim.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'S' THEN COALESCE(movim.monto_aplicado, 0) ELSE 0
        END 
        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as deposito_final
    "))
    ->value('deposito_final') ?? 0;
    $deposito_final = $depositoProvCompraOPago + $depositoProvAnticipoOPago + $depositoProvPagoDirecto;
    return $deposito_final;
  }

  private function retirosProveedorByToken($token_cat_proveedores,$fechaInicio,$fechaFin){
    $retiroProvCompraOPago = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "payment.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
    ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->whereBetween("movim.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'R' THEN COALESCE(movim.monto_aplicado, 0) ELSE 0
        END 
        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as retiro_final
    "))
    ->value('retiro_final') ?? 0;

    $retiroProvAnticipoOPago = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS ordpv", "payment.id", "=", "ordpv.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "ordpv.orden_pago_vinculada", "=", "order.id")
    ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.anticipo_proveedor", "=", "ant.uuid_anticipo")
    ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "=", "catprov.id")
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->whereBetween("movim.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'R' THEN COALESCE(movim.monto_aplicado, 0) ELSE 0
        END 
        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as retiro_final
    "))
    ->value('retiro_final') ?? 0;

    $retiroProvPagoDirecto = DB::table("fnzs_actividad_movimientos AS movim")
    ->join("fnzs_pagos_pago AS payment", "movim.pago", "=", "payment.id")
    ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "=", "catprov.id")
    ->whereNotIn('payment.id', function($queryCFDI) {
      $queryCFDI->select('pago_realizado')->from('fnzs_pagos_pago_ordenes_vinculadas');
    })
    ->where('catprov.token_cat_proveedores',$token_cat_proveedores)
    ->whereBetween("movim.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
    ->select(DB::raw("
      SUM(
        CASE 
          WHEN movim.tipo_movimiento = 'R' THEN COALESCE(movim.monto_aplicado, 0) ELSE 0
        END 
        * /* 3. Buscamos el tipo de cambio en cualquier tabla relacionada que no sea nula */
        COALESCE(
          movim.tipo_cambio_movimiento, 
          payment.tipo_cambio, 
          1.0
        )
      ) as retiro_final
    "))
    ->value('retiro_final') ?? 0;

    $retiro_final = $retiroProvCompraOPago + $retiroProvAnticipoOPago + $retiroProvPagoDirecto;
    return $retiro_final;
  }

  private function parteRelMovPagoDirecto($pago,$JwtAuth){
    $vPago = DB::table("fnzs_pagos_pago AS pag")

    ->leftJoin("eegr_catalogo_proveedores AS catprov", "pag.vinc_proveedor", "=", "catprov.id")
    ->leftJoin("sos_personas AS prov_data", "catprov.proveedor", "=", "prov_data.id")

    ->leftJoin("ingr_catalogo_clientes AS catcli", "pag.vinc_cliente", "=", "catcli.id")
    ->leftJoin("sos_personas AS client_data", "catcli.cliente", "=", "client_data.id")
    
    ->leftJoin("vhum_empleados_catalogo AS trab", "pag.vinc_empleado", "=", "trab.id")
    ->leftJoin("sos_personas AS trab_data", "trab.personal", "=", "trab_data.id")

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
      date_default_timezone_set('America/Mexico_City');
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
        DB::raw("'orden' AS medio_pago"),
        "fnzs_actividad_movimientos.*",
        "pay.*",
        "emp.*",
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
        DB::raw("'orden' AS medio_pago"),
        "fnzs_actividad_movimientos.*",
        "pay.*",
        "emp.*",
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
        DB::raw("'directo' AS medio_pago"),
        "fnzs_actividad_movimientos.*",
        "pay.*",
        "emp.*",
      ]);
  
      $queryMovimientos = $movimientosProvByCompraOrdenPago->unionAll($movimientosProvByAnticipoOrdenPago)->unionAll($movimientosProvDirecto)
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
        
        $saldo_inicial_prov = $this->saldoInicialProveedorByToken($token_cat_proveedores,$fechaInicio);
        $saldo_acumulado_progresivo = $saldo_inicial_prov;
        $contador = 0;
        
        foreach ($queryMovimientos as $vMov) {
          date_default_timezone_set($vMov->zona_horaria);
          $token_movimiento = $vMov->token_movimiento;
          $folio_movimiento = 'MOV-'.$JwtAuth->generarFolio($vMov->folio_movimiento);
          $concepto_movimiento = !is_null($vMov->concepto_movimiento) && $vMov->concepto_movimiento != '' ? $JwtAuth->desencriptar($vMov->concepto_movimiento) : '';
          $mov_f_cont = $vMov->fecha_contabilizacion_movimiento;
          $fecha_movimiento = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('Y-m-d',$mov_f_cont) : '';
          $fecha_movimiento_excel = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('d/m/Y',$mov_f_cont) : '';

          $monto_applc = (float)$vMov->monto_aplicado;
          $documento_anterior_asociado = "PAGO-".$JwtAuth->generarFolio($vMov->folio_pagos);
          $parte_relacionada = $vMov->medio_pago == 'orden' ? $this->parteRelMovPagoByOrden($vMov->pago,$JwtAuth) : $this->parteRelMovPagoDirecto($vMov->pago,$JwtAuth);

          $movimiento_debe = $vMov->tipo_movimiento == 'S' ? $monto_applc : 0;
          $movimiento_haber = $vMov->tipo_movimiento == 'R' ? $monto_applc : 0;
          $saldo_acumulado_progresivo += ($movimiento_debe - $movimiento_haber);

          if (!is_null($vMov->movimiento_asociado)) {
            $folio_mov_asociado = 'MOV-'.$JwtAuth->generarFolio(DB::table("fnzs_actividad_movimientos")->where("id",$vMov->movimiento_asociado)->value("folio_movimiento"));
            $concepto_movimiento = 'CANCELACIÓN DE MOVIMIENTO - '.$folio_mov_asociado;
          }

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
            "mov_monto_debe_format" => $movimiento_debe > 0 ? "$".number_format($movimiento_debe,$decimalesMoneda,'.', ',') : '',
            "mov_monto_haber" => $movimiento_haber,
            "mov_monto_haber_format" => $movimiento_haber > 0 ? "$".number_format($movimiento_haber,$decimalesMoneda,'.', ',') : '',
            "mov_monto_saldo" => $saldo_acumulado_progresivo,
            "mov_monto_saldo_format" => "$".number_format($saldo_acumulado_progresivo,$decimalesMoneda,'.', ','),
            "documento_anterior_asociado" => $documento_anterior_asociado,
            "observaciones_movimiento" => $JwtAuth->desencriptar($vMov->observaciones_movimiento),
          ];
          ++$contador;
        }

        $prov_result_depositos = $this->depositosProveedorByToken($token_cat_proveedores,$fechaInicio,$fechaFin);
        $prov_result_retiros = $this->retirosProveedorByToken($token_cat_proveedores,$fechaInicio,$fechaFin);
        $prov_result_saldo = ($saldo_inicial_prov + $prov_result_depositos) - $prov_result_retiros;

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "total_movimientos" => count($arrayMovimientos),
          "mov_moneda" => $codeMoneda,
          "mov_moneda_decimales" => $decimalesMoneda,
          "movimientos_saldo_inicial" => "$".number_format($saldo_inicial_prov,$decimalesMoneda,'.', ','),
          "movimientos_deposito" => "$".number_format($prov_result_depositos,$decimalesMoneda,'.', ','),
          "movimientos_retiro" => "$".number_format($prov_result_retiros,$decimalesMoneda,'.', ','),
          "saldo_final" => "$".number_format($prov_result_saldo,$decimalesMoneda,'.', ','),
          "movimientos" => $arrayMovimientos,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}

<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\NominasModelo;
use App\Models\OrdenPagoModelo;
use App\Services\FirebaseService;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VHUM_NominasController extends Controller{
  public function reportesNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nominas = array();
        
        $queryRepNomina = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "emp.id")
        ->whereIn('vhum_nominas_main.id', function ($query) {
          $query->select('nomina_main')->from('vhum_nominas_recibos');
        })
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('vhum_nominas_main.id', 'DESC')->get();

        foreach ($queryRepNomina as $vNomina) {
          date_default_timezone_set('UTC');
          $totales_nomina_reporte_efectivo = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_efectivo');

          $moneda_nomina_recibos = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->value('nrec.nomina_moneda');

          $totales_nomina_pago_efectivo = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

					$totales_nomina_saldo_efectivo = $totales_nomina_reporte_efectivo - $totales_nomina_pago_efectivo;

          $queryNominaEfectOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_efectivo_ord_pago_token = $queryNominaEfectOrdPago ? $queryNominaEfectOrdPago->token_ordenPago :'';
					$nomina_efectivo_ord_pago_folio = $queryNominaEfectOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEfectOrdPago->folio_ordenPago) :'';

          $totales_nomina_reporte_especie = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_en_especie');

          $totales_nomina_pago_especie = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

          $totales_nomina_saldo_especie = $totales_nomina_reporte_especie - $totales_nomina_pago_especie;

          $queryNominaEspeOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_especie_ord_pago_token = $queryNominaEspeOrdPago ? $queryNominaEspeOrdPago->token_ordenPago :'';
					$nomina_especie_ord_pago_folio = $queryNominaEspeOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEspeOrdPago->folio_ordenPago) :'';

          $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->count();

          $nominas[] = array(
            'token_nominas_periodos' => $vNomina->token_nominas_periodos,
            'folio_interior' => 'NOM-EF-'.$JwtAuth->generarFolio($vNomina->nomina_folio_interior).(!is_null($vNomina->nomina_subfolio) ? '-'.$vNomina->nomina_subfolio : ''),
            'nomina_numero' => $vNomina->nomina_numero,
            //'nomina_moneda' => $vNomina->nomina_moneda,
            'nomina_fecha_contabilizacion' => date('d-m-Y',$vNomina->nomina_fecha_contabilizacion),
            //'centrotrab_uuid' => $vNomina->centrotrab_uuid,
            //'centrotrab_registro_patronal_imss' => $vNomina->centrotrab_clave_registro_patronal_imss,
            //'nomina_periodo_pago' => date('d/m/Y',$vNomina->nomina_periodo_fecha_inicio)." - ".date('d/m/Y',$vNomina->nomina_periodo_fecha_fin),
            //'nomina_periodicidad' => $vNomina->nomina_periodicidad,
            //efectivo
            'nomina_reporte_efectivo' => "$".number_format($totales_nomina_reporte_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_pago_efectivo' => "$".number_format($totales_nomina_pago_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_saldo_efectivo' => "$".number_format($totales_nomina_saldo_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_efectivo_ord_pago_token' => $nomina_efectivo_ord_pago_token,
            'nomina_efectivo_ord_pago_folio' => $nomina_efectivo_ord_pago_folio,
            //especie
            'nomina_reporte_especie' => "$".number_format($totales_nomina_reporte_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_pago_especie' => "$".number_format($totales_nomina_pago_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_saldo_especie' => "$".number_format($totales_nomina_saldo_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_especie_ord_pago_token' => $nomina_especie_ord_pago_token,
            'nomina_especie_ord_pago_folio' => $nomina_especie_ord_pago_folio,
            'vinculacion_a_pagos' => $queryNominaPago > 0 ? true : false
          );
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nominas' => $nominas
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaEfectivoSeguimientoOrdenPago(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string',
        'nomina_efectivo_ord_pago_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$token_nominas_periodos = $parametrosArray['token_nominas_periodos'];
				$nomina_efectivo_ord_pago_token = $parametrosArray['nomina_efectivo_ord_pago_token'];
        $orden_pago_nomina = array();
        $pagos_realizados_nomina = array();
        
        $queryNominaOrdenPago = NominasModelo::join("fnzs_pagos_orden AS order", "vhum_nominas_main.id", "=", "order.nomina_main")
        ->join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
        ->join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereIn('vhum_nominas_main.id', function ($query) {
          $query->select('nomina_main')->from('vhum_nominas_recibos');
        })
        ->whereNull("order.nomina_en_especie")
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
          'order.token_ordenPago' => $nomina_efectivo_ord_pago_token,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        //echo count($queryNominaPagos);
				foreach ($queryNominaOrdenPago as $rOrdPag) {
					date_default_timezone_set($rOrdPag->zona_horaria);
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? date('d-m-Y H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

					$orden_emisor_emp = $rOrdPag->abrev_nombre;

          $importe_total_anticipo = 0;
					$importe_total_inicial = 0;
					$orden_moneda_inicial_name = $rOrdPag->nomina_moneda;
					$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomina_moneda);

					$importe_autorizado_inicial = 0;
					$orden_moneda_autorizado_inicial_tkn = $rOrdPag->nomina_moneda;
					$orden_moneda_autorizado_inicial_name = $rOrdPag->nomina_moneda;
					$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomina_moneda);

					$importe_autorizado_final = 0;
					$orden_moneda_autorizado_final_name = $rOrdPag->nomina_moneda;
					$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomina_moneda);
          
          $importe_concepto_simple = floatval(DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$rOrdPag->token_nominas_periodos)
          ->sum('nrec.total_efectivo'));
          $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
          $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
          $importe_autorizado_final = $importe_autorizado_final + $importe_concepto_simple;

					//pagos_realizados
          $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? date('d-m-Y', $rOrdPag->orden_terminada_fecha) : "---";
          $pagos_realizados = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])
          ->sum('vinc.orden_pago_monto');

          $pagos_realizados_orden = $JwtAuth->pagosDoneBYOrden($rOrdPag->token_ordenPago);

					$pago_restante = count($pagos_realizados_orden) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

          $lpr = $pagos_realizados_orden;
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
            "id" => 1,
            "token_ordenPago" => $rOrdPag->token_ordenPago,
            "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
            "fecha_contabilizacion_doc_anterior" => date('d-m-Y',$rOrdPag->nomina_fecha_contabilizacion),
            "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? date('d-m-Y',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
            "fecha_registro" => date('d-m-Y H:i:s', $rOrdPag->fecha_sistema_ordenp),
            "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
            "autorizacion_pay" => $autorizacion_pay,
            "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
            "autorizacion_pay_text" => "",
            "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
            "factura_relacionada_typo" => "nominas",
            "factura_relacionada_token" => $rOrdPag->token_nominas_periodos,
            "factura_relacionada_string" => 'NOM-EF-'.$JwtAuth->generarFolio($rOrdPag->nomina_folio_interior).(!is_null($rOrdPag->nomina_subfolio) ? '-'.$rOrdPag->nomina_subfolio : ''),
            "orden_emisor_emp" => $orden_emisor_emp,

            "importe_total_inicial_simple" => $importe_total_inicial,
            "orden_moneda_inicial_name" => $orden_moneda_inicial_name,
            "importe_total_inicial" => $JwtAuth->muestraCantidadesConMoneda($importe_total_inicial,$orden_moneda_inicial_name,$orden_moneda_inicial_decimales),
            "importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
            "orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
            "orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
            "importe_autorizado_inicial_format" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_inicial,$orden_moneda_autorizado_inicial_name,$orden_moneda_autorizado_inicial_decimales),
            //$orden_moneda_inicial_decimales = 0;
            "importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
            "importe_autorizado_final" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_final,$orden_moneda_autorizado_final_name,$orden_moneda_autorizado_final_decimales),
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
            //"pagos_realizados_orden" => $pagos_realizados_orden,
            "pago_realizado_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['token_pagos'] : '',
            "pago_realizado_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['folio_pagos'] : '',
            "pago_realizado_status" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['status_pago'] : '',
            "pago_realizado_folio_operacion" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['folio_operacion'] : '',
            "pago_realizado_fecha_pago" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_pago'] : '',
            "pago_realizado_fecha_contabilizacion" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_contabilizacion'] : '',
            "pago_realizado_monto" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['monto_pago'] : '',
            "pago_realizado_observaciones" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['observacionesPago'] : '',
            "pago_realizado_tipo_cambio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['tipo_cambio'] : '',
            "pago_realizado_moneda" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['p_moneda'] : '',
            "pago_realizado_destino" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['destino'] : '',
            "pago_realizado_concepto" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['concepto'] : '',
            //forma_pago
            "pago_realizado_forma_pago_vinculada" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['forma_pago_vinculada'] : '',
            "pago_realizado_forma_pago_cfdi" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['forma_pago_cfdi'] : '',
            "pago_realizado_metodo_pago_cfdi" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['metodo_pago_cfdi'] : '',
            "pago_realizado_forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
            //proveedor
            "pago_realizado_proveedor_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['proveedor_token'] : '',
            "pago_realizado_proveedor_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['proveedor_name'] : '',
            //cliente
            "pago_realizado_cliente_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['cliente_token'] : '',
            "pago_realizado_cliente_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['cliente_name'] : '',
            //empleado
            "pago_realizado_empleado_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['empleado_token'] : '',
            "pago_realizado_empleado_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['empleado_name'] : '',
            //acreedor
            "pago_realizado_acreedor_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['acreedor_token'] : '',
            "pago_realizado_acreedor_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['acreedor_name'] : '',
            //personal_pago
            "pago_realizado_personal_pago_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_token'] : '',
            "pago_realizado_personal_pago_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_folio'] : '',
            "pago_realizado_personal_pago_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_name'] : '',
            "pago_realizado_pago_autorizado" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['pago_autorizado'] : '',
            "pago_realizado_fecha_pago_auth" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_pago_auth'] : '',
            //personal_autoriza
            "pago_realizado_personal_autoriza_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_token'] : '',
            "pago_realizado_personal_autoriza_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_folio'] : '',
            "pago_realizado_personal_autoriza_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_name'] : '',
          );
          $orden_pago_nomina[] = $row_ordenPay;
				}

				$pagos_realizados_nomina = $JwtAuth->pagosDoneBYOrdenDesglose($nomina_efectivo_ord_pago_token,$usuario->empresa_token,$usuario->user_token);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'seguimiento_orden_pago' => $orden_pago_nomina,
          'pagos_realizados' => $pagos_realizados_nomina,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaEspecieSeguimientoOrdenPago(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nominas = array();
        
        $queryRepNomina = NominasModelo::join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_nominas_main.nomina_registro_patronal", "c_trab.id")
        ->join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "emp.id")
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('vhum_nominas_main.id', 'DESC')->get();

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nominas' => $nominas
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaDesgloseDispersion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$token_nominas_periodos = $parametrosArray['token_nominas_periodos'];
        $detalleNominaLista = array();
        
        $queryNominaDesglose = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        
        foreach ($queryNominaDesglose as $vNom) {
          $queryNominaEfectOrdPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
          
          $detalleNominaQuery = DB::table("vhum_nominas_recibos AS recibos")
          ->join("vhum_empleados_catalogo AS nomi_trab", "recibos.trabajador", "=", "nomi_trab.id")
          ->join("sos_personas AS people", "nomi_trab.empleado_name", "=", "people.id")
          ->join("teci_bancos AS bank", "nomi_trab.trabcuentabanc_banco", "=", "bank.id")
          ->join("vhum_nominas_main AS nomi", "recibos.nomina_main", "=", "nomi.id")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "recibos.nomina_registro_patronal", "c_trab.id")
          ->where('nomi.token_nominas_periodos',$vNom->token_nominas_periodos)
          ->get();
          $contador = 1;
          foreach ($detalleNominaQuery as $vNomRec) {
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vNomRec->folio_pers).(!is_null($vNomRec->post_folio_pers) ? '-'.$vNomRec->post_folio_pers : '');
            $trabajador_name_paterno = ucwords($JwtAuth->desencriptar($vNomRec->paterno));
            $trabajador_name_materno = ucwords($JwtAuth->desencriptar($vNomRec->materno));
            $trabajador_name_nombre = ucwords($JwtAuth->desencriptar($vNomRec->nombre));
            $trabajador_nombre = "$trabajador_name_paterno $trabajador_name_materno $trabajador_name_nombre";
            $numero_de_seguridad_social = !is_null($vNomRec->numero_de_seguridad_social) && $vNomRec->numero_de_seguridad_social != '' ? $vNomRec->numero_de_seguridad_social : '';
            $rfc = !is_null($vNomRec->rfc) && $vNomRec->rfc != '' ? $JwtAuth->desencriptar($vNomRec->rfc) : '';
            $curp = !is_null($vNomRec->curp) && $vNomRec->curp != '' ? $JwtAuth->desencriptar($vNomRec->curp) : '';
            $fecha_alta_en_empresa = !is_null($vNomRec->fecha_alta_en_empresa) && $vNomRec->fecha_alta_en_empresa != '' ? date('d-m-Y', $vNomRec->fecha_alta_en_empresa) : '';
            $salario_tipo = !is_null($vNomRec->salario_tipo) && $vNomRec->salario_tipo != '' ? $vNomRec->salario_tipo : '';

            $cuenta_descifrada = '';
            $cuenta_descifrada_last_digitos = '';
            if (!is_null($vNomRec->trabcuentabanc_cuenta) && $vNomRec->trabcuentabanc_cuenta != '') {
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_cuenta);
              $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_cuenta), -4);
              $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
            }
            
            $clabe_descifrada = '';
            $clabe_descifrada_last_digitos = '';
            if (!is_null($vNomRec->trabcuentabanc_clabe) && $vNomRec->trabcuentabanc_clabe != '') {
              $clabe_descifrada = $JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_clabe);
              $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_clabe), -4);
              $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
            }

            $nomina_moneda_name = $vNomRec->nomina_moneda;
            $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vNomRec->nomina_moneda);

            $detNomRow = array(
              "nomina_clave" => $contador,
              "token_nomina_recibo" => $vNomRec->token_nomina_recibo,
              //nomina_registro_patronal
              "centrotrab_uuid" => $vNomRec->centrotrab_uuid,
              "centrotrab_registro_patronal_imss" => $vNomRec->centrotrab_clave_registro_patronal_imss,
              
              //nomina_empleado_nombre
              "nomina_empleado_token" => $vNomRec->empleado_token,
              "nomina_empleado_folio" => $folio_empleado,
              "nomina_empleado_nombre" => $trabajador_nombre,
              //nomina_periodicidad
              "nomina_periodicidad" => $vNomRec->nomina_periodicidad,
              //nomina_periodo_inicio
              "nomina_periodo_pago" => date('d/m/Y',$vNomRec->nomina_periodo_fecha_inicio)." - ".date('d/m/Y',$vNomRec->nomina_periodo_fecha_fin),
              "nomina_periodo_fecha_inicio" => date('Y-m-d',$vNomRec->nomina_periodo_fecha_inicio),
              "nomina_periodo_fecha_fin" => date('Y-m-d',$vNomRec->nomina_periodo_fecha_fin),
              //nomina_moneda
              "nomina_moneda" => $vNomRec->nomina_moneda,
              //nomina_empleado_cbankBanco
              "nomina_empleado_cbankBancoToken" => $vNomRec->token_bancos,
              "nomina_empleado_cbankBancoNombre" => $vNomRec->clave." ".$vNomRec->nombre_comercial,
              "nomina_empleado_cbankCuenta" => $cuenta_descifrada,
              "nomina_empleado_cbankCuentaMin" => $cuenta_descifrada_last_digitos,
              "cuenta_view" => false,
              "nomina_empleado_cbankCuentaClabeInter" => $clabe_descifrada,
              "nomina_empleado_cbankCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
              "clabe_inter_view" => false,
              //nomina_empleado_nss
              "nomina_empleado_nss" => $numero_de_seguridad_social,
              //nomina_empleado_rfc
              "nomina_empleado_rfc" => $rfc,
              //nomina_empleado_curp
              "nomina_empleado_curp" => $curp,
              //nomina_empleado_fecha_alta
              "nomina_empleado_fecha_alta" => $fecha_alta_en_empresa,
              //nomina_empleado_departamento
              "nomina_empleado_departamento" => !is_null($vNomRec->departamento) ? $JwtAuth->desencriptar($vNomRec->departamento) : '',
              //nomina_empleado_puesto
              "nomina_empleado_puesto" => !is_null($vNomRec->puesto) ? $JwtAuth->desencriptar($vNomRec->puesto) : '',
              //nomina_empleado_tipo_salario
              "nomina_empleado_tipo_salario" => $salario_tipo,
              //nomina_salario_diario
              "nomina_salario_diario" => number_format($vNomRec->salario_diario,$nomina_moneda_decimales,'.',''),
              "nomina_salario_diario_format" => "$".number_format($vNomRec->salario_diario,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_salario_integrado
              "nomina_salario_integrado" => number_format($vNomRec->salario_integrado,$nomina_moneda_decimales,'.',''),
              "nomina_salario_integrado_format" => "$".number_format($vNomRec->salario_integrado,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_dias_trabajados
              "nomina_dias_trabajados" => $vNomRec->dias_trabajados,
              //nomina_faltas
              "nomina_faltas" => intval($vNomRec->faltas),
              //nomina_sueldo
              "nomina_sueldo" => number_format($vNomRec->sueldo,$nomina_moneda_decimales,'.',''),
              "nomina_sueldo_format" => "$".number_format($vNomRec->sueldo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_horas_extras_dobles
              "nomina_horas_extras_dobles" => number_format($vNomRec->horas_extras_dobles,$nomina_moneda_decimales,'.',''),
              "nomina_horas_extras_dobles_format" => "$".number_format($vNomRec->horas_extras_dobles,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_aguinaldo
              "nomina_aguinaldo" => number_format($vNomRec->aguinaldo,$nomina_moneda_decimales,'.',''),
              "nomina_aguinaldo_format" => "$".number_format($vNomRec->aguinaldo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_horas_extras_triples
              "nomina_horas_extras_triples" => number_format($vNomRec->horas_extras_triples,$nomina_moneda_decimales,'.',''),
              "nomina_horas_extras_triples_format" => "$".number_format($vNomRec->horas_extras_triples,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_vacaciones
              "nomina_vacaciones" => number_format($vNomRec->vacaciones,$nomina_moneda_decimales,'.',''),
              "nomina_vacaciones_format" => "$".number_format($vNomRec->vacaciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_prima_vacacional
              "nomina_prima_vacacional" => number_format($vNomRec->prima_vacacional,$nomina_moneda_decimales,'.',''),
              "nomina_prima_vacacional_format" => "$".number_format($vNomRec->prima_vacacional,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_reparto_de_utilidades
              "nomina_reparto_de_utilidades" => number_format($vNomRec->reparto_de_utilidades,$nomina_moneda_decimales,'.',''),
              "nomina_reparto_de_utilidades_format" => "$".number_format($vNomRec->reparto_de_utilidades,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_despensa
              "nomina_despensa" => number_format($vNomRec->despensa,$nomina_moneda_decimales,'.',''),
              "nomina_despensa_format" => "$".number_format($vNomRec->despensa,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_premios_de_asistencia
              "nomina_premios_de_asistencia" => number_format($vNomRec->premios_de_asistencia,$nomina_moneda_decimales,'.',''),
              "nomina_premios_de_asistencia_format" => "$".number_format($vNomRec->premios_de_asistencia,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_premios_de_puntualidad
              "nomina_premios_de_puntualidad" => number_format($vNomRec->premios_de_puntualidad,$nomina_moneda_decimales,'.',''),
              "nomina_premios_de_puntualidad_format" => "$".number_format($vNomRec->premios_de_puntualidad,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_prima_dominical
              "nomina_prima_dominical" => number_format($vNomRec->prima_dominical,$nomina_moneda_decimales,'.',''),
              "nomina_prima_dominical_format" => "$".number_format($vNomRec->prima_dominical,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_bno_extra_x_comision_otro_edo
              "nomina_bno_extra_x_comision_otro_edo" => number_format($vNomRec->bno_extra_x_comision_otro_edo,$nomina_moneda_decimales,'.',''),
              "nomina_bno_extra_x_comision_otro_edo_format" => "$".number_format($vNomRec->bno_extra_x_comision_otro_edo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_indemnizacion
              "nomina_indemnizacion" => number_format($vNomRec->indemnizacion,$nomina_moneda_decimales,'.',''),
              "nomina_indemnizacion_format" => "$".number_format($vNomRec->indemnizacion,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_prima_de_antiguedad
              "nomina_prima_de_antiguedad" => number_format($vNomRec->prima_de_antiguedad,$nomina_moneda_decimales,'.',''),
              "nomina_prima_de_antiguedad_format" => "$".number_format($vNomRec->prima_de_antiguedad,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_otras_percepciones
              "nomina_otras_percepciones" => number_format($vNomRec->otras_percepciones,$nomina_moneda_decimales,'.',''),
              "nomina_otras_percepciones_format" => "$".number_format($vNomRec->otras_percepciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_otros_pagos
              "nomina_otros_pagos" => number_format($vNomRec->otros_pagos,$nomina_moneda_decimales,'.',''),
              "nomina_otros_pagos_format" => "$".number_format($vNomRec->otros_pagos,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_percepciones
              "nomina_total_percepciones" => number_format($vNomRec->total_percepciones,$nomina_moneda_decimales,'.',''),
              "nomina_total_percepciones_format" => "$".number_format($vNomRec->total_percepciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_isr_ajustado_por_subsidio
              "nomina_isr_ajustado_por_subsidio" => number_format($vNomRec->isr_ajustado_por_subsidio,$nomina_moneda_decimales,'.',''),
              "nomina_isr_ajustado_por_subsidio_format" => "$".number_format($vNomRec->isr_ajustado_por_subsidio,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_isr
              "nomina_total_isr" => number_format($vNomRec->total_isr,$nomina_moneda_decimales,'.',''),
              "nomina_total_isr_format" => "$".number_format($vNomRec->total_isr,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_imss
              "nomina_total_imss" => number_format($vNomRec->total_imss,$nomina_moneda_decimales,'.',''),
              "nomina_total_imss_format" => "$".number_format($vNomRec->total_imss,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_credito_fonacot
              "nomina_credito_fonacot" => number_format($vNomRec->credito_fonacot,$nomina_moneda_decimales,'.',''),
              "nomina_credito_fonacot_format" => "$".number_format($vNomRec->credito_fonacot,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_credito_infonavit
              "nomina_credito_infonavit" => number_format($vNomRec->credito_infonavit,$nomina_moneda_decimales,'.',''),
              "nomina_credito_infonavit_format" => "$".number_format($vNomRec->credito_infonavit,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_subsidio_empleo
              "nomina_subsidio_empleo" => number_format($vNomRec->subsidio_empleo,$nomina_moneda_decimales,'.',''),
              "nomina_subsidio_empleo_format" => "$".number_format($vNomRec->subsidio_empleo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_subsidio_empleo_aplicado
              //"nomina_subsidio_empleo_aplicado" => number_format($vNomRec->subsidio_para_el_empleo_aplicado,$nomina_moneda_decimales,'.',''),
              //"nomina_subsidio_empleo_aplicado_format" => "$".number_format($vNomRec->subsidio_para_el_empleo_aplicado,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_otras_deducciones
              "nomina_otras_deducciones" => number_format($vNomRec->otras_deducciones,$nomina_moneda_decimales,'.',''),
              "nomina_otras_deducciones_format" => "$".number_format($vNomRec->otras_deducciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_deducciones
              "nomina_total_deducciones" => number_format($vNomRec->total_deducciones,$nomina_moneda_decimales,'.',''),
              "nomina_total_deducciones_format" => "$".number_format($vNomRec->total_deducciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_efectivo
              "nomina_total_efectivo" => number_format($vNomRec->total_efectivo,$nomina_moneda_decimales,'.',''),
              "nomina_total_efectivo_format" => "$".number_format($vNomRec->total_efectivo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_en_especie
              "nomina_total_en_especie" => number_format($vNomRec->total_en_especie,$nomina_moneda_decimales,'.',''),
              "nomina_total_en_especie_format" => "$".number_format($vNomRec->total_en_especie,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_neto_pagado
              "nomina_neto_pagado" => number_format($vNomRec->neto_pagado,$nomina_moneda_decimales,'.',''),
              "nomina_neto_pagado_format" => "$".number_format($vNomRec->neto_pagado,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_horas_por_dia
              "nomina_horas_por_dia" => intval($vNomRec->horas_por_dia),
              //nomina_salario_por_hora
              "nomina_salario_por_hora" => number_format($vNomRec->salario_por_hora,$nomina_moneda_decimales,'.',''),
              "nomina_salario_por_hora_format" => "$".number_format($vNomRec->salario_por_hora,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_dias_jornada
              "nomina_dias_jornada" => !is_null($vNomRec->nomina_jornada) && $vNomRec->nomina_jornada != '' ? $vNomRec->nomina_jornada : '',
              "nomina_habilita_carga_docs" => $queryNominaEfectOrdPago ? true : false,
              "nomina_factura_doc_xml" => !is_null($vNomRec->xml_url) ? $JwtAuth->desencriptar($vNomRec->xml_url) : null,
              "nomina_factura_doc_pdf" => !is_null($vNomRec->pdf_url) ? $JwtAuth->desencriptar($vNomRec->pdf_url) : null,
              "nomina_factura_xml" => null,
              "nomina_factura_pdf" => null,
              "nomina_valida_xml" => '',
              "nomina_cfdi_comprobante" => [],
              "nomina_cfdi_emisor" => [],
              "nomina_cfdi_receptor" => [],
              "nomina_cfdi_conceptos" => [],
              "nomina_cfdi_complemento" => [],
              "nomina_cfdi_nomina" => []
            );
            $contador++;
            $detalleNominaLista[] = $detNomRow;
          }
				}

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'desglose' => $detalleNominaLista
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaCargaCFDIS(Request $request){
    $JwtAuth = new \JwtAuth();
		$user_token = $request->input('user_token');
		$token_nominas_periodos = $request->input('token_nominas_periodos');
		$nomina_reportada = $request->input('nomina_reportada');

    $validate = \Validator::make($request->all(), [
      'user_token' => 'required',
      'token_nominas_periodos' => 'required|string',
      'nomina_reportada' => 'required|array',
    ]);
    if ($validate->fails()) {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son incorrectos',
        'errors' => $validate->errors()
      );
    } else {
      $usuario = $JwtAuth->checkToken($user_token, true);
      
      $queryNominaDesglose = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'vhum_nominas_main.nomina_status' => TRUE,
        'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])->get();

      foreach ($queryNominaDesglose as $vNom) {
        $nomina_main = DB::table('vhum_nominas_main')->where("token_nominas_periodos", $vNom->token_nominas_periodos)->value("id");
        $folio_interior = 'NOM-EF-'.$JwtAuth->generarFolio($vNom->nomina_folio_interior).(!is_null($vNom->nomina_subfolio) ? '-'.$vNom->nomina_subfolio : '');
        $nomina_moneda_name = $vNom->nomina_moneda;
        $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vNom->nomina_moneda);
        $count_nomina_reportada = 0;
        
        foreach ($nomina_reportada as $r_nomina => $rNomi) {
          $nomina_recibo = DB::table('vhum_nominas_recibos')->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])->value("id");
          $folio_nomina_recibo = $JwtAuth->generarFolio(DB::table('vhum_nominas_recibos')->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])->value("nomina_recibo_folio"));
          $empleado_referenciado = DB::table('vhum_empleados_catalogo')->where("empleado_token", $rNomi["nomina_empleado_token"])->value("id");
          //$nomina_factura_xml = $rNomi["nomina_factura_xml"];
          //$nomina_factura_pdf = $rNomi["nomina_factura_pdf"];

          $archivo_xml = $request->file("nomina_reportada.$r_nomina.nomina_factura_xml");
          $archivo_pdf = $request->file("nomina_reportada.$r_nomina.nomina_factura_pdf");

          $nomina_cfdi_comprobante = $rNomi["nomina_cfdi_comprobante"];
          $nomina_cfdi_emisor = $rNomi["nomina_cfdi_emisor"];
          $nomina_cfdi_receptor = $rNomi["nomina_cfdi_receptor"];
          $nomina_cfdi_conceptos = $rNomi["nomina_cfdi_conceptos"];
          $nomina_cfdi_complemento = $rNomi["nomina_cfdi_complemento"];
          $nomina_cfdi_nomina = $rNomi["nomina_cfdi_nomina"];
          
          $cfdi_comprobante_version = '';
          $cfdi_comprobante_serie = '';
          $cfdi_comprobante_folio = '';
          $cfdi_comprobante_fecha = '';
          $cfdi_comprobante_forma_de_pago = '';
          $cfdi_comprobante_metodo_de_pago = '';
          $cfdi_comprobante_subtotal = '';
          $cfdi_comprobante_descuento = '';
          $cfdi_comprobante_moneda = '';
          $cfdi_comprobante_tipo_de_cambio = '';
          $cfdi_comprobante_total = '';
          $cfdi_comprobante_confirmacion = '';
          $cfdi_comprobante_tipo_de_comprobante = '';
          $cfdi_comprobante_lugar_de_expedicion = '';
          $cfdi_comprobante_no_de_certificado = '';
          $cfdi_comprobante_sello = '';
          $cfdi_comprobante_certificado = '';

          $cfdi_complementoUUID = '';
          $cfdi_complementoFechaTimbrado = '';
          $cfdi_complementoRfcProvCertif = '';
          $cfdi_complementoVersion = '';
          $cfdi_complementoNoCertificadoSAT = '';
          $cfdi_complementoSelloCFD = '';
          $cfdi_complementoSelloSAT = '';

          $data_comprobante = json_decode($nomina_cfdi_comprobante, true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($data_comprobante)) {

            foreach ($data_comprobante as $vComp) {
              $cfdi_comprobante_version = $vComp["Version"];
              $cfdi_comprobante_serie = $vComp["Serie"];
              $cfdi_comprobante_folio = $vComp["Folio"];
              $cfdi_comprobante_fecha = $vComp["Fecha"];
              $cfdi_comprobante_forma_de_pago = $vComp["FormaDePago"];
              $cfdi_comprobante_subtotal = $vComp["Subtotal"];
              $cfdi_comprobante_descuento = $vComp["Descuento"];
              $cfdi_comprobante_moneda = $vComp["Moneda"];
              $cfdi_comprobante_tipo_de_cambio = $vComp["TipoDeCambio"];
              $cfdi_comprobante_total = $vComp["Total"];
              $cfdi_comprobante_confirmacion = $vComp["Confirmacion"];
              $cfdi_comprobante_tipo_de_comprobante = $vComp["TipoDeComprobante"];
              $cfdi_comprobante_metodo_de_pago = $vComp["MetodoDePago"];
              $cfdi_comprobante_lugar_de_expedicion = $vComp["LugarDeExpedición"];
              $cfdi_comprobante_no_de_certificado = $vComp["NoDeCertificado"];
              $cfdi_comprobante_sello = $vComp["Sello"];
              $cfdi_comprobante_certificado = $vComp["Certificado"];
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
            $cfdi_emisor_rfc = '';
            $cfdi_emisor_nombre = '';
            $cfdi_emisor_regimen_fiscal = '';
            $data_emisor = json_decode($nomina_cfdi_emisor, true);
            foreach ($data_emisor as $CFDIe) {
              $cfdi_emisor_rfc = $CFDIe["EmisorRfc"];
              $cfdi_emisor_nombre = $CFDIe["EmisorNombre"];
              $cfdi_emisor_regimen_fiscal = $CFDIe["EmisorRegimenFiscal"];
            }

            $cfdi_receptor_rfc = '';
            $cfdi_receptor_uso_del_cfdi = '';
            $data_receptor = json_decode($nomina_cfdi_receptor, true);
            foreach ($data_receptor as $CFDIReceptor) {
              $cfdi_receptor_rfc = $CFDIReceptor["ReceptorRfc"];
              $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["ReceptorUsoCFDI"];
            }

            $data_complemento = json_decode($nomina_cfdi_complemento, true);
            foreach ($data_complemento as $vComplemento) {
              $cfdi_complementoUUID = $vComplemento["UUID"];
              $cfdi_complementoFechaTimbrado = $vComplemento["FechaTimbrado"];
              $cfdi_complementoRfcProvCertif = $vComplemento["RfcProvCertif"];
              $cfdi_complementoNoCertificadoSAT = $vComplemento["NoCertificadoSAT"];
              $cfdi_complementoSelloCFD = $vComplemento["SelloCFD"];
              $cfdi_complementoSelloSAT = $vComplemento["SelloSAT"];
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
            $comprobante_fiscal_reg = "";
            if ($cfdi_comprobante_version != '') {
              $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$nomina_main.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
              $insertCFDINomina = DB::table('cfdi_comprobantes_fiscales')
              ->insert(array(
                "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                "origen_proceso" => "nomina",
                "cfdi_comprobante_version" => $cfdi_comprobante_version,	
                "cfdi_comprobante_serie" => $cfdi_comprobante_serie,	
                "cfdi_comprobante_folio" => $cfdi_comprobante_folio,	
                "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,	
                "cfdi_comprobante_sello" => $cfdi_comprobante_sello,	
                "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,	
                "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,	
                "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,	
                "cfdi_comprobante_descuento" => $cfdi_comprobante_descuento,	
                "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,	
                "cfdi_comprobante_total" => $cfdi_comprobante_total,	
                "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,	
                "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,	
                "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,	
                "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,	
                "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
                
                "cfdi_emisor_rfc" => $cfdi_emisor_rfc,	
                "cfdi_emisor_nombre" => $cfdi_emisor_nombre,	
                "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
                
                "cfdi_receptor_rfc" => $cfdi_receptor_rfc,	
                "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,	
                //"cfdi_receptor_regimen_fiscal" => $select_reembolso_main,	
                //"cfdi_receptor_domicilio_fiscal" => $select_reembolso_main,	
                
                "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,	
                "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,	
                "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,	
                "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,	
                "cfdi_complementoUUID" => $cfdi_complementoUUID,	
                "cfdi_complementoVersion" => $cfdi_complementoVersion,	
                "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
              ));
              
              $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
              $insertCFDIVincNomina = DB::table('cfdi_vinculacion_nomina')//cfdi__estructura
              ->insert(array(
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                "nomina_main" => $nomina_main,	
                "nomina_recibo" => $nomina_recibo,	
                "empleado_referenciado" => $empleado_referenciado,
              ));

              $data_conceptos = json_decode($nomina_cfdi_conceptos, true);
              for ($lrdc = 0; $lrdc < count($data_conceptos); $lrdc++) {
                $uuid_cfdi_detalle = Str::uuid()->toString();
                $insertConceptCFDINominas = DB::table('cfdi_comprobantes_conceptos')
                ->insert(array(
                  "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  //"nomina_main" => $nomina_main,
                  //"nomina_recibo" => $nomina_recibo,
                  //"empleado_referenciado" => $empleado_referenciado,
                  "ClaveProdServ" => $data_conceptos[$lrdc]['ClaveProdServ'],
                  "Cantidad" => $data_conceptos[$lrdc]['Cantidad'],
                  "ClaveUnidad" => $data_conceptos[$lrdc]['ClaveUnidad'],
                  "Descripcion" => $data_conceptos[$lrdc]['Descripcion'],
                  "ValorUnitario" => $data_conceptos[$lrdc]['ValorUnitario'],
                  "Importe" => $data_conceptos[$lrdc]['Importe'],
                  "Descuento" => $data_conceptos[$lrdc]['Descuento'],
                  "ObjetoImp" => $data_conceptos[$lrdc]['ObjetoImp']
                ));
              }
            }
            //return response()->json(['status' => 'error','code' => 200,'message' => "FechaFinalPago"]);
            $data_cfdi_nomina = json_decode($nomina_cfdi_nomina, true);
            foreach ($data_cfdi_nomina as $CFDINomi) {
              //return response()->json(['status' => 'error','code' => 200,'message' => $CFDINomi["FechaFinalPago"]]);
              $cfdi_nominaEmisor = $CFDINomi["Emisor"]; 
              $cfdi_nominaReceptor = $CFDINomi["Receptor"]; 
              $cfdi_nominaPercepciones = $CFDINomi["Percepciones"];
              $cfdi_nominaDeducciones = $CFDINomi["Deducciones"]; 
              $cfdi_nominaOtrosPagos = $CFDINomi["OtrosPagos"];
              
              $uuid_nomina_nomina = Str::uuid()->toString();
              $insertNominaCFDINominas = DB::table('cfdi_nomina_nomina')
              ->insert(array(
                "uuid_nomina_nomina" => $uuid_nomina_nomina,
                "nomina_main" => $nomina_main,
                "nomina_recibo" => $nomina_recibo,
                "empleado_referenciado" => $empleado_referenciado,
                //"nomina_estructura" => $nomina_estructura,

                "FechaFinalPago" => $CFDINomi["FechaFinalPago"],
                "FechaInicialPago" => $CFDINomi["FechaInicialPago"],
                "FechaPago" => $CFDINomi["FechaPago"],
                "NumDiasPagados" => $CFDINomi["NumDiasPagados"],
                "TipoNomina" => $CFDINomi["TipoNomina"],
                "TotalDeducciones" => $CFDINomi["TotalDeducciones"],
                "TotalOtrosPagos" => $CFDINomi["TotalOtrosPagos"],
                "TotalPercepciones" => $CFDINomi["TotalPercepciones"],
                "Version" => $CFDINomi["Version"]
              ));

              foreach ($cfdi_nominaEmisor as $EmisorCFDINomi) {
                $uuid_nomina_emisor = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_emisor')
                ->insert(array(
                  "uuid_nomina_emisor" => $uuid_nomina_emisor,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,
  
                  "registro_patronal" => $EmisorCFDINomi["RegistroPatronal"]
                ));
              }

              foreach ($cfdi_nominaReceptor as $ReceptorCFDINomi) {
                $uuid_nomina_receptor = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_receptor')
                ->insert(array(
                  "uuid_nomina_receptor" => $uuid_nomina_receptor,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "Antigüedad" => $ReceptorCFDINomi["Antigüedad"],
                  "Banco" => $ReceptorCFDINomi["Banco"],
                  "ClaveEntFed" => $ReceptorCFDINomi["ClaveEntFed"],
                  "CuentaBancaria" => $ReceptorCFDINomi["CuentaBancaria"],
                  "Curp" => $ReceptorCFDINomi["Curp"],
                  "Departamento" => $ReceptorCFDINomi["Departamento"],
                  "FechaInicioRelLaboral" => $ReceptorCFDINomi["FechaInicioRelLaboral"],
                  "NumEmpleado" => $ReceptorCFDINomi["NumEmpleado"],
                  "NumSeguridadSocial" => $ReceptorCFDINomi["NumSeguridadSocial"],
                  "PeriodicidadPago" => $ReceptorCFDINomi["PeriodicidadPago"],
                  "Puesto" => $ReceptorCFDINomi["Puesto"],
                  "RiesgoPuesto" => $ReceptorCFDINomi["RiesgoPuesto"],
                  "SalarioBaseCotApor" => $ReceptorCFDINomi["SalarioBaseCotApor"],
                  "SalarioDiarioIntegrado" => $ReceptorCFDINomi["SalarioDiarioIntegrado"],
                  "Sindicalizado" => $ReceptorCFDINomi["Sindicalizado"],
                  "TipoContrato" => $ReceptorCFDINomi["TipoContrato"],
                  "TipoJornada" => $ReceptorCFDINomi["TipoJornada"],
                  "TipoRegimen" => $ReceptorCFDINomi["TipoRegimen"],
                ));
              }

              foreach ($cfdi_nominaPercepciones as $PercepcionesCFDINomi) {
                $nominaPercepcionesPercepcion = $PercepcionesCFDINomi["Percepcion"];
                $uuid_nomina_percepciones = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_percepciones')
                ->insert(array(
                  "uuid_nomina_percepciones" => $uuid_nomina_percepciones,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "TotalExento" => $PercepcionesCFDINomi["TotalExento"],
                  "TotalGravado" => $PercepcionesCFDINomi["TotalGravado"],
                  "TotalSueldos" => $PercepcionesCFDINomi["TotalSueldos"],
                ));
                
                foreach ($nominaPercepcionesPercepcion as $PercepcionNomi) {
                  $uuid_nomina_percepcion = Str::uuid()->toString();
                  DB::table('cfdi_nomina_nomina_percepciones_percepcion')
                  ->insert(array(
                    "uuid_nomina_percepcion" => $uuid_nomina_percepcion,
                    "nomina_main" => $nomina_main,
                    "nomina_recibo" => $nomina_recibo,
                    "empleado_referenciado" => $empleado_referenciado,
                    "nomina_estructura" => $comprobante_fiscal_reg,
                    "nomina_nomina" => $uuid_nomina_nomina,
                    "nomina_percepciones" => $uuid_nomina_percepciones,
  
                    "Clave" => $PercepcionNomi["Clave"],
                    "Concepto" => $PercepcionNomi["Concepto"],
                    "ImporteExento" => $PercepcionNomi["ImporteExento"],
                    "ImporteGravado" => $PercepcionNomi["ImporteGravado"],
                    "TipoPercepcion" => $PercepcionNomi["TipoPercepcion"],
                  ));
                }
              }

              foreach ($cfdi_nominaDeducciones as $DeduccionesCFDINomi) {
                $nominaDeduccionesDeduccion = $DeduccionesCFDINomi["Deduccion"];
                $uuid_nomina_deducciones = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_deducciones')
                ->insert(array(
                  "uuid_nomina_deducciones" => $uuid_nomina_deducciones,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "TotalImpuestosRetenidos" => $DeduccionesCFDINomi["TotalImpuestosRetenidos"],
                  "TotalOtrasDeducciones" => $DeduccionesCFDINomi["TotalOtrasDeducciones"],
                ));
                
                foreach ($nominaDeduccionesDeduccion as $DeduccionNomi) {
                  $uuid_nomina_deduccion = Str::uuid()->toString();
                  DB::table('cfdi_nomina_nomina_deducciones_deduccion')
                  ->insert(array(
                    "uuid_nomina_deduccion" => $uuid_nomina_deduccion,
                    "nomina_main" => $nomina_main,
                    "nomina_recibo" => $nomina_recibo,
                    "empleado_referenciado" => $empleado_referenciado,
                    "nomina_estructura" => $comprobante_fiscal_reg,
                    "nomina_nomina" => $uuid_nomina_nomina,
                    "nomina_deducciones" => $uuid_nomina_deducciones,
                    "Clave" => $DeduccionNomi["Clave"],
                    "Concepto" => $DeduccionNomi["Concepto"],
                    "Importe" => $DeduccionNomi["Importe"],
                    "TipoDeduccion" => $DeduccionNomi["TipoDeduccion"],
                  ));
                }
              }

              foreach ($cfdi_nominaOtrosPagos as $OtrosPagosCFDINomi) {
                $nominaOtrosPagosSubsidioAlEmpleo = $OtrosPagosCFDINomi["SubsidioAlEmpleo"];
                $uuid_nomina_otros_pagos = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_otros_pagos')
                ->insert(array(
                  "uuid_nomina_otros_pagos" => $uuid_nomina_otros_pagos,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "Clave" => $OtrosPagosCFDINomi["Clave"],
                  "Concepto" => $OtrosPagosCFDINomi["Concepto"],
                  "Importe" => $OtrosPagosCFDINomi["Importe"],
                  "TipoOtroPago" => $OtrosPagosCFDINomi["TipoOtroPago"],
                ));
                
                foreach ($nominaOtrosPagosSubsidioAlEmpleo as $SubsidioAlEmpleoNomi) {
                  $uuid_nomina_subsidio_empleo = Str::uuid()->toString();
                  DB::table('cfdi_nomina_nomina_otros_pagos_subsidio_empleo')
                  ->insert(array(
                    "uuid_nomina_subsidio_empleo" => $uuid_nomina_subsidio_empleo,
                    "nomina_main" => $nomina_main,
                    "nomina_recibo" => $nomina_recibo,
                    "empleado_referenciado" => $empleado_referenciado,
                    "nomina_estructura" => $comprobante_fiscal_reg,
                    "nomina_nomina" => $uuid_nomina_nomina,
                    "nomina_otros_pagos" => $uuid_nomina_otros_pagos,

                    "SubsidioCausado" => $SubsidioAlEmpleoNomi["SubsidioCausado"],
                  ));
                }
              }
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
          }
          $filepath = $vNom->root_tkn."/0004-vhm/nominas/$folio_interior/$folio_nomina_recibo";
          
          if ($archivo_xml) {
            $nombre_original = $archivo_xml->getClientOriginalName();
            $ext_doc = $archivo_xml->getClientOriginalExtension();

            $documento_crypt = $JwtAuth->encriptar($nombre_original);
            $token_documento = $JwtAuth->encriptarToken($nomina_recibo, $ext_doc, $nombre_original);

            $insertDocSoli = DB::table("sos_documentos")->insert([
              "token_documento" => $token_documento,
              "fecha_carga" => time(),
              "modulo" => "reembolsos",
              "folio_modulo" => "NOMINA-CFDI-XML",
              "tipo_documento" => "xml",
              "nombre_documento" => $documento_crypt,
              "extension_documento" => $ext_doc,
              "nomina_main" => $nomina_main,
              "nomina_recibo" => $nomina_recibo,
              "status_documento" => true,
            ]);

            if ($insertDocSoli) {
              DB::table('vhum_nominas_recibos')
              ->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])
              ->limit(1)->update(array(
                "folio_fiscal" => $cfdi_complementoUUID,
                "serie_folio" => "$cfdi_comprobante_serie - $cfdi_comprobante_folio",
                "xml_url" => $documento_crypt,
                "fecha_emision" => $cfdi_comprobante_fecha,
                "fecha_timbrado" => $cfdi_complementoFechaTimbrado
              ));

              $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
            }
          }

          if ($archivo_pdf) {
            $nombre_original = $archivo_pdf->getClientOriginalName();
            $ext_doc = $archivo_pdf->getClientOriginalExtension();

            $documento_crypt = $JwtAuth->encriptar($nombre_original);
            $token_documento = $JwtAuth->encriptarToken($nomina_recibo, $ext_doc, $nombre_original);

            $insertDocSoli = DB::table("sos_documentos")->insert([
              "token_documento" => $token_documento,
              "fecha_carga" => time(),
              "modulo" => "reembolsos",
              "folio_modulo" => "NOMINA-CFDI-PDF",
              "tipo_documento" => "pdf",
              "nombre_documento" => $documento_crypt,
              "extension_documento" => $ext_doc,
              "nomina_main" => $nomina_main,
              "nomina_recibo" => $nomina_recibo,
              "status_documento" => true,
            ]);

            if ($insertDocSoli) {
              $regFolder = DB::table('vhum_nominas_recibos')
              ->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])
              ->limit(1)->update(array(
                "pdf_url" => $documento_crypt,
              ));
              $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
            }
          }
          ++$count_nomina_reportada;
          //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$token_nomina_recibo]);
        }

        if ($count_nomina_reportada == count($nomina_reportada)) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'CFDIs de la nómina han sido cargados correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error al cargar los CFDIs de la nómina, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'numero_de_nomina' => 'required|string',
        'fecha_contabilizacion' => 'required|string',
        'nomina_observacion' => 'required|string',
        'nomina_reportada' => 'required|array',
        'nomina_en_especie' => 'array',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $numero_de_nomina = $parametrosArray['numero_de_nomina'];
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $nomina_observacion = $parametrosArray['nomina_observacion'];
        $nomina_reportada = $parametrosArray['nomina_reportada'];
        $nomina_en_especie = $parametrosArray['nomina_en_especie'];

        $OKNominaNumero = isset($numero_de_nomina) && !empty($numero_de_nomina) && preg_match($JwtAuth->filtroNumerico(),$numero_de_nomina);
        $OKNominaFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $OKNominaObservacion = isset($nomina_observacion) && !empty($nomina_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_observacion);
        $OKNominaReportada = isset($nomina_reportada) && is_array($nomina_reportada) && count($nomina_reportada) > 0;
        $OKNominaEspecie = isset($nomina_en_especie) && is_array($nomina_en_especie) && count($nomina_en_especie) > 0;

        if ($OKNominaNumero  && $OKNominaFCont && $OKNominaObservacion && $OKNominaReportada) {
          $fechaSistema = time();
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT nomina.nomina_folio_interior+1 AS folio,nomina_subfolio FROM vhum_nominas_main AS nomina JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
              ORDER BY nomina.nomina_folio_interior DESC LIMIT 1",[$usuario->empresa_token,$usuario->user_token]);
            //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT nomina_subfolio FROM vhum_nominas_main WHERE id = (SELECT Max(nomina.id) FROM vhum_nominas_main AS nomina JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                  
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->nomina_subfolio);
                  $folio_nuevo = 1;
              } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
            $folio_nomina = 'NOM-EF-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
            $tokenMainNomina = $JwtAuth->encriptarToken($numero_de_nomina.$nomina_observacion.count($nomina_reportada));
            
            $creaMainNomina = new NominasModelo();
            $creaMainNomina->token_nominas_periodos = $tokenMainNomina;
            $creaMainNomina->nomina_folio_interior = $folio_nuevo;
            $creaMainNomina->nomina_subfolio = $post_folio;
            $creaMainNomina->nomina_numero = $numero_de_nomina;
            $creaMainNomina->nomina_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $creaMainNomina->nomina_observaciones = $JwtAuth->encriptar($nomina_observacion);
            $creaMainNomina->nomina_empresa = $vEmp->id;
            $savedNomnina = $creaMainNomina->save();
                        
            $nomina_id = $creaMainNomina->id;

            if ($OKNominaReportada) {
              foreach ($nomina_reportada as $e_nom_v => $e_nom_d) {
                $nomina_clave = $e_nom_d["nomina_clave"];
                $nomina_empleado_token = $e_nom_d["nomina_empleado_token"];
                $nomina_trabajador = DB::table("vhum_empleados_catalogo")->where("empleado_token",$nomina_empleado_token)->value("id");
                
                $nomina_registro_patronal = $e_nom_d['nomina_registro_patronal'];
                $OKRegistroPatronal = isset($nomina_registro_patronal) && !empty($nomina_registro_patronal) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_registro_patronal);
                $imss_registro_patronal = $OKRegistroPatronal ? DB::table("vhum_centros_de_trabajo_catalogo")->where('centrotrab_clave_registro_patronal_imss',$nomina_registro_patronal)->value('id') : NULL;
                
                $nomina_periodicidad = $e_nom_d['nomina_periodicidad'];
                $OKPeriodicidad = isset($nomina_periodicidad) && !empty($nomina_periodicidad) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_periodicidad);
                
                $nomina_periodo_inicio = $e_nom_d['nomina_periodo_inicio'];
                $OKPeriodoInicio = isset($nomina_periodo_inicio) && !empty($nomina_periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$nomina_periodo_inicio);
                
                $nomina_periodo_fin = $e_nom_d['nomina_periodo_fin'];
                $OKPeriodoFin = isset($nomina_periodo_fin) && !empty($nomina_periodo_fin) && preg_match($JwtAuth->filtroFecha(),$nomina_periodo_fin);
                
                $nomina_moneda = $e_nom_d['nomina_moneda'];
                $OKMoneda = isset($nomina_moneda) && !empty($nomina_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_moneda);
                
                $nomina_empleado_nombre = $e_nom_d["nomina_empleado_nombre"];
                $nomina_dias_trabajados = $e_nom_d["nomina_dias_trabajados"];
                $nomina_sueldo = $e_nom_d["nomina_sueldo"];
                $nomina_otras_percepciones = $e_nom_d["nomina_otras_percepciones"];
                $nomina_otros_pagos = $e_nom_d["nomina_otros_pagos"];
                $nomina_total_percepciones = $e_nom_d["nomina_total_percepciones"];
                $nomina_neto_pagado = $e_nom_d["nomina_neto_pagado"];
                $nomina_total_en_especie = $e_nom_d["nomina_total_en_especie"];
                $nomina_salario_por_hora = $e_nom_d["nomina_salario_por_hora"];
                $nomina_total_imss = $e_nom_d["nomina_total_imss"];
                $nomina_horas_por_dia = $e_nom_d["nomina_horas_por_dia"];
                $nomina_total_isr = $e_nom_d["nomina_total_isr"];
                $nomina_subsidio_empleo = $e_nom_d["nomina_subsidio_empleo"];
                $nomina_otras_deducciones = $e_nom_d["nomina_otras_deducciones"];
                $nomina_total_deducciones = $e_nom_d["nomina_total_deducciones"];
                $nomina_total_efectivo = $e_nom_d["nomina_total_efectivo"];
                $nomina_salario_diario = $e_nom_d["nomina_salario_diario"];
                $nomina_salario_integrado = $e_nom_d["nomina_salario_integrado"];
                $nomina_faltas = $e_nom_d["nomina_faltas"];
                $horas_extras_dobles = $e_nom_d["nomina_horas_extras_dobles"];
                $aguinaldo = $e_nom_d["nomina_aguinaldo"];
                $horas_extras_triples = $e_nom_d["nomina_horas_extras_triples"];
                $vacaciones = $e_nom_d["nomina_vacaciones"];
                $prima_vacacional = $e_nom_d["nomina_prima_vacacional"];
                $reparto_de_utilidades = $e_nom_d["nomina_reparto_de_utilidades"];
                $despensa = $e_nom_d["nomina_despensa"];
                $premios_de_asistencia = $e_nom_d["nomina_premios_de_asistencia"];
                $premios_de_puntualidad = $e_nom_d["nomina_premios_de_puntualidad"];
                $prima_dominical = $e_nom_d["nomina_prima_dominical"];
                $bno_extra_x_comision_otro_edo = $e_nom_d["nomina_bno_extra_x_comision_otro_edo"];
                $indemnizacion = $e_nom_d["nomina_indemnizacion"];
                $prima_de_antiguedad = $e_nom_d["nomina_prima_de_antiguedad"];
                $isr_ajustado_por_subsidio = $e_nom_d["nomina_isr_ajustado_por_subsidio"];
                $credito_fonacot = $e_nom_d["nomina_credito_fonacot"];
                $credito_infonavit = $e_nom_d["nomina_credito_infonavit"];
                //$subsidio_para_el_empleo_aplicado = $e_nom_d["nomina_subsidio_empleo_aplicado"];
                
                $tokenNominaRecibo = $JwtAuth->encriptarToken($nomina_clave.$nomina_id.$nomina_trabajador.$nomina_empleado_nombre.$nomina_dias_trabajados.$nomina_sueldo.$nomina_otras_percepciones.$nomina_total_percepciones.$nomina_neto_pagado);

                DB::table("vhum_nominas_recibos")
                ->insert(array(
                  "token_nomina_recibo" => $tokenNominaRecibo,
                  "nomina_recibo_folio" => $nomina_clave,
                  "trabajador" => $nomina_trabajador,
                  "nomina_main" => $nomina_id,

                  "nomina_registro_patronal" => $imss_registro_patronal,
                  "nomina_periodo_fecha_inicio" => $OKPeriodoInicio ? $JwtAuth->convierteFechaEpoc($nomina_periodo_inicio) : NULL,
                  "nomina_periodo_fecha_fin" => $OKPeriodoFin ? $JwtAuth->convierteFechaEpoc($nomina_periodo_fin) : NULL,
                  "nomina_periodicidad" => $OKPeriodicidad ? $nomina_periodicidad : NULL,
                  "nomina_moneda" => $OKMoneda ? $nomina_moneda : NULL,

                  "dias_trabajados" => $nomina_dias_trabajados,
                  "sueldo" => $nomina_sueldo,
                  "otras_percepciones" => $nomina_otras_percepciones,
                  "otros_pagos" => $nomina_otros_pagos,
                  "total_percepciones" => $nomina_total_percepciones,
                  "neto_pagado" => $nomina_neto_pagado,
                  "total_en_especie" => $nomina_total_en_especie,
                  "salario_por_hora" => $nomina_salario_por_hora,
                  "total_imss" => $nomina_total_imss,
                  "horas_por_dia" => $nomina_horas_por_dia,
                  "total_isr" => $nomina_total_isr,
                  "subsidio_empleo" => $nomina_subsidio_empleo,
                  "otras_deducciones" => $nomina_otras_deducciones,
                  "total_deducciones" => $nomina_total_deducciones,
                  "total_efectivo" => $nomina_total_efectivo,
                  "salario_diario" => $nomina_salario_diario,
                  "salario_integrado" => $nomina_salario_integrado,
                  
                  "horas_extras_dobles" => $horas_extras_dobles,
                  "aguinaldo" => $aguinaldo,
                  "horas_extras_triples" => $horas_extras_triples,
                  "vacaciones" => $vacaciones,
                  "prima_vacacional" => $prima_vacacional,
                  "reparto_de_utilidades" => $reparto_de_utilidades,
                  "despensa" => $despensa,
                  "premios_de_asistencia" => $premios_de_asistencia,
                  "premios_de_puntualidad" => $premios_de_puntualidad,
                  "prima_dominical" => $prima_dominical,
                  "bno_extra_x_comision_otro_edo" => $bno_extra_x_comision_otro_edo,
                  "indemnizacion" => $indemnizacion,
                  "prima_de_antiguedad" => $prima_de_antiguedad,
                  "isr_ajustado_por_subsidio" => $isr_ajustado_por_subsidio,
                  "credito_fonacot" => $credito_fonacot,
                  "credito_infonavit" => $credito_infonavit,
                  //"subsidio_para_el_empleo_aplicado" => $subsidio_para_el_empleo_aplicado,
                  "faltas" => $nomina_faltas,
                ));
              }
            }
            
            //ALTER TABLE `fnzs_pagos_orden` ADD `nomina_main` INT(10) NULL AFTER `reembolso_solicitud`;
            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$nomina_id);
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = $fechaSistema;
            $orderpay->nomina_main = $nomina_id;
            $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $orderpay->orden_bloqueada = FALSE;
            $orderpay->autorizacion_pay = FALSE;
            $orderpay->fecha_autorizacion_pay = NULL;
            $orderpay->tentativa_pago = NULL;
            $orderpay->orden_terminada_bool = FALSE;
            $orderpay->orden_terminada_fecha = NULL;
            $orderpay->status_ordenPago = TRUE;
            $orderpay->empresa = $vEmp->id;
            $orderpay->comprador = $vEmp->userr;
            $insertOrder = $orderpay->save();

            if ($OKNominaEspecie) {
              $folioEspecie = DB::select("SELECT espnom.nomina_esp_folio_interior+1 AS folio,nomina_esp_subfolio FROM vhum_nominas_especie AS espnom JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN teci_usuarios_catalogo AS users WHERE espnom.nomina_esp_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
                ORDER BY espnom.nomina_esp_folio_interior DESC LIMIT 1",[$usuario->empresa_token,$usuario->user_token]);
              //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
              if (count($folioEspecie) == 1) {
                if ($folioEspecie[0]->folio == 1000000000) {
                    $post_folio_esp = DB::select("SELECT nomina_esp_subfolio FROM vhum_nominas_especie WHERE id = (SELECT Max(espnom.id) FROM vhum_nominas_especie AS espnom JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE espnom.nomina_esp_empresa = emp.id AND emp.empresa_token = ?
                      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                    
                    $esp_post_folio = $JwtAuth->generarPostFolio($post_folio_esp[0]->nomina_esp_subfolio);
                    $esp_folio_nuevo = 1;
                } else {
                    $esp_post_folio = NULL;
                    $esp_folio_nuevo = $folioEspecie[0]->folio;
                }
              } else {
                $esp_post_folio = NULL;
                $esp_folio_nuevo = 1;
              }

              $tokenEspecieNomina = $JwtAuth->encriptarToken($nomina_id.$numero_de_nomina.$fechaSistema.count($nomina_en_especie));
              
              DB::table("vhum_nominas_especie")
              ->insert(array(
                "token_nominas_especie" => $tokenEspecieNomina,
                "nomina_esp_folio_interior" => $esp_folio_nuevo,
                "nomina_esp_subfolio" => $esp_post_folio,
                "nomina_esp_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                "nomina_main" => $nomina_id,
                "nomina_esp_empresa" => $vEmp->id
              ));
              $id_nomina_en_especie = DB::table("vhum_nominas_especie")->where('token_nominas_especie',$tokenEspecieNomina)->value('id');

              foreach ($nomina_en_especie as $especie_v => $especie_d) {
                $espnom_empleado_token = $especie_d["nomina_empleado_token"];
                $espnom_trabajador = DB::table("vhum_empleados_catalogo")->where("empleado_token",$espnom_empleado_token)->value("id");
                $espnom_total_especie = $especie_d["nomina_total_en_especie"];
                $espnom_moneda = $especie_d['nomina_moneda'];
                
                $tokenEspNomDesg = $JwtAuth->encriptarToken($espnom_trabajador.$id_nomina_en_especie.$espnom_total_especie);

                DB::table("vhum_nominas_especie_desglose")
                ->insert(array(
                  "token_especie_desglose" => $tokenEspNomDesg,
                  "trabajador" => $espnom_trabajador,
                  "periodo_nomina" => $nomina_id,
                  "nomina_especie" => $id_nomina_en_especie,
                  "total_en_especie" => $espnom_total_especie,
                  "nomina_esp_moneda" => $espnom_moneda,
                ));
              }

              $folioEspOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

              $espOrdenPago = $JwtAuth->encriptarToken(time(),$folioEspOrden[0]->folio,$id_nomina_en_especie);
              $ordEspPay = new OrdenPagoModelo();
              $ordEspPay->token_ordenPago = $espOrdenPago;
              $ordEspPay->folio_ordenPago = $folioEspOrden[0]->folio; //falta generar
              $ordEspPay->fecha_sistema_ordenp = $fechaSistema;
              $ordEspPay->nomina_main = $nomina_id;
              $ordEspPay->nomina_en_especie = $id_nomina_en_especie;
              $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
              $ordEspPay->orden_bloqueada = FALSE;
              $ordEspPay->autorizacion_pay = FALSE;
              $ordEspPay->fecha_autorizacion_pay = NULL;
              $ordEspPay->tentativa_pago = NULL;
              $ordEspPay->orden_terminada_bool = FALSE;
              $ordEspPay->orden_terminada_fecha = NULL;
              $ordEspPay->status_ordenPago = TRUE;  //cifrado
              $ordEspPay->empresa = $vEmp->id; //cifrado
              $ordEspPay->comprador = $vEmp->userr; //cifrado
              $insertOrder = $ordEspPay->save();
            }
            
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Nomina registrada satisfactoriamente con el folio $folio_nomina"
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKNominaNumero) $mensaje_error = "Error al registrar número de nómina, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaFCont) $mensaje_error = "Error en fecha de contabilización de nómina, verifique su información";
          if (!$OKNominaObservacion) $mensaje_error = "Error al registrar observaciones de nómina, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaReportada) $mensaje_error = "Error al registrar lista de nóminas, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_nominas_periodos = $parametrosArray['token_nominas_periodos'];

        $OKNominaPeriodo = isset($token_nominas_periodos) && !empty($token_nominas_periodos);

        if ($OKNominaPeriodo) {
          $queryNominaMain = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'vhum_nominas_main.nomina_status' => TRUE,
            'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
          
          foreach ($queryNominaMain as $vNom) {
            $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
            ->whereNull("order.nomina_en_especie")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();
            
            if (count($queryNominaPago) == 0) {
              $queryDeleteNomina = DB::table("vhum_nominas_main")
              ->where("token_nominas_periodos",$vNom->token_nominas_periodos)
              ->limit(1)->update(array(
                "nomina_status" => FALSE,
                "nomina_fecha_delete" => time()
              ));

              if ($queryDeleteNomina) {
                $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Esta nómina ha sido eliminada satisfactoriamente');
              } else {
                $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
              }
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede eliminar, se encuentra vinculada a pagos realizados, intentelo nuevamente o comuniquese a soporte');
            }
            
            
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar nómina, intentelo nuevamente o comuniquese a soporte');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function reportesDeletedNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nominas = array();
        
        $queryRepNomina = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "emp.id")
        ->whereIn('vhum_nominas_main.id', function ($query) {
          $query->select('nomina_main')->from('vhum_nominas_recibos');
        })
        ->where([
          'vhum_nominas_main.nomina_status' => FALSE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('vhum_nominas_main.id', 'DESC')->get();

        foreach ($queryRepNomina as $vNomina) {
          date_default_timezone_set('UTC');
          $totales_nomina_reporte_efectivo = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_efectivo');

          $moneda_nomina_recibos = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->value('nrec.nomina_moneda');

          $totales_nomina_pago_efectivo = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

					$totales_nomina_saldo_efectivo = $totales_nomina_reporte_efectivo - $totales_nomina_pago_efectivo;

          $queryNominaEfectOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_efectivo_ord_pago_token = $queryNominaEfectOrdPago ? $queryNominaEfectOrdPago->token_ordenPago :'';
					$nomina_efectivo_ord_pago_folio = $queryNominaEfectOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEfectOrdPago->folio_ordenPago) :'';

          $totales_nomina_reporte_especie = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_en_especie');

          $totales_nomina_pago_especie = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

          $totales_nomina_saldo_especie = $totales_nomina_reporte_especie - $totales_nomina_pago_especie;

          $queryNominaEspeOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_especie_ord_pago_token = $queryNominaEspeOrdPago ? $queryNominaEspeOrdPago->token_ordenPago :'';
					$nomina_especie_ord_pago_folio = $queryNominaEspeOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEspeOrdPago->folio_ordenPago) :'';

          $nominas[] = array(
            'token_nominas_periodos' => $vNomina->token_nominas_periodos,
            'folio_interior' => 'NOM-EF-'.$JwtAuth->generarFolio($vNomina->nomina_folio_interior).(!is_null($vNomina->nomina_subfolio) ? '-'.$vNomina->nomina_subfolio : ''),
            'nomina_numero' => $vNomina->nomina_numero,
            'nomina_fecha_contabilizacion' => date('d-m-Y',$vNomina->nomina_fecha_contabilizacion),
            'nomina_fecha_delete' => date('d-m-Y',$vNomina->nomina_fecha_delete)
          );
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nominas' => $nominas
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restauraNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_nominas_periodos = $parametrosArray['token_nominas_periodos'];

        $OKNominaPeriodo = isset($token_nominas_periodos) && !empty($token_nominas_periodos);

        if ($OKNominaPeriodo) {
          $queryNominaMain = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'vhum_nominas_main.nomina_status' => FALSE,
            'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
          
          foreach ($queryNominaMain as $vNom) {
            $queryDeleteNomina = DB::table("vhum_nominas_main")
            ->where("token_nominas_periodos",$vNom->token_nominas_periodos)
            ->limit(1)->update(array(
              "nomina_status" => TRUE,
              "nomina_fecha_delete" => NULL
            ));

            if ($queryDeleteNomina) {
              $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Esta nómina ha sido restaurada satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede restaurar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar nómina, intentelo nuevamente o comuniquese a soporte');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPermanenteNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_nominas_periodos = $parametrosArray['token_nominas_periodos'];

        $OKNominaPeriodo = isset($token_nominas_periodos) && !empty($token_nominas_periodos);

        if ($OKNominaPeriodo) {
          $queryNominaMain = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'vhum_nominas_main.nomina_status' => FALSE,
            'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
          
          foreach ($queryNominaMain as $vNom) {
            $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
            ->whereNull("order.nomina_en_especie")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();
            
            if (count($queryNominaPago) == 0) {
              $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
              ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
              ->whereNull("order.nomina_en_especie")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->limit(1)->delete();
            }
            
            $queryNominaEspeciePago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
            ->join("vhum_nominas_especie AS n_espe", "order.nomina_en_especie", "=", "n_espe.id")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();
            
            if (count($queryNominaEspeciePago) > 0) {
              $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
              ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
              ->join("vhum_nominas_especie AS n_espe", "order.nomina_en_especie", "=", "n_espe.id")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->limit(1)->delete();
            }

            $queryNominaEspecie = DB::table("vhum_nominas_especie AS n_espe")
            ->join("vhum_nominas_main AS nmain", "n_espe.nomina_main", "=", "nmain.id")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();

            if (count($queryNominaEspecie) > 0) {
              $queryNominaEspecieDesglose = DB::table("vhum_nominas_especie_desglose AS desglo")
              ->join("vhum_nominas_especie AS n_espe", "desglo.nomina_especie", "=", "n_espe.id")
              ->join("vhum_nominas_main AS nmain", "n_espe.nomina_main", "=", "nmain.id")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->get();

              foreach ($queryNominaEspecieDesglose as $dDesg) {
                $queryNominaEspecieDesglose = DB::table("vhum_nominas_especie_desglose")
                ->where('token_especie_desglose',$dDesg->token_especie_desglose)
                ->limit(1)->delete();
              }

              $queryNominaEspDesglosar = DB::table("vhum_nominas_especie AS n_espe")
              ->join("vhum_nominas_main AS nmain", "n_espe.nomina_main", "=", "nmain.id")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->limit(1)->delete();
            }
            
            $queryNominaEspecieRecibos = DB::table("vhum_nominas_recibos AS recibos")
            ->join("vhum_nominas_main AS nmain", "recibos.nomina_main", "=", "nmain.id")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();

            foreach ($queryNominaEspecieRecibos as $dnRec) {
              $queryNominaEspecieDesglose = DB::table("vhum_nominas_recibos")
              ->where('token_nomina_recibo',$dnRec->token_nomina_recibo)
              ->limit(1)->delete();
            }

            $queryDeleteNomina = DB::table("vhum_nominas_main")
            ->where("token_nominas_periodos",$vNom->token_nominas_periodos)
            ->limit(1)->delete();

            if ($queryDeleteNomina) {
              $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Esta nómina ha sido eliminada satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
            }
            
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar nómina, intentelo nuevamente o comuniquese a soporte');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fecha_contabilizacion' => 'required|string', 
        'fecha_vencimiento' => 'required|string',
        'fecha_presentacion' => 'required|string',
        'estado' => 'required|string', 
        'ejercicio' => 'required|numeric',
        'periodo_inicio' => 'required|string',
        'periodo_fin' => 'required|string',
        //'fecha_pago' => 'required|string',
        'tipo_declaracion' => 'required|string',
        'total_remuneraciones_erogadas' => 'required|numeric', 
        'porcent_sobre_total_remuneraciones_erogadas' => 'required|numeric', 
        'complementarias_impuesto_a_cargo' => 'required|numeric', 
        'complementarias_saldo_a_favor' => 'required|numeric', 
        'impuesto_actualizado' => 'required|numeric', 
        'impuesto_descuento' => 'required|string', 
        'impuesto_recargos' => 'required|numeric', 
        'impuesto_recargos_condonados' => 'required|numeric', 
        'subsi_n_resolu_impuesto_pagar' => 'required|numeric', 
        'subsi_n_resolu_recargos' => 'required|numeric', 
        'compensa_n_resolucion' => 'required|numeric', 
        'compensa_n_resolu_recargos' => 'required|numeric', 
        'impuesto_total_a_pagar' => 'required|numeric', 
        'impuesto_saldo_a_favor' => 'required|numeric',
        'observaciones' => 'required|string', 
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fecha_vencimiento = $parametrosArray['fecha_vencimiento'];
        $fecha_presentacion = $parametrosArray['fecha_presentacion'];
        $estado = $parametrosArray['estado'];
        $ejercicio = $parametrosArray['ejercicio'];
        $periodo_inicio = $parametrosArray['periodo_inicio'];
        $periodo_fin = $parametrosArray['periodo_fin'];
        //$fecha_pago = $parametrosArray['fecha_pago'];
        $tipo_declaracion = $parametrosArray['tipo_declaracion'];
        $total_remuneraciones_erogadas = $parametrosArray['total_remuneraciones_erogadas'];
        $porcen_sobre_total_remun_erog = $parametrosArray['porcent_sobre_total_remuneraciones_erogadas'];
        $complementarias_impuesto_a_cargo = $parametrosArray['complementarias_impuesto_a_cargo'];
        $complementarias_saldo_a_favor = $parametrosArray['complementarias_saldo_a_favor'];
        $impuesto_actualizado = $parametrosArray['impuesto_actualizado'];
        $impuesto_descuento = $parametrosArray['impuesto_descuento'];
        $impuesto_recargos = $parametrosArray['impuesto_recargos'];
        $impuesto_recargos_condonados = $parametrosArray['impuesto_recargos_condonados'];
        $subsi_n_resolu_impuesto_pagar = $parametrosArray['subsi_n_resolu_impuesto_pagar'];
        $subsi_n_resolu_recargos = $parametrosArray['subsi_n_resolu_recargos'];
        $compensa_n_resolucion = $parametrosArray['compensa_n_resolucion'];
        $compensa_n_resolu_recargos = $parametrosArray['compensa_n_resolu_recargos'];
        $impuesto_total_a_pagar = $parametrosArray['impuesto_total_a_pagar'];
        $impuesto_saldo_a_favor = $parametrosArray['impuesto_saldo_a_favor'];
        $observaciones = $parametrosArray['observaciones'];

        $OKNominaFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $OKNominaFechaVencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
        $OKNominaFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
        $OKNominaEstado = isset($estado) && !empty($estado);
        $OKNominaEjercicio = isset($ejercicio) && !empty($ejercicio) && preg_match($JwtAuth->filtroNumerico(),$ejercicio);
        $OKNominaPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
        $OKNominaPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
        $OKNominaPeriodo = $OKNominaPeriodoInicio && $OKNominaPeriodoFin && ($JwtAuth->convierteFechaEpoc($periodo_fin) >= $JwtAuth->convierteFechaEpoc($periodo_inicio));
        $OKNominaTipoDeclaracion = isset($tipo_declaracion) && !empty($tipo_declaracion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_declaracion);
        
        $OKNominaTotalRemuneracionesErogadas = isset($total_remuneraciones_erogadas) && is_numeric($total_remuneraciones_erogadas) && preg_match($JwtAuth->filtroCostoPrecio(),$total_remuneraciones_erogadas);
        $OKNominaPorcenSobreTotalRemunErogad = isset($porcen_sobre_total_remun_erog) && is_numeric($porcen_sobre_total_remun_erog) && preg_match($JwtAuth->filtroCostoPrecio(),$porcen_sobre_total_remun_erog);
        $OKNominaComplementariasImpuestoACargo = isset($complementarias_impuesto_a_cargo) && is_numeric($complementarias_impuesto_a_cargo) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_impuesto_a_cargo);
        $OKNominaComplementariasSaldoAFavor = isset($complementarias_saldo_a_favor) && is_numeric($complementarias_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_saldo_a_favor);
        $OKNominaImpuestoActualizado = isset($impuesto_actualizado) && is_numeric($impuesto_actualizado) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_actualizado);
        $OKNominaImpuestoDescuento = isset($impuesto_descuento) && is_numeric($impuesto_descuento) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_descuento);
        $OKNominaImpuestoRecargos = isset($impuesto_recargos) && is_numeric($impuesto_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos);
        $OKNominaImpuestoRecargosCondonados = isset($impuesto_recargos_condonados) && is_numeric($impuesto_recargos_condonados) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos_condonados);
        $OKNominaSubsiNResoluImpuestoPagar = isset($subsi_n_resolu_impuesto_pagar) && is_numeric($subsi_n_resolu_impuesto_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_impuesto_pagar);
        $OKNominaSubsiNResoluRecargos = isset($subsi_n_resolu_recargos) && is_numeric($subsi_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_recargos);
        $OKNominaimporteCompensaNResolucion = isset($compensa_n_resolucion) && is_numeric($compensa_n_resolucion) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolucion);
        $OKNominaimporteCompensaNResolucionRecargos = isset($compensa_n_resolu_recargos) && is_numeric($compensa_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolu_recargos);
        $OKNominaImpuestoTotalAPagar = isset($impuesto_total_a_pagar) && is_numeric($impuesto_total_a_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_total_a_pagar);
        $OKNominaImpuestoSaldoAFavor = isset($impuesto_saldo_a_favor) && is_numeric($impuesto_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_saldo_a_favor);
        $OKNominaObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

        if ($OKNominaFCont && $OKNominaFechaVencimiento && $OKNominaFechaPresentacion && $OKNominaEstado && $OKNominaEjercicio && $OKNominaPeriodo && $OKNominaTipoDeclaracion && $OKNominaTotalRemuneracionesErogadas && $OKNominaPorcenSobreTotalRemunErogad && 
          $OKNominaComplementariasImpuestoACargo && $OKNominaComplementariasSaldoAFavor && $OKNominaImpuestoActualizado && $OKNominaImpuestoDescuento && $OKNominaImpuestoRecargos && $OKNominaImpuestoRecargosCondonados && 
          $OKNominaSubsiNResoluImpuestoPagar && $OKNominaSubsiNResoluRecargos && $OKNominaimporteCompensaNResolucion && $OKNominaimporteCompensaNResolucionRecargos && $OKNominaImpuestoTotalAPagar && 
          $OKNominaImpuestoSaldoAFavor && $OKNominaObservacion) {
          $fechaSistema = time();
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT nomina.nomi_imp_folio_interior+1 AS folio,nomi_imp_subfolio FROM vhum_nominas_impuestos AS nomina JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
              ORDER BY nomina.nomi_imp_folio_interior DESC LIMIT 1",[$usuario->empresa_token,$usuario->user_token]);
            //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT nomi_imp_subfolio FROM vhum_nominas_impuestos WHERE id = (SELECT Max(nomina.id) FROM vhum_nominas_impuestos AS nomina JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                  
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->nomi_imp_subfolio);
                  $folio_nuevo = 1;
              } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
            $folio_nomina = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
            $tokenImpuestosNomina = $JwtAuth->encriptarToken($ejercicio.$periodo_inicio.$periodo_fin.$observaciones.$impuesto_total_a_pagar);
            //vhum_nominas_impuestos
            DB::table("vhum_nominas_impuestos")
            ->insert(array(
              "nomi_imp_token" => $tokenImpuestosNomina,
              "nomi_imp_fecha_registro" => time(),
              "nomi_imp_folio_interior" => $folio_nuevo,
              "nomi_imp_subfolio" => $post_folio,
              "nomi_imp_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
              "nomi_imp_estado" => DB::table("fnzs_catalogos_fed_estados_municipios")->where("fed_est_mun_token", $estado)->value("id"),
              "nomi_imp_ejercicio" => $ejercicio,
              "nomi_imp_periodo_inicio" => $JwtAuth->convierteFechaEpoc($periodo_inicio),
              "nomi_imp_periodo_fin" => $JwtAuth->convierteFechaEpoc($periodo_fin),
              "nomi_imp_fecha_pago" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
              "nomi_imp_fecha_vencimiento" => $JwtAuth->convierteFechaEpoc($fecha_vencimiento),
              "nomi_imp_fecha_presentacion" => $JwtAuth->convierteFechaEpoc($fecha_presentacion),
              "nomi_imp_tipo_declaracion" => $tipo_declaracion,
              "nomi_imp_moneda" => "MXN",
              "nomi_imp_total_remuneraciones_erogadas" => $total_remuneraciones_erogadas,
              "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => $porcen_sobre_total_remun_erog,
              "nomi_imp_complementarias_impuesto_a_cargo" => $complementarias_impuesto_a_cargo,
              "nomi_imp_complementarias_saldo_a_favor" => $complementarias_saldo_a_favor,
              "nomi_imp_impuesto_actualizado" => $impuesto_actualizado,
              "nomi_imp_impuesto_descuento" => $impuesto_descuento,
              "nomi_imp_impuesto_recargos" => $impuesto_recargos,
              "nomi_imp_impuesto_recargos_condonados" => $impuesto_recargos_condonados,
              "nomi_imp_subsi_n_resolu_impuesto_pagar" => $subsi_n_resolu_impuesto_pagar,
              "nomi_imp_subsi_n_resolu_recargos" => $subsi_n_resolu_recargos,
              "nomi_imp_compensa_n_resolucion" => $compensa_n_resolucion,
              "nomi_imp_compensa_n_resolu_recargos" => $compensa_n_resolu_recargos,
              "nomi_imp_impuesto_total_a_pagar" => $impuesto_total_a_pagar,
              "nomi_imp_impuesto_saldo_a_favor" => $impuesto_saldo_a_favor,
              "observaciones" => $JwtAuth->encriptar($observaciones),
              "nomi_imp_status" => TRUE,
              //ALTER TABLE `vhum_nominas_impuestos` ADD `nomi_imp_status` BOOLEAN NOT NULL DEFAULT TRUE AFTER `observaciones`, ADD `nomi_imp_fecha_delete` VARCHAR(10) NULL AFTER `nomi_imp_status`;
              "nomina_empresa" => $vEmp->id,
            ));

            $nomina_id = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$tokenImpuestosNomina)->value("id");
            
            //ALTER TABLE `fnzs_pagos_orden` ADD `nomina_main` INT(10) NULL AFTER `reembolso_solicitud`;
            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$nomina_id);
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = $fechaSistema;
            $orderpay->impuesto_sobre_nomina = $nomina_id;
            $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $orderpay->orden_bloqueada = FALSE;
            $orderpay->autorizacion_pay = FALSE;
            $orderpay->fecha_autorizacion_pay = NULL;
            $orderpay->tentativa_pago = NULL;
            $orderpay->orden_terminada_bool = FALSE;
            $orderpay->orden_terminada_fecha = NULL;
            $orderpay->status_ordenPago = TRUE;
            $orderpay->empresa = $vEmp->id;
            $orderpay->comprador = $vEmp->userr;
            $insertOrder = $orderpay->save();

						$fecha_sistema_ordenp = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$tokenImpuestosNomina)->value("nomi_imp_fecha_registro");
            $filepath = $vEmp->root_tkn . "/0004-vhm/impuestos_sobre_nomina/$fecha_sistema_ordenp-$folio_nomina/anexos/";
            if (!empty($_FILES['documentos_evidencia'])) {
              $evidencias = $_FILES["documentos_evidencia"];
              //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
              $string_name_evid = json_encode($_FILES["documentos_evidencia"]["name"]);
              if (count(json_decode($string_name_evid)) != 0) {
                $evidencia_nombre = json_decode($string_name_evid);
                for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                  $temporal = $evidencias["tmp_name"][$doc];
                  $doc_name = $evidencias["name"][$doc];
                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%IMP-NOMI-EVID%'");
                  $token_documento = $JwtAuth->encriptarToken($nomina_id,$usuario->empresa_token,$usuario->user_token,$doc_name,$select_folio_doc[0]->folio);
                  $insertDocSoli = DB::table("sos_documentos")->insert(
                    array(
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "pagos",
                      "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($doc_name),
                      "impuesto_sobre_nomina" => $nomina_id,
                      "status_documento" => TRUE,
                    )
                  );
                }
              }
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Nomina registrada satisfactoriamente con el folio $folio_nomina"
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKNominaFCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaFechaVencimiento) $mensaje_error = "Error en fecha de vencimiento, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaFechaPresentacion) $mensaje_error = "Error en fecha de presentación, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaEstado) $mensaje_error = "Error al seleccionar estado, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaEjercicio) $mensaje_error = "Error al registrar ejercicio, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaPeriodo) $mensaje_error = "Error al registrar periodo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaTipoDeclaracion) $mensaje_error = "Error al registrar tipo de declaración, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaTotalRemuneracionesErogadas) $mensaje_error = "Error al registrar total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaPorcenSobreTotalRemunErogad) $mensaje_error = "Error al registrar % sobre el total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaComplementariasImpuestoACargo) $mensaje_error = "Error al registrar complementarias (Impuesto a cargo), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaComplementariasSaldoAFavor) $mensaje_error = "Error al registrar complementarias (Saldo a favor), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoActualizado) $mensaje_error = "Error al registrar impuesto actualizado, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoDescuento) $mensaje_error = "Error al registrar descuento, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoRecargos) $mensaje_error = "Error al registrar recargos, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoRecargosCondonados) $mensaje_error = "Error al registrar recargos condonados, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaSubsiNResoluImpuestoPagar) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaSubsiNResoluRecargos) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre recargos (%)), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaimporteCompensaNResolucion) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaimporteCompensaNResolucionRecargos) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre recargos), intentelo nuevamente o comuniquese a soporte";

          if (!$OKNominaImpuestoTotalAPagar) $mensaje_error = "Error al registrar total a pagar, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoSaldoAFavor) $mensaje_error = "Error al registrar saldo a favor, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaObservacion) $mensaje_error = "Error al registrar observaciones de nómina, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaRegNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $lista_imp_nomina = array();
        $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
        ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
        ->where([
          'nomImp.nomi_imp_status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('nomImp.id', 'DESC')->get();

        foreach ($queryImpNomina as $vImpNom) {
          $folio_nomina = $vImpNom->nomi_imp_folio_interior;
          $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
          $nomi_imp_moneda = $vImpNom->nomi_imp_moneda;
          $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($vImpNom->nomi_imp_moneda);
          $ejercicio = $vImpNom->nomi_imp_ejercicio;
          $periodo_inicio = $vImpNom->nomi_imp_periodo_inicio;
          $periodo_fin = $vImpNom->nomi_imp_periodo_fin;

          $queryImpEstado = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("fnzs_catalogos_fed_estados_municipios AS ent", "nomImp.nomi_imp_estado", "ent.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
          ->first();

          $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
          $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
          $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $queryIMPNominaPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();

          $totales_isn_pago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->sum('pay.monto_pago');

					$totales_isn_saldo = $vImpNom->nomi_imp_impuesto_total_a_pagar - $totales_isn_pago;

          $queryISNOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$isn_ord_pago_token = $queryISNOrdPago ? $queryISNOrdPago->token_ordenPago :'';
					$isn_ord_pago_folio = $queryISNOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryISNOrdPago->folio_ordenPago) :'';
          
          $row = array(
            "nomi_imp_token" => $vImpNom->nomi_imp_token,
            "nomi_imp_folio" => 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : ''),
            "nomi_imp_fecha_contabilizacion" => date('d-m-Y',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_estado_rfc" => $estado_rfc,
            "nomi_imp_estado_entidad" => $estado_entidad,
            "nomi_imp_estado_all_info" => $estado_all_info,
            "nomi_imp_ejercicio" => $ejercicio,
            "nomi_imp_periodo_inicio" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F')),
            "nomi_imp_periodo_fin" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F')),
            "nomi_imp_fecha_vencimiento" => date('d-m-Y',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_fecha_presentacion" => date('d-m-Y',$vImpNom->nomi_imp_fecha_presentacion),
            "nomi_imp_tipo_declaracion" => $vImpNom->nomi_imp_tipo_declaracion == 'comple' ? "complementaria" : "normal",
            "nomi_imp_moneda" => "MXN",
            "nomi_imp_impuesto_total_a_pagar" => "$".number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            'nomi_imp_pago' => "$".number_format($totales_isn_pago,$nomi_imp_moneda_decimales,'.', ',')." $nomi_imp_moneda",
            'nomi_imp_saldo' => "$".number_format($totales_isn_saldo,$nomi_imp_moneda_decimales,'.', ',')." $nomi_imp_moneda",
            'nomi_imp_ord_pago_token' => $isn_ord_pago_token,
            'nomi_imp_ord_pago_folio' => $isn_ord_pago_folio,
            "nomi_imp_habilita_carga_docs" => $queryIMPNominaPagoDone ? true : false,
            "nomi_imp_factura_doc_xml" => !is_null($vImpNom->nomi_imp_fact_xml) ? $JwtAuth->desencriptar($vImpNom->nomi_imp_fact_xml) : null,
            "nomi_imp_factura_doc_pdf" => !is_null($vImpNom->nomi_imp_fact_pdf) ? $JwtAuth->desencriptar($vImpNom->nomi_imp_fact_pdf) : null,
            "nomi_imp_factura_xml" => null,
            "nomi_imp_factura_pdf" => null,
            "nomi_imp_valida_xml" => '',
            "nomi_imp_cfdi_comprobante" => [],
            "nomi_imp_cfdi_emisor" => [],
            "nomi_imp_cfdi_receptor" => [],
            "nomi_imp_cfdi_conceptos" => [],
            "nomi_imp_cfdi_complemento" => [],
            "puede_eliminar" => !$queryIMPNominaPagoDone ? true : false,
          );
          $lista_imp_nomina[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'isn_lista' => $lista_imp_nomina
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaImpuestosSeguimientoOrdenPago(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required|string',
        'nomi_imp_ord_pago_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$nomi_imp_token = $parametrosArray['nomi_imp_token'];
				$nomi_imp_ord_pago_token = $parametrosArray['nomi_imp_ord_pago_token'];
        $orden_pago_isn = array();
        $pagos_realizados_isn = array();
        
        $queryISNOrdenPago = DB::table("vhum_nominas_impuestos AS nomImp")
        ->join("fnzs_pagos_orden AS order", "nomImp.id", "=", "order.impuesto_sobre_nomina")
        ->join("main_empresas AS emp", "nomImp.nomina_empresa", "=", "emp.id")
        ->join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'nomImp.nomi_imp_status' => TRUE,
          'nomImp.nomi_imp_token' => $nomi_imp_token,
          'order.token_ordenPago' => $nomi_imp_ord_pago_token,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        //echo count($queryNominaPagos);
				foreach ($queryISNOrdenPago as $rOrdPag) {
					date_default_timezone_set($rOrdPag->zona_horaria);
          $folio_nomina = $rOrdPag->nomi_imp_folio_interior;
          $post_folio_nomina = $rOrdPag->nomi_imp_subfolio;
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? date('d-m-Y H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

					$orden_emisor_emp = $rOrdPag->abrev_nombre;

          $importe_total_anticipo = 0;
					$importe_total_inicial = 0;
					$orden_moneda_inicial_name = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomi_imp_moneda);

					$importe_autorizado_inicial = 0;
					$orden_moneda_autorizado_inicial_tkn = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_autorizado_inicial_name = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomi_imp_moneda);

					$importe_autorizado_final = 0;
					$orden_moneda_autorizado_final_name = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomi_imp_moneda);
          
          $importe_total_inicial = $rOrdPag->nomi_imp_impuesto_total_a_pagar;
          $importe_autorizado_inicial = $rOrdPag->nomi_imp_impuesto_total_a_pagar;
          $importe_autorizado_final = $rOrdPag->nomi_imp_impuesto_total_a_pagar;

					//pagos_realizados
          $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? date('d-m-Y', $rOrdPag->orden_terminada_fecha) : "---";

          $pagos_realizados = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])
          ->sum('vinc.orden_pago_monto');

          $pagos_realizados_orden = $JwtAuth->pagosDoneBYOrden($rOrdPag->token_ordenPago);

					$pago_restante = count($pagos_realizados_orden) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

          $lpr = $pagos_realizados_orden;
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
            "id" => 1,
            "token_ordenPago" => $rOrdPag->token_ordenPago,
            "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
            "fecha_contabilizacion_doc_anterior" => date('d-m-Y',$rOrdPag->doc_anterior_fecha_contabilizacion),
            "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? date('d-m-Y',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
            "fecha_registro" => date('d-m-Y H:i:s', $rOrdPag->fecha_sistema_ordenp),
            "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
            "autorizacion_pay" => $autorizacion_pay,
            "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
            "autorizacion_pay_text" => "",
            "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
            "factura_relacionada_typo" => "nominas",
            "factura_relacionada_token" => $rOrdPag->nomi_imp_token,
            "factura_relacionada_string" => 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : ''),
            "orden_emisor_emp" => $orden_emisor_emp,

            "importe_total_inicial_simple" => $importe_total_inicial,
            "orden_moneda_inicial_name" => $orden_moneda_inicial_name,
            "importe_total_inicial" => $JwtAuth->muestraCantidadesConMoneda($importe_total_inicial,$orden_moneda_inicial_name,$orden_moneda_inicial_decimales),
            "importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
            "orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
            "orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
            "importe_autorizado_inicial_format" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_inicial,$orden_moneda_autorizado_inicial_name,$orden_moneda_autorizado_inicial_decimales),
            //$orden_moneda_inicial_decimales = 0;
            "importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
            "importe_autorizado_final" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_final,$orden_moneda_autorizado_final_name,$orden_moneda_autorizado_final_decimales),
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
            //"pagos_realizados_orden" => $pagos_realizados_orden,
            "pago_realizado_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['token_pagos'] : '',
            "pago_realizado_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['folio_pagos'] : '',
            "pago_realizado_status" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['status_pago'] : '',
            "pago_realizado_folio_operacion" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['folio_operacion'] : '',
            "pago_realizado_fecha_pago" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_pago'] : '',
            "pago_realizado_fecha_contabilizacion" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_contabilizacion'] : '',
            "pago_realizado_monto" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['monto_pago'] : '',
            "pago_realizado_observaciones" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['observacionesPago'] : '',
            "pago_realizado_tipo_cambio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['tipo_cambio'] : '',
            "pago_realizado_moneda" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['p_moneda'] : '',
            "pago_realizado_destino" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['destino'] : '',
            "pago_realizado_concepto" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['concepto'] : '',
            //forma_pago
            "pago_realizado_forma_pago_vinculada" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['forma_pago_vinculada'] : '',
            "pago_realizado_forma_pago_cfdi" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['forma_pago_cfdi'] : '',
            "pago_realizado_metodo_pago_cfdi" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['metodo_pago_cfdi'] : '',
            "pago_realizado_forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
            //proveedor
            "pago_realizado_proveedor_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['proveedor_token'] : '',
            "pago_realizado_proveedor_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['proveedor_name'] : '',
            //cliente
            "pago_realizado_cliente_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['cliente_token'] : '',
            "pago_realizado_cliente_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['cliente_name'] : '',
            //empleado
            "pago_realizado_empleado_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['empleado_token'] : '',
            "pago_realizado_empleado_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['empleado_name'] : '',
            //acreedor
            "pago_realizado_acreedor_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['acreedor_token'] : '',
            "pago_realizado_acreedor_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['acreedor_name'] : '',
            //personal_pago
            "pago_realizado_personal_pago_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_token'] : '',
            "pago_realizado_personal_pago_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_folio'] : '',
            "pago_realizado_personal_pago_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_name'] : '',
            "pago_realizado_pago_autorizado" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['pago_autorizado'] : '',
            "pago_realizado_fecha_pago_auth" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_pago_auth'] : '',
            //personal_autoriza
            "pago_realizado_personal_autoriza_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_token'] : '',
            "pago_realizado_personal_autoriza_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_folio'] : '',
            "pago_realizado_personal_autoriza_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_name'] : '',
          );
          $orden_pago_isn[] = $row_ordenPay;
				}

        $pagos_realizados_isn = $JwtAuth->pagosDoneBYOrdenDesglose($nomi_imp_ord_pago_token,$usuario->empresa_token,$usuario->user_token);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'seguimiento_orden_pago' => $orden_pago_isn,
          'pagos_realizados' => $pagos_realizados_isn,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desgloseNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nomi_imp_token = $parametrosArray['nomi_imp_token'];
        $lista_imp_nomina = array();
        $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
        ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
        ->where([
          'nomImp.nomi_imp_token' => $nomi_imp_token,
          'nomImp.nomi_imp_status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->get();

        foreach ($queryImpNomina as $vImpNom) {
          $folio_nomina = $vImpNom->nomi_imp_folio_interior;
          $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
          $nomi_imp_folio = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
          $nomi_imp_moneda = $vImpNom->nomi_imp_moneda;
          $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($vImpNom->nomi_imp_moneda);
          $ejercicio = $vImpNom->nomi_imp_ejercicio;
          $periodoCarbonI = ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F'));
          $periodoCarbonF = ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F'));
          
          $queryImpEstado = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("fnzs_catalogos_fed_estados_municipios AS ent", "nomImp.nomi_imp_estado", "ent.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('ent.fed_est_mun_token','ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
          ->first();

          $estado_token = $queryImpEstado ? $queryImpEstado->fed_est_mun_token : '';
          $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
          $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
          $estado_name = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $queryISNPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_impuestos AS isn", "order.impuesto_sobre_nomina", "=", "isn.id")
          ->where('isn.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->count();
          
          $isnAnexos = array();
          $queryDocsISN = DB::table("sos_documentos AS docs")
          ->join("vhum_nominas_impuestos AS isn", "docs.impuesto_sobre_nomina", "=", "isn.id")
          ->where([
            "docs.status_documento" => TRUE,
            "isn.nomi_imp_token" => $vImpNom->nomi_imp_token
          ])
          ->get();

          foreach ($queryDocsISN as $xDoc) {
            $nombre = $JwtAuth->desencriptar($xDoc->nombre_documento);
            $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

            $rowXML = array(
              "token_documento" => $xDoc->token_documento,
              "tipo_documental" => $xDoc->tipo_documento,
              "extension" => $extension,
              "name_documento" => $nombre,
              "url" => "https://downloads.sos-mexico.com.mx/impuestos_sobre_nomina/$nomi_imp_folio/$xDoc->token_documento",
              "eliminacion_proceso" => false
            );
            $isnAnexos[] = $rowXML;
          }

          $row = array(
            "nomi_imp_token" => $vImpNom->nomi_imp_token,
            "nomi_imp_folio" => $nomi_imp_folio,
            "nomi_imp_fecha_contabilizacion_edit" => date('Y-m-d',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_fecha_contabilizacion" => date('d-m-Y',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_estado_token" => $estado_token,
            "nomi_imp_estado_rfc" => $estado_rfc,
            "nomi_imp_estado_entidad" => $estado_entidad,
            "nomi_imp_estado_name" => $estado_name,

            "nomi_imp_ejercicio_simple" => $ejercicio,
            "nomi_imp_periodo" => $periodoCarbonI == $periodoCarbonF ? $periodoCarbonF : $periodoCarbonI." - ".$periodoCarbonF,

            "nomi_imp_periodo_inicio_edit" => date('Y-m-d',$vImpNom->nomi_imp_periodo_inicio),
            "nomi_imp_periodo_fin_edit" => date('Y-m-d',$vImpNom->nomi_imp_periodo_fin),
            "nomi_imp_fecha_vencimiento_edit" => date('Y-m-d',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_fecha_vencimiento" => date('d-m-Y',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_fecha_presentacion_edit" => date('Y-m-d',$vImpNom->nomi_imp_fecha_presentacion),
            "nomi_imp_fecha_presentacion" => date('d-m-Y',$vImpNom->nomi_imp_fecha_presentacion),
            "nomi_imp_tipo_declaracion" => $vImpNom->nomi_imp_tipo_declaracion == 'comple' ? "Complementaria" : "Normal",
            
            "nomi_imp_total_remuneraciones_erogadas" => number_format($vImpNom->nomi_imp_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => number_format($vImpNom->nomi_imp_porcent_sobre_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_complementarias_impuesto_a_cargo" => number_format($vImpNom->nomi_imp_complementarias_impuesto_a_cargo,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_complementarias_saldo_a_favor" => number_format($vImpNom->nomi_imp_complementarias_saldo_a_favor,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_actualizado" => number_format($vImpNom->nomi_imp_impuesto_actualizado,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_descuento" => number_format($vImpNom->nomi_imp_impuesto_descuento,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_recargos" => number_format($vImpNom->nomi_imp_impuesto_recargos,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_recargos_condonados" => number_format($vImpNom->nomi_imp_impuesto_recargos_condonados,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_subsi_n_resolu_impuesto_pagar" => number_format($vImpNom->nomi_imp_subsi_n_resolu_impuesto_pagar,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_subsi_n_resolu_recargos" => number_format($vImpNom->nomi_imp_subsi_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_compensa_n_resolucion" => number_format($vImpNom->nomi_imp_compensa_n_resolucion,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_compensa_n_resolu_recargos" => number_format($vImpNom->nomi_imp_compensa_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_total_a_pagar" => number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_saldo_a_favor" => number_format($vImpNom->nomi_imp_impuesto_saldo_a_favor,$nomi_imp_moneda_decimales,'.',''),
            
            "nomi_imp_total_remuneraciones_erogadas_format" => "$".number_format($vImpNom->nomi_imp_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_porcent_sobre_total_remuneraciones_erogadas_format" => "$".number_format($vImpNom->nomi_imp_porcent_sobre_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_complementarias_impuesto_a_cargo_format" => "$".number_format($vImpNom->nomi_imp_complementarias_impuesto_a_cargo,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_complementarias_saldo_a_favor_format" => "$".number_format($vImpNom->nomi_imp_complementarias_saldo_a_favor,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_actualizado_format" => "$".number_format($vImpNom->nomi_imp_impuesto_actualizado,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_descuento_format" => "$".number_format($vImpNom->nomi_imp_impuesto_descuento,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_recargos_format" => "$".number_format($vImpNom->nomi_imp_impuesto_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_recargos_condonados_format" => "$".number_format($vImpNom->nomi_imp_impuesto_recargos_condonados,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_subsi_n_resolu_impuesto_pagar_format" => "$".number_format($vImpNom->nomi_imp_subsi_n_resolu_impuesto_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_subsi_n_resolu_recargos_format" => "$".number_format($vImpNom->nomi_imp_subsi_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_compensa_n_resolucion_format" => "$".number_format($vImpNom->nomi_imp_compensa_n_resolucion,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_compensa_n_resolu_recargos_format" => "$".number_format($vImpNom->nomi_imp_compensa_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_total_a_pagar_format" => "$".number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_saldo_a_favor_format" => "$".number_format($vImpNom->nomi_imp_impuesto_saldo_a_favor,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            
            "observaciones" => $JwtAuth->desencriptar($vImpNom->observaciones),
            "isnAnexos" => $isnAnexos,
            'vinculacion_a_pagos' => $queryISNPago > 0 ? true : false
          );
          $lista_imp_nomina[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'isn_desglose' => $lista_imp_nomina
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaImpuestosCargaCFDIS(Request $request){
    $JwtAuth = new \JwtAuth();
		$user_token = $request->input('user_token');
		$nomi_imp_token = $request->input('nomi_imp_token');
		$isn = $request->input('isn');

    $validate = \Validator::make($request->all(), [
      'user_token' => 'required',
      'nomi_imp_token' => 'required|string',
      'isn' => 'required|array',
    ]);
    if ($validate->fails()) {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son incorrectos',
        'errors' => $validate->errors()
      );
    } else {
      $usuario = $JwtAuth->checkToken($user_token, true);
      
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'nomImp.nomi_imp_status' => TRUE,
        'emp.empresa_token' => $usuario->empresa_token,
      ])
      ->get();

      foreach ($queryImpNomina as $vIsn) {
        $isn_id = DB::table('vhum_nominas_impuestos')->where("nomi_imp_token", $vIsn->nomi_imp_token)->value("id");
        $folio_nomina = $vIsn->nomi_imp_folio_interior;
        $post_folio_nomina = $vIsn->nomi_imp_subfolio;
        $folio_interior = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
        $count_isn = 0;
        
        foreach ($isn as $r_nomina => $rNomi) {
          $archivo_xml = $request->file("isn.$r_nomina.nomi_imp_factura_xml");
          $archivo_pdf = $request->file("isn.$r_nomina.nomi_imp_factura_pdf");

          $nomi_imp_cfdi_comprobante = $rNomi["nomi_imp_cfdi_comprobante"];
          $nomi_imp_cfdi_emisor = $rNomi["nomi_imp_cfdi_emisor"];
          $nomi_imp_cfdi_receptor = $rNomi["nomi_imp_cfdi_receptor"];
          $nomi_imp_cfdi_conceptos = $rNomi["nomi_imp_cfdi_conceptos"];
          $nomi_imp_cfdi_complemento = $rNomi["nomi_imp_cfdi_complemento"];
          
          $cfdi_comprobante_fecha_contabilizacion = '';
          $cfdi_comprobante_version = '';
          $cfdi_comprobante_serie = '';
          $cfdi_comprobante_folio = '';
          $cfdi_comprobante_fecha = '';
          $cfdi_comprobante_sello = '';
          $cfdi_comprobante_forma_de_pago = '';
          $cfdi_comprobante_no_de_certificado = '';
          $cfdi_comprobante_certificado = '';
          $cfdi_comprobante_subtotal = '';
          $cfdi_comprobante_descuento = '';
          $cfdi_comprobante_moneda = '';
          $cfdi_comprobante_tipo_de_cambio = '';
          $cfdi_comprobante_total = '';
          $cfdi_comprobante_confirmacion = '';
          $cfdi_comprobante_tipo_de_comprobante = '';
          $cfdi_comprobante_metodo_de_pago = '';
          $cfdi_comprobante_lugar_de_expedicion = '';

          $cfdi_complementoVersion = '';
          $cfdi_complementoUUID = '';
          $cfdi_complementoFechaTimbrado = '';
          $cfdi_complementoRfcProvCertif = '';
          $cfdi_complementoNoCertificadoSAT = '';
          $cfdi_complementoSelloCFD = '';
          $cfdi_complementoSelloSAT = '';

          $data_comprobante = json_decode($nomi_imp_cfdi_comprobante, true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($data_comprobante)) {

            foreach ($data_comprobante as $vComp) {
              $cfdi_comprobante_fecha = $vComp["Fecha"];
              $cfdi_comprobante_version = $vComp["Version"];
              $cfdi_comprobante_serie = $vComp["Serie"];
              $cfdi_comprobante_folio = $vComp["Folio"];
              $cfdi_comprobante_fecha = $vComp["Fecha"];
              $cfdi_comprobante_sello = $vComp["Sello"];
              $cfdi_comprobante_forma_de_pago = $vComp["FormaDePago"];
              $cfdi_comprobante_no_de_certificado = $vComp["NoDeCertificado"];
              $cfdi_comprobante_certificado = $vComp["Certificado"];
              $cfdi_comprobante_subtotal = $vComp["Subtotal"];
              $cfdi_comprobante_descuento = $vComp["Descuento"];
              $cfdi_comprobante_moneda = $vComp["Moneda"];
              $cfdi_comprobante_tipo_de_cambio = $vComp["TipoDeCambio"];
              $cfdi_comprobante_total = $vComp["Total"];
              $cfdi_comprobante_confirmacion = $vComp["Confirmacion"];
              $cfdi_comprobante_tipo_de_comprobante = $vComp["TipoDeComprobante"];
              $cfdi_comprobante_metodo_de_pago = $vComp["MetodoDePago"];
              $cfdi_comprobante_lugar_de_expedicion = $vComp["LugarDeExpedición"];
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
            $cfdi_emisor_rfc = '';
            $cfdi_emisor_nombre = '';
            $cfdi_emisor_regimen_fiscal = '';
            $data_emisor = json_decode($nomi_imp_cfdi_emisor, true);
            foreach ($data_emisor as $CFDIe) {
              $cfdi_emisor_rfc = $CFDIe["EmisorRfc"];
              $cfdi_emisor_nombre = $CFDIe["EmisorNombre"];
              $cfdi_emisor_regimen_fiscal = $CFDIe["EmisorRegimenFiscal"];
            }

            $cfdi_receptor_rfc = '';
            $cfdi_receptor_domicilio_fiscal = '';
            $cfdi_receptor_regimen_fiscal = '';
            $cfdi_receptor_uso_del_cfdi = '';
            $data_receptor = json_decode($nomi_imp_cfdi_receptor, true);
            foreach ($data_receptor as $CFDIReceptor) {
              $cfdi_receptor_rfc = $CFDIReceptor["ReceptorRfc"];
              $cfdi_receptor_domicilio_fiscal = $CFDIReceptor["ReceptorDomicilioFiscal"];
              $cfdi_receptor_regimen_fiscal = $CFDIReceptor["ReceptorRegimenFiscal"];
              $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["ReceptorUsoCFDI"];
            }

            $data_complemento = json_decode($nomi_imp_cfdi_complemento, true);
            foreach ($data_complemento as $vComplemento) {
              $cfdi_complementoVersion = $vComplemento["Version"];
              $cfdi_complementoUUID = $vComplemento["UUID"];
              $cfdi_complementoFechaTimbrado = $vComplemento["FechaTimbrado"];
              $cfdi_complementoRfcProvCertif = $vComplemento["RfcProvCertif"];
              $cfdi_complementoNoCertificadoSAT = $vComplemento["NoCertificadoSAT"];
              $cfdi_complementoSelloCFD = $vComplemento["SelloCFD"];
              $cfdi_complementoSelloSAT = $vComplemento["SelloSAT"];
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
            if ($cfdi_comprobante_version != '') {
              $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$isn_id.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
              $insertCFDISN = DB::table('cfdi_comprobantes_fiscales')//cfdi__estructura
              ->insert(array(
                "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                "origen_proceso" => "isn",
                //"isn_vinculado" => $isn_id,
                "cfdi_comprobante_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($cfdi_comprobante_fecha_contabilizacion),
                "cfdi_comprobante_version" => $cfdi_comprobante_version,	
                "cfdi_comprobante_serie" => $cfdi_comprobante_serie,	
                "cfdi_comprobante_folio" => $cfdi_comprobante_folio,	
                "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
                "cfdi_comprobante_sello" => $cfdi_comprobante_sello,	
                "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
                "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,	
                "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,	
                "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,	
                "cfdi_comprobante_descuento" => $cfdi_comprobante_descuento,	
                "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,	
                "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,	
                "cfdi_comprobante_total" => $cfdi_comprobante_total,
                "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
                "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,	
                "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,	
                "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,

                "cfdi_emisor_rfc" => $cfdi_emisor_rfc,	
                "cfdi_emisor_nombre" => $cfdi_emisor_nombre,	
                "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,

                "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
                "cfdi_receptor_domicilio_fiscal" => $cfdi_receptor_domicilio_fiscal,
                "cfdi_receptor_regimen_fiscal" => $cfdi_receptor_regimen_fiscal,
                "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
                
                "cfdi_complementoVersion" => $cfdi_complementoVersion,	
                "cfdi_complementoUUID" => $cfdi_complementoUUID,	
                "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,	
                "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
                "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,	
                "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,	
                "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,	
              ));

              $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
              $insertCFDIVincBuy = DB::table('cfdi_vinculacion_isn')//cfdi__estructura
              ->insert(array(
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                "isn_vinculado" => $isn_id,
              ));

              $data_conceptos = json_decode($nomi_imp_cfdi_conceptos, true);
              for ($lrdc = 0; $lrdc < count($data_conceptos); $lrdc++) {
                $uuid_cfdi_detalle = Str::uuid()->toString();
                $insertConceptCFDINominas = DB::table('cfdi_comprobantes_conceptos')
                ->insert(array(
                  "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                  "comprobante_fiscal" => $comprobante_fiscal_reg, 
                  "ClaveProdServ" => $data_conceptos[$lrdc]['ClaveProdServ'],
                  "Cantidad" => $data_conceptos[$lrdc]['Cantidad'],
                  "ClaveUnidad" => $data_conceptos[$lrdc]['ClaveUnidad'],
                  "Descripcion" => $data_conceptos[$lrdc]['Descripcion'],
                  "ValorUnitario" => $data_conceptos[$lrdc]['ValorUnitario'],
                  "Importe" => $data_conceptos[$lrdc]['Importe'],
                  "Descuento" => $data_conceptos[$lrdc]['Descuento'],
                  "ObjetoImp" => $data_conceptos[$lrdc]['ObjetoImp']
                ));
              }
            }
            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
          }
          $filepath = $vIsn->root_tkn."/0004-vhm/impuestos_sobre_nomina/$folio_interior";
          
          if ($archivo_xml) {
            $nombre_original = $archivo_xml->getClientOriginalName();
            $ext_doc = $archivo_xml->getClientOriginalExtension();

            $documento_crypt = $JwtAuth->encriptar($nombre_original);
            $token_documento = $JwtAuth->encriptarToken($isn_id, $ext_doc, $nombre_original);

            $insertDocSoli = DB::table("sos_documentos")->insert([
              "token_documento" => $token_documento,
              "fecha_carga" => time(),
              "modulo" => "reembolsos",
              "folio_modulo" => "NOMINA-CFDI-XML",
              "tipo_documento" => "xml",
              "nombre_documento" => $documento_crypt,
              "extension_documento" => $ext_doc,
              "impuesto_sobre_nomina_cfdi" => $isn_id,
              "status_documento" => true,
            ]);

            if ($insertDocSoli) {
              DB::table('vhum_nominas_impuestos')->where("nomi_imp_token", $vIsn->nomi_imp_token)
              ->limit(1)->update(array("nomi_imp_fact_xml" => $documento_crypt));

              $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
            }
          }

          if ($archivo_pdf) {
            $nombre_original = $archivo_pdf->getClientOriginalName();
            $ext_doc = $archivo_pdf->getClientOriginalExtension();

            $documento_crypt = $JwtAuth->encriptar($nombre_original);
            $token_documento = $JwtAuth->encriptarToken($isn_id, $ext_doc, $nombre_original);

            $insertDocSoli = DB::table("sos_documentos")->insert([
              "token_documento" => $token_documento,
              "fecha_carga" => time(),
              "modulo" => "reembolsos",
              "folio_modulo" => "NOMINA-CFDI-PDF",
              "tipo_documento" => "pdf",
              "nombre_documento" => $documento_crypt,
              "extension_documento" => $ext_doc,
              "impuesto_sobre_nomina_cfdi" => $isn_id,
              "status_documento" => true,
            ]);

            if ($insertDocSoli) {
              DB::table('vhum_nominas_impuestos')->where("nomi_imp_token", $vIsn->nomi_imp_token)
              ->limit(1)->update(array("nomi_imp_fact_pdf" => $documento_crypt));
              $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
            }
          }
          ++$count_isn;
          //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$token_nomina_recibo]);
        }

        if ($count_isn == count($isn)) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'CFDIs de la nómina han sido cargados correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error al cargar los CFDIs de la nómina, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required|string',
        'fecha_contabilizacion' => 'required|string', 
        'fecha_vencimiento' => 'required|string',
        'fecha_presentacion' => 'required|string',
        'estado' => 'required|string', 
        'ejercicio' => 'required|numeric', 
        'periodo_inicio' => 'required|string',
        'periodo_fin' => 'required|string',
        //'fecha_pago' => 'required|string',
        'tipo_declaracion' => 'required|string',
        'total_remuneraciones_erogadas' => 'required|numeric', 
        'porcent_sobre_total_remuneraciones_erogadas' => 'required|numeric', 
        'complementarias_impuesto_a_cargo' => 'required|numeric', 
        'complementarias_saldo_a_favor' => 'required|numeric', 
        'impuesto_actualizado' => 'required|numeric', 
        'impuesto_descuento' => 'required|string', 
        'impuesto_recargos' => 'required|numeric', 
        'impuesto_recargos_condonados' => 'required|numeric', 
        'subsi_n_resolu_impuesto_pagar' => 'required|numeric', 
        'subsi_n_resolu_recargos' => 'required|numeric', 
        'compensa_n_resolucion' => 'required|numeric', 
        'compensa_n_resolu_recargos' => 'required|numeric', 
        'impuesto_total_a_pagar' => 'required|numeric', 
        'impuesto_saldo_a_favor' => 'required|numeric',
        'observaciones' => 'required|string', 
        'docs_eliminar' => 'array', 
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nomi_imp_token = $parametrosArray['nomi_imp_token'];
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fecha_vencimiento = $parametrosArray['fecha_vencimiento'];
        $fecha_presentacion = $parametrosArray['fecha_presentacion'];
        $estado = $parametrosArray['estado'];
        $ejercicio = $parametrosArray['ejercicio'];
        $periodo_inicio = $parametrosArray['periodo_inicio'];
        $periodo_fin = $parametrosArray['periodo_fin'];
        //$fecha_pago = $parametrosArray['fecha_pago'];
        $tipo_declaracion = $parametrosArray['tipo_declaracion'];
        $total_remuneraciones_erogadas = $parametrosArray['total_remuneraciones_erogadas'];
        $porcen_sobre_total_remun_erog = $parametrosArray['porcent_sobre_total_remuneraciones_erogadas'];
        $complementarias_impuesto_a_cargo = $parametrosArray['complementarias_impuesto_a_cargo'];
        $complementarias_saldo_a_favor = $parametrosArray['complementarias_saldo_a_favor'];
        $impuesto_actualizado = $parametrosArray['impuesto_actualizado'];
        $impuesto_descuento = $parametrosArray['impuesto_descuento'];
        $impuesto_recargos = $parametrosArray['impuesto_recargos'];
        $impuesto_recargos_condonados = $parametrosArray['impuesto_recargos_condonados'];
        $subsi_n_resolu_impuesto_pagar = $parametrosArray['subsi_n_resolu_impuesto_pagar'];
        $subsi_n_resolu_recargos = $parametrosArray['subsi_n_resolu_recargos'];
        $compensa_n_resolucion = $parametrosArray['compensa_n_resolucion'];
        $compensa_n_resolu_recargos = $parametrosArray['compensa_n_resolu_recargos'];
        $impuesto_total_a_pagar = $parametrosArray['impuesto_total_a_pagar'];
        $impuesto_saldo_a_favor = $parametrosArray['impuesto_saldo_a_favor'];
        $observaciones = $parametrosArray['observaciones'];
        $docs_eliminar = $parametrosArray['docs_eliminar'];

        $OKNominaTkn = isset($nomi_imp_token) && !empty($nomi_imp_token);
        $OKNominaFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $OKNominaFechaVencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
        $OKNominaFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
        $OKNominaEstado = isset($estado) && !empty($estado);
        $OKNominaEjercicio = isset($ejercicio) && !empty($ejercicio) && preg_match($JwtAuth->filtroNumerico(),$ejercicio);
        $OKNominaPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
        $OKNominaPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
        $OKNominaPeriodo = $OKNominaPeriodoInicio && $OKNominaPeriodoFin && ($JwtAuth->convierteFechaEpoc($periodo_fin) >= $JwtAuth->convierteFechaEpoc($periodo_inicio));
        $OKNominaTipoDeclaracion = isset($tipo_declaracion) && !empty($tipo_declaracion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_declaracion);
        
        $OKNominaTotalRemuneracionesErogadas = isset($total_remuneraciones_erogadas) && is_numeric($total_remuneraciones_erogadas) && preg_match($JwtAuth->filtroCostoPrecio(),$total_remuneraciones_erogadas);
        $OKNominaPorcenSobreTotalRemunErogad = isset($porcen_sobre_total_remun_erog) && is_numeric($porcen_sobre_total_remun_erog) && preg_match($JwtAuth->filtroCostoPrecio(),$porcen_sobre_total_remun_erog);
        $OKNominaComplementariasImpuestoACargo = isset($complementarias_impuesto_a_cargo) && is_numeric($complementarias_impuesto_a_cargo) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_impuesto_a_cargo);
        $OKNominaComplementariasSaldoAFavor = isset($complementarias_saldo_a_favor) && is_numeric($complementarias_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_saldo_a_favor);
        $OKNominaImpuestoActualizado = isset($impuesto_actualizado) && is_numeric($impuesto_actualizado) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_actualizado);
        $OKNominaImpuestoDescuento = isset($impuesto_descuento) && is_numeric($impuesto_descuento) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_descuento);
        $OKNominaImpuestoRecargos = isset($impuesto_recargos) && is_numeric($impuesto_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos);
        $OKNominaImpuestoRecargosCondonados = isset($impuesto_recargos_condonados) && is_numeric($impuesto_recargos_condonados) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos_condonados);
        $OKNominaSubsiNResoluImpuestoPagar = isset($subsi_n_resolu_impuesto_pagar) && is_numeric($subsi_n_resolu_impuesto_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_impuesto_pagar);
        $OKNominaSubsiNResoluRecargos = isset($subsi_n_resolu_recargos) && is_numeric($subsi_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_recargos);
        $OKNominaimporteCompensaNResolucion = isset($compensa_n_resolucion) && is_numeric($compensa_n_resolucion) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolucion);
        $OKNominaimporteCompensaNResolucionRecargos = isset($compensa_n_resolu_recargos) && is_numeric($compensa_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolu_recargos);
        $OKNominaImpuestoTotalAPagar = isset($impuesto_total_a_pagar) && is_numeric($impuesto_total_a_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_total_a_pagar);
        $OKNominaImpuestoSaldoAFavor = isset($impuesto_saldo_a_favor) && is_numeric($impuesto_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_saldo_a_favor);
        $OKNominaObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);
        $OKNominaDocsEliminar = isset($docs_eliminar) && is_array($docs_eliminar) && count($docs_eliminar) > 0;

        if ($OKNominaTkn && $OKNominaFCont && $OKNominaFechaVencimiento && $OKNominaFechaPresentacion && $OKNominaEstado && $OKNominaEjercicio && $OKNominaPeriodo && $OKNominaTipoDeclaracion && $OKNominaTotalRemuneracionesErogadas && $OKNominaPorcenSobreTotalRemunErogad && 
          $OKNominaComplementariasImpuestoACargo && $OKNominaComplementariasSaldoAFavor && $OKNominaImpuestoActualizado && $OKNominaImpuestoDescuento && $OKNominaImpuestoRecargos && $OKNominaImpuestoRecargosCondonados && 
          $OKNominaSubsiNResoluImpuestoPagar && $OKNominaSubsiNResoluRecargos && $OKNominaimporteCompensaNResolucion && $OKNominaimporteCompensaNResolucionRecargos && $OKNominaImpuestoTotalAPagar && 
          $OKNominaImpuestoSaldoAFavor && $OKNominaObservacion) {
            
          $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
          ->where([
            'nomImp.nomi_imp_token' => $nomi_imp_token,
            'nomImp.nomi_imp_status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();

          foreach ($queryImpNomina as $vImpNom) {
            $nomina_id = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$vImpNom->nomi_imp_token)->value("id");
            $folio_nomina = $vImpNom->nomi_imp_folio_interior;
            $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
            $nomi_imp_folio = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
            $filepath = $vImpNom->root_tkn . "/0004-vhm/impuestos_sobre_nomina/$vImpNom->nomi_imp_fecha_registro-$folio_nomina/anexos/";

            $isnUpdate = DB::table("vhum_nominas_impuestos")
            ->where("nomi_imp_token",$vImpNom->nomi_imp_token)
            ->limit(1)->update(
              array(
                "nomi_imp_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                "nomi_imp_estado" => DB::table("fnzs_catalogos_fed_estados_municipios")->where("fed_est_mun_token", $estado)->value("id"),
                "nomi_imp_ejercicio" => $ejercicio,
                "nomi_imp_periodo_inicio" => $JwtAuth->convierteFechaEpoc($periodo_inicio),
                "nomi_imp_periodo_fin" => $JwtAuth->convierteFechaEpoc($periodo_fin),
                "nomi_imp_fecha_pago" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                "nomi_imp_fecha_vencimiento" => $JwtAuth->convierteFechaEpoc($fecha_vencimiento),
                "nomi_imp_fecha_presentacion" => $JwtAuth->convierteFechaEpoc($fecha_presentacion),
                "nomi_imp_tipo_declaracion" => $tipo_declaracion,
                "nomi_imp_moneda" => "MXN",
                "nomi_imp_total_remuneraciones_erogadas" => $total_remuneraciones_erogadas,
                "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => $porcen_sobre_total_remun_erog,
                "nomi_imp_complementarias_impuesto_a_cargo" => $complementarias_impuesto_a_cargo,
                "nomi_imp_complementarias_saldo_a_favor" => $complementarias_saldo_a_favor,
                "nomi_imp_impuesto_actualizado" => $impuesto_actualizado,
                "nomi_imp_impuesto_descuento" => $impuesto_descuento,
                "nomi_imp_impuesto_recargos" => $impuesto_recargos,
                "nomi_imp_impuesto_recargos_condonados" => $impuesto_recargos_condonados,
                "nomi_imp_subsi_n_resolu_impuesto_pagar" => $subsi_n_resolu_impuesto_pagar,
                "nomi_imp_subsi_n_resolu_recargos" => $subsi_n_resolu_recargos,
                "nomi_imp_compensa_n_resolucion" => $compensa_n_resolucion,
                "nomi_imp_compensa_n_resolu_recargos" => $compensa_n_resolu_recargos,
                "nomi_imp_impuesto_total_a_pagar" => $impuesto_total_a_pagar,
                "nomi_imp_impuesto_saldo_a_favor" => $impuesto_saldo_a_favor,
                "observaciones" => $JwtAuth->encriptar($observaciones),
              )
            );

            $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
            ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
            ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
            ->limit(1)->update(
              array(
                "order.doc_anterior_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
              )
            );

            if ($OKNominaDocsEliminar) {
              for ($de=0; $de < count($docs_eliminar); $de++) {
                $token_documento = $docs_eliminar[$de]['token_documento'];
                $nombre_documento = $docs_eliminar[$de]['name_documento'];
                $rutaCompleta = "/public/root/".$filepath.$nombre_documento;
                if (Storage::exists($rutaCompleta)) {
                    Storage::delete($rutaCompleta);
                }
                DB::table("sos_documentos")->where("token_documento",$token_documento)->limit(1)->delete();
              }
            }
            
            if (!empty($_FILES['documentos_evidencia'])) {
              $evidencias = $_FILES["documentos_evidencia"];
              //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
              $string_name_evid = json_encode($_FILES["documentos_evidencia"]["name"]);
              if (count(json_decode($string_name_evid)) != 0) {
                $evidencia_nombre = json_decode($string_name_evid);
                for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                  $temporal = $evidencias["tmp_name"][$doc];
                  $doc_name = $evidencias["name"][$doc];
                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%IMP-NOMI-EVID%'");
                  $token_documento = $JwtAuth->encriptarToken($nomina_id,$usuario->empresa_token,$usuario->user_token,$doc_name,$select_folio_doc[0]->folio);
                  $insertDocSoli = DB::table("sos_documentos")->insert(
                    array(
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "pagos",
                      "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($doc_name),
                      "impuesto_sobre_nomina" => $nomina_id,
                      "status_documento" => TRUE,
                    )
                  );
                }
              }
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Reporte de isn con el folio $nomi_imp_folio ha sido actualizada satisfactoriamente"
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKNominaTkn) $mensaje_error = "Error al seleccionar reporte de isn, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaFCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaFechaVencimiento) $mensaje_error = "Error en fecha de vencimiento, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaEstado) $mensaje_error = "Error al seleccionar estado, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaEjercicio) $mensaje_error = "Error al registrar ejercicio, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaPeriodo) $mensaje_error = "Error al registrar periodo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaTipoDeclaracion) $mensaje_error = "Error al registrar tipo de declaración, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaTotalRemuneracionesErogadas) $mensaje_error = "Error al registrar total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaPorcenSobreTotalRemunErogad) $mensaje_error = "Error al registrar % sobre el total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaComplementariasImpuestoACargo) $mensaje_error = "Error al registrar complementarias (Impuesto a cargo), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaComplementariasSaldoAFavor) $mensaje_error = "Error al registrar complementarias (Saldo a favor), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoActualizado) $mensaje_error = "Error al registrar impuesto actualizado, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoDescuento) $mensaje_error = "Error al registrar descuento, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoRecargos) $mensaje_error = "Error al registrar recargos, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoRecargosCondonados) $mensaje_error = "Error al registrar recargos condonados, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaSubsiNResoluImpuestoPagar) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaSubsiNResoluRecargos) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre recargos (%)), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaimporteCompensaNResolucion) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaimporteCompensaNResolucionRecargos) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre recargos), intentelo nuevamente o comuniquese a soporte";

          if (!$OKNominaImpuestoTotalAPagar) $mensaje_error = "Error al registrar total a pagar, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaImpuestoSaldoAFavor) $mensaje_error = "Error al registrar saldo a favor, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaObservacion) $mensaje_error = "Error al registrar observaciones de nómina, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nomi_imp_token = $parametrosArray['nomi_imp_token'];

        $OKNominaPeriodo = isset($nomi_imp_token) && !empty($nomi_imp_token);

        if ($OKNominaPeriodo) {
          $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
          ->where([
            'nomImp.nomi_imp_token' => $nomi_imp_token,
            'nomImp.nomi_imp_status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();
          
          foreach ($queryImpNomina as $vImpNom) {
            $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
            ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
            ->get();
            
            if (count($queryNominaPago) == 0) {
              $queryDeleteNomina = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$vImpNom->nomi_imp_token)
              ->limit(1)->update(array(
                "nomi_imp_status" => FALSE,
                "nomi_imp_fecha_delete" => time()
              ));

              if ($queryDeleteNomina) {
                $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Este reporte de isn ha sido eliminado satisfactoriamente');
              } else {
                $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
              }
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede eliminar, se encuentra vinculado a pagos realizados, intentelo nuevamente o comuniquese a soporte');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar reporte de isn, intentelo nuevamente o comuniquese a soporte');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaDeletedNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $lista_imp_nomina = array();
        $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
        ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
        ->where([
          'nomImp.nomi_imp_status' => FALSE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('nomImp.id', 'DESC')->get();

        foreach ($queryImpNomina as $vImpNom) {
          $folio_nomina = $vImpNom->nomi_imp_folio_interior;
          $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
          $nomi_imp_moneda = $vImpNom->nomi_imp_moneda;
          $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($vImpNom->nomi_imp_moneda);
          $ejercicio = $vImpNom->nomi_imp_ejercicio;
          $periodo_inicio = $vImpNom->nomi_imp_periodo_inicio;
          $periodo_fin = $vImpNom->nomi_imp_periodo_fin;
          
          $queryImpEstado = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("fnzs_catalogos_fed_estados_municipios AS ent", "nomImp.nomi_imp_estado", "ent.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
          ->first();

          $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $row = array(
            "nomi_imp_token" => $vImpNom->nomi_imp_token,
            "nomi_imp_folio" => 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : ''),
            "nomi_imp_fecha_contabilizacion" => date('d-m-Y',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_estado_all_info" => $estado_all_info,
            "nomi_imp_ejercicio" => $ejercicio,
            "nomi_imp_periodo_inicio" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F')),
            "nomi_imp_periodo_fin" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F')),
            "nomi_imp_fecha_vencimiento" => date('d-m-Y',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_tipo_declaracion" => $vImpNom->nomi_imp_tipo_declaracion == 'comple' ? "complementaria" : "normal",
            "nomi_imp_moneda" => "MXN",
            "nomi_imp_impuesto_total_a_pagar" => "$".number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_fecha_delete" => date('d-m-Y',$vImpNom->nomi_imp_fecha_delete),
          );
          $lista_imp_nomina[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'isn_lista' => $lista_imp_nomina
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restauraNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nomi_imp_token = $parametrosArray['nomi_imp_token'];

        $OKNominaPeriodo = isset($nomi_imp_token) && !empty($nomi_imp_token);

        if ($OKNominaPeriodo) {
          $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
          ->where([
            'nomImp.nomi_imp_token' => $nomi_imp_token,
            'nomImp.nomi_imp_status' => FALSE,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();
          
          foreach ($queryImpNomina as $vImpNom) {
            $queryDeleteNomina = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$vImpNom->nomi_imp_token)
            ->limit(1)->update(array(
              "nomi_imp_status" => TRUE,
              "nomi_imp_fecha_delete" => NULL
            ));

            if ($queryDeleteNomina) {
              $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Este reporte de isn ha sido restaurado satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede restaurar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar reporte de isn, intentelo nuevamente o comuniquese a soporte');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPermanenteNominaImpuestos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nomi_imp_token = $parametrosArray['nomi_imp_token'];

        $OKNominaPeriodo = isset($nomi_imp_token) && !empty($nomi_imp_token);

        if ($OKNominaPeriodo) {
          $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
          ->where([
            'nomImp.nomi_imp_token' => $nomi_imp_token,
            'nomImp.nomi_imp_status' => FALSE,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();

          foreach ($queryImpNomina as $vNom) {
            $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
            ->where('nomImp.nomi_imp_token',$vNom->nomi_imp_token)
            ->get();
            
            if (count($queryNominaPago) == 0) {
              $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
              ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
              ->where('nomImp.nomi_imp_token',$vNom->nomi_imp_token)
              ->limit(1)->delete();
            }

            $queryDeleteNomina = DB::table("vhum_nominas_impuestos")
            ->where("nomi_imp_token",$vNom->nomi_imp_token)
            ->limit(1)->delete();

            if ($queryDeleteNomina) {
              $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Este reporte de isn ha sido eliminado satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
            } 
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar reporte de isn, intentelo nuevamente o comuniquese a soporte');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}




  public function listaegresosProductosProcessBuy(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaProductosTrue = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'cant_art_prorrateo' => 'required|numeric',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto '.$validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $cant_art_prorrateo = $parametrosArray['cant_art_prorrateo'];

        $artList = ProductosModelo::join("eegr_compras_detalle AS detcomp", "in_egr_catalogo_productos.id", "=", "detcomp.producto")
        ->join("eegr_compras AS buy","detcomp.numero_compra","=","buy.id")
        ->join("sos_ps_genero AS gen", "in_egr_catalogo_productos.genero", "=", "gen.id")
        ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'in_egr_catalogo_productos.status' => TRUE,
          'detcomp.activo_fijo' => NULL,
          'detcomp.activo_intangible' => NULL,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])
        ->whereIn('in_egr_catalogo_productos.familia', ['i_i', 'i_v'])
        ->get();
        //echo count($artList);
        $totalCompra = 0;
        $resultCompratotal = 0;
        $numLista = 1;
        foreach ($artList as $vArt) {
          $token_detcompra = $vArt->token_detcompra;

          $totalDetComp = DB::select("SELECT TRUNCATE(SUM(precio_unitario*cantidad) - SUM(descuento*cantidad),?) AS total
            FROM eegr_compras_detalle WHERE token_detcompra = ?", [$vArt->e_moneda_decimales, $token_detcompra]);

          $totalDetCompFormat = DB::select("SELECT FORMAT(SUM(precio_unitario*cantidad) - SUM(descuento*cantidad),?) AS total 
            FROM eegr_compras_detalle WHERE token_detcompra = ?", [$vArt->e_moneda_decimales, $token_detcompra]);

          if ($vArt->concepto_producto != '') {
            $articulo = $JwtAuth->desencriptar($vArt->concepto_producto) . " - " . $JwtAuth->desencriptar($vArt->marca_producto);
          }

          if ($vArt->concepto_servicio != '') {
            $articulo = $JwtAuth->desencriptar($vArt->concepto_servicio);
          }

          $formatPuRetTras = DB::select(
            "SELECT FORMAT(?,?) AS formatPunit,FORMAT(?,?) AS formatDescuento,FORMAT(?,?) AS formatRetenc,FORMAT(?,?) AS formatTraslad",
            [
              $vArt->precio_unitario,
              $vArt->e_moneda_decimales,
              $vArt->descuento,
              $vArt->e_moneda_decimales,
              $vArt->total_retenciones,
              $vArt->e_moneda_decimales,
              $vArt->total_traslados,
              $vArt->e_moneda_decimales
            ]
          );

          $arrayEachDetalleCompra = array(
            "numLista" => $numLista,
            "token_cat_productos" => $vArt->token_cat_productos,
            "imagen" => "./assets/images/catalogos/default_producto.jpg",
            "clasificacion" => $JwtAuth->generar($vArt->clasificacion) . '-' . $JwtAuth->generar($vArt->folio_genero) . '-' . $JwtAuth->generar($vArt->folio),
            "producto" => $JwtAuth->desencriptar(DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$vArt->token_cat_productos)->value("producto")),
            "clave" => $vArt->clave,
            "folio_compra" => $JwtAuth->generar($vArt->folio_compra),
            "cantidad" => $vArt->cantidad,
            "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
            "precio_unitario" => $formatPuRetTras[0]->formatPunit,
            "token_detcompra" => $token_detcompra,
            "total" => $totalDetComp[0]->total,
            "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
            "total_retenciones" => "$" . $formatPuRetTras[0]->formatRetenc,
            "total_traslados" => "$" . $formatPuRetTras[0]->formatTraslad,
            "totalCompra" => "",
            "totalProrrateo" => "",
            "desvioProrrateo" => "",
          );
          $listaProductosTrue[] = $arrayEachDetalleCompra;
          $totalCompra = $totalCompra + $totalDetComp[0]->total;
          ++$numLista;
        }
        for ($i = 0; $i < count($listaProductosTrue); $i++) {
          $listaProductosTrue[$i]["totalCompra"] = $totalCompra;
          //echo $totalCompra;exit;
          $prorrateoUno = $totalCompra != 0 ? $parametrosArray['cant_art_prorrateo'] * ($listaProductosTrue[$i]["total"] / $totalCompra) : 0;
          $prorrateoDos = $prorrateoUno != 0 ? $prorrateoUno / $listaProductosTrue[$i]["cantidad"] : 0;

          $listaProductosTrue[$i]["totalProrrateo"] = $prorrateoUno;
          $listaProductosTrue[$i]["desvioProrrateo"] = $prorrateoDos;
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'listado' => $listaProductosTrue
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaegresosProductosProcessBuy2(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaProductosTrue = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $decimalesMoneda = DB::select(
          "SELECT catmon.decimales FROM catalogo_monedas AS catmon 
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                JOIN teci_usuarios_catalogo AS users WHERE emp.moneda = catmon.id AND emp.empresa_token = ?
                AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                AND pers.usuario = users.id AND users.usuario_token = ?",
          [$usuario->empresa_token, $usuario->user_token]
        );

        $servList = ProductosModelo::join("productos", "catprod.producto", "=", "catprod.id")
          ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
          ->join("teci_catalogo_prodservsat AS pscsat", "catprod.catalogoSAT", "=", "teci_catalogo_prodservsat AS pscsat.id")
          ->join("teci_unidad_medida AS medida_entrada", "catprod.medida_entrada", "=", "medida_entrada.id")
          ->join("teci_unidad_medida AS medida_salida", "catprod.medida_salida", "=", "medida_salida.id")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            'catprod.status' => TRUE,
            'catprod.uso_producto' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          //echo $value->root_tkn;
          date_default_timezone_set($value->zona_horaria);
          if ($value->imagen == '' || !file_exists(Storage::path('public/root/' .
            $value->root_tkn . '/0002-cpp/catalogos/productos/'
            . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
            $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
            $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
          } else {
            $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
              $value->root_tkn . '/0002-cpp/catalogos/productos/'
              . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
              $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen)));
          }

          /*$filepath = $value->root_tkn."/0002-cpp/catalogos/productos/".$JwtAuth->generar($value->clasificacion)."-".
                        $JwtAuth->generar($value->folio_genero)."-".$JwtAuth->generar($value->folio)."-".$value->fecha_alta."/";
                        return QRCode::text('QR Code Generator for Laravel!')->png();*/

          $buyList = ProductosModelo::join("eegr_compras_detalle AS detcomp", "catprod.id", "=", "detcomp.producto")
            ->join("compras AS buy", "detcomp.numero_compra", "=", "buy.id")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'buy.status_recepcion' => FALSE,
              'detcomp.activo_fijo' => NULL,
              'detcomp.activo_intangible' => NULL,
              'detcomp.prorrateo' => FALSE,
              'catprod.token_cat_productos' => $value->token_cat_productos,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->orderBy('detcomp.id', 'DESC')->get();

          if (count($buyList) > 0) {
            $totalCompra = 0;
            $detcompra = array();
            foreach ($buyList as $resDetCompra) {
              $token_detcompra = $resDetCompra->token_detcompra;

              $totalDetComp = DB::select("SELECT 
                                TRUNCATE(SUM(precio_unitario*cantidad) - SUM(descuento*cantidad),?) AS total
                                FROM detalle_compra WHERE token_detcompra = ?", [$decimalesMoneda[0]->decimales, $token_detcompra]);

              $totalDetCompFormat = DB::select("SELECT 
                                FORMAT(SUM(precio_unitario*cantidad) - SUM(descuento*cantidad),?) AS total
                                FROM detalle_compra WHERE token_detcompra = ?", [$decimalesMoneda[0]->decimales, $token_detcompra]);

              if ($resDetCompra->concepto_producto != '') {
                $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_producto) . " - " . $JwtAuth->desencriptar($resDetCompra->marca_producto);
              }

              if ($resDetCompra->concepto_servicio != '') {
                $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_servicio);
              }

              $formatPuRetTras = DB::select(
                "SELECT FORMAT(?,?) AS formatPunit,FORMAT(?,?) AS formatDescuento,FORMAT(?,?) AS formatRetenc,FORMAT(?,?) AS formatTraslad",
                [
                  $resDetCompra->precio_unitario,
                  $decimalesMoneda[0]->decimales,
                  $resDetCompra->descuento,
                  $decimalesMoneda[0]->decimales,
                  $resDetCompra->total_retenciones,
                  $decimalesMoneda[0]->decimales,
                  $resDetCompra->total_traslados,
                  $decimalesMoneda[0]->decimales
                ]
              );

              $arrayEachDetalleCompra = array(
                "cat_productos" => $value->token_cat_productos,
                "articulo" => $JwtAuth->desencriptar($value->producto),
                "cantidad" => $resDetCompra->cantidad,
                "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
                "precio_unitario" => "$" . $formatPuRetTras[0]->formatPunit,
                "token_detcompra" => $token_detcompra,
                "total" => $totalDetComp[0]->total,
                "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
                "total_retenciones" => "$" . $formatPuRetTras[0]->formatRetenc,
                "total_traslados" => "$" . $formatPuRetTras[0]->formatTraslad,
              );
              $detcompra[] = $arrayEachDetalleCompra;
              $totalCompra = $totalCompra + $totalDetComp[0]->total;
            }

            $arrayForeachVig = array(
              "token_cat_productos" => $value->token_cat_productos,
              "imagen" => $logo_prod,
              "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
                $JwtAuth->generar($value->folio),
              "producto" => $JwtAuth->desencriptar($value->producto),
              "clave" => $value->clave,
              "detcompra" => $detcompra,
              "totalCompra" => $totalCompra,
            );
            $listaProductosTrue[] = $arrayForeachVig;
          }
        }
        return response()->json([
          'listado' => $listaProductosTrue,
          'codigo' => 200,
          'status' => 'success'
        ]);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }


    private function guardaDepreciacionFiscal($vActDeprec,$main_empresa_id) {
    $valor_minimo_fiscal = 1.00;
    $mejoras = DB::table('eegr_compras_prorrateos_incrementos')
    ->whereBetween("fecha_contabilizacion_incremento", [$vActDeprec->fecha_ultimo_corte_fiscal, $vActDeprec->fecha_proximo_corte_fiscal])
    ->where('empresa', $main_empresa_id)
    ->where('activof_unidad', $vActDeprec->FUNIID)
    ->sum('incremento_monto');

    $totales_deprec_fiscal = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID,'tipo' => 'fiscal'])
    ->sum('importe');

    $base_depreciable_fiscal = $vActDeprec->costo_adquisicion - $totales_deprec_fiscal + $mejoras;//

    if ($vActDeprec->deprec_fiscal_tipo === 'cuota') {
      $importe_depreciado_mensual_fiscal = $vActDeprec->deprec_fiscal_importe;
    } else {
      $fiscal_tasa_anual_decimal = $vActDeprec->deprec_fiscal_importe / 100;
      $fiscal_depreciado_anual = $base_depreciable_fiscal * $fiscal_tasa_anual_decimal;
      $importe_depreciado_mensual_fiscal = $fiscal_depreciado_anual / 12;
    }

    $pendiente_por_depreciar_fiscal = $base_depreciable_fiscal - $totales_deprec_fiscal - $valor_minimo_fiscal;

    if ($pendiente_por_depreciar_fiscal <= 0) {
      $importe_depreciado_fiscal = 0;
    } else if ($importe_depreciado_mensual_fiscal > $pendiente_por_depreciar_fiscal) {
      $importe_depreciado_fiscal = $pendiente_por_depreciar_fiscal;
    } else {
      $importe_depreciado_fiscal = $importe_depreciado_mensual_fiscal;
    }

    $nuevo_total_acumulado = $totales_deprec_fiscal + $importe_depreciado_fiscal;
    $valor_libros_al_cierre = $base_depreciable_fiscal - $nuevo_total_acumulado;
    
    if ($importe_depreciado_fiscal > 0) {
      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(                
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $vActDeprec->FUNIID,
        'tipo' => 'fiscal',
        'periodo' => $vActDeprec->fecha_proximo_corte_fiscal, // El Unix Timestamp confirmado
        'importe' => $importe_depreciado_fiscal,
        'valor_libros_final' => $valor_libros_al_cierre,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));
    }
  }

  private function guardaDepreciacionContable($vActDeprec,$main_empresa_id) {
    $valor_rescate = 0;//(float)$vActDeprec->valor_rescate ?? 0;

    $mejoras = DB::table('eegr_compras_prorrateos_incrementos')
    //->where('fecha_contabilizacion_incremento','>',$vActDeprec->fecha_proximo_corte_fiscal)
    ->whereBetween("fecha_contabilizacion_incremento", [$vActDeprec->fecha_ultimo_corte_fiscal, $vActDeprec->fecha_proximo_corte_fiscal])
    ->where('empresa', $main_empresa_id)
    ->where('activof_unidad', $vActDeprec->FUNIID)
    ->sum('incremento_monto');
  
    //Asiento contable 
    $totales_deprec_contable = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID,'tipo' => 'contable'])
    ->sum('importe');
  
    $base_depreciable_contable = ($vActDeprec->costo_adquisicion + $mejoras) - $valor_rescate;
    if ($vActDeprec->deprec_contable_tipo === 'cuota') {
      $importe_depreciado_contable_calculo = $vActDeprec->deprec_contable_importe;
    } else {
      $contable_tasa_anual_decimal = $vActDeprec->deprec_contable_importe / 100;
      $contable_depreciado_anual = $base_depreciable_contable * $contable_tasa_anual_decimal;
      $importe_depreciado_contable_calculo = $contable_depreciado_anual / 12;
    }
    
    $pendiente_por_depreciar_contable = $base_depreciable_contable - $totales_deprec_contable;
  
    $importe_depreciado_contable_final = ($importe_depreciado_contable_calculo > $pendiente_por_depreciar_contable) ? $pendiente_por_depreciar_contable : $importe_depreciado_contable_calculo;
  
    $nuevo_acumulado_contable = $totales_deprec_contable + $importe_depreciado_contable_final;
    $costo_total_activo_contable = (float)$vActDeprec->costo_adquisicion + (float)$mejoras;
    $valor_libros_post_depreciacion = $costo_total_activo_contable - $nuevo_acumulado_contable;
  
    DB::table('eegr_activos_fijos_depreciaciones')
    ->insert(array(
      'token_activof_deprec' => Str::uuid(),
      'activof_unidad' => $vActDeprec->FUNIID,
      'tipo' => 'contable',
      'periodo' => $vActDeprec->fecha_proximo_corte_contable, // El Unix Timestamp confirmado
      'importe' => $importe_depreciado_contable_final,
      'valor_libros_final' => $valor_libros_post_depreciacion,
      'empresa' => $main_empresa_id,
      'depreciado' => 1 // Marcamos como aplicado
    ));
  }

  private function guardaDepreciacionFiscal2($vActDeprec,$fecha_contabilizacion,$main_empresa_id) {
    $valor_minimo_fiscal = 1.00;

    // 1. Obtener TODAS las mejoras hasta la fecha de corte
    $mejoras_totales = DB::table('eegr_compras_prorrateos_incrementos')
    ->where('fecha_contabilizacion_incremento', '<=', $vActDeprec->fecha_proximo_corte_fiscal)
    ->where('empresa', $main_empresa_id)
    ->where('activof_unidad', $vActDeprec->FUNIID)
    ->sum('incremento_monto');

    // 2. La base es el costo original + todas las mejoras
    $monto_original_activo = (float)$vActDeprec->costo_adquisicion + $mejoras_totales;

    // 3. Obtener lo que ya se depreció históricamente
    $depreciacion_acumulada_previa = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID,'tipo' => 'fiscal'])
    ->sum('importe');

    // 4. Calcular el gasto del mes sobre la base completa
    if ($vActDeprec->deprec_fiscal_tipo === 'cuota') {
      $gasto_mensual_teorico = $vActDeprec->deprec_fiscal_importe;
    } else {
      $fiscal_tasa_anual_decimal = $vActDeprec->deprec_fiscal_importe / 100;
      $gasto_mensual_teorico = ($monto_original_activo * $fiscal_tasa_anual_decimal) / 12;
    }

    // 5. Controlar que no nos pasemos del valor del activo
    $pendiente_por_depreciar_fiscal = $monto_original_activo - $depreciacion_acumulada_previa - $valor_minimo_fiscal;

    // Si ya llegamos al valor mínimo, el gasto es 0
    $gasto_final = max(0, min($gasto_mensual_teorico, $pendiente_por_depreciar_fiscal));

    // 6. Valores finales para registro
    $nuevo_total_acumulado = $depreciacion_acumulada_previa + $gasto_final;
    $valor_libros_al_cierre = $monto_original_activo - $nuevo_total_acumulado;
    
    if ($gasto_final > 0) {
      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(                
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $vActDeprec->FUNIID,
        'deprec_concepto' => 'depreciación',
        'tipo' => 'fiscal',
        'fecha_cont_deprec_periodo' => $fecha_contabilizacion,
        'periodo' => $vActDeprec->fecha_proximo_corte_fiscal, // El Unix Timestamp confirmado
        'importe' => $gasto_final,
        'valor_libros_final' => $valor_libros_al_cierre,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));
    }
  }

  private function guardaDepreciacionContable2($vActDeprec,$fecha_contabilizacion,$main_empresa_id) {
    $valor_rescate = 0;//(float)$vActDeprec->valor_rescate ?? 0;

    // 1. Obtener TODAS las mejoras históricas hasta la fecha de corte
    $mejoras_acumuladas = DB::table('eegr_compras_prorrateos_incrementos')
    ->where('fecha_contabilizacion_incremento','>',$vActDeprec->fecha_proximo_corte_fiscal)
    ->where('empresa', $main_empresa_id)
    ->where('activof_unidad', $vActDeprec->FUNIID)
    ->sum('incremento_monto');

    // 2. Costo Total Histórico (Costo Original + Mejoras Acumuladas)
    $costo_total_historico = (float)$vActDeprec->costo_adquisicion + $mejoras_acumuladas;
    $monto_original_activo = $costo_total_historico - $valor_rescate;

    // 3. Obtener depreciación contable acumulada hasta antes de este movimiento
    $depreciacion_acumulada_previa = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID,'tipo' => 'contable'])
    ->sum('importe');
  
    // 4. Calcular el gasto del mes sobre la base completa
    if ($vActDeprec->deprec_contable_tipo === 'cuota') {
      $gasto_mensual_teorico = $vActDeprec->deprec_contable_importe;
    } else {
      $contable_tasa_anual_decimal = $vActDeprec->deprec_contable_importe / 100;
      $gasto_mensual_teorico = ($monto_original_activo * $contable_tasa_anual_decimal) / 12;
    }
    
    // 5. Controlar que no nos pasemos del valor del activo
    $pendiente_por_depreciar_contable = $monto_original_activo - $depreciacion_acumulada_previa;
  
    // 6. Determinar el gasto final (evitar exceder el saldo)
    $gasto_final = max(0, min($gasto_mensual_teorico, $pendiente_por_depreciar_contable));
  
    // 7. Calcular valores para el cierre
    $nuevo_acumulado_contable = $depreciacion_acumulada_previa + $gasto_final;
    $valor_libros_final = $costo_total_historico - $nuevo_acumulado_contable;
  
    DB::table('eegr_activos_fijos_depreciaciones')
    ->insert(array(
      'token_activof_deprec' => Str::uuid(),
      'activof_unidad' => $vActDeprec->FUNIID,
      'deprec_concepto' => 'depreciación',
      'tipo' => 'contable',
      'fecha_cont_deprec_periodo' => $fecha_contabilizacion,
      'periodo' => $vActDeprec->fecha_proximo_corte_contable, // El Unix Timestamp confirmado
      'importe' => $gasto_final,
      'valor_libros_final' => $valor_libros_final,
      'empresa' => $main_empresa_id,
      'depreciado' => 1 // Marcamos como aplicado
    ));
  }