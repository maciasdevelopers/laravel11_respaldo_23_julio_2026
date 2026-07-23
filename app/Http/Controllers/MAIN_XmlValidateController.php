<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\ComprasModelo;
use Exception;
use PhpCfdi\CfdiToJson\JsonConverter;
use PhpCfdi\CfdiToJson\Factory;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

class MAIN_XmlValidateController extends Controller{
  public function construirSoapRequest($emisorRfc, $receptorRfc, $uuid, $total){
    // El monto debe tener exactamente 6 decimales
    $totalFormat = number_format((float)$total, 6, '.', '');

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://tempuri.org/">
        <soapenv:Header/>
        <soapenv:Body>
            <ser:Consulta>
                <ser:expresionImpresa>?re=$emisorRfc&amp;rr=$receptorRfc&amp;tt=$totalFormat&amp;id=$uuid</ser:expresionImpresa>
            </ser:Consulta>
        </soapenv:Body>
    </soapenv:Envelope>
    XML;
  }

  public function consultarEstadoSAT($emisorRfc, $receptorRfc, $uuid, $total){
    $soapRequest = $this->construirSoapRequest($emisorRfc, $receptorRfc, $uuid, $total);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://consultaqr.facturaelectronica.sat.gob.mx/ConsultaCFDIService.svc");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: text/xml; charset=utf-8',
      'SOAPAction: "http://tempuri.org/IConsultaCFDIService/Consulta"'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      throw new Exception('Error en CURL: ' . curl_error($ch));
    }

    curl_close($ch);

