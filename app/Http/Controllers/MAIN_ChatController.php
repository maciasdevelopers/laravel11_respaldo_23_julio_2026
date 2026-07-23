<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\ChatModelo;
use App\Models\User;
use PDF;
use QRCode;

class MAIN_ChatController extends Controller{
  public function listaHistoryChat(Request $request){
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'user_chat_recept' => 'nullable|string'
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
      $user_chat_recept = $request->input('user_chat_recept');
      $arrayChatUsers = array();
      
      $areaEmisor = '';
      $selectareaMiUser = User::join("vhum_empleados_catalogo AS trab", "teci_usuarios_catalogo.empleado", "=", "trab.id")
      ->join("vhum_empleados_catalogo_area AS area", "trab.area", "=", "area.id")
      ->join("vhum_empleados_catalogo_cargo AS cargo", "trab.cargo", "=", "cargo.id")
      ->join("sos_personas AS people", "trab.empleado_name", "=", "people.id")
      ->join("main_empresa_usuario AS empuser", "trab.id", "=", "empuser.empleado")
      ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
      ->where([
        'teci_usuarios_catalogo.usuario_token' => $usuario,
      ])
      ->select("area.areaemp")
      ->first();
      
      if ($selectareaMiUser) {
        switch ($selectareaMiUser->areaemp) {
          case 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaEmisor = 'messageIngresos';
            break;
          case 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaEmisor = 'messageEgresos';
            break;
          case 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaEmisor = 'messageTesoreria';
            break;
          case 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaEmisor = 'messagevHumano';
            break;
          case 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaEmisor = 'messageContabilidad';
            break;
          case 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=':
            $areaEmisor = 'messageTecInfo';
            break;
          case 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=':
            $areaEmisor = 'messageAdmGeneral';
            break;
          default:
            $areaEmisor = '';
            break;
        }
      }

      $areaReceptor = '';
      $selectNameMensajero = User::join("vhum_empleados_catalogo AS trab", "teci_usuarios_catalogo.empleado", "=", "trab.id")
      ->join("vhum_empleados_catalogo_area AS area", "trab.area", "=", "area.id")
      ->join("vhum_empleados_catalogo_cargo AS cargo", "trab.cargo", "=", "cargo.id")
      ->join("sos_personas AS people", "trab.empleado_name", "=", "people.id")
      ->join("main_empresa_usuario AS empuser", "trab.id", "=", "empuser.empleado")
      ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
      ->where([
        'teci_usuarios_catalogo.usuario_token' => $user_chat_recept,
      ])
      ->first();

      if ($selectNameMensajero) {
        switch ($selectNameMensajero->areaemp) {
          case 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaReceptor = 'messageIngresos';
            break;
          case 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaReceptor = 'messageEgresos';
            break;
          case 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaReceptor = 'messageTesoreria';
            break;
          case 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaReceptor = 'messagevHumano';
            break;
          case 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4':
            $areaReceptor = 'messageContabilidad';
            break;
          case 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=':
            $areaReceptor = 'messageTecInfo';
            break;
          case 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=':
            $areaReceptor = 'messageAdmGeneral';
            break;
          default:
            $areaReceptor = '';
            break;
        }
      }


      $chatUlista = DB::select("SELECT chat.token_chat,chat.fecha_chat,chat.area_chat_emisor,chat.receptor_chat,chat.area_chat_receptor,
                  emisor.usuario_token AS emisor,receptor.usuario_token AS receptor FROM teci_user_chat AS chat JOIN teci_usuarios_catalogo AS emisor
                  JOIN teci_usuarios_catalogo AS receptor WHERE chat.emisor_chat = emisor.id AND chat.receptor_chat = receptor.id AND
                  (chat.emisor_chat = (SELECT id FROM teci_usuarios_catalogo WHERE usuario_token = ?) or chat.receptor_chat = (SELECT id FROM teci_usuarios_catalogo WHERE usuario_token = ?))",
        [$usuario, $usuario]
      );

      foreach ($chatUlista as $value) {
        $nameMensajero = '';
        $arrayhistrialChat = array();

        $chatHistorial = ChatModelo::join("detalle_teci_user_chat AS chat_detail", "teci_user_chat.id", "chat_detail.chat")
          ->join("teci_usuarios_catalogo AS users", "chat_detail.emisor", "users.id")
          ->where([
            'teci_user_chat.token_chat' => $value->token_chat
          ])->orderBy('chat_detail.id', 'desc')
          ->get();

        foreach ($chatHistorial as $valchatHistorial) {

          if ($usuario == $valchatHistorial->user_token) {
            $tipoMensajero = "messageEmisor";
            $divMessagearea = $areaEmisor;
          } else {
            $tipoMensajero = "messageReceptor";
            $divMessagearea = $areaReceptor;
          }

          $arrayeachdetale = array(
            "tipoMensajeCss" => 'col s12 divmessage ' . $tipoMensajero . ' ' . $divMessagearea,
            "tipoMensajeCss" => 'col s12 divmessage ' . $tipoMensajero . ' ' . $divMessagearea,
            //"mensaje_chat" => $JwtAuth->desencriptar($valchatHistorial->mensaje_chat),
            "mensaje_chat" => $valchatHistorial->mensaje_chat,
          );
          $arrayhistrialChat[] = $arrayeachdetale;
        }

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $arrayForeach = array(
          "token_chat" => $value->token_chat,
          "fecha_chat" => date('d-m-Y H:i:s', $value->fecha_chat),
          "mensajero" => $JwtAuth->desencriptar($selectNameMensajero[0]->paterno) . " " . $JwtAuth->desencriptar($selectNameMensajero[0]->materno) . " " . $JwtAuth->desencriptar($selectNameMensajero[0]->nombre),
          "conversacion" => $arrayhistrialChat
        );
        $arrayChatUsers[] = $arrayForeach;
      }
      return response()->json([
        'datosChat' => $arrayChatUsers,
        'codigo' => 200,
        'status' => 'success'
      ]);
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}