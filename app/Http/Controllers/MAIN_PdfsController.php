<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\RequisicionesModelo;
use Carbon\Carbon;

class MAIN_PdfsController extends Controller{
  public function visorImagenes(Request $request){
    $JwtAuth = new \JwtAuth();
    $folio = $request->folio;
    $nombre_imagen = $request->nombre_imagen;

    $file = Storage::path('public/posts/' . $folio . '/' . $nombre_imagen);
    $content = file_get_contents($file);
    header("Content-Type: image/jpeg");
    echo $content;
  }

  //compras
  public function verCompraPdfHtml($token_compras){
    $JwtAuth = new \JwtAuth();
    if (isset($token_compras) && !empty($token_compras)) {
      $listaCompras = DB::table("eegr_compras AS buy")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("buy.token_compras",$token_compras)
      ->select(
        "buy.*",
        "emp.root_tkn",
        "people.img_perfil",
        "people.denominacion_rs",
        "people.paterno",
        "people.materno",
        "people.nombre",
        "people.abrev_nombre"
        )
      ->get();
      
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

      foreach ($listaCompras as $vBuy) {
        $folio_compra = "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');

        if ($JwtAuth->desencriptar($vBuy->img_perfil) == "empresa_desconocida.png") {
          $ruta_logo = 'public/settings/empresa_desconocida.png';
        } else {
          $ruta_logo = "public/root/$vBuy->root_tkn/0007-core/".$JwtAuth->desencriptar($vBuy->img_perfil);
        }
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path($ruta_logo));
        
        $nombreEmpresa = $vBuy->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vBuy->paterno, $vBuy->materno, $vBuy->nombre) : $JwtAuth->desencriptar($vBuy->denominacion_rs);
        $name_abrev = $vBuy->abrev_nombre;
        
        $queryBuyProv = $compraProveedorMap->get($vBuy->proveedor);
        $proveedor_token = '';
        $proveedor_folio = '';
        $proveedor_nombre = '';
        if ($queryBuyProv) {
          $proveedor_token = $queryBuyProv->token_cat_proveedores;
          $proveedor_folio = 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '');
          $proveedor_nombre = $JwtAuth->desencriptar($queryBuyProv->nombre_extendido).(!is_null($queryBuyProv->nombre_com) ? ' Nombre comercial: '.$JwtAuth->desencriptar($queryBuyProv->nombre_com) : '');
        }

        $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
        $totales_compra_subtotal = 0;
        $totales_compra_descuento = 0;
        $totales_compra_retenciones = 0;
        $totales_compra_traslados = 0;
        $totales_compra_importe = 0;
        $total_art_recibidos = 0;
        
