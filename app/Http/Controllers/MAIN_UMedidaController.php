<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Http\Response;
Use App\Models\UMedidaModelo;
use Illuminate\Support\Facades\DB;
use PDF;

class INVENT_UMedidaController extends Controller{
    
    public function clasificacionMedidaSat(){
        $listMedidas = DB::select("SELECT representa FROM unidad_medida 
            GROUP BY representa ORDER BY representa ASC");
        //return $listMedidas;
        //echo 'hola';
        return response()->json([
            'listMedidas' => $listMedidas,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function listaUnidadesMedida(){
        $listMedidas = UMedidaModelo::all();
        return response()->json([
            'listMedidas' => $listMedidas,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }
    
    public function pdfHtml(){
        echo $_GET['tokenRequi'];
        $JwtAuth = new \JwtAuth();
        $listMedidas = UMedidaModelo::all();
        
        $pdfGenerado = $JwtAuth->generaPdf("information-fnz","compras","requisiciones","alta de requisición");
        $dompdf = \PDF::loadHtml($pdfGenerado);
        $dompdf->setPaper("A2", "portrait");
        //$contenidoPDF = $dompdf->output();
        //$contenidoPDF = $dompdf->download('cert.pdf');
        $contenidoPDF = $dompdf->stream();
        
        return $contenidoPDF;
        /*return response()->json([
            'listMedidas' => $listMedidas,
            'codigo' => 200,
            'status' => 'success'
        ]);*/
    }
    
    public function medidasSat(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $listMedidas = UMedidaModelo::select('token_unidad_medida','unidad_medida','sat_clave')
        ->where([
            'representa' => $parametros['classifUmedida'],
        ]);
        //return $listMedidas;
        //echo 'hola';
        return response()->json([
            'listMedidas' => $listMedidas,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }
    
    public function medidasSatServicios(){
        $JwtAuth = new \JwtAuth();
        $listMedidas = UMedidaModelo::select('token_unidad_medida','unidad_medida','sat_clave','representa')
        ->where([
            'serv_bool' => TRUE,
        ])->get();
        return response()->json([
            'listMedidas' => $listMedidas,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }
    
    public function postMedidasSatServicios(Request $request){
        $JwtAuth = new \JwtAuth();
        $json = $request->input('json');
        $parametros = json_decode($json);
        $parametrosArray = json_decode($json,true);

        if (!empty($parametros) && !empty($parametrosArray)) {
 
            $validate = \Validator::make($parametrosArray,[
                'clave' => 'required|string']);
            if ($validate->fails()) {
                $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                'errors' => $validate->errors()
            );
            } else {
                $listMedidas = UMedidaModelo::select('token_unidad_medida','unidad_medida','sat_clave','representa')
                ->where('unidad_medida','LIKE','%'.$parametrosArray['clave'].'%')
                ->where(['serv_bool' => TRUE])
                ->orwhere('sat_clave','LIKE','%'.$parametrosArray['clave'].'%')
                ->where(['serv_bool' => TRUE])
                ->orwhere('representa','LIKE','%'.$parametrosArray['clave'].'%')
                ->where(['serv_bool' => TRUE])
                ->get();
                
                $dataMensaje = array(
                    'listMedidas' => $listMedidas,
                    'code' => 200,
                    'status' => 'success'
                );
                
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La información que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

}
