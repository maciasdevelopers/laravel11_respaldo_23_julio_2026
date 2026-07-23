<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ComprasModelo;
use App\Models\OrdenPagoModelo;
use App\Models\ActivosFijosModelo;
use App\Models\ActivosIntangiblesModelo;
use App\Models\RecepcionCompraModelo;
use App\Models\RechazoCompraModelo;
use App\Models\DevengacionCompraModelo;
use App\Models\OrdenRecepcionModelo;

class EGRE_ComprasListasController extends Controller{
  private function eachListaComprasGeneral($queryCompras,$JwtAuth){
    $idCompras = $listOrdenes->pluck('factura_compra')->filter()->unique()->toArray();
    $arrayCompras = array();
    foreach ($queryCompras as $vBuy) {
      date_default_timezone_set('UTC');
      $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
    
      $queryBuyProv = DB::table("eegr_compras AS buy")
      ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
      ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
      ->where(["buy.token_compras" => $vBuy->token_compras])
      ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
      ->first();
      $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
      $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
      $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
      $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';
    
      $totales_compra_subtotal = 0;
      $totales_compra_descuento = 0;
      $totales_compra_retenciones = 0;
      $totales_compra_traslados = 0;
      $totales_compra_importe = 0;
      $total_art_recibidos = 0;
    
      $queryDEtailsTotal = DB::table("eegr_compras AS buy")
      ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
      ->where("buy.token_compras",$vBuy->token_compras)
      ->get();
    
      foreach ($queryDEtailsTotal as $vDet) {
        $resultante = 0;
        $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
        $totales_compra_subtotal = $totales_compra_subtotal + $det_subtotal;
        $totales_compra_descuento = $totales_compra_descuento + $vDet->descuento;
        $totales_compra_retenciones = $totales_compra_retenciones + $vDet->retenciones_total;
        $totales_compra_traslados = $totales_compra_traslados + $vDet->traslados_total;
        $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
        $totales_compra_importe = $totales_compra_importe + $resultante;
        
        if($vDet->producto){
          $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
          ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
          ->where(["rec.producto" => $vDet->producto])->get();
          if (count($queryRecepcionPRD) > 0) {
            ++$total_art_recibidos;
          }
        }
        if($vDet->servicio){
          $queryRecepcionSERV = DB::table("eegr_compras_devengacion AS dev")
          ->join("eegr_compras_detalle AS detBuy", "dev.detalle_compra","=","detBuy.id")
          ->where(["dev.servicio" => $vDet->servicio])->get();
          //echo "count(queryRecepcionSERV) ".count($queryRecepcionSERV);
          if (count($queryRecepcionSERV) > 0) {
            ++$total_art_recibidos;
          }
        }
      }
  
      $queryUserCompra = DB::table("eegr_compras AS buy")
      ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
      ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
      ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
      ->where("buy.token_compras",$vBuy->token_compras)
      ->select('people.paterno','people.materno','people.nombre')
      ->first();
      $user_compra = $queryUserCompra ? $JwtAuth->desencriptarNombres($queryUserCompra->paterno, $queryUserCompra->materno, $queryUserCompra->nombre) : '';
    
      $queryUserAuth = DB::table("eegr_compras AS buy")
      ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
      ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
      ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
      ->where("buy.status_autorizacion",TRUE)
      ->where("buy.token_compras",$vBuy->token_compras)
      ->select('people.paterno','people.materno','people.nombre')
      ->first();
      $user_autoriza = $queryUserAuth ? $JwtAuth->desencriptarNombres($queryUserAuth->paterno, $queryUserAuth->materno, $queryUserAuth->nombre) : '';
          
      $queryCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")//cfdi__estructura
      ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
      ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
      ->where('buy.token_compras',$vBuy->token_compras)
      ->select(
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
      ->first();
      $cfdi_comprobante_version = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_version : "---"; 
      $cfdi_comprobante_serie = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_serie : "---"; 
      $cfdi_comprobante_folio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_folio : "---"; 
      $cfdi_comprobante_fecha = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_fecha : "---"; 
      $cfdi_comprobante_forma_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_forma_de_pago : "---"; 
      $cfdi_comprobante_metodo_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_metodo_de_pago : "---"; 
      $cfdi_comprobante_subtotal = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_subtotal : "---"; 
      $cfdi_comprobante_moneda = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_moneda : "---"; 
      $cfdi_comprobante_tipo_de_cambio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_cambio : "---"; 
      $cfdi_comprobante_total = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_total : "---"; 
      $cfdi_comprobante_confirmacion = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_confirmacion : "---"; 
      $cfdi_comprobante_tipo_de_comprobante = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_comprobante : "---"; 
      $cfdi_complementoFechaTimbrado = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoFechaTimbrado : "---"; 
      $cfdi_complementoUUID = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoUUID : "---"; 
  
      //Punto de entrega o recepción
      if (is_null($vBuy->recepcion_prov) &&	is_null($vBuy->recepcion_estab)) {
        $lugarRecepcionTipo = "N/A";
        $lugarRecepcionToken = "";
        $lugarRecepcionDireccion = "";
      } else {
        if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
          $listaDirEstab = DB::table("eegr_compras AS buy")
          ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
          ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
          ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->where(["catprov.token_cat_proveedores" => $proveedor_token])
          ->get();
          foreach ($listaDirEstab as $vUbica) {
            $lugarRecepcionTipo = "proveedor";
            $lugarRecepcionToken = $vUbica->token_direccion;
            $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
              $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
          }
        } else {
          $listaDirEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
          ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
          ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
          ->where("estab.status_establecimiento",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          foreach ($listaDirEstab as $vEstab) {
            $lugarRecepcionTipo = "Establecimiento";
            $lugarRecepcionToken = $vEstab->token_establecimiento;
            $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
          }
        }
      }
  
      $uuid_orden_recepcion = "";
      $folio_orden_recepcion = "";
      $bloqueo_orden_recepcion = false;
      $desbloqueo_fecha_orden_recepcion = "";
      $folio_orden_recepcion = "";
      $ordRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
      ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
      ->where("buy.token_compras",$vBuy->token_compras)
      ->get();
      //echo "ordPagoExists $ordPagoExists ";
      foreach ($ordRecepcionExists as $vOrdRec) {
        $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
        $bloqueo_orden_recepcion = $vOrdRec->orden_bloqueada ? true : false;
        $desbloqueo_fecha_orden_recepcion = !$vOrdRec->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdRec->fecha_desbloqueo) : '';
        $uuid_orden_recepcion = $vOrdRec->uuid_orden_recepcion;
      }
    
      $orden_pago_token = "---";
      $orden_pago_folio = "---";
      $orden_pago_fecha_contabilizacion = "---";
      $orden_pago_bloqueo = false;
      $orden_pago_desbloqueo_fecha = "";
      $pagos_realizados_folio = "---";
      $pagos_realizados_fecha_contabilizacion = "---";
      $pagos_realizados_total = 0;
      $ordPagoExists = DB::table("fnzs_pagos_orden AS orden")
      ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
      ->where("orden.status_ordenPago",TRUE)
      ->where("buy.token_compras",$vBuy->token_compras)
      ->get();
      //echo "ordPagoExists $ordPagoExists ";
      foreach ($ordPagoExists as $vOrdp) {
        $orden_pago_token = $vOrdp->token_ordenPago;
        $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
        $orden_pago_fecha_contabilizacion = !is_null($vOrdp->fecha_contabilizacion_ordenPago) && $vOrdp->fecha_contabilizacion_ordenPago != '' ? date('d-m-Y',$vOrdp->fecha_contabilizacion_ordenPago) : "---";
        $orden_pago_bloqueo = $vOrdp->orden_bloqueada ? true : false;
        $orden_pago_desbloqueo_fecha = !$vOrdp->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdp->fecha_desbloqueo) : '';
      
        $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
        foreach ($queryPagosDone as $vPayDone) {
          $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
          $pagos_realizados_fecha_contabilizacion = date('d-m-Y',$vPayDone->fecha_contabilizacion);
          $pagos_realizados_total += $vPayDone->orden_pago_monto;
        }
      }
      
      $arrayForeach = array(
        "token_compras" => $vBuy->token_compras,
        "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
        "fecha_contabilizacion" => date('d-m-Y', $vBuy->fecha_contabilizacion),
        //proveedor
        "proveedor_token" => $proveedor_token,
        "proveedor_folio" => $proveedor_folio,
        "proveedor_nombre" => $proveedor_nombre,
        "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
        //credito
        "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
        "fecha_vencimiento" => date('d-m-Y', $vBuy->fecha_vencimiento),
        //moneda
        "compra_moneda" => $vBuy->moneda,
        "compra_moneda_decimales" => $moneda_decimales,
        //importes
        "compra_subtotal" => "$" . number_format($totales_compra_subtotal, $moneda_decimales, '.', ','),
        "compra_descuento" => "$" . number_format($totales_compra_descuento, $moneda_decimales, '.', ','),
        "compra_retenciones" => "$" . number_format($totales_compra_retenciones, $moneda_decimales, '.', ','),
        "compra_traslados" => "$" . number_format($totales_compra_traslados, $moneda_decimales, '.', ','),
        "importe_total_compra" => "$" . number_format($totales_compra_importe, $moneda_decimales, '.', ','),
        //facturas
        "cfdi_reporte" => $vBuy->reporte,
        "cfdi_comprobante_version" => $cfdi_comprobante_version,
        "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
        "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
        "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
        "forma_pago" => $vBuy->forma_pago ? $vBuy->forma_pago : "---",
        "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
        "metodo_pago" => $vBuy->metodo_pago ? $vBuy->metodo_pago : "---",
        "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
        "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
        "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
        "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
        "cfdi_comprobante_total" => $cfdi_comprobante_total,
        "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
        "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
        "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
        "cfdi_complementoUUID" => $cfdi_complementoUUID,
        "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
        "recibeFactura" => $vBuy->recibeFactura ? true : false,
        "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
        "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
        "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
        "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
        "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
        "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
        "cfdi_documentos" => $vBuy->documentos,
        //recepcion
        "articulos_recibidos" => $total_art_recibidos,
        "total_articulos" => count($queryDEtailsTotal),
        "articulos_recibidos_comparativa" => "$total_art_recibidos / ".count($queryDEtailsTotal),
        "recepcionCollapsed" => true,
        "lugarRecepcionTipo" => $lugarRecepcionTipo,
        "lugarRecepcionToken" => $lugarRecepcionToken,
        "lugarRecepcionDireccion" => $lugarRecepcionDireccion,
        "lugarRecepcionComplete" => $lugarRecepcionTipo == 'N/A' ? $lugarRecepcionTipo : "$lugarRecepcionTipo $lugarRecepcionDireccion",
        "existe_orden_recepcion" => count($ordRecepcionExists) > 0 ? true : false,
        "folio_orden_recepcion" => $folio_orden_recepcion,
        "bloqueo_orden_recepcion" => $bloqueo_orden_recepcion,
        "desbloqueo_fecha_orden_recepcion" => $desbloqueo_fecha_orden_recepcion,
        "uuid_orden_recepcion" => $uuid_orden_recepcion,
        //orden de pago 
        "existe_orden_pago" => count($ordPagoExists) > 0 ? true : false,
        "token_orden_pago" => $orden_pago_token,
        "folio_orden_pago" => $orden_pago_folio,
        "fecha_contabilizacion_orden_pago" => $orden_pago_fecha_contabilizacion,
        "orden_pago_complete" => $orden_pago_folio == '---' ? '---' : "$orden_pago_folio $orden_pago_fecha_contabilizacion",
        "bloqueo_orden_pago" => $orden_pago_bloqueo,
        "desbloqueo_fecha_orden_pago" => $orden_pago_desbloqueo_fecha,
        "pagos_realizados_folio" => $pagos_realizados_folio,
        "pagos_realizados_fecha_contabilizacion" => $pagos_realizados_fecha_contabilizacion,
        "pagos_realizados_total" => $pagos_realizados_total,
        "pagos_realizados_complete" => $pagos_realizados_folio == '---' ? '---' : "$pagos_realizados_folio $pagos_realizados_fecha_contabilizacion",
        //autorizacion 
        "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
        "user_autoriza" => $user_autoriza,
        //periodicidad de compra
        "periodicidadCompra" => $vBuy->periodicidadCompra,
        "repeticionPeriodo" => $vBuy->repeticionPeriodo,
        "tipoPeriodo" => $vBuy->tipoPeriodo,
        "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
        "varImporte" => $vBuy->varImporte,
        //desglose
        "ver_seccion_compra" => false,
        "desglose_compra" => [],
        //otros
        "recepcionPago" => $vBuy->recepcionPago,
        "recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
        "usuario_comprador" => $user_compra,
      );
      $arrayCompras[] = $arrayForeach;
    }

