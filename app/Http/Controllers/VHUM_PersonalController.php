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
use App\Models\PersonalModelo;

class VHUM_TrabajadoresController extends Controller{
  //empleados SOS
  public function catalogo_general_trabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
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

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
        ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
        ->where('emp.empresa_token',$usuario->empresa_token)->get();

        //echo count($listPersonal);
        foreach ($listEmpleados as $vEmploy) {
          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          if ($JwtAuth->desencriptar($vEmploy->img_perfil) == 'default-profile.png') {
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vEmploy->img_perfil)));
          } else {
            $filepath = "main_users/" . $JwtAuth->generar($vEmploy->folio_pers) . "-" . $vEmploy->fecha_alta_pers;
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vEmploy->img_perfil) . '-profile.png'));
          }

          $rowEmpleado = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => "TRB-" . $JwtAuth->generarFolio($vEmploy->folio_pers),
            "token_personas" => $vEmploy->token_personas,
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "nombre_completo" => ucwords($nombre_completo),
            "nacionalidad" => $vEmploy->nacionalidad,
            "rfc_generico" => $vEmploy->rfc_generico,
            "rfc" => !is_null($vEmploy->rfc) ? $vEmploy->rfc : '',
            "tax_id" => !is_null($vEmploy->tax_id) ? $vEmploy->tax_id : '',
            "curp" => $vEmploy->curp != '' ? : '',
            "imagen" => $img_perfil,
            "selected" => false,
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
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

  public function empleado_detalle(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
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

        if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY") {
          $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
          ->join("teci_usuarios_catalogo AS users", "vhum_empleados_catalogo.id", "users.empleado")
          ->join("main_empresa_usuario AS empuser", "users.id", "empuser.usuario")
          ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
          ->where('emp.empresa_token',$usuario->empresa_token)->get();
        } else {
          $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
          ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
          ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
          ->where('emp.empresa_token',$usuario->empresa_token)->get();
        }

        //echo count($listPersonal);
        foreach ($listEmpleados as $vEmploy) {
          $token_empleado_inside = $vEmploy->empleado_token;
          $token_empleado_vhum = $vEmploy->empleado_token;
          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          if ($JwtAuth->desencriptar($vEmploy->img_perfil) == 'default-profile.png') {
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vEmploy->img_perfil)));
          } else {
            $filepath = "main_users/" . $JwtAuth->generar($vEmploy->folio_pers) . "-" . $vEmploy->fecha_alta_pers;
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vEmploy->img_perfil) . '-profile.png'));
          }

          //telefonos
          $arrayTelefonos = array();
          $listPhone = DB::table("sos_personas_telefonos AS tel")
          ->join("vhum_empleados_catalogo AS pers", "tel.personal", "pers.id")
          ->where('pers.empleado_token',$token_empleado_inside)
          ->where('tel.status_telefono',TRUE)
          ->get();

          foreach ($listPhone as $vPhone) {
            if ($vPhone->extension != "" && $vPhone->extension != NULL) {
              $extension_tel = $JwtAuth->desencriptar($vPhone->extension);
            } else {
              $extension_tel = "";
            }

            $each = array(
              "token_telefono" => $vPhone->token_telefono,
              "icono" => $vPhone->icono,
              "etiqueta" => $vPhone->etiqueta,
              "telefono" => $JwtAuth->desencriptar($vPhone->telefono),
              //"telefono" => $JwtAuth->encriptar("5631863335"),
              "extension" => $extension_tel,
            );
            $arrayTelefonos[] = $each;
          }

          //correos
          $arrayCorreos = array();
          $listMails = DB::table("sos_personas_correos AS mail")
          ->join("vhum_empleados_catalogo AS pers", "mail.personal", "pers.id")
          ->where('pers.empleado_token',$token_empleado_inside)            
          ->where('mail.status_correo',TRUE)
          ->get();

          foreach ($listMails as $vMail) {
            $row = array(
              "token_correo" => $vMail->token_correo,
              "correo" => $JwtAuth->desencriptar($vMail->correo),
            );
            $arrayCorreos[] = $row;
          }

          //direcciones
          $arrayDirecciones = array();
          $listLocations = DB::table("teci_direcciones AS dom")
          ->join("vhum_empleados_catalogo AS pers", "dom.empleado", "pers.id")
          ->join('teci_direcciones_codigos_postales AS cpostal', 'dom.codigo_postal', 'cpostal.id')
          ->join("teci_pais AS detpais", "dom.pais", "detpais.id")
          ->where("dom.status",TRUE)
          ->where("pers.empleado_token",$token_empleado_inside)
          ->get();

          foreach ($listLocations as $vDom) {
            if ($vDom->calle != '' && $vDom->calle != NULL) {
              $calle = $JwtAuth->desencriptar($vDom->calle);
            } else {
              $calle = 's/c';
            }

            if ($vDom->num_ext != '' && $vDom->num_ext != NULL) {
              $num_ext = $JwtAuth->desencriptar($vDom->num_ext);
            } else {
              $num_ext = 's/n';
            }

            if ($vDom->num_int != '' && $vDom->num_int != NULL) {
              $num_int = $JwtAuth->desencriptar($vDom->num_int);
            } else {
              $num_int = 's/n';
            }

            if ($vDom->calle1 != '' && $vDom->calle1 != NULL) {
              $calle1 = $JwtAuth->desencriptar($vDom->calle1);
            } else {
              $calle1 = 's/c';
            }

            if ($vDom->calle2 != '' && $vDom->calle2 != NULL) {
              $calle2 = $JwtAuth->desencriptar($vDom->calle2);
            } else {
              $calle2 = 's/c';
            }

            if ($vDom->referencia != '' && $vDom->referencia != NULL) {
              $referencia = $JwtAuth->desencriptar($vDom->referencia);
            } else {
              $referencia = 's/reg';
            }

            $domRow = array(
              "token_direccion" => $vDom->token_direccion,
              "token_direccion" => $vDom->token_direccion,
              "tipo_direccion" => $vDom->tipo_direccion,
              "clasificacion" => $JwtAuth->desencriptar($vDom->clase),
              "alias" => $JwtAuth->desencriptar($vDom->alias),
              "calle" => $calle,
              "num_ext" => $num_ext,
              "num_int" => $num_int,
              "token_codigos_postales" => $vDom->token_codigos_postales,
              "codigo_postal" => $vDom->codigo_postal,
              "asentamiento" => $vDom->asentamiento,
              "tipo_asentamiento" => $vDom->tipo_asentamiento,
              "deleg_mun" => $vDom->deleg_mun,
              "estado" => $vDom->estado,
              "ciudad" => $vDom->ciudad,
              "pais" => $vDom->pais,
              "calle1" => $calle1,
              "calle2" => $calle2,
              "referencia" => $referencia,
              "validate" => false,
            );
            $arrayDirecciones[] = $domRow;
          }

          $rowEmpleado = array(
            "token_empleado_inside" => $token_empleado_inside,
            "token_empleado_vhum" => $token_empleado_vhum,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => "TRB-" . $JwtAuth->generarFolio($vEmploy->folio_pers),
            "nombre_completo" => ucwords($nombre_completo),
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "imagen" => $img_perfil,
            "telefonos" => $arrayTelefonos,
            "correos" => $arrayCorreos,
            "direcciones" => $arrayDirecciones,
            "selected" => false,
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
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

  public function actualizaPaternoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'paterno' => 'required|string',
        'token_personal' => 'required|string',
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
        $paterno = $parametrosArray['paterno'];
        $token_personal = $parametrosArray['token_personal'];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        if (
          isset($paterno) && !empty($paterno) && preg_match($patron, $paterno) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $updatePaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("sos_personas AS people", "vhum_empleados_catalogo.personal", "=", "people.id")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'people.paterno' => $JwtAuth->encriptar(ucwords($paterno)),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Apellido paterno de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Apellido paterno de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en apellido paterno de personal'
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

  public function actualizaMaternoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'materno' => 'required|string',
        'token_personal' => 'required|string',
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
        $materno = $parametrosArray['materno'];
        $token_personal = $parametrosArray['token_personal'];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        if (
          isset($materno) && !empty($materno) && preg_match($patron, $materno) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $updateMaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("sos_personas AS people", "vhum_empleados_catalogo.personal", "=", "people.id")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'people.materno' => $JwtAuth->encriptar(ucwords($materno)),
              )
            );

          if ($updateMaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Apellido materno de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Apellido materno de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en apellido materno de personal'
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

  public function actualizaNombresPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'nombres' => 'required|string',
        'token_personal' => 'required|string',
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
        $nombres = $parametrosArray['nombres'];
        $token_personal = $parametrosArray['token_personal'];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        if (
          isset($nombres) && !empty($nombres) && preg_match($patron, $nombres) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $updateNombres = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "pers.usuario")
            ->join("sos_personas AS people", "pers.personal", "=", "people.id")
            ->join("main_empresa_usuario AS empuser", "pers.id", "=", "empuser.empleado")
            ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'people.nombre' => $JwtAuth->encriptar(ucwords($nombres)),
              )
            );

          if ($updateNombres) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Nombre(s) de personal actualizado(s)'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Nombre(s) de personal no actualizado(s)'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en nombre(s) de personal'
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

  public function actualizaAreaPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'email' => 'required|string',
        'token_personal' => 'required|string',
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
        $email = $parametrosArray['email'];
        $token_personal = $parametrosArray['token_personal'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        if (
          isset($email) && !empty($email) && preg_match($patronMail, $email) &&
          isset($token_personal) && !empty($token_personal)
        ) {
          $updatePaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'users.email' => $JwtAuth->encriptar($email),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Correo electrónico de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Correo electrónico de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
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

  public function actualizaMailPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'email' => 'required|string',
        'username' => 'required|string',
        'token_personal' => 'required|string',
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
        $email = $parametrosArray['email'];
        $username = $parametrosArray['username'];
        $token_personal = $parametrosArray['token_personal'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        if (
          isset($email) && !empty($email) && preg_match($patronMail, $email) &&
          isset($username) && !empty($username) &&
          isset($token_personal) && !empty($token_personal)
        ) {
          $updateEmail = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'users.email' => $JwtAuth->encriptar($email),
                'users.username' => $JwtAuth->encriptar($username),
              )
            );

          if ($updateEmail) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Correo electrónico de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Correo electrónico de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
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

  public function registraTelefonoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'telefono' => 'required|string',
        'token_personal' => 'required|string',
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
        $telefono = $parametrosArray['telefono'];
        $token_personal = $parametrosArray['token_personal'];

        $patronNum = '/^[1-9][0-9]*$/';
        if (
          isset($telefono) && !empty($telefono) && preg_match($patronNum, $telefono) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $selectPers = DB::select("SELECT id FROM personal WHERE empleado_token = ?", [$token_personal]);
          $tokentel = $JwtAuth->encriptarToken($token_personal . $telefono);
          $encriptPhone = $JwtAuth->encriptar($telefono);
          $insertTelefono = DB::table('sos_personas_telefonos')
            ->insert(array(
              "token_telefono" => $tokentel,
              "personal" => $selectPers[0]->id,
              "icono" => "",
              "etiqueta" => "",
              "cod_pais" => "52",
              "telefono" => $encriptPhone,
              "extension" => "",
              "status_telefono" => TRUE,
              "fecha_delete_tel" => NULL,
            ));

          if ($insertTelefono) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Teléfono de personal registrado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Teléfono de personal no registrado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
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

  public function actualizaTelefonoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'telefono' => 'required|string',
        'token_personal' => 'required|string',
        'token_telefono' => 'required|string',
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
        $telefono = $parametrosArray['telefono'];
        $token_personal = $parametrosArray['token_personal'];
        $token_telefono = $parametrosArray['token_telefono'];

        $patronNum = '/^[1-9][0-9]*$/';
        if (
          isset($telefono) && !empty($telefono) && preg_match($patronNum, $telefono) &&
          isset($token_personal) && !empty($token_personal) &&
          isset($token_telefono) && !empty($token_telefono)
        ) {

          $encriptPhone = $JwtAuth->encriptar($telefono);

          $updateTelefono = DB::table("sos_personas_telefonos AS phone")
            ->join("vhum_empleados_catalogo AS pers", "phone.personal", "=", "vhum_empleados_catalogo.id")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'phone.token_telefono' => $token_telefono,
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'phone.telefono' => $encriptPhone,
              )
            );

          if ($updateTelefono) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Teléfono de personal registrado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Teléfono de personal no registrado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
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
        'token_personal' => 'required|string',
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
        $access_code = $parametrosArray['access_code'];
        $password_code = $parametrosArray['password_code'];
        $token_personal = $parametrosArray['token_personal'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        if (
          isset($access_code) && !empty($access_code) &&
          isset($password_code) && !empty($password_code) &&
          isset($token_personal) && !empty($token_personal)
        ) {
          $updatePaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'users.codigo_acceso' => $JwtAuth->encriptar($access_code),
                'users.password' => $JwtAuth->encriptar($password_code),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
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
        } else {
          if (isset($access_code) && !empty($access_code)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en código de acceso de personal'
            );
          }

          if (isset($password_code) && !empty($password_code)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en password de personal'
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

  public function listaPersonalGneral(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $personal = array();

    $listPersonal = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.personal", "people.id")
      ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
      ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
      ->where([
        'emp.empresa_token' => $usuario->empresa_token,
      ])->get();

    //echo count($listPersonal);
    foreach ($listPersonal as $resPersonal) {
      if ($JwtAuth->desencriptar($resPersonal->img_perfil) == 'default-profile.png') {
        $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resPersonal->img_perfil)));
      } else {
        $img_perfil =  $JwtAuth->encriptaBase64(Storage::path('public/root/' .
          $resPersonal->root_tkn . '/0004-vhm/catalogos/employees/' . $JwtAuth->desencriptar($resPersonal->img_perfil)
          . '/' . $JwtAuth->desencriptar($resPersonal->img_perfil) . '-profile.png'));
      }

      $nombre_full = $JwtAuth->desencriptar($resPersonal->paterno)
        . " " . $JwtAuth->desencriptar($resPersonal->materno)
        . " " . $JwtAuth->desencriptar($resPersonal->nombre);

      $arrayRespons = array(
        "token_personal" => $resPersonal->empleado_token,
        "folio" => $JwtAuth->generar($resPersonal->folio_pers),
        "nombre_completo" => ucwords($nombre_full),
        //"email" => $JwtAuth->desencriptar($resPersonal->email),
        "imagen" => $img_perfil,
        "selected" => false,
      );
      if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
        $personal[] = $arrayRespons;
      } else {
        if ($resPersonal->empleado_token != "8bTRXM1JJS0FpYy9CdEtFWGRHSExXZz09OjoxMjM0NTY3ODEyMzQ1Njc4") {
          $personal[] = $arrayRespons;
        }
      }
    }

    return response()->json([
      'personal' => $personal,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function listaPersonalArea(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $personal = array();

    $tipo_userquery = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
      ->join("empresapersonal AS empuser", "personal.id", "empuser.empleado")
      ->join("teci_usuarios_catalogo AS users", "personal.usuario", "users.id")
      ->join("tipo_usuario AS typeuser", "users.tipo", "typeuser.id_tipo")
      ->join("empresas AS emp", "empuser.empresa", "emp.id")
      ->where([
        'emp.empresa_token' => $usuario->empresa_token,
        'users.user_token' => $usuario->user_token,
      ])->get();

    //echo $tipo_userquery[0]->tipo;
    if ($tipo_userquery[0]->tipo == 'SuperAdministrador') {
      $area_tipo = 'sos-ti';
    } else {
      $area_tipo = $tipo_userquery[0]->tipo;
    }

    $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
      ->join("empresapersonal AS empuser", "personal.id", "empuser.empleado")
      ->join("teci_usuarios_catalogo AS users", "personal.usuario", "users.id")
      ->join("tipo_usuario AS typeuser", "users.tipo", "typeuser.id_tipo")
      ->join("empresas AS emp", "empuser.empresa", "emp.id")
      ->where([
        'typeuser.tipo' => $area_tipo,
        'personal.jerarquia' => 'terc',
        'emp.empresa_token' => $usuario->empresa_token,
      ])->get();

    //echo count($listPersonal);
    foreach ($listPersonal as $resPersonal) {
      if ($JwtAuth->desencriptar($resPersonal->img_perfil) == 'default-profile.png') {
        $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resPersonal->img_perfil)));
      } else {
        $img_perfil =  $JwtAuth->encriptaBase64(Storage::path('public/root/' .
          $resPersonal->root_tkn . '/0004-vhm/catalogos/employees/' . $JwtAuth->desencriptar($resPersonal->img_perfil)
          . '/' . $JwtAuth->desencriptar($resPersonal->img_perfil) . '-profile.png'));
      }

      $name_completo = $JwtAuth->desencriptar($resPersonal->paterno) . " " .
        $JwtAuth->desencriptar($resPersonal->materno) . " " .
        $JwtAuth->desencriptar($resPersonal->nombre);

      $arrayRespons = array(
        "token_personal" => $resPersonal->empleado_token,
        "folio" => $JwtAuth->generar($resPersonal->folio_pers),
        "nombre_completo" => ucwords($name_completo),
        "imagen" => $img_perfil,
      );

      $personal[] = $arrayRespons;
    }

    return response()->json([
      'personal' => $personal,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function listaResponsablesAlmacen(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $personal = array();
    $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
      ->join("responsables_almacen AS resp", "personal.id", "resp.responsable")
      ->join("almacen as alm", "resp.almacen", "alm.id")
      ->join("empresas AS emp", "alm.empresa", "emp.id")
      ->where([
        'personal.status' => TRUE,
        'emp.empresa_token' => $usuario->empresa_token,
        'alm.token_almacen' => $parametros->datalmpers,
        //'users.user_token' => $tokenUser '1457869IHDIFUJJ39485'
      ])->get();
    $name_completo = $JwtAuth->desencriptar($resPersonal->paterno) . " " .
      $JwtAuth->desencriptar($resPersonal->materno) . " " .
      $JwtAuth->desencriptar($resPersonal->nombre);
    //echo count($listPersonal);
    foreach ($listPersonal as $resPersonal) {
      $arrayRespons = array(
        "token_personal" => $resPersonal->token_responsables,
        "nombre_completo" => ucwords($name_completo),
        "imagen" => $JwtAuth->desencriptar($resPersonal->img_perfil)
      );

      $personal[] = $arrayRespons;
    }

    return response()->json([
      'personal' => $personal,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function asistenciaPersonalEntrada(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $nombrePersonal = '';

        //$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
        //user_token original dGEzQVAzZnArRmY3SXpoV0lsTzRkem8xNkdtM1JFRFJOSnlEV1FKNXRreVRGdE9Tb05RVVB1R0QrelZtWkFPSStlVlNVNmpjZWEyQTQzelVpS1AzSFYwanc4cVBrZ1Q3aXZPV1M0ZTJTQW5CUmJhYUlXVHFvS2xzY1pqd1hCZ3RNRjB2c2hSTWsyalpzVDlwNDZqayswZU9mSHRDME5xWGRkbVJRVE9GM2ppQld3S242cnBLVVpJaTF0aGlkSVE5cUhnUlJOaUZYK2FhTmFRUXNjdjZWQzhEbDFhZUsxVks5MDRKMi9FOUlWNGxWVHl1ZDdSeER6N1k3MlBFbktlMCtoS25lMmNWeVlFbmQ5VG9PdVFSejJEOW9JYjVEc01lN3ZPRWRRYXFUT0E9OjoxMjM0NTY3ODEyMzQ1Njc4 

        $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
          ->join("empresapersonal AS empuser", "personal.id", "=", "empuser.empleado")
          ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "personal.usuario", "=", "users.id")
          ->where([
            'personal.status' => TRUE,
            //'emp.empresa_token' => $usuario->empresa_token,
            //'users.user_token' => $usuario->user_token,
            'users.user_token' => $parametrosArray['user_token'],
          ])->get();

        if (count($listPersonal) == 1) {
          foreach ($listPersonal as $resPersonal) {
            $fecha = time();
            //da_te_default_timezone_set($resPersonal->zona_horaria);

            $nombrePersonal = $JwtAuth->desencriptar($resPersonal->paterno)
              . " " . $JwtAuth->desencriptar($resPersonal->materno)
              . " " . $JwtAuth->desencriptar($resPersonal->nombre);

            $selectEmp = DB::select("SELECT emp.id FROM empresas AS emp  
                            JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN usuarios AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
                            AND pers.usuario = users.id AND users.user_token= ?", [$resPersonal->empresa_token, $resPersonal->user_token]);

            $empleado_token = DB::select("SELECT id FROM personal WHERE empleado_token = ?", [$resPersonal->empleado_token]);

            $insertAsistenciaEntrada = DB::table('asistencias')
              ->insert(
                array(
                  "empresa" => $selectEmp[0]->id,
                  "personal" => $empleado_token[0]->id,
                  "entrada" => $fecha,
                  "salida" => NULL,
                )
              );

            if ($insertAsistenciaEntrada) {
              $dataMensaje = array(
                'personal' => $nombrePersonal,
                'fecha' => gmdate('Y-m-d H:i:s', $fecha),
                'hora' => date('H:i:s', $fecha),
                'code' => 200,
                'status' => 'success'
              );
            } else {
              $dataMensaje = array(
                'message' => 'error en registro de entrada, intente nuevamente o comuniquese a soporte',
                'code' => 200,
                'status' => 'error'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'message' => 'personal no encontrado',
            'code' => 200,
            'status' => 'error'
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

  public function asistenciaPersonalSalida(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $nombrePersonal = '';

        //$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
        //user_token original dGEzQVAzZnArRmY3SXpoV0lsTzRkem8xNkdtM1JFRFJOSnlEV1FKNXRreVRGdE9Tb05RVVB1R0QrelZtWkFPSStlVlNVNmpjZWEyQTQzelVpS1AzSFYwanc4cVBrZ1Q3aXZPV1M0ZTJTQW5CUmJhYUlXVHFvS2xzY1pqd1hCZ3RNRjB2c2hSTWsyalpzVDlwNDZqayswZU9mSHRDME5xWGRkbVJRVE9GM2ppQld3S242cnBLVVpJaTF0aGlkSVE5cUhnUlJOaUZYK2FhTmFRUXNjdjZWQzhEbDFhZUsxVks5MDRKMi9FOUlWNGxWVHl1ZDdSeER6N1k3MlBFbktlMCtoS25lMmNWeVlFbmQ5VG9PdVFSejJEOW9JYjVEc01lN3ZPRWRRYXFUT0E9OjoxMjM0NTY3ODEyMzQ1Njc4 

        $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
          ->join("empresapersonal AS empuser", "personal.id", "=", "empuser.empleado")
          ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "personal.usuario", "=", "users.id")
          ->where([
            'personal.status' => TRUE,
            //'emp.empresa_token' => $usuario->empresa_token,
            //'users.user_token' => $usuario->user_token,
            'users.user_token' => $parametrosArray['user_token'],
          ])->get();

        if (count($listPersonal) == 1) {
          foreach ($listPersonal as $resPersonal) {
            $fecha = time();
            //da_te_default_timezone_set($resPersonal->zona_horaria);

            $nombrePersonal = $JwtAuth->desencriptar($resPersonal->paterno)
              . " " . $JwtAuth->desencriptar($resPersonal->materno)
              . " " . $JwtAuth->desencriptar($resPersonal->nombre);

            $selectEmp = DB::select("SELECT emp.id FROM empresas AS emp  
                            JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN usuarios AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
                            AND pers.usuario = users.id AND users.user_token= ?", [$resPersonal->empresa_token, $resPersonal->user_token]);

            $empleado_token = DB::select("SELECT id FROM personal WHERE empleado_token = ?", [$resPersonal->empleado_token]);

            $insertAsistenciaEntrada = DB::table('asistencias')
              ->insert(
                array(
                  "empresa" => $selectEmp[0]->id,
                  "personal" => $empleado_token[0]->id,
                  "entrada" => NULL,
                  "salida" => $fecha,
                )
              );

            if ($insertAsistenciaEntrada) {
              $dataMensaje = array(
                'personal' => $nombrePersonal,
                'fecha' => gmdate('Y-m-d H:i:s', $fecha),
                'hora' => date('H:i:s', $fecha),
                'code' => 200,
                'status' => 'success'
              );
            } else {
              $dataMensaje = array(
                'message' => 'error en registro de salida, intente nuevamente o comuniquese a soporte',
                'code' => 200,
                'status' => 'error'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'message' => 'personal no encontrado',
            'code' => 200,
            'status' => 'error'
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
