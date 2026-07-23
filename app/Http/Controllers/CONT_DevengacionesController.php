<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\ComprasModelo;
use App\Models\OrdenDevengacionModelo;
use PDF;
use QRCode;

class CONT_DevengacionesController extends Controller{
  private function eachOrdenDevengacion($listaCompras,$JwtAuth){
    $ordenesRecept = array();
    foreach ($listaCompras as $vBuy) {
      $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

      $proveedor_token = "";
      $proveedor_folio = "";
      $proveedor_nombre = "";

      $queryBuyProv = DB::table("eegr_compras AS buy")
      ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "=", "catprov.id")
      ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
      ->where('buy.token_compras',$vBuy->token_compras)->get();

      foreach ($queryBuyProv as $vProv) {
        $proveedor_token = $vProv->token_cat_proveedores;
        $proveedor_folio = 'PRV-'.$JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : '');
        $proveedor_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
      }

      $user_compra = "";
      $queryUserCompra = DB::table("eegr_compras AS buy")
      ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
      ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
      ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
      ->where('buy.token_compras',$vBuy->token_compras)->get();
      foreach ($queryUserCompra as $vUser) {
        $user_compra = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
      }

      $user_autoriza = "";
      $queryUserAuth = DB::table("eegr_compras AS buy")
      ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
      ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
      ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
      ->where([
        "buy.status_autorizacion" => TRUE, 
        "buy.token_compras" => $vBuy->token_compras
      ])
      ->get();
      foreach ($queryUserAuth as $vUser) {
        $user_autoriza = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
      }

      $devengacion_token = "";
      $devengacion_prov = "";
      $devengacion_estab = "";

      $importe_total_compra = 0;
      $queryDEtailsTotal = DB::table("eegr_compras AS buy")
      ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.id")
      ->where("buy.token_compras",$vBuy->token_compras)
      ->get();
      foreach ($queryDEtailsTotal as $vDet) {
        $resultante = 0;
        $det_precio_unitario = $vDet->precio_unitario;
        $det_descuento = $vDet->descuento;
        $det_total_traslados = $vDet->traslados_total;
        $det_total_retenciones = $vDet->retenciones_total;
        $resultante = $det_precio_unitario - $det_descuento + $det_total_traslados - $det_total_retenciones;
        $importe_total_compra = $importe_total_compra + $resultante;
      }