        $queryDetailsTotal = $detailsTotalMap->get($vBuy->token_compras) ?? collect([]);
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
          $lugarRecepcionDireccion = "";
        } else {
          if (!is_null($vBuy->recepcion_prov) && is_null($vBuy->recepcion_estab)) {
            $keyKompraProv = $vBuy->token_compras.'_'.$proveedor_token;
            $listaDirProvEstab = $mapDirEstabProveedor->get($keyKompraProv) ?? collect([]);
            foreach ($listaDirProvEstab as $vUbica) {
              $lugarRecepcionTipo = "proveedor";
              $lugarRecepcionDireccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
                $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
            }
          } else {
            $listaDirOurEstab = $mapDirOurEstab->get($vBuy->token_compras) ?? collect([]);
            foreach ($listaDirOurEstab as $vEstab) {
              $lugarRecepcionTipo = "Establecimiento";
              $lugarRecepcionDireccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
            }
          }
        }
        
        $folio_orden_recepcion = "";
        $ordRecepcionExists = DB::table("eegr_compras_orden_recepcion AS ordRec")
        ->join("eegr_compras AS buy", "ordRec.orden_compra", "=", "buy.id")
        ->where("buy.token_compras",$vBuy->token_compras)
        ->get();
        //echo "ordPagoExists $ordPagoExists ";
        foreach ($ordRecepcionExists as $vOrdRec) {
          $folio_orden_recepcion = "ORDREC-".$JwtAuth->generarFolio($vOrdRec->folio_recepcion);
        }
        
        $orden_pago_folio = "---";
        $orden_pago_fecha_contabilizacion = "---";
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
          $orden_pago_folio = "ORDP-".$JwtAuth->generarFolio($vOrdp->folio_ordenPago);
          $orden_pago_fecha_contabilizacion = gmdate('Y-m-d H:i:s',$vOrdp->fecha_contabilizacion_ordenPago);
        
          $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where(["order.token_ordenPago" => $vOrdp->token_ordenPago])->get();
          foreach ($queryPagosDone as $vPayDone) {
            $pagos_realizados_folio = "PAYM-".$JwtAuth->generarFolio($vPayDone->folio_pagos);
            $pagos_realizados_fecha_contabilizacion = gmdate('Y-m-d H:i:s',$vPayDone->fecha_contabilizacion);
            $pagos_realizados_total += $vPayDone->orden_pago_monto;
          }
        }

        $buy_data = [
          "estilos_css" => $JwtAuth->css_pdf(),
          "logo_emp" => $logoEmp,
          "company_name_large" => "$name_abrev - $nombreEmpresa",
          "token_compras" => $vBuy->token_compras,
          "folio_compra" => $folio_compra,
          "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vBuy->fecha_contabilizacion),
          //proveedor
          "proveedor_folio" => $proveedor_folio,
          "proveedor_nombre" => $proveedor_nombre,
          //credito
          "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
          "fecha_vencimiento" => gmdate('Y-m-d H:i:s', $vBuy->fecha_vencimiento),
          //moneda
          "compra_moneda" => $vBuy->moneda,
          "compra_moneda_decimales" => $moneda_decimales,
          //importes
          "compra_subtotal" => "$" . number_format($totales_compra_subtotal, $moneda_decimales, '.', ','),
          "compra_descuento" => "$" . number_format($totales_compra_descuento, $moneda_decimales, '.', ','),
          "compra_retenciones" => "$" . number_format($totales_compra_retenciones, $moneda_decimales, '.', ','),
          "compra_traslados" => "$" . number_format($totales_compra_traslados, $moneda_decimales, '.', ','),
          "importe_total_compra" => "$" . number_format($totales_compra_importe, $moneda_decimales, '.', ','),
          
          "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
          "recibeFactura" => $vBuy->recibeFactura ? 'Antes' : 'Despues',
          "cfdi_reporte" => $vBuy->reporte,
          "cfdi_comprobante_version" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_version,
          "cfdi_comprobante_serie" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_serie,
          "cfdi_comprobante_folio" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_folio,
          "cfdi_comprobante_fecha" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_fecha,
          "cfdi_comprobante_forma_de_pago" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_forma_de_pago,
          "cfdi_comprobante_metodo_de_pago" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_metodo_de_pago,
          "cfdi_comprobante_subtotal" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_subtotal,
          "cfdi_comprobante_moneda" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_moneda,
          "cfdi_comprobante_tipo_de_cambio" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_tipo_de_cambio,
          "cfdi_comprobante_total" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_total,
          "cfdi_comprobante_confirmacion" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_confirmacion,
          "cfdi_comprobante_tipo_de_comprobante" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_comprobante_tipo_de_comprobante,
          "cfdi_complementoFechaTimbrado" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_complementoFechaTimbrado,
          "cfdi_complementoUUID" => $vBuy->aplica_recepcion_facturas == 'No' ? "N/A" : $cfdi_complementoUUID,
          //recepcion
          "articulos_recibidos" => $total_art_recibidos." / ".count($queryDetailsTotal),
          "lugarRecepcionComplete" => $lugarRecepcionTipo == 'N/A' ? $lugarRecepcionTipo : "$lugarRecepcionTipo $lugarRecepcionDireccion",
          "folio_orden_recepcion" => $folio_orden_recepcion,
          //orden de pago 
          "folio_orden_pago" => $orden_pago_folio,
          "fecha_contabilizacion_orden_pago" => $orden_pago_fecha_contabilizacion,
          "pagos_realizados_folio" => $pagos_realizados_folio,
          "pagos_realizados_fecha_contabilizacion" => $pagos_realizados_fecha_contabilizacion,
          "pagos_realizados_total" => $pagos_realizados_total,
        ];
      }
      $pdf = \PDF::loadView('pdf.plantilla_compras',$buy_data);
      $pdf->setPaper('a2', 'landscape')->stream();
      return $pdf->stream($folio_compra . ".pdf");
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verDocRequiAnexo($folioReq, $tokenAnexo){
    $JwtAuth = new \JwtAuth();
    if (isset($folioReq) && !empty($folioReq) && isset($tokenAnexo) && !empty($tokenAnexo)) {
      $docs_query = DB::table("sos_documentos AS doc")
        ->join("eegr_compras_requisicion AS req", "doc.requisicion", "=", "req.id")
        ->join("main_empresas AS emp", "req.empresa", "=", "emp.id")
        ->where(["doc.token_documento" => $tokenAnexo])->get();

      foreach ($docs_query as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->nombre_documento);
        //echo $name_doc;
        $filepath = $vDoc->root_tkn . "/0002-cpp/compras/requisiciones/" . $folioReq . '/' . $name_doc;

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verRequisicionPdfHtml(Request $request){
    $JwtAuth = new \JwtAuth();
    $tokenRequi = $request->tokenRequi;
    if (isset($tokenRequi) && !empty($tokenRequi)) {
      $reqVista = RequisicionesModelo::join("vhum_empleados_catalogo AS pers", "eegr_compras_requisicion.usuario_requisita", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->where(["eegr_compras_requisicion.token_requisicion" => $tokenRequi, "eegr_compras_requisicion.status" => TRUE])->get();
      //var_dump($reqVista);
      foreach ($reqVista as $vReq) {
        $token_requisicion = $vReq->token_requisicion;
        $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);
        $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vReq->fecha);
        $requisicion_proyecto = $JwtAuth->desencriptar($vReq->proyecto);
        //echo $requisicion_proyecto;

        if ($vReq->prioridad == "baj") {
          $requisicion_prioridad = "baja";
        }
        if ($vReq->prioridad == "med") {
          $requisicion_prioridad = "media";
        }
        if ($vReq->prioridad == "alt") {
          $requisicion_prioridad = "alta";
        }

        $usuario_requisita = $vReq->denominacion_rs != NULL ? $JwtAuth->desencriptar($vReq->denominacion_rs) : $JwtAuth->desencriptarNombres($vReq->paterno, $vReq->materno, $vReq->nombre);

        if ($vReq->autorizacion == TRUE) {
          $requisicion_autorizacion = "Requisición autorizada (" . gmdate('Y-m-d H:i:s', $vReq->fecha_autorizacion) . ")";
        }
        if ($vReq->autorizacion == FALSE) {
          $requisicion_autorizacion = "Requisición no autorizada";
        }
        //autoriza_user
        $persona_autoriza = "---";
        if ($vReq->autorizacion == TRUE && $vReq->autoriza_user != NULL) {
          $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["pers.id" => $vReq->autoriza_user])->get();

          foreach ($queryAutoriza as $rAutoriza) {
            $persona_autoriza = $rAutoriza->denominacion_rs != NULL ? $JwtAuth->desencriptar($rAutoriza->denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
          }
        }

        $selectEmp = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->where(["eegr_compras_requisicion.token_requisicion" => $vReq->token_requisicion])->get();

        $nameEmp = $selectEmp[0]->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($selectEmp[0]->paterno, $selectEmp[0]->materno, $selectEmp[0]->nombre) : $JwtAuth->desencriptar($selectEmp[0]->denominacion_rs);
        //$logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/homePagePrincipal/sos-mexico.png'));
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $selectEmp[0]->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($selectEmp[0]->img_perfil)));

        $detalle_table_ = "";
        $detalle_caract_ = "";
        $selectDetalleReq = DB::table("eegr_compras_requisicion_detalle AS reqDet")
          ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
          ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
          ->where(["reqMain.token_requisicion" => $vReq->token_requisicion])->get();

        foreach ($selectDetalleReq as $vDet) {
          if ($vDet->tipo_necesidad == "Merc") {
            $det_requi_tipo = "Mercancia";
          }
          if ($vDet->tipo_necesidad == "Gast") {
            $det_requi_tipo = "Gastos";
          }
          if ($vDet->tipo_necesidad == "Acti") {
            $det_requi_tipo = "Activos";
          }
          if ($vDet->tipo_necesidad == "Mixt") {
            $det_requi_tipo = "Mixto";
          }

          $det_requi_necesidad = $JwtAuth->desencriptar($vDet->necesidad);
          //caracteristicas
          //caracteristicas_extend
          //cantidad
          $det_requi_unidad_medida = $vDet->unidad_medida . " - " . $vDet->sat_clave . ", representa " . $vDet->representa;
          $det_requi_marca = $vDet->marca != NULL ? $JwtAuth->desencriptar($vDet->marca) : "---";

          $row_table = "<tr>
                        <td>" . $det_requi_tipo . "</td>
                        <td>" . $det_requi_necesidad . "</td>
                        <td>" . $vDet->cantidad . "</td>
                        <td>" . $vDet->cantidad_autorizada . "</td>
                        <td>" . $det_requi_unidad_medida . "</td>
                        <td>" . $det_requi_marca . "</td>
                    </tr>";
          $detalle_table_ = $detalle_table_ . $row_table;

          $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
            ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
            ->where(["reqMain.token_requisicion" => $vReq->token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
          //echo count($selectDetReqCaractList);
          $list_caract = '';
          if (count($selectDetReqCaractList) > 0) {
            foreach ($selectDetReqCaractList as $vCaract) {
              $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
              $descif_valor = $descif_clave == "Precio" ? "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',') : $JwtAuth->desencriptar($vCaract->valor);
              $jRow = '<tr><td>' . $descif_clave . '</td><td>' . $descif_valor . '</td></tr>';
              $list_caract = $list_caract . $jRow;
            }
          } else {
            $list_caract = '<tr><td colspan="2">!NO HAY REGISTROS¡</td></tr>';
          }

          $txt_other_caract = '';
          if ($vDet->caracteristicas_extend != NULL) {
            $txt_other_caract = '<h5 style="text-align: left;">Otras características: ' . $JwtAuth->desencriptar($vDet->caracteristicas_extend) . '</h5>';
          }
          //<!--<tbody>'.$list_caract.'</tbody>-->
          $row_caract = '<div style="width: 100%;margin-top:20px;">
                        <h5 style="text-align: left;">Concepto: ' . $det_requi_necesidad . '</h5>
                        <table>
   					    	<thead>
					        	<tr>
									<th colspan="2">características</th>
					        	</tr>
					        </thead>
					        <tbody>' . $list_caract . '</tbody>
                        </table>' . $txt_other_caract . '</div>';
          $detalle_caract_ = $detalle_caract_ . $row_caract;
        }

        $cargaPDFAuth = '<!doctype html>
                    <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <title>Invoice - #123</title>
                            <style type="text/css">' . $JwtAuth->css_pdf() . '</style>
                        </head>
                        <body>
                            <header class="information information-cpp">
                                <table width="100%" style="margin:0!important;padding:0!important;">
                                    <tr><td colspan="3" style="margin:0!important;padding:0!important;" align="center">
                                        <img src="' . $logoEmp . '" alt="Logo" height="50" class="logotipo"/>
                                        <h4 style="margin:0!important;padding:0!important;">' . $nameEmp . '</h4>
                                    </td></tr>
                                    <tr>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Egresos y cuentas por pagar (Compras)</h3></td>
                                        <td align="center" style="width: 60%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Reporte de requisición registrada</h3></td>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">' . date('d M, Y H:i:s', time()) . '</h3></td>
                                    </tr>
                                </table>
                            </header>
                            <main>
                                <article style="margin-top:20px;">
                                    <h3>Proyecto de requisición ' . $requisicion_folio . ' ' . $requisicion_proyecto . '</h3>
                                
                                    <table>
   								    	<thead>
								        	<tr>
								        		<th>prioridad</th>
								        		<th>FECHA DE REGISTRO</th>
								        		<th>personal que envía</th>
								        		<th>autorización</th>
								        		<th>personal que autoriza</th>
								        	</tr>
								        </thead>
								    	<tbody>
								    		<tr>
								    		    <td>' . $requisicion_prioridad . '</td>
								    		    <td>' . $requisicion_fecha_registro . '</td>
								    		    <td>' . $usuario_requisita . '</td>
								    		    <td>' . $requisicion_autorizacion . '</td>
								    		    <td>' . $persona_autoriza . '</td>
								    		</tr>
								    	</tbody>
                                    </table>
                                </article>
                                <article style="margin-top:20px;">
                                    <h4>Listado de articulos</h4>
                                    <table>
   								    	<thead>
								        	<tr>
												<th>tipo de requisicion</th>
												<th>concepto</th>
												<th>cantidad</th>
												<th>cantidad autorizada</th>
												<th>unidad de medida</th>
												<th>marca sugerida</th>
								        	</tr>
								        </thead>
								    	<tbody>' . $detalle_table_ . '</tbody>
                                    </table>
                                </article>
                                <article style="margin-top:20px;"><h4>desglose de productos</h4>' . $detalle_caract_ . '</article>
                            </main>
                            <footer style="display:flex;">
                                <table width="100%"><tr>
                                <td align="left" style="width: 50%;">sos-mexico.com.mx</td>
                                <td align="right" style="width: 50%;">página <span class="page"></span></td>
                                </tr></table>
                            </footer>
                        </body>
                    </html>';
        $dompdf = \PDF::loadHtml($cargaPDFAuth); //Se define el objeto DomPdf con el contenido HTML.
        $dompdf->setPaper('A2', 'landscape'); //Se define tamaño y orientación del papel
        $dompdf->render(); // Renderizamos el documento PDF.
        $contenidoPDF = $dompdf->stream($requisicion_folio . ".pdf"); // Enviamos el fichero PDF al navegador.
        return $contenidoPDF;
      }

      //$pdfGenerado = $JwtAuth->generaPdf("information-fnz","compras","requisiciones","alta de requisición");
      //$dompdf = \PDF::loadHtml($pdfGenerado);
      //$dompdf->setPaper("A2", "portrait");
      ////$contenidoPDF = $dompdf->output();
      ////$contenidoPDF = $dompdf->download('cert.pdf');
      //$contenidoPDF = $dompdf->stream();
      //return $contenidoPDF;
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verCotizacionPdfHtml(Request $request){
    $JwtAuth = new \JwtAuth();
    $tokenRequi = $request->tokenRequi;
    //echo $request->tokenRequi;
    $tokenCoti = $request->tokenCoti;
    if (isset($tokenRequi) && !empty($tokenRequi) && isset($tokenCoti) && !empty($tokenCoti)) {

      $cotiQuery = DB::table("eegr_compras_requisicion AS reqMain")
        ->join("vhum_empleados_catalogo AS pers", "reqMain.usuario_requisita", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->where([
          "reqMain.token_requisicion" => $tokenRequi
        ])->get();
      //echo count($cotiQuery);
      $cotiVista = DB::table("eegr_compras_cotizacion AS cotMain")
        ->join("eegr_compras_cotizacion_solicitud AS soliCot", "cotMain.solicitud_cotizacion", "=", "soliCot.id")
        ->join("eegr_compras_requisicion AS reqMain", "soliCot.requisicion", "=", "reqMain.id")
        ->join("vhum_empleados_catalogo AS pers", "reqMain.usuario_requisita", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->join("eegr_compras_cotizacion_solicitud_requi AS rel", "soliCot.id", "=", "rel.cotizacion_solicitud")
        ->join("eegr_compras_requisicion_detalle AS reqInside", "rel.requisicion_detalle", "=", "reqInside.id")
        ->where([
          "cotMain.coti_folio" => $tokenCoti,
          "soliCot.status_cotizacion_solicitud" => TRUE,
          "reqMain.autorizacion" => TRUE,
          "reqMain.token_requisicion" => $tokenRequi
        ])->get();

      //var_dump($reqVista);$tokenCoti
      foreach ($cotiVista as $vCot) {
        //da_te_default_timezone_set('America/Mexico_City');
        //cotizacion
        $cotizacionFolio = "COT-" . $JwtAuth->generarFolio($vCot->coti_folio);
        $cotizacionFecha = gmdate('Y-m-d H:i:s', $vCot->coti_fecha_sistema);
        $cotizacionComentariosFinales = $vCot->comentarios_finales != NULL ?
          '<h5 style="text-align:left!important;text-transform:initial!important;">Comentarios: ' . $JwtAuth->desencriptar($vCot->comentarios_finales) . '</h5>' : '';

        //solicitud de cotizacion
        $soliCotFolio = $JwtAuth->generarFolio($vCot->folio_registro) . " " . gmdate('Y-m-d H:i:s', $vCot->fecha_registro);
        $queryExpide = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
          ->join("vhum_empleados_catalogo AS pers", "soliCot.usuario_expide", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where([
            "soliCot.token_solicitud_cotizacion" => $vCot->token_solicitud_cotizacion
          ])->get();
        $soliCotUserExpide = $JwtAuth->desencriptarNombres($queryExpide[0]->paterno, $queryExpide[0]->materno, $queryExpide[0]->nombre);

        $token_requisicion = $vCot->token_requisicion;
        $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vCot->folio);
        $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vCot->fecha);
        $requisicion_proyecto = $requisicion_folio . " - " . $JwtAuth->desencriptar($vCot->proyecto) . " (" . gmdate('Y-m-d H:i:s', $vCot->fecha) . ")";
        //echo $requisicion_proyecto;

        if ($vCot->prioridad == "baj") {
          $requisicion_prioridad = "baja";
        }
        if ($vCot->prioridad == "med") {
          $requisicion_prioridad = "media";
        }
        if ($vCot->prioridad == "alt") {
          $requisicion_prioridad = "alta";
        }

        $usuario_requisita = $vCot->denominacion_rs != NULL ? $JwtAuth->desencriptar($vCot->denominacion_rs) : $JwtAuth->desencriptarNombres($vCot->paterno, $vCot->materno, $vCot->nombre);

        if ($vCot->autorizacion == TRUE) {
          $requisicion_autorizacion = "Requisición autorizada (" . gmdate('Y-m-d H:i:s', $vCot->fecha_autorizacion) . ")";
        }
        if ($vCot->autorizacion == FALSE) {
          $requisicion_autorizacion = "Requisición no autorizada";
        }
        //autoriza_user
        $persona_autoriza = "---";
        if ($vCot->autorizacion == TRUE && $vCot->autoriza_user != NULL) {
          $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["pers.id" => $vCot->autoriza_user])->get();

          foreach ($queryAutoriza as $rAutoriza) {
            $persona_autoriza = $rAutoriza->denominacion_rs != NULL ? $JwtAuth->desencriptar($rAutoriza->denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
          }
        }

        $selectEmp = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->where(["eegr_compras_requisicion.token_requisicion" => $vCot->token_requisicion])->get();

        $nameEmp = $selectEmp[0]->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($selectEmp[0]->paterno, $selectEmp[0]->materno, $selectEmp[0]->nombre) : $JwtAuth->desencriptar($selectEmp[0]->denominacion_rs);
        //$logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/homePagePrincipal/sos-mexico.png'));
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $selectEmp[0]->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($selectEmp[0]->img_perfil)));

        $detalle_table_ = "";
        $detalle_caract_ = "";
        $cotDetalleExtend = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
          ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
          ->join("eegr_catalogo_proveedores AS catprov", "cotDesk.coti_proveedor", "=", "catprov.id")
          ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
          ->join("teci_forma_pago AS fpay", "cotDesk.coti_forma_pago", "=", "fpay.id")
          ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
          ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
          ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
          ->join("eegr_compras_cotizacion_solicitud AS soliCot", "cotMain.solicitud_cotizacion", "=", "soliCot.id")
          ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
          ->join("teci_catalogo_monedas AS mon", "emp.e_moneda", "=", "mon.id")
          ->where([
            "reqDet.status_req" => TRUE,
            "cotMain.token_cotizacion" => $vCot->token_cotizacion,
          ])->get();

        foreach ($cotDetalleExtend as $vDet) {
          $main_moneda_token = $vDet->token_monedas;
          $main_moneda_codigo = $vDet->codigo;
          $main_moneda_name = $vDet->moneda;
          $main_moneda_decimales = $vDet->decimales;
          $tkn_detcot = $vDet->token_detalle_cotizacion;

          if ($vDet->tipo_necesidad == "Merc") {
            $det_requi_tipo = "Mercancia";
          }
          if ($vDet->tipo_necesidad == "Gast") {
            $det_requi_tipo = "Gastos";
          }
          if ($vDet->tipo_necesidad == "Acti") {
            $det_requi_tipo = "Activos";
          }
          if ($vDet->tipo_necesidad == "Mixt") {
            $det_requi_tipo = "Mixto";
          }

          $det_requi_necesidad = $JwtAuth->desencriptar($vDet->necesidad) . " (" . $det_requi_tipo . ")";
          $det_requi_marca = $vDet->marca != NULL ? $JwtAuth->desencriptar($vDet->marca) : "no hay marca referida";

          $des_persona_autoriza = "---";
          if ($vDet->des_autorizacion == "A" && $vDet->des_autoriza_user != NULL) {
            $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["pers.id" => $vDet->des_autoriza_user])->get();

            foreach ($queryAutoriza as $rAutoriza) {
              $denominacion_rs = $rAutoriza->denominacion_rs;
              $des_persona_autoriza = $denominacion_rs ? $JwtAuth->desencriptar($denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
            }
          }

          if ($vDet->des_autorizacion == TRUE) {
            $des_bool_requisicion_autorizacion = true;
            $des_requisicion_autorizacion = "Requisición autorizada por " . $des_persona_autoriza . " (" . gmdate('Y-m-d H:i:s', $vDet->des_fecha_autorizacion) . ")";
          } else {
            $des_bool_requisicion_autorizacion = false;
            $des_requisicion_autorizacion = "Requisición no autorizada";
          }

          $proveedor_tkn = $vDet->token_cat_proveedores;
          $prv_den = $vDet->denominacion_rs;
          $proveedor_name = $prv_den != NULL ? $JwtAuth->desencriptar($prv_den) : $JwtAuth->desencriptarNombres($vDet->paterno, $vDet->materno, $vDet->nombre);
          $proveedor_rfc_generico = $vDet->rfc_generico;
          $proveedor_rfc = $vDet->rfc != NULL ? $JwtAuth->desencriptar($vDet->rfc) : "---";
          $proveedor_taxId = $vDet->tax_id != NULL ? $JwtAuth->desencriptar($vDet->tax_id) : "---";

          if ($vDet->coti_entrega_tipo == "domi") {
            $coti_entrega_tipo_extend = "Domicilio";
          } else if ($vDet->coti_entrega_tipo == "stre") {
            $coti_entrega_tipo_extend = "Tienda";
          } else if ($vDet->coti_entrega_tipo == "ofna") {
            $coti_entrega_tipo_extend = "Oficina";
          } else if ($vDet->coti_entrega_tipo == "dest") {
            $coti_entrega_tipo_extend = "Destino";
          } else if ($vDet->coti_entrega_tipo == "cntr") {
            $coti_entrega_tipo_extend = "Contra reembolso";
          }

          $cotDetDeskMoneda = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
            ->join("teci_catalogo_monedas AS mon", "cotDesk.coti_moneda", "=", "mon.id")
            ->where(["cotDesk.token_desc_detalle_cotiza" => $vDet->token_desc_detalle_cotiza])->get();

          $cotDetDeskUMedida = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "cotDesk.coti_proveedor", "=", "catprov.id")
            ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
            ->join("teci_catalogo_monedas AS mon", "cotDesk.coti_moneda", "=", "mon.id")
            ->join("teci_unidad_medida AS umed", "cotDesk.coti_unidad_medida", "=", "umed.id")
            ->join("teci_forma_pago AS fpay", "cotDesk.coti_forma_pago", "=", "fpay.id")
            //->join("teci_metodo_pago AS mpay","cotDesk.coti_metodo_pago","=","mpay.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->where(["cotDet.token_detalle_cotizacion" => $tkn_detcot, "cotMain.token_cotizacion" => $vDet->token_cotizacion])->get();

          $coti_credito_otorga = $vDet->coti_credito_otorga == TRUE ? true : false;
          $coti_credito_time = $vDet->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vDet->coti_credito_time) : null;
          //$coti_precio = "$".number_format($vDet->coti_precio,$cotDetDeskMoneda[0]->decimales,'.',',')." ".$vDet->codigo." ".$vDet->moneda;
          $coti_precio = "$" . number_format($vDet->coti_precio, $cotDetDeskMoneda[0]->decimales, '.', ',') . " " . $cotDetDeskMoneda[0]->codigo;

          if ($main_moneda_token == $cotDetDeskMoneda[0]->token_monedas) {
            //$coti_conversion = "$".number_format($cotDetDeskMoneda[0]->coti_precio,$cotDetDeskMoneda[0]->decimales,'.',',')." ".$cotDetDeskMoneda[0]->codigo." ".$cotDetDeskMoneda[0]->moneda;
            $coti_conversion = "$" . number_format($cotDetDeskMoneda[0]->coti_precio, $cotDetDeskMoneda[0]->decimales, '.', ',') . " " . $cotDetDeskMoneda[0]->codigo;
          } else {
            $convet = $cotDetDeskMoneda[0]->coti_precio * $cotDetDeskMoneda[0]->coti_tipo_cambio;
            //$coti_conversion = "$".number_format($convet,$main_moneda_decimales,'.',',')." ".$main_moneda_codigo." ".$main_moneda_name;
            $coti_conversion = "$" . number_format($convet, $main_moneda_decimales, '.', ',') . " " . $main_moneda_codigo;
          }

          $coti_desc_autorizacion = $vDet->coti_desc_autorizacion == TRUE ? true : false;
          $coti_desc_fecha_autorizacion = $vDet->coti_desc_autorizacion == TRUE ? gmdate('Y-m-d H:i:s', $vDet->coti_desc_fecha_autorizacion) : null;

          $coti_desc_pers_autoriza = "";
          if ($vDet->coti_desc_autorizacion == TRUE) {
            $persAuthCoti = DB::table("sos_personas AS people")
              ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.personal")
              ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
              ->where(["cotDesk.token_desc_detalle_cotiza" => $vDet->token_desc_detalle_cotiza])->get();
            $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres($persAuthCoti[0]->paterno, $persAuthCoti[0]->materno, $persAuthCoti[0]->nombre);
          }

          $valoracion_posicion = "";
          $valoracion_stars = "";
          $valoracion_comentarios = "";
          $queryCotimOP = DB::table("eegr_compras_cotizacion_detalle_mejor_opcion AS mOP")
            ->join("eegr_compras_cotizacion AS cotMain", "mOP.cotizacion", "=", "cotMain.id")
            ->join("eegr_compras_cotizacion_detalle AS cotDesk", "mOP.detalle_cotizacion", "=", "cotDesk.id")
            ->join("eegr_catalogo_proveedores AS catprov", "mOP.proveedor", "=", "catprov.id")
            ->where([
              "cotMain.token_cotizacion" => $vDet->token_cotizacion,
              "cotDesk.token_detalle_cotizacion" => $vDet->token_detalle_cotizacion,
              "catprov.token_cat_proveedores" => $vDet->token_cat_proveedores
            ])->get();

          if (count($queryCotimOP) == 1) {
            foreach ($queryCotimOP as $vMop) {
              $valoracion_posicion = $vMop->posicion;
              $valoracion_stars = $vMop->posicion == 1 ? "***" : ($vMop->posicion == 2 ? "**" : ($vMop->posicion == 3 ? "*" : ""));
              $valoracion_comentarios = $JwtAuth->desencriptar($vMop->observaciones);
            }
          }

          $coti_credito = $vDet->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vDet->coti_credito_time) : "N/A";

          /*$insertCotizacionDescAdicionales = DB::table('eegr_compras_cotizacion_detalle_adicionales')->insert(
                        array(
                            "cotizacion" => end($selectCotizacionMain)->id,
                            "detalle_cotizacion" => end($selectCotizacionDetalle)->id,
                            "clave" => $JwtAuth->encriptar($adicionales_claves),
                            "proveedor" => $row_prv_adi,
                            "valor" => $JwtAuth->encriptar($adi_prv_val),
                        ) 
                    );*/

          $adicionalesList = array();
          $queryCotiMore = DB::table("eegr_compras_cotizacion_detalle_adicionales AS more")
            ->join("eegr_compras_cotizacion AS cotMain", "more.cotizacion", "=", "cotMain.id")
            ->join("eegr_compras_cotizacion_detalle AS cotDesk", "more.detalle_cotizacion", "=", "cotDesk.id")
            ->join("eegr_catalogo_proveedores AS catprov", "more.proveedor", "=", "catprov.id")
            ->where([
              "cotMain.token_cotizacion" => $vDet->token_cotizacion,
              "cotDesk.token_detalle_cotizacion" => $vDet->token_detalle_cotizacion,
              "catprov.token_cat_proveedores" => $vDet->token_cat_proveedores
            ])->get();

          foreach ($queryCotiMore as $vMore) {
            $rowMore = array(
              "clave" => $JwtAuth->desencriptar($vMore->clave),
              "valor" => $JwtAuth->desencriptar($vMore->valor),
            );
            $adicionalesList[] = $rowMore;
          }

          $coti_desc_fecha_autorizacion = $vDet->coti_desc_autorizacion == TRUE ? "si " . gmdate('Y-m-d H:i:s', $vDet->coti_desc_fecha_autorizacion) : "no";

          $row_table = "<tr>
                        <td>" . $det_requi_necesidad . "</td>
                        <td>" . $proveedor_name . " " . $proveedor_rfc . "</td>
                        <td>" . $valoracion_posicion . " " . $valoracion_stars . "</td>
                        <td>" . $valoracion_comentarios . "</td>
                        <td>" . $JwtAuth->desencriptar($vDet->coti_especificaciones) . "</td>
                        <td>" . $vDet->cantidad_autorizada . "</td>
                        <td>" . $coti_precio . " / " . $coti_conversion . "</td>
                        <td>" . $JwtAuth->desencriptar($vDet->coti_calidad) . "</td>
                        <td>" . $JwtAuth->desencriptar($vDet->coti_servicio) . "</td>
                        <td>" . $coti_entrega_tipo_extend . " Tiempo de entrega " . $JwtAuth->desencriptar($vDet->coti_entrega_tiempo) . "</td>
                        <td>" . $JwtAuth->desencriptar($vDet->coti_descuento) . "</td>
                        <td>" . $coti_credito . "</td>
                        <td>" . $JwtAuth->desencriptar($vDet->coti_garantia) . "</td>
                        <td>" . $vDet->unidad_medida . " - " . $vDet->sat_clave . "</td>
                        <td>" . $vDet->clave . " " . $vDet->forma . "</td>
                        <td>" . $JwtAuth->desencriptar($vDet->coti_valoracion) . "</td>
                        <td>" . $det_requi_marca . "</td>
                        <td>" . $coti_desc_fecha_autorizacion . "</td>
                    </tr>";
          $detalle_table_ = $detalle_table_ . $row_table;
        }

        $selectDetalleReq = DB::table("eegr_compras_requisicion_detalle AS reqDet")
          ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
          ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
          ->where(["reqMain.token_requisicion" => $vCot->token_requisicion])->get();

        foreach ($selectDetalleReq as $vDet) {
          $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
            ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
            ->where(["reqMain.token_requisicion" => $vCot->token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
          //echo count($selectDetReqCaractList);
          $list_caract = '';
          if (count($selectDetReqCaractList) > 0) {
            foreach ($selectDetReqCaractList as $vCaract) {
              $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
              $descif_valor = $descif_clave == "Precio" ? "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',') : $JwtAuth->desencriptar($vCaract->valor);
              $jRow = '<tr><td>' . $descif_clave . '</td><td>' . $descif_valor . '</td></tr>';
              $list_caract = $list_caract . $jRow;
            }
          } else {
            $list_caract = '<tr><td colspan="2">!NO HAY REGISTROS¡</td></tr>';
          }

          $txt_other_caract = '';
          if ($vDet->caracteristicas_extend != NULL) {
            $txt_other_caract = '<h5 style="text-align: left;">Otras características: ' . $JwtAuth->desencriptar($vDet->caracteristicas_extend) . '</h5>';
          }
          //<!--<tbody>'.$list_caract.'</tbody>-->
          $row_caract = '<div style="width: 100%;margin-top:20px;">
                        <h5 style="text-align: left;">Concepto: ' . $det_requi_necesidad . '</h5>
                        <table>
   					    	<thead>
					        	<tr>
									<th colspan="2">características</th>
					        	</tr>
					        </thead>
					        <tbody>' . $list_caract . '</tbody>
                        </table>' . $txt_other_caract . '</div>';
          $detalle_caract_ = $detalle_caract_ . $row_caract;
        }

        $cargaPDFAuth = '<!doctype html>
                    <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <title>Invoice - #123</title>
                            <style type="text/css">' . $JwtAuth->css_pdf() . '</style>
                        </head>
                        <body>
                            <header class="information information-cpp">
                                <table width="100%" style="margin:0!important;padding:0!important;">
                                    <tr><td colspan="3" style="margin:0!important;padding:0!important;" align="center">
                                        <img src="' . $logoEmp . '" alt="Logo" height="50" class="logotipo"/>
                                        <h4 style="margin:0!important;padding:0!important;">' . $nameEmp . '</h4>
                                    </td></tr>
                                    <tr>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Egresos y cuentas por pagar (Compras)</h3></td>
                                        <td align="center" style="width: 60%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Reporte de cotización registrada</h3></td>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">' . date('d M, Y H:i:s', time()) . '</h3></td>
                                    </tr>
                                </table>
                            </header>
                            <main>
                                <article style="margin-top:20px;">
                                    <h3>Cotización ' . $cotizacionFolio . '</h3>
                                    <h5 style="text-align:left!important;text-transform:initial!important;">Fecha de registro: ' . $cotizacionFecha . '</h5>
                                    ' . $cotizacionComentariosFinales . '
                                </article>
                                
                                <article style="margin-top:20px;">
                                    <h5 style="text-align:left!important;text-transform:initial!important;">Requisición vinculada</h5>
                                    <table>
   								    	<thead>
								        	<tr>
								        		<th>proyecto</th>
								        		<th>prioridad</th>
								        		<th>personal que envía</th>
								        		<th>autorización</th>
								        		<th>personal que autoriza</th>
								        	</tr>
								        </thead>
								    	<tbody>
								    		<tr>
								    		    <td>' . $requisicion_proyecto . '</td>
								    		    <td>' . $requisicion_prioridad . '</td>
								    		    <td>' . $usuario_requisita . '</td>
								    		    <td>' . $requisicion_autorizacion . '</td>
								    		    <td>' . $persona_autoriza . '</td>
								    		</tr>
								    	</tbody>
                                    </table>
                                </article>
                                
                                <article style="margin-top:20px;">
                                    <h5 style="text-align:left!important;text-transform:initial!important;">Solicitud de cotización vinculada</h5>
                                    <table>
   								    	<thead>
								        	<tr>
								        		<th>folio</th>
								        		<th>Solicitante</th>
								        	</tr>
								        </thead>
								    	<tbody>
								    		<tr>
								    		    <td>' . $soliCotFolio . '</td>
								    		    <td>' . $soliCotUserExpide . '</td>
								    		</tr>
								    	</tbody>
                                    </table>
                                </article>
                
                                <article style="margin-top:20px;">
                                    <h5 style="text-align:left!important;text-transform:initial!important;">Listado de articulos</h5>
                                    <table>
   								    	<thead>
								        	<tr>
												<th>concepto</th>
												<th>proveedor</th>
												<th></th>
												<th>comentarios</th>
												<th>Especificaciones</th>
												<th>Cantidad autorizada</th>
												<th>Precio</th>
												<th>Calidad</th>
												<th>Servicio</th>
												<th>Tipo y tiempo de entrega</th>
												<th>Descuento</th>
												<th>Cr&eacute;dito</th>
												<th>Garant&iacute;a</th>
												<th>Unidad de medida</th>
												<th>Forma de pago</th>
												<th>Valoraci&oacute;n</th>
												<th>marca sugerida</th>
												<th>autorizaci&oacute;n</th>
								        	</tr>
								        </thead>
								    	<tbody>' . $detalle_table_ . '</tbody>
                                    </table>
                                </article>
                                <article style="margin-top:20px;"><h4>desglose de productos</h4>' . $detalle_caract_ . '</article>
                            </main>
                            <footer style="display:flex;">
                                <table width="100%"><tr>
                                <td align="left" style="width: 50%;">sos-mexico.com.mx</td>
                                <td align="right" style="width: 50%;">página <span class="page"></span></td>
                                </tr></table>
                            </footer>
                        </body>
                    </html>';
        $dompdf = \PDF::loadHtml($cargaPDFAuth); //Se define el objeto DomPdf con el contenido HTML.
        $dompdf->setPaper('A2', 'landscape'); //Se define tamaño y orientación del papel
        $dompdf->render(); // Renderizamos el documento PDF.
        $contenidoPDF = $dompdf->stream($requisicion_folio . ".pdf"); // Enviamos el fichero PDF al navegador.
        return $contenidoPDF;
      }

      //$pdfGenerado = $JwtAuth->generaPdf("information-fnz","compras","requisiciones","alta de requisición");
      //$dompdf = \PDF::loadHtml($pdfGenerado);
      //$dompdf->setPaper("A2", "portrait");
      ////$contenidoPDF = $dompdf->output();
      ////$contenidoPDF = $dompdf->download('cert.pdf');
      //$contenidoPDF = $dompdf->stream();
      //return $contenidoPDF;
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verCompraFacturaXML($token_compras){
    $JwtAuth = new \JwtAuth();
    if (isset($token_compras) && !empty($token_compras) && isset($token_compras) && !empty($token_compras)) {
      $buy_query = DB::table("eegr_compras AS buy")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->where(["buy.token_compras" => $token_compras])->get();

      foreach ($buy_query as $vDoc) {
        $fechaSistema = $vDoc->fecha_sistemaCompras;
        $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vDoc->folio_compra).($vDoc->post_folio != NULL ? '-'.$vDoc->post_folio:'');
        $nombreDocs = $fechaSistema."-".$folio_buy;
        $name_doc = $JwtAuth->desencriptar($vDoc->facturaXml);
        //echo $name_doc;
        $filepath = $vDoc->root_tkn ."/0002-cpp/compras/compras/$nombreDocs/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verCompraFacturaPDF($token_compras){
    $JwtAuth = new \JwtAuth();
    if (isset($token_compras) && !empty($token_compras) && isset($token_compras) && !empty($token_compras)) {
      $buy_query = DB::table("eegr_compras AS buy")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->where(["buy.token_compras" => $token_compras])->get();

      foreach ($buy_query as $vDoc) {
        $fechaSistema = $vDoc->fecha_sistemaCompras;
        $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vDoc->folio_compra).($vDoc->post_folio != NULL ? '-'.$vDoc->post_folio:'');
        $nombreDocs = $fechaSistema."-".$folio_buy;
        $name_doc = $JwtAuth->desencriptar($vDoc->facturaPdf);
        //echo $name_doc;
        $filepath = $vDoc->root_tkn ."/0002-cpp/compras/compras/$nombreDocs/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verCompraEvidenciaSAT($token_compras){
    $JwtAuth = new \JwtAuth();
    if (isset($token_compras) && !empty($token_compras) && isset($token_compras) && !empty($token_compras)) {
      $buy_query = DB::table("eegr_compras AS buy")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->where(["buy.token_compras" => $token_compras])->get();

      foreach ($buy_query as $vDoc) {
        $fechaSistema = $vDoc->fecha_sistemaCompras;
        $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vDoc->folio_compra).($vDoc->post_folio != NULL ? '-'.$vDoc->post_folio:'');
        $nombreDocs = $fechaSistema."-".$folio_buy;
        $name_doc = $JwtAuth->desencriptar($vDoc->evidenciaSAT);
        //echo $name_doc;
        $filepath = $vDoc->root_tkn ."/0002-cpp/compras/compras/$nombreDocs/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  //ventas

  //reembolsos
  public function verPdfHtmlReembolso(Request $request){
    $JwtAuth = new \JwtAuth();
    $tokenReem = $request->tokenReem;
    if (!empty($tokenReem) && !empty($tokenReem)) {
      $reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
      ->join("sos_reembolsos_comisiones_rel AS comi_reem", "reem_main.id", "=", "comi_reem.reembolso_main")
      ->join("terc_comisiones_main AS comi_main", "comi_reem.comision", "=", "comi_main.id")
      ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
      ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
      ->where(["reem_main.token_reem" => $tokenReem, "reem_main.status_reem" => TRUE])->get();

      foreach ($reembolso_main_selected as $vremb) {
        $nameEmp = "SOLUCIONES OPORTUNAS SIMPLES";
        $logoEmp = "";

        //da_te_default_timezone_set($vremb->zona_horaria);
        $fecha_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);
        $token_reem = $vremb->token_reem;

        $folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');

        //emisor 
        $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
        ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
        ->join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        foreach ($selectNameEmpEmi as $vEmisor) {
          $name_emisor = $vEmisor->abrev_nombre;

          $rfc_gen_emi = $vEmisor->rfc_generico;
          $rfc_emp_emi = !is_null($vEmisor->rfc) ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
          $taxid_emp_emi = !is_null($vEmisor->tax_id) ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";

          $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vEmisor->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($vEmisor->img_perfil)));
        }

        $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        foreach ($selectPersEmpEmi as $vPemi) {
          $paterno_emi = ucfirst($JwtAuth->desencriptar($vPemi->paterno));
          $materno_emi = ucfirst($JwtAuth->desencriptar($vPemi->materno));
          $nombres_emi = ucwords($JwtAuth->desencriptar($vPemi->nombre));
          $name_pers_emisor = $paterno_emi . " " . $materno_emi . " " . $nombres_emi;
        }

        //receptor                
        $selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        $txt_folio_solicitud = "0";

        foreach ($selectNameEmpRec as $vReceptor) {
          $tkn_receptor = $vReceptor->empresa_token;
          $name_receptor = $vReceptor->abrev_nombre;
          $rfc_gen_receptor = $vReceptor->rfc_generico;
          $rfc_emp_receptor = !is_null($vReceptor->rfc) ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
          $taxid_emp_receptor = !is_null($vReceptor->tax_id) ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
        }

        $name_pers_receptor_vh = "N/A";
        $selectPersEmpReceptorVH = DB::table("terc_reembolso_main AS reem_main")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        if (count($selectPersEmpReceptorVH) == 1) {
          foreach ($selectPersEmpReceptorVH as $vPVH) {
            $desif_paterno_vh = ucfirst($JwtAuth->desencriptar($vPVH->paterno));
            $desif_materno_vh = ucfirst($JwtAuth->desencriptar($vPVH->materno));
            $desif_nombres_vh = ucwords($JwtAuth->desencriptar($vPVH->nombre));
            $name_pers_receptor_vh = $desif_paterno_vh . " " . $desif_materno_vh . " " . $desif_nombres_vh;
          }
        }

        $selectPersEmpReceptorEGR = DB::table("terc_reembolso_main AS reem_main")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        foreach ($selectPersEmpReceptorEGR as $vPEgr) {
          $desif_paterno_egr = ucfirst($JwtAuth->desencriptar($vPEgr->paterno));
          $desif_materno_egr = ucfirst($JwtAuth->desencriptar($vPEgr->materno));
          $desif_nombres_egr = ucwords($JwtAuth->desencriptar($vPEgr->nombre));
          $name_pers_receptor_egr = $desif_paterno_egr . " " . $desif_materno_egr . " " . $desif_nombres_egr;
        }

        $soli_reem = DB::table("terc_reembolso_main AS reem_main")
          ->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
          ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
          ->where(["reem_main.token_reem" => $token_reem])
          ->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

        $desg_reem = '';

        $importe_total = 0;
        $importe_total_conv = 0;
        $total_tipo_cambio = 0;
        $total_reem_auth = 0;
        $total_reem_auth_conv = 0;

        $moneda_ent_string = "";
        $moneda_ent_dec = 0;

        $moneda_sal_string = "";
        $moneda_sal_dec = 0;
        $total_reem_saliente = 0;

        foreach ($soli_reem as $vSoliR) {
          $soli_mon_entrante = DB::table("teci_catalogo_monedas AS mon_in")
          ->join("terc_reembolso_solicitud AS reem_soli", "mon_in.id", "=", "reem_soli.moneda_entrante")
          ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
          foreach ($soli_mon_entrante as $mon_in) {
            $moneda_ent_string = $mon_in->codigo;
            $moneda_ent_dec = $mon_in->decimales;
            $moneda_solie_string = $mon_in->codigo;
            $moneda_solie_dec = $mon_in->decimales;
          }

          $total_tipo_cambio = $vSoliR->tipo_cambio;
          $importe_total = $importe_total + $vSoliR->importe_entrante;
          $importe_total_conv = $importe_total_conv + ($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
          if (($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") && $vSoliR->autorizacion_egr == "A") {
            $total_reem_auth = $total_reem_auth + $vSoliR->importe_entrante;
            $total_reem_auth_conv = $total_reem_auth_conv + ($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
          }

          $soli_mon_saliente = DB::table("teci_catalogo_monedas AS mon_out")
            ->join("terc_reembolso_solicitud AS reem_soli", "mon_out.id", "=", "reem_soli.moneda_saliente")
            ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
          foreach ($soli_mon_saliente as $mon_out) {
            $moneda_sal_string = $mon_out->codigo;
            $moneda_sal_dec = $mon_out->decimales;
            $moneda_soliO_string = $mon_out->codigo;
            $moneda_soliO_dec = $mon_out->decimales;
          }

          $importe_entr = "$" . number_format($vSoliR->importe_entrante, $moneda_solie_dec, '.', ',') . " " . $moneda_solie_string;
          $importe_sali = "$" . number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio, $moneda_soliO_dec, '.', ',') . " " . $moneda_soliO_string;

          //proveedor
          $tkn_prov = "";
          $name_prov = "";
          $rfc_generico_prov = "";
          $rfc_prov = "";
          $taxid_prov = "";
          if ($vSoliR->proveedor != NULL) {
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

              $rfc_prov = !is_null($sr_prov->rfc) ? $JwtAuth->desencriptar($sr_prov->rfc) : "---";
              $taxid_prov = !is_null($sr_prov->tax_id) ? $JwtAuth->desencriptar($sr_prov->tax_id) : "---";
            }
          }

          $pagado_a = $vSoliR->pagado_a == "pubgeneral" ? "público general" : "proveedor ($rfc_prov $name_prov)";

          $select_folio_auth_vh = DB::select(
            "SELECT r_auth.id FROM terc_reembolso_autorizacion_vh AS r_auth 
                        JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                        WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                        AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
            [$token_reem, $vSoliR->token_solicitud_reem]
          );

          if (count($select_folio_auth_vh) == 0) {
            $max_auth_vh = false;
            $time_registro_auth_vh = "";
            $comments_auth_vh = "";
          } else {
            $select_max_auth_vh = DB::select("SELECT fecha_registro,autorizacion_vh,comentarios 
                            FROM terc_reembolso_autorizacion_vh WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_vh AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
              [$token_reem, $vSoliR->token_solicitud_reem]
            );
            $max_auth_vh = $select_max_auth_vh[0]->autorizacion_vh == "A" ? true : false;
            $time_registro_auth_vh = date('d-m-Y - H:i:s', $select_max_auth_vh[0]->fecha_registro);
            $comments_auth_vh = $JwtAuth->desencriptar($select_max_auth_vh[0]->comentarios);
          }
          //echo $vSoliR->autorizacion_vh;
          switch ($vSoliR->autorizacion_vh) {
            case 'A':
              $autorizacion_vh = "si (" . $time_registro_auth_vh . ")";
              break;
            case 'N':
              $autorizacion_vh = "N/A";
              break;
            default:
              $autorizacion_vh = "no";
              break;
          }

          $select_folio_auth_egr = DB::select(
            "SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth 
                        JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                        WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                        AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
            [$token_reem, $vSoliR->token_solicitud_reem]
          );

          if (count($select_folio_auth_egr) == 0) {
            $max_auth_egr = false;
            $time_registro_auth_egr = "";
            $comments_auth_egr = "";
          } else {
            $select_max_auth_egr = DB::select(
              "SELECT fecha_registro,autorizacion_egr,comentarios 
                            FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
              [$token_reem, $vSoliR->token_solicitud_reem]
            );

            $max_auth_egr = $select_max_auth_egr[0]->autorizacion_egr == "A" ? true : false;

            $time_registro_auth_egr = date('d-m-Y - H:i:s', $select_max_auth_egr[0]->fecha_registro);
            $comments_auth_egr = $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios);
          }

          $autorizacion_egr = $vSoliR->autorizacion_egr == "A" ? "si (" . $time_registro_auth_egr . ")" : "no";
          //date('d-m-Y H:i:s',$vSoliR->fecha_gasto)
          $desg_reem = $desg_reem . '<tr><td>' . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . '</td><td>' . gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud) . '</td>
          <td>' . gmdate('Y-m-d H:i:s', $vSoliR->fecha_gasto) . '</td><td>' . $JwtAuth->desencriptar($vSoliR->ticket_gasto) . '</td><td>' . $pagado_a . '</td>
          <td>' . $vSoliR->clave . ' ' . $vSoliR->forma . '</td><td>' . $importe_entr . ' / ' . $importe_sali . '</td><td>$' . $vSoliR->tipo_cambio . '</td>
          <td>' . $JwtAuth->desencriptar($vSoliR->motivo_reem) . '</td><td>valor humano: ' . $autorizacion_vh . '</td><td>Egresos: ' . $autorizacion_egr . '</td></tr>';
        }

        $pagos_reem = '';
        $listaPagos = DB::select("SELECT payment.token_pagos,payment.folio_pagos,payment.fecha_sistema,payment.fecha_pago,
                    payment.cuenta_bancaria,payment.cuenta_monedero,payment.caja,payment.monto_pago,payment.tipo_cambio,
                    payment.forma_pago,payment.metodo_pago,payment.p_moneda,payment.concepto,payment.almacen,payment.personal_pago,
                    payment.personal_autoriza,payment.empresa,payment.status_pagos,payment.fecha_deletePagos,payment.pago_autorizado,
                    ordenp.fecha_sistema_ordenp,ordenp.folio_ordenPago FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_orden AS ordenp 
                    JOIN terc_reembolso_main AS reem_main WHERE payment.orden_pago = ordenp.id AND ordenp.reembolso_main = reem_main.id 
                    AND reem_main.token_reem = ?", [$token_reem]);

        if (count($listaPagos) > 0) {
          $num_lista_pagos = 1;
          $total_pagado = 0;
          foreach ($listaPagos as $resListaPagos) {
            $total_pagado = $total_pagado + $resListaPagos->monto_pago;

            $forma_pago_text = "-";
            if ($resListaPagos->forma_pago != NULL) {
              $pagosformaPago = DB::select("SELECT token_formapago,clave,forma FROM teci_forma_pago WHERE id = ?", [$resListaPagos->forma_pago]);
              $forma_pago_text = $pagosformaPago[0]->clave . ' - ' . $pagosformaPago[0]->forma;
            }

            $metodo_pago_text = "-";
            if ($resListaPagos->metodo_pago != NULL) {
              $pagosmetodoPago = DB::select("SELECT token_metodopago,abrev,metodo FROM teci_metodo_pago WHERE id = ?", [$resListaPagos->metodo_pago]);
              $metodo_pago_text = $pagosmetodoPago[0]->abrev . ' - ' . $pagosmetodoPago[0]->metodo;
            }
            $pagosmoneda = DB::select("SELECT token_monedas,codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resListaPagos->p_moneda]);

            $medio_de_pago = "indefinido";
            $name_caja = "---";
            $name_cuenta_banc = "---";
            $name_cuenta_mone = "---";

            if ($resListaPagos->caja != NULL) {
              $medio_de_pago = "caja";
              $cajaPago = DB::table("fnzs_catalogos_caja")->where(["id" => $resListaPagos->caja])->get();
              foreach ($cajaPago as $resultCaja) {
                $name_caja = $JwtAuth->generar($resultCaja->no_caja) . " (" . $JwtAuth->desencriptar($resultCaja->alias_caja) . ")";
              }
            }

            if ($resListaPagos->cuenta_bancaria != NULL) {
              $medio_de_pago = "cuenta bancaria";
              $tknCuenta = DB::select("SELECT token_cuenta FROM fnzs_catalogos_cuentas WHERE id = ?", [$resListaPagos->cuenta_bancaria]);

              $respCuenta = DB::table("fnzs_catalogos_cuentas AS account")
                ->join("teci_bancos AS bank", "account.banco", "bank.id")
                ->where(["account.id" => $resListaPagos->cuenta_bancaria])->get();

              if (count($respCuenta) != 0) {
                foreach ($respCuenta as $resCuentas) {
                  $name_cuenta_banc = $JwtAuth->generar($resCuentas->folio_cuenta) . " (" . $resCuentas->clave . " - " . $resCuentas->nombre_comercial . ")";
                }
              }
            }

            if ($resListaPagos->cuenta_monedero != NULL) {
              $medio_de_pago = "cuenta de monedero electrónico";
              $arrayOpcionAdicionalMon = array();
              $idCuentaMonedero = DB::select("SELECT token_cuentamonedero FROM fnzs_catalogos_cuentas_monedero WHERE id = ?", [$resListaPagos->cuenta_monedero]);

              $respMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS accMon")
                ->join("teci_plataformas_digitales AS pdig", "accMon.monedero", "pdig.id")
                ->where(["accMon.id" => $resListaPagos->cuenta_monedero])->get();

              foreach ($respMonedero as $resMonedero) {
                $name_cuenta_mone = $JwtAuth->generar($resMonedero->folio_cuentmon) . " (" . $resMonedero->nombre . ")";
              }
            }

            $pagos_reem = $pagos_reem . '<tr>
                            <td>' . $num_lista_pagos . '</td>
                            <td>' . $medio_de_pago . '</td>
                            <td>' . $name_caja . '</td>
                            <td>' . $name_cuenta_banc . '</td>
                            <td>' . $name_cuenta_mone . '</td>
                            <td>' . $forma_pago_text . '</td>
                            <td>' . $metodo_pago_text . '</td>
                            <td>' . $pagosmoneda[0]->codigo . ' - ' . $pagosmoneda[0]->moneda . '</td>
                            <td>$' . number_format($resListaPagos->tipo_cambio, $vremb->decimales, '.', ',') . '</td>
                            <td>$' . number_format($resListaPagos->monto_pago, $vremb->decimales, '.', ',') . '</td>
                        </tr>';

            ++$num_lista_pagos;
          }
          $pagos_reem = $pagos_reem . '<tr><td colspan="8"></td><td>Total:</td><td>$' . number_format($total_pagado, $vremb->decimales, '.', ',') . '</td></tr>';
        } else {
          $pagos_reem = $pagos_reem . '<tr><td colspan="10">!NO HAY REGISTROS¡</td></tr>';
        }

        $table_docs_asoc = "";
        $selectAnexosReem = DB::table("sos_documentos AS docs")
          ->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
          ->where(["docs.tipo_documento" => "an", "main.token_reem" => $token_reem])->get();

        if (count($selectAnexosReem) > 0) {
          $name_docs_asoc = "";
          foreach ($selectAnexosReem as $vDoc) {
            $name_docs_asoc = $name_docs_asoc . $JwtAuth->desencriptar($vDoc->nombre_documento) . ", ";
          }
          $thml_docs_asoc = '<tr><td colspan="2">Documentos asociados: ' . substr($name_docs_asoc, 0, -2) . '</td></tr>';
        } else {
          $thml_docs_asoc = $thml_docs_asoc . '<tr><td colspan="2">Sin documentos asociados</td></tr>';
        }

        //echo $desg_reem;
        $monto_total_entr = "$" . number_format($importe_total, $moneda_ent_dec, '.', ',') . " " . $moneda_ent_string;
        $monto_total_sali = "$" . number_format($importe_total_conv, $moneda_sal_dec, '.', ',') . " " . $moneda_sal_string;
        $monto_auth_entr = "$" . number_format($total_reem_auth, $moneda_ent_dec, '.', ',') . " " . $moneda_ent_string;
        $monto_auth_sali = "$" . number_format($total_reem_auth_conv, $moneda_sal_dec, '.', ',') . " " . $moneda_sal_string;

        $cargaPDFAuth = '<!doctype html>
                    <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <title>Reembolsos</title>
                            <style type="text/css">' . $JwtAuth->css_pdf() . '</style>
                        </head>
                        <body>
                            <header class="information information-cpp">
                                <table width="100%" style="margin:0!important;padding:0!important;">
                                    <tr><td colspan="3" style="margin:0!important;padding:0!important;" align="center">
                                        <img src="' . $logoEmp . '" alt="Logo" height="50" class="logotipo"/>
                                        <h4 style="margin:0!important;padding:0!important;">' . $nameEmp . '</h4>
                                    </td></tr>
                                    <tr>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Módulo de empleados</h3></td>
                                        <td align="center" style="width: 60%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Reporte de reembolsos</h3></td>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">' . date('d M, Y H:i:s', time()) . '</h3></td>
                                    </tr>
                                </table>
                            </header>
                            <main>
                                <h3 style="text-align:center;">' . $folio_reem . '</h3>
                                <article style="margin-top:20px;">
                                    <table>  
                                        <thead>
                                            <tr>
                                                <th>FECHA DE REGISTRO</th>
                                                <th>Comisión</th>
                                                <th>EMISOR</th>
                                                <th colspan="2">RECEPTOR</th>
                                                <th>Total</th>
                                                <th>Autorizado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>' . $fecha_solicitud . '</td>
                                                <td>COMI-' . $JwtAuth->generarFolio($vremb->folio_comision) . '</td>
                                                <td>' . $name_pers_emisor . ' (' . $name_emisor . ')</td>
                                                <td>' . $name_pers_receptor_vh . ' (Valor Humano ' . $name_receptor . ')</td>
                                                <td>' . $name_pers_receptor_egr . ' (Egresos ' . $name_receptor . ')</td>
                                                <td>' . $monto_total_entr . ' / ' . $monto_total_sali . '</td>
                                                <td>' . $monto_auth_entr . ' / ' . $monto_auth_sali . '</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </article>
                                <article style="margin-top:20px;">
                                    <h4>Listado de solicitudes</h4>
                                    <div class="card">
                                        <table>
               					    		<thead>
            					    	    	<tr>
            					    	    		<th>folio</th>
            					    	    		<th>fecha de solicitud</th>
            					    	    		<th>Fecha de gasto</th>
            					    	    		<th>Ticket</th>
            					    	    		<th>Pagado a:</th>
            					    	    		<th>forma de pago</th>
            					    	    		<th>importe</th>
            					    	    		<th>tipo de cambio</th>
            					    	    		<th>observaciones</th>
            					    	    		<th colspan="2">autorizado por</th>
            					    	    	</tr>
            					    	    </thead>
            					    		<tbody>' . $desg_reem . '</tbody>
                                        </table>
                                    </div>
                                </article>
                                <article style="margin-top:20px;">
                                    <h4>PAGOS REALIZADOS</h4>
                                    <table>
   							            <thead>
						                	<tr>
                                                <th class="ultimo"></th>
                                                <th>medio de pago</th>
                                                <th>caja (folio)</th>
                                                <th>cuenta (folio)</th>
                                                <th>monedero (folio)</th>
                                                <th>forma de pago</th>
                                                <th>metodo de pago</th>
                                                <th>moneda</th>
                                                <th>tipo de cambio</th>
                                                <th>pago recibido</th>
						                	</tr>
						                </thead>
							            <tbody>' . $pagos_reem . '</tbody>
                                    </table>
                                </article>
                            </main>
                            
                            <footer style="display:flex;">
                                <table width="100%">' . $thml_docs_asoc . '<tr>
                                        <td align="left" style="width: 50%;">sos-mexico.com.mx</td>
                                        <td align="right" style="width: 50%;">página <span class="page"></span></td>
                                    </tr>
                                </table>
                            </footer>
                        </body>
                    </html>';

        $dompdf = \PDF::loadHtml($cargaPDFAuth); //Se define el objeto DomPdf con el contenido HTML.
        $dompdf->setPaper('A2', 'landscape'); //Se define tamaño y orientación del papel
        $dompdf->render(); // Renderizamos el documento PDF.
        $contenidoPDF = $dompdf->stream($folio_reem . ".pdf"); // Enviamos el fichero PDF al navegador.
        return $contenidoPDF;
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function egr_reembolso_visor_anexos($reemFolio, $tknAnexo){
    //echo $nombre_evidencia;
    $JwtAuth = new \JwtAuth();

    if (isset($tknAnexo) && !empty($tknAnexo)) {
      //echo $tknAnexo;
      $selectAnexosReem = DB::table("sos_documentos AS docs")
        ->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
        ->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
        ->join("main_empresas AS emp", "main.emisor", "=", "emp.id")
        ->where(["docs.token_documento" => $tknAnexo])->get();
      //echo count($selectAnexosReem);
      if (count($selectAnexosReem) > 0) {
        foreach ($selectAnexosReem as $vDoc) {
          $name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
          $filepath_old = $vDoc->root_tkn . "/0010-reem/" . $reemFolio . "/anexos";
          $filepath_new = $vDoc->root_tkn . "/0010-reem/" . $reemFolio . "/" . $JwtAuth->generarFolio($vDoc->folio_solicitud) . "/anexos";
          $archivo_old = Storage::path('public/root/' . $filepath_old . '/' . $JwtAuth->desencriptar($vDoc->nombre_documento));
          $archivo_new = Storage::path('public/root/' . $filepath_new . '/' . $JwtAuth->desencriptar($vDoc->nombre_documento));

          if (file_exists($archivo_old)) {
            $nombre_documento = Storage::get('public/root/' . $filepath_old . '/' . $name_documento);
            return response(Storage::disk('root')->get($filepath_old . '/' . $name_documento), 200)
              ->header('Content-Type', Storage::disk('root')->mimeType($filepath_old . '/' . $nombre_documento));
          } else if (file_exists($archivo_new)) {
            $nombre_documento = Storage::get('public/root/' . $filepath_new . '/' . $name_documento);
            return response(Storage::disk('root')->get($filepath_new . '/' . $name_documento), 200)
              ->header('Content-Type', Storage::disk('root')->mimeType($filepath_new . '/' . $name_documento));
          } else {
            $nombre_documento = Storage::get('public/settings/dont_exist_evidencia.png');
            return response(Storage::disk('settings')->get('dont_exist_evidencia.png'), 200)
              ->header('Content-Type', Storage::disk('settings')->mimeType('dont_exist_evidencia.png'));
          }
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "La información que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function visorDocsPagos(Request $request){
    $JwtAuth = new \JwtAuth();
    $folioPago = $request->folioPago;
    $tokenDocumento = $request->tokenDocumento;
    if (isset($folioPago) && !empty($folioPago) && isset($tokenDocumento) && !empty($tokenDocumento)) {
      $docs_query = DB::table("sos_documentos AS docs")
        ->join("fnzs_pagos_orden AS order", "docs.orden_pago", "=", "order.id")
        ->join("main_empresas AS emp", "order.empresa", "=", "emp.id")
        ->where(["docs.token_documento" => $tokenDocumento])->get();

      foreach ($docs_query as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->nombre_documento);
        //echo $name_doc;
        $filepath = $vDoc->root_tkn . "/0003-fnzs/ordenes_pagos/" . $vDoc->fecha_sistema_ordenp . "-" . $folioPago . "/pago_evidencias/" . $name_doc;

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  //calculadora de retenciones
  public function calculo_retenciones_pdf(Request $request){
    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    $retencion_decimales = $request->retencion_decimales;
    $iva_establecido_percent = $request->iva_establecido_percent;
    $retencion_iva_liva_percent = $request->retencion_iva_liva_percent;
    $retencion_isr_porcentaje = $request->retencion_isr_porcentaje;

    $retencion_importe = "$" . number_format($request->retencion_importe, $retencion_decimales, '.', ',');
    $iva_establecido_view = "$" . number_format($request->iva_establecido_view, $retencion_decimales, '.', ',');
    $retencion_iva_liva_view = "$" . number_format($request->retencion_iva_liva_view, $retencion_decimales, '.', ',');
    $retencion_subtotal_view = "$" . number_format($request->retencion_subtotal_view, $retencion_decimales, '.', ',');
    $retencion_isr_view = "$" . number_format($request->retencion_isr_view, $retencion_decimales, '.', ',');
    $retencion_total_view = "$" . number_format($request->retencion_total_view, $retencion_decimales, '.', ',');

    $perfil = $request->perfil_name != 'No Aplica' ? '<h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Perfil: ' . $request->perfil_name . '</h3>' : '';
    $html_descripcion = '';
    if ($request->perfil_name != 'No Aplica') {
      switch ($request->perfil_clave) {
        case 'pfg':
          $html_descripcion = '
              <h3 class="textoheader">Persona física general.</h3>
              <p class="textoinformativo">
                Generalmente, se aplican cuando una persona física presta un servicio o realiza una actividad que está sujeta al ISR (Impuesto Sobre la Renta), IVA (Impuesto al Valor Agregado) u otros impuestos, y el 
                receptor del servicio (una empresa o persona moral, comúnmente) está obligado a retener.</p>
              <p class="textoinformativo">¿Qué impuestos se retienen?</p>
              <ol class="textoinformativo">
                <li>ISR (Impuesto Sobre la Renta):
                  <ol>
                    <li>Se retiene un porcentaje del pago que recibe la persona física como anticipo del impuesto anual.</li>
                    <li>El porcentaje depende del tipo de actividad (honorarios, arrendamiento, etc.), pero suele rondar el 10%.</li>
                  </ol>
                </li>
                <li>IVA (Impuesto al Valor Agregado):
                  <ol>
                    <li>Si se causa IVA, se retiene el 10.6667% del total facturado por el servicio (equivalente a 2/3 del IVA del 16%).</li>
                    <li>Esto aplica cuando la persona física está dada de alta en el régimen general o de actividades profesionales.</li>
                  </ol>
                </li>
              </ol>
              <p class="textoinformativo">¿Quién las realiza?</p>
              <ol class="textoinformativo">
                <li>Generalmente, personas morales (empresas) que pagan a personas físicas tienen la obligación de retener estos impuestos.</li>
                <li>Ellas deben entregar constancias a las personas físicas y declarar las retenciones al SAT.</li>
              </ol>
              <p class="textoinformativo">¿Cómo se reportan?</p>
              <ol class="textoinformativo">
                <li>El retenedor las declara mensualmente en sus pagos provisionales.</li>
                <li>La persona física podrá acreditar esas retenciones en su declaración anual para reducir su carga fiscal.</li>
              </ol>
            ';
          break;
        case 'pmg':
          $html_descripcion = '
            <h3 class="textoheader">Persona moral general</h3>
              <ol class="textoinformativo">
                <li>Las personas morales retienen impuestos cuando pagan ciertos conceptos, principalmente a personas físicas, y actúan como agentes retenedores ante el SAT.</li>
                <li>Las retenciones no son automáticas en todos los pagos: solo se aplican en casos específicos establecidos en ley, como sueldos, honorarios, arrendamiento, pagos al extranjero, y ciertas operaciones con IVA.</li>
                <li>La obligación de retener está regulada principalmente por:
                  <ol>
                    <li>Ley del Impuesto sobre la Renta (LISR)</li>
                    <li>Ley del Impuesto al Valor Agregado (LIVA)</li>
                    <li>Código Fiscal de la Federación (CFF)</li>
                  </ol>
                </li>
                <li>La persona moral que retiene debe:
                  <ol>
                    <li>Emitir comprobantes fiscales (CFDI) con el detalle de la retención.</li>
                    <li>Enterar las retenciones al SAT dentro del plazo legal.</li>
                    <li>Informar estas retenciones en declaraciones mensuales y anuales.</li>
                    <li>Expedir constancias si corresponde (por ejemplo, en sueldos).</li>
                  </ol>
                </li>
                <li>La persona moral también puede ser sujeta a retenciones, por ejemplo:
                  <ol>
                    <li>En operaciones con el sector público.</li>
                    <li>En esquemas de subcontratación o servicios especializados, donde le retienen el 6% de IVA.</li>
                  </ol>
                </li>
              </ol>
              <p class="textoinformativo">
                En resumen, las retenciones en personas morales de régimen general no se aplican en todos los casos, sino solo en situaciones específicas donde la ley lo exige. Su propósito es facilitar el control fiscal y asegurar que 
                ciertos impuestos lleguen al SAT de forma anticipada.</p>
            ';
          break;
        case 'ate':
          $html_descripcion = '
            <h3 class="textoheader">Adquisici&oacute;n o arrendamiento de bienes tangibles a residentes en el extranjero sin establecimiento permanente.</h3>
              <p class="textoinformativo">
                La adquisición o arrendamiento de bienes tangibles a residentes en el extranjero sin establecimiento permanente en México se refiere a las operaciones en las que un residente en México (persona física o moral) compra o arrienda bienes físicos (muebles o inmuebles) de una persona o empresa que reside fuera del país y no tiene establecimiento permanente en territorio nacional.Este tipo de operación tiene implicaciones fiscales específicas, sobre todo en cuanto a la retención de impuestos, deducibilidad, y comprobación de operaciones con residentes en el extranjero, reguladas por la Ley del ISR y el Código Fiscal de la Federación.</p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li>Ley del Impuesto sobre la Renta (LISR)
                  <ol>
                    <li>Artículo 9: Define qué se considera establecimiento permanente.</li>
                    <li>Artículo 24: Establece los ingresos de fuente de riqueza en México, incluyendo arrendamientos de bienes usados en territorio nacional.</li>
                    <li>Artículo 31: Requisitos de deducciones, incluyendo operaciones con extranjeros.</li>
                    <li>Artículo 161: Aplicación de retenciones cuando se pagan ingresos a residentes en el extranjero.</li>
                  </ol>
                </li>
                <li>Código Fiscal de la Federación (CFF)
                  <ol>
                    <li>Artículo 76-A: Requisitos para la documentación de operaciones con partes relacionadas extranjeras.</li>
                    <li>Artículo 179 y 180: Precios de transferencia entre residentes nacionales y extranjeros.</li>
                  </ol>
                </li>
                <li>Resolución Miscelánea Fiscal vigente: Contiene reglas específicas sobre la retención del ISR e IVA en operaciones con extranjeros.</li>
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf"target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código Fiscal de la Federación. Última reforma publicada DOF 01-01-2024. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf"target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Operaciones con residentes en el extranjero. Recuperado de:<a href="https://www.sat.gob.mx"target="_blank">https://www.sat.gob.mx</a></li>
                <li>PricewaterhouseCoopers (PwC). (2023). Guía fiscal de México 2023. Recuperado de:<a href="https://www.pwc.com/mx/es/servicios/fiscales.html"target="_blank">https://www.pwc.com/mx/es/servicios/fiscales.html</a></li>
              </ol>
            ';
          break;
        case 'hon':
          $html_descripcion = '
            <h3 class="textoheader">Honorarios.</h3>
              <p class="textoinformativo">
                Los honorarios son los pagos que una persona física recibe por la prestación de servicios profesionales independientes, es decir, cuando un profesional (como un abogado, contador, arquitecto, médico, diseñador, etc.) realiza un servicio a cambio de un pago, sin tener una relación laboral con el cliente.
              </p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <div class="col-12">
                <ol class="textoinformativo">
                  <li>Ley del Impuesto sobre la Renta (ISR).
                    <ol>
                      <li>Artículo 100 Define los ingresos que obtienen las personas físicas por la prestación de servicios profesionales independientes (honorarios) como parte de los ingresos acumulables.</li>
                      <li>Artículo 106 al 110 Regulan el cálculo del ISR, deducciones autorizadas, y la obligación de expedir comprobantes fiscales (CFDI).</li>
                      <li>Artículo 113-J (si aplica Régimen Simplificado de Confianza) Establece reglas especiales de tributación si el contribuyente opta por este régimen.</li>
                    </ol>
                  </li>
                  <li>Código Fiscal de la Federación (CFF).
                    <ol>
                      <li>Artículo 29 y 29-A Obligan a expedir Comprobantes Fiscales Digitales (CFDI) por los servicios prestados, incluyendo honorarios.</li>
                    </ol>
                  </li>
                  <li>Ley del Impuesto al Valor Agregado (IVA).
                    <ol>
                      <li>Artículo 1 y 14 Indican que los servicios profesionales están gravados con IVA al 16%, salvo que estén expresamente exentos.</li>
                    </ol>
                  </li>
                </ol>
              </div>

              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Diario Oficial de la Federación. <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código Fiscal de la Federación. Diario Oficial de la Federación. <a href="http://www.diputados.gob.mx/LeyesBiblio/pdf/CFF.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/CFF.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto al Valor Agregado. Diario Oficial de la Federación. <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf</a></li>
                <li>Servicio de Administración Tributaria. (s.f.). Actividades profesionales (servicios profesionales). Gobierno de México. <a href="https://www.sat.gob.mx/consultas/25685/actividades-profesionales-(servicios-profesionales)" target="_blank">https://www.sat.gob.mx/consultas/25685/actividades-profesionales-(servicios-profesionales)</a> </li>
                <li>Servicio de Administración Tributaria. (s.f.). Obligaciones fiscales por honorarios. Gobierno de México. <a href="https://www.sat.gob.mx/" target="_blank"> https://www.sat.gob.mx/</a></li>
              </ol>';
          break;
        case 'ari':
          $html_descripcion = '
              <h3 class="textoheader">Arrendamiento</h3>
              <p class="textoinformativo">El arrendamiento es un contrato por el cual una de las partes, llamada arrendador, se obliga a conceder el uso o goce temporal de un bien a otra parte, llamada arrendatario, quien a su vez se obliga a pagar por ese uso un precio cierto y determinado</p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li>Artículo 2398 del Código Civil Federal.
                  "El arrendamiento es un contrato por el cual las dos partes se obligan recíprocamente, una a conceder el uso o goce temporal de una cosa, y la otra a pagar por ese uso o goce un precio cierto.
                </li>
                <li>Artículos 2398 al 2425 del Código Civil Federal.
                  Estos artículos establecen las disposiciones generales del arrendamiento: obligaciones del arrendador y arrendatario, duración, forma de pago, causas de terminación, entre otros.
                </li>
                <li>Código Civil para el Distrito Federal.
                  (ahora Ciudad de México) Tiene disposiciones similares, pero con  algunas particularidades locales, especialmente en temas como prórrogas automáticas o requisitos para la desocupación del inmueble.
                </li>
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2023). Código Civil Federal. Última reforma publicada DOF 13-06-2023. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/2_130623.pdf." target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/2_130623.pdf.  </a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2023). Código Civil para el Distrito Federal. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/CCDF.pdf." target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/CCDF.pdf.</a></li>
                <li>García Máynez, E. (2019). Introducción al estudio del derecho (37ª ed.). México: Porrúa. (Referencia adicional para el concepto general del contrato)</li>
              </ol>
            ';
          break;
        case 'reg':
          $html_descripcion = '
            <h3 class="textoheader">RESICO</h3>
              <p class="textoinformativo"></p>
              <p>SUSTENTO LEGAL.<br>El RESICO está regulado en el Título IV, Capítulo II, Sección IV (Personas Físicas) y el Título VII, Capítulo XII (Personas Morales) de la Ley del Impuesto sobre la Renta (LISR).</p>
              <ol class="textoinformativo">
                <li> Para personas físicas:
                  <ol>
                    <li>Artículos 113-E al 113-J de la Ley del ISR. Aquí se establecen los requisitos para tributar bajo RESICO, tasas aplicables, forma de cálculo del impuesto y obligaciones.</li>
                  </ol>
                </li>
                <li>Para personas morales:
                  <ol>
                    <li>Artículos 206 al 210 de la Ley del ISR.Se establecen los lineamientos similares pero adaptados a personas morales con ingresos menores a 35 millones de pesos anuales.</li>
                  </ol>
                </li>
                <li>Fundamento complementario:Código Fiscal de la Federación (CFF): artículos sobre inscripción, obligaciones fiscales y uso del buzón tributario.
                </li>
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Régimen Simplificado de Confianza. Recuperado de:<a href="https://www.sat.gob.mx/personas/regimen-simplificado-de-confianza" target="_blank">https://www.sat.gob.mx/personas/regimen-simplificado-de-confianza</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código Fiscal de la Federación. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf</a></li>
              </ol>
            ';
          break;
        case 'spe':
          $html_descripcion = '
             <h3 class="textoheader">Servicios profesionales de residentes en el extranjero</h3>
              <p class="textoinformativo">Los servicios profesionales de residentes en el extranjero son aquellas actividades intelectuales, técnicas o especializadas (como asesorías, consultorías, diseño, programación, entre otros) que no implican una relación laboral, prestadas por personas físicas o morales que no residen en México y que no cuentan con un establecimiento permanente en el país.Cuando una persona o empresa en México contrata estos servicios, existen obligaciones fiscales como la retención del Impuesto Sobre la Renta (ISR), y en ciertos casos, del Impuesto al Valor Agregado (IVA), dependiendo del tipo de servicio y su aprovechamiento en México.</p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li> Ley del Impuesto sobre la Renta (LISR).
                  <ol>
                    <li>Artículo 1: Obliga a los residentes en México a retener impuestos cuando pagan a extranjeros sin establecimiento permanente.</li>
                    <li>Artículo 9: Define qué es un establecimiento permanente.</li>
                    <li>Artículo 24, fracción IV: Establece que los ingresos obtenidos por servicios personales independientes prestados por extranjeros se consideran de fuente de riqueza en México cuando se aprovechan en el país.</li>
                    <li>Artículo 167 y 172: Tasas y tratamiento de ISR sobre ingresos de fuente mexicana obtenidos por residentes en el extranjero.</li>
                  </ol>
                </li>
                <li>Ley del IVA.
                  <ol>
                    <li>Artículo 1-A, fracción III: Obliga a quienes reciben servicios de extranjeros a retener el IVA, cuando el servicio se aprovecha en territorio nacional.</li>
                    <li>Artículo 29: Establece que los servicios prestados por residentes en el extranjero son actos gravados si se aprovechan en México.</li>
                  </ol>
                </li>
                <li>Código Fiscal de la Federación (CFF).
                  <ol>
                    <li>Artículo 31: Requisitos fiscales para la deducibilidad de pagos al extranjero.</li>
                    <li>Artículo 76-A y 179–180: Obligaciones en materia de precios de transferencia cuando los servicios son con partes relacionadas extranjeras.</li>
                  </ol>
                </li>
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf  </a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código Fiscal de la Federación. Última reforma publicada DOF 01-01-2024. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Guía sobre pagos al extranjero. Recuperado de:<a href="https://www.sat.gob.mx" target="_blank">https://www.sat.gob.mx</a></li>
                <li>KPMG México. (2023). Guía de impuestos corporativos en México 2023. Recuperado de:<a href="https://home.kpmg/mx/es/home/insights.html" target="_blank">https://home.kpmg/mx/es/home/insights.html</a></li>
              </ol>
            ';
          break;
        case 'com':
          $html_descripcion = '
             <h3 class="textoheader">Comisiones</h3>
              <p class="textoinformativo">Una comisión es una remuneración económica que recibe una persona (comisionista) por llevar a cabo un acto o negocio por cuenta de otra (comitente), ya sea por la venta de bienes, colocación de servicios, gestiones comerciales, financieras, entre otros. Las comisiones pueden darse en relaciones laborales, civiles, o mercantiles. Dependiendo del contexto, pueden clasificarse como: Comisión mercantil (regulada en el Código de Comercio), ingresos por servicios independientes o asimilables a salarios (según la Ley del ISR), remuneraciones laborales, si están dentro de una relación de subordinación.</p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li>Código de Comercio.
                  <ol>
                    <li>Artículos 273 al 308: Regulan el contrato de comisión mercantil, donde una persona (comisionista) realiza actos de comercio por cuenta de otra (comitente).</li>
                  </ol>
                </li>
                <li>Ley del Impuesto sobre la Renta (LISR).
                  <ol>
                    <li>Artículo 94, fracción IV: Las comisiones pueden considerarse asimilables a salarios si provienen de personas físicas o morales que no tienen relación laboral directa, pero hay subordinación económica.</li>
                    <li>Artículo 100: Obligaciones de los retenedores en el caso de ingresos asimilables a salarios.</li>
                    <li>Artículo 106: Si se prestan los servicios de forma independiente, se consideran ingresos por actividad empresarial o profesional.</li>
                  </ol>
                </li>
                <li>Ley del Impuesto al Valor Agregado (LIVA).
                  <ol>
                    <li>Artículo 1 y 14: Las comisiones están gravadas con IVA cuando se prestan servicios independientes en territorio nacional.</li>
                  </ol>
                </li>
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código de Comercio. Última reforma publicada DOF 29-01-2024. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/3_290124.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/3_290124.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto al Valor Agregado. Última reforma publicada DOF 08-11-2023. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Comisiones y otros ingresos por servicios profesionales. Recuperado de:<a href="https://www.sat.gob.mx" target="_blank">https://www.sat.gob.mx</a></li>
              </ol>
            ';
          break;
        case 'fts':
          $html_descripcion = '
              <h3 class="textoheader">Fletes</h3>
              <p class="textoinformativo">El término flete se refiere al servicio de transporte de bienes o mercancías, ya sea por vía terrestre, marítima, aérea o ferroviaria, a cambio de una contraprestación económica. También se le llama “flete” al monto que se paga por ese servicio. El flete puede estar vinculado a un contrato de prestación de servicios, transporte, o comisión mercantil, dependiendo de la relación entre las partes.</p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li>Código de Comercio.
                  <ol>
                    <li>Artículos 575 al 630: Regulan el contrato de transporte terrestre de mercancías, estableciendo derechos y obligaciones del porteador y el cargador, así como temas de responsabilidad por pérdida o daño.</li>
                  </ol>
                </li>
                <li>Ley del Impuesto al Valor Agregado (LIVA).
                  <ol>
                    <li>Artículo 1: Establece que están sujetos al IVA los actos o actividades como la prestación de servicios, incluyendo el transporte de bienes.</li>
                  </ol>
                </li>
                <li>Ley del Impuesto sobre la Renta (LISR).
                  <ol>
                    <li>Artículo 16 y 17: El ingreso por prestación de servicios de transporte forma parte de la base gravable.</li>
                    <li>Artículo 106 y 110: Regulan ingresos por actividades empresariales y profesionales para personas físicas.</li>
                    <li>Artículo 31 y 36: Requisitos de deducibilidad de gastos para personas morales, incluyendo fletes.</li>
                  </ol>
                </li>
                <li>Código Fiscal de la Federación (CFF).
                  <ol>
                    <li>Artículo 29 y 29-A: Establecen los requisitos de los comprobantes fiscales digitales (CFDI), aplicables al servicio de flete.</li>
                  </ol>
                </li>
              </ol>
                <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código de Comercio. Última reforma publicada DOF 29-01-2024.
                  Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/3_290124.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/3_290124.pdf</a></li>
                <li>
                  Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023.
                  Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto al Valor Agregado. Última reforma publicada DOF 08-11-2023.
                  Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Complemento Carta Porte.
                  Recuperado de: <a href="https://www.sat.gob.mx/consultas/41524/conoce-el-complemento-carta-porte" target="_blank">https://www.sat.gob.mx/consultas/41524/conoce-el-complemento-carta-porte</a></li>
              </ol>
            ';
          break;
        case 'ade':
          $html_descripcion = '
             <h3 class="textoheader">Adquicición de desperdicios</h3>
              <p class="textoinformativo">La adquisición de desperdicios se refiere a la compra de materiales residuales o sobrantes de procesos productivos o de consumo, como metal, cartón, vidrio, plásticos, textiles, electrónicos, entre otros, que aún pueden tener valor comercial o ser reciclados. Fiscalmente, esta actividad tiene tratamiento especial en el ISR y el IVA, principalmente cuando se adquieren desperdicios a personas físicas no inscritas en el RFC (por ejemplo, recolectores informales).</p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li>Ley del Impuesto sobre la Renta (LISR).
                  <ol>
                    <li>Artículo 74, fracción IV: Permite deducir la adquisición de desperdicios si se cumplen ciertos requisitos fiscales y si se realizan retenciones al vendedor, cuando este sea una persona física no inscrita en el RFC.</li>
                    <li>Artículo 76, fracción X: Establece la obligación de efectuar retención del ISR cuando se adquieran desperdicios de personas físicas no contribuyentes.</li>
                    <li>Artículo 113: Trata sobre retenciones a terceros sin RFC o con actividades esporádicas.</li>
                  </ol>
                </li>
                <li>Ley del Impuesto al Valor Agregado (LIVA).
                  <ol>
                    <li>Artículo 1-A, fracción III: Obliga a retener el IVA cuando se adquieren bienes o servicios de ciertos contribuyentes.</li>
                    <li>Artículo 5, fracción III: Establece que para que el IVA sea acreditable, debe estar debidamente comprobado con CFDI y retenido cuando aplique.</li>
                  </ol>
                </li>
                <li>Reglas de la Resolución Miscelánea Fiscal (RMF).
                  <ol>
                    <li>Reglas como la 2.7.3.1. y otras afines establecen facilidades administrativas para la emisión del CFDI en operaciones con personas físicas que venden desperdicios.</li>
                  </ol>
                </li> 
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023. Recuperado de:<a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto al Valor Agregado. Última reforma publicada DOF 08-11-2023. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Guía para la adquisición de desperdicios y bienes a contribuyentes no registrados. Recuperado de:<a href="https://www.sat.gob.mx" target="_blank">https://www.sat.gob.mx</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Resolución Miscelánea Fiscal para 2024.Recuperado de: <a href="https://www.dof.gob.mx/" target="_blank">https://www.dof.gob.mx/</a></li>
              </ol>
            ';
          break;
        case 'sdp':
          $html_descripcion = '
              <h3 class="textoheader">Servicios digitales a traves de plataformas tecnológicas</h3>
              <p class="textoinformativo">Los servicios digitales a través de plataformas tecnológicas son aquellos que se prestan o distribuyen por medio de aplicaciones, páginas web o sistemas informáticos que operan en internet. Estos servicios pueden ser ofrecidos por residentes en México o en el extranjero, e incluyen actividades como:<br>Transmisión o descarga de contenido digital (video, música, ebooks, etc.).<br>Servicios de intermediación (Uber, Didi, Airbnb, Mercado Libre, etc.).<br>Enseñanza a distancia o software como servicio (SaaS).<br>Venta de bienes o servicios mediante plataformas digitales.<br>Fiscalmente, en México estos servicios están regulados principalmente en materia de IVA y retenciones, tanto para los usuarios como para los prestadores de servicios digitales, con o sin residencia en el país.],
                </p>
              <p class="textoinformativo">SUSTENTO LEGAL.</p>
              <ol class="textoinformativo">
                <li>Ley del Impuesto al Valor Agregado (LIVA).
                  <ol>
                    <li>Artículo 1, fracción IV: Grava la prestación de servicios digitales por residentes en el extranjero a receptores ubicados en México.</li>
                    <li>Artículo 18-B al 18-L: Definen qué son servicios digitales. <br>Establecen las obligaciones fiscales de los proveedores extranjeros. <br>Incluyen reglas sobre inscripción en el RFC, cobro y traslado del IVA, emisión de comprobantes y declaraciones.
                    </li>
                  </ol>
                </li>
                <li>Ley del Impuesto sobre la Renta (LISR).
                  <ol>
                    <li>Artículo 113-A a 113-G: Regulan la tributación de personas físicas que obtienen ingresos a través de plataformas digitales (por enajenación de bienes, prestación de servicios, hospedaje, transporte, etc.).<br>Establecen tasas de retención de ISR aplicables a estas actividades.</li>
                  </ol>
                </li>
                <li>Código Fiscal de la Federación (CFF).
                  <ol>
                    <li>Artículo 29 y 29-A: Obligan a emitir CFDI (comprobantes fiscales digitales por internet) también en operaciones realizadas por plataformas digitales.
                    </li>
                    <li>Resolución Miscelánea Fiscal (RMF) vigente. Reglas específicas para la retención y declaración del IVA e ISR por plataformas tecnológicas.</li>
                  </ol>
                </li>
              </ol>
              <ol class="footinformativo">
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto al Valor Agregado. Última reforma publicada DOF 08-11-2023. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LIVA.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Ley del Impuesto sobre la Renta. Última reforma publicada DOF 08-11-2023. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/LISR.pdf</a></li>
                <li>Cámara de Diputados del H. Congreso de la Unión. (2024). Código Fiscal de la Federación. Última reforma publicada DOF 01-01-2024. Recuperado de: <a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf" target="_blank">https://www.diputados.gob.mx/LeyesBiblio/pdf/8_010124.pdf</a></li>
                <li>Servicio de Administración Tributaria (SAT). (2024). Plataformas digitales - Obligaciones fiscales. Recuperado de:<a href="https://www.sat.gob.mx" target="_blank">https://www.sat.gob.mx</a></li>
              </ol>
              ';
          break;
        default:
          $html_descripcion = '';
          break;
      }
    }

    //$html_descripcion = $descripcion != '' ? '<div class="explain"><p style="margin: 0;"><strong>Descripcion</strong><br>'.$descripcion.'</p></div>' : '';
    $html = '
        <!DOCTYPE html>
        <html lang="en">

        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Document</title>
          <style>

            body {
              font-family: Arial, sans-serif;
              argin: 0%!important;
              padding: 0;
            }

            .explain{padding: 0;display: flex;margin: 0;flex-wrap: wrap;justify-content: flex-start;align-items: center;align-content: center;}
            div.explain h6{font-family: Arial, sans-serif;font-size: 17px;text-align: left;color: #353535;white-space: break-spaces !important;}
            div.explain h6.title{width: max-content;font-weight: bold;}
            div.explain h6.content{margin: 0;margin-bottom: 2px;width: max-content;}
              
            div.main table {
              width: 40%;
              border-collapse: collapse;
              margin-left: 60%;
              margin-top: 20px;
              text-align: right;
              font-size: 20px;
            }
              
            div.main td {
                padding: 8px 5px;
                border-bottom: 1px solid #ccc;
            }
            div.main .label {
                font-weight: bold;
                color: black;
            }

            h3.textoheader{
						  text-align: center; 
						  font-size: 25px; 
						}

						.textoinformativo{
						  text-align: justify; 
						  font-size: 20px; 
						  font-family: Arial, Helvetica, sans-serif;
						  list-style-type: number;
						}
              
						ol.footinformativo li{
						  text-align: left; 
						  font-size: 15px;
						  font-family: Arial, Helvetica, sans-serif;
						}
          </stle>
        </head>

        <body>
          <header class="information information-cpp">
            <table width="100%" style="margin:0!important;padding:0!important;">
              <tr>
                <td colspan="3" style="margin:0!important;padding:0!important;" align="center">
                  <h1 style="margin:0!important;padding:0!important;">Calculadora de retenci&oacute;n del ISR e IVA</h1>
                </td>
              </tr>
              <tr>
                <td align="center" style="width: 60%;text-align:left;">' . $perfil . '</td>
                <td align="center" style="width: 40%;text-align:right;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">' . date('d M, Y H:i:s', time()) . '</h3></td>
              </tr>
            </table>
          </header>
          <br>
          <main>
          </div>
          <div class="main">
            <table>
              <tr>
                <td></td>
                <td></td>
                <td class="label">Importe</td>
                <td>' . $retencion_importe . '</td>
              </tr>
              <tr>
                <td class="label">IVA al</td>
                <td>' . $iva_establecido_percent . '</td>
                <td class="label">+ % IVA</td>
                <td>' . $iva_establecido_view . '</td>
              </tr>
              <tr>
                <td></td>
                <td></td>
                <td class="label">Subtotal</td>
                <td>' . $retencion_subtotal_view . '</td>
              </tr>
              <tr>
                <td class="label">Retención LIVA</td>
                <td>' . $retencion_iva_liva_percent . '</td>
                <td class="label">- Retención IVA</td>
                <td>' . $retencion_iva_liva_view . '</td>
              </tr>
              <tr>
                <td class="label">Retención ISR (%)</td>
                <td>' . $retencion_isr_porcentaje . '</td>
                <td class="label">- Retención ISR</td>
                <td>' . $retencion_isr_view . '</td>
              </tr>
              <tr>
                <td></td>
                <td></td>
                <td class="label total">Total</td>
                <td class="total">' . $retencion_total_view . '</td>
              </tr>
            </table>
            ' . $html_descripcion . '
            <div style="width: 100%;position: absolute;bottom: 0;display: flex;justify-content: center;align-items: center;flex-direction: column;">
              <p class="footinformativo" style="text-align:center;margin: 0;">
                Este comprobante no tiene validez fiscal ni legal, y no sustituye la asesoría profesional. si requiere una interpretación legal o fiscal de este documento, consulte a un profesional certificado.
              </p>
              <p class="footinformativo" style="text-align:center;margin: 0;">
                SOS-M&eacute;xico no se hace responsable por el uso de la informaci&oacute;n obtenida mediante esta herramienta.
              </p>
            </div>
          </main>
        </body>
      </html>';
    $dompdf = \PDF::loadHtml($html);
    $dompdf->setPaper('A2', 'portrait');
    $dompdf->render();
    $contenidoPDF = $dompdf->stream("calculo de retenciones.pdf");
    return $contenidoPDF;
  }

  //anticipo
  //nomina_en_especie
  public function verNominaEnEspeciePdfHtml($token_nominas_especie){
    $JwtAuth = new \JwtAuth();
    if (isset($token_nominas_especie) && !empty($token_nominas_especie)) {
      $nomina_data = [];
      $listaEspecieNomina = DB::table("vhum_nominas_especie AS nomi_esp")
      ->join("main_empresas AS emp", "nomi_esp.nomina_esp_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("nomi_esp.token_nominas_especie",$token_nominas_especie)
      ->select(
        "nomi_esp.*",
        "emp.root_tkn",
        "people.img_perfil",
        "people.denominacion_rs",
        "people.paterno",
        "people.materno",
        "people.nombre",
        "people.abrev_nombre"
      )
      ->get();
      
      $idNominaEspecie = $listaEspecieNomina->pluck('token_nominas_especie')->filter()->unique()->toArray();
      $detailEspNominaMap = DB::table("vhum_nominas_especie_desglose AS desg_esp")
      ->join("vhum_empleados_catalogo AS nomi_trab", "desg_esp.trabajador", "=", "nomi_trab.id")
      ->join("sos_personas AS tname", "nomi_trab.empleado_name", "=", "tname.id")
      ->join("teci_bancos AS bank", "nomi_trab.trabcuentabanc_banco", "=", "bank.id")
      ->join("vhum_nominas_especie AS nomi_esp", "desg_esp.nomina_especie", "=", "nomi_esp.id")
      ->whereIn("nomi_esp.token_nominas_especie", $idNominaEspecie)
      ->select(
        'nomi_esp.token_nominas_especie AS esp_tkn',
        'desg_esp.token_especie_desglose',
        'desg_esp.nomina_esp_moneda',
        'desg_esp.total_en_especie',
        'nomi_trab.folio_pers',
        'nomi_trab.post_folio_pers',
        'tname.numero_de_seguridad_social',
        'nomi_trab.fecha_alta_en_empresa',
        'nomi_trab.salario_tipo',
        'nomi_trab.departamento',
        'nomi_trab.puesto',
        'nomi_trab.trabcuentabanc_cuenta',
        'nomi_trab.trabcuentabanc_clabe',
        'bank.token_bancos AS bancos_token',
        'bank.clave AS bancos_clave',
        'bank.nombre_comercial AS bancos_nombre_comercial',
        'tname.paterno',
        'tname.materno',
        'tname.nombre',
        'tname.rfc',
        'tname.curp',
      )
      ->get()->groupBy('esp_tkn');

      foreach ($listaEspecieNomina as $vnEsp) {
        $nomina_esp_folio = 'NOM-ES-'.$JwtAuth->generarFolio($vnEsp->nomina_esp_folio_interior).(!is_null($vnEsp->nomina_esp_subfolio) ? '-'.$vnEsp->nomina_esp_subfolio : '');

        if ($JwtAuth->desencriptar($vnEsp->img_perfil) == "empresa_desconocida.png") {
          $ruta_logo = 'public/settings/empresa_desconocida.png';
        } else {
          $ruta_logo = "public/root/$vnEsp->root_tkn/0007-core/".$JwtAuth->desencriptar($vnEsp->img_perfil);
        }
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path($ruta_logo));
        
        $nombreEmpresa = $vnEsp->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vnEsp->paterno, $vnEsp->materno, $vnEsp->nombre) : $JwtAuth->desencriptar($vnEsp->denominacion_rs);
        $name_abrev = $vnEsp->abrev_nombre;

        $esp_moneda = "";
        $nomina_desglose = [];
        $detailEspNominaLista = $detailEspNominaMap->get($vnEsp->token_nominas_especie) ?? collect([]);
        $contador = 1;
        $importe_especie_total = 0;
        foreach ($detailEspNominaLista as $vNomDetEsp) {
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vNomDetEsp->folio_pers).(!is_null($vNomDetEsp->post_folio_pers) ? '-'.$vNomDetEsp->post_folio_pers : '');
          $trabajador_name_paterno = ucwords($JwtAuth->desencriptar($vNomDetEsp->paterno));
          $trabajador_name_materno = ucwords($JwtAuth->desencriptar($vNomDetEsp->materno));
          $trabajador_name_nombre = ucwords($JwtAuth->desencriptar($vNomDetEsp->nombre));
          $trabajador_nombre = "$trabajador_name_paterno $trabajador_name_materno $trabajador_name_nombre";
          $numero_de_seguridad_social = !is_null($vNomDetEsp->numero_de_seguridad_social) && $vNomDetEsp->numero_de_seguridad_social != '' ? $vNomDetEsp->numero_de_seguridad_social : '';
          $rfc = !is_null($vNomDetEsp->rfc) && $vNomDetEsp->rfc != '' ? $JwtAuth->desencriptar($vNomDetEsp->rfc) : '';
          $curp = !is_null($vNomDetEsp->curp) && $vNomDetEsp->curp != '' ? $JwtAuth->desencriptar($vNomDetEsp->curp) : '';
          $fecha_alta_en_empresa = !is_null($vNomDetEsp->fecha_alta_en_empresa) && $vNomDetEsp->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vNomDetEsp->fecha_alta_en_empresa) : '';
          $salario_tipo = !is_null($vNomDetEsp->salario_tipo) && $vNomDetEsp->salario_tipo != '' ? $vNomDetEsp->salario_tipo : '';

          $cuenta_descifrada = '';
          $cuenta_descifrada_last_digitos = '';
          if (!is_null($vNomDetEsp->trabcuentabanc_cuenta) && $vNomDetEsp->trabcuentabanc_cuenta != '') {
            $cuenta_descifrada = $JwtAuth->decryptBankAccount($vNomDetEsp->trabcuentabanc_cuenta);
            $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomDetEsp->trabcuentabanc_cuenta), -4);
            $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
          }
          
          $clabe_descifrada = '';
          $clabe_descifrada_last_digitos = '';
          if (!is_null($vNomDetEsp->trabcuentabanc_clabe) && $vNomDetEsp->trabcuentabanc_clabe != '') {
            $clabe_descifrada = $JwtAuth->decryptBankAccount($vNomDetEsp->trabcuentabanc_clabe);
            $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomDetEsp->trabcuentabanc_clabe), -4);
            $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
          }

          $nomina_moneda_name = $vNomDetEsp->nomina_esp_moneda;
          $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vNomDetEsp->nomina_esp_moneda);

          $esp_moneda = $vNomDetEsp->nomina_esp_moneda;
          $importe_especie_total += floatval($vNomDetEsp->total_en_especie);
          
          $nomina_desglose[] = [
            "nomina_clave" => $contador,
            "token_nominas_especie" => $vNomDetEsp->token_especie_desglose,
            //nomina_empleado_nombre
            "nomina_empleado" => $folio_empleado." - ".$trabajador_nombre,
            //nomina_moneda
            "nomina_moneda" => $vNomDetEsp->nomina_esp_moneda,
            //nomina_empleado_cbankBanco
            "nomina_empleado_cbankBancoToken" => $vNomDetEsp->bancos_token,
            "nomina_empleado_cbankBancoNombre" => $vNomDetEsp->bancos_clave." ".$vNomDetEsp->bancos_nombre_comercial,
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
            "nomina_empleado_departamento" => !is_null($vNomDetEsp->departamento) ? $JwtAuth->desencriptar($vNomDetEsp->departamento) : '',
            //nomina_empleado_puesto
            "nomina_empleado_puesto" => !is_null($vNomDetEsp->puesto) ? $JwtAuth->desencriptar($vNomDetEsp->puesto) : '',
            //nomina_empleado_tipo_salario
            "nomina_empleado_tipo_salario" => $salario_tipo,
            //nomina_salario_diario
            "total_en_especie" => number_format($vNomDetEsp->total_en_especie,$nomina_moneda_decimales,'.',''),
            "total_en_especie_format" => "$".number_format($vNomDetEsp->total_en_especie,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name"
          ];
          $contador++;
        }

        $moneda_decimales = $JwtAuth->getMonedaAPI($esp_moneda);
        
        $nomina_data = [
          "estilos_css" => $JwtAuth->css_pdf(),
          "logo_emp" => $logoEmp,
          "company_name_large" => "$name_abrev - $nombreEmpresa",
          "token_nominas_especie" => $vnEsp->token_nominas_especie,
          "nomina_esp_folio" => $nomina_esp_folio,
          "nomina_esp_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vnEsp->nomina_esp_fecha_contabilizacion),
          //moneda
          "esp_moneda" => $esp_moneda,
          "esp_moneda_decimales" => $moneda_decimales,
          "nomina_desglose" => $nomina_desglose,
          "importe_especie_total" => "$".number_format($importe_especie_total,$moneda_decimales,'.',',')." $esp_moneda"
        ];
      }
      $pdf = \PDF::loadView('pdf.plantilla_nominas_especie',$nomina_data);
      $pdf->setPaper('a2', 'landscape')->stream();
      return $pdf->stream($nomina_esp_folio . ".pdf");
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  //nomina_en_efectivo
  public function verNominaEnEfectivoPdfHtml($token_nominas_periodos){
    $JwtAuth = new \JwtAuth();
    if (isset($token_nominas_periodos) && !empty($token_nominas_periodos)) {
      $nomina_data = [];
      $queryRepNomina = DB::table("vhum_nominas_main AS nmain")
      ->join("main_empresas AS emp", "nmain.nomina_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("nmain.token_nominas_periodos",$token_nominas_periodos)
      ->select(
        "nmain.*",
        "emp.root_tkn",
        "people.img_perfil",
        "people.denominacion_rs",
        "people.paterno",
        "people.materno",
        "people.nombre",
        "people.abrev_nombre"
        )
      ->get();

      foreach ($queryRepNomina as $vNomi) {
        $folio_nomina = 'NOM-EF-'.$JwtAuth->generarFolio($vNomi->nomina_folio_interior).(!is_null($vNomi->nomina_subfolio) ? '-'.$vNomi->nomina_subfolio : '');

        if ($JwtAuth->desencriptar($vNomi->img_perfil) == "empresa_desconocida.png") {
          $ruta_logo = 'public/settings/empresa_desconocida.png';
        } else {
          $ruta_logo = "public/root/$vNomi->root_tkn/0007-core/".$JwtAuth->desencriptar($vNomi->img_perfil);
        }
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path($ruta_logo));
        
        $nombreEmpresa = $vNomi->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vNomi->paterno, $vNomi->materno, $vNomi->nombre) : $JwtAuth->desencriptar($vNomi->denominacion_rs);
        $name_abrev = $vNomi->abrev_nombre;
        
        $totales_nomina_reporte_efectivo = DB::table("vhum_nominas_recibos AS nrec")
        ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->sum('nrec.total_efectivo');
        
        $moneda_nomina_recibos = DB::table("vhum_nominas_recibos AS nrec")
        ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->value('nrec.nomina_moneda');

        $totales_nomina_pago_efectivo = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
        ->whereNull("order.nomina_en_especie")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->sum('pay.monto_pago');

        $totales_nomina_saldo_efectivo = $totales_nomina_reporte_efectivo - $totales_nomina_pago_efectivo;
        
        $queryNominaEfectOrdPago = DB::table("fnzs_pagos_orden AS order")
        ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
        ->whereNull("order.nomina_en_especie")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->select('order.token_ordenPago', 'order.folio_ordenPago')
        ->first();
        $nomina_efectivo_ord_pago_token = $queryNominaEfectOrdPago ? $queryNominaEfectOrdPago->token_ordenPago :'';
        $nomina_efectivo_ord_pago_folio = $queryNominaEfectOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEfectOrdPago->folio_ordenPago) :'';

        $totales_nomina_reporte_especie = DB::table("vhum_nominas_recibos AS nrec")
        ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->sum('nrec.total_en_especie');

        $totales_nomina_pago_especie = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
        ->whereNotNull("order.nomina_en_especie")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->sum('pay.monto_pago');

        $totales_nomina_saldo_especie = $totales_nomina_reporte_especie - $totales_nomina_pago_especie;

        $queryNominaEspeOrdPago = DB::table("fnzs_pagos_orden AS order")
        ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
        ->whereNotNull("order.nomina_en_especie")
        ->where('nmain.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->select('order.token_ordenPago', 'order.folio_ordenPago')
        ->first();
        $nomina_especie_ord_pago_token = $queryNominaEspeOrdPago ? $queryNominaEspeOrdPago->token_ordenPago :'';
        $nomina_especie_ord_pago_folio = $queryNominaEspeOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEspeOrdPago->folio_ordenPago) :'';
        
        $nomina_totales_salario_diario = 0;
        $nomina_totales_salario_integrado = 0;
        $nomina_totales_dias_trabajados = 0;
        $nomina_totales_faltas = 0;
        $nomina_totales_sueldo = 0;
        $nomina_totales_horas_extras_dobles = 0;
        $nomina_totales_aguinaldo = 0;
        $nomina_totales_horas_extras_triples = 0;
        $nomina_totales_vacaciones = 0;
        $nomina_totales_prima_vacacional = 0;
        $nomina_totales_reparto_de_utilidades = 0;
        $nomina_totales_despensa = 0;
        $nomina_totales_premios_de_asistencia = 0;
        $nomina_totales_premios_de_puntualidad = 0;
        $nomina_totales_prima_dominical = 0;
        $nomina_totales_bno_extra_x_comision_otro_edo = 0;
        $nomina_totales_indemnizacion = 0;
        $nomina_totales_prima_de_antiguedad = 0;
        $nomina_totales_otras_percepciones = 0;
        $nomina_totales_otros_pagos = 0;
        $nomina_totales_total_percepciones = 0;
        $nomina_totales_isr_ajustado_por_subsidio = 0;
        $nomina_totales_total_isr = 0;
        $nomina_totales_total_imss = 0;
        $nomina_totales_credito_fonacot = 0;
        $nomina_totales_credito_infonavit = 0;
        $nomina_totales_subsidio_empleo = 0;
        $nomina_totales_otras_deducciones = 0;
        $nomina_totales_total_deducciones = 0;
        $nomina_totales_total_efectivo = 0;
        $nomina_totales_total_en_especie = 0;
        $nomina_totales_neto_pagado = 0;
        $nomina_totales_horas_por_dia = 0;
        $nomina_totales_salario_por_hora = 0;

        $detalleNominaQuery = DB::table("vhum_nominas_recibos AS recibos")
        ->join("vhum_empleados_catalogo AS nomi_trab", "recibos.trabajador", "=", "nomi_trab.id")
        ->join("sos_personas AS people", "nomi_trab.empleado_name", "=", "people.id")
        ->join("teci_bancos AS bank", "nomi_trab.trabcuentabanc_banco", "=", "bank.id")
        ->join("vhum_nominas_main AS nomi", "recibos.nomina_main", "=", "nomi.id")
        ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "recibos.nomina_registro_patronal", "c_trab.id")
        ->where('nomi.token_nominas_periodos',$vNomi->token_nominas_periodos)
        ->get();
        $detalleNominaLista = array();
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
          $fecha_alta_en_empresa = !is_null($vNomRec->fecha_alta_en_empresa) && $vNomRec->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vNomRec->fecha_alta_en_empresa) : '';
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

        $nomina_data = [
          "estilos_css" => $JwtAuth->css_pdf(),
          "logo_emp" => $logoEmp,
          "company_name_large" => "$name_abrev - $nombreEmpresa",
          "token_compras" => $vNomi->token_nominas_periodos,
          "folio_nomina" => $folio_nomina,
          "nomina_numero" => $vNomi->nomina_numero,
          "moneda_nomina" => $moneda_nomina_recibos,
          "nomina_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vNomi->nomina_fecha_contabilizacion),
          "nomina_observaciones" => $JwtAuth->desencriptar($vNomi->nomina_observaciones),
          //efectivo
          "nomina_reporte_efectivo" => "$".number_format($totales_nomina_reporte_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_pago_efectivo" => "$".number_format($totales_nomina_pago_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_saldo_efectivo" => "$".number_format($totales_nomina_saldo_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_efectivo_ord_pago_token" => $nomina_efectivo_ord_pago_token,
          "nomina_efectivo_ord_pago_folio" => $nomina_efectivo_ord_pago_folio,
          //especie
          "nomina_reporte_especie" => "$".number_format($totales_nomina_reporte_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_pago_especie" => "$".number_format($totales_nomina_pago_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_saldo_especie" => "$".number_format($totales_nomina_saldo_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_especie_ord_pago_token" => $nomina_especie_ord_pago_token,
          "nomina_especie_ord_pago_folio" => $nomina_especie_ord_pago_folio,
          "desglose" => $detalleNominaLista,
          "nomina_totales_salario_diario" => "$".number_format($nomina_totales_salario_diario,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_salario_integrado" => "$".number_format($nomina_totales_salario_integrado,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_dias_trabajados" => "$".number_format($nomina_totales_dias_trabajados,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_faltas" => "$".number_format($nomina_totales_faltas,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_sueldo" => "$".number_format($nomina_totales_sueldo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_horas_extras_dobles" => "$".number_format($nomina_totales_horas_extras_dobles,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_aguinaldo" => "$".number_format($nomina_totales_aguinaldo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_horas_extras_triples" => "$".number_format($nomina_totales_horas_extras_triples,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_vacaciones" => "$".number_format($nomina_totales_vacaciones,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_prima_vacacional" => "$".number_format($nomina_totales_prima_vacacional,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_reparto_de_utilidades" => "$".number_format($nomina_totales_reparto_de_utilidades,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_despensa" => "$".number_format($nomina_totales_despensa,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_premios_de_asistencia" => "$".number_format($nomina_totales_premios_de_asistencia,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_premios_de_puntualidad" => "$".number_format($nomina_totales_premios_de_puntualidad,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_prima_dominical" => "$".number_format($nomina_totales_prima_dominical,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_bno_extra_x_comision_otro_edo" => "$".number_format($nomina_totales_bno_extra_x_comision_otro_edo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_indemnizacion" => "$".number_format($nomina_totales_indemnizacion,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_prima_de_antiguedad" => "$".number_format($nomina_totales_prima_de_antiguedad,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_otras_percepciones" => "$".number_format($nomina_totales_otras_percepciones,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_otros_pagos" => "$".number_format($nomina_totales_otros_pagos,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_total_percepciones" => "$".number_format($nomina_totales_total_percepciones,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_isr_ajustado_por_subsidio" => "$".number_format($nomina_totales_isr_ajustado_por_subsidio,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_total_isr" => "$".number_format($nomina_totales_total_isr,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_total_imss" => "$".number_format($nomina_totales_total_imss,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_credito_fonacot" => "$".number_format($nomina_totales_credito_fonacot,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_credito_infonavit" => "$".number_format($nomina_totales_credito_infonavit,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_subsidio_empleo" => "$".number_format($nomina_totales_subsidio_empleo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_otras_deducciones" => "$".number_format($nomina_totales_otras_deducciones,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_total_deducciones" => "$".number_format($nomina_totales_total_deducciones,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_total_efectivo" => "$".number_format($nomina_totales_total_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_total_en_especie" => "$".number_format($nomina_totales_total_en_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_neto_pagado" => "$".number_format($nomina_totales_neto_pagado,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_horas_por_dia" => "$".number_format($nomina_totales_horas_por_dia,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
          "nomina_totales_salario_por_hora" => "$".number_format($nomina_totales_salario_por_hora,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
        ];
      }
      $pdf = \PDF::loadView('pdf.plantilla_nominas_efectivo',$nomina_data);
      $pdf->setPaper('a2', 'landscape')->stream();
      return $pdf->stream($folio_nomina . ".pdf");
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  //impuestos_sobre_nomina
  public function verImpuestosSobreNominaPdfHtml($nomi_imp_token){
    $JwtAuth = new \JwtAuth();
    if (isset($nomi_imp_token) && !empty($nomi_imp_token)) {
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("nomImp.nomi_imp_token",$nomi_imp_token)
      ->select(
        "nomImp.nomi_imp_token",
        "nomImp.nomi_imp_fecha_registro",
        "nomImp.nomi_imp_folio_interior",
        "nomImp.nomi_imp_subfolio",
        "nomImp.nomi_imp_fecha_contabilizacion",
        "nomImp.nomi_imp_estado",
        "nomImp.nomi_imp_ejercicio",
        "nomImp.nomi_imp_periodo_inicio",
        "nomImp.nomi_imp_periodo_fin",
        "nomImp.nomi_imp_fecha_pago",
        "nomImp.nomi_imp_fecha_vencimiento",
        "nomImp.nomi_imp_fecha_presentacion",
        "nomImp.nomi_imp_tipo_declaracion",
        "nomImp.nomi_imp_moneda",
        "nomImp.nomi_imp_total_remuneraciones_erogadas",
        "nomImp.nomi_imp_porcent_sobre_total_remuneraciones_erogadas",
        "nomImp.nomi_imp_complementarias_impuesto_a_cargo",
        "nomImp.nomi_imp_complementarias_saldo_a_favor",
        "nomImp.nomi_imp_impuesto_actualizado",
        "nomImp.nomi_imp_impuesto_descuento",
        "nomImp.nomi_imp_impuesto_recargos",
        "nomImp.nomi_imp_impuesto_recargos_condonados",
        "nomImp.nomi_imp_subsi_n_resolu_impuesto_pagar",
        "nomImp.nomi_imp_subsi_n_resolu_recargos",
        "nomImp.nomi_imp_compensa_n_resolucion",
        "nomImp.nomi_imp_compensa_n_resolu_recargos",
        "nomImp.nomi_imp_impuesto_total_a_pagar",
        "nomImp.nomi_imp_impuesto_saldo_a_favor",
        "nomImp.observaciones",
        "emp.root_tkn",
        "people.img_perfil",
        "people.denominacion_rs",
        "people.paterno",
        "people.materno",
        "people.nombre",
        "people.abrev_nombre"
      )
      ->get();

      $idImpEstado = $queryImpNomina->pluck('nomi_imp_estado')->filter()->unique()->toArray();
      $impEstadoMap = DB::table('fnzs_catalogos_fed_estados_municipios')->whereIn('id', $idImpEstado)->get()->keyBy('id');
      
      foreach ($queryImpNomina as $vIsn) {
        $nomi_imp_folio = $vIsn->nomi_imp_folio_interior;
        $post_folio_nomina = $vIsn->nomi_imp_subfolio;
        $folio_is_nomina = 'NOM-IMP-'.$JwtAuth->generarFolio($nomi_imp_folio).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');

        if ($JwtAuth->desencriptar($vIsn->img_perfil) == "empresa_desconocida.png") {
          $ruta_logo = 'public/settings/empresa_desconocida.png';
        } else {
          $ruta_logo = "public/root/$vIsn->root_tkn/0007-core/".$JwtAuth->desencriptar($vIsn->img_perfil);
        }
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path($ruta_logo));
        
        $nombreEmpresa = $vIsn->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vIsn->paterno, $vIsn->materno, $vIsn->nombre) : $JwtAuth->desencriptar($vIsn->denominacion_rs);
        $name_abrev = $vIsn->abrev_nombre;

        $queryImpEstado = $impEstadoMap->get($vIsn->nomi_imp_estado);
        $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

        $periodoCarbonI = ucfirst(Carbon::createFromTimestamp($vIsn->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F'));
        $periodoCarbonF = ucfirst(Carbon::createFromTimestamp($vIsn->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F'));
        $nomi_imp_moneda = $vIsn->nomi_imp_moneda;
        $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($nomi_imp_moneda);

        $isn_data = [
          "estilos_css" => $JwtAuth->css_pdf(),
          "logo_emp" => $logoEmp,
          "company_name_large" => "$name_abrev - $nombreEmpresa",
          "nomi_imp_token" => $vIsn->nomi_imp_token,
          "nomi_imp_folio" => $folio_is_nomina,
          "nomi_imp_fecha_contabilizacion" => date('Y-m-d',$vIsn->nomi_imp_fecha_contabilizacion),
          "nomi_imp_estado" => $estado_all_info,//DB::table("fnzs_catalogos_fed_estados_municipios")->where("fed_est_mun_token", $estado)->value("id"),
          "nomi_imp_ejercicio" => $vIsn->nomi_imp_ejercicio,
          "nomi_imp_periodo" => $periodoCarbonI == $periodoCarbonF ? $periodoCarbonF : $periodoCarbonI." - ".$periodoCarbonF,
          "nomi_imp_fecha_pago" => $vIsn->nomi_imp_fecha_pago,
          "nomi_imp_fecha_vencimiento" => date('Y-m-d',$vIsn->nomi_imp_fecha_vencimiento),
          "nomi_imp_fecha_presentacion" => date('Y-m-d',$vIsn->nomi_imp_fecha_presentacion),
          "nomi_imp_tipo_declaracion" => $vIsn->nomi_imp_tipo_declaracion,
          "nomi_imp_moneda" => $nomi_imp_moneda,
          "nomi_imp_total_remuneraciones_erogadas" => "$".number_format($vIsn->nomi_imp_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => "$".number_format($vIsn->nomi_imp_porcent_sobre_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_complementarias_impuesto_a_cargo" => "$".number_format($vIsn->nomi_imp_complementarias_impuesto_a_cargo,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_complementarias_saldo_a_favor" => "$".number_format($vIsn->nomi_imp_complementarias_saldo_a_favor,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_impuesto_actualizado" => "$".number_format($vIsn->nomi_imp_impuesto_actualizado,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_impuesto_descuento" => "$".number_format($vIsn->nomi_imp_impuesto_descuento,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_impuesto_recargos" => "$".number_format($vIsn->nomi_imp_impuesto_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_impuesto_recargos_condonados" => "$".number_format($vIsn->nomi_imp_impuesto_recargos_condonados,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_subsi_n_resolu_impuesto_pagar" => "$".number_format($vIsn->nomi_imp_subsi_n_resolu_impuesto_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_subsi_n_resolu_recargos" => "$".number_format($vIsn->nomi_imp_subsi_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_compensa_n_resolucion" => "$".number_format($vIsn->nomi_imp_compensa_n_resolucion,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_compensa_n_resolu_recargos" => "$".number_format($vIsn->nomi_imp_compensa_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_impuesto_total_a_pagar" => "$".number_format($vIsn->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_impuesto_saldo_a_favor" => "$".number_format($vIsn->nomi_imp_impuesto_saldo_a_favor,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "observaciones" => $JwtAuth->desencriptar($vIsn->observaciones)
        ];
      }
      $pdf = \PDF::loadView('pdf.plantilla_impuestos_isn',$isn_data);
      $pdf->setPaper('a2', 'portrait')->stream();
      return $pdf->stream($folio_is_nomina . ".pdf");
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verImpuestosSobreNominaFactXML($nomi_imp_token){
    $JwtAuth = new \JwtAuth();
    if (isset($nomi_imp_token) && !empty($nomi_imp_token)) {
      $queryDocsISN = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where("nomImp.nomi_imp_token",$nomi_imp_token)
      ->get();

      foreach ($queryDocsISN as $vDoc) {
        $folio_nomina = $vDoc->nomi_imp_folio_interior;
        //echo $folio_nomina;
        $post_folio_nomina = $vDoc->nomi_imp_subfolio;
        $folio_interior = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');

        $name_doc = $JwtAuth->desencriptar($vDoc->nomi_imp_fact_xml);
        $filepath = $vDoc->root_tkn."/0004-vhm/impuestos_sobre_nomina/$folio_interior/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verImpuestosSobreNominaFactPDF($nomi_imp_token){
    $JwtAuth = new \JwtAuth();
    if (isset($nomi_imp_token) && !empty($nomi_imp_token)) {
      $queryDocsISN = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where("nomImp.nomi_imp_token",$nomi_imp_token)
      ->get();

      foreach ($queryDocsISN as $vDoc) {
        $folio_nomina = $vDoc->nomi_imp_folio_interior;
        //echo $folio_nomina;
        $post_folio_nomina = $vDoc->nomi_imp_subfolio;
        $folio_interior = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');

        $name_doc = $JwtAuth->desencriptar($vDoc->nomi_imp_fact_pdf);
        $filepath = $vDoc->root_tkn."/0004-vhm/impuestos_sobre_nomina/$folio_interior/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verImpuestosSobreNominaDocsAdjuntos($folio_isn, $token_documento){
    $JwtAuth = new \JwtAuth();
    if (isset($folio_isn) && !empty($folio_isn) && isset($token_documento) && !empty($token_documento)) {
      $queryDocsISN = DB::table("sos_documentos AS docs")
      ->join("vhum_nominas_impuestos AS isn", "docs.impuesto_sobre_nomina", "=", "isn.id")
      ->join("main_empresas AS emp", "isn.nomina_empresa", "=", "emp.id")
      ->where([
        "docs.status_documento" => TRUE,
        "docs.token_documento" => $token_documento
      ])
      ->get();

      foreach ($queryDocsISN as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->nombre_documento);
        $filepath = $vDoc->root_tkn . "/0004-vhm/impuestos_sobre_nomina/$vDoc->nomi_imp_fecha_registro-$folio_isn/anexos/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  //aportaciones_de_seguridad_social
  public function verAportacionesSeSeguridadSocialPdfHtml($aport_ssocial_token){
    $JwtAuth = new \JwtAuth();
    if (isset($aport_ssocial_token) && !empty($aport_ssocial_token)) {
      $queryIMSSAportacion = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
      ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "social_main.aport_ssocial_registro_patronal", "c_trab.id")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("social_main.aport_ssocial_token",$aport_ssocial_token)
      ->select(
        "social_main.id AS idAport",
        "social_main.aport_ssocial_token",
        "social_main.aport_ssocial_fecha_registro",
        "social_main.aport_ssocial_folio_interior",
        "social_main.aport_ssocial_subfolio",
        "social_main.aport_ssocial_fecha_contabilizacion",
        "social_main.aport_ssocial_fecha_presentacion",
        "social_main.aport_ssocial_registro_patronal",
        "social_main.periodo_pago_seguros_imss_anio",
        "social_main.periodo_pago_seguros_imss_mes",
        "social_main.pago_rcv_infonavit_inicio",
        "social_main.pago_rcv_infonavit_fin",
        "social_main.folio_sua",
        "social_main.aport_ssocial_moneda",
        "social_main.clave_recepcion_archivo_pago",
        "social_main.propuesta_fecha_limite_pago",
        "social_main.linea_captura_sipare",
        "social_main.propuesta_s_m_g_d_f",
        "social_main.propuesta_fecha_salario_minimo_pago",
        "social_main.propuesta_valor_uma",
        "social_main.propuesta_num_de_cotizantes",
        "social_main.propuesta_num_dias_a_cotizar",
        "social_main.propuesta_num_de_acreditados",
        "social_main.observaciones",
        "c_trab.centrotrab_uuid",
        "c_trab.centrotrab_clave_registro_patronal_imss",
        "emp.root_tkn",
        "people.img_perfil",
        "people.denominacion_rs",
        "people.paterno",
        "people.materno",
        "people.nombre",
        "people.abrev_nombre"
      )
      ->get();

      $idAportSsocial = $queryIMSSAportacion->pluck('idAport')->filter()->unique()->toArray();
      $cuotasDesgloseMap = DB::table("imss_cuotas_detalle")
      ->whereIn('aportaciones_main',$idAportSsocial)
      ->select(
        'id',
	      'aportaciones_main AS idAport',
	      'type',
	      'label',
	      'patronal',
	      'obrera',
	      'total'
      )
      ->get()->groupBy('idAport');

      foreach ($queryIMSSAportacion as $vIMMS) {
        if ($JwtAuth->desencriptar($vIMMS->img_perfil) == "empresa_desconocida.png") {
          $ruta_logo = 'public/settings/empresa_desconocida.png';
        } else {
          $ruta_logo = "public/root/$vIMMS->root_tkn/0007-core/".$JwtAuth->desencriptar($vIMMS->img_perfil);
        }
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path($ruta_logo));
        
        $nombreEmpresa = $vIMMS->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vIMMS->paterno, $vIMMS->materno, $vIMMS->nombre) : $JwtAuth->desencriptar($vIMMS->denominacion_rs);
        $name_abrev = $vIMMS->abrev_nombre;

        $folio_interior = $vIMMS->aport_ssocial_folio_interior;
        $post_folio = $vIMMS->aport_ssocial_subfolio;
        $aport_ssocial_moneda = $vIMMS->aport_ssocial_moneda;
        $aport_ssocial_moneda_decimales = $JwtAuth->getMonedaAPI($aport_ssocial_moneda);
  
        $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');

        $periodo_pago_seguros_imss = !is_null($vIMMS->periodo_pago_seguros_imss_anio) && !is_null($vIMMS->periodo_pago_seguros_imss_mes) ? ucfirst(Carbon::create($vIMMS->periodo_pago_seguros_imss_anio, $vIMMS->periodo_pago_seguros_imss_mes, 1)->locale('es')->isoFormat('MMMM YYYY')) : '';
        $pago_rcv_infonavit_inicio = !is_null($vIMMS->pago_rcv_infonavit_inicio) ? ucfirst(Carbon::createFromTimestamp($vIMMS->pago_rcv_infonavit_inicio)->locale('es')->translatedFormat('F')) : '';
        $pago_rcv_infonavit_fin = !is_null($vIMMS->pago_rcv_infonavit_fin) ? ucfirst(Carbon::createFromTimestamp($vIMMS->pago_rcv_infonavit_fin)->locale('es')->translatedFormat('F')) : '';
        $pago_rcv_infonavit = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' ? "$pago_rcv_infonavit_inicio - $pago_rcv_infonavit_fin" : '';
        
        $listCuotasDesglose = array();
        $queryCuotasDesglose = $cuotasDesgloseMap->get($vIMMS->idAport);
        foreach ($queryCuotasDesglose as $vDesgCuot) {
          $tipo_label = (stripos($vDesgCuot->label, 'SUBTOTAL') !== false) ? 'subtotal' : 'input';
          $desg_row = array(
            "type" => $tipo_label,
            "label" => $vDesgCuot->label,
            "patronal" => number_format($vDesgCuot->patronal,$aport_ssocial_moneda_decimales,'.',''),
            "obrera" => number_format($vDesgCuot->obrera,$aport_ssocial_moneda_decimales,'.',''),
            "total" => number_format($vDesgCuot->total,$aport_ssocial_moneda_decimales,'.',''),
          );
          $listCuotasDesglose[] = $desg_row;
        }

        array_unshift($listCuotasDesglose,[
          "type" => "section",
          "label" => "ENFERMEDADES Y MATERNIDAD",
        ]);

        for ($dc=0; $dc < count($listCuotasDesglose); $dc++) { 
          if (isset($listCuotasDesglose[$dc]['label']) && $listCuotasDesglose[$dc]['label'] === 'SUBTOTAL RCV') {
            array_splice($listCuotasDesglose,$dc + 1, 0, [[
              "type" => "label_aport",
              "label" => ""
            ]]);
            break;
          }
        }
        
        $totales_cuotas_patronales = DB::table("imss_cuotas_detalle AS imsDet")
        ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
        ->where('social_main.aport_ssocial_token',$vIMMS->aport_ssocial_token)
        ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
        ->sum('imsDet.patronal');

        $totales_cuotas_obreras = DB::table("imss_cuotas_detalle AS imsDet")
        ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
        ->where('social_main.aport_ssocial_token',$vIMMS->aport_ssocial_token)
        ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
        ->sum('imsDet.obrera');

        $totales_cuotas_totales = DB::table("imss_cuotas_detalle AS imsDet")
        ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
        ->where('social_main.aport_ssocial_token',$vIMMS->aport_ssocial_token)
        ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
        ->sum('imsDet.total');

        $buy_data = [
          "estilos_css" => $JwtAuth->css_pdf(),
          "logo_emp" => $logoEmp,
          "company_name_large" => "$name_abrev - $nombreEmpresa",
          "aport_ssocial_token" => $vIMMS->aport_ssocial_token,
          "aport_ssocial_folio" => $folio_aport,
          "aport_ssocial_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vIMMS->aport_ssocial_fecha_contabilizacion),
          "aport_ssocial_fecha_presentacion" => date('Y-m-d',$vIMMS->aport_ssocial_fecha_presentacion),
          "aport_ssocial_registro_patronal" => $vIMMS->centrotrab_clave_registro_patronal_imss,
          "periodo_pago_seguros_imss" => $periodo_pago_seguros_imss,
          "pago_rcv_infonavit" => $pago_rcv_infonavit,
          "folio_sua" => $vIMMS->folio_sua,
          "clave_recepcion_archivo_pago" => $vIMMS->clave_recepcion_archivo_pago,
          "propuesta_fecha_limite_pago" => date('Y-m-d',$vIMMS->propuesta_fecha_limite_pago),
          "linea_captura_sipare" => $vIMMS->linea_captura_sipare,
          "propuesta_s_m_g_d_f" => "$ ".number_format($vIMMS->propuesta_s_m_g_d_f,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "propuesta_fecha_salario_minimo_pago" => date('Y-m-d',$vIMMS->propuesta_fecha_salario_minimo_pago),
          "propuesta_valor_uma" => "$ ".number_format($vIMMS->propuesta_valor_uma,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "propuesta_num_de_cotizantes" => $vIMMS->propuesta_num_de_cotizantes,
          "propuesta_num_dias_a_cotizar" => $vIMMS->propuesta_num_dias_a_cotizar,
          "propuesta_num_de_acreditados" => $vIMMS->propuesta_num_de_acreditados,
          "cuotasDesglose" => $listCuotasDesglose,
          "cuotas_patronales" => "$ ".number_format($totales_cuotas_patronales,$aport_ssocial_moneda_decimales,'.','')." $aport_ssocial_moneda",
          "cuotas_obreras" => "$ ".number_format($totales_cuotas_obreras,$aport_ssocial_moneda_decimales,'.','')." $aport_ssocial_moneda",
          "cuotas_totales" => "$ ".number_format($totales_cuotas_totales,$aport_ssocial_moneda_decimales,'.','')." $aport_ssocial_moneda",
          "observaciones" => $JwtAuth->desencriptar($vIMMS->observaciones),
        ];
      }
      $pdf = \PDF::loadView('pdf.plantilla_aportaciones_seguridad_social',$buy_data);
      $pdf->setPaper('a2', 'portrait')->stream();
      return $pdf->stream($folio_aport . ".pdf");
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verAportSegSocialImssFactXML($aport_ssocial_token){
    $JwtAuth = new \JwtAuth();
    if (isset($aport_ssocial_token) && !empty($aport_ssocial_token)) {
      $queryDocsAportSSocial = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->where("social_main.aport_ssocial_token",$aport_ssocial_token)
      ->get();

      foreach ($queryDocsAportSSocial as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->aport_ssocial_fact_xml);
        $folio_interior = $vDoc->aport_ssocial_folio_interior;
        $post_folio = $vDoc->aport_ssocial_subfolio;
        $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
        $filepath = $vDoc->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$vDoc->aport_ssocial_fecha_registro-$folio_aport/anexos/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verAportSegSocialImssFactPDF($aport_ssocial_token){
    $JwtAuth = new \JwtAuth();
    if (isset($aport_ssocial_token) && !empty($aport_ssocial_token)) {
      $queryDocsAportSSocial = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->where("social_main.aport_ssocial_token",$aport_ssocial_token)
      ->get();

      foreach ($queryDocsAportSSocial as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->aport_ssocial_fact_pdf);
        $folio_interior = $vDoc->aport_ssocial_folio_interior;
        $post_folio = $vDoc->aport_ssocial_subfolio;
        $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
        $filepath = $vDoc->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$vDoc->aport_ssocial_fecha_registro-$folio_aport/anexos/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verAportSegSocialInfonavitFactXML($aport_ssocial_token){
    $JwtAuth = new \JwtAuth();
    if (isset($aport_ssocial_token) && !empty($aport_ssocial_token)) {
      $queryDocsAportSSocial = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->where("social_main.aport_ssocial_token",$aport_ssocial_token)
      ->get();

      foreach ($queryDocsAportSSocial as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->aport_ssocial_infonavit_xml);
        $folio_interior = $vDoc->aport_ssocial_folio_interior;
        $post_folio = $vDoc->aport_ssocial_subfolio;
        $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
        $filepath = $vDoc->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$vDoc->aport_ssocial_fecha_registro-$folio_aport/anexos/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verAportSegSocialInfonavitFactPDF($aport_ssocial_token){
    $JwtAuth = new \JwtAuth();
    if (isset($aport_ssocial_token) && !empty($aport_ssocial_token)) {
      $queryDocsAportSSocial = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->where("social_main.aport_ssocial_token",$aport_ssocial_token)
      ->get();

      foreach ($queryDocsAportSSocial as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->aport_ssocial_infonavit_pdf);
        $folio_interior = $vDoc->aport_ssocial_folio_interior;
        $post_folio = $vDoc->aport_ssocial_subfolio;
        $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
        $filepath = $vDoc->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$vDoc->aport_ssocial_fecha_registro-$folio_aport/anexos/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  public function verAportSegSocialDocsAdjuntos($folio_aport, $token_documento){
    $JwtAuth = new \JwtAuth();
    if (isset($folio_aport) && !empty($folio_aport) && isset($token_documento) && !empty($token_documento)) {
      $queryDocsAportSSocial = DB::table("sos_documentos AS docs")
      ->join("vhum_aportaciones_seguridad_social_main AS social_main", "docs.aportacion_seguridad_social", "=", "social_main.id")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->where([
        "docs.status_documento" => TRUE,
        "docs.token_documento" => $token_documento
      ])
      ->get();

      foreach ($queryDocsAportSSocial as $vDoc) {
        $name_doc = $JwtAuth->desencriptar($vDoc->nombre_documento);
        $filepath = $vDoc->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$vDoc->aport_ssocial_fecha_registro-$folio_aport/anexos/$name_doc";

        $archivo = Storage::path('public/root/' . $filepath);
        $extension = pathinfo($archivo, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        if (file_exists($archivo)) {
          $ruta = Storage::disk('root')->get($filepath);
          if ($extension == 'pdf') {
            $content_type = "application/pdf";
          } else if ($extension == 'xml') {
            $content_type = "text/xml";
          } else if ($extension == 'jpg') {
            $content_type = "image/jpg";
          } else if ($extension == 'jpeg') {
            $content_type = "image/jpeg";
          } else if ($extension == 'png') {
            $content_type = "image/png";
          }
          return Response($ruta, 200, ['Content-Type' => $content_type, 'Content-Disposition' => 'inline; filename="' . $name_doc . '"']);
        } else {
          $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
          return Response($ruta, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }

  //declaraciones_de_impuestos_federales
  public function verDeclaracionesDeImpuestosFederalesPdfHtml($declaracion_token){
    $JwtAuth = new \JwtAuth();
    if (isset($declaracion_token) && !empty($declaracion_token)) {
      $queryDeclaraciones = DB::table("cont_reg_fisc_declaraciones_imp_federales AS fedMain")
      ->join("main_empresas AS emp", "fedMain.declaracion_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->where("fedMain.declaracion_token",$declaracion_token)
      ->select(
        "fedMain.id AS idDec",
        "fedMain.declaracion_token",
        "fedMain.declaracion_fecha_registro",
        "fedMain.declaracion_folio_interior",
        "fedMain.declaracion_subfolio",
        "fedMain.declaracion_fecha_contabilizacion",
        "fedMain.declaracion_tipo",
        "fedMain.declaracion_periodicidad",
        "fedMain.declaracion_ejercicio",
        "fedMain.declaracion_periodo_inicio",
        "fedMain.declaracion_periodo_fin",
        "fedMain.declaracion_fecha_presentacion",
        "fedMain.declaracion_medio_presentacion",
        "fedMain.declaracion_fecha_vencimiento",
        "fedMain.declaracion_version",
        "fedMain.declaracion_numero_operacion",
        "fedMain.declaracion_linea_de_captura",
        "fedMain.declaracion_moneda",
        "fedMain.declaracion_observaciones",
        "emp.root_tkn",
        "people.img_perfil",
        "people.denominacion_rs",
        "people.paterno",
        "people.materno",
        "people.nombre",
        "people.abrev_nombre"
      )
      ->get();
      
      $idDeclaracion = $queryDeclaraciones->pluck('idDec')->filter()->unique()->toArray();
      $desgloseColeccion = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose")
      ->whereIn('declaracion',$idDeclaracion)
      ->select(
        'id',
	      'declaracion AS idDec',
	      'dec_desglose_token',
	      'dec_desglose_impuesto',
	      'dec_desglose_impuesto_importe_a_favor',
	      'dec_desglose_impuesto_a_cargo',
	      'dec_desglose_impuesto_actualizaciones',
	      'dec_desglose_impuesto_recargos',
	      'dec_desglose_impuesto_otros_cargos',
	      'dec_desglose_impuesto_otros_abonos',
	      'dec_desglose_impuesto_cantidad_a_pagar'
      )
      ->get();

      $decImpFedDesgloseMap = $desgloseColeccion->groupBy('idDec');

      $impuestosIds = $desgloseColeccion->pluck('dec_desglose_impuesto')->unique();
      $impuestosMap = DB::table('cont_impuestos_catalogo')
      ->whereIn('id',$impuestosIds)
      ->select('id','token_catalogo_impuesto','folio_impuesto','post_folio','concepto_impuesto','abreviacion_impuesto')
      ->get()->keyBy('id');

      foreach ($queryDeclaraciones as $vDec) {
        if ($JwtAuth->desencriptar($vDec->img_perfil) == "empresa_desconocida.png") {
          $ruta_logo = 'public/settings/empresa_desconocida.png';
        } else {
          $ruta_logo = "public/root/$vDec->root_tkn/0007-core/".$JwtAuth->desencriptar($vDec->img_perfil);
        }
        $logoEmp = $JwtAuth->encriptaBase64(Storage::path($ruta_logo));
        
        $nombreEmpresa = $vDec->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vDec->paterno, $vDec->materno, $vDec->nombre) : $JwtAuth->desencriptar($vDec->denominacion_rs);
        $name_abrev = $vDec->abrev_nombre;

        $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
        $declaracion_moneda = $vDec->declaracion_moneda;
        $declaracion_moneda_decimales = $JwtAuth->getMonedaAPI($vDec->declaracion_moneda);

        $periodoCarbonI = ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_inicio)->locale('es')->translatedFormat('F'));
        $periodoCarbonF = ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_fin)->locale('es')->translatedFormat('F'));
        
        $calculo_importe_a_favor = 0;
        $calculo_total_a_cargo = 0;
        $calculo_total_actualizaciones = 0;
        $calculo_total_recargos = 0;
        $calculo_total_otros_cargos = 0;
        $calculo_total_otros_abonos = 0;
        $calculo_total_cantidad_a_pagar = 0;
        $desglose_dec = array();
        $queryDecImpFedDesglose = $decImpFedDesgloseMap->get($vDec->idDec);
        foreach ($queryDecImpFedDesglose as $dVec) {
          $catImp = $impuestosMap->get($dVec->dec_desglose_impuesto);
          $folio_impuesto = $catImp ?'IMP-'.$JwtAuth->generarFolio($catImp->folio_impuesto).(!is_null($catImp->post_folio) ? '-'.$catImp->post_folio : '') : '';
          
          $calculo_importe_a_favor += $dVec->dec_desglose_impuesto_importe_a_favor;
          $calculo_total_a_cargo += $dVec->dec_desglose_impuesto_a_cargo;
          $calculo_total_actualizaciones += $dVec->dec_desglose_impuesto_actualizaciones;
          $calculo_total_recargos += $dVec->dec_desglose_impuesto_recargos;
          $calculo_total_otros_cargos += $dVec->dec_desglose_impuesto_otros_cargos;
          $calculo_total_otros_abonos += $dVec->dec_desglose_impuesto_otros_abonos;
          $calculo_total_cantidad_a_pagar += $dVec->dec_desglose_impuesto_cantidad_a_pagar;

          $rddeg = array(
            "concepto_pago_name" => $catImp ? $folio_impuesto." ".$JwtAuth->desencriptar($catImp->concepto_impuesto)." (". $JwtAuth->desencriptar($catImp->abreviacion_impuesto).")" : '',
            "importe_a_favor" => "$".number_format($dVec->dec_desglose_impuesto_importe_a_favor,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            "a_cargo" => "$".number_format($dVec->dec_desglose_impuesto_a_cargo,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            "actualizaciones" => "$".number_format($dVec->dec_desglose_impuesto_actualizaciones,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            "recargos" => "$".number_format($dVec->dec_desglose_impuesto_recargos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            "otros_cargos" => "$".number_format($dVec->dec_desglose_impuesto_otros_cargos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            "otros_abonos" => "$".number_format($dVec->dec_desglose_impuesto_otros_abonos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            "cantidad_a_pagar" => "$".number_format($dVec->dec_desglose_impuesto_cantidad_a_pagar,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          );
          $desglose_dec[] = $rddeg;
        }

        $declaracion_data = [
          "estilos_css" => $JwtAuth->css_pdf(),
          "logo_emp" => $logoEmp,
          "company_name_large" => "$name_abrev - $nombreEmpresa",
          "declaracion_token" => $vDec->declaracion_token,
          "folio_imp_fed" => $folio_imp_fed,
          "declaracion_fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vDec->declaracion_fecha_contabilizacion),
          "declaracion_tipo" => $vDec->declaracion_tipo == 'comple' ? 'complementaria' : 'normal',
          "declaracion_periodicidad" => $vDec->declaracion_periodicidad,
          "declaracion_ejercicio" => $vDec->declaracion_ejercicio,
          "declaracion_periodo" => $periodoCarbonI == $periodoCarbonF ? $periodoCarbonF : $periodoCarbonI." - ".$periodoCarbonF,
          "declaracion_fecha_presentacion" => date('Y-m-d', $vDec->declaracion_fecha_presentacion),
          "declaracion_medio_presentacion" => $vDec->declaracion_medio_presentacion,
          "declaracion_fecha_vencimiento" => date('Y-m-d', $vDec->declaracion_fecha_vencimiento),
          "declaracion_version" => $vDec->declaracion_version,
          "declaracion_numero_operacion" => $vDec->declaracion_numero_operacion,
          "declaracion_linea_de_captura" => $vDec->declaracion_linea_de_captura,
          "declaracion_moneda" => $declaracion_moneda,
          "desglose_dec" => $desglose_dec,
          "calculo_importe_a_favor" => "$".number_format($calculo_importe_a_favor,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "calculo_total_a_cargo" => "$".number_format($calculo_total_a_cargo,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "calculo_total_actualizaciones" => "$".number_format($calculo_total_actualizaciones,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "calculo_total_recargos" => "$".number_format($calculo_total_recargos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "calculo_total_otros_cargos" => "$".number_format($calculo_total_otros_cargos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "calculo_total_otros_abonos" => "$".number_format($calculo_total_otros_abonos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "calculo_total_cantidad_a_pagar" => "$".number_format($calculo_total_cantidad_a_pagar,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "declaracion_observaciones" => $JwtAuth->desencriptar($vDec->declaracion_observaciones)
        ];
      }
      $pdf = \PDF::loadView('pdf.declaraciones_de_impuestos_federales',$declaracion_data);
      $pdf->setPaper('a2', 'portrait')->stream();
      return $pdf->stream($folio_imp_fed . ".pdf");
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
      return response()->json($dataMensaje, $dataMensaje['code']);
    }
  }
}