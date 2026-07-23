<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\UMedidaModelo;
use Illuminate\Support\Facades\DB;
use PDF;

class INVENT_UMedidaController extends Controller{
  public function unidadesMedidaRegistrar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nombre' => 'required|string',
			'simbolo' => 'required|string',
			'categoria' => 'required|string',
			'sat_vinculo' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea registrar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $nombre = $request->input('nombre');
      $simbolo = $request->input('simbolo');
      $categoria = $request->input('categoria');
      $sat_vinculo = $request->input('sat_vinculo');

      $validate_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre);
      $validate_simbolo = isset($simbolo) && !empty($simbolo) && preg_match($JwtAuth->filtroAlfaNumerico(),$simbolo);
      $validate_categoria = isset($categoria) && !empty($categoria) && preg_match($JwtAuth->filtroAlfaNumerico(),$categoria);
      $validate_sat_vinculo = isset($sat_vinculo) && !empty($sat_vinculo) && preg_match($JwtAuth->filtroAlfaNumerico(),$sat_vinculo);

      if ($validate_nombre && $validate_simbolo && $validate_categoria) {// && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();

        if ($vEmp) {
          $nombreEncriptado = $JwtAuth->encriptar($nombre);
          $unidadExistente = UMedidaModelo::where('empresa', $vEmp->id)
          ->where('nombre', $nombreEncriptado)
          ->where('simbolo', $simbolo)
          ->where('categoria', $categoria)
          ->first();

          if (!$unidadExistente) {
            DB::beginTransaction();
            try {
              $maxFolioUnidad = DB::table('teci_unidad_medida')
              ->where('empresa', $vEmp->id)
              ->lockForUpdate()->max('folio_unidad_medida');
              $folioUnidad = $maxFolioUnidad ? $maxFolioUnidad + 1 : 1;
  
              $token_unidad_medida = $JwtAuth->encriptarToken(time(),$nombre,$simbolo,$categoria);
              $medidas = new UMedidaModelo();
              $medidas->token_unidad_medida = $token_unidad_medida;
              $medidas->folio_unidad_medida = $folioUnidad;
              $medidas->nombre = $nombreEncriptado;
              $medidas->simbolo = $simbolo;
              $medidas->categoria = $categoria;
              $medidas->sat_vinculo = $validate_sat_vinculo ? $sat_vinculo : NULL;
              $medidas->aplica_productos = FALSE;
              $medidas->aplica_servicios = FALSE;
              $medidas->status_unidad_medida = TRUE;
              $medidas->empresa = $vEmp->id;
              $medidas->save();
              DB::commit();
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'unidad de medida registrada con el folio UMED-'.$JwtAuth->generarFolio($folioUnidad)
              );
            } catch (\Exception $e) {
              DB::rollBack();
              return response()->json([
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Esta unidad de medida no fue registrada debido a errores internos, intente más tarde o comuníquese a soporte'
              ], 500);
            }
          } else {
            $dataMensaje = array(
              'status' => 'sucess',
              'code' => 409,
              'message' => 'Ya existe una unidad de medida con los mismos datos.'
            );
          }
        }
        
      } else {
        $mensaje_error_main = '';
        if (!$validate_nombre) {$mensaje_error_main = 'Error en nombre de unidad de medida, verifique su información o comuníquese a soporte';}
        if (!$validate_simbolo) {$mensaje_error_main = 'Error en simbolo de unidad de medida, verifique su información o comuníquese a soporte';}
        if (!$validate_categoria) {$mensaje_error_main = 'Error en categoria de unidad de medida, verifique su información o comuníquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaCatalogo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryUnidadMedida = UMedidaModelo::join("main_empresas AS emp", "teci_unidad_medida.empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where("teci_unidad_medida.status_unidad_medida",TRUE)
    ->where("emp.empresa_token",$empresa)
    ->where("users.usuario_token",$usuario)
    ->orderBy("teci_unidad_medida.id","DESC")->get();

    if ($queryUnidadMedida->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron unidades de medida registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaUMedida = array();      
      foreach ($queryUnidadMedida as $vMed) {
        $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
        $prodUMED = DB::table("in_egr_catalogo_productos AS catprod")
        ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->where(function($q) use ($nombre_umed) {
          $q->where("catprod.unidad_medida_entrada_clave", $nombre_umed)
            ->orWhere("catprod.unidad_medida_salida_clave", $nombre_umed);
        })
        ->exists();

        $servUMED = DB::table("in_egr_catalogo_servicios AS serv")
        ->join("main_empresas AS emp","serv.administrador","=","emp.id")
        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
        ->where('serv.unidad_medida_clave',$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();

        $buyUMED = DB::table("eegr_compras AS buy")
        ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
        ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("detBuy.unidad_medida",$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();

        $row = array(
          "token_unidad_medida" => $vMed->token_unidad_medida,
          "folio_unidad_medida" => "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida),
          "nombre" => $nombre_umed,
          "simbolo" => $vMed->simbolo,
          "categoria" => $vMed->categoria,
          "sat_vinculo" => $vMed->sat_vinculo ? $vMed->sat_vinculo : '',
          "sat_vinculo_complete" => $vMed->sat_vinculo ? $vMed->sat_vinculo.' '.$JwtAuth->getUnidadesDeMedidaSATApi($vMed->sat_vinculo) : '',
          "unidad_deshabilitada" => $vMed->umed_deshabilitada ? true : false,
          "unidad_deshabilitada_fecha" => $vMed->umed_deshabilitada ? date('d-m-Y H:i:s',$vMed->umed_deshabilitada_fecha) : '',
          "unidad_deshabilitada_by" => $vMed->umed_deshabilitada ? $vMed->umed_deshabilitada_by : '',
          "unidad_utilizada" => !$prodUMED && !$servUMED && !$buyUMED ? false : true,
          "aplica_productos" => $vMed->aplica_productos ? true : false,
          "aplica_servicios" => $vMed->aplica_servicios ? true : false
        );
        $listaUMedida[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listaUMedida' => $listaUMedida
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaEnabledCatalogo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

		$queryUnidadMedida = UMedidaModelo::join("main_empresas AS emp", "teci_unidad_medida.empresa", "=", "emp.id")
		->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
		->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
		->where([
      "teci_unidad_medida.umed_deshabilitada" => FALSE,
		  "teci_unidad_medida.status_unidad_medida" => TRUE,
		  "emp.empresa_token" => $empresa,
		  "users.usuario_token" => $usuario
    ])
		->orderBy("teci_unidad_medida.id","DESC")
    ->get();
    
    if ($queryUnidadMedida->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron unidades de medida registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaUMedida = array();
      foreach ($queryUnidadMedida as $vMed) {
        $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
        $prodUMED = DB::table("in_egr_catalogo_productos AS catprod")
        ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("emp.empresa_token", $empresa)
        ->where("users.usuario_token", $usuario)
        ->where(function($q) use ($nombre_umed) {
            $q->where("catprod.unidad_medida_entrada_clave", $nombre_umed)
              ->orWhere("catprod.unidad_medida_salida_clave", $nombre_umed);
        })
        ->exists();

        $servUMED = DB::table("in_egr_catalogo_servicios AS serv")
        ->join("main_empresas AS emp","serv.administrador","=","emp.id")
        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
        ->where('serv.unidad_medida_clave',$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();

        $buyUMED = DB::table("eegr_compras AS buy")
        ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
        ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("detBuy.unidad_medida",$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();

        $row = array(
          "token_unidad_medida" => $vMed->token_unidad_medida,
          "folio_unidad_medida" => "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida),
          "nombre" => $nombre_umed,
          "simbolo" => $vMed->simbolo,
          "categoria" => $vMed->categoria,
          "sat_vinculo" => $vMed->sat_vinculo ? $vMed->sat_vinculo : '',
          "sat_vinculo_complete" => $vMed->sat_vinculo ? $vMed->sat_vinculo.' '.$JwtAuth->getUnidadesDeMedidaSATApi($vMed->sat_vinculo) : '',
          "unidad_utilizada" => !$prodUMED && !$servUMED && !$buyUMED ? false : true,
          "aplica_productos" => $vMed->aplica_productos ? true : false,
          "aplica_servicios" => $vMed->aplica_servicios ? true : false
        );
        $listaUMedida[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listaUMedida' => $listaUMedida
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaUpdate(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_unidad_medida' => 'required|string',
      'nombre' => 'required|string',
			'simbolo' => 'required|string',
			'categoria' => 'required|string',
			'sat_vinculo' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea actualizar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_unidad_medida = $request->input('token_unidad_medida');
      $nombre = $request->input('nombre');
      $simbolo = $request->input('simbolo');
      $categoria = $request->input('categoria');
      $sat_vinculo = $request->input('sat_vinculo');
      
      $validate_token_unidad_medida = isset($token_unidad_medida) && !empty($token_unidad_medida);
      $validate_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre);
      $validate_simbolo = isset($simbolo) && !empty($simbolo) && preg_match($JwtAuth->filtroAlfaNumerico(),$simbolo);
      $validate_categoria = isset($categoria) && !empty($categoria) && preg_match($JwtAuth->filtroAlfaNumerico(),$categoria);
      $validate_sat_vinculo = isset($sat_vinculo) && !empty($sat_vinculo) && preg_match($JwtAuth->filtroAlfaNumerico(),$sat_vinculo);

      if ($validate_token_unidad_medida && $validate_nombre && $validate_simbolo && $validate_categoria) {
        $vMed = DB::table("teci_unidad_medida AS umed")
        ->join("main_empresas AS emp", "umed.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("umed.status_unidad_medida",TRUE)
        ->where("umed.token_unidad_medida",$token_unidad_medida)
        ->where("emp.empresa_token",$empresa)
        ->where("users.usuario_token",$usuario)
        ->select('umed.token_unidad_medida', 'umed.folio_unidad_medida', 'umed.nombre')
        ->first();
        
        if ($vMed->isEmpty()) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontraron unidades de medida registradas'
          );
        }

        $folio_umed = "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida);
        $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
        //echo $nombre_umed;
        $prodUMED = DB::table("in_egr_catalogo_productos AS catprod")
        ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("emp.empresa_token", $empresa)
        ->where("users.usuario_token", $usuario)
        ->where(function($q) use ($nombre_umed) {
          $q->where("catprod.unidad_medida_entrada_clave", $nombre_umed)
            ->orWhere("catprod.unidad_medida_salida_clave", $nombre_umed);
        })
        ->exists();
        if ($prodUMED) {
          return response()->json([
            'code' => 200,
            'status' => 'error',
            'message' => 'Unidad de medida no actualizada, se encuentra vinculada a productos registrados'
          ], 200);
        }
        
        $servUMED = DB::table("in_egr_catalogo_servicios AS serv")
        ->join("main_empresas AS emp","serv.administrador","=","emp.id")
        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
        ->where('serv.unidad_medida_clave',$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();
        if ($servUMED) {
          return response()->json([
            'code' => 200,
            'status' => 'error',
            'message' => 'Unidad de medida no actualizada, se encuentra vinculada a servicios registrados'
          ], 200);
        }
        
        $buyUMED = DB::table("eegr_compras AS buy")
        ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
        ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("detBuy.unidad_medida",$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();
        if ($buyUMED) {
          return response()->json([
            'code' => 200,
            'status' => 'error',
            'message' => 'Unidad de medida no actualizada, se encuentra vinculada a compras registradas'
          ], 200);
        }
        
        $update_u_medida = DB::table("teci_unidad_medida")
        ->where("token_unidad_medida",$vMed->token_unidad_medida)
        ->limit(1)->update(
          array(
            "nombre" => $JwtAuth->encriptar($nombre),
            "simbolo" => $simbolo,
            "categoria" => $categoria,
            "sat_vinculo" => $validate_sat_vinculo ? $sat_vinculo : NULL,
          )
        );

        if ($update_u_medida) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Unidad de medida con folio $folio_umed ha sido actualizada"
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Esta unidad de medida no fue actualizada debido a errores internos, intente más tarde o comuníquese a soporte'
          );
        }
      } else {
        $mensaje_error_main = '';
        if (!$validate_token_unidad_medida) {$mensaje_error_main = 'Error en unidad de medida seleccionada, verifique su información o comuníquese a soporte';}
        if (!$validate_nombre) {$mensaje_error_main = 'Error en nombre de unidad de medida, verifique su información o comuníquese a soporte';}
        if (!$validate_simbolo) {$mensaje_error_main = 'Error en simbolo de unidad de medida, verifique su información o comuníquese a soporte';}
        if (!$validate_categoria) {$mensaje_error_main = 'Error en categoria de unidad de medida, verifique su información o comuníquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaHabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_unidad_medida' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en unidad de medida seleccionada, verifique su información o comuníquese a soporte',
				'errors' => $validate->errors()
			);
    } else {
      $token_unidad_medida = $request->input('token_unidad_medida');
      
      $vMed = DB::table("teci_unidad_medida AS umed")
      ->join("main_empresas AS emp", "umed.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "umed.status_unidad_medida" => TRUE,
        "umed.token_unidad_medida" => $token_unidad_medida,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select('umed.token_unidad_medida', 'umed.folio_unidad_medida')
      ->first();

      if (!$vMed) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron unidades de medida registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $folio_umed = "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida);
        $delete_u_medida = DB::table("teci_unidad_medida")
        ->where("token_unidad_medida",$vMed->token_unidad_medida)
        ->limit(1)->update(
          array(
            "umed_deshabilitada" => FALSE,
            "umed_deshabilitada_fecha" => NULL,
            "umed_deshabilitada_by" => NULL,
          )
        );
  
        if ($delete_u_medida) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Unidad de medida con folio $folio_umed ha sido habilitada"
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Esta unidad de medida no fue habilitada debido a errores internos, intente más tarde o comuníquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaDeshabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_unidad_medida' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en unidad de medida seleccionada, verifique su información o comuníquese a soporte',
				'errors' => $validate->errors()
			);
    } else {
      $token_unidad_medida = $request->input('token_unidad_medida');
      $vMed = DB::table("teci_unidad_medida AS umed")
      ->join("main_empresas AS emp", "umed.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "umed.status_unidad_medida" => TRUE,
        "umed.token_unidad_medida" => $token_unidad_medida,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select('umed.token_unidad_medida', 'umed.folio_unidad_medida', 'umed.nombre')
      ->first();
      
      if (!$vMed) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron unidades de medida registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
        //echo $nombre_umed;
        $prodUMED = DB::table("in_egr_catalogo_productos AS catprod")
        ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("emp.empresa_token", $empresa)
        ->where("users.usuario_token", $usuario)
        ->where(function($q) use ($nombre_umed) {
            $q->where("catprod.unidad_medida_entrada_clave", $nombre_umed)
              ->orWhere("catprod.unidad_medida_salida_clave", $nombre_umed);
        })
        ->exists();
        if ($prodUMED) {
          return response()->json([
            'code' => 200,
            'status' => 'error',
            'message' => 'Unidad de medida no deshabilitada, se encuentra vinculada a productos registrados'
          ], 200);
        }

        $servUMED = DB::table("in_egr_catalogo_servicios AS serv")
        ->join("main_empresas AS emp","serv.administrador","=","emp.id")
        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
        ->where('serv.unidad_medida_clave',$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();
        if ($servUMED) {
          return response()->json([
            'code' => 200,
            'status' => 'error',
            'message' => 'Unidad de medida no deshabilitada, se encuentra vinculada a servicios registrados'
          ], 200);
        }

        $buyUMED = DB::table("eegr_compras AS buy")
        ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
        ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where("detBuy.unidad_medida",$nombre_umed)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->exists();
        if ($buyUMED) {
          return response()->json([
            'code' => 200,
            'status' => 'error',
            'message' => 'Unidad de medida no deshabilitada, se encuentra vinculada a compras registradas'
          ], 200);
        }
        
        $userr = DB::table("teci_usuarios_catalogo")->where("usuario_token",$usuario)->value("id");
        $folio_umed = "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida);
        $delete_u_medida = DB::table("teci_unidad_medida")
        ->where("token_unidad_medida",$vMed->token_unidad_medida)
        ->limit(1)->update(
          array(
            "umed_deshabilitada" => TRUE,
            "umed_deshabilitada_fecha" => time(),
            "umed_deshabilitada_by" => $userr,
          )
        );
  
        if ($delete_u_medida) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Unidad de medida con folio $folio_umed ha sido deshabilitada"
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Esta unidad de medida no fue deshabilitada debido a errores internos, intente más tarde o comuníquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaEliminarPapelera(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_unidad_medida' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en unidad de medida seleccionada, verifique su información o comuníquese a soporte',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_unidad_medida = $request->input('token_unidad_medida');
      
      $vMed = DB::table("teci_unidad_medida AS umed")
      ->join("main_empresas AS emp", "umed.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("umed.status_unidad_medida",TRUE)
      ->where("umed.token_unidad_medida",$token_unidad_medida)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->select('umed.token_unidad_medida', 'umed.folio_unidad_medida', 'umed.nombre')
      ->first();
      
      if (!$vMed) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron unidades de medida registradas'
        ], 200);
      } 
      
      $JwtAuth = new \App\Helpers\JwtAuth();
      $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
      //echo $nombre_umed;
      $prodUMED = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("emp.empresa_token", $empresa)
      ->where("users.usuario_token", $usuario)
      ->where(function($q) use ($nombre_umed) {
          $q->where("catprod.unidad_medida_entrada_clave", $nombre_umed)
            ->orWhere("catprod.unidad_medida_salida_clave", $nombre_umed);
      })
      ->exists();
      if ($prodUMED) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'Unidad de medida no eliminada, se encuentra vinculada a productos registrados'
        ], 200);
      }

      $servUMED = DB::table("in_egr_catalogo_servicios AS serv")
      ->join("main_empresas AS emp","serv.administrador","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where('serv.unidad_medida_clave',$nombre_umed)
      ->where('emp.empresa_token',$empresa)
      ->where('users.usuario_token',$usuario)
      ->exists();
      if ($servUMED) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'Unidad de medida no eliminada, se encuentra vinculada a servicios registrados'
        ], 200);
      }

      $buyUMED = DB::table("eegr_compras AS buy")
      ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("detBuy.unidad_medida",$nombre_umed)
      ->where('emp.empresa_token',$empresa)
      ->where('users.usuario_token',$usuario)
      ->exists();
      if ($buyUMED) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'Unidad de medida no eliminada, se encuentra vinculada a compras registradas'
        ], 200);
      }
      
      $folio_umed = "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida);
      $delete_u_medida = DB::table("teci_unidad_medida")
      ->where("token_unidad_medida",$vMed->token_unidad_medida)
      ->limit(1)->update(
        array(
          "status_unidad_medida" => FALSE,
          "fecha_delete_unidad_medida" => time(),
        )
      );

      if ($delete_u_medida) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => "Unidad de medida con folio $folio_umed ha sido eliminada"
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Esta unidad de medida no fue eliminada debido a errores internos, intente más tarde o comuníquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaEliminadasCatalogo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
		$queryUnidadMedida = UMedidaModelo::join("main_empresas AS emp", "teci_unidad_medida.empresa", "=", "emp.id")
		->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
		->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
		->where([
      "teci_unidad_medida.status_unidad_medida" => FALSE, 
      "emp.empresa_token" => $empresa, 
      "users.usuario_token" => $usuario
    ])
		->orderBy("teci_unidad_medida.id","DESC")
    ->get();

    if ($queryUnidadMedida->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron unidades de medida registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaUMedida = array();
      foreach ($queryUnidadMedida as $vMed) {
        $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
        $row = array(
          "token_unidad_medida" => $vMed->token_unidad_medida,
          "folio_unidad_medida" => "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida),
          "nombre" => $nombre_umed,
          "simbolo" => $vMed->simbolo,
          "categoria" => $vMed->categoria,
          "sat_vinculo" => $vMed->sat_vinculo ? $vMed->sat_vinculo : '',
          "sat_vinculo_complete" => $vMed->sat_vinculo ? $vMed->sat_vinculo.' '.$JwtAuth->getUnidadesDeMedidaSATApi($vMed->sat_vinculo) : '',
          "fecha_eliminacion" => date('d-m-Y H:i:s',$vMed->fecha_delete_unidad_medida),
          "aplica_productos" => $vMed->aplica_productos ? true : false,
          "aplica_servicios" => $vMed->aplica_servicios ? true : false
        );
        $listaUMedida[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listaUMedida' => $listaUMedida
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaRestaurar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_unidad_medida' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en unidad de medida seleccionada, verifique su información o comuníquese a soporte',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_unidad_medida = $request->input('token_unidad_medida');
      
      $vMed = DB::table("teci_unidad_medida AS umed")
      ->join("main_empresas AS emp", "umed.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("umed.status_unidad_medida",FALSE)
      ->where("umed.token_unidad_medida",$token_unidad_medida)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->select('umed.token_unidad_medida', 'umed.folio_unidad_medida')
      ->first();
      
      if (!$vMed) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron unidades de medida registradas'
        ], 200);
      } 

      $folio_umed = "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida);
      $restore_u_medida = DB::table("teci_unidad_medida")
      ->where("token_unidad_medida",$vMed->token_unidad_medida)
      ->limit(1)->update(
        array(
          "status_unidad_medida" => TRUE,
          "fecha_delete_unidad_medida" => NULL,
        )
      );

      if ($restore_u_medida) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => "Unidad de medida con folio $folio_umed ha sido restaurada"
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Esta unidad de medida no fue restaurada debido a errores internos, intente más tarde o comuníquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function unidadesMedidaEliminacionPermanente(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_unidad_medida' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Error en unidad de medida seleccionada, verifique su información o comuníquese a soporte',
				'errors' => $validate->errors()
			);
    } else {
      $token_unidad_medida = $request->input('token_unidad_medida');
      
			$vMed = DB::table("teci_unidad_medida AS umed")
      ->join("main_empresas AS emp", "umed.empresa", "=", "emp.id")
			->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
			->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
			->where("umed.status_unidad_medida",FALSE)
      ->where("umed.token_unidad_medida",$token_unidad_medida)
      ->where("emp.empresa_token",$empresa)
      ->where("users.usuario_token",$usuario)
      ->select('umed.token_unidad_medida', 'umed.folio_unidad_medida', 'umed.nombre')
      ->first();
      
      if (!$vMed) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron unidades de medida registradas'
        ], 200);
      } 

      $JwtAuth = new \App\Helpers\JwtAuth();
      $folio_umed = "UMED-".$JwtAuth->generarFolio($vMed->folio_unidad_medida);
      $nombre_umed = $JwtAuth->desencriptar($vMed->nombre);
      $prodUMED = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("emp.empresa_token", $empresa)
      ->where("users.usuario_token", $usuario)
      ->where(function($q) use ($nombre_umed) {
        $q->where("catprod.unidad_medida_entrada_clave", $nombre_umed)
          ->orWhere("catprod.unidad_medida_salida_clave", $nombre_umed);
      })
      ->exists();
      if ($prodUMED) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'Unidad de medida no eliminada, se encuentra vinculada a productos registrados'
        ], 200);
      }

      $servUMED = DB::table("in_egr_catalogo_servicios AS serv")
      ->join("main_empresas AS emp","serv.administrador","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where('serv.unidad_medida_clave',$nombre_umed)
      ->where('emp.empresa_token',$empresa)
      ->where('users.usuario_token',$usuario)
      ->exists();
      if ($servUMED) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'Unidad de medida no eliminada, se encuentra vinculada a servicios registrados'
        ], 200);
      }

      $buyUMED = DB::table("eegr_compras AS buy")
      ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where("detBuy.unidad_medida",$nombre_umed)
      ->where('emp.empresa_token',$empresa)
      ->where('users.usuario_token',$usuario)
      ->exists();
      if ($buyUMED) {
        return response()->json([
          'code' => 200,
          'status' => 'error',
          'message' => 'Unidad de medida no eliminada, se encuentra vinculada a compras registradas'
        ], 200);
      }
      
      $delete_u_medida = DB::table("teci_unidad_medida")
      ->where("token_unidad_medida",$vMed->token_unidad_medida)
      ->limit(1)->delete();

      if ($delete_u_medida) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => "Unidad de medida con folio $folio_umed ha sido eliminada"
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Esta unidad de medida no fue eliminada debido a errores internos, intente más tarde o comuníquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

	public function clasificacionMedidaSat(){
		$listMedidas = DB::select("SELECT representa FROM unidad_medida GROUP BY representa ORDER BY representa ASC");
		//return $listMedidas;
		//echo 'hola';
		return response()->json([
			'listMedidas' => $listMedidas,
			'codigo' => 200,
			'status' => 'success'
		]);
	}

	public function listaUnidadesMedida(){
		$listMedidas = UMedidaModelo::all();
		return response()->json([
			'listMedidas' => $listMedidas,
			'codigo' => 200,
			'status' => 'success'
		]);
	}

	public function pdfHtml(){
		echo $_GET['tokenRequi'];
		$JwtAuth = new \JwtAuth();
		$listMedidas = UMedidaModelo::all();

		$pdfGenerado = $JwtAuth->generaPdf("information-fnz", "compras", "requisiciones", "alta de requisición");
		$dompdf = \PDF::loadHtml($pdfGenerado);
		$dompdf->setPaper("A2", "portrait");
		//$contenidoPDF = $dompdf->output();
		//$contenidoPDF = $dompdf->download('cert.pdf');
		$contenidoPDF = $dompdf->stream();

		return $contenidoPDF;
		/*return response()->json([
            'listMedidas' => $listMedidas,
            'codigo' => 200,
            'status' => 'success'
        ]);*/
	}

	public function medidasSat(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'classifUmedida' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $classifUmedida = $request->input('classifUmedida');
      $listMedidas = UMedidaModelo::select('token_unidad_medida', 'unidad_medida', 'sat_clave')
      ->where('representa',$classifUmedida);
      //return $listMedidas;
      //echo 'hola';
      return response()->json([
        'listMedidas' => $listMedidas,
        'codigo' => 200,
        'status' => 'success'
      ]);
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function medidasSatServicios(){
		$JwtAuth = new \JwtAuth();
		$listMedidas = UMedidaModelo::select('token_unidad_medida', 'unidad_medida', 'sat_clave', 'representa')
			->where([
				'serv_bool' => TRUE,
			])->get();
		return response()->json([
			'listMedidas' => $listMedidas,
			'codigo' => 200,
			'status' => 'success'
		]);
	}

	public function postMedidasSatServicios(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'clave' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $clave = $request->input('clave');
      
      $listMedidas = UMedidaModelo::select('token_unidad_medida', 'unidad_medida', 'sat_clave', 'representa')
      ->where('unidad_medida', 'LIKE', "%$clave%")
      ->where(['serv_bool' => TRUE])
      ->orwhere('sat_clave', 'LIKE', "%$clave%")
      ->where(['serv_bool' => TRUE])
      ->orwhere('representa', 'LIKE', "%$clave%")
      ->where(['serv_bool' => TRUE])
      ->get();

      $dataMensaje = array(
        'listMedidas' => $listMedidas,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

  public function catalogoPrdServ() {
    $url = 'https://raw.githubusercontent.com/phpcfdi/resources-sat-pys/main/data/pys.json';
    $json = file_get_contents($url);
    return response($json)->header('Content-Type', 'application/json');
  }
}