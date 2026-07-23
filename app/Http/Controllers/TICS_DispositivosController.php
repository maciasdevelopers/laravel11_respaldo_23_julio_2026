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
use App\Models\DispositivosModelo;
use App\Models\MonedElectModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use App\Models\PersonalModelo;
use PDF;
use QRCode;

class TICS_DispositivosController extends Controller{
    public function listaTipoDispositivo(){
        $dipositivos = array();
        $litDips = DB::select("SELECT * FROM teci_tipo_dispositivo");
        foreach ($litDips as $value) {	
            $array = array(
                "token_tipo_disp" =>$value->token_tipo_disp,
                "tipo" =>$value->tipo,
            );
            $dipositivos[] = $array;
        }

        return response()->json([
            'dispositivo' => $dipositivos,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function folioDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string'
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Dispositivo invalido',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $folioDispositivo = DB::select("SELECT 
                    IF (max(folio_dispositivo) IS NOT NULL,(max(folio_dispositivo)+1),1) AS folio
                    FROM dispositivos AS disp FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                    JOIN personal AS pers JOIN teci_usuarios_catalogo AS users
                    WHERE disp.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empper.empresa AND empper.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);

                $dataMensaje = array(
                    'dispositivo' => $JwtAuth->generar($folioDispositivo[0]->folio),
                    'code' => 200,
                    'status' => 'success'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);  
    }
    
    public function listaDispositivosVig(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $dipositivos = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string'
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Dispositivo invalido',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $selectDispositivo = DispositivosModelo::join("main_empresas AS emp","teci_dispositivos.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                ->where([
                    'teci_dispositivos.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token
                ])->get();

                foreach ($selectDispositivo as $resDispositivo) {

                  $tipoDispositivo = DB::table("teci_dispositivos AS disp")
                  ->join("teci_tipo_dispositivo AS tip","disp.tipo_dispositivo","tip.id")
                  ->where('disp.token_dispositivos',$resDispositivo->token_dispositivos)->value("tip.tipo");

                  $arrayDispositivos = array(
                    "token_dispositivos" => $resDispositivo->token_dispositivos,
                    "folio" => "DISP-".$JwtAuth->generarFolio($resDispositivo->folio_dispositivo),
                    "alias" => $JwtAuth->desencriptar($resDispositivo->alias),
                    "tipo" => $tipoDispositivo,
                  );

                  $dipositivos[] = $arrayDispositivos;
                }

                $dataMensaje = array(
                    'dispositivo' => $dipositivos,
                    'code' => 200,
                    'status' => 'success'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']); 
    }

