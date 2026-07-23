<?php

namespace App\Http\Controllers;
header("Content-Type: text/html;charset=utf-8");
/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\ModuleProyectosModelo;
use App\Models\ModeloProyectosInsert;
use PDF;
use QRCode;

class ModuleProyectosController extends Controller {
//plantillas
    public function catalogoPlantillas(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
        $listaTemplates = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',  
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Usuario incorrecto".$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                
                $queryTemplates = DB::table("sos_templates_proyectos AS template")
                ->join("vhum_empleados_catalogo AS pers","template.template_lider","pers.id")
                ->join("sos_personas AS people","pers.empleado_name","people.id")
                ->join("main_empresas AS emp","template.empresa","emp.id")
                ->where(["template.template_status" => TRUE,"emp.empresa_token" => $usuario->empresa_token])->get();
                
                if (count($queryTemplates)) {
                    $lista_num = 1;
                    foreach ($queryTemplates as $vTemp) {
                        $template_lider_tkn = $vTemp->empleado_token;
                        $template_lider_nombre = $JwtAuth->desencriptarNombres($vTemp->paterno,$vTemp->materno,$vTemp->nombre);
                        
                        $template_upload_evid = false;
                        $permiso_delete_evd = false; 
                        if ($vTemp->template_upload_evd == TRUE) $template_upload_evid = true;
                        if ($vTemp->permiso_delete_evd == TRUE) $permiso_delete_evd = true;
                        
                        $row = array(
                            "template_lista" => $lista_num,
                            "template_token" => $vTemp->template_token,
                            "template_folio" => "PLANT-".$JwtAuth->generarFolio($vTemp->template_folio),
                            "fecha_registro" => date('d-m-Y H:i:s',$vTemp->fecha_registro),
                            "template_titulo" => $JwtAuth->desencriptar($vTemp->template_titulo),
                            "template_lider_tkn" => $template_lider_tkn,
                            "template_lider_nombre" => $template_lider_nombre,
                            "template_upload_evd" => $template_upload_evid,
                            "permiso_delete_evd" => $permiso_delete_evd,
                            "template_tareas" => []
                        );
                        ++$lista_num;
                        $listaTemplates[] = $row;
                    }
                    
                    $status_salida = "success";
                    $message_salida = "Total de plantillas registradas ".count($queryTemplates);
                } else {
                    $status_salida = "error";
                    $message_salida = "No hay plantillas registradas";
                }
                
                $dataMensaje = array(
                    "status" => $status_salida,
                    "code" => 200,
                    "message" => $message_salida,
                    "templates" => $listaTemplates
                );

            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    } 

    public function registrarPlantilla(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'plantilla_titulo' => 'required|string',
                'plantilla_upload_evidencias' => 'boolean',
                'plantilla_delete_evidencias' => 'boolean',
                'plantilla_responsable' => 'required|string',
                'plantilla_team' => 'array',
                'plantilla_lista_tareas' => 'required|array',  
                
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Usuario incorrecto".$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 

                //$patron = '/[aA-zZ_]/';
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                $patronFecha = '/^[0-9-]*$/';

                $validaTarea = false;

                $plantillaTitulo = $parametrosArray['plantilla_titulo'];
                $plantillaEvdUpload = $parametrosArray['plantilla_upload_evidencias'];
                $plantillaEvdDelete = $parametrosArray['plantilla_delete_evidencias'];
                $plantillaResponsable = $parametrosArray['plantilla_responsable'];
                $plantillaTeam = $parametrosArray['plantilla_team'];
                $plantillaListaTareas = $parametrosArray['plantilla_lista_tareas'];
                
                //echo $prioridadProy; exit;
                if (isset($plantillaTitulo) && !empty($plantillaTitulo) && preg_match($patron,$plantillaTitulo) && is_bool($plantillaEvdUpload) && is_bool($plantillaEvdDelete) &&
                    isset($plantillaResponsable) && !empty($plantillaResponsable) && count($plantillaListaTareas) > 0) {
                    
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                    $validaTarea = true;
                    $fecha_sistema = time();
                    $fecha_inicio = time();
                    
                    $plantilla_token = $JwtAuth->encriptarToken($fecha_sistema.$fecha_inicio.$plantillaTitulo.$plantillaEvdUpload.$plantillaEvdDelete.$plantillaResponsable.json_encode($plantillaTeam).json_encode($plantillaListaTareas)); 

                    $folioSistema = DB::select("SELECT max(template_folio) AS template_folio FROM sos_templates_proyectos AS template JOIN main_empresas AS emp 
                        WHERE template.empresa = emp.id AND emp.empresa_token = ?",[$usuario->empresa_token]);  
                        
                    if (count($folioSistema) == 0){
                        $sql_folio = 1;
                    } else {
                        $sql_folio = $folioSistema[0]->template_folio+1;
                    }
            
                    $folio_template = 'PLANT-'.$JwtAuth->generarFolio($sql_folio);
                    
                    $plantillaTitulo = str_replace(".diagon.","/",$plantillaTitulo);
                    $plantillaTitulo = str_replace(".porcent.","%",$plantillaTitulo);
                    $plantillaTitulo = str_replace(".ampersand.","&",$plantillaTitulo);
                    $plantillaTitulo = str_replace(".pessos.","$",$plantillaTitulo);
                    
                    $select_lider_plant = DB::select("SELECT pers.id,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                        WHERE pers.empleado_token = ? AND pers.id = users.empleado",[$plantillaResponsable]);
                    
                    if ($plantillaEvdUpload == true){ 
                        $sql_plantillaEvdUpload = TRUE;
                    } else {
                        $sql_plantillaEvdUpload = FALSE;
                    }
                    if ($plantillaEvdDelete == true){ 
                        $sql_plantillaEvdDelete = TRUE;
                    } else {
                        $sql_plantillaEvdDelete = TRUE;
                    }
                    
                    $insertTemplate = DB::table("sos_templates_proyectos")->insert(
                        array(
                            "template_token" => $plantilla_token,
                            "template_folio" => $sql_folio,
                            "fecha_registro" => time(),
                            "template_titulo" => $JwtAuth->encriptar($plantillaTitulo),
                            "template_lider" => end($select_lider_plant)->id,
                            "template_upload_evd" => $sql_plantillaEvdUpload,
                            "permiso_delete_evd" => $sql_plantillaEvdDelete,
                            "template_status" => TRUE,
                            "empresa" => end($selectEmp)->id,
                        )
                    );
                    
                    if ($insertTemplate) {
                        $selectPlantilla = DB::select("SELECT id FROM sos_templates_proyectos WHERE template_token = ?",[$plantilla_token]);
                        $titulo_alerta_lider = "Has sido registrado como lider para el módulo de proyectos predefinidos";
                        $JwtAuth->notificacionPushDevices(end($select_lider_plant)->usuario_token,"SOS-México - Finanzas",$titulo_alerta_lider);
                        
                        if (count($plantillaTeam) > 0){
                            for ($i = 0; $i < count($plantillaTeam); $i++){
                                $team_token = $plantillaTeam[$i]["token_empleado_inside"];
                                $tknEquipo = DB::select("SELECT pers.id,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                    WHERE pers.empleado_token = ? AND pers.id = users.empleado",[$team_token]);
                                    
                                $insertEquipo = DB::table('sos_templates_proyectos_team')->insert(array("template" => end($selectPlantilla)->id,"personal" => end($tknEquipo)->id));
                                
                                $titulo_alerta_team = "Has sido registrado como equipo de trabajo para el módulo de proyectos";
                                $JwtAuth->notificacionPushDevices(end($tknEquipo)->usuario_token,"SOS-México - Módulo de proyectos",$titulo_alerta_team);
                            } 
                        }
                        
                        if (count($plantillaListaTareas) > 0){
                            for ($i = 0; $i < count($plantillaListaTareas); $i++){
                                $row_tar_name = $plantillaListaTareas[$i]["tarea"];
                                $row_tar_desc = $plantillaListaTareas[$i]["descripcion"];
                                $row_tar_team = $plantillaListaTareas[$i]["team"];
                                
                                $tarea_token = $JwtAuth->encriptarToken($plantilla_token.$row_tar_name.$row_tar_desc); 
                                
                                $tarea_folio = DB::select("SELECT max(tar.folio_tarea) AS folio_tarea FROM sos_templates_proyectos_tareas AS tar JOIN sos_templates_proyectos AS template 
                                    WHERE tar.template = template.id AND template.template_token = ?",[$plantilla_token]);  
                                    
                                if (count($tarea_folio) == 0){
                                    $sql_tarea_folio = 1;
                                } else {
                                    $sql_tarea_folio = $tarea_folio[0]->folio_tarea+1;
                                }
                                            
                                $row_tar_lider = DB::select("SELECT pers.id,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                    WHERE pers.empleado_token = ? AND pers.id = users.empleado",[$plantillaListaTareas[$i]["lider_ktn"]]);
                                
                                $insertTarea = DB::table('sos_templates_proyectos_tareas')->insert(
                                    array(
                                        "token_tarea" => $tarea_token,
                                        "folio_tarea" => $sql_tarea_folio,
                                        "tarea_nombre" => $JwtAuth->encriptar($row_tar_name),
                                        "tarea_descripcion" => $JwtAuth->encriptar($row_tar_desc),
                                        "tarea_prioridad" => "baj",
                                        "tarea_lider" => end($row_tar_lider)->id,
                                        "template" => end($selectPlantilla)->id,
                                        "tarea_status" => TRUE
                                    )
                                );
                                
                                if ($insertTarea){
                                    $selectTareaPlant = DB::select("SELECT tar.id FROM sos_templates_proyectos_tareas AS tar JOIN sos_templates_proyectos AS template 
                                        WHERE tar.token_tarea = ? AND tar.template = template.id AND template.template_token = ?",[$tarea_token,$plantilla_token]);
                                    
                                    if (count($row_tar_team) > 0){
                                        for ($j = 0; $j < count($row_tar_team); $j++){
                                            $team_tkn = $row_tar_team[$j];
                                            $row_tar_desc = $plantillaListaTareas[$i]["descripcion"];
                                            
                                            $query_tar_team = DB::select("SELECT pers.id,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                                WHERE pers.empleado_token = ? AND pers.id = users.empleado",[$team_tkn]);
                                            
                                            $insertEquipo = DB::table('sos_templates_proyectos_tareas_team')->insert(array("tarea" => end($selectTareaPlant)->id,"personal" => end($query_tar_team)->id));
                                        } 
                                    } 
                                }
                            } 
                        }
                    
                        $dataMensaje = array(
                            'message' => 'Plantilla registrada con el folio '.$folio_template,
                            "code" => 200,
                            'status' => 'success'
                        );

                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                        );
                    }
                } else {
                    if (!isset($plantillaTitulo) || empty($plantillaTitulo) || !preg_match($patron,$plantillaTitulo)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Error en registro de nombre de proyecto, verifique su información'
                        );
                    }
                    if (!is_bool($plantillaEvdUpload)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Error en petición de carga de evidencias, verifique su información'
                        );
                    }
                    if (!is_bool($plantillaEvdDelete)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Error en petición de eliminación de evidencias, verifique su información e intente nuevamente'
                        );
                    }
                    if (!isset($plantillaResponsable) || empty($plantillaResponsable)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                        );
                    }
                    if (count($plantillaListaTareas) == 0) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Error en lista de tareas, verifique su información e intente nuevamente'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    } 

//proyectos  
    //permisos
        public function permisosProyectos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    
                    $listaPermisos = DB::table("module_proyectos_settings AS conf")
                    ->join("main_empresas AS emp","conf.empresa","=","emp.id")
                    ->join("teci_usuarios_catalogo AS users","conf.usuario","=","users.id")
                    ->where([
                        "emp.empresa_token" => $usuario->empresa_token,
                        "users.usuario_token" => $usuario->user_token
                    ])->get();
                    
