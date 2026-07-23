<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use App\Models\ProductosModelo;
use App\Models\UMedidaModelo;

class MAIN_ImagesController extends Controller
{
    public function convertidor(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        //echo $parametros->proddata;
        $parametros->data_load;
        $listImg = array();        
        $arrayImg = array('lgSOS.jpg','sos-mexico.png',
            'nosotros/pyme.jpg','nosotros/registro_egresos.jpg',
            'nosotros/control_inventarios.jpg','nosotros/mala-gestion.jpg',
            'nosotros/mision.jpg','nosotros/vision.jpg');
            
        for ($i=0; $i < count($arrayImg); $i++) { 
            $path = Storage::path('public/homePagePrincipal/'.$arrayImg[$i]);
            $type = pathinfo($path,PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            $baseses = 'data:image/'.$type.';base64,'.base64_encode($data);
            $listImg[] = $baseses;
        }

        //echo $baseses; exit;
        return response()->json([
            'imgconverted' => $listImg,
            'codigo' => 200,
            'status' => 'success'
        ]); 
    }
}