    return $response;
  }

  public function extraerEstadoCFDI($soapResponse){
    $xml = simplexml_load_string($soapResponse);

    // Registrar todos los namespaces necesarios
    $xml->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xml->registerXPathNamespace('t', 'http://tempuri.org/');

    // Buscar ConsultaResult (usando el prefijo correcto 't')
    $result = $xml->xpath('//s:Body/t:ConsultaResponse/t:ConsultaResult');

    if (!empty($result)) {
      // Acceder a los datos
      $consultaResult = $result[0];
      // Ahora registramos también el namespace 'a' para leer los hijos
      $consultaResult->registerXPathNamespace('a', 'http://schemas.datacontract.org/2004/07/Sat.Cfdi.Negocio.ConsultaCfdi.Servicio');
      $codigoEstatus = (string) $consultaResult->children('a', true)->CodigoEstatus;
      ///$esCancelable = (string) $xml_obj->xpath("//a:EsCancelable")[0];
      return (string) $consultaResult->xpath("//a:Estado")[0];
      ///$estatusCancelacion = (string) $xml_obj->xpath("//a:EstatusCancelacion")[0];
      ///$validacionEFOS = (string) $xml_obj->xpath("//a:ValidacionEFOS")[0];

      // Mostrar los datos extraídos
      //echo "Código Estatus: " . $codigoEstatus;
      //echo "Es Cancelable: " . $esCancelable . PHP_EOL;
      //echo "Estado: " . $estado . PHP_EOL;
      //echo "Estatus Cancelación: " . ($estatusCancelacion ?: 'No disponible') . PHP_EOL;
      //echo "Validación EFOS: " . $validacionEFOS . PHP_EOL; 
    }
    throw new Exception('No se pudo interpretar la respuesta del SAT.');
  }

  public function validaEstadoXmlCFDIISN(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);

          $uuidQuery = DB::table("cfdi_comprobantes_fiscales AS cfd")
          ->join("cfdi_vinculacion_isn AS vinc_isn", "cfd.id", "=", "vinc_isn.comprobante_fiscal")
          ->join("vhum_nominas_impuestos AS isn", "vinc_isn.isn_vinculado", "=", "isn.id")
          ->join("main_empresas AS emp", "isn.nomina_empresa", "=", "emp.id")
          ->where([
            "cfd.cfdi_complementoUUID" => $uuid,
            "emp.empresa_token" => $usuario->empresa_token,
          ])
          ->count();

          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => $uuidQuery > 0 ? true : false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstadoXmlCFDINomina(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);

          $uuidQuery = DB::table("cfdi_comprobantes_fiscales AS cfd")
          ->join("cfdi_vinculacion_nomina AS vinc_nomi", "cfd.id", "=", "vinc_nomi.comprobante_fiscal")
          ->join("vhum_nominas_main AS nmain", "vinc_nomi.nomina_main", "=", "nmain.id")
          ->join("vhum_nominas_recibos AS nreci", "vinc_nomi.nomina_recibo", "=", "nreci.id")
          ->join("main_empresas AS emp", "nmain.nomina_empresa", "=", "emp.id")
          ->where([
            "cfd.cfdi_complementoUUID" => $uuid,
            "emp.empresa_token" => $usuario->empresa_token,
          ])
          ->count();

          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => $uuidQuery > 0 ? true : false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstadoXmlCFDIReembolsos(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);

          $uuidQuery = DB::table("cfdi_comprobantes_fiscales AS cfd")
		      ->join("cfdi_vinculacion_reembolsos AS vinc_reem", "cfd.id", "=", "vinc_reem.comprobante_fiscal")
		      ->join("terc_reembolso_main AS reem_main", "vinc_reem.reembolso_vinculado_main", "=", "reem_main.id")
		      ->join("terc_reembolso_solicitud AS reem_soli", "vinc_reem.reembolso_vinculado_soli", "=", "reem_soli.id")
          ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
          ->where([
            "reem_main.borrador_reem" => FALSE,
            "reem_soli.status_activacion" => TRUE,
            "cfd.cfdi_complementoUUID" => $uuid,
            "emp.empresa_token" => $usuario->empresa_token,
          ])
          ->count();
          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => $uuidQuery > 0 ? true : false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstadoXmlCFDICompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);

          $uuidQuery = DB::table("cfdi_comprobantes_fiscales AS cfd")
          ->join("cfdi_vinculacion_compras AS vinc_buy", "cfd.id", "=", "vinc_buy.comprobante_fiscal")
          ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
          ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
          ->where([
            "cfd.cfdi_complementoUUID" => $uuid,
            "emp.empresa_token" => $usuario->empresa_token,
          ])
          ->count();

          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => $uuidQuery > 0 ? true : false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstadoXmlCFDIAportacionesIMSS(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);

          $uuidQuery = DB::table("cfdi_comprobantes_fiscales AS cfd")
		      ->join("cfdi_vinculacion_aport_seg_social_imss AS vinc_aport", "cfd.id", "=", "vinc_aport.comprobante_fiscal")
		      ->join("vhum_aportaciones_seguridad_social_main AS aport_social", "vinc_aport.aport_seg_social_vinculado", "=", "aport_social.id")
          ->join("main_empresas AS emp", "aport_social.aport_ssocial_empresa", "=", "emp.id")
          ->where([
            "vinc_aport.comprobante_tipo" => "imss",
            "cfd.cfdi_complementoUUID" => $uuid,
            "emp.empresa_token" => $usuario->empresa_token,
          ])
          ->count();
          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => $uuidQuery > 0 ? true : false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function validaEstadoXmlCFDIDeclaracionesImpFederales(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);

          $uuidQuery = DB::table("cfdi_comprobantes_fiscales AS cfd")
		      ->join("cfdi_vinculacion_declaraciones AS vinc_dec", "cfd.id", "=", "vinc_dec.comprobante_fiscal")
		      ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "vinc_dec.declaracion_vinculada", "=", "fedMain.id")
          ->join("main_empresas AS emp", "fedMain.declaracion_empresa", "=", "emp.id")
          ->where([
            "vinc_aport.comprobante_tipo" => "imss",
            "cfd.cfdi_complementoUUID" => $uuid,
            "emp.empresa_token" => $usuario->empresa_token,
          ])
          ->count();
          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => $uuidQuery > 0 ? true : false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function validaEstructXmlIngresos(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('imagenEvidenciaXMl');

    $arrayErroresComprobante = array();
    $arrayErroresEmisor = array();
    $arrayErroresReceptor = array();
    $arrayErroresCfdiRelacionados = array();
    $arrayListaConceptos = array();
    $arrayListaImpuestosConceptos = array();
    $arrayErroresConceptos = array();
    $arrayImpuestosRetenciones = array();
    $arrayImpuestosTraslados = array();
    $arrayErroresImpuestos = array();
    $arrayErroresComplemento = array();

    $proveedor = $request->input('proveedor');
    $parametros = json_decode($proveedor);
    $parametrosArray = json_decode($proveedor, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'emisor' => 'required|string',
        'receptor' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];

        $schama_tres = "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd";
        $schama_cuatro = "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd";

        $http_cfdi3 = "http://www.sat.gob.mx/cfd/3";
        $http_cfdi4 = "http://www.sat.gob.mx/cfd/4";

        $verifiedCfdiComprobante = "";
        $verifiedCfdiEmisor = "";
        $verifiedCfdiReceptor = "";

        $verifiedCfdiRelacionados = "";
        $verifiedCfdiRelacionadostipoRelacion = "";
        $verifiedCfdiRelacionadosuuid = "";

        $verifiedCfdiConceptos = "";

        $verifiedCfdiImpuestos = "";
        $txttotalImpuestosRetenidos = "";
        $txttotalImpuestosTrasladados = "";

        $verifiedCfdiComplemento = "";

        $dataEmisor = DB::select("SELECT people.rfc FROM sos_personas AS people JOIN main_empresas AS emp 
                    WHERE people.id = emp.persona AND emp.emp_token = ?", [$emisor]);
        $rfc_emisor = strtolower($JwtAuth->desencriptar($dataEmisor[0]->rfc));

        $dataReceptor = DB::table("ingr_catalogo_clientes AS cKli")
          ->join("sos_personas AS client", "cKli.cliente", "=", "client.id")
          ->where(["cKli.token_cat_clientes" => $receptor])->get();
        $rfc_receptor = strtolower($JwtAuth->desencriptar($dataReceptor[0]->rfc));

        $xmlObject = simplexml_load_file($imageServ);

        $ns = $xmlObject->getNamespaces(true);
        $cfdi = $ns['cfdi'];
        $xsi = $ns['xsi'];
        $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

        $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
        $xmlObject->registerXPathNamespace('t', $ns['tfd']);

        //comprabante
        $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
        $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
        $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
        $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
        $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

        $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
        $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
        $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
        $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
        $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
        $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
        $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
        $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad ".$Fecha]);
        if ($comprobante[0]["TipoCambio"] != NULL) {
          $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
        } else {
          $tipoCambio = 'no especificado';
        }

        $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

        if ($comprobante[0]["Confirmacion"] != NULL) {
          $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
        } else {
          $confirmacion = 'no especificado';
        }

        $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
        $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
        $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
        $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

        if (
          isset($cfdi) && !empty($cfdi) && ($cfdi == $http_cfdi3 || $cfdi == $http_cfdi4) &&
          isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" &&
          isset($datSchama) && !empty($datSchama) && ($datSchama == $schama_tres || $datSchama == $schama_cuatro) &&
          isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0") &&
          isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) && strlen($Folio) <= 40 &&
          isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
          isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 &&
          isset($noCertificado) && !empty($noCertificado) &&
          isset($certificado) && !empty($certificado) &&
          isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
          !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
          !empty($TipoDeComprobante) && $TipoDeComprobante == 'I' && isset($MetodoPago) && !empty($MetodoPago) &&
          strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
        ) {

          if ($Moneda != 'MXN' && $Moneda != 'XXX') {
            if (
              isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
              $comprobante[0]["TipoCambio"] != NULL
            ) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "TipoCambio",
                "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                "correccion" => "agregar o verificar atributo TipoCambio"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }

          if ($comprobante[0]["Confirmacion"]) {
            if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "Confirmacion",
                "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                "correccion" => "agregar o verificar atributo Confirmacion"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }
        } else {
          $verifiedCfdiComprobante = 'false';
          if (!isset($cfdi) || empty($cfdi) || ($cfdi != $http_cfdi3 && $cfdi != $http_cfdi4)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:cfdi",
              "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "' . $http_cfdi3 . '" ó "' . $http_cfdi4 . '"',
              "correccion" => "agregar o verificar atributo xmlns:cfdi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:xsi",
              "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
              "correccion" => "agregar o verificar atributo xmlns:xsi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($datSchama) || empty($datSchama) || ($datSchama != $schama_tres && $datSchama != $schama_cuatro)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xsi:schemaLocation",
              "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a "' . $schama_tres . '" ó "' . $schama_cuatro . '"',
              "correccion" => "agregar o verificar atributo xsi:schemaLocation"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (
            !isset($version) || empty($version) ||
            ($version != "3.3" && $version != "4.0")
          ) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Version",
              "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
              "correccion" => "agregar o verificar atributo Version"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Serie",
              "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
              "correccion" => "agregar o verificar atributo Serie"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Folio",
              "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
              "correccion" => "agregar o verificar atributo Folio"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Fecha",
              "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
              "correccion" => "agregar o verificar atributo Fecha"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Sello) || empty($Sello)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Sello",
              "mensaje" => "el atributo Sello no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Sello"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "FormaPago",
              "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
              "correccion" => "agregar o verificar atributo FormaPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($noCertificado) || empty($noCertificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "NoCertificado",
              "mensaje" => "el atributo NoCertificado no existe o esta vacio",
              "correccion" => "agregar o verificar atributo NoCertificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($certificado) || empty($certificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Certificado",
              "mensaje" => "el atributo Certificado no existeo o esta vacio",
              "correccion" => "agregar o verificar atributo Certificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($SubTotal) || empty($SubTotal)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "SubTotal",
              "mensaje" => "el atributo SubTotal no existe,esta vacio",
              "correccion" => "agregar o verificar atributo SubTotal"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Moneda",
              "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo Moneda"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Total) || empty($Total)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Total",
              "mensaje" => "el atributo Total no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Total"
            );
            $arrayErroresComprobante[] = $arrayError;
            $mensajeError = 'nodo Total incorrecto';
          }
          if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'I') {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "TipoComprobante",
              "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
              "correccion" => "agregar o verificar atributo TipoComprobante"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "MetodoPago",
              "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo MetodoPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "LugarExpedicion",
              "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
              "correccion" => "agregar o verificar atributo LugarExpedicion"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
        }

        //nodo CfdiRelacionados
        $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
        if ($CfdiRelacionados) {
          if (!empty($CfdiRelacionados)) {
            $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
            $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
            $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
            if (
              isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
              isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
              isset($uuid) && !empty($uuid)
            ) {
              $verifiedCfdiRelacionados = 'true';
              $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
              $verifiedCfdiRelacionadosuuid = $uuid;
            } else {
              $verifiedCfdiRelacionados = 'false';
              if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "TipoRelacion",
                  "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                  "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($uuid) || empty($uuid)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "UUID",
                  "mensaje" => "el nodo UUID no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
            }
          } else {
            $arrayError = array(
              "nodo" => "CfdiRelacionados",
              "atributo_nodohijo" => "---",
              "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
              "correccion" => "---"
            );
            $arrayErroresCfdiRelacionados[] = $arrayError;
            $verifiedCfdiRelacionados = 'false';
          }
        } else {
          $verifiedCfdiRelacionados = 'true';
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad2 ".$Fecha]);

        //nodo emisor
        $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
        $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
        $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
        $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad3 ".$Fecha]);

        if (
          isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 && strlen($RfcEmi) <= 13 &&
          $RfcEmi == $rfc_emisor &&
          isset($nombre) &&
          !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3
        ) {
          $verifiedCfdiEmisor = 'true';
        } else {
          $verifiedCfdiEmisor = 'false';
          if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if ($RfcEmi != $rfc_emisor) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
              "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($nombre) || empty($nombre)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Nombre",
              "mensaje" => "el atributo Nombre no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Nombre"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "RegimenFiscal",
              "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo RegimenFiscal"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad4 ".$Fecha]);

        //nodo receptor
        $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
        $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
        $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
        $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);

        if (
          isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) &&
          $RfcRec == $rfc_receptor && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3
        ) {
          $verifiedCfdiReceptor = 'true';
        } else {
          $verifiedCfdiReceptor = 'false';
          if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if ($RfcRec != $rfc_receptor) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
              "correccion" => "el rfc de su empresa debe ser " . $rfc_company
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "UsoCFDI",
              "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo UsoCFDI"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad5 ".$Fecha]);

        //nodo conceptos
        $countConceptos = 0;
        $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
        $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
        if (isset($conceptos) && !empty($conceptos)) {
          for ($i = 0; $i < count($forConcepto); $i++) {
            $verifiedCfdiConceptosConcepto = "";
            $verifiedCfdiConceptosImpuestos = "";
            $verifiedCfdiConceptosImpuestosRetenciones = "";
            $verifiedCfdiConceptosImpuestosTraslados = "";

            $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
            $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
            $resultnoIdentificacion = "";
            $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
            $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
            $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
            $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
            $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
            $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
            $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
            $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

            if (
              isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8
              && isset($cantidad) && !empty($cantidad)
              && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3
              && isset($unidad) && !empty($unidad)
              && isset($descripcion) && !empty($descripcion)
              && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6
              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
            ) {
              if (isset($noIdentificacion)) {
                if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                  $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                  $verifiedCfdiConceptosConcepto = 'true';
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "NoIdentificacion",
                    "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                    "correccion" => "agregar o verificar nodo NoIdentificacion"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosConcepto = 'true';
              }

              if (isset($forConcepto[$i]["Descuento"])) {
                $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                  $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                } else {
                  $verifiedCfdiConceptosDescuento = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "Descuento",
                    "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                    "correccion" => "agregar o verificar nodo Descuento"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosDescuento = 'true';
                $resultDescuento = '---';
              }

              $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida WHERE sat_clave = ?", [$claveUnidad]);

              if ($verifiedCfdiConceptosConcepto == 'true') {
                //nodo impuestos
                $arrayImpuestosCncRetenciones = array();
                $arrayImpuestosCncTraslados = array();
                $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                if ($impuestos) {
                  if (isset($impuestos) && !empty($impuestos)) {
                    $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                    if ($retenciones) {
                      if (!empty($retenciones)) {
                        $countRetencion = 0;
                        $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                        if (isset($retencion) && !empty($retencion)) {
                          foreach ($retencion as $forRetencion) {
                            $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);

                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countRetencion;
                              $arrayRetencionFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countRetencion == count($retencion)) {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                            "mensaje" => "el nodo Retencion no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Retenciones",
                          "mensaje" => "el nodo Retenciones no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                    }
                    $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                    $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                    if ($traslados) {
                      if (!empty($traslados)) {
                        $countTraslado = 0;
                        $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                        if (isset($traslado) && !empty($traslado)) {
                          foreach ($traslado as $forTtraslado) {
                            $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);
                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countTraslado;
                              $arrayTrasladoFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countTraslado == count($traslado)) {
                            $verifiedCfdiConceptosImpuestosTraslados = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Traslados Traslado",
                            "mensaje" => "el nodo Traslado no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosTraslados = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Traslados",
                          "mensaje" => "el nodo Traslados no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                    }
                    $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    if (
                      $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                      $verifiedCfdiConceptosImpuestosTraslados == 'true'
                    ) {
                      $verifiedCfdiConceptosImpuestos = 'true';
                    }
                  } else {
                    $verifiedCfdiConceptosImpuestos = 'false';
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Impuestos",
                      "mensaje" => "el nodo Impuestos no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                } else {
                  $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                  $verifiedCfdiConceptosImpuestosTraslados = 'true';
                  $verifiedCfdiConceptosImpuestos = 'true';
                  $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                  $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                }
              }
              if (
                $verifiedCfdiConceptosConcepto == 'true' &&
                $verifiedCfdiConceptosDescuento == 'true' &&
                $verifiedCfdiConceptosImpuestos == 'true' &&
                $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                $verifiedCfdiConceptosImpuestosTraslados == 'true'
              ) {

                ++$countConceptos;
                $arrayforeachConcept = array(
                  "claveProdServ" => $claveProdServ,
                  "noIdentificacion" => $resultnoIdentificacion,
                  "cantidad" => $cantidad,
                  "claveUnidad" => $claveUnidad,
                  "unidad" => $unidad,
                  "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                  "descripcion" => $descripcion,
                  "valorUnitario" => $valorUnitario,
                  "importe" => $importe,
                  "descuento" => $resultDescuento,
                  "impuestos" => $arrayListaImpuestosConceptos,
                );
                $arrayListaConceptos[] = $arrayforeachConcept;
              }
            } else {
              $verifiedCfdiConceptosConcepto = 'false';
              if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveProdServ",
                  "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo ClaveProdServ"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($cantidad) || empty($cantidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Cantidad",
                  "mensaje" => "el atributo Cantidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Cantidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveUnidad",
                  "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                  "correccion" => "agregar o verificar nodo ClaveUnidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($unidad) || empty($unidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Unidad",
                  "mensaje" => "el atributo Unidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Unidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($descripcion) || empty($descripcion)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Descripcion",
                  "mensaje" => "el atributo Descripcion no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Descripcion"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ValorUnitario",
                  "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo ValorUnitario"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Importe",
                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo Importe"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
            }
          }

          if ($countConceptos == count($forConcepto)) {
            $verifiedCfdiConceptos = 'true';
          }
        } else {
          $verifiedCfdiConceptos = 'false';
          $arrayError = array(
            "nodo" => "Conceptos",
            "atributo_nodohijo" => "---",
            "mensaje" => "el nodo Conceptos no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Conceptos"
          );
          $arrayErroresConceptos[] = $arrayError;
        }

        //nodo impuestos
        $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
        if ($impuestosCfdi && count($impuestosCfdi) > 0) {
          if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
            $verifiedCfdiImpuestosRetenciones = "";
            $verifiedCfdiImpuestosRetencionesRetencion = "";
            $verifiedCfdiImpuestosTraslados = "";
            $verifiedCfdiImpuestosTrasladosTraslado = "";
            $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
            if ($retenciones) {
              $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
              if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                $countRetenidoImp = 0;
                $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                if (isset($retencion) && !empty($retencion)) {
                  foreach ($retencion as $forRetencion) {
                    if (isset($forRetencion["Base"])) {
                      $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);

                    if (isset($forRetencion["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forRetencion["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forRetencion["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forRetencion["Importe"])) {
                      $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                      && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countRetenidoImp;
                      $arrayTrasladoFor = array(
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }
                  if ($countRetenidoImp == count($retencion)) {
                    $verifiedCfdiImpuestosRetenciones = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones Retencion",
                    "mensaje" => "el nodo Retencion no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosRetenciones = 'false';
                if (empty($retenciones)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones",
                    "mensaje" => "el nodo Retenciones no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosRetenidos",
                    "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosRetenciones = 'true';
            }
            $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

            $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
            if ($traslados) {
              $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
              if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                $countTrasladoImp = 0;
                $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (isset($traslado) && !empty($traslado)) {
                  foreach ($traslado as $forTtraslado) {
                    if (isset($forTtraslado["Base"])) {
                      $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);
                    if (isset($forTtraslado["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forTtraslado["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forTtraslado["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forTtraslado["Importe"])) {
                      $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                      isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countTrasladoImp;
                      $arrayTrasladoFor = array(
                        "Base" => $base,
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }

                  if ($countTrasladoImp == count($traslado)) {
                    $verifiedCfdiImpuestosTraslados = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados Traslado",
                    "mensaje" => "el nodo Traslado no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosTraslados = 'false';
                if (empty($traslados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados",
                    "mensaje" => "el nodo Traslados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosTrasladados",
                    "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosTraslados = 'true';
            }
            $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

            if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
              $verifiedCfdiImpuestos = 'true';
            }
          } else {
            $verifiedCfdiImpuestos = 'false';
            $arrayError = array(
              "nodo" => "Impuestos",
              "atributo/nodohijo" => "---",
              "mensaje" => "el nodo Impuestos no existe o esta vacio",
              "correccion" => "agregar o verificar nodo Impuestos"
            );
            $arrayErroresImpuestos[] = $arrayError;
          }
        } else {
          $verifiedCfdiImpuestos = 'true';
        }

        //nodo complemento
        $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
        $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
        $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
        $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
        $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
        $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
        $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

        if (isset($complemento) && !empty($complemento)) {
          if (
            isset($uuidComplemento) && !empty($uuidComplemento)
            && isset($fechaTimbrado) && !empty($fechaTimbrado)
            && isset($RfcProvCertif) && !empty($RfcProvCertif)
            && isset($SelloCFD) && !empty($SelloCFD)
            && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT)
            && isset($SelloSAT) && !empty($SelloSAT)
          ) {
            $verifiedCfdiComplemento = 'true';
          } else {
            $verifiedCfdiComplemento = 'false';
            if (!isset($uuidComplemento) || empty($uuidComplemento)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "UUID",
                "mensaje" => "el atributo UUID no existe o esta vacio",
                "correccion" => "agregar o verificar atributo UUID"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "FechaTimbrado",
                "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                "correccion" => "agregar o verificar atributo FechaTimbrado"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "RfcProvCertif",
                "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                "correccion" => "agregar o verificar atributo RfcProvCertif"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($SelloCFD) || empty($SelloCFD)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloCFD",
                "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloCFD"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloCFD incorrecto';
            }
            if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "NoCertificadoSAT",
                "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo NoCertificadoSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
            }
            if (!isset($SelloSAT) || empty($SelloSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloSAT",
                "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloSAT incorrecto';
            }
          }
        } else {
          $verifiedCfdiComplemento = 'false';
          $arrayError = array(
            "nodo" => "Complemento",
            "atributo_nodohijo" => "TimbreFiscalDigital",
            "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
          );
          $arrayErroresComplemento[] = $arrayError;
        }

        if (
          $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
          $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
          $verifiedCfdiComplemento == 'true'
        ) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'xml valido',
            //informacion del xml
            //comprobante
            'version' => $version,
            'serie' => $serie,
            'Folio' => $Folio,
            'Fecha' => $Fecha,
            'Sello' => $Sello,
            'formaPago' => $formaPago,
            'tokenformaPago' => $selectFpago[0]->token_formapago,
            'noCertificado' => $noCertificado,
            'certificado' => $certificado,
            'SubTotal' => $SubTotal,
            'Moneda' => $Moneda,
            'tokenMoneda' => $selectMoneda[0]->token_monedas,
            'tipoCambio' => $tipoCambio,
            'Total' => $Total,
            'confirmacion' => $confirmacion,
            'TipoDeComprobante' => $TipoDeComprobante,
            'MetodoPago' => $MetodoPago,
            'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
            'LugarExpedicion' => $LugarExpedicion,
            //comprobante
            'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
            'uuid' => $verifiedCfdiRelacionadosuuid,
            //emisor
            'emisorRfc' => $RfcEmi,
            'emisorNombre' => $nombre,
            'emisorRegimenFiscal' => $regimenFiscal,
            //receptor
            'receptorRfc' => $RfcRec,
            'receptorUsoCFDI' => $UsoCFDI,
            'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
            //conceptos    
            'conceptos' => $arrayListaConceptos,
            //impuestos    
            'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
            'impuestosRetenciones' => $arrayImpuestosRetenciones,
            'impuestosTraslados' => $arrayImpuestosTraslados,
            //complemento 
            'compluuidComplemento' => $uuidComplemento,
            'complfechaTimbrado' => $fechaTimbrado,
            'complRfcProvCertif' => $RfcProvCertif,
            'complSelloCFD' => $SelloCFD,
            'complNoCertificadoSAT' => $NoCertificadoSAT,
            'complSelloSAT' => $SelloSAT,
          );
        } else {
          $dataMensaje = array(
            'status' => 'errorValidate',
            'code' => 200,
            'arrayErroresComprobante' => $arrayErroresComprobante,
            'arrayErroresEmisor' => $arrayErroresEmisor,
            'arrayErroresReceptor' => $arrayErroresReceptor,
            'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
            'arrayErroresConceptos' => $arrayErroresConceptos,
            'arrayErroresImpuestos' => $arrayErroresImpuestos,
            'arrayErroresComplemento' => $arrayErroresComplemento,
            'message' => 'xml invalido, revise informe de errores',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstructXmlEgresos(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('imagenEvidenciaXMl');
    $proveedor = $request->input('json');
    $parametros = json_decode($proveedor);
    $parametrosArray = json_decode($proveedor, true);
    //return response()->json(['status' => 'error','code' => 200,'message' => $proveedor]);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "proveedor_token" => "required|string",
        "proveedor_rfc" => "required|string",
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Proveedor invalido',
          'errors' => $validate->errors()
        );
      } else {
        $arrayErroresComprobante = array();
        $arrayErroresEmisor = array();
        $arrayErroresReceptor = array();
        $arrayErroresCfdiRelacionados = array();
        $arrayListaConceptos = array();
        $arrayListaImpuestosConceptos = array();
        $arrayErroresConceptos = array();
        $arrayImpuestosRetenciones = array();
        $arrayImpuestosTraslados = array();
        $arrayErroresImpuestos = array();
        $arrayErroresComplemento = array();

        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $proveedor_token = $parametrosArray['proveedor_token'];
        $proveedor_rfc = $parametrosArray['proveedor_rfc'];
        //return response()->json(['status' => 'error','code' => 200,'message' => $proveedor_rfc]);
        $prvData = DB::table("eegr_catalogo_proveedores AS catprov")
          ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
          ->where(["catprov.token_cat_proveedores" => $proveedor_token])->get();

        $rfc_prov = $prvData[0]->rfc != NULL ? strtolower($JwtAuth->desencriptar($prvData[0]->rfc)) : "---";
        //return response()->json(['status' => 'error','code' => 200,'message' => $rfc_prov]);

        $schama_tres = "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd";
        $schama_cuatro = "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd";

        $http_cfdi3 = "http://www.sat.gob.mx/cfd/3";
        $http_cfdi4 = "http://www.sat.gob.mx/cfd/4";

        $verifiedCfdiComprobante = "";
        $verifiedCfdiEmisor = "";
        $verifiedCfdiReceptor = "";

        $verifiedCfdiRelacionados = "";
        $verifiedCfdiRelacionadostipoRelacion = "";
        $verifiedCfdiRelacionadosuuid = "";

        $verifiedCfdiConceptos = "";

        $verifiedCfdiImpuestos = "";
        $txttotalImpuestosRetenidos = "";
        $txttotalImpuestosTrasladados = "";

        $verifiedCfdiComplemento = "";

        $dataEmpresa = DB::select("SELECT people.rfc FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresapersonal AS emppers 
                    JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE people.id = emp.persona AND emp.emp_token = ? AND emp.id = emppers.empresa
                    AND emppers.personal = pers.id AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);
        $rfc_company = strtolower($JwtAuth->desencriptar($dataEmpresa[0]->rfc));

        $xmlObject = simplexml_load_file($imageServ);

        $ns = $xmlObject->getNamespaces(true);
        $cfdi = $ns['cfdi'];
        $xsi = $ns['xsi'];
        $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

        $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
        $xmlObject->registerXPathNamespace('t', $ns['tfd']);

        //comprabante
        $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
        $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
        $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
        $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
        $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

        $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
        $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
        $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
        $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
        $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
        $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
        $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
        $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

        if ($comprobante[0]["TipoCambio"] != NULL) {
          $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
        } else {
          $tipoCambio = 'no especificado';
        }

        $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

        if ($comprobante[0]["Confirmacion"] != NULL) {
          $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
        } else {
          $confirmacion = 'no especificado';
        }

        $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
        $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
        $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
        $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

        if (
          isset($cfdi) && !empty($cfdi) && ($cfdi == $http_cfdi3 || $cfdi == $http_cfdi4) &&
          isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" &&
          isset($datSchama) && !empty($datSchama) && ($datSchama == $schama_tres || $datSchama == $schama_cuatro) &&
          isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0") &&
          isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) &&
          strlen($Folio) <= 40 && isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
          isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 && isset($noCertificado) && !empty($noCertificado) &&
          isset($certificado) && !empty($certificado) && isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
          !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
          !empty($TipoDeComprobante) && $TipoDeComprobante == 'E' && isset($MetodoPago) && !empty($MetodoPago) &&
          strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
        ) {

          if ($Moneda != 'MXN' && $Moneda != 'XXX') {
            if (
              isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
              $comprobante[0]["TipoCambio"] != NULL
            ) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "TipoCambio",
                "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                "correccion" => "agregar o verificar atributo TipoCambio"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }

          if ($comprobante[0]["Confirmacion"]) {
            if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "Confirmacion",
                "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                "correccion" => "agregar o verificar atributo Confirmacion"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }
        } else {
          $verifiedCfdiComprobante = 'false';
          if (!isset($cfdi) || empty($cfdi) || ($cfdi != $http_cfdi3 && $cfdi != $http_cfdi4)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:cfdi",
              "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "' . $http_cfdi3 . '" ó "' . $http_cfdi4 . '"',
              "correccion" => "agregar o verificar atributo xmlns:cfdi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:xsi",
              "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
              "correccion" => "agregar o verificar atributo xmlns:xsi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($datSchama) || empty($datSchama) || ($datSchama != $schama_tres && $datSchama != $schama_cuatro)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xsi:schemaLocation",
              "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a  "' . $schama_tres . '" ó "' . $schama_cuatro . '"',
              "correccion" => "agregar o verificar atributo xsi:schemaLocation"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (
            !isset($version) || empty($version) ||
            ($version != "3.3" && $version != "4.0")
          ) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Version",
              "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
              "correccion" => "agregar o verificar atributo Version"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Serie",
              "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
              "correccion" => "agregar o verificar atributo Serie"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Folio",
              "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
              "correccion" => "agregar o verificar atributo Folio"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Fecha",
              "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
              "correccion" => "agregar o verificar atributo Fecha"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Sello) || empty($Sello)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Sello",
              "mensaje" => "el atributo Sello no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Sello"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "FormaPago",
              "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
              "correccion" => "agregar o verificar atributo FormaPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($noCertificado) || empty($noCertificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "NoCertificado",
              "mensaje" => "el atributo NoCertificado no existe o esta vacio",
              "correccion" => "agregar o verificar atributo NoCertificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($certificado) || empty($certificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Certificado",
              "mensaje" => "el atributo Certificado no existeo o esta vacio",
              "correccion" => "agregar o verificar atributo Certificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($SubTotal) || empty($SubTotal)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "SubTotal",
              "mensaje" => "el atributo SubTotal no existe,esta vacio",
              "correccion" => "agregar o verificar atributo SubTotal"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Moneda",
              "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo Moneda"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Total) || empty($Total)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Total",
              "mensaje" => "el atributo Total no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Total"
            );
            $arrayErroresComprobante[] = $arrayError;
            $mensajeError = 'nodo Total incorrecto';
          }
          if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'I') {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "TipoComprobante",
              "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
              "correccion" => "agregar o verificar atributo TipoComprobante"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "MetodoPago",
              "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo MetodoPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "LugarExpedicion",
              "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
              "correccion" => "agregar o verificar atributo LugarExpedicion"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
        }

        //nodo CfdiRelacionados
        $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
        if ($CfdiRelacionados) {
          if (!empty($CfdiRelacionados)) {
            $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
            $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
            $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
            if (
              isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
              isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
              isset($uuid) && !empty($uuid)
            ) {
              $verifiedCfdiRelacionados = 'true';
              $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
              $verifiedCfdiRelacionadosuuid = $uuid;
            } else {
              $verifiedCfdiRelacionados = 'false';
              if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "TipoRelacion",
                  "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                  "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($uuid) || empty($uuid)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "UUID",
                  "mensaje" => "el nodo UUID no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
            }
          } else {
            $arrayError = array(
              "nodo" => "CfdiRelacionados",
              "atributo_nodohijo" => "---",
              "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
              "correccion" => "---"
            );
            $arrayErroresCfdiRelacionados[] = $arrayError;
            $verifiedCfdiRelacionados = 'false';
          }
        } else {
          $verifiedCfdiRelacionados = 'true';
        }

        //nodo emisor
        $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
        $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
        $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
        $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];

        if (
          isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 &&
          strlen($RfcEmi) <= 13 && $RfcEmi == $rfc_prov && isset($nombre) &&
          !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3
        ) {
          $verifiedCfdiEmisor = 'true';
        } else {
          $verifiedCfdiEmisor = 'false';
          if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if ($RfcEmi != $rfc_prov) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
              "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($nombre) || empty($nombre)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Nombre",
              "mensaje" => "el atributo Nombre no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Nombre"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "RegimenFiscal",
              "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo RegimenFiscal"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
        }

        //nodo receptor
        $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
        $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
        $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
        $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);
        if (
          isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) &&
          $RfcRec == $rfc_company && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3
        ) {
          $verifiedCfdiReceptor = 'true';
        } else {
          $verifiedCfdiReceptor = 'false';
          if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if ($RfcRec != $rfc_company) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
              "correccion" => "el rfc de su empresa debe ser " . $rfc_company
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "UsoCFDI",
              "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo UsoCFDI"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
        }

        //nodo conceptos
        $countConceptos = 0;
        $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
        $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
        if (isset($conceptos) && !empty($conceptos)) {
          for ($i = 0; $i < count($forConcepto); $i++) {
            $verifiedCfdiConceptosConcepto = "";
            $verifiedCfdiConceptosImpuestos = "";
            $verifiedCfdiConceptosImpuestosRetenciones = "";
            $verifiedCfdiConceptosImpuestosTraslados = "";

            $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
            $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
            $resultnoIdentificacion = "";
            $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
            $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
            $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
            $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
            $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
            $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
            $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
            $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

            if (
              isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8
              && isset($cantidad) && !empty($cantidad)
              && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3
              && isset($unidad) && !empty($unidad)
              && isset($descripcion) && !empty($descripcion)
              && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6
              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
            ) {
              if (isset($noIdentificacion)) {
                if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                  $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                  $verifiedCfdiConceptosConcepto = 'true';
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "NoIdentificacion",
                    "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                    "correccion" => "agregar o verificar nodo NoIdentificacion"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosConcepto = 'true';
              }

              if (isset($forConcepto[$i]["Descuento"])) {
                $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                  $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                } else {
                  $verifiedCfdiConceptosDescuento = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "Descuento",
                    "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                    "correccion" => "agregar o verificar nodo Descuento"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosDescuento = 'true';
                $resultDescuento = '---';
              }

              $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida WHERE sat_clave = ?", [$claveUnidad]);

              if ($verifiedCfdiConceptosConcepto == 'true') {
                //nodo impuestos
                $arrayImpuestosCncRetenciones = array();
                $arrayImpuestosCncTraslados = array();
                $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                if ($impuestos) {
                  if (isset($impuestos) && !empty($impuestos)) {
                    $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                    if ($retenciones) {
                      if (!empty($retenciones)) {
                        $countRetencion = 0;
                        $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                        if (isset($retencion) && !empty($retencion)) {
                          foreach ($retencion as $forRetencion) {
                            $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);

                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countRetencion;
                              $arrayRetencionFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countRetencion == count($retencion)) {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                            "mensaje" => "el nodo Retencion no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Retenciones",
                          "mensaje" => "el nodo Retenciones no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                    }
                    $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                    $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                    if ($traslados) {
                      if (!empty($traslados)) {
                        $countTraslado = 0;
                        $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                        if (isset($traslado) && !empty($traslado)) {
                          foreach ($traslado as $forTtraslado) {
                            $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);
                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countTraslado;
                              $arrayTrasladoFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countTraslado == count($traslado)) {
                            $verifiedCfdiConceptosImpuestosTraslados = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Traslados Traslado",
                            "mensaje" => "el nodo Traslado no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosTraslados = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Traslados",
                          "mensaje" => "el nodo Traslados no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                    }
                    $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    if (
                      $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                      $verifiedCfdiConceptosImpuestosTraslados == 'true'
                    ) {
                      $verifiedCfdiConceptosImpuestos = 'true';
                    }
                  } else {
                    $verifiedCfdiConceptosImpuestos = 'false';
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Impuestos",
                      "mensaje" => "el nodo Impuestos no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                } else {
                  $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                  $verifiedCfdiConceptosImpuestosTraslados = 'true';
                  $verifiedCfdiConceptosImpuestos = 'true';
                  $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                  $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                }
              }
              if (
                $verifiedCfdiConceptosConcepto == 'true' &&
                $verifiedCfdiConceptosDescuento == 'true' &&
                $verifiedCfdiConceptosImpuestos == 'true' &&
                $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                $verifiedCfdiConceptosImpuestosTraslados == 'true'
              ) {

                ++$countConceptos;
                $arrayforeachConcept = array(
                  "claveProdServ" => $claveProdServ,
                  "noIdentificacion" => $resultnoIdentificacion,
                  "cantidad" => $cantidad,
                  "claveUnidad" => $claveUnidad,
                  "unidad" => $unidad,
                  "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                  "descripcion" => $descripcion,
                  "valorUnitario" => $valorUnitario,
                  "importe" => $importe,
                  "descuento" => $resultDescuento,
                  "impuestos" => $arrayListaImpuestosConceptos,
                );
                $arrayListaConceptos[] = $arrayforeachConcept;
              }
            } else {
              $verifiedCfdiConceptosConcepto = 'false';
              if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveProdServ",
                  "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo ClaveProdServ"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($cantidad) || empty($cantidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Cantidad",
                  "mensaje" => "el atributo Cantidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Cantidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveUnidad",
                  "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                  "correccion" => "agregar o verificar nodo ClaveUnidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($unidad) || empty($unidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Unidad",
                  "mensaje" => "el atributo Unidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Unidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($descripcion) || empty($descripcion)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Descripcion",
                  "mensaje" => "el atributo Descripcion no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Descripcion"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ValorUnitario",
                  "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo ValorUnitario"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Importe",
                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo Importe"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
            }
          }

          if ($countConceptos == count($forConcepto)) {
            $verifiedCfdiConceptos = 'true';
          }
        } else {
          $verifiedCfdiConceptos = 'false';
          $arrayError = array(
            "nodo" => "Conceptos",
            "atributo_nodohijo" => "---",
            "mensaje" => "el nodo Conceptos no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Conceptos"
          );
          $arrayErroresConceptos[] = $arrayError;
        }

        //nodo impuestos
        $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
        if ($impuestosCfdi && count($impuestosCfdi) > 0) {
          if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
            $verifiedCfdiImpuestosRetenciones = "";
            $verifiedCfdiImpuestosRetencionesRetencion = "";
            $verifiedCfdiImpuestosTraslados = "";
            $verifiedCfdiImpuestosTrasladosTraslado = "";
            $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
            if ($retenciones) {
              $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
              if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                $countRetenidoImp = 0;
                $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                if (isset($retencion) && !empty($retencion)) {
                  foreach ($retencion as $forRetencion) {
                    if (isset($forRetencion["Base"])) {
                      $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);

                    if (isset($forRetencion["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forRetencion["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forRetencion["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forRetencion["Importe"])) {
                      $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                      && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countRetenidoImp;
                      $arrayTrasladoFor = array(
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }
                  if ($countRetenidoImp == count($retencion)) {
                    $verifiedCfdiImpuestosRetenciones = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones Retencion",
                    "mensaje" => "el nodo Retencion no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosRetenciones = 'false';
                if (empty($retenciones)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones",
                    "mensaje" => "el nodo Retenciones no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosRetenidos",
                    "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosRetenciones = 'true';
            }
            $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

            $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
            if ($traslados) {
              $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
              if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                $countTrasladoImp = 0;
                $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (isset($traslado) && !empty($traslado)) {
                  foreach ($traslado as $forTtraslado) {
                    if (isset($forTtraslado["Base"])) {
                      $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);
                    if (isset($forTtraslado["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forTtraslado["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forTtraslado["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forTtraslado["Importe"])) {
                      $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                      isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countTrasladoImp;
                      $arrayTrasladoFor = array(
                        "Base" => $base,
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }

                  if ($countTrasladoImp == count($traslado)) {
                    $verifiedCfdiImpuestosTraslados = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados Traslado",
                    "mensaje" => "el nodo Traslado no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosTraslados = 'false';
                if (empty($traslados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados",
                    "mensaje" => "el nodo Traslados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosTrasladados",
                    "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosTraslados = 'true';
            }
            $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

            if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
              $verifiedCfdiImpuestos = 'true';
            }
          } else {
            $verifiedCfdiImpuestos = 'false';
            $arrayError = array(
              "nodo" => "Impuestos",
              "atributo/nodohijo" => "---",
              "mensaje" => "el nodo Impuestos no existe o esta vacio",
              "correccion" => "agregar o verificar nodo Impuestos"
            );
            $arrayErroresImpuestos[] = $arrayError;
          }
        } else {
          $verifiedCfdiImpuestos = 'true';
        }

        //nodo complemento
        $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
        $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
        $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
        $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
        $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
        $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
        $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

        if (isset($complemento) && !empty($complemento)) {
          if (
            isset($uuidComplemento) && !empty($uuidComplemento)
            && isset($fechaTimbrado) && !empty($fechaTimbrado)
            && isset($RfcProvCertif) && !empty($RfcProvCertif)
            && isset($SelloCFD) && !empty($SelloCFD)
            && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT)
            && isset($SelloSAT) && !empty($SelloSAT)
          ) {
            $verifiedCfdiComplemento = 'true';
          } else {
            $verifiedCfdiComplemento = 'false';
            if (!isset($uuidComplemento) || empty($uuidComplemento)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "UUID",
                "mensaje" => "el atributo UUID no existe o esta vacio",
                "correccion" => "agregar o verificar atributo UUID"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "FechaTimbrado",
                "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                "correccion" => "agregar o verificar atributo FechaTimbrado"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "RfcProvCertif",
                "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                "correccion" => "agregar o verificar atributo RfcProvCertif"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($SelloCFD) || empty($SelloCFD)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloCFD",
                "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloCFD"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloCFD incorrecto';
            }
            if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "NoCertificadoSAT",
                "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo NoCertificadoSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
            }
            if (!isset($SelloSAT) || empty($SelloSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloSAT",
                "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloSAT incorrecto';
            }
          }
        } else {
          $verifiedCfdiComplemento = 'false';
          $arrayError = array(
            "nodo" => "Complemento",
            "atributo_nodohijo" => "TimbreFiscalDigital",
            "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
          );
          $arrayErroresComplemento[] = $arrayError;
        }

        if (
          $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
          $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
          $verifiedCfdiComplemento == 'true'
        ) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'xml valido',
            //informacion del xml
            //comprobante
            'version' => $version,
            'serie' => $serie,
            'Folio' => $Folio,
            'Fecha' => $Fecha,
            'Sello' => $Sello,
            'formaPago' => $formaPago,
            'tokenformaPago' => $selectFpago[0]->token_formapago,
            'noCertificado' => $noCertificado,
            'certificado' => $certificado,
            'SubTotal' => $SubTotal,
            'Moneda' => $Moneda,
            'tokenMoneda' => $selectMoneda[0]->token_monedas,
            'tipoCambio' => $tipoCambio,
            'Total' => $Total,
            'confirmacion' => $confirmacion,
            'TipoDeComprobante' => $TipoDeComprobante,
            'MetodoPago' => $MetodoPago,
            'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
            'LugarExpedicion' => $LugarExpedicion,
            //comprobante
            'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
            'uuid' => $verifiedCfdiRelacionadosuuid,
            //emisor
            'emisorRfc' => $RfcEmi,
            'emisorNombre' => $nombre,
            'emisorRegimenFiscal' => $regimenFiscal,
            //receptor
            'receptorRfc' => $RfcRec,
            'receptorUsoCFDI' => $UsoCFDI,
            'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
            //conceptos    
            'conceptos' => $arrayListaConceptos,
            //impuestos    
            'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
            'impuestosRetenciones' => $arrayImpuestosRetenciones,
            'impuestosTraslados' => $arrayImpuestosTraslados,
            //complemento 
            'compluuidComplemento' => $uuidComplemento,
            'complfechaTimbrado' => $fechaTimbrado,
            'complRfcProvCertif' => $RfcProvCertif,
            'complSelloCFD' => $SelloCFD,
            'complNoCertificadoSAT' => $NoCertificadoSAT,
            'complSelloSAT' => $SelloSAT,
          );
        } else {
          $dataMensaje = array(
            'status' => 'errorValidate',
            'code' => 200,
            'arrayErroresComprobante' => $arrayErroresComprobante,
            'arrayErroresEmisor' => $arrayErroresEmisor,
            'arrayErroresReceptor' => $arrayErroresReceptor,
            'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
            'arrayErroresConceptos' => $arrayErroresConceptos,
            'arrayErroresImpuestos' => $arrayErroresImpuestos,
            'arrayErroresComplemento' => $arrayErroresComplemento,
            'message' => 'xml invalido, revise informe de errores',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstructXmlNominas(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('imagenEvidenciaXMl');

    $arrayErroresComprobante = array();
    $arrayErroresEmisor = array();
    $arrayErroresReceptor = array();
    $arrayErroresCfdiRelacionados = array();
    $arrayListaConceptos = array();
    $arrayListaImpuestosConceptos = array();
    $arrayErroresConceptos = array();
    $arrayImpuestosRetenciones = array();
    $arrayImpuestosTraslados = array();
    $arrayErroresImpuestos = array();
    $arrayErroresComplemento = array();

    $proveedor = $request->input('proveedor');
    $parametros = json_decode($proveedor);
    $parametrosArray = json_decode($proveedor, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'emisor' => 'required|string',
        'receptor' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];

        $schama_tres = "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd";
        $schama_cuatro = "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd";

        $http_cfdi3 = "http://www.sat.gob.mx/cfd/3";
        $http_cfdi4 = "http://www.sat.gob.mx/cfd/4";

        $verifiedCfdiComprobante = "";
        $verifiedCfdiEmisor = "";
        $verifiedCfdiReceptor = "";

        $verifiedCfdiRelacionados = "";
        $verifiedCfdiRelacionadostipoRelacion = "";
        $verifiedCfdiRelacionadosuuid = "";

        $verifiedCfdiConceptos = "";

        $verifiedCfdiImpuestos = "";
        $txttotalImpuestosRetenidos = "";
        $txttotalImpuestosTrasladados = "";

        $verifiedCfdiComplemento = "";

        $dataEmisor = DB::select("SELECT people.rfc FROM sos_personas AS people JOIN main_empresas AS emp WHERE people.id = emp.persona AND emp.emp_token = ?", [$emisor]);
        $rfc_emisor = strtolower($JwtAuth->desencriptar($dataEmisor[0]->rfc));

        $dataReceptor = DB::table("ingr_catalogo_clientes AS cKli")
          ->join("sos_personas AS client", "cKli.cliente", "=", "client.id")
          ->where(["cKli.token_cat_clientes" => $receptor])->get();
        $rfc_receptor = strtolower($JwtAuth->desencriptar($dataReceptor[0]->rfc));

        $xmlObject = simplexml_load_file($imageServ);

        $ns = $xmlObject->getNamespaces(true);
        $cfdi = $ns['cfdi'];
        $xsi = $ns['xsi'];
        $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

        $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
        $xmlObject->registerXPathNamespace('t', $ns['tfd']);

        //comprabante
        $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
        $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
        $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
        $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
        $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

        $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
        $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
        $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
        $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
        $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
        $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
        $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
        $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

        if ($comprobante[0]["TipoCambio"] != NULL) {
          $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
        } else {
          $tipoCambio = 'no especificado';
        }

        $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

        if ($comprobante[0]["Confirmacion"] != NULL) {
          $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
        } else {
          $confirmacion = 'no especificado';
        }

        $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
        $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
        $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
        $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

        if (
          isset($cfdi) && !empty($cfdi) && ($cfdi == $http_cfdi3 || $cfdi == $http_cfdi4) &&
          isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" &&
          isset($datSchama) && !empty($datSchama) && ($datSchama == $schama_tres || $datSchama == $schama_cuatro) &&
          isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0") &&
          isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) && strlen($Folio) <= 40 &&
          isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
          isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 &&
          isset($noCertificado) && !empty($noCertificado) &&
          isset($certificado) && !empty($certificado) &&
          isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
          !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
          !empty($TipoDeComprobante) && $TipoDeComprobante == 'N' && isset($MetodoPago) && !empty($MetodoPago) &&
          strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
        ) {

          if ($Moneda != 'MXN' && $Moneda != 'XXX') {
            if (
              isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
              $comprobante[0]["TipoCambio"] != NULL
            ) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "TipoCambio",
                "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                "correccion" => "agregar o verificar atributo TipoCambio"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }

          if ($comprobante[0]["Confirmacion"]) {
            if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "Confirmacion",
                "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                "correccion" => "agregar o verificar atributo Confirmacion"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }
        } else {
          $verifiedCfdiComprobante = 'false';
          if (!isset($cfdi) || empty($cfdi) || ($cfdi != $http_cfdi3 && $cfdi != $http_cfdi4)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:cfdi",
              "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "' . $http_cfdi3 . '" ó "' . $http_cfdi4 . '"',
              "correccion" => "agregar o verificar atributo xmlns:cfdi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:xsi",
              "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
              "correccion" => "agregar o verificar atributo xmlns:xsi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($datSchama) || empty($datSchama) || ($datSchama != $schama_tres && $datSchama != $schama_cuatro)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xsi:schemaLocation",
              "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a "' . $schama_tres . '" ó "' . $schama_cuatro . '"',
              "correccion" => "agregar o verificar atributo xsi:schemaLocation"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (
            !isset($version) || empty($version) ||
            ($version != "3.3" && $version != "4.0")
          ) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Version",
              "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
              "correccion" => "agregar o verificar atributo Version"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Serie",
              "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
              "correccion" => "agregar o verificar atributo Serie"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Folio",
              "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
              "correccion" => "agregar o verificar atributo Folio"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Fecha",
              "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
              "correccion" => "agregar o verificar atributo Fecha"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Sello) || empty($Sello)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Sello",
              "mensaje" => "el atributo Sello no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Sello"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "FormaPago",
              "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
              "correccion" => "agregar o verificar atributo FormaPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($noCertificado) || empty($noCertificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "NoCertificado",
              "mensaje" => "el atributo NoCertificado no existe o esta vacio",
              "correccion" => "agregar o verificar atributo NoCertificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($certificado) || empty($certificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Certificado",
              "mensaje" => "el atributo Certificado no existeo o esta vacio",
              "correccion" => "agregar o verificar atributo Certificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($SubTotal) || empty($SubTotal)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "SubTotal",
              "mensaje" => "el atributo SubTotal no existe,esta vacio",
              "correccion" => "agregar o verificar atributo SubTotal"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Moneda",
              "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo Moneda"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Total) || empty($Total)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Total",
              "mensaje" => "el atributo Total no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Total"
            );
            $arrayErroresComprobante[] = $arrayError;
            $mensajeError = 'nodo Total incorrecto';
          }
          if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'N') {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "TipoComprobante",
              "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
              "correccion" => "agregar o verificar atributo TipoComprobante"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "MetodoPago",
              "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo MetodoPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "LugarExpedicion",
              "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
              "correccion" => "agregar o verificar atributo LugarExpedicion"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
        }

        //nodo CfdiRelacionados
        $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
        if ($CfdiRelacionados) {
          if (!empty($CfdiRelacionados)) {
            $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
            $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
            $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
            if (
              isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
              isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
              isset($uuid) && !empty($uuid)
            ) {
              $verifiedCfdiRelacionados = 'true';
              $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
              $verifiedCfdiRelacionadosuuid = $uuid;
            } else {
              $verifiedCfdiRelacionados = 'false';
              if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "TipoRelacion",
                  "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                  "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($uuid) || empty($uuid)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "UUID",
                  "mensaje" => "el nodo UUID no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
            }
          } else {
            $arrayError = array(
              "nodo" => "CfdiRelacionados",
              "atributo_nodohijo" => "---",
              "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
              "correccion" => "---"
            );
            $arrayErroresCfdiRelacionados[] = $arrayError;
            $verifiedCfdiRelacionados = 'false';
          }
        } else {
          $verifiedCfdiRelacionados = 'true';
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad2 ".$Fecha]);

        //nodo emisor
        $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
        $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
        $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
        $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad3 ".$Fecha]);

        if (
          isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 && strlen($RfcEmi) <= 13 &&
          $RfcEmi == $rfc_emisor &&
          isset($nombre) &&
          !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3
        ) {
          $verifiedCfdiEmisor = 'true';
        } else {
          $verifiedCfdiEmisor = 'false';
          if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if ($RfcEmi != $rfc_emisor) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
              "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($nombre) || empty($nombre)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Nombre",
              "mensaje" => "el atributo Nombre no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Nombre"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "RegimenFiscal",
              "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo RegimenFiscal"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad4 ".$Fecha]);

        //nodo receptor
        $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
        $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
        $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
        $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);
        if (
          isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) &&
          $RfcRec == $rfc_receptor && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3
        ) {
          $verifiedCfdiReceptor = 'true';
        } else {
          $verifiedCfdiReceptor = 'false';
          if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if ($RfcRec != $rfc_receptor) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
              "correccion" => "el rfc de su empresa debe ser " . $rfc_company
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "UsoCFDI",
              "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo UsoCFDI"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad5 ".$Fecha]);

        //nodo conceptos
        $countConceptos = 0;
        $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
        $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
        if (isset($conceptos) && !empty($conceptos)) {
          for ($i = 0; $i < count($forConcepto); $i++) {
            $verifiedCfdiConceptosConcepto = "";
            $verifiedCfdiConceptosImpuestos = "";
            $verifiedCfdiConceptosImpuestosRetenciones = "";
            $verifiedCfdiConceptosImpuestosTraslados = "";

            $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
            $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
            $resultnoIdentificacion = "";
            $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
            $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
            $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
            $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
            $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
            $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
            $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
            $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

            if (
              isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8
              && isset($cantidad) && !empty($cantidad)
              && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3
              && isset($unidad) && !empty($unidad)
              && isset($descripcion) && !empty($descripcion)
              && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6
              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
            ) {
              if (isset($noIdentificacion)) {
                if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                  $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                  $verifiedCfdiConceptosConcepto = 'true';
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "NoIdentificacion",
                    "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                    "correccion" => "agregar o verificar nodo NoIdentificacion"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosConcepto = 'true';
              }

              if (isset($forConcepto[$i]["Descuento"])) {
                $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                  $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                } else {
                  $verifiedCfdiConceptosDescuento = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "Descuento",
                    "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                    "correccion" => "agregar o verificar nodo Descuento"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosDescuento = 'true';
                $resultDescuento = '---';
              }

              $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida WHERE sat_clave = ?", [$claveUnidad]);

              if ($verifiedCfdiConceptosConcepto == 'true') {
                //nodo impuestos
                $arrayImpuestosCncRetenciones = array();
                $arrayImpuestosCncTraslados = array();
                $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                if ($impuestos) {
                  if (isset($impuestos) && !empty($impuestos)) {
                    $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                    if ($retenciones) {
                      if (!empty($retenciones)) {
                        $countRetencion = 0;
                        $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                        if (isset($retencion) && !empty($retencion)) {
                          foreach ($retencion as $forRetencion) {
                            $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);

                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countRetencion;
                              $arrayRetencionFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countRetencion == count($retencion)) {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                            "mensaje" => "el nodo Retencion no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Retenciones",
                          "mensaje" => "el nodo Retenciones no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                    }
                    $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                    $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                    if ($traslados) {
                      if (!empty($traslados)) {
                        $countTraslado = 0;
                        $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                        if (isset($traslado) && !empty($traslado)) {
                          foreach ($traslado as $forTtraslado) {
                            $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);
                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countTraslado;
                              $arrayTrasladoFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countTraslado == count($traslado)) {
                            $verifiedCfdiConceptosImpuestosTraslados = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Traslados Traslado",
                            "mensaje" => "el nodo Traslado no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosTraslados = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Traslados",
                          "mensaje" => "el nodo Traslados no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                    }
                    $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    if (
                      $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                      $verifiedCfdiConceptosImpuestosTraslados == 'true'
                    ) {
                      $verifiedCfdiConceptosImpuestos = 'true';
                    }
                  } else {
                    $verifiedCfdiConceptosImpuestos = 'false';
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Impuestos",
                      "mensaje" => "el nodo Impuestos no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                } else {
                  $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                  $verifiedCfdiConceptosImpuestosTraslados = 'true';
                  $verifiedCfdiConceptosImpuestos = 'true';
                  $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                  $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                }
              }
              if (
                $verifiedCfdiConceptosConcepto == 'true' &&
                $verifiedCfdiConceptosDescuento == 'true' &&
                $verifiedCfdiConceptosImpuestos == 'true' &&
                $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                $verifiedCfdiConceptosImpuestosTraslados == 'true'
              ) {

                ++$countConceptos;
                $arrayforeachConcept = array(
                  "claveProdServ" => $claveProdServ,
                  "noIdentificacion" => $resultnoIdentificacion,
                  "cantidad" => $cantidad,
                  "claveUnidad" => $claveUnidad,
                  "unidad" => $unidad,
                  "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                  "descripcion" => $descripcion,
                  "valorUnitario" => $valorUnitario,
                  "importe" => $importe,
                  "descuento" => $resultDescuento,
                  "impuestos" => $arrayListaImpuestosConceptos,
                );
                $arrayListaConceptos[] = $arrayforeachConcept;
              }
            } else {
              $verifiedCfdiConceptosConcepto = 'false';
              if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveProdServ",
                  "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo ClaveProdServ"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($cantidad) || empty($cantidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Cantidad",
                  "mensaje" => "el atributo Cantidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Cantidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveUnidad",
                  "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                  "correccion" => "agregar o verificar nodo ClaveUnidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($unidad) || empty($unidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Unidad",
                  "mensaje" => "el atributo Unidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Unidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($descripcion) || empty($descripcion)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Descripcion",
                  "mensaje" => "el atributo Descripcion no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Descripcion"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ValorUnitario",
                  "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo ValorUnitario"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Importe",
                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo Importe"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
            }
          }

          if ($countConceptos == count($forConcepto)) {
            $verifiedCfdiConceptos = 'true';
          }
        } else {
          $verifiedCfdiConceptos = 'false';
          $arrayError = array(
            "nodo" => "Conceptos",
            "atributo_nodohijo" => "---",
            "mensaje" => "el nodo Conceptos no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Conceptos"
          );
          $arrayErroresConceptos[] = $arrayError;
        }

        //nodo impuestos
        $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
        if ($impuestosCfdi && count($impuestosCfdi) > 0) {
          if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
            $verifiedCfdiImpuestosRetenciones = "";
            $verifiedCfdiImpuestosRetencionesRetencion = "";
            $verifiedCfdiImpuestosTraslados = "";
            $verifiedCfdiImpuestosTrasladosTraslado = "";
            $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
            if ($retenciones) {
              $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
              if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                $countRetenidoImp = 0;
                $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                if (isset($retencion) && !empty($retencion)) {
                  foreach ($retencion as $forRetencion) {
                    if (isset($forRetencion["Base"])) {
                      $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);

                    if (isset($forRetencion["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forRetencion["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forRetencion["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forRetencion["Importe"])) {
                      $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                      && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countRetenidoImp;
                      $arrayTrasladoFor = array(
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }
                  if ($countRetenidoImp == count($retencion)) {
                    $verifiedCfdiImpuestosRetenciones = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones Retencion",
                    "mensaje" => "el nodo Retencion no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosRetenciones = 'false';
                if (empty($retenciones)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones",
                    "mensaje" => "el nodo Retenciones no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosRetenidos",
                    "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosRetenciones = 'true';
            }
            $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

            $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
            if ($traslados) {
              $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
              if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                $countTrasladoImp = 0;
                $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (isset($traslado) && !empty($traslado)) {
                  foreach ($traslado as $forTtraslado) {
                    if (isset($forTtraslado["Base"])) {
                      $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);
                    if (isset($forTtraslado["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forTtraslado["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forTtraslado["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forTtraslado["Importe"])) {
                      $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                      isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countTrasladoImp;
                      $arrayTrasladoFor = array(
                        "Base" => $base,
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }

                  if ($countTrasladoImp == count($traslado)) {
                    $verifiedCfdiImpuestosTraslados = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados Traslado",
                    "mensaje" => "el nodo Traslado no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosTraslados = 'false';
                if (empty($traslados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados",
                    "mensaje" => "el nodo Traslados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosTrasladados",
                    "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosTraslados = 'true';
            }
            $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

            if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
              $verifiedCfdiImpuestos = 'true';
            }
          } else {
            $verifiedCfdiImpuestos = 'false';
            $arrayError = array(
              "nodo" => "Impuestos",
              "atributo/nodohijo" => "---",
              "mensaje" => "el nodo Impuestos no existe o esta vacio",
              "correccion" => "agregar o verificar nodo Impuestos"
            );
            $arrayErroresImpuestos[] = $arrayError;
          }
        } else {
          $verifiedCfdiImpuestos = 'true';
        }

        //nodo complemento
        $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
        $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
        $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
        $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
        $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
        $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
        $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

        if (isset($complemento) && !empty($complemento)) {
          if (
            isset($uuidComplemento) && !empty($uuidComplemento)
            && isset($fechaTimbrado) && !empty($fechaTimbrado)
            && isset($RfcProvCertif) && !empty($RfcProvCertif)
            && isset($SelloCFD) && !empty($SelloCFD)
            && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT)
            && isset($SelloSAT) && !empty($SelloSAT)
          ) {
            $verifiedCfdiComplemento = 'true';
          } else {
            $verifiedCfdiComplemento = 'false';
            if (!isset($uuidComplemento) || empty($uuidComplemento)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "UUID",
                "mensaje" => "el atributo UUID no existe o esta vacio",
                "correccion" => "agregar o verificar atributo UUID"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "FechaTimbrado",
                "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                "correccion" => "agregar o verificar atributo FechaTimbrado"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "RfcProvCertif",
                "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                "correccion" => "agregar o verificar atributo RfcProvCertif"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($SelloCFD) || empty($SelloCFD)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloCFD",
                "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloCFD"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloCFD incorrecto';
            }
            if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "NoCertificadoSAT",
                "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo NoCertificadoSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
            }
            if (!isset($SelloSAT) || empty($SelloSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloSAT",
                "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloSAT incorrecto';
            }
          }
        } else {
          $verifiedCfdiComplemento = 'false';
          $arrayError = array(
            "nodo" => "Complemento",
            "atributo_nodohijo" => "TimbreFiscalDigital",
            "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
          );
          $arrayErroresComplemento[] = $arrayError;
        }

        if (
          $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
          $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
          $verifiedCfdiComplemento == 'true'
        ) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'xml valido',
            //informacion del xml
            //comprobante
            'version' => $version,
            'serie' => $serie,
            'Folio' => $Folio,
            'Fecha' => $Fecha,
            'Sello' => $Sello,
            'formaPago' => $formaPago,
            'tokenformaPago' => $selectFpago[0]->token_formapago,
            'noCertificado' => $noCertificado,
            'certificado' => $certificado,
            'SubTotal' => $SubTotal,
            'Moneda' => $Moneda,
            'tokenMoneda' => $selectMoneda[0]->token_monedas,
            'tipoCambio' => $tipoCambio,
            'Total' => $Total,
            'confirmacion' => $confirmacion,
            'TipoDeComprobante' => $TipoDeComprobante,
            'MetodoPago' => $MetodoPago,
            'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
            'LugarExpedicion' => $LugarExpedicion,
            //comprobante
            'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
            'uuid' => $verifiedCfdiRelacionadosuuid,
            //emisor
            'emisorRfc' => $RfcEmi,
            'emisorNombre' => $nombre,
            'emisorRegimenFiscal' => $regimenFiscal,
            //receptor
            'receptorRfc' => $RfcRec,
            'receptorUsoCFDI' => $UsoCFDI,
            'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
            //conceptos    
            'conceptos' => $arrayListaConceptos,
            //impuestos    
            'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
            'impuestosRetenciones' => $arrayImpuestosRetenciones,
            'impuestosTraslados' => $arrayImpuestosTraslados,
            //complemento 
            'compluuidComplemento' => $uuidComplemento,
            'complfechaTimbrado' => $fechaTimbrado,
            'complRfcProvCertif' => $RfcProvCertif,
            'complSelloCFD' => $SelloCFD,
            'complNoCertificadoSAT' => $NoCertificadoSAT,
            'complSelloSAT' => $SelloSAT,
          );
        } else {
          $dataMensaje = array(
            'status' => 'errorValidate',
            'code' => 200,
            'arrayErroresComprobante' => $arrayErroresComprobante,
            'arrayErroresEmisor' => $arrayErroresEmisor,
            'arrayErroresReceptor' => $arrayErroresReceptor,
            'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
            'arrayErroresConceptos' => $arrayErroresConceptos,
            'arrayErroresImpuestos' => $arrayErroresImpuestos,
            'arrayErroresComplemento' => $arrayErroresComplemento,
            'message' => 'xml invalido, revise informe de errores',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstructXmlTraslados(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('imagenEvidenciaXMl');

    $arrayErroresComprobante = array();
    $arrayErroresEmisor = array();
    $arrayErroresReceptor = array();
    $arrayErroresCfdiRelacionados = array();
    $arrayListaConceptos = array();
    $arrayListaImpuestosConceptos = array();
    $arrayErroresConceptos = array();
    $arrayImpuestosRetenciones = array();
    $arrayImpuestosTraslados = array();
    $arrayErroresImpuestos = array();
    $arrayErroresComplemento = array();

    $proveedor = $request->input('proveedor');
    $parametros = json_decode($proveedor);
    $parametrosArray = json_decode($proveedor, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'emisor' => 'required|string',
        'receptor' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];

        $schama_tres = "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd";
        $schama_cuatro = "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd";

        $http_cfdi3 = "http://www.sat.gob.mx/cfd/3";
        $http_cfdi4 = "http://www.sat.gob.mx/cfd/4";

        $verifiedCfdiComprobante = "";
        $verifiedCfdiEmisor = "";
        $verifiedCfdiReceptor = "";

        $verifiedCfdiRelacionados = "";
        $verifiedCfdiRelacionadostipoRelacion = "";
        $verifiedCfdiRelacionadosuuid = "";

        $verifiedCfdiConceptos = "";

        $verifiedCfdiImpuestos = "";
        $txttotalImpuestosRetenidos = "";
        $txttotalImpuestosTrasladados = "";

        $verifiedCfdiComplemento = "";

        $dataEmisor = DB::select("SELECT people.rfc FROM sos_personas AS people JOIN main_empresas AS emp WHERE people.id = emp.persona AND emp.emp_token = ?", [$emisor]);
        $rfc_emisor = strtolower($JwtAuth->desencriptar($dataEmisor[0]->rfc));

        $dataReceptor = DB::table("ingr_catalogo_clientes AS cKli")
          ->join("sos_personas AS client", "cKli.cliente", "=", "client.id")
          ->where(["cKli.token_cat_clientes" => $receptor])->get();
        $rfc_receptor = strtolower($JwtAuth->desencriptar($dataReceptor[0]->rfc));

        $xmlObject = simplexml_load_file($imageServ);

        $ns = $xmlObject->getNamespaces(true);
        $cfdi = $ns['cfdi'];
        $xsi = $ns['xsi'];
        $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

        $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
        $xmlObject->registerXPathNamespace('t', $ns['tfd']);

        //comprabante
        $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
        $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
        $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
        $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
        $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

        $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
        $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
        $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
        $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
        $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
        $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
        $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
        $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

        if ($comprobante[0]["TipoCambio"] != NULL) {
          $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
        } else {
          $tipoCambio = 'no especificado';
        }

        $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

        if ($comprobante[0]["Confirmacion"] != NULL) {
          $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
        } else {
          $confirmacion = 'no especificado';
        }

        $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
        $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
        $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
        $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

        if (
          isset($cfdi) && !empty($cfdi) && ($cfdi == $http_cfdi3 || $cfdi == $http_cfdi4) &&
          isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" &&
          isset($datSchama) && !empty($datSchama) && ($datSchama == $schama_tres || $datSchama == $schama_cuatro) &&
          isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0") &&
          isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) && strlen($Folio) <= 40 &&
          isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
          isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 &&
          isset($noCertificado) && !empty($noCertificado) &&
          isset($certificado) && !empty($certificado) &&
          isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
          !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
          !empty($TipoDeComprobante) && $TipoDeComprobante == 'T' && isset($MetodoPago) && !empty($MetodoPago) &&
          strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
        ) {

          if ($Moneda != 'MXN' && $Moneda != 'XXX') {
            if (
              isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
              $comprobante[0]["TipoCambio"] != NULL
            ) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "TipoCambio",
                "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                "correccion" => "agregar o verificar atributo TipoCambio"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }

          if ($comprobante[0]["Confirmacion"]) {
            if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "Confirmacion",
                "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                "correccion" => "agregar o verificar atributo Confirmacion"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }
        } else {
          $verifiedCfdiComprobante = 'false';
          if (!isset($cfdi) || empty($cfdi) || ($cfdi != $http_cfdi3 && $cfdi != $http_cfdi4)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:cfdi",
              "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "' . $http_cfdi3 . '" ó "' . $http_cfdi4 . '"',
              "correccion" => "agregar o verificar atributo xmlns:cfdi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:xsi",
              "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
              "correccion" => "agregar o verificar atributo xmlns:xsi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($datSchama) || empty($datSchama) || ($datSchama != $schama_tres && $datSchama != $schama_cuatro)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xsi:schemaLocation",
              "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a "' . $schama_tres . '" ó "' . $schama_cuatro . '"',
              "correccion" => "agregar o verificar atributo xsi:schemaLocation"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (
            !isset($version) || empty($version) ||
            ($version != "3.3" && $version != "4.0")
          ) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Version",
              "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
              "correccion" => "agregar o verificar atributo Version"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Serie",
              "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
              "correccion" => "agregar o verificar atributo Serie"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Folio",
              "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
              "correccion" => "agregar o verificar atributo Folio"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Fecha",
              "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
              "correccion" => "agregar o verificar atributo Fecha"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Sello) || empty($Sello)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Sello",
              "mensaje" => "el atributo Sello no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Sello"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "FormaPago",
              "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
              "correccion" => "agregar o verificar atributo FormaPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($noCertificado) || empty($noCertificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "NoCertificado",
              "mensaje" => "el atributo NoCertificado no existe o esta vacio",
              "correccion" => "agregar o verificar atributo NoCertificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($certificado) || empty($certificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Certificado",
              "mensaje" => "el atributo Certificado no existeo o esta vacio",
              "correccion" => "agregar o verificar atributo Certificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($SubTotal) || empty($SubTotal)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "SubTotal",
              "mensaje" => "el atributo SubTotal no existe,esta vacio",
              "correccion" => "agregar o verificar atributo SubTotal"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Moneda",
              "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo Moneda"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Total) || empty($Total)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Total",
              "mensaje" => "el atributo Total no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Total"
            );
            $arrayErroresComprobante[] = $arrayError;
            $mensajeError = 'nodo Total incorrecto';
          }
          if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'T') {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "TipoComprobante",
              "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
              "correccion" => "agregar o verificar atributo TipoComprobante"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "MetodoPago",
              "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo MetodoPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "LugarExpedicion",
              "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
              "correccion" => "agregar o verificar atributo LugarExpedicion"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
        }

        //nodo CfdiRelacionados
        $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
        if ($CfdiRelacionados) {
          if (!empty($CfdiRelacionados)) {
            $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
            $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
            $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
            if (
              isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
              isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
              isset($uuid) && !empty($uuid)
            ) {
              $verifiedCfdiRelacionados = 'true';
              $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
              $verifiedCfdiRelacionadosuuid = $uuid;
            } else {
              $verifiedCfdiRelacionados = 'false';
              if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "TipoRelacion",
                  "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                  "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($uuid) || empty($uuid)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "UUID",
                  "mensaje" => "el nodo UUID no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
            }
          } else {
            $arrayError = array(
              "nodo" => "CfdiRelacionados",
              "atributo_nodohijo" => "---",
              "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
              "correccion" => "---"
            );
            $arrayErroresCfdiRelacionados[] = $arrayError;
            $verifiedCfdiRelacionados = 'false';
          }
        } else {
          $verifiedCfdiRelacionados = 'true';
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad2 ".$Fecha]);

        //nodo emisor
        $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
        $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
        $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
        $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad3 ".$Fecha]);

        if (
          isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 && strlen($RfcEmi) <= 13 &&
          $RfcEmi == $rfc_emisor &&
          isset($nombre) &&
          !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3
        ) {
          $verifiedCfdiEmisor = 'true';
        } else {
          $verifiedCfdiEmisor = 'false';
          if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if ($RfcEmi != $rfc_emisor) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
              "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($nombre) || empty($nombre)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Nombre",
              "mensaje" => "el atributo Nombre no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Nombre"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "RegimenFiscal",
              "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo RegimenFiscal"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad4 ".$Fecha]);

        //nodo receptor
        $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
        $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
        $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
        $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);
        if (
          isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) &&
          $RfcRec == $rfc_receptor && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3
        ) {
          $verifiedCfdiReceptor = 'true';
        } else {
          $verifiedCfdiReceptor = 'false';
          if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if ($RfcRec != $rfc_receptor) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
              "correccion" => "el rfc de su empresa debe ser " . $rfc_company
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "UsoCFDI",
              "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo UsoCFDI"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad5 ".$Fecha]);

        //nodo conceptos
        $countConceptos = 0;
        $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
        $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
        if (isset($conceptos) && !empty($conceptos)) {
          for ($i = 0; $i < count($forConcepto); $i++) {
            $verifiedCfdiConceptosConcepto = "";
            $verifiedCfdiConceptosImpuestos = "";
            $verifiedCfdiConceptosImpuestosRetenciones = "";
            $verifiedCfdiConceptosImpuestosTraslados = "";

            $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
            $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
            $resultnoIdentificacion = "";
            $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
            $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
            $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
            $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
            $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
            $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
            $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
            $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

            if (
              isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8
              && isset($cantidad) && !empty($cantidad)
              && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3
              && isset($unidad) && !empty($unidad)
              && isset($descripcion) && !empty($descripcion)
              && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6
              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
            ) {
              if (isset($noIdentificacion)) {
                if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                  $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                  $verifiedCfdiConceptosConcepto = 'true';
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "NoIdentificacion",
                    "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                    "correccion" => "agregar o verificar nodo NoIdentificacion"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosConcepto = 'true';
              }

              if (isset($forConcepto[$i]["Descuento"])) {
                $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                  $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                } else {
                  $verifiedCfdiConceptosDescuento = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "Descuento",
                    "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                    "correccion" => "agregar o verificar nodo Descuento"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosDescuento = 'true';
                $resultDescuento = '---';
              }

              $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida WHERE sat_clave = ?", [$claveUnidad]);

              if ($verifiedCfdiConceptosConcepto == 'true') {
                //nodo impuestos
                $arrayImpuestosCncRetenciones = array();
                $arrayImpuestosCncTraslados = array();
                $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                if ($impuestos) {
                  if (isset($impuestos) && !empty($impuestos)) {
                    $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                    if ($retenciones) {
                      if (!empty($retenciones)) {
                        $countRetencion = 0;
                        $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                        if (isset($retencion) && !empty($retencion)) {
                          foreach ($retencion as $forRetencion) {
                            $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);

                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countRetencion;
                              $arrayRetencionFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countRetencion == count($retencion)) {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                            "mensaje" => "el nodo Retencion no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Retenciones",
                          "mensaje" => "el nodo Retenciones no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                    }
                    $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                    $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                    if ($traslados) {
                      if (!empty($traslados)) {
                        $countTraslado = 0;
                        $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                        if (isset($traslado) && !empty($traslado)) {
                          foreach ($traslado as $forTtraslado) {
                            $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);
                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countTraslado;
                              $arrayTrasladoFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countTraslado == count($traslado)) {
                            $verifiedCfdiConceptosImpuestosTraslados = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Traslados Traslado",
                            "mensaje" => "el nodo Traslado no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosTraslados = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Traslados",
                          "mensaje" => "el nodo Traslados no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                    }
                    $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    if (
                      $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                      $verifiedCfdiConceptosImpuestosTraslados == 'true'
                    ) {
                      $verifiedCfdiConceptosImpuestos = 'true';
                    }
                  } else {
                    $verifiedCfdiConceptosImpuestos = 'false';
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Impuestos",
                      "mensaje" => "el nodo Impuestos no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                } else {
                  $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                  $verifiedCfdiConceptosImpuestosTraslados = 'true';
                  $verifiedCfdiConceptosImpuestos = 'true';
                  $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                  $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                }
              }
              if (
                $verifiedCfdiConceptosConcepto == 'true' &&
                $verifiedCfdiConceptosDescuento == 'true' &&
                $verifiedCfdiConceptosImpuestos == 'true' &&
                $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                $verifiedCfdiConceptosImpuestosTraslados == 'true'
              ) {

                ++$countConceptos;
                $arrayforeachConcept = array(
                  "claveProdServ" => $claveProdServ,
                  "noIdentificacion" => $resultnoIdentificacion,
                  "cantidad" => $cantidad,
                  "claveUnidad" => $claveUnidad,
                  "unidad" => $unidad,
                  "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                  "descripcion" => $descripcion,
                  "valorUnitario" => $valorUnitario,
                  "importe" => $importe,
                  "descuento" => $resultDescuento,
                  "impuestos" => $arrayListaImpuestosConceptos,
                );
                $arrayListaConceptos[] = $arrayforeachConcept;
              }
            } else {
              $verifiedCfdiConceptosConcepto = 'false';
              if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveProdServ",
                  "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo ClaveProdServ"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($cantidad) || empty($cantidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Cantidad",
                  "mensaje" => "el atributo Cantidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Cantidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveUnidad",
                  "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                  "correccion" => "agregar o verificar nodo ClaveUnidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($unidad) || empty($unidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Unidad",
                  "mensaje" => "el atributo Unidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Unidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($descripcion) || empty($descripcion)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Descripcion",
                  "mensaje" => "el atributo Descripcion no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Descripcion"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ValorUnitario",
                  "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo ValorUnitario"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Importe",
                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo Importe"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
            }
          }

          if ($countConceptos == count($forConcepto)) {
            $verifiedCfdiConceptos = 'true';
          }
        } else {
          $verifiedCfdiConceptos = 'false';
          $arrayError = array(
            "nodo" => "Conceptos",
            "atributo_nodohijo" => "---",
            "mensaje" => "el nodo Conceptos no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Conceptos"
          );
          $arrayErroresConceptos[] = $arrayError;
        }

        //nodo impuestos
        $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
        if ($impuestosCfdi && count($impuestosCfdi) > 0) {
          if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
            $verifiedCfdiImpuestosRetenciones = "";
            $verifiedCfdiImpuestosRetencionesRetencion = "";
            $verifiedCfdiImpuestosTraslados = "";
            $verifiedCfdiImpuestosTrasladosTraslado = "";
            $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
            if ($retenciones) {
              $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
              if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                $countRetenidoImp = 0;
                $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                if (isset($retencion) && !empty($retencion)) {
                  foreach ($retencion as $forRetencion) {
                    if (isset($forRetencion["Base"])) {
                      $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);

                    if (isset($forRetencion["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forRetencion["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forRetencion["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forRetencion["Importe"])) {
                      $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                      && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countRetenidoImp;
                      $arrayTrasladoFor = array(
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }
                  if ($countRetenidoImp == count($retencion)) {
                    $verifiedCfdiImpuestosRetenciones = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones Retencion",
                    "mensaje" => "el nodo Retencion no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosRetenciones = 'false';
                if (empty($retenciones)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones",
                    "mensaje" => "el nodo Retenciones no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosRetenidos",
                    "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosRetenciones = 'true';
            }
            $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

            $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
            if ($traslados) {
              $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
              if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                $countTrasladoImp = 0;
                $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (isset($traslado) && !empty($traslado)) {
                  foreach ($traslado as $forTtraslado) {
                    if (isset($forTtraslado["Base"])) {
                      $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);
                    if (isset($forTtraslado["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forTtraslado["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forTtraslado["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forTtraslado["Importe"])) {
                      $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                      isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countTrasladoImp;
                      $arrayTrasladoFor = array(
                        "Base" => $base,
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }

                  if ($countTrasladoImp == count($traslado)) {
                    $verifiedCfdiImpuestosTraslados = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados Traslado",
                    "mensaje" => "el nodo Traslado no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosTraslados = 'false';
                if (empty($traslados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados",
                    "mensaje" => "el nodo Traslados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosTrasladados",
                    "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosTraslados = 'true';
            }
            $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

            if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
              $verifiedCfdiImpuestos = 'true';
            }
          } else {
            $verifiedCfdiImpuestos = 'false';
            $arrayError = array(
              "nodo" => "Impuestos",
              "atributo/nodohijo" => "---",
              "mensaje" => "el nodo Impuestos no existe o esta vacio",
              "correccion" => "agregar o verificar nodo Impuestos"
            );
            $arrayErroresImpuestos[] = $arrayError;
          }
        } else {
          $verifiedCfdiImpuestos = 'true';
        }

        //nodo complemento
        $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
        $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
        $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
        $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
        $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
        $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
        $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

        if (isset($complemento) && !empty($complemento)) {
          if (
            isset($uuidComplemento) && !empty($uuidComplemento)
            && isset($fechaTimbrado) && !empty($fechaTimbrado)
            && isset($RfcProvCertif) && !empty($RfcProvCertif)
            && isset($SelloCFD) && !empty($SelloCFD)
            && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT)
            && isset($SelloSAT) && !empty($SelloSAT)
          ) {
            $verifiedCfdiComplemento = 'true';
          } else {
            $verifiedCfdiComplemento = 'false';
            if (!isset($uuidComplemento) || empty($uuidComplemento)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "UUID",
                "mensaje" => "el atributo UUID no existe o esta vacio",
                "correccion" => "agregar o verificar atributo UUID"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "FechaTimbrado",
                "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                "correccion" => "agregar o verificar atributo FechaTimbrado"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "RfcProvCertif",
                "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                "correccion" => "agregar o verificar atributo RfcProvCertif"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($SelloCFD) || empty($SelloCFD)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloCFD",
                "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloCFD"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloCFD incorrecto';
            }
            if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "NoCertificadoSAT",
                "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo NoCertificadoSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
            }
            if (!isset($SelloSAT) || empty($SelloSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloSAT",
                "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloSAT incorrecto';
            }
          }
        } else {
          $verifiedCfdiComplemento = 'false';
          $arrayError = array(
            "nodo" => "Complemento",
            "atributo_nodohijo" => "TimbreFiscalDigital",
            "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
          );
          $arrayErroresComplemento[] = $arrayError;
        }

        if (
          $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
          $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
          $verifiedCfdiComplemento == 'true'
        ) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'xml valido',
            //informacion del xml
            //comprobante
            'version' => $version,
            'serie' => $serie,
            'Folio' => $Folio,
            'Fecha' => $Fecha,
            'Sello' => $Sello,
            'formaPago' => $formaPago,
            'tokenformaPago' => $selectFpago[0]->token_formapago,
            'noCertificado' => $noCertificado,
            'certificado' => $certificado,
            'SubTotal' => $SubTotal,
            'Moneda' => $Moneda,
            'tokenMoneda' => $selectMoneda[0]->token_monedas,
            'tipoCambio' => $tipoCambio,
            'Total' => $Total,
            'confirmacion' => $confirmacion,
            'TipoDeComprobante' => $TipoDeComprobante,
            'MetodoPago' => $MetodoPago,
            'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
            'LugarExpedicion' => $LugarExpedicion,
            //comprobante
            'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
            'uuid' => $verifiedCfdiRelacionadosuuid,
            //emisor
            'emisorRfc' => $RfcEmi,
            'emisorNombre' => $nombre,
            'emisorRegimenFiscal' => $regimenFiscal,
            //receptor
            'receptorRfc' => $RfcRec,
            'receptorUsoCFDI' => $UsoCFDI,
            'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
            //conceptos    
            'conceptos' => $arrayListaConceptos,
            //impuestos    
            'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
            'impuestosRetenciones' => $arrayImpuestosRetenciones,
            'impuestosTraslados' => $arrayImpuestosTraslados,
            //complemento 
            'compluuidComplemento' => $uuidComplemento,
            'complfechaTimbrado' => $fechaTimbrado,
            'complRfcProvCertif' => $RfcProvCertif,
            'complSelloCFD' => $SelloCFD,
            'complNoCertificadoSAT' => $NoCertificadoSAT,
            'complSelloSAT' => $SelloSAT,
          );
        } else {
          $dataMensaje = array(
            'status' => 'errorValidate',
            'code' => 200,
            'arrayErroresComprobante' => $arrayErroresComprobante,
            'arrayErroresEmisor' => $arrayErroresEmisor,
            'arrayErroresReceptor' => $arrayErroresReceptor,
            'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
            'arrayErroresConceptos' => $arrayErroresConceptos,
            'arrayErroresImpuestos' => $arrayErroresImpuestos,
            'arrayErroresComplemento' => $arrayErroresComplemento,
            'message' => 'xml invalido, revise informe de errores',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstructXmlRetenciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('imagenEvidenciaXMl');

    $arrayErroresComprobante = array();
    $arrayErroresEmisor = array();
    $arrayErroresReceptor = array();
    $arrayErroresCfdiRelacionados = array();
    $arrayListaConceptos = array();
    $arrayListaImpuestosConceptos = array();
    $arrayErroresConceptos = array();
    $arrayImpuestosRetenciones = array();
    $arrayImpuestosTraslados = array();
    $arrayErroresImpuestos = array();
    $arrayErroresComplemento = array();

    $proveedor = $request->input('proveedor');
    $parametros = json_decode($proveedor);
    $parametrosArray = json_decode($proveedor, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'emisor' => 'required|string',
        'receptor' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];

        $schama_tres = "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd";
        $schama_cuatro = "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd";

        $http_cfdi3 = "http://www.sat.gob.mx/cfd/3";
        $http_cfdi4 = "http://www.sat.gob.mx/cfd/4";

        $verifiedCfdiComprobante = "";
        $verifiedCfdiEmisor = "";
        $verifiedCfdiReceptor = "";

        $verifiedCfdiRelacionados = "";
        $verifiedCfdiRelacionadostipoRelacion = "";
        $verifiedCfdiRelacionadosuuid = "";

        $verifiedCfdiConceptos = "";

        $verifiedCfdiImpuestos = "";
        $txttotalImpuestosRetenidos = "";
        $txttotalImpuestosTrasladados = "";

        $verifiedCfdiComplemento = "";

        $dataEmisor = DB::select("SELECT people.rfc FROM sos_personas AS people JOIN main_empresas AS emp WHERE people.id = emp.persona AND emp.emp_token = ?", [$emisor]);
        $rfc_emisor = strtolower($JwtAuth->desencriptar($dataEmisor[0]->rfc));

        $dataReceptor = DB::table("ingr_catalogo_clientes AS cKli")
          ->join("sos_personas AS client", "cKli.cliente", "=", "client.id")
          ->where(["cKli.token_cat_clientes" => $receptor])->get();
        $rfc_receptor = strtolower($JwtAuth->desencriptar($dataReceptor[0]->rfc));

        $xmlObject = simplexml_load_file($imageServ);

        $ns = $xmlObject->getNamespaces(true);
        $cfdi = $ns['cfdi'];
        $xsi = $ns['xsi'];
        $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

        $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
        $xmlObject->registerXPathNamespace('t', $ns['tfd']);

        //comprabante
        $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
        $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
        $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
        $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
        $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

        $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
        $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
        $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
        $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
        $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
        $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
        $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
        $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

        if ($comprobante[0]["TipoCambio"] != NULL) {
          $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
        } else {
          $tipoCambio = 'no especificado';
        }

        $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

        if ($comprobante[0]["Confirmacion"] != NULL) {
          $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
        } else {
          $confirmacion = 'no especificado';
        }

        $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
        $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
        $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
        $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

        if (
          isset($cfdi) && !empty($cfdi) && ($cfdi == $http_cfdi3 || $cfdi == $http_cfdi4) &&
          isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" &&
          isset($datSchama) && !empty($datSchama) && ($datSchama == $schama_tres || $datSchama == $schama_cuatro) &&
          isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0") &&
          isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) && strlen($Folio) <= 40 &&
          isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
          isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 &&
          isset($noCertificado) && !empty($noCertificado) &&
          isset($certificado) && !empty($certificado) &&
          isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
          !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
          !empty($TipoDeComprobante) && $TipoDeComprobante == 'R' && isset($MetodoPago) && !empty($MetodoPago) &&
          strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
        ) {

          if ($Moneda != 'MXN' && $Moneda != 'XXX') {
            if (
              isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
              $comprobante[0]["TipoCambio"] != NULL
            ) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "TipoCambio",
                "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                "correccion" => "agregar o verificar atributo TipoCambio"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }

          if ($comprobante[0]["Confirmacion"]) {
            if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "Confirmacion",
                "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                "correccion" => "agregar o verificar atributo Confirmacion"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }
        } else {
          $verifiedCfdiComprobante = 'false';
          if (!isset($cfdi) || empty($cfdi) || ($cfdi != $http_cfdi3 && $cfdi != $http_cfdi4)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:cfdi",
              "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "' . $http_cfdi3 . '" ó "' . $http_cfdi4 . '"',
              "correccion" => "agregar o verificar atributo xmlns:cfdi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:xsi",
              "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
              "correccion" => "agregar o verificar atributo xmlns:xsi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($datSchama) || empty($datSchama) || ($datSchama != $schama_tres && $datSchama != $schama_cuatro)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xsi:schemaLocation",
              "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a "' . $schama_tres . '" ó "' . $schama_cuatro . '"',
              "correccion" => "agregar o verificar atributo xsi:schemaLocation"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (
            !isset($version) || empty($version) ||
            ($version != "3.3" && $version != "4.0")
          ) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Version",
              "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
              "correccion" => "agregar o verificar atributo Version"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Serie",
              "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
              "correccion" => "agregar o verificar atributo Serie"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Folio",
              "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
              "correccion" => "agregar o verificar atributo Folio"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Fecha",
              "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
              "correccion" => "agregar o verificar atributo Fecha"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Sello) || empty($Sello)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Sello",
              "mensaje" => "el atributo Sello no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Sello"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "FormaPago",
              "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
              "correccion" => "agregar o verificar atributo FormaPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($noCertificado) || empty($noCertificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "NoCertificado",
              "mensaje" => "el atributo NoCertificado no existe o esta vacio",
              "correccion" => "agregar o verificar atributo NoCertificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($certificado) || empty($certificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Certificado",
              "mensaje" => "el atributo Certificado no existeo o esta vacio",
              "correccion" => "agregar o verificar atributo Certificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($SubTotal) || empty($SubTotal)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "SubTotal",
              "mensaje" => "el atributo SubTotal no existe,esta vacio",
              "correccion" => "agregar o verificar atributo SubTotal"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Moneda",
              "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo Moneda"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Total) || empty($Total)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Total",
              "mensaje" => "el atributo Total no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Total"
            );
            $arrayErroresComprobante[] = $arrayError;
            $mensajeError = 'nodo Total incorrecto';
          }
          if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'R') {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "TipoComprobante",
              "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
              "correccion" => "agregar o verificar atributo TipoComprobante"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "MetodoPago",
              "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo MetodoPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "LugarExpedicion",
              "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
              "correccion" => "agregar o verificar atributo LugarExpedicion"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
        }

        //nodo CfdiRelacionados
        $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
        if ($CfdiRelacionados) {
          if (!empty($CfdiRelacionados)) {
            $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
            $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
            $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
            if (
              isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
              isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
              isset($uuid) && !empty($uuid)
            ) {
              $verifiedCfdiRelacionados = 'true';
              $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
              $verifiedCfdiRelacionadosuuid = $uuid;
            } else {
              $verifiedCfdiRelacionados = 'false';
              if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "TipoRelacion",
                  "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                  "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($uuid) || empty($uuid)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "UUID",
                  "mensaje" => "el nodo UUID no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
            }
          } else {
            $arrayError = array(
              "nodo" => "CfdiRelacionados",
              "atributo_nodohijo" => "---",
              "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
              "correccion" => "---"
            );
            $arrayErroresCfdiRelacionados[] = $arrayError;
            $verifiedCfdiRelacionados = 'false';
          }
        } else {
          $verifiedCfdiRelacionados = 'true';
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad2 ".$Fecha]);

        //nodo emisor
        $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
        $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
        $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
        $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad3 ".$Fecha]);

        if (
          isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 && strlen($RfcEmi) <= 13 &&
          $RfcEmi == $rfc_emisor &&
          isset($nombre) &&
          !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3
        ) {
          $verifiedCfdiEmisor = 'true';
        } else {
          $verifiedCfdiEmisor = 'false';
          if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if ($RfcEmi != $rfc_emisor) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
              "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($nombre) || empty($nombre)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Nombre",
              "mensaje" => "el atributo Nombre no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Nombre"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "RegimenFiscal",
              "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo RegimenFiscal"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad4 ".$Fecha]);

        //nodo receptor
        $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
        $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
        $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
        $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);
        if (
          isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) &&
          $RfcRec == $rfc_receptor && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3
        ) {
          $verifiedCfdiReceptor = 'true';
        } else {
          $verifiedCfdiReceptor = 'false';
          if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if ($RfcRec != $rfc_receptor) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
              "correccion" => "el rfc de su empresa debe ser " . $rfc_company
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "UsoCFDI",
              "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo UsoCFDI"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad5 ".$Fecha]);

        //nodo conceptos
        $countConceptos = 0;
        $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
        $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
        if (isset($conceptos) && !empty($conceptos)) {
          for ($i = 0; $i < count($forConcepto); $i++) {
            $verifiedCfdiConceptosConcepto = "";
            $verifiedCfdiConceptosImpuestos = "";
            $verifiedCfdiConceptosImpuestosRetenciones = "";
            $verifiedCfdiConceptosImpuestosTraslados = "";

            $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
            $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
            $resultnoIdentificacion = "";
            $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
            $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
            $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
            $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
            $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
            $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
            $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
            $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

            if (
              isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8
              && isset($cantidad) && !empty($cantidad)
              && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3
              && isset($unidad) && !empty($unidad)
              && isset($descripcion) && !empty($descripcion)
              && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6
              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
            ) {
              if (isset($noIdentificacion)) {
                if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                  $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                  $verifiedCfdiConceptosConcepto = 'true';
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "NoIdentificacion",
                    "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                    "correccion" => "agregar o verificar nodo NoIdentificacion"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosConcepto = 'true';
              }

              if (isset($forConcepto[$i]["Descuento"])) {
                $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                  $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                } else {
                  $verifiedCfdiConceptosDescuento = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "Descuento",
                    "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                    "correccion" => "agregar o verificar nodo Descuento"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosDescuento = 'true';
                $resultDescuento = '---';
              }

              $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida 
                                    WHERE sat_clave = ?", [$claveUnidad]);

              if ($verifiedCfdiConceptosConcepto == 'true') {
                //nodo impuestos
                $arrayImpuestosCncRetenciones = array();
                $arrayImpuestosCncTraslados = array();
                $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                if ($impuestos) {
                  if (isset($impuestos) && !empty($impuestos)) {
                    $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                    if ($retenciones) {
                      if (!empty($retenciones)) {
                        $countRetencion = 0;
                        $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                        if (isset($retencion) && !empty($retencion)) {
                          foreach ($retencion as $forRetencion) {
                            $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);

                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countRetencion;
                              $arrayRetencionFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countRetencion == count($retencion)) {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                            "mensaje" => "el nodo Retencion no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Retenciones",
                          "mensaje" => "el nodo Retenciones no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                    }
                    $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                    $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                    if ($traslados) {
                      if (!empty($traslados)) {
                        $countTraslado = 0;
                        $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                        if (isset($traslado) && !empty($traslado)) {
                          foreach ($traslado as $forTtraslado) {
                            $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);
                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countTraslado;
                              $arrayTrasladoFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countTraslado == count($traslado)) {
                            $verifiedCfdiConceptosImpuestosTraslados = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Traslados Traslado",
                            "mensaje" => "el nodo Traslado no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosTraslados = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Traslados",
                          "mensaje" => "el nodo Traslados no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                    }
                    $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    if (
                      $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                      $verifiedCfdiConceptosImpuestosTraslados == 'true'
                    ) {
                      $verifiedCfdiConceptosImpuestos = 'true';
                    }
                  } else {
                    $verifiedCfdiConceptosImpuestos = 'false';
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Impuestos",
                      "mensaje" => "el nodo Impuestos no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                } else {
                  $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                  $verifiedCfdiConceptosImpuestosTraslados = 'true';
                  $verifiedCfdiConceptosImpuestos = 'true';
                  $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                  $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                }
              }
              if (
                $verifiedCfdiConceptosConcepto == 'true' &&
                $verifiedCfdiConceptosDescuento == 'true' &&
                $verifiedCfdiConceptosImpuestos == 'true' &&
                $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                $verifiedCfdiConceptosImpuestosTraslados == 'true'
              ) {

                ++$countConceptos;
                $arrayforeachConcept = array(
                  "claveProdServ" => $claveProdServ,
                  "noIdentificacion" => $resultnoIdentificacion,
                  "cantidad" => $cantidad,
                  "claveUnidad" => $claveUnidad,
                  "unidad" => $unidad,
                  "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                  "descripcion" => $descripcion,
                  "valorUnitario" => $valorUnitario,
                  "importe" => $importe,
                  "descuento" => $resultDescuento,
                  "impuestos" => $arrayListaImpuestosConceptos,
                );
                $arrayListaConceptos[] = $arrayforeachConcept;
              }
            } else {
              $verifiedCfdiConceptosConcepto = 'false';
              if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveProdServ",
                  "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo ClaveProdServ"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($cantidad) || empty($cantidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Cantidad",
                  "mensaje" => "el atributo Cantidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Cantidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveUnidad",
                  "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                  "correccion" => "agregar o verificar nodo ClaveUnidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($unidad) || empty($unidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Unidad",
                  "mensaje" => "el atributo Unidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Unidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($descripcion) || empty($descripcion)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Descripcion",
                  "mensaje" => "el atributo Descripcion no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Descripcion"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ValorUnitario",
                  "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo ValorUnitario"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Importe",
                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo Importe"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
            }
          }

          if ($countConceptos == count($forConcepto)) {
            $verifiedCfdiConceptos = 'true';
          }
        } else {
          $verifiedCfdiConceptos = 'false';
          $arrayError = array(
            "nodo" => "Conceptos",
            "atributo_nodohijo" => "---",
            "mensaje" => "el nodo Conceptos no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Conceptos"
          );
          $arrayErroresConceptos[] = $arrayError;
        }

        //nodo impuestos
        $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
        if ($impuestosCfdi && count($impuestosCfdi) > 0) {
          if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
            $verifiedCfdiImpuestosRetenciones = "";
            $verifiedCfdiImpuestosRetencionesRetencion = "";
            $verifiedCfdiImpuestosTraslados = "";
            $verifiedCfdiImpuestosTrasladosTraslado = "";
            $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
            if ($retenciones) {
              $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
              if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                $countRetenidoImp = 0;
                $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                if (isset($retencion) && !empty($retencion)) {
                  foreach ($retencion as $forRetencion) {
                    if (isset($forRetencion["Base"])) {
                      $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);

                    if (isset($forRetencion["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forRetencion["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forRetencion["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forRetencion["Importe"])) {
                      $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                      && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countRetenidoImp;
                      $arrayTrasladoFor = array(
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }
                  if ($countRetenidoImp == count($retencion)) {
                    $verifiedCfdiImpuestosRetenciones = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones Retencion",
                    "mensaje" => "el nodo Retencion no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosRetenciones = 'false';
                if (empty($retenciones)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones",
                    "mensaje" => "el nodo Retenciones no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosRetenidos",
                    "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosRetenciones = 'true';
            }
            $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

            $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
            if ($traslados) {
              $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
              if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                $countTrasladoImp = 0;
                $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (isset($traslado) && !empty($traslado)) {
                  foreach ($traslado as $forTtraslado) {
                    if (isset($forTtraslado["Base"])) {
                      $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);
                    if (isset($forTtraslado["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forTtraslado["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forTtraslado["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forTtraslado["Importe"])) {
                      $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                      isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countTrasladoImp;
                      $arrayTrasladoFor = array(
                        "Base" => $base,
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }

                  if ($countTrasladoImp == count($traslado)) {
                    $verifiedCfdiImpuestosTraslados = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados Traslado",
                    "mensaje" => "el nodo Traslado no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosTraslados = 'false';
                if (empty($traslados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados",
                    "mensaje" => "el nodo Traslados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosTrasladados",
                    "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosTraslados = 'true';
            }
            $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

            if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
              $verifiedCfdiImpuestos = 'true';
            }
          } else {
            $verifiedCfdiImpuestos = 'false';
            $arrayError = array(
              "nodo" => "Impuestos",
              "atributo/nodohijo" => "---",
              "mensaje" => "el nodo Impuestos no existe o esta vacio",
              "correccion" => "agregar o verificar nodo Impuestos"
            );
            $arrayErroresImpuestos[] = $arrayError;
          }
        } else {
          $verifiedCfdiImpuestos = 'true';
        }

        //nodo complemento
        $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
        $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
        $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
        $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
        $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
        $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
        $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

        if (isset($complemento) && !empty($complemento)) {
          if (
            isset($uuidComplemento) && !empty($uuidComplemento)
            && isset($fechaTimbrado) && !empty($fechaTimbrado)
            && isset($RfcProvCertif) && !empty($RfcProvCertif)
            && isset($SelloCFD) && !empty($SelloCFD)
            && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT)
            && isset($SelloSAT) && !empty($SelloSAT)
          ) {
            $verifiedCfdiComplemento = 'true';
          } else {
            $verifiedCfdiComplemento = 'false';
            if (!isset($uuidComplemento) || empty($uuidComplemento)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "UUID",
                "mensaje" => "el atributo UUID no existe o esta vacio",
                "correccion" => "agregar o verificar atributo UUID"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "FechaTimbrado",
                "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                "correccion" => "agregar o verificar atributo FechaTimbrado"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "RfcProvCertif",
                "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                "correccion" => "agregar o verificar atributo RfcProvCertif"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($SelloCFD) || empty($SelloCFD)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloCFD",
                "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloCFD"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloCFD incorrecto';
            }
            if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "NoCertificadoSAT",
                "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo NoCertificadoSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
            }
            if (!isset($SelloSAT) || empty($SelloSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloSAT",
                "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloSAT incorrecto';
            }
          }
        } else {
          $verifiedCfdiComplemento = 'false';
          $arrayError = array(
            "nodo" => "Complemento",
            "atributo_nodohijo" => "TimbreFiscalDigital",
            "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
          );
          $arrayErroresComplemento[] = $arrayError;
        }

        if (
          $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
          $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
          $verifiedCfdiComplemento == 'true'
        ) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'xml valido',
            //informacion del xml
            //comprobante
            'version' => $version,
            'serie' => $serie,
            'Folio' => $Folio,
            'Fecha' => $Fecha,
            'Sello' => $Sello,
            'formaPago' => $formaPago,
            'tokenformaPago' => $selectFpago[0]->token_formapago,
            'noCertificado' => $noCertificado,
            'certificado' => $certificado,
            'SubTotal' => $SubTotal,
            'Moneda' => $Moneda,
            'tokenMoneda' => $selectMoneda[0]->token_monedas,
            'tipoCambio' => $tipoCambio,
            'Total' => $Total,
            'confirmacion' => $confirmacion,
            'TipoDeComprobante' => $TipoDeComprobante,
            'MetodoPago' => $MetodoPago,
            'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
            'LugarExpedicion' => $LugarExpedicion,
            //comprobante
            'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
            'uuid' => $verifiedCfdiRelacionadosuuid,
            //emisor
            'emisorRfc' => $RfcEmi,
            'emisorNombre' => $nombre,
            'emisorRegimenFiscal' => $regimenFiscal,
            //receptor
            'receptorRfc' => $RfcRec,
            'receptorUsoCFDI' => $UsoCFDI,
            'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
            //conceptos    
            'conceptos' => $arrayListaConceptos,
            //impuestos    
            'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
            'impuestosRetenciones' => $arrayImpuestosRetenciones,
            'impuestosTraslados' => $arrayImpuestosTraslados,
            //complemento 
            'compluuidComplemento' => $uuidComplemento,
            'complfechaTimbrado' => $fechaTimbrado,
            'complRfcProvCertif' => $RfcProvCertif,
            'complSelloCFD' => $SelloCFD,
            'complNoCertificadoSAT' => $NoCertificadoSAT,
            'complSelloSAT' => $SelloSAT,
          );
        } else {
          $dataMensaje = array(
            'status' => 'errorValidate',
            'code' => 200,
            'arrayErroresComprobante' => $arrayErroresComprobante,
            'arrayErroresEmisor' => $arrayErroresEmisor,
            'arrayErroresReceptor' => $arrayErroresReceptor,
            'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
            'arrayErroresConceptos' => $arrayErroresConceptos,
            'arrayErroresImpuestos' => $arrayErroresImpuestos,
            'arrayErroresComplemento' => $arrayErroresComplemento,
            'message' => 'xml invalido, revise informe de errores',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function validaEstructXmlRecepcion_de_pagos(Request $request){
    $JwtAuth = new \JwtAuth();
    $imageServ = $request->file('imagenEvidenciaXMl');

    $arrayErroresComprobante = array();
    $arrayErroresEmisor = array();
    $arrayErroresReceptor = array();
    $arrayErroresCfdiRelacionados = array();
    $arrayListaConceptos = array();
    $arrayListaImpuestosConceptos = array();
    $arrayErroresConceptos = array();
    $arrayImpuestosRetenciones = array();
    $arrayImpuestosTraslados = array();
    $arrayErroresImpuestos = array();
    $arrayErroresComplemento = array();

    $proveedor = $request->input('proveedor');
    $parametros = json_decode($proveedor);
    $parametrosArray = json_decode($proveedor, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'emisor' => 'required|string',
        'receptor' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];

        $schama_tres = "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd";
        $schama_cuatro = "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd";

        $http_cfdi3 = "http://www.sat.gob.mx/cfd/3";
        $http_cfdi4 = "http://www.sat.gob.mx/cfd/4";

        $verifiedCfdiComprobante = "";
        $verifiedCfdiEmisor = "";
        $verifiedCfdiReceptor = "";

        $verifiedCfdiRelacionados = "";
        $verifiedCfdiRelacionadostipoRelacion = "";
        $verifiedCfdiRelacionadosuuid = "";

        $verifiedCfdiConceptos = "";

        $verifiedCfdiImpuestos = "";
        $txttotalImpuestosRetenidos = "";
        $txttotalImpuestosTrasladados = "";

        $verifiedCfdiComplemento = "";

        $dataEmisor = DB::select("SELECT people.rfc FROM sos_personas AS people JOIN main_empresas AS emp WHERE people.id = emp.persona AND emp.emp_token = ?", [$emisor]);
        $rfc_emisor = strtolower($JwtAuth->desencriptar($dataEmisor[0]->rfc));

        $dataReceptor = DB::table("ingr_catalogo_clientes AS cKli")
          ->join("sos_personas AS client", "cKli.cliente", "=", "client.id")
          ->where(["cKli.token_cat_clientes" => $receptor])->get();
        $rfc_receptor = strtolower($JwtAuth->desencriptar($dataReceptor[0]->rfc));

        $xmlObject = simplexml_load_file($imageServ);

        $ns = $xmlObject->getNamespaces(true);
        $cfdi = $ns['cfdi'];
        $xsi = $ns['xsi'];
        $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

        $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
        $xmlObject->registerXPathNamespace('t', $ns['tfd']);

        //comprabante
        $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
        $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];
        $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
        $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
        $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

        $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
        $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
        $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
        $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
        $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
        $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
        $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
        $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

        if ($comprobante[0]["TipoCambio"] != NULL) {
          $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
        } else {
          $tipoCambio = 'no especificado';
        }

        $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

        if ($comprobante[0]["Confirmacion"] != NULL) {
          $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
        } else {
          $confirmacion = 'no especificado';
        }

        $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
        $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
        $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
        $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];

        if (
          isset($cfdi) && !empty($cfdi) && ($cfdi == $http_cfdi3 || $cfdi == $http_cfdi4) &&
          isset($xsi) && !empty($xsi) && $xsi == "http://www.w3.org/2001/XMLSchema-instance" &&
          isset($datSchama) && !empty($datSchama) && ($datSchama == $schama_tres || $datSchama == $schama_cuatro) &&
          isset($version) && !empty($version) && ($version == "3.3" || $version == "4.0") &&
          isset($serie) && !empty($serie) && strlen($serie) <= 25 && isset($Folio) && !empty($Folio) && strlen($Folio) <= 40 &&
          isset($Fecha) && !empty($Fecha) && strlen($Fecha) <= 19 && isset($Sello) && !empty($Sello) &&
          isset($formaPago) && !empty($formaPago) && strlen($formaPago) == 2 &&
          isset($noCertificado) && !empty($noCertificado) &&
          isset($certificado) && !empty($certificado) &&
          isset($SubTotal) && !empty($SubTotal) && isset($Moneda) &&
          !empty($Moneda) && strlen($Moneda) == 3 && isset($Total) && !empty($Total)  && isset($TipoDeComprobante) &&
          !empty($TipoDeComprobante) && $TipoDeComprobante == 'P' && isset($MetodoPago) && !empty($MetodoPago) &&
          strlen($MetodoPago) == 3 && isset($LugarExpedicion) && !empty($LugarExpedicion) && strlen($LugarExpedicion) == 5
        ) {

          if ($Moneda != 'MXN' && $Moneda != 'XXX') {
            if (
              isset($comprobante[0]["TipoCambio"]) && !empty($comprobante[0]["TipoCambio"]) &&
              $comprobante[0]["TipoCambio"] != NULL
            ) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "TipoCambio",
                "mensaje" => "el atributo TipoCambio no existe o esta vacio",
                "correccion" => "agregar o verificar atributo TipoCambio"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }

          if ($comprobante[0]["Confirmacion"]) {
            if (!empty($comprobante[0]["Confirmacion"]) && strlen($comprobante[0]["Confirmacion"]) == 5) {
              $verifiedCfdiComprobante = 'true';
            } else {
              $arrayError = array(
                "nodo" => "Comprobante",
                "atributo_nodohijo" => "Confirmacion",
                "mensaje" => "el atributo Confirmacion no existe,esta vacio o excede la cantidad de caracteres permitida (5)",
                "correccion" => "agregar o verificar atributo Confirmacion"
              );
              $arrayErroresComprobante[] = $arrayError;
              $verifiedCfdiComprobante = 'false';
            }
          } else {
            $verifiedCfdiComprobante = 'true';
          }
        } else {
          $verifiedCfdiComprobante = 'false';
          if (!isset($cfdi) || empty($cfdi) || ($cfdi != $http_cfdi3 && $cfdi != $http_cfdi4)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:cfdi",
              "mensaje" => 'el atributo xmlns:cfdi no existe,esta vacio o es dferente a "' . $http_cfdi3 . '" ó "' . $http_cfdi4 . '"',
              "correccion" => "agregar o verificar atributo xmlns:cfdi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($xsi) || empty($xsi) || $xsi != "http://www.w3.org/2001/XMLSchema-instance") {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xmlns:xsi",
              "mensaje" => 'el atributo xmlns:xsi no existe,esta vacio o es diferente a "http://www.w3.org/2001/XMLSchema-instance"',
              "correccion" => "agregar o verificar atributo xmlns:xsi"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($datSchama) || empty($datSchama) || ($datSchama != $schama_tres && $datSchama != $schama_cuatro)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "xsi:schemaLocation",
              "mensaje" => 'el atributo xsi:schemaLocation no existe,esta vacio o es diferente a "' . $schama_tres . '" ó "' . $schama_cuatro . '"',
              "correccion" => "agregar o verificar atributo xsi:schemaLocation"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (
            !isset($version) || empty($version) ||
            ($version != "3.3" && $version != "4.0")
          ) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Version",
              "mensaje" => "el atributo Version no existe,esta vacio o su version es incorrecta (3.3 o 4.0)" . $version,
              "correccion" => "agregar o verificar atributo Version"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($serie) || empty($serie) || strlen($serie) > 25) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Serie",
              "mensaje" => "el atributo Serie no existe,esta vacio o excede la cantidad de caracteres permitida (25)",
              "correccion" => "agregar o verificar atributo Serie"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Folio) || empty($Folio) || strlen($Folio) > 40) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Folio",
              "mensaje" => "el atributo Folio no existe,esta vacio o excede la cantidad de caracteres permitida (40)",
              "correccion" => "agregar o verificar atributo Folio"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Fecha) || empty($Fecha) || strlen($Fecha) > 19) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Fecha",
              "mensaje" => "el atributo Fecha no existe,esta vacio o excede la cantidad de caracteres permitida (19)",
              "correccion" => "agregar o verificar atributo Fecha"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Sello) || empty($Sello)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Sello",
              "mensaje" => "el atributo Sello no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Sello"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($formaPago) || empty($formaPago) || strlen($formaPago) != 2) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "FormaPago",
              "mensaje" => "el atributo FormaPago no existe,esta vacio o excede la cantidad de caracteres permitida (2)",
              "correccion" => "agregar o verificar atributo FormaPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($noCertificado) || empty($noCertificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "NoCertificado",
              "mensaje" => "el atributo NoCertificado no existe o esta vacio",
              "correccion" => "agregar o verificar atributo NoCertificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($certificado) || empty($certificado)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Certificado",
              "mensaje" => "el atributo Certificado no existeo o esta vacio",
              "correccion" => "agregar o verificar atributo Certificado"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($SubTotal) || empty($SubTotal)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "SubTotal",
              "mensaje" => "el atributo SubTotal no existe,esta vacio",
              "correccion" => "agregar o verificar atributo SubTotal"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Moneda) || empty($Moneda) || strlen($Moneda) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Moneda",
              "mensaje" => "el atributo Moneda no existe,esta vacio o excede l acantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo Moneda"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($Total) || empty($Total)) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "Total",
              "mensaje" => "el atributo Total no existe,esta vacio",
              "correccion" => "agregar o verificar atributo Total"
            );
            $arrayErroresComprobante[] = $arrayError;
            $mensajeError = 'nodo Total incorrecto';
          }
          if (!isset($TipoDeComprobante) || empty($TipoDeComprobante) || $TipoDeComprobante != 'P') {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "TipoComprobante",
              "mensaje" => "el atributo TipoComprobante no existe,esta vacio o es incorrecto",
              "correccion" => "agregar o verificar atributo TipoComprobante"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($MetodoPago) || empty($MetodoPago) || strlen($MetodoPago) != 3) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "MetodoPago",
              "mensaje" => "el atributo MetodoPago no existe,esta vacio o excede la cantidad de caracteres permitida (3)",
              "correccion" => "agregar o verificar atributo MetodoPago"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
          if (!isset($LugarExpedicion) || empty($LugarExpedicion) || strlen($LugarExpedicion) != 5) {
            $arrayError = array(
              "nodo" => "Comprobante",
              "atributo_nodohijo" => "LugarExpedicion",
              "mensaje" => "el atributo LugarExpedicion no existe,esta vacio o excede la cantidad de caracretes permitida (5)",
              "correccion" => "agregar o verificar atributo LugarExpedicion"
            );
            $arrayErroresComprobante[] = $arrayError;
          }
        }

        //nodo CfdiRelacionados
        $CfdiRelacionados = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados');
        if ($CfdiRelacionados) {
          if (!empty($CfdiRelacionados)) {
            $tipoRelacion = json_decode(json_encode($CfdiRelacionados[0]["TipoRelacion"]), true)['0'];
            $CfdiRelacionado = $xmlObject->xpath('//cfdi:Comprobante//cfdi:CfdiRelacionados//cfdi:CfdiRelacionado');
            $uuid = json_decode(json_encode($CfdiRelacionado[0]["UUID"]), true)['0'];
            if (
              isset($tipoRelacion) && !empty($tipoRelacion) && strlen($tipoRelacion) == 2 &&
              isset($CfdiRelacionado) && !empty($CfdiRelacionado) &&
              isset($uuid) && !empty($uuid)
            ) {
              $verifiedCfdiRelacionados = 'true';
              $verifiedCfdiRelacionadostipoRelacion = $tipoRelacion;
              $verifiedCfdiRelacionadosuuid = $uuid;
            } else {
              $verifiedCfdiRelacionados = 'false';
              if (!isset($tipoRelacion) || empty($tipoRelacion) || strlen($tipoRelacion) != 2) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionados",
                  "atributo_nodohijo" => "TipoRelacion",
                  "mensaje" => "el atributo TipoRelacion no existe,esta vacio, o excede el tamaño permitido",
                  "correccion" => "agregar o verificar atributo TipoRelacion Ej: 04"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($CfdiRelacionado) || empty($CfdiRelacionado)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "---",
                  "mensaje" => "el nodo CfdiRelacionado no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
              if (!isset($uuid) || empty($uuid)) {
                $arrayError = array(
                  "nodo" => "CfdiRelacionado",
                  "atributo_nodohijo" => "UUID",
                  "mensaje" => "el nodo UUID no existe o viene vacio",
                  "correccion" => "---"
                );
                $arrayErroresCfdiRelacionados[] = $arrayError;
              }
            }
          } else {
            $arrayError = array(
              "nodo" => "CfdiRelacionados",
              "atributo_nodohijo" => "---",
              "mensaje" => "el nodo CfdiRelacionados no existe o viene vacio",
              "correccion" => "---"
            );
            $arrayErroresCfdiRelacionados[] = $arrayError;
            $verifiedCfdiRelacionados = 'false';
          }
        } else {
          $verifiedCfdiRelacionados = 'true';
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad2 ".$Fecha]);

        //nodo emisor
        $Emisor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Emisor');
        $RfcEmi = strtolower(json_decode(json_encode($Emisor[0]["Rfc"]), true)['0']);
        $nombre = json_decode(json_encode($Emisor[0]["Nombre"]), true)['0'];
        $regimenFiscal = json_decode(json_encode($Emisor[0]["RegimenFiscal"]), true)['0'];
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad3 ".$Fecha]);

        if (
          isset($RfcEmi) && !empty($RfcEmi) && strlen($RfcEmi) >= 12 && strlen($RfcEmi) <= 13 &&
          $RfcEmi == $rfc_emisor &&
          isset($nombre) &&
          !empty($nombre) && isset($regimenFiscal) && !empty($regimenFiscal) && strlen($regimenFiscal) == 3
        ) {
          $verifiedCfdiEmisor = 'true';
        } else {
          $verifiedCfdiEmisor = 'false';
          if (!isset($RfcEmi) || empty($RfcEmi) || (strlen($RfcEmi) != 12 && strlen($RfcEmi) != 13)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if ($RfcEmi != $rfc_emisor) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del emisor de este documento no coincide con el rfc del proveedor seleccionado",
              "correccion" => "el rfc del proveedor seleccionado debe ser " . $RfcEmi
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($nombre) || empty($nombre)) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "Nombre",
              "mensaje" => "el atributo Nombre no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Nombre"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
          if (!isset($regimenFiscal) || empty($regimenFiscal) || strlen($regimenFiscal) != 3) {
            $arrayError = array(
              "nodo" => "Emisor",
              "atributo_nodohijo" => "RegimenFiscal",
              "mensaje" => "el atributo RegimenFiscal no existe o esta vacio o excede la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo RegimenFiscal"
            );
            $arrayErroresEmisor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad4 ".$Fecha]);

        //nodo receptor
        $Receptor = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Receptor');
        $RfcRec = strtolower(json_decode(json_encode($Receptor[0]["Rfc"]), true)['0']);
        $UsoCFDI = json_decode(json_encode($Receptor[0]["UsoCFDI"]), true)['0'];
        $selectUsoCFDI = DB::select("SELECT token_uso_cfdi FROM teci_uso_cfdi WHERE clave_uso = ?", [$UsoCFDI]);
        if (
          isset($RfcRec) && !empty($RfcRec) && (strlen($RfcRec) == 12 || strlen($RfcRec) == 13) &&
          $RfcRec == $rfc_receptor && isset($UsoCFDI) && !empty($UsoCFDI) && strlen($UsoCFDI) == 3
        ) {
          $verifiedCfdiReceptor = 'true';
        } else {
          $verifiedCfdiReceptor = 'false';
          if (!isset($RfcRec) || empty($RfcRec) || (strlen($RfcRec) != 12 && strlen($RfcRec) != 13)) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el atributo Rfc no existe o esta vacio",
              "correccion" => "agregar o verificar atributo Rfc"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if ($RfcRec != $rfc_receptor) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "Rfc",
              "mensaje" => "el rfc del receptor de este documento no coincide con el rfc de su empresa",
              "correccion" => "el rfc de su empresa debe ser " . $rfc_company
            );
            $arrayErroresReceptor[] = $arrayError;
          }
          if (!isset($UsoCFDI) || empty($UsoCFDI) || strlen($UsoCFDI) != 3) {
            $arrayError = array(
              "nodo" => "Receptor",
              "atributo_nodohijo" => "UsoCFDI",
              "mensaje" => "el atributo UsoCFDI no existe, esta vacio o excede el la cantidad de caracteres permitidos (3)",
              "correccion" => "agregar o verificar atributo UsoCFDI"
            );
            $arrayErroresReceptor[] = $arrayError;
          }
        }
        //return response()->json(["status" => "error","code" => 200,"message" => "cantidad5 ".$Fecha]);

        //nodo conceptos
        $countConceptos = 0;
        $conceptos = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos');
        $forConcepto = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto');
        if (isset($conceptos) && !empty($conceptos)) {
          for ($i = 0; $i < count($forConcepto); $i++) {
            $verifiedCfdiConceptosConcepto = "";
            $verifiedCfdiConceptosImpuestos = "";
            $verifiedCfdiConceptosImpuestosRetenciones = "";
            $verifiedCfdiConceptosImpuestosTraslados = "";

            $claveProdServ = json_decode(json_encode($forConcepto[$i]["ClaveProdServ"]), true)['0'];
            $noIdentificacion = $forConcepto[$i]["NoIdentificacion"];
            $resultnoIdentificacion = "";
            $cantidad = json_decode(json_encode($forConcepto[$i]["Cantidad"]), true)['0'];
            $claveUnidad = json_decode(json_encode($forConcepto[$i]["ClaveUnidad"]), true)['0'];
            $unidad = json_decode(json_encode($forConcepto[$i]["Unidad"]), true)['0'];
            $descripcion = json_decode(json_encode($forConcepto[$i]["Descripcion"]), true)['0'];
            $explodeUnitario = explode('.', $forConcepto[$i]["ValorUnitario"]);
            $valorUnitario = json_decode(json_encode($forConcepto[$i]["ValorUnitario"]), true)['0'];
            $importe = json_decode(json_encode($forConcepto[$i]["Importe"][0]), true)['0'];
            $explodeImporte = explode('.', $forConcepto[$i]["Importe"]);

            if (
              isset($claveProdServ) && !empty($claveProdServ) && strlen($claveProdServ) == 8
              && isset($cantidad) && !empty($cantidad)
              && isset($claveUnidad) && !empty($claveUnidad) && strlen($claveUnidad) == 3
              && isset($unidad) && !empty($unidad)
              && isset($descripcion) && !empty($descripcion)
              && isset($valorUnitario) && !empty($valorUnitario) && strlen($explodeUnitario[1]) <= 6
              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
            ) {
              if (isset($noIdentificacion)) {
                if (!empty($noIdentificacion) && strlen($noIdentificacion) <= 100) {
                  $resultnoIdentificacion = json_decode(json_encode($noIdentificacion), true)['0'];
                  $verifiedCfdiConceptosConcepto = 'true';
                } else {
                  $verifiedCfdiConceptosConcepto = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "NoIdentificacion",
                    "mensaje" => "el atributo NoIdentificacion esta vacio o sobrepasa el limite de caracteres permitidos (100)",
                    "correccion" => "agregar o verificar nodo NoIdentificacion"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosConcepto = 'true';
              }

              if (isset($forConcepto[$i]["Descuento"])) {
                $explodeDescuento = explode('.', $forConcepto[$i]["Descuento"]);
                if (!empty($forConcepto[$i]["Descuento"]) && strlen($explodeDescuento[1]) <= 6) {
                  $resultDescuento = json_decode(json_encode($forConcepto[$i]["Descuento"]), true)['0'];
                } else {
                  $verifiedCfdiConceptosDescuento = 'false';
                  $arrayError = array(
                    "nodo" => "Conceptos",
                    "atributo_nodohijo" => "Descuento",
                    "mensaje" => "el atributo Descuento esta vacio o sobrepasa el limite de caracteres permitidos (6)",
                    "correccion" => "agregar o verificar nodo Descuento"
                  );
                  $arrayErroresConceptos[] = $arrayError;
                }
              } else {
                $verifiedCfdiConceptosDescuento = 'true';
                $resultDescuento = '---';
              }

              $medida_unidad = DB::select("SELECT token_unidad_medida FROM teci_unidad_medida WHERE sat_clave = ?", [$claveUnidad]);

              if ($verifiedCfdiConceptosConcepto == 'true') {
                //nodo impuestos
                $arrayImpuestosCncRetenciones = array();
                $arrayImpuestosCncTraslados = array();
                $impuestos = $forConcepto[$i]->xpath('cfdi:Impuestos');
                if ($impuestos) {
                  if (isset($impuestos) && !empty($impuestos)) {
                    $retenciones = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones');

                    if ($retenciones) {
                      if (!empty($retenciones)) {
                        $countRetencion = 0;
                        $retencion = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Retenciones//cfdi:Retencion');
                        if (isset($retencion) && !empty($retencion)) {
                          foreach ($retencion as $forRetencion) {
                            $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);

                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countRetencion;
                              $arrayRetencionFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncRetenciones[] = $arrayRetencionFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Retencion",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countRetencion == count($retencion)) {
                            $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Retenciones Retencion",
                            "mensaje" => "el nodo Retencion no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosRetenciones = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Retenciones",
                          "mensaje" => "el nodo Retenciones no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Retenciones que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                    }
                    $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;

                    $traslados = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados');
                    if ($traslados) {
                      if (!empty($traslados)) {
                        $countTraslado = 0;
                        $traslado = $forConcepto[$i]->xpath('cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado');
                        if (isset($traslado) && !empty($traslado)) {
                          foreach ($traslado as $forTtraslado) {
                            $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                            $explodeBase = explode('.', $base);
                            $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                            $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                            $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                            $importeImp = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                            $explodeImporte = explode('.', $importeImp);
                            if (
                              isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                              && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                              && isset($tipoFactor) && !empty($tipoFactor)
                              && isset($TasaOCuota) && !empty($TasaOCuota)
                              && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                            ) {
                              ++$countTraslado;
                              $arrayTrasladoFor = array(
                                "Base" => $base,
                                "Impuesto" => $impuesto,
                                "TipoFactor" => $tipoFactor,
                                "TasaOCuota" => $TasaOCuota,
                                "Importe" => $importeImp,
                              );
                              $arrayImpuestosCncTraslados[] = $arrayTrasladoFor;
                            } else {
                              if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Base",
                                  "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Base"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Impuesto",
                                  "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                                  "correccion" => "agregar o verificar nodo Impuesto"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($tipoFactor) || empty($tipoFactor)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TipoFactor",
                                  "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TipoFactor"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "TasaOCuota",
                                  "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                                  "correccion" => "agregar o verificar nodo TasaOCuota"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                                $arrayError = array(
                                  "nodo" => "Traslado",
                                  "atributo_nodohijo" => "Importe",
                                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                                  "correccion" => "agregar o verificar nodo Importe"
                                );
                                $arrayErroresConceptos[] = $arrayError;
                              }
                            }
                          }
                          if ($countTraslado == count($traslado)) {
                            $verifiedCfdiConceptosImpuestosTraslados = 'true';
                          }
                        } else {
                          $verifiedCfdiConceptosImpuestosTraslados = 'false';
                          $arrayError = array(
                            "nodo" => "Conceptos",
                            "atributo_nodohijo" => "Impuestos Traslados Traslado",
                            "mensaje" => "el nodo Traslado no existe o esta vacio",
                            "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                          );
                          $arrayErroresConceptos[] = $arrayError;
                        }
                      } else {
                        $verifiedCfdiConceptosImpuestosTraslados = 'false';
                        $arrayError = array(
                          "nodo" => "Conceptos",
                          "atributo_nodohijo" => "Impuestos Traslados",
                          "mensaje" => "el nodo Traslados no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo Traslados que se incluye en el nodo Impuestos"
                        );
                        $arrayErroresConceptos[] = $arrayError;
                      }
                    } else {
                      $verifiedCfdiConceptosImpuestosTraslados = 'true';
                    }
                    $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                    if (
                      $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                      $verifiedCfdiConceptosImpuestosTraslados == 'true'
                    ) {
                      $verifiedCfdiConceptosImpuestos = 'true';
                    }
                  } else {
                    $verifiedCfdiConceptosImpuestos = 'false';
                    $arrayError = array(
                      "nodo" => "Conceptos",
                      "atributo_nodohijo" => "Impuestos",
                      "mensaje" => "el nodo Impuestos no existe o esta vacio",
                      "correccion" => "agregar o verificar nodo Impuestos que se incluye en el nodo Concepto"
                    );
                    $arrayErroresConceptos[] = $arrayError;
                  }
                } else {
                  $verifiedCfdiConceptosImpuestosRetenciones = 'true';
                  $verifiedCfdiConceptosImpuestosTraslados = 'true';
                  $verifiedCfdiConceptosImpuestos = 'true';
                  $arrayListaImpuestosConceptos[0] = $arrayImpuestosCncRetenciones;
                  $arrayListaImpuestosConceptos[1] = $arrayImpuestosCncTraslados;
                }
              }
              if (
                $verifiedCfdiConceptosConcepto == 'true' &&
                $verifiedCfdiConceptosDescuento == 'true' &&
                $verifiedCfdiConceptosImpuestos == 'true' &&
                $verifiedCfdiConceptosImpuestosRetenciones == 'true' &&
                $verifiedCfdiConceptosImpuestosTraslados == 'true'
              ) {

                ++$countConceptos;
                $arrayforeachConcept = array(
                  "claveProdServ" => $claveProdServ,
                  "noIdentificacion" => $resultnoIdentificacion,
                  "cantidad" => $cantidad,
                  "claveUnidad" => $claveUnidad,
                  "unidad" => $unidad,
                  "token_unidad_medida" => $medida_unidad[0]->token_unidad_medida,
                  "descripcion" => $descripcion,
                  "valorUnitario" => $valorUnitario,
                  "importe" => $importe,
                  "descuento" => $resultDescuento,
                  "impuestos" => $arrayListaImpuestosConceptos,
                );
                $arrayListaConceptos[] = $arrayforeachConcept;
              }
            } else {
              $verifiedCfdiConceptosConcepto = 'false';
              if (!isset($claveProdServ) || empty($claveProdServ) || strlen($claveProdServ) != 8) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveProdServ",
                  "mensaje" => "el atributo ClaveProdServ no existe o esta vacio",
                  "correccion" => "agregar o verificar atributo ClaveProdServ"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($cantidad) || empty($cantidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Cantidad",
                  "mensaje" => "el atributo Cantidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Cantidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($claveUnidad) || empty($claveUnidad) || strlen($claveUnidad) != 3) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ClaveUnidad",
                  "mensaje" => "el atributo ClaveUnidad no existe, esta vacio o no cumple con la cantidad de caracteres requeridos (3)",
                  "correccion" => "agregar o verificar nodo ClaveUnidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($unidad) || empty($unidad)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Unidad",
                  "mensaje" => "el atributo Unidad no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Unidad"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($descripcion) || empty($descripcion)) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Descripcion",
                  "mensaje" => "el atributo Descripcion no existe o esta vacio",
                  "correccion" => "agregar o verificar nodo Descripcion"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($valorUnitario) || empty($valorUnitario) || strlen($explodeUnitario[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "ValorUnitario",
                  "mensaje" => "el atributo ValorUnitario no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo ValorUnitario"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
              if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                $arrayError = array(
                  "nodo" => "Conceptos",
                  "atributo_nodohijo" => "Importe",
                  "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                  "correccion" => "agregar o verificar nodo Importe"
                );
                $arrayErroresConceptos[] = $arrayError;
              }
            }
          }

          if ($countConceptos == count($forConcepto)) {
            $verifiedCfdiConceptos = 'true';
          }
        } else {
          $verifiedCfdiConceptos = 'false';
          $arrayError = array(
            "nodo" => "Conceptos",
            "atributo_nodohijo" => "---",
            "mensaje" => "el nodo Conceptos no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Conceptos"
          );
          $arrayErroresConceptos[] = $arrayError;
        }

        //nodo impuestos
        $impuestosCfdi = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos');
        if ($impuestosCfdi && count($impuestosCfdi) > 0) {
          if (isset($impuestosCfdi) && !empty($impuestosCfdi)) {
            $verifiedCfdiImpuestosRetenciones = "";
            $verifiedCfdiImpuestosRetencionesRetencion = "";
            $verifiedCfdiImpuestosTraslados = "";
            $verifiedCfdiImpuestosTrasladosTraslado = "";
            $retenciones = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones');
            if ($retenciones) {
              $totalImpuestosRetenidos = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosRetenidos"]), true)['0'];
              if (!empty($retenciones) && isset($totalImpuestosRetenidos) && !empty($totalImpuestosRetenidos)) {
                $txttotalImpuestosRetenidos = $totalImpuestosRetenidos;
                $countRetenidoImp = 0;
                $retencion = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                if (isset($retencion) && !empty($retencion)) {
                  foreach ($retencion as $forRetencion) {
                    if (isset($forRetencion["Base"])) {
                      $base = json_decode(json_encode($forRetencion["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);

                    if (isset($forRetencion["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forRetencion["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forRetencion["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forRetencion["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forRetencion["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forRetencion["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forRetencion["Importe"])) {
                      $importe = json_decode(json_encode($forRetencion["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6
                      && isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countRetenidoImp;
                      $arrayTrasladoFor = array(
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosRetenciones[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Retencion",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }
                  if ($countRetenidoImp == count($retencion)) {
                    $verifiedCfdiImpuestosRetenciones = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosRetenciones = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones Retencion",
                    "mensaje" => "el nodo Retencion no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retencion que se incluye en el nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosRetenciones = 'false';
                if (empty($retenciones)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Retenciones",
                    "mensaje" => "el nodo Retenciones no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Retenciones"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosRetenidos) || empty($totalImpuestosRetenidos)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosRetenidos",
                    "mensaje" => "el atributo TotalImpuestosRetenidos no existe o esta vacio",
                    "correccion" => "agregar o verificar atributo TotalImpuestosRetenidos"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosRetenciones = 'true';
            }
            $arrayListaImpuestos[0] = $arrayImpuestosRetenciones;

            $traslados = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados');
            if ($traslados) {
              $totalImpuestosTrasladados = json_decode(json_encode($impuestosCfdi[0]["TotalImpuestosTrasladados"]), true)['0'];
              if (!empty($traslados) && isset($totalImpuestosTrasladados) && !empty($totalImpuestosTrasladados)) {
                $txttotalImpuestosTrasladados = $totalImpuestosTrasladados;
                $countTrasladoImp = 0;
                $traslado = $xmlObject->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                if (isset($traslado) && !empty($traslado)) {
                  foreach ($traslado as $forTtraslado) {
                    if (isset($forTtraslado["Base"])) {
                      $base = json_decode(json_encode($forTtraslado["Base"]), true)['0'];
                    } else {
                      $base = '0.00';
                    }
                    $explodeBase = explode('.', $base);
                    if (isset($forTtraslado["Impuesto"])) {
                      $impuesto = json_decode(json_encode($forTtraslado["Impuesto"]), true)['0'];
                    } else {
                      $impuesto = 'xxx';
                    }

                    if (isset($forTtraslado["TipoFactor"])) {
                      $tipoFactor = json_decode(json_encode($forTtraslado["TipoFactor"]), true)['0'];
                    } else {
                      $tipoFactor = 'xxxx';
                    }

                    if (isset($forTtraslado["TasaOCuota"])) {
                      $TasaOCuota = json_decode(json_encode($forTtraslado["TasaOCuota"]), true)['0'];
                    } else {
                      $TasaOCuota = '0.00';
                    }

                    if (isset($forTtraslado["Importe"])) {
                      $importe = json_decode(json_encode($forTtraslado["Importe"]), true)['0'];
                    } else {
                      $importe = '0.00';
                    }
                    $explodeImporte = explode('.', $importe);

                    if (
                      isset($base) && !empty($base) && strlen($explodeBase[1]) <= 6 &&
                      isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                      && isset($tipoFactor) && !empty($tipoFactor)
                      && isset($TasaOCuota) && !empty($TasaOCuota)
                      && isset($importe) && !empty($importe) && strlen($explodeImporte[1]) <= 6
                    ) {
                      ++$countTrasladoImp;
                      $arrayTrasladoFor = array(
                        "Base" => $base,
                        "Impuesto" => $impuesto,
                        "TipoFactor" => $tipoFactor,
                        "TasaOCuota" => $TasaOCuota,
                        "Importe" => $importe,
                      );
                      $arrayImpuestosTraslados[] = $arrayTrasladoFor;
                    } else {
                      if (!isset($base) || empty($base) || strlen($explodeBase[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Base",
                          "mensaje" => "el atributo Base no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Base"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Impuesto",
                          "mensaje" => "el atributo Impuesto no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)",
                          "correccion" => "agregar o verificar nodo Impuesto"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($tipoFactor) || empty($tipoFactor)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TipoFactor",
                          "mensaje" => "el atributo TipoFactor no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TipoFactor"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "TasaOCuota",
                          "mensaje" => "el atributo TasaOCuota no existe o esta vacio",
                          "correccion" => "agregar o verificar nodo TasaOCuota"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                      if (!isset($importe) || empty($importe) || strlen($explodeImporte[1]) > 6) {
                        $arrayError = array(
                          "nodo" => "Traslado",
                          "atributo/nodohijo" => "Importe",
                          "mensaje" => "el atributo Importe no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 6)",
                          "correccion" => "agregar o verificar nodo Importe"
                        );
                        $arrayErroresImpuestos[] = $arrayError;
                      }
                    }
                  }

                  if ($countTrasladoImp == count($traslado)) {
                    $verifiedCfdiImpuestosTraslados = 'true';
                  }
                } else {
                  $verifiedCfdiImpuestosTraslados = 'false';
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados Traslado",
                    "mensaje" => "el nodo Traslado no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslado que se incluye en el nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              } else {
                $verifiedCfdiImpuestosTraslados = 'false';
                if (empty($traslados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "Traslados",
                    "mensaje" => "el nodo Traslados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo Traslados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
                if (!isset($totalImpuestosTrasladados) || empty($totalImpuestosTrasladados)) {
                  $arrayError = array(
                    "nodo" => "Impuestos",
                    "atributo/nodohijo" => "TotalImpuestosTrasladados",
                    "mensaje" => "el nodo TotalImpuestosTrasladados no existe o esta vacio",
                    "correccion" => "agregar o verificar nodo TotalImpuestosTrasladados"
                  );
                  $arrayErroresImpuestos[] = $arrayError;
                }
              }
            } else {
              $verifiedCfdiImpuestosTraslados = 'true';
            }
            $arrayListaImpuestos[1] = $arrayImpuestosTraslados;

            if ($verifiedCfdiImpuestosTraslados == 'true' || $verifiedCfdiImpuestosRetenciones == 'true') {
              $verifiedCfdiImpuestos = 'true';
            }
          } else {
            $verifiedCfdiImpuestos = 'false';
            $arrayError = array(
              "nodo" => "Impuestos",
              "atributo/nodohijo" => "---",
              "mensaje" => "el nodo Impuestos no existe o esta vacio",
              "correccion" => "agregar o verificar nodo Impuestos"
            );
            $arrayErroresImpuestos[] = $arrayError;
          }
        } else {
          $verifiedCfdiImpuestos = 'true';
        }

        //nodo complemento
        $complemento = $xmlObject->xpath('//cfdi:Comprobante//cfdi:Complemento//t:TimbreFiscalDigital');
        $uuidComplemento = json_decode(json_encode($complemento[0]["UUID"]), true)['0'];
        $fechaTimbrado = json_decode(json_encode($complemento[0]["FechaTimbrado"]), true)['0'];
        $RfcProvCertif = json_decode(json_encode($complemento[0]["RfcProvCertif"]), true)['0'];
        $SelloCFD = json_decode(json_encode($complemento[0]["SelloCFD"]), true)['0'];
        $NoCertificadoSAT = json_decode(json_encode($complemento[0]["NoCertificadoSAT"]), true)['0'];
        $SelloSAT = json_decode(json_encode($complemento[0]["SelloSAT"]), true)['0'];

        if (isset($complemento) && !empty($complemento)) {
          if (
            isset($uuidComplemento) && !empty($uuidComplemento)
            && isset($fechaTimbrado) && !empty($fechaTimbrado)
            && isset($RfcProvCertif) && !empty($RfcProvCertif)
            && isset($SelloCFD) && !empty($SelloCFD)
            && isset($NoCertificadoSAT) && !empty($NoCertificadoSAT)
            && isset($SelloSAT) && !empty($SelloSAT)
          ) {
            $verifiedCfdiComplemento = 'true';
          } else {
            $verifiedCfdiComplemento = 'false';
            if (!isset($uuidComplemento) || empty($uuidComplemento)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "UUID",
                "mensaje" => "el atributo UUID no existe o esta vacio",
                "correccion" => "agregar o verificar atributo UUID"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($fechaTimbrado) || empty($fechaTimbrado)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "FechaTimbrado",
                "mensaje" => "el atributo FechaTimbrado no existe o esta vacio",
                "correccion" => "agregar o verificar atributo FechaTimbrado"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($RfcProvCertif) || empty($RfcProvCertif)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "RfcProvCertif",
                "mensaje" => "el atributo RfcProvCertif no existe o esta vacio",
                "correccion" => "agregar o verificar atributo RfcProvCertif"
              );
              $arrayErroresComplemento[] = $arrayError;
            }
            if (!isset($SelloCFD) || empty($SelloCFD)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloCFD",
                "mensaje" => "el atributo SelloCFD no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloCFD"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloCFD incorrecto';
            }
            if (!isset($NoCertificadoSAT) || empty($NoCertificadoSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "NoCertificadoSAT",
                "mensaje" => "el atributo NoCertificadoSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo NoCertificadoSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID NoCertificadoSAT incorrecto';
            }
            if (!isset($SelloSAT) || empty($SelloSAT)) {
              $arrayError = array(
                "nodo" => "Complemento",
                "atributo_nodohijo" => "SelloSAT",
                "mensaje" => "el atributo SelloSAT no existe o esta vacio",
                "correccion" => "agregar o verificar atributo SelloSAT"
              );
              $arrayErroresComplemento[] = $arrayError;
              $mensajeError = 'nodo UUID SelloSAT incorrecto';
            }
          }
        } else {
          $verifiedCfdiComplemento = 'false';
          $arrayError = array(
            "nodo" => "Complemento",
            "atributo_nodohijo" => "TimbreFiscalDigital",
            "mensaje" => "el nodo Complemento-TimbreFiscalDigital no existe o esta vacio",
            "correccion" => "agregar o verificar nodo Complemento-TimbreFiscalDigital"
          );
          $arrayErroresComplemento[] = $arrayError;
        }

        if (
          $verifiedCfdiComprobante == 'true' && $verifiedCfdiEmisor == 'true' && $verifiedCfdiReceptor == 'true' &&
          $verifiedCfdiRelacionados == 'true' && $countConceptos == count($forConcepto) && $verifiedCfdiImpuestos == 'true' &&
          $verifiedCfdiComplemento == 'true'
        ) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'xml valido',
            //informacion del xml
            //comprobante
            'version' => $version,
            'serie' => $serie,
            'Folio' => $Folio,
            'Fecha' => $Fecha,
            'Sello' => $Sello,
            'formaPago' => $formaPago,
            'tokenformaPago' => $selectFpago[0]->token_formapago,
            'noCertificado' => $noCertificado,
            'certificado' => $certificado,
            'SubTotal' => $SubTotal,
            'Moneda' => $Moneda,
            'tokenMoneda' => $selectMoneda[0]->token_monedas,
            'tipoCambio' => $tipoCambio,
            'Total' => $Total,
            'confirmacion' => $confirmacion,
            'TipoDeComprobante' => $TipoDeComprobante,
            'MetodoPago' => $MetodoPago,
            'tokenMetodoPago' => $selectMetodoPago[0]->token_metodopago,
            'LugarExpedicion' => $LugarExpedicion,
            //comprobante
            'tipoRelacion' => $verifiedCfdiRelacionadostipoRelacion,
            'uuid' => $verifiedCfdiRelacionadosuuid,
            //emisor
            'emisorRfc' => $RfcEmi,
            'emisorNombre' => $nombre,
            'emisorRegimenFiscal' => $regimenFiscal,
            //receptor
            'receptorRfc' => $RfcRec,
            'receptorUsoCFDI' => $UsoCFDI,
            'token_uso_cfdi' => $selectUsoCFDI[0]->token_uso_cfdi,
            //conceptos    
            'conceptos' => $arrayListaConceptos,
            //impuestos    
            'TotalImpuestosRetenidos' => $txttotalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $txttotalImpuestosTrasladados,
            'impuestosRetenciones' => $arrayImpuestosRetenciones,
            'impuestosTraslados' => $arrayImpuestosTraslados,
            //complemento 
            'compluuidComplemento' => $uuidComplemento,
            'complfechaTimbrado' => $fechaTimbrado,
            'complRfcProvCertif' => $RfcProvCertif,
            'complSelloCFD' => $SelloCFD,
            'complNoCertificadoSAT' => $NoCertificadoSAT,
            'complSelloSAT' => $SelloSAT,
          );
        } else {
          $dataMensaje = array(
            'status' => 'errorValidate',
            'code' => 200,
            'arrayErroresComprobante' => $arrayErroresComprobante,
            'arrayErroresEmisor' => $arrayErroresEmisor,
            'arrayErroresReceptor' => $arrayErroresReceptor,
            'arrayErroresCfdiRelacionados' => $arrayErroresCfdiRelacionados,
            'arrayErroresConceptos' => $arrayErroresConceptos,
            'arrayErroresImpuestos' => $arrayErroresImpuestos,
            'arrayErroresComplemento' => $arrayErroresComplemento,
            'message' => 'xml invalido, revise informe de errores',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function guardarFacturasXml(Request $request){
    $JwtAuth = new \JwtAuth();

    $json_data = $request->input('json');
    $parametros = json_decode($json_data);
    $parametrosArray = json_decode($json_data, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario invalido',
          'errors' => $validate->errors()
        );
      } else {
        $empresa = "";
        //return response()->json(['status'=>'error','code'=>200,'message'=>'elementos']);
        if ($parametrosArray['user_token'] != "xxxx") {
          $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
          $empresa = $usuario->emp_token;
        } else {
          $empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
        }

        $facturas = $_FILES["imgEvidencias"];
        $facturas_nombre = $facturas["name"];

        $selectEmp = DB::table("empresas AS emp")
          ->where(['emp.emp_token' => $empresa,])->get();

        if (count($facturas_nombre) != 0) {
          for ($i = 0; $i < count($facturas_nombre); $i++) {
            $nombre = $facturas_nombre[$i];
            $name_archivo = pathinfo($nombre, PATHINFO_FILENAME);
            $extension = explode(".", $name_archivo);
            $temporal = $facturas["tmp_name"][$i];

            $filepath = $selectEmp[0]->root_tkn . "/0005-cnt/facturas/" . $extension[0] . "/";
            if (!file_exists(storage_path("/root/" . $filepath))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }
            Storage::putFileAs("/public/root/" . $filepath, $temporal, $nombre);

            if ($extension[1] == "xml") {
              $link_doc = Storage::path('public/root/' . $val->root_tkn .
                '/0005-cnt/facturas/' . $extension[0] . '/' . $nombre);
              $xmlObject = simplexml_load_file($link_doc);
              $ns = $xmlObject->getNamespaces(true);
              $cfdi = $ns['cfdi'];
              $xsi = $ns['xsi'];
              $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

              $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
              $xmlObject->registerXPathNamespace('t', $ns['tfd']);

              //comprabante
              $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
              $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];

              $token_factura = $JwtAuth->encriptarToken(time(), $nombre);
              $insertFact = DB::table('sos_facturas')
                ->insert(array(
                  "token_factura" => $token_factura,
                  "fecha_sistema" => time(),
                  "fecha_inicio" => time(),
                  "fecha_fin" => time(),
                  "ruta" => $name_archivo,
                  "archivo_xml" => $extension[0] . "xml",
                  "archivo_pdf" => $extension[0] . "pdf",
                  "tipo" => $TipoDeComprobante,
                  "status_factura" => TRUE,
                  "empresa" => $selectEmp[0]->id,
                ));
            }
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function consultaFacturasXml(Request $request){
    $JwtAuth = new \JwtAuth();
    $json_data = $request->input('json');
    $parametros = json_decode($json_data);
    $parametrosArray = json_decode($json_data, true);
    $arrayXmls = array();
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario invalido',
          'errors' => $validate->errors()
        );
      } else {
        $empresa = "";
        //return response()->json(['status'=>'error','code'=>200,'message'=>'elementos']);
        if ($parametrosArray['user_token'] != "" && $parametrosArray['user_token'] != "xxxx") {
          $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
          $empresa = $usuario->emp_token;
        } else {
          $empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
        }

        //echo $empresa;

        $select_facturas = DB::table('sos_facturas AS fact')
          ->join("empresas AS emp", "fact.empresa", "emp.id")
          ->where(["emp.emp_token" => $empresa,])->get();

        foreach ($select_facturas as $val) {
          $desglose_doc = array();
          $extension = explode(".", $val->archivo_xml);

          $link_pdf = Storage::path('public/root/' . $val->root_tkn .
            '/0005-cnt/facturas/' . $val->ruta . '/' . $val->archivo_pdf);
          $arch_pdf = $JwtAuth->encriptaBase64($link_pdf);

          $link_xml = Storage::path('public/root/' . $val->root_tkn .
            '/0005-cnt/facturas/' . $val->ruta . '/' . $val->archivo_xml);
          $arch_xml = $JwtAuth->encriptaBase64($link_xml);

          if ($extension[1] == "xml") {
            $xmlObject = simplexml_load_file($link_xml);
            $ns = $xmlObject->getNamespaces(true);
            $cfdi = $ns['cfdi'];
            $xsi = $ns['xsi'];
            $datSchama = $xmlObject->attributes('xsi', true)->schemaLocation;

            $xmlObject->registerXPathNamespace('c', $ns['cfdi']);
            $xmlObject->registerXPathNamespace('t', $ns['tfd']);

            //comprabante
            $comprobante = $xmlObject->xpath('//cfdi:Comprobante');
            $version = json_decode(json_encode($comprobante[0]['Version']), true)['0'];

            $serie = json_decode(json_encode($comprobante[0]["Serie"]), true)['0'];
            $Folio = json_decode(json_encode($comprobante[0]["Folio"]), true)['0'];
            $Fecha = json_decode(json_encode($comprobante[0]["Fecha"]), true)['0'];

            $Sello = json_decode(json_encode($comprobante[0]["Sello"]), true)['0'];
            $formaPago = json_decode(json_encode($comprobante[0]["FormaPago"]), true)['0'];
            $selectFpago = DB::select("SELECT token_formapago FROM teci_forma_pago WHERE clave = ?", [$formaPago]);
            $noCertificado = json_decode(json_encode($comprobante[0]["NoCertificado"]), true)['0'];
            $certificado = json_decode(json_encode($comprobante[0]["Certificado"]), true)['0'];
            $SubTotal = json_decode(json_encode($comprobante[0]["SubTotal"]), true)['0'];
            $Moneda = json_decode(json_encode($comprobante[0]["Moneda"]), true)['0'];
            $selectMoneda = DB::select("SELECT token_monedas FROM teci_catalogo_monedas WHERE codigo = ?", [$Moneda]);

            if ($comprobante[0]["TipoCambio"] != NULL) {
              $tipoCambio = json_decode(json_encode($comprobante[0]["TipoCambio"]), true)['0'];
            } else {
              $tipoCambio = 'no especificado';
            }

            $Total = json_decode(json_encode($comprobante[0]["Total"]), true)['0'];

            if ($comprobante[0]["Confirmacion"] != NULL) {
              $confirmacion = json_decode(json_encode($comprobante[0]["Confirmacion"]), true)['0'];
            } else {
              $confirmacion = 'no especificado';
            }

            $TipoDeComprobante = json_decode(json_encode($comprobante[0]["TipoDeComprobante"]), true)['0'];
            $MetodoPago = json_decode(json_encode($comprobante[0]["MetodoPago"]), true)['0'];
            $selectMetodoPago = DB::select("SELECT token_metodopago FROM teci_metodo_pago WHERE abrev = ?", [$MetodoPago]);
            $LugarExpedicion = json_decode(json_encode($comprobante[0]["LugarExpedicion"]), true)['0'];
          }

          $row = array(
            "token_factura" => $val->token_factura,
            "fecha_sistema" => $val->fecha_sistema,
            "fecha_inicio" => $val->fecha_inicio,
            "fecha_fin" => $val->fecha_fin,
            "ruta" => $val->ruta,
            "archivo_xml" => $val->archivo_xml,
            "archivo_pdf" => $val->archivo_pdf,
            "tipo" => $val->tipo,
            "status_factura" => $val->status_factura,
            "arch_pdf" => $arch_pdf,
            "arch_xml" => $arch_xml,
          );
          $arrayXmls[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'arrayxmls' => $arrayXmls
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function aduanas(){
    $dsn = sprintf('sqlite://%s/catalogos.db', __DIR__);
    $factory = new Factory();
    $satCatalogos = $factory->catalogosFromDsn($dsn);

    $aduanas = $satCatalogos->aduanas();
    $aduana = $aduanas->obtain('24');
    echo $aduana->texto();
  }

  public function visorEstadoXmlCFDICompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $json = $request->input('json');
    $parametros = json_decode($json);
    $parametrosArray = json_decode($json, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'emisor' => 'string',
        'receptor' => 'string',
        'uuid' => 'string',
        'total' => 'numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Cliente invalido',
          'errors' => $validate->errors()
        );
      } else {
        $emisor = $parametrosArray['emisor'];
        $receptor = $parametrosArray['receptor'];
        $uuid = $parametrosArray['uuid'];
        $total = $parametrosArray['total'];
        try {
          $soapResponse = $this->consultarEstadoSAT($emisor, $receptor, $uuid, $total);
          //print $soapResponse;
          $estado = $this->extraerEstadoCFDI($soapResponse);
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'estado' => $estado,
            'encontrado' => false,
          );
        } catch (Exception $e) {
          echo "Error: " . $e->getMessage();
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
