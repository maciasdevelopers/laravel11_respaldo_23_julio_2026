<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\DeudoresModelo;
use App\Models\PersonalModelo;
use App\Models\User;
use PDF;
use QRCode;

class FNZS_DeudoresController extends Controller{
  public function catalogoNombresRelacionados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
    ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
    ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
    ->where('emp.empresa_token',$empresa)
    ->get();
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where("catprov.authorized",TRUE)
    ->where("catprov.subClase","PF")
    ->where(function($nac) {
      $nac->where(function ($dat){
        $dat->where("prov.nacionalidad","118")
        ->whereIn('catprov.regimen_fiscal', function ($query) {
          $query->select('id')->from('sos_regimen_fiscal')
          ->whereIn("clave",["605","612"]);
        });
      })
      ->orWhere(function ($dat) {
        $dat->where("prov.nacionalidad","!=","118");
      });
    })
    ->where("catprov.status",TRUE)
    ->where("emp.empresa_token",$empresa)
    ->where("users.usuario_token",$usuario)
    ->get();
    
    $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
    ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where("catAcree.id",">", 0)
    ->where("catAcree.acr_status",TRUE)
    ->where("emp.empresa_token",$empresa)
    ->where("users.usuario_token",$usuario)
    ->get();

    if ($listEmpleados->isEmpty() && $listaProveedores->isEmpty() && $queryAcreedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron nombres relacionados registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaNombresRelacionados = array();
      
      foreach ($listEmpleados as $vEmploy) {
        $trab_relacionado_token = $vEmploy->empleado_token;
        $trab_relacionado_folio = "TRB-".$JwtAuth->generarFolio($vEmploy->folio_pers);
        $trab_relacionado_nombre = ucwords($JwtAuth->desencriptarNombres($vEmploy->paterno,$vEmploy->materno,$vEmploy->nombre));

        $rowTrab = array(
          "people_relacionado_tipo" => "TRAB",
          "people_relacionado_token" => $trab_relacionado_token,
          "people_relacionado_folio" => $trab_relacionado_folio,
          "people_relacionado_nombre" => $trab_relacionado_nombre,
          "people_relacionado_nombre_completo" => "$trab_relacionado_folio $trab_relacionado_nombre",
          "people_relacionado_comercial_nombre" => "",
          "selected" => false,
        );
        $listaNombresRelacionados[] = $rowTrab;
      }

      foreach ($listaProveedores as $vProv) {
        $prov_relacionado_token = $vProv->token_cat_proveedores;
        $prov_relacionado_folio = 'PRV-'.$JwtAuth->generarFolio($vProv->folio).(!is_null($vProv->post_folio) ? '-'.$vProv->post_folio : '');
        $prov_relacionado_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
        $prov_relacionado_comercial_nombre = !is_null($vProv->nombre_com) && $vProv->nombre_com != '' ? ' nombre com. '.$JwtAuth->desencriptar($vProv->nombre_com) : '';
        $people_relacionado_nacionalidad = $vProv->nacionalidad;
        //echo "$people_relacionado_nacionalidad ";
        $rowProv = array(
          "people_relacionado_tipo" => "PROV",
          "people_relacionado_token" => $prov_relacionado_token,
          "people_relacionado_folio" => $prov_relacionado_folio,
          "people_relacionado_nombre" => $prov_relacionado_nombre,
          "people_relacionado_comercial_nombre" => $prov_relacionado_comercial_nombre,
          "people_relacionado_nombre_completo" => "$prov_relacionado_folio $prov_relacionado_nombre",
          "selected" => false,
        );
        $listaNombresRelacionados[] = $rowProv;
      }

      foreach ($queryAcreedores as $vAcr) {
        date_default_timezone_set($vAcr->zona_horaria);
        $arc_relacionado_token = $vAcr->token_cat_acreedores;
        $arc_relacionado_folio = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');
        $arc_relacionado_nombre = !is_null($vAcr->acr_titular) && $vAcr->acr_titular != '' ? $JwtAuth->desencriptar($vAcr->acr_titular) : '';
        $arc_relacionado_comercial_nombre = !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : '';

        $queryTrabVinc = DB::table("fnzs_catalogo_acreedores AS catAcree")
        ->join("vhum_empleados_catalogo AS catTrab", "catAcree.acr_empleado_vinculado", "catTrab.id")
        ->join("sos_personas AS trab", "catTrab.empleado_name", "trab.id")
        ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
        ->select("catTrab.folio_pers","trab.rfc_generico","trab.rfc","trab.tax_id","trab.nombre_extendido","trab.paterno","trab.materno","trab.nombre")
        ->first();
        $trab_folio = $queryTrabVinc ? "TRB-".$JwtAuth->generarFolio($queryTrabVinc->folio_pers) : 'N/A';
        $trab_rfc_generico = $queryTrabVinc ? $queryTrabVinc->rfc_generico : 'N/A';
        $trab_rfc = $queryTrabVinc && $queryTrabVinc->rfc != NULL ? $JwtAuth->desencriptar($queryTrabVinc->rfc) : 'N/A';
        $trab_tax_id = $queryTrabVinc && $queryTrabVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryTrabVinc->tax_id) : 'N/A';
        $trab_nombre = $queryTrabVinc ? ($queryTrabVinc->nombre_extendido ? $JwtAuth->desencriptar($queryTrabVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryTrabVinc->paterno,$queryTrabVinc->materno,$queryTrabVinc->nombre)) : 'N/A';

        $queryProvVinc = DB::table("fnzs_catalogo_acreedores AS catAcree")
        ->join("eegr_catalogo_proveedores AS catprov", "catAcree.acr_proveedor_vinculado", "catprov.id")
        ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
        ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
        ->select("catprov.folio","catprov.post_folio","prov.rfc_generico","prov.rfc_generico","prov.rfc","prov.tax_id","prov.nombre_extendido","prov.paterno","prov.materno","prov.nombre")
        ->first();
        $prov_folio = $queryProvVinc ? 'PRV-'.$JwtAuth->generarFolio($queryProvVinc->folio).(!is_null($queryProvVinc->post_folio) ? '-'.$queryProvVinc->post_folio : '') : 'N/A';
        $prov_rfc_generico = $queryProvVinc ? $queryProvVinc->rfc_generico : 'N/A';
        $prov_rfc = $queryProvVinc && $queryProvVinc->rfc != NULL ? $JwtAuth->desencriptar($queryProvVinc->rfc) : 'N/A';
        $prov_tax_id = $queryProvVinc && $queryProvVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryProvVinc->tax_id) : 'N/A';
        $prov_nombre = $queryProvVinc ? ($queryProvVinc->nombre_extendido ? $JwtAuth->desencriptar($queryProvVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryProvVinc->paterno,$queryProvVinc->materno,$queryProvVinc->nombre)) : 'N/A';

        $queryDeuVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
        ->join("fnzs_catalogo_acreedores AS catAcree", "catDeu.id", "catAcree.acr_deudor_vinculado")
        ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
        ->select("catDeu.deu_folio","catDeu.deu_post_folio","catDeu.deu_titular")
        ->first();
        $deu_folio = $queryDeuVinc ? 'DEU-'.$JwtAuth->generarFolio($queryDeuVinc->deu_folio).(!is_null($queryDeuVinc->deu_post_folio) ? '-'.$queryDeuVinc->deu_post_folio : '') : 'N/A';
        $deu_nombre = $queryDeuVinc ? (!is_null($queryDeuVinc->deu_titular) && $queryDeuVinc->deu_titular != "" ? $JwtAuth->desencriptar($queryDeuVinc->deu_titular) : '') : 'N/A';
        $deu_rfc_generico = 'N/A';
        $deu_rfc = 'N/A';
        $deu_tax_id = 'N/A';