      $rowOrdMain = array(
        "token_compras" => $vBuy->token_compras,
        "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "uuid_orden_devengacion" => $vBuy->uuid_orden_devengacion,
        "folio_devengacion" => "ORDDEP-".$JwtAuth->generarFolio($vBuy->folio_devengacion),
        "estado_devengacion" => $vBuy->estado,
        "fecha_registro" => gmdate('Y-m-d H:i:s', $vBuy->fecha_sistemaCompras),
        "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
        "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
        "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
        //"facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
        "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
        "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
        "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
        "documentos" => $vBuy->documentos,
        "reporte" => $vBuy->reporte,
        "forma_pago" => $vBuy->forma_pago,
        "metodo_pago" => $vBuy->metodo_pago,
        "devengacionPago" => $vBuy->devengacionPago,
        "recibeProducto" => $vBuy->recibeProducto,
        "periodicidadCompra" => $vBuy->periodicidadCompra,
        "repeticionPeriodo" => $vBuy->repeticionPeriodo,
        "tipoPeriodo" => $vBuy->tipoPeriodo,
        "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
        "varImporte" => $vBuy->varImporte,
        //proveedor
        "proveedor_token" => $proveedor_token,
        "proveedor_folio" => $proveedor_folio,
        "proveedor_nombre" => $proveedor_nombre,
        //credito
        "compra_a_credito" => $vBuy->compra_a_credito ? true : false,
        //moneda
        "compra_moneda" => $vBuy->moneda,
        "compra_moneda_decimales" => $moneda_decimales,
        //recepcion
        "seleccion_devengacion" => !empty($vBuy->devengacion_prov) ? 'proveedor' : 'establecimiento',
        "lugar_devengacion_token" => $devengacion_token,
        "lugar_devengacion_data" => !empty($devengacion_prov) ? $devengacion_prov : $devengacion_estab,
        //autorizacion 
        "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
        "user_autoriza" => $user_autoriza,
        //"comprador" => $vBuy->comprador,
        "usuario_comprador" => $user_compra,
        //productos y servicios
        "articulos_recibidos" => count($queryDEtailsTotal),
        "devengacionCollapsed" => true,
        "total_articulos" => count($queryDEtailsTotal),
        //importes
        "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
        //"lugar_devengacion_data" => !empty($devengacion_prov) ? $devengacion_prov : $devengacion_estab, 
      );
      $ordenesRecept[] = $rowOrdMain;
    }
    return $ordenesRecept;
  }

  public function listaOrdenesDevengacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    //da_te_default_timezone_set('America/Mexico_City');

    $listaCompras = ComprasModelo::join("eegr_compras_orden_devengacion AS ordDeveng", "eegr_compras.id", "=", "ordDeveng.orden_compra")
    ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereIn('eegr_compras.id', function ($query) {
      $query->select('numero_compra')->from('eegr_compras_detalle');
    })
    ->where([
      'eegr_compras.status_compra' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->get();

    if ($listaCompras->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'ordenes' => $this->eachOrdenDevengacion($listaCompras,$JwtAuth)
      );
    }
    
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  private function detalleDevengacionProd($JwtAuth,$vBuy,$empresa,$usuario){
    $registros = array();
    $fecha_deveng = null;
    $folio_deveng = null;
    $bool_recept_observaciones = null;
    $bool_recept_validado_por = null;
    $recept_establecimiento = "";

    $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
    ->join("in_egr_catalogo_productos AS catprod","detcomp.producto","=","catprod.id")
    ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
    ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereNotNull("detcomp.producto")
    ->where([
      'comp.token_compras' => $vBuy->token_compras,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])->get();

    foreach ($detalleCompraLista as $vDetBuy) {
      $selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.recibe_factura,recept.observaciones,recept.recept_status,
        people.paterno,people.materno,people.nombre FROM eegr_compras_devengacion AS recept JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people 
        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? 
        AND recept.valida_recept = peo_buy.id AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
        [$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);
  
      if (count($selectRecibido) != 0) {
        $fecha_deveng = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
        $folio_deveng = $JwtAuth->generar($selectRecibido[0]->folio_recep);
        $bool_recept_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
        $bool_recept_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);
      }

      $tipo_articulo = $vDetBuy->activo_fijo == NULL && $vDetBuy->activo_intangible == NULL ? 'producto' : ($vDetBuy->activo_fijo != NULL ? 'activo fijo' : 'activo intangible');

      $folio_prod = $vDetBuy->folio_sistema != NULL && $vDetBuy->folio_sistema != "" ? ('PROD-' . ($vDetBuy->post_folio == NULL ? $JwtAuth->generarFolio($vDetBuy->folio_sistema) : $JwtAuth->generarFolio($vDetBuy->folio_sistema) . '-' . $vDetBuy->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vDetBuy->temps_folio);

      $row = array(
        "token_articulo" => $vDetBuy->token_cat_productos,
        "prod_recep_fecha_contabilizacion" => "",
        "recibido" => count($selectRecibido) > 0 ? true : false,
        "tipo_articulo" => $tipo_articulo,
        "folio_articulo" => $folio_prod,
        "token_detcompra" => $vDetBuy->token_detcompra,
        "unidad_medida" => $vDetBuy->unidad_medida,
        "clasificacion" => $JwtAuth->generar($vBuy->clasificacion) . '-' . $JwtAuth->generar($vBuy->folio_genero).'-'.$JwtAuth->generar($vDetBuy->folio_sistema),
        "articulo" => $JwtAuth->desencriptar($vDetBuy->producto),
        //"clave" => $servicioList[0]->clave,
        "cantidad" => $vDetBuy->cantidad,
        "cantidad_background" => $vDetBuy->cantidad,
        "boolRecibido" => true,
        "fecha_deveng" => $fecha_deveng,
        "folio_deveng" => $folio_deveng,
        "lo_pedido" => count($selectRecibido) > 0 && $selectRecibido[0]->lo_pedido ? true : false,
        "llegoTiempo" => count($selectRecibido) > 0 && $selectRecibido[0]->llego_tiempo ? true : false,
        "buenEstado" => count($selectRecibido) > 0 && $selectRecibido[0]->buen_estado ? true : false,
        "calidadRecepcion" => count($selectRecibido) > 0 && $selectRecibido[0]->calidad_devengacion ? true : false,
        "recibe_factura" => count($selectRecibido) > 0 && $selectRecibido[0]->recibe_factura ? true : false,
        "observaciones" => $bool_recept_observaciones,
        "checked_recept" => count($selectRecibido) > 0 && $selectRecibido[0]->recept_status ? true : false,
        "establecimiento" => $recept_establecimiento,
        "archivos_cargados_names" => [],
        "archivos_cargados_files" => [],
        "validado_por" => $bool_recept_validado_por,
        "seleccionado" => false,
      );
      $registros[] = $row;
    }
    return $registros;
  }

  private function detalleRecepcionActivoFijo($JwtAuth,$vBuy,$empresa,$usuario){
    $list_unidades_devengacion = array();
    $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
    ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
    ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereNotNull("detcomp.activo_fijo")
    ->where([
      'comp.token_compras' => $vBuy->token_compras,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])->get();
  
    foreach ($detalleCompraLista as $vDetBuy) {
      $activoList = DB::table("eegr_activos_fijos_unidades AS actfUnid")
      ->join("eegr_activos_fijos_detalle AS actfDet","actfUnid.activof_detalle","=","actfDet.id")
      ->join("eegr_activos_fijos_catalogo AS actfCat","actfDet.activo_fijo","=","actfCat.id")
      ->join("eegr_compras_detalle AS detBuy","actfDet.compra_detalle","=","detBuy.id")
      ->whereNotNull('detBuy.activo_fijo')
      ->where('detBuy.token_detcompra',$vDetBuy->token_detcompra)
      ->get();
  
      foreach ($activoList as $vDetActF) {
        $tipo_articulo = 'Activo fijo';
        $folio_activo = "ACTF-".$JwtAuth->generarFolio($vDetActF->folio_activo).(!is_null($vDetActF->subfolio_activo) ? '-'.$vDetActF->subfolio_activo : '');
        
        $fecha_deveng = null;
        $folio_deveng = null;
        $bool_recept_observaciones = null;
        $bool_recept_validado_por = null;
        $recept_establecimiento = "";

        $selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.calidad_recepcion,recept.recibe_factura,recept.establecimiento AS estab_recept,recept.observaciones,
          recept.recept_status,people.paterno,people.materno,people.nombre FROM eegr_compras_devengacion AS recept JOIN eegr_activos_fijos_unidades AS actfUnid JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy 
          JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.unidad_activo_fijo = actfUnid.id 
          AND actfUnid.token_activof_unidad = ? AND recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND recept.valida_recept = peo_buy.id 
          AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$vDetActF->token_activof_unidad,$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);
    
        if (count($selectRecibido) != 0) {
          $fecha_deveng = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
          $folio_deveng = $JwtAuth->generarFolio($selectRecibido[0]->folio_recep);
          $bool_recept_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
          $bool_recept_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);

          $queryEstabs = DB::table("in_egr_establecimientos_catalogo AS estabCat")
          ->where('estabCat.id',$selectRecibido[0]->estab_recept)
          ->select('folio_establecimiento','post_folio','alias_establecimiento')
          ->first();
          $estab_folio = 'ESTAB-'.$JwtAuth->generarFolio($queryEstabs->folio_establecimiento).($queryEstabs->post_folio != NULL ? '-'.$queryEstabs->post_folio : '');
          $estab_alias = $JwtAuth->desencriptar($queryEstabs->alias_establecimiento);

          $recept_establecimiento = "$estab_folio $estab_alias";
        }

        $row_act_de_buy = array(
          "token_activof_unidad" => $vDetActF->token_activof_unidad,
          "act_ivo_recep_fecha_contabilizacion" => "",
          "token_articulo" => $vDetActF->token_det_activo_fijo,
          "recibido" => count($selectRecibido) > 0 ? true : false,
          "tipo_articulo" => $tipo_articulo,
          "folio_articulo" => $folio_activo,
          "folio_activof_unidad" => $vDetActF->folio_activof_unidad,

          "unidad_serie" => !is_null($vDetActF->unidad_serie) ? $vDetActF->unidad_serie : '',
          "unidad_otros" => !is_null($vDetActF->unidad_otros) ? $JwtAuth->desencriptar($vDetActF->unidad_otros) : '',
          "unidad_observaciones" => !is_null($vDetActF->unidad_observaciones) ? $JwtAuth->desencriptar($vDetActF->unidad_observaciones) : '',

          "token_detcompra" => $vDetBuy->token_detcompra,
          "clasificacion" => '---',
          "articulo" => $JwtAuth->desencriptar($vDetActF->concepto),
          //"clave" => $servicioList[0]->clave,
          "cantidad" => 1,//$vDetBuy->cantidad,
          "cantidad_background" => $vDetBuy->cantidad,
          "unidad_medida" => $vDetBuy->unidad_medida,
          "fecha_deveng" => $fecha_deveng,
          "folio_deveng" => $folio_deveng,
          "lo_pedido" => count($selectRecibido) > 0 && $selectRecibido[0]->lo_pedido ? true : false,
          "llegoTiempo" => count($selectRecibido) > 0 && $selectRecibido[0]->llego_tiempo ? true : false,
          "buenEstado" => count($selectRecibido) > 0 && $selectRecibido[0]->buen_estado ? true : false,
          "calidadRecepcion" => count($selectRecibido) > 0 && $selectRecibido[0]->calidad_devengacion ? true : false,
          "recibe_factura" => count($selectRecibido) > 0 && $selectRecibido[0]->recibe_factura ? true : false,
          "observaciones" => $bool_recept_observaciones,
          "checked_recept" => count($selectRecibido) > 0 && $selectRecibido[0]->recept_status ? true : false,
          "establecimiento" => $recept_establecimiento,
          "archivos_cargados_names" => [],
          "archivos_cargados_files" => [],
          "validado_por" => $bool_recept_validado_por,
          "seleccionado" => false,
        );
        $list_unidades_devengacion[] = $row_act_de_buy;
      }
    }
    return $list_unidades_devengacion;
  }

  private function detalleRecepcionServ($JwtAuth,$vBuy,$empresa,$usuario){
    $registros = array();
    $fecha_devengado = "";
    $folio_devengado = "";
    $bool_devengado_observaciones = "";
    $bool_devengado_validado_por = "";
    
    $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
    ->join("in_egr_catalogo_servicios AS catserv", "detcomp.servicio", "=", "catserv.id")
    ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
    ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereNotNull("detcomp.servicio")
    ->where([
      'comp.token_compras' => $vBuy->token_compras,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->get();

    foreach ($detalleCompraLista as $vDetBuy) {
      $selectRecibido = DB::select("SELECT deveng.fecha_devengacion,deveng.folio_devengacion,deveng.observaciones,deveng.devengacion_status,people.paterno,people.materno,people.nombre FROM eegr_compras_devengacion AS deveng 
        JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE deveng.compra = buy.id AND buy.token_compras = ? AND deveng.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND deveng.valida_devengacion = peo_buy.id 
        AND peo_buy.empleado_name = people.id AND deveng.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
        [$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);
      
      if (count($selectRecibido) != 0) {
        $fecha_devengado = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_devengacion);
        $folio_devengado = $JwtAuth->generar($selectRecibido[0]->folio_devengacion);
        $bool_devengado_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
        $bool_devengado_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);
      }
  
      //echo $vDetBuy->numero_compra;
      $folio_serv = $vDetBuy->folio_sistema != NULL && $vDetBuy->folio_sistema != "" ? ('SERV-'.($vDetBuy->post_folio == NULL ? $JwtAuth->generarFolio($vDetBuy->folio_sistema) : $JwtAuth->generarFolio($vDetBuy->folio_sistema).'-'.$vDetBuy->post_folio)):
      'SERV-TEMP-'.$JwtAuth->generarFolio($vDetBuy->temps_folio);

      $row = array(
        "token_articulo" => $vDetBuy->token_cat_servicios,
        "serv_recep_fecha_contabilizacion" => "",
        "recibido" => count($selectRecibido) > 0 ? true : false,
        "tipo_articulo" => 'servicio',
        "folio_articulo" => $folio_serv,
        "token_detcompra" => $vDetBuy->token_detcompra,
        "articulo" => $JwtAuth->desencriptar($vDetBuy->servicio),
        //"clave" => $servicioList[0]->clave,
        "cantidad" => $vDetBuy->cantidad,
        "boolRecibido" => false,
        "fecha_devengado" => $fecha_devengado,
        "folio_devengado" => $folio_devengado,
        "observaciones" => $bool_devengado_observaciones,
        "checked_recept" => false,
        "validado_por" => $bool_devengado_validado_por,
        "archivos_cargados_names" => [],
        "archivos_cargados_files" => [],
        "seleccionado" => false,
      );
      $registros[] = $row;
    }
    return $registros;
  }

  private function detalleRecepcionActivoDiferido($JwtAuth,$vBuy,$empresa,$usuario){
    $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];
    $list_unidades_devengacion = array();
    $detalleCompraLista = DB::table("eegr_compras_detalle AS detcomp")
    ->join("eegr_compras AS comp", "detcomp.numero_compra", "=", "comp.id")
    ->join("main_empresas AS emp", "comp.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereNotNull("detcomp.activo_intangible")
    ->where([
      'comp.token_compras' => $vBuy->token_compras,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->get();
  
    foreach ($detalleCompraLista as $vDetBuy) {
      $activoList = DB::table("eegr_activos_intangibles_unidades AS actDifUnid")
      ->join("eegr_activos_intangibles_detalle AS actDifDet","actDifUnid.activod_detalle","=","actDifDet.id")
      ->join("eegr_activos_intangibles_catalogo AS actfCat","actDifDet.activo_intang","=","actfCat.id")
      ->join("eegr_compras_detalle AS detBuy","actDifDet.compra_detalle","=","detBuy.id")
      ->whereNotNull('detBuy.activo_intangible')
      ->where('detBuy.token_detcompra',$vDetBuy->token_detcompra)
      ->select(
        'detBuy.cantidad AS cant_for_recibir',
        'actfCat.folio_activo',
        'actfCat.subfolio_activo',
        'actDifDet.token_det_act_intang',
        'actDifDet.concepto',
        'actDifUnid.token_activod_unidad',
        'actDifUnid.folio_activod_unidad',
        'actDifUnid.amort_contable_periodo',
        'actDifUnid.amort_contable_tiempo',
        'actDifUnid.amort_contable_fecha_apartir',
        'actDifUnid.amort_contable_observaciones',
        'actDifUnid.amort_fiscal_periodo',
        'actDifUnid.amort_fiscal_tiempo',
        'actDifUnid.amort_fiscal_fecha_apartir',
        'actDifUnid.amort_fiscal_observaciones',
      )
      ->get();
  
      foreach ($activoList as $vDetActDif) {
        $tipo_articulo = 'Activo diferido';
        $folio_activo = "ACTD-".$JwtAuth->generarFolio($vDetActDif->folio_activo).(!is_null($vDetActDif->subfolio_activo) ? '-'.$vDetActDif->subfolio_activo : '');
        
        $fecha_deveng = null;
        $folio_deveng = null;
        $bool_recept_observaciones = null;
        $bool_recept_validado_por = null;
        $recept_establecimiento = "";

        $selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.calidad_recepcion,recept.recibe_factura,recept.establecimiento AS estab_recept,recept.observaciones,
          recept.recept_status,people.paterno,people.materno,people.nombre FROM eegr_compras_devengacion AS recept JOIN eegr_activos_fijos_unidades AS actfUnid JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy 
          JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.unidad_activo_fijo = actfUnid.id 
          AND actfUnid.token_activof_unidad = ? AND recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND recept.valida_recept = peo_buy.id 
          AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$vDetActDif->token_activod_unidad,$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);
    
        if (count($selectRecibido) != 0) {
          $fecha_deveng = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
          $folio_deveng = $JwtAuth->generarFolio($selectRecibido[0]->folio_recep);
          $bool_recept_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
          $bool_recept_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);

          $queryEstabs = DB::table("in_egr_establecimientos_catalogo AS estabCat")
          ->where('estabCat.id',$selectRecibido[0]->estab_recept)
          ->select('folio_establecimiento','post_folio','alias_establecimiento')
          ->first();
          $estab_folio = 'ESTAB-'.$JwtAuth->generarFolio($queryEstabs->folio_establecimiento).($queryEstabs->post_folio != NULL ? '-'.$queryEstabs->post_folio : '');
          $estab_alias = $JwtAuth->desencriptar($queryEstabs->alias_establecimiento);

          $recept_establecimiento = "$estab_folio $estab_alias";
        }

        $row_act_de_buy = array(
          "token_activod_unidad" => $vDetActDif->token_activod_unidad,
          "act_ivo_recep_fecha_contabilizacion" => "",
          "token_articulo" => $vDetActDif->token_det_act_intang,
          "recibido" => count($selectRecibido) > 0 ? true : false,
          "tipo_articulo" => $tipo_articulo,
          "folio_articulo" => $folio_activo,
          "folio_activod_unidad" => $vDetActDif->folio_activod_unidad,

          //"unidad_serie" => !is_null($vDetActDif->unidad_serie) ? $vDetActDif->unidad_serie : '',
          //"unidad_otros" => !is_null($vDetActDif->unidad_otros) ? $JwtAuth->desencriptar($vDetActDif->unidad_otros) : '',
          //"unidad_observaciones" => !is_null($vDetActDif->unidad_observaciones) ? $JwtAuth->desencriptar($vDetActDif->unidad_observaciones) : '',

          "amort_contable_periodo" => $periodos[$vDetActDif->amort_contable_periodo] ?? '',
          "amort_contable_tiempo" => !is_null($vDetActDif->amort_contable_tiempo) ? $vDetActDif->amort_contable_tiempo : '',
          "amort_contable_fecha_apartir" => !is_null($vDetActDif->amort_contable_fecha_apartir) ? gmdate('Y-m-d H:i:s', $vDetActDif->amort_contable_fecha_apartir) : '',
          "amort_contable_observaciones" => !is_null($vDetActDif->amort_contable_observaciones) ? $JwtAuth->desencriptar($vDetActDif->amort_contable_observaciones) : '',

          "amort_fiscal_periodo" => $periodos[$vDetActDif->amort_fiscal_periodo] ?? '',
          "amort_fiscal_tiempo" => !is_null($vDetActDif->amort_fiscal_tiempo) ? $vDetActDif->amort_fiscal_tiempo : '',
          "amort_fiscal_fecha_apartir" => !is_null($vDetActDif->amort_fiscal_fecha_apartir) ? gmdate('Y-m-d H:i:s', $vDetActDif->amort_fiscal_fecha_apartir) : '',
          "amort_fiscal_observaciones" => !is_null($vDetActDif->amort_fiscal_observaciones) ? $JwtAuth->desencriptar($vDetActDif->amort_fiscal_observaciones) : '',

          "token_detcompra" => $vDetBuy->token_detcompra,
          "clasificacion" => '---',
          "articulo" => $JwtAuth->desencriptar($vDetActDif->concepto),
          //"clave" => $servicioList[0]->clave,
          "cantidad" => 1,
          "cantidad_background" => $vDetBuy->cantidad,
          "unidad_medida" => $vDetBuy->unidad_medida,
          "fecha_deveng" => $fecha_deveng,
          "folio_deveng" => $folio_deveng,
          "observaciones" => $bool_recept_observaciones,
          "checked_recept" => count($selectRecibido) > 0 && $selectRecibido[0]->recept_status ? true : false,
          "establecimiento" => $recept_establecimiento,
          "archivos_cargados_names" => [],
          "archivos_cargados_files" => [],
          "validado_por" => $bool_recept_validado_por,
          "seleccionado" => false,
        );
        $list_unidades_devengacion[] = $row_act_de_buy;
      }
    }
    return $list_unidades_devengacion;
  }

  public function detalleOrdenDevengacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_devengacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $orden_devengacion = $request->input('orden_devengacion');
      
      $listaReceptCompras = ComprasModelo::join("eegr_compras_orden_devengacion AS ordDeveng", "eegr_compras.id", "=", "ordDeveng.orden_compra")
      ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->whereIn('eegr_compras.id', function ($query) {
        $query->select('numero_compra')->from('eegr_compras_detalle');
      })
      ->where('eegr_compras.status_autorizacion',TRUE)
      ->where('ordDeveng.uuid_orden_devengacion',$orden_devengacion)
      ->where('emp.empresa_token',$empresa)
      ->where('users.usuario_token',$usuario)
      ->get();

      if ($listaReceptCompras->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de recepción o devengación registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayCompras = array();

        foreach ($listaReceptCompras as $vBuy) {
          //da_te_default_timezone_set($vBuy->zona_horaria);
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

          $validaTimerFact = $vBuy->validaTimerFact ? true : false;
          $fechaTimerFact = $vBuy->validaTimerFact ? $vBuy->fechaTimerFact + 86400 : NULL;

          $arrayServicios = array();

          $user_compra = "";
          $queryUserCompra = DB::table("eegr_compras AS buy")
          ->join("teci_usuarios_catalogo AS users", "buy.usuario_comprador", "=", "users.id")
          ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryUserCompra as $vUser) {
            $user_compra = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }

          $productos = $this->detalleDevengacionProd($JwtAuth,$vBuy,$empresa,$usuario);
          $activos_fijos = $this->detalleRecepcionActivoFijo($JwtAuth,$vBuy,$empresa,$usuario);
          $servicios = $this->detalleRecepcionServ($JwtAuth,$vBuy,$empresa,$usuario);
          $activos_diferidos = $this->detalleRecepcionActivoDiferido($JwtAuth,$vBuy,$empresa,$usuario);
          $arrayForeach = array(
            "uuid_orden_devengacion" => $vBuy->uuid_orden_devengacion,
            "token_compras" => $vBuy->token_compras,
            "persCompra" => $user_compra,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            "folio" => $folio_buy,
            "fecha_sistemaCompras" => gmdate('Y-m-d H:i:s', $vBuy->fecha_sistemaCompras),
            "fecha_altaCompra" => gmdate('Y-m-d H:i:s', $vBuy->fecha_altaCompra),
            "forma_pago" => $vBuy->forma_pago,
            "metodo_pago" => $vBuy->metodo_pago,
            "productos" => $productos,
            "activos_fijos" => $activos_fijos,
            "servicios" => $servicios,
            "activos_diferidos" => $activos_diferidos,
            "validaTimerFact" => $validaTimerFact,
            "fechaTimerFact" => $fechaTimerFact,
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array(
          'total' => count($listaReceptCompras),
          'compras' => $arrayCompras,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaComprasProdSinRecibir(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "La infomación que ha intantado registrar es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $listaCompras = ComprasModelo::whereExists(function($queryMain){
          $queryMain->select(DB::raw(1))
          ->from('eegr_compras_detalle as detBuy')
          ->whereColumn('detBuy.numero_compra','eegr_compras.id')
          ->whereNull('detBuy.servicio')
          ->whereNotExists(function($queryNotExists){
            $queryNotExists->select(DB::raw(1))
            ->from('eegr_compras_devengacion as r')
            ->whereColumn('r.compra', 'detBuy.numero_compra')
            ->whereColumn('r.producto', 'detBuy.producto');
          });
        })
        ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereIn('eegr_compras.id', function ($query) {
          $query->select('numero_compra')->from('eegr_compras_detalle');
        })
        ->where("eegr_compras.status_compra",TRUE)
        ->where("emp.empresa_token",$empresa)
        ->where("users.usuario_token",$usuario)
        ->get();

        foreach ($listaCompras as $vBuy) {
          //da_te_default_timezone_set($vBuy->zona_horaria); 

            //$fecha = \Carbon\Carbon::createFromTimestamp($vBuy->fecha_sistemaCompras)
            //->setTimezone($vBuy->zona_horaria) // Solo afecta a esta variable
            //->format('d-m-Y H:i:s');
          
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
    
          $user_autoriza = "";
          $queryUserAuth = DB::table("eegr_compras AS buy")
            ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
            ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["buy.status_autorizacion" => TRUE, "buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryUserAuth as $vUser) {
            $user_autoriza = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }
    
          $devengacion_token = "";
    
          $importe_total_compra = 0;
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
            ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_precio_unitario = $vDet->precio_unitario;
            $det_descuento = $vDet->descuento;
            $det_total_traslados = $vDet->traslados_total;
            $det_total_retenciones = $vDet->retenciones_total;
            $resultante = $det_precio_unitario - $det_descuento + $det_total_traslados - $det_total_retenciones;
            $importe_total_compra = $importe_total_compra + $resultante;
          }
    
          /*$insertDetCompra = DB::table('eegr_compras_detalle') 
              ->insert(array(
                  "token_detcompra" => $tokenDetalleCompra, 
                  "numero_compra" => $obtenCompra, 
                  "producto" => $token_producto, 
                  "servicio" => $token_servicio, 
                  "precio_unitario" => $precioUnitario,
                  "cantidad" => $cantidad, 
                  "unidad_medida" => $token_unidad_medida,
                  "descuento" => $total_descuento, 
                  "total_retenciones" => $total_retenciones, 
                  "total_traslados" => $total_traslado, 
                  "destino" => $usoArticulo,  
                  "activo_fijo" => $activos_fijos, 
                  "activo_intangible" => $activos_intangibles, 
                  "serie" => $serie, 
                  "lote" => $lote, 
                  "pedimento_aduanal" => $pedimento_aduanal, 
                  "prorrateo" => $boolprorratea,
                  "empresa" => $selectEmp[0]->id,
              ));*/

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => gmdate('Y-m-d H:i:s', $vBuy->fecha_sistemaCompras),
            "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            //"facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "documentos" => $vBuy->documentos,
            "reporte" => $vBuy->reporte,
            "forma_pago" => $vBuy->forma_pago,
            "metodo_pago" => $vBuy->metodo_pago,
            "devengacionPago" => $vBuy->devengacionPago,
            "recibeProducto" => $vBuy->recibeProducto,
            "periodicidadCompra" => $vBuy->periodicidadCompra,
            "repeticionPeriodo" => $vBuy->repeticionPeriodo,
            "tipoPeriodo" => $vBuy->tipoPeriodo,
            "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
            "varImporte" => $vBuy->varImporte,
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            //credito
            "compra_a_credito" => $vBuy->compra_a_credito ? true : false,
            //moneda
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //recepcion
            "seleccion_devengacion" => !empty($vBuy->devengacion_prov) ? 'proveedor' : 'establecimiento',
            "lugar_devengacion_token" => $devengacion_token,
            "lugar_devengacion_data" => !empty($devengacion_prov) ? $devengacion_prov : $devengacion_estab,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //"comprador" => $vBuy->comprador,
            "usuario_comprador" => $user_compra,
            //productos y servicios
            "articulos_recibidos" => count($queryDEtailsTotal),
            "devengacionCollapsed" => true,
            "total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
            //"lugar_devengacion_data" => !empty($devengacion_prov) ? $devengacion_prov : $devengacion_estab, 
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array("status" => "success","code" => 200,"compras" => $arrayCompras);
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los informacion que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function listaComprasServSinDevengar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input("json");
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCompras = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "La infomación que ha intantado registrar es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $listaCompras = ComprasModelo::whereExists(function($queryMain){
          $queryMain->select(DB::raw(1))
          ->from('eegr_compras_detalle as detBuy')
          ->whereColumn('detBuy.numero_compra','eegr_compras.id')
          ->whereNull('detBuy.producto')
          ->whereNotExists(function($queryNotExists){
            $queryNotExists->select(DB::raw(1))
            ->from('eegr_compras_devengacion as dev')
            ->whereColumn('dev.compra', 'detBuy.numero_compra')
            ->whereColumn('dev.servicio', 'detBuy.servicio');
          });
        })
        ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereIn('eegr_compras.id', function ($query) {
          $query->select('numero_compra')->from('eegr_compras_detalle');
        })
        ->where("eegr_compras.status_compra",TRUE)
        ->where("emp.empresa_token",$empresa)
        ->where("users.usuario_token",$usuario)
        ->get();
        //echo "listaCompras ".count($listaCompras);
        foreach ($listaCompras as $vBuy) {
          //da_te_default_timezone_set($vBuy->zona_horaria);
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
        
          $user_autoriza = "";
          $queryUserAuth = DB::table("eegr_compras AS buy")
            ->join("teci_usuarios_catalogo AS users", "buy.autoriza", "=", "users.id")
            ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["buy.status_autorizacion" => TRUE, "buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryUserAuth as $vUser) {
            $user_autoriza = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
          }
        
          $devengacion_token = "";
          $devengacion_prov = "";
          $devengacion_estab = "";
          if (!empty($value->devengacion_prov)) { //$value->devengacion_estab 
            # code...
          } else {
            $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.devengacion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where([
                "buy.token_compras" => $vBuy->token_compras,
                "estab.status_establecimiento" => TRUE
              ])->get();
              
            foreach ($listaDirAlmacen as $vEstab) {
              $devengacion_token = $vEstab->token_establecimiento;
              $devengacion_estab = 'ESTAB-' . $JwtAuth->generarFolio($vEstab->folio_establecimiento) . ($vEstab->post_folio != NULL ? '-' . $vEstab->post_folio : '') . " " . $JwtAuth->desencriptar($vEstab->alias_establecimiento);
            }
          }
        
          $importe_total_compra = 0;
          $queryDEtailsTotal = DB::table("eegr_compras AS buy")
            ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.id")
            ->where(["buy.token_compras" => $vBuy->token_compras])->get();
          foreach ($queryDEtailsTotal as $vDet) {
            $resultante = 0;
            $det_precio_unitario = $vDet->precio_unitario;
            $det_descuento = $vDet->descuento;
            $det_total_traslados = $vDet->traslados_total;
            $det_total_retenciones = $vDet->retenciones_total;
            $resultante = $det_precio_unitario - $det_descuento + $det_total_traslados - $det_total_retenciones;
            $importe_total_compra = $importe_total_compra + $resultante;
          }
        
          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => gmdate('Y-m-d H:i:s', $vBuy->fecha_sistemaCompras),
            "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            //"facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
            "documentos" => $vBuy->documentos,
            "reporte" => $vBuy->reporte,
            "forma_pago" => $vBuy->forma_pago,
            "metodo_pago" => $vBuy->metodo_pago,
            "devengacionPago" => $vBuy->devengacionPago,
            "recibeProducto" => $vBuy->recibeProducto,
            "periodicidadCompra" => $vBuy->periodicidadCompra,
            "repeticionPeriodo" => $vBuy->repeticionPeriodo,
            "tipoPeriodo" => $vBuy->tipoPeriodo,
            "fechaFinPeriodo" => $vBuy->fechaFinPeriodo,
            "varImporte" => $vBuy->varImporte,
            //proveedor
            "proveedor_token" => $proveedor_token,
            "proveedor_folio" => $proveedor_folio,
            "proveedor_nombre" => $proveedor_nombre,
            //credito
            "compra_a_credito" => $vBuy->compra_a_credito ? true : false,
            //moneda
            "compra_moneda" => $vBuy->moneda,
            "compra_moneda_decimales" => $moneda_decimales,
            //recepcion
            "seleccion_devengacion" => !empty($vBuy->devengacion_prov) ? 'proveedor' : 'establecimiento',
            "lugar_devengacion_token" => $devengacion_token,
            "lugar_devengacion_data" => !empty($devengacion_prov) ? $devengacion_prov : $devengacion_estab,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //"comprador" => $vBuy->comprador,
            "usuario_comprador" => $user_compra,
            //productos y servicios
            "articulos_recibidos" => count($queryDEtailsTotal),
            "devengacionCollapsed" => true,
            "total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
            //"lugar_devengacion_data" => !empty($devengacion_prov) ? $devengacion_prov : $devengacion_estab, 
          );
          $arrayCompras[] = $arrayForeach;
        }

        $dataMensaje = array("status" => "success","code" => 200,"compras" => $arrayCompras);
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "Los informacion que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function recibeActivoFijoAlmacen(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $rules = [
      'token_compra' => 'required|string',
      'activos_fijos' => 'required|array|min:1',
      'activos_fijos.*.articulo' => 'required|string',
      'activos_fijos.*.token_activof_unidad' => 'required|string',
      'activos_fijos.*.token_detcompra' => 'required|string',
      'activos_fijos.*.token_articulo' => 'required|string',
      'activos_fijos.*.recibido' => 'required|boolean',
      'activos_fijos.*.tipo_articulo' => 'required|string',
      'activos_fijos.*.lo_pedido' => 'required|boolean',
      'activos_fijos.*.llegoTiempo' => 'required|boolean',
      'activos_fijos.*.buenEstado' => 'required|boolean',
      'activos_fijos.*.calidadRecepcion' => 'required|boolean',
      'activos_fijos.*.observaciones' => 'required|string',
      'activos_fijos.*.checked_recept' => 'required|boolean',
      'activos_fijos.*.establecimiento' => 'required_if:activos_fijos.*.checked_recept,true'
    ];

    $JwtAuth = new \App\Helpers\JwtAuth();
    $validate = \Validator::make($request->all(),$rules);

    if ($validate->fails()) {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
        'errors' => $validate->errors()
      );
    } else {
      $token_compra = $request->input('token_compra');
      $activos_fijos = $request->input('activos_fijos');
      $validateReceptInsert = false;

      $validaCompraTKN = isset($token_compra) && !empty($token_compra);
      $validaActivosFijos = isset($activos_fijos) && is_array($activos_fijos) && count($activos_fijos) > 0;

      if ($validaCompraTKN && $validaActivosFijos) {
        $activo_fijo = $request->input('activos_fijos')[0];
        DB::beginTransaction();

        try {
          $vEmp = DB::table('main_empresas as emp')
          ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
          ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
          ->where('emp.empresa_token', $empresa)
          ->where('users.usuario_token', $usuario)
          ->select('emp.id', 'users.id AS userr')//emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main
          ->first();
  
          $compraID = DB::table("eegr_compras")->where("token_compras",$token_compra)->value("id");
          $fecha_contabilizacion_registro = $activo_fijo['act_ivo_recep_fecha_contabilizacion'];
          $folio_activof_unidad = $activo_fijo['folio_activof_unidad'];
          $articulo = $activo_fijo['articulo'];
          $token_activof_unidad = $activo_fijo['token_activof_unidad'];
          $token_detcompra = $activo_fijo['token_detcompra'];
          $cantidad = $activo_fijo['cantidad'];
          $unidad_medida = $activo_fijo['unidad_medida'];
          $lo_pedido = $activo_fijo['lo_pedido'];
          $llegoTiempo = $activo_fijo['llegoTiempo'];
          $buenEstado = $activo_fijo['buenEstado'];
          $calidadRecepcion = $activo_fijo['calidadRecepcion'];
          $activo_serie = $activo_fijo['unidad_serie'];
          $activo_otros = $activo_fijo['unidad_otros'];
          $observaciones =  $activo_fijo['observaciones'];
          $checked_recept = $activo_fijo['checked_recept'];
          $establecimiento = $activo_fijo['establecimiento'];
  
          $maxFolio = DB::table('eegr_compras_devengacion')
          ->where('empresa', $vEmp->id)
          ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
          ->max('folio_recep');
            
          $folioRecepNuevo = $maxFolio ? $maxFolio + 1 : 1;

          $compra_detalle = DB::table("eegr_compras_detalle")
          ->where("token_detcompra",$token_detcompra)
          ->select('id','cantidad','unidad_medida','precio_unitario')
          ->first();
  
          $activo_fijo_data = DB::table("eegr_activos_fijos_catalogo AS actFCAT")
          ->join("eegr_activos_fijos_detalle AS actFDET", "actFCAT.id", "=", "actFDET.activo_fijo")
          ->join("eegr_activos_fijos_unidades AS actFUNI", "actFDET.id", "=", "actFUNI.activof_detalle")
          ->where("actFUNI.token_activof_unidad",$token_activof_unidad)
          ->select('actFCAT.id AS FCATID','actFDET.id AS FDETID','actFUNI.id AS FUNIID')
          ->first();
  
          $fecha_registro = $fecha_contabilizacion_registro ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion_registro) : time();
          $sql_establecimiento = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id");
          $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro.$activo_fijo_data->FCATID.$activo_fijo_data->FDETID.$activo_fijo_data->FUNIID,$compra_detalle->id);
  
          $newReceptBuy = new OrdenDevengacionModelo();
          $newReceptBuy->token_recept_compra = $tookenRecept_compra;
          $newReceptBuy->fecha_recep = $fecha_registro;
          $newReceptBuy->folio_recep = $folioRecepNuevo;
          $newReceptBuy->compra = $compraID;
          $newReceptBuy->detalle_compra = $compra_detalle->id;
          $newReceptBuy->activo_fijo = $activo_fijo_data->FCATID;
          $newReceptBuy->detalle_activo_fijo = $activo_fijo_data->FDETID;
          $newReceptBuy->unidad_activo_fijo = $activo_fijo_data->FUNIID;
          $newReceptBuy->cantidad_recibida = $cantidad;
          $newReceptBuy->unidad_medida_recibida = $unidad_medida;
          $newReceptBuy->lo_pedido = $lo_pedido;
          $newReceptBuy->llego_tiempo = $llegoTiempo;
          $newReceptBuy->buen_estado = $buenEstado;
          $newReceptBuy->calidad_devengacion = $calidadRecepcion;
          $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
          $newReceptBuy->establecimiento = $sql_establecimiento;
          $newReceptBuy->recept_status = $checked_recept;
          $newReceptBuy->valida_recept = $vEmp->userr;
          $newReceptBuy->empresa = $vEmp->id;
          $newReceptBuy->save();

          if ($checked_recept) {
            $selectRecept = $newReceptBuy->id;
            $tookenAlmDos = $JwtAuth->encriptarToken($tookenRecept_compra.$sql_establecimiento.$compra_detalle->id. time());
            $id_insert_almacen = DB::table('in_egr_establecimientos_almacen')
            ->insertGetId([
              "token_establecimiento_almacen" => $tookenAlmDos,
              "almacen" => $sql_establecimiento,
              "nivel_almacen" => 3,
              "num_serie" => $activo_serie,
              "existencia" => 1,
              "unidad_entrada" => $compra_detalle->unidad_medida,
              "costo_aplicable" => $compra_detalle->precio_unitario,
              "status_disponibilidad" => TRUE,
              "recepcion_compra" => $selectRecept,
              "empresa" => $vEmp->id,
            ]);
            
            DB::table('eegr_activos_fijos_almacen')
            ->insert(array(
              "almacen_general" => $id_insert_almacen,
              "activo_fijo" => $activo_fijo_data->FCATID,
              "detalle_activo_fijo" => $activo_fijo_data->FDETID,
              "unidad_activo_fijo" => $activo_fijo_data->FUNIID
            ));

            DB::table('eegr_activos_fijos_unidades')
            ->where('token_activof_unidad',$token_activof_unidad)
            ->limit(1)->update(
              array(
                "unidad_serie" => $activo_serie,
                "unidad_otros" => $JwtAuth->encriptar($activo_otros),
                "unidad_observaciones" => $JwtAuth->encriptar($observaciones)
              )
            );

            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Recepción para el activo don folio $folio_activof_unidad ha sido registrada con éxito"], 200);
          }
        } catch (\Throwable $e) {
          DB::rollBack();
          // 1. Guardar el error real en storage/logs/laravel.log
          \Log::error("Error al recibir activo: " . $e->getMessage());
      
          // 2. Responder al usuario con algo genérico
          return response()->json(['status' => 'error', 'message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.'], 500);
        }
      } else {
        if (!$validaCompraTKN) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'compra indefinida ó invalida',
          );
        }

        if (!$validaActivosFijos) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'lista de recepción incompleta ó invalida',
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recibeServComprasAlmacen(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
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
									FROM eegr_compras_devengacion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
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
                $newReceptBuy = new OrdenDevengacionModelo();
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
              $newReceptBuy->calidad_devengacion = $calidadRecepcion;
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

  public function habilitaPeridoEspera(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
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
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

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
            'message' => 'periodo de espera habilitado con fecha maxima ' . gmdate('Y-m-d H:i:s', ($fecha_timer + 86400)),
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
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
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

          if ($resSelectRechazos->calidad_devengacion == TRUE) {
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
            "fecha_rechazo" => gmdate('Y-m-d H:i:s', $resSelectRechazos->fecha_rechazo),
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
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_compra' => 'required|string',
      'productList' => 'required|array'
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
      $token_compra = $request->input('token_compra');
      $productList = $request->input('productList');

      $validaCompraToken = isset($token_compra) && !empty($token_compra); 
      $validaListaProductos = isset($productList) && !empty($productList);
      
      if ($validaCompraToken && $validaListaProductos) {
        $contadorRecept = 0;
        for ($i = 0; $i < count($productList); $i++) {
          $producto = $productList[$i]['articulo'];
          $token_product = $productList[$i]['token_articulo'];
          $token_detcompra = $productList[$i]['token_detcompra'];
          $lo_pedido = $productList[$i]['lo_pedido'];
          $llegoTiempo = $productList[$i]['llegoTiempo'];
          $buenEstado = $productList[$i]['buenEstado'];
          $calidadRecepcion = $productList[$i]['calidadRecepcion'];
          $observaciones =  $productList[$i]['observaciones'];
          $checked_recept = $productList[$i]['checked_recept'];
          $establecimiento = $productList[$i]['establecimiento'];

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

        if ($contadorRecept == count($productList)) {
          $validateRecept = true;
        }

        if ($contadorRecept == count($productList) && $validateRecept == true) {
          $contadorReceptInsert = 0;
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp  
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

          $selectCompras = DB::select("SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp  
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
              WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_compra, $usuario->empresa_token, $usuario->user_token]);

          for ($i = 0; $i < count($productList); $i++) {
            $prod_recep_fecha_contabilizacion = $productList[$i]['prod_recep_fecha_contabilizacion'];
            $producto = $productList[$i]['articulo'];
            $token_product = $productList[$i]['token_articulo'];
            $token_detcompra = $productList[$i]['token_detcompra'];
            $lo_pedido = $productList[$i]['lo_pedido'];
            $llegoTiempo = $productList[$i]['llegoTiempo'];
            $buenEstado = $productList[$i]['buenEstado'];
            $calidadRecepcion = $productList[$i]['calidadRecepcion'];
            $observaciones =  $productList[$i]['observaciones'];
            $checked_recept = $productList[$i]['checked_recept'];
            $establecimiento = $productList[$i]['establecimiento'];
            
            $listUpdateKardex = DB::table('eegr_compras')
            ->where('token_compras',$token_compra)
            ->limit(1)->update(
              array(
                'fecha_real_recepcion' => $JwtAuth->convierteFechaEpoc($prod_recep_fecha_contabilizacion),
              )
            );

            $folioRecept = DB::select(
              "SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
                FROM eegr_compras_devengacion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? 
                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
              [$usuario->empresa_token, $usuario->user_token]
            );

            $selectCompras = DB::select("SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras FROM eegr_compras AS buy JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
              AND empuser.usuario = users.id AND users.usuario_token= ?",[$token_compra, $usuario->empresa_token, $usuario->user_token]);

            $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy JOIN eegr_compras AS buy 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
              AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
              [$token_detcompra, $token_compra, $usuario->empresa_token, $usuario->user_token]
            );

            $selectCatProd = DB::select("SELECT catprod.id,catprod.unidad_medida_salida_clave FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
              AND users.usuario_token= ?",[$token_product, $usuario->empresa_token, $usuario->user_token]);//,prodList.medida_entrada,prodList.medida_salida
              //unidad_medida_entrada_clave,unidad_medida_entrada_homologada,unidad_medida_salida_clave,unidad_medida_salida_homologada

            $fecha_registro = time();
            $sql_establecimiento = $establecimiento != '' ?  DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id") : '';
            $tookenRecept_compra = $JwtAuth->encriptarToken($fecha_registro . $token_compra . $token_product . $token_detcompra);

            $newReceptBuy = new OrdenDevengacionModelo();//eegr_compras_devengacion
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
            $newReceptBuy->calidad_devengacion = $calidadRecepcion;
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
                  [$token_compra, $token_detcompra, $usuario->empresa_token, $usuario->user_token]
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
                  'buy.token_compras' => $token_compra,
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

          if ($contadorReceptInsert == count($productList)) {
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
        if (!$validaCompraToken) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'compra indefinida ó invalida',
          );
        }

        if (!$validaListaProductos) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'lista de recepción incompleta ó invalida',
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recibeActivoIntangComprasAlmacen(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
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
                                    FROM eegr_compras_devengacion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
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
                $newReceptBuy = new OrdenDevengacionModelo();
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
              $newReceptBuy->calidad_devengacion = $calidadRecepcion;
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

  public function listaComprasRechazos(Request $request){
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

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

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
            "fecha_rechazo" => gmdate('Y-m-d H:i:s', $vRec->fecha_rechazo),
            "folio_rechazo" => "RECEPT-".$JwtAuth->generarFolio($vRec->folio_rechazo),
            "compra" => $folio_comp,
            "articulo_tipo" => $articulo_tipo,
            "articulo_folio" => $articulo_folio,
            "articulo_name" => $articulo_name,
            "lo_pedido" => $vRec->lo_pedido ? true : false,
            "llego_tiempo" => $vRec->llego_tiempo ? true : false,
            "buen_estado" => $vRec->buen_estado ? true : false,
            "calidad_recepcion" => $vRec->calidad_devengacion ? true : false,
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

  public function detalleComprasDevengServ(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
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
          //da_te_default_timezone_set($vBuy->zona_horaria);
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
              $fecha_devengado = $JwtAuth->mostrarUnixAFechaMexico($selectRecibido[0]->fecha_devengacion);
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
            "fecha_sistemaCompras" => $JwtAuth->mostrarUnixAFechaMexico($vBuy->fecha_sistemaCompras),
            "fecha_altaCompra" => $JwtAuth->mostrarUnixAFechaMexico($vBuy->fecha_altaCompra),
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
                $newReceptBuy->fecha_devengacion = $fecha_devengado;
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
}