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
use App\Models\VisitasModelo;
use PDF;
use QRCode;

class TICS_VisitasController extends Controller
{
    public function totalVisitas(){
        $total_visitas_salida = 0;
        $pi_server = $_SERVER['REMOTE_ADDR'];
        $query_visitas = VisitasModelo::all();
        
        if (count($query_visitas) == 0) {
            $new_visita = DB::select("INSERT INTO teci_page_visitas (ip,fecha_visita) VALUES (?,?)",[$pi_server,time()]);
            $total_visitas_salida = 1;    
        } else {
            $total_visitas_salida = count($query_visitas);
            $query_visitasIP = VisitasModelo::where(['vis.ip' => $pi_server])->get();
            if (count($query_visitasIP) == 0) {
                $new_visita = DB::select("INSERT INTO teci_page_visitas (ip,fecha_visita) VALUES (?,?)",[$pi_server,time()]);
                $total_visitas_salida = $total_visitas_salida + 1;  
            } else {
                $last_visita = VisitasModelo::where(['vis.ip' => $pi_server])
                    ->orderBy('vis.id','DESC')->limit(1)->get();
                foreach ($last_visita as $lvis) {
                    $fecha_registro = $lvis->fecha_visita + 3600;
                    $fecha_t_actual = date('d-m-Y H:i:s',time());
                    if (time() >= $fecha_registro){
                        
                        $new_visita = DB::select("INSERT INTO teci_page_visitas (ip,fecha_visita) VALUES (?,?)",[$pi_server,time()]);
                        $total_visitas_salida = $total_visitas_salida + 1; 
                    }
                }
            }
        }
        
        $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'total_visitas' => $total_visitas_salida,
        );
        return response()->json($dataMensaje,$dataMensaje['code']); 
    }
}
