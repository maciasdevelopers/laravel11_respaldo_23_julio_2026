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
use App\Models\OrdenRecepcionModelo;

class EGRE_ComprasRegistro_Controller extends Controller{
  public function selectFolioCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    //return $usuario->user_token;
    $folioCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->count();
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
    //$resultQuery = DB::select($combinado, [$usuario->empresa_token, $usuario->user_token, $usuario->empresa_token, $usuario->user_token]);
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
        "articulo_homologado_activoIntangible" => "",
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

    $JwtAuth = new \App\Helpers\JwtAuth();

    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayArticulos = array();

    //echo $JwtAuth->encriptar('acer')." acer";
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'proveedor' => 'string',
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
        $proveedor_selected = $parametrosArray['proveedor'];

        $queryCatActFijo = DB::table('in_egr_catalogo_servicios as catserv')
        ->join('sos_ps_genero as gen', 'catserv.genero', '=', 'gen.id')
        ->join('in_egr_catalogo_servicios_claves as srclave', 'catserv.id', '=', 'srclave.servicio_id')
        ->join('eegr_catalogo_proveedores as catprov', 'srclave.proveedor', '=', 'catprov.id')
        ->where("catserv.folio_sistema","999999998")
        ->where('catserv.status', TRUE)
        ->whereNull('catserv.administrador')
        ->where('catprov.token_cat_proveedores', $proveedor_selected)
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
        ->where('catserv.status', TRUE)
        ->whereNull('catserv.administrador')
        ->where('catprov.token_cat_proveedores', $proveedor_selected)
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
        ->where('catprov.token_cat_proveedores', $proveedor_selected)
        ->where('catprod.modulo_mostrador', FALSE)
        ->where('catprod.status', TRUE)
        ->where('emp.empresa_token', $usuario->empresa_token)
        ->where('users.usuario_token', $usuario->user_token)
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
        ->where('catserv.proceso', 'c')
        ->where('catserv.status', TRUE)
        ->where('catprov.token_cat_proveedores', $proveedor_selected)
        ->where('emp.empresa_token', $usuario->empresa_token)
        ->where('users.usuario_token', $usuario->user_token)
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
        //$resultQuery = DB::select($combinado, [$proveedor_selected, $usuario->empresa_token, $usuario->user_token, $proveedor_selected, $usuario->empresa_token, $usuario->user_token]);
        $resultQuery = $queryCatActFijo->unionAll($queryCatActDiferido)->unionAll($queryCatProd)->unionAll($queryCatServ)->get();
        $countList = 0;

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
            "articulo_homologado_activoIntangible" => "",
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
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
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
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayArticulos = array();

