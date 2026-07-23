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
use App\Models\EventosModeloConsulta;
use App\Models\EventosModeloInsert;
use App\Models\ModuleProyectosModelo;
use PDF;
use QRCode;

class JURI_EventosController extends Controller {
    public function calendarCompleteProyectos(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listCalendarProyectos = array();
        $listGanttProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                
                if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                    $eventList = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
                    ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                    ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                    ->where(["evnt.area" => 10,"proy.status_proyecto" => TRUE,"resp_pr.tipo_pp" => 'cr',"emp.emp_token" => $usuario->emp_token])                
                    ->orderBy('evnt.end_time','ASC')->get();
                } else {
                    $eventList = DB::select("SELECT evt.evento,evt.start_time,evt.end_time,evt.evt_tarea,
                        proy.token_proyecto,proy.folio,proy.post_folio,proy.proyecto_name,emp.zona_horaria 
                        FROM module_proyectos_eventos AS evt JOIN module_proyectos AS proy JOIN main_empresas AS emp WHERE evt.area = 10 
                        AND evt.proyecto = proy.id AND proy.status_proyecto = TRUE AND proy.empresa = emp.id 
                        AND emp.emp_token = ? AND proy.id IN (SELECT proyecto FROM module_proyectos_responsable AS resp_pr 
                            JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE resp_pr.personal = pers.id 
                            AND pers.usuario = users.id AND users.user_token = ?)
                        ORDER BY evt.end_time ASC",[$usuario->emp_token,$usuario->user_token]);
                }
                
                foreach ($eventList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->end_time);
                    $start_calendar_long = $value->start_time;
                    $end_calendar_long = $value->end_time;
                    $fecha_fin_plana = $value->end_time;

                    if ($value->evt_tarea == NULL) {
                        $queryTarList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                        
                        $tar_total = count($queryTarList);
                        $tar_terminadas = 0;
                        
                        foreach ($queryTarList as $valSub) {
                            if ($valSub->realizacion == TRUE) {
                                $tar_terminadas++;
                            }
                        }
                        
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $stado_tarea = "#EFEFEF";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma = "#67FF80";//darkgreen
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma = "#FB8C00";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma = "#FF6767";//darkred
                                }
                            } else {
                                $paloma = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $stado_tarea = "#67FF80";//darkgreen
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $stado_tarea = "#FB8C00";
                                } else if ($fecha_fin_plana <= time()){
                                    $stado_tarea = "#FF6767";//darkred
                                }
                            }
                        } else {
                            $stado_tarea = "#6FBDFE";//353553 
                        }
                        
                        $row = array(
                            "token_proyecto" => $value->token_proyecto,
                            "token_tarea" => "",
                            "title" => $folio_proy,
                            "id" => $folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).", Evento: ".$value->evento,
                            "backgroundColor" => $stado_tarea,
                            "textColor" => "black",
                            "start" => $start_calendar,
                            "end" => $end_calendar,
                            "start_long" => $start_calendar_long,
                            "end_long" => $end_calendar_long,
                        );
                        $listCalendarProyectos [] = $row;    
                    } else {
                        $eventTar = EventosModeloConsulta::join("module_proyectos_tareas AS tar","evnt.evt_tarea","=","tar.id")
                        ->where([
                            'evnt.area' => 10,
                            'evnt.evt_tarea' => $value->evt_tarea,
                        ])                
                        ->orderBy('evnt.end_time','ASC')->get();
                        
                        if ($eventTar[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea).'-'.$eventTar[0]->post_folio_tar;
                        }
                    
                        $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->start_time);
                        $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->end_time);
                        $fecha_fin_plana = $eventTar[0]->end_time;
                    
                        if ($eventTar[0]->realizacion == TRUE) {
                            $semaforo_realizacion = "#67FF80";//darkgreen
                        } else {
                            $time_fin = time()+(86400*5);
                            if ($eventTar[0]->fin_tarea > $time_fin) {
                                $semaforo_realizacion = "#FFBC67";//darkgreen
                            } else if ($eventTar[0]->fin_tarea > time() && $eventTar[0]->fin_tarea < $time_fin) {
                                $semaforo_realizacion = "#FB8C00";
                            } else if ($eventTar[0]->fin_tarea <= time()){
                                $semaforo_realizacion = "#FF6767";//darkred
                            }
                        }
                    
                        $row = array(
                            "token_proyecto" => "",
                            "token_tarea" => $eventTar[0]->token_tarea,
                            "title" => $folio_tar,
                            "id" => "Proyecto ".$folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).
                                ", Tarea: ".$folio_tar." - ".$JwtAuth->desencriptar($eventTar[0]->tarea_nombre).
                                ", Evento: ".$eventTar[0]->evento,
                            "backgroundColor" => $semaforo_realizacion,
                            "textColor" => "black",
                            "start" => $start_calendar,
                            "end" => $end_calendar,
                            "start_long" => $start_calendar_long,
                            "end_long" => $end_calendar_long,
                        );
                        $listCalendarProyectos [] = $row;
                    }
                    
                    $rowGantt = array(
                        "pID" => 1,
                        "pName" => "Define Chart API v1",
                        "pStart" => "",
                        "pEnd" => "",
                        "pClass" => "ggroupblack",
                        "pLink" => "",
                        "pMile" => 0,
                        "pRes" => "Brian",
                        "pComp" => 0,
                        "pGroup" => 1,
                        "pParent" => 0,
                        "pOpen" => 1,
                        "pDepend" => "",
                        "pCaption" => "",
                        "pNotes" => "Some Notes text"
                    );
                    $listGanttProyectos [] = $rowGantt;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'calendar' => count($listCalendarProyectos),
                    'calendar_proyectos' => $listCalendarProyectos,
                    'gantt' => count($listGanttProyectos),
                    'gantt_proyectos' => $listGanttProyectos,
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
    
    public function calendarProyectos(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listCalendarProyectos = array();
        $listGanttProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                
                if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                    $eventList = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
                    ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                    ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                    ->where(["evnt.area" => 10,"proy.status_proyecto" => TRUE,"evnt.evt_tarea" => NULL,"resp_pr.tipo_pp" => 'cr',"emp.emp_token" => $usuario->emp_token])                
                    ->orderBy('evnt.end_time','ASC')->get();
                } else {
                    $eventList = DB::select("SELECT evt.evento,evt.start_time,evt.end_time,proy.token_proyecto,
                        proy.folio,proy.post_folio,proy.proyecto_name,emp.zona_horaria FROM module_proyectos_eventos AS evt 
                        JOIN module_proyectos AS proy JOIN main_empresas AS emp WHERE evt.area = 10 
                        AND evt.proyecto = proy.id AND proy.status_proyecto = TRUE AND proy.empresa = emp.id 
                        AND emp.emp_token = ? AND proy.id IN (SELECT proyecto FROM module_proyectos_responsable AS resp_pr 
                            JOIN vhum_personal AS pers JOIN main_usuarios as users WHERE resp_pr.personal = pers.id 
                            AND pers.usuario = users.id AND users.user_token = ?) AND evt.evt_tarea IS NULL
                        ORDER BY evt.end_time ASC",[$usuario->emp_token,$usuario->user_token]);
                }
                
                foreach ($eventList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->end_time);
                    $start_calendar_long = $value->start_time;
                    $end_calendar_long = $value->end_time;
                    $fecha_fin_plana = $value->end_time;

                    $queryTarList = DB::table("module_proyectos_tareas AS subtar")
                    ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                    ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                    ->orderBy("subtar.id","DESC")->get();
                    
                    $tar_total = count($queryTarList);
                    $tar_terminadas = 0;
                    
                    foreach ($queryTarList as $valSub) {
                        if ($valSub->realizacion == TRUE) {
                            $tar_terminadas++;
                        }
                    }
                    
                    if ($tar_total > 0) {
                        if ($tar_total == $tar_terminadas) {
                            $stado_tarea = "#EFEFEF";
                            if ($fecha_fin_plana > $time_fin) {
                                $paloma = "#67FF80";//darkgreen
                            } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                $paloma = "#FB8C00";
                            } else if ($fecha_fin_plana <= time()){
                                $paloma = "#FF6767";//darkred
                            }
                        } else {
                            $paloma = "";
                            if ($fecha_fin_plana > $time_fin) {
                                $stado_tarea = "#67FF80";//darkgreen
                            } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                $stado_tarea = "#FB8C00";
                            } else if ($fecha_fin_plana <= time()){
                                $stado_tarea = "#FF6767";//darkred
                            }
                        }
                    } else {
                        $stado_tarea = "#6FBDFE";//353553 
                    }
                    
                    $row = array(
                        "token_proyecto" => $value->token_proyecto,
                        "title" => $folio_proy,
                        "id" => $folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).", Evento: ".$value->evento,
                        "backgroundColor" => $stado_tarea,
                        "textColor" => "black",
                        "start" => $start_calendar,
                        "end" => $end_calendar,
                        "start_long" => $start_calendar_long,
                        "end_long" => $end_calendar_long,
                    );
                    $listCalendarProyectos [] = $row; 
                    
                    $rowGantt = array(
                        "pID" => 1,
                        "pName" => "Define Chart API v1",
                        "pStart" => "",
                        "pEnd" => "",
                        "pClass" => "ggroupblack",
                        "pLink" => "",
                        "pMile" => 0,
                        "pRes" => "Brian",
                        "pComp" => 0,
                        "pGroup" => 1,
                        "pParent" => 0,
                        "pOpen" => 1,
                        "pDepend" => "",
                        "pCaption" => "",
                        "pNotes" => "Some Notes text"
                    );
                    $listGanttProyectos [] = $rowGantt;
                }
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'calendar' => count($listCalendarProyectos),
                    'calendar_proyectos' => $listCalendarProyectos,
                    'gantt' => count($listGanttProyectos),
                    'gantt_proyectos' => $listGanttProyectos,
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
    
    public function calendarTareas(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listCalendarProyectos = array();
        $listGanttProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                
                if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                    $eventList = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                    ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
                    ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                    ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                    ->where(["evnt.area" => 10,"proy.status_proyecto" => TRUE,"resp_pr.tipo_pp" => 'cr',"emp.emp_token" => $usuario->emp_token])  
                    ->whereNotNull('evnt.evt_tarea')
                    ->orderBy('evnt.end_time','ASC')->get();
                } else {
                    $eventList = DB::select("SELECT evt.evento,evt.start_time,evt.end_time,evt.evt_tarea,
                        proy.token_proyecto,proy.folio,proy.post_folio,proy.proyecto_name,emp.zona_horaria 
                        FROM module_proyectos_eventos AS evt JOIN module_proyectos AS proy JOIN main_empresas AS emp WHERE evt.area = 10 
                        AND evt.proyecto = proy.id AND proy.status_proyecto = TRUE AND proy.empresa = emp.id 
                        AND emp.emp_token = ? AND proy.id IN (SELECT proyecto FROM module_proyectos_responsable AS resp_pr 
                            JOIN vhum_personal AS pers JOIN main_usuarios as users WHERE resp_pr.personal = pers.id 
                            AND pers.usuario = users.id AND users.user_token = ?) AND evt.evt_tarea IS NOT NULL
                        ORDER BY evt.end_time ASC",[$usuario->emp_token,$usuario->user_token]);
                }
                
                foreach ($eventList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->end_time);
                    $start_calendar_long = $value->start_time;
                    $end_calendar_long = $value->end_time;
                    $fecha_fin_plana = $value->end_time;

                    $eventTar = EventosModeloConsulta::join("module_proyectos_tareas AS tar","evnt.evt_tarea","=","tar.id")
                    ->where([
                        'evnt.area' => 10,
                        'evnt.evt_tarea' => $value->evt_tarea,
                    ])                
                    ->orderBy('evnt.end_time','ASC')->get();
                    
                    if ($eventTar[0]->post_folio_tar == NULL) {
                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea);
                    } else {
                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea).'-'.$eventTar[0]->post_folio_tar;
                    }
                
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->end_time);
                    $fecha_fin_plana = $eventTar[0]->end_time;
                
                    if ($eventTar[0]->realizacion == TRUE) {
                        $semaforo_realizacion = "#67FF80";//darkgreen
                    } else {
                        $time_fin = time()+(86400*5);
                        if ($eventTar[0]->fin_tarea > $time_fin) {
                            $semaforo_realizacion = "#FFBC67";//darkgreen
                        } else if ($eventTar[0]->fin_tarea > time() && $eventTar[0]->fin_tarea < $time_fin) {
                            $semaforo_realizacion = "#FB8C00";
                        } else if ($eventTar[0]->fin_tarea <= time()){
                            $semaforo_realizacion = "#FF6767";//darkred
                        }
                    }
                
                    $row = array(
                        "token_tarea" => $eventTar[0]->token_tarea,
                        "title" => $folio_tar,
                        "id" => "Proyecto ".$folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).
                            ", Tarea: ".$folio_tar." - ".$JwtAuth->desencriptar($eventTar[0]->tarea_nombre).
                            ", Evento: ".$eventTar[0]->evento,
                        "backgroundColor" => $semaforo_realizacion,
                        "textColor" => "black",
                        "start" => $start_calendar,
                        "end" => $end_calendar,
                        "start_long" => $start_calendar_long,
                        "end_long" => $end_calendar_long,
                    );
                    $listCalendarProyectos [] = $row;
                    
                    $rowGantt = array(
                        "pID" => 1,
                        "pName" => "Define Chart API v1",
                        "pStart" => "",
                        "pEnd" => "",
                        "pClass" => "ggroupblack",
                        "pLink" => "",
                        "pMile" => 0,
                        "pRes" => "Brian",
                        "pComp" => 0,
                        "pGroup" => 1,
                        "pParent" => 0,
                        "pOpen" => 1,
                        "pDepend" => "",
                        "pCaption" => "",
                        "pNotes" => "Some Notes text"
                    );
                    $listGanttProyectos [] = $rowGantt;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'calendar' => count($listCalendarProyectos),
                    'calendar_proyectos' => $listCalendarProyectos,
                    'gantt' => count($listGanttProyectos),
                    'gantt_proyectos' => $listGanttProyectos,
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
    
    public function calendarProyectosPersonalAll(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listCalendarProyectos = array();
        $listGanttProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'pers_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                
                $eventList = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
                ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                ->where(["evnt.area" => 10,"proy.status_proyecto" => TRUE,"pers.pers_token" => $parametrosArray['pers_token'],"emp.emp_token" => $usuario->emp_token])                
                ->orderBy('evnt.end_time','ASC')->get();
                
                foreach ($eventList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->end_time);
                    $fecha_fin_plana = $value->end_time;

                    if ($value->evt_tarea == NULL) {
                        $queryTarList = DB::table("module_proyectos_tareas AS subtar")
                        ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                        ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                        ->orderBy("subtar.id","DESC")->get();
                        
                        $tar_total = count($queryTarList);
                        $tar_terminadas = 0;
                        
                        foreach ($queryTarList as $valSub) {
                            if ($valSub->realizacion == TRUE) {
                                $tar_terminadas++;
                            }
                        }
                        
                        if ($tar_total > 0) {
                            if ($tar_total == $tar_terminadas) {
                                $stado_tarea = "#EFEFEF";
                                if ($fecha_fin_plana > $time_fin) {
                                    $paloma = "#67FF80";//darkgreen
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $paloma = "#FB8C00";
                                } else if ($fecha_fin_plana <= time()){
                                    $paloma = "#FF6767";//darkred
                                }
                            } else {
                                $paloma = "";
                                if ($fecha_fin_plana > $time_fin) {
                                    $stado_tarea = "#67FF80";//darkgreen
                                } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                    $stado_tarea = "#FB8C00";
                                } else if ($fecha_fin_plana <= time()){
                                    $stado_tarea = "#FF6767";//darkred
                                }
                            }
                        } else {
                            $stado_tarea = "#6FBDFE";//353553 
                        }
                        
                        $row = array(
                            "token_proyecto" => $value->token_proyecto,
                            "date" => $value->fecha,
                            "title" => $folio_proy,
                            "id" => $folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).", Evento: ".$value->evento,
                            "backgroundColor" => $stado_tarea,
                            "textColor" => "black",
                            "start" => $start_calendar,
                            "end" => $end_calendar,
                        );
                        $listCalendarProyectos [] = $row;    
                    } else {
                        $eventTar = EventosModeloConsulta::join("module_proyectos_tareas AS tar","evnt.evt_tarea","=","tar.id")
                        ->where([
                            'evnt.area' => 10,
                            'evnt.evt_tarea' => $value->evt_tarea,
                        ])                
                        ->orderBy('evnt.end_time','ASC')->get();
                        
                        if ($eventTar[0]->post_folio_tar == NULL) {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea);
                        } else {
                            $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea).'-'.$eventTar[0]->post_folio_tar;
                        }
                    
                        $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->start_time);
                        $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->end_time);
                        $fecha_fin_plana = $eventTar[0]->end_time;
                    
                        if ($eventTar[0]->realizacion == TRUE) {
                            $semaforo_realizacion = "#67FF80";//darkgreen
                        } else {
                            $time_fin = time()+(86400*5);
                            if ($eventTar[0]->fin_tarea > $time_fin) {
                                $semaforo_realizacion = "#FFBC67";//darkgreen
                            } else if ($eventTar[0]->fin_tarea > time() && $eventTar[0]->fin_tarea < $time_fin) {
                                $semaforo_realizacion = "#FB8C00";
                            } else if ($eventTar[0]->fin_tarea <= time()){
                                $semaforo_realizacion = "#FF6767";//darkred
                            }
                        }
                    
                        $row = array(
                            "token_tarea" => $eventTar[0]->token_tarea,
                            "title" => $folio_tar,
                            "id" => "Proyecto ".$folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).
                                ", Tarea: ".$folio_tar." - ".$JwtAuth->desencriptar($eventTar[0]->tarea_nombre).
                                ", Evento: ".$eventTar[0]->evento,
                            "backgroundColor" => $semaforo_realizacion,
                            "textColor" => "black",
                            "start" => $start_calendar,
                            "end" => $end_calendar,
                        );
                        $listCalendarProyectos [] = $row;
                    }
                    
                    $rowGantt = array(
                        "pID" => 1,
                        "pName" => "Define Chart API v1",
                        "pStart" => "",
                        "pEnd" => "",
                        "pClass" => "ggroupblack",
                        "pLink" => "",
                        "pMile" => 0,
                        "pRes" => "Brian",
                        "pComp" => 0,
                        "pGroup" => 1,
                        "pParent" => 0,
                        "pOpen" => 1,
                        "pDepend" => "",
                        "pCaption" => "",
                        "pNotes" => "Some Notes text"
                    );
                    $listGanttProyectos [] = $rowGantt;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'calendar' => count($listCalendarProyectos),
                    'calendar_proyectos' => $listCalendarProyectos,
                    'gantt' => count($listGanttProyectos),
                    'gantt_proyectos' => $listGanttProyectos,
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
    
    public function calendarProyectosPersonal(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listCalendarProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'pers_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                
                $eventList = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
                ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                ->where(["evnt.area" => 10,"proy.status_proyecto" => TRUE,"evnt.evt_tarea" => NULL,"pers.pers_token" => $parametrosArray['pers_token'],"emp.emp_token" => $usuario->emp_token])                
                ->orderBy('evnt.end_time','ASC')->get();
                
                foreach ($eventList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->end_time);
                    $fecha_fin_plana = $value->end_time;

                    $queryTarList = DB::table("module_proyectos_tareas AS subtar")
                    ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                    ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                    ->orderBy("subtar.id","DESC")->get();
                        
                    $tar_total = count($queryTarList);
                    $tar_terminadas = 0;
                        
                    foreach ($queryTarList as $valSub) {
                        if ($valSub->realizacion == TRUE) {
                            $tar_terminadas++;
                        }
                    }
                        
                    if ($tar_total > 0) {
                        if ($tar_total == $tar_terminadas) {
                            $stado_tarea = "#EFEFEF";
                            if ($fecha_fin_plana > $time_fin) {
                                $paloma = "#67FF80";//darkgreen
                            } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                $paloma = "#FB8C00";
                            } else if ($fecha_fin_plana <= time()){
                                $paloma = "#FF6767";//darkred
                            }
                        } else {
                            $paloma = "";
                            if ($fecha_fin_plana > $time_fin) {
                                $stado_tarea = "#67FF80";//darkgreen
                            } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                $stado_tarea = "#FB8C00";
                            } else if ($fecha_fin_plana <= time()){
                                $stado_tarea = "#FF6767";//darkred
                            }
                        }
                    } else {
                        $stado_tarea = "#6FBDFE";//353553 
                    }
                        
                    $row = array(
                        "token_proyecto" => $value->token_proyecto,
                        "date" => $value->fecha,
                        "title" => $folio_proy,
                        "id" => $folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).", Evento: ".$value->evento,
                        "backgroundColor" => $stado_tarea,
                        "textColor" => "black",
                        "start" => $start_calendar,
                        "end" => $end_calendar,
                    );
                    $listCalendarProyectos [] = $row;
                }
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'calendar' => count($listCalendarProyectos),
                    'calendar_proyectos' => $listCalendarProyectos,
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
    
    public function calendarTareasPersonal(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listCalendarProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'pers_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                
                $eventList = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
                ->join("module_proyectos_responsable AS resp_pr","proy.id","=","resp_pr.proyecto")
                ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                ->where(["evnt.area" => 10,"proy.status_proyecto" => TRUE,"pers.pers_token" => $parametrosArray['pers_token'],"emp.emp_token" => $usuario->emp_token])                
                ->whereNotNull('evnt.evt_tarea')
                ->orderBy('evnt.end_time','ASC')->get();
                
                foreach ($eventList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->end_time);
                    $fecha_fin_plana = $value->end_time;

                    $eventTar = EventosModeloConsulta::join("module_proyectos_tareas AS tar","evnt.evt_tarea","=","tar.id")
                    ->where([
                        'evnt.area' => 10,
                        'evnt.evt_tarea' => $value->evt_tarea,
                    ])                
                    ->orderBy('evnt.end_time','ASC')->get();
                    
                    if ($eventTar[0]->post_folio_tar == NULL) {
                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea);
                    } else {
                        $folio_tar = 'TAR-'.$JwtAuth->generarFolio($eventTar[0]->folio_tarea).'-'.$eventTar[0]->post_folio_tar;
                    }
                
                    $start_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->start_time);
                    $end_calendar = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$eventTar[0]->end_time);
                    $fecha_fin_plana = $eventTar[0]->end_time;
                
                    if ($eventTar[0]->realizacion == TRUE) {
                        $semaforo_realizacion = "#67FF80";//darkgreen
                    } else {
                        $time_fin = time()+(86400*5);
                        if ($eventTar[0]->fin_tarea > $time_fin) {
                            $semaforo_realizacion = "#FFBC67";//darkgreen
                        } else if ($eventTar[0]->fin_tarea > time() && $eventTar[0]->fin_tarea < $time_fin) {
                            $semaforo_realizacion = "#FB8C00";
                        } else if ($eventTar[0]->fin_tarea <= time()){
                            $semaforo_realizacion = "#FF6767";//darkred
                        }
                    }
                
                    $row = array(
                        "token_tarea" => $eventTar[0]->token_tarea,
                        "title" => $folio_tar,
                        "id" => "Proyecto ".$folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name).
                            ", Tarea: ".$folio_tar." - ".$JwtAuth->desencriptar($eventTar[0]->tarea_nombre).
                            ", Evento: ".$eventTar[0]->evento,
                        "backgroundColor" => $semaforo_realizacion,
                        "textColor" => "black",
                        "start" => $start_calendar,
                        "end" => $end_calendar,
                    );
                    $listCalendarProyectos [] = $row;
                }
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'calendar' => count($listCalendarProyectos),
                    'calendar_proyectos' => $listCalendarProyectos,
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
    
    public static function registraEventoProyectos($proyecto,$tarea,$tipo_evento,$evento,$fecha,$empresa){
        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
        $patronFecha = '/^[0-9-]*$/';
                
        if (isset($proyecto) && !empty($proyecto) &&
            isset($tipo_evento) && !empty($tipo_evento) &&
            isset($evento) && !empty($evento) && preg_match($patron,$evento) &&
            isset($fecha) && !empty($fecha) && 
            isset($empresa) && !empty($empresa)) {
                        
            if (isset($tarea) && !empty($tarea)) {
                $tarea_ident = $tarea;
            } else {
                $tarea_ident = NULL;
            }
                    
            $creaEvento = new EventosModeloInsert();		
	        $creaEvento->area = 10;
	        $creaEvento->proyecto = $proyecto;
	        $creaEvento->evt_tarea = $tarea;
	        $creaEvento->tipo_evento = $tipo_evento;
	        $creaEvento->evento = $evento; 
	        $creaEvento->start_time = time();
	        $creaEvento->end_time = $fecha;
	        $creaEvento->empresa = $empresa;
	        $saveNewEvent = $creaEvento->save();
			    	
			if ($saveNewEvent) {
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'evento registrado'
                );	    	    
			} else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'registro de evento no completado'
                );
            }
        } else {
            if (!isset($proyecto) || empty($proyecto)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de proyecto, verifique su información'
                );
            }
            if (!isset($tipo_evento) || empty($tipo_evento)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en tipo de evento, verifique su información e intente nuevamente'
                );
            }
            if (!isset($evento) || empty($evento) || !preg_match($patron,$evento)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en registro de titulo de evento, verifique su información'
                );
            }
            if (!isset($fecha) || empty($fecha)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en fechas de evento, verifique su información e intente nuevamente'
                );
            }
            if (!isset($empresa) || empty($empresa)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de empresa, verifique su información e intente nuevamente'
                );
            }
        }
    }
    
    public static function actualizaEventoProyecto($proyecto,$tipo_evento,$evento,$fecha,$empresa){
        //echo $tipo_evento; exit;
        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
        $patronFecha = '/^[0-9-]*$/';
                
        if (isset($proyecto) && !empty($proyecto) &&
            isset($tipo_evento) && !empty($tipo_evento) &&
            isset($evento) && !empty($evento) && preg_match($patron,$evento) &&
            isset($fecha) && !empty($fecha) && 
            isset($empresa) && !empty($empresa)) {
                    
            $selectEvent = DB::select("SELECT id FROM module_proyectos_eventos WHERE proyecto = ?",[$proyecto]);
                    
			$updateEvento = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
	    		->where([
    				"evnt.proyecto" => $proyecto,
    				"evnt.evt_tarea" => NULL,
    				"evnt.empresa" => $empresa,
    			])
	    		->limit(1)->update(
                    array(
                        "evnt.tipo_evento" => $tipo_evento,
	                    "evnt.evento" => $evento, 
                        "evnt.end_time" => $fecha,
                    )
                );
                    
			if ($updateEvento) {
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'Actualización completada'
                );	    	    
			} else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La información que intenta registrar no es valida'
                );
            }
        } else {
            if (!isset($proyecto) || empty($proyecto)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de proyecto, verifique su información'
                );
            }
            if (!isset($tipo_evento) || empty($tipo_evento)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en tipo de evento, verifique su información e intente nuevamente'
                );
            }
            if (!isset($evento) || empty($evento) || !preg_match($patron,$evento)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en registro de titulo de evento, verifique su información'
                );
            }
            if (!isset($fecha) || empty($fecha)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en fechas de evento, verifique su información e intente nuevamente'
                );
            }
            if (!isset($empresa) || empty($empresa)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de empresa, verifique su información e intente nuevamente'
                );
            }
        }
    }
    
    public static function actualizaEventoTarea($proyecto,$tarea,$tipo_evento,$evento,$fecha,$empresa){
        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
        $patronFecha = '/^[0-9-]*$/';
                
        if (isset($proyecto) && !empty($proyecto) &&
            isset($tarea) && !empty($tarea) &&
            isset($tipo_evento) && !empty($tipo_evento) &&
            isset($evento) && !empty($evento) && preg_match($patron,$evento) &&
            isset($fecha) && !empty($fecha) && 
            isset($empresa) && !empty($empresa)) {
                    
			$updateEvento = EventosModeloConsulta::join("module_proyectos AS proy","evnt.proyecto","=","proy.id")
                ->join("main_empresas AS emp","evnt.empresa","=","emp.id")
	    		->where([
    				"evnt.proyecto" => $proyecto,
    				"evnt.evt_tarea" => $tarea,
    				"evnt.empresa" => $empresa,
    			])
	    		->limit(1)->update(
                    array(
                        "evnt.tipo_evento" => $tipo_evento,
	                    "evnt.evento" => $evento, 
                        "evnt.end_time" => $fecha,
                    )
                );
			    	
			if ($updateEvento) {
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'La información que intenta registrar no es valida'
                );	    	    
			} else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La información que intenta registrar no es valida'
                );
            }
        } else {
            if (!isset($proyecto) || empty($proyecto)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de proyecto, verifique su información'
                );
            }
            if (!isset($tarea) || empty($tarea)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de tarea, verifique su información'
                );
            }
            if (!isset($tipo_evento) || empty($tipo_evento)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en tipo de evento, verifique su información e intente nuevamente'
                );
            }
            if (!isset($evento) || empty($evento) || !preg_match($patron,$evento)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en registro de titulo de evento, verifique su información'
                );
            }
            if (!isset($fecha) || empty($fecha)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en fechas de evento, verifique su información e intente nuevamente'
                );
            }
            if (!isset($empresa) || empty($empresa)) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Error en identificación de empresa, verifique su información e intente nuevamente'
                );
            }
        }
    }
    
    public function ganttCompleteProyectos(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $listGanttProyectos = array();
    
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
            ]);
        
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $time_fin = time()+(86400*5);
                $id_pers = DB::select("SELECT pers.id FROM vhum_personal AS pers 
                    JOIN main_usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?",[$usuario->user_token]);
                
                if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
                    $projectList = ModuleProyectosModelo::join("main_empresas AS emp","mod_proy.empresa","=","emp.id")
                    ->join("module_proyectos_responsable AS resp_pr","mod_proy.id","=","resp_pr.proyecto")
                    ->join("vhum_personal AS pers","resp_pr.personal","=","pers.id")
                    ->where(["mod_proy.status_proyecto" => TRUE,"resp_pr.tipo_pp" => 'cr',"emp.emp_token" => $usuario->emp_token])                
                    ->orderBy('mod_proy.folio','DESC')->get();
                    //echo " 22 ";
                } else {//
                    $projectList = DB::select("SELECT * FROM module_proyectos AS proy JOIN main_empresas AS emp WHERE proy.status_proyecto = TRUE AND 
                        proy.empresa = emp.id AND emp.emp_token = ? AND proy.id IN (
                        SELECT proyecto FROM module_proyectos_responsable AS resp_pr JOIN vhum_personal AS pers JOIN main_usuarios as users 
                        WHERE resp_pr.personal = pers.id AND pers.usuario = users.id
                        AND users.user_token = ?) order by folio DESC",[$usuario->emp_token,$usuario->user_token]);
                    //echo " 24 ";
                }
                    
                //foreach ($projectList as $value) {
                
                $count_list = 0;
                foreach ($projectList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $token_proyecto = $value->token_proyecto;
                    
                    if ($value->post_folio == NULL) {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio);
                    } else {
                        $folio_proy = 'PROY-'.$JwtAuth->generarFolio($value->folio).'-'.$value->post_folio;
                    }
                    
                    $proyecto_name = $JwtAuth->desencriptar($value->proyecto_name);
                    
                    $start_gantt = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_inicio);

                    $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                        JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?",[$value->token_proyecto]);
                    if (count($selectRecalendar) > 0) {
                        $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                            WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)",[$value->token_proyecto]);
                        
                        $fecha_fin_plana = $nuevaFechaFin[0]->fecha_compromiso_nueva;
                        $end_gantt = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$nuevaFechaFin[0]->fecha_compromiso_nueva);
                    } else {
                        $fecha_fin_plana = $value->fecha_fin;
                        $end_gantt = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fecha_fin);
                    }
                    
                    $count_list++;
                    $tar_terminadas = 0;
                    $tareaList = DB::table("module_proyectos_tareas AS subtar")
                    ->join("module_proyectos AS tar","subtar.proyecto","=","tar.id")
                    ->where(["subtar.status" => TRUE,"tar.token_proyecto" => $value->token_proyecto,])
                    ->orderBy("subtar.id","DESC")->get();
                     
                    $tar_total = count($tareaList);
                    //echo $tar_total." "; 
                    foreach ($tareaList as $vTar) {
                        if ($vTar->realizacion == TRUE) {
                            $tar_terminadas++;
                        }
                    }
                    
                    if ($tar_terminadas > 0){
                        //$porcent_avance = round(($tar_terminadas / $tar_total) * 100, 2);
                        $porcent_bruto = ($tar_terminadas / $tar_total) * 100;
                        $query_porcent = DB::select("SELECT FORMAT(?,2) AS total",[$porcent_bruto]);
                        $porcent_avance = $query_porcent[0]->total;
                    } else {
                        $porcent_avance = 0;   
                    }
                    
                    if ($tar_total > 0) {
                        if ($tar_total == $tar_terminadas) {
                            $proyecto_status = "ggroupblackcomplete";
                        } else {
                            if ($fecha_fin_plana > $time_fin) {
                                $proyecto_status = "gtaskgreen";
                            } else if ($fecha_fin_plana > time() && $fecha_fin_plana < $time_fin) {
                                $proyecto_status = "gtaskyellow";
                            } else if ($fecha_fin_plana <= time()){
                                $proyecto_status = "gtaskred";
                            }
                        }
                    } else {
                        $proyecto_status = "ggroupblack";
                    }
                    
                    $selectLider = DB::select("SELECT pers.pers_token,people.paterno,
                        people.materno,people.nombre FROM vhum_personal AS pers JOIN sos_personas AS people JOIN 
                        module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                        AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                        AND pers.personal = people.id",[$value->token_proyecto]);
                        
                    if (count($selectLider) == 1) {
                        $token_lider = $selectLider[0]->pers_token;
                        $nombre_lider = ucfirst($JwtAuth->desencriptar($selectLider[0]->paterno))." ".
                            ucfirst($JwtAuth->desencriptar($selectLider[0]->materno))." ".
                            ucfirst($JwtAuth->desencriptar($selectLider[0]->nombre));
                    } else {
                        $selectCr = DB::select("SELECT pers.pers_token,people.paterno,
                        people.materno,people.nombre FROM vhum_personal AS pers JOIN sos_personas AS people JOIN 
                        module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                        AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                        AND pers.personal = people.id",[$value->token_proyecto]);
                        $token_lider = $selectCr[0]->pers_token;
                        $nombre_lider = ucfirst($JwtAuth->desencriptar($selectCr[0]->paterno))." ".
                            ucfirst($JwtAuth->desencriptar($selectCr[0]->materno))." ".
                            ucfirst($JwtAuth->desencriptar($selectCr[0]->nombre));
                    }
                    
                    $rowGantt = array(
                        "token_proyecto" => $value->token_proyecto,
                        "folio_proy" => $folio_proy,
                        "proyecto_name" => $JwtAuth->desencriptar($value->proyecto_name),
                        "pID" => $count_list,
                        "pName" => $folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name),
                        "pStart" => $start_gantt,
                        "pEnd" => $end_gantt,
                        "pClass" => $proyecto_status,
                        //"pLink" => "",
                        "pMile" => 0,
                        "pRes" => $nombre_lider,
                        "pComp" => $porcent_avance,
                        "pGroup" => 1,
                        "pParent" => 0,
                        "pOpen" => 1,
                        "pDepend" => "",
                        "pCaption" => "",
                        
                        //"pNotes" => "Some Notes text"
                        'TaskID' => $count_list,
                        'TaskName' => $folio_proy." - ".$JwtAuth->desencriptar($value->proyecto_name),
                        'StartDate' => $start_gantt,
                        'EndDate' => $end_gantt,
                        'Progress' => $porcent_avance,
                        'subtasks' => [],
                    );
                    $listGanttProyectos [] = $rowGantt;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'gantt' => count($listGanttProyectos),
                    'gantt_proyectos' => $listGanttProyectos,
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