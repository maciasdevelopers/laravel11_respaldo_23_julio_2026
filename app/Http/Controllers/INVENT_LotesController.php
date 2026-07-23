<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\LotesModelo;
use QRCode;

class INVENT_LotesController extends Controller{
  public function registraLote(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'fechaLote' => 'required|string',
      'numeroLote' => 'required|string',
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
      $fechaLote = $request->input('fechaLote');
      $numeroLote = $request->input('numeroLote');
      $comentarios = $request->input('comentarios');
      $nameEvidencia = $request->input('nameEvidencia');
      $doc_evidencia = $request->file('imagenAltaPdfevidencialote');
      
      $validar_lote_fecha = isset($fechaLote) && !empty($fechaLote) && preg_match($JwtAuth->filtroFecha(),$fechaLote);
      $validar_lote_numero = isset($numeroLote) && !empty($numeroLote) && preg_match($JwtAuth->filtroRfc(),$numeroLote);
      $validar_lote_comentarios = isset($comentarios) && !empty($comentarios) && preg_match($JwtAuth->filtroAlfaNumerico(),$comentarios);
      
      if ($validar_lote_fecha && $validar_lote_numero && $validar_lote_comentarios) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp  
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$empresa,$usuario]);

        foreach ($queryEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          $fecha_sistema_lote = time();

          $maxFolioLote = DB::table('inventarios_catalogo_lotes')
          ->where('empresa', $vEmp->id)
          ->lockForUpdate()->max('folio_lote');
          $folioLote = $maxFolioLote ? $maxFolioLote + 1 : 1;

          $tokenLote = $JwtAuth->encriptarToken(time().$fechaLote.$numeroLote.$comentarios);
          $newLote = new LotesModelo();
          $newLote->token_lote = $tokenLote;
          $newLote->folio_lote = $folioLote;
          $newLote->numero_lote = $JwtAuth->encriptar($numeroLote);
          $newLote->fecha_sistema_lote = $fecha_sistema_lote;
          $newLote->fecha_lote = $JwtAuth->convierteFechaEpoc($fechaLote);
          $newLote->comentarios = $JwtAuth->encriptar($comentarios);
          $newLote->empresa = $vEmp->id;
          $newLote->status_lote = TRUE;
          $newLote->fecha_delete_lote = '';
          $savednewLote = $newLote->save();
          if ($savednewLote) {
            if (file_exists($doc_evidencia)) {
              $obtenLote = $newLote->id;
              $filepath = $vEmp->root_tkn."/0002-cpp/catalogos/lotes/".$JwtAuth->generarFolio($folioLote).'-'.$fecha_sistema_lote."/";
              !file_exists(storage_path("/root/".$filepath)) ? Storage::disk('root')->makeDirectory($filepath,0777, true, true) : NULL;
              $doc_nombre = $JwtAuth->generarFolio($folioLote).'-'.$fecha_sistema_lote.'-'.$doc_evidencia->getClientOriginalName();
              $doc_tipo = $doc_evidencia->getClientOriginalExtension();
              Storage::putFileAs("/public/root/".$filepath,$request->file('imagenAltaPdfevidencialote'),$doc_nombre);
              $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%IN-LOT%'");
              $tkn_evidencia = $JwtAuth->encriptarToken($obtenLote,$usuario,$empresa,$doc_nombre);
              $insertEvidenceInf = DB::table('sos_documentos')->insert(
                array(
                  "token_documento" => $tkn_evidencia,
                  "fecha_carga" => time(),
                  "modulo" => "ped_aduanal",
                  "folio_modulo" => "IN-LOT".$select_folio_doc[0]->folio,
                  "tipo_documento" => "file",
                  "nombre_documento" => $JwtAuth->encriptar($doc_nombre),
                  "extension_documento" => $doc_tipo,
                  "lote" => $obtenLote,
                  "status_documento" => TRUE,	
                  "fecha_delete_documento" => NULL,
                ) 
              );
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este lote ha sido registrado satisfactoriamente con el folio LTE-'.$JwtAuth->generarFolio($folioLote),
            );
          } else {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Este lote no ha sido registrado debido a problemas internos, comuniquese a soporte para más información',
            );
          }

        }
      } else {
        $mensaje_error = '';
        if (!$validar_lote_fecha) $mensaje_error = "Error al registrar fecha de lote, intentelo nuevamente o comuniquese a soporte";
        if (!$validar_lote_numero) $mensaje_error = "Error al registrar número de lote, intentelo nuevamente o comuniquese a soporte";
        if (!$validar_lote_comentarios) $mensaje_error = "Error al registrar comentarios de lote, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaLotesVigentes(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $loteList = LotesModelo::join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    ->where([
      "inventarios_catalogo_lotes.status_lote" => TRUE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])
    ->get();
    
    if ($loteList->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron lotes registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayLotes = array();      
      foreach ($loteList as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $arrayForeach = array(
          "token_lote" => $value->token_lote,
          "folio_lote" => "LTE-".$JwtAuth->generarFolio($value->folio_lote),
          "numero_lote" => $JwtAuth->desencriptar($value->numero_lote),	
          "fecha_lote" => date('d-m-Y H:i:s',$value->fecha_lote),
          "contenido" => [],
        );
        $arrayLotes[] = $arrayForeach; 
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'datosLote' => $arrayLotes
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleEgresosLote(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_lote' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_lote = $request->input('token_lote');
      
      $loteList = LotesModelo::join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        'inventarios_catalogo_lotes.status_lote' => TRUE, 
        'inventarios_catalogo_lotes.token_lote' => $token_lote,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();

      if ($loteList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron lotes registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayLotes = array();
        foreach ($loteList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $folio_lote = "LTE-".$JwtAuth->generarFolio($value->folio_lote);
          $evidencia_files = array();
          
          $selectIdEvid = DB::table("sos_documentos AS docs")
					->join("inventarios_catalogo_lotes AS lot","docs.lote","=","lot.id")
					->where(["status_documento" => TRUE,"lot.token_lote" => $value->token_lote])->get();
					if (count($selectIdEvid) > 0) {
						foreach ($selectIdEvid as $vDoc){
							$rowDocs = array(
								"token_documento" => $vDoc->token_documento,
								"ext_doc" => $vDoc->extension_documento,
								"name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
								"url" => "https://downloads.sos-mexico.com.mx/lotes/".$folio_lote."/".$vDoc->token_documento,
							);
							$evidencia_files[] = $rowDocs;
						}
					}
          
          $arrayForeach = array(
            "token_lote" => $value->token_lote,
            "folio_lote" => $folio_lote,
            "fecha_lote" => date('Y-m-d',$value->fecha_lote),
            "numero_lote" => $JwtAuth->desencriptar($value->numero_lote),
            "comentarios" => $JwtAuth->desencriptar($value->comentarios),	
            "evidencia_file" => $evidencia_files,
          );
          $arrayLotes[] = $arrayForeach; 
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosLote' => $arrayLotes
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateEgresosLote(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_lote' => 'required|string',
      'fechaLote' => 'required|string',
      'numeroLote' => 'required|string',
      'comentarios' => 'required|string',
      'nameEvidencia' => 'required|string',
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
      $token_lote = $request->input('token_lote');
      $fechaLote = $request->input('fechaLote');
      $numeroLote = $request->input('numeroLote');
      $comentarios = $request->input('comentarios');
      $nameEvidencia = $request->input('nameEvidencia');
      
      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp  
        main_empresa_usuario AS empuser JOIN personal AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
        AND pers.usuario = users.id AND users.usuario_token= ?",[$empresa,$usuario]);
      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $OKFechaLote = isset($fechaLote) && !empty($fechaLote) && preg_match($JwtAuth->filtroFecha(),$fechaLote);
      $OKNumeroLote = isset($numeroLote) && !empty($numeroLote) && preg_match($JwtAuth->filtroLote(),$numeroLote);
      $OKComentarios = isset($comentarios) && !empty($comentarios) && preg_match($JwtAuth->filtroAlfaNumerico(),$comentarios);
      $OKNameEvidencia = isset($nameEvidencia) && !empty($nameEvidencia) && preg_match($JwtAuth->filtroAlfaNumerico(),$nameEvidencia);

      if ($OKFechaLote && $OKNumeroLote && $OKComentarios && $OKNameEvidencia) {

        $selectLote = DB::select("SELECT lote.fecha_sistema_lote,lote.folio_lote,lote.numero_lote,lote.fecha_lote,lote.evidencias,lote.comentarios FROM inventarios_catalogo_lotes AS lote
          JOIN main_empresas AS emp main_empresa_usuario AS empuser JOIN personal AS pers JOIN teci_usuarios_catalogo AS users WHERE lote.token_lote = ? 
          AND lote.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
          AND pers.usuario = users.id AND users.usuario_token= ?",[$token_lote,$empresa,$usuario]); 

        if ($selectLote[0]->fecha_lote == $JwtAuth->convierteFechaEpoc($fechaLote) &&
          $selectLote[0]->numero_lote == $JwtAuth->encriptar($numeroLote) &&
          $selectLote[0]->comentarios == $JwtAuth->encriptar($comentarios) &&
          $JwtAuth->desencriptar($selectLote[0]->evidencias) == $JwtAuth->encriptar($nameEvidencia)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'No es posible actualizar este lote ya que la información es la misma',
          );
        } else {
          $validatefechaLote = false;
          $validatenumlote = false;
          $validatecoments = false;
          $validateevidencia = false;
          if ($selectLote[0]->fecha_lote != $JwtAuth->convierteFechaEpoc($fechaLote)) {
            $actLotefecha = LotesModelo::join("empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
            ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
            ->join("personal AS pers","empuser.personal","=","pers.id")
            ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
            ->where([
              'inventarios_catalogo_lotes.token_lote' => $token_lote,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'inventarios_catalogo_lotes.fecha_lote' => $JwtAuth->convierteFechaEpoc($fechaLote),
              )
            );
            if ($actLotefecha) {
              $validatefechaLote = true;
            } else {
              $validatefechaLote = false;
            }
          } else {
            $validatefechaLote = true;
          }

          if ($selectLote[0]->numero_lote != $JwtAuth->encriptar($numeroLote)) {
            $actLotenum = LotesModelo::join("empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
            ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
            ->join("personal AS pers","empuser.personal","=","pers.id")
            ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
            ->where([
              'inventarios_catalogo_lotes.token_lote' => $token_lote,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'inventarios_catalogo_lotes.numero_lote' => $JwtAuth->encriptar($numeroLote),
              )
            );
            if ($actLotenum) {
              $validatenumlote = true;
            } else {
              $validatenumlote = false;
            }
          } else {
            $validatenumlote = true;
          }

          if ($selectLote[0]->comentarios != $JwtAuth->encriptar($comentarios)) {
            $actLotecoments = LotesModelo::join("empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
            ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
            ->join("personal AS pers","empuser.personal","=","pers.id")
            ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
            ->where([
              'inventarios_catalogo_lotes.token_lote' => $token_lote,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->update(
              array(
                'inventarios_catalogo_lotes.comentarios' => $JwtAuth->encriptar($comentarios),
              )
            );
            if ($actLotecoments) {
              $validatecoments = true;
            } else {
              $validatecoments = false;
            }
          } else {
            $validatecoments = true;
          }

          if ($JwtAuth->desencriptar($selectLote[0]->evidencias) != $nameEvidencia) {
            if (file_exists($request->file('imagenAltaPdfevidencialote'))) {
              $evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($selectLote[0]->folio_lote).'-'.$selectLote[0]->fecha_sistema_lote.'-'.$nameEvidencia);
              $filepath = $selectEmp[0]->root_tkn."/0002-cpp/catalogos/lotes/".$JwtAuth->generar($selectLote[0]->folio_lote).'-'.
              $selectLote[0]->fecha_sistema_lote."/";
      
              if (!file_exists(storage_path("/root/".$filepath))){
                Storage::disk('root')->makeDirectory($filepath,0777, true, true);
              }
              
              Storage::putFileAs("/public/root/".$filepath,$request->file('imagenAltaPdfevidencialote'),$JwtAuth->desencriptar($evidenciaNombre));
      
              $actLotedocs = LotesModelo::join("empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
              ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
              ->join("personal AS pers","empuser.personal","=","pers.id")
              ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
              ->where([
                'inventarios_catalogo_lotes.token_lote' => $token_lote,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])
              ->limit(1)->update(
                array(
                  'inventarios_catalogo_lotes.evidencias' =>  $evidenciaNombre,
                )
              );
              
              if ($actLotedocs) {
                $validateevidencia = true;
              } else {
                $validateevidencia = false;
              }
            } else {
              //$evidenciaNombre = $JwtAuth->encriptar($JwtAuth->generar($selectLote[0]->folio_lote).'-'.$selectLote[0]->fecha_sistema_lote.'-base64.txt');
              $evidenciaNombre = '';
            }
          } else {
            $validateevidencia = true;
          }
                       
          if ($validatefechaLote == true && $validatenumlote == true && $validatecoments == true && $validateevidencia == true) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'el lote con el folio '.$JwtAuth->generar($selectLote[0]->folio_lote).' ha sido actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'el lote con el folio '.$JwtAuth->generar($selectLote[0]->folio_lote).' no fue actualizado debido a errores internos, para mas información comuniquese a soporte'
            );
          }
        }
      } else {
        $mensaje_error = '';
        if (!$OKFechaLote) { $mensaje_error = 'Ingrese fecha de lote'; }
        if (!$OKNumeroLote) { $mensaje_error = 'Ingrese número de lote'; }
        if (!$OKComentarios) { $mensaje_error = 'Ingrese comentarios del lote'; }
        if (!$OKNameEvidencia) { $mensaje_error = 'Debe cargar o escanear evidencia de lote'; }
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaLotesDelete(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_lote' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_lote = $request->input('token_lote');
      
      $lotevincAlm = LotesModelo::join("in_egr_establecimientos_almacen AS almDet","inventarios_catalogo_lotes.id","=","almDet.num_lote")
      ->join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        'inventarios_catalogo_lotes.token_lote' => $token_lote,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();
        
      $lotevinComp = LotesModelo::join("eegr_compras_detalle AS buyDet","inventarios_catalogo_lotes.id","=","buyDet.lote")
      ->join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        'inventarios_catalogo_lotes.token_lote' => $token_lote,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      if (!$lotevincAlm->isEmpty() || !$lotevinComp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El lote que ha seleccionado no fue eliminado ya que esta vinculado a compras realizadas o productos en almacen'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $loteUpdate = LotesModelo::join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
        ->where([
          'inventarios_catalogo_lotes.token_lote' => $token_lote,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
        ])->limit(1)->update(
          array(
            'inventarios_catalogo_lotes.status_lote' => FALSE,
            'inventarios_catalogo_lotes.fecha_delete_lote' => time(),
          )
        );

        if ($loteUpdate) {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado ha sido eliminado satisfactoriamente',
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado no fue eliminado debido a errores internos, para mayor información comuniquese a soporte',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaLotesDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $loteList = LotesModelo::join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    ->where([
      'inventarios_catalogo_lotes.status_lote' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->get();
    
    if ($loteList->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron lotes registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayLotes = array();
      
      foreach ($loteList as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $arrayForeach = array(
          "token_lote" => $value->token_lote,	
          "folio_lote" => $JwtAuth->generar($value->folio_lote),
          "numero_lote" => $JwtAuth->desencriptar($value->numero_lote),	
          "fecha_lote" => date('d-m-Y H:i:s',$value->fecha_lote),
          "fecha_delete_lote" => date('d-m-Y H:i:s',$value->fecha_delete_lote),
        );
        $arrayLotes[] = $arrayForeach; 
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'datosLote' => $arrayLotes,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function loteRestart(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_lote' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_lote = $request->input('token_lote');
      
      $loteList = LotesModelo::join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        'inventarios_catalogo_lotes.status_lote' => FALSE,
        'inventarios_catalogo_lotes.token_lote' => $token_lote,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();

      if ($loteList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron lotes registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $loteUpdate = LotesModelo::where('token_lote',$token_lote)
        ->limit(1)->update(
          array(
            'status_lote' => TRUE,
            'fecha_delete_lote' => NULL,
          )
        );

        if ($loteUpdate) {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado ha sido restaurado satisfactoriamente',
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado no fue restaurado debido a errores internos, para mayor información comuniquese a soporte',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function LoteDeletePerm(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_lote' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_lote = $request->input('token_lote');
      
      $lotevincAlm = LotesModelo::join("in_egr_establecimientos_almacen AS almDet","inventarios_catalogo_lotes.id","=","almDet.num_lote")
      ->join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        'inventarios_catalogo_lotes.token_lote' => $token_lote,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();
      
      $lotevinComp = LotesModelo::join("eegr_compras_detalle AS buyDet","inventarios_catalogo_lotes.id","=","buyDet.lote")
      ->join("main_empresas AS emp","inventarios_catalogo_lotes.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
      ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
      ->where([
        'inventarios_catalogo_lotes.token_lote' => $token_lote,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      if (!$lotevincAlm->isEmpty() || !$lotevinComp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'El lote que ha seleccionado no fue eliminado ya que esta vinculado a compras realizadas o productos en almacen'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $loteUpdate = LotesModelo::where('token_lote',$token_lote)->limit(1)->delete();
        
        if ($loteUpdate) {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado ha sido eliminado satisfactoriamente',
            'code' => 200,
            'status' => 'success'
          );
        } else {
          $dataMensaje = array(
            'message' => 'El lote que ha seleccionado no fue eliminado debido a errores internos, para mayor información comuniquese a soporte',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}