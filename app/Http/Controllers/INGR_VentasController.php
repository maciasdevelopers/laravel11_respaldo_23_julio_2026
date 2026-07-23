<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Response;
use App\Models\VentasModelo;
use App\Models\ServiciosModelo;
use App\Models\ProductosModelo;
use App\Models\DetalleVentasModelo;
use App\Models\ClientesModelo;
use App\Models\ImpuestosModelo;
use App\Models\OrdenCobroModelo;

class INGR_VentasController extends Controller
{
  public $clave_cifrado;
  public function __construct()
  {
    $this->clave_cifrado = "dtclavessecreto-9876986986986986s";
  }

  public function buscaArticulosVentaMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'busqueda' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $busqueda = $parametrosArray["busqueda"];
        $arrayArticulos = array();

        $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        $contador_posicion = 0;
        $contador_principal = 1;
        $catProdServ = "SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,catprod.producto AS concepto,catprod.folio_sistema AS folio,
                    catprod.costo_aplicable AS precioBase,catprod.unidad_medida_salida_clave as unidad_medida,emp.root_tkn FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprod.modulo_mostrador = TRUE AND catprod.authorized = TRUE AND catprod.tipo_prod = 'pr' 
                    AND catprod.activo IS NULL AND catprod.status = TRUE AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                    AND users.usuario_token = ?";

        $catServQuery = "SELECT catserv.token_cat_servicios AS token_articulo,catserv.fecha_registro_serv AS fecha_alta,'Servicio' AS identificador,catserv.servicio AS concepto,catserv.folio_sistema AS folio,
                    catserv.precioBase AS precioBase,catserv.unidad_medida_clave as unidad_medida,emp.root_tkn FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users WHERE catserv.modulo_mostrador = TRUE AND catserv.authorized = TRUE AND catserv.proceso = 'v' AND catserv.status = TRUE AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?";

        $combinado = "{$catProdServ} UNION {$catServQuery}";
        $resultQuery = DB::select($combinado, [$usuario->empresa_token, $usuario->user_token, $usuario->empresa_token, $usuario->user_token]);
        $arrayInterno = array();
        foreach ($resultQuery as $value) {
          $precioBase = number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', '');
          $logotipo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";
          $precioBaseConImp = number_format($value->precioBase, 2, '.', ',');
          $arraForeach = array(
            "contador_posicion" => $contador_posicion,
            "token_articulo" => $value->token_articulo,
            "num_lista" => $contador_principal,
            "imagen" => $logotipo,
            "identificador" => $value->identificador,
            "concepto" => $JwtAuth->desencriptar($value->concepto),
            "precioBase" => $precioBase,
            "precioBaseNew" => $precioBase,
            "precioBaseFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
            "dataCantidad" => 0,
            "descuento_aplicado" => 0.00,
            "descuento_aplicadoFormat" =>  "$" . number_format(0, $decimalesMoneda[0]->decimales, '.', ','),
            "subtotalAfterDescuentos" => $precioBase,
            "subtotalAfterDescuentosFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
            "esquema_impuestos_aplicado" => "",
            "impuestos_aplicados" => [],
            "totalTrasladados" => 0.00,
            "totalTrasladadosFormat" => "",
            "totalRetenidos" => 0.00,
            "totalRetenidosFormat" => "",
            "importePartida" => $value->precioBase,
            "importePartidaFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
          );
          $arrayInterno[] = $arraForeach;
          ++$contador_principal;
          ++$contador_posicion;
        }

        $columnas = array_column($arrayInterno, "concepto");
        $index = array_search($busqueda, $columnas);
        //echo $arrayArticulos[$index]["identificador"];
        $arrayArticulos[] = $arrayInterno[$index];
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'listaArticulos' => $arrayArticulos,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cargaArticulosVentaMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayArticulos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                    AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        $contador_posicion = 0;
        $contador_principal = 1;
        $catProdServ = "SELECT catprod.token_cat_productos AS token_articulo,
                    catprod.fecha_registro_prod AS fecha_alta,
                    'Producto' AS identificador,
                    catprod.producto AS concepto,
                    catprod.folio_sistema AS folio,
                    catprod.costo_aplicable AS precioBase,
                    catprod.unidad_medida_salida_clave as unidad_medida,
                    emp.root_tkn
                    FROM in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users 
                    WHERE catprod.modulo_mostrador = TRUE 
                    AND catprod.authorized = TRUE 
                    AND catprod.tipo_prod = 'pr' 
                    AND catprod.activo IS NULL 
                    AND catprod.status = TRUE 
                    AND catprod.admin_empresa = emp.id 
                    AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id 
                    AND users.usuario_token = ?";

        $catServQuery = "SELECT catserv.token_cat_servicios AS token_articulo,
                    catserv.fecha_registro_serv AS fecha_alta,
                    'Servicio' AS identificador,
                    catserv.servicio AS concepto,
                    catserv.folio_sistema AS folio,
                    catserv.precioBase AS precioBase,
                    catserv.unidad_medida_clave as unidad_medida,
                    emp.root_tkn
                    FROM in_egr_catalogo_servicios AS catserv 
                    JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users 
                    WHERE catserv.modulo_mostrador = TRUE 
                    AND catserv.authorized = TRUE 
                    AND catserv.proceso = 'v'  
                    AND catserv.status = TRUE 
                    AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id 
                    AND users.usuario_token = ?";

        $combinado = "{$catProdServ} UNION {$catServQuery}";
        $resultQuery = DB::select($combinado, [$usuario->empresa_token, $usuario->user_token, $usuario->empresa_token, $usuario->user_token]);
        //echo count($resultQuery);

