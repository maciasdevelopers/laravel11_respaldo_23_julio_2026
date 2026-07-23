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
use App\Models\AssociatesModelo;
use App\Models\CfdiModelo;

class TERC_AssociatesController extends Controller{
    
    public function listaSolicitudCFDI(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arraySoliCFDI = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "token_back_ter" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"],true);
                
                if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                $listSoliCFDI = CfdiModelo::join("sos_cfdi_solicitud AS soli_cfdi","sos_cfdi_main.id","=","soli_cfdi.cfdi_main")
                ->join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                ->join("vhum_personal AS pers","sos_cfdi_main.user_emisor","=","pers.id")
                //->join("main_usuarios AS users","pers.usuario","=","users.id")
                ->where([
                    "soli_cfdi.valid_version" => TRUE,
                    "sos_cfdi_main.registro_cfdi" => "so",
                    "sos_cfdi_main.status_cfdi" => TRUE,
                    "emp.emp_token" => $usuario->emp_token
                ])                
                ->orderBy('sos_cfdi_main.folio_cfdi','DESC')->get();
                } else {
                    $listSoliCFDI = CfdiModelo::join("sos_cfdi_solicitud AS soli_cfdi","sos_cfdi_main.id","=","soli_cfdi.cfdi_main")
                    ->join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                    ->join("vhum_personal AS pers","sos_cfdi_main.user_emisor","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        "soli_cfdi.valid_version" => TRUE,
                        "sos_cfdi_main.registro_cfdi" => "so",
                        "sos_cfdi_main.status_cfdi" => TRUE,
                        "emp.emp_token" => $usuario->emp_token,
                        "users.user_token" => $usuario->user_token
                    ])                
                    ->orderBy('sos_cfdi_main.folio_cfdi','DESC')->get();
                }
                
                foreach ($listSoliCFDI as $vsoli) {
                    //da_te_default_timezone_set($vsoli->zona_horaria);
                    $token_cfdi = $vsoli->token_cfdi;
                    $tkn_soli_cfdi = $vsoli->token_solicitud_cfdi;
                    //echo $token_cfdi." ".$token_cfdi;exit;
                    
                    $fecha_solicitud = date('d-m-Y H:i:s',$vsoli->fecha_sistema);
                    $iva_final = 0;
                    $importe_final = 0;
                    
                    if ($vsoli->post_folio_cfdi == NULL) {
                        $folio_cfdi = 'CFDI-'.$JwtAuth->generarFolio($vsoli->folio_cfdi)."-S".$vsoli->folio_solicitud;
                    } else {
                        $folio_cfdi = 'CFDI-'.$JwtAuth->generarFolio($vsoli->folio_cfdi).'-'.$vsoli->post_folio_cfdi."-S".$vsoli->folio_solicitud;
                    }
                    
                    $selectNameEmpEmi = CfdiModelo::join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                    ->join("sos_personas AS people","emp.persona","=","people.id")
                    ->where(["sos_cfdi_main.token_cfdi" => $token_cfdi])->get();
                    
                    foreach ($selectNameEmpEmi as $vEmisor) {
                        if ($vEmisor->denominacion_rs == '') {
                            $name_emisor = $JwtAuth->desencriptar($vEmisor->paterno).
                                " ".$JwtAuth->desencriptar($vEmisor->materno).
                                " ".$JwtAuth->desencriptar($vEmisor->nombre);
                        } else {
                            $name_emisor = $JwtAuth->desencriptar($vEmisor->denominacion_rs);
                        }
                    
                        $rfc_gen_emi = $vEmisor->rfc_generico;
			            
			            if ($vEmisor->rfc != NULL) {
			            	$rfc_emp_emi = $JwtAuth->desencriptar($vEmisor->rfc);
			            } else {
			            	$rfc_emp_emi = "---";
			            }
                        
                        if ($vEmisor->tax_id != NULL) {
			            	$taxid_emp_emi = $JwtAuth->desencriptar($vEmisor->tax_id);
			            } else {
			            	$taxid_emp_emi = "---";
			            }
                    }
            
                    $selectPersEmpEmi = CfdiModelo::join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                    ->join("vhum_personal AS pers","sos_cfdi_main.user_emisor","=","pers.id")
                    ->join("sos_personas AS people","pers.personal","=","people.id")
                    ->where(["sos_cfdi_main.token_cfdi" => $token_cfdi])->get();
                    
                    foreach ($selectPersEmpEmi as $vPemi) {
                        $nombreEmiPers = $JwtAuth->desencriptar($vPemi->paterno).
                            " ".$JwtAuth->desencriptar($vPemi->materno).
                            " ".$JwtAuth->desencriptar($vPemi->nombre);
                    }
                    
                    if ($vsoli->cliente != NULL) {
                        $selectNameEmpFact = CfdiModelo::join("sos_cfdi_solicitud AS soli_cfdi","sos_cfdi_main.id","=","soli_cfdi.cfdi_main")
                        ->join("ingr_catalogo_clientes AS cKli","soli_cfdi.cliente","=","cKli.id")
                        ->join("sos_personas AS client","cKli.cliente","=","client.id")
                        //->join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                        //->join("vhum_personal AS pers","sos_cfdi_main.user_emisor","=","pers.id")
                        //->join("usuarios AS users","pers.usuario","=","users.id")
                        ->where(["sos_cfdi_main.token_cfdi" => $token_cfdi])->get();
                        
                        foreach ($selectNameEmpFact as $vEmpFact) {
                            $tkn_emp_fact = $vEmpFact->token_cat_clientes;
                            if ($vEmpFact->denominacion_rs == '') {
                                $name_emp_fact = $JwtAuth->desencriptar($vEmpFact->paterno).
                                    " ".$JwtAuth->desencriptar($vEmpFact->materno).
                                    " ".$JwtAuth->desencriptar($vEmpFact->nombre);
                            } else {
                                $name_emp_fact = $JwtAuth->desencriptar($vEmpFact->denominacion_rs);
                            }
                        
                            $rfc_gen_fact = $vEmpFact->rfc_generico;
			                
			                if ($vEmpFact->rfc != NULL) {
			                	$rfc_emp_fact = $JwtAuth->desencriptar($vEmpFact->rfc);
			                } else {
			                	$rfc_emp_fact = "---";
			                }
                            
                            if ($vEmpFact->tax_id != NULL) {
			                	$taxid_emp_fact = $JwtAuth->desencriptar($vEmpFact->tax_id);
			                } else {
			                	$taxid_emp_fact = "---";
			                }
                        }
                    } else {
                        $tkn_emp_fact = "---";
                        $name_emp_fact = $JwtAuth->desencriptar($vsoli->emp_factura);
                        $rfc_gen_fact = "---";
			            $rfc_emp_fact = $vsoli->rfc_factura;
			            $taxid_emp_fact = "---";
                    }
                    
                    $email_referencia = $JwtAuth->desencriptar($vsoli->email_referencia);	
                    $tipo_factura = $vsoli->tipo_factura;
                
                    $selectDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                    ->join("sos_cfdi_main AS main","det_soli.cfdi_main","=","main.id")
                    ->where([
                        "main.token_cfdi" => $token_cfdi,
                        "soli.token_solicitud_cfdi" => $tkn_soli_cfdi,
                    ])->get();
                    
                    //echo " count(selectDetCFDI)".count($selectDetCFDI);
                    
                    foreach ($selectDetCFDI as $vDet) {
                        $token_det_soli = $vDet->token_detalle_soli;
                        
                        if ($vDet->clave_sat != NULL) {
                            $satKeyDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                            ->join("teci_catalogo_prodservsat AS sat","det_soli.clave_sat","=","sat.id")
                            ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud","=","soli.id")
                            ->where([
                                "det_soli.token_detalle_soli" => $token_det_soli,
                                "soli.token_solicitud_cfdi" => $tkn_soli_cfdi,
                            ])->get();
                                    
                            $sat_token = $satKeyDetCFDI[0]->token_prodservsat;	
                            $sat_clave = $satKeyDetCFDI[0]->clave;
                            $sat_descripcion = $satKeyDetCFDI[0]->descripcion;
                            
                        } else {
                            $sat_token = "";
                            $sat_clave = "";
                            $sat_descripcion = "";
                        }
                            
                        if ($vDet->unidad_medida != NULL) {
                            $satMedDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                            ->join("teci_unidad_medida AS med","det_soli.unidad_medida","=","med.id")
                            ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                            ->where([
                                "det_soli.token_detalle_soli" => $token_det_soli,
                                "soli.token_solicitud_cfdi" => $tkn_soli_cfdi,
                            ])->get();
                                    
                            $uni_med_token = $satMedDetCFDI[0]->token_unidad_medida;	
                            $uni_medida = $satMedDetCFDI[0]->unidad_medida;
                            $uni_med_clave = $satMedDetCFDI[0]->sat_clave;
                        } else {
                            $uni_med_token = "";	
                            $uni_medida = "";
                            $uni_med_clave = "";
                        }
                        
                        $fPagoDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                        ->join("teci_forma_pago AS fpago","det_soli.forma_pago","=","fpago.id")
                        ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                        ->where([
                            "det_soli.token_detalle_soli" => $token_det_soli,
                            "soli.token_solicitud_cfdi" => $tkn_soli_cfdi,
                        ])->get();
                                    
                        $fpago_token = $fPagoDetCFDI[0]->token_formapago;	
                        $fpago_clave = $fPagoDetCFDI[0]->clave;
                        $fpago_forma = $fPagoDetCFDI[0]->forma;
                        
                        $mPagoDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                        ->join("teci_metodo_pago AS mpago","det_soli.metodo_pago","=","mpago.id")
                        ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                        ->where([
                            "det_soli.token_detalle_soli" => $token_det_soli,
                            "soli.token_solicitud_cfdi" => $tkn_soli_cfdi,
                        ])->get();
                                
                        $mpago_token = $mPagoDetCFDI[0]->token_metodopago;	
                        $mpago_abrev = $mPagoDetCFDI[0]->abrev;
                        $mpago_metodo = $mPagoDetCFDI[0]->metodo;
                        
                        if ($vDet->uso_cfdi != NULL) {
                            $usoCDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                            ->join("teci_uso_cfdi AS uso","det_soli.uso_cfdi","=","uso.id")
                            ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                            ->where([
                                "det_soli.token_detalle_soli" => $token_det_soli,
                                "soli.token_solicitud_cfdi" => $tkn_soli_cfdi,
                            ])->get();
                                    
                            $usoCfdi_token = $usoCDetCFDI[0]->token_uso_cfdi;	
                            $usoCfdi_clave = $usoCDetCFDI[0]->clave_uso;
                            $usoCfdi_uso = $usoCDetCFDI[0]->uso_cfdi;
                            $usoCfdi_descripcion = $usoCDetCFDI[0]->descripcion_cfdi;
                        } else {
                            $usoCfdi_token = "";	
                            $usoCfdi_clave = "";
                            $usoCfdi_uso = "";
                            $usoCfdi_descripcion = "";
                        }
                        
                        $val_import1 = floatval($vDet->precio_unitario) * floatval($vDet->cantidad);
                        $val_import2 = floatval($val_import1) - floatval($vDet->descuento);
                        $inicial_iva = floatval($vDet->iva);
                        $iva_row = floatval("0.00");
                        $importe_row = floatval("0.00");
                        //percent cant
                        if ($vDet->type_iva == "percent") {
                          $ammount_iva = floatval($val_import2) * floatval("0.".$inicial_iva);
                          $iva_row = floatval($iva_row) + floatval($ammount_iva);
                          $importe_row = floatval($val_import2) + floatval($ammount_iva);
                        } else {
                          $importe_row = floatval($val_import2) + floatval($inicial_iva);
                          $iva_row = floatval($iva_row) + floatval($vDet->iva);
                        }

                        $iva_final = floatval($iva_final) + floatval($iva_row);
                        $importe_final = floatval($importe_final) + floatval($importe_row);
                    }
                
                    $sql_iva = DB::select("SELECT FORMAT(?,2) AS final_format",[$iva_final]);
                    $final_imp_iva = $sql_iva[0]->final_format;
                    $sql_importe = DB::select("SELECT FORMAT(?,2) AS final_format",[$importe_final]);
                    $final_importe = $sql_importe[0]->final_format;
                
                    $row = array(
                        "token_cfdi" => $token_cfdi,	
                        "folio_cfdi" => $folio_cfdi,
                        "fecha_solicitud" => $fecha_solicitud,
                        "name_emisor" => $name_emisor,
                        "rfc_gen_emi" => $rfc_gen_emi,
			            "rfc_emp_emi" => $rfc_emp_emi,
                        "taxid_emp_emi" => $taxid_emp_emi,
                        "nombreEmiPers" => $nombreEmiPers,
                        "tkn_emp_fact" => $tkn_emp_fact,
                        "name_emp_fact" => $name_emp_fact,
                        "rfc_gen_fact" => $rfc_gen_fact,
			            "rfc_emp_fact" => $rfc_emp_fact,
			            "taxid_emp_fact" => $taxid_emp_fact,
                        "email_referencia" => $email_referencia,	
                        "tipo_factura" => $tipo_factura,
                        "iva_total" => "$".$final_imp_iva,
                        "importe_total" => "$".$final_importe,
                    );
                    $arraySoliCFDI[] = $row;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'listSoliCFDI' => $arraySoliCFDI,
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function detalleSolicitudCFDI(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayCFDI = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "token_back_ter" => "required|string",
                "token_cfdi" => "required|string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"],true);
                $token_cfdi = $parametrosArray["token_cfdi"];
                
                $cfdi_main_selected = CfdiModelo::join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                ->join("vhum_personal AS pers","sos_cfdi_main.user_emisor","=","pers.id")
                ->join("usuarios AS users","pers.usuario","=","users.id")
                ->where([
                    "sos_cfdi_main.token_cfdi" => $token_cfdi,
                    "sos_cfdi_main.registro_cfdi" => "so",
                    "sos_cfdi_main.status_cfdi" => TRUE,
                    "emp.emp_token" => $usuario->emp_token,
                    "users.user_token" => $usuario->user_token
                ])->get();
                
                foreach ($cfdi_main_selected as $vsoli) {
                    //da_te_default_timezone_set($vsoli->zona_horaria);
                    $fecha_registro = date('d-m-Y H:i:s',$vsoli->fecha_sistema);
                    
                    $old_versions = array();
                    $last_version = array();
                    $soli_cancelArray = array();
                    $list_cancelaciones = array();
                    
                    if ($vsoli->post_folio_cfdi == NULL) {
                        $folio_cfdi = 'CFDI-'.$JwtAuth->generarFolio($vsoli->folio_cfdi);
                    } else {
                        $folio_cfdi = 'CFDI-'.$JwtAuth->generarFolio($vsoli->folio_cfdi).'-'.$vsoli->post_folio_cfdi;
                    }
                    
                    //emisor
                        $selectNameEmpEmi = CfdiModelo::join("main_empresas AS emp","sos_cfdi_main.emisor","=","emp.id")
                        ->join("sos_personas AS people","emp.persona","=","people.id")
                        ->where(["sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi])->get();
                
                        foreach ($selectNameEmpEmi as $vEmisor) {
                            if ($vEmisor->denominacion_rs == '') {
                                $name_emisor = $JwtAuth->desencriptar($vEmisor->paterno).
                                    " ".$JwtAuth->desencriptar($vEmisor->materno).
                                    " ".$JwtAuth->desencriptar($vEmisor->nombre);
                            } else {
                                $name_emisor = $JwtAuth->desencriptar($vEmisor->denominacion_rs);
                            }
                        
                            $rfc_gen_emi = $vEmisor->rfc_generico;
                    
                            if ($vEmisor->rfc != NULL) {
                                $rfc_emp_emi = $JwtAuth->desencriptar($vEmisor->rfc);
                            } else {
                                $rfc_emp_emi = "---";
                            }
                            
                            if ($vEmisor->tax_id != NULL) {
                                $taxid_emp_emi = $JwtAuth->desencriptar($vEmisor->tax_id);
                            } else {
                                $taxid_emp_emi = "---";
                            }
                        }
                    
                        $selectPersEmpEmi = CfdiModelo::join("vhum_personal AS pers","sos_cfdi_main.user_emisor","=","pers.id")
                        ->join("sos_personas AS people","pers.personal","=","people.id")
                        ->where(["sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi])->get();
                        
                        foreach ($selectPersEmpEmi as $vPemi) {
                            $nombreEmiPers = $JwtAuth->desencriptar($vPemi->paterno).
                                " ".$JwtAuth->desencriptar($vPemi->materno).
                                " ".$JwtAuth->desencriptar($vPemi->nombre);
                        }
                    
                    //receptor 
                        $selectNameEmpRec = CfdiModelo::join("main_empresas AS emp","sos_cfdi_main.receptor","=","emp.id")
                        ->join("sos_personas AS people","emp.persona","=","people.id")
                        ->where(["sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi])->get();
                        
                        $txt_folio_solicitud = "0";
                
                        foreach ($selectNameEmpRec as $vReceptor) {
                            $tkn_receptor = $vReceptor->emp_token;
                            if ($vReceptor->denominacion_rs == '') {
                                $name_receptor = $JwtAuth->desencriptar($vReceptor->paterno).
                                    " ".$JwtAuth->desencriptar($vReceptor->materno).
                                    " ".$JwtAuth->desencriptar($vReceptor->nombre);
                            } else {
                                $name_receptor = $JwtAuth->desencriptar($vReceptor->denominacion_rs);
                            }
                        
                            $rfc_gen_receptor = $vReceptor->rfc_generico;
                    
                            if ($vReceptor->rfc != NULL) {
                                $rfc_emp_receptor = $JwtAuth->desencriptar($vReceptor->rfc);
                            } else {
                                $rfc_emp_receptor = "---";
                            }
                            
                            if ($vReceptor->tax_id != NULL) {
                                $taxid_emp_receptor = $JwtAuth->desencriptar($vReceptor->tax_id);
                            } else {
                                $taxid_emp_receptor = "---";
                            }
                        }
                    
                        $selectPersEmpReceptor = CfdiModelo::join("vhum_personal AS pers","sos_cfdi_main.user_receptor","=","pers.id")
                        ->join("sos_personas AS people","pers.personal","=","people.id")
                        ->where(["sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi])->get();
                        
                        foreach ($selectPersEmpReceptor as $vPrec) {
                            $nombreReceptorPers = $JwtAuth->desencriptar($vPrec->paterno).
                                " ".$JwtAuth->desencriptar($vPrec->materno).
                                " ".$JwtAuth->desencriptar($vPrec->nombre);
                        }
                        
                    //last_version
                        $soli_last_version = CfdiModelo::join("sos_cfdi_solicitud AS soli_cfdi","sos_cfdi_main.id","=","soli_cfdi.cfdi_main")
                        ->join("ingr_catalogo_clientes AS cKli","soli_cfdi.cliente","=","cKli.id")
                        ->join("sos_personas AS client","cKli.cliente","=","client.id")
                        ->where([
                            "sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi,
                            "sos_cfdi_main.registro_cfdi" => "so",
                            "sos_cfdi_main.status_cfdi" => TRUE,
                            "soli_cfdi.valid_version" => TRUE,
                            "soli_cfdi.status_emision" => TRUE
                        ])->get();
                        //echo count($soli_last_version);
                        foreach ($soli_last_version as $vLast) {
                            $tkn_solicitud_cfdi = $vLast->token_solicitud_cfdi;
                            $txt_folio_solicitud = $vLast->folio_solicitud;
                            $all_folio_solicitud = $JwtAuth->generarFolio($vLast->folio_solicitud);
                            $fecha_solicitud = date('d-m-Y H:i:s',$vLast->fecha_solicitud);
                            
                            $iva_final = 0;
                            $importe_final = 0;
                            
                            $tkn_cliente = $vLast->token_cat_clientes;
                            if ($vLast->denominacion_rs == '') {
                                $name_cliente = $JwtAuth->desencriptar($vLast->paterno).
                                    " ".$JwtAuth->desencriptar($vLast->materno).
                                    " ".$JwtAuth->desencriptar($vLast->nombre);
                            } else {
                                $name_cliente = $JwtAuth->desencriptar($vLast->denominacion_rs);
                            }
                        
                            $rfc_generico_cliente = $vLast->rfc_generico;
                  
                            if ($vLast->rfc != NULL) {
                                $rfc_cliente = $JwtAuth->desencriptar($vLast->rfc);
                            } else {
                                $rfc_cliente = "---";
                            }
                        
                            if ($vLast->tax_id != NULL) {
                                $taxid_cliente = $JwtAuth->desencriptar($vLast->tax_id);
                            } else {
                                $taxid_cliente = "---";
                            }
                            
                            $email_referencia = $JwtAuth->desencriptar($vLast->email_referencia);	
                            
                            if ($vLast->fact_pagada == TRUE) {
                                $fact_pagada = true;
                                $tentativa_pago = $JwtAuth->convierteEpocFechaHtml($vLast->zona_horaria,$vLast->tentativa_pago);
                                $mes_venta = $JwtAuth->convierteEpocFechaHtmlMY($vLast->zona_horaria,$vLast->mes_venta);
                                $importe_venta = floatval($vLast->importe_venta);
                            } else {
                                $fact_pagada = false;
                                //$.tentativa_pago = null;
                                $tentativa_pago = "";	
                                //$.mes_venta = null;
                                $mes_venta = "";
                                $importe_venta = floatval("0.00");   
                            }
                            
                            $tipo_factura = $vLast->tipo_factura;
                            
                            //detalle_solicfdi
                            $detalle_solicfdi = array();
                            $selectDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                            ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                            ->join("sos_cfdi_main AS main","det_soli.cfdi_main","=","main.id")
                            ->where([
                                "main.token_cfdi" => $token_cfdi,
                                "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                            ])->get();
                            //echo count($selectDetCFDI);
                            foreach ($selectDetCFDI as $vDet) {
                                $token_det_soli = $vDet->token_detalle_soli;
                                if ($vDet->clave_sat != NULL) {
                                    $satKeyDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                    ->join("teci_catalogo_prodservsat AS sat","det_soli.clave_sat","=","sat.id")
                                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud","=","soli.id")
                                    ->where([
                                        "det_soli.token_detalle_soli" => $token_det_soli,
                                        "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                    ])->get();
                                                
                                    $sat_token = $satKeyDetCFDI[0]->token_prodservsat;	
                                    $sat_clave = $satKeyDetCFDI[0]->clave;
                                    $sat_descripcion = $satKeyDetCFDI[0]->descripcion;
                                    
                                } else {
                                    $sat_token = "";
                                    $sat_clave = "";
                                    $sat_descripcion = "";
                                }
                                    
                                if ($vDet->unidad_medida != NULL) {
                                    $satMedDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                    ->join("teci_unidad_medida AS med","det_soli.unidad_medida","=","med.id")
                                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                    ->where([
                                        "det_soli.token_detalle_soli" => $token_det_soli,
                                        "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                    ])->get();
                                            
                                    $uni_med_token = $satMedDetCFDI[0]->token_unidad_medida;	
                                    $uni_medida = $satMedDetCFDI[0]->unidad_medida;
                                    $uni_med_clave = $satMedDetCFDI[0]->sat_clave;
                                } else {
                                    $uni_med_token = "";	
                                    $uni_medida = "";
                                    $uni_med_clave = "";
                                }
                                
                                $fPagoDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                ->join("teci_forma_pago AS fpago","det_soli.forma_pago","=","fpago.id")
                                ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                ->where([
                                    "det_soli.token_detalle_soli" => $token_det_soli,
                                    "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                ])->get();
                                            
                                $fpago_token = $fPagoDetCFDI[0]->token_formapago;	
                                $fpago_clave = $fPagoDetCFDI[0]->clave;
                                $fpago_forma = $fPagoDetCFDI[0]->forma;
                                
                                $mPagoDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                ->join("teci_metodo_pago AS mpago","det_soli.metodo_pago","=","mpago.id")
                                ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                ->where([
                                    "det_soli.token_detalle_soli" => $token_det_soli,
                                    "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                ])->get();
                                        
                                $mpago_token = $mPagoDetCFDI[0]->token_metodopago;	
                                $mpago_abrev = $mPagoDetCFDI[0]->abrev;
                                $mpago_metodo = $mPagoDetCFDI[0]->metodo;
                                
                                if ($vDet->uso_cfdi != NULL) {
                                    $usoCDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                    ->join("teci_uso_cfdi AS uso","det_soli.uso_cfdi","=","uso.id")
                                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                    ->where([
                                        "det_soli.token_detalle_soli" => $token_det_soli,
                                        "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                    ])->get();
                                            
                                    $usoCfdi_token = $usoCDetCFDI[0]->token_uso_cfdi;	
                                    $usoCfdi_clave = $usoCDetCFDI[0]->clave_uso;
                                    $usoCfdi_uso = $usoCDetCFDI[0]->uso_cfdi;
                                    $usoCfdi_descripcion = $usoCDetCFDI[0]->descripcion_cfdi;
                                } else {
                                    $usoCfdi_token = "";	
                                    $usoCfdi_clave = "";
                                    $usoCfdi_uso = "";
                                    $usoCfdi_descripcion = "";
                                }
                                
                                $precio_unitario = floatval($vDet->precio_unitario);
                                
                                $sql_descuento = DB::select("SELECT FORMAT(?,2) AS final_format",[floatval($vDet->descuento)]);
                                $descuento = $sql_descuento[0]->final_format;
                                //echo $descuento;
                                //$descuento = floatval($vDet->descuento);
                                $val_import1 = $precio_unitario * floatval($vDet->cantidad);
                                $val_import2 = floatval($val_import1) - $descuento;
                                
                                $inicial_iva = floatval($vDet->iva);
                                $iva_row = floatval("0.00");
                                $importe_row = floatval("0.00");
                                //percent cant
                                if ($vDet->type_iva == "percent") {
                                  $ammount_iva = floatval($val_import2) * floatval("0.".$inicial_iva);
                                  $iva_row = floatval($iva_row) + floatval($ammount_iva);
                                  $importe_row = floatval($val_import2) + floatval($ammount_iva);
                                } else {
                                  $importe_row = floatval($val_import2) + floatval($vDet->iva);
                                  $iva_row = floatval($iva_row) + floatval($inicial_iva);
                                }
            
                                $iva_final = floatval($iva_final) + floatval($iva_row);
                                $importe_final = floatval($importe_final) + floatval($importe_row);
                                //$importe_total = floatval(val_import_final).toFixed(2);
                                
                                $rowDet = array(
                                    "token_detalle_soli" => $token_det_soli,
                                    //clave_sat
                                    "sat_token" => $sat_token,
                                    "sat_clave" => $sat_clave,
                                    "sat_descripcion" => $sat_descripcion,
                                    //unidad_medida
                                    "uni_med_token" => $uni_med_token,	
                                    "uni_medida" => $uni_medida,
                                    "uni_med_clave" => $uni_med_clave,
                                    //cantidades
                                    "cantidad" => $vDet->cantidad,
                                    "descuento"	=> $descuento,
                                    "short_descripcion"	=> $JwtAuth->desencriptar($vDet->short_descripcion),
                                    "large_descripcion"	=> $JwtAuth->desencriptar($vDet->large_descripcion),
                                    //forma_pago
                                    "fpago_token" => $fpago_token,	
                                    "fpago_clave" => $fpago_clave,
                                    "fpago_forma" => $fpago_forma,
                                    //metodo_pago
                                    "mpago_token" => $mpago_token,	
                                    "mpago_abrev" => $mpago_abrev,
                                    "mpago_metodo" => $mpago_metodo,
                                    //uso_cfdi
                                    "usoCfdi_token" => $usoCfdi_token,	
                                    "usoCfdi_clave" => $usoCfdi_clave,
                                    "usoCfdi_uso" => $usoCfdi_uso,
                                    "usoCfdi_descripcion" => $usoCfdi_descripcion,
                                    //precio
                                    "precio_unitario" => $precio_unitario,
                                    "type_iva" => $vDet->type_iva,
                                    "iva" => $inicial_iva,
                                );
                                $detalle_solicfdi[] = $rowDet;
                            }    
                            
                            $sql_iva = DB::select("SELECT FORMAT(?,2) AS final_format",[$iva_final]);
                            $final_imp_iva = $sql_iva[0]->final_format;
                            $sql_importe = DB::select("SELECT FORMAT(?,2) AS final_format",[$importe_final]);
                            $final_importe = $sql_importe[0]->final_format;
                            
                            $listAnexos = array();
                            $selectAnexosCFDI = DB::table("cfdi_docs AS docs")
                            ->join("sos_cfdi_main AS main","docs.cfdi_main","=","main.id")
                            ->join("sos_cfdi_solicitud AS soli","docs.solicitud_cfdi","=","soli.id")
                            ->where([
                                "docs.tipo_doc" => "an",
                                "main.token_cfdi" => $token_cfdi,
                                "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                            ])->get();
                            
                            foreach ($selectAnexosCFDI as $vDoc) {
                                $token_docs = $vDoc->token_doc_cfdi;
                                $tipo_doc = $vDoc->tipo_doc;
                                $ext_doc = $vDoc->ext_doc;
                                $name_documento = $JwtAuth->desencriptar($vDoc->name_documento);	
                                
                                $filepath = $vsoli->root_tkn."/0009-cfdi/".$folio_cfdi."/version-".$all_folio_solicitud."/anexos";
                                $archivo = Storage::path('public/root/'.$filepath.'/'.$name_documento);
                                $extension = pathinfo($archivo, PATHINFO_EXTENSION);
                                
                                if ($extension == 'pdf') {
                                    $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                                    $html = '<iframe src="'.$base64.'" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
                                }
                    
                                if ($extension == 'jpg' || $extension == 'png') {
                                    $base64 = $JwtAuth->encriptaBase64($archivo);
                                    $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="'.$base64.'">';
                                }
                    
                                $rowDet = array(
                                    "token_docs" => $token_docs,
                                    "ext_doc" => $extension,
                                    "name_documento" => $name_documento,	
                                    "html" => $html,
                                );
                                $listAnexos[] = $rowDet;
                            }     
                            
                            $rowLast = array(
                                "token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                "txt_folio_solicitud" => $txt_folio_solicitud,
                                "all_folio_solicitud" => $all_folio_solicitud,
                                "fecha_solicitud" => $fecha_solicitud,
                                //cliente
                                    "tkn_cliente" => $tkn_cliente,
                                    "name_cliente" => $name_cliente,
                                    "rfc_generico_cliente" => $rfc_generico_cliente,
                                    "rfc_cliente" => $rfc_cliente,
                                    "taxid_cliente" => $taxid_cliente,
                                "email_referencia" => $email_referencia,
                                "fact_pagada" => $fact_pagada,
                                "tentativa_pago" => $tentativa_pago,
                                "mes_venta" => $mes_venta,
                                "importe_venta" => $importe_venta,
                                "tipo_factura" => $tipo_factura,
                                "detalle_solicfdi" => $detalle_solicfdi,
                                "tipo_factura" => $tipo_factura,
                                "iva_total" => "$".$final_imp_iva,
                                "importe_total" => "$".$final_importe,
                                "listAnexos" => $listAnexos,
                            );
                            $last_version[] = $rowLast;
                        }      
                        
                    //old_versions
                        $soli_old_versions = CfdiModelo::join("sos_cfdi_solicitud AS soli_cfdi","sos_cfdi_main.id","=","soli_cfdi.cfdi_main")
                        ->join("ingr_catalogo_clientes AS cKli","soli_cfdi.cliente","=","cKli.id")
                        ->join("sos_personas AS client","cKli.cliente","=","client.id")
                        ->where([
                            "sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi,
                            "sos_cfdi_main.registro_cfdi" => "so",
                            "sos_cfdi_main.status_cfdi" => TRUE,
                            "soli_cfdi.valid_version" => FALSE,
                            "soli_cfdi.status_emision" => TRUE
                        ])->orderBy('soli_cfdi.folio_solicitud','DESC')->get();
                        //echo count($soli_old_versions);
                        foreach ($soli_old_versions as $vOld) {
                            $tkn_solicitud_cfdi = $vOld->token_solicitud_cfdi;
                            $all_folio_solicitud = $JwtAuth->generarFolio($vOld->folio_solicitud);
                            $fecha_solicitud = date('d-m-Y H:i:s',$vOld->fecha_solicitud);
                            
                            $iva_final = 0;
                            $importe_final = 0;
                            
                            $tkn_cliente = $vOld->token_cat_clientes;
                            if ($vOld->denominacion_rs == '') {
                                $name_cliente = $JwtAuth->desencriptar($vOld->paterno).
                                    " ".$JwtAuth->desencriptar($vOld->materno).
                                    " ".$JwtAuth->desencriptar($vOld->nombre);
                            } else {
                                $name_cliente = $JwtAuth->desencriptar($vOld->denominacion_rs);
                            }
                        
                            $rfc_generico_cliente = $vOld->rfc_generico;
                  
                            if ($vOld->rfc != NULL) {
                                $rfc_cliente = $JwtAuth->desencriptar($vOld->rfc);
                            } else {
                                $rfc_cliente = "---";
                            }
                        
                            if ($vOld->tax_id != NULL) {
                                $taxid_cliente = $JwtAuth->desencriptar($vOld->tax_id);
                            } else {
                                $taxid_cliente = "---";
                            }
                            
                            $email_referencia = $JwtAuth->desencriptar($vOld->email_referencia);	
                            
                            if ($vOld->fact_pagada == TRUE) {
                                $fact_pagada = true;
                                $tentativa_pago = $JwtAuth->convierteEpocFechaHtml($vOld->zona_horaria,$vOld->tentativa_pago);
                                $mes_venta = $JwtAuth->convierteEpocFechaHtmlMY($vOld->zona_horaria,$vOld->mes_venta);
                                $importe_venta = floatval($vOld->importe_venta);
                            } else {
                                $fact_pagada = false;	
                                $tentativa_pago = "";	
                                $mes_venta = "";	
                                $importe_venta = floatval("0.00");   
                            }
                            
                            $tipo_factura = $vOld->tipo_factura;
                            
                            //detalle_solicfdi
                            $detalle_solicfdi = array();
                            $selectDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                            ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                            ->join("sos_cfdi_main AS main","det_soli.cfdi_main","=","main.id")
                            ->where([
                                "main.token_cfdi" => $token_cfdi,
                                "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                            ])->get();
                            //echo count($selectDetCFDI);
                            foreach ($selectDetCFDI as $vDet) {
                                $token_det_soli = $vDet->token_detalle_soli;
                                if ($vDet->clave_sat != NULL) {
                                    $satKeyDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                    ->join("teci_catalogo_prodservsat AS sat","det_soli.clave_sat","=","sat.id")
                                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud","=","soli.id")
                                    ->where([
                                        "det_soli.token_detalle_soli" => $token_det_soli,
                                        "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                    ])->get();
                                                
                                    $sat_token = $satKeyDetCFDI[0]->token_prodservsat;	
                                    $sat_clave = $satKeyDetCFDI[0]->clave;
                                    $sat_descripcion = $satKeyDetCFDI[0]->descripcion;
                                    
                                } else {
                                    $sat_token = "";
                                    $sat_clave = "";
                                    $sat_descripcion = "";
                                }
                                    
                                if ($vDet->unidad_medida != NULL) {
                                    $satMedDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                    ->join("teci_unidad_medida AS med","det_soli.unidad_medida","=","med.id")
                                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                    ->where([
                                        "det_soli.token_detalle_soli" => $token_det_soli,
                                        "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                    ])->get();
                                            
                                    $uni_med_token = $satMedDetCFDI[0]->token_unidad_medida;	
                                    $uni_medida = $satMedDetCFDI[0]->unidad_medida;
                                    $uni_med_clave = $satMedDetCFDI[0]->sat_clave;
                                } else {
                                    $uni_med_token = "";	
                                    $uni_medida = "";
                                    $uni_med_clave = "";
                                }
                                
                                $fPagoDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                ->join("teci_forma_pago AS fpago","det_soli.forma_pago","=","fpago.id")
                                ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                ->where([
                                    "det_soli.token_detalle_soli" => $token_det_soli,
                                    "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                ])->get();
                                            
                                $fpago_token = $fPagoDetCFDI[0]->token_formapago;	
                                $fpago_clave = $fPagoDetCFDI[0]->clave;
                                $fpago_forma = $fPagoDetCFDI[0]->forma;
                                
                                $mPagoDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                ->join("teci_metodo_pago AS mpago","det_soli.metodo_pago","=","mpago.id")
                                ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                ->where([
                                    "det_soli.token_detalle_soli" => $token_det_soli,
                                    "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                ])->get();
                                        
                                $mpago_token = $mPagoDetCFDI[0]->token_metodopago;	
                                $mpago_abrev = $mPagoDetCFDI[0]->abrev;
                                $mpago_metodo = $mPagoDetCFDI[0]->metodo;
                                
                                if ($vDet->uso_cfdi != NULL) {
                                    $usoCDetCFDI = DB::table("sos_cfdi_detalle_solicitud AS det_soli")
                                    ->join("teci_uso_cfdi AS uso","det_soli.uso_cfdi","=","uso.id")
                                    ->join("sos_cfdi_solicitud AS soli","det_soli.solicitud_cfdi","=","soli.id")
                                    ->where([
                                        "det_soli.token_detalle_soli" => $token_det_soli,
                                        "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                    ])->get();
                                            
                                    $usoCfdi_token = $usoCDetCFDI[0]->token_uso_cfdi;	
                                    $usoCfdi_clave = $usoCDetCFDI[0]->clave_uso;
                                    $usoCfdi_uso = $usoCDetCFDI[0]->uso_cfdi;
                                    $usoCfdi_descripcion = $usoCDetCFDI[0]->descripcion_cfdi;
                                } else {
                                    $usoCfdi_token = "";	
                                    $usoCfdi_clave = "";
                                    $usoCfdi_uso = "";
                                    $usoCfdi_descripcion = "";
                                }
                                
                                $precio_unitario = floatval($vDet->precio_unitario);
                                
                                $sql_descuento = DB::select("SELECT FORMAT(?,2) AS final_format",[floatval($vDet->descuento)]);
                                $descuento = $sql_descuento[0]->final_format;
                                //echo $descuento;
                                //$descuento = floatval($vDet->descuento);
                                $val_import1 = $precio_unitario * floatval($vDet->cantidad);
                                $val_import2 = floatval($val_import1) - $descuento;
                                
                                $inicial_iva = floatval($vDet->iva);
                                $iva_row = floatval("0.00");
                                $importe_row = floatval("0.00");
                                //percent cant
                                if ($vDet->type_iva == "percent") {
                                  $ammount_iva = floatval($val_import2) * floatval("0.".$inicial_iva);
                                  $iva_row = floatval($iva_row) + floatval($ammount_iva);
                                  $importe_row = floatval($val_import2) + floatval($ammount_iva);
                                } else {
                                  $importe_row = floatval($val_import2) + floatval($vDet->iva);
                                  $iva_row = floatval($iva_row) + floatval($inicial_iva);
                                }
            
                                $iva_final = floatval($iva_final) + floatval($iva_row);
                                $importe_final = floatval($importe_final) + floatval($importe_row);
                                //$importe_total = floatval(val_import_final).toFixed(2);
                                
                                $rowDet = array(
                                    "token_detalle_soli" => $token_det_soli,
                                    //clave_sat
                                    "sat_token" => $sat_token,
                                    "sat_clave" => $sat_clave,
                                    "sat_descripcion" => $sat_descripcion,
                                    //unidad_medida
                                    "uni_med_token" => $uni_med_token,	
                                    "uni_medida" => $uni_medida,
                                    "uni_med_clave" => $uni_med_clave,
                                    //cantidades
                                    "cantidad" => $vDet->cantidad,
                                    "descuento"	=> $descuento,
                                    "short_descripcion"	=> $JwtAuth->desencriptar($vDet->short_descripcion),
                                    "large_descripcion"	=> $JwtAuth->desencriptar($vDet->large_descripcion),
                                    //forma_pago
                                    "fpago_token" => $fpago_token,	
                                    "fpago_clave" => $fpago_clave,
                                    "fpago_forma" => $fpago_forma,
                                    //metodo_pago
                                    "mpago_token" => $mpago_token,	
                                    "mpago_abrev" => $mpago_abrev,
                                    "mpago_metodo" => $mpago_metodo,
                                    //uso_cfdi
                                    "usoCfdi_token" => $usoCfdi_token,	
                                    "usoCfdi_clave" => $usoCfdi_clave,
                                    "usoCfdi_uso" => $usoCfdi_uso,
                                    "usoCfdi_descripcion" => $usoCfdi_descripcion,
                                    //precio
                                    "precio_unitario" => $precio_unitario,
                                    "type_iva" => $vDet->type_iva,
                                    "iva" => $inicial_iva,
                                );
                                $detalle_solicfdi[] = $rowDet;
                            }    
                            
                            $sql_iva = DB::select("SELECT FORMAT(?,2) AS final_format",[$iva_final]);
                            $final_imp_iva = $sql_iva[0]->final_format;
                            $sql_importe = DB::select("SELECT FORMAT(?,2) AS final_format",[$importe_final]);
                            $final_importe = $sql_importe[0]->final_format;
                            
                            $listAnexos = array();
                            $selectAnexosCFDI = DB::table("cfdi_docs AS docs")
                            ->join("sos_cfdi_main AS main","docs.cfdi_main","=","main.id")
                            ->join("sos_cfdi_solicitud AS soli","docs.solicitud_cfdi","=","soli.id")
                            ->where([
                                "docs.tipo_doc" => "an",
                                "main.token_cfdi" => $token_cfdi,
                                "soli.token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                            ])->get();
                            
                            foreach ($selectAnexosCFDI as $vDoc) {
                                $token_docs = $vDoc->token_doc_cfdi;
                                $tipo_doc = $vDoc->tipo_doc;
                                $ext_doc = $vDoc->ext_doc;
                                $name_documento = $JwtAuth->desencriptar($vDoc->name_documento);	
                                
                                $filepath = $vsoli->root_tkn."/0009-cfdi/".$folio_cfdi."/version-".$all_folio_solicitud."/anexos";
                                $archivo = Storage::path('public/root/'.$filepath.'/'.$name_documento);
                                $extension = pathinfo($archivo, PATHINFO_EXTENSION);
                                
                                if ($extension == 'pdf') {
                                    $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                                    $html = '<iframe src="'.$base64.'" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
                                }
                    
                                if ($extension == 'jpg' || $extension == 'png') {
                                    $base64 = $JwtAuth->encriptaBase64($archivo);
                                    $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="'.$base64.'">';
                                }
                    
                                $rowDet = array(
                                    "token_docs" => $token_docs,
                                    "ext_doc" => $extension,
                                    "name_documento" => $name_documento,	
                                    "html" => $html,
                                );
                                $listAnexos[] = $rowDet;
                            }     
                            
                            $rowOld = array(
                                "token_solicitud_cfdi" => $tkn_solicitud_cfdi,
                                "all_folio_solicitud" => $all_folio_solicitud,
                                "fecha_solicitud" => $fecha_solicitud,
                                //cliente
                                    "tkn_cliente" => $tkn_cliente,
                                    "name_cliente" => $name_cliente,
                                    "rfc_generico_cliente" => $rfc_generico_cliente,
                                    "rfc_cliente" => $rfc_cliente,
                                    "taxid_cliente" => $taxid_cliente,
                                "email_referencia" => $email_referencia,
                                "fact_pagada" => $fact_pagada,
                                "tentativa_pago" => $tentativa_pago,
                                "mes_venta" => $mes_venta,
                                "importe_venta" => $importe_venta,
                                "tipo_factura" => $tipo_factura,
                                "detalle_solicfdi" => $detalle_solicfdi,
                                "tipo_factura" => $tipo_factura,
                                "iva_total" => "$".$final_imp_iva,
                                "importe_total" => "$".$final_importe,
                                "listAnexos" => $listAnexos,
                            );
                            $old_versions[] = $rowOld;
                        }
                        
                    //solicitud_cancelaciones
                        $soli_cancelaciones = CfdiModelo::join("cfdi_cancelaciones AS soli_can","sos_cfdi_main.id","=","soli_can.cfdi_main")
                        ->join("cfdi_motivos_cancelacion AS m_can","soli_can.motivo_cancelacion","=","m_can.id")
                        ->where([
                            "soli_can.autorizacion" => FALSE,
                            "sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi,
                            "sos_cfdi_main.registro_cfdi" => "so",
                            "sos_cfdi_main.status_cfdi" => TRUE
                        ])->get();
                        //echo count($soli_old_versions);
                        foreach ($soli_cancelaciones as $vCan) {
                            $tkn_cancelacion = $vCan->token_cancelacion;
                            $folio_cancelacion = $JwtAuth->generarFolio($vCan->folio_cancelacion);
                            $fecha_cancelacion = date('d-m-Y H:i:s',$vCan->fecha_cancelacion);
                            
                            $older_folio = "";
                            $replace_folio = "";
                            
                            //solicitud_cfdi_old
                            if ($vCan->solicitud_cfdi_old != NULL) {
                                $querySoliOld = DB::table("cfdi_cancelaciones AS cancel") 
                                ->join("sos_cfdi_solicitud AS soli_cfdi","cancel.solicitud_cfdi_old","=","soli_cfdi.id")
                                ->where(["cancel.token_cancelacion" => $tkn_cancelacion])->get();
                                        
                                $older_folio = $JwtAuth->generarFolio($querySoliOld[0]->folio_solicitud);
                            }
                            
                            //solicitud_cfdi_new
                            if ($vCan->solicitud_cfdi_new != NULL) {
                                $querySoliReplace = DB::table("cfdi_cancelaciones AS cancel") 
                                ->join("sos_cfdi_solicitud AS soli_cfdi","cancel.solicitud_cfdi_new","=","soli_cfdi.id")
                                ->where(["cancel.token_cancelacion" => $tkn_cancelacion])->get();
                                        
                                $replace_folio = $JwtAuth->generarFolio($querySoliReplace[0]->folio_solicitud);
                            }
                            
                            //motivo_cancelacion
                            $cancelation_token = $vCan->token_clave;
                            $cancelation_clave = $vCan->clave;	
                            $cancelation_motivo = $vCan->motivo;
                            $cancelation_descripcion = $vCan->descripcion;
                            $cancelation_accion = $vCan->accion;
                            
                            $detalle_motivo_cancelacion = $JwtAuth->desencriptar($vCan->detalle_motivo_cancelacion);
                            
                            $rowOld = array(
                                "tkn_cancelacion" => $tkn_cancelacion,
                                "folio_cancelacion" => $folio_cancelacion,
                                "fecha_cancelacion" => $fecha_cancelacion,
                                //solicitud_cfdi_old
                                    "older_folio" => $older_folio,
                                //solicitud_cfdi_new
                                    "replace_folio" => $replace_folio,
                                //motivo_cancelacion
                                    "cancelation_token" => $cancelation_token,
                                    "cancelation_clave" => $cancelation_clave,	
                                    "cancelation_motivo" => $cancelation_motivo,
                                    "cancelation_descripcion" => $cancelation_descripcion,
                                    "cancelation_accion" => $cancelation_accion,
                                "detalle_motivo_cancelacion" => $detalle_motivo_cancelacion,
                            );
                            $soli_cancelArray[] = $rowOld;
                        } 
                        
                    //cancelaciones_autorizadas
                        $auth_cancelaciones = CfdiModelo::join("cfdi_cancelaciones AS soli_can","sos_cfdi_main.id","=","soli_can.cfdi_main")
                        ->join("cfdi_motivos_cancelacion AS m_can","soli_can.motivo_cancelacion","=","m_can.id")
                        ->where([
                            "soli_can.autorizacion" => TRUE,
                            "sos_cfdi_main.token_cfdi" => $vsoli->token_cfdi,
                            "sos_cfdi_main.registro_cfdi" => "so",
                            "sos_cfdi_main.status_cfdi" => TRUE
                        ])->get();
                        //echo count($soli_old_versions);
                        foreach ($auth_cancelaciones as $vCan) {
                            $tkn_cancelacion = $vCan->token_cancelacion;
                            $folio_cancelacion = $JwtAuth->generarFolio($vCan->folio_cancelacion);
                            $fecha_cancelacion = date('d-m-Y H:i:s',$vCan->fecha_cancelacion);
                            
                            $older_folio = "";
                            $replace_folio = "";
                            
                            //solicitud_cfdi_old
                            if ($vCan->solicitud_cfdi_old != NULL) {
                                $querySoliOld = DB::table("cfdi_cancelaciones AS cancel") 
                                ->join("sos_cfdi_solicitud AS soli_cfdi","cancel.solicitud_cfdi_old","=","soli_cfdi.id")
                                ->where(["cancel.token_cancelacion" => $tkn_cancelacion])->get();
                                        
                                $older_folio = $JwtAuth->generarFolio($querySoliOld[0]->folio_solicitud);
                            }
                            
                            //solicitud_cfdi_new
                            if ($vCan->solicitud_cfdi_new != NULL) {
                                $querySoliReplace = DB::table("cfdi_cancelaciones AS cancel") 
                                ->join("sos_cfdi_solicitud AS soli_cfdi","cancel.solicitud_cfdi_new","=","soli_cfdi.id")
                                ->where(["cancel.token_cancelacion" => $tkn_cancelacion])->get();
                                        
                                $replace_folio = $JwtAuth->generarFolio($querySoliReplace[0]->folio_solicitud);
                            }
                            
                            //motivo_cancelacion
                            $cancelation_token = $vCan->token_clave;
                            $cancelation_clave = $vCan->clave;	
                            $cancelation_motivo = $vCan->motivo;
                            $cancelation_descripcion = $vCan->descripcion;
                            $cancelation_accion = $vCan->accion;
                            
                            $detalle_motivo_cancelacion = $JwtAuth->desencriptar($vCan->detalle_motivo_cancelacion);
                            $fecha_autorizacion = date('d-m-Y H:i:s',$vCan->fecha_autorizacion);
                            $observaciones_autorizacion = $JwtAuth->desencriptar($vCan->observaciones_autorizacion);
                            
                            $rowOld = array(
                                "tkn_cancelacion" => $tkn_cancelacion,
                                "folio_cancelacion" => $folio_cancelacion,
                                "fecha_cancelacion" => $fecha_cancelacion,
                                //solicitud_cfdi_old
                                    "older_folio" => $older_folio,
                                //solicitud_cfdi_new
                                    "replace_folio" => $replace_folio,
                                //motivo_cancelacion
                                    "cancelation_token" => $cancelation_token,
                                    "cancelation_clave" => $cancelation_clave,	
                                    "cancelation_motivo" => $cancelation_motivo,
                                    "cancelation_descripcion" => $cancelation_descripcion,
                                    "cancelation_accion" => $cancelation_accion,
                                "detalle_motivo_cancelacion" => $detalle_motivo_cancelacion,
                                "fecha_autorizacion" => $fecha_autorizacion,
                                "observaciones_autorizacion" => $observaciones_autorizacion,
                            );
                            $list_cancelaciones[] = $rowOld;
                        }                        
                        
                    $row = array(
                        "token_cfdi" => $token_cfdi,	
                        "folio_cfdi" => $folio_cfdi."-S".$txt_folio_solicitud,
                        "fecha_registro" => $fecha_registro,
                        //emisor
                        "name_emisor" => $name_emisor,
                        "rfc_gen_emi" => $rfc_gen_emi,
                        "rfc_emp_emi" => $rfc_emp_emi,
                        "taxid_emp_emi" => $taxid_emp_emi,
                        "nombreEmiPers" => $nombreEmiPers,
                        //receptor
                        "tkn_receptor" => $tkn_receptor,
                        "name_receptor" => $name_receptor,
                        "rfc_gen_receptor" => $rfc_gen_receptor,
                        "rfc_emp_receptor" => $rfc_emp_receptor,
                        "taxid_emp_receptor" => $taxid_emp_receptor,
                        "nombreReceptorPers" => $nombreReceptorPers,
                        //last_version
                            "token_solicitud_cfdi" => $last_version[0]["token_solicitud_cfdi"],
                            "last_version_principal" => $last_version,
                            "last_version_reflejo" => $last_version,
                            "last_version_cancelacion" => $last_version,
                            //"last_version" => $last_version,
                        //old_versions
                            "old_versions" => $old_versions,
                        //cancelaciones
                            "soli_cancelaciones" => $soli_cancelArray,
                            "auth_cancelaciones" => $list_cancelaciones,    
                    );
                    $arrayCFDI[] = $row;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'cfdi' => $arrayCFDI,
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function cancelarCFDI(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('solicitud');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arraySoliCFDI = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "token_back_ter" => "required|string",
                "token_cfdi" => "required|string",
                "token_solicitud_cfdi" => "required|string",
                "token_cancelacion" => "required|string",
                "clave_motivo_cancelacion" => "required|string",
                "motivo_cancelacion" => "required|string",
                
                "client_tkn_soli" => "string",
                "soliCfdiRfc" => "string",
                "soliCfdiEmp" => "string",
                "soliCfdiEmail" => "string",
                "soliCfdiFactPagada" => "boolean",
                "soliCfdiTentativaPago" => "string",
                "soliCfdiMesVenta" => "string",
                "soliCfdiImporteVenta" => "string",
                "soliCfdiXmlSoli" => "array",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $fecha_sistema = time();
                $emisor_emp = "";
                $nacionalidad = "";
                $rfc_generico_emi = "";
                $rfc_emp_emi = "";
                $count_rfc_emp_emi = 0;
                $taxid_emp_emi = "";
                $tipo_factura = "";
                
                //return response()->json(['status' => 'error','code' => 200,'message' => "prueba ".$soliCfdiFactPagada]);
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronRfc = '/[aA0-zZ9]/';
                $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
                $patronFecha = '/^[0-9-]*$/';
                $patronPrecio = '/^[0-9$,.-]*$/';
                
                $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"],true);
                $token_cfdi = $parametrosArray["token_cfdi"];
                $token_solicitud_cfdi = $parametrosArray["token_solicitud_cfdi"];
                $token_cancelacion = $parametrosArray["token_cancelacion"];
                $clave_cancelacion = $parametrosArray["clave_motivo_cancelacion"];
                $motivo_cancelacion = $parametrosArray["motivo_cancelacion"];
                
                if (isset($token_cfdi) && !empty($token_cfdi) &&
                    isset($token_solicitud_cfdi) && !empty($token_solicitud_cfdi) &&
                    isset($token_cancelacion) && !empty($token_cancelacion) &&
                    isset($clave_cancelacion) && !empty($clave_cancelacion) && preg_match($patron,$clave_cancelacion) &&
                    isset($motivo_cancelacion) && !empty($motivo_cancelacion) && preg_match($patron,$motivo_cancelacion)) {
                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_01"]);     
                    $select_cfdi_main = DB::select("SELECT id,folio_cfdi,post_folio_cfdi FROM sos_cfdi_main WHERE token_cfdi = ?",[$token_cfdi]); 
                    if ($select_cfdi_main[0]->post_folio_cfdi == NULL) {
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($select_cfdi_main[0]->folio_cfdi);
                    } else {
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($select_cfdi_main[0]->folio_cfdi).'-'.$select_cfdi_main[0]->post_folio_cfdi;
                    }
                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_02"]); 
                    $first_soli_cfdi = DB::select("SELECT csol.id,csol.folio_solicitud+1 AS folio_solicitud FROM sos_cfdi_solicitud AS csol 
                        JOIN cfdi_main AS main WHERE csol.token_solicitud_cfdi = ? 
                        AND csol.cfdi_main = main.id AND main.token_cfdi = ?",[$token_solicitud_cfdi,$token_cfdi]);
                    $second_soli_cfdi = "";    
                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_03"]);
                    $select_canc_mot = DB::select("SELECT id FROM cfdi_motivos_cancelacion WHERE token_clave = ?",[$token_cancelacion]); 
                    
                    $queryAllCancelat = DB::select("SELECT ccanc.id FROM cfdi_cancelaciones AS ccanc 
                        JOIN cfdi_main AS main WHERE ccanc.cfdi_main = main.id AND main.token_cfdi = ?",[$token_cfdi]);
                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_04"]);
                    if (count($queryAllCancelat) == 1) {
                        $folio_canc_nuevo = 1;
                    } else {
                        $queryMaxCancelat = DB::select("SELECT folio_cancelacion+1 AS folio FROM cfdi_cancelaciones
                            WHERE id = (SELECT MAX(ccanc.id) FROM cfdi_cancelaciones AS ccanc
                            JOIN cfdi_main AS main WHERE ccanc.cfdi_main = main.id AND main.token_cfdi = ?)",[$token_cfdi]);
                        $folio_canc_nuevo = $queryMaxCancelat[0]->folio;
                    }
                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_05"]);
                    $folio_canc_all = $JwtAuth->generarFolio($folio_canc_nuevo);
                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud ".$folio_canc_all]);
                    if ($clave_cancelacion == "01" || $clave_cancelacion == "04") {
                        $client_tkn_soli = $parametrosArray["client_tkn_soli"];
                        $soliCfdiRfc = $parametrosArray["soliCfdiRfc"];
                        $soliCfdiEmp = $parametrosArray["soliCfdiEmp"];
                        $soliCfdiEmail = $parametrosArray["soliCfdiEmail"];
                        $soliCfdiFactPagada = $parametrosArray["soliCfdiFactPagada"];
                        $soliCfdiTentativaPago = $parametrosArray["soliCfdiTentativaPago"];
                        $soliCfdiMesVenta = $parametrosArray["soliCfdiMesVenta"];
                        $soliCfdiImporteVenta = $parametrosArray["soliCfdiImporteVenta"];
                        $soliCfdiXmlSoli = $parametrosArray["soliCfdiXmlSoli"];
                        
                        if (isset($client_tkn_soli) && !empty($client_tkn_soli) &&
                            isset($soliCfdiRfc) && !empty($soliCfdiRfc) && preg_match($patronRfc,$soliCfdiRfc) &&
                            isset($soliCfdiEmp) && !empty($soliCfdiEmp) && preg_match($patron,$soliCfdiEmp) &&
                            isset($soliCfdiEmail) && !empty($soliCfdiEmail) && preg_match($patronMail,$soliCfdiEmail) &&
                            isset($soliCfdiXmlSoli) && !empty($soliCfdiXmlSoli)) {
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_06"]);     
                            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM main_empresas AS emp  
                                JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                                AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                                AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                                //da_te_default_timezone_set($selectEmp[0]->zona_horaria);  
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_07"]);
                            $selectEmisorPers = DB::select("SELECT pers.id FROM vhum_personal AS pers 
                                JOIN main_usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?",
                                [$usuario->user_token]);
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_08"]);
                            foreach ($selectEmisorPers as $vPersEmi) {
                                $emisor_pers = $vPersEmi->id;
                            }
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_09"]);
                            $selectEmpIdFact = DB::select("SELECT id FROM ingr_catalogo_clientes 
                                WHERE token_cat_clientes = ?",[$client_tkn_soli]);
                            
                            foreach ($selectEmpIdFact as $vEmpSoli) {
                                $clienteIdent = $vEmpSoli->id;
                            }
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_10"]);
                            $selectEmisorEmp = DB::select("SELECT emp.id AS company,people.nacionalidad,people.rfc_generico,
                                people.rfc,people.tax_id FROM main_empresas AS emp 
                                FROM sos_personas AS people WHERE emp.persona = people.id AND emp.emp_token = ?",
                                [$usuario->emp_token]);
                            
                            foreach ($selectEmisorEmp as $vEmpEmi) {
                                $emisor_emp = $vEmpEmi->company;
                                $nacionalidad = $vEmpEmi->nacionalidad;
                                $rfc_generico_emi = $vEmpEmi->rfc_generico;
                                
			                    if ($vEmpEmi->rfc != NULL) {
			                    	$rfc_emp_emi = $JwtAuth->desencriptar($vEmpEmi->rfc);
			                    	$count_rfc_emp_emi = strlen($rfc_emp_emi);
			                    } else {
			                    	$rfc_emp_emi = "---";
			                    	$count_rfc_emp_emi = 0;
			                    }
                    
                                if ($vEmpEmi->tax_id != NULL) {
			                    	$taxid_emp_emi = $JwtAuth->desencriptar($vEmpEmi->tax_id);
			                    } else {
			                    	$taxid_emp_emi = "---";
			                    }
                            }
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_11"]);
                            if ($nacionalidad == 118) {
                                if ($count_rfc_emp_emi == 13){
                                    if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                        $tipo_factura = "FF";
                                    } else {
                                        $tipo_factura = "FM";
                                    }
                                } 
                                
                                if ($count_rfc_emp_emi == 12){
                                    if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                        $tipo_factura = "MM";
                                    } else {
                                        $tipo_factura = "MF";
                                    }
                                } 
                            }
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_12"]);
                            if (isset($soliCfdiFactPagada)) {
                                if ($soliCfdiFactPagada == true) {
                                    if (isset($soliCfdiTentativaPago) && !empty($soliCfdiTentativaPago) && preg_match($patronFecha,$soliCfdiTentativaPago) && 
                                        isset($soliCfdiMesVenta) && !empty($soliCfdiMesVenta) && preg_match($patronFecha,$soliCfdiMesVenta) &&
                                        isset($soliCfdiImporteVenta) && !empty($soliCfdiImporteVenta) && preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                        $sql_fact_pagada = TRUE;
                                        $sql_tentativa_pago = $JwtAuth->convierteFechaEpoc($soliCfdiTentativaPago);
                                        $sql_mes_venta = $JwtAuth->convierteFechaEpoc($soliCfdiMesVenta);
                                        $sql_importe_venta = $soliCfdiImporteVenta;
                                    } else {
                                        if (!isset($soliCfdiTentativaPago) || empty($soliCfdiTentativaPago) || !preg_match($patronFecha,$soliCfdiTentativaPago)) {
                                            $dataMensaje = array(
                                                'status' => 'error',
                                                'code' => 200,
                                                'message' => 'Error en fecha tentativa de pago, verifique su información'
                                            );
                                        }
                                        
                                        if (!isset($soliCfdiMesVenta) || empty($soliCfdiMesVenta) || !preg_match($patronFecha,$soliCfdiMesVenta)) {
                                            $dataMensaje = array(
                                                'status' => 'error',
                                                'code' => 200,
                                                'message' => 'Error en mes de venta, verifique su información'
                                            );
                                        }
                                        
                                        if (!isset($soliCfdiImporteVenta) || empty($soliCfdiImporteVenta) || !preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                            $dataMensaje = array(
                                                'status' => 'error',
                                                'code' => 200,
                                                'message' => 'Error en importe de venta, verifique su información'
                                            );
                                        }
                                    }
                                } else {
                                    $sql_fact_pagada = FALSE;
                                    $sql_tentativa_pago = NULL;
                                    $sql_mes_venta = NULL;
                                    $sql_importe_venta = NULL;
                                }
                            } else {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'No especificó si la factura que intenta registrar fue pagada anteriormente, verifique su información'
                                );
                            }
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_13"]);
                            $token_soli_cfdi = $JwtAuth->encriptarToken($client_tkn_soli.$soliCfdiRfc.
                                $soliCfdiEmp.$soliCfdiEmail.$emisor_emp.$emisor_pers.$clienteIdent);
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_14"]);    
                            $new_folio_solicitud = $JwtAuth->generarFolio($first_soli_cfdi[0]->folio_solicitud);
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_15"]);  
                            //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_15 ".$new_folio_solicitud]);
                            
                            $insert_soli_cfdi = DB::table('cfdi_solicitud')->insert(
                                array(
                                    "token_solicitud_cfdi" => $token_soli_cfdi,	
                                    "folio_solicitud" => $first_soli_cfdi[0]->folio_solicitud,
                                    "fecha_solicitud" => $fecha_sistema, 
                                    "cfdi_main" => $select_cfdi_main[0]->id,
                                    "cliente" => $clienteIdent,
                                    "email_referencia" => $JwtAuth->encriptar($soliCfdiEmail),
                                    "fact_pagada" => $sql_fact_pagada,
                                    "tentativa_pago" => $sql_tentativa_pago,
                                    "mes_venta" => $sql_mes_venta,
                                    "importe_venta" => $sql_importe_venta,
                                    "tipo_factura" => $tipo_factura,
                                    "valid_version" => FALSE,
                                    "status_emision" => TRUE,
                                    "fecha_delete" => NULL
                                )
                            );
                            
                            if ($insert_soli_cfdi) {
                                //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_16"]);  
                                $query_soli_cfdi = DB::select("SELECT id FROM sos_cfdi_solicitud WHERE token_solicitud_cfdi = ?",[$token_soli_cfdi]);
                                $second_soli_cfdi = $query_soli_cfdi[0]->id;
                                
                                for ($i = 0; $i < count($soliCfdiXmlSoli); $i++) {
                                    $sat_token = $soliCfdiXmlSoli[$i]["sat_token"];
                                    $unidad_medida = $soliCfdiXmlSoli[$i]["uni_med_token"];
                                    $cantidad = $soliCfdiXmlSoli[$i]["cantidad"];
                                    $descuento = $soliCfdiXmlSoli[$i]["descuento"];
                                    $short_descripcion = $soliCfdiXmlSoli[$i]["short_descripcion"];
                                    $large_descripcion = $soliCfdiXmlSoli[$i]["large_descripcion"];
                                    $forma_pago = $soliCfdiXmlSoli[$i]["fpago_token"];
                                    $metodo_pago = $soliCfdiXmlSoli[$i]["mpago_token"];
                                    $uso_cfdi = $soliCfdiXmlSoli[$i]["usoCfdi_token"];
                                    $precio_unitario = $soliCfdiXmlSoli[$i]["precio_unitario"];
                                    $type_iva = $soliCfdiXmlSoli[$i]["type_iva"];
                                    $iva = $soliCfdiXmlSoli[$i]["iva"];
                                    
                                    $token_det_soli = $JwtAuth->encriptarToken($sat_token.$unidad_medida.$cantidad.
                                        $descuento.$short_descripcion.$large_descripcion.$forma_pago.$metodo_pago.
                                        $uso_cfdi.$precio_unitario.$type_iva.$iva);
                                    
                                    if ($sat_token != "") {
                                        $selectClaveSat = DB::select("SELECT id FROM teci_catalogo_prodservsat 
                                            WHERE token_prodservsat = ?",[$sat_token]);
                                        $sat_sql = $selectClaveSat[0]->id;
                                    } else {
                                        $sat_sql = NULL;
                                    }
                                    
                                    if ($unidad_medida != "") {
                                        $selectUMedida = DB::select("SELECT id FROM teci_unidad_medida 
                                            WHERE token_unidad_medida = ?",[$unidad_medida]);
                                        $u_med_sat = $selectUMedida[0]->id;
                                    } else {
                                        $u_med_sat = NULL;
                                    }
                                    
                                    $selectFPago = DB::select("SELECT id FROM teci_forma_pago 
                                        WHERE token_formapago = ?",[$forma_pago]);
                                    $sql_f_pago = $selectFPago[0]->id;
                                    
                                    $selectMPago = DB::select("SELECT id FROM teci_metodo_pago 
                                        WHERE token_metodopago = ?",[$metodo_pago]);
                                    $sql_m_pago = $selectMPago[0]->id;
                                    
                                    if ($uso_cfdi != "") {
                                        $selectUsoCFDI = DB::select("SELECT id FROM teci_uso_cfdi 
                                            WHERE token_uso_cfdi = ?",[$uso_cfdi]);
                                        $sql_uso_cfdi = $selectUsoCFDI[0]->id;
                                    } else {
                                        $sql_uso_cfdi = NULL;
                                    }
                                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_17"]);  
                                    //return response()->json(['status' => 'error','code' => 200,'message' => "selectIdentSoli ".$sql_m_pago]); 
                                    $insertDetSoli = DB::table('sos_cfdi_detalle_solicitud')->insert(
                                        array(
                                            "token_detalle_soli" => $token_det_soli,	
                                            "cfdi_main" => $select_cfdi_main[0]->id,
                                            "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                            "clave_sat" => $sat_sql, 
                                            "unidad_medida" => $u_med_sat,
                                            "cantidad" => $cantidad,
                                            "descuento"	=> $descuento,
                                            "short_descripcion"	=> $JwtAuth->encriptar($short_descripcion),
                                            "large_descripcion"	=> $JwtAuth->encriptar($large_descripcion),
                                            "forma_pago" => $sql_f_pago,
                                            "metodo_pago" => $sql_m_pago,
                                            "uso_cfdi" => $sql_uso_cfdi,
                                            "precio_unitario" => $precio_unitario,
                                            "type_iva" => $type_iva,
                                            "iva" => $iva
                                        )
                                    );
                                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_18"]);
                                    //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                                }
                                //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_19"]);
                                if(!empty($_FILES["docsAnexos"])){
                                    $anexos = $_FILES["docsAnexos"];
                                    //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_21"]);
                                    $filepath = $selectEmp[0]->root_tkn."/0009-cfdi/".$folio_cfdi."/version-".$new_folio_solicitud."/anexos";
                                
                                    if (!file_exists(storage_path("/root/".$filepath))){
                                        Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                    }
                                    $docs_nombre = json_decode(json_encode($_FILES["docsAnexos"]["name"]));
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
                                        $token_docs = $JwtAuth->encriptarToken($token_soli_cfdi,$ext_doc,$name_documento);
                                        //return response()->json(['status' => 'error','code' => 200,'message' => $JwtAuth->encriptar($nombre)]);
                                        $insertDocSoli = DB::table("sos_cfdi_docs")->insert(
                                            array(
                                                "token_doc_cfdi" => $token_docs,
                                                "cfdi_main" => $select_cfdi_main[0]->id,
                                                "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                                "tipo_doc" => "an",
                                                "ext_doc" => $ext_doc,
                                                "name_documento" => $JwtAuth->encriptar($name_documento),
                                            )
                                        );
                                        
                                        if ($insertDocSoli) {
                                            Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                                        }
                                    }
                                }
                                //return response()->json(['status' => 'error','code' => 200,'message' => "sos_cfdi_solicitud_20"]);
                            } else {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                                );
                            }
                        } else {
                            if (!isset($client_tkn_soli) || empty($client_tkn_soli)) {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en cliente seleccionado, verifique su información'
                                );
                            }
                            if (!isset($soliCfdiRfc) || empty($soliCfdiRfc) || !preg_match($patronRfc,$soliCfdiRfc)) {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en rfc de cliente seleccionado, verifique su información'
                                );
                            }
                            if (!isset($soliCfdiEmp) || empty($soliCfdiEmp) || !preg_match($patron,$soliCfdiEmp)) {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en nombre de cliente seleccionado, verifique su información'
                                );
                            }
                            if (!isset($soliCfdiEmail) || empty($soliCfdiEmail) || !preg_match($patronMail,$soliCfdiEmail)) {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en email de referencia, verifique su información'
                                );
                            }
                            if (!isset($soliCfdiXmlSoli) || empty($soliCfdiXmlSoli)) {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'Error en llenado de conceptos , verifique su información'
                                );
                            }
                        }
                    }
                    
                    $tkn_cancel_new = $JwtAuth->encriptarToken($folio_canc_nuevo.$folio_canc_all.$fecha_sistema.$select_cfdi_main[0]->id.
                        $first_soli_cfdi[0]->id.$second_soli_cfdi.$select_canc_mot[0]->id.$JwtAuth->encriptar($motivo_cancelacion));
                    
                    $insert_soli_cfdi = DB::table('cfdi_cancelaciones')->insert(
                        array(
                            "token_cancelacion" => $tkn_cancel_new,	
                            "folio_cancelacion" => $folio_canc_nuevo,
                            "fecha_cancelacion" => $fecha_sistema, 
                            "cfdi_main" => $select_cfdi_main[0]->id,
                            "solicitud_cfdi_old" => $first_soli_cfdi[0]->id,
                            "solicitud_cfdi_new" => $second_soli_cfdi,
                            "motivo_cancelacion" => $select_canc_mot[0]->id,
                            "detalle_motivo_cancelacion" => $JwtAuth->encriptar($motivo_cancelacion),
                            "autorizacion" => FALSE,
                        )
                    );
                    
                    $titulo_alerta = "Ha solicitado la cancelación del CFDI con folio: ".$folio_cfdi.", folio de cancelación: ".$folio_canc_all;
                    $JwtAuth->insertGeneralNotif($titulo_alerta,1,2,1,$emisor_pers,3);
                     
                    $dataMensaje = array(
                        'message' => 'Solicitud de cancelación del CFDI '.$folio_cfdi.' ha sido registrada con el folio '.$folio_canc_all,
                        'code' => 200,
                        'status' => 'success'
                    );
                } else {
                    if (!isset($token_cfdi) || empty($token_cfdi)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en cfdi seleccionado, verifique su información'
                        );
                    }
                    if (!isset($token_solicitud_cfdi) || empty($token_solicitud_cfdi)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en solicitud de cfdi seleccionado, verifique su información'
                        );
                    }
                    if (!isset($token_cancelacion) || empty($token_cancelacion)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en motivo de cancelación seleccionado, verifique su información'
                        );
                    }
                    if (!isset($clave_cancelacion) || empty($clave_cancelacion) || !preg_match($patron,$clave_cancelacion)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en clave de cancelación seleccionada, verifique su información'
                        );
                    }
                    if (!isset($motivo_cancelacion) || empty($motivo_cancelacion) || !preg_match($patron,$motivo_cancelacion)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en descripción de motivo de cancelación, verifique su información'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    } 

    public function registroSolicitudCFDI(Request $request){
        $JwtAuth = new \JwtAuth();
        $docsAnexos = $request->file("docsAnexos");
        $jsonUser = $request->input('solicitud');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "token_back_ter" => "required|string",
                "client_tkn_soli" => "required|string",
                "soliCfdiRfc" => "required|string",
                "soliCfdiEmp" => "required|string",
                "soliCfdiEmail" => "required|string",
                "soliCfdiFactPagada" => "boolean",
                "soliCfdiTentativaPago" => "string",
                "soliCfdiMesVenta" => "string",
                "soliCfdiImporteVenta" => "string",
                "soliCfdiXmlSoli" => "required|array",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"],true);
                $client_tkn_soli = $parametrosArray["client_tkn_soli"];
                $soliCfdiRfc = $parametrosArray["soliCfdiRfc"];
                $soliCfdiEmp = $parametrosArray["soliCfdiEmp"];
                $soliCfdiEmail = $parametrosArray["soliCfdiEmail"];
                $soliCfdiFactPagada = $parametrosArray["soliCfdiFactPagada"];
                $soliCfdiTentativaPago = $parametrosArray["soliCfdiTentativaPago"];
                $soliCfdiMesVenta = $parametrosArray["soliCfdiMesVenta"];
                $soliCfdiImporteVenta = $parametrosArray["soliCfdiImporteVenta"];
                $soliCfdiXmlSoli = $parametrosArray["soliCfdiXmlSoli"];
                //echo $soliCfdiRfc;exit;
                $fecha_sistema = time();
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
                
                if (isset($client_tkn_soli) && !empty($client_tkn_soli) &&
                    isset($soliCfdiRfc) && !empty($soliCfdiRfc) && preg_match($patronRfc,$soliCfdiRfc) &&
                    isset($soliCfdiEmp) && !empty($soliCfdiEmp) && preg_match($patron,$soliCfdiEmp) &&
                    isset($soliCfdiEmail) && !empty($soliCfdiEmail) && preg_match($patronMail,$soliCfdiEmail) &&
                    isset($soliCfdiXmlSoli) && !empty($soliCfdiXmlSoli)) {
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM main_empresas AS emp  
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);  
                    
                    $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder
                        FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers 
                        JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE fold.solicitud_cfdi = TRUE 
                        AND fold.empresa = emp.id AND emp.emp_token = ? AND emp.id = emppers.empresa 
                        AND emppers.personal = pers.id AND pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->emp_token,$usuario->user_token]);
               
                    if (count($folioSistema) == 1) {
                        if ($folioSistema[0]->folio == 1000000000) {
                            $post_folio_db = DB::select("SELECT post_folio_cfdi FROM sos_cfdi_main 
                                WHERE id = (SELECT Max(soli.id) FROM sos_cfdi_main AS soli 
                                JOIN main_empresas AS emp JOIN empresapersonal AS empper 
                                JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                WHERE soli.emisor = emp.id AND emp.emp_token = ?
                                AND emp.id = empper.empresa AND empper.personal = pers.id
                                AND pers.usuario = users.id AND users.user_token = ?)",
                                [$usuario->emp_token,$usuario->user_token]);

                            $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_cfdi);
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
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($folio_nuevo);
                    } else {
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                    }
                    
                    $selectEmisorPers = DB::select("SELECT pers.id FROM vhum_personal AS pers 
                        JOIN main_usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->user_token]);
                    
                    foreach ($selectEmisorPers as $vPersEmi) {
                        $emisor_pers = $vPersEmi->id;
                    }
                    
                    $selectEmpIdFact = DB::select("SELECT id FROM ingr_catalogo_clientes 
                        WHERE token_cat_clientes = ?",[$client_tkn_soli]);
                    
                    foreach ($selectEmpIdFact as $vEmpSoli) {
                        $clienteIdent = $vEmpSoli->id;
                    }
                    
                    $selectEmisorEmp = DB::select("SELECT emp.id AS company,people.nacionalidad,people.rfc_generico,
                        people.rfc,people.tax_id FROM main_empresas AS emp 
                        JOIN sos_personas AS people WHERE emp.persona = people.id AND emp.emp_token = ?",
                        [$usuario->emp_token]);
                    
                    foreach ($selectEmisorEmp as $vEmpEmi) {
                        $emisor_emp = $vEmpEmi->company;
                        $nacionalidad = $vEmpEmi->nacionalidad;
                        $rfc_generico_emi = $vEmpEmi->rfc_generico;
                        
			            if ($vEmpEmi->rfc != NULL) {
			            	$rfc_emp_emi = $JwtAuth->desencriptar($vEmpEmi->rfc);
			            	$count_rfc_emp_emi = strlen($rfc_emp_emi);
			            } else {
			            	$rfc_emp_emi = "---";
			            	$count_rfc_emp_emi = 0;
			            }
            
                        if ($vEmpEmi->tax_id != NULL) {
			            	$taxid_emp_emi = $JwtAuth->desencriptar($vEmpEmi->tax_id);
			            } else {
			            	$taxid_emp_emi = "---";
			            }
                    }
                    
                    if ($nacionalidad == 118) {
                        if ($count_rfc_emp_emi == 13){
                            if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                $tipo_factura = "FF";
                            } else {
                                $tipo_factura = "FM";
                            }
                        } 
                        
                        if ($count_rfc_emp_emi == 12){
                            if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                $tipo_factura = "MM";
                            } else {
                                $tipo_factura = "MF";
                            }
                        } 
                    }
                    
                    if (isset($soliCfdiFactPagada)) {
                        if ($soliCfdiFactPagada == true) {
                            if (isset($soliCfdiTentativaPago) && !empty($soliCfdiTentativaPago) && preg_match($patronFecha,$soliCfdiTentativaPago) && 
                                isset($soliCfdiMesVenta) && !empty($soliCfdiMesVenta) && preg_match($patronFecha,$soliCfdiMesVenta) &&
                                isset($soliCfdiImporteVenta) && !empty($soliCfdiImporteVenta) && preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                $sql_fact_pagada = TRUE;
                                $sql_tentativa_pago = $JwtAuth->convierteFechaEpoc($soliCfdiTentativaPago);
                                $sql_mes_venta = $JwtAuth->convierteFechaEpoc($soliCfdiMesVenta);
                                $sql_importe_venta = $soliCfdiImporteVenta;
                            } else {
                                if (!isset($soliCfdiTentativaPago) || empty($soliCfdiTentativaPago) || !preg_match($patronFecha,$soliCfdiTentativaPago)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en fecha tentativa de pago, verifique su información'
                                    );
                                }
                                
                                if (!isset($soliCfdiMesVenta) || empty($soliCfdiMesVenta) || !preg_match($patronFecha,$soliCfdiMesVenta)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en mes de venta, verifique su información'
                                    );
                                }
                                
                                if (!isset($soliCfdiImporteVenta) || empty($soliCfdiImporteVenta) || !preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en importe de venta, verifique su información'
                                    );
                                }
                            }
                        } else {
                            $sql_fact_pagada = FALSE;
                            $sql_tentativa_pago = NULL;
                            $sql_mes_venta = NULL;
                            $sql_importe_venta = NULL;
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'No especificó si la factura que intenta registrar fue pagada anteriormente, verifique su información'
                        );
                    }
                    
                    //echo $clienteIdent;exit;
                    $token_cfdi_main = $JwtAuth->encriptarToken(rand(5, 15).$folio_cfdi.$fecha_sistema.$emisor_emp.$emisor_pers.$clienteIdent);
                    //return response()->json(['status' => 'error','code' => 200,'message' => "prueba ".$sql_importe_venta]);
                    $newCfdi = new CfdiModelo();
                    $newCfdi->token_cfdi = $token_cfdi_main;
                    $newCfdi->folio_cfdi = $folio_nuevo;
                    $newCfdi->post_folio_cfdi = $post_folio;
                    $newCfdi->registro_cfdi = "so";
                    $newCfdi->fecha_sistema = $fecha_sistema;
                    $newCfdi->emisor = $emisor_emp;
                    $newCfdi->receptor = 1;
                    $newCfdi->status_cfdi = TRUE;
                    $newCfdi->fecha_delete = NULL;
                    $newCfdi->user_emisor = $emisor_pers;
                    $newCfdi->user_receptor = 3;
                    $insertCfdi = $newCfdi->save();
                    
                    if ($insertCfdi) {
                        $select_cfdi_main = DB::select("SELECT id FROM sos_cfdi_main WHERE token_cfdi = ?",[$token_cfdi_main]);
                        $token_soli_cfdi = $JwtAuth->encriptarToken($client_tkn_soli.$soliCfdiRfc.
                            $soliCfdiEmp.$soliCfdiEmail.$emisor_emp.$emisor_pers.$clienteIdent);
                            
                        $new_folio_solicitud = $JwtAuth->generarFolio(1);
                           
                        $insert_soli_cfdi = DB::table('sos_cfdi_solicitud')->insert(
                            array(
                                "token_solicitud_cfdi" => $token_soli_cfdi,	
                                "folio_solicitud" => 1,
                                "fecha_solicitud" => $fecha_sistema, 
                                "cfdi_main" => $select_cfdi_main[0]->id,
                                "cliente" => $clienteIdent,
                                "email_referencia" => $JwtAuth->encriptar($soliCfdiEmail),
                                "fact_pagada" => $sql_fact_pagada,
                                "tentativa_pago" => $sql_tentativa_pago,
                                "mes_venta" => $sql_mes_venta,
                                "importe_venta" => $sql_importe_venta,
                                "tipo_factura" => $tipo_factura,
                                "valid_version" => TRUE,
                                "status_emision" => TRUE,
                                "fecha_delete" => NULL
                            )
                        );
                        
                        if ($insert_soli_cfdi) {
                            $query_soli_cfdi = DB::select("SELECT id FROM sos_cfdi_solicitud WHERE token_solicitud_cfdi = ?",[$token_soli_cfdi]);
                            
                            for ($i = 0; $i < count($soliCfdiXmlSoli); $i++) {
                                $clave_sat = $soliCfdiXmlSoli[$i]["clave_sat"];
                                $unidad_medida = $soliCfdiXmlSoli[$i]["unidad_medida"];
                                $cantidad = $soliCfdiXmlSoli[$i]["cantidad"];
                                $descuento = $soliCfdiXmlSoli[$i]["descuento"];
                                $short_descripcion = $soliCfdiXmlSoli[$i]["short_descripcion"];
                                $large_descripcion = $soliCfdiXmlSoli[$i]["large_descripcion"];
                                $forma_pago = $soliCfdiXmlSoli[$i]["forma_pago"];
                                $metodo_pago = $soliCfdiXmlSoli[$i]["metodo_pago"];
                                $uso_cfdi = $soliCfdiXmlSoli[$i]["uso_cfdi"];
                                $precio_unitario = $soliCfdiXmlSoli[$i]["precio_unitario"];
                                $type_iva = $soliCfdiXmlSoli[$i]["type_iva"];
                                $iva = $soliCfdiXmlSoli[$i]["iva"];
                                
                                $token_det_soli = $JwtAuth->encriptarToken($clave_sat.$unidad_medida.$cantidad.
                                    $descuento.$short_descripcion.$large_descripcion.$forma_pago.$metodo_pago.
                                    $uso_cfdi.$precio_unitario.$type_iva.$iva);
                                
                                if ($clave_sat != "") {
                                    $selectClaveSat = DB::select("SELECT id FROM teci_catalogo_prodservsat 
                                        WHERE token_prodservsat = ?",[$clave_sat]);
                                    $sat_sql = $selectClaveSat[0]->id;
                                } else {
                                    $sat_sql = NULL;
                                }
                                
                                if ($unidad_medida != "") {
                                    $selectUMedida = DB::select("SELECT id FROM teci_unidad_medida 
                                        WHERE token_unidad_medida = ?",[$unidad_medida]);
                                    $u_med_sat = $selectUMedida[0]->id;
                                } else {
                                    $u_med_sat = NULL;
                                }
                                //echo $forma_pago;
                                /*$selectFPago = DB::table("SELECT teci_forma_pago 
                                    WHERE token_formapago = ?",[$forma_pago]);*/
                                $selectFPago = DB::table("teci_forma_pago")
                                ->where(["token_formapago" => $forma_pago])->get();
                                foreach($selectFPago as $vfpag) {
                                    $sql_f_pago = $vfpag->id;
                                }
                                $selectMPago = DB::select("SELECT id FROM teci_metodo_pago 
                                    WHERE token_metodopago = ?",[$metodo_pago]);
                                $sql_m_pago = $selectMPago[0]->id;
                                
                                if ($uso_cfdi != "") {
                                    $selectUsoCFDI = DB::select("SELECT id FROM teci_uso_cfdi 
                                        WHERE token_uso_cfdi = ?",[$uso_cfdi]);
                                    $sql_uso_cfdi = $selectUsoCFDI[0]->id;
                                } else {
                                    $sql_uso_cfdi = NULL;
                                }
                                //return response()->json(['status' => 'error','code' => 200,'message' => "selectIdentSoli ".$sql_m_pago]); 
                                $insertDetSoli = DB::table('sos_cfdi_detalle_solicitud')->insert(
                                    array(
                                        "token_detalle_soli" => $token_det_soli,	
                                        "cfdi_main" => $select_cfdi_main[0]->id,
                                        "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                        "clave_sat" => $sat_sql, 
                                        "unidad_medida" => $u_med_sat,
                                        "cantidad" => $cantidad,
                                        "descuento"	=> $descuento,
                                        "short_descripcion"	=> $JwtAuth->encriptar($short_descripcion),
                                        "large_descripcion"	=> $JwtAuth->encriptar($large_descripcion),
                                        "forma_pago" => $sql_f_pago,
                                        "metodo_pago" => $sql_m_pago,
                                        "uso_cfdi" => $sql_uso_cfdi,
                                        "precio_unitario" => $precio_unitario,
                                        "type_iva" => $type_iva,
                                        "iva" => $iva
                                    )
                                );
                                //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                            }
                            
                            //
                            if(!empty($_FILES["docsAnexos"])){
                                $anexos = $_FILES["docsAnexos"];
                                $filepath = $selectEmp[0]->root_tkn."/0009-cfdi/".$folio_cfdi."/version-".$new_folio_solicitud."/anexos";
                            
                                if (!file_exists(storage_path("/root/".$filepath))){
                                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                }
                                $docs_nombre = json_decode(json_encode($_FILES["docsAnexos"]["name"]));
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
                                    $token_docs = $JwtAuth->encriptarToken($token_soli_cfdi,$ext_doc,$name_documento);
                                    //return response()->json(['status' => 'error','code' => 200,'message' => $JwtAuth->encriptar($nombre)]);
                                    $insertDocSoli = DB::table("cfdi_docs")->insert(
                                        array(
                                            "token_doc_cfdi" => $token_docs,
                                            "cfdi_main" => $select_cfdi_main[0]->id,
                                            "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                            "tipo_doc" => "an",
                                            "ext_doc" => $ext_doc,
                                            "name_documento" => $JwtAuth->encriptar($name_documento),
                                        )
                                    );
                                    
                                    if ($insertDocSoli) {
                                        Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                                    }
                                }
                            }
                            
                            if (count($folioSistema) == 0) {
                                $insertSistema = DB::table("sos_last_folders")
                                ->insert(
                                    array(
                                        "solicitud_cfdi" => TRUE, 
                                        "folder" => 1, 
                                        "post_folder" => $post_folio,
                                        "empresa" => $emisor_emp,
                                    )
                                );
                            } else {
                                $regFolder = DB::table("sos_last_folders AS ltfold")
                                ->join("main_empresas AS emp","ltfold.empresa","=","emp.id")
                                ->join("main_empresapersonal AS emppers","emp.id","emppers.empresa")
                                ->join("vhum_personal AS pers","emppers.personal","pers.id")
                                ->join("main_usuarios AS users","pers.usuario","users.id")
                                ->where([
                                    "ltfold.solicitud_cfdi" => TRUE,
                                    "emp.emp_token" => $usuario->emp_token,
                                    "users.user_token" => $usuario->user_token,
                                ])
                                ->limit(1)->update(
                                    array(
                                        "ltfold.folder" => $folio_nuevo,
                                        "ltfold.post_folder" => $post_folio,
                                    )
                                );
                            }
                            
                            $titulo_alerta = "Ha enviado una nueva solicitud de CFDI con folio: ".$folio_cfdi;
                            $JwtAuth->insertGeneralNotif("registro de solicitud de CFDI",$titulo_alerta,"tercA",1,2,1,$emisor_pers,4);
                            $dataMensaje = array(
                                'message' => 'Solicitud de CFDI registrada con el folio '.$folio_cfdi,
                                'code' => 200,
                                'status' => 'success'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                            );
                        }
                            
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                        );
                    }
                } else {
                    if (!isset($client_tkn_soli) || empty($client_tkn_soli)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiRfc) || empty($soliCfdiRfc) || !preg_match($patronRfc,$soliCfdiRfc)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en rfc de cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiEmp) || empty($soliCfdiEmp) || !preg_match($patron,$soliCfdiEmp)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en nombre de cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiEmail) || empty($soliCfdiEmail) || !preg_match($patronMail,$soliCfdiEmail)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en email de referencia, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiXmlSoli) || empty($soliCfdiXmlSoli)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en llenado de conceptos , verifique su información'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function registroSolicitudCFDIMostrador(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "token_venta_registrada" => "required|string",
                "razon_social_tipo" => "required|string",
                "razon_social_rfc" => "required|string",
                "razon_social_name" => "required|string",
                "razon_social_uso_cfdi" => "required|string",
                "razon_social_regimen_fiscal" => "required|string",
                "razon_social_cpostal" => "required|string",
                "dipomex_cod_postal_estado" => "required|string",
                "dipomex_cod_postal_municipio" => "required|string",
                "dipomex_cod_postal_cp" => "required|string",
                "dipomex_cod_postal_colonia_vinculada" => "required|string",
                "razon_social_dir_fiscal" => "required|string",
                "razon_social_email" => "string",
                "razon_social_telefono_dial" => "string",
                "razon_social_telefono_number" => "string",
                "razon_social_telefono_all" => "string",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $fecha_sistema = time();
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                $token_venta_registrada = $parametrosArray["token_venta_registrada"];
                $razon_social_tipo = $parametrosArray["razon_social_tipo"];
                $razon_social_rfc = $parametrosArray["razon_social_rfc"];
                $razon_social_name = $parametrosArray["razon_social_name"];
                $razon_social_uso_cfdi = $parametrosArray["razon_social_uso_cfdi"];
                $razon_social_regimen_fiscal = $parametrosArray["razon_social_regimen_fiscal"];
                $razon_social_cpostal = $parametrosArray["razon_social_cpostal"];
                $dipomex_cod_postal_estado = $parametrosArray["dipomex_cod_postal_estado"];
                $dipomex_cod_postal_municipio = $parametrosArray["dipomex_cod_postal_municipio"];
                $dipomex_cod_postal_cp = $parametrosArray["dipomex_cod_postal_cp"];
                $dipomex_cod_postal_colonia_vinculada = $parametrosArray["dipomex_cod_postal_colonia_vinculada"];
                $razon_social_dir_fiscal = $parametrosArray["razon_social_dir_fiscal"];
                $razon_social_email = $parametrosArray["razon_social_email"];
                $razon_social_telefono_dial = $parametrosArray["razon_social_telefono_dial"];
                $razon_social_telefono_number = $parametrosArray["razon_social_telefono_number"];
                $razon_social_telefono_all = $parametrosArray["razon_social_telefono_all"];
                //echo $soliCfdiRfc;exit;
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
                
                if (isset($client_tkn_soli) && !empty($client_tkn_soli) &&
                    isset($soliCfdiRfc) && !empty($soliCfdiRfc) && preg_match($patronRfc,$soliCfdiRfc) &&
                    isset($soliCfdiEmp) && !empty($soliCfdiEmp) && preg_match($patron,$soliCfdiEmp) &&
                    isset($soliCfdiEmail) && !empty($soliCfdiEmail) && preg_match($patronMail,$soliCfdiEmail) &&
                    isset($soliCfdiXmlSoli) && !empty($soliCfdiXmlSoli)) {
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM main_empresas AS emp  
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);  
                    
                    $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder
                        FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers 
                        JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE fold.solicitud_cfdi = TRUE 
                        AND fold.empresa = emp.id AND emp.emp_token = ? AND emp.id = emppers.empresa 
                        AND emppers.personal = pers.id AND pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->emp_token,$usuario->user_token]);
               
                    if (count($folioSistema) == 1) {
                        if ($folioSistema[0]->folio == 1000000000) {
                            $post_folio_db = DB::select("SELECT post_folio_cfdi FROM sos_cfdi_main 
                                WHERE id = (SELECT Max(soli.id) FROM sos_cfdi_main AS soli 
                                JOIN main_empresas AS emp JOIN empresapersonal AS empper 
                                JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                WHERE soli.emisor = emp.id AND emp.emp_token = ?
                                AND emp.id = empper.empresa AND empper.personal = pers.id
                                AND pers.usuario = users.id AND users.user_token = ?)",
                                [$usuario->emp_token,$usuario->user_token]);

                            $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_cfdi);
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
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($folio_nuevo);
                    } else {
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                    }
                    
                    $selectEmisorPers = DB::select("SELECT pers.id FROM vhum_personal AS pers 
                        JOIN main_usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->user_token]);
                    
                    foreach ($selectEmisorPers as $vPersEmi) {
                        $emisor_pers = $vPersEmi->id;
                    }
                    
                    $selectEmpIdFact = DB::select("SELECT id FROM ingr_catalogo_clientes 
                        WHERE token_cat_clientes = ?",[$client_tkn_soli]);
                    
                    foreach ($selectEmpIdFact as $vEmpSoli) {
                        $clienteIdent = $vEmpSoli->id;
                    }
                    
                    $selectEmisorEmp = DB::select("SELECT emp.id AS company,people.nacionalidad,people.rfc_generico,
                        people.rfc,people.tax_id FROM main_empresas AS emp 
                        JOIN sos_personas AS people WHERE emp.persona = people.id AND emp.emp_token = ?",
                        [$usuario->emp_token]);
                    
                    foreach ($selectEmisorEmp as $vEmpEmi) {
                        $emisor_emp = $vEmpEmi->company;
                        $nacionalidad = $vEmpEmi->nacionalidad;
                        $rfc_generico_emi = $vEmpEmi->rfc_generico;
                        
			            if ($vEmpEmi->rfc != NULL) {
			            	$rfc_emp_emi = $JwtAuth->desencriptar($vEmpEmi->rfc);
			            	$count_rfc_emp_emi = strlen($rfc_emp_emi);
			            } else {
			            	$rfc_emp_emi = "---";
			            	$count_rfc_emp_emi = 0;
			            }
            
                        if ($vEmpEmi->tax_id != NULL) {
			            	$taxid_emp_emi = $JwtAuth->desencriptar($vEmpEmi->tax_id);
			            } else {
			            	$taxid_emp_emi = "---";
			            }
                    }
                    
                    if ($nacionalidad == 118) {
                        if ($count_rfc_emp_emi == 13){
                            if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                $tipo_factura = "FF";
                            } else {
                                $tipo_factura = "FM";
                            }
                        } 
                        
                        if ($count_rfc_emp_emi == 12){
                            if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                $tipo_factura = "MM";
                            } else {
                                $tipo_factura = "MF";
                            }
                        } 
                    }
                    
                    if (isset($soliCfdiFactPagada)) {
                        if ($soliCfdiFactPagada == true) {
                            if (isset($soliCfdiTentativaPago) && !empty($soliCfdiTentativaPago) && preg_match($patronFecha,$soliCfdiTentativaPago) && 
                                isset($soliCfdiMesVenta) && !empty($soliCfdiMesVenta) && preg_match($patronFecha,$soliCfdiMesVenta) &&
                                isset($soliCfdiImporteVenta) && !empty($soliCfdiImporteVenta) && preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                $sql_fact_pagada = TRUE;
                                $sql_tentativa_pago = $JwtAuth->convierteFechaEpoc($soliCfdiTentativaPago);
                                $sql_mes_venta = $JwtAuth->convierteFechaEpoc($soliCfdiMesVenta);
                                $sql_importe_venta = $soliCfdiImporteVenta;
                            } else {
                                if (!isset($soliCfdiTentativaPago) || empty($soliCfdiTentativaPago) || !preg_match($patronFecha,$soliCfdiTentativaPago)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en fecha tentativa de pago, verifique su información'
                                    );
                                }
                                
                                if (!isset($soliCfdiMesVenta) || empty($soliCfdiMesVenta) || !preg_match($patronFecha,$soliCfdiMesVenta)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en mes de venta, verifique su información'
                                    );
                                }
                                
                                if (!isset($soliCfdiImporteVenta) || empty($soliCfdiImporteVenta) || !preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en importe de venta, verifique su información'
                                    );
                                }
                            }
                        } else {
                            $sql_fact_pagada = FALSE;
                            $sql_tentativa_pago = NULL;
                            $sql_mes_venta = NULL;
                            $sql_importe_venta = NULL;
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'No especificó si la factura que intenta registrar fue pagada anteriormente, verifique su información'
                        );
                    }
                    
                    //echo $clienteIdent;exit;
                    $token_cfdi_main = $JwtAuth->encriptarToken(rand(5, 15).$folio_cfdi.$fecha_sistema.$emisor_emp.$emisor_pers.$clienteIdent);
                    //return response()->json(['status' => 'error','code' => 200,'message' => "prueba ".$sql_importe_venta]);
                    $newCfdi = new CfdiModelo();
                    $newCfdi->token_cfdi = $token_cfdi_main;
                    $newCfdi->folio_cfdi = $folio_nuevo;
                    $newCfdi->post_folio_cfdi = $post_folio;
                    $newCfdi->registro_cfdi = "so";
                    $newCfdi->fecha_sistema = $fecha_sistema;
                    $newCfdi->emisor = $emisor_emp;
                    $newCfdi->receptor = 1;
                    $newCfdi->status_cfdi = TRUE;
                    $newCfdi->fecha_delete = NULL;
                    $newCfdi->user_emisor = $emisor_pers;
                    $newCfdi->user_receptor = 3;
                    $insertCfdi = $newCfdi->save();
                    
                    if ($insertCfdi) {
                        $select_cfdi_main = DB::select("SELECT id FROM sos_cfdi_main WHERE token_cfdi = ?",[$token_cfdi_main]);
                        $token_soli_cfdi = $JwtAuth->encriptarToken($client_tkn_soli.$soliCfdiRfc.
                            $soliCfdiEmp.$soliCfdiEmail.$emisor_emp.$emisor_pers.$clienteIdent);
                            
                        $new_folio_solicitud = $JwtAuth->generarFolio(1);
                           
                        $insert_soli_cfdi = DB::table('sos_cfdi_solicitud')->insert(
                            array(
                                "token_solicitud_cfdi" => $token_soli_cfdi,	
                                "folio_solicitud" => 1,
                                "fecha_solicitud" => $fecha_sistema, 
                                "cfdi_main" => $select_cfdi_main[0]->id,
                                "cliente" => $clienteIdent,
                                "email_referencia" => $JwtAuth->encriptar($soliCfdiEmail),
                                "fact_pagada" => $sql_fact_pagada,
                                "tentativa_pago" => $sql_tentativa_pago,
                                "mes_venta" => $sql_mes_venta,
                                "importe_venta" => $sql_importe_venta,
                                "tipo_factura" => $tipo_factura,
                                "valid_version" => TRUE,
                                "status_emision" => TRUE,
                                "fecha_delete" => NULL
                            )
                        );
                        
                        if ($insert_soli_cfdi) {
                            $query_soli_cfdi = DB::select("SELECT id FROM sos_cfdi_solicitud WHERE token_solicitud_cfdi = ?",[$token_soli_cfdi]);
                            
                            for ($i = 0; $i < count($soliCfdiXmlSoli); $i++) {
                                $clave_sat = $soliCfdiXmlSoli[$i]["clave_sat"];
                                $unidad_medida = $soliCfdiXmlSoli[$i]["unidad_medida"];
                                $cantidad = $soliCfdiXmlSoli[$i]["cantidad"];
                                $descuento = $soliCfdiXmlSoli[$i]["descuento"];
                                $short_descripcion = $soliCfdiXmlSoli[$i]["short_descripcion"];
                                $large_descripcion = $soliCfdiXmlSoli[$i]["large_descripcion"];
                                $forma_pago = $soliCfdiXmlSoli[$i]["forma_pago"];
                                $metodo_pago = $soliCfdiXmlSoli[$i]["metodo_pago"];
                                $uso_cfdi = $soliCfdiXmlSoli[$i]["uso_cfdi"];
                                $precio_unitario = $soliCfdiXmlSoli[$i]["precio_unitario"];
                                $type_iva = $soliCfdiXmlSoli[$i]["type_iva"];
                                $iva = $soliCfdiXmlSoli[$i]["iva"];
                                
                                $token_det_soli = $JwtAuth->encriptarToken($clave_sat.$unidad_medida.$cantidad.
                                    $descuento.$short_descripcion.$large_descripcion.$forma_pago.$metodo_pago.
                                    $uso_cfdi.$precio_unitario.$type_iva.$iva);
                                
                                if ($clave_sat != "") {
                                    $selectClaveSat = DB::select("SELECT id FROM teci_catalogo_prodservsat 
                                        WHERE token_prodservsat = ?",[$clave_sat]);
                                    $sat_sql = $selectClaveSat[0]->id;
                                } else {
                                    $sat_sql = NULL;
                                }
                                
                                if ($unidad_medida != "") {
                                    $selectUMedida = DB::select("SELECT id FROM teci_unidad_medida 
                                        WHERE token_unidad_medida = ?",[$unidad_medida]);
                                    $u_med_sat = $selectUMedida[0]->id;
                                } else {
                                    $u_med_sat = NULL;
                                }
                                //echo $forma_pago;
                                /*$selectFPago = DB::table("SELECT teci_forma_pago 
                                    WHERE token_formapago = ?",[$forma_pago]);*/
                                $selectFPago = DB::table("teci_forma_pago")
                                ->where(["token_formapago" => $forma_pago])->get();
                                foreach($selectFPago as $vfpag) {
                                    $sql_f_pago = $vfpag->id;
                                }
                                $selectMPago = DB::select("SELECT id FROM teci_metodo_pago 
                                    WHERE token_metodopago = ?",[$metodo_pago]);
                                $sql_m_pago = $selectMPago[0]->id;
                                
                                if ($uso_cfdi != "") {
                                    $selectUsoCFDI = DB::select("SELECT id FROM teci_uso_cfdi 
                                        WHERE token_uso_cfdi = ?",[$uso_cfdi]);
                                    $sql_uso_cfdi = $selectUsoCFDI[0]->id;
                                } else {
                                    $sql_uso_cfdi = NULL;
                                }
                                //return response()->json(['status' => 'error','code' => 200,'message' => "selectIdentSoli ".$sql_m_pago]); 
                                $insertDetSoli = DB::table('sos_cfdi_detalle_solicitud')->insert(
                                    array(
                                        "token_detalle_soli" => $token_det_soli,	
                                        "cfdi_main" => $select_cfdi_main[0]->id,
                                        "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                        "clave_sat" => $sat_sql, 
                                        "unidad_medida" => $u_med_sat,
                                        "cantidad" => $cantidad,
                                        "descuento"	=> $descuento,
                                        "short_descripcion"	=> $JwtAuth->encriptar($short_descripcion),
                                        "large_descripcion"	=> $JwtAuth->encriptar($large_descripcion),
                                        "forma_pago" => $sql_f_pago,
                                        "metodo_pago" => $sql_m_pago,
                                        "uso_cfdi" => $sql_uso_cfdi,
                                        "precio_unitario" => $precio_unitario,
                                        "type_iva" => $type_iva,
                                        "iva" => $iva
                                    )
                                );
                                //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                            }
                            
                            //
                            if(!empty($_FILES["docsAnexos"])){
                                $anexos = $_FILES["docsAnexos"];
                                $filepath = $selectEmp[0]->root_tkn."/0009-cfdi/".$folio_cfdi."/version-".$new_folio_solicitud."/anexos";
                            
                                if (!file_exists(storage_path("/root/".$filepath))){
                                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                }
                                $docs_nombre = json_decode(json_encode($_FILES["docsAnexos"]["name"]));
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
                                    $token_docs = $JwtAuth->encriptarToken($token_soli_cfdi,$ext_doc,$name_documento);
                                    //return response()->json(['status' => 'error','code' => 200,'message' => $JwtAuth->encriptar($nombre)]);
                                    $insertDocSoli = DB::table("cfdi_docs")->insert(
                                        array(
                                            "token_doc_cfdi" => $token_docs,
                                            "cfdi_main" => $select_cfdi_main[0]->id,
                                            "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                            "tipo_doc" => "an",
                                            "ext_doc" => $ext_doc,
                                            "name_documento" => $JwtAuth->encriptar($name_documento),
                                        )
                                    );
                                    
                                    if ($insertDocSoli) {
                                        Storage::putFileAs("/public/root/".$filepath,$temporal,$name_documento);
                                    }
                                }
                            }
                            
                            if (count($folioSistema) == 0) {
                                $insertSistema = DB::table("sos_last_folders")
                                ->insert(
                                    array(
                                        "solicitud_cfdi" => TRUE, 
                                        "folder" => 1, 
                                        "post_folder" => $post_folio,
                                        "empresa" => $emisor_emp,
                                    )
                                );
                            } else {
                                $regFolder = DB::table("sos_last_folders AS ltfold")
                                ->join("main_empresas AS emp","ltfold.empresa","=","emp.id")
                                ->join("main_empresapersonal AS emppers","emp.id","emppers.empresa")
                                ->join("vhum_personal AS pers","emppers.personal","pers.id")
                                ->join("main_usuarios AS users","pers.usuario","users.id")
                                ->where([
                                    "ltfold.solicitud_cfdi" => TRUE,
                                    "emp.emp_token" => $usuario->emp_token,
                                    "users.user_token" => $usuario->user_token,
                                ])
                                ->limit(1)->update(
                                    array(
                                        "ltfold.folder" => $folio_nuevo,
                                        "ltfold.post_folder" => $post_folio,
                                    )
                                );
                            }
                            
                            $titulo_alerta = "Ha enviado una nueva solicitud de CFDI con folio: ".$folio_cfdi;
                            $JwtAuth->insertGeneralNotif("registro de solicitud de CFDI",$titulo_alerta,"tercA",1,2,1,$emisor_pers,4);
                            $dataMensaje = array(
                                'message' => 'Solicitud de CFDI registrada con el folio '.$folio_cfdi,
                                'code' => 200,
                                'status' => 'success'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                            );
                        }
                            
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                        );
                    }
                } else {
                    if (!isset($client_tkn_soli) || empty($client_tkn_soli)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiRfc) || empty($soliCfdiRfc) || !preg_match($patronRfc,$soliCfdiRfc)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en rfc de cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiEmp) || empty($soliCfdiEmp) || !preg_match($patron,$soliCfdiEmp)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en nombre de cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiEmail) || empty($soliCfdiEmail) || !preg_match($patronMail,$soliCfdiEmail)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en email de referencia, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiXmlSoli) || empty($soliCfdiXmlSoli)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en llenado de conceptos , verifique su información'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function registroSoliCFDI(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "token_back_ter" => "required|string",
                "client_tkn_soli" => "required|string",
                "soliCfdiRfc" => "required|string",
                "soliCfdiEmp" => "required|string",
                "soliCfdiEmail" => "required|string",
                "soliCfdiFactPagada" => "boolean",
                "soliCfdiTentativaPago" => "string",
                "soliCfdiMesVenta" => "string",
                "soliCfdiImporteVenta" => "string",
                "soliCfdiXmlSoli" => "required|array",
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"],true);
                $client_tkn_soli = $parametrosArray["client_tkn_soli"];
                $soliCfdiRfc = $parametrosArray["soliCfdiRfc"];
                $soliCfdiEmp = $parametrosArray["soliCfdiEmp"];
                $soliCfdiEmail = $parametrosArray["soliCfdiEmail"];
                $soliCfdiFactPagada = $parametrosArray["soliCfdiFactPagada"];
                $soliCfdiTentativaPago = $parametrosArray["soliCfdiTentativaPago"];
                $soliCfdiMesVenta = $parametrosArray["soliCfdiMesVenta"];
                $soliCfdiImporteVenta = $parametrosArray["soliCfdiImporteVenta"];
                $soliCfdiXmlSoli = $parametrosArray["soliCfdiXmlSoli"];
                //echo $soliCfdiRfc;exit;
                $fecha_sistema = time();
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
                
                if (isset($client_tkn_soli) && !empty($client_tkn_soli) &&
                    isset($soliCfdiRfc) && !empty($soliCfdiRfc) && preg_match($patronRfc,$soliCfdiRfc) &&
                    isset($soliCfdiEmp) && !empty($soliCfdiEmp) && preg_match($patron,$soliCfdiEmp) &&
                    isset($soliCfdiEmail) && !empty($soliCfdiEmail) && preg_match($patronMail,$soliCfdiEmail) &&
                    isset($soliCfdiXmlSoli) && !empty($soliCfdiXmlSoli)) {
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM main_empresas AS emp  
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);  
                    
                    $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder
                        FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers 
                        JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE fold.solicitud_cfdi = TRUE 
                        AND fold.empresa = emp.id AND emp.emp_token = ? AND emp.id = emppers.empresa 
                        AND emppers.personal = pers.id AND pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->emp_token,$usuario->user_token]);
               
                    if (count($folioSistema) == 1) {
                        if ($folioSistema[0]->folio == 1000000000) {
                            $post_folio_db = DB::select("SELECT post_folio_cfdi FROM sos_cfdi_main 
                                WHERE id = (SELECT Max(soli.id) FROM sos_cfdi_main AS soli 
                                JOIN main_empresas AS emp JOIN empresapersonal AS empper 
                                JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                WHERE soli.emisor = emp.id AND emp.emp_token = ?
                                AND emp.id = empper.empresa AND empper.personal = pers.id
                                AND pers.usuario = users.id AND users.user_token = ?)",
                                [$usuario->emp_token,$usuario->user_token]);

                            $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_cfdi);
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
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($folio_nuevo);
                    } else {
                        $folio_cfdi = "CFDI-".$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                    }
                    
                    $selectEmisorPers = DB::select("SELECT pers.id FROM vhum_personal AS pers 
                        JOIN main_usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->user_token]);
                    
                    foreach ($selectEmisorPers as $vPersEmi) {
                        $emisor_pers = $vPersEmi->id;
                    }
                    
                    $selectEmpIdFact = DB::select("SELECT id FROM ingr_catalogo_clientes 
                        WHERE token_cat_clientes = ?",[$client_tkn_soli]);
                    
                    foreach ($selectEmpIdFact as $vEmpSoli) {
                        $clienteIdent = $vEmpSoli->id;
                    }
                    
                    $selectEmisorEmp = DB::select("SELECT emp.id AS company,people.nacionalidad,people.rfc_generico,
                        people.rfc,people.tax_id FROM main_empresas AS emp 
                        FROM sos_personas AS people WHERE emp.persona = people.id AND emp.emp_token = ?",
                        [$usuario->emp_token]);
                    
                    foreach ($selectEmisorEmp as $vEmpEmi) {
                        $emisor_emp = $vEmpEmi->company;
                        $nacionalidad = $vEmpEmi->nacionalidad;
                        $rfc_generico_emi = $vEmpEmi->rfc_generico;
                        
			            if ($vEmpEmi->rfc != NULL) {
			            	$rfc_emp_emi = $JwtAuth->desencriptar($vEmpEmi->rfc);
			            	$count_rfc_emp_emi = strlen($rfc_emp_emi);
			            } else {
			            	$rfc_emp_emi = "---";
			            	$count_rfc_emp_emi = 0;
			            }
            
                        if ($vEmpEmi->tax_id != NULL) {
			            	$taxid_emp_emi = $JwtAuth->desencriptar($vEmpEmi->tax_id);
			            } else {
			            	$taxid_emp_emi = "---";
			            }
                    }
                    
                    if ($nacionalidad == 118) {
                        if ($count_rfc_emp_emi == 13){
                            if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                $tipo_factura = "FF";
                            } else {
                                $tipo_factura = "FM";
                            }
                        } 
                        
                        if ($count_rfc_emp_emi == 12){
                            if ($count_rfc_emp_emi == strlen($soliCfdiRfc)) {
                                $tipo_factura = "MM";
                            } else {
                                $tipo_factura = "MF";
                            }
                        } 
                    }
                    
                    if (isset($soliCfdiFactPagada)) {
                        if ($soliCfdiFactPagada == true) {
                            if (isset($soliCfdiTentativaPago) && !empty($soliCfdiTentativaPago) && preg_match($patronFecha,$soliCfdiTentativaPago) && 
                                isset($soliCfdiMesVenta) && !empty($soliCfdiMesVenta) && preg_match($patronFecha,$soliCfdiMesVenta) &&
                                isset($soliCfdiImporteVenta) && !empty($soliCfdiImporteVenta) && preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                $sql_fact_pagada = TRUE;
                                $sql_tentativa_pago = $JwtAuth->convierteFechaEpoc($soliCfdiTentativaPago);
                                $sql_mes_venta = $JwtAuth->convierteFechaEpoc($soliCfdiMesVenta);
                                $sql_importe_venta = $soliCfdiImporteVenta;
                            } else {
                                if (!isset($soliCfdiTentativaPago) || empty($soliCfdiTentativaPago) || !preg_match($patronFecha,$soliCfdiTentativaPago)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en fecha tentativa de pago, verifique su información'
                                    );
                                }
                                
                                if (!isset($soliCfdiMesVenta) || empty($soliCfdiMesVenta) || !preg_match($patronFecha,$soliCfdiMesVenta)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en mes de venta, verifique su información'
                                    );
                                }
                                
                                if (!isset($soliCfdiImporteVenta) || empty($soliCfdiImporteVenta) || !preg_match($patronPrecio,$soliCfdiImporteVenta)) {
                                    $dataMensaje = array(
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'Error en importe de venta, verifique su información'
                                    );
                                }
                            }
                        } else {
                            $sql_fact_pagada = FALSE;
                            $sql_tentativa_pago = NULL;
                            $sql_mes_venta = NULL;
                            $sql_importe_venta = NULL;
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'No especificó si la factura que intenta registrar fue pagada anteriormente, verifique su información'
                        );
                    }
                    
                    //echo $clienteIdent;exit;
                    $token_cfdi_main = $JwtAuth->encriptarToken(rand(5, 15).$folio_cfdi.$fecha_sistema.$emisor_emp.$emisor_pers.$clienteIdent);
                    //return response()->json(['status' => 'error','code' => 200,'message' => "prueba ".$sql_importe_venta]);
                    $newCfdi = new CfdiModelo();
                    $newCfdi->token_cfdi = $token_cfdi_main;
                    $newCfdi->folio_cfdi = $folio_nuevo;
                    $newCfdi->post_folio_cfdi = $post_folio;
                    $newCfdi->registro_cfdi = "so";
                    $newCfdi->fecha_sistema = $fecha_sistema;
                    $newCfdi->emisor = $emisor_emp;
                    $newCfdi->receptor = 1;
                    $newCfdi->status_cfdi = TRUE;
                    $newCfdi->fecha_delete = NULL;
                    $newCfdi->user_emisor = $emisor_pers;
                    $newCfdi->user_receptor = 3;
                    $insertCfdi = $newCfdi->save();
                    
                    if ($insertCfdi) {
                        $select_cfdi_main = DB::select("SELECT id FROM sos_cfdi_main WHERE token_cfdi = ?",[$token_cfdi_main]);
                        $token_soli_cfdi = $JwtAuth->encriptarToken($client_tkn_soli.$soliCfdiRfc.
                            $soliCfdiEmp.$soliCfdiEmail.$emisor_emp.$emisor_pers.$clienteIdent);
                            
                        $new_folio_solicitud = $JwtAuth->generarFolio(1);
                           
                        $insert_soli_cfdi = DB::table('cfdi_solicitud')->insert(
                            array(
                                "token_solicitud_cfdi" => $token_soli_cfdi,	
                                "folio_solicitud" => 1,
                                "fecha_solicitud" => $fecha_sistema, 
                                "cfdi_main" => $select_cfdi_main[0]->id,
                                "cliente" => $clienteIdent,
                                "email_referencia" => $JwtAuth->encriptar($soliCfdiEmail),
                                "fact_pagada" => $sql_fact_pagada,
                                "tentativa_pago" => $sql_tentativa_pago,
                                "mes_venta" => $sql_mes_venta,
                                "importe_venta" => $sql_importe_venta,
                                "tipo_factura" => $tipo_factura,
                                "valid_version" => TRUE,
                                "status_emision" => TRUE,
                                "fecha_delete" => NULL
                            )
                        );
                        
                        if ($insert_soli_cfdi) {
                            $query_soli_cfdi = DB::select("SELECT id FROM sos_cfdi_solicitud WHERE token_solicitud_cfdi = ?",[$token_soli_cfdi]);
                            
                            for ($i = 0; $i < count($soliCfdiXmlSoli); $i++) {
                                $clave_sat = $soliCfdiXmlSoli[$i]["clave_sat"];
                                $unidad_medida = $soliCfdiXmlSoli[$i]["unidad_medida"];
                                $cantidad = $soliCfdiXmlSoli[$i]["cantidad"];
                                $descuento = $soliCfdiXmlSoli[$i]["descuento"];
                                $short_descripcion = $soliCfdiXmlSoli[$i]["short_descripcion"];
                                $large_descripcion = $soliCfdiXmlSoli[$i]["large_descripcion"];
                                $forma_pago = $soliCfdiXmlSoli[$i]["forma_pago"];
                                $metodo_pago = $soliCfdiXmlSoli[$i]["metodo_pago"];
                                $uso_cfdi = $soliCfdiXmlSoli[$i]["uso_cfdi"];
                                $precio_unitario = $soliCfdiXmlSoli[$i]["precio_unitario"];
                                $type_iva = $soliCfdiXmlSoli[$i]["type_iva"];
                                $iva = $soliCfdiXmlSoli[$i]["iva"];
                                
                                $token_det_soli = $JwtAuth->encriptarToken($clave_sat.$unidad_medida.$cantidad.
                                    $descuento.$short_descripcion.$large_descripcion.$forma_pago.$metodo_pago.
                                    $uso_cfdi.$precio_unitario.$type_iva.$iva);
                                
                                if ($clave_sat != "") {
                                    $selectClaveSat = DB::select("SELECT id FROM teci_catalogo_prodservsat 
                                        WHERE token_prodservsat = ?",[$clave_sat]);
                                    $sat_sql = $selectClaveSat[0]->id;
                                } else {
                                    $sat_sql = NULL;
                                }
                                
                                if ($unidad_medida != "") {
                                    $selectUMedida = DB::select("SELECT id FROM teci_unidad_medida 
                                        WHERE token_unidad_medida = ?",[$unidad_medida]);
                                    $u_med_sat = $selectUMedida[0]->id;
                                } else {
                                    $u_med_sat = NULL;
                                }
                                
                                $selectFPago = DB::select("SELECT id FROM teci_forma_pago 
                                    WHERE token_formapago = ?",[$forma_pago]);
                                $sql_f_pago = $selectFPago[0]->id;
                                
                                $selectMPago = DB::select("SELECT id FROM teci_metodo_pago 
                                    WHERE token_metodopago = ?",[$metodo_pago]);
                                $sql_m_pago = $selectMPago[0]->id;
                                
                                if ($uso_cfdi != "") {
                                    $selectUsoCFDI = DB::select("SELECT id FROM teci_uso_cfdi 
                                        WHERE token_uso_cfdi = ?",[$uso_cfdi]);
                                    $sql_uso_cfdi = $selectUsoCFDI[0]->id;
                                } else {
                                    $sql_uso_cfdi = NULL;
                                }
                                //return response()->json(['status' => 'error','code' => 200,'message' => "selectIdentSoli ".$sql_m_pago]); 
                                $insertDetSoli = DB::table('cfdi_detalle_solicitud')->insert(
                                    array(
                                        "token_detalle_soli" => $token_det_soli,	
                                        "cfdi_main" => $select_cfdi_main[0]->id,
                                        "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                        "clave_sat" => $sat_sql, 
                                        "unidad_medida" => $u_med_sat,
                                        "cantidad" => $cantidad,
                                        "descuento"	=> $descuento,
                                        "short_descripcion"	=> $JwtAuth->encriptar($short_descripcion),
                                        "large_descripcion"	=> $JwtAuth->encriptar($large_descripcion),
                                        "forma_pago" => $sql_f_pago,
                                        "metodo_pago" => $sql_m_pago,
                                        "uso_cfdi" => $sql_uso_cfdi,
                                        "precio_unitario" => $precio_unitario,
                                        "type_iva" => $type_iva,
                                        "iva" => $iva
                                    )
                                );
                                //return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
                            }
                            
                            $insertDocSoli = DB::table("cfdi_docs")->insert(
                                array(
                                    "token_doc_cfdi" => "token_docs",
                                    "cfdi_main" => $select_cfdi_main[0]->id,
                                    "solicitud_cfdi" => $query_soli_cfdi[0]->id,
                                    "tipo_doc" => "an",
                                    "ext_doc" => "tipo_doc",
                                    "name_documento" => "name_documento",
                                )
                            );
                            return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud "]); 
                            if (count($folioSistema) == 0) {
                                $insertSistema = DB::table('last_folders')
                                ->insert(
                                    array(
                                        "solicitud_cfdi" => TRUE, 
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
                                    'last_folders.solicitud_cfdi' => TRUE,
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
                            
                            $titulo_alerta = "Ha enviado una nueva solicitud de CFDI con folio: ".$folio_cfdi;
                            $JwtAuth->insertGeneralNotif($titulo_alerta,1,2,1,$emisor_pers,3);
                             
                            $dataMensaje = array(
                                'message' => 'Solicitud de CFDI registrada con el folio '.$folio_cfdi,
                                'code' => 200,
                                'status' => 'success'
                            );
                            
                        } else {
                            $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
    );
                        }
                            
                    } else {
                        $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
    );
                    }
                } else {
                    if (!isset($client_tkn_soli) || empty($client_tkn_soli)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiRfc) || empty($soliCfdiRfc) || !preg_match($patronRfc,$soliCfdiRfc)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en rfc de cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiEmp) || empty($soliCfdiEmp) || !preg_match($patron,$soliCfdiEmp)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en nombre de cliente seleccionado, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiEmail) || empty($soliCfdiEmail) || !preg_match($patronMail,$soliCfdiEmail)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en email de referencia, verifique su información'
                        );
                    }
                    if (!isset($soliCfdiXmlSoli) || empty($soliCfdiXmlSoli)) {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Error en llenado de conceptos , verifique su información'
                        );
                    }
                }
                
                
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

}
