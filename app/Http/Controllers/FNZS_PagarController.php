<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\OrdenPagoModelo;
use APP\Models\ProveedoresModelo;
use App\Models\MonedElectModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use Illuminate\Support\Collection;
use App\Http\Controllers\FNZS_CajaController;
use App\Http\Controllers\FNZS_CuentBancController;
use App\Http\Controllers\FNZS_MonedElectController;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

class FNZS_PagarController extends Controller{
}