    public function verDispositivoBankVig(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        $dispotvos = $parametros->token_dispositivos;
        $dipositivos = array();
        $verDispositivo = DispositivosModelo::join('cuenta AS cuentaa','dispositivos.cuenta','cuentaa.id')
        ->join('responsables AS respons','dispositivos.id','respons.dispositivo')
        ->join("personal AS pers","respons.responsable","pers.id")
        ->join("personas AS people","pers.personal","people.id")
        ->join('bancos AS ban','cuentaa.banco','ban.id')
        ->join("main_empresas AS emp","cuentaa.empresa","emp.id")
        ->join("empresapersonal AS empuser","emp.id","empuser.empresa")
        ->join("personal AS peruser","empuser.personal","peruser.id")
        ->join("teci_usuarios_catalogo AS users","peruser.usuario","users.id")
        ->where([
            'dispositivos.status' => TRUE,
            'dispositivos.token_dispositivos' => $dispotvos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token
        ])->get();

        foreach ($verDispositivo as $resDispositivo) {
            /*$responsable = DB::table('responsables')
            ->join("dispositivos AS disp","responsables.dispositivo","disp.id")
            ->join("personal AS pers","responsables.responsable","pers.id")
            ->join("personas AS people","pers.personal","people.id")
            ->where([
                'disp.token_dispositivos' => $dispotvos
            ])->get();

            $arrayRespons = array();
            foreach ($responsable as $value) {
                $arrayRes = array(
                    "token_responsables" => $value->token_responsables,
                    "ocupacion" => $value->ocupacion,
                    "turno_inicio" => $value->turno_inicio,
                    "turno_fin" => $value->turno_fin,
                    "nombre_completo" => $JwtAuth->desencriptar($value->paterno)." ".$JwtAuth->desencriptar($value->materno)." ".
                    $JwtAuth->desencriptar($value->nombre),
                    "img_perfil" => $JwtAuth->desencriptar($value->img_perfil) 
                );
                $arrayRespons[] = $arrayRes;
            }*/

            if ($resDispositivo->tipo_dispositivo == 'tok_fisico') {
                $tipo = 'Token fisico';
            }
            if ($resDispositivo->tipo_dispositivo == 'tok_digital') {
                $tipo = 'Token digital';
            }
            if ($resDispositivo->tipo_dispositivo == 'telefono') {
                $tipo = 'Telefono';
            }
            if ($resDispositivo->tipo_dispositivo == 'clip') {
                $tipo = 'Clip';
            }
            if ($resDispositivo->tipo_dispositivo == 'kit_ventas') {
                $tipo = 'Kit punto de venta';
            }

            $primercuent = substr($resDispositivo->cuenta,0,-4);
            $primercuent = str_replace($primercuent,'*************',$resDispositivo->cuenta);

            $primerserie = substr($resDispositivo->serie,0,-4);
            $primerserie = str_replace($primerserie,'******',$resDispositivo->serie);

            $dataVigencia = $resDispositivo->vigencia;
            date_default_timezone_set('America/Mexico_City');
            $primervig = substr(date('m-Y',$dataVigencia),0,0);
            $primervig = str_replace($primervig,'*******',date('m-Y',$dataVigencia));

            $arrayDisp = array(
                "token_dispositivos" => $resDispositivo->token_dispositivos,
                "alias" => $resDispositivo->alias,
                "tipo" => $tipo,
                "cuenta" => $primercuent,
                "serie" => $primerserie,
                "vigencia" => $primervig,
                "token_responsables" => $resDispositivo->token_responsables,
                "ocupacion" => $resDispositivo->ocupacion,
                "turno_inicio" => $resDispositivo->turno_inicio,
                "turno_fin" => $resDispositivo->turno_fin,
                "nombre_completo" => $JwtAuth->desencriptar($resDispositivo->paterno)." ".$JwtAuth->desencriptar($resDispositivo->materno)." ".
                $JwtAuth->desencriptar($resDispositivo->nombre),
                "img_perfil" => $JwtAuth->desencriptar($resDispositivo->img_perfil),
                "img" => $resDispositivo->img
            );
            
            $dipositivos[] = $arrayDisp;
        }

        return response()->json([
            'dipositivos' => $dipositivos,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function detalleDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $monedero = array();
        $caaJa = array();
        $cuentaBancaria = array();
        $arrayDetDisp = array();
        $personal = array();
        $dipositivoTipo = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que ha intantado actualizar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $verDispositivos = DispositivosModelo::join("teci_tipo_dispositivo AS tipoDisp","teci_dispositivos.tipo_dispositivo","tipoDisp.id")
                ->join("main_empresas AS emp","teci_dispositivos.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                ->where([
                    'teci_dispositivos.status' => TRUE,
                    'teci_dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token
                ])->get();

                foreach ($verDispositivos as $resDispositivos) {
                    date_default_timezone_set($resDispositivos->zona_horaria);
                    $tokenCajaDispositivo = $resDispositivos->caja != NULL ? DB::table("fnzs_catalogos_caja")->where("id",$resDispositivos->caja)->value("token_caja") : '';
                    $tokenCuentaDispositivo = $resDispositivos->cuenta != NULL ? DB::TABLE("fnzs_catalogos_cuentas")->where("id",$resDispositivos->cuenta)->value("token_cuenta") : '';
                    $tokenMonederoDispositivo = $resDispositivos->monedero != NULL ? DB::table("fnzs_catalogos_cuentas_monedero")->where("id",$resDispositivos->monedero)->value("token_cuentamonedero") : '';
                    $tokenResponsDispositivo = $resDispositivos->responsable != NULL ? DB::table("vhum_empleados_catalogo")->where("id",$resDispositivos->responsable)->value("empleado_token") : '';
                    
                    /*$qrGenerado = $JwtAuth->encriptaBase64(
                        Storage::path('public/root/'.$resDispositivos->root_tkn."/0003-tes/catalogos/devices/".
                        $JwtAuth->generarFolio($resDispositivos->folio_dispositivo)."-".
                            $resDispositivos->fecha_alta_disp.'/'.$JwtAuth->generarFolio($resDispositivos->folio_dispositivo)."-".
                            $resDispositivos->fecha_alta_disp.'-QRCode.png'));*/

                    if (file_exists(Storage::path('public/root/'.$resDispositivos->root_tkn."/0003-tes/catalogos/devices/".
                        $JwtAuth->generar($resDispositivos->folio_dispositivo)."-".$resDispositivos->fecha_alta_disp.'/'.
                        $JwtAuth->generar($resDispositivos->folio_dispositivo)."-".$resDispositivos->fecha_alta_disp.'-logotypo.png'))){
                        $logotypoGenerado = $JwtAuth->encriptaBase64(
                            Storage::path('public/root/'.$resDispositivos->root_tkn."/0003-tes/catalogos/devices/".
                            $JwtAuth->generar($resDispositivos->folio_dispositivo)."-".$resDispositivos->fecha_alta_disp.'/'.
                            $JwtAuth->generar($resDispositivos->folio_dispositivo)."-".$resDispositivos->fecha_alta_disp.'-logotypo.png'));
                    } else {
                        $logotypoGenerado = '';
                    }

                    $arrayDispositivo = array(
                      "token_dispositivos" => $resDispositivos->token_dispositivos,
                      "fecha_alta" => date('d-m-Y H:i:s',$resDispositivos->fecha_alta_disp),
                      //"rqCode" => $qrGenerado,
                      "logotypo" => $logotypoGenerado,
                      "folio_dispositivo" => "DISP-".$JwtAuth->generarFolio($resDispositivos->folio_dispositivo),
                      "tipo_dispositivo" => $resDispositivos->token_tipo_disp,
                      "alias" => $JwtAuth->desencriptar($resDispositivos->alias),
                      "serie" => $JwtAuth->desencriptar($resDispositivos->serie),
                      "vigencia" => date('Y-m',$resDispositivos->vigencia),
                      //cajas
                      "caja_token" => $tokenCajaDispositivo,
                      //cuenta Bancaria
                      "cuenta_bank_token" => $tokenCuentaDispositivo,
                      //monedero     
                      "monedero_token" => $tokenMonederoDispositivo,
                      //$personal responsable
                      "tokenResponsDispositivo" => $tokenResponsDispositivo,
                    );

                    $arrayDetDisp[] = $arrayDispositivo;
                }

                $dataMensaje = array(
                    'dispositivo' => $arrayDetDisp,
                    'code' => 200,
                    'status' => 'success'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'La información que intenta guardar es incorrecta'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function actualizaDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        /*return response()->json([
            'message' => 'error prueba',
            'codigo' => 200,
            'status' => 'error'
        ]);*/
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'tipo_dispositivo' => 'required|string',
                'alias_dispositivo' => 'required|string',
                'serie' => 'required|string',
                'token_responsable' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,people.paterno,people.materno,people.nombre,people.denominacion_rs,people.sitio_web 
                FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                WHERE emp.persona = people.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
                [$usuario->empresa_token,$usuario->user_token]);

                date_default_timezone_set($selectEmp[0]->zona_horaria);

                $token_cuentaBanc = $parametrosArray['token_cuentaBanc'];
                $cuenta_banco = !empty($token_cuentaBanc) ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuentaBanc)->value("id") : NULL;

                $token_caja = $parametrosArray['token_caja'];
                $caja = !empty($token_caja) ? DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id") : NULL;
        
                $token_monElect = $parametrosArray['token_monElect'];
                $cuenta_monedero = !empty($token_monElect) ? DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_monElect)->value("id") : NULL;

