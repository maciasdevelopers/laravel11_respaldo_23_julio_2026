<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;

class FNZS_PagoOrdenController extends Controller{
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
    ->whereIn('comp.id', $idCompras)
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->get();

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
    ->get()->keyBy('id');

    $reembolsoSoliAuthMap = DB::table("terc_reembolso_solicitud AS reem_soli")
    ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
    ->where("reem_soli.autorizacion_egr","A")
    ->whereIn('reem_soli.reembolso_main', $idReembolsoMain)
		->orderBy('reem_soli.folio_solicitud', 'DESC')
    ->get()->keyBy('id');

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

    $detailEspNominaMap = DB::table("vhum_nominas_especie_desglose")->whereIn("nomina_especie", $idNominaEspecie)->get()->keyBy('id');

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
		
		$detalleNominaListaMap = DB::table("vhum_nominas_recibos")->whereIn("nomina_main", $idNominaMain)->get()->keyBy('id');

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


		//factura_compra
    $token_o_p = $listOrdenes->pluck('id_pago_orden')->filter()->unique()->toArray();
    $pagosORDVinc = DB::table("fnzs_pagos_pago AS pay")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS o_p_vinc", "pay.id", "=", "o_p_vinc.pago_realizado")
    ->whereIn('o_p_vinc.orden_pago_vinculada', $token_o_p)
    ->select('pay.*', 'o_p_vinc.orden_pago_vinculada')
    ->orderBy('pay.id', 'asc') 
    ->get()
    ->keyBy('orden_pago_vinculada');

    //$queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
    //->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
    //->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
    //->where([
    //  "vinc.vinculo_cancelado" => FALSE,
    //  "order.token_ordenPago" => $orden_de_pago
    //])->get();

