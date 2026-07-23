<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MAIN_MenuController extends Controller
{
    public function getRelojes(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true); 
        $hoy = time();
        //da_te_default_timezone_set('America/Mexico_City');
                
        $dias = array("Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sábado");
        $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto",
                    "Septiembre","Octubre","Noviembre","Diciembre");
                
        $zonaSistema = array(
            'lugar' => 'Ciudad de México',//id de usuario
            'fecha' => $dias[date('w',$hoy)].", ".date('j',$hoy)." de ".$meses[date('n',$hoy)-1]." de ".date('Y',$hoy),
            'hora' => date('h:i:s A',$hoy),
        );

        $zOnaUser = DB::select('SELECT emp.zona_horaria 
            FROM empresas AS emp 
            JOIN empresapersonal AS emppers
            JOIN personal AS pers
            JOIN teci_usuarios AS users
            WHERE emp.id = emppers.empresa  
            AND emppers.personal = pers.id
            AND pers.usuario = users.id
            AND users.user_token = ?',[$usuario->user_token]);
    
        //da_te_default_timezone_set($zOnaUser[0]->zona_horaria);
        $zonaUserHoraria = array(
            'lugar' => 'Horario local',//id de usuario
            'fecha' => $dias[date('w',$hoy)].", ".date('j',$hoy)." de ".$meses[date('n',$hoy)-1]." de ".date('Y',$hoy),
            'hora' => date('h:i:s A',$hoy),
        );

        $arraYrelojAutom = array(
            $zonaSistema,
            $zonaUserHoraria
        );

        return $arraYrelojAutom;
    }

    public function getFechaInput(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true); 
        $hoy = time();
        //da_te_default_timezone_set('America/Mexico_City');
                
        $zOnaUser = DB::select('SELECT emp.zona_horaria FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.id = empuser.empresa  
            AND empuser.usuario = users.id AND users.usuario_token = ?',[$usuario->user_token]);
    
        //da_te_default_timezone_set($zOnaUser[0]->zona_horaria);

        return response()->json([
            'fechaAlta' => date('d/m/Y',$hoy),
            'codigo' => 200,
            'status' => 'success'
        ]);
    }
}
