<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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

class INVENT_ServiciosVentasController extends Controller
{
  public function deleteServicioEgresos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'servdata' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $obtenCompraServ = DB::select("SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_servicios AS catserv 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE detcomp.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                    AND users.usuario_token = ?", [$parametrosArray['servdata'], $usuario->empresa_token, $usuario->user_token]);

        if (count($obtenCompraServ) == 0) {
          $servDeleteList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'in_egr_catalogo_servicios.token_cat_servicios' => $parametrosArray['servdata'],
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(array('in_egr_catalogo_servicios.fecha_delete_serv' => time(), 'in_egr_catalogo_servicios.status' => FALSE));

          if ($servDeleteList) {
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
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos solicitados para este registro son incorrectos o invalidos, revise su información o comuniquese a soporte'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restartServicioEgresos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'servdata' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $servDeleteList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.token_cat_servicios' => $parametrosArray['servdata'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(array('in_egr_catalogo_servicios.status' => TRUE, 'in_egr_catalogo_servicios.fecha_delete_serv' => NULL));

        if ($servDeleteList) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'servicio restaurado satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'servicio no restaurado'
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

  //ventas de mostrador
  public function servToVentasMostradorRegistro(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'concepto' => 'required|string',
        'precio' => 'required|numeric',
        'unidad_medida' => 'string',
        'moneda_codigo' => 'required|string',
        'impuestos' => 'array',
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
        $fecha_sistema = time();
        $concepto = $parametrosArray["concepto"];
        $precio = $parametrosArray["precio"];
        $unidad_medida = $parametrosArray["unidad_medida"];
        $moneda_codigo = $parametrosArray["moneda_codigo"];
        $impuestos = $parametrosArray["impuestos"];

        if (
          isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $concepto) &&
          isset($precio) && !empty($precio) && preg_match($JwtAuth->filtroNumericoSimple(), $precio) &&
          isset($unidad_medida) && !empty($unidad_medida) && preg_match($JwtAuth->filtroAlfaNumerico(), $unidad_medida) &&
          isset($moneda_codigo) && !empty($moneda_codigo) && preg_match($JwtAuth->filtroAlfaNumerico(), $moneda_codigo)
        ) {
          //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
          $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,people.materno,people.nombre,
                        people.denominacion_rs,people.sitio_web FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
          //echo $selectEmp[0]->id;

          $folioSistemaTemp = DB::select("SELECT temps_folio FROM in_egr_catalogo_servicios WHERE temps_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
          if (count($folioSistemaTemp) > 0) {
            $queryFolioTmpSrv = DB::select("SELECT temps_folio+1 AS temps_folio FROM in_egr_catalogo_servicios 
                            WHERE id = (SELECT Max(catserv.id) FROM in_egr_catalogo_servicios AS catserv 
                            JOIN main_empresas AS emp WHERE temps_folio IS NOT NULL AND catserv.administrador = emp.id 
                            AND emp.empresa_token = ?)", [$usuario->empresa_token]);

            foreach ($queryFolioTmpSrv as $vTemp) {
              $folio_temporal = $vTemp->temps_folio;
            }
          } else {
            $folio_temporal = 1;
          }

          $folio_serv_temp = 'SERV-TEMP-' . $JwtAuth->generarFolio($folio_temporal);

          $conceptoServ = $JwtAuth->encriptar(strtolower($concepto));
          $ubicaServicio = DB::select(
            "SELECT catserv.id FROM in_egr_catalogo_servicios AS catserv
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                        WHERE catserv.servicio = ? AND catserv.administrador = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$conceptoServ, $usuario->empresa_token, $usuario->user_token]
          );
          if (count($ubicaServicio) == 0) {
            $tokencatserv = $JwtAuth->encriptarToken($conceptoServ . $precio . $moneda_codigo . $unidad_medida);
            $newServ = new ServiciosModelo();
            $newServ->fecha_registro_serv = $fecha_sistema;
            $newServ->token_cat_servicios = $tokencatserv;
            $newServ->temps_folio = $folio_temporal;
            $newServ->authorized = FALSE;
            $newServ->modulo_mostrador = TRUE;
            $newServ->servicio = $conceptoServ;
            $newServ->precioBase = $precio;
            $newServ->moneda_clave = $moneda_codigo;
            $newServ->unidad_medida_clave = $unidad_medida;
            $newServ->proceso = 'v';
            $newServ->utilizado = FALSE;
            $newServ->status = TRUE;
            $newServ->administrador = $selectEmp[0]->id;
            $newServ->admin_user_registra = $selectEmp[0]->userr;
            $savednewServ = $newServ->save();

            if ($savednewServ) {
              $JwtAuth->insertBitacoraActividad('egresos', 'catalogos', 'servicios', $folio_serv_temp, 'registro en el catalogo de servicios', $usuario->empresa_token, $usuario->user_token);

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Este servicio ha sido registrado satisfactoriamente con el folio ' . $folio_serv_temp
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'La información de este servicio no es valida'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Este servicio ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $error_alerta = "";
          if (!isset($concepto) || empty($concepto) || !preg_match($JwtAuth->filtroAlfaNumerico(), $concepto)) {
            $error_alerta = "Error al ingresar concepto del servicio, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($precio) || empty($precio) || !preg_match($JwtAuth->filtroNumericoSimple(), $precio)) {
            $error_alerta = "Error al ingresar precio de servicio, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($unidad_medida) || empty($unidad_medida) || !preg_match($JwtAuth->filtroAlfaNumerico(), $unidad_medida)) {
            $error_alerta = "Error al ingresar unidad de medida, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($moneda_codigo) || empty($moneda_codigo) || !preg_match($JwtAuth->filtroAlfaNumerico(), $moneda_codigo)) {
            $error_alerta = "Error al ingresar moneda, verifique su información o comuniquese a soporte para más información";
          }
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => $error_alerta
          );
        }
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

  public function servToVentasMostradorCatalogo(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.status' => TRUE,
            'in_egr_catalogo_servicios.proceso' => "v",
            'in_egr_catalogo_servicios.modulo_mostrador' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $rowEach = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "modulo_destino" => $value->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
            "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
          );
          $serviciosLista[] = $rowEach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosServicio' => $serviciosLista,
          'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'servicios', $usuario->empresa_token, $usuario->user_token),
        );
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

  public function servToVentasMostradorPerfil(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_servicios' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_servicios = $parametrosArray["token_cat_servicios"];
        $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.token_cat_servicios' => $token_cat_servicios,
            'in_egr_catalogo_servicios.status' => TRUE,
            'in_egr_catalogo_servicios.proceso' => "v",
            'in_egr_catalogo_servicios.modulo_mostrador' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $rowEach = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "fechaAlta" => gmdate('Y-m-d H:i:s', $value->fecha_registro_serv),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "precioBase" => $value->precioBase,
            //"moneda_clave_name" => $moneda_clave_name,
            "moneda_clave_code" => $value->moneda_clave,
            "unidad_medida_clave" => $value->unidad_medida_clave,
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
          );
          $serviciosLista[] = $rowEach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosServicio' => $serviciosLista,
        );
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

  public function servToVentasMostradorUpdate(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_servicios' => 'required|string',
        'concepto' => 'required|string',
        'precio' => 'required|numeric',
        'unidad_medida' => 'string',
        'moneda_codigo' => 'required|string',
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
        $token_cat_servicios = $parametrosArray["token_cat_servicios"];
        $concepto = $parametrosArray["concepto"];
        $precio = $parametrosArray["precio"];
        $unidad_medida = $parametrosArray["unidad_medida"];
        $moneda_codigo = $parametrosArray["moneda_codigo"];
        if (
          isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $concepto) &&
          isset($precio) && !empty($precio) && preg_match($JwtAuth->filtroNumericoSimple(), $precio) &&
          isset($unidad_medida) && !empty($unidad_medida) && preg_match($JwtAuth->filtroAlfaNumerico(), $unidad_medida) &&
          isset($moneda_codigo) && !empty($moneda_codigo) && preg_match($JwtAuth->filtroAlfaNumerico(), $moneda_codigo)
        ) {
          //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
          $conceptoServ = $JwtAuth->encriptar(strtolower($concepto));

          $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'in_egr_catalogo_servicios.token_cat_servicios' => $token_cat_servicios,
              'in_egr_catalogo_servicios.status' => TRUE,
              'in_egr_catalogo_servicios.proceso' => "v",
              'in_egr_catalogo_servicios.modulo_mostrador' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

          foreach ($servList as $value) {
            $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
              'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

            $servUpdate = ServiciosModelo::find(1);
            $servUpdate->where("token_cat_servicios", $value->token_cat_servicios)
              ->update([
                "servicio" => $conceptoServ,
                "precioBase" => $precio,
                "moneda_clave" => $moneda_codigo,
                "unidad_medida_clave" => $unidad_medida
              ]);

            if ($servUpdate) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Este servicio con el folio ' . $folio_serv . ' ha sido actualizado satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'La información de este servicio no es valida'
              );
            }
          }
        } else {
          $error_alerta = "";
          if (!isset($concepto) || empty($concepto) || !preg_match($JwtAuth->filtroAlfaNumerico(), $concepto)) {
            $error_alerta = "Error al ingresar concepto del servicio, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($precio) || empty($precio) || !preg_match($JwtAuth->filtroNumericoSimple(), $precio)) {
            $error_alerta = "Error al ingresar precio de servicio, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($unidad_medida) || empty($unidad_medida) || !preg_match($JwtAuth->filtroAlfaNumerico(), $unidad_medida)) {
            $error_alerta = "Error al ingresar unidad de medida, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($moneda_codigo) || empty($moneda_codigo) || !preg_match($JwtAuth->filtroAlfaNumerico(), $moneda_codigo)) {
            $error_alerta = "Error al ingresar moneda, verifique su información o comuniquese a soporte para más información";
          }
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => $error_alerta
          );
        }
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