        $rowAcr = array(
          "people_relacionado_tipo" => "ACREE",
          "people_relacionado_token" => $arc_relacionado_token,
          "people_relacionado_folio" => $arc_relacionado_folio,
          "people_relacionado_nombre" => $arc_relacionado_nombre,
          "people_relacionado_comercial_nombre" => $arc_relacionado_comercial_nombre,
          "people_relacionado_nombre_completo" => "$arc_relacionado_folio $arc_relacionado_nombre",

          "trab_folio" => $trab_folio,
          "trab_rfc_generico" => $trab_rfc_generico,
          "trab_rfc" => $trab_rfc,
          "trab_tax_id" => $trab_tax_id,
          "trab_nombre" => $trab_nombre,

          "prov_folio" => $prov_folio,
          "prov_rfc_generico" => $prov_rfc_generico,
          "prov_rfc" => $prov_rfc,
          "prov_tax_id" => $prov_tax_id,
          "prov_nombre" => $prov_nombre,

          "deu_folio" => $deu_folio,
          "deu_rfc_generico" => $deu_rfc_generico,
          "deu_rfc" => $deu_rfc,
          "deu_tax_id" => $deu_tax_id,
          "deu_nombre" => $deu_nombre
        );
        $listaNombresRelacionados[] = $rowAcr;
      }

      $dataMensaje = array(
        "nombres_relacionados" => $listaNombresRelacionados,
        "code" => 200,
        "status" => "success"
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deudoresCatGeneral(Request $request){
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
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catDeu.deu_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catDeu.deu_fecha_contab_registro", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron deudores registrados'
        );
      } else {
        $arrayDeudores = array();
        foreach ($queryDeudores as $vDeu) {
          date_default_timezone_set($vDeu->zona_horaria);
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');
          
          $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_catalogo_deudores AS catDeu", "reem_main.user_acreedor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $deudor_deuda_total = 0;
          $deudor_deuda_restante = 0;
          $deudor_deuda_debe = 0;
          $deudor_deuda_haber = 0;
          $deudor_deuda_saldo = 0;
          $pagos_deudor_moneda = "";
          $estado_cuenta_deudor = array();
          
          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_deudores AS catDeu", "pago.vinc_deudor", "=", "catDeu.id")
          ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_deudores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("mov.condicion_deu_mov","S")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select([
            "mov.token_deu_mov AS token_deu_mov",
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
            "mov.deu_fecha_registro AS f_reg_mov",
            "pago.token_pagos AS token_pagos",
            "pago.token_pagos AS id_registro",
            "pago.folio_pagos AS folio_movimiento",
            "pago.fecha_contabilizacion AS fecha_contabilizacion",
            "pago.observacionesPago AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $movimientos = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
          ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
          ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->where("mov.condicion_deu_mov","R")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select([
            "mov.token_deu_mov AS token_deu_mov",
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            "mov.deu_fecha_registro AS f_reg_mov",
            DB::raw("'' AS token_pagos"),
            "mov.token_deu_mov AS id_registro",
            "mov.folio_deu_mov AS folio_movimiento",
            "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.deu_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.deu_monto_mov AS monto_movimiento",
            "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
            "mov.deu_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $queryEstadoDeCuenta = $pagos->unionAll($movimientos)
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();
          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_pagos = $vECuenta->token_pagos;

            $mov_documento_anterior = "";
            switch ($vECuenta->tipo_registro_e_cuenta) {
              case 'PAGO':
					      $queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS pvinc", "mov.id", "pvinc.mov_realizado")
                ->join("fnzs_pagos_pago AS pag", "pvinc.pago_vinculado", "pag.id")
                ->join("fnzs_pagos_pago_ordenes_vinculadas AS opvinc", "pag.id", "opvinc.pago_realizado")
                ->join("fnzs_pagos_orden AS order", "opvinc.orden_pago_vinculada", "order.id")
                ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.ord_anticipo", "ant.uuid_anticipo")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("ant.folio_anticipo")->first();
                $mov_documento_anterior = $queryDocAnterior ? 'ANT-'.$JwtAuth->generarFolio($queryDocAnterior->folio_anticipo) : '';
                break;
              case 'MOVIMIENTO':
					      /*$queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_pagos_orden AS order", "mov.orden_pago_vinculada", "order.id")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("order.folio_ordenPago")->first();*/

					      $queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo AS opvinc", "mov.id", "opvinc.mov_realizado")
                ->join("fnzs_pagos_orden AS order", "opvinc.orden_pago", "order.id")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("order.folio_ordenPago")->first();
                $mov_documento_anterior = $queryDocAnterior ? "ORDP-".$JwtAuth->generarFolio($queryDocAnterior->folio_ordenPago) : '';
                break;
              default:
                $mov_documento_anterior = "";
                break;
            }

            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';

					  $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->where("buy.fecha_contabilizacion",$vECuenta->fecha_contabilizacion)
            ->select("cfdi.cfdi_comprobante_forma_de_pago","cfdi.cfdi_comprobante_metodo_de_pago")->first();
            
            $mov_forma_metodo_de_pago = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" && $queryMetodoPago ? 
              $queryMetodoPago->cfdi_comprobante_forma_de_pago." - ".$JwtAuth->getFormasPagoAPI($queryMetodoPago->cfdi_comprobante_forma_de_pago)." / ".$queryMetodoPago->cfdi_comprobante_metodo_de_pago : "---";

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_deudor) == 0 ? $e_cuenta_debe - $e_cuenta_haber : ($estado_cuenta_deudor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_debe) - $e_cuenta_haber;

            $row_cuenta_estado = array(
              "contador" => $contador, 
              "tipo_registro_e_cuenta" => $vECuenta->tipo_registro_e_cuenta,
              
              //pagos
              "pago_token" => $token_pagos,
              "pago_folio" => $vECuenta->tipo_registro_e_cuenta == "PAGO" ? "PAGO-".$JwtAuth->generarFolio($vECuenta->folio_movimiento) : "",
              
              //movimientos
              "movimiento_token" => $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? $vECuenta->id_registro : "",
              "movimiento_folio" => $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? "MOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento) : "",

              //neutrales
              "documento_anterior" => $mov_documento_anterior,
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($vECuenta->forma_pago_pago) : "---",
              "observacionesPago" => $payment_observaciones,
              "pago_moneda" => $vECuenta->moneda_movimiento,
							"pago_moneda_decimales" =>$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),
              "monto_pago" => "$".number_format($vECuenta->monto_movimiento * $vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_debe" => $e_cuenta_debe,
              "estado_cuenta_debe_format" => "$".number_format($e_cuenta_debe,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_haber" => $e_cuenta_haber,
              "estado_cuenta_haber_format" => "$".number_format($e_cuenta_haber,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_saldo" => $e_cuenta_saldo,
              "estado_cuenta_saldo_format" => "$".number_format($e_cuenta_saldo,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
            );
            $estado_cuenta_deudor[] = $row_cuenta_estado;
            ++$contador;
          }

          $deudor_deuda_total = 0;
          $deudor_deuda_restante = 0;
          $deudor_deuda_debe = 0;
          $deudor_deuda_haber = 0;
          $deudor_deuda_saldo = 0;
          $pagos_deudor_moneda = "";
          for ($i=0; $i < count($estado_cuenta_deudor); $i++) { 
            $pagos_deudor_moneda = $vECuenta->moneda_movimiento;
            $deudor_deuda_debe = $deudor_deuda_debe + floatval($estado_cuenta_deudor[$i]["estado_cuenta_debe"] ?? 0);
            $deudor_deuda_haber = $deudor_deuda_haber + floatval($estado_cuenta_deudor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $deudor_deuda_saldo = floatval($deudor_deuda_debe ?? 0) - floatval($deudor_deuda_haber ?? 0);
          
          $queryTrabVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("vhum_empleados_catalogo AS catTrab", "catDeu.deu_empleado_vinculado", "catTrab.id")
          ->join("sos_personas AS trab", "catTrab.empleado_name", "trab.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catTrab.folio_pers","trab.rfc_generico","trab.rfc","trab.tax_id","trab.nombre_extendido","trab.paterno","trab.materno","trab.nombre")
          ->first();
          $trab_folio = $queryTrabVinc ? "TRB-".$JwtAuth->generarFolio($queryTrabVinc->folio_pers) : 'N/A';
          $trab_rfc_generico = $queryTrabVinc ? $queryTrabVinc->rfc_generico : 'N/A';
          $trab_rfc = $queryTrabVinc && $queryTrabVinc->rfc != NULL ? $JwtAuth->desencriptar($queryTrabVinc->rfc) : 'N/A';
          $trab_tax_id = $queryTrabVinc && $queryTrabVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryTrabVinc->tax_id) : 'N/A';
          $trab_nombre = $queryTrabVinc ? ($queryTrabVinc->nombre_extendido ? $JwtAuth->desencriptar($queryTrabVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryTrabVinc->paterno,$queryTrabVinc->materno,$queryTrabVinc->nombre)) : 'N/A';

          $queryProvVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("eegr_catalogo_proveedores AS catprov", "catDeu.deu_proveedor_vinculado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catprov.folio","catprov.post_folio","prov.rfc_generico","prov.rfc_generico","prov.rfc","prov.tax_id","prov.nombre_extendido","prov.paterno","prov.materno","prov.nombre")
          ->first();
          $prov_folio = $queryProvVinc ? 'PRV-'.$JwtAuth->generarFolio($queryProvVinc->folio).(!is_null($queryProvVinc->post_folio) ? '-'.$queryProvVinc->post_folio : '') : 'N/A';
          $prov_rfc_generico = $queryProvVinc ? $queryProvVinc->rfc_generico : 'N/A';
          $prov_rfc = $queryProvVinc && $queryProvVinc->rfc != NULL ? $JwtAuth->desencriptar($queryProvVinc->rfc) : 'N/A';
          $prov_tax_id = $queryProvVinc && $queryProvVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryProvVinc->tax_id) : 'N/A';
          $prov_nombre = $queryProvVinc ? ($queryProvVinc->nombre_extendido ? $JwtAuth->desencriptar($queryProvVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryProvVinc->paterno,$queryProvVinc->materno,$queryProvVinc->nombre)) : 'N/A';

          $queryAcreeVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("fnzs_catalogo_acreedores AS catAcree", "catDeu.deu_acreedor_vinculado", "catAcree.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catAcree.acr_folio","catAcree.acr_post_folio","catAcree.acr_titular")
          ->first();
          $acr_folio = $queryAcreeVinc ? 'ACREE-'.$JwtAuth->generarFolio($queryAcreeVinc->acr_folio).(!is_null($queryAcreeVinc->acr_post_folio) ? '-'.$queryAcreeVinc->acr_post_folio : '') : 'N/A';
          $acr_nombre = $queryAcreeVinc ? (!is_null($queryAcreeVinc->acr_titular) && $queryAcreeVinc->acr_titular != "" ? $JwtAuth->desencriptar($queryAcreeVinc->acr_titular) : '') : 'N/A';
          $acr_rfc_generico = 'N/A';
          $acr_rfc = 'N/A';
          $acr_tax_id = 'N/A';

          $queryRegFis = DB::table("sos_regimen_fiscal AS reg_fis")
          ->join("fnzs_catalogo_deudores AS catDeu", "reg_fis.id", "catDeu.deu_regimen_fiscal")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("reg_fis.token_regimen_fiscal","reg_fis.clave","reg_fis.descripcion")
          ->first();
          $deu_regimen_fiscal_token = $queryRegFis ? $queryRegFis->token_regimen_fiscal : ''; 
          $deu_regimen_fiscal_clave = $queryRegFis ? $queryRegFis->clave : 'N/A'; 
          $deu_regimen_fiscal_descripcion = $queryRegFis ? $queryRegFis->descripcion : 'N/A'; 

          $selectMovimientosDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
					->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

					$selectPagosDeudor = DB::table("fnzs_pagos_pago AS pay")
					->join("fnzs_catalogo_deudores AS catDeu", "pay.vinc_deudor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $arrayForeach = array(
            "token_cat_deudores" => $vDeu->token_cat_deudores,
            "folio" => $folio_deu,

            "deu_rfc_generico" => !is_null($vDeu->deu_rfc_generico) ? $vDeu->deu_rfc_generico : 'N/A',
            "deu_rfc" => !is_null($vDeu->deu_rfc) ? $JwtAuth->desencriptar($vDeu->deu_rfc) : 'N/A',
            "deu_taxId" => !is_null($vDeu->deu_taxId) ? $JwtAuth->desencriptar($vDeu->deu_taxId) : 'N/A',
            "deu_titular" => !is_null($vDeu->deu_titular) ? $JwtAuth->desencriptar($vDeu->deu_titular) : 'N/A',
            "nombre_comercial" => !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : 'N/A',
            "cuenta_contable" => !empty($vDeu->deu_cuenta_contable) ? $vDeu->deu_cuenta_contable : 'N/A',

            "deuda_al_deudor" => $deudor_deuda_saldo > 0 ? "$".number_format($deudor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
            "eliminacion_activa" => $selectPersEmpEmi == 0 ? true : false,
            "data_detalle_vista" => false,

            "trab_folio" => $trab_folio,
            "trab_rfc_generico" => $trab_rfc_generico,
            "trab_rfc" => $trab_rfc,
            "trab_tax_id" => $trab_tax_id,
            "trab_nombre" => $trab_nombre,
            "trab_complete_nombre" => $trab_folio != "N/A" && $trab_nombre != "N/A" ? "$trab_folio - $trab_nombre" : "N/A",

            "prov_folio" => $prov_folio,
            "prov_rfc_generico" => $prov_rfc_generico,
            "prov_rfc" => $prov_rfc,
            "prov_tax_id" => $prov_tax_id,
            "prov_nombre" => $prov_nombre,
            "prov_complete_nombre" => $prov_folio != "N/A" && $prov_nombre != "N/A" ? "$prov_folio - $prov_nombre" : "N/A",

            "acr_folio" => $acr_folio,
            "acr_rfc_generico" => $acr_rfc_generico,
            "acr_rfc" => $acr_rfc,
            "acr_tax_id" => $acr_tax_id,
            "acr_nombre" => $acr_nombre,
            "acr_complete_nombre" => $acr_folio != "N/A" && $acr_nombre != "N/A" ? "$acr_folio - $acr_nombre" : "N/A",

            "regimen_fiscal_token" => $deu_regimen_fiscal_token,
            "regimen_fiscal_clave" => $deu_regimen_fiscal_clave,
            "regimen_fiscal_descripcion" => $deu_regimen_fiscal_descripcion,

            "data_detalle" => [],
            "data_reembolsos" => [],
            "data_pagos" => [],
            "data_anticipos" => [],
            "utilizado" => $selectMovimientosDeudor > 0 && $selectPagosDeudor > 0 ? true : false,
          );
          $arrayDeudores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'deudores' => $arrayDeudores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deudoresCatMx(Request $request){
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
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catDeu.deu_nacionalidad" => "MEX",
        "catDeu.deu_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catDeu.deu_fecha_contab_registro", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron deudores registrados'
        );
      } else {
        $arrayDeudores = array();
        foreach ($queryDeudores as $vDeu) {
          date_default_timezone_set($vDeu->zona_horaria);
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');
          
          $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_catalogo_deudores AS catDeu", "reem_main.user_acreedor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $deudor_deuda_total = 0;
          $deudor_deuda_restante = 0;
          $deudor_deuda_debe = 0;
          $deudor_deuda_haber = 0;
          $deudor_deuda_saldo = 0;
          $pagos_deudor_moneda = "";
          $estado_cuenta_deudor = array();
          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_deudores AS catDeu", "pago.vinc_deudor", "=", "catDeu.id")
          ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_deudores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("mov.condicion_deu_mov","S")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select([
            "mov.token_deu_mov AS token_deu_mov",
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
            "mov.deu_fecha_registro AS f_reg_mov",
            "pago.token_pagos AS token_pagos",
            "pago.token_pagos AS id_registro",
            "pago.folio_pagos AS folio_movimiento",
            "pago.fecha_contabilizacion AS fecha_contabilizacion",
            "pago.observacionesPago AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $movimientos = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
          ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
          ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->where("mov.condicion_deu_mov","R")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select([
            "mov.token_deu_mov AS token_deu_mov",
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            "mov.deu_fecha_registro AS f_reg_mov",
            DB::raw("'' AS token_pagos"),
            "mov.token_deu_mov AS id_registro",
            "mov.folio_deu_mov AS folio_movimiento",
            "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.deu_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.deu_monto_mov AS monto_movimiento",
            "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
            "mov.deu_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $queryEstadoDeCuenta = $pagos->unionAll($movimientos)
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();
          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_pagos = $vECuenta->token_pagos;

            $mov_documento_anterior = "";
            switch ($vECuenta->tipo_registro_e_cuenta) {
              case 'PAGO':
					      $queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS pvinc", "mov.id", "pvinc.mov_realizado")
                ->join("fnzs_pagos_pago AS pag", "pvinc.pago_vinculado", "pag.id")
                ->join("fnzs_pagos_pago_ordenes_vinculadas AS opvinc", "pag.id", "opvinc.pago_realizado")
                ->join("fnzs_pagos_orden AS order", "opvinc.orden_pago_vinculada", "order.id")
                ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.ord_anticipo", "ant.uuid_anticipo")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("ant.folio_anticipo")->first();
                $mov_documento_anterior = $queryDocAnterior ? 'ANT-'.$JwtAuth->generarFolio($queryDocAnterior->folio_anticipo) : '';
                break;
              case 'MOVIMIENTO':
					      /*$queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_pagos_orden AS order", "mov.orden_pago_vinculada", "order.id")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("order.folio_ordenPago")->first();*/

					      $queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo AS opvinc", "mov.id", "opvinc.mov_realizado")
                ->join("fnzs_pagos_orden AS order", "opvinc.orden_pago", "order.id")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("order.folio_ordenPago")->first();
                $mov_documento_anterior = $queryDocAnterior ? "ORDP-".$JwtAuth->generarFolio($queryDocAnterior->folio_ordenPago) : '';
                break;
              default:
                $mov_documento_anterior = "";
                break;
            }

            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';

					  $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->where("buy.fecha_contabilizacion",$vECuenta->fecha_contabilizacion)
            ->select("cfdi.cfdi_comprobante_forma_de_pago","cfdi.cfdi_comprobante_metodo_de_pago")->first();
            
            $mov_forma_metodo_de_pago = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" && $queryMetodoPago ? 
              $queryMetodoPago->cfdi_comprobante_forma_de_pago." - ".$JwtAuth->getFormasPagoAPI($queryMetodoPago->cfdi_comprobante_forma_de_pago)+" / "+$queryMetodoPago->cfdi_comprobante_metodo_de_pago : "---";

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_deudor) == 0 ? $e_cuenta_debe - $e_cuenta_haber : ($estado_cuenta_deudor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_debe) - $e_cuenta_haber;

            $row_cuenta_estado = array(
              "contador" => $contador, 
              "tipo_registro_e_cuenta" => $vECuenta->tipo_registro_e_cuenta,
              
              //pagos
              "pago_token" => $token_pagos,
              "pago_folio" => $vECuenta->tipo_registro_e_cuenta == "PAGO" ? "PAGO-".$JwtAuth->generarFolio($vECuenta->folio_movimiento) : "",
              
              //movimientos
              "movimiento_token" => $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? $vECuenta->id_registro : "",
              "movimiento_folio" => $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? "MOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento) : "",

              //neutrales
              "documento_anterior" => $mov_documento_anterior,
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($vECuenta->forma_pago_pago) : "---",
              "observacionesPago" => $payment_observaciones,
              "pago_moneda" => $vECuenta->moneda_movimiento,
							"pago_moneda_decimales" =>$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),
              "monto_pago" => "$".number_format($vECuenta->monto_movimiento * $vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_debe" => $e_cuenta_debe,
              "estado_cuenta_debe_format" => "$".number_format($e_cuenta_debe,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_haber" => $e_cuenta_haber,
              "estado_cuenta_haber_format" => "$".number_format($e_cuenta_haber,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_saldo" => $e_cuenta_saldo,
              "estado_cuenta_saldo_format" => "$".number_format($e_cuenta_saldo,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
            );
            $estado_cuenta_deudor[] = $row_cuenta_estado;
            ++$contador;
          }

          $deudor_deuda_total = 0;
          $deudor_deuda_restante = 0;
          $deudor_deuda_debe = 0;
          $deudor_deuda_haber = 0;
          $deudor_deuda_saldo = 0;
          $pagos_deudor_moneda = "";
          for ($i=0; $i < count($estado_cuenta_deudor); $i++) { 
            $pagos_deudor_moneda = $vECuenta->moneda_movimiento;
            $deudor_deuda_debe = $deudor_deuda_debe + floatval($estado_cuenta_deudor[$i]["estado_cuenta_debe"] ?? 0);
            $deudor_deuda_haber = $deudor_deuda_haber + floatval($estado_cuenta_deudor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $deudor_deuda_saldo = floatval($deudor_deuda_debe ?? 0) - floatval($deudor_deuda_haber ?? 0);
          
          $queryTrabVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("vhum_empleados_catalogo AS catTrab", "catDeu.deu_empleado_vinculado", "catTrab.id")
          ->join("sos_personas AS trab", "catTrab.empleado_name", "trab.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catTrab.folio_pers","trab.rfc_generico","trab.rfc","trab.tax_id","trab.nombre_extendido","trab.paterno","trab.materno","trab.nombre")
          ->first();
          $trab_folio = $queryTrabVinc ? "TRB-".$JwtAuth->generarFolio($queryTrabVinc->folio_pers) : 'N/A';
          $trab_rfc_generico = $queryTrabVinc ? $queryTrabVinc->rfc_generico : 'N/A';
          $trab_rfc = $queryTrabVinc && $queryTrabVinc->rfc != NULL ? $JwtAuth->desencriptar($queryTrabVinc->rfc) : 'N/A';
          $trab_tax_id = $queryTrabVinc && $queryTrabVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryTrabVinc->tax_id) : 'N/A';
          $trab_nombre = $queryTrabVinc ? ($queryTrabVinc->nombre_extendido ? $JwtAuth->desencriptar($queryTrabVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryTrabVinc->paterno,$queryTrabVinc->materno,$queryTrabVinc->nombre)) : 'N/A';

          $queryProvVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("eegr_catalogo_proveedores AS catprov", "catDeu.deu_proveedor_vinculado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catprov.folio","catprov.post_folio","prov.rfc_generico","prov.rfc_generico","prov.rfc","prov.tax_id","prov.nombre_extendido","prov.paterno","prov.materno","prov.nombre")
          ->first();
          $prov_folio = $queryProvVinc ? 'PRV-'.$JwtAuth->generarFolio($queryProvVinc->folio).(!is_null($queryProvVinc->post_folio) ? '-'.$queryProvVinc->post_folio : '') : 'N/A';
          $prov_rfc_generico = $queryProvVinc ? $queryProvVinc->rfc_generico : 'N/A';
          $prov_rfc = $queryProvVinc && $queryProvVinc->rfc != NULL ? $JwtAuth->desencriptar($queryProvVinc->rfc) : 'N/A';
          $prov_tax_id = $queryProvVinc && $queryProvVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryProvVinc->tax_id) : 'N/A';
          $prov_nombre = $queryProvVinc ? ($queryProvVinc->nombre_extendido ? $JwtAuth->desencriptar($queryProvVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryProvVinc->paterno,$queryProvVinc->materno,$queryProvVinc->nombre)) : 'N/A';

          $acr_folio = 'N/A';
          $acr_rfc_generico = 'N/A';
          $acr_rfc = 'N/A';
          $acr_tax_id = 'N/A';
          $acr_nombre = 'N/A';
          $queryAcreeVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("fnzs_catalogo_acreedores AS catAcree", "catDeu.deu_acreedor_vinculado", "catAcree.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catAcree.acr_folio","catAcree.acr_post_folio","catAcree.acr_titular")
          ->first();
          $acr_folio = $queryAcreeVinc ? 'DEU-'.$JwtAuth->generarFolio($queryAcreeVinc->acr_folio).(!is_null($queryAcreeVinc->acr_post_folio) ? '-'.$queryAcreeVinc->acr_post_folio : '') : 'N/A';
          $acr_nombre = $queryAcreeVinc ? (!is_null($queryAcreeVinc->acr_titular) && $queryAcreeVinc->acr_titular != "" ? $JwtAuth->desencriptar($queryAcreeVinc->acr_titular) : '') : 'N/A';

          $queryRegFis = DB::table("sos_regimen_fiscal AS reg_fis")
          ->join("fnzs_catalogo_deudores AS catDeu", "reg_fis.id", "catDeu.deu_regimen_fiscal")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("reg_fis.token_regimen_fiscal","reg_fis.clave","reg_fis.descripcion")
          ->first();
          $deu_regimen_fiscal_token = $queryRegFis ? $queryRegFis->token_regimen_fiscal : ''; 
          $deu_regimen_fiscal_clave = $queryRegFis ? $queryRegFis->clave : 'N/A'; 
          $deu_regimen_fiscal_descripcion = $queryRegFis ? $queryRegFis->descripcion : 'N/A'; 

          $selectMovimientosDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
					->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

					$selectPagosDeudor = DB::table("fnzs_pagos_pago AS pay")
					->join("fnzs_catalogo_deudores AS catDeu", "pay.vinc_deudor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $arrayForeach = array(
            "token_cat_deudores" => $vDeu->token_cat_deudores,
            "folio" => $folio_deu,

            "deu_rfc_generico" => !is_null($vDeu->deu_rfc_generico) ? $vDeu->deu_rfc_generico : 'N/A',
            "deu_rfc" => !is_null($vDeu->deu_rfc) ? $JwtAuth->desencriptar($vDeu->deu_rfc) : 'N/A',
            "deu_taxId" => !is_null($vDeu->deu_taxId) ? $JwtAuth->desencriptar($vDeu->deu_taxId) : 'N/A',
            "deu_titular" => !is_null($vDeu->deu_titular) ? $JwtAuth->desencriptar($vDeu->deu_titular) : 'N/A',
            "nombre_comercial" => !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : 'N/A',
            "cuenta_contable" => !empty($vDeu->deu_cuenta_contable) ? $vDeu->deu_cuenta_contable : 'N/A',

            "deuda_al_deudor" => $deudor_deuda_saldo > 0 ? "$".number_format($deudor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
            "eliminacion_activa" => $selectPersEmpEmi == 0 ? true : false,
            "data_detalle_vista" => false,

            "trab_folio" => $trab_folio,
            "trab_rfc_generico" => $trab_rfc_generico,
            "trab_rfc" => $trab_rfc,
            "trab_tax_id" => $trab_tax_id,
            "trab_nombre" => $trab_nombre,

            "prov_folio" => $prov_folio,
            "prov_rfc_generico" => $prov_rfc_generico,
            "prov_rfc" => $prov_rfc,
            "prov_tax_id" => $prov_tax_id,
            "prov_nombre" => $prov_nombre,

            "acr_folio" => $acr_folio,
            "acr_rfc_generico" => $acr_rfc_generico,
            "acr_rfc" => $acr_rfc,
            "acr_tax_id" => $acr_tax_id,
            "acr_nombre" => $acr_nombre,

            "regimen_fiscal_token" => $deu_regimen_fiscal_token,
            "regimen_fiscal_clave" => $deu_regimen_fiscal_clave,
            "regimen_fiscal_descripcion" => $deu_regimen_fiscal_descripcion,
            "data_detalle" => [],
            "data_reembolsos" => [],
            "data_pagos" => [],
            "data_anticipos" => [],
            "utilizado" => $selectMovimientosDeudor > 0 && $selectPagosDeudor > 0 ? true : false,
          );
          $arrayDeudores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'total_lista' => count($queryDeudores),
          'deudores' => $arrayDeudores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deudoresCatExt(Request $request){
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
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catDeu.deu_nacionalidad" => "EXT",
        "catDeu.deu_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catDeu.deu_fecha_contab_registro", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron deudores registrados'
        );
      } else {
        $arrayDeudores = array();
        foreach ($queryDeudores as $vDeu) {
          date_default_timezone_set($vDeu->zona_horaria);
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');
          
          $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_catalogo_deudores AS catDeu", "reem_main.user_acreedor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $deudor_deuda_total = 0;
          $deudor_deuda_restante = 0;
          $deudor_deuda_debe = 0;
          $deudor_deuda_haber = 0;
          $deudor_deuda_saldo = 0;
          $pagos_deudor_moneda = "";
          $estado_cuenta_deudor = array();
          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_deudores AS catDeu", "pago.vinc_deudor", "=", "catDeu.id")
          ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_deudores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("mov.condicion_deu_mov","S")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select([
            "mov.token_deu_mov AS token_deu_mov",
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
            "mov.deu_fecha_registro AS f_reg_mov",
            "pago.token_pagos AS token_pagos",
            "pago.token_pagos AS id_registro",
            "pago.folio_pagos AS folio_movimiento",
            "pago.fecha_contabilizacion AS fecha_contabilizacion",
            "pago.observacionesPago AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $movimientos = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
          ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
          ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->where("mov.condicion_deu_mov","R")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select([
            "mov.token_deu_mov AS token_deu_mov",
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            "mov.deu_fecha_registro AS f_reg_mov",
            DB::raw("'' AS token_pagos"),
            "mov.token_deu_mov AS id_registro",
            "mov.folio_deu_mov AS folio_movimiento",
            "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.deu_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.deu_monto_mov AS monto_movimiento",
            "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
            "mov.deu_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $queryEstadoDeCuenta = $pagos->unionAll($movimientos)
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();
          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_pagos = $vECuenta->token_pagos;

            $mov_documento_anterior = "";
            switch ($vECuenta->tipo_registro_e_cuenta) {
              case 'PAGO':
					      $queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS pvinc", "mov.id", "pvinc.mov_realizado")
                ->join("fnzs_pagos_pago AS pag", "pvinc.pago_vinculado", "pag.id")
                ->join("fnzs_pagos_pago_ordenes_vinculadas AS opvinc", "pag.id", "opvinc.pago_realizado")
                ->join("fnzs_pagos_orden AS order", "opvinc.orden_pago_vinculada", "order.id")
                ->join("eegr_catalogo_proveedores_anticipo AS ant", "order.ord_anticipo", "ant.uuid_anticipo")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("ant.folio_anticipo")->first();
                $mov_documento_anterior = $queryDocAnterior ? 'ANT-'.$JwtAuth->generarFolio($queryDocAnterior->folio_anticipo) : '';
                break;
              case 'MOVIMIENTO':
					      /*$queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_pagos_orden AS order", "mov.orden_pago_vinculada", "order.id")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("order.folio_ordenPago")->first();*/

					      $queryDocAnterior = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
                ->join("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo AS opvinc", "mov.id", "opvinc.mov_realizado")
                ->join("fnzs_pagos_orden AS order", "opvinc.orden_pago", "order.id")
                ->where("mov.token_deu_mov",$vECuenta->token_deu_mov)
                ->select("order.folio_ordenPago")->first();
                $mov_documento_anterior = $queryDocAnterior ? "ORDP-".$JwtAuth->generarFolio($queryDocAnterior->folio_ordenPago) : '';
                break;
              default:
                $mov_documento_anterior = "";
                break;
            }

            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';

					  $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->where("buy.fecha_contabilizacion",$vECuenta->fecha_contabilizacion)
            ->select("cfdi.cfdi_comprobante_forma_de_pago","cfdi.cfdi_comprobante_metodo_de_pago")->first();
            
            $mov_forma_metodo_de_pago = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" && $queryMetodoPago ? 
              $queryMetodoPago->cfdi_comprobante_forma_de_pago." - ".$JwtAuth->getFormasPagoAPI($queryMetodoPago->cfdi_comprobante_forma_de_pago)+" / "+$queryMetodoPago->cfdi_comprobante_metodo_de_pago : "---";

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_deudor) == 0 ? $e_cuenta_debe - $e_cuenta_haber : ($estado_cuenta_deudor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_debe) - $e_cuenta_haber;

            $row_cuenta_estado = array(
              "contador" => $contador, 
              "tipo_registro_e_cuenta" => $vECuenta->tipo_registro_e_cuenta,
              
              //pagos
              "pago_token" => $token_pagos,
              "pago_folio" => $vECuenta->tipo_registro_e_cuenta == "PAGO" ? "PAGO-".$JwtAuth->generarFolio($vECuenta->folio_movimiento) : "",
              
              //movimientos
              "movimiento_token" => $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? $vECuenta->id_registro : "",
              "movimiento_folio" => $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" ? "MOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento) : "",

              //neutrales
              "documento_anterior" => $mov_documento_anterior,
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($vECuenta->forma_pago_pago) : "---",
              "observacionesPago" => $payment_observaciones,
              "pago_moneda" => $vECuenta->moneda_movimiento,
							"pago_moneda_decimales" =>$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),
              "monto_pago" => "$".number_format($vECuenta->monto_movimiento * $vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_debe" => $e_cuenta_debe,
              "estado_cuenta_debe_format" => "$".number_format($e_cuenta_debe,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_haber" => $e_cuenta_haber,
              "estado_cuenta_haber_format" => "$".number_format($e_cuenta_haber,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
              "estado_cuenta_saldo" => $e_cuenta_saldo,
              "estado_cuenta_saldo_format" => "$".number_format($e_cuenta_saldo,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
            );
            $estado_cuenta_deudor[] = $row_cuenta_estado;
            ++$contador;
          }

          $deudor_deuda_total = 0;
          $deudor_deuda_restante = 0;
          $deudor_deuda_debe = 0;
          $deudor_deuda_haber = 0;
          $deudor_deuda_saldo = 0;
          $pagos_deudor_moneda = "";
          for ($i=0; $i < count($estado_cuenta_deudor); $i++) { 
            $pagos_deudor_moneda = $vECuenta->moneda_movimiento;
            $deudor_deuda_debe = $deudor_deuda_debe + floatval($estado_cuenta_deudor[$i]["estado_cuenta_debe"] ?? 0);
            $deudor_deuda_haber = $deudor_deuda_haber + floatval($estado_cuenta_deudor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $deudor_deuda_saldo = floatval($deudor_deuda_debe ?? 0) - floatval($deudor_deuda_haber ?? 0);
          
          $queryTrabVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("vhum_empleados_catalogo AS catTrab", "catDeu.deu_empleado_vinculado", "catTrab.id")
          ->join("sos_personas AS trab", "catTrab.empleado_name", "trab.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catTrab.folio_pers","trab.rfc_generico","trab.rfc","trab.tax_id","trab.nombre_extendido","trab.paterno","trab.materno","trab.nombre")
          ->first();
          $trab_folio = $queryTrabVinc ? "TRB-".$JwtAuth->generarFolio($queryTrabVinc->folio_pers) : 'N/A';
          $trab_rfc_generico = $queryTrabVinc ? $queryTrabVinc->rfc_generico : 'N/A';
          $trab_rfc = $queryTrabVinc && $queryTrabVinc->rfc != NULL ? $JwtAuth->desencriptar($queryTrabVinc->rfc) : 'N/A';
          $trab_tax_id = $queryTrabVinc && $queryTrabVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryTrabVinc->tax_id) : 'N/A';
          $trab_nombre = $queryTrabVinc ? ($queryTrabVinc->nombre_extendido ? $JwtAuth->desencriptar($queryTrabVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryTrabVinc->paterno,$queryTrabVinc->materno,$queryTrabVinc->nombre)) : 'N/A';

          $queryProvVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("eegr_catalogo_proveedores AS catprov", "catDeu.deu_proveedor_vinculado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catprov.folio","catprov.post_folio","prov.rfc_generico","prov.rfc_generico","prov.rfc","prov.tax_id","prov.nombre_extendido","prov.paterno","prov.materno","prov.nombre")
          ->first();
          $prov_folio = $queryProvVinc ? 'PRV-'.$JwtAuth->generarFolio($queryProvVinc->folio).(!is_null($queryProvVinc->post_folio) ? '-'.$queryProvVinc->post_folio : '') : 'N/A';
          $prov_rfc_generico = $queryProvVinc ? $queryProvVinc->rfc_generico : 'N/A';
          $prov_rfc = $queryProvVinc && $queryProvVinc->rfc != NULL ? $JwtAuth->desencriptar($queryProvVinc->rfc) : 'N/A';
          $prov_tax_id = $queryProvVinc && $queryProvVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryProvVinc->tax_id) : 'N/A';
          $prov_nombre = $queryProvVinc ? ($queryProvVinc->nombre_extendido ? $JwtAuth->desencriptar($queryProvVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryProvVinc->paterno,$queryProvVinc->materno,$queryProvVinc->nombre)) : 'N/A';

          $acr_folio = 'N/A';
          $acr_rfc_generico = 'N/A';
          $acr_rfc = 'N/A';
          $acr_tax_id = 'N/A';
          $acr_nombre = 'N/A';
          $queryAcreeVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("fnzs_catalogo_acreedores AS catAcree", "catDeu.deu_acreedor_vinculado", "catAcree.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catAcree.acr_folio","catAcree.acr_post_folio","catAcree.acr_titular")
          ->first();
          $acr_folio = $queryAcreeVinc ? 'DEU-'.$JwtAuth->generarFolio($queryAcreeVinc->acr_folio).(!is_null($queryAcreeVinc->acr_post_folio) ? '-'.$queryAcreeVinc->acr_post_folio : '') : 'N/A';
          $acr_nombre = $queryAcreeVinc ? (!is_null($queryAcreeVinc->acr_titular) && $queryAcreeVinc->acr_titular != "" ? $JwtAuth->desencriptar($queryAcreeVinc->acr_titular) : '') : 'N/A';

          $queryRegFis = DB::table("sos_regimen_fiscal AS reg_fis")
          ->join("fnzs_catalogo_deudores AS catDeu", "reg_fis.id", "catDeu.deu_regimen_fiscal")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("reg_fis.token_regimen_fiscal","reg_fis.clave","reg_fis.descripcion")
          ->first();
          $deu_regimen_fiscal_token = $queryRegFis ? $queryRegFis->token_regimen_fiscal : ''; 
          $deu_regimen_fiscal_clave = $queryRegFis ? $queryRegFis->clave : 'N/A'; 
          $deu_regimen_fiscal_descripcion = $queryRegFis ? $queryRegFis->descripcion : 'N/A'; 

          $selectMovimientosDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
					->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

					$selectPagosDeudor = DB::table("fnzs_pagos_pago AS pay")
					->join("fnzs_catalogo_deudores AS catDeu", "pay.vinc_deudor", "=", "catDeu.id")
					->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $arrayForeach = array(
            "token_cat_deudores" => $vDeu->token_cat_deudores,
            "folio" => $folio_deu,

            "deu_rfc_generico" => !is_null($vDeu->deu_rfc_generico) ? $vDeu->deu_rfc_generico : 'N/A',
            "deu_rfc" => !is_null($vDeu->deu_rfc) ? $JwtAuth->desencriptar($vDeu->deu_rfc) : 'N/A',
            "deu_taxId" => !is_null($vDeu->deu_taxId) ? $JwtAuth->desencriptar($vDeu->deu_taxId) : 'N/A',
            "deu_titular" => !is_null($vDeu->deu_titular) ? $JwtAuth->desencriptar($vDeu->deu_titular) : 'N/A',
            "nombre_comercial" => !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : 'N/A',
            "cuenta_contable" => !empty($vDeu->deu_cuenta_contable) ? $vDeu->deu_cuenta_contable : 'N/A',

            "deuda_al_deudor" => $deudor_deuda_saldo > 0 ? "$".number_format($deudor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
            "eliminacion_activa" => $selectPersEmpEmi == 0 ? true : false,
            "data_detalle_vista" => false,

            "trab_folio" => $trab_folio,
            "trab_rfc_generico" => $trab_rfc_generico,
            "trab_rfc" => $trab_rfc,
            "trab_tax_id" => $trab_tax_id,
            "trab_nombre" => $trab_nombre,

            "prov_folio" => $prov_folio,
            "prov_rfc_generico" => $prov_rfc_generico,
            "prov_rfc" => $prov_rfc,
            "prov_tax_id" => $prov_tax_id,
            "prov_nombre" => $prov_nombre,

            "acr_folio" => $acr_folio,
            "acr_rfc_generico" => $acr_rfc_generico,
            "acr_rfc" => $acr_rfc,
            "acr_tax_id" => $acr_tax_id,
            "acr_nombre" => $acr_nombre,

            "regimen_fiscal_token" => $deu_regimen_fiscal_token,
            "regimen_fiscal_clave" => $deu_regimen_fiscal_clave,
            "regimen_fiscal_descripcion" => $deu_regimen_fiscal_descripcion,

            "data_detalle" => [],
            "data_reembolsos" => [],
            "data_pagos" => [],
            "data_anticipos" => [],
            "utilizado" => $selectMovimientosDeudor > 0 && $selectPagosDeudor > 0 ? true : false,
          );
          $arrayDeudores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'deudores' => $arrayDeudores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deudorDetalleInfoGeneral(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_deudores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_deudores = $request->input('token_cat_deudores');
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catDeu.token_cat_deudores",$token_cat_deudores)
      ->where("catDeu.deu_status",TRUE)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron deudores registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayDeudores = array();

        foreach ($queryDeudores as $vDeu) {
          date_default_timezone_set($vDeu->zona_horaria);
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');

          $queryTrabVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("vhum_empleados_catalogo AS catTrab", "catDeu.deu_empleado_vinculado", "catTrab.id")
          ->join("sos_personas AS trab", "catTrab.empleado_name", "trab.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catTrab.empleado_token","catTrab.folio_pers","trab.rfc_generico","trab.rfc","trab.tax_id","trab.nombre_extendido","trab.paterno","trab.materno","trab.nombre")
          ->first();
          $trab_token = $queryTrabVinc ? $queryTrabVinc->empleado_token : '';
          $trab_folio = $queryTrabVinc ? "TRB-".$JwtAuth->generarFolio($queryTrabVinc->folio_pers) : 'N/A';
          $trab_rfc_generico = $queryTrabVinc ? $queryTrabVinc->rfc_generico : 'N/A';
          $trab_rfc = $queryTrabVinc && $queryTrabVinc->rfc != NULL ? $JwtAuth->desencriptar($queryTrabVinc->rfc) : 'N/A';
          $trab_tax_id = $queryTrabVinc && $queryTrabVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryTrabVinc->tax_id) : 'N/A';
          $trab_nombre = $queryTrabVinc ? ($queryTrabVinc->nombre_extendido ? $JwtAuth->desencriptar($queryTrabVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryTrabVinc->paterno,$queryTrabVinc->materno,$queryTrabVinc->nombre)) : 'N/A';

          $queryProvVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("eegr_catalogo_proveedores AS catprov", "catDeu.deu_proveedor_vinculado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catprov.token_cat_proveedores","catprov.folio","catprov.post_folio","prov.rfc_generico","prov.rfc_generico","prov.rfc","prov.tax_id","prov.nombre_extendido","prov.paterno","prov.materno","prov.nombre")
          ->first();
          $prov_token = $queryProvVinc ? $queryProvVinc->token_cat_proveedores : '';
          $prov_folio = $queryProvVinc ? 'PRV-'.$JwtAuth->generarFolio($queryProvVinc->folio).(!is_null($queryProvVinc->post_folio) ? '-'.$queryProvVinc->post_folio : '') : 'N/A';
          $prov_rfc_generico = $queryProvVinc ? $queryProvVinc->rfc_generico : 'N/A';
          $prov_rfc = $queryProvVinc && $queryProvVinc->rfc != NULL ? $JwtAuth->desencriptar($queryProvVinc->rfc) : 'N/A';
          $prov_tax_id = $queryProvVinc && $queryProvVinc->tax_id != NULL ? $JwtAuth->desencriptar($queryProvVinc->tax_id) : 'N/A';
          $prov_nombre = $queryProvVinc ? ($queryProvVinc->nombre_extendido ? $JwtAuth->desencriptar($queryProvVinc->nombre_extendido) : $JwtAuth->desencriptarNombres($queryProvVinc->paterno,$queryProvVinc->materno,$queryProvVinc->nombre)) : 'N/A';

          $queryAcreeVinc = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("fnzs_catalogo_acreedores AS catAcree", "catDeu.deu_acreedor_vinculado", "catAcree.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("catAcree.token_cat_acreedores","catAcree.acr_folio","catAcree.acr_post_folio","catAcree.acr_titular")
          ->first();
          $acr_token = $queryAcreeVinc ? $queryAcreeVinc->token_cat_acreedores : '';
          $acr_folio = $queryAcreeVinc ? 'ACREE-'.$JwtAuth->generarFolio($queryAcreeVinc->acr_folio).(!is_null($queryAcreeVinc->acr_post_folio) ? '-'.$queryAcreeVinc->acr_post_folio : '') : 'N/A';
          $acr_nombre = $queryAcreeVinc ? (!is_null($queryAcreeVinc->acr_titular) && $queryAcreeVinc->acr_titular != "" ? $JwtAuth->desencriptar($queryAcreeVinc->acr_titular) : '') : 'N/A';
          $acr_rfc_generico = 'N/A';
          $acr_rfc = 'N/A';
          $acr_tax_id = 'N/A';

          $mailDeudor = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("teci_usuarios_catalogo AS users", "catDeu.id", "=", "users.deudor")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
          ->select("users.usuario_alias")
          ->first();

          $arrayForeach = array(
            "token_cat_deudores" => $vDeu->token_cat_deudores,
            "folio" => $folio_deu,
            "tipo" => $vDeu->deu_nacionalidad == 'MEX' ? 'nacional' : 'extranjero',
            "subtipo" => $vDeu->deu_fisica_moral == 'PF' ? 'deudorFisica' : 'deudorMoral',
            //"pais" => $vDeu->pais,
            "rfc_generico" => !is_null($vDeu->deu_rfc_generico) ? $vDeu->deu_rfc_generico : '',
            "rfc_ddr" => !is_null($vDeu->deu_rfc) ? $JwtAuth->desencriptar($vDeu->deu_rfc) : '',
            "tax_id_ddr" => !is_null($vDeu->deu_taxId) ? $JwtAuth->desencriptar($vDeu->deu_taxId) : '',
            "nombre" => !is_null($vDeu->deu_titular) ? $JwtAuth->desencriptar($vDeu->deu_titular) : '',
            "nombre_comercial" => !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : '',
            "cuenta_contable" => !empty($vDeu->deu_cuenta_contable) ? $vDeu->deu_cuenta_contable : '',
            //"utilizado" =>$vDeu->utilizado == TRUE ? true : false,
            //trabajador
              "trab_token" => $trab_token,
              "trab_folio" => $trab_folio,
              "trab_rfc_generico" => $trab_rfc_generico,
              "trab_rfc" => $trab_rfc,
              "trab_tax_id" => $trab_tax_id,
              "trab_nombre" => $trab_nombre,
            //proveedor
              "prov_token" => $prov_token,
              "prov_folio" => $prov_folio,
              "prov_rfc_generico" => $prov_rfc_generico,
              "prov_rfc" => $prov_rfc,
              "prov_tax_id" => $prov_tax_id,
              "prov_nombre" => $prov_nombre,
            //acreedor
              "acr_token" => $acr_token,
              "acr_folio" => $acr_folio,
              "acr_nombre" => $acr_nombre,
              "acr_rfc_generico" => $acr_rfc_generico,
              "acr_rfc" => $acr_rfc,
              "acr_tax_id" => $acr_tax_id,
            //trabajador
            "nombres_bloqueados" => $trab_token != '' || $prov_token != '' || $acr_token != '' ? true : false,
            "habilita_reembolsos" => $vDeu->deu_habilita_reembolsos ? true : false,
            "email" => $mailDeudor ? $JwtAuth->desencriptar($mailDeudor->usuario_alias) : ''
          );
          $arrayDeudores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'deudor' => $arrayDeudores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deudorDetalleInfoPagos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_deudores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que busca es invalida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cat_deudores = $request->input('token_cat_deudores');
      $arrayDeudores = array();

      $queryAcreedores = DB::table('fnzs_catalogo_acreedores AS catAcr')
      ->join('main_empresas AS emp', 'catAcr.acr_empresa', '=', 'emp.id')
      ->join('main_empresa_usuario AS empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', '=', 'users.id')
      ->leftJoin('eegr_catalogo_proveedores AS catprov', 'catAcr.acr_proveedor_vinculado', '=', 'catprov.id')
      ->leftJoin('sos_personas AS prv', 'catprov.proveedor', '=', 'prv.id')
      ->where([
        'catAcr.acr_status' => true,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select(
        'catAcr.*',
        'catAcr.acr_rfc as rfc_acr',
        'catAcr.acr_taxId as tax_id_acr',
        'catAcr.acr_titular as nombre_acr',
        'catAcr.acr_nombre_comercial as nombre_comercial_acr',
        'prv.rfc as rfc_prv',
        'prv.tax_id as tax_id_prv',
        'prv.nombre_extendido as nombre_extendido_prv',
        'prv.paterno as paterno_prv',
        'prv.materno as materno_prv',
        'prv.nombre as nombre_prv',
        'prv.nombre_com as nombre_com_prv',
        'catAcr.acr_cuenta_contable',
        'emp.*'
      )
      ->get();
      
      $listaAcreedores = $queryAcreedores->map(function($vAcr) use ($JwtAuth) {
        // Folio
        //da_te_default_timezone_set('UTC');
        $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');
        $vAcr->folio_acr = $folio_acr;

        // Determinar si usamos persona o proveedor
        //echo $vAcr->deudor;
        if (is_null($vAcr->acr_proveedor_vinculado)) {
          $vAcr->rfc_acr = $vAcr->rfc_acr ? $JwtAuth->desencriptar($vAcr->rfc_acr) : '';
          $vAcr->tax_id_acr = $vAcr->tax_id_acr ? $JwtAuth->desencriptar($vAcr->tax_id_acr) : '';
          $vAcr->acreedor_nombre = $vAcr->nombre_acr != '' ? $JwtAuth->desencriptar($vAcr->nombre_acr) : '';
          $vAcr->acreedor_nombre_comercial = $vAcr->nombre_comercial_acr ? $JwtAuth->desencriptar($vAcr->nombre_comercial_acr) : '';
        } else {
          $vAcr->rfc_ddr = $vAcr->rfc_prv ? $JwtAuth->desencriptar($vAcr->rfc_prv) : '';
          $vAcr->tax_id_ddr = $vAcr->tax_id_prv ? $JwtAuth->desencriptar($vAcr->tax_id_prv) : '';
          $vAcr->acreedor_nombre = $vAcr->nombre_extendido_prv != '' ? $JwtAuth->desencriptar($vAcr->nombre_extendido_prv) : $JwtAuth->desencriptarNombres($vAcr->paterno_prv, $vAcr->materno_prv, $vAcr->nombre_prv);
          $vAcr->acreedor_nombre_comercial = $vAcr->nombre_com_prv ? $JwtAuth->desencriptar($vAcr->nombre_com_prv) : '';
        }
        $vAcr->acreedor_nombre = strtolower(trim($vAcr->acreedor_nombre));
        $vAcr->acreedor_nombre_comercial = strtolower(trim($vAcr->acreedor_nombre_comercial));
        return $vAcr;
      });

      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catDeu.token_cat_deudores" => $token_cat_deudores,
        "catDeu.deu_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      foreach ($queryDeudores as $vDeu) {
        //da_te_default_timezone_set($vDeu->zona_horaria);

        $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');

        $estado_cuenta_deudor = array();
        $pagosRealizados = DB::table("fnzs_pagos_pago AS pago")
        ->join("fnzs_catalogo_deudores AS catDeu", "pago.vinc_deudor", "=", "catDeu.id")
        ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
        ->leftJoin("fnzs_catalogo_deudores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")//->where("mov.condicion_deu_mov","S")
        ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
        ->select([
          DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
          DB::raw("'---' AS doc_asociado"),
          DB::raw("'---' AS condi_deu_mov"),
          "pago.token_pagos AS token_movimiento",
          "pago.folio_pagos AS folio_movimiento",
          "pago.fecha_contabilizacion AS fecha_contabilizacion",
          "pago.observacionesPago AS observaciones",
          "pago.forma_pago_pago AS forma_pago_pago",
          "pago.monto_pago AS monto_movimiento",
          "pago.tipo_cambio AS tipo_cambio_movimiento",
          "pago.p_moneda AS moneda_movimiento",
          DB::raw("NULL AS movimiento_id"),
        ]);

        $pagosCancelados = DB::table("fnzs_pagos_pago AS pago")
        ->join("fnzs_catalogo_deudores AS catDeu", "pago.vinc_deudor", "=", "catDeu.id")
        ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
        ->leftJoin("fnzs_catalogo_deudores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
        ->where("pago.pago_cancelado", TRUE)
        ->where("catDeu.token_cat_deudores", $vDeu->token_cat_deudores)
        ->select([
          DB::raw("'PAGO-CANCELADO' AS tipo_registro_e_cuenta"),
          "pago.id AS doc_asociado",
          DB::raw("'---' AS condi_deu_mov"),
          "pago.token_pagos AS token_movimiento",
          "pago.pago_folio_cancelacion AS folio_movimiento",
          "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
          "pago.pago_comentarios_cancelacion AS observaciones",
          "pago.forma_pago_pago AS forma_pago_pago",
          "pago.monto_pago AS monto_movimiento",
          "pago.tipo_cambio AS tipo_cambio_movimiento",
          "pago.p_moneda AS moneda_movimiento",
          DB::raw("NULL AS movimiento_id"),
        ]);

        $movimDEUWithPago = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
        ->join("fnzs_catalogo_deudores AS deudor", "mov.vinc_deudor", "=", "deudor.id")
        //->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
        //->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
        ->whereIn('mov.id', function ($query) {
          $query->select('vinc.mov_realizado')->from('fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc')
                ->join('fnzs_pagos_pago AS pago','vinc.pago_vinculado','=','pago.id');
        })
        ->whereNull('mov.deu_mov_asociado')
        ->where("deudor.token_cat_deudores",$vDeu->token_cat_deudores)
        ->select([
          DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
          DB::raw("'---' AS doc_asociado"),
          "mov.condicion_deu_mov AS condi_deu_mov",
          "mov.token_deu_mov AS token_movimiento",
          "mov.folio_deu_mov AS folio_movimiento",
          "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
          "mov.deu_observaciones_mov AS observaciones",
          DB::raw("'---' AS forma_pago_pago"),
          "mov.deu_monto_mov AS monto_movimiento",
          "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
          "mov.deu_mov_moneda AS moneda_movimiento",
          "mov.id AS movimiento_id",
        ]);

        $movimDEUCancelWithPago = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
        ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
        //->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
        //->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
        ->whereIn('mov.id', function ($query) {
          $query->select('vinc.mov_realizado')->from('fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc')
                ->join('fnzs_pagos_pago AS pago','vinc.pago_vinculado','=','pago.id');
        })
        ->where("mov.deu_mov_cancelado", FALSE)
        ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
        ->whereNotNull("mov.deu_mov_asociado")
        ->whereIn('mov.deu_mov_asociado', function ($query) {
          $query->select('id')->from('fnzs_catalogo_deudores_movimientos')
          ->where("deu_mov_cancelado", TRUE);
        })
        ->select([
          DB::raw("'MOVIMIENTO-CANCELADO' AS tipo_registro_e_cuenta"),
          "mov.deu_mov_asociado AS doc_asociado",
          "mov.condicion_deu_mov AS condi_deu_mov",
          "mov.token_deu_mov AS token_movimiento",
          "mov.folio_deu_mov AS folio_movimiento",
          "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
          "mov.deu_observaciones_mov AS observaciones",
          DB::raw("'---' AS forma_pago_pago"),
          "mov.deu_monto_mov AS monto_movimiento",
          "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
          "mov.deu_mov_moneda AS moneda_movimiento",
          "mov.id AS movimiento_id",
        ]);

        $movimDEUWithoutPago = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
        ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
        ->whereNotIn('mov.id', function ($query) {
          $query->select('vinc.mov_realizado')->from('fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc')
                ->join('fnzs_pagos_pago AS pago','vinc.pago_vinculado','=','pago.id');
        })
        ->whereNull('mov.deu_mov_asociado')
        ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
        ->select([
          DB::raw("'MOVIMIENTO_PURO' AS tipo_registro_e_cuenta"),
          DB::raw("'---' AS doc_asociado"),
          "mov.condicion_deu_mov AS condi_deu_mov",
          "mov.token_deu_mov AS token_movimiento",
          "mov.folio_deu_mov AS folio_movimiento",
          "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
          "mov.deu_observaciones_mov AS observaciones",
          DB::raw("'---' AS forma_pago_pago"),
          "mov.deu_monto_mov AS monto_movimiento",
          "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
          "mov.deu_mov_moneda AS moneda_movimiento",
          "mov.id AS movimiento_id",
        ]);

        $movimDEUCancelWithoutPago = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
        ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
        ->whereNotIn('mov.id', function ($query) {
          $query->select('vinc.mov_realizado')->from('fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc')
                ->join('fnzs_pagos_pago AS pago','vinc.pago_vinculado','=','pago.id');
        })
        ->where("mov.deu_mov_cancelado", FALSE)
        ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
        ->whereIn('mov.deu_mov_asociado', function ($query) {
          $query->select('id')->from('fnzs_catalogo_deudores_movimientos')
          ->where("deu_mov_cancelado", TRUE);
        })
        ->select([
          DB::raw("'MOVIMIENTO_PURO_CANCELADO' AS tipo_registro_e_cuenta"),
          "mov.deu_mov_asociado AS doc_asociado",
          "mov.condicion_deu_mov AS condi_deu_mov",
          "mov.token_deu_mov AS token_movimiento",
          "mov.folio_deu_mov AS folio_movimiento",
          "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
          "mov.deu_observaciones_mov AS observaciones",
          DB::raw("'---' AS forma_pago_pago"),
          "mov.deu_monto_mov AS monto_movimiento",
          "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
          "mov.deu_mov_moneda AS moneda_movimiento",
          "mov.id AS movimiento_id",
        ]);

        $unionEstadoDeCuenta = $pagosRealizados->unionAll($pagosCancelados)->unionAll($movimDEUWithPago)->unionAll($movimDEUCancelWithPago)->unionAll($movimDEUWithoutPago)->unionAll($movimDEUCancelWithoutPago);

        $queryEstadoDeCuenta = DB::table(DB::raw("({$unionEstadoDeCuenta->toSql()}) as estado_cuenta"))
        ->mergeBindings($unionEstadoDeCuenta)
        ->orderBy("fecha_contabilizacion", "asc")
        ->get();

        $idPagos = $queryEstadoDeCuenta->pluck('token_movimiento')->filter()->unique()->toArray();
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
        ->get()
        ->keyBy('id');

        $contador = 0;
        foreach ($queryEstadoDeCuenta as $vECuenta) {
          $token_movimiento = $vECuenta->token_movimiento;
          $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';
          $f_m_pago_cfdi = "";
					$forma_pago_registrada = $vECuenta->forma_pago_pago !== '---' ? $vECuenta->forma_pago_pago." - ".$JwtAuth->getFormasPagoAPI($vECuenta->forma_pago_pago) : "";

          $cfdi_comprobante_metodo_de_pago = "";
          if ($vECuenta->tipo_registro_e_cuenta == "PAGO" || $vECuenta->tipo_registro_e_cuenta == "PAGO-CANCELADO") {
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
            case 'PAGO':
              $folio_e_cuenta = "PAGO-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
              break;
            case 'PAGO-CANCELADO':
              $folio_e_cuenta = "PCAN-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
              break;
            case 'MOVIMIENTO':
              $folio_e_cuenta = "DEUMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
              break;
            case 'MOVIMIENTO_PURO':
              $folio_e_cuenta = "DEUMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
              break;
            case 'MOVIMIENTO-CANCELADO':
              $folio_e_cuenta = "DEUMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
              break;
            case 'MOVIMIENTO_PURO_CANCELADO':
              $folio_e_cuenta = "DEUMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
              break;
            default:
              $folio_e_cuenta = "";
              break;
          }

          if (in_array($vECuenta->tipo_registro_e_cuenta, ['MOVIMIENTO_PURO', 'MOVIMIENTO_PURO_CANCELADO'])) {
            $e_cuenta_debe = $vECuenta->condi_deu_mov == "R" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->condi_deu_mov == "S" ? $vECuenta->monto_movimiento : 0;
          } else {
            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" || $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO-CANCELADO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "PAGO" || $vECuenta->tipo_registro_e_cuenta == "PAGO-CANCELADO" ? $vECuenta->monto_movimiento : 0;
          }

          $e_cuenta_saldo = count($estado_cuenta_deudor) == 0 ? $e_cuenta_debe - $e_cuenta_haber : ($estado_cuenta_deudor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_debe) - $e_cuenta_haber;
          
          $cancelacion_doc_anterior = "";
          switch ($vECuenta->tipo_registro_e_cuenta) {
            case 'PAGO-CANCELADO':
              $cancelacion_doc_anterior = "PAGO-".$JwtAuth->generarFolio(
                DB::table("fnzs_pagos_pago")
                ->where("id",$vECuenta->doc_asociado)
                ->value("folio_pagos")
              );
              break;
            case 'MOVIMIENTO-CANCELADO':
              $cancelacion_doc_anterior = "DEUMOV-".$JwtAuth->generarFolio(
                DB::table("fnzs_catalogo_deudores_movimientos")
                ->where("id",$vECuenta->doc_asociado)
                ->value("folio_deu_mov")
              );
              break;
            default:
              $cancelacion_doc_anterior = "";
              break;
          }

          //echo count($estado_cuenta_acreedor);
          $row_cuenta_estado = array(
            "contador" => $contador,
            "tipo_registro_e_cuenta" => $vECuenta->tipo_registro_e_cuenta,
            "folio_e_cuenta" => $folio_e_cuenta,
            "movimiento_token" => $token_movimiento,
            //cancelaciones
            "cancelacion_doc_anterior" => $cancelacion_doc_anterior,

            //neutrales
            "fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? $JwtAuth->mostrarUnixAFechaMexico($vECuenta->fecha_contabilizacion) : "",
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
            "estado_cuenta_saldo_format" => "$".number_format($e_cuenta_saldo,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.', ',')." $vECuenta->moneda_movimiento",
          );
          $estado_cuenta_deudor[] = $row_cuenta_estado;
          ++$contador;
        }

        $deudor_deuda_total = 0;
        $deudor_deuda_restante = 0;
        $deudor_deuda_debe = 0;
        $deudor_deuda_haber = 0;
        $deudor_deuda_saldo = 0;
        $pagos_deudor_moneda = "";

        for ($i=0; $i < count($estado_cuenta_deudor); $i++) { 
          $pagos_deudor_moneda = $vECuenta->moneda_movimiento;
          $deudor_deuda_debe = $deudor_deuda_debe + floatval($estado_cuenta_deudor[$i]["estado_cuenta_debe"] ?? 0);
          $deudor_deuda_haber = $deudor_deuda_haber + floatval($estado_cuenta_deudor[$i]["estado_cuenta_haber"] ?? 0);
        }
        $deudor_deuda_saldo = floatval($deudor_deuda_debe ?? 0) - floatval($deudor_deuda_haber ?? 0);

        $pagos_deudor_list = array();
        $queryPagosDeudor = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_catalogo_deudores AS catDeu", "pay.vinc_deudor", "=", "catDeu.id")
        ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->get();
        foreach ($queryPagosDeudor as $vPayDone) {
          $payment_observaciones = !is_null($vPayDone->observacionesPago) ? $JwtAuth->desencriptar($vPayDone->observacionesPago) : '';
          $forma_pago_registrada = $vPayDone->forma_pago_pago;

          $forma_pago_vinculada = "";
          $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
          ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
          ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
          ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
          ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
          ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
          ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
          ->where("payment.token_pagos", $vPayDone->token_pagos)
          ->select("r_caj.*","r_cuent.*","r_moned.*")->get();
          //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();

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
          $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
          ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
          ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
          ->where("payment.token_pagos", $vPayDone->token_pagos)
          ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

          $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

          $movs_realizados = 0;
          $pago_movimientos_realizados = [];
          $queryMovimientosDone = DB::table("fnzs_catalogo_deudores_movimientos AS deumov")
          ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deumov.id", "=","vinc.mov_realizado")
          ->join("fnzs_pagos_pago AS pay", "vinc.pago_vinculado", "=", "pay.id")
          ->where("pay.token_pagos",$vPayDone->token_pagos)->get();
          foreach ($queryMovimientosDone as $vMovDone) {
            $movs_realizados += $vMovDone->monto_pago;
          }
          
          $importe_pago = $vPayDone->monto_pago * $vPayDone->tipo_cambio;
          $pago_restante = count($queryMovimientosDone) > 0 ? ($importe_pago) - $movs_realizados : $importe_pago;

          $queryDocAnterior = DB::table("fnzs_pagos_orden AS order")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
          ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
          ->where("payment.token_pagos", $vPayDone->token_pagos)
          ->select("order.folio_ordenPago","order.fecha_contabilizacion_ordenPago")
          ->first();
          $doc_anterior_folio = $queryDocAnterior ? "ORDP-".$JwtAuth->generarFolio($queryDocAnterior->folio_ordenPago) : '';
          $doc_anterior_fecha_contabilizacion = $queryDocAnterior ? $JwtAuth->mostrarUnixAFechaMexico($queryDocAnterior->fecha_contabilizacion_ordenPago) : '';

          $row_pagos_realizados = array(
            "token_pagos" => $vPayDone->token_pagos,
            "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($vPayDone->folio_pagos),
            "status_pago" => $vPayDone->status_pagos ? true : false,
            "doc_anterior_folio" => $doc_anterior_folio,
            "doc_anterior_fecha_contabilizacion" => $doc_anterior_fecha_contabilizacion,
            "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? $JwtAuth->mostrarUnixAFechaMexico($vPayDone->fecha_contabilizacion) : "",
            "forma_pago_vinculada" => $forma_pago_vinculada,
            "forma_pago_cfdi" => $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada),
            "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
            "concepto" => !empty($vPayDone->concepto) ? $JwtAuth->desencriptar($vPayDone->concepto) : '',
            "monto_pago" => "$".number_format($vPayDone->monto_pago * $vPayDone->tipo_cambio,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
            "p_moneda" => $vPayDone->p_moneda,
            "tipo_cambio" => "$".number_format($vPayDone->tipo_cambio,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.',',')." $vPayDone->p_moneda",
            "observacionesPago" => $payment_observaciones,
            "pago_restante" => $pago_restante,
            "importe_restante" => number_format($pago_restante,$JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ''),
            "importe_por_pagar" => "0.00",
            "debe_simple" => number_format($pago_restante,$JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ''),
            "debe_format" => "$".number_format($pago_restante,$JwtAuth->getMonedaAPI($vPayDone->p_moneda), '.', ',')." ".$vPayDone->p_moneda,
          );
          if ($pago_restante > 0) {
            $pagos_deudor_list[] = $row_pagos_realizados;
          }
        }

        $lista_movimientos_realizados = [];
        $queryMovimientosDone = DB::table("fnzs_catalogo_deudores_movimientos AS deumov")
        ->join("fnzs_catalogo_deudores AS catDeu","deumov.vinc_deudor", "=","catDeu.id")
        ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc", "deumov.id", "=","vinc.mov_realizado")
        ->join("fnzs_pagos_pago AS pay", "vinc.pago_vinculado", "=", "pay.id")
        ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->get();
        foreach ($queryMovimientosDone as $vMovDone) {
          $queryPersResponsable = DB::table("fnzs_catalogo_deudores_movimientos AS movim")
          ->join("vhum_empleados_catalogo AS pers", "movim.deu_personal_mov", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "people.id")
          ->where('movim.token_deu_mov',$vMovDone->token_deu_mov)
          ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
          ->first();
          $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
          $pers_responsmov_folio = $queryPersResponsable ? "TRB-".$JwtAuth->generarFolio($queryPersResponsable->folio_pers) : "";
          $pers_responsmov_name = $queryPersResponsable ? $JwtAuth->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

          $queryCaja = DB::table("fnzs_catalogos_caja AS caj")
          ->join("fnzs_catalogo_deudores_movimientos_cajas AS mov_caj", "caj.id", "mov_caj.caja_relacionada")
          ->join("fnzs_catalogo_deudores_movimientos AS movim", "mov_caj.mov_realizado", "movim.id")
          ->where('movim.token_deu_mov',$vMovDone->token_deu_mov)
          ->select('caj.token_caja','caj.no_caja','caj.alias_caja')
          ->first();

          $queryCuenta = DB::table("fnzs_catalogos_cuentas AS cuent")
          ->join("teci_bancos AS bank", "cuent.banco", "bank.id")
          ->join("fnzs_catalogo_deudores_movimientos_cuentas AS mov_cuent", "cuent.id", "mov_cuent.cuenta_relacionada")
          ->join("fnzs_catalogo_deudores_movimientos AS movim", "mov_cuent.mov_realizado", "movim.id")
          ->where('movim.token_deu_mov',$vMovDone->token_deu_mov)
          ->select('cuent.token_cuenta','cuent.folio_cuenta','cuent.cuenta')
          ->first();

          $queryMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
          //->join("teci_plataformas_digitales AS pdig", "moned.monedero", "pdig.id")
          ->join("fnzs_catalogo_deudores_movimientos_monederos AS mov_mon", "moned.id", "mov_mon.moned_relacionado")
          ->join("fnzs_catalogo_deudores_movimientos AS movim", "mov_mon.mov_realizado", "movim.id")
          ->where('movim.token_deu_mov',$vMovDone->token_deu_mov)
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
            $movimiento_folio = "CUENTM-".$JwtAuth->generarFolio($queryMonedero->folio_cuentmon) ;
            $movimiento_name = $queryMonedero->cuenta;
          } else {
            $movimiento_tipo = "N/A";
            $movimiento_token = "N/A";
            $movimiento_folio = "N/A";
            $movimiento_name = "N/A";
          }

          $mainMovs = DB::table("fnzs_actividad_movimientos AS movAct")
          ->join("fnzs_catalogo_deudores_movimientos AS movim", "movAct.acreedor_movimiento", "movim.id")
          ->where('movim.token_deu_mov',$vMovDone->token_deu_mov)
          ->select('movAct.tipo_movimiento','movAct.subtipo_movimiento')
          ->first();

          $row_mov_acr = array(
            "token_deu_mov" => $vMovDone->token_deu_mov,
            "folio_deu_mov" => "ACRMOV-".$JwtAuth->generarFolio($vMovDone->folio_deu_mov),
            "deu_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vMovDone->deu_fecha_contabilizacion),
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
            "monto_aplicado" => "$".number_format($vMovDone->deu_monto_mov,$JwtAuth->getMonedaAPI($vPayDone->p_moneda),'.', ',')." $vPayDone->p_moneda",
          );
          $lista_movimientos_realizados[] = $row_mov_acr;
        }

        $_deudor_nombre = $JwtAuth->desencriptar($vDeu->deu_titular);
        $_acreedor_vinculado = $listaAcreedores->first(function($vAcr) use ($_deudor_nombre) {return $vAcr->acreedor_nombre === strtolower(trim($_deudor_nombre));});
        //echo $_deudor_vinculado->deudor_nombre;

        $arrayForeach = array(
          "token_cat_deudores" => $vDeu->token_cat_deudores,
          "folio" => $folio_deu,
          "tipo" => $vDeu->deu_nacionalidad == 'MEX' ? 'Nacional' : 'Extranjero',
          "subtipo" => $vDeu->deu_fisica_moral == 'PF' ? 'Fisica' : 'Moral',
          //"pais" => $vDeu->pais,
          "rfc_ddr" => $vDeu->deu_rfc	!= NULL ? $JwtAuth->desencriptar($vDeu->deu_rfc) : '',
          "tax_id_ddr" => $vDeu->deu_taxId != NULL ? $JwtAuth->desencriptar($vDeu->deu_taxId) : '',
          "nombre" => $_deudor_nombre,
          "nombre_comercial" => !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : '',
          "cuenta_contable" => !empty($vDeu->cuenta_contable) ? $JwtAuth->desencriptar($vDeu->cuenta_contable) : '',
          "acreedor_vinculado_token" => $_acreedor_vinculado ? $_acreedor_vinculado->token_cat_acreedores : '',
          "acreedor_vinculado_folio" => $_acreedor_vinculado ? $_acreedor_vinculado->folio_acr : '',
          "acreedor_vinculado_nombre" => $_acreedor_vinculado ? $_acreedor_vinculado->acreedor_nombre : '',
          
          "deuda_del_deudor" => $deudor_deuda_total > 0 ? "$".number_format($deudor_deuda_total,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
          "deu_total_debe" => $deudor_deuda_debe > 0 ? "$".number_format($deudor_deuda_debe,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
          "deu_total_haber" => $deudor_deuda_haber > 0 ? "$".number_format($deudor_deuda_haber,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
          "deu_total_saldo_simple" => $deudor_deuda_saldo > 0 ? $deudor_deuda_saldo : 0,
          "deu_total_saldo" => $deudor_deuda_saldo > 0 ? "$".number_format($deudor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
          "deu_total_saldo_aplicar" => 0,
          "deu_total_saldo_restante_simple" => $deudor_deuda_saldo > 0 ? $deudor_deuda_saldo : 0,
          "deu_total_saldo_restante" => $deudor_deuda_saldo > 0 ? "$".number_format($deudor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_deudor_moneda),'.', ',')." $pagos_deudor_moneda" : "$0.00 MXN",
          "habilita_reembolsos" => $vDeu->deu_habilita_reembolsos ? true : false,
          "estado_de_cuenta" => $estado_cuenta_deudor,
          "pagos_deudor_list" => $pagos_deudor_list,
          "movimientos_realizados" => $lista_movimientos_realizados,
        );
        $arrayDeudores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'deudor' => $arrayDeudores,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaDeudor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_deudores' => 'required|string',
      'tipo' => 'required|string',
      'subtipo' => 'required|string',
      'rfc' => 'nullable|string',
      'taxID' => 'nullable|string',
      'nombre' => 'required|string',
      'nombre_comercial' => 'required|string',
      'cuenta_contable' => 'nullable|string',
      'habilita_reembolsos' => 'nullable|boolean',
      'regimen_fiscal' => 'nullable|string',
      'trabajador_vinculado' => 'nullable|string',
      'proveedor_vinculado' => 'nullable|string',
      'acreedor_vinculado' => 'nullable|string',
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
      $token_cat_deudores = $request->input('token_cat_deudores');
      $tipo = $request->input('tipo');
      $subtipo = $request->input('subtipo');
      $rfc = $request->input('rfc');
      $taxID = $request->input('taxID');
      $nombre = $request->input('nombre');
      $nombre_comercial = $request->input('nombre_comercial');

      $cuenta_contable = $request->input('cuenta_contable');
      $habilita_reembolsos = $request->input('habilita_reembolsos');
      $regimen_fiscal = $request->input('regimen_fiscal');
      $trabajador_vinculado = $request->input('trabajador_vinculado');
      $proveedor_vinculado = $request->input('proveedor_vinculado');
      $acreedor_vinculado = $request->input('acreedor_vinculado');
            
      $validar_tipo = isset($tipo) && !empty($tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo);
      $validar_subtipo = isset($subtipo) && !empty($subtipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $subtipo);
      $validar_rfc = isset($rfc) && !empty($rfc) && preg_match($JwtAuth->filtroRfc(), $rfc);
      $validar_taxID = isset($taxID) && !empty($taxID) && preg_match($JwtAuth->filtroRfc(), $taxID);
      $validar_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre);
      $validar_nombre_comercial = isset($nombre_comercial) && !empty($nombre_comercial) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre_comercial);
      //$validar_email = isset($email) && !empty($email);
      //$validar_email_encrypt = isset($email_encrypt) && !empty($email_encrypt);
      $validar_trabajador_vinculado = isset($trabajador_vinculado) && !empty($trabajador_vinculado);
      $validar_proveedor_vinculado = isset($proveedor_vinculado) && !empty($proveedor_vinculado);
      $validar_acreedor_vinculado = isset($acreedor_vinculado) && !empty($acreedor_vinculado);

      if ($validar_tipo && $validar_subtipo && $validar_nombre && $validar_nombre_comercial) {
        $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
        ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("catDeu.token_cat_deudores",$token_cat_deudores)
        ->where("emp.empresa_token",$empresa)
        ->where("users.usuario_token",$usuario)
        ->get();
        
        foreach ($queryDeudores as $vDeu) {
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->folio).(!is_null($vDeu->post_folio) ? '-'.$vDeu->post_folio : '');

          $sql_tipo = $tipo == 'nacional' ? 'MEX' : 'EXT';
          $sql_subtipo = $subtipo == 'deudorFisica' ? 'PF' : 'PM';
          $sql_rfc = $validar_rfc ? $JwtAuth->encriptar(strtoupper($rfc)) : NULL;
          $sql_taxID = $validar_taxID ? $JwtAuth->encriptar(strtoupper($taxID)) : NULL;
          $sql_nombre = $JwtAuth->encriptar($nombre);
          $sql_nombre_comercial = $JwtAuth->encriptar($nombre_comercial);

          $deudor_empleado = $validar_trabajador_vinculado ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$trabajador_vinculado)->value("id") : NULL;
          $deudor_proveedor = $validar_proveedor_vinculado ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$proveedor_vinculado)->value("id") : NULL;
          $deudor_acreedor = $validar_acreedor_vinculado ? DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor_vinculado)->value("id") : NULL;
          $deudor_regimen_fiscal = $regimen_fiscal != '' ? DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $regimen_fiscal)->value("id") : NULL;

          $update_deu = DB::table("fnzs_catalogo_deudores")
          ->where("token_cat_deudores",$vDeu->token_cat_deudores)
          ->limit(1)->update(
            array(
              "deu_rfc" => $sql_rfc,
              "deu_taxId" => $sql_taxID,
              "deu_nacionalidad" => $sql_tipo,
              "deu_titular" => $sql_nombre,
              "deu_nombre_comercial" => $sql_nombre_comercial,

              "deu_cuenta_contable" => $cuenta_contable,
              "deu_habilita_reembolsos" => $habilita_reembolsos ? TRUE : FALSE,
              "deu_fisica_moral" => $sql_subtipo,
              "deu_empleado_vinculado" => $deudor_empleado,
              "deu_proveedor_vinculado" => $deudor_proveedor,
              "deu_acreedor_vinculado" => $deudor_acreedor,
              "deu_regimen_fiscal" => $deudor_regimen_fiscal,
            )
          );

          if ($update_deu) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu ha sido actualizado",
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu no actualizado, intente más tarde o comuniquese a soporte",
            );
          }
        }
      } else {
        $mensaje_error = '';
        if (!$validar_tipo) {$mensaje_error = 'Error en tipo de deudor, intente más tarde o comuniquese a soporte';}
        if (!$validar_subtipo) {$mensaje_error = 'Error en tipo de persona, intente más tarde o comuniquese a soporte';}
        //if (!$validar_rfc) {$mensaje_error = 'Error en , intente más tarde o comuniquese a soporte';}
        //if (!$validar_taxID) {$mensaje_error = 'Error en , intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre) {$mensaje_error = 'Error en Nombre / Razón social, intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre_comercial) {$mensaje_error = 'Error en Nombre Comercial,intente más tarde o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaDeudorPapelera(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_deudores' => 'required|string'
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
      $token_cat_deudores = $request->input('token_cat_deudores');
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catDeu.deu_status",TRUE)
      ->where("catDeu.token_cat_deudores",$token_cat_deudores)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Deudor no se encuentra registrado, verifique su información o comuniquese a soporte'
        );
      } else {
        foreach ($queryDeudores as $vDeu) {
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');

          $deleteDeudor = DB::table("fnzs_catalogo_deudores")
          ->where("token_cat_deudores",$vDeu->token_cat_deudores)
          ->limit(1)->update(array("deu_status" => FALSE,"deu_fecha_delete" => time()));

          if ($deleteDeudor) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu ha sido eliminado",
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu no eliminado, intente más tarde o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deudoresCatEliminados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
    ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where("catDeu.deu_status",FALSE)
    ->where("emp.empresa_token",$empresa)
    ->where("users.usuario_token",$usuario)
    ->get();

    if ($queryDeudores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron deudores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayDeudores = array();
      
      foreach ($queryDeudores as $vDeu) {
        date_default_timezone_set($vDeu->zona_horaria);
        $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');

        $arrayForeach = array(
          "token_cat_deudores" => $vDeu->token_cat_deudores,
          "folio" => $folio_deu,
          "fecha_delete_deudor" => gmdate('Y-m-d H:i:s', $vDeu->deu_fecha_delete),
          //"pais" => $vDeu->pais,
          "deu_rfc_generico" => !is_null($vDeu->deu_rfc_generico) ? $vDeu->deu_rfc_generico : 'N/A',
          "deu_rfc" => !is_null($vDeu->deu_rfc) ? $JwtAuth->desencriptar($vDeu->deu_rfc) : 'N/A',
          "deu_taxId" => !is_null($vDeu->deu_taxId) ? $JwtAuth->desencriptar($vDeu->deu_taxId) : 'N/A',
          "deu_titular" => !is_null($vDeu->deu_titular) ? $JwtAuth->desencriptar($vDeu->deu_titular) : 'N/A',
          "nombre_comercial" => !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != '' ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : 'N/A',
          "cuenta_contable" => !empty($vDeu->deu_cuenta_contable) ? $vDeu->deu_cuenta_contable : 'N/A'
        );
        $arrayDeudores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'deudores' => $arrayDeudores,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarDeudor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_deudores' => 'required|string'
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
      $token_cat_deudores = $request->input('token_cat_deudores');
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catDeu.deu_status",FALSE)
      ->where("catDeu.token_cat_deudores",$token_cat_deudores)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Deudor no se encuentra registrado, verifique su información o comuniquese a soporte'
        );
      } else {
        foreach ($queryDeudores as $vDeu) {
          date_default_timezone_set($vDeu->zona_horaria);
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');

          $deleteDeudor = DB::table("fnzs_catalogo_deudores")
          ->where("token_cat_deudores",$vDeu->token_cat_deudores)
          ->limit(1)->update(array("deu_status" => TRUE,"deu_fecha_delete" => NULL));

          if ($deleteDeudor) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu ha sido restaurado",
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu no restaurado, intente más tarde o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaDeudorPermanente(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_deudores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_deudores = $request->input('token_cat_deudores');
      
      $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
      ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catDeu.deu_status",FALSE)
      ->where("catDeu.token_cat_deudores",$token_cat_deudores)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->get();

      if ($queryDeudores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Deudor no se encuentra registrado, verifique su información o comuniquese a soporte'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();        
        foreach ($queryDeudores as $vDeu) {
          $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');

          $selectMovimientosDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
          ->join("fnzs_catalogo_deudores AS catDeu", "mov.vinc_deudor", "=", "catDeu.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          $selectPagosDeudor = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_catalogo_deudores AS catDeu", "pay.vinc_deudor", "=", "catDeu.id")
          ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)->count();

          if ($selectMovimientosDeudor == 0 && $selectPagosDeudor == 0) {
            $queryUsersDeudor = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS catTrab", "users.empleado", "=", "catTrab.id")
            ->join("fnzs_catalogo_deudores AS catDeu", "users.deudor", "=", "catDeu.id")
            ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
            ->get();

            if (count($queryUsersDeudor) == 0) {
              DB::table("teci_usuarios_catalogo AS users")
              ->join("fnzs_catalogo_deudores AS catDeu", "users.deudor", "catDeu.id")
              ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
              ->limit(1)->delete();
            } else {
              DB::table("teci_usuarios_catalogo AS users")
              ->join("fnzs_catalogo_deudores AS catDeu", "users.deudor", "catDeu.id")
              ->where("catDeu.token_cat_deudores",$vDeu->token_cat_deudores)
              ->limit(1)->update(array("users.deudor" => NULL));
            }

            $deleteDeudor = DB::table("fnzs_catalogo_deudores")
            ->where("deu_status",FALSE)
            ->where("token_cat_deudores",$vDeu->token_cat_deudores)
            ->limit(1)->delete();

            if ($deleteDeudor) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Deudor con folio $folio_deu ha sido eliminado",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Deudor con folio $folio_deu no eliminado, intente más tarde o comuniquese a soporte",
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Deudor con folio $folio_deu no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarDeudor(Request $request) {
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
      
      'tipo' => 'required|string',
      'subtipo' => 'required|string',
      'rfc' => 'nullable|string',
      'taxID' => 'nullable|string',
      'nombre' => 'required|string',
      'nombre_comercial' => 'required|string',
      'trabajador_vinculado' => 'nullable|string',
      'proveedor_vinculado' => 'nullable|string',
      'acreedor_vinculado' => 'nullable|string',
      'email' => 'nullable|string',
      'email_encrypt' => 'nullable|string',
      'access_code' => 'nullable|string',
      'password_code' => 'nullable|string',
      'habilita_reembolsos' => 'nullable|boolean',
      'cuenta_contable' => 'nullable|string',
      'regimen_fiscal' => 'nullable|string'
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
      $tipo = $request->input('tipo');
      $subtipo = $request->input('subtipo');
      $rfc = $request->input('rfc');
      $taxID = $request->input('taxID');
      $nombre = $request->input('nombre');
      $nombre_comercial = $request->input('nombre_comercial');
      $trabajador_vinculado = $request->input('trabajador_vinculado');
      $proveedor_vinculado = $request->input('proveedor_vinculado');
      $acreedor_vinculado = $request->input('acreedor_vinculado');
      $email = $request->input('email');
      $email_encrypt = $request->input('email_encrypt');
      $access_code = $request->input('access_code');
      $password_code = $request->input('password_code');
      $habilita_reembolsos = $request->input('habilita_reembolsos');
      $cuenta_contable = $request->input('cuenta_contable');
      $regimen_fiscal = $request->input('regimen_fiscal');

      $validar_tipo = isset($tipo) && !empty($tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo);
      $validar_subtipo = isset($subtipo) && !empty($subtipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $subtipo);
      $validar_rfc = isset($rfc) && !empty($rfc) && preg_match($JwtAuth->filtroRfc(), $rfc);
      $validar_taxID = isset($taxID) && !empty($taxID) && preg_match($JwtAuth->filtroRfc(), $taxID);
      $validar_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre);
      $validar_nombre_comercial = isset($nombre_comercial) && !empty($nombre_comercial) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre_comercial);
      $validar_email = isset($email) && !empty($email);
      $validar_email_encrypt = isset($email_encrypt) && !empty($email_encrypt);
      $validar_trabajador_vinculado = isset($trabajador_vinculado) && !empty($trabajador_vinculado);
      $validar_proveedor_vinculado = isset($proveedor_vinculado) && !empty($proveedor_vinculado);
      $validar_acreedor_vinculado = isset($acreedor_vinculado) && !empty($acreedor_vinculado);

      if ($validar_tipo && $validar_subtipo && $validar_nombre && $validar_nombre_comercial) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.jerarquia_main,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token = ?", [$empresa, $usuario]);

        if (count($queryEmp) > 0) {
          foreach ($queryEmp as $vEmp) {
            date_default_timezone_set($vEmp->zona_horaria);
            $autorizado = FALSE;
            $autorizacion_fecha = NULL;
            $autorizacion_user = NULL;
            $folio_nuevo = NULL;
            $post_folio =  NULL;
            $folio_temporal = NULL;

            $sql_tipo = $tipo == 'nacional' ? 'MEX' : 'EXT';
            $sql_subtipo = $subtipo == 'deudorFisica' ? 'PF' : 'PM';
            $sql_rfc = $validar_rfc ? $JwtAuth->encriptar(strtoupper($rfc)) : NULL;
            $sql_taxID = $validar_taxID ? $JwtAuth->encriptar(strtoupper($taxID)) : NULL;
            $sql_nombre = $JwtAuth->encriptar($nombre);
            $sql_nombre_comercial = $JwtAuth->encriptar($nombre_comercial);

            $deudorExiste = deudoresModelo::join("main_empresas AS emp", "fnzs_catalogo_deudores.deu_empresa", "=", "emp.id")
            ->where('emp.empresa_token',$empresa)
            ->where('fnzs_catalogo_deudores.deu_status',true)
            ->where(function ($query) use ($sql_rfc, $sql_taxID, $sql_nombre,$sql_nombre_comercial) {
              if ($sql_rfc) {
                $query->orWhere('fnzs_catalogo_deudores.deu_rfc', $sql_rfc);
              }
              if (!empty($sql_taxID)) {
                $query->orWhere('fnzs_catalogo_deudores.deu_taxId', $sql_taxID);
              }
              if (!empty($sql_nombre)) {
                $query->orWhereRaw('LOWER(fnzs_catalogo_deudores.deu_titular) = ?', [strtolower($sql_nombre)]);
              }
              if (!empty($sql_nombre_comercial)) {
                $query->orWhereRaw('LOWER(fnzs_catalogo_deudores.deu_nombre_comercial) = ?', [strtolower($sql_nombre_comercial)]);
              }
            })->exists();

            if (!$deudorExiste) {
              $fechaAlta = time();

              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.fnzs_deudores = TRUE AND fold.empresa = emp.id 
                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                [$empresa, $usuario]
              );
            
              $post_folio_db = DB::select("SELECT deu_post_folio FROM fnzs_catalogo_deudores WHERE id = (SELECT Max(catDeu.id) FROM fnzs_catalogo_deudores AS catDeu 
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catDeu.deu_empresa = emp.id 
                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
                [$empresa, $usuario]
              );
            
              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->deu_post_folio);
              $folio_deu = $post_folio == NULL ? 'DEU-' . $JwtAuth->generarFolio($folio_nuevo) : 'DEU-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
              $autorizado = TRUE;
              $autorizacion_fecha = time();
              $autorizacion_user = $vEmp->userr;

              //$correo_electronico = $parametrosArray['correo_electronico'];
              //$empleado_vinculado_token = $parametrosArray['empleado_vinculado_token'];
              //$empleado_vinculado_nombre_completo = $parametrosArray['empleado_vinculado_nombre_completo'];

              $tkn_people_ddre = $JwtAuth->encriptarToken($fechaAlta,$tipo,$subtipo,$nombre,$nombre_comercial);

              $deudor_empleado = $validar_trabajador_vinculado ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$trabajador_vinculado)->value("id") : NULL;
              $deudor_proveedor = $validar_proveedor_vinculado ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$proveedor_vinculado)->value("id") : NULL;
              $deudor_acreedor = $validar_acreedor_vinculado ? DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor_vinculado)->value("id") : NULL;
              $deudor_regimen_fiscal = $regimen_fiscal != '' ? DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $regimen_fiscal)->value("id") : NULL;
              
              $tokenDeus = $JwtAuth->encriptarToken($fechaAlta,$tipo,$subtipo,$nombre,$nombre_comercial,$folio_nuevo,$post_folio,$folio_temporal,$folio_deu,$vEmp->id);
              $creaCatDeus = new deudoresModelo();
              $creaCatDeus->token_cat_deudores = $tokenDeus;
              $creaCatDeus->deu_folio = $folio_nuevo;
              $creaCatDeus->deu_post_folio = $post_folio;
              $creaCatDeus->deu_fecha_contab_registro = $fechaAlta;
              $creaCatDeus->deu_rfc_generico = $sql_tipo == 'MEX' ? ($sql_subtipo == 'PF' ? 'xaxx010101000' : 'xax010101000') : 'xexx010101000';
              $creaCatDeus->deu_rfc = $sql_rfc;
              $creaCatDeus->deu_taxId = $sql_taxID;
              $creaCatDeus->deu_nacionalidad = $sql_tipo;
              $creaCatDeus->deu_titular = $sql_nombre;
              $creaCatDeus->deu_nombre_comercial = $sql_nombre_comercial;
              
              $creaCatDeus->deu_cuenta_contable = $cuenta_contable;
              $creaCatDeus->deu_habilita_reembolsos = $habilita_reembolsos ? TRUE : FALSE;
              $creaCatDeus->deu_fisica_moral = $sql_subtipo;
              $creaCatDeus->deu_empleado_vinculado = $deudor_empleado;
              $creaCatDeus->deu_proveedor_vinculado = $deudor_proveedor;
              $creaCatDeus->deu_acreedor_vinculado = $deudor_acreedor;
              $creaCatDeus->deu_regimen_fiscal = $deudor_regimen_fiscal;
              $creaCatDeus->deu_status = TRUE;
              $creaCatDeus->deu_empresa = $vEmp->id;
              $savednewDeus = $creaCatDeus->save();

              if ($validar_email && $validar_email_encrypt) {
                $tokenUserNew = $JwtAuth->encriptarToken($creaCatDeus->id,$JwtAuth->encriptar($email),$JwtAuth->encriptar($email_encrypt));
                $dataUser = new User();
                $dataUser->usuario_token = $tokenUserNew;
                $dataUser->usuario_alias = $JwtAuth->encriptar($email);
                $dataUser->acceso_email = $JwtAuth->encriptar($email_encrypt);
                $dataUser->acceso_codigo = $JwtAuth->encriptar($access_code);
                $dataUser->acceso_password = $JwtAuth->encriptar($password_code);
                $dataUser->login_permission = TRUE;
                $dataUser->jerarquia_main = "P";
                $dataUser->tipo = 5;
                $dataUser->empresa = $vEmp->id;
                $dataUser->deudor = $creaCatDeus->id;
                $savedNewUser = $dataUser->save();
              } else {
                if ($validar_trabajador_vinculado) {
                  $dataUser = DB::table("teci_usuarios_catalogo AS users")
                  ->join("vhum_empleados_catalogo AS pers", "users.empleado", "pers.id")
                  ->where("pers.empleado_token",$trabajador_vinculado)
                  ->limit(1)->update(
                    array(
                      'users.deudor' => $creaCatDeus->id,
                    )
                  );
                }
              }

              if (count($folioSistema) == 0) {
                $insertSistema = DB::table("sos_last_folders")
                ->insert(
                  array(
                    "fnzs_deudores" => TRUE,
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
                ->where("fold.fnzs_deudores",TRUE)
                ->where("emp.empresa_token",$empresa)
                ->where("users.usuario_token",$usuario)
                ->limit(1)->update(
                  array(
                    "fold.folder" => $folio_nuevo,
                    "fold.post_folder" => $post_folio,
                  )
                );
              }

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Deudor registrado satisfactoriamente con el folio $folio_deu"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'ya existe un deudor con esta información'
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
      } else {
        $mensaje_error = '';
        if (!$validar_tipo) {$mensaje_error = 'Error en tipo de deudor, intente más tarde o comuniquese a soporte';}
        if (!$validar_subtipo) {$mensaje_error = 'Error en tipo de persona, intente más tarde o comuniquese a soporte';}
        //if (!$validar_rfc) {$mensaje_error = 'Error en , intente más tarde o comuniquese a soporte';}
        //if (!$validar_taxID) {$mensaje_error = 'Error en , intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre) {$mensaje_error = 'Error en Nombre / Razón social, intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre_comercial) {$mensaje_error = 'Error en Nombre Comercial,intente más tarde o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 
}
