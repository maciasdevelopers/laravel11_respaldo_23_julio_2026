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
      $fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_autorizacion_pay) : "---";
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
          $fecha_contabilizacion_doc_anterior = $oBuy->fecha_contabilizacion;
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
          $fecha_contabilizacion_doc_anterior = $oSell->fecha_contabilizacion;
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
          //$fecha_contabilizacion_doc_anterior = $JwtAuth->mostrarUnixAFechaMexico($rReem->fecha_contabilizacion);
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
          $fecha_contabilizacion_doc_anterior = $oAnt->ant_fecha_contabilizacion;
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
          $fecha_contabilizacion_doc_anterior = $ordAnt->ant_fecha_contabilizacion;
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

            $fecha_contabilizacion_doc_anterior = $vEspNom->nomina_esp_fecha_contabilizacion;
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
            
            $fecha_contabilizacion_doc_anterior = $vNom->nomina_fecha_contabilizacion;
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
          $fecha_contabilizacion_doc_anterior = $oIsn->nomi_imp_fecha_contabilizacion;
					
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
          $fecha_contabilizacion_doc_anterior = $oIMMS->aport_ssocial_fecha_contabilizacion;
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
          $fecha_contabilizacion_doc_anterior = $oDecFed->declaracion_fecha_contabilizacion;
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
          $fecha_contabilizacion_doc_anterior = $oAsim->asim_fecha_contabilizacion;

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
      $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->orden_terminada_fecha) : "---";
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

        //$fecha_contabilizacion_doc_anterior = gmdate('Y-m-d H:i:s', $oBuy->fecha_contabilizacion);
        $fecha_contabiliza_ordp = " date ".date('Y-m-d H:i:s', $rOrdPag->fecha_contabilizacion_ordenPago)." gmdate ".gmdate('Y-m-d H:i:s', $rOrdPag->fecha_contabilizacion_ordenPago);
        $correct_fecha_cont = $JwtAuth->corregirTimestampUnixHistorico($rOrdPag->fecha_contabilizacion_ordenPago);
        $fecha_contabilizacion = " date ".date('Y-m-d H:i:s', $fecha_contabilizacion_doc_anterior)." gmdate ".gmdate('Y-m-d H:i:s', $fecha_contabilizacion_doc_anterior);
        
        $row_ordenPay = array(
          "id" => $id_list,
          "token_ordenPago" => $rOrdPag->token_ordenPago,
          "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
          "fecha_contabilizacion_doc_anterior" => $JwtAuth->mostrarUnixAFechaMexico($fecha_contabilizacion_doc_anterior),//$fecha_contabilizacion_doc_anterior,
          "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_contabilizacion_ordenPago) : '',
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_sistema_ordenp),
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
          "pagos_realizados" => number_format($pagos_realizados, $orden_moneda_autorizado_final_decimales, '.', ''),
          "pagos_realizados_format" => number_format($pagos_realizados, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "importe_restante" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
          "importe_restante_format" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "importe_por_pagar" => "0.00",
          "importe_por_pagar_format" => "$".number_format(0, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "debe_simple" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
          "debe_format" => "$".number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          "pago_anticipado" => "$".number_format($importe_total_anticipo, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
          //$orden_moneda_final_decimales /= 0;
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
          "pago_orden_cancel_fecha_cont" => $rOrdPag->op_cancel ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->pago_orden_cancel_fecha_cont) : '',
          "pago_orden_cancel_comentarios" => $rOrdPag->op_cancel ? $JwtAuth->desencriptar($rOrdPag->pago_orden_cancel_comentarios) : ''
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      //return response()->json([
      //  'php' => date_default_timezone_get(),
      //  'laravel' => config('app.timezone')
      //]);

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = gmdate('Y-m-d H:i:s',  strtotime('monday this week'));
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
      ->select('orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        
        /*$query_pagooo_orden = DB::table("fnzs_pagos_orden")
        ->orderBy("id","DESC")
        ->get();
  
        foreach ($query_pagooo_orden as $lMov) {
          //fecha_contabilizacion_ordenPago 	
          //doc_anterior_fecha_contabilizacion 	
          //fecha_desbloqueo 	
          //fecha_autorizacion_pay 	
          //tentativa_pago 	
          //orden_terminada_fecha 	
          //pago_orden_cancel_fecha_cont
 
          $correct_fecha_contabilizacion_ordenPago = !is_null($lMov->fecha_contabilizacion_ordenPago_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->fecha_contabilizacion_ordenPago_old): null;
          $correct_doc_anterior_fecha_contabilizacion = !is_null($lMov->doc_anterior_fecha_contabilizacion_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->doc_anterior_fecha_contabilizacion_old): null;
          $correct_fecha_desbloqueo = !is_null($lMov->fecha_desbloqueo_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->fecha_desbloqueo_old): null;
          $correct_fecha_autorizacion_pay = !is_null($lMov->fecha_autorizacion_pay_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->fecha_autorizacion_pay_old): null;
          $correct_tentativa_pago = !is_null($lMov->tentativa_pago_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->tentativa_pago_old): null;
          $correct_orden_terminada_fecha = !is_null($lMov->orden_terminada_fecha_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->orden_terminada_fecha_old): null;
          $correct_pago_orden_cancel_fecha_cont = !is_null($lMov->pago_orden_cancel_fecha_cont_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->pago_orden_cancel_fecha_cont_old): null; 	
          DB::table("fnzs_pagos_orden")
          ->where('token_ordenPago',$lMov->token_ordenPago)
          ->limit(1)->update(array( 
            "fecha_contabilizacion_ordenPago" => $correct_fecha_contabilizacion_ordenPago, 
            "doc_anterior_fecha_contabilizacion" => $correct_doc_anterior_fecha_contabilizacion, 
            "fecha_desbloqueo" => $correct_fecha_desbloqueo, 
            "fecha_autorizacion_pay" => $correct_fecha_autorizacion_pay, 
            "tentativa_pago" => $correct_tentativa_pago, 
            "orden_terminada_fecha" => $correct_orden_terminada_fecha, 
            "pago_orden_cancel_fecha_cont" => $correct_pago_orden_cancel_fecha_cont
          ));
        }*/

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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = gmdate('Y-m-d H:i:s',  strtotime('monday this week'));
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
			->select('orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = gmdate('Y-m-d H:i:s',  strtotime('monday this week'));
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
			->select('orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
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
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'token_compras' => 'required|string',
			'token_ordenPago' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_compras = $request->input('token_compras');
      $token_ordenPago = $request->input('token_ordenPago');
      
      $listOrdenes = DB::table("fnzs_pagos_orden AS orden")
      ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
      ->join("main_empresas AS emp", "orden.empresa", "=", "emp.id")
      ->where([
        "orden.token_ordenPago" => $token_ordenPago,
        "orden.status_ordenPago" => TRUE,
        "orden.autorizacion_pay" => TRUE,
        "orden.orden_terminada_bool" => FALSE,
        "buy.token_compras" => $token_compras,
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("orden.fecha_autorizacion_pay", "desc")
      ->select('orden.*','emp.empresa_token','emp.zona_horaria')
      ->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $ordenes_pago_lista = array();

				$id_list = 1;
				foreach ($listOrdenes as $rOrdPag) {
					//da_te_default_timezone_set($rOrdPag->zona_horaria);
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;
					$status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->orden_terminada_fecha) : "---";

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
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
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
							"fecha_contabilizacion_ordenPago" => $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_contabilizacion_ordenPago),
							"fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_sistema_ordenp),
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = gmdate('Y-m-d H:i:s',  strtotime('monday this week'));
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
			->select('orden.pago_orden_cancelada As op_cancel','orden.*','emp.empresa_token', 'emp.zona_horaria')
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
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'ordenes' => 'required|array'
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
      $orden_pago = $request->input('ordenes');
      
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
            $fecha_auto_response = $JwtAuth->mostrarUnixAFechaMexico($f_auth);
          } else {
            $response_status = "error";
            $response_message = "Aprobación de orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " no fue registrada, intente nuevamente o comuníquese a soporte";
            $fecha_auto_response = "---";
          }
          $dataMensaje = array("status" => $response_status, "code" => 200, "message" => $response_message, "response_fauth" => $fecha_auto_response);
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function autorizarOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_pago' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $orden_pago = $request->input('orden_pago');

			$listOrdenes = DB::table("fnzs_pagos_orden AS orden")
			->join("main_empresas AS emp", "orden.empresa", "emp.id")
			->where([
        "orden.token_ordenPago" => $orden_pago, 
        "orden.status_ordenPago" => TRUE
      ])
			->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
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
                  'emp.empresa_token' => $empresa,
                  'users.usuario_token' => $usuario,
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
						$fecha_auto_response = $JwtAuth->mostrarUnixAFechaMexico($f_auth);
					} else {
						$response_status = "error";
						$response_message = "Aprobación de orden de pago con folio ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . " no fue registrada, intente nuevamente o comuníquese a soporte";
						$fecha_auto_response = "---";
					}
					$dataMensaje = array("status" => $response_status, "code" => 200, "message" => $response_message, "response_fauth" => $fecha_auto_response);
				}
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function desautorizarOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_pago' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $orden_pago = $request->input('orden_pago');
      
			$listOrdenes = DB::table("fnzs_pagos_orden AS orden")
			->join("main_empresas AS emp", "orden.empresa", "emp.id")
			->where(["orden.token_ordenPago" => $orden_pago, "orden.status_ordenPago" => TRUE])
			->get();

      if ($listOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
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
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizarOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_pago' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $orden_pago = $request->input('orden_pago');

			$rOrdPag = DB::table('fnzs_pagos_orden as orden')
      ->join('main_empresas as emp', 'orden.empresa', '=', 'emp.id')
      ->where([
        "orden.token_ordenPago" => $orden_pago, 
        "orden.status_ordenPago" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->orderBy('orden.id', 'desc')
      ->select('orden.autorizacion_pay','orden.fecha_autorizacion_pay')
      ->first();

      if (!$rOrdPag) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de pago registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_autorizacion_pay) : "---";
        $autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;

        $dataMensaje = array(
          'autorizacion_pay' => $autorizacion_pay,
          'autorizacion_pay_translate' => $autorizacion_pay ? 'yes_auth' : 'not_auth',
          //'autorizacion_pay_text' => "",
          'fecha_autorizacion_pay' => $fecha_autorizacion_pay,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function generaPagoSimple(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'order_importe' => 'required|numeric',
			'fecha_contabilizacion' => 'required|string',
			'order_caja' => 'nullable|array',
			'order_cuenta_bancaria' => 'nullable|array',
			'order_monedero_electronico' => 'nullable|array',
			'anticipos' => 'nullable|numeric',
			'saldos' => 'nullable|array',
			'prv_token' => 'nullable|string',
			'saldo_a_favor' => 'required|string',
			'order_moneda' => 'required|string',
			'order_tipo_cambio' => 'required|numeric',
			'order_forma_pago' => 'required|string',
			'order_ordenes_pago' => 'required|array',
			'order_observacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en los datos seleccionados'.$validate->errors(),
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $order_importe = $request->input('order_importe');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $order_caja = $request->input('order_caja') ?? [];
      $order_cuenta_bancaria = $request->input('order_cuenta_bancaria') ?? [];
      $order_cuenta_monedero = $request->input('order_monedero_electronico') ?? [];
      $anticipos_aplicados = $request->input('anticipos');
      $saldos_aplicados = $request->input('saldos') ?? [];
      $saldo_a_favor = $request->input('saldo_a_favor');
      $prv_token = $request->input('prv_token');
      //echo "saldo_a_favor $saldo_a_favor"; exit;
      $order_moneda = $request->input('order_moneda');
      $order_tipo_cambio = $request->input('order_tipo_cambio');
      $order_forma_pago = $request->input('order_forma_pago');
      $order_ordenes_pago = $request->input('order_ordenes_pago') ?? [];
      $order_observacion = $request->input('order_observacion');

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
            //da_te_default_timezone_set($vEmp->zona_horaria);
            
  
            $folioPagos = DB::select("SELECT IF (max(folio_pagos) IS NOT NULL,(max(folio_pagos)+1),1) AS folio FROM fnzs_pagos_pago AS payment JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE payment.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$empresa, $usuario]
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
            
            DB::table("fnzs_pagos_pago")
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
  
                $folioMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                  [$empresa, $usuario]
                );
                
                $tokenMov = $JwtAuth->encriptarToken($anticipos_aplicados.$order_observacion.time());
                $folio_pago_generar = "DEUMOV-".$JwtAuth->generarFolio($folioMovimientos[0]->folio);
  
                $insertPagoMon = DB::table("fnzs_catalogo_deudores_movimientos")
                ->insert(
                  array(
                    "token_deu_mov" => $tokenMov,
                    "folio_deu_mov" => $folioMovimientos[0]->folio,
                    "deu_fecha_registro" => time(),
                    "deu_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    //"orden_pago_vinculada" => DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_de_pago_vinculada)->value("id"),
                    "condicion_deu_mov" => "R",
                    "deu_monto_mov" => $anticipos_aplicados,
                    "deu_observaciones_mov" => $JwtAuth->encriptar($order_observacion),
                    "deu_tipo_cambio" => $order_tipo_cambio,
                    "deu_mov_moneda" => $order_moneda,
                    "vinc_deudor" => $ident_deudor,
                    "deu_personal_mov" => $vEmp->userr,
                    "deu_mov_autorizado" => TRUE,
                    "deu_fecha_mov_auth" => time(),
                    "deu_personal_autoriza" => $vEmp->userr,
                    "deu_empresa" => $vEmp->id,
                    "deu_status_mov" => TRUE,
                  )
                );
  
                $id_mov_realizado = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenMov)->value("id");
  
                $insertPagoVinc = DB::table("fnzs_catalogo_deudores_movimientos_pagos_vinculados")
                ->insert(array(
                  "mov_realizado" => $id_mov_realizado,
                  "pago_vinculado" => $id_pago_realizado,
                  "mov_pago_monto" => $anticipos_aplicados
                ));
  
                //echo "saldo_a_favor $saldo_a_favor";exit;
                /*for ($i=0; $i < count($anticipos_aplicados); $i++) {
                  $uuid_anticipo = $anticipos_aplicados[$i]["uuid_anticipo"];
                  $monto_real = $anticipos_aplicados[$i]["monto_real"];
                  $anticipo_aplicar = $anticipos_aplicados[$i]["monto_aplicar"];
                  $insertAnticipo = DB::table("eegr_catalogo_proveedores_anticipo_aplicacion")
                  ->insert(
                    array(
                      "uuid_anticipo_aplicacion" => Str::uuid()->toString(),
                      "proveedor" => $idProveedor,
                      "pago_realizado" => $id_pago_realizado,
                      "anticipo_fecha_aplicacion" => time(),
                      "monto_total_anticipo" => floatval(str_replace(',', '', $anticipo_aplicar)),
                      "anticipo_registrado" => $uuid_anticipo
                    )
                  );
                  $updateProvValid = DB::table("eegr_catalogo_proveedores_anticipo")->where("uuid_anticipo",$uuid_anticipo)->limit(1)->update(array("saldo_disponible" => floatval($monto_real - $anticipo_aplicar),"disponible" => $monto_real - $anticipo_aplicar == 0 ? FALSE : TRUE));
                }*/
              }
  
              if (count($saldos_aplicados) > 0 && $saldo_a_favor == "0.00") {
                for ($i=0; $i < count($saldos_aplicados); $i++) { 
                  $saldo_id = $saldos_aplicados[$i]["uuid_saldo"];
                  $saldo_real = $saldos_aplicados[$i]["monto_real"];
                  $saldo_aplicar = $saldos_aplicados[$i]["monto_aplicar"];
                  $insertSaldoFavor = DB::table("fnzs_pagos_saldo_a_favor_aplicaciones")
                  ->insert(
                    array(
                      "uuid_aplicacion" => Str::uuid()->toString(),
                      "proveedor" => $idProveedor,
                      "pago_realizado" => $id_pago_realizado,
                      "saldo_registrado" => $saldo_id,
                      "saldo_monto" => floatval(str_replace(',', '', $saldo_aplicar)),
                      "fecha_de_aplicacion" => time()
                    )
                  );
                  $updateProvValid = DB::table("fnzs_pagos_saldo_a_favor")->where("uuid_saldo",$saldo_id)->limit(1)->update(array("saldo_disponible" => floatval($saldo_real - $saldo_aplicar),"disponible" => $saldo_real - $saldo_aplicar == 0 ? FALSE : TRUE));
                }
              } else if (count($saldos_aplicados) == 0 && $saldo_a_favor != "0.00") {
                DB::table("fnzs_pagos_saldo_a_favor")
                ->insert(
                  array(
                    "uuid_saldo" => Str::uuid()->toString(),
                    "proveedor" => $idProveedor,
                    "pago_realizado" => $id_pago_realizado,
                    "saldo_monto" => floatval(str_replace(',', '', $saldo_a_favor)),
                    "saldo_disponible" => floatval(str_replace(',', '', $saldo_a_favor)),
                    "disponible" => TRUE,
                    "fecha_de_registro" => time(),
                    "status_saldo" => TRUE
                  )
                );
              }
            }
            
            if (count($order_ordenes_pago) > 0) {
              for ($i=0; $i < count($order_ordenes_pago); $i++) {
                $tipo_factura_relacionada = $order_ordenes_pago[$i]["factura_relacionada_typo"];
                $folio_orden_pago = $order_ordenes_pago[$i]["folio_ordenPago"];
                $orden_pago = $order_ordenes_pago[$i]["token_ordenPago"];
                $factura_relacionada = $order_ordenes_pago[$i]["factura_relacionada_token"];
                $factura_relacionada_string = $order_ordenes_pago[$i]["factura_relacionada_string"];
                $importe_por_pagar = $order_ordenes_pago[$i]["importe_por_pagar"];
                //echo "importe_por_pagar $i ";
                $importe_restante = $order_ordenes_pago[$i]["debe_simple"];
  
                $id_ord_pago = DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_pago)->value("id");
                
                $insertPagoVinc = DB::table("fnzs_pagos_pago_ordenes_vinculadas")
                ->insert(array("pago_realizado" => $id_pago_realizado,"orden_pago_vinculada" => $id_ord_pago,"orden_pago_monto" => $importe_por_pagar));
                
                if ($anticipos_aplicados > 0) {
                  $insertMovVincPagosOrden = DB::table("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo")
                  ->insert(array("mov_realizado" => $id_mov_realizado,"orden_pago" => $id_ord_pago));
                }
                
                if ($importe_restante == "0.00") {
                  $terminaReembolso = DB::table("fnzs_pagos_orden")->where("id",$id_ord_pago)->limit(1)->update(array(
                    "orden_terminada_bool" => TRUE,
                    "orden_terminada_fecha" => time(),
                    "fecha_contabilizacion_ordenPago" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  ));
                }
  
                if ($tipo_factura_relacionada == "compras") {
                  $query_ord_buy = DB::table("fnzs_pagos_orden AS order")
                  ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
                  ->join("eegr_compras_orden_recepcion AS ordRec", "buy.id", "=", "ordRec.orden_compra")
                  ->where("order.token_ordenPago",$orden_pago)
                  ->where("buy.token_compras",$factura_relacionada)
                  ->where("ordRec.orden_bloqueada",TRUE)
                  ->get();
                  foreach ($query_ord_buy as $vOrdB) {
                    $orderUnLock = DB::table("eegr_compras_orden_recepcion")
                    ->where("uuid_orden_recepcion",$vOrdB->uuid_orden_recepcion)
                    ->limit(1)->update(
                      array("orden_bloqueada" => FALSE,"fecha_desbloqueo" => time())
                    );
                  }
                }
  
                if ($tipo_factura_relacionada == "reembolsos") {
                  $mensaje_user = "Recibiste un pago del reembolso con folio $factura_relacionada_string por un total de: $$importe_por_pagar $order_moneda";
                  //$JwtAuth->insertGeneralNotif($asunt,$titulo_alert,$typo ,$area,$sub,$empresa         ,$emisor             ,$receptor)
  
                  $query_ord_reem = DB::table("fnzs_pagos_orden AS order")
                  ->join("terc_reembolso_main AS reem_main", "order.reembolso_main", "=", "reem_main.id")
                  ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
                  ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                  ->where(["order.token_ordenPago" => $orden_pago, "reem_main.token_reem" => $factura_relacionada])->get();
  
                  foreach ($query_ord_reem as $vOrd) {
                    $JwtAuth->insertGeneralNotif("Pago de reembolso", $mensaje_user, "terem", NULL, NULL, $vEmp->id, $vEmp->userr, $vOrd->user_emisor);
                  }
                }
              }
            }
            
            $fecha_sistema_ordenp = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("fecha_sistema");
            $filepath = $vEmp->root_tkn . "/0003-fnzs/ordenes_pagos/$fecha_sistema_ordenp-$folio_pago_generar/pago_evidencias/";
            if (!file_exists(storage_path("/root/$filepath"))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }
            
            if (!empty($_FILES['evidencias_pagos'])) {
              $evidencias = $_FILES["evidencias_pagos"];
              $string_name_evid = json_encode($_FILES["evidencias_pagos"]["name"]);
              if (count(json_decode($string_name_evid)) != 0) {
                $evidencia_nombre = json_decode($string_name_evid);
                for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                  $temporal = $evidencias["tmp_name"][$doc];
                  $doc_name = $evidencias["name"][$doc];
                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PAY-EVID%'");
                  $token_documento = $JwtAuth->encriptarToken($id_pago_realizado,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                  $insertDocSoli = DB::table("sos_documentos")->insert(
                    array(
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "pagos",
                      "folio_modulo" => "PAY-EVID" . $select_folio_doc[0]->folio,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($doc_name),
                      "pago" => $id_pago_realizado,
                      "status_documento" => TRUE,
                    )
                  );
                }
              }
            }

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => '¡Pago realizado existosamente, revise su información y comuníquese con al área correspondiente al pago realizado!'
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
        if (!$valide_order_importe) $mensaje_error = "Error en importe de pago, verifique su información";
        if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
        if (!$valide_saldo_a_favor) $mensaje_error = "Error en saldo a favor de pago, verifique su información";
        if (!$valide_order_moneda) $mensaje_error = "Error en moneda seleccionada, verifique su información";
        if (!$valide_order_tipo_cambio) $mensaje_error = "Error en tipo de cambio, verifique su información";
        if (!$valide_order_forma_pago) $mensaje_error = "Error en forma de pago seleccionada, verifique su información";
        if (!$valide_order_ordenes_pago) $mensaje_error = "Error en facturas seleccionadas, verifique su información";
        if (!$valide_order_observacion) $mensaje_error = "Error en observaciones finales, verifique su información";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function eachCatalogoPagos($queryPagos,$JwtAuth){
		$lista_pagos = array();
    $idPay = $queryPagos->pluck('id_pago')->filter()->unique()->toArray();

    $docAnteriorMap = DB::table("fnzs_pagos_orden AS order")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
    ->whereIn("vinc.pago_realizado", $idPay)
    ->select("vinc.pago_realizado AS id_pago","order.folio_ordenPago","order.fecha_contabilizacion_ordenPago")
    ->get()->keyBy('id_pago');
    
    foreach ($queryPagos as $pay) {
      $queryDocAnterior = $docAnteriorMap->get($pay->id_pago);
      $doc_anterior_folio = $queryDocAnterior ? "ORDP-".$JwtAuth->generarFolio($queryDocAnterior->folio_ordenPago) : '';
      $doc_anterior_fecha_contabilizacion = $queryDocAnterior ? $JwtAuth->mostrarUnixAFechaMexico($queryDocAnterior->fecha_contabilizacion_ordenPago) : '';

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

        if ($vOrdp->token_compras !== null) {
          $queryFormaPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
          ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
          ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select("cfdi.cfdi_comprobante_forma_de_pago","cfdi.cfdi_comprobante_metodo_de_pago")->first();
          $pago_rr_forma_metodo_pago_cfdi = $queryFormaPago ? $queryFormaPago->cfdi_comprobante_forma_de_pago." - ".$JwtAuth->getFormasPagoAPI($queryFormaPago->cfdi_comprobante_forma_de_pago)." / ".$queryFormaPago->cfdi_comprobante_metodo_de_pago : '';

          $factura_relacionada_typo = "compras";
          $factura_relacionada_token = $vOrdp->token_compras;
          $factura_relacionada_string = "COMP-" . $JwtAuth->generarFolio($vOrdp->folio_compra);
        } elseif ($vOrdp->token_ventas !== null) {
          $factura_relacionada_typo = "ventas";
          $factura_relacionada_token = $vOrdp->token_ventas;
          $factura_relacionada_string = "VENT-" . $JwtAuth->generarFolio($vOrdp->numero_venta);
        } elseif ($vOrdp->token_reem !== null) {
          $factura_relacionada_typo = "reembolsos";
          $factura_relacionada_token = $vOrdp->token_reem;
          $factura_relacionada_string = 'REEM-'.$JwtAuth->generarFolio($vOrdp->folio_reem).($vOrdp->post_folio_reem == NULL ? '-'.$vOrdp->post_folio_reem : '');
        } elseif ($vOrdp->ord_anticipo != NULL) {
          $factura_relacionada_typo = "anticipos";
          $factura_relacionada_token = $vOrdp->uuid_anticipo;
          $factura_relacionada_string = 'ANT-'.$JwtAuth->generarFolio($vOrdp->folio_anticipo);

          $query_deu_anticipo = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
          ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where("ant.uuid_anticipo",$vOrdp->ord_anticipo)->get();

          foreach ($query_deu_anticipo as $oDeu) {
            $prov_token = $oDeu->token_cat_proveedores;
            $prov_folio = 'PRV-'.$JwtAuth->generarFolio($oDeu->folio).(!is_null($oDeu->post_folio) ? '-'.$oDeu->post_folio : '');
            $prov_name = $JwtAuth->desencriptar($oDeu->nombre_extendido);
            $prov_comercial_name = !is_null($oDeu->nombre_com) ? $JwtAuth->desencriptar($oDeu->nombre_com) : '';
          }
        }
        
        $row_ord = array(
          "token_ordenPago" => $vOrdp->token_ordenPago,
          "orden_pago_monto" => "$".number_format($orden_pago_monto * $pay->tipo_cambio,$JwtAuth->getMonedaAPI($pay->p_moneda),'.',','),
          "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago),
          "fecha_contabilizacion_ordenPago" => $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_contabilizacion_ordenPago),
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_sistema_ordenp),
          "autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,
          "pago_cancelado" => $pay->pago_cancelado ? true : false,
          //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_folio_cancelacion
          //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_fecha_cancelacion
          //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_fecha_contabilizacion_cancelacion

          "fecha_autorizacion_pay" => $vOrdp->autorizacion_pay ? $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_autorizacion_pay) : "---",
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
        //$pers_responsmov_name = $queryPersResponsable ? $JwtAuth->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

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
          "fecha_sistema" => $JwtAuth->mostrarUnixAFechaMexico($vMov->fecha_sistema),
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
      
      $fecha_contabilizacion = " date ".date('Y-m-d H:i:s', $pay->fecha_contabilizacion)." gmdate ".gmdate('Y-m-d H:i:s', $pay->fecha_contabilizacion);
      $correct_fecha_cont = $JwtAuth->corregirTimestampUnixHistorico($pay->fecha_contabilizacion);

      $row = array(
        "token_pagos" => $pay->token_pagos,
        "folio_pagos" => "PAGO-".$JwtAuth->generarFolio($pay->folio_pagos),
        //"folio_operacion" => $pay->folio_operacion,
        "fecha_pago" => $JwtAuth->mostrarUnixAFechaMexico($pay->fecha_pago),
        "fecha_contabilizacion" => !empty($pay->fecha_contabilizacion) ? $JwtAuth->mostrarUnixAFechaMexico($pay->fecha_contabilizacion) : "",
        //cancelado
        "pago_cancelado" => $pay->pago_cancelado ? true : false,	
        "pago_cancelado_translate" => $pay->pago_cancelado ? 'canceled_reg' : 'approved_reg',
        "pago_folio_cancelacion" => $pay->pago_cancelado ? "PCAN-".$JwtAuth->generarFolio($pay->pago_folio_cancelacion) : "",
        "pago_fecha_cancelacion" => $pay->pago_cancelado ? $JwtAuth->mostrarUnixAFechaMexico($pay->pago_fecha_cancelacion) : "",
        "pago_fecha_contabilizacion_cancelacion" => $pay->pago_cancelado ? $JwtAuth->mostrarUnixAFechaMexico($pay->pago_fecha_contabilizacion_cancelacion) : "",
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
        //"destino" => $destino,

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
        "fecha_pago_auth" => $JwtAuth->mostrarUnixAFechaMexico($pay->fecha_pago_auth),
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

		return $lista_pagos;
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = gmdate('Y-m-d H:i:s',  strtotime('monday this week'));
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
			->where([
				"payment.status_pagos" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("payment.fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->select('payment.id As id_pago','payment.*','emp.*')
			->orderBy("payment.folio_pagos", "DESC")->get();

      if ($queryPagos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();

        /*$query_movimeintos_cp = DB::table("fnzs_pagos_pago")
        ->orderBy("id","DESC")
        ->get();
  
        foreach ($query_movimeintos_cp as $lMov) {
          //fecha_pago 	
          //fecha_contabilizacion
          //pago_fecha_cancelacion 	
          //pago_fecha_contabilizacion_cancelacion
          //fecha_pago_auth

          $correct_fecha_pago = !is_null($lMov->fecha_pago_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->fecha_pago_old): null;
          $correct_fecha_contabilizacion = !is_null($lMov->fecha_contabilizacion_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->fecha_contabilizacion_old): null;
          $correct_pago_fecha_cancelacion = !is_null($lMov->pago_fecha_cancelacion_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->pago_fecha_cancelacion_old): null;
          $correct_pago_fecha_contabilizacion_cancelacion = !is_null($lMov->pago_fecha_contabilizacion_cancelacion_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->pago_fecha_contabilizacion_cancelacion_old): null;
          $correct_fecha_pago_auth = !is_null($lMov->fecha_pago_auth_old) ? $JwtAuth->corregirTimestampUnixHistorico($lMov->fecha_pago_auth_old): null;
          DB::table("fnzs_pagos_pago")
          ->where('token_pagos',$lMov->token_pagos)
          ->limit(1)->update(array( 
            "fecha_pago" => $correct_fecha_pago, 
            "fecha_contabilizacion" => $correct_fecha_contabilizacion,
            "pago_fecha_cancelacion" => $correct_pago_fecha_cancelacion, 
            "pago_fecha_contabilizacion_cancelacion" => $correct_pago_fecha_contabilizacion_cancelacion, 
            "fecha_pago_auth" => $correct_fecha_pago_auth
          ));
        }*/

				$lista_pagos = $this->eachCatalogoPagos($queryPagos,$JwtAuth);

				$dataMensaje = array(
					"status" => "success",
					"code" => 200,
					'lista_pagos' => collect($lista_pagos)->sortBy('id')->values(),
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function catalogoPagosDesglose(Request $request){
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
				'message' => 'Error en los datos seleccionados',
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
      ->get();
			//echo "count(query_pagos_realizados) ".count($query_pagos_realizados);

      if ($queryPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $lista_pagos_realizados = array();
        foreach ($queryPago as $vPago) {
          $pago_folio = "PAGO-".$JwtAuth->generarFolio($vPago->folio_pagos);
          $forma_pago_registrada = $vPago->forma_pago_pago;
          //proveedor
					$queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
					->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          ->where("payment.token_pagos", $vPago->token_pagos)
          ->get();
          //echo count($queryOrvVincReembolsosPago);
          if (count($queryOrvVincReembolsosPago) > 0) {
            $forma_pago_registrada = $vPago->forma_pago_pago;
					  $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
            ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
            ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
            ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
					  ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "people.id")
					  ->where(["payment.token_pagos" => $vPago->token_pagos])
					  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					  ->first();
					  $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
            $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					  $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
          } else {
            $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
					  ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
					  ->join("sos_personas AS people", "catprov.proveedor", "people.id")
					  ->where(["payment.token_pagos" => $vPago->token_pagos])
					  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					  ->first();
					  $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
            $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					  $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
          }

          //cliente
					$queryCliente = DB::table("fnzs_pagos_pago AS payment")
					->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
					->join("sos_personas AS people", "catclient.cliente", "people.id")
					->where(["payment.token_pagos" => $vPago->token_pagos])
					->select('catclient.token_cat_clientes','catclient.folio','catclient.post_folio','people.nombre_extendido')
					->first();
					$cliente_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
          $cliente_folio = $queryCliente ? ('CLI-'.$JwtAuth->generarFolio($queryCliente->folio).(!is_null($queryCliente->post_folio) ? '-'.$queryCliente->post_folio : '')) : "";
					$cliente_name = $queryCliente ? $JwtAuth->desencriptar($queryCliente->nombre_extendido) : "";
          //empleado
					$queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
					->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where(["payment.token_pagos" => $vPago->token_pagos])
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$empleado_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
          $empleado_folio = $queryEmpleado ? "TRB-".$JwtAuth->generarFolio($queryEmpleado->folio_pers) : "";
					$empleado_name = $queryEmpleado ? $JwtAuth->desencriptarNombres($queryEmpleado->paterno,$queryEmpleado->materno,$queryEmpleado->nombre) : "";
          //acreedor
					$queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
					->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
					//->join("sos_personas AS people", "acr.acreedor", "people.id")
					->where(["payment.token_pagos" => $vPago->token_pagos])
					->select('acr.token_cat_acreedores','acr.acr_folio','acr.acr_post_folio','acr.acr_titular')
					->first();
  				$acreedor_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
          $acreedor_folio = $queryAcreedor ? ('ACREE-'.$JwtAuth->generarFolio($queryAcreedor->acr_folio).(!is_null($queryAcreedor->acr_post_folio) ? '-'.$queryAcreedor->acr_post_folio : '')) : "";
  				$acreedor_name = $queryAcreedor ? $JwtAuth->desencriptar($queryAcreedor->acr_titular) : "";
          //deudor
					$queryDeudor = DB::table("fnzs_pagos_pago AS payment")
					->join("fnzs_catalogo_deudores AS deu", "payment.vinc_deudor", "deu.id")
					->join("sos_personas AS people", "deu.deudor", "people.id")
					->where(["payment.token_pagos" => $vPago->token_pagos])
					->select('deu.token_cat_deudores','deu.folio','deu.post_folio','people.nombre_extendido','people.paterno','people.materno','people.nombre')
					->first();
					$deudor_token = $queryDeudor ? $queryDeudor->token_cat_deudores : "";
          $deudor_folio = $queryDeudor ? ('DEU-'.$JwtAuth->generarFolio($queryDeudor->folio).(!is_null($queryDeudor->post_folio) ? '-'.$queryDeudor->post_folio : '')) : "";
					$deudor_name = $queryDeudor ? ($queryDeudor->nombre_extendido ? $JwtAuth->desencriptar($queryDeudor->nombre_extendido) : $JwtAuth->desencriptarNombres($queryDeudor->paterno,$queryDeudor->materno,$queryDeudor->nombre)) : "";

          //personal_pago
					$queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
					->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where('payment.token_pagos',$vPago->token_pagos)
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
          $p_paga_folio = $queryPersPaga ? "TRB-".$JwtAuth->generarFolio($queryPersPaga->folio_pers) : "";
					$p_paga_name = $queryPersPaga ? $JwtAuth->desencriptarNombres($queryPersPaga->paterno,$queryPersPaga->materno,$queryPersPaga->nombre) : "";

					$queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
					->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where('payment.token_pagos',$vPago->token_pagos)
					->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno','people.nombre')
					->first();
					$p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
          $p_autoriza_folio = $queryPersAuth ? "TRB-".$JwtAuth->generarFolio($queryPersAuth->folio_pers) : "";
					$p_autoriza_name = $queryPersAuth ? $JwtAuth->desencriptarNombres($queryPersAuth->paterno,$queryPersAuth->materno,$queryPersAuth->nombre) : "";

          $ordenes_relacionadas_lista = array();
					$queryOrdenesPago = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->leftJoin("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->leftJoin("ingr_ventas AS sell", "order.factura_venta", "=", "sell.id")
          ->leftJoin("terc_reembolso_main AS reem", "order.reembolso_main", "=", "reem.id")
          ->where("payment.token_pagos", $vPago->token_pagos)
          ->select("order.*","vinc.*","buy.token_compras","buy.folio_compra","sell.token_ventas","sell.folio_venta","reem.token_reem","reem.folio_reem","reem.post_folio_reem")->get();

          foreach ($queryOrdenesPago as $vOrdp) {
            $orden_pago_monto = $vOrdp->orden_pago_monto;
					  $factura_relacionada_typo = "---";
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

            if ($vOrdp->token_compras !== null) {
					    $queryFormaPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
              ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
              ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
              ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
              ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
              ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
              ->where("payment.token_pagos", $vPago->token_pagos)
              ->select("cfdi.cfdi_comprobante_forma_de_pago")->first();
              $forma_pago_registrada = $queryFormaPago ? $queryFormaPago->cfdi_comprobante_forma_de_pago : '';

              $factura_relacionada_typo = "compras";
							$factura_relacionada_token = $vOrdp->token_compras;
							$factura_relacionada_string = "COMP-" . $JwtAuth->generarFolio($vOrdp->folio_compra);

							$orden_moneda_inicial_name = DB::table("eegr_compras")->where("token_compras",$vOrdp->token_compras)->value("moneda");
							$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($orden_moneda_inicial_name);
							$orden_moneda_autorizado_inicial_name = DB::table("eegr_compras")->where("token_compras",$vOrdp->token_compras)->value("moneda");
							$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($orden_moneda_autorizado_inicial_name);
							$orden_moneda_autorizado_final_name = DB::table("eegr_compras")->where("token_compras",$vOrdp->token_compras)->value("moneda");
							$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($orden_moneda_autorizado_final_name);

							$detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
							->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
							->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
							->where([
								'comp.token_compras' => $vOrdp->token_compras,
								'emp.empresa_token' => $empresa,
								'users.usuario_token' => $usuario,
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

            } elseif ($vOrdp->token_ventas !== null) {
              $factura_relacionada_typo = "ventas";
							$factura_relacionada_token = $vOrdp->token_ventas;
							$factura_relacionada_string = "VENT-" . $JwtAuth->generarFolio($vOrdp->numero_venta);
            } elseif ($vOrdp->token_reem !== null) {
              $factura_relacionada_typo = "reembolsos";
							$factura_relacionada_token = $vOrdp->token_reem;
              $factura_relacionada_string = 'REEM-'.$JwtAuth->generarFolio($vOrdp->folio_reem).($vOrdp->post_folio_reem == NULL ? '-'.$vOrdp->post_folio_reem : '');

							$soli_reem = DB::table("terc_reembolso_main AS reem_main")
								->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
								//->join("fnzs_pagos_orden AS pay_orden","reem_soli.id","=","pay_orden.reembolso_solicitud")
								->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
								->where(["reem_main.token_reem" => $vOrdp->token_reem])
								->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

							foreach ($soli_reem as $vSoliR) {
                $orden_moneda_inicial_name = $vSoliR->moneda_entrante;
                $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);
								$importe_total_inicial = $importe_total_inicial + $vSoliR->importe_entrante;
							}

							$soli_reem_auth = DB::table("terc_reembolso_main AS reem_main")
								->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
								//->join("fnzs_pagos_orden AS pay_orden","reem_soli.id","=","pay_orden.reembolso_solicitud")
								->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
								->where(["reem_soli.autorizacion_egr" => "A", "reem_main.token_reem" => $vOrdp->token_reem])
								->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

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

					  $importe_total_inicial = 0;
					  $orden_moneda_inicial_name = "---";
					  $orden_moneda_inicial_decimales = 0;
            
  					$importe_autorizado_inicial = 0;
  					$orden_moneda_autorizado_inicial_tkn = "---";
  					$orden_moneda_autorizado_inicial_name = "---";
  					$orden_moneda_autorizado_inicial_decimales = 0;

            //pagos_realizados
            $pagos_realizados = 0;
            $lista_pagos_realizados = [];
            $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
  
            //foreach ($queryPagosDone as $vPayDone) {
            //  $pagos_realizados += $vPayDone->orden_pago_monto;
            //}

            $pago_restante = count($queryPagosDone) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

            $row_ord = array(
              "token_ordenPago" => $vOrdp->token_ordenPago,
              "orden_pago_monto" => "$".number_format($orden_pago_monto * $vPago->tipo_cambio,$JwtAuth->getMonedaAPI($vPago->p_moneda),'.',','),
              "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago),
							"fecha_contabilizacion_ordenPago" => $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_contabilizacion_ordenPago),
              "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_sistema_ordenp),
              "autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,
              "fecha_autorizacion_pay" => $vOrdp->autorizacion_pay ? $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_autorizacion_pay) : "---",
							"factura_relacionada_typo" => $factura_relacionada_typo,
							"factura_relacionada_token" => $factura_relacionada_token,
							"factura_relacionada_string" => $factura_relacionada_string,

							//"orden_emisor_emp" => $orden_emisor_emp,
							//"orden_emisor_personal" => $orden_emisor_personal,
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
            );
            $ordenes_relacionadas_lista[] = $row_ord;
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
              "fecha_sistema" => $JwtAuth->mostrarUnixAFechaMexico($vMov->fecha_sistema),
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

          $row_pay = array(
            "token_pagos" => $vPago->token_pagos,
            "folio_pagos" => $pago_folio,
            
            "folio_operacion" => $vPago->folio_operacion,
            "fecha_sistema" => $JwtAuth->mostrarUnixAFechaMexico($vPago->fecha_sistema),
            "fecha_pago" => $JwtAuth->mostrarUnixAFechaMexico($vPago->fecha_pago),
            "fecha_contabilizacion" => $vPago->fecha_contabilizacion ? $JwtAuth->mostrarUnixAFechaMexico($vPago->fecha_contabilizacion) : '',
            "monto_pago" => "$".number_format($vPago->monto_pago * $vPago->tipo_cambio,$JwtAuth->getMonedaAPI($vPago->p_moneda), '.', ','),
            "observacionesPago" => $vPago->observacionesPago ? $JwtAuth->desencriptar($vPago->observacionesPago) : '',
            "tipo_cambio" => "$".number_format($vPago->tipo_cambio,$JwtAuth->getMonedaAPI($vPago->p_moneda), '.', ','),
            "p_moneda" => $vPago->p_moneda,
            "forma_pago_vinculada" => $forma_pago_vinculada,
            "forma_pago_cfdi" => $forma_pago_registrada ? $forma_pago_registrada." - ".$JwtAuth->getFormasPagoAPI($forma_pago_registrada) : '',
            "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
            //proveedor
						"proveedor_token" => $queryProveedor ? $proveedor_token : '',
						"proveedor_name" => $queryProveedor ? "$proveedor_folio - $proveedor_name" : '',
            //cliente
						"cliente_token" => $queryCliente ? $cliente_token : '',
						"cliente_name" => $queryCliente ? "$cliente_folio - $cliente_name" : '',
            //empleado
						"empleado_token" => $queryEmpleado ? $empleado_token : '',
						"empleado_name" => $queryEmpleado ? "$empleado_folio - $empleado_name" : '',
            //acreedor
						"acreedor_token" => $queryAcreedor ? $acreedor_token : '',
						"acreedor_name" => $queryAcreedor ? "$acreedor_folio - $acreedor_name" : '',
            //deudor
						"deudor_token" => $queryDeudor ? $deudor_token : '',
						"deudor_name" => $queryDeudor ? "$deudor_folio - $deudor_name" : '',
            "compra" => $vPago->compra,
            "venta" => $vPago->venta,
            "reembolso_main" => $vPago->reembolso_main,
            "reembolso_solicitud" => $vPago->reembolso_solicitud,
            "concepto" => $vPago->concepto ? $JwtAuth->desencriptar($vPago->concepto) : '',
            "almacen" => $vPago->almacen,
            //personal_pago
            "personal_pago_token" => $p_paga_token,
            "personal_pago_folio" => $p_paga_folio,
            "personal_pago_name" => $p_paga_name,

            "pago_autorizado" => $vPago->pago_autorizado ? true : false,
            "fecha_pago_auth" => $vPago->fecha_pago_auth ? $JwtAuth->mostrarUnixAFechaMexico($vPago->fecha_pago_auth) : '',
            //personal_autoriza
            "personal_autoriza_token" => $p_autoriza_token,
            "personal_autoriza_folio" => $p_autoriza_folio,
            "personal_autoriza_name" => $p_autoriza_name,
            //ordenes_relacionadas
            "ordenes_relacionadas_lista" => $ordenes_relacionadas_lista,
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

	//reembolsos
	public function reembolso_op_lista(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    if (
      $usuario == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4" ||
      $usuario == "WXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjYTlNMEtyUW01ZkQ2ckEyWWJTaThxNTZYQkFVTGJVakFVWDFPdVk9OjoxMjM0NTY3ODEyMzQ1Njc4"
    ) {
      $list_reembolso = DB::select("SELECT reem_main.*,emp.zona_horaria FROM terc_reembolso_main AS reem_main  
      JOIN main_empresas AS emp WHERE reem_main.id IN (SELECT reembolso_main FROM fnzs_pagos_orden)
      AND reem_main.status_reem = TRUE AND reem_main.receptor = emp.id
      AND emp.empresa_token = ? ORDER BY reem_main.folio_reem DESC", [$empresa]);
    } else {
      $list_reembolso = DB::table("terc_reembolso_main AS reem_main")
      ->join("fnzs_pagos_orden AS op", "reem_main.id", "=", "op.reembolso_main")
      ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
      ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
      ->where([
        "reem_main.status_reem" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])->orderBy('reem_main.folio_reem', 'DESC')->get();
    }

    if ($list_reembolso->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron reembolsos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayReem = array();
      foreach ($list_reembolso as $vremb) {
        //da_te_default_timezone_set($vremb->zona_horaria);
        $token_reem = $vremb->token_reem;
        $fecha_solicitud = $vremb->fecha_sistema;
        $date_solicitud = $JwtAuth->mostrarUnixAFechaMexico($vremb->fecha_sistema);

        //$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).'-'.$vremb->post_folio_reem;
        $folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
        if ($vremb->post_folio_reem != NULL) $folio_reem = $folio_reem . '-' . $vremb->post_folio_reem;

        $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
          ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->where(["reem_main.token_reem" => $token_reem])->get();

        foreach ($selectNameEmpEmi as $vEmisor) {
          $name_emisor = $vEmisor->abrev_nombre;
          $rfc_gen_emi = $vEmisor->rfc_generico;
          if ($vEmisor->rfc != NULL) {
            $rfc_emp_emi = $JwtAuth->desencriptar($vEmisor->rfc);
          } else {
            $rfc_emp_emi = "---";
          }
          if ($vEmisor->tax_id != NULL) {
            $taxid_emp_emi = $JwtAuth->desencriptar($vEmisor->tax_id);
          } else {
            $taxid_emp_emi = "---";
          }
        }

        $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
          ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where(["reem_main.token_reem" => $token_reem])->get();

        foreach ($selectPersEmpEmi as $vPemi) {
          $nombreEmiPers = $JwtAuth->desencriptar($vPemi->paterno) . " " .
            $JwtAuth->desencriptar($vPemi->materno) . " " .
            $JwtAuth->desencriptar($vPemi->nombre);
        }

        $importe_final = 0;

        $ordenesTotal = 0;
        $ordenesVencidas = 0;
        $ordenesPorVencer = 0;
        $ordenesProximas = 0;
        $ordenesPagadas = 0;

        $total_tipo_cambio = 0;
        $moneda_entrante_tkn = "";
        $moneda_entrante_string = "";
        $moneda_entrante_decimales = 0;

        $saldo_total = 0;
        $saldo_total_conversion = 0;
        $saldo_pagado = 0;
        $saldo_pagado_conversion = 0;
        $saldo_por_pagar = 0;
        $saldo_por_pagar_conversion = 0;

        $moneda_saliente_tkn = "";
        $moneda_saliente_string = "";
        $moneda_saliente_decimales = 0;
        $total_reem_saliente = 0;

        $soli_reem = DB::table("terc_reembolso_main AS reem_main")
          ->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
          ->join("fnzs_pagos_orden AS op", "reem_soli.id", "=", "op.reembolso_solicitud")
          ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
          ->where(["reem_main.token_reem" => $token_reem])
          ->orderBy('reem_soli.folio_solicitud', 'DESC')->get();
        //echo "soli_reem ".count($soli_reem);

        foreach ($soli_reem as $vSoliR) {
          if (($vSoliR->autorizacion_vh == "N" || $vSoliR->autorizacion_vh == "A") && $vSoliR->autorizacion_egr == "A") {
            $ordenesTotal++;
            $folio_solicitud = $vSoliR->folio_solicitud;
            if ($folio_solicitud + (73440) >= time()) {
              $ordenesProximas++;
            } else if (time() > ($folio_solicitud + (73440)) && time() < ($folio_solicitud + (86400))) {
              $ordenesPorVencer++;
            } else if (time() >= ($folio_solicitud + (86400))) {
              $ordenesVencidas++;
            }
            
            $moneda_entrante_tkn = $vSoliR->moneda_entrante;
            $moneda_entrante_string = $vSoliR->moneda_entrante;
            $moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

            $saldo_total = $saldo_total + $vSoliR->importe_entrante;
            $saldo_total_conversion = $saldo_total * $vSoliR->tipo_cambio;
            //$saldo_pagar_soli = $vSoliR->importe_entrante;

            $totalPagadoFact = DB::table("fnzs_pagos_pago AS payment")
              ->join("fnzs_pagos_orden AS orden", "payment.orden_pago", "=", "orden.id")
              ->join("terc_reembolso_main AS reem_main", "orden.reembolso_main", "=", "reem_main.id")
              ->join("main_empresas AS emp", "orden.empresa", "=", "emp.id")
              ->where([
                "orden.token_ordenPago" => $vSoliR->token_ordenPago,
                "reem_main.token_reem" => $token_reem,
                "emp.empresa_token" => $empresa
              ])->get();

            if (count($totalPagadoFact) > 0) {
              foreach ($totalPagadoFact as $vPagado) {
                if ($moneda_entrante_tkn == $vPagado->p_moneda) {
                  $saldo_pagado = $saldo_pagado + $vPagado->monto_pago;
                } else {
                  $saldo_pagado = $saldo_pagado + ($vPagado->monto_pago / $vSoliR->tipo_cambio);
                }
                $saldo_pagado_conversion = $saldo_pagado * $vSoliR->tipo_cambio;
              }
            }

            $saldo_por_pagar = $saldo_total - $saldo_pagado;
            $saldo_por_pagar_conversion = $saldo_por_pagar * $vSoliR->tipo_cambio;
            
            $moneda_saliente_tkn = $vSoliR->moneda_saliente;
            $moneda_saliente_string = $vSoliR->moneda_saliente;
            $moneda_saliente_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_saliente);
          }
        }

        $row = array(
          "token_reem" => $token_reem,
          "folio_reem" => $folio_reem,
          "fecha_solicitud" => $fecha_solicitud,
          "date_solicitud" => $date_solicitud,
          "name_emisor" => $name_emisor,
          "rfc_gen_emi" => $rfc_gen_emi,
          "rfc_emp_emi" => $rfc_emp_emi,
          "taxid_emp_emi" => $taxid_emp_emi,
          "nombreEmiPers" => $nombreEmiPers,

          "no_ordenes" => $ordenesTotal,
          "ordenesVencidas" => $ordenesVencidas,
          "ordenesPorVencer" => $ordenesPorVencer,
          "ordenesProximas" => $ordenesProximas,
          "ordenesPagadas" => $ordenesPagadas,

          "saldo_pagado" => "$" . number_format($saldo_pagado, $moneda_entrante_decimales, '.', ','),
          "saldo_por_pagar" => "$" . number_format($saldo_por_pagar, $moneda_entrante_decimales, '.', ','),
          "saldo_total" => "$" . number_format($saldo_total, $moneda_entrante_decimales, '.', ','),
          "moneda_entrante" => $moneda_entrante_string,

          "saldo_pagado_conversion" => "$" . number_format($saldo_pagado_conversion, $moneda_saliente_decimales, '.', ','),
          "saldo_por_pagar_conversion" => "$" . number_format($saldo_por_pagar_conversion, $moneda_saliente_decimales, '.', ','),
          "saldo_total_conversion" => "$" . number_format($saldo_total_conversion, $moneda_saliente_decimales, '.', ','),
          "moneda_saliente" => $moneda_saliente_string,
        );
        $arrayReem[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'list_reem' => $arrayReem,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_op_detalle(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_reem' => 'required|string'
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
      $token_reem = $request->input('token_reem');
      
      if ($JwtAuth->usersAdmins($usuario)) {
        $reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
        ->join("fnzs_pagos_orden AS pay_orden", "reem_main.id", "=", "pay_orden.reembolso_main")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->where([
          "reem_main.token_reem" => $token_reem,
          "reem_main.status_reem" => TRUE,
          "emp.empresa_token" => $empresa
        ])->get();
      } else {
        $reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
        ->join("fnzs_pagos_orden AS pay_orden", "reem_main.id", "=", "pay_orden.reembolso_main")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_fnzs", "=", "pers.id")
        ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
        ->where([
          "reem_main.token_reem" => $token_reem,
          "reem_main.status_reem" => TRUE,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])->get();
      }

      if ($reembolso_main_selected->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron reembolsos registrados'
        );
      } else {
        $arrayReem = array();
				foreach ($reembolso_main_selected as $vremb) {
					$orden_pago_folio = "ORDP-" . $JwtAuth->generarFolio($vremb->folio_ordenPago);
					if ($vremb->fecha_sistema_ordenp + (73440) >= time()) {
						$modalidad = 'proximos';
					} else if (time() > ($vremb->fecha_sistema_ordenp + (73440)) && time() < ($vremb->fecha_sistema_ordenp + (86400))) {
						$modalidad = 'porvencer';
					} else if (time() >= ($vremb->fecha_sistema_ordenp + (86400))) {
						$modalidad = 'vencidos';
					}

					$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp
				    	JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
				    	AND emp.id = empuser.empresa AND empuser.empleado = pers.id
				    	AND pers.id = users.empleado AND users.usuario_token = ?", [$empresa, $usuario]);
					//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

					$fecha_solicitud = $JwtAuth->mostrarUnixAFechaMexico($vremb->fecha_sistema);
					$token_reem = $vremb->token_reem;

          $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).($vremb->post_folio_reem != NULL ? '-'.$vremb->post_folio_reem : '');

					//emisor
					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectNameEmpEmi as $vEmisor) {
						$name_emisor = $vEmisor->abrev_nombre;
						$rfc_gen_emi = $vEmisor->rfc_generico;
            $rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
            $taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
					}

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectPersEmpEmi as $vPemi) {
						$name_pers_emisor = $JwtAuth->desencriptarNombres($vPemi->paterno,$vPemi->materno,$vPemi->nombre);
					}

					$array_solicitudes_general = array();
					$arraySoliPendientes = array();
					$arraySoliPagadas = array();
					$num_lista = 1;

					$importe_total = 0;
					$importe_total_conversion = 0;

					$total_reembolsado = 0;
					$total_reembolsado_conversion = 0;

					$moneda_entrante_string = "";
					$moneda_entrante_string_min = "";
					$moneda_entrante_decimales = 0;

					$moneda_saliente_string = "";
					$moneda_saliente_string_min = "";
					$moneda_saliente_decimales = 0;
					$total_restante = 0;
					$total_restante_conversion = 0;

					$soli_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						//->join("fnzs_pagos_orden AS pay_orden","reem_soli.id","=","pay_orden.reembolso_solicitud")
						->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
						->where("reem_soli.autorizacion_vh", "!=", NULL)
						->where("reem_soli.autorizacion_vh", "!=", "D")
						->where(["reem_soli.autorizacion_egr" => "A"])
						->where(["reem_main.token_reem" => $token_reem])
						->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					foreach ($soli_reem as $vSoliR) {
						$fecha_gasto = $JwtAuth->mostrarUnixAFechaMexico($vSoliR->fecha_gasto);
						$fecha_gasto_html = $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria, $vSoliR->fecha_gasto);

						$autorizacion_vh = $vSoliR->autorizacion_vh;

						$select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main 
              JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
              AND s_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);

						$max_auth_vh = null;
						$fecha_registro_auth_vh = "";
						$hora_registro_auth_vh = "";
						$comments_auth_vh = "";
						$auth_vh_list_array = array();

						if (count($select_list_auth_vh) > 0) {
							foreach ($select_list_auth_vh as $l_auth) {
								$row_auth_vh = array(
									"autorizacion_vh" => $l_auth->autorizacion_vh,
									"registro_auth_vh" => $JwtAuth->mostrarUnixAFechaMexico($l_auth->fecha_registro),
									"comentarios" => $JwtAuth->desencriptar($l_auth->comentarios)
								);
								$auth_vh_list_array[] = $row_auth_vh;
							}
							if (end($select_list_auth_vh)->autorizacion_vh == "A" || end($select_list_auth_vh)->autorizacion_vh == "N") $max_auth_vh = true;
							if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
							$fecha_registro_auth_vh = $JwtAuth->mostrarUnixAFechaMexico(end($select_list_auth_vh)->fecha_registro);
							$hora_registro_auth_vh = $JwtAuth->mostrarUnixAFechaMexico(end($select_list_auth_vh)->fecha_registro);
							$comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
						}

						$autorizacion_egr = null;
						if ($vSoliR->autorizacion_egr == "A") $autorizacion_egr = true;
						if ($vSoliR->autorizacion_egr == "D") $autorizacion_egr = false;

						$select_list_auth_egr = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_egr,r_auth.comentarios 
                            FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                            AND s_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);

						$max_auth_egr = null;
						$fecha_registro_auth_egr = "";
						$hora_registro_auth_egr = "";
						$comments_auth_egr = "";
						if (count($select_list_auth_egr) > 0) {
							if (end($select_list_auth_egr)->autorizacion_egr == "A") $max_auth_egr = true;
							if (end($select_list_auth_egr)->autorizacion_egr == "D") $max_auth_egr = false;
							$fecha_registro_auth_egr = $JwtAuth->mostrarUnixAFechaMexico(end($select_list_auth_egr)->fecha_registro);
							$hora_registro_auth_egr = $JwtAuth->mostrarUnixAFechaMexico(end($select_list_auth_egr)->fecha_registro);
							$comments_auth_egr = $JwtAuth->desencriptar(end($select_list_auth_egr)->comentarios);
						}

						$fecha_respuesta_autorizacion = $JwtAuth->mostrarUnixAFechaMexico($vSoliR->tiempo_respuesta_autorizacion);
						$time_respuesta_autorizacion = "";
						if ($vSoliR->tiempo_respuesta_autorizacion > time()) {
							$time_inicial_autorizacion = $vSoliR->tiempo_respuesta_autorizacion - time();
							$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
							$time_inicial_autorizacion %= (60 * 60 * 24);
							$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
							$time_inicial_autorizacion %= (60 * 60);
							$min_autorizacion = floor($time_inicial_autorizacion / 60);
							$time_inicial_autorizacion %= 60;
							$sec_autorizacion = $time_inicial_autorizacion;
							$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; // 
						} else {
							$time_respuesta_autorizacion = "tiempo de respuesta terminado";
						}

						$listAnexosSoli = array();
						$selectAnexosReem = DB::table("sos_documentos AS docs")
							->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
							->where([
								"docs.status_documento" => TRUE,
								"docs.tipo_documento" => "an",
								"main.token_reem" => $token_reem,
								"reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
							])->get();

						foreach ($selectAnexosReem as $vDoc) {
							$token_docs = $vDoc->token_documento;
							$tipo_doc = $vDoc->tipo_documento;
							$ext_doc = $vDoc->extension_documento;

							$filepath_old = $vremb->root_tkn . "/0010-reem/" . $folio_reem . "/anexos";
							$filepath_new = $vremb->root_tkn . "/0010-reem/" . $folio_reem . "/" . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . "/anexos";
							$archivo_old = Storage::path('public/root/' . $filepath_old . '/' . $JwtAuth->desencriptar($vDoc->nombre_documento));
							$archivo_new = Storage::path('public/root/' . $filepath_new . '/' . $JwtAuth->desencriptar($vDoc->nombre_documento));

							if (file_exists($archivo_old)) {
								$extension = pathinfo($archivo_old, PATHINFO_EXTENSION);
								$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
								if ($extension == 'pdf') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_old);
									$html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
								}

								if ($extension == 'xml') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_old);
									$html = file_get_contents($archivo_old);
								}

								if ($extension == 'jpg' || $extension == 'png') {
									$base64 = $JwtAuth->encriptaBase64($archivo_old);
									$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
								}
							} else if (file_exists($archivo_new)) {
								$extension = pathinfo($archivo_new, PATHINFO_EXTENSION);
								$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
								if ($extension == 'pdf') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_new);
									$html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
								}

								if ($extension == 'xml') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_new);
									$html = file_get_contents($archivo_new);
								}

								if ($extension == 'jpg' || $extension == 'png') {
									$base64 = $JwtAuth->encriptaBase64($archivo_new);
									$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
								}
							} else {
								$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento) . " (inexistente)";
								$archivo = Storage::path('public/settings/dont_exist_evidencia.png');
								$extension = pathinfo($archivo, PATHINFO_EXTENSION);
								$base64 = $JwtAuth->encriptaBase64($archivo);
								$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
							}

							$rowDet = array(
								"token_docs" => $token_docs,
								"ext_doc" => $extension,
								"name_documento" => $name_documento,
								"html" => $html,
							);
							$listAnexosSoli[] = $rowDet;
						}
						//echo $vSoliR->proveedor;
						$tkn_prov = "";
						$name_prov = "";
						$rfc_generico_prov = "";
						$rfc_prov = "";
						$taxid_prov = "";
						if ($vSoliR->pagado_a == "prov" && $vSoliR->proveedor != NULL) {
							$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
								->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
								->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
								->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
								->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
								->where([
									"reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
									"rmain.token_reem" => $token_reem
								])->get();

							foreach ($soli_r_prov as $sr_prov) {
								$tkn_prov = $sr_prov->token_cat_proveedores;
								$name_prov = $JwtAuth->desencriptar($sr_prov->nombre_extendido);

								$rfc_generico_prov = $sr_prov->rfc_generico;

								if ($sr_prov->rfc != NULL) {
									$rfc_prov = $JwtAuth->desencriptar($sr_prov->rfc);
								} else {
									$rfc_prov = "---";
								}

								if ($sr_prov->tax_id != NULL) {
									$taxid_prov = $JwtAuth->desencriptar($sr_prov->tax_id);
								} else {
									$taxid_prov = "---";
								}
							}
						}

						$moneda_entrante_string = $vSoliR->moneda_entrante;
						$moneda_entrante_string_min = $vSoliR->moneda_entrante;
						$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						$moneda_origen_tkn = $vSoliR->moneda_entrante;
						$moneda_origen_codigo = $vSoliR->moneda_entrante;
						$moneda_origen_name = $vSoliR->moneda_entrante;
						$moneda_origen_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						$importe_total = $importe_total + $vSoliR->importe_entrante;
						$importe_total_conversion = $importe_total * $vSoliR->tipo_cambio;

						$moneda_saliente_string = $vSoliR->moneda_saliente;
						$moneda_saliente_string_min = $vSoliR->moneda_saliente;
						$moneda_saliente_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_saliente);

						$moneda_final_tkn = $vSoliR->moneda_saliente;
						$moneda_final_codigo = $vSoliR->moneda_saliente;
						$moneda_final_name = $vSoliR->moneda_saliente;
						$moneda_final_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_saliente);

						$saldototalPagadoFact = 0;
						$totalPagadoFact = DB::table("fnzs_pagos_pago AS payment")
						->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "=", "vinc.pago_realizado")
						->join("fnzs_pagos_orden AS orden", "vinc.orden_pago_vinculada", "=", "orden.id")
						->join("terc_reembolso_main AS reem_main", "orden.reembolso_main", "=", "reem_main.id")
						->join("main_empresas AS emp", "orden.empresa", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
						->where([
							"orden.token_ordenPago" => $vremb->token_ordenPago,
							"reem_main.token_reem" => $token_reem,
							"emp.empresa_token" => $empresa,
							"users.usuario_token" => $usuario
						])->get();

						if (count($totalPagadoFact) > 0) {
							foreach ($totalPagadoFact as $vPagado) {
								if ($moneda_origen_tkn == $vPagado->p_moneda) {
									$total_reembolsado = $total_reembolsado + $vPagado->monto_pago;
									$saldototalPagadoFact = $saldototalPagadoFact + $vPagado->monto_pago;
								} else {
									$total_reembolsado = $total_reembolsado + ($vPagado->monto_pago / $vSoliR->tipo_cambio);
									$saldototalPagadoFact = $saldototalPagadoFact + ($vPagado->monto_pago / $vSoliR->tipo_cambio);
								}
								$total_reembolsado_conversion = $total_reembolsado * $vSoliR->tipo_cambio;
							}
						} else {
							$saldototalPagadoFact = round(0);
						}

						$positionResta = $vSoliR->importe_entrante - $saldototalPagadoFact;

						$arrayPagosRegistrados = array();
						$num_lista_pagos = 1;
						$listaPagos = DB::select("SELECT payment.token_pagos,payment.folio_pagos,payment.fecha_sistema,payment.fecha_pago,payment.cuenta_bancaria,payment.cuenta_monedero,payment.caja,payment.monto_pago,payment.tipo_cambio,
              payment.forma_pago,payment.metodo_pago,payment.p_moneda,payment.concepto,payment.almacen,payment.personal_pago,payment.personal_autoriza,payment.empresa,payment.status_pagos,payment.fecha_deletePagos,payment.pago_autorizado 
              FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_pago_ordenes_vinculadas AS vinc JOIN fnzs_pagos_orden AS ordenp WHERE payment.id = vinc.pago_realizado AND vinc.orden_pago_vinculada = ordenp.id AND ordenp.token_ordenPago = ?",
							[$vremb->token_ordenPago]
						);

						foreach ($listaPagos as $resListaPagos) {
							$orden_pago_fecha_registro = $resListaPagos->fecha_sistema;

							$token_forma_pago = null;
							$clave_forma_pago = null;
							$forma_pago = null;

							if ($resListaPagos->forma_pago != NULL) {
								$pagosformaPago = DB::select("SELECT token_formapago,clave,forma FROM teci_forma_pago WHERE id = ?", [$resListaPagos->forma_pago]);
								$token_forma_pago = end($pagosformaPago)->token_formapago;
								$clave_forma_pago = end($pagosformaPago)->clave;
								$forma_pago = end($pagosformaPago)->forma;
							}

							$token_metodopago = null;
							$abrev_metodo_pago = null;
							$metodo_pago_name = null;
							if ($resListaPagos->metodo_pago != NULL) {
								$pagosmetodoPago = DB::select("SELECT token_metodopago,abrev,metodo FROM teci_metodo_pago WHERE id = ?", [$resListaPagos->metodo_pago]);
								$token_metodopago = end($pagosmetodoPago)->token_metodopago;
								$abrev_metodo_pago = end($pagosmetodoPago)->abrev;
								$metodo_pago_name = end($pagosmetodoPago)->metodo;
							}

							$namePersonalPaga = DB::table("vhum_empleados_catalogo AS pers")
								->join("sos_personas AS people", "pers.empleado_name", "people.id")
								->where([
									'pers.id' => $resListaPagos->personal_pago,
								])->get();

							if ($JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) == 'default-profile.png') {
								$img_perfil_paga = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil)));
							} else {
								$img_perfil_paga = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $selectEmp[0]->root_tkn . '/0004-vhm/catalogos/employees/' .
									$JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) . '/' . $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) . '-profile.png'));
							}

							$nombre_completo_paga = $JwtAuth->desencriptar($namePersonalPaga[0]->paterno) . " " .
								$JwtAuth->desencriptar($namePersonalPaga[0]->materno) . " " . $JwtAuth->desencriptar($namePersonalPaga[0]->nombre);

							$namePersonalAutoriza = DB::table("vhum_empleados_catalogo AS pers")
								->join("sos_personas AS people", "pers.empleado_name", "people.id")
								->where([
									'pers.id' => $resListaPagos->personal_autoriza,
								])->get();

							if ($JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) == 'default-profile.png') {
								$img_perfil_autoriza = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil)));
							} else {
								$img_perfil_autoriza = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $selectEmp[0]->root_tkn . '/0004-vhm/catalogos/employees/' .
									$JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) . '/' . $JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) . '-profile.png'));
							}

							$nombre_completo_autoriza = $JwtAuth->desencriptar($namePersonalAutoriza[0]->paterno) . " " .
								$JwtAuth->desencriptar($namePersonalAutoriza[0]->materno) . " " . $JwtAuth->desencriptar($namePersonalAutoriza[0]->nombre);

							$selectCuentas = array();
							$detalleMonedero = array();
							$cajacaja = array();
							$medio_pago = "";
							$medio_de_pago = "";
							$name_caja = "---";
							$name_cuenta_banc = "---";
							$name_cuenta_mone = "---";

							if ($resListaPagos->caja != NULL) {
								$medio_pago = "caja";
								$medio_de_pago = "caja";
								$tknCaja = DB::select("SELECT token_caja FROM fnzs_catalogos_caja WHERE id = ?", [$resListaPagos->caja]);

								$cajaPago = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "caja.almacen", "alm.id")
									->join("teci_direcciones AS dirubica", "alm.ubicacion", "dirubica.id")
									->join("in_egr_establecimientos_responsables AS respons", "caja.id", "respons.caja")
									->join("vhum_empleados_catalogo AS persnl", "respons.responsable", "persnl.id")
									->join("sos_personas AS people", "persnl.personal", "people.id")
									->join("teci_usuarios_catalogo AS users", "persnl.usuario", "users.id")
									//->where('respons.almacen','alm.id')
									->where([
										"caja.serv_ingresos" => TRUE,
										"caja.token_caja" => $tknCaja[0]->token_caja,
										"caja.empresa" => $selectEmp[0]->id,
										'users.usuario_token' => $usuario
									])->get();

								foreach ($cajaPago as $resultCaja) {
									$name_caja = $JwtAuth->generar($resultCaja->no_caja) . " (" . $JwtAuth->desencriptar($resultCaja->alias_caja) . ")";
									$arrayCaja = array(
										"token_caja" => $resultCaja->token_caja,
										"alias_caja" => $JwtAuth->desencriptar($resultCaja->alias_caja),
										"caja" => $JwtAuth->generar($resultCaja->no_caja),
									);

									$cajacaja[] = $arrayCaja;
								}
							}

							if ($resListaPagos->cuenta_bancaria != NULL) {
								$medio_pago = "cuenta_bancaria";
								$medio_de_pago = "cuenta bancaria";
								$tknCuenta = DB::select("SELECT token_cuenta FROM fnzs_catalogos_cuentas WHERE id = ?", [$resListaPagos->cuenta_bancaria]);

								$arrayContrato = array();
								$arrayCuenta = array();
								$arrayClabeInetr = array();
								$arraySucursal = array();
								$arrayTitular = array();
								$arrayOpcionAdicional = array();

								$respCuenta = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
									->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas.responsable", "pers.id")
									->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
									->where([
										'fnzs_catalogos_cuentas.status' => TRUE,
										'fnzs_catalogos_cuentas.egresos' => TRUE,
										'fnzs_catalogos_cuentas.token_cuenta' => $tknCuenta[0]->token_cuenta,
										'fnzs_catalogos_cuentas.empresa' => $selectEmp[0]->id,
										'users.usuario_token' => $usuario
									])
									->orwhere([
										'fnzs_catalogos_cuentas.status' => TRUE,
										'fnzs_catalogos_cuentas.v_humano' => TRUE,
										'fnzs_catalogos_cuentas.token_cuenta' => $tknCuenta[0]->token_cuenta,
										'fnzs_catalogos_cuentas.empresa' => $selectEmp[0]->id,
										'users.usuario_token' => $usuario
									])->get();

								if (count($respCuenta) != 0) {
									foreach ($respCuenta as $resCuentas) {
										//da_te_default_timezone_set($selectEmp[0]->zona_horaria);
										$claveBanco = $resCuentas->clave;
										$tknBancos = $resCuentas->token_bancos;

										$arrayStatusContrato = array(
											"status" => false,
											"no_contrato" => $resCuentas->contrato,
											"no_contrato_encrypt" => $resCuentas->contrato,
										);
										$arrayContrato[] = $arrayStatusContrato;

										$arrayStatusCuenta = array(
											"status" => false,
											"no_cuenta" => $resCuentas->cuenta,
											"no_cuenta_encrypt" => $resCuentas->cuenta,
										);
										$name_cuenta_banc = $JwtAuth->generar($resCuentas->folio_cuenta);
										$arrayCuenta[] = $arrayStatusCuenta;

										$arrayStatusClabInt = array(
											"status" => false,
											"clabe_inter" => $resCuentas->clabe_inter,
											"clabe_inter_encrypt" => $resCuentas->clabe_inter,
										);
										$arrayClabeInetr[] = $arrayStatusClabInt;

										if ($resCuentas->titular == '') {
											$titular = utf8_decode($JwtAuth->desencriptar('---'));
										} else {
											$titular = utf8_decode($JwtAuth->desencriptar($resCuentas->titular));
										}

										if ($resCuentas->opciones_adicionales != '-') {
											//echo $JwtAuth->desencriptar($resCuentas->opciones_adicionales);
											$optAdicional = json_decode($JwtAuth->desencriptar($resCuentas->opciones_adicionales));
											for ($i = 0; $i < count($optAdicional); $i++) {
												$optionAddc = array(
													"clave" => $optAdicional[$i]->clave,
													"valor" => $optAdicional[$i]->valor
												);
												$arrayOpcionAdicional[] = $optionAddc;
											}
										}

										if ($resCuentas->egresos == TRUE) {
											$egresos = true;
										} else {
											$egresos = false;
										}

										if ($resCuentas->v_humano == TRUE) {
											$v_humano = true;
										} else {
											$v_humano = false;
										}

										$sucursal = utf8_decode($JwtAuth->desencriptar($resCuentas->sucursal));

										$arrayCuentas = array(
											"token_cuenta" => $resCuentas->token_cuenta,
											"token_bancos" => $resCuentas->token_bancos,
											"nameBanco" => $resCuentas->clave . " - " . $resCuentas->nombre_comercial,
											"alta_cuenta" => $JwtAuth->mostrarUnixAFechaMexico($resCuentas->fecha_alta_cuenta),
											"folio" => $JwtAuth->generar($resCuentas->folio_cuenta),
											"contrato" => $arrayContrato,
											"cuenta" => $arrayCuenta,
											"clabe_inter" => $arrayClabeInetr,
											"sucursal" => $sucursal,
											"titular" => $titular,
											"moneda" =>  $resCuentas->moneda,
											"egresos" => $egresos,
											"v_humano" => $v_humano,
											"vigencia" => $JwtAuth->mostrarUnixAFechaMexico($resCuentas->vigencia),
											"opciones_adicionales" => $arrayOpcionAdicional,
										);

										$selectCuentas[] = $arrayCuentas;
									}
								}
							}

							if ($resListaPagos->cuenta_monedero != NULL) {
								$medio_pago = "cuenta_monedero_elect";
								$medio_de_pago = "cuenta de monedero electrónico";
								$arrayOpcionAdicionalMon = array();
								$idCuentaMonedero = DB::select("SELECT token_cuentamonedero FROM fnzs_catalogos_cuentas_monedero WHERE id = ?", [$resListaPagos->cuenta_monedero]);

								$respMonedero = CuentaMonederoModelo::join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas_monedero.responsable", "pers.id")
                ->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
                ->where([
                  'fnzs_catalogos_cuentas_monedero.status' => TRUE,
                  'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $idCuentaMonedero[0]->token_cuentamonedero,
                  'fnzs_catalogos_cuentas_monedero.empresa' => $selectEmp[0]->id,
                  'users.usuario_token' => $usuario
                ])
                ->where([
                  'fnzs_catalogos_cuentas_monedero.egresos' => TRUE
                ])
                ->orwhere([
                  'fnzs_catalogos_cuentas_monedero.v_humano' => TRUE
                ])->get();

								foreach ($respMonedero as $resMonedero) {
									$cuenta_bancaria = '';
									$name_cuenta = '';
									$token_caja = '';
									$folio_caja = '';
									$alias_caja = '';

									//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

									if ($resMonedero->cuenta_banco != '') {
										$tknCount = DB::select("SELECT token_cuenta FROM cuenta WHERE id = ? ", [$resMonedero->cuenta_banco]);
										$cuentaBancoMon = CuentBancModelo::join("main_empresas AS emp", "cuenta.empresa", "emp.id")
											->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
											->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
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
											->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
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

									$referencia = $resMonedero->referencia;
									$cuenta_monedero = $resMonedero->cuenta;
									$clabeInter = $resMonedero->clabe_inter;
									$titular = $JwtAuth->desencriptar($resMonedero->titular);

									if ($resMonedero->egresos == TRUE) {
										$egresos = true;
									} else {
										$egresos = false;
									}

									if ($resMonedero->v_humano == TRUE) {
										$v_humano = true;
									} else {
										$v_humano = false;
									}

									$selectManejCuenta = DB::table('fnzs_catalogos_cuentas_manejo AS man_count')
										->join("fnzs_catalogos_cuentas_monedero AS countMon", "man_count.cuenta_monedero", "countMon.id")
										->join("main_empresas AS emp", "man_count.empresa", "emp.id")
										->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
										->join("vhum_empleados_catalogo AS pers", "empuser.empleado", "pers.id")
										->join("sos_personas AS people", "pers.empleado_name", "people.id")
										->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
										->where([
											'man_count.cuenta_bancaria' => NULL,
											'countMon.token_cuentamonedero' => $resMonedero->token_cuentamonedero,
											'emp.empresa_token' => $empresa,
											'users.usuario_token' => $usuario
										])->get();

									foreach ($selectManejCuenta as $resOpciones) {
										if ($resOpciones->chequera == TRUE) {
											$chequera = true;
										} else {
											$chequera = false;
										}

										if ($resOpciones->credito == TRUE) {
											$credito = true;
										} else {
											$credito = false;
										}

										if ($resOpciones->debito == TRUE) {
											$debito = true;
										} else {
											$debito = false;
										}

										$arrayOptions = array(
											"token_manejocuentas" => $resOpciones->token_manejocuentas,
											"chequera" => $chequera,
											"credito" => $credito,
											"debito" => $debito,
											"valorManejo" => $resOpciones->clave_referencia,
											"token_personal" => $resOpciones->pers_token,
											"nombre_completo" => $JwtAuth->desencriptar($resOpciones->paterno)
												. " " . $JwtAuth->desencriptar($resOpciones->materno)
												. " " . $JwtAuth->desencriptar($resOpciones->nombre),
										);
										$arrayOpcionAdicional[] = $arrayOptions;
									}

									$arrayMonedero = array(
										'token_cuentaMon' => $resMonedero->token_cuentamonedero,
										'fecha_alta_cuentamoned' => $JwtAuth->mostrarUnixAFechaMexico($resMonedero->fecha_alta_cuentamoned),
										'folio' => $JwtAuth->generar($resMonedero->folio_cuentmon),

										'cuenta_bancaria' =>  $cuenta_bancaria,
										'name_cuenta_bancaria' =>  $name_cuenta,

										'token_caja' => $token_caja,
										'folio_caja' => $folio_caja,
										'alias_caja' => $alias_caja,

										'referencia' => $referencia,
										'cuenta_monedero' => $cuenta_monedero,
										'cuenta_monedero_encrypt' => $cuenta_monedero,
										'clabe_inter' => $clabeInter,
										'titular' => $titular,
										'moneda' => $resMonedero->moneda,
										'egresos' => $egresos,
										'v_humano' => $v_humano,
										'vigencia' => $JwtAuth->mostrarUnixAFechaMexico($resMonedero->vigencia),
										'opciones_adicionales' => $arrayOpcionAdicionalMon,
									);
									$name_cuenta_mone = $JwtAuth->generar($resMonedero->folio_cuentmon);
									$detalleMonedero[] = $arrayMonedero;
								}
							}

							$arrayEvidencias = array();
							$rutaDocsPay = $selectEmp[0]->root_tkn . "/0003-fnzs/ordenes_pagos/" . $orden_pago_fecha_registro . "-" . $orden_pago_folio . "/" .
								$orden_pago_fecha_registro . "-" . $orden_pago_folio . "-PAY-" . $JwtAuth->generarFolio($resListaPagos->folio_pagos) . '-';
							//echo $rutaDocsPay; 	
							/*$evidenciasPagos = DB::select("SELECT evidence.nombre_documento FROM sos_documentos AS evidence JOIN fnzs_pagos_pago AS payment 
				    		    WHERE evidence.pago = payment.id AND payment.token_pagos = ?",[$resListaPagos->token_pagos]);*/

							$evidenciasPagos = DB::table("sos_documentos AS evidence")
								->join("fnzs_pagos_pago AS payment", "evidence.pago", "=", "payment.id")
								->where(["payment.token_pagos" => $resListaPagos->token_pagos])
								->get();

							foreach ($evidenciasPagos as $evidFor) {
								if (file_exists(Storage::path('public/root/' . $rutaDocsPay . $JwtAuth->desencriptar($evidFor->nombre_documento)))) {
									$fiscalB64 = $JwtAuth->encriptaBase64Pdf(Storage::path('public/root/' . $rutaDocsPay . $JwtAuth->desencriptar($evidFor->nombre_documento)));
									$evidDoc = '<iframe src="' . $fiscalB64 . '" width="100%" height="400px"></iframe>';
									$evidName = $JwtAuth->desencriptar($evidFor->nombre_documento);
									$arrayEach = array(
										"evidDoc" => $evidDoc,
										"evidName" => $evidName,
									);
									$arrayEvidencias[] = $arrayEach;
								}
							}

							//$saldo_tipo_cambio = DB::select("SELECT FORMAT(?,?) AS saldoFormat",[$resListaPagos->tipo_cambio,$decimalesMoneda[0]->decimales]);

							$pago_autorizado = null;
							if ($resListaPagos->pago_autorizado == "F") $pago_autorizado = false;
							if ($resListaPagos->pago_autorizado == "V") $pago_autorizado = true;

							if ($resListaPagos->token_monedas == $moneda_origen_tkn) {
								$monto_formato = "$" . number_format($resListaPagos->monto_pago, $moneda_origen_decimales, '.', ',') . " " . $moneda_origen_codigo;
								$monto_formato_conversion = "$" . number_format($resListaPagos->monto_pago * $resListaPagos->tipo_cambio, $moneda_final_decimales, '.', ',') . " " . $moneda_final_codigo;

								$monto_simple = number_format($resListaPagos->monto_pago, $moneda_origen_decimales, '.', '') . " " . $moneda_origen_codigo;
								$monto_simple_conversion = number_format($resListaPagos->monto_pago * $resListaPagos->tipo_cambio, $moneda_final_decimales, '.', '') . " " . $moneda_final_codigo;
							} else {
								$monto_formato_conversion = "$" . number_format($resListaPagos->monto_pago, $moneda_final_decimales, '.', ',') . " " . $moneda_final_codigo;
								$monto_formato = "$" . number_format($resListaPagos->monto_pago / $vSoliR->tipo_cambio, $moneda_origen_decimales, '.', ',') . " " . $moneda_origen_codigo;

								$monto_simple_conversion = number_format($resListaPagos->monto_pago, $moneda_final_decimales, '.', '') . " " . $moneda_final_codigo;
								//$monto_simple = floatval($resListaPagos->monto_pago / $vSoliR->tipo_cambio)." ".$moneda_origen_codigo;
								$monto_simple = number_format($resListaPagos->monto_pago / $vSoliR->tipo_cambio, $moneda_final_decimales, '.', '') . " " . $moneda_origen_codigo;
							}

							$arrayEachPagohs = array(
								"num_lista" => $num_lista_pagos,
								//"nombre_documento" => $JwtAuth->desencriptar($resListaPagos->nombre_documento),
								"token_pagos" => $resListaPagos->token_pagos,
								"folio_pagos" => $JwtAuth->generar($resListaPagos->folio_pagos),
								"fecha_sistema" => $JwtAuth->mostrarUnixAFechaMexico($resListaPagos->fecha_sistema),
								"fecha_pago" => $JwtAuth->mostrarUnixAFechaMexico($resListaPagos->fecha_pago),
								"medio_pago" => $medio_pago,
								"medio_de_pago" => $medio_de_pago,
								"name_caja" => $name_caja,
								"name_cuenta_banc" => $name_cuenta_banc,
								"name_cuenta_mone" => $name_cuenta_mone,
								"caja" => $cajacaja,
								"cuenta_bancaria" => $selectCuentas,
								"cuenta_monedero" => $detalleMonedero,

								"formatMonto" => $monto_formato,
								"formatMonto_conversion" => $monto_formato_conversion,
								"monto_pago" => $monto_simple,
								"monto_pago_conversion" => $monto_simple_conversion,

								"tipo_cambio" => number_format($resListaPagos->tipo_cambio, $moneda_entrante_decimales, '.', ''),
								"token_forma_pago" => $token_forma_pago,
								"clave_forma_pago" => $clave_forma_pago,
								"forma_pago" => $forma_pago,
								"token_metodopago" => $token_metodopago,
								"abrev_metodo_pago" => $abrev_metodo_pago,
								"metodo_pago" => $metodo_pago_name,
								"moneda_token_monedas" => $resListaPagos->p_moneda,
								"moneda_codigo" => $resListaPagos->p_moneda,
								"moneda" => $JwtAuth->getMonedaAPI($resListaPagos->p_moneda),
								//"img_perfil_paga" => $img_perfil_paga,
								"nombre_completo_paga" => $nombre_completo_paga,
								"personal_autoriza" => $img_perfil_autoriza,
								"pago_autorizado" => $pago_autorizado,
								"personal_autoriza" => $nombre_completo_autoriza,
								"evidencias" => $arrayEvidencias,
							);

							$arrayPagosRegistrados[] = $arrayEachPagohs;
							++$num_lista_pagos;
						}

						$terminado = false;
						if ($vSoliR->terminado == TRUE) {
							$terminado = true;
						} else {
							if ($saldototalPagadoFact != 0 && round($saldototalPagadoFact) == $importe_total) {
								$update_auth_true = DB::table("terc_reembolso_solicitud AS reem_soli")
									->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
									->where(["reem_main.token_reem" => $token_reem, "reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])
									->limit(1)->update(array("reem_soli.terminado" => TRUE));
								$terminado = true;
							}
						}

						$row_soli = array(
							"num_lista" => $num_lista,
							"token_solicitud_reem" => $vSoliR->token_solicitud_reem,
							"folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
							"fecha_solicitud" => $JwtAuth->mostrarUnixAFechaMexico($vSoliR->fecha_solicitud),
							"fecha_gasto" => $fecha_gasto,
							"fecha_gasto_html" => $fecha_gasto_html,
							"ticket_gasto" => $JwtAuth->desencriptar($vSoliR->ticket_gasto),

							"pagado_a" => $vSoliR->pagado_a,
							//proveedor
							"tkn_prov" => $tkn_prov,
							"name_prov" => $name_prov,
							"rfc_generico_prov" => $rfc_generico_prov,
							"rfc_prov" => $rfc_prov,
							"taxid_prov" => $taxid_prov,

							//forma de pago
							"forma_pago_token" => $vSoliR->token_formapago,
							"forma_pago_clave" => $vSoliR->clave,
							"forma_pago_forma" => $vSoliR->forma,
							"forma_pago_extend" => $vSoliR->clave . " - " . $vSoliR->forma,

							"moneda_origen_tkn" => $moneda_origen_tkn,
							"moneda_origen_codigo" => $moneda_origen_codigo,
							"moneda_origen_name" => $moneda_origen_name,
							"tipo_cambio" => $vSoliR->tipo_cambio,

							"importe_requerido" => floatval($vSoliR->importe_entrante),
							"importe_requerido_conversion" => floatval($vSoliR->importe_entrante * $vSoliR->tipo_cambio) . "",

							"importe_requerido_info" => "$" . number_format($vSoliR->importe_entrante, $moneda_origen_decimales, '.', ','),
							"importe_requerido_info_conversion" => "$" . number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),

							"new_pago" => number_format(0, $moneda_saliente_decimales, '.'),
							"format_new_pago" => "$" . number_format(0, $moneda_origen_decimales, '.', ','),
							"format_new_pago_conversion" => "$" . number_format(0 * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),

							"saldototalPagadoFact" => $saldototalPagadoFact,
							"pagado" => number_format($saldototalPagadoFact, $moneda_origen_decimales, '.', ''),
							"pagado_conversion" => number_format($saldototalPagadoFact * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ''),
							"formatPagado" => "$" . number_format($saldototalPagadoFact, $moneda_origen_decimales, '.', ','),
							"formatPagado_respaldo" => "$" . number_format($saldototalPagadoFact, $moneda_origen_decimales, '.', ','),
							"formatPagado_conversion" => "$" . number_format($saldototalPagadoFact * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),
							"formatPagado_respaldo_conversion" => "$" . number_format($saldototalPagadoFact * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),

							"resta" => number_format($positionResta, $moneda_origen_decimales, '.', ''),
							"resta_conversion" => number_format($positionResta * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ''),
							"formatResta" => "$" . number_format($positionResta, $moneda_origen_decimales, '.', ','),
							"formatResta_conversion" => "$" . number_format($positionResta * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),
							"formatResta_respaldo" => "$" . number_format($positionResta, $moneda_origen_decimales, '.', ','),
							"formatResta_respaldo_conversion" => "$" . number_format($positionResta * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),

							"pagoCaja" => "0.00",
							"pagoCuenta" => "0.00",
							"pagoMonedero" => "0.00",
							"fondosaldo" => "",

							"moneda_final_tkn" => $moneda_final_tkn,
							"moneda_final_codigo" => $moneda_final_codigo,
							"moneda_final_name" => $moneda_final_name,

							"observaciones" => $JwtAuth->desencriptar($vSoliR->motivo_reem),
							//
							"autorizacion_vh" => $autorizacion_vh,
							"max_auth_vh" => $max_auth_vh,
							"comments_auth_vh" => $comments_auth_vh,
							"comments_auth_vh_back" => $comments_auth_vh,
							"fecha_registro_auth_vh" => $fecha_registro_auth_vh,
							"hora_registro_auth_vh" => $hora_registro_auth_vh,
							//
							"autorizacion_egr" => $autorizacion_egr,
							"max_auth_egr" => $max_auth_egr,
							"comments_auth_egr" => $comments_auth_egr,
							"comments_auth_egr_back" => $comments_auth_egr,
							"fecha_registro_auth_egr" => $fecha_registro_auth_egr,
							"hora_registro_auth_egr" => $hora_registro_auth_egr,
							//
							"terminado" => $terminado,
							//
							"fecha_respuesta_autorizacion" => $fecha_respuesta_autorizacion,
							"time_respuesta_autorizacion" => $time_respuesta_autorizacion,
							"anexos" => $listAnexosSoli,
							//
							"numEvidencias" => 0,
							"selected" => false,
							"pagosRegistrados" => $arrayPagosRegistrados,
							"pagosParaRealizar" => [],
							"sistemasContables" => [],
							"observacionesPago" => "",
							"diferencia_por_ajustes" => "$" . number_format($positionResta, $moneda_origen_decimales, '.', ','),
							"diferencia_por_ajustes_conversion" => "$" . number_format($positionResta * $vSoliR->tipo_cambio, $moneda_final_decimales, '.', ','),
						);

						$array_solicitudes_general[] = $row_soli;
						++$num_lista;
						if ($saldototalPagadoFact != 0 && round($saldototalPagadoFact) == $importe_total) {
							$arraySoliPagadas[] = $row_soli;
						} else {
							$arraySoliPendientes[] = $row_soli;
						}
					}

					$autorized_pago_class = "disabledView";
					$autorized_pago_bool = false;
					$fecha_autorized_pago = "";
					if ($vremb->autorizacion_pay == TRUE) {
						$autorized_pago_class = "";
						$autorized_pago_bool = true;
						$fecha_autorized_pago = $JwtAuth->mostrarUnixAFechaMexico($vremb->fecha_autorizacion_pay);
					}

					$total_restante = $importe_total - $total_reembolsado;
					$total_restante_conversion = $importe_total_conversion - $total_reembolsado_conversion;

					$row = array(
						"token_reem" => $token_reem,
						"folio_reem" => $folio_reem,
						"fecha_solicitud" => $fecha_solicitud,
						//emisor
						"emisor_company" => $name_emisor,
						"nombreEmiPers" => $name_pers_emisor,

						//"comienzaCredito" => $comienzaCredito,
						"saldototalNotFormat" => $importe_total,
						//"saldoPagadoNotFormat" => $saldoPagado,//"resultPagarNotFormat" => $saldoPagar,
						"moneda_entrante" => $moneda_entrante_string,
						"moneda_entrante_min" => $moneda_entrante_string_min,
						"saldototal" => "$" . number_format($importe_total, $moneda_entrante_decimales, '.', ','),
						"saldoPagado" => "$" . number_format($total_reembolsado, $moneda_entrante_decimales, '.', ','),
						"saldoPagar" => "$" . number_format($total_restante, $moneda_entrante_decimales, '.', ','),
						"moneda_saliente" => $moneda_saliente_string,
						"moneda_saliente_min" => $moneda_saliente_string_min,
						"saldototal_conversion" => "$" . number_format($importe_total_conversion, $moneda_saliente_decimales, '.', ','),
						"saldoPagado_conversion" => "$" . number_format($total_reembolsado_conversion, $moneda_entrante_decimales, '.', ','),
						"saldoPagar_conversion" => "$" . number_format($total_restante_conversion, $moneda_entrante_decimales, '.', ','),
						"solicitudes_general" => $array_solicitudes_general,
						"solicitudes_pendientes" => $arraySoliPendientes,
						"solicitudes_pagadas" => $arraySoliPagadas,
						"token_ordenPago" => $vremb->token_ordenPago,
						"foliOrdenPago" => "ORDP-" . $JwtAuth->generarFolio($vremb->folio_ordenPago),
						"modalidad" => $modalidad,
						"autorized_pago_class" => $autorized_pago_class,
						"autorized_pago_bool" => $autorized_pago_bool,
						"fecha_autorized_pago" => $fecha_autorized_pago,
					);

					$arrayReem[] = $row;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'reem_det' => $arrayReem,
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  //acreedores_movimientos movimientos
	public function registraMovimientoAcreedor(Request $request){
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
			'fecha_contabilizacion' => 'required|string',
			'pay_moneda' => 'required|string',
			'pay_tipo_cambio' => 'required|numeric',
			'pay_forma_pago' => 'required|string',
      'deudor_vinculado_token' => 'nullable|string',
      'movi_debe_haber' => 'nullable|string',
			'pay_importe' => 'required|numeric',
			'pay_caja' => 'nullable|array',
			'pay_cuenta_bancaria' => 'nullable|array',
			'pay_monedero_electronico' => 'nullable|array',
			'lista_movimientos' => 'nullable|array',
      'deu_total_saldo_aplicar' => 'nullable|string',
			'pay_observacion' => 'required|string'
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
      $token_cat_acreedores = $request->input('token_cat_acreedores');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $pay_moneda = $request->input('pay_moneda');
      $pay_tipo_cambio = $request->input('pay_tipo_cambio');
      $pay_forma_pago = $request->input('pay_forma_pago');
      $deudor_vinculado_token = $request->input('deudor_vinculado_token');
      $movi_debe_haber = $request->input('movi_debe_haber');
      $pay_importe = $request->input('pay_importe');
      $pay_caja = $request->input('pay_caja');
      $pay_cuenta_bancaria = $request->input('pay_cuenta_bancaria');
      $pay_monedero_electronico = $request->input('pay_monedero_electronico');
      $lista_movimientos = $request->input('lista_movimientos');
      $deu_total_saldo_aplicar = $request->input('deu_total_saldo_aplicar');
      $pay_observacion = $request->input('pay_observacion');
      //return response()->json(['codigo' => 200,'status' => 'error','message' => 'pais5'.$deu_total_saldo_aplicar]);
      //exit;

      $valide_order_token_cat_acreedores = isset($token_cat_acreedores) && !empty($token_cat_acreedores);
      $valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $valide_pay_moneda = isset($pay_moneda) && !empty($pay_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$pay_moneda);
      $valide_pay_tipo_cambio = isset($pay_tipo_cambio) && !empty($pay_tipo_cambio) && preg_match($JwtAuth->filtroCostoPrecio(),$pay_tipo_cambio);
      $valide_pay_forma_pago = isset($pay_forma_pago) && !empty($pay_forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$pay_forma_pago);
      $valide_deudor_vinculado_token = isset($deudor_vinculado_token) && !empty($deudor_vinculado_token);
      $valide_movi_debe_haber = isset($movi_debe_haber) && !empty($movi_debe_haber) && preg_match($JwtAuth->filtroAlfaNumerico(),$movi_debe_haber);
      $valide_pay_importe = isset($pay_importe) && !empty($pay_importe) && preg_match($JwtAuth->filtroCostoPrecio(),$pay_importe);
      $valide_pay_caja = isset($pay_caja) && !empty($pay_caja);
      $valide_pay_cuenta_bancaria = isset($pay_cuenta_bancaria) && !empty($pay_cuenta_bancaria);
      $valide_pay_monedero_electronico = isset($pay_monedero_electronico) && !empty($pay_monedero_electronico);
      $valide_debe_haber = $movi_debe_haber == "haber" || $movi_debe_haber == "debe";
      $valide_lista_movimientos = isset($lista_movimientos) && !empty($lista_movimientos) && count($lista_movimientos) > 0;
      $valide_deu_saldo = $pay_forma_pago == "por-compensacion" && (isset($deu_total_saldo_aplicar) && !empty($deu_total_saldo_aplicar) && preg_match($JwtAuth->filtroCostoPrecio(),$deu_total_saldo_aplicar)) || $pay_forma_pago != "por-compensacion";
      $valide_pay_observacion = isset($pay_observacion) && !empty($pay_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $pay_observacion);
      $fechaSistema = time();

      if (
        $valide_order_token_cat_acreedores && 
        $valide_fecha_contabilizacion && 
        $valide_pay_moneda && 
        $valide_pay_tipo_cambio && 
        $valide_pay_forma_pago && 
        $valide_movi_debe_haber &&
        $valide_pay_importe && 
        $valide_debe_haber && 
        $valide_deu_saldo && 
        $valide_pay_observacion
      ) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$empresa, $usuario]);
        foreach ($queryEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);

          $folioMovimientos = DB::select("SELECT IF (max(acrmov.folio_acre_mov) IS NOT NULL,(max(acrmov.folio_acre_mov)+1),1) AS folio FROM fnzs_catalogo_acreedores_movimientos AS acrmov JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE acrmov.acre_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]
          );
          
          $tokenMov = $JwtAuth->encriptarToken($pay_importe.$pay_observacion.$fechaSistema);
          $folio_pago_generar = "ACRMOV-".$JwtAuth->generarFolio($folioMovimientos[0]->folio);

          $insertPagoMon = DB::table("fnzs_catalogo_acreedores_movimientos")
          ->insert(
            array(
              "token_acre_mov" => $tokenMov,
              "folio_acre_mov" => $folioMovimientos[0]->folio,
              "acre_fecha_registro" => time(),
              "acre_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              "acre_monto_mov" => $pay_importe,
              "condicion_acree_mov" => $movi_debe_haber == "debe" ? "R" : "S",
              "acre_observaciones_mov" => $JwtAuth->encriptar($pay_observacion),
              "acre_tipo_cambio" => $pay_tipo_cambio,
              "acre_mov_moneda" => $pay_moneda,
              "vinc_acreedor" => DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$token_cat_acreedores)->value("id"),
              "acre_personal_mov" => $vEmp->userr,
              "acre_mov_autorizado" => TRUE,
              "acre_fecha_mov_auth" => time(),
              "acre_personal_autoriza" => $vEmp->userr,
              "acre_empresa" => $vEmp->id,
              "acre_status_mov" => TRUE
            )
          );

          $id_mov_realizado = DB::table("fnzs_catalogo_acreedores_movimientos")->where("token_acre_mov",$tokenMov)->value("id");

          $id_deu_mov_realizado = NULL;
          if ($pay_forma_pago == "por-compensacion") {
            $identDeudor = $valide_deudor_vinculado_token ? DB::table('fnzs_catalogo_deudores')->where("token_cat_deudores",$deudor_vinculado_token)->value("id") : NULL;
            $folioDeuMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$empresa, $usuario]
            );
            $token_deu_mov = $JwtAuth->encriptarToken($pay_importe.$pay_observacion.time());
            $folio_pago_generar = "DEUMOV-".$JwtAuth->generarFolio($folioDeuMovimientos[0]->folio);

            $insertPagoMon = DB::table("fnzs_catalogo_deudores_movimientos")
            ->insert(
              array(
                "token_deu_mov" => $token_deu_mov,
                "folio_deu_mov" => $folioDeuMovimientos[0]->folio,
                "deu_fecha_registro" => time(),
                "deu_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                //"orden_pago_vinculada" => DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_de_pago_vinculada)->value("id"),
                "condicion_deu_mov" => $movi_debe_haber == "haber" ? "R" : "S",
                "deu_monto_mov" => $deu_total_saldo_aplicar,
                "deu_observaciones_mov" => $JwtAuth->encriptar($pay_observacion),
                "deu_tipo_cambio" => $pay_tipo_cambio,
                "deu_mov_moneda" => $pay_moneda,
                "vinc_deudor" => $identDeudor,
                "deu_personal_mov" => $vEmp->userr,
                "deu_mov_autorizado" => TRUE,
                "deu_fecha_mov_auth" => time(),
                "deu_personal_autoriza" => $vEmp->userr,
                "deu_empresa" => $vEmp->id,
                "deu_status_mov" => TRUE,
              )
            );

            $folioActMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
              AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

            $token_movimiento = $JwtAuth->encriptarToken($pay_importe,$folioActMovimiento[0]->folio,$pay_observacion);
            $insertActMovimiento = DB::table("fnzs_actividad_movimientos")
            ->insert(
              array(
                "token_movimiento" => $token_movimiento,
                "folio_movimiento" => $folioActMovimiento[0]->folio,
                "fecha_sistema" => time(),
                "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                "tipo_movimiento" => $movi_debe_haber == "haber" ? "R" : "S",
                "subtipo_movimiento" => "C",
                "responsable" => $vEmp->userr,
                "monto_aplicado" => $deu_total_saldo_aplicar,
                "tipo_cambio_movimiento" => $pay_tipo_cambio,
                "moneda_movimiento" => $pay_moneda,
                "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                "deudor_movimiento" => DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$token_deu_mov)->value("id"),
                "empresa" => $vEmp->id
              )
            );
          }

          if ($valide_pay_caja && count($pay_caja) > 0) {
            for ($i=0; $i < count($pay_caja); $i++) { 
              $token_caja = $pay_caja[$i]["token_caja"];
              $monto_aplicar = $pay_caja[$i]["monto_aplicar"];
              $sql_caja = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
              $insertPagoCaja = DB::table("fnzs_catalogo_acreedores_movimientos_cajas")
              ->insert(array("mov_realizado" => $id_mov_realizado,"caja_relacionada" => $sql_caja));

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_caja,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => $movi_debe_haber == "debe" ? "R" : "S",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "caja" => $sql_caja,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $pay_tipo_cambio,
                  "moneda_movimiento" => $pay_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                  "acreedor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }

          if ($valide_pay_cuenta_bancaria && count($pay_cuenta_bancaria) > 0) {
            for ($i=0; $i < count($pay_cuenta_bancaria); $i++) { 
              $token_cuenta = $pay_cuenta_bancaria[$i]["token_cuenta"];
              $monto_aplicar = $pay_cuenta_bancaria[$i]["monto_aplicar"];
              $sql_cuenta_bancaria = DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuenta)->value("id");
              $insertPagoCuenta = DB::table("fnzs_catalogo_acreedores_movimientos_cuentas")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "cuenta_relacionada" => $sql_cuenta_bancaria
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_cuenta_bancaria,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => $movi_debe_haber == "debe" ? "R" : "S",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_bancaria" => $sql_cuenta_bancaria,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $pay_tipo_cambio,
                  "moneda_movimiento" => $pay_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                  "acreedor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }

          if ($valide_pay_monedero_electronico && count($pay_monedero_electronico) > 0) {
            for ($i=0; $i < count($pay_monedero_electronico); $i++) { 
              $token_cuentaMon = $pay_monedero_electronico[$i]["token_cuentaMon"];
              $monto_aplicar = $pay_monedero_electronico[$i]["monto_aplicar"];
              $sql_cuenta_monedero = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");

              $insertPagoCuenta = DB::table("fnzs_catalogo_acreedores_movimientos_monederos")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "moned_relacionado" => $sql_cuenta_monedero
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_cuenta_monedero,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => $movi_debe_haber == "debe" ? "R" : "S",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_monedero" => $sql_cuenta_monedero,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $pay_tipo_cambio,
                  "moneda_movimiento" => $pay_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                  "acreedor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }
          
          if ($movi_debe_haber == "haber" && count($lista_movimientos) > 0) {
            for ($i=0; $i < count($lista_movimientos); $i++) {
              $token_pagos = $lista_movimientos[$i]["token_pagos"];
              $importe_por_pagar = $lista_movimientos[$i]["importe_por_pagar"];
              //echo "importe_por_pagar $i ";
              $importe_restante = $lista_movimientos[$i]["debe_simple"];
              $insertPagoVinc = DB::table("fnzs_catalogo_acreedores_movimientos_pagos_vinculados")
              ->insert(array("mov_realizado" => $id_mov_realizado,"pago_vinculado" => DB::table("fnzs_pagos_pago")->where("token_pagos",$token_pagos)->value("id"),"mov_pago_monto" => $importe_por_pagar));
              
              //if ($importe_restante == "0.00") {
              //	DB::table("fnzs_pagos_pago")->where("token_pagos",$token_pagos)->limit(1)->update(array("orden_terminada_bool" => TRUE, "orden_terminada_fecha" => time()));
              //}

              //if ($tipo_factura_relacionada == "reembolsos") {
              //  $mensaje_user = "Recibiste un pago del reembolso con folio $factura_relacionada_string por un total de: $$importe_por_pagar $pay_moneda";
              //  //$JwtAuth->insertGeneralNotif($asunt,$titulo_alert,$typo ,$area,$sub,$empresa         ,$emisor             ,$receptor)
              //	$query_ord_reem = DB::table("fnzs_pagos_orden AS order")
              //  ->join("terc_reembolso_main AS reem_main", "order.reembolso_main", "=", "reem_main.id")
              //  ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
              //  ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
              //  ->where(["order.token_ordenPago" => $orden_pago, "reem_main.token_reem" => $factura_relacionada])->get();
              //	foreach ($query_ord_reem as $vOrd) {
              //    $JwtAuth->insertGeneralNotif("Pago de reembolso", $mensaje_user, "terem", NULL, NULL, $vEmp->id, $vEmp->userr, $vOrd->user_emisor);
              //	}
              //}
            }
          }

          $fecha_sistema_mov = DB::table("fnzs_catalogo_acreedores_movimientos")->where("token_acre_mov",$tokenMov)->value("acre_fecha_registro");
          $filepath = $vEmp->root_tkn . "/0003-fnzs/acreedores/movimientos/$fecha_sistema_mov-$folio_pago_generar/pago_evidencias/";
          if (!file_exists(storage_path("/root/$filepath"))) {
            Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
          }
          //"orden_pago" => $id_ord_pago,
          if (!empty($_FILES['evidencias_pagos'])) {
            $evidencias = $_FILES["evidencias_pagos"];
            $string_name_evid = json_encode($_FILES["evidencias_pagos"]["name"]);
            if (count(json_decode($string_name_evid)) != 0) {
              $evidencia_nombre = json_decode($string_name_evid);
              for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                $temporal = $evidencias["tmp_name"][$doc];
                $doc_name = $evidencias["name"][$doc];
                Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);

                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PAY-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($id_mov_realizado,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "PAY-EVID" . $select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "acreedor_movimiento" => $id_mov_realizado,
                    "status_documento" => TRUE,
                  )
                );
                //return response()->json(['message' => 'pais5'.$doc_name,'codigo' => 200,'status' => 'error']);
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
        if (!$valide_order_token_cat_acreedores) $mensaje_error = "Error en acreedor seleccionado, verifique su información";
        if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
        if (!$valide_pay_moneda) $mensaje_error = "Error en moneda seleccionada, verifique su información";
        if (!$valide_pay_tipo_cambio) $mensaje_error = "Error en tipo de cambio, verifique su información";
        if (!$valide_pay_forma_pago) $mensaje_error = "Error en forma de pago seleccionada, verifique su información";
        if (!$valide_movi_debe_haber) $mensaje_error = "Error en tipo de movimiento seleccionado, verifique su información";
        if (!$valide_pay_importe) $mensaje_error = "Error en importe de pago, verifique su información";
        if (!$valide_lista_movimientos) $mensaje_error = "Error en facturas seleccionadas, verifique su información";
        if (!$valide_deu_saldo) $mensaje_error = "Error en saldo aplicado al deudor, verifique su información";
        if (!$valide_pay_observacion) $mensaje_error = "Error en observaciones finales, verifique su información";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function registraMovimientoDeudor(Request $request){
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
			'fecha_contabilizacion' => 'required|string',
			'pay_moneda' => 'required|string',
			'pay_tipo_cambio' => 'required|numeric',
			'pay_forma_pago' => 'required|string',
      'acreedor_vinculado_token' => 'string',
      'movi_debe_haber' => 'string',
			'pay_importe' => 'required|numeric',
			'pay_caja' => 'array',
			'pay_cuenta_bancaria' => 'array',
			'pay_monedero_electronico' => 'array',
			'lista_movimientos' => 'array',
      'acr_total_saldo_aplicar' => 'numeric',
			'pay_observacion' => 'required|string'
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
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $pay_moneda = $request->input('pay_moneda');
      $pay_tipo_cambio = $request->input('pay_tipo_cambio');
      $pay_forma_pago = $request->input('pay_forma_pago');
      $acreedor_vinculado_token = $request->input('acreedor_vinculado_token');
      $movi_debe_haber = $request->input('movi_debe_haber');
      $pay_importe = $request->input('pay_importe');
      $pay_caja = $request->input('pay_caja');
      $pay_cuenta_bancaria = $request->input('pay_cuenta_bancaria');
      $pay_monedero_electronico = $request->input('pay_monedero_electronico');
      $lista_movimientos = $request->input('lista_movimientos');
      $acr_total_saldo_aplicar = $request->input('acr_total_saldo_aplicar');
      $pay_observacion = $request->input('pay_observacion');
      //return response()->json(['codigo' => 200,'status' => 'error','message' => 'pais5'.$acr_total_saldo_aplicar]);
      //exit;

      $valide_order_token_cat_deudores = isset($token_cat_deudores) && !empty($token_cat_deudores);
      $valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $valide_pay_moneda = isset($pay_moneda) && !empty($pay_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$pay_moneda);
      $valide_pay_tipo_cambio = isset($pay_tipo_cambio) && !empty($pay_tipo_cambio) && preg_match($JwtAuth->filtroCostoPrecio(),$pay_tipo_cambio);
      $valide_pay_forma_pago = isset($pay_forma_pago) && !empty($pay_forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$pay_forma_pago);
      $valide_acreedor_vinculado_token = isset($acreedor_vinculado_token) && !empty($acreedor_vinculado_token);
      $valide_movi_debe_haber = isset($movi_debe_haber) && !empty($movi_debe_haber) && preg_match($JwtAuth->filtroAlfaNumerico(),$movi_debe_haber);
      $valide_pay_importe = isset($pay_importe) && !empty($pay_importe) && preg_match($JwtAuth->filtroCostoPrecio(),$pay_importe);
      $valide_pay_caja = isset($pay_caja) && !empty($pay_caja);
      $valide_pay_cuenta_bancaria = isset($pay_cuenta_bancaria) && !empty($pay_cuenta_bancaria);
      $valide_pay_monedero_electronico = isset($pay_monedero_electronico) && !empty($pay_monedero_electronico);
      $valide_lista_movimientos = isset($lista_movimientos) && !empty($lista_movimientos) && count($lista_movimientos) > 0;
      $valide_deu_saldo = $pay_forma_pago == "por-compensacion" && (isset($acr_total_saldo_aplicar) && !empty($acr_total_saldo_aplicar) && preg_match($JwtAuth->filtroCostoPrecio(),$acr_total_saldo_aplicar)) || $pay_forma_pago != "por-compensacion";
      $valide_pay_observacion = isset($pay_observacion) && !empty($pay_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $pay_observacion);
      $fechaSistema = time();

      if (
        $valide_order_token_cat_deudores && 
        $valide_fecha_contabilizacion && 
        $valide_pay_moneda && 
        $valide_pay_tipo_cambio && 
        $valide_pay_forma_pago && 
        $valide_movi_debe_haber &&
        $valide_pay_importe &&
        $valide_deu_saldo && 
        $valide_pay_observacion
      ) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$empresa, $usuario]);
        foreach ($queryEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);

          $folioMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]
          );
          
          $tokenMov = $JwtAuth->encriptarToken($pay_importe.$pay_observacion.$fechaSistema);
          $folio_pago_generar = "DEUMOV-".$JwtAuth->generarFolio($folioMovimientos[0]->folio);

          $insertPagoMon = DB::table("fnzs_catalogo_deudores_movimientos")
          ->insert(
            array(
              "token_deu_mov" => $tokenMov,
              "folio_deu_mov" => $folioMovimientos[0]->folio,
              "deu_fecha_registro" => time(),
              "deu_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              "deu_monto_mov" => $pay_importe,
              "condicion_deu_mov" => $movi_debe_haber == "haber" ? "R" : "S",
              "deu_observaciones_mov" => $JwtAuth->encriptar($pay_observacion),
              "deu_tipo_cambio" => $pay_tipo_cambio,
              "deu_mov_moneda" => $pay_moneda,
              "vinc_deudor" => DB::table("fnzs_catalogo_deudores")->where("token_cat_deudores",$token_cat_deudores)->value("id"),
              "deu_personal_mov" => $vEmp->userr,
              "deu_mov_autorizado" => TRUE,
              "deu_fecha_mov_auth" => time(),
              "deu_personal_autoriza" => $vEmp->userr,
              "deu_empresa" => $vEmp->id,
              "deu_status_mov" => TRUE
            )
          );

          $id_mov_realizado = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenMov)->value("id");

          $id_deu_mov_realizado = NULL;
          if ($pay_forma_pago == "por-compensacion") {
            $identDeudor = $valide_acreedor_vinculado_token ? DB::table('fnzs_catalogo_acreedores')->where("token_cat_acreedores",$acreedor_vinculado_token)->value("id") : NULL;
            $folioAcrMovimientos = DB::select("SELECT IF (max(acrmov.folio_acre_mov) IS NOT NULL,(max(acrmov.folio_acre_mov)+1),1) AS folio FROM fnzs_catalogo_acreedores_movimientos AS acrmov JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE acrmov.acre_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$empresa, $usuario]
            );
            $token_acre_mov = $JwtAuth->encriptarToken($pay_importe.$pay_observacion.time());
            $folio_pago_generar = "ACRMOV-".$JwtAuth->generarFolio($folioAcrMovimientos[0]->folio);

            $insertPagoMon = DB::table("fnzs_catalogo_acreedores_movimientos")
            ->insert(
              array(
                "token_acre_mov" => $token_acre_mov,
                "folio_acre_mov" => $folioAcrMovimientos[0]->folio,
                "acre_fecha_registro" => time(),
                "acre_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                //"orden_pago_vinculada" => DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_de_pago_vinculada)->value("id"),
                "condicion_acree_mov" => $movi_debe_haber == "debe" ? "R" : "S",
                "acre_monto_mov" => $acr_total_saldo_aplicar,

                "acre_observaciones_mov" => $JwtAuth->encriptar($pay_observacion),
                "acre_tipo_cambio" => $pay_tipo_cambio,
                "acre_mov_moneda" => $pay_moneda,
                "vinc_acreedor" => $identDeudor,
                "acre_personal_mov" => $vEmp->userr,
                "acre_mov_autorizado" => TRUE,
                "acre_fecha_mov_auth" => time(),
                "acre_personal_autoriza" => $vEmp->userr,
                "acre_empresa" => $vEmp->id,
                "acre_status_mov" => TRUE,
              )
            );

            $folioActMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
              AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

            $token_movimiento = $JwtAuth->encriptarToken($pay_importe,$folioActMovimiento[0]->folio,$pay_observacion);
            $insertActMovimiento = DB::table("fnzs_actividad_movimientos")
            ->insert(
              array(
                "token_movimiento" => $token_movimiento,
                "folio_movimiento" => $folioActMovimiento[0]->folio,
                "fecha_sistema" => time(),
                "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                "tipo_movimiento" => $movi_debe_haber == "debe" ? "R" : "S",
                "subtipo_movimiento" => "C",
                "responsable" => $vEmp->userr,
                "monto_aplicado" => $acr_total_saldo_aplicar,
                "tipo_cambio_movimiento" => $pay_tipo_cambio,
                "moneda_movimiento" => $pay_moneda,
                "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),

                "acreedor_movimiento" => DB::table("fnzs_catalogo_acreedores_movimientos")->where("token_acre_mov",$token_acre_mov)->value("id"),
                "empresa" => $vEmp->id
              )
            );
          }

          if ($valide_pay_caja && count($pay_caja) > 0) {
            for ($i=0; $i < count($pay_caja); $i++) { 
              $token_caja = $pay_caja[$i]["token_caja"];
              $monto_aplicar = $pay_caja[$i]["monto_aplicar"];
              $sql_caja = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
              $insertPagoCaja = DB::table("fnzs_catalogo_deudores_movimientos_cajas")
              ->insert(array("mov_realizado" => $id_mov_realizado,"caja_relacionada" => $sql_caja));

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_caja,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => $movi_debe_haber == "haber" ? "R" : "S",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "caja" => $sql_caja,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $pay_tipo_cambio,
                  "moneda_movimiento" => $pay_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                  "deudor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }

          if ($valide_pay_cuenta_bancaria && count($pay_cuenta_bancaria) > 0) {
            for ($i=0; $i < count($pay_cuenta_bancaria); $i++) { 
              $token_cuenta = $pay_cuenta_bancaria[$i]["token_cuenta"];
              $monto_aplicar = $pay_cuenta_bancaria[$i]["monto_aplicar"];
              $sql_cuenta_bancaria = DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuenta)->value("id");
              $insertPagoCuenta = DB::table("fnzs_catalogo_deudores_movimientos_cuentas")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "cuenta_relacionada" => $sql_cuenta_bancaria
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_cuenta_bancaria,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => $movi_debe_haber == "debe" ? "R" : "S",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_bancaria" => $sql_cuenta_bancaria,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $pay_tipo_cambio,
                  "moneda_movimiento" => $pay_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                  "deudor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }

          if ($valide_pay_monedero_electronico && count($pay_monedero_electronico) > 0) {
            for ($i=0; $i < count($pay_monedero_electronico); $i++) { 
              $token_cuentaMon = $pay_monedero_electronico[$i]["token_cuentaMon"];
              $monto_aplicar = $pay_monedero_electronico[$i]["monto_aplicar"];
              $sql_cuenta_monedero = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");

              $insertPagoCuenta = DB::table("fnzs_catalogo_deudores_movimientos_monederos")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "moned_relacionado" => $sql_cuenta_monedero
                )
              );

              $folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_cuenta_monedero,$folioMovimiento[0]->folio);

              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovimiento[0]->folio,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => $movi_debe_haber == "debe" ? "R" : "S",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_monedero" => $sql_cuenta_monedero,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $pay_tipo_cambio,
                  "moneda_movimiento" => $pay_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($pay_observacion),
                  "deudor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }
          
          if ($movi_debe_haber == "debe" && $valide_lista_movimientos) {
            for ($i=0; $i < count($lista_movimientos); $i++) {
              $token_pagos = $lista_movimientos[$i]["token_pagos"];
              $importe_por_pagar = $lista_movimientos[$i]["importe_por_pagar"];
              //echo "importe_por_pagar $i ";
              $importe_restante = $lista_movimientos[$i]["debe_simple"];
              $insertPagoVinc = DB::table("fnzs_catalogo_deudores_movimientos_pagos_vinculados")
              ->insert(array(
                "mov_realizado" => $id_mov_realizado,
                "pago_vinculado" => DB::table("fnzs_pagos_pago")->where("token_pagos",$token_pagos)->value("id"),
                "mov_pago_monto" => $importe_por_pagar
              ));
              
              //if ($importe_restante == "0.00") {
              //	DB::table("fnzs_pagos_pago")->where("token_pagos",$token_pagos)->limit(1)->update(array("orden_terminada_bool" => TRUE, "orden_terminada_fecha" => time()));
              //}

              //if ($tipo_factura_relacionada == "reembolsos") {
              //  $mensaje_user = "Recibiste un pago del reembolso con folio $factura_relacionada_string por un total de: $$importe_por_pagar $pay_moneda";
              //  //$JwtAuth->insertGeneralNotif($asunt,$titulo_alert,$typo ,$area,$sub,$empresa         ,$emisor             ,$receptor)
              //	$query_ord_reem = DB::table("fnzs_pagos_orden AS order")
              //  ->join("terc_reembolso_main AS reem_main", "order.reembolso_main", "=", "reem_main.id")
              //  ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
              //  ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
              //  ->where(["order.token_ordenPago" => $orden_pago, "reem_main.token_reem" => $factura_relacionada])->get();
              //	foreach ($query_ord_reem as $vOrd) {
              //    $JwtAuth->insertGeneralNotif("Pago de reembolso", $mensaje_user, "terem", NULL, NULL, $vEmp->id, $vEmp->userr, $vOrd->user_emisor);
              //	}
              //}
            }
          }

          $fecha_sistema_mov = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenMov)->value("deu_fecha_registro");
          $filepath = $vEmp->root_tkn . "/0003-fnzs/deudores/movimientos/$fecha_sistema_mov-$folio_pago_generar/pago_evidencias/";
          if (!file_exists(storage_path("/root/$filepath"))) {
            Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
          }
          //"orden_pago" => $id_ord_pago,
          if (!empty($_FILES['evidencias_pagos'])) {
            $evidencias = $_FILES["evidencias_pagos"];
            $string_name_evid = json_encode($_FILES["evidencias_pagos"]["name"]);
            if (count(json_decode($string_name_evid)) != 0) {
              $evidencia_nombre = json_decode($string_name_evid);
              for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                $temporal = $evidencias["tmp_name"][$doc];
                $doc_name = $evidencias["name"][$doc];
                Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);

                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PAY-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($id_mov_realizado,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "PAY-EVID" . $select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "deudor_movimiento" => $id_mov_realizado,
                    "status_documento" => TRUE,
                  )
                );
                //return response()->json(['message' => 'pais5'.$doc_name,'codigo' => 200,'status' => 'error']);
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
        if (!$valide_order_token_cat_deudores) $mensaje_error = "Error en acreedor seleccionado, verifique su información";
        if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
        if (!$valide_pay_moneda) $mensaje_error = "Error en moneda seleccionada, verifique su información";
        if (!$valide_pay_tipo_cambio) $mensaje_error = "Error en tipo de cambio, verifique su información";
        if (!$valide_pay_forma_pago) $mensaje_error = "Error en forma de pago seleccionada, verifique su información";
        if (!$valide_movi_debe_haber) $mensaje_error = "Error en tipo de movimiento seleccionado, verifique su información";
        if (!$valide_pay_importe) $mensaje_error = "Error en importe de pago, verifique su información";
        if (!$valide_lista_movimientos) $mensaje_error = "Error en facturas seleccionadas, verifique su información";
        if (!$valide_deu_saldo) $mensaje_error = "Error en saldo aplicado al deudor, verifique su información";
        if (!$valide_pay_observacion) $mensaje_error = "Error en observaciones finales, verifique su información";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}
}
