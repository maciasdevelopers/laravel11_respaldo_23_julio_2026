<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MovimientosBancariosModelo;

class FNZS_EstadoMovFinanCajaController extends Controller{
  private function saldoInicialCaja($token_caja,$empresa,$fechaInicio){
    $saldo_inicial_progresivo = 0;
    $querySaldoInicial = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_catalogos_caja AS caj_cat", "fnzs_actividad_movimientos.caja", "=", "caj_cat.id")
    ->where([
      "caj_cat.token_caja" => $token_caja,
      "emp.empresa_token" => $empresa
    ])
    ->where('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', '<', $fechaInicio)
    ->orderBy('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', 'ASC')
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
    ->leftJoin("vhum_reporte_asimilados_main AS asim_main", "order.asimilados_reporte", "=", "asim_main.id")
    ->leftJoin("vhum_reporte_asimilados_desglose AS asim_desg", "asim_main.id", "=", "asim_desg.asim_reporte")
    ->leftJoin("eegr_catalogo_proveedores AS catAsimProv", "asim_desg.desglose_asim_receptor", "=", "catAsimProv.id")
    ->leftJoin("sos_personas AS asimProv", "catAsimProv.proveedor", "=", "asimProv.id")
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
      'catAsimProv.folio AS asim_folio_prov',
      'catAsimProv.post_folio AS asim_post_folio_prov',
      'asimProv.nombre_extendido AS asim_nombre_prov',
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
    if ($vOrdenPago->asim_folio_prov && $vOrdenPago->asim_nombre_prov) {
      //$asim_folio = 'ASIM-'.$JwtAuth->generarFolio($vOrdenPago->asim_folio).(!is_null($vOrdenPago->asim_subf) ? '-'.$vOrdenPago->asim_subf : '');
      $asim_folio_prov = 'PRV-'.$JwtAuth->generarFolio($vOrdenPago->asim_folio_prov).(!is_null($vOrdenPago->asim_post_folio_prov) ? '-'.$vOrdenPago->asim_post_folio_prov : '');
      $asim_nombre_prov = $JwtAuth->desencriptar($vOrdenPago->asim_nombre_prov);
      return "$asim_folio_prov $asim_nombre_prov";
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

  public function movimientosFinancierosCaja(Request $request){
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
      $token_caja = $request->input('token_caja');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
      $fechaFin = strtotime($periodo_fin . " 23:59:59");

      $queryMovimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("fnzs_catalogos_caja AS caj_cat", "fnzs_actividad_movimientos.caja", "=", "caj_cat.id")
      ->where([
        "caj_cat.token_caja" => $token_caja,
        "emp.empresa_token" => $empresa
      ])
      ->whereBetween("fnzs_actividad_movimientos.fecha_contabilizacion_movimiento", [$fechaInicio, $fechaFin])
      ->orderBy('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', 'ASC')
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
        
        $saldo_inicial_caja = $this->saldoInicialCaja($token_caja,$empresa,$fechaInicio);
        $saldo_acumulado_depositos = 0;
        $saldo_acumulado_retiros = 0;
        $saldo_acumulado_progresivo = $saldo_inicial_caja;
        $contador = 0;
        
        foreach ($queryMovimientos as $vMov) {
          //da_te_default_timezone_set($vMov->zona_horaria);
          $token_movimiento = $vMov->token_movimiento;
          $folio_movimiento = 'MOV-'.$JwtAuth->generarFolio($vMov->folio_movimiento);
          $concepto_movimiento = !is_null($vMov->concepto_movimiento) && $vMov->concepto_movimiento != '' ? $JwtAuth->desencriptar($vMov->concepto_movimiento) : '';
          $mov_f_cont = $vMov->fecha_contabilizacion_movimiento;
          $fecha_movimiento = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('Y-m-d',$mov_f_cont) : '';
          $fecha_movimiento_excel = !is_null($mov_f_cont) && $mov_f_cont != '' ? date('d/m/Y',$mov_f_cont) : '';

          $token_caja = $vMov->token_caja;
          $monto_applc = (float)$vMov->monto_aplicado;
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
                ->join("fnzs_actividad_movimientos AS actMov", "mcp.movimiento_cp_destino", "=", "actMov.id")
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
            $documento_anterior_asociado = "";
            $parte_relacionada = "";
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
              $concepto_movimiento = $JwtAuth->desencriptar(DB::table("fnzs_pagos_pago")->where("id",$vMov->pago)->value('observacionesPago'));
              
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
            $documento_anterior_asociado = "ACRMOV-".$JwtAuth->generarFolio(
              DB::table("fnzs_catalogo_acreedores_movimientos")->where("id",$vMov->acreedor_movimiento)->value('folio_acre_mov')
            );
            $concepto_movimiento = $JwtAuth->desencriptar(DB::table("fnzs_catalogo_acreedores_movimientos")->where("id",$vMov->acreedor_movimiento)->value('acre_observaciones_mov'));
            $parte_relacionada = $this->parteRelMovAcreedor($vMov->acreedor_movimiento,$JwtAuth);
            $movimiento_debe = $vMov->tipo_movimiento == 'R' ? $vMov->monto_aplicado : 0;

            $movimiento_haber = $vMov->tipo_movimiento == 'S' ? $vMov->monto_aplicado : 0;
          }
          if (!is_null($vMov->deudor_movimiento)) {
            $documento_anterior_asociado = "DEUMOV-".$JwtAuth->generarFolio(
              DB::table("fnzs_catalogo_deudores_movimientos")->where("id",$vMov->deudor_movimiento)->value('folio_deu_mov')
            );
            $concepto_movimiento = $JwtAuth->desencriptar(DB::table("fnzs_catalogo_deudores_movimientos")->where("id",$vMov->deudor_movimiento)->value('deu_observaciones_mov'));
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
        
        $caja_result_saldo = ($saldo_inicial_caja + $saldo_acumulado_depositos) - $saldo_acumulado_retiros;

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "total_movimientos" => count($arrayMovimientos),
          "mov_moneda" => $codeMoneda,
          "mov_moneda_decimales" => $decimalesMoneda,
          "movimientos_saldo_inicial" => "$".number_format($saldo_inicial_caja,$decimalesMoneda,'.', ','),
          "movimientos_deposito" => "$".number_format($saldo_acumulado_depositos,$decimalesMoneda,'.', ','),
          "movimientos_retiro" => "$".number_format($saldo_acumulado_retiros,$decimalesMoneda,'.', ','),
          "saldo_final" => "$".number_format($caja_result_saldo,$decimalesMoneda,'.', ','),
          "movimientos" => $arrayMovimientos,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}