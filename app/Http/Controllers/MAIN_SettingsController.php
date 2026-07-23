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
use Illuminate\Validation\Validator;

class MAIN_SettingsController extends Controller{
    
    public function updateLanguage(Request $request){
        $JwtAuth = new \App\Helpers\JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $arrayParams = json_decode($jsonUser,true); 

        if (!empty($parametros) && !empty($arrayParams)) {
            $validate = Validator($arrayParams,[
                'user_token' => 'required|string',
                'lenguaje' => 'required|string',
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'usuario no identificado old',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($arrayParams['user_token'],true);
                $len_guaje = $arrayParams['lenguaje'];
                
                if ($len_guaje == "es") {
                    $titulo_success = "idioma actualizado";
                    $titulo_error = "idioma no actualizado";
                } else {
                    $titulo_success = "updated language";
                    $titulo_error = "language not updated";  
                }
                
                $updateLangQuery = DB::table('teci_user_settings AS conf')
                ->join("main_usuarios AS users","conf.usuario","users.id")
                ->join("vhum_personal AS pers","users.empleado","pers.id")
                ->join("main_empresapersonal AS emppers","pers.id","emppers.personal")
                ->join("main_empresas AS emp","emppers.empresa","=","emp.id")
                ->where([
                    'emp.emp_token' => $usuario->emp_token,
                    'users.user_token' => $usuario->user_token,
                ])
                ->limit(1)->update(
                    array(
                        'conf.lenguaje' => $len_guaje,
                    )
                );
                
                if ($updateLangQuery) {
                    $dataMensaje = array(
                        'status' => 'success',
                        'code' => 200,
                        'message' => $titulo_success, 
                    );
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => $titulo_error, 
                    );
                }
                
            }
            
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'usuario no identificado'
            );
        }  
        //return $JwtAuth->signup($email,$passDecrypt);
        return response()->json($dataMensaje,200);
    }
    
}
