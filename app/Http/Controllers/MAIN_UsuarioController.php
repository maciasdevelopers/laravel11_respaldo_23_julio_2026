<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class MAIN_UsuarioController extends Controller{
  public $key;
  public function __construct(){
    $this->key = 'dtclavessecreto-9876986986986986s';
  }
  
  public function catalogo_general_usuarios(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listUsuarios = DB::table("teci_usuarios_catalogo AS users")
    ->join("main_empresa_usuario AS empuser", "users.id", "empuser.usuario")
    ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
    ->where('emp.empresa_token',$empresa)->get();

    //$queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
    //WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
    
    if ($listUsuarios->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron usuarios registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $usersArray = array();
      foreach ($listUsuarios as $vUsers) {
        $queryTipoUsuario = DB::table("teci_usuario_tipo AS utip")
        ->join("teci_usuarios_catalogo AS users", "utip.id", "users.tipo")
        ->where('users.usuario_token',$vUsers->usuario_token)
        ->select('utip.tipo')
        ->first();

        $row = array(
          "usuario_token" => $vUsers->usuario_token,
          "usuario_folio" => 'USER-'.$JwtAuth->generarFolio($vUsers->usuario_folio),
          "usuario_alias" => $JwtAuth->desencriptar($vUsers->usuario_alias),
          "usuario_has_pass" => ($vUsers->acceso_email == "" || $vUsers->acceso_codigo == "") && $vUsers->acceso_password == "" ? false : true,
          "login_permission" => $vUsers->login_permission ? true : false,
          "jerarquia_main" => $vUsers->jerarquia_main,
          "tipo" => $queryTipoUsuario ? $queryTipoUsuario->tipo : '',
          "verModalUsuario" => false,
          "desglose_info" => [],
        );
        $usersArray[] = $row;
      }
      
      $dataMensaje = array(
        'usuarios' => $usersArray,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function usuarios_desglose_completo(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usersArray = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $usuario_token = $parametrosArray['usuario_token'];

        //$listUsuarios = DB::table("teci_usuarios_catalogo AS users")
        //->join("main_empresas AS emp", "users.empresa", "emp.id")
        //->where('users.usuario_token',$usuario_token)
        //->where('emp.empresa_token',$usuario->empresa_token)
        //->get();
        
        $listUsuarios = DB::table("teci_usuarios_catalogo AS users")
        ->join("main_empresa_usuario AS empuser", "users.id", "empuser.usuario")
        ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
        ->where([
          'users.usuario_token' => $usuario_token,
          'emp.empresa_token' => $usuario->empresa_token
        ])->get();

        //echo count($listPersonal);
        foreach ($listUsuarios as $vUsers) {
          $queryTipoUsuario = DB::table("teci_usuario_tipo AS utip")
          ->join("teci_usuarios_catalogo AS users", "utip.id", "users.tipo")
          ->where('users.usuario_token',$vUsers->usuario_token)
          ->select('utip.tipo')
          ->first();

          $permisos_ingresos = array();
          $queryConfigIngr = DB::table("configuracion_systema_ingr AS conf_ingr")
          ->join("main_empresas AS emp","conf_ingr.empresa","=","emp.id")
          ->join("teci_usuarios_catalogo AS users","conf_ingr.usuario","=","users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario_token])->get();
          foreach ($queryConfigIngr as $cINGR) {
            $row_in_conf = array(
              "jerarquia" => $cINGR->jerarquia,
              "bool_ingr_perm_crear" => $cINGR->privilegio_crear ? true : false,	
              "bool_ingr_perm_editar" => $cINGR->privilegio_editar ? true : false,	
              "bool_ingr_perm_consulta" => $cINGR->privilegio_consulta ? true : false,	
              "bool_ingr_perm_elimina" => $cINGR->privilegio_elimina ? true : false,	
              "bool_ingr_perm_ver_docs" => $cINGR->privilegio_ver_docs ? true : false,
            );
            $permisos_ingresos[] = $row_in_conf;
          }

          $permisos_egresos = array();
          $queryConfigEegr = DB::table("configuracion_systema_eegr AS eegr_conf")
          ->join("main_empresas AS emp","eegr_conf.empresa","emp.id")
          ->join("teci_usuarios_catalogo AS users","eegr_conf.usuario","users.id")                                                        
          ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario_token])->get();
          foreach ($queryConfigEegr as $vCegr) {
            $row_ee_conf = array(
              "jerarquia" => $vCegr->jerarquia,
              "bool_eegr_catalogos" => $vCegr->catalogos ? true : false,
              "bool_eegr_cat_prod" => $vCegr->cat_prod ? true : false,
              "bool_eegr_cat_serv" => $vCegr->cat_serv ? true : false,
              "bool_eegr_cat_actf" => $vCegr->cat_actf ? true : false,
              "bool_eegr_cat_acti" => $vCegr->cat_acti ? true : false,
              "bool_eegr_cat_prov" => $vCegr->cat_prov ? true : false,
              "bool_eegr_cat_esta" => $vCegr->cat_esta ? true : false,
              "bool_eegr_compras" => $vCegr->compras ? true : false,
              "bool_eegr_comp_req" => $vCegr->comp_req ? true : false,
              "bool_eegr_comp_cot" => $vCegr->comp_cot ? true : false,
              "bool_eegr_comp_dir" => $vCegr->comp_dir ? true : false,
              "bool_eegr_comp_seg" => $vCegr->comp_seg ? true : false,
              "bool_eegr_perm_crear" => $vCegr->privilegio_crear ? true : false,	
              "bool_eegr_perm_editar" => $vCegr->privilegio_editar ? true : false,	
              "bool_eegr_perm_consulta" => $vCegr->privilegio_consulta ? true : false,	
              "bool_eegr_perm_elimina" => $vCegr->privilegio_elimina ? true : false,	
              "bool_eegr_perm_ver_docs" => $vCegr->privilegio_ver_docs ? true : false,
            );
            $permisos_egresos[] = $row_ee_conf;
          }

          $permisos_finanzas = array();
          $queryConfigFnzs = DB::table("configuracion_systema_fnzs AS conf_fnzs")
          ->join("main_empresas AS emp","conf_fnzs.empresa","=","emp.id")
          ->join("teci_usuarios_catalogo AS users","conf_fnzs.usuario","=","users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario_token])->get();
          foreach ($queryConfigFnzs as $cFNZS) {
            $row_fnzs_conf = array(
              "jerarquia" => $cFNZS->jerarquia,
              "bool_fnzs_perm_crear" => $cFNZS->privilegio_crear ? true : false,	
              "bool_fnzs_perm_editar" => $cFNZS->privilegio_editar ? true : false,	
              "bool_fnzs_perm_consulta" => $cFNZS->privilegio_consulta ? true : false,	
              "bool_fnzs_perm_elimina" => $cFNZS->privilegio_elimina ? true : false,	
              "bool_fnzs_perm_ver_docs" => $cFNZS->privilegio_ver_docs ? true : false,
            );
            $permisos_finanzas[] = $row_fnzs_conf;
          }

          $permisos_vhum = array();
          $queryConfigVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
          ->join("main_empresas AS emp","conf_vhum.empresa","=","emp.id")
          ->join("teci_usuarios_catalogo AS users","conf_vhum.usuario","=","users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario_token])->get();
          foreach ($queryConfigVhum as $cVHUM) {
            $row_vhum_conf = array(
              "jerarquia" => $cVHUM->jerarquia,
              "bool_vhum_perm_crear" => $cVHUM->privilegio_crear ? true : false,	
              "bool_vhum_perm_editar" => $cVHUM->privilegio_editar ? true : false,	
              "bool_vhum_perm_consulta" => $cVHUM->privilegio_consulta ? true : false,	
              "bool_vhum_perm_elimina" => $cVHUM->privilegio_elimina ? true : false,	
              "bool_vhum_perm_ver_docs" => $cVHUM->privilegio_ver_docs ? true : false,
            );
            $permisos_vhum[] = $row_vhum_conf;
          }

          $permisos_contabilidad = array();
          $queryConfigCont = DB::table("configuracion_systema_cont AS conf_cont")
          ->join("main_empresas AS emp","conf_cont.empresa","=","emp.id")
          ->join("teci_usuarios_catalogo AS users","conf_cont.usuario","=","users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario_token])->get();
          foreach ($queryConfigCont as $cCONT) {
            $row_cont_conf = array(
              "jerarquia" => $cCONT->jerarquia,
              "bool_cont_perm_crear" => $cCONT->privilegio_crear ? true : false,	
              "bool_cont_perm_editar" => $cCONT->privilegio_editar ? true : false,	
              "bool_cont_perm_consulta" => $cCONT->privilegio_consulta ? true : false,	
              "bool_cont_perm_elimina" => $cCONT->privilegio_elimina ? true : false,	
              "bool_cont_perm_ver_docs" => $cCONT->privilegio_ver_docs ? true : false,
            );
            $permisos_contabilidad[] = $row_cont_conf;
          }

          $permisos_teci = array();
          $queryConfigTeci = DB::table("configuracion_systema_teci AS conf_teci")
          ->join("main_empresas AS emp","conf_teci.empresa","=","emp.id")
          ->join("teci_usuarios_catalogo AS users","conf_teci.usuario","=","users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario_token])->get();
          foreach ($queryConfigTeci as $cTECI) {
            $row_teci_conf = array(
              "jerarquia" => $cTECI->jerarquia,
              "bool_teci_perm_crear" => $cTECI->privilegio_crear ? true : false,	
              "bool_teci_perm_editar" => $cTECI->privilegio_editar ? true : false,	
              "bool_teci_perm_consulta" => $cTECI->privilegio_consulta ? true : false,	
              "bool_teci_perm_elimina" => $cTECI->privilegio_elimina ? true : false,	
              "bool_teci_perm_ver_docs" => $cTECI->privilegio_ver_docs ? true : false,
            );
            $permisos_teci[] = $row_teci_conf;
          }

          $row = array(
            "empresa_token" => $vUsers->empresa_token,
            "usuario_token" => $vUsers->usuario_token,
            "usuario_folio" => 'USER-'.$JwtAuth->generarFolio($vUsers->usuario_folio),
            "usuario_alias" => $JwtAuth->desencriptar($vUsers->usuario_alias),
            "usuario_has_pass" => ($vUsers->acceso_email == "" || $vUsers->acceso_codigo == "") && $vUsers->acceso_password == "" ? false : true,
            "login_permission" => $vUsers->login_permission ? true : false,
            "jerarquia_main" => $vUsers->jerarquia_main,
            "tipo" => $queryTipoUsuario ? $queryTipoUsuario->tipo : '',
            "conf_ingresos" => $permisos_ingresos,
            "conf_egresos" => $permisos_egresos,
            "conf_finanzas" => $permisos_finanzas,
            "conf_valor_humano" => $permisos_vhum,
            "conf_contabilidad" => $permisos_contabilidad,
            "conf_tec_info" => $permisos_teci,
          );
          $usersArray[] = $row;
        }

        return response()->json(["status" => "success", "code" => 200, "usuario" => $usersArray]);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function generaPassCodeUserPersonalSOS(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'access_code' => 'required|string',
        'password_code' => 'required|string',
        'usuario_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $usuario_token = $parametrosArray['usuario_token'];
        $access_code = $parametrosArray['access_code'];
        $password_code = $parametrosArray['password_code'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';

        $valida_usuario_token = isset($usuario_token) && !empty($usuario_token);
        $valida_access_code = isset($access_code) && !empty($access_code);
        $valida_password_code = isset($password_code) && !empty($password_code);

        if ($valida_usuario_token && $valida_access_code && $valida_password_code) {
          $queryCredenciales = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresa_usuario AS empuser", "users.id", "=", "empuser.usuario")
          ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->where('users.usuario_token',$usuario_token)
          ->where('emp.empresa_token',$usuario->empresa_token)
          ->get();

          foreach ($queryCredenciales as $vCred) {
            $updateCredenciales = DB::table("teci_usuarios_catalogo")
            ->where('usuario_token',$vCred->usuario_token)
            ->limit(1)->update(
              array(
                'acceso_codigo' => $JwtAuth->encriptar($access_code),
                'acceso_password' => $JwtAuth->encriptar($password_code)
              )
            );

            if ($updateCredenciales) {
              $dataMensaje = array(
                'status' => "success",
                'code' => 200,
                'message' => 'Códigos de acceso generados'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Códigos de acceso no generados'
              );
            }
          }
        } else {
          $mensaje_error = '';
          if (!$valida_usuario_token) {$mensaje_error = 'Error en usuario seleccionado, intentelo nuevamente o comuniquese a soporte';}
          if (!$valida_access_code) {$mensaje_error = 'Error en código de acceso de usuario seleccionado, intentelo nuevamente o comuniquese a soporte';}
          if (!$valida_password_code) {$mensaje_error = 'Error en password de usuario seleccionado, intentelo nuevamente o comuniquese a soporte';}
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => $mensaje_error
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function revocaPassCodeUserPersonalSOS(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $usuario_token = $parametrosArray['usuario_token'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';

        if (isset($usuario_token) && !empty($usuario_token)) {
          //echo $usuario_token;
          $queryCredenciales = DB::table("teci_usuarios_catalogo AS users")
          //->join("main_empresa_usuario AS empuser", "users.id", "=", "empuser.usuario")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where('users.usuario_token',$usuario_token)
          ->where('emp.empresa_token',$usuario->empresa_token)
          ->get();
          //echo count($queryCredenciales);exit;

          foreach ($queryCredenciales as $vCred) {
            $updateCredenciales = DB::table("teci_usuarios_catalogo")
            ->where('usuario_token',$vCred->usuario_token)
            ->limit(1)->update(
              array(
                'acceso_codigo' => NULL,
                'acceso_password' => NULL
              )
            );

            if ($updateCredenciales) {
              $dataMensaje = array(
                'status' => "success",
                'code' => 200,
                'message' => 'Códigos de acceso revocados'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Códigos de acceso no revocados'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en usuario seleccionado, intentelo nuevamente o comuniquese a soporte'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function oldLoginUsuarioMain(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authSsic = new \App\Helpers\AuthSsic();
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "token_device" => "nullable|string",
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $codAccessDecrypt = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $passDecrypt =  $JwtAuth->encriptar($arrayParams['password']);
        $token_device = $arrayParams["token_device"];
        $dataMensaje = $JwtAuth->loginMainInside($codAccessDecrypt, $passDecrypt, $token_device);
        /*$url = 'https://fcm.googleapis.com/v1/projects/notif-11f0a/messages:send';
                $serverKey = env('FIREBASE_SERVER_KEY');
                $data = [
                    "to" => $firebase_token_web,
                    "notification" => array (
                        "title" => "sistemas",
                        "body" => "testeo de aplicacion",
                        "sound" => "default",
                    )
                ];
                $headers = [
                    'Authorization: key=' . $serverKey,
                    'Content-Type: application/json',
                ];
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                $response = curl_exec($curl);
                if (curl_errno($curl)) {
                    echo 'Error:' . curl_error($curl);
                } else {
                    echo 'Respuesta de Firebase: ' . $response;
                }*/
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    return response()->json($dataMensaje, 200);
  }

  //ingresos
  public function userPermisosIngresosAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'acceso' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $acceso_menu = $parametrosArray["acceso"] == true ? FALSE : TRUE;

        $data_user = DB::table("teci_permisos_usuario_old AS old_menu")
          ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])->get();

        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_permisos_usuario_old AS old_menu")
            ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(["emp.empresa_token" => $vUser->empresa_token, "users.usuario_token" => $vUser->user_token])
            ->limit(1)->update(array("old_menu.ingr_cpc" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al menu de ingresos de tu usuario ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_acceso" => $acceso_menu);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  //jerarquia_ingresos

  public function userPermisosIngresosJerarquia(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'jerarquia' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $jerarquia = $parametrosArray["jerarquia"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($jerarquia == "D") {
            $ingr_jerarquia = "P";
          } else {
            $ingr_jerarquia = "D";
          }

          $updateJingrUser = DB::table("configuracion_systema_ingr AS conf_ingr")
            ->join("main_empresas AS emp", "conf_ingr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_ingr.jerarquia" => $ingr_jerarquia));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "La jerarquía de tu usuario para el módulo de ingresos ha sido modificada";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_jerarquia" => $ingr_jerarquia);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosIngresosCrear(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_crear' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_crear = $parametrosArray["perm_crear"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_crear == "permitido") {
            $ingr_crear = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_crear = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "ingr",
                "perm_soli.permiso" => "crear",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_ingr AS conf_ingr")
            ->join("main_empresas AS emp", "conf_ingr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_ingr.privilegio_crear" => $ingr_crear));

          if ($updateJingrUser) {
            $titulo_ = "Permisos para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de ingresos para registrar ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_crear" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosIngresosEditar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_editar' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_editar = $parametrosArray["perm_editar"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_editar == "permitido") {
            $ingr_editar = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_editar = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "ingr",
                "perm_soli.permiso" => "editar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_ingr AS conf_ingr")
            ->join("main_empresas AS emp", "conf_ingr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_ingr.privilegio_editar" => $ingr_editar));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de ingresos para actualizar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_editar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosIngresosConsultar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_consulta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_consulta = $parametrosArray["perm_consulta"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_consulta == "permitido") {
            $ingr_consulta = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_consulta = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "ingr",
                "perm_soli.permiso" => "consulta",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_ingr AS conf_ingr")
            ->join("main_empresas AS emp", "conf_ingr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_ingr.privilegio_consulta" => $ingr_consulta));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de ingresos para consultar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_consultar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosIngresosEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_elimina' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_elimina = $parametrosArray["perm_elimina"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_elimina == "permitido") {
            $ingr_elimina = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_elimina = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "ingr",
                "perm_soli.permiso" => "eliminar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_ingr AS conf_ingr")
            ->join("main_empresas AS emp", "conf_ingr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_ingr.privilegio_elimina" => $ingr_elimina));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de ingresos para eliminar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_eliminar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosIngresosVerDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_ver_docs' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_ver_docs = $parametrosArray["perm_ver_docs"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_ver_docs == "permitido") {
            $ingr_ver_docs = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_ver_docs = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "ingr",
                "perm_soli.permiso" => "ver_docs",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_ingr AS conf_ingr")
            ->join("main_empresas AS emp", "conf_ingr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_ingr.privilegio_ver_docs" => $ingr_ver_docs));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de ingresos para ver y descargar documentos ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_ver_docs" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //Catalogos
  //MERCANCIAS
  //SERVICIOS
  //LISTA DE PRECIOS
  //DESCUENTOS
  //PROMOCIONES
  //IMPUESTOS
  //CLIENTES
  //Ventas
  //PEDIDOS
  //VENTAS
  //SEGUIMIENTO DE VENTAS
  //DEVOLUCIONES
  //FACTURACIÓN
  //reportes
  //egresos
  public function userPermisosEgresosAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'acceso' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $acceso_menu = $parametrosArray["acceso"] == true ? FALSE : TRUE;

        $data_user = DB::table("teci_permisos_usuario_old AS old_menu")
          ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])->get();

        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_permisos_usuario_old AS old_menu")
            ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(["emp.empresa_token" => $vUser->empresa_token, "users.usuario_token" => $vUser->user_token])
            ->limit(1)->update(array("old_menu.eegr_cpp" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al menu de egresos de tu usuario ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_acceso" => $acceso_menu);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //jerarquia_egresos
  public function userPermisosEgresosJerarquia(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'jerarquia' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $jerarquia = $parametrosArray["jerarquia"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($jerarquia == "D") {
            $eegr_jerarquia = "P";
          } else {
            $eegr_jerarquia = "D";
          }

          $updateJeegrUser = DB::table("configuracion_systema_eegr AS conf_eegr")
            ->join("main_empresas AS emp", "conf_eegr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_eegr.jerarquia" => $eegr_jerarquia));

          if ($updateJeegrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "La jerarquía de tu usuario para el módulo de egresos ha sido modificada";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_jerarquia" => $eegr_jerarquia);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosEgresosCrear(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_crear' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_crear = $parametrosArray["perm_crear"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
        ->join("main_empresa_usuario AS empuser", "users.id", "=", "empuser.usuario")
        ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
        ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        //$queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
        //WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_crear) {
            $ingr_crear = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_crear = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "eegr",
                "perm_soli.permiso" => "crear",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_eegr AS conf_eegr")
          ->join("main_empresas AS emp", "conf_eegr.empresa", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
          ->limit(1)->update(array("conf_eegr.privilegio_crear" => $ingr_crear));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de egresos para registrar ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_crear" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosEgresosEditar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_editar' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_editar = $parametrosArray["perm_editar"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_editar == "permitido") {
            $ingr_editar = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_editar = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "eegr",
                "perm_soli.permiso" => "editar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_eegr AS conf_eegr")
            ->join("main_empresas AS emp", "conf_eegr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_eegr.privilegio_editar" => $ingr_editar));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de egresos para actualizar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_editar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosEgresosConsultar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_consulta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_consulta = $parametrosArray["perm_consulta"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_consulta == "permitido") {
            $ingr_consulta = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_consulta = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "eegr",
                "perm_soli.permiso" => "consulta",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_eegr AS conf_eegr")
            ->join("main_empresas AS emp", "conf_eegr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_eegr.privilegio_consulta" => $ingr_consulta));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de egresos para consultar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_consultar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosEgresosEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_elimina' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_elimina = $parametrosArray["perm_elimina"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_elimina == "permitido") {
            $ingr_elimina = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_elimina = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "eegr",
                "perm_soli.permiso" => "eliminar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_eegr AS conf_eegr")
            ->join("main_empresas AS emp", "conf_eegr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_eegr.privilegio_elimina" => $ingr_elimina));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de egresos para eliminar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_eliminar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosEgresosVerDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_ver_docs' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_ver_docs = $parametrosArray["perm_ver_docs"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_ver_docs == "permitido") {
            $ingr_ver_docs = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_ver_docs = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "eegr",
                "perm_soli.permiso" => "ver_docs",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_eegr AS conf_eegr")
            ->join("main_empresas AS emp", "conf_eegr.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_eegr.privilegio_ver_docs" => $ingr_ver_docs));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de egresos para ver y descargar documentos ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_ver_docs" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //Catalogos
  //PRODUCTOS
  //SERVICIOS
  //ACTIVOS FIJOS
  //ACTIVOS INTANG
  //PROVEEDORES
  //ESTABLECIMIENTOS
  //Compras
  //REQUISICIONES
  //COTIZACIONES   
  //COMPRA DIRECTA
  //SEGUIMIENTO DE COMPRA                     
  //finanzas
  public function userPermisosFinanzasAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'acceso' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $acceso_menu = $parametrosArray["acceso"] == true ? FALSE : TRUE;

        $data_user = DB::table("teci_permisos_usuario_old AS old_menu")
          ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          $updateAccesoMenu = DB::table("teci_permisos_usuario_old AS old_menu")
            ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(["emp.empresa_token" => $vUser->empresa_token, "users.usuario_token" => $vUser->user_token])
            ->limit(1)->update(array("old_menu.fnzs" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al menu de finanzas de tu usuario ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_acceso" => $acceso_menu);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //jerarquia_finanzas
  public function userPermisosFinanzasJerarquia(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'jerarquia' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $jerarquia = $parametrosArray["jerarquia"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($jerarquia == "D") {
            $eegr_jerarquia = "P";
          } else {
            $eegr_jerarquia = "D";
          }

          $updateJfnzsUser = DB::table("configuracion_systema_fnzs AS conf_fnzs")
            ->join("main_empresas AS emp", "conf_fnzs.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_fnzs.jerarquia" => $eegr_jerarquia));

          if ($updateJfnzsUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "La jerarquía de tu usuario para el módulo de ingresos ha sido modificada";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_jerarquia" => $eegr_jerarquia);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosFinanzasCrear(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_crear' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_crear = $parametrosArray["perm_crear"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_crear == "permitido") {
            $ingr_crear = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_crear = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "fnzs",
                "perm_soli.permiso" => "crear",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_fnzs AS conf_fnzs")
            ->join("main_empresas AS emp", "conf_fnzs.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_fnzs.privilegio_crear" => $ingr_crear));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de finanzas para registrar ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_crear" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosFinanzasEditar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_editar' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_editar = $parametrosArray["perm_editar"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_editar == "permitido") {
            $ingr_editar = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_editar = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "fnzs",
                "perm_soli.permiso" => "editar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_fnzs AS conf_fnzs")
            ->join("main_empresas AS emp", "conf_fnzs.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_fnzs.privilegio_editar" => $ingr_editar));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de finanzas para actualizar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_editar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosFinanzasConsultar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_consulta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_consulta = $parametrosArray["perm_consulta"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_consulta == "permitido") {
            $ingr_consulta = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_consulta = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "fnzs",
                "perm_soli.permiso" => "consulta",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_fnzs AS conf_fnzs")
            ->join("main_empresas AS emp", "conf_fnzs.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_fnzs.privilegio_consulta" => $ingr_consulta));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de finanzas para consultar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_consultar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosFinanzasEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_elimina' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_elimina = $parametrosArray["perm_elimina"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_elimina == "permitido") {
            $ingr_elimina = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_elimina = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "fnzs",
                "perm_soli.permiso" => "eliminar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_fnzs AS conf_fnzs")
            ->join("main_empresas AS emp", "conf_fnzs.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_fnzs.privilegio_elimina" => $ingr_elimina));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de finanzas para eliminar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_eliminar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosFinanzasVerDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_ver_docs' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_ver_docs = $parametrosArray["perm_ver_docs"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_ver_docs == "permitido") {
            $ingr_ver_docs = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_ver_docs = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "fnzs",
                "perm_soli.permiso" => "ver_docs",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_fnzs AS conf_fnzs")
            ->join("main_empresas AS emp", "conf_fnzs.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_fnzs.privilegio_ver_docs" => $ingr_ver_docs));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de finanzas para ver y descargar documentos ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_ver_docs" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //Catalogos
  //CUENTAS BANCARIAS
  //CAJA
  //MONEDEROS ELECTRÓNICOS
  //DISPOSITIVOS ELECTRÓNICOS
  //CONTROL DE MOVIMIENTOS BANCARIOS
  //CONTROL DE MOVIMIENTOS EN EFECTIVO
  //ORDENES DE PAGO
  //AJUSTES Y CUENTAS PROPIAS
  //INFORMACIÓN BANCARIA
  //valor_humano
  public function userPermisosValorHumanoAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'acceso' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $acceso_menu = $parametrosArray["acceso"] == true ? FALSE : TRUE;

        $data_user = DB::table("teci_permisos_usuario_old AS old_menu")
          ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          $updateAccesoMenu = DB::table("teci_permisos_usuario_old AS old_menu")
            ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(["emp.empresa_token" => $vUser->empresa_token, "users.usuario_token" => $vUser->user_token])
            ->limit(1)->update(array("old_menu.vhum" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al menu de valor humano de tu usuario ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_acceso" => $acceso_menu);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //jerarquia_valor_humano
  public function userPermisosValorHumanoJerarquia(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'jerarquia' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $jerarquia = $parametrosArray["jerarquia"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($jerarquia == "D") {
            $eegr_jerarquia = "P";
          } else {
            $eegr_jerarquia = "D";
          }

          $updateJvhumUser = DB::table("configuracion_systema_vhum AS conf_vhum")
            ->join("main_empresas AS emp", "conf_vhum.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_vhum.jerarquia" => $eegr_jerarquia));

          if ($updateJvhumUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "La jerarquía de tu usuario para el módulo de ingresos ha sido modificada";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_jerarquia" => $eegr_jerarquia);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosValorHumanoCrear(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_crear' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_crear = $parametrosArray["perm_crear"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_crear == "permitido") {
            $ingr_crear = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_crear = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "vhum",
                "perm_soli.permiso" => "crear",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_vhum AS conf_vhum")
            ->join("main_empresas AS emp", "conf_vhum.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_vhum.privilegio_crear" => $ingr_crear));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de valor humano para registrar ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_crear" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosValorHumanoEditar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_editar' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_editar = $parametrosArray["perm_editar"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_editar == "permitido") {
            $ingr_editar = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_editar = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "vhum",
                "perm_soli.permiso" => "editar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_vhum AS conf_vhum")
            ->join("main_empresas AS emp", "conf_vhum.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_vhum.privilegio_editar" => $ingr_editar));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de valor humano para actualizar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_editar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosValorHumanoConsultar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_consulta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_consulta = $parametrosArray["perm_consulta"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_consulta == "permitido") {
            $ingr_consulta = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_consulta = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "vhum",
                "perm_soli.permiso" => "consulta",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_vhum AS conf_vhum")
            ->join("main_empresas AS emp", "conf_vhum.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_vhum.privilegio_consulta" => $ingr_consulta));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de valor humano para consultar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_consultar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosValorHumanoEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_elimina' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_elimina = $parametrosArray["perm_elimina"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_elimina == "permitido") {
            $ingr_elimina = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_elimina = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "vhum",
                "perm_soli.permiso" => "eliminar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_vhum AS conf_vhum")
            ->join("main_empresas AS emp", "conf_vhum.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_vhum.privilegio_elimina" => $ingr_elimina));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de valor humano para eliminar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_eliminar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosValorHumanoVerDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_ver_docs' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_ver_docs = $parametrosArray["perm_ver_docs"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_ver_docs == "permitido") {
            $ingr_ver_docs = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_ver_docs = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "vhum",
                "perm_soli.permiso" => "ver_docs",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_vhum AS conf_vhum")
            ->join("main_empresas AS emp", "conf_vhum.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_vhum.privilegio_ver_docs" => $ingr_ver_docs));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de valor humano para ver y descargar documentos ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_ver_docs" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //Catalogos
  //REEMBOLSOS
  //JUSTIFICACIÓN DE GASTOS
  //REPORTES
  //contabilidad
  public function userPermisosContabilidadAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'acceso' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $acceso_menu = $parametrosArray["acceso"] == true ? FALSE : TRUE;

        $data_user = DB::table("teci_permisos_usuario_old AS old_menu")
          ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          $updateAccesoMenu = DB::table("teci_permisos_usuario_old AS old_menu")
            ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(["emp.empresa_token" => $vUser->empresa_token, "users.usuario_token" => $vUser->user_token])
            ->limit(1)->update(array("old_menu.cont" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al menu de contabilidad de tu usuario ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_acceso" => $acceso_menu);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //jerarquia_contabilidad
  public function userPermisosContabilidadJerarquia(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'jerarquia' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $jerarquia = $parametrosArray["jerarquia"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($jerarquia == "D") {
            $eegr_jerarquia = "P";
          } else {
            $eegr_jerarquia = "D";
          }

          $updateJcontUser = DB::table("configuracion_systema_cont AS conf_cont")
            ->join("main_empresas AS emp", "conf_cont.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_cont.jerarquia" => $eegr_jerarquia));

          if ($updateJcontUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "La jerarquía de tu usuario para el módulo de ingresos ha sido modificada";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_jerarquia" => $eegr_jerarquia);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosContabilidadCrear(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_crear' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_crear = $parametrosArray["perm_crear"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_crear == "permitido") {
            $ingr_crear = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_crear = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "cont",
                "perm_soli.permiso" => "crear",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_cont AS conf_cont")
            ->join("main_empresas AS emp", "conf_cont.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_cont.privilegio_crear" => $ingr_crear));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de contabilidad para registrar ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_crear" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosContabilidadEditar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_editar' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_editar = $parametrosArray["perm_editar"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_editar == "permitido") {
            $ingr_editar = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_editar = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "cont",
                "perm_soli.permiso" => "editar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_cont AS conf_cont")
            ->join("main_empresas AS emp", "conf_cont.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_cont.privilegio_editar" => $ingr_editar));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de contabilidad para actualizar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_editar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosContabilidadConsultar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_consulta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_consulta = $parametrosArray["perm_consulta"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_consulta == "permitido") {
            $ingr_consulta = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_consulta = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "cont",
                "perm_soli.permiso" => "consulta",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_cont AS conf_cont")
            ->join("main_empresas AS emp", "conf_cont.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_cont.privilegio_consulta" => $ingr_consulta));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de contabilidad para consultar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_consultar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosContabilidadEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_elimina' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_elimina = $parametrosArray["perm_elimina"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_elimina == "permitido") {
            $ingr_elimina = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_elimina = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "cont",
                "perm_soli.permiso" => "eliminar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_cont AS conf_cont")
            ->join("main_empresas AS emp", "conf_cont.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_cont.privilegio_elimina" => $ingr_elimina));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de contabilidad para eliminar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_eliminar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosContabilidadVerDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_ver_docs' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_ver_docs = $parametrosArray["perm_ver_docs"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_ver_docs == "permitido") {
            $ingr_ver_docs = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_ver_docs = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "cont",
                "perm_soli.permiso" => "ver_docs",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_cont AS conf_cont")
            ->join("main_empresas AS emp", "conf_cont.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_cont.privilegio_ver_docs" => $ingr_ver_docs));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de contabilidad para ver y descargar documentos ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_ver_docs" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //Catalogos
  //CATALOGO DE CUENTAS
  //ESTADOS FINANCIEROS
  //REPORTES
  //tec_info
  public function userPermisosTeciInfoAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'acceso' => 'required|boolean',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $acceso_menu = $parametrosArray["acceso"] == true ? FALSE : TRUE;

        $data_user = DB::table("teci_permisos_usuario_old AS old_menu")
          ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          $updateAccesoMenu = DB::table("teci_permisos_usuario_old AS old_menu")
            ->join("main_empresas AS emp", "old_menu.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "old_menu.usuario", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(["emp.empresa_token" => $vUser->empresa_token, "users.usuario_token" => $vUser->user_token])
            ->limit(1)->update(array("old_menu.teci" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al menu de tecnologías de la información de tu usuario ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_acceso" => $acceso_menu);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //jerarquia_tec_info
  public function userPermisosTeciInfoJerarquia(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'jerarquia' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $jerarquia = $parametrosArray["jerarquia"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($jerarquia == "D") {
            $eegr_jerarquia = "P";
          } else {
            $eegr_jerarquia = "D";
          }

          $updateJcontUser = DB::table("configuracion_systema_teci AS conf_teci")
            ->join("main_empresas AS emp", "conf_teci.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_teci.jerarquia" => $eegr_jerarquia));

          if ($updateJcontUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "La jerarquía de tu usuario para el módulo de ingresos ha sido modificada";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_jerarquia" => $eegr_jerarquia);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosTeciInfoCrear(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_crear' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_crear = $parametrosArray["perm_crear"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_crear == "permitido") {
            $ingr_crear = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_crear = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "teci",
                "perm_soli.permiso" => "crear",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_teci AS conf_teci")
            ->join("main_empresas AS emp", "conf_teci.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_teci.privilegio_crear" => $ingr_crear));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de tecnologías de la información para registrar ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_crear" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosTeciInfoEditar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_editar' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_editar = $parametrosArray["perm_editar"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_editar == "permitido") {
            $ingr_editar = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_editar = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "teci",
                "perm_soli.permiso" => "editar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_teci AS conf_teci")
            ->join("main_empresas AS emp", "conf_teci.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_teci.privilegio_editar" => $ingr_editar));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de tecnologías de la información para actualizar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_editar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosTeciInfoConsultar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_consulta' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_consulta = $parametrosArray["perm_consulta"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_consulta == "permitido") {
            $ingr_consulta = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_consulta = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "teci",
                "perm_soli.permiso" => "consulta",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_teci AS conf_teci")
            ->join("main_empresas AS emp", "conf_teci.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_teci.privilegio_consulta" => $ingr_consulta));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de tecnologías de la información para consultar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_consultar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosTeciInfoEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_elimina' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_elimina = $parametrosArray["perm_elimina"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_elimina == "permitido") {
            $ingr_elimina = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_elimina = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "teci",
                "perm_soli.permiso" => "eliminar",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_teci AS conf_teci")
            ->join("main_empresas AS emp", "conf_teci.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_teci.privilegio_elimina" => $ingr_elimina));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de tecnologías de la información para eliminar información ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_eliminar" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function userPermisosTeciInfoVerDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'usuario_empresa' => 'required|string',
        'usuario_user' => 'required|string',
        'perm_ver_docs' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'información incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $usuario_empresa = $parametrosArray["usuario_empresa"];
        $usuario_user = $parametrosArray["usuario_user"];
        $perm_ver_docs = $parametrosArray["perm_ver_docs"];

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario_user, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          //da_te_default_timezone_set($vUser->zona_horaria);
          if ($perm_ver_docs == "permitido") {
            $ingr_ver_docs = FALSE;
            $ingr_salida = "denegado";
          } else {
            $ingr_ver_docs = TRUE;
            $ingr_salida = "permitido";

            $soliPermPend = DB::table("teci_solicitudes_permisos AS perm_soli")
              ->join("main_empresas AS emp", "perm_soli.user_emp", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "perm_soli.user_user", "users.id")
              ->where([
                "perm_soli.modulo" => "teci",
                "perm_soli.permiso" => "ver_docs",
                "perm_soli.solicitud_perm_status" => TRUE,
                "perm_soli.soli_aprobada" => FALSE,
                "users.usuario_token" => $usuario_user,
                "emp.empresa_token" => $usuario_empresa
              ])->get();

            if (count($soliPermPend) > 0) {
              foreach ($soliPermPend as $vPendSP) {
                $aprobarSoli = DB::table("teci_solicitudes_permisos")
                  ->where(["token_permiso" => $vPendSP->token_permiso])
                  ->update(array("soli_aprobada" => TRUE));
              }
            }
          }

          $updateJingrUser = DB::table("configuracion_systema_teci AS conf_teci")
            ->join("main_empresas AS emp", "conf_teci.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "users.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario_user])
            ->limit(1)->update(array("conf_teci.privilegio_ver_docs" => $ingr_ver_docs));

          if ($updateJingrUser) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de tu usuario en el módulo de tecnologías de la información para ver y descargar documentos ha sido modificado";
            $JwtAuth->notificacionPushDevices($vUser->usuario_token, $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Permiso actualizado", "new_ver_docs" => $ingr_salida);
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Permiso no actualizado");
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaAreasSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaAreas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        //'empresa_cliente' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        //echo $JwtAuth->encriptar("capturista");
        $selectArea = DB::select("SELECT area.token_area,area.areaemp FROM vhum_empleados_catalogo_area AS area JOIN main_empresas AS emp WHERE area.empresa = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);
        foreach ($selectArea as $vArea) {
          $listaCargo = array();
          //$selectCargo = DB::select("SELECT cargo.id FROM vhum_empleados_catalogo_cargo AS cargo JOIN vhum_empleados_catalogo_area AS area WHERE cargo.area = area.id AND area.areaemp = ?",[$vArea->areaemp]);
          $selectCargo = DB::table("vhum_empleados_catalogo_area AS area")
            ->join("vhum_empleados_catalogo_cargo AS cargo", "area.id", "cargo.area")
            ->where(["area.token_area" => $vArea->token_area])->get();

          foreach ($selectCargo as $vCarg) {
            //$token_cargo = $JwtAuth->encriptarToken($vCarg->id,$vCarg->cargo,$vArea->areaemp,$vArea->token_area,$usuario->empresa_token);
            //$updateContProv = DB::table("vhum_empleados_catalogo_cargo")->where(["id" => $vCarg->id])->limit(1)->update(array("token_cargo" => $token_cargo));
            $row_cargo = array(
              "cargo_tkn" => $vCarg->token_cargo,
              "cargo_nombre" => $JwtAuth->desencriptar($vCarg->cargo),
            );
            $listaCargo[] = $row_cargo;
          }

          $row = array(
            "token_area" => $vArea->token_area,
            "areaemp" => $JwtAuth->desencriptar($vArea->areaemp),
            "cargos" => $listaCargo,
          );
          $listaAreas[] = $row;
        }
        return response()->json(["status" => "success", "code" => 200, "areas" => $listaAreas]);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
