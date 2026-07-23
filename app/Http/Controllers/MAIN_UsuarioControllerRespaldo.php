<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\User;
use Illuminate\Validation\Validator;
use App\Models\PersonalModelo;
use Firebase\JWT\JWT;

class MAIN_UsuarioController extends Controller{
  //catalogos
  public function catalogo_usuarios_SOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usersArray = array();

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

        $listUsuarios = DB::table("teci_usuarios_catalogo AS users")
        //->join("vhum_empleados_catalogo AS pers", "users.empleado", "pers.id")
        //->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->join("main_empresas AS emp", "users.empresa", "emp.id")
        ->where(['emp.empresa_token' => $usuario->empresa_token])->get();

        //echo count($listPersonal);
        foreach ($listUsuarios as $vUsers) {
          $queryTipoUsuario = DB::table("teci_usuario_tipo AS utip")
          ->join("teci_usuarios_catalogo AS users", "utip.id", "users.tipo")
          ->where(['users.usuario_token' => $vUsers->usuario_token])
          ->select('utip.tipo')
          ->first();

          $row = array(
            "usuario_token" => $vUsers->usuario_token,
            "usuario_folio" => 'USER-'.$JwtAuth->generarFolio($vUsers->usuario_folio),
            "usuario_alias" => $JwtAuth->desencriptar($vUsers->usuario_alias),
            "usuario_has_pass" => ($vUsers->acceso_email == "" || $vUsers->acceso_codigo == "") && $vUsers->acceso_password == "" ? false : true,
            //"acceso_email" => $JwtAuth->desencriptar($vUsers->acceso_email),
            //"acceso_codigo" => $vUsers->acceso_codigo,
            //"acceso_password" => $vUsers->acceso_password,
            "login_permission" => $vUsers->login_permission ? true : false,
            "jerarquia_main" => $vUsers->jerarquia_main,
            "tipo" => $queryTipoUsuario ? $queryTipoUsuario->tipo : '',
            "verModalUsuario" => false,
            //"empresa" => $vUsers->empresa,
            //"empleado" => $vUsers->empleado,
            //"acreedor" => $vUsers->acreedor,
            //"deudor" => $vUsers->deudor,
            //"nombre_identificacion" => $vUsers->nombre_identificacion,

            //"empresa_token" => $vUsers->empresa_token,
            //"empleado_token" => $vUsers->empleado_token,
            //"folio" => $JwtAuth->generar($vUsers->folio_pers),
            //"paterno" => ucwords($JwtAuth->desencriptar($vUsers->paterno)),
            //"materno" => ucwords($JwtAuth->desencriptar($vUsers->materno)),
            //"nombres" => ucwords($JwtAuth->desencriptar($vUsers->nombre)),
            //"nombre_completo" => $nombre_completo,
            //"usuario_alias" => $JwtAuth->desencriptar($vUsers->usuario_alias),
            //"acceso_email" => $JwtAuth->desencriptar($vUsers->acceso_email),
            //"hasPass" => $hasPass,
            //"imagen" => $img_perfil,
            //"arrayTelefonos" => $arrayTelefonos,
            //"modulo_ssic" => $selectAccessSSIC == TRUE ? true : false,
            //"modulo_descarga_xml" => $selectAccessDescargaXml == TRUE ? true : false,
            //"modulo_logistica" => $selectAccessLogistica == TRUE ? true : false,
            //"modulo_cotizaciones" => $selectAccessCotizaciones == TRUE ? true : false,
            //"modulo_proyectos" => $selectAccessGestionProyectos == TRUE ? true : false,
            //"modulo_terceros_associates" => $selectAccessTerAssoc == TRUE ? true : false,
            //"modulo_terceros_clientes" => $selectAccessTerClient == TRUE ? true : false,
            //"modulo_terceros_proveedores" => $selectAccessTerProv == TRUE ? true : false,
            //"modulo_terceros_empleados" => $selectAccessTerEmpleados == TRUE ? true : false,
            //"conf_ingresos" => $permisos_ingresos,
            //"conf_egresos" => $permisos_egresos,
            //"conf_finanzas" => $permisos_finanzas,
            //"conf_valor_humano" => $permisos_valor_humano,
            //"conf_contabilidad" => $permisos_contabilidad,
            //"conf_tec_info" => $permisos_tec_info,
            //"permisos_solicitud" => $permisos_solicitud,
            //"class_perm" => $class_perm,
            //"class_user" => $class_user,
          );
          $usersArray[] = $row;
        }

        return response()->json(["status" => "success", "code" => 200, "usuarios" => $usersArray]);
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

  public function registraUsuarioNuevo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usersArray = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'user_paterno' => 'required|string',
        'user_materno' => 'required|string',
        'user_nombres' => 'required|string',
        'user_email' => 'required|string',
        'user_email_encrypt' => 'required|string',
        'user_empresas' => 'required|array',
        'user_area' => 'required|string',
        'user_cargo' => 'required|string',
        //'empresa_raiz' => 'required|string'
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
        $user_paterno = $parametrosArray['user_paterno'];
        $user_materno = $parametrosArray['user_materno'];
        $user_nombres = $parametrosArray['user_nombres'];
        $user_email = $parametrosArray['user_email'];
        $user_email_encrypt = $parametrosArray['user_email_encrypt'];
        $user_area = $parametrosArray['user_area'];
        $user_cargo = $parametrosArray['user_cargo'];
        $user_empresas = $parametrosArray['user_empresas'];
        //$empresa_raiz = $parametrosArray['empresa_raiz'];

        $valida_user_paterno = isset($user_paterno) && !empty($user_paterno) && preg_match($JwtAuth->filtroAlfabetico(), $user_paterno);
        $valida_user_materno = isset($user_materno) && !empty($user_materno) && preg_match($JwtAuth->filtroAlfabetico(), $user_materno);
        $valida_user_nombres = isset($user_nombres) && !empty($user_nombres) && preg_match($JwtAuth->filtroAlfabetico(), $user_nombres);
        $valida_user_email = isset($user_email) && !empty($user_email) && preg_match($JwtAuth->filtroMail(), $user_email);
        $valida_user_empresas = isset($user_empresas) && !empty($user_empresas) && is_array($user_empresas) && count($user_empresas) > 0;
        $valida_user_area = isset($user_area) && !empty($user_area);
        $valida_user_cargo = isset($user_cargo) && !empty($user_cargo);

