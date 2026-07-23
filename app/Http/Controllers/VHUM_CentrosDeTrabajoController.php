<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\CentrosDeTrabajoModelo;
use App\Services\FirebaseService;
use Illuminate\Support\Str;

class VHUM_CentrosDeTrabajoController extends Controller{
  public function registraCentroDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'registro_patronal_imss' => 'required|string',
        'riesgo_division' => 'required|string',
        'riesgo_grupo' => 'required|string',
        'riesgo_fraccion' => 'required|string',
        'riesgo_clave' => 'required|string',
        'descripcion' => 'required|string',
        'ubicacion' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $registro_patronal_imss = $argumentos['registro_patronal_imss'];
        $riesgo_division = $argumentos['riesgo_division'];
        $riesgo_grupo = $argumentos['riesgo_grupo'];
        $riesgo_fraccion = $argumentos['riesgo_fraccion'];
        $riesgo_clave = $argumentos['riesgo_clave'];
        $descripcion = $argumentos['descripcion'];
        $ubicacion = $argumentos['ubicacion'];

        $OKRPImss = isset($registro_patronal_imss) && !empty($registro_patronal_imss) && preg_match($JwtAuth->filtroAlfaNumerico(),$registro_patronal_imss);
        $OKRiesgoDivision = isset($riesgo_division) && !is_null($riesgo_division) && preg_match($JwtAuth->filtroNumericoSimple(),$riesgo_division);
        $OKRiesgoGrupo = isset($riesgo_grupo) && !empty($riesgo_grupo) && preg_match($JwtAuth->filtroAlfaNumerico(),$riesgo_grupo);
        $OKRiesgoFraccion = isset($riesgo_fraccion) && !empty($riesgo_fraccion) && preg_match($JwtAuth->filtroAlfaNumerico(),$riesgo_fraccion);
        $OKRiesgoClave = isset($riesgo_clave) && !empty($riesgo_clave) && preg_match($JwtAuth->filtroAlfaNumerico(),$riesgo_clave);
        $OKDescripcion = isset($descripcion) && !empty($descripcion) && preg_match($JwtAuth->filtroAlfaNumerico(),$descripcion);
        $OKUbicacion = isset($ubicacion) && !empty($ubicacion);
        if ($OKRPImss && $OKRiesgoDivision && $OKRiesgoGrupo && $OKRiesgoFraccion && $OKRiesgoClave && $OKDescripcion && $OKUbicacion) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT c_trab.centrotrab_folio+1 AS folio,c_trab.centrotrab_sub_folio FROM vhum_centros_de_trabajo_catalogo AS c_trab JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE c_trab.centrotrab_empresa = emp.id AND emp.empresa_token = ? 
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? ORDER BY c_trab.centrotrab_folio DESC LIMIT 1",
              [$usuario->empresa_token,$usuario->user_token]);
            //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT centrotrab_sub_folio FROM vhum_centros_de_trabajo_catalogo WHERE id = (SELECT Max(c_trab.id) FROM vhum_centros_de_trabajo_catalogo AS c_trab JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE c_trab.centrotrab_empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                  
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->centrotrab_sub_folio);
                  $folio_nuevo = 1;
              } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');

            $ctrab_ubicacion = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$ubicacion)->value("id");

            $newCTrab = new CentrosDeTrabajoModelo();
            $newCTrab->centrotrab_uuid = Str::uuid()->toString();
            $newCTrab->centrotrab_folio = $folio_nuevo;
            $newCTrab->centrotrab_sub_folio = $post_folio;
            $newCTrab->centrotrab_fecha_contabilizacion = time();
            $newCTrab->centrotrab_descripcion = $JwtAuth->encriptar($descripcion);
            $newCTrab->centrotrab_clave_registro_patronal_imss = $registro_patronal_imss;
            $newCTrab->riesgo_trabajo_division = $riesgo_division;
            $newCTrab->riesgo_trabajo_grupo = $riesgo_grupo;
            $newCTrab->riesgo_trabajo_fraccion = $riesgo_fraccion; 
            $newCTrab->riesgo_trabajo_clave = $riesgo_clave;
            $newCTrab->centrotrab_ubicacion = $ctrab_ubicacion;
            $newCTrab->centrotrab_baja = FALSE;
            $newCTrab->centrotrab_status = TRUE;
            $newCTrab->centrotrab_empresa = $vEmp->id;
            $savednewCTrab = $newCTrab->save();
            if ($savednewCTrab) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Centro de trabajo registrado satisfactoriamente con el folio $folio_centro_trab"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo no registrado debido a problemas internos, comuniquese a soporte para más información"
              );
            }
          }
        } else {
          $mensaje_error = "";
          if (!$OKRPImss) $mensaje_error = "Error al registrar clave de registro patronal del IMSS, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKRiesgoDivision) $mensaje_error = "Error al registrar división de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKRiesgoGrupo) $mensaje_error = "Error al registrar grupo de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte";  
          if (!$OKRiesgoFraccion) $mensaje_error = "Error al registrar fracción de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte";  
          if (!$OKRiesgoClave) $mensaje_error = "Error al registrar clave de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte";  
          if (!$OKDescripcion) $mensaje_error = "Error al registrar descripción de actividades, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKUbicacion) $mensaje_error = "Error al seleccionar ubicacion, intentelo nuevamente o comuniquese a soporte"; 
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
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

  public function catalogoCentrosDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    $catalogo_cent_trab = array();
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where('c_trab.centrotrab_status',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($cTrabQuery as $vList) {
          //da_te_default_timezone_set('UTC');
          $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
          
          $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "estab.id", "c_trab.centrotrab_ubicacion")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->select('estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento')
					->first();
          $estab_token = $listaDirAlmacen ? $listaDirAlmacen->token_establecimiento : '';
          $estab_folio = $listaDirAlmacen ? 'ESTAB-'.$JwtAuth->generarFolio($listaDirAlmacen->folio_establecimiento).($listaDirAlmacen->post_folio != NULL ? '-'.$listaDirAlmacen->post_folio : '') : '';
          $estab_alias = $listaDirAlmacen ? $JwtAuth->desencriptar($listaDirAlmacen->alias_establecimiento) : '';

          $cTrabTrabs = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
          ->join("vhum_empleados_catalogo AS trab", "c_trab.id", "trab.centro_de_trabajo")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->count();

          $row = array(
            "centrotrab_uuid" => $vList->centrotrab_uuid,
            "folio_centro_trab" => $folio_centro_trab,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_contabilizacion),
            "descripcion" => $JwtAuth->desencriptar($vList->centrotrab_descripcion),
            "clave_registro_patronal_imss" => $vList->centrotrab_clave_registro_patronal_imss,
            "ubicacion_token" => $estab_token,
            "ubicacion_alias" => "$estab_folio $estab_alias",
            "select_for_trabajador" => false,
            "baja_dado" => $vList->centrotrab_baja ? true : false,
            "baja_motivo" => $vList->centrotrab_baja ? $JwtAuth->desencriptar($vList->centrotrab_causa_baja) : '',
            "baja_fecha" => $vList->centrotrab_baja ? gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_baja) : '',
            "puede_eliminar" => $cTrabTrabs == 0 ? true : false,
          );
          $catalogo_cent_trab[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'cent_trab' => $catalogo_cent_trab
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

  public function detalleCentroDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    $catalogo_cent_trab = array();
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
        ->get();

        foreach ($cTrabQuery as $vList) {
          //da_te_default_timezone_set('UTC');
          $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
          
          $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "estab.id", "c_trab.centrotrab_ubicacion")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->select('estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento')
					->first();
          $estab_token = $listaDirAlmacen ? $listaDirAlmacen->token_establecimiento : '';
          $estab_folio = $listaDirAlmacen ? 'ESTAB-'.$JwtAuth->generarFolio($listaDirAlmacen->folio_establecimiento).($listaDirAlmacen->post_folio != NULL ? '-'.$listaDirAlmacen->post_folio : '') : '';
          $estab_alias = $listaDirAlmacen ? $JwtAuth->desencriptar($listaDirAlmacen->alias_establecimiento) : '';

          $row = array(
            "centrotrab_uuid" => $vList->centrotrab_uuid,
            "folio_centro_trab" => $folio_centro_trab,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_contabilizacion),
            "descripcion" => $JwtAuth->desencriptar($vList->centrotrab_descripcion),
            "clave_registro_patronal_imss" => $vList->centrotrab_clave_registro_patronal_imss,
            "riesgo_trabajo_division" => !is_null($vList->riesgo_trabajo_division) && $vList->riesgo_trabajo_division != '' ? $vList->riesgo_trabajo_division : '---',
            "riesgo_trabajo_grupo" => !is_null($vList->riesgo_trabajo_grupo) && $vList->riesgo_trabajo_grupo != '' ? $vList->riesgo_trabajo_grupo : '---',
            "riesgo_trabajo_fraccion" => !is_null($vList->riesgo_trabajo_fraccion) && $vList->riesgo_trabajo_fraccion != '' ? $vList->riesgo_trabajo_fraccion : '---',
            "riesgo_trabajo_clave" => !is_null($vList->riesgo_trabajo_clave) && $vList->riesgo_trabajo_clave != '' ? $vList->riesgo_trabajo_clave : '---',
            "ubicacion_token" => $estab_token,
            "ubicacion_alias" => "$estab_folio $estab_alias",
            "select_for_trabajador" => false,
          );
          $catalogo_cent_trab[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'cent_trab' => $catalogo_cent_trab
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

  public function actualizaCentroDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string',
        'registro_patronal_imss' => 'required|string',
        'riesgo_division' => 'required|string',
        'riesgo_grupo' => 'required|string',
        'riesgo_fraccion' => 'required|string',
        'riesgo_clave' => 'required|string',
        'descripcion' => 'required|string',
        'ubicacion' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];
        $registro_patronal_imss = $argumentos['registro_patronal_imss'];
        $riesgo_division = $argumentos['riesgo_division'];
        $riesgo_grupo = $argumentos['riesgo_grupo'];
        $riesgo_fraccion = $argumentos['riesgo_fraccion'];
        $riesgo_clave = $argumentos['riesgo_clave'];
        $descripcion = $argumentos['descripcion'];
        $ubicacion = $argumentos['ubicacion'];

        $OKRPImss = isset($registro_patronal_imss) && !empty($registro_patronal_imss) && preg_match($JwtAuth->filtroAlfaNumerico(),$registro_patronal_imss);
        $OKRiesgoDivision = isset($riesgo_division) && !is_null($riesgo_division) && preg_match($JwtAuth->filtroNumericoSimple(),$riesgo_division);
        $OKRiesgoGrupo = isset($riesgo_grupo) && !empty($riesgo_grupo) && preg_match($JwtAuth->filtroAlfaNumerico(),$riesgo_grupo);
        $OKRiesgoFraccion = isset($riesgo_fraccion) && !empty($riesgo_fraccion) && preg_match($JwtAuth->filtroAlfaNumerico(),$riesgo_fraccion);
        $OKRiesgoClave = isset($riesgo_clave) && !empty($riesgo_clave) && preg_match($JwtAuth->filtroAlfaNumerico(),$riesgo_clave);
        $OKDescripcion = isset($descripcion) && !empty($descripcion) && preg_match($JwtAuth->filtroAlfaNumerico(),$descripcion);
        $OKUbicacion = isset($ubicacion) && !empty($ubicacion);
        if ($OKRPImss && $OKRiesgoDivision && $OKRiesgoGrupo && $OKRiesgoFraccion && $OKRiesgoClave && $OKDescripcion && $OKUbicacion) {
          $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
          ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
          ->where("emp.empresa_token",$usuario->empresa_token)
          ->where("users.usuario_token",$usuario->user_token)
          ->get();

          foreach ($cTrabQuery as $vList) {
            //da_te_default_timezone_set('UTC');
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
            $ctrab_ubicacion = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$ubicacion)->value("id");
            $updateCTrab = DB::table("vhum_centros_de_trabajo_catalogo")
            ->where("centrotrab_status",TRUE)
            ->where("centrotrab_uuid",$vList->centrotrab_uuid)
            ->limit(1)->update(array(
              "centrotrab_descripcion" => $JwtAuth->encriptar($descripcion),
              "centrotrab_clave_registro_patronal_imss" => $registro_patronal_imss,
              "riesgo_trabajo_division" => $riesgo_division,
              "riesgo_trabajo_grupo" => $riesgo_grupo,
              "riesgo_trabajo_fraccion" => $riesgo_fraccion, 
              "riesgo_trabajo_clave" => $riesgo_clave,
              "centrotrab_ubicacion" => $ctrab_ubicacion,
            ));

            if ($updateCTrab) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab ha sido actualizado",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab no actualizado, intente más tarde o comuniquese a soporte",
              );
            }
          }
        } else {
          $mensaje_error = "";
          if (!$OKRPImss) $mensaje_error = "Error al registrar clave de registro patronal del IMSS, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKRiesgoDivision) $mensaje_error = "Error al registrar división de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKRiesgoGrupo) $mensaje_error = "Error al registrar grupo de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte";  
          if (!$OKRiesgoFraccion) $mensaje_error = "Error al registrar fracción de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte";  
          if (!$OKRiesgoClave) $mensaje_error = "Error al registrar clave de riesgos de trabajo, intentelo nuevamente o comuniquese a soporte";  
          if (!$OKDescripcion) $mensaje_error = "Error al registrar descripción de actividades, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKUbicacion) $mensaje_error = "Error al seleccionar ubicacion, intentelo nuevamente o comuniquese a soporte"; 
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
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

  public function catalogoEliminadosCentrosDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    $catalogo_cent_trab = array();
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where('c_trab.centrotrab_status',FALSE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($cTrabQuery as $vList) {
          //da_te_default_timezone_set('UTC');
          $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
          
          $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "estab.id", "c_trab.centrotrab_ubicacion")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->select('estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento')
					->first();
          $estab_token = $listaDirAlmacen ? $listaDirAlmacen->token_establecimiento : '';
          $estab_folio = $listaDirAlmacen ? 'ESTAB-'.$JwtAuth->generarFolio($listaDirAlmacen->folio_establecimiento).($listaDirAlmacen->post_folio != NULL ? '-'.$listaDirAlmacen->post_folio : '') : '';
          $estab_alias = $listaDirAlmacen ? $JwtAuth->desencriptar($listaDirAlmacen->alias_establecimiento) : '';

          $row = array(
            "centrotrab_uuid" => $vList->centrotrab_uuid,
            "folio_centro_trab" => $folio_centro_trab,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_contabilizacion),
            "descripcion" => $JwtAuth->desencriptar($vList->centrotrab_descripcion),
            "clave_registro_patronal_imss" => $vList->centrotrab_clave_registro_patronal_imss,
            "ubicacion_token" => $estab_token,
            "ubicacion_alias" => "$estab_folio $estab_alias",
            "centrotrab_fecha_delete" => gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_delete)
          );
          $catalogo_cent_trab[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'cent_trab' => $catalogo_cent_trab
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

  public function altaCentroDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("c_trab.centrotrab_status",TRUE)
        ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
        ->get();

        if (count($cTrabQuery) > 0) {
          foreach ($cTrabQuery as $vList) {
            //da_te_default_timezone_set('UTC');
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');

            $updateCTrab = DB::table("vhum_centros_de_trabajo_catalogo")
            ->where("centrotrab_status",TRUE)
            ->where("centrotrab_uuid",$vList->centrotrab_uuid)
            ->limit(1)->update(array(
              "centrotrab_baja" => FALSE,
              "centrotrab_causa_baja" => NULL,
              "centrotrab_fecha_baja" => NULL,
            ));

            if ($updateCTrab) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab ha sido dado de alta",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab no dado de alta, intente más tarde o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Centro de trabajo no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
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

  public function bajaCentroDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string',
        'baja_motivo' => 'required|string',
        'fecha_contabilizacion' => 'required|string'

      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];
        $baja_motivo = $argumentos['baja_motivo'];
        $fecha_contabilizacion = $argumentos['fecha_contabilizacion'];

        $OKBajaMotivo = isset($baja_motivo) && !empty($baja_motivo) && preg_match($JwtAuth->filtroAlfaNumerico(),$baja_motivo);
        $OKFechaContabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);

        if ($OKBajaMotivo && $OKFechaContabilizacion) {
          $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
          ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
          ->where("emp.empresa_token",$usuario->empresa_token)
          ->where("users.usuario_token",$usuario->user_token)
          ->get();

          foreach ($cTrabQuery as $vList) {
            //da_te_default_timezone_set('UTC');
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
            $updateCTrab = DB::table("vhum_centros_de_trabajo_catalogo")
            ->where("centrotrab_status",TRUE)
            ->where("centrotrab_uuid",$vList->centrotrab_uuid)
            ->limit(1)->update(array(
              "centrotrab_baja" => TRUE,
              "centrotrab_causa_baja" => $JwtAuth->encriptar($baja_motivo),
              "centrotrab_fecha_baja" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            ));

            if ($updateCTrab) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab ha sido dado de baja",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab no dado de baja, intente más tarde o comuniquese a soporte",
              );
            }
          }
        } else {
          $mensaje_error = "";
          if (!$OKBajaMotivo) $mensaje_error = "Error al registrar motivo de baja, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKFechaContabilizacion) $mensaje_error = "Error al registrar fecha de baja, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
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

  public function catalogoCentrosActivosDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    $catalogo_cent_trab = array();
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where('c_trab.centrotrab_baja',FALSE)
        ->where('c_trab.centrotrab_status',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($cTrabQuery as $vList) {
          //da_te_default_timezone_set('UTC');
          $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
          
          $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "estab.id", "c_trab.centrotrab_ubicacion")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->select('estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento')
					->first();
          $estab_token = $listaDirAlmacen ? $listaDirAlmacen->token_establecimiento : '';
          $estab_folio = $listaDirAlmacen ? 'ESTAB-'.$JwtAuth->generarFolio($listaDirAlmacen->folio_establecimiento).($listaDirAlmacen->post_folio != NULL ? '-'.$listaDirAlmacen->post_folio : '') : '';
          $estab_alias = $listaDirAlmacen ? $JwtAuth->desencriptar($listaDirAlmacen->alias_establecimiento) : '';

          $cTrabTrabs = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
          ->join("vhum_empleados_catalogo AS trab", "c_trab.id", "trab.centro_de_trabajo")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->count();

          $row = array(
            "centrotrab_uuid" => $vList->centrotrab_uuid,
            "folio_centro_trab" => $folio_centro_trab,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_contabilizacion),
            "descripcion" => $JwtAuth->desencriptar($vList->centrotrab_descripcion),
            "clave_registro_patronal_imss" => $vList->centrotrab_clave_registro_patronal_imss,
            "ubicacion_token" => $estab_token,
            "ubicacion_alias" => "$estab_folio $estab_alias",
            "select_for_trabajador" => false,
            "puede_eliminar" => $cTrabTrabs == 0 ? true : false,
          );
          $catalogo_cent_trab[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'cent_trab' => $catalogo_cent_trab
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

  public function catalogoCentrosInactivosDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    $catalogo_cent_trab = array();
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where('c_trab.centrotrab_baja',TRUE)
        ->where('c_trab.centrotrab_status',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($cTrabQuery as $vList) {
          //da_te_default_timezone_set('UTC');
          $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
          
          $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS estab")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "estab.id", "c_trab.centrotrab_ubicacion")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->select('estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento')
					->first();
          $estab_token = $listaDirAlmacen ? $listaDirAlmacen->token_establecimiento : '';
          $estab_folio = $listaDirAlmacen ? 'ESTAB-'.$JwtAuth->generarFolio($listaDirAlmacen->folio_establecimiento).($listaDirAlmacen->post_folio != NULL ? '-'.$listaDirAlmacen->post_folio : '') : '';
          $estab_alias = $listaDirAlmacen ? $JwtAuth->desencriptar($listaDirAlmacen->alias_establecimiento) : '';

          $cTrabTrabs = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
          ->join("vhum_empleados_catalogo AS trab", "c_trab.id", "trab.centro_de_trabajo")
          ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
          ->count();

          $row = array(
            "centrotrab_uuid" => $vList->centrotrab_uuid,
            "folio_centro_trab" => $folio_centro_trab,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_contabilizacion),
            "descripcion" => $JwtAuth->desencriptar($vList->centrotrab_descripcion),
            "clave_registro_patronal_imss" => $vList->centrotrab_clave_registro_patronal_imss,
            "ubicacion_token" => $estab_token,
            "ubicacion_alias" => "$estab_folio $estab_alias",
            "select_for_trabajador" => false,
            "baja_motivo" => $vList->centrotrab_baja ? $JwtAuth->desencriptar($vList->centrotrab_causa_baja) : '',
            "baja_fecha" => $vList->centrotrab_baja ? gmdate('Y-m-d H:i:s', $vList->centrotrab_fecha_baja) : '',
            "puede_eliminar" => $cTrabTrabs == 0 ? true : false,
          );
          $catalogo_cent_trab[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'cent_trab' => $catalogo_cent_trab
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

  public function eliminaCentroDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("c_trab.centrotrab_status",TRUE)
        ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
        ->get();

        if (count($cTrabQuery) > 0) {
          foreach ($cTrabQuery as $vList) {
            //da_te_default_timezone_set('UTC');
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
            
            $cTrabTrabs = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
            ->join("vhum_empleados_catalogo AS trab", "c_trab.id", "trab.centro_de_trabajo")
            ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
            ->count();
  
            if ($cTrabTrabs == 0) {
              $deleteCTrab = DB::table("vhum_centros_de_trabajo_catalogo")
              ->where("centrotrab_status",TRUE)
              ->where("centrotrab_uuid",$vList->centrotrab_uuid)
              ->limit(1)->update(array("centrotrab_status" => FALSE,"centrotrab_fecha_delete" => time()));
  
              if ($deleteCTrab) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => "Centro de trabajo con folio $folio_centro_trab ha sido eliminado",
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => "Centro de trabajo con folio $folio_centro_trab no eliminado, intente más tarde o comuniquese a soporte",
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Centro de trabajo no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
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

  public function restauraCentrosDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("c_trab.centrotrab_status",FALSE)
        ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
        ->get();

        if (count($cTrabQuery) > 0) {
          foreach ($cTrabQuery as $vList) {
            //da_te_default_timezone_set('UTC');
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
            
            $restauraCTrab = DB::table("vhum_centros_de_trabajo_catalogo")
            ->where("centrotrab_status",FALSE)
            ->where("centrotrab_uuid",$vList->centrotrab_uuid)
            ->limit(1)->update(array("centrotrab_status" => TRUE,"centrotrab_fecha_delete" => NULL));

            if ($restauraCTrab) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab ha sido restaurado",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab no restaurado, intente más tarde o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Centro de trabajo no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
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

  public function eliminacionPermanenteCentrosDeTrabajo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'centrotrab_uuid' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $centrotrab_uuid = $argumentos['centrotrab_uuid'];

        $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
        ->join("main_empresas AS emp", "c_trab.centrotrab_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("c_trab.centrotrab_status",FALSE)
        ->where("c_trab.centrotrab_uuid",$centrotrab_uuid)
        ->where("emp.empresa_token",$usuario->empresa_token)
        ->where("users.usuario_token",$usuario->user_token)
        ->get();

        if (count($cTrabQuery) > 0) {
          foreach ($cTrabQuery as $vList) {
            //da_te_default_timezone_set('UTC');
            $folio_centro_trab = 'CTRA-'.$JwtAuth->generarFolio($vList->centrotrab_folio).(!is_null($vList->centrotrab_sub_folio) ? '-'.$vList->centrotrab_sub_folio : '');
            
            $cTrabTrabs = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
            ->join("vhum_empleados_catalogo AS trab", "c_trab.id", "trab.centro_de_trabajo")
            ->where('c_trab.centrotrab_uuid',$vList->centrotrab_uuid)
            ->count();
  
            if ($cTrabTrabs == 0) {
              $deleteAcreedor = DB::table("vhum_centros_de_trabajo_catalogo")
              ->where("centrotrab_status",FALSE)
              ->where("centrotrab_uuid",$vList->centrotrab_uuid)
              ->limit(1)->delete();
  
              if ($deleteAcreedor) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => "Centro de trabajo con folio $folio_centro_trab ha sido eliminado",
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => "Centro de trabajo con folio $folio_centro_trab no eliminado, intente más tarde o comuniquese a soporte",
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Centro de trabajo con folio $folio_centro_trab no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Centro de trabajo no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
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
} 