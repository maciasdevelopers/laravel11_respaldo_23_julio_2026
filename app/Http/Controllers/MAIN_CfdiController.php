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
use App\Models\UsoCFDIModelo;
use App\Models\CancelacionCFDIModelo;
use App\Models\CfdiModelo;

class MAIN_CfdiController extends Controller{
    
    public function getListaUso(){
        return UsoCFDIModelo::all();
    }
    
    public function getMotivosCancelacion(){
        return CancelacionCFDIModelo::all();
    }
}