    //echo $JwtAuth->encriptar('acer')." acer";
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'tokenProveedor' => 'required|string',
        'token_articulo' => 'required|string',
        'identificador' => 'required|string',
        'prov_relacionado_registrar' => 'required|boolean',
        'prov_relacionado_tiene_clave' => 'required|boolean',
        'prov_relacionado_clave' => 'string',
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
        $tokenProveedor = $parametrosArray['tokenProveedor'];
        $token_articulo = $parametrosArray['token_articulo'];
        $identificador = $parametrosArray['identificador'];
        $prov_relacionado_registrar = $parametrosArray['prov_relacionado_registrar'];
        $prov_relacionado_tiene_clave = $parametrosArray['prov_relacionado_tiene_clave'];
        $prov_relacionado_clave = $parametrosArray['prov_relacionado_clave'];

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
          $tokenClabeProdProv = $JwtAuth->encriptarToken(time().$obtenProv . $usuario->empresa_token . $usuario->user_token);

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
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
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
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_articulo' => 'required|string',
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
            $parametrosArray['token_articulo'],
            $usuario->empresa_token,
            $usuario->user_token,
            $parametrosArray['token_articulo'],
            $usuario->empresa_token,
            $usuario->user_token
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
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
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
      $activoIntangible = $vDet['articulo_homologado_activoIntangible'];
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
            $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada';
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
        } else if ($usoArticulo == 'activo_intangible') {
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

  private function registraAnticipoCompra($JwtAuth,$token_proveedor,$usuario,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$cfdi_comprobante_tipo_de_cambio,$cfdi_comprobante_moneda,$user_id,$emp_id,$orden_de_pago_vinculada){
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
      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$usuario->empresa_token, $usuario->user_token]
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

  private function registraArticuloCompra($JwtAuth,$detBuy,$usuario,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$comprobante_fiscal_reg,$emp_id){
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
    $activoIntangible = $detBuy['articulo_homologado_activoIntangible'];
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
            WHEN ? IN (SELECT token_cat_productos FROM in_egr_catalogo_productos WHERE status = TRUE AND admin_empresa = (SELECT id FROM main_empresas WHERE empresa_token = ? LIMIT 1)) THEN "Producto"
            WHEN ? IN (SELECT token_cat_servicios FROM in_egr_catalogo_servicios WHERE status = TRUE AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ? LIMIT 1)) THEN "Servicio"
        END AS identificador) AS subconsulta'))
    ->setBindings([$tokenArticulo, $usuario->empresa_token, $tokenArticulo, $usuario->empresa_token])
    ->value("identificador");

    // 3. Obtención de IDs reales
    $id_producto = ($catProdServ == 'Producto') ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $tokenArticulo)->value("id") : NULL;
    $id_servicio = ($catProdServ != 'Producto') ? DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $tokenArticulo)->value("id") : NULL;
    $id_activo_fijo = ($usoArticulo == 'activo_fijo') ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos", $detBuy['articulo_homologado_activoFijo'])->value("id") : NULL;
    $id_activo_intangible = ($usoArticulo == 'activo_intangible') ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang", $detBuy['articulo_homologado_activoIntangible'])->value("id") : NULL;
  
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

    $this->procesarImpuestos($id_compra_detalle, $obtenCompra, $retenciones ?? [], 'rete', $JwtAuth);
    $this->procesarImpuestos($id_compra_detalle, $obtenCompra, $traslados ?? [], 'tras', $JwtAuth);
    
    return $id_compra_detalle;
  }

  private function procesarImpuestos($idDetalle, $obtenCompra, $impuestos, $tipo, $JwtAuth) {
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

  private function registraArticuloCFDICompra($JwtAuth,$detBuy,$usuario,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$comprobante_fiscal_reg,$emp_id){
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
  
  private function procesarProrrateo($JwtAuth, $usuario, $vEmp, $id_prod, $id_serv, $obtenCompra, $selectDetBuy, $fecha_contabilizacion) {
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
  
  private function procesarKardexProducto($JwtAuth, $usuario, $vEmp, $id_producto, $tokenArticulo, $obtenCompra, $selectDetBuy, $cantidad, $precioUnitario, $tokenCompra) {
      // 1. Mejora en la obtención del folio: Más legible y usando el Query Builder de Laravel
      $ultimoFolio = DB::table('in_egr_productos_kardex as dexkar')
      ->join('in_egr_catalogo_productos as catprod', 'dexkar.producto', '=', 'catprod.id')
      ->join('main_empresas as emp', 'catprod.admin_empresa', '=', 'emp.id')
      ->where('catprod.token_cat_productos', $tokenArticulo)
      ->where('emp.empresa_token', $usuario->empresa_token)
      ->max('dexkar.folio_kardex');
  
      $nuevoFolio = ($ultimoFolio ?? 0) + 1;
  
      // 2. Token con mayor entropía: Evitamos colisiones si ocurren procesos en el mismo segundo
      $token_kardex = $JwtAuth->encriptarToken(time() . $tokenCompra . $selectDetBuy . uniqid());
  
      // 3. Inserción en Kardex
      // Nota: Usamos una variable para validar el resultado
      $insertOk = DB::table("in_egr_productos_kardex")->insert([
          "token_kardex"      => $token_kardex,
          "folio_kardex"      => $nuevoFolio,
          "fecha_kardex"      => time(),
          "status_kardex"     => 2, // 2 = Por recibir
          "producto"          => $id_producto,
          "concepto"          => "por recibir",
          "factura_compra"    => $obtenCompra,
          "detalle_compra"    => $selectDetBuy,
          "recibir_cantidad"  => $cantidad,
          "valor_unitario"    => $precioUnitario,
      ]);
  
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
    $unidadesParaInsertar = [];

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
      $unidadesParaInsertar[] = [
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
    if (!empty($unidadesParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_fijos_unidades')->insert($unidadesParaInsertar);

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
    $jsonUser = $request->input('json');//json_decode($request->input('json'), true);
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'fecha_contabilizacion' => 'required|string',
        'fecha_vencimiento' => 'required|string',
        'cfdi_comprobante' => 'required|array', 
        'total' => 'required|string', 
        'cfdi_relacionados' => 'array',
        'cfdi_emisor' => 'required|array',  
        'token_proveedor' => 'required|string',
        'cfdi_receptor' => 'required|array',
        'cfdi_conceptos' => 'required|array',
        'cfdi_impuestos_retenidos' => 'array',
        'cfdi_impuestos_trasladados' => 'array',
        'cfdi_complemento' => 'required|array',
        'compra_contado_credito' => 'required|string',
        'anticipo_aplicado' => 'numeric',
        'classRecibeArtPago' => 'required|boolean',
        'tentativa_recepcion_activo' => 'string',
        'tipoLugarEntrega' => 'required|string',
        'tknLugarRecepcion' => 'string',
        'compra_observaciones' => 'string',
        'pagar' => 'string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $JwtAuth = new \JwtAuth();
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        
        $moneda_decimales = 0;
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fecha_vencimiento = $parametrosArray['fecha_vencimiento'];
        $cfdi_comprobante = $parametrosArray['cfdi_comprobante'];
        $total = $parametrosArray['total'];
        $cfdi_relacionados = $parametrosArray['cfdi_relacionados'];
        $cfdi_emisor = $parametrosArray['cfdi_emisor'];
        $token_proveedor = $parametrosArray['token_proveedor'];
        $cfdi_receptor = $parametrosArray['cfdi_receptor'];
        $cfdi_conceptos = $parametrosArray['cfdi_conceptos'];
        $cfdi_impuestos_retenidos = $parametrosArray['cfdi_impuestos_retenidos'];
        $cfdi_impuestos_trasladados = $parametrosArray['cfdi_impuestos_trasladados'];
        $cfdi_complemento = $parametrosArray['cfdi_complemento'];
        $compra_contado_credito = $parametrosArray['compra_contado_credito'];
        $anticipo_aplicado = $parametrosArray['anticipo_aplicado'];
        $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
        $tentativa_recepcion_activo = $parametrosArray['tentativa_recepcion_activo'];
        $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
        $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];
        $compra_observaciones = $parametrosArray['compra_observaciones'];
        $compra_pagar = $parametrosArray['pagar'];

        //echo $usuario->empresa_token;exit;
        $mi_llave_secreta = env('JWT_BUY_ID_SECRET');
        $permisosCreacion = $JwtAuth->permisosCreacion($mi_llave_secreta,$usuario->empresa_token,$usuario->user_token);
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
        $validate_tentativa_recepactivo = isset($tentativa_recepcion_activo) && !empty($tentativa_recepcion_activo) && preg_match($JwtAuth->filtroFecha(),$tentativa_recepcion_activo);
        $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega) && isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);
        $validate_compra_observaciones = isset($compra_observaciones) && !empty($compra_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_observaciones);

        if ($validate_fecha_contabilizacion && $validate_fecha_vencimiento && $permisosCreacion && $validate_cfdi_comprobante && $validate_total && $validate_cfdi_emisor && $validate_prov && $validate_cfdi_receptor && 
          $validate_cfdi_conceptos && $validate_cfdi_impuestos_retenidos && $validate_cfdi_impuestos_trasladados && $validate_cfdi_complemento && $validate_compra_contado_credito &&
          $validate_classRecibeArtPago) {
            // && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))

          $moneda_decimales = $JwtAuth->getMonedaAPI($cfdi_comprobante['moneda'] ?? 'MXN');
          $idDireccionProv = DB::table("teci_direcciones AS dir")
          ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
          ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
          ->value("dir.id");
          
          $idDireccionEst = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id");
          if (($tipoLugarEntrega == 'proveedor' && $idDireccionProv == "") || ($tipoLugarEntrega == 'establecimiento' && $idDireccionEst == "")) {
            $dataMensaje = array('status' => 'error','code' => 200,'message' => 'El lugar de recepción seleccionado no encontrado, verifique su información o comuniquese a soporte');
          }
          
          $detalleErrores = $this->registrarCompraValidaConceptos($cfdi_conceptos,$moneda_decimales,$JwtAuth);
          //return response()->json(['status' => 'error','code' => 200,'message' => $detalleErrores.$cfdi_comprobante['moneda']]);
          
          if ($detalleErrores == "") {
            $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            foreach ($queryEmp as $vEmp) {
              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

              $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token, $usuario->user_token]);

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
                
                $tokenCompra = $JwtAuth->encriptarToken(time(), $token_proveedor, $cfdi_comprobante_moneda, $tipoLugarEntrega, $tknLugarRecepcion, $cfdi_conceptos);
                $fechaSistema = time();
                $fecha_altaCompra = $cfdi_comprobante_fecha != '' ? $JwtAuth->convierteFechaEpoc($cfdi_comprobante_fecha) : time();
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
  
                $compras->recepcion_prov = $tipoLugarEntrega == 'proveedor' ? $idDireccionProv : NULL;
                $compras->recepcion_estab = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
                $compras->comprador = $vEmp->id;
                $compras->usuario_comprador = $vEmp->userr;
                $compras->status_autorizacion = $status_autorizacion;
                $compras->autoriza = $user_jerarquia;
                $compras->status_cancelacion = FALSE;
                $compras->cancela = NULL;
                $compras->status_recepcion = FALSE;
                $compras->recibe = NULL;
                $compras->fecha_delete_compra = '';
                $compras->status_compra = TRUE;
                $compras->observaciones_compra = $validate_compra_observaciones ? $JwtAuth->encriptar($compra_observaciones) : NULL;

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
                  
                  $validate_insert_ord_pago = false;
                  $orden_de_pago_vinculada = "";
                  if ($vEmp->jerarquia_main == 'P') {
                    $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);
  
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
  
                    $folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
                      ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                      WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);
  
                    $orden_recept = new OrdenRecepcionModelo();
                    $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
                    $orden_recept->folio_recepcion = $folioRecepcionOrden[0]->folio;
                    $orden_recept->fecha_recepcion = $validate_tentativa_recepactivo ? $tentativa_recepcion_activo : time();
                    $orden_recept->proveedor = $idProveedor;
                    $orden_recept->orden_compra = $obtenCompra;
                    $orden_recept->almacen = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
                    $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                    $orden_recept->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                    $orden_recept->observaciones = NULL;
                    $orden_recept->empresa = $vEmp->id; //cifrado
                    $newOrderRecept = $orden_recept->save();
                    if (!$newOrderRecept) {
                      throw new \Exception("Error al guardar orden de recepción de compra.");
                    }
                  }
  
                  if ($anticipo_aplicado > 0) {
                    $this->registraAnticipoCompra($JwtAuth,$token_proveedor,$usuario,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$cfdi_comprobante_tipo_de_cambio,$cfdi_comprobante_moneda,$vEmp->userr,$vEmp->id,$orden_de_pago_vinculada);
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

                  $contadorDetallecompra = 0;
                  foreach ($cfdi_conceptos as $vDet) {
                    $tokenArticulo = $vDet['articulo_homologado_token'];
                    $identificador = $vDet['articulo_homologado_identificador'];
                    $precioUnitario = $vDet['ValorUnitario'];
                    $cantidad = $vDet['Cantidad'];
                    $usoArticulo = $vDet['articulo_homologado_uso'];
                    $prorratea = $vDet['articulo_homologado_prorratea'];
  
                    //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                    $selectDetBuy = $this->registraArticuloCompra($JwtAuth,$vDet,$usuario,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$comprobante_fiscal_reg,$vEmp->id);
                    $this->registraArticuloCFDICompra($JwtAuth,$vDet,$usuario,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$comprobante_fiscal_reg,$vEmp->id);
                    
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
                      $this->procesarProrrateo($JwtAuth, $usuario, $vEmp, $id_producto, $id_servicio, $obtenCompra, $selectDetBuy, $fecha_contabilizacion);
                    }
  
                    if ($catProdServ == 'Producto') {
                      $this->procesarKardexProducto($JwtAuth, $usuario, $vEmp, $id_producto, $tokenArticulo, $obtenCompra, $selectDetBuy, $cantidad, $precioUnitario, $tokenCompra);
                    }
  
                    if ($catProdServ == 'Servicio') {
                      $upDateServicio = DB::table('in_egr_catalogo_servicios')
                      ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                      ->where([
                        'in_egr_catalogo_servicios.status' => TRUE,
                        'in_egr_catalogo_servicios.id' => $id_servicio,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));
                    }
  
                    if ($identificador == 'ActivoFijo' && $usoArticulo == 'activo_fijo') {
                      $this->procesarActivoFijo($JwtAuth,$selectDetBuy,$vDet,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio,$vEmp->id);
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
                    $usuario->empresa_token,
                    $usuario->user_token
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
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
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
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarCompraByARTICULOS(Request $request){
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

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'fecha_contabilizacion' => 'required|string',
        'fecha_vencimiento' => 'required|string',
        'token_proveedor' => 'required|string',
        'token_moneda' => 'required|string',
        'tipoDeCambio' => 'required|string',
        'compra_conceptos' => 'required|array',
        'total' => 'required|string', 
        'compra_contado_credito' => 'required|string',
        'classRecibeArtPago' => 'required|boolean',
        'tipoLugarEntrega' => 'required|string',
        'tknLugarRecepcion' => 'string',
        'anticipo_aplicado' => 'numeric',
        'aplica_recepcion_facturas' => 'string',
        'compra_observaciones' => 'string',
        'pagar' => 'string',
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
        $patrónNum = '/^[0-9$,.-]*$/';
        $patrónNumCosto = '/^[0-9$,.-]*$/';
        $patrónRfc = '/[aA0-zZ9]/';
        $patrónFecha = '/^[0-9-]*$/';
        
        $moneda_decimales = 0;
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fecha_vencimiento = $parametrosArray['fecha_vencimiento'];
        $token_proveedor = $parametrosArray['token_proveedor'];
        $token_moneda = $parametrosArray['token_moneda'];
        $tipoDeCambio = $parametrosArray['tipoDeCambio'] ? $parametrosArray['tipoDeCambio'] : "1.00";
        $compra_conceptos = $parametrosArray['compra_conceptos'];
        $total = $parametrosArray['total'];
        $compra_contado_credito = $parametrosArray['compra_contado_credito'];
        $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
        $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
        $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];
        $anticipo_aplicado = $parametrosArray['anticipo_aplicado'];
        $aplica_recepcion_facturas = $parametrosArray['aplica_recepcion_facturas'];
        $compra_observaciones = $parametrosArray['compra_observaciones'];
        $compra_pagar = $parametrosArray['pagar'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $validate_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $validate_fecha_vencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
        $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
        $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && $idProveedor != "";

        $validate_token_moneda = isset($token_moneda) && !empty($token_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$token_moneda);
        $validate_tipoDeCambio = isset($tipoDeCambio) && !empty($tipoDeCambio) && preg_match($JwtAuth->filtroNumericoSimple(),$tipoDeCambio);
        $validate_compra_conceptos = isset($compra_conceptos) && !empty($compra_conceptos) && is_array($compra_conceptos);
        $validate_total = isset($total) && !empty($total);
        $validate_compra_contado_credito = isset($compra_contado_credito) && !empty($compra_contado_credito) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_contado_credito);
        $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);
        $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega) && isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);
        $validate_anticipo_aplicado = isset($anticipo_aplicado) && !empty($anticipo_aplicado);
        $validate_compra_observaciones = isset($compra_observaciones) && !empty($compra_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_observaciones);

        if ($permisosCreacion && $validate_fecha_contabilizacion && $validate_fecha_vencimiento && $validate_prov && $validate_token_moneda && $validate_tipoDeCambio && $validate_compra_conceptos && 
          $validate_total && $validate_compra_contado_credito && $validate_classRecibeArtPago) {// && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))

          $moneda_decimales = $JwtAuth->getMonedaAPI($token_moneda);
          
          $idDireccionProv = DB::table("teci_direcciones AS dir")
          ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
          ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
          ->value("dir.id");

          $idDireccionEst = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id");
          if (($tipoLugarEntrega == 'proveedor' && $idDireccionProv == "") || ($tipoLugarEntrega == 'establecimiento' && $idDireccionEst == "")) {
            $dataMensaje = array('status' => 'error','code' => 200,'message' => 'El lugar de recepción seleccionado no encontrado, verifique su información o comuniquese a soporte');
          }

          $detalleErrores = "";
          foreach ($compra_conceptos as $vDet) {
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
            $activoIntangible = $vDet['articulo_homologado_activoIntangible'];
            $prorratea = $vDet['articulo_homologado_prorratea'];

            $importe = $vDet['Importe'];
            $validateActivos = false;
            $validatePeriodicidad = false;
            $validateDescuentos = false;
            $validateDecimalesMoneda = false;
            $validateForImpuRetenciones = false;
            $validateForImpuTraslados = false;

            $vItem_tokenArticulo = isset($tokenArticulo) && !empty($tokenArticulo);
            $vItem_identificador = isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador);
            $vItem_precioUnitario = isset($precioUnitario) && !empty($precioUnitario) && preg_match($patrónNumCosto, $precioUnitario);
            $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($patrónNum, $cantidad);
            //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
            $vItem_usoArticulo = isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo);
            $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
            //$vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
            $vItem_importe = isset($importe) && !empty($importe) && preg_match($patrónNumCosto, $importe);

            if ($vItem_tokenArticulo && $vItem_identificador && $vItem_precioUnitario && $vItem_cantidad && $vItem_usoArticulo && $vItem_importe) {
              if (isset($descuentoXUni) && !empty($descuentoXUni)) {
                if ($descuentoXUni != '---') {
                  if (preg_match($patrónNumCosto, $descuentoXUni)) {
                    $strPosdescuentoXUni = strpos($descuentoXUni, '.');
                    if ($strPosdescuentoXUni !== FALSE) {
                      $expdescuentoXUni = explode('.', $descuentoXUni);
                      if ($moneda_decimales == strlen($expdescuentoXUni[1])) {
                        $validateDescuentos = true;
                      } else {
                        $validateDescuentos = false;
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'La cantidad de decimales del descuento no coincide con los decimales que soporta la moneda seleccionada'
                        );
                      }
                    } else {
                      $validateDescuentos = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  } else {
                    $validateDescuentos = false;
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Descuento invalido'
                    );
                  }
                } else {
                  $validateDescuentos = true;
                }
              } else {
                $validateDescuentos = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La cantidad de descuento es invalida o inexistente'
                );
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
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                    );
                  }
                } else {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                  );
                }
              }

              if ($moneda_decimales == 0) {
                $strPosPrecioUnit = strpos($precioUnitario, '.');
                $strPosimporte = strpos($importe, '.');
                if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El precio unitario del producto/servicio no tiene decimales'
                  );
                } else {
                  $validateDecimalesMoneda = true;
                }
              }

              if ($usoArticulo == 'activo_fijo') {
                if (isset($activoFijo) && !empty($activoFijo) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoFijo)) {
                  $validateActivos = true;
                } else {
                  $validateActivos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El activo del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              } else if ($usoArticulo == 'activo_intangible') {
                if (isset($activoIntangible) && !empty($activoIntangible) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoIntangible)) {
                  $validateActivos = true;
                } else {
                  $validateActivos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El descuento del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              } else {
                $validateActivos = true;
              }

              if (count($retenciones) != 0) {
                $countValidateRetencionesConcept = 0;
                for ($t = 0; $t < count($retenciones); $t++) {
                  $impuesto_relacionado_token = $retenciones[$t]["impuesto_relacionado_token"];
                  $importe = "1.00";
                  //$importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]) : "0.00";
                  //return response()->json(['message' => $retenciones[$t]["Importe"],'codigo' => 200,'status' => 'error']);
                  $explodeImporte = explode('.', $importe);
                  $valida_ret_token = isset($impuesto_relacionado_token) && !empty($impuesto_relacionado_token);
                  $valida_ret_importe = isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales);

                  if ($valida_ret_token && $valida_ret_importe) {
                    ++$countValidateRetencionesConcept;
                    //return response()->json(['message' => $base,'codigo' => 200,'status' => 'error']);
                  } else {
                    $error_ret = '';
                    if (!$valida_ret_token) {$error_ret = 'Impuesto de retención del producto/servicio '.$concepto.' no existe';}
                    if (!$valida_ret_importe) {$error_ret = 'Importe de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';}
                    $dataMensaje = array('status' => 'error','code' => 200,'message' => $error_ret);
                    break;
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
                  $impuesto_relacionado_token = $traslados[$t]["impuesto_relacionado_token"];
                  $importe = "1.00";
                  //$importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]) : "0.00";
                  $explodeImporte = explode('.', $importe);
                  $valida_tras_token = isset($impuesto_relacionado_token) && !empty($impuesto_relacionado_token);
                  $valida_tras_importe = isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales);

                  if ($valida_tras_token && $valida_tras_importe) {
                    ++$countValidateTrasladosConcept;
                  } else {
                    $error_tras = '';
                    if (!$valida_tras_token) {$error_tras = 'Impuesto de traslado del producto/servicio '.$concepto.' no existe';}
                    if (!$valida_tras_importe) {$error_tras = 'Importe de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida (' . $moneda_decimales . ')';}
                    $dataMensaje = array('status' => 'error','code' => 200,'message' => $error_tras);
                    break;
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
              //if (!$vItem_periodicidadPc) {$detalleErrores = 'La periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente';}
              if (!$vItem_importe) {$detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';}
              break;
            }
          }
          
          if ($detalleErrores == "") {
            $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            foreach ($queryEmp as $vEmp) {
              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

              $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token, $usuario->user_token]);

              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
              $folio_buy = 'COMP-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');
              //return response()->json(['message' => $folio_buy,'codigo' => 200,'status' => 'error']);
              $nombreRecePago = '';
              
              $tokenCompra = $JwtAuth->encriptarToken(time(),$idProveedor,$token_moneda,$tipoLugarEntrega,$tknLugarRecepcion);
              $fechaSistema = time();
              $fecha_altaCompra = time();
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
              $compras->recibeFactura = FALSE;
              $compras->aplica_recepcion_facturas = $aplica_recepcion_facturas;
              $compras->recepcionPago = $nombreRecePago; //cifrado
              $compras->moneda = $token_moneda;
              $compras->tipo_de_cambio = $tipoDeCambio;
              $compras->anticipo = $anticipo_aplicado;
              $compras->recibeProducto = $classRecibeArtPago ? TRUE : FALSE;// si es TRUE genera orden de pago, si es FALSE no
              $compras->pago_caja_tesoreria = NULL;
              $compras->caja_paga = NULL;
              $compras->recepcion_prov = $tipoLugarEntrega == 'proveedor' ? $idDireccionProv : NULL;
              $compras->recepcion_estab = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
              $compras->comprador = $vEmp->id;
              $compras->usuario_comprador = $vEmp->userr;
              $compras->status_autorizacion = $status_autorizacion;
              $compras->autoriza = $user_jerarquia;
              $compras->status_cancelacion = FALSE;
              $compras->cancela = NULL;
              $compras->status_recepcion = FALSE;
              $compras->recibe = NULL;
              $compras->fecha_delete_compra = '';
              $compras->status_compra = TRUE;
              $compras->observaciones_compra = $validate_compra_observaciones ? $JwtAuth->encriptar($compra_observaciones) : NULL;
              $insertCompra = $compras->save();
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden']);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
              if ($insertCompra) {
                $obtenCompra = $compras->id;

                $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";

                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

                if (!empty($_FILES['compra_anexos'])) {
                  $evidencias = $_FILES["compra_anexos"];
                  //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
                  $string_name_evid = json_encode($_FILES["compra_anexos"]["name"]);
                  if (count(json_decode($string_name_evid)) != 0) {
                    $evidencia_nombre = json_decode($string_name_evid);
                    for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                      $temporal = $evidencias["tmp_name"][$doc];
                      $doc_name = $evidencias["name"][$doc];
                      Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%BUY-ANEX%'");
                      $token_documento = $JwtAuth->encriptarToken($obtenCompra,$doc_name,$select_folio_doc[0]->folio);
                      $insertDocSoli = DB::table("sos_documentos")->insert(
                        array(
                          "token_documento" => $token_documento,
                          "fecha_carga" => time(),
                          "modulo" => "pagos",
                          "folio_modulo" => "BUY-ANEX" . $select_folio_doc[0]->folio,
                          "tipo_documento" => "an",
                          "nombre_documento" => $JwtAuth->encriptar($doc_name),
                          "compra" => $obtenCompra,
                          "status_documento" => TRUE,
                        )
                      );
                    }
                  }
                }
    
                $validate_insert_ord_pago = false;
                $orden_de_pago_vinculada = "";
                if ($vEmp->jerarquia_main == 'P') {
                  $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

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
                  $orderpay->fecha_autorizacion_pay = $compra_pagar == "pagar" ? time() : NULL;
                  $orderpay->tentativa_pago = $compra_pagar == "pagar" ? time() : NULL;
                  $orderpay->orden_terminada_bool = FALSE;
                  $orderpay->orden_terminada_fecha = NULL;
                  $orderpay->status_ordenPago = TRUE;  //cifrado
                  $orderpay->empresa = $vEmp->id; //cifrado
                  $orderpay->comprador = $vEmp->userr; //cifrado
                  $insertOrder = $orderpay->save();
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                  if ($insertOrder) {
                    $validate_insert_ord_pago = true;
                  } 

                  $folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
                    ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                    WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                  $orden_recept = new OrdenRecepcionModelo();
                  $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
                  $orden_recept->folio_recepcion = $folioRecepcionOrden[0]->folio;
                  $orden_recept->fecha_recepcion = time();
                  $orden_recept->proveedor = $idProveedor;
                  $orden_recept->orden_compra = $obtenCompra;
                  $orden_recept->almacen = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
                  $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                  $orden_recept->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                  $orden_recept->observaciones = NULL;
                  $orden_recept->empresa = $vEmp->id; //cifrado
                  $newOrderRecept = $orden_recept->save();
                }

                if ($anticipo_aplicado > 0) {
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
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                    [$usuario->empresa_token, $usuario->user_token]
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
                      "deu_tipo_cambio" => $tipoDeCambio,
                      "deu_mov_moneda" => $token_moneda,
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
                  $insertMovVincPagosOrden = DB::table("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo")
								  ->insert(array("mov_realizado" => $id_mov_realizado,"orden_pago" => $orden_de_pago_vinculada));

						      $insertPagoVinc = DB::table("fnzs_catalogo_deudores_movimientos_pagos_vinculados")
						      ->insert(array(
                    "mov_realizado" => $id_mov_realizado,
                    "pago_vinculado" => $id_pago_realizado,
                    "mov_pago_monto" => $anticipo_aplicado
                  ));
                }

                $contadorDetallecompra = 0;
                for ($i = 0; $i < count($compra_conceptos); $i++) {
                  $validUpdtProd = false;
                  $validUpdtServ = false;
                  $tokenArticulo = $compra_conceptos[$i]['articulo_homologado_token'];
                  $identificador = $compra_conceptos[$i]['articulo_homologado_identificador'];
                  $precioUnitario = $compra_conceptos[$i]['ValorUnitario'];
                  $cantidad = $compra_conceptos[$i]['Cantidad'];
                  $descuentoXUni = $compra_conceptos[$i]['Descuento'];
                  $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
                  $iva = $compra_conceptos[$i]['articulo_homologado_iva'];
                  $retenciones = $compra_conceptos[$i]['retenciones'];
                  $total_retenciones = $compra_conceptos[$i]['TotalRetenciones'];
                  //$retenciones_homologada = $compra_conceptos[$i]['retencion_token'];
                  $traslados = $compra_conceptos[$i]['traslados'];
                  $total_traslado = $compra_conceptos[$i]['TotalTraslados'];
                  //$traslados_homologada = $compra_conceptos[$i]['traslado_token'];
                  $usoArticulo = $compra_conceptos[$i]['articulo_homologado_uso'];
                  $efectoFiscalArticulo = $compra_conceptos[$i]['articulo_homologado_efecto_fiscal'];
                  $alm_serie = $compra_conceptos[$i]['articulo_homologado_serie_token'];
                  $alm_lote = $compra_conceptos[$i]['articulo_homologado_lote_token'];
                  $alm_pedimento = $compra_conceptos[$i]['articulo_homologado_pedimento_token'];
                  $activoFijo = $compra_conceptos[$i]['articulo_homologado_activoFijo'];
                  $activoIntangible = $compra_conceptos[$i]['articulo_homologado_activoIntangible'];
                  $prorratea = $compra_conceptos[$i]['articulo_homologado_prorratea'];

                  $importe = $compra_conceptos[$i]['Importe'];
                  $token_unidad_medida = $compra_conceptos[$i]['Unidad'];

                  $token_producto = '';
                  $token_servicio = '';
                  $activos_fijos = '';
                  $activos_intangibles = '';
                  $pedimento_aduanal = NULL;
                  $boolprorratea = FALSE;
                  //$boolperiodicidadPc = FALSE;
                  //$txtiteracionPc = NULL;
                  //$boolperiodoDetIndPc = FALSE;
                  //$txtfechaFinPc = NULL;

                  $catProdServ = DB::table(DB::raw('(SELECT
                    CASE
                      WHEN ? IN (
                        SELECT token_cat_productos 
                        FROM in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp ON catprod.admin_empresa = emp.id
                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa
                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                        WHERE catprod.modulo_mostrador = FALSE 
                          AND catprod.status = TRUE 
                          AND emp.empresa_token = ?
                          AND users.usuario_token = ?
                      ) THEN "Producto"
                      WHEN ? IN (
                        SELECT token_cat_servicios 
                        FROM in_egr_catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp ON catserv.administrador = emp.id
                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa 
                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                        WHERE catserv.proceso = "c" 
                          AND catserv.status = TRUE 
                          AND emp.empresa_token = ? 
                          AND users.usuario_token = ?
                      ) THEN "Servicio"
                    END AS identificador) AS subconsulta'))
                  ->select('identificador')
                  ->setBindings([
                    $tokenArticulo,
                    $usuario->empresa_token,
                    $usuario->user_token,
                    $tokenArticulo,
                    $usuario->empresa_token,
                    $usuario->user_token
                  ])
                  ->value("identificador");

                  $token_producto = $catProdServ == 'Producto' ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$tokenArticulo)->value("id") : NULL;
                  $token_servicio = $catProdServ == 'Producto' ? NULL : DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$tokenArticulo)->value("id");
                  $serie = $catProdServ == 'Producto' && $alm_serie != '' ? DB::table("inventarios_catalogo_series")->where("serie_token",$alm_serie)->value("id") : NULL;
                  $lote = $catProdServ == 'Producto' && $alm_lote != '' ? DB::table("inventarios_catalogo_lotes")->where("token_lote",$alm_lote)->value("id") : NULL;
                  $pedimento_aduanal = $catProdServ == 'Producto' && $alm_pedimento != '' ? DB::table("inventarios_catalogo_pedimento_aduanal")->where("token_pedimento",$alm_pedimento)->value("id") : NULL;
                  $activos_fijos = $catProdServ == 'Producto' && $usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo) ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos",$activoFijo)->value("id") : NULL;
                  $activos_intangibles = $catProdServ == 'Servicio' && $usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible) ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang",$activoIntangible)->value("id") : NULL;

                  $tokenDetalleCompra = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$tokenArticulo.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.
                    $alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$importe);

                  if (count($retenciones) != 0) {
                    for ($t = 0; $t < count($retenciones); $t++) {
                      //$importe = $retenciones[$t]["TipoFactor"] != "Exento" ? $retenciones[$t]["Importe"] : 0;
                      //$importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $retenciones[$t]["Importe"] : 0;
                      //$sumaImporteimp = $sumaImporteimp + $importe;
                    }
                  }

                  if (count($traslados) != 0) {
                    for ($t = 0; $t < count($traslados); $t++) {
                      //$importe = $traslados[$t]["TipoFactor"] != "Exento" ? $traslados[$t]["Importe"] : 0;
                      //$importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $traslados[$t]["Importe"] : 0;
                      //$sumaImporteimp = $sumaImporteimp + $importe;
                    }
                  }

                  $serie_homologada = $alm_serie != "" ? DB::table("inventarios_catalogo_series")->where("serie_token",$alm_serie)->value("id") : NULL;
                  $lote_homologado = $alm_lote != "" ? DB::table("inventarios_catalogo_lotes")->where("token_lote",$alm_lote)->value("id") : NULL;
                  $pedimento_homologado = $alm_pedimento != "" ? DB::table("inventarios_catalogo_pedimento_aduanal")->where("token_pedimento",$alm_pedimento)->value("id") : NULL;

                  $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
                  
                  $insertDetCompra = DB::table('eegr_compras_detalle')
                    ->insert(array(
                      "token_detcompra" => $tokenDetalleCompra,
                      "numero_compra" => $obtenCompra,
                      "producto" => $token_producto,
                      "servicio" => $token_servicio,
                      "moneda_detalle_compra" => $token_moneda,
                      "tipo_de_cambio_detalle_compra" => $tipoDeCambio,
                      "precio_unitario" => $precioUnitario,
                      "cantidad" => $cantidad,
                      "unidad_medida" => $token_unidad_medida,
                      "descuento" => $total_descuento,
                      "retenciones_total" => $total_retenciones,
                      //"retencion_homologada" => $rete_homologada,
                      "traslados_total" => $total_traslado,
                      //"traslado_homologado" => $tras_homologado,
                      "destino" => $usoArticulo,
                      "efecto_fiscal" => $vItem_efectoFiscalArticulo ? $efectoFiscalArticulo : NULL,
                      "activo_fijo" => $activos_fijos,
                      "activo_intangible" => $activos_intangibles,
                      "serie" => $serie_homologada,	
                      "lote" => $lote_homologado,	
                      "pedimento_aduanal" => $pedimento_homologado,
                      "prorrateo" => $prorratea ? TRUE : FALSE,
                      "empresa" => $vEmp->id,
                    ));
                    //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                    $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                      WHERE detcomp.token_detcompra = ? AND detcomp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$tokenDetalleCompra, $usuario->empresa_token, $usuario->user_token]);


                      if (count($retenciones) != 0) {
                        for ($r = 0; $r < count($retenciones); $r++) {
                          $retencion_traslado = "rete";
                          $impuesto_relacionado = $retenciones[$r]["impuesto_relacionado_token"];
                          $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                          
                          $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$rete_homologada.$retencion_traslado);
                          $insertDetCompra = DB::table('eegr_compras_detalle_impuestos')
                          ->insert(array(
                            "token_imp_det_buy" => $tokenDetBuyImp,
                            "detalle_compra" => $selectDetBuy[0]->id,	
                            "retencion_traslado" => $retencion_traslado,
                            //"base" => $Base,
                            //"impuesto" => $Impuesto,
                            //"tipo_factor" => $TipoFactor,
                            //"tasa_cuota" => $TasaOCuota,
                            //"importe" => $importe,
                            "impuesto_relacionado" => $rete_homologada
                          ));

                        }
                      }
    
                      if (count($traslados) != 0) {
                        for ($t = 0; $t < count($traslados); $t++) {
                          $retencion_traslado = "tras";
                          $impuesto_relacionado = $traslados[$t]["impuesto_relacionado_token"];
                          $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                          
                          $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$tras_homologado.$retencion_traslado);
                          $insertDetCompra = DB::table('eegr_compras_detalle_impuestos')
                          ->insert(array(
                            "token_imp_det_buy" => $tokenDetBuyImp,
                            "detalle_compra" => $selectDetBuy[0]->id,	
                            "retencion_traslado" => $retencion_traslado,
                            //"base" => $Base,
                            //"impuesto" => $Impuesto,
                            //"tipo_factor" => $TipoFactor,
                            //"tasa_cuota" => $TasaOCuota,
                            //"importe" => $importe,
                            "impuesto_relacionado" => $tras_homologado
                          ));
                        }
                      }

                  if ($prorratea) {
                      
                    $folioProrrateo = DB::selectOne("SELECT COALESCE(MAX(fold.folder) + 1, 1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp ON fold.empresa = emp.id
                    JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                    WHERE fold.egr_prorrateos = TRUE AND emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                      
                    //return response()->json(['message' => 'error GeneralesCompra'.$folioProrrateo->folio,'codigo' => 200,'status' => 'error']);
                    $tokenCompraProrrateo = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);

                    //return response()->json(['message' => 'error GeneralesCompra'.$importeMinVi,'codigo' => 200,'status' => 'error']);

                    $insertDetCompra = DB::table('eegr_compras_prorrateos')
                    ->insert(array(
                      "token_prorrateo" => $tokenCompraProrrateo,
                      "folio_prorrateo" => $folioProrrateo->folio,	
                      "fecha_sistema_prorrateo" => time(),	
                      "fecha_prorrateo" => time(),	
                      "producto" => $token_producto,	
                      "servicio" => $token_servicio,	
                      "compra" => $obtenCompra,	
                      "detalle_compra" => $selectDetBuy[0]->id,	
                      "empresa"	 => $vEmp->id,	
                      "status_prorrateo" => TRUE,
                    ));

                    if ($folioProrrateo->folio == 1) {
                      $insertSistema = DB::table('sos_last_folders')
                        ->insert(
                          array(
                            "egr_prorrateos" => TRUE,
                            "folder" => 1,
                            "empresa" => $vEmp->id,
                          )
                        );
                    } else {
                      $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                      ->where([
                        'sos_last_folders.egr_prorrateos' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          'sos_last_folders.folder' => $folioProrrateo->folio,
                        )
                      );
                    }

                    $obten_prorrateo_ident =DB::table("eegr_compras_prorrateos")->where("token_prorrateo",$tokenCompraProrrateo)->value("id");
                    $token_detalle_prorrt = $JwtAuth->encriptarToken(time().$obten_prorrateo_ident.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);
                    $insertDetCompra = DB::table('eegr_compras_prorrateos_detalle')
                    ->insert(array(
                      "token_detalle_prorrt" => $token_detalle_prorrt,
                      "prorrateo" => $obten_prorrateo_ident,	
                      "detalle_compra" => $selectDetBuy[0]->id,
                    ));
                  }

                  if ($token_producto != NULL && $token_producto != '') {
                    $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detcomp.token_detcompra = ? 
                      AND detcomp.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$tokenDetalleCompra, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]
                    );

                    $folioKardex = DB::select("SELECT IF (max(dexkar.folio_kardex) IS NOT NULL,(max(dexkar.folio_kardex)+1),1) AS folio 
                      FROM in_egr_productos_kardex AS dexkar JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE dexkar.producto = catprod.id 
                      AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

                    $token_kardex =  $JwtAuth->encriptarToken(time(),$tokenCompra, $tokenDetalleCompra);

                    $insertKardex = DB::table("in_egr_productos_kardex")
                      ->insert(array(
                        "token_kardex" => $token_kardex,
                        "folio_kardex" => $folioKardex[0]->folio,
                        "fecha_kardex" => time(),
                        "status_kardex" => 2,
                        "producto" => $token_producto,
                        "concepto" => "por recibir",
                        "factura_compra" => $obtenCompra,
                        "detalle_compra" => $selectDetBuy[0]->id,
                        //"factura_venta" => NULL, 
                        //"detalle_venta" => NULL, 
                        "recibir_cantidad" => $cantidad,
                        //"entrada_cantidad" => NULL, 
                        //"entregar_cantidad" => NULL,    
                        //"salida_cantidad" => NULL,  
                        //"saldo_cantidad" => NULL,   
                        "valor_unitario" => $precioUnitario,
                        //"entrada_valor" => NULL,    
                        //"salida_valor" => NULL, 
                        //"saldo_valor" => NULL,
                      ));
                      //return response()->json(['status' => 'error','code' => 200,'message' => "total_descuento ".$total_descuento]);
                      $upDateProducto = DB::table('in_egr_catalogo_productos')
                      ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                      ->where([
                        'in_egr_catalogo_productos.status' => TRUE,
                        'in_egr_catalogo_productos.id' => $token_producto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(array("in_egr_catalogo_productos.ultima_compra" => time(),));
                      $validUpdtProd = $upDateProducto ? true : false;
                  } else {
                    $validUpdtProd = true;
                  }

                  if ($token_servicio != NULL && $token_servicio != '') {
                    $upDateServicio = DB::table('in_egr_catalogo_servicios')
                    ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'in_egr_catalogo_servicios.status' => TRUE,
                      'in_egr_catalogo_servicios.id' => $token_servicio,
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));
                    $validUpdtServ = $upDateServicio ? true : false;
                  } else {
                    $validUpdtServ = true;
                  }

                  if ($insertDetCompra && $validUpdtProd == true && $validUpdtServ == true) {
                    ++$contadorDetallecompra;
                  }
                }

                if ($insertCompra && $contadorDetallecompra == count($compra_conceptos)) {
                  $JwtAuth->insertBitacoraActividad(
                    'egresos',
                    'compras',
                    'compras',
                    $folio_buy,
                    'registro en el alta de compras',
                    $usuario->empresa_token,
                    $usuario->user_token
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
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
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
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Esta compra no fue terminada debido a errores internos'
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
          if (!$validate_total) {$mensaje_error_main = 'Error en total de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_prov) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique su información o comuniquese a soporte';}
          if (!$validate_compra_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
          if (!$validate_compra_contado_credito) {$mensaje_error_main = 'Error en seleccion de compra a crédito o contado, verifique su información o comuniquese a soporte';}
          if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información o comuniquese a soporte';}
          //if (!$validate_tipoLugarEntrega) {$mensaje_error_main = 'No se encontro respuesta a seleccion de lugar de entrega sobre esta compra, verifique su información o comuniquese a soporte';}
          if (!file_exists($request->file('imagenEvidenciaXMl'))) {$mensaje_error_main = 'Debe cargar la factura en formato xml correspondiente a esta compra';}
          if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarCompraByINSTRUCCION(Request $request){
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

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_proveedor' => 'required|string',
        'token_formaPago' => 'required|string',
        'token_metodoPago' => 'required|string',
        'compra_contado_credito' => 'string',
        'token_moneda' => 'required|string',
        'tipoDeCambio' => 'required|numeric',
        'anticipoValor' => 'string',
        'classRecibeArtPago' => 'required|boolean',
        'totalPagoCompra' => 'required|string',
        'pagoTesoreriaCaja' => 'string',
        'datosCajaToken' => 'string',
        'array_desgloceCompra' => 'required|array',
        'tipoLugarEntrega' => 'required|string',
        'tknLugarRecepcion' => 'required|string',
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
        $patrónNum = '/^[0-9$,.-]*$/';
        $patrónNumCosto = '/^[0-9$,.-]*$/';
        $patrónRfc = '/[aA0-zZ9]/';
        $patrónFecha = '/^[0-9-]*$/';

        $moneda_decimales = 0;
        $token_proveedor = $parametrosArray['token_proveedor'];
        $token_formaPago = $parametrosArray['token_formaPago'];
        $token_metodoPago = $parametrosArray['token_metodoPago'];
        $compra_contado_credito = $parametrosArray['compra_contado_credito'];
        $moneda_codigo = $parametrosArray['token_moneda'];
        $tipoDeCambio = $parametrosArray['tipoDeCambio'];
        $anticipoValor = $parametrosArray['uuid_anticipo'];
        $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
        $totalPagoCompra = $parametrosArray['totalPagoCompra'];
        $pagoTesoreriaCaja = $parametrosArray['pagoTesoreriaCaja'];
        $datosCajaToken = $parametrosArray['datosCajaToken'];
        $array_desgloceCompra = $parametrosArray['array_desgloceCompra'];
        $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
        $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
        $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && $idProveedor != "";
        $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);
        $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega) && isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);
        $validate_moneda_codigo = isset($moneda_codigo) && !empty($moneda_codigo);
        $validate_array_desgloceCompra = isset($array_desgloceCompra) && !empty($array_desgloceCompra) && is_array($array_desgloceCompra);
        $valida_f_pago = isset($token_formaPago) && !empty($token_formaPago);
        $valida_m_pago = isset($token_metodoPago) && !empty($token_metodoPago); 

        if ($permisosCreacion && $validate_prov && $validate_classRecibeArtPago && $validate_tipoLugarEntrega && $validate_moneda_codigo && $validate_array_desgloceCompra &&
          $valida_f_pago && $valida_m_pago) {

          $moneda_decimales = $JwtAuth->getMonedaAPI($moneda_codigo);

          $idDireccionProv = DB::table("teci_direcciones AS dir")
          ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
          ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
          ->value("dir.id");

          $idDireccionEst = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id");
          if (($tipoLugarEntrega == 'proveedor' && $idDireccionProv == "") || ($tipoLugarEntrega == 'establecimiento' && $idDireccionEst == "")) {
            $dataMensaje = array('status' => 'error','code' => 200,'message' => 'El lugar de recepción seleccionado no encontrado, verifique su información');
          }

          $detalleErrores = "";
          foreach ($array_desgloceCompra as $vDet) {
            $tokenArticulo = $vDet['token_articulo'];
            $identificador = $vDet['identificador'];
            $concepto = $vDet['concepto'];
            $precioUnitario = $vDet['precioUnitario'];
            $cantidad = $vDet['cantidad_registro'];
            //return response()->json(['status' => 'error','code' => 200,'message' => $cantidad]);
            $descuentoXUni = $vDet['descuentoUnidadRegistro'];
            $iva = 0;
            $retenciones = $vDet['retencion_importeRegistro'];
            $retenciones_homologada = $vDet['retencion_token'];
            $traslados = $vDet['traslado_importeRegistro'];
            $traslados_homologada = $vDet['traslado_token'];
            $usoArticulo = $vDet['articulo_homologado_uso'];
            $activoFijo = $vDet['articulo_homologado_activoFijo'];
            $activoIntangible = $vDet['articulo_homologado_activoIntangible'];
            $prorratea = $vDet['articulo_homologado_prorratea'];
            $periodicidadPc = $vDet['articulo_homologado_periodicidadPc'];
            $iteracionPc = $vDet['articulo_homologado_iteracionPc'];
            $periodoDetIndPc = $vDet['articulo_homologado_periodoDetIndPc'];
            $fechaFinPc = $vDet['articulo_homologado_fechaFinPc'];
            $tipoImporteVi = $vDet['articulo_homologado_tipoImporteVi'];
            $importeMinVi = $vDet['articulo_homologado_importeMinVi']; //importeMinVi
            $importeMaxVi = $vDet['articulo_homologado_importeMaxVi'];
            $importe = $JwtAuth->rellenaImportesCompras($vDet['totalConImpuesto']);
            //return response()->json(['message' => 'pais11','codigo' => 200,'status' => 'error']);
            $validateActivos = false;
            $validatePeriodicidad = false;
            $validateDescuentos = false;
            $validateDecimalesMoneda = false;
            $validateForImpuRetenciones = false;
            $validateForImpuTraslados = false;

            $vItem_tokenArticulo = isset($tokenArticulo) && !empty($tokenArticulo);
            $vItem_identificador = isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador);
            $vItem_precioUnitario = isset($precioUnitario) && !empty($precioUnitario) && preg_match($patrónNumCosto, $precioUnitario);
            $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($patrónNum, $cantidad);
            //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
            $vItem_usoArticulo = isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo);
            $vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
            $vItem_importe = isset($importe) && !empty($importe) && preg_match($patrónNumCosto, $importe);

            if ($vItem_tokenArticulo && $vItem_identificador && $vItem_precioUnitario && $vItem_cantidad && $vItem_usoArticulo && $vItem_periodicidadPc && $vItem_importe) {
              if (isset($descuentoXUni) && !empty($descuentoXUni)) {
                if ($descuentoXUni != '---') {
                  if (preg_match($patrónNumCosto, $descuentoXUni)) {
                    $strPosdescuentoXUni = strpos($descuentoXUni, '.');
                    if ($strPosdescuentoXUni !== FALSE) {
                      $expdescuentoXUni = explode('.', $descuentoXUni);
                      if ($moneda_decimales == strlen($expdescuentoXUni[1])) {
                        $validateDescuentos = true;
                      } else {
                        $validateDescuentos = false;
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'La cantidad de decimales del descuento no coincide con los decimales que soporta la moneda seleccionada'
                        );
                      }
                    } else {
                      $validateDescuentos = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  } else {
                    $validateDescuentos = false;
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Descuento invalido'
                    );
                  }
                } else {
                  $validateDescuentos = true;
                }
              } else {
                $validateDescuentos = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La cantidad de descuento es invalida o inexistente'
                );
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
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                    );
                  }
                } else {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                  );
                }
              }

              if ($moneda_decimales == 0) {
                $strPosPrecioUnit = strpos($precioUnitario, '.');
                $strPosimporte = strpos($importe, '.');
                if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El precio unitario del producto/servicio no tiene decimales'
                  );
                } else {
                  $validateDecimalesMoneda = true;
                }
              }

              if ($usoArticulo == 'activo_fijo') {
                if (isset($activoFijo) && !empty($activoFijo) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoFijo)) {
                  $validateActivos = true;
                } else {
                  $validateActivos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El activo del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              } else if ($usoArticulo == 'activo_intangible') {
                if (isset($activoIntangible) && !empty($activoIntangible) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoIntangible)) {
                  $validateActivos = true;
                } else {
                  $validateActivos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El descuento del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              } else {
                $validateActivos = true;
              }

              if ($periodicidadPc == 'periodo') {
                //return response()->json(['message' => 'error desglose'.$importe,'codigo' => 200,'status' => 'error']);
                if (
                  isset($iteracionPc) && !empty($iteracionPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $iteracionPc) &&
                  isset($periodoDetIndPc) && !empty($periodoDetIndPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc) &&
                  isset($tipoImporteVi) && !empty($tipoImporteVi) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)  &&
                  isset($importeMinVi) && !empty($importeMinVi) && preg_match($patrónNumCosto, $importeMinVi) &&
                  isset($importeMaxVi) && !empty($importeMaxVi) && preg_match($patrónNumCosto, $importeMaxVi)
                ) {
                  if ($periodoDetIndPc == 'determinado') {
                    if (isset($fechaFinPc) && !empty($fechaFinPc) && preg_match($patrónFecha, $fechaFinPc)) {
                      $validatePeriodicidad = true;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La fecha de fin de periodo de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente '
                      );
                      break;
                    }
                  }
                  if ($periodoDetIndPc == 'indeterminado') {
                    $validatePeriodicidad = true;
                  }

                  if ($moneda_decimales != 0) {
                    $strPosimporteMinVi = strpos($importeMinVi, '.');
                    $strPosimporteMaxVi = strpos($importeMaxVi, '.');

                    if ($strPosimporteMinVi !== FALSE && $strPosimporteMaxVi !== FALSE) {
                      $expimporteMinVi = explode('.', $importeMinVi);
                      $expimporteMaxVi = explode('.', $importeMaxVi);

                      if (
                        $moneda_decimales == strlen($expimporteMinVi[1]) &&
                        $moneda_decimales == strlen($expimporteMaxVi[1])
                      ) {
                        $validateDecimalesMoneda = true;
                      } else {
                        $validateDecimalesMoneda = false;
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                        );
                      }
                    } else {
                      $validateDecimalesMoneda = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  }

                  if ($moneda_decimales == 0) {
                    $strPosimporteMinVi = strpos($importeMinVi, '.');
                    $strPosimporteMaxVi = strpos($importeMaxVi, '.');

                    if ($strPosimporteMinVi !== FALSE && $strPosimporteMaxVi !== FALSE) {
                      $validateDecimalesMoneda = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'El precio unitario del producto/servicio no tiene decimales'
                      );
                    } else {
                      $validateDecimalesMoneda = true;
                    }
                  }
                } else {
                  $validatePeriodicidad = false;
                  if (!isset($iteracionPc) || empty($iteracionPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $iteracionPc)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La iteración (repetición) de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente'
                    );
                    break;
                  }
                  if (!isset($periodoDetIndPc) || empty($periodoDetIndPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La selección de periodo de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente'
                    );
                    break;
                  }

                  if (!isset($tipoImporteVi) || empty($tipoImporteVi) || !preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El tipo de variablidilad de importe del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                  if (!isset($importeMinVi) || empty($importeMinVi) || !preg_match($patrónNumCosto, $importeMinVi)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El importe mínimo de variabilidad del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                  if (!isset($importeMaxVi) || empty($importeMaxVi) || !preg_match($patrónNumCosto, $importeMaxVi)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El importe maximo de variabilidad del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                }
              }
              if ($periodicidadPc == 'eventual') {
                $validatePeriodicidad = true;
              }
            } else {
              if (!$vItem_tokenArticulo) {$detalleErrores = 'producto/servicio '.$concepto.' invalidado';}
              if (!$vItem_identificador) {$detalleErrores = 'identificador del producto/servicio '.$concepto.' es incorrecto o inexistente';}
              if (!$vItem_precioUnitario) {$detalleErrores = 'El precio unitario del producto/servicio '.$concepto.' es invalido o inexistente';}
              if (!$vItem_cantidad) {$detalleErrores = 'La cantidad del producto/servicio '.$concepto.' es invalida o inexistente';}
              if (!$vItem_usoArticulo) {$detalleErrores = 'El uso del producto/servicio '.$concepto.' es invalido o inexistente';}
              if (!$vItem_periodicidadPc) {$detalleErrores = 'La periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente';}
              if (!$vItem_importe) {$detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';}
              break;
            }
          }
          
          if ($detalleErrores == "") {
            $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            foreach ($queryEmp as $vEmp) {
              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

              $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token, $usuario->user_token]);

              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
              $folio_buy = 'COMP-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');
              //return response()->json(['message' => $folio_buy,'codigo' => 200,'status' => 'error']);
              $nombreRecePago = '';
              $tokenCompra = $JwtAuth->encriptarToken(time(), $token_proveedor, $moneda_codigo, $tipoLugarEntrega, $tknLugarRecepcion, $array_desgloceCompra);
              $fechaSistema = time();
              $anticipo = $anticipoValor != '' ? $anticipoValor : NULL;
              $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
              $status_autorizacion = $vEmp->jerarquia_main == 'P' ? TRUE : FALSE;
              $nombreDocs = $fechaSistema."-".$folio_buy;
              $compras = new ComprasModelo();
              $compras->token_compras = $tokenCompra;
              $compras->folio_compra = $folio_nuevo;
              $compras->post_folio = $post_folio;
              $compras->fecha_sistemaCompras = $fechaSistema;
              $compras->fecha_altaCompra = time();
              $compras->proveedor = $idProveedor;
              $compras->compra_a_credito = $compra_contado_credito == 'contado' ? 'cont' : 'cred';
              $compras->recibeFactura = FALSE;
              $compras->recepcionPago = $nombreRecePago; //cifrado
              $compras->anexos = $JwtAuth->encriptar($nombreDocs.".pdf");  //cifrado
              $compras->reporte = $JwtAuth->encriptar($nombreDocs.".pdf"); //cifrado
              $compras->moneda = $moneda_codigo;
              $compras->anticipo = $anticipo;
              $compras->forma_pago = $token_formaPago;
              $compras->metodo_pago = $token_metodoPago;

              $compras->recibeProducto = $classRecibeArtPago ? TRUE : FALSE;
              $compras->pago_caja_tesoreria = NULL;
              $compras->caja_paga = NULL;

              $compras->recepcion_prov = $tipoLugarEntrega == 'proveedor' ? $idDireccionProv : NULL;
              $compras->recepcion_estab = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
              $compras->comprador = $vEmp->id;
              $compras->usuario_comprador = $vEmp->userr;
              $compras->status_autorizacion = $status_autorizacion;
              $compras->autoriza = $user_jerarquia;
              $compras->status_cancelacion = FALSE;
              $compras->cancela = NULL;
              $compras->status_recepcion = FALSE;
              $compras->recibe = NULL;
              $compras->fecha_delete_compra = '';
              $compras->status_compra = TRUE;
              $insertCompra = $compras->save();
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden']);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
              if ($insertCompra) {
                $obtenCompra = $compras->id;
                $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";

                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

                $validate_insert_ord_pago = false;
                if ($vEmp->jerarquia_main == 'P') {
                  $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                  $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra);

                  $orderpay = new OrdenPagoModelo();
                  $orderpay->token_ordenPago = $tknOrder;
                  $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                  $orderpay->fecha_sistema_ordenp = $fechaSistema;
                  //$orderpay->fecha_contabilizacion_ordenPago = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor;
                  //$orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orderpay->autorizacion_pay = $classRecibeArtPago ? TRUE : FALSE;
                  $orderpay->fecha_autorizacion_pay = $classRecibeArtPago ? time() : NULL;
                  $orderpay->tentativa_pago = $classRecibeArtPago ? time() : NULL;
                  $orderpay->orden_terminada_bool = $classRecibeArtPago ? TRUE : FALSE;
                  $orderpay->orden_terminada_fecha = $classRecibeArtPago ? time() : NULL;
                  $orderpay->status_ordenPago = $classRecibeArtPago ? TRUE : FALSE;  //cifrado
                  $orderpay->empresa = $vEmp->id;    //cifrado
                  $orderpay->comprador = $vEmp->userr; //cifrado
                  $insertOrder = $orderpay->save();
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                  if ($insertOrder) {
                    $validate_insert_ord_pago = true;
                  } 
                }

                $contadorDetallecompra = 0;
                for ($i = 0; $i < count($array_desgloceCompra); $i++) {
                  $validUpdtProd = false;
                  $validUpdtServ = false;
                  $tokenArticulo = $array_desgloceCompra[$i]['token_articulo'];
                  $identificador = $array_desgloceCompra[$i]['identificador'];
                  $concepto = $array_desgloceCompra[$i]['concepto'];
                  $moneda_code = $array_desgloceCompra[$i]['moneda_code'];
                  $tipoCambio = $array_desgloceCompra[$i]['tipoCambio'];
                  $precioUnitario = $array_desgloceCompra[$i]['precioUnitario'];
                  $cantidad = $array_desgloceCompra[$i]['cantidad_registro'];
                  $descuentoXUni = $array_desgloceCompra[$i]['descuentoUnidadRegistro'];
                  $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
                  $iva = 0;
                  $retenciones = $array_desgloceCompra[$i]['retencion_importeRegistro'];
                  $retenciones_homologada = $array_desgloceCompra[$i]['retencion_token'];
                  $traslados = $array_desgloceCompra[$i]['traslado_importeRegistro'];
                  $traslados_homologada = $array_desgloceCompra[$i]['traslado_token'];
                  $usoArticulo = $array_desgloceCompra[$i]['articulo_homologado_uso'];
                  $activoFijo = $array_desgloceCompra[$i]['articulo_homologado_activoFijo'];
                  $activoIntangible = $array_desgloceCompra[$i]['articulo_homologado_activoIntangible'];
                  $prorratea = $array_desgloceCompra[$i]['articulo_homologado_prorratea'];
                  $periodicidadPc = $array_desgloceCompra[$i]['articulo_homologado_periodicidadPc'];
                  $iteracionPc = $array_desgloceCompra[$i]['articulo_homologado_iteracionPc'];
                  $periodoDetIndPc = $array_desgloceCompra[$i]['articulo_homologado_periodoDetIndPc'];
                  $fechaFinPc = $array_desgloceCompra[$i]['articulo_homologado_fechaFinPc'];
                  $tipoImporteVi = $array_desgloceCompra[$i]['articulo_homologado_tipoImporteVi'];
                  $importeMinVi = $array_desgloceCompra[$i]['articulo_homologado_importeMinVi']; //importeMinVi
                  $importeMaxVi = $array_desgloceCompra[$i]['articulo_homologado_importeMaxVi'];
                  $importe = $array_desgloceCompra[$i]['totalConImpuesto'];
                  $token_unidad_medida = $array_desgloceCompra[$i]['unidadMedida'];

                  $token_producto = '';
                  $token_servicio = '';
                  $activos_fijos = '';
                  $activos_intangibles = '';
                  $boolprorratea = FALSE;
                  $boolperiodicidadPc = FALSE;
                  $txtiteracionPc = NULL;
                  $boolperiodoDetIndPc = FALSE;
                  $txtfechaFinPc = NULL;

                  $catProdServ = DB::table(DB::raw('(SELECT
                    CASE
                      WHEN ? IN (
                        SELECT token_cat_productos 
                        FROM in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp ON catprod.admin_empresa = emp.id
                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa
                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                        WHERE catprod.modulo_mostrador = FALSE 
                          AND catprod.status = TRUE 
                          AND emp.empresa_token = ?
                          AND users.usuario_token = ?
                      ) THEN "Producto"
                      WHEN ? IN (
                        SELECT token_cat_servicios 
                        FROM in_egr_catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp ON catserv.administrador = emp.id
                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa 
                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                        WHERE catserv.proceso = "c" 
                          AND catserv.status = TRUE 
                          AND emp.empresa_token = ? 
                          AND users.usuario_token = ?
                      ) THEN "Servicio"
                    END AS identificador) AS subconsulta'))
                  ->select('identificador')
                  ->setBindings([
                    $tokenArticulo,
                    $usuario->empresa_token,
                    $usuario->user_token,
                    $tokenArticulo,
                    $usuario->empresa_token,
                    $usuario->user_token
                  ])
                  ->value("identificador");

                  $token_producto = $catProdServ == 'Producto' ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$tokenArticulo)->value("id") : NULL;
                  $token_servicio = $catProdServ == 'Producto' ? NULL : DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$tokenArticulo)->value("id");
                  $activos_fijos = $catProdServ == 'Producto' && $usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo) ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos",$activoFijo)->value("id") : NULL;
                  $activos_intangibles = $catProdServ == 'Servicio' && $usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible) ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang",$activoIntangible)->value("id") : NULL;

                  $tokenDetalleCompra = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$tokenArticulo.$identificador.$concepto.$precioUnitario.$cantidad.
                    $total_descuento.$iva.$usoArticulo.$activoFijo.$activoIntangible.$periodicidadPc.$iteracionPc.$periodoDetIndPc.$fechaFinPc.$tipoImporteVi.$importeMinVi.$importeMaxVi.$importe);

                  $boolperiodicidadPc = $periodicidadPc == 'periodo' ? TRUE : FALSE;
                  $txtiteracionPc = $periodicidadPc == 'periodo' ? $iteracionPc : NULL;
                  $boolperiodoDetIndPc = $periodicidadPc == 'periodo' && $periodoDetIndPc == 'determinado' ? TRUE : FALSE;
                  $txtfechaFinPc = $periodicidadPc == 'periodo' && $periodoDetIndPc == 'determinado' ? $JwtAuth->convierteFechaEpoc($fechaFinPc) : NULL;

                  //return response()->json(['status' => 'error','code' => 200,'message' => $total_traslado]);
                  //return response()->json(['status' => 'error','code' => 200,'message' => $total_descuento]);

                  $rete_homologada = $retenciones_homologada != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$retenciones_homologada)->value("id") : NULL;
                  $tras_homologado = $traslados_homologada != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$traslados_homologada)->value("id") : NULL;
                  
                  $insertDetCompra = DB::table('eegr_compras_detalle')
                    ->insert(array(
                      "token_detcompra" => $tokenDetalleCompra,
                      "numero_compra" => $obtenCompra,
                      "producto" => $token_producto,
                      "servicio" => $token_servicio,
                      "moneda_detalle_compra" => $moneda_code,
                      "tipo_de_cambio_detalle_compra" => $tipoCambio,
                      "precio_unitario" => $precioUnitario,
                      "cantidad" => $cantidad,
                      "unidad_medida" => $token_unidad_medida,
                      "descuento" => $total_descuento,
                      "retenciones_total" => $retenciones,
                      "retencion_homologada" => $rete_homologada,
                      "traslados_total" => $traslados,
                      "traslado_homologado" => $tras_homologado,
                      "destino" => $usoArticulo,
                      "activo_fijo" => $activos_fijos,
                      "activo_intangible" => $activos_intangibles,
                      "prorrateo" => $prorratea ? TRUE : FALSE,
                      "empresa" => $vEmp->id,
                    ));
                    //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                  if ($prorratea) {
                    $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                      WHERE detcomp.token_detcompra = ? AND detcomp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$tokenDetalleCompra, $usuario->empresa_token, $usuario->user_token]);
                      
                    $folioProrrateo = DB::selectOne("SELECT COALESCE(MAX(fold.folder) + 1, 1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp ON fold.empresa = emp.id
                    JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                    WHERE fold.egr_prorrateos = TRUE AND emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                      
                    //return response()->json(['message' => 'error GeneralesCompra'.$folioProrrateo->folio,'codigo' => 200,'status' => 'error']);
                    $tokenCompraProrrateo = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);

                    //return response()->json(['message' => 'error GeneralesCompra'.$importeMinVi,'codigo' => 200,'status' => 'error']);

                    $insertDetCompra = DB::table('eegr_compras_prorrateos')
                    ->insert(array(
                      "token_prorrateo" => $tokenCompraProrrateo,
                      "folio_prorrateo" => $folioProrrateo->folio,	
                      "fecha_sistema_prorrateo" => time(),	
                      "fecha_prorrateo" => time(),	
                      "producto" => $token_producto,	
                      "servicio" => $token_servicio,	
                      "compra" => $obtenCompra,	
                      "detalle_compra" => $selectDetBuy[0]->id,	
                      "empresa"	 => $vEmp->id,	
                      "status_prorrateo" => TRUE,
                    ));

                    if ($folioProrrateo->folio == 1) {
                      $insertSistema = DB::table('sos_last_folders')
                        ->insert(
                          array(
                            "egr_prorrateos" => TRUE,
                            "folder" => 1,
                            "empresa" => $vEmp->id,
                          )
                        );
                    } else {
                      $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                      ->where([
                        'sos_last_folders.egr_prorrateos' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          'sos_last_folders.folder' => $folioProrrateo->folio,
                        )
                      );
                    }

                    $obten_prorrateo_ident =DB::table("eegr_compras_prorrateos")->where("token_prorrateo",$tokenCompraProrrateo)->value("id");
                    $token_detalle_prorrt = $JwtAuth->encriptarToken(time().$obten_prorrateo_ident.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);
                    $insertDetCompra = DB::table('eegr_compras_prorrateos_detalle')
                    ->insert(array(
                      "token_detalle_prorrt" => $token_detalle_prorrt,
                      "prorrateo" => $obten_prorrateo_ident,	
                      "detalle_compra" => $selectDetBuy[0]->id,
                    ));
                  }

                  if ($token_producto != NULL && $token_producto != '') {
                    $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detcomp.token_detcompra = ? 
                      AND detcomp.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$tokenDetalleCompra, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]
                    );

                    $folioKardex = DB::select("SELECT IF (max(dexkar.folio_kardex) IS NOT NULL,(max(dexkar.folio_kardex)+1),1) AS folio 
                      FROM in_egr_productos_kardex AS dexkar JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE dexkar.producto = catprod.id 
                      AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

                    $token_kardex =  $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra, $tokenDetalleCompra);

                    $insertKardex = DB::table("in_egr_productos_kardex")
                      ->insert(array(
                        "token_kardex" => $token_kardex,
                        "folio_kardex" => $folioKardex[0]->folio,
                        "fecha_kardex" => time(),
                        "status_kardex" => 2,
                        "producto" => $token_producto,
                        "concepto" => "por recibir",
                        "factura_compra" => $obtenCompra,
                        "detalle_compra" => $selectDetBuy[0]->id,
                        //"factura_venta" => NULL, 
                        //"detalle_venta" => NULL, 
                        "recibir_cantidad" => $cantidad,
                        //"entrada_cantidad" => NULL, 
                        //"entregar_cantidad" => NULL,    
                        //"salida_cantidad" => NULL,  
                        //"saldo_cantidad" => NULL,   
                        "valor_unitario" => $precioUnitario,
                        //"entrada_valor" => NULL,    
                        //"salida_valor" => NULL, 
                        //"saldo_valor" => NULL,
                      ));
                      //return response()->json(['status' => 'error','code' => 200,'message' => "total_descuento ".$total_descuento]);
                      $upDateProducto = DB::table('in_egr_catalogo_productos')
                      ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                      ->where([
                        'in_egr_catalogo_productos.status' => TRUE,
                        'in_egr_catalogo_productos.id' => $token_producto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(array("in_egr_catalogo_productos.ultima_compra" => time(),));


                    if ($boolperiodicidadPc == FALSE) {
                      //echo $selectPseudoCompra[$pc]->periodicidad;
                      $validUpdtProd = true;
                    } else {
                      $selector = DB::select("SELECT periodicidad,repeticion_periodo,tipo_periodo,fecha_finPeriodo,tipo_variabilidad,importe_minimo,importe_maximo 
                        FROM in_egr_catalogo_productos WHERE id = ?", [$token_producto]);

                      if (
                        $selector[0]->periodicidad == NULL && $selector[0]->repeticion_periodo == NULL &&
                        $selector[0]->tipo_periodo == NULL && $selector[0]->fecha_finPeriodo == NULL &&
                        $selector[0]->tipo_variabilidad == NULL && $selector[0]->importe_minimo == NULL &&
                        $selector[0]->importe_maximo == NULL
                      ) {

                        $upDateProducto = DB::table('in_egr_catalogo_productos')
                          ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                          ->where([
                            'in_egr_catalogo_productos.status' => TRUE,
                            'in_egr_catalogo_productos.id' => $token_producto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                          ])
                          ->limit(1)->update(
                            array(
                              "in_egr_catalogo_productos.periodicidad" => $boolperiodicidadPc,
                              "in_egr_catalogo_productos.repeticion_periodo" => $txtiteracionPc,
                              "in_egr_catalogo_productos.tipo_periodo" => $boolperiodoDetIndPc,
                              "in_egr_catalogo_productos.fecha_finPeriodo" => $txtfechaFinPc,
                              "in_egr_catalogo_productos.tipo_variabilidad" => $tipoImporteVi,
                              "in_egr_catalogo_productos.importe_minimo" => $importeMinVi,
                              "in_egr_catalogo_productos.importe_maximo" => $importeMaxVi,
                            )
                          );

                        $validUpdtProd = $upDateProducto ? true : false;
                      } else {
                        $validUpdtProd = true;
                      }
                    }
                  } else {
                    $validUpdtProd = true;
                  }

                  if ($token_servicio != NULL && $token_servicio != '') {

                    $upDateServicio = DB::table('in_egr_catalogo_servicios')
                    ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'in_egr_catalogo_servicios.status' => TRUE,
                      'in_egr_catalogo_servicios.id' => $token_servicio,
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));

                    if ($boolperiodicidadPc == FALSE) {
                      $validUpdtServ = true;
                    } else {
                      //return response()->json(['message' => 'error GeneralesCompra'.$importeMinVi,'codigo' => 200,'status' => 'error']);
                      $selector = DB::select("SELECT periodicidad,repeticion_periodo,tipo_periodo,fecha_finPeriodo,tipo_variabilidad,importe_minimo,importe_maximo 
                        FROM in_egr_catalogo_servicios WHERE id = ?", [$token_servicio]);

                      if (
                        $selector[0]->periodicidad == NULL && $selector[0]->repeticion_periodo == NULL &&
                        $selector[0]->tipo_periodo == NULL && $selector[0]->fecha_finPeriodo == NULL &&
                        $selector[0]->tipo_variabilidad == NULL && $selector[0]->importe_minimo == NULL &&
                        $selector[0]->importe_maximo == NULL
                      ) {
                        $upDateServicio = DB::table('in_egr_catalogo_servicios')
                          ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                          ->where([
                            'in_egr_catalogo_servicios.status' => TRUE,
                            'in_egr_catalogo_servicios.id' => $token_servicio,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                          ])
                          ->limit(1)->update(
                            array(
                              "in_egr_catalogo_servicios.periodicidad" => $boolperiodicidadPc,
                              "in_egr_catalogo_servicios.repeticion_periodo" => $txtiteracionPc,
                              "in_egr_catalogo_servicios.tipo_periodo" => $boolperiodoDetIndPc,
                              "in_egr_catalogo_servicios.fecha_finPeriodo" => $txtfechaFinPc,
                              "in_egr_catalogo_servicios.tipo_variabilidad" => $tipoImporteVi,
                              "in_egr_catalogo_servicios.importe_minimo" => $importeMinVi,
                              "in_egr_catalogo_servicios.importe_maximo" => $importeMaxVi,
                            )
                          );

                        if ($upDateServicio) {
                          $validUpdtServ = true;
                        } else {
                          $validUpdtServ = false;
                        }
                      } else {
                        $validUpdtServ = true;
                      }
                    }
                  } else {
                    $validUpdtServ = true;
                  }

                  if ($insertDetCompra && $validUpdtProd == true && $validUpdtServ == true) {
                    ++$contadorDetallecompra;
                  }
                }

                if ($insertCompra && $contadorDetallecompra == count($array_desgloceCompra)) {
                  $JwtAuth->insertBitacoraActividad(
                    'egresos',
                    'compras',
                    'compras',
                    $folio_buy,
                    'registro en el alta de compras',
                    $usuario->empresa_token,
                    $usuario->user_token
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
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
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
                    'status' => 'success'
                  );
                }

              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Esta compra no fue terminada debido a errores internos'
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
          if (!$validate_prov) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique la información de su proveedor';}
          if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información';}
          if (!$validate_tipoLugarEntrega) {$mensaje_error_main = 'No se encontro respuesta a seleccion de lugar de entrega sobre esta compra, verifique su información';}
          if (!$validate_moneda_codigo) {$mensaje_error_main = 'No se encontro respuesta a seleccion de moneda sobre esta compra, verifique su información';}
          if (!$validate_array_desgloceCompra) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información';}
          if (!$valida_f_pago) {$mensaje_error_main = 'Error en forma de pago seleccionada, verifique su información';}
          if (!$valida_m_pago) {$mensaje_error_main = 'Error en método de pago seleccionado, verifique su información';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarCompraByReembolso(Request $request){
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

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_reem' => 'required|string',
        'token_solicitud_reem' => 'required|string',
        'fecha_contabilizacion' => 'required|string',
        'fecha_vencimiento' => 'required|string',
        'total' => 'required|string', 
        'token_proveedor' => 'required|string',
        'dataCFDI_comprobante_fecha' => 'required|string',
        'cfdi_TipoCambio' => 'required|string',
        'cfdi_Moneda' => 'required|string',
        'cfdi_MoneDecimales' => 'required|string',
        'dataCFDI_comprobante_formaPago' => 'required|string',
        'dataCFDI_comprobante_MetodoPago' => 'required|string',
        'dataCFDI_receptor_UsoCFDI' => 'required|string',
        'cfdi_conceptos' => 'required|array',
        'cfdi_impuestos_retenidos' => 'array',
        'cfdi_impuestos_trasladados' => 'array',
        'compra_contado_credito' => 'required|string',
        'receptFactura' => 'required|boolean',
        'anticipoValor' => 'string',
        'classRecibeArtPago' => 'required|boolean',
        'tipoLugarEntrega' => 'required|string',
        'tknLugarRecepcion' => 'string',
        'compra_observaciones' => 'string'
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
        $patrónNum = '/^[0-9$,.-]*$/';
        $patrónNumCosto = '/^[0-9$,.-]*$/';
        $patrónRfc = '/[aA0-zZ9]/';
        $patrónFecha = '/^[0-9-]*$/';
        
        $moneda_decimales = 0;
        $token_reem = $parametrosArray['token_reem'];
        $token_solicitud_reem = $parametrosArray['token_solicitud_reem'];
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fecha_vencimiento = $parametrosArray['fecha_vencimiento'];
        $total = $parametrosArray['total'];
        $token_proveedor = $parametrosArray['token_proveedor'];
        
        $cfdi_comprobante_fecha = $parametrosArray['dataCFDI_comprobante_fecha'];
        $cfdi_TipoCambio = $parametrosArray['cfdi_TipoCambio'];
        $cfdi_Moneda = $parametrosArray['cfdi_Moneda'];
        $cfdi_MoneDecimales = $parametrosArray['cfdi_MoneDecimales'];
        $dataCFDI_comprobante_formaPago = $parametrosArray['dataCFDI_comprobante_formaPago'];
        $dataCFDI_comprobante_MetodoPago = $parametrosArray['dataCFDI_comprobante_MetodoPago'];
        $dataCFDI_receptor_UsoCFDI = $parametrosArray['dataCFDI_receptor_UsoCFDI'];
        $cfdi_conceptos = $parametrosArray['cfdi_conceptos'];
        $cfdi_impuestos_retenidos = $parametrosArray['cfdi_impuestos_retenidos'];
        $cfdi_impuestos_trasladados = $parametrosArray['cfdi_impuestos_trasladados'];
        $compra_contado_credito = $parametrosArray['compra_contado_credito'];
        $receptFactura = $parametrosArray['receptFactura'] ? $parametrosArray['tipoDeCambio'] : '1.00';
        $anticipoValor = $parametrosArray['uuid_anticipo'];
        $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
        $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
        $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];
        $compra_observaciones = $parametrosArray['compra_observaciones'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);

        $validate_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $validate_fecha_vencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
        $validate_total = isset($total) && !empty($total);
        $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
        $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && $idProveedor != "";
        
        $cfdi_comprobante_fecha = $parametrosArray['dataCFDI_comprobante_fecha'];
        $cfdi_TipoCambio = $parametrosArray['cfdi_TipoCambio'];
        $cfdi_Moneda = $parametrosArray['cfdi_Moneda'];
        $cfdi_MoneDecimales = $parametrosArray['cfdi_MoneDecimales'];
        $dataCFDI_comprobante_formaPago = $parametrosArray['dataCFDI_comprobante_formaPago'];
        $dataCFDI_comprobante_MetodoPago = $parametrosArray['dataCFDI_comprobante_MetodoPago'];
        $dataCFDI_receptor_UsoCFDI = $parametrosArray['dataCFDI_receptor_UsoCFDI'];

        $validate_cfdi_conceptos = isset($cfdi_conceptos) && !empty($cfdi_conceptos) && is_array($cfdi_conceptos);
        $validate_cfdi_impuestos_retenidos = isset($cfdi_impuestos_retenidos) && is_array($cfdi_impuestos_retenidos);
        $validate_cfdi_impuestos_trasladados = isset($cfdi_impuestos_trasladados) && is_array($cfdi_impuestos_trasladados);
        $validate_compra_contado_credito = isset($compra_contado_credito) && !empty($compra_contado_credito) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_contado_credito);
        $validate_anticipoValor = isset($anticipoValor) && !empty($anticipoValor);
        $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);
        $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega) && isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);
        $validate_compra_observaciones = isset($compra_observaciones) && !empty($compra_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_observaciones);

        if ($validate_fecha_contabilizacion && $validate_fecha_vencimiento && $permisosCreacion && $validate_total && $validate_prov && $validate_cfdi_conceptos && 
          $validate_cfdi_impuestos_retenidos && $validate_cfdi_impuestos_trasladados && $validate_compra_contado_credito && $validate_classRecibeArtPago) {
          //&& file_exists($request->file('imagenEvidenciaVerificacion'))

          $idDireccionProv = DB::table("teci_direcciones AS dir")
          ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
          ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
          ->value("dir.id");

          $idDireccionEst = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id");
          if (($tipoLugarEntrega == 'proveedor' && $idDireccionProv == "") || ($tipoLugarEntrega == 'establecimiento' && $idDireccionEst == "")) {
            $dataMensaje = array('status' => 'error','code' => 200,'message' => 'El lugar de recepción seleccionado no encontrado, verifique su información o comuniquese a soporte');
          }

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
            $activoIntangible = $vDet['articulo_homologado_activoIntangible'];
            $prorratea = $vDet['articulo_homologado_prorratea'];

            //$periodicidadPc = $vDet['articulo_homologado_periodicidadPc'];
            //$iteracionPc = $vDet['articulo_homologado_iteracionPc'];
            //$periodoDetIndPc = $vDet['articulo_homologado_periodoDetIndPc'];
            //$fechaFinPc = $vDet['articulo_homologado_fechaFinPc'];
            //$tipoImporteVi = $vDet['articulo_homologado_tipoImporteVi'];
            //$importeMinVi = $vDet['articulo_homologado_importeMinVi']; //importeMinVi
            //$importeMaxVi = $vDet['articulo_homologado_importeMaxVi'];
            $importe = $JwtAuth->rellenaImportesCompras($vDet['Importe']);
            //return response()->json(['message' => 'pais11','codigo' => 200,'status' => 'error']);
            $validateActivos = false;
            $validatePeriodicidad = false;
            $validateDescuentos = false;
            $validateDecimalesMoneda = false;
            $validateForImpuRetenciones = false;
            $validateForImpuTraslados = false;

            $vItem_tokenArticulo = isset($tokenArticulo) && !empty($tokenArticulo);
            $vItem_identificador = isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador);
            $vItem_precioUnitario = isset($precioUnitario) && !empty($precioUnitario) && preg_match($patrónNumCosto, $precioUnitario);
            $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($patrónNum, $cantidad);
            //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
            $vItem_usoArticulo = isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo);
            $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
            //$vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
            $vItem_importe = isset($importe) && !empty($importe) && preg_match($patrónNumCosto, $importe);

            if ($vItem_tokenArticulo && $vItem_identificador && $vItem_precioUnitario && $vItem_cantidad && $vItem_usoArticulo /*&& $vItem_periodicidadPc*/ && $vItem_importe) {
              if (isset($descuentoXUni) && !empty($descuentoXUni)) {
                if ($descuentoXUni != '---') {
                  if (preg_match($patrónNumCosto, $descuentoXUni)) {
                    $strPosdescuentoXUni = strpos($descuentoXUni, '.');
                    if ($strPosdescuentoXUni !== FALSE) {
                      $expdescuentoXUni = explode('.', $descuentoXUni);
                      if ($moneda_decimales == strlen($expdescuentoXUni[1])) {
                        $validateDescuentos = true;
                      } else {
                        $validateDescuentos = false;
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'La cantidad de decimales del descuento no coincide con los decimales que soporta la moneda seleccionada'
                        );
                      }
                    } else {
                      $validateDescuentos = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  } else {
                    $validateDescuentos = false;
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Descuento invalido'
                    );
                  }
                } else {
                  $validateDescuentos = true;
                }
              } else {
                $validateDescuentos = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La cantidad de descuento es invalida o inexistente'
                );
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
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                    );
                  }
                } else {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                  );
                }
              }

              if ($moneda_decimales == 0) {
                $strPosPrecioUnit = strpos($precioUnitario, '.');
                $strPosimporte = strpos($importe, '.');
                if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El precio unitario del producto/servicio no tiene decimales'
                  );
                } else {
                  $validateDecimalesMoneda = true;
                }
              }

              if ($usoArticulo == 'activo_fijo') {
                if (isset($activoFijo) && !empty($activoFijo) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoFijo)) {
                  $validateActivos = true;
                } else {
                  $validateActivos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El activo del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              } else if ($usoArticulo == 'activo_intangible') {
                if (isset($activoIntangible) && !empty($activoIntangible) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoIntangible)) {
                  $validateActivos = true;
                } else {
                  $validateActivos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El descuento del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              } else {
                $validateActivos = true;
              }

              /*if ($periodicidadPc == 'periodo') {
                //return response()->json(['message' => 'error desglose'.$importe,'codigo' => 200,'status' => 'error']);
                if (
                  isset($iteracionPc) && !empty($iteracionPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $iteracionPc) &&
                  isset($periodoDetIndPc) && !empty($periodoDetIndPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc) &&
                  isset($tipoImporteVi) && !empty($tipoImporteVi) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)  &&
                  isset($importeMinVi) && !empty($importeMinVi) && preg_match($patrónNumCosto, $importeMinVi) &&
                  isset($importeMaxVi) && !empty($importeMaxVi) && preg_match($patrónNumCosto, $importeMaxVi)
                ) {
                  if ($periodoDetIndPc == 'determinado') {
                    if (isset($fechaFinPc) && !empty($fechaFinPc) && preg_match($patrónFecha, $fechaFinPc)) {
                      $validatePeriodicidad = true;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La fecha de fin de periodo de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente '
                      );
                      break;
                    }
                  }
                  if ($periodoDetIndPc == 'indeterminado') {
                    $validatePeriodicidad = true;
                  }

                  if ($moneda_decimales != 0) {
                    $strPosimporteMinVi = strpos($importeMinVi, '.');
                    $strPosimporteMaxVi = strpos($importeMaxVi, '.');

                    if ($strPosimporteMinVi !== FALSE && $strPosimporteMaxVi !== FALSE) {
                      $expimporteMinVi = explode('.', $importeMinVi);
                      $expimporteMaxVi = explode('.', $importeMaxVi);

                      if (
                        $moneda_decimales == strlen($expimporteMinVi[1]) &&
                        $moneda_decimales == strlen($expimporteMaxVi[1])
                      ) {
                        $validateDecimalesMoneda = true;
                      } else {
                        $validateDecimalesMoneda = false;
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                        );
                      }
                    } else {
                      $validateDecimalesMoneda = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  }

                  if ($moneda_decimales == 0) {
                    $strPosimporteMinVi = strpos($importeMinVi, '.');
                    $strPosimporteMaxVi = strpos($importeMaxVi, '.');

                    if ($strPosimporteMinVi !== FALSE && $strPosimporteMaxVi !== FALSE) {
                      $validateDecimalesMoneda = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'El precio unitario del producto/servicio no tiene decimales'
                      );
                    } else {
                      $validateDecimalesMoneda = true;
                    }
                  }
                } else {
                  $validatePeriodicidad = false;
                  if (!isset($iteracionPc) || empty($iteracionPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $iteracionPc)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La iteración (repetición) de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente'
                    );
                    break;
                  }
                  if (!isset($periodoDetIndPc) || empty($periodoDetIndPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La selección de periodo de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente'
                    );
                    break;
                  }

                  if (!isset($tipoImporteVi) || empty($tipoImporteVi) || !preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El tipo de variablidilad de importe del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                  if (!isset($importeMinVi) || empty($importeMinVi) || !preg_match($patrónNumCosto, $importeMinVi)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El importe mínimo de variabilidad del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                  if (!isset($importeMaxVi) || empty($importeMaxVi) || !preg_match($patrónNumCosto, $importeMaxVi)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El importe maximo de variabilidad del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                }
              }
              if ($periodicidadPc == 'eventual') {
                $validatePeriodicidad = true;
              }*/

              if (count($retenciones) != 0) {
                $countValidateRetencionesConcept = 0;
                for ($t = 0; $t < count($retenciones); $t++) {
                  $base = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Base"]);
                  $explodeBase = explode('.', $base);
                  $impuesto = $retenciones[$t]["Impuesto"];
                  $tipoFactor = $retenciones[$t]["TipoFactor"];
                  $TasaOCuota = $retenciones[$t]["TasaOCuota"];
                  $importe = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]);
                  $importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]) : "0.00";
                  //return response()->json(['message' => $retenciones[$t]["Importe"],'codigo' => 200,'status' => 'error']);
                  $explodeImporte = explode('.', $importe);

                  if (
                    isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                    && isset($tipoFactor) && !empty($tipoFactor)
                    && isset($TasaOCuota) && !empty($TasaOCuota)
                    && isset($importe) && !empty($importe)
                    && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales)
                  ) {
                    if (isset($base)) {
                      if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                        ++$countValidateRetencionesConcept;
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'Base de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
                        );
                        break;
                      }
                    } else {
                      ++$countValidateRetencionesConcept;
                    }
                    //return response()->json(['message' => $base,'codigo' => 200,'status' => 'error']);
                  } else {
                    if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Impuesto de retención del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)'
                      );
                      break;
                    }
                    if (!isset($tipoFactor) || empty($tipoFactor)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'TipoFactor de retención del producto/servicio '.$concepto.' no existe o esta vacio'
                      );
                      break;
                    }
                    if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'TasaOCuota de retención del producto/servicio '.$concepto.' no existe o esta vacio'
                      );
                      break;
                    }
                    if (
                      !isset($importe) || empty($importe) ||
                      (strlen($explodeImporte[1]) != 6 && strlen($explodeImporte[1]) != $moneda_decimales)
                    ) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Importe de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
                      );
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
                  $TasaOCuota = $traslados[$t]["TasaOCuota"];
                  $importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]) : "0.00";
                  $explodeImporte = explode('.', $importe);

                  if (
                    isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                    && isset($tipoFactor) && !empty($tipoFactor)
                    && isset($TasaOCuota) && !empty($TasaOCuota)
                    && isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales)
                  ) {
                    if (isset($base)) {
                      //return response()->json(['message' => strlen($explodeBase[1]).' == '.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                      if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                        ++$countValidateTrasladosConcept;
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'Base de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
                        );
                        break;
                      }
                    } else {
                      ++$countValidateTrasladosConcept;
                    }
                  } else {
                    if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Impuesto de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)'
                      );
                      break;
                    }
                    if (!isset($tipoFactor) || empty($tipoFactor)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'TipoFactor de traslado del producto/servicio '.$concepto.' no existe o esta vacio'
                      );
                      break;
                    }
                    if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'TasaOCuota de traslado del producto/servicio '.$concepto.' no existe o esta vacio'
                      );
                      break;
                    }
                    if (!isset($importe) || empty($importe) || (strlen($explodeImporte[1]) != 6 && strlen($explodeImporte[1]) != $moneda_decimales)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Importe de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida (' . $moneda_decimales . ')'
                      );
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
              //if (!$vItem_periodicidadPc) {$detalleErrores = 'La periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente';}
              if (!$vItem_importe) {$detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';}
              break;
            }
          }
          
          if ($detalleErrores == "") {
            $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            foreach ($queryEmp as $vEmp) {
              $reembolso_id = $token_reem ? DB::table("terc_reembolso_main")->where("token_reem",$token_reem)->value("id") : NULL;
              $reembolso_soli_id = $token_solicitud_reem ? DB::table("terc_reembolso_solicitud")->where("token_solicitud_reem", $token_solicitud_reem)->value("id") : NULL;

              //$cfdi_comprobante_fecha = "";
              //$cfdi_comprobante_moneda = "";
              //$cfdi_comprobante_forma_de_pago = "";
              //$cfdi_comprobante_metodo_de_pago = "";
              //$cfdi_receptor_uso_del_cfdi = "";
              //$cfdi_comprobante_tipo_de_cambio = "";

              //$cfdi_comprobante_fecha
              //$cfdi_TipoCambio
              //$cfdi_Moneda
              //$cfdi_MoneDecimales
              //$dataCFDI_comprobante_formaPago
              //$dataCFDI_comprobante_MetodoPago
              //$dataCFDI_receptor_UsoCFDI

              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

              $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token, $usuario->user_token]);

              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
              $folio_buy = 'COMP-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');
              //return response()->json(['message' => $folio_buy,'codigo' => 200,'status' => 'error']);
              $nombreRecePago = '';
              
              foreach ($cfdi_impuestos_retenidos as $vComp) {}
              foreach ($cfdi_impuestos_trasladados as $vComp) {}
              
              $tokenCompra = $JwtAuth->encriptarToken(time(), $token_proveedor, $cfdi_Moneda, $tipoLugarEntrega, $tknLugarRecepcion, $cfdi_conceptos);
              $fechaSistema = time();
              $fecha_altaCompra = $cfdi_comprobante_fecha != '' ? $JwtAuth->convierteFechaEpoc($cfdi_comprobante_fecha) : time();
              $anticipo = $anticipoValor != '' ? $anticipoValor : NULL;
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
              $compras->reembolso_vinculado_main = $reembolso_id;
              $compras->reembolso_vinculado_soli = $reembolso_soli_id;
              $compras->compra_a_credito = $compra_contado_credito == 'contado' ? 'cont' : 'cred';
              $compras->recibeFactura = TRUE;
              $compras->aplica_recepcion_facturas = 'Sí';
              //$compras->facturaXml = file_exists($request->file('imagenEvidenciaXMl')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL;  //cifrado 
              //$compras->facturaPdf = file_exists($request->file('imagenEvidenciaPdf')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL;  //cifrado 
              $compras->recepcionPago = $nombreRecePago; //cifrado
              $compras->evidenciaSAT = file_exists($request->file('imagenEvidenciaVerificacion')) ? $JwtAuth->encriptar($fechaSistema."-".$folio_buy.pathinfo($request->file('imagenEvidenciaVerificacion')->getClientOriginalName(), PATHINFO_FILENAME). ".pdf") : NULL; //cifrado
              $compras->anexos = $JwtAuth->encriptar($nombreDocs.".pdf");  //cifrado
              $compras->reporte = $JwtAuth->encriptar($nombreDocs.".pdf"); //cifrado
              $compras->moneda = $cfdi_Moneda;
              $compras->anticipo = $anticipo;
              $compras->forma_pago = $dataCFDI_comprobante_formaPago;
              $compras->metodo_pago = $dataCFDI_comprobante_MetodoPago;
              $compras->uso_cfdi = $dataCFDI_receptor_UsoCFDI;
              $compras->recibeProducto = $classRecibeArtPago ? TRUE : FALSE;// si es TRUE genera orden de pago, si es FALSE no
              $compras->pago_caja_tesoreria = NULL;
              $compras->caja_paga = NULL;

              $compras->recepcion_prov = $tipoLugarEntrega == 'proveedor' ? $idDireccionProv : NULL;
              $compras->recepcion_estab = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
              $compras->comprador = $vEmp->id;
              $compras->usuario_comprador = $vEmp->userr;
              $compras->status_autorizacion = $status_autorizacion;
              $compras->autoriza = $user_jerarquia;
              $compras->status_cancelacion = FALSE;
              $compras->cancela = NULL;
              $compras->status_recepcion = FALSE;
              $compras->recibe = NULL;
              $compras->fecha_delete_compra = '';
              $compras->status_compra = TRUE;
              $compras->observaciones_compra = $validate_compra_observaciones ? $JwtAuth->encriptar($compra_observaciones) : NULL;
              $insertCompra = $compras->save();
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden']);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
              if ($insertCompra) {
                $obtenCompra = $compras->id;
                $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales AS cfdi')//cfdi__estructura
                ->join("cfdi_vinculacion_reembolsos AS reem_vinc", "cfdi.id", "=", "reem_vinc.comprobante_fiscal")
                ->where("reem_vinc.reembolso_vinculado_main",$reembolso_id)
                ->where("reem_vinc.reembolso_vinculado_soli",$reembolso_soli_id)
                ->limit(1)->update(array('reem_vinc.compra_vinculada' => $obtenCompra));
                $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";

                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

                file_exists($request->file('imagenEvidenciaVerificacion')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName()) : NULL; 

                if (!empty($_FILES['compra_anexos'])) {
                  $evidencias = $_FILES["compra_anexos"];
                  $string_name_evid = json_encode($_FILES["compra_anexos"]["name"]);
                  if (count(json_decode($string_name_evid)) != 0) {
                    $evidencia_nombre = json_decode($string_name_evid);
                    for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                      $temporal = $evidencias["tmp_name"][$doc];
                      $doc_name = $evidencias["name"][$doc];
                      Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%BUY-ANEX%'");
                      $token_documento = $JwtAuth->encriptarToken($obtenCompra,$doc_name,$select_folio_doc[0]->folio);
                      $insertDocSoli = DB::table("sos_documentos")->insert(
                        array(
                          "token_documento" => $token_documento,
                          "fecha_carga" => time(),
                          "modulo" => "pagos",
                          "folio_modulo" => "BUY-ANEX" . $select_folio_doc[0]->folio,
                          "tipo_documento" => "an",
                          "nombre_documento" => $JwtAuth->encriptar($doc_name),
                          "compra" => $obtenCompra,
                          "status_documento" => TRUE,
                        )
                      );
                    }
                  }
                }
    
                $validate_insert_ord_pago = false;
                $orden_de_pago_vinculada = "";
                if ($vEmp->jerarquia_main == 'P') {
                  $folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
                    ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                    WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                  $orden_recept = new OrdenRecepcionModelo();
                  $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
                  $orden_recept->folio_recepcion = $folioRecepcionOrden[0]->folio;
                  $orden_recept->fecha_recepcion = time();
                  $orden_recept->proveedor = $idProveedor;
                  $orden_recept->orden_compra = $obtenCompra;
                  $orden_recept->almacen = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
                  $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                  $orden_recept->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                  $orden_recept->observaciones = NULL;
                  $orden_recept->empresa = $vEmp->id; //cifrado
                  $newOrderRecept = $orden_recept->save();
                }

                $contadorDetallecompra = 0;
                for ($i = 0; $i < count($cfdi_conceptos); $i++) {
                  $validUpdtProd = false;
                  $validUpdtServ = false;
                  $NoIdentificacion = $cfdi_conceptos[$i]['NoIdentificacion'];
                  $ObjetoImp = $cfdi_conceptos[$i]['ObjetoImp'];
                  $ClaveProdServ = $cfdi_conceptos[$i]['ClaveProdServ'];
                  $tokenArticulo = $cfdi_conceptos[$i]['articulo_homologado_token'];
                  $identificador = $cfdi_conceptos[$i]['articulo_homologado_identificador'];
                  $concepto = $cfdi_conceptos[$i]['Descripcion'];
                  $precioUnitario = $cfdi_conceptos[$i]['ValorUnitario'];
                  $cantidad = $cfdi_conceptos[$i]['Cantidad'];
                  $ClaveUnidad = $cfdi_conceptos[$i]['ClaveUnidad'];
                  $Unidad = $cfdi_conceptos[$i]['Unidad'];
                  $descuentoXUni = $cfdi_conceptos[$i]['Descuento'];
                  $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
                  $iva = $cfdi_conceptos[$i]['articulo_homologado_iva'];
                  $retenciones = $cfdi_conceptos[$i]['retenciones'];
                  $TotalRetenciones = $cfdi_conceptos[$i]['TotalRetenciones'];
                  //$retenciones_homologada = $cfdi_conceptos[$i]['retencion_token'];
                  $traslados = $cfdi_conceptos[$i]['traslados'];
                  $TotalTraslados = $cfdi_conceptos[$i]['TotalTraslados'];
                  //$traslados_homologada = $cfdi_conceptos[$i]['traslado_token'];
                  $Subtotal = $cfdi_conceptos[$i]['Subtotal'];
                  $usoArticulo = $cfdi_conceptos[$i]['articulo_homologado_uso'];
                  $efectoFiscalArticulo = $cfdi_conceptos[$i]['articulo_homologado_efecto_fiscal'];
                  $alm_serie = $cfdi_conceptos[$i]['articulo_homologado_serie_token'];
                  $alm_lote = $cfdi_conceptos[$i]['articulo_homologado_lote_token'];
                  $alm_pedimento = $cfdi_conceptos[$i]['articulo_homologado_pedimento_token'];
                  $activoFijo = $cfdi_conceptos[$i]['articulo_homologado_activoFijo'];
                  $activoIntangible = $cfdi_conceptos[$i]['articulo_homologado_activoIntangible'];
                  $prorratea = $cfdi_conceptos[$i]['articulo_homologado_prorratea'];

                  $importe = $cfdi_conceptos[$i]['Importe'];
                  $token_unidad_medida = $cfdi_conceptos[$i]['Unidad'];

                  $token_producto = '';
                  $token_servicio = '';
                  $total_retenciones = '';
                  $total_traslado = '';
                  $activos_fijos = '';
                  $activos_intangibles = '';
                  $pedimento_aduanal = NULL;
                  $boolprorratea = FALSE;
                  
                  $catProdServ = DB::table(DB::raw('(SELECT
                    CASE
                      WHEN ? IN (
                        SELECT token_cat_productos 
                        FROM in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp ON catprod.admin_empresa = emp.id
                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa
                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                        WHERE catprod.modulo_mostrador = FALSE 
                          AND catprod.status = TRUE 
                          AND emp.empresa_token = ?
                          AND users.usuario_token = ?
                      ) THEN "Producto"
                      WHEN ? IN (
                        SELECT token_cat_servicios 
                        FROM in_egr_catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp ON catserv.administrador = emp.id
                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa 
                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                        WHERE catserv.proceso = "c" 
                          AND catserv.status = TRUE 
                          AND emp.empresa_token = ? 
                          AND users.usuario_token = ?
                      ) THEN "Servicio"
                    END AS identificador) AS subconsulta'))
                  ->select('identificador')
                  ->setBindings([
                    $tokenArticulo,
                    $usuario->empresa_token,
                    $usuario->user_token,
                    $tokenArticulo,
                    $usuario->empresa_token,
                    $usuario->user_token
                  ])
                  ->value("identificador");

                  $token_producto = $catProdServ == 'Producto' ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$tokenArticulo)->value("id") : NULL;
                  $token_servicio = $catProdServ == 'Producto' ? NULL : DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$tokenArticulo)->value("id");
                  $serie = $catProdServ == 'Producto' && $alm_serie != '' ? DB::table("inventarios_catalogo_series")->where("serie_token",$alm_serie)->value("id") : NULL;
                  $lote = $catProdServ == 'Producto' && $alm_lote != '' ? DB::table("inventarios_catalogo_lotes")->where("token_lote",$alm_lote)->value("id") : NULL;
                  $pedimento_aduanal = $catProdServ == 'Producto' && $alm_pedimento != '' ? DB::table("inventarios_catalogo_pedimento_aduanal")->where("token_pedimento",$alm_pedimento)->value("id") : NULL;
                  $activos_fijos = $catProdServ == 'Producto' && $usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo) ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos",$activoFijo)->value("id") : NULL;
                  $activos_intangibles = $catProdServ == 'Servicio' && $usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible) ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang",$activoIntangible)->value("id") : NULL;

                  $tokenDetalleCompra = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$tokenArticulo.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.
                    $alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$importe);

                  if (count($retenciones) != 0) {
                    $sumaImporteimp = 0;
                    for ($t = 0; $t < count($retenciones); $t++) {
                      //$importe = $retenciones[$t]["TipoFactor"] != "Exento" ? $retenciones[$t]["Importe"] : 0;
                      $importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $retenciones[$t]["Importe"] : 0;
                      $sumaImporteimp = $sumaImporteimp + $importe;
                    }
                    $total_retenciones = $sumaImporteimp;
                  } else {
                    $total_retenciones = '0.00';
                  }

                  if (count($traslados) != 0) {
                    $sumaImporteimp = 0;
                    for ($t = 0; $t < count($traslados); $t++) {
                      //$importe = $traslados[$t]["TipoFactor"] != "Exento" ? $traslados[$t]["Importe"] : 0;
                      $importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $traslados[$t]["Importe"] : 0;
                      $sumaImporteimp = $sumaImporteimp + $importe;
                    }

                    $total_traslado = $sumaImporteimp;
                  } else {
                    $total_traslado = '0.00';
                  }

                  $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
                  
                  $insertDetCompra = DB::table('eegr_compras_detalle')
                  ->insert(array(
                    "token_detcompra" => $tokenDetalleCompra,
                    "numero_compra" => $obtenCompra,
                    "concepto_cfdi" => $JwtAuth->encriptar($concepto),
                    "producto" => $token_producto,
                    "servicio" => $token_servicio,
                    "moneda_detalle_compra" => $cfdi_Moneda,
                    "tipo_de_cambio_detalle_compra" => $cfdi_TipoCambio,
                    "precio_unitario" => $precioUnitario,
                    "cantidad" => $cantidad,
                    "unidad_medida" => $token_unidad_medida,
                    "descuento" => $total_descuento,
                    "retenciones_total" => $total_retenciones,
                    //"retencion_homologada" => $rete_homologada,
                    "traslados_total" => $total_traslado,
                    //"traslado_homologado" => $tras_homologado,
                    "destino" => $usoArticulo,
                    "efecto_fiscal" => $vItem_efectoFiscalArticulo ? $efectoFiscalArticulo : NULL,
                    "activo_fijo" => $activos_fijos,
                    "activo_intangible" => $activos_intangibles,
                    "prorrateo" => $prorratea ? TRUE : FALSE,
                    "empresa" => $vEmp->id,
                  ));

                  $uuid_cfdi_detalle = Str::uuid()->toString();
                  $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
                  ->insert(array(
                    "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                    //"comprobante_fiscal" => DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id"),
                    "numero_compra" => $obtenCompra,
                    "NoIdentificacion" => $NoIdentificacion,
                    "ObjetoImp" => $ObjetoImp,
                    "ClaveProdServ" => $ClaveProdServ,
                    "Cantidad" => $cantidad,
                    "ClaveUnidad" => $ClaveUnidad,
                    "Unidad" => $Unidad,
                    "Descripcion" => $concepto,
                    "ValorUnitario" => $precioUnitario,
                    "Descuento" => $descuentoXUni,
                    "Importe" => $importe,
                    "TotalRetenciones" => $TotalRetenciones,
                    "TotalTraslados" => $TotalTraslados,
                    "Subtotal" => $Subtotal,
                    "empresa" => $vEmp->id
                  ));

                  //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                  $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE detcomp.token_detcompra = ? AND detcomp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                    [$tokenDetalleCompra, $usuario->empresa_token, $usuario->user_token]);


                  if (count($retenciones) != 0) {
                    for ($r = 0; $r < count($retenciones); $r++) {
                      $retencion_traslado = "rete";
                      $Base = $retenciones[$r]["Base"] ? $retenciones[$r]["Base"] : 0.00;
                      $Impuesto = $retenciones[$r]["Impuesto"] ? $retenciones[$r]["Impuesto"] : 000;
                      $TipoFactor = $retenciones[$r]["TipoFactor"] ? $retenciones[$r]["TipoFactor"] : NULL;
                      $TasaOCuota = $retenciones[$r]["TasaOCuota"] ? $retenciones[$r]["TasaOCuota"] : NULL;
                      $importe = $retenciones[$r]["Importe"] ? $retenciones[$r]["Importe"] : 0.00;
                      $impuesto_relacionado = $retenciones[$r]["impuesto_relacionado"];
                      $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                      $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$rete_homologada.$retencion_traslado.$Base.$Impuesto.$TipoFactor.$TasaOCuota.$importe);
                      
                      $insertDetImpuestoCompra = DB::table('eegr_compras_detalle_impuestos')
                      ->insert(array(
                        "token_imp_det_buy" => $tokenDetBuyImp,
                        "detalle_compra" => $selectDetBuy[0]->id,	
                        "retencion_traslado" => $retencion_traslado,
                        "base" => $Base,
                        "impuesto" => $Impuesto,
                        "tipo_factor" => $TipoFactor,
                        "tasa_cuota" => $TasaOCuota,
                        "importe" => $importe,
                        "impuesto_relacionado" => $rete_homologada
                      ));
                    }
                  }
    
                  if (count($traslados) != 0) {
                    for ($t = 0; $t < count($traslados); $t++) {
                      $retencion_traslado = "tras";
                      $Base = $traslados[$t]["Base"] ? $traslados[$t]["Base"] : 0.00;
                      $Impuesto = $traslados[$t]["Impuesto"] ? $traslados[$t]["Impuesto"] : 000;
                      $TipoFactor = $traslados[$t]["TipoFactor"] ? $traslados[$t]["TipoFactor"] : NULL;
                      $TasaOCuota = $traslados[$t]["TasaOCuota"] ? $traslados[$t]["TasaOCuota"] : NULL;
                      $importe = $traslados[$t]["Importe"] ? $traslados[$t]["Importe"] : 0.00;
                      $impuesto_relacionado = $traslados[$t]["impuesto_relacionado"];
                      $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                      $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$tras_homologado.$retencion_traslado.$Base.$Impuesto.$TipoFactor.$TasaOCuota.$importe);
                      
                      $insertDetCompra = DB::table('eegr_compras_detalle_impuestos')
                      ->insert(array(
                        "token_imp_det_buy" => $tokenDetBuyImp,
                        "detalle_compra" => $selectDetBuy[0]->id,	
                        "retencion_traslado" => $retencion_traslado,
                        "base" => $Base,
                        "impuesto" => $Impuesto,
                        "tipo_factor" => $TipoFactor,
                        "tasa_cuota" => $TasaOCuota,
                        "importe" => $importe,
                        "impuesto_relacionado" => $tras_homologado
                      ));
                    }
                  }

                  if ($prorratea) {
                      
                    $folioProrrateo = DB::selectOne("SELECT COALESCE(MAX(fold.folder) + 1, 1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp ON fold.empresa = emp.id
                    JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                    WHERE fold.egr_prorrateos = TRUE AND emp.empresa_token = ? AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                      
                    //return response()->json(['message' => 'error GeneralesCompra'.$folioProrrateo->folio,'codigo' => 200,'status' => 'error']);
                    $tokenCompraProrrateo = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);

                    //return response()->json(['message' => 'error GeneralesCompra'.$importeMinVi,'codigo' => 200,'status' => 'error']);

                    $insertDetCompra = DB::table('eegr_compras_prorrateos')
                    ->insert(array(
                      "token_prorrateo" => $tokenCompraProrrateo,
                      "folio_prorrateo" => $folioProrrateo->folio,	
                      "fecha_sistema_prorrateo" => time(),	
                      "fecha_prorrateo" => time(),	
                      "producto" => $token_producto,	
                      "servicio" => $token_servicio,	
                      "compra" => $obtenCompra,	
                      "detalle_compra" => $selectDetBuy[0]->id,	
                      "empresa"	 => $vEmp->id,	
                      "status_prorrateo" => TRUE,
                    ));

                    if ($folioProrrateo->folio == 1) {
                      $insertSistema = DB::table('sos_last_folders')
                        ->insert(
                          array(
                            "egr_prorrateos" => TRUE,
                            "folder" => 1,
                            "empresa" => $vEmp->id,
                          )
                        );
                    } else {
                      $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                      ->where([
                        'sos_last_folders.egr_prorrateos' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          'sos_last_folders.folder' => $folioProrrateo->folio,
                        )
                      );
                    }

                    $obten_prorrateo_ident =DB::table("eegr_compras_prorrateos")->where("token_prorrateo",$tokenCompraProrrateo)->value("id");
                    $token_detalle_prorrt = $JwtAuth->encriptarToken(time().$obten_prorrateo_ident.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);
                    $insertDetCompra = DB::table('eegr_compras_prorrateos_detalle')
                    ->insert(array(
                      "token_detalle_prorrt" => $token_detalle_prorrt,
                      "prorrateo" => $obten_prorrateo_ident,	
                      "detalle_compra" => $selectDetBuy[0]->id,
                    ));
                  }

                  if ($token_producto != NULL && $token_producto != '') {
                    $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detcomp.token_detcompra = ? 
                      AND detcomp.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$tokenDetalleCompra, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]
                    );

                    $folioKardex = DB::select("SELECT IF (max(dexkar.folio_kardex) IS NOT NULL,(max(dexkar.folio_kardex)+1),1) AS folio 
                      FROM in_egr_productos_kardex AS dexkar JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE dexkar.producto = catprod.id 
                      AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

                    $token_kardex =  $JwtAuth->encriptarToken(time(),$tokenCompra, $tokenDetalleCompra);

                    $insertKardex = DB::table("in_egr_productos_kardex")
                      ->insert(array(
                        "token_kardex" => $token_kardex,
                        "folio_kardex" => $folioKardex[0]->folio,
                        "fecha_kardex" => time(),
                        "status_kardex" => 2,
                        "producto" => $token_producto,
                        "concepto" => "por recibir",
                        "factura_compra" => $obtenCompra,
                        "detalle_compra" => $selectDetBuy[0]->id,
                        "recibir_cantidad" => $cantidad,
                        "valor_unitario" => $precioUnitario,
                      ));
                      //return response()->json(['status' => 'error','code' => 200,'message' => "total_descuento ".$total_descuento]);
                      $upDateProducto = DB::table('in_egr_catalogo_productos')
                      ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                      ->where([
                        'in_egr_catalogo_productos.status' => TRUE,
                        'in_egr_catalogo_productos.id' => $token_producto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(array("in_egr_catalogo_productos.ultima_compra" => time(),));
                      $validUpdtProd = $upDateProducto ? true : false;
                  } else {
                    $validUpdtProd = true;
                  }

                  if ($token_servicio != NULL && $token_servicio != '') {
                    $upDateServicio = DB::table('in_egr_catalogo_servicios')
                    ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'in_egr_catalogo_servicios.status' => TRUE,
                      'in_egr_catalogo_servicios.id' => $token_servicio,
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));
                    $validUpdtServ = $upDateServicio ? true : false;
                  } else {
                    $validUpdtServ = true;
                  }

                  if ($insertDetCompra && $validUpdtProd == true && $validUpdtServ == true) {
                    ++$contadorDetallecompra;
                  }
                }

                if ($insertCompra && $contadorDetallecompra == count($cfdi_conceptos)) {
                  $JwtAuth->insertBitacoraActividad(
                    'egresos',
                    'compras',
                    'compras',
                    $folio_buy,
                    'registro en el alta de compras',
                    $usuario->empresa_token,
                    $usuario->user_token
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
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          'sos_last_folders.folder' => $folio_nuevo,
                          'sos_last_folders.post_folder' => $post_folio,
                        )
                      );
                  }

                  $dataMensaje = array(
                    'message' => 'Compra registrada y autorizada con el folio '.$folio_buy.($validate_insert_ord_pago ? ', revise ordenes de pago' : ''),'code' => 200,'status' => 'success','token_compras' => $tokenCompra
                  );
                }

              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Esta compra no fue terminada debido a errores internos'
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
          if (!$validate_total) {$mensaje_error_main = 'Error en total de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_prov) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_impuestos_retenidos) {$mensaje_error_main = 'Error en impuestos retenidos de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_cfdi_impuestos_trasladados) {$mensaje_error_main = 'Error en impuestos trasladados de su CFDI, verifique su información o comuniquese a soporte';}
          if (!$validate_compra_contado_credito) {$mensaje_error_main = 'Error en seleccion de compra a crédito o contado, verifique su información o comuniquese a soporte';}
          if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información o comuniquese a soporte';}
          if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
