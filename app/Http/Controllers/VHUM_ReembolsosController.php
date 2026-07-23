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
use App\Models\ReembolsoModelo;
use App\Models\OrdenPagoModelo;
use App\Models\CajaModelo;
use App\Models\CuentBancModelo;
use App\Models\CuentaMonederoModelo;

class VHUM_ReembolsosController extends Controller{
    public function reembolso_lista(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $reembolsos_lista_general = array();
        $reembolsos_lista_pendientes = array();
        $reembolsos_lista_concluidos = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                
                if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                    $list_reembolso = DB::table("terc_reembolso_main AS reem_main")
                    ->join("sos_reembolsos_comisiones_rel AS reem_comi","reem_main.id","=","reem_comi.reembolso_main")
                    ->join("terc_comisiones_main AS comi_soli","reem_comi.comision","=","comi_soli.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.last_version","=","reem_soli.id")
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->where("reem_main.user_receptor_vh","!=",NULL)   
                    ->where([
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                    ])                
                    ->orderBy('reem_main.folio_reem','DESC')->get();
                } else {
                    $list_reembolso = DB::table("terc_reembolso_main AS reem_main")
                    ->join("sos_reembolsos_comisiones_rel AS reem_comi","reem_main.id","=","reem_comi.reembolso_main")
                    ->join("terc_comisiones_main AS comi_soli","reem_comi.comision","=","comi_soli.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.last_version","=","reem_soli.id")
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("vhum_personal AS pers","reem_main.user_receptor_vh","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                        "users.user_token" => $usuario->user_token
                    ])                
                    ->orderBy('reem_main.folio_reem','DESC')->get();
                }
                
                foreach ($list_reembolso as $vremb) {
                    //da_te_default_timezone_set($vremb->zona_horaria);
                    $fecha_solicitud = $vremb->fecha_sistema;
                    $date_solicitud = date('d-m-Y H:i:s',$vremb->fecha_sistema);
                    
                    $fecha_respuesta_autorizacion = date('d-m-Y H:i:s',$vremb->tiempo_respuesta_autorizacion);
                    $time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
                    $days_autorizacion = floor($time_inicial_autorizacion / (60*60*24));
                    $time_inicial_autorizacion %= (60 * 60 * 24);
                    $hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
                    $time_inicial_autorizacion %= (60 * 60);
                    $min_autorizacion = floor($time_inicial_autorizacion / 60);
                    $time_inicial_autorizacion %= 60;
                    $sec_autorizacion = $time_inicial_autorizacion;
                    $time_respuesta_autorizacion = $days_autorizacion." días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion";//
                    
                    $iva_final = 0;
                    $importe_final = 0;
                    
                    if ($vremb->post_folio_reem == NULL) {
                        $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem);
                    } else {
                        $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).'-'.$vremb->post_folio_reem;
                    }
                    
                    $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                    ->join("main_empresas AS emp","reem_main.emisor","=","emp.id")
                    ->join("sos_personas AS people","emp.persona","=","people.id")
                    ->where(["reem_main.token_reem" => $vremb->token_reem])->get();
                    
                    foreach ($selectNameEmpEmi as $vEmisor) {
                        $name_emisor = $vEmisor->abrev_nombre;
                        $rfc_gen_emi = $vEmisor->rfc_generico;
			            $rfc_emp_emi = "---";
			            $taxid_emp_emi = "---";
			            if ($vEmisor->rfc != NULL) $rfc_emp_emi = $JwtAuth->desencriptar($vEmisor->rfc);
                        if ($vEmisor->tax_id != NULL) $taxid_emp_emi = $JwtAuth->desencriptar($vEmisor->tax_id);
                    }
            
                    $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                    ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                    ->join("sos_personas AS people","pers.personal","=","people.id")
                    ->where(["reem_main.token_reem" => $vremb->token_reem])->get();
                    
                    foreach ($selectPersEmpEmi as $vPemi) {
                        $nombreEmiPers = $JwtAuth->desencriptarNombres($vPemi->paterno,$vPemi->materno,$vPemi->nombre);
                    }
                    
                    $soli_reem = DB::table("terc_reembolso_main AS reem_main")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                    ->join("teci_forma_pago AS fpago","reem_soli.forma_pago","=","fpago.id")
                    ->where(["reem_main.token_reem" => $vremb->token_reem])
                    ->orderBy('reem_soli.folio_solicitud','DESC')->get();
                    
                    $reem_total = 0;
                    $total_tipo_cambio = 0;
                    $moneda_entrante_string = "";
                    $moneda_entrante_decimales = 0;
                    $moneda_saliente_string = "";
                    $moneda_saliente_decimales = 0;
                    $total_reem_saliente = 0;
                    
                    $reem_soli_all = 0;
                    $reem_soli_all_auth = 0;
                    $reem_soli_auth_style = "";
                    foreach ($soli_reem as $vSoliR) {
                        $reem_total = $reem_total+$vSoliR->importe_entrante;
                        
                        $soli_mon_entrante = DB::table("teci_catalogo_monedas AS mon_in")                         
                        ->join("terc_reembolso_solicitud AS reem_soli","mon_in.id","=","reem_soli.moneda_entrante")
                        ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
                        foreach ($soli_mon_entrante as $mon_in) {
                            //$moneda_entrante_string = $mon_in->codigo." ".$mon_in->moneda;
                            $moneda_entrante_string = $mon_in->codigo;
                            $moneda_entrante_decimales = $mon_in->decimales;
                        }
                        
                        $total_tipo_cambio = $vSoliR->tipo_cambio;
                        $resultante = $vSoliR->importe_entrante * $vSoliR->tipo_cambio; 
                        $total_reem_saliente = $total_reem_saliente+$resultante;
                        
                        $soli_mon_saliente = DB::table("teci_catalogo_monedas AS mon_out")                         
                        ->join("terc_reembolso_solicitud AS reem_soli","mon_out.id","=","reem_soli.moneda_saliente")
                        ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
                        foreach ($soli_mon_saliente as $mon_out) {
                            //$moneda_saliente_string = $mon_out->codigo." ".$mon_out->moneda;
                            $moneda_saliente_string = $mon_out->codigo;
                            $moneda_saliente_decimales = $mon_out->decimales;
                        }
                        
                        ++$reem_soli_all;
                        if ($vSoliR->autorizacion_vh == "A") {
                            ++$reem_soli_all_auth; 
                        }
                    }
                    
                    if ($reem_soli_all_auth != 0) {
                        //$reem_soli_auth_style = 100 * ($reem_soli_all/$reem_soli_all_auth);
                        $reem_soli_auth_style = (100 * $reem_soli_all_auth)/$reem_soli_all;
                    } else {
                        $reem_soli_auth_style = 0; 
                    }
                    
                    $reem_evd = DB::table("sos_documentos AS docs")
                    ->join("terc_reembolso_main AS reem_main","docs.reembolso_main","=","reem_main.id")
                    ->where(["reem_main.token_reem" => $vremb->token_reem,"docs.status_documento" => TRUE])->get();
                    
                    $row_main = array(
                        "token_reem" => $vremb->token_reem,	
                        "folio_reem" => $folio_reem,
                        "fecha_solicitud" => $fecha_solicitud,
                        "date_solicitud" => $date_solicitud,
                        "fecha_respuesta_autorizacion_vhegr" => $fecha_respuesta_autorizacion,
                        "time_respuesta_autorizacion_vhegr" => $time_respuesta_autorizacion,
                        "name_emisor" => $name_emisor,
                        "rfc_gen_emi" => $rfc_gen_emi,
			            "rfc_emp_emi" => $rfc_emp_emi,
                        "taxid_emp_emi" => $taxid_emp_emi,
                        "nombreEmiPers" => $nombreEmiPers,
                        "reem_soli_all" => $reem_soli_all,
                        "reem_soli_all_auth" => $reem_soli_all_auth,
                        "reem_soli_auth_style" => $reem_soli_auth_style."%",
                        "importe_total" => "$".number_format($reem_total,$moneda_entrante_decimales,'.', ','),
                        "moneda_entrante" => $moneda_entrante_string,
                        "moneda_entrante_decimales" => $moneda_entrante_decimales,
                        "total_tipo_cambio" => "$".$total_tipo_cambio,
                        "total_reem_saliente" => "$".number_format($total_reem_saliente,$moneda_saliente_decimales,'.', ','),
                        "moneda_saliente" => $moneda_saliente_string,
                        "comision_folio" => "COMI-".$JwtAuth->generarFolio($vremb->folio_comision),
                        "comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
                        "total_evd" => count($reem_evd),
                    );
                    $reembolsos_lista_general[] = $row_main;
                    
                    if ($reem_soli_all_auth == $reem_soli_all) {
                       $reembolsos_lista_concluidos[] = $row_main;
                    } else {
                       $reembolsos_lista_pendientes[] = $row_main;
                    }
                }
                $dataMensaje = array("status" => "success","code" => 200,"reem_lista_general" => $reembolsos_lista_general,"reem_lista_pend" => $reembolsos_lista_pendientes,"reem_lista_conc" => $reembolsos_lista_concluidos);
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
    
    public function reembolso_detalle(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayReem = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "token_reem" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                $token_reem = $parametrosArray["token_reem"];
                
                if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                    $reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
                    ->join("sos_reembolsos_comisiones_rel AS reem_comi","reem_main.id","=","reem_comi.reembolso_main")
                    ->join("terc_comisiones_main AS comi_soli","reem_comi.comision","=","comi_soli.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.last_version","=","reem_soli.id")
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->where([
                        "reem_main.token_reem" => $token_reem,
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                    ])                
                    ->orderBy('reem_main.folio_reem','DESC')->get();
                } else {
                    $reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
                    ->join("sos_reembolsos_comisiones_rel AS reem_comi","reem_main.id","=","reem_comi.reembolso_main")
                    ->join("terc_comisiones_main AS comi_soli","reem_comi.comision","=","comi_soli.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.last_version","=","reem_soli.id")
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("vhum_personal AS pers","reem_main.user_receptor_vh","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "reem_main.token_reem" => $token_reem,
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                        "users.user_token" => $usuario->user_token
                    ])                
                    ->orderBy('reem_main.folio_reem','DESC')->get();
                }
                
                foreach ($reembolso_main_selected as $vremb) {
                    $root_main_emisor = "";
                    //da_te_default_timezone_set($vremb->zona_horaria);
                    $fecha_solicitud = date('d-m-Y H:i:s',$vremb->fecha_sistema);
                    $token_reem = $vremb->token_reem;
                    
                    if ($vremb->post_folio_reem == NULL) {
                        $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem);
                    } else {
                        $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).'-'.$vremb->post_folio_reem;
                    }
                    
                    //emisor
                        $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                        ->join("main_empresas AS emp","reem_main.emisor","=","emp.id")
                        ->join("sos_personas AS people","emp.persona","=","people.id")
                        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();
                
                        foreach ($selectNameEmpEmi as $vEmisor) {
                            $root_main_emisor = $vEmisor->root_tkn;
                            $name_emisor = $vEmisor->abrev_nombre;
                            $rfc_gen_emi = $vEmisor->rfc_generico;
                            $rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
                            $taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
                        }
                    
                        $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                        ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                        ->join("sos_personas AS people","pers.personal","=","people.id")
                        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();
                        
                        foreach ($selectPersEmpEmi as $vPemi) {
                            $name_pers_emisor = $JwtAuth->desencriptarNombres($vPemi->paterno,$vPemi->materno,$vPemi->nombre);
                        }
                    
                    //receptor 
                        $selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
                        ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                        ->join("sos_personas AS people","emp.persona","=","people.id")
                        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();
                        
                        $txt_folio_solicitud = "0";
                
                        foreach ($selectNameEmpRec as $vReceptor) {
                            $tkn_receptor = $vReceptor->emp_token;
                            $name_receptor = $vReceptor->abrev_nombre;
                            $rfc_gen_receptor = $vReceptor->rfc_generico;
                            $rfc_emp_receptor = $vReceptor->rfc != NULL ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
                            $taxid_emp_receptor = $vReceptor->tax_id != NULL ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
                        }
                    
                        $selectPersEmpReceptor = DB::table("terc_reembolso_main AS reem_main")
                        ->join("vhum_personal AS pers","reem_main.user_receptor_vh","=","pers.id")
                        ->join("sos_personas AS people","pers.personal","=","people.id")
                        ->where(["reem_main.token_reem" => $vremb->token_reem])->get();
                        
                        foreach ($selectPersEmpReceptor as $vPrec) {
                            $name_pers_receptor = $JwtAuth->desencriptarNombres($vPrec->paterno,$vPrec->materno,$vPrec->nombre);
                        }
                        
                    $arraySoliReem = array();
                    $soli_reem = DB::table("terc_reembolso_main AS reem_main")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                    ->join("teci_forma_pago AS fpago","reem_soli.forma_pago","=","fpago.id")
                    ->where(["reem_main.token_reem" => $token_reem])
                    ->orderBy('reem_soli.folio_solicitud','DESC')->get();
                    
                    $importe_total = 0;
                    $importe_total_conversion = 0;
                    $total_reembolsado = 0;
                    $total_reembolsado_conversion = 0;
                    
                    $total_tipo_cambio = 0;
                    $moneda_entrante_string = "";
                    $moneda_entrante_string_min = "";
                    $moneda_entrante_decimales = 0;
                    
                    $moneda_saliente_string = "";
                    $moneda_saliente_string_min = "";
                    $moneda_saliente_decimales = 0;
                    $total_reem_saliente = 0;
                    $num_posicion = 0;
                    
                    foreach ($soli_reem as $vSoliR) {
                        $tkn_prov = "";
                        $name_prov = "";
                        $rfc_generico_prov = "";
                        $rfc_prov = "";
                        $taxid_prov = "";
                        if ($vSoliR->pagado_a == "prov" && $vSoliR->proveedor != NULL) {
                            $soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
                            ->join("terc_reembolso_main AS rmain","reem_soli.reembolso_main","=","rmain.id")
                            ->join("eegr_catalogo_proveedores AS cprov","reem_soli.proveedor","=","cprov.id")
                            ->join("sos_personas AS prov","cprov.proveedor","=","prov.id")
                            ->join("teci_forma_pago AS fpago","reem_soli.forma_pago","=","fpago.id")
                            ->where([
                                "reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
                                "rmain.token_reem" => $token_reem
                            ])->get();
                            
                            foreach ($soli_r_prov as $sr_prov) {
                                $tkn_prov = $sr_prov->token_cat_proveedores;
                                $den_rs_prv = $sr_prov->denominacion_rs;
                                $name_prov = $den_rs_prv != '' ? $JwtAuth->desencriptar($den_rs_prv) : $JwtAuth->desencriptarNombres($sr_prov->paterno,$sr_prov->materno,$sr_prov->nombre);
                                $rfc_generico_prov = $sr_prov->rfc_generico;
                                $rfc_prov = $sr_prov->rfc != NULL ? $JwtAuth->desencriptar($sr_prov->rfc) : "---";
                                $taxid_prov = $sr_prov->tax_id != NULL ? $JwtAuth->desencriptar($sr_prov->tax_id) : "---";
                            }
                        }
                        
                        $soli_mon_entrante = DB::table("teci_catalogo_monedas AS mon_in")                         
                        ->join("terc_reembolso_solicitud AS reem_soli","mon_in.id","=","reem_soli.moneda_entrante")
                        ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
                        foreach ($soli_mon_entrante as $mon_in) {
                            $moneda_entrante_string = $mon_in->codigo." ".$mon_in->moneda;
                            $moneda_entrante_string_min = $mon_in->codigo;
                            $moneda_entrante_decimales = $mon_in->decimales;
                        }
                        
                        //importe
                        $importe_total = $importe_total+$vSoliR->importe_entrante;
                        $importe_total_conversion = $importe_total_conversion+($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
                        if (($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") && $vSoliR->autorizacion_egr == "A" && $vSoliR->terminado == TRUE) {
                            $total_reembolsado = $total_reembolsado+$vSoliR->importe_entrante;
                            $total_reembolsado_conversion = $total_reembolsado_conversion+($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
                        }
                        
                        $soli_mon_saliente = DB::table("teci_catalogo_monedas AS mon_out")                         
                        ->join("terc_reembolso_solicitud AS reem_soli","mon_out.id","=","reem_soli.moneda_saliente")
                        ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
                        foreach ($soli_mon_saliente as $mon_out) {
                            $moneda_saliente_string = $mon_out->codigo." ".$mon_out->moneda;
                            $moneda_saliente_string_min = $mon_out->codigo;
                            $moneda_saliente_decimales = $mon_out->decimales;
                        }
                        
                        $importe_requ_info_entr = "$".number_format($vSoliR->importe_entrante,$moneda_entrante_decimales,'.', ',')." ".$moneda_entrante_string_min;
                        $importe_requ_info_sali = "$".number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio,$moneda_saliente_decimales,'.', ',')." ".$moneda_saliente_string_min;  
                            
                        $autorizacion_vh = null;
                        if ($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") $autorizacion_vh = true;
                        if ($vSoliR->autorizacion_vh == "D") $autorizacion_vh = false;
                        
                        $select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios 
                            FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                            AND s_soli.token_solicitud_reem = ?",[$token_reem,$vSoliR->token_solicitud_reem]);
                            
                        $max_auth_vh = null;
                        $fecha_registro_auth_vh = "";
                        $hora_registro_auth_vh = "";
                        $comments_auth_vh = "";
                        $auth_vh_list_array = array();
                            
                        if (count($select_list_auth_vh) > 0) {
                            foreach ($select_list_auth_vh as $l_auth) {
                                $row_auth_vh = array(
                                    "autorizacion_vh" => $l_auth->autorizacion_vh,
                                    "registro_auth_vh" => date('d-m-Y - H:i:s',$l_auth->fecha_registro),
                                    "comentarios" => $JwtAuth->desencriptar($l_auth->comentarios)
                                );
                                $auth_vh_list_array[] = $row_auth_vh;
                            }
                            if (end($select_list_auth_vh)->autorizacion_vh == "A" || end($select_list_auth_vh)->autorizacion_vh == "N") $max_auth_vh = true;
                            if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
                            $fecha_registro_auth_vh = gmdate('Y-m-d H:i:s',end($select_list_auth_vh)->fecha_registro);
                            $hora_registro_auth_vh = date('H:i:s',end($select_list_auth_vh)->fecha_registro);
                            $comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
                        }
                        
                        $autorizacion_egr = null;
                        if ($vSoliR->autorizacion_egr == "A") $autorizacion_egr = true;
                        if ($vSoliR->autorizacion_egr == "D") $autorizacion_egr = false;
                        
                        $select_list_auth_egr = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_egr,r_auth.comentarios 
                            FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                            AND s_soli.token_solicitud_reem = ?",[$token_reem,$vSoliR->token_solicitud_reem]);  
                            
                        $max_auth_egr = null;
                        $fecha_registro_auth_egr = "";
                        $hora_registro_auth_egr = "";
                        $comments_auth_egr = "";    
                        if (count($select_list_auth_egr) > 0) {
                            if (end($select_list_auth_egr)->autorizacion_egr == "A") $max_auth_egr = true;
                            if (end($select_list_auth_egr)->autorizacion_egr == "D") $max_auth_egr = false;
                            $fecha_registro_auth_egr = gmdate('Y-m-d H:i:s',end($select_list_auth_egr)->fecha_registro);
                            $hora_registro_auth_egr = date('H:i:s',end($select_list_auth_egr)->fecha_registro);
                            $comments_auth_egr = $JwtAuth->desencriptar(end($select_list_auth_egr)->comentarios);
                        }  
                        
                        $terminado = false;
                        if ($vSoliR->terminado == TRUE) $terminado = true;
                            
                        $fecha_respuesta_autorizacion = date('d-m-Y H:i:s',$vremb->tiempo_respuesta_autorizacion);
                        $time_respuesta_autorizacion = "";
                        if ($vSoliR->tiempo_respuesta_autorizacion > time()) {
                            $time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
                            $days_autorizacion = floor($time_inicial_autorizacion / (60*60*24));
                            $time_inicial_autorizacion %= (60 * 60 * 24);
                            $hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
                            $time_inicial_autorizacion %= (60 * 60);
                            $min_autorizacion = floor($time_inicial_autorizacion / 60);
                            $time_inicial_autorizacion %= 60;
                            $sec_autorizacion = $time_inicial_autorizacion;
                            $time_respuesta_autorizacion = $days_autorizacion." días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion";// 
                        } else {
                            $time_respuesta_autorizacion = "tiempo de respuesta terminado";
                        }    
                            
                        $listAnexosSoli = array();
                        $selectAnexosReem = DB::table("sos_documentos AS docs")
                        ->join("terc_reembolso_main AS main","docs.reembolso_main","=","main.id")
                        ->join("terc_reembolso_solicitud AS reem_soli","docs.reembolso_solicitud","=","reem_soli.id")
                        ->where([
                            "docs.status_documento" => TRUE,
                            "docs.tipo_documento" => "an",
                            "main.token_reem" => $token_reem,
                            "reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
                        ])->get();
                        
                        foreach ($selectAnexosReem as $vDoc) {
                            $token_docs = $vDoc->token_documento;
                            $tipo_doc = $vDoc->tipo_documento;
                            $ext_doc = $vDoc->extension_documento;
                            //$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);	
                            
                            $filepath_old = $root_main_emisor."/0010-reem/".$folio_reem."/anexos";
                            $filepath_new = $root_main_emisor."/0010-reem/".$folio_reem."/".$JwtAuth->generarFolio($vSoliR->folio_solicitud)."/anexos";
                            $archivo_old = Storage::path('public/root/'.$filepath_old.'/'.$JwtAuth->desencriptar($vDoc->nombre_documento));
                            $archivo_new = Storage::path('public/root/'.$filepath_new.'/'.$JwtAuth->desencriptar($vDoc->nombre_documento));
                            
                            if (file_exists($archivo_old)) {
                                $extension = pathinfo($archivo_old, PATHINFO_EXTENSION);
                                $name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
                                if ($extension == 'pdf' || $extension == 'PDF') {
                                    $base64 = $JwtAuth->encriptaBase64Pdf($archivo_old);
                                    $html = '<iframe src="'.$base64.'" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
                                }
                                
                                if ($extension == 'xml') {
                                    $base64 = $JwtAuth->encriptaBase64Pdf($archivo_old);
                                    $html = file_get_contents($archivo_old);
                                }
                    
                                if ($extension == 'jpg' || $extension == 'png') {
                                    $base64 = $JwtAuth->encriptaBase64($archivo_old);
                                    $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="'.$base64.'">';
                                }
                            } else if (file_exists($archivo_new)) {
                                $extension = pathinfo($archivo_new, PATHINFO_EXTENSION);
                                $name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
                                if ($extension == 'pdf' || $extension == 'PDF') {
                                    $base64 = $JwtAuth->encriptaBase64Pdf($archivo_new);
                                    $html = '<iframe src="'.$base64.'" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
                                }
                                
                                if ($extension == 'xml') {
                                    $base64 = $JwtAuth->encriptaBase64Pdf($archivo_new);
                                    $html = file_get_contents($archivo_new);
                                }
                    
                                if ($extension == 'jpg' || $extension == 'png') {
                                    $base64 = $JwtAuth->encriptaBase64($archivo_new);
                                    $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="'.$base64.'">';
                                }
                            } else {
                                $name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento)." (inexistente)";
                                $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
                                $extension = pathinfo($archivo, PATHINFO_EXTENSION);
                                $base64 = $JwtAuth->encriptaBase64($archivo);
                                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="'.$base64.'">';
                            }
                
                            $rowDet = array(
                                "token_docs" => $token_docs,
                                "ext_doc" => $extension,
                                "name_documento" => $name_documento,	
                                "html" => $html,
                            );
                            $listAnexosSoli[] = $rowDet;
                        }    
                            
                        $row_soli = array(
                            "posicion" => $num_posicion,
                            "token_solicitud_reem" => $vSoliR->token_solicitud_reem,
                            "folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
                            "fecha_solicitud" => date('d-m-Y H:i:s',$vSoliR->fecha_solicitud),
                            "fecha_gasto" => date('d-m-Y H:i:s',$vSoliR->fecha_gasto),
                            "fecha_gasto_html" => $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria,$vSoliR->fecha_gasto),	
                            "ticket_gasto" => $JwtAuth->desencriptar($vSoliR->ticket_gasto),
                            "pagado_a" => $vSoliR->pagado_a,
                            //proveedor
                            "tkn_prov" => $tkn_prov,
                            "proveedor" => $name_prov,
                            "rfc_generico_prov" => $rfc_generico_prov,
                            "rfc_prov" => $rfc_prov,
                            "taxid_prov" => $taxid_prov,
                            //forma de pago
                            "fpago_token" => $vSoliR->token_formapago,
                            "fpago_clave" => $vSoliR->clave,
                            "fpago_forma" => $vSoliR->forma,
                            //importe
                            "importe_requerido" => floatval($vSoliR->importe_entrante),
                            //"importe_requerido_info" => $importe_requerido_info,
                            
                            "importe_requ_info_entr" => $importe_requ_info_entr,
                            "importe_requ_info_sali" => $importe_requ_info_sali,
                            
                            "tipo_cambio_soli" => $vSoliR->tipo_cambio,
                            //observaciones
                            "observaciones" => $JwtAuth->desencriptar($vSoliR->motivo_reem),
                            
                            "autorizacion_vh" => $autorizacion_vh,
                            "max_auth_vh" => $max_auth_vh,
                            "comments_auth_vh" => $comments_auth_vh,
                            "comments_auth_vh_write" => "",
                            "fecha_registro_auth_vh" => $fecha_registro_auth_vh,
                            "hora_registro_auth_vh" => $hora_registro_auth_vh,
                            "auth_vh_list_array" => $auth_vh_list_array,
                            
                            "autorizacion_egr" => $autorizacion_egr,
                            "max_auth_egr" => $max_auth_egr,
                            "comments_auth_egr" => $comments_auth_egr,
                            "comments_auth_egr_back" => $comments_auth_egr,
                            "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                            "hora_registro_auth_egr" => $hora_registro_auth_egr,
                            "terminado" => $terminado,
                            "fecha_respuesta_autorizacion" => $fecha_respuesta_autorizacion,
                            "time_respuesta_autorizacion" => $time_respuesta_autorizacion,
                            "anexos" => $listAnexosSoli,
                        );
                        $arraySoliReem[] = $row_soli;
                        ++$num_posicion;
                    }
                    
                    $total_restante = $importe_total - $total_reembolsado;
                    $total_restante_conversion = $importe_total_conversion - $total_reembolsado_conversion;
                    
                    $row = array(
                        "token_reem" => $token_reem,	
                        "folio_reem" => $folio_reem,
                        "fecha_solicitud" => $fecha_solicitud,
                        //emisor
                        "emisor_company" => $name_emisor,
                        "nombreEmiPers" => $name_pers_emisor,
                        //receptor
                        "receptor_company" => $name_receptor,
                        "nombreReceptorPers" => $name_pers_receptor,
                        
                        "total_reembolsado" => "$".number_format($total_reembolsado,$moneda_entrante_decimales,'.', ',')." ".$moneda_entrante_string,
                        "total_reembolsado_conversion" => "$".number_format($total_reembolsado_conversion,$moneda_saliente_decimales,'.', ',')." ".$moneda_saliente_string,
                        "total_restante" => "$".number_format($total_restante,$moneda_entrante_decimales,'.', ',')." ".$moneda_entrante_string,
                        "total_restante_conversion" => "$".number_format($total_restante_conversion,$moneda_saliente_decimales,'.', ',')." ".$moneda_saliente_string,
                        "total_importe" => "$".number_format($importe_total,$moneda_entrante_decimales,'.', ',')." ".$moneda_entrante_string,
                        "total_importe_conversion" => "$".number_format($importe_total_conversion,$moneda_saliente_decimales,'.', ',')." ".$moneda_saliente_string,
                        "comision_folio" => "COMI-".$JwtAuth->generarFolio($vremb->folio_comision),
                        "comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
                        "soliReem" => $arraySoliReem,
                    );                     
                        
                    $arrayReem[] = $row;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'reem_det' => $arrayReem,
                );
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

    public function vh_reembolso_auth(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayJust = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "tokenReembolso" => "required|string",
                "tkn_solicitud" => "required|string",
                "autorizacion" => "required|boolean",
                "observaciones" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronFecha = '/^[0-9-]*$/';
                $patronNum = '/^[0-9$,.-]*$/';
                
                $fecha_sistema = time();
                $tokenReembolso = $parametrosArray["tokenReembolso"];
                $tkn_solicitud = $parametrosArray["tkn_solicitud"];
                $autorizacion = $parametrosArray["autorizacion"];
                $observaciones = $parametrosArray["observaciones"];
                
                if (isset($tokenReembolso) && !empty($tokenReembolso) &&
                    isset($tkn_solicitud) && !empty($tkn_solicitud) &&
                    isset($autorizacion) && is_bool($autorizacion) &&
                    isset($observaciones) && !empty($observaciones) && preg_match($patron,$observaciones)) {
                        
                    $list_reem = DB::table("terc_reembolso_main AS reem_main")                         
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                    ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where(["reem_main.token_reem" => $tokenReembolso,"reem_soli.token_solicitud_reem" => $tkn_solicitud,"reem_main.status_reem" => TRUE,"emp.emp_token" => $usuario->emp_token])->get();
                    
                    foreach ($list_reem as $vReem) {
                        //da_te_default_timezone_set($vReem->zona_horaria);
                        $auth_bd = "A";    
                        if ($autorizacion == false) $auth_bd = "D";
                        
                        if ($vReem->post_folio_reem == NULL) {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem);
                        } else {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).'-'.$vReem->post_folio_reem;
                        }
                        
                        $folio_soli_reem = $JwtAuth->generarFolio($vReem->folio_solicitud);
                        
                        $update_auth_true = DB::table("terc_reembolso_main AS reem_main")
                        ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                        ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                        ->where(["reem_main.token_reem" => $vReem->token_reem,"reem_soli.token_solicitud_reem" => $vReem->token_solicitud_reem,"reem_main.status_reem" => TRUE,"emp.emp_token" => $usuario->emp_token])
                        ->limit(1)->update(array("reem_soli.autorizacion_vh" => $auth_bd));
					    
                        if ($update_auth_true) {
                            $all_soli_reem = DB::table("terc_reembolso_main AS reem_main")
                            ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                            ->where(["reem_main.token_reem" => $vReem->token_reem])->get();
                            
                            $approv_soli_reem = DB::table("terc_reembolso_main AS reem_main")
                            ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                            ->where(["reem_main.token_reem" => $vReem->token_reem,"reem_soli.autorizacion_vh" => "A"])
                            ->orwhere(["reem_main.token_reem" => $vReem->token_reem,"reem_soli.autorizacion_vh" => "D"])->get();
                            
                            if (count($approv_soli_reem) == count($all_soli_reem)) {
                                $tiempo_respuesta = time()+(259200);
                                $update_revision_vh = DB::table("terc_reembolso_main")->where(["token_reem" => $vReem->token_reem])->limit(1)
                                ->update(array("last_revision_vh" => time(),"tiempo_respuesta_auth_egr" => $tiempo_respuesta));    
                            }
                            
                            if ($autorizacion == true) {                                
                                $mensaje_sistema = "Solicitud de reembolso con folio ".$folio_reem." y subfolio ".$folio_soli_reem." fue aprobada satisfactoriamente";  
                                $mensaje_user = "Solicitud de reembolso con folio ".$folio_reem." y subfolio ".$folio_soli_reem." fue aprobada";  
                            } else {
                                $mensaje_sistema = "Solicitud de reembolso con folio ".$folio_reem." y subfolio ".$folio_soli_reem." fue desaprobada satisfactoriamente";  
                                $mensaje_user = "Solicitud de reembolso con folio ".$folio_reem." y subfolio ".$folio_soli_reem." fue desaprobada por los siguientes motivos: ".$observaciones; 
                            }
                            
                            $select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?",[$tokenReembolso]);
                            $select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?",[$tkn_solicitud]);
                            
                            $token_auth = $JwtAuth->encriptarToken(time(),$tokenReembolso.$tkn_solicitud.$autorizacion.$observaciones.time()-500);
                            
                            $select_folio_auth_vh = DB::select("SELECT r_auth.id FROM terc_reembolso_autorizacion_vh AS r_auth 
                                JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                                AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
                                [$tokenReembolso,$tkn_solicitud]);
                                
                            if (count($select_folio_auth_vh) == 0) {
                                $folio_auth = 1;
                            } else {
                                $select_folio_auth_vh = DB::select("SELECT folio_auth_reem FROM terc_reembolso_autorizacion_vh 
                                    WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_vh AS r_auth 
                                    JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                    WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                                    AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
                                    [$tokenReembolso,$tkn_solicitud]);
                                $folio_auth = $select_folio_auth_vh[0]->folio_auth_reem+1;
                            }
                            
                            $insertEquipo = DB::table('terc_reembolso_autorizacion_vh')
                            ->insert(
                                array(
                                    "token_auth_reem" => $token_auth,
                                    "folio_auth_reem" => $folio_auth,
                                    "fecha_registro" => time(),
                                    "reembolso_main" => $select_reembolso_main[0]->id, 
                                    "reembolso_solicitud" => $select_reem_soli[0]->id, 
                                    "autorizacion_vh" => $auth_bd,
                                    "comentarios" => $JwtAuth->encriptar($observaciones),
                                )
                            ); 
                            
                            if ($vReem->token_dispositivo_movil != null && $vReem->token_dispositivo_movil != "") {
                                $JwtAuth->notificacionPushDevices($vReem->token_dispositivo_movil,"Revisión de solicitud de reembolso por valor humano",$mensaje_user);
                            }
                            
                            if ($vReem->token_dispositivo_web != null && $vReem->token_dispositivo_web != "") {
                                $JwtAuth->notificacionPushDevices($vReem->token_dispositivo_web,"Revisión de solicitud de reembolso por valor humano",$mensaje_user);
                            }
                            
                            $eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                            ->join("vhum_personal AS pers","reem_main.user_receptor_vh","=","pers.id")
                            ->join("main_usuarios AS users","pers.usuario","=","users.id")
                            ->where(["reem_main.token_reem" => $vReem->token_reem])->get();
                            
                            foreach ($eegrPersEmpEmi as $vpEGR) {
                                $egrtkn_disp_movil = $vpEGR->token_dispositivo_movil;
                                $egrtkn_disp_web = $vpEGR->token_dispositivo_web;
                                if ($egrtkn_disp_movil != null && $egrtkn_disp_movil != "") $JwtAuth->notificacionPushDevices($egrtkn_disp_movil,"SOS-México - Portal para empleados",$mensaje_user);
                                if ($egrtkn_disp_web != null && $egrtkn_disp_web != "") $JwtAuth->notificacionPushDevices($egrtkn_disp_web,"SOS-México - Portal para empleados",$mensaje_user);   
                            }
                            
                            $dataMensaje = array(
                                'message' => $mensaje_sistema,
                                'code' => 200,
                                'status' => 'success'
                            );
                            //echo $vReem->token_dispositivo_movil." ".$vReem->token_dispositivo_web;
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Error en autorización'
                            );  
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                    }
                } else {
                    if(!isset($tokenReembolso) || empty($tokenReembolso)){
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Folio de reembolso incorrecto'
                        );   
                    }
                    if(!isset($tkn_solicitud) || empty($tkn_solicitud)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La solicitud de reembolso es invalida'
                        );
                    }
                    if(!isset($autorizacion) || !is_bool($autorizacion)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en validación de autorización'
                        );
                    }
                    if(!isset($observaciones) || empty($observaciones) || !preg_match($patron,$observaciones)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en observaciones'
                        );
                    }
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
    
    public function egr_reembolso_auth(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayJust = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "tokenReembolso" => "required|string",
                "tkn_solicitud" => "required|string",
                "autorizacion" => "required|boolean",
                "observaciones" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronFecha = '/^[0-9-]*$/';
                $patronNum = '/^[0-9$,.-]*$/';
                
                $fecha_sistema = time();
                $tokenReembolso = $parametrosArray["tokenReembolso"];
                $tkn_solicitud = $parametrosArray["tkn_solicitud"];
                $autorizacion = $parametrosArray["autorizacion"];
                $observaciones = $parametrosArray["observaciones"];
                
                if (isset($tokenReembolso) && !empty($tokenReembolso) &&
                    isset($tkn_solicitud) && !empty($tkn_solicitud) &&
                    isset($autorizacion) && is_bool($autorizacion) &&
                    isset($observaciones) && !empty($observaciones) && preg_match($patron,$observaciones)) {
                        
                    $list_reem = DB::table("terc_reembolso_main AS reem_main")                         
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                    ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "reem_main.token_reem" => $tokenReembolso,
                        "reem_soli.token_solicitud_reem" => $tkn_solicitud,
                        "reem_soli.autorizacion_vh" => TRUE,
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                    ])->get();
                    
                    foreach ($list_reem as $vReem) {
                        //da_te_default_timezone_set($vReem->zona_horaria);
                        
                        if ($vReem->post_folio_reem == NULL) {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem);
                        } else {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).'-'.$vReem->post_folio_reem;
                        }
                        
                        $folio_soli_reem = $JwtAuth->generarFolio($vReem->folio_solicitud);
                        
                        $update_auth_true = DB::table("terc_reembolso_main AS reem_main")                         
                        ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                        ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                        ->where([
                            "reem_main.token_reem" => $tokenReembolso,
                            "reem_soli.token_solicitud_reem" => $tkn_solicitud,
                            "reem_main.status_reem" => TRUE,
                            "emp.emp_token" => $usuario->emp_token,
                        ])
                        ->limit(1)->update(array("reem_soli.autorizacion_egr" => $autorizacion));
					    
                        if ($update_auth_true) {
                            $select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?",[$tokenReembolso]);
                            $select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?",[$tkn_solicitud]);
                            if ($autorizacion == true) {
                                $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM main_empresas AS emp  
                                    JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                                    AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                                
                                $folioOrden = DB::select("SELECT
                                    IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio
                                    FROM fnzs_pagos_orden AS ordenP JOIN main_empresas AS emp JOIN main_empresapersonal AS empper
                                    JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                    WHERE ordenP.empresa = emp.id AND emp.emp_token = ?
                                    AND emp.id = empper.empresa AND empper.personal = pers.id
                                    AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                                
                                $token_orden = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$tokenReembolso,$tkn_solicitud);
                                
                                $orderpay = new OrdenPagoModelo();
                                $orderpay->token_ordenPago = $token_orden;
                                $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                                $orderpay->fecha_sistema_ordenp = time();
                                $orderpay->factura_compra = NULL;
                                $orderpay->factura_venta = NULL;
                                $orderpay->proveedor = NULL;
                                $orderpay->cliente = NULL;
                                $orderpay->reembolso_main = $select_reembolso_main[0]->id;
                                $orderpay->reembolso_solicitud = $select_reem_soli[0]->id;
                                //$orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                                $orderpay->justificacion_main = NULL;
                                $orderpay->justificacion_solicitud = NULL;
                                $orderpay->fecha_delete_ordenPago = '';  //cifrado
                                $orderpay->status = TRUE;  //cifrado
                                $orderpay->status_pago = FALSE; //cifrado
                                $orderpay->empresa = $selectEmp[0]->id;    //cifrado
                                $orderpay->comprador = $selectEmp[0]->userr; //cifrado
                                $insertOrder = $orderpay->save();
                                
                                $mensaje_sistema = "Solicitud de reembolso con folio".$folio_reem." y subfolio ".
                                    $folio_soli_reem." fue aprobada satisfactoriamente, orden de pago generada con el folio: ".$JwtAuth->generarFolio($folioOrden[0]->folio);  
                                $mensaje_user = "Solicitud de reembolso con folio".$folio_reem." y subfolio ".
                                    $folio_soli_reem." fue aprobada, orden de pago generada con el folio: ".$JwtAuth->generarFolio($folioOrden[0]->folio);  
                            } else {
                                $mensaje_sistema = "Solicitud de reembolso con folio".$folio_reem." y subfolio ".$folio_soli_reem." fue desaprobada satisfactoriamente";  
                                $mensaje_user = "Solicitud de reembolso con folio".$folio_reem." y subfolio ".$folio_soli_reem." fue desaprobada por los siguientes motivos: ".$observaciones; 
                            }
                            
                            $token_auth = $JwtAuth->encriptarToken(time(),$tokenReembolso.$tkn_solicitud.$autorizacion.$observaciones.time()-500);
                            
                            $select_folio_auth_egr = DB::select("SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth 
                                JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                                AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
                                [$tokenReembolso,$tkn_solicitud]);
                                
                            if (count($select_folio_auth_egr) == 0) {
                                $folio_auth = 1;
                            } else {
                                $select_folio_auth_egr = DB::select("SELECT folio_auth_reem FROM terc_reembolso_autorizacion_egr 
                                    WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                                    JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                    WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                                    AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
                                    [$tokenReembolso,$tkn_solicitud]);
                                $folio_auth = $select_folio_auth_egr[0]->folio_auth_reem+1;
                            }
                            
                            $insertEquipo = DB::table('terc_reembolso_autorizacion_egr')
                            ->insert(
                                array(
                                    "token_auth_reem" => $token_auth,
                                    "folio_auth_reem" => $folio_auth,
                                    "fecha_registro" => time(),
                                    "reembolso_main" => $select_reembolso_main[0]->id, 
                                    "reembolso_solicitud" => $select_reem_soli[0]->id, 
                                    "autorizacion_egr" => $autorizacion,
                                    "comentarios" => $JwtAuth->encriptar($observaciones),
                                )
                            ); 
                            
                            if ($vReem->token_dispositivo_movil != null && $vReem->token_dispositivo_movil != "") {
                                $JwtAuth->notificacionPushDevices($vReem->token_dispositivo_movil,"Revisión de solicitud de reembolso por egresos",$mensaje_user);
                            }
                            
                            if ($vReem->token_dispositivo_web != null && $vReem->token_dispositivo_web != "") {
                                $JwtAuth->notificacionPushDevices($vReem->token_dispositivo_web,"Revisión de solicitud de reembolso por egresos",$mensaje_user);
                            }
                            
                            $dataMensaje = array(
                                'message' => $mensaje_sistema,
                                'code' => 200,
                                'status' => 'success'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Error en eliminación de anexo'
                            );  
                        }
                    }
                } else {
                    if(!isset($tokenReembolso) || empty($tokenReembolso)){
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Folio de reembolso incorrecto'
                        );   
                    }
                    if(!isset($tkn_solicitud) || empty($tkn_solicitud)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La solicitud de reembolso es invalida'
                        );
                    }
                    if(!isset($autorizacion) || !is_bool($autorizacion)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en validación de autorización'
                        );
                    }
                    if(!isset($observaciones) || empty($observaciones) || !preg_match($patron,$observaciones)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en observaciones'
                        );
                    }
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

    public function reembolso_soli_update(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('solicitud');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayJust = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "tokenReembolso" => "required|string",
                "tkn_solicitud" => "required|string",
                "fecha_gasto" => "required|string",
                "ticket_gasto" => "required|string",
                "pagado_a" => "required|string",
                "tkn_proveedor" => "string",
                "forma_pago" => "required|string",
                "importe_requerido" => "required|numeric",
                "motivo_reem" => "required|string"
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronFecha = '/^[0-9-]*$/';
                $patronNum = '/^[0-9$,.-]*$/';
                
                $fecha_sistema = time();
                $tokenReembolso = $parametrosArray["tokenReembolso"];
                $tkn_solicitud = $parametrosArray["tkn_solicitud"];
                $fecha_gasto = $parametrosArray["fecha_gasto"];
                $ticket_gasto = $parametrosArray["ticket_gasto"];
                $pagado_a = $parametrosArray["pagado_a"];
                $tkn_proveedor = $parametrosArray["tkn_proveedor"];
                $forma_pago = $parametrosArray["forma_pago"];
                $importe_requerido = $parametrosArray["importe_requerido"];
                $motivo_reem = $parametrosArray["motivo_reem"];
                
                if (isset($tokenReembolso) && !empty($tokenReembolso) &&
                    isset($tkn_solicitud) && !empty($tkn_solicitud) &&
                    isset($fecha_gasto) && !empty($fecha_gasto) && preg_match($patronFecha,$fecha_gasto) &&
                    isset($ticket_gasto) && !empty($ticket_gasto) && preg_match($patron,$ticket_gasto) &&
                    isset($pagado_a) && !empty($pagado_a) && preg_match($patron,$pagado_a) &&
                    isset($importe_requerido) && !empty($importe_requerido) && preg_match($patronNum,$importe_requerido) && 
                    isset($forma_pago) && !empty($forma_pago) && 
                    isset($motivo_reem) && !empty($motivo_reem) && preg_match($patron,$motivo_reem)) {
                        
                    $list_reem = DB::table("terc_reembolso_main AS reem_main")                         
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                    ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                    ->join("usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "reem_main.token_reem" => $tokenReembolso,
                        "reem_soli.token_solicitud_reem" => $tkn_solicitud,
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                        "users.user_token" => $usuario->user_token
                    ])->get();
                    
                    foreach ($list_reem as $vReem) {
                        //da_te_default_timezone_set($vReem->zona_horaria);
                        $select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?",[$tokenReembolso]);
                        if ($vReem->post_folio_reem == NULL) {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem);
                        } else {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).'-'.$vReem->post_folio_reem;
                        }
                        
                        $prov_sql = NULL;
                        if ($pagado_a == "prov") {
                            if (isset($tkn_proveedor) && !empty($tkn_proveedor)) {
                                $selectProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?",[$tkn_proveedor]);
                                foreach ($selectProv as $vProv) {
                                    $prov_sql = $vProv->id;
                                }
                            } else {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en proveedor seleccionado'
                                );
                            }
                        }
                        
                        $selectFPago = DB::select("SELECT id FROM forma_pago WHERE token_formapago = ?",[$forma_pago]);
                        foreach ($selectFPago as $vfpag) {
                            $fpag_sql = $vfpag->id;
                        }
                        
                        $regUpdate = DB::table('reembolso_solicitud AS reem_soli')
                        ->join("terc_reembolso_main AS rmain","reem_soli.reembolso_main","=","rmain.id")
                        ->where([
                            "reem_soli.token_solicitud_reem" => $vReem->token_solicitud_reem,
                            "rmain.token_reem" => $vReem->token_reem,
                        ])
                        ->limit(1)->update(
                            array(
                                "reem_soli.fecha_gasto" => $JwtAuth->convierteFechaEpoc($fecha_gasto),
                                "reem_soli.ticket_gasto" => $JwtAuth->encriptar($ticket_gasto),
                                "reem_soli.pagado_a" => $pagado_a,
                                "reem_soli.proveedor" => $prov_sql,
                                "reem_soli.forma_pago" => $fpag_sql,
                                "reem_soli.importe_requerido" => $importe_requerido,
                                "reem_soli.motivo_reem" => $JwtAuth->encriptar($motivo_reem),
                            )
                        );
                        
                        if ($regUpdate) {
                            $dataMensaje = array(
                                'message' => 'Reembolso '.$folio_reem.' fue actualizado',
                                'code' => 200,
                                'status' => 'success'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Error en actualización de solicitud'
                            );  
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                    }
                } else {
                    if(!isset($tokenReembolso) || empty($tokenReembolso)){
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Folio de reembolso incorrecto'
                        );   
                    }
                    if(!isset($tkn_solicitud) || empty($tkn_solicitud)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La solicitud de reembolso es invalida'
                        );
                    }
                    if(!isset($fecha_gasto) || empty($fecha_gasto) || !preg_match($patronFecha,$fecha_gasto)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en fecha de gasto'
                        );
                    }
                    if(!isset($ticket_gasto) || empty($ticket_gasto) || !preg_match($patron,$ticket_gasto)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en ticket de comprobación de gasto'
                        );
                    }
                    if(!isset($pagado_a) || empty($pagado_a) || !preg_match($patron,$pagado_a)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en campo "pagado a:"'
                        );
                    }
                    if(!isset($importe_requerido) || empty($importe_requerido) || !preg_match($patronNum,$importe_requerido)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en reembolso total'
                        );
                    } 
                    if(!isset($forma_pago) || empty($forma_pago)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en forma de pago'
                        );
                    } 
                    if(!isset($motivo_reem) || empty($motivo_reem) || !preg_match($patron,$motivo_reem)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en motivos de reembolso / observaciones'
                        );
                    }
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
    
    public function reembolso_load_docs(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('solicitud');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayJust = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "tokenReembolso" => "required|string",
                "tkn_solicitud" => "required|string"
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronFecha = '/^[0-9-]*$/';
                $patronNum = '/^[0-9$,.-]*$/';
                
                $fecha_sistema = time();
                $tokenReembolso = $parametrosArray["tokenReembolso"];
                $tkn_solicitud = $parametrosArray["tkn_solicitud"];
                
                if (isset($tokenReembolso) && !empty($tokenReembolso) &&
                    isset($tkn_solicitud) && !empty($tkn_solicitud) &&
                    isset($_FILES["docsReemAnexos"]) && !empty($_FILES["docsReemAnexos"])) {
                        
                    $list_reem = DB::table("terc_reembolso_main AS reem_main")                         
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("terc_reembolso_solicitud AS reem_soli","reem_main.id","=","reem_soli.reembolso_main")
                    ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                    ->join("usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "reem_main.token_reem" => $tokenReembolso,
                        "reem_soli.token_solicitud_reem" => $tkn_solicitud,
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                        "users.user_token" => $usuario->user_token
                    ])->get();
                    
                    foreach ($list_reem as $vReem) {
                        //da_te_default_timezone_set($vReem->zona_horaria);
                        $countdocs = 0;
                        $anexos = $_FILES["docsReemAnexos"];
                        
                        $select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?",[$tokenReembolso]);
                        $select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?",[$tkn_solicitud]);
                        
                        if ($vReem->post_folio_reem == NULL) {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem);
                        } else {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).'-'.$vReem->post_folio_reem;
                        }
                        
                        $filepath = $vReem->root_tkn."/0010-reem/".$folio_reem."/anexos";
                        
                        if (!file_exists(storage_path("/root/".$filepath))){
                            Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => $filepath]);
                        
                        $docs_nombre = json_decode(json_encode($_FILES["docsReemAnexos"]["name"]));
                        for ($i=0; $i < count($docs_nombre); $i++){
                            $name_documento = $docs_nombre[$i];
                            //return response()->json(['status' => 'error','code' => 200,'message' => $folio_reem]);
                            $type = $anexos["type"][$i];
                            if ($type == "application/pdf") {
                                $ext_doc = "pdf";
                            } else if ($type == "image/jpeg") {
                                $ext_doc = "jpg";
                            } else if ($type == "image/jpg") {
                                $ext_doc = "jpg";
                            } else if ($type == "image/png") {
                                $ext_doc = "png";
                            }
                            
                            $temporal = $anexos["tmp_name"][$i];
                            $documento_crypt = $JwtAuth->encriptar($name_documento);
                            $token_docs = $JwtAuth->encriptarToken($tkn_solicitud,$ext_doc,$documento_crypt);
                            //return response()->json(['status' => 'error','code' => 200,'message' => $name_documento]);
                            
                            $rowsDocSoli = DB::select("SELECT id FROM sos_documentos WHERE name_documento = ? 
                                AND reembolso_main = ? AND reembolso_solicitud = ?",
                                [$documento_crypt,$select_reembolso_main[0]->id,$select_reem_soli[0]->id]);
                            
                            if (count($rowsDocSoli) == 0) {
                                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
                                $insertDocSoli = DB::table("sos_documentos")->insert(
                                    array(
                                        "token_documento" => $token_docs,
                                        "fecha_carga" => $fecha_sistema,
                                        "modulo" => "reembolsos",
                                        "folio_modulo" => "REEM-EVID".$select_folio_doc[0]->folio,
                                        "tipo_documento" => "an",
                                        "nombre_documento" => $JwtAuth->encriptar($name_documento),
                                        "extension_documento" => $ext_doc,
                                        "reembolso_main" => $select_r_main[0]->id,
                                        "reembolso_solicitud" => $select_reem_soli[0]->id,
                                    )
                                );
                                
                                if ($insertDocSoli) {
                                    $countdocs++;
                                    Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                                }
                            } else {
                                $countdocs++;
                                Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                            }
                        }
                        
                        if ($countdocs == count($docs_nombre)) {
                            $dataMensaje = array(
                                'message' => 'Reembolso '.$folio_reem.' fue actualizado',
                                'code' => 200,
                                'status' => 'success'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Error en actualización de solicitud'
                            );  
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                    }
                } else {
                    if(!isset($tokenReembolso) || empty($tokenReembolso)){
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Folio de reembolso incorrecto'
                        );   
                    }
                    if(!isset($tkn_solicitud) || empty($tkn_solicitud)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La solicitud de reembolso es invalida'
                        );
                    }
                    if(!isset($_FILES["docsReemAnexos"]) || empty($_FILES["docsReemAnexos"])) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en los archivos que intenta cargar'
                        );
                    }
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
    
    public function reembolso_agregar(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('solicitud');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayJust = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "tokenReembolso" => "required|string",
                "fecha_gasto" => "required|string",
                "ticket_gasto" => "required|string",
                "pagado_a" => "required|string",
                "tkn_proveedor" => "required|string",
                "forma_pago" => "required|string",
                "importe_requerido" => "required|numeric",
                "motivo_reem" => "required|string"
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronFecha = '/^[0-9-]*$/';
                $patronNum = '/^[0-9$,.-]*$/';
                
                $fecha_sistema = time();
                $tiempo_respuesta = $fecha_sistema+(86400*5);
                $tokenReembolso = $parametrosArray["tokenReembolso"];
                $fecha_gasto = $parametrosArray["fecha_gasto"];
                $ticket_gasto = $parametrosArray["ticket_gasto"];
                $pagado_a = $parametrosArray["pagado_a"];
                $tkn_proveedor = $parametrosArray["tkn_proveedor"];
                $forma_pago = $parametrosArray["forma_pago"];
                $importe_requerido = $parametrosArray["importe_requerido"];
                $motivo_reem = $parametrosArray["motivo_reem"];
                
                //if(!empty($_FILES["docsReemAnexos"])){
                //    return response()->json(['status' => 'error','code' => 200,'message' => "hay_documento"]);
                //}
                
                if (isset($tokenReembolso) && !empty($tokenReembolso) &&
                    isset($fecha_gasto) && !empty($fecha_gasto) && preg_match($patronFecha,$fecha_gasto) &&
                    isset($ticket_gasto) && !empty($ticket_gasto) && preg_match($patron,$ticket_gasto) &&
                    isset($pagado_a) && !empty($pagado_a) && preg_match($patron,$pagado_a) &&
                    isset($forma_pago) && !empty($forma_pago) && 
                    isset($importe_requerido) && !empty($importe_requerido) && preg_match($patronNum,$importe_requerido) && 
                    isset($motivo_reem) && !empty($motivo_reem) && preg_match($patron,$motivo_reem)) {
                        
                    $list_reem = DB::table("terc_reembolso_main AS reem_main")                         
                    ->join("main_empresas AS emp","reem_main.receptor","=","emp.id")
                    ->join("vhum_personal AS pers","reem_main.user_emisor","=","pers.id")
                    ->join("usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "reem_main.token_reem" => $tokenReembolso,
                        "reem_main.status_reem" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                        "users.user_token" => $usuario->user_token
                    ])->get();
                    
                    foreach ($list_reem as $vReem) {
                        //da_te_default_timezone_set($vReem->zona_horaria);

                        if ($vReem->post_folio_reem == NULL) {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem);
                        } else {
                            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).'-'.$vReem->post_folio_reem;
                        }

                        $select_r_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?",[$vReem->token_reem]);
                        
                        $query_fol_max = DB::select("SELECT MAX(rSoli.folio_solicitud)+1 AS jFolio FROM reembolso_solicitud AS rSoli 
                            JOIN terc_reembolso_main AS rMain WHERE rSoli.reembolso_main = rMain.id AND rMain.token_reem = ?",
                            [$tokenReembolso]);
                        
                        $new_folio_solicitud = $query_fol_max[0]->jFolio;
                        $new_folio_soli_all = $JwtAuth->generarFolio($query_fol_max[0]->jFolio);
                    
                        $token_reem_soli = $JwtAuth->encriptarToken($tokenReembolso.$fecha_gasto.$ticket_gasto.$motivo_reem.
                            $tkn_proveedor.$pagado_a.$importe_requerido.$forma_pago.$motivo_reem);
                        
                        $prov_sql = NULL;
                        if ($pagado_a == "prov") {
                            if (isset($tkn_proveedor) && !empty($tkn_proveedor)) {
                                $selectProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?",[$tkn_proveedor]);
                                foreach ($selectProv as $vProv) {
                                    $prov_sql = $vProv->id;
                                }                            
                            } else {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en proveedor seleccionado'
                                );
                            }
                        }
                        
                        $selectFPago = DB::select("SELECT id FROM forma_pago WHERE token_formapago = ?",[$forma_pago]);
                        foreach ($selectFPago as $vfpag) {
                            $fpag_sql = $vfpag->id;
                        }
                        
                        $insert_reem_soli = DB::table('terc_reembolso_solicitud')->insert(
                            array(
                                "token_solicitud_reem" => $token_reem_soli,	
                                "folio_solicitud" => $new_folio_solicitud,
                                "fecha_solicitud" => $fecha_sistema, 
                                "reembolso_main" => $select_r_main[0]->id,
                                "fecha_gasto" => $JwtAuth->convierteFechaEpoc($fecha_gasto),
                                "ticket_gasto" => $JwtAuth->encriptar($ticket_gasto),
                                "pagado_a" => $pagado_a,
                                "proveedor" => $prov_sql,
                                "forma_pago" => $fpag_sql,
                                "importe_requerido" => $importe_requerido,
                                "motivo_reem" => $JwtAuth->encriptar($motivo_reem),
                                "tiempo_respuesta" => $tiempo_respuesta,	
                                "version" => TRUE,
                                "fecha_delete" => NULL,
                            )
                        );
                        if ($insert_reem_soli) {
                            if(!empty($_FILES["docsReemAnexos"])){
                                $select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?",[$token_reem_soli]);
                                //return response()->json(['status' => 'error','code' => 200,'message' => "name_documento1"]);
                                $anexos = $_FILES["docsReemAnexos"];
                                //return response()->json(['status' => 'error','code' => 200,'message' => "name_documento2"]);
                                $filepath = $vReem->root_tkn."/0010-reem/".$folio_reem."/anexos";
                                //return response()->json(['status' => 'error','code' => 200,'message' => "name_documento3"]);
                                if (!file_exists(storage_path("/root/".$filepath))){
                                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                    //return response()->json(['status' => 'error','code' => 200,'message' => "name_documento4"]);
                                }
                                //return response()->json(['status' => 'error','code' => 200,'message' => "name_documento5"]);
                                $docs_nombre = json_decode(json_encode($_FILES["docsReemAnexos"]["name"]));
                                //return response()->json(['status' => 'error','code' => 200,'message' => "name_documento6"]);
                                for ($i=0; $i < count($docs_nombre); $i++){
                                    $name_documento = $docs_nombre[$i];
                                    
                                    $type = $anexos["type"][$i];
                                    if ($type == "application/pdf") {
                                        $ext_doc = "pdf";
                                    } else if ($type == "image/jpeg") {
                                        $ext_doc = "jpg";
                                    } else if ($type == "image/jpg") {
                                        $ext_doc = "jpg";
                                    } else if ($type == "image/png") {
                                        $ext_doc = "png";
                                    }
                                    
                                    $temporal = $anexos["tmp_name"][$i];
                                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
                                    $token_docs = $JwtAuth->encriptarToken($token_reem_soli,$ext_doc,$name_documento);
                                    //return response()->json(['status' => 'error','code' => 200,'message' => $name_documento]);
                                    $insertDocSoli = DB::table("sos_documentos")->insert(
                                        array(
                                            "token_documento" => $token_docs,
                                            "fecha_carga" => $fecha_sistema,
                                            "modulo" => "reembolsos",
                                            "folio_modulo" => "REEM-EVID".$select_folio_doc[0]->folio,
                                            "tipo_documento" => "an",
                                            "nombre_documento" => $JwtAuth->encriptar($name_documento),
                                            "extension_documento" => $ext_doc,
                                            "reembolso_main" => $select_r_main[0]->id,
                                            "reembolso_solicitud" => $select_reem_soli[0]->id,
                                        )
                                    );
                                    
                                    //return response()->json(['status' => 'error','code' => 200,'message' => $name_documento]);
                                    if ($insertDocSoli) {
                                        Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                                    }
                                }
                            }
                            
                            $dataMensaje = array(
                                'message' => 'Solicitud añadida con el folio '.$new_folio_soli_all,
                                'code' => 200,
                                'status' => 'success'
                            );
                        }
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
                        //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                    }
                } else {
                    if(!isset($tokenReembolso) || empty($tokenReembolso)){
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Folio de reembolso incorrecto'
                        );   
                    }
                    if(!isset($fecha_gasto) || empty($fecha_gasto) || !preg_match($patronFecha,$fecha_gasto)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en fecha de gasto'
                        );
                    }
                    if(!isset($ticket_gasto) || empty($ticket_gasto) || !preg_match($patron,$ticket_gasto)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en ticket de comprobación de gasto'
                        );
                    }
                    if(!isset($pagado_a) || empty($pagado_a) || !preg_match($patron,$pagado_a)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en campo "pagado a:"'
                        );
                    }
                    if(!isset($importe_requerido) || empty($importe_requerido) || !preg_match($patronNum,$importe_requerido)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en reembolso total'
                        );
                    } 
                    if(!isset($forma_pago) || empty($forma_pago)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en forma de pago'
                        );
                    } 
                    if(!isset($motivo_reem) || empty($motivo_reem) || !preg_match($patron,$motivo_reem)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en motivos de reembolso / observaciones'
                        );
                    }
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

    public function reembolso_registro(Request $request){
        $JwtAuth = new \JwtAuth();
        //$docsAnexos = $request->file("docsAnexos");
        $json_reem = $request->input('solicitud');
        $parametros = json_decode($json_reem);
        $parametrosArray = json_decode($json_reem,true);
        $arrayTareas = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "reembolsos" => "required|array",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                $reembolsos = $parametrosArray["reembolsos"];
                //echo $soliCfdiRfc;exit;
                $fecha_sistema = time();
                $tiempo_respuesta = $fecha_sistema+(86400*5);
                $emisor_emp = "";
                $nacionalidad = "";
                $rfc_generico_emi = "";
                $rfc_emp_emi = "";
                $count_rfc_emp_emi = 0;
                $taxid_emp_emi = "";
                $tipo_factura = "";
                
                //return response()->json(['status' => 'error','code' => 200,'message' => "prueba ".$soliCfdiFactPagada]);
                
                //$patron = '/[aA-zZ_]/';
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronRfc = '/[aA0-zZ9]/';
                $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
                $patronFecha = '/^[0-9-]*$/';
                $patronPrecio = '/^[0-9$,.-]*$/';
                
                if (isset($reembolsos) && !empty($reembolsos)) {
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM empresas AS emp  
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);  
                    
                    $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder
                        FROM last_folders AS fold JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers 
                        JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE fold.reembolsos = TRUE 
                        AND fold.empresa = emp.id AND emp.emp_token = ? AND emp.id = emppers.empresa 
                        AND emppers.personal = pers.id AND pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->emp_token,$usuario->user_token]);
               
                    if (count($folioSistema) == 1) {
                        if ($folioSistema[0]->folio == 1000000000) {
                            $post_folio_db = DB::select("SELECT post_folio_reem FROM reembolso_main 
                                WHERE id = (SELECT Max(reem.id) FROM reembolso_main AS reem 
                                JOIN main_empresas AS emp JOIN empresapersonal AS empper 
                                JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                WHERE reem.receptor = emp.id AND emp.emp_token = ?
                                AND emp.id = empper.empresa AND empper.personal = pers.id
                                AND pers.usuario = users.id AND users.user_token = ?)",
                                [$usuario->emp_token,$usuario->user_token]);

                            $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_reem);
                            $folio_nuevo = 1;
                        } else {
                            $post_folio = NULL;
                            $folio_nuevo = $folioSistema[0]->folio;
                        }
                    } else {
                        $post_folio = NULL;
                        $folio_nuevo = 1;
                    }
            
                    if ($post_folio == NULL) {
                        $folio_reem = "REEM-".$JwtAuth->generarFolio($folio_nuevo);
                    } else {
                        $folio_reem = "REEM-".$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                    }
                    
                    $selectEmisorPers = DB::select("SELECT pers.id FROM personal AS pers 
                        JOIN main_usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->user_token]);
                    
                    foreach ($selectEmisorPers as $vPersEmi) {
                        $emisor_pers = $vPersEmi->id;
                    }
                    
                    $selectEmisorEmp = DB::select("SELECT emp.id AS company,people.nacionalidad,people.rfc_generico,
                        people.rfc,people.tax_id FROM empresas AS emp 
                        JOIN personas AS people WHERE emp.persona = people.id AND emp.emp_token = ?",
                        [$usuario->emp_token]);
                    
                    foreach ($selectEmisorEmp as $vEmpEmi) {
                        $emisor_emp = $vEmpEmi->company;
                    }
                    
                    //echo $clienteIdent;exit;
                    $token_reembolso_main = $JwtAuth->encriptarToken(rand(5, 15).$folio_reem.$fecha_sistema.
                        $emisor_emp.$emisor_pers.$usuario->emp_token,$usuario->user_token);
                    
                    //$importe_reembolso,$motivo_reembolso
                    //return response()->json(['status' => 'error','code' => 200,'message' => "prueba ".$sql_importe_venta]);
                    $newReem = new ReembolsoModelo();
                    $newReem->token_reem = $token_reembolso_main;
                    $newReem->folio_reem = $folio_nuevo;
                    $newReem->post_folio_reem = $post_folio;
                    $newReem->fecha_sistema = $fecha_sistema;
                    $newReem->emisor = $emisor_emp;
                    $newReem->receptor = 1;
                    $newReem->status_reem = TRUE;
                    $newReem->fecha_delete = NULL;
                    $newReem->user_emisor = $emisor_pers;
                    $newReem->user_receptor = 3;
                    $insertReem = $newReem->save();
                    
                    if ($insertReem) {
                        $select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?",[$token_reembolso_main]);
                        $countReembolsos = 0;
                        for ($i = 0; $i < count($reembolsos); $i++) {
                            $new_folio_solicitud = $i+1;
                            $new_folio_all_solicitud = $JwtAuth->generarFolio($i+1);
                            
                            $reem_fecha = $reembolsos[$i]["reem_fecha"];
                            $reem_folio_ticket = $reembolsos[$i]["reem_folio_ticket"];
                            $reem_pagado_a = $reembolsos[$i]["reem_pagado_a"];
                            $proveedor_id = NULL;
                            $proveedor_tkn = $reembolsos[$i]["proveedor_tkn"];
                            $tkn_forma_pago = $reembolsos[$i]["tkn_forma_pago"];
                            $reem_importe_total = $reembolsos[$i]["reem_importe_total"];
                            $reem_observacion = $reembolsos[$i]["reem_observacion"];
                            $anexos = $reembolsos[$i]["anexos"];
                            
                            if ($proveedor_tkn != "") {
                                $selectProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?",[$proveedor_tkn]);
                                foreach ($selectProv as $vProv) {
                                    $proveedor_id = $vProv->id;
                                }
                            }
                            
                            $selectFPago = DB::select("SELECT id FROM forma_pago WHERE token_formapago = ?",[$tkn_forma_pago]);
                            foreach ($selectFPago as $vfpag) {
                                $fpag_sql = $vfpag->id;
                            }
                            
                            $token_reem_soli = $JwtAuth->encriptarToken($token_reembolso_main.$new_folio_all_solicitud.
                                $reem_fecha.$reem_folio_ticket.$reem_pagado_a.$proveedor_tkn.$tkn_forma_pago.$reem_importe_total.$reem_observacion);
                                
                            $insert_reem_soli = DB::table('terc_reembolso_solicitud')->insert(
                                array(
                                    "token_solicitud_reem" => $token_reem_soli,	
                                    "folio_solicitud" => $new_folio_solicitud,
                                    "fecha_solicitud" => $fecha_sistema,	
                                    "reembolso_main" => $select_reembolso_main[0]->id, 
                                    "fecha_gasto" => $JwtAuth->convierteFechaEpoc($reem_fecha),
                                    "ticket_gasto" => $JwtAuth->encriptar($reem_folio_ticket),
                                    "pagado_a" => $reem_pagado_a,
                                    "proveedor" => $proveedor_id,
                                    "forma_pago" => $fpag_sql,
                                    "importe_requerido" => $reem_importe_total,
                                    "motivo_reem" => $JwtAuth->encriptar($reem_observacion),
                                    "tiempo_respuesta" => $tiempo_respuesta,	
                                    "version" => TRUE,
                                    "fecha_delete" => NULL,
                                )
                            );
                            if ($insert_reem_soli) {
                                if (!empty($_FILES["docsReemAnexos"]) && count($anexos) > 0) {
                                    $select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?",[$token_reem_soli]);
                                    for ($j = 0; $j < count($anexos); $j++){
                                        $name_documento = $anexos[$j]["nameFile"];
                                        $type = $anexos[$j]["typoElement"];
                                        if ($type == "application/pdf") {
                                            $ext_doc = "pdf";
                                        } else if ($type == "text/xml") {
                                            $ext_doc = "xml";
                                        } else if ($type == "image/jpeg") {
                                            $ext_doc = "jpg";
                                        } else if ($type == "image/jpg") {
                                            $ext_doc = "jpg";
                                        } else if ($type == "image/png") {
                                            $ext_doc = "png";
                                        }
                                        
                                        $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
                                        
                                        $token_docs = $JwtAuth->encriptarToken($token_reembolso_main,$ext_doc,$name_documento);
                                        //return response()->json(['status' => 'error','code' => 200,'message' => $JwtAuth->encriptar($name_documento)]);
                                        $insertDocSoli = DB::table("sos_documentos")->insert(
                                            array(
                                                "token_documento" => $token_docs,
                                                "fecha_carga" => $fecha_sistema,
                                                "modulo" => "reembolsos",
                                                "folio_modulo" => "REEM-EVID".$select_folio_doc[0]->folio,
                                                "tipo_documento" => "an",
                                                "nombre_documento" => $JwtAuth->encriptar($name_documento),
                                                "extension_documento" => $ext_doc,
                                                "reembolso_main" => $select_r_main[0]->id,
                                                "reembolso_solicitud" => $select_reem_soli[0]->id,
                                            )
                                        );
                                    }
                                }
                                $countReembolsos++;
                            }
                        }
                        
                        if ($countReembolsos == count($reembolsos)) {
                            //$query_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?",[$token_reem_soli]);
                            if(!empty($_FILES["docsReemAnexos"])){
                                $anexos = $_FILES["docsReemAnexos"];
                                $filepath = $selectEmp[0]->root_tkn."/0010-reem/".$folio_reem."/anexos";
                            
                                if (!file_exists(storage_path("/root/".$filepath))){
                                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                }
                                $docs_nombre = json_decode(json_encode($_FILES["docsReemAnexos"]["name"]));
                                for ($i=0; $i < count($docs_nombre); $i++){
                                    $name_documento = $docs_nombre[$i];
                                    $temporal = $anexos["tmp_name"][$i];
                                    Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                                }
                            }
                            
                            if (count($folioSistema) == 0) {
                                $insertSistema = DB::table('last_folders')
                                ->insert(
                                    array(
                                        "reembolsos" => TRUE, 
                                        "folder" => 1, 
                                        "post_folder" => $post_folio,
                                        "empresa" => $emisor_emp,
                                    )
                                );
                            } else {
                                $regFolder = DB::table('last_folders')->join("main_empresas AS emp","last_folders.empresa","=","emp.id")
                                ->join("empresapersonal AS emppers","emp.id","emppers.empresa")
                                ->join("vhum_personal AS pers","emppers.personal","pers.id")
                                ->join("usuarios AS users","pers.usuario","users.id")
                                ->where([
                                    'last_folders.reembolsos' => TRUE,
                                    'emp.emp_token' => $usuario->emp_token,
                                    'users.user_token' => $usuario->user_token,
                                ])
                                ->limit(1)->update(
                                    array(
                                        'last_folders.folder' => $folio_nuevo,
                                        'last_folders.post_folder' => $post_folio,
                                    )
                                );
                            }
                            
                            $titulo_alerta = "Ha enviado una nueva solicitud de CFDI con folio: ".$folio_reem;
                            $JwtAuth->insertGeneralNotif($titulo_alerta,1,2,1,$emisor_pers,3);
                             
                            $dataMensaje = array(
                                'message' => 'reem_saved',
                                'folio_reem' => $folio_reem,
                                'code' => 200,
                                'status' => 'success'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'reem_fail_inside'
                            );
                        }
                            
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'reem_fail_inside'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'reem_list_fail'
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