        foreach ($resultQuery as $value) {
          //$precioBase = $value->precioBase;
          $precioBase = number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', '');
          $logotipo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";
          $precioBaseConImp = number_format($value->precioBase, 2, '.', ',');
          $arraForeach = array(
            "contador_posicion" => $contador_posicion,
            "token_articulo" => $value->token_articulo,
            "num_lista" => $contador_principal,
            "imagen" => $logotipo,
            "identificador" => $value->identificador,
            "concepto" => $JwtAuth->desencriptar($value->concepto),
            "precioBase" => $precioBase,
            "precioBaseNew" => $precioBase,
            "precioBaseFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
            "dataCantidad" => 0,
            "descuento_aplicado" => 0.00,
            "descuento_aplicadoFormat" =>  "$" . number_format(0, $decimalesMoneda[0]->decimales, '.', ','),
            "subtotalAfterDescuentos" => $precioBase,
            "subtotalAfterDescuentosFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
            "esquema_impuestos_aplicado" => "",
            "impuestos_aplicados" => [],
            "totalTrasladados" => 0.00,
            "totalTrasladadosFormat" => "",
            "totalRetenidos" => 0.00,
            "totalRetenidosFormat" => "",
            "importePartida" => $value->precioBase,
            "importePartidaFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
          );
          $arrayArticulos[] = $arraForeach;
          ++$contador_principal;
          ++$contador_posicion;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'listaArticulos' => $arrayArticulos,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cargaArticulosVentaMostradorByCode(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayArticulos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'scanner_codigo' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $scanner_codigo = $parametrosArray['scanner_codigo'];

        $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                    AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        $contador_posicion = 0;
        $contador_principal = 1;
        $catProdServ = "SELECT catprod.token_cat_productos AS token_articulo,
                    catprod.fecha_registro_prod AS fecha_alta,
                    'Producto' AS identificador,
                    catprod.producto AS concepto,
                    catprod.folio_sistema AS folio,
                    catprod.costo_aplicable AS precioBase,
                    catprod.unidad_medida_salida_clave as unidad_medida,
                    emp.root_tkn
                    FROM in_egr_catalogo_productos AS catprod 
                    JOIN in_egr_catalogo_productos_claves_internas AS klav
                    JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users 
                    WHERE catprod.modulo_mostrador = TRUE 
                    AND catprod.authorized = TRUE 
                    AND catprod.tipo_prod = 'pr' 
                    AND catprod.activo IS NULL 
                    AND catprod.status = TRUE 
                    AND catprod.id = klav.producto_alta 
                    AND klav.clave_valor = ?
                    AND catprod.admin_empresa = emp.id 
                    AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id 
                    AND users.usuario_token = ?";

        $catServQuery = "SELECT catserv.token_cat_servicios AS token_articulo,
                    catserv.fecha_registro_serv AS fecha_alta,
                    'Servicio' AS identificador,
                    catserv.servicio AS concepto,
                    catserv.folio_sistema AS folio,
                    catserv.precioBase AS precioBase,
                    catserv.unidad_medida_clave as unidad_medida,
                    emp.root_tkn
                    FROM in_egr_catalogo_servicios AS catserv 
                    JOIN in_egr_catalogo_servicios_claves_internas AS klav
                    JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users 
                    WHERE catserv.modulo_mostrador = TRUE 
                    AND catserv.authorized = TRUE 
                    AND catserv.proceso = 'v'  
                    AND catserv.status = TRUE 
                    AND catserv.id = klav.servicio_alta
                    AND klav.clave_valor = ?
                    AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id 
                    AND users.usuario_token = ?";

        $combinado = "{$catProdServ} UNION {$catServQuery}";
        $resultQuery = DB::select($combinado, [$scanner_codigo, $usuario->empresa_token, $usuario->user_token, $scanner_codigo, $usuario->empresa_token, $usuario->user_token]);
        //echo count($resultQuery);

        foreach ($resultQuery as $value) {
          //$precioBase = $value->precioBase;
          $precioBase = number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', '');
          $logotipo = $value->identificador == "Producto" ? "./assets/images/catalogos/default_producto.jpg" : "./assets/images/catalogos/default_servicio.jpg";
          $precioBaseConImp = number_format($value->precioBase, 2, '.', ',');
          $arraForeach = array(
            "contador_posicion" => $contador_posicion,
            "token_articulo" => $value->token_articulo,
            "num_lista" => $contador_principal,
            "imagen" => $logotipo,
            "identificador" => $value->identificador,
            "concepto" => $JwtAuth->desencriptar($value->concepto),
            "precioBase" => $precioBase,
            "precioBaseNew" => $precioBase,
            "precioBaseFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
            "dataCantidad" => 0,
            "descuento_aplicado" => 0.00,
            "descuento_aplicadoFormat" =>  "$" . number_format(0, $decimalesMoneda[0]->decimales, '.', ','),
            "subtotalAfterDescuentos" => $precioBase,
            "subtotalAfterDescuentosFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
            "esquema_impuestos_aplicado" => "",
            "impuestos_aplicados" => [],
            "totalTrasladados" => 0.00,
            "totalTrasladadosFormat" => "",
            "totalRetenidos" => 0.00,
            "totalRetenidosFormat" => "",
            "importePartida" => $value->precioBase,
            "importePartidaFormat" => "$" . number_format($value->precioBase, $decimalesMoneda[0]->decimales, '.', ','),
          );
          $arrayArticulos[] = $arraForeach;
          ++$contador_principal;
          ++$contador_posicion;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'listaArticulos' => $arrayArticulos,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registroVentaMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_clientes' => 'required|string',
        'token_puntodeventa' => 'required|string',
        'mx_venta_moneda_codigo' => 'required|string',
        'mx_venta_moneda_decimales' => 'required|numeric',
        'cnvr_venta_tipo_cambio_simple' => 'required|numeric',
        'cnvr_venta_moneda_codigo' => 'required|string',
        'cnvr_venta_moneda_decimales' => 'required|numeric',
        'listaArticulosVenta' => 'required|array',
        'generar_factura' => 'string',
        'imperial_code' => 'required|string',
        'imperial_pass' => 'required|string',

        'venta_cobro_forma_generada' => 'required|string',
        'venta_cobro_fecha' => 'required|string',
        'venta_cobro_banco' => 'string',
        'venta_cobro_cuenta_card_clabe' => 'string',
        'venta_cobro_clave_referencia' => 'string',
        'venta_cobro_moneda_code' => 'required|string',
        'venta_cobro_moneda_decimales' => 'required|numeric',
        'venta_cobro_importe' => 'required|numeric',
        'venta_cobro_tipo_cambio' => 'required|numeric',
        'venta_cobro_concepto' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fecha_registro = time();
        $token_cat_clientes = $parametrosArray['token_cat_clientes'];
        $token_puntodeventa = $parametrosArray['token_puntodeventa'];
        $mx_venta_moneda_codigo = $parametrosArray['mx_venta_moneda_codigo'];
        $mx_venta_moneda_decimales = $parametrosArray['mx_venta_moneda_decimales'];
        $cnvr_venta_tipo_cambio_simple = $parametrosArray['cnvr_venta_tipo_cambio_simple'];
        $cnvr_venta_moneda_codigo = $parametrosArray['cnvr_venta_moneda_codigo'];
        $cnvr_venta_moneda_decimales = $parametrosArray['cnvr_venta_moneda_decimales'];
        $listaArticulosVenta = $parametrosArray['listaArticulosVenta'];
        $generar_factura = $parametrosArray['generar_factura'];
        $imperial_code = $parametrosArray['imperial_code'];
        $imperial_pass = $parametrosArray['imperial_pass'];

        $venta_cobro_forma_generada = $parametrosArray['venta_cobro_forma_generada'];
        $venta_cobro_fecha = $parametrosArray['venta_cobro_fecha'];
        $venta_cobro_banco = $parametrosArray['venta_cobro_banco'];
        $venta_cobro_cuenta_card_clabe = $parametrosArray['venta_cobro_cuenta_card_clabe'];
        $venta_cobro_clave_referencia = $parametrosArray['venta_cobro_clave_referencia'];
        $venta_cobro_moneda_code = $parametrosArray['venta_cobro_moneda_code'];
        $venta_cobro_moneda_decimales = $parametrosArray['venta_cobro_moneda_decimales'];
        $venta_cobro_importe = $parametrosArray['venta_cobro_importe'];
        $venta_cobro_tipo_cambio = $parametrosArray['venta_cobro_tipo_cambio'];
        $venta_cobro_concepto = $parametrosArray['venta_cobro_concepto'];

        //echo $venta_cobro_forma_generada;exit;

        if (
          isset($token_cat_clientes) && !empty($token_cat_clientes) && isset($token_puntodeventa) && !empty($token_puntodeventa) &&
          isset($mx_venta_moneda_codigo) && !empty($mx_venta_moneda_codigo) && preg_match($JwtAuth->filtroAlfabetico(), $mx_venta_moneda_codigo) &&
          isset($mx_venta_moneda_decimales) && !empty($mx_venta_moneda_decimales) && preg_match($JwtAuth->filtroNumericoSimple(), $mx_venta_moneda_decimales) &&
          isset($cnvr_venta_tipo_cambio_simple) && !empty($cnvr_venta_tipo_cambio_simple) && preg_match($JwtAuth->filtroNumericoSimple(), $cnvr_venta_tipo_cambio_simple) &&
          isset($cnvr_venta_moneda_codigo) && !empty($cnvr_venta_moneda_codigo) && preg_match($JwtAuth->filtroAlfabetico(), $cnvr_venta_moneda_codigo) &&
          isset($cnvr_venta_moneda_decimales) && !empty($cnvr_venta_moneda_decimales) && preg_match($JwtAuth->filtroNumericoSimple(), $cnvr_venta_moneda_decimales) &&
          isset($listaArticulosVenta) && !empty($listaArticulosVenta) && isset($generar_factura) && !empty($generar_factura) && preg_match($JwtAuth->filtroAlfabetico(), $generar_factura) &&

          isset($venta_cobro_forma_generada) && !empty($venta_cobro_forma_generada) && preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_forma_generada) &&
          isset($venta_cobro_fecha) && !empty($venta_cobro_fecha) && preg_match($JwtAuth->filtroFecha(), $venta_cobro_fecha) &&
          isset($venta_cobro_moneda_code) && !empty($venta_cobro_moneda_code) && preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_moneda_code) &&
          isset($venta_cobro_moneda_decimales) && !empty($venta_cobro_moneda_decimales) && preg_match($JwtAuth->filtroNumerico(), $venta_cobro_moneda_decimales) &&
          isset($venta_cobro_importe) && !empty($venta_cobro_importe) && preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_importe) &&
          isset($venta_cobro_tipo_cambio) && !empty($venta_cobro_tipo_cambio) && preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_tipo_cambio) &&
          isset($venta_cobro_concepto) && !empty($venta_cobro_concepto) && preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_concepto)
        ) {

          $selectEmp = DB::select(
            "SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$usuario->empresa_token, $usuario->user_token]
          );
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

          $selectIdentCliente = DB::table("ingr_catalogo_clientes")->where("token_cat_clientes", $token_cat_clientes)->pluck("id")->first();
          $selectIdentPVenta = DB::table("sos_puntodeventa_catalogos")->where("token_puntodeventa", $token_puntodeventa)->pluck("id")->first();

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp
                        WHERE fold.ing_ventas = TRUE AND fold.serie IS NULL AND fold.subserie IS NULL AND fold.empresa = emp.id 
                        AND emp.empresa_token = ?", [$usuario->empresa_token]);

          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
              $post_folio_db = DB::select("SELECT post_folio FROM ingr_ventas WHERE id = (SELECT Max(sell.id) FROM ingr_ventas AS sell 
                                JOIN main_empresas AS emp WHERE sell.vendedor_empresa = emp.id AND emp.empresa_token = ?)", [$usuario->empresa_token]);
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
            $folio_vent = 'VENT-' . $JwtAuth->generarFolio($folio_nuevo);
          } else {
            $folio_vent = 'VENT-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
          }

          $serie = "1";
          $subserie = "";
          $numsubserie = "";

          if ($generar_factura == true) {
            $subserie = "1";
            $folioSerie = DB::select("SELECT IF(MAX(fold.folder) IS NOT NULL,MAX(fold.folder)+1,1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp 
    					    WHERE fold.ing_ventas = TRUE AND fold.serie = 1 AND fold.subserie = 1 AND fold.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);
            $numsubserie = $folioSerie[0]->folio;
          } else {
            $subserie = "2";
            $folioSerie = DB::select("SELECT IF(MAX(fold.folder) IS NOT NULL,MAX(fold.folder)+1,1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp 
    					    WHERE fold.ing_ventas = TRUE AND fold.serie = 1 AND fold.subserie = 2 AND fold.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);
            $numsubserie = $folioSerie[0]->folio;
          }

          $token_new_venta = $JwtAuth->encriptarToken($fecha_registro . end($selectEmp)->id . end($selectEmp)->userr, $selectIdentCliente . $selectIdentPVenta);
          $token_venta_access = substr($token_new_venta, 0, 20);

          $nuevaVenta = new VentasModelo();
          $nuevaVenta->token_ventas = $token_new_venta;
          $nuevaVenta->fecha_registro_venta = $fecha_registro;
          $nuevaVenta->tipo_venta = "m";
          $nuevaVenta->genera_factura = $generar_factura == "true" ? TRUE : FALSE;
          $nuevaVenta->token_acceso_venta = $token_venta_access;
          $nuevaVenta->folio_venta = $folio_nuevo;
          $nuevaVenta->post_folio = $post_folio;
          $nuevaVenta->serie = $serie;
          $nuevaVenta->subserie = $subserie;
          $nuevaVenta->numero = $numsubserie;
          $nuevaVenta->punto_venta = $selectIdentPVenta;
          $nuevaVenta->cliente = $selectIdentCliente;
          $nuevaVenta->moneda_code = $mx_venta_moneda_codigo;
          $nuevaVenta->moneda_decimales = $mx_venta_moneda_decimales;
          $nuevaVenta->tipo_cambio_venta = $cnvr_venta_tipo_cambio_simple;
          $nuevaVenta->moneda_conv_code = $cnvr_venta_moneda_codigo;
          $nuevaVenta->moneda_conv_decimales = $cnvr_venta_moneda_decimales;
          $nuevaVenta->access_code = $JwtAuth->encriptar($imperial_code);
          $nuevaVenta->access_pass = $JwtAuth->encriptarCredenciales($imperial_pass);
          $nuevaVenta->vendedor_empresa = end($selectEmp)->id;
          $nuevaVenta->vendedor_usuario = end($selectEmp)->userr;
          $saveVenta = $nuevaVenta->save();

          if ($saveVenta) {
            $contador = 0;

            foreach ($listaArticulosVenta as $row) {
              $token_articulo = $row["token_articulo"];
              $ident_prod = DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $token_articulo)->pluck("id")->first();
              $ident_serv = DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $token_articulo)->pluck("id")->first();
              $identificador = $row["identificador"];
              $precioBase = $row["precioBase"];
              $dataCantidad = $row["dataCantidad"];
              $descuento_aplicado = $row["descuento_aplicado"];
              $subtotalAfterDescuentos = $row["subtotalAfterDescuentos"]; //
              $esquema_impuestos_aplicado = $row["esquema_impuestos_aplicado"];
              $ident_esquema = DB::table("cont_impuestos_esquema")->where("esquema_token", $esquema_impuestos_aplicado)->pluck("id")->first();
              $impuestos_aplicados = $row["impuestos_aplicados"];
              $totalTrasladados = $row["totalTrasladados"]; //
              $totalRetenidos = $row["totalRetenidos"]; //
              $importePartida = $row["importePartida"]; //
              $token_det_venta = $JwtAuth->encriptarToken($precioBase . $dataCantidad . $descuento_aplicado . $totalTrasladados . $totalRetenidos . $importePartida);

              $detSave = DB::table("ingr_ventas_detalle")
                ->insert(array(
                  "venta_detalle_token" => $token_det_venta,
                  "venta_raiz" => $nuevaVenta->id,
                  "producto" => $identificador == "Producto" ? $ident_prod : NULL,
                  "servicio" => $identificador == "Servicio" ? $ident_serv : NULL,
                  "precio_bruto" => $precioBase,
                  "cantidad" => $dataCantidad,
                  "descuento_total" => $descuento_aplicado,
                  "promocion_total" => 0,
                  "esquema_impuestos" => $ident_esquema
                ));

              if ($detSave) {
                foreach ($impuestos_aplicados as $imp_tag) {
                  $ident_det_vent = DB::table("ingr_ventas_detalle")->where("venta_detalle_token", $token_det_venta)->value("id");
                  $ident_imp = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $imp_tag["token_catalogo_impuesto"])->pluck("id")->first();
                  $impSave = DB::table("ingr_ventas_detalle_impuestos")->insert(array("detalle_venta" => $ident_det_vent, "impuesto_relacionado" => $ident_imp));
                }
                ++$contador;
              }
            }

            if ($contador == count($listaArticulosVenta)) {
              $folioOrd = DB::select("SELECT IF(MAX(ord.folio_ordenCobro) IS NOT NULL,MAX(ord.folio_ordenCobro)+1,1) AS folio FROM fnzs_cobros_orden AS ord 
        				        JOIN main_empresas AS emp WHERE ord.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

              $token_ord_cob = $JwtAuth->encriptarToken($fecha_registro . end($selectEmp)->id . end($selectEmp)->userr, $nuevaVenta->id . $venta_cobro_forma_generada);

              $nuevaOrd = new OrdenCobroModelo();
              $nuevaOrd->token_ordenCobro = $token_ord_cob;
              $nuevaOrd->folio_ordenCobro = $folioOrd[0]->folio;
              $nuevaOrd->fecha_sistema_ordenc = $fecha_registro;
              $nuevaOrd->factura_venta = $nuevaVenta->id;
              $nuevaOrd->ord_cliente = 1;
              $nuevaOrd->autorizacion_cobro = TRUE;
              $nuevaOrd->fecha_autorizacion_cobro = $fecha_registro;
              $nuevaOrd->tentativa_cobro = $fecha_registro;
              $nuevaOrd->orden_terminada_bool = TRUE;
              $nuevaOrd->orden_terminada_fecha = $fecha_registro;
              $nuevaOrd->status = TRUE;
              $nuevaOrd->status_cobro = TRUE;
              $nuevaOrd->empresa = end($selectEmp)->id;
              $nuevaOrd->usuario_requiere = end($selectEmp)->userr;
              $saveOrd = $nuevaOrd->save();

              if ($saveOrd) {
                $selectIdentOrd = DB::table("fnzs_cobros_orden")->where("token_ordenCobro", $token_ord_cob)->pluck("id")->first();
                $folioCobro = DB::select("SELECT IF(MAX(cob.folio_cobros) IS NOT NULL,MAX(cob.folio_cobros)+1,1) AS folio FROM fnzs_cobros_cobro AS cob 
            				        JOIN main_empresas AS emp WHERE cob.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);
                $folio_cobro = "COB-" . $JwtAuth->generarFolio($folioCobro[0]->folio);
                $token_cobRO = $JwtAuth->encriptarToken($fecha_registro . $selectIdentOrd . end($selectEmp)->userr, $nuevaVenta->id . $venta_cobro_forma_generada);
                $insertCobro = DB::table("fnzs_cobros_cobro")->insert(
                  array(
                    "token_cobros" => $token_cobRO,
                    "folio_cobros" => $folioCobro[0]->folio,
                    "fecha_sistema" => $fecha_registro,
                    "fecha_cobro" => $fecha_registro,
                    "orden_cobro" => $nuevaOrd->id,
                    "forma_cobro_clave" => $venta_cobro_forma_generada,
                    "banco_clave" => $venta_cobro_banco != "" ? $venta_cobro_banco : NULL,
                    "cuenta_bancaria_clave" => $venta_cobro_cuenta_card_clabe != "" ? $venta_cobro_cuenta_card_clabe : NULL,
                    "num_referencia_cobro" => $venta_cobro_clave_referencia != "" ? $venta_cobro_clave_referencia : NULL,
                    "monto_cobro" => $venta_cobro_importe,
                    "tipo_cambio" => $venta_cobro_tipo_cambio,
                    "moneda_clave" => $venta_cobro_moneda_code,
                    "moneda_decimales" => $venta_cobro_moneda_decimales,
                    "cliente" => 1,
                    "venta" => $nuevaVenta->id,
                    "concepto" => $JwtAuth->encriptar($venta_cobro_concepto),
                    "personal_cobro" => end($selectEmp)->userr,
                    "personal_autoriza" => end($selectEmp)->userr,
                    "empresa" => end($selectEmp)->id,
                    "status_cobro" => TRUE,
                  )
                );

                if ($insertCobro) {
                  if (count($folioSistema) == 0) {
                    $insertSistema = DB::table("sos_last_folders")->insert(array("ing_ventas" => TRUE, "folder" => 1, "post_folder" => $post_folio, "empresa" => end($selectEmp)->id));
                  } else {
                    $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                      ->where(["lastf.ing_ventas" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
                      ->limit(1)->update(array("lastf.folder" => $folio_nuevo, "lastf.post_folder" => $post_folio));
                  }

                  if ($generar_factura == true) {
                    if ($numsubserie == 1) {
                      $insertSistema = DB::table("sos_last_folders")->insert(array("ing_ventas" => TRUE, "folder" => 1, "serie" => 1, "subserie" => 1, "empresa" => end($selectEmp)->id));
                    } else {
                      $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                        ->where(["lastf.ing_ventas" => TRUE, "lastf.serie" => 1, "lastf.subserie" => 1, "emp.empresa_token" => $usuario->empresa_token])
                        ->limit(1)->update(array("lastf.folder" => $numsubserie));
                    }
                  } else {
                    if ($numsubserie == 1) {
                      $insertSistema = DB::table("sos_last_folders")->insert(array("ing_ventas" => TRUE, "folder" => 1, "serie" => 1, "subserie" => 2, "empresa" => end($selectEmp)->id));
                    } else {
                      $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                        ->where(["lastf.ing_ventas" => TRUE, "lastf.serie" => 1, "lastf.subserie" => 2, "emp.empresa_token" => $usuario->empresa_token])
                        ->limit(1)->update(array("lastf.folder" => $numsubserie));
                    }
                  }

                  $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "message" => "Venta registrada exitosamente con el folio " . $folio_vent . " y cobro con folio " . $folio_cobro,
                    "venta_access" => $token_venta_access,
                    "folio_vent" => $folio_vent,
                  );
                } else {
                  $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Cobro no registrado, por favor verifique su información o comuniquese a soporte"
                  );
                }
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Cobro no registrado, por favor verifique su información o comuniquese a soporte"
                );
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Venta no registrada, por favor verifique su información o comuniquese a soporte"
              );
            }
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Venta no registrada, por favor verifique su información o comuniquese a soporte"
            );
          }
        } else {
          $mensajeError = "";
          if (!isset($token_cat_clientes) || empty($token_cat_clientes)) $mensajeError = "Error al seleccionar cliente para venta, por favor verifique su información o comuniquese a soporte";
          if (!isset($token_puntodeventa) || empty($token_puntodeventa)) $mensajeError = "Error al seleccionar punto de venta, por favor verifique su información o comuniquese a soporte";
          if (!isset($mx_venta_moneda_codigo) || empty($mx_venta_moneda_codigo) || !preg_match($JwtAuth->filtroAlfabetico(), $mx_venta_moneda_codigo)) $mensajeError = "Error al seleccionar moneda para venta (código de moneda), por favor verifique su información o comuniquese a soporte";
          if (!isset($mx_venta_moneda_decimales) || empty($mx_venta_moneda_decimales) || !preg_match($JwtAuth->filtroNumericoSimple(), $mx_venta_moneda_decimales)) $mensajeError = "Error al seleccionar moneda para venta (decimales), por favor verifique su información o comuniquese a soporte";
          if (!isset($cnvr_venta_tipo_cambio_simple) || empty($cnvr_venta_tipo_cambio_simple) || !preg_match($JwtAuth->filtroNumericoSimple(), $cnvr_venta_tipo_cambio_simple)) $mensajeError = "Error al registrar tipo de cambio para venta, por favor verifique su información o comuniquese a soporte";
          if (!isset($cnvr_venta_moneda_codigo) || empty($cnvr_venta_moneda_codigo) || !preg_match($JwtAuth->filtroAlfabetico(), $cnvr_venta_moneda_codigo)) $mensajeError = "Error al seleccionar otra moneda para venta (código de moneda), por favor verifique su información o comuniquese a soporte";
          if (!isset($cnvr_venta_moneda_decimales) || empty($cnvr_venta_moneda_decimales) || !preg_match($JwtAuth->filtroNumericoSimple(), $cnvr_venta_moneda_decimales)) $mensajeError = "Error al seleccionar otra moneda para venta (decimales), por favor verifique su información o comuniquese a soporte";
          if (!isset($listaArticulosVenta) || empty($listaArticulosVenta)) $mensajeError = "Error al seleccionar lista de articulos para venta, por favor verifique su información o comuniquese a soporte";
          if (!isset($generar_factura) || !empty($generar_factura) || !preg_match($JwtAuth->filtroAlfabetico(), $generar_factura)) $mensajeError = "Error al decidir si requiere facrtura para venta, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_forma_generada) || empty($venta_cobro_forma_generada) || !preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_forma_generada)) $mensajeError = "Error al seleccionar forma de cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_fecha) || empty($venta_cobro_fecha) || !preg_match($JwtAuth->filtroFecha(), $venta_cobro_fecha)) $mensajeError = "Error al seleccionar fecha de cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_moneda_code) || empty($venta_cobro_moneda_code) || !preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_moneda_code)) $mensajeError = "Error al seleccionar moneda para cobro (código de moneda), por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_moneda_decimales) || empty($venta_cobro_moneda_decimales) || !preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_moneda_decimales)) $mensajeError = "Error al seleccionar moneda para cobro (decimales), por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_importe) || empty($venta_cobro_importe) || !preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_importe)) $mensajeError = "Error al registrar monto de cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_tipo_cambio) || empty($venta_cobro_tipo_cambio) || !preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_tipo_cambio)) $mensajeError = "Error al registrar tipo de cambio para cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_concepto) || empty($venta_cobro_concepto) || !preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_concepto)) $mensajeError = "Error al seleccionar concepto de cobro, por favor verifique su información o comuniquese a soporte";

          $dataMensaje = array(
            "status" => "error",
            "code" => 400,
            "message" => $mensajeError
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registroCobroVentaMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_venta_generada' => 'required|string',
        'venta_cobro_bool_generar' => 'required|boolean',
        'venta_cobro_forma_generada' => 'required|string',
        'venta_cobro_fecha' => 'required|string',
        'venta_cobro_banco' => 'string',
        'venta_cobro_cuenta_card_clabe' => 'string',
        'venta_cobro_clave_referencia' => 'string',
        'venta_cobro_moneda_code' => 'required|string',
        'venta_cobro_moneda_decimales' => 'required|numeric',
        'venta_cobro_importe' => 'required|numeric',
        'venta_cobro_tipo_cambio' => 'required|numeric',
        'venta_cobro_concepto' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fecha_registro = time();
        $token_venta_generada = $parametrosArray['token_venta_generada'];
        $venta_cobro_bool_generar = $parametrosArray['venta_cobro_bool_generar'];
        $venta_cobro_forma_generada = $parametrosArray['venta_cobro_forma_generada'];
        $venta_cobro_fecha = $parametrosArray['venta_cobro_fecha'];
        $venta_cobro_banco = $parametrosArray['venta_cobro_banco'];
        $venta_cobro_cuenta_card_clabe = $parametrosArray['venta_cobro_cuenta_card_clabe'];
        $venta_cobro_clave_referencia = $parametrosArray['venta_cobro_clave_referencia'];
        $venta_cobro_moneda_code = $parametrosArray['venta_cobro_moneda_code'];
        $venta_cobro_moneda_decimales = $parametrosArray['venta_cobro_moneda_decimales'];
        $venta_cobro_importe = $parametrosArray['venta_cobro_importe'];
        $venta_cobro_tipo_cambio = $parametrosArray['venta_cobro_tipo_cambio'];
        $venta_cobro_concepto = $parametrosArray['venta_cobro_concepto'];

        if (
          isset($token_venta_generada) && !empty($token_venta_generada) && isset($venta_cobro_bool_generar) && is_bool($venta_cobro_bool_generar) &&
          isset($venta_cobro_forma_generada) && !empty($venta_cobro_forma_generada) && preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_forma_generada) &&
          isset($venta_cobro_fecha) && !empty($venta_cobro_fecha) && preg_match($JwtAuth->filtroFecha(), $venta_cobro_fecha) &&
          isset($venta_cobro_moneda_code) && !empty($venta_cobro_moneda_code) && preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_moneda_code) &&
          isset($venta_cobro_moneda_decimales) && !empty($venta_cobro_moneda_decimales) && preg_match($JwtAuth->filtroNumerico(), $venta_cobro_moneda_decimales) &&
          isset($venta_cobro_importe) && !empty($venta_cobro_importe) && preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_importe) &&
          isset($venta_cobro_tipo_cambio) && !empty($venta_cobro_tipo_cambio) && preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_tipo_cambio) &&
          isset($venta_cobro_concepto) && !empty($venta_cobro_concepto) && preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_concepto)
        ) {

          $selectEmp = DB::select(
            "SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$usuario->empresa_token, $usuario->user_token]
          );
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

          $selectIdentVenta = DB::table("ingr_ventas")->where("token_ventas", $token_venta_generada)->pluck("id")->first();

          $folioOrd = DB::select("SELECT IF(MAX(ord.folio_ordenCobro) IS NOT NULL,MAX(ord.folio_ordenCobro)+1,1) AS folio FROM fnzs_cobros_orden AS ord 
    				    JOIN main_empresas AS emp WHERE ord.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

          $token_ord_cob = $JwtAuth->encriptarToken($fecha_registro . end($selectEmp)->id . end($selectEmp)->userr, $selectIdentVenta . $venta_cobro_forma_generada);

          $nuevaOrd = new OrdenCobroModelo();
          $nuevaOrd->token_ordenCobro = $token_ord_cob;
          $nuevaOrd->folio_ordenCobro = $folioOrd[0]->folio;
          $nuevaOrd->fecha_sistema_ordenc = $fecha_registro;
          $nuevaOrd->factura_venta = $selectIdentVenta;
          $nuevaOrd->ord_cliente = 1;
          $nuevaOrd->autorizacion_cobro = TRUE;
          $nuevaOrd->fecha_autorizacion_cobro = $fecha_registro;
          $nuevaOrd->tentativa_cobro = $fecha_registro;
          $nuevaOrd->orden_terminada_bool = TRUE;
          $nuevaOrd->orden_terminada_fecha = $fecha_registro;
          $nuevaOrd->status = TRUE;
          $nuevaOrd->status_cobro = TRUE;
          $nuevaOrd->empresa = end($selectEmp)->id;
          $nuevaOrd->usuario_requiere = end($selectEmp)->userr;
          $saveOrd = $nuevaOrd->save();

          if ($saveOrd) {
            $selectIdentOrd = DB::table("fnzs_cobros_orden")->where("token_ordenCobro", $token_ord_cob)->pluck("id")->first();
            $folioCobro = DB::select("SELECT IF(MAX(cob.folio_cobros) IS NOT NULL,MAX(cob.folio_cobros)+1,1) AS folio FROM fnzs_cobros_cobro AS cob 
    				        JOIN main_empresas AS emp WHERE cob.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);
            $folio_cobro = "COB" . $JwtAuth->generarFolio($folioCobro[0]->folio);
            $token_cobRO = $JwtAuth->encriptarToken($fecha_registro . $selectIdentOrd . end($selectEmp)->userr, $selectIdentVenta . $venta_cobro_forma_generada);
            $insertCobro = DB::table("fnzs_cobros_cobro")->insert(
              array(
                "token_cobros" => $token_cobRO,
                "folio_cobros" => $folioCobro[0]->folio,
                "fecha_sistema" => $fecha_registro,
                "fecha_cobro" => $fecha_registro,
                "orden_cobro" => $selectIdentOrd,
                "forma_cobro_clave" => $venta_cobro_forma_generada,
                "banco_clave" => $venta_cobro_banco != "" ? $venta_cobro_banco : NULL,
                "cuenta_bancaria_clave" => $venta_cobro_cuenta_card_clabe != "" ? $venta_cobro_cuenta_card_clabe : NULL,
                "num_referencia_cobro" => $venta_cobro_clave_referencia != "" ? $venta_cobro_clave_referencia : NULL,
                "monto_cobro" => $venta_cobro_importe,
                "tipo_cambio" => $venta_cobro_tipo_cambio,
                "moneda_clave" => $venta_cobro_moneda_code,
                "moneda_decimales" => $venta_cobro_moneda_decimales,
                "cliente" => 1,
                "venta" => $selectIdentVenta,
                "concepto" => $JwtAuth->encriptar($venta_cobro_concepto),
                "personal_cobro" => end($selectEmp)->userr,
                "personal_autoriza" => end($selectEmp)->userr,
                "empresa" => end($selectEmp)->id,
                "status_cobro" => TRUE,
              )
            );

            if ($insertCobro) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Cobro registrado exitosamente con el folio " . $folio_cobro,
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Cobro no registrado, por favor verifique su información o comuniquese a soporte"
              );
            }
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Cobro no registrado, por favor verifique su información o comuniquese a soporte"
            );
          }
        } else {
          $mensajeError = "";
          if (!isset($token_venta_generada) || empty($token_venta_generada)) $mensajeError = "Error al seleccionar venta para cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_bool_generar) || !is_bool($venta_cobro_bool_generar)) $mensajeError = "Error al decidir si desea generar cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_fecha) || empty($venta_cobro_fecha) || !preg_match($JwtAuth->filtroFecha(), $venta_cobro_fecha)) $mensajeError = "Error al seleccionar fecha de cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_moneda_code) || empty($venta_cobro_moneda_code) || !preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_moneda_code)) $mensajeError = "Error al seleccionar moneda para cobro (código de moneda), por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_moneda_decimales) || empty($venta_cobro_moneda_decimales) || !preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_moneda_decimales)) $mensajeError = "Error al seleccionar moneda para cobro (decimales), por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_importe) || empty($venta_cobro_importe) || !preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_importe)) $mensajeError = "Error al registrar monto de cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_tipo_cambio) || empty($venta_cobro_tipo_cambio) || !preg_match($JwtAuth->filtroNumericoSimple(), $venta_cobro_tipo_cambio)) $mensajeError = "Error al registrar tipo de cambio para cobro, por favor verifique su información o comuniquese a soporte";
          if (!isset($venta_cobro_concepto) || empty($venta_cobro_concepto) || !preg_match($JwtAuth->filtroAlfabetico(), $venta_cobro_concepto)) $mensajeError = "Error al seleccionar concepto de cobro, por favor verifique su información o comuniquese a soporte";

          $dataMensaje = array(
            "status" => "error",
            "code" => 400,
            "message" => $mensajeError
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function imperialAccessVentas(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'imperial_code' => 'required|string',
        'imperial_pass' => 'required|string',
        'folio_venta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $imperial_code = $parametrosArray['imperial_code'];
        $imperial_pass = $parametrosArray['imperial_pass'];
        $folio_venta = $parametrosArray['folio_venta'];

        if (isset($imperial_code) && !empty($imperial_code) && isset($imperial_pass) && !empty($imperial_pass) && isset($folio_venta) && !empty($folio_venta)) {
          $imperial_pass_cred = $JwtAuth->encriptarCredenciales($imperial_pass);
          $imperialQuery = VentasModelo::where('access_code', '=', $JwtAuth->encriptar($imperial_code))->first();
          //.echo $imperialQuery->folio_venta." ".$imperial_pass_cred;
          if ($imperialQuery) {
            $folio_vent = "VENT-" . ($imperialQuery->post_folio == NULL ? $JwtAuth->generarFolio($imperialQuery->folio_venta) : $JwtAuth->generarFolio($imperialQuery->folio_venta) . "-" . $imperialQuery->post_folio);
            if (Hash::check($JwtAuth->encriptar($imperial_pass), $imperialQuery->access_pass) && $folio_vent == $folio_venta) {
              $tkn_session = array(
                "token_ventas" => $imperialQuery->token_ventas,
                "imperial_code" => $JwtAuth->encriptar($imperial_code),
                "imperial_pass" => $JwtAuth->encriptar($imperial_pass)
              );
              $jwt = JWT::encode($tkn_session, $this->clave_cifrado, "HS256");

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "large_token_access" => $jwt,
                "modulo_code" => "dEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=",
                "message" => "Usuario registrado, ¡Bienvenido!"
              );
            } else {
              $errorMensaje = "";
              if (!Hash::check($JwtAuth->encriptar($imperial_pass), $imperialQuery->access_pass)) $errorMensaje = "Contraseña incorrecta";
              if ($folio_vent != $folio_venta) $errorMensaje = "Folio de venta no encontrado";
              $dataMensaje = array("status" => "error", "code" => 200, "message" => $errorMensaje . ", por favor verifique su información o comuniquese a soporte");
            }
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Código de acceso incorrecto");
          }
        } else {
          $error_alerta = "";
          if (!isset($imperial_code) || empty($imperial_code)) $error_alerta = "Código de acceso incorrecto, por favor verifique su información o comuniquese a soporte";
          if (!isset($imperial_pass) || empty($imperial_pass)) $error_alerta = "Contraseña incorrecta, por favor verifique su información o comuniquese a soporte";
          if (!isset($folio_venta) || empty($folio_venta)) $error_alerta = "Folio de venta incorrecto, por favor verifique su información o comuniquese a soporte";
          $dataMensaje = array("status" => "success", "code" => 200, "message" => $error_alerta);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoVentasMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayVentasCatalogo = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $counter = 0;
        $folioVenta = VentasModelo::join("sos_last_folders_series AS ser", "ingr_ventas.serie", "=", "ser.id")
          ->join("sos_last_folders_subseries AS subser", "ingr_ventas.subserie", "=", "subser.id")
          ->join("sos_puntodeventa_catalogos AS pv", "ingr_ventas.punto_venta", "=", "pv.id")
          ->join("ingr_catalogo_clientes AS cl", "ingr_ventas.cliente", "=", "cl.id")
          ->join("sos_personas AS client", "cl.cliente", "client.id")
          ->join("main_empresas AS emp", "ingr_ventas.vendedor_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($folioVenta as $vVent) {
          //da_te_default_timezone_set($vVent->zona_horaria);
          $folio_vent_general = $vVent->serie_principal . "-" . ($vVent->post_folio == NULL ? $JwtAuth->generarFolio($vVent->folio_venta) : $JwtAuth->generarFolio($vVent->folio_venta) . "-" . $vVent->post_folio);
          $folio_vent_serie = $vVent->serie_principal . "-" . $vVent->subserie . "-" . $JwtAuth->generarFolio($vVent->numero);

          $importe_total_venta = 0.00;

          $queryDetVenta = VentasModelo::join("ingr_ventas_detalle AS vendet", "ingr_ventas.id", "vendet.venta_raiz")
            ->join("cont_impuestos_esquema AS impesq", "vendet.esquema_impuestos", "impesq.id")
            ->where([
              "ingr_ventas.token_ventas" => $vVent->token_ventas
            ])->get();

          foreach ($queryDetVenta as $vDet) {
            $precio_bruto = $vDet->precio_bruto;
            $cantidad = $vDet->cantidad;
            $descuento_total = $vDet->descuento_total;
            $promocion_total = $vDet->promocion_total;
            $precioInicial = $precio_bruto * $cantidad;
            $precioSubtotal = $precioInicial - $descuento_total;
            $traslados = 0;
            $retenciones = 0;
            $queryImpuestos = DB::table("ingr_ventas_detalle AS det")
              ->join("ingr_ventas_detalle_impuestos AS rel", "det.id", "rel.detalle_venta")
              ->join("cont_impuestos_catalogo AS imp", "rel.impuesto_relacionado", "imp.id")
              ->where(["det.venta_detalle_token" => $vDet->venta_detalle_token])->get();
            //echo count($queryImpuestos);
            foreach ($queryImpuestos as $vImp) {
              $importe_impuesto_aplicado = 0.00;
              $base_aplicable = $vImp->base;
              $tipo_impuesto = $vImp->tipo_impuesto;
              //echo $tipo_impuesto;
              $calculo = $vImp->calculo;
              $importe = $vImp->importe;
              //echo $importe;
              switch ($calculo) {
                case "tasa":
                  $importe_impuesto_aplicado = $precioSubtotal * ($importe / 100);
                  break;
                case "cuota":
                  $importe_impuesto_aplicado = $importe;
                  break;
                default:
                  break;
              }

              switch ($tipo_impuesto) {
                case "tras":
                  $traslados = $traslados + $importe_impuesto_aplicado;
                  break;
                case "rete":
                  $retenciones = $retenciones + $importe_impuesto_aplicado;
                  break;
                default:
                  break;
              }
            }

            $importe_total = $precioSubtotal + $traslados - $retenciones;
            $importe_total_venta = $importe_total_venta + $importe_total;
          }

          $row = array(
            "counter" => $counter,
            "token_ventas" => $vVent->token_ventas,
            "folio_vent_general" => $folio_vent_general,
            "folio_vent_serie" => $folio_vent_serie,
            "fecha" => gmdate('Y-m-d H:i:s', $vVent->fecha_registro_venta),
            //punto_venta
            "punto_venta_token" => $vVent->token_puntodeventa,
            "punto_venta_alias" => $JwtAuth->desencriptar($vVent->pv_alias),
            //cliente
            "clientes_token" => $vVent->token_cat_clientes,
            "clientes_nombre" => $JwtAuth->desencriptar($vVent->nombre_extendido),
            //moneda
            "moneda_code" => $vVent->moneda_code,
            "moneda_decimales" => $vVent->moneda_decimales,
            "tipo_cambio_venta" => $vVent->tipo_cambio_venta,
            "moneda_conv_code" => $vVent->moneda_conv_code,
            "moneda_conv_decimales" => $vVent->moneda_conv_decimales,
            "importe_total" => "$" . number_format($importe_total_venta, $vVent->moneda_decimales, '.', ','),
            "importe_total_convert" => "$" . number_format($importe_total_venta / $vVent->tipo_cambio_venta, $vVent->moneda_conv_decimales, '.', ','),
            "venta_cancelada" => $vVent->cancelado == TRUE ? true : false,
            "razon_cancelar" => "",
          );
          $arrayVentasCatalogo[] = $row;
          ++$counter;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "datosVenta" => $arrayVentasCatalogo,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoVentas(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayVentasCatalogo = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $folioVenta = VentasModelo::join("sos_last_folders_series AS ser", "ingr_ventas.serie", "=", "ser.id")
          ->join("sos_last_folders_subseries AS subser", "ingr_ventas.subserie", "=", "subser.id")
          ->join("main_empresas AS emp", "ingr_ventas.vendedor_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($folioVenta as $vVent) {
          //da_te_default_timezone_set($vVent->zona_horaria);
          $folio_vent_general = $vVent->serie_principal . "-" . ($vVent->post_folio == NULL ? $JwtAuth->generarFolio($vVent->folio_venta) : $JwtAuth->generarFolio($vVent->folio_venta) . "-" . $vVent->post_folio);
          $folio_vent_serie = $vVent->serie_principal . "-" . $vVent->subserie . "-" . $JwtAuth->generarFolio($vVent->numero);

          $listaClientes = ClientesModelo::join("sos_personas AS client", "ingr_catalogo_clientes.cliente", "client.id")
            //->join("teci_pais AS ps","client.nacionalidad","ps.id")
            //->join("teci_forma_pago AS pago","ingr_catalogo_clientes.forma_pago","pago.id")
            ->join("main_empresas AS emp", "ingr_catalogo_clientes.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'ingr_catalogo_clientes.id' => $vVent->cliente,
              'ingr_catalogo_clientes.status' => true,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();
          $resCliente = array();
          foreach ($listaClientes as $resListClient) {
            $nombreCl = $JwtAuth->desencriptar($resListClient->nombre_extendido);

            $arrayForeach = array(
              "token" => $resListClient->token_cat_clientes,
              "folio" => $JwtAuth->generar($resListClient->folio),
              "listaPrecios" => $resListClient->listaPrecios,
              "nombre" => $nombreCl,
            );
            $resCliente[] = $arrayForeach;
          }

          $arraInterno = array(
            "token_ventas" => $vVent->token_ventas,
            "folio_vent_general" => $folio_vent_general,
            "folio_vent_serie" => $folio_vent_serie,
            "fecha" => gmdate('Y-m-d H:i:s', $vVent->fecha_registro_venta),
            "cliente" => $resCliente,
            "lugar_entrega" => $vVent->lugar_entrega,
            "responsable" => $vVent->responsable,
            "caja" => $vVent->caja,
            "created_at" => $vVent->created_at,
            "updated_at" => $vVent->updated_at,
            "vendedor" => $vVent->vendedor,
          );
          $arrayVentasCatalogo[] = $arraInterno;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "datosVenta" => $arrayVentasCatalogo,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleVentaInsideMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataVenta = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_ventas" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_ventas = $parametrosArray["token_ventas"];
        $queryVentas = VentasModelo::where(['token_ventas' => $token_ventas])->get();

        if (count($queryVentas) > 0) {
          foreach ($queryVentas as $vVent) {
            $folio_vent = "VENT-" . ($vVent->post_folio == NULL ? $JwtAuth->generarFolio($vVent->folio_venta) : $JwtAuth->generarFolio($vVent->folio_venta) . "-" . $vVent->post_folio);
            $moneda_code = $vVent->moneda_code;
            $moneda_decimales = $vVent->moneda_decimales;
            $tipo_cambio_venta = $vVent->tipo_cambio_venta;
            $moneda_conv_code = $vVent->moneda_conv_code;
            $moneda_conv_decimales = $vVent->moneda_conv_decimales;
            //punto de venta (sos_puntodeventa_catalogos)
            $queryPVenta = VentasModelo::join("sos_puntodeventa_catalogos AS pv", "ingr_ventas.punto_venta", "pv.id")
              //->where(["ingr_ventas.token_ventas" => $vVent->token_ventas])->get();
              ->where("ingr_ventas.token_ventas", $vVent->token_ventas)->pluck("pv.pv_alias")->first();
            $punto_venta_nombre = $JwtAuth->desencriptar($queryPVenta);
            //nombre del cliente (ingr_catalogo_clientes)
            $queryNameCli = VentasModelo::join("ingr_catalogo_clientes AS catkli", "ingr_ventas.cliente", "catkli.id")
              ->join("sos_personas AS people", "catkli.cliente", "people.id")
              ->where("ingr_ventas.token_ventas", $vVent->token_ventas)->pluck("people.nombre_extendido")->first();
            $nombre_cliente = $JwtAuth->desencriptar($queryNameCli);
            //detalle de venta (ingr_ventas_detalle,in_egr_catalogo_productos,in_egr_catalogo_servicios,cont_impuestos_esquema,ingr_ventas_detalle_impuestos)
            $venta_importes_subtotal = 0.00;
            $venta_importes_descuento = 0.00;
            $venta_importes_trasladados = 0.00;
            $venta_importes_retenidos = 0.00;
            $venta_importes_total = 0.00;

            $detalleSave = array();
            $queryDetVenta = VentasModelo::join("ingr_ventas_detalle AS vendet", "ingr_ventas.id", "vendet.venta_raiz")
              ->join("cont_impuestos_esquema AS impesq", "vendet.esquema_impuestos", "impesq.id")
              ->where([
                "ingr_ventas.token_ventas" => $vVent->token_ventas
              ])->get();

            foreach ($queryDetVenta as $vDet) {
              $precio_bruto = $vDet->precio_bruto;
              $cantidad = $vDet->cantidad;
              $descuento_total = $vDet->descuento_total;
              $promocion_total = $vDet->promocion_total;
              $precioInicial = $precio_bruto * $cantidad;
              $precioSubtotal = $precioInicial - $descuento_total;
              $traslados = 0;
              $retenciones = 0;
              $queryImpuestos = DB::table("ingr_ventas_detalle AS det")
                ->join("ingr_ventas_detalle_impuestos AS rel", "det.id", "rel.detalle_venta")
                ->join("cont_impuestos_catalogo AS imp", "rel.impuesto_relacionado", "imp.id")
                ->where(["det.venta_detalle_token" => $vDet->venta_detalle_token])->get();
              //echo count($queryImpuestos);
              foreach ($queryImpuestos as $vImp) {
                $importe_impuesto_aplicado = 0.00;
                $base_aplicable = $vImp->base;
                $tipo_impuesto = $vImp->tipo_impuesto;
                //echo $tipo_impuesto;
                $calculo = $vImp->calculo;
                $importe = $vImp->importe;
                //echo $importe;
                switch ($calculo) {
                  case "tasa":
                    $importe_impuesto_aplicado = $precioSubtotal * ($importe / 100);
                    break;
                  case "cuota":
                    $importe_impuesto_aplicado = $importe;
                    break;
                  default:
                    break;
                }

                switch ($tipo_impuesto) {
                  case "tras":
                    $traslados = $traslados + $importe_impuesto_aplicado;
                    break;
                  case "rete":
                    $retenciones = $retenciones + $importe_impuesto_aplicado;
                    break;
                  default:
                    break;
                }
              }

              $importe_total = $precioSubtotal + $traslados - $retenciones;
              $venta_importes_subtotal = $venta_importes_subtotal + $precioSubtotal;
              $venta_importes_descuento = $venta_importes_descuento + $descuento_total;
              $venta_importes_trasladados = $venta_importes_trasladados + $traslados;
              $venta_importes_retenidos = $venta_importes_retenidos + $retenciones;
              $venta_importes_total = $venta_importes_total + $importe_total;

              $listaEsquemas = array();
              $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
                ->join("ingr_ventas_detalle AS det", "esqImp.id", "det.esquema_impuestos")
                ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
                ->where(["esqImp.status_esquema" => TRUE, "det.venta_detalle_token" => $vDet->venta_detalle_token])->get();

              foreach ($queryEsquema as $value) {
                //da_te_default_timezone_set($value->zona_horaria);
                $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
                $listaImpuestos = array();
                $queryImpVinc = DB::table("cont_impuestos_esquema AS esqImp")
                  ->join('cont_impuestos_esquema_vinculo AS vinc', 'esqImp.id', 'vinc.esquema_vinculado')
                  ->join('cont_impuestos_catalogo AS catImp', 'vinc.impuesto_vinculado', 'catImp.id')
                  ->where(['esqImp.esquema_token' => $value->esquema_token])
                  ->get();

                if (count($queryImpVinc) > 0) {
                  foreach ($queryImpVinc as $impCat) {
                    $folio_impuesto = 'IMP-' . ($impCat->post_folio == NULL ? $JwtAuth->generarFolio($impCat->folio_impuesto) : $JwtAuth->generarFolio($impCat->folio_impuesto) . '-' . $impCat->post_folio);
                    $importe_imp = $impCat->calculo == "cuota" ? "$" . $impCat->importe : $impCat->importe . "%";
                    $data_tipo_cambio = "";
                    $data_monedas_tkn = ""; //token_monedas
                    $data_monedas_codigo = ""; //codigo
                    $data_monedas_moneda = ""; //moneda
                    $data_monedas_decimales = ""; //decimales

                    if ($impCat->calculo == "cuota") {
                      //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
                      $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
                        ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $impCat->token_catalogo_impuesto])->get();
                      foreach ($queryCurrencyImp as $vMon) {
                        $data_monedas_tkn = $vMon->token_monedas;
                        $data_monedas_codigo = $vMon->codigo;
                        $data_monedas_moneda = $vMon->moneda;
                        $data_monedas_decimales = $vMon->decimales;
                        $data_tipo_cambio = "$" . number_format($impCat->tipo_cambio_imp, $vMon->decimales, '.', ',');
                      }
                    }
                    $arrayforeach = array(
                      "token_catalogo_impuesto" => $impCat->token_catalogo_impuesto,
                      "fecha_registro" => gmdate('Y-m-d H:i:s', $impCat->fecha_registro),
                      "folio_impuesto" => $folio_impuesto,
                      "abreviacion_impuesto" => $JwtAuth->desencriptar($impCat->abreviacion_impuesto),
                      "concepto_impuesto" => $JwtAuth->desencriptar($impCat->concepto_impuesto),
                      "modulo" => $impCat->modulo != NULL ? $JwtAuth->desencriptar($impCat->modulo) : null,
                      "nivel_aplicacion" => $impCat->nivel_aplicacion,
                      "catalogo_sat" => $impCat->catalogo_sat != NULL ? $impCat->catalogo_sat : null,
                      "tipo_impuesto" => $impCat->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
                      "calculo" => $impCat->calculo,
                      "importe" => $impCat->importe,
                      "txtimporte" => $importe_imp,
                      "valor_para_venta" => 0.00,
                      "tipo_cambio" => $data_tipo_cambio,
                      //moneda_registrada_imp
                      "monedas_tkn" => $data_monedas_tkn,
                      "monedas_codigo" => $data_monedas_codigo,
                      "monedas_moneda" => $data_monedas_moneda,
                      "monedas_decimales" => $data_monedas_decimales,
                      "base_aplicable" => $impCat->base,
                      "desglose" => $impCat->desglose == TRUE ? true : false,
                      "gl_por_pagarcobrar" => $impCat->gl_por_pagarcobrar != NULL ? $impCat->gl_por_pagarcobrar : null,
                      "gl_pagada_o_cobrada" => $impCat->gl_pagada_o_cobrada != NULL ? $impCat->gl_pagada_o_cobrada : null,
                      "observaciones" => $JwtAuth->desencriptar($impCat->observaciones),
                      "habilitado" => $impCat->habilitado_imp == TRUE ? true : false,
                      "vinculacion" => false,
                    );
                    $listaImpuestos[] = $arrayforeach;
                  }

                  $arrayforeach = array(
                    "esquema_token" => $value->esquema_token,
                    "esquema_folio" => $folio_esquema,
                    "esquema_date_insert" => gmdate('Y-m-d H:i:s', $value->esquema_date_insert),
                    "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
                    "impuestos" => $listaImpuestos,
                    "habilitado" => true
                  );
                  $listaEsquemas[] = $arrayforeach;
                }
              }

              $rowDet = array(
                "venta_detalle_token" => $vDet->venta_detalle_token,
                "producto" => "",
                "servicio" => "",
                "precio_bruto" => "$" . number_format($precio_bruto, $moneda_decimales, '.', ','),
                "precio_bruto_convert" => "$" . number_format($precio_bruto / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "cantidad" => $cantidad,
                "descuento_total" => "$" . number_format($descuento_total, $moneda_decimales, '.', ','),
                "descuento_total_convert" => "$" . number_format($descuento_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "subtotalAfterDescuentos" => "$" . number_format($precioSubtotal, $moneda_decimales, '.', ','),
                "subtotalAfterDescuentos_convert" => "$" . number_format($precioSubtotal / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "importe_total" => "$" . number_format($importe_total, $moneda_decimales, '.', ','),
                "importe_total_convert" => "$" . number_format($importe_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                //"promocion_total" => $promocion_total,
                "esquema_impuestos" => $listaEsquemas,
              );
              $detalleSave[] = $rowDet;
            }
            $row = array(
              "token_ventas" => $vVent->token_ventas,
              "folio_venta" => $folio_vent,
              "punto_venta" => $punto_venta_nombre,
              "cliente" => $nombre_cliente,
              "moneda_code" => $moneda_code,
              "moneda_decimales" => $moneda_decimales,
              "tipo_cambio_venta" => $tipo_cambio_venta,
              "moneda_conv_code" => $moneda_conv_code,
              "moneda_conv_decimales" => $moneda_conv_decimales,
              "mx_venta_subtotal" => "$" . number_format($venta_importes_subtotal, $moneda_decimales, '.', ','),
              "cnvr_venta_subtotal" => "$" . number_format($venta_importes_subtotal / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_descuento" => "$" . number_format($venta_importes_descuento, $moneda_decimales, '.', ','),
              "cnvr_venta_descuento" => "$" . number_format($venta_importes_descuento / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_traslados" => "$" . number_format($venta_importes_trasladados, $moneda_decimales, '.', ','),
              "cnvr_venta_traslados" => "$" . number_format($venta_importes_trasladados / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_retenciones" => "$" . number_format($venta_importes_retenidos, $moneda_decimales, '.', ','),
              "cnvr_venta_retenciones" => "$" . number_format($venta_importes_retenidos / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_importe_total" => "$" . number_format($venta_importes_total, $moneda_decimales, '.', ','),
              "cnvr_venta_importe_total" => "$" . number_format($venta_importes_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "detalle_venta" => $detalleSave,
            );
            $dataVenta[] = $row;
            $dataMensaje = array('status' => 'success', 'code' => 200, 'dataVenta' => $dataVenta);
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "Código de acceso incorrecto");
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleVentaMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataVenta = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $queryVentas = VentasModelo::where(['token_ventas' => $usuario->token_ventas, 'access_code' => $usuario->imperial_code])->get();

        if (count($queryVentas) > 0) {
          foreach ($queryVentas as $vVent) {
            $folio_vent = "VENT-" . ($vVent->post_folio == NULL ? $JwtAuth->generarFolio($vVent->folio_venta) : $JwtAuth->generarFolio($vVent->folio_venta) . "-" . $vVent->post_folio);
            $moneda_code = $vVent->moneda_code;
            $moneda_decimales = $vVent->moneda_decimales;
            $tipo_cambio_venta = $vVent->tipo_cambio_venta;
            $moneda_conv_code = $vVent->moneda_conv_code;
            $moneda_conv_decimales = $vVent->moneda_conv_decimales;
            //punto de venta (sos_puntodeventa_catalogos)
            $queryPVenta = VentasModelo::join("sos_puntodeventa_catalogos AS pv", "ingr_ventas.punto_venta", "pv.id")
              //->where(["ingr_ventas.token_ventas" => $vVent->token_ventas])->get();
              ->where("ingr_ventas.token_ventas", $vVent->token_ventas)->pluck("pv.pv_alias")->first();
            $punto_venta_nombre = $JwtAuth->desencriptar($queryPVenta);
            //nombre del cliente (ingr_catalogo_clientes)
            $queryNameCli = VentasModelo::join("ingr_catalogo_clientes AS catkli", "ingr_ventas.cliente", "catkli.id")
              ->join("sos_personas AS people", "catkli.cliente", "people.id")
              ->where("ingr_ventas.token_ventas", $vVent->token_ventas)->pluck("people.nombre_extendido")->first();
            $nombre_cliente = $JwtAuth->desencriptar($queryNameCli);
            //detalle de venta (ingr_ventas_detalle,in_egr_catalogo_productos,in_egr_catalogo_servicios,cont_impuestos_esquema,ingr_ventas_detalle_impuestos)
            $venta_importes_subtotal = 0.00;
            $venta_importes_descuento = 0.00;
            $venta_importes_trasladados = 0.00;
            $venta_importes_retenidos = 0.00;
            $venta_importes_total = 0.00;

            $detalleSave = array();
            $queryDetVenta = VentasModelo::join("ingr_ventas_detalle AS vendet", "ingr_ventas.id", "vendet.venta_raiz")
              ->join("cont_impuestos_esquema AS impesq", "vendet.esquema_impuestos", "impesq.id")
              ->where([
                "ingr_ventas.token_ventas" => $vVent->token_ventas
              ])->get();

            foreach ($queryDetVenta as $vDet) {
              $precio_bruto = $vDet->precio_bruto;
              $cantidad = $vDet->cantidad;
              $descuento_total = $vDet->descuento_total;
              $promocion_total = $vDet->promocion_total;
              $precioInicial = $precio_bruto * $cantidad;
              $precioSubtotal = $precioInicial - $descuento_total;
              $traslados = 0;
              $retenciones = 0;
              $queryImpuestos = DB::table("ingr_ventas_detalle AS det")
                ->join("ingr_ventas_detalle_impuestos AS rel", "det.id", "rel.detalle_venta")
                ->join("cont_impuestos_catalogo AS imp", "rel.impuesto_relacionado", "imp.id")
                ->where(["det.venta_detalle_token" => $vDet->venta_detalle_token])->get();
              //echo count($queryImpuestos);
              foreach ($queryImpuestos as $vImp) {
                $importe_impuesto_aplicado = 0.00;
                $base_aplicable = $vImp->base;
                $tipo_impuesto = $vImp->tipo_impuesto;
                //echo $tipo_impuesto;
                $calculo = $vImp->calculo;
                $importe = $vImp->importe;
                //echo $importe;
                switch ($calculo) {
                  case "tasa":
                    $importe_impuesto_aplicado = $precioSubtotal * ($importe / 100);
                    break;
                  case "cuota":
                    $importe_impuesto_aplicado = $importe;
                    break;
                  default:
                    break;
                }

                switch ($tipo_impuesto) {
                  case "tras":
                    $traslados = $traslados + $importe_impuesto_aplicado;
                    break;
                  case "rete":
                    $retenciones = $retenciones + $importe_impuesto_aplicado;
                    break;
                  default:
                    break;
                }
              }

              $importe_total = $precioSubtotal + $traslados - $retenciones;
              $venta_importes_subtotal = $venta_importes_subtotal + $precioSubtotal;
              $venta_importes_descuento = $venta_importes_descuento + $descuento_total;
              $venta_importes_trasladados = $venta_importes_trasladados + $traslados;
              $venta_importes_retenidos = $venta_importes_retenidos + $retenciones;
              $venta_importes_total = $venta_importes_total + $importe_total;

              $listaEsquemas = array();
              $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
                ->join("ingr_ventas_detalle AS det", "esqImp.id", "det.esquema_impuestos")
                ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
                ->where(["esqImp.status_esquema" => TRUE, "det.venta_detalle_token" => $vDet->venta_detalle_token])->get();

              foreach ($queryEsquema as $value) {
                //da_te_default_timezone_set($value->zona_horaria);
                $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
                $listaImpuestos = array();
                $queryImpVinc = DB::table("cont_impuestos_esquema AS esqImp")
                  ->join('cont_impuestos_esquema_vinculo AS vinc', 'esqImp.id', 'vinc.esquema_vinculado')
                  ->join('cont_impuestos_catalogo AS catImp', 'vinc.impuesto_vinculado', 'catImp.id')
                  ->where(['esqImp.esquema_token' => $value->esquema_token])
                  ->get();

                if (count($queryImpVinc) > 0) {
                  foreach ($queryImpVinc as $impCat) {
                    $folio_impuesto = 'IMP-' . ($impCat->post_folio == NULL ? $JwtAuth->generarFolio($impCat->folio_impuesto) : $JwtAuth->generarFolio($impCat->folio_impuesto) . '-' . $impCat->post_folio);
                    $importe_imp = $impCat->calculo == "cuota" ? "$" . $impCat->importe : $impCat->importe . "%";
                    $data_tipo_cambio = "";
                    $data_monedas_tkn = ""; //token_monedas
                    $data_monedas_codigo = ""; //codigo
                    $data_monedas_moneda = ""; //moneda
                    $data_monedas_decimales = ""; //decimales

                    if ($impCat->calculo == "cuota") {
                      //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
                      $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
                        ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $impCat->token_catalogo_impuesto])->get();
                      foreach ($queryCurrencyImp as $vMon) {
                        $data_monedas_tkn = $vMon->token_monedas;
                        $data_monedas_codigo = $vMon->codigo;
                        $data_monedas_moneda = $vMon->moneda;
                        $data_monedas_decimales = $vMon->decimales;
                        $data_tipo_cambio = "$" . number_format($impCat->tipo_cambio_imp, $vMon->decimales, '.', ',');
                      }
                    }
                    $arrayforeach = array(
                      "token_catalogo_impuesto" => $impCat->token_catalogo_impuesto,
                      "fecha_registro" => gmdate('Y-m-d H:i:s', $impCat->fecha_registro),
                      "folio_impuesto" => $folio_impuesto,
                      "abreviacion_impuesto" => $JwtAuth->desencriptar($impCat->abreviacion_impuesto),
                      "concepto_impuesto" => $JwtAuth->desencriptar($impCat->concepto_impuesto),
                      "modulo" => $impCat->modulo != NULL ? $JwtAuth->desencriptar($impCat->modulo) : null,
                      "nivel_aplicacion" => $impCat->nivel_aplicacion,
                      "catalogo_sat" => $impCat->catalogo_sat != NULL ? $impCat->catalogo_sat : null,
                      "tipo_impuesto" => $impCat->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
                      "calculo" => $impCat->calculo,
                      "importe" => $impCat->importe,
                      "txtimporte" => $importe_imp,
                      "valor_para_venta" => 0.00,
                      "tipo_cambio" => $data_tipo_cambio,
                      //moneda_registrada_imp
                      "monedas_tkn" => $data_monedas_tkn,
                      "monedas_codigo" => $data_monedas_codigo,
                      "monedas_moneda" => $data_monedas_moneda,
                      "monedas_decimales" => $data_monedas_decimales,
                      "base_aplicable" => $impCat->base,
                      "desglose" => $impCat->desglose == TRUE ? true : false,
                      "gl_por_pagarcobrar" => $impCat->gl_por_pagarcobrar != NULL ? $impCat->gl_por_pagarcobrar : null,
                      "gl_pagada_o_cobrada" => $impCat->gl_pagada_o_cobrada != NULL ? $impCat->gl_pagada_o_cobrada : null,
                      "observaciones" => $JwtAuth->desencriptar($impCat->observaciones),
                      "habilitado" => $impCat->habilitado_imp == TRUE ? true : false,
                      "vinculacion" => false,
                    );
                    $listaImpuestos[] = $arrayforeach;
                  }

                  $arrayforeach = array(
                    "esquema_token" => $value->esquema_token,
                    "esquema_folio" => $folio_esquema,
                    "esquema_date_insert" => gmdate('Y-m-d H:i:s', $value->esquema_date_insert),
                    "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
                    "impuestos" => $listaImpuestos,
                    "habilitado" => true
                  );
                  $listaEsquemas[] = $arrayforeach;
                }
              }

              $articulo_concepto = "";
              $articulo_logotipo = "";
              if ($vDet->producto != NULL && $vDet->servicio == NULL) {
                $articulo_logotipo = "./assets/images/catalogos/default_producto.jpg";
                $queryDataArt = DB::table("ingr_ventas_detalle AS vendet")
                  ->join("in_egr_catalogo_productos AS catprod", "vendet.producto", "catprod.id")
                  ->where("vendet.venta_detalle_token", $vDet->venta_detalle_token)->get();
                foreach ($queryDataArt as $dArt) {
                  $folio_prod = 'PROD-' . ($dArt->folio_sistema == "" || $dArt->folio_sistema == NULL ? 'TEMP-' . $JwtAuth->generarFolio($dArt->temps_folio) :
                    $JwtAuth->generarFolio($dArt->folio_sistema) . ($dArt->post_folio == NULL ? "" : '-' . $dArt->post_folio));
                  $articulo_concepto = $folio_prod . " " . $JwtAuth->desencriptar($dArt->producto);
                }
              } else {
                $articulo_logotipo = "./assets/images/catalogos/default_servicio.jpg";
                $queryDataArt = DB::table("ingr_ventas_detalle AS vendet")
                  ->join("in_egr_catalogo_servicios AS catserv", "vendet.servicio", "catserv.id")
                  ->where("vendet.venta_detalle_token", $vDet->venta_detalle_token)->get();
                foreach ($queryDataArt as $dArt) {
                  $folio_serv = 'SERV-' . ($dArt->folio_sistema == "" || $dArt->folio_sistema == NULL ? 'TEMP-' . $JwtAuth->generarFolio($dArt->temps_folio) :
                    $JwtAuth->generarFolio($dArt->folio_sistema) . ($dArt->post_folio == NULL ? "" : '-' . $dArt->post_folio));
                  $articulo_concepto = $folio_serv . " " . $JwtAuth->desencriptar($dArt->servicio);
                }
              }

              $rowDet = array(
                "venta_detalle_token" => $vDet->venta_detalle_token,
                "concepto" => $articulo_concepto,
                "logotipo" => $articulo_logotipo,
                "precio_bruto" => "$" . number_format($precio_bruto, $moneda_decimales, '.', ','),
                "precio_bruto_convert" => "$" . number_format($precio_bruto / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "cantidad" => $cantidad,
                "descuento_total" => "$" . number_format($descuento_total, $moneda_decimales, '.', ','),
                "descuento_total_convert" => "$" . number_format($descuento_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "subtotalAfterDescuentos" => "$" . number_format($precioSubtotal, $moneda_decimales, '.', ','),
                "subtotalAfterDescuentos_convert" => "$" . number_format($precioSubtotal / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "importe_total" => "$" . number_format($importe_total, $moneda_decimales, '.', ','),
                "importe_total_convert" => "$" . number_format($importe_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                //"promocion_total" => $promocion_total,
                "esquema_impuestos" => $listaEsquemas,
              );
              $detalleSave[] = $rowDet;
            }
            $row = array(
              "token_ventas" => $vVent->token_ventas,
              "folio_venta" => $folio_vent,
              "punto_venta" => $punto_venta_nombre,
              "cliente" => $nombre_cliente,
              "moneda_code" => $moneda_code,
              "moneda_decimales" => $moneda_decimales,
              "tipo_cambio_venta" => $tipo_cambio_venta,
              "moneda_conv_code" => $moneda_conv_code,
              "moneda_conv_decimales" => $moneda_conv_decimales,
              "mx_venta_subtotal" => "$" . number_format($venta_importes_subtotal, $moneda_decimales, '.', ','),
              "cnvr_venta_subtotal" => "$" . number_format($venta_importes_subtotal / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_descuento" => "$" . number_format($venta_importes_descuento, $moneda_decimales, '.', ','),
              "cnvr_venta_descuento" => "$" . number_format($venta_importes_descuento / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_traslados" => "$" . number_format($venta_importes_trasladados, $moneda_decimales, '.', ','),
              "cnvr_venta_traslados" => "$" . number_format($venta_importes_trasladados / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_retenciones" => "$" . number_format($venta_importes_retenidos, $moneda_decimales, '.', ','),
              "cnvr_venta_retenciones" => "$" . number_format($venta_importes_retenidos / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_importe_total" => "$" . number_format($venta_importes_total, $moneda_decimales, '.', ','),
              "cnvr_venta_importe_total" => "$" . number_format($venta_importes_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "detalle_venta" => $detalleSave,
            );
            $dataVenta[] = $row;
            $dataMensaje = array('status' => 'success', 'code' => 200, 'dataVenta' => $dataVenta);
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "Código de acceso incorrecto");
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cancelarVentaMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataVenta = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_ventas" => "required|string",
        "razones_cancelacion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 400,
          "message" => "La infomación que hemos recibido es invalida",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_ventas = $parametrosArray["token_ventas"];
        $razones_cancelacion = $parametrosArray["razones_cancelacion"];
        $queryVentas = VentasModelo::join("main_empresas AS emp", "ingr_ventas.vendedor_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(['token_ventas' => $token_ventas, 'emp.empresa_token' => $usuario->empresa_token, 'users.usuario_token' => $usuario->user_token])->get();

        if (count($queryVentas) > 0) {
          foreach ($queryVentas as $vVent) {
            $folio_vent = "VENT-" . ($vVent->post_folio == NULL ? $JwtAuth->generarFolio($vVent->folio_venta) : $JwtAuth->generarFolio($vVent->folio_venta) . "-" . $vVent->post_folio);
            $moneda_code = $vVent->moneda_code;
            $moneda_decimales = $vVent->moneda_decimales;
            $tipo_cambio_venta = $vVent->tipo_cambio_venta;
            $moneda_conv_code = $vVent->moneda_conv_code;
            $moneda_conv_decimales = $vVent->moneda_conv_decimales;
            //punto de venta (sos_puntodeventa_catalogos)
            $queryPVenta = VentasModelo::join("sos_puntodeventa_catalogos AS pv", "ingr_ventas.punto_venta", "pv.id")
              //->where(["ingr_ventas.token_ventas" => $vVent->token_ventas])->get();
              ->where("ingr_ventas.token_ventas", $vVent->token_ventas)->pluck("pv.pv_alias")->first();
            $punto_venta_nombre = $JwtAuth->desencriptar($queryPVenta);
            //nombre del cliente (ingr_catalogo_clientes)
            $queryNameCli = VentasModelo::join("ingr_catalogo_clientes AS catkli", "ingr_ventas.cliente", "catkli.id")
              ->join("sos_personas AS people", "catkli.cliente", "people.id")
              ->where("ingr_ventas.token_ventas", $vVent->token_ventas)->pluck("people.nombre_extendido")->first();
            $nombre_cliente = $JwtAuth->desencriptar($queryNameCli);
            //detalle de venta (ingr_ventas_detalle,in_egr_catalogo_productos,in_egr_catalogo_servicios,cont_impuestos_esquema,ingr_ventas_detalle_impuestos)
            $venta_importes_subtotal = 0.00;
            $venta_importes_descuento = 0.00;
            $venta_importes_trasladados = 0.00;
            $venta_importes_retenidos = 0.00;
            $venta_importes_total = 0.00;

            $detalleSave = array();
            $queryDetVenta = VentasModelo::join("ingr_ventas_detalle AS vendet", "ingr_ventas.id", "vendet.venta_raiz")
              ->join("cont_impuestos_esquema AS impesq", "vendet.esquema_impuestos", "impesq.id")
              ->where([
                "ingr_ventas.token_ventas" => $vVent->token_ventas
              ])->get();

            foreach ($queryDetVenta as $vDet) {
              $precio_bruto = $vDet->precio_bruto;
              $cantidad = $vDet->cantidad;
              $descuento_total = $vDet->descuento_total;
              $promocion_total = $vDet->promocion_total;
              $precioInicial = $precio_bruto * $cantidad;
              $precioSubtotal = $precioInicial - $descuento_total;
              $traslados = 0;
              $retenciones = 0;
              $queryImpuestos = DB::table("ingr_ventas_detalle AS det")
                ->join("ingr_ventas_detalle_impuestos AS rel", "det.id", "rel.detalle_venta")
                ->join("cont_impuestos_catalogo AS imp", "rel.impuesto_relacionado", "imp.id")
                ->where(["det.venta_detalle_token" => $vDet->venta_detalle_token])->get();
              //echo count($queryImpuestos);
              foreach ($queryImpuestos as $vImp) {
                $importe_impuesto_aplicado = 0.00;
                $base_aplicable = $vImp->base;
                $tipo_impuesto = $vImp->tipo_impuesto;
                //echo $tipo_impuesto;
                $calculo = $vImp->calculo;
                $importe = $vImp->importe;
                //echo $importe;
                switch ($calculo) {
                  case "tasa":
                    $importe_impuesto_aplicado = $precioSubtotal * ($importe / 100);
                    break;
                  case "cuota":
                    $importe_impuesto_aplicado = $importe;
                    break;
                  default:
                    break;
                }

                switch ($tipo_impuesto) {
                  case "tras":
                    $traslados = $traslados + $importe_impuesto_aplicado;
                    break;
                  case "rete":
                    $retenciones = $retenciones + $importe_impuesto_aplicado;
                    break;
                  default:
                    break;
                }
              }

              $importe_total = $precioSubtotal + $traslados - $retenciones;
              $venta_importes_subtotal = $venta_importes_subtotal + $precioSubtotal;
              $venta_importes_descuento = $venta_importes_descuento + $descuento_total;
              $venta_importes_trasladados = $venta_importes_trasladados + $traslados;
              $venta_importes_retenidos = $venta_importes_retenidos + $retenciones;
              $venta_importes_total = $venta_importes_total + $importe_total;

              $listaEsquemas = array();
              $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
                ->join("ingr_ventas_detalle AS det", "esqImp.id", "det.esquema_impuestos")
                ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
                ->where(["esqImp.status_esquema" => TRUE, "det.venta_detalle_token" => $vDet->venta_detalle_token])->get();

              foreach ($queryEsquema as $value) {
                //da_te_default_timezone_set($value->zona_horaria);
                $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
                $listaImpuestos = array();
                $queryImpVinc = DB::table("cont_impuestos_esquema AS esqImp")
                  ->join('cont_impuestos_esquema_vinculo AS vinc', 'esqImp.id', 'vinc.esquema_vinculado')
                  ->join('cont_impuestos_catalogo AS catImp', 'vinc.impuesto_vinculado', 'catImp.id')
                  ->where(['esqImp.esquema_token' => $value->esquema_token])
                  ->get();

                if (count($queryImpVinc) > 0) {
                  foreach ($queryImpVinc as $impCat) {
                    $folio_impuesto = 'IMP-' . ($impCat->post_folio == NULL ? $JwtAuth->generarFolio($impCat->folio_impuesto) : $JwtAuth->generarFolio($impCat->folio_impuesto) . '-' . $impCat->post_folio);
                    $importe_imp = $impCat->calculo == "cuota" ? "$" . $impCat->importe : $impCat->importe . "%";
                    $data_tipo_cambio = "";
                    $data_monedas_tkn = ""; //token_monedas
                    $data_monedas_codigo = ""; //codigo
                    $data_monedas_moneda = ""; //moneda
                    $data_monedas_decimales = ""; //decimales

                    if ($impCat->calculo == "cuota") {
                      //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
                      $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
                        ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $impCat->token_catalogo_impuesto])->get();
                      foreach ($queryCurrencyImp as $vMon) {
                        $data_monedas_tkn = $vMon->token_monedas;
                        $data_monedas_codigo = $vMon->codigo;
                        $data_monedas_moneda = $vMon->moneda;
                        $data_monedas_decimales = $vMon->decimales;
                        $data_tipo_cambio = "$" . number_format($impCat->tipo_cambio_imp, $vMon->decimales, '.', ',');
                      }
                    }
                    $arrayforeach = array(
                      "token_catalogo_impuesto" => $impCat->token_catalogo_impuesto,
                      "fecha_registro" => gmdate('Y-m-d H:i:s', $impCat->fecha_registro),
                      "folio_impuesto" => $folio_impuesto,
                      "abreviacion_impuesto" => $JwtAuth->desencriptar($impCat->abreviacion_impuesto),
                      "concepto_impuesto" => $JwtAuth->desencriptar($impCat->concepto_impuesto),
                      "modulo" => $impCat->modulo != NULL ? $JwtAuth->desencriptar($impCat->modulo) : null,
                      "nivel_aplicacion" => $impCat->nivel_aplicacion,
                      "catalogo_sat" => $impCat->catalogo_sat != NULL ? $impCat->catalogo_sat : null,
                      "tipo_impuesto" => $impCat->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
                      "calculo" => $impCat->calculo,
                      "importe" => $impCat->importe,
                      "txtimporte" => $importe_imp,
                      "valor_para_venta" => 0.00,
                      "tipo_cambio" => $data_tipo_cambio,
                      //moneda_registrada_imp
                      "monedas_tkn" => $data_monedas_tkn,
                      "monedas_codigo" => $data_monedas_codigo,
                      "monedas_moneda" => $data_monedas_moneda,
                      "monedas_decimales" => $data_monedas_decimales,
                      "base_aplicable" => $impCat->base,
                      "desglose" => $impCat->desglose == TRUE ? true : false,
                      "gl_por_pagarcobrar" => $impCat->gl_por_pagarcobrar != NULL ? $impCat->gl_por_pagarcobrar : null,
                      "gl_pagada_o_cobrada" => $impCat->gl_pagada_o_cobrada != NULL ? $impCat->gl_pagada_o_cobrada : null,
                      "observaciones" => $JwtAuth->desencriptar($impCat->observaciones),
                      "habilitado" => $impCat->habilitado_imp == TRUE ? true : false,
                      "vinculacion" => false,
                    );
                    $listaImpuestos[] = $arrayforeach;
                  }

                  $arrayforeach = array(
                    "esquema_token" => $value->esquema_token,
                    "esquema_folio" => $folio_esquema,
                    "esquema_date_insert" => gmdate('Y-m-d H:i:s', $value->esquema_date_insert),
                    "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
                    "impuestos" => $listaImpuestos,
                    "habilitado" => true
                  );
                  $listaEsquemas[] = $arrayforeach;
                }
              }

              $rowDet = array(
                "venta_detalle_token" => $vDet->venta_detalle_token,
                "producto" => "",
                "servicio" => "",
                "precio_bruto" => "$" . number_format($precio_bruto, $moneda_decimales, '.', ','),
                "precio_bruto_convert" => "$" . number_format($precio_bruto / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "cantidad" => $cantidad,
                "descuento_total" => "$" . number_format($descuento_total, $moneda_decimales, '.', ','),
                "descuento_total_convert" => "$" . number_format($descuento_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "subtotalAfterDescuentos" => "$" . number_format($precioSubtotal, $moneda_decimales, '.', ','),
                "subtotalAfterDescuentos_convert" => "$" . number_format($precioSubtotal / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                "importe_total" => "$" . number_format($importe_total, $moneda_decimales, '.', ','),
                "importe_total_convert" => "$" . number_format($importe_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
                //"promocion_total" => $promocion_total,
                "esquema_impuestos" => $listaEsquemas,
              );
              $detalleSave[] = $rowDet;
            }
            $row = array(
              "token_ventas" => $vVent->token_ventas,
              "folio_venta" => $folio_vent,
              "punto_venta" => $punto_venta_nombre,
              "cliente" => $nombre_cliente,
              "moneda_code" => $moneda_code,
              "moneda_decimales" => $moneda_decimales,
              "tipo_cambio_venta" => $tipo_cambio_venta,
              "moneda_conv_code" => $moneda_conv_code,
              "moneda_conv_decimales" => $moneda_conv_decimales,
              "mx_venta_subtotal" => "$" . number_format($venta_importes_subtotal, $moneda_decimales, '.', ','),
              "cnvr_venta_subtotal" => "$" . number_format($venta_importes_subtotal / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_descuento" => "$" . number_format($venta_importes_descuento, $moneda_decimales, '.', ','),
              "cnvr_venta_descuento" => "$" . number_format($venta_importes_descuento / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_traslados" => "$" . number_format($venta_importes_trasladados, $moneda_decimales, '.', ','),
              "cnvr_venta_traslados" => "$" . number_format($venta_importes_trasladados / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_retenciones" => "$" . number_format($venta_importes_retenidos, $moneda_decimales, '.', ','),
              "cnvr_venta_retenciones" => "$" . number_format($venta_importes_retenidos / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "mx_venta_importe_total" => "$" . number_format($venta_importes_total, $moneda_decimales, '.', ','),
              "cnvr_venta_importe_total" => "$" . number_format($venta_importes_total / $tipo_cambio_venta, $moneda_conv_decimales, '.', ','),
              "detalle_venta" => $detalleSave,
            );
            $dataVenta[] = $row;
            $dataMensaje = array('status' => 'success', 'code' => 200, 'dataVenta' => $dataVenta, 'message' => 'test');
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "Código de acceso incorrecto");
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoVentasCanceladasMostrador(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayVentasCatalogo = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $counter = 0;
        $folioVenta = VentasModelo::join("sos_last_folders_series AS ser", "ingr_ventas.serie", "=", "ser.id")
          ->join("sos_last_folders_subseries AS subser", "ingr_ventas.subserie", "=", "subser.id")
          ->join("sos_puntodeventa_catalogos AS pv", "ingr_ventas.punto_venta", "=", "pv.id")
          ->join("ingr_catalogo_clientes AS cl", "ingr_ventas.cliente", "=", "cl.id")
          ->join("sos_personas AS client", "cl.cliente", "client.id")
          ->join("main_empresas AS emp", "ingr_ventas.vendedor_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'ingr_ventas.cancelado' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($folioVenta as $vVent) {
          //da_te_default_timezone_set($vVent->zona_horaria);
          $folio_vent_general = $vVent->serie_principal . "-" . ($vVent->post_folio == NULL ? $JwtAuth->generarFolio($vVent->folio_venta) : $JwtAuth->generarFolio($vVent->folio_venta) . "-" . $vVent->post_folio);
          $folio_vent_serie = $vVent->serie_principal . "-" . $vVent->subserie . "-" . $JwtAuth->generarFolio($vVent->numero);

          $importe_total_venta = 0.00;

          $queryDetVenta = VentasModelo::join("ingr_ventas_detalle AS vendet", "ingr_ventas.id", "vendet.venta_raiz")
            ->join("cont_impuestos_esquema AS impesq", "vendet.esquema_impuestos", "impesq.id")
            ->where([
              "ingr_ventas.token_ventas" => $vVent->token_ventas
            ])->get();

          foreach ($queryDetVenta as $vDet) {
            $precio_bruto = $vDet->precio_bruto;
            $cantidad = $vDet->cantidad;
            $descuento_total = $vDet->descuento_total;
            $promocion_total = $vDet->promocion_total;
            $precioInicial = $precio_bruto * $cantidad;
            $precioSubtotal = $precioInicial - $descuento_total;
            $traslados = 0;
            $retenciones = 0;
            $queryImpuestos = DB::table("ingr_ventas_detalle AS det")
              ->join("ingr_ventas_detalle_impuestos AS rel", "det.id", "rel.detalle_venta")
              ->join("cont_impuestos_catalogo AS imp", "rel.impuesto_relacionado", "imp.id")
              ->where(["det.venta_detalle_token" => $vDet->venta_detalle_token])->get();
            //echo count($queryImpuestos);
            foreach ($queryImpuestos as $vImp) {
              $importe_impuesto_aplicado = 0.00;
              $base_aplicable = $vImp->base;
              $tipo_impuesto = $vImp->tipo_impuesto;
              //echo $tipo_impuesto;
              $calculo = $vImp->calculo;
              $importe = $vImp->importe;
              //echo $importe;
              switch ($calculo) {
                case "tasa":
                  $importe_impuesto_aplicado = $precioSubtotal * ($importe / 100);
                  break;
                case "cuota":
                  $importe_impuesto_aplicado = $importe;
                  break;
                default:
                  break;
              }

              switch ($tipo_impuesto) {
                case "tras":
                  $traslados = $traslados + $importe_impuesto_aplicado;
                  break;
                case "rete":
                  $retenciones = $retenciones + $importe_impuesto_aplicado;
                  break;
                default:
                  break;
              }
            }

            $importe_total = $precioSubtotal + $traslados - $retenciones;
            $importe_total_venta = $importe_total_venta + $importe_total;
          }

          $row = array(
            "counter" => $counter,
            "token_ventas" => $vVent->token_ventas,
            "folio_vent_general" => $folio_vent_general,
            "folio_vent_serie" => $folio_vent_serie,
            "fecha" => gmdate('Y-m-d H:i:s', $vVent->fecha_registro_venta),
            //punto_venta
            "punto_venta_token" => $vVent->token_puntodeventa,
            "punto_venta_alias" => $JwtAuth->desencriptar($vVent->pv_alias),
            //cliente
            "clientes_token" => $vVent->token_cat_clientes,
            "clientes_nombre" => $JwtAuth->desencriptar($vVent->nombre_extendido),
            //moneda
            "moneda_code" => $vVent->moneda_code,
            "moneda_decimales" => $vVent->moneda_decimales,
            "tipo_cambio_venta" => $vVent->tipo_cambio_venta,
            "moneda_conv_code" => $vVent->moneda_conv_code,
            "moneda_conv_decimales" => $vVent->moneda_conv_decimales,
            "importe_total" => "$" . number_format($importe_total_venta, $vVent->moneda_decimales, '.', ','),
            "importe_total_convert" => "$" . number_format($importe_total_venta / $vVent->tipo_cambio_venta, $vVent->moneda_conv_decimales, '.', ','),
          );
          $arrayVentasCatalogo[] = $row;
          ++$counter;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "datosVenta" => $arrayVentasCatalogo,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cargaArticulosVenta(Request $request)
  {
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

        $queryEmp = DB::select("SELECT emp.id,emp.e_moneda_code FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        foreach ($queryEmp as $vEmp) {
          $moneda_decimales = '';
          $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaMonedas');
          if ($response->successful()) {
            $datos = $response->json();
            $cantidadRegistros = is_array($datos) ? count($datos) : 0;
            $indice = array_search($vEmp->e_moneda_code, array_column($datos["monedas"], "code"));
            $moneda_decimales = $datos["monedas"][$indice]["decimales"];
            //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
          }

          $catProdServ = DB::select(
            "SELECT total.token_articulo,total.identificador,total.concepto,total.clasificacion,
            total.genero,total.folio,total.sat_clave_code,total.precioBase,total.unidad_medida,total.root_tkn,total.fecha_alta
            FROM ((SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,
              concat(catprod.producto,'-',catprod.marca) AS concepto,catprod.clasificacion,gen.folio_genero AS genero,
              catprod.folio_sistema AS folio,catprod.post_folio,catprod.sat_clave_code,ROUND(detprice.precio,?) AS precioBase,
              catprod.unidad_medida_salida_clave as unidad_medida,emp.root_tkn
              FROM in_egr_catalogo_productos AS catprod JOIN sos_ps_genero AS gen 
              JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
              JOIN teci_usuarios_catalogo AS users WHERE catprod.id = detprice.producto AND detprice.lista = price.id
              AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4'
              AND catprod.genero = gen.id AND catprod.token_cat_productos IN (
                SELECT catprod.token_cat_productos FROM in_egr_catalogo_productos AS catprod
                JOIN eegr_compras_detalle AS detcomp JOIN eegr_compras AS buy JOIN eegr_compras_recepcion AS recept
                JOIN in_egr_establecimientos_almacen AS det_alm WHERE catprod.id = detcomp.producto
                AND detcomp.activo_fijo IS NULL AND detcomp.activo_intangible IS NULL
                AND detcomp.numero_compra = buy.id AND detcomp.id = recept.detalle_compra
                AND recept.recept_status = TRUE AND recept.id = det_alm.recepcion_compra
                AND buy.status_recepcion = TRUE
              )
            AND catprod.uso_producto = 'v'
            AND catprod.activo IS NULL AND catprod.status = TRUE AND catprod.admin_empresa = emp.id
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?
            UNION ALL
            SELECT catserv.token_cat_servicios AS token_articulo,catserv.fecha_registro_serv AS fecha_alta,'Servicio' AS identificador,catserv.servicio AS concepto,catserv.clasificacion,
              gen.folio_genero AS genero,catserv.folio_sistema,catserv.post_folio,catserv.sat_clave_code,ROUND(detprice.precio,?) AS precioBase,catserv.unidad_medida_clave as unidad_medida,
              emp.root_tkn FROM in_egr_catalogo_servicios AS catserv JOIN sos_ps_genero AS gen JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catserv.id = detprice.servicio AND detprice.lista = price.id
              AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4' AND catserv.genero = gen.id AND catserv.proceso = 'v' AND catserv.status = TRUE 
              AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?) as total) ORDER BY total.concepto DESC",
            [$moneda_decimales, $usuario->empresa_token, $usuario->user_token, $moneda_decimales, $usuario->empresa_token, $usuario->user_token]
          );

          foreach ($catProdServ as $value) {
            $arrayDescuentos = array();
            $arrayPromociones = array();
            $arrayDesgloseImpuestos = array();
            $arraySerieLoteImport = array();
            $arrayImportado = array();
            $token_Articulo = $value->token_articulo;
            $dataPrecioBase = $value->precioBase;

            //if ($value->identificador == 'Servicio') {
            //    $dataPrecioBase = $JwtAuth->desencriptar($dataPrecioBase);
            //}
            //echo ." ".;
            $dataCantidad = 1.00;
            $resTotalDataDesc = '';
            $importeTdescuento = 0.00;
            $totalImpuesto = floatVal(0);
            $importePartida = 0.00;
            $contadorDescuentos = 0;

            if ($value->identificador == 'Producto') {
              $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                  JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                  JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.producto_rel = catprod.id 
                  AND catprod.token_cat_productos = ? AND catprod.status = TRUE AND catprod.administrador = emp.id
                  AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                  AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

              $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                  descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                  JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                  JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.producto = catprod.id
                  AND catprod.token_cat_productos = ? AND descu.status_activacion = TRUE AND descu.status = TRUE 
                  AND descu.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                  AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

              $listaPromoModal = DB::select(
                "SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                  promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin
                  FROM ingr_catalogo_promociones AS promo JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_productos AS catprod 
                  JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                  WHERE promo.id = detpromo.promocion AND detpromo.producto = catprod.id AND catprod.token_cat_productos = ?
                  AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id AND emp.empresa_token = ?
                  AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
                [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
              );
            } else {
              $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                  JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                  JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.servicio_rel = catserv.id 
                  AND catserv.token_cat_servicios = ? AND catserv.status = TRUE AND catserv.administrador = emp.id 
                  AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                  AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

              $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                  descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                  JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                  JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.servicio = catserv.id
                  AND catserv.token_cat_servicios = ? AND descu.status_activacion = TRUE AND descu.status = TRUE AND descu.empresa = emp.id
                  AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                  AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

              $listaPromoModal = DB::select("SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                  promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin FROM ingr_catalogo_promociones AS promo 
                  JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                  JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE promo.id = detpromo.promocion AND detpromo.servicio = catserv.id
                  AND catserv.token_cat_servicios = ? AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id
                  AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                  AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
            }

            if (count($consultaImpArticulo) != 0) {
              //$arrayImpuestos = json_decode($JwtAuth->desencriptar($consultaImpArticulo[0]->impuestos));
              for ($i = 0; $i < count($consultaImpArticulo); $i++) {
                $tknImpuesto = $consultaImpArticulo[$i]->token_cat_impuestos;
                $catImpuestos = DB::select("SELECT catimp.id,catimp.token_cat_impuestos,catimp.alias,catimp.clasificacion_impuestos,
                    catimp.ret_tras,catimp.por_cuo,catimp.importe,tip.concepto,tip.tipo FROM cont_catalogo_impuestos AS catimp  
                    JOIN cont_catalogo_impuestos_tipo AS tip JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.token_cat_impuestos = ? AND catimp.impuesto = tip.id AND catimp.status = TRUE
                    AND catimp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$tknImpuesto, $usuario->empresa_token, $usuario->user_token]);
                if (count($catImpuestos) == 1) {
                  $resImpDat = $catImpuestos[0];
                  //$dataPrecioBase,$totalImpuesto 
                  $cantBaseImpuesto = $catImpuestos[0]->importe;

                  if ($resImpDat->por_cuo == TRUE) {
                    $importeBase = explode("%", $cantBaseImpuesto);
                    //echo $importeBase[0];
                    $multi = '';
                    if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                      $importeBase2 = explode(".", $importeBase[0]);
                      $multi = '0.00' . $importeBase2[1];
                    } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                      $multi = '0.0' . $importeBase[0];
                    } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                      $multi = '0.' . $importeBase[0];
                      //echo $multi;
                    } else if ($importeBase[0] == 100) {
                      $multi = 1;
                    }
                    //echo $importePartida ;
                    $totalImp =  floatval($dataPrecioBase) * floatval($multi);
                  } else {
                    $importeBase = str_replace("$", "", $cantBaseImpuesto);
                    $importeBase = str_replace(",", "", $importeBase);
                    $totalImp = floatval($importeBase);
                  }
                  if ($resImpDat->ret_tras == TRUE) {
                    //echo $totalImpuesto;
                    $totalImpuesto = $totalImpuesto + $totalImp;
                  }

                  if ($resImpDat->ret_tras == FALSE) {
                    $totalImpuesto = $totalImpuesto - $totalImp;
                  }
                } else {
                  $totalImpuesto = $totalImpuesto + 0;
                }
              }
            } else {
              $totalImpuesto = $totalImpuesto;
            }

            $precioBaseConImp = number_format($dataPrecioBase + $totalImpuesto, 2, '.', ',');

            //echo count($listaDescModal).", "; 

            if (count($listaDescModal) == 0) {
              $importeTdescuento = 0.00;
            } else {
              $cantidadBaseDesc = $JwtAuth->desencriptar($listaDescModal[0]->cantidad_base);

              if ($listaDescModal[0]->cuo_porc == TRUE) {
                $importeBase = explode("%", $cantidadBaseDesc);
                $multi = '';
                if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                  $importeBase2 = explode(".", $importeBase[0]);
                  $multi = '0.00' . $importeBase2[1];
                } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                  $multi = '0.0' . $importeBase[0];
                } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                  $multi = '0.' . $importeBase[0];
                } else if ($importeBase[0] == 100) {
                  $multi = 1;
                }
                $importeTdescuento = $dataPrecioBase * floatval($multi);
              } else {
                $importeBase = explode("$", $cantidadBaseDesc);
                $importeTdescuento = floatval($importeBase[1]);
              }
              //$importeTdescuento = number_format($importeTdescuento,2,'.',',');

              foreach ($listaDescModal as $resListaDesc) {
                //echo $resListaDesc->id;
                if ($resListaDesc->cuo_porc == 0) {
                  $cuoPorc = 'cuota';
                } else {
                  $cuoPorc = 'porcentaje';
                }

                if ($resListaDesc->aplicacion == 'usa') {
                  $periodo = 'Eventual';
                  $resPeriodoInicio = '-';
                  $resPeriodoFin = '-';
                } else if ($resListaDesc->aplicacion == 'ind') {
                  $periodo = 'Periodo Indeterminado';
                  $resPeriodoInicio = '';
                  $resPeriodoFin = '-';
                  $valorFechaInicio = $resListaDesc->fecha_inicio;
                  //da_te_default_timezone_set('America/Mexico_City');
                  $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
                } else if ($resListaDesc->aplicacion == 'det') {
                  $periodo = 'Periodo Determinado';
                  //da_te_default_timezone_set('America/Mexico_City');
                  $resPeriodoInicio = '';
                  $resPeriodoFin = '';
                  $valorFechaInicio = $resListaDesc->fecha_inicio;
                  $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
                  $valorFechaFin = $resListaDesc->fecha_fin;
                  $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
                }

                if (count($arrayDescuentos) == 0) {
                  $valorDescuento = $importeTdescuento;
                } else {
                  $valorDescuento = floatVal('0.00');
                }

                if ($contadorDescuentos == 0) {
                  $checkDesc = '0TRUE';
                } else {
                  $checkDesc = '1FALSE';
                }

                $arraForeachDesc = array(
                  "token_descuentos" => $resListaDesc->token_descuentos,
                  "folioDesc" => $JwtAuth->generar($resListaDesc->folio),
                  "aliasDesc" => $JwtAuth->desencriptar($resListaDesc->alias),
                  "conceptoDesc" => $JwtAuth->desencriptar($resListaDesc->concepto),
                  "cuoPorc" => $cuoPorc,
                  "cantidad_base" => $JwtAuth->desencriptar($resListaDesc->cantidad_base),
                  "periodo" => $periodo,
                  "resPeriodoInicio" => $resPeriodoInicio,
                  "resPeriodoFin" => $resPeriodoFin,
                  "tdImporteDesc" => number_format($valorDescuento, 2, '.', ','),
                  "rescheck" => $checkDesc,
                );
                $arrayDescuentos[] = $arraForeachDesc;
                $contadorDescuentos++;
              }
            }

            $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
            $importePartida = number_format($importePartida, 2, '.', ',');

            if (count($listaPromoModal) > 0) {
              foreach ($listaPromoModal as $resListaPromo) {
                if ($resListaPromo->cuo_porc == 0) {
                  $cuoPorc = 'cuota';
                } else {
                  $cuoPorc = 'porcentaje';
                }

                if ($resListaPromo->aplicacion == 'usa') {
                  $periodo = 'Eventual';
                  $resPeriodoInicio = '-';
                  $resPeriodoFin = '-';
                } else if ($resListaPromo->aplicacion == 'ind') {
                  $periodo = 'Periodo Indeterminado';
                  $resPeriodoInicio = '';
                  $resPeriodoFin = '-';
                  $valorFechaInicio = $resListaPromo->fecha_inicio;
                  //da_te_default_timezone_set('America/Mexico_City');
                  $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
                } else if ($resListaPromo->aplicacion == 'det') {
                  $periodo = 'Periodo Determinado';
                  //da_te_default_timezone_set('America/Mexico_City');
                  $resPeriodoInicio = '';
                  $resPeriodoFin = '';
                  $valorFechaInicio = $resListaPromo->fecha_inicio;
                  $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
                  $valorFechaFin = $resListaPromo->fecha_fin;
                  $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
                }
                //$importePartida

                $cantidadBasePromo = $JwtAuth->desencriptar($resListaPromo->cantidad_base);
                //echo $cantidadBasePromo;
                if ($resListaPromo->cuo_porc == TRUE) {
                  $importeBase = explode("%", $cantidadBasePromo);
                  $multi = '';
                  if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                    $importeBase2 = explode(".", $importeBase[0]);
                    $multi = '0.00' . $importeBase2[1];
                  } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                    $multi = '0.0' . $importeBase[0];
                  } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                    $multi = '0.' . $importeBase[0];
                  } else if ($importeBase[0] == 100) {
                    $multi = 1;
                  }
                  $tdImportePromo = $dataPrecioBase * floatval($multi);
                } else {
                  $importeBase = explode("$", $cantidadBasePromo);
                  $tdImportePromo = floatval($importeBase[1]);
                }
                $tdImportePromo = number_format($tdImportePromo, 2, '.', ',');
                //echo $tdImportePromo; 
                $arraForeachPromo = array(
                  "token_promocion" => $resListaPromo->token_promocion,
                  "folioPromo" => $JwtAuth->generar($resListaPromo->folio),
                  "aliasPromo" => $JwtAuth->desencriptar($resListaPromo->alias),
                  "conceptoPromo" => $JwtAuth->desencriptar($resListaPromo->concepto),
                  "cuoPorc" => $cuoPorc,
                  "cantidad_base" => $JwtAuth->desencriptar($resListaPromo->cantidad_base),
                  "periodo" => $periodo,
                  "resPeriodoInicio" => $resPeriodoInicio,
                  "resPeriodoFin" => $resPeriodoFin,
                  "tdImportePromo" => $tdImportePromo,
                );
                $arrayPromociones[] = $arraForeachPromo;
              }
            }

            if ($value->identificador == 'Producto') {
              $conceptoExplode = explode("-", $value->concepto);
              //SELECT * FROM kardex where status_kardex = 5 AND fecha = (SELECT max(fecha) from kardex where fecha < now());
              //;
              $sumaExistencias = DB::select("SELECT SUM(saldo_cantidad) AS existencia FROM kardex 
                      WHERE status_kardex = 6 AND fecha_kardex = (SELECT MAX(fecha_kardex) FROM kardex WHERE fecha_kardex < now() AND status_kardex = 6)
                      AND producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?)", [$token_Articulo]);

              $producto = DB::select("SELECT catprod.costeo,catprod.num_serie,catprod.num_lote,catprod.importado 
                      FROM in_egr_catalogo_productos AS catprod 
                      JOIN main_empresas AS emp
                      JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                      JOIN teci_usuarios_catalogo AS users
                      WHERE catprod.token_cat_productos = ?
                      AND catprod.administrador = emp.id
                      AND emp.empresa_token = ?
                      AND emp.id = empuser.empresa
                      AND empuser.personal = pers.id
                      AND pers.usuario = users.id
                      AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

              if ($producto[0]->num_serie == TRUE) {
                $serieProd = DB::select("SELECT detalm.token_detalle_almacen,detalm.num_serie,detalm.existencia
                          FROM detalle_almacen AS detalm
                          JOIN in_egr_catalogo_productos AS catprod 
                          JOIN main_empresas AS emp
                          JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                          JOIN teci_usuarios_catalogo AS users
                          WHERE detalm.status_disponibilidad = TRUE
                          AND detalm.producto = catprod.id
                          AND catprod.token_cat_productos = ?
                          AND catprod.administrador = emp.id
                          AND emp.empresa_token = ?
                          AND emp.id = empuser.empresa
                          AND empuser.personal = pers.id
                          AND pers.usuario = users.id
                          AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
                //echo count($serieProd);
                if (count($serieProd) != 0) {
                  $serie = array();
                  if ($producto[0]->costeo == 'UEPS') {
                    for ($i = count($serieProd) - 1; $i >= 0; $i--) {
                      $arrayEach = array(
                        "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                        "num_serie" => $serieProd[$i]->num_serie,
                        "existencia" => $serieProd[$i]->existencia,
                      );
                      $serie[] = $arrayEach;
                    }
                  }
                  if ($producto[0]->costeo == 'PEPS') {
                    for ($i = 0; $i < count($serieProd); $i++) {
                      $arrayEach = array(
                        "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                        "num_serie" => $serieProd[$i]->num_serie,
                        "existencia" => $serieProd[$i]->existencia,
                      );
                      $serie[] = $arrayEach;
                    }
                  }
                } else {
                  $serie = array();
                }
              } else {
                $serie = array();
              }

              if ($producto[0]->num_lote == TRUE) {
                $loteProd = DB::select("SELECT token_lote,numero_lote
                          FROM lote_prod 
                          WHERE id in (SELECT detalm.num_lote
                          FROM detalle_almacen AS detalm
                          JOIN in_egr_catalogo_productos AS catprod 
                          JOIN main_empresas AS emp
                          JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                          JOIN teci_usuarios_catalogo AS users
                          WHERE detalm.status_disponibilidad = TRUE
                          AND detalm.producto = catprod.id
                          AND catprod.token_cat_productos = ?
                          AND catprod.administrador = emp.id
                          AND emp.empresa_token = ?
                          AND emp.id = empuser.empresa
                          AND empuser.personal = pers.id
                          AND pers.usuario = users.id
                          AND users.usuario_token = ?)
                          ", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

                //echo count($loteProd);
                if (count($loteProd) != 0) {
                  $lote = array();

                  if ($producto[0]->costeo == 'UEPS') {
                    for ($i = count($loteProd) - 1; $i >= 0; $i--) {
                      $sumLote = DB::select(
                        "SELECT SUM(existencia) AS existencia
                                      FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                      JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                      AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                      AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                      AND pers.usuario = users.id AND users.usuario_token = ?",
                        [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                      );
                      $arrayEach = array(
                        "token_lote" => $loteProd[$i]->token_lote,
                        "num_lote" => $loteProd[$i]->numero_lote,
                        "existencia" => $sumLote[0]->existencia,
                      );
                      $lote[] = $arrayEach;
                    }
                  }
                  if ($producto[0]->costeo == 'PEPS') {
                    for ($i = 0; $i < count($loteProd); $i++) {
                      $sumLote = DB::select(
                        "SELECT SUM(existencia) AS existencia
                                      FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                      JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                      AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                      AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                      AND pers.usuario = users.id AND users.usuario_token = ?",
                        [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                      );
                      $arrayEach = array(
                        "token_lote" => $loteProd[$i]->token_lote,
                        "num_lote" => $loteProd[$i]->numero_lote,
                        "existencia" => $sumLote[0]->existencia,
                      );
                      $lote[] = $arrayEach;
                    }
                  }
                } else {
                  $lote = array();
                }
              } else {
                $lote = array();
              }

              if ($producto[0]->importado == TRUE) {
                //$prodImportado = DB::select("SELECT catprod.importado,
                //ped.token_pedimento,ped.numero_pedimiento,ped.tipo_operacion,
                //ped.regimen,ped.destino,ped.tipo_cambio,ped.aduana FROM  pedimento_aduanal AS ped 
                //JOIN productos_importados AS importt
                //JOIN in_egr_catalogo_productos AS catprod 
                //WHERE ped.id = importt.pedimento
                //AND importt.existencia != 0
                //AND importt.producto = catprod.id
                //AND catprod.importado = true
                //AND catprod.status = true
                //AND catprod.token_cat_productos = ?
                //AND catprod.administrador = emp.id
                //AND emp.empresa_token = ?
                //AND emp.id = empuser.empresa
                //AND empuser.personal = pers.id
                //AND pers.usuario = users.id
                //AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                $pedimentoProd = DB::select(
                  "SELECT token_pedimento,numero_pedimento 
                          FROM pedimento_aduanal
                          WHERE id IN (SELECT detalm.importado
                              FROM detalle_almacen AS detalm
                              JOIN in_egr_catalogo_productos AS catprod 
                              JOIN main_empresas AS emp
                              JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                              JOIN teci_usuarios_catalogo AS users
                              WHERE detalm.status_disponibilidad = TRUE
                              AND detalm.producto = catprod.id
                              AND catprod.token_cat_productos = ?
                              AND catprod.administrador = emp.id
                              AND emp.empresa_token = ?
                              AND emp.id = empuser.empresa
                              AND empuser.personal = pers.id
                              AND pers.usuario = users.id
                              AND users.usuario_token = ?)",
                  [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                if (count($pedimentoProd) != 0) {
                  $pedimento = array();

                  if ($producto[0]->costeo == 'UEPS') {
                    for ($i = count($pedimentoProd) - 1; $i >= 0; $i--) {
                      echo $pedimentoProd[$i]->token_pedimento;
                      $sumImported = DB::select(
                        "SELECT SUM(existencia) AS existencia
                                      FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                      JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                      AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                      AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                      AND pers.usuario = users.id AND users.usuario_token = ?",
                        [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                      );
                      $arrayEach = array(
                        "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                        "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                        "existencia" => $sumImported[0]->existencia,
                      );
                      $pedimento[] = $arrayEach;
                    }
                  }
                  if ($producto[0]->costeo == 'PEPS') {
                    for ($i = 0; $i < count($pedimentoProd); $i++) {
                      //echo $token_Articulo;
                      $sumImported = DB::select(
                        "SELECT SUM(existencia) AS existencia
                                      FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                      JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                      AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                      AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                      AND pers.usuario = users.id AND users.usuario_token = ?",
                        [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                      );
                      $arrayEach = array(
                        "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                        "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                        "existencia" => $sumImported[0]->existencia,
                      );
                      $pedimento[] = $arrayEach;
                    }
                  }
                } else {
                  $pedimento = array();
                }
              } else {
                $pedimento = array();
              }

              $arraySerieLoteImport = array(
                "existKardex" => $sumaExistencias[0]->existencia,
                "serie" => $serie,
                "lote" => $lote,
                "pedimento" => $pedimento
              );

              $conceptoArticulo = $JwtAuth->desencriptar($conceptoExplode[0]) .
                " Marca:(" . $JwtAuth->desencriptar($conceptoExplode[1]) . ")";
            } else {
              $conceptoArticulo = $JwtAuth->desencriptar($value->concepto);
              $arraySerieLoteImport = [];
            }
            ///echo 'imaagen '.$JwtAuth->encriptar('default_prod.jpg').' ';
            if ($value->identificador == 'Producto') {
              if ($value->imagen == '' || !file_exists(Storage::path('public/root/' .
                $value->root_tkn . '/0002-cpp/catalogos/productos/'
                . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
                $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
                $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
              } else {
                $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
                  $value->root_tkn . '/0002-cpp/catalogos/productos/'
                  . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
                  $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen)));
              }
            } else {
              if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
                $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
              } else {

                $nameServImg = ServiciosModelo::join("main_empresas AS emp", "catalogo_servicios.administrador", "=", "emp.id")
                  ->join("empresapersonal", "emp.id", "=", "empresapersonal.empresa")
                  ->join("vhum_empleados_catalogo AS pers", "empresapersonal.personal", "=", "pers.id")
                  ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
                  ->where([
                    'catalogo_servicios.token_cat_servicios' => $value->token_articulo,
                  ])->get();

                $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
                  $value->root_tkn . '/0001-cpc/catalogos/servicios/' . $nameServImg[0]->fecha_sistema . '-' .
                  $JwtAuth->generar($nameServImg[0]->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
              }
            }
            //echo $totalImpuesto;
            $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
            $importePartida = number_format($importePartida, 2, '.', ',');

            $arraForeach = array(
              "token_articulo" => $value->token_articulo,
              "identificador" => $value->identificador,
              "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio),
              "sat" => $value->SAT,
              "clave" => $value->clave,
              "descripcion" => $value->descripcion,
              "concepto" => $conceptoArticulo,
              "arraySerieLoteImport" => $arraySerieLoteImport,
              "precioBaseConImp" => $precioBaseConImp,
              "precioBase" => $dataPrecioBase,
              "dataCantidad" => $dataCantidad,
              "imagen" => $imgArticulo,
              "importeTdescuento" => $importeTdescuento,
              "arrayDescuentos" => $arrayDescuentos,
              "arrayPromociones" => $arrayPromociones,
              "totalImpuesto" => number_format($totalImpuesto, 2, '.', ','),
              //"arrayDesgloseImpuestos" => $arrayDesgloseImpuestos,
              "importePartida" => $importePartida,
            );
            $arrayArticulos[] = $arraForeach;
          }
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

  public function cargaArticulosVentaDos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayArticulos = array();

    //echo $JwtAuth->encriptar('acer')." acer";

    $decimalesMoneda = DB::select(
      "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN teci_usuarios_catalogo AS users WHERE emp.moneda = catmon.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.personal = pers.id 
            AND pers.usuario = users.id AND users.usuario_token = ?",
      [$usuario->empresa_token, $usuario->user_token]
    );

    $catProdServ = DB::select(
      "SELECT total.token_articulo,total.identificador,total.concepto,total.clasificacion,
            total.genero,total.folio,total.clave,total.descripcion,total.precioBase,total.SAT,total.imagen,total.root_tkn,total.fecha_alta
            FROM ((SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_alta,'Producto' AS identificador,
                concat(prod.producto,'-',prod.marca) AS concepto,prod.clasificacion,gen.folio_genero AS genero,
                catprod.folio_sistema,prodsat.clave,prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,
                concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,prod.imagen,emp.root_tkn
                FROM productos AS prod JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_productos AS catprod
                JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice
                JOIN teci_catalogo_prodservsat AS prodsat JOIN teci_unidad_medida AS unimed
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                JOIN teci_usuarios_catalogo AS users WHERE catprod.producto = prod.id
                AND catprod.id = detprice.producto AND detprice.lista = price.id
                AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4'
                AND prod.genero = gen.id AND catprod.token_cat_productos IN (
                    SELECT catprod.token_cat_productos FROM in_egr_catalogo_productos AS catprod
                    JOIN eegr_compras_detalle AS detcomp JOIN eegr_compras AS buy JOIN eegr_compras_recepcion AS recept
                    JOIN in_egr_establecimientos_almacen AS det_alm WHERE catprod.id = detcomp.producto
                    AND detcomp.activo_fijo IS NULL AND detcomp.activo_intangible IS NULL
                    AND detcomp.numero_compra = buy.id AND detcomp.id = recept.detalle_compra
                    AND recept.recept_status = TRUE AND recept.id = det_alm.recepcion_compra
                    AND buy.status_recepcion = TRUE
                )
            AND catprod.catalogo_sat = prodsat.id AND prod.medida_entrada = unimed.id AND catprod.tipo_prod = 'pr'
            AND catprod.activo IS NULL AND catprod.status = TRUE AND catprod.administrador = emp.id
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?
            UNION ALL
            SELECT catserv.token_cat_servicios AS token_articulo,catserv.fechaAlta AS fecha_alta,'Servicio' AS identificador,
            serv.servicio AS concepto,serv.clasificacion,gen.folio_genero AS genero,catserv.folio,prodsat.clave,
            prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,
            serv.imagen,emp.root_tkn FROM servicios AS serv JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_servicios AS catserv 
            JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice JOIN teci_catalogo_prodservsat AS prodsat
            JOIN teci_unidad_medida AS unimed JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
            JOIN teci_usuarios_catalogo AS users WHERE catserv.servicio = serv.id AND catserv.id = detprice.servicio AND detprice.lista = price.id
            AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4' AND serv.genero = gen.id 
            AND serv.catalogo_sat = prodsat.id AND serv.medida_sat = unimed.id AND catserv.proceso = FALSE AND catserv.status = TRUE 
            AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?) as total) ORDER BY total.concepto DESC",
      [$decimalesMoneda[0]->decimales, $usuario->empresa_token, $usuario->user_token, $decimalesMoneda[0]->decimales, $usuario->empresa_token, $usuario->user_token]
    );

    foreach ($catProdServ as $value) {
      $arrayDescuentos = array();
      $arrayPromociones = array();
      $arrayDesgloseImpuestos = array();
      $arraySerieLoteImport = array();
      $arrayImportado = array();
      $token_Articulo = $value->token_articulo;
      $dataPrecioBase = $value->precioBase;

      //if ($value->identificador == 'Servicio') {
      //    $dataPrecioBase = $JwtAuth->desencriptar($dataPrecioBase);
      //}
      //echo ." ".;
      $dataCantidad = 1.00;
      $resTotalDataDesc = '';
      $importeTdescuento = 0.00;
      $totalImpuesto = floatVal(0);
      $importePartida = 0.00;
      $contadorDescuentos = 0;

      if ($value->identificador == 'Producto') {
        $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                    JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.producto_rel = catprod.id 
                    AND catprod.token_cat_productos = ? AND catprod.status = TRUE AND catprod.administrador = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.producto = catprod.id
                    AND catprod.token_cat_productos = ? AND descu.status_activacion = TRUE AND descu.status = TRUE 
                    AND descu.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaPromoModal = DB::select(
          "SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin
                    FROM ingr_catalogo_promociones AS promo JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                    WHERE promo.id = detpromo.promocion AND detpromo.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
          [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
        );
      } else {
        $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                    JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.servicio_rel = catserv.id 
                    AND catserv.token_cat_servicios = ? AND catserv.status = TRUE AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND descu.status_activacion = TRUE AND descu.status = TRUE AND descu.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaPromoModal = DB::select("SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin FROM ingr_catalogo_promociones AS promo 
                    JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE promo.id = detpromo.promocion AND detpromo.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
      }

      if (count($consultaImpArticulo) != 0) {
        //$arrayImpuestos = json_decode($JwtAuth->desencriptar($consultaImpArticulo[0]->impuestos));
        for ($i = 0; $i < count($consultaImpArticulo); $i++) {
          $tknImpuesto = $consultaImpArticulo[$i]->token_cat_impuestos;
          $catImpuestos = DB::select("SELECT catimp.id,catimp.token_cat_impuestos,catimp.alias,catimp.clasificacion_impuestos,
                        catimp.ret_tras,catimp.por_cuo,catimp.importe,tip.concepto,tip.tipo FROM cont_catalogo_impuestos AS catimp  
                        JOIN cont_catalogo_impuestos_tipo AS tip JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE catimp.token_cat_impuestos = ? AND catimp.impuesto = tip.id AND catimp.status = TRUE
                        AND catimp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                        AND pers.usuario = users.id AND users.usuario_token = ?", [$tknImpuesto, $usuario->empresa_token, $usuario->user_token]);

          if (count($catImpuestos) == 1) {
            $resImpDat = $catImpuestos[0];
            //$dataPrecioBase,$totalImpuesto 
            $cantBaseImpuesto = $catImpuestos[0]->importe;

            if ($resImpDat->por_cuo == TRUE) {
              $importeBase = explode("%", $cantBaseImpuesto);
              //echo $importeBase[0];
              $multi = '';
              if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                $importeBase2 = explode(".", $importeBase[0]);
                $multi = '0.00' . $importeBase2[1];
              } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                $multi = '0.0' . $importeBase[0];
              } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                $multi = '0.' . $importeBase[0];
                //echo $multi;
              } else if ($importeBase[0] == 100) {
                $multi = 1;
              }
              //echo $importePartida ;
              $totalImp =  floatval($dataPrecioBase) * floatval($multi);
            } else {
              $importeBase = str_replace("$", "", $cantBaseImpuesto);
              $importeBase = str_replace(",", "", $importeBase);
              $totalImp = floatval($importeBase);
            }

            if ($resImpDat->ret_tras == TRUE) {
              //echo $totalImpuesto;
              $totalImpuesto = $totalImpuesto + $totalImp;
            }

            if ($resImpDat->ret_tras == FALSE) {
              $totalImpuesto = $totalImpuesto - $totalImp;
            }
          } else {
            $totalImpuesto = $totalImpuesto + 0;
          }
        }
      } else {
        $totalImpuesto = $totalImpuesto;
      }

      $precioBaseConImp = number_format($dataPrecioBase + $totalImpuesto, 2, '.', ',');

      //echo count($listaDescModal).", "; 

      if (count($listaDescModal) == 0) {
        $importeTdescuento = 0.00;
      } else {
        $cantidadBaseDesc = $JwtAuth->desencriptar($listaDescModal[0]->cantidad_base);

        if ($listaDescModal[0]->cuo_porc == TRUE) {
          $importeBase = explode("%", $cantidadBaseDesc);
          $multi = '';
          if ($importeBase[0] > 0 && $importeBase[0] < 1) {
            $importeBase2 = explode(".", $importeBase[0]);
            $multi = '0.00' . $importeBase2[1];
          } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
            $multi = '0.0' . $importeBase[0];
          } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
            $multi = '0.' . $importeBase[0];
          } else if ($importeBase[0] == 100) {
            $multi = 1;
          }
          $importeTdescuento = $dataPrecioBase * floatval($multi);
        } else {
          $importeBase = explode("$", $cantidadBaseDesc);
          $importeTdescuento = floatval($importeBase[1]);
        }
        //$importeTdescuento = number_format($importeTdescuento,2,'.',',');

        foreach ($listaDescModal as $resListaDesc) {
          //echo $resListaDesc->id;
          if ($resListaDesc->cuo_porc == 0) {
            $cuoPorc = 'cuota';
          } else {
            $cuoPorc = 'porcentaje';
          }

          if ($resListaDesc->aplicacion == 'usa') {
            $periodo = 'Eventual';
            $resPeriodoInicio = '-';
            $resPeriodoFin = '-';
          } else if ($resListaDesc->aplicacion == 'ind') {
            $periodo = 'Periodo Indeterminado';
            $resPeriodoInicio = '';
            $resPeriodoFin = '-';
            $valorFechaInicio = $resListaDesc->fecha_inicio;
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
          } else if ($resListaDesc->aplicacion == 'det') {
            $periodo = 'Periodo Determinado';
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = '';
            $resPeriodoFin = '';
            $valorFechaInicio = $resListaDesc->fecha_inicio;
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
            $valorFechaFin = $resListaDesc->fecha_fin;
            $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
          }

          if (count($arrayDescuentos) == 0) {
            $valorDescuento = $importeTdescuento;
          } else {
            $valorDescuento = floatVal('0.00');
          }

          if ($contadorDescuentos == 0) {
            $checkDesc = '0TRUE';
          } else {
            $checkDesc = '1FALSE';
          }

          $arraForeachDesc = array(
            "token_descuentos" => $resListaDesc->token_descuentos,
            "folioDesc" => $JwtAuth->generar($resListaDesc->folio),
            "aliasDesc" => $JwtAuth->desencriptar($resListaDesc->alias),
            "conceptoDesc" => $JwtAuth->desencriptar($resListaDesc->concepto),
            "cuoPorc" => $cuoPorc,
            "cantidad_base" => $JwtAuth->desencriptar($resListaDesc->cantidad_base),
            "periodo" => $periodo,
            "resPeriodoInicio" => $resPeriodoInicio,
            "resPeriodoFin" => $resPeriodoFin,
            "tdImporteDesc" => number_format($valorDescuento, 2, '.', ','),
            "rescheck" => $checkDesc,
          );
          $arrayDescuentos[] = $arraForeachDesc;
          $contadorDescuentos++;
        }
      }

      $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
      $importePartida = number_format($importePartida, 2, '.', ',');

      if (count($listaPromoModal) > 0) {
        foreach ($listaPromoModal as $resListaPromo) {
          if ($resListaPromo->cuo_porc == 0) {
            $cuoPorc = 'cuota';
          } else {
            $cuoPorc = 'porcentaje';
          }

          if ($resListaPromo->aplicacion == 'usa') {
            $periodo = 'Eventual';
            $resPeriodoInicio = '-';
            $resPeriodoFin = '-';
          } else if ($resListaPromo->aplicacion == 'ind') {
            $periodo = 'Periodo Indeterminado';
            $resPeriodoInicio = '';
            $resPeriodoFin = '-';
            $valorFechaInicio = $resListaPromo->fecha_inicio;
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
          } else if ($resListaPromo->aplicacion == 'det') {
            $periodo = 'Periodo Determinado';
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = '';
            $resPeriodoFin = '';
            $valorFechaInicio = $resListaPromo->fecha_inicio;
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
            $valorFechaFin = $resListaPromo->fecha_fin;
            $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
          }
          //$importePartida

          $cantidadBasePromo = $JwtAuth->desencriptar($resListaPromo->cantidad_base);
          //echo $cantidadBasePromo;
          if ($resListaPromo->cuo_porc == TRUE) {
            $importeBase = explode("%", $cantidadBasePromo);
            $multi = '';
            if ($importeBase[0] > 0 && $importeBase[0] < 1) {
              $importeBase2 = explode(".", $importeBase[0]);
              $multi = '0.00' . $importeBase2[1];
            } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
              $multi = '0.0' . $importeBase[0];
            } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
              $multi = '0.' . $importeBase[0];
            } else if ($importeBase[0] == 100) {
              $multi = 1;
            }
            $tdImportePromo = $dataPrecioBase * floatval($multi);
          } else {
            $importeBase = explode("$", $cantidadBasePromo);
            $tdImportePromo = floatval($importeBase[1]);
          }
          $tdImportePromo = number_format($tdImportePromo, 2, '.', ',');
          //echo $tdImportePromo; 
          $arraForeachPromo = array(
            "token_promocion" => $resListaPromo->token_promocion,
            "folioPromo" => $JwtAuth->generar($resListaPromo->folio),
            "aliasPromo" => $JwtAuth->desencriptar($resListaPromo->alias),
            "conceptoPromo" => $JwtAuth->desencriptar($resListaPromo->concepto),
            "cuoPorc" => $cuoPorc,
            "cantidad_base" => $JwtAuth->desencriptar($resListaPromo->cantidad_base),
            "periodo" => $periodo,
            "resPeriodoInicio" => $resPeriodoInicio,
            "resPeriodoFin" => $resPeriodoFin,
            "tdImportePromo" => $tdImportePromo,
          );
          $arrayPromociones[] = $arraForeachPromo;
        }
      }

      if ($value->identificador == 'Producto') {
        $conceptoExplode = explode("-", $value->concepto);
        //SELECT * FROM kardex where status_kardex = 5 AND fecha = (SELECT max(fecha) from kardex where fecha < now());
        //;
        $sumaExistencias = DB::select("SELECT SUM(saldo_cantidad) AS existencia FROM kardex 
                    WHERE status_kardex = 6 AND fecha_kardex = (SELECT MAX(fecha_kardex) FROM kardex WHERE fecha_kardex < now() AND status_kardex = 6)
                    AND producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?)", [$token_Articulo]);

        $producto = DB::select("SELECT catprod.costeo,catprod.num_serie,catprod.num_lote,catprod.importado 
                    FROM in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                    JOIN teci_usuarios_catalogo AS users
                    WHERE catprod.token_cat_productos = ?
                    AND catprod.administrador = emp.id
                    AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa
                    AND empuser.personal = pers.id
                    AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        if ($producto[0]->num_serie == TRUE) {
          $serieProd = DB::select("SELECT detalm.token_detalle_almacen,detalm.num_serie,detalm.existencia
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
          //echo count($serieProd);
          if (count($serieProd) != 0) {
            $serie = array();
            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($serieProd) - 1; $i >= 0; $i--) {
                $arrayEach = array(
                  "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                  "num_serie" => $serieProd[$i]->num_serie,
                  "existencia" => $serieProd[$i]->existencia,
                );
                $serie[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($serieProd); $i++) {
                $arrayEach = array(
                  "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                  "num_serie" => $serieProd[$i]->num_serie,
                  "existencia" => $serieProd[$i]->existencia,
                );
                $serie[] = $arrayEach;
              }
            }
          } else {
            $serie = array();
          }
        } else {
          $serie = array();
        }

        if ($producto[0]->num_lote == TRUE) {
          $loteProd = DB::select("SELECT token_lote,numero_lote
                        FROM lote_prod 
                        WHERE id in (SELECT detalm.num_lote
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?)
                        ", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

          //echo count($loteProd);
          if (count($loteProd) != 0) {
            $lote = array();

            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($loteProd) - 1; $i >= 0; $i--) {
                $sumLote = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_lote" => $loteProd[$i]->token_lote,
                  "num_lote" => $loteProd[$i]->numero_lote,
                  "existencia" => $sumLote[0]->existencia,
                );
                $lote[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($loteProd); $i++) {
                $sumLote = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_lote" => $loteProd[$i]->token_lote,
                  "num_lote" => $loteProd[$i]->numero_lote,
                  "existencia" => $sumLote[0]->existencia,
                );
                $lote[] = $arrayEach;
              }
            }
          } else {
            $lote = array();
          }
        } else {
          $lote = array();
        }

        if ($producto[0]->importado == TRUE) {
          //$prodImportado = DB::select("SELECT catprod.importado,
          //ped.token_pedimento,ped.numero_pedimiento,ped.tipo_operacion,
          //ped.regimen,ped.destino,ped.tipo_cambio,ped.aduana FROM  pedimento_aduanal AS ped 
          //JOIN productos_importados AS importt
          //JOIN in_egr_catalogo_productos AS catprod 
          //WHERE ped.id = importt.pedimento
          //AND importt.existencia != 0
          //AND importt.producto = catprod.id
          //AND catprod.importado = true
          //AND catprod.status = true
          //AND catprod.token_cat_productos = ?
          //AND catprod.administrador = emp.id
          //AND emp.empresa_token = ?
          //AND emp.id = empuser.empresa
          //AND empuser.personal = pers.id
          //AND pers.usuario = users.id
          //AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
          $pedimentoProd = DB::select(
            "SELECT token_pedimento,numero_pedimento 
                        FROM pedimento_aduanal
                        WHERE id IN (SELECT detalm.importado
                            FROM detalle_almacen AS detalm
                            JOIN in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                            JOIN teci_usuarios_catalogo AS users
                            WHERE detalm.status_disponibilidad = TRUE
                            AND detalm.producto = catprod.id
                            AND catprod.token_cat_productos = ?
                            AND catprod.administrador = emp.id
                            AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa
                            AND empuser.personal = pers.id
                            AND pers.usuario = users.id
                            AND users.usuario_token = ?)",
            [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
          );
          if (count($pedimentoProd) != 0) {
            $pedimento = array();

            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($pedimentoProd) - 1; $i >= 0; $i--) {
                echo $pedimentoProd[$i]->token_pedimento;
                $sumImported = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                  "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                  "existencia" => $sumImported[0]->existencia,
                );
                $pedimento[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($pedimentoProd); $i++) {
                //echo $token_Articulo;
                $sumImported = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                  "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                  "existencia" => $sumImported[0]->existencia,
                );
                $pedimento[] = $arrayEach;
              }
            }
          } else {
            $pedimento = array();
          }
        } else {
          $pedimento = array();
        }

        $arraySerieLoteImport = array(
          "existKardex" => $sumaExistencias[0]->existencia,
          "serie" => $serie,
          "lote" => $lote,
          "pedimento" => $pedimento
        );

        $conceptoArticulo = $JwtAuth->desencriptar($conceptoExplode[0]) .
          " Marca:(" . $JwtAuth->desencriptar($conceptoExplode[1]) . ")";
      } else {
        $conceptoArticulo = $JwtAuth->desencriptar($value->concepto);
        $arraySerieLoteImport = [];
      }
      ///echo 'imaagen '.$JwtAuth->encriptar('default_prod.jpg').' ';
      if ($value->identificador == 'Producto') {
        if ($value->imagen == '' || !file_exists(Storage::path('public/root/' .
          $value->root_tkn . '/0002-cpp/catalogos/productos/'
          . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
          $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
        } else {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $value->root_tkn . '/0002-cpp/catalogos/productos/'
            . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
            $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen)));
        }
      } else {
        if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
        } else {

          $nameServImg = ServiciosModelo::join("main_empresas AS emp", "catalogo_servicios.administrador", "=", "emp.id")
            ->join("empresapersonal", "emp.id", "=", "empresapersonal.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empresapersonal.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'catalogo_servicios.token_cat_servicios' => $value->token_articulo,
            ])->get();

          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $value->root_tkn . '/0001-cpc/catalogos/servicios/' . $nameServImg[0]->fecha_sistema . '-' .
            $JwtAuth->generar($nameServImg[0]->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
        }
      }
      //echo $totalImpuesto;
      $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
      $importePartida = number_format($importePartida, 2, '.', ',');

      $arraForeach = array(
        "token_articulo" => $value->token_articulo,
        "identificador" => $value->identificador,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio),
        "sat" => $value->SAT,
        "clave" => $value->clave,
        "descripcion" => $value->descripcion,
        "concepto" => $conceptoArticulo,
        "arraySerieLoteImport" => $arraySerieLoteImport,
        "precioBaseConImp" => $precioBaseConImp,
        "precioBase" => $dataPrecioBase,
        "dataCantidad" => $dataCantidad,
        "imagen" => $imgArticulo,
        "importeTdescuento" => $importeTdescuento,
        "arrayDescuentos" => $arrayDescuentos,
        "arrayPromociones" => $arrayPromociones,
        "totalImpuesto" => number_format($totalImpuesto, 2, '.', ','),
        //"arrayDesgloseImpuestos" => $arrayDesgloseImpuestos,
        "importePartida" => $importePartida,
      );
      $arrayArticulos[] = $arraForeach;
    }

    return response()->json([
      'listaArticulos' => $arrayArticulos,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function cargaArticulosVentaTres(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayArticulos = array();

    //echo $JwtAuth->encriptar('acer')." acer";

    $decimalesMoneda = DB::select(
      "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN teci_usuarios_catalogo AS users WHERE emp.moneda = catmon.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.personal = pers.id 
            AND pers.usuario = users.id AND users.usuario_token = ?",
      [$usuario->empresa_token, $usuario->user_token]
    );

    $catProdServ = DB::select(
      "SELECT total.token_articulo,total.identificador,total.concepto,total.clasificacion,
            total.genero,total.folio,total.clave,total.descripcion,total.precioBase,total.SAT,total.imagen,total.root_tkn,total.fecha_alta
            FROM ((SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_alta,'Producto' AS identificador,
                concat(prod.producto,'-',prod.marca) AS concepto,prod.clasificacion,gen.folio_genero AS genero,
                catprod.folio_sistema,prodsat.clave,prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,
                concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,prod.imagen,emp.root_tkn
                FROM productos AS prod JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_productos AS catprod
                JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice
                JOIN teci_catalogo_prodservsat AS prodsat JOIN teci_unidad_medida AS unimed
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                JOIN teci_usuarios_catalogo AS users WHERE catprod.producto = prod.id
                AND catprod.id = detprice.producto AND detprice.lista = price.id
                AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4'
                AND prod.genero = gen.id AND catprod.token_cat_productos IN (
                    SELECT catprod.token_cat_productos FROM in_egr_catalogo_productos AS catprod
                    JOIN eegr_compras_detalle AS detcomp JOIN eegr_compras AS buy JOIN eegr_compras_recepcion AS recept
                    JOIN in_egr_establecimientos_almacen AS det_alm WHERE catprod.id = detcomp.producto
                    AND detcomp.activo_fijo IS NULL AND detcomp.activo_intangible IS NULL
                    AND detcomp.numero_compra = buy.id AND detcomp.id = recept.detalle_compra
                    AND recept.recept_status = TRUE AND recept.id = det_alm.recepcion_compra
                    AND buy.status_recepcion = TRUE
                )
            AND catprod.catalogo_sat = prodsat.id AND prod.medida_entrada = unimed.id AND catprod.tipo_prod = 'pr'
            AND catprod.activo IS NULL AND catprod.status = TRUE AND catprod.administrador = emp.id
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?
            UNION ALL
            SELECT catserv.token_cat_servicios AS token_articulo,catserv.fechaAlta AS fecha_alta,'Servicio' AS identificador,
            serv.servicio AS concepto,serv.clasificacion,gen.folio_genero AS genero,catserv.folio,prodsat.clave,
            prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,
            serv.imagen,emp.root_tkn FROM servicios AS serv JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_servicios AS catserv 
            JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice JOIN teci_catalogo_prodservsat AS prodsat
            JOIN teci_unidad_medida AS unimed JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
            JOIN teci_usuarios_catalogo AS users WHERE catserv.servicio = serv.id AND catserv.id = detprice.servicio AND detprice.lista = price.id
            AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4' AND serv.genero = gen.id 
            AND serv.catalogo_sat = prodsat.id AND serv.medida_sat = unimed.id AND catserv.proceso = FALSE AND catserv.status = TRUE 
            AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?) as total) ORDER BY total.concepto DESC",
      [$decimalesMoneda[0]->decimales, $usuario->empresa_token, $usuario->user_token, $decimalesMoneda[0]->decimales, $usuario->empresa_token, $usuario->user_token]
    );

    foreach ($catProdServ as $value) {
      $arrayDescuentos = array();
      $arrayPromociones = array();
      $arrayDesgloseImpuestos = array();
      $arraySerieLoteImport = array();
      $arrayImportado = array();
      $token_Articulo = $value->token_articulo;
      $dataPrecioBase = $value->precioBase;

      //if ($value->identificador == 'Servicio') {
      //    $dataPrecioBase = $JwtAuth->desencriptar($dataPrecioBase);
      //}
      //echo ." ".;
      $dataCantidad = 1.00;
      $resTotalDataDesc = '';
      $importeTdescuento = 0.00;
      $totalImpuesto = floatVal(0);
      $importePartida = 0.00;
      $contadorDescuentos = 0;

      if ($value->identificador == 'Producto') {
        $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                    JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.producto_rel = catprod.id 
                    AND catprod.token_cat_productos = ? AND catprod.status = TRUE AND catprod.administrador = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.producto = catprod.id
                    AND catprod.token_cat_productos = ? AND descu.status_activacion = TRUE AND descu.status = TRUE 
                    AND descu.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaPromoModal = DB::select(
          "SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin
                    FROM ingr_catalogo_promociones AS promo JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                    WHERE promo.id = detpromo.promocion AND detpromo.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
          [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
        );
      } else {
        $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                    JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.servicio_rel = catserv.id 
                    AND catserv.token_cat_servicios = ? AND catserv.status = TRUE AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND descu.status_activacion = TRUE AND descu.status = TRUE AND descu.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaPromoModal = DB::select("SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin FROM ingr_catalogo_promociones AS promo 
                    JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE promo.id = detpromo.promocion AND detpromo.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
      }

      if (count($consultaImpArticulo) != 0) {
        //$arrayImpuestos = json_decode($JwtAuth->desencriptar($consultaImpArticulo[0]->impuestos));
        for ($i = 0; $i < count($consultaImpArticulo); $i++) {
          $tknImpuesto = $consultaImpArticulo[$i]->token_cat_impuestos;
          $catImpuestos = DB::select("SELECT catimp.id,catimp.token_cat_impuestos,catimp.alias,catimp.clasificacion_impuestos,
                        catimp.ret_tras,catimp.por_cuo,catimp.importe,tip.concepto,tip.tipo FROM cont_catalogo_impuestos AS catimp  
                        JOIN cont_catalogo_impuestos_tipo AS tip JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE catimp.token_cat_impuestos = ? AND catimp.impuesto = tip.id AND catimp.status = TRUE
                        AND catimp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                        AND pers.usuario = users.id AND users.usuario_token = ?", [$tknImpuesto, $usuario->empresa_token, $usuario->user_token]);

          if (count($catImpuestos) == 1) {
            $resImpDat = $catImpuestos[0];
            //$dataPrecioBase,$totalImpuesto 
            $cantBaseImpuesto = $catImpuestos[0]->importe;

            if ($resImpDat->por_cuo == TRUE) {
              $importeBase = explode("%", $cantBaseImpuesto);
              //echo $importeBase[0];
              $multi = '';
              if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                $importeBase2 = explode(".", $importeBase[0]);
                $multi = '0.00' . $importeBase2[1];
              } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                $multi = '0.0' . $importeBase[0];
              } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                $multi = '0.' . $importeBase[0];
                //echo $multi;
              } else if ($importeBase[0] == 100) {
                $multi = 1;
              }
              //echo $importePartida ;
              $totalImp =  floatval($dataPrecioBase) * floatval($multi);
            } else {
              $importeBase = str_replace("$", "", $cantBaseImpuesto);
              $importeBase = str_replace(",", "", $importeBase);
              $totalImp = floatval($importeBase);
            }

            if ($resImpDat->ret_tras == TRUE) {
              //echo $totalImpuesto;
              $totalImpuesto = $totalImpuesto + $totalImp;
            }

            if ($resImpDat->ret_tras == FALSE) {
              $totalImpuesto = $totalImpuesto - $totalImp;
            }
          } else {
            $totalImpuesto = $totalImpuesto + 0;
          }
        }
      } else {
        $totalImpuesto = $totalImpuesto;
      }

      $precioBaseConImp = number_format($dataPrecioBase + $totalImpuesto, 2, '.', ',');

      //echo count($listaDescModal).", "; 

      if (count($listaDescModal) == 0) {
        $importeTdescuento = 0.00;
      } else {
        $cantidadBaseDesc = $JwtAuth->desencriptar($listaDescModal[0]->cantidad_base);

        if ($listaDescModal[0]->cuo_porc == TRUE) {
          $importeBase = explode("%", $cantidadBaseDesc);
          $multi = '';
          if ($importeBase[0] > 0 && $importeBase[0] < 1) {
            $importeBase2 = explode(".", $importeBase[0]);
            $multi = '0.00' . $importeBase2[1];
          } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
            $multi = '0.0' . $importeBase[0];
          } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
            $multi = '0.' . $importeBase[0];
          } else if ($importeBase[0] == 100) {
            $multi = 1;
          }
          $importeTdescuento = $dataPrecioBase * floatval($multi);
        } else {
          $importeBase = explode("$", $cantidadBaseDesc);
          $importeTdescuento = floatval($importeBase[1]);
        }
        //$importeTdescuento = number_format($importeTdescuento,2,'.',',');

        foreach ($listaDescModal as $resListaDesc) {
          //echo $resListaDesc->id;
          if ($resListaDesc->cuo_porc == 0) {
            $cuoPorc = 'cuota';
          } else {
            $cuoPorc = 'porcentaje';
          }

          if ($resListaDesc->aplicacion == 'usa') {
            $periodo = 'Eventual';
            $resPeriodoInicio = '-';
            $resPeriodoFin = '-';
          } else if ($resListaDesc->aplicacion == 'ind') {
            $periodo = 'Periodo Indeterminado';
            $resPeriodoInicio = '';
            $resPeriodoFin = '-';
            $valorFechaInicio = $resListaDesc->fecha_inicio;
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
          } else if ($resListaDesc->aplicacion == 'det') {
            $periodo = 'Periodo Determinado';
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = '';
            $resPeriodoFin = '';
            $valorFechaInicio = $resListaDesc->fecha_inicio;
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
            $valorFechaFin = $resListaDesc->fecha_fin;
            $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
          }

          if (count($arrayDescuentos) == 0) {
            $valorDescuento = $importeTdescuento;
          } else {
            $valorDescuento = floatVal('0.00');
          }

          if ($contadorDescuentos == 0) {
            $checkDesc = '0TRUE';
          } else {
            $checkDesc = '1FALSE';
          }

          $arraForeachDesc = array(
            "token_descuentos" => $resListaDesc->token_descuentos,
            "folioDesc" => $JwtAuth->generar($resListaDesc->folio),
            "aliasDesc" => $JwtAuth->desencriptar($resListaDesc->alias),
            "conceptoDesc" => $JwtAuth->desencriptar($resListaDesc->concepto),
            "cuoPorc" => $cuoPorc,
            "cantidad_base" => $JwtAuth->desencriptar($resListaDesc->cantidad_base),
            "periodo" => $periodo,
            "resPeriodoInicio" => $resPeriodoInicio,
            "resPeriodoFin" => $resPeriodoFin,
            "tdImporteDesc" => number_format($valorDescuento, 2, '.', ','),
            "rescheck" => $checkDesc,
          );
          $arrayDescuentos[] = $arraForeachDesc;
          $contadorDescuentos++;
        }
      }

      $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
      $importePartida = number_format($importePartida, 2, '.', ',');

      if (count($listaPromoModal) > 0) {
        foreach ($listaPromoModal as $resListaPromo) {
          if ($resListaPromo->cuo_porc == 0) {
            $cuoPorc = 'cuota';
          } else {
            $cuoPorc = 'porcentaje';
          }

          if ($resListaPromo->aplicacion == 'usa') {
            $periodo = 'Eventual';
            $resPeriodoInicio = '-';
            $resPeriodoFin = '-';
          } else if ($resListaPromo->aplicacion == 'ind') {
            $periodo = 'Periodo Indeterminado';
            $resPeriodoInicio = '';
            $resPeriodoFin = '-';
            $valorFechaInicio = $resListaPromo->fecha_inicio;
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
          } else if ($resListaPromo->aplicacion == 'det') {
            $periodo = 'Periodo Determinado';
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = '';
            $resPeriodoFin = '';
            $valorFechaInicio = $resListaPromo->fecha_inicio;
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
            $valorFechaFin = $resListaPromo->fecha_fin;
            $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
          }
          //$importePartida

          $cantidadBasePromo = $JwtAuth->desencriptar($resListaPromo->cantidad_base);
          //echo $cantidadBasePromo;
          if ($resListaPromo->cuo_porc == TRUE) {
            $importeBase = explode("%", $cantidadBasePromo);
            $multi = '';
            if ($importeBase[0] > 0 && $importeBase[0] < 1) {
              $importeBase2 = explode(".", $importeBase[0]);
              $multi = '0.00' . $importeBase2[1];
            } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
              $multi = '0.0' . $importeBase[0];
            } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
              $multi = '0.' . $importeBase[0];
            } else if ($importeBase[0] == 100) {
              $multi = 1;
            }
            $tdImportePromo = $dataPrecioBase * floatval($multi);
          } else {
            $importeBase = explode("$", $cantidadBasePromo);
            $tdImportePromo = floatval($importeBase[1]);
          }
          $tdImportePromo = number_format($tdImportePromo, 2, '.', ',');
          //echo $tdImportePromo; 
          $arraForeachPromo = array(
            "token_promocion" => $resListaPromo->token_promocion,
            "folioPromo" => $JwtAuth->generar($resListaPromo->folio),
            "aliasPromo" => $JwtAuth->desencriptar($resListaPromo->alias),
            "conceptoPromo" => $JwtAuth->desencriptar($resListaPromo->concepto),
            "cuoPorc" => $cuoPorc,
            "cantidad_base" => $JwtAuth->desencriptar($resListaPromo->cantidad_base),
            "periodo" => $periodo,
            "resPeriodoInicio" => $resPeriodoInicio,
            "resPeriodoFin" => $resPeriodoFin,
            "tdImportePromo" => $tdImportePromo,
          );
          $arrayPromociones[] = $arraForeachPromo;
        }
      }

      if ($value->identificador == 'Producto') {
        $conceptoExplode = explode("-", $value->concepto);
        //SELECT * FROM kardex where status_kardex = 5 AND fecha = (SELECT max(fecha) from kardex where fecha < now());
        //;
        $sumaExistencias = DB::select("SELECT SUM(saldo_cantidad) AS existencia FROM kardex 
                    WHERE status_kardex = 6 AND fecha_kardex = (SELECT MAX(fecha_kardex) FROM kardex WHERE fecha_kardex < now() AND status_kardex = 6)
                    AND producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?)", [$token_Articulo]);

        $producto = DB::select("SELECT catprod.costeo,catprod.num_serie,catprod.num_lote,catprod.importado 
                    FROM in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                    JOIN teci_usuarios_catalogo AS users
                    WHERE catprod.token_cat_productos = ?
                    AND catprod.administrador = emp.id
                    AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa
                    AND empuser.personal = pers.id
                    AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        if ($producto[0]->num_serie == TRUE) {
          $serieProd = DB::select("SELECT detalm.token_detalle_almacen,detalm.num_serie,detalm.existencia
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
          //echo count($serieProd);
          if (count($serieProd) != 0) {
            $serie = array();
            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($serieProd) - 1; $i >= 0; $i--) {
                $arrayEach = array(
                  "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                  "num_serie" => $serieProd[$i]->num_serie,
                  "existencia" => $serieProd[$i]->existencia,
                );
                $serie[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($serieProd); $i++) {
                $arrayEach = array(
                  "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                  "num_serie" => $serieProd[$i]->num_serie,
                  "existencia" => $serieProd[$i]->existencia,
                );
                $serie[] = $arrayEach;
              }
            }
          } else {
            $serie = array();
          }
        } else {
          $serie = array();
        }

        if ($producto[0]->num_lote == TRUE) {
          $loteProd = DB::select("SELECT token_lote,numero_lote
                        FROM lote_prod 
                        WHERE id in (SELECT detalm.num_lote
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?)
                        ", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

          //echo count($loteProd);
          if (count($loteProd) != 0) {
            $lote = array();

            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($loteProd) - 1; $i >= 0; $i--) {
                $sumLote = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_lote" => $loteProd[$i]->token_lote,
                  "num_lote" => $loteProd[$i]->numero_lote,
                  "existencia" => $sumLote[0]->existencia,
                );
                $lote[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($loteProd); $i++) {
                $sumLote = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_lote" => $loteProd[$i]->token_lote,
                  "num_lote" => $loteProd[$i]->numero_lote,
                  "existencia" => $sumLote[0]->existencia,
                );
                $lote[] = $arrayEach;
              }
            }
          } else {
            $lote = array();
          }
        } else {
          $lote = array();
        }

        if ($producto[0]->importado == TRUE) {
          //$prodImportado = DB::select("SELECT catprod.importado,
          //ped.token_pedimento,ped.numero_pedimiento,ped.tipo_operacion,
          //ped.regimen,ped.destino,ped.tipo_cambio,ped.aduana FROM  pedimento_aduanal AS ped 
          //JOIN productos_importados AS importt
          //JOIN in_egr_catalogo_productos AS catprod 
          //WHERE ped.id = importt.pedimento
          //AND importt.existencia != 0
          //AND importt.producto = catprod.id
          //AND catprod.importado = true
          //AND catprod.status = true
          //AND catprod.token_cat_productos = ?
          //AND catprod.administrador = emp.id
          //AND emp.empresa_token = ?
          //AND emp.id = empuser.empresa
          //AND empuser.personal = pers.id
          //AND pers.usuario = users.id
          //AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
          $pedimentoProd = DB::select(
            "SELECT token_pedimento,numero_pedimento 
                        FROM pedimento_aduanal
                        WHERE id IN (SELECT detalm.importado
                            FROM detalle_almacen AS detalm
                            JOIN in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                            JOIN teci_usuarios_catalogo AS users
                            WHERE detalm.status_disponibilidad = TRUE
                            AND detalm.producto = catprod.id
                            AND catprod.token_cat_productos = ?
                            AND catprod.administrador = emp.id
                            AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa
                            AND empuser.personal = pers.id
                            AND pers.usuario = users.id
                            AND users.usuario_token = ?)",
            [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
          );
          if (count($pedimentoProd) != 0) {
            $pedimento = array();

            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($pedimentoProd) - 1; $i >= 0; $i--) {
                echo $pedimentoProd[$i]->token_pedimento;
                $sumImported = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                  "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                  "existencia" => $sumImported[0]->existencia,
                );
                $pedimento[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($pedimentoProd); $i++) {
                //echo $token_Articulo;
                $sumImported = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                  "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                  "existencia" => $sumImported[0]->existencia,
                );
                $pedimento[] = $arrayEach;
              }
            }
          } else {
            $pedimento = array();
          }
        } else {
          $pedimento = array();
        }

        $arraySerieLoteImport = array(
          "existKardex" => $sumaExistencias[0]->existencia,
          "serie" => $serie,
          "lote" => $lote,
          "pedimento" => $pedimento
        );

        $conceptoArticulo = $JwtAuth->desencriptar($conceptoExplode[0]) .
          " Marca:(" . $JwtAuth->desencriptar($conceptoExplode[1]) . ")";
      } else {
        $conceptoArticulo = $JwtAuth->desencriptar($value->concepto);
        $arraySerieLoteImport = [];
      }
      ///echo 'imaagen '.$JwtAuth->encriptar('default_prod.jpg').' ';
      if ($value->identificador == 'Producto') {
        if ($value->imagen == '' || !file_exists(Storage::path('public/root/' .
          $value->root_tkn . '/0002-cpp/catalogos/productos/'
          . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
          $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
        } else {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $value->root_tkn . '/0002-cpp/catalogos/productos/'
            . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
            $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen)));
        }
      } else {
        if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
        } else {

          $nameServImg = ServiciosModelo::join("main_empresas AS emp", "catalogo_servicios.administrador", "=", "emp.id")
            ->join("empresapersonal", "emp.id", "=", "empresapersonal.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empresapersonal.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'catalogo_servicios.token_cat_servicios' => $value->token_articulo,
            ])->get();

          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $value->root_tkn . '/0001-cpc/catalogos/servicios/' . $nameServImg[0]->fecha_sistema . '-' .
            $JwtAuth->generar($nameServImg[0]->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
        }
      }
      //echo $totalImpuesto;
      $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
      $importePartida = number_format($importePartida, 2, '.', ',');

      $arraForeach = array(
        "token_articulo" => $value->token_articulo,
        "identificador" => $value->identificador,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio),
        "sat" => $value->SAT,
        "clave" => $value->clave,
        "descripcion" => $value->descripcion,
        "concepto" => $conceptoArticulo,
        "arraySerieLoteImport" => $arraySerieLoteImport,
        "precioBaseConImp" => $precioBaseConImp,
        "precioBase" => $dataPrecioBase,
        "dataCantidad" => $dataCantidad,
        "imagen" => $imgArticulo,
        "importeTdescuento" => $importeTdescuento,
        "arrayDescuentos" => $arrayDescuentos,
        "arrayPromociones" => $arrayPromociones,
        "totalImpuesto" => number_format($totalImpuesto, 2, '.', ','),
        //"arrayDesgloseImpuestos" => $arrayDesgloseImpuestos,
        "importePartida" => $importePartida,
      );
      $arrayArticulos[] = $arraForeach;
    }

    return response()->json([
      'listaArticulos' => $arrayArticulos,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function cargaArticulosVentaFour(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    //echo $JwtAuth->encriptar('prueba1serv');
    $arrayArticulos = array();

    //echo $JwtAuth->encriptar('acer')." acer";

    $decimalesMoneda = DB::select(
      "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN teci_usuarios_catalogo AS users WHERE emp.moneda = catmon.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.personal = pers.id 
            AND pers.usuario = users.id AND users.usuario_token = ?",
      [$usuario->empresa_token, $usuario->user_token]
    );

    $catProdServ = DB::select(
      "SELECT total.token_articulo,total.identificador,total.concepto,total.clasificacion,
            total.genero,total.folio,total.clave,total.descripcion,total.precioBase,total.SAT,total.imagen,total.root_tkn,total.fecha_alta
            FROM ((SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_alta,'Producto' AS identificador,
                concat(prod.producto,'-',prod.marca) AS concepto,prod.clasificacion,gen.folio_genero AS genero,
                catprod.folio_sistema AS folio,prodsat.clave,prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,
                concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,prod.imagen,emp.root_tkn
                FROM productos AS prod JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_productos AS catprod
                JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice
                JOIN teci_catalogo_prodservsat AS prodsat JOIN teci_unidad_medida AS unimed
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                JOIN teci_usuarios_catalogo AS users WHERE catprod.producto = prod.id
                AND catprod.id = detprice.producto AND detprice.lista = price.id
                AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4'
                AND prod.genero = gen.id AND catprod.token_cat_productos IN (
                    SELECT catprod.token_cat_productos FROM in_egr_catalogo_productos AS catprod
                    JOIN eegr_compras_detalle AS detcomp JOIN eegr_compras AS buy JOIN eegr_compras_recepcion AS recept
                    JOIN in_egr_establecimientos_almacen AS det_alm WHERE catprod.id = detcomp.producto
                    AND detcomp.activo_fijo IS NULL AND detcomp.activo_intangible IS NULL
                    AND detcomp.numero_compra = buy.id AND detcomp.id = recept.detalle_compra
                    AND recept.recept_status = TRUE AND recept.id = det_alm.recepcion_compra
                    AND buy.status_recepcion = TRUE
                )
            AND catprod.catalogo_sat = prodsat.id AND prod.medida_entrada = unimed.id AND catprod.tipo_prod = 'pr'
            AND catprod.activo IS NULL AND catprod.status = TRUE AND catprod.administrador = emp.id
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?
            UNION ALL
            SELECT catserv.token_cat_servicios AS token_articulo,catserv.fechaAlta AS fecha_alta,'Servicio' AS identificador,
            serv.servicio AS concepto,serv.clasificacion,gen.folio_genero AS genero,catserv.folio AS folio,prodsat.clave,
            prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,
            serv.imagen,emp.root_tkn FROM servicios AS serv JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_servicios AS catserv 
            JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice JOIN teci_catalogo_prodservsat AS prodsat
            JOIN teci_unidad_medida AS unimed JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
            JOIN teci_usuarios_catalogo AS users WHERE catserv.servicio = serv.id AND catserv.id = detprice.servicio AND detprice.lista = price.id
            AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4' AND serv.genero = gen.id 
            AND serv.catalogo_sat = prodsat.id AND serv.medida_sat = unimed.id AND catserv.proceso = FALSE AND catserv.status = TRUE 
            AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?) as total) ORDER BY total.concepto DESC",
      [$decimalesMoneda[0]->decimales, $usuario->empresa_token, $usuario->user_token, $decimalesMoneda[0]->decimales, $usuario->empresa_token, $usuario->user_token]
    );

    foreach ($catProdServ as $value) {
      $arrayDescuentos = array();
      $arrayPromociones = array();
      $arrayDesgloseImpuestos = array();
      $arraySerieLoteImport = array();
      $arrayImportado = array();
      $token_Articulo = $value->token_articulo;
      $dataPrecioBase = $value->precioBase;

      //if ($value->identificador == 'Servicio') {
      //    $dataPrecioBase = $JwtAuth->desencriptar($dataPrecioBase);
      //}
      //echo ." ".;
      $dataCantidad = 1.00;
      $resTotalDataDesc = '';
      $importeTdescuento = 0.00;
      $totalImpuesto = floatVal(0);
      $importePartida = 0.00;
      $contadorDescuentos = 0;

      if ($value->identificador == 'Producto') {
        $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                    JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.producto_rel = catprod.id 
                    AND catprod.token_cat_productos = ? AND catprod.status = TRUE AND catprod.administrador = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.producto = catprod.id
                    AND catprod.token_cat_productos = ? AND descu.status_activacion = TRUE AND descu.status = TRUE 
                    AND descu.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaPromoModal = DB::select(
          "SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin
                    FROM ingr_catalogo_promociones AS promo JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                    WHERE promo.id = detpromo.promocion AND detpromo.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
          [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
        );
      } else {
        $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_catalogo_impuestos AS catimp
                    JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.servicio_rel = catserv.id 
                    AND catserv.token_cat_servicios = ? AND catserv.status = TRUE AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND descu.status_activacion = TRUE AND descu.status = TRUE AND descu.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        $listaPromoModal = DB::select("SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin FROM ingr_catalogo_promociones AS promo 
                    JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE promo.id = detpromo.promocion AND detpromo.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
      }

      if (count($consultaImpArticulo) != 0) {
        //$arrayImpuestos = json_decode($JwtAuth->desencriptar($consultaImpArticulo[0]->impuestos));
        for ($i = 0; $i < count($consultaImpArticulo); $i++) {
          $tknImpuesto = $consultaImpArticulo[$i]->token_cat_impuestos;
          $catImpuestos = DB::select("SELECT catimp.id,catimp.token_cat_impuestos,catimp.alias,catimp.clasificacion_impuestos,
                        catimp.ret_tras,catimp.por_cuo,catimp.importe,tip.concepto,tip.tipo FROM cont_catalogo_impuestos AS catimp  
                        JOIN cont_catalogo_impuestos_tipo AS tip JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE catimp.token_cat_impuestos = ? AND catimp.impuesto = tip.id AND catimp.status = TRUE
                        AND catimp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                        AND pers.usuario = users.id AND users.usuario_token = ?", [$tknImpuesto, $usuario->empresa_token, $usuario->user_token]);

          if (count($catImpuestos) == 1) {
            $resImpDat = $catImpuestos[0];
            //$dataPrecioBase,$totalImpuesto 
            $cantBaseImpuesto = $catImpuestos[0]->importe;

            if ($resImpDat->por_cuo == TRUE) {
              $importeBase = explode("%", $cantBaseImpuesto);
              //echo $importeBase[0];
              $multi = '';
              if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                $importeBase2 = explode(".", $importeBase[0]);
                $multi = '0.00' . $importeBase2[1];
              } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                $multi = '0.0' . $importeBase[0];
              } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                $multi = '0.' . $importeBase[0];
                //echo $multi;
              } else if ($importeBase[0] == 100) {
                $multi = 1;
              }
              //echo $importePartida ;
              $totalImp =  floatval($dataPrecioBase) * floatval($multi);
            } else {
              $importeBase = str_replace("$", "", $cantBaseImpuesto);
              $importeBase = str_replace(",", "", $importeBase);
              $totalImp = floatval($importeBase);
            }

            if ($resImpDat->ret_tras == TRUE) {
              //echo $totalImpuesto;
              $totalImpuesto = $totalImpuesto + $totalImp;
            }

            if ($resImpDat->ret_tras == FALSE) {
              $totalImpuesto = $totalImpuesto - $totalImp;
            }
          } else {
            $totalImpuesto = $totalImpuesto + 0;
          }
        }
      } else {
        $totalImpuesto = $totalImpuesto;
      }

      $precioBaseConImp = number_format($dataPrecioBase + $totalImpuesto, 2, '.', ',');

      //echo count($listaDescModal).", "; 

      if (count($listaDescModal) == 0) {
        $importeTdescuento = 0.00;
      } else {
        $cantidadBaseDesc = $JwtAuth->desencriptar($listaDescModal[0]->cantidad_base);

        if ($listaDescModal[0]->cuo_porc == TRUE) {
          $importeBase = explode("%", $cantidadBaseDesc);
          $multi = '';
          if ($importeBase[0] > 0 && $importeBase[0] < 1) {
            $importeBase2 = explode(".", $importeBase[0]);
            $multi = '0.00' . $importeBase2[1];
          } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
            $multi = '0.0' . $importeBase[0];
          } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
            $multi = '0.' . $importeBase[0];
          } else if ($importeBase[0] == 100) {
            $multi = 1;
          }
          $importeTdescuento = $dataPrecioBase * floatval($multi);
        } else {
          $importeBase = explode("$", $cantidadBaseDesc);
          $importeTdescuento = floatval($importeBase[1]);
        }
        //$importeTdescuento = number_format($importeTdescuento,2,'.',',');

        foreach ($listaDescModal as $resListaDesc) {
          //echo $resListaDesc->id;
          if ($resListaDesc->cuo_porc == 0) {
            $cuoPorc = 'cuota';
          } else {
            $cuoPorc = 'porcentaje';
          }

          if ($resListaDesc->aplicacion == 'usa') {
            $periodo = 'Eventual';
            $resPeriodoInicio = '-';
            $resPeriodoFin = '-';
          } else if ($resListaDesc->aplicacion == 'ind') {
            $periodo = 'Periodo Indeterminado';
            $resPeriodoInicio = '';
            $resPeriodoFin = '-';
            $valorFechaInicio = $resListaDesc->fecha_inicio;
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
          } else if ($resListaDesc->aplicacion == 'det') {
            $periodo = 'Periodo Determinado';
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = '';
            $resPeriodoFin = '';
            $valorFechaInicio = $resListaDesc->fecha_inicio;
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
            $valorFechaFin = $resListaDesc->fecha_fin;
            $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
          }

          if (count($arrayDescuentos) == 0) {
            $valorDescuento = $importeTdescuento;
          } else {
            $valorDescuento = floatVal('0.00');
          }

          if ($contadorDescuentos == 0) {
            $checkDesc = '0TRUE';
          } else {
            $checkDesc = '1FALSE';
          }

          $arraForeachDesc = array(
            "token_descuentos" => $resListaDesc->token_descuentos,
            "folioDesc" => $JwtAuth->generar($resListaDesc->folio),
            "aliasDesc" => $JwtAuth->desencriptar($resListaDesc->alias),
            "conceptoDesc" => $JwtAuth->desencriptar($resListaDesc->concepto),
            "cuoPorc" => $cuoPorc,
            "cantidad_base" => $JwtAuth->desencriptar($resListaDesc->cantidad_base),
            "periodo" => $periodo,
            "resPeriodoInicio" => $resPeriodoInicio,
            "resPeriodoFin" => $resPeriodoFin,
            "tdImporteDesc" => number_format($valorDescuento, 2, '.', ','),
            "rescheck" => $checkDesc,
          );
          $arrayDescuentos[] = $arraForeachDesc;
          $contadorDescuentos++;
        }
      }

      $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
      $importePartida = number_format($importePartida, 2, '.', ',');

      if (count($listaPromoModal) > 0) {
        foreach ($listaPromoModal as $resListaPromo) {
          if ($resListaPromo->cuo_porc == 0) {
            $cuoPorc = 'cuota';
          } else {
            $cuoPorc = 'porcentaje';
          }

          if ($resListaPromo->aplicacion == 'usa') {
            $periodo = 'Eventual';
            $resPeriodoInicio = '-';
            $resPeriodoFin = '-';
          } else if ($resListaPromo->aplicacion == 'ind') {
            $periodo = 'Periodo Indeterminado';
            $resPeriodoInicio = '';
            $resPeriodoFin = '-';
            $valorFechaInicio = $resListaPromo->fecha_inicio;
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
          } else if ($resListaPromo->aplicacion == 'det') {
            $periodo = 'Periodo Determinado';
            //da_te_default_timezone_set('America/Mexico_City');
            $resPeriodoInicio = '';
            $resPeriodoFin = '';
            $valorFechaInicio = $resListaPromo->fecha_inicio;
            $resPeriodoInicio = gmdate('Y-m-d H:i:s', $valorFechaInicio);
            $valorFechaFin = $resListaPromo->fecha_fin;
            $resPeriodoFin = gmdate('Y-m-d H:i:s', $valorFechaFin);
          }
          //$importePartida

          $cantidadBasePromo = $JwtAuth->desencriptar($resListaPromo->cantidad_base);
          //echo $cantidadBasePromo;
          if ($resListaPromo->cuo_porc == TRUE) {
            $importeBase = explode("%", $cantidadBasePromo);
            $multi = '';
            if ($importeBase[0] > 0 && $importeBase[0] < 1) {
              $importeBase2 = explode(".", $importeBase[0]);
              $multi = '0.00' . $importeBase2[1];
            } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
              $multi = '0.0' . $importeBase[0];
            } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
              $multi = '0.' . $importeBase[0];
            } else if ($importeBase[0] == 100) {
              $multi = 1;
            }
            $tdImportePromo = $dataPrecioBase * floatval($multi);
          } else {
            $importeBase = explode("$", $cantidadBasePromo);
            $tdImportePromo = floatval($importeBase[1]);
          }
          $tdImportePromo = number_format($tdImportePromo, 2, '.', ',');
          //echo $tdImportePromo; 
          $arraForeachPromo = array(
            "token_promocion" => $resListaPromo->token_promocion,
            "folioPromo" => $JwtAuth->generar($resListaPromo->folio),
            "aliasPromo" => $JwtAuth->desencriptar($resListaPromo->alias),
            "conceptoPromo" => $JwtAuth->desencriptar($resListaPromo->concepto),
            "cuoPorc" => $cuoPorc,
            "cantidad_base" => $JwtAuth->desencriptar($resListaPromo->cantidad_base),
            "periodo" => $periodo,
            "resPeriodoInicio" => $resPeriodoInicio,
            "resPeriodoFin" => $resPeriodoFin,
            "tdImportePromo" => $tdImportePromo,
          );
          $arrayPromociones[] = $arraForeachPromo;
        }
      }

      if ($value->identificador == 'Producto') {
        $conceptoExplode = explode("-", $value->concepto);
        //SELECT * FROM kardex where status_kardex = 5 AND fecha = (SELECT max(fecha) from kardex where fecha < now());
        //;
        $sumaExistencias = DB::select("SELECT SUM(saldo_cantidad) AS existencia FROM kardex 
                    WHERE status_kardex = 6 AND fecha_kardex = (SELECT MAX(fecha_kardex) FROM kardex WHERE fecha_kardex < now() AND status_kardex = 6)
                    AND producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?)", [$token_Articulo]);

        $producto = DB::select("SELECT catprod.costeo,catprod.num_serie,catprod.num_lote,catprod.importado 
                    FROM in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                    JOIN teci_usuarios_catalogo AS users
                    WHERE catprod.token_cat_productos = ?
                    AND catprod.administrador = emp.id
                    AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa
                    AND empuser.personal = pers.id
                    AND pers.usuario = users.id
                    AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

        if ($producto[0]->num_serie == TRUE) {
          $serieProd = DB::select("SELECT detalm.token_detalle_almacen,detalm.num_serie,detalm.existencia
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);
          //echo count($serieProd);
          if (count($serieProd) != 0) {
            $serie = array();
            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($serieProd) - 1; $i >= 0; $i--) {
                $arrayEach = array(
                  "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                  "num_serie" => $serieProd[$i]->num_serie,
                  "existencia" => $serieProd[$i]->existencia,
                );
                $serie[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($serieProd); $i++) {
                $arrayEach = array(
                  "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                  "num_serie" => $serieProd[$i]->num_serie,
                  "existencia" => $serieProd[$i]->existencia,
                );
                $serie[] = $arrayEach;
              }
            }
          } else {
            $serie = array();
          }
        } else {
          $serie = array();
        }

        if ($producto[0]->num_lote == TRUE) {
          $loteProd = DB::select("SELECT token_lote,numero_lote
                        FROM lote_prod 
                        WHERE id in (SELECT detalm.num_lote
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?)
                        ", [$token_Articulo, $usuario->empresa_token, $usuario->user_token]);

          //echo count($loteProd);
          if (count($loteProd) != 0) {
            $lote = array();

            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($loteProd) - 1; $i >= 0; $i--) {
                $sumLote = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_lote" => $loteProd[$i]->token_lote,
                  "num_lote" => $loteProd[$i]->numero_lote,
                  "existencia" => $sumLote[0]->existencia,
                );
                $lote[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($loteProd); $i++) {
                $sumLote = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$loteProd[$i]->token_lote, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_lote" => $loteProd[$i]->token_lote,
                  "num_lote" => $loteProd[$i]->numero_lote,
                  "existencia" => $sumLote[0]->existencia,
                );
                $lote[] = $arrayEach;
              }
            }
          } else {
            $lote = array();
          }
        } else {
          $lote = array();
        }

        if ($producto[0]->importado == TRUE) {
          //$prodImportado = DB::select("SELECT catprod.importado,
          //ped.token_pedimento,ped.numero_pedimiento,ped.tipo_operacion,
          //ped.regimen,ped.destino,ped.tipo_cambio,ped.aduana FROM  pedimento_aduanal AS ped 
          //JOIN productos_importados AS importt
          //JOIN in_egr_catalogo_productos AS catprod 
          //WHERE ped.id = importt.pedimento
          //AND importt.existencia != 0
          //AND importt.producto = catprod.id
          //AND catprod.importado = true
          //AND catprod.status = true
          //AND catprod.token_cat_productos = ?
          //AND catprod.administrador = emp.id
          //AND emp.empresa_token = ?
          //AND emp.id = empuser.empresa
          //AND empuser.personal = pers.id
          //AND pers.usuario = users.id
          //AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
          $pedimentoProd = DB::select(
            "SELECT token_pedimento,numero_pedimento 
                        FROM pedimento_aduanal
                        WHERE id IN (SELECT detalm.importado
                            FROM detalle_almacen AS detalm
                            JOIN in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                            JOIN teci_usuarios_catalogo AS users
                            WHERE detalm.status_disponibilidad = TRUE
                            AND detalm.producto = catprod.id
                            AND catprod.token_cat_productos = ?
                            AND catprod.administrador = emp.id
                            AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa
                            AND empuser.personal = pers.id
                            AND pers.usuario = users.id
                            AND users.usuario_token = ?)",
            [$token_Articulo, $usuario->empresa_token, $usuario->user_token]
          );
          if (count($pedimentoProd) != 0) {
            $pedimento = array();

            if ($producto[0]->costeo == 'UEPS') {
              for ($i = count($pedimentoProd) - 1; $i >= 0; $i--) {
                echo $pedimentoProd[$i]->token_pedimento;
                $sumImported = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                  "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                  "existencia" => $sumImported[0]->existencia,
                );
                $pedimento[] = $arrayEach;
              }
            }
            if ($producto[0]->costeo == 'PEPS') {
              for ($i = 0; $i < count($pedimentoProd); $i++) {
                //echo $token_Articulo;
                $sumImported = DB::select(
                  "SELECT SUM(existencia) AS existencia
                                    FROM detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.administrador = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                  [$pedimentoProd[$i]->token_pedimento, $token_Articulo, $usuario->empresa_token, $usuario->user_token]
                );
                $arrayEach = array(
                  "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                  "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                  "existencia" => $sumImported[0]->existencia,
                );
                $pedimento[] = $arrayEach;
              }
            }
          } else {
            $pedimento = array();
          }
        } else {
          $pedimento = array();
        }

        $arraySerieLoteImport = array(
          "existKardex" => $sumaExistencias[0]->existencia,
          "serie" => $serie,
          "lote" => $lote,
          "pedimento" => $pedimento
        );

        $conceptoArticulo = $JwtAuth->desencriptar($conceptoExplode[0]) .
          " Marca:(" . $JwtAuth->desencriptar($conceptoExplode[1]) . ")";
      } else {
        $conceptoArticulo = $JwtAuth->desencriptar($value->concepto);
        $arraySerieLoteImport = [];
      }
      ///echo 'imaagen '.$JwtAuth->encriptar('default_prod.jpg').' ';
      if ($value->identificador == 'Producto') {
        if ($value->imagen == '' || !file_exists(Storage::path('public/root/' .
          $value->root_tkn . '/0002-cpp/catalogos/productos/'
          . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
          $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
        } else {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $value->root_tkn . '/0002-cpp/catalogos/productos/'
            . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' .
            $JwtAuth->generar($value->folio) . '-' . $value->fecha_alta . '/' . $JwtAuth->desencriptar($value->imagen)));
        }
      } else {
        if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
        } else {

          $nameServImg = ServiciosModelo::join("main_empresas AS emp", "catalogo_servicios.administrador", "=", "emp.id")
            ->join("empresapersonal", "emp.id", "=", "empresapersonal.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empresapersonal.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'catalogo_servicios.token_cat_servicios' => $value->token_articulo,
            ])->get();

          $imgArticulo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $value->root_tkn . '/0001-cpc/catalogos/servicios/' . $nameServImg[0]->fecha_sistema . '-' .
            $JwtAuth->generar($nameServImg[0]->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
        }
      }
      //echo $totalImpuesto;
      $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
      $importePartida = number_format($importePartida, 2, '.', ',');

      $arraForeach = array(
        "token_articulo" => $value->token_articulo,
        "identificador" => $value->identificador,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->genero) . '-' . $JwtAuth->generar($value->folio),
        "sat" => $value->SAT,
        "clave" => $value->clave,
        "descripcion" => $value->descripcion,
        "concepto" => $conceptoArticulo,
        "arraySerieLoteImport" => $arraySerieLoteImport,
        "precioBaseConImp" => $precioBaseConImp,
        "precioBase" => $dataPrecioBase,
        "dataCantidad" => $dataCantidad,
        "imagen" => $imgArticulo,
        "importeTdescuento" => $importeTdescuento,
        "arrayDescuentos" => $arrayDescuentos,
        "arrayPromociones" => $arrayPromociones,
        "totalImpuesto" => number_format($totalImpuesto, 2, '.', ','),
        //"arrayDesgloseImpuestos" => $arrayDesgloseImpuestos,
        "importePartida" => $importePartida,
      );
      $arrayArticulos[] = $arraForeach;
    }

    return response()->json([
      'listaArticulos' => $arrayArticulos,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function detalleVentaArticulo(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $array_articulo = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        //'token_descuento' => 'required|string',
        'tkn_articulo' => 'required|string',
        'cantidad' => 'required|string',
        'descuento' => 'required|string',
        'arrayDescuentos' => 'array',
        'promocion' => 'required|string',
        //'arrayPromociones' => 'required|array',
        'importePartida' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que hemos recibido es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $tokenArticulo = $parametrosArray['tkn_articulo'];
        $paramCantidad = $parametrosArray['cantidad'];
        $paramDescuento = $parametrosArray['descuento'];
        $arrayDescuentos = $parametrosArray['arrayDescuentos'];
        $totalPromociones = $parametrosArray['promocion'];
        $totalImpuesto = floatVal('0.00');
        $xplodeImportePartida = str_replace("$", "", $parametrosArray['importePartida']);
        $xplodeImportePartida = str_replace(",", "", $xplodeImportePartida);

        $totalImpretenido = 0;
        $totalImptrasladado = 0;
        $listaRetenidos = array();
        $listaTrasladado = array();
        $clasificacionImpIva = 0;
        $clasificacionImpIsRet = 0;
        $clasificacionImpIvaRet = 0;
        $clasificacionImpIeps = 0;
        $clasificacionImpOtrImpFed = 0;
        $clasificacionImpOtrImpLoc = 0;

        $catProdServ = DB::select(
          "SELECT catserv.token_cat_servicios,catserv.fecha_sistema,catserv.folio_sistema,catserv.fechaAlta,serv.servicio,
                    serv.clasificacion,gen.folio_genero AS genero,catserv.folio,prodsat.clave,prodsat.descripcion,catserv.precioBase, 
                    concat(unimed.unidad_medida,' - ',unimed.sat_clave) as sat,serv.imagen 
                    FROM servicios AS serv  JOIN sos_ps_genero AS gen JOIN in_egr_catalogo_servicios AS catserv JOIN teci_catalogo_prodservsat AS prodsat 
                    JOIN teci_unidad_medida AS unimed  JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                    WHERE catserv.token_cat_servicios = ?  AND catserv.servicio = serv.id AND serv.genero = gen.id AND serv.catalogo_sat = prodsat.id AND 
                    serv.medida_sat = unimed.id AND catserv.proceso = FALSE AND catserv.status = TRUE AND catserv.administrador = emp.id AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
          [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]
        );

        if (count($catProdServ) == 1) {
          $conceptoArticulo = $JwtAuth->desencriptar($catProdServ[0]->servicio);

          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id
                        AND pers.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

          //echo $JwtAuth->encriptar($catProdServ[0]->precioBase);exit;

          $precioBase = str_replace("$", "", $JwtAuth->desencriptar($catProdServ[0]->precioBase));

          /*$consultaImpServ = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art 
                        JOIN cont_catalogo_impuestos AS catimp
                        JOIN cont_catalogo_impuestos_tipo AS tip
                        JOIN in_egr_catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp 
                        JOIN main_empresa_usuario AS empuser 
                        JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users 
                        WHERE catimp.id = imp_art.impuestos 
                        AND imp_art.servicio_rel = catserv.id 
                        AND catserv.token_cat_servicios = ? 
                        AND catserv.status = TRUE 
                        AND catserv.administrador = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                        AND users.usuario_token = ?",[$catProdServ[0]->token_cat_servicios,$usuario->empresa_token,$usuario->user_token]);*/

          $consultaImpServ = DB::table("in_egr_impuestos_articulos AS imp_art")
            ->join("cont_catalogo_impuestos AS catimp", "imp_art.impuestos", "=", "catimp.id")
            ->join("cont_catalogo_impuestos_tipo AS tip", "catimp.impuesto", "=", "tip.id")
            ->join("in_egr_catalogo_servicios AS catserv", "imp_art.servicio_rel", "=", "catserv.id")
            ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              "catserv.token_cat_servicios" => $catProdServ[0]->token_cat_servicios,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          if (count($consultaImpServ) != 0) {
            foreach ($consultaImpServ as $resImpDat) {

              if ($resImpDat->tipo == 001) {
                $tipoImpuesto = 'Impuesto Federal';
              }
              if ($resImpDat->tipo == 002) {
                $tipoImpuesto = 'Impuesto Estatal';
              }
              if ($resImpDat->tipo == 003) {
                $tipoImpuesto = 'Impuesto Local';
              }

              if ($resImpDat->clasificacion_impuestos == 'iva') {
                $clasificacionImp = 'IVA';
              }
              if ($resImpDat->clasificacion_impuestos == 'isrr') {
                $clasificacionImp = 'ISR RETENIDO';
              }
              if ($resImpDat->clasificacion_impuestos == 'ivar') {
                $clasificacionImp = 'IVA RETENIDO';
              }
              if ($resImpDat->clasificacion_impuestos == 'ieps') {
                $clasificacionImp = 'IEPS';
              }
              if ($resImpDat->clasificacion_impuestos == 'oidf') {
                $clasificacionImp = 'OTROS IMPUESTOS FEDERALES';
              }
              if ($resImpDat->clasificacion_impuestos == 'oilo') {
                $clasificacionImp = 'OTROS IMPUESTOS LOCALES';
              }

              if ($resImpDat->ret_tras == FALSE) {
                $reTras = 'retenido';
              } else {
                $reTras = 'trasladado';
              }

              if ($resImpDat->por_cuo == FALSE) {
                $cuoPorc = 'cuota';
              } else {
                $cuoPorc = 'porcentaje';
              }

              $cantBaseImpuesto = $resImpDat->importe;

              if ($resImpDat->por_cuo == TRUE) {
                $importeBase = explode("%", $cantBaseImpuesto);
                //echo $importeBase[0];
                $multi = '';
                if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                  $importeBase2 = explode(".", $importeBase[0]);
                  $multi = '0.00' . $importeBase2[1];
                } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                  $multi = '0.0' . $importeBase[0];
                } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                  $multi = '0.' . $importeBase[0];
                  //echo $multi;
                } else if ($importeBase[0] == 100) {
                  $multi = 1;
                }
                //echo $importePartida ;
                $totalImp =  floatval($xplodeImportePartida) * floatval($multi);
              } else {
                $importeBase = str_replace("$", "", $cantBaseImpuesto);
                $importeBase = str_replace(",", "", $importeBase);
                $totalImp = floatval($importeBase);
              }


              $arrayIntImpuestos = array(
                "token_cat_impuestos" => $resImpDat->token_cat_impuestos,
                "tipoImpuesto" => $tipoImpuesto,
                "clasificacionImp" => $clasificacionImp,
                "concepto" => $resImpDat->concepto,
                "alias" => $resImpDat->alias,
                "reTras" => $reTras,
                "cuoPorc" => $cuoPorc,
                "ImporteImpuesto" => $cantBaseImpuesto,
                "totalImpPart" => number_format($totalImp, 2, '.', ',')
              );

              if ($resImpDat->ret_tras == TRUE) {
                $totalImpuesto = $totalImpuesto + $totalImp;
                $totalImptrasladado = $totalImptrasladado + $totalImpuesto;
                $listaTrasladado[] = $arrayIntImpuestos;
              }

              if ($resImpDat->ret_tras == FALSE) {
                $totalImpuesto = $totalImpuesto - $totalImp;
                $totalImpretenido = $totalImpretenido + $totalImpuesto;
                $listaRetenidos[] = $arrayIntImpuestos;
              }

              if ($resImpDat->clasificacion_impuestos == 'iva') {
                $txtclasifImp = 'iva';
                $clasificacionImpIva = $clasificacionImpIva + $totalImpuesto;
              }
              if ($resImpDat->clasificacion_impuestos == 'isrr') {
                $txtclasifImp = 'isr retenido';
                $clasificacionImpIsRet = $clasificacionImpIsRet + $totalImpuesto;
              }
              if ($resImpDat->clasificacion_impuestos == 'ivar') {
                $txtclasifImp = 'iva retenido';
                $clasificacionImpIvaRet = $clasificacionImpIvaRet + $totalImpuesto;
              }
              if ($resImpDat->clasificacion_impuestos == 'ieps') {
                $txtclasifImp = 'ieps';
                $clasificacionImpIeps = $clasificacionImpIeps + $totalImpuesto;
              }
              if ($resImpDat->clasificacion_impuestos == 'oidf') {
                $txtclasifImp = 'otros impuestos federales';
                $clasificacionImpOtrImpFed = $clasificacionImpOtrImpFed + $totalImpuesto;
              }
              if ($resImpDat->clasificacion_impuestos == 'oilo') {
                $txtclasifImp = 'otros impuestos locales';
                $clasificacionImpOtrImpLoc = $clasificacionImpOtrImpLoc + $totalImpuesto;
              }
            }
          } else {
            $totalImpuesto = $totalImpuesto;
            //$totalImpuesto = $totalImpuesto + 0;
          }

          $paramImportePartidaImpuesto = number_format($xplodeImportePartida + $totalImpuesto, 2, '.', ',');
          //$impuestoPositivo = str_replace("-","",$totalImpuesto);
          if ($JwtAuth->desencriptar($catProdServ[0]->imagen) == 'default-servicios.jpg') {
            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($catProdServ[0]->imagen)));
          } else {
            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
              $selectEmp[0]->root_tkn . '/0001-cpc/catalogos/servicios/' . $catProdServ[0]->fecha_sistema . '-' .
              $JwtAuth->generar($catProdServ[0]->folio_sistema) . '/' . $JwtAuth->desencriptar($catProdServ[0]->imagen)));
          }

          $arrayVenta = array(
            "token_articulo" => $catProdServ[0]->token_cat_servicios,
            "idenArt" => 'servicio',
            "imagen" => $logo_serv,
            "clasificacion" => $JwtAuth->generar($catProdServ[0]->clasificacion) . "-" .
              $JwtAuth->generar($catProdServ[0]->genero) . "-" . $JwtAuth->generar($catProdServ[0]->folio),
            "sat" => $catProdServ[0]->sat,
            "clave" => $catProdServ[0]->clave,
            "descripcion" => $catProdServ[0]->descripcion,
            "concepto" => $conceptoArticulo,
            "precioBase" => $precioBase,
            "cantidad" => $paramCantidad,
            "paramDescuento" => $paramDescuento,
            "totalPromociones" => $totalPromociones,
            "totalImpretenido" => $JwtAuth->conversionPositivos($totalImpretenido),
            "listaRetenidos" => $listaRetenidos,
            "totalImptrasladado" => $JwtAuth->conversionPositivos($totalImptrasladado),
            "listaTrasladado" => $listaTrasladado,
            "paramImportePartida" => number_format($xplodeImportePartida, 2, '.', ','),
            "importeImpuestos" => $JwtAuth->conversionPositivos($totalImpuesto),
            "paramImportePartidaImpuesto" => $paramImportePartidaImpuesto,
            "clasificacionImpIva" => $JwtAuth->conversionPositivos($clasificacionImpIva),
            "clasificacionImpIsRet" => $JwtAuth->conversionPositivos($clasificacionImpIsRet),
            "clasificacionImpIvaRet" => $JwtAuth->conversionPositivos($clasificacionImpIvaRet),
            "clasificacionImpIeps" => $JwtAuth->conversionPositivos($clasificacionImpIeps),
            "clasificacionImpOtrImpFed" => $JwtAuth->conversionPositivos($clasificacionImpOtrImpFed),
            "clasificacionImpOtrImpLoc" => $JwtAuth->conversionPositivos($clasificacionImpOtrImpLoc),
          );
          $array_articulo[] = $arrayVenta;
        }

        return response()->json([
          'listaArticulos' => $array_articulo,
          'codigo' => 200,
          'status' => 'success'
        ]);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleVentaArticuloPr(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $tokenArticulo = $parametros->tkn_articulo;
    $lotSerAdu = $parametros->lotSerAdu;
    //echo $lotSerAdu[0];
    $paramCantidad = $parametros->cantidad;
    $paramDescuento = $parametros->descuento;
    $arrayDescuentos = $parametros->arrayDescuentos;
    $totalPromociones = $parametros->promocion;
    $totalImpuesto = floatVal('0.00');
    $xplodeImportePartida = str_replace("$", "", $parametros->importePartida);
    $xplodeImportePartida = str_replace(",", "", $xplodeImportePartida);

    $totalImpretenido = 0;
    $totalImptrasladado = 0;
    $listaRetenidos = array();
    $listaTrasladado = array();
    $clasificacionImpIva = 0;
    $clasificacionImpIsRet = 0;
    $clasificacionImpIvaRet = 0;
    $clasificacionImpIeps = 0;
    $clasificacionImpOtrImpFed = 0;
    $clasificacionImpOtrImpLoc = 0;

    $dataProd = DB::select("SELECT catprod.token_cat_productos,prod.producto,prod.marca,
            prod.clasificacion,gen.folio_genero AS genero,
            catprod.folio_sistema,prodsat.clave,prodsat.descripcion,catprod.costo_aplicable AS precioBase,
            concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,prod.imagen
            FROM productos AS prod JOIN sos_ps_genero AS gen
            JOIN in_egr_catalogo_productos AS catprod
            JOIN compras AS comp JOIN eegr_compras_detalle AS detcomp
            JOIN teci_catalogo_prodservsat AS prodsat JOIN teci_unidad_medida AS unimed
            JOIN main_empresas AS emp
            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
            JOIN teci_usuarios_catalogo AS users
            WHERE catprod.producto = prod.id
            AND prod.genero = gen.id
            AND prod.id = detcomp.producto
            AND detcomp.numero_compra = comp.id
            AND catprod.catalogo_sat = prodsat.id
            AND prod.medida_entrada = unimed.id
            AND catprod.token_cat_productos = ?
            AND catprod.tipo_prod = 'pr'
            AND catprod.activo = 'FALSE'
            AND catprod.status = TRUE
            AND catprod.administrador = emp.id
            AND emp.empresa_token = ?
            AND emp.id = empuser.empresa
            AND empuser.personal = pers.id
            AND pers.usuario = users.id
            AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

    if (count($dataProd) == 1) {
      $conceptoArticulo = $JwtAuth->desencriptar($dataProd[0]->producto) .
        " Marca: " . $JwtAuth->desencriptar($dataProd[0]->marca);

      $precioBase = str_replace("$", "", $JwtAuth->desencriptar($dataProd[0]->precioBase));

      $consultaImpArticulo = DB::select("SELECT catprod.impuestos 
                FROM in_egr_catalogo_productos AS catprod
                JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser
                JOIN vhum_empleados_catalogo AS pers 
                JOIN teci_usuarios_catalogo AS users
                WHERE catprod.token_cat_productos = ?
                AND catprod.status = TRUE
                AND catprod.administrador = emp.id 
                AND emp.empresa_token = ?
                AND emp.id = empuser.empresa
                AND empuser.personal = pers.id
                AND pers.usuario = users.id
                AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

      if (count($consultaImpArticulo) != 0) {
        $arrayImpuestos = json_decode($JwtAuth->desencriptar($consultaImpArticulo[0]->impuestos));
        for ($i = 0; $i < count($arrayImpuestos); $i++) {
          $tknImpuesto = $arrayImpuestos[$i];
          $catImpuestos = DB::select("SELECT catimp.id,catimp.token_cat_impuestos,catimp.alias,catimp.clasificacion_impuestos,
                        catimp.ret_tras,catimp.por_cuo,catimp.importe,tip.concepto,tip.tipo
                        FROM cont_catalogo_impuestos AS catimp  
                        JOIN cont_catalogo_impuestos_tipo AS tip
                        JOIN main_empresas AS emp 
                        JOIN main_empresa_usuario AS empuser
                        JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users
                        WHERE catimp.token_cat_impuestos = ?
                        AND catimp.impuesto = tip.id
                        AND catimp.status = TRUE
                        AND catimp.empresa = emp.id 
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?", [$tknImpuesto, $usuario->empresa_token, $usuario->user_token]);

          if (count($catImpuestos) == 1) {
            $resImpDat = $catImpuestos[0];
            //$dataPrecioBase,$totalImpuesto 

            if ($resImpDat->tipo == 001) {
              $tipoImpuesto = 'Impuesto Federal';
            }
            if ($resImpDat->tipo == 002) {
              $tipoImpuesto = 'Impuesto Estatal';
            }
            if ($resImpDat->tipo == 003) {
              $tipoImpuesto = 'Impuesto Local';
            }


            if ($resImpDat->clasificacion_impuestos == 'iva') {
              $clasificacionImp = 'IVA';
            }
            if ($resImpDat->clasificacion_impuestos == 'isrr') {
              $clasificacionImp = 'ISR RETENIDO';
            }
            if ($resImpDat->clasificacion_impuestos == 'ivar') {
              $clasificacionImp = 'IVA RETENIDO';
            }
            if ($resImpDat->clasificacion_impuestos == 'ieps') {
              $clasificacionImp = 'IEPS';
            }
            if ($resImpDat->clasificacion_impuestos == 'oidf') {
              $clasificacionImp = 'OTROS IMPUESTOS FEDERALES';
            }
            if ($resImpDat->clasificacion_impuestos == 'oilo') {
              $clasificacionImp = 'OTROS IMPUESTOS LOCALES';
            }

            if ($resImpDat->ret_tras == FALSE) {
              $reTras = 'retenido';
            } else {
              $reTras = 'trasladado';
            }

            if ($resImpDat->por_cuo == FALSE) {
              $cuoPorc = 'cuota';
            } else {
              $cuoPorc = 'porcentaje';
            }

            $cantBaseImpuesto = $catImpuestos[0]->importe;

            if ($resImpDat->por_cuo == TRUE) {
              $importeBase = explode("%", $cantBaseImpuesto);
              //echo $importeBase[0];
              $multi = '';
              if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                $importeBase2 = explode(".", $importeBase[0]);
                $multi = '0.00' . $importeBase2[1];
              } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                $multi = '0.0' . $importeBase[0];
              } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                $multi = '0.' . $importeBase[0];
                //echo $multi;
              } else if ($importeBase[0] == 100) {
                $multi = 1;
              }
              //echo $importePartida ;
              $totalImp =  floatval($xplodeImportePartida) * floatval($multi);
            } else {
              $importeBase = str_replace("$", "", $cantBaseImpuesto);
              $importeBase = str_replace(",", "", $importeBase);
              $totalImp = floatval($importeBase);
            }

            $arrayIntImpuestos = array(
              "token_cat_impuestos" => $resImpDat->token_cat_impuestos,
              "tipoImpuesto" => $tipoImpuesto,
              "clasificacionImp" => $clasificacionImp,
              "concepto" => $resImpDat->concepto,
              "alias" => $resImpDat->alias,
              "reTras" => $reTras,
              "cuoPorc" => $cuoPorc,
              "ImporteImpuesto" => $cantBaseImpuesto,
              "totalImpPart" => number_format($totalImp, 2, '.', ',')
            );

            if ($resImpDat->ret_tras == TRUE) {
              $totalImpuesto = $totalImpuesto + $totalImp;
              $totalImptrasladado = $totalImptrasladado + $totalImpuesto;
              $listaTrasladado[] = $arrayIntImpuestos;
            }

            if ($resImpDat->ret_tras == FALSE) {
              $totalImpuesto = $totalImpuesto - $totalImp;
              $totalImpretenido = $totalImpretenido + $totalImpuesto;
              $listaRetenidos[] = $arrayIntImpuestos;
            }

            if ($resImpDat->clasificacion_impuestos == 'iva') {
              $txtclasifImp = 'iva';
              $clasificacionImpIva = $clasificacionImpIva + $totalImpuesto;
            }
            if ($resImpDat->clasificacion_impuestos == 'isrr') {
              $txtclasifImp = 'isr retenido';
              $clasificacionImpIsRet = $clasificacionImpIsRet + $totalImpuesto;
            }
            if ($resImpDat->clasificacion_impuestos == 'ivar') {
              $txtclasifImp = 'iva retenido';
              $clasificacionImpIvaRet = $clasificacionImpIvaRet + $totalImpuesto;
            }
            if ($resImpDat->clasificacion_impuestos == 'ieps') {
              $txtclasifImp = 'ieps';
              $clasificacionImpIeps = $clasificacionImpIeps + $totalImpuesto;
            }
            if ($resImpDat->clasificacion_impuestos == 'oidf') {
              $txtclasifImp = 'otros impuestos federales';
              $clasificacionImpOtrImpFed = $clasificacionImpOtrImpFed + $totalImpuesto;
            }
            if ($resImpDat->clasificacion_impuestos == 'oilo') {
              $txtclasifImp = 'otros impuestos locales';
              $clasificacionImpOtrImpLoc = $clasificacionImpOtrImpLoc + $totalImpuesto;
            }
          } else {
            $totalImpuesto = $totalImpuesto + 0;
          }
        }
      } else {
        $totalImpuesto = $totalImpuesto;
      }

      $producto = DB::select("SELECT catprod.costeo,catprod.num_serie,catprod.num_lote,catprod.importado 
                FROM in_egr_catalogo_productos AS catprod 
                JOIN main_empresas AS emp
                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                JOIN teci_usuarios_catalogo AS users
                WHERE catprod.token_cat_productos = ?
                AND catprod.administrador = emp.id
                AND emp.empresa_token = ?
                AND emp.id = empuser.empresa
                AND empuser.personal = pers.id
                AND pers.usuario = users.id
                AND users.usuario_token = ?", [$tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

      if ($producto[0]->num_serie == TRUE) {
        $serie = array();
        for ($a = 0; $a < count($lotSerAdu); $a++) {
          $tokenAlmacenCant = $lotSerAdu[$a];
          $serieProd = DB::select("SELECT detalm.token_detalle_almacen
                        FROM detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.token_detalle_almacen = ?
                        AND detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.administrador = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?", [$tokenAlmacenCant, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]);

          if (count($serieProd) == 1) {
            $arrayEach = array(
              "token_almacen" => $serieProd[0]->token_detalle_almacen,
            );
            $serie[] = $arrayEach;
          }
          //else {
          //    $serie = array();
          //}
        }
      } else {
        $serie = array();
      }

      if ($producto[0]->num_lote == TRUE) {
        $lote = array();
        for ($b = 0; $b < count($lotSerAdu); $b++) {
          $tokenAlmacenCant = $lotSerAdu[$b];
          $loteProd = DB::select("SELECT token_lote,numero_lote
                        FROM lote_prod 
                        WHERE token_lote = ?
                        AND id in (SELECT detalm.num_lote
                            FROM detalle_almacen AS detalm
                            JOIN in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                            JOIN teci_usuarios_catalogo AS users
                            WHERE detalm.status_disponibilidad = TRUE
                            AND detalm.producto = catprod.id
                            AND catprod.token_cat_productos = ?
                            AND catprod.administrador = emp.id
                            AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa
                            AND empuser.personal = pers.id
                            AND pers.usuario = users.id
                            AND users.usuario_token = ?)", [$tokenAlmacenCant, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]);
          if (count($loteProd) == 1) {
            $arrayEach = array(
              "token_lote" => $loteProd[0]->token_lote
            );
            $lote[] = $arrayEach;
          }
          //else {
          //    $lote = array();
          //}
        }
      } else {
        $lote = array();
      }

      if ($producto[0]->importado == TRUE) {
        $pedimento = array();
        for ($c = 0; $c < count($lotSerAdu); $c++) {
          $tokenAlmacenCant = $lotSerAdu[$c];
          $pedimentoProd = DB::select(
            "SELECT token_pedimento
                        FROM pedimento_aduanal
                        WHERE token_pedimento = ?
                        AND id IN (SELECT detalm.importado
                            FROM detalle_almacen AS detalm
                            JOIN in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                            JOIN teci_usuarios_catalogo AS users
                            WHERE detalm.status_disponibilidad = TRUE
                            AND detalm.producto = catprod.id
                            AND catprod.token_cat_productos = ?
                            AND catprod.administrador = emp.id
                            AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa
                            AND empuser.personal = pers.id
                            AND pers.usuario = users.id
                            AND users.usuario_token = ?)",
            [$tokenAlmacenCant, $tokenArticulo, $usuario->empresa_token, $usuario->user_token]
          );
          if (count($pedimentoProd) == 1) {
            $arrayEach = array(
              "token_pedimento" => $pedimentoProd[0]->token_pedimento
            );
            $pedimento[] = $arrayEach;
          }
          //else {
          //    $pedimento = array();
          //}
        }
      } else {
        $pedimento = array();
      }

      $arraySerieLoteImport = array(
        "serie" => $serie,
        "lote" => $lote,
        "pedimento" => $pedimento
      );

      $paramImportePartidaImpuesto = number_format($xplodeImportePartida + $totalImpuesto, 2, '.', ',');

      $arrayVenta = array(
        "token_articulo" => $dataProd[0]->token_cat_productos,
        "idenArt" => 'producto',
        "arraySerieLoteImport" => $arraySerieLoteImport,
        "imagen" => '/assets/images/interno/egresos/catalogos/productos/' . $dataProd[0]->imagen,
        "clasificacion" => $JwtAuth->generar($dataProd[0]->clasificacion) . "-" .
          $JwtAuth->generar($dataProd[0]->genero) . "-" . $JwtAuth->generar($dataProd[0]->folio),
        "sat" => $dataProd[0]->SAT,
        "clave" => $dataProd[0]->clave,
        "descripcion" => $dataProd[0]->descripcion,
        "concepto" => $conceptoArticulo,
        "precioBase" => $precioBase,
        "cantidad" => $paramCantidad,
        "paramDescuento" => $paramDescuento,
        "totalPromociones" => $totalPromociones,
        "totalImpretenido" => $JwtAuth->conversionPositivos($totalImpretenido),
        "listaRetenidos" => $listaRetenidos,
        "totalImptrasladado" => $JwtAuth->conversionPositivos($totalImptrasladado),
        "listaTrasladado" => $listaTrasladado,
        "paramImportePartida" => number_format($xplodeImportePartida, 2, '.', ','),
        "importeImpuestos" => $JwtAuth->conversionPositivos($totalImpuesto),
        "paramImportePartidaImpuesto" => $paramImportePartidaImpuesto,
        "clasificacionImpIva" => $JwtAuth->conversionPositivos($clasificacionImpIva),
        "clasificacionImpIsRet" => $JwtAuth->conversionPositivos($clasificacionImpIsRet),
        "clasificacionImpIvaRet" => $JwtAuth->conversionPositivos($clasificacionImpIvaRet),
        "clasificacionImpIeps" => $JwtAuth->conversionPositivos($clasificacionImpIeps),
        "clasificacionImpOtrImpFed" => $JwtAuth->conversionPositivos($clasificacionImpOtrImpFed),
        "clasificacionImpOtrImpLoc" => $JwtAuth->conversionPositivos($clasificacionImpOtrImpLoc),
      );
      $array_articulo[] = $arrayVenta;
    }

    return response()->json([
      'listaArticulos' => $array_articulo,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function registraVentaArticulo(Request $request)
  {
    /*{"user_token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjEsIm5hbWUiOiJHdWVycmVybyBBbWFkb3IgSnVhbiBDYXJsb3MiLCJyZmMiOm51bGwsImFyZWEiOiJBZG1pbmlzdHJhY2lcdTAwZjNuIGdlbmVyYWwiLCJjYXJnbyI6ImNvb3JkaW5hZG9yIiwiYXZhdGFyIjoicGVyc29uLnBuZyIsInVzZXJfdG9rZW4iOiJXWEpwTURKT2JIVmxMMXBZU1M4MVJDdHRVazVTVUd4NlVXbDFOakV2VkcxWVNsUjFZMXB1WVdrNVJGazFUM2QzVmpGTVJFeFpOM2hPVGxCeGNHRTBVM3AxWlVNMlVUUkhWV3A0VWtGdVIyNDFhVXhLYkhkTFUxSkxabUZNZVhwdksxcDNXbVpSZW1reWVuZENaR1kxTTBVd00waDJPR2h5Y2xSRE15dE1NbkpSUkVOVVVYQjRSbFJwT1dwWmVFVnBZVlI2TmlzNGIwMVZhWFYwV0hwVlozSnpXR2cxUTNwR2EzbHpZMEUxVkdFMk16TTJUamRHVTFVMGF6TXZNWEZ3VFZNM1ltSk1NM3AzUVRkdllsQXhRM0ZqVURKVldsUnlkMDl4WVdKaFVGQkxSbTFCZFhwYVZWcFhjMVowVVVjeFZXdEpORFZWVGpCamNFMUxiMmhJUkdwTVQyTmpZVGxOTUV0eVVXMDFaa1EyY2tFeVdXSlRhVGh4TlRaWVFrRlZUR0pWYWtGVldERlBkVms5T2pveE1qTTBOVFkzT0RFeU16UTFOamM0IiwiZW1wX3Rva2VuIjoiYmtkRVJHMUtSVUYyVWk5SWRuTlRVa2N4U1hKUU55dG1iSGxGY2xRd2MyUlhNV3cwU0dsdlduZFNUbnAzTjNOWU5VSkdVbFZRVkZOc2NUVXJZblZST0c0elFXOTZVRGxFV25KTVdVUjBNVTFSTmtseGEwSTVNMHBxT1c4eGFuaGFaRk0zYjNFM1EyOVJPV0ZpUjB0U1ptNHZiMnBzYmt4MFJFWndOek5sUWs5alZVTnhXak0xV25wNmMzRk9hMHQ2U1ROVWNVRm5SVEk0ZGtkTU5WTkdjbE5TUW1wcWVWUlJUVWM1VmxneVdWRmhNelp4UVdGMFFscExNV2d4ZW01QloxWmtWMG92WVZNckwwa3ZZalJJV2xWNldFbFpkbE5HYlVkeGRFRmhkVXRxZGpsbWJrRnhjRzFxY1hWc0swNUJRVkJITnl0UGFHOW5RMlJDYXpBMlVTOXpWMGhCYTJKTFRuVlhLMHBvV1Voc1UxSnBZbEU5UFRvNk1USXpORFUyTnpneE1qTTBOVFkzT0E9PSIsImlhdCI6MTY1MDkzNDcwOSwiZXhwIjoxNjUxNTM5NTA5fQ.8BKvf4QjiohjnTxpgXKpaAcUaYqpLAsaCiEYdpwofaQ",
        "HiddenclienteToken":"---",
        "ListaPrecV":"público general",
        "MonedaClientV":"MXN-PESO MEXICANO",
        "TipoCambioClientV":"$1.00",
        "arrayDesgloseVenta":[                                      
            {
            "token_articulo":
            "eUhLV1hVL01sK21tUnFsR0JHcXZRb1dEQVZnRVZXZTFxNnpsOHBST2YwbDRMVTE2akJMWk5CdDJOa0RFaTdJcUNGdU9CTGRZaGo2L0hJOCt1RVI2STQxa3E3bUt4MFNRa00wQW5lSnJ5ZjNuWjFIQXc4SjNvQ0hja1lJM3RVQk9lZy84OUZNaDNISkhDdzFkL2tvNGlEbHpROTF4TUU0a0FoM1V2UFR5Y2hRNHZvUHlPa3c1RE1tZVF0YkxmaGIzYXhRUDFzQXhuSGRKU1J6RFFETXVCZz09OjoxMjM0NTY3ODEyMzQ1Njc4",
            "idenArt":"servicio",
            "imagen":"",
            "clasificacion":"0006-0001-0002",
            "sat":"Unidad de Servicio - E48",
            "clave":10101501,
            "descripcion":"Gatos vivos",
            "concepto":"otra prieba",
            "precioBase":"100.00",
            "cantidad":"1",
            "paramDescuento":"$0.00",
            "totalPromociones":"$0.00",
            "totalImpretenido":"10.00",
            "listaRetenidos":[
                {"token_cat_impuestos":"eHY2MzBjZXpnbVVBWlRsTGwrOCtaTVRzOERic29EcHQrajJWUjY1NUdaQT06OjEyMzQ1Njc4MTIzNDU2Nzg=",
                    "tipoImpuesto":"Impuesto Federal",
                    "clasificacionImp":"ISR RETENIDO",
                    "concepto":"Impuestos sobre la Renta (ISR)",
                    "alias":"isr prueba",
                    "reTras":"retenido",
                    "cuoPorc":"porcentaje",
                    "ImporteImpuesto":"10%",
                    "totalImpPart":"10.00"
                }
            ],
            "totalImptrasladado":"0.00",
            "listaTrasladado":[],
            "paramImportePartida":"100.00",
            "importeImpuestos":"10.00",
            "paramImportePartidaImpuesto":"90.00",
            "clasificacionImpIva":"0.00",
            "clasificacionImpIsRet":"10.00",
            "clasificacionImpIvaRet":"0.00",
            "clasificacionImpIeps":"0.00",
            "clasificacionImpOtrImpFed":"0.00",
            "clasificacionImpOtrImpLoc":"0.00"}
        ],
        "datosCaja":[
            {
                "pers_token":"jfjhfjhfhfjhfojhfkjhfkjfkjhfjgdyfdutfsugfsugfsjgfsngfsjugfsjgfsjgfxjgdiygdkhgckhcf",
                "img_resp":"person.png",
                "nombre":"Guerrero Amador Juan Carlos",
                "token_caja":"bcjhcmhfjhfjhfjhfjhf",
                "caja":"9",
                "token_almacen":"1khgk.mgkjgkjgjkjgkj",
                "alias":"matriz",
                "latitud":"19.3079885",
                "longitud":"-98.93687416666667",
                "token_direccion":"Vy9WOEFPWENub0lYdlZQR09weTVMNDNiQzByZGUrbzZ0WFUzVUloZ2x5az06OjEyMzQ1Njc4MTIzNDU2Nzg=",
                "dir_completa":"Calle la rosa No. 12 Int.-, C.P. 20018 Col. Línea de Fuego, 
                    Aguascalientes, Aguascalientes, México, loc lorenzo, entre calle 1 y calle 2 referencia panteon"}],
        "datosCajaAlmacenDir":"Vy9WOEFPWENub0lYdlZQR09weTVMNDNiQzByZGUrbzZ0WFUzVUloZ2x5az06OjEyMzQ1Njc4MTIzNDU2Nzg=",
        "arrayFormaPago":"{\"efectivo\":[\"$100.00\"],\"cheque\":[\"\"],\"valeDespensa\":[\"\"]}"}*/

    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //$parametrosArray = array_map('trim',$parametrosArray);
      $validate = \Validator::make($parametrosArray, [
        'HiddenclienteToken' => 'required|string',
        'ListaPrecV' => 'required|string',
        'MonedaClientV' => 'required|string',
        'TipoCambioClientV' => 'required|string',
        'arrayDesgloseVenta' => 'required',
        'datosCaja' => 'required',
        'datosCajaAlmacenDir' => 'required|string',
        'responsableEntrega' => 'required|string',
        'arrayFormaPago' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'usuario no creado correctamente',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametros->user_token, true);
        //$jsonFpago = json_decode($parametros->arrayFormaPago);
        //echo $jsonFpago->efectivo[0];

        $folioVenta = VentasModelo::join("empresas", "ventas.vendedor", "=", "empresas.id")
          ->join("main_empresa_usuario AS empuser", "empresas.id", "=", "empuser.empresa")
          ->join("personal", "empuser.personal", "=", "personal.id")
          ->join("usuarios", "personal.usuario", "=", "usuarios.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->count();

        $tknVenta = $JwtAuth->encriptarToken(($folioVenta + 1) . time() .
          $parametrosArray['HiddenclienteToken'] . $parametrosArray['datosCaja'][0]['pers_token'] .
          $parametrosArray['datosCajaAlmacenDir']);

        if ($parametrosArray['HiddenclienteToken'] != '---') {
          $queryIdClient = DB::select("SELECT id FROM catalogo_clientes 
                       WHERE token_cat_clientes = ?", [$parametrosArray['HiddenclienteToken']]);
          $queryIdClient = $queryIdClient[0]->id;
        } else {
          $queryIdClient = NULL;
        }

        $selectLugEntrega = DB::select("SELECT id FROM direcciones 
                    WHERE token_direccion = ?", [$parametrosArray['datosCajaAlmacenDir']]);

        $selectRespCaja = DB::select("SELECT id FROM responsables_almacen 
                    WHERE token_responsables = ?", [$parametrosArray['datosCaja'][0]['pers_token']]);

        $selectCaja = DB::select("SELECT id FROM caja 
                    WHERE token_caja = ?", [$parametrosArray['datosCaja'][0]['token_caja']]);

        $selectEmp = DB::select("SELECT emp.id FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

        $newVenta = new VentasModelo();
        //return $parametros->HiddenclienteToken;
        //return $parametros->ListaPrecV;
        //return $parametros->MonedaClientV;
        //return $parametros->TipoCambioClientV;
        $newVenta->token_ventas = $tknVenta;
        $newVenta->folio_venta = $folioVenta + 1;
        $newVenta->fecha = time();
        $newVenta->cliente = $queryIdClient;
        $newVenta->lugar_entrega = $selectLugEntrega[0]->id;
        $newVenta->responsable = $selectRespCaja[0]->id;
        $newVenta->caja = $selectCaja[0]->id;
        $newVenta->vendedor = $selectEmp[0]->id;
        $newVenta->save();

        $selectVenta = DB::select("SELECT id FROM ventas WHERE token_ventas = ?", [$tknVenta]);
        //echo "selectVenta ".$selectVenta[0]->id;
        for ($i = 0; $i < count($parametrosArray['arrayFormaPago']); $i++) {
          if ($i == 0) {
            if ($parametrosArray['arrayFormaPago'][$i] != '[""]') {
              $fomaPagoVEnta = DB::table("forma_pagoventa")->insert(
                array(
                  "token_formapagoventa" => 1,
                  "venta" => $selectVenta[0]->id,
                  "forma_pago" => 1,
                  "desgloce_pago" => '',
                  "suma_total" => $parametrosArray['arrayFormaPago'][$i][0],
                  "empresa" => $selectEmp[0]->id
                )
              );
            }
          }

          if ($i == 1) {
            //echo count($parametrosArray['arrayFormaPago'][$i]);
            for ($pCheque = 0; $pCheque < count($parametrosArray['arrayFormaPago'][$i]); $pCheque++) {
              if ($parametrosArray['arrayFormaPago'][$i][$pCheque] != '') {
                $desgloseFpago = $JwtAuth->encriptar(json_encode($parametrosArray['arrayFormaPago'][$i][$pCheque][0]));
                $fomaPagoVEnta = DB::table("forma_pagoventa")->insert(
                  array(
                    "token_formapagoventa" => 2,
                    "venta" => $selectVenta[0]->id,
                    "forma_pago" => 2,
                    "desgloce_pago" => $desgloseFpago,
                    "suma_total" => $parametrosArray['arrayFormaPago'][$i][$pCheque][0]["montoChque"],
                    "empresa" => $selectEmp[0]->id
                  )
                );
              }
            }
          }

          if ($i == 2) {
            for ($pVale = 0; $pVale < count($parametrosArray['arrayFormaPago'][$i]); $pVale++) {
              if ($parametrosArray['arrayFormaPago'][$i][$pVale] != '') {
                $desgloseFpago = $JwtAuth->encriptar(json_encode($parametrosArray['arrayFormaPago'][$i][$pVale][0]));
                $fomaPagoVEnta = DB::table("forma_pagoventa")->insert(
                  array(
                    "token_formapagoventa" => 2,
                    "venta" => $selectVenta[0]->id,
                    "forma_pago" => 7,
                    "desgloce_pago" => $desgloseFpago,
                    "suma_total" => $parametrosArray['arrayFormaPago'][$i][$pVale][0]["montoVale"],
                    "empresa" => $selectEmp[0]->id
                  )
                );
              }
            }
          }
        }

        //echo count($parametrosArray['arrayDesgloseVenta']);
        $countDesgloseVenta = 0;
        for ($i = 0; $i < count($parametrosArray['arrayDesgloseVenta']); $i++) {
          $desgloseVentaCantidad = str_replace('$', '', $parametrosArray['arrayDesgloseVenta'][$i]['cantidad']);
          $desgloseVentaCantidad = str_replace(',', '', $desgloseVentaCantidad);
          //echo $desgloseVentaCantidad." desgloseVentaCantidad ";
          $desgloseVentaDescuentoTotal = str_replace('$', '', $parametrosArray['arrayDesgloseVenta'][$i]['paramDescuento']);
          $desgloseVentaDescuentoTotal = str_replace(',', '', $desgloseVentaDescuentoTotal);
          $desgloseVentaPromocionesTotal = str_replace('$', '', $parametrosArray['arrayDesgloseVenta'][$i]['totalPromociones']);
          $desgloseVentaPromocionesTotal = str_replace(',', '', $desgloseVentaPromocionesTotal);
          $desgloseVentaImportePartida = str_replace('$', '', $parametrosArray['arrayDesgloseVenta'][$i]['paramImportePartidaImpuesto']);
          $desgloseVentaImportePartida = str_replace(',', '', $desgloseVentaImportePartida);

          $tknDetVenta = $JwtAuth->encriptarToken(($folioVenta + 1) . time() .
            $parametrosArray['arrayDesgloseVenta'][$i]['token_articulo'] .
            $parametrosArray['arrayDesgloseVenta'][$i]['cantidad']);

          if ($parametrosArray['arrayDesgloseVenta'][$i]['idenArt'] == 'producto') {
            $queryProdVenta = DB::select("SELECT id FROM in_egr_catalogo_productos 
                            WHERE token_cat_productos = ?", [$parametrosArray['arrayDesgloseVenta'][$i]['token_articulo']]);
            $prodVenta = $queryProdVenta[0]->id;
            $servVenta = NULL;
          }
          if ($parametrosArray['arrayDesgloseVenta'][$i]['idenArt'] == 'servicio') {
            $prodVenta = NULL;
            $queryServVenta = DB::select("SELECT id FROM catalogo_servicios 
                            WHERE token_cat_servicios = ?", [$parametrosArray['arrayDesgloseVenta'][$i]['token_articulo']]);
            $servVenta = $queryServVenta[0]->id;
          }

          $insertDetVenta = new DetalleVentasModelo();
          $insertDetVenta->token_detventa = $tknDetVenta;
          $insertDetVenta->numero_venta = $selectVenta[0]->id;
          $insertDetVenta->producto = $prodVenta;
          $insertDetVenta->servicio = $servVenta;
          $insertDetVenta->total_descuento = $desgloseVentaDescuentoTotal;
          $insertDetVenta->total_promocion = $desgloseVentaPromocionesTotal;
          $insertDetVenta->cantidad = $desgloseVentaCantidad;
          $insertDetVenta->precio = $desgloseVentaImportePartida;
          $insertDetVenta->save();

          $selectDetVenta = DB::select("SELECT id FROM detalle_venta WHERE token_detventa = ?", [$tknDetVenta]);
          //echo "selectVenta ".$selectDetVenta[0]->id;

          if ($parametrosArray['arrayDesgloseVenta'][$i]['idenArt'] == 'producto') {
            $queryProdVenta = DB::select("SELECT id,costeo,num_serie,num_lote,importado FROM in_egr_catalogo_productos 
                            WHERE token_cat_productos = ?", [$parametrosArray['arrayDesgloseVenta'][$i]['token_articulo']]);
            $selectRespEntrega = DB::select("SELECT id FROM responsables_almacen WHERE token_responsables = ?", [$parametrosArray['responsableEntrega']]);
            $tokenEntregfa = $JwtAuth->encriptarToken('6hgiutiut' . $queryProdVenta[0]->id, 'kjkjf', time(), 'kbouitijugkjku', $parametrosArray['responsableEntrega']);

            if (count($parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["serie"]) != 0) {
              for ($fSerie = 0; $fSerie < count($parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["serie"]); $fSerie++) {
                //echo $parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["serie"][$fSerie]['token_almacen'];
                $updateSerie = DB::table('detalle_almacen')
                  ->where(['token_detalle_almacen' => $parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["serie"][$fSerie]['token_almacen']])
                  ->limit(1)
                  ->update(array('status_disponibilidad' => FALSE));

                $selectIdSerie = DB::select(
                  "SELECT id FROM detalle_almacen WHERE num_serie != '' AND token_detalle_almacen = ?",
                  [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["serie"][$fSerie]['token_almacen']]
                );

                $updateSerie = DB::table('almacen_venta_detalle')
                  ->insert(
                    array(
                      'almacen' => $selectIdSerie[0]->id,
                      'producto' => $queryProdVenta[0]->id,
                      'venta' => $selectVenta[0]->id,
                      'detalle_venta' => $selectDetVenta[0]->id,
                      'cantidad' => $desgloseVentaCantidad
                    )
                  );
                $llenaEntrega = DB::table('entregas')->insert(
                  array(
                    'token_entrega' => $tokenEntregfa,
                    'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                    'producto' => $queryProdVenta[0]->id,
                    'almacen' => $selectIdSerie[0]->id,
                    'venta' => $selectVenta[0]->id,
                    'resp_entrega' => $selectRespEntrega[0]->id,
                    'lugar_entrega' => $selectLugEntrega[0]->id,
                    'tiempo_estimado' => time() + 1800,
                    'status_entrega' => false,
                  )
                );
              }
            }

            if (count($parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["lote"]) != 0) {
              for ($fLote = 0; $fLote < count($parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["lote"]); $fLote++) {
                //echo $parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["lote"][$fLote]['token_lote'];
                if ($queryProdVenta[0]->num_serie == TRUE) {
                  if ($queryProdVenta[0]->costeo == 'PEPS') {
                    $selectSeries = DB::select(
                      "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                        FROM detalle_almacen AS alm JOIN lote_prod AS lot 
                                        WHERE alm.num_serie != '' AND alm.num_lote = lot.id AND lot.token_lote = ? 
                                        AND alm.status_disponibilidad = TRUE ORDER BY alm.id ASC",
                      [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["lote"][$fLote]['token_lote']]
                    );
                  }

                  if ($queryProdVenta[0]->costeo == 'UEPS') {
                    $selectSeries = DB::select(
                      "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                        FROM detalle_almacen AS alm JOIN lote_prod AS lot 
                                        WHERE alm.num_serie != '' AND alm.num_lote = lot.id AND lot.token_lote = ? 
                                        AND alm.status_disponibilidad = TRUE ORDER BY alm.id DESC",
                      [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["lote"][$fLote]['token_lote']]
                    );
                  }
                  foreach ($selectSeries as $key => $valselectSeries) {
                    //echo $valselectSeries->existencia;
                    $updateSerie = DB::table('almacen_venta_detalle')
                      ->insert(
                        array(
                          'almacen' => $valselectSeries->id,
                          'producto' => $queryProdVenta[0]->id,
                          'venta' => $selectVenta[0]->id,
                          'detalle_venta' => $selectDetVenta[0]->id,
                          'cantidad' => $desgloseVentaCantidad
                        )
                      );
                    $llenaEntrega = DB::table('entregas')->insert(
                      array(
                        'token_entrega' => $tokenEntregfa,
                        'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                        'producto' => $queryProdVenta[0]->id,
                        'almacen' => $valselectSeries->id,
                        'venta' => $selectVenta[0]->id,
                        'resp_entrega' => $selectRespEntrega[0]->id,
                        'lugar_entrega' => $selectLugEntrega[0]->id,
                        'tiempo_estimado' => time() + 1800,
                        'status_entrega' => false,
                      )
                    );
                    if ($valselectSeries->existencia > $desgloseVentaCantidad) {
                      $restaCantiDad =  $valselectSeries->existencia - $desgloseVentaCantidad;

                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                        ->limit(1)->update(array('existencia' => $restaCantiDad));
                      break;
                    } else if ($valselectSeries->existencia == $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                      break;
                    } else if ($valselectSeries->existencia < $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                    }
                  }
                } else {
                  $selectLote = DB::select(
                    "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                        FROM detalle_almacen AS alm JOIN lote_prod AS lot 
                                        WHERE alm.num_serie == '' AND alm.num_lote = lot.id AND lot.token_lote = ? 
                                        AND alm.status_disponibilidad = TRUE",
                    [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["lote"][$fLote]['token_lote']]
                  );

                  foreach ($selectLote as $key => $valselectLote) {
                    //echo $valselectLote->existencia;
                    $updateSerie = DB::table('almacen_venta_detalle')
                      ->insert(
                        array(
                          'almacen' => $valselectLote->id,
                          'producto' => $queryProdVenta[0]->id,
                          'venta' => $selectVenta[0]->id,
                          'detalle_venta' => $selectDetVenta[0]->id,
                          'cantidad' => $desgloseVentaCantidad
                        )
                      );
                    $llenaEntrega = DB::table('entregas')->insert(
                      array(
                        'token_entrega' => $tokenEntregfa,
                        'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                        'producto' => $queryProdVenta[0]->id,
                        'almacen' => $valselectLote->id,
                        'venta' => $selectVenta[0]->id,
                        'resp_entrega' => $selectRespEntrega[0]->id,
                        'lugar_entrega' => $selectLugEntrega[0]->id,
                        'tiempo_estimado' => time() + 1800,
                        'status_entrega' => false,
                      )
                    );
                    if ($valselectLote->existencia > $desgloseVentaCantidad) {
                      $restaCantiDad =  $valselectLote->existencia - $desgloseVentaCantidad;

                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                        ->limit(1)->update(array('existencia' => $restaCantiDad));
                      break;
                    } else if ($valselectLote->existencia == $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                      break;
                    } else if ($valselectLote->existencia < $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                    }
                  }
                }
              }
            }

            if (count($parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"]) != 0) {
              for ($fPedimento = 0; $fPedimento < count($parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"]); $fPedimento++) {
                //echo $parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"][$fPedimento]['token_pedimento'];
                if ($queryProdVenta[0]->num_lote == TRUE) {

                  if ($queryProdVenta[0]->costeo == 'PEPS') {
                    $selectPedimenTad = DB::select(
                      "SELECT alm.num_lote FROM detalle_almacen AS alm 
                                        JOIN pedimento_aduanal AS pedad WHERE alm.num_lote != '' AND alm.importado = pedad.id 
                                        AND pedad.token_pedimento = ? AND alm.status_disponibilidad = TRUE GROUP BY alm.num_lote ORDER BY alm.id ASC",
                      [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"][$fPedimento]['token_pedimento']]
                    );
                  }

                  if ($queryProdVenta[0]->costeo == 'UEPS') {
                    $selectPedimenTad = DB::select(
                      "SELECT alm.num_lote FROM detalle_almacen AS alm 
                                        JOIN pedimento_aduanal AS pedad WHERE alm.num_lote != '' AND alm.importado = pedad.id 
                                        AND pedad.token_pedimento = ? AND alm.status_disponibilidad = TRUE GROUP BY alm.num_lote ORDER BY alm.id ASC",
                      [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"][$fPedimento]['token_pedimento']]
                    );
                  }

                  foreach ($selectPedimenTad as $valselectPedimenTad) {

                    if ($queryProdVenta[0]->num_serie == TRUE) {
                      if ($queryProdVenta[0]->costeo == 'PEPS') {
                        $selectSeries = DB::select(
                          "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                                FROM detalle_almacen AS alm JOIN lote_prod AS lot 
                                                WHERE alm.num_serie != '' AND alm.num_lote = ? 
                                                AND alm.status_disponibilidad = TRUE ORDER BY alm.id ASC",
                          [$valselectPedimenTad->num_lote]
                        );
                      }

                      if ($queryProdVenta[0]->costeo == 'UEPS') {
                        $selectSeries = DB::select(
                          "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                                FROM detalle_almacen AS alm JOIN lote_prod AS lot 
                                                WHERE alm.num_serie != '' AND alm.num_lote = ? 
                                                AND alm.status_disponibilidad = TRUE ORDER BY alm.id DESC",
                          [$valselectPedimenTad->num_lote]
                        );
                      }
                      foreach ($selectSeries as $key => $valselectSeries) {
                        //echo $valselectSeries->existencia;
                        $updateSerie = DB::table('almacen_venta_detalle')
                          ->insert(
                            array(
                              'almacen' => $valselectSeries->id,
                              'producto' => $queryProdVenta[0]->id,
                              'venta' => $selectVenta[0]->id,
                              'detalle_venta' => $selectDetVenta[0]->id,
                              'cantidad' => $desgloseVentaCantidad
                            )
                          );
                        $llenaEntrega = DB::table('entregas')->insert(
                          array(
                            'token_entrega' => $tokenEntregfa,
                            'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                            'producto' => $queryProdVenta[0]->id,
                            'almacen' => $valselectSeries->id,
                            'venta' => $selectVenta[0]->id,
                            'resp_entrega' => $selectRespEntrega[0]->id,
                            'lugar_entrega' => $selectLugEntrega[0]->id,
                            'tiempo_estimado' => time() + 1800,
                            'status_entrega' => false,
                          )
                        );
                        if ($valselectSeries->existencia > $desgloseVentaCantidad) {
                          $restaCantiDad =  $valselectSeries->existencia - $desgloseVentaCantidad;
                          //echo "#restaCantiDad".$restaCantiDad;
                          $updateSerie = DB::table('detalle_almacen')
                            ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                            ->limit(1)->update(array('existencia' => $restaCantiDad));

                          //echo $restaCantiDad." adios";
                          break;
                        } else if ($valselectSeries->existencia == $desgloseVentaCantidad) {
                          $updateSerie = DB::table('detalle_almacen')
                            ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                            ->limit(1)->update(
                              array(
                                'existencia' => 0,
                                'status_disponibilidad' => FALSE
                              )
                            );

                          break;
                        } else if ($valselectSeries->existencia < $desgloseVentaCantidad) {
                          $updateSerie = DB::table('detalle_almacen')
                            ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                            ->limit(1)->update(
                              array(
                                'existencia' => 0,
                                'status_disponibilidad' => FALSE
                              )
                            );
                        }
                      }
                    } else {
                      $selectLote = DB::select(
                        "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                                FROM detalle_almacen AS alm JOIN lote_prod AS lot 
                                                WHERE alm.num_serie == '' AND alm.num_lote = ?
                                                AND alm.status_disponibilidad = TRUE",
                        [$valselectPedimenTad->num_lote]
                      );

                      foreach ($selectLote as $key => $valselectLote) {
                        //echo $valselectLote->existencia;
                        $updateSerie = DB::table('almacen_venta_detalle')
                          ->insert(
                            array(
                              'almacen' => $valselectLote->id,
                              'producto' => $queryProdVenta[0]->id,
                              'venta' => $selectVenta[0]->id,
                              'detalle_venta' => $selectDetVenta[0]->id,
                              'cantidad' => $desgloseVentaCantidad
                            )
                          );
                        $llenaEntrega = DB::table('entregas')->insert(
                          array(
                            'token_entrega' => $tokenEntregfa,
                            'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                            'producto' => $queryProdVenta[0]->id,
                            'almacen' => $valselectLote->id,
                            'venta' => $selectVenta[0]->id,
                            'resp_entrega' => $selectRespEntrega[0]->id,
                            'lugar_entrega' => $selectLugEntrega[0]->id,
                            'tiempo_estimado' => time() + 1800,
                            'status_entrega' => false,
                          )
                        );
                        if ($valselectLote->existencia > $desgloseVentaCantidad) {
                          $restaCantiDad =  $valselectLote->existencia - $desgloseVentaCantidad;

                          $updateSerie = DB::table('detalle_almacen')
                            ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                            ->limit(1)->update(array('existencia' => $restaCantiDad));

                          //echo $restaCantiDad." adios";
                          break;
                        } else if ($valselectLote->existencia == $desgloseVentaCantidad) {
                          $updateSerie = DB::table('detalle_almacen')
                            ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                            ->limit(1)->update(
                              array(
                                'existencia' => 0,
                                'status_disponibilidad' => FALSE
                              )
                            );

                          break;
                        } else if ($valselectLote->existencia < $desgloseVentaCantidad) {
                          $updateSerie = DB::table('detalle_almacen')
                            ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                            ->limit(1)->update(
                              array(
                                'existencia' => 0,
                                'status_disponibilidad' => FALSE
                              )
                            );
                        }
                      }
                    }
                  }
                } else if ($queryProdVenta[0]->num_serie == TRUE) {
                  if ($queryProdVenta[0]->costeo == 'PEPS') {
                    $selectSeries = DB::select(
                      "SELECT alm.token_detalle_almacen,alm.existencia 
                                        FROM detalle_almacen AS alm JOIN pedimento_aduanal AS pedad
                                        WHERE alm.num_serie != '' AND alm.num_lote = '' AND alm.importado = pedad.id 
                                        AND pedad.token_pedimento = ? AND alm.status_disponibilidad = TRUE ORDER BY alm.id ASC",
                      [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"][$fPedimento]['token_pedimento']]
                    );
                  }

                  if ($queryProdVenta[0]->costeo == 'UEPS') {
                    $selectSeries = DB::select(
                      "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                        FROM detalle_almacen AS alm JOIN pedimento_aduanal AS pedad
                                        WHERE alm.num_serie != '' AND alm.num_lote = '' AND alm.importado = pedad.id 
                                        AND pedad.token_pedimento = ? AND alm.status_disponibilidad = TRUE ORDER BY alm.id ASC",
                      [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"][$fPedimento]['token_pedimento']]
                    );
                  }
                  foreach ($selectSeries as $key => $valselectSeries) {
                    //echo $valselectSeries->existencia;
                    $updateSerie = DB::table('almacen_venta_detalle')
                      ->insert(
                        array(
                          'almacen' => $valselectSeries->id,
                          'producto' => $queryProdVenta[0]->id,
                          'venta' => $selectVenta[0]->id,
                          'detalle_venta' => $selectDetVenta[0]->id,
                          'cantidad' => $desgloseVentaCantidad
                        )
                      );
                    $llenaEntrega = DB::table('entregas')->insert(
                      array(
                        'token_entrega' => $tokenEntregfa,
                        'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                        'producto' => $queryProdVenta[0]->id,
                        'almacen' => $valselectSeries->id,
                        'venta' => $selectVenta[0]->id,
                        'resp_entrega' => $selectRespEntrega[0]->id,
                        'lugar_entrega' => $selectLugEntrega[0]->id,
                        'tiempo_estimado' => time() + 1800,
                        'status_entrega' => false,
                      )
                    );
                    if ($valselectSeries->existencia > $desgloseVentaCantidad) {
                      $restaCantiDad =  $valselectSeries->existencia - $desgloseVentaCantidad;

                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                        ->limit(1)->update(array('existencia' => $restaCantiDad));
                      break;
                    } else if ($valselectSeries->existencia == $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                      break;
                    } else if ($valselectSeries->existencia < $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectSeries->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                    }
                  }
                } else {
                  $selectLote = DB::select(
                    "SELECT alm.id,alm.token_detalle_almacen,alm.existencia 
                                        FROM detalle_almacen AS alm JOIN pedimento_aduanal AS pedad
                                        WHERE alm.num_serie = '' AND alm.num_lote = '' AND alm.importado = pedad.id 
                                        AND pedad.token_pedimento = ? AND alm.status_disponibilidad = TRUE ORDER BY alm.id ASC",
                    [$parametrosArray['arrayDesgloseVenta'][$i]['arraySerieLoteImport']["pedimento"][$fPedimento]['token_pedimento']]
                  );

                  foreach ($selectLote as $key => $valselectLote) {
                    //echo $valselectLote->existencia;
                    $updateSerie = DB::table('almacen_venta_detalle')
                      ->insert(
                        array(
                          'almacen' => $valselectLote->id,
                          'producto' => $queryProdVenta[0]->id,
                          'venta' => $selectVenta[0]->id,
                          'detalle_venta' => $selectDetVenta[0]->id,
                          'cantidad' => $desgloseVentaCantidad
                        )
                      );
                    $llenaEntrega = DB::table('entregas')->insert(
                      array(
                        'token_entrega' => $tokenEntregfa,
                        'mini_token_entrega' => substr($tokenEntregfa, 5, 10),
                        'producto' => $queryProdVenta[0]->id,
                        'almacen' => $valselectLote->id,
                        'venta' => $selectVenta[0]->id,
                        'resp_entrega' => $selectRespEntrega[0]->id,
                        'lugar_entrega' => $selectLugEntrega[0]->id,
                        'tiempo_estimado' => time() + 1800,
                        'status_entrega' => false,
                      )
                    );
                    if ($valselectLote->existencia > $desgloseVentaCantidad) {
                      $restaCantiDad =  $valselectLote->existencia - $desgloseVentaCantidad;

                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                        ->limit(1)->update(array('existencia' => $restaCantiDad));
                      break;
                    } else if ($valselectLote->existencia == $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                      break;
                    } else if ($valselectLote->existencia < $desgloseVentaCantidad) {
                      $updateSerie = DB::table('detalle_almacen')
                        ->where(['token_detalle_almacen' => $valselectLote->token_detalle_almacen])
                        ->limit(1)->update(
                          array(
                            'existencia' => 0,
                            'status_disponibilidad' => FALSE
                          )
                        );
                    }
                  }
                }
              }
            }

            if ($queryProdVenta[0]->costeo == 'PEPS') {
              $selectContkardex = DB::select(
                "SELECT id,saldo_cantidad,factura_compra,factura_venta FROM kardex 
                                WHERE producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?) 
                                AND status_kardex = 6 AND fecha = (SELECT MAX(fecha) FROM kardex WHERE fecha <= now() AND status_kardex = 6) 
                                AND saldo_cantidad != 0 ORDER BY id ASC",
                [$parametrosArray['arrayDesgloseVenta'][$i]['token_articulo']]
              );
            }

            if ($queryProdVenta[0]->costeo == 'UEPS') {
              $selectContkardex = DB::select(
                "SELECT id,saldo_cantidad,factura_compra,factura_venta FROM kardex 
                                WHERE producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?) 
                                AND status_kardex = 6 AND fecha = (SELECT MAX(fecha) FROM kardex WHERE fecha <= now() AND status_kardex = 6) 
                                AND saldo_cantidad != 0 ORDER BY id DESC",
                [$parametrosArray['arrayDesgloseVenta'][$i]['token_articulo']]
              );
            }

            $cantidadKardex = $desgloseVentaCantidad;
            $new_saldo_cantidad = 0;
            for ($fK = 0; $fK < count($selectContkardex); $fK++) {
              //echo 'saldo_cantidad '.$selectContkardex[$fK]->saldo_cantidad;
              if ($cantidadKardex != 0) {
                if ($selectContkardex[$fK]->saldo_cantidad > $cantidadKardex) {
                  $new_saldo_cantidad = $selectContkardex[$fK]->saldo_cantidad - $cantidadKardex;
                  $cantidadKardex = 0;
                  //echo 'saldo_cantidad1 '.$new_saldo_cantidad.' '.$cantidadKardex;
                }

                if ($selectContkardex[$fK]->saldo_cantidad = $cantidadKardex) {
                  $new_saldo_cantidad = 0;
                  $cantidadKardex = 0;
                  //echo 'saldo_cantidad2 '.$selectContkardex[$fK]->id." ".$new_saldo_cantidad.' '.$cantidadKardex;
                }

                if ($selectContkardex[$fK]->saldo_cantidad < $cantidadKardex) {
                  $new_saldo_cantidad = 0;
                  $cantidadKardex = $cantidadKardex - $selectContkardex[$fK]->saldo_cantidad;
                  //echo 'saldo_cantidad3 '.$new_saldo_cantidad.' '.$cantidadKardex;
                }
              } else if ($cantidadKardex == 0) {
                $new_saldo_cantidad = $selectContkardex[$fK]->saldo_cantidad;
                //echo 'saldo_cantidad4 '.$selectContkardex[$fK]->id." ".$new_saldo_cantidad;
              }
              $tokenKardexVent = $JwtAuth->encriptarToken('2hgiutiut' . $queryProdVenta[0]->id, 'kjfkjfkjhfkjf', time(), 'kbouitijugkjku', $parametrosArray['arrayDesgloseVenta'][$i]['precioBase']);
              $insertaKardex = DB::table("kardex")->insert(
                array(
                  "c_token" => $tokenKardexVent,
                  "producto" => $queryProdVenta[0]->id,
                  "fecha" => time(),
                  "status_kardex" => 4,
                  "concepto" => 'venta' . time(),
                  "factura_compra" => NULL,
                  "factura_venta" => $selectVenta[0]->id,
                  //"valor_unitario" => $JwtAuth->encriptar($parametrosArray['arrayDesgloseVenta'][$i]['precioBase']),
                  "valor_unitario" => $parametrosArray['arrayDesgloseVenta'][$i]['precioBase'],
                  "entrada_cantidad" => NULL,
                  "entrada_valor" => NULL,
                  "salida_cantidad" => $desgloseVentaCantidad,
                  //"salida_valor" => $desgloseVentaImportePartida,
                  //"salida_valor" => $JwtAuth->encriptar($parametrosArray['arrayDesgloseVenta'][$i]['precioBase']*$desgloseVentaCantidad),
                  "salida_valor" => $parametrosArray['arrayDesgloseVenta'][$i]['precioBase'] * $desgloseVentaCantidad,
                  "saldo_cantidad" => $desgloseVentaCantidad,
                  "saldo_valor" => $parametrosArray['arrayDesgloseVenta'][$i]['precioBase'] * $new_saldo_cantidad
                )
              );
              $tokenKardexCont = $JwtAuth->encriptarToken('6hgiutiut' . $queryProdVenta[0]->id, 'kjfkjfkjhfkjf', time(), 'kbouitijugkjku', $parametrosArray['arrayDesgloseVenta'][$i]['precioBase']);
              $insertaContKardex = DB::table("kardex")->insert(
                array(
                  "token_kardex" => $tokenKardexCont,
                  "producto" => $queryProdVenta[0]->id,
                  "fecha" => time(),
                  "status_kardex" => 6,
                  "concepto" => 'venta' . time(),
                  "factura_compra" => NULL,
                  "factura_venta" => $selectVenta[0]->id,
                  //"valor_unitario" => $JwtAuth->encriptar($parametrosArray['arrayDesgloseVenta'][$i]['precioBase']),
                  "valor_unitario" => $parametrosArray['arrayDesgloseVenta'][$i]['precioBase'],
                  "entrada_cantidad" => NULL,
                  "entrada_valor" => NULL,
                  "salida_cantidad" => NULL,
                  "salida_valor" => NULL,
                  "saldo_cantidad" => $new_saldo_cantidad,
                  "saldo_valor" => $parametrosArray['arrayDesgloseVenta'][$i]['precioBase'] * $new_saldo_cantidad
                ),
              );
            }
          }

          if ($insertDetVenta->save() == TRUE) {
            //echo 'mundo jgjgjg';
            $countDesgloseVenta = $countDesgloseVenta + 1;
          } else {
            $countDesgloseVenta = $countDesgloseVenta + 0;
          }
        }

        //echo $countDesgloseVenta;
        if ($countDesgloseVenta == count($parametrosArray['arrayDesgloseVenta'])) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'venta realizada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'información de venta incorrecta'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'venta incorrectamente realizada'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