    return $arrayCompras;
  }

  public function listaComprasGeneral(Request $request){
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
      
      $queryCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "eegr_compras.status_compra" => TRUE, 
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("eegr_compras.fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("eegr_compras.id","DESC")
      ->get();

      if ($queryCompras->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron compras registradas'
        );
      } else {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'compras' => $this->eachListaComprasGeneral($queryCompras,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listanoautorizadaCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where('eegr_compras.status_autorizacion',FALSE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();
        
        foreach ($listaCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
        
          $queryBuyProv = DB::table("eegr_compras AS buy")
          ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
          ->where(["buy.token_compras" => $vBuy->token_compras])
					->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
					->first();
          $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
          $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
          $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
          $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';
        
          $totales_compra_subtotal = 0;
          $totales_compra_descuento = 0;
          $totales_compra_retenciones = 0;
          $totales_compra_traslados = 0;
          $totales_compra_importe = 0;
          $total_art_recibidos = 0;
        
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
          ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
        
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
            $totales_compra_subtotal = $totales_compra_subtotal + $det_subtotal;
            $totales_compra_descuento = $totales_compra_descuento + $vDet->descuento;
            $totales_compra_retenciones = $totales_compra_retenciones + $vDet->retenciones_total;
            $totales_compra_traslados = $totales_compra_traslados + $vDet->traslados_total;
            $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
            $totales_compra_importe = $totales_compra_importe + $resultante;
            
            if($vDet->producto){
              $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
              ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
              ->where(["rec.producto" => $vDet->producto])->get();
              if (count($queryRecepcionPRD) > 0) {
                ++$total_art_recibidos;
              }
            }
            if($vDet->servicio){
              $queryRecepcionSERV = DB::table("eegr_compras_devengacion AS dev")
              ->join("eegr_compras_detalle AS detBuy", "dev.detalle_compra","=","detBuy.id")
              ->where(["dev.servicio" => $vDet->servicio])->get();
              //echo "count(queryRecepcionSERV) ".count($queryRecepcionSERV);
              if (count($queryRecepcionSERV) > 0) {
                ++$total_art_recibidos;
              }
            }
          }

          $queryUserCompra = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_compra = $queryUserCompra ? $JwtAuth->desencriptarNombres($queryUserCompra->paterno, $queryUserCompra->materno, $queryUserCompra->nombre) : '';
        
          $queryUserAuth = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.status_autorizacion",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_autoriza = $queryUserAuth ? $JwtAuth->desencriptarNombres($queryUserAuth->paterno, $queryUserAuth->materno, $queryUserAuth->nombre) : '';
              
          $queryCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")//cfdi__estructura
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
          ->where('buy.token_compras',$vBuy->token_compras)
					->select(
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
					->first();
          $cfdi_comprobante_version = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_version : "---"; 
          $cfdi_comprobante_serie = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_serie : "---"; 
          $cfdi_comprobante_folio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_folio : "---"; 
          $cfdi_comprobante_fecha = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_fecha : "---"; 
          $cfdi_comprobante_forma_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_forma_de_pago : "---"; 
          $cfdi_comprobante_metodo_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_metodo_de_pago : "---"; 
          $cfdi_comprobante_subtotal = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_subtotal : "---"; 
          $cfdi_comprobante_moneda = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_moneda : "---"; 
          $cfdi_comprobante_tipo_de_cambio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_cambio : "---"; 
          $cfdi_comprobante_total = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_total : "---"; 
          $cfdi_comprobante_confirmacion = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_confirmacion : "---"; 
          $cfdi_comprobante_tipo_de_comprobante = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_comprobante : "---"; 
          $cfdi_complementoFechaTimbrado = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoFechaTimbrado : "---"; 
          $cfdi_complementoUUID = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoUUID : "---"; 

          //Punto de entrega o recepción
          if (is_null($vBuy->recepcion_prov) &&	is_null($vBuy->recepcion_estab)) {
            $lugarRecepcionTipo = "N/A";
            $lugarRecepcionToken = "";
            $lugarRecepcionDireccion = "";
          } else {
            if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
              $listaDirEstab = DB::table("eegr_compras AS buy")
              ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
              ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
              ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
              ->where("buy.token_compras",$vBuy->token_compras)
              ->where(["catprov.token_cat_proveedores" => $proveedor_token])
              ->get();
              foreach ($listaDirEstab as $vUbica) {
                $lugarRecepcionTipo = "proveedor";
                $lugarRecepcionToken = $vUbica->token_direccion;
                $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
                  $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
              }
            } else {
              $listaDirEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where("estab.status_establecimiento",TRUE)
              ->where("buy.token_compras",$vBuy->token_compras)
              ->get();
              foreach ($listaDirEstab as $vEstab) {
                $lugarRecepcionTipo = "Establecimiento";
                $lugarRecepcionToken = $vEstab->token_establecimiento;
                $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
              }
            }
          }

          $uuid_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $bloqueo_orden_recepcion = false;
          $desbloqueo_fecha_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $ordRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
          ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordRecepcionExists as $vOrdRec) {
            $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
            $bloqueo_orden_recepcion = $vOrdRec->orden_bloqueada ? true : false;
            $desbloqueo_fecha_orden_recepcion = !$vOrdRec->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdRec->fecha_desbloqueo) : '';
            $uuid_orden_recepcion = $vOrdRec->uuid_orden_recepcion;
          }
        
          $orden_pago_token = "---";
          $orden_pago_folio = "---";
          $orden_pago_fecha_contabilizacion = "---";
          $orden_pago_bloqueo = false;
          $orden_pago_desbloqueo_fecha = "";
          $pagos_realizados_folio = "---";
          $pagos_realizados_fecha_contabilizacion = "---";
          $pagos_realizados_total = 0;
          $ordPagoExists = DB::table("fnzs_pagos_orden AS orden")
          ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
          ->where("orden.status_ordenPago",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordPagoExists as $vOrdp) {
            $orden_pago_token = $vOrdp->token_ordenPago;
            $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
            $orden_pago_fecha_contabilizacion = date('d-m-Y',$vOrdp->fecha_contabilizacion_ordenPago);
            $orden_pago_bloqueo = $vOrdp->orden_bloqueada ? true : false;
            $orden_pago_desbloqueo_fecha = !$vOrdp->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdp->fecha_desbloqueo) : '';
          
            $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
            foreach ($queryPagosDone as $vPayDone) {
              $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
              $pagos_realizados_fecha_contabilizacion = date('d-m-Y',$vPayDone->fecha_contabilizacion);
              $pagos_realizados_total += $vPayDone->orden_pago_monto;
            }
          }
          
          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_contabilizacion" => date('d-m-Y', $vBuy->fecha_contabilizacion),
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
            //credito
            "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
            "fecha_vencimiento" => date('d-m-Y', $vBuy->fecha_vencimiento),
            //moneda
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //importes
            "compra_subtotal" => "$" . number_format($totales_compra_subtotal, $moneda_decimales, '.', ','),
            "compra_descuento" => "$" . number_format($totales_compra_descuento, $moneda_decimales, '.', ','),
            "compra_retenciones" => "$" . number_format($totales_compra_retenciones, $moneda_decimales, '.', ','),
            "compra_traslados" => "$" . number_format($totales_compra_traslados, $moneda_decimales, '.', ','),
            "importe_total_compra" => "$" . number_format($totales_compra_importe, $moneda_decimales, '.', ','),
            //facturas
            "cfdi_reporte" => $vBuy->reporte,
            "cfdi_comprobante_version" => $cfdi_comprobante_version,
            "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
            "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
            "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
            "forma_pago" => $vBuy->forma_pago ? $vBuy->forma_pago : "---",
            "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
            "metodo_pago" => $vBuy->metodo_pago ? $vBuy->metodo_pago : "---",
            "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
            "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
            "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
            "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
            "cfdi_comprobante_total" => $cfdi_comprobante_total,
            "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
            "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
            "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
            "cfdi_complementoUUID" => $cfdi_complementoUUID,
            "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "cfdi_documentos" => $vBuy->documentos,
            //recepcion
            "articulos_recibidos" => $total_art_recibidos,
            "total_articulos" => count($queryDEtailsTotal),
            "recepcionCollapsed" => true,
            "lugarRecepcionTipo" => $lugarRecepcionTipo,
            "lugarRecepcionToken" => $lugarRecepcionToken,
            "lugarRecepcionDireccion" => $lugarRecepcionDireccion,
            "existe_orden_recepcion" => count($ordRecepcionExists) > 0 ? true : false,
            "folio_orden_recepcion" => $folio_orden_recepcion,
            "bloqueo_orden_recepcion" => $bloqueo_orden_recepcion,
            "desbloqueo_fecha_orden_recepcion" => $desbloqueo_fecha_orden_recepcion,
            "uuid_orden_recepcion" => $uuid_orden_recepcion,
            //orden de pago 
            "existe_orden_pago" => count($ordPagoExists) > 0 ? true : false,
            "token_orden_pago" => $orden_pago_token,
            "folio_orden_pago" => $orden_pago_folio,
            "fecha_contabilizacion_orden_pago" => $orden_pago_fecha_contabilizacion,
            "bloqueo_orden_pago" => $orden_pago_bloqueo,
            "desbloqueo_fecha_orden_pago" => $orden_pago_desbloqueo_fecha,
            "pagos_realizados_folio" => $pagos_realizados_folio,
            "pagos_realizados_fecha_contabilizacion" => $pagos_realizados_fecha_contabilizacion,
            "pagos_realizados_total" => $pagos_realizados_total,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //periodicidad de compra
            "periodicidadCompra" => $vBuy->periodicidadCompra,
            "repeticionPeriodo" => $vBuy->repeticionPeriodo,
            "tipoPeriodo" => $vBuy->tipoPeriodo,
            "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
            "varImporte" => $vBuy->varImporte,
            //desglose
            "ver_seccion_compra" => false,
            "desglose_compra" => [],
            //otros
            "recepcionPago" => $vBuy->recepcionPago,
            "recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
            "usuario_comprador" => $user_compra,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function listaComprasAutorizadas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereIn('eegr_compras.id', function ($query) {
          $query->select('numero_compra')->from('eegr_compras_detalle');
        })
        ->where('eegr_compras.status_autorizacion',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($listaCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
        
          $queryBuyProv = DB::table("eegr_compras AS buy")
          ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
          ->where(["buy.token_compras" => $vBuy->token_compras])
					->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
					->first();
          $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
          $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
          $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
          $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';
        
          $totales_compra_subtotal = 0;
          $totales_compra_descuento = 0;
          $totales_compra_retenciones = 0;
          $totales_compra_traslados = 0;
          $totales_compra_importe = 0;
          $total_art_recibidos = 0;
        
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
          ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
        
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
            $totales_compra_subtotal = $totales_compra_subtotal + $det_subtotal;
            $totales_compra_descuento = $totales_compra_descuento + $vDet->descuento;
            $totales_compra_retenciones = $totales_compra_retenciones + $vDet->retenciones_total;
            $totales_compra_traslados = $totales_compra_traslados + $vDet->traslados_total;
            $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
            $totales_compra_importe = $totales_compra_importe + $resultante;
            
            if($vDet->producto){
              $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
              ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
              ->where(["rec.producto" => $vDet->producto])->get();
              if (count($queryRecepcionPRD) > 0) {
                ++$total_art_recibidos;
              }
            }
            if($vDet->servicio){
              $queryRecepcionSERV = DB::table("eegr_compras_devengacion AS dev")
              ->join("eegr_compras_detalle AS detBuy", "dev.detalle_compra","=","detBuy.id")
              ->where(["dev.servicio" => $vDet->servicio])->get();
              //echo "count(queryRecepcionSERV) ".count($queryRecepcionSERV);
              if (count($queryRecepcionSERV) > 0) {
                ++$total_art_recibidos;
              }
            }
          }

          $queryUserCompra = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_compra = $queryUserCompra ? $JwtAuth->desencriptarNombres($queryUserCompra->paterno, $queryUserCompra->materno, $queryUserCompra->nombre) : '';
        
          $queryUserAuth = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.status_autorizacion",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_autoriza = $queryUserAuth ? $JwtAuth->desencriptarNombres($queryUserAuth->paterno, $queryUserAuth->materno, $queryUserAuth->nombre) : '';
              
          $queryCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")//cfdi__estructura
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
          ->where('buy.token_compras',$vBuy->token_compras)
					->select(
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
					->first();
          $cfdi_comprobante_version = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_version : "---"; 
          $cfdi_comprobante_serie = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_serie : "---"; 
          $cfdi_comprobante_folio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_folio : "---"; 
          $cfdi_comprobante_fecha = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_fecha : "---"; 
          $cfdi_comprobante_forma_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_forma_de_pago : "---"; 
          $cfdi_comprobante_metodo_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_metodo_de_pago : "---"; 
          $cfdi_comprobante_subtotal = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_subtotal : "---"; 
          $cfdi_comprobante_moneda = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_moneda : "---"; 
          $cfdi_comprobante_tipo_de_cambio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_cambio : "---"; 
          $cfdi_comprobante_total = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_total : "---"; 
          $cfdi_comprobante_confirmacion = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_confirmacion : "---"; 
          $cfdi_comprobante_tipo_de_comprobante = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_comprobante : "---"; 
          $cfdi_complementoFechaTimbrado = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoFechaTimbrado : "---"; 
          $cfdi_complementoUUID = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoUUID : "---"; 

          //Punto de entrega o recepción
          if (is_null($vBuy->recepcion_prov) &&	is_null($vBuy->recepcion_estab)) {
            $lugarRecepcionTipo = "N/A";
            $lugarRecepcionToken = "";
            $lugarRecepcionDireccion = "";
          } else {
            if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
              $listaDirEstab = DB::table("eegr_compras AS buy")
              ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
              ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
              ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
              ->where("buy.token_compras",$vBuy->token_compras)
              ->where(["catprov.token_cat_proveedores" => $proveedor_token])
              ->get();
              foreach ($listaDirEstab as $vUbica) {
                $lugarRecepcionTipo = "proveedor";
                $lugarRecepcionToken = $vUbica->token_direccion;
                $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
                  $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
              }
            } else {
              $listaDirEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where("estab.status_establecimiento",TRUE)
              ->where("buy.token_compras",$vBuy->token_compras)
              ->get();
              foreach ($listaDirEstab as $vEstab) {
                $lugarRecepcionTipo = "Establecimiento";
                $lugarRecepcionToken = $vEstab->token_establecimiento;
                $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
              }
            }
          }

          $uuid_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $bloqueo_orden_recepcion = false;
          $desbloqueo_fecha_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $ordRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
          ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordRecepcionExists as $vOrdRec) {
            $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
            $bloqueo_orden_recepcion = $vOrdRec->orden_bloqueada ? true : false;
            $desbloqueo_fecha_orden_recepcion = !$vOrdRec->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdRec->fecha_desbloqueo) : '';
            $uuid_orden_recepcion = $vOrdRec->uuid_orden_recepcion;
          }
        
          $orden_pago_token = "---";
          $orden_pago_folio = "---";
          $orden_pago_fecha_contabilizacion = "---";
          $orden_pago_bloqueo = false;
          $orden_pago_desbloqueo_fecha = "";
          $pagos_realizados_folio = "---";
          $pagos_realizados_fecha_contabilizacion = "---";
          $pagos_realizados_total = 0;
          $ordPagoExists = DB::table("fnzs_pagos_orden AS orden")
          ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
          ->where("orden.status_ordenPago",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordPagoExists as $vOrdp) {
            $orden_pago_token = $vOrdp->token_ordenPago;
            $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
            $orden_pago_fecha_contabilizacion = date('d-m-Y',$vOrdp->fecha_contabilizacion_ordenPago);
            $orden_pago_bloqueo = $vOrdp->orden_bloqueada ? true : false;
            $orden_pago_desbloqueo_fecha = !$vOrdp->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdp->fecha_desbloqueo) : '';
          
            $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
            foreach ($queryPagosDone as $vPayDone) {
              $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
              $pagos_realizados_fecha_contabilizacion = date('d-m-Y',$vPayDone->fecha_contabilizacion);
              $pagos_realizados_total += $vPayDone->orden_pago_monto;
            }
          }
          
          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_contabilizacion" => date('d-m-Y', $vBuy->fecha_contabilizacion),
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
            //credito
            "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
            "fecha_vencimiento" => date('d-m-Y', $vBuy->fecha_vencimiento),
            //moneda
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //importes
            "compra_subtotal" => "$" . number_format($totales_compra_subtotal, $moneda_decimales, '.', ','),
            "compra_descuento" => "$" . number_format($totales_compra_descuento, $moneda_decimales, '.', ','),
            "compra_retenciones" => "$" . number_format($totales_compra_retenciones, $moneda_decimales, '.', ','),
            "compra_traslados" => "$" . number_format($totales_compra_traslados, $moneda_decimales, '.', ','),
            "importe_total_compra" => "$" . number_format($totales_compra_importe, $moneda_decimales, '.', ','),
            //facturas
            "cfdi_reporte" => $vBuy->reporte,
            "cfdi_comprobante_version" => $cfdi_comprobante_version,
            "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
            "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
            "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
            "forma_pago" => $vBuy->forma_pago ? $vBuy->forma_pago : "---",
            "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
            "metodo_pago" => $vBuy->metodo_pago ? $vBuy->metodo_pago : "---",
            "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
            "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
            "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
            "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
            "cfdi_comprobante_total" => $cfdi_comprobante_total,
            "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
            "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
            "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
            "cfdi_complementoUUID" => $cfdi_complementoUUID,
            "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "cfdi_documentos" => $vBuy->documentos,
            //recepcion
            "articulos_recibidos" => $total_art_recibidos,
            "total_articulos" => count($queryDEtailsTotal),
            "recepcionCollapsed" => true,
            "lugarRecepcionTipo" => $lugarRecepcionTipo,
            "lugarRecepcionToken" => $lugarRecepcionToken,
            "lugarRecepcionDireccion" => $lugarRecepcionDireccion,
            "existe_orden_recepcion" => count($ordRecepcionExists) > 0 ? true : false,
            "folio_orden_recepcion" => $folio_orden_recepcion,
            "bloqueo_orden_recepcion" => $bloqueo_orden_recepcion,
            "desbloqueo_fecha_orden_recepcion" => $desbloqueo_fecha_orden_recepcion,
            "uuid_orden_recepcion" => $uuid_orden_recepcion,
            //orden de pago 
            "existe_orden_pago" => count($ordPagoExists) > 0 ? true : false,
            "token_orden_pago" => $orden_pago_token,
            "folio_orden_pago" => $orden_pago_folio,
            "fecha_contabilizacion_orden_pago" => $orden_pago_fecha_contabilizacion,
            "bloqueo_orden_pago" => $orden_pago_bloqueo,
            "desbloqueo_fecha_orden_pago" => $orden_pago_desbloqueo_fecha,
            "pagos_realizados_folio" => $pagos_realizados_folio,
            "pagos_realizados_fecha_contabilizacion" => $pagos_realizados_fecha_contabilizacion,
            "pagos_realizados_total" => $pagos_realizados_total,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //periodicidad de compra
            "periodicidadCompra" => $vBuy->periodicidadCompra,
            "repeticionPeriodo" => $vBuy->repeticionPeriodo,
            "tipoPeriodo" => $vBuy->tipoPeriodo,
            "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
            "varImporte" => $vBuy->varImporte,
            //desglose
            "ver_seccion_compra" => false,
            "desglose_compra" => [],
            //otros
            "recepcionPago" => $vBuy->recepcionPago,
            "recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
            "usuario_comprador" => $user_compra,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaComprasPagadas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $queryCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereIn('eegr_compras.id', function ($query) {
          $query->select('numero_compra')->from('eegr_compras_detalle');
        })
        ->whereIn('eegr_compras.id', function ($query) {
          $query->select('factura_compra')->from('fnzs_pagos_orden')->where("orden_terminada_bool",TRUE);
        })
        ->where('eegr_compras.status_autorizacion',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($queryCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
        
          $queryBuyProv = DB::table("eegr_compras AS buy")
          ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
          ->where(["buy.token_compras" => $vBuy->token_compras])
					->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
					->first();
          $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
          $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
          $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
          $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';
        
          $totales_compra_subtotal = 0;
          $totales_compra_descuento = 0;
          $totales_compra_retenciones = 0;
          $totales_compra_traslados = 0;
          $totales_compra_importe = 0;
          $total_art_recibidos = 0;
        
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
          ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
        
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
            $totales_compra_subtotal = $totales_compra_subtotal + $det_subtotal;
            $totales_compra_descuento = $totales_compra_descuento + $vDet->descuento;
            $totales_compra_retenciones = $totales_compra_retenciones + $vDet->retenciones_total;
            $totales_compra_traslados = $totales_compra_traslados + $vDet->traslados_total;
            $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
            $totales_compra_importe = $totales_compra_importe + $resultante;
            
            if($vDet->producto){
              $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
              ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
              ->where(["rec.producto" => $vDet->producto])->get();
              if (count($queryRecepcionPRD) > 0) {
                ++$total_art_recibidos;
              }
            }
            if($vDet->servicio){
              $queryRecepcionSERV = DB::table("eegr_compras_devengacion AS dev")
              ->join("eegr_compras_detalle AS detBuy", "dev.detalle_compra","=","detBuy.id")
              ->where(["dev.servicio" => $vDet->servicio])->get();
              //echo "count(queryRecepcionSERV) ".count($queryRecepcionSERV);
              if (count($queryRecepcionSERV) > 0) {
                ++$total_art_recibidos;
              }
            }
          }

          $queryUserCompra = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_compra = $queryUserCompra ? $JwtAuth->desencriptarNombres($queryUserCompra->paterno, $queryUserCompra->materno, $queryUserCompra->nombre) : '';
        
          $queryUserAuth = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.status_autorizacion",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_autoriza = $queryUserAuth ? $JwtAuth->desencriptarNombres($queryUserAuth->paterno, $queryUserAuth->materno, $queryUserAuth->nombre) : '';
              
          $queryCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")//cfdi__estructura
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
          ->where('buy.token_compras',$vBuy->token_compras)
					->select(
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
					->first();
          $cfdi_comprobante_version = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_version : "---"; 
          $cfdi_comprobante_serie = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_serie : "---"; 
          $cfdi_comprobante_folio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_folio : "---"; 
          $cfdi_comprobante_fecha = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_fecha : "---"; 
          $cfdi_comprobante_forma_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_forma_de_pago : "---"; 
          $cfdi_comprobante_metodo_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_metodo_de_pago : "---"; 
          $cfdi_comprobante_subtotal = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_subtotal : "---"; 
          $cfdi_comprobante_moneda = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_moneda : "---"; 
          $cfdi_comprobante_tipo_de_cambio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_cambio : "---"; 
          $cfdi_comprobante_total = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_total : "---"; 
          $cfdi_comprobante_confirmacion = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_confirmacion : "---"; 
          $cfdi_comprobante_tipo_de_comprobante = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_comprobante : "---"; 
          $cfdi_complementoFechaTimbrado = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoFechaTimbrado : "---"; 
          $cfdi_complementoUUID = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoUUID : "---"; 

          //Punto de entrega o recepción
          if (is_null($vBuy->recepcion_prov) &&	is_null($vBuy->recepcion_estab)) {
            $lugarRecepcionTipo = "N/A";
            $lugarRecepcionToken = "";
            $lugarRecepcionDireccion = "";
          } else {
            if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
              $listaDirEstab = DB::table("eegr_compras AS buy")
              ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
              ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
              ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
              ->where("buy.token_compras",$vBuy->token_compras)
              ->where(["catprov.token_cat_proveedores" => $proveedor_token])
              ->get();
              foreach ($listaDirEstab as $vUbica) {
                $lugarRecepcionTipo = "proveedor";
                $lugarRecepcionToken = $vUbica->token_direccion;
                $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
                  $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
              }
            } else {
              $listaDirEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where("estab.status_establecimiento",TRUE)
              ->where("buy.token_compras",$vBuy->token_compras)
              ->get();
              foreach ($listaDirEstab as $vEstab) {
                $lugarRecepcionTipo = "Establecimiento";
                $lugarRecepcionToken = $vEstab->token_establecimiento;
                $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
              }
            }
          }

          $uuid_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $bloqueo_orden_recepcion = false;
          $desbloqueo_fecha_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $ordRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
          ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordRecepcionExists as $vOrdRec) {
            $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
            $bloqueo_orden_recepcion = $vOrdRec->orden_bloqueada ? true : false;
            $desbloqueo_fecha_orden_recepcion = !$vOrdRec->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdRec->fecha_desbloqueo) : '';
            $uuid_orden_recepcion = $vOrdRec->uuid_orden_recepcion;
          }
        
          $orden_pago_token = "---";
          $orden_pago_folio = "---";
          $orden_pago_fecha_contabilizacion = "---";
          $orden_pago_bloqueo = false;
          $orden_pago_desbloqueo_fecha = "";
          $pagos_realizados_folio = "---";
          $pagos_realizados_fecha_contabilizacion = "---";
          $pagos_realizados_total = 0;
          $ordPagoExists = DB::table("fnzs_pagos_orden AS orden")
          ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
          ->where("orden.status_ordenPago",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordPagoExists as $vOrdp) {
            $orden_pago_token = $vOrdp->token_ordenPago;
            $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
            $orden_pago_fecha_contabilizacion = date('d-m-Y',$vOrdp->fecha_contabilizacion_ordenPago);
            $orden_pago_bloqueo = $vOrdp->orden_bloqueada ? true : false;
            $orden_pago_desbloqueo_fecha = !$vOrdp->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdp->fecha_desbloqueo) : '';
          
            $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
            foreach ($queryPagosDone as $vPayDone) {
              $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
              $pagos_realizados_fecha_contabilizacion = date('d-m-Y',$vPayDone->fecha_contabilizacion);
              $pagos_realizados_total += $vPayDone->orden_pago_monto;
            }
          }
          
          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_contabilizacion" => date('d-m-Y', $vBuy->fecha_contabilizacion),
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
            //credito
            "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
            "fecha_vencimiento" => date('d-m-Y', $vBuy->fecha_vencimiento),
            //moneda
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //importes
            "compra_subtotal" => "$" . number_format($totales_compra_subtotal, $moneda_decimales, '.', ','),
            "compra_descuento" => "$" . number_format($totales_compra_descuento, $moneda_decimales, '.', ','),
            "compra_retenciones" => "$" . number_format($totales_compra_retenciones, $moneda_decimales, '.', ','),
            "compra_traslados" => "$" . number_format($totales_compra_traslados, $moneda_decimales, '.', ','),
            "importe_total_compra" => "$" . number_format($totales_compra_importe, $moneda_decimales, '.', ','),
            //facturas
            "cfdi_reporte" => $vBuy->reporte,
            "cfdi_comprobante_version" => $cfdi_comprobante_version,
            "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
            "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
            "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
            "forma_pago" => $vBuy->forma_pago ? $vBuy->forma_pago : "---",
            "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
            "metodo_pago" => $vBuy->metodo_pago ? $vBuy->metodo_pago : "---",
            "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
            "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
            "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
            "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
            "cfdi_comprobante_total" => $cfdi_comprobante_total,
            "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
            "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
            "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
            "cfdi_complementoUUID" => $cfdi_complementoUUID,
            "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "cfdi_documentos" => $vBuy->documentos,
            //recepcion
            "articulos_recibidos" => $total_art_recibidos,
            "total_articulos" => count($queryDEtailsTotal),
            "recepcionCollapsed" => true,
            "lugarRecepcionTipo" => $lugarRecepcionTipo,
            "lugarRecepcionToken" => $lugarRecepcionToken,
            "lugarRecepcionDireccion" => $lugarRecepcionDireccion,
            "existe_orden_recepcion" => count($ordRecepcionExists) > 0 ? true : false,
            "folio_orden_recepcion" => $folio_orden_recepcion,
            "bloqueo_orden_recepcion" => $bloqueo_orden_recepcion,
            "desbloqueo_fecha_orden_recepcion" => $desbloqueo_fecha_orden_recepcion,
            "uuid_orden_recepcion" => $uuid_orden_recepcion,
            //orden de pago 
            "existe_orden_pago" => count($ordPagoExists) > 0 ? true : false,
            "token_orden_pago" => $orden_pago_token,
            "folio_orden_pago" => $orden_pago_folio,
            "fecha_contabilizacion_orden_pago" => $orden_pago_fecha_contabilizacion,
            "bloqueo_orden_pago" => $orden_pago_bloqueo,
            "desbloqueo_fecha_orden_pago" => $orden_pago_desbloqueo_fecha,
            "pagos_realizados_folio" => $pagos_realizados_folio,
            "pagos_realizados_fecha_contabilizacion" => $pagos_realizados_fecha_contabilizacion,
            "pagos_realizados_total" => $pagos_realizados_total,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //periodicidad de compra
            "periodicidadCompra" => $vBuy->periodicidadCompra,
            "repeticionPeriodo" => $vBuy->repeticionPeriodo,
            "tipoPeriodo" => $vBuy->tipoPeriodo,
            "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
            "varImporte" => $vBuy->varImporte,
            //desglose
            "ver_seccion_compra" => false,
            "desglose_compra" => [],
            //otros
            "recepcionPago" => $vBuy->recepcionPago,
            "recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
            "usuario_comprador" => $user_compra,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //recepcion de facturas
  public function listaComprasRecibeFacturaDespues(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $queryCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereNotIn('eegr_compras.id', function($queryCFDI) {
          $queryCFDI->select('compra_vinculada')->from('cfdi_vinculacion_compras');//cfdi__estructura cfdi_comprobantes_fiscales
        })
        ->where([
          "eegr_compras.status_compra" => TRUE, 
          "emp.empresa_token" => $usuario->empresa_token, 
          "users.usuario_token" => $usuario->user_token
        ])->get();
        
        foreach ($queryCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
        
          $queryBuyProv = DB::table("eegr_compras AS buy")
          ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
          ->where(["buy.token_compras" => $vBuy->token_compras])
					->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					->first();
          $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
          $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
          $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
        
          $totales_compra_subtotal = 0;
          $totales_compra_descuento = 0;
          $totales_compra_retenciones = 0;
          $totales_compra_traslados = 0;
          $totales_compra_importe = 0;
          $total_art_recibidos = 0;
        
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
          ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
        
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
            $totales_compra_subtotal = $totales_compra_subtotal + $det_subtotal;
            $totales_compra_descuento = $totales_compra_descuento + $vDet->descuento;
            $totales_compra_retenciones = $totales_compra_retenciones + $vDet->retenciones_total;
            $totales_compra_traslados = $totales_compra_traslados + $vDet->traslados_total;
            $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
            $totales_compra_importe = $totales_compra_importe + $resultante;
            
            if($vDet->producto){
              $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
              ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
              ->where(["rec.producto" => $vDet->producto])->get();
              if (count($queryRecepcionPRD) > 0) {
                ++$total_art_recibidos;
              }
            }
            if($vDet->servicio){
              $queryRecepcionSERV = DB::table("eegr_compras_devengacion AS dev")
              ->join("eegr_compras_detalle AS detBuy", "dev.detalle_compra","=","detBuy.id")
              ->where(["dev.servicio" => $vDet->servicio])->get();
              //echo "count(queryRecepcionSERV) ".count($queryRecepcionSERV);
              if (count($queryRecepcionSERV) > 0) {
                ++$total_art_recibidos;
              }
            }
          }

          $queryUserCompra = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_compra = $queryUserCompra ? $JwtAuth->desencriptarNombres($queryUserCompra->paterno, $queryUserCompra->materno, $queryUserCompra->nombre) : '';
        
          $queryUserAuth = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where("buy.status_autorizacion",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
					->select('people.paterno','people.materno','people.nombre')
					->first();
          $user_autoriza = $queryUserAuth ? $JwtAuth->desencriptarNombres($queryUserAuth->paterno, $queryUserAuth->materno, $queryUserAuth->nombre) : '';
              
          $queryCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")//cfdi__estructura
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
          ->where('buy.token_compras',$vBuy->token_compras)
					->select(
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
					->first();
          $cfdi_comprobante_version = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_version : "---"; 
          $cfdi_comprobante_serie = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_serie : "---"; 
          $cfdi_comprobante_folio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_folio : "---"; 
          $cfdi_comprobante_fecha = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_fecha : "---"; 
          $cfdi_comprobante_forma_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_forma_de_pago : "---"; 
          $cfdi_comprobante_metodo_de_pago = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_metodo_de_pago : "---"; 
          $cfdi_comprobante_subtotal = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_subtotal : "---"; 
          $cfdi_comprobante_moneda = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_moneda : "---"; 
          $cfdi_comprobante_tipo_de_cambio = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_cambio : "---"; 
          $cfdi_comprobante_total = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_total : "---"; 
          $cfdi_comprobante_confirmacion = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_confirmacion : "---"; 
          $cfdi_comprobante_tipo_de_comprobante = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_comprobante_tipo_de_comprobante : "---"; 
          $cfdi_complementoFechaTimbrado = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoFechaTimbrado : "---"; 
          $cfdi_complementoUUID = $queryCFDIEstructura ? $queryCFDIEstructura->cfdi_complementoUUID : "---"; 

          //Punto de entrega o recepción
          if (is_null($vBuy->recepcion_prov) &&	is_null($vBuy->recepcion_estab)) {
            $lugarRecepcionTipo = "N/A";
            $lugarRecepcionToken = "";
            $lugarRecepcionDireccion = "";
          } else {
            if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
              $listaDirEstab = DB::table("eegr_compras AS buy")
              ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
              ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
              ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
              ->where("buy.token_compras",$vBuy->token_compras)
              ->where(["catprov.token_cat_proveedores" => $proveedor_token])
              ->get();
              foreach ($listaDirEstab as $vUbica) {
                $lugarRecepcionTipo = "proveedor";
                $lugarRecepcionToken = $vUbica->token_direccion;
                $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
                  $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
              }
            } else {
              $listaDirEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where("estab.status_establecimiento",TRUE)
              ->where("buy.token_compras",$vBuy->token_compras)
              ->get();
              foreach ($listaDirEstab as $vEstab) {
                $lugarRecepcionTipo = "Establecimiento";
                $lugarRecepcionToken = $vEstab->token_establecimiento;
                $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
              }
            }
          }

          $uuid_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $bloqueo_orden_recepcion = false;
          $desbloqueo_fecha_orden_recepcion = "";
          $folio_orden_recepcion = "";
          $ordRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
          ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordRecepcionExists as $vOrdRec) {
            $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
            $bloqueo_orden_recepcion = $vOrdRec->orden_bloqueada ? true : false;
            $desbloqueo_fecha_orden_recepcion = !$vOrdRec->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdRec->fecha_desbloqueo) : '';
            $uuid_orden_recepcion = $vOrdRec->uuid_orden_recepcion;
          }
        
          $orden_pago_token = "---";
          $orden_pago_folio = "---";
          $orden_pago_fecha_contabilizacion = "---";
          $orden_pago_bloqueo = false;
          $orden_pago_desbloqueo_fecha = "";
          $pagos_realizados_folio = "---";
          $pagos_realizados_fecha_contabilizacion = "---";
          $pagos_realizados_total = 0;
          $ordPagoExists = DB::table("fnzs_pagos_orden AS orden")
          ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
          ->where("orden.status_ordenPago",TRUE)
          ->where("buy.token_compras",$vBuy->token_compras)
          ->get();
          //echo "ordPagoExists $ordPagoExists ";
          foreach ($ordPagoExists as $vOrdp) {
            $orden_pago_token = $vOrdp->token_ordenPago;
            $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
            $orden_pago_fecha_contabilizacion = date('d-m-Y',$vOrdp->fecha_contabilizacion_ordenPago);
            $orden_pago_bloqueo = $vOrdp->orden_bloqueada ? true : false;
            $orden_pago_desbloqueo_fecha = !$vOrdp->orden_bloqueada ? date('d-m-Y H:i:s', $vOrdp->fecha_desbloqueo) : '';
          
            $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
            foreach ($queryPagosDone as $vPayDone) {
              $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
              $pagos_realizados_fecha_contabilizacion = date('d-m-Y',$vPayDone->fecha_contabilizacion);
              $pagos_realizados_total += $vPayDone->orden_pago_monto;
            }
          }
          
          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_contabilizacion" => date('d-m-Y', $vBuy->fecha_contabilizacion),
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            //credito
            "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
            "fecha_vencimiento" => date('d-m-Y', $vBuy->fecha_vencimiento),
            //moneda
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //importes
            "compra_subtotal" => "$" . number_format($totales_compra_subtotal, $moneda_decimales, '.', ','),
            "compra_descuento" => "$" . number_format($totales_compra_descuento, $moneda_decimales, '.', ','),
            "compra_retenciones" => "$" . number_format($totales_compra_retenciones, $moneda_decimales, '.', ','),
            "compra_traslados" => "$" . number_format($totales_compra_traslados, $moneda_decimales, '.', ','),
            "importe_total_compra" => "$" . number_format($totales_compra_importe, $moneda_decimales, '.', ','),
            //facturas
            "cfdi_reporte" => $vBuy->reporte,
            "cfdi_comprobante_version" => $cfdi_comprobante_version,
            "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
            "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
            "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
            "forma_pago" => $vBuy->forma_pago ? $vBuy->forma_pago : "---",
            "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
            "metodo_pago" => $vBuy->metodo_pago ? $vBuy->metodo_pago : "---",
            "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
            "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
            "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
            "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
            "cfdi_comprobante_total" => $cfdi_comprobante_total,
            "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
            "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
            "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
            "cfdi_complementoUUID" => $cfdi_complementoUUID,
            "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "cfdi_documentos" => $vBuy->documentos,
            //recepcion
            "articulos_recibidos" => $total_art_recibidos,
            "total_articulos" => count($queryDEtailsTotal),
            "recepcionCollapsed" => true,
            "lugarRecepcionTipo" => $lugarRecepcionTipo,
            "lugarRecepcionToken" => $lugarRecepcionToken,
            "lugarRecepcionDireccion" => $lugarRecepcionDireccion,
            "existe_orden_recepcion" => count($ordRecepcionExists) > 0 ? true : false,
            "folio_orden_recepcion" => $folio_orden_recepcion,
            "bloqueo_orden_recepcion" => $bloqueo_orden_recepcion,
            "desbloqueo_fecha_orden_recepcion" => $desbloqueo_fecha_orden_recepcion,
            "uuid_orden_recepcion" => $uuid_orden_recepcion,
            //orden de pago 
            "existe_orden_pago" => count($ordPagoExists) > 0 ? true : false,
            "token_orden_pago" => $orden_pago_token,
            "folio_orden_pago" => $orden_pago_folio,
            "fecha_contabilizacion_orden_pago" => $orden_pago_fecha_contabilizacion,
            "bloqueo_orden_pago" => $orden_pago_bloqueo,
            "desbloqueo_fecha_orden_pago" => $orden_pago_desbloqueo_fecha,
            "pagos_realizados_folio" => $pagos_realizados_folio,
            "pagos_realizados_fecha_contabilizacion" => $pagos_realizados_fecha_contabilizacion,
            "pagos_realizados_total" => $pagos_realizados_total,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //periodicidad de compra
            "periodicidadCompra" => $vBuy->periodicidadCompra,
            "repeticionPeriodo" => $vBuy->repeticionPeriodo,
            "tipoPeriodo" => $vBuy->tipoPeriodo,
            "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
            "varImporte" => $vBuy->varImporte,
            //desglose
            "ver_seccion_compra" => false,
            "desglose_compra" => [],
            //otros
            "recepcionPago" => $vBuy->recepcionPago,
            "recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
            "usuario_comprador" => $user_compra,
          );
          $arrayCompras[] = $arrayForeach;
        }
        
        $dataMensaje = array(
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function autorizarCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compra = $parametrosArray['token_compra'];

        if (isset($token_compra) && !empty($token_compra)) {
          $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where("eegr_compras.token_compras",$token_compra)
          ->where("eegr_compras.status_compra",TRUE)
          ->where("emp.empresa_token",$usuario->empresa_token)
          ->where("users.usuario_token",$usuario->user_token)
          ->select(
            "eegr_compras.id AS idBuy",
            "eegr_compras.token_compras",
            "eegr_compras.fecha_contabilizacion",
            "eegr_compras.proveedor",
            "eegr_compras.folio_compra",
            "eegr_compras.post_folio",
            "eegr_compras.recibeProducto",
            "eegr_compras.recepcion_prov", 
            "eegr_compras.recepcion_estab",
            "eegr_compras.usuario_comprador",
            "emp.id AS idEmp",
            "users.id AS userr")
          ->get();
          
          foreach ($listaCompras as $vBuy) {
            $folio_compra = "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
            //$vBuy->token_compras;
            $obtenCompra = $vBuy->idBuy;
            //echo $vBuy->userr;exit;

            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $obtenCompra);
            $orden_de_pago_vinculada = $tknOrder;
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = time();
            $orderpay->fecha_contabilizacion_ordenPago = $vBuy->fecha_contabilizacion;
            $orderpay->factura_compra = $obtenCompra;
            $orderpay->ord_proveedor = $vBuy->proveedor;
            $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($vBuy->fecha_contabilizacion);
            $orderpay->orden_bloqueada = $vBuy->recibeProducto ? FALSE : TRUE;
            $orderpay->autorizacion_pay = FALSE;
            $orderpay->fecha_autorizacion_pay = NULL;
            $orderpay->tentativa_pago = NULL;
            $orderpay->orden_terminada_bool = FALSE;
            $orderpay->orden_terminada_fecha = NULL;
            $orderpay->status_ordenPago = TRUE;  //cifrado
            $orderpay->empresa = $vBuy->idEmp; //cifrado
            $orderpay->comprador = $vBuy->usuario_comprador; //cifrado
            $insertOrder = $orderpay->save();

            $folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
              ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
              WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $orden_recept = new OrdenRecepcionModelo();
            $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
            $orden_recept->folio_recepcion = $folioRecepcionOrden[0]->folio;
            $orden_recept->fecha_recepcion = time();
            $orden_recept->proveedor = $vBuy->proveedor;
            $orden_recept->orden_compra = $obtenCompra;
            $orden_recept->almacen = is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab) ? NULL : (!is_null($vBuy->recepcion_prov) ? $vBuy->recepcion_prov : $vBuy->recepcion_estab);
            $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
            $orden_recept->orden_bloqueada = !$vBuy->recibeProducto ? FALSE : TRUE;
            $orden_recept->observaciones = NULL;
            $orden_recept->empresa = $vBuy->idEmp; //cifrado
            $newOrderRecept = $orden_recept->save();

            $upDateautorizCompra = DB::table('eegr_compras')
            ->where('token_compras',$vBuy->token_compras)
            ->limit(1)->update(
              array(
                "status_autorizacion" => TRUE,
                "autoriza" => $vBuy->userr,
              )
            );

            if ($upDateautorizCompra) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "La compra con el folio $folio_compra ha sido autorizada"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Hay errores en configuracion de los productos/servicios relacionados a esta compra, para mayor información comuniquese a soporte'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'La compra que ha seleccionado para autorizar no existe'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraRecepcionOrdenByCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras' => 'required|string',
        'token_cat_proveedores' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compras = $parametrosArray['token_compras'];
        $token_cat_proveedores = $parametrosArray['token_cat_proveedores'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $validate_token_compras = isset($token_compras) && !empty($token_compras);
        $validate_token_cat_proveedores = isset($token_cat_proveedores) && !empty($token_cat_proveedores);
        
        if ($permisosCreacion && $validate_token_compras && $validate_token_cat_proveedores) {
          $queryCompra = DB::table("eegr_compras")->where("token_compras",$token_compras)->get();

          foreach ($queryCompra as $vBuy) {
            $obtenCompra = DB::table("eegr_compras")->where("token_compras",$token_compras)->value("id");
            $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_cat_proveedores)->value("id");

            $folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
              ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
              WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $folio_ordenRecepcion = "ORDREC-".$JwtAuth->generarFolio($folioRecepcionOrden[0]->folio);
            $tknOrder = $JwtAuth->encriptarToken(time(), $folio_ordenRecepcion, $obtenCompra);

            $orden_recept = new OrdenRecepcionModelo();
            $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
            $orden_recept->folio_recepcion = $folioRecepcionOrden[0]->folio;
            $orden_recept->fecha_recepcion = time();
            $orden_recept->proveedor = $idProveedor;
            $orden_recept->orden_compra = $obtenCompra;
            $orden_recept->almacen = is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab) ? NULL : (!is_null($vBuy->recepcion_prov) ? $vBuy->recepcion_prov : $vBuy->recepcion_estab);
            $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
            $orden_recept->orden_bloqueada = !$vBuy->recibeProducto ? FALSE : TRUE;
            $orden_recept->observaciones = NULL;
            $orden_recept->empresa = $vBuy->comprador; //cifrado
            $newOrderRecept = $orden_recept->save();
            $dataMensaje = array("status" => $newOrderRecept ? "success" : "error","code" => 200,"message" => $newOrderRecept ? "orden de recepción registrada con folio $folio_ordenRecepcion" : "orden de recepción no registrada, intente más tarde o comuniquese a soporte");
          }
        } else {
          $mensaje_error_main = '';
          if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
          if (!$validate_token_compras) {$mensaje_error_main = 'No se encontro respuesta a compra registrada, verifique su información o comuniquese a soporte';}
          if (!$validate_token_cat_proveedores) {$mensaje_error_main = 'No se encontro respuesta a proveedor registrado, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desbloqueaRecepcionOrdenByCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras' => 'required|string',
        'uuid_orden_recepcion' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compras = $parametrosArray['token_compras'];
        $uuid_orden_recepcion = $parametrosArray['uuid_orden_recepcion'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $validate_token_compras = isset($token_compras) && !empty($token_compras);
        $validate_uuid_orden_recepcion = isset($uuid_orden_recepcion) && !empty($uuid_orden_recepcion);
        
        if ($permisosCreacion && $validate_token_compras && $validate_uuid_orden_recepcion) {
          $ordenRecepcionQuery = DB::table("eegr_compras_orden_recepcion AS order")
          ->join("eegr_compras AS buy", "order.orden_compra", "=","buy.id")
          ->where("order.uuid_orden_recepcion",$uuid_orden_recepcion)
          ->where("buy.token_compras",$token_compras)
          ->get();
          foreach ($ordenRecepcionQuery as $vORecep) {
            $orderUnLock = DB::table("eegr_compras_orden_recepcion AS order")
            ->where("order.uuid_orden_recepcion",$vORecep->uuid_orden_recepcion)
            ->limit(1)->update(array("order.orden_bloqueada" => FALSE,"order.fecha_desbloqueo" => time()));
            $dataMensaje = array("status" => $orderUnLock ? "success" : "error","code" => 200,"message" => $orderUnLock ? "orden de recepción activada" : "orden de recepción no registrada, intente más tarde o comuniquese a soporte");
          }
        } else {
          $mensaje_error_main = '';
          if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
          if (!$validate_token_compras) {$mensaje_error_main = 'No se encontro respuesta a compra registrada, verifique su información o comuniquese a soporte';}
          if (!$validate_uuid_orden_recepcion) {$mensaje_error_main = 'No se encontro respuesta a orden de recepción registrada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraPagoOrdenByCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras' => 'required|string',
        'token_cat_proveedores' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compras = $parametrosArray['token_compras'];
        $token_cat_proveedores = $parametrosArray['token_cat_proveedores'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $validate_token_compras = isset($token_compras) && !empty($token_compras);
        $validate_token_cat_proveedores = isset($token_cat_proveedores) && !empty($token_cat_proveedores);
        
        if ($permisosCreacion && $validate_token_compras && $validate_token_cat_proveedores) {
          $fechaSistema = time();
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $obtenCompra = DB::table("eegr_compras")->where("token_compras",$token_compras)->value("id");
            $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_cat_proveedores)->value("id");
            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $folio_ordenPago = "ORDP-".$JwtAuth->generarFolio($folioOrden[0]->folio);
            $tknOrder = $JwtAuth->encriptarToken(time(), $folio_ordenPago, $obtenCompra);

            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = $fechaSistema;
            //$orderpay->fecha_contabilizacion_ordenPago = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $orderpay->factura_compra = $obtenCompra;
            $orderpay->ord_proveedor = $idProveedor;
            $orderpay->doc_anterior_fecha_contabilizacion = DB::table("eegr_compras")->where("token_compras",$token_compras)->value("fecha_contabilizacion");
            $orderpay->orden_bloqueada = FALSE;
            $orderpay->autorizacion_pay = FALSE;
            $orderpay->fecha_autorizacion_pay = NULL;
            $orderpay->tentativa_pago = NULL;
            $orderpay->orden_terminada_bool = FALSE;
            $orderpay->orden_terminada_fecha = NULL;
            $orderpay->status_ordenPago = TRUE;  //cifrado
            $orderpay->empresa = $vEmp->id; //cifrado
            $orderpay->comprador = $vEmp->userr; //cifrado
            $insertOrder = $orderpay->save();
            $dataMensaje = array("status" => $insertOrder ? "success" : "error","code" => 200,"message" => $insertOrder ? "orden de pago registrada con folio $folio_ordenPago" : "orden de pago no registrada, intente más tarde o comuniquese a soporte");
          }
        } else {
          $mensaje_error_main = '';
          if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
          if (!$validate_token_compras) {$mensaje_error_main = 'No se encontro respuesta a compra registrada, verifique su información o comuniquese a soporte';}
          if (!$validate_token_cat_proveedores) {$mensaje_error_main = 'No se encontro respuesta a proveedor registrado, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desbloqueaPagoOrdenByCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras' => 'required|string',
        'token_orden_pago' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compras = $parametrosArray['token_compras'];
        $token_orden_pago = $parametrosArray['token_orden_pago'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $validate_token_compras = isset($token_compras) && !empty($token_compras);
        $validate_token_orden_pago = isset($token_orden_pago) && !empty($token_orden_pago);
        
        if ($permisosCreacion && $validate_token_compras && $validate_token_orden_pago) {
          $orderPagosQuery = DB::table("fnzs_pagos_orden")->where("token_ordenPago",$token_orden_pago)->get();
          foreach ($orderPagosQuery as $vPayDone) {
            $orderUnLock = DB::table("fnzs_pagos_orden AS order")
            ->join("eegr_compras AS buy", "order.factura_compra", "=","buy.id")
            ->where("order.token_ordenPago",$vPayDone->token_ordenPago)
            ->where("buy.token_compras",$token_compras)
            ->limit(1)->update(
              array("order.orden_bloqueada" => FALSE,"order.fecha_desbloqueo" => time())
            );
            $dataMensaje = array("status" => $orderUnLock ? "success" : "error","code" => 200,"message" => $orderUnLock ? "orden de pago activada" : "orden de pago no registrada, intente más tarde o comuniquese a soporte");
          }
        } else {
          $mensaje_error_main = '';
          if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
          if (!$validate_token_compras) {$mensaje_error_main = 'No se encontro respuesta a compra registrada, verifique su información o comuniquese a soporte';}
          if (!$validate_token_orden_pago) {$mensaje_error_main = 'No se encontro respuesta a orden de pago registrada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cancelarCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        date_default_timezone_set($selectEmp[0]->zona_horaria);

        $listaCompra = DB::select("SELECT comp.id,comp.folio_compra,comp.proveedor,comp.usuario_comprador FROM eegr_compras AS comp JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE comp.token_compras = ? AND comp.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token = ?",[$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]);

        if (count($listaCompra) == 1) {

          $upDateautorizCompra = DB::table('compras')
            ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'eegr_compras.status_compra' => TRUE,
              'eegr_compras.token_compras' => $parametrosArray['token_compra'],
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                "eegr_compras.status_autorizacion" => FALSE,
                "eegr_compras.autoriza" => $selectEmp[0]->userr,
                "eegr_compras.status_cancelacion" => FALSE,
                "eegr_compras.cancela" => $selectEmp[0]->userr,
              )
            );

          //echo $upDateautorizCompra." autorizasa";

          if ($upDateautorizCompra) {
            $buscaOrdenPago = DB::select("SELECT order_pay.id FROM orden_pago AS order_pay JOIN compras AS comp WHERE order_pay.factura_compra = comp.id AND comp.token_compras = ?", [$parametrosArray['token_compra']]);
            if (count($buscaOrdenPago) != 0) {
              $selectPagosOrden = DB::select("SELECT id FROM pagos WHERE orden_pago = ?", [$buscaOrdenPago[0]->id]);

              if (count($selectPagosOrden) == 0) {
                $upDateorden_pago = DB::table('orden_pago')
                  ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
                  ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                  ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                  ->where([
                    'eegr_compras.status_compra' => TRUE,
                    'eegr_compras.token_compras' => $parametrosArray['token_compra'],
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                  ])
                  ->limit(1)->update(
                    array(
                      "orden_pago.status" => FALSE,
                      "orden_pago.fecha_delete_ordenPago" => time(),
                    )
                  );

                if ($insertOrder) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La compra con el folio ' . $listaCompra[0]->folio_compra . ' que ha seleccionado fue cancelada'
                  );
                } else {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La cancelación de la compra con el folio ' . $listaCompra[0]->folio_compra . ' no fue terminada debido a errores internos, para mayor información comuniquese a soporte'
                  );
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La cancelación de la compra con el folio ' . $listaCompra[0]->folio_compra . ' no fue terminada debido a que ya registra pagos realizados, para mayor información comuniquese a soporte'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La compra con el folio ' . $listaCompra[0]->folio_compra . ' que ha seleccionado fue cancelada'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'No se generó autorización para esta compra debido a errores internos, para mayor información comuniquese a soporte'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'La compra que ha seleccionado para autorizar no existe'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //compras por autorizar
  public function desgloseCompletoCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_compras.status_autorizacion' => TRUE,
            'eegr_compras.token_compras' => $parametrosArray['token_compra'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($listaCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

					$queryProveedor = DB::table("eegr_compras AS buy")
					->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "catprov.id")
					->join("sos_personas AS people", "catprov.proveedor", "people.id")
					->where(["buy.token_compras" => $vBuy->token_compras])
					->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					->first();
					$proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
          $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).($queryProveedor->post_folio != NULL ? '-'.$queryProveedor->post_folio : '')) : "";
					$proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";

					$queryAnticipoBuy = DB::table("eegr_compras AS buy")
					->join("eegr_catalogo_proveedores_anticipo AS ant", "buy.anticipo", "ant.uuid_anticipo")
					->where(["buy.token_compras" => $vBuy->token_compras])
					->select('ant.uuid_anticipo','ant.moneda_code','ant.moneda_decimales','ant.tipo_cambio','ant.monto_total','ant.observaciones')
					->first();
					$anticipo_uuid = $queryAnticipoBuy ? $queryAnticipoBuy->uuid_anticipo : "";
          $anticipo_moneda_code = $queryAnticipoBuy ? $queryAnticipoBuy->moneda_code : "";
					$anticipo_moneda_decimales = $queryAnticipoBuy ? $queryAnticipoBuy->moneda_decimales : "";
					$anticipo_tipo_cambio = $queryAnticipoBuy ? $queryAnticipoBuy->tipo_cambio : "";
          $anticipo_monto_total = $queryAnticipoBuy ? $queryAnticipoBuy->monto_total : "";
					$anticipo_observaciones = $queryAnticipoBuy ? $JwtAuth->desencriptar($queryAnticipoBuy->observaciones) : "";

          //$resultXml ="errorXml"; //<div *ngSwitchCase="'validoXml'" class="col-12">
          $dataCFDI_comprobante = array();
          $dataCFDIRelacionados = array();
          $dataCFDIEmisor = array();
          $dataCFDIReceptor = array();
          $dataCFDI_impuestos_retenidos_lista = array();
          $dataCFDI_impuestos_trasladados_lista = array();
          $dataCFDIComplemento = array();

          $queryCFDIEstructura = ComprasModelo::join("cfdi_vinculacion_compras AS vinc_buy", "eegr_compras.id", "=", "vinc_buy.compra_vinculada")
          ->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")//cfdi__estructura
          ->where('eegr_compras.token_compras',$vBuy->token_compras)->get();
          foreach ($queryCFDIEstructura as $vCFDI) {
            //$resultXml = "validoXml";
            $dataCFDI_comprobante[] = array("title" => "Versión","content" => $vCFDI->cfdi_comprobante_version);
            $dataCFDI_comprobante[] = array("title" => 'Serie',"content" => $vCFDI->cfdi_comprobante_serie);
            $dataCFDI_comprobante[] = array("title" => 'Folio',"content" => $vCFDI->cfdi_comprobante_folio);
            $dataCFDI_comprobante[] = array("title" => 'Fecha',"content" => $vCFDI->cfdi_comprobante_fecha);
            $dataCFDI_comprobante[] = array("title" => 'Forma de pago',"content" => $vCFDI->cfdi_comprobante_forma_de_pago);
            $dataCFDI_comprobante[] = array("title" => 'Método de Pago',"content" => $vCFDI->cfdi_comprobante_metodo_de_pago);
            $dataCFDI_comprobante[] = array("title" => 'Subtotal',"content" => $vCFDI->cfdi_comprobante_subtotal);
            $dataCFDI_comprobante[] = array("title" => 'Moneda',"content" => $vCFDI->cfdi_comprobante_moneda);
            $dataCFDI_comprobante[] = array("title" => 'Tipo de cambio',"content" => $vCFDI->cfdi_comprobante_tipo_de_cambio);
            $dataCFDI_comprobante[] = array("title" => 'Total',"content" => $vCFDI->cfdi_comprobante_total);
            $dataCFDI_comprobante[] = array("title" => 'Confirmación',"content" => $vCFDI->cfdi_comprobante_confirmacion);
            $dataCFDI_comprobante[] = array("title" => 'Tipo de comprobante',"content" => $vCFDI->cfdi_comprobante_tipo_de_comprobante);
            $dataCFDI_comprobante[] = array("title" => 'Lugar de Expedición',"content" => $vCFDI->cfdi_comprobante_lugar_de_expedicion);
            $dataCFDI_comprobante[] = array("title" => 'No de certificado',"content" => $vCFDI->cfdi_comprobante_no_de_certificado);
            $dataCFDI_comprobante[] = array("title" => 'Sello',"content" => $vCFDI->cfdi_comprobante_sello);
            $dataCFDI_comprobante[] = array("title" => 'Certificado',"content" => $vCFDI->cfdi_comprobante_certificado);

            $dataCFDIRelacionados[] = array("title" => 'Tipo de relación',"content" => $vCFDI->cfdi_relacionados_tipo_de_relacion);
            $dataCFDIRelacionados[] = array("title" => 'UUID',"content" => $vCFDI->cfdi_relacionados_uuid);

            $dataCFDIEmisor[] = array("title" => 'Rfc del emisor',"content" => $vCFDI->cfdi_emisor_rfc);
            $dataCFDIEmisor[] = array("title" => 'Nombre del emisor',"content" => $vCFDI->cfdi_emisor_nombre);
            $dataCFDIEmisor[] = array("title" => 'Regimen fiscal del emisor',"content" => $vCFDI->cfdi_emisor_regimen_fiscal);

            $dataCFDIReceptor[] = array("title" => 'Rfc del receptor',"content" => $vCFDI->cfdi_receptor_rfc);
            $dataCFDIReceptor[] = array("title" => 'Uso del CFDI',"content" => $vCFDI->cfdi_receptor_uso_del_cfdi);

            $dataCFDIComplemento[] = array("title" => 'UUID',"content" => $vCFDI->cfdi_complementoUUID);
            $dataCFDIComplemento[] = array("title" => 'FechaTimbrado',"content" => $vCFDI->cfdi_complementoFechaTimbrado);
            $dataCFDIComplemento[] = array("title" => 'RfcProvCertif',"content" => $vCFDI->cfdi_complementoRfcProvCertif);
            $dataCFDIComplemento[] = array("title" => 'NoCertificadoSAT',"content" => $vCFDI->cfdi_complementoNoCertificadoSAT);
            $dataCFDIComplemento[] = array("title" => 'SelloCFD',"content" => $vCFDI->cfdi_complementoSelloCFD);
            $dataCFDIComplemento[] = array("title" => 'SelloSAT',"content" => $vCFDI->cfdi_complementoSelloSAT);
          }

          $resultXml = is_null($vBuy->facturaXml) || (!is_null($vBuy->facturaXml) && count($queryCFDIEstructura) == 1) ? "validoXml" : "errorXml";
          
          $dataConceptosINTERNO = array();
          $num_lista = 1;
          $queryConceptosCompras = ComprasModelo::join("eegr_compras_detalle AS detbuy", "eegr_compras.id", "=", "detbuy.numero_compra")
          ->where('eegr_compras.token_compras',$vBuy->token_compras)->get();
          foreach ($queryConceptosCompras as $vDet) {
            $subtotal = (floatval($vDet->precio_unitario) * floatval($vDet->tipo_de_cambio_detalle_compra)) * $vDet->cantidad;
            //echo floatval($vDet->descuento);
            $importe_total = $subtotal - $vDet->descuento + floatval($vDet->traslados_total) - floatval($vDet->retenciones_total);

            $totalDetComp = number_format($subtotal,$moneda_decimales,'.', ',');
            $totalDetCompFormat = number_format($subtotal - $vDet->descuento,$moneda_decimales,'.', ',');

            $format_precio_unitario = number_format($vDet->precio_unitario,$moneda_decimales,'.', ',');
            $format_descuento = number_format($vDet->descuento,$moneda_decimales,'.', ',');
            $format_retenciones = number_format($vDet->retenciones_total,$moneda_decimales,'.', ',');
            $format_traslados = number_format($vDet->traslados_total,$moneda_decimales,'.', ',');
            $format_total = number_format($importe_total,$moneda_decimales,'.', ',');

            $articulo_homologado_token = "";
            $articulo_homologado_folio = "";
            $articulo_homologado_nombre = "";

            if (!is_null($vDet->producto)) {
              $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_productos AS catprod","detbuy.producto","=","catprod.id")
              ->where('detbuy.token_detcompra',$vDet->token_detcompra)->get();

              foreach ($productoList as $vProd) {
                $articulo_homologado_token = $vProd->token_cat_productos;
                $articulo_homologado_folio = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);
                $articulo_homologado_nombre = $JwtAuth->desencriptar($vProd->producto);
              }
            } 
            
            if (!is_null($vDet->servicio)) {
              $servicioList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_servicios AS catserv", "detbuy.servicio", "=", "catserv.id")
              ->where('detbuy.token_detcompra',$vDet->token_detcompra)->get();

              foreach ($servicioList as $vServ) {
                $articulo_homologado_token = $vServ->token_cat_servicios;
                $articulo_homologado_folio = $vServ->folio_sistema != NULL && $vServ->folio_sistema != "" ? ('SERV-'.($vServ->post_folio == NULL ? $JwtAuth->generarFolio($vServ->folio_sistema) : $JwtAuth->generarFolio($vServ->folio_sistema).'-'.$vServ->post_folio)):
                'SERV-TEMP-'.$JwtAuth->generarFolio($vServ->temps_folio);
                $articulo_homologado_nombre = $JwtAuth->desencriptar($vServ->servicio);
              }
            }
            
            $detail_retenciones = array();
            $queryRetencBuy = DB::table("eegr_compras_detalle_impuestos AS ret_buy")
            ->join("cont_impuestos_catalogo AS impcat", "ret_buy.impuesto_relacionado", "=", "impcat.id")
            ->join("eegr_compras_detalle AS detbuy", "ret_buy.detalle_compra", "=", "detbuy.id")
            ->join("eegr_compras AS buy", "detbuy.numero_compra", "=", "buy.id")
            ->where("ret_buy.retencion_traslado","rete")
            ->where('detbuy.token_detcompra',$vDet->token_detcompra)
            ->where('buy.token_compras',$vBuy->token_compras)
					  ->select('impcat.token_catalogo_impuesto','impcat.folio_impuesto','impcat.post_folio','impcat.abreviacion_impuesto','ret_buy.token_imp_det_buy','ret_buy.base','ret_buy.impuesto','ret_buy.tipo_factor','ret_buy.tasa_cuota','ret_buy.importe')
					  ->first();
            if ($queryRetencBuy) {
              $folio_impuesto = 'IMP-'.$JwtAuth->generarFolio($queryRetencBuy->folio_impuesto).(!empty($queryRetencBuy->post_folio) && !is_null($queryRetencBuy->post_folio) ? '-'.$queryRetencBuy->post_folio : '');
              $row_ret = array(
                "token_imp_det_buy" => $queryRetencBuy->token_imp_det_buy,
                "Base" => $queryRetencBuy->base,
                "Impuesto" => $queryRetencBuy->impuesto,
                "TipoFactor" => $queryRetencBuy->tipo_factor,
                "TasaOCuota" => $queryRetencBuy->tasa_cuota,
                "Importe" => $queryRetencBuy->importe,
                "impuesto_relacionado_token" => $queryRetencBuy->token_catalogo_impuesto,
                "impuesto_relacionado_folio" => $folio_impuesto,
                "impuesto_relacionado_abreviacion" => $JwtAuth->desencriptar($queryRetencBuy->abreviacion_impuesto),
              );
              $detail_retenciones[] = $row_ret;
            }

            $detail_traslados = array();
            $queryTrasBuy = DB::table("eegr_compras_detalle_impuestos AS tras_buy")
            ->join("cont_impuestos_catalogo AS impcat", "tras_buy.impuesto_relacionado", "=", "impcat.id")
            ->join("eegr_compras_detalle AS detbuy", "tras_buy.detalle_compra", "=", "detbuy.id")
            ->join("eegr_compras AS buy", "detbuy.numero_compra", "=", "buy.id")
            ->where("tras_buy.retencion_traslado","tras")
            ->where('detbuy.token_detcompra',$vDet->token_detcompra)
            ->where('buy.token_compras',$vBuy->token_compras)
					  ->select('impcat.token_catalogo_impuesto','impcat.folio_impuesto','impcat.post_folio','impcat.abreviacion_impuesto','tras_buy.token_imp_det_buy','tras_buy.base','tras_buy.impuesto','tras_buy.tipo_factor','tras_buy.tasa_cuota','tras_buy.importe')
					  ->first();
            if ($queryTrasBuy) {
              $folio_impuesto_tras = 'IMP-'.$JwtAuth->generarFolio($queryTrasBuy->folio_impuesto).(!empty($queryTrasBuy->post_folio) && !is_null($queryTrasBuy->post_folio) ? '-'.$queryTrasBuy->post_folio : '');
              $row_tras = array(
                "token_imp_det_buy" => $queryTrasBuy->token_imp_det_buy,
                "Base" => $queryTrasBuy->base,
                "Impuesto" => $queryTrasBuy->impuesto,
                "TipoFactor" => $queryTrasBuy->tipo_factor,
                "TasaOCuota" => $queryTrasBuy->tasa_cuota,
                "Importe" => $queryTrasBuy->importe,
                "impuesto_relacionado_token" => $queryTrasBuy->token_catalogo_impuesto,
                "impuesto_relacionado_folio" => $folio_impuesto_tras,
                "impuesto_relacionado_abreviacion" => $JwtAuth->desencriptar($queryTrasBuy->abreviacion_impuesto),
              );
              $detail_traslados[] = $row_tras;
            }

            $listActivosFijos = ActivosFijosModelo::join("eegr_compras_detalle AS detbuy", "eegr_activos_fijos_catalogo.id", "=", "detbuy.activo_fijo")
            ->join("eegr_compras AS buy", "detbuy.numero_compra", "=", "buy.id")
            ->where('eegr_activos_fijos_catalogo.activo_status',TRUE)
            ->where('detbuy.token_detcompra',$vDet->token_detcompra)
            ->where('buy.token_compras',$vBuy->token_compras)
					  ->select('eegr_activos_fijos_catalogo.token_act_fijos','eegr_activos_fijos_catalogo.folio_activo','eegr_activos_fijos_catalogo.categoria')
					  ->first();
            $activos_fijos_token = $listActivosFijos ? $listActivosFijos->token_act_fijos : '';
            $activos_fijos_folio = $listActivosFijos ? "ACTF-".$JwtAuth->generarFolio($listActivosFijos->folio_activo) : '';

            if ($listActivosFijos) {
              switch ($listActivosFijos->categoria) {
                case 'act_cat_1':
                  $activos_fijos_categoria = 'Terrenos y edificios industriales';
                  break;
                case 'act_cat_2':
                  $activos_fijos_categoria = 'Maquinaria y equipo de producción';
                  break;
                case 'act_cat_3':
                  $activos_fijos_categoria = 'Vehículos de transporte y distribución<';
                  break;
                case 'act_cat_3':
                  $activos_fijos_categoria = 'Mobiliario y enseres industriales';
                  break;
                case 'act_cat_5':
                  $activos_fijos_categoria = 'Equipos de control de calidad y laboratorio';
                  break;
                case 'act_cat_6':
                  $activos_fijos_categoria = 'Sistemas de energía y utilidades';
                  break;
                case 'act_cat_7':
                  $activos_fijos_categoria = 'Activos intangibles relacionados con la producción';
                  break;
                default:
                  $activos_fijos_categoria = null;
                  break;
              }
            } else {
              $activos_fijos_categoria = null;
            }

            $listActivosIntang = ActivosIntangiblesModelo::join("eegr_compras_detalle AS detbuy", "eegr_activos_intangibles_catalogo.id", "=", "detbuy.activo_intangible")
            ->join("eegr_compras AS buy", "detbuy.numero_compra", "=", "buy.id")
            ->where('eegr_activos_intangibles_catalogo.status',TRUE)
            ->where('detbuy.token_detcompra',$vDet->token_detcompra)
            ->where('buy.token_compras',$vBuy->token_compras)
					  ->select('eegr_activos_intangibles_catalogo.token_act_intang','eegr_activos_intangibles_catalogo.folio_activo','eegr_activos_intangibles_catalogo.categoria')
					  ->first();
            $activos_intang_token = $listActivosIntang ? $listActivosIntang->token_act_intang : '';
            $activos_intang_folio = $listActivosIntang ? "ACTI-".$JwtAuth->generarFolio($vActivos->folio_activo) : '';

            if ($listActivosIntang) {
              switch ($listActivosIntang->categoria) {
                case 'act_cat_1':
                  $activos_intang_categoria = 'Marcas comerciales y nombres de dominio';
                  break;
                case 'act_cat_2':
                  $activos_intang_categoria = 'Patentes, derechos de autor y secretos comerciales';
                  break;
                case 'act_cat_3':
                  $activos_intang_categoria = 'Software y tecnología';
                  break;
                case 'act_cat_3':
                  $activos_intang_categoria = 'Contratos y acuerdos comerciales';
                  break;
                case 'act_cat_5':
                  $activos_intang_categoria = 'Relaciones con clientes y proveedores';
                  break;
                case 'act_cat_6':
                  $activos_intang_categoria = 'Conocimiento y habilidades especializadas de los empleados';
                  break;
                case 'act_cat_7':
                  $activos_intang_categoria = 'Reputación de la marca y prestigio de la empresa';
                  break;
                case 'act_cat_8':
                  $activos_intang_categoria = 'Derechos de explotación de franquicias y licencias';
                  break;
                default:
                  $activos_intang_categoria = null;
                  break;
              }
            } else {
              $activos_intang_categoria = null;
            }

            $row_det = array(
              "token_detcompra" => $vDet->token_detcompra,
              "num_lista" => $num_lista,
              "Cantidad" => $vDet->cantidad,
              "Cantidad_class" => "",
              "Unidad" => $vDet->unidad_medida,
              "Unidad_class" => "",
              "Descripcion" => isset($vDet->concepto_cfdi) && !is_null($vDet->concepto_cfdi) ? $JwtAuth->desencriptar($vDet->concepto_cfdi) : "",
              "ValorUnitario" => "$$format_precio_unitario",
              "ValorUnitario_class" => "",
              "Descuento" => "$$format_descuento",
              "Descuento_class" => "",
              "Importe" => "$$totalDetCompFormat",
              "Importe_class" => "",
              "TotalRetenciones" => "$$format_retenciones",
              "Retenciones_class" => "",
              "retenciones" => $detail_retenciones,
              "TotalTraslados" => "$$format_traslados",
              "Traslados_class" => "",
              "traslados" => $detail_traslados,
              "Subtotal" => "$$format_total",
              "Subtotal_class" => "",
              //Articulo a homologar generales
              "articulo_homologado_token" => $articulo_homologado_token,
              "articulo_homologado_folio" => $articulo_homologado_folio,
              "articulo_homologado_nombre" => $articulo_homologado_nombre,
              //Articulo a homologar series
              "articulo_homologado_serie_token" => "",
              "articulo_homologado_serie_numero" => "",
              //Articulo a homologar lotes
              "articulo_homologado_lote_token" => "",
              "articulo_homologado_lote_numero" => "",
              //Articulo a homologar pedimentos
              "articulo_homologado_pedimento_token" => "",
              "articulo_homologado_pedimento_numero" => "",
              //Articulo a homologar uso
              "articulo_homologado_view_uso" => false,
              "articulo_homologado_uso" => $vDet->destino,
              //Articulo a homologar uso
              "articulo_homologado_activoFijo_token" => $activos_fijos_token,
              "articulo_homologado_activoFijo_folio" => $activos_fijos_folio,
              "articulo_homologado_activoFijo_categoria" => $activos_fijos_categoria,

              "articulo_homologado_activoIntangible_token" => $activos_intang_token,
              "articulo_homologado_activoIntangible_folio" => $activos_intang_folio,
              "articulo_homologado_activoIntangible_categoria" => $activos_intang_categoria,
              //prorrateos
              "articulo_homologado_prorratea" => false,
              //desglose
              "activa_desglose" => false,
              "conceptoCFDI_listas" => [],
              "conceptoCFDI_referido" => [],
            );
            $dataConceptosINTERNO[] = $row_det; 
            ++$num_lista;
          }

          $dataCFDI_conceptos = array();
          $cfdi_num_lista = 1;
          $queryCFDIConceptosCompras = ComprasModelo::join("cfdi_vinculacion_compras AS vinc_buy", "eegr_compras.id", "=", "vinc_buy.compra_vinculada")
          ->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
          ->join("cfdi_comprobantes_conceptos AS det_cfdi_buy", "cfdi.id", "=", "det_cfdi_buy.comprobante_fiscal")
          ->where('eegr_compras.token_compras',$vBuy->token_compras)->get();
          foreach ($queryCFDIConceptosCompras as $cfdiDet) {
            $cfdi_retenciones = array();
            $queryCFDIRetencionesCompras = DB::table("eegr_compras_cfdi_detalle_impuestos AS cfdi_ret_buy")
            ->join("cfdi_comprobantes_conceptos AS det_cfdi_buy", "cfdi_ret_buy.uuid_cfdi_detalle", "=", "det_cfdi_buy.uuid_cfdi_detalle")
            //->join("eegr_compras AS buy", "det_cfdi_buy.numero_compra", "=", "buy.id")
            ->where("cfdi_ret_buy.retencion_traslado","rete")
            ->where("det_cfdi_buy.uuid_cfdi_detalle",$cfdiDet->uuid_cfdi_detalle)
            //->where('buy.token_compras',$vBuy->token_compras)
            ->get();
            foreach ($queryCFDIRetencionesCompras as $cfdiRete) {
              $row_ret = array(
                "uuid_buydet_impuestos" => $cfdiRete->uuid_buydet_impuestos,
                "uuid_cfdi_detalle" => $cfdiRete->uuid_cfdi_detalle,
                "Base" => $cfdiRete->base,
                "Impuesto" => $cfdiRete->impuesto,
                "TipoFactor" => $cfdiRete->tipoFactor,
                "TasaOCuota" => $cfdiRete->tasaOCuota,
                "Importe" => $cfdiRete->importe,
              );
              $cfdi_retenciones[] = $row_ret;
              $dataCFDI_impuestos_retenidos_lista[] = $row_ret;
            }

            $cfdi_traslados = array();
            $queryCFDITrasladosCompras = DB::table("eegr_compras_cfdi_detalle_impuestos AS cfdi_ret_buy")
            ->join("cfdi_comprobantes_conceptos AS det_cfdi_buy", "cfdi_ret_buy.uuid_cfdi_detalle", "=", "det_cfdi_buy.uuid_cfdi_detalle")
            //->join("eegr_compras AS buy", "det_cfdi_buy.numero_compra", "=", "buy.id")
            ->where("cfdi_ret_buy.retencion_traslado","tras")
            ->where("det_cfdi_buy.uuid_cfdi_detalle",$cfdiDet->uuid_cfdi_detalle)
            //->where('buy.token_compras',$vBuy->token_compras)
            ->get();
            foreach ($queryCFDITrasladosCompras as $cfdiTras) {
              $row_tras = array(
                "uuid_buydet_impuestos" => $cfdiTras->uuid_buydet_impuestos,
                "uuid_cfdi_detalle" => $cfdiTras->uuid_cfdi_detalle,
                "Base" => $cfdiTras->base,
                "Impuesto" => $cfdiTras->impuesto,
                "TipoFactor" => $cfdiTras->tipoFactor,
                "TasaOCuota" => $cfdiTras->tasaOCuota,
                "Importe" => $cfdiTras->importe,
              );
              $cfdi_traslados[] = $row_tras;
              $dataCFDI_impuestos_trasladados_lista[] = $row_tras;
            }

            $row_det = array(
              "cfdi_num_lista" => $cfdi_num_lista,
              "uuid_cfdi_detalle" => $cfdiDet->uuid_cfdi_detalle,
              "numero_compra" => $cfdiDet->numero_compra,
              "NoIdentificacion" => $cfdiDet->NoIdentificacion,
              "ObjetoImp" => $cfdiDet->ObjetoImp,
              "ClaveProdServ" => $cfdiDet->ClaveProdServ,
              "Cantidad" => $cfdiDet->Cantidad,
              "ClaveUnidad" => $cfdiDet->ClaveUnidad,
              "Unidad" => $cfdiDet->Unidad,
              "Descripcion" => $cfdiDet->Descripcion,
              "ValorUnitario" => $cfdiDet->ValorUnitario,
              "Descuento" => $cfdiDet->Descuento,
              "Importe" => $cfdiDet->Importe,
              "TotalRetenciones" => $cfdiDet->TotalRetenciones,
              "cfdi_retenciones" => $cfdi_retenciones,
              "TotalTraslados" => $cfdiDet->TotalTraslados,
              "cfdi_traslados" => $cfdi_traslados,
              "Subtotal" => $cfdiDet->Subtotal,
            );
            $dataCFDI_conceptos[] = $row_det; 
            ++$cfdi_num_lista;
          }
          
          foreach ($dataConceptosINTERNO as $di => $vInt) {
            $conceptoCFDI_referido = null;
            foreach ($dataCFDI_conceptos as $cfdl => $vCfdl) {
              if (trim($vInt["Descripcion"]) === trim($vCfdl["Descripcion"])) {
                $conceptoCFDI_referido = $vCfdl;
                break;
              }
            }
            $dataConceptosINTERNO[$di]["conceptoCFDI_referido"][] = $conceptoCFDI_referido; 
          }

          //Punto de entrega o recepción
          if (is_null($vBuy->recepcion_prov) &&	is_null($vBuy->recepcion_estab)) {
            $lugarRecepcionTipo = "N/A";
            $lugarRecepcionToken = "";
            $lugarRecepcionDireccion = "";
          } else {
            if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
              $listaDirEstab = DB::table("eegr_compras AS buy")
              ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
              ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
              ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
              ->where("buy.token_compras",$vBuy->token_compras)
              ->where(["catprov.token_cat_proveedores" => $proveedor_token])
              ->get();
              foreach ($listaDirEstab as $vUbica) {
                $lugarRecepcionTipo = "proveedor";
                $lugarRecepcionToken = $vUbica->token_direccion;
                $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
                  $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
              }
            } else {
              $listaDirEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where("estab.status_establecimiento",TRUE)
              ->where("buy.token_compras",$vBuy->token_compras)
              ->get();
              foreach ($listaDirEstab as $vEstab) {
                $lugarRecepcionTipo = "Establecimiento";
                $lugarRecepcionToken = $vEstab->token_establecimiento;
                $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
              }
            }
          }
          
          //seccion facturas
          if (!is_null($vBuy->reembolso_vinculado_main) && !is_null($vBuy->reembolso_vinculado_soli)) {
            $queryCFDIXMLCompras = DB::table("eegr_compras AS buy")
            ->join("terc_reembolso_main AS reem_main", "buy.reembolso_vinculado_main", "=", "reem_main.id")
            ->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
            ->join("sos_documentos AS docs", function ($join) {
              $join->on("docs.reembolso_main", "=", "reem_main.id")
              ->on("docs.reembolso_solicitud", "=", "reem_soli.id")
              ->where("docs.modulo", "reembolsos")
              ->where("docs.folio_modulo", "REEM-CFDI-XML")
              ->where("docs.tipo_documento", "xml");
            })
            ->where("buy.token_compras", $vBuy->token_compras)
            ->select("docs.nombre_documento")
            ->first();

            $documentofacturaXml = $queryCFDIXMLCompras ? $JwtAuth->desencriptar($queryCFDIXMLCompras->nombre_documento) : '';

            $queryCFDIPDFCompras = DB::table("eegr_compras AS buy")
            ->join("terc_reembolso_main AS reem_main", "buy.reembolso_vinculado_main", "=", "reem_main.id")
            ->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
            ->join("sos_documentos AS docs", function ($join) {
              $join->on("docs.reembolso_main", "=", "reem_main.id")
              ->on("docs.reembolso_solicitud", "=", "reem_soli.id")
              ->where("docs.modulo", "reembolsos")
              ->where("docs.folio_modulo", "REEM-CFDI-PDF")
              ->where("docs.tipo_documento", "pdf");
            })
            ->where("buy.token_compras", $vBuy->token_compras)
            ->select("docs.nombre_documento")
            ->first();
            $documentofacturaPdf = $queryCFDIPDFCompras ? $JwtAuth->desencriptar($queryCFDIPDFCompras->nombre_documento) : '';
          } else {
            $documentofacturaXml = !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '';
            $documentofacturaPdf = !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '';
          }
          
          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compras" => $folio_buy,
            "fecha_sistemaCompras" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_altaCompra" => date('d-m-Y H:i:s', $vBuy->fecha_altaCompra),
            "fecha_contabilizacion" => date('d-m-Y', $vBuy->fecha_contabilizacion),
            //proveedor
						"proveedor_token" => $proveedor_token,
						"proveedor_folio" => $proveedor_folio,
						"proveedor_name" => $proveedor_name,
            "compra_contado_credito" => $vBuy->compra_a_credito == 'cont' ? 'contado' : 'crédito',
            "fecha_vencimiento" => $vBuy->compra_a_credito == 'cred' ? date('d-m-Y', $vBuy->fecha_vencimiento) : '',
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
            "facturaXml" => $documentofacturaXml,
            "facturaPdf" => $documentofacturaPdf,
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/$vBuy->token_compras/factura_xml" : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/$vBuy->token_compras/factura_pdf" : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/$vBuy->token_compras/evidencia_sat" : '',
            "recepcionPago" => !empty($vBuy->recepcionPago) ? $JwtAuth->desencriptar($vBuy->recepcionPago) : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "resultXml" => $resultXml,
            "compras_moneda_code" => $vBuy->moneda,
            "compras_moneda_decimales" => $JwtAuth->getMonedaAPI($vBuy->moneda),
            //compras_anticipo
            "compras_anticipo_uuid" => $anticipo_uuid,
            "compras_anticipo_moneda_code" => $anticipo_moneda_code,
            "compras_anticipo_moneda_decimales" => $anticipo_moneda_decimales,
            "compras_anticipo_tipo_cambio" => $anticipo_tipo_cambio,
            "compras_anticipo_monto_total" => $anticipo_monto_total,
            "compras_anticipo_observaciones" => $anticipo_observaciones,
            "compras_forma_pago" => $vBuy->forma_pago,
            "compras_metodo_pago" => $vBuy->metodo_pago,
            "compras_uso_cfdi" => $vBuy->uso_cfdi,
            "compras_recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
            "recepcion_modalidad" => is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab) ? "N/A" : (is_null($vBuy->recepcion_prov) && !is_null($vBuy->recepcion_estab) ? 'proveedor' : 'establecimiento'),
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "tipoLugarRecepcion" => $lugarRecepcionTipo,//$compras->recepcion_prov = $tipoLugarEntrega == 'proveedor' ? $idDireccionProv : NULL;
            "tokenLugarRecepcion" => $lugarRecepcionToken,
            "direccionLugarRecepcion" => $lugarRecepcionDireccion,
            //$compras->recepcion_estab = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
            "dataConceptosINTERNO" => $dataConceptosINTERNO,
            "dataCFDI_comprobante" => $dataCFDI_comprobante,
            "dataCFDIRelacionados" => $dataCFDIRelacionados,
            "dataCFDIEmisor" => $dataCFDIEmisor,
            "dataCFDIReceptor" => $dataCFDIReceptor,
            "dataCFDI_conceptos" => $dataCFDI_conceptos,
            "dataCFDI_impuestos_retenidos_lista" => $dataCFDI_impuestos_retenidos_lista,
            "dataCFDI_impuestos_trasladados_lista" => $dataCFDI_impuestos_trasladados_lista,
            "dataCFDIComplemento" => $dataCFDIComplemento,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'compra_info' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function compraComplementaInformacionCFDI(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras' => 'required|string',
        'cfdi_comprobante' => 'required|array', 
        'cfdi_emisor' => 'required|array',  
        'cfdi_receptor' => 'required|array',
        'cfdi_conceptos' => 'required|array',
        'cfdi_impuestos_retenidos' => 'array',
        'cfdi_impuestos_trasladados' => 'array',
        'cfdi_complemento' => 'required|array',
        'cfdi_relacionados' => 'array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $patrónNumCosto = '/^[0-9$,.-]*$/';
        $patrónNum = '/^[0-9$,.-]*$/';
      
        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $token_compras = $parametrosArray['token_compras'];
        $cfdi_comprobante = $parametrosArray['cfdi_comprobante'];
        $cfdi_emisor = $parametrosArray['cfdi_emisor'];
        $cfdi_receptor = $parametrosArray['cfdi_receptor'];
        $cfdi_conceptos = $parametrosArray['cfdi_conceptos'];
        $cfdi_impuestos_retenidos = $parametrosArray['cfdi_impuestos_retenidos'];
        $cfdi_impuestos_trasladados = $parametrosArray['cfdi_impuestos_trasladados'];
        $cfdi_complemento = $parametrosArray['cfdi_complemento'];
        $cfdi_relacionados = $parametrosArray['cfdi_relacionados'];

        $validate_cfdi_comprobante = isset($cfdi_comprobante) && !empty($cfdi_comprobante) && is_array($cfdi_comprobante);
        $validate_cfdi_emisor = isset($cfdi_emisor) && !empty($cfdi_emisor) && is_array($cfdi_emisor);
        $validate_cfdi_receptor = isset($cfdi_receptor) && !empty($cfdi_receptor) && is_array($cfdi_receptor);
        $validate_cfdi_conceptos = isset($cfdi_conceptos) && !empty($cfdi_conceptos) && is_array($cfdi_conceptos);
        $validate_cfdi_impuestos_retenidos = isset($cfdi_impuestos_retenidos) && is_array($cfdi_impuestos_retenidos);
        $validate_cfdi_impuestos_trasladados = isset($cfdi_impuestos_trasladados) && is_array($cfdi_impuestos_trasladados);
        $validate_cfdi_complemento = isset($cfdi_complemento) && !empty($cfdi_complemento) && is_array($cfdi_complemento);
        $validate_cfdi_relacionados = isset($cfdi_relacionados) && !empty($cfdi_relacionados) && is_array($cfdi_relacionados);

        if ($permisosCreacion && $validate_cfdi_comprobante && $validate_cfdi_emisor && $validate_cfdi_receptor && $validate_cfdi_conceptos && 
          $validate_cfdi_impuestos_retenidos && $validate_cfdi_impuestos_trasladados && $validate_cfdi_complemento
           && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))) {
          $moneda_decimales = 0;
          foreach ($cfdi_comprobante as $vComp) {
            if ($vComp["title"] == "Moneda") {
              $moneda_decimales = $JwtAuth->getMonedaAPI($vComp["content"]);
            }
          }
          
          $queryEmp = DB::table("main_empresas")->where("empresa_token",$usuario->empresa_token)->select('id','root_tkn')->first();

          //$queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          //WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where('eegr_compras.token_compras',$token_compras)
          ->where('eegr_compras.status_autorizacion',TRUE)
          ->where('emp.empresa_token',$usuario->empresa_token)
          ->where('users.usuario_token',$usuario->user_token)
          ->get();
              
          foreach ($listaCompras as $vBuy) {
            $fechaSistema = $vBuy->fecha_sistemaCompras;
            $folio_buy = $vBuy->fecha_sistemaCompras."-COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
            $obtenCompra = DB::table("eegr_compras")->where('token_compras',$vBuy->token_compras)->value("id");
            //echo $obtenCompra;exit;
            
            $cfdi_comprobante_version = '';
            $cfdi_comprobante_serie = '';
            $cfdi_comprobante_folio = '';
            $cfdi_comprobante_fecha = '';
            $cfdi_comprobante_forma_de_pago = '';
            $cfdi_comprobante_metodo_de_pago = '';
            $cfdi_comprobante_subtotal = '';
            $cfdi_comprobante_moneda = '';
            $cfdi_comprobante_tipo_de_cambio = '';
            $cfdi_comprobante_total = '';
            $cfdi_comprobante_confirmacion = '';
            $cfdi_comprobante_tipo_de_comprobante = '';
            $cfdi_comprobante_lugar_de_expedicion = '';
            $cfdi_comprobante_no_de_certificado = '';
            $cfdi_comprobante_sello = '';
            $cfdi_comprobante_certificado = '';

            foreach ($cfdi_comprobante as $vComp) {
              switch ($vComp["title"]) {
                case 'Versión':
                  $cfdi_comprobante_version = $vComp["content"];
                  break;
                case 'Serie':
                  $cfdi_comprobante_serie = $vComp["content"];
                  break;
                case 'Folio':
                  $cfdi_comprobante_folio = $vComp["content"];
                  break;
                case 'Fecha':
                  $cfdi_comprobante_fecha = $vComp["content"];
                  break;
                case 'Forma de pago':
                  $cfdi_comprobante_forma_de_pago = $vComp["content"];
                  break;
                case 'Subtotal':
                  $cfdi_comprobante_subtotal = $vComp["content"];
                  break;
                case 'Moneda':
                  $cfdi_comprobante_moneda = $vComp["content"];
                  break;
                case 'Tipo de cambio':
                  $cfdi_comprobante_tipo_de_cambio = $vComp["content"];
                  break;
                case 'Total':
                  $cfdi_comprobante_total = $vComp["content"];
                  break;
                case 'Confirmación':
                  $cfdi_comprobante_confirmacion = $vComp["content"];
                  break;
                case 'Tipo de comprobante':
                  $cfdi_comprobante_tipo_de_comprobante = $vComp["content"];
                  break;
                case 'Método de Pago':
                  $cfdi_comprobante_metodo_de_pago = $vComp["content"];
                  break;
                case 'Lugar de Expedición':
                  $cfdi_comprobante_lugar_de_expedicion = $vComp["content"];
                  break;
                case 'No de certificado':
                  $cfdi_comprobante_no_de_certificado = $vComp["content"];
                  break;
                case 'Sello':
                  $cfdi_comprobante_sello = $vComp["content"];
                  break;
                case 'Certificado':
                  $cfdi_comprobante_certificado = $vComp["content"];
                  break;
                default:
                  # code...
                  break;
              }
            }

            $cfdi_relacionados_tipo_de_relacion = '';
            $cfdi_relacionados_uuid = '';

            foreach ($cfdi_relacionados as $CFDIr) {
              switch ($CFDIr["title"]) {
                case 'Tipo de relación':
                  $cfdi_relacionados_tipo_de_relacion = $CFDIr["content"];
                  break;
                case 'UUID':
                  $cfdi_relacionados_uuid = $CFDIr["content"];
                  break;
                
                default:
                  //--
                  break;
              }
            }

            $cfdi_emisor_rfc = '';
            $cfdi_emisor_nombre = '';
            $cfdi_emisor_regimen_fiscal = '';

            foreach ($cfdi_emisor as $CFDIe) {
              switch ($CFDIe["title"]) {
                case 'Rfc del emisor':
                  $cfdi_emisor_rfc = $CFDIe["content"];
                  break;
                case 'Nombre del emisor':
                  $cfdi_emisor_nombre = $CFDIe["content"];
                  break;
                case 'Regimen fiscal del emisor':
                  $cfdi_emisor_regimen_fiscal = $CFDIe["content"];
                  break;
                default:
                  //--
                  break;
              }
            }

            $cfdi_receptor_rfc = '';
            $cfdi_receptor_uso_del_cfdi = '';

            foreach ($cfdi_receptor as $CFDIReceptor) {
              switch ($CFDIReceptor["title"]) {
                case 'Rfc del receptor':
                  $cfdi_receptor_rfc = $CFDIReceptor["content"];
                  break;
                case 'Uso del CFDI':
                  $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["content"];
                  break;
                default:
                  //--
                  break;
              }
            }

            foreach ($cfdi_impuestos_retenidos as $vComp) {}
          
            foreach ($cfdi_impuestos_trasladados as $vComp) {}
          
            $cfdi_complementoUUID = '';
            $cfdi_complementoFechaTimbrado = '';
            $cfdi_complementoRfcProvCertif = '';
            $cfdi_complementoNoCertificadoSAT = '';
            $cfdi_complementoSelloCFD = '';
            $cfdi_complementoSelloSAT = '';

            foreach ($cfdi_complemento as $vComplemento) {
              switch ($vComplemento["title"]) {
                case 'UUID':
                  $cfdi_complementoUUID = $vComplemento["content"];
                  break;                  
                case 'FechaTimbrado':
                  $cfdi_complementoFechaTimbrado = $vComplemento["content"];
                  break;
                case 'RfcProvCertif':
                  $cfdi_complementoRfcProvCertif = $vComplemento["content"];
                  break;
                case 'NoCertificadoSAT':
                  $cfdi_complementoNoCertificadoSAT = $vComplemento["content"];
                  break;
                case 'SelloCFD':
                  $cfdi_complementoSelloCFD = $vComplemento["content"];
                  break;
                case 'SelloSAT':
                  $cfdi_complementoSelloSAT = $vComplemento["content"];
                  break;
                default:
                  //--
                  break;
              }
            }

            $compraUpdate = ComprasModelo::where('token_compras',$vBuy->token_compras)
            ->limit(1)->update(
              array(
                'facturaXml' => file_exists($request->file('imagenEvidenciaXMl')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL,
                'facturaPdf' => file_exists($request->file('imagenEvidenciaPdf')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL,
                'evidenciaSAT' => file_exists($request->file('imagenEvidenciaVerificacion')) ? $JwtAuth->encriptar($fechaSistema."-".$folio_buy.pathinfo($request->file('imagenEvidenciaVerificacion')->getClientOriginalName(), PATHINFO_FILENAME). ".pdf") : NULL,
                'forma_pago' => $cfdi_comprobante_forma_de_pago,
                'metodo_pago' => $cfdi_comprobante_metodo_de_pago,
              )
            );

            $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$obtenCompra.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
            $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales')//cfdi__estructura
            ->insert(array(
              "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
              "origen_proceso" => "compra",
              //"compra_vinculada" => $obtenCompra,
              "cfdi_comprobante_version" => $cfdi_comprobante_version,
              "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
              "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
              "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
              "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
              "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
              "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
              "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
              "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
              "cfdi_comprobante_total" => $cfdi_comprobante_total,
              "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
              "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
              "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
              "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,
              "cfdi_comprobante_sello" => $cfdi_comprobante_sello,
              "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,
              "cfdi_relacionados_tipo_de_relacion" => $cfdi_relacionados_tipo_de_relacion,
              "cfdi_relacionados_uuid" => $cfdi_relacionados_uuid,
              "cfdi_emisor_rfc" => $cfdi_emisor_rfc,
              "cfdi_emisor_nombre" => $cfdi_emisor_nombre,
              "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
              "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
              "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
              "cfdi_complementoUUID" => $cfdi_complementoUUID,
              "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
              "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
              "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,
              "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,
              "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,
            ));

            $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
            $insertCFDIVincBuy = DB::table('cfdi_vinculacion_compras')//cfdi__estructura
            ->insert(array(
              "comprobante_fiscal" => $comprobante_fiscal_reg,
              "compra_vinculada" => $obtenCompra,
            ));

            $nombreDocs = "$fechaSistema-$folio_buy";
            $filepath = $queryEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";

            if (!file_exists(storage_path("/root/" . $filepath))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }

            file_exists($request->file('imagenEvidenciaXMl')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaXMl'),$request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL;
            if (file_exists($request->file('imagenEvidenciaPdf'))) {
              file_exists($request->file('imagenEvidenciaPdf')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL; 
            }
            file_exists($request->file('imagenEvidenciaVerificacion')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName()) : NULL; 

            $contadorDetallecompra = 0;
            for ($i = 0; $i < count($cfdi_conceptos); $i++) { 
              $token_detcompra = $cfdi_conceptos[$i]['token_detcompra'];
              $ident_detcompra = DB::table('eegr_compras_detalle')->where("token_detcompra",$token_detcompra)->value("id");
              $concepto = $cfdi_conceptos[$i]['Descripcion'];
              $conceptoCFDI_referido = $cfdi_conceptos[$i]['conceptoCFDI_referido'];

              //foreach ($conceptoCFDI_referido as $cfdRef) {
              //  echo $conceptoCFDI_referido['NoIdentificacion'];exit;
              //  
              //}
              
              $updtaeDetCompra = DB::table('eegr_compras_detalle')->where("token_detcompra",$token_detcompra)
              ->limit(1)->update(
                array(
                  'concepto_cfdi' => ".".$JwtAuth->encriptar($concepto),
                )
              );

              $contadorReferidos = 0;
              //echo count($conceptoCFDI_referido);exit;
              //foreach ($concepto as $clave => $valor)
              $cfr_NoIdentificacion = $conceptoCFDI_referido['NoIdentificacion'];
              $cfr_ObjetoImp = $conceptoCFDI_referido['ObjetoImp'];
              $cfr_ClaveProdServ = $conceptoCFDI_referido['ClaveProdServ'];
              $cfr_Cantidad = $conceptoCFDI_referido['Cantidad'];
              $cfr_ClaveUnidad = $conceptoCFDI_referido['ClaveUnidad'];
              $cfr_Unidad = $conceptoCFDI_referido['Unidad'];
              $cfr_Descripcion = $conceptoCFDI_referido['Descripcion'];
              $cfr_ValorUnitario = $conceptoCFDI_referido['ValorUnitario'];
              $cfr_Descuento = $conceptoCFDI_referido['Descuento'];
              $cfr_Importe = $conceptoCFDI_referido['Importe'];
              $cfr_TotalRetenciones = $conceptoCFDI_referido['TotalRetenciones'];
              $cfr_articulo_retenciones_modal = $conceptoCFDI_referido['articulo_retenciones_modal'];
              $cfr_cfdi_retenciones = $conceptoCFDI_referido['cfdi_retenciones'];
              $cfr_expandedRowsRetenciones = $conceptoCFDI_referido['expandedRowsRetenciones'];
              $cfr_retenciones_llenadas = $conceptoCFDI_referido['retenciones_llenadas'];
              $cfr_TotalTraslados = $conceptoCFDI_referido['TotalTraslados'];
              $cfr_articulo_traslados_modal = $conceptoCFDI_referido['articulo_traslados_modal'];
              $cfr_cfdi_traslados = $conceptoCFDI_referido['cfdi_traslados']; 
              //[
              //  {
              //    "id": 1,
              //    "Base": "6200.00",
              //    "Impuesto": "002",
              //    "TipoFactor": "Tasa",
              //    "TasaOCuota": "0.160000",
              //    "Importe": "992.00",
              //    "impuesto_relacionado": "",
              //    "impuesto_relacion_nombre": ""
              //  }
              //],
              $cfr_expandedRowsTraslados = $conceptoCFDI_referido['expandedRowsTraslados'];
              $cfr_traslados_llenados = $conceptoCFDI_referido['traslados_llenados'];
              $cfr_Subtotal = $conceptoCFDI_referido['Subtotal'];

              $uuid_cfdi_detalle = Str::uuid()->toString();
              $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
              ->insert(array(
                "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                //"numero_compra" => $obtenCompra,
                "NoIdentificacion" => $cfr_NoIdentificacion,
                "ObjetoImp" => $cfr_ObjetoImp,
                "ClaveProdServ" => $cfr_ClaveProdServ,
                "Cantidad" => $cfr_Cantidad,
                "ClaveUnidad" => $cfr_ClaveUnidad,
                "Unidad" => $cfr_Unidad,
                "Descripcion" => $cfr_Descripcion,
                "ValorUnitario" => floatval(str_replace(['$', ','], '', $cfr_ValorUnitario)),
                "Descuento" => floatval(str_replace(['$', ','], '', $cfr_Descuento)),
                "Importe" => floatval(str_replace(['$', ','], '', $cfr_Importe)),
                "TotalRetenciones" => floatval(str_replace(['$', ','], '', $cfr_TotalRetenciones)),
                "TotalTraslados" => floatval(str_replace(['$', ','], '', $cfr_TotalTraslados)),
                "Subtotal" => floatval(str_replace(['$', ','], '', $cfr_Subtotal)),
                "empresa" => $queryEmp->id
              ));

              if (count($cfr_cfdi_retenciones) > 0) {
                for ($r = 0; $r < count($cfr_cfdi_retenciones); $r++) {
                  $retencion_traslado = "rete";
                  $cfr_Base = $cfr_cfdi_retenciones[$r]["Base"] ? $cfr_cfdi_retenciones[$r]["Base"] : 0.00;
                  $cfr_Impuesto = $cfr_cfdi_retenciones[$r]["Impuesto"] ? $cfr_cfdi_retenciones[$r]["Impuesto"] : 000;
                  $cfr_TipoFactor = $cfr_cfdi_retenciones[$r]["TipoFactor"] ? $cfr_cfdi_retenciones[$r]["TipoFactor"] : NULL;
                  $cfr_TasaOCuota = $cfr_cfdi_retenciones[$r]["TasaOCuota"] ? $cfr_cfdi_retenciones[$r]["TasaOCuota"] : NULL;
                  $cfr_importe = $cfr_cfdi_retenciones[$r]["Importe"] ? $cfr_cfdi_retenciones[$r]["Importe"] : 0.00;

                  $insertDetImpuestoCompra = DB::table('eegr_compras_detalle_impuestos AS imp')
                  ->join("eegr_compras_detalle AS detbuy", "imp.detalle_compra", "detbuy.id")
                  ->where("detbuy.token_detcompra",$token_detcompra)
                  ->limit(1)->update(
                    array(
                      "base" => $cfr_Base,
                      "impuesto" => $cfr_Impuesto,
                      "tipo_factor" => $cfr_TipoFactor,
                      "tasa_cuota" => $cfr_TasaOCuota,
                      "importe" => $cfr_importe,
                    )
                  );

                  $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                  ->insert(array(
                    "uuid_buydet_impuestos" => Str::uuid()->toString(),
                    "numero_compra" => $obtenCompra,	
                    "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                    "retencion_traslado" => $retencion_traslado,
                    "base" => $cfr_Base,
                    "impuesto" => $cfr_Impuesto,
                    "tipoFactor" => $cfr_TipoFactor,
                    "tasaOCuota" => $cfr_TasaOCuota,
                    "importe" => $cfr_importe
                  ));
                }
              }

              if (count($cfr_cfdi_traslados) > 0) {
                for ($tras = 0; $tras < count($cfr_cfdi_traslados); $tras++) {
                  $retencion_traslado = "tras";
                  $tras_Base = $cfr_cfdi_traslados[$tras]["Base"] ? $cfr_cfdi_traslados[$tras]["Base"] : 0.00;
                  $tras_Impuesto = $cfr_cfdi_traslados[$tras]["Impuesto"] ? $cfr_cfdi_traslados[$tras]["Impuesto"] : 000;
                  $tras_TipoFactor = $cfr_cfdi_traslados[$tras]["TipoFactor"] ? $cfr_cfdi_traslados[$tras]["TipoFactor"] : NULL;
                  $tras_TasaOCuota = $cfr_cfdi_traslados[$tras]["TasaOCuota"] ? $cfr_cfdi_traslados[$tras]["TasaOCuota"] : NULL;
                  $tras_importe = $cfr_cfdi_traslados[$tras]["Importe"] ? $cfr_cfdi_traslados[$tras]["Importe"] : 0.00;

                  $insertDetImpuestoCompra = DB::table('eegr_compras_detalle_impuestos AS imp')
                  ->join("eegr_compras_detalle AS detbuy", "imp.detalle_compra", "detbuy.id")
                  ->where("detbuy.token_detcompra",$token_detcompra)
                  ->limit(1)->update(
                    array(
                      "base" => $tras_Base,
                      "impuesto" => $tras_Impuesto,
                      "tipo_factor" => $tras_TipoFactor,
                      "tasa_cuota" => $tras_TasaOCuota,
                      "importe" => $tras_importe,
                    )
                  );

                  $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                  ->insert(array(
                    "uuid_buydet_impuestos" => Str::uuid()->toString(),
                    "numero_compra" => $obtenCompra,	
                    "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                    "retencion_traslado" => $retencion_traslado,
                    "base" => $tras_Base,
                    "impuesto" => $tras_Impuesto,
                    "tipoFactor" => $tras_TipoFactor,
                    "tasaOCuota" => $tras_TasaOCuota,
                    "importe" => $tras_importe
                  ));
                }
              }

              if ($updtaeDetCompra && $insertDetCFDICompra) {
                ++$contadorDetallecompra;
              }
            }
            //echo "1110".$contadorDetallecompra.count($cfdi_conceptos);exit;

            if ($contadorDetallecompra == count($cfdi_conceptos)) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Registro de facturas completado satisfactoriamente',
              );
            } else {
              $dataMensaje = array(
                'code' => 200,
                'status' => 'error',
                'message' => 'Registro de facturas no completado, por favor intente más tarde o comuniquese a soporte',
              );
            }
          }
        } else {
          $mensaje_error_main = '';
          if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
          if (!$validate_cfdi_comprobante) {$mensaje_error_main = 'Error en nodo comprobante de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_emisor) {$mensaje_error_main = 'Error en nodo emisor de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_receptor) {$mensaje_error_main = 'Error en nodo receptor de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_impuestos_retenidos) {$mensaje_error_main = 'Error en impuestos retenidos de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_impuestos_trasladados) {$mensaje_error_main = 'Error en impuestos trasladados de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_complemento) {$mensaje_error_main = 'Error en nodo complemento de su CFDI, verifique su información o comuniquese a soporte';}
          if (!file_exists($request->file('imagenEvidenciaXMl'))) {$mensaje_error_main = 'Debe cargar la factura en formato xml correspondiente a esta compra';}
          if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desgloseCompraActivarAplicaFacturasRecep(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compra = $parametrosArray['token_compra'];

        $updateCompraRecibeFacts = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where('eegr_compras.token_compras',$token_compra)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->limit(1)->update(
          array(
            'eegr_compras.aplica_recepcion_facturas' => 'Sí',
          )
        );

        if ($updateCompraRecibeFacts) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Carga de factura electrónica esta habilitada",
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => "No fue posible habilitar la carga de factura electrónica",
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desgloseCompraDeshabilitarAplicaFacturasRecep(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compra = $parametrosArray['token_compra'];

        $updateCompraRecibeFacts = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where('eegr_compras.token_compras',$token_compra)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->limit(1)->update(
          array(
            'eegr_compras.aplica_recepcion_facturas' => 'No',
          )
        );

        if ($updateCompraRecibeFacts) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Carga de factura electrónica esta deshabilitada",
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => "No fue posible deshabilitar la carga de factura electrónica",
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleComprasAutorizadas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_compras.status_autorizacion' => TRUE,
            'eegr_compras.token_compras' => $parametrosArray['token_compra'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($listaCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

          $proveedor_token = "";
          $proveedor_folio = "";
          $proveedor_nombre = "";
          $proveedor_rfc = "";
    
          $queryBuyProv = DB::table("eegr_compras AS buy")
            ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
    
          foreach ($queryBuyProv as $vProv) {
            $proveedor_token = $vProv->token_cat_proveedores;
            $proveedor_folio = 'PRV-' . $JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : '');
            $proveedor_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
            $proveedor_rfc = $JwtAuth->desencriptar($vProv->rfc);
          }

          if ($vBuy->validaTimerFact == TRUE) {
            $validaTimerFact = true;
            $fechaTimerFact = $vBuy->fechaTimerFact + 86400;
          } else {
            $validaTimerFact = false;
            $fechaTimerFact = NULL;
          }

          $arrayArticulos = array();
          $arrayArticulosRecibidos = array();

          $user_compra = "";
          $queryUserCompra = DB::table("eegr_compras AS buy")
            ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
            ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryUserCompra as $vUser) {
            $user_compra = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }

          $compra_total = 0;
          $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
            ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
            ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'comp.token_compras' => $vBuy->token_compras,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

          foreach ($detalleCompraLista as $vDetBuy) {
            $token_detcompra = $vDetBuy->token_detcompra;
            
            $subtotal = (floatval($vDetBuy->precio_unitario) * floatval($vDetBuy->tipo_de_cambio_detalle_compra)) * $vDetBuy->cantidad;
            $importe_concepto = $subtotal - floatval($vDetBuy->descuento) + floatval($vDetBuy->traslados_total) - floatval($vDetBuy->retenciones_total);
            $compra_total = $compra_total + $importe_concepto;

            $totalDetComp = number_format($subtotal,$moneda_decimales,'.', ',');
            $totalDetCompFormat = number_format($subtotal,$moneda_decimales,'.', ',');

            $format_precio_unitario = number_format($vDetBuy->precio_unitario,$moneda_decimales,'.', ',');
            $format_descuento = number_format($vDetBuy->descuento,$moneda_decimales,'.', ',');
            $format_retenciones = number_format($vDetBuy->retenciones_total,$moneda_decimales,'.', ',');
            $format_traslados = number_format($vDetBuy->traslados_total,$moneda_decimales,'.', ',');

            //impuestos
            $arrayimpuestos_det_buy = array();
            $selectImpDetBuy = DB::table("eegr_compras_detalle_impuestos AS imp_det_buy")
              ->join("eegr_compras_detalle AS detcomp", "imp_det_buy.detalle_compra", "=", "detcomp.id")
              ->where([
                'detcomp.token_detcompra' => $token_detcompra
              ])->get();

            foreach ($selectImpDetBuy as $valImpdet) {
              $eachLinea = array(
                "token_imp_det_buy" => $valImpdet->token_imp_det_buy,
                "retencion_traslado" => $valImpdet->retencion_traslado ? 'Retencion' : 'Traslado',
                "base" => $valImpdet->base,
                "impuesto" => $valImpdet->impuesto,
                "tipo_factor" => $valImpdet->tipo_factor,
                "tasa_cuota" => $valImpdet->tasa_cuota,
                "importe" => $valImpdet->importe,
              );
              $arrayimpuestos_det_buy[] = $eachLinea;
            }

            $fecha_recep = null;
            $folio_recep = null;
            $bool_recept_observaciones = null;
            $bool_recept_validado_por = null;

            $selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.calidad_recepcion,recept.recibe_factura,recept.observaciones,recept.recept_status,
              people.paterno,people.materno,people.nombre FROM eegr_compras_recepcion AS recept JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? 
              AND recept.valida_recept = peo_buy.id AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$parametrosArray['token_compra'], $token_detcompra, $usuario->empresa_token, $usuario->user_token]);

            if (count($selectRecibido) != 0) {
              $fecha_recep = date('d-m-Y', $selectRecibido[0]->fecha_recep);
              $folio_recep = $JwtAuth->generar($selectRecibido[0]->folio_recep);
              $bool_recept_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
              $bool_recept_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);
            }

            //productos
            if ($vDetBuy->producto != NULL) {
              $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_productos AS catprod","detbuy.producto","=","catprod.id")
              ->where([
                'detbuy.token_detcompra' => $vDetBuy->token_detcompra,
                //'detbuy.activo_fijo' => NULL,
                //'detbuy.activo_intangible' => NULL,
              ])->get();

              foreach ($productoList as $vProd) {
                $tipo_articulo = $vProd->activo_fijo == NULL && $vProd->activo_intangible == NULL ? 'producto' : ($vProd->activo_fijo != NULL ? 'activo fijo' : 'activo intangible');

                $folio_prod = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);

                $arrayEachDetalleCompra = array(
                  "token_articulo" => $vProd->token_cat_productos,
                  "tipo_articulo" => $tipo_articulo,
                  "folio_articulo" => $folio_prod,
                  "token_detcompra" => $token_detcompra,
                  "clasificacion" => $JwtAuth->generar($vBuy->clasificacion) . '-' . $JwtAuth->generar($vBuy->folio_genero).'-'.$JwtAuth->generar($vProd->folio_sistema),
                  "articulo" => $JwtAuth->desencriptar($vProd->producto),
                  //"clave" => $servicioList[0]->clave,
                  "cantidad" => $vDetBuy->cantidad,
                  "cantidad_background" => $vDetBuy->cantidad,
                  "precio_unitario" => "$$format_precio_unitario",
                  "descuento" => "$$format_descuento",
                  "total" => $totalDetComp,
                  "totalDetCompFormat" => "$$totalDetCompFormat",
                  "total_retenciones" => "$$format_retenciones",
                  "total_traslados" => "$$format_traslados",
                  "impuestos_det_buy" => $arrayimpuestos_det_buy,
                  "boolRecibido" => true,
                  "fecha_recep" => $fecha_recep,
                  "folio_recep" => $folio_recep,
                  "lo_pedido" => count($selectRecibido) != 0 && $selectRecibido[0]->lo_pedido ? true : false,
                  "llegoTiempo" => count($selectRecibido) != 0 && $selectRecibido[0]->llego_tiempo ? true : false,
                  "buenEstado" => count($selectRecibido) != 0 && $selectRecibido[0]->buen_estado ? true : false,
                  "calidadRecepcion" => count($selectRecibido) != 0 && $selectRecibido[0]->calidad_recepcion ? true : false,
                  "recibe_factura" => count($selectRecibido) != 0 && $selectRecibido[0]->recibe_factura ? true : false,
                  "observaciones" => $bool_recept_observaciones,
                  "checked_recept" => count($selectRecibido) != 0 && $selectRecibido[0]->recept_status ? true : false,
                  "validado_por" => $bool_recept_validado_por,
                  "seleccionado" => false,
                  //"saved_message" => "",
                );
              }
              $arrayArticulos[] = $arrayEachDetalleCompra;

              if (count($selectRecibido) != 0) {
                $arrayArticulosRecibidos[] = $arrayEachDetalleCompra;
              }
            }

            //activos fijos
            //activos intangibles

            //servicios
            if ($vDetBuy->servicio != NULL) {
              $servicioList = DB::table("eegr_compras_detalle AS detbuy")
                ->join("in_egr_catalogo_servicios AS catserv", "detbuy.servicio", "=", "catserv.id")
                ->where(['detbuy.token_detcompra' => $vDetBuy->token_detcompra])->get();

              foreach ($servicioList as $valSrView) {
                $folio_serv = $valSrView->folio_sistema != NULL && $valSrView->folio_sistema != "" ? ('SERV-'.($valSrView->post_folio == NULL ? $JwtAuth->generarFolio($valSrView->folio_sistema) : $JwtAuth->generarFolio($valSrView->folio_sistema).'-'.$valSrView->post_folio)):
                'SERV-TEMP-'.$JwtAuth->generarFolio($valSrView->temps_folio);

                $arrayEachDetalleCompra = array(
                  "numero" => "",
                  "token_detcompra" => $token_detcompra,
                  "token_articulo" => $valSrView->token_cat_servicios,
                  "tipo_articulo" => 'servicio',
                  "folio_articulo" => $folio_serv,
                  "articulo" => $JwtAuth->desencriptar($valSrView->servicio),
                  //"clave" => $servicioList[0]->clave,
                  "cantidad" => $vDetBuy->cantidad,
                  "precio_unitario" => "$$format_precio_unitario",
                  "descuento" => "$$format_descuento",
                  "total" => $totalDetComp,
                  "totalDetCompFormat" => "$$totalDetCompFormat",
                  "total_retenciones" => "$$format_retenciones",
                  "total_traslados" => "$$format_traslados",
                  "impuestos_det_buy" => $arrayimpuestos_det_buy,
                  "boolRecibido" => false,
                  "fecha_recep" => $fecha_recep,
                  "folio_recep" => $folio_recep,
                  "lo_pedido" => false,
                  "llegoTiempo" => false,
                  "buenEstado" => false,
                  "calidadRecepcion" => false,
                  "recibe_factura" => false,
                  "observaciones" => $bool_recept_observaciones,
                  "recept" => null,
                  "checked_recept" => false,
                  "validado_por" => $bool_recept_validado_por,
                  "establecimiento" => "",
                  //"saved" => false,
                  //"saved_message" => "",
                );
                $arrayArticulos[] = $arrayEachDetalleCompra;
                if (count($selectRecibido) != 0) {
                  $arrayArticulosRecibidos[] = $arrayEachDetalleCompra;
                }
              }
            }
          }

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "persCompra" => $user_compra,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            //"nombre_pdf" => $nombre_pdf,
            //"factura_pdf" => $factura_pdf,
            //"factura_xml" => $factura_xml,
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            "proveedor_rfc" => $proveedor_rfc,
            "folio" => $folio_buy,
            "fecha_sistemaCompras" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_altaCompra" => date('d-m-Y H:i:s', $vBuy->fecha_altaCompra),
            "forma_pago" => $vBuy->forma_pago,
            "metodo_pago" => $vBuy->metodo_pago,
            "articulos" => $arrayArticulos,
            "compra_total" => "$".number_format($compra_total,$moneda_decimales,'.', ','),
            "articulosRecibidos" => $arrayArticulosRecibidos,
            "validaTimerFact" => $validaTimerFact,
            "fechaTimerFact" => $fechaTimerFact,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($listaCompras),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //compras pagadas
  public function listaComprasPeriodicasProd(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    date_default_timezone_set($vBuy->zona_horaria);

    $arrayCompras = array();
    $queryProductos = DB::table("in_egr_catalogo_productos AS catprod")
    ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "catprod.periodicidad" => TRUE, 
      "catprod.repeticion_periodo" => 'dia',
      "emp.empresa_token" => $usuario->empresa_token, 
      "users.usuario_token" => $usuario->user_token
    ])
    ->where("catprod.ultima_compra", "<", now()->subDay()->timestamp)
    ->where(function ($a) {
      $a->where("catprod.tipo_periodo",FALSE)
        ->orWhere(function ($b){
          $b->where("catprod.tipo_periodo",TRUE)
          ->where("catprod.fecha_finPeriodo","<",time());
        });
    })->get();
    //var_dump($queryProductos);1744062837 1744145250
    //echo "listaCompras ".count($queryProductos)." ".now()->toDateString();

    return response()->json([
      'compras' => count($queryProductos),
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function listaGeneralComprasPeriodicas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $queryLastBuy = DB::table("eegr_compras_detalle AS detcomp")
        ->select("detcomp.producto", DB::raw("MAX(subcomp.id) AS ultima_buy"))
        ->join("eegr_compras AS subcomp", "detcomp.numero_compra", "=", "subcomp.id")
        ->groupBy("detcomp.producto");
      
        $queryProductos = DB::table("in_egr_catalogo_productos AS catprod")
        ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id","=","empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        
        // Traer última compra real con fecha y token
        ->join("eegr_compras_detalle AS detcomp", "catprod.id", "=", "detcomp.producto")
        ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
            
        // Unir con subconsulta para filtrar solo la última compra
        ->joinSub($queryLastBuy,'lastBuy', function($comp){
          $comp->on("detcomp.producto","=","lastBuy.producto")
              ->on('comp.id','=','lastBuy.ultima_buy');
        })
        ->where([
          "catprod.periodicidad" => TRUE, 
          "emp.empresa_token" => $usuario->empresa_token, 
          "users.usuario_token" => $usuario->user_token
        ])
        ->select(
          "catprod.*",
          "comp.*",
          "emp.*"
        )
        ->get();

        foreach ($queryProductos as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $importe_total_compra = 0;
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
            ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_precio_unitario = $vDet->precio_unitario;
            //echo $det_precio_unitario;
            $det_descuento = $vDet->descuento;
            $det_total_traslados = $vDet->traslados_total;
            $det_total_retenciones = $vDet->retenciones_total;
            $resultante = $det_precio_unitario - $det_descuento + $det_total_traslados - $det_total_retenciones;
            $importe_total_compra = $importe_total_compra + $resultante;
          }

          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

          $proveedor_token = "";
          $proveedor_folio = "";
          $proveedor_nombre = "";
      
          $queryBuyProv = DB::table("eegr_compras AS buy")
            ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
      
          foreach ($queryBuyProv as $vProv) {
            $proveedor_token = $vProv->token_cat_proveedores;
            $proveedor_folio = 'PRV-' . $JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : '');
            $proveedor_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
          }
      
          $user_compra = "";
          $queryUserCompra = DB::table("eegr_compras AS buy")
            ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
            ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryUserCompra as $vUser) {
            $user_compra = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }
      
          $arrayForeach = array(
            //compra
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio(DB::table("eegr_compras")->where("token_compras",$vBuy->token_compras)->value("folio_compra")),
            "fecha_registro_compra" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
            "facturaXml" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            "facturaPdf" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "reporte" => $vBuy->reporte,
            "forma_pago" => $vBuy->forma_pago,
            "metodo_pago" => $vBuy->metodo_pago,
            "recepcionPago" => $vBuy->recepcionPago,
            "recibeProducto" => $vBuy->recibeProducto? 'Antes' : 'Después',
            "compra_a_credito" => $vBuy->compra_a_credito ? 'Crédito' : 'Contado',
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //Producto
            "token_cat_productos" => $vBuy->token_cat_productos,
            "folio_prod" => $vBuy->folio_sistema != NULL && $vBuy->folio_sistema != "" ? ('PROD-' . ($vBuy->post_folio == NULL ? $JwtAuth->generarFolio($vBuy->folio_sistema) : $JwtAuth->generarFolio($vBuy->folio_sistema) . '-' . $vBuy->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vBuy->temps_folio),
            "producto" => $JwtAuth->desencriptar($vBuy->producto),
            "periodicidadCompra" => $vBuy->periodicidad ? "Periodica" : "Eventual",
            "repeticionPeriodo" => $vBuy->repeticion_periodo,
            "tipoPeriodo" => $vBuy->tipo_periodo ? 'deteminado' : 'indeterminado',
            "fechaFinPeriodo" => $vBuy->tipo_periodo ? date('Y-m',$vBuy->fecha_finPeriodo) : '---',
      
            "tipo_variabilidad" => $vBuy->tipo_variabilidad,
            "importe_minimo" => "$".number_format($vBuy->importe_minimo,$moneda_decimales,'.',',')." ".$vBuy->moneda,
            "importe_maximo" => "$".number_format($vBuy->importe_maximo,$moneda_decimales,'.',',')." ".$vBuy->moneda,
      
            //"varImporte" => $vBuy->varImporte,
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            ////recepcion
            //"seleccion_recepcion" => !empty($vBuy->recepcion_prov) ? 'proveedor' : 'establecimiento',
            //"lugar_recepcion_token" => $recepcion_token,
            //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab,
            ////autorizacion 
            //"status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            //"user_autoriza" => $user_autoriza,
            ////"comprador" => $vBuy->comprador,
            //"usuario_comprador" => $user_compra,
            ////productos y servicios
            //"articulos_recibidos" => count($queryDEtailsTotal),
            //"total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ',')." ".$vBuy->moneda,
            ////"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab, 
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($arrayCompras),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //recepcion de productos
  public function habilitaPeridoEspera(Request $request){
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,pers.jerarquia FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
        date_default_timezone_set($selectEmp[0]->zona_horaria);

        $fecha_timer = time();

        $updateTimerFact = ComprasModelo::join("teci_forma_pago AS fPago", "eegr_compras.forma_pago", "=", "fPago.id")
          ->join("teci_metodo_pago AS mPago", "eegr_compras.metodo_pago", "=", "mPago.id")
          ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_compras.status_autorizacion' => TRUE,
            'eegr_compras.token_compras' => $parametrosArray['token_compra'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->limit(1)->update(
            array(
              'eegr_compras.validaTimerFact' => TRUE,
              'eegr_compras.fechaTimerFact' => $fecha_timer,
            )
          );

        if ($updateTimerFact) {
          $dataMensaje = array(
            'message' => 'periodo de espera habilitado con fecha maxima ' . date('d-m-Y H:i:s', ($fecha_timer + 86400)),
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'periodo de espera no habilitado, intente mas tarde o comuniquese a soporte',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function rechazosComprasAutorizadas(Request $request){
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayRechazos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
        'token_detcompra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $selectRechazos = DB::select("SELECT recept.token_rechazo_compra,recept.fecha_rechazo,recept.folio_rechazo,recept.compra,recept.detalle_compra,recept.producto,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,
          recept.calidad_recepcion,recept.observaciones,recept.recept_status,recept.valida_recept,people.paterno,people.materno,people.nombre FROM eegr_compras_rechazo AS recept JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy 
          JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.compra = buy.id AND buy.token_compras = ? 
          AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND recept.valida_recept = peo_buy.id AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
          AND empuser.usuario = users.id AND users.usuario_token = ?",[$parametrosArray['token_compra'],$parametrosArray['token_detcompra'],$usuario->empresa_token,$usuario->user_token]);

        foreach ($selectRechazos as $resSelectRechazos) {
          if ($resSelectRechazos->lo_pedido == TRUE) {
            $rech_lo_pedido = true;
          } else {
            $rech_lo_pedido = false;
          }

          if ($resSelectRechazos->llego_tiempo == TRUE) {
            $rech_llego_tiempo = true;
          } else {
            $rech_llego_tiempo = false;
          }

          if ($resSelectRechazos->buen_estado == TRUE) {
            $rech_buen_estado = true;
          } else {
            $rech_buen_estado = false;
          }

          if ($resSelectRechazos->calidad_recepcion == TRUE) {
            $rech_calidad_recepcion = true;
          } else {
            $rech_calidad_recepcion = false;
          }

          if ($resSelectRechazos->recept_status == TRUE) {
            $rech_recept_status = true;
          } else {
            $rech_recept_status = false;
          }

          $arrayEachRechazo = array(
            "fecha_rechazo" => date('d-m-Y', $resSelectRechazos->fecha_rechazo),
            "folio_rechazo" => $JwtAuth->generar($resSelectRechazos->folio_rechazo),
            "lo_pedido" => $rech_lo_pedido,
            "llegoTiempo" => $rech_llego_tiempo,
            "buenEstado" => $rech_buen_estado,
            "calidadRecepcion" => $rech_calidad_recepcion,
            "observaciones" => $JwtAuth->desencriptar($resSelectRechazos->observaciones),
            "checked_recept" => $rech_recept_status,
            "validado_por" => $JwtAuth->desencriptar($resSelectRechazos->paterno) . " " . $JwtAuth->desencriptar($resSelectRechazos->materno) . " " . $JwtAuth->desencriptar($resSelectRechazos->nombre),                                            //"saved" => false,
            //"saved_message" => "",
          );
          $arrayRechazos[] = $arrayEachRechazo;
        }

        $dataMensaje = array(
          'arrayRechazos' => $arrayRechazos,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recibeProdComprasAlmacen(Request $request){
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $imagenEvidenciaXMl = $request->file('imagenEvidenciaXMl');
    $imagenEvidenciaPdf = $request->file('imagenEvidenciaPdf');

    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
        'productList' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $patrón = '/[aA-zZ_]/';
        $validateRecept = false;
        $validateReceptInsert = false;

        if (isset($parametrosArray['token_compra']) && !empty($parametrosArray['token_compra']) && isset($parametrosArray['productList']) && !empty($parametrosArray['productList'])) {
          $contadorRecept = 0;
          for ($i = 0; $i < count($parametrosArray['productList']); $i++) {
            $producto = $parametrosArray['productList'][$i]['articulo'];
            $token_product = $parametrosArray['productList'][$i]['token_articulo'];
            $token_detcompra = $parametrosArray['productList'][$i]['token_detcompra'];
            $lo_pedido = $parametrosArray['productList'][$i]['lo_pedido'];
            $llegoTiempo = $parametrosArray['productList'][$i]['llegoTiempo'];
            $buenEstado = $parametrosArray['productList'][$i]['buenEstado'];
            $calidadRecepcion = $parametrosArray['productList'][$i]['calidadRecepcion'];
            $observaciones =  $parametrosArray['productList'][$i]['observaciones'];
            $checked_recept = $parametrosArray['productList'][$i]['checked_recept'];
            $establecimiento = $parametrosArray['productList'][$i]['establecimiento'];

            $validate_token_product = isset($token_product) && !empty($token_product);
            $validate_token_detcompra = isset($token_detcompra) && !empty($token_detcompra);
            $validate_lo_pedido = isset($lo_pedido) && is_bool($lo_pedido) === true;
            $validate_llegoTiempo = isset($llegoTiempo) && is_bool($llegoTiempo) === true;
            $validate_buenEstado = isset($buenEstado) && is_bool($buenEstado) === true;
            $validate_calidadRecepcion = isset($calidadRecepcion) && is_bool($calidadRecepcion) === true;
            $validate_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);
            $validate_checked_recept = isset($checked_recept) && is_bool($checked_recept) === true;
            $validate_establecimiento = isset($establecimiento) && !empty($establecimiento);

            if ($validate_token_product && $validate_token_detcompra && $validate_lo_pedido && $validate_llegoTiempo && $validate_buenEstado && $validate_calidadRecepcion && 
              $validate_observaciones && $validate_checked_recept && ((!$checked_recept) || ($checked_recept && $validate_establecimiento))) {
              $contadorRecept++;
            } else {
              $errorAlerta = '';
              if (!$validate_token_product) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';}
              if (!$validate_token_detcompra) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';}
              if (!$validate_lo_pedido) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';}
              if (!$validate_llegoTiempo) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' llegó a tiempo';}
              if (!$validate_buenEstado) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' llegó en buen estado';}
              if (!$validate_calidadRecepcion) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' corresponde a la calidad esperada';}
              if (!$validate_observaciones) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' no tiene observaciones';}
              if (!$validate_checked_recept) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' será recibido';}
              if ($checked_recept && !$validate_establecimiento) {$errorAlerta = 'No seleccionó establecimiento para recepción';}
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => $errorAlerta,
              );
              break;
            }
          }

          if ($contadorRecept == count($parametrosArray['productList'])) {
            $validateRecept = true;
          }

          if ($contadorRecept == count($parametrosArray['productList']) && $validateRecept == true) {
            $contadorReceptInsert = 0;
            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp  
								JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
								AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

            $selectCompras = DB::select("SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp  
								JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
								WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
								AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]);

            for ($i = 0; $i < count($parametrosArray['productList']); $i++) {
              $producto = $parametrosArray['productList'][$i]['articulo'];
              $token_product = $parametrosArray['productList'][$i]['token_articulo'];
              $token_detcompra = $parametrosArray['productList'][$i]['token_detcompra'];
              $lo_pedido = $parametrosArray['productList'][$i]['lo_pedido'];
              $llegoTiempo = $parametrosArray['productList'][$i]['llegoTiempo'];
              $buenEstado = $parametrosArray['productList'][$i]['buenEstado'];
              $calidadRecepcion = $parametrosArray['productList'][$i]['calidadRecepcion'];
              $observaciones =  $parametrosArray['productList'][$i]['observaciones'];
              $checked_recept = $parametrosArray['productList'][$i]['checked_recept'];
              $establecimiento = $parametrosArray['productList'][$i]['establecimiento'];

              $folioRecept = DB::select(
                "SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
									FROM eegr_compras_recepcion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
									JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? 
									AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$usuario->empresa_token, $usuario->user_token]
              );

              $selectCompras = DB::select("SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras FROM eegr_compras AS buy JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token= ?",[$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]);

              $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy JOIN eegr_compras AS buy 
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
                AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$token_detcompra, $parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
              );

              $selectCatProd = DB::select("SELECT catprod.id,catprod.unidad_medida_salida_clave FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN teci_usuarios_catalogo AS users WHERE catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                AND users.usuario_token= ?",[$token_product, $usuario->empresa_token, $usuario->user_token]);//,prodList.medida_entrada,prodList.medida_salida
                //unidad_medida_entrada_clave,unidad_medida_entrada_homologada,unidad_medida_salida_clave,unidad_medida_salida_homologada

              $fecha_registro = time();
              $sql_establecimiento = $establecimiento != '' ?  DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id") : '';
              $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $parametrosArray['token_compra'] . $token_product . $token_detcompra);

              $newReceptBuy = new RecepcionCompraModelo();
              $newReceptBuy->token_recept_compra = $tookenRecept_compra;
              $newReceptBuy->fecha_recep = $fecha_registro;
              $newReceptBuy->folio_recep = $folioRecept[0]->folio;
              $newReceptBuy->compra = $selectCompras[0]->id;
              $newReceptBuy->detalle_compra = $selectComprasDetalle[0]->id;
              $newReceptBuy->producto = $selectCatProd[0]->id;
              $newReceptBuy->activo_fijo = NULL;
              $newReceptBuy->activo_intangible = NULL;
              $newReceptBuy->servicio = NULL;
              $newReceptBuy->lo_pedido = $lo_pedido;
              $newReceptBuy->llego_tiempo = $llegoTiempo;
              $newReceptBuy->buen_estado = $buenEstado;
              $newReceptBuy->calidad_recepcion = $calidadRecepcion;
              $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
              $newReceptBuy->establecimiento = $sql_establecimiento;
              $newReceptBuy->recept_status = $checked_recept;
              $newReceptBuy->valida_recept = $selectEmp[0]->userr;
              $newReceptBuy->empresa = $selectEmp[0]->id;
              $insert_recepcion_compras = $newReceptBuy->save();

              if ($insert_recepcion_compras) {
                if ($checked_recept == TRUE) {
                  $selectRecept = $newReceptBuy->id;

                  $establ = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id");

                  $selectKardex = DB::select(
                    "SELECT dexkar.valor_unitario,dexkar.recibir_cantidad,dexkar.recibir_valor FROM in_egr_productos_kardex AS dexkar 
											JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
											JOIN teci_usuarios_catalogo AS users WHERE dexkar.factura_compra = buy.id 
											AND buy.token_compras = ? AND dexkar.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? 
											AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
											AND empuser.usuario = users.id AND users.usuario_token = ?",
                    [$parametrosArray['token_compra'], $token_detcompra, $usuario->empresa_token, $usuario->user_token]
                  );
                  //echo $selectKardex[0]->recibir_cantidad;
                  $listUpdateKardex = DB::table("in_egr_productos_kardex AS dexkar")
                    ->join("eegr_compras AS buy", "dexkar.factura_compra", "=", "buy.id")
                    ->join("eegr_compras_detalle AS detbuy", "dexkar.detalle_compra", "=", "detbuy.id")
                    ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'detbuy.token_detcompra' => $token_detcompra,
                      'buy.token_compras' => $parametrosArray['token_compra'],
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                      array(
                        'dexkar.entrada_cantidad' => $selectKardex[0]->recibir_cantidad,
                        'dexkar.entrada_valor' => $selectKardex[0]->recibir_valor,
                        'dexkar.recibir_cantidad' => 0,
                        'dexkar.recibir_valor' => 0,
                      )
                    );

                  $tookenAlmDos = $JwtAuth->encriptarToken($tookenRecept_compra, $establ, $selectComprasDetalle[0]->serie, $selectCatProd[0]->id, time());

                  $insertProd = DB::table('in_egr_establecimientos_almacen')
                    ->insert(array(
                      "token_establecimiento_almacen" => $tookenAlmDos,
                      "almacen" => $establ,
                      "producto" => $selectCatProd[0]->id,
                      "activo" => NULL,
                      "nivel_almacen" => 3,
                      "num_serie" => $selectComprasDetalle[0]->serie,
                      "num_lote" => $selectComprasDetalle[0]->lote,
                      "importado" => $selectComprasDetalle[0]->pedimento_aduanal,
                      "existencia" => $selectComprasDetalle[0]->cantidad,
                      "unidad_entrada" => $selectComprasDetalle[0]->unidad_medida,
                      "unidad_salida" => $selectCatProd[0]->unidad_medida_salida_clave,
                      "costo_aplicable" => $selectKardex[0]->valor_unitario,
                      "status_disponibilidad" => TRUE,
                      "recepcion_compra" => $selectRecept,
                      "empresa" => $selectEmp[0]->id,
                    ));

                  if ($listUpdateKardex && $insertProd) {//$listUpdateKardex && $insertProd
                    $contadorReceptInsert++;
                  } else {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => "Registro de recepcion del producto $producto incompleto"
                    );
                    break;
                  }
                } else {
                  $contadorReceptInsert++;
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                );
                break;
              }
            }

            if ($contadorReceptInsert == count($parametrosArray['productList'])) {
              $validateReceptInsert = true;
            } else {
              $validateReceptInsert = false;
            }

            if ($validateReceptInsert == true) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Recepcion / rechazo de compras registrada'
              );
            }
          }
        } else {

          if (!isset($parametrosArray['token_compra']) || empty($parametrosArray['token_compra'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'compra indefinida ó invalida',
            );
          }

          if (!isset($parametrosArray['productList']) || empty($parametrosArray['productList'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'lista de recepción incompleta ó invalida',
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recibeProdComprasAlmacen2(Request $request){
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
        'productList' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $patrón = '/[aA-zZ_]/';
        $validateRecept = false;
        $validateReceptInsert = false;

        if (
          isset($parametrosArray['token_compra']) && !empty($parametrosArray['token_compra']) &&
          isset($parametrosArray['productList']) && !empty($parametrosArray['productList'])
        ) {
          $contadorRecept = 0;
          for ($i = 0; $i < count($parametrosArray['productList']); $i++) {
            $producto = $parametrosArray['productList'][$i]['producto'];
            $token_product = $parametrosArray['productList'][$i]['c_token'];
            $token_detcompra = $parametrosArray['productList'][$i]['token_detcompra'];
            $lo_pedido = $parametrosArray['productList'][$i]['lo_pedido'];
            $llegoTiempo = $parametrosArray['productList'][$i]['llegoTiempo'];
            $buenEstado = $parametrosArray['productList'][$i]['buenEstado'];
            $calidadRecepcion = $parametrosArray['productList'][$i]['calidadRecepcion'];
            $observaciones =  $parametrosArray['productList'][$i]['observaciones'];
            $checked_recept = $parametrosArray['productList'][$i]['checked_recept'];

            if (
              isset($token_product) && !empty($token_product) &&
              isset($token_detcompra) && !empty($token_detcompra) &&
              isset($lo_pedido) && is_bool($lo_pedido) === true &&
              isset($llegoTiempo) && is_bool($llegoTiempo) === true &&
              isset($buenEstado) && is_bool($buenEstado) === true &&
              isset($calidadRecepcion) && is_bool($calidadRecepcion) === true &&
              isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones) &&
              isset($checked_recept) && is_bool($checked_recept) === true
            ) {
              $contadorRecept++;
            } else {
              if (!isset($token_product) || empty($token_product)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($token_detcompra) || empty($token_detcompra)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($lo_pedido) || is_bool($lo_pedido) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($llegoTiempo) || is_bool($llegoTiempo) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' llegó a tiempo',
                );
                break;
              }
              if (!isset($buenEstado) || is_bool($buenEstado) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' llegó en buen estado',
                );
                break;
              }
              if (!isset($calidadRecepcion) || is_bool($calidadRecepcion) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' corresponde a la calidad esperada',
                );
                break;
              }
              if (!isset($observaciones) || empty($observaciones) || !preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' no tiene observaciones',
                );
                break;
              }
              if (!isset($checked_recept) || is_bool($checked_recept) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' será recibido',
                );
                break;
              }
            }
          }

          if ($contadorRecept == count($parametrosArray['productList'])) {
            $validateRecept = true;
          }

          if ($contadorRecept == count($parametrosArray['productList']) && $validateRecept == true) {
            $contadorReceptInsert = 0;
            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp  
								JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

            $selectCompras = DB::select("SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
								AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]);

            for ($i = 0; $i < count($parametrosArray['productList']); $i++) {
              $producto = $parametrosArray['productList'][$i]['producto'];
              $token_product = $parametrosArray['productList'][$i]['c_token'];
              $token_detcompra = $parametrosArray['productList'][$i]['token_detcompra'];
              $lo_pedido = $parametrosArray['productList'][$i]['lo_pedido'];
              $llegoTiempo = $parametrosArray['productList'][$i]['llegoTiempo'];
              $buenEstado = $parametrosArray['productList'][$i]['buenEstado'];
              $calidadRecepcion = $parametrosArray['productList'][$i]['calidadRecepcion'];
              $observaciones =  $parametrosArray['productList'][$i]['observaciones'];
              $checked_recept = $parametrosArray['productList'][$i]['checked_recept'];

              $folioRecept = DB::select("SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
									FROM eegr_compras_recepcion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
									JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

              $selectCompras = DB::select(
                "SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                    JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
									AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
              );

              $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy 
									JOIN eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
									JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
                                    AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token= ?", [$token_detcompra, $parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]);

              $selectCatProd = DB::select(
                "SELECT catprod.id,prodList.medida_entrada,prodList.medida_salida FROM in_egr_catalogo_productos AS catprod 
									JOIN productos AS prodList JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                    WHERE prodList.id = catprod.producto AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
									AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$token_product, $usuario->empresa_token, $usuario->user_token]
              );

              $fecha_registro = time();
              $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $parametrosArray['token_compra'] . $token_product . $token_detcompra);

              if ($checked_recept == TRUE) {
                $newReceptBuy = new RecepcionCompraModelo();
              } else {
                $newReceptBuy = new RechazoCompraModelo();
              }

              if ($checked_recept == TRUE) {
                $newReceptBuy->token_recept_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_recep = $fecha_registro;
                $newReceptBuy->folio_recep = $folioRecept[0]->folio;
              } else {
                $newReceptBuy->token_rechazo_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_rechazo = $fecha_registro;
                $newReceptBuy->folio_rechazo = $folioRecept[0]->folio;
              }

              $newReceptBuy->compra = $selectCompras[0]->id;
              $newReceptBuy->detalle_compra = $selectComprasDetalle[0]->id;
              $newReceptBuy->producto = $selectCatProd[0]->id;
              $newReceptBuy->activo_fijo = NULL;
              $newReceptBuy->activo_intangible = NULL;
              $newReceptBuy->servicio = NULL;
              $newReceptBuy->lo_pedido = $lo_pedido;
              $newReceptBuy->llego_tiempo = $llegoTiempo;
              $newReceptBuy->buen_estado = $buenEstado;
              $newReceptBuy->calidad_recepcion = $calidadRecepcion;
              $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
              $newReceptBuy->recept_status = $checked_recept;
              $newReceptBuy->valida_recept = $selectEmp[0]->userr;
              $newReceptBuy->empresa = $selectEmp[0]->id;
              $insert_recepcion_compras = $newReceptBuy->save();

              if ($insert_recepcion_compras) {
                if ($checked_recept == TRUE) {
                  $selectRecept = DB::select("SELECT id FROM recepcion_compras WHERE token_recept_compra = ?", [$tookenRecept_compra]);

                  $selectRecept = DB::select("SELECT id FROM recepcion_compras WHERE token_recept_compra = ?", [$tookenRecept_compra]);

                  $establ = DB::table("almacen AS alm")
                    ->join("responsables_almacen AS respons", "alm.id", "respons.almacen")
                    ->join("personal AS persnl", "respons.responsable", "persnl.id")
                    ->join("teci_usuarios_catalogo AS users", "persnl.usuario", "users.id")
                    //->where('respons.almacen','alm.id')
                    ->where([
                      "respons.administrador" => $selectEmp[0]->id,
                      'users.usuario_token' => $usuario->user_token
                    ])->get();

                  $selectKardex = DB::select(
                    "SELECT dexkar.valor_unitario FROM in_egr_productos_kardex AS dexkar 
											JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
											JOIN teci_usuarios_catalogo AS users WHERE dexkar.factura_compra = buy.id AND buy.token_compras = ? 
                                            AND dexkar.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
                                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                    [$parametrosArray['token_compra'], $token_detcompra, $usuario->empresa_token, $usuario->user_token]
                  );

                  $listUpdateKardex = DB::table("kardex AS dexkar")
                    ->join("compras AS buy", "dexkar.factura_compra", "=", "buy.id")
                    ->join("eegr_compras_detalle AS detbuy", "dexkar.detalle_compra", "=", "detbuy.id")
                    ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'detbuy.token_detcompra' => $token_detcompra,
                      'buy.token_compras' => $parametrosArray['token_compra'],
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                      array(
                        'dexkar.entrada_cantidad' => 'dexkar.recibir_cantidad',
                        'dexkar.entrada_valor' => 'dexkar.recibir_valor',
                        'dexkar.recibir_cantidad' => 0,
                        'dexkar.recibir_valor' => 0,
                      )
                    );

                  if ($listUpdateKardex) {
                    $tookenAlmDos = $JwtAuth->encriptarToken($tookenRecept_compra, $establ[0]->id, $selectComprasDetalle[0]->serie, $selectCatProd[0]->id, time());

                    $insertProd = DB::table('in_egr_establecimientos_almacen')
                      ->insert(array(
                        "token_establecimiento_almacen" => $tookenAlmDos,
                        "almacen" => $establ[0]->id,
                        "producto" => $selectCatProd[0]->id,
                        "activo" => NULL,
                        "nivel_almacen" => 3,
                        "num_serie" => $selectComprasDetalle[0]->serie,
                        "num_lote" => $selectComprasDetalle[0]->lote,
                        "importado" => $selectComprasDetalle[0]->pedimento_aduanal,
                        "existencia" => $selectComprasDetalle[0]->cantidad,
                        "unidad_entrada" => $selectComprasDetalle[0]->unidad_medida,
                        "unidad_salida" => $selectCatProd[0]->medida_salida,
                        "costo_aplicable" => $selectKardex[0]->valor_unitario,
                        "status_disponibilidad" => TRUE,
                        "empresa" => $selectEmp[0]->id,
                      ));

                    if ($insertProd) {
                      $contadorReceptInsert++;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                      );
                      break;
                    }
                  } else {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 404,
                      'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                    );
                    break;
                  }
                } else {
                  $contadorReceptInsert++;
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                );
                break;
              }
            }

            if ($contadorReceptInsert == count($parametrosArray['productList'])) {
              $validateReceptInsert = true;
            } else {
              $validateReceptInsert = false;
            }

            if ($validateReceptInsert == true) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Recepcion / rechazo de compras registrada'
              );
            }
          }
        } else {

          if (!isset($parametrosArray['token_compra']) || empty($parametrosArray['token_compra'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'compra indefinida ó invalida',
            );
          }

          if (!isset($parametrosArray['productList']) || empty($parametrosArray['productList'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'lista de recepción incompleta ó invalida',
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recibeActivoIntangComprasAlmacen(Request $request){
    $JwtAuth = new \JwtAuth();

    $imagenEvidenciaXMl = $request->file('imagenEvidenciaXMl');
    $imagenEvidenciaPdf = $request->file('imagenEvidenciaPdf');

    $jsonUser = $request->input('dataCompra');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
        'arrayActFijos' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $patrón = '/[aA-zZ_]/';
        $validateRecept = false;
        $validateReceptInsert = false;

        if (
          isset($parametrosArray['token_compra']) && !empty($parametrosArray['token_compra']) &&
          isset($parametrosArray['arrayActFijos']) && !empty($parametrosArray['arrayActFijos'])
        ) {
          $contadorRecept = 0;
          for ($i = 0; $i < count($parametrosArray['arrayActFijos']); $i++) {
            $producto = $parametrosArray['arrayActFijos'][$i]['producto'];
            $token_product = $parametrosArray['arrayActFijos'][$i]['c_token'];
            $token_detcompra = $parametrosArray['arrayActFijos'][$i]['token_detcompra'];
            $lo_pedido = $parametrosArray['arrayActFijos'][$i]['lo_pedido'];
            $llegoTiempo = $parametrosArray['arrayActFijos'][$i]['llegoTiempo'];
            $buenEstado = $parametrosArray['arrayActFijos'][$i]['buenEstado'];
            $calidadRecepcion = $parametrosArray['arrayActFijos'][$i]['calidadRecepcion'];
            $observaciones =  $parametrosArray['arrayActFijos'][$i]['observaciones'];
            $checked_recept = $parametrosArray['arrayActFijos'][$i]['checked_recept'];

            if (
              isset($token_product) && !empty($token_product) &&
              isset($token_detcompra) && !empty($token_detcompra) &&
              isset($lo_pedido) && is_bool($lo_pedido) === true &&
              isset($llegoTiempo) && is_bool($llegoTiempo) === true &&
              isset($buenEstado) && is_bool($buenEstado) === true &&
              isset($calidadRecepcion) && is_bool($calidadRecepcion) === true &&
              isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones) &&
              isset($checked_recept) && is_bool($checked_recept) === true
            ) {
              $contadorRecept++;
            } else {
              if (!isset($token_product) || empty($token_product)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($token_detcompra) || empty($token_detcompra)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($lo_pedido) || is_bool($lo_pedido) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($llegoTiempo) || is_bool($llegoTiempo) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' llegó a tiempo',
                );
                break;
              }
              if (!isset($buenEstado) || is_bool($buenEstado) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' llegó en buen estado',
                );
                break;
              }
              if (!isset($calidadRecepcion) || is_bool($calidadRecepcion) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' corresponde a la calidad esperada',
                );
                break;
              }
              if (!isset($observaciones) || empty($observaciones) || !preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' no tiene observaciones',
                );
                break;
              }
              if (!isset($checked_recept) || is_bool($checked_recept) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el articulo ' . $producto . ' será recibido',
                );
                break;
              }
            }
          }

          if ($contadorRecept == count($parametrosArray['arrayActFijos'])) {
            $validateRecept = true;
          }

          if ($contadorRecept == count($parametrosArray['arrayActFijos']) && $validateRecept == true) {
            $contadorReceptInsert = 0;
            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp  
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

            $selectCompras = DB::select(
              "SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
                                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
              [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
            );

            for ($i = 0; $i < count($parametrosArray['arrayActFijos']); $i++) {
              $producto = $parametrosArray['arrayActFijos'][$i]['producto'];
              $token_product = $parametrosArray['arrayActFijos'][$i]['c_token'];
              $token_act_intang = $parametrosArray['arrayActFijos'][$i]['token_act_intang'];
              $token_detcompra = $parametrosArray['arrayActFijos'][$i]['token_detcompra'];
              $lo_pedido = $parametrosArray['arrayActFijos'][$i]['lo_pedido'];
              $llegoTiempo = $parametrosArray['arrayActFijos'][$i]['llegoTiempo'];
              $buenEstado = $parametrosArray['arrayActFijos'][$i]['buenEstado'];
              $calidadRecepcion = $parametrosArray['arrayActFijos'][$i]['calidadRecepcion'];
              $observaciones =  $parametrosArray['arrayActFijos'][$i]['observaciones'];
              $checked_recept = $parametrosArray['arrayActFijos'][$i]['checked_recept'];

              $folioRecept = DB::select("SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
                                    FROM eegr_compras_recepcion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                    JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

              $selectCompras = DB::select(
                "SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras FROM eegr_compras AS buy JOIN main_empresas AS emp  
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id 
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
              );

              $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal 
                                    FROM eegr_compras_detalle AS detbuy JOIN eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                    JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
                                    AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token= ?", [$token_detcompra, $parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]);

              $selectCatProd = DB::select(
                "SELECT catprod.id,prodList.medida_entrada,prodList.medida_salida FROM in_egr_catalogo_productos AS catprod 
                                    JOIN productos AS prodList JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                    WHERE prodList.id = catprod.producto AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$token_product, $usuario->empresa_token, $usuario->user_token]
              );

              $selectCatActIntang = DB::select("SELECT cat_activo.id FROM activos_intangibles AS cat_activo JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE cat_activo.token_act_intang = ? 
                                    AND cat_activo.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token= ?", [$token_act_intang, $usuario->empresa_token, $usuario->user_token]);

              $fecha_registro = time();
              $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $parametrosArray['token_compra'] . $token_product . $token_detcompra);

              if ($checked_recept == TRUE) {
                $newReceptBuy = new RecepcionCompraModelo();
              } else {
                $newReceptBuy = new RechazoCompraModelo();
              }

              if ($checked_recept == TRUE) {
                $newReceptBuy->token_recept_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_recep = $fecha_registro;
                $newReceptBuy->folio_recep = $folioRecept[0]->folio;
              } else {
                $newReceptBuy->token_rechazo_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_rechazo = $fecha_registro;
                $newReceptBuy->folio_rechazo = $folioRecept[0]->folio;
              }

              $newReceptBuy->compra = $selectCompras[0]->id;
              $newReceptBuy->detalle_compra = $selectComprasDetalle[0]->id;
              $newReceptBuy->producto = $selectCatProd[0]->id;
              $newReceptBuy->activo_fijo = NULL;
              $newReceptBuy->activo_intangible = $selectCatActIntang[0]->id;
              $newReceptBuy->servicio = NULL;
              $newReceptBuy->lo_pedido = $lo_pedido;
              $newReceptBuy->llego_tiempo = $llegoTiempo;
              $newReceptBuy->buen_estado = $buenEstado;
              $newReceptBuy->calidad_recepcion = $calidadRecepcion;
              $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
              $newReceptBuy->recept_status = $checked_recept;
              $newReceptBuy->valida_recept = $selectEmp[0]->userr;
              $newReceptBuy->empresa = $selectEmp[0]->id;
              $insert_recepcion_compras = $newReceptBuy->save();

              if ($insert_recepcion_compras) {

                if ($selectCompras[0]->recibeFactura == FALSE && $selectCompras[0]->facturaXml == '') {
                  $nombreXml = $request->file('imagenEvidenciaXMl')->getClientOriginalName();
                  $nombrePDf = $request->file('imagenEvidenciaPdf')->getClientOriginalName();
                  $nombreDocs = $fechaSistema . "-" . $JwtAuth->generar($selectCompras[0]->folio_compra);
                  $filepath = $selectEmp[0]->root_tkn . "/0002-cpp/compras/compras/" . $nombreDocs . "/";

                  if (!file_exists(storage_path("/root/" . $filepath))) {
                    Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                  }

                  Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenEvidenciaXMl'), $request->file('imagenEvidenciaXMl')->getClientOriginalName());
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenEvidenciaPdf'), $request->file('imagenEvidenciaPdf')->getClientOriginalName());

                  $guardaXmlFact = DB::table("eegr_compras")
                    ->where([
                      'token_compras' => $parametrosArray['token_compra'],
                    ])
                    ->limit(1)->update(
                      array(
                        'recibeFactura' => TRUE,
                        'facturaXml' => $JwtAuth->encriptar($nombreXml),
                        'facturaPdf' => $JwtAuth->encriptar($nombrePDf),
                        'validaTimerFact' => FALSE,
                        'fechaTimerFact' => '',
                      )
                    );
                }

                if ($checked_recept == TRUE) {
                  $selectRecept = DB::select("SELECT id FROM recepcion_compras WHERE token_recept_compra = ?", [$tookenRecept_compra]);

                  $establ = DB::table("almacen AS alm")
                    ->join("responsables_almacen AS respons", "alm.id", "respons.almacen")
                    ->join("personal AS persnl", "respons.responsable", "persnl.id")
                    ->join("teci_usuarios_catalogo AS users", "persnl.usuario", "users.id")
                    //->where('respons.almacen','alm.id')
                    ->where([
                      "respons.administrador" => $selectEmp[0]->id,
                      'users.usuario_token' => $usuario->user_token
                    ])->get();


                  $tookenAlmDos = $JwtAuth->encriptarToken($tookenRecept_compra, $establ[0]->id, $selectComprasDetalle[0]->serie, $selectCatProd[0]->id, time());

                  $insertProd = DB::table('in_egr_establecimientos_almacen')
                    ->insert(array(
                      "token_establecimiento_almacen" => $tookenAlmDos,
                      "almacen" => $establ[0]->id,
                      "producto" => $selectCatProd[0]->id,
                      "activo" => NULL,
                      "nivel_almacen" => 1,
                      "num_serie" => $selectComprasDetalle[0]->serie,
                      "num_lote" => $selectComprasDetalle[0]->lote,
                      "importado" => $selectComprasDetalle[0]->pedimento_aduanal,
                      "existencia" => $selectComprasDetalle[0]->cantidad,
                      "unidad_entrada" => $selectComprasDetalle[0]->unidad_medida,
                      "unidad_salida" => $selectCatProd[0]->medida_salida,
                      "costo_aplicable" => 0,
                      "status_disponibilidad" => TRUE,
                      "recepcion_compra" => $selectRecept[0]->id,
                      "empresa" => $selectEmp[0]->id,
                    ));

                  if ($insertProd) {
                    $contadorReceptInsert++;
                  } else {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Registro de recepcion de1 ' . $producto . ' incompleto'
                    );
                    break;
                  }
                } else {
                  $contadorReceptInsert++;
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                );
                break;
              }
            }

            if ($contadorReceptInsert == count($parametrosArray['arrayActFijos'])) {
              $validateReceptInsert = true;
            } else {
              $validateReceptInsert = false;
            }

            if ($validateReceptInsert == true) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Recepcion / rechazo de compras registrada'
              );
            }
          }
        } else {

          if (!isset($parametrosArray['token_compra']) || empty($parametrosArray['token_compra'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'compra indefinida ó invalida',
            );
          }

          if (!isset($parametrosArray['arrayActFijos']) || empty($parametrosArray['arrayActFijos'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'lista de recepción incompleta ó invalida',
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recibeServComprasAlmacen(Request $request){
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");

    $imagenEvidenciaXMl = $request->file('imagenEvidenciaXMl');
    $imagenEvidenciaPdf = $request->file('imagenEvidenciaPdf');

    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayCompras = array();
    $arrayservicios = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
        'arrayServicios' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $patrón = '/[aA-zZ_]/';
        $validateRecept = false;
        $validateReceptInsert = false;

        if (
          isset($parametrosArray['token_compra']) && !empty($parametrosArray['token_compra']) &&
          isset($parametrosArray['arrayServicios']) && !empty($parametrosArray['arrayServicios'])
        ) {
          $contadorRecept = 0;
          for ($i = 0; $i < count($parametrosArray['arrayServicios']); $i++) {
            $token_detcompra = $parametrosArray['arrayServicios'][$i]['token_detcompra'];
            $token_cat_servicios = $parametrosArray['arrayServicios'][$i]['token_articulo'];
            $servicio = $parametrosArray['arrayServicios'][$i]['articulo'];
            $lo_pedido = $parametrosArray['arrayServicios'][$i]['lo_pedido'];
            $llegoTiempo = $parametrosArray['arrayServicios'][$i]['llegoTiempo'];
            $buenEstado = $parametrosArray['arrayServicios'][$i]['buenEstado'];
            $calidadRecepcion = $parametrosArray['arrayServicios'][$i]['calidadRecepcion'];
            $observaciones =  $parametrosArray['arrayServicios'][$i]['observaciones'];
            $checked_recept = $parametrosArray['arrayServicios'][$i]['checked_recept'];
            //echo $token_cat_servicios;
            if (
              isset($token_detcompra) && !empty($token_detcompra) &&
              isset($token_cat_servicios) && !empty($token_cat_servicios) &&
              isset($lo_pedido) && is_bool($lo_pedido) === true &&
              isset($llegoTiempo) && is_bool($llegoTiempo) === true &&
              isset($buenEstado) && is_bool($buenEstado) === true &&
              isset($calidadRecepcion) && is_bool($calidadRecepcion) === true &&
              isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones) &&
              isset($checked_recept) && is_bool($checked_recept) === true
            ) {
              $contadorRecept++;
            } else {
              if (!isset($token_detcompra) || empty($token_detcompra)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No existe token de detalle de compra para ' . $servicio,
                );
                break;
              }
              if (!isset($token_cat_servicios) || empty($token_cat_servicios)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No existe token de servicio ' . $servicio,
                );
                break;
              }
              if (!isset($lo_pedido) || is_bool($lo_pedido) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el servicio ' . $servicio . ' es lo que ha pedido',
                );
                break;
              }
              if (!isset($llegoTiempo) || is_bool($llegoTiempo) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el servicio ' . $servicio . ' llegó a tiempo',
                );
                break;
              }
              if (!isset($buenEstado) || is_bool($buenEstado) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el servicio ' . $servicio . ' llegó en buen estado',
                );
                break;
              }
              if (!isset($calidadRecepcion) || is_bool($calidadRecepcion) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el servicio ' . $servicio . ' corresponde a la calidad esperada',
                );
                break;
              }
              if (!isset($observaciones) || empty($observaciones) || !preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el servicio ' . $servicio . ' no tiene observaciones',
                );
                break;
              }
              if (!isset($checked_recept) || is_bool($checked_recept) === false) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'No seleccionó si el servicio ' . $servicio . ' será recibido',
                );
                break;
              }
            }
          }

          if ($contadorRecept == count($parametrosArray['arrayServicios'])) {
            $validateRecept = true;
          }

          if ($contadorRecept == count($parametrosArray['arrayServicios']) && $validateRecept == true) {
            $contadorReceptInsert = 0;
            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp  
								JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
								AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

            $selectCompras = DB::select(
              "SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id 
                                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
              [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
            );

            for ($i = 0; $i < count($parametrosArray['arrayServicios']); $i++) {
              $token_detcompra = $parametrosArray['arrayServicios'][$i]['token_detcompra'];
              $token_cat_servicios = $parametrosArray['arrayServicios'][$i]['token_articulo'];
              $servicio = $parametrosArray['arrayServicios'][$i]['articulo'];

              $lo_pedido = $parametrosArray['arrayServicios'][$i]['lo_pedido'];
              $llegoTiempo = $parametrosArray['arrayServicios'][$i]['llegoTiempo'];
              $buenEstado = $parametrosArray['arrayServicios'][$i]['buenEstado'];
              $calidadRecepcion = $parametrosArray['arrayServicios'][$i]['calidadRecepcion'];
              $observaciones =  $parametrosArray['arrayServicios'][$i]['observaciones'];
              $checked_recept = $parametrosArray['arrayServicios'][$i]['checked_recept'];

              $folioRecept = DB::select("SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
									FROM eegr_compras_recepcion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
									JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

              $folioRechazo = DB::select("SELECT IF (max(recept.folio_rechazo) IS NOT NULL,(max(recept.folio_rechazo)+1),1) AS folio
									FROM eegr_compras_rechazo AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                    WHERE recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

              $selectCompras = DB::select(
                "SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras 
                                    FROM eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                    WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
              );

              $selectComprasDetalle = DB::select(
                "SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy 
									JOIN eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                    WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? AND buy.comprador = emp.id 
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$token_detcompra, $parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
              );

              $selectcatserv = DB::select(
                "SELECT catserv.id FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                                    WHERE catserv.token_cat_servicios = ? AND catserv.administrador = emp.id AND emp.empresa_token = ? 
									AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$token_cat_servicios, $usuario->empresa_token, $usuario->user_token]
              );

              $fecha_registro = time();
              $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $parametrosArray['token_compra'] . $token_cat_servicios . $token_detcompra);
              //echo $checked_recept;
              if ($checked_recept == TRUE) {
                $newReceptBuy = new RecepcionCompraModelo();
              } else {
                $newReceptBuy = new RechazoCompraModelo();
              }

              if ($checked_recept == TRUE) {
                $newReceptBuy->token_recept_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_recep = $fecha_registro;
                $newReceptBuy->folio_recep = $folioRecept[0]->folio;
              } else {
                $newReceptBuy->token_rechazo_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_rechazo = $fecha_registro;
                $newReceptBuy->folio_rechazo = $folioRechazo[0]->folio;
              }

              $newReceptBuy->compra = $selectCompras[0]->id;
              $newReceptBuy->detalle_compra = $selectComprasDetalle[0]->id;
              $newReceptBuy->servicio = $selectcatserv[0]->id;
              $newReceptBuy->activo_fijo = NULL;
              $newReceptBuy->activo_intangible = NULL;
              $newReceptBuy->servicio = NULL;
              $newReceptBuy->lo_pedido = $lo_pedido;
              $newReceptBuy->llego_tiempo = $llegoTiempo;
              $newReceptBuy->buen_estado = $buenEstado;
              $newReceptBuy->calidad_recepcion = $calidadRecepcion;
              $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
              $newReceptBuy->recept_status = $checked_recept;
              $newReceptBuy->valida_recept = $selectEmp[0]->userr;
              $newReceptBuy->empresa = $selectEmp[0]->id;
              $insert_recepcion_compras = $newReceptBuy->save();

              if ($insert_recepcion_compras) {

                /*if ($selectCompras[0]->recibeFactura == FALSE && $selectCompras[0]->facturaXml == '') {
								        $nombreXml = $request->file('imagenEvidenciaXMl')->getClientOriginalName();
								        $nombrePDf = $request->file('imagenEvidenciaPdf')->getClientOriginalName();
								        $nombreDocs = $fechaSistema."-".$JwtAuth->generar($selectCompras[0]->folio_compra);
								        $filepath = $selectEmp[0]->root_tkn."/0002-cpp/compras/compras/".$nombreDocs."/";
								        
								        if (!file_exists(storage_path("/root/".$filepath))) {
								            Storage::disk('root')->makeDirectory($filepath,0777, true, true);
								        } 
                                        
                                        Storage::putFileAs("/public/root/".$filepath,$request->file('imagenEvidenciaXMl'),$request->file('imagenEvidenciaXMl')->getClientOriginalName());
								        Storage::putFileAs("/public/root/".$filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName());
								        
    								    $guardaXmlFact = DB::table("eegr_compras")
			    					    ->where([
				    				    	'token_compras' => $parametrosArray['token_compra'],
						    		    ])
						    		    ->limit(1)->update(
							    	    	array(
							    	    		'recibeFactura' => TRUE,
							    	    		'facturaXml' => $JwtAuth->encriptar($nombreXml),
								        		'facturaPdf' => $JwtAuth->encriptar($nombrePDf),
								        		'validaTimerFact' => FALSE,
								        		'fechaTimerFact' => '',
									    	)
									    ); 
								    }*/

                $contadorReceptInsert++;
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Registro de recepcion de ' . $servicio . ' incompleto'
                );
                break;
              }
            }

            if ($contadorReceptInsert == count($parametrosArray['arrayServicios'])) {
              $validateReceptInsert = true;
            } else {
              $validateReceptInsert = false;
            }

            if ($validateReceptInsert == true) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Recepcion / rechazo de compras registrada'
              );
            }
          }
        } else {

          if (!isset($parametrosArray['token_compra']) || empty($parametrosArray['token_compra'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'compra indefinida ó invalida',
            );
          }

          if (!isset($parametrosArray['arrayServicios']) || empty($parametrosArray['arrayServicios'])) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'lista de recepción incompleta ó invalida',
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaComprasRecepciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        date_default_timezone_set($selectEmp[0]->zona_horaria);

        $listaRecepciones = RecepcionCompraModelo::join("eegr_compras AS buy", "eegr_compras_recepcion.compra", "=", "buy.id")
          ->join("eegr_compras_detalle AS detbuy", "eegr_compras_recepcion.detalle_compra", "=", "detbuy.id")
          ->join("vhum_empleados_catalogo AS pers_r", "eegr_compras_recepcion.valida_recept", "=", "pers_r.id")
          ->join("sos_personas AS people_r", "pers_r.empleado_name", "=", "people_r.id")
          ->join("main_empresas AS emp", "eegr_compras_recepcion.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_compras_recepcion.recept_status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        //echo count($listaRecepciones);
        foreach ($listaRecepciones as $vRec) {
          $articulo_tipo = '';
          $articulo_folio = '';
          $articulo_name = '';
          if ($vRec->producto != NULL) {
            $articulo_tipo = 'producto';
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_productos AS catprod", "detbuy.producto", "=", "catprod.id")
              ->where(['detbuy.token_detcompra' => $vRec->token_detcompra])->get();
            //echo $value->servicio; 
            foreach ($productoList as $vProd) {
              $articulo_folio = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);

              $articulo_name = $JwtAuth->desencriptar($vProd->producto);
            }
          }

          if ($vRec->activo_fijo != NULL) {
            $articulo_tipo = 'activo_fijo';
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("eegr_activos_fijos_catalogo AS act_fijo", "detbuy.activo_fijo", "=", "act_fijo.id")
              ->where(['detbuy.token_detcompra' => $vRec->token_detcompra])->get();
            //echo $value->servicio; 
            foreach ($productoList as $vActivos) {
              $articulo_folio = "ACTF-" . $JwtAuth->generarFolio($vActivos->folio_activo);
              switch ($vActivos->categoria) {
                case 'act_cat_1':
                  $articulo_name = 'Terrenos y edificios industriales';
                  break;
                case 'act_cat_2':
                  $articulo_name = 'Maquinaria y equipo de producción';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Vehículos de transporte y distribución<';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Mobiliario y enseres industriales';
                  break;
                case 'act_cat_5':
                  $articulo_name = 'Equipos de control de calidad y laboratorio';
                  break;
                case 'act_cat_6':
                  $articulo_name = 'Sistemas de energía y utilidades';
                  break;
                case 'act_cat_7':
                  $articulo_name = 'Activos intangibles relacionados con la producción';
                  break;
                default:
                  $articulo_name = null;
                  break;
              }
            }
          }

          if ($vRec->activo_intangible != NULL) {
            $articulo_tipo = 'activo_intangible';
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("eegr_activos_intangibles_catalogo AS act_intang", "detbuy.activo_intangible", "=", "act_intang.id")
              ->where(['detbuy.token_detcompra' => $vRec->token_detcompra])->get();
            //echo $value->servicio; 
            foreach ($productoList as $vActivos) {
              $articulo_folio = "ACTI-" . $JwtAuth->generarFolio($vActivos->folio_activo);
              switch ($vActivos->categoria) {
                case 'act_cat_1':
                  $articulo_name = 'Marcas comerciales y nombres de dominio';
                  break;
                case 'act_cat_2':
                  $articulo_name = 'Patentes, derechos de autor y secretos comerciales';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Software y tecnología';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Contratos y acuerdos comerciales';
                  break;
                case 'act_cat_5':
                  $articulo_name = 'Relaciones con clientes y proveedores';
                  break;
                case 'act_cat_6':
                  $articulo_name = 'Conocimiento y habilidades especializadas de los empleados';
                  break;
                case 'act_cat_7':
                  $articulo_name = 'Reputación de la marca y prestigio de la empresa';
                  break;
                case 'act_cat_8':
                  $articulo_name = 'Derechos de explotación de franquicias y licencias';
                  break;
                default:
                  $articulo_name = null;
                  break;
              }
            }
          }

          if ($vRec->servicio != NULL) {
            $articulo_tipo = 'servicio';
            $servicioList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_servicios AS catserv", "detbuy.servicio", "=", "catserv.id")
              ->where([
                'detbuy.token_detcompra' => $vRec->token_detcompra,
              ])->get();
            //echo $value->servicio; 
            foreach ($servicioList as $vServ) {
              $articulo_folio = $vServ->folio_sistema != NULL && $vServ->folio_sistema != "" ? ('SERV-'.($vServ->post_folio == NULL ? $JwtAuth->generarFolio($vServ->folio_sistema) : $JwtAuth->generarFolio($vServ->folio_sistema).'-'.$vServ->post_folio)):
              'SERV-TEMP-'.$JwtAuth->generarFolio($vServ->temps_folio);

              $articulo_name = $JwtAuth->desencriptar($vServ->servicio);
            }
          }

          $establecimiento_folio = '';
          $establecimiento_alias = '';
          $servicioList = DB::table("eegr_compras_recepcion AS recept")
            ->join("in_egr_establecimientos_catalogo AS estab", "recept.establecimiento", "=", "estab.id")
            ->where(["recept.token_recept_compra" => $vRec->token_recept_compra])->get();
          //echo $value->servicio; 
          foreach ($servicioList as $vEstab) {
            $establecimiento_folio = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '');
            $establecimiento_alias = $JwtAuth->desencriptar($vEstab->alias_establecimiento);
          }

          $folio_comp = "COMP-".$JwtAuth->generarFolio($vRec->folio_compra).($vRec->post_folio != NULL ? '-'.$vRec->post_folio : '');

          $arrayForeach = array(
            "token_recept_compra" => $vRec->token_recept_compra,
            "fecha_recep" => date('d-m-Y H:i:s', $vRec->fecha_recep),
            "folio_recep" => "RECEPT-".$JwtAuth->generarFolio($vRec->folio_recep),
            "compra" => $folio_comp,
            "articulo_tipo" => $articulo_tipo,
            "articulo_folio" => $articulo_folio,
            "articulo_name" => $articulo_name,
            "lo_pedido" => $vRec->lo_pedido ? true : false,
            "llego_tiempo" => $vRec->llego_tiempo ? true : false,
            "buen_estado" => $vRec->buen_estado ? true : false,
            "calidad_recepcion" => $vRec->calidad_recepcion ? true : false,
            "observaciones" => $JwtAuth->desencriptar($vRec->observaciones),
            "establecimiento" => $establecimiento_folio." ".$establecimiento_alias,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($listaRecepciones),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaComprasRechazos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        date_default_timezone_set($selectEmp[0]->zona_horaria);

        $listaRecepciones = RechazoCompraModelo::join("eegr_compras AS buy", "eegr_compras_rechazo.compra", "=", "buy.id")
          ->join("eegr_compras_detalle AS detbuy", "eegr_compras_rechazo.detalle_compra", "=", "detbuy.id")
          ->join("vhum_empleados_catalogo AS pers_r", "eegr_compras_rechazo.valida_recept", "=", "pers_r.id")
          ->join("sos_personas AS people_r", "pers_r.empleado_name", "=", "people_r.id")
          ->join("main_empresas AS emp", "eegr_compras_rechazo.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_compras_rechazo.recept_status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        //echo count($listaRecepciones);
        foreach ($listaRecepciones as $vRec) {
          $articulo_tipo = '';
          $articulo_folio = '';
          $articulo_name = '';
          if ($vRec->producto != NULL) {
            $articulo_tipo = 'producto';
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_productos AS catprod", "detbuy.producto", "=", "catprod.id")
              ->where(['detbuy.token_detcompra' => $vRec->token_detcompra])->get();
            //echo $value->servicio; 
            foreach ($productoList as $vProd) {
              $articulo_folio = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);

              $articulo_name = $JwtAuth->desencriptar($vProd->producto);
            }
          }

          if ($vRec->activo_fijo != NULL) {
            $articulo_tipo = 'activo_fijo';
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("eegr_activos_fijos_catalogo AS act_fijo", "detbuy.activo_fijo", "=", "act_fijo.id")
              ->where(['detbuy.token_detcompra' => $vRec->token_detcompra])->get();
            //echo $value->servicio; 
            foreach ($productoList as $vActivos) {
              $articulo_folio = "ACTF-" . $JwtAuth->generarFolio($vActivos->folio_activo);
              switch ($vActivos->categoria) {
                case 'act_cat_1':
                  $articulo_name = 'Terrenos y edificios industriales';
                  break;
                case 'act_cat_2':
                  $articulo_name = 'Maquinaria y equipo de producción';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Vehículos de transporte y distribución<';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Mobiliario y enseres industriales';
                  break;
                case 'act_cat_5':
                  $articulo_name = 'Equipos de control de calidad y laboratorio';
                  break;
                case 'act_cat_6':
                  $articulo_name = 'Sistemas de energía y utilidades';
                  break;
                case 'act_cat_7':
                  $articulo_name = 'Activos intangibles relacionados con la producción';
                  break;
                default:
                  $articulo_name = null;
                  break;
              }
            }
          }

          if ($vRec->activo_intangible != NULL) {
            $articulo_tipo = 'activo_intangible';
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("eegr_activos_intangibles_catalogo AS act_intang", "detbuy.activo_intangible", "=", "act_intang.id")
              ->where(['detbuy.token_detcompra' => $vRec->token_detcompra])->get();
            //echo $value->servicio; 
            foreach ($productoList as $vActivos) {
              $articulo_folio = "ACTI-" . $JwtAuth->generarFolio($vActivos->folio_activo);
              switch ($vActivos->categoria) {
                case 'act_cat_1':
                  $articulo_name = 'Marcas comerciales y nombres de dominio';
                  break;
                case 'act_cat_2':
                  $articulo_name = 'Patentes, derechos de autor y secretos comerciales';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Software y tecnología';
                  break;
                case 'act_cat_3':
                  $articulo_name = 'Contratos y acuerdos comerciales';
                  break;
                case 'act_cat_5':
                  $articulo_name = 'Relaciones con clientes y proveedores';
                  break;
                case 'act_cat_6':
                  $articulo_name = 'Conocimiento y habilidades especializadas de los empleados';
                  break;
                case 'act_cat_7':
                  $articulo_name = 'Reputación de la marca y prestigio de la empresa';
                  break;
                case 'act_cat_8':
                  $articulo_name = 'Derechos de explotación de franquicias y licencias';
                  break;
                default:
                  $articulo_name = null;
                  break;
              }
            }
          }

          if ($vRec->servicio != NULL) {
            $articulo_tipo = 'servicio';
            $servicioList = DB::table("eegr_compras_detalle AS detbuy")
              ->join("in_egr_catalogo_servicios AS catserv", "detbuy.servicio", "=", "catserv.id")
              ->where([
                'detbuy.token_detcompra' => $vRec->token_detcompra,
              ])->get();
            //echo $value->servicio; 
            foreach ($servicioList as $vServ) {
              $articulo_folio = $vServ->folio_sistema != NULL && $vServ->folio_sistema != "" ? ('SERV-'.($vServ->post_folio == NULL ? $JwtAuth->generarFolio($vServ->folio_sistema) : $JwtAuth->generarFolio($vServ->folio_sistema).'-'.$vServ->post_folio)):
              'SERV-TEMP-'.$JwtAuth->generarFolio($vServ->temps_folio);

              $articulo_name = $JwtAuth->desencriptar($vServ->servicio);
            }
          }

          $folio_comp = "COMP-".$JwtAuth->generarFolio($vRec->folio_compra).($vRec->post_folio != NULL ? '-'.$vRec->post_folio : '');

          $arrayForeach = array(
            "token_rechazo_compra" => $vRec->token_rechazo_compra,
            "fecha_rechazo" => date('d-m-Y H:i:s', $vRec->fecha_rechazo),
            "folio_rechazo" => "RECEPT-".$JwtAuth->generarFolio($vRec->folio_rechazo),
            "compra" => $folio_comp,
            "articulo_tipo" => $articulo_tipo,
            "articulo_folio" => $articulo_folio,
            "articulo_name" => $articulo_name,
            "lo_pedido" => $vRec->lo_pedido ? true : false,
            "llego_tiempo" => $vRec->llego_tiempo ? true : false,
            "buen_estado" => $vRec->buen_estado ? true : false,
            "calidad_recepcion" => $vRec->calidad_recepcion ? true : false,
            "observaciones" => $JwtAuth->desencriptar($vRec->observaciones),
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($listaRecepciones),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //devengación de servicios
  public function detalleComprasDevengServ(Request $request){
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_compras.status_autorizacion' => TRUE,
            'eegr_compras.token_compras' => $parametrosArray['token_compra'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($listaCompras as $vBuy) {
          date_default_timezone_set($vBuy->zona_horaria);
          $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

          $proveedor_token = "";
          $proveedor_folio = "";
          $proveedor_nombre = "";
          $proveedor_rfc = "";
    
          $queryBuyProv = DB::table("eegr_compras AS buy")
            ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
    
          foreach ($queryBuyProv as $vProv) {
            $proveedor_token = $vProv->token_cat_proveedores;
            $proveedor_folio = 'PRV-' . $JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : '');
            $proveedor_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
            $proveedor_rfc = $JwtAuth->desencriptar($vProv->rfc);
          }

          if ($vBuy->validaTimerFact == TRUE) {
            $validaTimerFact = true;
            $fechaTimerFact = $vBuy->fechaTimerFact + 86400;
          } else {
            $validaTimerFact = false;
            $fechaTimerFact = NULL;
          }

          $arrayservicios = array();
          $arrayserviciosRecibidos = array();

          $user_compra = "";
          $queryUserCompra = DB::table("eegr_compras AS buy")
            ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
            ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryUserCompra as $vUser) {
            $user_compra = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }

          $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
            ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
            ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'comp.token_compras' => $vBuy->token_compras,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

          foreach ($detalleCompraLista as $vDetBuy) {
            $token_detcompra = $vDetBuy->token_detcompra;
            $totalDetComp = DB::select("SELECT TRUNCATE(SUM(precio_unitario*cantidad) - SUM(descuento*cantidad),?) AS total FROM eegr_compras_detalle WHERE token_detcompra = ?", [$moneda_decimales, $token_detcompra]);
            $totalDetCompFormat = DB::select("SELECT FORMAT(SUM(precio_unitario*cantidad) - SUM(descuento*cantidad),?) AS total FROM eegr_compras_detalle WHERE token_detcompra = ?", [$moneda_decimales, $token_detcompra]);

            $formatPuRetTras = DB::select("SELECT FORMAT(?,?) AS formatPunit,FORMAT(?,?) AS formatDescuento,FORMAT(?,?) AS formatRetenc,FORMAT(?,?) AS formatTraslad",
            [$vDetBuy->precio_unitario,$moneda_decimales,$vDetBuy->descuento,$moneda_decimales,$vDetBuy->retenciones_total,$moneda_decimales,$vDetBuy->traslados_total,$moneda_decimales]);

            //impuestos
            $arrayimpuestos_det_buy = array();
            $selectImpDetBuy = DB::table("eegr_compras_detalle_impuestos AS imp_det_buy")
              ->join("eegr_compras_detalle AS detcomp", "imp_det_buy.detalle_compra", "=", "detcomp.id")
              ->where([
                'detcomp.token_detcompra' => $token_detcompra
              ])->get();

            foreach ($selectImpDetBuy as $valImpdet) {
              $eachLinea = array(
                "token_imp_det_buy" => $valImpdet->token_imp_det_buy,
                "retencion_traslado" => $valImpdet->retencion_traslado ? 'Retencion' : 'Traslado',
                "base" => $valImpdet->base,
                "impuesto" => $valImpdet->impuesto,
                "tipo_factor" => $valImpdet->tipo_factor,
                "tasa_cuota" => $valImpdet->tasa_cuota,
                "importe" => $valImpdet->importe,
              );
              $arrayimpuestos_det_buy[] = $eachLinea;
            }

            $fecha_devengado = "";
            $folio_devengado = "";
            $bool_devengado_observaciones = "";
            $bool_devengado_validado_por = "";

            $selectRecibido = DB::select("SELECT deveng.fecha_devengacion,deveng.folio_devengacion,deveng.observaciones,deveng.devengacion_status,people.paterno,people.materno,people.nombre FROM eegr_compras_devengacion AS deveng 
              JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE deveng.compra = buy.id AND buy.token_compras = ? AND deveng.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND deveng.valida_devengacion = peo_buy.id 
              AND peo_buy.empleado_name = people.id AND deveng.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$parametrosArray['token_compra'], $token_detcompra, $usuario->empresa_token, $usuario->user_token]);

            if (count($selectRecibido) != 0) {
              $fecha_devengado = date('d-m-Y', $selectRecibido[0]->fecha_devengacion);
              $folio_devengado = $JwtAuth->generar($selectRecibido[0]->folio_devengacion);
              $bool_devengado_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
              $bool_devengado_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);
            }

            //servicios
            if ($vDetBuy->servicio != NULL) {
            }

            $servicioList = DB::table("eegr_compras_detalle AS detbuy")
            ->join("in_egr_catalogo_servicios AS catserv", "detbuy.servicio", "=", "catserv.id")
            ->whereNotNull('detbuy.servicio')
            ->where(['detbuy.token_detcompra' => $vDetBuy->token_detcompra])->get();

            foreach ($servicioList as $valSrView) {
              $folio_serv = $valSrView->folio_sistema != NULL && $valSrView->folio_sistema != "" ? ('SERV-'.($valSrView->post_folio == NULL ? $JwtAuth->generarFolio($valSrView->folio_sistema) : $JwtAuth->generarFolio($valSrView->folio_sistema).'-'.$valSrView->post_folio)):
              'SERV-TEMP-'.$JwtAuth->generarFolio($valSrView->temps_folio);

              $arrayEachDetalleCompra = array(
                "numero" => "",
                "token_detcompra" => $token_detcompra,
                "token_articulo" => $valSrView->token_cat_servicios,
                "tipo_articulo" => 'servicio',
                "folio_articulo" => $folio_serv,
                "articulo" => $JwtAuth->desencriptar($valSrView->servicio),
                //"clave" => $servicioList[0]->clave,
                "cantidad" => $vDetBuy->cantidad,
                "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
                "precio_unitario" => "$" . $formatPuRetTras[0]->formatPunit,
                "total" => $totalDetComp[0]->total,
                "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
                "total_retenciones" => "$" . $formatPuRetTras[0]->formatRetenc,
                "total_traslados" => "$" . $formatPuRetTras[0]->formatTraslad,
                "impuestos_det_buy" => $arrayimpuestos_det_buy,
                "boolRecibido" => false,
                "fecha_devengado" => $fecha_devengado,
                "folio_devengado" => $folio_devengado,
                "observaciones" => $bool_devengado_observaciones,
                "checked_recept" => false,
                "validado_por" => $bool_devengado_validado_por,
                //"saved" => false,
                //"saved_message" => "",
              );
              if (count($selectRecibido) != 0) {
                $arrayArticulosRecibidos[] = $arrayEachDetalleCompra;
              } else {
                $arrayArticulos[] = $arrayEachDetalleCompra;
                //for ($r = 0; $r < 1000; $r++){
                //	$arrayArticulos[] = $arrayEachDetalleCompra;
                //}
                //for ($r = 0; $r < count($arrayArticulos); $r++){
                //	$arrayArticulos[$r]['numero'] = $r+1;
                //}
              }
            }
          }

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "persCompra" => $user_compra,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            "folio" => $folio_buy,
            "fecha_sistemaCompras" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_altaCompra" => date('d-m-Y H:i:s', $vBuy->fecha_altaCompra),
            "forma_pago" => $vBuy->forma_pago,
            "metodo_pago" => $vBuy->metodo_pago,
            "articulos" => $arrayArticulos,
            "articulosRecibidos" => $arrayArticulosRecibidos,
            "validaTimerFact" => $validaTimerFact,
            "fechaTimerFact" => $fechaTimerFact,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($listaCompras),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function devengaServicioCompras(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayCompras = array();
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compra' => 'required|string',
        'productList' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $validateRecept = false;
        $validateReceptInsert = false;
        $token_compra = $parametrosArray['token_compra'];
        $productList = $parametrosArray['productList'];

        $validate_token_compra = isset($token_compra) && !empty($token_compra); 
        $validate_productList = isset($productList) && !empty($productList);

        if ($validate_token_compra && $validate_productList) {
          $contadorRecept = 0;
          for ($i = 0; $i < count($productList); $i++) {
            $producto = $productList[$i]['articulo'];
            $token_product = $productList[$i]['token_articulo'];
            $token_detcompra = $productList[$i]['token_detcompra'];
            $fecha_devengado = $productList[$i]['fecha_devengado'];
            $observaciones =  $productList[$i]['observaciones'];
            $checked_recept = $productList[$i]['checked_recept'];

            $validate_token_product = isset($token_product) && !empty($token_product);
            $validate_token_detcompra = isset($token_detcompra) && !empty($token_detcompra);
            $validate_fecha_devengado = isset($fecha_devengado) && !empty($fecha_devengado) && preg_match($JwtAuth->filtroFecha(), $fecha_devengado);
            $validate_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);
            $validate_checked_recept = isset($checked_recept) && is_bool($checked_recept) === true;

            if ($validate_token_product && $validate_token_detcompra && $validate_fecha_devengado && $validate_observaciones && $validate_checked_recept && ((!$checked_recept) || ($checked_recept && $validate_establecimiento))) {
              $contadorRecept++;
            } else {
              $errorAlerta = '';
              if (!$validate_token_product) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';}
              if (!$validate_token_detcompra) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';}
              if (!$validate_fecha_devengado) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' contiene fecha de devengación registrada';}
              if (!$validate_observaciones) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' no tiene observaciones';}
              if (!$validate_checked_recept) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' será recibido';}
              if ($checked_recept && !$validate_establecimiento) {$errorAlerta = 'No seleccionó establecimiento para recepción';}
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => $errorAlerta,
              );
              break;
            }
          }

          if ($contadorRecept == count($parametrosArray['productList'])) {
            $validateRecept = true;
          }

          if ($contadorRecept == count($parametrosArray['productList']) && $validateRecept == true) {
            $contadorReceptInsert = 0;
            $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp  
								JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
								AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
            foreach ($queryEmp as $vEmp) {
              $selectCompras = DB::select("SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp  
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
              WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_compra, $usuario->empresa_token, $usuario->user_token]);

              for ($i = 0; $i < count($productList); $i++) {
                $producto = $productList[$i]['articulo'];
                $token_product = $productList[$i]['token_articulo'];
                $token_detcompra = $productList[$i]['token_detcompra'];
                $fecha_devengado = $productList[$i]['fecha_devengado'];
                $observaciones =  $productList[$i]['observaciones'];
                $checked_recept = $productList[$i]['checked_recept'];
              
                $folioRecept = DB::select(
                  "SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
                    FROM eegr_compras_recepcion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                  [$usuario->empresa_token, $usuario->user_token]
                );
              
                $selectCompras = DB::select("SELECT buy.id,buy.folio_compra,buy.post_folio,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras FROM eegr_compras AS buy JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                  AND empuser.usuario = users.id AND users.usuario_token= ?",[$token_compra, $usuario->empresa_token, $usuario->user_token]);

                $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy JOIN eegr_compras AS buy 
                  JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
                  AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                  [$token_detcompra, $token_compra, $usuario->empresa_token, $usuario->user_token]
                );
                $selectComprasDetalleId = DB::table("eegr_compras_detalle")->where("token_detcompra",$token_detcompra)->value("id");//,prodList.medida_entrada,prodList.medida_salida
              
                $selectServ = DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$token_product)->value("id");//,prodList.medida_entrada,prodList.medida_salida
                  //unidad_medida_entrada_clave,unidad_medida_entrada_homologada,unidad_medida_salida_clave,unidad_medida_salida_homologada
              
                $fecha_registro = time();
                $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $selectCompras[0]->id . $selectServ . $selectComprasDetalleId);
              
                $newReceptBuy = new DevengacionCompraModelo();
                $newReceptBuy->token_devengacion_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_dev_sistema = $fecha_registro;
                $newReceptBuy->folio_devengacion = $folioRecept[0]->folio;
                $newReceptBuy->compra = $selectCompras[0]->id;
                $newReceptBuy->detalle_compra = $selectComprasDetalle[0]->id;
                $newReceptBuy->servicio = $selectServ;
                $newReceptBuy->fecha_devengacion = $JwtAuth->convierteFechaEpoc($fecha_devengado);
                $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
                $newReceptBuy->devengacion_status = $checked_recept;
                $newReceptBuy->valida_devengacion = $vEmp->userr;
                $newReceptBuy->empresa = $vEmp->id;
                $insert_recepcion_compras = $newReceptBuy->save();
              
                if ($insert_recepcion_compras) {
                  $new_dev = $newReceptBuy->id;
                  if (isset($_FILES['docsDevengacionAnexos']) && !empty($_FILES['docsDevengacionAnexos'])) {
                    $fechaSistema = $selectCompras[0]->fecha_sistemaCompras;
                    $folio_buy = 'COMP-'.$JwtAuth->generarFolio($selectCompras[0]->folio_compra).($selectCompras[0]->post_folio != NULL ? '-'.$selectCompras[0]->post_folio:'');
                    $filepath = $vEmp->root_tkn . "/0002-cpp/catalogos/compras/$fechaSistema-$folio_buy/";
                    if (!file_exists(storage_path("/root/" . $filepath))) {
                      Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
                    }
                  
                    $anexo_archivos = $_FILES["docsDevengacionAnexos"];
                    $anexo_archivos_strings = json_decode(json_encode($_FILES["docsDevengacionAnexos"]["name"]));
                    if (count($anexo_archivos_strings) > 0) {
                      for ($i = 0; $i < count($anexo_archivos_strings); $i++) {
                        $docAnexo_temporal = $anexo_archivos["tmp_name"][$i];
                        $docAnexo_name = "anexos/" . $anexo_archivos["name"][$i];
                        $docAnexo_type = $JwtAuth->getExtensionDoc($anexo_archivos["type"][$i]);
                        $docAnexo_tknn = $JwtAuth->encriptarToken($new_dev, $usuario->user_token, $usuario->empresa_token, $docAnexo_name);
                        //$JwtAuth->registraDocsProveedor($new_dev, $docAnexo_tknn, "anex", $docAnexo_name, $docAnexo_type);
                      
                        $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%BUY-EVID%'");
                        $insertEvidenceInf = DB::table('sos_documentos')->insert(
                            array(
                                "token_documento" => $docAnexo_tknn,
                                "fecha_carga" => time(),
                                "modulo" => "proyectos",
                                "folio_modulo" => "BUY-EVID".$select_folio_doc[0]->folio,
                                "tipo_documento" => "file",
                                "nombre_documento" => $JwtAuth->encriptar($docAnexo_name),
                                "extension_documento" => $docAnexo_type,
                                "compra_devengacion" => $new_dev,
                                "status_documento" => TRUE,	
                                "fecha_delete_documento" => NULL,
                            ) 
                        );
                      
                        Storage::putFileAs("/public/root/" . $filepath, $docAnexo_temporal, $docAnexo_name);
                      }
                    }
                  }
                  ++$contadorReceptInsert;
                } else {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                  );
                  break;
                }
              }

              if ($contadorReceptInsert == count($parametrosArray['productList'])) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'Devengación de compras registrada'
                );
              }
            }
          }
        } else {
          $mensaje_error_main = '';
          if (!$validate_token_compra) {$mensaje_error_main = 'compra indefinida ó invalida';}
          if (!$validate_productList) {$mensaje_error_main = 'lista de recepción incompleta ó invalida';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //prorrateos
  //descuentos
  //devoluciones
  public function registrarComprasDevolucion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'tipo_devolucion' => 'required|string',
        'compra' => 'required|string',
        'proveedor' => 'required|string',
        'articulos' => 'required|array',
        'establecimiento' => 'required|string',
        'observaciones' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $tipo_devolucion = $parametrosArray['tipo_devolucion'];
        $compra = $parametrosArray['compra'];
        $proveedor = $parametrosArray['proveedor'];
        $articulos = $parametrosArray['articulos'];
        $establecimiento = $parametrosArray['establecimiento'];
        $observaciones = $parametrosArray['observaciones'];

        $valida_tipo_devolucion = isset($tipo_devolucion) && !empty($tipo_devolucion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_devolucion);
        $valida_compra = isset($compra) && !empty($compra);
        $valida_proveedor = isset($proveedor) && !empty($proveedor);
        $valida_articulo = isset($articulos) && !empty($articulos) && count($articulos) > 0;
        $valida_establecimiento = isset($establecimiento) && !empty($establecimiento);
        $valida_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

        if ($valida_tipo_devolucion && $valida_compra && $valida_proveedor && $valida_articulo && $valida_establecimiento && $valida_observaciones) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
            AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          foreach ($queryEmp as $vEmp) {
            date_default_timezone_set($vEmp->zona_horaria);
            $fecha_registro = time();
            $sql_compras = $tipo_devolucion == 'compra' ? DB::table("eegr_compras")->where('token_compras',$compra)->value("id") : NULL;
            $sql_proveedor = $tipo_devolucion == 'compra' ? DB::table("eegr_catalogo_proveedores")->where('token_cat_proveedores',$proveedor)->value("id") : NULL;
            $sql_establ = $tipo_devolucion == 'compra' ? DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id") : NULL;

            $folioSistema = DB::select("SELECT dev.folio_devolucion+1 AS folio FROM eegr_compras_devoluciones AS dev JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
              WHERE dev.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $post_folio_db = DB::select("SELECT post_folio_devolucion FROM eegr_compras_devoluciones WHERE id = (SELECT Max(dev.id) FROM eegr_compras_devoluciones AS dev JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
              WHERE dev.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token, $usuario->user_token]);
  
            $folio_nuevo = count($folioSistema) == 0 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            //return response()->json(['status' => 'error','code' => 200,'message' => "folio_nuevo $folio_nuevo"]);
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->folio_devolucion);
            $folio_dev = 'DEV-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');

            $tokenDevolucion = $JwtAuth->encriptarToken($fecha_registro,$sql_compras,$sql_proveedor,$sql_establ,$observaciones);

            $insertDevoluciones = DB::table('eegr_compras_devoluciones')
            ->insert(array(
              "token_compras_devoluciones" => $tokenDevolucion,
              "folio_devolucion" => $folio_nuevo,
              "post_folio_devolucion" => $post_folio,
              "fecha_registro_devolucion" => $fecha_registro,
              "tipo_devolucion" => $tipo_devolucion,
              "compra" => $sql_compras,
              "proveedor" => $sql_proveedor,
              "establecimiento" => $sql_establ,
              "observaciones" => $JwtAuth->encriptar($observaciones),
              "devolucion_cancelacion" => FALSE,
              "devolucion_cancelacion_fecha" => NULL,
              "devolucion_authorized" => FALSE,
              "devolucion_authorized_fecha" => NULL,
              "devolucion_authorized_by" => NULL,
              "status_devolucion" => TRUE,
              "fecha_delete_devolucion" => NULL,
              "empresa" => $vEmp->id,
            ));

            if ($insertDevoluciones) {
              $devolucion_vinculada = DB::table('eegr_compras_devoluciones')->where("token_compras_devoluciones",$tokenDevolucion)->value("id");
              $contadorReceptInsert = 0;
              for ($i = 0; $i < count($articulos); $i++) {
                $token_product = $articulos[$i]['token_articulo'];
                $cantidad = $articulos[$i]['cantidad'];
                $token_detcompra = $articulos[$i]['token_detcompra'];
                $lo_pedido = $articulos[$i]['lo_pedido'];
                $llegoTiempo = $articulos[$i]['llegoTiempo'];
                $buenEstado = $articulos[$i]['buenEstado'];
                $calidadRecepcion = $articulos[$i]['calidadRecepcion'];
                $observaciones = $articulos[$i]['observaciones'];
                $checked_recept = $articulos[$i]['checked_recept'];
  
                $folioRecept = DB::select("SELECT IF (max(recept.folio_rechazo) IS NOT NULL,(max(recept.folio_rechazo)+1),1) AS folio FROM eegr_compras_rechazo AS recept JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                  AND users.usuario_token= ?",[$usuario->empresa_token, $usuario->user_token]);
  
                $selectCompras = DB::select("SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras FROM eegr_compras AS buy JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                  AND empuser.usuario = users.id AND users.usuario_token= ?",[$compra, $usuario->empresa_token, $usuario->user_token]);
  
                $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy JOIN eegr_compras AS buy 
                  JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
                  AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                  [$token_detcompra, $compra, $usuario->empresa_token, $usuario->user_token]);
  
                $selectCatProd = DB::select("SELECT catprod.id,catprod.unidad_medida_salida_clave FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                  JOIN teci_usuarios_catalogo AS users WHERE catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                  AND users.usuario_token= ?",[$token_product, $usuario->empresa_token, $usuario->user_token]);//,prodList.medida_entrada,prodList.medida_salida
                  //unidad_medida_entrada_clave,unidad_medida_entrada_homologada,unidad_medida_salida_clave,unidad_medida_salida_homologada
  
                $sql_productos = DB::table("in_egr_catalogo_productos")->where('token_cat_productos',$token_product)->value("id");

                $fecha_registro = time();
                $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $compra . $token_product . $token_detcompra);
  
                $newReceptBuy = new RechazoCompraModelo();
                $newReceptBuy->token_rechazo_compra = $tookenRecept_compra;
                $newReceptBuy->fecha_rechazo = $fecha_registro;
                $newReceptBuy->folio_rechazo = $folioRecept[0]->folio;
                $newReceptBuy->compra = $selectCompras[0]->id;
                $newReceptBuy->detalle_compra = $selectComprasDetalle[0]->id;
                $newReceptBuy->producto = $sql_productos;
                $newReceptBuy->lo_pedido = $lo_pedido;
                $newReceptBuy->llego_tiempo = $llegoTiempo;
                $newReceptBuy->buen_estado = $buenEstado;
                $newReceptBuy->calidad_recepcion = $calidadRecepcion;
                $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
                $newReceptBuy->recept_status = $checked_recept;
                $newReceptBuy->valida_recept = $vEmp->userr;
                $newReceptBuy->empresa = $vEmp->id;
                $insert_recepcion_compras = $newReceptBuy->save();
  
                if ($insert_recepcion_compras) {
                  $rechazo = $newReceptBuy->id;
                  $tokenDevolucionDesglose = $JwtAuth->encriptarToken($rechazo,$fecha_registro,$sql_compras,$sql_productos,$sql_proveedor,$sql_establ,$observaciones);

                  $insertDevDesglose = DB::table('eegr_compras_devoluciones_desglose')
                  ->insert(array(
                    "token_devolucion_desglose" => $tokenDevolucionDesglose,
                    "devolucion_vinculada" => $devolucion_vinculada,
                    "rechazo_vinculado" => $rechazo,
                    "producto" => $sql_productos,
                    "cantidad" => $cantidad,
                  ));

                  if ($insertDevDesglose) {
                    $contadorReceptInsert++;
                  }
                } else {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Registro de recepcion de ' . $producto . ' incompleto'
                  );
                  break;
                }
              }

              if ($contadorReceptInsert == count($articulos)) {
                $validateReceptInsert = true;
              } else {
                $validateReceptInsert = false;
              }
  
              if ($validateReceptInsert == true) {
                $dataMensaje = array(
                  'message' => 'registro de devoluciòn de compra realizado con folio '.$folio_dev,
                  'code' => 200,
                  'status' => 'success'
                );
              }
            } else {
              $dataMensaje = array(
                'message' => 'registro de devoluciòn de compra incompleto, revise su información o comuniquese a soporte',
                'code' => 200,
                'status' => 'error'
              );
            }

          }
        } else {
          $mensaje_error = '';
          if (!$valida_tipo_devolucion) {$mensaje_error = 'Error en tipo de devolución, revise su información o comuniquese a soporte';}
          if (!$valida_compra) {$mensaje_error = 'Error en compra seleccionada, revise su información o comuniquese a soporte';}
          if (!$valida_proveedor) {$mensaje_error = 'Error en proveedor seleccionado, revise su información o comuniquese a soporte';}
          if (!$valida_articulo) {$mensaje_error = 'Error en articulo producto seleccionado, revise su información o comuniquese a soporte';}
          if (!$valida_establecimiento) {$mensaje_error = 'Error en establecimiento seleccionado, revise su información o comuniquese a soporte';}
          if (!$valida_observaciones) {$mensaje_error = 'Error en observaciones, revise su información o comuniquese a soporte';}
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => $mensaje_error,
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaComprasDevoluciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaDevoluciones = ComprasModelo::join("eegr_compras_devoluciones AS dev", "eegr_compras.id", "=", "dev.compra")
        //->join("in_egr_catalogo_productos AS catProd", "dev.producto", "=", "catProd.id")
        ->join("eegr_catalogo_proveedores AS catProv", "dev.proveedor", "=", "catProv.id")
        ->join("in_egr_establecimientos_catalogo AS estab", "dev.establecimiento", "=", "estab.id")
        ->join("main_empresas AS emp", "dev.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'dev.status_devolucion' => TRUE,
          'eegr_compras.status_autorizacion' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        //echo count($listaDevoluciones);
        foreach ($listaDevoluciones as $vDev) {
          date_default_timezone_set($vDev->zona_horaria);
          $proveedor_token = "";
          $proveedor_folio = "";
          $proveedor_nombre = "";
    
          $queryBuyProv = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
            ->where(["catprov.token_cat_proveedores" => $vDev->token_cat_proveedores])->get();
    
          foreach ($queryBuyProv as $vProv) {
            $proveedor_token = $vProv->token_cat_proveedores;
            $proveedor_folio = 'PRV-' . $JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : '');
            $proveedor_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
          }

          $user_compra = "";
          $queryUserCompra = DB::table("eegr_compras AS buy")
            ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
            ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["buy.token_compras" => $vDev->token_compras])->get();
          foreach ($queryUserCompra as $vUser) {
            $user_compra = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }

          $folio_comp = "COMP-".$JwtAuth->generarFolio($vDev->folio_compra).($vDev->post_folio != NULL ? '-'.$vDev->post_folio : '');

          $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo")->where(["token_establecimiento" => $vDev->token_establecimiento,"status_establecimiento" => TRUE])->get();
          foreach ($listaDirAlmacen as $vEstab) {
            $establecimiento_token = $vEstab->token_establecimiento;
            $establecimiento_folio = 'ESTAB-' . $JwtAuth->generarFolio($vEstab->folio_establecimiento) . ($vEstab->post_folio != NULL ? '-' . $vEstab->post_folio : '') . " " . $JwtAuth->desencriptar($vEstab->alias_establecimiento);
          }

          $desgloseDevolucion = array();
          $devArticulosQuery = DB::table("eegr_compras_devoluciones_desglose AS desdev")
          ->join("eegr_compras_devoluciones AS dev", "desdev.devolucion_vinculada", "=", "dev.id")
          ->join("eegr_compras_rechazo AS rech", "desdev.rechazo_vinculado", "=", "rech.id")
          ->join("in_egr_catalogo_productos AS catProd", "desdev.producto", "=", "catProd.id")
          ->where("dev.token_compras_devoluciones",$vDev->token_compras_devoluciones)->get();

          foreach ($devArticulosQuery as $vDetDev) {
            $productoList = DB::table("in_egr_catalogo_productos")->where('token_cat_productos',$vDetDev->token_cat_productos)->get();
            foreach ($productoList as $vProd) {
              $producto = $JwtAuth->desencriptar($vProd->producto);
              $folio_prod = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);
            }

            $rowdDet = array(
              //productos
              "producto_token" => $vDetDev->token_cat_productos,
              "producto_folio" => $folio_prod,
              "producto_nombre" => $producto,
              //rechazos
              "token_rechazo_compra" => $vDetDev->token_rechazo_compra,
              "fecha_rechazo" => date('d-m-Y H:i:s', $vDetDev->fecha_rechazo),
              "folio_rechazo" => "RECEPT-".$JwtAuth->generarFolio($vDetDev->folio_rechazo),
              "lo_pedido" => $vDetDev->lo_pedido ? true : false,
              "llego_tiempo" => $vDetDev->llego_tiempo ? true : false,
              "buen_estado" => $vDetDev->buen_estado ? true : false,
              "calidad_recepcion" => $vDetDev->calidad_recepcion ? true : false,
              "observaciones" => $JwtAuth->desencriptar($vDetDev->observaciones),
            );
            $desgloseDevolucion[] = $rowdDet;
          }

          $arrayForeach = array(
            "token_compras_devoluciones" => $vDev->token_compras_devoluciones,
            "folio_devolucion" => 'DEV-'.$JwtAuth->generarFolio($vDev->folio_devolucion).($vDev->post_folio_devolucion != NULL ? '-'.$vDev->post_folio_devolucion:''),
            "fecha_registro_devolucion" => date('d-m-Y H:i:s', $vDev->fecha_registro_devolucion),
            "token_compras" => $vDev->token_compras,
            "folio_compra" => $folio_comp,
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            //personal
            "user_compra" => $user_compra,
            "establecimiento_token" => $establecimiento_token,
            "establecimiento_folio" => $establecimiento_folio,
            "authorized" => $vDev->devolucion_authorized ? true : false,
            "authorized_fecha" => $vDev->devolucion_authorized ?  date('d-m-Y H:i:s', $vDev->devolucion_authorized_fecha) : '',
            "cancelado" => $vDev->devolucion_cancelacion ? true : false,
            "cancelado_fecha" => $vDev->devolucion_cancelacion ?  date('d-m-Y H:i:s', $vDev->devolucion_cancelacion_fecha) : '',
            //"authorized_by",
            "observaciones" => $JwtAuth->desencriptar($vDev->observaciones),
            "desglose" => $desgloseDevolucion
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($listaDevoluciones),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function autorizarComprasDevolucion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras_devoluciones' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compras_devoluciones = $parametrosArray['token_compras_devoluciones'];

        $valida_token_compras_devoluciones = isset($token_compras_devoluciones) && !empty($token_compras_devoluciones);
        $listaDevoluciones = DB::table("eegr_compras_devoluciones AS dev")
        ->join("main_empresas AS emp", "dev.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'dev.token_compras_devoluciones' => $token_compras_devoluciones,
          'dev.status_devolucion' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();

        if ($valida_token_compras_devoluciones && count($listaDevoluciones) == 1) {
          foreach ($listaDevoluciones as $vDev) {
            $updateDevoluciones = DB::table("eegr_compras_devoluciones")
            ->where('token_compras_devoluciones',$vDev->token_compras_devoluciones)
            ->limit(1)->update(
              array(
                'devolucion_authorized' => TRUE,
                'devolucion_authorized_fecha' => time(),	
                'devolucion_authorized_by' => DB::table("teci_usuarios_catalogo")->where('usuario_token',$usuario->user_token)->value("id"),
              )
            );

            if ($updateDevoluciones) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Devolución autorizada satisfactoriamente',
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Devolución no autorizada, intente más tarde o comuniquese a soporte',
              );
            }
            
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en registro de devolución seleccionado, revise su información o comuniquese a soporte',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cancelarComprasDevoluciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_compras_devoluciones' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_compras_devoluciones = $parametrosArray['token_compras_devoluciones'];

        $valida_token_compras_devoluciones = isset($token_compras_devoluciones) && !empty($token_compras_devoluciones);
        $listaDevoluciones = DB::table("eegr_compras_devoluciones AS dev")
        ->join("main_empresas AS emp", "dev.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'dev.token_compras_devoluciones' => $token_compras_devoluciones,
          'dev.status_devolucion' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();

        if ($valida_token_compras_devoluciones && count($listaDevoluciones) == 1) {
          foreach ($listaDevoluciones as $vDev) {
            $updateDevoluciones = DB::table("eegr_compras_devoluciones")
            ->where('token_compras_devoluciones',$vDev->token_compras_devoluciones)
            ->limit(1)->update(
              array(
                'devolucion_cancelacion' => TRUE,
                'devolucion_cancelacion_fecha' => time(),	
                'devolucion_cancelacion_by' => DB::table("teci_usuarios_catalogo")->where('usuario_token',$usuario->user_token)->value("id"),
              )
            );

            if ($updateDevoluciones) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Devolución cancelada satisfactoriamente',
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Devolución no cancelada, intente más tarde o comuniquese a soporte',
              );
            }
            
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en registro de devolución seleccionado, revise su información o comuniquese a soporte',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}