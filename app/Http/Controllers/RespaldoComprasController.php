<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\PermisosModelo;
use App\Models\ComprasModelo;
use App\Models\OrdenPagoModelo;
use APP\Models\ProveedoresModelo;
use App\Models\ProductosModelo;
use App\Models\ServiciosModelo;
use App\Models\ActivosFijosModelo;
use App\Models\ActivosIntangiblesModelo;
use App\Models\RecepcionCompraModelo;
use App\Models\RechazoCompraModelo;
use App\Models\DevengacionCompraModelo;

class EGRE_ComprasController extends Controller{
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
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayArticulos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
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

        $queryCatProd = "SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,catprod.producto AS concepto,catprod.marca,catprod.clasificacion,gen.folio_genero AS genero,
          catprod.folio_sistema,catprod.post_folio,emp.root_tkn,catprod.num_serie,catprod.num_lote,catprod.importado,catprod.costo_aplicable AS precioUnitario FROM in_egr_catalogo_productos AS catprod 
          JOIN sos_ps_genero as gen JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprod.genero = gen.id AND catprod.modulo_mostrador = FALSE AND catprod.status = TRUE
          AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";

        $queryCatServ = "SELECT catserv.token_cat_servicios AS token_articulo,catserv.fecha_registro_serv AS fecha_alta,'Servicio' AS identificador,catserv.servicio AS concepto,'---' AS marca,catserv.clasificacion,gen.folio_genero AS genero,
          catserv.folio_sistema,catserv.post_folio,emp.root_tkn,FALSE AS num_serie,FALSE AS num_lote,FALSE AS importado,catserv.precioBase AS precioUnitario FROM in_egr_catalogo_servicios AS catserv 
          JOIN sos_ps_genero as gen JOIN main_empresas AS emp  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catserv.genero = gen.id AND catserv.proceso = 'c' AND catserv.status = TRUE
          AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";

