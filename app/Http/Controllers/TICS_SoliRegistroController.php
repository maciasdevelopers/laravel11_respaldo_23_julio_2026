<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\EmpresasModelo;
use App\Models\SoliRegistroModelo;

class TICS_SoliRegistroController extends Controller{
    
    public function solicitudRegistroVigentes(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listaSolicitudes = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto '.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 

                $empList = SoliRegistroModelo::join("main_empresas AS emp","teci_solicitud_registro.empresa_ancla","=","emp.id")
                ->where(['emp.empresa_token' => $usuario->empresa_token,'teci_solicitud_registro.status' => TRUE])->get();

                foreach ($empList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    if ($value->denominacion_rs == '') {
                        $nombreEmpresa = $JwtAuth->desencriptar($value->apePaterno)." ".$JwtAuth->desencriptar($value->apeMaterno)." ".$JwtAuth->desencriptar($value->nombre);
                        $tipoPersona = 'persona física';
                    } else {
                        $nombreEmpresa = $JwtAuth->desencriptar($value->denominacion_rs);
                        $tipoPersona = 'persona moral';
                    }

                    $arrayforeach = array(
                        'token_soli_registro' => $value->token_soli_registro,
                        'company_name' => $nombreEmpresa,
                        'tipoPersona' => $tipoPersona,
                        'fecha_nac_const' => gmdate('Y-m-d H:i:s',$value->fecha_nac_const),
                        'fecha_registro' => date('d-m-Y H:i:s',$value->fecha_registro),
                        'company_rfc' => $JwtAuth->desencriptar($value->rfc),
                        'telefono' => $JwtAuth->desencriptar($value->telefono),
                        'extension' => $JwtAuth->desencriptar($value->extension),
                        'correo' => $JwtAuth->desencriptar($value->correo),
                    );
                    $listaSolicitudes[] = $arrayforeach;
                }
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'arrayEmpVig' => $listaSolicitudes,
                );

            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

}