        if ($valida_user_paterno && $valida_user_materno && $valida_user_nombres && $valida_user_email && $valida_user_empresas && $valida_user_area && $valida_user_cargo) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?",
            [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            //da_te_default_timezone_set($vEmp->zona_horaria);
            $tokenUserPersona = $JwtAuth->encriptarToken($user_paterno . '/' . $user_materno . '/' . $user_nombres);
            $insertapersonalpersonas = DB::table('sos_personas')
            ->insert(
              array(
                "token_personas" => $tokenUserPersona,
                "paterno" => $JwtAuth->encriptar($user_paterno),
                "materno" => $JwtAuth->encriptar($user_materno),
                "nombre" => $JwtAuth->encriptar($user_nombres),
                "img_perfil" => $JwtAuth->encriptar('default-profile.png'),
              )
            );

            $selectPersona = DB::table("sos_personas")->where("token_personas",$tokenUserPersona)->value("id");
            $selectArea = DB::table("vhum_empleados_catalogo_area")->where("token_area",$user_area)->value("id");
            $selectCargoID = DB::table("vhum_empleados_catalogo_cargo")->where("token_cargo",$user_cargo)->value("id");
            $selectCargoCargo = DB::table("vhum_empleados_catalogo_cargo")->where("token_cargo",$user_cargo)->value("cargo");
            $selectFolioPers = DB::select("SELECT MAX(folio_pers)+1 AS folio_pers FROM vhum_empleados_catalogo");
            $nivel_empleado = $JwtAuth->desencriptar($selectCargoCargo) == "coordinador" ? "N1" : "N2";
            $jerarquia_main = $JwtAuth->desencriptar($selectCargoCargo) == "coordinador" ? "P" : "D";
            $tokenPersonal = $JwtAuth->encriptarToken("$user_paterno/$user_nombres/$user_materno/$vEmp->id/$selectArea/$selectCargoID");

            $insertapersonal = DB::table('vhum_empleados_catalogo')->insert(
              array(
                "empleado_token" => $tokenPersonal,
                "fecha_alta_pers" => time(),
                "folio_pers" => $selectFolioPers[0]->folio_pers,
                "area" => $selectArea,
                "cargo" => $selectCargoID,
                "empleado_name" => $selectPersona,
                "nivel_empleado" => $nivel_empleado,
                //"jerarquia_main" => $jerarquia_main,
                "status" => TRUE
                //folio_pers
                //area
                //cargo
                //empleado_name
                //nivel_empleado
                //jerarquia_main
                //usuario
                //status
                //fecha_delete
              )
            );

            if ($insertapersonal) {
              $selectEmpleado = DB::table("vhum_empleados_catalogo")->where("empleado_token", $tokenPersonal)->value("id");

              $insertPermisosOld = DB::table('teci_permisos_usuario_old')
              ->insert(array("ingr_cpc" => FALSE,"eegr_cpp" => FALSE,"fnzs" => FALSE,"vhum" => FALSE,"cont" => FALSE,"teci" => FALSE,"juri" => FALSE,"empresa" => $vEmp->id,"usuario" => $selectEmpleado));

              $tokenUserNew = $JwtAuth->encriptarToken($tokenPersonal, $JwtAuth->encriptar($user_email), $JwtAuth->encriptar($user_email_encrypt), $tokenUserPersona);
              $dataUser = new User();
              $dataUser->usuario_token = $tokenUserNew;
              $dataUser->usuario_alias = $JwtAuth->encriptar($user_email);
              $dataUser->acceso_email = $JwtAuth->encriptar($user_email_encrypt);
              $dataUser->login_permission = TRUE;
              $dataUser->jerarquia_main = $jerarquia_main;
              $dataUser->tipo = 8;
              $dataUser->empresa = 1;
              $dataUser->empleado = $selectEmpleado;
              
              //$dataUser->inside_ssic = TRUE;
              //$dataUser->outside_descarga_xml = FALSE;
              //$dataUser->outside_logistica = FALSE;
              //$dataUser->outside_compras = FALSE;
              //$dataUser->outside_proyectos = FALSE;
              //$dataUser->outside_terceros = FALSE;
              //$dataUser->outside_terceros_associates = FALSE;
              //$dataUser->outside_terceros_clientes = FALSE;
              //$dataUser->outside_terceros_proveedores = FALSE;
              //$dataUser->outside_terceros_empleados = FALSE;
              //$dataUser->area = $selectArea;
              //$dataUser->registro = NULL;
              $savedNewUser = $dataUser->save();
              
              if ($savedNewUser) {
                $selectUser = DB::table("teci_usuarios_catalogo")->where("usuario_token",$tokenUserNew)->value("id");
                //$updatePersonal = DB::table("vhum_empleados_catalogo")->where("empleado_token",$tokenPersonal)->limit(1)->update(array("usuario" => $selectUser));
  
                $insertUserSettings = DB::table('teci_user_settings')->insert(array(
                  "usuario" => $selectUser,
                  "lenguaje" => "es",
                  "privilegio_crear" => TRUE,
                  "privilegio_editar" => TRUE,
                  "privilegio_consulta" => TRUE,
                  "privilegio_elimina" => TRUE,
                  "privilegio_ver_docs" => TRUE,
                ));
  
                $user_empresas = $parametrosArray['user_empresas'];
                for ($i = 0; $i < count($user_empresas); $i++) {
                  $company = $user_empresas[$i];
                  $selectCompany = DB::table("main_empresas")->where("empresa_token",$company["empresa_token"])->value("id");

                  $insertUnion = DB::table('main_empresa_usuario')->insert(array("empresa" => $selectCompany,"empleado" => $selectEmpleado,"usuario" => $selectUser));

                  //ingresos
                  $insertConfigIngr = DB::table('configuracion_systema_ingr')->insert(array(
                    "acceso" => TRUE,
                    "catalogos" => FALSE,
                    "cat_merc" => FALSE,
                    "cat_serv" => FALSE,
                    "cat_prec" => FALSE,
                    "cat_desc" => FALSE,
                    "cat_prom" => FALSE,
                    "cat_impu" => FALSE,
                    "cat_clie" => FALSE,
                    "ventas" => FALSE,
                    "vent_ped" => FALSE,
                    "vent_dir" => FALSE,
                    "vent_seg" => FALSE,
                    "vent_dev" => FALSE,
                    "vent_fac" => FALSE,
                    "jerarquia" => "D",
                    "privilegio_crear" => FALSE,
                    "privilegio_editar" => FALSE,
                    "privilegio_consulta" => FALSE,
                    "privilegio_elimina" => FALSE,
                    "privilegio_ver_docs" => FALSE,
                    "empresa" => $selectCompany,
                    "usuario" => $selectUser,
                  ));

                  //egresos
                  $insertConfigEegr = DB::table('configuracion_systema_eegr')->insert(array(
                    "acceso" => TRUE,
                    "catalogos" => FALSE,
                    "cat_prod" => FALSE,
                    "cat_serv" => FALSE,
                    "cat_actf" => FALSE,
                    "cat_acti" => FALSE,
                    "cat_prov" => FALSE,
                    "cat_esta" => FALSE,
                    "compras" => FALSE,
                    "comp_req" => FALSE,
                    "comp_cot" => FALSE,
                    "comp_dir" => FALSE,
                    "comp_seg" => FALSE,
                    "reembolsos" => FALSE,
                    "justificaciones" => FALSE,
                    "reportes" => FALSE,
                    "jerarquia" => "D",
                    "privilegio_crear" => FALSE,
                    "privilegio_editar" => FALSE,
                    "privilegio_consulta" => FALSE,
                    "privilegio_elimina" => FALSE,
                    "privilegio_ver_docs" => FALSE,
                    "empresa" => $selectCompany,
                    "usuario" => $selectUser,
                  ));

                  //finanzas
                  $insertConfigEegr = DB::table('configuracion_systema_fnzs')->insert(array(
                    "acceso" => TRUE,
                    "catalogos" => FALSE,
                    "cat_cban" => FALSE,
                    "cat_caja" => FALSE,
                    "cat_moel" => FALSE,
                    "cat_disp" => FALSE,
                    "cmov_ban" => FALSE,
                    "cmov_efe" => FALSE,
                    "paym_ord" => FALSE,
                    "cuen_aju" => FALSE,
                    "info_ban" => FALSE,
                    "jerarquia" => "D",
                    "privilegio_crear" => FALSE,
                    "privilegio_editar" => FALSE,
                    "privilegio_consulta" => FALSE,
                    "privilegio_elimina" => FALSE,
                    "privilegio_ver_docs" => FALSE,
                    "empresa" => $selectCompany,
                    "usuario" => $selectUser,
                  ));

                  //valor_humano
                  $insertConfigVHum = DB::table('configuracion_systema_vhum')->insert(array(
                    "acceso" => TRUE,
                    "catalogos" => FALSE,
                    "reembolsos" => FALSE,
                    "reportes" => FALSE,
                    "jerarquia" => "D",
                    "privilegio_crear" => FALSE,
                    "privilegio_editar" => FALSE,
                    "privilegio_consulta" => FALSE,
                    "privilegio_elimina" => FALSE,
                    "privilegio_ver_docs" => FALSE,
                    "empresa" => $selectCompany,
                    "usuario" => $selectUser,
                  ));

                  //contabilidad
                  $insertConfigCont = DB::table('configuracion_systema_cont')->insert(array(
                    "acceso" => TRUE,
                    "catalogos" => FALSE,
                    "cat_cuentas" => FALSE,
                    "estados_fin" => FALSE,
                    "reportes" => FALSE,
                    "jerarquia" => "D",
                    "privilegio_crear" => FALSE,
                    "privilegio_editar" => FALSE,
                    "privilegio_consulta" => FALSE,
                    "privilegio_elimina" => FALSE,
                    "privilegio_ver_docs" => FALSE,
                    "empresa" => $selectCompany,
                    "usuario" => $selectUser,
                  ));

                  //tec_info
                  $insertConfigTec = DB::table('configuracion_systema_teci')->insert(array(
                    "acceso" => TRUE,
                    "apps_complement" => FALSE,
                    "soporte" => FALSE,
                    "comunicacion" => FALSE,
                    "publicaciones" => FALSE,
                    "jerarquia" => "D",
                    "privilegio_crear" => FALSE,
                    "privilegio_editar" => FALSE,
                    "privilegio_consulta" => FALSE,
                    "privilegio_elimina" => FALSE,
                    "privilegio_ver_docs" => FALSE,
                    "empresa" => $selectCompany,
                    "usuario" => $selectUser,
                  ));
                }
  
                $dataMensaje = array(
                  "status" => "success",
                  "code" => 200,
                  "message" => "Usuario registrado",
                  "token_user_new" => $tokenPersonal
                );
              } else {
                $deletePerosnal = DB::table("vhum_empleados_catalogo")->where(['empleado_token' => $tokenPersonal])->limit(1)->delete();
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Error en registro de usuario, verifique su información o comuniquese a soporte para más información"
                );
              }
            } else {
              DB::table("sos_personas")->where("token_personas",$tokenUserPersona)->limit(1)->delete();
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Error en registro de usuario, verifique su información o comuniquese a soporte para más información"
              );
            }
            
          }
        } else {
          $error_alerta = "";
          if (!$valida_user_paterno) {$error_alerta = "Error al ingresar apellido paterno";}
          if (!$valida_user_materno) {$error_alerta = "Error al ingresar apellido paterno";}
          if (!$valida_user_nombres) {$error_alerta = "Error al ingresar nombre(s)";}
          if (!$valida_user_email) {$error_alerta = "Error al ingresar email";}
          if (!$valida_user_empresas) {$error_alerta = "Error al obtener listado de empresas";}
          if (!$valida_user_area) {$error_alerta = "Error al obtener area asignada";}
          if (!$valida_user_cargo) {$error_alerta = "Error al obtener cargo asignado";}
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => $error_alerta . ", verifique su información o comuniquese a soporte para más información"
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

  public function catalogo_usuarios_clientes(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'empresa_cliente' => 'required|string',
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
        $empresa_cliente = $parametrosArray['empresa_cliente'];

        $listPersonal = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.personal", "people.id")
          ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
          ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "vhum_empleados_catalogo.usuario", "users.id")
          ->where([
            'emp.empresa_token' => $empresa_cliente,
          ])->get();

        //echo count($listPersonal);
        foreach ($listPersonal as $resPersonal) {
          //telefonos
          $arrayTelefonos = array();
          $listPhone = DB::table("sos_personas_telefonos AS tel")
            ->join("vhum_empleados_catalogo AS pers", "tel.personal", "pers.id")
            ->where([
              'pers.empleado_token' => $resPersonal->empleado_token,
              'tel.status_telefono' => TRUE
            ])->get();

          foreach ($listPhone as $vPhone) {
            $each = array(
              "token_telefono" => $vPhone->token_telefono,
              "icono" => $vPhone->icono,
              "etiqueta" => $vPhone->etiqueta,
              "telefono" => $JwtAuth->desencriptar($vPhone->telefono),
              //"telefono" => $JwtAuth->encriptar("5631863335"),
              "extension" => $vPhone->extension,
            );
            $arrayTelefonos[] = $each;
          }

          if ($JwtAuth->desencriptar($resPersonal->img_perfil) == 'default-profile.png') {
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resPersonal->img_perfil)));
          } else {
            $img_perfil =  $JwtAuth->encriptaBase64(Storage::path('public/root/' .
              $resPersonal->root_tkn . '/0004-vhm/catalogos/employees/' . $JwtAuth->desencriptar($resPersonal->img_perfil)
              . '/' . $JwtAuth->desencriptar($resPersonal->img_perfil) . '-profile.png'));
          }

          if ($resPersonal->codigo_acceso  == "" && $resPersonal->password  == "") {
            $hasPass = false;
          } else {
            $hasPass = true;
          }

          $arrayRespons = array(
            "token_personal" => $resPersonal->empleado_token,
            "folio" => $JwtAuth->generar($resPersonal->folio_pers),
            "paterno" => ucwords($JwtAuth->desencriptar($resPersonal->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($resPersonal->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($resPersonal->nombre)),
            "email" => $JwtAuth->desencriptar($resPersonal->email),
            "hasPass" => $hasPass,
            "imagen" => $img_perfil,
            "arrayTelefonos" => $arrayTelefonos,
          );
          $personal[] = $arrayRespons;
        }

        return response()->json([
          'personal' => $personal,
          'codigo' => 200,
          'status' => "success"
        ]);
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

  //accesos
  public function loginUsuarioMain(Request $request){
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

  //login_module_ssic
  public function loginModuleSSIC(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authSsic = new \App\Helpers\AuthSsic();
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "empresa_token" => "required|string",
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "firebase_token_movil" => "string",
        "firebase_token_web" => "string",
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $userEmpresa = $arrayParams["empresa_token"];
        $codAccessDecrypt = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $passDecrypt =  $JwtAuth->encriptar($arrayParams['password']);
        $firebase_token_movil = $arrayParams["firebase_token_movil"];
        $firebase_token_web = $arrayParams["firebase_token_web"];
        $dataMensaje = $authSsic->moduleSSICSignup($userEmpresa, $codAccessDecrypt, $passDecrypt, $firebase_token_movil, $firebase_token_web);
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

  //login_module_xml_download
  public function loginModuleXmlDownload(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authSsic = new \App\Helpers\AuthSsic();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'codigo_acceso' => 'required',
        'password' => 'required',
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
        //devolver token o datos
        $user_token = $arrayParams['user_token'];
        $empresa_token = $arrayParams['empresa_token'];
        //echo $user_token; 
        if (isset($user_token) && !empty($user_token) && $user_token == true) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';
          $dataMensaje = $authSsic->signupSsic($codAccessDecrypt, $passDecrypt, true);
        } else {
          $dataMensaje = $authSsic->signupSsic($codAccessDecrypt, $passDecrypt);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  //login_module_logistica
  public function loginModuleLogistica(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authLogistica = new \App\Helpers\AuthLogistica();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "firebase_token_movil" => "string",
        "firebase_token_web" => "string",
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
        $firebase_token_movil = $arrayParams["firebase_token_movil"];
        $firebase_token_web = $arrayParams["firebase_token_web"];
        $dataMensaje = $authLogistica->moduleLoginLogistica($codAccessDecrypt, $passDecrypt, $firebase_token_movil, $firebase_token_web);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  //login_module_compras
  public function loginModuleCompras(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authCompras = new \App\Helpers\AuthCompras();
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "empresa_token" => "required|string",
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "firebase_token_movil" => "string",
        "firebase_token_web" => "string",
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        $userEmpresa = $arrayParams["empresa_token"];
        $userUsername = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $userPassword =  $JwtAuth->encriptar($arrayParams['password']);
        $firebase_token_movil = $arrayParams["firebase_token_movil"];
        $firebase_token_web = $arrayParams["firebase_token_web"];
        $dataMensaje = $authCompras->moduleLoginCompras($userEmpresa, $userUsername, $userPassword, $firebase_token_movil, $firebase_token_web);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificadoo'
      );
    }
    return response()->json($dataMensaje, 200);
  }

  //login_module_gestion_proyectos
  public function loginModuleGestionProyectos(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authGP = new \App\Helpers\AuthGestionProyectos();
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "empresa_token" => "required|string",
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "firebase_token_movil" => "string",
        "firebase_token_web" => "string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $userEmpresa = $arrayParams["empresa_token"];
        $userUsername = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $userPassword =  $JwtAuth->encriptar($arrayParams['password']);
        $firebase_token_movil = $arrayParams["firebase_token_movil"];
        $firebase_token_web = $arrayParams["firebase_token_web"];
        $dataMensaje = $authGP->moduleLoginGP($userEmpresa, $userUsername, $userPassword, $firebase_token_movil, $firebase_token_web);
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

  public function updatePassModuleGestionProyectos(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authGP = new \App\Helpers\AuthGestionProyectos();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'user_token' => 'required',
        'passPrimera' => 'required',
        'passSegunda' => 'required',
        'passOlder' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado old',
          'errors' => $validate->errors()
        );
      } else {

        if (!empty($arrayParams['passPrimera']) && !empty($arrayParams['passSegunda']) && !empty($arrayParams['passOlder'])) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';

          $usuario = $JwtAuth->checkToken($arrayParams['user_token'], true);
          $passPrimera = $JwtAuth->encriptar($arrayParams['passPrimera']);
          $passSegunda = $JwtAuth->encriptar($arrayParams['passSegunda']);
          $passOlder =  $JwtAuth->encriptar($arrayParams['passOlder']);
          //devolver token o datos
          $dataMensaje = $authGP->signupNewPass($usuario->user_token, $passPrimera, $passSegunda, $passOlder);
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'contraseñas invalidas'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado 2'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  //terceros
  public function loginModuleTercerosAssociates(Request $request){
    $authJwt = new \App\Helpers\JwtAuth();
    $authAssociates = new \App\Helpers\AuthAssociates();
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "firebase_token_movil" => "string",
        "firebase_token_web" => "string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $codAccessDecrypt = $authJwt->encriptar($arrayParams['codigo_acceso']);
        $passDecrypt =  $authJwt->encriptar($arrayParams['password']);
        $firebase_token_movil = $arrayParams["firebase_token_movil"];
        $firebase_token_web = $arrayParams["firebase_token_web"];

        $dataMensaje = $authAssociates->loginSession($codAccessDecrypt, $passDecrypt, $firebase_token_movil, $firebase_token_web);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $authJwt->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function loginModuleTercerosCustomers(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'codigo_acceso' => 'required',
        'password' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        $codAccessDecrypt = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $passDecrypt =  $JwtAuth->encriptar($arrayParams['password']);
        //devolver token o datos
        $user_token = $arrayParams['user_token'];
        $empresa_token = $arrayParams['empresa_token'];
        //echo $user_token; 
        if (isset($user_token) && !empty($user_token) && $user_token == true) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';
          $dataMensaje = $JwtAuth->signupClientes($codAccessDecrypt, $passDecrypt, true);
        } else {
          $dataMensaje = $JwtAuth->signupClientes($codAccessDecrypt, $passDecrypt);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function loginModuleTercerosSuppliers(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'codigo_acceso' => 'required',
        'password' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        $codAccessDecrypt = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $passDecrypt =  $JwtAuth->encriptar($arrayParams['password']);
        //devolver token o datos
        $user_token = $arrayParams['user_token'];
        $empresa_token = $arrayParams['empresa_token'];
        //echo $user_token; 
        if (isset($user_token) && !empty($user_token) && $user_token == true) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';
          $dataMensaje = $JwtAuth->signupProveedores($codAccessDecrypt, $passDecrypt, true);
        } else {
          $dataMensaje = $JwtAuth->signupProveedores($codAccessDecrypt, $passDecrypt);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function loginModuleTercerosEmployees(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $AuthEmployees = new \App\Helpers\AuthEmployees();
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        "empresa_token" => "required|string",
        "codigo_acceso" => "required|string",
        "password" => "required|string",
        "firebase_token_movil" => "string",
        "firebase_token_web" => "string",
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        $userEmpresa = $arrayParams["empresa_token"];
        $codAccessDecrypt = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $passDecrypt =  $JwtAuth->encriptar($arrayParams['password']);
        $firebase_token_movil = $arrayParams["firebase_token_movil"];
        $firebase_token_web = $arrayParams["firebase_token_web"];

        $dataMensaje = $AuthEmployees->signupEmpleados($userEmpresa, $codAccessDecrypt, $passDecrypt, $firebase_token_movil, $firebase_token_web);
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

  //token_access
  public function get_access_token(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usersArray = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'empresa_token' => 'required|string',
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
        $empresa_token = $parametrosArray['empresa_token'];
        $token = array('user_token' => $usuario->user_token, 'empresa_token' => $empresa_token);
        $jwt = JWT::encode($token, "dtclavessecreto-9876986986986986s", 'HS256');
        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "large_token_access" => $jwt
        );
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
    $JwtAuth = new \JwtAuth();
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
            //echo $access_code." ".$password_code;
            //exit;

            //echo $vCred->usuario_token;
            //exit;
            
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

  public function guardarCodigoPass(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $arrayParams = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'codigo_acceso' => 'string',
        'email' => 'string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado old',
          'errors' => $validate->errors()
        );
      } else {
        $username = $JwtAuth->encriptar($arrayParams['codigo_acceso']);
        $email = $JwtAuth->encriptar($arrayParams['email']);
        $queryUser = DB::select('SELECT id,usuario_token FROM teci_usuarios_catalogo WHERE acceso_email = ?', [$email]);

        if (count($queryUser) != 0) {
          foreach ($queryUser as $vUser) {
            $count = 0;
            $random_text = "";
            $ramdom = mt_srand();
            while ($count < 10) {
              $rand_num = mt_rand(0, 100);
              $random_text = $random_text . $rand_num;
              $count++;
            }

            $random_code = substr($random_text, 0, 10);
            $insertPassReset = DB::table('teci_users_pass_reset')
              ->insert(
                array(
                  "usuario" => $vUser->id,
                  "codigo_verificacion" => $random_code,
                  "fecha_verificacion" => time(),
                )
              );

            if ($insertPassReset) {
              $dataMensaje = array(
                'status' => "success",
                'code' => 200,
                'message' => "código enviado",
                'random_text' => $random_code,
                'user_token_text' => $vUser->usuario_token,
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "código no enviado, intente nuevamente",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => "No tenemos registros de ningun usuario con el correo recibido",
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function verificarCodigoPass(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $arrayParams = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'user_token' => 'required|string',
        'code_verif' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado old',
          'errors' => $validate->errors()
        );
      } else {
        $user_token = $arrayParams['user_token'];
        $code_verif = $arrayParams['code_verif'];

        $selectUser = DB::select(
          "SELECT codigo_verificacion,fecha_verificacion FROM teci_users_pass_reset 
                    WHERE id = (SELECT MAX(upr.id) FROM teci_users_pass_reset AS upr JOIN teci_usuarios_catalogo AS users 
                        WHERE upr.usuario = users.id AND users.usuario_token = ?)",
          [$user_token]
        );

        if ($selectUser) {
          $status = "";
          $mensaje_resp = "";
          if ($selectUser[0]->codigo_verificacion == $code_verif) {
            $vigencia = $selectUser[0]->fecha_verificacion + 300;
            if ($vigencia > time()) {
              $status = "success";
              $resp_cod = "success";
              $mensaje_resp = "Código correcto";
            } else {
              $status = "error";
              $resp_cod = "code_expired";
              $mensaje_resp = "El código recibido ha vencido";
            }
          } else {
            $status = "error";
            $resp_cod = "code_invalid";
            $mensaje_resp = "El código recibido no coincide con el que se envio a su email";
          }

          $dataMensaje = array(
            'status' => $status,
            'code' => 200,
            'resp_cod' => $resp_cod,
            'message' => $mensaje_resp,
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'resp_cod' => 'null_code',
            'message' => "código no encontrado",
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'resp_cod' => 'none',
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function userUpdatePass(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authSsic = new \App\Helpers\AuthSsic();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'user_token' => 'required',
        'passPrimera' => 'required',
        'passSegunda' => 'required',
        'passOlder' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado old',
          'errors' => $validate->errors()
        );
      } else {

        if (!empty($arrayParams['passPrimera']) && !empty($arrayParams['passSegunda']) && !empty($arrayParams['passOlder'])) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';

          $usuario = $JwtAuth->checkToken($arrayParams['user_token'], true);
          $passPrimera = $JwtAuth->encriptar($arrayParams['passPrimera']);
          $passSegunda = $JwtAuth->encriptar($arrayParams['passSegunda']);
          $passOlder =  $JwtAuth->encriptar($arrayParams['passOlder']);
          //devolver token o datos
          $dataMensaje = $authSsic->signupNewPass($usuario->user_token, $passPrimera, $passSegunda, $passOlder);
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'contraseñas invalidas'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado 2'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  //token_access
  public function user_update_avatar(Request $request){
    $JwtAuth = new \JwtAuth();
    $imgProdCaarga = $request->file('avatar_user_img');
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usersArray = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
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
        //$empresa_token = $parametrosArray['empresa_token'];

        if (file_exists($request->file('avatar_user_img'))) {
          $userData = DB::table("vhum_empleados_catalogo AS pers")
            ->join("main_empresa_usuario AS empuser", "pers.id", "empuser.empleado")
            ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where(['emp.empresa_token' => $usuario->empresa_token, 'users.usuario_token' => $usuario->user_token])
            ->get();
          //return response()->json(['status' => 'error','code' => 200,'message' => 'true1']);
          foreach ($userData as $uData) {
            $new_image = $JwtAuth->generar($uData->folio_pers) . "-" . time();
            //return response()->json(['status' => 'error','code' => 200,'message' => 'true2'.$new_image]);

            $avatarUpdate = DB::table("sos_personas AS people")
              ->join("vhum_empleados_catalogo AS pers", "people.id", "pers.empleado_name")
              ->join("main_empresa_usuario AS empuser", "pers.id", "empuser.empleado")
              ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
              ->where(['emp.empresa_token' => $uData->empresa_token, 'users.usuario_token' => $uData->user_token])
              ->limit(1)->update(array('people.img_perfil' => $JwtAuth->encriptar($new_image)));

            if ($avatarUpdate) {
              $filepath = "main_users/" . $JwtAuth->generar($uData->folio_pers) . "-" . $uData->fecha_alta_pers;
              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }
              Storage::putFileAs("/public/root/" . $filepath, $request->file('avatar_user_img'), $new_image . '-profile.png');
              $avatar = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $new_image . '-profile.png'));
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "imagen de perfil actualizada", "avatar_response" => $avatar);
            }
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "error en imagen de perfil");
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

  //permisos_acceso_areas
  //solicitar_permisos
  public function userSolicitarPermisoJerarquia(Request $request){
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

  public function userSolicitarPermisoCrear(Request $request){
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
        'modulo' => 'required|string',
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
        $modulo = $parametrosArray["modulo"];
        $observaciones = "permiso de prueba";

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario->user_token, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          if ($modulo == "ingr") {
            $extend_modulo = "ingresos";
          } else if ($modulo == "eegr") {
            $extend_modulo = "egresos";
          } else if ($modulo == "fnzs") {
            $extend_modulo = "finanzas";
          } else if ($modulo == "vhum") {
            $extend_modulo = "valor humano";
          } else if ($modulo == "cont") {
            $extend_modulo = "contabilidad";
          } else if ($modulo == "teci") {
            $extend_modulo = "tecnologías de la información";
          }

          //da_te_default_timezone_set($vUser->zona_horaria);
          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario_empresa]);

          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people JOIN vhum_empleados_catalogo AS pers 
                            JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario_user]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
          $folioSistema = DB::select("SELECT max(folio_permiso) AS folio_permiso FROM teci_solicitudes_permisos AS perm_soli 
                            JOIN main_empresas AS emp WHERE perm_soli.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

          if (count($folioSistema) == 0) {
            $sql_folio = 1;
          } else {
            $sql_folio = end($folioSistema)->folio_permiso + 1;
          }

          $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $modulo . $observaciones . time() - 500);

          $insertSoliPerm = DB::table('teci_solicitudes_permisos')
            ->insert(
              array(
                "token_permiso" => $token_auth,
                "folio_permiso" => $sql_folio,
                "fecha_permiso" => time(),
                "user_emp" => end($select_empresa)->id,
                "user_user" => end($select_usuario)->id,
                "modulo" => $modulo,
                "permiso" => "crear",
                "observaciones" => $JwtAuth->encriptar($observaciones),
                "receptor" => 3,
                "solicitud_perm_status" => TRUE,
              )
            );

          if ($insertSoliPerm) {
            $titulo_ = "Permisos para usuarios";
            $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado permiso para registrar información en el módulo de " . $extend_modulo;
            $JwtAuth->notificacionPushDevices($JwtAuth->userAdminMain(), $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio));
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
            );
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

  public function userSolicitarPermisoEditar(Request $request){
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
        'modulo' => 'required|string',
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
        $modulo = $parametrosArray["modulo"];
        $observaciones = "permiso de prueba";

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario->user_token, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          if ($modulo == "ingr") {
            $extend_modulo = "ingresos";
          } else if ($modulo == "eegr") {
            $extend_modulo = "egresos";
          } else if ($modulo == "fnzs") {
            $extend_modulo = "finanzas";
          } else if ($modulo == "vhum") {
            $extend_modulo = "valor humano";
          } else if ($modulo == "cont") {
            $extend_modulo = "contabilidad";
          } else if ($modulo == "teci") {
            $extend_modulo = "tecnologías de la información";
          }

          //da_te_default_timezone_set($vUser->zona_horaria);
          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario_empresa]);

          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people JOIN vhum_empleados_catalogo AS pers 
                            JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario_user]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
          $folioSistema = DB::select("SELECT max(folio_permiso) AS folio_permiso FROM teci_solicitudes_permisos AS perm_soli 
                            JOIN main_empresas AS emp WHERE perm_soli.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

          if (count($folioSistema) == 0) {
            $sql_folio = 1;
          } else {
            $sql_folio = end($folioSistema)->folio_permiso + 1;
          }

          $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $modulo . $observaciones . time() - 500);

          $insertSoliPerm = DB::table('teci_solicitudes_permisos')
            ->insert(
              array(
                "token_permiso" => $token_auth,
                "folio_permiso" => $sql_folio,
                "fecha_permiso" => time(),
                "user_emp" => end($select_empresa)->id,
                "user_user" => end($select_usuario)->id,
                "modulo" => $modulo,
                "permiso" => "editar",
                "observaciones" => $JwtAuth->encriptar($observaciones),
                "receptor" => 3,
                "solicitud_perm_status" => TRUE,
              )
            );

          if ($insertSoliPerm) {
            $titulo_ = "Permisos para usuarios";
            $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado permiso para editar o actualizar información en el módulo de " . $extend_modulo;
            $JwtAuth->notificacionPushDevices($JwtAuth->userAdminMain(), $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio));
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
            );
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

  public function userSolicitarPermisoConsultar(Request $request){
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
        'modulo' => 'required|string',
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
        $modulo = $parametrosArray["modulo"];
        $observaciones = "permiso de prueba";

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario->user_token, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          if ($modulo == "ingr") {
            $extend_modulo = "ingresos";
          } else if ($modulo == "eegr") {
            $extend_modulo = "egresos";
          } else if ($modulo == "fnzs") {
            $extend_modulo = "finanzas";
          } else if ($modulo == "vhum") {
            $extend_modulo = "valor humano";
          } else if ($modulo == "cont") {
            $extend_modulo = "contabilidad";
          } else if ($modulo == "teci") {
            $extend_modulo = "tecnologías de la información";
          }

          //da_te_default_timezone_set($vUser->zona_horaria);
          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario_empresa]);

          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people JOIN vhum_empleados_catalogo AS pers 
                            JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario_user]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
          $folioSistema = DB::select("SELECT max(perm_soli.folio_permiso) AS folio_permiso FROM teci_solicitudes_permisos AS perm_soli 
                            JOIN main_empresas AS emp WHERE perm_soli.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

          if (count($folioSistema) == 0) {
            $sql_folio = 1;
          } else {
            $sql_folio = end($folioSistema)->folio_permiso + 1;
          }

          $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $modulo . $observaciones . time() - 500);

          $insertSoliPerm = DB::table('teci_solicitudes_permisos')
            ->insert(
              array(
                "token_permiso" => $token_auth,
                "folio_permiso" => $sql_folio,
                "fecha_permiso" => time(),
                "user_emp" => end($select_empresa)->id,
                "user_user" => end($select_usuario)->id,
                "modulo" => $modulo,
                "permiso" => "consulta",
                "observaciones" => $JwtAuth->encriptar($observaciones),
                "receptor" => 3,
                "solicitud_perm_status" => TRUE,
              )
            );

          if ($insertSoliPerm) {
            $titulo_ = "Permisos para usuarios";
            $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado permiso de consulta en el módulo de " . $extend_modulo;
            $JwtAuth->notificacionPushDevices($JwtAuth->userAdminMain(), $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio));
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
            );
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

  public function userSolicitarPermisoEliminar(Request $request){
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
        'modulo' => 'required|string',
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
        $modulo = $parametrosArray["modulo"];
        $observaciones = "permiso de prueba";

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario->user_token, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          if ($modulo == "ingr") {
            $extend_modulo = "ingresos";
          } else if ($modulo == "eegr") {
            $extend_modulo = "egresos";
          } else if ($modulo == "fnzs") {
            $extend_modulo = "finanzas";
          } else if ($modulo == "vhum") {
            $extend_modulo = "valor humano";
          } else if ($modulo == "cont") {
            $extend_modulo = "contabilidad";
          } else if ($modulo == "teci") {
            $extend_modulo = "tecnologías de la información";
          }

          //da_te_default_timezone_set($vUser->zona_horaria);
          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario_empresa]);

          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people JOIN vhum_empleados_catalogo AS pers 
                            JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario_user]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
          $folioSistema = DB::select("SELECT max(folio_permiso) AS folio_permiso FROM teci_solicitudes_permisos AS perm_soli 
                            JOIN main_empresas AS emp WHERE perm_soli.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

          if (count($folioSistema) == 0) {
            $sql_folio = 1;
          } else {
            $sql_folio = end($folioSistema)->folio_permiso + 1;
          }

          $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $modulo . $observaciones . time() - 500);

          $insertSoliPerm = DB::table('teci_solicitudes_permisos')
            ->insert(
              array(
                "token_permiso" => $token_auth,
                "folio_permiso" => $sql_folio,
                "fecha_permiso" => time(),
                "user_emp" => end($select_empresa)->id,
                "user_user" => end($select_usuario)->id,
                "modulo" => $modulo,
                "permiso" => "eliminar",
                "observaciones" => $JwtAuth->encriptar($observaciones),
                "receptor" => 3,
                "solicitud_perm_status" => TRUE,
              )
            );

          if ($insertSoliPerm) {
            $titulo_ = "Permisos para usuarios";
            $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado permiso para editar o eliminar información en el módulo de " . $extend_modulo;
            $JwtAuth->notificacionPushDevices($JwtAuth->userAdminMain(), $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio));
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
            );
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

  public function userSolicitarPermisoVerDocs(Request $request){
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
        'modulo' => 'required|string',
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
        $modulo = $parametrosArray["modulo"];
        $observaciones = "permiso de prueba";

        $data_user = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where(["users.usuario_token" => $usuario->user_token, "emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($data_user as $vUser) {
          if ($modulo == "ingr") {
            $extend_modulo = "ingresos";
          } else if ($modulo == "eegr") {
            $extend_modulo = "egresos";
          } else if ($modulo == "fnzs") {
            $extend_modulo = "finanzas";
          } else if ($modulo == "vhum") {
            $extend_modulo = "valor humano";
          } else if ($modulo == "cont") {
            $extend_modulo = "contabilidad";
          } else if ($modulo == "teci") {
            $extend_modulo = "tecnologías de la información";
          }

          //da_te_default_timezone_set($vUser->zona_horaria);
          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario_empresa]);

          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people JOIN vhum_empleados_catalogo AS pers 
                            JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario_user]);

          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
          $folioSistema = DB::select("SELECT max(folio_permiso) AS folio_permiso FROM teci_solicitudes_permisos AS perm_soli 
                            JOIN main_empresas AS emp WHERE perm_soli.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

          if (count($folioSistema) == 0) {
            $sql_folio = 1;
          } else {
            $sql_folio = end($folioSistema)->folio_permiso + 1;
          }

          $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $modulo . $observaciones . time() - 500);

          $insertSoliPerm = DB::table('teci_solicitudes_permisos')
            ->insert(
              array(
                "token_permiso" => $token_auth,
                "folio_permiso" => $sql_folio,
                "fecha_permiso" => time(),
                "user_emp" => end($select_empresa)->id,
                "user_user" => end($select_usuario)->id,
                "modulo" => $modulo,
                "permiso" => "ver_docs",
                "observaciones" => $JwtAuth->encriptar($observaciones),
                "receptor" => 3,
                "solicitud_perm_status" => TRUE,
              )
            );

          if ($insertSoliPerm) {
            $titulo_ = "Permisos para usuarios";
            $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado permiso para ver y descargar documentos en el módulo de " . $extend_modulo;
            $JwtAuth->notificacionPushDevices($JwtAuth->userAdminMain(), $titulo_, $mensaje_user);
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio),);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
            );
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

  //ingresos
  public function userAccesoModuloSsic(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("inside_ssic" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al SSIC de tu usuario ha sido modificado";
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

  public function userAccesoModuloDescargaXml(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_descarga_xml" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al Módulo de descarga de xml de tu usuario ha sido modificado";
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

  public function userAccesoModuloLogistica(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_logistica" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al Módulo de logística de tu usuario ha sido modificado";
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

  public function userAccesoModuloCompras(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_compras" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al Módulo de Cotizaciones y Requisiciones de tu usuario ha sido modificado";
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

  public function userAccesoModuloProyectos(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_proyectos" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso al Módulo de proyectos de tu usuario ha sido modificado";
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

  public function userAccesoModuloTerceros(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_terceros" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso a todos los Módulos de terceros de tu usuario ha sido modificado";
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

  public function userAccesoModuloTercerosAssociates(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_terceros_associates" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso a todos los Módulos de asociados (terceros) de tu usuario ha sido modificado";
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

  public function userAccesoModuloTercerosClientes(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_terceros_clientes" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso a todos los Módulos de clientes (terceros) de tu usuario ha sido modificado";
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

  public function userAccesoModuloTercerosProveedores(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_terceros_proveedores" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso a todos los Módulos de proveedores (terceros) de tu usuario ha sido modificado";
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

  public function userAccesoModuloTercerosEmpleados(Request $request){
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

        $data_user = DB::table("teci_usuarios_catalogo")->where(["usuario_token" => $usuario_user])->get();
        foreach ($data_user as $vUser) {
          $updateAccesoMenu = DB::table("teci_usuarios_catalogo")->where(["user_token" => $vUser->user_token])->limit(1)->update(array("outside_terceros_empleados" => $acceso_menu));

          if ($updateAccesoMenu) {
            $titulo_ = "Permisos de para usuarios";
            $mensaje_user = "El permiso de acceso a todos los Módulos de empleados (terceros) de tu usuario ha sido modificado";
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

  //Apps complementarias
  //Soporte
  //Comunicación
  //Publicaciones

  //codigos_olvidados
  public function sesionReload(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'sos_tokens' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        $user_token = $arrayParams['sos_tokens'];
        $dataMensaje = $JwtAuth->signupReload($user_token);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function sesionPermissionUser(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'codigo_acceso' => 'required',
        'password' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        //cifrar contraseña
        //$key ='textoencriptado';
        //$iv = "1234567812345678";
        //$encriptaCodAccess = openssl_encrypt($parametros->codigo_acceso,"aes-256-cbc",$key,0,$iv);
        //$codAccessDecrypt = base64_encode($encriptaCodAccess."::".$iv);
        $codAccessDecrypt = $JwtAuth->encriptar($parametros->codigo_acceso);
        $passDecrypt =  $JwtAuth->encriptar($parametros->password);
        //devolver token o datos
        if (!empty($parametros->user_token) && ! !empty($parametros->empresa_token)) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';
          $dataMensaje = $JwtAuth->signup($codAccessDecrypt, $passDecrypt, true);
        } else {
          $dataMensaje = $JwtAuth->signup($codAccessDecrypt, $passDecrypt);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function sesionSecondLoginAccess(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    //recibir los mpost
    $jsonLogin = $request->input('json');
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'user_token' => 'required|string',
        'accessCode' => 'required|string',
        'area' => 'required|string',
        'subarea1' => 'required|string',
        'subarea2' => 'required|string',
        'folio_relacionado' => 'required|string',
        'actividad' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $passDecrypt =  $JwtAuth->encriptar($arrayParams['accessCode']);
        $usuario = $JwtAuth->checkToken($arrayParams['user_token'], true);
        //devolver token o datos
        if (!empty($usuario->user_token) && !empty($usuario->empresa_token)) {
          $dataMensaje = $JwtAuth->signupAcessEnableForce(
            $arrayParams['area'],
            $arrayParams['subarea2'],
            $arrayParams['subarea1'],
            $arrayParams['folio_relacionado'],
            $usuario->empresa_token,
            $usuario->user_token,
            $passDecrypt,
            $arrayParams['actividad']
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'usuario no identificado'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function resetPassFunction(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $authSsic = new \App\Helpers\AuthSsic();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $arrayParams = json_decode($jsonLogin, true);
    //return $arrayParams;
    //die();
    //validar los datos
    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'user_token' => 'required',
        'passPrimera' => 'required',
        'passSegunda' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado old',
          'errors' => $validate->errors()
        );
      } else {

        if (!empty($arrayParams['passPrimera']) && !empty($arrayParams['passSegunda'])) { // si existe token de identificacion envia losa datos decodificados
          //$dataMensaje = 'holaaaa $dataMensaje = ';

          $usuario = $arrayParams['user_token'];
          $passPrimera = $JwtAuth->encriptar($arrayParams['passPrimera']);
          $passSegunda = $JwtAuth->encriptar($arrayParams['passSegunda']);
          //devolver token o datos
          $dataMensaje = $authSsic->resetPassFunction($usuario, $passPrimera, $passSegunda);
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'contraseñas invalidas'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado 2'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function updateLanguage(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $arrayParams = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($arrayParams)) {
      $validate = Validator($arrayParams, [
        'user_token' => 'required|string',
        'lenguaje' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado old',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($arrayParams['user_token'], true);
        $len_guaje = $arrayParams['lenguaje'];

        if ($len_guaje == "es") {
          $titulo_success = "idioma actualizado";
          $titulo_error = "idioma no actualizado";
        } else {
          $titulo_success = "updated language";
          $titulo_error = "language not updated";
        }

        $updateLangQuery = DB::table('settings AS conf')
          ->join("vhum_empleados_catalogo AS pers", "conf.personal", "pers.id")
          ->join("main_empresa_usuario AS empuser", "pers.id", "empuser.empleado")
          ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->join("usuarios AS users", "pers.usuario", "users.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(
            array(
              'conf.lenguaje' => $len_guaje,
            )
          );

        if ($updateLangQuery) {
          $dataMensaje = array(
            'status' => "success",
            'code' => 200,
            'message' => $titulo_success,
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => $titulo_error,
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function registraDevice(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    //recibir los mpost
    $jsonLogin = $request->input('json', null);
    $parametros = json_decode($jsonLogin);
    $parametrosArray = json_decode($jsonLogin, true);
    $arrayNotificaciones = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = Validator($parametrosArray, [
        'user_token' => 'required|string',
        'token_device' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'usuario no identificado',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_device = $parametrosArray['token_device'];

        $notifDelete = DB::table("usuarios AS users")
          ->join("main_empresas AS emp", "users.empresa", "=", "emp.id")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(
            array(
              'users.token_device' => $token_device,
            )
          );

        if ($notifDelete) {
          $dataMensaje = array(
            'status' => "success",
            'code' => 200,
            'message' => 'token registrado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'token no registrado'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'usuario no identificado'
      );
    }
    //return $JwtAuth->signup($email,$passDecrypt);
    return response()->json($dataMensaje, 200);
  }

  public function firebaseCodeUpdate(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $personal = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'firebase_codigo' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $firebase_codigo = $parametrosArray["firebase_codigo"];

        $updateFireUser = DB::table("teci_usuarios_catalogo AS users")
          ->join("main_empresas AS emp", "users.empresa", "emp.id")
          ->where([
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->limit(1)->update(
            array(
              "users.token_dispositivo_firebase" => $firebase_codigo,
            )
          );

        if ($updateFireUser) {
          $dataMensaje = array(
            'status' => "success",
            'code' => 200,
            'message' => 'Codigo de dispositivo actualizado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Codigo de dispositivo no actualizado'
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
}