        $combinado = "{$queryCatProd} UNION {$queryCatServ}";
        $resultQuery = DB::select($combinado, [$usuario->empresa_token, $usuario->user_token, $usuario->empresa_token, $usuario->user_token]);
        $countList = 0;
        foreach ($resultQuery as $value) {
          $conceptoArticulo = $JwtAuth->desencriptar($value->concepto) . ($value->marca != '' && $value->marca != '---' ? ' ' . $JwtAuth->desencriptar($value->marca) : '');
          $imgArticulo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";

          $prov_relacionado_rfc = "";
          $prov_relacionado_tiene_clave = "";
          $prov_relacionado_clave = "";

          if ($value->identificador == "Producto") {
            $prvProdQuery = DB::table("in_egr_catalogo_productos_claves AS clav")
            ->join("in_egr_catalogo_productos AS catprod","clav.productoid","=","catprod.id",)
            ->join("eegr_catalogo_proveedores AS catprov","clav.proveedor","=","catprov.id")
            ->join("sos_personas AS prov","catprov.proveedor","=","prov.id")
            ->where("catprod.token_cat_productos",$value->token_articulo)
            ->get();
            foreach ($prvProdQuery as $vKlav) {
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

  public function listaArticulosComprasByProv(Request $request){
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

        $queryCatProd = "SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,catprod.producto AS concepto,catprod.marca,catprod.clasificacion,gen.folio_genero AS genero,
          catprod.folio_sistema,catprod.post_folio,emp.root_tkn,prclav.identificador AS claveArticulo,catprod.num_serie,catprod.num_lote,catprod.importado,catprod.costo_aplicable AS precioUnitario FROM in_egr_catalogo_productos AS catprod 
          JOIN sos_ps_genero as gen JOIN eegr_catalogo_proveedores AS catprov JOIN in_egr_catalogo_productos_claves AS prclav JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
          WHERE catprod.genero = gen.id AND catprod.id = prclav.productoid AND prclav.proveedor = catprov.id AND catprov.token_cat_proveedores = ? AND catprod.modulo_mostrador = FALSE AND catprod.status = TRUE
          AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";

        $queryCatServ = "SELECT catserv.token_cat_servicios AS token_articulo,catserv.fecha_registro_serv AS fecha_alta,'Servicio' AS identificador,catserv.servicio AS concepto,'---' AS marca,catserv.clasificacion,gen.folio_genero AS genero,
          catserv.folio_sistema,catserv.post_folio,emp.root_tkn,srclave.asigned_clave AS claveArticulo,FALSE AS num_serie,FALSE AS num_lote,FALSE AS importado,catserv.precioBase AS precioUnitario FROM in_egr_catalogo_servicios AS catserv 
          JOIN sos_ps_genero as gen JOIN eegr_catalogo_proveedores AS catprov JOIN in_egr_catalogo_servicios_claves AS srclave JOIN main_empresas AS emp  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
          WHERE catserv.genero = gen.id AND catserv.id = srclave.servicio_id AND srclave.proveedor = catprov.id AND catprov.token_cat_proveedores = ? AND catserv.proceso = 'c' AND catserv.status = TRUE
          AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";

        $combinado = "{$queryCatProd} UNION ALL {$queryCatServ}";
        $resultQuery = DB::select($combinado, [$proveedor_selected, $usuario->empresa_token, $usuario->user_token, $proveedor_selected, $usuario->empresa_token, $usuario->user_token]);
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

  public function registraCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
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
        'receptFactura' => 'required|boolean',
        'anticipoValor' => 'string',
        'classRecibeArtPago' => 'required|boolean',
        'tipoLugarEntrega' => 'required|string',
        'tknLugarRecepcion' => 'string',
        //'pagoTesoreriaCaja' => 'string',
        //'datosCajaToken' => 'string',
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
        $receptFactura = $parametrosArray['receptFactura'] ? $parametrosArray['tipoDeCambio'] : '1.00';
        $anticipoValor = $parametrosArray['anticipoValor'];
        $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
        $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
        $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);

        $validate_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $validate_fecha_vencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
        $validate_cfdi_comprobante = isset($cfdi_comprobante) && !empty($cfdi_comprobante) && is_array($cfdi_comprobante);
        $validate_total = isset($total) && !empty($total);
        $validate_cfdi_emisor = isset($cfdi_emisor) && !empty($cfdi_emisor) && is_array($cfdi_emisor);
        $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
        $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && $idProveedor != "";
        $validate_cfdi_receptor = isset($cfdi_receptor) && !empty($cfdi_receptor) && is_array($cfdi_receptor);
        $validate_cfdi_conceptos = isset($cfdi_conceptos) && !empty($cfdi_conceptos) && is_array($cfdi_conceptos);
        $validate_cfdi_impuestos_retenidos = isset($cfdi_impuestos_retenidos) && is_array($cfdi_impuestos_retenidos);
        $validate_cfdi_impuestos_trasladados = isset($cfdi_impuestos_trasladados) && is_array($cfdi_impuestos_trasladados);
        $validate_cfdi_complemento = isset($cfdi_complemento) && !empty($cfdi_complemento) && is_array($cfdi_complemento);
        $validate_compra_contado_credito = isset($compra_contado_credito) && !empty($compra_contado_credito) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_contado_credito);
        $validate_anticipoValor = isset($anticipoValor) && !empty($anticipoValor);
        $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);
        $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega) && isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);

        if ($validate_fecha_contabilizacion && $validate_fecha_vencimiento && $permisosCreacion && $validate_cfdi_comprobante && $validate_total && $validate_cfdi_emisor && $validate_prov && $validate_cfdi_receptor && 
          $validate_cfdi_conceptos && $validate_cfdi_impuestos_retenidos && $validate_cfdi_impuestos_trasladados && $validate_cfdi_complemento && $validate_compra_contado_credito &&
          $validate_classRecibeArtPago) {// && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))

          foreach ($cfdi_comprobante as $vComp) {
            if ($vComp["title"] == "Moneda") {
              $moneda_decimales = $JwtAuth->getMonedaAPI($vComp["content"]);
            }
          }

          $validaReceptFact = true;
          $validaReceptArt = $classRecibeArtPago || (!$classRecibeArtPago && isset($pagoTesoreriaCaja) && !empty($pagoTesoreriaCaja))? true : false;
          if (!$classRecibeArtPago && !isset($pagoTesoreriaCaja) && empty($pagoTesoreriaCaja)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'información de pago con caja o tesoreria no valida, verifique su información o comuniquese a sopporte'
            );
          }

          $idDireccionProv = DB::table("teci_direcciones AS dir")
          ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
          ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
          ->value("dir.id");

          $idDireccionEst = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id");
          if (($tipoLugarEntrega == 'proveedor' && $idDireccionProv == "") || ($tipoLugarEntrega == 'establecimiento' && $idDireccionEst == "")) {
            $dataMensaje = array('status' => 'error','code' => 200,'message' => 'El lugar de recepción seleccionado no encontrado, verifique su información o comuniquese a sopporte');
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

              if (count($retenciones) != 0) {
                $countValidateRetencionesConcept = 0;
                for ($t = 0; $t < count($retenciones); $t++) {
                  $base = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Base"]);
                  $explodeBase = explode('.', $base);
                  $impuesto = $retenciones[$t]["Impuesto"];
                  $tipoFactor = $retenciones[$t]["TipoFactor"];
                  $TasaOCuota = $retenciones[$t]["TasaOCuota"];
                  $importe = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]);
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
                  $importe = $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]);
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

              $tokenCompra = $JwtAuth->encriptarToken(time(), $token_proveedor, $cfdi_comprobante_moneda, $tipoLugarEntrega, $tknLugarRecepcion, $cfdi_conceptos);
              $fechaSistema = time();
              $fecha_altaCompra = $cfdi_comprobante_fecha != '' ? $JwtAuth->convierteFechaEpoc($cfdi_comprobante_fecha) : time();
              $anticipo = $anticipoValor != '' ? DB::table("eegr_catalogo_proveedores_anticipo")->where("token_anticipo",$anticipoValor)->value("id") : NULL;
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
              $compras->facturaXml = file_exists($request->file('imagenEvidenciaXMl')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL;  //cifrado 
              $compras->facturaPdf = file_exists($request->file('imagenEvidenciaPdf')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL;  //cifrado 
              $compras->recepcionPago = $nombreRecePago; //cifrado
              $compras->evidenciaSAT = file_exists($request->file('imagenEvidenciaVerificacion')) ? $JwtAuth->encriptar($fechaSistema."-".$folio_buy.pathinfo($request->file('imagenEvidenciaVerificacion')->getClientOriginalName(), PATHINFO_FILENAME). ".pdf") : NULL; //cifrado
              $compras->anexos = $JwtAuth->encriptar($nombreDocs.".pdf");  //cifrado
              $compras->reporte = $JwtAuth->encriptar($nombreDocs.".pdf"); //cifrado
              $compras->moneda = $cfdi_comprobante_moneda;
              $compras->anticipo = $anticipo;
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
              $insertCompra = $compras->save();
              //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden']);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
              if ($insertCompra) {
                $obtenCompra = $compras->id;

                $insertCFDIEstructura = DB::table('cfdi_estructura')
                ->insert(array(
                  "compra_vinculada" => $obtenCompra,
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

                $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";

                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

                file_exists($request->file('imagenEvidenciaXMl')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaXMl'),$request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL;
                if (file_exists($request->file('imagenEvidenciaPdf'))) {
                  file_exists($request->file('imagenEvidenciaPdf')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL; 
                }
                file_exists($request->file('imagenEvidenciaVerificacion')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName()) : NULL; 

                $validate_insert_ord_pago = false;
                if ($vEmp->jerarquia_main == 'P') {
                  $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

                  $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra);

                  $orderpay = new OrdenPagoModelo();
                  $orderpay->token_ordenPago = $tknOrder;
                  $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                  $orderpay->fecha_sistema_ordenp = time();
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor;
                  $orderpay->orden_bloqueada = $classRecibeArtPago ? FALSE : TRUE;
                  $orderpay->autorizacion_pay = FALSE;
                  $orderpay->fecha_autorizacion_pay = NULL;
                  $orderpay->tentativa_pago = NULL;
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
                }

                $contadorDetallecompra = 0;
                for ($i = 0; $i < count($cfdi_conceptos); $i++) {
                  $validUpdtProd = false;
                  $validUpdtServ = false;
                  $tokenArticulo = $cfdi_conceptos[$i]['articulo_homologado_token'];
                  $identificador = $cfdi_conceptos[$i]['articulo_homologado_identificador'];
                  $concepto = $cfdi_conceptos[$i]['Descripcion'];
                  $precioUnitario = $cfdi_conceptos[$i]['ValorUnitario'];
                  $cantidad = $cfdi_conceptos[$i]['Cantidad'];
                  $descuentoXUni = $cfdi_conceptos[$i]['Descuento'];
                  $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
                  $iva = $cfdi_conceptos[$i]['articulo_homologado_iva'];
                  $retenciones = $cfdi_conceptos[$i]['retenciones'];
                  //$retenciones_homologada = $cfdi_conceptos[$i]['retencion_token'];
                  $traslados = $cfdi_conceptos[$i]['traslados'];
                  //$traslados_homologada = $cfdi_conceptos[$i]['traslado_token'];
                  $usoArticulo = $cfdi_conceptos[$i]['articulo_homologado_uso'];
                  $alm_serie = $cfdi_conceptos[$i]['articulo_homologado_serie_token'];
                  $alm_lote = $cfdi_conceptos[$i]['articulo_homologado_lote_token'];
                  $alm_pedimento = $cfdi_conceptos[$i]['articulo_homologado_pedimento_token'];
                  $activoFijo = $cfdi_conceptos[$i]['articulo_homologado_activoFijo'];
                  $activoIntangible = $cfdi_conceptos[$i]['articulo_homologado_activoIntangible'];
                  $prorratea = $cfdi_conceptos[$i]['articulo_homologado_prorratea'];
                  $periodicidadPc = $cfdi_conceptos[$i]['articulo_homologado_periodicidadPc'];
                  $iteracionPc = $cfdi_conceptos[$i]['articulo_homologado_iteracionPc'];
                  $periodoDetIndPc = $cfdi_conceptos[$i]['articulo_homologado_periodoDetIndPc'];
                  $fechaFinPc = $cfdi_conceptos[$i]['articulo_homologado_fechaFinPc'];
                  $tipoImporteVi = $cfdi_conceptos[$i]['articulo_homologado_tipoImporteVi'];
                  $importeMinVi = $cfdi_conceptos[$i]['articulo_homologado_importeMinVi'];
                  $importeMaxVi = $cfdi_conceptos[$i]['articulo_homologado_importeMaxVi'];
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
                  $serie = $catProdServ == 'Producto' && $alm_serie != '' ? DB::table("inventarios_catalogo_series")->where("serie_token",$alm_serie)->value("id") : NULL;
                  $lote = $catProdServ == 'Producto' && $alm_lote != '' ? DB::table("inventarios_catalogo_lotes")->where("token_lote",$alm_lote)->value("id") : NULL;
                  $pedimento_aduanal = $catProdServ == 'Producto' && $alm_pedimento != '' ? DB::table("inventarios_catalogo_pedimento_aduanal")->where("token_pedimento",$alm_pedimento)->value("id") : NULL;
                  $activos_fijos = $catProdServ == 'Producto' && $usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo) ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos",$activoFijo)->value("id") : NULL;
                  $activos_intangibles = $catProdServ == 'Servicio' && $usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible) ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang",$activoIntangible)->value("id") : NULL;

                  $tokenDetalleCompra = $JwtAuth->encriptarToken(time() . $token_producto . $token_servicio . $tokenArticulo .
                    $identificador . $concepto . $precioUnitario . $cantidad . $total_descuento . $iva . $usoArticulo . $alm_serie .
                    $alm_lote . $alm_pedimento . $activoFijo . $activoIntangible . $periodicidadPc . $iteracionPc .
                    $periodoDetIndPc . $fechaFinPc . $tipoImporteVi . $importeMinVi . $importeMaxVi . $importe);

                  if (count($retenciones) != 0) {
                    $sumaImporteimp = 0;
                    for ($t = 0; $t < count($retenciones); $t++) {
                      $importe = $retenciones[$t]["Importe"];
                      $sumaImporteimp = $sumaImporteimp + $importe;
                    }
                    $total_retenciones = $sumaImporteimp;
                  } else {
                    $total_retenciones = '0.00';
                  }

                  if (count($traslados) != 0) {
                    $sumaImporteimp = 0;
                    for ($t = 0; $t < count($traslados); $t++) {
                      $importe = $traslados[$t]["Importe"];
                      $sumaImporteimp = $sumaImporteimp + $importe;
                    }

                    $total_traslado = $sumaImporteimp;
                  } else {
                    $total_traslado = '0.00';
                  }

                  $boolperiodicidadPc = $periodicidadPc == 'periodo' ? TRUE : FALSE;
                  $txtiteracionPc = $periodicidadPc == 'periodo' ? $iteracionPc : NULL;
                  $boolperiodoDetIndPc = $periodicidadPc == 'periodo' && $periodoDetIndPc == 'determinado' ? TRUE : FALSE;
                  $txtfechaFinPc = $periodicidadPc == 'periodo' && $periodoDetIndPc == 'determinado' ? $JwtAuth->convierteFechaEpoc($fechaFinPc) : NULL;

                  //return response()->json(['status' => 'error','code' => 200,'message' => $total_traslado]);
                  //return response()->json(['status' => 'error','code' => 200,'message' => $total_descuento]);
                  
                  $insertDetCompra = DB::table('eegr_compras_detalle')
                    ->insert(array(
                      "token_detcompra" => $tokenDetalleCompra,
                      "numero_compra" => $obtenCompra,
                      "concepto_cfdi" => $JwtAuth->encriptar($concepto),
                      "producto" => $token_producto,
                      "servicio" => $token_servicio,
                      "moneda_detalle_compra" => $cfdi_comprobante_moneda,
                      "tipo_de_cambio_detalle_compra" => $cfdi_comprobante_tipo_de_cambio,
                      "precio_unitario" => $precioUnitario,
                      "cantidad" => $cantidad,
                      "unidad_medida" => $token_unidad_medida,
                      "descuento" => $total_descuento,
                      "retenciones_total" => $total_retenciones,
                      //"retencion_homologada" => $rete_homologada,
                      "traslados_total" => $total_traslado,
                      //"traslado_homologado" => $tras_homologado,
                      "destino" => $usoArticulo,
                      "activo_fijo" => $activos_fijos,
                      "activo_intangible" => $activos_intangibles,
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
                          $Base = $retenciones[$r]["Base"] ? $retenciones[$r]["Base"] : 0.00;
                          $Impuesto = $retenciones[$r]["Impuesto"] ? $retenciones[$r]["Impuesto"] : 000;
                          $TipoFactor = $retenciones[$r]["TipoFactor"] ? $retenciones[$r]["TipoFactor"] : NULL;
                          $TasaOCuota = $retenciones[$r]["TasaOCuota"] ? $retenciones[$r]["TasaOCuota"] : NULL;
                          $importe = $retenciones[$r]["Importe"] ? $retenciones[$r]["Importe"] : 0.00;
                          $impuesto_relacionado = $retenciones[$r]["impuesto_relacionado"];

                          $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                          
                          $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$rete_homologada.$retencion_traslado.$Base.$Impuesto.$TipoFactor.$TasaOCuota.$importe);
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
          if (!$validate_fecha_contabilizacion) {$mensaje_error_main = 'Error en fecha de contabilización, verifique su información o comuniquese a sopporte';}
          if (!$validate_fecha_vencimiento) {$mensaje_error_main = 'Error en fecha de vencimiento, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_comprobante) {$mensaje_error_main = 'Error en nodo comprobante de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_total) {$mensaje_error_main = 'Error en total de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_emisor) {$mensaje_error_main = 'Error en nodo emisor de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_prov) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_receptor) {$mensaje_error_main = 'Error en nodo receptor de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_impuestos_retenidos) {$mensaje_error_main = 'Error en impuestos retenidos de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_impuestos_trasladados) {$mensaje_error_main = 'Error en impuestos trasladados de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_cfdi_complemento) {$mensaje_error_main = 'Error en nodo complemento de su CFDI, verifique su información o comuniquese a sopporte';}
          if (!$validate_compra_contado_credito) {$mensaje_error_main = 'Error en seleccion de compra a crédito o contado, verifique su información o comuniquese a sopporte';}
          if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información o comuniquese a sopporte';}
          //if (!$validate_tipoLugarEntrega) {$mensaje_error_main = 'No se encontro respuesta a seleccion de lugar de entrega sobre esta compra, verifique su información o comuniquese a sopporte';}
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
        $anticipoValor = $parametrosArray['anticipoValor'];
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
          $validaReceptFact = true;
          $validaReceptArt = $classRecibeArtPago || (!$classRecibeArtPago && isset($pagoTesoreriaCaja) && !empty($pagoTesoreriaCaja))? true : false;
          if (!$classRecibeArtPago && !isset($pagoTesoreriaCaja) && empty($pagoTesoreriaCaja)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'información de pago con caja o tesoreria no valida, verifique su información'
            );
          }

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
              $anticipo = $anticipoValor != '' ? DB::table("eegr_catalogo_proveedores_anticipo")->where("token_anticipo",$anticipoValor)->value("id") : NULL;
              $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
              $status_autorizacion = $vEmp->jerarquia_main == 'P' ? TRUE : FALSE;
              $nombreDocs = $fechaSistema."-".$folio_buy;
              $compras = new ComprasModelo();
              $compras->token_compras = $tokenCompra;
              $compras->folio_compra = $folio_nuevo;
              $compras->post_folio = $post_folio;
              $compras->fecha_sistemaCompras = time();
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
                  $orderpay->fecha_sistema_ordenp = time();
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor;
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

  public function registrarCompraByINSTRUCCION(Request $request){
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
        $anticipoValor = $parametrosArray['anticipoValor'];
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

          $validaReceptFact = true;
          $validaReceptArt = $classRecibeArtPago || (!$classRecibeArtPago && isset($pagoTesoreriaCaja) && !empty($pagoTesoreriaCaja))? true : false;
          if (!$classRecibeArtPago && !isset($pagoTesoreriaCaja) && empty($pagoTesoreriaCaja)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'información de pago con caja o tesoreria no valida, verifique su información'
            );
          }

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
              $anticipo = $anticipoValor != '' ? DB::table("eegr_catalogo_proveedores_anticipo")->where("token_anticipo",$anticipoValor)->value("id") : NULL;
              $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
              $status_autorizacion = $vEmp->jerarquia_main == 'P' ? TRUE : FALSE;
              $nombreDocs = $fechaSistema."-".$folio_buy;
              $compras = new ComprasModelo();
              $compras->token_compras = $tokenCompra;
              $compras->folio_compra = $folio_nuevo;
              $compras->post_folio = $post_folio;
              $compras->fecha_sistemaCompras = time();
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
                  $orderpay->fecha_sistema_ordenp = time();
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor;
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

  public function registroBackupCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $imagenEvidenciaXml = $request->file('imagenEvidenciaXMl');
    $imagenEvidenciaPdf = $request->file('imagenEvidenciaPdf');
    $imagenEvidenciaVerificacion = $request->file('imagenEvidenciaVerificacion');
    $imagenEvidenciaReceptPago = $request->file('imagenEvidenciaReceptPago');
    //$facturaXml = $request->file('json');
    //$facturaXml = $request->file('json');
    $jsonUser = $request->input('dataCompra');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'validXmlFecha' => 'string',
        'token_proveedor' => 'required|string',
        'token_formaPago' => 'required|string',
        'token_metodoPago' => 'required|string',
        'token_moneda' => 'required|string',
        'tipoDeCambio' => 'required|string',
        'token_usoCdfi' => 'required|string',
        'receptFactura' => 'required|boolean',
        'anticipoValor' => 'string',
        'classRecibeArtPago' => 'required|boolean',
        'totalPagoCompra' => 'required|string',
        'pagoTesoreriaCaja' => 'string',
        'datosCajaToken' => 'string',
        'array_desgloceCompra' => 'required',
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
        $menu_accion = 'bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==';
        //return response()->json(['message' => $usuario->user_token,'codigo' => 200,'status' => 'error']);
        $permisosCreacion = $JwtAuth->permisosCreacion($menu_accion, $usuario->empresa_token, $usuario->user_token);
        //return response()->json(['message' => 'pais2','codigo' => 200,'status' => 'error']);
        if ($permisosCreacion == true) {
          $patrón = '/[aA-zZ_]/';
          $patrónNum = '/^[0-9$,.-]*$/';
          $patrónNumCosto = '/^[0-9$,.-]*$/';
          $patrónRfc = '/[aA0-zZ9]/';
          $patrónFecha = '/^[0-9-]*$/';

          $validaGeneralesCompra = false;
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
          //return response()->json(['message' => 'pais3','codigo' => 200,'status' => 'error']);
          $validXmlFecha = $parametrosArray['validXmlFecha'];
          $token_proveedor = $parametrosArray['token_proveedor'];
          $receptFactura = $parametrosArray['receptFactura'];
          $token_formaPago = $parametrosArray['token_formaPago'];
          $token_metodoPago = $parametrosArray['token_metodoPago'];
          $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];

          $pagoTesoreriaCaja = $parametrosArray['pagoTesoreriaCaja'];
          $totalPagoCompra = $parametrosArray['totalPagoCompra'];
          $datosCajaToken = $parametrosArray['datosCajaToken'];

          $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
          $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];
          $moneda_codigo = $parametrosArray['token_moneda'];
          $moneda_decimales = 0;

          $moneda_decimales = $JwtAuth->getMonedaAPI($moneda_codigo);

          $tipoCambio = $parametrosArray['tipoDeCambio'];
          $token_usoCdfi = $parametrosArray['token_usoCdfi'];
          $array_desgloceCompra = $parametrosArray['array_desgloceCompra'];
          //return response()->json(['message' => 'pais4','codigo' => 200,'status' => 'error']);
          $idProveedor = DB::select("SELECT catprov.id FROM eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ?
            AND catprov.administrador = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ? AND empuser.usuario = users.id 
            AND users.usuario_token = ?", [$token_proveedor, $usuario->empresa_token, $usuario->user_token]);
          //return response()->json(['message' => 'pais5'.$moneda_codigo,'codigo' => 200,'status' => 'error']);
          //$idMoneda = DB::select("SELECT id,decimales FROM catalogo_monedas WHERE token_monedas = ?",[$moneda_codigo]);

          //$idUsoCFDI = DB::select("SELECT id FROM uso_cfdi WHERE token_uso_cfdi = ?",[$token_usoCdfi]);

          //if (isset($classRecibeArtPago) && is_bool($classRecibeArtPago) === true) {
          //    return response()->json(['message' => 'error Compra1','codigo' => 200,'status' => 'error']);
          //} else {
          //    return response()->json(['message' => 'error Compra0','codigo' => 200,'status' => 'error']);
          //}
          //return response()->json(['message' => 'pais6','codigo' => 200,'status' => 'error']);

          $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && count($idProveedor) == 1;
          $validate_receptFactura = isset($receptFactura) && !empty($receptFactura);
          $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);
          $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega);
          $validate_moneda_codigo = isset($moneda_codigo) && !empty($moneda_codigo);
          $validate_token_usoCdfi = isset($token_usoCdfi) && !empty($token_usoCdfi);
          $validate_array_desgloceCompra = isset($array_desgloceCompra) && !empty($array_desgloceCompra);

          if ($validate_prov && $validate_receptFactura  && $validate_classRecibeArtPago  && $validate_tipoLugarEntrega  && $validate_moneda_codigo  && $validate_token_usoCdfi  && $validate_array_desgloceCompra) {
            $validaReceptFact = false;
            $validaReceptArt = false;
            $validaLugarRecepcion = false;
            $contadorValidacionConceptos = 0;
            //return response()->json(['message' => 'pais7','codigo' => 200,'status' => 'error']);
            if ($classRecibeArtPago == true) {
              $validaReceptArt = true;
            } else {
              if (isset($pagoTesoreriaCaja) && !empty($pagoTesoreriaCaja)) {
                //return response()->json(['message' => 'pais7'.$pagoTesoreriaCaja,'codigo' => 200,'status' => 'error']);
                $validaReceptArt = true;
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'información de pago con caja o tesoreria no valida, verifique su información'
                );
              }
            }
            //return response()->json(['message' => 'pais8','codigo' => 200,'status' => 'error']);
            if (isset($tknLugarRecepcion) && !empty($tknLugarRecepcion)) {
              if ($tipoLugarEntrega == 'proveedor') {
                $idDireccionProv = DB::select("SELECT dir.id FROM teci_direcciones AS dir JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                  WHERE dir.token_direccion = ? AND dir.proveedor = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ?
                  AND empuser.usuario = users.id AND users.usuario_token = ?", [$tknLugarRecepcion, $token_proveedor, $usuario->empresa_token, $usuario->user_token]);
                if (count($idDireccionProv) == 1) {
                  $validaLugarRecepcion = true;
                } else {
                  $validaLugarRecepcion = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El lugar de recepción seleccionado fue encontrado ' . count($idDireccionProv) . ' veces, verifique su información'
                  );
                }
              }

              if ($tipoLugarEntrega == 'establecimiento') {
                $idDireccionEst = DB::select("SELECT estab.id FROM in_egr_establecimientos_catalogo AS estab JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                  WHERE estab.token_establecimiento = ? AND estab.empresa = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ? AND empuser.usuario = users.id AND users.usuario_token = ?",
                  [$tknLugarRecepcion, $usuario->empresa_token, $usuario->user_token]);

                if (count($idDireccionEst) == 1) {
                  $validaLugarRecepcion = true;
                } else {
                  $validaLugarRecepcion = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El lugar de recepción seleccionado fue encontrado ' . count($idDireccionEst) . ' veces, verifique su información'
                  );
                }
              }
            } else {
              $validaLugarRecepcion = false;
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Lugar de recepción no seleccionado'
              );
            }
            //return response()->json(['message' => 'pais9','codigo' => 200,'status' => 'error']);
            if ($receptFactura == true) {
              //$idFormaPago = DB::select("SELECT id FROM forma_pago WHERE token_formapago = ?",[$token_formaPago]);
              //$idMetodoPago = DB::select("SELECT id FROM metodo_pago WHERE token_metodoPago = ?",[$token_metodoPago]);
              $valida_f_pago = isset($token_formaPago) && !empty($token_formaPago); // && count($idFormaPago) == 1 &&
              $valida_m_pago = isset($token_metodoPago) && !empty($token_metodoPago); // && count($idMetodoPago) == 1 && 
              if ($valida_f_pago && $valida_m_pago && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaPdf')) && file_exists($request->file('imagenEvidenciaVerificacion'))) {
                $validaReceptFact = true;
              } else {
                $validaReceptFact = false;
                if (!$valida_f_pago) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La forma de pago seleccionada fue encontrada ' . count($idDireccionProv) . ' veces, verifique su información'
                  );
                }
                if (!$valida_m_pago) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El método de pago seleccionado fue encontrado ' . count($idDireccionProv) . ' veces, verifique su información'
                  );
                }
                if (!file_exists($request->file('imagenEvidenciaXMl'))) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Debe cargar la factura en formato xml correspondiente a esta compra'
                  );
                }
                if (!file_exists($request->file('imagenEvidenciaPdf'))) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Debe cargar la factura en formato pdf correspondiente a esta compra'
                  );
                }
                if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra'
                  );
                }
              }
            }
            //return response()->json(['message' => 'pais10','codigo' => 200,'status' => 'error']);

            for ($i = 0; $i < count($array_desgloceCompra); $i++) {
              $tokenArticulo = $array_desgloceCompra[$i]['articulo_homologado_token'];
              $identificador = $array_desgloceCompra[$i]['articulo_homologado_identificador'];
              $concepto = $array_desgloceCompra[$i]['Descripcion'];
              $precioUnitario = $array_desgloceCompra[$i]['ValorUnitario'];
              $cantidad = $array_desgloceCompra[$i]['Cantidad'];
              //return response()->json(['status' => 'error','code' => 200,'message' => $cantidad]);
              $descuentoXUni = $array_desgloceCompra[$i]['Descuento'];
              $iva = $array_desgloceCompra[$i]['articulo_homologado_iva'];
              $retenciones = $array_desgloceCompra[$i]['retenciones'];
              $traslados = $array_desgloceCompra[$i]['traslados'];
              $usoArticulo = $array_desgloceCompra[$i]['articulo_homologado_uso'];
              $activoFijo = $array_desgloceCompra[$i]['articulo_homologado_activoFijo'];
              $activoIntangible = $array_desgloceCompra[$i]['articulo_homologado_activoIntangible'];
              $prorratea = $array_desgloceCompra[$i]['articulo_homologado_prorratea'];
              $det_prorrt = $prorratea ? 'prorratea' : 'no prorratea'; 
              return response()->json(['status' => $det_prorrt,'code' => 200,'message' => 'orden']);
              $periodicidadPc = $array_desgloceCompra[$i]['articulo_homologado_periodicidadPc'];
              $iteracionPc = $array_desgloceCompra[$i]['articulo_homologado_iteracionPc'];
              $periodoDetIndPc = $array_desgloceCompra[$i]['articulo_homologado_periodoDetIndPc'];
              $fechaFinPc = $array_desgloceCompra[$i]['articulo_homologado_fechaFinPc'];
              $tipoImporteVi = $array_desgloceCompra[$i]['articulo_homologado_tipoImporteVi'];
              $importeMinVi = $array_desgloceCompra[$i]['articulo_homologado_importeMinVi']; //importeMinVi
              $importeMaxVi = $array_desgloceCompra[$i]['articulo_homologado_importeMaxVi'];
              $importe = $JwtAuth->rellenaImportesCompras($array_desgloceCompra[$i]['Importe']);
              //return response()->json(['message' => 'pais11','codigo' => 200,'status' => 'error']);
              $validateActivos = false;
              $validatePeriodicidad = false;
              $validateDescuentos = false;
              $validateDecimalesMoneda = false;
              $validateForImpuRetenciones = false;
              $validateForImpuTraslados = false;

              if (
                isset($tokenArticulo) && !empty($tokenArticulo)
                && isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador)
                && isset($precioUnitario) && !empty($precioUnitario) && preg_match($patrónNumCosto, $precioUnitario)
                && isset($cantidad) && !empty($cantidad) && preg_match($patrónNum, $cantidad)
                //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
                && isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo)
                && isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc)
                && isset($importe) && !empty($importe) && preg_match($patrónNumCosto, $importe)
              ) {
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
                      'message' => 'El activo del producto/servicio ' . $concepto . ' es invalido o inexistente '
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
                      'message' => 'El descuento del producto/servicio ' . $concepto . ' es invalido o inexistente '
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
                          'message' => 'La fecha de fin de periodo de periodicidad de compra del producto/servicio ' . $concepto . ' es invalido o inexistente '
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
                        'message' => 'La iteración (repetición) de periodicidad de compra del producto/servicio ' . $concepto . ' es invalido o inexistente'
                      );
                      break;
                    }
                    if (!isset($periodoDetIndPc) || empty($periodoDetIndPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La selección de periodo de periodicidad de compra del producto/servicio ' . $concepto . ' es invalido o inexistente'
                      );
                      break;
                    }

                    if (!isset($tipoImporteVi) || empty($tipoImporteVi) || !preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'El tipo de variablidilad de importe del producto/servicio ' . $concepto . ' es invalido o inexistente '
                      );
                      break;
                    }
                    if (!isset($importeMinVi) || empty($importeMinVi) || !preg_match($patrónNumCosto, $importeMinVi)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'El importe mínimo de variabilidad del producto/servicio ' . $concepto . ' es invalido o inexistente '
                      );
                      break;
                    }
                    if (!isset($importeMaxVi) || empty($importeMaxVi) || !preg_match($patrónNumCosto, $importeMaxVi)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'El importe maximo de variabilidad del producto/servicio ' . $concepto . ' es invalido o inexistente '
                      );
                      break;
                    }
                  }
                }
                if ($periodicidadPc == 'eventual') {
                  $validatePeriodicidad = true;
                }

                if (count($retenciones) != 0) {
                  $countValidateRetencionesConcept = 0;
                  for ($t = 0; $t < count($retenciones); $t++) {
                    $base = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Base"]);
                    $explodeBase = explode('.', $base);
                    $impuesto = $retenciones[$t]["Impuesto"];
                    $tipoFactor = $retenciones[$t]["TipoFactor"];
                    $TasaOCuota = $retenciones[$t]["TasaOCuota"];
                    $importe = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]);
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
                            'message' => 'Base de retención del producto/servicio ' . $concepto . ' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
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
                          'message' => 'Impuesto de retención del producto/servicio ' . $concepto . ' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)'
                        );
                        break;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'TipoFactor de retención del producto/servicio ' . $concepto . ' no existe o esta vacio'
                        );
                        break;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'TasaOCuota de retención del producto/servicio ' . $concepto . ' no existe o esta vacio'
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
                          'message' => 'Importe de retención del producto/servicio ' . $concepto . ' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
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
                    $importe = $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]);
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
                            'message' => 'Base de traslado del producto/servicio ' . $concepto . ' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
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
                          'message' => 'Impuesto de traslado del producto/servicio ' . $concepto . ' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)'
                        );
                        break;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'TipoFactor de traslado del producto/servicio ' . $concepto . ' no existe o esta vacio'
                        );
                        break;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'TasaOCuota de traslado del producto/servicio ' . $concepto . ' no existe o esta vacio'
                        );
                        break;
                      }
                      if (!isset($importe) || empty($importe) || (strlen($explodeImporte[1]) != 6 && strlen($explodeImporte[1]) != $moneda_decimales)) {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'message' => 'Importe de traslado del producto/servicio ' . $concepto . ' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida (' . $moneda_decimales . ')'
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
                //return response()->json(['message' => 'final codigo','codigo' => 200,'status' => 'error']);
                if ($validateDescuentos == true && $validateDecimalesMoneda == true && $validatePeriodicidad == true && $validateActivos == true && $validateForImpuRetenciones == true && $validateForImpuTraslados == true) {
                  ++$contadorValidacionConceptos;
                }
              } else {
                if (!isset($tokenArticulo) || empty($tokenArticulo)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'producto/servicio ' . $concepto . ' invalidado'
                  );
                  break;
                }
                if (!isset($identificador) || empty($identificador) || !preg_match($JwtAuth->filtroAlfaNumerico(), $identificador)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'identificador del producto/servicio ' . $concepto . ' es incorrecto o inexistente'
                  );
                  break;
                }
                if (!isset($precioUnitario) || empty($precioUnitario) || !preg_match($patrónNumCosto, $precioUnitario)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El precio unitario del producto/servicio ' . $concepto . ' es invalido o inexistente'
                  );
                  break;
                }
                if (!isset($cantidad) || empty($cantidad) || !preg_match($patrónNum, $cantidad)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La cantidad del producto/servicio ' . $concepto . ' es invalida o inexistente'
                  );
                  break;
                }
                if (!isset($descuentoXUni) || empty($descuentoXUni) || !preg_match($patrónNumCosto, $descuentoXUni)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El descuento del producto/servicio ' . $concepto . ' es invalido o inexistente '
                  );
                  break;
                }
                if (!isset($usoArticulo) || empty($usoArticulo) || !preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El uso del producto/servicio ' . $concepto . ' es invalido o inexistente '
                  );
                  break;
                }
                if (!isset($periodicidadPc) || empty($periodicidadPc) || !preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La periodicidad de compra del producto/servicio ' . $concepto . ' es invalido o inexistente '
                  );
                  break;
                }
                if (!isset($importe) || empty($importe) || !preg_match($patrónNumCosto, $importe)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El importe del producto/servicio ' . $concepto . ' es invalido o inexistente '
                  );
                  break;
                }
              }
            }
            //return response()->json(['message' => 'pais12','codigo' => 200,'status' => 'error']);
            if (
              $validaReceptFact == true && $validaReceptArt == true && $validaLugarRecepcion == true &&
              $contadorValidacionConceptos == count($array_desgloceCompra)
            ) {
              $validaGeneralesCompra = true;
            }
          } else {
            $validaGeneralesCompra = false;
            if (!isset($parametrosArray['token_proveedor']) || empty($parametrosArray['token_proveedor']) || count($idProveedor) != 1) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'El proveedor seleccionado fue encontrado ' . count($idProveedor) . ' veces, verifique la información de su proveedor'
              );
            }
            if (!isset($parametrosArray['receptFactura']) || empty($parametrosArray['receptFactura'])) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se encontro respuesta a recepcion de facturas sobre esta compra, verifique su infomrmación'
              );
            }
            if (!isset($parametrosArray['classRecibeArtPago']) || is_bool($classRecibeArtPago) === false) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su infomrmación'
              );
            }
            if (!isset($parametrosArray['tipo_lugarEntrega']) || empty($parametrosArray['tipo_lugarEntrega'])) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se encontro respuesta a seleccion de lugar de entrega sobre esta compra, verifique su infomrmación'
              );
            }
            if (!isset($parametrosArray['moneda']) || empty($parametrosArray['moneda']) || count($idMoneda) != 1) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se encontro respuesta a seleccion de moneda sobre esta compra, verifique su infomrmación'
              );
            }
            if (!isset($parametrosArray['uso_cdfi']) || empty($parametrosArray['uso_cdfi'])) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se encontro respuesta a seleccion de uso de cfdi sobre esta compra, verifique su infomrmación'
              );
            }
            if (!isset($parametrosArray['array_desgloceCompra']) || empty($parametrosArray['array_desgloceCompra'])) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su infomrmación'
              );
            }
          }
          //return response()->json(['message' => 'pais13','codigo' => 200,'status' => 'error']);
          if ($validaGeneralesCompra == true) {

            $folioSistema = DB::select(
              "SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$usuario->empresa_token, $usuario->user_token]
            );
            //return response()->json(['message' => 'pais14','codigo' => 200,'status' => 'error']);                  
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select(
                  "SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                    WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?)",
                  [$usuario->empresa_token, $usuario->user_token]
                );

                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
                $folio_nuevo = 1;
              } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }

            if ($post_folio == NULL) {
              $folio_buy = 'COMP-' . $JwtAuth->generarFolio($folio_nuevo);
            } else {
              $folio_buy = 'COMP-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
            }
            //return response()->json(['message' => 'pais15','codigo' => 200,'status' => 'error']);
            $recibe_factura = FALSE;
            $nombreXml = '';
            $nombrePDf = '';
            $nombreRecePago = '';
            $nombreVerificacion = '';
            $txtForma_pago = '';
            $txtMetodo_pago = '';
            $recibe_producto = NULL;
            $pago_caja_tesoreria = NULL;
            $ktn_caja = NULL;
            $direccion_proveedor = '';
            $direccion_establecimiento = '';

            if ($receptFactura == true) {
              $recibe_factura = TRUE;
              //$idFormaPago = DB::select("SELECT id FROM forma_pago WHERE token_formapago = ?",[$token_formaPago]);
              //$idMetodoPago = DB::select("SELECT id FROM metodo_pago WHERE token_metodoPago = ?",[$token_metodoPago]);
              $txtForma_pago = $token_formaPago;
              $txtMetodo_pago = $token_metodoPago;

              $nombreXml = $request->file('imagenEvidenciaXMl')->getClientOriginalName();
              $nombrePDf = $request->file('imagenEvidenciaPdf')->getClientOriginalName();
              /*if ($txtForma_pago == 22 && $txtMetodo_pago == 2) {
                                $nombreRecePago = $JwtAuth->encriptar($request->file('imagenEvidenciaReceptPago')->getClientOriginalName());
                            }*/
              $nombreVerificacion = $request->file('imagenEvidenciaVerificacion')->getClientOriginalName();
            }

            $recibe_producto = $classRecibeArtPago ? TRUE : FALSE;
            if (!$classRecibeArtPago) {
              $pago_caja_tesoreria = FALSE;
              $ktn_caja = NULL;
            }

            //return response()->json(['message' => 'pais16','codigo' => 200,'status' => 'error']);
            if ($tipoLugarEntrega == 'proveedor') {
              $idDireccionProv = DB::select(
                "SELECT dir.id FROM teci_direcciones AS dir JOIN eegr_catalogo_proveedores AS catprov 
                                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                WHERE dir.token_direccion = ? AND dir.proveedor = catprov.id AND catprov.token_cat_proveedores = ?
                                AND catprov.administrador = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ?
                                AND empuser.usuario = users.id AND users.usuario_token = ?",
                [$tknLugarRecepcion, $token_proveedor, $usuario->empresa_token, $usuario->user_token]
              );

              $direccion_proveedor = $idDireccionProv[0]->id;
              $direccion_establecimiento = NULL;
            } else {
              $idDireccionEst = DB::select("SELECT estab.id FROM in_egr_establecimientos_catalogo AS estab JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN teci_usuarios_catalogo AS users WHERE estab.token_establecimiento = ? AND estab.empresa = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ? 
                            AND empuser.usuario = users.id AND users.usuario_token = ?", [$tknLugarRecepcion, $usuario->empresa_token, $usuario->user_token]);

              $direccion_proveedor = NULL;
              $direccion_establecimiento = $idDireccionEst[0]->id;
            }
            //return response()->json(['message' => 'pais17','codigo' => 200,'status' => 'error']);
            $tokenCompra = $JwtAuth->encriptarToken(time(), $token_proveedor, $moneda_codigo, $tipoLugarEntrega, $tknLugarRecepcion, $array_desgloceCompra);

            $fechaSistema = time();
            if ($validXmlFecha != '') {
              $fecha_altaCompra =  $JwtAuth->convierteFechaEpoc($validXmlFecha);
            } else {
              $fecha_altaCompra = time();
            }
            $nombreDocs = $fechaSistema . "-" . $folio_buy;
            //return response()->json(['message' => 'pais18','codigo' => 200,'status' => 'error']);
            $user_jerarquia = $selectEmp[0]->jerarquia_main == 'P' ? $selectEmp[0]->userr : NULL;

            $anticipoPrv = $parametrosArray['anticipoValor'];
            $anticipo = $anticipoPrv != '' ? DB::table("eegr_catalogo_proveedores_anticipo")->where("token_anticipo", $anticipoPrv)->value("id") : NULL;

            $status_autorizacion = $selectEmp[0]->jerarquia_main == 'P' ? TRUE : FALSE;
            //return response()->json(['message' => 'final codigo','codigo' => 200,'status' => 'error']);
            $compras = new ComprasModelo();
            $compras->token_compras = $tokenCompra;
            $compras->folio_compra = $folio_nuevo; //cifrado
            $compras->post_folio = $post_folio; //cifrado
            $compras->fecha_sistemaCompras = $fechaSistema;
            $compras->fecha_altaCompra = $fecha_altaCompra;
            $compras->proveedor = $idProveedor[0]->id;
            $compras->recibeFactura = TRUE;
            $compras->facturaXml = $JwtAuth->encriptar($nombreXml); //cifrado  
            $compras->facturaPdf = $JwtAuth->encriptar($nombrePDf);  //cifrado 
            $compras->recepcionPago = $nombreRecePago; //cifrado
            $compras->evidenciaSAT = $JwtAuth->encriptar($nombreDocs . $nombreVerificacion . ".pdf"); //cifrado
            $compras->anexos = $JwtAuth->encriptar($nombreDocs . ".pdf");  //cifrado
            $compras->reporte = $JwtAuth->encriptar($nombreDocs . ".pdf"); //cifrado
            $compras->moneda = $moneda_codigo;
            $compras->anticipo = $anticipo;
            $compras->forma_pago = $txtForma_pago;
            $compras->metodo_pago = $txtMetodo_pago;
            $compras->uso_cfdi = $token_usoCdfi;

            $compras->recibeProducto = $recibe_producto;
            $compras->pago_caja_tesoreria = $pago_caja_tesoreria;
            $compras->caja_paga = $ktn_caja;

            $compras->recepcion_prov = $direccion_proveedor;
            $compras->recepcion_estab = $direccion_establecimiento;
            $compras->comprador = $selectEmp[0]->id;
            $compras->usuario_comprador = $selectEmp[0]->userr;
            $compras->status_autorizacion = $status_autorizacion;
            $compras->autoriza = $user_jerarquia;
            $compras->status_cancelacion = FALSE;
            $compras->cancela = NULL;
            $compras->status_recepcion = FALSE;
            $compras->recibe = NULL;
            $compras->fecha_delete_compra = '';
            $compras->status_compra = TRUE;
            $insertCompra = $compras->save();
            //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
            if ($insertCompra) {
              $obtenCompra = $compras->id;

              if ($receptFactura == true) {
                $filepath = $selectEmp[0]->root_tkn . "/0002-cpp/compras/compras/" . $nombreDocs . "/";

                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

                Storage::putFileAs(
                  "/public/root/" . $filepath,
                  $request->file('imagenEvidenciaXMl'),
                  $request->file('imagenEvidenciaXMl')->getClientOriginalName()
                );

                Storage::putFileAs(
                  "/public/root/" . $filepath,
                  $request->file('imagenEvidenciaPdf'),
                  $request->file('imagenEvidenciaPdf')->getClientOriginalName()
                );

                Storage::putFileAs(
                  "/public/root/" . $filepath,
                  $request->file('imagenEvidenciaVerificacion'),
                  $request->file('imagenEvidenciaVerificacion')->getClientOriginalName()
                );
                //$nombreDocs
                //if ($txtForma_pago == 22 && $txtMetodo_pago == 2) {
                //    Storage::putFileAs("/public/root/".$filepath,$request->file('imagenEvidenciaReceptPago'),
                //    $request->file('imagenEvidenciaReceptPago')->getClientOriginalName().".pdf");
                //}
              }
              //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
              if ($selectEmp[0]->jerarquia_main == 'P') {
                //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
                if ($classRecibeArtPago == false) {
                  if ($pagoTesoreriaCaja == 'false') {
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
                    $folioOrden = DB::select(
                      "SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio
                                            FROM fnzs_pagos_orden AS ordenP JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                            JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$usuario->empresa_token, $usuario->user_token]
                    );

                    $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra);

                    $orderpay = new OrdenPagoModelo();
                    $orderpay->token_ordenPago = $tknOrder;
                    $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                    $orderpay->fecha_sistema_ordenp = time();
                    $orderpay->factura_compra = $obtenCompra;
                    $orderpay->factura_venta = NULL;
                    $orderpay->ord_proveedor = $idProveedor[0]->id;
                    $orderpay->fecha_delete_ordenPago = '';  //cifrado
                    $orderpay->orden_terminada_bool = FALSE; //cifrado
                    $orderpay->status_ordenPago = TRUE;  //cifrado
                    $orderpay->empresa = $selectEmp[0]->id;    //cifrado
                    $orderpay->comprador = $selectEmp[0]->userr; //cifrado
                    $insertOrder = $orderpay->save();
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                    if ($insertOrder) {
                      $contadorDetallecompra = 0;
                      for ($i = 0; $i < count($array_desgloceCompra); $i++) {
                        $validUpdtProd = false;
                        $validUpdtServ = false;
                        $tokenArticulo = $array_desgloceCompra[$i]['articulo_homologado_token'];
                        $identificador = $array_desgloceCompra[$i]['articulo_homologado_identificador'];
                        $concepto = $array_desgloceCompra[$i]['Descripcion'];
                        $precioUnitario = $array_desgloceCompra[$i]['ValorUnitario'];
                        $cantidad = $array_desgloceCompra[$i]['Cantidad'];
                        $descuentoXUni = $array_desgloceCompra[$i]['Descuento'];
                        if ($descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00') {
                          $total_descuento = $descuentoXUni;
                        } else {
                          $total_descuento = '0.00';
                        }
                        $iva = $array_desgloceCompra[$i]['articulo_homologado_iva'];
                        $retenciones = $array_desgloceCompra[$i]['retenciones'];
                        $traslados = $array_desgloceCompra[$i]['traslados'];
                        $usoArticulo = $array_desgloceCompra[$i]['articulo_homologado_uso'];
                        $alm_serie = $array_desgloceCompra[$i]['articulo_homologado_serie_token'];
                        $alm_lote = $array_desgloceCompra[$i]['articulo_homologado_lote_token'];
                        $alm_pedimento = $array_desgloceCompra[$i]['articulo_homologado_pedimento_token'];
                        $activoFijo = $array_desgloceCompra[$i]['articulo_homologado_activoFijo'];
                        $activoIntangible = $array_desgloceCompra[$i]['articulo_homologado_activoIntangible'];
                        $prorratea = $array_desgloceCompra[$i]['articulo_homologado_prorratea'];
                        $periodicidadPc = $array_desgloceCompra[$i]['articulo_homologado_periodicidadPc'];
                        $iteracionPc = $array_desgloceCompra[$i]['articulo_homologado_iteracionPc'];
                        $periodoDetIndPc = $array_desgloceCompra[$i]['articulo_homologado_periodoDetIndPc'];
                        $fechaFinPc = $array_desgloceCompra[$i]['articulo_homologado_fechaFinPc'];
                        $tipoImporteVi = $array_desgloceCompra[$i]['articulo_homologado_tipoImporteVi'];
                        $importeMinVi = $array_desgloceCompra[$i]['articulo_homologado_importeMinVi'];
                        $importeMaxVi = $array_desgloceCompra[$i]['articulo_homologado_importeMaxVi'];
                        $importe = $array_desgloceCompra[$i]['Importe'];
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                        $token_unidad_medida = $array_desgloceCompra[$i]['Unidad'];
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                        $token_producto = '';
                        $token_servicio = '';
                        $total_retenciones = '';
                        $total_traslado = '';
                        $activos_fijos = '';
                        $activos_intangibles = '';
                        $serie = NULL;
                        $lote = NULL;
                        $pedimento_aduanal = NULL;
                        $boolprorratea = FALSE;
                        $boolperiodicidadPc = FALSE;
                        $txtiteracionPc = NULL;
                        $boolperiodoDetIndPc = FALSE;
                        $txtfechaFinPc = NULL;
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                        //$catProdServ = DB::select("SELECT 
                        //    CASE WHEN ? IN (
                        //        SELECT catprod.token_cat_productos FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                        //        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                        //        WHERE catprod.proceso = TRUE AND catprod.status = TRUE AND catprod.admin_empresa = emp.id AND emp.empresa_token = ?
                        //        AND emp.id = empuser.empresa AND empuser.usuario = users.id
                        //        AND users.usuario_token = ?) THEN 'Producto' 
                        //        WHEN ? IN ( 
                        //        SELECT token_cat_servicios FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                        //        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                        //        WHERE catserv.proceso = 'c' AND catserv.status = TRUE AND catserv.administrador = emp.id 
                        //        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                        //        AND users.usuario_token = ?) THEN 'Servicio' END
                        //    AS identificador"
                        //    ,[$tokenArticulo,$usuario->empresa_token,$usuario->user_token,
                        //      $tokenArticulo,$usuario->empresa_token,$usuario->user_token]
                        //);

                        $catProdServ = DB::table(DB::raw('(SELECT
                                                CASE
                                                    WHEN ? IN (
                                                        SELECT catprod.token_cat_productos
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
                                                        SELECT catserv.token_cat_servicios
                                                        FROM in_egr_catalogo_servicios AS catserv
                                                        JOIN main_empresas AS emp ON catserv.administrador = emp.id
                                                        JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa
                                                        JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                                                        WHERE catserv.proceso = "c"
                                                          AND catserv.status = TRUE
                                                          AND emp.empresa_token = ?
                                                          AND users.usuario_token = ?
                                                    ) THEN "Servicio"
                                                END AS identificador
                                            ) AS subconsulta'))
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
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden_comptra']);
                        if ($catProdServ == 'Producto') {
                          $selectProd = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?", [$tokenArticulo]);
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden_beta']);
                          $token_producto = $selectProd[0]->id;
                          $token_servicio = NULL;
                          $activos_fijos = NULL;
                          $activos_intangibles = NULL;
                          if ($alm_serie != '') {
                            $consultSerie = DB::select("SELECT id FROM 	inventarios_catalogo_series WHERE serie_token = ?", [$alm_serie]);
                            $serie = $consultSerie[0]->id;
                          }
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden1'.$alm_serie]);
                          if ($alm_lote != '') {
                            $consultLote = DB::select("SELECT id FROM inventarios_catalogo_lotes WHERE token_lote = ?", [$alm_lote]);
                            $lote = $consultLote[0]->id;
                          }
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                          if ($alm_pedimento != '') {
                            $consultPedimentoAduanal = DB::select("SELECT id FROM inventarios_catalogo_pedimento_aduanal WHERE token_pedimento = ?", [$alm_pedimento]);
                            $pedimento_aduanal = $consultPedimentoAduanal[0]->id;
                          }
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                          if ($usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo)) {
                            $consult_activosFijos = DB::select("SELECT id FROM eegr_activos_fijos_catalogo WHERE token_act_fijos = ?", [$activoFijo]);
                            $activos_fijos = $consult_activosFijos[0]->id;
                          }
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden_activo']);
                        } else {
                          $selectServ = DB::select("SELECT id FROM in_egr_catalogo_servicios WHERE token_cat_servicios = ?", [$tokenArticulo]);
                          $token_producto = NULL;
                          $activos_fijos = NULL;
                          $activos_intangibles = NULL;
                          $token_servicio = $selectServ[0]->id;

                          if ($usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible)) {
                            $consult_activosIntan = DB::select("SELECT id FROM eegr_activos_intangibles_catalogo 
                                                            WHERE token_act_intang = ?", [$activoIntangible]);
                            $activos_intangibles = $consult_activosIntan[0]->id;
                          }
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                        $tokenDetalleCompra = $JwtAuth->encriptarToken(time() . $token_producto . $token_servicio . $tokenArticulo .
                          $identificador . $concepto . $precioUnitario . $cantidad . $total_descuento . $iva . $usoArticulo . $alm_serie .
                          $alm_lote . $alm_pedimento . $activoFijo . $activoIntangible . $periodicidadPc . $iteracionPc .
                          $periodoDetIndPc . $fechaFinPc . $tipoImporteVi . $importeMinVi . $importeMaxVi . $importe);

                        $total_retenciones = '0.00';
                        if (count($retenciones) != 0) {
                          $sumaImporteimp = 0;
                          for ($t = 0; $t < count($retenciones); $t++) {
                            $importe = $retenciones[$t]["Importe"];
                            $sumaImporteimp = $sumaImporteimp + $importe;
                          }
                          $total_retenciones = "" . $sumaImporteimp;
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden2']);
                        $total_traslado = '0.00';
                        if (count($traslados) > 0) {
                          $sumaImporteimp = 0;
                          for ($t = 0; $t < count($traslados); $t++) {
                            $importe = $traslados[$t]["Importe"];
                            $sumaImporteimp = $sumaImporteimp + $importe;
                          }

                          $total_traslado = "" . $sumaImporteimp;
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden3']);
                        if ($periodicidadPc == 'periodo') {
                          $boolperiodicidadPc = TRUE;
                          $txtiteracionPc = $iteracionPc;
                          if ($periodoDetIndPc == 'determinado') {
                            $boolperiodoDetIndPc = TRUE;
                            $txtfechaFinPc = $JwtAuth->convierteFechaEpoc($fechaFinPc);
                          }
                          if ($periodoDetIndPc == 'indeterminado') {
                            $boolperiodoDetIndPc = FALSE;
                            $txtfechaFinPc = NULL;
                          }
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden4']);
                        if ($periodicidadPc == 'eventual') {
                          $boolperiodicidadPc = FALSE;
                          $txtiteracionPc = NULL;
                          $boolperiodoDetIndPc = FALSE;
                          $txtfechaFinPc = NULL;
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden5_1']);
                        //return response()->json(['status' => 'error','code' => 200,'message' => $total_traslado]);
                        $boolprorratea = $prorratea == true ? TRUE : FALSE;
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                        //$select_unidad_medida = DB::select("SELECT id FROM unidad_medida WHERE token_unidad_medida = ?",[$token_unidad_medida]); 
                        //return response()->json(['status' => 'error','code' => 200,'message' => $total_descuento]);
                        $insertDetCompra = DB::table('eegr_compras_detalle')
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
                          ));
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                        $selectDetBuy = DB::table("eegr_compras_detalle")->where("token_detcompra", $tokenDetalleCompra)->value("id");
                        if ($token_producto != NULL && $token_producto != '') {
                          //$selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp 
                          //JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                          //JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                          //WHERE detcomp.token_detcompra = ? ANd detcomp.producto = catprod.id 
                          //AND catprod.token_cat_productos = ? AND dexkar.empresa = emp.id AND emp.empresa_token = ?
                          //AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                          //[$tokenDetalleCompra,$tokenArticulo,$usuario->empresa_token,$usuario->user_token]);
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden8']);
                          $folioKardex = DB::select("SELECT
                                                    IF (max(dexkar.folio_kardex) IS NOT NULL,(max(dexkar.folio_kardex)+1),1) AS folio
                                                    FROM in_egr_productos_kardex AS dexkar JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                                    WHERE dexkar.producto = catprod.id AND catprod.token_cat_productos = ? 
                                                    AND catprod.admin_empresa = emp.id AND emp.empresa_token = ?
                                                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden7.1.'.$selectDetBuy]);

                          $token_kardex =  $JwtAuth->encriptarToken(time(), $folioKardex[0]->folio, $obtenCompra, $selectDetBuy);
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden7.1.ka.'.$precioUnitario]);  
                          $insertKardex = DB::table("in_egr_productos_kardex")
                            ->insert(array(
                              "token_kardex" => $token_kardex,
                              "folio_kardex" => $folioKardex[0]->folio,
                              "fecha_kardex" => $fechaSistema,
                              "status_kardex" => 2,
                              "producto" => $token_producto,
                              "concepto" => "por recibir",
                              "factura_compra" => $obtenCompra,
                              "detalle_compra" => $selectDetBuy,
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
                          //return response()->json(['status' => 'error','code' => 200,'message' => 'orden kardex 7.1.'.$selectDetBuy]);  
                          if ($boolperiodicidadPc == FALSE) {
                            //echo $selectPseudoCompra[$pc]->periodicidad;
                            $validUpdtProd = true;
                          } else {
                            $selector = DB::select("SELECT periodicidad,repeticion_periodo,
                                                        tipo_periodo,fecha_finPeriodo,tipo_variabilidad,importe_minimo,
                                                        importe_maximo FROM in_egr_catalogo_productos WHERE id = ?", [$token_producto]);

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

                              if ($upDateProducto) {
                                $validUpdtProd = true;
                              } else {
                                $validUpdtProd = false;
                              }
                            } else {
                              $validUpdtProd = true;
                            }
                          }
                        } else {
                          $validUpdtProd = true;
                        }

                        if ($token_servicio != NULL && $token_servicio != '') {
                          if ($boolperiodicidadPc == FALSE) {
                            $validUpdtServ = true;
                          } else {
                            $selector = DB::select("SELECT periodicidad,repeticion_periodo,
                                                            tipo_periodo,fecha_finPeriodo,tipo_variabilidad,importe_minimo,
                                                            importe_maximo FROM in_egr_catalogo_servicios WHERE id = ?", [$token_servicio]);

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

                        if ($insertDetCompra  && $validUpdtProd == true && $validUpdtServ == true) {
                          ++$contadorDetallecompra;
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => 'orden_7']);
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
                                "empresa" => $selectEmp[0]->id,
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
                          'message' => 'Compra registrada y autorizada con el folio ' . $folio_buy . ', revise ordenes de pago',
                          'code' => 200,
                          'status' => 'success'
                        );
                      }
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'No se generó orden de pago para esta compra debido a errores internos, para mayor información comuniquese a soporte'
                      );
                    }
                  }
                }

                if ($classRecibeArtPago == true) {
                  //return response()->json(['message' => 'pruebaaaa','codigo' => 200,'status' => 'error']);
                  $folioOrden = DB::select("SELECT
                                        IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                        WHERE ordenP.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                        AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

                  $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra);

                  $orderpay = new OrdenPagoModelo();
                  $orderpay->token_ordenPago = $tknOrder;
                  $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                  $orderpay->fecha_sistema_ordenp = time();
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor[0]->id;
                  $orderpay->autorizacion_pay = TRUE;
                  $orderpay->fecha_autorizacion_pay = time();
                  $orderpay->tentativa_pago = time();
                  $orderpay->orden_terminada_bool = TRUE;
                  $orderpay->orden_terminada_fecha = time();
                  $orderpay->status_ordenPago = FALSE; //cifrado
                  $orderpay->empresa = $selectEmp[0]->id;    //cifrado
                  $orderpay->comprador = $selectEmp[0]->userr; //cifrado
                  $insertOrder = $orderpay->save();

                  if ($insertOrder) {
                    $contadorDetallecompra = 0;
                    for ($i = 0; $i < count($array_desgloceCompra); $i++) {
                      $validUpdtProd = false;
                      $validUpdtServ = false;
                      $tokenArticulo = $array_desgloceCompra[$i]['articulo_homologado_token'];
                      $identificador = $array_desgloceCompra[$i]['articulo_homologado_identificador'];
                      $concepto = $array_desgloceCompra[$i]['Descripcion'];
                      $precioUnitario = $array_desgloceCompra[$i]['ValorUnitario'];
                      $cantidad = $array_desgloceCompra[$i]['Cantidad'];
                      $descuentoXUni = $array_desgloceCompra[$i]['Descuento'];
                      if ($descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00') {
                        $total_descuento = $descuentoXUni;
                      } else {
                        $total_descuento = '0.00';
                      }
                      $iva = $array_desgloceCompra[$i]['articulo_homologado_iva'];
                      $retenciones = $array_desgloceCompra[$i]['retenciones'];
                      $traslados = $array_desgloceCompra[$i]['traslados'];
                      $usoArticulo = $array_desgloceCompra[$i]['articulo_homologado_uso'];
                      $alm_serie = $array_desgloceCompra[$i]['articulo_homologado_serie_token'];
                      $alm_lote = $array_desgloceCompra[$i]['articulo_homologado_lote_token'];
                      $alm_pedimento = $array_desgloceCompra[$i]['articulo_homologado_pedimento_token'];
                      $activoFijo = $array_desgloceCompra[$i]['articulo_homologado_activoFijo'];
                      $activoIntangible = $array_desgloceCompra[$i]['articulo_homologado_activoIntangible'];
                      $prorratea = $array_desgloceCompra[$i]['articulo_homologado_prorratea'];
                      $periodicidadPc = $array_desgloceCompra[$i]['articulo_homologado_periodicidadPc'];
                      $iteracionPc = $array_desgloceCompra[$i]['articulo_homologado_iteracionPc'];
                      $periodoDetIndPc = $array_desgloceCompra[$i]['articulo_homologado_periodoDetIndPc'];
                      $fechaFinPc = $array_desgloceCompra[$i]['articulo_homologado_fechaFinPc'];
                      $tipoImporteVi = $array_desgloceCompra[$i]['articulo_homologado_tipoImporteVi'];
                      $importeMinVi = $array_desgloceCompra[$i]['articulo_homologado_importeMinVi'];
                      $importeMaxVi = $array_desgloceCompra[$i]['articulo_homologado_importeMaxVi'];
                      $importe = $array_desgloceCompra[$i]['Importe'];
                      $token_unidad_medida = $array_desgloceCompra[$i]['Unidad'];

                      $token_producto = '';
                      $token_servicio = '';
                      $total_retenciones = '';
                      $total_traslado = '';
                      $activos_fijos = '';
                      $activos_intangibles = '';
                      $serie = NULL;
                      $lote = NULL;
                      $pedimento_aduanal = NULL;
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

                      if ($catProdServ == 'Producto') {
                        $selectProd = DB::select(
                          "SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?",
                          [$tokenArticulo]
                        );
                        $token_producto = $selectProd[0]->id;
                        $token_servicio = NULL;
                        $activos_fijos = NULL;
                        $activos_intangibles = NULL;
                        if ($alm_serie != '') {
                          $consultSerie = DB::select("SELECT id FROM serie WHERE token_serie = ?", [$alm_serie]);
                          $serie = $consultSerie[0]->id;
                        }

                        if ($alm_lote != '') {
                          $consultLote = DB::select("SELECT id FROM lote_prod WHERE token_lote = ?", [$alm_lote]);
                          $lote = $consultLote[0]->id;
                        }

                        if ($alm_pedimento != '') {
                          $consultPedimentoAduanal = DB::select(
                            "SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?",
                            [$alm_pedimento]
                          );
                          $pedimento_aduanal = $consultPedimentoAduanal[0]->id;
                        }

                        if ($usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo)) {
                          $consult_activosFijos = DB::select(
                            "SELECT id FROM activos_fijos WHERE token_act_fijos = ?",
                            [$activoFijo]
                          );
                          $activos_fijos = $consult_activosFijos[0]->id;
                        }
                      } else {
                        $selectServ = DB::select(
                          "SELECT id FROM in_egr_catalogo_servicios WHERE token_cat_servicios = ?",
                          [$tokenArticulo]
                        );
                        $token_producto = NULL;
                        $activos_fijos = NULL;
                        $activos_intangibles = NULL;
                        $token_servicio = $selectServ[0]->id;

                        if ($usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible)) {
                          $consult_activosIntan = DB::select("SELECT id FROM activos_intangibles 
                                                        WHERE token_act_intang = ?", [$activoIntangible]);
                          $activos_intangibles = $consult_activosIntan[0]->id;
                        }
                      }

                      $tokenDetalleCompra = $JwtAuth->encriptarToken(time() . $token_producto . $token_servicio . $tokenArticulo .
                        $identificador . $concepto . $precioUnitario . $cantidad . $total_descuento . $iva . $usoArticulo . $alm_serie .
                        $alm_lote . $alm_pedimento . $activoFijo . $activoIntangible . $periodicidadPc . $iteracionPc .
                        $periodoDetIndPc . $fechaFinPc . $tipoImporteVi . $importeMinVi . $importeMaxVi . $importe);

                      if (count($retenciones) != 0) {
                        $sumaImporteimp = 0;
                        for ($t = 0; $t < count($retenciones); $t++) {
                          $importe = $retenciones[$t]["Importe"];
                          $sumaImporteimp = $sumaImporteimp + $importe;
                        }
                        $total_retenciones = $sumaImporteimp;
                      } else {
                        $total_retenciones = '0.00';
                      }

                      if (count($traslados) != 0) {
                        $sumaImporteimp = 0;
                        for ($t = 0; $t < count($traslados); $t++) {
                          $importe = $traslados[$t]["Importe"];
                          $sumaImporteimp = $sumaImporteimp + $importe;
                        }

                        $total_traslado = $sumaImporteimp;
                      } else {
                        $total_traslado = '0.00';
                      }

                      if ($periodicidadPc == 'periodo') {
                        $boolperiodicidadPc = TRUE;
                        $txtiteracionPc = $iteracionPc;
                        if ($periodoDetIndPc == 'determinado') {
                          $boolperiodoDetIndPc = TRUE;
                          $txtfechaFinPc = $JwtAuth->convierteFechaEpoc($fechaFinPc);
                        }
                        if ($periodoDetIndPc == 'indeterminado') {
                          $boolperiodoDetIndPc = FALSE;
                          $txtfechaFinPc = NULL;
                        }
                      }
                      if ($periodicidadPc == 'eventual') {
                        $boolperiodicidadPc = FALSE;
                        $txtiteracionPc = NULL;
                        $boolperiodoDetIndPc = FALSE;
                        $txtfechaFinPc = NULL;
                      }
                      //return response()->json(['status' => 'error','code' => 200,'message' => $total_traslado]);
                      if ($prorratea == true) {
                        $boolprorratea = TRUE;
                      } else {
                        $boolprorratea = FALSE;
                      }

                      //return response()->json(['status' => 'error','code' => 200,'message' => $total_descuento]);
                      $insertDetCompra = DB::table('eegr_compras_detalle')
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
                        ));

                      if ($token_producto != NULL && $token_producto != '') {

                        $selectDetBuy = DB::select(
                          "SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
                                                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                                WHERE detcomp.token_detcompra = ? ANd detcomp.producto = catprod.id AND catprod.token_cat_productos = ? 
                                                AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                                AND empuser.usuario = users.id AND users.usuario_token = ?",
                          [$tokenDetalleCompra, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]
                        );

                        $folioKardex = DB::select("SELECT
                                                IF (max(dexkar.folio_kardex) IS NOT NULL,(max(dexkar.folio_kardex)+1),1) AS folio FROM in_egr_productos_kardex AS dexkar 
                                                JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                                JOIN teci_usuarios_catalogo AS users WHERE dexkar.producto = catprod.id AND catprod.token_cat_productos = ? 
                                                AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                                AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

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

                        if ($boolperiodicidadPc == FALSE) {
                          //echo $selectPseudoCompra[$pc]->periodicidad;
                          $validUpdtProd = true;
                        } else {
                          $selector = DB::select("SELECT periodicidad,repeticion_periodo,
                                                    tipo_periodo,fecha_finPeriodo,tipo_variabilidad,importe_minimo,
                                                    importe_maximo FROM in_egr_catalogo_productos WHERE id = ?", [$token_producto]);

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

                            if ($upDateProducto) {
                              $validUpdtProd = true;
                            } else {
                              $validUpdtProd = false;
                            }
                          } else {
                            $validUpdtProd = true;
                          }
                        }
                      } else {
                        $validUpdtProd = true;
                      }

                      if ($token_servicio != NULL && $token_servicio != '') {
                        if ($boolperiodicidadPc == FALSE) {
                          $validUpdtServ = true;
                        } else {
                          //return response()->json(['message' => 'error GeneralesCompra'.$importeMinVi,'codigo' => 200,'status' => 'error']);

                          $selector = DB::select("SELECT periodicidad,repeticion_periodo,
                                                        tipo_periodo,fecha_finPeriodo,tipo_variabilidad,importe_minimo,
                                                        importe_maximo FROM in_egr_catalogo_servicios WHERE id = ?", [$token_servicio]);

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

                      if ($insertDetCompra  && $validUpdtProd == true && $validUpdtServ == true) {
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
                              "empresa" => $selectEmp[0]->id,
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
                        'message' => 'Compra registrada y autorizada con el folio ' . $folio_buy . ', revise ordenes de pago',
                        'code' => 200,
                        'status' => 'success'
                      );
                    }
                  } else {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'No se guardó orden de pago para esta compra debido a errores internos, para mayor información comuniquese a soporte'
                    );
                  }
                }
              }

              if ($selectEmp[0]->jerarquia_main == 'tD') {
                $contadorDetallecompra = 0;
                for ($i = 0; $i < count($array_desgloceCompra); $i++) {
                  $tokenArticulo = $array_desgloceCompra[$i]['tokenArticulo'];
                  $identificador = $array_desgloceCompra[$i]['identificador'];
                  $concepto = $array_desgloceCompra[$i]['concepto'];
                  $precioUnitario = $array_desgloceCompra[$i]['precioUnitario'];
                  $cantidad = $array_desgloceCompra[$i]['cantidad'];
                  $descuentoXUni = $array_desgloceCompra[$i]['descuentoXUni'];
                  $iva = $array_desgloceCompra[$i]['iva'];
                  $retenciones = $array_desgloceCompra[$i]['retenciones'];
                  $traslados = $array_desgloceCompra[$i]['traslados'];
                  $usoArticulo = $array_desgloceCompra[$i]['usoArticulo'];
                  $alm_serie = $array_desgloceCompra[$i]['alm_serie'];
                  $alm_lote = $array_desgloceCompra[$i]['alm_lote'];
                  $alm_pedimento = $array_desgloceCompra[$i]['alm_pedimento'];
                  $activoFijo = $array_desgloceCompra[$i]['activoFijo'];
                  $activoIntangible = $array_desgloceCompra[$i]['activoIntangible'];
                  $prorratea = $array_desgloceCompra[$i]['prorratea'];
                  $periodicidadPc = $array_desgloceCompra[$i]['periodicidadPc'];
                  $iteracionPc = $array_desgloceCompra[$i]['iteracionPc'];
                  $periodoDetIndPc = $array_desgloceCompra[$i]['periodoDetIndPc'];
                  $fechaFinPc = $array_desgloceCompra[$i]['fechaFinPc'];
                  $tipoImporteVi = $array_desgloceCompra[$i]['tipoImporteVi'];
                  $importeMinVi = $array_desgloceCompra[$i]['importMinVi'];
                  $importeMaxVi = $array_desgloceCompra[$i]['importeMaxVi'];
                  $importe = $array_desgloceCompra[$i]['importe'];

                  $token_producto = '';
                  $token_servicio = '';
                  $total_retenciones = '';
                  $total_traslado = '';
                  $activos_fijos = '';
                  $activos_intangibles = '';
                  $boolprorratea = FALSE;
                  $serie = NULL;
                  $lote = NULL;
                  $pedimento_aduanal = NULL;
                  $boolperiodicidadPc = FALSE;
                  $txtiteracionPc = NULL;
                  $boolperiodoDetIndPc = FALSE;
                  $txtfechaFinPc = NULL;

                  $catProdServ = DB::select(
                    "SELECT 
                                        CASE WHEN ? IN (
                                            SELECT token_cat_productos FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprod.status = TRUE 
                                            AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                            AND empuser.usuario = users.id AND users.usuario_token = ?) THEN 'Producto' 
                                            WHEN ? IN ( 
                                            SELECT token_cat_servicios FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catserv.proceso = TRUE 
                                            AND catserv.status = TRUE AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                            AND empuser.usuario = users.id AND users.usuario_token = ?) THEN 'Servicio' END
                                        AS identificador",
                    [
                      $tokenArticulo,
                      $usuario->empresa_token,
                      $usuario->user_token,
                      $tokenArticulo,
                      $usuario->empresa_token,
                      $usuario->user_token
                    ]
                  );

                  if ($catProdServ[0]->identificador == 'Producto') {
                    $selectProd = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?", [$tokenArticulo]);
                    $token_producto = $selectProd[0]->id;
                    $token_servicio = NULL;
                    $activos_fijos = NULL;
                    $activos_intangibles = NULL;
                    if ($alm_serie != '') {
                      $consultSerie = DB::select("SELECT id FROM serie WHERE token_serie = ?", [$alm_serie]);
                      $serie = $consultSerie[0]->id;
                    }

                    if ($alm_lote != '') {
                      $consultLote = DB::select("SELECT id FROM lote_prod WHERE token_lote = ?", [$alm_lote]);
                      $lote = $consultLote[0]->id;
                    }

                    if ($alm_pedimento != '') {
                      $consultPedimentoAduanal = DB::select("SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?", [$alm_pedimento]);
                      $pedimento_aduanal = $consultPedimentoAduanal[0]->id;
                    }

                    if ($usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo)) {
                      $consult_activosFijos = DB::select("SELECT id FROM activos_fijos WHERE token_act_fijos = ?", [$activoFijo]);
                      $activos_fijos = $consult_activosFijos[0]->id;
                    }
                  } else {
                    $selectServ = DB::select("SELECT id FROM in_egr_catalogo_servicios WHERE token_cat_servicios = ?", [$tokenArticulo]);
                    $token_producto = NULL;
                    $activos_fijos = NULL;
                    $activos_intangibles = NULL;
                    $token_servicio = $selectServ[0]->id;

                    if ($usoArticulo == 'activo_intangible' && isset($activoIntangible) && !empty($activoIntangible)) {
                      $consult_activosIntan = DB::select("SELECT id FROM activos_intangibles WHERE token_act_intang = ?", [$activoIntangible]);
                      $activos_intangibles = $consult_activosIntan[0]->id;
                    }
                  }

                  $tokenDetalleCompra = $JwtAuth->encriptarToken(time() . $token_producto . $token_servicio . $tokenArticulo . $identificador
                    . $concepto . $precioUnitario . $cantidad . $descuentoXUni . $iva . $usoArticulo . $alm_serie . $alm_lote . $alm_pedimento
                    . $activoFijo . $activoIntangible . $periodicidadPc . $iteracionPc . $periodoDetIndPc . $fechaFinPc . $tipoImporteVi
                    . $importeMinVi . $importeMaxVi . $importe);

                  if (count($retenciones) != 0) {
                    $sumaImporteimp = 0;
                    for ($t = 0; $t < count($retenciones); $t++) {
                      $importe = $retenciones[$t]["Importe"][0];
                      $sumaImporteimp = $sumaImporteimp + $importe;
                    }
                    $total_retenciones = $sumaImporteimp;
                  } else {
                    $total_retenciones = '0.00';
                  }

                  if (count($traslados) != 0) {
                    $sumaImporteimp = 0;
                    for ($t = 0; $t < count($traslados); $t++) {
                      $importe = $traslados[$t]["Importe"][0];
                      $sumaImporteimp = $sumaImporteimp + $importe;
                    }

                    $total_traslado = $sumaImporteimp;
                  } else {
                    $total_traslado = '0.00';
                  }

                  if ($periodicidadPc == 'periodo') {
                    $boolperiodicidadPc = TRUE;
                    $txtiteracionPc = $iteracionPc;
                    if ($periodoDetIndPc == 'determinado') {
                      $boolperiodoDetIndPc = TRUE;
                      $txtfechaFinPc = $JwtAuth->convierteFechaEpoc($fechaFinPc);
                    }
                    if ($periodoDetIndPc == 'indeterminado') {
                      $boolperiodoDetIndPc = FALSE;
                      $txtfechaFinPc = NULL;
                    }
                  }
                  if ($periodicidadPc == 'eventual') {
                    $boolperiodicidadPc = FALSE;
                    $txtiteracionPc = NULL;
                    $boolperiodoDetIndPc = FALSE;
                    $txtfechaFinPc = NULL;
                  }

                  if ($prorratea == true) {
                    $boolprorratea = TRUE;
                  } else {
                    $boolprorratea = FALSE;
                  }

                  $insertDetCompra = DB::table('detalle_pseudocompra')
                    ->insert(array(
                      "token_detcompra" => $tokenDetalleCompra,
                      "numero_compra" => $obtenCompra,
                      "producto" => $token_producto,
                      "servicio" => $token_servicio,
                      "precio_unitario" => $precioUnitario,
                      "cantidad" => $cantidad,
                      "descuento" => $descuentoXUni,
                      "total_retenciones" => $total_retenciones,
                      "total_traslados" => $total_traslado,
                      "destino" => $usoArticulo,
                      "activos_fijos" => $activos_fijos,
                      "activos_intangibles" => $activos_intangibles,
                      "serie" => $serie,
                      "lote" => $lote,
                      "pedimento_aduanal" => $pedimento_aduanal,
                      "prorratea" => $boolprorratea,
                      "periodicidad" => $boolperiodicidadPc,
                      "repeticion_periodo" => $txtiteracionPc,
                      "tipo_periodo" => $boolperiodoDetIndPc,
                      "fecha_finPeriodo" => $txtfechaFinPc,
                      "tipo_variabilidad" => $tipoImporteVi,
                      "importe_minimo" => $importeMinVi,
                      "importe_maximo" => $importeMaxVi,
                      "status_recepcion" => FALSE,
                      "empresa" => $selectEmp[0]->id,
                    ));

                  if ($insertDetCompra) {
                    $contadorDetallecompra++;
                  }
                }

                if ($insertCompra && $contadorDetallecompra == count($array_desgloceCompra)) {
                  $dataMensaje = array(
                    'message' => 'Compra registrada con el folio ' . $folio_buy . ', espere autorización',
                    'code' => 200,
                    'status' => 'success'
                  );
                }
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
            'message' => 'No tiene permisos para registrar esta compra'
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

  //seguimiento 
  public function listaComprasGeneral(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    $arrayCompras = array();
    $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where(["eegr_compras.status_compra" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->orderBy("eegr_compras.id","DESC")->get();

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
      $total_art_recibidos = 0;

      $queryDEtailsTotal = DB::table("eegr_compras AS buy")
      ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
      ->where(["buy.token_compras" => $vBuy->token_compras])->get();

      foreach ($queryDEtailsTotal as $vDet) {
        $resultante = 0;
        $det_precio_unitario = $vDet->precio_unitario;
        $det_descuento = $vDet->descuento;
        $det_total_traslados = $vDet->traslados_total;
        $det_total_retenciones = $vDet->retenciones_total;
        $resultante = $det_precio_unitario - $det_descuento + $det_total_traslados - $det_total_retenciones;
        $importe_total_compra = $importe_total_compra + $resultante;
        
        if($vDet->producto){
          $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
          ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
          ->where(["rec.producto" => $vDet->producto])->get();
          //echo "count(queryRecepcionPRD) ".count($queryRecepcionPRD);
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

        /*
        eegr_compras_recepcion
        2	token_recept_compra	text	latin1_swedish_ci		Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        3	fecha_recep	varchar(10)	latin1_swedish_ci		No	Ninguna			Cambiar Cambiar	Eliminar Eliminar	
        4	folio_recep	int(5)			No	Ninguna			Cambiar Cambiar	Eliminar Eliminar	
        5	compra Índice	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        6	detalle_compra Índice	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        7	producto	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        8	activo_fijo	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        9	activo_intangible
        */

        /*
        eegr_compras_devengacion
        2	token_devengacion_compra	text	latin1_swedish_ci		Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        3	fecha_dev_sistema	varchar(10)	latin1_swedish_ci		No	Ninguna			Cambiar Cambiar	Eliminar Eliminar	
        4	folio_devengacion	int(5)			No	Ninguna			Cambiar Cambiar	Eliminar Eliminar	
        5	compra	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        6	detalle_compra	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
        7	servicio
        */
      }


      $ordPagoExists = DB::table("fnzs_pagos_orden AS orden")
      ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
      ->where("orden.status_ordenPago",TRUE)
      ->where("buy.token_compras",$vBuy->token_compras)
      ->count();
      //echo "ordPagoExists $ordPagoExists ";
      
      $arrayForeach = array(
        "token_compras" => $vBuy->token_compras,
        "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
        "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vBuy->fecha_contabilizacion),
        "fecha_vencimiento" => gmdate('Y-m-d H:i:s', $vBuy->fecha_vencimiento),
        "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
        "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
        "urlFactXml" => !empty($vBuy->facturaXml) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
        "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
        "urlFactPdf" => !empty($vBuy->facturaPdf) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
        "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
        "urlEvdSAT" => !empty($vBuy->evidenciaSAT) ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
        "documentos" => $vBuy->documentos,
        "reporte" => $vBuy->reporte,
        "forma_pago" => $vBuy->forma_pago,
        "metodo_pago" => $vBuy->metodo_pago,
        "recepcionPago" => $vBuy->recepcionPago,
        "recibeProducto" => $vBuy->recibeProducto ? true : false,// si es TRUE genera orden de pago, si es FALSE no
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
        "existe_orden_pago" => $ordPagoExists > 0 ? true : false,
        //"comprador" => $vBuy->comprador,
        "usuario_comprador" => $user_compra,
        //productos y servicios
        "articulos_recibidos" => $total_art_recibidos,
        "total_articulos" => count($queryDEtailsTotal),
        "recepcionCollapsed" => true,
        //importes
        "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
        //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab, 
        "desglose_compra" => [],
      );
      $arrayCompras[] = $arrayForeach;
    }

    return response()->json([
      'compras' => $arrayCompras,
      'codigo' => 200,
      'status' => 'success'
    ]);
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
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
        ->get();

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
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
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
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
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
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
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

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
        WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $listaCompra = DB::select(
          "SELECT comp.id,comp.folio_compra,comp.proveedor,comp.usuario_comprador 
    					FROM eegr_compras AS comp JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
    					JOIN teci_usuarios_catalogo AS users WHERE comp.token_compras = ? 
    					AND comp.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
    					AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$parametrosArray['token_compra'], $usuario->empresa_token, $usuario->user_token]
        );

        if (count($listaCompra) == 1) {
          $folioOrden = DB::select("SELECT
    						IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
    						AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);


          $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $parametrosArray['token_compra']);

          $upDateautorizCompra = DB::table('eegr_compras AS buy')
            ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'buy.status_compra' => TRUE,
              'buy.token_compras' => $parametrosArray['token_compra'],
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                "buy.status_autorizacion" => TRUE,
                "buy.autoriza" => $selectEmp[0]->userr,
              )
            );

          //echo $upDateautorizCompra." autorizasa";

          if ($upDateautorizCompra) {
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = time();
            $orderpay->factura_compra = $obtenCompra;
            $orderpay->factura_venta = NULL;
            $orderpay->proveedor = $idProveedor[0]->id;
            $orderpay->cliente = NULL; //cifrado
            $orderpay->fecha_delete_ordenPago = '';  //cifrado
            $orderpay->status = TRUE;  //cifrado
            $orderpay->status_pago = FALSE; //cifrado
            $orderpay->empresa = $selectEmp[0]->id;    //cifrado
            $orderpay->comprador = $selectEmp[0]->userr; //cifrado
            $insertOrder = $orderpay->save();
            if ($insertOrder) {
              $countSelectPseudoCompra = 0;
              $selectPseudoCompra = DB::select("SELECT * FROM detalle_pseudocompra WHERE numero_compra = ?", [$obtenCompra]);

              for ($pc = 0; $pc < count($selectPseudoCompra); $pc++) {
                $validUpdtProd = false;
                $validUpdtServ = false;
                $insertDetCompra = DB::table('detalle_compra')
                  ->insert(array(
                    "token_detcompra" => $JwtAuth->encriptarToken($selectPseudoCompra[$pc]->token_detcompra . $parametrosArray['token_compra']),
                    "numero_compra" => $selectPseudoCompra[$pc]->numero_compra,
                    "producto" => $selectPseudoCompra[$pc]->producto,
                    "servicio" => $selectPseudoCompra[$pc]->servicio,
                    "precio_unitario" => $selectPseudoCompra[$pc]->precio_unitario,
                    "cantidad" => $selectPseudoCompra[$pc]->cantidad,
                    "descuento" => $selectPseudoCompra[$pc]->descuento,
                    "total_retenciones" => $selectPseudoCompra[$pc]->retenciones_total,
                    "total_traslados" => $selectPseudoCompra[$pc]->traslados_total,
                    "destino" => $selectPseudoCompra[$pc]->destino,
                    "activo_fijo" => $selectPseudoCompra[$pc]->activos_fijos,
                    "activo_intangible" => $selectPseudoCompra[$pc]->activos_intangibles,
                    "serie" => $selectPseudoCompra[$pc]->serie,
                    "lote" => $selectPseudoCompra[$pc]->lote,
                    "pedimento_aduanal" => $selectPseudoCompra[$pc]->pedimento_aduanal,
                    "status_recepcion" => $selectPseudoCompra[$pc]->status_recepcion,
                    "empresa" => $selectPseudoCompra[$pc]->empresa,
                  ));

                if ($selectPseudoCompra[$pc]->producto != NULL && $selectPseudoCompra[$pc]->producto != '') {
                  if ($selectPseudoCompra[$pc]->periodicidad == FALSE) {
                    //echo $selectPseudoCompra[$pc]->periodicidad;
                    $validUpdtProd = true;
                  } else {
                    $upDateProducto = DB::table('in_egr_catalogo_productos')
                      ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                      ->where([
                        'in_egr_catalogo_productos.status' => TRUE,
                        'in_egr_catalogo_productos.id' => $selectPseudoCompra[$pc]->producto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          "in_egr_catalogo_productos.periodicidad" => $selectPseudoCompra[$pc]->periodicidad,
                          "in_egr_catalogo_productos.repeticion_periodo" => $selectPseudoCompra[$pc]->repeticion_periodo,
                          "in_egr_catalogo_productos.tipo_periodo" => $selectPseudoCompra[$pc]->tipo_periodo,
                          "in_egr_catalogo_productos.fecha_finPeriodo" => $JwtAuth->convierteFechaEpoc($selectPseudoCompra[$pc]->fecha_finPeriodo),
                          "in_egr_catalogo_productos.tipo_variabilidad" => $selectPseudoCompra[$pc]->tipo_variabilidad,
                          "in_egr_catalogo_productos.importe_minimo" => $selectPseudoCompra[$pc]->importe_minimo,
                          "in_egr_catalogo_productos.importe_maximo" => $selectPseudoCompra[$pc]->importe_maximo,
                        )
                      );

                    if ($upDateProducto) {
                      $validUpdtProd = true;
                    } else {
                      $validUpdtProd = false;
                    }
                  }
                } else {
                  $validUpdtProd = true;
                }

                if ($selectPseudoCompra[$pc]->servicio != NULL && $selectPseudoCompra[$pc]->servicio != '') {
                  if ($selectPseudoCompra[$pc]->periodicidad == FALSE) {
                    $validUpdtServ = true;
                  } else {
                    $upDateServicio = DB::table('in_egr_catalogo_servicios')
                      ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                      ->where([
                        'in_egr_catalogo_servicios.status' => TRUE,
                        'in_egr_catalogo_servicios.id' => $selectPseudoCompra[$pc]->servicio,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          "in_egr_catalogo_servicios.periodicidad" => $selectPseudoCompra[$pc]->periodicidad,
                          "in_egr_catalogo_servicios.repeticion_periodo" => $selectPseudoCompra[$pc]->repeticion_periodo,
                          "in_egr_catalogo_servicios.tipo_periodo" => $selectPseudoCompra[$pc]->tipo_periodo,
                          "in_egr_catalogo_servicios.fecha_finPeriodo" => $selectPseudoCompra[$pc]->fecha_finPeriodo,
                          "in_egr_catalogo_servicios.tipo_variabilidad" => $selectPseudoCompra[$pc]->tipo_variabilidad,
                          "in_egr_catalogo_servicios.importe_minimo" => $selectPseudoCompra[$pc]->importe_minimo,
                          "in_egr_catalogo_servicios.importe_maximo" => $selectPseudoCompra[$pc]->importe_maximo,
                        )
                      );

                    if ($upDateServicio) {
                      $validUpdtServ = true;
                    } else {
                      $validUpdtServ = false;
                    }
                  }
                } else {
                  $validUpdtServ = true;
                }

                if ($insertDetCompra && $validUpdtProd == true && $validUpdtServ == true) {
                  ++$countSelectPseudoCompra;
                }
              }

              if ($countSelectPseudoCompra == count($selectPseudoCompra)) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La compra con el folio ' . $listaCompra[0]->folio_compra . ' que ha seleccionado fue autorizada'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Hay errores en configuracion de los productos/servicios relacionados a esta compra, para mayor información comuniquese a soporte'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'No se generó orden de pago para esta compra debido a errores internos, para mayor información comuniquese a soporte'
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

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

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
        ->where([
          'eegr_compras.status_autorizacion' => FALSE,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        
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
            ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
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

          //forma de pago
          //https://insideapis.sos-mexico.com.mx/api/listaFormasDePago
          //metodo

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
            "facturaXml" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            //"facturaPdf" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
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
            "total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
            //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab, 
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
          ->where([
            'eegr_compras.status_autorizacion' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

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

          //forma de pago
          //https://insideapis.sos-mexico.com.mx/api/listaFormasDePago
          //metodo

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
            "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
            "recibeFactura" => $vBuy->recibeFactura == TRUE ? true : false,
            "facturaXml" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "urlFactXml" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_xml" : '',
            //"facturaPdf" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "urlFactPdf" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/factura_pdf" : '',
            "evidenciaSAT" => $vBuy->recibeFactura == TRUE ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "urlEvdSAT" => $vBuy->recibeFactura == TRUE ? "https://downloads.sos-mexico.com.mx/compras/" . "COMP-" . $JwtAuth->generarFolio($vBuy->folio_compra) . "/evidencia_sat" : '',
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
            "total_articulos" => count($queryDEtailsTotal),
            //importes
            "importe_total_compra" => "$" . number_format($importe_total_compra, $moneda_decimales, '.', ','),
            //"lugar_recepcion_data" => !empty($recepcion_prov) ? $recepcion_prov : $recepcion_estab, 
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
          //da_te_default_timezone_set($vBuy->zona_horaria);
          $folio_buy = 'COMP-'.$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
          $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);

          $resultXml ="errorXml"; //<div *ngSwitchCase="'validoXml'" class="col-12">
          $dataCFDI_comprobante = array();
          $dataCFDIRelacionados = array();
          $dataCFDIEmisor = array();
          $dataCFDIReceptor = array();
          $dataCFDIComplemento = array();

          $queryCFDIEstructura = ComprasModelo::join("cfdi_estructura AS cfdi", "eegr_compras.id", "=", "cfdi.compra_vinculada")
          ->where('eegr_compras.token_compras',$vBuy->token_compras)->get();
          foreach ($queryCFDIEstructura as $vCFDI) {
            $resultXml = "validoXml";
            $dataCFDI_comprobante[] = array("title" => "Versión","content" => $vCFDI->cfdi_comprobante_version);
            $dataCFDI_comprobante[] = array("title" => 'Serie',"content" => $vCFDI->cfdi_comprobante_serie);
            $dataCFDI_comprobante[] = array("title" => 'Folio',"content" => $vCFDI->cfdi_comprobante_folio);
            $dataCFDI_comprobante[] = array("title" => 'Fecha',"content" => $vCFDI->cfdi_comprobante_fecha);
            $dataCFDI_comprobante[] = array("title" => 'Forma de pago',"content" => $vCFDI->cfdi_comprobante_forma_de_pago);
            $dataCFDI_comprobante[] = array("title" => 'Subtotal',"content" => $vCFDI->cfdi_comprobante_metodo_de_pago);
            $dataCFDI_comprobante[] = array("title" => 'Moneda',"content" => $vCFDI->cfdi_comprobante_subtotal);
            $dataCFDI_comprobante[] = array("title" => 'Tipo de cambio',"content" => $vCFDI->cfdi_comprobante_moneda);
            $dataCFDI_comprobante[] = array("title" => 'Total',"content" => $vCFDI->cfdi_comprobante_tipo_de_cambio);
            $dataCFDI_comprobante[] = array("title" => 'Confirmación',"content" => $vCFDI->cfdi_comprobante_total);
            $dataCFDI_comprobante[] = array("title" => 'Tipo de comprobante',"content" => $vCFDI->cfdi_comprobante_confirmacion);
            $dataCFDI_comprobante[] = array("title" => 'Método de Pago',"content" => $vCFDI->cfdi_comprobante_tipo_de_comprobante);
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

          $dataCFDI_conceptos = array();
          $num_lista = 1;
          $queryConceptosCompras = ComprasModelo::join("eegr_compras_detalle AS detbuy", "eegr_compras.id", "=", "detbuy.numero_compra")
          ->where('eegr_compras.token_compras',$vBuy->token_compras)->get();
          foreach ($queryConceptosCompras as $vDet) {
            $subtotal = (floatval($vDet->precio_unitario) * floatval($vDet->tipo_de_cambio_detalle_compra)) * $vDet->cantidad;
            $importe_total = $subtotal - floatval($vDet->descuento) + floatval($vDet->traslados_total) - floatval($vDet->retenciones_total);

            $totalDetComp = number_format($subtotal,$moneda_decimales,'.', ',');
            $totalDetCompFormat = number_format($subtotal,$moneda_decimales,'.', ',');

            $format_precio_unitario = number_format($vDet->precio_unitario,$moneda_decimales,'.', ',');
            $format_descuento = number_format($vDet->descuento,$moneda_decimales,'.', ',');
            $format_retenciones = number_format($vDet->retenciones_total,$moneda_decimales,'.', ',');
            $format_traslados = number_format($vDet->traslados_total,$moneda_decimales,'.', ',');
            $format_total = number_format($importe_total,$moneda_decimales,'.', ',');

            $row_det = array(
              "num_lista" => $num_lista,
              //"NoIdentificacion" => cChild.getAttribute("NoIdentificacion") ? cChild.getAttribute("NoIdentificacion") : "",
              //"ObjetoImp" => cChild.getAttribute("ObjetoImp"),
              //"ClaveProdServ" => cChild.getAttribute("ClaveProdServ"),
              "Cantidad" => $vDet->cantidad,
              //"ClaveUnidad" => cChild.getAttribute("ClaveUnidad"),
              "Unidad" => $vDet->unidad_medida,
              "Descripcion" => $JwtAuth->desencriptar($vDet->concepto_cfdi),
              "ValorUnitario" => "$$format_precio_unitario",
              "Descuento" => "$$format_descuento",
              "Importe" => "$$totalDetCompFormat",
              "TotalRetenciones" => "$$format_retenciones",
              "TotalTraslados" => "$$format_traslados",
              "Subtotal" => "$$format_total",
              //impuestos
              //"Impuestos" => list_impuestos,
              //retenciones
              "articulo_retenciones_modal" => false,
              //"retenciones" => list_retenciones,
              //"expandedRowsRetenciones" => expandRowsRetenciones,
              "retenciones_llenadas" => false,
              //traslados
              "articulo_traslados_modal" => false,
              //"traslados" => list_traslados,
              //"expandedRowsTraslados" => expandRowsTraslados,
              "traslados_llenados" => false,
              //iva
              "articulo_homologado_iva" => "",
              //Articulo para guardar
              "articulo_guardar_tkn" => "",
              "articulo_guardar_identificador" => "",
              //Articulo a homologar generales
              "articulo_homologado_comprobacion" => true,
              "articulo_homologado_ventana_registro" => false,
              "articulo_homologado_registro_tipo" => false,
              "articulo_homologado_token" => "",
              "articulo_homologado_view" => false,
              "articulo_homologado_nombre" => "",
              "articulo_homologado_logotipo" => "",
              "articulo_homologado_clasificacion" => "",
              "articulo_homologado_identificador" => "",
              //Articulo a homologar series
              "articulo_homologado_serie_bool" => false,
              "articulo_homologado_serie_view" => false,
              "articulo_homologado_serie_token" => "",
              "articulo_homologado_serie_numero" => "",
              //Articulo a homologar lotes
              "articulo_homologado_lote_bool" => false,
              "articulo_homologado_lote_view" => false,
              "articulo_homologado_lote_token" => "",
              "articulo_homologado_lote_numero" => "",
              //Articulo a homologar pedimentos
              "articulo_homologado_pedimento_bool" => false,
              "articulo_homologado_pedimento_view" => false,
              "articulo_homologado_pedimento_token" => "",
              "articulo_homologado_pedimento_numero" => "",
              //Articulo a homologar uso
              "articulo_homologado_view_uso" => false,
              "articulo_homologado_uso" => $vDet->destino,
              //Articulo a homologar uso
              "articulo_homologado_view_activos" => false,
              "articulo_homologado_activoFijo" => $vDet->activo_fijo,
              "articulo_homologado_activoIntangible" => $vDet->activo_intangible,
              //prorrateos
              "articulo_homologado_prorratea" => false,
              //gastos relacionados
              "articulo_homologado_gastos_rel" => [],
              //periodicidad
              "articulo_homologado_periodicidad_view" => false,
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
              "articulo_homologado_periodicidad_reg" => false,
              //desglose
              "activa_desglose" => false,
            );
            $dataCFDI_conceptos[] = $row_det; 
            ++$num_lista;
          }

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "fecha_altaCompra" => date('d-m-Y H:i:s', $vBuy->fecha_altaCompra),
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vBuy->fecha_contabilizacion),
            "fecha_vencimiento" => gmdate('Y-m-d H:i:s', $vBuy->fecha_vencimiento),
            "folio_compras" => $folio_buy,
            "facturaXml" => !empty($vBuy->facturaXml) ? $JwtAuth->desencriptar($vBuy->facturaXml) : '',
            "facturaPdf" => !empty($vBuy->facturaPdf) ? $JwtAuth->desencriptar($vBuy->facturaPdf) : '',
            "evidenciaSAT" => !empty($vBuy->evidenciaSAT) ? $JwtAuth->desencriptar($vBuy->evidenciaSAT) : '',
            "resultXml" => $resultXml,

            "dataCFDI_comprobante" => $dataCFDI_comprobante,
            "dataCFDIRelacionados" => $dataCFDIRelacionados,
            "dataCFDIEmisor" => $dataCFDIEmisor,
            "dataCFDIReceptor" => $dataCFDIReceptor,
            "dataCFDIComplemento" => $dataCFDIComplemento,
            "dataCFDI_conceptos" => $dataCFDI_conceptos,
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
              $fecha_recep = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
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

  /*public function detalleComprasAutorizadas(Request $request){
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
          $moneda_decimales = 0;
          $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaMonedas');
          if ($response->successful()) {
            $datos = $response->json();
            $cantidadRegistros = is_array($datos) ? count($datos) : 0;
            $indice = array_search($vBuy->moneda, array_column($datos["monedas"], "code"));
            $moneda_decimales = $datos["monedas"][$indice]["decimales"];
            //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
          }  

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

          //facturas
          $factura_xml = array();
          $nombre_pdf = '';
          $factura_pdf = '';
          if ($vBuy->facturaXml != NULL && $vBuy->facturaPdf != NULL) {
            $ruta = $vBuy->fecha_sistemaCompras."-$folio_buy";
            //factura Pdf  
            $nombre_pdf = $JwtAuth->desencriptar($vBuy->facturaPdf);
            $factura_pdf = '<iframe src="'.$JwtAuth->encriptaBase64Pdf(Storage::path('public/root/' .$vBuy->root_tkn .'/0002-cpp/compras/compras/'.$ruta.'/'.$JwtAuth->desencriptar($vBuy->facturaPdf))).'" width="100%" height="100%"></iframe>';

            //factura xml
            $arrayErroresComprobante = array();
            $arrayErroresEmisor = array();
            $arrayErroresReceptor = array();
            $arrayErroresCfdiRelacionados = array();
            $arrayListaConceptos = array();
            $arrayListaImpuestosConceptos = array();
            $arrayErroresConceptos = array();
            $arrayImpuestosRetenciones = array();
            $arrayImpuestosTraslados = array();
            $arrayErroresImpuestos = array();
            $arrayErroresComplemento = array();

            $verifiedCfdiComprobante = '';
            $verifiedCfdiEmisor = '';
            $verifiedCfdiReceptor = '';
            $verifiedCfdiRelacionados = '';
            $verifiedCfdiRelacionadostipoRelacion = '';
            $verifiedCfdiRelacionadosuuid = '';
            $verifiedCfdiConceptos = '';
            $verifiedCfdiImpuestos = '';
            $txttotalImpuestosRetenidos = '';
            $txttotalImpuestosTrasladados = '';
            $verifiedCfdiComplemento = '';

            $dataEmpresa = DB::table("sos_personas AS people")
            ->join("main_empresas AS emp","people.id","emp.persona")
            ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
            ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" =>$usuario->user_token])
            ->value("people.rfc");

            $rfc_company = !empty($dataEmpresa) ? strtolower($JwtAuth->desencriptar($dataEmpresa)) : '';

            $xmlObject = simplexml_load_file(Storage::path('public/root/'.$vBuy->root_tkn.'/0002-cpp/compras/compras/'.$ruta.'/'.$JwtAuth->desencriptar($vBuy->facturaXml)));
            $nombre_xml = $JwtAuth->desencriptar($vBuy->facturaXml);
            $ns = $xmlObject->getNamespaces(true);
            $cfdi = $ns['cfdi'];
            $xsi = $ns['xsi'];
            $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

            $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
            $xmlObject->registerXPathNamespace('t', $ns['tfd']);

            //comprabante
            $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
            $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
            $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
            $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
            $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

            $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
            $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
            $selectFpago = DB::select("SELECT token_formapago FROM 	teci_forma_pago WHERE clave = ?", [$formaPago]);
            $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
            $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
            $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
            $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
            $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

            if ($comprobante[0]["TipoCambio"] != NULL) {
              $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
            } else {
              $tipoCambio = 'no especificado';
            }

            $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

            if ($comprobante[0]["Confirmacion"] != NULL) {
              $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
            } else {
              $confirmacion = 'no especificado';
            }

            $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
            $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
            $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
            $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

            if (
              isset($cfdi) && !empty($cfdi) && ($cfdi == "http://www.sat.gob.mx/cfd/3" ||  $cfdi == "http://www.sat.gob.mx/cfd/4") &&
              isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" && isset($datSchama) &&
              !empty($datSchama) && ($datSchama == "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd" ||
                $datSchama == "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd") &&
              isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0" || $jsonversion == "3.3" || $jsonversion == "4.0") && isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) &&
              strlen($Folio) <= 40 && isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
              isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 && isset($noCertificado) && !empty($noCertificado) &&
              isset($certificado) && !empty($certificado) && isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
              !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
              !empty($TipoDeComprobante) && $TipoDeComprobante == 'I' && isset($MetodoPago) && !empty($MetodoPago) &&
              strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
            ) {

              if ($Moneda != 'MXN' && $Moneda != 'XXX') {
                if (
                  isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
                  $comprobante[0]["TipoCambio"] != NULL
                ) {
                  $verifiedCfdiComprobante = 'true';
                } else {
                  $arrayError = array(
                    "nodo" => "Comprobante",
                    "atributo_nodohijo" => "TipoCambio",
                    "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TipoCambio"
                  );
                  $arrayErroresComprobante[] = $arrayError;
                  $verifiedCfdiComprobante = 'false';
                }
              } else {
                $verifiedCfdiComprobante = 'true';
              }

              if ($comprobante[0]["Confirmacion"]) {
                if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
                  $verifiedCfdiComprobante = 'true';
                } else {
                  $arrayError = array(
                    "nodo" => "Comprobante",
                    "atributo_nodohijo" => "Confirmacion",
                    "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                    "correccion" => "agregar o verificar atributo Confirmacion"
                  );
                  $arrayErroresComprobante[] = $arrayError;
                  $verifiedCfdiComprobante = 'false';
                }
              } else {
                $verifiedCfdiComprobante = 'true';
              }
            } else {
              $verifiedCfdiComprobante = 'false';
              if (!isset($cfdi) || empty($cfdi) || ($cfdi != "http://www.sat.gob.mx/cfd/3" && $cfdi != "http://www.sat.gob.mx/cfd/4")) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "xmlns:cfdi",
                  "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "http://www.sat.gob.mx/cfd/3"',
                  "correccion" => "agregar o verificar atributo xmlns:cfdi"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "xmlns:xsi",
                  "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
                  "correccion" => "agregar o verificar atributo xmlns:xsi"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (
                !isset($datSchama) || empty($datSchama) ||
                ($datSchama != "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd" &&
                  $datSchama != "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd")
              ) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "xsi:schemaLocation",
                  "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd"',
                  "correccion" => "agregar o verificar atributo xsi:schemaLocation"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (
                !isset($version) || empty($version) ||
                ($version != "3.3" && $version != "4.0" && $jsonversion != "3.3" && $jsonversion != "4")
              ) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Version",
                  "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
                  "correccion" => "agregar o verificar atributo Version"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Serie",
                  "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
                  "correccion" => "agregar o verificar atributo Serie"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Folio",
                  "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
                  "correccion" => "agregar o verificar atributo Folio"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Fecha",
                  "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
                  "correccion" => "agregar o verificar atributo Fecha"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($Sello) || empty($Sello)) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Sello",
                  "mensaje" => "el atributo Sello no existe,esta vacio",
                  "correccion" => "agregar o verificar atributo Sello"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "FormaPago",
                  "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
                  "correccion" => "agregar o verificar atributo FormaPago"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($noCertificado) || empty($noCertificado)) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "NoCertificado",
                  "mensaje" => "el atributo NoCertificado no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo NoCertificado"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($certificado) || empty($certificado)) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Certificado",
                  "mensaje" => "el atributo Certificado no existeo o esta vacio",
                  "correccion" => "agregar o verificar atributo Certificado"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($SubTotal) || empty($SubTotal)) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "SubTotal",
                  "mensaje" => "el atributo SubTotal no existe,esta vacio",
                  "correccion" => "agregar o verificar atributo SubTotal"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Moneda",
                  "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
                  "correccion" => "agregar o verificar atributo Moneda"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($Total) || empty($Total)) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "Total",
                  "mensaje" => "el atributo Total no existe,esta vacio",
                  "correccion" => "agregar o verificar atributo Total"
                );
                $arrayErroresComprobante[] = $arrayError;
                $mensajeError = 'nodo Total incorrecto';
              }
              if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'I') {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "TipoComprobante",
                  "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
                  "correccion" => "agregar o verificar atributo TipoComprobante"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "MetodoPago",
                  "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
                  "correccion" => "agregar o verificar atributo MetodoPago"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
              if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
                $arrayError = array(
                  "nodo" => "Comprobante",
                  "atributo_nodohijo" => "LugarExpedicion",
                  "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
                  "correccion" => "agregar o verificar atributo LugarExpedicion"
                );
                $arrayErroresComprobante[] = $arrayError;
              }
            }

            //nodo CfdiRelacionados
            $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
            if ($CfdiRelacionados) {
              if (!empty($CfdiRelacionados)) {
                $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
                $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
                $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
                if (
                  isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
                  isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
                  isset($uuid) && !empty($uuid)
                ) {
                  $verifiedCfdiRelacionados = 'true';
                  $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
                  $verifiedCfdiRelacionadosuuid = $uuid;
                } else {
                  $verifiedCfdiRelacionados = 'false';
                  if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                    $arrayError = array(
                      "nodo" => "CfdiRelacionados",
                      "atributo_nodohijo" => "TipoRelacion",
                      "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                      "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                    );
                    $arrayErroresCfdiRelacionados[] = $arrayError;
                  }
                  if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                    $arrayError = array(
                      "nodo" => "CfdiRelacionado",
                      "atributo_nodohijo" => "---",
                      "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                      "correccion" => "---"
                    );
                    $arrayErroresCfdiRelacionados[] = $arrayError;
                  }
                  if (!isset($uuid) || empty($uuid)) {
                    $arrayError = array(
                      "nodo" => "CfdiRelacionado",
                      "atributo_nodohijo" => "UUID",
                      "mensaje" => "el nodo UUID no existe o viene vacio",
                      "correccion" => "---"
                    );
                    $arrayErroresCfdiRelacionados[] = $arrayError;
                  }
                }
              } else {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
                $verifiedCfdiRelacionados = 'false';
              }
            } else {
              $verifiedCfdiRelacionados = 'true';
            }

            //nodo emisor
            $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
            $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
            $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
            $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];

            if (isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 && strlen($RfcEmi) <= 13 && $RfcEmi == $proveedor_rfc && isset($nombre) && !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3) {
              $verifiedCfdiEmisor = 'true';
            } else {
              $verifiedCfdiEmisor = 'false';
              if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
                $arrayError = array(
                  "nodo" => "Emisor",
                  "atributo_nodohijo" => "Rfc",
                  "mensaje" => "el atributo Rfc no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo Rfc"
                );
                $arrayErroresEmisor[] = $arrayError;
              }
              if ($RfcEmi != $proveedor_rfc) {
                $arrayError = array(
                  "nodo" => "Emisor",
                  "atributo_nodohijo" => "Rfc",
                  "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
                  "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
                );
                $arrayErroresEmisor[] = $arrayError;
              }
              if (!isset($nombre) || empty($nombre)) {
                $arrayError = array(
                  "nodo" => "Emisor",
                  "atributo_nodohijo" => "Nombre",
                  "mensaje" => "el atributo Nombre no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo Nombre"
                );
                $arrayErroresEmisor[] = $arrayError;
              }
              if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
                $arrayError = array(
                  "nodo" => "Emisor",
                  "atributo_nodohijo" => "RegimenFiscal",
                  "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
                  "correccion" => "agregar o verificar atributo RegimenFiscal"
                );
                $arrayErroresEmisor[] = $arrayError;
              }
            }

            //nodo receptor
            $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
            $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
            $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
            $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);
            if (isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) && $RfcRec == $rfc_company && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3) {
              $verifiedCfdiReceptor = 'true';
            } else {
              $verifiedCfdiReceptor = 'false';
              if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
                $arrayError = array(
                  "nodo" => "Receptor",
                  "atributo_nodohijo" => "Rfc",
                  "mensaje" => "el atributo Rfc no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo Rfc"
                );
                $arrayErroresReceptor[] = $arrayError;
              }
              if ($RfcRec != $rfc_company) {
                $arrayError = array(
                  "nodo" => "Receptor",
                  "atributo_nodohijo" => "Rfc",
                  "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
                  "correccion" => "el rfc de su empresa debe ser " . $rfc_company
                );
                $arrayErroresReceptor[] = $arrayError;
              }
              if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
                $arrayError = array(
                  "nodo" => "Receptor",
                  "atributo_nodohijo" => "UsoCFDI",
                  "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
                  "correccion" => "agregar o verificar atributo UsoCFDI"
                );
                $arrayErroresReceptor[] = $arrayError;
              }
            }

            //nodo conceptos
            $countConceptos = 0;
            $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
            $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
            if (isset($conceptos) && !empty($conceptos)) {
              for ($i = 0; $i < count($forConcepto); $i++) {
                $verifiedCfdiConceptosConcepto = '';
                $verifiedCfdiConceptosDescuento = '';
                $verifiedCfdiConceptosImpuestos = '';
                $verifiedCfdiConceptosImpuestosRetenciones = '';
                $verifiedCfdiConceptosImpuestosTraslados = '';

                $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
                $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
                $resultnoIdentificacion = '';
                $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
                $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
                $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
                $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
                $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
                $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
                $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
                $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

                if (isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8 && isset($cantidad) && !empty($cantidad) && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3 && 
                  isset($unidad) && !empty($unidad) && isset($descripcion) && !empty($descripcion) && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6 && isset($importe) && 
                  !empty($importe) && strlen($explodeImporte[1]) <= 6) {
                  if (isset($noIdentificacion)) {
                    if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                      $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                      $verifiedCfdiConceptosConcepto = 'true';
                    } else {
                      $verifiedCfdiConceptosConcepto = 'false';
                      $arrayError = array(
                        "nodo" => "Conceptos",
                        "atributo_nodohijo" => "NoIdentificacion",
                        "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                        "correccion" => "agregar o verificar nodo NoIdentificacion"
                      );
                      $arrayErroresConceptos[] = $arrayError;
                    }
                  } else {
                    $verifiedCfdiConceptosConcepto = 'true';
                  }

                  if (isset($forConcepto[$i]["Descuento"])) {
                    $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                    if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                      $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                    } else {
                      $verifiedCfdiConceptosDescuento = 'false';
                      $arrayError = array(
                        "nodo" => "Conceptos",
                        "atributo_nodohijo" => "Descuento",
                        "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                        "correccion" => "agregar o verificar nodo Descuento"
                      );
                      $arrayErroresConceptos[] = $arrayError;
                    }
                  } else {
                    $verifiedCfdiConceptosDescuento = 'true';
                    $resultDescuento = '---';
                  }

                  $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida WHERE sat_clave = ?", [$claveUnidad]);

                  if ($verifiedCfdiConceptosConcepto == 'true') {
                    //nodo impuestos
                    $arrayImpuestosCncRetenciones = array();
                    $arrayImpuestosCncTraslados = array();
                    $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                    if ($impuestos) {
                      if (isset($impuestos) && !empty($impuestos)) {
                        $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                        if ($retenciones) {
                          if (!empty($retenciones)) {
                            $countRetencion = 0;
                            $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                            if (isset($retencion) && !empty($retencion)) {
                              foreach ($retencion as $forRetencion) {
                                $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                                $explodeBase = explode('.', $base);
                                $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                                $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                                $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                                $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                                $explodeImporte = explode('.', $importeImp);

                                if (
                                  isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                                  && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                                  && isset($tipoFactor) && !empty($tipoFactor)
                                  && isset($TasaOCuota) && !empty($TasaOCuota)
                                  && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                                ) {
                                  ++$countRetencion;
                                  $arrayRetencionFor = array(
                                    "Base" => $base,
                                    "Impuesto" => $impuesto,
                                    "TipoFactor" => $tipoFactor,
                                    "TasaOCuota" => $TasaOCuota,
                                    "Importe" => $importeImp,
                                  );
                                  $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                                } else {
                                  if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                    $arrayError = array(
                                      "nodo" => "Retencion",
                                      "atributo_nodohijo" => "Base",
                                      "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                      "correccion" => "agregar o verificar nodo Base"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                    $arrayError = array(
                                      "nodo" => "Retencion",
                                      "atributo_nodohijo" => "Impuesto",
                                      "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                      "correccion" => "agregar o verificar nodo Impuesto"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($tipoFactor) || empty($tipoFactor)) {
                                    $arrayError = array(
                                      "nodo" => "Retencion",
                                      "atributo_nodohijo" => "TipoFactor",
                                      "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                      "correccion" => "agregar o verificar nodo TipoFactor"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                    $arrayError = array(
                                      "nodo" => "Retencion",
                                      "atributo_nodohijo" => "TasaOCuota",
                                      "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                      "correccion" => "agregar o verificar nodo TasaOCuota"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                    $arrayError = array(
                                      "nodo" => "Retencion",
                                      "atributo_nodohijo" => "Importe",
                                      "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                      "correccion" => "agregar o verificar nodo Importe"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                }
                              }
                              if ($countRetencion == count($retencion)) {
                                $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                              }
                            } else {
                              $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                              $arrayError = array(
                                "nodo" => "Conceptos",
                                "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                                "mensaje" => "el nodo Retencion no existe o esta vacio",
                                "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                              );
                              $arrayErroresConceptos[] = $arrayError;
                            }
                          } else {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                            $arrayError = array(
                              "nodo" => "Conceptos",
                              "atributo_nodohijo" => "Impuestos Retenciones",
                              "mensaje" => "el nodo Retenciones no existe o esta vacio",
                              "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                            );
                            $arrayErroresConceptos[] = $arrayError;
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                        }
                        $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                        $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                        if ($traslados) {
                          if (!empty($traslados)) {
                            $countTraslado = 0;
                            $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                            if (isset($traslado) && !empty($traslado)) {
                              foreach ($traslado as $forTtraslado) {
                                $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                                $explodeBase = explode('.', $base);
                                $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                                $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                                $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                                $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                                $explodeImporte = explode('.', $importeImp);
                                if (
                                  isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                                  && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                                  && isset($tipoFactor) && !empty($tipoFactor)
                                  && isset($TasaOCuota) && !empty($TasaOCuota)
                                  && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                                ) {
                                  ++$countTraslado;
                                  $arrayTrasladoFor = array(
                                    "Base" => $base,
                                    "Impuesto" => $impuesto,
                                    "TipoFactor" => $tipoFactor,
                                    "TasaOCuota" => $TasaOCuota,
                                    "Importe" => $importeImp,
                                  );
                                  $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                                } else {
                                  if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                    $arrayError = array(
                                      "nodo" => "Traslado",
                                      "atributo_nodohijo" => "Base",
                                      "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                      "correccion" => "agregar o verificar nodo Base"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                    $arrayError = array(
                                      "nodo" => "Traslado",
                                      "atributo_nodohijo" => "Impuesto",
                                      "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                      "correccion" => "agregar o verificar nodo Impuesto"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($tipoFactor) || empty($tipoFactor)) {
                                    $arrayError = array(
                                      "nodo" => "Traslado",
                                      "atributo_nodohijo" => "TipoFactor",
                                      "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                      "correccion" => "agregar o verificar nodo TipoFactor"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                    $arrayError = array(
                                      "nodo" => "Traslado",
                                      "atributo_nodohijo" => "TasaOCuota",
                                      "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                      "correccion" => "agregar o verificar nodo TasaOCuota"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                  if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                    $arrayError = array(
                                      "nodo" => "Traslado",
                                      "atributo_nodohijo" => "Importe",
                                      "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                      "correccion" => "agregar o verificar nodo Importe"
                                    );
                                    $arrayErroresConceptos[] = $arrayError;
                                  }
                                }
                              }
                              if ($countTraslado == count($traslado)) {
                                $verifiedCfdiConceptosImpuestosTraslados = 'true';
                              }
                            } else {
                              $verifiedCfdiConceptosImpuestosTraslados = 'false';
                              $arrayError = array(
                                "nodo" => "Conceptos",
                                "atributo_nodohijo" => "Impuestos Traslados Traslado",
                                "mensaje" => "el nodo Traslado no existe o esta vacio",
                                "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                              );
                              $arrayErroresConceptos[] = $arrayError;
                            }
                          } else {
                            $verifiedCfdiConceptosImpuestosTraslados = 'false';
                            $arrayError = array(
                              "nodo" => "Conceptos",
                              "atributo_nodohijo" => "Impuestos Traslados",
                              "mensaje" => "el nodo Traslados no existe o esta vacio",
                              "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                            );
                            $arrayErroresConceptos[] = $arrayError;
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'true';
                        }
                        $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                        if (
                          $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                          $verifiedCfdiConceptosImpuestosTraslados == 'true'
                        ) {
                          $verifiedCfdiConceptosImpuestos = 'true';
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestos = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos",
                          "mensaje" => "el nodo Impuestos no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                      $verifiedCfdiConceptosImpuestos = 'true';
                      $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                      $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    }
                  }

                  if ($verifiedCfdiConceptosConcepto == 'true' && $verifiedCfdiConceptosDescuento == 'true' && $verifiedCfdiConceptosImpuestos == 'true' && $verifiedCfdiConceptosImpuestosRetenciones == 'true' && $verifiedCfdiConceptosImpuestosTraslados == 'true') {
                    ++$countConceptos;
                    $arrayforeachConcept = array(
                      "claveProdServ" => $claveProdServ,
                      "noIdentificacion" => $resultnoIdentificacion,
                      "cantidad" => $cantidad,
                      "claveUnidad" => $claveUnidad,
                      "unidad" => $unidad,
                      "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                      "descripcion" => $descripcion,
                      "valorUnitario" => $valorUnitario,
                      "importe" => $importe,
                      "descuento" => $resultDescuento,
                      "impuestos" => $arrayListaImpuestosConceptos,
                    );
                    $arrayListaConceptos[] = $arrayforeachConcept;
                  }
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "ClaveProdServ",
                      "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                      "correccion" => "agregar o verificar atributo ClaveProdServ"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                  if (!isset($cantidad) || empty($cantidad)) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Cantidad",
                      "mensaje" => "el atributo Cantidad no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Cantidad"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                  if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "ClaveUnidad",
                      "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                      "correccion" => "agregar o verificar nodo ClaveUnidad"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                  if (!isset($unidad) || empty($unidad)) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Unidad",
                      "mensaje" => "el atributo Unidad no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Unidad"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                  if (!isset($descripcion) || empty($descripcion)) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Descripcion",
                      "mensaje" => "el atributo Descripcion no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Descripcion"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                  if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "ValorUnitario",
                      "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                      "correccion" => "agregar o verificar nodo ValorUnitario"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                  if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Importe",
                      "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                      "correccion" => "agregar o verificar nodo Importe"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                }
              }

              if ($countConceptos == count($forConcepto)) {
                $verifiedCfdiConceptos = 'true';
              }
            } else {
              $verifiedCfdiConceptos = 'false';
              $arrayError = array(
                "nodo" => "Conceptos",
                "atributo_nodohijo" => "---",
                "mensaje" => "el nodo Conceptos no existe o esta vacio",
                "correccion" => "agregar o verificar nodo Conceptos"
              );
              $arrayErroresConceptos[] = $arrayError;
            }

            //nodo impuestos
            $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
            if ($impuestosCfdi && count($impuestosCfdi) > 0) {
              if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
                $verifiedCfdiImpuestosRetenciones = '';
                $verifiedCfdiImpuestosRetencionesRetencion = '';
                $verifiedCfdiImpuestosTraslados = '';
                $verifiedCfdiImpuestosTrasladosTraslado = '';
                $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
                if ($retenciones) {
                  $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
                  if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                    $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                    $countRetenidoImp = 0;
                    $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                    if (isset($retencion) && !empty($retencion)) {
                      foreach ($retencion as $forRetencion) {
                        if (isset($forRetencion["Base"])) {
                          $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                        } else {
                          $base = '0.00';
                        }
                        $explodeBase = explode('.', $base);

                        if (isset($forRetencion["Impuesto"])) {
                          $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                        } else {
                          $impuesto = 'xxx';
                        }

                        if (isset($forRetencion["TipoFactor"])) {
                          $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                        } else {
                          $tipoFactor = 'xxxx';
                        }

                        if (isset($forRetencion["TasaOCuota"])) {
                          $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                        } else {
                          $TasaOCuota = '0.00';
                        }

                        if (isset($forRetencion["Importe"])) {
                          $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                        } else {
                          $importe = '0.00';
                        }
                        $explodeImporte = explode('.', $importe);

                        if (
                          isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                          && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                          && isset($tipoFactor) && !empty($tipoFactor)
                          && isset($TasaOCuota) && !empty($TasaOCuota)
                          && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                        ) {
                          ++$countRetenidoImp;
                          $arrayTrasladoFor = array(
                            "Impuesto" => $impuesto,
                            "TipoFactor" => $tipoFactor,
                            "TasaOCuota" => $TasaOCuota,
                            "Importe" => $importe,
                          );
                          $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                        } else {
                          if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                            $arrayError = array(
                              "nodo" => "Retencion",
                              "atributo/nodohijo" => "Base",
                              "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                              "correccion" => "agregar o verificar nodo Base"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                            $arrayError = array(
                              "nodo" => "Retencion",
                              "atributo/nodohijo" => "Impuesto",
                              "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                              "correccion" => "agregar o verificar nodo Impuesto"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($tipoFactor) || empty($tipoFactor)) {
                            $arrayError = array(
                              "nodo" => "Retencion",
                              "atributo/nodohijo" => "TipoFactor",
                              "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                              "correccion" => "agregar o verificar nodo TipoFactor"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                            $arrayError = array(
                              "nodo" => "Retencion",
                              "atributo/nodohijo" => "TasaOCuota",
                              "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                              "correccion" => "agregar o verificar nodo TasaOCuota"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                            $arrayError = array(
                              "nodo" => "Retencion",
                              "atributo/nodohijo" => "Importe",
                              "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                              "correccion" => "agregar o verificar nodo Importe"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                        }
                      }
                      if ($countRetenidoImp == count($retencion)) {
                        $verifiedCfdiImpuestosRetenciones = 'true';
                      }
                    } else {
                      $verifiedCfdiImpuestosRetenciones = 'false';
                      $arrayError = array(
                        "nodo" => "Impuestos",
                        "atributo/nodohijo" => "Retenciones Retencion",
                        "mensaje" => "el nodo Retencion no existe o esta vacio",
                        "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                      );
                      $arrayErroresImpuestos[] = $arrayError;
                    }
                  } else {
                    $verifiedCfdiImpuestosRetenciones = 'false';
                    if (empty($retenciones)) {
                      $arrayError = array(
                        "nodo" => "Impuestos",
                        "atributo/nodohijo" => "Retenciones",
                        "mensaje" => "el nodo Retenciones no existe o esta vacio",
                        "correccion" => "agregar o verificar nodo Retenciones"
                      );
                      $arrayErroresImpuestos[] = $arrayError;
                    }
                    if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                      $arrayError = array(
                        "nodo" => "Impuestos",
                        "atributo/nodohijo" => "TotalImpuestosRetenidos",
                        "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                        "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                      );
                      $arrayErroresImpuestos[] = $arrayError;
                    }
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'true';
                }
                $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

                $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
                if ($traslados) {
                  $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
                  if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                    $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                    $countTrasladoImp = 0;
                    $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                    if (isset($traslado) && !empty($traslado)) {
                      foreach ($traslado as $forTtraslado) {
                        if (isset($forTtraslado["Base"])) {
                          $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                        } else {
                          $base = '0.00';
                        }
                        $explodeBase = explode('.', $base);
                        if (isset($forTtraslado["Impuesto"])) {
                          $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                        } else {
                          $impuesto = 'xxx';
                        }

                        if (isset($forTtraslado["TipoFactor"])) {
                          $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                        } else {
                          $tipoFactor = 'xxxx';
                        }

                        if (isset($forTtraslado["TasaOCuota"])) {
                          $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                        } else {
                          $TasaOCuota = '0.00';
                        }

                        if (isset($forTtraslado["Importe"])) {
                          $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                        } else {
                          $importe = '0.00';
                        }
                        $explodeImporte = explode('.', $importe);

                        if (
                          isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                          isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                          && isset($tipoFactor) && !empty($tipoFactor)
                          && isset($TasaOCuota) && !empty($TasaOCuota)
                          && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                        ) {
                          ++$countTrasladoImp;
                          $arrayTrasladoFor = array(
                            "Base" => $base,
                            "Impuesto" => $impuesto,
                            "TipoFactor" => $tipoFactor,
                            "TasaOCuota" => $TasaOCuota,
                            "Importe" => $importe,
                          );
                          $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                        } else {
                          if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                            $arrayError = array(
                              "nodo" => "Traslado",
                              "atributo/nodohijo" => "Base",
                              "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                              "correccion" => "agregar o verificar nodo Base"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                            $arrayError = array(
                              "nodo" => "Traslado",
                              "atributo/nodohijo" => "Impuesto",
                              "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                              "correccion" => "agregar o verificar nodo Impuesto"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($tipoFactor) || empty($tipoFactor)) {
                            $arrayError = array(
                              "nodo" => "Traslado",
                              "atributo/nodohijo" => "TipoFactor",
                              "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                              "correccion" => "agregar o verificar nodo TipoFactor"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                            $arrayError = array(
                              "nodo" => "Traslado",
                              "atributo/nodohijo" => "TasaOCuota",
                              "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                              "correccion" => "agregar o verificar nodo TasaOCuota"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                          if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                            $arrayError = array(
                              "nodo" => "Traslado",
                              "atributo/nodohijo" => "Importe",
                              "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                              "correccion" => "agregar o verificar nodo Importe"
                            );
                            $arrayErroresImpuestos[] = $arrayError;
                          }
                        }
                      }

                      if ($countTrasladoImp == count($traslado)) {
                        $verifiedCfdiImpuestosTraslados = 'true';
                      }
                    } else {
                      $verifiedCfdiImpuestosTraslados = 'false';
                      $arrayError = array(
                        "nodo" => "Impuestos",
                        "atributo/nodohijo" => "Traslados Traslado",
                        "mensaje" => "el nodo Traslado no existe o esta vacio",
                        "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                      );
                      $arrayErroresImpuestos[] = $arrayError;
                    }
                  } else {
                    $verifiedCfdiImpuestosTraslados = 'false';
                    if (empty($traslados)) {
                      $arrayError = array(
                        "nodo" => "Impuestos",
                        "atributo/nodohijo" => "Traslados",
                        "mensaje" => "el nodo Traslados no existe o esta vacio",
                        "correccion" => "agregar o verificar nodo Traslados"
                      );
                      $arrayErroresImpuestos[] = $arrayError;
                    }
                    if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                      $arrayError = array(
                        "nodo" => "Impuestos",
                        "atributo/nodohijo" => "TotalImpuestosTrasladados",
                        "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                        "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                      );
                      $arrayErroresImpuestos[] = $arrayError;
                    }
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'true';
                }
                $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

                if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
                  $verifiedCfdiImpuestos = 'true';
                }
              } else {
                $verifiedCfdiImpuestos = 'false';
                $arrayError = array(
                  "nodo" => "Impuestos",
                  "atributo/nodohijo" => "---",
                  "mensaje" => "el nodo Impuestos no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Impuestos"
                );
                $arrayErroresImpuestos[] = $arrayError;
              }
            } else {
              $verifiedCfdiImpuestos = 'true';
            }

            //nodo complemento
            $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
            $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
            $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
            $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
            $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
            $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
            $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

            if (isset($complemento) && !empty($complemento)) {
              if (isset($uuidComplemento) && !empty($uuidComplemento) && isset($fechaTimbrado) && !empty($fechaTimbrado) && isset($RfcProvCertif) && !empty($RfcProvCertif) && 
                isset($SelloCFD) && !empty($SelloCFD) && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT) && isset($SelloSAT) && !empty($SelloSAT)) {
                $verifiedCfdiComplemento = 'true';
              } else {
                $verifiedCfdiComplemento = 'false';
                if (!isset($uuidComplemento) || empty($uuidComplemento)) {
                  $arrayError = array(
                    "nodo" => "Complemento",
                    "atributo_nodohijo" => "UUID",
                    "mensaje" => "el atributo UUID no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo UUID"
                  );
                  $arrayErroresComplemento[] = $arrayError;
                }
                if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
                  $arrayError = array(
                    "nodo" => "Complemento",
                    "atributo_nodohijo" => "FechaTimbrado",
                    "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo FechaTimbrado"
                  );
                  $arrayErroresComplemento[] = $arrayError;
                }
                if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
                  $arrayError = array(
                    "nodo" => "Complemento",
                    "atributo_nodohijo" => "RfcProvCertif",
                    "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo RfcProvCertif"
                  );
                  $arrayErroresComplemento[] = $arrayError;
                }
                if (!isset($SelloCFD) || empty($SelloCFD)) {
                  $arrayError = array(
                    "nodo" => "Complemento",
                    "atributo_nodohijo" => "SelloCFD",
                    "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo SelloCFD"
                  );
                  $arrayErroresComplemento[] = $arrayError;
                  $mensajeError = 'nodo UUID SelloCFD incorrecto';
                }
                if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
                  $arrayError = array(
                    "nodo" => "Complemento",
                    "atributo_nodohijo" => "NoCertificadoSAT",
                    "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo NoCertificadoSAT"
                  );
                  $arrayErroresComplemento[] = $arrayError;
                  $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
                }
                if (!isset($SelloSAT) || empty($SelloSAT)) {
                  $arrayError = array(
                    "nodo" => "Complemento",
                    "atributo_nodohijo" => "SelloSAT",
                    "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo SelloSAT"
                  );
                  $arrayErroresComplemento[] = $arrayError;
                  $mensajeError = 'nodo UUID SelloSAT incorrecto';
                }
              }
            } else {
              $verifiedCfdiComplemento = 'false';
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "TimbreFiscalDigital",
                "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
                "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
              );
              $arrayErroresComplemento[] = $arrayError;
            }

            if (
              $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
              $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
              $verifiedCfdiComplemento == 'true'
            ) {
              $factura_xml = array(
                'nombre_xml' => $nombre_xml,
                'resultXml' => 'validoXml',
                //informacion del xml
                //comprobante
                'version' => $version,
                'serie' => $serie,
                'Folio' => $Folio,
                'Fecha' => $Fecha,
                'Sello' => $Sello,
                'formaPago' => $formaPago,
                'tokenformaPago' => $selectFpago[0]->token_formapago,
                'noCertificado' => $noCertificado,
                'certificado' => $certificado,
                'SubTotal' => $SubTotal,
                'Moneda' => $Moneda,
                'tokenMoneda' => $selectMoneda[0]->token_monedas,
                'tipoCambio' => $tipoCambio,
                'Total' => $Total,
                'confirmacion' => $confirmacion,
                'TipoDeComprobante' => $TipoDeComprobante,
                'MetodoPago' => $MetodoPago,
                'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
                'LugarExpedicion' => $LugarExpedicion,
                //comprobante
                'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
                'uuid' => $verifiedCfdiRelacionadosuuid,
                //emisor
                'emisorRfc' => $RfcEmi,
                'emisorNombre' => $nombre,
                'emisorRegimenFiscal' => $regimenFiscal,
                //receptor
                'receptorRfc' => $RfcRec,
                'receptorUsoCFDI' => $UsoCFDI,
                'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
                //conceptos    
                'conceptos' => $arrayListaConceptos,
                //impuestos    
                'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
                'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
                'impuestosRetenciones' => $arrayImpuestosRetenciones,
                'impuestosTraslados' => $arrayImpuestosTraslados,
                //complemento 
                'compluuidComplemento' => $uuidComplemento,
                'complfechaTimbrado' => $fechaTimbrado,
                'complRfcProvCertif' => $RfcProvCertif,
                'complSelloCFD' => $SelloCFD,
                'complNoCertificadoSAT' => $NoCertificadoSAT,
                'complSelloSAT' => $SelloSAT,
              );
            } else {
              $factura_xml = array(
                'nombre_xml' => $nombre_xml,
                'resultXml' => 'errorXml',
                'arrayErroresComprobante' => $arrayErroresComprobante,
                'arrayErroresEmisor' => $arrayErroresEmisor,
                'arrayErroresReceptor' => $arrayErroresReceptor,
                'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
                'arrayErroresConceptos' => $arrayErroresConceptos,
                'arrayErroresImpuestos' => $arrayErroresImpuestos,
                'arrayErroresComplemento' => $arrayErroresComplemento,
                'message' => 'xml invalido, revise informe de errores',
              );
            }
          }

          //$arrayProductos = array();
          //$arrayProductosRecibidos = array();
          //$arrayActFijos = array();
          //$arrayActFijosRecibidos = array();
          //$arrayActIntan = array();
          //$arrayActIntanRecibidos = array();
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
              $fecha_recep = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
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
                  "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
                  "precio_unitario" => "$" . $formatPuRetTras[0]->formatPunit,
                  "total" => $totalDetComp[0]->total,
                  "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
                  "total_retenciones" => "$" . $formatPuRetTras[0]->formatRetenc,
                  "total_traslados" => "$" . $formatPuRetTras[0]->formatTraslad,
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

              if (count($selectRecibido) != 0) {
                $arrayArticulosRecibidos[] = $arrayEachDetalleCompra;
              } else {
                $arrayArticulos[] = $arrayEachDetalleCompra;
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
                  "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
                  "precio_unitario" => "$" . $formatPuRetTras[0]->formatPunit,
                  "total" => $totalDetComp[0]->total,
                  "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
                  "total_retenciones" => "$" . $formatPuRetTras[0]->formatRetenc,
                  "total_traslados" => "$" . $formatPuRetTras[0]->formatTraslad,
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
          }

          $arrayForeach = array(
            "token_compras" => $vBuy->token_compras,
            "persCompra" => $user_compra,
            "recibeFactura" => $vBuy->recibeFactura ? true : false,
            "nombre_pdf" => $nombre_pdf,
            "factura_pdf" => $factura_pdf,
            "factura_xml" => $factura_xml,
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
  }*/

  //compras pagadas
  public function listaComprasPeriodicasProd(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    //da_te_default_timezone_set($vBuy->zona_horaria);

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
          //da_te_default_timezone_set($vBuy->zona_horaria);
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

  //recepcion de facturas
  public function listaComprasRecibeFacturaDespues(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    $arrayCompras = array();
    //$listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
    //->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    //->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    //->where([
    //  "eegr_compras.recibeFactura" => FALSE,
    //  "eegr_compras.status_compra" => TRUE, 
    //  "eegr_compras.facturaXml" => NULL,
    //  "eegr_compras.facturaPdf" => NULL,
    //  "eegr_compras.evidenciaSAT" => NULL,
    //  "emp.empresa_token" => $usuario->empresa_token, 
    //  "users.usuario_token" => $usuario->user_token
    //])->get();

    $listaCompras = ComprasModelo::join("main_empresas AS emp", "eegr_compras.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->whereNotIn('eegr_compras.id', function($queryCFDI) {
      $queryCFDI->select('compra_vinculada')->from('cfdi_estructura');
    })
    ->where([
      "eegr_compras.recibeFactura" => FALSE,
      "eegr_compras.status_compra" => TRUE, 
      "emp.empresa_token" => $usuario->empresa_token, 
      "users.usuario_token" => $usuario->user_token
    ])->get();

    foreach ($listaCompras as $vBuy) {
      //da_te_default_timezone_set($vBuy->zona_horaria);
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
        $proveedor_rfc = $vProv->rfc != NULL ? $JwtAuth->desencriptar($vProv->rfc) : $vProv->rfc_generico;
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
        "fecha_registro" => date('d-m-Y H:i:s', $vBuy->fecha_sistemaCompras),
        "facturaXml" => '',
        "xmlRecibido" => false,
        "facturaPdf" => '',
        "pdflRecibido" => false,
        "evidenciaSAT" => '',
        "evdSRecibida" => false,
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
        "proveedor_rfc" => strtoupper($proveedor_rfc),
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

    return response()->json([
      'compras' => $arrayCompras,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function registraRecepcionFacturaCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('dataCompra');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        "compra_token" => 'string', 
        "proveedor_token" => 'string', 
        "emisor" => 'string', 
        "receptor" => 'string', 
        "uuid" => 'string',
        "tipoDeComprobante" => 'string',  
        "fechaTimbrado" => 'string',
        "total" => 'string',
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
        
        $compra_token = $parametrosArray['compra_token'];
        $proveedor_token = $parametrosArray['proveedor_token'];
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $tipoDeComprobante = $parametrosArray['tipoDeComprobante'];
        $fechaTimbrado = $parametrosArray['fechaTimbrado'];
        $total = $parametrosArray['total'];

        $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$usuario->empresa_token,$usuario->user_token);
        $idCompra = DB::table("eegr_compras")->where("token_compras",$compra_token)->value("id");
        $validate_compra_token = isset($compra_token) && !empty($compra_token) && $idCompra != "";

        $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$proveedor_token)->value("id");
        $validate_proveedor_token = isset($proveedor_token) && !empty($proveedor_token) && $idProveedor != "";

        $validate_emisor = isset($emisor) && !empty($emisor);
        $validate_receptor = isset($receptor) && !empty($receptor);
        $validate_uuid = isset($uuid) && !empty($uuid);
        $validate_tipoDeComprobante = isset($tipoDeComprobante) && !empty($tipoDeComprobante);
        $validate_fechaTimbrado = isset($fechaTimbrado) && !empty($fechaTimbrado);
        $validate_total = isset($total) && !empty($total); 

        if ($permisosCreacion && $validate_compra_token && $validate_proveedor_token && $validate_emisor && $validate_receptor && $validate_uuid && $validate_tipoDeComprobante && $validate_fechaTimbrado && $validate_total && 
          file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaPdf')) && file_exists($request->file('imagenEvidenciaVerificacion'))) {
          $selectCompraFacturas = DB::table('eegr_compras AS buy')
          ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'buy.status_compra' => TRUE,
            'buy.token_compras' => $compra_token,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->get();
          foreach ($selectCompraFacturas as $vBuy) {
            $fechaSistema = $vBuy->fecha_sistemaCompras;
            $folio_buy = "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : '');
            $upDateCompraFacturas = DB::table('eegr_compras AS buy')
            ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'buy.status_compra' => TRUE,
              'buy.token_compras' => $compra_token,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                "buy.facturaXml" => $JwtAuth->encriptar($request->file('imagenEvidenciaXMl')->getClientOriginalName()), //cifrado  
                "buy.facturaPdf" => $JwtAuth->encriptar($request->file('imagenEvidenciaPdf')->getClientOriginalName()),  //cifrado 
                "buy.evidenciaSAT" => $JwtAuth->encriptar($fechaSistema."-".$folio_buy.$request->file('imagenEvidenciaVerificacion')->getClientOriginalName(). ".pdf"), //cifrado
              )
            );
            if ($upDateCompraFacturas) {
              $insertCFDIEstructura = DB::table('cfdi_estructura')
              ->insert(array(
                "rfc_emisor" => $emisor,
                "uuid" => $uuid,
                "tipo_cfdi" => $tipoDeComprobante,
                "fecha_timbrado" => $fechaTimbrado,
                "total" => $total,
                "compra_vinculada" => $idCompra,
              ));
              
              $filepath = $vBuy->root_tkn . "/0002-cpp/compras/compras/$fechaSistema-$folio_buy/";
              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }
              
              Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaXMl'),$request->file('imagenEvidenciaXMl')->getClientOriginalName());
              Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName());
              Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName());
              
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Recepción de facturas terminada satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Recepción de facturas no terminada, intente más tarde o comuniquese a soporte'
              );
            }
          }
        } else {
          $mensaje_error_main = '';
          if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
          if (!$validate_compra_token) {$mensaje_error_main = 'Error al seleccionar compra, verifique la información de su compra';}
          if (!$validate_proveedor_token) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique la información de su proveedor';}
          if (!$validate_emisor) {$mensaje_error_main = 'Error al seleccionar emisor, verifique la información de su proveedor';}
          if (!$validate_receptor) {$mensaje_error_main = 'Error al seleccionar receptor, verifique la información de su empresa';}
          if (!$validate_uuid) {$mensaje_error_main = 'Error en UUID de su CFDI, verifique su información';}
          if (!$validate_tipoDeComprobante) {$mensaje_error_main = 'Error en tipo de comprobante de su CFDI, verifique su información';}
          if (!$validate_fechaTimbrado) {$mensaje_error_main = 'Error en fecha de timbrado de su CFDI, verifique su información';}
          if (!$validate_total) {$mensaje_error_main = 'Error en total de su CFDI, verifique su información';}
          if (!file_exists($request->file('imagenEvidenciaXMl'))) {$mensaje_error_main = 'Debe cargar la factura en formato xml correspondiente a esta compra';}
          if (!file_exists($request->file('imagenEvidenciaPdf'))) {$mensaje_error_main = 'Debe cargar la factura en formato pdf correspondiente a esta compra';}
          if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
        }
      }
    } else {
      $dataMensaje = array('status' => 'error','code' => 200,'message' => 'Los datos no son correctos');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cargaFacturaCompras(Request $request){
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
                  //$selectRecept = DB::select("SELECT id FROM recepcion_compras WHERE token_recept_compra = ?", [$tookenRecept_compra]);
                  $selectRecept = $newReceptBuy->id;

                  //$establ = DB::table("in_egr_establecimientos_catalogo AS estab")
                  //->join("in_egr_establecimientos_responsables AS resp", "estab.id", "resp.almacen")
                  //->join("main_empresas AS emp", "resp.administrador", "emp.id")
                  //->join("vhum_empleados_catalogo AS persnl", "resp.responsable", "persnl.id")
                  //->join("teci_usuarios_catalogo AS users", "persnl.id", "users.empleado")
                  ////->where('respons.almacen','alm.id')
                  //->where([
                  //  "emp.empresa_token" => $usuario->empresa_token,
                  //  'users.usuario_token' => $usuario->user_token
                  //])->get();
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

  //recepcion de productos
  public function detalleComprasReceptProd(Request $request){
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
              $fecha_recep = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_recep);
              $folio_recep = $JwtAuth->generar($selectRecibido[0]->folio_recep);
              $bool_recept_observaciones = $JwtAuth->desencriptar($selectRecibido[0]->observaciones);
              $bool_recept_validado_por = $JwtAuth->desencriptar($selectRecibido[0]->paterno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->materno) . " " . $JwtAuth->desencriptar($selectRecibido[0]->nombre);
            }

            //productos
            if ($vDetBuy->producto != NULL) {

            }
            $productoList = DB::table("eegr_compras_detalle AS detbuy")
            ->join("in_egr_catalogo_productos AS catprod","detbuy.producto","=","catprod.id")
            ->whereNotNull('detbuy.producto')
            ->where(['detbuy.token_detcompra' => $vDetBuy->token_detcompra])->get();

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
                "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
                "precio_unitario" => "$" . $formatPuRetTras[0]->formatPunit,
                "total" => $totalDetComp[0]->total,
                "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
                "total_retenciones" => "$" . $formatPuRetTras[0]->formatRetenc,
                "total_traslados" => "$" . $formatPuRetTras[0]->formatTraslad,
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

            if (count($selectRecibido) != 0) {
              $arrayArticulosRecibidos[] = $arrayEachDetalleCompra;
            } else {
              $arrayArticulos[] = $arrayEachDetalleCompra;
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

  public function recibeActivoFijoComprasAlmacen(Request $request){
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
              $token_act_fijos = $parametrosArray['arrayActFijos'][$i]['token_act_fijos'];
              $token_detcompra = $parametrosArray['arrayActFijos'][$i]['token_detcompra'];
              $lo_pedido = $parametrosArray['arrayActFijos'][$i]['lo_pedido'];
              $llegoTiempo = $parametrosArray['arrayActFijos'][$i]['llegoTiempo'];
              $buenEstado = $parametrosArray['arrayActFijos'][$i]['buenEstado'];
              $calidadRecepcion = $parametrosArray['arrayActFijos'][$i]['calidadRecepcion'];
              $observaciones =  $parametrosArray['arrayActFijos'][$i]['observaciones'];
              $checked_recept = $parametrosArray['arrayActFijos'][$i]['checked_recept'];
              $recibe_factura = $parametrosArray['arrayActFijos'][$i]['recibe_factura'];

              $folioRecept = DB::select(
                "SELECT IF (max(recept.folio_recep) IS NOT NULL,(max(recept.folio_recep)+1),1) AS folio
                                    FROM eegr_compras_recepcion AS recept JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                    JOIN teci_usuarios_catalogo AS users WHERE recept.empresa = emp.id AND emp.empresa_token = ? 
                                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$usuario->empresa_token, $usuario->user_token]
              );

              $selectCompras = DB::select(
                "SELECT buy.id,buy.folio_compra,buy.recibeFactura,buy.facturaXml,buy.facturaPdf,buy.fecha_sistemaCompras 
                                    FROM eegr_compras AS buy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                                    WHERE buy.token_compras = ? AND buy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token= ?",
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

              $selectCatActFijo = DB::select("SELECT cat_activo.id FROM eegr_activos_fijos_catalogo AS cat_activo JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE cat_activo.token_act_fijos = ? 
                                    AND cat_activo.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token= ?", [$token_act_fijos, $usuario->empresa_token, $usuario->user_token]);

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
              $newReceptBuy->activo_fijo = $selectCatActFijo[0]->id;
              $newReceptBuy->activo_intangible = NULL;
              $newReceptBuy->servicio = NULL;
              $newReceptBuy->lo_pedido = $lo_pedido;
              $newReceptBuy->llego_tiempo = $llegoTiempo;
              $newReceptBuy->buen_estado = $buenEstado;
              $newReceptBuy->calidad_recepcion = $calidadRecepcion;
              $newReceptBuy->observaciones = $JwtAuth->encriptar($observaciones);
              $newReceptBuy->recept_status = $checked_recept;
              $newReceptBuy->recibe_factura = $recibe_factura;
              $newReceptBuy->valida_recept = $selectEmp[0]->userr;
              $newReceptBuy->empresa = $selectEmp[0]->id;
              $insert_recept = $newReceptBuy->save();
              if ($insert_recept) {
                //return response()->json(['message' => 'final codigo'.$selectCompras[0]->recibeFactura,'codigo' => 200,'status' => 'error']);
                if ($selectCompras[0]->facturaXml == '') {
                  $nombreXml = $request->file('imagenEvidenciaXMl')->getClientOriginalName();
                  //return response()->json(['message' => 'final codigo'.$selectCompras[0]->fecha_sistemaCompras,'codigo' => 200,'status' => 'error']);
                  $nombrePDf = $request->file('imagenEvidenciaPdf')->getClientOriginalName();
                  $nombreDocs = $selectCompras[0]->fecha_sistemaeegr_compras . "-" . $JwtAuth->generar($selectCompras[0]->folio_compra);
                  //return response()->json(['message' => 'final codigo'.$selectCompras[0]->facturaXml,'codigo' => 200,'status' => 'error']);
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
                      "activo" => $selectCatActFijo[0]->id,
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

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

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
              $fecha_devengado = gmdate('Y-m-d H:i:s', $selectRecibido[0]->fecha_devengacion);
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
            //da_te_default_timezone_set($vEmp->zona_horaria);
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
          //da_te_default_timezone_set($vDev->zona_horaria);
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
