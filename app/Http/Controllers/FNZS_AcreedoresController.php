<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\AcreedoresModelo;
use App\Models\PersonalModelo;
use App\Models\User;
use PDF;
use QRCode;

class FNZS_AcreedoresController extends Controller{
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
    ->where('emp.empresa_token',$empresa)->get();
    
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
    
    $queryDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
    ->join("main_empresas AS emp", "catDeu.deu_empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where("catDeu.deu_status",TRUE)
    ->where("emp.empresa_token",$empresa)
    ->where("users.usuario_token",$usuario)
    ->get();

    if ($listEmpleados->isEmpty() && $listaProveedores->isEmpty() && $queryDeudores->isEmpty()) {
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

      foreach ($queryDeudores as $vDeu) {
        //da_te_default_timezone_set($vDeu->zona_horaria);
        $deu_relacionado_token = $vDeu->token_cat_deudores;
        $deu_relacionado_folio = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');
        
        $deu_relacionado_nombre = !is_null($vDeu->deu_titular) && $vDeu->deu_titular != "" ? $JwtAuth->desencriptar($vDeu->deu_titular) : 'N/A';
        $deu_relacionado_comercial_nombre = !is_null($vDeu->deu_nombre_comercial) && $vDeu->deu_nombre_comercial != "" ? $JwtAuth->desencriptar($vDeu->deu_nombre_comercial) : '';

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
        
        $rowDeu = array(
          "people_relacionado_tipo" => "DEU",
          "people_relacionado_token" => $deu_relacionado_token,
          "people_relacionado_folio" => $deu_relacionado_folio,
          "people_relacionado_nombre" => $deu_relacionado_nombre,
          "people_relacionado_comercial_nombre" => $deu_relacionado_comercial_nombre,
          "people_relacionado_nombre_completo" => "$deu_relacionado_folio $deu_relacionado_nombre",

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
          "selected" => false,
        );
        $listaNombresRelacionados[] = $rowDeu;
      }

      $dataMensaje = array(
        "nombres_relacionados" => $listaNombresRelacionados,
        "code" => 200,
        "status" => "success"
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function acreedoresCatGeneral(Request $request){
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
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catAcree.id",">", 0)
      ->where([
        "catAcree.acr_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catAcree.acr_fecha_contab_registro", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('catAcree.id', 'desc')
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron acreedores registrados'
        );
      } else {
        $arrayAcreedores = array();
        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)->count();

          $acreedor_deuda_total = 0;
          $acreedor_deuda_restante = 0;
          $acreedor_deuda_debe = 0;
          $acreedor_deuda_haber = 0;
          $acreedor_deuda_saldo = 0;
          $pagos_acreedor_moneda = "";
          $estado_cuenta_acreedor = array();
          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
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

          $cancelaciones = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("pago.pago_cancelado", TRUE)
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'CANCELACION' AS tipo_registro_e_cuenta"),
            "pago.token_pagos AS token_pagos",
            "pago.token_pagos AS id_registro",
            "pago.pago_folio_cancelacion AS folio_movimiento",
            "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
            "pago.pago_comentarios_cancelacion AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $movimientos = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            "mov.token_acre_mov AS token_acre_mov",
            "mov.token_acre_mov AS id_registro",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            //"'---' AS forma_pago_pago",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $queryEstadoDeCuenta = $pagos->unionAll($cancelaciones)->unionAll($movimientos)
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();
          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_pagos = $vECuenta->token_pagos;
            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';
					  $forma_pago_registrada = $vECuenta->forma_pago_pago;

					  $cfdi_comprobante_metodo_de_pago = "";
					  $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
            ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
            ->where("payment.token_pagos", $token_pagos)
            ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

            $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" || $vECuenta->tipo_registro_e_cuenta == "CANCELACION" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_acreedor) == 0 ? $e_cuenta_haber - $e_cuenta_debe : ($estado_cuenta_acreedor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_haber) - $e_cuenta_debe;
            
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
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => "---",
              "forma_pago_cfdi" => $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada),
              "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
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
            $estado_cuenta_acreedor[] = $row_cuenta_estado;
            ++$contador;
          }

          for ($i=0; $i < count($estado_cuenta_acreedor); $i++) { 
            $pagos_acreedor_moneda = $vECuenta->moneda_movimiento;
            $acreedor_deuda_debe = $acreedor_deuda_debe + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_debe"] ?? 0);
            $acreedor_deuda_haber = $acreedor_deuda_haber + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $acreedor_deuda_saldo = floatval($acreedor_deuda_haber ?? 0) - floatval($acreedor_deuda_debe ?? 0);

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

          $queryRegFis = DB::table("sos_regimen_fiscal AS reg_fis")
          ->join("fnzs_catalogo_acreedores AS catAcree", "reg_fis.id", "catAcree.acr_regimen_fiscal")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->select("reg_fis.token_regimen_fiscal","reg_fis.clave","reg_fis.descripcion")
          ->first();
          $acr_regimen_fiscal_token = $queryRegFis ? $queryRegFis->token_regimen_fiscal : ''; 
          $acr_regimen_fiscal_clave = $queryRegFis ? $queryRegFis->clave : 'N/A'; 
          $acr_regimen_fiscal_descripcion = $queryRegFis ? $queryRegFis->descripcion : 'N/A'; 
          
          $rowAcr = array(
            "token_cat_acreedores" => $vAcr->token_cat_acreedores,
            "folio" => $folio_acr,

            "acr_rfc_generico" => !is_null($vAcr->acr_rfc_generico) ? $vAcr->acr_rfc_generico : 'N/A',
            "acr_rfc" => !is_null($vAcr->acr_rfc) ? $JwtAuth->desencriptar($vAcr->acr_rfc) : 'N/A',
            "acr_taxId" => !is_null($vAcr->acr_taxId) ? $JwtAuth->desencriptar($vAcr->acr_taxId) : 'N/A',
            "acr_titular" => !is_null($vAcr->acr_titular) ? $JwtAuth->desencriptar($vAcr->acr_titular) : 'N/A',
            "nombre_comercial" => !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : 'N/A',
            "cuenta_contable" => !empty($vAcr->acr_cuenta_contable) ? $vAcr->acr_cuenta_contable : 'N/A',
            "deuda_al_acreedor" => $acreedor_deuda_saldo > 0 ? "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda" : "$0.00 MXN",
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

            "deu_folio" => $deu_folio,
            "deu_rfc_generico" => $deu_rfc_generico,
            "deu_rfc" => $deu_rfc,
            "deu_tax_id" => $deu_tax_id,
            "deu_nombre" => $deu_nombre,

            "regimen_fiscal_token" => $acr_regimen_fiscal_token,
            "regimen_fiscal_clave" => $acr_regimen_fiscal_clave,
            "regimen_fiscal_descripcion" => $acr_regimen_fiscal_descripcion,

            "data_detalle" => [],
            "data_reembolsos" => [],
            "data_pagos" => [],
            "data_anticipos" => [],
          );
          $arrayAcreedores[] = $rowAcr;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'acreedores' => $arrayAcreedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function acreedoresCatMx(Request $request){
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
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catAcree.id",">", 0)
      ->where([
        "catAcree.acr_nacionalidad" => "MEX",
        "catAcree.acr_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catAcree.acr_fecha_contab_registro", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('catAcree.id', 'desc')
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron acreedores registrados'
        );
      } else {
        $arrayAcreedores = array();
        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)->count();

          $acreedor_deuda_total = 0;
          $acreedor_deuda_restante = 0;
          $acreedor_deuda_debe = 0;
          $acreedor_deuda_haber = 0;
          $acreedor_deuda_saldo = 0;
          $pagos_acreedor_moneda = "";
          $estado_cuenta_acreedor = array();
          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
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

          $cancelaciones = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("pago.pago_cancelado", TRUE)
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'CANCELACION' AS tipo_registro_e_cuenta"),
            "pago.token_pagos AS token_pagos",
            "pago.token_pagos AS id_registro",
            "pago.pago_folio_cancelacion AS folio_movimiento",
            "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
            "pago.pago_comentarios_cancelacion AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $movimientos = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            "mov.token_acre_mov AS token_acre_mov",
            "mov.token_acre_mov AS id_registro",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            //"'---' AS forma_pago_pago",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $queryEstadoDeCuenta = $pagos->unionAll($cancelaciones)->unionAll($movimientos)
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();
          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_pagos = $vECuenta->token_pagos;
            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';
					  $forma_pago_registrada = $vECuenta->forma_pago_pago;

					  $cfdi_comprobante_metodo_de_pago = "";
					  $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
            ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
            ->where("payment.token_pagos", $token_pagos)
            ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

            $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" || $vECuenta->tipo_registro_e_cuenta == "CANCELACION" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_acreedor) == 0 ? $e_cuenta_haber - $e_cuenta_debe : ($estado_cuenta_acreedor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_haber) - $e_cuenta_debe;
            
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
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => "---",
              "forma_pago_cfdi" => $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada),
              "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
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
            $estado_cuenta_acreedor[] = $row_cuenta_estado;
            ++$contador;
          }

          for ($i=0; $i < count($estado_cuenta_acreedor); $i++) { 
            $pagos_acreedor_moneda = $vECuenta->moneda_movimiento;
            $acreedor_deuda_debe = $acreedor_deuda_debe + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_debe"] ?? 0);
            $acreedor_deuda_haber = $acreedor_deuda_haber + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $acreedor_deuda_saldo = floatval($acreedor_deuda_haber ?? 0) - floatval($acreedor_deuda_debe ?? 0);

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

          $queryRegFis = DB::table("sos_regimen_fiscal AS reg_fis")
          ->join("fnzs_catalogo_acreedores AS catAcree", "reg_fis.id", "catAcree.acr_regimen_fiscal")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->select("reg_fis.token_regimen_fiscal","reg_fis.clave","reg_fis.descripcion")
          ->first();
          $acr_regimen_fiscal_token = $queryRegFis ? $queryRegFis->token_regimen_fiscal : ''; 
          $acr_regimen_fiscal_clave = $queryRegFis ? $queryRegFis->clave : 'N/A'; 
          $acr_regimen_fiscal_descripcion = $queryRegFis ? $queryRegFis->descripcion : 'N/A'; 
          
          $rowAcr = array(
            "token_cat_acreedores" => $vAcr->token_cat_acreedores,
            "folio" => $folio_acr,

            "acr_rfc_generico" => !is_null($vAcr->acr_rfc_generico) ? $vAcr->acr_rfc_generico : 'N/A',
            "acr_rfc" => !is_null($vAcr->acr_rfc) ? $JwtAuth->desencriptar($vAcr->acr_rfc) : 'N/A',
            "acr_taxId" => !is_null($vAcr->acr_taxId) ? $JwtAuth->desencriptar($vAcr->acr_taxId) : 'N/A',
            "acr_titular" => !is_null($vAcr->acr_titular) ? $JwtAuth->desencriptar($vAcr->acr_titular) : 'N/A',
            "nombre_comercial" => !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : 'N/A',
            "cuenta_contable" => !empty($vAcr->acr_cuenta_contable) ? $vAcr->acr_cuenta_contable : 'N/A',
            "deuda_al_acreedor" => $acreedor_deuda_saldo > 0 ? "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda" : "$0.00 MXN",
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

            "deu_folio" => $deu_folio,
            "deu_rfc_generico" => $deu_rfc_generico,
            "deu_rfc" => $deu_rfc,
            "deu_tax_id" => $deu_tax_id,
            "deu_nombre" => $deu_nombre,

            "regimen_fiscal_token" => $acr_regimen_fiscal_token,
            "regimen_fiscal_clave" => $acr_regimen_fiscal_clave,
            "regimen_fiscal_descripcion" => $acr_regimen_fiscal_descripcion,

            "data_detalle" => [],
            "data_reembolsos" => [],
            "data_pagos" => [],
            "data_anticipos" => [],
          );
          $arrayAcreedores[] = $rowAcr;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'acreedores' => $arrayAcreedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function acreedoresCatExt(Request $request){
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
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("catAcree.id",">", 0)
      ->where([
        "catAcree.acr_nacionalidad" => "EXT",
        "catAcree.acr_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catAcree.acr_fecha_contab_registro", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('catAcree.id', 'desc')
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron acreedores registrados'
        );
      } else {
        $arrayAcreedores = array();
        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)->count();

          $acreedor_deuda_total = 0;
          $acreedor_deuda_restante = 0;
          $acreedor_deuda_debe = 0;
          $acreedor_deuda_haber = 0;
          $acreedor_deuda_saldo = 0;
          $pagos_acreedor_moneda = "";
          $estado_cuenta_acreedor = array();
          $pagos = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
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

          $cancelaciones = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("pago.pago_cancelado", TRUE)
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'CANCELACION' AS tipo_registro_e_cuenta"),
            "pago.token_pagos AS token_pagos",
            "pago.token_pagos AS id_registro",
            "pago.pago_folio_cancelacion AS folio_movimiento",
            "pago.pago_fecha_contabilizacion_cancelacion AS fecha_contabilizacion",
            "pago.pago_comentarios_cancelacion AS observaciones",
            "pago.forma_pago_pago AS forma_pago_pago",
            "pago.monto_pago AS monto_movimiento",
            "pago.tipo_cambio AS tipo_cambio_movimiento",
            "pago.p_moneda AS moneda_movimiento",
            DB::raw("NULL AS movimiento_id"),
          ]);

          $movimientos = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          ->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            "mov.token_acre_mov AS token_acre_mov",
            "mov.token_acre_mov AS id_registro",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            //"'---' AS forma_pago_pago",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $queryEstadoDeCuenta = $pagos->unionAll($cancelaciones)->unionAll($movimientos)
          ->orderBy("fecha_contabilizacion", "asc")
          ->get();
          $contador = 0;
          foreach ($queryEstadoDeCuenta as $vECuenta) {
            $token_pagos = $vECuenta->token_pagos;
            $payment_observaciones = !is_null($vECuenta->observaciones) ? $JwtAuth->desencriptar($vECuenta->observaciones) : '';
					  $forma_pago_registrada = $vECuenta->forma_pago_pago;

					  $cfdi_comprobante_metodo_de_pago = "";
					  $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
            ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
            ->where("payment.token_pagos", $token_pagos)
            ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

            $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

            $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" || $vECuenta->tipo_registro_e_cuenta == "CANCELACION" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "PAGO" ? $vECuenta->monto_movimiento : 0;
            $e_cuenta_saldo = count($estado_cuenta_acreedor) == 0 ? $e_cuenta_haber - $e_cuenta_debe : ($estado_cuenta_acreedor[$contador-1]["estado_cuenta_saldo"] +  $e_cuenta_haber) - $e_cuenta_debe;
            
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
					  	"fecha_contabilizacion" => !empty($vECuenta->fecha_contabilizacion) ? gmdate('Y-m-d H:i:s', $vECuenta->fecha_contabilizacion) : "",
              "tipo_cambio_movimiento" => "$".number_format($vECuenta->tipo_cambio_movimiento,$JwtAuth->getMonedaAPI($vECuenta->moneda_movimiento),'.',',')." $vECuenta->moneda_movimiento",
              "forma_pago_vinculada" => "---",
              "forma_pago_cfdi" => $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada),
              "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
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
            $estado_cuenta_acreedor[] = $row_cuenta_estado;
            ++$contador;
          }

          for ($i=0; $i < count($estado_cuenta_acreedor); $i++) { 
            $pagos_acreedor_moneda = $vECuenta->moneda_movimiento;
            $acreedor_deuda_debe = $acreedor_deuda_debe + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_debe"] ?? 0);
            $acreedor_deuda_haber = $acreedor_deuda_haber + floatval($estado_cuenta_acreedor[$i]["estado_cuenta_haber"] ?? 0);
          }
          $acreedor_deuda_saldo = floatval($acreedor_deuda_haber ?? 0) - floatval($acreedor_deuda_debe ?? 0);

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

          $queryRegFis = DB::table("sos_regimen_fiscal AS reg_fis")
          ->join("fnzs_catalogo_acreedores AS catAcree", "reg_fis.id", "catAcree.acr_regimen_fiscal")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->select("reg_fis.token_regimen_fiscal","reg_fis.clave","reg_fis.descripcion")
          ->first();
          $acr_regimen_fiscal_token = $queryRegFis ? $queryRegFis->token_regimen_fiscal : ''; 
          $acr_regimen_fiscal_clave = $queryRegFis ? $queryRegFis->clave : 'N/A'; 
          $acr_regimen_fiscal_descripcion = $queryRegFis ? $queryRegFis->descripcion : 'N/A'; 
          
          $rowAcr = array(
            "token_cat_acreedores" => $vAcr->token_cat_acreedores,
            "folio" => $folio_acr,

            "acr_rfc_generico" => !is_null($vAcr->acr_rfc_generico) ? $vAcr->acr_rfc_generico : 'N/A',
            "acr_rfc" => !is_null($vAcr->acr_rfc) ? $JwtAuth->desencriptar($vAcr->acr_rfc) : 'N/A',
            "acr_taxId" => !is_null($vAcr->acr_taxId) ? $JwtAuth->desencriptar($vAcr->acr_taxId) : 'N/A',
            "acr_titular" => !is_null($vAcr->acr_titular) ? $JwtAuth->desencriptar($vAcr->acr_titular) : 'N/A',
            "nombre_comercial" => !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : 'N/A',
            "cuenta_contable" => !empty($vAcr->acr_cuenta_contable) ? $vAcr->acr_cuenta_contable : 'N/A',
            "deuda_al_acreedor" => $acreedor_deuda_saldo > 0 ? "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda" : "$0.00 MXN",
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

            "deu_folio" => $deu_folio,
            "deu_rfc_generico" => $deu_rfc_generico,
            "deu_rfc" => $deu_rfc,
            "deu_tax_id" => $deu_tax_id,
            "deu_nombre" => $deu_nombre,

            "regimen_fiscal_token" => $acr_regimen_fiscal_token,
            "regimen_fiscal_clave" => $acr_regimen_fiscal_clave,
            "regimen_fiscal_descripcion" => $acr_regimen_fiscal_descripcion,

            "data_detalle" => [],
            "data_reembolsos" => [],
            "data_pagos" => [],
            "data_anticipos" => [],
          );
          $arrayAcreedores[] = $rowAcr;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'acreedores' => $arrayAcreedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function acreedorDetalleInfoGeneral(Request $request){
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
      $token_cat_acreedores = $request->input('token_cat_acreedores');

      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catAcree.token_cat_acreedores" => $token_cat_acreedores,
        "catAcree.acr_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron acreedores registrados'
        );
      } else {
        $arrayAcreedores = array();

        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

          $queryNamesAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
          ->join("vhum_empleados_catalogo AS pers", "catAcree.acr_empleado_vinculado", "=", "pers.id")
          ->join("sos_personas AS acr", "pers.empleado_name", "acr.id")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->get();

          $mailAcreedor = DB::table("fnzs_catalogo_acreedores AS catAcree")
          ->join("teci_usuarios_catalogo AS users", "catAcree.id", "=", "users.acreedor")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->select("users.usuario_alias")
          ->first();

          $fiscRegAcreedor = DB::table("sos_regimen_fiscal AS regFis")
          ->join("fnzs_catalogo_acreedores AS catAcree", "regFis.id", "=", "catAcree.acr_regimen_fiscal")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->select("regFis.token_regimen_fiscal","regFis.clave","regFis.descripcion")
          ->first();
          
          $regimen_fiscal_token = $fiscRegAcreedor ? $fiscRegAcreedor->token_regimen_fiscal : '';
          $regimen_fiscal_desc = $fiscRegAcreedor ? $fiscRegAcreedor->clave."-".$fiscRegAcreedor->descripcion : '';

          $arrayForeach = array(
            "token_cat_acreedores" => $vAcr->token_cat_acreedores,
            "folio" => $folio_acr,
            "tipo" => $vAcr->acr_nacionalidad == 'MEX' ? 'nacional' : 'extranjero',
            "subtipo" => $vAcr->acr_fisica_moral == 'PF' ? 'acreeFisica' : 'acreeMoral',
            //"pais" => $vAcr->pais,
            "rfc_generico" => $vAcr->acr_rfc_generico,
            "rfc_acr" => $vAcr->acr_rfc != NULL ? $JwtAuth->desencriptar($vAcr->acr_rfc) : '',
            "tax_id_acr" => $vAcr->acr_taxId != NULL ? $JwtAuth->desencriptar($vAcr->acr_taxId) : '',
            "nombre" => !is_null($vAcr->acr_titular) && $vAcr->acr_titular != '' ? $JwtAuth->desencriptar($vAcr->acr_titular) : '',
            "nombre_comercial" => !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : '',
            "regimen_fiscal_token" => $regimen_fiscal_token,
            "regimen_fiscal_desc" => $regimen_fiscal_desc,
            "cuenta_contable" => !empty($vAcr->acr_cuenta_contable) ? $vAcr->acr_cuenta_contable : '',
            //"utilizado" =>$vAcr->utilizado == TRUE ? true : false,
            "nombres_bloqueados" => count($queryNamesAcreedores) > 0 ? true : false,
            "habilita_reembolsos" => $vAcr->acr_habilita_reembolsos ? true : false,
            "email" => $mailAcreedor ? $JwtAuth->desencriptar($mailAcreedor->usuario_alias) : '',
            "trabajador_vinculado" => !is_null($vAcr->acr_empleado_vinculado) ? DB::table("vhum_empleados_catalogo")->where("id",$vAcr->acr_empleado_vinculado)->value("empleado_token") : '',
            "proveedor_vinculado" => !is_null($vAcr->acr_proveedor_vinculado) ? DB::table("eegr_catalogo_proveedores")->where("id",$vAcr->acr_proveedor_vinculado)->value("token_cat_proveedores") : '',
            "deudor_vinculado" => !is_null($vAcr->acr_deudor_vinculado) ? DB::table("fnzs_catalogo_deudores")->where("id",$vAcr->acr_deudor_vinculado)->value("token_cat_deudores") : '',
          );
          $arrayAcreedores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'acreedor' => $arrayAcreedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function acreedorDetalleInfoPagos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_acreedores' => 'required|string'
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
      $token_cat_acreedores = $request->input('token_cat_acreedores');
      
      $queryDeudores = DB::table('fnzs_catalogo_deudores AS catDeu')
      ->join('main_empresas AS emp', 'catDeu.deu_empresa', '=', 'emp.id')
      ->join('main_empresa_usuario AS empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', '=', 'users.id')
      ->leftJoin('eegr_catalogo_proveedores AS catprov', 'catDeu.deu_proveedor_vinculado', '=', 'catprov.id')
      ->leftJoin('sos_personas AS prv', 'catprov.proveedor', '=', 'prv.id')
      ->where([
        'catDeu.deu_status' => true,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select(
        'catDeu.*',
        'catDeu.deu_rfc as rfc_ddr',
        'catDeu.deu_taxId as tax_id_ddr',
        'catDeu.deu_titular as nombre_ddr',
        'catDeu.deu_nombre_comercial as nombre_comercial_ddr',
        'prv.rfc as rfc_prv',
        'prv.tax_id as tax_id_prv',
        'prv.nombre_extendido as nombre_extendido_prv',
        'prv.paterno as paterno_prv',
        'prv.materno as materno_prv',
        'prv.nombre as nombre_prv',
        'prv.nombre_com as nombre_com_prv',
        'catDeu.deu_cuenta_contable',
        'emp.*'
      )->get();
      
      $listaDeudores = $queryDeudores->map(function($vDeu) use ($JwtAuth) {
        // Folio
        //da_te_default_timezone_set('UTC');
        $folio_deu = 'DEU-'.$JwtAuth->generarFolio($vDeu->deu_folio).(!is_null($vDeu->deu_post_folio) ? '-'.$vDeu->deu_post_folio : '');
        $vDeu->folio_deu = $folio_deu;

        // Determinar si usamos persona o proveedor
        //echo $vDeu->deudor;
        if (is_null($vDeu->deu_proveedor_vinculado)) {
          $vDeu->rfc_ddr = $vDeu->rfc_ddr ? $JwtAuth->desencriptar($vDeu->rfc_ddr) : '';
          $vDeu->tax_id_ddr = $vDeu->tax_id_ddr ? $JwtAuth->desencriptar($vDeu->tax_id_ddr) : '';
          $vDeu->deudor_nombre = $vDeu->nombre_ddr != '' ? $JwtAuth->desencriptar($vDeu->nombre_ddr) : '';
          $vDeu->deudor_nombre_comercial = $vDeu->nombre_comercial_ddr ? $JwtAuth->desencriptar($vDeu->nombre_comercial_ddr) : '';
        } else {
          $vDeu->rfc_ddr = $vDeu->rfc_prv ? $JwtAuth->desencriptar($vDeu->rfc_prv) : '';
          $vDeu->tax_id_ddr = $vDeu->tax_id_prv ? $JwtAuth->desencriptar($vDeu->tax_id_prv) : '';
          $vDeu->deudor_nombre = $vDeu->nombre_extendido_prv != '' 
            ? $JwtAuth->desencriptar($vDeu->nombre_extendido_prv) 
            : $JwtAuth->desencriptarNombres($vDeu->paterno_prv, $vDeu->materno_prv, $vDeu->nombre_prv);
          $vDeu->deudor_nombre_comercial = $vDeu->nombre_com_prv 
            ? $JwtAuth->desencriptar($vDeu->nombre_com_prv) 
            : '';
        }
        $vDeu->deudor_nombre = strtolower(trim($vDeu->deudor_nombre));
        $vDeu->deudor_nombre_comercial = strtolower(trim($vDeu->deudor_nombre_comercial));
        return $vDeu;
      });
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catAcree.token_cat_acreedores" => $token_cat_acreedores,
        "catAcree.acr_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron acreedores registrados'
        );
      } else {
        $arrayAcreedores = array();

        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);

          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

          $estado_cuenta_acreedor = array();
          $pagosRealizados = DB::table("fnzs_pagos_pago AS pago")
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'PAGO' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            DB::raw("'---' AS condi_acree_mov"),
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
          ->join("fnzs_catalogo_acreedores AS acreedor", "pago.vinc_acreedor", "=", "acreedor.id")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "pago.id", "=", "vinc.pago_vinculado")
          ->leftJoin("fnzs_catalogo_acreedores_movimientos AS mov", "vinc.mov_realizado", "=", "mov.id")
          ->where("pago.pago_cancelado", TRUE)
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'PAGO-CANCELADO' AS tipo_registro_e_cuenta"),
            "pago.id AS doc_asociado",
            DB::raw("'---' AS condi_acree_mov"),
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

