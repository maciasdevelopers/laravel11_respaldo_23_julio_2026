<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ComprasModelo;
use App\Models\OrdenPagoModelo;
use App\Models\OrdenRecepcionModelo;
use App\Models\OrdenDevengacionModelo;
use App\Models\CFDITrasladoModelo;
use App\Services\KardexService;

class EGRE_ComprasRegistroController extends Controller{
  protected $kardexService;
  
  public function __construct(KardexService $kardexService) {
    $this->kardexService = $kardexService;
  }

  public function selectFolioCompra(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();

    $folioCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])->count();

    return response()->json([
      'folioCompleto' => $JwtAuth->generar($folioCompras + 1),
      'folio' => $folioCompras + 1,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function listaGeneralArticulosCompras(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $queryCatActFijo = DB::table('in_egr_catalogo_servicios as catserv')
    ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
    ->where("catserv.folio_sistema","999999998")
    ->where('catserv.status', TRUE)
    ->whereNull('catserv.administrador')
    ->select([
      'catserv.token_cat_servicios as token_articulo',
      'catserv.fecha_registro_serv as fecha_alta',
      DB::raw("'ActivoFijo' as identificador"),
      'catserv.servicio as concepto',
      DB::raw("'---' as marca"),
      'catserv.clasificacion',
      'gen.folio_genero as genero',
      'catserv.folio_sistema',
      'catserv.post_folio',
      DB::raw("'---' as root_tkn"),
      DB::raw('FALSE as num_serie'),
      DB::raw('FALSE as num_lote'),
      DB::raw('FALSE as importado'),
      'catserv.precioBase as precioUnitario'
    ]);
    
    $queryCatActDiferido = DB::table('in_egr_catalogo_servicios as catserv')
    ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
    ->where("catserv.folio_sistema","999999999")
    ->where('catserv.status', TRUE)
    ->whereNull('catserv.administrador')
    ->select([
      'catserv.token_cat_servicios as token_articulo',
      'catserv.fecha_registro_serv as fecha_alta',
      DB::raw("'ActivoDiferido' as identificador"),
      'catserv.servicio as concepto',
      DB::raw("'---' as marca"),
      'catserv.clasificacion',
      'gen.folio_genero as genero',
      'catserv.folio_sistema',
      'catserv.post_folio',
      DB::raw("'---' as root_tkn"),
      DB::raw('FALSE as num_serie'),
      DB::raw('FALSE as num_lote'),
      DB::raw('FALSE as importado'),
      'catserv.precioBase as precioUnitario'
    ]);

    /*$queryCatProd = "SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,catprod.producto AS concepto,catprod.marca,catprod.clasificacion,gen.folio_genero AS genero,
      catprod.folio_sistema,catprod.post_folio,emp.root_tkn,catprod.num_serie,catprod.num_lote,catprod.importado,catprod.costo_aplicable AS precioUnitario FROM in_egr_catalogo_productos AS catprod 
      JOIN sos_ps_genero as gen JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprod.genero = gen.id AND catprod.modulo_mostrador = FALSE AND catprod.status = TRUE
      AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";*/

    $queryCatProd = DB::table('in_egr_catalogo_productos as catprod')
    ->join('sos_ps_genero as gen', 'catprod.genero', '=', 'gen.id')
    ->join('main_empresas as emp', 'catprod.admin_empresa', '=', 'emp.id')
    ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
    ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
    ->where('catprod.modulo_mostrador', FALSE)
    ->where('catprod.status', TRUE)
    ->where('emp.empresa_token', $empresa)
    ->where('users.usuario_token', $usuario)
    ->select([
      'catprod.token_cat_productos as token_articulo',
      'catprod.fecha_registro_prod as fecha_alta',
      DB::raw("'Producto' as identificador"),
      'catprod.producto as concepto',
      'catprod.marca',
      'catprod.clasificacion',
      'gen.folio_genero as genero',
      'catprod.folio_sistema',
      'catprod.post_folio',
      'emp.root_tkn',
      'catprod.num_serie',
      'catprod.num_lote',
      'catprod.importado',
      'catprod.costo_aplicable as precioUnitario'
    ]);

    /*$queryCatServ = "SELECT catserv.token_cat_servicios AS token_articulo,catserv.fecha_registro_serv AS fecha_alta,'Servicio' AS identificador,catserv.servicio AS concepto,'---' AS marca,catserv.clasificacion,gen.folio_genero AS genero,
      catserv.folio_sistema,catserv.post_folio,emp.root_tkn,FALSE AS num_serie,FALSE AS num_lote,FALSE AS importado,catserv.precioBase AS precioUnitario FROM in_egr_catalogo_servicios AS catserv 
      JOIN sos_ps_genero as gen JOIN main_empresas AS emp  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catserv.genero = gen.id AND catserv.proceso = 'c' AND catserv.status = TRUE
      AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";*/

    $queryCatServ = DB::table('in_egr_catalogo_servicios as catserv')
    ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
    ->join('main_empresas as emp', 'catserv.administrador', '=', 'emp.id')
    ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
    ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
    ->where('catserv.proceso', 'c')
    ->where('catserv.status', TRUE)
    ->where('emp.empresa_token', $empresa)
    ->where('users.usuario_token', $usuario)
    ->select([
      'catserv.token_cat_servicios as token_articulo',
      'catserv.fecha_registro_serv as fecha_alta',
      DB::raw("'Servicio' as identificador"),
      'catserv.servicio as concepto',
      DB::raw("'---' as marca"),
      'catserv.clasificacion',
      'gen.folio_genero as genero',
      'catserv.folio_sistema',
      'catserv.post_folio',
      'emp.root_tkn',
      DB::raw('FALSE as num_serie'),
      DB::raw('FALSE as num_lote'),
      DB::raw('FALSE as importado'),
      'catserv.precioBase as precioUnitario'
    ]);

    //$combinado = "{$queryCatProd} UNION {$queryCatServ}";
    //$resultQuery = DB::select($combinado, [$empresa, $usuario, $empresa, $usuario]);
    $resultQuery = $queryCatActFijo->unionAll($queryCatActDiferido)->unionAll($queryCatProd)->unionAll($queryCatServ)->get();

    $countList = 0;
    foreach ($resultQuery as $value) {
      $conceptoArticulo = $JwtAuth->desencriptar($value->concepto) . ($value->marca != '' && $value->marca != '---' ? ' ' . $JwtAuth->desencriptar($value->marca) : '');
      $imgArticulo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";

      $prov_relacionado_token = "";
      $prov_relacionado_rfc = "";
      $prov_relacionado_tiene_clave = false;
      $prov_relacionado_clave = "";

      if ($value->identificador == "Producto") {
        $prvProdQuery = DB::table("in_egr_catalogo_productos_claves AS clav")
        ->join("in_egr_catalogo_productos AS catprod","clav.productoid","=","catprod.id",)
        ->join("eegr_catalogo_proveedores AS catprov","clav.proveedor","=","catprov.id")
        ->join("sos_personas AS prov","catprov.proveedor","=","prov.id")
        ->where("catprod.token_cat_productos",$value->token_articulo)
        ->get();
        foreach ($prvProdQuery as $vKlav) {
          $prov_relacionado_token = $vKlav->token_cat_proveedores;
          $prov_relacionado_rfc = strtoupper($JwtAuth->desencriptar($vKlav->rfc));
          $prov_relacionado_tiene_clave = $vKlav->tiene_clave ? true : false;
          $prov_relacionado_clave = $vKlav->identificador ? $vKlav->identificador : '';
        }
      } else {
        $prvProdQuery = DB::table("in_egr_catalogo_servicios_claves AS clav")
        ->join("in_egr_catalogo_servicios AS catprod","clav.servicio_id","=","catprod.id",)
        ->join("eegr_catalogo_proveedores AS catprov","clav.proveedor","=","catprov.id")
        ->join("sos_personas AS prov","catprov.proveedor","=","prov.id")
        ->where("catprod.token_cat_servicios",$value->token_articulo)
        ->get();
        foreach ($prvProdQuery as $vKlav) {
          $prov_relacionado_token = $vKlav->token_cat_proveedores;
          $prov_relacionado_rfc = strtoupper($JwtAuth->desencriptar($vKlav->rfc));
          $prov_relacionado_tiene_clave = $vKlav->tiene_clave ? true : false;
          $prov_relacionado_clave = $vKlav->asigned_clave != NULL && $vKlav->asigned_clave != '' ? $JwtAuth->desencriptar($vKlav->asigned_clave) : '';
        }
      }

      ++$countList;
      $arraForeach = array(
        "listado" => $countList,
        "token_articulo" => $value->token_articulo,
        "identificador" => $value->identificador,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio_sistema),
        //"sat" => $value->SAT,
        //"clave" => $value->clave,
        "prov_relacionado_token" => $prov_relacionado_token,
        "prov_relacionado_rfc" => $prov_relacionado_rfc,
        "prov_relacionado_tiene_clave" => $prov_relacionado_tiene_clave,
        "prov_relacionado_clave" => $prov_relacionado_clave,
        "prov_relacionado_registrar" => false,
        //"descripcion" => $value->descripcion,
        "concepto" => $conceptoArticulo,
        "imagen" => $imgArticulo,
        "articulo_det_view" => false,
        "subTotalCompra" => "0.00",
        "aplicacion" => "",
        //"precioUnitario" => "",
        //"cantidad" => "1",
        //"descuentoUnidad" => "0.00",
        //impuesto retencion
        "impuesto_entidad" => "",
        "retencion_tipo" => "",
        "retencion_importe" => "",
        "list_retenciones" => [],
        "impuesto" => "",
        "valImpuesto" => "",
        "arrayImpuestos" => [],

        "mx_moneda_code" => "MXN",
        "mx_moneda_decimales" => 2,
        "moneda_code" => "MXN",
        "moneda_decimales" => 2,
        "precioUnitario" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
        "precioUnitarioBackground" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
        "precioUnitarioFormat" => 0,
        "tipoCambio" => "1.00",
        "precioUnitarioConversion" => '0.00',
        "cantidad" => "1",
        "cantidad_registro" => "1",
        "unidadMedida" => "",
        "descuentoUnidad" => "0.00",
        "descuentoUnidadRegistro" => "0.00",
        //retenciones
        "articulo_retenciones" => false,
        "retencion_tipo" => "",
        "retencion_importe" => "0.00",
        "retencion_importeRegistro" => "0.00",
        "retencion_token" => "",
        "list_retenciones" => [],
        "impuesto_entidad" => "",
        //traslados
        "articulo_traslados" => false,
        "traslado_tipo" => "",
        "traslado_importe" => "0.00",
        "traslado_importeRegistro" => "0.00",
        "traslado_token" => "",
        "list_traslados" => [],
        "impuesto_entidad" => "",

        "impuesto" => "",
        "valImpuesto" => "",
        "arrayImpuestos" => [],
        "boolAddRegCompra" => false,
        "totalConImpuesto" => "0.00",
        "totalConImpuestoConversion" => "0.00",

        //Articulo a homologar generales
        "articulo_homologado_view" => false,
        "articulo_homologado_nombre" => "",
        "articulo_homologado_logotipo" => "",
        //Articulo a homologar series
        "articulo_homologado_serie_bool" => $value->num_serie ? true : false,
        "articulo_homologado_serie_view" => false,
        "articulo_homologado_serie_token" => "",
        "articulo_homologado_serie_numero" => "",
        //Articulo a homologar lotes
        "articulo_homologado_lote_bool" => $value->num_lote ? true : false,
        "articulo_homologado_lote_view" => false,
        "articulo_homologado_lote_token" => "",
        "articulo_homologado_lote_numero" => "",
        //Articulo a homologar pedimentos
        "articulo_homologado_pedimento_bool" => $value->importado ? true : false,
        "articulo_homologado_pedimento_view" => false,
        "articulo_homologado_pedimento_token" => "",
        "articulo_homologado_pedimento_numero" => "",
        //Articulo a homologar uso
        "articulo_homologado_view_uso" => false,
        "articulo_homologado_uso" => "",
        //Articulo a homologar uso
        "articulo_homologado_view_activos" => false,
        "articulo_homologado_activoFijo" => "",
        "articulo_homologado_activoDiferido" => "",
        //prorrateos
        "articulo_homologado_prorratea" => false,
        //gastos relacionados
        "articulo_homologado_gastos_rel" => [],
        //periodicidad
        "articulo_homologado_periodicidadPc" => "",
        "articulo_homologado_iteracionPc" => "",
        "articulo_homologado_periodoDetIndPc" => "",
        "articulo_homologado_fechaFinPc" => "",
        //variabilidad de importe
        "articulo_homologado_tipoImporteVi" => "",
        "articulo_homologado_monedaVi" => "",
        "articulo_homologado_monedaDecimalesVi" => "",
        "articulo_homologado_importeMinVi" => "",
        "articulo_homologado_importeMaxVi" => "",
        //desglose
        "activa_desglose" =>  false,
      );
      $arrayArticulos[] = $arraForeach;
    }

    $dataMensaje = array(
      'listaArticulos' => $arrayArticulos,
      'code' => 200,
      'status' => 'success'
    );
    
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaArticulosComprasByProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $proveedor_selected = $request->input('proveedor');
      
      $queryCatActFijo = DB::table('in_egr_catalogo_servicios as catserv')
      ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
      ->join('in_egr_catalogo_servicios_claves as srclave', 'catserv.id', '=', 'srclave.servicio_id')
      ->join('eegr_catalogo_proveedores as catprov', 'srclave.proveedor', '=', 'catprov.id')
      ->where("catserv.folio_sistema","999999998")
      ->where([
        'catserv.status' => TRUE,
        'catprov.token_cat_proveedores' => $proveedor_selected
      ])
      ->whereNull('catserv.administrador')
      ->select([
        'catserv.token_cat_servicios as token_articulo',
        'catserv.fecha_registro_serv as fecha_alta',
        DB::raw("'ActivoFijo' as identificador"),
        'catserv.servicio as concepto',
        DB::raw("'---' as marca"),
        'catserv.clasificacion',
        'gen.folio_genero as genero',
        'catserv.folio_sistema',
        'catserv.post_folio',
        DB::raw("'---' as root_tkn"),
        'srclave.asigned_clave AS claveArticulo',
        DB::raw('FALSE as num_serie'),
        DB::raw('FALSE as num_lote'),
        DB::raw('FALSE as importado'),
        'catserv.precioBase as precioUnitario'
      ]);

      $queryCatActDiferido = DB::table('in_egr_catalogo_servicios as catserv')
      ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
      ->join('in_egr_catalogo_servicios_claves as srclave', 'catserv.id', '=', 'srclave.servicio_id')
      ->join('eegr_catalogo_proveedores as catprov', 'srclave.proveedor', '=', 'catprov.id')
      ->where("catserv.folio_sistema","999999999")
      ->where([
        'catserv.status' => TRUE,
        'catprov.token_cat_proveedores' => $proveedor_selected
      ])
      ->whereNull('catserv.administrador')
      ->select([
        'catserv.token_cat_servicios as token_articulo',
        'catserv.fecha_registro_serv as fecha_alta',
        DB::raw("'ActivoDiferido' as identificador"),
        'catserv.servicio as concepto',
        DB::raw("'---' as marca"),
        'catserv.clasificacion',
        'gen.folio_genero as genero',
        'catserv.folio_sistema',
        'catserv.post_folio',
        DB::raw("'---' as root_tkn"),
        'srclave.asigned_clave AS claveArticulo',
        DB::raw('FALSE as num_serie'),
        DB::raw('FALSE as num_lote'),
        DB::raw('FALSE as importado'),
        'catserv.precioBase as precioUnitario'
      ]);

      /*$queryCatProd = "SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,catprod.producto AS concepto,catprod.marca,catprod.clasificacion,gen.folio_genero AS genero,
        catprod.folio_sistema,catprod.post_folio,emp.root_tkn,prclav.identificador AS claveArticulo,catprod.num_serie,catprod.num_lote,catprod.importado,catprod.costo_aplicable AS precioUnitario FROM in_egr_catalogo_productos AS catprod 
        JOIN sos_ps_genero as gen JOIN eegr_catalogo_proveedores AS catprov JOIN in_egr_catalogo_productos_claves AS prclav JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
        WHERE catprod.genero = gen.id AND catprod.id = prclav.productoid AND prclav.proveedor = catprov.id AND catprov.token_cat_proveedores = ? AND catprod.modulo_mostrador = FALSE AND catprod.status = TRUE
        AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";*/

      $queryCatProd = DB::table('in_egr_catalogo_productos as catprod')
      ->join('sos_ps_genero as gen', 'catprod.genero', '=', 'gen.id')
      ->join('in_egr_catalogo_productos_claves as prclav', 'catprod.id', '=', 'prclav.productoid')
      ->join('eegr_catalogo_proveedores as catprov', 'prclav.proveedor', '=', 'catprov.id')
      ->join('main_empresas as emp', 'catprod.admin_empresa', '=', 'emp.id')
      ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
      ->where([
        'catprov.token_cat_proveedores' => $proveedor_selected,
        'catprod.modulo_mostrador' => FALSE,
        'catprod.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select([
        'catprod.token_cat_productos as token_articulo',
        'catprod.fecha_registro_prod as fecha_alta',
        DB::raw("'Producto' as identificador"),
        'catprod.producto as concepto',
        'catprod.marca',
        'catprod.clasificacion',
        'gen.folio_genero as genero',
        'catprod.folio_sistema',
        'catprod.post_folio',
        'emp.root_tkn',
        'prclav.identificador AS claveArticulo',
        'catprod.num_serie',
        'catprod.num_lote',
        'catprod.importado',
        'catprod.costo_aplicable as precioUnitario'
      ]);

      /*$queryCatServ = "SELECT catserv.token_cat_servicios AS token_articulo,catserv.fecha_registro_serv AS fecha_alta,'Servicio' AS identificador,catserv.servicio AS concepto,'---' AS marca,catserv.clasificacion,gen.folio_genero AS genero,
        catserv.folio_sistema,catserv.post_folio,emp.root_tkn,srclave.asigned_clave AS claveArticulo,FALSE AS num_serie,FALSE AS num_lote,FALSE AS importado,catserv.precioBase AS precioUnitario FROM in_egr_catalogo_servicios AS catserv 
        JOIN sos_ps_genero as gen JOIN eegr_catalogo_proveedores AS catprov JOIN in_egr_catalogo_servicios_claves AS srclave JOIN main_empresas AS emp  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
        WHERE catserv.genero = gen.id AND catserv.id = srclave.servicio_id AND srclave.proveedor = catprov.id AND catprov.token_cat_proveedores = ? AND catserv.proceso = 'c' AND catserv.status = TRUE
        AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";*/

      $queryCatServ = DB::table('in_egr_catalogo_servicios as catserv')
      ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
      ->join('in_egr_catalogo_servicios_claves as srclave', 'catserv.id', '=', 'srclave.servicio_id')
      ->join('eegr_catalogo_proveedores as catprov', 'srclave.proveedor', '=', 'catprov.id')
      ->join('main_empresas as emp', 'catserv.administrador', '=', 'emp.id')
      ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
      ->where([
        'catserv.proceso' => 'c',
        'catserv.status' => TRUE,
        'catprov.token_cat_proveedores' => $proveedor_selected,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select([
        'catserv.token_cat_servicios as token_articulo',
        'catserv.fecha_registro_serv as fecha_alta',
        DB::raw("'Servicio' as identificador"),
        'catserv.servicio as concepto',
        DB::raw("'---' as marca"),
        'catserv.clasificacion',
        'gen.folio_genero as genero',
        'catserv.folio_sistema',
        'catserv.post_folio',
        'emp.root_tkn',
        'srclave.asigned_clave AS claveArticulo',
        DB::raw('FALSE as num_serie'),
        DB::raw('FALSE as num_lote'),
        DB::raw('FALSE as importado'),
        'catserv.precioBase as precioUnitario'
      ]);

      //$combinado = "{$queryCatProd} UNION ALL {$queryCatServ}";
      //$resultQuery = DB::select($combinado, [$proveedor_selected, $empresa, $usuario, $proveedor_selected, $empresa, $usuario]);
      $resultQuery = $queryCatActFijo->unionAll($queryCatActDiferido)->unionAll($queryCatProd)->unionAll($queryCatServ)->get();
      $countList = 0;

      if ($resultQuery->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron articulos, activos o servicios vinculados al proveedor seleccionado'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayArticulos = array();
        foreach ($resultQuery as $value) {
          $conceptoArticulo = $JwtAuth->desencriptar($value->concepto) . ($value->marca != '' && $value->marca != '---' ? ' ' . $JwtAuth->desencriptar($value->marca) : '');

          $imgArticulo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";

          ++$countList;
          $arraForeach = array(
            "listado" => $countList,
            //"sat" => $value->SAT,
            //"clave" => $value->clave,
            //"descripcion" => $value->descripcion,
            "concepto" => $conceptoArticulo,
            "imagen" => $imgArticulo,
            "articulo_det_view" => false,
            "subTotalCompra" => "0.00",
            "aplicacion" => "",
            "mx_moneda_code" => "MXN",
            "mx_moneda_decimales" => 2,
            "moneda_code" => "MXN",
            "moneda_decimales" => 2,
            "precioUnitario" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
            "precioUnitarioBackground" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
            "precioUnitarioFormat" => 0,
            "tipoCambio" => "1.00",
            "precioUnitarioConversion" => '0.00',
            "cantidad" => "1",
            "cantidad_registro" => "1",
            "unidadMedida" => "",
            "descuentoUnidad" => "0.00",
            "descuentoUnidadRegistro" => "0.00",
            //retenciones
            "articulo_retenciones" => false,
            "retencion_tipo" => "",
            "retencion_importe" => "0.00",
            "retencion_importeRegistro" => "0.00",
            "retencion_token" => "",
            "list_retenciones" => [],
            "impuesto_entidad" => "",
            //traslados
            "articulo_traslados" => false,
            "traslado_tipo" => "",
            "traslado_importe" => "0.00",
            "traslado_importeRegistro" => "0.00",
            "traslado_token" => "",
            "list_traslados" => [],
            "impuesto_entidad" => "",

            "impuesto" => "",
            "valImpuesto" => "",
            "arrayImpuestos" => [],
            "boolAddRegCompra" => false,
            "totalConImpuesto" => "0.00",
            "totalConImpuestoConversion" => "0.00",

            //Articulo a homologar generales
            //"articulo_homologado_token" => "",
            "token_articulo" => $value->token_articulo,
            "articulo_homologado_view" => false,
            "articulo_homologado_nombre" => "",
            "articulo_homologado_logotipo" => "",
            //"articulo_homologado_identificador" => "",
            "identificador" => $value->identificador,
            //"articulo_homologado_clasificacion" => "",
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio_sistema),
            //Articulo a homologar series
            "articulo_homologado_serie_bool" => $value->num_serie ? true : false,
            "articulo_homologado_serie_view" => false,
            "articulo_homologado_serie_token" => "",
            "articulo_homologado_serie_numero" => "",
            //Articulo a homologar lotes
            "articulo_homologado_lote_bool" => $value->num_lote ? true : false,
            "articulo_homologado_lote_view" => false,
            "articulo_homologado_lote_token" => "",
            "articulo_homologado_lote_numero" => "",
            //Articulo a homologar pedimentos
            "articulo_homologado_pedimento_bool" => $value->importado ? true : false,
            "articulo_homologado_pedimento_view" => false,
            "articulo_homologado_pedimento_token" => "",
            "articulo_homologado_pedimento_numero" => "",
            //Articulo a homologar uso
            "articulo_homologado_view_uso" => false,
            "articulo_homologado_uso" => "",
            //Articulo a homologar uso
            "articulo_homologado_view_activos" => false,
            "articulo_homologado_activoFijo" => "",
            "articulo_homologado_activoDiferido" => "",
            //prorrateos
            "articulo_homologado_prorratea" => false,
            //gastos relacionados
            "articulo_homologado_gastos_rel" => [],
            //periodicidad
            "articulo_homologado_periodicidadPc" => "",
            "articulo_homologado_iteracionPc" => "",
            "articulo_homologado_periodoDetIndPc" => "",
            "articulo_homologado_fechaFinPc" => "",
            //variabilidad de importe
            "articulo_homologado_tipoImporteVi" => "",
            "articulo_homologado_monedaVi" => "",
            "articulo_homologado_monedaDecimalesVi" => "",
            "articulo_homologado_importeMinVi" => "",
            "articulo_homologado_importeMaxVi" => "",
            //desglose
            "activa_desglose" =>  false,
          );
          $arrayArticulos[] = $arraForeach;
        }
        $dataMensaje = array(
          'listaArticulos' => $arrayArticulos,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaGeneralServiciosCompras(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $queryCatServ = DB::table('in_egr_catalogo_servicios as catserv')
    ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
    ->join('main_empresas as emp', 'catserv.administrador', '=', 'emp.id')
    ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
    ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
    ->where('catserv.proceso', 'c')
    ->where('catserv.status', TRUE)
    ->where('emp.empresa_token', $empresa)
    ->where('users.usuario_token', $usuario)
    ->select([
      'catserv.token_cat_servicios as token_articulo',
      'catserv.fecha_registro_serv as fecha_alta',
      DB::raw("'Servicio' as identificador"),
      'catserv.servicio as concepto',
      DB::raw("'---' as marca"),
      'catserv.clasificacion',
      'gen.folio_genero as genero',
      'catserv.folio_sistema',
      'catserv.post_folio',
      'emp.root_tkn',
      DB::raw('FALSE as num_serie'),
      DB::raw('FALSE as num_lote'),
      DB::raw('FALSE as importado'),
      'catserv.precioBase as precioUnitario'
    ])
    ->get();
    $countList = 0;

    //$combinado = "{$queryCatProd} UNION {$queryCatServ}";
    //$resultQuery = DB::select($combinado, [$empresa, $usuario, $empresa, $usuario]);

    foreach ($queryCatServ as $value) {
      $conceptoArticulo = $JwtAuth->desencriptar($value->concepto) . ($value->marca != '' && $value->marca != '---' ? ' ' . $JwtAuth->desencriptar($value->marca) : '');
      $imgArticulo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";

      $prov_relacionado_token = "";
      $prov_relacionado_rfc = "";
      $prov_relacionado_tiene_clave = false;
      $prov_relacionado_clave = "";
      
      $prvProdQuery = DB::table("in_egr_catalogo_servicios_claves AS clav")
      ->join("in_egr_catalogo_servicios AS catprod","clav.servicio_id","=","catprod.id",)
      ->join("eegr_catalogo_proveedores AS catprov","clav.proveedor","=","catprov.id")
      ->join("sos_personas AS prov","catprov.proveedor","=","prov.id")
      ->where("catprod.token_cat_servicios",$value->token_articulo)
      ->get();
      foreach ($prvProdQuery as $vKlav) {
        $prov_relacionado_token = $vKlav->token_cat_proveedores;
        $prov_relacionado_rfc = strtoupper($JwtAuth->desencriptar($vKlav->rfc));
        $prov_relacionado_tiene_clave = $vKlav->tiene_clave ? true : false;
        $prov_relacionado_clave = $vKlav->asigned_clave != NULL && $vKlav->asigned_clave != '' ? $JwtAuth->desencriptar($vKlav->asigned_clave) : '';
      }

      ++$countList;
      $arraForeach = array(
        "listado" => $countList,
        "token_articulo" => $value->token_articulo,
        "identificador" => $value->identificador,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio_sistema),
        //"sat" => $value->SAT,
        //"clave" => $value->clave,
        "prov_relacionado_token" => $prov_relacionado_token,
        "prov_relacionado_rfc" => $prov_relacionado_rfc,
        "prov_relacionado_tiene_clave" => $prov_relacionado_tiene_clave,
        "prov_relacionado_clave" => $prov_relacionado_clave,
        "prov_relacionado_registrar" => false,
        //"descripcion" => $value->descripcion,
        "concepto" => $conceptoArticulo,
        "imagen" => $imgArticulo,
        "articulo_det_view" => false,
        "subTotalCompra" => "0.00",
        "aplicacion" => "",
        //"precioUnitario" => "",
        //"cantidad" => "1",
        //"descuentoUnidad" => "0.00",
        //impuesto retencion
        "impuesto_entidad" => "",
        "retencion_tipo" => "",
        "retencion_importe" => "",
        "list_retenciones" => [],
        "impuesto" => "",
        "valImpuesto" => "",
        "arrayImpuestos" => [],

        "mx_moneda_code" => "MXN",
        "mx_moneda_decimales" => 2,
        "moneda_code" => "MXN",
        "moneda_decimales" => 2,
        "precioUnitario" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
        "precioUnitarioBackground" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
        "precioUnitarioFormat" => 0,
        "tipoCambio" => "1.00",
        "precioUnitarioConversion" => '0.00',
        "cantidad" => "1",
        "cantidad_registro" => "1",
        "unidadMedida" => "",
        "descuentoUnidad" => "0.00",
        "descuentoUnidadRegistro" => "0.00",
        //retenciones
        "articulo_retenciones" => false,
        "retencion_tipo" => "",
        "retencion_importe" => "0.00",
        "retencion_importeRegistro" => "0.00",
        "retencion_token" => "",
        "list_retenciones" => [],
        "impuesto_entidad" => "",
        //traslados
        "articulo_traslados" => false,
        "traslado_tipo" => "",
        "traslado_importe" => "0.00",
        "traslado_importeRegistro" => "0.00",
        "traslado_token" => "",
        "list_traslados" => [],
        "impuesto_entidad" => "",

        "impuesto" => "",
        "valImpuesto" => "",
        "arrayImpuestos" => [],
        "boolAddRegCompra" => false,
        "totalConImpuesto" => "0.00",
        "totalConImpuestoConversion" => "0.00",

        //Articulo a homologar generales
        "articulo_homologado_view" => false,
        "articulo_homologado_nombre" => "",
        "articulo_homologado_logotipo" => "",
        //Articulo a homologar series
        "articulo_homologado_serie_bool" => $value->num_serie ? true : false,
        "articulo_homologado_serie_view" => false,
        "articulo_homologado_serie_token" => "",
        "articulo_homologado_serie_numero" => "",
        //Articulo a homologar lotes
        "articulo_homologado_lote_bool" => $value->num_lote ? true : false,
        "articulo_homologado_lote_view" => false,
        "articulo_homologado_lote_token" => "",
        "articulo_homologado_lote_numero" => "",
        //Articulo a homologar pedimentos
        "articulo_homologado_pedimento_bool" => $value->importado ? true : false,
        "articulo_homologado_pedimento_view" => false,
        "articulo_homologado_pedimento_token" => "",
        "articulo_homologado_pedimento_numero" => "",
        //Articulo a homologar uso
        "articulo_homologado_view_uso" => false,
        "articulo_homologado_uso" => "",
        //Articulo a homologar uso
        "articulo_homologado_view_activos" => false,
        "articulo_homologado_activoFijo" => "",
        "articulo_homologado_activoDiferido" => "",
        //prorrateos
        "articulo_homologado_prorratea" => false,
        //gastos relacionados
        "articulo_homologado_gastos_rel" => [],
        //periodicidad
        "articulo_homologado_periodicidadPc" => "",
        "articulo_homologado_iteracionPc" => "",
        "articulo_homologado_periodoDetIndPc" => "",
        "articulo_homologado_fechaFinPc" => "",
        //variabilidad de importe
        "articulo_homologado_tipoImporteVi" => "",
        "articulo_homologado_monedaVi" => "",
        "articulo_homologado_monedaDecimalesVi" => "",
        "articulo_homologado_importeMinVi" => "",
        "articulo_homologado_importeMaxVi" => "",
        //desglose
        "activa_desglose" =>  false,
      );
      $arrayArticulos[] = $arraForeach;
    }

    $dataMensaje = array(
      'listaArticulos' => $arrayArticulos,
      'code' => 200,
      'status' => 'success'
    );

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaServiciosComprasByProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'proveedor' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $proveedor_selected = $request->input('proveedor');
      
      $queryCatServ = DB::table('in_egr_catalogo_servicios as catserv')
      ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
      ->join('in_egr_catalogo_servicios_claves as srclave', 'catserv.id', '=', 'srclave.servicio_id')
      ->join('eegr_catalogo_proveedores as catprov', 'srclave.proveedor', '=', 'catprov.id')
      ->join('main_empresas as emp', 'catserv.administrador', '=', 'emp.id')
      ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
      ->where('catserv.proceso', 'c')
      ->where('catserv.status', TRUE)
      ->where('catprov.token_cat_proveedores', $proveedor_selected)
      ->where('emp.empresa_token', $empresa)
      ->where('users.usuario_token', $usuario)
      ->select([
        'catserv.token_cat_servicios as token_articulo',
        'catserv.fecha_registro_serv as fecha_alta',
        DB::raw("'Servicio' as identificador"),
        'catserv.servicio as concepto',
        DB::raw("'---' as marca"),
        'catserv.clasificacion',
        'gen.folio_genero as genero',
        'catserv.folio_sistema',
        'catserv.post_folio',
        'emp.root_tkn',
        'srclave.asigned_clave AS claveArticulo',
        DB::raw('FALSE as num_serie'),
        DB::raw('FALSE as num_lote'),
        DB::raw('FALSE as importado'),
        'catserv.precioBase as precioUnitario'
      ])
      ->get();

      if ($queryCatServ->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron servicios registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayArticulos = array();
        $countList = 0;
        foreach ($queryCatServ as $value) {
          $conceptoArticulo = $JwtAuth->desencriptar($value->concepto) . ($value->marca != '' && $value->marca != '---' ? ' ' . $JwtAuth->desencriptar($value->marca) : '');

          $imgArticulo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";

          ++$countList;
          $arraForeach = array(
            "listado" => $countList,
            //"sat" => $value->SAT,
            //"clave" => $value->clave,
            //"descripcion" => $value->descripcion,
            "concepto" => $conceptoArticulo,
            "imagen" => $imgArticulo,
            "articulo_det_view" => false,
            "subTotalCompra" => "0.00",
            "aplicacion" => "",
            "mx_moneda_code" => "MXN",
            "mx_moneda_decimales" => 2,
            "moneda_code" => "MXN",
            "moneda_decimales" => 2,
            "precioUnitario" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
            "precioUnitarioBackground" => $value->precioUnitario != NULL && $value->precioUnitario != "" ? $value->precioUnitario : '0.00',
            "precioUnitarioFormat" => 0,
            "tipoCambio" => "1.00",
            "precioUnitarioConversion" => '0.00',
            "cantidad" => "1",
            "cantidad_registro" => "1",
            "unidadMedida" => "",
            "descuentoUnidad" => "0.00",
            "descuentoUnidadRegistro" => "0.00",
            //retenciones
            "articulo_retenciones" => false,
            "retencion_tipo" => "",
            "retencion_importe" => "0.00",
            "retencion_importeRegistro" => "0.00",
            "retencion_token" => "",
            "list_retenciones" => [],
            "impuesto_entidad" => "",
            //traslados
            "articulo_traslados" => false,
            "traslado_tipo" => "",
            "traslado_importe" => "0.00",
            "traslado_importeRegistro" => "0.00",
            "traslado_token" => "",
            "list_traslados" => [],
            "impuesto_entidad" => "",

            "impuesto" => "",
            "valImpuesto" => "",
            "arrayImpuestos" => [],
            "boolAddRegCompra" => false,
            "totalConImpuesto" => "0.00",
            "totalConImpuestoConversion" => "0.00",

            //Articulo a homologar generales
            //"articulo_homologado_token" => "",
            "token_articulo" => $value->token_articulo,
            "articulo_homologado_view" => false,
            "articulo_homologado_nombre" => "",
            "articulo_homologado_logotipo" => "",
            //"articulo_homologado_identificador" => "",
            "identificador" => $value->identificador,
            //"articulo_homologado_clasificacion" => "",
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio_sistema),
            //Articulo a homologar series
            "articulo_homologado_serie_bool" => $value->num_serie ? true : false,
            "articulo_homologado_serie_view" => false,
            "articulo_homologado_serie_token" => "",
            "articulo_homologado_serie_numero" => "",
            //Articulo a homologar lotes
            "articulo_homologado_lote_bool" => $value->num_lote ? true : false,
            "articulo_homologado_lote_view" => false,
            "articulo_homologado_lote_token" => "",
            "articulo_homologado_lote_numero" => "",
            //Articulo a homologar pedimentos
            "articulo_homologado_pedimento_bool" => $value->importado ? true : false,
            "articulo_homologado_pedimento_view" => false,
            "articulo_homologado_pedimento_token" => "",
            "articulo_homologado_pedimento_numero" => "",
            //Articulo a homologar uso
            "articulo_homologado_view_uso" => false,
            "articulo_homologado_uso" => "",
            //Articulo a homologar uso
            "articulo_homologado_view_activos" => false,
            "articulo_homologado_activoFijo" => "",
            "articulo_homologado_activoDiferido" => "",
            //prorrateos
            "articulo_homologado_prorratea" => false,
            //gastos relacionados
            "articulo_homologado_gastos_rel" => [],
            //periodicidad
            "articulo_homologado_periodicidadPc" => "",
            "articulo_homologado_iteracionPc" => "",
            "articulo_homologado_periodoDetIndPc" => "",
            "articulo_homologado_fechaFinPc" => "",
            //variabilidad de importe
            "articulo_homologado_tipoImporteVi" => "",
            "articulo_homologado_monedaVi" => "",
            "articulo_homologado_monedaDecimalesVi" => "",
            "articulo_homologado_importeMinVi" => "",
            "articulo_homologado_importeMaxVi" => "",
            //desglose
            "activa_desglose" =>  false,
          );
          $arrayArticulos[] = $arraForeach;
        }
        $dataMensaje = array(
          'listaArticulos' => $arrayArticulos,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraArticulosClavesProv(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'tokenProveedor' => 'required|string',
      'token_articulo' => 'required|string',
      'identificador' => 'required|string',
      'prov_relacionado_registrar' => 'required|boolean',
      'prov_relacionado_tiene_clave' => 'required|boolean',
      'prov_relacionado_clave' => 'nullable|string',
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
      $tokenProveedor = $request->input('tokenProveedor');
      $token_articulo = $request->input('token_articulo');
      $identificador = $request->input('identificador');
      $prov_relacionado_registrar = $request->input('prov_relacionado_registrar');
      $prov_relacionado_tiene_clave = $request->input('prov_relacionado_tiene_clave');
      $prov_relacionado_clave = $request->input('prov_relacionado_clave');

      $valida_proveedor = isset($tokenProveedor) && !empty($tokenProveedor);
      $valida_token_articulo = isset($token_articulo) && !empty($token_articulo);
      $valida_identificador = isset($identificador) && !empty($identificador);
      $valida_prov_relacionado_registrar = isset($prov_relacionado_registrar) && is_bool($prov_relacionado_registrar);
      $valida_prov_relacionado_tiene_clave = isset($prov_relacionado_tiene_clave) && is_bool($prov_relacionado_tiene_clave);
      $valida_prov_relacionado_clave = isset($prov_relacionado_clave) && !empty($prov_relacionado_clave);

      if ($valida_proveedor && $valida_token_articulo && $valida_identificador && $valida_prov_relacionado_registrar && $valida_prov_relacionado_tiene_clave &&
        (!$prov_relacionado_tiene_clave || ($prov_relacionado_tiene_clave && $valida_prov_relacionado_clave))) {
        
        $obtenProv = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $tokenProveedor)->value("id");
        $prv_tiene_clave = $prov_relacionado_tiene_clave ? TRUE : FALSE;
        $tokenClabeProdProv = $JwtAuth->encriptarToken(time().$obtenProv . $empresa . $usuario);

        if ($identificador == "Producto") {
          $prv_clave = $prov_relacionado_tiene_clave ? $prov_relacionado_clave : NULL;
          $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$token_articulo)->value("id");
          $insertKlaves = DB::table('in_egr_catalogo_productos_claves')
          ->insert(array(
            "token_producto_claves" => $tokenClabeProdProv,
            "productoid" => $obtenProducto,
            "proveedor" => $obtenProv,
            "cliente" => NULL,
            "tiene_clave" => $prv_tiene_clave,
            "identificador" => $prv_clave,
          ));
        } else {
          $prv_clave = $prov_relacionado_tiene_clave ? $JwtAuth->encriptar($prov_relacionado_clave) : NULL;
          $obtenProducto = DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$token_articulo)->value("id");
          $insertKlaves = DB::table('in_egr_catalogo_servicios_claves')
          ->insert(array(
            "token_serv_claves" => $tokenClabeProdProv,
            "servicio_id" => $obtenProducto,
            "proveedor" => $obtenProv,
            "cliente" => NULL,
            "tiene_clave" => $prv_tiene_clave,
            "asigned_clave" => $prv_clave,
          ));
        }
        
        if ($insertKlaves) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "relacion con proveedor ha sido registrada",
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => "relacion con proveedor no registrada",
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Hay errores en la información obtenida, por favor revise su información o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function consultArticuloCompras(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_articulo' => 'required|string'
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
      $token_articulo = $request->input('token_articulo');
      
      $catProdServ = DB::select(
        "SELECT 
                  CASE WHEN ? IN (
                      SELECT token_cat_productos FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprod.status = TRUE 
                      AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id
                      AND users.usuario_token = ?) THEN 'Producto' 
                  WHEN ? IN ( 
                      SELECT token_cat_servicios FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catserv.proceso = TRUE 
                      AND catserv.status = TRUE AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token = ?) THEN 'Servicio' END AS identificador",
        [
          $token_articulo,
          $empresa,
          $usuario,
          $token_articulo,
          $empresa,
          $usuario
        ]
      );
      foreach ($catProdServ as $value) {
        $dataMensaje = array(
          'identificador' => $value->identificador,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function registrarCompraValidaConceptos($cfdi_conceptos,$moneda_decimales,$JwtAuth){
    $detalleErrores = "";
    foreach ($cfdi_conceptos as $vDet) {
      $tokenArticulo = $vDet['articulo_homologado_token'];
      $identificador = $vDet['articulo_homologado_identificador'];
      $concepto = $vDet['Descripcion'];
      $precioUnitario = $vDet['ValorUnitario'];
      $cantidad = $vDet['Cantidad'];
      //return response()->json(['status' => 'error','code' => 200,'message' => $cantidad]);
      $descuentoXUni = $vDet['Descuento'];
      $iva = $vDet['articulo_homologado_iva'];
      $retenciones = $vDet['retenciones'];
      $traslados = $vDet['traslados'];
      $usoArticulo = $vDet['articulo_homologado_uso'];
      $efectoFiscalArticulo = $vDet['articulo_homologado_efecto_fiscal'];
      $activoFijo = $vDet['articulo_homologado_activoFijo'];
      $activoIntangible = $vDet['articulo_homologado_activoDiferido'];
      $prorratea = $vDet['articulo_homologado_prorratea'];

      $importe = $JwtAuth->rellenaImportesCompras($vDet['Importe']);
      $validateActivos = false;
      $validatePeriodicidad = false;
      $validateDescuentos = false;
      $validateDecimalesMoneda = false;
      $validateForImpuRetenciones = false;
      $validateForImpuTraslados = false;

      $vItem_tokenArticulo = isset($tokenArticulo) && !empty($tokenArticulo);
      $vItem_identificador = isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador);//$JwtAuth->filtroFecha()
      $vItem_precioUnitario = isset($precioUnitario) && !empty($precioUnitario) && preg_match($JwtAuth->filtroCostoPrecio(), $precioUnitario);
      $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($JwtAuth->filtroCostoPrecio(), $cantidad);
      //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
      $vItem_usoArticulo = isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo);
      $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
      //$vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
      $vItem_importe = isset($importe) && !empty($importe) && preg_match($JwtAuth->filtroCostoPrecio(), $importe);

      if ($vItem_tokenArticulo && $vItem_identificador && $vItem_precioUnitario && $vItem_cantidad && $vItem_usoArticulo /*&& $vItem_periodicidadPc*/ && $vItem_importe) {
        if (isset($descuentoXUni) && !empty($descuentoXUni)) {
          if ($descuentoXUni != '---') {
            if (preg_match($JwtAuth->filtroCostoPrecio(), $descuentoXUni)) {
              $strPosdescuentoXUni = strpos($descuentoXUni, '.');
              if ($strPosdescuentoXUni !== FALSE) {
                $expdescuentoXUni = explode('.', $descuentoXUni);
                if ($moneda_decimales == strlen($expdescuentoXUni[1])) {
                  $validateDescuentos = true;
                } else {
                  $validateDescuentos = false;
                  $detalleErrores = 'La cantidad de decimales del descuento no coincide con los decimales que soporta la moneda seleccionada';
                }
              } else {
                $validateDescuentos = false;
                $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada';
              }
            } else {
              $validateDescuentos = false;
              $detalleErrores = 'Descuento invalido';
            }
          } else {
            $validateDescuentos = true;
          }
        } else {
          $validateDescuentos = false;
          $detalleErrores = 'La cantidad de descuento es invalida o inexistente';
        }

        if ($moneda_decimales != 0) {
          $strPosPrecioUnit = strpos($precioUnitario, '.');
          $strPosimporte = strpos($importe, '.');

          if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
            $expUnitPrecio = explode('.', $precioUnitario);
            $expimporte = explode('.', $importe);

            if ((strlen($expUnitPrecio[1]) == 6 || strlen($expUnitPrecio[1]) == $moneda_decimales) &&
              (strlen($expimporte[1]) == 6 || strlen($expimporte[1]) == $moneda_decimales)
            ) {
              $validateDecimalesMoneda = true;
            } else {
              $validateDecimalesMoneda = false;
              $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada';
            }
          } else {
            $validateDecimalesMoneda = false;
            $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada';
          }
        }

        if ($moneda_decimales == 0) {
          $strPosPrecioUnit = strpos($precioUnitario, '.');
          $strPosimporte = strpos($importe, '.');
          if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
            $validateDecimalesMoneda = false;
            $detalleErrores = 'El precio unitario del producto/servicio no tiene decimales';
          } else {
            $validateDecimalesMoneda = true;
          }
        }

        if ($usoArticulo == 'activo_fijo') {
          if (isset($activoFijo) && !empty($activoFijo) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoFijo)) {
            $validateActivos = true;
          } else {
            $validateActivos = false;
            $detalleErrores = 'El activo del producto/servicio '.$concepto.' es invalido o inexistente';
            break;
          }
        } else if ($usoArticulo == 'activo_diferido') {
          if (isset($activoIntangible) && !empty($activoIntangible) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoIntangible)) {
            $validateActivos = true;
          } else {
            $validateActivos = false;
            $detalleErrores = 'El descuento del producto/servicio '.$concepto.' es invalido o inexistente';
            break;
          }
        } else {
          $validateActivos = true;
        }

        if (count($retenciones) != 0) {
          $countValidateRetencionesConcept = 0;
          for ($t = 0; $t < count($retenciones); $t++) {
            $base = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Base"]);
            $explodeBase = explode('.', $base);
            $impuesto = $retenciones[$t]["Impuesto"];
            $tipoFactor = $retenciones[$t]["TipoFactor"];
            $TasaOCuota = $retenciones[$t]["TasaOCuota"] ?? null;
            $importe = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]);
            $importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]) : "0.00";
            //return response()->json(['message' => $retenciones[$t]["Importe"],'codigo' => 200,'status' => 'error']);
            $explodeImporte = explode('.', $importe);

            $OKRetImpuesto = isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3;
            $OKRetTipoFactor = isset($tipoFactor) && !empty($tipoFactor);
            $OKRetTasaOCuota = ($tipoFactor === "Exento") ? true : (isset($TasaOCuota) && !empty($TasaOCuota));
            $OKRetImporte = isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales);
            if ($OKRetImpuesto && $OKRetTipoFactor && $OKRetTasaOCuota && $OKRetImporte) {
              if (isset($base)) {
                if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                  ++$countValidateRetencionesConcept;
                } else {
                  $detalleErrores = 'Base de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';
                  break;
                }
              } else {
                ++$countValidateRetencionesConcept;
              }
              //return response()->json(['message' => $base,'codigo' => 200,'status' => 'error']);
            } else {
              if (!$OKRetImpuesto) {
                $detalleErrores = 'Impuesto de retención del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)';
                break;
              }
              if (!$OKRetTipoFactor) {
                $detalleErrores = 'TipoFactor de retención del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKRetTasaOCuota) {
                $detalleErrores = 'TasaOCuota de retención del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKRetImporte) {
                $detalleErrores = 'Importe de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';
                break;
              }
            }
          }

          if ($countValidateRetencionesConcept == count($retenciones)) {
            $validateForImpuRetenciones = true;
          }
        } else {
          $validateForImpuRetenciones = true;
        }

        if (count($traslados) != 0) {
          $countValidateTrasladosConcept = 0;
          for ($t = 0; $t < count($traslados); $t++) {
            $base = $JwtAuth->rellenaImportesCompras($traslados[$t]["Base"]);
            $explodeBase = explode('.', $base);
            $impuesto = $traslados[$t]["Impuesto"];
            //return response()->json(['message' => $impuesto,'codigo' => 200,'status' => 'error']);
            $tipoFactor = $traslados[$t]["TipoFactor"];
            $TasaOCuota = $traslados[$t]["TasaOCuota"] ?? null;
            $importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]) : "0.00";
            $explodeImporte = explode('.', $importe);

            $OKTrasImpuesto = isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3;
            $OKTrasTipoFactor = isset($tipoFactor) && !empty($tipoFactor);
            $OKTrasTasaOCuota = ($tipoFactor === "Exento") ? true : (isset($TasaOCuota) && !empty($TasaOCuota));
            $OKTrasImporte = isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales);
            if ($OKTrasImpuesto && $OKTrasTipoFactor && $OKTrasTasaOCuota && $OKTrasImporte) {
              if (isset($base)) {
                //return response()->json(['message' => strlen($explodeBase[1]).' == '.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                  ++$countValidateTrasladosConcept;
                } else {
                  $detalleErrores = 'Base de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';
                  break;
                }
              } else {
                ++$countValidateTrasladosConcept;
              }
            } else {
              if (!$OKTrasImpuesto) {
                $detalleErrores = 'Impuesto de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)';
                break;
              }
              if (!$OKTrasTipoFactor) {
                $detalleErrores = 'TipoFactor de traslado del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKTrasTasaOCuota) {
                $detalleErrores = 'TasaOCuota de traslado del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKTrasImporte) {
                $detalleErrores = 'Importe de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida (' . $moneda_decimales . ')';
                break;
              }
            }
          }
          if ($countValidateTrasladosConcept == count($traslados)) {
            $validateForImpuTraslados = true;
          }
        } else {
          $validateForImpuTraslados = true;
        }
      } else {
        if (!$vItem_tokenArticulo) {$detalleErrores = 'producto/servicio '.$concepto.' invalidado';}
        if (!$vItem_identificador) {$detalleErrores = 'identificador del producto/servicio '.$concepto.' es incorrecto o inexistente';}
        if (!$vItem_precioUnitario) {$detalleErrores = 'El precio unitario del producto/servicio '.$concepto.' es invalido o inexistente';}
        if (!$vItem_cantidad) {$detalleErrores = 'La cantidad del producto/servicio '.$concepto.' es invalida o inexistente';}
        if (!$vItem_usoArticulo) {$detalleErrores = 'El uso del producto/servicio '.$concepto.' es invalido o inexistente';}
        if (!$vItem_importe) {$detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';}
        break;
      }
    }
    return $detalleErrores;
  }

  private function registraAnticipoCompra($JwtAuth,$token_proveedor,$emp_id,$usuario,$user_id,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$cfdi_comprobante_tipo_de_cambio,$cfdi_comprobante_moneda,$orden_de_pago_vinculada){
    $ident_deudor = DB::table("fnzs_catalogo_deudores AS catdeu")
    ->join("eegr_catalogo_proveedores AS catprov", "catdeu.proveedor_deudor", "=", "catprov.id")
    ->where("catprov.token_cat_proveedores",$token_proveedor)->value("catdeu.id");
  
    $id_pago_realizado = DB::table("fnzs_pagos_pago AS pag")
    ->join("fnzs_catalogo_deudores AS catdeu", "pag.vinc_deudor", "=", "catdeu.proveedor_deudor")
    ->join("eegr_catalogo_proveedores AS catprov", "catdeu.proveedor_deudor", "=", "catprov.id")
    ->where("catprov.token_cat_proveedores",$token_proveedor)
    ->where("pag.concepto",$JwtAuth->encriptar("Pago por concepto de anticipo")) 
    ->orderBy("pag.fecha_sistema", "asc")
    ->select("pag.id")
    ->first();
  
    $folioMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas AS emp 
      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.id = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$emp_id, $usuario]
    );
  
    $tokenMov = $JwtAuth->encriptarToken($anticipo_aplicado.$compra_observaciones.time());
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
        "deu_monto_mov" => $anticipo_aplicado,
        "deu_observaciones_mov" => $JwtAuth->encriptar($compra_observaciones),
        "deu_tipo_cambio" => $cfdi_comprobante_tipo_de_cambio,
        "deu_mov_moneda" => $cfdi_comprobante_moneda,
        "vinc_deudor" => $ident_deudor,
        "deu_personal_mov" => $user_id,
        "deu_mov_autorizado" => TRUE,
        "deu_fecha_mov_auth" => time(),
        "deu_personal_autoriza" => $user_id,
        "deu_empresa" => $emp_id,
        "deu_status_mov" => TRUE,
      )
    );
    $id_mov_realizado = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenMov)->value("id");
    
    $insertMovVincPagosOrden = DB::table("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo")
    ->insert(array("mov_realizado" => $id_mov_realizado,"orden_pago" => $orden_de_pago_vinculada));
  
    $insertPagoVinc = DB::table("fnzs_catalogo_deudores_movimientos_pagos_vinculados")
    ->insert(array(
      "mov_realizado" => $id_mov_realizado,
      "pago_vinculado" => $id_pago_realizado,
      "mov_pago_monto" => $anticipo_aplicado
    ));

    if (!$insertPagoMon || !$insertMovVincPagosOrden || !$insertPagoVinc) {
      throw new \Exception("No se pudo registrar el movimiento en a deudores.");
    }
  }

  private function registraArticuloCompra($JwtAuth,$detBuy,$emp_id,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio){
    $validUpdtProd = false;
    $validUpdtServ = false;
    $NoIdentificacion = $detBuy['NoIdentificacion'];
    $ObjetoImp = $detBuy['ObjetoImp'];
    $ClaveProdServ = $detBuy['ClaveProdServ'];
    $tokenArticulo = $detBuy['articulo_homologado_token'];
    $identificador = $detBuy['articulo_homologado_identificador'];
    $concepto = $detBuy['Descripcion'];
    $precioUnitario = $detBuy['ValorUnitario'];
    $cantidad = $detBuy['Cantidad'];
    $ClaveUnidad = $detBuy['ClaveUnidad'];
    $Unidad = $detBuy['Unidad'];
    $descuentoXUni = $detBuy['Descuento'];
    $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
    $iva = $detBuy['articulo_homologado_iva'];
    $retenciones = $detBuy['retenciones'];
    $TotalRetenciones = $detBuy['TotalRetenciones'];
    $traslados = $detBuy['traslados'];
    $TotalTraslados = $detBuy['TotalTraslados'];
    $Subtotal = $detBuy['Subtotal'];
    $usoArticulo = $detBuy['articulo_homologado_uso'];
    $efectoFiscalArticulo = $detBuy['articulo_homologado_efecto_fiscal'];
    $alm_serie = $detBuy['articulo_homologado_serie_token'];
    $alm_lote = $detBuy['articulo_homologado_lote_token'];
    $alm_pedimento = $detBuy['articulo_homologado_pedimento_token'];
    $activoFijo = $detBuy['articulo_homologado_activoFijo'];
    $activoIntangible = $detBuy['articulo_homologado_activoDiferido'];
    $prorratea = $detBuy['articulo_homologado_prorratea'];
    
    $importe = $detBuy['Importe'];
    $token_unidad_medida = $detBuy['Unidad'];
  
    //$token_producto = '';
    //$token_servicio = '';
    //$activos_fijos = '';
    //$activos_intangibles = '';
    $pedimento_aduanal = NULL;
    $boolprorratea = FALSE;
  
    $catProdServ = DB::table(DB::raw('(SELECT
        CASE
            WHEN ? IN (SELECT token_cat_productos FROM in_egr_catalogo_productos WHERE status = TRUE AND admin_empresa = ?) THEN "Producto"
            WHEN ? IN (SELECT token_cat_servicios FROM in_egr_catalogo_servicios WHERE status = TRUE AND administrador = ?) THEN "Servicio"
        END AS identificador) AS subconsulta'))
    ->setBindings([$tokenArticulo, $emp_id, $tokenArticulo, $emp_id])
    ->value("identificador");

    // 3. Obtención de IDs reales
    $id_producto = ($catProdServ == 'Producto') ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $tokenArticulo)->value("id") : NULL;
    $id_servicio = ($catProdServ != 'Producto') ? DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $tokenArticulo)->value("id") : NULL;
    $id_activo_fijo = ($usoArticulo == 'activo_fijo') ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos", $detBuy['articulo_homologado_activoFijo'])->value("id") : NULL;
    $id_activo_intangible = ($usoArticulo == 'activo_diferido') ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang", $detBuy['articulo_homologado_activoDiferido'])->value("id") : NULL;
  
    $tokenDetalleCompra = $JwtAuth->encriptarToken(time().$id_producto.$id_servicio.$tokenArticulo.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.
      $alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$importe);
  
    $total_retenciones = collect($retenciones)->sum(function($r) { return ($r['TipoFactor'] != 'Exento') ? ($r['Importe'] ?? 0) : 0; });
    $total_traslados = collect($traslados)->sum(function($t) { return ($t['TipoFactor'] != 'Exento') ? ($t['Importe'] ?? 0) : 0; });
  
    $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
    
    $id_compra_detalle = DB::table('eegr_compras_detalle')
    ->insertGetId([
      'token_detcompra'               => $tokenDetalleCompra,
      'numero_compra'                 => $obtenCompra,
      'concepto_cfdi'                 => $JwtAuth->encriptar($concepto),
      'producto'                      => $id_producto,
      'servicio'                      => $id_servicio,
      'moneda_detalle_compra'         => $cfdi_comprobante_moneda,
      'tipo_de_cambio_detalle_compra' => $cfdi_comprobante_tipo_de_cambio,
      'precio_unitario'               => $precioUnitario,
      'cantidad'                      => $cantidad,
      'unidad_medida'                 => $token_unidad_medida,
      'descuento'                     => $total_descuento,
      'retenciones_total'             => $total_retenciones,
      //'retencion_homologada'          => $rete_homologada,
      'traslados_total'               => $total_traslados,
      //'traslado_homologado'           => $tras_homologado,
      'destino'                       => $usoArticulo,
      'efecto_fiscal'                 => $vItem_efectoFiscalArticulo ? $efectoFiscalArticulo : NULL,
      'activo_fijo'                   => $id_activo_fijo,
      'activo_intangible'             => $id_activo_intangible,
      'prorrateo'                     => $prorratea ? TRUE : FALSE,
      'empresa'                       => $emp_id
    ]);
    
    if (!$id_compra_detalle) {
      throw new \Exception("No se pudo generar el registro de detalle para: " . $detBuy['Descripcion']);
    }

    $this->procesarImpuestos($id_compra_detalle, $retenciones ?? [], 'rete', $JwtAuth);
    $this->procesarImpuestos($id_compra_detalle, $traslados ?? [], 'tras', $JwtAuth);
    
    return $id_compra_detalle;
  }

  private function procesarImpuestos($idDetalle, $impuestos, $tipo, $JwtAuth) {
    if (empty($impuestos)) return;
    $dataToInsert = [];
    foreach ($impuestos as $imp) {
      $impRelacionado = $imp["impuesto_relacionado"] ?? "";
      $idHomonimo = null;

      // 1. Mejora: Validación mínima antes de consultar
      if (!empty($impRelacionado)) {
        $idHomonimo = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impRelacionado)->value("id");  
        // Opcional: Si el token existe pero no halló ID, podrías lanzar excepción
        if (!$idHomonimo) {
          throw new \Exception("El impuesto homologado con token {$impRelacionado} no existe.");
        }
      }

      // 2. Preparar el array para inserción masiva (Bulk Insert)
      $dataToInsert[] = [
        "token_imp_det_buy"    => $JwtAuth->encriptarToken(time() . uniqid() . $idDetalle),
        "detalle_compra"       => $idDetalle, 
        "retencion_traslado"   => $tipo, // 'rete' o 'tras'
        "base"                 => $imp["Base"] ?? 0.00,
        "impuesto"             => $imp["Impuesto"] ?? '000',
        "tipo_factor"          => $imp["TipoFactor"] ?? null,
        "tasa_cuota"           => $imp["TasaOCuota"] ?? null,
        "importe"              => $imp["Importe"] ?? 0.00,
        "impuesto_relacionado" => $idHomonimo,
          //"created_at"           => now() // Recomendado si usas timestamps
      ];
    }

    // 3. Inserción masiva: Una sola ejecución de SQL para todos los impuestos
    if (!empty($dataToInsert)) {
      $inserted = DB::table('eegr_compras_detalle_impuestos')->insert($dataToInsert);

      if (!$inserted) {
        throw new \Exception("Error crítico al registrar el bloque de impuestos de tipo: " . $tipo);
      }
    }
  }

  private function registraArticuloCFDICompra($detBuy,$obtenCompra,$comprobante_fiscal_reg,$emp_id){
    $retenciones = $detBuy['retenciones'];
    $traslados = $detBuy['traslados'];
    
    $uuid_cfdi_detalle = Str::uuid()->toString();
    $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
    ->insert(array(
      "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
      "comprobante_fiscal" => $comprobante_fiscal_reg,
      "NoIdentificacion" => $detBuy['NoIdentificacion'],
      "ObjetoImp" => $detBuy['ObjetoImp'],
      "ClaveProdServ" => $detBuy['ClaveProdServ'],
      "Cantidad" => $detBuy['Cantidad'],
      "ClaveUnidad" => $detBuy['ClaveUnidad'],
      "Unidad" => $detBuy['Unidad'],
      "Descripcion" => $detBuy['Descripcion'],
      "ValorUnitario" => $detBuy['ValorUnitario'],
      "Descuento" => $detBuy['Descuento'],
      "Importe" => $detBuy['Importe'],
      "TotalRetenciones" => $detBuy['TotalRetenciones'],
      "TotalTraslados" => $detBuy['TotalTraslados'],
      "Subtotal" => $detBuy['Subtotal'],
      "empresa" => $emp_id
    ));

    if (!$insertDetCFDICompra) {
      throw new \Exception("No se pudo generar el registro de detalle de CFDI para: " . $detBuy['Descripcion']);
    }

    $this->insertarImpuestosCFDI($uuid_cfdi_detalle, $obtenCompra, $detBuy['retenciones'] ?? [], 'rete');
    $this->insertarImpuestosCFDI($uuid_cfdi_detalle, $obtenCompra, $detBuy['traslados'] ?? [], 'tras');

    return $uuid_cfdi_detalle;
  }

  private function insertarImpuestosCFDI($uuidDetalle, $numCompra, $impuestos, $tipo) {
    if (empty($impuestos)) return;
    $dataImpCFDIToInsert = [];
    foreach ($impuestos as $imp) {
      $dataImpCFDIToInsert[] = [
        'uuid_buydet_impuestos' => Str::uuid()->toString(),
        'numero_compra'         => $numCompra,  
        'uuid_cfdi_detalle'     => $uuidDetalle,
        'retencion_traslado'    => $tipo,
        'base'                  => $imp["Base"] ?? 0.00,
        'impuesto'              => $imp["Impuesto"] ?? '000',
        'tipoFactor'            => $imp["TipoFactor"] ?? NULL,
        'tasaOCuota'            => $imp["TasaOCuota"] ?? NULL,
        'importe'               => $imp["Importe"] ?? 0.00,
        //"created_at"            => now()
      ];
    }

    // 3. Inserción masiva: Una sola ejecución de SQL para todos los impuestos
    if (!empty($dataImpCFDIToInsert)) {
      $inserted = DB::table('eegr_compras_cfdi_detalle_impuestos')->insert($dataImpCFDIToInsert);

      if (!$inserted) {
        throw new \Exception("No se pudo registrar los impuestos de CFDI.");
      }
    }
  }
  
  private function procesarProrrateo($JwtAuth, $vEmp, $id_prod, $id_serv, $obtenCompra, $selectDetBuy, $fecha_contabilizacion) {
    $folioProrrateo = DB::selectOne("SELECT COALESCE(MAX(folder) + 1, 1) AS folio FROM sos_last_folders WHERE egr_prorrateos = TRUE AND empresa = ?", [$vEmp->id]);
    $tokenProrrateo = $JwtAuth->encriptarToken(time().$selectDetBuy.$id_prod.$id_serv.$obtenCompra);
  
    $id_p = DB::table('eegr_compras_prorrateos')->insertGetId([
      "token_prorrateo" => $tokenProrrateo,
      "folio_prorrateo" => $folioProrrateo->folio,
      "fecha_sistema_prorrateo" => time(),
      "fecha_prorrateo" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
      "producto" => $id_prod,
      "servicio" => $id_serv,
      "compra" => $obtenCompra,
      "detalle_compra" => $selectDetBuy,
      "empresa" => $vEmp->id,
      "status_prorrateo" => TRUE,
    ]);

    if (!$id_p) {
      throw new \Exception("No se pudo registrar prorrateos.");
    }

    // Actualizar el folio en la tabla de control
    DB::table('sos_last_folders')
    ->updateOrInsert(
      ['egr_prorrateos' => TRUE, 'empresa' => $vEmp->id],
      ['folder' => $folioProrrateo->folio]
    );

    $obten_prorrateo_ident =DB::table("eegr_compras_prorrateos")->where("token_prorrateo",$tokenProrrateo)->value("id");
    $tokenDetalleProrrt = $JwtAuth->encriptarToken(time().$obten_prorrateo_ident.$id_prod, $id_serv, $obtenCompra, $selectDetBuy);

    DB::table('eegr_compras_prorrateos_detalle')->insert([
      "token_detalle_prorrt" => $tokenDetalleProrrt,
      "prorrateo" => $id_p,
      "detalle_compra" => $selectDetBuy,
    ]);
  }
  
  private function procesarKardexProducto($JwtAuth, $vEmp, $id_producto, $tokenArticulo, $obtenCompra, $selectDetBuy, $cantidad, $precioUnitario, $tokenCompra) {
    //// 1. Mejora en la obtención del folio: Más legible y usando el Query Builder de Laravel
    //$ultimoFolio = DB::table('in_egr_productos_kardex as dexkar')
    //->join('in_egr_catalogo_productos as catprod', 'dexkar.producto_id', '=', 'catprod.id')
    //->join('main_empresas as emp', 'catprod.admin_empresa', '=', 'emp.id')
    //->where('catprod.token_cat_productos', $tokenArticulo)
    //->where('emp.empresa_token', $vEmp->id)
    //->max('dexkar.folio_kardex');
    ////
    //$nuevoFolio = ($ultimoFolio ?? 0) + 1;
    //// 2. Token con mayor entropía: Evitamos colisiones si ocurren procesos en el mismo segundo
    //$token_kardex = $JwtAuth->encriptarToken(time() . $tokenCompra . $selectDetBuy . uniqid());
    //// 3. Inserción en Kardex
    //// Nota: Usamos una variable para validar el resultado
    //$insertOk = DB::table("in_egr_productos_kardex")->insert([
    //  "token_kardex"      => $token_kardex,
    //  "folio_kardex"      => $nuevoFolio,
    //  "fecha_kardex"      => time(),
    //  "status_kardex"     => "por_recibir", // 2 = Por recibir
    //  "producto_id"       => $id_producto,
    //  "concepto"          => "por recibir",
    //  "tipo_documento"    => "COMPRA",
    //  "factura_compra"    => $obtenCompra,
    //  "detalle_compra"    => $selectDetBuy,
    //  "recibir_cantidad"  => $cantidad,
    //  "valor_unitario"    => $precioUnitario,
    //]);

    $insertOk = $this->kardexService->registrarRecibir($id_producto,$cantidad,$precioUnitario,'por_recibir', /*status_kardex*/'Registro de Orden de Compra mediante CFDI', /*concepto*/'COMPRA', /*tipo_documento*/$obtenCompra,$selectDetBuy);
  
    // 4. Validación inmediata (Throw) antes de cualquier otra operación
    if (!$insertOk) {
      throw new \Exception("Error al registrar movimiento en Kardex para el producto ID: $id_producto");
    }
  
    // 5. Actualización del catálogo: Solo ocurre si el Kardex fue exitoso
    $updateCat = DB::table('in_egr_catalogo_productos')
    ->where('id', $id_producto)
    ->update(["ultima_compra" => time()]);
  
    if (!$updateCat) {
      throw new \Exception("Error al registrar movimiento en ultima compra para el producto ID: $id_producto");
    }
  }

  private function procesarActivoFijo($JwtAuth, $selectDetBuy, $detBuy, $cfdi_moneda, $cfdi_tc, $emp_id) {
    $empData = DB::table("sos_personas AS people")
    ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
    ->where("emp.id",$emp_id)
    ->select('people.abrev_nombre')
    ->first();
    $abrev = $empData ? strtoupper($empData->abrev_nombre) : 'EMP';

    // 1. Obtención de datos base e ID del catálogo
    $id_activo_fijo = DB::table("eegr_activos_fijos_catalogo")
    ->where("token_act_fijos", $detBuy['articulo_homologado_activoFijo'])
    ->value("id");
    
    // Limpieza de descuento
    $descuentoXUni = $detBuy['Descuento'] ?? '0.00';
    $total_descuento = ($descuentoXUni !== '' && $descuentoXUni !== '---' && $descuentoXUni !== '0.00') ? $descuentoXUni : '0.00';
    
    // Cálculo eficiente de impuestos
    $total_retenciones = collect($detBuy['retenciones'] ?? [])->sum(fn($r) => ($r['TipoFactor'] !== 'Exento') ? ($r['Importe'] ?? 0) : 0);
    $total_traslados = collect($detBuy['traslados'] ?? [])->sum(fn($t) => ($t['TipoFactor'] !== 'Exento') ? ($t['Importe'] ?? 0) : 0);
    
    $uuid_activo_fijo_det = Str::uuid()->toString();//(string) Str::uuid();
    
    // 2. Inserción del registro Maestro (Detalle de Activo)
    // Usamos insertGetId para obtener el ID real y evitar consultas extras dentro del bucle
    //echo $emp_id;
    $id_det_insertado = DB::table('eegr_activos_fijos_detalle')->insertGetId([
      "token_det_activo_fijo" => $uuid_activo_fijo_det,
      "activo_fijo"           => $id_activo_fijo,
      "compra_detalle"        => $selectDetBuy,
      "concepto"              => $JwtAuth->encriptar($detBuy['Descripcion']),
      "moneda"                => $cfdi_moneda,
      "tipo_de_cambio"        => $cfdi_tc,
      "precio_unitario"       => $detBuy['ValorUnitario'],
      "cantidad"              => $detBuy['Cantidad'],
      "unidad_medida"         => $detBuy['Unidad'],
      "descuento"             => $total_descuento,
      "retenciones_total"     => $total_retenciones,
      "traslados_total"       => $total_traslados,
      "empresa"               => $emp_id
    ]);

    if (!$id_det_insertado) {
      throw new \Exception("No se pudo generar el registro de detalle de activo para: " . $detBuy['Descripcion']);
    }

    // 3. Preparación de Unidades (Bulk Insert)
    //$activo_fijo_foliado = $detBuy['articulo_homologado_activo_foliado'] ?? [];
    $unidadesFijosParaInsertar = [];

    $ultimoFolio = DB::table('eegr_activos_fijos_unidades')
    ->where('empresa', $emp_id)
    ->where('folio_activof_unidad', 'LIKE', "ACT-$abrev-%")
    ->orderBy('id', 'desc')
    ->value('folio_activof_unidad');
    
    // 3. Extraer el número y determinar el siguiente
    if ($ultimoFolio) {
      $partes = explode('-', $ultimoFolio);
      $consecutivo = (int)end($partes) + 1;
    } else {
      $consecutivo = 1;
    }
    $ua_fe_cantidad = (int) $detBuy['Cantidad'];
    for ($ufae = 0; $ufae < $ua_fe_cantidad; $ufae++) {
      $folioAutomatico = "ACT-" . $abrev . "-" . str_pad($consecutivo, 4, "0", STR_PAD_LEFT);
      $unidadesFijosParaInsertar[] = [
        "token_activof_unidad" => Str::uuid()->toString(),
        "activof_detalle"      => $id_det_insertado, // Relación directa por ID
        "folio_activof_unidad"         => $folioAutomatico,
        //"serie"                => $folio['activo_serie'] ?? null,
        //"otros"                => $folio['activo_otros'] ?? null,
        //"observaciones"        => $folio['activo_observaciones'] ?? null,
        "empresa"              => $emp_id,
        //"created_at"           => now() // Recomendado para trazabilidad
      ];
      $consecutivo++;
    }

    // 4. Inserción masiva de una sola vez
    if (!empty($unidadesFijosParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_fijos_unidades')->insert($unidadesFijosParaInsertar);

      if (!$insertUnidades) {
        throw new \Exception("Error crítico al registrar las unidades individuales de los activos.");
      }
    }
  }

  private function procesarActivoDiferido($JwtAuth, $selectDetBuy, $detBuy, $cfdi_moneda, $cfdi_tc, $emp_id) {
    $empData = DB::table("sos_personas AS people")
    ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
    ->where("emp.id",$emp_id)
    ->select('people.abrev_nombre')
    ->first();
    $abrev = $empData ? strtoupper($empData->abrev_nombre) : 'EMP';

    // 1. Obtención de datos base e ID del catálogo
    $id_activo_diferido = DB::table("eegr_activos_intangibles_catalogo")
    ->where("token_act_intang", $detBuy['articulo_homologado_activoDiferido'])
    ->value("id");
    
    // Limpieza de descuento
    $descuentoXUni = $detBuy['Descuento'] ?? '0.00';
    $total_descuento = ($descuentoXUni !== '' && $descuentoXUni !== '---' && $descuentoXUni !== '0.00') ? $descuentoXUni : '0.00';
    
    // Cálculo eficiente de impuestos
    $total_retenciones = collect($detBuy['retenciones'] ?? [])->sum(fn($r) => ($r['TipoFactor'] !== 'Exento') ? ($r['Importe'] ?? 0) : 0);
    $total_traslados = collect($detBuy['traslados'] ?? [])->sum(fn($t) => ($t['TipoFactor'] !== 'Exento') ? ($t['Importe'] ?? 0) : 0);
    
    $uuid_activo_fijo_det = Str::uuid()->toString();//(string) Str::uuid();
    
    // 2. Inserción del registro Maestro (Detalle de Activo)
    // Usamos insertGetId para obtener el ID real y evitar consultas extras dentro del bucle
    //echo $emp_id;
    $id_det_insertado = DB::table('eegr_activos_intangibles_detalle')->insertGetId([
      "token_det_act_intang"  => $uuid_activo_fijo_det,
      "activo_intang"         => $id_activo_diferido,
      "compra_detalle"        => $selectDetBuy,
      "concepto"              => $JwtAuth->encriptar($detBuy['Descripcion']),
      "moneda"                => $cfdi_moneda,
      "tipo_de_cambio"        => $cfdi_tc,
      "precio_unitario"       => $detBuy['ValorUnitario'],
      "cantidad"              => $detBuy['Cantidad'],
      "unidad_medida"         => $detBuy['Unidad'],
      "descuento"             => $total_descuento,
      "retenciones_total"     => $total_retenciones,
      "traslados_total"       => $total_traslados,
      "empresa"               => $emp_id
    ]);

    if (!$id_det_insertado) {
      throw new \Exception("No se pudo generar el registro de detalle de activo para: " . $detBuy['Descripcion']);
    }

    // 3. Preparación de Unidades (Bulk Insert)
    //$activo_fijo_foliado = $detBuy['articulo_homologado_activo_foliado'] ?? [];
    $unidadesDiferidosParaInsertar = [];

    $ultimoFolio = DB::table('eegr_activos_intangibles_unidades')
    ->where('empresa', $emp_id)
    ->where('folio_activod_unidad', 'LIKE', "ACT-$abrev-%")
    ->orderBy('id', 'desc')
    ->value('folio_activod_unidad');
    
    // 3. Extraer el número y determinar el siguiente
    if ($ultimoFolio) {
      $partes = explode('-', $ultimoFolio);
      $consecutivo = (int)end($partes) + 1;
    } else {
      $consecutivo = 1;
    }
    $ua_fe_foliado = $detBuy['articulo_homologado_activo_diferido_foliado'];
    foreach ($ua_fe_foliado as $item) {
      $folioAutomatico = "ACT-" . $abrev . "-" . str_pad($consecutivo, 4, "0", STR_PAD_LEFT);
      $unidadesDiferidosParaInsertar[] = [
        "token_activod_unidad" => Str::uuid()->toString(),
        "activod_detalle"      => $id_det_insertado, // Relación directa por ID
        "folio_activod_unidad" => $folioAutomatico,
        //costo_adquisicion
        //fecha_inicio_amortizacion
        "amort_contable_periodo" => $item['amort_contable_periodo'],
        "amort_contable_tiempo" => $item['amort_contable_tiempo'],
        "amort_contable_fecha_apartir" => $item['amort_contable_fecha_apartir'] ? $JwtAuth->convierteFechaEpoc($item['amort_contable_fecha_apartir']) : NULL,
        "amort_contable_observaciones" => $JwtAuth->encriptar($item['amort_contable_observaciones']),
        //fecha_ultimo_corte_contable
        //fecha_proximo_corte_contable
        "amort_fiscal_periodo" => $item['amort_fiscal_periodo'],
        "amort_fiscal_tiempo" => $item['amort_fiscal_tiempo'],
        "amort_fiscal_fecha_apartir" => $item['amort_fiscal_fecha_apartir'] ? $JwtAuth->convierteFechaEpoc($item['amort_fiscal_fecha_apartir']) : NULL,
        "amort_fiscal_observaciones" => $JwtAuth->encriptar($item['amort_fiscal_observaciones']),
        //fecha_ultimo_corte_fiscal
        //fecha_proximo_corte_fiscal
        //amortizacion_bloqueada
        //date_bloqueo_desbloqueo_prorrateo
        "empresa"              => $emp_id
      ];
      $consecutivo++;
    }

    // 4. Inserción masiva de una sola vez
    if (!empty($unidadesDiferidosParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_intangibles_unidades')->insert($unidadesDiferidosParaInsertar);

      if (!$insertUnidades) {
        throw new \Exception("Error crítico al registrar las unidades individuales de los activos.");
      }
    }
  }

  public function registrarCompraByCFDI(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'fecha_contabilizacion' => 'required|string',
      'fecha_vencimiento' => 'required|string',
      'cfdi_comprobante' => 'required|json',//* 
      'total' => 'required|string', 
      'cfdi_relacionados' => 'nullable|json',
      'cfdi_emisor' => 'required|json',//*  
      'token_proveedor' => 'required|string',
      'cfdi_receptor' => 'required|json',//*
      'cfdi_conceptos' => 'required|json',//*
      'cfdi_impuestos_retenidos' => 'nullable|json',
      'cfdi_impuestos_trasladados' => 'nullable|json',
      'cfdi_complemento' => 'required|json',//*
      'cfdi_complemento_carta_porte' => 'nullable|json',//
      'compra_contado_credito' => 'required|string',
      'anticipo_aplicado' => 'nullable|numeric',
      'classRecibeArtPago' => 'required|string',
      'tipoLugarEntrega' => 'required|string',
      'compra_fecha_tentativa_salida' => 'nullable|string',
      'tknLugarSalida' => 'nullable|string',
      'compra_fecha_tentativa_recepcion' => 'nullable|string',
      'tknLugarRecepcion' => 'nullable|string',
      'compra_observaciones' => 'nullable|string',
      'pagar' => 'nullable|string'
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
      $moneda_decimales = 0;
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $fecha_vencimiento = $request->input('fecha_vencimiento');
      $cfdi_comprobante = json_decode($request->input('cfdi_comprobante'), true);
      $total = $request->input('total');
      $cfdi_relacionados = json_decode($request->input('cfdi_relacionados'), true);
      $cfdi_emisor = json_decode($request->input('cfdi_emisor'), true);
      $token_proveedor = $request->input('token_proveedor');
      $cfdi_receptor = json_decode($request->input('cfdi_receptor'), true);
      $cfdi_conceptos = json_decode($request->input('cfdi_conceptos'), true);
      $cfdi_impuestos_retenidos = json_decode($request->input('cfdi_impuestos_retenidos'), true);
      $cfdi_impuestos_trasladados = json_decode($request->input('cfdi_impuestos_trasladados'), true);
      $cfdi_complemento = json_decode($request->input('cfdi_complemento'), true);
      $cfdi_complemento_carta_porte = json_decode($request->input('cfdi_complemento_carta_porte'), true);
      $compra_contado_credito = $request->input('compra_contado_credito');
      $anticipo_aplicado = $request->input('anticipo_aplicado');
      $classRecibeArtPago = $request->input('classRecibeArtPago') == 'true' ? true : false;
      $tipoLugarEntrega = $request->input('tipoLugarEntrega');
      $compra_fecha_tentativa_salida = $request->input('compra_fecha_tentativa_salida');
      $tknLugarSalida = $request->input('tknLugarSalida');
      $compra_fecha_tentativa_recepcion = $request->input('compra_fecha_tentativa_recepcion');
      $tknLugarRecepcion = $request->input('tknLugarRecepcion');
      $compra_observaciones = $request->input('compra_observaciones');
      $compra_pagar = $request->input('pagar');

      //echo $empresa;exit;
      $mi_llave_secreta = env('JWT_BUY_ID_SECRET');
      $permisosCreacion = $JwtAuth->permisosCreacion($mi_llave_secreta,$empresa,$usuario);
      //return response()->json(['status' => 'error','code' => 200,'message' => "fecha_contabilizacion $fecha_contabilizacion"]);

      $validate_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $validate_fecha_vencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $validate_cfdi_comprobante = isset($cfdi_comprobante) && !empty($cfdi_comprobante) && is_array($cfdi_comprobante);
      $validate_total = isset($total) && !empty($total);
      $validate_cfdi_emisor = isset($cfdi_emisor) && !empty($cfdi_emisor) && is_array($cfdi_emisor);
      $validate_prov = isset($token_proveedor) && !empty($token_proveedor);
      $validate_cfdi_receptor = isset($cfdi_receptor) && !empty($cfdi_receptor) && is_array($cfdi_receptor);
      $validate_cfdi_conceptos = isset($cfdi_conceptos) && !empty($cfdi_conceptos) && is_array($cfdi_conceptos);
      $validate_cfdi_impuestos_retenidos = isset($cfdi_impuestos_retenidos) && is_array($cfdi_impuestos_retenidos);
      $validate_cfdi_impuestos_trasladados = isset($cfdi_impuestos_trasladados) && is_array($cfdi_impuestos_trasladados);
      $validate_cfdi_complemento = isset($cfdi_complemento) && !empty($cfdi_complemento) && is_array($cfdi_complemento);
      $validate_compra_contado_credito = isset($compra_contado_credito) && !empty($compra_contado_credito) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_contado_credito);
      $validate_anticipo_aplicado = isset($anticipo_aplicado) && !empty($anticipo_aplicado);
      $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);

      $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega);
      $validate_fecha_tentativa_salida_compra = isset($compra_fecha_tentativa_salida) && !empty($compra_fecha_tentativa_salida) && preg_match($JwtAuth->filtroFecha(),$compra_fecha_tentativa_salida);
      $validate_LugarSalida_tkn = isset($tknLugarSalida) && !empty($tknLugarSalida);
      $validate_fecha_tentativa_recepcion_compra = isset($compra_fecha_tentativa_recepcion) && !empty($compra_fecha_tentativa_recepcion) && preg_match($JwtAuth->filtroFecha(),$compra_fecha_tentativa_recepcion);
      $validate_LugarRecepcion_tkn = isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);

      $validate_compra_observaciones = isset($compra_observaciones) && !empty($compra_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_observaciones);

      if ($validate_fecha_contabilizacion && $validate_fecha_vencimiento && $permisosCreacion && $validate_cfdi_comprobante && $validate_total && $validate_cfdi_emisor && $validate_prov && $validate_cfdi_receptor && 
        $validate_cfdi_conceptos && $validate_cfdi_impuestos_retenidos && $validate_cfdi_impuestos_trasladados && $validate_cfdi_complemento && $validate_compra_contado_credito &&
        $validate_classRecibeArtPago && $validate_tipoLugarEntrega) {
          // && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))

        $moneda_decimales = $JwtAuth->getMonedaAPI($cfdi_comprobante['moneda'] ?? 'MXN') || 2;

        $tentativa_salida_compra = $tipoLugarEntrega != 'noAplica' && $validate_fecha_tentativa_salida_compra ? $JwtAuth->convierteFechaEpoc($compra_fecha_tentativa_salida) : NULL;
        $idSalidaLugar = $tipoLugarEntrega != 'proveedor' && $validate_LugarSalida_tkn ? DB::table("teci_direcciones AS dir")
        ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
        ->where(["dir.token_direccion" => $tknLugarSalida,"catprov.token_cat_proveedores" => $token_proveedor])
        ->value("dir.id") : NULL;
        
        $tentativa_recepcion_compra = $tipoLugarEntrega != 'noAplica' && $validate_fecha_tentativa_recepcion_compra ? $JwtAuth->convierteFechaEpoc($compra_fecha_tentativa_recepcion) : NULL;
        $idRecepcionLugar = $tipoLugarEntrega != 'noAplica' && $validate_LugarRecepcion_tkn ? DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id") : NULL;
        
        $detalleErrores = $this->registrarCompraValidaConceptos($cfdi_conceptos,$moneda_decimales,$JwtAuth);
        //return response()->json(['status' => 'error','code' => 200,'message' => $detalleErrores.$cfdi_comprobante['moneda']]);
        
        if ($detalleErrores == "") {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
              WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

            $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
              WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa, $usuario]);

            $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
            $folio_buy = 'COMP-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');
            //return response()->json(['message' => $folio_buy,'codigo' => 200,'status' => 'error']);
            $nombreRecePago = '';
            
            DB::beginTransaction();
            try {
              $cfdi_comprobante_version = $cfdi_comprobante['version'] ?? '---';
              $cfdi_comprobante_serie = $cfdi_comprobante['serie'] ?? '---';
              $cfdi_comprobante_folio = $cfdi_comprobante['folio'] ?? '---';
              $cfdi_comprobante_fecha = $cfdi_comprobante['fecha'] ?? '---';
              $cfdi_comprobante_forma_de_pago = $cfdi_comprobante['forma_de_pago'] ?? '---';
              $cfdi_comprobante_subtotal = $cfdi_comprobante['subtotal'] ?? '---';
              $cfdi_comprobante_moneda = $cfdi_comprobante['moneda'] ?? '---';
              $cfdi_comprobante_tipo_de_cambio = $cfdi_comprobante['tipo_de_cambio'] ?? '1.00';
              $cfdi_comprobante_total = $cfdi_comprobante['total'] ?? '---';
              $cfdi_comprobante_confirmacion = $cfdi_comprobante['confirmacion'] ?? '---';
              $cfdi_comprobante_tipo_de_comprobante = $cfdi_comprobante['tipo_de_comprobante'] ?? '---';
              $cfdi_comprobante_metodo_de_pago = $cfdi_comprobante['metodo_de_pago'] ?? '---';
              $cfdi_comprobante_lugar_de_expedicion = $cfdi_comprobante['lugar_de_expedicion'] ?? '---';
              $cfdi_comprobante_no_de_certificado = $cfdi_comprobante['no_de_certificado'] ?? '---';
              $cfdi_comprobante_sello = $cfdi_comprobante['sello'] ?? '---';
              $cfdi_comprobante_certificado = $cfdi_comprobante['certificado'] ?? '---';

              $cfdi_relacionados_tipo_de_relacion = $cfdi_relacionados['tipo_de_relacion'] ?? '---';
              $cfdi_relacionados_uuid = $cfdi_relacionados['UUID'] ?? '---';

              $cfdi_emisor_rfc = $cfdi_emisor['rfc_del_emisor'] ?? '---';
              $cfdi_emisor_nombre = $cfdi_emisor['nombre_del_emisor'] ?? '---';
              $cfdi_emisor_regimen_fiscal = $cfdi_emisor['regimen_fiscal_del_emisor'] ?? '---';
              
              $cfdi_receptor_rfc = $cfdi_receptor['rfc_del_receptor'] ?? '---';
              $cfdi_receptor_uso_del_cfdi = $cfdi_receptor['uso_del_cfdi'] ?? '---';

              $cfdi_complementoUUID = $cfdi_complemento['UUID'] ?? '---';
              $cfdi_complementoFechaTimbrado = $cfdi_complemento['FechaTimbrado'] ?? '---';
              $cfdi_complementoRfcProvCertif = $cfdi_complemento['RfcProvCertif'] ?? '---';
              $cfdi_complementoNoCertificadoSAT = $cfdi_complemento['NoCertificadoSAT'] ?? '---';
              $cfdi_complementoSelloCFD = $cfdi_complemento['SelloCFD'] ?? '---';
              $cfdi_complementoSelloSAT = $cfdi_complemento['SelloSAT'] ?? '---';
              $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
              
              $tokenCompra = $JwtAuth->encriptarToken(time(), $idProveedor, $cfdi_comprobante_moneda, $tipoLugarEntrega, $tknLugarRecepcion, $cfdi_conceptos);
              $fechaSistema = time();
              $fecha_altaCompra = $cfdi_comprobante_fecha != '' ? $JwtAuth->convierteFechaCFDIUnix($cfdi_comprobante_fecha) : time();
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden_'.$fecha_altaCompra]);
              $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
              $status_autorizacion = $vEmp->jerarquia_main == 'P' ? TRUE : FALSE;
              $nombreDocs = $fechaSistema."-".$folio_buy;
              $compras = new ComprasModelo();
              $compras->token_compras = $tokenCompra;
              $compras->folio_compra = $folio_nuevo;
              $compras->post_folio = $post_folio;
              $compras->fecha_sistemaCompras = $fechaSistema;
              $compras->fecha_altaCompra = $fecha_altaCompra;
              $compras->fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
              $compras->fecha_vencimiento = $JwtAuth->convierteFechaEpoc($fecha_vencimiento);
              $compras->proveedor = $idProveedor;
              $compras->compra_a_credito = $compra_contado_credito == 'contado' ? 'cont' : 'cred';
              $compras->recibeFactura = TRUE;
              $compras->aplica_recepcion_facturas = 'Sí';
              //$compras->facturaXml = file_exists($request->file('imagenEvidenciaXMl')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL;  //cifrado 
              //$compras->facturaPdf = file_exists($request->file('imagenEvidenciaPdf')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL;  //cifrado 
              $compras->recepcionPago = $nombreRecePago; //cifrado
              //$compras->evidenciaSAT = file_exists($request->file('imagenEvidenciaVerificacion')) ? $JwtAuth->encriptar($fechaSistema."-".$folio_buy.pathinfo($request->file('imagenEvidenciaVerificacion')->getClientOriginalName(), PATHINFO_FILENAME). ".pdf") : NULL; //cifrado
              $compras->anexos = $JwtAuth->encriptar($nombreDocs.".pdf");  //cifrado
              $compras->reporte = $JwtAuth->encriptar($nombreDocs.".pdf"); //cifrado
              $compras->moneda = $cfdi_comprobante_moneda;
              $compras->tipo_de_cambio = $cfdi_comprobante_tipo_de_cambio;
              $compras->anticipo = $anticipo_aplicado;
              $compras->forma_pago = $cfdi_comprobante_forma_de_pago;
              $compras->metodo_pago = $cfdi_comprobante_metodo_de_pago;
              $compras->uso_cfdi = $cfdi_receptor_uso_del_cfdi;
              $compras->recibeProducto = $classRecibeArtPago ? TRUE : FALSE;// si es TRUE genera orden de pago, si es FALSE no
              $compras->pago_caja_tesoreria = NULL;
              $compras->caja_paga = NULL;

              $compras->fecha_tentativa_salida = $tentativa_salida_compra;
              $compras->direccion_salida_prov = $idSalidaLugar;
              $compras->recepcion_estab = $idRecepcionLugar;
              $compras->fecha_tentativa_recepcion = $tentativa_recepcion_compra;
              
              $compras->comprador = $vEmp->id;
              $compras->usuario_comprador = $vEmp->userr;
              $compras->status_autorizacion = $status_autorizacion;
              $compras->autoriza = $user_jerarquia;
              $compras->status_cancelacion = FALSE;
              $compras->cancela = NULL;
              $compras->status_recepcion = FALSE;
              $compras->recibe = NULL;
              $compras->status_compra = TRUE;
              $compras->observaciones_compra = $validate_compra_observaciones ? $JwtAuth->encriptar($compra_observaciones) : NULL;
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden_']);

              if ($request->hasFile('imagenEvidenciaXMl') && $request->file('imagenEvidenciaXMl')->isValid()) {
                $xmlFile = $request->file('imagenEvidenciaXMl');
                $nombreFisicoXML = $fechaSistema . "-" . $folio_buy . "_" . str_replace([' ', '#'], '_', $xmlFile->getClientOriginalName());
                $compras->facturaXml = $JwtAuth->encriptar($nombreFisicoXML);
              }

              //Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName()); 
              if ($request->hasFile('imagenEvidenciaPdf') && $request->file('imagenEvidenciaPdf')->isValid()) {
                $pdfFile = $request->file('imagenEvidenciaPdf');
                $nombreFisicoPDF = $fechaSistema . "-" . $folio_buy . "_" . str_replace([' ', '#'], '_', $pdfFile->getClientOriginalName());
                $compras->facturaPdf = $JwtAuth->encriptar($nombreFisicoPDF);
              }

              //Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName()) : NULL;
              if ($request->hasFile('imagenEvidenciaVerificacion') && $request->file('imagenEvidenciaVerificacion')->isValid()) {
                $verifFile = $request->file('imagenEvidenciaVerificacion');
                $nombreFisicoVerif = $fechaSistema . "-" . $folio_buy . "_" . str_replace([' ', '#'], '_', $verifFile->getClientOriginalName());
                $compras->evidenciaSAT = $JwtAuth->encriptar($nombreFisicoVerif);
              }
                
              $insertCompra =$compras->save(); 
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden']);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
              
              if (!$insertCompra) {
                throw new \Exception("Error al guardar la cabecera de la compra.");
              }
              
              if ($insertCompra) {
                $obtenCompra = $compras->id;
                $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";
                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

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
                if (!$insertCFDIEstructura) {
                  throw new \Exception("Error al guardar comprobante fiscal.");
                }
                
                $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
                $insertCFDIVincBuy = DB::table('cfdi_vinculacion_compras')//cfdi__estructura
                ->insert(array(
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  "compra_vinculada" => $obtenCompra,
                ));

                if ($cfdi_complemento_carta_porte) {
                  $carta_porte_token = Str::uuid()->toString();
                  DB::table('comprobante_carta_porte')
                  ->insert(array(
                    "carta_porte_token" => $carta_porte_token,
                    "comprobante_fiscal" => $comprobante_fiscal_reg,
                    "version" => $cfdi_complemento_carta_porte['Version'] ?? '---',
                    "id_ccp" => $cfdi_complemento_carta_porte['IdCCP'] ?? '---',
                    "transp_internac" => $cfdi_complemento_carta_porte['TranspInternac'] ?? '---',
                    "regimen_aduanero" => $cfdi_complemento_carta_porte['RegimenAduanero'] ?? '---',
                    "entrada_salida_merc" => $cfdi_complemento_carta_porte['EntradaSalidaMerc'] ?? '---',
                    "pais_origen_destino" => $cfdi_complemento_carta_porte['PaisOrigenDestino'] ?? '---',
                    "via_entrada_salida" => $cfdi_complemento_carta_porte['ViaEntradaSalida'] ?? '---',
                    "total_dist_rec" => $cfdi_complemento_carta_porte['TotalDistRec'] ?? '---',
                    "registro_istmo" => $cfdi_complemento_carta_porte['RegistroISTMO'] ?? '---',
                    "ubicacion_polo_origen" => $cfdi_complemento_carta_porte['UbicacionPoloOrigen'] ?? '---',
                    "ubicacion_polo_destino" => $cfdi_complemento_carta_porte['UbicacionPoloDestino'] ?? '---',
                  ));
                  $carta_porte_id = DB::table('comprobante_carta_porte')->where("carta_porte_token",$carta_porte_token)->value("id");
                  
                  foreach ($cfdi_complemento_carta_porte['ubicaciones'] as $vcpUbica) {
                    DB::table('carta_porte_ubicaciones')//cfdi__estructura
                    ->insert(array(
                      "carta_porte" => $carta_porte_id,
                      "tipo_ubicacion" => $vcpUbica['TipoUbicacion'] ?? '---',//TipoUbicacion
                      "id_ubicacion" => $vcpUbica['IdUbicacion'] ?? '---',//IdUbicacion
                      "rfc_remitente_destinatario" => $vcpUbica['RFCRemitenteDestinatario'] ?? '---',//RFCRemitenteDestinatario
                      "nombre_remitente_destinatario" => $vcpUbica['NombreRemitenteDestinatario'] ?? '---',//NombreRemitenteDestinatario
                      "num_reg_id_trib" => $vcpUbica['NumRegIdTrib'] ?? '---',//NumRegIdTrib
                      "residencia_fiscal" => $vcpUbica['ResidenciaFiscal'] ?? '---',//ResidenciaFiscal
                      "num_estacion" => $vcpUbica['NumEstacion'] ?? '---',//NumEstacion
                      "nombre_estacion" => $vcpUbica['NombreEstacion'] ?? '---',//NombreEstacion
                      "navegacion_trafico" => $vcpUbica['NavegacionTrafico'] ?? '---',//NavegacionTrafico
                      "fecha_hora_salida_llegada" => $vcpUbica['FechaHoraSalidaLlegada'] ?? '---',//FechaHoraSalidaLlegada
                      "tipo_estacion" => $vcpUbica['TipoEstacion'] ?? '---',//TipoEstacion
                      "distancia_recorrida" => $vcpUbica['DistanciaRecorrida'] ?? '---',//DistanciaRecorrida
                      "calle" => $vcpUbica['Calle'] ?? '---',
                      "numero_exterior" => $vcpUbica['NumeroExterior'] ?? '---',
                      "numero_interior" => $vcpUbica['NumeroInterior'] ?? '---',
                      "colonia" => $vcpUbica['Colonia'] ?? '---',
                      "localidad" => $vcpUbica['Localidad'] ?? '---',
                      "referencia" => $vcpUbica['Referencia'] ?? '---',
                      "municipio" => $vcpUbica['Municipio'] ?? '---',
                      "estado" => $vcpUbica['Estado'] ?? '---',
                      "pais" => $vcpUbica['Pais'] ?? '---',
                      "codigo_postal" => $vcpUbica['CodigoPostal'] ?? '---',
                    ));
                  }
                  
                  foreach ($cfdi_complemento_carta_porte['mercancias'] as $vcpMercan) {
                    $mercancias_totales_token = Str::uuid()->toString();
                    DB::table('carta_porte_mercancias_totales')
                    ->insert(array(
                      "mercancias_totales_token" => $mercancias_totales_token,
                      "carta_porte" => $carta_porte_id,
                      "peso_bruto_total" => $vcpMercan['PesoBrutoTotal'] ?? '---',
                      "unidad_peso" => $vcpMercan['UnidadPeso'] ?? '---',
                      "peso_neto_total" => $vcpMercan['PesoNetoTotal'] ?? '---',
                      "num_total_mercancias" => $vcpMercan['NumTotalMercancias'] ?? '---',
                      "cargo_por_tasacion" => $vcpMercan['CargoPorTasacion'] ?? '---',
                      "logistica_inversa_recoleccion_devolucion" => $vcpMercan['LogisticaInversaRecoleccionDevolucion'] ?? '---'
                    ));
                    $mercancias_totales_id = DB::table('carta_porte_mercancias_totales')->where("mercancias_totales_token",$mercancias_totales_token)->value("id");

                    foreach ($vcpMercan['Mercancia'] as $vLMerc) {
                      $cporte_merc_det_token = Str::uuid()->toString();
                      DB::table('carta_porte_mercancia_detalle')
                      ->insert(array(
                        "carta_porte_mercancia_detalle_token" => $cporte_merc_det_token,
                        "mercancias_totales" => $mercancias_totales_id,
                        "bienes_transp" => $vLMerc['BienesTransp'] ?? '---',
                        "clave_stcc" => $vLMerc['ClaveSTCC'] ?? '---',
                        "descripcion" => $vcpMercan['Descripcion'] ?? '---',
                        "cantidad" => $vcpMercan['Cantidad'] ?? 0,
                        "clave_unidad" => $vcpMercan['ClaveUnidad'] ?? '---',
                        "unidad" => $vcpMercan['Unidad'] ?? '---',
                        "dimensiones" => $vcpMercan['Dimensiones'] ?? '---',
                        "material_peligroso" => $vcpMercan['MaterialPeligroso'] ?? '---',
                        "cve_material_peligroso" => $vcpMercan['CveMaterialPeligroso'] ?? '---',
                        "embalaje" => $vcpMercan['Embalaje'] ?? '---',
                        "descrip_embalaje" => $vcpMercan['DescripEmbalaje'] ?? '---',
                        "sector_cofepris" => $vcpMercan['SectorCOFEPRIS'] ?? '---',
                        "nombre_ingrediente_activo" => $vcpMercan['NombreIngredienteActivo'] ?? '---',
                        "nom_quimico" => $vcpMercan['NomQuimico'] ?? '---',
                        "denominacion_generica_prod" => $vcpMercan['DenominacionGenericaProd'] ?? '---',
                        "denominacion_distintiva_prod" => $vcpMercan['DenominacionDistintivaProd'] ?? '---',
                        "fabricante" => $vcpMercan['Fabricante'] ?? '---',
                        "fecha_caducidad" => $vcpMercan['FechaCaducidad'] ?? '---',
                        "lote_medicamento" => $vcpMercan['LoteMedicamento'] ?? '---',
                        "forma_farmaceutica" => $vcpMercan['FormaFarmaceutica'] ?? '---',
                        "condiciones_esp_transp" => $vcpMercan['CondicionesEspTransp'] ?? '---',
                        "registro_sanitario_folio_autorizacion" => $vcpMercan['RegistroSanitarioFolioAutorizacion'] ?? '---',
                        "permiso_importacion" => $vcpMercan['PermisoImportacion'] ?? '---',
                        "folio_impovucem" => $vcpMercan['FolioImpoVUCEM'] ?? '---',
                        "numcas" => $vcpMercan['NumCAS'] ?? '---',
                        "razon_social_emp_imp" => $vcpMercan['RazonSocialEmpImp'] ?? '---',
                        "num_reg_san_plag_cofepris" => $vcpMercan['NumRegSanPlagCOFEPRIS'] ?? '---',
                        "datos_fabricante" => $vcpMercan['DatosFabricante'] ?? '---',
                        "datos_formulador" => $vcpMercan['DatosFormulador'] ?? '---',
                        "datos_maquilador" => $vcpMercan['DatosMaquilador'] ?? '---',
                        "uso_autorizado" => $vcpMercan['UsoAutorizado'] ?? '---',
                        "peso_enkg" => $vcpMercan['PesoEnKg'] ?? 0,
                        "valor_mercancia" => $vcpMercan['ValorMercancia'] ?? 0,
                        "moneda" => $vcpMercan['Moneda'] ?? '---',
                        "fraccion_arancelaria" => $vcpMercan['FraccionArancelaria'] ?? '---',
                        "uuid_comercio_ext" => $vcpMercan['UUIDComercioExt'] ?? '---',
                        "tipo_materia" => $vcpMercan['TipoMateria'] ?? '---',
                        "descripcion_materia" => $vcpMercan['DescripcionMateria'] ?? '---'
                      ));
                      $cporte_merc_det_id = DB::table("carta_porte_mercancia_detalle")->where("carta_porte_mercancia_detalle_token",$cporte_merc_det_token)->value("id");

                      foreach ($vLMerc['DocumentacionAduanera'] as $vDocAdu) {//
                        DB::table('carta_porte_mercancia_doc_aduanera')
                        ->insert(array(
                          "mercancias_totales" => $mercancias_totales_id,
                          "mercancia_detalle" => $cporte_merc_det_id,
                          "tipo_documento" => $vDocAdu['TipoDocumento'] ?? '---',
                          "num_pedimento" => $vDocAdu['NumPedimento'] ?? '---',
                          "ident_doc_aduanero" => $vDocAdu['IdentDocAduanero'] ?? '---',
                          "rfc_impo" => $vDocAdu['RFCImpo'] ?? '---'
                        ));
                      }
                      
                      foreach ($vLMerc['GuiasIdentificacion'] as $vGuia) {
                        DB::table('carta_porte_mercancia_guia_identificacion')
                        ->insert(array(
                          "mercancias_totales" => $mercancias_totales_id,
                          "mercancia_detalle" => $cporte_merc_det_id,
                          "numero_guia_identificacion" => $vGuia['NumeroGuiaIdentificacion'] ?? '---',
                          "descrip_guia_identificacion" => $vGuia['DescripGuiaIdentificacion'] ?? '---',
                          "peso_guia_identificacion" => $vGuia['PesoGuiaIdentificacion'] ?? 0
                        ));
                      }
                      
                      foreach ($vLMerc['CantidadTransporta'] as $vCantTr) {
                        DB::table('carta_porte_mercancia_cantidad_transporta')
                        ->insert(array(
                          "mercancias_totales" => $mercancias_totales_id,
                          "mercancia_detalle" => $cporte_merc_det_id,
                          "cantidad" => $vCantTr['Cantidad'] ?? 0,
                          "id_origen" => $vCantTr['IDOrigen'] ?? '---',
                          "id_destino" => $vCantTr['IDDestino'] ?? '---',
                          "cves_transporte" => $vCantTr['CvesTransporte'] ?? '---',
                        ));
                      }
                      
                      foreach ($vLMerc['DetalleMercancia'] as $vDetMr) {
                        DB::table('carta_porte_mercancia_detalle_mercancia')
                        ->insert(array(
                          "mercancias_totales" => $mercancias_totales_id,
                          "mercancia_detalle" => $cporte_merc_det_id,
                          "unidad_pesomerc" => $vDetMr['UnidadPesoMerc'] ?? '---',
                          "peso_bruto" => $vDetMr['PesoBruto'] ?? 0,
                          "peso_neto" => $vDetMr['PesoNeto'] ?? 0,
                          "peso_tara" => $vDetMr['PesoTara'] ?? 0,
                          "num_piezas" => $vDetMr['NumPiezas'] ?? 0,
                        ));
                      }
                      
                      foreach ($vLMerc['DescripcionesEspecificas'] as $vDesEs) {
                        DB::table('carta_porte_mercancia_descripciones_especificas')
                        ->insert(array(
                          "mercancias_totales" => $mercancias_totales_id,
                          "mercancia_detalle" => $cporte_merc_det_id,
                          "marca" => $vDesEs['Marca'] ?? '---',
                          "modelo" => $vDesEs['Modelo'] ?? '---',
                          "submodelo" => $vDesEs['SubModelo'] ?? '---',
                          "numeroserie" => $vDesEs['NumeroSerie'] ?? '---',
                        ));
                      }
                    }
                  }
                  
                  foreach ($cfdi_complemento_carta_porte['Autotransporte'] as $vAutotr) {
                    $autotransporte_token = Str::uuid()->toString();
                    DB::table('carta_porte_autotransporte')
                    ->insert(array(
                      "autotransporte_token" => $autotransporte_token,
                      "carta_porte" => $carta_porte_id,
                      "perm_sct" => $vAutotr['PermSCT'] ?? '---',
                      "num_permiso_sct" => $vAutotr['NumPermisoSCT'] ?? '---',

                      "config_vehicular" => $vAutotr['ConfigVehicular'] ?? '---',
                      "peso_bruto_vehicular" => $vAutotr['PesoBrutoVehicular'] ?? '---',
                      "placa_vm" => $vAutotr['PlacaVM'] ?? '---',
                      "anio_modelo_vm" => $vAutotr['AnioModeloVM'] ?? '---',

                      "asegura_resp_civil" => $vAutotr['AseguraRespCivil'] ?? '---',
                      "poliza_resp_civil" => $vAutotr['PolizaRespCivil'] ?? '---',
                      "asegura_med_ambiente" => $vAutotr['AseguraMedAmbiente'] ?? '---',
                      "poliza_med_ambiente" => $vAutotr['PolizaMedAmbiente'] ?? '---',
                      "asegura_carga" => $vAutotr['AseguraCarga'] ?? '---',
                      "poliza_carga" => $vAutotr['PolizaCarga'] ?? '---',
                      "prima_seguro" => $vAutotr['PrimaSeguro'] ?? '0.00'
                    ));
                    $autotransporte_id = DB::table('carta_porte_autotransporte')->where("autotransporte_token",$autotransporte_token)->value("id");
                    
                    foreach ($vAutotr['Remolques'] as $vRemol) {
                      DB::table('carta_porte_remolques')
                      ->insert(array(
                        "autotransporte" => $autotransporte_id,
                        "sub_tipo_rem" => $vRemol['SubTipoRem'] ?? '---',
                        "placa" => $vRemol['Placa'] ?? '---'
                      ));
                      
                    }
                  }

                  foreach ($cfdi_complemento_carta_porte['TransporteMaritimo'] as $vcpTrMar) {
                    $transporte_maritimo_token = Str::uuid()->toString();
                    DB::table('carta_porte_transporte_maritimo')//cfdi__estructura
                    ->insert(array(
                      "transporte_maritimo_token" => $transporte_maritimo_token,
                      "carta_porte" => $carta_porte_id,
                      "perm_sct" => $vcpTrMar['PermSCT'] ?? '---',
                      "num_permiso_sct" => $vcpTrMar['NumPermisoSCT'] ?? '---',
                      "nombre_aseg" => $vcpTrMar['NombreAseg'] ?? '---',
                      "num_poliza_seguro" => $vcpTrMar['NumPolizaSeguro'] ?? '---',
                      "tipo_embarcacion" => $vcpTrMar['TipoEmbarcacion'] ?? '---',
                      "matricula" => $vcpTrMar['Matricula'] ?? '---',
                      "numero_omi" => $vcpTrMar['NumeroOMI'] ?? '---',
                      "anio_embarcacion" => $vcpTrMar['AnioEmbarcacion'] ?? '---',
                      "nombre_embarc" => $vcpTrMar['NombreEmbarc'] ?? '---',
                      "nacionalidad_embarc" => $vcpTrMar['NacionalidadEmbarc'] ?? '---',
                      "unidades_de_arq_bruto" => $vcpTrMar['UnidadesDeArqBruto'] ?? '---',
                      "tipo_carga" => $vcpTrMar['TipoCarga'] ?? '---',
                      "eslora" => $vcpTrMar['Eslora'] ?? '---',
                      "manga" => $vcpTrMar['Manga'] ?? '---',
                      "calado" => $vcpTrMar['Calado'] ?? '---',
                      "puntal" => $vcpTrMar['Puntal'] ?? '---',
                      "linea_naviera" => $vcpTrMar['LineaNaviera'] ?? '---',
                      "nombre_agente_naviero" => $vcpTrMar['NombreAgenteNaviero'] ?? '---',
                      "num_autorizacion_naviero" => $vcpTrMar['NumAutorizacionNaviero'] ?? '---',
                      "num_viaje" => $vcpTrMar['NumViaje'] ?? '---',
                      "num_conoc_embarc" => $vcpTrMar['NumConocEmbarc'] ?? '---',
                      "permiso_temp_navegacion" => $vcpTrMar['PermisoTempNavegacion'] ?? '---',
                    ));
                    $transporte_maritimo_id = DB::table("carta_porte_transporte_maritimo")->where("transporte_maritimo_token",$transporte_maritimo_token)->value("id");
                    
                    foreach ($vcpTrMar['ContenedorM'] as $vMConten) {
                      $transporte_maritimo_token = Str::uuid()->toString();
                      DB::table('carta_porte_transporte_maritimo_contenedorm')//cfdi__estructura
                      ->insert(array(
                        "transporte_maritimo" => $transporte_maritimo_id,
                        "tipo_contenedor" => $vMConten['TipoContenedor'] ?? '---',
                        "matricula_contenedor" => $vMConten['MatriculaContenedor'] ?? '---',
                        "num_precinto" => $vMConten['NumPrecinto'] ?? '---',
                        "idccp_relacionado" => $vMConten['IdCCPRelacionado'] ?? '---',
                        "placa_vmccp" => $vMConten['PlacaVMCCP'] ?? '---',
                        "fecha_certificacion_ccp" => $vMConten['FechaCertificacionCCP'] ?? '---',
                      ));
                    }
                  }
                  
                  foreach ($cfdi_complemento_carta_porte['TransporteAereo'] as $vcpTrAir) {
                    DB::table('carta_porte_transporte_aereo')//cfdi__estructura
                    ->insert(array(
                      "carta_porte" => $carta_porte_id,
                      "perm_sct" => $vcpTrAir['PermSCT'] ?? '---',
                      "num_permiso_sct" => $vcpTrAir['NumPermisoSCT'] ?? '---',
                      "matricula_aeronave" => $vcpTrAir['MatriculaAeronave'] ?? '---',
                      "nombre_aseg" => $vcpTrAir['NombreAseg'] ?? '---',
                      "num_poliza_seguro" => $vcpTrAir['NumPolizaSeguro'] ?? '---',
                      "numero_guia" => $vcpTrAir['NumeroGuia'] ?? '---',
                      "lugar_contrato" => $vcpTrAir['LugarContrato'] ?? '---',
                      "codigo_transportista" => $vcpTrAir['CodigoTransportista'] ?? '---',
                      "rfc_embarcador" => $vcpTrAir['RFCEmbarcador'] ?? '---',
                      "num_reg_id_trib_embarc" => $vcpTrAir['NumRegIdTribEmbarc'] ?? '---',
                      "residencia_fiscal_embarc" => $vcpTrAir['ResidenciaFiscalEmbarc'] ?? '---',
                      "nombre_embarcador" => $vcpTrAir['NombreEmbarcador'] ?? '---'
                    ));
                  }

                  foreach ($cfdi_complemento_carta_porte['TransporteFerroviario'] as $vcpTrFerro) {
                    $transporte_ferroviario_token = Str::uuid()->toString();
                    DB::table('carta_porte_transporte_ferroviario')//cfdi__estructura
                    ->insert(array(
                      "transporte_ferroviario_token" => $transporte_ferroviario_token,
                      "carta_porte" => $carta_porte_id,
                      "tipo_de_servicio" => $vcpTrFerro['TipoDeServicio'] ?? '---',
                      "tipo_de_trafico" => $vcpTrFerro['TipoDeTrafico'] ?? '---',
                      "nombre_aseg" => $vcpTrFerro['NombreAseg'] ?? '---',
                      "num_poliza_seguro" => $vcpTrFerro['NumPolizaSeguro'] ?? '---'
                    ));
                    $transporte_ferroviario_id = DB::table('carta_porte_transporte_ferroviario')->where("transporte_ferroviario_token",$transporte_ferroviario_token)->value("id");

                    foreach ($vcpTrFerro['DerechosDePaso'] as $vFerroDere) {
                      DB::table('carta_porte_transporte_ferroviario_derechos_de_paso')
                      ->insert(array(
                        "transporte_ferroviario" => $transporte_ferroviario_id,
                        "carta_porte" => $carta_porte_id,
                        "tipo_de_servicio" => $vFerroDere['TipoDerechoDePaso'] ?? '---',
                        "tipo_de_trafico" => $vFerroDere['KilometrajePagado'] ?? '---'
                      ));
                    }

                    foreach ($vcpTrFerro['Carro'] as $vFerroCarro) {
                      $transporte_ferroviario_carro_token = Str::uuid()->toString();
                      DB::table('carta_porte_transporte_ferroviario_carro')
                      ->insert(array(
                        "transporte_ferroviario_carro_token" => $transporte_ferroviario_carro_token,
                        "transporte_ferroviario" => $transporte_ferroviario_id,
                        "tipo_carro" => $vFerroCarro['TipoCarro'] ?? '---',
                        "matricula_carro" => $vFerroCarro['MatriculaCarro'] ?? '---',
                        "guia_carro" => $vFerroCarro['GuiaCarro'] ?? '---',
                        "toneladas_netas_carro" => $vFerroCarro['ToneladasNetasCarro'] ?? '---'
                      ));
                      $transporte_ferroviario_carro_id = DB::table('carta_porte_transporte_ferroviario_carro')->where("transporte_ferroviario_carro_token",$transporte_ferroviario_carro_token)->value("id");
                      
                      foreach ($vFerroCarro['Contenedor'] as $vCarroContenedor) {
                        $transporte_ferroviario_carro_token = Str::uuid()->toString();
                        DB::table('carta_porte_transporte_ferroviario_carro_contenedor')
                        ->insert(array(
                          "transporte_ferroviario_carro" => $transporte_ferroviario_carro_id,
                          "tipo_contenedor" => $vCarroContenedor['TipoContenedor'] ?? '---',
                          "peso_contenedor_vacio" => $vCarroContenedor['PesoContenedorVacio'] ?? '---',
                          "peso_neto_mercancia" => $vCarroContenedor['PesoNetoMercancia'] ?? '---'
                        ));
                      }
                    }
                  }
                  
                  foreach ($cfdi_complemento_carta_porte['PartesTransporte'] as $vcpTrPartes) {
                    DB::table('carta_porte_partes_transporte')
                    ->insert(array(
                      "carta_porte" => $carta_porte_id,
                      "parte_transporte" => $vcpTrPartes['ParteTransporte'] ?? '---',
                      "id_partes_transporte" => $vcpTrPartes['IdPartesTransporte'] ?? '---'
                    ));
                  }
          
                  foreach ($cfdi_complemento_carta_porte['FiguraTransporte'] as $figTransp) {
                    DB::table('carta_porte_figura_transporte')
                    ->insert(array(
                      "carta_porte" => $carta_porte_id,
                      "tipo_figura" => $figTransp['TipoFigura'] ?? '---',
                      "rfc_figura" => $figTransp['RFCFigura'] ?? '---',
                      "num_licencia" => $figTransp['NumLicencia'] ?? '---',
                      "nombre_figura" => $figTransp['NombreFigura'] ?? '---',
                      "num_reg_id_trib_figura" => $figTransp['NumRegIdTribFigura'] ?? '---',
                      "residencia_fiscal_figura" => $figTransp['ResidenciaFiscalFigura'] ?? '---',
                    ));
                  }
                }

                if (!$insertCFDIVincBuy) {
                  throw new \Exception("Error al guardar vinculación de comprobante fiscal a compra.");
                } 

                if ($request->hasFile('compra_anexos')) {
                  $anexos = $request->file('compra_anexos');
                
                  // 1. Rendimiento: Consultamos el folio una sola vez fuera del ciclo
                  $conteoActual = DB::table("sos_documentos")->where('folio_modulo', 'LIKE', 'BUY-ANEX%')->count();
                  $folioSiguiente = $conteoActual + 1;
                  
                  foreach ($anexos as $archivo) {
                    if ($archivo && $archivo->isValid()) {
                      // 2. Definición de nombre original
                      $nombreOriginal = $archivo->getClientOriginalName();
                        
                      // Usamos el nombre original directamente ya que $filepath es único por compra
                      $nombreFisico = $nombreOriginal;
            
                      // 3. Guardado físico en el storage
                      $storagePath = "/public/root/" . $filepath;
                      $saveFile = Storage::putFileAs($storagePath, $archivo, $nombreFisico);
            
                      if (!$saveFile) {
                        throw new \Exception("Error al guardar el archivo físico: $nombreOriginal");
                      }
            
                      // 4. Preparar datos y generar Token
                      $folioModulo = "BUY-ANEX" . $folioSiguiente;
                      $tokenDoc = $JwtAuth->encriptarToken($obtenCompra, $nombreOriginal, $folioSiguiente);
            
                      // 5. Inserción en base de datos
                      $insertDoc = DB::table("sos_documentos")->insert([
                        "token_documento"  => $tokenDoc,
                        "fecha_carga"      => time(),
                        "modulo"           => "pagos",
                        "folio_modulo"     => $folioModulo,
                        "tipo_documento"   => "an",
                        "nombre_documento" => $JwtAuth->encriptar($nombreOriginal),
                        "compra"           => $obtenCompra,
                        "status_documento" => true,
                      ]);
            
                      if (!$insertDoc) {
                        throw new \Exception("Error al registrar el anexo $nombreOriginal en la base de datos.");
                      }
          
                      // Incrementamos para el siguiente archivo
                      $folioSiguiente++;
                    }
                  }
                }

                // Productos y Servicios de la Empresa
                $productos = DB::table("in_egr_catalogo_productos")
                ->where(["admin_empresa" => $vEmp->id, "status" => true])
                ->pluck("id", "token_cat_productos")->toArray();

                $servicios = DB::table("in_egr_catalogo_servicios")
                ->where(["administrador" => $vEmp->id, "status" => true])
                ->pluck("id", "token_cat_servicios")->toArray();

                // Servicios GLOBALES (Activos Fijos/Diferidos)
                // Buscamos los tokens de esos folios especiales
                $globales = DB::table('in_egr_catalogo_servicios')
                ->whereIn('folio_sistema', ['999999998', '999999999'])
                ->whereNull('administrador')
                ->select('id', 'token_cat_servicios', 'folio_sistema')
                ->get()
                ->keyBy('token_cat_servicios');
                // --- 2. DETECTAR CONTENIDO ANTES O DURANTE EL CICLO ---
                $tieneProductos = false;
                $tieneServicios = false;
                
                foreach ($cfdi_conceptos as $oDetHave) {
                  $tokenArticulo = $oDetHave['articulo_homologado_token'];
                  
                  if (isset($productos[$tokenArticulo])) {
                    $tieneProductos = true;
                  } elseif (isset($servicios[$tokenArticulo])) {
                    $tieneServicios = true;
                  } elseif (isset($globales[$tokenArticulo])) {
                    $global = $globales[$tokenArticulo];
                    // Folio 999999998 = Activo Fijo (Entra a almacén/inventario de activos)
                    // Folio 999999999 = Activo Diferido (Gasto que se devenga)
                    if ($global->folio_sistema == '999999998') {
                      $tieneProductos = true; 
                    } else {
                      $tieneServicios = true;
                    }
                  }
                }
                
                $validate_insert_ord_pago = false;
                $orden_de_pago_vinculada = "";
                if ($vEmp->jerarquia_main == 'P') {
                  $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

                  $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra);
                  $orden_de_pago_vinculada = $tknOrder;
                  $orderpay = new OrdenPagoModelo();
                  $orderpay->token_ordenPago = $tknOrder;
                  $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                  $orderpay->fecha_sistema_ordenp = $fechaSistema;
                  //$orderpay->fecha_contabilizacion_ordenPago = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor;
                  $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orderpay->orden_bloqueada = $compra_pagar == "pagar" || $classRecibeArtPago ? FALSE : TRUE;
                  $orderpay->autorizacion_pay = $compra_pagar == "pagar" ? TRUE : FALSE;
                  $orderpay->fecha_autorizacion_pay = $compra_pagar == "pagar" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL;
                  $orderpay->tentativa_pago = $compra_pagar == "pagar" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL;
                  $orderpay->orden_terminada_bool = FALSE;
                  $orderpay->orden_terminada_fecha = NULL;
                  $orderpay->status_ordenPago = TRUE;  //cifrado
                  $orderpay->empresa = $vEmp->id; //cifrado
                  $orderpay->comprador = $vEmp->userr; //cifrado
                  $insertOrder = $orderpay->save();
                  if (!$insertOrder) {
                    throw new \Exception("Error al guardar orden de pago de compra.");
                  }
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                  if ($insertOrder) {
                    $validate_insert_ord_pago = true;
                  }
                }

                if ($vEmp->jerarquia_main == 'P' && $tieneProductos) {
                  //$folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
                  //  ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                  //  WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$empresa, $usuario]);
  
                  $maxFolioOrdenRecep = DB::table('eegr_compras_orden_recepcion')
                  ->where('empresa', $vEmp->id)
                  ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
                  ->max('folio_recepcion');
  
                  $folioRecepcionOrden = $maxFolioOrdenRecep ? $maxFolioOrdenRecep + 1 : 1;
  
                  $orden_recept = new OrdenRecepcionModelo();
                  $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
                  $orden_recept->folio_recepcion = $folioRecepcionOrden;//$folioRecepcionOrden[0]->folio;
                  $orden_recept->fecha_recepcion = $tentativa_recepcion_compra;
                  $orden_recept->fecha_contabilizacion_recep = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orden_recept->proveedor = $idProveedor;
                  $orden_recept->orden_compra = $obtenCompra;
                  $orden_recept->almacen = $idRecepcionLugar;
                  $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                  $orden_recept->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                  $orden_recept->observaciones = NULL;
                  $orden_recept->empresa = $vEmp->id; //cifrado
                  $newOrderRecept = $orden_recept->save();
                  if (!$newOrderRecept) {
                    throw new \Exception("Error al guardar orden de recepción de compra.");
                  }
                }
  
                if ($vEmp->jerarquia_main == 'P' && $tieneServicios) {
                  //$folioDevengacionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_devengacion) + 1, 1) AS folio FROM eegr_compras_orden_devengacion AS ord_rec JOIN main_empresas AS emp 
                  //  ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                  //  WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$empresa, $usuario]);
  
                  $maxFolioOrdenDeven = DB::table('eegr_compras_orden_devengacion')
                  ->where('empresa', $vEmp->id)
                  ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
                  ->max('folio_devengacion');
  
                  $folioDevengacionOrden = $maxFolioOrdenDeven ? $maxFolioOrdenDeven + 1 : 1;
  
                  $orden_deven = new OrdenDevengacionModelo();
                  $orden_deven->uuid_orden_devengacion = Str::uuid()->toString();
                  $orden_deven->folio_devengacion = $folioDevengacionOrden;//$folioDevengacionOrden[0]->folio;
                  $orden_deven->fecha_devengacion = $tentativa_recepcion_compra;
                  $orden_deven->proveedor = $idProveedor;
                  $orden_deven->orden_compra = $obtenCompra;
                  $orden_deven->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                  $orden_deven->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                  $orden_deven->observaciones = NULL;
                  $orden_deven->empresa = $vEmp->id; //cifrado
                  $newOrderDeven = $orden_deven->save();
                  if (!$newOrderDeven) {
                    throw new \Exception("Error al guardar orden de recepción de compra.");
                  }
                }

                if ($anticipo_aplicado > 0) {
                  $this->registraAnticipoCompra($JwtAuth,$token_proveedor,$vEmp->id,$usuario,$vEmp->userr,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$cfdi_comprobante_tipo_de_cambio,$cfdi_comprobante_moneda,$orden_de_pago_vinculada);
                }

                $contadorDetallecompra = 0;
                foreach ($cfdi_conceptos as $vDet) {
                  $tokenArticulo = $vDet['articulo_homologado_token'];
                  $identificador = $vDet['articulo_homologado_identificador'];
                  $precioUnitario = $vDet['ValorUnitario'];
                  $cantidad = $vDet['Cantidad'];
                  $usoArticulo = $vDet['articulo_homologado_uso'];
                  $prorratea = $vDet['articulo_homologado_prorratea'];

                  //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                  $selectDetBuy = $this->registraArticuloCompra($JwtAuth,$vDet,$vEmp->id,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio);
                  $this->registraArticuloCFDICompra($vDet,$obtenCompra,$comprobante_fiscal_reg,$vEmp->id);
                  
                  $id_producto = $productos[$tokenArticulo] ?? null;
                  $id_servicio = $servicios[$tokenArticulo] ?? null;
                  $global      = $globales->get($tokenArticulo);
                  
                  if ($id_producto) {
                    $catProdServ = 'Producto';
                    $articulo_id = $id_producto;
                  } elseif ($id_servicio) {
                    $catProdServ = 'Servicio';
                    $articulo_id = $id_servicio;
                  } elseif ($global) {
                    // Si el folio es 999999998 es Fijo, si es 999999999 es Diferido
                    $catProdServ = ($global->folio_sistema == '999999998') ? 'ActivoFijo' : 'ActivoDiferido';
                    $articulo_id = $global->id;
                  } else {
                    $catProdServ = null; // Token no encontrado en ningún catálogo
                    throw new \Exception("El artículo con token $tokenArticulo no se encuentra en ningún catálogo.");
                  }

                  if ($prorratea) {
                    $this->procesarProrrateo($JwtAuth, $vEmp, $id_producto, $id_servicio, $obtenCompra, $selectDetBuy, $fecha_contabilizacion);
                  }

                  if ($catProdServ == 'Producto') {
                    $this->procesarKardexProducto($JwtAuth, $vEmp, $id_producto, $tokenArticulo, $obtenCompra, $selectDetBuy, $cantidad, $precioUnitario, $tokenCompra);
                  }

                  if ($catProdServ == 'Servicio') {
                    $upDateServicio = DB::table('in_egr_catalogo_servicios')
                    ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'in_egr_catalogo_servicios.status' => TRUE,
                      'in_egr_catalogo_servicios.id' => $id_servicio,
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario,
                    ])
                    ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));
                  }

                  if ($usoArticulo == 'activo_fijo') {
                    $this->procesarActivoFijo($JwtAuth,$selectDetBuy,$vDet,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$vEmp->id);
                  }

                  if ($usoArticulo == 'activo_diferido') {
                    $this->procesarActivoDiferido($JwtAuth,$selectDetBuy,$vDet,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$vEmp->id);
                  }

                  ++$contadorDetallecompra;
                }
                
                if (isset($xmlFile)) Storage::putFileAs("/public/root/" . $filepath,$xmlFile,$nombreFisicoXML);
                if (isset($pdfFile)) Storage::putFileAs("/public/root/" . $filepath, $pdfFile, $nombreFisicoPDF);
                if (isset($verifFile)) Storage::putFileAs("/public/root/" . $filepath, $verifFile, $nombreFisicoVerif);
                // 6. Si llegamos aquí, todo es correcto
                DB::commit();
                $JwtAuth->insertBitacoraActividad(
                  'egresos',
                  'compras',
                  'compras',
                  $folio_buy,
                  'registro en el alta de compras',
                  $empresa,
                  $usuario
                );

                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table('sos_last_folders')
                    ->insert(
                      array(
                        "egr_compras" => TRUE,
                        "folder" => 1,
                        "post_folder" => $post_folio,
                        "empresa" => $vEmp->id,
                      )
                    );
                } else {
                  $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                    ->where([
                      'sos_last_folders.egr_compras' => TRUE,
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario,
                    ])
                    ->limit(1)->update(
                      array(
                        'sos_last_folders.folder' => $folio_nuevo,
                        'sos_last_folders.post_folder' => $post_folio,
                      )
                    );
                }

                $dataMensaje = array(
                  'message' => 'Compra registrada y autorizada con el folio '.$folio_buy.($validate_insert_ord_pago ? ', revise ordenes de pago' : ''),
                  'code' => 200,
                  'status' => 'success',
                  'token_compras' => $compra_pagar == "pagar" ? $tokenCompra : null,
                  'token_proveedor' => $compra_pagar == "pagar" ? $token_proveedor : null,
                  'token_ordenPago' => $compra_pagar == "pagar" ? $orden_de_pago_vinculada : null,
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 422,
                  'message' => 'Esta compra no fue terminada debido a errores internos'
                );
              }

            } catch (\Exception $e) {
              // 7. Si algo falla, revertimos TODO en la BD
              DB::rollBack();
              // Opcional: Borrar carpetas físicas creadas en este intento
              // Storage::disk('root')->deleteDirectory($filepath);
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en el registro: ' . $e->getMessage(),
                'line' => $e->getLine()
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => $detalleErrores
          );
        }
        //$dataMensaje = array('status' => 'error','code' => 200,'message' => 'mensaje_error_main'.$receptFactura);
      } else {
        $mensaje_error_main = '';
        if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
        if (!$validate_fecha_contabilizacion) {$mensaje_error_main = 'Error en fecha de contabilización, verifique su información o comuniquese a soporte';}
        if (!$validate_fecha_vencimiento) {$mensaje_error_main = 'Error en fecha de vencimiento, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_comprobante) {$mensaje_error_main = 'Error en nodo comprobante de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_total) {$mensaje_error_main = 'Error en total de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_emisor) {$mensaje_error_main = 'Error en nodo emisor de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_prov) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_receptor) {$mensaje_error_main = 'Error en nodo receptor de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_impuestos_retenidos) {$mensaje_error_main = 'Error en impuestos retenidos de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_impuestos_trasladados) {$mensaje_error_main = 'Error en impuestos trasladados de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_complemento) {$mensaje_error_main = 'Error en nodo complemento de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_compra_contado_credito) {$mensaje_error_main = 'Error en seleccion de compra a crédito o contado, verifique su información o comuniquese a soporte';}
        if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información o comuniquese a soporte';}
        //if (!$validate_tipoLugarEntrega) {$mensaje_error_main = 'No se encontro respuesta a seleccion de lugar de entrega sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!file_exists($request->file('imagenEvidenciaXMl'))) {$mensaje_error_main = 'Debe cargar la factura en formato xml correspondiente a esta compra';}
        if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function registrarCompraValidaTrasladoConceptos($cfdi_conceptos,$moneda_decimales,$JwtAuth){
    $detalleErrores = "";
    foreach ($cfdi_conceptos as $vDet) {
      $concepto = $vDet['Descripcion'];
      $precioUnitario = $vDet['ValorUnitario'];
      $cantidad = $vDet['Cantidad'];
      //return response()->json(['status' => 'error','code' => 200,'message' => $cantidad]);
      $descuentoXUni = $vDet['Descuento'];
      $iva = $vDet['articulo_homologado_iva'];
      $retenciones = $vDet['retenciones'];
      $traslados = $vDet['traslados'];
      $efectoFiscalArticulo = $vDet['articulo_homologado_efecto_fiscal'];
      $activoFijo = $vDet['articulo_homologado_activoFijo'];
      $activoIntangible = $vDet['articulo_homologado_activoDiferido'];
      $prorratea = $vDet['articulo_homologado_prorratea'];

      $importe = $JwtAuth->rellenaImportesCompras($vDet['Importe']);
      $validateActivos = false;
      $validatePeriodicidad = false;
      $validateDescuentos = false;
      $validateDecimalesMoneda = false;
      $validateForImpuRetenciones = false;
      $validateForImpuTraslados = false;

      $vItem_tokenArticulo = isset($concepto) && !empty($concepto);
      $vItem_precioUnitario = $precioUnitario != '' && $precioUnitario == 0;
      $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($JwtAuth->filtroCostoPrecio(), $cantidad);
      $vItem_descuento = $descuentoXUni != '' && $descuentoXUni == 0;
      //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
      $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
      //$vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
      $vItem_importe = isset($importe) && !empty($importe) && preg_match($JwtAuth->filtroCostoPrecio(), $importe);

      if (!$vItem_descuento) {
        $detalleErrores = 'La cantidad de descuento es invalida o inexistente';
        break;
      }
      if (!$vItem_tokenArticulo) {
        $detalleErrores = 'producto/servicio '.$concepto.' invalidado';
        break;
      }
      if (!$vItem_precioUnitario) {
        $detalleErrores = 'El precio unitario del producto/servicio '.$concepto.' es invalido o inexistente';
        break;
      }
      if (!$vItem_cantidad) {
        $detalleErrores = 'La cantidad del producto/servicio '.$concepto.' es invalida o inexistente';
        break;
      }
      if (!$vItem_importe) {
        $detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';
        break;
      }
    }
    return $detalleErrores;
  }

  public function cargarCfdiTraslado(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'fecha_contabilizacion' => 'required|string',
      'cfdi_comprobante' => 'required|json',
      'cfdi_relacionados' => 'nullable|json',
      'cfdi_emisor' => 'required|json',
      'cfdi_receptor' => 'required|json',
      'cfdi_conceptos' => 'required|json',
      'cfdi_complemento' => 'required|json',//*
      'cfdi_complemento_carta_porte' => 'nullable|json',
      'compras_seleccionadas' => 'required|json',
      'observaciones' => 'nullable|string'
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
      $mi_llave_secreta = env('JWT_BUY_ID_SECRET');
      $permisosCreacion = $JwtAuth->permisosCreacion($mi_llave_secreta,$empresa,$usuario);
      $moneda_decimales = 0;
      
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $cfdi_comprobante = json_decode($request->input('cfdi_comprobante'), true);
      $cfdi_relacionados = json_decode($request->input('cfdi_relacionados'), true);
      $cfdi_emisor = json_decode($request->input('cfdi_emisor'), true);
      $cfdi_receptor = json_decode($request->input('cfdi_receptor'), true);
      $cfdi_conceptos = json_decode($request->input('cfdi_conceptos'), true);
      $cfdi_complemento = json_decode($request->input('cfdi_complemento'), true);
      $cfdi_complemento_carta_porte = json_decode($request->input('cfdi_complemento_carta_porte'), true);
      $compras_seleccionadas = json_decode($request->input('compras_seleccionadas'), true);
      $observaciones = $request->input('observaciones');

      //echo $empresa;exit;

      $OKFechaContab = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKCfdiComprobante = isset($cfdi_comprobante) && !empty($cfdi_comprobante) && is_array($cfdi_comprobante);
      $OKCfdiRelacionados = isset($cfdi_relacionados) && !empty($cfdi_relacionados) && is_array($cfdi_relacionados);
      $OKCfdiEmisor = isset($cfdi_emisor) && !empty($cfdi_emisor) && is_array($cfdi_emisor);
      $OKCfdiReceptor = isset($cfdi_receptor) && !empty($cfdi_receptor) && is_array($cfdi_receptor);
      $OKCfdiConceptos = isset($cfdi_conceptos) && !empty($cfdi_conceptos) && is_array($cfdi_conceptos);
      $OKCfdiComplemento = isset($cfdi_complemento) && !empty($cfdi_complemento) && is_array($cfdi_complemento);
      $OKCfdiCartaPorte = isset($cfdi_complemento_carta_porte) && !empty($cfdi_complemento_carta_porte) && is_array($cfdi_complemento_carta_porte);
      $OKComprasSeleccionadas = isset($compras_seleccionadas) && !empty($compras_seleccionadas) && is_array($compras_seleccionadas);
      $OKObservaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);
      
      if ($permisosCreacion && $OKFechaContab && $OKCfdiComprobante && $OKCfdiEmisor && $OKCfdiReceptor && $OKCfdiConceptos && $OKCfdiComplemento && $OKCfdiCartaPorte && $OKComprasSeleccionadas && $OKObservaciones) {
        $moneda_decimales = $JwtAuth->getMonedaAPI($cfdi_comprobante['moneda'] ?? 'MXN') || 2;
        $detalleErrores = $this->registrarCompraValidaTrasladoConceptos($cfdi_conceptos,$moneda_decimales,$JwtAuth);
        //return response()->json(['status' => 'error','code' => 200,'message' => "fecha_contabilizacio_n ".Str::uuid()->toString()]);
        if ($detalleErrores != "") {
          return response()->json(['status' => 'error','message' => $detalleErrores], 200);
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

        if ($vEmp) {
          DB::beginTransaction();
          try {
            $maxFolioFiscalTraslado = DB::table('cfdi_comprobante_fiscal_traslado')
            ->where('cfdi_traslado_empresa', $vEmp->id)
            ->lockForUpdate()
            ->max('cfdi_traslado_folio');
  
            $folioFiscalTraslado = $maxFolioFiscalTraslado ? $maxFolioFiscalTraslado + 1 : 1;
            $folio_traslado = 'TRASLADO-'.$JwtAuth->generarFolio($folioFiscalTraslado);
            $fecha_contabilizacion_unix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $nombreDocs = $fecha_contabilizacion_unix."-".$folio_traslado;
            //use App\Models\CFDITrasladoModelo;
            $carta_porte_token = Str::uuid()->toString();
            //return response()->json(['status' => 'error','code' => 200,'message' => "fecha_contabilizacion $folioFiscalTraslado"]);
            $traslado = new CFDITrasladoModelo();
            $traslado->cfdi_traslado_token = $carta_porte_token;
            $traslado->cfdi_traslado_folio = $folioFiscalTraslado;
            $traslado->cfdi_traslado_fecha_contabilizacion = $fecha_contabilizacion_unix;

            //$traslado->cfdi_traslado_factura_xml = $tokenCompra;
            if ($request->hasFile('imagenEvidenciaXMl') && $request->file('imagenEvidenciaXMl')->isValid()) {
              $xmlFile = $request->file('imagenEvidenciaXMl');
              $nombreFisicoXML = $fecha_contabilizacion_unix . "-" . $folio_traslado . "_" . str_replace([' ', '#'], '_', $xmlFile->getClientOriginalName());
              $traslado->cfdi_traslado_factura_xml = $JwtAuth->encriptar($nombreFisicoXML);
            }

            //$traslado->cfdi_traslado_factura_pdf = $tokenCompra;
            //Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName()); 
            if ($request->hasFile('imagenEvidenciaPdf') && $request->file('imagenEvidenciaPdf')->isValid()) {
              $pdfFile = $request->file('imagenEvidenciaPdf');
              $nombreFisicoPDF = $fecha_contabilizacion_unix . "-" . $folio_traslado . "_" . str_replace([' ', '#'], '_', $pdfFile->getClientOriginalName());
              $traslado->cfdi_traslado_factura_pdf = $JwtAuth->encriptar($nombreFisicoPDF);
            }

            //$traslado->cfdi_traslado_evidenciaSAT = $tokenCompra;
            //Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName()) : NULL;
            if ($request->hasFile('imagenEvidenciaVerificacion') && $request->file('imagenEvidenciaVerificacion')->isValid()) {
              $verifFile = $request->file('imagenEvidenciaVerificacion');
              $nombreFisicoVerif = $fecha_contabilizacion_unix . "-" . $folio_traslado . "_" . str_replace([' ', '#'], '_', $verifFile->getClientOriginalName());
              $traslado->cfdi_traslado_evidenciaSAT = $JwtAuth->encriptar($nombreFisicoVerif);
            }

            $traslado->cfdi_traslado_observaciones = $JwtAuth->encriptar($observaciones);
            $traslado->cfdi_traslado_empresa = $vEmp->id;
            $traslado->cfdi_traslado_status = TRUE;
            //token_compras
  
            $trasladoInsert = $traslado->save(); 
  
            if (!$trasladoInsert) {
              throw new \Exception("Error al guardar la cabecera de la compra.");
            }

            if ($trasladoInsert) {
              $obtenTraslado = $traslado->id;
              $filepath = $vEmp->root_tkn . "/0002-cpp/compras/cfdi/traslados/$nombreDocs/";
              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }
              
              $cfdi_comprobante_folio = $cfdi_comprobante['folio'] ?? '---';
              $cfdi_complementoUUID = $cfdi_complemento['UUID'] ?? '---';
              //$idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");

              $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
              $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales')//cfdi__estructura
              ->insert(array(
                "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                "origen_proceso" => "compra",
                //"compra_vinculada" => $obtenCompra,
                "cfdi_comprobante_version" => $cfdi_comprobante['version'] ?? '---',
                "cfdi_comprobante_serie" => $cfdi_comprobante['serie'] ?? '---',
                "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
                "cfdi_comprobante_fecha" => $cfdi_comprobante['fecha'] ?? '---',
                "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante['forma_de_pago'] ?? '---',
                "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante['metodo_de_pago'] ?? '---',
                "cfdi_comprobante_subtotal" => $cfdi_comprobante['subtotal'] ?? '---',
                "cfdi_comprobante_moneda" => $cfdi_comprobante['moneda'] ?? '---',
                "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante['tipo_de_cambio'] ?? '1.00',
                "cfdi_comprobante_total" => $cfdi_comprobante['total'] ?? '---',
                "cfdi_comprobante_confirmacion" => $cfdi_comprobante['confirmacion'] ?? '---',
                "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante['tipo_de_comprobante'] ?? '---',
                "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante['lugar_de_expedicion'] ?? '---',
                "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante['no_de_certificado'] ?? '---',
                "cfdi_comprobante_sello" => $cfdi_comprobante['sello'] ?? '---',
                "cfdi_comprobante_certificado" => $cfdi_comprobante['certificado'] ?? '---',
                "cfdi_relacionados_tipo_de_relacion" => $cfdi_relacionados['tipo_de_relacion'] ?? '---',
                "cfdi_relacionados_uuid" => $cfdi_relacionados['UUID'] ?? '---',
                "cfdi_emisor_rfc" => $cfdi_emisor['rfc_del_emisor'] ?? '---',
                "cfdi_emisor_nombre" => $cfdi_emisor['nombre_del_emisor'] ?? '---',
                "cfdi_emisor_regimen_fiscal" => $cfdi_emisor['regimen_fiscal_del_emisor'] ?? '---',
                "cfdi_receptor_rfc" => $cfdi_receptor['rfc_del_receptor'] ?? '---',
                "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor['uso_del_cfdi'] ?? '---',
                "cfdi_complementoUUID" => $cfdi_complementoUUID,
                "cfdi_complementoFechaTimbrado" => $cfdi_complemento['FechaTimbrado'] ?? '---',
                "cfdi_complementoRfcProvCertif" => $cfdi_complemento['RfcProvCertif'] ?? '---',
                "cfdi_complementoNoCertificadoSAT" => $cfdi_complemento['NoCertificadoSAT'] ?? '---',
                "cfdi_complementoSelloCFD" => $cfdi_complemento['SelloCFD'] ?? '---',
                "cfdi_complementoSelloSAT" => $cfdi_complemento['SelloSAT'] ?? '---',
              ));
              if (!$insertCFDIEstructura) {
                throw new \Exception("Error al guardar comprobante fiscal.");
              }
              
              $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");

              if ($cfdi_complemento_carta_porte) {
                $carta_porte_token = Str::uuid()->toString();
                DB::table('comprobante_carta_porte')
                ->insert(array(
                  "carta_porte_token" => $carta_porte_token,
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  "version" => $cfdi_complemento_carta_porte['Version'] ?? '---',
                  "id_ccp" => $cfdi_complemento_carta_porte['IdCCP'] ?? '---',
                  "transp_internac" => $cfdi_complemento_carta_porte['TranspInternac'] ?? '---',
                  "regimen_aduanero" => $cfdi_complemento_carta_porte['RegimenAduanero'] ?? '---',
                  "entrada_salida_merc" => $cfdi_complemento_carta_porte['EntradaSalidaMerc'] ?? '---',
                  "pais_origen_destino" => $cfdi_complemento_carta_porte['PaisOrigenDestino'] ?? '---',
                  "via_entrada_salida" => $cfdi_complemento_carta_porte['ViaEntradaSalida'] ?? '---',
                  "total_dist_rec" => $cfdi_complemento_carta_porte['TotalDistRec'] ?? '---',
                  "registro_istmo" => $cfdi_complemento_carta_porte['RegistroISTMO'] ?? '---',
                  "ubicacion_polo_origen" => $cfdi_complemento_carta_porte['UbicacionPoloOrigen'] ?? '---',
                  "ubicacion_polo_destino" => $cfdi_complemento_carta_porte['UbicacionPoloDestino'] ?? '---',
                ));
                $carta_porte_id = DB::table('comprobante_carta_porte')->where("carta_porte_token",$carta_porte_token)->value("id");
                
                foreach ($cfdi_complemento_carta_porte['ubicaciones'] as $vcpUbica) {
                  DB::table('carta_porte_ubicaciones')//cfdi__estructura
                  ->insert(array(
                    "carta_porte" => $carta_porte_id,
                    "tipo_ubicacion" => $vcpUbica['TipoUbicacion'] ?? '---',//TipoUbicacion
                    "id_ubicacion" => $vcpUbica['IdUbicacion'] ?? '---',//IdUbicacion
                    "rfc_remitente_destinatario" => $vcpUbica['RFCRemitenteDestinatario'] ?? '---',//RFCRemitenteDestinatario
                    "nombre_remitente_destinatario" => $vcpUbica['NombreRemitenteDestinatario'] ?? '---',//NombreRemitenteDestinatario
                    "num_reg_id_trib" => $vcpUbica['NumRegIdTrib'] ?? '---',//NumRegIdTrib
                    "residencia_fiscal" => $vcpUbica['ResidenciaFiscal'] ?? '---',//ResidenciaFiscal
                    "num_estacion" => $vcpUbica['NumEstacion'] ?? '---',//NumEstacion
                    "nombre_estacion" => $vcpUbica['NombreEstacion'] ?? '---',//NombreEstacion
                    "navegacion_trafico" => $vcpUbica['NavegacionTrafico'] ?? '---',//NavegacionTrafico
                    "fecha_hora_salida_llegada" => $vcpUbica['FechaHoraSalidaLlegada'] ?? '---',//FechaHoraSalidaLlegada
                    "tipo_estacion" => $vcpUbica['TipoEstacion'] ?? '---',//TipoEstacion
                    "distancia_recorrida" => $vcpUbica['DistanciaRecorrida'] ?? '---',//DistanciaRecorrida
                    "calle" => $vcpUbica['Calle'] ?? '---',
                    "numero_exterior" => $vcpUbica['NumeroExterior'] ?? '---',
                    "numero_interior" => $vcpUbica['NumeroInterior'] ?? '---',
                    "colonia" => $vcpUbica['Colonia'] ?? '---',
                    "localidad" => $vcpUbica['Localidad'] ?? '---',
                    "referencia" => $vcpUbica['Referencia'] ?? '---',
                    "municipio" => $vcpUbica['Municipio'] ?? '---',
                    "estado" => $vcpUbica['Estado'] ?? '---',
                    "pais" => $vcpUbica['Pais'] ?? '---',
                    "codigo_postal" => $vcpUbica['CodigoPostal'] ?? '---',
                  ));
                }
                
                foreach ($cfdi_complemento_carta_porte['mercancias'] as $vcpMercan) {
                  $mercancias_totales_token = Str::uuid()->toString();
                  DB::table('carta_porte_mercancias_totales')
                  ->insert(array(
                    "mercancias_totales_token" => $mercancias_totales_token,
                    "carta_porte" => $carta_porte_id,
                    "peso_bruto_total" => $vcpMercan['PesoBrutoTotal'] ?? '---',
                    "unidad_peso" => $vcpMercan['UnidadPeso'] ?? '---',
                    "peso_neto_total" => $vcpMercan['PesoNetoTotal'] ?? '---',
                    "num_total_mercancias" => $vcpMercan['NumTotalMercancias'] ?? '---',
                    "cargo_por_tasacion" => $vcpMercan['CargoPorTasacion'] ?? '---',
                    "logistica_inversa_recoleccion_devolucion" => $vcpMercan['LogisticaInversaRecoleccionDevolucion'] ?? '---'
                  ));
                  $mercancias_totales_id = DB::table('carta_porte_mercancias_totales')->where("mercancias_totales_token",$mercancias_totales_token)->value("id");

                  foreach ($vcpMercan['Mercancia'] as $vLMerc) {
                    $cporte_merc_det_token = Str::uuid()->toString();
                    DB::table('carta_porte_mercancia_detalle')
                    ->insert(array(
                      "carta_porte_mercancia_detalle_token" => $cporte_merc_det_token,
                      "mercancias_totales" => $mercancias_totales_id,
                      "bienes_transp" => $vLMerc['BienesTransp'] ?? '---',
                      "clave_stcc" => $vLMerc['ClaveSTCC'] ?? '---',
                      "descripcion" => $vcpMercan['Descripcion'] ?? '---',
                      "cantidad" => $vcpMercan['Cantidad'] ?? 0,
                      "clave_unidad" => $vcpMercan['ClaveUnidad'] ?? '---',
                      "unidad" => $vcpMercan['Unidad'] ?? '---',
                      "dimensiones" => $vcpMercan['Dimensiones'] ?? '---',
                      "material_peligroso" => $vcpMercan['MaterialPeligroso'] ?? '---',
                      "cve_material_peligroso" => $vcpMercan['CveMaterialPeligroso'] ?? '---',
                      "embalaje" => $vcpMercan['Embalaje'] ?? '---',
                      "descrip_embalaje" => $vcpMercan['DescripEmbalaje'] ?? '---',
                      "sector_cofepris" => $vcpMercan['SectorCOFEPRIS'] ?? '---',
                      "nombre_ingrediente_activo" => $vcpMercan['NombreIngredienteActivo'] ?? '---',
                      "nom_quimico" => $vcpMercan['NomQuimico'] ?? '---',
                      "denominacion_generica_prod" => $vcpMercan['DenominacionGenericaProd'] ?? '---',
                      "denominacion_distintiva_prod" => $vcpMercan['DenominacionDistintivaProd'] ?? '---',
                      "fabricante" => $vcpMercan['Fabricante'] ?? '---',
                      "fecha_caducidad" => $vcpMercan['FechaCaducidad'] ?? '---',
                      "lote_medicamento" => $vcpMercan['LoteMedicamento'] ?? '---',
                      "forma_farmaceutica" => $vcpMercan['FormaFarmaceutica'] ?? '---',
                      "condiciones_esp_transp" => $vcpMercan['CondicionesEspTransp'] ?? '---',
                      "registro_sanitario_folio_autorizacion" => $vcpMercan['RegistroSanitarioFolioAutorizacion'] ?? '---',
                      "permiso_importacion" => $vcpMercan['PermisoImportacion'] ?? '---',
                      "folio_impovucem" => $vcpMercan['FolioImpoVUCEM'] ?? '---',
                      "numcas" => $vcpMercan['NumCAS'] ?? '---',
                      "razon_social_emp_imp" => $vcpMercan['RazonSocialEmpImp'] ?? '---',
                      "num_reg_san_plag_cofepris" => $vcpMercan['NumRegSanPlagCOFEPRIS'] ?? '---',
                      "datos_fabricante" => $vcpMercan['DatosFabricante'] ?? '---',
                      "datos_formulador" => $vcpMercan['DatosFormulador'] ?? '---',
                      "datos_maquilador" => $vcpMercan['DatosMaquilador'] ?? '---',
                      "uso_autorizado" => $vcpMercan['UsoAutorizado'] ?? '---',
                      "peso_enkg" => $vcpMercan['PesoEnKg'] ?? 0,
                      "valor_mercancia" => $vcpMercan['ValorMercancia'] ?? 0,
                      "moneda" => $vcpMercan['Moneda'] ?? '---',
                      "fraccion_arancelaria" => $vcpMercan['FraccionArancelaria'] ?? '---',
                      "uuid_comercio_ext" => $vcpMercan['UUIDComercioExt'] ?? '---',
                      "tipo_materia" => $vcpMercan['TipoMateria'] ?? '---',
                      "descripcion_materia" => $vcpMercan['DescripcionMateria'] ?? '---'
                    ));
                    $cporte_merc_det_id = DB::table("carta_porte_mercancia_detalle")->where("carta_porte_mercancia_detalle_token",$cporte_merc_det_token)->value("id");

                    foreach ($vLMerc['DocumentacionAduanera'] as $vDocAdu) {//
                      DB::table('carta_porte_mercancia_doc_aduanera')
                      ->insert(array(
                        "mercancias_totales" => $mercancias_totales_id,
                        "mercancia_detalle" => $cporte_merc_det_id,
                        "tipo_documento" => $vDocAdu['TipoDocumento'] ?? '---',
                        "num_pedimento" => $vDocAdu['NumPedimento'] ?? '---',
                        "ident_doc_aduanero" => $vDocAdu['IdentDocAduanero'] ?? '---',
                        "rfc_impo" => $vDocAdu['RFCImpo'] ?? '---'
                      ));
                    }
                    
                    foreach ($vLMerc['GuiasIdentificacion'] as $vGuia) {
                      DB::table('carta_porte_mercancia_guia_identificacion')
                      ->insert(array(
                        "mercancias_totales" => $mercancias_totales_id,
                        "mercancia_detalle" => $cporte_merc_det_id,
                        "numero_guia_identificacion" => $vGuia['NumeroGuiaIdentificacion'] ?? '---',
                        "descrip_guia_identificacion" => $vGuia['DescripGuiaIdentificacion'] ?? '---',
                        "peso_guia_identificacion" => $vGuia['PesoGuiaIdentificacion'] ?? 0
                      ));
                    }
                    
                    foreach ($vLMerc['CantidadTransporta'] as $vCantTr) {
                      DB::table('carta_porte_mercancia_cantidad_transporta')
                      ->insert(array(
                        "mercancias_totales" => $mercancias_totales_id,
                        "mercancia_detalle" => $cporte_merc_det_id,
                        "cantidad" => $vCantTr['Cantidad'] ?? 0,
                        "id_origen" => $vCantTr['IDOrigen'] ?? '---',
                        "id_destino" => $vCantTr['IDDestino'] ?? '---',
                        "cves_transporte" => $vCantTr['CvesTransporte'] ?? '---',
                      ));
                    }
                    
                    foreach ($vLMerc['DetalleMercancia'] as $vDetMr) {
                      DB::table('carta_porte_mercancia_detalle_mercancia')
                      ->insert(array(
                        "mercancias_totales" => $mercancias_totales_id,
                        "mercancia_detalle" => $cporte_merc_det_id,
                        "unidad_pesomerc" => $vDetMr['UnidadPesoMerc'] ?? '---',
                        "peso_bruto" => $vDetMr['PesoBruto'] ?? 0,
                        "peso_neto" => $vDetMr['PesoNeto'] ?? 0,
                        "peso_tara" => $vDetMr['PesoTara'] ?? 0,
                        "num_piezas" => $vDetMr['NumPiezas'] ?? 0,
                      ));
                    }
                    
                    foreach ($vLMerc['DescripcionesEspecificas'] as $vDesEs) {
                      DB::table('carta_porte_mercancia_descripciones_especificas')
                      ->insert(array(
                        "mercancias_totales" => $mercancias_totales_id,
                        "mercancia_detalle" => $cporte_merc_det_id,
                        "marca" => $vDesEs['Marca'] ?? '---',
                        "modelo" => $vDesEs['Modelo'] ?? '---',
                        "submodelo" => $vDesEs['SubModelo'] ?? '---',
                        "numeroserie" => $vDesEs['NumeroSerie'] ?? '---',
                      ));
                    }
                  }
                }
                
                foreach ($cfdi_complemento_carta_porte['Autotransporte'] as $vAutotr) {
                  $autotransporte_token = Str::uuid()->toString();
                  DB::table('carta_porte_autotransporte')
                  ->insert(array(
                    "autotransporte_token" => $autotransporte_token,
                    "carta_porte" => $carta_porte_id,
                    "perm_sct" => $vAutotr['PermSCT'] ?? '---',
                    "num_permiso_sct" => $vAutotr['NumPermisoSCT'] ?? '---',

                    "config_vehicular" => $vAutotr['ConfigVehicular'] ?? '---',
                    "peso_bruto_vehicular" => $vAutotr['PesoBrutoVehicular'] ?? '---',
                    "placa_vm" => $vAutotr['PlacaVM'] ?? '---',
                    "anio_modelo_vm" => $vAutotr['AnioModeloVM'] ?? '---',

                    "asegura_resp_civil" => $vAutotr['AseguraRespCivil'] ?? '---',
                    "poliza_resp_civil" => $vAutotr['PolizaRespCivil'] ?? '---',
                    "asegura_med_ambiente" => $vAutotr['AseguraMedAmbiente'] ?? '---',
                    "poliza_med_ambiente" => $vAutotr['PolizaMedAmbiente'] ?? '---',
                    "asegura_carga" => $vAutotr['AseguraCarga'] ?? '---',
                    "poliza_carga" => $vAutotr['PolizaCarga'] ?? '---',
                    "prima_seguro" => $vAutotr['PrimaSeguro'] ?? '0.00'
                  ));
                  $autotransporte_id = DB::table('carta_porte_autotransporte')->where("autotransporte_token",$autotransporte_token)->value("id");
                  
                  foreach ($vAutotr['Remolques'] as $vRemol) {
                    DB::table('carta_porte_remolques')
                    ->insert(array(
                      "autotransporte" => $autotransporte_id,
                      "sub_tipo_rem" => $vRemol['SubTipoRem'] ?? '---',
                      "placa" => $vRemol['Placa'] ?? '---'
                    ));
                    
                  }
                }

                foreach ($cfdi_complemento_carta_porte['TransporteMaritimo'] as $vcpTrMar) {
                  $transporte_maritimo_token = Str::uuid()->toString();
                  DB::table('carta_porte_transporte_maritimo')//cfdi__estructura
                  ->insert(array(
                    "transporte_maritimo_token" => $transporte_maritimo_token,
                    "carta_porte" => $carta_porte_id,
                    "perm_sct" => $vcpTrMar['PermSCT'] ?? '---',
                    "num_permiso_sct" => $vcpTrMar['NumPermisoSCT'] ?? '---',
                    "nombre_aseg" => $vcpTrMar['NombreAseg'] ?? '---',
                    "num_poliza_seguro" => $vcpTrMar['NumPolizaSeguro'] ?? '---',
                    "tipo_embarcacion" => $vcpTrMar['TipoEmbarcacion'] ?? '---',
                    "matricula" => $vcpTrMar['Matricula'] ?? '---',
                    "numero_omi" => $vcpTrMar['NumeroOMI'] ?? '---',
                    "anio_embarcacion" => $vcpTrMar['AnioEmbarcacion'] ?? '---',
                    "nombre_embarc" => $vcpTrMar['NombreEmbarc'] ?? '---',
                    "nacionalidad_embarc" => $vcpTrMar['NacionalidadEmbarc'] ?? '---',
                    "unidades_de_arq_bruto" => $vcpTrMar['UnidadesDeArqBruto'] ?? '---',
                    "tipo_carga" => $vcpTrMar['TipoCarga'] ?? '---',
                    "eslora" => $vcpTrMar['Eslora'] ?? '---',
                    "manga" => $vcpTrMar['Manga'] ?? '---',
                    "calado" => $vcpTrMar['Calado'] ?? '---',
                    "puntal" => $vcpTrMar['Puntal'] ?? '---',
                    "linea_naviera" => $vcpTrMar['LineaNaviera'] ?? '---',
                    "nombre_agente_naviero" => $vcpTrMar['NombreAgenteNaviero'] ?? '---',
                    "num_autorizacion_naviero" => $vcpTrMar['NumAutorizacionNaviero'] ?? '---',
                    "num_viaje" => $vcpTrMar['NumViaje'] ?? '---',
                    "num_conoc_embarc" => $vcpTrMar['NumConocEmbarc'] ?? '---',
                    "permiso_temp_navegacion" => $vcpTrMar['PermisoTempNavegacion'] ?? '---',
                  ));
                  $transporte_maritimo_id = DB::table("carta_porte_transporte_maritimo")->where("transporte_maritimo_token",$transporte_maritimo_token)->value("id");
                  
                  foreach ($vcpTrMar['ContenedorM'] as $vMConten) {
                    $transporte_maritimo_token = Str::uuid()->toString();
                    DB::table('carta_porte_transporte_maritimo_contenedorm')//cfdi__estructura
                    ->insert(array(
                      "transporte_maritimo" => $transporte_maritimo_id,
                      "tipo_contenedor" => $vMConten['TipoContenedor'] ?? '---',
                      "matricula_contenedor" => $vMConten['MatriculaContenedor'] ?? '---',
                      "num_precinto" => $vMConten['NumPrecinto'] ?? '---',
                      "idccp_relacionado" => $vMConten['IdCCPRelacionado'] ?? '---',
                      "placa_vmccp" => $vMConten['PlacaVMCCP'] ?? '---',
                      "fecha_certificacion_ccp" => $vMConten['FechaCertificacionCCP'] ?? '---',
                    ));
                  }
                }
                
                foreach ($cfdi_complemento_carta_porte['TransporteAereo'] as $vcpTrAir) {
                  DB::table('carta_porte_transporte_aereo')//cfdi__estructura
                  ->insert(array(
                    "carta_porte" => $carta_porte_id,
                    "perm_sct" => $vcpTrAir['PermSCT'] ?? '---',
                    "num_permiso_sct" => $vcpTrAir['NumPermisoSCT'] ?? '---',
                    "matricula_aeronave" => $vcpTrAir['MatriculaAeronave'] ?? '---',
                    "nombre_aseg" => $vcpTrAir['NombreAseg'] ?? '---',
                    "num_poliza_seguro" => $vcpTrAir['NumPolizaSeguro'] ?? '---',
                    "numero_guia" => $vcpTrAir['NumeroGuia'] ?? '---',
                    "lugar_contrato" => $vcpTrAir['LugarContrato'] ?? '---',
                    "codigo_transportista" => $vcpTrAir['CodigoTransportista'] ?? '---',
                    "rfc_embarcador" => $vcpTrAir['RFCEmbarcador'] ?? '---',
                    "num_reg_id_trib_embarc" => $vcpTrAir['NumRegIdTribEmbarc'] ?? '---',
                    "residencia_fiscal_embarc" => $vcpTrAir['ResidenciaFiscalEmbarc'] ?? '---',
                    "nombre_embarcador" => $vcpTrAir['NombreEmbarcador'] ?? '---'
                  ));
                }

                foreach ($cfdi_complemento_carta_porte['TransporteFerroviario'] as $vcpTrFerro) {
                  $transporte_ferroviario_token = Str::uuid()->toString();
                  DB::table('carta_porte_transporte_ferroviario')//cfdi__estructura
                  ->insert(array(
                    "transporte_ferroviario_token" => $transporte_ferroviario_token,
                    "carta_porte" => $carta_porte_id,
                    "tipo_de_servicio" => $vcpTrFerro['TipoDeServicio'] ?? '---',
                    "tipo_de_trafico" => $vcpTrFerro['TipoDeTrafico'] ?? '---',
                    "nombre_aseg" => $vcpTrFerro['NombreAseg'] ?? '---',
                    "num_poliza_seguro" => $vcpTrFerro['NumPolizaSeguro'] ?? '---'
                  ));
                  $transporte_ferroviario_id = DB::table('carta_porte_transporte_ferroviario')->where("transporte_ferroviario_token",$transporte_ferroviario_token)->value("id");

                  foreach ($vcpTrFerro['DerechosDePaso'] as $vFerroDere) {
                    DB::table('carta_porte_transporte_ferroviario_derechos_de_paso')
                    ->insert(array(
                      "transporte_ferroviario" => $transporte_ferroviario_id,
                      "carta_porte" => $carta_porte_id,
                      "tipo_de_servicio" => $vFerroDere['TipoDerechoDePaso'] ?? '---',
                      "tipo_de_trafico" => $vFerroDere['KilometrajePagado'] ?? '---'
                    ));
                  }

                  foreach ($vcpTrFerro['Carro'] as $vFerroCarro) {
                    $transporte_ferroviario_carro_token = Str::uuid()->toString();
                    DB::table('carta_porte_transporte_ferroviario_carro')
                    ->insert(array(
                      "transporte_ferroviario_carro_token" => $transporte_ferroviario_carro_token,
                      "transporte_ferroviario" => $transporte_ferroviario_id,
                      "tipo_carro" => $vFerroCarro['TipoCarro'] ?? '---',
                      "matricula_carro" => $vFerroCarro['MatriculaCarro'] ?? '---',
                      "guia_carro" => $vFerroCarro['GuiaCarro'] ?? '---',
                      "toneladas_netas_carro" => $vFerroCarro['ToneladasNetasCarro'] ?? '---'
                    ));
                    $transporte_ferroviario_carro_id = DB::table('carta_porte_transporte_ferroviario_carro')->where("transporte_ferroviario_carro_token",$transporte_ferroviario_carro_token)->value("id");
                    
                    foreach ($vFerroCarro['Contenedor'] as $vCarroContenedor) {
                      $transporte_ferroviario_carro_token = Str::uuid()->toString();
                      DB::table('carta_porte_transporte_ferroviario_carro_contenedor')
                      ->insert(array(
                        "transporte_ferroviario_carro" => $transporte_ferroviario_carro_id,
                        "tipo_contenedor" => $vCarroContenedor['TipoContenedor'] ?? '---',
                        "peso_contenedor_vacio" => $vCarroContenedor['PesoContenedorVacio'] ?? '---',
                        "peso_neto_mercancia" => $vCarroContenedor['PesoNetoMercancia'] ?? '---'
                      ));
                    }
                  }
                }
                
                foreach ($cfdi_complemento_carta_porte['PartesTransporte'] as $vcpTrPartes) {
                  DB::table('carta_porte_partes_transporte')
                  ->insert(array(
                    "carta_porte" => $carta_porte_id,
                    "parte_transporte" => $vcpTrPartes['ParteTransporte'] ?? '---',
                    "id_partes_transporte" => $vcpTrPartes['IdPartesTransporte'] ?? '---'
                  ));
                }
        
                foreach ($cfdi_complemento_carta_porte['FiguraTransporte'] as $figTransp) {
                  DB::table('carta_porte_figura_transporte')
                  ->insert(array(
                    "carta_porte" => $carta_porte_id,
                    "tipo_figura" => $figTransp['TipoFigura'] ?? '---',
                    "rfc_figura" => $figTransp['RFCFigura'] ?? '---',
                    "num_licencia" => $figTransp['NumLicencia'] ?? '---',
                    "nombre_figura" => $figTransp['NombreFigura'] ?? '---',
                    "num_reg_id_trib_figura" => $figTransp['NumRegIdTribFigura'] ?? '---',
                    "residencia_fiscal_figura" => $figTransp['ResidenciaFiscalFigura'] ?? '---',
                  ));
                }
              }

              foreach ($compras_seleccionadas as $vBuyList) {
                $compra_id = DB::table("eegr_compras")->where("token_compras",$vBuyList['token_compras'])->value("id");
                $insertCFDIVincBuy = DB::table('cfdi_vinculacion_compras')
                ->insert(array(
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  "compra_vinculada" => $compra_id,
                ));

                $insertCFDITrasladoVincBuy = DB::table('cfdi_traslado_vinculacion_compras')
                ->insert(array(
                  "cfdi_traslado" => $obtenTraslado,
                  "compra_vinculada" => $compra_id,
                ));

                if (!$insertCFDIVincBuy || !$insertCFDITrasladoVincBuy) {
                  throw new \Exception("Error al guardar vinculación de comprobante fiscal a compra.");
                } 
              }

              $contadorDetallecompra = 0;
              foreach ($cfdi_conceptos as $vDet) {
                $this->registraArticuloCFDICompra($vDet,NULL,$comprobante_fiscal_reg,$vEmp->id);
                ++$contadorDetallecompra;
              }
              
              if (isset($xmlFile)) Storage::putFileAs("/public/root/" . $filepath,$xmlFile,$nombreFisicoXML);
              if (isset($pdfFile)) Storage::putFileAs("/public/root/" . $filepath, $pdfFile, $nombreFisicoPDF);
              if (isset($verifFile)) Storage::putFileAs("/public/root/" . $filepath, $verifFile, $nombreFisicoVerif);

              DB::commit();
              $dataMensaje = array(
                'message' => "Carga de CFDI registrada con el folio $folio_traslado",
                'code' => 200,
                'status' => 'success',
              );
            }
          } catch (\Exception $e) {
            // 7. Si algo falla, revertimos TODO en la BD
            DB::rollBack();
            // Opcional: Borrar carpetas físicas creadas en este intento
            // Storage::disk('root')->deleteDirectory($filepath);
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en el registro: ' . $e->getMessage(),
              'line' => $e->getLine()
            );
          }
        }
      } else {
        $mensaje_error_main = '';
        if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
        if (!$OKFechaContab) {$mensaje_error_main = 'Error en fecha de contabilización, verifique su información o comuniquese a soporte';}
        if (!$OKCfdiComprobante) {$mensaje_error_main = 'Error en nodo comprobante de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$OKCfdiEmisor) {$mensaje_error_main = 'Error en nodo emisor de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$OKCfdiReceptor) {$mensaje_error_main = 'Error en nodo receptor de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$OKCfdiConceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!$OKCfdiComplemento) {$mensaje_error_main = 'Error en nodo complemento de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$OKCfdiCartaPorte) {$mensaje_error_main = 'Error en nodo carta porte de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$OKComprasSeleccionadas) {$mensaje_error_main = 'Error en seleccion de compras vinculadas, verifique su información o comuniquese a soporte';}
        if (!$OKObservaciones) {$mensaje_error_main = 'Error en observaciones, verifique su información o comuniquese a soporte';}
        if (!file_exists($request->file('imagenEvidenciaXMl'))) {$mensaje_error_main = 'Debe cargar la factura en formato xml correspondiente a esta compra';}
        if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}