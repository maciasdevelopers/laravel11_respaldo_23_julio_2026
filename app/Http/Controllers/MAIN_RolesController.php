<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\PermisosModelo;
use App\Models\PermisoLoginModelo;
use App\Models\User;
//session_start();
use Session;

class MAIN_RolesController extends Controller{
  public function permisoAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $empresa = $token_session->empresa_token;
        $usuario = $token_session->user_token;

        $queryPermLog = DB::select(
          "SELECT users.id as iduser,users.login_permission AS login FROM teci_usuarios_catalogo AS users JOIN main_empresas AS emp 
                    WHERE emp.empresa_token = ? AND emp.id = users.empresa AND users.usuario_token = ?",
          [$empresa, $usuario]
        );
        //echo $queryPermLog[0]->iduser;exit;
        if ($queryPermLog[0]->login == TRUE) {
          $loginPerM = true;
          $queryPermMenu = DB::select("SELECT permenu.token_permisos_menu,permiso.acceso 
                        FROM teci_permisos_usuario AS permiso JOIN teci_permisos_menu AS permenu JOIN main_empresas AS emp
                        WHERE permiso.menu = permenu.id AND permiso.empresa = emp.id AND emp.empresa_token = ?
                        AND (
                            permiso.clasificacion = 1 AND permiso.uprincipal IS NOT NULL AND permiso.empleado IS NULL 
                            AND permiso.uprincipal = (SELECT id FROM teci_usuarios_catalogo WHERE user_token = ?)
                        ) 
                        OR permiso.menu = permenu.id AND permiso.empresa = emp.id AND emp.empresa_token = ?
                        AND (
                            permiso.clasificacion = 2 AND permiso.empleado IS NOT NULL AND permiso.uprincipal IS NULL 
                            AND permiso.empleado = (
                                SELECT pers.id FROM vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                                WHERE pers.usuario = users.id AND users.usuario_token = ?
                            )
                        )", [$empresa, $usuario, $empresa, $usuario]);

          foreach ($queryPermMenu as $valPerMenu) {
            //echo $valPerMenu->token_permisos_menu." ".$valPerMenu->acceso." ";
            if ($valPerMenu->token_permisos_menu == 'VUVEREt3aUd0WXVDZitjNmt6SGhIWnVmMC94dllvRHM3bVorMkNmZ3VIcWFKYmk5cGp6Yzg0MlA0UTZLRTFsTzo6MTIzNDU2NzgxMjM0NTY3OA==') {
              $menuData = 'ingtkndat';
            } else if ($valPerMenu->token_permisos_menu == 'bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==') {
              $menuData = 'egrtkndat';
            } else if ($valPerMenu->token_permisos_menu == 'a25CTllTTHIzQUxqMytVREI2SzQ2cmNwKy9CQ0NuUEVoUFNXVnpOMld4bz06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
              $menuData = 'testkndat';
            } else if ($valPerMenu->token_permisos_menu == 'MmlEQzEvYnJ6cFNXTEZ2N3FXbVlLUXVQMEp4OGJEa2h6REo5ak93TlhNdz06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
              $menuData = 'valtkndat';
            } else if ($valPerMenu->token_permisos_menu == 'ek5vaHgrODJIVEtJcnlGbWxMVWVQSVpxQjdoaEttS2ZrWFlOOU44dXhSND06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
              $menuData = 'contkndat';
            } else if ($valPerMenu->token_permisos_menu == 'MHZzVlhpRHQyaldUdXkvNmY5S0FQU3A5OHRIZlF6ellXZGRsWHNpMUtNZVFMMkJZVDNVU0FPV2xRVUU3ZFBoQjo6MTIzNDU2NzgxMjM0NTY3OA==') {
              $menuData = 'tinftkndat';
            }

            if ($valPerMenu->acceso == TRUE) {
              $accesoStatus = true;
            } else {
              $accesoStatus = false;
            }

            $arraYnterno = array(
              "menuData" => $menuData,
              "accesoData" => $accesoStatus,
            );
            $arrayMenuPerm[] = $arraYnterno;
          }
        } else {
          $loginPerM = false;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'statusPerm' => $loginPerM,
          'arrayMenuPerm' => $arrayMenuPerm,
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

  public function allUserConfigSSIC(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $empresa = $token_session->empresa_token;
        $usuario = $token_session->user_token;

        $queryPermLog = DB::select(
          "SELECT users.id as iduser,users.login_permission AS login FROM teci_usuarios_catalogo AS users JOIN main_empresas AS emp 
                    WHERE emp.empresa_token = ? AND emp.id = users.empresa AND users.usuario_token = ?",
          [$empresa, $usuario]
        );

        $num_total_notif = 0;
        if ($queryPermLog[0]->login == TRUE) {
          $loginPerM = true;

          $alertaList = DB::select(
            "SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp
                        ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                        INNER JOIN teci_usuarios_catalogo AS users ON receptor.usuario = users.id 
                        WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.empresa_token = ? 
                        AND users.usuario_token = ?
                        AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                            AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                            AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                            AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                            OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                            AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                            AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                            AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
            [$empresa, $usuario]
          );

          $num_total_notif = count($alertaList);

          $queryPermMenu = DB::select("SELECT oldperm.* FROM teci_permisos_usuario_old AS oldperm
                        JOIN main_empresas AS emp JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users
                        WHERE oldperm.empresa = emp.id AND emp.empresa_token = ?
                        AND oldperm.usuario = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
          //echo count($queryPermMenu);
          foreach ($queryPermMenu as $valPerMenu) {
            //echo $valPerMenu->token_permisos_menu." ".$valPerMenu->acceso." ";
            $menuData = 'tinftkndat';
            /*if ($valPerMenu->token_permisos_menu == 'VUVEREt3aUd0WXVDZitjNmt6SGhIWnVmMC94dllvRHM3bVorMkNmZ3VIcWFKYmk5cGp6Yzg0MlA0UTZLRTFsTzo6MTIzNDU2NzgxMjM0NTY3OA=='){ 
                            $menuData = 'ingtkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA=='){ 
                            $menuData = 'egrtkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'a25CTllTTHIzQUxqMytVREI2SzQ2cmNwKy9CQ0NuUEVoUFNXVnpOMld4bz06OjEyMzQ1Njc4MTIzNDU2Nzg='){ 
                            $menuData = 'testkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'MmlEQzEvYnJ6cFNXTEZ2N3FXbVlLUXVQMEp4OGJEa2h6REo5ak93TlhNdz06OjEyMzQ1Njc4MTIzNDU2Nzg='){ 
                            $menuData = 'valtkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'ek5vaHgrODJIVEtJcnlGbWxMVWVQSVpxQjdoaEttS2ZrWFlOOU44dXhSND06OjEyMzQ1Njc4MTIzNDU2Nzg='){ 
                            $menuData = 'contkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'MHZzVlhpRHQyaldUdXkvNmY5S0FQU3A5OHRIZlF6ellXZGRsWHNpMUtNZVFMMkJZVDNVU0FPV2xRVUU3ZFBoQjo6MTIzNDU2NzgxMjM0NTY3OA=='){ 
                            $menuData = 'tinftkndat';
                        }*/

            $css_ingr_cpc = 'ingtkndat';
            $css_eegr_cpp = 'egrtkndat';
            $css_fnzs = 'testkndat';
            $css_vhum = 'valtkndat';
            $css_cont = 'contkndat';
            $css_teci = 'tinftkndat';
            $css_juri = 'juritkndat';

            if ($valPerMenu->ingr_cpc == TRUE) {
              $acceso_ingr_cpc = true;
            } else {
              $acceso_ingr_cpc = false;
            }

            if ($valPerMenu->eegr_cpp == TRUE) {
              $acceso_eegr_cpp = true;
            } else {
              $acceso_eegr_cpp = false;
            }


            if ($valPerMenu->fnzs == TRUE) {
              $acceso_fnzs = true;
            } else {
              $acceso_fnzs = false;
            }

            if ($valPerMenu->vhum == TRUE) {
              $acceso_vhum = true;
            } else {
              $acceso_vhum = false;
            }

            if ($valPerMenu->cont == TRUE) {
              $acceso_cont = true;
            } else {
              $acceso_cont = false;
            }

            if ($valPerMenu->teci == TRUE) {
              $acceso_teci = true;
            } else {
              $acceso_teci = false;
            }

            if ($valPerMenu->juri == TRUE) {
              $acceso_juri = true;
            } else {
              $acceso_juri = false;
            }

            $arraYnterno = array(
              //cpc
              "css_ingr_cpc" => $css_ingr_cpc,
              "acceso_ingr_cpc" => $acceso_ingr_cpc,
              //cpp
              "css_eegr_cpp" => $css_eegr_cpp,
              "acceso_eegr_cpp" => $acceso_eegr_cpp,
              //fnzs
              "css_fnzs" => $css_fnzs,
              "acceso_fnzs" => $acceso_fnzs,
              //vhum
              "css_vhum" => $css_vhum,
              "acceso_vhum" => $acceso_vhum,
              //cont
              "css_cont" => $css_cont,
              "acceso_cont" => $acceso_cont,
              //teci
              "css_teci" => $css_teci,
              "acceso_teci" => $acceso_teci,
              //juri
              "css_juri" => $css_juri,
              "acceso_juri" => $acceso_juri,
            );
            $arrayMenuPerm[] = $arraYnterno;
          }
        } else {
          $loginPerM = false;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'statusPerm' => $loginPerM,
          'arrayMenuPerm' => $arrayMenuPerm,
          'num_total_notif' => $num_total_notif
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

  public function newPermisoAcceso(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Usuario incorrecto",
          "errors" => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        //echo $token_session->empresa_token;
        /*$queryPermLog = DB::select("SELECT users.id as iduser,users.login_permission AS login,conf.privilegio_crear,conf.privilegio_editar,conf.privilegio_consulta,
                    conf.privilegio_elimina,conf.privilegio_ver_docs,pers.jerarquia_main FROM teci_usuarios_catalogo AS users 
                    JOIN vhum_personal AS pers 
                    JOIN teci_user_settings AS conf 
                    JOIN main_empresas AS emp
                    WHERE users.usuario_token = ?   
                    AND users.id = conf.usuario
                    AND users.empleado = pers.id
                    AND users.empresa = emp.id
                    AND emp.empresa_token = ?",[$token_session->user_token,$token_session->empresa_token]);*/

        $queryPermLog = DB::select("SELECT users.id as iduser,users.login_permission AS login,conf.privilegio_crear,conf.privilegio_editar,conf.privilegio_consulta,
                    conf.privilegio_elimina,conf.privilegio_ver_docs,users.jerarquia_main 
                    FROM teci_user_settings AS conf
                    JOIN teci_usuarios_catalogo AS users
                    JOIN main_empresa_usuario AS empuser
                    JOIN main_empresas AS emp
                    WHERE conf.usuario = users.id  
                    AND users.usuario_token = ?
                    AND users.id = empuser.usuario
                    AND empuser.empresa = emp.id
                    AND emp.empresa_token = ?", [$token_session->user_token, $token_session->empresa_token]);
        //echo count($queryPermLog);
        if (count($queryPermLog) == 1) {
          $main_jerarquia = null;
          $main_privilegio_crear = false;
          $main_privilegio_editar = false;
          $main_privilegio_consulta = false;
          $main_privilegio_elimina = false;
          $main_privilegio_ver_docs = false;

          foreach ($queryPermLog as $conf) {
            if ($conf->login == TRUE) {
              $loginPerM = true;
            } else {
              $loginPerM = false;
            }

            $main_jerarquia = $conf->jerarquia_main;
            if ($conf->privilegio_crear == TRUE) {
              $main_privilegio_crear = true;
            }
            if ($conf->privilegio_editar == TRUE) {
              $main_privilegio_editar = true;
            }
            if ($conf->privilegio_consulta == TRUE) {
              $main_privilegio_consulta = true;
            }
            if ($conf->privilegio_elimina == TRUE) {
              $main_privilegio_elimina = true;
            }
            if ($conf->privilegio_ver_docs == TRUE) {
              $main_privilegio_ver_docs = true;
            }

            $queryPermMenu = DB::select("SELECT oldperm.* FROM teci_permisos_usuario_old AS oldperm JOIN main_empresas AS emp  
                            JOIN teci_usuarios_catalogo AS users WHERE oldperm.empresa = emp.id AND emp.empresa_token = ? 
                            AND oldperm.usuario = users.id AND users.usuario_token = ?", [$token_session->empresa_token, $token_session->user_token]);
            //echo count($queryPermMenu);
            foreach ($queryPermMenu as $valPerMenu) {
              $menuData = 'tinftkndat';
              $css_ingr_cpc = 'ingtkndat';
              $css_eegr_cpp = 'egrtkndat';
              $css_fnzs = 'testkndat';
              $css_vhum = 'valtkndat';
              $css_cont = 'contkndat';
              $css_teci = 'tinftkndat';
              $css_juri = 'juritkndat';

              $acceso_ingr_cpc = $valPerMenu->ingr_cpc == TRUE ? true : false;
              $acceso_eegr_cpp = $valPerMenu->eegr_cpp == TRUE ? true : false;
              $acceso_fnzs = $valPerMenu->fnzs == TRUE ? true : false;
              $acceso_vhum = $valPerMenu->vhum == TRUE ? true : false;
              $acceso_cont = $valPerMenu->cont == TRUE ? true : false;
              $acceso_teci = $valPerMenu->teci == TRUE ? true : false;
              $acceso_juri = $valPerMenu->juri == TRUE ? true : false;

              $arraYnterno = array(
                //cpc
                "css_ingr_cpc" => $css_ingr_cpc,
                "acceso_ingr_cpc" => $acceso_ingr_cpc,
                //cpp
                "css_eegr_cpp" => $css_eegr_cpp,
                "acceso_eegr_cpp" => $acceso_eegr_cpp,
                //fnzs
                "css_fnzs" => $css_fnzs,
                "acceso_fnzs" => $acceso_fnzs,
                //vhum
                "css_vhum" => $css_vhum,
                "acceso_vhum" => $acceso_vhum,
                //cont
                "css_cont" => $css_cont,
                "acceso_cont" => $acceso_cont,
                //teci
                "css_teci" => $css_teci,
                "acceso_teci" => $acceso_teci,
                //juri
                "css_juri" => $css_juri,
                "acceso_juri" => $acceso_juri,
              );
              $arrayMenuPerm[] = $arraYnterno;
            }

            $ingr_jerarquia = "D";
            $ingr_privilegio_crear = false;
            $ingr_privilegio_editar = false;
            $ingr_privilegio_consulta = false;
            $ingr_privilegio_elimina = false;
            $ingr_privilegio_ver_docs = false;

            $usuario_conf_ingr = DB::table("configuracion_systema_ingr AS conf_ingr")
              ->join("main_empresas AS emp", "conf_ingr.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "=", "users.id")
              ->where(["emp.empresa_token" => $token_session->empresa_token, "users.usuario_token" => $token_session->user_token])->get();
            foreach ($usuario_conf_ingr as $cINGR) {
              $ingr_jerarquia = $cINGR->jerarquia;
              if ($cINGR->privilegio_crear == TRUE) $ingr_privilegio_crear = true;
              if ($cINGR->privilegio_editar == TRUE) $ingr_privilegio_editar = true;
              if ($cINGR->privilegio_consulta == TRUE) $ingr_privilegio_consulta = true;
              if ($cINGR->privilegio_elimina == TRUE) $ingr_privilegio_elimina = true;
              if ($cINGR->privilegio_ver_docs == TRUE) $ingr_privilegio_ver_docs = true;
            }

            $eegr_jerarquia = "D";
            $eegr_privilegio_crear = false;
            $eegr_privilegio_editar = false;
            $eegr_privilegio_consulta = false;
            $eegr_privilegio_elimina = false;
            $eegr_privilegio_ver_docs = false;

            $usuario_conf_eegr = DB::table("configuracion_systema_eegr AS conf_eegr")
              ->join("main_empresas AS emp", "conf_eegr.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "conf_eegr.usuario", "=", "users.id")
              ->where(["emp.empresa_token" => $token_session->empresa_token, "users.usuario_token" => $token_session->user_token])->get();
            foreach ($usuario_conf_eegr as $cEEGR) {
              $eegr_jerarquia = $cEEGR->jerarquia;
              if ($cEEGR->privilegio_crear == TRUE) $eegr_privilegio_crear = true;
              if ($cEEGR->privilegio_editar == TRUE) $eegr_privilegio_editar = true;
              if ($cEEGR->privilegio_consulta == TRUE) $eegr_privilegio_consulta = true;
              if ($cEEGR->privilegio_elimina == TRUE) $eegr_privilegio_elimina = true;
              if ($cEEGR->privilegio_ver_docs == TRUE) $eegr_privilegio_ver_docs = true;
            }

            $fnzs_jerarquia = "D";
            $fnzs_privilegio_crear = false;
            $fnzs_privilegio_editar = false;
            $fnzs_privilegio_consulta = false;
            $fnzs_privilegio_elimina = false;
            $fnzs_privilegio_ver_docs = false;

            $usuario_conf_fnzs = DB::table("configuracion_systema_fnzs AS conf_fnzs")
              ->join("main_empresas AS emp", "conf_fnzs.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "=", "users.id")
              ->where(["emp.empresa_token" => $token_session->empresa_token, "users.usuario_token" => $token_session->user_token])->get();
            foreach ($usuario_conf_fnzs as $cFNZS) {
              $fnzs_jerarquia = $cFNZS->jerarquia;
              if ($cFNZS->privilegio_crear == TRUE) $fnzs_privilegio_crear = true;
              if ($cFNZS->privilegio_editar == TRUE) $fnzs_privilegio_editar = true;
              if ($cFNZS->privilegio_consulta == TRUE) $fnzs_privilegio_consulta = true;
              if ($cFNZS->privilegio_elimina == TRUE) $fnzs_privilegio_elimina = true;
              if ($cFNZS->privilegio_ver_docs == TRUE) $fnzs_privilegio_ver_docs = true;
            }

            $vhum_jerarquia = "D";
            $vhum_privilegio_crear = false;
            $vhum_privilegio_editar = false;
            $vhum_privilegio_consulta = false;
            $vhum_privilegio_elimina = false;
            $vhum_privilegio_ver_docs = false;

            $usuario_conf_vhum = DB::table("configuracion_systema_vhum AS conf_vhum")
              ->join("main_empresas AS emp", "conf_vhum.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "=", "users.id")
              ->where(["emp.empresa_token" => $token_session->empresa_token, "users.usuario_token" => $token_session->user_token])->get();
            foreach ($usuario_conf_vhum as $cVHUM) {
              $vhum_jerarquia = $cVHUM->jerarquia;
              if ($cVHUM->privilegio_crear == TRUE) $vhum_privilegio_crear = true;
              if ($cVHUM->privilegio_editar == TRUE) $vhum_privilegio_editar = true;
              if ($cVHUM->privilegio_consulta == TRUE) $vhum_privilegio_consulta = true;
              if ($cVHUM->privilegio_elimina == TRUE) $vhum_privilegio_elimina = true;
              if ($cVHUM->privilegio_ver_docs == TRUE) $vhum_privilegio_ver_docs = true;
            }

            $cont_jerarquia = "D";
            $cont_privilegio_crear = false;
            $cont_privilegio_editar = false;
            $cont_privilegio_consulta = false;
            $cont_privilegio_elimina = false;
            $cont_privilegio_ver_docs = false;

            $usuario_conf_cont = DB::table("configuracion_systema_cont AS conf_cont")
              ->join("main_empresas AS emp", "conf_cont.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "=", "users.id")
              ->where(["emp.empresa_token" => $token_session->empresa_token, "users.usuario_token" => $token_session->user_token])->get();
            foreach ($usuario_conf_cont as $cCONT) {
              $cont_jerarquia = $cCONT->jerarquia;
              if ($cCONT->privilegio_crear == TRUE) $cont_privilegio_crear = true;
              if ($cCONT->privilegio_editar == TRUE) $cont_privilegio_editar = true;
              if ($cCONT->privilegio_consulta == TRUE) $cont_privilegio_consulta = true;
              if ($cCONT->privilegio_elimina == TRUE) $cont_privilegio_elimina = true;
              if ($cCONT->privilegio_ver_docs == TRUE) $cont_privilegio_ver_docs = true;
            }

            $teci_jerarquia = "D";
            $teci_privilegio_crear = false;
            $teci_privilegio_editar = false;
            $teci_privilegio_consulta = false;
            $teci_privilegio_elimina = false;
            $teci_privilegio_ver_docs = false;

            $usuario_conf_teci = DB::table("configuracion_systema_teci AS conf_teci")
              ->join("main_empresas AS emp", "conf_teci.empresa", "=", "emp.id")
              ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "=", "users.id")
              ->where(["emp.empresa_token" => $token_session->empresa_token, "users.usuario_token" => $token_session->user_token])->get();
            foreach ($usuario_conf_teci as $cTECI) {
              $teci_jerarquia = $cTECI->jerarquia;
              if ($cTECI->privilegio_crear == TRUE) $teci_privilegio_crear = true;
              if ($cTECI->privilegio_editar == TRUE) $teci_privilegio_editar = true;
              if ($cTECI->privilegio_consulta == TRUE) $teci_privilegio_consulta = true;
              if ($cTECI->privilegio_elimina == TRUE) $teci_privilegio_elimina = true;
              if ($cTECI->privilegio_ver_docs == TRUE) $teci_privilegio_ver_docs = true;
            }

            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "statusPerm" => $loginPerM,
              "arrayMenuPerm" => $arrayMenuPerm,
              "main_jerarquia" => $main_jerarquia,
              "main_perm_crear" => $main_privilegio_crear,
              "main_perm_editar" => $main_privilegio_editar,
              "main_perm_consulta" => $main_privilegio_consulta,
              "main_perm_elimina" => $main_privilegio_elimina,
              "main_perm_ver_docs" => $main_privilegio_ver_docs,

              "ingr_jerarquia" => $ingr_jerarquia,
              "ingr_privilegio_crear" => $ingr_privilegio_crear,
              "ingr_privilegio_editar" => $ingr_privilegio_editar,
              "ingr_privilegio_consulta" => $ingr_privilegio_consulta,
              "ingr_privilegio_elimina" => $ingr_privilegio_elimina,
              "ingr_privilegio_ver_docs" => $ingr_privilegio_ver_docs,

              "eegr_jerarquia" => $eegr_jerarquia,
              "eegr_privilegio_crear" => $eegr_privilegio_crear,
              "eegr_privilegio_editar" => $eegr_privilegio_editar,
              "eegr_privilegio_consulta" => $eegr_privilegio_consulta,
              "eegr_privilegio_elimina" => $eegr_privilegio_elimina,
              "eegr_privilegio_ver_docs" => $eegr_privilegio_ver_docs,

              "fnzs_jerarquia" => $fnzs_jerarquia,
              "fnzs_privilegio_crear" => $fnzs_privilegio_crear,
              "fnzs_privilegio_editar" => $fnzs_privilegio_editar,
              "fnzs_privilegio_consulta" => $fnzs_privilegio_consulta,
              "fnzs_privilegio_elimina" => $fnzs_privilegio_elimina,
              "fnzs_privilegio_ver_docs" => $fnzs_privilegio_ver_docs,

              "vhum_jerarquia" => $vhum_jerarquia,
              "vhum_privilegio_crear" => $vhum_privilegio_crear,
              "vhum_privilegio_editar" => $vhum_privilegio_editar,
              "vhum_privilegio_consulta" => $vhum_privilegio_consulta,
              "vhum_privilegio_elimina" => $vhum_privilegio_elimina,
              "vhum_privilegio_ver_docs" => $vhum_privilegio_ver_docs,

              "cont_jerarquia" => $cont_jerarquia,
              "cont_privilegio_crear" => $cont_privilegio_crear,
              "cont_privilegio_editar" => $cont_privilegio_editar,
              "cont_privilegio_consulta" => $cont_privilegio_consulta,
              "cont_privilegio_elimina" => $cont_privilegio_elimina,
              "cont_privilegio_ver_docs" => $cont_privilegio_ver_docs,

              "teci_jerarquia" => $teci_jerarquia,
              "teci_privilegio_crear" => $teci_privilegio_crear,
              "teci_privilegio_editar" => $teci_privilegio_editar,
              "teci_privilegio_consulta" => $teci_privilegio_consulta,
              "teci_privilegio_elimina" => $teci_privilegio_elimina,
              "teci_privilegio_ver_docs" => $teci_privilegio_ver_docs,
            );
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "Usuario no encontrado");
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "La información que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function permisosIngresos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $empresa = $token_session->empresa_token;
        $usuario = $token_session->user_token;

        $queryPermMenu = DB::select("SELECT ingrperm.* FROM teci_permisos_usuario_ingr AS ingrperm
                    JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users
                    WHERE ingrperm.empresa = emp.id AND emp.empresa_token = ?
                    AND ingrperm.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
        //echo count($queryPermMenu);
        foreach ($queryPermMenu as $valPerMenu) {
          if ($valPerMenu->catalogos == TRUE) {
            $acceso_ingr_catalogos = true;
          } else {
            $acceso_ingr_catalogos = false;
          }

          if ($valPerMenu->ventas == TRUE) {
            $acceso_ingr_ventas = true;
          } else {
            $acceso_ingr_ventas = false;
          }


          if ($valPerMenu->reportes == TRUE) {
            $acceso_ingr_reportes = true;
          } else {
            $acceso_ingr_reportes = false;
          }

          $arraYnterno = array(
            "ingr-catalogos" => $acceso_ingr_catalogos,
            "ingr-ventas" => $acceso_ingr_ventas,
            "ingr-reportes" => $acceso_ingr_reportes,
          );
          $arrayMenuPerm[] = $arraYnterno;
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'arrayMenuPerm' => $arrayMenuPerm,
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

  //ingresos
  //sos_inside/ingresos/catalogodemercancias
  //sos_inside/ingresos/catalogodeservicios
  //sos_inside/ingresos/altadeservicios
  //sos_inside/ingresos/lista_de_precios
  //sos_inside/ingresos/catalogodedescuentos
  //sos_inside/ingresos/altadedescuentos
  //sos_inside/ingresos/catalogodepromociones
  //sos_inside/ingresos/altadepromociones
  //sos_inside/ingresos/catalogodeimpuestos
  //sos_inside/ingresos/altadeimpuestos
  //sos_inside/ingresos/catalogodeclientes
  //sos_inside/ingresos/altadeclientes
  //sos_inside/ingresos/listadepedidos
  //sos_inside/ingresos/altadeopedidos
  //sos_inside/ingresos/altadeventas
  //sos_inside/ingresos/seguimientodeventas
  //btnAbreCatSeg
  //btnAbreAltaSeg
  //btnAbreCatDevol
  //btnAbreAltaDevol
  //sos_inside/ingresos/solicitudes_facturacion
  //sos_inside/ingresos/nueva_factura
  //menuEgresos
  //sos_inside/egresos/catalogodeproductos
  public function permisosEgresosProductosCatalogo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $queryPermPrdCat = DB::select("SELECT catalogos,cat_prod FROM configuracion_systema_eegr AS confVh JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users
                        WHERE confVh.empresa = emp.id AND emp.empresa_token = ? AND confVh.usuario = users.id AND users.usuario_token = ?", [$token_session->empresa_token, $token_session->user_token]);
        //echo count($queryPermMenu);
        foreach ($queryPermPrdCat as $vprCat) {
          $acceso_catalogos = $vprCat->catalogos == TRUE && $vprCat->cat_prod == TRUE ? true : false;
          $dataMensaje = array("status" => "success", "code" => 200, "acceso_catalogos" => $acceso_catalogos);
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

  //sos_inside/egresos/altadeproductos
  //sos_inside/egresos/catalogodelotes
  //sos_inside/egresos/altadelotes
  //sos_inside/egresos/catalogodepedimentos
  //sos_inside/egresos/altadepedimentos
  //sos_inside/egresos/catalogodeservicios
  //sos_inside/egresos/altadeservicios
  //sos_inside/egresos/catalogodeactivosfijos
  //sos_inside/egresos/altadeactivosfijos
  //sos_inside/egresos/catalogodeactivosintangibles
  //sos_inside/egresos/altadeactivosintangibles
  //sos_inside/egresos/catalogodeproveedores
  //sos_inside/egresos/altadeproveedores
  //sos_inside/egresos/catalogodeestablecimientos
  //sos_inside/egresos/altadeestablecimientos
  //sos_inside/egresos/catalogoderequisiciones
  //sos_inside/egresos/altaderequisiciones
  //sos_inside/egresos/catalogodecotizaciones
  //sos_inside/egresos/altadecotizaciones
  //sos_inside/egresos/catalogode_erogacionesygastos
  //sos_inside/egresos/altade_erogacionesygastos
  //sos_inside/egresos/altadecompras
  //sos_inside/egresos/seguimientodecompras
  //sos_inside/egresos/reembolsos
  //sos_inside/egresos/justificacion_de_gastos

  public function permisosEGRESOSReembolsos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $queryPermReem = DB::select("SELECT reembolsos FROM configuracion_systema_eegr AS confVh
                        JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users
                        WHERE confVh.empresa = emp.id AND emp.empresa_token = ?
                        AND confVh.usuario = users.id AND users.usuario_token = ?", [$token_session->empresa_token, $token_session->user_token]);
        //echo count($queryPermMenu);
        foreach ($queryPermReem as $vPReem) {
          $acceso_reembolsos = false;
          if ($vPReem->reembolsos == TRUE) $acceso_reembolsos = true;

          $dataMensaje = array("status" => "success", "code" => 200, "acceso_reembolsos" => $acceso_reembolsos);
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

  //menuFinanzas
  //sos_inside/finanzas/catalogodecuentasbancarias
  //sos_inside/finanzas/altadecuentasbancarias
  //sos_inside/finanzas/catalogodecajas
  //sos_inside/finanzas/altadecajas
  //sos_inside/finanzas/catalogodemonederos_electronicos
  //sos_inside/finanzas/altademonederos_electronicos
  //sos_inside/finanzas/catalogodedispositivos
  //sos_inside/finanzas/altadedispositivos
  //sos_inside/finanzas/control_movimientos_bancarios
  //sos_inside/finanzas/control_movimientos_en_efectivo

  public function permisosFINANZASOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $queryPermFnzsPay = DB::table("configuracion_systema_fnzs AS confVh")
    ->join("main_empresas AS emp", "confVh.empresa", "=", "emp.id")
    ->join("teci_usuarios_catalogo AS users", "confVh.usuario", "=", "users.id")
    ->where([
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])
    ->select('confVh.paym_ord')
    ->first();

    if (!$queryPermFnzsPay) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron permisos registrados'
      );
    } else {
      $acceso_paym_ord = $queryPermFnzsPay->paym_ord == TRUE ? true : false;
      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "acceso_paym_ord" => $acceso_paym_ord
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //menuValorHumano: any = [
  //{id: 1,name: 'Catalogos'},
  //{id: 2,name: 'Asistencias'}
  //{id: 3,name: 'Cálculo de nominas'},
  //{id: 4,name: 'Cálculo de aportaciones'},
  //{path:'centros_de_trabajo_alta',component: VHCentrosTrabajoAltaComponent,canActivate:[AuthGuardService]},
  //{path:'centros_de_trabajo_lista',component: VHCentrosTrabajoListaComponent,canActivate:[AuthGuardService]},
  //{path:'empleados_alta',component: VHEmpleadosAltaComponent,canActivate:[AuthGuardService]},
  //{path:'empleados_lista',component: VHEmpleadosListaComponent,canActivate:[AuthGuardService]},
  //{path:'empleados_asistencias',component: AsistenciasComponent,canActivate:[AuthGuardService]},
  //{path:'empleados_nomina',component: CalcNominasComponent,canActivate:[AuthGuardService]},
  //{path:'empleados_aportaciones',component: CalcAportacionesComponent,canActivate:[AuthGuardService]},

  public function permisosVHUMReembolsos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMenuPerm = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_session = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $queryPermReem = DB::select("SELECT reembolsos FROM configuracion_systema_vhum AS confVh
                        JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users
                        WHERE confVh.empresa = emp.id AND emp.empresa_token = ?
                        AND confVh.usuario = users.id AND users.usuario_token = ?", [$token_session->empresa_token, $token_session->user_token]);
        //echo count($queryPermMenu);
        foreach ($queryPermReem as $vPReem) {
          $acceso_reembolsos = false;
          if ($vPReem->reembolsos == TRUE) $acceso_reembolsos = true;

          $dataMensaje = array("status" => "success", "code" => 200, "acceso_reembolsos" => $acceso_reembolsos);
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

  //menuContabilidad: any = [
  //sos_inside/contabilidad/catalogodecuentas'},
  //Estados Financieros'},
  //Reportes'},
  //menuTecInfo: any = [
  //Apps complementarias'},
  //sos_inside/tecnologias_info/soporte_sos'},
  //comunicación'},
  //Publicaciones'},
}
