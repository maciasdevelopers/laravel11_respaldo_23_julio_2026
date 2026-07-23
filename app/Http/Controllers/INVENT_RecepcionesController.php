<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\ComprasModelo;
use App\Models\RecepcionCompraModelo;
use Illuminate\Support\Str;
use App\Services\KardexService;
use PDF;
use QRCode;

class INVENT_RecepcionesController extends Controller{
  protected $kardexService;

  public function __construct(KardexService $kardexService) {
    $this->kardexService = $kardexService;
  }

  private function eachOrdenRecepcion($listaOrdenes,$JwtAuth){
    $idCompras = $listaOrdenes->pluck('id_comp')->filter()->unique()->toArray();
    $idProveedor = $listaOrdenes->pluck('proveedor')->filter()->unique()->toArray();
    $idUsuarioComprador = $listaOrdenes->pluck('usuario_comprador')->filter()->unique()->toArray();
    $idUsuarioAutoriza = $listaOrdenes->pluck('autoriza')->filter()->unique()->toArray();
    
    $compraProvMap = DB::table("sos_personas AS people")
    ->join("eegr_catalogo_proveedores AS catprov", "people.id", "=", "catprov.proveedor")
    ->whereIn('catprov.id',$idProveedor)
    ->get()->keyBy('id');

    $userCompradorMap = DB::table("sos_personas AS people")
    ->join("vhum_empleados_catalogo AS pers", "people.id", "=", "pers.empleado_name")
    ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
    ->whereIn('users.id',$idUsuarioComprador)
    ->get()->keyBy('id');
    
    $userAuthorizaMap = DB::table("sos_personas AS people")
    ->join("vhum_empleados_catalogo AS pers", "people.id", "=", "pers.empleado_name")
    ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
    ->whereIn('users.id',$idUsuarioAutoriza)
    ->get()->keyBy('id');

    $cantidadesRecibidasMap = DB::table("eegr_compras_recepcion")
    ->whereIn('compra', $idCompras)
    ->groupBy('compra')
    ->select('compra', DB::raw('SUM(cantidad_recibida) as total'))
    ->get()->pluck('total', 'compra');

    // 4. NUEVO: Agrupamos y calculamos los importes totales financieros directamente desde la BD
    $detallesCompraMap = DB::table("eegr_compras_detalle AS detBuy")
    ->whereIn('detBuy.numero_compra', $idCompras)
    ->select(
      'detBuy.numero_compra AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*'
    )
    ->get()
    ->groupBy('id_compras'); // Agrupa las colecciones por ID de compra

    /*$detailsProductosMap = DB::table("eegr_compras_detalle AS detBuy")
    ->whereNull('detBuy.servicio')
    ->whereNull('detBuy.activo_fijo')
    ->whereNull('detBuy.activo_intangible')
    ->whereIn('detBuy.numero_compra',$idCompras)
    ->select(
      'detBuy.numero_compra AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*'
    )
    ->get()->groupBy('id_compras');*/

    //$allDetailIds = $detailsProductosMap->collapse()->pluck('id_det_compras')->unique()->toArray();
    
    /*$detailsActivosFijosMap = DB::table("eegr_compras_detalle AS detBuy")
    ->whereNotNull('detBuy.activo_fijo')
    ->whereIn('detBuy.numero_compra',$idCompras)
    ->select(
      'detBuy.numero_compra AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*'
    )
    ->get()->groupBy('id_compras');*/

    //$allDetailIds = $detailsProductosMap
    //->concat($detailsActivosFijosMap)
    //->collapse()
    //->pluck('id_det_compras')
    //->unique()
    //->toArray();

    $allDetailIds = $detallesCompraMap->collapse()->pluck('id_det_compras')->unique()->toArray();

    $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_compras AS l_comp", "l_uni.transito_compra_id", "=", "l_comp.id")
    ->whereIn("art.articulo_detcompra", $detallesCompraMap->collapse()->pluck('id_det_compras')->unique()->toArray())
    ->select(
      'art.articulo_detcompra AS id_det_compras',
      'l_comp.estado_alcanzado',
      'art.cantidad_asignada'
    )
    ->get()
    ->groupBy('id_det_compras');

    $ordenesRecept = array();

    foreach ($listaOrdenes as $vBuy) {
      $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

      $proveedor_token = "";
      $proveedor_folio = "";
      $proveedor_nombre = "";
      $vProv = $compraProvMap->get($vBuy->proveedor);
      if ($vProv) {
        $proveedor_token = $vProv->token_cat_proveedores;
        $proveedor_folio = 'PRV-'.$JwtAuth->generarFolio($vProv->folio) . ($vProv->post_folio != NULL ? '-' . $vProv->post_folio : '');
        $proveedor_nombre = $JwtAuth->desencriptar($vProv->nombre_extendido);
      }

      $vUserComprador = $userCompradorMap->get($vBuy->usuario_comprador);
      $user_compra = $vUserComprador ? $JwtAuth->desencriptarNombres($vUserComprador->paterno, $vUserComprador->materno, $vUserComprador->nombre) : '';

      $vUserAuth = $userAuthorizaMap->get($vBuy->autoriza);
      $user_autoriza = $vBuy->status_autorizacion && $vUserAuth ? $JwtAuth->desencriptarNombres($vUserAuth->paterno, $vUserAuth->materno, $vUserAuth->nombre) : "";

      $recepcion_token = "";
      $recepcion_prov = "";
      $recepcion_estab = "";
      /*if (!empty($vBuy->recepcion_prov)) { //$value->recepcion_estab 
        # code...
      } else {
        $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
        ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
        ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
        ->where([
          "buy.token_compras" => $vBuy->token_compras,
          "estab.status_establecimiento" => TRUE
        ])
        ->get();

        foreach ($listaDirAlmacen as $vEstab) {
          $recepcion_token = $vEstab->token_establecimiento;
          $recepcion_estab = 'ESTAB-' . $JwtAuth->generarFolio($vEstab->folio_establecimiento) . ($vEstab->post_folio != NULL ? '-' . $vEstab->post_folio : '') . " " . $JwtAuth->desencriptar($vEstab->alias_establecimiento);
        }
      }*/

      // Obtenemos las partidas asignadas de este ID de compra desde la memoria
      $partidasDetalle = $detallesCompraMap->get($vBuy->id_comp, collect());
      $total_articulos_comprados = $partidasDetalle->sum('cantidad');
      $logistica_articulos_entregados = 0;

      $importe_total_compra = 0;
      foreach ($partidasDetalle as $vDet) {
        $resultante = 0;
        $resultante = $vDet->precio_unitario - $vDet->descuento + $vDet->traslados_total - $vDet->retenciones_total;
        $importe_total_compra += $resultante;
        $movimientos = $transitoEstadosMap->get($vDet->id_det_compras) ?? collect([]);
        $entregados = $movimientos->where('estado_alcanzado', 'entregado')->sum('cantidad_asignada'); // Cambia 'entregado' por tu estado final
        $logistica_articulos_entregados += $entregados;
      }

      $total_articulos_recibidos = $cantidadesRecibidasMap->get($vBuy->id_comp, 0);

      $semaforo_entregados = 'text-gray-500';
      $semaforo_pendientes = 'text-gray-500';
      $total_pendientes_recibir = $total_articulos_comprados - $total_articulos_recibidos;

      if ($total_articulos_comprados > 0) {
        // 3. Semáforo para artículos ENTREGADOS
        if ($logistica_articulos_entregados == $total_articulos_comprados) {
          $semaforo_entregados = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
        } elseif ($logistica_articulos_entregados > 0) {
          $semaforo_entregados = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
        } else {
          $semaforo_entregados = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
        }

        if ($total_articulos_recibidos == $total_articulos_comprados) {
          $semaforo_pendientes = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
        } elseif ($total_articulos_recibidos > 0) {
          $semaforo_pendientes = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
        } else {
          $semaforo_pendientes = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
        }
      }

      $rowOrdMain = array(
        "token_compras" => $vBuy->token_compras,
        "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "uuid_orden_recepcion" => $vBuy->uuid_orden_recepcion,
        "folio_recepcion" => "ORDREC-".$JwtAuth->generarFolio($vBuy->folio_recepcion),
        "estado_recepcion" => $vBuy->estado,
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
        "recepcionPago" => $vBuy->recepcionPago,
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
        "seleccion_recepcion" => !empty($vBuy->recepcion_prov) ? 'proveedor' : 'establecimiento',
        "lugar_recepcion_token" => $recepcion_token,
        "lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab,
        //autorizacion 
        "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
        "user_autoriza" => $user_autoriza,
        //"comprador" => $vBuy->comprador,
        "usuario_comprador" => $user_compra,
        //productos y servicios
        "recepcionCollapsed" => true,
        "clase_entregados" => $semaforo_entregados,
        "articulos_entregados" => "$logistica_articulos_entregados / $total_articulos_comprados", 
        "clase_recibidos" => $semaforo_pendientes,
        "articulos_pendientes" => "$total_pendientes_recibir / $total_articulos_comprados",
        //importes
        "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
        //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab,
      );
      $ordenesRecept[] = $rowOrdMain;
    }
    return $ordenesRecept;
  }

