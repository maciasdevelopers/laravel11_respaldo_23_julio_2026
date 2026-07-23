<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\PedimentosModelo;
use QRCode;

class INVENT_PedimentosController extends Controller{
	public function registraPedimento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'fechaPedim' => 'required|string',
			'numeroPedim' => 'required|string',
			'aduana' => 'required|string',
			'comentarios' => 'required|string',
			'nameEvidencia' => 'nullable|string',
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
      $fechaPedim = $request->input('fechaPedim');
      $numeroPedim = $request->input('numeroPedim');
      $aduana = $request->input('aduana');
      $comentarios = $request->input('comentarios');
      $doc_evidencia = $request->file('imagenAltaPdfevidenciapedim');
      
      $valida_fechaPedim = isset($fechaPedim) && !empty($fechaPedim) && preg_match($JwtAuth->filtroFecha(), $fechaPedim);
      $valida_numeroPedim = isset($numeroPedim) && !empty($numeroPedim) && preg_match($JwtAuth->filtroRfc(), $numeroPedim);
      $valida_aduana = isset($aduana) && !empty($aduana) && preg_match($JwtAuth->filtroAlfaNumerico(), $aduana);
      $valida_comentarios = isset($comentarios) && !empty($comentarios) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios);

      if ($valida_fechaPedim && $valida_numeroPedim && $valida_aduana && $valida_comentarios) {
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
          DB::beginTransaction();
          try {
            //da_te_default_timezone_set($vEmp->zona_horaria);
            $fecha_sistema_pedim = time();
  
            $maxFolioPedim = DB::table('inventarios_catalogo_pedimento_aduanal')
            ->where('empresa', $vEmp->id)
            ->lockForUpdate()->max('folio_pedimento');
            $folioPedim = $maxFolioPedim ? $maxFolioPedim + 1 : 1;
  
            $tokenPedim = $JwtAuth->encriptarToken(time().$fechaPedim.$numeroPedim.$comentarios);
            
            $newPedim = new PedimentosModelo();
            $newPedim->token_pedimento = $tokenPedim;
            $newPedim->folio_pedimento = $folioPedim;
            $newPedim->fecha_sistema_pedim = $fecha_sistema_pedim;
            $newPedim->fecha_importacion = $JwtAuth->convierteFechaEpoc($fechaPedim);
            $newPedim->numero_pedimento = $JwtAuth->encriptar($numeroPedim);
            $newPedim->aduana = $JwtAuth->encriptar($aduana);
            $newPedim->comentarios = $JwtAuth->encriptar($comentarios);
            $newPedim->empresa = $vEmp->id;
            $newPedim->status_pedimento = TRUE;
            $newPedim->fecha_delete_pedimento = '';
            $savednewPedim = $newPedim->save();
            $obtenPedimento = $newPedim->id;
  
            if (file_exists($doc_evidencia)) {
              $filepath = $vEmp->root_tkn."/0002-cpp/catalogos/pedimentos/".$JwtAuth->generarFolio($folioPedim).'-'.$fecha_sistema_pedim."/";
              !file_exists(storage_path("/root/".$filepath)) ? Storage::disk('root')->makeDirectory($filepath, 0777, true, true) : NULL;
              $doc_nombre = $JwtAuth->generarFolio($folioPedim).'-'.$fecha_sistema_pedim.'-'.$doc_evidencia->getClientOriginalName();
              $doc_tipo = $doc_evidencia->getClientOriginalExtension();
              Storage::putFileAs("/public/root/".$filepath, $request->file('imagenAltaPdfevidenciapedim'), $doc_nombre);
              $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%IN-PAD%'");
              $tkn_evidencia = $JwtAuth->encriptarToken($obtenPedimento,$usuario,$empresa,$doc_nombre);
              $insertEvidenceInf = DB::table('sos_documentos')->insert(
                array(
                  "token_documento" => $tkn_evidencia,
                  "fecha_carga" => time(),
                  "modulo" => "ped_aduanal",
                  "folio_modulo" => "IN-PAD".$select_folio_doc[0]->folio,
                  "tipo_documento" => "file",
                  "nombre_documento" => $JwtAuth->encriptar($doc_nombre),
                  "extension_documento" => $doc_tipo,
                  "pedimento_aduanal" => $obtenPedimento,
                  "status_documento" => TRUE,	
                  "fecha_delete_documento" => NULL,
                ) 
              );	
            }
            
            DB::commit();
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este pedimento aduanal ha sido registrado satisfactoriamente con el folio PAD-'.$JwtAuth->generarFolio($folioPedim),
            );
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Este pedimento aduanal no ha sido registrado debido a problemas internos, comuniquese a soporte para más información'
            ], 500);
          }
        }
      } else {
        if (!$valida_fechaPedim) $mensaje_error = 'Error al registrar fecha de importación, intentelo nuevamente o comuniquese a soporte';
        if (!$valida_numeroPedim) $mensaje_error = 'Error al registrar número de pedimento, intentelo nuevamente o comuniquese a soporte';
        if (!$valida_aduana) $mensaje_error = 'Error al registrar informacion sobre aduana del pedimento, intentelo nuevamente o comuniquese a soporte';
        if (!$valida_comentarios) $mensaje_error = 'Error al registrar comentarios del pedimento aduanal, intentelo nuevamente o comuniquese a soporte';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function listaegresosPedimentosVigentes(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
		$pedimentoList = PedimentosModelo::join("main_empresas AS emp", "inventarios_catalogo_pedimento_aduanal.empresa", "=", "emp.id")
		->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
		->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
		->where([
      "inventarios_catalogo_pedimento_aduanal.status_pedimento" => TRUE, 
      "emp.empresa_token" => $empresa, 
      "users.usuario_token" => $usuario
    ])
    ->get();

    if ($pedimentoList->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron pedimentos aduanales registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayPedimentos = array();

      foreach ($pedimentoList as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $arrayForeach = array(
          "token_pedimento" => $value->token_pedimento,
          "folio_pedimento" => "PAD-".$JwtAuth->generarFolio($value->folio_pedimento),
          "fecha_registro" => gmdate('Y-m-d H:i:s', $value->fecha_sistema_pedim),
          "fecha_importacion" => gmdate('Y-m-d H:i:s', $value->fecha_importacion),
          "numero_pedimento" => $JwtAuth->desencriptar($value->numero_pedimento),
          "aduana" => $JwtAuth->desencriptar($value->aduana),
          "detalle" => []
        );
        $arrayPedimentos[] = $arrayForeach;
      }
      
      $dataMensaje = array(
        'datosPedimento' => $arrayPedimentos,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function detalleEgresosPedimento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_pedimento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_pedimento = $request->input('token_pedimento');
      
      $pedimentoList = PedimentosModelo::join("main_empresas AS emp", "inventarios_catalogo_pedimento_aduanal.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "inventarios_catalogo_pedimento_aduanal.status_pedimento" => TRUE, 
        "inventarios_catalogo_pedimento_aduanal.token_pedimento" => $token_pedimento, 
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])->get();
      
      if ($pedimentoList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron pedimentos aduanales registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayPedimento = array();
				foreach ($pedimentoList as $value) {
					//da_te_default_timezone_set($value->zona_horaria);
					$folio_pedimento = "PAD-".$JwtAuth->generarFolio($value->folio_pedimento);
					$evidencia_files = array();

					$selectIdEvid = DB::table("sos_documentos AS docs")
					->join("inventarios_catalogo_pedimento_aduanal AS pad","docs.pedimento_aduanal","=","pad.id")
					->where(["status_documento" => TRUE,"pad.token_pedimento" => $value->token_pedimento])->get();
					if (count($selectIdEvid) > 0) {
						foreach ($selectIdEvid as $vDoc){
							$rowDocs = array(
								"token_documento" => $vDoc->token_documento,
								"ext_doc" => $vDoc->extension_documento,
								"name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
								"url" => "https://downloads.sos-mexico.com.mx/pedimentos/".$folio_pedimento."/".$vDoc->token_documento,
							);
							$evidencia_files[] = $rowDocs;
						}
					}

					$arrayForeach = array(
						"token_pedimento" => $value->token_pedimento,
						"folio_pedimento" => "PAD-".$JwtAuth->generarFolio($value->folio_pedimento),
						"fecha_registro" => gmdate('Y-m-d H:i:s', $value->fecha_sistema_pedim),
						"fecha_importacion" => date('Y-m-d', $value->fecha_importacion),
						"numero_pedimento" => $JwtAuth->desencriptar($value->numero_pedimento),
						"aduana" => $JwtAuth->desencriptar($value->aduana),
						"comentarios" => $JwtAuth->desencriptar($value->comentarios),
						"evidencia_file" => $evidencia_files,
					);
					$arrayPedimento[] = $arrayForeach;
				}

				$dataMensaje = array(
					'datosPedimento' => $arrayPedimento,
					'code' => 200,
					'status' => 'success'
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function updateEgresosPedimento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'token_pedimento' => 'required|string',
			'fechaPedim' => 'required|string',
			'numeroPedim' => 'required|string',
			'aduana' => 'required|string',
			'comentarios' => 'required|string',
			'nameEvidencia' => 'required|string'
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
      $token_pedimento = $request->input('token_pedimento');
      $fechaPedim = $request->input('fechaPedim');
      $numeroPedim = $request->input('numeroPedim');
      $aduana = $request->input('aduana');
      $comentarios = $request->input('comentarios');
      $nameEvidencia = $request->input('nameEvidencia');
      
      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN personal AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
        AND pers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $valida_fechaPedim = isset($fechaPedim) && !empty($fechaPedim) && preg_match($JwtAuth->filtroFecha(), $fechaPedim);
      $valida_numeroPedim = isset($numeroPedim) && !empty($numeroPedim) && preg_match($JwtAuth->filtroRfc(), $numeroPedim);
      $valida_aduana = isset($aduana) && !empty($aduana) && preg_match($JwtAuth->filtroAlfaNumerico(), $aduana);
      $valida_comentarios = isset($comentarios) && !empty($comentarios) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios);
      $valida_evidencia = isset($nameEvidencia) && !empty($nameEvidencia) && preg_match($JwtAuth->filtroAlfaNumerico(), $nameEvidencia);

      if ($valida_fechaPedim && $valida_numeroPedim && $valida_aduana && $valida_comentarios && $valida_evidencia) {
        $selectPedim = DB::table('inventarios_catalogo_pedimento_aduanal as pedim')
        ->join('main_empresas as emp', 'pedim.empresa', '=', 'emp.id')
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'pedim.token_pedimento' => $token_pedimento,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select(
          'pedim.id', 
          'pedim.fecha_sistema_pedim', 
          'pedim.folio_pedimento', 
          'pedim.numero_pedimento', 
          'pedim.aduana', 
          'pedim.fecha_importacion', 
          'pedim.evidencia', 
          'pedim.comentarios'
        )
        ->first();
        if (!$selectPedim) {
          return response()->json(['status' => 'error', 'message' => 'No se encontró el registro'], 404);
        }
        
        // 2. Preparar el array de cambios (Solo lo que realmente cambió)
        $updateData = [];
        
        $nuevaFecha = $JwtAuth->convierteFechaEpoc($fechaPedim);
        if ($selectPedim->fecha_importacion != $nuevaFecha) $updateData['fecha_importacion'] = $nuevaFecha;
        
        $nuevoNum = $JwtAuth->encriptar($numeroPedim);
        if ($selectPedim->numero_pedimento != $nuevoNum) $updateData['numero_pedimento'] = $nuevoNum;
        
        $nuevaAduana = $JwtAuth->encriptar($aduana);
        if ($selectPedim->aduana != $nuevaAduana) $updateData['aduana'] = $nuevaAduana;
        
        $nuevosComents = $JwtAuth->encriptar($comentarios);
        if ($selectPedim->comentarios != $nuevosComents) $updateData['comentarios'] = $nuevosComents;
        
        // Lógica de archivo/evidencia
        if ($JwtAuth->desencriptar($selectPedim->evidencia) != $nameEvidencia && $request->hasFile('imagenAltaPdfevidenciapedim')) {
          $evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($selectPedim->folio_pedimento).'-'.$selectPedim->fecha_sistema_pedim.'-'.$nameEvidencia);
          $filepath = $selectEmp[0]->root_tkn."/0002-cpp/catalogos/pedimentos/".$JwtAuth->generar($selectPedim->folio_pedimento).'-'.$selectPedim->fecha_sistema_pedim."/";

          if (!file_exists(storage_path("/root/".$filepath))) {
            Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
          }

          Storage::putFileAs("/public/root/".$filepath, $request->file('imagenAltaPdfevidenciapedim'), $JwtAuth->desencriptar($evidenciaNombre));
          $updateData['evidencia'] = $evidenciaNombre; 
        }
        
        // 3. Ejecutar una SOLA actualización si hay cambios
        if (empty($updateData)) {
          return response()->json(['status' => 'error', 'message' => 'No es posible actualizar este pedimento aduanal ya que la información es la misma'], 200);
        }
        
        $actPedim = DB::table("inventarios_catalogo_pedimento_aduanal")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("personal AS pers", "empuser.personal", "=", "pers.id")
        ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
        ->where('id',$selectPedim->id)
        ->update($updateData);
                
        if ($actPedim) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'el pedimento aduanal con el folio '.$JwtAuth->generar($selectPedim->folio_pedimento).' ha sido actualizado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'el pedimento aduanal con el folio '.$JwtAuth->generar($selectPedim->folio_pedimento).' no fue actualizado debido a errores internos, para mas información comuniquese a soporte'
          );
        }
      } else {
        $mansaje_error = '';
        if (!$valida_fechaPedim) { $mansaje_error = 'Ingrese fecha de importación'; }
        if (!$valida_numeroPedim) { $mansaje_error = 'Ingrese número de pedimento'; }
        if (!$valida_aduana) { $mansaje_error = 'Ingrese aduana para pedimento aduanal'; }
        if (!$valida_comentarios) { $mansaje_error = 'Ingrese comentarios del pedimento aduanal'; }
        if (!$valida_evidencia) { $mansaje_error = 'Debe cargar o escanear evidencia del pedimento aduanal'; }        
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mansaje_error,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function listaegresosPedimentosDelete(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_pedimento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_pedimento = $request->input('token_pedimento');
      
			$pedvincAlm = PedimentosModelo::join("detalle_almacen", "pedimento_aduanal.id", "=", "detalle_almacen.importado")
			->join("main_empresas AS emp", "pedimento_aduanal.empresa", "=", "emp.id")
			->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
			->join("personal", "empuser.personal", "=", "personal.id")
			->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
			->where([
				'pedimento_aduanal.token_pedimento' => $token_pedimento,
				'empresas.empresa_token' => $empresa,
				'teci_usuarios.user_token' => $usuario,
			])->get();

      if (!$pedvincAlm->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El pedimento aduanal que ha seleccionado no fue eliminado ya que esta vinculado a compras realizadas o productos en almacen'
        );
      } else {
        $pedimentoUpdate = PedimentosModelo::join("main_empresas AS emp", "pedimento_aduanal.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("personal", "empuser.personal", "=", "personal.id")
        ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
        ->where([
          'pedimento_aduanal.token_pedimento' => $token_pedimento,
          'empresas.empresa_token' => $empresa,
          'teci_usuarios.user_token' => $usuario,
        ])->limit(1)->update(
          array(
            'pedimento_aduanal.status_pedimento' => FALSE,
            'pedimento_aduanal.fecha_delete_pedimento' => time(),
          )
        );

        if ($pedimentoUpdate) {
          $dataMensaje = array(
            'message' => 'El pedimento aduanal que ha seleccionado ha sido eliminado satisfactoriamente',
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'El pedimento aduanal que ha seleccionado no fue eliminado debido a errores internos, para mayor información comuniquese a soporte',
            'code' => 200,
            'status' => 'error'
          );
        }
        
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function listaegresosPedimentosDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
		$pedimentoList = PedimentosModelo::join("main_empresas AS emp", "inventarios_catalogo_pedimento_aduanal.empresa", "=", "emp.id")
		->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
		->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
		->where([
      "inventarios_catalogo_pedimento_aduanal.status_pedimento" => FALSE, 
      "emp.empresa_token" => $empresa, 
      "users.usuario_token" => $usuario
    ])
    ->get();

    if ($pedimentoList->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron pedimentos aduanales registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayPedimentos = array();
      foreach ($pedimentoList as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $arrayForeach = array(
          "token_pedimento" => $value->token_pedimento,
          "folio_pedimento" => "PAD-".$JwtAuth->generarFolio($value->folio_pedimento),
          "fecha_registro" => gmdate('Y-m-d H:i:s', $value->fecha_sistema_pedim),
          "fecha_importacion" => gmdate('Y-m-d H:i:s', $value->fecha_importacion),
          "numero_pedimento" => $JwtAuth->desencriptar($value->numero_pedimento),
          "aduana" => $JwtAuth->desencriptar($value->aduana),
          "fecha_delete_pedimento" => gmdate('Y-m-d H:i:s', $value->fecha_delete_pedimento),
        );
        $arrayPedimentos[] = $arrayForeach;
      }
      $dataMensaje = array(
        'datosPedimento' => $arrayPedimentos,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function pedimentoRestart(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_pedimento' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_pedimento = $request->input('token_pedimento');
      
      $pedimentoUpdate = PedimentosModelo::join("main_empresas AS emp", "pedimento_aduanal.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("personal", "empuser.personal", "=", "personal.id")
      ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
      ->where([
        'pedimento_aduanal.token_pedimento' => $token_pedimento,
        'empresas.empresa_token' => $empresa,
        'teci_usuarios.user_token' => $usuario,
      ])->limit(1)->update(
        array(
          'pedimento_aduanal.status_pedimento' => TRUE,
          'pedimento_aduanal.fecha_delete_pedimento' => '',
        )
      );

      if ($pedimentoUpdate) {
        $dataMensaje = array(
          'message' => 'El pedimento aduanal que ha seleccionado ha sido restaurado satisfactoriamente',
          'code' => 200,
          'status' => 'success'
        );
      } else {
        $dataMensaje = array(
          'message' => 'El pedimento aduanal que ha seleccionado no fue restaurado debido a errores internos, para mayor información comuniquese a soporte',
          'code' => 200,
          'status' => 'error'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function pedimentoDeletePerm(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_pedimento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_pedimento = $request->input('token_pedimento');
      
      $pedvincAlm = PedimentosModelo::join("detalle_almacen", "pedimento_aduanal.id", "=", "detalle_almacen.importado")
      ->join("main_empresas AS emp", "pedimento_aduanal.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("personal", "empuser.personal", "=", "personal.id")
      ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
      ->where([
        'pedimento_aduanal.token_pedimento' => $token_pedimento,
        'empresas.empresa_token' => $empresa,
        'teci_usuarios.user_token' => $usuario,
      ])->get();

      $pedvinComp = PedimentosModelo::join("detalle_compra", "pedimento_aduanal.id", "=", "detalle_compra.pedimento_aduanal")
      ->join("main_empresas AS emp", "pedimento_aduanal.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("personal", "empuser.personal", "=", "personal.id")
      ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
      ->where([
        'pedimento_aduanal.token_pedimento' => $token_pedimento,
        'empresas.empresa_token' => $empresa,
        'teci_usuarios.user_token' => $usuario,
      ])->get();
      
      if (!$pedvincAlm->isEmpty() && !$pedvinComp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El pedimento aduanal que ha seleccionado no fue eliminado ya que esta vinculado a compras realizadas o productos en almacen'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();        
        $pedimentoUpdate = PedimentosModelo::join("main_empresas AS emp", "pedimento_aduanal.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("personal", "empuser.personal", "=", "personal.id")
        ->join("teci_usuarios", "personal.usuario", "=", "teci_usuarios.id")
        ->where([
          'pedimento_aduanal.token_pedimento' => $token_pedimento,
          'empresas.empresa_token' => $empresa,
          'teci_usuarios.user_token' => $usuario,
        ])->limit(1)->delete();

        if ($pedimentoUpdate) {
          $dataMensaje = array(
            'message' => 'El pedimento aduanal que ha seleccionado ha sido eliminado permanentemente',
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'El pedimento aduanal que ha seleccionado no fue eliminado debido a errores internos, para mayor información comuniquese a soporte',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}
}