		$ordenes_pago = array();
    $id_list = 1;
    foreach ($listOrdenes as $rOrdPag) {
      date_default_timezone_set($rOrdPag->zona_horaria);
      $fecha_contabilizacion_doc_anterior = "";
      $autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
      $fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? date('d-m-Y H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
      $status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

      $factura_relacionada_typo = "---";
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
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$oBuy->fecha_contabilizacion);
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
  
          $vpComp = $compraCompradorEmpMap->get($oBuy->comprador);
          if ($vpComp) {
            $orden_emisor_emp = $vpComp->abrev_nombre;
          }
  
          $vDetBuy = $detalleCompraMap->get($rOrdPag->factura_compra);
          if ($vDetBuy) {
            //echo $vDetBuy->precio_unitario;
            $subtotal_simple = floatval($vDetBuy->precio_unitario) * $vDetBuy->cantidad;
            
            $importe_concepto_simple = $subtotal_simple - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
            $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
            $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
  
            $subtotal_convert = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
            $importe_concepto_convert = $subtotal_convert - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
            $importe_autorizado_final = $importe_autorizado_final + $importe_concepto_convert;
  
            //$totalDetComp = number_format($subtotal,$moneda_decimales,'.', ',');
            //$totalDetCompFormat = number_format($subtotal,$moneda_decimales,'.', ',');
            //$format_precio_unitario = number_format($vDetBuy->precio_unitario,$moneda_decimales,'.', ',');
            //$format_descuento = number_format($vDetBuy->descuento,$moneda_decimales,'.', ',');
            //$format_retenciones = number_format($vDetBuy->retenciones_total,$moneda_decimales,'.', ',');
            //$format_traslados = number_format($vDetBuy->traslados_total,$moneda_decimales,'.', ',');
          }

          $importe_total_anticipo = $importe_total_anticipo + $oBuy->anticipo;
          $importe_total_inicial = $importe_total_inicial - $oBuy->anticipo;
          $importe_autorizado_inicial = $importe_autorizado_inicial - $oBuy->anticipo;
          $importe_autorizado_final = $importe_autorizado_final - $oBuy->anticipo;
        }
      }

      if (!is_null($rOrdPag->factura_venta)) {
        $factura_relacionada_typo = "ventas";
				$oSell = $ventasMap->get($rOrdPag->factura_venta);
				$mostrar_partida = $oSell ? true : false;

        if ($oSell) {
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$oSell->fecha_contabilizacion);
          $factura_relacionada_token = $oSell->token_ventas;
          $factura_relacionada_string = "VENT-" . $JwtAuth->generarFolio($oSell->numero_venta);

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
          //$fecha_contabilizacion_doc_anterior = date('d-m-Y',$rReem->fecha_contabilizacion);
          $factura_relacionada_token = $rReem->token_reem;
          $factura_relacionada_string = 'REEM-'.$JwtAuth->generarFolio($rReem->folio_reem).(!is_null($rReem->post_folio_reem) ? '-'.$rReem->post_folio_reem : '');

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

          $vSoliR = $reembolsoSoliMap->get($rOrdPag->reembolso_main);
          if ($vSoliR) {
            $orden_moneda_inicial_name = $vSoliR->moneda_entrante;
            $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);
            $importe_total_inicial = $importe_total_inicial + $vSoliR->importe_entrante;
          }

          $vSoliA = $reembolsoSoliAuthMap->get($rOrdPag->reembolso_main);
          if ($vSoliA) {
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
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$oAnt->ant_fecha_contabilizacion);
          $factura_relacionada_token = $oAnt->uuid_anticipo;
          $factura_relacionada_string = 'ANT-'.$JwtAuth->generarFolio($oAnt->folio_anticipo);

          $vopAnt = $anticiposProveedorMap->get($oAnt->uuid_anticipo);
          if ($vopAnt) {
            $orden_emisor_personal_folio = 'PRV-'.$JwtAuth->generarFolio($vopAnt->folio) . ($vopAnt->post_folio != NULL ? '-'.$vopAnt->post_folio : '');
            $orden_emisor_personal_nombre = $JwtAuth->desencriptar($vopAnt->nombre_extendido);
            $orden_emisor_personal_nombre_comercial = !is_null($vopAnt->nombre_com) ? $JwtAuth->desencriptar($vopAnt->nombre_com) : '';
          }

          $orden_moneda_inicial_name = $oAnt->moneda_code;
          $orden_moneda_inicial_decimales = $oAnt->moneda_decimales;
          $importe_total_inicial = $importe_total_inicial + ($oAnt->monto_total * $oAnt->tipo_cambio);

          $orden_moneda_autorizado_inicial_tkn = $oAnt->moneda_code;
          $orden_moneda_autorizado_inicial_name = $oAnt->moneda_code;
          $orden_moneda_autorizado_inicial_decimales = $oAnt->moneda_decimales;

          $importe_autorizado_inicial = $importe_autorizado_inicial + $oAnt->monto_total;
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
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$ordAnt->ant_fecha_contabilizacion);
          $factura_relacionada_token = $ordAnt->uuid_anticipo;
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
          $importe_total_inicial = $importe_total_inicial + ($ordAnt->monto_total * $ordAnt->tipo_cambio);

          $orden_moneda_autorizado_inicial_tkn = $ordAnt->moneda_code;
          $orden_moneda_autorizado_inicial_name = $ordAnt->moneda_code;
          $orden_moneda_autorizado_inicial_decimales = $ordAnt->moneda_decimales;

          $importe_autorizado_inicial = $importe_autorizado_inicial + $ordAnt->monto_total;
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

            $fecha_contabilizacion_doc_anterior = date('d-m-Y',$vEspNom->nomina_esp_fecha_contabilizacion);
            $factura_relacionada_token = $vEspNom->token_nominas_especie;
            $factura_relacionada_string = 'NOM-ES-'.$JwtAuth->generarFolio($vEspNom->nomina_esp_folio_interior).(!is_null($vEspNom->nomina_esp_subfolio) ? '-'.$vEspNom->nomina_esp_subfolio : '');
            
						$vNomDetEsp = $detailEspNominaMap->get($rOrdPag->nomina_en_especie);
            if ($vNomDetEsp) {
              $orden_moneda_inicial_name = $vNomDetEsp->nomina_esp_moneda;
              $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
              $orden_moneda_autorizado_inicial_name = $vNomDetEsp->nomina_esp_moneda;
              $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
              $orden_moneda_autorizado_final_name = $vNomDetEsp->nomina_esp_moneda;
              $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);
              $importe_concepto_simple = floatval($vNomDetEsp->total_en_especie);
              $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
              $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;

              $importe_autorizado_final = $importe_autorizado_final + floatval($vNomDetEsp->total_en_especie);
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
            
            $fecha_contabilizacion_doc_anterior = date('d-m-Y',$vNom->nomina_fecha_contabilizacion);
            $factura_relacionada_token = $vNom->token_nominas_periodos;
            $factura_relacionada_string = 'NOM-EF-'.$JwtAuth->generarFolio($vNom->nomina_folio_interior).(!is_null($vNom->nomina_subfolio) ? '-'.$vNom->nomina_subfolio : '');
            
						$vNomDetMain = $detalleNominaListaMap->get($rOrdPag->nomina_main);
            if ($vNomDetMain) {
              $orden_moneda_inicial_name = $vNomDetMain->nomina_moneda;
              $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
              $orden_moneda_autorizado_inicial_name = $vNomDetMain->nomina_moneda;
              $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);
              $orden_moneda_autorizado_final_name = $vNomDetMain->nomina_moneda;
              $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($vNomDetMain->nomina_moneda);

              $importe_concepto_simple = floatval($vNomDetMain->total_efectivo);
              $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
              $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;

              $importe_autorizado_final = $importe_autorizado_final + floatval($vNomDetMain->total_efectivo);
            }
          }
        }
      }

      if (!is_null($rOrdPag->impuesto_sobre_nomina)) {
        $factura_relacionada_typo = "impuestos sobre nómina";
				$oIsn = $isNominaMap->get($rOrdPag->impuesto_sobre_nomina);
        $mostrar_partida = $oIsn ? true : false;

        if ($oIsn) {
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$oIsn->nomi_imp_fecha_contabilizacion);
					
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
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$oIMMS->aport_ssocial_fecha_contabilizacion);
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
          $fecha_contabilizacion_doc_anterior = date('d-m-Y',$oDecFed->declaracion_fecha_contabilizacion);
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
      //pagos_realizados
      $op_vincul = $pagosORDVinc->get($rOrdPag->id_pago_orden);
      $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? date('d-m-Y', $rOrdPag->orden_terminada_fecha) : "---";

      $pagos_realizados = DB::table("fnzs_pagos_pago AS pay")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
      ->where([
				"vinc.vinculo_cancelado" => FALSE,
				"order.token_ordenPago" => $rOrdPag->token_ordenPago
			])
      ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])
      ->sum('vinc.orden_pago_monto');

      $lista_pagos_realizados = $JwtAuth->pagosDoneBYOrden($rOrdPag->token_ordenPago);
      $pago_restante = count($lista_pagos_realizados) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

      //var_dump($op_vincul);
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
          "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
          "fecha_registro" => date('d-m-Y H:i:s', $rOrdPag->fecha_sistema_ordenp),
          
          "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
          "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? date('d-m-Y',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
          "factura_relacionada_string" => $factura_relacionada_string,
          "factura_relacionada_typo" => $factura_relacionada_typo,
          "factura_relacionada_token" => $factura_relacionada_token,
          "fecha_contabilizacion_doc_anterior" => $fecha_contabilizacion_doc_anterior,
          
          //tercero
          "orden_emisor_personal_token" => $orden_emisor_personal_token,
          "orden_emisor_personal_folio" => $orden_emisor_personal_folio,
          "orden_emisor_personal_nombre" => $orden_emisor_personal_nombre,
          "orden_emisor_personal_nombre_comercial" => $orden_emisor_personal_nombre_comercial,
          
          "orden_emisor_emp" => $orden_emisor_emp,
          
          //autorizacion
          "autorizacion_pay_text" => "",
          "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
          "autorizacion_pay" => $autorizacion_pay,
          "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',

          //pagado
          "pago_anticipado" => "$".number_format($importe_total_anticipo, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "status_pago" => $status_pay_bool,
          "status_pago_date" => $status_pay_date,
          "pago_realizado_folio" => $op_vincul ? "PAGO-" . $JwtAuth->generarFolio($op_vincul->folio_pagos) : '',
          "pago_realizado_fecha_contabilizacion" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_contabilizacion'] : '',
          //proveedor
          "pago_realizado_proveedor_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['proveedor_token'] : '',
          "pago_realizado_proveedor_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['proveedor_name'] : '',
          //acreedor
          "pago_realizado_acreedor_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['acreedor_token'] : '',
          "pago_realizado_acreedor_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['acreedor_name'] : '',
          //forma_pago
          "pago_realizado_forma_pago_vinculada" => count($lista_pagos_realizados) > 0 ? ($lista_pagos_realizados[0]['acreedor_name'] == '' ? $lista_pagos_realizados[0]['forma_pago_vinculada'] : '') : '',
          "pago_realizado_forma_pago_cfdi" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['forma_pago_cfdi'] : '',
          "pago_realizado_metodo_pago_cfdi" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['metodo_pago_cfdi'] : '',
          "pago_realizado_forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,

          "pago_realizado_monto" => "$".number_format($pagos_realizados, $orden_moneda_autorizado_final_decimales, '.', '')." $orden_moneda_autorizado_final_name",
          "pago_realizado_tipo_cambio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['tipo_cambio'] : '',

          //"importe_total_inicial_simple" => $importe_total_inicial,
          //"orden_moneda_inicial_name" => $orden_moneda_inicial_name,
          //"importe_total_inicial" => $this->muestraCantidadesConMoneda($importe_total_inicial,$orden_moneda_inicial_name,$orden_moneda_inicial_decimales),
          //"importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
          //"orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
          //"orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
          //"importe_autorizado_inicial_format" => $this->muestraCantidadesConMoneda($importe_autorizado_inicial,$orden_moneda_autorizado_inicial_name,$orden_moneda_autorizado_inicial_decimales),
          ////$orden_moneda_inicial_decimales = 0;
          //"importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
          //"importe_autorizado_final" => $this->muestraCantidadesConMoneda($importe_autorizado_final,$orden_moneda_autorizado_final_name,$orden_moneda_autorizado_final_decimales),
          //"orden_moneda_final_autorizada_name" => $orden_moneda_autorizado_final_name,
          //"importe_restante" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
          //"importe_restante_format" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          //"importe_por_pagar" => "0.00",
          //"debe_simple" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
          //"debe_format" => "$".number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          ////$orden_moneda_final_decimales = 0;
          //"empresa" => "", //empresa
          //"comprador" => "", //comprador
          //"open_inside" => false, //comprador
          //"detail_orden" => [], //comprador
          //"autorizacion_proceso" => false, //comprador
          ////pagos_realizados
          //"lista_pagos_realizados" => $lista_pagos_realizados,
          //"pago_realizado_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['token_pagos'] : '',
          //"pago_realizado_status" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['status_pago'] : '',
          //"pago_realizado_folio_operacion" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['folio_operacion'] : '',
          //"pago_realizado_fecha_pago" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_pago'] : '',
          //"pago_realizado_observaciones" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['observacionesPago'] : '',
          //"pago_realizado_moneda" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['p_moneda'] : '',
          //"pago_realizado_destino" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['destino'] : '',
          //"pago_realizado_concepto" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['concepto'] : '',
          ////cliente
          //"pago_realizado_cliente_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['cliente_token'] : '',
          //"pago_realizado_cliente_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['cliente_name'] : '',
          ////empleado
          //"pago_realizado_empleado_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['empleado_token'] : '',
          //"pago_realizado_empleado_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['empleado_name'] : '',
          ////personal_pago
          //"pago_realizado_personal_pago_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_token'] : '',
          //"pago_realizado_personal_pago_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_folio'] : '',
          //"pago_realizado_personal_pago_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_pago_name'] : '',
          //"pago_realizado_pago_autorizado" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['pago_autorizado'] : '',
          //"pago_realizado_fecha_pago_auth" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['fecha_pago_auth'] : '',
          ////personal_autoriza
          //"pago_realizado_personal_autoriza_token" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_token'] : '',
          //"pago_realizado_personal_autoriza_folio" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_folio'] : '',
          //"pago_realizado_personal_autoriza_name" => count($lista_pagos_realizados) > 0 ? $lista_pagos_realizados[0]['personal_autoriza_name'] : '',
        );
        $ordenes_pago[] = $row_ordenPay;
        ++$id_list;
      }
    }
    return $ordenes_pago;
	}