          $movimACRWithPago = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          //->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          //->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->whereIn('mov.id', function ($query) {
            $query->select('vinc.mov_realizado')->from('fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc')
                  ->join("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id");
          })
          ->whereNull("mov.acre_mov_asociado")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            "mov.condicion_acree_mov AS condi_acree_mov",
            "mov.token_acre_mov AS token_movimiento",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $movimACRCancelWithPago = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          //->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          //->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->whereIn('mov.id', function ($query) {
            $query->select('vinc.mov_realizado')->from('fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc')
                  ->join("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id");
          })
          ->where("mov.acre_mov_cancelado", FALSE)
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->whereNotNull("mov.acre_mov_asociado")
          ->whereIn('mov.acre_mov_asociado', function ($query) {
            $query->select('id')->from('fnzs_catalogo_acreedores_movimientos')
            ->where("acre_mov_cancelado", TRUE);
          })
          ->select([
            DB::raw("'MOVIMIENTO-CANCELADO' AS tipo_registro_e_cuenta"),
            "mov.acre_mov_asociado AS doc_asociado",
            "mov.condicion_acree_mov AS condi_acree_mov",
            "mov.token_acre_mov AS token_movimiento",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $movimACRWithoutPago = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          ->whereNotIn('mov.id', function ($query) {
            $query->select('vinc.mov_realizado')->from('fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc')
                  ->join("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id");
          })
          ->whereNull("mov.acre_mov_asociado")
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->select([
            DB::raw("'MOVIMIENTO_PURO' AS tipo_registro_e_cuenta"),
            DB::raw("'---' AS doc_asociado"),
            "mov.condicion_acree_mov AS condi_acree_mov",
            "mov.token_acre_mov AS token_movimiento",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $movimACRCancelWithoutPago = DB::table("fnzs_catalogo_acreedores_movimientos AS mov")
          ->join("fnzs_catalogo_acreedores AS acreedor", "acreedor.id", "=", "mov.vinc_acreedor")
          //->leftJoin("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "mov.id", "=", "vinc.mov_realizado")
          //->leftJoin("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id")
          ->whereNotIn('mov.id', function ($query) {
            $query->select('vinc.mov_realizado')->from('fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc')
                  ->join("fnzs_pagos_pago AS pago", "vinc.pago_vinculado", "=", "pago.id");
          })
          ->where("mov.acre_mov_cancelado", FALSE)
          ->where("acreedor.token_cat_acreedores", $vAcr->token_cat_acreedores)
          ->whereNotNull("mov.acre_mov_asociado")
          ->whereIn('mov.acre_mov_asociado', function ($query) {
            $query->select('id')->from('fnzs_catalogo_acreedores_movimientos')
            ->where("acre_mov_cancelado", TRUE);
          })
          ->select([
            DB::raw("'MOVIMIENTO_PURO_CANCELADO' AS tipo_registro_e_cuenta"),
            "mov.acre_mov_asociado AS doc_asociado",
            "mov.condicion_acree_mov AS condi_acree_mov",
            "mov.token_acre_mov AS token_movimiento",
            "mov.folio_acre_mov AS folio_movimiento",
            "mov.acre_fecha_contabilizacion AS fecha_contabilizacion",
            "mov.acre_observaciones_mov AS observaciones",
            DB::raw("'---' AS forma_pago_pago"),
            "mov.acre_monto_mov AS monto_movimiento",
            "mov.acre_tipo_cambio AS tipo_cambio_movimiento",
            "mov.acre_mov_moneda AS moneda_movimiento",
            "mov.id AS movimiento_id",
          ]);

          $unionEstadoDeCuenta = $pagosRealizados->unionAll($pagosCancelados)->unionAll($movimACRWithPago)->unionAll($movimACRCancelWithPago)->unionAll($movimACRWithoutPago)->unionAll($movimACRCancelWithoutPago);

          $queryEstadoDeCuenta = DB::table(DB::raw("({$unionEstadoDeCuenta->toSql()}) as estado_cuenta"))
          ->mergeBindings($unionEstadoDeCuenta) // Importante para no perder los parámetros del WHERE
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
          ->get()->keyBy('id');

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
                $folio_e_cuenta = "ACRMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              case 'MOVIMIENTO_PURO':
                $folio_e_cuenta = "ACRMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              case 'MOVIMIENTO-CANCELADO':
                $folio_e_cuenta = "ACRMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              case 'MOVIMIENTO_PURO_CANCELADO':
                $folio_e_cuenta = "ACRMOV-".$JwtAuth->generarFolio($vECuenta->folio_movimiento);
                break;
              default:
                $folio_e_cuenta = "";
                break;
            }

            if (in_array($vECuenta->tipo_registro_e_cuenta, ['MOVIMIENTO_PURO', 'MOVIMIENTO_PURO_CANCELADO'])) {
              $e_cuenta_debe = $vECuenta->condi_acree_mov == "S" ? $vECuenta->monto_movimiento : 0;
              $e_cuenta_haber = $vECuenta->condi_acree_mov == "R" ? $vECuenta->monto_movimiento : 0;
            } else {
              $e_cuenta_debe = $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO" || $vECuenta->tipo_registro_e_cuenta == "MOVIMIENTO-CANCELADO" ? $vECuenta->monto_movimiento : 0;
              $e_cuenta_haber = $vECuenta->tipo_registro_e_cuenta == "PAGO" || $vECuenta->tipo_registro_e_cuenta == "PAGO-CANCELADO" ? $vECuenta->monto_movimiento : 0;
            }
            
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

          $pagos_acreedor_list = array();
          $queryPagosAcree = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_catalogo_acreedores AS catAcree", "pay.vinc_acreedor", "=", "catAcree.id")
          ->where("pay.pago_cancelado",FALSE)
          ->where("pay.status_pagos",TRUE)
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->get();
          foreach ($queryPagosAcree as $vPayDone) {
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
            $queryMovimientosDone = DB::table("fnzs_catalogo_acreedores_movimientos AS acrmov")
            ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acrmov.id", "=","vinc.mov_realizado")
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
              $pagos_acreedor_list[] = $row_pagos_realizados;
            }
          }

          $lista_movimientos_realizados = [];
          $queryMovimientosDone = DB::table("fnzs_catalogo_acreedores_movimientos AS acrmov")
          ->join("fnzs_catalogo_acreedores AS catAcr", "acrmov.vinc_acreedor", "=","catAcr.id")
          ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS vinc", "acrmov.id", "=","vinc.mov_realizado")
          ->join("fnzs_pagos_pago AS pay", "vinc.pago_vinculado", "=", "pay.id")
          ->where("catAcr.token_cat_acreedores",$vAcr->token_cat_acreedores)->get();
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
            ->select('movAct.tipo_movimiento','movAct.subtipo_movimiento')
            ->first();

            $row_mov_acr = array(
              "token_acre_mov" => $vMovDone->token_acre_mov,
              "folio_acre_mov" => "ACRMOV-".$JwtAuth->generarFolio($vMovDone->folio_acre_mov),
              "acre_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vMovDone->acre_fecha_contabilizacion),
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
          
          $_acreedor_nombre = $JwtAuth->desencriptar($vAcr->acr_titular);
          $_deudor_vinculado = $listaDeudores->first(function($vDeu) use ($_acreedor_nombre) {return $vDeu->deudor_nombre === strtolower(trim($_acreedor_nombre));});
          //echo $_deudor_vinculado->deudor_nombre;

          $arrayForeach = array(
            "token_cat_acreedores" => $vAcr->token_cat_acreedores,
            "folio" => $folio_acr,
            "tipo" => $vAcr->acr_nacionalidad == 'MEX' ? 'nacional' : 'extranjero',
            "subtipo" => $vAcr->acr_fisica_moral == 'PF' ? 'Física' : 'Moral',
            //"pais" => $vAcr->pais,
            "rfc_acr" => $vAcr->acr_rfc	!= NULL ? $JwtAuth->desencriptar($vAcr->acr_rfc) : '',
            "tax_id_acr" => $vAcr->acr_taxId != NULL ? $JwtAuth->desencriptar($vAcr->acr_taxId) : '',
            "nombre" => $_acreedor_nombre,
            "nombre_comercial" => !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : '',
            "cuenta_contable" => !empty($vAcr->cuenta_contable) ? $vAcr->cuenta_contable : '',
            "deudor_vinculado_token" => $_deudor_vinculado ? $_deudor_vinculado->token_cat_deudores : '',
            "deudor_vinculado_folio" => $_deudor_vinculado ? $_deudor_vinculado->folio_deu : '',
            "deudor_vinculado_nombre" => $_deudor_vinculado ? $_deudor_vinculado->deudor_nombre : '',

            "deuda_al_acreedor" => "$".number_format($acreedor_deuda_total,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_debe" => "$".number_format($acreedor_deuda_debe,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_haber" => "$".number_format($acreedor_deuda_haber,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_saldo_simple" => $acreedor_deuda_saldo,
            "acr_total_saldo" => "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "acr_total_saldo_aplicar" => 0,
            "acr_total_saldo_restante_simple" => $acreedor_deuda_saldo,
            "acr_total_saldo_restante" => "$".number_format($acreedor_deuda_saldo,$JwtAuth->getMonedaAPI($pagos_acreedor_moneda),'.', ',')." $pagos_acreedor_moneda",
            "habilita_reembolsos" => $vAcr->acr_habilita_reembolsos ? true : false,
            "estado_de_cuenta" => $estado_cuenta_acreedor,
            "pagos_acreedor_list" => $pagos_acreedor_list,
            "movimientos_realizados" => $lista_movimientos_realizados,
          );
          $arrayAcreedores[] = $arrayForeach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'acreedor' => $arrayAcreedores,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaAcreedor(Request $request){
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
      'tipo' => 'required|string',
      'subtipo' => 'required|string',
      'rfc' => 'string',
      'taxID' => 'string',
      'nombre' => 'required|string',
      'nombre_comercial' => 'required|string',
      'cuenta_contable' => 'string',
      'habilita_reembolsos' => 'boolean',
      'regimen_fiscal' => 'string',
      'trabajador_vinculado' => 'string',
      'proveedor_vinculado' => 'string',
      'deudor_vinculado' => 'string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es invalida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cat_acreedores = $request->input('token_cat_acreedores');
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
      $deudor_vinculado = $request->input('deudor_vinculado');

      $validar_tipo = isset($tipo) && !empty($tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo);
      $validar_subtipo = isset($subtipo) && !empty($subtipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $subtipo);
      $validar_rfc = isset($rfc) && !empty($rfc) && preg_match($JwtAuth->filtroRfc(), $rfc);
      $validar_taxID = isset($taxID) && !empty($taxID) && preg_match($JwtAuth->filtroRfc(), $taxID);
      $validar_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre);
      $validar_nombre_comercial = isset($nombre_comercial) && !empty($nombre_comercial) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre_comercial);
      $validar_trabajador_vinculado = isset($trabajador_vinculado) && !empty($trabajador_vinculado);
      $validar_proveedor_vinculado = isset($proveedor_vinculado) && !empty($proveedor_vinculado);
      $validar_deudor_vinculado = isset($deudor_vinculado) && !empty($deudor_vinculado);

      if ($validar_tipo && $validar_subtipo && $validar_nombre && $validar_nombre_comercial) {
        $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
        ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "catAcree.token_cat_acreedores" => $token_cat_acreedores,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->get();
        
        foreach ($queryAcreedores as $vAcr) {
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

          $sql_tipo = $tipo == 'nacional' ? 'MEX' : 'EXT';
          $sql_subtipo = $subtipo == 'acreeFisica' ? 'PF' : 'PM';
          $sql_rfc = $validar_rfc ? $JwtAuth->encriptar(strtoupper($rfc)) : NULL;
          $sql_taxID = $validar_taxID ? $JwtAuth->encriptar(strtoupper($taxID)) : NULL;
          $sql_nombre = $JwtAuth->encriptar($nombre);
          $sql_nombre_comercial = $JwtAuth->encriptar($nombre_comercial);

          $acreedor_empleado = $validar_trabajador_vinculado ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$trabajador_vinculado)->value("id") : NULL;
          $acreedor_proveedor = $validar_proveedor_vinculado ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$proveedor_vinculado)->value("id") : NULL;
          $acreedor_deudor = $validar_deudor_vinculado ? DB::table("fnzs_catalogo_deudores")->where("token_cat_deudores",$deudor_vinculado)->value("id") : NULL;
          $acreedor_regimen_fiscal = $regimen_fiscal != '' ? DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $regimen_fiscal)->value("id") : NULL;

          $update_acree = DB::table("fnzs_catalogo_acreedores")
          ->where("token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->limit(1)->update(
            array(
              "acr_rfc" => $sql_rfc,
              "acr_taxId" => $sql_taxID,
              "acr_nacionalidad" => $sql_tipo,
              "acr_titular" => $sql_nombre,
              "acr_nombre_comercial" => $sql_nombre_comercial,

              "acr_cuenta_contable" => $cuenta_contable,
              "acr_habilita_reembolsos" => $habilita_reembolsos ? TRUE : FALSE,
              "acr_fisica_moral" => $sql_subtipo,
              "acr_empleado_vinculado" => $acreedor_empleado,
              "acr_proveedor_vinculado" => $acreedor_proveedor,
              "acr_deudor_vinculado" => $acreedor_deudor,
              "acr_regimen_fiscal" => $acreedor_regimen_fiscal,
            )
          );

          if ($update_acree) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Acreedor con folio $folio_acr ha sido actualizado",
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Acreedor con folio $folio_acr no actualizado, intente más tarde o comuniquese a soporte",
            );
          }
        }
      } else {
        $mensaje_error = '';
        if (!$validar_tipo) {$mensaje_error = 'Error en tipo de acreedor, intente más tarde o comuniquese a soporte';}
        if (!$validar_subtipo) {$mensaje_error = 'Error en tipo de persona, intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre) {$mensaje_error = 'Error en Nombre / Razón social, intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre_comercial) {$mensaje_error = 'Error en Nombre Comercial,intente más tarde o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaAcreedorPapelera(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_acreedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado eliminar es invalida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cat_acreedores = $request->input('token_cat_acreedores');
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catAcree.acr_status" => TRUE,
        "catAcree.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Acreedor no se encuentra registrado, verifique su información o comuniquese a soporte'
        );
      } else {
        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

          $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
          ->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)->count();

          if ($selectPersEmpEmi == 0) {
            $deleteAcreedor = DB::table("fnzs_catalogo_acreedores")
            ->where("acr_status",TRUE)
            ->where("token_cat_acreedores",$vAcr->token_cat_acreedores)
            ->limit(1)->update(array("acr_status" => FALSE,"acr_fecha_delete" => time()));

            if ($deleteAcreedor) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Acreedor con folio $folio_acr ha sido eliminado",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Acreedor con folio $folio_acr no eliminado, intente más tarde o comuniquese a soporte",
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Acreedor con folio $folio_acr no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function acreedoresCatEliminados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
    ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "catAcree.acr_status" => FALSE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])
    ->orderBy('catAcree.acr_fecha_delete', 'desc')
    ->get();

    if ($queryAcreedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron acreedores registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayAcreedores = array();
      foreach ($queryAcreedores as $vAcr) {
        //da_te_default_timezone_set($vAcr->zona_horaria);
        $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

        $arrayForeach = array(
          "token_cat_acreedores" => $vAcr->token_cat_acreedores,
          "folio" => $folio_acr,
          "fecha_delete_acreedor" => gmdate('Y-m-d H:i:s', $vAcr->acr_fecha_delete),
          //"pais" => $vAcr->pais,
          "acr_rfc_generico" => !is_null($vAcr->acr_rfc_generico) ? $vAcr->acr_rfc_generico : 'N/A',
          "acr_rfc" => !is_null($vAcr->acr_rfc) ? $JwtAuth->desencriptar($vAcr->acr_rfc) : 'N/A',
          "acr_taxId" => !is_null($vAcr->acr_taxId) ? $JwtAuth->desencriptar($vAcr->acr_taxId) : 'N/A',
          "acr_titular" => !is_null($vAcr->acr_titular) ? $JwtAuth->desencriptar($vAcr->acr_titular) : 'N/A',
          "nombre_comercial" => !is_null($vAcr->acr_nombre_comercial) && $vAcr->acr_nombre_comercial != '' ? $JwtAuth->desencriptar($vAcr->acr_nombre_comercial) : 'N/A',
          "cuenta_contable" => !empty($vAcr->acr_cuenta_contable) ? $vAcr->acr_cuenta_contable : 'N/A',
          //"utilizado" =>$vAcr->utilizado == TRUE ? true : false,
          "cuenta_contable" => !empty($vAcr->cuenta_contable) ? $JwtAuth->desencriptar($vAcr->cuenta_contable) : '',
          "data_detalle_vista" => false,
          "data_detalle" => [],
          "data_reembolsos" => [],
          "data_pagos" => [],
          "data_anticipos" => [],
        );
        $arrayAcreedores[] = $arrayForeach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'acreedores' => $arrayAcreedores,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarAcreedor(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_acreedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado restaurar es invalida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cat_acreedores = $request->input('token_cat_acreedores');
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catAcree.acr_status" => FALSE,
        "catAcree.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Acreedor no se encuentra registrado, verifique su información o comuniquese a soporte'
        );
      } else {
        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

          $deleteAcreedor = DB::table("fnzs_catalogo_acreedores")
          ->where("acr_status",FALSE)
          ->where("token_cat_acreedores",$vAcr->token_cat_acreedores)
          ->limit(1)->update(array("acr_status" => TRUE,"acr_fecha_delete" => NULL));

          if ($deleteAcreedor) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Acreedor con folio $folio_acr ha sido restaurado",
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Acreedor con folio $folio_acr no restaurado, intente más tarde o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaAcreedorPermanente(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_acreedores' => 'required|string'
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
      //da_te_default_timezone_set('America/Mexico_City');
      $token_cat_acreedores = $request->input('token_cat_acreedores');
      
      $queryAcreedores = DB::table("fnzs_catalogo_acreedores AS catAcree")
      ->join("main_empresas AS emp", "catAcree.acr_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "catAcree.acr_status" => FALSE,
        "catAcree.token_cat_acreedores" => $token_cat_acreedores,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($queryAcreedores->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Acreedor no se encuentra registrado, verifique su información o comuniquese a soporte'
        );
      } else {
        foreach ($queryAcreedores as $vAcr) {
          //da_te_default_timezone_set($vAcr->zona_horaria);
          $folio_acr = 'ACREE-'.$JwtAuth->generarFolio($vAcr->acr_folio).(!is_null($vAcr->acr_post_folio) ? '-'.$vAcr->acr_post_folio : '');

          //vinc_acreedor
          $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
          ->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
          ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)->count();

          if ($selectPersEmpEmi == 0) {
            $queryUsersAcree = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS catTrab", "users.empleado", "=", "catTrab.id")
            ->join("fnzs_catalogo_acreedores AS catAcree", "users.acreedor", "=", "catAcree.id")
            ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
            ->get();

            if (count($queryUsersAcree) == 0) {
              DB::table("teci_usuarios_catalogo AS users")
              ->join("fnzs_catalogo_acreedores AS catAcree", "users.acreedor", "catAcree.id")
              ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
              ->limit(1)->delete();
            } else {
              DB::table("teci_usuarios_catalogo AS users")
              ->join("fnzs_catalogo_acreedores AS catAcree", "users.acreedor", "catAcree.id")
              ->where("catAcree.token_cat_acreedores",$vAcr->token_cat_acreedores)
              ->limit(1)->update(array("users.acreedor" => NULL));
            }

            $deleteAcreedor = DB::table("fnzs_catalogo_acreedores")
            ->where("acr_status",FALSE)
            ->where("token_cat_acreedores",$vAcr->token_cat_acreedores)
            ->limit(1)->delete();

            if ($deleteAcreedor) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Acreedor con folio $folio_acr ha sido eliminado",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Acreedor con folio $folio_acr no eliminado, intente más tarde o comuniquese a soporte",
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Acreedor con folio $folio_acr no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarAcreedor(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'tipo' => 'required|string',
      'subtipo' => 'required|string',
      'rfc' => 'nullable|string',
      'taxID' => 'nullable|string',
      'nombre' => 'required|string',
      'nombre_comercial' => 'required|string',
      'trabajador_vinculado' => 'nullable|string',
      'proveedor_vinculado' => 'nullable|string',
      'deudor_vinculado' => 'nullable|string',
      'email' => 'nullable|string',
      'email_encrypt' => 'nullable|string',
      'access_code' => 'nullable|string',
      'password_code' => 'nullable|string',
      'habilita_reembolsos' => 'required|boolean',
      'cuenta_contable' => 'required|string',
      'regimen_fiscal' => 'nullable|string'
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
      //da_te_default_timezone_set('America/Mexico_City');
      $tipo = $request->input('tipo');
      $subtipo = $request->input('subtipo');
      $rfc = $request->input('rfc');
      $taxID = $request->input('taxID');
      $nombre = $request->input('nombre');
      $nombre_comercial = $request->input('nombre_comercial');
      $trabajador_vinculado = $request->input('trabajador_vinculado');
      $proveedor_vinculado = $request->input('proveedor_vinculado');
      $deudor_vinculado = $request->input('deudor_vinculado');
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
      $validar_deudor_vinculado = isset($deudor_vinculado) && !empty($deudor_vinculado);
      
      if ($validar_tipo && $validar_subtipo && $validar_nombre && $validar_nombre_comercial) {
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

            $sql_tipo = $tipo == 'nacional' ? 'MEX' : 'EXT';
            $sql_subtipo = $subtipo == 'acreeFisica' ? 'PF' : 'PM';
            $sql_rfc = $validar_rfc ? $JwtAuth->encriptar(strtoupper($rfc)) : NULL;
            $sql_taxID = $validar_taxID ? $JwtAuth->encriptar(strtoupper($taxID)) : NULL;
            $sql_nombre = $JwtAuth->encriptar($nombre);
            $sql_nombre_comercial = $JwtAuth->encriptar($nombre_comercial);

            $acreedorExiste = AcreedoresModelo::join("main_empresas AS emp", "fnzs_catalogo_acreedores.acr_empresa", "=", "emp.id")
            ->where([
              'emp.empresa_token' => $empresa,
              'fnzs_catalogo_acreedores.acr_status' => true
            ])
            ->where(function ($query) use ($sql_rfc, $sql_taxID, $sql_nombre,$sql_nombre_comercial) {
              if ($sql_rfc) {
                $query->orWhere('fnzs_catalogo_acreedores.acr_rfc', $sql_rfc);
              }
              if (!empty($sql_taxID)) {
                $query->orWhere('fnzs_catalogo_acreedores.acr_taxId', $sql_taxID);
              }
              if (!empty($sql_nombre)) {
                $query->orWhereRaw('LOWER(fnzs_catalogo_acreedores.acr_titular) = ?', [strtolower($sql_nombre)]);
              }
              if (!empty($sql_nombre_comercial)) {
                $query->orWhereRaw('LOWER(fnzs_catalogo_acreedores.acr_nombre_comercial) = ?', [strtolower($sql_nombre_comercial)]);
              }
            })->exists();

            if (!$acreedorExiste) {
              $fechaAlta = time();

              $folioSistema = DB::select(
                "SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.fnzs_acreedores = TRUE AND fold.empresa = emp.id 
                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                [$empresa, $usuario]
              );
            
              $post_folio_db = DB::select("SELECT acr_post_folio FROM fnzs_catalogo_acreedores WHERE id = (SELECT Max(catAcree.id) FROM fnzs_catalogo_acreedores AS catAcree 
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catAcree.acr_empresa = emp.id 
                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
                [$empresa, $usuario]
              );
            
              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->acr_post_folio);
              $folio_acr = $post_folio == NULL ? 'ACREE-' . $JwtAuth->generarFolio($folio_nuevo) : 'ACREE-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;

              //$correo_electronico = $parametrosArray['correo_electronico'];
              //$empleado_vinculado_token = $parametrosArray['empleado_vinculado_token'];
              //$empleado_vinculado_nombre_completo = $parametrosArray['empleado_vinculado_nombre_completo'];

              $tkn_people_acre = $JwtAuth->encriptarToken($fechaAlta,$tipo,$subtipo,$nombre,$nombre_comercial);

              $acreedor_empleado = $validar_trabajador_vinculado ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$trabajador_vinculado)->value("id") : NULL;
              $acreedor_proveedor = $validar_proveedor_vinculado ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$proveedor_vinculado)->value("id") : NULL;
              $acreedor_deudor = $validar_deudor_vinculado ? DB::table("fnzs_catalogo_deudores")->where("token_cat_deudores",$deudor_vinculado)->value("id") : NULL;
              $acreedor_regimen_fiscal = $regimen_fiscal != '' ? DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $regimen_fiscal)->value("id") : NULL;

              //$tokenAcre = $JwtAuth->encriptarToken($fechaAlta,$tipo,$subtipo,$nombre,$nombre_comercial,$folio_nuevo,$post_folio,$folio_temporal,$folio_prov,$vEmp->id);
              $tokenAcre = $JwtAuth->encriptarToken($fechaAlta,$tipo,$subtipo,$nombre,$vEmp->id).Str::uuid()->toString();
              $creaCatAcr = new AcreedoresModelo();
              $creaCatAcr->token_cat_acreedores  = $tokenAcre;
              $creaCatAcr->acr_folio  = $folio_nuevo;
              $creaCatAcr->acr_post_folio = $post_folio;
              $creaCatAcr->acr_fecha_contab_registro = $fechaAlta;
              $creaCatAcr->acr_rfc_generico = $sql_tipo == 'MEX' ? ($sql_subtipo == 'PF' ? 'xaxx010101000' : 'xax010101000') : 'xexx010101000';
              $creaCatAcr->acr_rfc = $sql_rfc;
              $creaCatAcr->acr_taxId = $sql_taxID;
              $creaCatAcr->acr_nacionalidad = $sql_tipo;
              $creaCatAcr->acr_titular = $sql_nombre;
              $creaCatAcr->acr_nombre_comercial = $sql_nombre_comercial;
              
              $creaCatAcr->acr_cuenta_contable = $cuenta_contable;
              $creaCatAcr->acr_habilita_reembolsos = $habilita_reembolsos ? TRUE : FALSE;
              $creaCatAcr->acr_fisica_moral = $sql_subtipo;
              $creaCatAcr->acr_empleado_vinculado = $acreedor_empleado;
              $creaCatAcr->acr_proveedor_vinculado = $acreedor_proveedor;
              $creaCatAcr->acr_deudor_vinculado = $acreedor_deudor;
              $creaCatAcr->acr_regimen_fiscal = $acreedor_regimen_fiscal;
              $creaCatAcr->acr_status = TRUE;
              $creaCatAcr->acr_empresa = $vEmp->id;
              $savednewAcr = $creaCatAcr->save();

              if ($validar_email && $validar_email_encrypt) {
                $tokenUserNew = $JwtAuth->encriptarToken($creaCatAcr->id,$JwtAuth->encriptar($email),$JwtAuth->encriptar($email_encrypt));
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
                $dataUser->acreedor = $creaCatAcr->id;
                $savedNewUser = $dataUser->save();
              } else {
                if ($validar_trabajador_vinculado) {
                  $dataUser = DB::table("teci_usuarios_catalogo AS users")
                  ->join("vhum_empleados_catalogo AS pers", "users.empleado", "pers.id")
                  ->where("pers.empleado_token",$trabajador_vinculado)
                  ->limit(1)->update(
                    array(
                      'users.acreedor' => $creaCatAcr->id,
                    )
                  );
                }
              }

              if (count($folioSistema) == 0) {
                $insertSistema = DB::table("sos_last_folders")
                ->insert(
                  array(
                    "fnzs_acreedores" => TRUE,
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
                ->where("fold.fnzs_acreedores",TRUE)
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
                'message' => "Acreedor registrado satisfactoriamente con el folio $folio_acr"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'ya existe un acreedor con esta información'
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
        if (!$validar_tipo) {$mensaje_error = 'Error en tipo de acreedor, intente más tarde o comuniquese a soporte';}
        if (!$validar_subtipo) {$mensaje_error = 'Error en tipo de persona, intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre) {$mensaje_error = 'Error en Nombre / Razón social, intente más tarde o comuniquese a soporte';}
        if (!$validar_nombre_comercial) {$mensaje_error = 'Error en Nombre Comercial,intente más tarde o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 
}