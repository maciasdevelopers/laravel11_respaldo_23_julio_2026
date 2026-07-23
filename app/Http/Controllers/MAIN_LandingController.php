<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use App\Models\LandingModelo;
use App\Models\ServiciosModelo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MAIN_LandingController extends Controller
{
    public function listaServicios(){
        $JwtAuth = new \JwtAuth();
        $arrayServLanding = array();
        /*$resServ = DB::select('SELECT in_egr_catalogo_servicios.id,class.codigo AS clasificacion,gen.folio_genero AS genero,
            in_egr_catalogo_servicios.folio,serv.servicio,land.ayuda,
            land.beneficios,serv.imagen,land.imagen AS img_landing 
            FROM servicios AS serv 
            JOIN clasificacion AS class
            JOIN genero AS gen
            JOIN catalogo_servicios AS in_egr_catalogo_servicios
            JOIN landing_page AS land
            WHERE land.servicio = in_egr_catalogo_servicios.id
            AND in_egr_catalogo_servicios.servicio = serv.id
            AND serv.clasificacion = class.id
            AND serv.genero = gen.id
            AND gen.clasificacion = class.id
            AND in_egr_catalogo_servicios.administrador = 1');*/
      
        $servList = ServiciosModelo::join("sos_ps_genero AS gen","in_egr_catalogo_servicios.genero","=","gen.id")
        ->join("teci_page_servicios AS land","in_egr_catalogo_servicios.id","=","land.land_servicio")
        ->join("teci_unidad_medida AS umed","in_egr_catalogo_servicios.unidad_medida_homologada","=","umed.id")
        ->join("main_empresas AS emp","in_egr_catalogo_servicios.administrador","=","emp.id")
        ->where([
            'emp.empresa_token' => 'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==',
        ])->get();
        
        foreach ($servList as $value) {
            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/'.$value->root_tkn.
                '/0001-cpc/catalogos/servicios/'
                .$JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                $JwtAuth->generar($value->folio).'-'.$value->fechaAlta.'/'.$JwtAuth->desencriptar($value->imagen)));
            
            $arrayForeachVig = array(
                "c_token" => $value->token_cat_servicios,
                "imagen" => $logo_serv,
                "servicio" => $JwtAuth->desencriptar($value->servicio)
            );
            $arrayServLanding[] = $arrayForeachVig; 
        }

        return response()->json([
            'datosServicio' => $arrayServLanding,
            'codigo' => 200,
            'status' => 'success'
        ]); 
    }

    public function getJpgSolucion($filename){
        //$public_path = public_path();
        ////echo $public_path;
        ////die();
        //$url = $public_path.'/storage/app/public/services/'.$imagen;
        //if (\Storage::exists($imagen)) {
        //    return response()->download($url);
        //}
        //abort(404);

        //$isset = \Storage::disk('servmedia')->exists($imagen);
        //if ($isset) {
        //    $archivo = \Storage::disk('servmedia')->get($imagen);
        //    echo $archivo;
        //    return new Response($archivo,200);
        //} else {
        //    # code...
        //}
        
        $path = storage_public('services/' . $filename);

        if (!File::exists($path)) {
            abort(404);
        }
    
        $file = File::get($path);
    
        $type = File::mimeType($path);
    
        $response = Response::make($file, 200);
    
        $response->header("Content-Type", $type);
    
        return $response;
    }

    public function loginClientes(Request $request){
        echo "gola";
        die();
        $JwtAuth = new \JwtAuth();
        //recibir los mpost
            $jsonLogin = $request->input('json',null);
            $parametros = json_decode($jsonLogin);
            $arrayParams = json_decode($jsonLogin,true); 
        //validar los datos
            if (!empty($parametros) && !empty($arrayParams)) {
                $validate = \Validator::make($arrayParams,[
                    'email' => 'required|email',
                    'pass' => 'required',
                ]);

                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => 'usuario no identificado',
                        'errors' => $validate->errors()
                    );
                } else {
                    //cifrar contraseña
                    $email = $parametros->email;
                    $key ='textoencriptado';
                    $iv = "1234567812345678";
                    $encripta = openssl_encrypt($parametros->pass,"aes-256-cbc",$key,0,$iv);
                    $passDecrypt = base64_encode($encripta."::".$iv);
                    //devolver token o datos
                    $dataMensaje = $JwtAuth->signup($email,$passDecrypt);
                    if (!empty($parametros->getToken)) {// si existe token de identificacion envia losa datos decodificados
                        $dataMensaje = $JwtAuth->signup($email,$passDecrypt,true);
                    }
                }
                
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'usuario no identificado'
                );
            }  
        //return $JwtAuth->signup($email,$passDecrypt);
        return response()->json($dataMensaje,200);
    }
}