//listas generales
	public function listaGeneralOrdenesPago(Request $request){
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
      
      $listOrdenes = DB::table('fnzs_pagos_orden as orden')
      ->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
      ->where([
        'orden.status_ordenPago' => TRUE,
        'emp.empresa_token' => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('orden.id', 'desc')
      ->select('orden.id AS id_pago_orden','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $ordenes_pago_lista_general = $this->eachGeneralOrdenesPago($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista_general)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

//pendientes
	public function listaOrdenesPendientes(Request $request){
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
			
			$listOrdenes = DB::table('fnzs_pagos_orden as orden')
			->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
			->where([
				'orden.status_ordenPago' => TRUE,
				'orden.autorizacion_pay' => FALSE,
				'emp.empresa_token' => $empresa
			])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
			->orderBy('orden.id', 'desc')
			->select('orden.*','emp.empresa_token', 'emp.zona_horaria')
			->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $ordenes_pago_lista_general = $this->eachGeneralOrdenesPago($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista_general)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

//liberadas
	public function listaOrdenesLiberadas(Request $request){
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
			
			$listOrdenes = DB::table('fnzs_pagos_orden as orden')
			->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
			->where([
				'orden.status_ordenPago' => TRUE,
				'orden.autorizacion_pay' => TRUE,
				'orden.orden_terminada_bool' => FALSE,
				'orden.orden_bloqueada' => FALSE,
				'emp.empresa_token' => $empresa
			])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
			->orderBy('orden.id', 'desc')
			->select('orden.*','emp.empresa_token', 'emp.zona_horaria')
			->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $ordenes_pago_lista = $this->eachGeneralOrdenesPago($listOrdenes,$empresa,$usuario,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function listaOrdenForCompra(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$ordenes_pago_lista = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_compras' => 'required|string',
				'token_ordenPago' => 'required|string'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La información del usuario invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$token_compras = $parametrosArray['token_compras'];
				$token_ordenPago = $parametrosArray['token_ordenPago'];

				$listOrdenes = DB::select("SELECT orden.*,emp.empresa_token,emp.zona_horaria FROM fnzs_pagos_orden AS orden JOIN eegr_compras AS buy JOIN main_empresas AS emp WHERE orden.token_ordenPago = ? AND orden.status_ordenPago = TRUE 
          AND orden.autorizacion_pay = TRUE AND orden.orden_terminada_bool = FALSE AND orden.factura_compra = buy.id AND buy.token_compras = ? AND orden.empresa = emp.id AND emp.empresa_token = ? ORDER BY orden.fecha_autorizacion_pay DESC",
          [$token_ordenPago,$token_compras,$usuario->empresa_token]);
          
				$id_list = 1;
				foreach ($listOrdenes as $rOrdPag) {
					date_default_timezone_set($rOrdPag->zona_horaria);
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? date('d-m-Y H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;
					$status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? date('d-m-Y H:i:s', $rOrdPag->orden_terminada_fecha) : "---";

					$factura_relacionada_typo = "---";
					$factura_relacionada_token = "---";
					$factura_relacionada_string = "---";

					$orden_emisor_emp = "---";
					$orden_emisor_personal = "---";

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
          $factura_relacionada_typo = "compras";
          $query_buy_order = DB::table("fnzs_pagos_orden AS order")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->whereIn('buy.id', function ($query) {
            $query->select('numero_compra')->from('eegr_compras_detalle');
          })
          ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])->get();

          $mostrar_partida = count($query_buy_order) == 1 ? true : false;
          foreach ($query_buy_order as $oBuy) {
            $query_buy_comprador_pers = DB::table("eegr_compras AS buy")
            ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
            ->where(["buy.token_compras" => $oBuy->token_compras])->get();

            foreach ($query_buy_comprador_pers as $vpComp) {
              $orden_emisor_personal_token = $vpComp->token_cat_proveedores;
              $proveedor_folio = 'PRV-'.$JwtAuth->generarFolio($vpComp->folio) . ($vpComp->post_folio != NULL ? '-'.$vpComp->post_folio : '');
              $proveedor_nombre = $JwtAuth->desencriptar($vpComp->nombre_extendido);
              $orden_emisor_personal = $proveedor_folio." ".$proveedor_nombre;
            }

            $orden_moneda_inicial_name = $oBuy->moneda;
            $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);
            $orden_moneda_autorizado_inicial_name = $oBuy->moneda;
            $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);
            $orden_moneda_autorizado_final_name = $oBuy->moneda;
            $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);

            $factura_relacionada_token = $oBuy->token_compras;
            $factura_relacionada_string = "COMP-" . $JwtAuth->generarFolio($oBuy->folio_compra);

            $query_buy_comprador_emp = DB::table("eegr_compras AS buy")
              ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
              ->join("sos_personas AS people", "emp.persona", "=", "people.id")
              ->where(["buy.token_compras" => $oBuy->token_compras])->get();

            foreach ($query_buy_comprador_emp as $vComp) {
              $orden_emisor_emp = $vComp->abrev_nombre;
            }

            $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
              ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
              ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
              ->where([
                'comp.token_compras' => $oBuy->token_compras,
                'emp.empresa_token' => $usuario->empresa_token,
                'users.usuario_token' => $usuario->user_token,
              ])->get();

            foreach ($detalleCompraLista as $vDetBuy) {
              $subtotal_simple = floatval($vDetBuy->precio_unitario) * $vDetBuy->cantidad;
              $importe_concepto_simple = $subtotal_simple - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
              $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
              $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
              $subtotal_convert = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
              $importe_concepto_convert = $subtotal_convert - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
              $importe_autorizado_final = $importe_autorizado_final + $importe_concepto_convert;

              //$totalDetComp = number_format($subtotal,$moneda_decimales,'.', ',');
              //$totalDetCompFormat = number_format($subtotal,$moneda_decimales,'.', ',');
              //$format_precio_unitario = number_format($vDetBuy->precio_unitario,$moneda_decimales,'.', ',');
              //$format_descuento = number_format($vDetBuy->descuento,$moneda_decimales,'.', ',');
              //$format_retenciones = number_format($vDetBuy->retenciones_total,$moneda_decimales,'.', ',');
              //$format_traslados = number_format($vDetBuy->traslados_total,$moneda_decimales,'.', ',');

            }
          }

          //pagos_realizados
          $pagos_realizados = 0;
          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])->get();

          foreach ($queryPagosDone as $vPayDone) {
            $pagos_realizados += $vPayDone->orden_pago_monto;
          }

          $pago_restante = count($queryPagosDone) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;
          
					if ($mostrar_partida) {
						$row_ordenPay = array(
							"id" => $id_list,
							"token_ordenPago" => $rOrdPag->token_ordenPago,
							"folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
							"fecha_contabilizacion_ordenPago" => date('d-m-Y',$rOrdPag->fecha_contabilizacion_ordenPago),
							"fecha_registro" => date('d-m-Y H:i:s', $rOrdPag->fecha_sistema_ordenp),
							"orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
							"autorizacion_pay" => $autorizacion_pay,
							"autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
							"fecha_autorizacion_pay" => $fecha_autorizacion_pay,
							"factura_relacionada_typo" => $factura_relacionada_typo,
							"factura_relacionada_token" => $factura_relacionada_token,
							"factura_relacionada_string" => $factura_relacionada_string,
							"orden_emisor_emp" => $orden_emisor_emp,
							"orden_emisor_personal" => $orden_emisor_personal,
							"orden_emisor_personal_token" => $orden_emisor_personal_token,
	
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
							"moneda_autorizado_final_decimales" => $orden_moneda_autorizado_final_decimales,
							//$orden_moneda_final_decimales = 0;
	
							"status_pago" => $status_pay_bool,
							"status_pago_date" => $status_pay_date,
							"empresa" => "", //empresa
							"comprador" => "", //comprador
							"open_inside" => false, //comprador
							"detail_orden" => [], //comprador
							"pago_proceso" => false, //comprador
						);
						$ordenes_pago_lista[] = $row_ordenPay;
						++$id_list;
					}
				}

				$dataMensaje = array(
					'orden_generada' => $ordenes_pago_lista,
					'code' => 200,
					'status' => 'success'
				);
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'No fue posible procesar los datos recibidos'
			);
		}

		return response()->json($dataMensaje, $dataMensaje['code']);
	}

