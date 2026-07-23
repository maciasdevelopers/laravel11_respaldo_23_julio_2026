<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\PublicacionesModelo;
use Illuminate\Support\Str;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/


class TICS_PublicacionesController extends Controller{
  //landing_page
  public function listaPublicacionesHome(){
    $JwtAuth = new \JwtAuth();
    $arrayPublicaciones = array();
    $queryPublicaciones = PublicacionesModelo::join("teci_page_publicaciones_resena AS pubRes", "teci_page_publicaciones.id", "=", "pubRes.publicacion")
    ->orderBy("teci_page_publicaciones.id", "ASC")
    ->limit(5)
    ->get();

    if (count($queryPublicaciones) != 0) {
      foreach ($queryPublicaciones as $vPub) {
        $internoArray = array(
          "token_publicacion" => $vPub->token_publicacion,
          "folio_publicacion" => $JwtAuth->generar($vPub->folio_publicacion),
          "fecha_publicacion" => date('d-m-Y H:i:s', $vPub->fecha_publicacion),
          "encabezado" => $JwtAuth->desencriptar($vPub->encabezado),
          "resena" => $JwtAuth->desencriptar($vPub->resena_contenido)
        );
        $arrayPublicaciones[] = $internoArray;
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'arrayPublicaciones' => $arrayPublicaciones
      );
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'no hay publicaciones recientes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function verPublicacionCompleta(Request $request){
    $validate = \Validator::make($request->all(),[
      'token_publicacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $publicacion = array();
      $token_publicacion = $request->input('token_publicacion');
      
      $listaNotif = PublicacionesModelo::join("teci_page_publicaciones_resena AS pubRes", "teci_page_publicaciones.id", "=", "pubRes.publicacion")
      ->where('teci_page_publicaciones.token_publicacion',$token_publicacion)
      ->get();

      if (count($listaNotif) > 0) {
        foreach ($listaNotif as $vPub) {
          $contenidoData = array();
          $contentQuery = PublicacionesModelo::join("teci_page_publicaciones_contenido AS pub_cont", "teci_page_publicaciones.id", "=", "pub_cont.publicacion")
          ->where('teci_page_publicaciones.token_publicacion',$vPub->token_publicacion)
          ->get();

          foreach ($contentQuery as $vDetPub) {
            $rowDet = array(
              "subtitulo" => $JwtAuth->desencriptar($vDetPub->subtitulo),
              "parrafo" => $JwtAuth->desencriptar($vDetPub->parrafo)
            );
            $contenidoData[] = $rowDet;
          }

          $bibliografiaData = array();
          $biblioQuery = PublicacionesModelo::join("teci_page_publicaciones_bibliografia AS fuent", "teci_page_publicaciones.id", "=", "fuent.publicacion")
          ->where('teci_page_publicaciones.token_publicacion',$vPub->token_publicacion)
          ->get();

          foreach ($biblioQuery as $vFuentPub) {
            $rowFuent = array(
              "fuente" => $JwtAuth->desencriptar($vFuentPub->fuente),
              "detalle_fuente" => $JwtAuth->desencriptar($vFuentPub->detalle_fuente)
            );
            $bibliografiaData[] = $rowFuent;
          }

          $row = array(
            "folio_publicacion" => $JwtAuth->generar($vPub->folio_publicacion),
            "fecha_publicacion" => date('d-m-Y H:i:s',$vPub->fecha_publicacion),
            "encabezado" => $JwtAuth->desencriptar($vPub->encabezado),
            "resena" => $JwtAuth->desencriptar($vPub->resena_contenido),
            "contenido" => $contenidoData,
            "bibliografia" => $bibliografiaData,
          );
          $publicacion[] = $row;
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'publicacion' => $publicacion
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'no hay publicaciones recientes'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //sistema_interno
  private function publicacion_registra_desglose($JwtAuth,$idNewPub,$desglose){
    $parrafosParaInsertar = [];
    foreach ($desglose as $vDesg) {
      //$vDesg['subtitulo']
      $parrafosParaInsertar[] = [
        "token_publicacion_content" => Str::uuid()->toString(),
        "publicacion" => $idNewPub,
        "subtitulo" => $JwtAuth->encriptar($vDesg['subtitulo']),
        "parrafo" => $JwtAuth->encriptar($vDesg['parrafo']),
        "status_content_pub" => TRUE,
      ];
    }
    if (!empty($parrafosParaInsertar)) {
      $insertPubContenido = DB::table('teci_page_publicaciones_contenido')->insert($parrafosParaInsertar);

      if (!$insertPubContenido) {
        throw new \Exception("Error crítico al registrar el contenido de la publicación.");
      }
    }
  }

  private function publicacion_registra_fuentes($JwtAuth,$idNewPub,$fuentes_de_consulta){
    $fuentesParaInsertar = [];
    foreach ($fuentes_de_consulta as $vFue) {
      $fuentesParaInsertar[] = [
        "token_bibliografia" => Str::uuid()->toString(),
        "publicacion" => $idNewPub,
        "fuente" => $JwtAuth->encriptar($vFue['fuente']),
        "detalle_fuente" => $JwtAuth->encriptar($vFue['detalle_fuente']),
      ];
    }
    if (!empty($fuentesParaInsertar)) {
      $insertPubBibliografia = DB::table('teci_page_publicaciones_bibliografia')->insert($fuentesParaInsertar);

      if (!$insertPubBibliografia) {
        throw new \Exception("Error crítico al registrar el contenido bibliográfico de la publicación.");
      }
    }
  }

  public function registra_publicacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'titulo' => 'required|string',
      'resena' => 'required|string',
      'desglose' => 'required|array',
      'fuentes_de_consulta' => 'required|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');

      $titulo = $request->input('titulo');
      $resena = $request->input('resena');
      $desglose = $request->input('desglose');
      $fuentes_de_consulta = $request->input('fuentes_de_consulta');

      $OKTitulo = isset($titulo) && !empty($titulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $titulo);
      $OKResena = isset($resena) && !empty($resena) && preg_match($JwtAuth->filtroAlfaNumerico(), $resena);
      $OKDesglose = isset($desglose) && !empty($desglose) && count($desglose) > 0;
      $OKFuentesDEConsulta = isset($fuentes_de_consulta) && !empty($fuentes_de_consulta) && count($fuentes_de_consulta) > 0;
      
      if ($OKTitulo && $OKResena && $OKDesglose && $OKFuentesDEConsulta) {
        DB::beginTransaction();
        try {
          $maxFolioPublicacion = DB::table('teci_page_publicaciones')
          ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
          ->max('folio_publicacion');
          $folio_publicacion = $maxFolioPublicacion ? $maxFolioPublicacion + 1 : 1;
          $newPub = new PublicacionesModelo();
          $newPub->token_publicacion = Str::uuid();
          $newPub->folio_publicacion = $folio_publicacion;
          $newPub->fecha_publicacion = time();
          $newPub->encabezado = $JwtAuth->encriptar($titulo);
          $newPub->status = TRUE;
          $saveNewPub = $newPub->save();
          
          if (!$saveNewPub) {
            throw new \Exception("Error al guardar la cabecera de la publicación.");
          }

          $idNewPub = $newPub->id;
          $newPubResena = DB::table('teci_page_publicaciones_resena')//cfdi__estructura
          ->insert(array(
            "token_resena" => Str::uuid()->toString(),
            "resena_contenido" => $JwtAuth->encriptar($resena),
            "publicacion" => $idNewPub,
          ));
          
          if (!$newPubResena) {
            throw new \Exception("Error al guardar la reseña de la publicación.");
          }

          $this->publicacion_registra_desglose($JwtAuth,$idNewPub,$desglose);
          $this->publicacion_registra_fuentes($JwtAuth,$idNewPub,$fuentes_de_consulta);
          //if ($saveNewPub) {}
          DB::commit(); // Si llegamos aquí, todo se guarda permanentemente
          return response()->json(['status' => 'success','message' => 'Publicación registrada exitosamente'], 200);
        } catch (\Exception $e) {
          DB::rollBack();
          // 1. Guardar el error real en storage/logs/laravel.log
          \Log::error("Error al recibir activo: " . $e->getMessage());
          // 2. Responder al usuario con algo genérico
          return response()->json(['status' => 'error','message' => 'Registro de publicación incompleto, revise su información o comuniquese a soporte.' . $e->getMessage()], 500);
        }  
      } else {
        $mensaje_error = '';
        if (!$OKTitulo) $mensaje_error = 'Error al registrar título de publicación, intentelo nuevamente o comuniquese a soporte'; 
        if (!$OKResena) $mensaje_error = 'Error al registrar reseña de publicación, intentelo nuevamente o comuniquese a soporte'; 
        if (!$OKDesglose) $mensaje_error = 'Error al registrar desglose de publicación, intentelo nuevamente o comuniquese a soporte'; 
        if (!$OKFuentesDEConsulta) $mensaje_error = 'Error al registrar fuentes de consulta de publicación, intentelo nuevamente o comuniquese a soporte'; 
        $dataMensaje = array('code' => 200,'status' => 'error','message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoPublicaciones(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    $JwtAuth = new \JwtAuth();
    $arrayPublicaciones = array();
    $queryPublicaciones = PublicacionesModelo::join("teci_page_publicaciones_resena AS pubRes", "teci_page_publicaciones.id", "=", "pubRes.publicacion")
    ->where("teci_page_publicaciones.status", TRUE)
    ->orderBy("teci_page_publicaciones.id", "ASC")
    ->get();

    if (count($queryPublicaciones) != 0) {
      foreach ($queryPublicaciones as $vPub) {
        $row = array(
          "token_publicacion" => $vPub->token_publicacion,
          "folio_publicacion" => $JwtAuth->generar($vPub->folio_publicacion),
          "fecha_publicacion" => date('d-m-Y H:i:s', $vPub->fecha_publicacion),
          "encabezado" => $JwtAuth->desencriptar($vPub->encabezado),
          "resena" => $JwtAuth->desencriptar($vPub->resena_contenido)
        );
        $arrayPublicaciones[] = $row;
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'arrayPublicaciones' => $arrayPublicaciones
      );
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'no hay publicaciones recientes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function publicacionDetalle(Request $request){
    $validate = \Validator::make($request->all(),[
      'token_publicacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $publicacion = array();
      $token_publicacion = $request->input('token_publicacion');
      
      $queryPublic = PublicacionesModelo::join("teci_page_publicaciones_resena AS pubRes", "teci_page_publicaciones.id", "=", "pubRes.publicacion")
      ->where('teci_page_publicaciones.token_publicacion',$token_publicacion)
      ->get();

      if (count($queryPublic) > 0) {
        foreach ($queryPublic as $vPub) {
          $contenidoData = array();
          $contentQuery = PublicacionesModelo::join("teci_page_publicaciones_contenido AS pub_cont", "teci_page_publicaciones.id", "=", "pub_cont.publicacion")
          ->where('teci_page_publicaciones.token_publicacion',$vPub->token_publicacion)
          ->get();

          foreach ($contentQuery as $vDetPub) {
            $rowDet = array(
              "token_publicacion_content" => $vDetPub->token_publicacion_content,
              "subtitulo" => $JwtAuth->desencriptar($vDetPub->subtitulo),
              "parrafo" => $JwtAuth->desencriptar($vDetPub->parrafo),
              "proceso_eliminacion" => false,
            );
            $contenidoData[] = $rowDet;
          }

          $bibliografiaData = array();
          $biblioQuery = PublicacionesModelo::join("teci_page_publicaciones_bibliografia AS fuent", "teci_page_publicaciones.id", "=", "fuent.publicacion")
          ->where('teci_page_publicaciones.token_publicacion',$vPub->token_publicacion)
          ->get();

          foreach ($biblioQuery as $vFuentPub) {
            $rowFuent = array(
              "token_bibliografia" => $vFuentPub->token_bibliografia,
              "fuente" => $JwtAuth->desencriptar($vFuentPub->fuente),
              "detalle_fuente" => $JwtAuth->desencriptar($vFuentPub->detalle_fuente),
              "proceso_eliminacion" => false,
            );
            $bibliografiaData[] = $rowFuent;
          }

          $row = array(
            "token_publicacion" => $vPub->token_publicacion,
            "folio_publicacion" => $JwtAuth->generar($vPub->folio_publicacion),
            "fecha_publicacion" => $vPub->fecha_publicacion,
            "encabezado" => $JwtAuth->desencriptar($vPub->encabezado),
            "token_resena" => $vPub->token_resena,
            "resena_contenido" => $JwtAuth->desencriptar($vPub->resena_contenido),
            "contenido" => $contenidoData,
            "bibliografia" => $bibliografiaData,
          );
          $publicacion[] = $row;
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'publicacion' => $publicacion
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'no hay publicaciones recientes'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function publicacion_new_desglose($JwtAuth,$idNewPub,$desglose){
    $parrafosParaInsertar = [];
    foreach ($desglose as $vDesg) {
      //$vDesg['subtitulo']
      $parrafosParaInsertar[] = [
        "token_publicacion_content" => Str::uuid()->toString(),
        "publicacion" => $idNewPub,
        "subtitulo" => $JwtAuth->encriptar($vDesg['subtitulo']),
        "parrafo" => $JwtAuth->encriptar($vDesg['parrafo']),
        "status_content_pub" => TRUE,
      ];
    }
    if (!empty($parrafosParaInsertar)) {
      $insertPubContenido = DB::table('teci_page_publicaciones_contenido')->insert($parrafosParaInsertar);

      if (!$insertPubContenido) {
        throw new \Exception("Error crítico al registrar el contenido de la publicación.");
      }
    }
  }

  private function publicacion_desglose_update($JwtAuth,$desglose){
    foreach ($desglose as $vDesg) {
      try {
        DB::table('teci_page_publicaciones_contenido')
        ->where('token_publicacion_content',$vDesg['token_publicacion_content'])
        ->update([
          "subtitulo" => $JwtAuth->encriptar($vDesg['subtitulo']),
          "parrafo"   => $JwtAuth->encriptar($vDesg['parrafo']),
        ]);
      } catch (\Exception $e) {
        throw new \Exception("Error crítico al registrar el contenido de la publicación.");
      }
    }
  }

  private function publicacion_desglose_delete($desglose){
    foreach ($desglose as $vDesg) {
      try {
        DB::table('teci_page_publicaciones_contenido')
        ->where('token_publicacion_content',$vDesg['token_publicacion_content'])
        ->delete();
      } catch (\Exception $e) {
        throw new \Exception("Error crítico al registrar el contenido de la publicación.");
      }
    }
  }

  private function publicacion_new_fuentes($JwtAuth,$idNewPub,$fuentes_de_consulta){
    $fuentesParaInsertar = [];
    foreach ($fuentes_de_consulta as $vFue) {
      $fuentesParaInsertar[] = [
        "token_bibliografia" => Str::uuid()->toString(),
        "publicacion" => $idNewPub,
        "fuente" => $JwtAuth->encriptar($vFue['fuente']),
        "detalle_fuente" => $JwtAuth->encriptar($vFue['detalle_fuente']),
      ];
    }
    if (!empty($fuentesParaInsertar)) {
      $insertPubBibliografia = DB::table('teci_page_publicaciones_bibliografia')->insert($fuentesParaInsertar);

      if (!$insertPubBibliografia) {
        throw new \Exception("Error crítico al registrar el contenido bibliográfico de la publicación.");
      }
    }
  }

  private function publicacion_fuentes_update($JwtAuth,$fuentes_de_consulta){
    foreach ($fuentes_de_consulta as $vFue) {
      try {
        DB::table('teci_page_publicaciones_bibliografia')
        ->where('token_bibliografia',$vFue['token_bibliografia'])
        ->update([
          "fuente" => $JwtAuth->encriptar($vFue['fuente']),
          "detalle_fuente"   => $JwtAuth->encriptar($vFue['detalle_fuente']),
        ]);
      } catch (\Exception $e) {
        throw new \Exception("Error crítico al registrar el contenido de la publicación.");
      }
    }
  }

  private function publicacion_fuentes_delete($fuentes_de_consulta){
    foreach ($fuentes_de_consulta as $vFue) {
      try {
        DB::table('teci_page_publicaciones_bibliografia')
        ->where('token_bibliografia',$vFue['token_bibliografia'])
        ->delete();
      } catch (\Exception $e) {
        throw new \Exception("Error crítico al registrar el contenido de la publicación.");
      }
    }
  }

  public function actualiza_publicacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_publicacion' => 'required|string',
      'titulo' => 'required|string',
      'resena' => 'required|string',
      'desglose_nuevo' => 'required',
      'desglose_edit' => 'required',
      'desglose_delete' => 'required',
      'fuentes_de_consulta_nuevo' => 'required',
      'fuentes_de_consulta_edit' => 'required',
      'fuentes_de_consulta_delete' => 'required',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');

      $token_publicacion = $request->input('token_publicacion');
      $titulo = $request->input('titulo');
      $resena = $request->input('resena');
      $desglose_nuevo = $request->input('desglose_nuevo');
      $desglose_edit = $request->input('desglose_edit');
      $desglose_delete = $request->input('desglose_delete');
      $fuentes_de_consulta_nuevo = $request->input('fuentes_de_consulta_nuevo');
      $fuentes_de_consulta_edit = $request->input('fuentes_de_consulta_edit');
      $fuentes_de_consulta_delete = $request->input('fuentes_de_consulta_delete');

      $OKPublicacion = isset($token_publicacion) && !empty($token_publicacion);
      $OKTitulo = isset($titulo) && !empty($titulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $titulo);
      $OKResena = isset($resena) && !empty($resena) && preg_match($JwtAuth->filtroAlfaNumerico(), $resena);

      $OKDesgloseNuevo = isset($desglose_nuevo) && !empty($desglose_nuevo) && count($desglose_nuevo) > 0;
      $OKDesgloseEdit = isset($desglose_edit) && !empty($desglose_edit) && count($desglose_edit) > 0;
      $OKDesgloseDelete = isset($desglose_delete) && !empty($desglose_delete) && count($desglose_delete) > 0;
      $OKFuentesDEConsultaNuevo = isset($fuentes_de_consulta_nuevo) && !empty($fuentes_de_consulta_nuevo) && count($fuentes_de_consulta_nuevo) > 0;
      $OKFuentesDEConsultaEdit = isset($fuentes_de_consulta_edit) && !empty($fuentes_de_consulta_edit) && count($fuentes_de_consulta_edit) > 0;
      $OKFuentesDEConsultaDelete = isset($fuentes_de_consulta_delete) && !empty($fuentes_de_consulta_delete) && count($fuentes_de_consulta_delete) > 0;
      
      if ($OKPublicacion && $OKTitulo && $OKResena) {
        $queryPublic = PublicacionesModelo::where('token_publicacion',$token_publicacion)
        ->get();
        
        if ($queryPublic->isEmpty()) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontraron publicaciones registradas'
          );
        } else {
          foreach ($queryPublic as $vPub) {
            try {
              $idNewPub = DB::table('teci_page_publicaciones')->where('token_publicacion',$vPub->token_publicacion)->value('id');
              
              $upDatePub = PublicacionesModelo::where('token_publicacion',$vPub->token_publicacion)
              ->limit(1)->update(array(
                "encabezado" => $JwtAuth->encriptar($titulo),
              ));
              
              if (!$upDatePub) {
                throw new \Exception("Error al guardar la cabecera de la publicación.");
              }
    
              $newPubResena = DB::table('teci_page_publicaciones_resena AS pubRes')->where('publicacion',$idNewPub)
              ->limit(1)->update(array(
                "resena_contenido" => $JwtAuth->encriptar($resena)
              ));
              
              if (!$newPubResena) {
                throw new \Exception("Error al guardar la reseña de la publicación.");
              }

              if ($OKDesgloseNuevo) {
                $this->publicacion_new_desglose($JwtAuth,$idNewPub,$desglose_nuevo);
              }
              if ($OKDesgloseEdit) {
                $this->publicacion_desglose_update($JwtAuth,$desglose_edit);
              }
              if ($OKDesgloseDelete) {
                $this->publicacion_desglose_delete($desglose_delete);
              }

              if ($OKFuentesDEConsultaNuevo) {
                $this->publicacion_new_fuentes($JwtAuth,$idNewPub,$fuentes_de_consulta_nuevo);
              }
              if ($OKFuentesDEConsultaEdit) {
                $this->publicacion_fuentes_update($JwtAuth,$fuentes_de_consulta_edit);
              }
              if ($OKFuentesDEConsultaDelete) {
                $this->publicacion_fuentes_delete($fuentes_de_consulta_delete);
              }
              
              DB::commit(); // Si llegamos aquí, todo se guarda permanentemente
              return response()->json(['status' => 'success','message' => 'Publicación registrada exitosamente'], 200);
            } catch (\Exception $e) {
              DB::rollBack();
              // 1. Guardar el error real en storage/logs/laravel.log
              \Log::error("Error al recibir activo: " . $e->getMessage());
              // 2. Responder al usuario con algo genérico
              return response()->json(['status' => 'error','message' => 'Registro de publicación incompleto, revise su información o comuniquese a soporte.' . $e->getMessage()], 500);
            }  
          }
        }

        DB::beginTransaction();
      } else {
        $mensaje_error = '';
        if (!$OKPublicacion) $mensaje_error = 'Error al seleccionar publicación, intentelo nuevamente o comuniquese a soporte';
        if (!$OKTitulo) $mensaje_error = 'Error al registrar título de publicación, intentelo nuevamente o comuniquese a soporte'; 
        if (!$OKResena) $mensaje_error = 'Error al registrar reseña de publicación, intentelo nuevamente o comuniquese a soporte'; 
        $dataMensaje = array('code' => 200,'status' => 'error','message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function publicacionEliminar(Request $request){
    $validate = \Validator::make($request->all(),[
      'token_publicacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_publicacion = $request->input('token_publicacion');
      
      $queryPublic = PublicacionesModelo::where([
        'status' => TRUE,
        'token_publicacion' => $token_publicacion
      ])
      ->get();

      if (count($queryPublic) > 0) {
        foreach ($queryPublic as $vPub) {
          $deLetePub = PublicacionesModelo::where('token_publicacion',$vPub->token_publicacion)
          ->limit(1)->update(array(
            "status" => FALSE,
            "fecha_delete_publicacion" => time()
          ));

          if ($deLetePub) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Publicación eliminada exitosamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Publicación no eliminada, intentelo nuevamente o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'no hay publicaciones recientes'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function publicacionRestaurar(Request $request){
    $validate = \Validator::make($request->all(),[
      'token_publicacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_publicacion = $request->input('token_publicacion');
      
      $queryPublic = PublicacionesModelo::where([
        'status' => FALSE,
        'token_publicacion' => $token_publicacion
      ])
      ->get();

      if (count($queryPublic) > 0) {
        foreach ($queryPublic as $vPub) {
          $deLetePub = PublicacionesModelo::where('token_publicacion',$vPub->token_publicacion)
          ->limit(1)->update(array(
            "status" => TRUE,
            "fecha_delete_publicacion" => NULL
          ));

          if ($deLetePub) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Publicación restaurada exitosamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Publicación no restaurada, intentelo nuevamente o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'no hay publicaciones recientes'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function publicacionEliminacionPermanente(Request $request){
    $validate = \Validator::make($request->all(),[
      'token_publicacion' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_publicacion = $request->input('token_publicacion');
      
      $queryPublic = PublicacionesModelo::where([
        'status' => FALSE,
        'token_publicacion' => $token_publicacion
      ])
      ->get();

      if (count($queryPublic) > 0) {
        foreach ($queryPublic as $vPub) {
          $idNewPub = DB::table('teci_page_publicaciones')->where('token_publicacion',$vPub->token_publicacion)->value('id');
          $deLeteBibliografia = DB::table('teci_page_publicaciones_bibliografia')
          ->where('publicacion',$idNewPub)
          ->delete();

          $deLeteContenido = DB::table('teci_page_publicaciones_contenido')
          ->where('publicacion',$idNewPub)
          ->delete();

          $deLeteResena = DB::table('teci_page_publicaciones_resena AS pubRes')
          ->where('publicacion',$idNewPub)
          ->delete();

          $deLetePub = PublicacionesModelo::where('token_publicacion',$vPub->token_publicacion)
          ->delete();          

          if ($deLetePub) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Publicación restaurada exitosamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Publicación no restaurada, intentelo nuevamente o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'no hay publicaciones recientes'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
