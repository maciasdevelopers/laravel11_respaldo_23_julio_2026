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
use App\Models\DireccionesModelo;
use App\Models\CodPostalModelo;

class MAIN_GPTController extends Controller{
    public function respuestaChatGPT(Request $request){
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "clave_chat" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los datos del usuario son incorrectos, favor de verificarlos'.$validate->errors(),
                    'errors' => $validate->errors()
                );

            } else {
                $clave_chat = $parametrosArray["clave_chat"];
                $api_key = 'sk-soschat-l6qWQCPi2cPUxKexpuIoT3BlbkFJekU409fGC5weyDlB1yuc';
                $url = 'https://api.openai.com/v1/engines/gpt-3.5-turbo-instruct/completions'; // Endpoint para Completions API
                
                // Datos del cuerpo de la solicitud
                $data = array(
                    'prompt' => 'Hola, ¿cómo estás hoy?',
                    'max_tokens' => 50
                );
                
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>'{
                        "model": "gpt-3.5-turbo",
                        "messages": [
                            {
                                "role": "user",
                                "content": "'.$clave_chat.'"
                            }
                        ]
                    }',
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer '.$api_key,
                        'Content-Type: application/json'
                    ),
                ));
                
                $response = curl_exec($curl);
                curl_close($curl);
                //curl_close($curl);
                
                $json = json_decode($response);
                echo $response;
                $completion = $json->choices[0]->message->content;
                echo $response;
                //$dataMensaje = array("status" => "error","code" => 200,"message" => "No se recibió una respuesta válida");
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Los datos no son correctos'
            ); 
        }
        return response()->json($dataMensaje,$dataMensaje['code']); 
    }
}