//concluidas
	public function listaOrdenesConcluidas(Request $request){
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
			
			$listOrdenes = DB::table('fnzs_pagos_orden as orden')
			->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
			->where([
				'orden.status_ordenPago' => TRUE,
				'orden.autorizacion_pay' => TRUE,
				'orden.orden_terminada_bool' => TRUE,
				'orden.orden_bloqueada' => FALSE,
				'emp.empresa_token' => $empresa
			])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("orden.doc_anterior_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
			->orderBy('orden.id', 'desc')
			->select('orden.*','emp.empresa_token', 'emp.zona_horaria')
			->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $ordenes_pago_lista = $this->eachGeneralOrdenesPago($listOrdenes,$empresa,$usuario,$JwtAuth);

				$dataMensaje = array(
          'ordenes' => collect($ordenes_pago_lista)->sortBy('id')->values(),
          'code' => 200,
          'status' => 'success'
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function muestraCantidadesConMoneda($orden_importe,$orden_moneda_code,$orden_moneda_decimales){
		return $orden_importe > 0 && $orden_moneda_code != '---' ? "$".number_format($orden_importe, $orden_moneda_decimales, '.', ',')." $orden_moneda_code" : '$0.00 MXN';
	}

	public function autorizarOrdenesPago(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'ordenes' => 'required|array'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La información del usuario invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
				$orden_pago = $parametrosArray["ordenes"];

				for ($i = 0; $i < count($orden_pago); $i++) {
					$ordPay = $orden_pago[$i]["token_ordenPago"];
					$listOrdenes = DB::table("fnzs_pagos_orden AS orden")
						->join("main_empresas AS emp", "orden.empresa", "emp.id")
						->where(["orden.token_ordenPago" => $ordPay, "orden.status_ordenPago" => TRUE])
						->get();

					foreach ($listOrdenes as $rOrdPag) {
						$f_auth = time();
						$update_auth_true = DB::table("fnzs_pagos_orden AS orden")
							->where(["orden.token_ordenPago" => $rOrdPag->token_ordenPago])
							->limit(1)->update(array("orden.autorizacion_pay" => TRUE, "orden.fecha_autorizacion_pay" => $f_auth));
						if ($update_auth_true) {
							$reemOrdenes = DB::table("fnzs_pagos_orden AS orden")
							->join("terc_reembolso_main AS reem_main", "orden.reembolso_main", "reem_main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "orden.reembolso_solicitud", "reem_soli.id")
							->where(["orden.token_ordenPago" => $ordPay])->get();

							foreach ($reemOrdenes as $voReem) {
								$folio_reem = 'REEM-' . $JwtAuth->generarFolio($voReem->folio_reem);
								if ($voReem->post_folio_reem != NULL) $folio_reem = $folio_reem . '-' . $voReem->post_folio_reem;

								$folio_soli_reem = $JwtAuth->generarFolio($voReem->folio_solicitud);

								$vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
									->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
									->where(["reem_main.token_reem" => $voReem->token_reem])->get();

								foreach ($vhPersEmpEmi as $vpVH) {
									$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
									$JwtAuth->notificacionPushDevices($vpVH->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
								}

								$eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
									->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
									->where(["reem_main.token_reem" => $voReem->token_reem])->get();

								foreach ($eegrPersEmpEmi as $vpEGR) {
									$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
									$JwtAuth->notificacionPushDevices($vpEGR->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
								}

								$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
									->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
									->where(["reem_main.token_reem" => $voReem->token_reem])->get();

								foreach ($selectPersEmpEmi as $vPemi) {
									$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
									$JwtAuth->notificacionPushDevices($vPemi->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
								}
							}

							$response_status = "success";
							$response_message = "Orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " fue aprobada satisfactoriamente";
							$fecha_auto_response = date('d-m-Y H:i:s', $f_auth);
						} else {
							$response_status = "error";
							$response_message = "Aprobación de orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " no fue registrada, intente nuevamente o comuníquese a soporte";
							$fecha_auto_response = "---";
						}
						$dataMensaje = array("status" => $response_status, "code" => 200, "message" => $response_message, "response_fauth" => $fecha_auto_response);
					}
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'No fue posible procesar los datos recibidos'
			);
		}

		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function autorizarOrdenPago(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'orden_pago' => 'required|string'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La información del usuario invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
				$orden_pago = $parametrosArray["orden_pago"];

				$listOrdenes = DB::table("fnzs_pagos_orden AS orden")
					->join("main_empresas AS emp", "orden.empresa", "emp.id")
					->where(["orden.token_ordenPago" => $orden_pago, "orden.status_ordenPago" => TRUE])
					->get();

				foreach ($listOrdenes as $rOrdPag) {
					$f_auth = time();
					$update_auth_true = DB::table("fnzs_pagos_orden AS orden")
						->where(["orden.token_ordenPago" => $rOrdPag->token_ordenPago])
						->limit(1)->update(array("orden.autorizacion_pay" => TRUE, "orden.fecha_autorizacion_pay" => $f_auth));
					if ($update_auth_true) {
						$reemOrdenes = DB::table("fnzs_pagos_orden AS orden")
							->join("terc_reembolso_main AS reem_main", "orden.reembolso_main", "reem_main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "orden.reembolso_solicitud", "reem_soli.id")
							->where(["orden.token_ordenPago" => $orden_pago])->get();

						foreach ($reemOrdenes as $voReem) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($voReem->folio_reem);
							if ($voReem->post_folio_reem != NULL) $folio_reem = $folio_reem . '-' . $voReem->post_folio_reem;

							$folio_soli_reem = $JwtAuth->generarFolio($voReem->folio_solicitud);

							$vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $voReem->token_reem])->get();

							foreach ($vhPersEmpEmi as $vpVH) {
								$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
								$JwtAuth->notificacionPushDevices($vpVH->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
							}

							$eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $voReem->token_reem])->get();

							foreach ($eegrPersEmpEmi as $vpEGR) {
								$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
								$JwtAuth->notificacionPushDevices($vpEGR->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
							}

							$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $voReem->token_reem])->get();

							foreach ($selectPersEmpEmi as $vPemi) {
								$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
								$JwtAuth->notificacionPushDevices($vPemi->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
							}
						}
            
            if ($rOrdPag->factura_compra != NULL) {
              $query_buy_order = DB::table("fnzs_pagos_orden AS order")
              ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
              ->whereIn('buy.id', function ($query) {
                $query->select('numero_compra')->from('eegr_compras_detalle');
              })
              ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])->get();
  
              foreach ($query_buy_order as $oBuy) {
                $importe_total_inicial = 0;
                $importe_autorizado_inicial = 0;
                $importe_autorizado_final = 0;
                $orden_moneda_inicial_name = $oBuy->moneda;
                $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($oBuy->moneda);

                $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
                ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
                ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
                ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                ->where([
                  'comp.token_compras' => $oBuy->token_compras,
                  'emp.empresa_token' => $usuario->empresa_token,
                  'users.usuario_token' => $usuario->user_token,
                ])->get();
  
                foreach ($detalleCompraLista as $vDetBuy) {
                  $subtotal_simple = floatval($vDetBuy->precio_unitario) * $vDetBuy->cantidad;
                  
                  $importe_concepto_simple = $subtotal_simple - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
                  $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
                  $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
  
                  $subtotal_convert = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
                  $importe_concepto_convert = $subtotal_convert - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
                  $importe_autorizado_final = $importe_autorizado_final + $importe_concepto_convert;
                }
                $importe_total_inicial = $importe_total_inicial - $oBuy->anticipo;
                $importe_autorizado_inicial = $importe_autorizado_inicial - $oBuy->anticipo;
                $importe_autorizado_final = $importe_autorizado_final - $oBuy->anticipo;
                //echo $importe_autorizado_final;exit;
                if ($importe_autorizado_final == 0) {
									$terminaOrden = DB::table("fnzs_pagos_orden")->where("token_ordenPago",$rOrdPag->token_ordenPago)->limit(1)->update(array(
										"orden_terminada_bool" => TRUE,
										"orden_terminada_fecha" => time(),
										//"fecha_contabilizacion_ordenPago" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
									));
                }
              }
            }

						$response_status = "success";
						$response_message = "Orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " fue aprobada satisfactoriamente";
						$fecha_auto_response = date('d-m-Y H:i:s', $f_auth);
					} else {
						$response_status = "error";
						$response_message = "Aprobación de orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " no fue registrada, intente nuevamente o comuníquese a soporte";
						$fecha_auto_response = "---";
					}
					$dataMensaje = array("status" => $response_status, "code" => 200, "message" => $response_message, "response_fauth" => $fecha_auto_response);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'No fue posible procesar los datos recibidos'
			);
		}

		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function desautorizarOrdenPago(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'orden_pago' => 'required|string'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La información del usuario invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
				$orden_pago = $parametrosArray["orden_pago"];

				$listOrdenes = DB::table("fnzs_pagos_orden AS orden")
					->join("main_empresas AS emp", "orden.empresa", "emp.id")
					->where(["orden.token_ordenPago" => $orden_pago, "orden.status_ordenPago" => TRUE])
					->get();

				foreach ($listOrdenes as $rOrdPag) {
					$update_auth_false = DB::table("fnzs_pagos_orden AS orden")
						->where(["orden.token_ordenPago" => $rOrdPag->token_ordenPago])
						->limit(1)->update(array("orden.autorizacion_pay" => FALSE, "orden.fecha_autorizacion_pay" => NULL));
					if ($update_auth_false) {
						$reemOrdenes = DB::table("fnzs_pagos_orden AS orden")
							->join("terc_reembolso_main AS reem_main", "orden.reembolso_main", "reem_main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "orden.reembolso_solicitud", "reem_soli.id")
							->where(["orden.token_ordenPago" => $orden_pago])->get();

						foreach ($reemOrdenes as $voReem) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($voReem->folio_reem);
							if ($voReem->post_folio_reem != NULL) $folio_reem = $folio_reem . '-' . $voReem->post_folio_reem;

							$folio_soli_reem = $JwtAuth->generarFolio($voReem->folio_solicitud);

							$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $voReem->token_reem])->get();

							foreach ($selectPersEmpEmi as $vPemi) {
								$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue desaprobada para el pago correspondiente";
								$JwtAuth->notificacionPushDevices($vPemi->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
							}
						}

						$response_status = "success";
						$response_message = "Orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " fue desaprobada satisfactoriamente";
					} else {
						$response_status = "error";
						$response_message = "Desaprobación de orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " no fue registrada, intente nuevamente o comuníquese a soporte";
					}
					$dataMensaje = array("status" => $response_status, "code" => 200, "message" => $response_message, "response_fauth" => "---");
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'No fue posible procesar los datos recibidos'
			);
		}

		return response()->json($dataMensaje, $dataMensaje['code']);
	}



  //cancelaciones
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
              "ord_pag.pago_orden_cancelada" => TRUE,
              "pago_orden_cancel_user" => $user_jerarquia,
              "ord_pag.pago_orden_cancel_fecha_cont" => $fechaContabilizacionUnix,
              "ord_pag.pago_orden_cancel_comentarios" => $comentarios_encriptados
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
      //->whereIn('order.id', function ($query) {
      //  $query->select('orden_pago_vinculada')->from('fnzs_pagos_pago_ordenes_vinculadas')->where("vinculo_cancelado",FALSE);
      //})
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
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
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
                "acre_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico( $vMovDone->acre_fecha_contabilizacion),
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
              "fecha_pago" => $JwtAuth->mostrarUnixAFechaMexico($vPayDone->fecha_pago),
					  	"fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? $JwtAuth->mostrarUnixAFechaMexico( $vPayDone->fecha_contabilizacion) : "",
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
						"orden_pago_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_contabilizacion_ordenPago),
						"orden_pago_fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_sistema_ordenp),
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
            //  "fecha_contabilizacion_movimiento" => $JwtAuth->mostrarUnixAFechaMexico($origen->fecha_contabilizacion_movimiento),
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
            //  "fecha_contabilizacion_movimiento" => $JwtAuth->mostrarUnixAFechaMexico($final->fecha_contabilizacion_movimiento),
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
            "movimiento_cp_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vMov->movimiento_cp_fecha_contabilizacion),
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

	public function generaPagoSimple(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"order_importe" => "required|numeric",
				"fecha_contabilizacion" => "required|string",
				"order_caja" => "array",
				"order_cuenta_bancaria" => "array",
				"order_monedero_electronico" => "array",
				"anticipos" => "numeric",
				"saldos" => "array",
				"prv_token" => "string",
				"saldo_a_favor" => "required|string",
				"order_moneda" => "required|string",
				"order_tipo_cambio" => "required|numeric",
				"order_forma_pago" => "required|string",
				"order_ordenes_pago" => "required|array",
				"order_observacion" => "required|string"
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La información del usuario invalida'.$validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$order_importe = $parametrosArray["order_importe"];
				$fecha_contabilizacion = $parametrosArray["fecha_contabilizacion"];
				$order_caja = $parametrosArray["order_caja"];
				$order_cuenta_bancaria = $parametrosArray["order_cuenta_bancaria"];
				$order_cuenta_monedero = $parametrosArray["order_monedero_electronico"];
				$anticipos_aplicados = $parametrosArray["anticipos"];
				$saldos_aplicados = $parametrosArray["saldos"];
				$saldo_a_favor = $parametrosArray["saldo_a_favor"];
				$prv_token = $parametrosArray["prv_token"];
				//echo "saldo_a_favor $saldo_a_favor"; exit;
				$order_moneda = $parametrosArray["order_moneda"];
				$order_tipo_cambio = $parametrosArray["order_tipo_cambio"];
				$order_forma_pago = $parametrosArray["order_forma_pago"];
				$order_ordenes_pago = $parametrosArray["order_ordenes_pago"];
				$order_observacion = $parametrosArray["order_observacion"];

				$valide_order_importe = isset($order_importe) && !empty($order_importe) && preg_match($JwtAuth->filtroCostoPrecio(),$order_importe);
				$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
				$valide_order_prv_token = isset($prv_token) && !empty($prv_token);
				$valide_order_caja = isset($order_caja) && !empty($order_caja);
				$valide_order_cuenta_bancaria = isset($order_cuenta_bancaria) && !empty($order_cuenta_bancaria);
				$valide_order_cuenta_monedero = isset($order_cuenta_monedero) && !empty($order_cuenta_monedero);
        //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r'.$saldo_a_favor]);
				$valide_saldo_a_favor = isset($saldo_a_favor) && !empty($saldo_a_favor) && preg_match($JwtAuth->filtroAlfaNumerico(),$saldo_a_favor);
				$valide_order_moneda = isset($order_moneda) && !empty($order_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$order_moneda);
				$valide_order_tipo_cambio = isset($order_tipo_cambio) && !empty($order_tipo_cambio) && preg_match($JwtAuth->filtroCostoPrecio(),$order_tipo_cambio);
				$valide_order_forma_pago = isset($order_forma_pago) && !empty($order_forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$order_forma_pago);
				$valide_order_ordenes_pago = isset($order_ordenes_pago) && !empty($order_ordenes_pago) && count($order_ordenes_pago) > 0;
				$valide_order_observacion = isset($order_observacion) && !empty($order_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $order_observacion);
				$fechaSistema = time();

				if ($valide_order_importe && $valide_fecha_contabilizacion && $valide_saldo_a_favor && $valide_order_moneda && $valide_order_tipo_cambio && $valide_order_forma_pago && $valide_order_ordenes_pago && $valide_order_observacion) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser  
            JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          foreach ($queryEmp as $vEmp) {
            date_default_timezone_set($vEmp->zona_horaria);

            $folioPagos = DB::select("SELECT IF (max(folio_pagos) IS NOT NULL,(max(folio_pagos)+1),1) AS folio FROM fnzs_pagos_pago AS payment JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE payment.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$usuario->empresa_token, $usuario->user_token]
            );
						
						$tokenPago = $JwtAuth->encriptarToken($order_importe.$order_observacion.$fechaSistema);
						$folio_pago_generar = "PAY-".$JwtAuth->generarFolio($folioPagos[0]->folio);

            $idProveedor = $valide_order_prv_token ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$prv_token)->value("id") : NULL;
            $idCliente = $valide_order_prv_token ? DB::table("ingr_catalogo_clientes")->where("token_cat_clientes",$prv_token)->value("id") : NULL;
            $idEmpleado = $valide_order_prv_token ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$prv_token)->value("id") : NULL;

            if ($idProveedor != "") {
              $concepto_pago = $JwtAuth->encriptar("Pago a proveedores");
            } elseif ($idCliente != "") {
              $concepto_pago = $JwtAuth->encriptar("Pago a clientes");
            } elseif ($idEmpleado != "") {
              $concepto_pago = $JwtAuth->encriptar("Reembolso a empleados");
            } else {
              $concepto_pago = NULL;
            }

            $insertPagoMon = DB::table("fnzs_pagos_pago")
            ->insert(
              array(
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
                "vinc_proveedor" => $idProveedor != "" ? $idProveedor : NULL, 
                "vinc_cliente" => $idCliente != "" ? $idCliente : NULL,
                "vinc_empleado" => $idEmpleado != "" ? $idEmpleado : NULL,
                "concepto" => $concepto_pago,
                "personal_pago" => $vEmp->userr,
                "pago_autorizado" => TRUE,
                "fecha_pago_auth" => time(),
                "personal_autoriza" => $vEmp->userr,
                "empresa" => $vEmp->id,
                "status_pagos" => TRUE,
                "fecha_deletePagos" => ''
              )
            );

            $id_pago_realizado = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("id");

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
                  AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_caja,$folioMovimiento[0]->folio);

                $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
                ->insert(
                  array(
                    "token_movimiento" => $token_movimiento,
                    "folio_movimiento" => $folioMovimiento[0]->folio,
                    "fecha_sistema" => time(),
                    "seccion_movimiento" => 'tesorería',
                    "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "tipo_movimiento" => "R",
                    "subtipo_movimiento" => "C",
                    "responsable" => $vEmp->userr,
                    "caja" => $sql_caja,
                    "monto_aplicado" => $monto_aplicar,
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
                  AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_cuenta_bancaria,$folioMovimiento[0]->folio);

                $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
                ->insert(
                  array(
                    "token_movimiento" => $token_movimiento,
                    "folio_movimiento" => $folioMovimiento[0]->folio,
                    "fecha_sistema" => time(),
                    "seccion_movimiento" => 'tesorería',
                    "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "tipo_movimiento" => "R",
                    "subtipo_movimiento" => "C",
                    "responsable" => $vEmp->userr,
                    "cuenta_bancaria" => $sql_cuenta_bancaria,
                    "monto_aplicado" => $monto_aplicar,
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
                  AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                $token_movimiento = $JwtAuth->encriptarToken($id_pago_realizado,$sql_caja,$folioMovimiento[0]->folio);

                $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
                ->insert(
                  array(
                    "token_movimiento" => $token_movimiento,
                    "folio_movimiento" => $folioMovimiento[0]->folio,
                    "fecha_sistema" => time(),
                    "seccion_movimiento" => 'tesorería',
                    "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "tipo_movimiento" => "R",
                    "subtipo_movimiento" => "C",
                    "responsable" => $vEmp->userr,
                    "cuenta_monedero" => $sql_cuenta_monedero,
                    "monto_aplicado" => $monto_aplicar,
                    "pago" => $id_pago_realizado,
                    "empresa" => $vEmp->id
                  )
                ); 
              }
            }

						if ($idProveedor != "") {
							//echo "saldo_a_favor $saldo_a_favor";exit;
              $id_mov_realizado = "";

							if ($anticipos_aplicados > 0) {
                $ident_deudor = DB::table("fnzs_catalogo_deudores AS catdeu")
                ->join("eegr_catalogo_proveedores AS catprov", "catdeu.proveedor_deudor", "=", "catprov.id")
                ->where("catprov.id",$idProveedor)->value("catdeu.id");

                $id_pago_ant_realizado = DB::table("fnzs_pagos_pago AS pag")
                ->join("fnzs_catalogo_deudores AS catdeu", "pag.vinc_deudor", "=", "catdeu.proveedor_deudor")
                ->join("eegr_catalogo_proveedores AS catprov", "catdeu.proveedor_deudor", "=", "catprov.id")
                ->where("catprov.id",$idProveedor)
                ->where("pag.concepto",$JwtAuth->encriptar("Pago por concepto de anticipo")) 
                ->orderBy("pag.fecha_sistema", "asc")
                ->select("pag.id")
                ->first();

                $folioMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas 