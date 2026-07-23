<?php

namespace App\Http\Controllers;

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

class EGRE_CancelacionSoliController extends Controller{
  private function eachListaComprasGeneral($listaCompras,$JwtAuth){
    $arrayCompras = array();
    $idCompra = $listaCompras->pluck('token_compras')->filter()->unique()->toArray();
    $idProveedor = $listaCompras->pluck('proveedor')->filter()->unique()->toArray();
    $idUsuarioComprador = $listaCompras->pluck('usuario_comprador')->filter()->unique()->toArray();
    $idAutoriza = $listaCompras->pluck('autoriza')->filter()->unique()->toArray();
    
    $compraProveedorMap = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
    ->whereIn("catprov.id",$idProveedor)
    ->select(
      'catprov.id AS id_catalogo',
      'catprov.token_cat_proveedores',
      'catprov.folio','catprov.post_folio',
      'people.nombre_extendido',
      'people.nombre_com'
    )
    ->get()->keyBy('id_catalogo');
    
    $detailsTotalMap = DB::table("eegr_compras AS buy")
    ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
    ->whereIn('buy.token_compras',$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'detBuy.*'
    )
    ->get()->groupBy('id_compras');

    $usuarioCompradorMap = DB::table("teci_usuarios_catalogo AS users")
    ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
    ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
    ->whereIn("users.id",$idUsuarioComprador)
    ->select(
      'users.id AS id_user',
      'people.paterno',
      'people.materno',
      'people.nombre'
    )
    ->get()->keyBy('id_user');

    $UsuarioAuthorizaMap = DB::table("teci_usuarios_catalogo AS users")
    ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
    ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
    ->whereIn("users.id",$idAutoriza)
    ->select(
      'users.id AS auth_user',
      'people.paterno',
      'people.materno',
      'people.nombre'
    )
    ->get()->keyBy('auth_user');
    
    $mapCFDIEstructura = DB::table("cfdi_comprobantes_fiscales AS cfdi")
    ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
    ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
    ->whereIn('buy.token_compras',$idCompra)
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
    
    $mapDirEstabProveedor = DB::table("eegr_compras AS buy")
    ->join("teci_direcciones AS ubica", "buy.recepcion_prov", "ubica.id")
    ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
    ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
    ->whereIn("buy.token_compras",$idCompra)
    ->whereIn("catprov.token_cat_proveedores",$compraProveedorMap->pluck('token_cat_proveedores')->unique())
    ->select(
      'buy.token_compras AS id_compras',
      'ubica.token_direccion','ubica.pais_code','ubica.colonia_edit','ubica.c_postal_edit',
      'ubica.municipio_edit','ubica.estado_edit','ubica.cod_postalext',
      'catprov.token_cat_proveedores AS token_catalogo',
    )
    ->get()
    ->groupBy(function ($item) {
      return $item->id_compras.'_'.$item->token_catalogo;
    });
    
    $mapDirOurEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
    ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
    ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
    ->where("estab.status_establecimiento",TRUE)
    ->whereIn("buy.token_compras",$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento'
    )
    ->get()->groupBy('id_compras');
    
    $mapRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
    ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
    ->whereIn("buy.token_compras",$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'ordRec.folio_recepcion','ordRec.orden_bloqueada','ordRec.fecha_desbloqueo','ordRec.uuid_orden_recepcion'
    )
    ->get()->groupBy('id_compras');
    
    $mapPagoExists = DB::table("fnzs_pagos_orden AS orden")
    ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
    ->where("orden.status_ordenPago",TRUE)
    ->whereIn("buy.token_compras",$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'orden.token_ordenPago','orden.folio_ordenPago','orden.fecha_contabilizacion_ordenPago',
      'orden.orden_bloqueada','orden.fecha_desbloqueo'
    )
    ->get()->groupBy('id_compras');

    $tokensOrdenesPago = $mapPagoExists->collapse()->pluck('token_ordenPago')->unique()->toArray();
    
    $mapPagosDone = DB::table("fnzs_pagos_pago AS pay")
    ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
    ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
    ->whereIn("order.token_ordenPago",$tokensOrdenesPago)
    ->select(
      'order.token_ordenPago AS ref_token_orden', // Llave para agrupar
      'pay.folio_pagos',
      'pay.fecha_contabilizacion',
      'vinc.orden_pago_monto'
    )
    ->get()
    ->groupBy('ref_token_orden');

    foreach ($listaCompras as $vBuy) {
      //date_default_timezone_set('UTC');
      $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
    
      $queryBuyProv = $compraProveedorMap->get($vBuy->proveedor);
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
    
      $queryDetailsTotal = $detailsTotalMap->get($vBuy->token_compras) ?? collect([]);
      //$queryPagosDone = $pagosDoneMap->get($key_pag_done) ?? collect([]);
      //var_dump($queryDetailsTotal);
      foreach ($queryDetailsTotal as $vDet) {
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

      $queryUserCompra = $usuarioCompradorMap->get($vBuy->usuario_comprador);
      $user_compra = $queryUserCompra ? $JwtAuth->desencriptarNombres($queryUserCompra->paterno, $queryUserCompra->materno, $queryUserCompra->nombre) : '';
    
      $queryUserAuth = $UsuarioAuthorizaMap->get($vBuy->autoriza);
      $user_autoriza = $vBuy->status_autorizacion && $queryUserAuth ? $JwtAuth->desencriptarNombres($queryUserAuth->paterno, $queryUserAuth->materno, $queryUserAuth->nombre) : '';
          
      $queryCFDIEstructura = $mapCFDIEstructura->get($vBuy->token_compras);
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
          $keyKompraProv = $vBuy->token_compras.'_'.$proveedor_token;
          $listaDirProvEstab = $mapDirEstabProveedor->get($keyKompraProv) ?? collect([]);
          foreach ($listaDirProvEstab as $vUbica) {
            $lugarRecepcionTipo = "proveedor";
            $lugarRecepcionToken = $vUbica->token_direccion;
            $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
              $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
          }
        } else {
          $listaDirOurEstab = $mapDirOurEstab->get($vBuy->token_compras) ?? collect([]);
          foreach ($listaDirOurEstab as $vEstab) {
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
      $ordRecepcionExists = $mapRecepcionExists->get($vBuy->token_compras);
      //echo "ordPagoExists $ordPagoExists ";
      foreach ($ordRecepcionExists as $vOrdRec) {
        $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
        $bloqueo_orden_recepcion = $vOrdRec->orden_bloqueada ? true : false;
        $desbloqueo_fecha_orden_recepcion = !$vOrdRec->orden_bloqueada ? $JwtAuth->mostrarUnixAFechaMexico($vOrdRec->fecha_desbloqueo) : '';
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
      $ordPagoExists = $mapPagoExists->get($vBuy->token_compras);
      //echo "ordPagoExists $ordPagoExists ";
      foreach ($ordPagoExists as $vOrdp) {
        $orden_pago_token = $vOrdp->token_ordenPago;
        $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
        $orden_pago_fecha_contabilizacion = !is_null($vOrdp->fecha_contabilizacion_ordenPago) && $vOrdp->fecha_contabilizacion_ordenPago != '' ? gmdate('Y-m-d H:i:s',$vOrdp->fecha_contabilizacion_ordenPago) : "---";
        $orden_pago_bloqueo = $vOrdp->orden_bloqueada ? true : false;
        $orden_pago_desbloqueo_fecha = !$vOrdp->orden_bloqueada ? $JwtAuth->mostrarUnixAFechaMexico($vOrdp->fecha_desbloqueo) : '';
      
        $queryPagosDone = $mapPagosDone->get($vOrdp->token_ordenPago) ?? collect([]);
        foreach ($queryPagosDone as $vPayDone) {
          $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
          $pagos_realizados_fecha_contabilizacion = gmdate('Y-m-d H:i:s',$vPayDone->fecha_contabilizacion);
          $pagos_realizados_total += $vPayDone->orden_pago_monto;
        }
      }
      
      $arrayForeach = array(
        "token_compras" => $vBuy->token_compras,
        "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vBuy->fecha_sistemaCompras),
        "fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vBuy->fecha_contabilizacion),
        //proveedor
        "proveedor_token" => $proveedor_token,
        "proveedor_folio" => $proveedor_folio,
        "proveedor_nombre" => $proveedor_nombre,
        "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
        //credito
        "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
        "fecha_vencimiento" => $JwtAuth->mostrarUnixAFechaMexico($vBuy->fecha_vencimiento),
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
        //"cfdi_documentos" => $vBuy->documentos,
        //recepcion
        "articulos_recibidos" => $total_art_recibidos,
        "total_articulos" => count($queryDetailsTotal),
        "articulos_recibidos_comparativa" => "$total_art_recibidos / ".count($queryDetailsTotal),
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
        //"periodicidadCompra" => $vBuy->periodicidadCompra,
        //"repeticionPeriodo" => $vBuy->repeticionPeriodo,
        //"tipoPeriodo" => $vBuy->tipoPeriodo,
        //"fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
        //"varImporte" => $vBuy->varImporte,
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

  public function listaSolicitudesCancelacion(Request $request){
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
      //date_default_timezone_set('America/Mexico_City');
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
      
      $soliCancelCompras = DB::table("eegr_compra_soli_cancelacion AS buyCanc")
      ->join("main_empresas AS emp","buyCanc.compra_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->where([
        "buyCanc.compra_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'COMPRA' AS tipo_solicitud"),
        "buyCanc.token_cancel_compra AS token_soli",
        "buyCanc.folio_cancel_compra AS folio_soli",
        "buyCanc.compra_cancel AS doc_anterior",
        "buyCanc.fecha_cont_cancel_compra AS fecha_contabilizacion",
        "buyCanc.compra_cancel_observaciones_mov AS observaciones",
        "buyCanc.compra_cancel_realizada AS cancel_realizada"
      ]);
  
      $soliCancelOrdenPago = DB::table("fnzs_orden_pagos_soli_cancelacion AS ordcanc")
      //->join("fnzs_pagos_pago AS pago","ordcanc.pago_cancel","pago.id")
      ->join("main_empresas AS emp","ordcanc.orden_pago_cancel_empresa","emp.id")
      ->join("main_empresa_usuario AS empusers","emp.id","empusers.empresa")
      ->join("teci_usuarios_catalogo AS users","empusers.usuario","users.id")
      ->where([
        "ordcanc.orden_pago_cancel_status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select([
        DB::raw("'ORDEN DE PAGO' AS tipo_solicitud"),
        "ordcanc.token_cancel_soliordp AS token_soli",
        "ordcanc.folio_cancel_soliordp AS folio_soli",
        "ordcanc.orden_pago_cancel AS doc_anterior",
        "ordcanc.fecha_cont_cancel_soliordp AS fecha_contabilizacion",
        "ordcanc.orden_pago_cancel_observaciones_mov AS observaciones",
        "ordcanc.orden_pago_cancel_realizada AS cancel_realizada"
      ]);
      
      $unionCancelSoli = $soliCancelCompras;//->unionAll($soliCancelOrdenPago)->unionAll($soliCancelReemPago);//->unionAll($movimientosCancelados);
  
      $querySoliCancel = DB::table(DB::raw("({$unionCancelSoli->toSql()}) as cancelacion_soli"))
      ->mergeBindings($unionCancelSoli) // Importante para no perder los parámetros del WHERE
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("fecha_contabilizacion", "desc")
      ->get();

      if ($querySoliCancel->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron solicitudes de cancelación registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        $idDocAnterior = $querySoliCancel->pluck('doc_anterior')->filter()->unique()->toArray();
        $compraMap = DB::table('eegr_compras')->whereIn('id',$idDocAnterior)->get()->keyBy('id');
  
        foreach ($querySoliCancel as $cSoli) {
          $buy = $compraMap->get($cSoli->doc_anterior);
          $doc_anterior_token = "";
          $doc_anterior_folio = "";
  
          switch ($cSoli->tipo_solicitud) {
            case 'COMPRA':
              $solicitud_folio = 'COMP-SOLI-CANC-'.$JwtAuth->generarFolio($cSoli->folio_soli);
              $doc_anterior_token = $buy->token_compras;
              $doc_anterior_folio = "COMP-".$JwtAuth->generarFolio($buy->folio_compra).($buy->post_folio != NULL ? '-'.$buy->post_folio : '');
              break;
            default:
              $solicitud_folio = "";
              $doc_anterior_token = "";
              $doc_anterior_folio = "";
              break;
          }
  
          $row_pay = array(
            "cancel_soli_token" => $cSoli->token_soli,
            "tipo_solicitud" => $cSoli->tipo_solicitud,
            "cancel_soli_folio" => $solicitud_folio,
            "doc_anterior_token" => $doc_anterior_token,
            "doc_anterior_folio" => $doc_anterior_folio,
            "cancel_soli_observaciones" => $JwtAuth->desencriptar($cSoli->observaciones),
            "cancel_soli_cancel_realizada" => (bool)$cSoli->cancel_realizada,
            "comentarios_confirma_cancelacion" => "",
            "f_contab_confirma_cancelacion" => ""
          );
          $lista_solicitudes[] = $row_pay;
        }
        $dataMensaje = array("status" => "success", "code" => 200, "solicitudes" => $lista_solicitudes);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

	public function solicitudCancelacionCompra(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_compra' => 'required|string',
      'token_compras' => 'required|string'
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
      $token_cancel_compra = $request->input('token_cancel_compra');
      $token_compras = $request->input('token_compras');

			$queryComprasDone = DB::table("eegr_compras AS buy")
      ->join("eegr_compra_soli_cancelacion AS buyCanc", "buy.id", "=","buyCanc.compra_cancel")
			->join("main_empresas AS emp", "buy.comprador", "emp.id")
			->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
			->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
			->where([
				"buyCanc.token_cancel_compra" => $token_cancel_compra,
				"buy.token_compras" => $token_compras,
				"buy.status_compra" => TRUE,
				"emp.empresa_token" => $empresa,
				"users.usuario_token" => $usuario
			])
      ->select(
        'buyCanc.token_cancel_compra',
        'buyCanc.folio_cancel_compra',
        'buyCanc.fecha_cont_cancel_compra',
        'buyCanc.compra_cancel_observaciones_mov',
        'buy.id As id_compra',
        'buy.*',
        'emp.*'
      )
			->get();

      if ($queryComprasDone->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pagos registrados'
        );
      } else {
        $cOrdPag = DB::table("fnzs_pagos_orden AS orden")
        ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
        ->where("orden.status_ordenPago",TRUE)
        ->where("buy.token_compras",$token_compras)
        ->select('orden.pago_orden_cancelada')
        ->first();
        $orden_pago_cancelada = $cOrdPag ? (bool)$cOrdPag->pago_orden_cancelada : false;
    
        $data_cancel = [];
        foreach ($queryComprasDone as $vPayDone) {
          $solicitud_folio = 'COMP-SOLI-CANC-'.$JwtAuth->generarFolio($vPayDone->folio_cancel_compra);
          $data_cancel[] = [
            "token_cancel_compra" => $vPayDone->token_cancel_compra,
            "folio_cancel_compra" => $solicitud_folio,
            "compra_cancel_observaciones_mov" => $JwtAuth->desencriptar($vPayDone->compra_cancel_observaciones_mov),
          ];
        }

        $data_compras = $this->eachListaComprasGeneral($queryComprasDone,$JwtAuth);

        $dataMensaje = array(
          "status" => "success", 
          "code" => 200, 
          "canceled_orden_pago" => $orden_pago_cancelada,
          "data_cancel" => $data_cancel, 
          "data_compras" => $data_compras
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function confirmarCancelacionCompra(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cancel_compra' => 'required|string',
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
      //date_default_timezone_set('America/Mexico_City');
			$token_cancel_compra = $request->input('token_cancel_compra');
			$fecha_contabilizacion = $request->input("fecha_contabilizacion");
			$comentarios_confi = $request->input('comentarios_confirma_cancelacion');

			$valide_token_cancel_compra = isset($token_cancel_compra) && !empty($token_cancel_compra);
			$valide_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
			$valide_comentarios_confi = isset($comentarios_confi) && !empty($comentarios_confi) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_confi);

			if ($valide_token_cancel_compra && $valide_fecha_contabilizacion && $valide_comentarios_confi) {
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
				->first();
				
				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);

				//date_default_timezone_set($vEmp->zona_horaria);
				$fechaContabilizacionUnix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
				$comentarios_encriptados = $JwtAuth->encriptar($comentarios_confi);

				DB::beginTransaction();
				try {
					$ordenData = DB::table("eegr_compras AS buy")
          ->join("eegr_compra_soli_cancelacion AS buyCanc", "buy.id", "=","buyCanc.compra_cancel")
          ->join("main_empresas AS emp", "buy.comprador", "emp.id")
          ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
          ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
          ->where([
            "buyCanc.token_cancel_compra" => $token_cancel_compra,
            "buy.status_compra" => TRUE,
            "emp.empresa_token" => $empresa,
            "users.usuario_token" => $usuario
          ])
          ->select('buy.id As id_compra','buy.token_compras')
          ->lockForUpdate()->first();

          $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;

          DB::table("eegr_compras")->where("id",$ordenData->id_compra)
          ->update(array(
            "status_cancelacion" => TRUE,
            "cancela" => $user_jerarquia,
            "fecha_cont_cancelacion" => $fechaContabilizacionUnix,
            "compra_comentarios_cancelacion" => $comentarios_encriptados
          ));
          
          DB::table("cfdi_comprobantes_fiscales AS cfdi")
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
          ->where('buy.id',$ordenData->id_compra)
          ->update(array(
            "cfdi_cancelado" => TRUE,
            "cfdi_cancel_user" => $user_jerarquia,
            "cfdi_cancel_fecha_cont" => $fechaContabilizacionUnix,
            "cfdi_cancel_comentarios" => $comentarios_encriptados
          ));

					DB::table("eegr_compra_soli_cancelacion")
					->where("token_cancel_compra",$token_cancel_compra)
					->limit(1)->update(array("compra_cancel_realizada" => TRUE));
	
					DB::commit();
					return response()->json(['status' => 'success', 'message' => 'Cancelación y reversión contable finalizada con éxito'], 200);
				} catch (\Exception $e) {
          DB::rollBack();
          \Log::error("Error al recibir activo: " . $e->getMessage());
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.' . $e->getMessage()], 500);
				}
			} else {
				$mensaje_error = '';
				if (!$valide_token_cancel_compra) $mensaje_error = 'Error en facturas seleccionadas, verifique su información o comuníquese a soporte';
				if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$valide_comentarios_confi) $mensaje_error = 'Error en observaciones finales, verifique su información o comuníquese a soporte';
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}
}
