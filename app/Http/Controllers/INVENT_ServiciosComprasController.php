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

class INVENT_ServiciosComprasController extends Controller{
  public function servToComprasGeneralRegistro(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'concepto' => 'required|string',
        'clasificacion' => 'required|required',
        'genero' => 'required|string',
        'clave_sat' => 'numeric',
        'cuenta_contable' => 'string',
        'unidad_medida_clave' => 'string',
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
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $concepto = $parametrosArray["concepto"];
        $clasificacion = $parametrosArray["clasificacion"];
        $genero = $parametrosArray["genero"];
        $clave_sat = $parametrosArray["clave_sat"];
        $cuenta_contable = $parametrosArray["cuenta_contable"];
        $validacion_cuenta_contable = isset($cuenta_contable) && !empty($cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(), $cuenta_contable);
        $unidad_medida_clave = $parametrosArray["unidad_medida_clave"];
        $proveedor = $parametrosArray["proveedor"];

        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,people.materno,people.nombre,
                    people.denominacion_rs,people.sitio_web FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser 
                    JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

        foreach ($selectEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          $folioSistema = DB::select(
            "SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE fold.egr_servicios = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$usuario->empresa_token, $usuario->user_token]
          );

          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
              $post_folio_db = DB::select("SELECT post_folio FROM catalogo_servicios WHERE id = (SELECT Max(catserv.id) FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                            JOIN empresapersonal AS empper JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users WHERE catserv.administrador = emp.id AND emp.empresa_token = ?
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

          $folio_serv = 'SERV-' . ($post_folio == NULL ? $JwtAuth->generarFolio($folio_nuevo) : $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio);

          $folioServ = DB::select(
            "SELECT COUNT(catserv.id) AS folio FROM in_egr_catalogo_servicios AS catserv JOIN sos_ps_genero AS gen JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catserv.genero = gen.id AND gen.token_genero = ? 
                    AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
            [$parametrosArray['genero'], $usuario->empresa_token, $usuario->user_token]
          );

          $clasifServ = $clasificacion != "" ? DB::table("sos_ps_clasificacion")->where("token_clasificacion", $clasificacion)->value("id") : NULL;
          $generoServ = $genero != "" ? DB::table("sos_ps_genero")->where("token_genero", $genero)->value("id") : NULL;
          //$claveSat = $clave_sat != "" ? DB::table("teci_catalogo_prodservsat")->where("clave",$clave_sat)->value("id") : NULL;
          $unidadMedidaClv = $unidad_medida_clave != "" ? $unidad_medida_clave : NULL;
          $conceptoServ = $JwtAuth->encriptar(strtolower($concepto));

          $ubicaServicio = DB::select(
            "SELECT catserv.id FROM in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE catserv.servicio = ? AND catserv.proceso = 'c' AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$conceptoServ, $usuario->empresa_token, $usuario->user_token]
          );

          if (count($ubicaServicio) == 0) {
            $fechaSistema = time();
            $tokenCatServ = $JwtAuth->encriptarToken($clasifServ, $generoServ, $conceptoServ);
            $newServ = new ServiciosModelo();
            $newServ->token_cat_servicios = $tokenCatServ;
            $newServ->fecha_registro_serv = $fechaSistema;
            $newServ->folio_sistema = $folio_nuevo;
            $newServ->post_folio = $post_folio;
            $newServ->servicio = $conceptoServ;
            $newServ->authorized = TRUE;
            $newServ->authorized_fecha = $fechaSistema;
            $newServ->clasificacion = $clasifServ;
            $newServ->genero = $generoServ;
            $newServ->unidad_medida_clave = $unidadMedidaClv != "" ? $unidadMedidaClv : NULL;
            $newServ->sat_clave_code = $clave_sat != "" ? $clave_sat : NULL;
            //$newServ->folio = $folioServ[0]->folio+1;
            $newServ->proceso = 'c';
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
            $newServ->cuenta_contable = $validacion_cuenta_contable ? $JwtAuth->encriptar($cuenta_contable) : NULL;
            $newServ->utilizado = FALSE;
            $newServ->fecha_delete_serv = '';
            $newServ->status = TRUE;
            $newServ->administrador = $vEmp->id;
            $newServ->admin_user_registra = $vEmp->userr;
            $savednewServ = $newServ->save();
            if ($savednewServ) {
              $obtenServicio = $newServ->id;
              if (count($proveedor) > 0) {
                for ($i = 0; $i < count($proveedor); $i++) {
                  $proveedorToken = $proveedor[$i]['token_cat_proveedores'];
                  $obtenProv = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $proveedorToken)->value("id");
                  if ($proveedor[$i]['tiene_clave'] != '') {
                    $tiene_clave = $proveedor[$i]['tiene_clave'] == 'true' ? TRUE : FALSE;
                    $asigned_clave = $proveedor[$i]['tiene_clave'] == 'true' ? $JwtAuth->encriptar($proveedor[$i]['clave']) : NULL;
                    $txtClave = $proveedor[$i]['tiene_clave'] == 'true' ? $asigned_clave : 'noi hay clave';
                    $tokenClavesServ = $JwtAuth->encriptarToken(time(), $proveedor[$i]['tiene_clave'], $txtClave);
                    $insertProd = DB::table('in_egr_catalogo_servicios_claves')
                      ->insert(array(
                        "token_serv_claves" => $tokenClavesServ,
                        "servicio_id" => $obtenServicio,
                        "proveedor" => $obtenProv,
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

              $filepath = $vEmp->root_tkn . "/0002-cpp/catalogos/servicios/" . $fechaSistema . "-" . $folio_serv . "/";
              QRCode::text($tokenCatServ)->setOutfile(Storage::path('public/root/' . $filepath . $fechaSistema . "-" . $folio_serv . '-QRCode.png'))
                ->png();

              $JwtAuth->insertBitacoraActividad('egresos', 'catalogos', 'servicios', $folio_serv, 'registro en el catalogo de servicios', $usuario->empresa_token, $usuario->user_token);

              if (count($folioSistema) == 0) {
                $insertSistema = DB::table('sos_last_folders')
                  ->insert(
                    array(
                      "egr_servicios" => TRUE,
                      "folder" => 1,
                      "post_folder" => $post_folio,
                      "empresa" => $vEmp->id,
                    )
                  );
              } else {
                $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                  ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                  ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                  ->where(['sos_last_folders.egr_servicios' => TRUE, 'emp.empresa_token' => $usuario->empresa_token, 'users.usuario_token' => $usuario->user_token])
                  ->limit(1)->update(
                    array(
                      'sos_last_folders.folder' => $folio_nuevo,
                      'sos_last_folders.post_folder' => $post_folio,
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
              'message' => 'Este servicio ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
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

  public function serviciosCatalogoGeneral(Request $request){
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
        
        $servListActFijo = ServiciosModelo::where('status',TRUE)
        ->where("folio_sistema","999999998")
        ->whereNull("administrador")
        ->select([
          DB::raw("FALSE AS puede_eliminar"),
          "in_egr_catalogo_servicios.*",
          DB::raw("1 AS modo_registro")
        ]);

        $servListActDiferido = ServiciosModelo::where('status',TRUE)
        ->where("folio_sistema","999999999")
        ->whereNull("administrador")
        ->select([
          DB::raw("FALSE AS puede_eliminar"),
          "in_egr_catalogo_servicios.*",
          DB::raw("1 AS modo_registro")
        ]);

        $servListVincEmp = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'in_egr_catalogo_servicios.status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token
        ])
        ->select([
          DB::raw("TRUE AS puede_eliminar"),
          "in_egr_catalogo_servicios.*",
          DB::raw("2 AS modo_registro")
        ]);

        $queryServicios = $servListActDiferido->unionAll($servListActFijo)->unionAll($servListVincEmp)
        ->orderBy("modo_registro", "asc")
        ->get();

        foreach ($queryServicios as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $rowEach = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "proceso" => $value->proceso == "c" ? "compra" : "venta",
            "modulo_mostrador" => $value->modulo_mostrador ? true : false,
            "catalogo_sat" => $value->sat_homologado != NULL && $value->sat_homologado != "" ? $value->sat_homologado : "N/A",
            "cuenta_contable" => !empty($value->cuenta_contable) ? $JwtAuth->desencriptar($value->cuenta_contable) : '',
            "utilizado" => !$value->puede_eliminar || $value->utilizado ? true : false,
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

  public function servToComprasGeneralCatalogo(Request $request){
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
            'in_egr_catalogo_servicios.proceso' => "c",
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

  public function servToComprasGeneralPerfil(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'servdata' => 'required'
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
        $servicio = $parametrosArray["servdata"];
        //echo $servicio;
        $servList = ServiciosModelo::join("sos_ps_clasificacion AS classif", "in_egr_catalogo_servicios.clasificacion", "=", "classif.id")
          ->join("sos_ps_genero AS gen", "in_egr_catalogo_servicios.genero", "=", "gen.id")
          //->join("teci_catalogo_prodservsat AS prsrvsat","ltserv.catalogoSAT","=","prsrvsat.id")
          //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
          ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.token_cat_servicios' => $parametrosArray['servdata'],
            'in_egr_catalogo_servicios.status' => TRUE,
            'in_egr_catalogo_servicios.proceso' => "c",
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $file_pdf = Storage::path('public/root/' . $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $value->fecha_registro_serv . '-' . $JwtAuth->generar($value->folio_sistema) . '/' . $value->fecha_registro_serv . '-' .
            $JwtAuth->generar($value->folio_sistema) . '.pdf');

          $listaClavesProv = array();
          $claveByProv = ServiciosModelo::join("in_egr_catalogo_servicios_claves AS clavserv", "in_egr_catalogo_servicios.id", "=", "clavserv.servicio_id")
            ->join("eegr_catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
            ->where([
              'in_egr_catalogo_servicios.token_cat_servicios' => $value->token_cat_servicios,
              'catprov.status' => true
            ])->get();

          foreach ($claveByProv as $scProv) {
            $scRow = array(
              "encendido" => true,
              "token_cat_proveedores" => $scProv->token_cat_proveedores,
              "rfc_generico" => $scProv->rfc_generico,
              "rfc_prov" => $scProv->rfc != NULL ? $JwtAuth->desencriptar($scProv->rfc) : '---',
              "tax_id_prov" => $scProv->tax_id != NULL ? $JwtAuth->desencriptar($scProv->tax_id) : '---',
              "folio" => 'PRV-' . ($scProv->post_folio == NULL ? $JwtAuth->generarFolio($scProv->folio) : $JwtAuth->generarFolio($scProv->folio) . '-' . $scProv->post_folio),
              "nombre" => $JwtAuth->desencriptar($scProv->nombre_extendido),
              "token_serv_claves" => $scProv->token_serv_claves,
              "tiene_clave" => $scProv->tiene_clave == TRUE ? 'true' : 'false',
              "tiene_clave_respaldo" => $scProv->tiene_clave == TRUE ? 'true' : 'false',
              "asigned_clave" => $scProv->asigned_clave != NULL && $scProv->asigned_clave != '' ? $JwtAuth->desencriptar($scProv->asigned_clave) : '',
              "asigned_clave_respaldo" => $scProv->asigned_clave != NULL && $scProv->asigned_clave != '' ? $JwtAuth->desencriptar($scProv->asigned_clave) : '',
              "eliminacion_proceso" => false,
            );
            $listaClavesProv[] = $scRow;
          }

          $rowEach = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "fecha_registro_serv" => gmdate('Y-m-d H:i:s', $value->fecha_registro_serv),
            "folio_sistema" => $folio_serv,
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "clasificacion_token" => $value->token_clasificacion,
            "clasificacion_code" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio),
            "genero_token" => $value->token_genero,
            //unidad de medida
            "unidad_medida_clave" => $value->unidad_medida_clave,
            "unidad_medida_homologada" => $value->unidad_medida_homologada,
            //catalogo del sat
            "sat_clave_code" => $value->sat_clave_code,
            "sat_homologado" => $value->sat_homologado,
            "proveedores" => $listaClavesProv,
            "pdf_serv" => "https://downloads.sos-mexico.com.mx/compras/servicios/" . $folio_serv,
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

  public function detalleServicioProveedor(Request $request){
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
      'identificador' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_articulo = $request->input('token_articulo');
      $tokenProveedor = $request->input('tokenProveedor');
      $identificador = $request->input('identificador');

      $servListActFijo = ServiciosModelo::join("in_egr_catalogo_servicios_claves AS clavserv", "in_egr_catalogo_servicios.id", "=", "clavserv.servicio_id")
      ->join("eegr_catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
      ->whereNull("in_egr_catalogo_servicios.administrador")
      ->where([
        'in_egr_catalogo_servicios.folio_sistema' => '999999998',
        'in_egr_catalogo_servicios.token_cat_servicios' => $token_articulo,
        'catprov.token_cat_proveedores' => $tokenProveedor,
        //'clavserv.asigned_clave' => $noIdentificacionXML,
        'in_egr_catalogo_servicios.status' => true
      ])
      ->select(["in_egr_catalogo_servicios.*"]);

      $servListActDiferido = ServiciosModelo::join("in_egr_catalogo_servicios_claves AS clavserv", "in_egr_catalogo_servicios.id", "=", "clavserv.servicio_id")
      ->join("eegr_catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
      ->whereNull("in_egr_catalogo_servicios.administrador")
      ->where([
        'in_egr_catalogo_servicios.folio_sistema' => '999999999',
        'in_egr_catalogo_servicios.token_cat_servicios' => $token_articulo,
        'catprov.token_cat_proveedores' => $tokenProveedor,
        //'clavserv.asigned_clave' => $noIdentificacionXML,
        'in_egr_catalogo_servicios.status' => true
      ])
      ->select(["in_egr_catalogo_servicios.*"]);

      $servListPrv = ServiciosModelo::join("in_egr_catalogo_servicios_claves AS clavserv", "in_egr_catalogo_servicios.id", "=", "clavserv.servicio_id")
      ->join("eegr_catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
      ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'in_egr_catalogo_servicios.token_cat_servicios' => $token_articulo,
        'catprov.token_cat_proveedores' => $tokenProveedor,
        //'clavserv.asigned_clave' => $noIdentificacionXML,
        'in_egr_catalogo_servicios.status' => true,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select(["in_egr_catalogo_servicios.*"]);

      $queryServicios = $servListActFijo->unionAll($servListActDiferido)->unionAll($servListPrv)->get();
      
      if ($queryServicios->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Los codigos de identificación de acuerdo al proveedor seleccionado no coinciden'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'articulo homologado',
          'token_articulo' => $token_articulo,
          'identificador' => $identificador,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recargaProvServicios(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $arrayProvServ = array();

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
        $listaProveedores = ProveedoresModelo::join("personas AS prov", "catalogo_proveedores.proveedor", "prov.id")
          ->join("main_empresas AS emp", "catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("personal", "empresapersonal.personal", "=", "personal.id")
          ->join("teci_usuarios_catalogo AS users", "personal.usuario", "=", "users.id")
          ->where([
            'catalogo_proveedores.status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
            'catalogo_proveedores.status' => true
          ])->get();
        foreach ($listaProveedores as $resListProv) {
          $provservLista = ServiciosModelo::join(
            "serv_claves AS clavserv",
            "catserv.id",
            "=",
            "clavserv.servicio_id"
          )
            ->join("eegr_catalogo_proveedores AS catprov", "clavserv.proveedor", "=", "catprov.id")
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
            $tiene_clave = $relservprov->tiene_clave == TRUE ? 'true' : 'false';
            $claveAsignada = $relservprov->asigned_clave != NULL && $relservprov->asigned_clave != '' ? $JwtAuth->desencriptar($relservprov->asigned_clave) : '';
            $token_serv_claves = $relservprov->token_serv_claves;
            $encendido = true;
            $trProv = 'trCliente';
          }

          $rfc_generico = $resListProv->rfc_generico;
          $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
          $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';
          $folio_prov = 'prv-' . ($resListProv->post_folio == NULL ? $JwtAuth->generarFolio($resListProv->folio) : $JwtAuth->generarFolio($resListProv->folio) . '-' . $resListProv->post_folio);
          $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

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
          'status' => 'success',
          'code' => 200,
          'proveedores' => $arrayProvServ
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

  public function downloadServicioEgresosPdf(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $arrayProvServ = array();
    $arrayServVigentes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'servdata' => 'required'
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
        $servList = ServiciosModelo::join("servicios AS ltserv", "catserv.servicio", "=", "ltserv.id")
          ->join("sos_ps_genero AS gen", "ltserv.genero", "=", "gen.id")
          ->join("teci_catalogo_prodservsat AS prsrvsat", "ltserv.catalogoSAT", "=", "prsrvsat.id")
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
          $pdf_serv = Storage::path('public/root/' . $value->root_tkn . '/0002-cpp/catalogos/servicios/' . $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
            $JwtAuth->generar($value->folio) . '-' . $value->fechaAlta . '/' . $JwtAuth->desencriptar($value->imagen) . '.pdf');
          $dompdf = \PDF::loadView($pdf_serv);
          return response()->download($dompdf);
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

  public function actualizaGeneralesServicio(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_servicio' => 'required|string',
        'concepto' => 'required|string',
        'clasificacion' => 'required|string',
        'genero' => 'required|string',
        'clave_sat' => 'nullable|string',
        "unidad_medida_clave" => 'string',
        "proveedor_vinc" => 'array',
        "nuevo_proveedor" => 'array',
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
        $token_cat_servicio = $parametrosArray["token_cat_servicio"];
        $concepto = $parametrosArray["concepto"];
        $clasificacion = $parametrosArray["clasificacion"];
        $genero = $parametrosArray["genero"];
        $clave_sat = $parametrosArray["clave_sat"];
        $unidad_medida_clave = $parametrosArray["unidad_medida_clave"];
        $proveedor_vinc = $parametrosArray["proveedor_vinc"];
        $nuevo_proveedor = $parametrosArray["nuevo_proveedor"];

        $valida_token_cat_servicio = isset($token_cat_servicio) && !empty($token_cat_servicio);
        $valida_concepto = isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $concepto);
        $valida_clasificacion = isset($clasificacion) && !empty($clasificacion);
        $valida_genero = isset($genero) && !empty($genero);
        $valida_clave_sat = isset($clave_sat) && !empty($clave_sat) && preg_match($JwtAuth->filtroNumericoSimple(), $clave_sat);
        $valida_unidad_medida_clave = isset($unidad_medida_clave) && !empty($unidad_medida_clave) && preg_match($JwtAuth->filtroNumericoSimple(), $unidad_medida_clave);

        $queryServicio = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.status' => TRUE,
            'in_egr_catalogo_servicios.token_cat_servicios' => $token_cat_servicio,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        //echo count($queryServicio);
        if (count($queryServicio) == 1) {
          foreach ($queryServicio as $vServ) {
            $obtenServicio = DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $vServ->token_cat_servicios)->value("id");
            $clasifServ = $clasificacion != "" ? DB::table("sos_ps_clasificacion")->where("token_clasificacion", $clasificacion)->value("id") : NULL;
            $generoServ = $genero != "" ? DB::table("sos_ps_genero")->where("token_genero", $genero)->value("id") : NULL;
            $unidadMedidaClv = $unidad_medida_clave != "" ? $unidad_medida_clave : NULL;
            $sat_sql_clave = $clave_sat != "" ? $clave_sat : NULL;
            $conceptoServ = $JwtAuth->encriptar(strtolower($concepto));

            $upDateServicio = ServiciosModelo::where(['token_cat_servicios' => $vServ->token_cat_servicios])
              ->limit(1)->update(
                array(
                  "servicio" => $conceptoServ,
                  "clasificacion" => $clasifServ,
                  "genero" => $generoServ,
                  "unidad_medida_clave" => $unidadMedidaClv,
                  "sat_clave_code" => $sat_sql_clave,
                )
              );

            if (count($proveedor_vinc) > 0) {
              for ($i = 0; $i < count($proveedor_vinc); $i++) {
                $proveedorToken = $proveedor_vinc[$i]['token_cat_proveedores'];
                $obtenProv = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $proveedorToken)->value("id");
                if ($proveedor_vinc[$i]['eliminacion_proceso'] == false) {
                  $tiene_clave = $proveedor_vinc[$i]['tiene_clave'] == 'true' ? TRUE : FALSE;
                  $asigned_clave = $proveedor_vinc[$i]['tiene_clave'] == 'true' ? $JwtAuth->encriptar($proveedor_vinc[$i]['asigned_clave']) : NULL;
                  $txtClave = $proveedor_vinc[$i]['tiene_clave'] == 'true' ? $asigned_clave : 'noi hay clave';
                  $tokenClavesServ = $proveedor_vinc[$i]['token_serv_claves'];

                  $insertProd = DB::table('in_egr_catalogo_servicios_claves')
                    ->where(["token_serv_claves" => $tokenClavesServ, "servicio_id" => $obtenServicio, "proveedor" => $obtenProv])
                    ->limit(1)->update(array(
                      "tiene_clave" => $tiene_clave,
                      "asigned_clave" => $asigned_clave
                    ));
                } else {
                  $deleteClave = DB::table('in_egr_catalogo_servicios_claves')
                    ->where(["servicio_id" => $obtenServicio, "proveedor" => $obtenProv])
                    ->limit(1)->delete();
                }
              }
            }

            if (count($nuevo_proveedor) > 0) {
              for ($i = 0; $i < count($nuevo_proveedor); $i++) {
                $proveedorToken = $nuevo_proveedor[$i]['token_cat_proveedores'];
                $obtenProv = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $proveedorToken)->value("id");
                $tiene_clave = $nuevo_proveedor[$i]['tiene_clave'] == 'true' ? TRUE : FALSE;
                $asigned_clave = $nuevo_proveedor[$i]['tiene_clave'] == 'true' ? $JwtAuth->encriptar($nuevo_proveedor[$i]['clave']) : NULL;
                $txtClave = $nuevo_proveedor[$i]['tiene_clave'] == 'true' ? $asigned_clave : 'noi hay clave';
                $tokenClavesServ = $JwtAuth->encriptarToken(time(), $nuevo_proveedor[$i]['tiene_clave'], $txtClave);
                $insertProd = DB::table('in_egr_catalogo_servicios_claves')
                  ->insert(array(
                    "token_serv_claves" => $tokenClavesServ,
                    "servicio_id" => $obtenServicio,
                    "proveedor" => $obtenProv,
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
            'message' => 'El servicio solicitado no ha sido encontrado o no ha sido registrado, revise su información o comuniquese a soporte'
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

  public function actualizaProvClavesServicio(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
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
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['tknProveedor']]);
        if (count($obtenProv) == 1) {
          $tiene_clave = $parametrosArray['tiene_clave'] == 'true' ? TRUE : FALSE;
          $asigned_clave = $parametrosArray['tiene_clave'] == 'true' ? $JwtAuth->encriptar($parametrosArray['clave']) : NULL;

          $upDateServicio = DB::table('serv_claves')
            ->join("in_egr_catalogo_servicios AS catserv", "serv_claves.servicio_id", "=", "catserv.id")
            ->join("main_empresas AS emp", "catserv.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
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
            ->limit(1)->update(array("serv_claves.tiene_clave" => $tiene_clave, "serv_claves.asigned_clave" => $asigned_clave));

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
        'message' => 'Los datos solicitados para este registro son incorrectos o invalidos, revise su información o comuniquese a soporte'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function newProvClavesServicio(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
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
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['tknProveedor']]);
        $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?", [$parametrosArray['token_cat_servicio']]);
        $tkn_clavesServ = $JwtAuth->encriptarToken(time(), $parametrosArray['token_cat_servicio'], $parametrosArray['tknProveedor']);

        $tiene_clave = $parametrosArray['tiene_clave'] == 'true' ? TRUE : FALSE;
        $asigned_clave = $parametrosArray['tiene_clave'] == 'true' ? $JwtAuth->encriptar(strtolower($parametrosArray['clave'])) : NULL;

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
        'message' => 'Los datos solicitados para este registro son incorrectos o invalidos, revise su información o comuniquese a soporte'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteProvClavesServicio(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
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
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
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
        'message' => 'Los datos solicitados para este registro son incorrectos o invalidos, revise su información o comuniquese a soporte'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteServicioEgresos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $serviciosLista = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_servicio' => 'required|string'
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
        $token_cat_servicio = $parametrosArray['token_cat_servicio'];

        $obtenCompraServ = DB::select("SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_servicios AS catserv 
							JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
							WHERE detcomp.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
							AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
							AND users.usuario_token = ?", [$token_cat_servicio, $usuario->empresa_token, $usuario->user_token]);

        if (count($obtenCompraServ) == 0) {
          $prodDeleteList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'in_egr_catalogo_servicios.token_cat_servicios' => $token_cat_servicio,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                'in_egr_catalogo_servicios.fecha_delete_serv' => time(),
                'in_egr_catalogo_servicios.status' => FALSE
              )
            );

          if ($prodDeleteList) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Servicio eliminado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Servicio no eliminado, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Servicio no eliminado, esta vinculado a compras'
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

  public function listaegresosServiciosEliminados(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $arrayServVigentes = array();

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
            'in_egr_catalogo_servicios.proceso' => 'c',
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($servList as $value) {
          $folio_serv = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('SERV-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'SERV-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $arrayForeachVig = array(
            "token_cat_servicios" => $value->token_cat_servicios,
            "folio_sistema" => $folio_serv,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->folio),
            "servicio" => $JwtAuth->desencriptar($value->servicio),
            "catalogo_sat" => $value->sat_homologado != NULL && $value->sat_homologado != "" ? $value->sat_homologado : "N/A",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "fecha_delete_serv" => gmdate('Y-m-d H:i:s', $value->fecha_delete_serv),
            "logotipo" => "./assets/images/catalogos/default_servicio.jpg",
          );
          $arrayServVigentes[] = $arrayForeachVig;
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosServicio' => $arrayServVigentes,
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

  public function restartServicio(Request $request){
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
        $token_cat_servicio = $parametrosArray['token_cat_servicio'];
        $prodDeleteList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_servicios.token_cat_servicios' => $token_cat_servicio,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(
            array(
              'in_egr_catalogo_servicios.fecha_delete_serv' => '',
              'in_egr_catalogo_servicios.status' => TRUE
            )
          );

        if ($prodDeleteList) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Servicio restaurado satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Servicio no restaurado'
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

  public function deleteDeadServicioEgresos(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('image');
    $jsonServ = $request->input('servdata');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
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
        $obtenCompraServ = DB::select("SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_servicios AS catserv 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE detcomp.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                    AND users.usuario_token = ?", [$parametrosArray['servdata'], $usuario->empresa_token, $usuario->user_token]);

        if (count($obtenCompraServ) == 0) {
          $provservLista = ServiciosModelo::join("in_egr_catalogo_servicios_claves AS clavserv", "in_egr_catalogo_servicios.id", "=", "clavserv.servicio_id")
            ->where([
              'in_egr_catalogo_servicios.token_cat_servicios' => $parametrosArray['servdata']
            ])->count();

          if ($provservLista >= 1) {
            $deleteProdClaveServ = ServiciosModelo::join("in_egr_catalogo_servicios_claves AS clavserv", "in_egr_catalogo_servicios.id", "=", "clavserv.servicio_id")
              ->where([
                'in_egr_catalogo_servicios.token_cat_servicios' => $parametrosArray['servdata']
              ])->limit(1)->delete();

            if ($deleteProdClaveServ) {
              $servDeleteList = ServiciosModelo::join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                ->where([
                  'in_egr_catalogo_servicios.token_cat_servicios' => $parametrosArray['servdata'],
                  'emp.empresa_token' => $usuario->empresa_token,
                  'users.usuario_token' => $usuario->user_token,
                ])
                ->limit(1)->update(array('catserv.fecha_delete_serv' => time(), 'catserv.status' => FALSE));

              if ($servDeleteList) {
                $dataMensaje = array('status' => 'success', 'code' => 200, 'message' => 'servicio eliminado satisfactoriamente');
              } else {
                $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'servicio no eliminado');
              }
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'relación de servicio con proveedor no eliminada');
            }
          } else {
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
              $dataMensaje = array('status' => 'success', 'code' => 200, 'message' => 'servicio eliminado satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'servicio no eliminado');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'servicio no eliminado, esta vinculado a compras');
        }
      }
    } else {
      $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Los datos solicitados para este registro son incorrectos o invalidos, revise su información o comuniquese a soporte');
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