                $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token",$parametrosArray['token_responsable'])->value("id");

                if ($JwtAuth->convierteFechaEpoc($parametrosArray['vigencia']) > time()) {
                    $vigencia = $JwtAuth->convierteFechaEpoc($parametrosArray['vigencia']);
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'La vigencia del dispositivo ha vencido'
                    );
                }

                $tipo_dispositivo = $parametrosArray['tipo_dispositivo'];
                $idTipoDisp = DB::table("teci_tipo_dispositivo")->where("token_tipo_disp",$tipo_dispositivo)->value("id");

                $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","teci_dispositivos.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                ->where([
                    'teci_dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])
                ->limit(1)->update(
                    array(
                        'teci_dispositivos.alias' => $JwtAuth->encriptar($parametrosArray['alias_dispositivo']),
                        'teci_dispositivos.tipo_dispositivo' => $idTipoDisp,
                        'teci_dispositivos.cuenta' => $cuenta_banco,
                        'teci_dispositivos.caja' => $caja,
                        'teci_dispositivos.monedero' => $cuenta_monedero,
                        'teci_dispositivos.serie' => $JwtAuth->encriptar($parametrosArray['serie']),
                        'teci_dispositivos.vigencia' => $vigencia,
                        'teci_dispositivos.responsable' => $pers_responsable,   
                    )
                );

                if ($dispositivosUpdate) {
                    $dataMensaje = array(
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Dispositivo actualizado satisfactoriamente'
                    );
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Los datos del monedero electrónico no son correctos, error al intentar registrar'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 
    
    public function actualizaCajaDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'token_caja' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                if ($parametrosArray['token_caja'] != '') {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $tokenCaja = DB::select("SELECT id,alias_caja,no_caja FROM caja WHERE token_caja = ?",[$parametrosArray['token_caja']]);
                    
                    $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","=","emp.id")
                    ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
                    ->join("personal AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'dispositivos.caja' => $tokenCaja[0]->id,
                        )
                    );
                    if ($dispositivosUpdate) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Vinculación de caja con este dispositivo realizada satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Vinculación de caja con este dispositivo no fue realizada debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Caja no verificada, por favor verifique su información o comuniquese a soporte'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 

    public function unvincCajaDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'token_caja' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                if ($parametrosArray['token_caja'] != '') {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $tokenCaja = DB::select("SELECT id,alias_caja,no_caja FROM caja WHERE token_caja = ?",[$parametrosArray['token_caja']]);
                    
                    $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","=","emp.id")
                    ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
                    ->join("personal AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                        'dispositivos.caja' => $tokenCaja[0]->id,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'dispositivos.caja' => NULL,
                        )
                    );
                    if ($dispositivosUpdate) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Desvinculación de caja con este dispositivo realizada satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Desvinculación de caja con este dispositivo no fue realizada debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Caja no verificada, por favor verifique su información o comuniquese a soporte'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 
    
    public function actualizaCuentaBankDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'token_cuentaBanc' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                if ($parametrosArray['token_caja'] != '') {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $tokenCuentaBanc = DB::select("SELECT id,cuenta FROM cuenta WHERE token_cuenta = ?",[$parametrosArray['token_cuentaBanc']]);
                    
                    $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","=","emp.id")
                    ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
                    ->join("personal AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'dispositivos.cuenta' => $tokenCuentaBanc[0]->id,
                        )
                    );
                    if ($dispositivosUpdate) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Vinculación de cuenta bancaria con este dispositivo realizada satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Vinculación de cuenta bancaria con este dispositivo no fue realizada debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Cuenta bancaria no verificada, por favor verifique su información o comuniquese a soporte'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 

    public function unvincCuentaBankDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'token_cuentaBanc' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                if ($parametrosArray['token_caja'] != '') {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $tokenCuentaBanc = DB::select("SELECT id,cuenta FROM cuenta WHERE token_cuenta = ?",[$parametrosArray['token_cuentaBanc']]);
                    
                    $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","=","emp.id")
                    ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
                    ->join("personal AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                        'dispositivos.cuenta' => $tokenCuentaBanc[0]->id,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'dispositivos.cuenta' => NULL,
                        )
                    );
                    if ($dispositivosUpdate) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Desvinculación de cuenta bancaria con este dispositivo realizada satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Desvinculación de cuenta bancaria con este dispositivo no fue realizada debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Cuenta bancaria no verificada, por favor verifique su información o comuniquese a soporte'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 
    
    public function actualizaCuentaMonedDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        /*return response()->json([
            'message' => 'error prueba',
            'codigo' => 200,
            'status' => 'error'
        ]);*/
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'token_monElect' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                if ($parametrosArray['token_caja'] != '') {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $tokenCuentaMonedero = DB::select("SELECT id FROM cuenta_monedero WHERE token_cuentamonedero = ?",[$parametrosArray['token_monElect']]);
                
                    $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","=","emp.id")
                    ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
                    ->join("personal AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'dispositivos.monedero' => $tokenCuentaMonedero[0]->id
                        )
                    );
    
                    if ($dispositivosUpdate) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Vinculación de cuenta de monedero electrónico con este dispositivo realizada satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Vinculación de cuenta de monedero electrónico con este dispositivo no fue realizada debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Cuenta de monedero electrónico no verificada, por favor verifique su información o comuniquese a soporte'
                    );
                }



            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 

    public function unvincCuentaMonedDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        /*return response()->json([
            'message' => 'error prueba',
            'codigo' => 200,
            'status' => 'error'
        ]);*/
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_dispositivo' => 'required|string',
                'token_monElect' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Dispositivo invalido'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                if ($parametrosArray['token_caja'] != '') {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $tokenCuentaMonedero = DB::select("SELECT id FROM cuenta_monedero WHERE token_cuentamonedero = ?",[$parametrosArray['token_monElect']]);
                
                    $dispositivosUpdate = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","=","emp.id")
                    ->join("empresapersonal AS empuser","emp.id","=","empuser.empresa")
                    ->join("personal AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                        'dispositivos.monedero' => $tokenCuentaMonedero[0]->id,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'dispositivos.monedero' => NULL,
                        )
                    );
    
                    if ($dispositivosUpdate) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Desvinculación de cuenta de monedero electrónico con este dispositivo realizada satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Desvinculación de cuenta de monedero electrónico con este dispositivo no fue realizada debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Cuenta de monedero electrónico no verificada, por favor verifique su información o comuniquese a soporte'
                    );
                }



            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 

    public function deleteDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'token_dispositivo' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que ha intantado actualizar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $consultDisp = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empresapersonal.empresa")
                ->join("personal","empresapersonal.personal","personal.id")
                ->join("teci_usuarios_catalogo AS users","personal.usuario","users.id")
                ->where([
                    'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                    'dispositivos.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token
                ])->count();

                if ($consultDisp == 1) {
                    $updateStatusdispositivos = DB::table('dispositivos')
                    ->where(
                        [
                            'token_dispositivos' => $parametrosArray['token_dispositivo'] 
                        ]
                    )
                    ->limit(1)->update(
                        array(
                            'fecha_delete_disp' => time(),
                            'status' => FALSE
                        )
                    );

                    if ($updateStatusdispositivos) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El dispositivo se ha eliminado correctamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Error al eliminar el dispositivo, comuniquese a soporte'
                        );
                    } 
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El dispositivo que intenta eliminar no existe'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'La información que intenta guardar es incorrecta'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    } 

    public function listaDispositivosDel(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        $dipositivos = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string'
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Dispositivo invalido',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $selectDispositivo = DispositivosModelo::join("teci_tipo_dispositivo AS tip","teci_dispositivos.tipo_dispositivo","tip.id")
                ->join("main_empresas AS emp","teci_dispositivos.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                ->where([
                    'teci_dispositivos.status' => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token
                ])->get();

                foreach ($selectDispositivo as $resDispositivo) {
                    date_default_timezone_set($resDispositivo->zona_horaria);

                    $arrayDispositivos = array(
                        "token_dispositivos" => $resDispositivo->token_dispositivos,
                        "folio" => $JwtAuth->generar($resDispositivo->folio_dispositivo),
                        "alias" => $JwtAuth->desencriptar($resDispositivo->alias),
                        "tipo" => $resDispositivo->tipo,
                        "fecha_delete_disp" => date('d-m-Y H:i:s',$resDispositivo->fecha_delete_disp)
                    );

                    $dipositivos[] = $arrayDispositivos;
                }

                $dataMensaje = array(
                    'dispositivo' => $dipositivos,
                    'code' => 200,
                    'status' => 'success'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']); 
    }

    public function restaurarDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'token_dispositivo' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que ha intantado actualizar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $consultDispositivo = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empresapersonal.empresa")
                ->join("personal","empresapersonal.personal","personal.id")
                ->join("teci_usuarios_catalogo AS users","personal.usuario","users.id")
                ->where([
                    'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                    'dispositivos.status' => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token
                ])->count();

                if ($consultDispositivo == 1) {
                    $updateStatusCuenta = DB::table('dispositivos')
                    ->where(
                        [
                            'token_dispositivos' => $parametrosArray['token_dispositivo'] 
                        ]
                    )
                    ->limit(1)->delete();

                    if ($updateStatusCuenta) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El dispositivo se ha eliminado correctamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Error al eliminar el dispositivo, comuniquese a soporte'
                        );
                    } 
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El dispositivo que intenta eliminar no existe'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'La información que intenta guardar es incorrecta'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    } 

    public function deletePermanenteDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'token_dispositivo' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que ha intantado actualizar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $consultDispositivo = DispositivosModelo::join("main_empresas AS emp","dispositivos.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empresapersonal.empresa")
                ->join("personal","empresapersonal.personal","personal.id")
                ->join("teci_usuarios_catalogo AS users","personal.usuario","users.id")
                ->where([
                    'dispositivos.token_dispositivos' => $parametrosArray['token_dispositivo'],
                    'dispositivos.status' => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token
                ])->count();

                if ($consultDispositivo == 1) {
                    $updateStatusCuenta = DB::table('dispositivos')
                    ->where(
                        [
                            'token_dispositivos' => $parametrosArray['token_dispositivo'] 
                        ]
                    )
                    ->limit(1)->delete();

                    if ($updateStatusCuenta) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El dispositivo se ha eliminado correctamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Error al eliminar el dispositivo, comuniquese a soporte'
                        );
                    } 
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El dispositivo que intenta eliminar no existe'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'La información que intenta guardar es incorrecta'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

    public function registrarDispositivo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'dispositivo.tipo_dispositivo' => 'required|string',
                'dispositivo.alias_dispositivo' => 'required|string',
                'dispositivo.serie' => 'required|string',
                'dispositivo.token_responsable' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Dispositivo invalido',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,people.paterno,people.materno,people.nombre,people.denominacion_rs,people.sitio_web 
                FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.persona = people.id 
                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);

                date_default_timezone_set($selectEmp[0]->zona_horaria);

                $tokenDispositivo = $JwtAuth->encriptarToken(time(),$parametrosArray['dispositivo']['tipo_dispositivo'],
                    $parametrosArray['dispositivo']['alias_dispositivo'],$parametrosArray['dispositivo']['serie'],
                    $parametrosArray['dispositivo']['token_responsable']);

                $folioDispositivo = DB::select("SELECT IF (max(folio_dispositivo) IS NOT NULL,(max(folio_dispositivo)+1),1) AS folio FROM teci_dispositivos AS disp JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE disp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.usuario = users.id AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);

                $token_cuentaBanc = $parametrosArray['dispositivo']['token_cuentaBanc'];
                $cuenta_banco = !empty($token_cuentaBanc) ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuentaBanc)->value("id") : NULL;

                $token_caja = $parametrosArray['dispositivo']['token_caja'];
                $caja = !empty($token_caja) ? DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id") : NULL;
        
                $token_monElect = $parametrosArray['dispositivo']['token_monElect'];
                $cuenta_monedero = !empty($token_monElect) ? DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_monElect)->value("id") : NULL;

                $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token",$parametrosArray['dispositivo']['token_responsable'])->value("id");

                if ($JwtAuth->convierteFechaEpoc($parametrosArray['dispositivo']['vigencia']) > time()) {
                    $vigencia = $JwtAuth->convierteFechaEpoc($parametrosArray['dispositivo']['vigencia']);
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'La vigencia del dispositivo ha vencido'
                    );
                }

                $tipo_dispositivo = $parametrosArray['dispositivo']['tipo_dispositivo'];
                $idTipoDisp = DB::table("teci_tipo_dispositivo")->where("token_tipo_disp",$tipo_dispositivo)->value("id");
                
                $newDispositivo = new DispositivosModelo();	
                $newDispositivo->token_dispositivos = $tokenDispositivo;
                $newDispositivo->folio_dispositivo = $folioDispositivo[0]->folio;
                $newDispositivo->fecha_alta_disp = time();
                $newDispositivo->alias = $JwtAuth->encriptar($parametrosArray['dispositivo']['alias_dispositivo']);
                $newDispositivo->tipo_dispositivo = $idTipoDisp;
                $newDispositivo->cuenta = $cuenta_banco;
                $newDispositivo->caja = $caja;
                $newDispositivo->monedero = $cuenta_monedero;
                $newDispositivo->serie = $JwtAuth->encriptar($parametrosArray['dispositivo']['serie']);
                $newDispositivo->vigencia = $vigencia;
                $newDispositivo->fecha_delete_disp = '';
                $newDispositivo->status = TRUE;
                $newDispositivo->responsable = $pers_responsable;   
                $newDispositivo->empresa = $selectEmp[0]->id;
                $savedDispositivo = $newDispositivo->save();

                if ($savedDispositivo) {
                    $dataMensaje = array(
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Dispositivo registrado satisfactoriamente'
                    );
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Los datos del monedero electrónico no son correctos, error al intentar registrar'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los datos no son correctos'
            ); 
        }

        return response()->json($dataMensaje,$dataMensaje['code']);        
    } 

    public function registrar_dispositivo(Request $request){
      $JwtAuth = new \JwtAuth();
      $jsonUser = $request->input('json');
      $parametros = json_decode($jsonUser);
      $parametrosArray = json_decode($jsonUser,true);

      if (!empty($parametros) && !empty($parametrosArray)) {
          $validate = \Validator::make($parametrosArray,[
              'user_token' => 'required|string',
              'dispositivo.tipo_dispositivo' => 'required|string',
              'dispositivo.alias_dispositivo' => 'required|string',
              'dispositivo.serie' => 'required|string',
              'dispositivo.token_responsable' => 'required|string',
          ]);

          if ($validate->fails()) {
              $dataMensaje = array(
                  'status' => 'error',
                  'code' => 404,
                  'message' => 'Dispositivo invalido',
                  'errors' => $validate->errors()
              );
          } else {
              $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

              $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,people.paterno,people.materno,people.nombre,people.denominacion_rs,people.sitio_web 
              FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.persona = people.id 
              AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);

              date_default_timezone_set($selectEmp[0]->zona_horaria);

              $tokenDispositivo = $JwtAuth->encriptarToken(time(),$parametrosArray['dispositivo']['tipo_dispositivo'],
                  $parametrosArray['dispositivo']['alias_dispositivo'],$parametrosArray['dispositivo']['serie'],
                  $parametrosArray['dispositivo']['token_responsable']);

              $folioDispositivo = DB::select("SELECT IF (max(folio_dispositivo) IS NOT NULL,(max(folio_dispositivo)+1),1) AS folio FROM teci_dispositivos AS disp JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE disp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
              AND empuser.usuario = users.id AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);

              $token_cuentaBanc = $parametrosArray['dispositivo']['token_cuentaBanc'];
              $cuenta_banco = !empty($token_cuentaBanc) ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuentaBanc)->value("id") : NULL;

              $token_caja = $parametrosArray['dispositivo']['token_caja'];
              $caja = !empty($token_caja) ? DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id") : NULL;
      
              $token_monElect = $parametrosArray['dispositivo']['token_monElect'];
              $cuenta_monedero = !empty($token_monElect) ? DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_monElect)->value("id") : NULL;

              $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token",$parametrosArray['dispositivo']['token_responsable'])->value("id");

              if ($JwtAuth->convierteFechaEpoc($parametrosArray['dispositivo']['vigencia']) > time()) {
                  $vigencia = $JwtAuth->convierteFechaEpoc($parametrosArray['dispositivo']['vigencia']);
              } else {
                  $dataMensaje = array(
                      'status' => 'error',
                      'code' => 400,
                      'message' => 'La vigencia del dispositivo ha vencido'
                  );
              }

              $tipo_dispositivo = $parametrosArray['dispositivo']['tipo_dispositivo'];
              $idTipoDisp = DB::table("teci_tipo_dispositivo")->where("token_tipo_disp",$tipo_dispositivo)->value("id");
              
              $newDispositivo = new DispositivosModelo();	
              $newDispositivo->token_dispositivos = $tokenDispositivo;
              $newDispositivo->folio_dispositivo = $folioDispositivo[0]->folio;
              $newDispositivo->fecha_alta_disp = time();
              $newDispositivo->alias = $JwtAuth->encriptar($parametrosArray['dispositivo']['alias_dispositivo']);
              $newDispositivo->tipo_dispositivo = $idTipoDisp;
              $newDispositivo->cuenta = $cuenta_banco;
              $newDispositivo->caja = $caja;
              $newDispositivo->monedero = $cuenta_monedero;
              $newDispositivo->serie = $JwtAuth->encriptar($parametrosArray['dispositivo']['serie']);
              $newDispositivo->vigencia = $vigencia;
              $newDispositivo->fecha_delete_disp = '';
              $newDispositivo->status = TRUE;
              $newDispositivo->responsable = $pers_responsable;   
              $newDispositivo->empresa = $selectEmp[0]->id;
              $savedDispositivo = $newDispositivo->save();

              if ($savedDispositivo) {
                  $fecha_registro = DB::select("SELECT fecha_alta_disp FROM teci_dispositivos WHERE token_dispositivos = ?",
                      [$tokenDispositivo]);
                  $filepath = $selectEmp[0]->root_tkn."/0003-tes/catalogos/devices/".
                      $JwtAuth->generar($folioDispositivo[0]->folio)."-".$fecha_registro[0]->fecha_alta_disp."/";// or image.jpg
                   if (!file_exists(storage_path("/root/".$filepath))){
                      //Storage::disk('public')->makeDirectory('/storage/root/'.$filepath,0777, true, true);
                      Storage::disk('root')->makeDirectory($filepath,0777, true, true); 
                   }    
              
                  QRCode::text($tokenDispositivo)
                  ->setOutfile(Storage::path('public/root/'.$filepath.$JwtAuth->generar($folioDispositivo[0]->folio)."-".
                      $fecha_registro[0]->fecha_alta_disp.'-QRCode.png'))
                  ->png();
                  
                  $qrGenerado = $JwtAuth->encriptaBase64(
                      Storage::path('public/root/'.$filepath.$JwtAuth->generar($folioDispositivo[0]->folio)."-".
                          $fecha_registro[0]->fecha_alta_disp.'-QRCode.png'));
                  
                  $areaCss = 'information-tes';
                  $areaPdf = 'Tesorería';
                  $Subarea = 'Catalogos de tesorería';
                  $nameDoc = 'evidencia de regitro de dispositivos electrónicos';
                  $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/homePagePrincipal/sos-mexico.png'));
                  if ($selectEmp[0]->denominacion_rs == ''){
                      $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->paterno)." ".
                          $JwtAuth->desencriptar($selectEmp[0]->materno)." ".
                          $JwtAuth->desencriptar($selectEmp[0]->nombre);
                  } else {
                      $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->denominacion_rs);
                  }
                  if ($selectEmp[0]->sitio_web == '' || $selectEmp[0]->sitio_web == '-'){
                      $sitio_web = '---';
                  } else {
                      $sitio_web = $JwtAuth->desencriptar($selectEmp[0]->sitio_web);
                  }
                  $direccion = '';
                  $fecha_pdf = date('d-m-Y H:i:s',time());
                  
                  if ($idPersonal[0]->denominacion_rs == ''){
                      $namePersonal = $JwtAuth->desencriptar($idPersonal[0]->paterno)." ".
                          $JwtAuth->desencriptar($idPersonal[0]->materno)." ".
                          $JwtAuth->desencriptar($idPersonal[0]->nombre);
                  } else {
                      $namePersonal = $JwtAuth->desencriptar($idPersonal[0]->denominacion_rs);
                  }
                  
                  $contenidoPdf = '<div class="divLogo">
                          <img src="'.$qrGenerado.'" alt="">
                      </div>
                      <h3>Información general del dipositivo</h3>
                      <table class="contenido" width="100%">
                          <thead>
                              <tr>
                                  <th>Folio</th>
                                  <th>Alias</th>
                                  <th>Tipo</th>
                                  <th>No. serie</th>
                                  <th>Vigencia</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr>
                                  <td>'.$JwtAuth->generar($folioDispositivo[0]->folio).'</td>
                                  <td>'.$parametrosArray['dispositivo']['alias_dispositivo'].'</td>
                                  <td>'.$idTipoDisp[0]->tipo.'</td>
                                  <td>'.$parametrosArray['dispositivo']['serie'].'</td>
                                  <td>'.$parametrosArray['dispositivo']['vigencia'].'</td>
                              </tr>
                          </tbody>
                      </table>
                      <br>
                      <h3>Cuentas bancarias vinculadas</h3>
                      <table>
                          <thead>
                              <tr>
                                  <th>Cuenta</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr>
                                  <td>'.$JwtAuth->desencriptar($tokenCuentaBanc[0]->cuenta).'</td>
                              </tr>
                          </tbody>
                      </table>
                      <h3>Caja vinculada</h3>
                      <table>
                          <thead>
                              <tr>
                                  <th>No. caja</th>
                                  <th>alias</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr>
                                  <td>'.$tokenCaja[0]->no_caja.'</td>
                                  <td>'.$JwtAuth->desencriptar($tokenCaja[0]->alias_caja).'</td>
                              </tr>
                          </tbody>
                      </table>
                      <h3>Monederos electrónicos vinculados</h3>
                      <table>
                          <thead>
                              <tr>
                                  <th>Cuenta de monedero electrónico</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr>
                                  <td>'.$JwtAuth->desencriptar($tokenCuentaMonedero[0]->cuenta).'</td>
                              </tr>
                          </tbody>
                      </table>
                      <h3>Personal vinculado</h3>
                      <table>
                          <thead>
                              <tr>
                                  <th>Personal responsable</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr>
                                  <td>'.$namePersonal.'</td>
                              </tr>
                          </tbody>
                      </table>';
                  
                  
                  $pdfGenerado = $JwtAuth->generaPdf($areaCss,$areaPdf,$Subarea,$nameDoc,
                      $logoEmp,$nameEmp,$sitio_web,$direccion,$fecha_pdf,$contenidoPdf);
                  
                  $dompdf = \PDF::loadHtml($pdfGenerado);
                  $dompdf->setPaper("A2", "portrait");
                  $contenidoPDF = $dompdf->output();
                  
                  file_put_contents(storage_path("app/public/root/".$filepath).$JwtAuth->generar($folioDispositivo[0]->folio)."-".
                      $fecha_registro[0]->fecha_alta_disp.".pdf", $contenidoPDF);
                  
                  $dataMensaje = array(
                      'status' => 'success',
                      'code' => 200,
                      'message' => 'Dispositivo registrado satisfactoriamente'
                  );
              } else {
                  $dataMensaje = array(
                      'status' => 'error',
                      'code' => 400,
                      'message' => 'Los datos del monedero electrónico no son correctos, error al intentar registrar'
                  );
              }
          }
      } else {
          $dataMensaje = array(
              'status' => 'error',
              'code' => 404,
              'message' => 'Los datos no son correctos'
          ); 
      }

      return response()->json($dataMensaje,$dataMensaje['code']);        
  } 
}