  public function listaOrdenesRecepcionCompra(Request $request){
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
      
      $listaOrdenes = ComprasModelo::join("eegr_compras_orden_recepcion AS ordRec", "eegr_compras.id", "=", "ordRec.orden_compra")
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
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("ordRec.fecha_contabilizacion_recep", [$fechaInicio, $fechaFin]);
      })
      ->select('eegr_compras.*', 'ordRec.*', 'eegr_compras.id AS id_comp')
      ->get();

      if ($listaOrdenes->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de recepción registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $ordenes_recepcion = $this->eachOrdenRecepcion($listaOrdenes,$JwtAuth);

        $dataMensaje = array(
          'ordenes' => $ordenes_recepcion,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function detalleRecepcionProd($JwtAuth,$vBuy,$empresa,$usuario){
    $registros = array();
    $fecha_recep = null;
    $folio_recep = null;
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
      /*$selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.calidad_recepcion,recept.recibe_factura,recept.observaciones,recept.recept_status,
        people.paterno,people.materno,people.nombre 
        FROM eegr_compras_recepcion AS recept 
        JOIN eegr_compras AS buy 
        JOIN eegr_compras_detalle AS detbuy 
        JOIN vhum_empleados_catalogo AS peo_buy 
        JOIN sos_personas AS people 
        JOIN main_empresas AS emp 
        JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users 
        WHERE recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? 
        AND recept.valida_recept = peo_buy.id 
        AND peo_buy.empleado_name = people.id 
        AND recept.empresa = emp.id 
        AND emp.empresa_token = ? 
        AND emp.id = empuser.empresa 
        AND empuser.usuario = users.id 
        AND users.usuario_token = ?",
        [$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);*/
  
      $selectRecibido = DB::table("eegr_compras_recepcion AS recept")
      ->join("eegr_compras AS buy","recept.compra","=","buy.id")
      ->join("eegr_compras_detalle AS detbuy", "recept.detalle_compra", "=", "detbuy.id")
      ->join("vhum_empleados_catalogo AS peo_buy", "recept.valida_recept", "=", "peo_buy.id")
      ->join("sos_personas AS people", "peo_buy.empleado_name", "=", "people.id")
      ->join("main_empresas AS emp", "recept.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'buy.token_compras' => $vBuy->token_compras,
        'detbuy.token_detcompra' => $vDetBuy->token_detcompra,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select(
        'recept.cantidad_recibida',
        'recept.fecha_recep',
        'recept.folio_recep',
        'recept.lo_pedido',
        'recept.llego_tiempo',
        'recept.buen_estado',
        'recept.calidad_recepcion',
        'recept.recibe_factura',
        'recept.observaciones',
        'recept.recept_status',
        'people.paterno',
        'people.materno',
        'people.nombre'
      )
      ->get();
      
      if (count($selectRecibido) > 0) {
        $fecha_recep = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
        $folio_recep = $JwtAuth->generar($selectRecibido[0]->folio_recep);
        $bool_recept_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
        $bool_recept_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);
      }

      $tipo_articulo = $vDetBuy->activo_fijo == NULL && $vDetBuy->activo_intangible == NULL ? 'producto' : ($vDetBuy->activo_fijo != NULL ? 'activo fijo' : 'activo intangible');

      $folio_prod = $vDetBuy->folio_sistema != NULL && $vDetBuy->folio_sistema != "" ? ('PROD-'.($vDetBuy->post_folio == NULL ? $JwtAuth->generarFolio($vDetBuy->folio_sistema) : $JwtAuth->generarFolio($vDetBuy->folio_sistema).'-'.$vDetBuy->post_folio)) : 'PROD-TEMP-'.$JwtAuth->generarFolio($vDetBuy->temps_folio);

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
        "fecha_recep" => $fecha_recep,
        "folio_recep" => $folio_recep,
        "lo_pedido" => count($selectRecibido) > 0 && $selectRecibido[0]->lo_pedido ? true : false,
        "llegoTiempo" => count($selectRecibido) > 0 && $selectRecibido[0]->llego_tiempo ? true : false,
        "buenEstado" => count($selectRecibido) > 0 && $selectRecibido[0]->buen_estado ? true : false,
        "calidadRecepcion" => count($selectRecibido) > 0 && $selectRecibido[0]->calidad_recepcion ? true : false,
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
    $list_unidades_recepcion = array();
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
        
        $fecha_recep = null;
        $folio_recep = null;
        $bool_recept_observaciones = null;
        $bool_recept_validado_por = null;
        $recept_establecimiento = "";

        $selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.calidad_recepcion,recept.recibe_factura,recept.establecimiento AS estab_recept,recept.observaciones,
          recept.recept_status,people.paterno,people.materno,people.nombre FROM eegr_compras_recepcion AS recept JOIN eegr_activos_fijos_unidades AS actfUnid JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy 
          JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.unidad_activo_fijo = actfUnid.id 
          AND actfUnid.token_activof_unidad = ? AND recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND recept.valida_recept = peo_buy.id 
          AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$vDetActF->token_activof_unidad,$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);
    
        if (count($selectRecibido) != 0) {
          $fecha_recep = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
          $folio_recep = $JwtAuth->generarFolio($selectRecibido[0]->folio_recep);
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
          "fecha_recep" => $fecha_recep,
          "folio_recep" => $folio_recep,
          "lo_pedido" => count($selectRecibido) > 0 && $selectRecibido[0]->lo_pedido ? true : false,
          "llegoTiempo" => count($selectRecibido) > 0 && $selectRecibido[0]->llego_tiempo ? true : false,
          "buenEstado" => count($selectRecibido) > 0 && $selectRecibido[0]->buen_estado ? true : false,
          "calidadRecepcion" => count($selectRecibido) > 0 && $selectRecibido[0]->calidad_recepcion ? true : false,
          "recibe_factura" => count($selectRecibido) > 0 && $selectRecibido[0]->recibe_factura ? true : false,
          "observaciones" => $bool_recept_observaciones,
          "checked_recept" => count($selectRecibido) > 0 && $selectRecibido[0]->recept_status ? true : false,
          "establecimiento" => $recept_establecimiento,
          "archivos_cargados_names" => [],
          "archivos_cargados_files" => [],
          "validado_por" => $bool_recept_validado_por,
          "seleccionado" => false,
        );
        $list_unidades_recepcion[] = $row_act_de_buy;
      }
    }
    return $list_unidades_recepcion;
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
    $list_unidades_recepcion = array();
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
        
        $fecha_recep = null;
        $folio_recep = null;
        $bool_recept_observaciones = null;
        $bool_recept_validado_por = null;
        $recept_establecimiento = "";

        $selectRecibido = DB::select("SELECT recept.fecha_recep,recept.folio_recep,recept.lo_pedido,recept.llego_tiempo,recept.buen_estado,recept.calidad_recepcion,recept.recibe_factura,recept.establecimiento AS estab_recept,recept.observaciones,
          recept.recept_status,people.paterno,people.materno,people.nombre FROM eegr_compras_recepcion AS recept JOIN eegr_activos_fijos_unidades AS actfUnid JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy 
          JOIN vhum_empleados_catalogo AS peo_buy JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE recept.unidad_activo_fijo = actfUnid.id 
          AND actfUnid.token_activof_unidad = ? AND recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND recept.valida_recept = peo_buy.id 
          AND peo_buy.empleado_name = people.id AND recept.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$vDetActDif->token_activod_unidad,$vBuy->token_compras,$vDetBuy->token_detcompra,$empresa,$usuario]);
    
        if (count($selectRecibido) != 0) {
          $fecha_recep = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
          $folio_recep = $JwtAuth->generarFolio($selectRecibido[0]->folio_recep);
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
          "fecha_recep" => $fecha_recep,
          "folio_recep" => $folio_recep,
          "observaciones" => $bool_recept_observaciones,
          "checked_recept" => count($selectRecibido) > 0 && $selectRecibido[0]->recept_status ? true : false,
          "establecimiento" => $recept_establecimiento,
          "archivos_cargados_names" => [],
          "archivos_cargados_files" => [],
          "validado_por" => $bool_recept_validado_por,
          "seleccionado" => false,
        );
        $list_unidades_recepcion[] = $row_act_de_buy;
      }
    }
    return $list_unidades_recepcion;
  }

  public function detalleOrdenRecepcion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'orden_recepcion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $orden_recepcion = $request->input('orden_recepcion');
      
      $listaReceptCompras = ComprasModelo::join("eegr_compras_orden_recepcion AS ordRec", "eegr_compras.id", "=", "ordRec.orden_compra")
      ->join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->whereIn('eegr_compras.id', function ($query) {
        $query->select('numero_compra')->from('eegr_compras_detalle');
      })
      ->where('eegr_compras.status_autorizacion',TRUE)
      ->where('ordRec.uuid_orden_recepcion',$orden_recepcion)
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

          $productos = $this->detalleRecepcionProd($JwtAuth,$vBuy,$empresa,$usuario);
          $activos_fijos = $this->detalleRecepcionActivoFijo($JwtAuth,$vBuy,$empresa,$usuario);
          //$servicios = $this->detalleRecepcionServ($JwtAuth,$vBuy,$empresa,$usuario);
          //$activos_diferidos = $this->detalleRecepcionActivoDiferido($JwtAuth,$vBuy,$empresa,$usuario);
          $arrayForeach = array(
            "uuid_orden_recepcion" => $vBuy->uuid_orden_recepcion,
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
            //"servicios" => $servicios,
            //"activos_diferidos" => $activos_diferidos,
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
            ->from('eegr_compras_recepcion as r')
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
    
          $recepcion_token = "";
          $recepcion_prov = "";
          $recepcion_estab = "";
          if (!empty($value->recepcion_prov)) { //$value->recepcion_estab 
            # code...
          } else {
            $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where([
                "buy.token_compras" => $vBuy->token_compras,
                "estab.status_establecimiento" => TRUE
              ])->get();
    
            foreach ($listaDirAlmacen as $vEstab) {
              $recepcion_token = $vEstab->token_establecimiento;
              $recepcion_estab = 'ESTAB-' . $JwtAuth->generarFolio($vEstab->folio_establecimiento) . ($vEstab->post_folio != NULL ? '-' . $vEstab->post_folio : '') . " " . $JwtAuth->desencriptar($vEstab->alias_establecimiento);
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
            "recepcionPago" => $vBuy->recepcionPago,
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
            "seleccion_recepcion" => !empty($vBuy->recepcion_prov) ? 'proveedor' : 'establecimiento',
            "lugar_recepcion_token" => $recepcion_token,
            "lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //"comprador" => $vBuy->comprador,
            "usuario_comprador" => $user_compra,
            //productos y servicios
            "articulos_recibidos" => count($queryDEtailsTotal),
            "recepcionCollapsed" => true,
            "total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
            //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab, 
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
        
          $recepcion_token = "";
          $recepcion_prov = "";
          $recepcion_estab = "";
          if (!empty($value->recepcion_prov)) { //$value->recepcion_estab 
            # code...
          } else {
            $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
              ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
              ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
              ->where([
                "buy.token_compras" => $vBuy->token_compras,
                "estab.status_establecimiento" => TRUE
              ])->get();
              
            foreach ($listaDirAlmacen as $vEstab) {
              $recepcion_token = $vEstab->token_establecimiento;
              $recepcion_estab = 'ESTAB-' . $JwtAuth->generarFolio($vEstab->folio_establecimiento) . ($vEstab->post_folio != NULL ? '-' . $vEstab->post_folio : '') . " " . $JwtAuth->desencriptar($vEstab->alias_establecimiento);
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
            "recepcionPago" => $vBuy->recepcionPago,
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
            "seleccion_recepcion" => !empty($vBuy->recepcion_prov) ? 'proveedor' : 'establecimiento',
            "lugar_recepcion_token" => $recepcion_token,
            "lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab,
            //autorizacion 
            "status_autorizacion" => $vBuy->status_autorizacion ? true : false,
            "user_autoriza" => $user_autoriza,
            //"comprador" => $vBuy->comprador,
            "usuario_comprador" => $user_compra,
            //productos y servicios
            "articulos_recibidos" => count($queryDEtailsTotal),
            "recepcionCollapsed" => true,
            "total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
            //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab, 
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
  
          $maxFolio = DB::table('eegr_compras_recepcion')
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
  
          $newReceptBuy = new RecepcionCompraModelo();
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
          $newReceptBuy->calidad_recepcion = $calidadRecepcion;
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

  public function recibeProdValidaData($productList,$JwtAuth){
    $errorAlerta = '';
    foreach ($productList as $vProd) {
      $producto = $vProd['articulo'];
      $token_product = $vProd['token_articulo'];
      $token_detcompra = $vProd['token_detcompra'];
      $lo_pedido = $vProd['lo_pedido'];
      $llegoTiempo = $vProd['llegoTiempo'];
      $buenEstado = $vProd['buenEstado'];
      $calidadRecepcion = $vProd['calidadRecepcion'];
      $observaciones =  $vProd['observaciones'];
      $checked_recept = $vProd['checked_recept'];
      $establecimiento = $vProd['establecimiento'];

      $validate_token_product = isset($token_product) && !empty($token_product);
      $validate_token_detcompra = isset($token_detcompra) && !empty($token_detcompra);
      $validate_lo_pedido = isset($lo_pedido) && is_bool($lo_pedido) === true;
      $validate_llegoTiempo = isset($llegoTiempo) && is_bool($llegoTiempo) === true;
      $validate_buenEstado = isset($buenEstado) && is_bool($buenEstado) === true;
      $validate_calidadRecepcion = isset($calidadRecepcion) && is_bool($calidadRecepcion) === true;
      $validate_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);
      $validate_checked_recept = isset($checked_recept) && is_bool($checked_recept) === true;
      $validate_establecimiento = isset($establecimiento) && !empty($establecimiento);
      
      if (!$validate_token_product) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';break;}
      if (!$validate_token_detcompra) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';break;}
      if (!$validate_lo_pedido) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' es lo que ha pedido';break;}
      if (!$validate_llegoTiempo) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' llegó a tiempo';break;}
      if (!$validate_buenEstado) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' llegó en buen estado';break;}
      if (!$validate_calidadRecepcion) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' corresponde a la calidad esperada';break;}
      if (!$validate_observaciones) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' no tiene observaciones';break;}
      if (!$validate_checked_recept) {$errorAlerta = 'No seleccionó si el articulo ' . $producto . ' será recibido';break;}
      if ($checked_recept && !$validate_establecimiento) {$errorAlerta = 'No seleccionó establecimiento para recepción';break;}
    }
    return $errorAlerta;
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
      'uuid_orden_recepcion' => 'required|string',
      'token_compras' => 'required|string',
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
      $uuid_orden_recepcion = $request->input('uuid_orden_recepcion');
      $token_compras = $request->input('token_compras');
      $productList = $request->input('productList');

      $OKUUIDOrdenRecep = isset($uuid_orden_recepcion) && !empty($uuid_orden_recepcion);
      $OKCompraToken = isset($token_compras) && !empty($token_compras); 
      $OKListaProductos = isset($productList) && !empty($productList);
      
      if ($OKUUIDOrdenRecep && $OKCompraToken && $OKListaProductos) {
        $detalleErrores = $this->recibeProdValidaData($productList,$JwtAuth);

        if ($detalleErrores != "") {
          return response()->json(['status' => 'error','code' => 200,'message' => $detalleErrores], 200);
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

        if (!$vEmp) {
          return response()->json(['status' => 'error','code' => 200,'message' => 'Empresa no vinculada al usuario'], 200);
        }

        $vCompras = DB::table("eegr_compras AS buy")
        ->join("main_empresas AS emp", "buy.comprador", "empuser.empresa")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'buy.token_compras' => $token_compras,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('buy.id')
        ->first();

        if (!$vCompras) {
          return response()->json(['status' => 'error','code' => 200,'message' => 'Compra no registrada'], 200);
        }

        /*$selectCompras = DB::select("SELECT buy.id FROM eegr_compras AS buy JOIN main_empresas AS emp  
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$token_compra, $usuario->empresa_token, $usuario->user_token]);

        $selectCompras = DB::select("SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras FROM eegr_compras AS buy JOIN main_empresas AS emp 
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
          AND empuser.usuario = users.id AND users.usuario_token= ?",[$token_compra, $usuario->empresa_token, $usuario->user_token]);

        $selectComprasDetalle = DB::select("SELECT detbuy.id,detbuy.cantidad,detbuy.unidad_medida,detbuy.serie,detbuy.lote,detbuy.pedimento_aduanal FROM eegr_compras_detalle AS detbuy JOIN eegr_compras AS buy 
          JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detbuy.token_detcompra = ? AND detbuy.numero_compra = buy.id AND buy.token_compras = ? 
          AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
          [$token_detcompra, $token_compra, $usuario->empresa_token, $usuario->user_token]
        );*/

        $contadorReceptInsert = 0;
        for ($i = 0; $i < count($productList); $i++) {
          $prod_recep_fecha_contabilizacion = $productList[$i]['prod_recep_fecha_contabilizacion'];
          $producto = $productList[$i]['articulo'];
          $token_product = $productList[$i]['token_articulo'];
          $token_detcompra = $productList[$i]['token_detcompra'];
          $cantidad_recibir = $productList[$i]['cantidad_recibir'];
          $lo_pedido = $productList[$i]['lo_pedido'];
          $llegoTiempo = $productList[$i]['llegoTiempo'];
          $buenEstado = $productList[$i]['buenEstado'];
          $calidadRecepcion = $productList[$i]['calidadRecepcion'];
          $observaciones =  $productList[$i]['observaciones'];
          $checked_recept = $productList[$i]['checked_recept'];
          $establecimiento = $productList[$i]['establecimiento'];
        
          $maxFolioRecept = DB::table('eegr_compras_recepcion AS recept')
          ->join('main_empresas AS emp', 'recept.empresa', '=', 'emp.id')
          ->join('main_empresa_usuario AS empuser', 'emp.id', '=', 'empuser.empresa')
          ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', '=', 'users.id')
          ->where('emp.empresa_token', $empresa)
          ->where('users.usuario_token', $usuario)
          ->lockForUpdate() // 🔒 Bloquea las lecturas simultáneas de folios para esta empresa
          ->max('recept.folio_recep');

          $folioRecept = $maxFolioRecept ? $maxFolioRecept + 1 : 1;

          $vDetCompra = DB::table("eegr_compras_detalle")
          ->where([
            'token_detcompra' => $token_detcompra,
            'numero_compra' => $vCompras->id
          ])
          ->select('id','precio_unitario','cantidad','unidad_medida','serie','lote','pedimento_aduanal')
          ->first();

          /*$selectCatProd = DB::select("SELECT catprod.id,catprod.unidad_medida_salida_clave FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
            AND users.usuario_token= ?",[$token_product, $usuario->empresa_token, $usuario->user_token]);*///,prodList.medida_entrada,prodList.medida_salida
            //unidad_medida_entrada_clave,unidad_medida_entrada_homologada,unidad_medida_salida_clave,unidad_medida_salida_homologada
            
          $vProducto = DB::table("in_egr_catalogo_productos")
          ->where([
            'token_cat_productos' => $token_product
          ])
          ->select('id','unidad_medida_salida_clave')
          ->first();

          $fecha_registro = time();
          $sql_establecimiento = $establecimiento != '' ?  DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id") : '';
          $tookenRecept_compra = Str::uuid()->toString();

          $newReceptBuy = new RecepcionCompraModelo();//eegr_compras_recepcion
          $newReceptBuy->token_recept_compra = $tookenRecept_compra;
          $newReceptBuy->fecha_recep = $fecha_registro;
          $newReceptBuy->folio_recep = $folioRecept;
          $newReceptBuy->compra = $vCompras->id;
          $newReceptBuy->detalle_compra = $vDetCompra->id;
          $newReceptBuy->producto = $vProducto->id;
          $newReceptBuy->activo_fijo = NULL;
          $newReceptBuy->activo_intangible = NULL;
          $newReceptBuy->servicio = NULL;
          $newReceptBuy->cantidad_recibir = $cantidad_recibir;
          $newReceptBuy->lo_pedido = $lo_pedido;
          $newReceptBuy->llego_tiempo = $llegoTiempo;
          $newReceptBuy->buen_estado = $buenEstado;
          $newReceptBuy->calidad_recepcion = $calidadRecepcion;
          $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
          $newReceptBuy->establecimiento = $sql_establecimiento;
          $newReceptBuy->recept_status = $checked_recept;
          $newReceptBuy->valida_recept = $vEmp->userr;
          $newReceptBuy->empresa = $vEmp->id;
          $insert_recepcion_compras = $newReceptBuy->save();

          if ($insert_recepcion_compras) {
            DB::table('eegr_compras')->where('id',$vCompras->id)
            ->limit(1)->update(array('fecha_real_recepcion' => $JwtAuth->convierteFechaEpoc($prod_recep_fecha_contabilizacion)));

            if ($checked_recept == TRUE) {
              $selectRecept = $newReceptBuy->id;

              $this->kardexService->registrarEntradaMovimientoFisico(
                $vProducto->id,
                $cantidad_recibir,
                $vDetCompra->precio_unitario,
                'en_transito_compra', // Viene de lo disponible
                'disponible', // El stock final real sigue quedando disponible en su saldo residual
                'Recepción registrada en nuestros almacenes',
                'VENTA',
                $vCompras->id,
                $vDetCompra->id
              );

              $establ = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$establecimiento)->value("id");

              /*$selectKardex = DB::select(
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
              );*/

              $tookenAlmDos = Str::uuid()->toString();
              $insertProd = DB::table('in_egr_establecimientos_almacen')
                ->insert(array(
                  "token_establecimiento_almacen" => $tookenAlmDos,
                  "almacen" => $establ,
                  "producto" => $vProducto->id,
                  "activo" => NULL,
                  "nivel_almacen" => 3,
                  "num_serie" => $vDetCompra->serie,
                  "num_lote" => $vDetCompra->lote,
                  "importado" => $vDetCompra->pedimento_aduanal,
                  "existencia" => $vDetCompra->cantidad,
                  "unidad_entrada" => $vDetCompra->unidad_medida,
                  "unidad_salida" => $vProducto->unidad_medida_salida_clave,
                  "costo_aplicable" => $vDetCompra->precio_unitario,
                  "status_disponibilidad" => TRUE,
                  "recepcion_compra" => $selectRecept,
                  "empresa" => $vEmp->id,
                ));

              if ($insertProd) {//$listUpdateKardex && $insertProd
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
      } else {
        $mensaje_error = '';
        if (!$OKUUIDOrdenRecep) { $mensaje_error = 'orden de recepción indefinida ó invalida'; }
        if (!$OKCompraToken) { $mensaje_error = 'compra indefinida ó invalida'; }
        if (!$OKListaProductos) { $mensaje_error = 'lista de recepción incompleta ó invalida'; }        
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error,
        );
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
}