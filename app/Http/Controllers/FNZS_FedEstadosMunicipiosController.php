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
use App\Models\FedEstadosMunicipiosModelo;
use App\Services\FirebaseService;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FNZS_FedEstadosMunicipiosController extends Controller{
  public function fedEstMunRegistro(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fecha_contabilizacion' => 'required|string', 
        'fed_est_mun_name' => 'required|string', 
        'fed_est_mun_rfc' => 'required|string', 
        'fed_est_mun_observaciones' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fed_est_mun_name = $parametrosArray['fed_est_mun_name'];
        $fed_est_mun_rfc = $parametrosArray['fed_est_mun_rfc'];
        $fed_est_mun_observaciones = $parametrosArray['fed_est_mun_observaciones'];

        $OKFechaCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $OKFedEstMunName = isset($fed_est_mun_name) && !empty($fed_est_mun_name) && preg_match($JwtAuth->filtroAlfaNumerico(),$fed_est_mun_name);
        $OKFedEstMunRfc = isset($fed_est_mun_rfc) && !empty($fed_est_mun_rfc) && preg_match($JwtAuth->filtroAlfaNumerico(),$fed_est_mun_rfc);
        $OKFedEstMunObservacion = isset($fed_est_mun_observaciones) && !empty($fed_est_mun_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$fed_est_mun_observaciones);

        if ($OKFechaCont && $OKFedEstMunName && $OKFedEstMunRfc && $OKFedEstMunObservacion) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT fedest.fed_est_mun_folio+1 AS folio,fed_est_mun_subfolio FROM fnzs_catalogos_fed_estados_municipios AS fedest JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE fedest.fed_est_mun_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
              ORDER BY fedest.fed_est_mun_folio DESC LIMIT 1",[$usuario->empresa_token,$usuario->user_token]);
            //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT fed_est_mun_subfolio FROM fnzs_catalogos_fed_estados_municipios WHERE id = (SELECT Max(fedest.id) FROM fnzs_catalogos_fed_estados_municipios AS fedest JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fedest.fed_est_mun_empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                  
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->fed_est_mun_subfolio);
                  $folio_nuevo = 1;
              } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }

            //ALTER TABLE `fnzs_catalogos_fed_estados_municipios` ADD `fed_est_mun_folio` INT(5) NOT NULL AFTER `fed_est_mun_token`, ADD `fed_est_mun_subfolio` TEXT NULL AFTER `fed_est_mun_folio`, 
            //ADD `fed_est_mun_fecha_contabilizacion` INT(10) UNSIGNED NOT NULL AFTER `fed_est_mun_subfolio`;

            $folio_fem = 'FEM-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
            $tokenImpuestosNomina = $JwtAuth->encriptarToken($fecha_contabilizacion.$fed_est_mun_name.$fed_est_mun_rfc.$fed_est_mun_observaciones);

            $newFed = new FedEstadosMunicipiosModelo();
            $newFed->fed_est_mun_token = $tokenImpuestosNomina;
            $newFed->fed_est_mun_folio = $folio_nuevo;
        	  $newFed->fed_est_mun_subfolio = $post_folio;
            $newFed->fed_est_mun_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $newFed->fed_est_mun_entidad = $JwtAuth->encriptar($fed_est_mun_name);
            $newFed->fed_est_mun_rfc = $fed_est_mun_rfc;
            $newFed->fed_est_mun_observaciones = $JwtAuth->encriptar($fed_est_mun_observaciones);
            $newFed->fed_est_mun_empresa = $vEmp->id;
            $newFed->fed_est_mun_status = TRUE;

            $savedFed = $newFed->save();
            
            if ($savedFed) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Federación estado-municipio registrado satisfactoriamente con el folio $folio_fem"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Datos generales de este reporte no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          }
        } else {
          $mensaje_error = "";
          if (!$OKFechaCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFedEstMunName) $mensaje_error = "Error al registrar entidad, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFedEstMunRfc) $mensaje_error = "Error al registrar rfc, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFedEstMunObservacion) $mensaje_error = "Error al registrar observaciones, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function fedEstMunList(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $lista_federaciones = array();

    $systemFedEstMun = FedEstadosMunicipiosModelo::where([
      'fed_est_mun_protegido' => TRUE,
      'fed_est_mun_status' => TRUE
    ])
    ->select(
      'id',
      'fed_est_mun_token',
      'fed_est_mun_folio',
      'fed_est_mun_subfolio',
      'fed_est_mun_fecha_contabilizacion',
      'fed_est_mun_entidad',
      'fed_est_mun_rfc',
      'fed_est_mun_observaciones',
      'fed_est_mun_empresa',
      'fed_est_mun_protegido'
    );

    $fedEstMunNoSystem = FedEstadosMunicipiosModelo::where([
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_protegido' => FALSE,
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => TRUE
    ])
    ->whereIn('fed_est_mun_empresa', function ($q) use ($empresa) {
      $q->select('id')->from('main_empresas')->where('empresa_token', $empresa);
    })
    ->select(
      'fnzs_catalogos_fed_estados_municipios.id',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_token',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_folio',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_subfolio',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_fecha_contabilizacion',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_entidad',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_rfc',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_observaciones',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa',
      'fnzs_catalogos_fed_estados_municipios.fed_est_mun_protegido'
    );

    $queryFedUnion = $systemFedEstMun->unionAll($fedEstMunNoSystem);

    $queryFedEstMun = \DB::query()
    ->fromSub($queryFedUnion, 'fed_est_mun')
    ->orderBy('id', 'DESC')
    ->get();

    foreach ($queryFedEstMun as $vFed) {
      $esProtegido = (bool) $vFed->fed_est_mun_protegido;
      $totalRelISN = DB::table("fnzs_catalogos_fed_estados_municipios AS fedEst")
      ->join("vhum_nominas_impuestos AS nomImp", "fedEst.id", "nomImp.nomi_imp_estado")
      ->where('fedEst.fed_est_mun_token',$vFed->fed_est_mun_token)
      ->count();
      $row = array(
        "fed_est_mun_token" => $vFed->fed_est_mun_token,
        "fed_est_mun_folio" => 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : ''),
        "fed_est_mun_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vFed->fed_est_mun_fecha_contabilizacion),
        "fed_est_mun_entidad" => $JwtAuth->desencriptar($vFed->fed_est_mun_entidad),
        "fed_est_mun_rfc" => $vFed->fed_est_mun_rfc,
        "fed_est_mun_observaciones" => $JwtAuth->desencriptar($vFed->fed_est_mun_observaciones),
        "fed_est_mun_protegido" => $vFed->fed_est_mun_protegido ? true : false,
        "puede_eliminar" => (!$esProtegido && $totalRelISN == 0),
      );
      $lista_federaciones[] = $row;
    }
    
    $dataMensaje = array(
      'status' => 'success',
      'code' => 200,
      'federaciones' => $lista_federaciones
    );
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function fedEstMunDetalle(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fed_est_mun_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fed_est_mun_token = $parametrosArray['fed_est_mun_token'];
        $lista_federaciones = array();
        $queryFedEstMun = FedEstadosMunicipiosModelo::join("main_empresas AS emp", "fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa", "emp.id")
        ->where([
          'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => TRUE,
          'fnzs_catalogos_fed_estados_municipios.fed_est_mun_token' => $fed_est_mun_token,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->get();

        foreach ($queryFedEstMun as $vFed) {
          $row = array(
            "fed_est_mun_token" => $vFed->fed_est_mun_token,
            "fed_est_mun_folio" => 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : ''),
            "fed_est_mun_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vFed->fed_est_mun_fecha_contabilizacion),
            "fed_est_mun_fecha_contabilizacion_edit" => date('Y-m-d',$vFed->fed_est_mun_fecha_contabilizacion),
            "fed_est_mun_entidad" => $JwtAuth->desencriptar($vFed->fed_est_mun_entidad),
            "fed_est_mun_rfc" => $vFed->fed_est_mun_rfc,
            "fed_est_mun_observaciones" => $JwtAuth->desencriptar($vFed->fed_est_mun_observaciones),
            "puede_eliminar" => false,
          );
          $lista_federaciones[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'federaciones' => $lista_federaciones
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function fedEstMunActualiza(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fed_est_mun_token' => 'required|string',
        'fecha_contabilizacion' => 'required|string', 
        'fed_est_mun_name' => 'required|string', 
        'fed_est_mun_rfc' => 'required|string', 
        'fed_est_mun_observaciones' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fed_est_mun_token = $parametrosArray['fed_est_mun_token'];
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $fed_est_mun_name = $parametrosArray['fed_est_mun_name'];
        $fed_est_mun_rfc = $parametrosArray['fed_est_mun_rfc'];
        $fed_est_mun_observaciones = $parametrosArray['fed_est_mun_observaciones'];

        $OKFedEstMunToken = isset($fed_est_mun_token) && !empty($fed_est_mun_token);
        $OKFechaCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $OKFedEstMunName = isset($fed_est_mun_name) && !empty($fed_est_mun_name) && preg_match($JwtAuth->filtroAlfaNumerico(),$fed_est_mun_name);
        $OKFedEstMunRfc = isset($fed_est_mun_rfc) && !empty($fed_est_mun_rfc) && preg_match($JwtAuth->filtroAlfaNumerico(),$fed_est_mun_rfc);
        $OKFedEstMunObservacion = isset($fed_est_mun_observaciones) && !empty($fed_est_mun_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$fed_est_mun_observaciones);

        if ($OKFedEstMunToken && $OKFechaCont && $OKFedEstMunName && $OKFedEstMunRfc && $OKFedEstMunObservacion) {
          $queryFedEstMun = FedEstadosMunicipiosModelo::join("main_empresas AS emp", "fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa", "emp.id")
          ->where([
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => TRUE,
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_token' => $fed_est_mun_token,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();
  
          foreach ($queryFedEstMun as $vFed) {
            $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : '');
            $updateFedEstMun = FedEstadosMunicipiosModelo::updateOrCreate(
              ['fed_est_mun_token' => $vFed->fed_est_mun_token],
              [
                'fed_est_mun_fecha_contabilizacion' => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                'fed_est_mun_entidad' => $JwtAuth->encriptar($fed_est_mun_name),
                'fed_est_mun_rfc' => $fed_est_mun_rfc,
                'fed_est_mun_observaciones' => $JwtAuth->encriptar($fed_est_mun_observaciones)
              ]
            );

            if ($updateFedEstMun) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Federación estado-municipio con el folio $fed_est_mun_folio ha sido actualizado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Datos generales de este reporte no fueron actualizados debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          }
        } else {
          $mensaje_error = "";
          if (!$OKFedEstMunToken) $mensaje_error = "Error al seleccionar registro a actualizar, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFechaCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFedEstMunName) $mensaje_error = "Error al registrar entidad, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFedEstMunRfc) $mensaje_error = "Error al registrar rfc, intentelo nuevamente o comuniquese a soporte";
          if (!$OKFedEstMunObservacion) $mensaje_error = "Error al registrar observaciones, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }  

  public function fedEstMunEliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fed_est_mun_token' => 'required|string', 
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fed_est_mun_token = $parametrosArray['fed_est_mun_token'];

        $OKFedEstMunToken = isset($fed_est_mun_token) && !empty($fed_est_mun_token);        
        if ($OKFedEstMunToken) {
          $queryFedEstMun = FedEstadosMunicipiosModelo::join("main_empresas AS emp", "fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa", "emp.id")
          ->where([
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => TRUE,
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_token' => $fed_est_mun_token,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();
          
          foreach ($queryFedEstMun as $vFed) {
            $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : '');

            $queryDeleteFedEstMun = DB::table('fnzs_catalogos_fed_estados_municipios')
            ->where('fed_est_mun_token',$vFed->fed_est_mun_token)
            ->limit(1)->update(array(
              "fed_est_mun_status" => FALSE,
              "fed_est_mun_fecha_delete" => time(),
            ));

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Federación estado-municipio con el folio $fed_est_mun_folio ha sido eliminado satisfactoriamente"
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => 'Error al seleccionar registro a eliminar, intentelo nuevamente o comuniquese a soporte'
          );
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 

  public function fedEstMunDeletedList(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $lista_federaciones = array();
        $queryFedEstMun = FedEstadosMunicipiosModelo::join("main_empresas AS emp", "fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa", "emp.id")
        ->where([
          'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => FALSE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('fnzs_catalogos_fed_estados_municipios.id', 'DESC')->get();

        foreach ($queryFedEstMun as $vFed) {
          $totalRelISN = DB::table("fnzs_catalogos_fed_estados_municipios AS fedEst")
          ->join("vhum_nominas_impuestos AS nomImp", "fedEst.id", "nomImp.nomi_imp_estado")
          ->where('fedEst.fed_est_mun_token',$vFed->fed_est_mun_token)
          ->count();
          $row = array(
            "fed_est_mun_token" => $vFed->fed_est_mun_token,
            "fed_est_mun_folio" => 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : ''),
            "fed_est_mun_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vFed->fed_est_mun_fecha_contabilizacion),
            "fed_est_mun_entidad" => $JwtAuth->desencriptar($vFed->fed_est_mun_entidad),
            "fed_est_mun_rfc" => $vFed->fed_est_mun_rfc,
            "fed_est_mun_observaciones" => $JwtAuth->desencriptar($vFed->fed_est_mun_observaciones),
            "puede_eliminar" => $totalRelISN == 0 ? true : false,
          );
          $lista_federaciones[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'federaciones' => $lista_federaciones
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function fedEstMunRestaurar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fed_est_mun_token' => 'required|string', 
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fed_est_mun_token = $parametrosArray['fed_est_mun_token'];

        $OKFedEstMunToken = isset($fed_est_mun_token) && !empty($fed_est_mun_token);        
        if ($OKFedEstMunToken) {
          $queryFedEstMun = FedEstadosMunicipiosModelo::join("main_empresas AS emp", "fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa", "emp.id")
          ->where([
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => FALSE,
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_token' => $fed_est_mun_token,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();
          
          foreach ($queryFedEstMun as $vFed) {
            $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : '');

            $queryRestoreFedEstMun = DB::table('fnzs_catalogos_fed_estados_municipios')
            ->where('fed_est_mun_token',$vFed->fed_est_mun_token)
            ->limit(1)->update(array(
              "fed_est_mun_status" => TRUE,
              "fed_est_mun_fecha_delete" => NULL,
            ));

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Federación estado-municipio con el folio $fed_est_mun_folio ha sido restaurado satisfactoriamente"
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => 'Error al seleccionar registro a eliminar, intentelo nuevamente o comuniquese a soporte'
          );
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 

  public function fedEstMunEliminacionPerm(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'fed_est_mun_token' => 'required|string', 
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $fed_est_mun_token = $parametrosArray['fed_est_mun_token'];

        $OKFedEstMunToken = isset($fed_est_mun_token) && !empty($fed_est_mun_token);        
        if ($OKFedEstMunToken) {
          $queryFedEstMun = FedEstadosMunicipiosModelo::join("main_empresas AS emp", "fnzs_catalogos_fed_estados_municipios.fed_est_mun_empresa", "emp.id")
          ->where([
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_status' => FALSE,
            'fnzs_catalogos_fed_estados_municipios.fed_est_mun_token' => $fed_est_mun_token,
            'emp.empresa_token' => $usuario->empresa_token,
          ])
          ->get();
          
          foreach ($queryFedEstMun as $vFed) {
            $fed_est_mun_folio = 'FEM-'.$JwtAuth->generarFolio($vFed->fed_est_mun_folio).(!is_null($vFed->fed_est_mun_subfolio) ? '-'.$vFed->fed_est_mun_subfolio : '');

            $queryDeleteFedEstMun = DB::table('fnzs_catalogos_fed_estados_municipios')
            ->where('fed_est_mun_token',$vFed->fed_est_mun_token)
            ->limit(1)->delete();

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Federación estado-municipio con el folio $fed_est_mun_folio ha sido eliminado satisfactoriamente"
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => 'Error al seleccionar registro a eliminar, intentelo nuevamente o comuniquese a soporte'
          );
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 
}