                    if (count($listaPermisos) > 0) {
                        foreach ($listaPermisos as $value) {
                            $permisos_proyectos = false;
                            $permisos_tareas = false;
                            $permisos_informes = false;
                            $permisos_eliminar = false;
                            $permisos_ver_docs = false;
                            
                            if ($value->proyectos == TRUE) $permisos_proyectos = true;
                            if ($value->tareas == TRUE) $permisos_tareas = true;    
                            if ($value->informes == TRUE) $permisos_informes = true;    
                            if ($value->eliminar == TRUE) $permisos_eliminar = true;
                            if ($value->ver_docs == TRUE) $permisos_ver_docs = true;
        
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "permisos_proyectos" => $permisos_proyectos, 
                                "permisos_tareas" => $permisos_tareas,
                                "permisos_informes" => $permisos_informes,
                                "permisos_eliminar" => $permisos_eliminar,
                                "permisos_ver_docs" => $permisos_ver_docs,
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            "message" => "error al obtener la lista de permisos",
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
    //registro
        public function registrarProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $json_data = $request->input('json');
            $parametros = json_decode($json_data);
            $parametrosArray = json_decode($json_data,true);
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'nameProyecto' => 'required|string',
                    'descripProyecto' => 'required|string',    
                    'abrev_cliente' => 'required|string',
                    'clienteProyecto' => 'required|string',
                    'prioridad_proy' => 'required|string',
                    'fechaFinProyecto' => 'required|string',   
                    'token_empleado_inside' => 'required|string',
                    'personalEquipo' => 'array',
                    'upload_evidencias' => 'boolean',
                    'delete_evidencias' => 'boolean',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    //$patron = '/[aA-zZ_]/';
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $patronFecha = '/^[0-9-]*$/';
                    $patronHora = '/^[0-9:]*$/';
    
                    $validaTarea = false;
    
                    $nameProyecto = $parametrosArray['nameProyecto'];
                    $descripProyecto = $parametrosArray['descripProyecto'];
                    $abrev_cliente = $parametrosArray['abrev_cliente'];
                    $cliente = $parametrosArray['clienteProyecto'];
                    $prioridadProy = $parametrosArray['prioridad_proy'];
                    $fechaFinProyecto = $parametrosArray['fechaFinProyecto'];
                    $finaliza_proy = explode("T",$parametrosArray["fechaFinProyecto"]);
                    $fecha_fin = $finaliza_proy[0];
                    $hora_fin = $finaliza_proy[1];
                    $token_empleado_inside = $parametrosArray['token_empleado_inside'];
                    $personalEquipo = $parametrosArray['personalEquipo'];
                    $upload_evidencias = $parametrosArray['upload_evidencias'];
                    $delete_evidencias = $parametrosArray['delete_evidencias'];
                    //echo $prioridadProy; exit;
                    
                    if (isset($nameProyecto) && !empty($nameProyecto) && preg_match($patron,$nameProyecto) &&
                        isset($descripProyecto) && !empty($descripProyecto) && preg_match($patron,$descripProyecto) &&
                        isset($fechaFinProyecto) && !empty($fechaFinProyecto) && preg_match($patronFecha,$fecha_fin) &&
                        preg_match($patronHora,$hora_fin) && isset($cliente) && !empty($cliente) && preg_match($patron,$cliente) &&
                        isset($abrev_cliente) && !empty($abrev_cliente) && preg_match($patron,$abrev_cliente) &&
                        isset($token_empleado_inside) && !empty($token_empleado_inside)) {
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                            
                        $validaTarea = true;
                        $fecha_sistema = time();
                        $fecha_inicio = time();
                        
                        $token_proyecto = $JwtAuth->encriptarToken($fecha_sistema.$fecha_inicio.$fechaFinProyecto.$nameProyecto.$descripProyecto.$fechaFinProyecto.$cliente); 
    
                        $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                            FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN teci_usuarios_catalogo AS users WHERE fold.apppr_proyectos = TRUE 
                            AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                            AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$usuario->empresa_token,$usuario->user_token]);
                   
                        if (count($folioSistema) == 1) {
                            if ($folioSistema[0]->folio == 1000000000) {
                                $post_folio_db = DB::select("SELECT post_folio FROM module_proyectos 
                                    WHERE id = (SELECT Max(catbuy.id) FROM compras AS catbuy 
                                    JOIN main_empresas AS emp JOIN empresapersonal AS empper 
                                    JOIN teci_usuarios_catalogo AS users
                                    WHERE catbuy.comprador = emp.id AND emp.empresa_token = ?
                                    AND emp.id = empper.empresa AND empper.usuario = users.id AND users.usuario_token = ?)",
                                    [$usuario->empresa_token,$usuario->user_token]);
    
                                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
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
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($folio_nuevo);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                        }
                        
                        $nameProyecto = str_replace(".diagon.","/",$nameProyecto);
                        $nameProyecto = str_replace(".porcent.","%",$nameProyecto);
                        $nameProyecto = str_replace(".ampersand.","&",$nameProyecto);
                        $nameProyecto = str_replace(".pessos.","$",$nameProyecto);
                        
                        $descripProyecto = str_replace(".diagon.","/",$descripProyecto);
                        $descripProyecto = str_replace(".porcent.","%",$descripProyecto);
                        $descripProyecto = str_replace(".ampersand.","&",$descripProyecto);
                        $descripProyecto = str_replace(".pessos.","$",$descripProyecto);
    
                        $tareaHome = new ModeloProyectosInsert(); 
                        $tareaHome->token_proyecto = $token_proyecto; 
                        $tareaHome->folio = $folio_nuevo;   
                        $tareaHome->post_folio = $post_folio;   
                        $tareaHome->fecha_sistema = $fecha_sistema;
                        $tareaHome->proyecto_name = $JwtAuth->encriptar($nameProyecto);    
                        $tareaHome->descripcion = $JwtAuth->encriptar($descripProyecto);      
                        $tareaHome->fecha_inicio = $fecha_inicio; 
                        $tareaHome->fecha_fin = $JwtAuth->convierteFechaEpoc($fechaFinProyecto);       
                        $tareaHome->abrev_cliente = $abrev_cliente;
                        $tareaHome->cliente = $JwtAuth->encriptar($cliente);  
                        $tareaHome->prioridad_proyecto = $prioridadProy;
                        $tareaHome->upload_evidencias = $upload_evidencias;
                        $tareaHome->delete_evd_perm = $delete_evidencias;
                        $tareaHome->empresa = $selectEmp[0]->id;     
                        $tareaHome->status_proyecto = TRUE;      
                        $tareaHome->fecha_delete_pry = NULL;
                        $insertTareaHome = $tareaHome->save();
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($insertTareaHome) {
                            $JwtAuth->insertBitacoraActividad('tareas','apppr_proyectos','registro',$folio_proy,
                            'registro en tareas programadas',$usuario->empresa_token,$usuario->user_token);
                    
                            if (count($folioSistema) == 0) {
                                $insertSistema = DB::table('sos_last_folders')
                                ->insert(
                                    array(
                                        "apppr_proyectos" => TRUE, 
                                        "folder" => 1, 
                                        "post_folder" => $post_folio,
                                        "empresa" => $selectEmp[0]->id,
                                    )
                                );
                            } else {
                                $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp","sos_last_folders.empresa","=","emp.id")
                                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                                ->where([
                                    'sos_last_folders.apppr_proyectos' => TRUE,
                                    'emp.empresa_token' => $usuario->empresa_token,
                                    'users.usuario_token' => $usuario->user_token,
                                ])
                                ->limit(1)->update(
                                    array(
                                        'sos_last_folders.folder' => $folio_nuevo,
                                        'sos_last_folders.post_folder' => $post_folio,
                                    )
                                );
                            }
                            
                            $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                            JURI_EventosController::registraEventoProyectos(
                                $selectProyecto[0]->id,
                                NULL,
                                1,
                                'fecha de finalización',
                                $JwtAuth->convierteFechaEpoc($fecha_fin),
                                $selectEmp[0]->id
                            );
                            
                            $select_creador_proyecto = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE users.empleado = pers.id 
                                AND users.usuario_token = ?",[$usuario->user_token]);
                                
                            $select_lider_proyecto = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$token_empleado_inside]);
                            
                            $insertAlertaCreador = DB::table('module_proyectos_responsable')->insert(
                                array("proyecto" => $selectProyecto[0]->id,"personal" => $select_creador_proyecto[0]->id,"tipo_pp" => "cr"));
                                
                            if ($select_creador_proyecto[0]->id != $select_lider_proyecto[0]->id) {
                                $insertAlertaLider = DB::table('module_proyectos_responsable')->insert(
                                    array("proyecto" => $selectProyecto[0]->id,"personal" => $select_lider_proyecto[0]->id,"tipo_pp" => "li"));
                            }
                               
                            if (count($personalEquipo) != 0){
                                for ($i = 0; $i < count($personalEquipo); $i++){
                                    $tknEquipo = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$personalEquipo[$i]]); 
                                    $insertEquipo = DB::table('module_proyectos_responsable')
                                    ->insert(
                                        array(
                                            "proyecto" => $selectProyecto[0]->id,
                                            "personal" => $tknEquipo[0]->id,
                                            "tipo_pp" => "eq"
                                        )
                                    ); 
                                } 
                            }    
                                
                            $titulo_alerta = "Ha registrado un nuevo proyecto con el folio ".$folio_proy;
                            $JwtAuth->insertNotifLi("Nuevo proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$select_creador_proyecto[0]->id,NULL);
                              
                            if (count($personalEquipo) != 0){
                                for ($i = 0; $i < count($personalEquipo); $i++){
                                    $equipoToken = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",
                                        [$personalEquipo[$i]]);
                                        
                                    $titulo_alerta = $JwtAuth->encriptar("Te ha registrado como equipo de trabajo para el proyecto con el folio ".$folio_proy);
                                    $tkn_notif = $JwtAuth->encriptarToken($token_proyecto,$titulo_alerta,
                                        $selectEmp[0]->id,$selectEmp[0]->userr,$personalEquipo[$i]);
                                
                                    $insertAlertaProyecto = DB::table('teci_notificaciones')
                                    ->insert(
                                        array(
                                            "token_notificacion" => $tkn_notif,
                                            "fecha_notificacion" => time(),
                                            "type" => "gespr",
                                            "asunto" => "Nuevo proyecto",
                                            "titulo" => $titulo_alerta,
                                            "proyecto" => $selectProyecto[0]->id,
                                            "tarea" => NULL,
                                            "informe" => NULL,
                                            "control" => NULL,
                                            "empresa" => $selectEmp[0]->id,
                                            "emisor" => $selectEmp[0]->userr,
                                            "receptor" => $equipoToken[0]->id,
                                            "status_recibe" => FALSE,
                                            "status_delete" => TRUE,
                                            "visto" => FALSE,
                                        )
                                    );
                                    
                                    $selectTelEq = DB::select("SELECT tel_pers_resp.cod_pais AS pais_code,
                                    tel_pers_resp.telefono AS phone FROM sos_personas_telefonos AS tel_pers_resp 
                                    JOIN vhum_empleados_catalogo AS pers_resp WHERE tel_pers_resp.habilitado = TRUE AND tel_pers_resp.personal = pers_resp.id 
                                    AND pers_resp.empleado_token = ?",[$personalEquipo[$i]]);
                                    
                                    if (count($selectTelEq) > 0) {
                                        foreach ($selectTelEq as $valPhone){
                                            $mensaje = "Actualización de pyoyecto: ".$nameProyecto;
                                            $token_emisor = $valAlert->empleado_token;
                                            $emisor = $JwtAuth->desencriptar($valAlert->paterno)." ".$JwtAuth->desencriptar($valAlert->materno)." ".$JwtAuth->desencriptar($valAlert->nombre);
                                            $phone_numero = "+".$valPhone->pais_code.$JwtAuth->desencriptar($valPhone->phone);
                                            $JwtAuth->enviaSMS($phone_numero,$emisor.': '.$mensaje);
                                        }
                                    }
                                    
                                }
                            } 
                        
                            $dataMensaje = array(
                                'message' => 'Proyecto registrado con el folio '.$folio_proy,
                                "code" => 200,
                                'status' => 'success'
                            );
    
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Esta proyecto no fue registrado debido a errores internos, intente nuevamente'
                            );
                        }
                    } else {
                        if (!isset($nameProyecto) || empty($nameProyecto) || !preg_match($patron,$nameProyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de nombre de proyecto, verifique su información'
                            );
                        }
                        if (!isset($descripProyecto) || empty($descripProyecto) || !preg_match($patron,$descripProyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de descripción para proyecto, verifique su información'
                            );
                        }
                        if (!isset($fecha_fin) || empty($fecha_fin) || !preg_match($patronFecha,$fecha_fin)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en fecha de finalización de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($cliente) || empty($cliente) || !preg_match($patron,$cliente)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de cliente para proyecto, verifique su información'
                            );
                        }
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
    
    //listas
        public function lastProyectCreated(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE users.empleado = pers.id AND users.usuario_token = ?",[$usuario->user_token]);
                    
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->limit(1)->get();
                        //echo " 22 ";
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp WHERE proy.status_proyecto = TRUE 
                            AND proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                                SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                                WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                                AND users.usuario_token = ?) order by folio DESC limit 1",[$usuario->empresa_token,$usuario->user_token]);
                        //echo " 24 ";
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_fin_plana = "";
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                            
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                            
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }
                        
                        //inicio - fin
                            $arrayRecalendar = array();
                            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            if (count($selectRecalendar) > 0) {
                                $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                    WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                                
                                $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                                $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                                $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                                
                                $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                    pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                    WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                    tar.token_proyecto = ?",[$value->token_proyecto]);
                                
                                foreach ($listaRecalendar as $valRecal) {
                                    if ($valRecal->empleado_token == $value->empleado_token) {
                                        $personal_opera = "tú";
                                    } else {
                                        $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                            $JwtAuth->desencriptar($valRecal->materno)." ".
                                            $JwtAuth->desencriptar($valRecal->nombre);
                                    }
                                    //echo $personal_opera." ";
                                    $row = array(
                                       "token_calendarizacion" => $valRecal->token_calendarizacion,
                                       "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                       "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                       "personal_opera" => $personal_opera,
                                    );
                                    $arrayRecalendar[] = $row;
                                }
                            } else {
                                $fecha_fin_plana = $value->fecha_fin;
                                $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                                $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                            }
                        
                        //jerarquias de usuario 
                            if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                                if ($value->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($value->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($value->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }   
                            } else {
                                $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                                ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                                ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                                ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                ->where([
                                    'proy.token_proyecto' => $value->token_proyecto,
                                    'users.usuario_token' => $usuario->user_token
                                ])->get();
                                
                                if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }
                            }
                        
                            $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            if (count($selectLider) == 1) {
                                $token_lider = $selectLider[0]->empleado_token;
                                $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                    $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectLider[0]->nombre);
                            } else {
                                $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                $token_lider = $selectCr[0]->empleado_token;
                                $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->nombre);
                            }
                            
                            $equipoTrabajoProyecto = array();
                            $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            foreach($selectEquipo as $valEquipo){
                                $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                    $JwtAuth->desencriptar($valEquipo->materno)." ".
                                    $JwtAuth->desencriptar($valEquipo->nombre);
                                
                                $rowEQ = array(
                                    "token_pers_equipo" => $valEquipo->empleado_token,
                                    "nombre_integ" => ucwords($nombre_integ),
                                    "selected" => false,
                                );
                                $equipoTrabajoProyecto[] = $rowEQ;
                            }
                            
                        //tareas    
                            $tar_terminadas = 0;
                            $tareaList = DB::table("module_proyectos_tareas AS subtar")
                            ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                            ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                            ->orderBy("subtar.id","DESC")->get();
                             
                            $tar_total = count($tareaList);
                            
                            foreach ($tareaList as $vTar) {
                                if ($vTar->realizacion == TRUE) {
                                    $tar_terminadas++;
                                }
                            }
                        
                        //status_proyecto
                            if ($tar_total > 0) {
                                if ($tar_total == $tar_terminadas) {
                                    $proyecto_status = "finish";
                                    if ($fecha_fin_plana > $time_fin) {
                                        $paloma_proyecto = "green";
                                    } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                        $paloma_proyecto = "yellow";
                                    } else if ($fecha_fin_plana <= time()){
                                        $paloma_proyecto = "red";
                                    }
                                } else {
                                    $paloma_proyecto = "";
                                    if ($fecha_fin_plana > $time_fin) {
                                        $proyecto_status = "green";
                                    } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                        $proyecto_status = "yellow";
                                    } else if ($fecha_fin_plana <= time()){
                                        $proyecto_status = "red";
                                    }
                                }
                            } else {
                                $paloma_proyecto = "";
                                $proyecto_status = "black";
                            }
                        
                        $rowProyecto = array(
                            //proyecto
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //inicio - fin
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "equipo_trabajo" => $equipoTrabajoProyecto,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        $arrayProyectos[] = $rowProyecto;
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDeleted(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                    
                    if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => FALSE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp WHERE proy.status_proyecto = FALSE 
                            AND proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                                SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                                WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                                AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $time_fin = time()+(86400*5);
                        
                        if ($value->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                        }
                        
                        if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }                        
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                            $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                            $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
    
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "abrev_cliente" => $value->abrev_cliente,
                            "cliente" => $JwtAuth->desencriptar($value->cliente),
                            "folio_proy" => $folio_proy,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => $nombre_lider,
                            "fecha_delete" => date('d-m-Y H:i:s',$value->fecha_delete_pry),
                        );
                        $arrayProyectos[] = $rowProyecto;
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalTareas' => count($projectList),
                        'proyectos' => $arrayProyectos,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function restaurarProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    //echo $token_proyecto; exit; 
                    if (isset($token_proyecto) && !empty($token_proyecto)) {
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                            $selectProyecto = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                            ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->where([
                                'mod_proy.token_proyecto' => $token_proyecto,
                                'resp_pr.tipo_pp' => 'cr',
                                'emp.empresa_token' => $usuario->empresa_token,
                            ])->get();
                        } else {
                            $selectProyecto = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                            ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'mod_proy.token_proyecto' => $token_proyecto,
                                'resp_pr.tipo_pp' => 'cr',
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                        }
                        
                        foreach ($selectProyecto as $proyDel) {
                            $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$proyDel->token_proyecto]);
                            
                            $selectLider = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado
                                FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.id = tel_pers_resp.personal",[$proyDel->token_proyecto]);
        
                            if ($proyDel->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($proyDel->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($proyDel->folio).'-'.$proyDel->post_folio;
                            }
                        
                            $titulo_alerta = "Ha restaurado el proyecto con folio ".$folio_proy;
                            if (count($selectLider) == 1) {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            
                            $JwtAuth->insertNotifEqAll("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            
                            $cambioProyecto = ModuleProyectosModelo::where(['mod_proy.token_proyecto' => $proyDel->token_proyecto])
                            ->limit(1)->update(
                                array(
                                    'mod_proy.status_proyecto' => TRUE,
                                    'mod_proy.fecha_delete_pry' => NULL,
                                )
                            );
                            
                            if ($cambioProyecto) {
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Proyecto restaurado',
                                );   
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'proyecto no restaurado, intente nuevamente',
                                );   
                            }
                            
                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'no se encontró referencia de proyecto o este es invalido',
                        ); 
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
        
        public function recoverProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                    
                    if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY") {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.token_proyecto' => $token_proyecto,
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                        //echo " 22 ";
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp WHERE proy.token_proyecto = ? 
                            AND proy.status_proyecto = TRUE AND proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                                SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                                WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                                AND users.usuario_token = ?) order by folio DESC",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        //echo " 24 ";
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_fin_plana = "";
                        //proyecto
                            if ($value->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                            }
                            
                        //evidencias
                            if ($value->upload_evidencias == TRUE) {
                                $evidenciasUpload = true;
                            } else {
                                $evidenciasUpload = false;
                            }
                                
                            if ($value->delete_evd_perm == TRUE) {
                                $evd_delete_perm = true;
                            } else {
                                $evd_delete_perm = false;
                            }     
                            
                        //prioridad
                            $simple_prioridad_proyecto = $value->prioridad_proyecto;
                            if ($value->prioridad_proyecto == "baj") {
                                $text_prioridad_proyecto = "baja";
                            } else if ($value->prioridad_proyecto == "med") {
                                $text_prioridad_proyecto = "media";
                            } else if ($value->prioridad_proyecto == "alt") {
                                $text_prioridad_proyecto = "alta";
                            }    
                            
                        //inicio - fin
                            $arrayRecalendar = array();
                            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                if ($valRecal->empleado_token == $value->empleado_token) {
                                    $personal_opera = "tú";
                                } else {
                                    $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                        $JwtAuth->desencriptar($valRecal->materno)." ".
                                        $JwtAuth->desencriptar($valRecal->nombre);
                                }
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                            } else {
                                $fecha_fin_plana = $value->fecha_fin;
                                $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                                $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                            }
                        
                        //jerarquias de usuario 
                            if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                                if ($value->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($value->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($value->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }   
                            } else {
                                $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                                ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                                ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                                ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                ->where([
                                    'proy.token_proyecto' => $value->token_proyecto,
                                    'users.usuario_token' => $usuario->user_token
                                ])->get();
                                
                                if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }
                            }
                            
                            $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            if (count($selectLider) == 1) {
                                $token_lider = $selectLider[0]->empleado_token;
                                $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                    $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectLider[0]->nombre);
                            } else {
                                $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                $token_lider = $selectCr[0]->empleado_token;
                                $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->nombre);
                            }
                            
                            $ProyToLeader = array();
                            $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                                IF (pers.id in (
                                    SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                        AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                                FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                WHERE pers.id NOT IN (
                                    SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                                AND pers.id NOT IN (
                                    SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                                AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                                [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                                
                            foreach($selectToLeader as $valToLeader){
                                $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                    $JwtAuth->desencriptar($valToLeader->materno)." ".
                                    $JwtAuth->desencriptar($valToLeader->nombre);
                                
                                if ($valToLeader->selected == TRUE) {
                                    $selected = true;
                                } else {
                                    $selected = false;
                                }
                                
                                $rowEQ = array(
                                    "token_empleado" => $valToLeader->empleado_token,
                                    "nombre_completo" => ucwords($nombre_integ),
                                    "selected" => $selected,
                                );
                                $ProyToLeader[] = $rowEQ;
                            }
                            
                            $eqTrabajoProyMin = array();
                            $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            foreach($selectEquipo as $valEquipo){
                                $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                    $JwtAuth->desencriptar($valEquipo->materno)." ".
                                    $JwtAuth->desencriptar($valEquipo->nombre);
                                
                                $rowEQ = array(
                                    "token_pers_equipo" => $valEquipo->empleado_token,
                                    "nombre_integ" => ucwords($nombre_integ),
                                    "selected" => false,
                                );
                                $eqTrabajoProyMin[] = $rowEQ;
                            }
                            
                            $eqTrabajoProyMax = array();
                            $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                                IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                                WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                                FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                WHERE pers.id NOT IN (
                                    SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                                AND pers.id NOT IN (
                                    SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                                AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                                [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                                
                            foreach($selectEqTrabajoProyMax as $valEqMax){
                                $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                    $JwtAuth->desencriptar($valEqMax->materno)." ".
                                    $JwtAuth->desencriptar($valEqMax->nombre);
                                
                                if ($valEqMax->selected == TRUE) {
                                    $selected = true;
                                } else {
                                    $selected = false;
                                }
                                
                                $rowEQ = array(
                                    "token_empleado" => $valEqMax->empleado_token,
                                    "nombre_completo" => ucwords($nombre_integ),
                                    "selected" => $selected,
                                );
                                $eqTrabajoProyMax[] = $rowEQ;
                            }
                            
                        //tareas    
                            $tar_terminadas = 0;
                            $tareaList = DB::table("module_proyectos_tareas AS subtar")
                            ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                            ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                            ->orderBy("subtar.id","DESC")->get();
                             
                            $tar_total = count($tareaList);
                            
                            foreach ($tareaList as $vTar) {
                                if ($vTar->realizacion == TRUE) {
                                    $tar_terminadas++;
                                }
                            }
                        
                        //status_proyecto
                            if ($tar_total > 0) {
                                if ($tar_total == $tar_terminadas) {
                                    $proyecto_status = "finish";
                                    if ($fecha_fin_plana > $time_fin) {
                                        $paloma_proyecto = "green";
                                    } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                        $paloma_proyecto = "yellow";
                                    } else if ($fecha_fin_plana <= time()){
                                        $paloma_proyecto = "red";
                                    }
                                } else {
                                    $paloma_proyecto = "";
                                    if ($fecha_fin_plana > $time_fin) {
                                        $proyecto_status = "green";
                                    } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                        $proyecto_status = "yellow";
                                    } else if ($fecha_fin_plana <= time()){
                                        $proyecto_status = "red";
                                    }
                                }
                            } else {
                                $paloma_proyecto = "";
                                $proyecto_status = "black";
                            }
                        
                        $rowProyecto = array(
                            //proyecto
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => $folio_proy,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //inicio - fin
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        $arrayProyectos[] = $rowProyecto;
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function removerProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    if (isset($token_proyecto) && !empty($token_proyecto)) {
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                            $selectProyecto = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                            ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->where([
                                'mod_proy.token_proyecto' => $token_proyecto,
                                'resp_pr.tipo_pp' => 'cr',
                                'emp.empresa_token' => $usuario->empresa_token,
                            ])->get();
                        } else {
                            $selectProyecto = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                            ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'mod_proy.token_proyecto' => $token_proyecto,
                                'resp_pr.tipo_pp' => 'cr',
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                        }
                        
                        foreach ($selectProyecto as $proyDel) {
                            $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$proyDel->token_proyecto]);
                            
                            $selectTarea = DB::table("module_proyectos_tareas AS subtar")
                            ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                            ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                            ->where([
                                'tar.token_proyecto' => $proyDel->token_proyecto,
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token,
                            ])->get();
                            
                            if(count($selectTarea) != 0) {
                                foreach ($selectTarea as $delTarea) {
                                    $informeSubtarea = DB::table("module_proyectos_informes AS inform")
                                    ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                                    ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                                    ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                    ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                    ->where([
                                        'subtar.token_tarea' => $delTarea->token_tarea,
                                        'tar.token_proyecto' => $proyDel->token_proyecto,
                                    ])->get();
                    
                                    if (count($informeSubtarea) != 0) {
                                        foreach ($informeSubtarea as $valInform){
                                            if ($valInform->post_folio_tar == NULL) {
                                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valInform->folio_tarea);
                                            } else {
                                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valInform->folio_tarea).'-'.$valInform->post_folio_tar;
                                            }
                                            
                                            if ($valInform->post_folio_informe == NULL) {
                                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                                            } else {
                                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                                            }
                    
                                            if ($valInform->evidencia != NULL) {
                                                $filepath = $selectEmp[0]->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf;
                                                Storage::delete("/public/root/".$filepath);
                                            }
                                            $JwtAuth->deleteNotifInf($valInform->token_informe);
                                            $deleteInf = DB::table("module_proyectos_informes AS inf")
                                            ->join("module_proyectos_tareas AS subtar","inf.tarea","=","subtar.id")
                                            ->join("module_proyectos AS tar","inf.proyecto","=","tar.id")
                                            ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                                            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                                            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                                            ->where([
                                                'inf.token_informe' => $valInform->token_informe,
                                                'subtar.token_tarea' => $delTarea->token_tarea,
                                                'tar.token_proyecto' => $token_proyecto,
                                                'emp.empresa_token' => $usuario->empresa_token,
                                                'users.usuario_token' => $usuario->user_token,
                                            ])->limit(1)->delete();
                                            
                                        }
                                    }
                                    
                                    $deleteResp = DB::table('module_proyectos_tarea_responsable AS resp')
                                    ->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
                                    ->join("module_proyectos_tareas AS subtar","resp.tarea","=","subtar.id")
                                    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                                    ->where([
                                        'subtar.token_tarea' => $delTarea->token_tarea,
                                        'tar.token_proyecto' => $token_proyecto,
                                        'emp.empresa_token' => $usuario->empresa_token,
                                        'users.usuario_token' => $usuario->user_token,
                                    ])->limit(1)->delete();
                    
                                    $JwtAuth->deleteNotifTar($delTarea->token_tarea);    

                                    $deleteTarea = DB::table("module_proyectos_eventos AS evnt")
                                    ->join("module_proyectos_tareas AS tar","evnt.evt_tarea","=","tar.id")
                                    ->where(['tar.token_tarea' => $delTarea->token_tarea])->limit(1)->delete();
                    
                                    $deleteTarea = DB::table("module_proyectos_tareas AS subtar")
                                    ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                                    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                                    ->where([
                                        'subtar.token_tarea' => $delTarea->token_tarea,
                                        'tar.token_proyecto' => $token_proyecto,
                                        'emp.empresa_token' => $usuario->empresa_token,
                                        'users.usuario_token' => $usuario->user_token,
                                    ])->limit(1)->delete();
                                }
                            }
                            
                            $selectLider = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado
                                FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.id = tel_pers_resp.personal",[$proyDel->token_proyecto]);
        
                            if ($proyDel->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($proyDel->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($proyDel->folio).'-'.$proyDel->post_folio;
                            }
                        
                            $titulo_alerta = "Ha eliminado permanentemente el proyecto con folio ".$folio_proy;
                            if (count($selectLider) == 1) {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            
                            $JwtAuth->insertNotifEqAll("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            
                            $deleteRespPry = DB::table('module_proyectos_responsable AS resp')
                            ->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
                            ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                            ->where([
                                'tar.token_proyecto' => $token_proyecto,
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token,
                            ])->limit(1)->delete();
                            
                            //$cambioProyecto = ModuleProyectosModelo::where(['mod_proy.token_proyecto' => $proyDel->token_proyecto])
                            //->limit(1)->delete();
                            
                            $cambioProyecto = ModuleProyectosModelo::where(['mod_proy.token_proyecto' => $proyDel->token_proyecto])
                            ->limit(1)->update(
                                array(
                                    'mod_proy.status' => FALSE,
                                    'mod_proy.fecha_delete_pry' => time(),
                                )
                            );
                            
                            if ($cambioProyecto && $deleteRespPry) {
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Proyecto eliminado',
                                );   
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'proyecto no eliminado, intente nuevamente',
                                );   
                            }
                            
                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'no se encontró referencia de proyecto o este es invalido',
                        ); 
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                    
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where(['resp_pr.tipo_pp' => 'cr','emp.empresa_token' => $usuario->empresa_token])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) order by folio DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($value->status_proyecto == TRUE) {
                            $arrayProyectos[] = $rowProyecto;
                        } else {
                            $arrayProyectosDeleted[] = $rowProyecto;
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosAscFecha(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                    
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.fecha_sistema','ASC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.fecha_sistema ASC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                            
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                    
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        if ($value->status_proyecto == TRUE) {
                            $arrayProyectos[] = $rowProyecto;
                        } else {
                            $arrayProyectosDeleted[] = $rowProyecto;
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDescFecha(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.fecha_sistema','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.fecha_sistema DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($value->status_proyecto == TRUE) {
                            $arrayProyectos[] = $rowProyecto;
                        } else {
                            $arrayProyectosDeleted[] = $rowProyecto;
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosAscBlack(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','ASC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio ASC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($tar_total == 0) {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                        
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDescBlack(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
    
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($tar_total == 0) {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosAscGreen(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','ASC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio ASC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "green") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                    }
                    
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDescGreen(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "green") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
            
        public function listaProyectosAscYellow(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','ASC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio ASC",[$usuario->empresa_token,$usuario->user_token]);
                    }

                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "yellow") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDescYellow(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "yellow") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                        
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosAscRed(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','ASC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio ASC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );

                        if ($proyecto_status == "red") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                        
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDescRed(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.usuario
                            AND users.usuario_token = ?) ORDER BY proy.folio DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "red") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                        
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosAscFinish(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','ASC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio ASC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "finish") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                        
                    }
                    
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function listaProyectosDescFinish(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProyectos = array();
            $arrayProyectosDeleted = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.status_proyecto' => TRUE,
                            'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token,
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp 
                            WHERE proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                            SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                            AND users.usuario_token = ?) ORDER BY proy.folio DESC",[$usuario->empresa_token,$usuario->user_token]);
                    }
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_delete = $value->fecha_delete_pry != "" ? date('d-m-Y H:i:s',$value->fecha_delete_pry) : null;
                        $post_folio = $value->post_folio != NULL ? "-".$value->post_folio : "";
                        //evidencias
                        $evidenciasUpload = $value->upload_evidencias == TRUE ? true : false;
                        $evd_delete_perm = $value->delete_evd_perm == TRUE ? true : false;
                        //prioridad
                        $simple_prioridad_proyecto = $value->prioridad_proyecto;
                        if ($value->prioridad_proyecto == "baj") {
                            $text_prioridad_proyecto = "baja";
                        } else if ($value->prioridad_proyecto == "med") {
                            $text_prioridad_proyecto = "media";
                        } else if ($value->prioridad_proyecto == "alt") {
                            $text_prioridad_proyecto = "alta";
                        }   
                        
                        //finalización de proyecto
                        $arrayRecalendar = array();
                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                        if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                    $JwtAuth->desencriptar($valRecal->materno)." ".
                                    $JwtAuth->desencriptar($valRecal->nombre);
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                        } else {
                            $fecha_fin_plana = $value->fecha_fin;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                        }
                        
                        //jerarquias de usuario 
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            if ($value->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($value->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($value->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }   
                        } else {
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $value->token_proyecto,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                $creat_lider = 'CR';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                $creat_lider = 'LI';
                            } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                $creat_lider = 'EQ';
                            }
                        }
                        
                        $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        if (count($selectLider) == 1) {
                            $token_lider = $selectLider[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                $JwtAuth->desencriptar($selectLider[0]->nombre);
                        } else {
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            $token_lider = $selectCr[0]->empleado_token;
                            $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                $JwtAuth->desencriptar($selectCr[0]->nombre);
                        }
                        
                        $ProyToLeader = array();
                        $selectToLeader = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                        
                        foreach($selectToLeader as $valToLeader){
                            $nombre_integ = $JwtAuth->desencriptar($valToLeader->paterno)." ".
                                $JwtAuth->desencriptar($valToLeader->materno)." ".
                                $JwtAuth->desencriptar($valToLeader->nombre);
                            
                            if ($valToLeader->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valToLeader->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $ProyToLeader[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMin = array();
                        $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                            people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                            AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                        foreach($selectEquipo as $valEquipo){
                            $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                $JwtAuth->desencriptar($valEquipo->materno)." ".
                                $JwtAuth->desencriptar($valEquipo->nombre);
                            
                            $rowEQ = array(
                                "token_pers_equipo" => $valEquipo->empleado_token,
                                "nombre_integ" => ucwords($nombre_integ),
                                "selected" => false,
                            );
                            $eqTrabajoProyMin[] = $rowEQ;
                        }
                        
                        $eqTrabajoProyMax = array();
                        $selectEqTrabajoProyMax = DB::select("SELECT pers.id,pers.empleado_token,people.paterno,people.materno,people.nombre,
                            IF (pers.id in (SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq'),TRUE,FALSE) AS selected
                            FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            WHERE pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr') 
                            AND pers.id NOT IN (
                                SELECT resp.personal FROM module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li') 
                            AND pers.empleado_name = people.id AND pers.id = empuser.empleado AND empuser.empresa = emp.id AND emp.empresa_token = ?",
                            [$value->token_proyecto,$value->token_proyecto,$value->token_proyecto,$usuario->empresa_token]);
                            
                        foreach($selectEqTrabajoProyMax as $valEqMax){
                            $nombre_integ = $JwtAuth->desencriptar($valEqMax->paterno)." ".
                                $JwtAuth->desencriptar($valEqMax->materno)." ".
                                $JwtAuth->desencriptar($valEqMax->nombre);
                            
                            if ($valEqMax->selected == TRUE) {
                                $selected = true;
                            } else {
                                $selected = false;
                            }
                            
                            $rowEQ = array(
                                "token_empleado" => $valEqMax->empleado_token,
                                "nombre_completo" => ucwords($nombre_integ),
                                "selected" => $selected,
                            );
                            $eqTrabajoProyMax[] = $rowEQ;
                        } 
                        
                        //tareas
                        $tar_terminadas = 0;
                        $tareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                         
                        $tar_total = count($tareaList);
                        foreach ($tareaList as $vTar) {if ($vTar->realizacion == TRUE) $tar_terminadas++;}
                        
                        //status_proyecto
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $proyecto_status = "finish";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma_proyecto = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma_proyecto = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma_proyecto = "red";
                                }
                            } else {
                                $paloma_proyecto = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $proyecto_status = "green";
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $proyecto_status = "yellow";
                                } else if ($fecha_fin_plana <= time()){
                                    $proyecto_status = "red";
                                }
                            }
                        } else {
                            $paloma_proyecto = "";
                            $proyecto_status = "black";
                        }
                        
                        $rowProyecto = array(
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => "PROY-".$JwtAuth->generarFolio($value->folio).$post_folio,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            "descripcion" => strtolower($JwtAuth->desencriptar($value->descripcion)),
                            "fecha_delete" => $fecha_delete,
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //finalización de proyectos
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "listProyToLeader" => $ProyToLeader,
                            "equipo_trabajo_min" => $eqTrabajoProyMin,
                            "equipo_trabajo_max" => $eqTrabajoProyMax,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                            "isProyNewTareaCollapsed" => true,
                            "isProyTareaAntListCollapsed" => "",
                            "isProyTareaDeletedCollapsed" => true,
                            "isProyEdicionCollapsed" => true,
                            "isProyRecalendarCollapsed" => true,
                            "isProyDetalleCollapsed" => true,
                        );
                        
                        if ($proyecto_status == "finish") {
                            if ($value->status_proyecto == TRUE) {
                                $arrayProyectos[] = $rowProyecto;
                            } else {
                                $arrayProyectosDeleted[] = $rowProyecto;
                            }
                        }
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'totalProyectos' => count($arrayProyectos),
                        'proyectos' => $arrayProyectos,
                        'proyectosDeleted' => $arrayProyectosDeleted,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function actualizarProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'nameProyecto' => 'required|string',
                    'descripProyecto' => 'required|string',    
                    'clienteProyecto' => 'required|string',
                    'abrev_cliente' => 'required|string',
                    'prioridad' => 'required|string',
                    'token_empleado_inside' => 'required|string',
                    'upload_evidencias' => 'boolean',
                    'delete_evidencias' => 'boolean',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $patronFecha = '/^[0-9-]*$/';
    
                    $validaTarea = false;
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $nameProyecto = $parametrosArray['nameProyecto'];
                    $descripProyecto = $parametrosArray['descripProyecto'];
                    $cliente = $parametrosArray['clienteProyecto'];
                    $abrev_cliente = $parametrosArray['abrev_cliente'];
                    $prioridad = $parametrosArray['prioridad'];
                    $token_empleado_inside = $parametrosArray['token_empleado_inside'];
                    $upload_evidencias = $parametrosArray['upload_evidencias'];
                    $delete_evidencias = $parametrosArray['delete_evidencias'];
                    
                    if (isset($nameProyecto) && !empty($nameProyecto) && preg_match($patron,$nameProyecto) &&
                        isset($descripProyecto) && !empty($descripProyecto) && preg_match($patron,$descripProyecto) &&
                        isset($cliente) && !empty($cliente) && preg_match($patron,$cliente) &&
                        isset($abrev_cliente) && !empty($abrev_cliente) && preg_match($patron,$abrev_cliente) &&
                        isset($prioridad) && !empty($prioridad) && preg_match($patron,$prioridad) &&
                        isset($token_empleado_inside) && !empty($token_empleado_inside)) {
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT id,folio,post_folio FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                        
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                            
                        $validaTarea = true;
                        $fecha_sistema = time();
                        $fecha_inicio = time();
                        
                        $nameProyecto = str_replace(".diagon.","/",$nameProyecto);
                        $nameProyecto = str_replace(".porcent.","%",$nameProyecto);
                        $nameProyecto = str_replace(".ampersand.","&",$nameProyecto);
                        $nameProyecto = str_replace(".pessos.","$",$nameProyecto);
                        
                        $descripProyecto = str_replace(".diagon.","/",$descripProyecto);
                        $descripProyecto = str_replace(".porcent.","%",$descripProyecto);
                        $descripProyecto = str_replace(".ampersand.","&",$descripProyecto);
                        $descripProyecto = str_replace(".pessos.","$",$descripProyecto);
                        
                        $cambioProyecto = ModuleProyectosModelo::where(
                            ['mod_proy.token_proyecto' => $token_proyecto]
                        )->limit(1)->update(
                            array(
                                'mod_proy.proyecto_name' => $JwtAuth->encriptar($nameProyecto),
                                'mod_proy.descripcion' => $JwtAuth->encriptar($descripProyecto),        
                                'mod_proy.cliente' => $JwtAuth->encriptar($cliente),
                                'mod_proy.abrev_cliente' => $abrev_cliente,
                                'mod_proy.prioridad_proyecto' => $prioridad,
                                'mod_proy.upload_evidencias' => $upload_evidencias,
                                'mod_proy.delete_evd_perm' => $delete_evidencias,
                            )
                        );
                        
                        $selectLider = DB::table('module_proyectos_responsable AS resp')
    					->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'resp.tipo_pp' => 'li',
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
                        
                        $personalToken = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$token_empleado_inside]);
                        
                        if (count($selectLider) == 1) {
    					    if ($selectLider[0]->personal != $personalToken[0]->id) {
    					        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,"Has sido eliminado del proyecto con el folio ".$folio_proy,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
    					    }
                            
                            $cambioLider = DB::table('module_proyectos_responsable AS resp')
    					    ->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    					    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    					    ->where([
    					    	'resp.tipo_pp' => 'li',
    					    	'tar.token_proyecto' => $token_proyecto,
    					    	'emp.empresa_token' => $usuario->empresa_token,
    					    	'users.usuario_token' => $usuario->user_token,
    					    ])
    					    ->limit(1)->update(
    					    	array(
    					    		'resp.personal' => $personalToken[0]->id,
    					    	)
    					    );
    					    
    					    if ($selectLider[0]->personal != $personalToken[0]->id) {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,"Has sido registrado como lider del proyecto con el folio ".$folio_proy,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
    					    }
    					    
    					} else {
    					    $cambioLider = DB::table('module_proyectos_responsable')->insert(array("proyecto" => $selectProyecto[0]->id,"personal" => $personalToken[0]->id,"tipo_pp" => "li"));
    					    if ($cambioLider) {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,"Has sido registrado como lider del proyecto con el folio ".$folio_proy,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
    					    }
    					}
    					
                        if ($cambioProyecto || $cambioLider) {
    					    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,"El proyecto con folio ".$folio_proy." ha sido actualizado",NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                            $dataMensaje = array(
                                'message' => "El proyecto con folio ".$folio_proy." ha sido actualizado",
                                "code" => 200,
                                'status' => 'success'
                            );
    
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Esta proyecto no fue actualizado debido a errores internos, intente nuevamente'
                            );
                        }
                    } else {
                        if (!isset($nameProyecto) || empty($nameProyecto) || !preg_match($patron,$nameProyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de nombre de proyecto, verifique su información'
                            );
                        }
                        if (!isset($descripProyecto) || empty($descripProyecto) || !preg_match($patron,$descripProyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de descripción para proyecto, verifique su información'
                            );
                        }
                        if (!isset($cliente) || empty($cliente) || !preg_match($patron,$cliente)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de cliente para proyecto, verifique su información'
                            );
                        }//isset($prioridad) && !empty($prioridad) && preg_match($patron,$prioridad) &&
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }  
        
        public function quitaLiderProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto)) {
                            
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT id,folio,post_folio FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        
                        $valida_eq_trabajo = false;
                        $txt_alerta = "";
                        $txt_alerta_sweet = "";
                        
                        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,"Hasido eliminado como líder del proyecto con folio ".$folio_proy." ha sido actualizado",NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                        $deleteEquipo = DB::table("module_proyectos_responsable")
                        ->where([
					    	"proyecto" => $selectProyecto[0]->id,
					    	"tipo_pp" => "li",
					    	"tarea" => NULL
					    ])->delete();
					    
                        if ($deleteEquipo) {
                            $valida_eq_trabajo = true;
                            $txt_alerta = "ha eliminado los líder del proyecto con folio ".$folio_proy;
                            $JwtAuth->insertNotifEqAll("Actualización de proyecto",$token_proyecto,$txt_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            $txt_alerta_sweet = "ha eliminado los líderes del proyecto con folio ".$folio_proy;
                            
                            $dataMensaje = array(
                                'message' => $txt_alerta_sweet,
                                "code" => 200,
                                'status' => 'success',
                                'selected' => $valida_eq_trabajo,
                            );
                            
                        } else {
                            $dataMensaje = array(
                                'message' => "personal no registrado",
                                "code" => 200,
                                "status" => "error",
                            ); 
                        }   
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
        
        public function agregarEqTeamProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_empleado_inside' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_empleado_inside = $parametrosArray['token_empleado_inside'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) &&
                        isset($token_empleado_inside) && !empty($token_empleado_inside)) {
                            
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT id,folio,post_folio FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        
                        $valida_eq_trabajo = false;
                        $txt_alerta = "";
                        $txt_alerta_sweet = "";
                        
                        $eqList = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone FROM vhum_empleados_catalogo AS pers
                            JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE pers.empleado_token = ? AND pers.id = tel_pers_resp.personal AND pers.id = resp.personal AND resp.tipo_pp = 'eq'
                            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
                            [$token_empleado_inside,$token_proyecto]);
                        
                        //echo count($eqList); 
                        $tknEquipo = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$token_empleado_inside]);
                        if (count($eqList) == 0) {
                            $insertEquipo = DB::table('module_proyectos_responsable')
                                ->insert(array("proyecto" => $selectProyecto[0]->id,"personal" => $tknEquipo[0]->id,"tipo_pp" => "eq"));
                                
                            if ($insertEquipo) {
                                $valida_eq_trabajo = true;
                                $txt_alerta = "has sido registrado en el equipo de trabajo del proyecto con folio ".$folio_proy;
                                $JwtAuth->insertNotifEqPersonal("Actualización de proyecto",$token_empleado_inside,$token_proyecto,$txt_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $txt_alerta_sweet = "El personal seleccionado ha sido agregado al equipo de trabajo del proyecto con folio ".$folio_proy;
                                
                                $dataMensaje = array(
                                    'message' => $txt_alerta_sweet,
                                    "code" => 200,
                                    'status' => 'success',
                                    'selected' => $valida_eq_trabajo,
                                );
                                
                            } else {
                                $dataMensaje = array(
                                    'message' => "personal no registrado",
                                    "code" => 200,
                                    "status" => "error",
                                ); 
                            }
                        } else {
                            $dataMensaje = array(
                                'message' => "el personal seleccionado ya se encuentra registrado en el equipo de trabajo de este proyecto",
                                "code" => 200,
                                "status" => "error",
                            );
                        }    
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }  
        
        public function eliminarEqTeamProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_empleado_inside' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_empleado_inside = $parametrosArray['token_empleado_inside'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) &&
                        isset($token_empleado_inside) && !empty($token_empleado_inside)) {
                            
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT id,folio,post_folio FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        
                        $valida_eq_trabajo = false;
                        $txt_alerta = "";
                        $txt_alerta_sweet = "";
                        
                        $eqList = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone FROM vhum_empleados_catalogo AS pers
                            JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy 
                            WHERE pers.empleado_token = ? AND pers.id = tel_pers_resp.personal AND pers.id = resp.personal AND resp.tipo_pp = 'eq'
                            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
                            [$token_empleado_inside,$token_proyecto]);
                        
                        //echo count($eqList); 
                        //$tknEquipo = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$token_empleado_inside]);
                        if (count($eqList) != 0) {
                            $teamTareasRelacion = DB::table('module_proyectos_tarea_responsable AS resp')
    					    ->join("module_proyectos_tareas AS tar","resp.tarea","=","tar.id")
    					    ->join("module_proyectos AS proy","resp.proyecto","=","proy.id")
    					    ->join("vhum_empleados_catalogo AS people","resp.personal","=","people.id")
    					    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
    					    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    					    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    					    ->where([
    					    	'proy.token_proyecto' => $token_proyecto,
    					    	'people.empleado_token' => $token_empleado_inside,
    					    	'emp.empresa_token' => $usuario->empresa_token,
    					    	'users.usuario_token' => $usuario->user_token,
    					    ])->get();
                            
                            if (count($teamTareasRelacion) != 0) {
                                foreach ($teamTareasRelacion as $vTeamTar) {
                                    $deleteRelacion = DB::table('module_proyectos_tarea_responsable AS resp')
    					            ->join("module_proyectos_tareas AS tar","resp.tarea","=","tar.id")
    					            ->join("module_proyectos AS proy","resp.proyecto","=","proy.id")
    					            ->join("vhum_empleados_catalogo AS people","resp.personal","=","people.id")
    					            ->join("main_empresas AS emp","proy.empresa","=","emp.id")
    					            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    					            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    					            ->where([
    					            	'proy.token_proyecto' => $token_proyecto,
    					            	'people.empleado_token' => $token_empleado_inside,
    					            	'emp.empresa_token' => $usuario->empresa_token,
    					            	'users.usuario_token' => $usuario->user_token,
    					            ])->limit(1)->delete();
                                }
                            }
                            
                            $txt_alerta = "has sido eliminado del equipo de trabajo del proyecto con folio ".$folio_proy;
                            $JwtAuth->insertNotifEqPersonal("Actualización de proyecto",$token_empleado_inside,$token_proyecto,$txt_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            $deleteTeam = DB::table('module_proyectos_responsable AS resp')
    					    ->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					    ->join("vhum_empleados_catalogo AS people","resp.personal","=","people.id")
    					    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    					    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    					    ->where([
    					    	'resp.tipo_pp' => 'eq',
    					    	'tar.token_proyecto' => $token_proyecto,
    					    	'people.empleado_token' => $token_empleado_inside,
    					    	'emp.empresa_token' => $usuario->empresa_token,
    					    	'users.usuario_token' => $usuario->user_token,
    					    ])->limit(1)->delete();
    					    
    					    if ($deleteTeam) {
    					        $valida_eq_trabajo = false;
    					        $txt_alerta_sweet = "El personal seleccionado ha sido eliminado del equipo de trabajo del proyecto con folio ".$folio_proy;
                                
                                $dataMensaje = array(
                                    'message' => $txt_alerta_sweet,
                                    "code" => 200,
                                    'status' => 'success',
                                    'selected' => $valida_eq_trabajo,
                                );
    					        
    					    } else {
    					        $dataMensaje = array(
                                    'message' => 'personal no eliminado',
                                    "code" => 200,
                                    "status" => "error",
                                );
    					    }
    					    
                        } else {
                            $dataMensaje = array(
                                'message' => 'el personal seleccionado no se encuentra registrado en el equipo de trabajo de este proyecto',
                                "code" => 200,
                                "status" => "error",
                            );
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
        
        public function recalendarizarProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'fecha_recalendariza' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $patronFecha = '/^[0-9-]*$/';
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $fecha_recalendariza = $parametrosArray['fecha_recalendariza'];
    
                    if (isset($token_proyecto) && !empty($token_proyecto) && 
                        isset($fecha_recalendariza) && !empty($fecha_recalendariza) && preg_match($patronFecha,$fecha_recalendariza)) {
                        $fecha_sistema = time(); 
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                            AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,tar.fecha_inicio,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                            WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
                            AND pers.id = users.empleado AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $token_calendarizacion = $JwtAuth->encriptarToken($fecha_sistema,$usuario->user_token,$usuario->empresa_token,$fecha_recalendariza);
                        
                        $epoc_fecha = $JwtAuth->convierteFechaEpoc($fecha_recalendariza);
                        
                        if ($epoc_fecha > $selectProyecto[0]->fecha_inicio && $epoc_fecha > time()) {
                            $insertRecalendar = DB::table('module_proyectos_calendarizacion')
                            ->insert(
                                array(
                                    "token_calendarizacion" => $token_calendarizacion,
                                    "proyecto" => $selectProyecto[0]->id,
                                    "fecha_sistema" => $fecha_sistema,	
                                    "personal_opera" => $selectEmp[0]->userr,	
                                    "fecha_compromiso_nueva" => $JwtAuth->convierteFechaEpoc($fecha_recalendariza),
                                )
                            );
        
                            if ($insertRecalendar) {   
                                $selectEvent = DB::select("SELECT id FROM module_proyectos_eventos WHERE proyecto = ?",[$selectProyecto[0]->id]);
                                
                                if (count($selectEvent) == 0) {
                                    JURI_EventosController::registraEventoProyectos(
                                        $selectProyecto[0]->id,
                                        NULL,
                                        1,
                                        'fecha de finalización',
                                        $fecha_sistema,
                                        $selectEmp[0]->id
                                    );
                                } else {
                                    JURI_EventosController::actualizaEventoProyecto(
                                        $selectProyecto[0]->id,
                                        1,
                                        'fecha de finalización',
                                        $fecha_sistema,
                                        $selectEmp[0]->id
                                    );    
                                }
                                
                                $selectLider = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado 
                                    FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN
                                    module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                    AND pers.id = tel_pers_resp.personal",[$token_proyecto]);
            
                                if ($selectProyecto[0]->post_folio == NULL) {
                                    $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                                } else {
                                    $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                                }
    
                                $titulo_alerta = "Ha recalendarizado el proyecto con folio ".$folio_proy." hasta ".$fecha_recalendariza;
                                if (count($selectLider) == 1) {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } 
                                
                                //$JwtAuth->insertNotifEqAll("Actualización de proyecto",$token_proyecto,$txt_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqAll("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Proyecto recalendarizado'
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Proyecto no recalendarizado, intente nuevamente'
                                );
                            }
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No puede recalendarizar proyecto a una fecha anterior al inicio de este'
                            );
                        }
                        
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de proyecto para recalendarizar'
                            );
                        }
                        if (!isset($fecha_recalendariza) || empty($fecha_recalendariza) || !preg_match($patronFecha,$fecha_recalendariza)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe fecha para recalendarizar'
                            );                   
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function eliminarProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto)) {
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                            $selectProyecto = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                            ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->where([
                                'mod_proy.token_proyecto' => $token_proyecto,
                                'resp_pr.tipo_pp' => 'cr',
                                'emp.empresa_token' => $usuario->empresa_token,
                            ])->get();
                        } else {
                            $selectProyecto = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                            ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'mod_proy.token_proyecto' => $token_proyecto,
                                'resp_pr.tipo_pp' => 'cr',
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                        }
                        
                        foreach ($selectProyecto as $proyDel) {
                            $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$proyDel->token_proyecto]);
                            
                            $selectLider = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado
                                FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ?
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.id = tel_pers_resp.personal",[$proyDel->token_proyecto]);
        
                            if ($proyDel->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($proyDel->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($proyDel->folio).'-'.$proyDel->post_folio;
                            }
                        
                            $titulo_alerta = "Ha eliminado el proyecto con folio ".$folio_proy;
                            if (count($selectLider) == 1) {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            
                            $JwtAuth->insertNotifEqAll("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            
                            $cambioProyecto = ModuleProyectosModelo::where(['mod_proy.token_proyecto' => $proyDel->token_proyecto])
                            ->limit(1)->update(
                                array(
                                    'mod_proy.status_proyecto' => FALSE,
                                    'mod_proy.fecha_delete_pry' => time(),
                                )
                            );
                            
                            if ($cambioProyecto) {
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Proyecto eliminado',
                                );   
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'proyecto no eliminado, intente nuevamente',
                                );   
                            }
                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'no se encontró referencia de proyecto o este es invalido',
                        ); 
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
    //detalle
        public function nuevoNombreProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $time_fin = time()+(86400*5);
                    
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                        ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->where([
                            'mod_proy.token_proyecto' => $token_proyecto,
                            //'resp_pr.tipo_pp' => 'cr',
                            'emp.empresa_token' => $usuario->empresa_token
                        ])                
                        ->orderBy('mod_proy.folio','DESC')->get();
                        //echo " 22 ";
                    } else {
                        $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp WHERE proy.token_proyecto = ?
                            AND proy.status_proyecto = TRUE AND proy.empresa = emp.id AND emp.empresa_token = ? AND proy.id IN (
                                SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                                WHERE resp_pr.personal = pers.id AND pers.id = users.empleado
                                AND users.usuario_token = ?) order by folio DESC",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        //echo " 24 ";
                    }
                    
                    
                    foreach ($projectList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $fecha_fin_plana = "";
                        //proyecto
                            if ($value->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                            }
                            
                        //evidencias
                            if ($value->upload_evidencias == TRUE) {
                                $evidenciasUpload = true;
                            } else {
                                $evidenciasUpload = false;
                            }
                                
                            if ($value->delete_evd_perm == TRUE) {
                                $evd_delete_perm = true;
                            } else {
                                $evd_delete_perm = false;
                            }                             
                            
                        //prioridad
                            $simple_prioridad_proyecto = $value->prioridad_proyecto;
                            if ($value->prioridad_proyecto == "baj") {
                                $text_prioridad_proyecto = "baja";
                            } else if ($value->prioridad_proyecto == "med") {
                                $text_prioridad_proyecto = "media";
                            } else if ($value->prioridad_proyecto == "alt") {
                                $text_prioridad_proyecto = "alta";
                            }
                            
                        //inicio - fin
                            $arrayRecalendar = array();
                            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            if (count($selectRecalendar) > 0) {
                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                            
                            $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            $date_end_proy_epoc = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                            $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                            
                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion,rectar.fecha_sistema,rectar.fecha_compromiso_nueva,
                                pers.empleado_token,people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
                                WHERE rectar.personal_opera = pers.id AND pers.empleado_name = people.id AND rectar.proyecto = tar.id AND 
                                tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            foreach ($listaRecalendar as $valRecal) {
                                if ($valRecal->empleado_token == $value->empleado_token) {
                                    $personal_opera = "tú";
                                } else {
                                    $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                        $JwtAuth->desencriptar($valRecal->materno)." ".
                                        $JwtAuth->desencriptar($valRecal->nombre);
                                }
                                //echo $personal_opera." ";
                                $row = array(
                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                   "personal_opera" => $personal_opera,
                                );
                                $arrayRecalendar[] = $row;
                            }
                            } else {
                                $fecha_fin_plana = $value->fecha_fin;
                                $date_end_proy_epoc = date('d-m-Y H:i:s',$value->fecha_fin);
                                $date_end_proy_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                            }
                        
                        //jerarquias de usuario 
                            if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
                                //echo 'creat_lider == CR';exit;
                                if ($value->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($value->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($value->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }   
                            } else {
                                $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                                ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                                ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                                ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                ->where([
                                    'proy.token_proyecto' => $value->token_proyecto,
                                    'users.usuario_token' => $usuario->user_token
                                ])->get();
                                
                                if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }
                            }
                            
                            $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            if (count($selectLider) == 1) {
                                $token_lider = $selectLider[0]->empleado_token;
                                $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".
                                    $JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectLider[0]->nombre);
                            } else {
                                $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                $token_lider = $selectCr[0]->empleado_token;
                                $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->nombre);
                            }
                            
                            $equipoTrabajoProyecto = array();
                            $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            foreach($selectEquipo as $valEquipo){
                                $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                    $JwtAuth->desencriptar($valEquipo->materno)." ".
                                    $JwtAuth->desencriptar($valEquipo->nombre);
                                
                                $rowEQ = array(
                                    "token_pers_equipo" => $valEquipo->empleado_token,
                                    "nombre_integ" => ucwords($nombre_integ),
                                    "selected" => false,
                                );
                                $equipoTrabajoProyecto[] = $rowEQ;
                            }
                            
                        //tareas    
                            $tar_terminadas = 0;
                            $tareaList = DB::table("module_proyectos_tareas AS subtar")
                            ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                            ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                            ->orderBy("subtar.id","DESC")->get();
                             
                            $tar_total = count($tareaList);
                            
                            foreach ($tareaList as $vTar) {
                                if ($vTar->realizacion == TRUE) {
                                    $tar_terminadas++;
                                }
                            }
                        
                        //status_proyecto
                            if ($tar_total > 0) {
                                if ($tar_total == $tar_terminadas) {
                                    $proyecto_status = "finish";
                                    if ($fecha_fin_plana > $time_fin) {
                                        $paloma_proyecto = "green";
                                    } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                        $paloma_proyecto = "yellow";
                                    } else if ($fecha_fin_plana <= time()){
                                        $paloma_proyecto = "red";
                                    }
                                } else {
                                    $paloma_proyecto = "";
                                    if ($fecha_fin_plana > $time_fin) {
                                        $proyecto_status = "green";
                                    } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                        $proyecto_status = "yellow";
                                    } else if ($fecha_fin_plana <= time()){
                                        $proyecto_status = "red";
                                    }
                                }
                            } else {
                                $paloma_proyecto = "";
                                $proyecto_status = "black";
                            }
                        
                        $dataMensaje = array(
                            "status" => "success",
                            "code" => 200,
                            //proyecto
                            "token_proyecto" => $value->token_proyecto,
                            "folio_proy" => $folio_proy,
                            "proyecto" => $JwtAuth->desencriptar($value->proyecto_name),
                            //cliente
                            "abrev_cliente" => $value->abrev_cliente,
                            "nombre_cliente" => $JwtAuth->desencriptar($value->cliente),
                            //evidencias
                            "upload_evidencias" => $evidenciasUpload,
                            "evd_delete_perm" => $evd_delete_perm,
                            //prioridad
                            "simple_prioridad_proyecto" => $simple_prioridad_proyecto,
                            "text_prioridad_proyecto" => $text_prioridad_proyecto,
                            //inicio - fin
                            "fecha_inicio" => date('d-m-Y H:i:s',$value->fecha_inicio), 
                            "date_end_proy_epoc" => $date_end_proy_epoc, 
                            "date_end_proy_html" => $date_end_proy_html,
                            "recalendarizacion" => $arrayRecalendar,
                            //jerarquias de usuario 
                            "creat_lider" => $creat_lider,
                            "token_lider" => $token_lider,
                            "nombre_lider" => "Lider: ".$nombre_lider,
                            "equipo_trabajo" => $equipoTrabajoProyecto,
                            //tareas 
                            "tareas" => "Tareas: ".$tar_terminadas."/".$tar_total,
                            //status_proyecto
                            "proyecto_status" => $proyecto_status,
                            "paloma_proyecto" => $paloma_proyecto,
                            //detalle_proyecto
                            "detalle_proyecto" => [],
                            "status_detalle" => false,
                        );
                    }
    
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function detalleProyecto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $contentProyecto = array();
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    //echo setlocale(LC_TIME, 'spanish');
                    
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id,pers.empleado_token FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                    
                    $tareaList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.empleado","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                    ->where([
                        'mod_proy.status_proyecto' => TRUE,
                        'mod_proy.token_proyecto' => $parametrosArray['token_proyecto'],
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token
                    ])->get();
                    
                    foreach ($tareaList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        //nombre_proyecto
                            $nombre_proyecto = $JwtAuth->utf8Validate($JwtAuth->desencriptar($value->proyecto_name));
                            
                            if ($value->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                            }
                            
                            $descripcion_proyecto = $JwtAuth->utf8Validate($JwtAuth->desencriptar($value->descripcion));
                         
                        //creador   
                            $selectCr = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                            $token_creador = $selectCr[0]->empleado_token;
                            if ($token_creador == $id_pers[0]->empleado_token) {
                                $nombre_creador = "Tú";
                            } else {
                                $nombre_creador = $JwtAuth->desencriptar($selectCr[0]->paterno)." ".$JwtAuth->desencriptar($selectCr[0]->materno)." ".
                                    $JwtAuth->desencriptar($selectCr[0]->nombre);
                            }
                        
                        //tipo_pp
                            $char_tipo_pp = "";
                            $select_tipo_pp = DB::table("module_proyectos_responsable AS resp_pr")
                            ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                            ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                            ->where([
                                'proy.token_proyecto' => $parametrosArray['token_proyecto'],
                                'users.usuario_token' => $usuario->user_token
                            ])->get();
                            
                            if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                                $creat_lider = 'CR';
                            } else {
                                if ($select_tipo_pp[0]->tipo_pp == 'cr') {
                                    $creat_lider = 'CR';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'li') {
                                    $creat_lider = 'LI';
                                } else if ($select_tipo_pp[0]->tipo_pp == 'eq') {
                                    $creat_lider = 'EQ';
                                }
                            }
                        
                        //lider
                            $selectLider = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                            
                            if (count($selectLider) == 1) {
                                $token_lider = $selectLider[0]->empleado_token;
                                
                                if ($token_lider == $id_pers[0]->empleado_token) {
                                    $nombre_lider = "Tú";
                                } else {
                                    $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno)." ".$JwtAuth->desencriptar($selectLider[0]->materno)." ".
                                        $JwtAuth->desencriptar($selectLider[0]->nombre);
                                }
                            } else {
                                $token_lider = $token_creador;
                                $nombre_lider = $nombre_creador;
                            }
                            
                        //equipo de trabajo
                            $equipoTrabajoDetalle = array();
                            $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            foreach($selectEquipo as $valEquipo){
                                $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                    $JwtAuth->desencriptar($valEquipo->materno)." ".
                                    $JwtAuth->desencriptar($valEquipo->nombre);
                                
                                $each = array(
                                    "token_pers_equipo" => $valEquipo->empleado_token,
                                    "nombre_integ" => ucwords($nombre_integ),
                                    "selected" => false,
                                    "participacion" => false,
                                    "informesRealizados" => 0,
                                );
                                $equipoTrabajoDetalle[] = $each;
                            }
                            
                        //inicio y fin
                            $arrayRecalendar = array();
                            $inicio_date = date('d-m-Y H:i:s',$value->fecha_inicio); 
                            
                            $fecha_fin_plana = "";
                            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            if (count($selectRecalendar) > 0) {
                                
                                $recal_proy = true;
                                $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                    WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                                
                                $fecha_fin_tar = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                                $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                                
                                $listaRecalendar = DB::select("SELECT 
                                    rectar.token_calendarizacion,
                                    rectar.fecha_sistema,
                                    rectar.fecha_compromiso_nueva,
                                    pers.empleado_token,
                                    people.paterno,
                                    people.materno,
                                    people.nombre
                                    FROM module_proyectos_calendarizacion AS rectar JOIN module_proyectos AS tar
                                    JOIN vhum_empleados_catalogo AS pers
                                    JOIN sos_personas AS people
                                    WHERE rectar.personal_opera = pers.id AND
                                    pers.empleado_name = people.id AND
                                    rectar.proyecto = tar.id AND
                                    
                                    tar.token_proyecto = ?",
                                    [$value->token_proyecto]);
                                
                                foreach ($listaRecalendar as $valRecal) {
                                    if ($valRecal->empleado_token == $value->empleado_token) {
                                        $personal_opera = "tú";
                                    } else {
                                        $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                            $JwtAuth->desencriptar($valRecal->materno)." ".
                                            $JwtAuth->desencriptar($valRecal->nombre);
                                    }
                                    //echo $personal_opera." ";
                                    $row = array(
                                       "token_calendarizacion" => $valRecal->token_calendarizacion,
                                       "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                       "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                       "personal_opera" => $personal_opera,
                                    );
                                    $arrayRecalendar[] = $row;
                                }
                                $txt_fecha_fin_tar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$valRecal->fecha_compromiso_nueva);
                            } else {
                                $recal_proy = false;
                                $fecha_fin_tar = date('d-m-Y H:i:s',$value->fecha_fin);
                                $fecha_fin_plana = $value->fecha_fin;
                                $txt_fecha_fin_tar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                            }
                            
                        //cliente 
                            $nombre_cliente = $JwtAuth->desencriptar($value->cliente);
                            $abrev_cliente = $value->abrev_cliente;
                        
                        //tareas
                            //vigentes
                                $arrayTareas = array();
                                $tarGreenArray = array();
                                $tarYellowArray = array();
                                $tarRojoArray = array();
                                
                                if ($creat_lider == 'CR' || $creat_lider == 'LI') {
                                    $listaTareas = DB::table("module_proyectos_tareas AS subtar")
                                    ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                                    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                                    ->join("vhum_empleados_catalogo AS pers","empuser.empleado","=","pers.id")
                                    ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                    ->where([
                                        "subtar.status" => TRUE,
                                        "tar.token_proyecto" => $value->token_proyecto,
                                        "emp.empresa_token" => $usuario->empresa_token,
                                        "users.usuario_token" => $usuario->user_token,
                                    ])
                                    ->orderBy("subtar.id","DESC")->get(); 
                                } else {
                                    $listaTareas = DB::table("module_proyectos_tareas AS subtar")
                                    ->join("module_proyectos_tarea_responsable AS resp_tar","subtar.id","=","resp_tar.tarea")
                                    ->join("vhum_empleados_catalogo AS pers","resp_tar.personal","=","pers.id")
                                    ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                    ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                                    ->where([
                                        "subtar.status" => TRUE,
                                        "users.usuario_token" => $usuario->user_token,
                                        "tar.token_proyecto" => $value->token_proyecto,])
                                    ->orderBy("subtar.id","DESC")->get();
                                }
                                
                                $ttotal_subtareas = count($listaTareas);
                                $total_subterminadas = 0;
                                 
                                foreach ($listaTareas as $valSub) {
                                    if ($valSub->realizacion == TRUE) {
                                        $semaforo_realizacion = 'bg-green-600';
                                    } else {
                                        //echo $valSub->fin_tarea." ".time();exit;
                                        if ($valSub->fin_tarea <= time()) {
                                            $semaforo_realizacion = 'bg-red-600';
                                        } else {
                                            $time_fin = time()+(86400*5);
                                            if (time() < $valSub->fin_tarea && $valSub->fin_tarea < $time_fin) {
                                                $semaforo_realizacion = 'bg-yellow-300';
                                            } else if ($valSub->fin_tarea > $time_fin) {
                                                $semaforo_realizacion = 'bg-yellow-500';
                                            }
                                        }
                                    }
                                        
                                    if ($valSub->post_folio_tar == NULL) {
                                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea);
                                    } else {
                                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea).'-'.$valSub->post_folio_tar;
                                    }
  
                                    $equipoResponsableMin = array();
                                    $equipoResponsableMax = array();  
                                    if (count($equipoTrabajoDetalle) != 0) {
                                        for ($i = 0; $i < count($equipoTrabajoDetalle); $i++) {
                                            $equipoResponsableMax[] = $equipoTrabajoDetalle[$i];
                                            $selected = false;
                                            $tar_participacion = false;
                                            
                                            $pers_tarea_count = DB::table("sos_personas AS people")
                                            ->join("vhum_empleados_catalogo AS pers","people.id","=","pers.empleado_name")
                                            ->join("module_proyectos_tarea_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                                            ->join("module_proyectos_tareas AS subtar","resp_tar.tarea","=","subtar.id")
                                            ->join("module_proyectos AS tar","resp_tar.proyecto","=","tar.id")
                                            ->where([
                                                'pers.empleado_token' => $equipoTrabajoDetalle[$i]["token_pers_equipo"],
                                                'subtar.token_tarea' => $valSub->token_tarea,
                                                'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                            ])->orderBy('subtar.id','DESC')->get();
                                                
                                            if (count($pers_tarea_count) == 1) {
                                                $selected = true;
                                                foreach ($pers_tarea_count as $tTar) {
                                                    if ($tTar->participacion == TRUE) $tar_participacion = true;
                                                }
                                                
                                                $informesRealizados = DB::table("module_proyectos_informes AS inform")
                                                ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                                                ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                                ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                                ->where([
                                                    'inform.status_inf' => TRUE,
                                                    'pers.empleado_token' => $equipoTrabajoDetalle[$i]["token_pers_equipo"],
                                                    'subtar.token_tarea' => $valSub->token_tarea,
                                                    'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                                ])->count();
                                                
                                                $equipoResponsableMax[$i]["selected"] = $selected;
                                                $equipoResponsableMax[$i]["participacion"] = $tar_participacion;
                                                $equipoResponsableMax[$i]["informesRealizados"] = $informesRealizados;
                                                $equipoResponsableMin[] = $equipoResponsableMax[$i];
                                            }
                                        }
                                    }
                                         
                                    //informw de actividad de tareas
                                    $informeArray = array();
                                    $informeSubtarea = DB::table("module_proyectos_informes AS inform")
                                    ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                                    ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                                    //->join("module_proyectos_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                                    ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                    ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                    ->where([
                                        'inform.status_inf' => TRUE,
                                        'subtar.token_tarea' => $valSub->token_tarea,
                                        'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                    ])->orderBy('inform.id','DESC')->get();
                            
                                    $tottal_actividades = count($informeSubtarea);
                                    $actividades_aprob = 0;
                                    //$tottal_actividades = 0;echo json_encode(["servicios.jpg"])." ".$JwtAuth->encriptar(json_encode(["servicios.jpg"]));
                                        
                                    foreach ($informeSubtarea as $valInform){
                                        if ($valInform->post_folio_informe == NULL) {
                                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                                        } else {
                                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                                        }
                                        
                                        $aprobaciones = array();
                                        $select_aprobar = DB::table("module_proyectos_informes_aprobar AS aprob")
                                        ->join("module_proyectos_informes AS inf","aprob.informe","=","inf.id")
                                        ->join("module_proyectos_tareas AS subtar","aprob.tarea","=","subtar.id")
                                        ->join("module_proyectos AS tar","aprob.proyecto","=","tar.id")
                                        ->where([
                                            'inf.token_informe' => $valInform->token_informe,
                                            'subtar.token_tarea' => $valSub->token_tarea,
                                            'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                        ])->orderBy('aprob.id','DESC')->get();
                                        
                                        foreach ($select_aprobar as $valAprov) {
                                            if ($valAprov->aprobado_list == TRUE) {
                                                $aprobado_list = "yes_auth";
                                            } else {
                                                $aprobado_list = "not_auth";
                                            }
                                            
                                            $row = array(
                                                "fecha_aprobar" => date('d-m-Y H:i:s',$valAprov->fecha_aprobar),
                                                "aprobado_list" => $aprobado_list,
                                                "observaciones" => $JwtAuth->desencriptar($valAprov->comentarios_aprob),
                                            );
                                            $aprobaciones[] = $row;
                                        }
                                        
                                        $comentarios_aprob = null;
                                        
                                        if ($valInform->revisado == TRUE) {
                                            
                                            $detectApprobes = DB::select("SELECT approv.aprobado_list FROM module_proyectos_informes_aprobar AS approv
                                                JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                                JOIN module_proyectos_informes AS inf 
                                                WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                                AND approv.tarea = tar.id AND tar.token_tarea = ?
                                                AND approv.informe = inf.id AND inf.token_informe = ?",
                                                [$parametrosArray['token_proyecto'],$valSub->token_tarea,$valInform->token_informe]);
                    
                                            if (count($detectApprobes) == 0) {
                                                $status_aprob = "process";
                                                $rev_aprob = "en revision";   
                                            } else {
                                                $lastApprobes = DB::select("SELECT aprobado_list,comentarios_aprob FROM module_proyectos_informes_aprobar 
                                                    WHERE id = (SELECT MAX(approv.id) FROM module_proyectos_informes_aprobar AS approv
                                                JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                                JOIN module_proyectos_informes AS inf 
                                                WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                                AND approv.tarea = tar.id AND tar.token_tarea = ?
                                                AND approv.informe = inf.id AND inf.token_informe = ?)",
                                                [$parametrosArray['token_proyecto'],$valSub->token_tarea,$valInform->token_informe]);
                                                
                                                if (end($lastApprobes)->aprobado_list == TRUE) {
                                                    $status_aprob = "success";
                                                    $rev_aprob = "revisado y aprobado";
                                                    ++$actividades_aprob;
                                                } else {
                                                    $status_aprob = "error";
                                                    $rev_aprob = "revisado sin aprobar"; 
                                                }
                                                $comentarios_aprob = $JwtAuth->desencriptar(end($lastApprobes)->comentarios_aprob);
                                            }
                                        } else {
                                            $rev_aprob = "sin revisar";
                                            $status_aprob = "empty";
                                        }
                                            
                                        $personal_realiza = $JwtAuth->desencriptarNombres($valInform->paterno,$valInform->materno,$valInform->nombre);
                                            
                                        $lista_evidencias_true = array();
                                        $selectIdEvid = DB::table("sos_documentos AS docs")
                                        ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                                        ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                                        ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                                        ->where([
                                            "status_documento" => TRUE,
                                            "proy.token_proyecto" => $parametrosArray['token_proyecto'],
                                            "tar.token_tarea" => $valSub->token_tarea,
                                            "inf.token_informe" => $valInform->token_informe,
                                        ])->get();
                                        if (count($selectIdEvid) > 0) {
                                            foreach ($selectIdEvid as $vDoc){
                                                $rowDocs = array(
                                                    "token_documento" => $vDoc->token_documento,
                                                    "ext_doc" => $vDoc->extension_documento,
                                                    "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                                    "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                                );
                                                $lista_evidencias_true[] = $rowDocs;
                                            }
                                        }
                                        
                                        $lista_evidencias_false = array();
                                        $selectEvidDeleted = DB::table("sos_documentos AS docs")
                                        ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                                        ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                                        ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                                        ->where([
                                            "status_documento" => FALSE,
                                            "proy.token_proyecto" => $parametrosArray['token_proyecto'],
                                            "tar.token_tarea" => $valSub->token_tarea,
                                            "inf.token_informe" => $valInform->token_informe,
                                        ])->get();  
                                        if (count($selectEvidDeleted) > 0) {
                                            foreach ($selectEvidDeleted as $vDoc){
                                                $rowDocs = array(
                                                    "token_documento" => $vDoc->token_documento,
                                                    "ext_doc" => $vDoc->extension_documento,
                                                    "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                                    "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                                );
                                                $lista_evidencias_false[] = $rowDocs;
                                            }
                                        }
                                            
                                        $rowlist = array(
                                            "token_informe" => $valInform->token_informe,
                                            "token_proyecto" => $parametrosArray['token_proyecto'],
                                            "token_tarea" => $valSub->token_tarea,
                                            "folio_inf" => $folio_inf,
                                            "fecha_realizacion" => date('d-m-Y H:i:s',$valInform->fecha_realizacion),
                                            "informe" => $JwtAuth->desencriptar($valInform->informe),
                                            "informe_dos" => $JwtAuth->desencriptar($valInform->informe),
                                            "observaciones_revision" => "",
                                            "observaciones" => $JwtAuth->desencriptar($valInform->observaciones),
                                            "observaciones_dos" => $JwtAuth->desencriptar($valInform->observaciones),
                                            "horas_activas" => $valInform->horas_activas,
                                            "horas_activas_dos" => $valInform->horas_activas,
                                            "personal_realiza" => ucwords($personal_realiza),
                                            "aprobaciones" => $aprobaciones,
                                            "rev_aprob" => $rev_aprob,
                                            "status_aprob" => $status_aprob,
                                            "comentarios_aprob" => $comentarios_aprob,
                                            "evidencias_true" => $lista_evidencias_true,
                                            "evidencias_false" => $lista_evidencias_false,
                                        );
                                        $informeArray[] = $rowlist;
                                    }                                      
                                             
                                    //informw de actividad de tareas
                                    $infDelArray = array();
                                    $informDelSubtar = DB::table("module_proyectos_informes AS inform")
                                    ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                                    ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                                    //->join("module_proyectos_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                                    ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                    ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                    ->where([
                                        'inform.status_inf' => FALSE,
                                        'subtar.token_tarea' => $valSub->token_tarea,
                                        'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                    ])->orderBy('inform.id','DESC')->get();
                                        
                                    foreach ($informDelSubtar as $valInf){
                                        if ($valInf->post_folio_informe == NULL) {
                                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInf->folio_informe);
                                        } else {
                                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInf->folio_informe).'-'.$valInf->post_folio_informe;
                                        }
                                            
                                        $rowlist = array(
                                            "token_informe" => $valInf->token_informe,
                                            "token_proyecto" => $parametrosArray['token_proyecto'],
                                            "token_tarea" => $valInf->token_tarea,
                                            "folio_inf" => $folio_inf,
                                            "fecha_realizacion" => date('d-m-Y H:i:s',$valInf->fecha_realizacion),
                                            "informe" => $JwtAuth->desencriptar($valInf->informe),
                                            "personal_realiza" => $JwtAuth->desencriptar($valInf->paterno)." ".$JwtAuth->desencriptar($valInf->materno)." ".$JwtAuth->desencriptar($valInf->nombre),
                                            "fecha_delete_inf" => date('d-m-Y H:i:s',$valInf->fecha_delete_inf),
                                        );
                                        $infDelArray[] = $rowlist;
                                    } 
                                    
                                    //recalendarizacion
                                        $fin_tarea = "";
                                        $fin_tarea_html = "";
                                        $arrayRecalendar = array();
                                        $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                            JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar WHERE rectar.proyecto = tar.id 
                                            AND tar.token_proyecto = ? AND rectar.tarea = subtar.id AND subtar.token_tarea = ?",
                                            [$parametrosArray['token_proyecto'],$valSub->token_tarea]);
                                            
                                        if (count($selectRecalendar) > 0) {
                                            $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                                JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar WHERE rectar.proyecto = tar.id 
                                                AND tar.token_proyecto = ? AND rectar.tarea = subtar.id AND subtar.token_tarea = ?)",
                                                [$parametrosArray['token_proyecto'],$valSub->token_tarea]);
                                            
                                            $fin_tarea = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                                            $fin_tarea_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                                            
                                            $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion, 
                                                rectar.fecha_sistema,rectar.fecha_compromiso_nueva,pers.empleado_token,
                                                people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                                JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar JOIN vhum_empleados_catalogo AS pers 
                                                JOIN sos_personas AS people WHERE rectar.personal_opera = pers.id 
                                                AND pers.empleado_name = people.id AND rectar.proyecto = tar.id 
                                                AND tar.token_proyecto = ? AND rectar.tarea = subtar.id 
                                                AND subtar.token_tarea = ?",
                                                [$parametrosArray['token_proyecto'],$valSub->token_tarea]);
                                            
                                            foreach ($listaRecalendar as $valRecal) {
                                                //echo "valSub->empleado_token ".$valRecal->empleado_token;//exit;
                                                if ($valRecal->empleado_token == $valSub->empleado_token) {
                                                    $personal_opera = "tú";
                                                } else {
                                                    $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                                        $JwtAuth->desencriptar($valRecal->materno)." ".
                                                        $JwtAuth->desencriptar($valRecal->nombre);
                                                }
                                                //echo $personal_opera." ";
                                                $row = array(
                                                   "token_calendarizacion" => $valRecal->token_calendarizacion,
                                                   "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                                   "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                                   "personal_opera" => $personal_opera,
                                                );
                                                $arrayRecalendar[] = $row;
                                            }  
                                        } else {
                                            $fin_tarea = date('d-m-Y H:i:s',$valSub->fin_tarea);
                                            $fin_tarea_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$valSub->fin_tarea);
                                        }
                                        
                                        if ($valSub->upload_evidencias == TRUE) {
                                            $evidenciasUpload = true;
                                        } else {
                                            $evidenciasUpload = false;
                                        }     
                                        
                                    //tareas pendientes
                                        $tarea_enabled = true;
                                        $tareas_enabled_array = array();
                                        $selectTareaPend = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$valSub->token_tarea]);
                                        $selectwithOut = DB::table("module_proyectos_tareas AS tar")
                                        ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                                        ->where("tar.status","=",TRUE)
                                        ->where("tar.id","<",$selectTareaPend[0]->id)
                                        ->where("tar.token_tarea","!=",$valSub->token_tarea)
                                        ->where("proy.token_proyecto","=",$parametrosArray['token_proyecto'])
                                        ->get();
                                        
                                        if (count($selectwithOut) > 0) {
                                            foreach ($selectwithOut as $vPend){
                                                $folio_tar_enabled = 'TAR-'.$JwtAuth->generarFolio($vPend->folio_tarea);
                                                if ($vPend->post_folio_tar != NULL) $folio_tar_enabled = $folio_tar_enabled.'-'.$vPend->post_folio_tar;
                                                $realizacion_tar_enabled = $vPend->realizacion == TRUE ? true : false;
                                            
                                                $selectPendTar = DB::select("SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy 
                                                    JOIN module_proyectos_tareas AS tar_pend JOIN module_proyectos_tareas AS tar_ant WHERE cad.proyecto = proy.id 
                                                    AND proy.token_proyecto = ? AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ? AND cad.tarea_anterior = tar_ant.id 
                                                    AND tar_ant.token_tarea = ?",[$parametrosArray['token_proyecto'],$valSub->token_tarea,$vPend->token_tarea]);
                                                //->where(["tar.token_tarea" => $vPend->token_tarea])->get();
                                            
                                                $vinculado_cadena = count($selectPendTar) > 0 ? true : false;
                                            
                                                $row_pend = array(
                                                    "token_tarea" => $vPend->token_tarea,
                                                    "folio_tarea" => $folio_tar_enabled,
                                                    "tarea_nombre" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($vPend->tarea_nombre)),
                                                    "tarea_descripcion" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($vPend->tarea_descripcion)),
                                                    "realizacion" => $realizacion_tar_enabled,
                                                    "vinculado" => $vinculado_cadena,
                                                );
                                                $tareas_enabled_array[] = $row_pend;
                                            }
                                        }
                                        
                                        /*$queryPendTar = DB::select("SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy
                                        JOIN module_proyectos_tareas AS tar_pend WHERE cad.proyecto = proy.id AND proy.token_proyecto = ? 
                                        AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ?",[$parametrosArray['token_proyecto'],$valSub->token_tarea]);*/
                                        
                                        $queryPendTar = DB::select("SELECT tar_ant.id FROM module_proyectos_tareas AS tar_ant JOIN module_proyectos_tareas_cadena AS cad 
                                            WHERE tar_ant.realizacion = FALSE AND tar_ant.id = cad.tarea_anterior AND cad.id IN (
                                                SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy
                                                JOIN module_proyectos_tareas AS tar_pend WHERE cad.proyecto = proy.id AND proy.token_proyecto = ? 
                                                AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ?);",[$parametrosArray['token_proyecto'],$valSub->token_tarea]);
                                        
                                        //->where(["tar.token_tarea" => $vPend->token_tarea])->get();
                                        
                                        if (count($queryPendTar) > 0) {
                                            $tarea_enabled = false;
                                        } else {
                                            $tarea_enabled = true;
                                        }
                                        
                                        $enabled_terminar = false;
                                        if ($actividades_aprob == $tottal_actividades) $enabled_terminar = true;
                                        
                                    $particip_eq = null;
                                    $mis_informes = 0;
                                    if ($creat_lider == 'EQ') {
                                        if ($valSub->participacion == TRUE) $particip_eq = true;
                                        if ($valSub->participacion == FALSE) $particip_eq = false;
                                        $informMisConteo = DB::table("module_proyectos_informes AS inform")
                                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                                        ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                        ->where([
                                            'inform.status_inf' => TRUE,
                                            'subtar.token_tarea' => $valSub->token_tarea,
                                            'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                            'users.usuario_token' => $usuario->user_token,
                                        ])->orderBy('inform.id','DESC')->get();
                                        $mis_informes = count($informMisConteo);
                                    }
                                        
                                    $rowTar = array(
                                        "folio_tar" => $folio_tar,
                                        "token_proyecto" => $parametrosArray['token_proyecto'],
                                        "upload_evidencias" => $evidenciasUpload,
                                        "token_tarea" => $valSub->token_tarea,
                                        "tarea_nombre" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($valSub->tarea_nombre)),
                                        "tarea_nombre_back" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($valSub->tarea_nombre)),
                                        "tarea_descripcion" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($valSub->tarea_descripcion)),
                                        "tarea_descripcion_back" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($valSub->tarea_descripcion)),
                                        
                                        "inicio_tarea" => date('d-m-Y H:i:s',$valSub->inicio_tarea),
                                        
                                        //"fin_tarea" => date('d-m-Y H:i:s',$valSub->fin_tarea),
                                        "fin_tarea" => $fin_tarea,
                                        "fin_tarea_original" => $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$valSub->fin_tarea),
                                        "html_fin_tarea" => $fin_tarea_html,
                                        "tareaRecalendar" => $arrayRecalendar,
                                        
                                        "realizacion" => $semaforo_realizacion,
                                        "equipoResponsableMin" => $equipoResponsableMin,
                                        "equipoResponsableMax" => $equipoResponsableMax,
                                        "tottal_actividades" => $tottal_actividades,
                                        "enable_tarea" => $tarea_enabled,
                                        "tareas_enabled_list" => $tareas_enabled_array,
                                        //"tareas_enabled_list" => count($selectwithOut),
                                        "open_inside_tarea" => false,
                                        "detalle_inside_tarea" => [],
                                        "informeArray" => $informeArray,
                                        "informeDelArray" => $infDelArray,
                                        "enabled_terminar" => $enabled_terminar,
                                        "particip_eq" => $particip_eq,
                                        "mis_informes" => $mis_informes,
                                        "isTarRegTarAntCollapsed" => true,
                                        "isTarInformesListCollapsed" => true,
                                        "isTarInformesDeletedCollapsed" => true,
                                        "isTarInformesNewCollapsed" => true,
                                        "isTarEditCollapsed" => true,
                                        "isTarTeamCollapsed" => true,
                                        "isTarRecalCollapsed" => true,
                                    );
                                    $arrayTareas[] = $rowTar;
                                    
                                    if ($valSub->realizacion == TRUE) {
                                        $tarGreenArray[] = $rowTar;
                                    } else {
                                        $time_fin = time()+(86400*5);
                                        if ($valSub->fin_tarea > $time_fin) {
                                            $tarYellowArray[] = $rowTar;
                                        } else if ($valSub->fin_tarea > time() && $valSub->fin_tarea < $time_fin) {
                                            $tarYellowArray[] = $rowTar;
                                        } else if ($valSub->fin_tarea <= time()){
                                            $tarRojoArray[] = $rowTar;
                                        }
                                    }
                                }
                                
                                if ($ttotal_subtareas > 0) {
                                    if ($ttotal_subtareas == $total_subterminadas) {
                                        $stado_tarea = "finish";
                                        if ($fecha_fin_plana > $time_fin) {
                                            $paloma = "green";
                                        } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                            $paloma = "yellow";
                                        } else if ($fecha_fin_plana <= time()){
                                            $paloma = "red";
                                        }
                                    } else {
                                        $paloma = "";
                                        $time_fin = time()+(86400*5);
                                        if ($fecha_fin_plana > $time_fin) {
                                            $stado_tarea = "green";
                                        } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                            $stado_tarea = "yellow";
                                        } else if ($fecha_fin_plana <= time()){
                                            $stado_tarea = "red";
                                        }
                                    }
                                } else {
                                    $paloma = "";
                                    $stado_tarea = "red";
                                }
                                                            
                            //eliminadas
                                $arrayDeletedTareas = array();
                                
                                $subTarListDel = DB::table("module_proyectos_tareas AS subtar")
                                ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                                ->where(["subtar.status" => FALSE,"tar.token_proyecto" => $value->token_proyecto,])
                                //->where(["tar.token_proyecto" => $value->token_proyecto,])
                                ->orderBy("subtar.id","DESC")->get();
                                
                                foreach ($subTarListDel as $valSub) {
                                    if ($valSub->realizacion == TRUE) {
                                        $semaforo_realizacion = 'bg-green-600';
                                        $total_subterminadas++;
                                    } else {
                                        if ($valSub->fin_tarea > $time_fin) {
                                            $semaforo_realizacion = 'bg-yellow-500';
                                        } else if ($valSub->fin_tarea > time() && $valSub->fin_tarea < $time_fin) {
                                            $semaforo_realizacion = 'bg-yellow-300';
                                        } else if ($valSub->fin_tarea <= time()){
                                            $semaforo_realizacion = 'bg-red-600';
                                        }
                                    }
                                    
                                    if ($valSub->post_folio_tar == NULL) {
                                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea);
                                    } else {
                                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea).'-'.$valSub->post_folio_tar;
                                    }
                                                
                                    $rowTar = array(
                                        "folio_tar" => $folio_tar,
                                        "token_tarea" => $valSub->token_tarea,
                                        "tarea_nombre" => $JwtAuth->desencriptar($valSub->tarea_nombre),
                                        "inicio_tarea" => date('d-m-Y H:i:s',$valSub->inicio_tarea),
                                        "fin_tarea" => date('d-m-Y H:i:s',$valSub->fin_tarea),
                                        "fecha_delete" => date('d-m-Y H:i:s',$valSub->fecha_delete),
                                        "realizacion" => $semaforo_realizacion,
                                    );
                                    $arrayDeletedTareas[] = $rowTar;
                                }
                            
                        if ($value->upload_evidencias == TRUE) {
                            $evidenciasUpload = true;
                        } else {
                            $evidenciasUpload = false;
                        }
                            
                        if ($value->delete_evd_perm == TRUE) {
                            $evd_delete_perm = true;
                        } else {
                            $evd_delete_perm = false;
                        } 
                            
                        $status_proy = DB::select("SELECT status_proyecto FROM module_proyectos WHERE token_proyecto = ?",
                            [$parametrosArray['token_proyecto']]);
                        
                        if ($status_proy[0]->status_proyecto == TRUE) {
                            $rowProyecto = array(
                                "status" => true,
                                //nombre_proyecto
                                    "token_proyecto" => $value->token_proyecto,
                                    "folio_proy" => $folio_proy,
                                    "proyecto" => $nombre_proyecto,
                                    "descripcion" => $descripcion_proyecto,
                                //creat_lider
                                    "creat_lider" => $creat_lider,
                                //creador
                                    "token_creador" => $token_creador,
                                    "nombre_creador" => ucwords($nombre_creador), 
                                //lider
                                    "token_lider" => $token_lider,
                                    "nombre_lider" => ucwords($nombre_lider),
                                //equipo de trabajo
                                    "equipoTrabajoDetalle" => $equipoTrabajoDetalle,
                                //inicio y fin
                                    "fecha_inicio" => $inicio_date, 
                                    "fecha_fin" => $fecha_fin_tar,
                                    "recal_proy" => $recal_proy,
                                    "txt_fecha_fin" => $txt_fecha_fin_tar,
                                    "arrayRecalendar" => $arrayRecalendar,
                                //cliente 
                                    "cliente" => $nombre_cliente,
                                    "abrev_cliente" => $abrev_cliente,
                                //evidencias
                                    "upload_evidencias" => $evidenciasUpload,
                                    "evd_delete_perm" => $evd_delete_perm,
                                //tareas
                                    "tarea_list" => $arrayTareas,
                                    "tarea_list_pag" => [],
                                    "tar_green" => $tarGreenArray,
                                    "tar_yellow" => $tarYellowArray,
                                    "tar_red" => $tarRojoArray,
                                    "tarea_back" => $arrayTareas,
                                    "deletedTareas" => $arrayDeletedTareas,
                                    "stado_tarea" => $stado_tarea,
                                    "paloma" => $paloma,
                                    "visor_tabs" => true,
                            );    
                        } else {
                            $rowProyecto = array(
                                "status" => false,
                                "fecha_delete" => date('d-m-Y H:i:s',$value->fecha_delete_pry),
                                //nombre_proyecto
                                    "token_proyecto" => $value->token_proyecto,
                                    "folio_proy" => $folio_proy,
                                    "proyecto" => $nombre_proyecto,
                                    "descripcion" => $descripcion_proyecto,
                                //creat_lider
                                    "creat_lider" => $creat_lider,
                                //creador
                                    "token_creador" => $token_creador,
                                    "nombre_creador" => ucwords($nombre_creador), 
                                //lider
                                    "token_lider" => $token_lider,
                                    "nombre_lider" => ucwords($nombre_lider),
                                //equipo de trabajo
                                    "equipoTrabajoDetalle" => $equipoTrabajoDetalle,
                                //inicio y fin
                                    "fecha_inicio" => $inicio_date, 
                                    "fecha_fin" => $fecha_fin_tar,
                                    "recal_proy" => $recal_proy,
                                    "txt_fecha_fin" => $txt_fecha_fin_tar,
                                //cliente 
                                    "cliente" => $nombre_cliente,
                                    "abrev_cliente" => $abrev_cliente,
                            );      
                        }
                        
                        $contentProyecto[] = $rowProyecto; 
                    }
                    $dataMensaje = array(
                        'status' => 'success',
                        "code" => 200,
                        'proyecto' => $contentProyecto,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
//tareas
    //registro
        public function registrarTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $fecha_sistema = time();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'nameTarea' => 'required|string',
                    'descripTarea' => 'required|string',    
                    'fecha_fin_tareaNew' => 'required|string',   
                    'token_empleado_inside' => 'string',
                    'array_responsables_tarea' => 'array',
                    'array_depende_tarea' => 'array',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $token_proyecto = $parametrosArray["token_proyecto"];
                    
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    //$patronFecha = '/^[0-9-]*$/';
                    $patronFecha = '/^[0-9-]*$/';
                    $patronHora = '/^[0-9:]*$/';
                    
                    $tareaList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.empleado","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                    ->where([
                        //'mod_proy.status' => TRUE,
                        'mod_proy.token_proyecto' => $token_proyecto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token
                    ])->get();
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && count($tareaList) == 1) {
                        foreach ($tareaList as $value) {
                            $fin_proyecto = $value->fecha_fin;
                            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                            
                            if (count($selectRecalendar) > 0) {
                                $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                    WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                                $fin_proyecto = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                            }
                            
                            $validaTarea = false;
                            $nameTarea = $parametrosArray["nameTarea"];
                            $descripTarea = $parametrosArray["descripTarea"];
                            $fecha_fin_tareaNew = $parametrosArray["fecha_fin_tareaNew"];
                            $finaliza_tarea = explode("T",$parametrosArray["fecha_fin_tareaNew"]);
                            $fecha_fin = $finaliza_tarea[0];
                            $hora_fin = $finaliza_tarea[1];
                            $token_empleado_inside = $parametrosArray["token_empleado_inside"];
                            //$parametrosArray["array_depende_tarea"]
        
                            if (isset($nameTarea) && !empty($nameTarea) && preg_match($patron,$nameTarea) &&
                                isset($descripTarea) && !empty($descripTarea) && preg_match($patron,$descripTarea) &&
                                isset($fecha_fin_tareaNew) && !empty($fecha_fin_tareaNew) && preg_match($patronFecha,$fecha_fin) && 
                                preg_match($patronHora,$hora_fin) && $JwtAuth->convierteFechaEpoc($fecha_fin_tareaNew) <= $fin_proyecto) {
                                    
                                //$nameTarea str_replace("world","Peter","Hello world!")
                                    
                                $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                                
                                $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                                
                                $fecha_inicio = time();
                                
                                $tokenSubTarea = $JwtAuth->encriptarToken($fecha_sistema.$fecha_inicio.$fecha_fin.$nameTarea.$descripTarea.$fecha_fin); 
            
                                $folioSistemaSub = DB::select("SELECT COUNT(subtar.id)+1 AS folio FROM module_proyectos_tareas AS subtar
                                    JOIN module_proyectos AS tarprog WHERE subtar.proyecto = tarprog.id AND tarprog.token_proyecto = ?",[$token_proyecto]);
                                        
                                if ($folioSistemaSub[0]->folio > 1) {
                                    if ($folioSistemaSub[0]->folio == 1000000000) {
                                        $post_folio_db = DB::select("SELECT post_folio_tar FROM module_proyectos_tareas 
                                            WHERE id = (SELECT Max(subtar.id) FROM module_proyectos_tareas AS subtar 
                                            JOIN module_proyectos AS tarprog
                                            WHERE subtar.proyecto = tarprog.id AND tarprog.token_proyecto = ?)",[$parametrosArray['token_tarea']]);
                                        
                                        $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_tar);
                                        $folio_nuevo = 1;
                                    } else {
                                        $post_folio = NULL;
                                        $folio_nuevo = $folioSistemaSub[0]->folio;
                                    }
                                } else {
                                    $post_folio = NULL;
                                    $folio_nuevo = 1;
                                }
                        
                                if ($post_folio == NULL) {
                                    $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folio_nuevo);
                                } else {
                                    $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                                }
            
                                $nameTarea = str_replace(".diagon.","/",$nameTarea);
                                $nameTarea = str_replace(".porcent.","%",$nameTarea);
                                $nameTarea = str_replace(".ampersand.","&",$nameTarea);
                                $nameTarea = str_replace(".pessos.","$",$nameTarea);
                                
                                $descripTarea = str_replace(".diagon.","/",$descripTarea);
                                $descripTarea = str_replace(".porcent.","%",$descripTarea);
                                $descripTarea = str_replace(".ampersand.","&",$descripTarea);
                                $descripTarea = str_replace(".pessos.","$",$descripTarea);
            
                                $insertSubtarea = DB::table('module_proyectos_tareas') 
                                ->insert(array(
                                    "token_tarea" => $tokenSubTarea, 
                                    "folio_tarea" => $folio_nuevo, 
                                    "post_folio_tar" => $post_folio, 
                                    "proyecto" => $selectProyecto[0]->id, 
                                    "tarea_nombre" => $JwtAuth->encriptar($nameTarea),
                                    "tarea_descripcion" => $JwtAuth->encriptar($descripTarea),
                                    "inicio_tarea" => $fecha_inicio, 
                                    "fin_tarea" => $JwtAuth->convierteFechaEpoc($fecha_fin_tareaNew),
                                    "realizacion" => FALSE,
                                    "status" => TRUE,
                                    "fecha_delete" => NULL,
                                ));
                                //$insertSubtarea = 1;
                                if ($insertSubtarea) {
                                    $titulo_alerta = "Ha registrado una nueva tarea con folio ".$folio_tar;
                                    $selectTarea = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$tokenSubTarea]);
                                    //echo $responsable_tarea_token; exit;
                                    
                                    JURI_EventosController::registraEventoProyectos(
                                        $selectProyecto[0]->id,
                                        $selectTarea[0]->id,
                                        1,
                                        'fecha de finalización',
                                        $JwtAuth->convierteFechaEpoc($fecha_fin),
                                        $selectEmp[0]->id
                                    );
                                    
                                    //$certif_token_pers = false;
                                    if (count($parametrosArray['array_responsables_tarea']) != 0) {
                                        for ($i = 0; $i < count($parametrosArray['array_responsables_tarea']); $i++){
                                            $tokenEquForProy = DB::select("SELECT pers.id,resp.tipo_pp FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp 
                                                JOIN module_proyectos AS proy WHERE pers.empleado_token = ? AND pers.id = resp.personal AND resp.tipo_pp = 'eq' 
                                                AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
                                                [$parametrosArray['array_responsables_tarea'][$i],$token_proyecto]);
                                            $insertAlertaLider = DB::table('module_proyectos_tarea_responsable')
                                            ->insert(
                                                array(
                                                    "proyecto" => $selectProyecto[0]->id,
                                                    "tarea" => $selectTarea[0]->id,
                                                    "personal" => $tokenEquForProy[0]->id,
                                                )
                                            );
                                            $JwtAuth->insertNotifEqPersonal("Actualización de proyecto",$parametrosArray['array_responsables_tarea'][$i],$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                        }
                                    } else {
                                        $tokenLiderProyecto = DB::select("SELECT pers.id,pers.empleado_token,resp.tipo_pp,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp 
                                            JOIN module_proyectos AS proy JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND pers.id = resp.personal AND (resp.tipo_pp = 'li' OR resp.tipo_pp = 'cr') 
                                            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",[$token_proyecto]);
                                        //echo $token_empleado_inside." ";
                                        foreach ($tokenLiderProyecto as $vCreators) {
                                            //echo $vCreators->empleado_token." ";
                                            //echo $vCreators->token_dispositivo_movil;
                                            if ($token_empleado_inside != "" && $token_empleado_inside == $vCreators->empleado_token) {
                                                $responsable_tarea_token = $vCreators->id;
                                                //$certif_token_pers = true;
                                                //break;
                                                $insertAlertaLider = DB::table('module_proyectos_tarea_responsable')
                                                ->insert(
                                                    array(
                                                        "proyecto" => $selectProyecto[0]->id,
                                                        "tarea" => $selectTarea[0]->id,
                                                        "personal" => $responsable_tarea_token,
                                                        //"tipo_pp" => NULL
                                                    )
                                                );
                                                
                                                //if ($vCreators->token_dispositivo_movil != "") {
                                                //    $JwtAuth->notificacionPushDevices($vCreators->token_dispositivo_movil,"SOS-México - Gestión de proyectos",$titulo_alerta);
                                                //}
                                                
                                            } 
                                        }
                    
                                        /*if ($certif_token_pers == false) {
                                            $tokenEquipoProyecto = DB::select("SELECT pers.id,resp.tipo_pp FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp 
                                                JOIN module_proyectos AS proy WHERE pers.empleado_token = ? AND pers.id = resp.personal AND resp.tipo_pp = 'eq' 
                                                AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
                                                [$token_empleado_inside,$token_proyecto]);
                                            $responsable_tarea_token = $tokenEquipoProyecto[0]->id;
                                        }
                                                                
                                        $insertAlertaLider = DB::table('module_proyectos_responsable')
                                        ->insert(array("proyecto" => $selectProyecto[0]->id,"tarea" => $selectTarea[0]->id,"personal" => $responsable_tarea_token,"tipo_pp" => NULL));*/
                                    }
                                        
                                    if (count($parametrosArray["array_depende_tarea"]) != 0) {
                                        for ($i = 0; $i < count($parametrosArray["array_depende_tarea"]); $i++){
                                            $selectPendTarea = DB::select("SELECT tar.id FROM module_proyectos_tareas AS tar JOIN module_proyectos AS proy WHERE tar.token_tarea = ? 
                                                AND tar.proyecto = proy.id AND proy.token_proyecto = ?",[$parametrosArray["array_depende_tarea"][$i],$token_proyecto]);
                                            $insertAlertaLider = DB::table("module_proyectos_tareas_cadena")
                                            ->insert(
                                                array(
                                                    "proyecto" => $selectProyecto[0]->id,
                                                    "tarea" => $selectTarea[0]->id,
                                                    "tarea_anterior" => $selectPendTarea[0]->id,
                                                )
                                            );
                                        }
                                    }    
                                        
                                    $creatList = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp 
                                        JOIN module_proyectos AS proy WHERE pers.id = resp.personal AND resp.tipo_pp != 'eq' 
                                        AND resp.proyecto = proy.id AND proy.token_proyecto = ?",[$token_proyecto]);
                                    
                                    
                                    if (count($creatList) == 2) {
                                        $tokenLiderProyecto = DB::select("SELECT pers.id,pers.empleado_token,resp.tipo_pp FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp 
                                            JOIN module_proyectos AS proy WHERE pers.id = resp.personal AND (resp.tipo_pp = 'li' OR resp.tipo_pp = 'cr') 
                                            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",[$token_proyecto]);
                                        
                                        foreach ($tokenLiderProyecto as $vCreators) {
                                            if ($selectEmp[0]->userr == $vCreators->id) {
                                                //echo $vCreators->tipo_pp;
                                                if ($vCreators->tipo_pp == 'cr') {
                                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                                }   
                                                if ($vCreators->tipo_pp == 'li') {
                                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                                }
                                            }
                                        }
                                    }
                                    
                                    //if ($token_empleado_inside != "") {
                                    //    $JwtAuth->insertNotifEq($token_proyecto,$tokenSubTarea,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    //}
                                    
                                    $JwtAuth->insertBitacoraActividad('tareas','apppr_proyectos','registro',$folio_tar,
                                    'registro en tareas programadas',$usuario->empresa_token,$usuario->user_token);
                                
                                    $dataMensaje = array(
                                        'message' => 'Tarea registrada con el folio '.$folio_tar,
                                        "code" => 200,
                                        'status' => 'success'
                                    );
            
                                } else {
                                    $dataMensaje = array(
                                        "status" => "error",
                                        "code" => 200,
                                        'message' => 'Este proyecto no fue registrado debido a errores internos, intente nuevamente'
                                    );
                                }
            
                            } else {
                                if (!isset($nameTarea) || empty($nameTarea) || !preg_match($patron,$nameTarea)) {
                                    $dataMensaje = array(
                                        "status" => "error",
                                        "code" => 200,
                                        'message' => 'Error en registro de nombre de tarea, verifique su información'
                                    );
                                }
                                if (!isset($descripTarea) || empty($descripTarea) || !preg_match($patron,$descripTarea)) {
                                    $dataMensaje = array(
                                        "status" => "error",
                                        "code" => 200,
                                        'message' => 'Error en registro de descripción para tarea, verifique su información'
                                    );
                                }
                                if (!isset($fecha_fin_tareaNew) || empty($fecha_fin_tareaNew) || !preg_match($patronFecha,$fecha_fin) || 
                                    !preg_match($patronHora,$hora_fin) || $JwtAuth->convierteFechaEpoc($fecha_fin_tareaNew) > $fin_proyecto) {
                                    $dataMensaje = array(
                                        "status" => "error",
                                        "code" => 200,
                                        'message' => 'Error en fecha de finalización de tarea, verifique su información e intente nuevamente'
                                    );
                                }
                                if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                                    $dataMensaje = array(
                                        "status" => "error",
                                        "code" => 200,
                                        'message' => 'Error en seleccion de personal responsable de tarea, verifique su información e intente nuevamente'
                                    );
                                }
                            }

                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No hay referencia del proyecto seleccionado, verifique su información'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
    //listas
        public function lastTareaDeleted(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $contentTarea = array();
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    
                    $time_fin = time()+(86400*5);
                    $id_pers = DB::select("SELECT pers.id,pers.empleado_token FROM vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE pers.id = users.empleado AND users.usuario_token = ?",[$usuario->user_token]);
                        
                    $queryTarDel = DB::table("module_proyectos_tareas AS tar")
                    ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                    ->where([
                        "tar.status" => FALSE,
                        "tar.token_tarea" => $token_tarea,
                        "proy.token_proyecto" => $token_proyecto,
                    ])->get();
                                
                    foreach ($queryTarDel as $valSub) {
                        if ($valSub->realizacion == TRUE) {
                            $semaforo_realizacion = 'bg-green-600';
                            $total_subterminadas++;
                        } else {
                            if ($valSub->fin_tarea > $time_fin) {
                                $semaforo_realizacion = 'bg-yellow-500';
                            } else if ($valSub->fin_tarea > time() && $valSub->fin_tarea < $time_fin) {
                                $semaforo_realizacion = 'bg-yellow-300';
                            } else if ($valSub->fin_tarea <= time()){
                                $semaforo_realizacion = 'bg-red-600';
                            }
                        }
                        
                        if ($valSub->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea).'-'.$valSub->post_folio_tar;
                        }
                                    
                        $rowTar = array(
                            "folio_tar" => $folio_tar,
                            "token_tarea" => $valSub->token_tarea,
                            "tarea_nombre" => $JwtAuth->desencriptar($valSub->tarea_nombre),
                            "inicio_tarea" => date('d-m-Y H:i:s',$valSub->inicio_tarea),
                            "fin_tarea" => date('d-m-Y H:i:s',$valSub->fin_tarea),
                            "fecha_delete" => date('d-m-Y H:i:s',$valSub->fecha_delete),
                            "realizacion" => $semaforo_realizacion,
                        );
                        $contentTarea[] = $rowTar;
                    }    
                        
                    $dataMensaje = array(
                        "status" => "success",
                        "code" => 200,
                        "deletedTareas" => $contentTarea,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
        public function restaurarTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea)) {
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar 
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectSubTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectSubTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectSubTarea[0]->folio_tarea).'-'.$selectSubTarea[0]->post_folio_tar;
                        }
                        
                        $restoreTarea = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'subtar.token_tarea' => $parametrosArray['token_tarea'],
                            'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->limit(1)->update(
    						array(
    							'subtar.status' => TRUE,
    							'subtar.fecha_delete' => NULL,
    						)
    					);
    					
    					if ($restoreTarea) {
    					    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                            
                            $titulo_alerta = "Ha restaurado tarea con folio ".$folio_tar;
                    
                            if ($selectEmp[0]->userr != $selectProyecto[0]->creador_tarea) {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } else {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }
                            
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
            					    
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Tarea restaurada'
                            );
    					} else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Tarea no restaurada'
                            );
    					}
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Proyecto o tarea no validos'
                        );   
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
        public function removeTareaPerm(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea)) {
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                            AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        
                        $selectFoltar = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where([
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                        ])->get();
        
                        if ($selectFoltar[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectFoltar[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectFoltar[0]->folio_tarea).'-'.$selectFoltar[0]->post_folio_tar;
                        }
                        
                        $informeSubtarea = DB::table("module_proyectos_informes AS inform")
                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                        ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                        ->where([
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                        ])->get();
        
                        if (count($informeSubtarea) != 0) {
                            foreach ($informeSubtarea as $valInform){
                                if ($valInform->post_folio_informe == NULL) {
                                    $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                                } else {
                                    $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                                }
        
                                $docsInforme = DB::table("module_proyectos_informes AS inform")
                                ->join("sos_documentos AS evd","inform.id","=","evd.informe")
                                ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                                ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                                ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                ->where([
                                    'inform.token_informe' => $valInform->token_informe,
                                    'subtar.token_tarea' => $token_tarea,
                                    'tar.token_proyecto' => $token_proyecto,
                                ])->get();
        
                                //echo "docsInforme ".count($docsInforme)." ";
        
                                if (count($docsInforme) != 0) {
                                    foreach ($docsInforme as $vDocs) {
                                        $filepath = $selectEmp[0]->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$JwtAuth->desencriptar($vDocs->nombre_documento);
                                        //echo $filepath;exit;
                                        Storage::delete("/public/root/".$filepath);
                                        
                                        $docsDelete = DB::table("sos_documentos")
                                        ->where(["token_documento" => $vDocs->token_documento])->limit(1)->delete();
                                    }
                                }
                                
                                $deleteInf = DB::table('module_proyectos_informes AS inform')
                                ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                                ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                                ->where([
                                    'inform.token_informe' => $valInform->token_informe,
                                    'tar.token_proyecto' => $token_proyecto,
                                    'subtar.token_tarea' => $token_tarea,
                                ])->limit(1)->delete();
                                
                            }
                        }
                        
                        $deleteResp = DB::table('module_proyectos_tarea_responsable AS resp')
                        ->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
                        ->join("module_proyectos_tareas AS subtar","resp.tarea","=","subtar.id")
                        ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->limit(1)->delete();
                        
                        $titulo_alerta = "Tarea con folio ".$folio_tar." ha sido eliminada permanentemente";
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                            AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                            
                        $JwtAuth->deleteNotifTar($token_tarea);
                        
                        if ($selectEmp[0]->userr != $selectProyecto[0]->creador_tarea) {
                            $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        } else {
                            $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        }
                        
                        $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                        $deleteTarea = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->limit(1)->delete();
	    				
	    				if ($deleteResp && $deleteTarea) {
	    				    $filepath = $selectEmp[0]->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar;
                            Storage::delete("/public/root/".$filepath);
	    				    $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => $titulo_alerta
                            );
	    				} else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Tarea no eliminada'
                            );
	    				}
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Proyecto o tarea no validos'
                        );   
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function ultimaTareaCreada(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
            $tarGreenArray = array();
            $tarYellowArray = array();
            $tarRojoArray = array();
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'creat_lider' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $creat_lider = $parametrosArray['creat_lider'];
                    
                    if ($creat_lider == 'CR' || $creat_lider == 'LI') {
                        $listaTareas = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            "subtar.status" => TRUE,
                            "tar.token_proyecto" => $token_proyecto,
                            "emp.empresa_token" => $usuario->empresa_token,
                            "users.usuario_token" => $usuario->user_token,
                        ])
                        ->orderBy("subtar.id","DESC")->limit(1)->get(); 
                    } else {
                        $listaTareas = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos_tarea_responsable AS resp_pr","subtar.id","=","resp_pr.tarea")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where([
                            "subtar.status" => TRUE,
                            "users.usuario_token" => $usuario->user_token,
                            "tar.token_proyecto" => $token_proyecto,])
                        ->orderBy("subtar.id","DESC")->limit(1)->get();
                    }
                                
                    $ttotal_subtareas = count($listaTareas);
                    $total_subterminadas = 0;
                    
                    foreach ($listaTareas as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        //equipo de trabajo
                            $equipoTrabajoDetalle = array();
                            $equipoResponsableMin = array();
                            $equipoResponsableMax = array();
                            $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                                people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                                AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                
                            foreach($selectEquipo as $valEquipo){
                                $pers_tarea_count = DB::table("sos_personas AS people")
                                ->join("vhum_empleados_catalogo AS pers","people.id","=","pers.empleado_name")
                                ->join("module_proyectos_tarea_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                                ->join("module_proyectos_tareas AS subtar","resp_tar.tarea","=","subtar.id")
                                ->join("module_proyectos AS tar","resp_tar.proyecto","=","tar.id")
                                ->where([
                                    'pers.empleado_token' => $valEquipo->empleado_token,
                                    'subtar.token_tarea' => $value->token_tarea,
                                    'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                ])->orderBy('subtar.id','DESC')->count();
                                
                                if ($pers_tarea_count == 1) {
                                    $selected = true;
                                } else {
                                    $selected = false;
                                }
                                //echo $selected." ";
                                $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                    $JwtAuth->desencriptar($valEquipo->materno)." ".
                                    $JwtAuth->desencriptar($valEquipo->nombre);
                                
                                $each = array(
                                    "token_pers_equipo" => $valEquipo->empleado_token,
                                    "nombre_integ" => ucwords($nombre_integ),
                                    "selected" => $selected,
                                );
                                $equipoResponsableMax[] = $each;
                        
                                if ($selected == true) {
                                    $equipoResponsableMin[] = $each;
                                }
                            }
                        
                        if ($value->realizacion == TRUE) {
                            $semaforo_realizacion = 'bg-green-600';
                        } else {
                            if ($value->fin_tarea <= time()) {
                                $semaforo_realizacion = 'bg-red-600';
                            } else {
                                $time_fin = time()+(86400*5);
                                if (time() < $value->fin_tarea && $value->fin_tarea < $time_fin) {
                                    $semaforo_realizacion = 'bg-yellow-300';
                                } else if ($value->fin_tarea > $time_fin) {
                                    $semaforo_realizacion = 'bg-yellow-500';
                                }
                            }
                        }
                            
                        if ($value->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($value->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($value->folio_tarea).'-'.$value->post_folio_tar;
                        }
                             
                        //informw de actividad de tareas
                        $informeArray = array();
                        $informeSubtarea = DB::table("module_proyectos_informes AS inform")
                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                        ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                        //->join("module_proyectos_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                        ->where([
                            'inform.status_inf' => TRUE,
                            'subtar.token_tarea' => $value->token_tarea,
                            'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                        ])->orderBy('inform.id','DESC')->get();
                
                        $tottal_actividades = count($informeSubtarea);
                        $actividades_aprob = 0;
                        //$tottal_actividades = 0;echo json_encode(["servicios.jpg"])." ".$JwtAuth->encriptar(json_encode(["servicios.jpg"]));
                            
                        foreach ($informeSubtarea as $valInform){
                            if ($valInform->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                            }
                            
                            $aprobaciones = array();
                            $select_aprobar = DB::table("module_proyectos_informes_aprobar AS aprob")
                            ->join("module_proyectos_informes AS inf","aprob.informe","=","inf.id")
                            ->join("module_proyectos_tareas AS subtar","aprob.tarea","=","subtar.id")
                            ->join("module_proyectos AS tar","aprob.proyecto","=","tar.id")
                            ->where([
                                'inf.token_informe' => $valInform->token_informe,
                                'subtar.token_tarea' => $valSub->token_tarea,
                                'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                            ])->orderBy('aprob.id','DESC')->get();
                            
                            foreach ($select_aprobar as $valAprov) {
                                if ($valAprov->aprobado_list == TRUE) {
                                    $aprobado_list = "yes_auth";
                                } else {
                                    $aprobado_list = "not_auth";
                                }
                                
                                $row = array(
                                    "fecha_aprobar" => date('d-m-Y H:i:s',$valAprov->fecha_aprobar),
                                    "aprobado_list" => $aprobado_list,
                                    "observaciones" => $JwtAuth->desencriptar($valAprov->comentarios_aprob),
                                );
                                $aprobaciones[] = $row;
                            }
                            
                            $comentarios_aprob = null;
                            
                            if ($valInform->revisado == TRUE) {
                                
                                $detectApprobes = DB::select("SELECT approv.aprobado_list FROM module_proyectos_informes_aprobar AS approv
                                    JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                    JOIN module_proyectos_informes AS inf 
                                    WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                    AND approv.tarea = tar.id AND tar.token_tarea = ?
                                    AND approv.informe = inf.id AND inf.token_informe = ?",
                                    [$parametrosArray['token_proyecto'],$valSub->token_tarea,$valInform->token_informe]);
        
                                if (count($detectApprobes) == 0) {
                                    $status_aprob = "process";
                                    $rev_aprob = "en revision";   
                                } else {
                                    $lastApprobes = DB::select("SELECT aprobado_list,comentarios_aprob FROM module_proyectos_informes_aprobar 
                                        WHERE id = (SELECT MAX(approv.id) FROM module_proyectos_informes_aprobar AS approv
                                    JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                    JOIN module_proyectos_informes AS inf 
                                    WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                    AND approv.tarea = tar.id AND tar.token_tarea = ?
                                    AND approv.informe = inf.id AND inf.token_informe = ?)",
                                    [$parametrosArray['token_proyecto'],$valSub->token_tarea,$valInform->token_informe]);
                                    
                                    if (end($lastApprobes)->aprobado_list == TRUE) {
                                        $status_aprob = "success";
                                        $rev_aprob = "revisado y aprobado";
                                        ++$actividades_aprob;
                                    } else {
                                        $status_aprob = "error";
                                        $rev_aprob = "revisado sin aprobar"; 
                                    }
                                    $comentarios_aprob = $JwtAuth->desencriptar(end($lastApprobes)->comentarios_aprob);
                                }
                            } else {
                                $rev_aprob = "sin revisar";
                                $status_aprob = "empty";
                            }
                                
                            $personal_realiza = $JwtAuth->desencriptarNombres($valInform->paterno,$valInform->materno,$valInform->nombre);
                                
                            $lista_evidencias_true = array();
                            $selectIdEvid = DB::table("sos_documentos AS docs")
                            ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                            ->where([
                                "status_documento" => TRUE,
                                "proy.token_proyecto" => $parametrosArray['token_proyecto'],
                                "tar.token_tarea" => $valSub->token_tarea,
                                "inf.token_informe" => $valInform->token_informe,
                            ])->get();
                            if (count($selectIdEvid) > 0) {
                                foreach ($selectIdEvid as $vDoc){
                                    $rowDocs = array(
                                        "token_documento" => $vDoc->token_documento,
                                        "ext_doc" => $vDoc->extension_documento,
                                        "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                        "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                    );
                                    $lista_evidencias_true[] = $rowDocs;
                                }
                            }
                            
                            $lista_evidencias_false = array();
                            $selectEvidDeleted = DB::table("sos_documentos AS docs")
                            ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                            ->where([
                                "status_documento" => FALSE,
                                "proy.token_proyecto" => $parametrosArray['token_proyecto'],
                                "tar.token_tarea" => $valSub->token_tarea,
                                "inf.token_informe" => $valInform->token_informe,
                            ])->get();  
                            if (count($selectEvidDeleted) > 0) {
                                foreach ($selectEvidDeleted as $vDoc){
                                    $rowDocs = array(
                                        "token_documento" => $vDoc->token_documento,
                                        "ext_doc" => $vDoc->extension_documento,
                                        "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                        "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                    );
                                    $lista_evidencias_false[] = $rowDocs;
                                }
                            }
                                
                            $rowlist = array(
                                "token_informe" => $valInform->token_informe,
                                "token_proyecto" => $parametrosArray['token_proyecto'],
                                "token_tarea" => $valSub->token_tarea,
                                "folio_inf" => $folio_inf,
                                "fecha_realizacion" => date('d-m-Y H:i:s',$valInform->fecha_realizacion),
                                "informe" => $JwtAuth->desencriptar($valInform->informe),
                                "informe_dos" => $JwtAuth->desencriptar($valInform->informe),
                                "observaciones_revision" => "",
                                "observaciones" => $JwtAuth->desencriptar($valInform->observaciones),
                                "observaciones_dos" => $JwtAuth->desencriptar($valInform->observaciones),
                                "horas_activas" => $valInform->horas_activas,
                                "horas_activas_dos" => $valInform->horas_activas,
                                "personal_realiza" => ucwords($personal_realiza),
                                "aprobaciones" => $aprobaciones,
                                "rev_aprob" => $rev_aprob,
                                "status_aprob" => $status_aprob,
                                "comentarios_aprob" => $comentarios_aprob,
                                "evidencias_true" => $lista_evidencias_true,
                                "evidencias_false" => $lista_evidencias_false,
                            );
                            $informeArray[] = $rowlist;
                        }                                      
                                             
                        //informw de actividad de tareas
                        $infDelArray = array();
                        $informDelSubtar = DB::table("module_proyectos_informes AS inform")
                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                        ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                        //->join("module_proyectos_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                        ->where([
                            'inform.status_inf' => FALSE,
                            'subtar.token_tarea' => $value->token_tarea,
                            'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                        ])->orderBy('inform.id','DESC')->get();
                            
                        foreach ($informDelSubtar as $valInf){
                            if ($valInf->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInf->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInf->folio_informe).'-'.$valInf->post_folio_informe;
                            }
                                
                            $rowlist = array(
                                "token_informe" => $valInf->token_informe,
                                "token_proyecto" => $parametrosArray['token_proyecto'],
                                "token_tarea" => $valInf->token_tarea,
                                "folio_inf" => $folio_inf,
                                "fecha_realizacion" => date('d-m-Y H:i:s',$valInf->fecha_realizacion),
                                "informe" => $JwtAuth->desencriptar($valInf->informe),
                                "personal_realiza" => $JwtAuth->desencriptar($valInf->paterno)." ".$JwtAuth->desencriptar($valInf->materno)." ".$JwtAuth->desencriptar($valInf->nombre),
                                "fecha_delete_inf" => date('d-m-Y H:i:s',$valInf->fecha_delete_inf),
                            );
                            $infDelArray[] = $rowlist;
                        }
                        
                        //recalendarizacion
                            $fin_tarea = "";
                            $fin_tarea_html = "";
                            $arrayRecalendar = array();
                            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar WHERE rectar.proyecto = tar.id 
                                AND tar.token_proyecto = ? AND rectar.tarea = subtar.id AND subtar.token_tarea = ?",
                                [$parametrosArray['token_proyecto'],$value->token_tarea]);
                                
                            if (count($selectRecalendar) > 0) {
                                $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                    WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar WHERE rectar.proyecto = tar.id 
                                    AND tar.token_proyecto = ? AND rectar.tarea = subtar.id AND subtar.token_tarea = ?)",
                                    [$parametrosArray['token_proyecto'],$value->token_tarea]);
                                
                                $fin_tarea = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                                $fin_tarea_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                                
                                $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion, 
                                    rectar.fecha_sistema,rectar.fecha_compromiso_nueva,pers.empleado_token,
                                    people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar JOIN vhum_empleados_catalogo AS pers 
                                    JOIN sos_personas AS people WHERE rectar.personal_opera = pers.id 
                                    AND pers.empleado_name = people.id AND rectar.proyecto = tar.id 
                                    AND tar.token_proyecto = ? AND rectar.tarea = subtar.id 
                                    AND subtar.token_tarea = ?",
                                    [$parametrosArray['token_proyecto'],$value->token_tarea]);
                                
                                foreach ($listaRecalendar as $valRecal) {
                                    //echo "valSub->empleado_token ".$valRecal->empleado_token;//exit;
                                    if ($valRecal->empleado_token == $value->empleado_token) {
                                        $personal_opera = "tú";
                                    } else {
                                        $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                            $JwtAuth->desencriptar($valRecal->materno)." ".
                                            $JwtAuth->desencriptar($valRecal->nombre);
                                    }
                                    //echo $personal_opera." ";
                                    $row = array(
                                       "token_calendarizacion" => $valRecal->token_calendarizacion,
                                       "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                       "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                       "personal_opera" => $personal_opera,
                                    );
                                    $arrayRecalendar[] = $row;
                                }  
                            } else {
                                $fin_tarea = date('d-m-Y H:i:s',$value->fin_tarea);
                                $fin_tarea_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fin_tarea);
                            }
                            
                        if ($value->upload_evidencias == TRUE) {
                            $evidenciasUpload = true;
                        } else {
                            $evidenciasUpload = false;
                        }         
                             
                        //tareas pendientes
                            $tarea_enabled = true;
                            $tareas_enabled_array = array();
                            $selectTareaPend = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$value->token_tarea]);
                            $selectwithOut = DB::table("module_proyectos_tareas AS tar")
                            ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                            ->where("tar.status","=",TRUE)
                            ->where("tar.id","<",$selectTareaPend[0]->id)
                            ->where("tar.token_tarea","!=",$value->token_tarea)
                            ->where("proy.token_proyecto","=",$parametrosArray['token_proyecto'])
                            ->get();
                            
                            if (count($selectwithOut) > 0) {
                                foreach ($selectwithOut as $vPend){
                                    $folio_tar_enabled = 'TAR-'.$JwtAuth->generarFolio($vPend->folio_tarea);
                                    if ($vPend->post_folio_tar != NULL) $folio_tar_enabled = $folio_tar_enabled.'-'.$vPend->post_folio_tar;
                                    
                                    $realizacion_tar_enabled = $vPend->realizacion == TRUE ? true : false;
                                
                                    $selectPendTar = DB::select("SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy 
                                        JOIN module_proyectos_tareas AS tar_pend JOIN module_proyectos_tareas AS tar_ant WHERE cad.proyecto = proy.id 
                                        AND proy.token_proyecto = ? AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ? AND cad.tarea_anterior = tar_ant.id 
                                        AND tar_ant.token_tarea = ?",[$parametrosArray['token_proyecto'],$value->token_tarea,$vPend->token_tarea]);
                                    //->where(["tar.token_tarea" => $vPend->token_tarea])->get();
                                
                                    $vinculado_cadena = false;
                                    if (count($selectPendTar) > 0) {
                                        $vinculado_cadena = true;
                                    }
                                
                                    $row_pend = array(
                                        "token_tarea" => $vPend->token_tarea,
                                        "folio_tarea" => $folio_tar_enabled,
                                        "tarea_nombre" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($vPend->tarea_nombre)),
                                        "tarea_descripcion" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($vPend->tarea_descripcion)),
                                        "realizacion" => $realizacion_tar_enabled,
                                        "vinculado" => $vinculado_cadena,
                                    );
                                    $tareas_enabled_array[] = $row_pend;
                                }
                            }
                            
                            $queryPendTar = DB::select("SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy
                                JOIN module_proyectos_tareas AS tar_pend WHERE cad.proyecto = proy.id AND proy.token_proyecto = ? 
                                AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ?",[$parametrosArray['token_proyecto'],$value->token_tarea]);
                            
                            if (count($queryPendTar) > 0) {
                                $tarea_enabled = false;
                            } else {
                                $tarea_enabled = true;
                            }
                                 
                        $rowTar = array(
                            "folio_tar" => $folio_tar,
                            "token_proyecto" => $parametrosArray['token_proyecto'],
                            "upload_evidencias" => $evidenciasUpload,
                            "token_tarea" => $value->token_tarea,
                            "tarea_nombre" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($value->tarea_nombre)),
                            "tarea_descripcion" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($value->tarea_descripcion)),
                            "inicio_tarea" => date('d-m-Y H:i:s',$value->inicio_tarea),
                            
                            //"fin_tarea" => date('d-m-Y H:i:s',$value->fin_tarea),
                            "fin_tarea" => $fin_tarea,
                            "fin_tarea_original" => $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fin_tarea),
                            "html_fin_tarea" => $fin_tarea_html,
                            "tareaRecalendar" => $arrayRecalendar,
                            
                            "realizacion" => $semaforo_realizacion,
                            "equipoResponsableMin" => $equipoResponsableMin,
                            "equipoResponsableMax" => $equipoResponsableMax,
                            "tottal_actividades" => $tottal_actividades,
                            "enable_tarea" => $tarea_enabled,
                            "tareas_enabled_list" => $tareas_enabled_array,
                            "open_inside_tarea" => false,
                            "detalle_inside_tarea" => [],
                            "informeArray" => $informeArray,
                            "informeDelArray" => $infDelArray,
                            "isTarRegTarAntCollapsed" => true,
                            "isTarInformesListCollapsed" => true,
                            "isTarInformesDeletedCollapsed" => true,
                            "isTarInformesNewCollapsed" => true,
                            "isTarEditCollapsed" => true,
                            "isTarTeamCollapsed" => true,
                            "isTarRecalCollapsed" => true,
                        );
                        $arrayTareas[] = $rowTar;
                        
                        if ($value->realizacion == TRUE) {
                            $tarGreenArray[] = $rowTar;
                        } else {
                            $time_fin = time()+(86400*5);
                            if ($value->fin_tarea > $time_fin) {
                                $tarYellowArray[] = $rowTar;
                            } else if ($value->fin_tarea > time() && $value->fin_tarea < $time_fin) {
                                $tarYellowArray[] = $rowTar;
                            } else if ($value->fin_tarea <= time()){
                                $tarRojoArray[] = $rowTar;
                            }
                        }
                    }
                    
                    $dataMensaje = array(
                        "status" => "success",
                        "code" => 200,
                        //"proyecto" => $contentProyecto,
                        "ttotal_subtareas" => $ttotal_subtareas,
                        "total_subterminadas" => $total_subterminadas,
                        "tarea_list" => $arrayTareas,
                        "tar_green" => $tarGreenArray,
                        "tar_yellow" => $tarYellowArray,
                        "tar_red" => $tarRojoArray,
                        "tarea_back" => $arrayTareas,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
                    
        public function recoverTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
            $tarGreenArray = array();
            $tarYellowArray = array();
            $tarRojoArray = array();
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    
                    if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                        $queryProyecto = DB::table("module_proyectos_responsable AS resp_pr")
                        ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                        ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                        ->where(["proy.token_proyecto" => $token_proyecto,"emp.empresa_token" => $usuario->empresa_token])->get();
                    } else {
                        $queryProyecto = DB::table("module_proyectos_responsable AS resp_pr")
                        ->join("module_proyectos AS proy","resp_pr.proyecto","=","proy.id")
                        ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                        ->where(["proy.token_proyecto" => $token_proyecto,"users.usuario_token" => $usuario->user_token])->get();
                    }
                    
                    foreach ($queryProyecto as $qProy) { 
                        //echo $qProy->tipo_pp;
                        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                            $listaTareas = DB::table("module_proyectos_tareas AS tar")
                            ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                            ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                            ->where([
                                "tar.status" => TRUE,
                                "tar.token_tarea" => $token_tarea,
                                "proy.token_proyecto" => $token_proyecto,
                                "emp.empresa_token" => $usuario->empresa_token,
                                "users.usuario_token" => $usuario->user_token
                            ])->get(); 
                        } else {
                            if ($qProy->tipo_pp == 'cr') {
                                $listaTareas = DB::table("module_proyectos_tareas AS tar")
                                ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                                ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                                ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                                ->where([
                                    "resp_pr.tipo_pp" => "cr",
                                    "tar.status" => TRUE,
                                    "tar.token_tarea" => $token_tarea,
                                    "proy.token_proyecto" => $token_proyecto,
                                    "emp.empresa_token" => $usuario->empresa_token,
                                    "users.usuario_token" => $usuario->user_token
                                ])->get(); 
                            } else if ($qProy->tipo_pp == 'li') {
                                $listaTareas = DB::table("module_proyectos_tareas AS tar")
                                ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                                ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                                ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                                ->where([
                                    "resp_pr.tipo_pp" => "li",
                                    "tar.status" => TRUE,
                                    "tar.token_tarea" => $token_tarea,
                                    "proy.token_proyecto" => $token_proyecto,
                                    "emp.empresa_token" => $usuario->empresa_token,
                                    "users.usuario_token" => $usuario->user_token
                                ])->get();
                            } else if ($qProy->tipo_pp == 'eq') {
                                $listaTareas = DB::table("module_proyectos_tareas AS tar")
                                ->join("module_proyectos_tarea_responsable AS resp_pr","tar.id","=","resp_pr.tarea")
                                ->join("vhum_empleados_catalogo AS pers","resp_pr.personal","=","pers.id")
                                ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                                ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                                ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                                ->where([
                                    "tar.status" => TRUE,
                                    "tar.token_tarea" => $token_tarea,
                                    "users.usuario_token" => $usuario->user_token,
                                    "proy.token_proyecto" => $token_proyecto,
                                    "emp.empresa_token" => $usuario->empresa_token,
                                ])->get();
                            }
                        }
                        
                        $ttotal_subtareas = count($listaTareas);
                        //echo $ttotal_subtareas; 
                        $total_subterminadas = 0;
                        
                        foreach ($listaTareas as $value) {
                            if ($value->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                            }
                            //equipo de trabajo
                                $equipoTrabajoDetalle = array();
                                $equipoResponsableMin = array();
                                $equipoResponsableMax = array();
                                $selectEquipo = DB::select("SELECT pers.empleado_token,people.paterno,
                                    people.materno,people.nombre FROM vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN 
                                    module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                                    AND proy.id = resp.proyecto AND resp.tipo_pp = 'eq' AND resp.personal = pers.id
                                    AND pers.empleado_name = people.id",[$value->token_proyecto]);
                                    
                                foreach($selectEquipo as $valEquipo){
                                    $pers_tarea_count = DB::table("sos_personas AS people")
                                    ->join("vhum_empleados_catalogo AS pers","people.id","=","pers.empleado_name")
                                    ->join("module_proyectos_tarea_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                                    ->join("module_proyectos_tareas AS subtar","resp_tar.tarea","=","subtar.id")
                                    ->join("module_proyectos AS tar","resp_tar.proyecto","=","tar.id")
                                    ->where([
                                        'pers.empleado_token' => $valEquipo->empleado_token,
                                        'subtar.token_tarea' => $value->token_tarea,
                                        'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                                    ])->orderBy('subtar.id','DESC')->count();
                                    
                                    if ($pers_tarea_count == 1) {
                                        $selected = true;
                                    } else {
                                        $selected = false;
                                    }
                                    //echo $selected." ";
                                    $nombre_integ = $JwtAuth->desencriptar($valEquipo->paterno)." ".
                                        $JwtAuth->desencriptar($valEquipo->materno)." ".
                                        $JwtAuth->desencriptar($valEquipo->nombre);
                                    
                                    $each = array(
                                        "token_pers_equipo" => $valEquipo->empleado_token,
                                        "nombre_integ" => ucwords($nombre_integ),
                                        "selected" => $selected,
                                    );
                                    $equipoResponsableMax[] = $each;
                            
                                    if ($selected == true) {
                                        $equipoResponsableMin[] = $each;
                                    }
                                }
                            
                            if ($value->realizacion == TRUE) {
                                $semaforo_realizacion = 'bg-green-600';
                            } else {
                                $time_fin = time()+(86400*5);
                                if ($value->fin_tarea > $time_fin) {
                                    $semaforo_realizacion = 'bg-yellow-500';
                                } else if ($value->fin_tarea > time() && $value->fin_tarea < $time_fin) {
                                    $semaforo_realizacion = 'bg-yellow-300';
                                } else if ($value->fin_tarea <= time()){
                                    $semaforo_realizacion = 'bg-red-600';
                                }
                            }
                                
                            if ($value->post_folio_tar == NULL) {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($value->folio_tarea);
                            } else {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($value->folio_tarea).'-'.$value->post_folio_tar;
                            }
                                 
                            //informw de actividad de tareas
                            $informeArray = array();
                            $informeSubtarea = DB::table("module_proyectos_informes AS inform")
                            ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                            ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                            //->join("module_proyectos_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                            ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                            ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                            ->where([
                                'inform.status_inf' => TRUE,
                                'subtar.token_tarea' => $value->token_tarea,
                                'tar.token_proyecto' => $token_proyecto,
                            ])->orderBy('inform.id','DESC')->get();
                    
                            $tottal_actividades = count($informeSubtarea);
                            $actividades_aprob = 0;
                                
                            foreach ($informeSubtarea as $valInform){
                                if ($valInform->post_folio_informe == NULL) {
                                    $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                                } else {
                                    $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                                }
                                
                                $aprobaciones = array();
                                $select_aprobar = DB::table("module_proyectos_informes_aprobar AS aprob")
                                ->join("module_proyectos_informes AS inf","aprob.informe","=","inf.id")
                                ->join("module_proyectos_tareas AS subtar","aprob.tarea","=","subtar.id")
                                ->join("module_proyectos AS tar","aprob.proyecto","=","tar.id")
                                ->where([
                                    'inf.token_informe' => $valInform->token_informe,
                                    'subtar.token_tarea' => $value->token_tarea,
                                    'tar.token_proyecto' => $token_proyecto,
                                ])->orderBy('aprob.id','DESC')->get();
                                
                                foreach ($select_aprobar as $valAprov) {
                                    if ($valAprov->aprobado_list == TRUE) {
                                        $aprobado_list = "yes_auth";
                                    } else {
                                        $aprobado_list = "not_auth";
                                    }
                                    
                                    $row = array(
                                        "fecha_aprobar" => date('d-m-Y H:i:s',$valAprov->fecha_aprobar),
                                        "aprobado_list" => $aprobado_list,
                                        "observaciones" => $JwtAuth->desencriptar($valAprov->comentarios_aprob),
                                    );
                                    $aprobaciones[] = $row;
                                }
                                
                                $comentarios_aprob = null;
                                if ($valInform->revisado == TRUE) {
                                    $detectApprobes = DB::select("SELECT approv.aprobado_list FROM module_proyectos_informes_aprobar AS approv
                                        JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                        JOIN module_proyectos_informes AS inf 
                                        WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                        AND approv.tarea = tar.id AND tar.token_tarea = ?
                                        AND approv.informe = inf.id AND inf.token_informe = ?",
                                        [$token_proyecto,$value->token_tarea,$valInform->token_informe]);
            
                                    if (count($detectApprobes) == 0) {
                                        $status_aprob = "process";
                                        $rev_aprob = "en revision";   
                                    } else {
                                        $lastApprobes = DB::select("SELECT aprobado_list,comentarios_aprob FROM module_proyectos_informes_aprobar 
                                            WHERE id = (SELECT MAX(approv.id) FROM module_proyectos_informes_aprobar AS approv
                                        JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                        JOIN module_proyectos_informes AS inf 
                                        WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                        AND approv.tarea = tar.id AND tar.token_tarea = ?
                                        AND approv.informe = inf.id AND inf.token_informe = ?)",
                                        [$token_proyecto,$value->token_tarea,$valInform->token_informe]);
                                        
                                        if (end($lastApprobes)->aprobado_list == TRUE) {
                                            $status_aprob = "success";
                                            $rev_aprob = "revisado y aprobado";
                                            ++$actividades_aprob;
                                        } else {
                                            $status_aprob = "error";
                                            $rev_aprob = "revisado sin aprobar"; 
                                        }
                                        $comentarios_aprob = $JwtAuth->desencriptar(end($lastApprobes)->comentarios_aprob);
                                    }  
                                } else {
                                    $rev_aprob = "sin revisar";
                                    $status_aprob = "empty";
                                }
                                    
                                $personal_realiza = $JwtAuth->desencriptarNombres($valInform->paterno,$valInform->materno,$valInform->nombre);
                                
                                $lista_evidencias_true = array();
                                $selectIdEvid = DB::table("sos_documentos AS docs")
                                ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                                ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                                ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                                ->where([
                                    "status_documento" => TRUE,
                                    "proy.token_proyecto" => $token_proyecto,
                                    "tar.token_tarea" => $value->token_tarea,
                                    "inf.token_informe" => $valInform->token_informe,
                                ])->get();
                                if (count($selectIdEvid) > 0) {
                                    foreach ($selectIdEvid as $vDoc){
                                        $rowDocs = array(
                                            "token_documento" => $vDoc->token_documento,
                                            "ext_doc" => $vDoc->extension_documento,
                                            "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                            "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                        );
                                        $lista_evidencias_true[] = $rowDocs;
                                    }
                                }
                                
                                $lista_evidencias_false = array();
                                $selectEvidDeleted = DB::table("sos_documentos AS docs")
                                ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                                ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                                ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                                ->where([
                                    "status_documento" => FALSE,
                                    "proy.token_proyecto" => $token_proyecto,
                                    "tar.token_tarea" => $value->token_tarea,
                                    "inf.token_informe" => $valInform->token_informe,
                                ])->get();  
                                if (count($selectEvidDeleted) > 0) {
                                    foreach ($selectEvidDeleted as $vDoc){
                                        $rowDocs = array(
                                            "token_documento" => $vDoc->token_documento,
                                            "ext_doc" => $vDoc->extension_documento,
                                            "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                            "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                        );
                                        $lista_evidencias_false[] = $rowDocs;
                                    }
                                }
                                    
                                $rowlist = array(
                                    "token_informe" => $valInform->token_informe,
                                    "token_proyecto" => $parametrosArray['token_proyecto'],
                                    "token_tarea" => $value->token_tarea,
                                    "folio_inf" => $folio_inf,
                                    "fecha_realizacion" => date('d-m-Y H:i:s',$valInform->fecha_realizacion),
                                    "informe" => $JwtAuth->desencriptar($valInform->informe),
                                    "informe_dos" => $JwtAuth->desencriptar($valInform->informe),
                                    "observaciones_revision" => "",
                                    "observaciones" => $JwtAuth->desencriptar($valInform->observaciones),
                                    "observaciones_dos" => $JwtAuth->desencriptar($valInform->observaciones),
                                    "horas_activas" => $valInform->horas_activas,
                                    "horas_activas_dos" => $valInform->horas_activas,
                                    "personal_realiza" => ucwords($personal_realiza),
                                    "aprobaciones" => $aprobaciones,
                                    "rev_aprob" => $rev_aprob,
                                    "status_aprob" => $status_aprob,
                                    "comentarios_aprob" => $comentarios_aprob,
                                    "evidencias_true" => $lista_evidencias_true,
                                    "evidencias_false" => $lista_evidencias_false,
                                );
                                $informeArray[] = $rowlist;
                            }                                      
                                     
                            //informw de actividad de tareas
                            $infDelArray = array();
                            $informDelSubtar = DB::table("module_proyectos_informes AS inform")
                            ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                            ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                            //->join("module_proyectos_responsable AS resp_tar","pers.id","=","resp_tar.personal")
                            ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                            ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                            ->where([
                                'inform.status_inf' => FALSE,
                                'subtar.token_tarea' => $value->token_tarea,
                                'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                            ])->orderBy('inform.id','DESC')->get();
                                
                            foreach ($informDelSubtar as $valInf){
                                if ($valInf->post_folio_informe == NULL) {
                                    $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInf->folio_informe);
                                } else {
                                    $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInf->folio_informe).'-'.$valInf->post_folio_informe;
                                }
                                    
                                $rowlist = array(
                                    "token_informe" => $valInf->token_informe,
                                    "token_proyecto" => $parametrosArray['token_proyecto'],
                                    "token_tarea" => $parametrosArray['token_tarea'],
                                    "folio_inf" => $folio_inf,
                                    "fecha_realizacion" => date('d-m-Y H:i:s',$valInf->fecha_realizacion),
                                    "informe" => $JwtAuth->desencriptar($valInf->informe),
                                    "personal_realiza" => $JwtAuth->desencriptar($valInf->paterno)." ".$JwtAuth->desencriptar($valInf->materno)." ".$JwtAuth->desencriptar($valInf->nombre),
                                    "fecha_delete_inf" => date('d-m-Y H:i:s',$valInf->fecha_delete_inf),
                                );
                                $infDelArray[] = $rowlist;
                            } 
                            
                            //recalendarizacion
                                $fin_tarea = "";
                                $fin_tarea_html = "";
                                $arrayRecalendar = array();
                                $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                                    JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar WHERE rectar.proyecto = tar.id 
                                    AND tar.token_proyecto = ? AND rectar.tarea = subtar.id AND subtar.token_tarea = ?",
                                    [$parametrosArray['token_proyecto'],$value->token_tarea]);
                                    
                                if (count($selectRecalendar) > 0) {
                                    $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                        WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                        JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar WHERE rectar.proyecto = tar.id 
                                        AND tar.token_proyecto = ? AND rectar.tarea = subtar.id AND subtar.token_tarea = ?)",
                                        [$parametrosArray['token_proyecto'],$value->token_tarea]);
                                    
                                    $fin_tarea = date('d-m-Y H:i:s',$nuevaFechaFin[0]->fecha_compromiso_nueva)." (recalendarizada)";
                                    $fin_tarea_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                                    
                                    $listaRecalendar = DB::select("SELECT rectar.token_calendarizacion, 
                                        rectar.fecha_sistema,rectar.fecha_compromiso_nueva,pers.empleado_token,
                                        people.paterno,people.materno,people.nombre FROM module_proyectos_calendarizacion AS rectar 
                                        JOIN module_proyectos AS tar JOIN module_proyectos_tareas AS subtar JOIN vhum_empleados_catalogo AS pers 
                                        JOIN sos_personas AS people WHERE rectar.personal_opera = pers.id 
                                        AND pers.empleado_name = people.id AND rectar.proyecto = tar.id 
                                        AND tar.token_proyecto = ? AND rectar.tarea = subtar.id 
                                        AND subtar.token_tarea = ?",
                                        [$parametrosArray['token_proyecto'],$value->token_tarea]);
                                    
                                    foreach ($listaRecalendar as $valRecal) {
                                        //echo "valSub->empleado_token ".$valRecal->empleado_token;//exit;
                                        if ($valRecal->empleado_token == $value->empleado_token) {
                                            $personal_opera = "tú";
                                        } else {
                                            $personal_opera = $JwtAuth->desencriptar($valRecal->paterno)." ".
                                                $JwtAuth->desencriptar($valRecal->materno)." ".
                                                $JwtAuth->desencriptar($valRecal->nombre);
                                        }
                                        //echo $personal_opera." ";
                                        $row = array(
                                           "token_calendarizacion" => $valRecal->token_calendarizacion,
                                           "fecha_sistema" => date('d-m-Y H:i:s',$valRecal->fecha_sistema),
                                           "fecha_compromiso_nueva" => gmdate('Y-m-d H:i:s',$valRecal->fecha_compromiso_nueva),
                                           "personal_opera" => $personal_opera,
                                        );
                                        $arrayRecalendar[] = $row;
                                    }  
                                } else {
                                    $fin_tarea = date('d-m-Y H:i:s',$value->fin_tarea);
                                    $fin_tarea_html = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fin_tarea);
                                }
                                
                            if ($value->upload_evidencias == TRUE) {
                                $evidenciasUpload = true;
                            } else {
                                $evidenciasUpload = false;
                            }         
                                    
                            //tareas pendientes
                                $tarea_enabled = true;
                                $tareas_enabled_array = array();
                                $selectTareaPend = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$value->token_tarea]);
                                $selectwithOut = DB::table("module_proyectos_tareas AS tar")
                                ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                                ->where("tar.status","=",TRUE)
                                ->where("tar.id","<",$selectTareaPend[0]->id)
                                ->where("tar.token_tarea","!=",$value->token_tarea)
                                ->where("proy.token_proyecto","=",$parametrosArray['token_proyecto'])
                                ->get();
                                            
                                if (count($selectwithOut) > 0) {
                                    foreach ($selectwithOut as $vPend){
                                        $folio_tar_enabled = 'TAR-'.$JwtAuth->generarFolio($vPend->folio_tarea);
                                        if ($vPend->post_folio_tar != NULL) $folio_tar_enabled = $folio_tar_enabled.'-'.$vPend->post_folio_tar;
                                        
                                        $realizacion_tar_enabled = false;
                                        if ($vPend->realizacion == TRUE) $realizacion_tar_enabled = true;
                                    
                                        $selectPendTar = DB::select("SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy 
                                            JOIN module_proyectos_tareas AS tar_pend JOIN module_proyectos_tareas AS tar_ant WHERE cad.proyecto = proy.id 
                                            AND proy.token_proyecto = ? AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ? AND cad.tarea_anterior = tar_ant.id 
                                            AND tar_ant.token_tarea = ?",[$parametrosArray['token_proyecto'],$value->token_tarea,$vPend->token_tarea]);
                                        //->where(["tar.token_tarea" => $vPend->token_tarea])->get();
                                    
                                        $vinculado_cadena = false;
                                        if (count($selectPendTar) > 0) {
                                            $vinculado_cadena = true;
                                        }
                                    
                                        $row_pend = array(
                                            "token_tarea" => $vPend->token_tarea,
                                            "folio_tarea" => $folio_tar_enabled,
                                            "tarea_nombre" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($vPend->tarea_nombre)),
                                            "tarea_descripcion" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($vPend->tarea_descripcion)),
                                            "realizacion" => $realizacion_tar_enabled,
                                            "vinculado" => $vinculado_cadena,
                                        );
                                        $tareas_enabled_array[] = $row_pend;
                                    }
                                }
                                
                                $queryPendTar = DB::select("SELECT cad.id FROM module_proyectos_tareas_cadena AS cad JOIN module_proyectos AS proy
                                    JOIN module_proyectos_tareas AS tar_pend WHERE cad.proyecto = proy.id AND proy.token_proyecto = ? 
                                    AND cad.tarea = tar_pend.id AND tar_pend.token_tarea = ?",[$parametrosArray['token_proyecto'],$value->token_tarea]);
                                //->where(["tar.token_tarea" => $vPend->token_tarea])->get();
                                
                                if (count($queryPendTar) > 0) {
                                    $tarea_enabled = false;
                                } else {
                                    $tarea_enabled = true;
                                }
                                     
                            $rowTar = array(
                                "folio_tar" => $folio_tar,
                                "token_proyecto" => $parametrosArray['token_proyecto'],
                                "upload_evidencias" => $evidenciasUpload,
                                "token_tarea" => $value->token_tarea,
                                "tarea_nombre" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($value->tarea_nombre)),
                                "tarea_descripcion" => $JwtAuth->utf8Validate($JwtAuth->desencriptar($value->tarea_descripcion)),
                                "inicio_tarea" => date('d-m-Y H:i:s',$value->inicio_tarea),
                                
                                //"fin_tarea" => date('d-m-Y H:i:s',$value->fin_tarea),
                                "fin_tarea" => $fin_tarea,
                                "fin_tarea_original" => $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fin_tarea),
                                "html_fin_tarea" => $fin_tarea_html,
                                "tareaRecalendar" => $arrayRecalendar,
                                
                                "realizacion" => $semaforo_realizacion,
                                "equipoResponsableMin" => $equipoResponsableMin,
                                "equipoResponsableMax" => $equipoResponsableMax,
                                "tottal_actividades" => $tottal_actividades,
                                "enable_tarea" => $tarea_enabled,
                                "tareas_enabled_list" => $tareas_enabled_array,
                                "open_inside_tarea" => false,
                                "detalle_inside_tarea" => [],
                                "informeArray" => $informeArray,
                                "informeDelArray" => $infDelArray,
                                "isTarRegTarAntCollapsed" => true,
                                "isTarInformesListCollapsed" => true,
                                "isTarInformesDeletedCollapsed" => true,
                                "isTarInformesNewCollapsed" => true,
                                "isTarEditCollapsed" => true,
                                "isTarTeamCollapsed" => true,
                                "isTarRecalCollapsed" => true,
                            );
                            $arrayTareas[] = $rowTar;
                            
                            if ($value->realizacion == TRUE) {
                                $tarGreenArray[] = $rowTar;
                            } else {
                                $time_fin = time()+(86400*5);
                                if ($value->fin_tarea > $time_fin) {
                                    $tarYellowArray[] = $rowTar;
                                } else if ($value->fin_tarea > time() && $value->fin_tarea < $time_fin) {
                                    $tarYellowArray[] = $rowTar;
                                } else if ($value->fin_tarea <= time()){
                                    $tarRojoArray[] = $rowTar;
                                }
                            }
                        }
                        
                        $dataMensaje = array(
                            "status" => "success",
                            "code" => 200,
                            //"proyecto" => $contentProyecto,
                            "ttotal_subtareas" => $ttotal_subtareas,
                            "total_subterminadas" => $total_subterminadas,
                            "tarea_list" => $arrayTareas,
                            "tar_green" => $tarGreenArray,
                            "tar_yellow" => $tarYellowArray,
                            "tar_red" => $tarRojoArray,
                            "tarea_back" => $arrayTareas,
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }   

        public function revisionTareaAcceso(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];

                    $getTareaQuery = DB::table("module_proyectos_tareas AS tar")
                    ->join("module_proyectos AS proy","tar.proyecto","=","proy.id")
                    ->where([
                        "proy.token_proyecto" => $token_proyecto,
                        "tar.token_tarea" => $token_tarea,
                    ])->get();
                    
                    if ($getTareaQuery[0]->post_folio_tar == NULL) {
                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($getTareaQuery[0]->folio_tarea);
                    } else {
                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($getTareaQuery[0]->folio_tarea).'-'.$getTareaQuery[0]->post_folio_tar;
                    }
                            
                    $tarea_nombre = ucfirst(strtolower($JwtAuth->desencriptar($getTareaQuery[0]->tarea_nombre)));
                    
                    $pers_tarea_count = DB::table("module_proyectos_tarea_responsable AS resp_tar")
                    ->join("module_proyectos AS proy","resp_tar.proyecto","=","proy.id")
                    ->join("module_proyectos_tareas AS tar","resp_tar.tarea","=","tar.id")
                    ->join("vhum_empleados_catalogo AS pers","resp_tar.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
                    ->where([
                        "proy.token_proyecto" => $token_proyecto,
                        "tar.token_tarea" => $token_tarea,
                        "users.usuario_token" => $usuario->user_token,
                    ])->get();
                    
                    if (count($pers_tarea_count) == 1) {
                        //foreach ($pers_tarea_count as $vCount) {}
                        $aceptado_en_tarea = true;
                        $mensaje = "";
                    } else {
                        $aceptado_en_tarea = false;
                        $mensaje = 'no puedes ingresar al contenido de la tarea "'.$folio_tar.' - '.$tarea_nombre.'" por que no estas registrado en el equipo de responsables de esta';
                    }
                    
                    $dataMensaje = array(
                        "status" => "success",
                        "code" => 200,
                        "aceptado_en_tarea" => $aceptado_en_tarea,
                        "message" => $mensaje,
                    );
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function tareaDependienteAgregar(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    "user_token" => "required|string",
                    "token_proyecto" => "required|string",
                    "token_tarea_pendiente" => "required|string",
                    "token_tarea_anterior" => "required|string",
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true); 
                    $tknProy = $parametrosArray["token_proyecto"];
                    $tknTarPend = $parametrosArray["token_tarea_pendiente"];
                    $tknTarAnt = $parametrosArray["token_tarea_anterior"];
                    
                    if (isset($tknProy) && !empty($tknProy) && isset($tknTarPend) && !empty($tknTarPend) && isset($tknTarAnt) && !empty($tknTarAnt)) {
                        $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$tknProy]);
                        $selectTareaPend = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$tknTarPend]);
                        $selectTareaAnt = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$tknTarAnt]);
                        
                        $insertCadenaTar = DB::table("module_proyectos_tareas_cadena")
                        ->insert(
                            array(
                                "proyecto" => $selectProyecto[0]->id,
                                "tarea" => $selectTareaPend[0]->id,
                                "tarea_anterior" => $selectTareaAnt[0]->id,
                            )
                        ); 
                        
                        if ($insertCadenaTar) {
                            $dataMensaje = array("status" => "success","code" => 200,"message" => "Dependencia de tarea registrada");
                        } else {
                            $dataMensaje = array("status" => "error","code" => 200,"message" => "Dependencia de tarea no registrada");
                        }
                        
                    } else {
                        if (!isset($tknProy) || empty($tknProy)) $mensaje_error = "No hay referencia del proyecto seleccionado, verifique su información";
                        if (!isset($tknTarPend) || empty($tknTarPend)) $mensaje_error = "No hay referencia de tarea seleccionada, verifique su información";
                        if (!isset($tknTarAnt) || empty($tknTarAnt)) $mensaje_error = "No hay referencia de tarea registrada, verifique su información";
                        $dataMensaje = array("status" => "error","code" => 200,"message" => $mensaje_error);
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function tareaDependienteRemover(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    "user_token" => "required|string",
                    "token_proyecto" => "required|string",
                    "token_tarea_pendiente" => "required|string",
                    "token_tarea_anterior" => "required|string",
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true); 
                    $tknProy = $parametrosArray["token_proyecto"];
                    $tknTarPend = $parametrosArray["token_tarea_pendiente"];
                    $tknTarAnt = $parametrosArray["token_tarea_anterior"];
                    
                    if (isset($tknProy) && !empty($tknProy) && isset($tknTarPend) && !empty($tknTarPend) && isset($tknTarAnt) && !empty($tknTarAnt)) {
                        $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$tknProy]);
                        $selectTareaPend = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$tknTarPend]);
                        $selectTareaAnt = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$tknTarAnt]);
                        
                        $deleteCadenaTar = DB::table("module_proyectos_tareas_cadena")
                        ->where([
                            "proyecto" => $selectProyecto[0]->id,
                            "tarea" => $selectTareaPend[0]->id,
                            "tarea_anterior" => $selectTareaAnt[0]->id,
                        ])->limit(1)->delete();
                        
                        if ($deleteCadenaTar) {
                            $dataMensaje = array("status" => "success","code" => 200,"message" => "Dependencia de tarea eliminada");
                        } else {
                            $dataMensaje = array("status" => "error","code" => 200,"message" => "Dependencia de tarea no eliminada");
                        }
                        
                    } else {
                        if (!isset($tknProy) || empty($tknProy)) $mensaje_error = "No hay referencia del proyecto seleccionado, verifique su información";
                        if (!isset($tknTarPend) || empty($tknTarPend)) $mensaje_error = "No hay referencia de tarea seleccionada, verifique su información";
                        if (!isset($tknTarAnt) || empty($tknTarAnt)) $mensaje_error = "No hay referencia de tarea registrada, verifique su información";
                        $dataMensaje = array("status" => "error","code" => 200,"message" => $mensaje_error);
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }

        public function actualizaNameTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = utf8_encode($request->input('json'));
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            //echo $parametrosArray;exit;
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'nameTarea' => 'required|string',     
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $validaTarea = false;
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $nameTarea = $parametrosArray['nameTarea'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && 
                        isset($token_tarea) && !empty($token_tarea) && 
                        isset($nameTarea) && !empty($nameTarea) && preg_match($patron,$nameTarea)) {
                        
                        $nameTarea = str_replace(".diagon.","/",$nameTarea);
                        $nameTarea = str_replace(".porcent.","%",$nameTarea);
                        $nameTarea = str_replace(".ampersand.","&",$nameTarea);
                        $nameTarea = str_replace(".pessos.","$",$nameTarea);
                        //echo $nameTarea; exit;
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $folioTarea = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
                        
                        if ($folioTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folioTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folioTarea[0]->folio_tarea).'-'.$folioTarea[0]->post_folio_tar;
                        }
                            
                        $validaTarea = true;
                        
                        $cambioTarea = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->limit(1)->update(
                            array(
                                'subtar.tarea_nombre' => $JwtAuth->encriptar($nameTarea),      
                            )
                        );
                        
                        $selectLider = DB::table('module_proyectos_responsable AS resp')
    					->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'resp.tipo_pp' => 'li',
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
    					
                        if ($cambioTarea) {
                            $titulo_alerta = "Tarea con folio ".$folio_tar." ha sido actualizada";
                            $selectTarea = DB::table('module_proyectos_tareas')->where(['token_tarea' => $token_tarea])->get();
                            $personalToken = DB::select("SELECT tipo_pp FROM module_proyectos_responsable AS resp WHERE personal = ?",[$selectEmp[0]->userr]);
                            
                            if ($personalToken[0]->tipo_pp == 'cr') {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }   
                            if ($personalToken[0]->tipo_pp == 'li') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            if ($personalToken[0]->tipo_pp == 'eq') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                        
                            $dataMensaje = array(
                                'message' => $titulo_alerta,
                                "code" => 200,
                                'status' => 'success'
                            );
    
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Esta tarea no fue actualizada debido a errores internos, intente nuevamente'
                            );
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en proyecto seleccionado, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en tarea seleccionada, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($nameTarea) || empty($nameTarea) || !preg_match($patron,$nameTarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de nombre de tarea, verifique su información'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
        
        public function actualizaDescTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = utf8_encode($request->input('json'));
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            //echo $parametrosArray;exit;
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'descripTarea' => 'required|string',     
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $validaTarea = false;
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $descripTarea = $parametrosArray['descripTarea'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && 
                        isset($token_tarea) && !empty($token_tarea) && 
                        isset($descripTarea) && !empty($descripTarea) && preg_match($patron,$descripTarea)) {
                        
                        $descripTarea = str_replace(".diagon.","/",$descripTarea);
                        $descripTarea = str_replace(".porcent.","%",$descripTarea);
                        $descripTarea = str_replace(".ampersand.","&",$descripTarea);
                        $descripTarea = str_replace(".pessos.","$",$descripTarea);
                        //echo $nameTarea; exit;
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $folioTarea = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
                        
                        if ($folioTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folioTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folioTarea[0]->folio_tarea).'-'.$folioTarea[0]->post_folio_tar;
                        }
                            
                        $validaTarea = true;
                        
                        $cambioTarea = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->limit(1)->update(
                            array(
                                'subtar.tarea_descripcion' => $JwtAuth->encriptar($descripTarea),      
                            )
                        );
                        
                        $selectLider = DB::table('module_proyectos_responsable AS resp')
    					->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'resp.tipo_pp' => 'li',
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
    					
                        if ($cambioTarea) {
                            $titulo_alerta = "Tarea con folio ".$folio_tar." ha sido actualizada";
                            $selectTarea = DB::table('module_proyectos_tareas')->where(['token_tarea' => $token_tarea])->get();
                            $personalToken = DB::select("SELECT tipo_pp FROM module_proyectos_responsable AS resp WHERE personal = ?",[$selectEmp[0]->userr]);
                            
                            if ($personalToken[0]->tipo_pp == 'cr') {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }   
                            if ($personalToken[0]->tipo_pp == 'li') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            if ($personalToken[0]->tipo_pp == 'eq') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                        
                            $dataMensaje = array(
                                'message' => $titulo_alerta,
                                "code" => 200,
                                'status' => 'success'
                            );
    
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Esta tarea no fue actualizada debido a errores internos, intente nuevamente'
                            );
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en proyecto seleccionado, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en tarea seleccionada, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($descripTarea) || empty($descripTarea) || !preg_match($patron,$descripTarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de descripción para tarea, verifique su información'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
        
        public function actualizaTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = utf8_encode($request->input('json'));
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            //echo $parametrosArray;exit;
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'nameTarea' => 'required|string',
                    'descripTarea' => 'required|string',     
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $validaTarea = false;
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $nameTarea = $parametrosArray['nameTarea'];
                    $descripTarea = $parametrosArray['descripTarea'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && 
                        isset($token_tarea) && !empty($token_tarea) && 
                        isset($nameTarea) && !empty($nameTarea) && preg_match($patron,$nameTarea) &&
                        isset($descripTarea) && !empty($descripTarea) && preg_match($patron,$descripTarea)) {
                        
                        $nameTarea = str_replace(".diagon.","/",$nameTarea);
                        $nameTarea = str_replace(".porcent.","%",$nameTarea);
                        $nameTarea = str_replace(".ampersand.","&",$nameTarea);
                        $nameTarea = str_replace(".pessos.","$",$nameTarea);
                        
                        $descripTarea = str_replace(".diagon.","/",$descripTarea);
                        $descripTarea = str_replace(".porcent.","%",$descripTarea);
                        $descripTarea = str_replace(".ampersand.","&",$descripTarea);
                        $descripTarea = str_replace(".pessos.","$",$descripTarea);
                        //echo $nameTarea; exit;
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $folioTarea = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
                        
                        if ($folioTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folioTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($folioTarea[0]->folio_tarea).'-'.$folioTarea[0]->post_folio_tar;
                        }
                            
                        $validaTarea = true;
                        
                        $cambioTarea = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->limit(1)->update(
                            array(
                                'subtar.tarea_nombre' => $JwtAuth->encriptar($nameTarea),
                                'subtar.tarea_descripcion' => $JwtAuth->encriptar($descripTarea),      
                            )
                        );
                        
                        $selectLider = DB::table('module_proyectos_responsable AS resp')
    					->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->where([
    						'resp.tipo_pp' => 'li',
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    					])->get();
    					
                        if ($cambioTarea) {
                            $titulo_alerta = "Tarea con folio ".$folio_tar." ha sido actualizada";
                            $selectTarea = DB::table('module_proyectos_tareas')->where(['token_tarea' => $token_tarea])->get();
                            $personalToken = DB::select("SELECT tipo_pp FROM module_proyectos_responsable AS resp WHERE personal = ?",[$selectEmp[0]->userr]);
                            
                            if ($personalToken[0]->tipo_pp == 'cr') {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }   
                            if ($personalToken[0]->tipo_pp == 'li') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            if ($personalToken[0]->tipo_pp == 'eq') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                        
                            $dataMensaje = array(
                                'message' => $titulo_alerta,
                                "code" => 200,
                                'status' => 'success'
                            );
    
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Esta tarea no fue actualizada debido a errores internos, intente nuevamente'
                            );
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en proyecto seleccionado, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en tarea seleccionada, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($nameTarea) || empty($nameTarea) || !preg_match($patron,$nameTarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de nombre de tarea, verifique su información'
                            );
                        }
                        if (!isset($descripTarea) || empty($descripTarea) || !preg_match($patron,$descripTarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de descripción para tarea, verifique su información'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
    
        public function duplicaTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = utf8_encode($request->input('json'));
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            //echo $parametrosArray;exit;
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'nameTarea' => 'required|string',
                    'descripTarea' => 'required|string',  
                    'fecha_fin_tareaNew' => 'required|string',
                    'equipoResponsable' => 'array',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $patronFecha = '/^[0-9-]*$/';
                    $validaTarea = false;
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $nameTarea = $parametrosArray['nameTarea'];
                    $descripTarea = $parametrosArray['descripTarea'];
                    $fecha_fin = $parametrosArray['fecha_fin_tareaNew'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && 
                        isset($token_tarea) && !empty($token_tarea) && 
                        isset($nameTarea) && !empty($nameTarea) && preg_match($patron,$nameTarea) &&
                        isset($descripTarea) && !empty($descripTarea) && preg_match($patron,$descripTarea) &&
                        isset($fecha_fin) && !empty($fecha_fin) && preg_match($patronFecha,$fecha_fin)) {
                        
                        $nameTarea = str_replace(".diagon.","/",$nameTarea);
                        $nameTarea = str_replace(".porcent.","%",$nameTarea);
                        $nameTarea = str_replace(".ampersand.","&",$nameTarea);
                        $nameTarea = str_replace(".pessos.","$",$nameTarea);
                        
                        $descripTarea = str_replace(".diagon.","/",$descripTarea);
                        $descripTarea = str_replace(".porcent.","%",$descripTarea);
                        $descripTarea = str_replace(".ampersand.","&",$descripTarea);
                        $descripTarea = str_replace(".pessos.","$",$descripTarea);
                        //echo $nameTarea; exit;
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $subTareaList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->orderBy('subtar.id','DESC')->get();
        
                        foreach ($subTareaList as $valSub) {
                            //da_te_default_timezone_set($valSub->zona_horaria);
                            
                            if ($valSub->post_folio_tar == NULL) {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea);
                            } else {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valSub->folio_tarea).'-'.$valSub->post_folio_tar;
                            }
                            
                            $equipoResponsable = $parametrosArray['equipoResponsable'];
                            
                            //echo count($equipoResponsable);
                            //if (count($equipoResponsable) != 0) {
                            //    for ($i = 0; $i < count($equipoResponsable); $i++){
                            //        
                            //        $tokenEquForProy = DB::select("SELECT pers.id,resp.tipo_pp FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp
                            //                JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS subtar WHERE pers.empleado_token = ? AND pers.id = resp.personal 
                            //                AND resp.tipo_pp IS NULL AND resp.proyecto = proy.id AND proy.token_proyecto = ?
                            //                AND resp.tarea = subtar.id AND subtar.token_tarea = ?",
                            //                [$equipoResponsable[$i]['empleado_token'],$token_proyecto,$token_tarea]);
                            //        echo $equipoResponsable[$i]['empleado_token']." ".$tokenEquForProy[0]->id." ";
                            //    }
                            //}
                            //exit;
                            //$valSub->realizacion
                            //$valSub->folio_tarea
                            //$valSub->post_folio_tar
                            //$valSub->tarea_nombre
                            if ($JwtAuth->encriptar($nameTarea) == $valSub->tarea_nombre) {
                                $name_tarea_duplicada = $valSub->tarea_nombre;
                            } else {
                                $name_tarea_duplicada = $JwtAuth->encriptar($nameTarea);
                            }
                            
                            if ($JwtAuth->encriptar($descripTarea) == $valSub->tarea_descripcion) {
                                $descrip_tarea_duplicada = $valSub->tarea_descripcion;
                            } else {
                                $descrip_tarea_duplicada = $JwtAuth->encriptar($descripTarea);
                            }
                            
                            //$valSub->inicio_tarea
                            //$valSub->fin_tarea 
                            if ($JwtAuth->convierteFechaEpoc($fecha_fin) == $valSub->fin_tarea) {
                                $fin_tarea_duplicada = $valSub->fin_tarea;
                            } else {
                                $fin_tarea_duplicada = $JwtAuth->convierteFechaEpoc($fecha_fin);
                            }
                            
                            $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                            
                            $folioSistemaSub = DB::select("SELECT COUNT(subtar.id)+1 AS folio FROM module_proyectos_tareas AS subtar
                                JOIN module_proyectos AS tarprog WHERE subtar.proyecto = tarprog.id AND tarprog.token_proyecto = ?",[$token_proyecto]);
                            
                            if ($folioSistemaSub[0]->folio > 1) {
                                if ($folioSistemaSub[0]->folio == 1000000000) {
                                    $post_folio_db = DB::select("SELECT post_folio_tar FROM module_proyectos_tareas
                                        WHERE id = (SELECT Max(subtar.id) FROM module_proyectos_tareas AS subtar
                                        JOIN module_proyectos AS tarprog
                                        WHERE subtar.proyecto = tarprog.id AND tarprog.token_proyecto = ?)",[$parametrosArray['token_tarea']]);
        
                                    $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_tar);
                                    $folio_nuevo = 1;
                                } else {
                                    $post_folio = NULL;
                                    $folio_nuevo = $folioSistemaSub[0]->folio;
                                }
                            } else {
                                $post_folio = NULL;
                                $folio_nuevo = 1;
                            }
                            
                            if ($post_folio == NULL) {
                                $folio_new_tar = 'TAR-'.$JwtAuth->generarFolio($folio_nuevo);
                            } else {
                                $folio_new_tar = 'TAR-'.$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                            }
                            
                            $fecha_sistema = time();
                            $fecha_inicio = time();
                            
                            $tokenTareaDuplicada = $JwtAuth->encriptarToken($fecha_sistema.$fecha_inicio.$fecha_fin.$nameTarea.$descripTarea.$fecha_fin);
                            
                            $insertSubtarea = DB::table('module_proyectos_tareas')
                            ->insert(array(
                                "token_tarea" => $tokenTareaDuplicada,
                                "folio_tarea" => $folio_nuevo,
                                "post_folio_tar" => $post_folio,
                                "proyecto" => $selectProyecto[0]->id,
                                "tarea_nombre" => $name_tarea_duplicada,
                                "tarea_descripcion" => $descrip_tarea_duplicada,
                                "inicio_tarea" => $fecha_inicio,
                                "fin_tarea" => $fin_tarea_duplicada,
                                "realizacion" => FALSE,
                                "status" => TRUE,
                                "fecha_delete" => NULL,
                            ));
                            
                            if ($insertSubtarea) {
                                $selectTarea = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$tokenTareaDuplicada]);
                                if (count($equipoResponsable) != 0) {
                                    for ($i = 0; $i < count($equipoResponsable); $i++){
                                        $tokenEquForProy = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_tarea_responsable AS resp
                                            JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS subtar WHERE pers.empleado_token = ? AND pers.id = resp.personal 
                                            AND resp.proyecto = proy.id AND proy.token_proyecto = ? AND resp.tarea = subtar.id AND subtar.token_tarea = ?",
                                            [$equipoResponsable[$i]['token_pers_equipo'],$token_proyecto,$token_tarea]);
                                        $insertAlertaLider = DB::table('module_proyectos_tarea_responsable')
                                        ->insert(
                                            array(
                                                "proyecto" => $selectProyecto[0]->id,
                                                "tarea" => $selectTarea[0]->id,
                                                "personal" => $tokenEquForProy[0]->id
                                            )
                                        );
                                    }
                                } else {
                                    $tokenLiderProyecto = DB::select("SELECT pers.id,pers.empleado_token,resp.tipo_pp FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp
                                        JOIN module_proyectos AS proy WHERE pers.id = resp.personal AND (resp.tipo_pp = 'li' OR resp.tipo_pp = 'cr')
                                        AND resp.proyecto = proy.id AND proy.token_proyecto = ?",[$token_proyecto]);
        
                                    foreach ($tokenLiderProyecto as $vCreators) {
                                        if ($token_empleado_inside != "" && $token_empleado_inside == $vCreators->empleado_token) {
                                            $responsable_tarea_token = $vCreators->id;
                                            //$certif_token_pers = true;
                                            //break;
                                            $insertAlertaLider = DB::table('module_proyectos_tarea_responsable')
                                            ->insert(
                                                array(
                                                    "proyecto" => $selectProyecto[0]->id,
                                                    "tarea" => $selectTarea[0]->id,
                                                    "personal" => $responsable_tarea_token
                                                )
                                            );
                                        }
                                    }
        
                                    /*if ($certif_token_pers == false) {
                                        $tokenEquipoProyecto = DB::select("SELECT pers.id,resp.tipo_pp FROM vhum_empleados_catalogo AS pers JOIN module_proyectos_responsable AS resp
                                            JOIN module_proyectos AS proy WHERE pers.empleado_token = ? AND pers.id = resp.personal AND resp.tipo_pp = 'eq'
                                            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
                                            [$token_empleado_inside,$token_proyecto]);
                                        $responsable_tarea_token = $tokenEquipoProyecto[0]->id;
                                    }
        
                                    $insertAlertaLider = DB::table('module_proyectos_responsable')
                                    ->insert(array("proyecto" => $selectProyecto[0]->id,"tarea" => $selectTarea[0]->id,"personal" => $responsable_tarea_token,"tipo_pp" => NULL));*/
                                }
    
                                
                                
                                
                                $titulo_alerta = "Tarea con folio ".$folio_tar." ha sido duplicada con el folio ".$folio_new_tar;
                                $selectTarea = DB::table('module_proyectos_tareas')->where(['token_tarea' => $token_tarea])->get();
                                $personalToken = DB::select("SELECT tipo_pp FROM module_proyectos_responsable AS resp WHERE personal = ?",[$selectEmp[0]->userr]);
                                
                                if ($personalToken[0]->tipo_pp == 'cr') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }   
                                if ($personalToken[0]->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } 
                                if ($personalToken[0]->tipo_pp == 'eq') {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } 
                            
                                $dataMensaje = array(
                                    'message' => $titulo_alerta,
                                    "code" => 200,
                                    'status' => 'success'
                                );
        
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Esta tarea no fue actualizada debido a errores internos, intente nuevamente'
                                );
                            }
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en proyecto seleccionado, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en tarea seleccionada, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($nameTarea) || empty($nameTarea) || !preg_match($patron,$nameTarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de nombre de tarea, verifique su información'
                            );
                        }
                        if (!isset($descripTarea) || empty($descripTarea) || !preg_match($patron,$descripTarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en registro de descripción para tarea, verifique su información'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function agregarRespTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'token_empleado_inside' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $token_empleado_inside = $parametrosArray['token_empleado_inside'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea) &&
                        isset($token_empleado_inside) && !empty($token_empleado_inside)) {
                            
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                        $selectTarea = DB::select("SELECT id,folio_tarea,post_folio_tar FROM module_proyectos_tareas WHERE token_tarea = ?",[$token_tarea]);
                            
                        if ($selectTarea[0]->post_folio_tar == NULL) {
                            $folioTarea = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea);
                        } else {
                            $folioTarea = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea).'-'.$selectTarea[0]->post_folio_tar;
                        }
                        
                        $valida_eq_trabajo = false;
                        $txt_alerta = "";
                        $txt_alerta_sweet = "";
                        
                        $eqList = DB::select("SELECT pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone FROM vhum_empleados_catalogo AS pers
                            JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_tarea_responsable AS resp JOIN module_proyectos_tareas AS tar 
                            JOIN module_proyectos AS proy WHERE pers.empleado_token = ? AND pers.id = tel_pers_resp.personal AND pers.id = resp.personal 
                            AND resp.proyecto = proy.id AND proy.token_proyecto = ? AND resp.tarea = tar.id AND tar.token_tarea = ?",
                            [$token_empleado_inside,$token_proyecto,$token_tarea]);
                        
                        //echo count($eqList); 
                        $tknEquipo = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$token_empleado_inside]);
                        if (count($eqList) == 0) {
                            $insertEquipo = DB::table('module_proyectos_tarea_responsable')
                                ->insert(array("proyecto" => $selectProyecto[0]->id,"tarea" => $selectTarea[0]->id,"personal" => $tknEquipo[0]->id));
                                
                            if ($insertEquipo) {
                                $valida_eq_trabajo = true;
                                $txt_alerta = "has sido registrado como responsable de la tarea con folio ".$folioTarea;
                                $JwtAuth->insertNotifEqPersonal("Actualización de proyecto",$token_empleado_inside,$token_proyecto,$txt_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $txt_alerta_sweet = "El personal seleccionado ha sido agregado como responsable de la tarea con folio ".$folioTarea;
                                
                                $dataMensaje = array(
                                    'message' => $txt_alerta_sweet,
                                    "code" => 200,
                                    'status' => 'success',
                                    'selected' => $valida_eq_trabajo,
                                );
                            } else {
                                $dataMensaje = array(
                                    'message' => "personal no registrado",
                                    "code" => 200,
                                    "status" => "error",
                                ); 
                            }
                        } else {
                            $dataMensaje = array(
                                'message' => 'el personal seleccionado ya se encuentra registrado en el equipo de trabajo de este proyecto',
                                "code" => 200,
                                "status" => "error",
                            );
                        }    
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }  
    
        public function eliminarRespTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'token_empleado_inside' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto".$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $token_empleado_inside = $parametrosArray['token_empleado_inside'];
                    
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea) &&
                        isset($token_empleado_inside) && !empty($token_empleado_inside)) {
                            
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                        $selectTarea = DB::select("SELECT id,folio_tarea,post_folio_tar FROM module_proyectos_tareas WHERE token_tarea = ?",[$token_tarea]);
                            
                        if ($selectTarea[0]->post_folio_tar == NULL) {
                            $folioTarea = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea);
                        } else {
                            $folioTarea = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea).'-'.$selectTarea[0]->post_folio_tar;
                        }
                        
                        $valida_eq_trabajo = false;
                        $txt_alerta = "";
                        $txt_alerta_sweet = "";
                        
                        $eqList = DB::select("SELECT pers.id FROM vhum_empleados_catalogo AS pers
                            JOIN module_proyectos_tarea_responsable AS resp JOIN module_proyectos_tareas AS tar 
                            JOIN module_proyectos AS proy WHERE pers.empleado_token = ? AND pers.id = resp.personal 
                            AND resp.proyecto = proy.id AND proy.token_proyecto = ? AND resp.tarea = tar.id AND tar.token_tarea = ?",
                            [$token_empleado_inside,$token_proyecto,$token_tarea]);
                        
                        //echo count($eqList); 
                        $tknEquipo = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE empleado_token = ?",[$token_empleado_inside]);
                        if (count($eqList) != 0) {
                            $txt_alerta = "has sido eliminado de la tarea con folio ".$folioTarea;
                            $JwtAuth->insertNotifEqPersonal("Actualización de proyecto",$token_empleado_inside,$token_proyecto,$txt_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            $deleteTeam = DB::table('module_proyectos_tarea_responsable AS resp')
    					    ->join("module_proyectos AS tar","resp.proyecto","=","tar.id")
    					    ->join("module_proyectos_tareas AS subtar","resp.tarea","=","subtar.id")
    					    ->join("vhum_empleados_catalogo AS people","resp.personal","=","people.id")
    					    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    					    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    					    ->where([
    					    	'tar.token_proyecto' => $token_proyecto,
    					    	'subtar.token_tarea' => $token_tarea,
    					    	'people.empleado_token' => $token_empleado_inside,
    					    	'emp.empresa_token' => $usuario->empresa_token,
    					    	'users.usuario_token' => $usuario->user_token,
    					    ])->limit(1)->delete();
    					    
    					    if ($deleteTeam) {
    					        $valida_eq_trabajo = false;
    					        $txt_alerta_sweet = "El personal seleccionado ha sido eliminado de la tarea con folio ".$folioTarea;
                                
                                $dataMensaje = array(
                                    'message' => $txt_alerta_sweet,
                                    "code" => 200,
                                    'status' => 'success',
                                    'selected' => $valida_eq_trabajo,
                                );
    					        
    					    } else {
    					        $dataMensaje = array(
                                    'message' => 'personal no eliminado',
                                    "code" => 200,
                                    "status" => "error",
                                );
    					    }
    					    
                        } else {
                            $dataMensaje = array(
                                'message' => 'el personal seleccionado no se encuentra registrado responsable de esta tarea',
                                "code" => 200,
                                "status" => "error",
                            );
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                        if (!isset($token_empleado_inside) || empty($token_empleado_inside)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Error en seleccion de personal responsable de proyecto, verifique su información e intente nuevamente'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        } 
        
        public function recalendarizarTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'fecha_recalendariza' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    $patronFecha = '/^[0-9-]*$/';
    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $fecha_recalendariza = $parametrosArray['fecha_recalendariza'];
    
                    if (isset($token_proyecto) && !empty($token_proyecto) && 
                        isset($fecha_recalendariza) && !empty($fecha_recalendariza) && preg_match($patronFecha,$fecha_recalendariza)) {
                        $fecha_sistema = time(); 
                        
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                            AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
    
                        $selectTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar 
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id 
                            AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $tarea_id = $selectTarea[0]->id;
                        
                        if ($selectTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea).'-'.$selectTarea[0]->post_folio_tar;
                        }
    
                        $token_calendarizacion = $JwtAuth->encriptarToken($fecha_sistema,$usuario->user_token,$usuario->empresa_token,$token_tarea,$fecha_recalendariza);
                        
                        $insertInformeAct = DB::table('module_proyectos_calendarizacion')
                        ->insert(
                            array(
                                "token_calendarizacion" => $token_calendarizacion,
                                "proyecto" => $selectProyecto[0]->id,
                                "tarea" => $tarea_id,
                                "fecha_sistema" => $fecha_sistema,	
                                "personal_opera" => $selectEmp[0]->userr,	
                                "fecha_compromiso_nueva" => $JwtAuth->convierteFechaEpoc($fecha_recalendariza),
                            )
                        );
    
                        if ($insertInformeAct) {
    
                            $selectEvent = DB::select("SELECT id FROM module_proyectos_eventos WHERE proyecto = ? 
                                AND evt_tarea = ?",[$selectProyecto[0]->id,$tarea_id]);
                                
                            if (count($selectEvent) == 0) {
                                JURI_EventosController::registraEventoProyectos(
                                    $selectProyecto[0]->id,
                                    $tarea_id,
                                    1,
                                    'fecha de finalización',
                                    $fecha_sistema,
                                    $selectEmp[0]->id
                                );
                            } else {
                                JURI_EventosController::actualizaEventoTarea(
                                    $selectProyecto[0]->id,
                                    $tarea_id,
                                    1,
                                    'fecha de finalización',
                                    $fecha_sistema,
                                    $selectEmp[0]->id
                                );
                            }
                            
                            $selectSubTarea = DB::select("SELECT subtar.id FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? 
                                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                            
                            $titulo_alerta = "Ha recalendarizado tarea con folio ".$folio_tar;
                            if ($selectEmp[0]->userr != $selectProyecto[0]->creador_tarea) {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } else {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }
                            
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Tarea recalendarizada'
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Tarea no recalendarizada, intente nuevamente'
                            );
                        }
                    } else {
                        if (!isset($token_proyecto) || empty($token_proyecto)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de proyecto para recalendarizar'
                            );
                        }
                        if (!isset($fecha_recalendariza) || empty($fecha_recalendariza) || !preg_match($patronFecha,$fecha_recalendariza)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe fecha para recalendarizar'
                            );                   
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
        public function terminarTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
    
                    if (isset($token_proyecto) && !empty($token_proyecto) &&
                        isset($token_tarea) && !empty($token_tarea)) {
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,tar.proyecto_name,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto 
                            AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                            AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $selectTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? 
                            AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea).'-'.$selectTarea[0]->post_folio_tar;
                        }
                        
                        $insertInformeAct = DB::table('module_proyectos_tareas AS subtar')
    					->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
    					->join("main_empresas AS emp","tar.empresa","=","emp.id")
    					->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    					->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    					->where([
    						'subtar.token_tarea' => $token_tarea,
    						'tar.token_proyecto' => $token_proyecto,
    						'emp.empresa_token' => $usuario->empresa_token,
    						'users.usuario_token' => $usuario->user_token,
    					])
    					->limit(1)->update(
    						array(
    							'subtar.realizacion' => TRUE,
    						)
    					);
    					
                        if ($insertInformeAct) {
                            $titulo_alerta = "Tarea con folio ".$folio_tar." ha sido terminada";
                             
                            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$usuario->empresa_token,$usuario->user_token]);
                            //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                            
                            $personalToken = DB::select("SELECT tipo_pp FROM module_proyectos_responsable AS resp WHERE personal = ?",[$selectEmp[0]->userr]);
                            
                            if ($personalToken[0]->tipo_pp == 'cr') {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }   
                            if ($personalToken[0]->tipo_pp == 'li') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
                            if ($personalToken[0]->tipo_pp == 'eq') {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } 
    
                            $subTareaList = DB::table("module_proyectos_tareas AS subtar")
                            ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                            ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $token_proyecto,])->get();
                            
    					    $subTareaListTerminadas = DB::table("module_proyectos_tareas AS subtar")
                            ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                            ->where(["subtar.status" => TRUE,"subtar.realizacion" => TRUE,"tar.token_proyecto" => $token_proyecto,])->get();
                            
                            if (count($subTareaListTerminadas) == count($subTareaList)) {
                                if ($selectProyecto[0]->post_folio == NULL) {
                                    $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                                } else {
                                    $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                                }
                                $nombre_proy = $JwtAuth->desencriptar($selectProyecto[0]->proyecto_name);
                                $titulo_alerta = "Proyecto con folio ".$folio_proy." ha sido finalizado";
                    
                                if ($personalToken[0]->tipo_pp == 'cr') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqAll("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }   
                                if ($personalToken[0]->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqAll("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } 
                                if ($personalToken[0]->tipo_pp == 'eq') {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqAll("Actualización de proyecto",$proyDel->token_proyecto,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                            }
                            
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Tarea terminada'
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Tarea no terminada, intente nuevamente'
                            );
                        }
                            
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para recalendarizar'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
        public function eliminarTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Usuario incorrecto",
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea)) {
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? 
                            AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                            AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar 
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id 
                            AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectSubTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectSubTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectSubTarea[0]->folio_tarea).'-'.$selectSubTarea[0]->post_folio_tar;
                        }
                        
                        $deleteTarea = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'subtar.token_tarea' => $parametrosArray['token_tarea'],
                            'tar.token_proyecto' => $parametrosArray['token_proyecto'],
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->limit(1)->update(
    						array(
    							'subtar.status' => FALSE,
    							'subtar.fecha_delete' => time(),
    						)
    					);
    					
    					if ($deleteTarea) {
    					    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                            
                            $titulo_alerta = "Ha eliminado tarea con folio ".$folio_tar;
                            if ($selectEmp[0]->userr != $selectProyecto[0]->creador_tarea) {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } else {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }
                            
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
    
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Tarea eliminada'
                            );
    					} else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Tarea no eliminada'
                            );
    					}
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Proyecto o tarea no validos'
                        );   
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function terminarParticipacionTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                    
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
    
                    if (isset($token_proyecto) && !empty($token_proyecto) &&
                        isset($token_tarea) && !empty($token_tarea)) {
                        $selectProyecto = DB::select("SELECT tar.id,tar.folio,tar.post_folio,tar.proyecto_name,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto 
                            AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $selectTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? 
                            AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectTarea[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectTarea[0]->folio_tarea).'-'.$selectTarea[0]->post_folio_tar;
                        }
                        
                        $queryParticip = DB::table("module_proyectos_tarea_responsable AS resp_tar")
    					->join("module_proyectos_tareas AS tar","resp_tar.tarea","=","tar.id")
    					->join("module_proyectos AS proy","resp_tar.proyecto","=","proy.id")
    					->join("main_empresas AS emp","proy.empresa","=","emp.id")
    					->join("vhum_empleados_catalogo AS pers","resp_tar.personal","=","pers.id")
    					->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
    					->where([
    						"tar.token_tarea" => $token_tarea,
    						"proy.token_proyecto" => $token_proyecto,
    						"emp.empresa_token" => $usuario->empresa_token,
    						"users.usuario_token" => $usuario->user_token,
    					])->get();
    					
    					if ($queryParticip[0]->participacion == TRUE) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "Participación en la tarea con folio ".$folio_tar." ha sido avisada"
                            );
    					} else {
                            $insertInformeAct = DB::table("module_proyectos_tarea_responsable AS resp_tar")
        					->join("module_proyectos_tareas AS tar","resp_tar.tarea","=","tar.id")
        					->join("module_proyectos AS proy","resp_tar.proyecto","=","proy.id")
        					->join("main_empresas AS emp","proy.empresa","=","emp.id")
        					->join("vhum_empleados_catalogo AS pers","resp_tar.personal","=","pers.id")
        					->join("teci_usuarios_catalogo AS users","pers.id","=","users.empleado")
        					->where([
        						"tar.token_tarea" => $token_tarea,
        						"proy.token_proyecto" => $token_proyecto,
        						"emp.empresa_token" => $usuario->empresa_token,
        						"users.usuario_token" => $usuario->user_token,
        					])->limit(1)->update(array("resp_tar.participacion" => TRUE));
        					
                            if ($insertInformeAct) {
                                $titulo_alerta = "Terminó su participación en la tarea con folio ".$folio_tar;
                                 
                                $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                                //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                                
                                $personalToken = DB::select("SELECT tipo_pp FROM module_proyectos_responsable AS resp WHERE personal = ?",[$selectEmp[0]->userr]);
                                
                                if ($personalToken[0]->tipo_pp == 'cr') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }   
                                if ($personalToken[0]->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } 
                                if ($personalToken[0]->tipo_pp == 'eq') {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                                
                                $dataMensaje = array(
                                    "status" => "success",
                                    "code" => 200,
                                    "message" => "Participación en la tarea con folio ".$folio_tar." ha sido terminada"
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    "message" => "Participación en tarea no terminada, intente nuevamente"
                                );
                            }
    					}
                            
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            "message" => "No existe referencia de proyecto o de tarea para terminar participación"
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
//informes
    //registro
        public function registrarInformeTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayTareas = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    //'empresa_token' => 'required|string',
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'txt_informe' => 'required|string',
                    'observ_informe' => 'required|string',
                    'horas_activas' => 'required|string',
                    'informe_evidencias_nombres' => 'array',
                ]);
             
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $txt_informe = $parametrosArray['txt_informe'];
                    $observ_informe = $parametrosArray['observ_informe'];
                    $horas_activas = $parametrosArray['horas_activas'];
                    $informe_evidencias_nombres = $parametrosArray['informe_evidencias_nombres'];
                    //echo $JwtAuth->desencriptar("eExHK0s0TWlMcWJnMG92dnJNenR6QT09OjoxMjM0NTY3ODEyMzQ1Njc4");exit; 
                
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea) &&
                        isset($txt_informe) && !empty($txt_informe) && preg_match($JwtAuth->filtroAlfaNumerico(),$txt_informe) &&
                        isset($observ_informe) && !empty($observ_informe) && preg_match($JwtAuth->filtroAlfaNumerico(),$observ_informe) && 
                        isset($horas_activas) && !empty($horas_activas) && preg_match($JwtAuth->filtroNumerico(),$horas_activas)) {
                        
                        $fecha_sistema = time(); 
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers 
                            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.empleado = pers.id AND users.usuario_token = ?",
                            [$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
        
                        $selectTarea = DB::select("SELECT tar.id,tar.folio,tar.post_folio,tar.upload_evidencias,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto 
                            AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                        $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                        $token_informe = $JwtAuth->encriptarToken($fecha_sistema,$usuario->user_token,$usuario->empresa_token,$token_tarea,$token_tarea);
                        
                        $folioSistema = DB::select("SELECT COUNT(inftar.id)+1 AS folio FROM module_proyectos_informes AS inftar 
                            JOIN module_proyectos_tareas AS subtar WHERE inftar.tarea = subtar.id AND subtar.token_tarea = ?",[$token_tarea]);
                            
                        if ($folioSistema[0]->folio > 1) {
                            if ($folioSistema[0]->folio == 1000000000) {
                                $post_folio_db = DB::select("SELECT post_folio_informe FROM module_proyectos_informes 
                                    WHERE id = (SELECT Max(inftar.id) FROM module_proyectos_informes AS inftar 
                                    JOIN module_proyectos_tareas AS subtar
                                    WHERE inftar.tarea = subtar.id AND subtar.token_tarea = ?)",[$token_tarea]);
            
                                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_informe);
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
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($folio_nuevo);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio;
                        }
                         
                        $txt_informe = str_replace(".diagon.","/",$txt_informe);
                        $txt_informe = str_replace(".porcent.","%",$txt_informe);
                        $txt_informe = str_replace(".ampersand.","&",$txt_informe);
                        $txt_informe = str_replace(".pessos.","$",$txt_informe);
                         
                        $evidencia_nombre = "";
                        $validate_evidence_permisos = false;
                        if($selectTarea[0]->upload_evidencias == TRUE){
                            if(!empty($_FILES['imgEvidencias']) && count($informe_evidencias_nombres) != 0){
                                $validate_evidence_permisos = true;
                            } else {
                                $validate_evidence_permisos = false;
                            }
                        } else {
                            $validate_evidence_permisos = true;
                        }
                        
                        if($validate_evidence_permisos == true){
                            $insertInformeAct = DB::table('module_proyectos_informes')->insert(
                                array(
                                    "token_informe" => $token_informe,
                                    "folio_informe" => $folio_nuevo,
                                    "post_folio_informe" => $post_folio,
                                    "proyecto" => $selectTarea[0]->id,
                                    "tarea" => $selectSubTarea[0]->id,
                                    "fecha_realizacion" => $fecha_sistema,
                                    "informe" => $JwtAuth->encriptar($txt_informe),
                                    "observaciones" => $JwtAuth->encriptar($observ_informe),
                                    "personal_realiza" => $selectEmp[0]->userr,
                                    "horas_activas" => $horas_activas,
                                    "revisado" => FALSE,	
                                    "aprobado" => FALSE,
                                    "status_inf" => TRUE,	
                                    "fecha_delete_inf" => NULL
                                ) 
                            );
                            //return response()->json(["status" => "error","code" => 200,'message' => 'monitor 7']);
                            if ($insertInformeAct) {
                                //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                                $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? 
                                    AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                //return response()->json(["status" => "error","code" => 200,'message' => 'true2']);
                                
                                if ($selectTarea[0]->post_folio == NULL) {
                                    $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectTarea[0]->folio);
                                } else {
                                    $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectTarea[0]->folio).'-'.$selectTarea[0]->post_folio;
                                }
                                //return response()->json(["status" => "error","code" => 200,'message' => 'true3']);
                                if ($selectSubTarea[0]->post_folio_tar == NULL) {
                                    $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectSubTarea[0]->folio_tarea);
                                } else {
                                    $folio_tar = 'TAR-'.$JwtAuth->generarFolio($selectSubTarea[0]->folio_tarea).'-'.$selectSubTarea[0]->post_folio_tar;
                                }
                                //return response()->json(["status" => "error","code" => 200,'message' => 'true4']);
                                $filepath = $selectEmp[0]->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/";
                                
                                if (!file_exists(storage_path("/root/".$filepath))){
                                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                }
                                //return response()->json(["status" => "error","code" => 200,'message' => 'true5']);
                                if(!empty($_FILES['imgEvidencias'])){
                                    $evidencias = $_FILES["imgEvidencias"];
                                    //return response()->json(["status" => "error","code" => 200,'message' => 'true5.1']);
                                    $string_name_evid = json_encode($_FILES["imgEvidencias"]["name"]);
                                    //return response()->json(["status" => "error","code" => 200,'message' => 'true5.2']);
                                    if (count(json_decode($string_name_evid)) != 0) {
                                        //return response()->json(["status" => "error","code" => 200,'message' => 'true5.3']);
                                        $evidencia_nombre = json_decode($string_name_evid);
                                        //return response()->json(["status" => "error","code" => 200,'message' => 'true5.4']);
                                        for ($i=0; $i < count($evidencia_nombre); $i++){
                                            //return response()->json(["status" => "error","code" => 200,'message' => 'true5.5']);
                                            $nombre = $evidencia_nombre[$i];
                                            $temporal = $evidencias["tmp_name"][$i];
                                            //return response()->json(["status" => "error","code" => 200,'message' => 'true5.6 '.$temporal]);
                                            Storage::putFileAs("/public/root/".$filepath,$temporal,$nombre);
                                        }
                                    }
                                    
                                    if (count($informe_evidencias_nombres) > 0) {
                                        for ($i=0; $i < count($informe_evidencias_nombres); $i++){
                                            $element_tipo = $informe_evidencias_nombres[$i]["tipo"];
                                            $element_nombre = $informe_evidencias_nombres[$i]["nameFile"];
                                            $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PR-EVID%'");
                                            $tkn_evidencia = $JwtAuth->encriptarToken($token_proyecto,$token_tarea,$usuario->user_token,$usuario->empresa_token,$element_nombre);
                                            $insertEvidenceInf = DB::table('sos_documentos')->insert(
                                                array(
                                                    "token_documento" => $tkn_evidencia,
                                                    "fecha_carga" => time(),
                                                    "modulo" => "proyectos",
                                                    "folio_modulo" => "PR-EVID".$select_folio_doc[0]->folio,
                                                    "tipo_documento" => "file",
                                                    "nombre_documento" => $JwtAuth->encriptar($element_nombre),
                                                    "extension_documento" => $element_tipo,
                                                    "proyecto" => $selectTarea[0]->id,
                                                    "tarea" => $selectSubTarea[0]->id,
                                                    "informe" => $selectInforme[0]->id,
                                                    "status_documento" => TRUE,	
                                                    "fecha_delete_documento" => NULL,
                                                ) 
                                            );
                                        }
                                    }
                                }

                                $titulo_alerta = "Ha registrado un nuevo informe con folio ".$folio_inf;
                            
                                $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado 
                                    FROM module_proyectos AS tar JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp 
                                    JOIN sos_personas_telefonos AS tel_pers_resp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                                    WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') AND resp.personal = pers_resp.id 
                                    AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                    AND empuser.usuario = pers.id AND users.usuario_token = ? AND users.empleado = pers.id",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                          
                                foreach ($selectTelefono as $valPhone){
                                    if ($valPhone->tipo_pp == 'li') {
                                        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    } else {
                                        $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    }
                                }
                                    
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                          
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Informe registrado con el folio '.$folio_inf
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Informe no registrado, intente nuevamente'
                                );    
                            }
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'upload_evid'
                            );
                        }
                        
                    } else {
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de proyecto para registrar informe'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de tarea para registrar informe'
                            );
                        }
                        if (!isset($txt_informe) || empty($txt_informe) || !preg_match($patron,$txt_informe)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe informe para registrar'
                            );                   
                        }
                        if (!isset($observ_informe) || empty($observ_informe) || !preg_match($patron,$observ_informe)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe observaciones de informe para registrar'
                            );                   
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
    //listas    
        public function lastInformeTareaCreated(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $informeArray = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    //'empresa_token' => 'required|string',
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                ]);
             
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea)) {
                        $informeTarea = DB::table("module_proyectos_informes AS inform")
                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                        ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                        ->where([
                            'inform.status_inf' => TRUE,
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                        ])->orderBy('inform.id','DESC')->limit(1)->get();
                                        
                        foreach ($informeTarea as $valInform){
                            if ($valInform->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                            }
                            
                            if ($valInform->revisado == TRUE) {
                                if ($valInform->aprobado == TRUE) {
                                    $status_aprob = "success";
                                    $rev_aprob = "revisado y aprobado";
                                } else {
                                    $status_aprob = "error";
                                    $rev_aprob = "revisado sin aprobar";
                                }   
                            } else {
                                $rev_aprob = "sin revisar";
                                $status_aprob = "empty";
                            }
                                
                            $personal_realiza = $JwtAuth->desencriptar($valInform->paterno)." ".
                                $JwtAuth->desencriptar($valInform->materno)." ".
                                $JwtAuth->desencriptar($valInform->nombre);
                                
                            $rowlist = array(
                                "token_informe" => $valInform->token_informe,
                                "token_proyecto" => $token_proyecto,
                                "token_tarea" => $token_tarea,
                                "folio_inf" => $folio_inf,
                                "fecha_realizacion" => date('d-m-Y H:i:s',$valInform->fecha_realizacion),
                                "informe" => $JwtAuth->desencriptar($valInform->informe),
                                "personal_realiza" => ucwords($personal_realiza),
                                "rev_aprob" => $rev_aprob,
                                "status_aprob" => $status_aprob,
                            );
                            $informeArray[] = $rowlist;
                        } 
                        
                        $dataMensaje = array(
                            "status" => "success",
                            "code" => 200,
                            "informe" => $informeArray
                        );
                        
                    } else {
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de proyecto para registrar informe'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de tarea para registrar informe'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
        public function recoverInformeTarea(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $informeArray = array();
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    //'empresa_token' => 'required|string',
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'token_informe' => 'required|string',
                ]);
             
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $token_proyecto = $parametrosArray['token_proyecto'];
                    $token_tarea = $parametrosArray['token_tarea'];
                    $token_informe = $parametrosArray['token_informe'];
                
                    if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && !empty($token_tarea) && 
                        isset($token_informe) && !empty($token_informe)) {
                        $informeTarea = DB::table("module_proyectos_informes AS inform")
                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                        ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                        ->where([
                            'inform.status_inf' => TRUE,
                            'inform.token_informe' => $token_informe,
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                        ])->orderBy('subtar.id','DESC')->get();
                                        
                        foreach ($informeTarea as $valInform){
                            if ($valInform->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                            }
                            
                            if ($valInform->revisado == TRUE) {
                                if ($valInform->aprobado == TRUE) {
                                    $status_aprob = "success";
                                    $rev_aprob = "revisado y aprobado";
                                } else {
                                    $status_aprob = "error";
                                    $rev_aprob = "revisado sin aprobar";
                                }   
                            } else {
                                $rev_aprob = "sin revisar";
                                $status_aprob = "empty";
                            }
                                
                            $personal_realiza = $JwtAuth->desencriptar($valInform->paterno)." ".
                                $JwtAuth->desencriptar($valInform->materno)." ".
                                $JwtAuth->desencriptar($valInform->nombre);
                                
                            $rowlist = array(
                                "token_informe" => $valInform->token_informe,
                                "token_proyecto" => $token_proyecto,
                                "token_tarea" => $token_tarea,
                                "folio_inf" => $folio_inf,
                                "fecha_realizacion" => date('d-m-Y H:i:s',$valInform->fecha_realizacion),
                                "informe" => $JwtAuth->desencriptar($valInform->informe),
                                "personal_realiza" => ucwords($personal_realiza),
                                "rev_aprob" => $rev_aprob,
                                "status_aprob" => $status_aprob,
                            );
                            $informeArray[] = $rowlist;
                        } 
                        
                        $dataMensaje = array(
                            "status" => "success",
                            "code" => 200,
                            "informe" => $informeArray
                        );
                        
                    } else {
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de proyecto para registrar informe'
                            );
                        }
                        if (!isset($token_tarea) || empty($token_tarea)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de tarea para registrar informe'
                            );
                        }
                        if (!isset($token_informe) || empty($token_informe)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'No existe referencia de informe'
                            );
                        }
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
    
        public function detalleInforme(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $informe_row = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'token_informe' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $actividades_aprob = 0;
                    
                    $proyecto = $parametrosArray['token_proyecto'];
                    $tarea = $parametrosArray['token_tarea'];
                    $informe = $parametrosArray['token_informe'];
                    
                    $informeSubtarea = DB::table("module_proyectos_informes AS inf")
                    ->join("vhum_empleados_catalogo AS pers","inf.personal_realiza","=","pers.id")
                    ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                    ->join("module_proyectos_tareas AS subtar","inf.tarea","=","subtar.id")
                    ->join("module_proyectos AS tar","inf.proyecto","=","tar.id")
                    ->join("main_empresas AS emp","tar.empresa","=","emp.id")
                    ->where([
                        'inf.token_informe' => $informe,
                        'subtar.token_tarea' => $tarea,
                        'tar.token_proyecto' => $proyecto,
                        'emp.empresa_token' => $usuario->empresa_token
                    ])->get();
                            
                    if (count($informeSubtarea) == 1) {
                        foreach ($informeSubtarea as $valInform){
                            if ($valInform->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valInform->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valInform->folio).'-'.$valInform->post_folio;
                            }
                            
                            if ($valInform->post_folio_tar == NULL) {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valInform->folio_tarea);
                            } else {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valInform->folio_tarea).'-'.$valInform->post_folio_tar;
                            }
                            
                            if ($valInform->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valInform->folio_informe).'-'.$valInform->post_folio_informe;
                            }
                            
                            $aprobaciones = array();
                            $select_aprobar = DB::table("module_proyectos_informes_aprobar AS aprob")
                            ->join("module_proyectos_informes AS inf","aprob.informe","=","inf.id")
                            ->join("module_proyectos_tareas AS subtar","aprob.tarea","=","subtar.id")
                            ->join("module_proyectos AS tar","aprob.proyecto","=","tar.id")
                            ->where([
                                'inf.token_informe' => $valInform->token_informe,
                                'subtar.token_tarea' => $tarea,
                                'tar.token_proyecto' => $proyecto,
                            ])->orderBy('aprob.id','DESC')->get();
                            
                            foreach ($select_aprobar as $valAprov) {
                                if ($valAprov->aprobado_list == TRUE) {
                                    $aprobado_list = "yes_auth";
                                } else {
                                    $aprobado_list = "not_auth";
                                }
                                
                                $row = array(
                                    "fecha_aprobar" => date('d-m-Y H:i:s',$valAprov->fecha_aprobar),
                                    "aprobado_list" => $aprobado_list,
                                    "observaciones" => $JwtAuth->desencriptar($valAprov->comentarios_aprob),
                                );
                                $aprobaciones[] = $row;
                            }
                            
                            $comentarios_aprob = null;
                            
                            if ($valInform->revisado == TRUE) {
                                $detectApprobes = DB::select("SELECT approv.aprobado_list FROM module_proyectos_informes_aprobar AS approv
                                    JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                    JOIN module_proyectos_informes AS inf 
                                    WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                    AND approv.tarea = tar.id AND tar.token_tarea = ?
                                    AND approv.informe = inf.id AND inf.token_informe = ?",
                                    [$proyecto,$tarea,$valInform->token_informe]);
        
                                if (count($detectApprobes) == 0) {
                                    $status_aprob = "process";
                                    $rev_aprob = "en revision";   
                                } else {
                                    $lastApprobes = DB::select("SELECT aprobado_list,comentarios_aprob FROM module_proyectos_informes_aprobar 
                                        WHERE id = (SELECT MAX(approv.id) FROM module_proyectos_informes_aprobar AS approv
                                    JOIN module_proyectos AS proy JOIN module_proyectos_tareas AS tar
                                    JOIN module_proyectos_informes AS inf 
                                    WHERE approv.proyecto = proy.id AND proy.token_proyecto = ?
                                    AND approv.tarea = tar.id AND tar.token_tarea = ?
                                    AND approv.informe = inf.id AND inf.token_informe = ?)",
                                    [$proyecto,$tarea,$valInform->token_informe]);
                                    
                                    if (end($lastApprobes)->aprobado_list == TRUE) {
                                        $status_aprob = "success";
                                        $rev_aprob = "revisado y aprobado";
                                        ++$actividades_aprob;
                                    } else {
                                        $status_aprob = "error";
                                        $rev_aprob = "revisado sin aprobar"; 
                                    }
                                    $comentarios_aprob = $JwtAuth->desencriptar(end($lastApprobes)->comentarios_aprob);
                                }
                            } else {
                                $rev_aprob = "sin revisar";
                                $status_aprob = "empty";
                            }
                                
                            $personal_realiza = $JwtAuth->desencriptarNombres($valInform->paterno,$valInform->materno,$valInform->nombre);
                                
                            $lista_evidencias_true = array();
                            $selectIdEvid = DB::table("sos_documentos AS docs")
                            ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                            ->where([
                                "status_documento" => TRUE,
                                "proy.token_proyecto" => $proyecto,
                                "tar.token_tarea" => $tarea,
                                "inf.token_informe" => $valInform->token_informe,
                            ])->get();
                            if (count($selectIdEvid) > 0) {
                                foreach ($selectIdEvid as $vDoc){
                                    $rowDocs = array(
                                        "token_documento" => $vDoc->token_documento,
                                        "ext_doc" => $vDoc->extension_documento,
                                        "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                        "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                    );
                                    $lista_evidencias_true[] = $rowDocs;
                                }
                            }
                            
                            $lista_evidencias_false = array();
                            $selectEvidDeleted = DB::table("sos_documentos AS docs")
                            ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                            ->where([
                                "status_documento" => FALSE,
                                "proy.token_proyecto" => $proyecto,
                                "tar.token_tarea" => $tarea,
                                "inf.token_informe" => $valInform->token_informe,
                            ])->get();  
                            if (count($selectEvidDeleted) > 0) {
                                foreach ($selectEvidDeleted as $vDoc){
                                    $rowDocs = array(
                                        "token_documento" => $vDoc->token_documento,
                                        "ext_doc" => $vDoc->extension_documento,
                                        "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                        "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                                    );
                                    $lista_evidencias_false[] = $rowDocs;
                                }
                            }
                            
                            $dataMensaje = array(
                                "status" => 'success',
                                "code" => 200,
                                "token_informe" => $valInform->token_informe,
                                "token_proyecto" => $proyecto,
                                "token_tarea" => $tarea,
                                "folio_inf" => $folio_inf,
                                "fecha_realizacion" => date('d-m-Y H:i:s',$valInform->fecha_realizacion),
                                "informe" => $JwtAuth->desencriptar($valInform->informe),
                                "informe_dos" => "",
                                "observaciones_revision" => "",
                                "observaciones" => $JwtAuth->desencriptar($valInform->observaciones),
                                "observaciones_dos" => "",
                                "personal_realiza" => $JwtAuth->desencriptar($valInform->paterno)." ".$JwtAuth->desencriptar($valInform->materno)." ".$JwtAuth->desencriptar($valInform->nombre),
                                "aprobaciones" => $aprobaciones,
                                "rev_aprob" => $rev_aprob,
                                "status_aprob" => $status_aprob,
                                "comentarios_aprob" => $comentarios_aprob,
                                "evidencias_true" => $lista_evidencias_true,
                                "evidencias_false" => $lista_evidencias_false,
                            );
                        }
                    } else {
                        $dataMensaje = array("status" => "error","code" => 200,"message" => "informe no registrado");
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function informeListaEvidencias(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $lista_evidencias_true = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_proyecto' => 'required|string',
                    'token_tarea' => 'required|string',
                    'token_informe' => 'required|string',
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        'message' => 'Usuario incorrecto'.$validate->errors(),
                        "errors" => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $proyecto = $parametrosArray['token_proyecto'];
                    $tarea = $parametrosArray['token_tarea'];
                    $informe = $parametrosArray['token_informe'];
                    
                    $selectIdEvid = DB::table("sos_documentos AS docs")
                    ->join("module_proyectos AS proy","docs.proyecto","=","proy.id")
                    ->join("module_proyectos_tareas AS tar","docs.tarea","=","tar.id")
                    ->join("module_proyectos_informes AS inf","docs.informe","=","inf.id")
                    ->where([
                        "status_documento" => TRUE,
                        "proy.token_proyecto" => $proyecto,
                        "tar.token_tarea" => $tarea,
                        "inf.token_informe" => $informe,
                    ])->get();
                    if (count($selectIdEvid) > 0) {
                        foreach ($selectIdEvid as $vDoc){
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($vDoc->folio).($vDoc->post_folio != NULL ? "-".$vDoc->post_folio : "");
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($vDoc->folio_tarea).($vDoc->post_folio_tar != NULL ? '-'.$vDoc->post_folio_tar : '');
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($vDoc->folio_informe).($vDoc->post_folio_informe != NULL ? '-'.$vDoc->post_folio_informe : '');
                            $rowDocs = array(
                                "token_documento" => $vDoc->token_documento,
                                "ext_doc" => $vDoc->extension_documento,
                                "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                                "url" => "https://downloads.sos-mexico.com.mx/proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$vDoc->token_documento,
                            );
                            $lista_evidencias_true[] = $rowDocs;
                        }
                        $dataMensaje = array(
                            "status" => 'success',
                            "code" => 200,
                            "evidencias" => $lista_evidencias_true,
                        );
                    } else {
                        $dataMensaje = array("status" => "error","code" => 200,"message" => "informe no registrado");
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function visorEvidencias($code_evidencia,$nombre_evidencia){
            //echo $nombre_evidencia;
            $JwtAuth = new \JwtAuth();
        
            if (isset($code_evidencia) && !empty($code_evidencia)) {
                
                $selectIdEvid = DB::table("sos_documentos AS evd")
                    ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                    ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                    ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->where([
                        'evd.token_documento' => $code_evidencia,
                        'evd.nombre_documento' => $JwtAuth->encriptar($nombre_evidencia),
                    ])->get();      
                          
                      
                if (count($selectIdEvid) > 0) {
                    foreach ($selectIdEvid as $valEvid){
                        
                        if ($valEvid->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valEvid->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valEvid->folio).'-'.$valEvid->post_folio;
                        }
                        
                        if ($valEvid->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valEvid->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valEvid->folio_tarea).'-'.$valEvid->post_folio_tar;
                        }
                        
                        if ($valEvid->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valEvid->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valEvid->folio_informe).'-'.$valEvid->post_folio_informe;
                        }
                        
                        $filepath = $valEvid->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf;
                        $token_documento = $valEvid->token_documento;
                        $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento);
                        $nombre_documento = Storage::get('public/root/'.$filepath.'/'.$name_evidencia);
                        return response(Storage::disk('root')->get($filepath.'/'.$name_evidencia), 200)
                        ->header('Content-Type', Storage::disk('root')->mimeType($filepath.'/'.$name_evidencia));
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
        public function descargarEvidencias($code_evidencia){
            $JwtAuth = new \JwtAuth();
        
            if (isset($code_evidencia) && !empty($code_evidencia)) {
                
                $selectIdEvid = DB::table("sos_documentos AS evd")
                    ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                    ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                    ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->where([
                        'evd.token_documento' => $code_evidencia,
                    ])->get();      
                          
                      
                if (count($selectIdEvid) > 0) {
                    foreach ($selectIdEvid as $valEvid){
                        
                        if ($valEvid->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valEvid->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valEvid->folio).'-'.$valEvid->post_folio;
                        }
                        
                        if ($valEvid->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valEvid->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valEvid->folio_tarea).'-'.$valEvid->post_folio_tar;
                        }
                        
                        if ($valEvid->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valEvid->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valEvid->folio_informe).'-'.$valEvid->post_folio_informe;
                        }
                        
                        $filepath = $valEvid->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf;
                        $token_documento = $valEvid->token_documento;
                        $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento);
                        $nombre_documento = Storage::get('public/root/'.$filepath.'/'.$name_evidencia);
                        //Storage::setVisibility($nombre_documento, 'public');
                        //$nombre_documento = Storage::url('root/ultimo.docx');
                        //"crudo" => $JwtAuth->encriptaBase64Pdf($nombre_documento),
                        //return response(Storage::disk('root')->get('ultimo.docx'), 200)->header('Content-Type', Storage::disk('root')->mimeType('ultimo.docx'));
                        //return response()->download($nombre_documento);
                        header("Content-type");
                        header("Content-Disposition: inline; filename=ultimo.docx");
                        readfile(Storage::disk('root')->get('ultimo.docx'));
                        
                        $dataMensaje = array(
                            "status" => 'success',
                            "code" => 200,
                            "informe_row" => $nombre_documento,
                        );
                        
                    }
                }
            } else {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "La información que intenta registrar no es valida"
                );
            }
            return response()->json($dataMensaje, $dataMensaje["code"]);
        }
        
    public function revisarInformeTarea(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                
                
                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe)) {
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                    $selectInf = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos_tareas AS tar","inf.tarea","=","tar.id")
                        ->join("module_proyectos AS proy","inf.proyecto","=","proy.id")
                        ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tar.token_tarea' => $token_tarea,
                            'proy.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->get();
                    
                    foreach ($selectInf as $valevd) {
                        $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND proy.empresa = emp.id AND 
                            emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                        }

                        $queryInformeRevision = DB::table("module_proyectos_informes AS inform")
                        ->join("module_proyectos AS tarprog","inform.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","subtar.id")
                        ->where([
                            'inform.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])->get();
                        
                        if ($queryInformeRevision[0]->revisado == FALSE) {
                            $updateInformeAct = DB::table("module_proyectos_informes AS inform")
                            ->join("module_proyectos AS tarprog","inform.proyecto","tarprog.id")
                            ->join("module_proyectos_tareas AS subtar","inform.tarea","subtar.id")
                            ->where([
                                'inform.token_informe' => $token_informe,
                                'tarprog.token_proyecto' => $token_proyecto,
                                'subtar.token_tarea' => $token_tarea,
                            ])
                            ->limit(1)->update(
                                array(
                                    'inform.revisado' => TRUE,
                                )
                            );
                            
                            if ($updateInformeAct) {
                                $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ?
                                    AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                    [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? 
                                    AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa
                                    AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                                $titulo_alerta = "Ha revisado el informe con folio ".$folio_inf;
                                
                                $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,
                                    tel_pers_resp.habilitado FROM module_proyectos AS tar JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp 
                                    JOIN sos_personas_telefonos AS tel_pers_resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto 
                                    AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? 
                                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                                foreach ($selectTelefono as $valPhone){
                                    if ($valPhone->tipo_pp == 'li') {
                                        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    } else {
                                        $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    }
                                }
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Informe revisado satisfactoriamente'
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Informe no revisado, intente nuevamente'
                                );
                            } 
                        } else {
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Informe revisado satisfactoriamente'
                            );
                        }    
                    }    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para revisar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para revisar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para revisar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function aprobarInformeTarea(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
                'decision' => 'required|string',
                'txt_observaciones' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                $decision = $parametrosArray['decision'];
                $txt_observaciones = $parametrosArray['txt_observaciones'];
                
                
                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe) && 
                    isset($decision) && !empty($decision) && 
                    isset($txt_observaciones) && !empty($txt_observaciones) && 
                    preg_match($patron,$txt_observaciones)) {
                        
                    $txt_observaciones = str_replace(".diagon.","/",$txt_observaciones);
                    $txt_observaciones = str_replace(".porcent.","%",$txt_observaciones);
                    $txt_observaciones = str_replace(".ampersand.","&",$txt_observaciones);
                    $txt_observaciones = str_replace(".pessos.","$",$txt_observaciones);
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                    $selectInf = DB::table("module_proyectos_informes AS inf")
                    ->join("module_proyectos_tareas AS tar","inf.tarea","=","tar.id")
                    ->join("module_proyectos AS proy","inf.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                    ->where([
                        'inf.token_informe' => $token_informe,
                        'tar.token_tarea' => $token_tarea,
                        'proy.token_proyecto' => $token_proyecto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($selectInf as $valevd) {
                        $selectProyecto = DB::select("SELECT proy.id,proy.folio,proy.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS proy JOIN main_empresas AS emp
                            JOIN module_proyectos_responsable AS resp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto 
                            AND resp.tipo_pp = 'cr' AND proy.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        
                        $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                        }

                        $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id 
                            AND subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa
                            AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);

                        if ($decision == "true") {
                            $sql_decision = TRUE;
                            $titulo_alerta = "Ha aprobado el informe con folio ".$folio_inf;
                            $status_aprob = "Informe aprobado satisfactoriamente";
                        } else {
                            $sql_decision = FALSE;
                            $titulo_alerta = "Ha desaprobado el informe con folio ".$folio_inf;
                            $status_aprob = "Informe desaprobado satisfactoriamente";
                        }
                        
                        $sql_observaciones = $JwtAuth->encriptar($txt_observaciones);

                        $updateInformeAct = DB::table("module_proyectos_informes AS inform")
                        ->join("module_proyectos AS tarprog","inform.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","subtar.id")
                        ->where([
                            'inform.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])
                        ->limit(1)->update(
                            array(
                                'inform.revisado' => TRUE,
                                'inform.aprobado' => $sql_decision,
                            )
                        );
                        
                        $tokenAprob = $JwtAuth->encriptarToken($token_proyecto,$token_tarea,
                            $token_informe,$decision,$txt_observaciones,time(),
                            $selectProyecto[0]->id);
                        
                        $insertAprob = DB::table("module_proyectos_informes_aprobar")
                        ->insert(
                            array(
                                "token_aprobar" => $tokenAprob,	
                                "fecha_aprobar" => time(),	
                                "aprobado_list" => $sql_decision,
                                "comentarios_aprob" => $sql_observaciones,	
                                "proyecto" => $selectProyecto[0]->id,
                                "tarea" => $selectSubTarea[0]->id,
                                "informe" => $selectInforme[0]->id,
                            )
                        );
                        
                        if ($updateInformeAct || $insertAprob) {
                            if ($sql_decision == FALSE) {
                                $queryPersInforme = DB::table("module_proyectos_informes AS inf")
                                ->join("vhum_empleados_catalogo AS pers","inf.personal_realiza","pers.id")
                                ->join("module_proyectos_tareas AS tar","inf.tarea","tar.id")
                                ->join("module_proyectos AS proy","inf.proyecto","proy.id")
                                ->where([
                                    'inf.token_informe' => $token_informe,
                                    'tar.token_tarea' => $token_tarea,
                                    'proy.token_proyecto' => $token_proyecto,
                                ])->get();
                                
                                foreach ($queryPersInforme as $vPersRea) {
                                    $activaParticipacion = DB::table("module_proyectos_tarea_responsable AS resp_tar")
                                    ->join("vhum_empleados_catalogo AS pers","resp_tar.personal","pers.id")
                                    ->join("module_proyectos_tareas AS tar","resp_tar.tarea","tar.id")
                                    ->join("module_proyectos AS proy","resp_tar.proyecto","proy.id")
                                    ->where(['pers.empleado_token' => $vPersRea->empleado_token,'tar.token_tarea' => $token_tarea,'proy.token_proyecto' => $token_proyecto])
                                    ->limit(1)->update(array('resp_tar.participacion' => FALSE,));
                                }
                            }
                            
                            $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            foreach ($selectTelefono as $valPhone){
                                if ($valPhone->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } else {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                            }
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => $status_aprob
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Informe no aprobado/desaprobado, intente nuevamente'
                            );
                        }    
                    }    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para aprobar/desaprobar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para aprobar/desaprobar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para aprobar/desaprobar'
                        );
                    }
                    if (!isset($decision) || empty($decision)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia para aprobar/desaprobar informe'
                        );
                    }
                    if (!isset($txt_observaciones) || empty($txt_observaciones) || 
                        !preg_match($patron,$txt_observaciones)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe contenido de informe para aprobar/desaprobar o es invalido'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function updateInformeTarea(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
                'txt_informe' => 'string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                $txt_informe = $parametrosArray['txt_informe'];
                
                
                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe) && 
                    isset($txt_informe) && !empty($txt_informe) && preg_match($patron,$txt_informe)) {
                        
                    $txt_informe = str_replace(".diagon.","/",$txt_informe);
                    $txt_informe = str_replace(".porcent.","%",$txt_informe);
                    $txt_informe = str_replace(".ampersand.","&",$txt_informe);
                    $txt_informe = str_replace(".pessos.","$",$txt_informe);
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                    $selectInf = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos_tareas AS tar","inf.tarea","=","tar.id")
                        ->join("module_proyectos AS proy","inf.proyecto","=","proy.id")
                        ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tar.token_tarea' => $token_tarea,
                            'proy.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->get();
                    
                    foreach ($selectInf as $valevd) {
                        $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND proy.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                        }

                        $updateInformeAct = DB::table("module_proyectos_informes AS inform")
                        ->join("module_proyectos AS tarprog","inform.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","subtar.id")
                        ->where([
                            'inform.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])
                        ->limit(1)->update(
                            array(
                                'inform.informe' => $JwtAuth->encriptar($txt_informe),
                            )
                        );
                        
                        if ($updateInformeAct) {
                            $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar
                                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id 
                                AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                            $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id 
                                AND subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            $titulo_alerta = "Ha actualizado el informe con folio ".$folio_inf;
                            
                            $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado 
                                FROM module_proyectos AS tar JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            foreach ($selectTelefono as $valPhone){
                                if ($valPhone->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } else {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                            }
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Informe actualizado satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Informe no actualizado, intente nuevamente'
                            );
                        }    
                    }    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                    if (!isset($txt_informe) || empty($txt_informe) || !preg_match($patron,$txt_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe contenido de informe para actualizar o es invalido'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function updateObservacionesInforme(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
                'txt_observaciones' => 'string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                $txt_observaciones = $parametrosArray['txt_observaciones'];
                
                
                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe) && 
                    isset($txt_observaciones) && !empty($txt_observaciones) && preg_match($patron,$txt_observaciones)) {
                        
                    $txt_observaciones = str_replace(".diagon.","/",$txt_observaciones);
                    $txt_observaciones = str_replace(".porcent.","%",$txt_observaciones);
                    $txt_observaciones = str_replace(".ampersand.","&",$txt_observaciones);
                    $txt_observaciones = str_replace(".pessos.","$",$txt_observaciones);
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                    $selectInf = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos_tareas AS tar","inf.tarea","=","tar.id")
                        ->join("module_proyectos AS proy","inf.proyecto","=","proy.id")
                        ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tar.token_tarea' => $token_tarea,
                            'proy.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->get();
                    
                    foreach ($selectInf as $valevd) {
                        $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio,resp.personal AS creador_tarea FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE proy.token_proyecto = ? AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND proy.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => '$cantidad']);
                        if ($valevd->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                        }

                        $updateInformeAct = DB::table("module_proyectos_informes AS inform")
                        ->join("module_proyectos AS tarprog","inform.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","subtar.id")
                        ->where([
                            'inform.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])
                        ->limit(1)->update(
                            array(
                                'inform.observaciones' => $JwtAuth->encriptar($txt_observaciones),
                            )
                        );
                        
                        if ($updateInformeAct) {
                            $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                            $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? AND subtar.proyecto = tar.id 
                                AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            $titulo_alerta = "Ha actualizado el informe con folio ".$folio_inf;
                            
                            $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            foreach ($selectTelefono as $valPhone){
                                if ($valPhone->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } else {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                            }
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Informe actualizado satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Informe no actualizado, intente nuevamente'
                            );
                        }    
                    }    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                    if (!isset($txt_informe) || empty($txt_informe) || !preg_match($patron,$txt_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe contenido de informe para actualizar o es invalido'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function cargaEvidenciasInformeTarea(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                
                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe)) {
                        
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                        
                    $selectInf = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos_tareas AS tar","inf.tarea","=","tar.id")
                        ->join("module_proyectos AS proy","inf.proyecto","=","proy.id")
                        ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tar.token_tarea' => $token_tarea,
                            'proy.token_proyecto' => $token_proyecto,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->get();
                    
                    foreach ($selectInf as $valevd) {
                        if ($valevd->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valevd->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($valevd->folio).'-'.$valevd->post_folio;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => 'p1']);
                        if ($valevd->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => 'p2']);
                        if ($valevd->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                        }
                        //return response()->json(["status" => "error","code" => 200,'message' => 'p3']);
                        $selProyID = DB::select("SELECT id FROM module_proyectos WHERE token_proyecto = ?",[$token_proyecto]);
                        $selTarID = DB::select("SELECT id FROM module_proyectos_tareas WHERE token_tarea = ?",[$token_tarea]);
                        $selInfID = DB::select("SELECT id FROM module_proyectos_informes WHERE token_informe = ?",[$token_informe]);
                        
                        $filepath = $valevd->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/";
                        $validateEvid = false;
                        $countLoad = 0;
                        //return response()->json(["status" => "error","code" => 200,'message' => 'p4']);
                        $evidencias = $_FILES["imgEvidencias"];
                        $evidencia_nombre = $_FILES["imgEvidencias"]["name"];
                        
                        if(!empty($evidencias)){
                            $string_name_evid = json_encode($_FILES["imgEvidencias"]["name"]);
                            if (count(json_decode($string_name_evid)) != 0) {
                                $evidencia_nombre = json_decode($string_name_evid);
                                for ($i=0; $i < count($evidencia_nombre); $i++){
                                    $nombre = $evidencia_nombre[$i];
                                    $temporal = $evidencias["tmp_name"][$i];
                                    Storage::putFileAs("/public/root/".$filepath,$temporal,$nombre);
                                    
                                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PR-EVID%'");
                                    $tkn_evidencia = $JwtAuth->encriptarToken($token_proyecto,$token_tarea,$usuario->user_token,$usuario->empresa_token,$nombre);
                                    $insertEvidenceInf = DB::table('sos_documentos')->insert(
                                        array(
                                            "token_documento" => $tkn_evidencia,
                                            "fecha_carga" => time(),
                                            "modulo" => "proyectos",
                                            "folio_modulo" => "PR-EVID".$select_folio_doc[0]->folio,
                                            "tipo_documento" => "file",
                                            "nombre_documento" => $JwtAuth->encriptar($nombre),
                                            "proyecto" => $selProyID[0]->id,
                                            "tarea" => $selTarID[0]->id,
                                            "informe" => $selInfID[0]->id,
                                            "status_documento" => TRUE,	
                                            "fecha_delete_documento" => NULL,
                                        ) 
                                    );
                                    if ($insertEvidenceInf) {
                                        $countLoad++;
                                    }
                                }
                            }
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'upload_evid'
                            );
                        }
                        
                        if ($countLoad == count(json_decode($string_name_evid))) {
                            $validateEvid = true;
                        } else {
                            $validateEvid = false;
                        }
                        
                        if ($validateEvid == true) {
                            $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                                AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                            $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? AND subtar.proyecto = tar.id 
                                AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            $titulo_alerta = "Ha actualizado el informe con folio ".$folio_inf;
                            
                            $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            foreach ($selectTelefono as $valPhone){
                                if ($valPhone->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } else {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                            }
                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Informe actualizado satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Informe no actualizado, intente nuevamente'
                            );
                        }
                    }    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function deleteEvidenciaInfProyecto(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
                'token_documento' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                $token_documento = $parametrosArray['token_documento'];

                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe) &&
                    isset($token_documento) && !empty($token_documento)) {

                    $selectEvd = DB::table("sos_documentos AS evd")
                    ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                    ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                    ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                    ->where([
                        'evd.token_documento' => $token_documento,
                        'proy.token_proyecto' => $token_proyecto,
                        'tar.token_tarea' => $token_tarea,
                        'inf.token_informe' => $token_informe,
                        'evd.status_documento' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    if (count($selectEvd) != 0) {
                        foreach($selectEvd as $valevd){
                            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            
                            $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                WHERE proy.token_proyecto = ? AND proy.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);

                            if ($selectProyecto[0]->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                            }

                            if ($valevd->post_folio_tar == NULL) {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                            } else {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                            }

                            if ($valevd->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                            }
                            //return response()->json(["status" => "error","code" => 200,'message' => 'evd'.$encod_evidencias]);
                            $updateInformeAct = DB::table("sos_documentos AS evd")
                            ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                            ->where([
                                'evd.token_documento' => $token_documento,
                                'proy.token_proyecto' => $token_proyecto,
                                'tar.token_tarea' => $token_tarea,
                                'inf.token_informe' => $token_informe,
                                'evd.status_documento' => TRUE,
                            ])->limit(1)->update(
                                array(
                                    'evd.status_documento' => FALSE,
                                    'evd.fecha_delete_documento' => time(),
                                )
                            );
                            //$updateInformeAct = 1;
                            if ($updateInformeAct) {
                                $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                    [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id 
                                    AND subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa
                                    AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                                $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                    JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                    AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $titulo_alerta = "Ha eliminado evidencias del informe con folio ".$folio_inf;
                                foreach ($selectTelefono as $valPhone){
                                    if ($valPhone->tipo_pp == 'li') {
                                        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    } else {
                                        $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    }
                                }
    
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Evidencia eliminada'
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Evidencia no eliminada'
                                );
                            }

                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Evidencia no encontrada'
                        );
                    }
                    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                    if (!isset($token_documento) || empty($token_documento)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de evidencia para eliminar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function restartEvidenciaInfProyecto(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
                'token_documento' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                $token_documento = $parametrosArray['token_documento'];

                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe) &&
                    isset($token_documento) && !empty($token_documento)) {

                    $selectEvd = DB::table("sos_documentos AS evd")
                    ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                    ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                    ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                    ->where([
                        'evd.token_documento' => $token_documento,
                        'proy.token_proyecto' => $token_proyecto,
                        'tar.token_tarea' => $token_tarea,
                        'inf.token_informe' => $token_informe,
                        'evd.status_documento' => FALSE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    if (count($selectEvd) != 0) {
                        foreach($selectEvd as $valevd){
                            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            
                            $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                WHERE proy.token_proyecto = ? AND proy.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);

                            if ($selectProyecto[0]->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                            }

                            if ($valevd->post_folio_tar == NULL) {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                            } else {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                            }

                            if ($valevd->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                            }
                            //return response()->json(["status" => "error","code" => 200,'message' => 'evd'.$encod_evidencias]);
                            $updateInformeAct = DB::table("sos_documentos AS evd")
                            ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                            ->where([
                                'evd.token_documento' => $token_documento,
                                'proy.token_proyecto' => $token_proyecto,
                                'tar.token_tarea' => $token_tarea,
                                'inf.token_informe' => $token_informe,
                                'evd.status_documento' => FALSE,
                            ])->limit(1)->update(
                                array(
                                    'evd.status_documento' => TRUE,
                                    'evd.fecha_delete_documento' => NULL,
                                )
                            );
                            //$updateInformeAct = 1;
                            if ($updateInformeAct) {
                                $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                    [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? 
                                    AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                                $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                    JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                    AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $titulo_alerta = "Ha restaurado evidencias del informe con folio ".$folio_inf;
                                foreach ($selectTelefono as $valPhone){
                                    if ($valPhone->tipo_pp == 'li') {
                                        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    } else {
                                        $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    }
                                }
    
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Evidencia restaurada'
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Evidencia no restaurada'
                                );
                            }

                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Evidencia no encontrada'
                        );
                    }
                    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                    if (!isset($token_documento) || empty($token_documento)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de evidencia para eliminar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function deleteEvidInfProyectoPermanente(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
                'token_documento' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                $token_documento = $parametrosArray['token_documento'];

                if (isset($token_proyecto) && !empty($token_proyecto) &&
                    isset($token_tarea) && !empty($token_tarea) &&
                    isset($token_informe) && !empty($token_informe) &&
                    isset($token_documento) && !empty($token_documento)) {

                    $selectEvd = DB::table("sos_documentos AS evd")
                    ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                    ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                    ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                    ->where([
                        'evd.token_documento' => $token_documento,
                        'proy.token_proyecto' => $token_proyecto,
                        'tar.token_tarea' => $token_tarea,
                        'inf.token_informe' => $token_informe,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    if (count($selectEvd) != 0) {
                        foreach($selectEvd as $valevd){
                            $name_evidencia = $JwtAuth->desencriptar($valevd->nombre_documento);
                            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                            
                            $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                                WHERE proy.token_proyecto = ? AND proy.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);

                            if ($selectProyecto[0]->post_folio == NULL) {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                            } else {
                                $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                            }

                            if ($valevd->post_folio_tar == NULL) {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                            } else {
                                $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                            }

                            if ($valevd->post_folio_informe == NULL) {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                            } else {
                                $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                            }
                            $filepath = $valevd->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/";

                            //return response()->json(["status" => "error","code" => 200,'message' => 'evd'.$encod_evidencias]);
                            $deleteInformeAct = DB::table("sos_documentos AS evd")
                            ->join("module_proyectos AS proy","evd.proyecto","=","proy.id")
                            ->join("module_proyectos_tareas AS tar","evd.tarea","=","tar.id")
                            ->join("module_proyectos_informes AS inf","evd.informe","=","inf.id")
                            ->where([
                                'evd.token_documento' => $token_documento,
                                'proy.token_proyecto' => $token_proyecto,
                                'tar.token_tarea' => $token_tarea,
                                'inf.token_informe' => $token_informe,
                            ])->limit(1)->delete();
                            //$updateInformeAct = 1;
                            if ($deleteInformeAct) {
                                $filepath = $valevd->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/";
                                if (file_exists(storage_path("/root/".$filepath.$name_evidencia))) {
                                    Storage::delete("/public/root/".$filepath.$name_evidencia);
                                }
                                
                                $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ?
                                    AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                    [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $selectInforme = DB::select("SELECT infme.id FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? 
                                    AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                                $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                    JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                                    AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                    AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                    
                                $titulo_alerta = "Ha eliminado evidencias del informe con folio ".$folio_inf;
                                foreach ($selectTelefono as $valPhone){
                                    if ($valPhone->tipo_pp == 'li') {
                                        $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    } else {
                                        $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                    }
                                }
    
                                $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                
                                $dataMensaje = array(
                                    'status' => 'success',
                                    "code" => 200,
                                    'message' => 'Evidencia eliminada'
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    'message' => 'Evidencia no eliminada'
                                );
                            }

                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Evidencia no encontrada'
                        );
                    }
                    
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                    if (!isset($token_documento) || empty($token_documento)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de evidencia para eliminar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function deleteInformeTarea(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];

                if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && 
                    !empty($token_tarea) && isset($token_informe) && !empty($token_informe)) {
                    
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    
                    $selectTarea = DB::select("SELECT tar.id, resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp 
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                    $selectInforme = DB::select("SELECT infme.id,infme.folio_informe,infme.post_folio_informe FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? 
                        AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                        [$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                    if ($selectInforme[0]->post_folio_informe == NULL) {
                        $folio_inf = 'INF-'.$JwtAuth->generarFolio($selectInforme[0]->folio_informe);
                    } else {
                        $folio_inf = 'INF-'.$JwtAuth->generarFolio($selectInforme[0]->folio_informe).'-'.$selectInforme[0]->post_folio_informe;
                    }    
                        
                    $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                    
                    $updateInformeAct = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos AS tarprog","inf.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inf.tarea","subtar.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])
                        ->limit(1)->update(
                            array(
                                'inf.status_inf' => FALSE,
                                'inf.fecha_delete_inf' => time(),
                            )
                        );

                    if ($updateInformeAct) {
                        $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                            JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp JOIN main_empresa_usuario AS empuser 
                            JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') AND resp.personal = pers_resp.id 
                            AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $titulo_alerta = "Ha eliminado el informe con el folio ".$folio_inf;
                        foreach ($selectTelefono as $valPhone){
                            if ($valPhone->tipo_pp == 'li') {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } else {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }
                        }
                        
                        $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                        $dataMensaje = array(
                            'status' => 'success',
                            "code" => 200,
                            'message' => 'Informe eliminado satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Informe no eliminado, intente nuevamente'
                        );
                    }


                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para eliminar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para eliminar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para eliminar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function restaurarInformeTarea(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayTareas = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];

                if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && 
                    !empty($token_tarea) && isset($token_informe) && !empty($token_informe)) {
                    
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    
                    $selectTarea = DB::select("SELECT tar.id, resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp 
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' AND tar.empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                    
                    $updateInformeAct = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos AS tarprog","inf.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inf.tarea","subtar.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])
                        ->limit(1)->update(
                            array(
                                'inf.status_inf' => TRUE,
                                'inf.fecha_delete_inf' => NULL,
                            )
                        );

                    if ($updateInformeAct) {
                        $selectInforme = DB::select("SELECT infme.id,infme.folio_informe,infme.post_folio_informe FROM module_proyectos_informes AS infme JOIN module_proyectos_tareas AS subtar JOIN module_proyectos AS tar
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE infme.token_informe = ? AND infme.tarea = subtar.id AND subtar.token_tarea = ? 
                            AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND 
                            users.usuario_token = ?",[$token_informe,$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                        if ($selectInforme[0]->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($selectInforme[0]->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($selectInforme[0]->folio_informe).'-'.$selectInforme[0]->post_folio_informe;
                        } 
                                
                        $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id AND tar.token_proyecto = ? AND tar.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                        $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado FROM module_proyectos AS tar 
                            JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') 
                            AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                            AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                        $titulo_alerta = "Ha restaurado el informe con el folio ".$folio_inf;
                        foreach ($selectTelefono as $valPhone){
                            if ($valPhone->tipo_pp == 'li') {
                                $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            } else {
                                $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                            }
                        }
    
                        $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,$selectInforme[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                        
                        $dataMensaje = array(
                            'status' => 'success',
                            "code" => 200,
                            'message' => 'Informe restaurado satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'Informe no restaurado, intente nuevamente'
                        );
                    }


                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para actualizar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para actualizar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para actualizar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function deleteInformePerm(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                //'empresa_token' => 'required|string',
                'user_token' => 'required|string',
                'token_proyecto' => 'required|string',
                'token_tarea' => 'required|string',
                'token_informe' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    'message' => 'Usuario incorrecto'.$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
                
                $token_proyecto = $parametrosArray['token_proyecto'];
                $token_tarea = $parametrosArray['token_tarea'];
                $token_informe = $parametrosArray['token_informe'];
                
                if (isset($token_proyecto) && !empty($token_proyecto) && isset($token_tarea) && 
                    !empty($token_tarea) && isset($token_informe) && !empty($token_informe)) {
                    
                    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    
                    $selectEvd = DB::table("module_proyectos_informes AS inf")
                    ->join("module_proyectos_tareas AS tar","inf.tarea","=","tar.id")
                    ->join("module_proyectos AS proy","inf.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","proy.empresa","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                    ->where([
                        'inf.token_informe' => $token_informe,
                        'tar.token_tarea' => $token_tarea,
                        'proy.token_proyecto' => $token_proyecto,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();

                    foreach($selectEvd as $valevd){
                        $selectProyecto = DB::select("SELECT proy.folio,proy.post_folio FROM module_proyectos AS proy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                            WHERE proy.token_proyecto = ? AND proy.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$parametrosArray['token_proyecto'],$usuario->empresa_token,$usuario->user_token]);

                        if ($selectProyecto[0]->post_folio == NULL) {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio);
                        } else {
                            $folio_proy = 'PROY-'.$JwtAuth->generarFolio($selectProyecto[0]->folio).'-'.$selectProyecto[0]->post_folio;
                        }
                        
                        if ($valevd->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($valevd->folio_tarea).'-'.$valevd->post_folio_tar;
                        }

                        if ($valevd->post_folio_informe == NULL) {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe);
                        } else {
                            $folio_inf = 'INF-'.$JwtAuth->generarFolio($valevd->folio_informe).'-'.$valevd->post_folio_informe;
                        }

                        $filepath = $valevd->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/";
                        Storage::delete("/public/root/".$filepath);

                        $selectTarea = DB::select("SELECT tar.id,resp.personal AS creador_tarea FROM module_proyectos AS tar JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp 
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto AND resp.tipo_pp = 'cr' 
                            AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                        
                        $JwtAuth->deleteNotifInf($token_informe);
                        
                        $docsInforme = DB::table("module_proyectos_informes AS inform")
                        ->join("sos_documentos AS evd","inform.id","=","evd.informe")
                        ->join("vhum_empleados_catalogo AS pers","inform.personal_realiza","=","pers.id")
                        ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                        ->join("module_proyectos_tareas AS subtar","inform.tarea","=","subtar.id")
                        ->join("module_proyectos AS tar","inform.proyecto","=","tar.id")
                        ->where([
                            'inform.token_informe' => $token_informe,
                            'subtar.token_tarea' => $token_tarea,
                            'tar.token_proyecto' => $token_proyecto,
                        ])->get();

                        //echo "docsInforme ".count($docsInforme)." ";

                        if (count($docsInforme) != 0) {
                            foreach ($docsInforme as $vDocs) {
                                $filepath = $selectEmp[0]->root_tkn."/0008-proyectos/".$folio_proy."/".$folio_tar."/".$folio_inf."/".$JwtAuth->desencriptar($vDocs->nombre_documento);
                                //echo $filepath;exit;
                                Storage::delete("/public/root/".$filepath);
                                
                                $docsDelete = DB::table("sos_documentos")
                                ->where(["token_documento" => $vDocs->token_documento])->limit(1)->delete();
                            }
                        }
                        
                        $updateInformeAct = DB::table("module_proyectos_informes AS inf")
                        ->join("module_proyectos AS tarprog","inf.proyecto","tarprog.id")
                        ->join("module_proyectos_tareas AS subtar","inf.tarea","subtar.id")
                        ->where([
                            'inf.token_informe' => $token_informe,
                            'tarprog.token_proyecto' => $token_proyecto,
                            'subtar.token_tarea' => $token_tarea,
                        ])
                        ->limit(1)->delete();

                        if ($updateInformeAct) {
                            $selectSubTarea = DB::select("SELECT subtar.id,subtar.folio_tarea,subtar.post_folio_tar FROM module_proyectos_tareas AS subtar JOIN module_proyectos AS tar JOIN main_empresas AS emp 
                                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE subtar.token_tarea = ? AND subtar.proyecto = tar.id 
                                AND tar.token_proyecto = ? AND tar.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                                [$token_tarea,$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                            
                            $selectTelefono = DB::select("SELECT resp.tipo_pp,pers_resp.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone, tel_pers_resp.habilitado FROM module_proyectos AS tar 
                                JOIN main_empresas AS emp JOIN module_proyectos_responsable AS resp JOIN vhum_empleados_catalogo AS pers_resp JOIN sos_personas_telefonos AS tel_pers_resp 
                                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE tar.token_proyecto = ? AND tar.id = resp.proyecto 
                                AND (resp.tipo_pp = 'cr' OR resp.tipo_pp = 'li') AND resp.personal = pers_resp.id AND pers_resp.id = tel_pers_resp.personal AND tar.empresa = emp.id AND emp.empresa_token = ? 
                                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$token_proyecto,$usuario->empresa_token,$usuario->user_token]);
                                
                            $titulo_alerta = "Ha eliminado permanentemente el informe con folio ".$folio_inf;
                            foreach ($selectTelefono as $valPhone){
                                if ($valPhone->tipo_pp == 'li') {
                                    $JwtAuth->insertNotifLi("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                } else {
                                    $JwtAuth->insertNotifCr("Actualización de proyecto",$token_proyecto,$titulo_alerta,$selectSubTarea[0]->id,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                }
                            }

                            $JwtAuth->insertNotifEqTar("Actualización de proyecto",$token_proyecto,$token_tarea,$titulo_alerta,$selectSubTarea[0]->id,NULL,NULL,$selectEmp[0]->id,$selectEmp[0]->userr,NULL);
                                                        
                            $dataMensaje = array(
                                'status' => 'success',
                                "code" => 200,
                                'message' => 'Informe eliminado satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                'message' => 'Informe no eliminado, intente nuevamente'
                            );
                        }
                    }
                } else {
                    if (!isset($token_proyecto) || empty($token_proyecto)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de proyecto para eliminar informe'
                        );
                    }
                    if (!isset($token_tarea) || empty($token_tarea)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de tarea para eliminar informe'
                        );
                    }
                    if (!isset($token_informe) || empty($token_informe)) {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            'message' => 'No existe referencia de informe para eliminar'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }
    
    public function verDocInforme(Request $request){
        $JwtAuth = new \JwtAuth();
        //var_dump($request);
        $folioProy = $request->folioProy;
        $folioTar = $request->folioTar;
        $folioInf = $request->folioInf;
        $tokenInf = $request->tokenInf;
        if (!empty($folioProy) && !empty($folioProy) &&
            !empty($folioTar) && !empty($folioTar) &&
            !empty($tokenInf) && !empty($tokenInf)) {
            
            $docs_query = DB::table("sos_documentos AS doc")
            ->join("module_proyectos AS proy","doc.proyecto","=","proy.id")
            ->join("main_empresas AS emp","proy.empresa","=","emp.id")
            ->where(["doc.token_documento" => $tokenInf])->get();
            
            foreach ($docs_query as $vDoc) {
                $name_doc = $JwtAuth->desencriptar($vDoc->nombre_documento);
                $filepath = $vDoc->root_tkn."/0008-proyectos/".$folioProy."/".$folioTar."/".$folioInf.'/'.$name_doc;
                
                $archivo = Storage::path('public/root/'.$filepath);
                $extension = pathinfo($archivo, PATHINFO_EXTENSION);
                
                if (file_exists($archivo)) {                    
                    if ($extension == 'pdf') {
                        $ruta = Storage::disk('root')->get($filepath);
                        return Response($ruta, 200, ['Content-Type' => 'application/pdf','Content-Disposition' => 'inline; filename="'.$name_doc.'"']);
                    } else if ($extension == 'xml') {
                        $ruta = Storage::disk('root')->get($filepath);
                        return Response($ruta, 200, ['Content-Type' => 'text/xml','Content-Disposition' => 'inline; filename="'.$name_doc.'"']);
                    } else if ($extension == 'jpg' || $extension == 'png') {
                        $ruta = Storage::disk('root')->get($filepath);
                        return Response($ruta, 200, ['Content-Type' => 'image/jpeg','Content-Disposition' => 'inline; filename="'.$name_doc.'"']);
                    } else if ($extension == 'jpg' || $extension == 'png') {
                        $ruta = Storage::disk('root')->get($filepath);
                        return Response($ruta, 200, ['Content-Type' => 'image/png','Content-Disposition' => 'inline; filename="'.$name_doc.'"']);
                    } else {
                        $content_typo = Storage::disk('root')->mimeType($filepath);
                        $ruta = Storage::disk('root')->get($filepath);
                        return Response($ruta, 200, ['Content-Type' => $content_typo,'Content-Disposition' => 'inline; filename="'.$name_doc.'"']);
                    }
                    
                } else {
                    $ruta = Storage::disk('settings')->get('dont_exist_evidencia.png');
                    return Response($ruta, 200, ['Content-Type' => 'image/png','Content-Disposition' => 'inline; filename="dont_exist_evidencia.png"']);
                }
            }    
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => 'La información que intenta registrar no es valida'
            );
            return response()->json($dataMensaje, $dataMensaje['code']);
        }
    }
                                
}               