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
use App\Models\User;
use Illuminate\Validation\Validator;

class MAIN_ModulosController extends Controller{
    public function catalogoModulosSOS(){
        $JwtAuth = new \App\Helpers\JwtAuth();
        $list_modulos = array();
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "ssic"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("ssicem82dllwL1ozeUhsRE9GZ29uRXJuUT09OjoxMjM0NTY3ODEyMzQ1Njc4")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "descarga_xml"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("descarga_xmlRGdOVHhZMFozS1ptUmZJZ29XMW5FL2dDZ3BubUVzODR4aTN3SnE1aWR5RT06OjEyMzQ1Njc4MTIzNDU2Nzg=")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "logistica"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("logisticaZnpQZzIxbGxORytFWW5uQ3A5WWNhd0hZbFBteGppc1Z5VzNkZE9SM0I4TT06OjEyMzQ1Njc4MTIzNDU2Nzg")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "compras"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("GM5aDJwd0pKY2E2MHJoUW5hUkZvQT09OjoxMjM0NTY3ODEyMzQ1Njc4")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "gestion_proyectos"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("gestion_proyectosZUpOYkRNby81M2ZaT0ozYjlmb0srL2FwcCtxa0ZUY1N3TUYyU3F1Y05RRT06OjEyMzQ1Njc4MTIzNDU2Nzg=")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "ter_asoc"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("ter_asocdEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "ter_cli"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("ter_clidEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "ter_prv"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("ter_prvdEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=")));
        //$updateModulos = DB::table("sos_modulos_sistemas")->where(["modulo" => "ter_emp"])->limit(1)->update(array("token_modulo" => $JwtAuth->encriptarToken("ter_empdEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=")));
        
        $queryModulos = DB::table("sos_modulos_sistemas")->get();
        foreach ($queryModulos as $rMod) {
            $modulo_mantenimiento = false;
            if ($rMod->mantenimiento == TRUE) $modulo_mantenimiento = true;
            $modulo_acceso = false;
            if ($rMod->acceso == TRUE) $modulo_acceso = true;
            $row = array (
                "token_modulo" => $rMod->token_modulo,
                "modulo" => $rMod->modulo,
                "modulo_mantenimiento" => $modulo_mantenimiento,
                "modulo_acceso" => $modulo_acceso,
            );
            $list_modulos[] = $row;
        }
        return response()->json(["status" => "success","code" => 200,"modulos" => $list_modulos]);
    }
    
    public function modulosConfigSOS(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "token_modulo" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $queryModulos = DB::table("sos_modulos_sistemas")->where(["token_modulo" => $parametrosArray["token_modulo"]])->get();
                foreach ($queryModulos as $rMod) {
                    $modulo_mantenimiento = false;
                    if ($rMod->mantenimiento == TRUE) $modulo_mantenimiento = true;
                    $modulo_acceso = false;
                    if ($rMod->acceso == TRUE) $modulo_acceso = true;
                    $dataMensaje = array(
                        "status" => "success",
                        "code" => 200,
                        "modulo" => $rMod->modulo,
                        "modulo_mantenimiento" => $modulo_mantenimiento,
                        "modulo_acceso" => $modulo_acceso,
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
}