  public function servToVentasMostradorDelete(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_servicios' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_servicios = $parametrosArray["token_cat_servicios"];
        $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.token_cat_servicios' => $token_cat_servicios,
            'in_egr_catalogo_servicios.status' => FALSE,
            'in_egr_catalogo_servicios.proceso' => "v",
            'in_egr_catalogo_servicios.modulo_mostrador' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $servUpdate = ServiciosModelo::find(1);
          $servUpdate->where("token_cat_servicios", $value->token_cat_servicios)->limit(1)->delete();

          if ($servUpdate) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este servicio con el folio ' . $folio_serv . ' ha sido eliminado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 404,
              'message' => 'La información de este servicio no es valida'
            );
          }
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

  public function catalogoServiciosNotAutorizados(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaServiciosTrue = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        $servList = DB::table("in_egr_catalogo_servicios AS catserv")
          ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catserv.status' => TRUE,
            'catserv.authorized' => FALSE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        foreach ($servList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          QRCode::text($value->token_cat_servicios)->setOutfile(Storage::path('public/root/' . $value->fecha_registro_serv . 'QRCode.png'))->png();

          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $servGenero = DB::table("in_egr_catalogo_servicios AS catserv")
            ->join("sos_ps_genero AS gen", "catserv.genero", "=", "gen.id")
            ->where(['catserv.token_cat_servicios' => $value->token_cat_servicios])->get();
          $genero_serv = $value->modulo_mostrador == FALSE && count($servGenero) == 1 ? $JwtAuth->generar($servGenero[0]->folio_genero) : "---";

          $soliValidate = DB::table("in_egr_catalogo_servicios AS catserv")
            ->join("in_egr_catalogo_servicios_soli_auth AS soli_auth", "catserv.id", "=", "soli_auth.servicio")
            ->where(["soli_auth.soli_aprobada" => FALSE, "catserv.token_cat_servicios" => $value->token_cat_servicios])->get();

          $arrayForeachVig = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . "-" . $genero_serv . "-" . $JwtAuth->generar($value->folio_sistema),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "sat_clave_code" => $value->sat_clave_code != NULL && $value->sat_clave_code != "" ? $value->sat_clave_code : "N/A",
            "sat_homologado" => $value->sat_homologado != NULL && $value->sat_homologado != "" ? $value->sat_homologado : "N/A",
            "unidad_medida_clave" => $value->unidad_medida_clave != "" ? $value->unidad_medida_clave : "---",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "modulo_destino" => $value->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
            "solicitudes" => count($soliValidate),
          );
          $listaServiciosTrue[] = $arrayForeachVig;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'listado' => $listaServiciosTrue,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function requestValidacionServ(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProveedores = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_servicios" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_servicios = $parametrosArray["token_cat_servicios"];
        $observaciones = "permiso de prueba";

        $queryServicio = DB::table("in_egr_catalogo_servicios AS catserv")
          ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "catserv.modulo_mostrador" => TRUE,
            "catserv.token_cat_servicios" => $token_cat_servicios,
            "catserv.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        if (count($queryServicio) == 1) {
          foreach ($queryServicio as $vServ) {
            //da_te_default_timezone_set($vServ->zona_horaria);
            $folio_serv = 'SERV-TEMP-' . $JwtAuth->generarFolio($vServ->temps_folio);
            $nombre_serv = strtolower($JwtAuth->desencriptar($vServ->servicio));

            $select_id_serv = DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $vServ->token_cat_servicios)->value('id');

            $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

            $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

            $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
            $folioSistema = DB::select("SELECT max(soli_auth.folio_servicios_soli_auth) AS folio_permiso FROM in_egr_catalogo_servicios_soli_auth AS soli_auth 
                            JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

            $sql_folio = count($folioSistema) == 0 ? 1 : end($folioSistema)->folio_permiso + 1;

            $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $observaciones . time() - 500);
            $insertSoliPerm = DB::table("in_egr_catalogo_servicios_soli_auth")
              ->insert(
                array(
                  "token_servicios_soli_auth" => $token_auth,
                  "folio_servicios_soli_auth" => $sql_folio,
                  "fecha_servicios_soli_auth" => time(),
                  "user_emp" => end($select_empresa)->id,
                  "user_user" => end($select_usuario)->id,
                  "servicio" => $select_id_serv,
                  "observaciones" => $JwtAuth->encriptar($observaciones),
                  "receptor" => 3,
                  "solicitud_serv_status" => TRUE,
                )
              );

            if ($insertSoliPerm) {
              $userAdmin = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY";
              $titulo_ = "Validación de proveedor";
              $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado validación para el servicio con el folio " . $folio_serv . " " . $nombre_serv;
              $JwtAuth->notificacionPushDevices($userAdmin, $titulo_, $mensaje_user);

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio),
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'el proveedor buscado no existe'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validacionProcesoServicio(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProveedores = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_servicios" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_cat_servicios = $parametrosArray["token_cat_servicios"];
        $observaciones = "permiso de prueba";

        $queryServicio = DB::table("in_egr_catalogo_servicios AS catserv")
          ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "catserv.modulo_mostrador" => TRUE,
            "catserv.token_cat_servicios" => $token_cat_servicios,
            "catserv.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        if (count($queryServicio) == 1) {
          foreach ($queryServicio as $vServ) {
            //da_te_default_timezone_set($vServ->zona_horaria);

            $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

            $select_usuario = DB::select("SELECT pers.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

            $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);

            $nombre_serv = strtolower($JwtAuth->desencriptar($vServ->servicio));

            $folio_serv_temp = 'SERV-TEMP-' . $JwtAuth->generarFolio($vServ->temps_folio);

            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                            FROM sos_last_folders AS fold JOIN main_empresas AS emp
                            WHERE fold.egr_servicios = TRUE AND fold.empresa = emp.id 
                            AND emp.empresa_token = ?", [$usuario->empresa_token]);

            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT post_folio FROM in_egr_catalogo_servicios 
                                    WHERE id = (SELECT Max(catserv.id) FROM in_egr_catalogo_servicios AS catserv 
                                    JOIN main_empresas AS emp WHERE catserv.administrador = emp.id 
                                    AND emp.empresa_token = ?)", [$usuario->empresa_token]);

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

            $folio_serv = 'SERV-' . $JwtAuth->generarFolio($folio_nuevo) . ($post_folio != NULL ? '-' . $post_folio : '');
            //echo $folio_serv;exit;

            $updateServValid = DB::table("in_egr_catalogo_servicios")
              ->where(["token_cat_servicios" => $vServ->token_cat_servicios])
              ->limit(1)->update(
                array(
                  "folio_sistema" => $folio_nuevo,
                  "post_folio" => $post_folio,
                  "authorized" => TRUE,
                  "authorized_fecha" => time(),
                  "authorized_by" => end($select_usuario)->id,
                )
              );

            if ($updateServValid) {
              $soliValidate = DB::table("in_egr_catalogo_servicios AS catserv")
                ->join("in_egr_catalogo_servicios_soli_auth AS soli_auth", "catserv.id", "=", "soli_auth.servicio")
                ->join("teci_usuarios_catalogo AS users", "soli_auth.user_user", "=", "users.id")
                ->where(["soli_auth.soli_aprobada" => FALSE, "catserv.token_cat_servicios" => $vServ->token_cat_servicios])->get();

              if (count($soliValidate) > 0) {
                $titulo_ = "Validación de servicios";
                $mensaje_user = "El servicio $nombre_serv con folio temporal $folio_serv_temp ha sido validado con el folio " . $folio_serv;
                foreach ($soliValidate as $mSoli) {
                  $soliValidAprob = DB::table("in_egr_catalogo_servicios_soli_auth")
                    ->where(["token_servicios_soli_auth" => $mSoli->token_servicios_soli_auth])
                    ->limit(1)->update(array("soli_aprobada" => TRUE));

                  $JwtAuth->notificacionPushDevices($mSoli->usuario_token, $titulo_, $mensaje_user);
                }
              }

              if (count($folioSistema) == 0) {
                $insertSistema = DB::table("sos_last_folders")
                  ->insert(array("egr_servicios" => TRUE, "folder" => 1, "post_folder" => $post_folio, "empresa" => $select_empresa[0]->id));
              } else {
                $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                  ->where(["lastf.egr_servicios" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
                  ->limit(1)->update(array("lastf.folder" => $folio_nuevo, "lastf.post_folder" => $post_folio));
              }

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Servicio validado con el folio " . $folio_serv,
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Validación de proveedor no registrada, intentelo nuevamente o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'El servicio buscado no existe');
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //ventas catalogo general
  public function servToVentasGeneralRegistro(Request $request)
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
                    JOIN personas AS people JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
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
        $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers
                    JOIN teci_usuarios_catalogo AS users WHERE fold.egr_servicios = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

        if (count($folioSistema) == 1) {
          if ($folioSistema[0]->folio == 1000000000) {
            $post_folio_db = DB::select("SELECT post_folio FROM catalogo_servicios WHERE id = (SELECT Max(catserv.id) FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
			                JOIN empresapersonal AS empper JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE catserv.administrador = emp.id AND emp.empresa_token = ?
			        	    AND emp.id = empper.empresa AND empper.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?)", [$usuario->empresa_token, $usuario->user_token]);

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

        $folio_serv = 'SRV-' . ($post_folio == NULL ? $JwtAuth->generarFolio($folio_nuevo) : $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio);

        $folioServ = DB::select(
          "SELECT COUNT(catserv.id) AS folio FROM in_egr_catalogo_servicios AS catserv JOIN servicios AS listServ JOIN sos_ps_genero AS gen JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id AND gen.token_genero = ? 
                    AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token= ?",
          [$parametrosArray['genero'], $usuario->empresa_token, $usuario->user_token]
        );

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
          "SELECT listServ.id FROM servicios AS listServ JOIN in_egr_catalogo_servicios AS catserv
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
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

            $tokenCatServ = $JwtAuth->encriptarToken(time(), $parametrosArray['clasificacion'], $parametrosArray['clave_sat'], $conceptoServ);
            $newServ = new ServiciosModelo();
            $newServ->token_cat_servicios = $tokenCatServ;
            $newServ->fecha_registro_serv = $fechaSistema;
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
                  ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                  ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
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

  public function servToVentasGeneralCatalogo(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.status' => TRUE,
            'in_egr_catalogo_servicios.proceso' => "v",
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $rowEach = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "modulo_mostrador" => $value->modulo_mostrador == TRUE ? true : false,
            "catalogo_sat" => $value->sat_homologado != NULL && $value->sat_homologado != "" ? $value->sat_homologado : "N/A",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
            "modulo_destino" => $value->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
            "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
          );
          $serviciosLista[] = $rowEach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosServicio' => $serviciosLista,
          'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'servicios', $usuario->empresa_token, $usuario->user_token),
        );
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

  public function servToVentasGeneralDeletedCatalogo(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $servList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.status' => FALSE,
            'in_egr_catalogo_servicios.proceso' => "v",
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $rowEach = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "fecha_delete" => gmdate('Y-m-d H:i:s', $value->fecha_delete_serv),
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "modulo_mostrador" => $value->modulo_mostrador == TRUE ? true : false,
            "catalogo_sat" => $value->sat_homologado != NULL && $value->sat_homologado != "" ? $value->sat_homologado : "N/A",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
          );
          $serviciosLista[] = $rowEach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosServicio' => $serviciosLista,
          'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'servicios', $usuario->empresa_token, $usuario->user_token),
        );
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
