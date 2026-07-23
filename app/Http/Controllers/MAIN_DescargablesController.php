<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\DescargablesModelo;
use PDF;
use QRCode;

class MAIN_DescargablesController extends Controller
{
    public function listaDescargables(){
        $arrayDownloads = array();
        $downList = DescargablesModelo::all();

        foreach ($downList as $value) {
            $each = array(
                "token_down" => $value->token_down,  
                "icon_app" => $value->icon_app,    
                "name_app" => $value->name_app,    
                "link_app" => $value->link_app,
            );
            $arrayDownloads[] = $each;
        }

        $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'downloads' => $arrayDownloads
        );

        return response()->json($dataMensaje, $dataMensaje['code']);
    }

}
