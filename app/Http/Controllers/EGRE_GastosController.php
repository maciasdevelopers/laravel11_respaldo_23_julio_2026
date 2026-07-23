<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\GastosModelo;
use App\Models\ProductosModelo;
use App\Models\ServiciosModelo;
use QRCode;

class EGRE_GastosController extends Controller
{
  public function listaGastosVigentes(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $loteList = GastosModelo::join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.status_gasto' => TRUE,
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();
        foreach ($loteList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $arrayForeach = array(
            "token_gasto" => $value->token_gasto,
            "folio_gasto" => $JwtAuth->generar($value->folio_gasto),
            "fecha_sistema_gasto" => gmdate('Y-m-d H:i:s', $value->fecha_sistema_gasto),
          );
          $arrayGastos[] = $arrayForeach;
        }
        $dataMensaje = array(
          'datosGasto' => $arrayGastos,
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

  public function detalleGasto(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGastos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_gasto' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $loteList = GastosModelo::join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.status_gasto' => TRUE,
            'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();
        foreach ($loteList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          /*if ($value->evidencias == '') {
                        $lote_evidencia = '';
                        $nameDocEvidencia = '';
                    } else {
                        $rutaDocsEvid = $value->root_tkn.'/0002-cpp/catalogos/gastos/'.$JwtAuth->generar($value->folio_lote).'-'.$value->fecha_sistema_lote.'/';

                        if (file_exists(Storage::path('public/root/'.$rutaDocsEvid.$JwtAuth->desencriptar($value->evidencias)))) {
                            $extensionFiscal = pathinfo(Storage::path('public/root/'.$rutaDocsEvid.$JwtAuth->desencriptar($value->evidencias)), PATHINFO_EXTENSION);
                            $nameDocEvidencia = $JwtAuth->desencriptar($value->evidencias);
                            if ($extensionFiscal == 'pdf') {
                                $lote_evidencia = $JwtAuth->encriptaBase64Pdf(Storage::path('public/root/'.$rutaDocsEvid.$JwtAuth->desencriptar($value->evidencias)));
                            } 

                            if ($extensionFiscal == 'txt') {
                                $docevidencia = $JwtAuth->encriptaBase64(Storage::path('public/root/'.$rutaDocsEvid.$JwtAuth->desencriptar($value->evidencias)));
                                $lote_evidencia = '<img class="responsive-img circle materialboxed imag2" src="'.$docevidencia.'">';
                            }
                        } 
                    }*/

          $decimalesMoneda = DB::select(
            "SELECT catmon.decimales FROM catalogo_monedas AS catmon 
                        JOIN empresas AS emp JOIN empresapersonal AS emppers JOIN personal AS pers 
                        JOIN teci_usuarios AS users WHERE emp.moneda = catmon.id AND emp.emp_token = ?
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?",
            [$usuario->emp_token, $usuario->user_token]
          );

          $arrayProductos = array();
          if ($value->producto != '') {
            $prodList = ProductosModelo::join("catalogo_gastos AS catgas", "catalogo_productos.id", "=", "catgas.producto")
              ->join("productos", "catalogo_productos.producto", "=", "productos.id")
              ->join("genero AS gen", "productos.genero", "=", "gen.id")
              ->join("catalogo_prodservsat", "productos.catalogoSAT", "=", "catalogo_prodservsat.id")
              ->join("unidad_medida", "productos.medida_sat", "=", "unidad_medida.id")
              ->join("empresas", "catalogo_productos.administrador", "=", "empresas.id")
              ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
              ->join("personal", "empresapersonal.personal", "=", "personal.id")
              ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
              ->where([
                'catalogo_productos.status' => TRUE,
                'catgas.token_gasto' => $parametrosArray['token_gasto'],
                'empresas.emp_token' => $usuario->emp_token,
                'teci_usuarios.user_token' => $usuario->user_token,
              ])->get();

            foreach ($prodList as $vprod) {
              //echo $vprod->root_tkn;
              //da_te_default_timezone_set($vprod->zona_horaria);
              if ($JwtAuth->desencriptar($vprod->imagen) == 'default_prod.jpg') {
                $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vprod->imagen)));
              } else {
                $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
                  $vprod->root_tkn . '/0002-cpp/catalogos/productos/'
                  . $JwtAuth->generar($vprod->clasificacion) . '-' . $JwtAuth->generar($vprod->folio_genero) . '-' .
                  $JwtAuth->generar($vprod->folio) . '-' . $vprod->fecha_alta . '/' . $JwtAuth->desencriptar($vprod->imagen) . '.png'));
              }

              /*$filepath = $vprod->root_tkn."/0002-cpp/catalogos/productos/".$JwtAuth->generar($vprod->clasificacion)."-".
                                $JwtAuth->generar($vprod->folio_genero)."-".$JwtAuth->generar($vprod->folio)."-".$vprod->fecha_alta."/";
                                return QRCode::text('QR Code Generator for Laravel!')->png();*/

              $arrayForeachVig = array(
                "c_token" => $vprod->token_cat_productos,
                "imagen" => $logo_prod,
                "clasificacion" => $JwtAuth->generar($vprod->clasificacion) . '-' . $JwtAuth->generar($vprod->folio_genero) . '-' .
                  $JwtAuth->generar($vprod->folio),
                "producto" => $JwtAuth->desencriptar($vprod->producto),
                "clave" => $vprod->clave,
              );
              $arrayProductos[] = $arrayForeachVig;
            }
          }

          $arrayServicios = array();
          if ($value->servicio != '') {
            $servList = ServiciosModelo::join("catalogo_gastos AS catgas", "catalogo_productos.id", "=", "catgas.servicio")
              ->join("servicios AS ltserv", "catalogo_servicios.servicio", "=", "ltserv.id")
              ->join("genero AS gen", "ltserv.genero", "=", "gen.id")
              ->join("catalogo_prodservsat AS prsrvsat", "ltserv.catalogoSAT", "=", "prsrvsat.id")
              //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
              ->join("empresas AS emp", "catalogo_servicios.administrador", "=", "emp.id")
              ->join("empresapersonal", "emp.id", "=", "empresapersonal.empresa")
              ->join("personal AS pers", "empresapersonal.personal", "=", "pers.id")
              ->join("teci_usuarios AS users", "pers.usuario", "=", "users.id")
              ->where([
                'catalogo_servicios.status' => TRUE,
                'catalogo_servicios.proceso' => TRUE,
                'catgas.token_gasto' => $parametrosArray['token_gasto'],
                'emp.emp_token' => $usuario->emp_token,
                'users.user_token' => $usuario->user_token,
              ])->get();

            foreach ($servList as $vprod) {
              //echo $vprod->root_tkn;
              if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
                $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
              } else {
                $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
                  $value->root_tkn . '/0002-cpp/catalogos/servicios/'
                  . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
                  $JwtAuth->generar($value->folio) . '-' . $value->fechaAlta . '/' . $JwtAuth->desencriptar($value->imagen) . '.png'));
              }

              $arrayForeachVig = array(
                "c_token" => $value->token_cat_servicios,
                "imagen" => $logo_serv,
                "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
                  $JwtAuth->generar($value->folio),
                "servicio" => $JwtAuth->desencriptar($value->servicio),
                "clave" => $value->clave,
              );
              $arrayServicios[] = $arrayForeachVig;
            }
          }

          $arrayCompras = array();
          if ($value->compra != '' && $value->detalle_compra != '') {
            $listaCompras = ComprasModelo::join("detalle_compra AS detcomp", "compras.id", "detcomp.numero_compra")
              ->join("catalogo_gastos AS catgas", "compras.id", "=", "catgas.compra")
              ->join("personal AS ucomp", "compras.usuario_comprador", "=", "ucomp.id")
              ->join("personas AS people", "ucomp.personal", "=", "people.id")
              ->join("empresas AS emp", "compras.comprador", "=", "emp.id")
              ->join("empresapersonal AS emppers", "emp.id", "=", "emppers.empresa")
              ->join("personal AS pers", "emppers.personal", "=", "pers.id")
              ->join("teci_usuarios AS users", "pers.usuario", "=", "users.id")
              ->where([
                "detcomp.id" => $value->detalle_compra,
                'catgas.token_gasto' => $parametrosArray['token_gasto'],
                'emp.emp_token' => $usuario->emp_token,
                'users.user_token' => $usuario->user_token,
              ])->get();

            foreach ($listaCompras as $vcompra) {
              if ($vcompra->denominacion_rs != null) {
                $nombForeach = $JwtAuth->desencriptar($vcompra->denominacion_rs);
              } else {
                $nombForeach = $JwtAuth->desencriptar($vcompra->paterno) . " " . $JwtAuth->desencriptar($vcompra->materno) . " " . $JwtAuth->desencriptar($vcompra->nombre);
              }

              $precio_unitarioFormat = DB::select("SELECT FORMAT(?,?) AS total", [$vcompra->precio_unitario]);
              $descuentoFormat = DB::select("SELECT FORMAT(?,?) AS total", [$vcompra->descuento]);
              $retencionesFormat = DB::select("SELECT FORMAT(?,?) AS total", [$vcompra->total_retenciones]);
              $trasladosFormat = DB::select("SELECT FORMAT(?,?) AS total", [$vcompra->total_traslados]);

              $totalDetCompFormat = DB::select("SELECT 
                            FORMAT(((SUM(precio_unitario*cantidad) - SUM(descuento*cantidad)) -
                            SUM(total_retenciones)) + SUM(total_traslados),?) AS total
                            FROM detalle_compra WHERE token_detcompra = ?", [$decimalesMoneda[0]->decimales, $vcompra->token_detcompra]);

              $arrayForeach = array(
                "c_token" => $vcompra->c_token,
                "folio" => $JwtAuth->generar($vcompra->folio_compra),
                "fecha" => gmdate('Y-m-d H:i:s', $vcompra->fecha_altaCompra),
                "usuario_comprador" => $nombForeach,
                "status" => $vcompra->status,
                "precio_unitario" => $precio_unitarioFormat,
                "cantidad" => $vcompra->cantidad,
                "descuento" => $descuentoFormat,
                "total_retenciones" => $retencionesFormat,
                "total_traslados" => $trasladosFormat,
                "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
              );
              $arrayCompras[] = $arrayForeach;
            }
          }

          $arrayForeach = array(
            "token_gasto" => $value->token_gasto,
            "folio_gasto" => $JwtAuth->generar($value->folio_gasto),
            "fecha_sistema_gasto" => gmdate('Y-m-d H:i:s', $value->fecha_sistema_gasto),
            "producto" => $arrayProductos,
            "servicios" => $arrayServicios,
            "compras" => $arrayCompras,
          );
          $arrayGastos[] = $arrayForeach;
        }
        $dataMensaje = array(
          'datosLote' => $arrayGastos,
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

  public function updateEgresosGasto(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('user_token');
    $jsonLote = $request->input('arrayLote');
    $parametros = json_decode($jsonLote);
    $parametrosArray = json_decode($jsonLote, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_gasto' => 'required|string',
        'fecha_gasto' => 'required|string',
        'producto' => 'string',
        'servicio' => 'string',
        'compra' => 'string',
        'detalle_compra' => 'string',
      ]);
      if ($validate->fails() || $request->input('user_token') == '') {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Información incompleta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($request->input('user_token'), true);

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
                    JOIN empresapersonal AS emppers JOIN personal AS pers JOIN teci_usuarios AS users WHERE emp.emp_token = ? 
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $patrón = '/[aA-zZ_,.:;]/';
        $patrónlote = '/[aA0-zZ9-]/';
        $patrónFecha = '/^[0-9-]*$/';

        $token_gasto = $parametrosArray['token_gasto'];
        $fechaLote = $parametrosArray['fechaLote'];
        $numeroLote = $parametrosArray['numeroLote'];
        $comentarios = $parametrosArray['comentarios'];
        $nameEvidencia = $parametrosArray['nameEvidencia'];

        if (
          isset($fechaLote) && !empty($fechaLote) && preg_match($patrónFecha, $fechaLote) &&
          isset($numeroLote) && !empty($numeroLote) && preg_match($patrónlote, $numeroLote) &&
          isset($comentarios) && !empty($comentarios) && preg_match($patrón, $comentarios) &&
          isset($nameEvidencia) && !empty($nameEvidencia) && preg_match($patrón, $nameEvidencia)
        ) {

          $selectLote = DB::select("SELECT lote.fecha_sistema_lote,lote.folio_lote,lote.numero_lote,lote.fecha_lote,lote.evidencias,lote.comentarios FROM catalogo_gastos AS lote
                    JOIN empresas AS emp JOIN empresapersonal AS emppers JOIN personal AS pers JOIN teci_usuarios AS users WHERE lote.token_gasto = ? 
                    AND lote.empresa = emp.id AND emp.emp_token = ? AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token= ?", [$token_gasto, $usuario->emp_token, $usuario->user_token]);

          if (
            $selectLote[0]->fecha_lote == $JwtAuth->convierteFechaEpoc($fechaLote) &&
            $selectLote[0]->numero_lote == $JwtAuth->encriptar($numeroLote) &&
            $selectLote[0]->comentarios == $JwtAuth->encriptar($comentarios) &&
            $JwtAuth->desencriptar($selectLote[0]->evidencias) == $JwtAuth->encriptar($nameEvidencia)
          ) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'No es posible actualizar este lote ya que la información es la misma',
            );
          } else {
            $validatefechaLote = false;
            $validatenumlote = false;
            $validatecoments = false;
            $validateevidencia = false;

            if ($selectLote[0]->fecha_lote != $JwtAuth->convierteFechaEpoc($fechaLote)) {
              $actLotefecha = GastosModelo::join("empresas AS emp", "catalogo_gastos.empresa", "=", "emp.id")
                ->join("empresapersonal AS emppers", "emp.id", "=", "emppers.empresa")
                ->join("personal AS pers", "emppers.personal", "=", "pers.id")
                ->join("teci_usuarios AS users", "pers.usuario", "=", "users.id")
                ->where([
                  'catalogo_gastos.token_gasto' => $token_gasto,
                  'emp.emp_token' => $usuario->emp_token,
                  'users.user_token' => $usuario->user_token,
                ])
                ->limit(1)->update(
                  array(
                    'catalogo_gastos.fecha_lote' => $JwtAuth->convierteFechaEpoc($fechaLote),
                  )
                );
              if ($actLotefecha) {
                $validatefechaLote = true;
              } else {
                $validatefechaLote = false;
              }
            } else {
              $validatefechaLote = true;
            }

            if ($selectLote[0]->numero_lote != $JwtAuth->encriptar($numeroLote)) {
              $actLotenum = GastosModelo::join("empresas AS emp", "catalogo_gastos.empresa", "=", "emp.id")
                ->join("empresapersonal AS emppers", "emp.id", "=", "emppers.empresa")
                ->join("personal AS pers", "emppers.personal", "=", "pers.id")
                ->join("teci_usuarios AS users", "pers.usuario", "=", "users.id")
                ->where([
                  'catalogo_gastos.token_gasto' => $token_gasto,
                  'emp.emp_token' => $usuario->emp_token,
                  'users.user_token' => $usuario->user_token,
                ])
                ->limit(1)->update(
                  array(
                    'catalogo_gastos.numero_lote' => $JwtAuth->encriptar($numeroLote),
                  )
                );
              if ($actLotenum) {
                $validatenumlote = true;
              } else {
                $validatenumlote = false;
              }
            } else {
              $validatenumlote = true;
            }

            if ($selectLote[0]->comentarios != $JwtAuth->encriptar($comentarios)) {
              $actLotecoments = GastosModelo::join("empresas AS emp", "catalogo_gastos.empresa", "=", "emp.id")
                ->join("empresapersonal AS emppers", "emp.id", "=", "emppers.empresa")
                ->join("personal AS pers", "emppers.personal", "=", "pers.id")
                ->join("teci_usuarios AS users", "pers.usuario", "=", "users.id")
                ->where([
                  'catalogo_gastos.token_gasto' => $token_gasto,
                  'emp.emp_token' => $usuario->emp_token,
                  'users.user_token' => $usuario->user_token,
                ])
                ->limit(1)->update(
                  array(
                    'catalogo_gastos.comentarios' => $JwtAuth->encriptar($comentarios),
                  )
                );
              if ($actLotecoments) {
                $validatecoments = true;
              } else {
                $validatecoments = false;
              }
            } else {
              $validatecoments = true;
            }

            if ($JwtAuth->desencriptar($selectLote[0]->evidencias) != $nameEvidencia) {
              if (file_exists($request->file('imagenAltaPdfevidencialote'))) {
                $evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($selectLote[0]->folio_lote) . '-' . $selectLote[0]->fecha_sistema_lote . '-' . $nameEvidencia);
                $filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/Gastos/" . $JwtAuth->generar($selectLote[0]->folio_lote) . '-' .
                  $selectLote[0]->fecha_sistema_lote . "/";

                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }

                Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfevidencialote'), $JwtAuth->desencriptar($evidenciaNombre));

                $actLotedocs = GastosModelo::join("empresas AS emp", "catalogo_gastos.empresa", "=", "emp.id")
                  ->join("empresapersonal AS emppers", "emp.id", "=", "emppers.empresa")
                  ->join("personal AS pers", "emppers.personal", "=", "pers.id")
                  ->join("teci_usuarios AS users", "pers.usuario", "=", "users.id")
                  ->where([
                    'catalogo_gastos.token_gasto' => $token_gasto,
                    'emp.emp_token' => $usuario->emp_token,
                    'users.user_token' => $usuario->user_token,
                  ])
                  ->limit(1)->update(
                    array(
                      'catalogo_gastos.evidencias' =>  $evidenciaNombre,
                    )
                  );

                if ($actLotedocs) {
                  $validateevidencia = true;
                } else {
                  $validateevidencia = false;
                }
              } else {
                //$evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($selectLote[0]->folio_lote).'-'.$selectLote[0]->fecha_sistema_lote.'-base64.txt');
                $evidenciaNombre = '';
              }
            } else {
              $validateevidencia = true;
            }

            if ($validatefechaLote == true && $validatenumlote == true && $validatecoments == true && $validateevidencia == true) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'el lote con el folio ' . $JwtAuth->generar($selectLote[0]->folio_lote) . ' ha sido actualizado'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'el lote con el folio ' . $JwtAuth->generar($selectLote[0]->folio_lote) . ' no fue actualizado debido a errores internos, para mas información comuniquese a soporte'
              );
            }
          }
        } else {
          if (!isset($fechaLote) || empty($fechaLote) || !preg_match($patrónFecha, $fechaLote)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Ingrese fecha de lote',
            );
          }

          if (!isset($numeroLote) || empty($numeroLote) || !preg_match($patrónlote, $numeroLote)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Ingrese número de lote',
            );
          }

          if (!isset($comentarios) || empty($comentarios) || !preg_match($patrón, $comentarios)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Ingrese comentarios del lote',
            );
          }

          if (!isset($nameEvidencia) || empty($nameEvidencia) || !preg_match($patrón, $nameEvidencia)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Debe cargar o escanear evidencia de lote',
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

  public function listaGastosDelete(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_gasto' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $lotevincAlm = GastosModelo::join("detalle_almacen", "catalogo_gastos.id", "=", "detalle_almacen.num_lote")
          ->join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();

        $lotevinComp = GastosModelo::join("detalle_compra", "catalogo_gastos.id", "=", "detalle_compra.lote")
          ->join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();

        if (count($lotevincAlm) == 0 && count($lotevinComp) == 0) {
          $loteUpdate = GastosModelo::join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
            ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
            ->join("personal", "empresapersonal.personal", "=", "personal.id")
            ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
            ->where([
              'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
              'empresas.emp_token' => $usuario->emp_token,
              'teci_usuarios.user_token' => $usuario->user_token,
            ])->limit(1)->update(
              array(
                'catalogo_gastos.status_gasto' => FALSE,
                'catalogo_gastos.fecha_delete_lote' => time(),
              )
            );

          if ($loteUpdate) {
            $dataMensaje = array(
              'message' => 'El lote que ha seleccionado ha sido eliminado satisfactoriamente',
              'code' => 200,
              'status' => 'success'
            );
          } else {
            $dataMensaje = array(
              'message' => 'El lote que ha seleccionado no fue eliminado debido a errores internos, para mayor información comuniquese a soporte',
              'code' => 200,
              'status' => 'error'
            );
          }
        } else {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado no fue eliminado ya que esta vinculado a compras realizadas o productos en almacen',
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

  public function listaGastosDeleted(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $loteList = GastosModelo::join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.status_gasto' => FALSE,
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();
        foreach ($loteList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $arrayForeach = array(
            "token_gasto" => $value->token_gasto,
            "folio_lote" => $JwtAuth->generar($value->folio_lote),
            "numero_lote" => $JwtAuth->desencriptar($value->numero_lote),
            "fecha_lote" => gmdate('Y-m-d H:i:s', $value->fecha_lote),
            "fecha_delete_lote" => gmdate('Y-m-d H:i:s', $value->fecha_delete_lote),
          );
          $arrayGastos[] = $arrayForeach;
        }
        $dataMensaje = array(
          'datosLote' => $arrayGastos,
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

  public function gastRestart(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_gasto' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $loteUpdate = GastosModelo::join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->limit(1)->update(
            array(
              'catalogo_gastos.status_gasto' => TRUE,
              'catalogo_gastos.fecha_delete_lote' => '',
            )
          );

        if ($loteUpdate) {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado ha sido restaurado satisfactoriamente',
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado no fue restaurado debido a errores internos, para mayor información comuniquese a soporte',
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

  public function gastoDeletePerm(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_gasto' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $lotevincAlm = GastosModelo::join("detalle_almacen", "catalogo_gastos.id", "=", "detalle_almacen.num_lote")
          ->join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();

        $lotevinComp = GastosModelo::join("detalle_compra", "catalogo_gastos.id", "=", "detalle_compra.lote")
          ->join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
          ->where([
            'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
            'empresas.emp_token' => $usuario->emp_token,
            'teci_usuarios.user_token' => $usuario->user_token,
          ])->get();

        if (count($lotevincAlm) == 0 && count($lotevinComp) == 0) {
          $loteUpdate = GastosModelo::join("empresas", "catalogo_gastos.empresa", "=", "empresas.id")
            ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
            ->join("personal", "empresapersonal.personal", "=", "personal.id")
            ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
            ->where([
              'catalogo_gastos.token_gasto' => $parametrosArray['token_gasto'],
              'empresas.emp_token' => $usuario->emp_token,
              'teci_usuarios.user_token' => $usuario->user_token,
            ])->limit(1)->delete();

          if ($loteUpdate) {
            $dataMensaje = array(
              'message' => 'El lote que ha seleccionado ha sido eliminado satisfactoriamente',
              'code' => 200,
              'status' => 'success'
            );
          } else {
            $dataMensaje = array(
              'message' => 'El lote que ha seleccionado no fue eliminado debido a errores internos, para mayor información comuniquese a soporte',
              'code' => 200,
              'status' => 'error'
            );
          }
        } else {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado no fue eliminado ya que esta vinculado a compras realizadas o productos en almacen',
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

  public function registraLote(Request $request)
  {

    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('user_token');
    $jsonLote = $request->input('arrayLote');
    $parametros = json_decode($jsonLote);
    $parametrosArray = json_decode($jsonLote, true);
    $arrayGastos = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'fechaLote' => 'required|string',
        'numeroLote' => 'required|string',
        'comentarios' => 'required|string',
        'nameEvidencia' => 'required|string',
      ]);
      if ($validate->fails() || $request->input('user_token') == '') {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Información incompleta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($request->input('user_token'), true);

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
                    JOIN empresapersonal AS emppers JOIN personal AS pers JOIN teci_usuarios AS users WHERE emp.emp_token = ? 
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $patrón = '/[aA-zZ_,.:;]/';
        $patrónlote = '/[aA0-zZ9-]/';
        $patrónFecha = '/^[0-9-]*$/';

        $fechaLote = $parametrosArray['fechaLote'];
        $numeroLote = $parametrosArray['numeroLote'];
        $comentarios = $parametrosArray['comentarios'];
        $nameEvidencia = $parametrosArray['nameEvidencia'];

        if (
          isset($fechaLote) && !empty($fechaLote) && preg_match($patrónFecha, $fechaLote) &&
          isset($numeroLote) && !empty($numeroLote) && preg_match($patrónlote, $numeroLote) &&
          isset($comentarios) && !empty($comentarios) && preg_match($patrón, $comentarios) &&
          isset($nameEvidencia) && !empty($nameEvidencia) && preg_match($patrón, $nameEvidencia)
        ) {

          $fecha_sistema_lote = time();

          $folioLote = DB::select("SELECT IF (max(lote.folio_lote) IS NOT NULL,(max(lote.folio_lote)+1),1) AS folio
                        FROM catalogo_gastos AS lote JOIN empresas AS emp JOIN empresapersonal AS emppers 
                        JOIN personal AS pers JOIN teci_usuarios AS users WHERE lote.empresa = emp.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);

          if (file_exists($request->file('imagenAltaPdfevidencialote'))) {
            $evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($folioLote[0]->folio) . '-' . $fecha_sistema_lote . '-' . $nameEvidencia);
          } else {
            $evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($folioLote[0]->folio) . '-' . $fecha_sistema_lote . '-base64.txt');
          }

          $tokenLote = $JwtAuth->encriptarToken(time() . $fechaLote . $numeroLote . $comentarios);

          $newLote = new GastosModelo();
          $newLote->token_gasto = $tokenLote;
          $newLote->folio_lote = $folioLote[0]->folio;
          $newLote->numero_lote = $JwtAuth->encriptar($numeroLote);
          $newLote->fecha_sistema_lote = $fecha_sistema_lote;
          $newLote->fecha_lote = $JwtAuth->convierteFechaEpoc($fechaLote);
          $newLote->evidencias = $evidenciaNombre;
          $newLote->comentarios = $JwtAuth->encriptar($comentarios);
          $newLote->empresa = $selectEmp[0]->id;
          $newLote->status_gasto = TRUE;
          $newLote->fecha_delete_lote = '';
          $savednewLote = $newLote->save();
          if ($savednewLote) {
            $filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/Gastos/" . $JwtAuth->generar($folioLote[0]->folio) . '-' . $fecha_sistema_lote . "/";

            if (!file_exists(storage_path("/root/" . $filepath))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }

            if (file_exists($request->file('imagenAltaPdfevidencialote'))) {
              Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfevidencialote'), $JwtAuth->desencriptar($evidenciaNombre));
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este lote ha sido registrado satisfactoriamente con el folio ' . $JwtAuth->generar($folioLote[0]->folio),
            );
          } else {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este lote no ha sido registrado debido a problemas internos, comuniquese a soporte para más información',
            );
          }
        } else {
          if (!isset($fechaLote) || empty($fechaLote) || !preg_match($patrónFecha, $fechaLote)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Ingrese fecha de lote',
            );
          }

          if (!isset($numeroLote) || empty($numeroLote) || !preg_match($patrónlote, $numeroLote)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Ingrese número de lote',
            );
          }

          if (!isset($comentarios) || empty($comentarios) || !preg_match($patrón, $comentarios)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Ingrese comentarios del lote',
            );
          }

          if (!isset($nameEvidencia) || empty($nameEvidencia) || !preg_match($patrón, $nameEvidencia)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Debe cargar o escanear evidencia de lote',
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
}
