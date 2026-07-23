<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\ServiciosModelo;
use App\Models\ClientesModelo;
use App\Models\ProveedoresModelo;
use App\Models\DescuentosModelo;
use App\Models\PromocionesModelo;
use App\Models\MonedasModelo;
use App\Models\ListaPreciosModelo;
use App\Models\ClasificacionModelo;
use PDF;
use QRCode;

class EGRE_ServiciosController extends Controller
{
  public function listaegresosServiciosVigentes(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $arrayServVigentes = array();

    $servList = ServiciosModelo::join("sos_ps_genero AS gen", "catserv.genero", "=", "gen.id")
      ->join("teci_catalogo_prodservsat AS prsrvsat", "catserv.catalogo_sat", "=", "prsrvsat.id")
      //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
      ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
      ->where([
        'catserv.status' => TRUE,
        'catserv.proceso' => TRUE,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])->get();

    foreach ($servList as $value) {

      /*$updateImg = DB::table("in_egr_catalogo_servicios AS catserv")
                ->where([
                    "catserv.token_cat_servicios" => $value->token_cat_servicios,
                ])
                ->limit(1)->update(
                    array(
                        "catserv._servicio" => $value->old_servicio,
                        "catserv.clasificacion" => $value->old_clasificacion,
                        "catserv.genero" => $value->old_genero,
                        "catserv.catalogo_sat" => $value->old_catalogo_sat,
                        "catserv.medida_sat" => $value->old_medida_sat,
                        "catserv.imagen" => $value->old_imagen,
                    )
                );
                echo " ".$value->old_imagen." ";*/
      if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
        $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
      } else {
        $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
          $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $value->fecha_sistema . '-' .
          $JwtAuth->generar($value->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
      }

      if ($value->post_folio == NULL) {
        $folio_prov = 'srv-' . $JwtAuth->generarFolio($value->folio_sistema);
      } else {
        $folio_prov = 'srv-' . $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio;
      }

      if ($value->utilizado == TRUE) {
        $utilizado = true;
      } else {
        $utilizado = false;
      }

      $arrayForeachVig = array(
        "c_token" => $value->token_cat_servicios,
        "imagen" => $logo_serv,
        "folio_sistema" => $folio_prov,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
          $JwtAuth->generar($value->folio),
        "servicio" => $JwtAuth->desencriptar($value->servicio),
        "clave" => $value->clave,
        "utilizado" => $utilizado
      );
      $arrayServVigentes[] = $arrayForeachVig;
    }

    return response()->json([
      'datosServicio' => $arrayServVigentes,
      'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'servicios', $usuario->empresa_token, $usuario->user_token),
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function viewServicioEgresos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProvServ = array();
    $arrayPeriodicidad = array();
    $arrayServVigentes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'servdata' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $servicio_selected = $parametrosArray['servdata'];
        $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.token_cat_servicios' => $servicio_selected,
            'in_egr_catalogo_servicios.status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          //$servList = ServiciosModelo::join("clasificacion AS classif","in_egr_catalogo_servicios.clasificacion","=","classif.id")
          //->join("sos_ps_genero AS gen","ltserv.genero","=","gen.id")
          //->join("teci_catalogo_prodservsat AS prsrvsat","ltserv.catalogoSAT","=","prsrvsat.id")
          //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
          //->join("main_empresas AS emp","catserv.administrador","=","emp.id")
          //->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
          //->join("vhum_personal AS pers","empresapersonal.personal","=","pers.id")
          //->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
          //->where([
          //    'catserv.token_cat_servicios' => $parametrosArray['servdata'],
          //    'catserv.status' => TRUE,
          //    'catserv.proceso' => TRUE,
          //    'emp.empresa_token' => $usuario->empresa_token,
          //    'users.usuario_token' => $usuario->user_token,
          //])->get();
          if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
          } else {
            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
              $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $value->fecha_sistema . '-' .
              $JwtAuth->generar($value->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
          }

          $file_pdf = Storage::path('public/root/' .
            $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $value->fecha_sistema . '-' .
            $JwtAuth->generar($value->folio_sistema) . '/' . $value->fecha_sistema . '-' .
            $JwtAuth->generar($value->folio_sistema) . '.pdf');

          if (file_exists($file_pdf)) {
            $pdf_serv = $JwtAuth->encriptaBase64($file_pdf);
            $pdf_name = $value->fecha_sistema . '-' . $JwtAuth->generar($value->folio_sistema);
          } else {
            $pdf_serv = null;
            $pdf_name = null;
          }

          $listaProveedores = ProveedoresModelo::join("personas AS prov", "catalogo_proveedores.proveedor", "prov.id")
            ->join("main_empresas AS emp", "catalogo_proveedores.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("personal", "empresapersonal.personal", "=", "personal.id")
            ->join("usuarios", "personal.usuario", "=", "usuarios.id")
            ->where([
              'emp.empresa_token' => $usuario->empresa_token,
              'usuarios.user_token' => $usuario->user_token,
              'catalogo_proveedores.status' => true
            ])->get();

          foreach ($listaProveedores as $resListProv) {

            $provservLista = ServiciosModelo::join(
              "serv_claves AS clavserv",
              "catserv.id",
              "=",
              "clavserv.servicio_id"
            )
              ->join("catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
              ->join("personas AS people", "catprov.proveedor", "=", "people.id")
              ->where([
                'catprov.token_cat_proveedores' => $resListProv->token_cat_proveedores,
                'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                'catprov.status' => true
              ])->get();

            $tiene_clave = '';
            $claveAsignada = '';
            $token_serv_claves = '';
            $encendido = false;
            $trProv = '';
            foreach ($provservLista as $relservprov) {
              if ($relservprov->tiene_clave == TRUE) {
                $tiene_clave = 'true';
              } else {
                $tiene_clave = 'false';
              }

              if ($relservprov->asigned_clave != NULL && $relservprov->asigned_clave != '') {
                $claveAsignada = $JwtAuth->desencriptar($relservprov->asigned_clave);
              } else {
                $claveAsignada = '';
              }

              $token_serv_claves = $relservprov->token_serv_claves;
              $encendido = true;
              $trProv = 'trCliente';
            }

            $rfc_generico = $resListProv->rfc_generico;

            if ($resListProv->rfc != NULL) {
              $rfc_prov = $JwtAuth->desencriptar($resListProv->rfc);
            } else {
              $rfc_prov = '---';
            }

            if ($resListProv->tax_id != NULL) {
              $tax_id_prov = $JwtAuth->desencriptar($resListProv->tax_id);
            } else {
              $tax_id_prov = '---';
            }

            if ($resListProv->post_folio == NULL) {
              $folio_prov = 'prv-' . $JwtAuth->generarFolio($resListProv->folio);
            } else {
              $folio_prov = 'prv-' . $JwtAuth->generarFolio($resListProv->folio) . '-' . $resListProv->post_folio;
            }

            if ($resListProv->denominacion_rs != '') {
              $nombreProv = $JwtAuth->desencriptar($resListProv->denominacion_rs);
            } else {
              $nombreProv = $JwtAuth->desencriptar($resListProv->paterno) . " " .
                $JwtAuth->desencriptar($resListProv->materno) . " " .
                $JwtAuth->desencriptar($resListProv->nombre);
            }

            $arrayForeach = array(
              "token_cat_proveedores" => $resListProv->token_cat_proveedores,
              "rfc_generico" => $rfc_generico,
              "rfc_prov" => $rfc_prov,
              "tax_id_prov" => $tax_id_prov,
              "folio" => $folio_prov,
              "nombre" => $nombreProv,
              "tiene_clave" => $tiene_clave,
              "tiene_clave_respaldo" => $tiene_clave,
              "asigned_clave" => $claveAsignada,
              "asigned_clave_respaldo" => $claveAsignada,
              "token_serv_claves" => $token_serv_claves,
              "encendido" => $encendido,
              "class" => $trProv,
              "tdClass" => "",
              "btnClass" => false,
            );

            $arrayProvServ[] = $arrayForeach;
          }
          $arrayGenero = array();
          $listaClass = ClasificacionModelo::join("genero", "clasificacion.id", "genero.clasificacion")
            ->where('clasificacion.codigo', '=', '6')->get();

          foreach ($listaClass as $clasVal) {
            if ($clasVal->token_genero == $value->token_genero) {
              $selected = true;
            } else {
              $selected = false;
            }

            $lista = array(
              "token_clascificacion" => $clasVal->token_clascificacion,
              "concepto" => $clasVal->concepto,
              "codigo" => $clasVal->codigo,
              "token_genero" => $clasVal->token_genero,
              "folio_genero" => $clasVal->folio_genero,
              "clasificacion" => $clasVal->clasificacion,
              "selected" => $selected,
            );
            $arrayGenero[] = $lista;
          }

          if ($value->post_folio == NULL) {
            $folio_prov = 'SRV-' . $JwtAuth->generarFolio($value->folio_sistema);
          } else {
            $folio_prov = 'SRV-' . $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio;
          }

          if ($value->periodicidad == TRUE) {
            $periodicidadPc = 'periodo';
          } else {
            $periodicidadPc = 'eventual';
          }

          $iteracionPc = $value->repeticion_periodo;

          if ($value->periodicidad == TRUE) {
            if ($value->tipo_periodo == TRUE) {
              $periodoDetIndPc = 'determinado';
              $txtfechaFinPc = $JwtAuth->convierteEpocFechaHtmlMY($value->zona_horaria, $value->fecha_finPeriodo);
            }
            if ($value->tipo_periodo == FALSE) {
              $periodoDetIndPc = 'indeterminado';
              $txtfechaFinPc = null;
            }
          }
          if ($value->periodicidad == FALSE) {
            $periodoDetIndPc = null;
            $txtfechaFinPc = null;
          }

          $decimalesMoneda = DB::select(
            "SELECT catmon.decimales FROM catalogo_monedas AS catmon 
                            JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers 
                            JOIN teci_usuarios_catalogo AS users WHERE emp.moneda = catmon.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
            [$usuario->empresa_token, $usuario->user_token]
          );

          $tipo_variabilidad = $value->tipo_variabilidad;

          $minimoFormat = DB::select(
            "SELECT ROUND(importe_minimo,?) AS total FROM catalogo_servicios 
                            WHERE token_cat_servicios = ?",
            [$decimalesMoneda[0]->decimales, $parametrosArray['servdata']]
          );
          $importe_minimo = $minimoFormat[0]->total;

          $maximoFormat = DB::select(
            "SELECT ROUND(importe_maximo,?) AS total FROM catalogo_servicios 
                            WHERE token_cat_servicios = ?",
            [$decimalesMoneda[0]->decimales, $parametrosArray['servdata']]
          );
          $importe_maximo = $maximoFormat[0]->total;

          $listaPeriodicidad = array(
            "periodicidadPc" => $periodicidadPc,
            "iteracionPc" => $iteracionPc,
            "periodoDetIndPc" => $periodoDetIndPc,
            "txtfechaFinPc" => $txtfechaFinPc,
            "tipo_variabilidad" => $tipo_variabilidad,
            "importe_minimo" => $importe_minimo,
            "importe_maximo" => $importe_maximo,
          );
          $arrayPeriodicidad[] = $listaPeriodicidad;

          $arrayForeachVig = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "imagen" => $logo_serv,
            "pdf_serv" => $pdf_serv,
            "pdf_name" => $pdf_name,
            "folio_sistema" => $folio_prov,
            "clasificacion" => $value->token_clascificacion,
            "code-clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
              $JwtAuth->generar($value->folio),
            "genero" => $value->token_genero,
            "arrayGenero" => $arrayGenero,
            "unidad_medida" => $value->unidad_medida,
            "sat_clave" => $value->sat_clave,
            "representa" => $value->representa,
            "token_unidad_medida" => $value->token_unidad_medida,
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "clave" => $value->clave,
            "descripcion" => $value->descripcion,
            "tokenSat" => $value->token_prodservsat,
            "proveedores" => $arrayProvServ,
            //"fechaAlta" => date('d-m-Y H:i:s',$value->fechaAlta)
            "fechaAlta" => $JwtAuth->convierteEpocFechaHtml($value->zona_horaria, $value->fechaAlta),
            "arrayPeriodicidad" => $arrayPeriodicidad
          );
          $arrayServVigentes[] = $arrayForeachVig;
        }

        $dataMensaje = array(
          'datosServicio' => $arrayServVigentes,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleServicioProveedor(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $ordenes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'tokenProveedor' => 'required|string',
        'token_articulo' => 'required|string',
        'identificador' => 'required|string',
        'noIdentificacionXML' => 'string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación del usuario invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        if ($parametrosArray['noIdentificacionXML'] != '') {
          $noIdentificacionXML = $JwtAuth->encriptar(strtolower($parametrosArray['noIdentificacionXML']));
        } else {
          $noIdentificacionXML = NULL;
        }

        $prodList = ServiciosModelo::join("servicios AS listserv", "catserv.servicio", "=", "listserv.id")
          ->join("serv_claves AS clavserv", "catserv.id", "=", "clavserv.servicio_id")
          ->join("catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
          ->join("empresas", "catserv.administrador", "=", "empresas.id")
          ->join("empresapersonal", "empresas.id", "=", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("usuarios", "personal.usuario", "=", "usuarios.id")
          ->where([
            'catserv.token_cat_servicios' => $parametrosArray['token_articulo'],
            'catprov.token_cat_proveedores' => $parametrosArray['tokenProveedor'],
            'clavserv.asigned_clave' => $noIdentificacionXML,
            'catserv.status' => true,
            'empresas.empresa_token' => $usuario->empresa_token,
            'usuarios.user_token' => $usuario->user_token,
          ])->get();

        if (count($prodList) > 0) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'articulo homologado',
            'token_articulo' => $parametrosArray['token_articulo'],
            'identificador' => 'Servicio',
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Los codigos de identificación de acuerdo al proveedor seleccionado no coinciden'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recargaProvServicios(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProvServ = array();

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_servicios' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaProveedores = ProveedoresModelo::join("personas AS prov", "catalogo_proveedores.proveedor", "prov.id")
          ->join("main_empresas AS emp", "catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("usuarios", "personal.usuario", "=", "usuarios.id")
          ->where([
            'catalogo_proveedores.status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'usuarios.user_token' => $usuario->user_token,
            'catalogo_proveedores.status' => true
          ])->get();

        foreach ($listaProveedores as $resListProv) {

          $provservLista = ServiciosModelo::join(
            "serv_claves AS clavserv",
            "catserv.id",
            "=",
            "clavserv.servicio_id"
          )
            ->join("catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
            ->join("personas AS people", "catprov.proveedor", "=", "people.id")
            ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("vhum_personal AS pers", "empresapersonal.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'catprov.token_cat_proveedores' => $resListProv->token_cat_proveedores,
              'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicios'],
              'catserv.status' => TRUE,
              'catserv.proceso' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

          $tiene_clave = '';
          $claveAsignada = '';
          $token_serv_claves = '';
          $encendido = false;
          $trProv = '';

          foreach ($provservLista as $relservprov) {
            if ($relservprov->tiene_clave == TRUE) {
              $tiene_clave = 'true';
            } else {
              $tiene_clave = 'false';
            }

            if ($relservprov->asigned_clave != NULL && $relservprov->asigned_clave != '') {
              $claveAsignada = $JwtAuth->desencriptar($relservprov->asigned_clave);
            } else {
              $claveAsignada = '';
            }

            $token_serv_claves = $relservprov->token_serv_claves;
            $encendido = true;
            $trProv = 'trCliente';
          }

          if ($resListProv->rfc_taxId != NULL) {
            $dataResRfc = $JwtAuth->desencriptar($resListProv->rfc_taxId);
          } else {
            $dataResRfc = $resListProv->rfc_generico;
          }

          if ($resListProv->denominacion_rs != '') {
            $nombreProv = $JwtAuth->desencriptar($resListProv->denominacion_rs);
          } else {
            $nombreProv = $JwtAuth->desencriptar($resListProv->paterno) . " " .
              $JwtAuth->desencriptar($resListProv->materno) . " " .
              $JwtAuth->desencriptar($resListProv->nombre);
          }

          $arrayForeach = array(
            "token_cat_proveedores" => $resListProv->token_cat_proveedores,
            "rfc" => $dataResRfc,
            "folio" => $JwtAuth->generar($resListProv->folio),
            "nombre" => $nombreProv,
            "tiene_clave" => $tiene_clave,
            "asigned_clave" => $claveAsignada,
            "asigned_clave_respaldo" => $claveAsignada,
            "token_serv_claves" => $token_serv_claves,
            "encendido" => $encendido,
            "class" => $trProv,
          );

          $arrayProvServ[] = $arrayForeach;
        }

        $dataMensaje = array(
          'proveedores' => $arrayProvServ,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function downloadServicioEgresosPdf(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $proveedor = $parametros->servdata;
    $arrayProvServ = array();
    $arrayServVigentes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'servdata' => 'required'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $servList = ServiciosModelo::join("servicios AS ltserv", "catserv.servicio", "=", "ltserv.id")
          ->join("sos_ps_genero AS gen", "ltserv.genero", "=", "gen.id")
          ->join("teci_catalogo_prodservsat AS prsrvsat", "ltserv.catalogoSAT", "=", "prsrvsat.id")
          //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
          ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_personal AS pers", "empresapersonal.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            'catserv.token_cat_servicios' => $parametrosArray['servdata'],
            'catserv.status' => TRUE,
            'catserv.proceso' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $pdf_serv = Storage::path('public/root/' .
            $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $JwtAuth->generar($value->clasificacion) . '-' .
            $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio) . '-' .
            $value->fechaAlta . '/' . $JwtAuth->desencriptar($value->imagen) . '.pdf');

          $dompdf = \PDF::loadView($pdf_serv);
          return response()->download($dompdf);

          //$dompdf->setPaper("A2", "portrait");
          //$dompdf->render();
          //$contenidoPDF = $dompdf->output();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaGeneralesServicio(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_cat_servicio' => 'required|string',
        'fechaAlta' => 'required|string',
        'clasificacion' => 'required|string',
        'genero' => 'required|string',
        'clave_sat' => 'required|numeric',
        'concepto' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {

        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,emp.zona_horaria FROM empresas AS emp  
                    JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
        //echo $selectEmp[0]->id;
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $folioServ = DB::select("SELECT COUNT(catserv.id) AS folio FROM catalogo_servicios AS catserv 
                        JOIN servicios AS listServ JOIN sos_ps_genero AS gen JOIN empresas AS emp 
                        JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id AND gen.token_genero = ?
                        AND catserv.administrador = emp.id AND emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token= ?", [$parametrosArray['genero'], $usuario->empresa_token, $usuario->user_token]);

        $folioAsignadoServ = DB::select("SELECT catserv.folio FROM catalogo_servicios AS catserv 
                        JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catserv.token_cat_servicios = ? AND catserv.administrador = emp.id AND emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token= ?", [$parametrosArray['token_cat_servicio'], $usuario->empresa_token, $usuario->user_token]);

        $selectGenero = DB::select("SELECT gen.id FROM sos_ps_genero AS gen JOIN servicios AS listServ
                        JOIN catalogo_servicios AS catserv JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id
                        AND catserv.administrador = emp.id AND emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

        $clasifServ = DB::select("SELECT id FROM clasificacion WHERE token_clascificacion = ?", [$parametrosArray['clasificacion']]);
        //echo $clasifServ[0]->id;

        if ($selectGenero[0]->id == $clasifServ[0]->id) {
          $nuevofolio = $folioAsignadoServ[0]->folio;
        } else {
          $nuevofolio = $folioServ[0]->folio + 1;
        }

        $genroServ = DB::select("SELECT id,folio_genero,concepto FROM genero WHERE token_genero = ?", [$parametrosArray['genero']]);
        //$genroServ[0]->id;

        $claveSat = DB::select("SELECT id,descripcion FROM teci_catalogo_prodservsat WHERE clave = ?", [$parametrosArray['clave_sat']]);
        //echo " claveSat ".$claveSat[0]->id;

        $fechaAlta = $JwtAuth->convierteFechaEpoc($parametrosArray['fechaAlta']);
        //echo $fechaAlta;

        $conceptoServ = $JwtAuth->encriptar($parametrosArray['concepto']);

        $upDateServicio = ServiciosModelo::join("servicios AS serv", "catserv.servicio", "=", "serv.id")
          ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
          ->join("empresapersonal AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            'catserv.status' => TRUE,
            'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicio'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(
            array(
              "serv.servicio" => $conceptoServ,
              "serv.clasificacion" => $clasifServ[0]->id,
              "serv.genero" => $genroServ[0]->id,
              "serv.catalogoSAT" => $claveSat[0]->id,
              "catserv.fechaAlta" => $fechaAlta,
              "catserv.folio" => $nuevofolio
            )
          );

        if ($upDateServicio) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Datos generales de este servicio actualizados satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Datos generales de este servicio no fueron actualizados debido a problemas internos, comuniquese a soporte para más información'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta modificar es invalida o inexistente'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaProvClavesServicio(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_cat_servicio' => 'required|string',
        'tknProveedor' => 'required|string',
        'serv_claveTkn' => 'required|string',
        'tiene_clave' => 'required|string',
        'clave' => 'string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {

        $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['tknProveedor']]);

        if (count($obtenProv) == 1) {

          if ($parametrosArray['tiene_clave'] == 'true') {
            $tiene_clave = TRUE;
            $asigned_clave = $JwtAuth->encriptar($parametrosArray['clave']);
          } else {
            $tiene_clave = FALSE;
            $asigned_clave = NULL;
          }

          $upDateServicio = DB::table('serv_claves')
            ->join("catalogo_servicios AS catserv", "serv_claves.servicio_id", "=", "catserv.id")
            ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
            ->join("empresapersonal AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'serv_claves.token_serv_claves' => $parametrosArray['serv_claveTkn'],
              'serv_claves.proveedor' => $obtenProv[0]->id,
              'catserv.status' => TRUE,
              'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicio'],
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                "serv_claves.tiene_clave" => $tiene_clave,
                "serv_claves.asigned_clave" => $asigned_clave,
              )
            );

          if ($upDateServicio) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Relación de proveedor con este servicio actualizados satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Relación de proveedor con este servicio no fue actualizada debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'proveedor inexistente'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta modificar es invalida o inexistente'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function newProvClavesServicio(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_cat_servicio' => 'required|string',
        'tknProveedor' => 'required|string',
        'tiene_clave' => 'required|string',
        'clave' => 'string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {

        $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['tknProveedor']]);
        $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?", [$parametrosArray['token_cat_servicio']]);
        $tkn_clavesServ = $JwtAuth->encriptarToken(time(), $parametrosArray['token_cat_servicio'], $parametrosArray['tknProveedor']);

        if ($parametrosArray['tiene_clave'] == 'true') {
          $tiene_clave = TRUE;
          $asigned_clave = $JwtAuth->encriptar(strtolower($parametrosArray['clave']));
        } else {
          $tiene_clave = FALSE;
          $asigned_clave = NULL;
        }

        if (count($obtenProv) == 1) {
          $insertaClaves = DB::table('serv_claves')
            ->insert(array(
              "token_serv_claves" =>  $tkn_clavesServ,
              "servicio_id" => $obtenServicio[0]->id,
              "proveedor" => $obtenProv[0]->id,
              "tiene_clave" => $tiene_clave,
              "asigned_clave" => $asigned_clave,
              "periodicidad_c_v" => NULL,
              "notificacion_c_v" => NULL,
              "inicio_periodo" => NULL,
              "fin_periodo" => NULL,
              "status_c_v" => FALSE
            ));

          if ($insertaClaves) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Relación de proveedor con este servicio guradada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Relación de proveedor con este servicio no fue guardada debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'proveedor inexistente'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta modificar es invalida o inexistente'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteProvClavesServicio(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_cat_servicio' => 'required|string',
        'tknProveedor' => 'required|string',
        'serv_claveTkn' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {

        $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['tknProveedor']]);
        $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?", [$parametrosArray['token_cat_servicio']]);

        if (count($obtenProv) == 1 && count($obtenServicio) == 1) {
          $deleteServicio = DB::table('serv_claves')
            ->where([
              "token_serv_claves" => $parametrosArray['serv_claveTkn'],
              "servicio_id" => $obtenServicio[0]->id,
              "proveedor" => $obtenProv[0]->id,
            ])
            ->limit(1)->delete();

          if ($deleteServicio) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Relación de proveedor con este servicio eliminada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Relación de proveedor con este servicio no fue eliminada debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'proveedor inexistente'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta modificar es invalida o inexistente'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteServicioEgresos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'servdata' => 'required|string'
      ]);

      if ($validate->fails()) {
        return response()->json([
          'status' => 'error',
          'code' => 400,
          'message' => 'elementos de busqueda invalidos',
          'errors' => $validate->errors()
        ]);
      } else {
        $obtenCompraServ = DB::select("SELECT * FROM detalle_compra AS detcomp JOIN catalogo_servicios AS catserv 
                        JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE detcomp.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id 
                        AND users.usuario_token = ?", [$parametrosArray['servdata'], $usuario->empresa_token, $usuario->user_token]);

        if (count($obtenCompraServ) == 0) {
          $prodDeleteList = ServiciosModelo::join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
            ->join("empresapersonal AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
            ->where([
              'catserv.token_cat_servicios' => $parametrosArray['servdata'],
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                'catserv.fecha_delete_serv' => time(),
                'catserv.status' => FALSE
              )
            );

          if ($prodDeleteList) {
            return response()->json([
              'status' => 'success',
              'code' => 200,
              'message' => 'servicio eliminado satisfactoriamente'
            ]);
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 200,
              'message' => 'servicio no eliminado'
            ]);
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'message' => 'servicio no eliminado, esta vinculado a compras'
          ]);
        }
      }
    } else {
      return response()->json([
        'status' => 'error',
        'code' => 200,
        'message' => 'datos incorrectos'
      ]);
    }
  }

  public function listaegresosServiciosEliminados(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $arrayServVigentes = array();
    $servList = ServiciosModelo::join("servicios AS ltserv", "catserv.servicio", "=", "ltserv.id")
      ->join("sos_ps_genero AS gen", "ltserv.genero", "=", "gen.id")
      ->join("teci_catalogo_prodservsat AS prsrvsat", "ltserv.catalogoSAT", "=", "prsrvsat.id")
      ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
      ->where([
        'catserv.status' => FALSE,
        'catserv.proceso' => TRUE,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])->get();

    foreach ($servList as $value) {
      //da_te_default_timezone_set($value->zona_horaria);
      if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
        $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($value->imagen)));
      } else {
        $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
          $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $value->fecha_sistema . '-' .
          $JwtAuth->generar($value->folio_sistema) . '/' . $JwtAuth->desencriptar($value->imagen)));
      }

      if ($value->post_folio == NULL) {
        $folio_prov = 'srv-' . $JwtAuth->generarFolio($value->folio_sistema);
      } else {
        $folio_prov = 'srv-' . $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio;
      }

      $arrayForeachVig = array(
        "c_token" => $value->token_cat_servicios,
        "imagen" => $logo_serv,
        "folio_sistema" => $folio_prov,
        "servicio" => $JwtAuth->desencriptar($value->servicio),
        "clave" => $value->clave,
        "fechaDelete" => $fecha = gmdate('Y-m-d H:i:s', $value->fecha_delete_serv)
      );
      $arrayServVigentes[] = $arrayForeachVig;
    }

    return response()->json([
      'datosServicio' => $arrayServVigentes,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function restartServicioEgresos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'servdata' => 'required|string'
      ]);

      if ($validate->fails()) {
        return response()->json([
          'status' => 'error',
          'code' => 400,
          'message' => 'elementos de busqueda invalidos',
          'errors' => $validate->errors()
        ]);
      } else {
        $prodDeleteList = ServiciosModelo::join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
          ->join("empresapersonal AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            'catserv.token_cat_servicios' => $parametrosArray['servdata'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(
            array(
              'catserv.fecha_delete_serv' => '',
              'catserv.status' => TRUE
            )
          );

        if ($prodDeleteList) {
          return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'servicio restaurado satisfactoriamente'
          ]);
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'message' => 'servicio no restaurado'
          ]);
        }
      }
    } else {
      return response()->json([
        'status' => 'error',
        'code' => 200,
        'message' => 'datos incorrectos'
      ]);
    }
  }

  public function deleteDeadServicioEgresos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'servdata' => 'required|string'
      ]);

      if ($validate->fails()) {
        return response()->json([
          'status' => 'error',
          'code' => 400,
          'message' => 'elementos de busqueda invalidos',
          'errors' => $validate->errors()
        ]);
      } else {
        $obtenCompraServ = DB::select("SELECT * FROM detalle_compra AS detcomp JOIN catalogo_servicios AS catserv 
                        JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE detcomp.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id 
                        AND users.usuario_token = ?", [$parametrosArray['servdata'], $usuario->empresa_token, $usuario->user_token]);

        if (count($obtenCompraServ) == 0) {


          $provservLista = ServiciosModelo::join("serv_claves AS clavserv", "catserv.id", "=", "clavserv.servicio_id")
            ->where([
              'catserv.token_cat_servicios' => $parametrosArray['servdata']
            ])->count();

          if ($provservLista >= 1) {
            $deleteProdClaveServ = ServiciosModelo::join("serv_claves AS clavserv", "catserv.id", "=", "clavserv.servicio_id")
              ->where([
                'catserv.token_cat_servicios' => $parametrosArray['servdata']
              ])->limit(1)->delete();

            if ($deleteProdClaveServ) {
              $prodDeleteList = ServiciosModelo::join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
                ->join("empresapersonal AS empuser", "emp.id", "=", "empuser.empresa")
                ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
                ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
                ->where([
                  'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                  'emp.empresa_token' => $usuario->empresa_token,
                  'users.usuario_token' => $usuario->user_token,
                ])
                ->limit(1)->update(
                  array(
                    'catserv.fecha_delete_serv' => time(),
                    'catserv.status' => FALSE
                  )
                );

              if ($prodDeleteList) {
                return response()->json([
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'servicio eliminado satisfactoriamente'
                ]);
              } else {
                return response()->json([
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'servicio no eliminado'
                ]);
              }
            } else {
              return response()->json([
                'status' => 'error',
                'code' => 200,
                'message' => 'relación de servicio con proveedor no eliminada'
              ]);
            }
          } else {
            $prodDeleteList = ServiciosModelo::join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
              ->join("empresapersonal AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("vhum_personal AS pers", "empuser.personal", "=", "pers.id")
              ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
              ->where([
                'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                'emp.empresa_token' => $usuario->empresa_token,
                'users.usuario_token' => $usuario->user_token,
              ])
              ->limit(1)->update(
                array(
                  'catserv.fecha_delete_serv' => time(),
                  'catserv.status' => FALSE
                )
              );

            if ($prodDeleteList) {
              return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'servicio eliminado satisfactoriamente'
              ]);
            } else {
              return response()->json([
                'status' => 'error',
                'code' => 200,
                'message' => 'servicio no eliminado'
              ]);
            }
          }
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 200,
            'message' => 'servicio no eliminado, esta vinculado a compras'
          ]);
        }
      }
    } else {
      return response()->json([
        'status' => 'error',
        'code' => 200,
        'message' => 'datos incorrectos'
      ]);
    }
  }

  public function registroServicio(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'concepto' => 'required|string',
        'fechaAlta' => 'required|string',
        'clasificacion' => 'required',
        'genero' => 'required|string',
        'clave_sat' => 'required|numeric',
        'unidad_medida' => 'required|string',
        'proveedor' => 'array'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($request->input('user_token'), true);

        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,emp.zona_horaria,people.paterno,
                        people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM empresas AS emp  
                        JOIN personas AS people JOIN empresapersonal AS empuser JOIN vhum_personal AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id 
                        AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
        //echo $selectEmp[0]->id;
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
        //echo 'prueba '; exit;

        $infoUser = DB::table("teci_usuarios_catalogo AS users")
          ->join("personal", "users.id", "=", "personal.usuario")
          ->join("area", "personal.area", "=", "area.id")
          ->join("cargo", "personal.cargo", "=", "cargo.id")
          ->join("personas AS people", "personal.personal", "=", "people.id")
          ->join("empresapersonal", "personal.id", "=", "empresapersonal.personal")
          ->join("main_empresas AS emp", "empresapersonal.empresa", "=", "emp.id")
          ->where([
            'users.usuario_token' => $usuario->user_token,
            'emp.empresa_token' => $usuario->empresa_token,
          ])->get();
        //return response()->json(['status' => 'error','code' => 200,'message' => $usuario->user_token]);
        $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                        FROM last_folders AS fold 
				        JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers 
				        JOIN teci_usuarios_catalogo AS users WHERE fold.egr_servicios = TRUE AND fold.empresa = emp.id 
				        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
				        AND pers.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        if (count($folioSistema) == 1) {
          if ($folioSistema[0]->folio == 1000000000) {
            $post_folio_db = DB::select(
              "SELECT post_folio FROM catalogo_servicios 
				                WHERE id = (SELECT Max(catserv.id) FROM catalogo_servicios AS catserv 
				                JOIN empresas AS emp JOIN empresapersonal AS empper 
				        	    JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users
				        	    WHERE catserv.administrador = emp.id AND emp.empresa_token = ?
				        	    AND emp.id = empper.empresa AND empper.personal = pers.id
				        	    AND pers.usuario = users.id AND users.usuario_token = ?)",
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
          $folio_serv = 'SRV-' . $JwtAuth->generarFolio($folio_nuevo);
        } else {
          $folio_serv = 'SRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
        }

        $folioServ = DB::select("SELECT COUNT(catserv.id) AS folio FROM catalogo_servicios AS catserv 
                        JOIN servicios AS listServ JOIN sos_ps_genero AS gen JOIN empresas AS emp 
                        JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id AND gen.token_genero = ?
                        AND catserv.administrador = emp.id AND emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token= ?", [$parametrosArray['genero'], $usuario->empresa_token, $usuario->user_token]);

        $clasifServ = DB::select("SELECT id FROM clasificacion WHERE token_clascificacion = ?", [$parametrosArray['clasificacion']]);
        //echo $clasifServ[0]->id;

        $genroServ = DB::select("SELECT id,folio_genero,concepto FROM genero WHERE token_genero = ?", [$parametrosArray['genero']]);
        //$genroServ[0]->id;

        $claveSat = DB::select("SELECT id,descripcion FROM teci_catalogo_prodservsat WHERE clave = ?", [$parametrosArray['clave_sat']]);
        //echo " claveSat ".$claveSat[0]->id;

        $unidadMedida = DB::select("SELECT id FROM unidad_medida WHERE token_unidad_medida = ?", [$parametrosArray['unidad_medida']]);
        //echo " claveSat ".$claveSat[0]->id;

        $fechaAlta = $JwtAuth->convierteFechaEpoc($parametrosArray['fechaAlta']);
        //echo $fechaAlta;

        $conceptoServ = $JwtAuth->encriptar(strtolower($parametrosArray['concepto']));

        $tokenServ = $JwtAuth->encriptarToken(
          $parametrosArray['clasificacion'],
          $parametrosArray['clave_sat'],
          $JwtAuth->encriptar($conceptoServ) . $conceptoServ
        );

        if (file_exists($request->file('image'))) {
          $nombre_imagen = $JwtAuth->encriptar($request->file('image')->getClientOriginalName());
        } else {
          $nombre_imagen = $JwtAuth->encriptar('default-servicios.jpg');
        }

        $ubicaServicio = DB::select(
          "SELECT listServ.id FROM servicios AS listServ JOIN catalogo_servicios AS catserv
                        JOIN empresas AS emp JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.servicio = ? AND catserv.administrador = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
          [$conceptoServ, $usuario->empresa_token, $usuario->user_token]
        );

        //$ubicaServicio = 0;
        if (count($ubicaServicio) == 0) {

          $insertServ = DB::table('servicios')
            ->insert(array(
              "token_servicios" => $tokenServ,
              "servicio" => $conceptoServ,
              "clasificacion" => $clasifServ[0]->id,
              "genero" => $genroServ[0]->id,
              "catalogoSAT" => $claveSat[0]->id,
              "medida_sat" => $unidadMedida[0]->id,
              "imagen" => $nombre_imagen,
              "empresa" => $selectEmp[0]->id,
            ));

          if ($insertServ) {
            //echo "insertCorteCaja"; 
            $obtenServ = DB::select("SELECT id FROM servicios WHERE token_servicios = ?", [$tokenServ]);
            //echo $obtenServ[0]->id;
            $fechaSistema = time();

            $tokenCatServ = $JwtAuth->encriptarToken(
              time(),
              $parametrosArray['clasificacion'],
              $parametrosArray['clave_sat'],
              $conceptoServ
            );

            $newServ = new ServiciosModelo();
            $newServ->token_cat_servicios = $tokenCatServ;
            $newServ->fecha_sistema = $fechaSistema;
            $newServ->folio_sistema = $folio_nuevo;
            $newServ->post_folio = $post_folio;
            $newServ->fechaAlta = $fechaAlta;
            $newServ->servicio = $obtenServ[0]->id;
            $newServ->folio = $folioServ[0]->folio + 1;
            $newServ->proceso = TRUE;
            $newServ->moneda = NULL;
            $newServ->tipo_cambio = NULL;
            $newServ->cantidad_sim = NULL;
            $newServ->precioBase = NULL;
            $newServ->cantidad = NULL;
            $newServ->periodicidad = NULL;
            $newServ->repeticion_periodo = NULL;
            $newServ->tipo_periodo = NULL;
            $newServ->fecha_finPeriodo = NULL;
            $newServ->tipo_variabilidad = NULL;
            $newServ->importe_minimo = NULL;
            $newServ->importe_maximo = NULL;
            $newServ->utilizado = FALSE;
            $newServ->fecha_delete_serv = '';
            $newServ->status = TRUE;
            $newServ->administrador = $selectEmp[0]->id;
            $savednewServ = $newServ->save();

            if ($savednewServ) {

              $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?", [$tokenCatServ]);
              $servprovclaves = $parametrosArray['proveedor'];

              if (count($servprovclaves) > 0) {
                for ($i = 0; $i < count($servprovclaves); $i++) {
                  $proveedorToken = $servprovclaves[$i]['token_cat_proveedores'];
                  $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$proveedorToken]);

                  if ($servprovclaves[$i]['tiene_clave'] != '') {

                    if ($servprovclaves[$i]['tiene_clave'] == 'true') {
                      $tiene_clave = TRUE;
                      $asigned_clave = $JwtAuth->encriptar($servprovclaves[$i]['clave']);
                      $txtClave = $asigned_clave;
                    } else {
                      $tiene_clave = FALSE;
                      $asigned_clave = NULL;
                      $txtClave = 'noi hay clave';
                    }
                    $tokenClavesServ = $JwtAuth->encriptarToken(time(), $servprovclaves[$i]['tiene_clave'], $txtClave);
                    $insertProd = DB::table('serv_claves')
                      ->insert(array(
                        "token_serv_claves" => $tokenClavesServ,
                        "servicio_id" => $obtenServicio[0]->id,
                        "proveedor" => $obtenProv[0]->id,
                        "cliente" => NULL,
                        "tiene_clave" => $tiene_clave,
                        "asigned_clave" => $asigned_clave,
                        "periodicidad_c_v" => NULL,
                        "notificacion_c_v" => NULL,
                        "inicio_periodo" => NULL,
                        "fin_periodo" => NULL,
                        "status_c_v" => FALSE
                      ));
                  }
                }
              }

              $filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/servicios/" . $fechaSistema . "-" . $folio_serv . "/";
              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }

              if (file_exists($request->file('image'))) {
                $nombre_imagen = $JwtAuth->encriptar($request->file('image')->getClientOriginalName());
                Storage::putFileAs("/public/root/" . $filepath, $request->file('image'), $nombre_imagen);
              }

              QRCode::text($tokenCatServ)->setOutfile(Storage::path('public/root/' . $filepath . $fechaSistema . "-" . $folio_serv . '-QRCode.png'))
                ->png();

              $qrGenerado = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . $fechaSistema . "-" . $folio_serv . '-QRCode.png'));

              if (file_exists($request->file('image'))) {
                $nombre_imagen = $JwtAuth->encriptar($request->file('image')->getClientOriginalName());
                $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $nombre_imagen));
              } else {
                $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/default-servicios.jpg'));
              }

              $areaCss = 'information-cpp';
              $areaPdf = 'Egresos y cuentas por pagar';
              $Subarea = 'Catalogos de egresos';
              $nameDoc = 'evidencia de registro de servicios';

              $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/homePagePrincipal/sos-mexico.png'));
              if ($selectEmp[0]->denominacion_rs == '') {
                $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->paterno) . " " .
                  $JwtAuth->desencriptar($selectEmp[0]->materno) . " " .
                  $JwtAuth->desencriptar($selectEmp[0]->nombre);
              } else {
                $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->denominacion_rs);
              }
              if ($selectEmp[0]->sitio_web == '' || $selectEmp[0]->sitio_web == '-') {
                $sitio_web = '---';
              } else {
                $sitio_web = $JwtAuth->desencriptar($selectEmp[0]->sitio_web);
              }
              $direccion = '';

              $fecha_pdf = $JwtAuth->convierteEpocFecha($selectEmp[0]->zona_horaria, $fechaSistema);
              $datePdf = gmdate('Y-m-d H:i:s', $fechaAlta);

              $contenidoPdf = '<div class="divLogo"><img src="' . $qrGenerado . '" alt=""></div>
                                    <div class="divLogo"><img class="logotipo" src="' . $logo_serv . '" alt=""></div>
                                    <h3>' . $parametrosArray['concepto'] . '</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>fecha de alta registrada</th>
                                                <th>clasificación</th>
                                                <th>catalogo de sat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>' . $parametrosArray['fechaAlta'] . '</td>
                                            <td>' . $JwtAuth->generar('6') . "-" .
                $JwtAuth->generar($genroServ[0]->folio_genero) . "-" .
                $JwtAuth->generar($folioServ[0]->folio + 1) . ' (' . $genroServ[0]->concepto . ')</td>
                                            <td>' . $parametrosArray['clave_sat'] . ' (' . $claveSat[0]->descripcion . ')</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <br>
                                    <h3>Cuentas bancarias vinculadas</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Proveedor asignado</th>
                                                <th>clave de servicio</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
              if (count($servprovclaves) > 0) {
                for ($i = 0; $i < count($servprovclaves); $i++) {
                  $proveedorToken = $servprovclaves[$i]['token_cat_proveedores'];
                  $obtenProv = DB::select("SELECT people.paterno,people.materno,people.nombre,
                                                        people.denominacion_rs FROM catalogo_proveedores AS catprov 
                                                        JOIN personas AS people WHERE people.id = catprov.proveedor 
                                                        AND catprov.token_cat_proveedores = ?", [$proveedorToken]);
                  if ($obtenProv[0]->denominacion_rs == '') {
                    $nombreProv = $JwtAuth->desencriptar($obtenProv[0]->paterno) . " " .
                      $JwtAuth->desencriptar($obtenProv[0]->materno) . " " .
                      $JwtAuth->desencriptar($obtenProv[0]->nombre);
                  } else {
                    $nombreProv = $JwtAuth->desencriptar($obtenProv[0]->denominacion_rs);
                  }
                  $contenidoPdf .= '<tr>
                                                        <td>' . $nombreProv . '</td>
                                                        <td>' . $servprovclaves[$i]['clave'] . '</td>
                                                    </tr>';
                }
              } else {
                $contenidoPdf .= '<tr><td colspan="2">¡NO HAY REGISTROS!</td></tr>';
              }
              $contenidoPdf .= '</tbody>
                                    </table>
                                    <h3>registrado por</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Area</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>' . $JwtAuth->desencriptar($infoUser[0]->paterno) . " " . $JwtAuth->desencriptar($infoUser[0]->materno) . " " . $JwtAuth->desencriptar($infoUser[0]->nombre) . '</td>
                                                <td>' . $JwtAuth->desencriptar($infoUser[0]->areaemp) . '</td>
                                            </tr>
                                        </tbody>
                                    </table>';

              $pdfGenerado = $JwtAuth->generaPdf(
                $areaCss,
                $areaPdf,
                $Subarea,
                $nameDoc,
                $logoEmp,
                $nameEmp,
                $sitio_web,
                $direccion,
                $fecha_pdf,
                $contenidoPdf
              );

              $dompdf = \PDF::loadHtml($pdfGenerado);
              $dompdf->setPaper("A2", "portrait");
              $contenidoPDF = $dompdf->output();

              file_put_contents(storage_path("app/public/root/" . $filepath) . $fechaSistema . "-" .
                $folio_serv . ".pdf", $contenidoPDF);

              $dompdf = \PDF::loadHtml($pdfGenerado);
              $dompdf->setPaper("A2", "portrait");
              $contenidoPDF = $dompdf->output();

              $JwtAuth->insertBitacoraActividad(
                'egresos',
                'catalogos',
                'servicios',
                $folio_serv,
                'registro en el catalogo de servicios',
                $usuario->empresa_token,
                $usuario->user_token
              );

              if (count($folioSistema) == 0) {
                $insertSistema = DB::table('last_folders')
                  ->insert(
                    array(
                      "egr_servicios" => TRUE,
                      "folder" => 1,
                      "post_folder" => $post_folio,
                      "empresa" => $selectEmp[0]->id,
                    )
                  );
              } else {
                $regFolder = DB::table('last_folders')->join("main_empresas AS emp", "last_folders.empresa", "=", "emp.id")
                  ->join("empresapersonal AS empuser", "emp.id", "empuser.empresa")
                  ->join("vhum_personal AS pers", "empuser.personal", "pers.id")
                  ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
                  ->where([
                    'last_folders.egr_servicios' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                  ])
                  ->limit(1)->update(
                    array(
                      'last_folders.folder' => $folio_nuevo,
                      'last_folders.post_folder' => $post_folio,
                    )
                  );
              }

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Este servicio ha sido registrado satisfactoriamente con el folio ' . $folio_serv
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Registro de servicio incompleto, intente nuevamente o comuniquese a soporte'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Registro de servicio incompleto, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Este servicio ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos solicitados para este registro son incorrectos o invalidos, revise su información o comuniquese a soporte'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
