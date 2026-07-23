<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\LogisticaTransitoMain;
use App\Services\KardexService;

class EGRE_LogisticaComprasController extends Controller{
  protected $kardexService;

  public function __construct(KardexService $kardexService) {
    $this->kardexService = $kardexService;
  }

  //datos utiles para registros
  public function listaCFDICartaPorteUUID(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryComprobantesCPorte = DB::table("comprobante_carta_porte AS cfdi_cporte")//eegr_compras
    ->join("cfdi_comprobantes_fiscales AS cfdiMain","cfdi_cporte.comprobante_fiscal","=","cfdiMain.id") 
    ->join("cfdi_vinculacion_compras AS vinc_buy","cfdiMain.id", "=", "vinc_buy.comprobante_fiscal")
    ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
    ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    //->where("cfdiMain.cfdi_comprobante_tipo_de_comprobante", "<>", "T") logistica_transito_unidades 
    ->whereNotIn('cfdiMain.id', function ($query) {
      $query->select('cfdi_relacionado')->from('logistica_transito_unidades')
      ->whereNotNull('cfdi_relacionado');
    })
    ->where([
      "buy.status_compra" => TRUE, 
      "emp.empresa_token" => $empresa, 
      "users.usuario_token" => $usuario
    ])
    ->select(
      'cfdiMain.cfdi_comprobantes_token',
      'cfdiMain.cfdi_comprobante_tipo_de_comprobante',
      'cfdiMain.cfdi_complementoUUID',
      'buy.token_compras',
      'buy.folio_compra',
      'buy.post_folio',
      'cfdi_cporte.id_ccp'  
    )
    ->get();

    if ($queryComprobantesCPorte->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => "No se encontraron registros para carta porte."
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $cartas_porte = array();
      foreach ($queryComprobantesCPorte as $vCartaPorte) {
        $folio_compra = "COMP-".$JwtAuth->generarFolio($vCartaPorte->folio_compra).(!is_null($vCartaPorte->post_folio) ? '-'.$vCartaPorte->post_folio : '');
        $row = array(
          'cfdi_comprobante_registrado_token' => $vCartaPorte->cfdi_comprobantes_token,
          'cfdi_comprobante_registrado_tipo' => $vCartaPorte->cfdi_comprobante_tipo_de_comprobante,
          'cfdi_comprobante_registrado_uuid' => $vCartaPorte->cfdi_complementoUUID,
          'cfdi_comprobante_registrado_carta_porte_id_ccp' => $vCartaPorte->id_ccp,
          'cfdi_comprobante_registrado_compras' => $vCartaPorte->token_compras,
          "folio_compra" => $folio_compra,
        );
        $cartas_porte[] = $row;
      }
      
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'cartas_porte' => $cartas_porte,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function obtenerCFDICartaPorteUUID(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cfdi_comprobante_tipo' => 'required|string',
      'cfdi_complemento_uuid' => 'required|string',
      'cfdi_carta_porte_id' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta del CFDI o del complemento Carta Porte son requeridos o inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $cfdi_comprobante_tipo = $request->input('cfdi_comprobante_tipo');
      $cfdi_complemento_uuid = $request->input('cfdi_complemento_uuid');
      $cfdi_carta_porte_id = $request->input('cfdi_carta_porte_id');

      try {
        // 1. Buscamos el ID real de la compra a través de su Token
        $queryComprobantesCPorte = DB::table("comprobante_carta_porte AS cfdi_cporte")//eegr_compras
        ->join("cfdi_comprobantes_fiscales AS cfdiMain","cfdi_cporte.comprobante_fiscal","=","cfdiMain.id") 
        ->join("cfdi_vinculacion_compras AS vinc_buy","cfdiMain.id", "=", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "=", "buy.id")
        ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "buy.status_compra" => TRUE, 
          "emp.empresa_token" => $empresa, 
          "users.usuario_token" => $usuario,

          "cfdiMain.cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo,
          "cfdiMain.cfdi_complementoUUID" => $cfdi_complemento_uuid,
          "cfdi_cporte.id_ccp" => $cfdi_carta_porte_id
        ])
        ->select(
          //'cfdiMain.cfdi_comprobantes_token',
          //'cfdiMain.cfdi_comprobante_tipo_de_comprobante',
          //'cfdiMain.cfdi_complementoUUID',
          //'buy.token_compras',
          //'buy.folio_compra',
          //'buy.post_folio',
          'cfdi_cporte.id AS id_porte_carta',
          'cfdi_cporte.*'
        )
        ->get();

        if ($queryComprobantesCPorte->isEmpty()) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => "No se encontraron registros para el cfdi con UUID $cfdi_complemento_uuid."
          );
        } else {
          $JwtAuth = new \App\Helpers\JwtAuth();
          $porte_carta = array();
          //$folio_compra = "COMP-".$JwtAuth->generarFolio($comprobante->folio_compra).(!is_null($comprobante->post_folio) ? '-'.$comprobante->post_folio : '');
          
          foreach ($queryComprobantesCPorte as $vCartaPorte) {
            //ubicaciones
            $listUbicaciones = [];
            $queryUbicacionesCPorte = DB::table("carta_porte_ubicaciones")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryUbicacionesCPorte as $vcpUbica) {
              $listUbicaciones[] = [
                "TipoUbicacion" => $vcpUbica->tipo_ubicacion,//TipoUbicacion
                "IdUbicacion" => $vcpUbica->id_ubicacion,//IdUbicacion
                "RFCRemitenteDestinatario" => $vcpUbica->rfc_remitente_destinatario,//RFCRemitenteDestinatario
                "NombreRemitenteDestinatario" => $vcpUbica->nombre_remitente_destinatario,//NombreRemitenteDestinatario
                "NumRegIdTrib" => $vcpUbica->num_reg_id_trib,//NumRegIdTrib
                "ResidenciaFiscal" => $vcpUbica->residencia_fiscal,//ResidenciaFiscal
                "NumEstacion" => $vcpUbica->num_estacion,//NumEstacion
                "NombreEstacion" => $vcpUbica->nombre_estacion,//NombreEstacion
                "NavegacionTrafico" => $vcpUbica->navegacion_trafico,//NavegacionTrafico
                "FechaHoraSalidaLlegada" => $vcpUbica->fecha_hora_salida_llegada,//FechaHoraSalidaLlegada
                "TipoEstacion" => $vcpUbica->tipo_estacion,//TipoEstacion
                "DistanciaRecorrida" => $vcpUbica->distancia_recorrida,//DistanciaRecorrida
                "Calle" => $vcpUbica->calle,
                "NumeroExterior" => $vcpUbica->numero_exterior,
                "NumeroInterior" => $vcpUbica->numero_interior,
                "Colonia" => $vcpUbica->colonia,
                "Localidad" => $vcpUbica->localidad,
                "Referencia" => $vcpUbica->referencia,
                "Municipio" => $vcpUbica->municipio,
                "Estado" => $vcpUbica->estado,
                "Pais" => $vcpUbica->pais,
                "CodigoPostal" => $vcpUbica->codigo_postal,
              ];
            }
            
            //mercancias
            $listmercancias = [];
            $queryMercanciasCPorte = DB::table("carta_porte_mercancias_totales")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryMercanciasCPorte as $vcpMercan) {
              $mercanciaData = [];
              $queryMercancia = DB::table("carta_porte_mercancia_detalle")
              ->where("mercancias_totales",$vcpMercan->id)
              ->get();

              foreach ($queryMercancia as $vMerca) {
                $documentacionAduanera = []; //$documentacionAduanera[] = [];
                $queryDocAduanera = DB::table("carta_porte_mercancia_doc_aduanera")
                ->where("mercancias_totales",$vcpMercan->id)
                ->where("mercancia_detalle",$vMerca->id)
                ->get();
                
                foreach ($queryDocAduanera as $vDocAdu) {
                  $documentacionAduanera[] = [
                    "TipoDocumento" => $vDocAdu->tipo_documento,
                    "NumPedimento" => $vDocAdu->num_pedimento,
                    "IdentDocAduanero" => $vDocAdu->ident_doc_aduanero,
                    "RFCImpo" => $vDocAdu->rfc_impo
                  ];
                }

                $guiasIdentificacion = []; //$guiasIdentificacion[] = [];
                $queryIdentificacionGuias = DB::table("carta_porte_mercancia_guia_identificacion")
                ->where("mercancias_totales",$vcpMercan->id)
                ->where("mercancia_detalle",$vMerca->id)
                ->get();

                foreach ($queryIdentificacionGuias as $vGuia) {
                  $guiasIdentificacion[] = [
                    "NumeroGuiaIdentificacion" => $vGuia->numero_guia_identificacion,
                    "DescripGuiaIdentificacion" => $vGuia->descrip_guia_identificacion,
                    "PesoGuiaIdentificacion" => $vGuia->peso_guia_identificacion
                  ];
                }

                $cantidadTransporta = []; //$cantidadTransporta[] = [];
                $queryCantidadTransporta = DB::table("carta_porte_mercancia_cantidad_transporta")
                ->where("mercancias_totales",$vcpMercan->id)
                ->where("mercancia_detalle",$vMerca->id)
                ->get();

                foreach ($queryCantidadTransporta as $vCantTr) {
                  $cantidadTransporta[] = [
                    "Cantidad" => $vCantTr->cantidad,
                    "IDOrigen" => $vCantTr->id_origen,
                    "IDDestino" => $vCantTr->id_destino,
                    "CvesTransporte" => $vCantTr->cves_transporte,
                  ];
                }

                $detalleMercancia = []; //$detalleMercancia[] = [];
                $queryMercanciaDetalle = DB::table("carta_porte_mercancia_detalle_mercancia")
                ->where("mercancias_totales",$vcpMercan->id)
                ->where("mercancia_detalle",$vMerca->id)
                ->get();

                foreach ($queryMercanciaDetalle as $vDetMr) {
                  $detalleMercancia[] = [
                    "UnidadPesoMerc" => $vDetMr->unidad_pesomerc,
                    "PesoBruto" => $vDetMr->peso_bruto,
                    "PesoNeto" => $vDetMr->peso_neto,
                    "PesoTara" => $vDetMr->peso_tara,
                    "NumPiezas" => $vDetMr->num_piezas
                  ];
                }

                $descripcionesEspecificas = []; //$descripcionesEspecificas[] = [];
                $queryDescripEspe = DB::table("carta_porte_mercancia_descripciones_especificas")
                ->where("mercancias_totales",$vcpMercan->id)
                ->where("mercancia_detalle",$vMerca->id)
                ->get();

                foreach ($queryDescripEspe as $vDesEs) {
                  $descripcionesEspecificas[] = [
                    "Marca" => $vDesEs->marca,
                    "Modelo" => $vDesEs->modelo,
                    "SubModelo" => $vDesEs->submodelo,
                    "NumeroSerie" => $vDesEs->numeroserie
                  ];
                }

                $mercanciaData[] = [
                  "carta_porte_mercancia_detalle_token" => $vMerca->carta_porte_mercancia_detalle_token,
                  "BienesTransp" => $vMerca->bienes_transp,
                  "ClaveSTCC" => $vMerca->clave_stcc,
                  "Descripcion" => $vMerca->descripcion,
                  "Cantidad" => $vMerca->cantidad,
                  "ClaveUnidad" => $vMerca->clave_unidad,
                  "Unidad" => $vMerca->unidad,
                  "Dimensiones" => $vMerca->dimensiones,
                  "MaterialPeligroso" => $vMerca->material_peligroso,
                  "CveMaterialPeligroso" => $vMerca->cve_material_peligroso,
                  "Embalaje" => $vMerca->embalaje,
                  "DescripEmbalaje" => $vMerca->descrip_embalaje,
                  "SectorCOFEPRIS" => $vMerca->sector_cofepris,
                  "NombreIngredienteActivo" => $vMerca->nombre_ingrediente_activo,
                  "NomQuimico" => $vMerca->nom_quimico,
                  "DenominacionGenericaProd" => $vMerca->denominacion_generica_prod,
                  "DenominacionDistintivaProd" => $vMerca->denominacion_distintiva_prod,
                  "Fabricante" => $vMerca->fabricante,
                  "FechaCaducidad" => $vMerca->fecha_caducidad,
                  "LoteMedicamento" => $vMerca->lote_medicamento,
                  "FormaFarmaceutica" => $vMerca->forma_farmaceutica,
                  "CondicionesEspTransp" => $vMerca->condiciones_esp_transp,
                  "RegistroSanitarioFolioAutorizacion" => $vMerca->registro_sanitario_folio_autorizacion,
                  "PermisoImportacion" => $vMerca->permiso_importacion,
                  "FolioImpoVUCEM" => $vMerca->folio_impovucem,
                  "NumCAS" => $vMerca->numcas,
                  "RazonSocialEmpImp" => $vMerca->razon_social_emp_imp,
                  "NumRegSanPlagCOFEPRIS" => $vMerca->num_reg_san_plag_cofepris,
                  "DatosFabricante" => $vMerca->datos_fabricante,
                  "DatosFormulador" => $vMerca->datos_formulador,
                  "DatosMaquilador" => $vMerca->datos_maquilador,
                  "UsoAutorizado" => $vMerca->uso_autorizado,
                  "PesoEnKg" => $vMerca->peso_enkg,
                  "ValorMercancia" => $vMerca->valor_mercancia,
                  "Moneda" => $vMerca->moneda,
                  "FraccionArancelaria" => $vMerca->fraccion_arancelaria,
                  "UUIDComercioExt" => $vMerca->uuid_comercio_ext,
                  "TipoMateria" => $vMerca->tipo_materia,
                  "DescripcionMateria" => $vMerca->descripcion_materia,
                  "DocumentacionAduanera" => $documentacionAduanera,
                  "GuiasIdentificacion" => $guiasIdentificacion,
                  "CantidadTransporta" => $cantidadTransporta,
                  "DetalleMercancia" => $detalleMercancia,
                  "DescripcionesEspecificas" => $descripcionesEspecificas
                ];
              }

              $listmercancias[] = [
                "mercancias_totales_token" => $vcpMercan->mercancias_totales_token,
                "PesoBrutoTotal" => $vcpMercan->peso_bruto_total,
                "UnidadPeso" => $vcpMercan->unidad_peso,
                "PesoNetoTotal" => $vcpMercan->peso_neto_total,
                "NumTotalMercancias" => $vcpMercan->num_total_mercancias,
                "CargoPorTasacion" => $vcpMercan->cargo_por_tasacion,
                "LogisticaInversaRecoleccionDevolucion" => $vcpMercan->logistica_inversa_recoleccion_devolucion,
                "Mercancia" => $mercanciaData
              ]; 
            }

            $listAutotransporte = []; //$listAutotransporte[] = [];
            $queryAutotransporteCPorte = DB::table("carta_porte_autotransporte")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryAutotransporteCPorte as $vAutotr) {
              $remolques = []; //$remolques[] = [];
              $queryRemolques = DB::table("carta_porte_remolques")
              ->where("autotransporte",$vAutotr->id)
              ->get();

              foreach ($queryRemolques as $vRemol) {
                $remolques[] = [
                  "SubTipoRem" => $vRemol->sub_tipo_rem,
                  "Placa" => $vRemol->placa
                ];
              }

              $listAutotransporte[] = [
                "autotransporte_token" => $vAutotr->autotransporte_token,
                "PermSCT" => $vAutotr->perm_sct,
                "NumPermisoSCT" => $vAutotr->num_permiso_sct,
                "ConfigVehicular" => $vAutotr->config_vehicular,
                "PesoBrutoVehicular" => $vAutotr->peso_bruto_vehicular,
                "PlacaVM" => $vAutotr->placa_vm,
                "AnioModeloVM" => $vAutotr->anio_modelo_vm,
                "AseguraRespCivil" => $vAutotr->asegura_resp_civil,
                "PolizaRespCivil" => $vAutotr->poliza_resp_civil,
                "AseguraMedAmbiente" => $vAutotr->asegura_med_ambiente,
                "PolizaMedAmbiente" => $vAutotr->poliza_med_ambiente,
                "AseguraCarga" => $vAutotr->asegura_carga,
                "PolizaCarga" => $vAutotr->poliza_carga,
                "PrimaSeguro" => $vAutotr->prima_seguro,
                "Remolques" => $remolques
              ];
            }

            $listTransporteMaritimo = []; //$listTransporteMaritimo[] = [];
            $queryTransporteMaritimo = DB::table("carta_porte_transporte_maritimo")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryTransporteMaritimo as $vcpTrMar) {
              $contenedorM = []; //$contenedorM[] = [];
              $queryTranspMarContenedorM = DB::table("carta_porte_transporte_maritimo_contenedorm")
              ->where("transporte_maritimo",$vcpTrMar->id)
              ->get();

              foreach ($queryTranspMarContenedorM as $vMConten) {
                $contenedorM[] = [
                  "TipoContenedor" => $vMConten->tipo_contenedor,
                  "MatriculaContenedor" => $vMConten->matricula_contenedor,
                  "NumPrecinto" => $vMConten->num_precinto,
                  "IdCCPRelacionado" => $vMConten->idccp_relacionado,
                  "PlacaVMCCP" => $vMConten->placa_vmccp,
                  "FechaCertificacionCCP" => $vMConten->fecha_certificacion_ccp,
                ];
              }

              $listTransporteMaritimo[] = [
                "transporte_maritimo_token" => $vcpTrMar->transporte_maritimo_token,
                "PermSCT" => $vcpTrMar->perm_sct,
                "NumPermisoSCT" => $vcpTrMar->num_permiso_sct,
                "NombreAseg" => $vcpTrMar->nombre_aseg,
                "NumPolizaSeguro" => $vcpTrMar->num_poliza_seguro,
                "TipoEmbarcacion" => $vcpTrMar->tipo_embarcacion,
                "Matricula" => $vcpTrMar->matricula,
                "NumeroOMI" => $vcpTrMar->numero_omi,
                "AnioEmbarcacion" => $vcpTrMar->anio_embarcacion,
                "NombreEmbarc" => $vcpTrMar->nombre_embarc,
                "NacionalidadEmbarc" => $vcpTrMar->nacionalidad_embarc,
                "UnidadesDeArqBruto" => $vcpTrMar->unidades_de_arq_bruto,
                "TipoCarga" => $vcpTrMar->tipo_carga,
                "Eslora" => $vcpTrMar->eslora,
                "Manga" => $vcpTrMar->manga,
                "Calado" => $vcpTrMar->calado,
                "Puntal" => $vcpTrMar->puntal,
                "LineaNaviera" => $vcpTrMar->linea_naviera,
                "NombreAgenteNaviero" => $vcpTrMar->nombre_agente_naviero,
                "NumAutorizacionNaviero" => $vcpTrMar->num_autorizacion_naviero,
                "NumViaje" => $vcpTrMar->num_viaje,
                "NumConocEmbarc" => $vcpTrMar->num_conoc_embarc,
                "PermisoTempNavegacion" => $vcpTrMar->permiso_temp_navegacion,
                "ContenedorM" => $contenedorM
              ];
            }

            $listTransporteAereo = []; //$listTransporteAereo[] = [];
            $queryTransporteAereo = DB::table("carta_porte_transporte_aereo")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryTransporteAereo as $vcpTrAir) {
              $listTransporteAereo[] = [
                "PermSCT" => $vcpTrAir->perm_sct,
                "NumPermisoSCT" => $vcpTrAir->num_permiso_sct,
                "MatriculaAeronave" => $vcpTrAir->matricula_aeronave,
                "NombreAseg" => $vcpTrAir->nombre_aseg,
                "NumPolizaSeguro" => $vcpTrAir->num_poliza_seguro,
                "NumeroGuia" => $vcpTrAir->numero_guia,
                "LugarContrato" => $vcpTrAir->lugar_contrato,
                "CodigoTransportista" => $vcpTrAir->codigo_transportista,
                "RFCEmbarcador" => $vcpTrAir->rfc_embarcador,
                "NumRegIdTribEmbarc" => $vcpTrAir->num_reg_id_trib_embarc,
                "ResidenciaFiscalEmbarc" => $vcpTrAir->residencia_fiscal_embarc,
                "NombreEmbarcador" => $vcpTrAir->nombre_embarcador
              ];
            }
            
            $listTransporteFerroviario = []; //$listTransporteFerroviario[] = [];
            $queryTransporteFerroviario = DB::table("carta_porte_transporte_ferroviario")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryTransporteFerroviario as $vcpTrFerro) {
              $derechosDePaso = []; //$derechosDePaso[] = [];
              $queryTranspFerroDerechosDePaso = DB::table("carta_porte_transporte_ferroviario_derechos_de_paso")
              ->where("transporte_ferroviario",$vcpTrFerro->id)
              ->where("carta_porte",$vCartaPorte->id_porte_carta)
              ->get();

              foreach ($queryTranspFerroDerechosDePaso as $vFerroDere) {
                $derechosDePaso[] = [
                  "TipoDerechoDePaso" => $vFerroDere->tipo_de_servicio,
                  "KilometrajePagado" => $vFerroDere->tipo_de_trafico
                ];
              }

              $carro = []; //$carro[] = [];
              $queryTranspFerroCarro = DB::table("carta_porte_transporte_ferroviario_carro")
              ->where("transporte_ferroviario",$vcpTrFerro->id)
              ->where("carta_porte",$vCartaPorte->id_porte_carta)
              ->get();

              foreach ($queryTranspFerroCarro as $vFerroCarro) {
                $contenedorCarro = []; //$contenedorCarro[] = [];
                $queryTranspFerroContenedorCarro = DB::table("carta_porte_transporte_ferroviario_carro_contenedor")
                ->where("transporte_ferroviario_carro",$vFerroCarro->id)
                ->get();

                foreach ($queryTranspFerroContenedorCarro as $vCarroContenedor) {
                  $contenedorCarro[] = [
                    "TipoContenedor" => $vCarroContenedor->tipo_contenedor,
                    "PesoContenedorVacio" => $vCarroContenedor->peso_contenedor_vacio,
                    "PesoNetoMercancia" => $vCarroContenedor->peso_neto_mercancia
                  ];
                }
                
                $carro[] = [
                  "TipoCarro" => $vFerroCarro->tipo_carro,
                  "MatriculaCarro" => $vFerroCarro->matricula_carro,
                  "GuiaCarro" => $vFerroCarro->guia_carro,
                  "ToneladasNetasCarro" => $vFerroCarro->toneladas_netas_carro,
                  "Contenedor" => $contenedorCarro
                ];
              }

              $listTransporteFerroviario[] = [
                "transporte_ferroviario_token" => $vcpTrFerro->transporte_ferroviario_token,
                "TipoDeServicio" => $vcpTrFerro->tipo_de_servicio,
                "TipoDeTrafico" => $vcpTrFerro->tipo_de_trafico,
                "NombreAseg" => $vcpTrFerro->nombre_aseg,
                "NumPolizaSeguro" => $vcpTrFerro->num_poliza_seguro,
                "DerechosDePaso" => $derechosDePaso,
                "Carro" => $carro,
              ];
            }
          
            $listPartesTransporte = []; //$listPartesTransporte[] = [];
            $queryPartesTransporte = DB::table("carta_porte_partes_transporte")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryPartesTransporte as $vcpTrPartes) {
              $listPartesTransporte[] = [
                "ParteTransporte" => $vcpTrPartes->parte_transporte,
                "IdPartesTransporte" => $vcpTrPartes->id_partes_transporte
              ];
            }
            
            $listFiguraTransporte = []; //$listFiguraTransporte[] = [];
            $queryFiguraTransporte = DB::table("carta_porte_figura_transporte")
            ->where("carta_porte",$vCartaPorte->id_porte_carta)
            ->get();

            foreach ($queryFiguraTransporte as $figTransp) {
              $listFiguraTransporte[] = [
                "TipoFigura" => $figTransp->tipo_figura,
                "RFCFigura" => $figTransp->rfc_figura,
                "NumLicencia" => $figTransp->num_licencia,
                "NombreFigura" => $figTransp->nombre_figura,
                "NumRegIdTribFigura" => $figTransp->num_reg_id_trib_figura,
                "ResidenciaFiscalFigura" => $figTransp->residencia_fiscal_figura,
              ];
            }

            $row = array(
              "carta_porte_token" => $vCartaPorte->carta_porte_token,
              "Version" => $vCartaPorte->version,
              "IdCCP" => $vCartaPorte->id_ccp,
              "TranspInternac" => $vCartaPorte->transp_internac,
              "RegimenAduanero" => $vCartaPorte->regimen_aduanero,
              "EntradaSalidaMerc" => $vCartaPorte->entrada_salida_merc,
              "PaisOrigenDestino" => $vCartaPorte->pais_origen_destino,
              "ViaEntradaSalida" => $vCartaPorte->via_entrada_salida,
              "TotalDistRec" => $vCartaPorte->total_dist_rec,
              "RegistroISTMO" => $vCartaPorte->registro_istmo,
              "UbicacionPoloOrigen" => $vCartaPorte->ubicacion_polo_origen,
              "UbicacionPoloDestino" => $vCartaPorte->ubicacion_polo_destino,
              "ubicaciones" => $listUbicaciones, 
              "mercancias" => $listmercancias, 
              "Autotransporte" => $listAutotransporte, 
              "TransporteMaritimo" => $listTransporteMaritimo, 
              "TransporteAereo" => $listTransporteAereo, 
              "TransporteFerroviario" => $listTransporteFerroviario, 
              "PartesTransporte" => $listPartesTransporte, 
              "FiguraTransporte" => $listFiguraTransporte, 
            );
            $porte_carta[] = $row;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'carta_porte' => $porte_carta,
          );
        }
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //lista de logísticas registradas
  private function eachListaComprasTransito($transitos,$JwtAuth){
    $arrayLogisticaTransito = [];

    $idTransito = $transitos->pluck('id_transito')->filter()->unique()->toArray();

    $detailCompraMap = DB::table("eegr_compras_detalle AS detBuy")
    ->join("eegr_compras AS buy", "detBuy.numero_compra", "=", "buy.id")
    ->join("logistica_transito_compras_relacionada AS logBuy", "buy.id", "=", "logBuy.compra_relacionada")
    ->whereNull('detBuy.servicio')
    ->whereNull('detBuy.activo_intangible')
    ->whereIn('logBuy.transito_main',$idTransito)
    ->select(
      'logBuy.transito_main AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*'
    )
    ->get()->groupBy('id_compras');

    $allDetailIds = $detailCompraMap->collapse()->pluck('id_det_compras')->unique()->toArray();
    $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->where("l_uni.tipo_trayecto", "inicio")
    ->whereIn("art.articulo_detcompra", $allDetailIds)
    ->select(
      'art.articulo_detcompra AS id_det_compras',
      'l_uni.tipo_trayecto',
      'art.cantidad_asignada'
    )
    ->get()
    ->groupBy('id_det_compras');

    $transitoEntregadosMap = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->where("l_uni.tipo_trayecto", "entrega")
    ->where("l_uni.unidad_arribo_autorizado", TRUE)
    ->whereIn("art.articulo_detcompra", $allDetailIds)
    ->select(
      'art.articulo_detcompra AS id_det_compras',
      'l_uni.tipo_trayecto',
      'art.cantidad_asignada'
    )
    ->get()
    ->groupBy('id_det_compras');

    $transitoSinDateLlegadaMap = DB::table("logistica_transito_unidades AS l_uni")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->whereIn("l_comp.id", $idTransito)
    ->whereNull("l_uni.unidad_fecha_real_arribo") // Filtramos solo los pendientes
    ->select('l_comp.id AS id_transito', 'l_comp.id')
    ->get() // 1. Traemos la lista de pendientes a PHP
    ->groupBy('id_transito') // 2. Los agrupamos por compra creando el Map
    ->map(function ($puntos) {
      return $puntos->count(); // 3. ¡AQUÍ usamos el count() para contar cada grupo!
    });
    
    $transitoLlegadaSinAuthMap = DB::table("logistica_transito_unidades AS l_uni")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->whereIn("l_comp.id", $idTransito)
    ->whereNotNull("l_uni.unidad_fecha_real_arribo")
    ->where("l_uni.unidad_arribo_autorizado",FALSE)
    ->select('l_comp.id AS id_transito', 'l_comp.id')
    ->get() // 1. Traemos la lista de pendientes a PHP
    ->groupBy('id_transito') // 2. Los agrupamos por compra creando el Map
    ->map(function ($puntos) {
      return $puntos->count(); // 3. ¡AQUÍ usamos el count() para contar cada grupo!
    });

    foreach ($transitos as $vLogTrans) {
      $semaforo_espera = 'text-gray-500'; 
      $semaforo_transito = 'text-gray-500';
      $semaforo_entregados = 'text-gray-500';
      $semaforo_recibidos = 'text-gray-500';

      $total_articulos_comprados = 0;
      $total_articulos_en_espera = 0;
      $total_articulos_en_transito = 0;
      $total_articulos_entregados = 0;
      $total_articulos_recibidos = 0;
      $avance_entrega = 0;
      $avance_recepcion = 0;

      $queryDetBuyProd = $detailCompraMap->get($vLogTrans->id_transito) ?? collect([]);
      foreach ($queryDetBuyProd as $vDet) {
        $total_articulos_comprados += $vDet->cantidad;
        $movimientos = $transitoEstadosMap->get($vDet->id_det_compras) ?? collect([]);
        // Calculamos salidas vs entregas usando las colecciones en memoria
        $salieron = $movimientos->sum('cantidad_asignada');
        $total_articulos_en_espera = $vDet->cantidad - $salieron;

        $mov_entregados = $transitoEntregadosMap->get($vDet->id_det_compras) ?? collect([]);
        $entregados = $mov_entregados->sum('cantidad_asignada'); // Cambia 'entregado' por tu estado final
        
        // Acumulamos para el nodo de la compra
        $total_articulos_en_transito += ($salieron - $entregados); // Lo que salió pero no ha llegado
        $total_articulos_entregados += $entregados;

        $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
        ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
        ->where([
          "detBuy.token_detcompra" => $vDet->token_detcompra,
          "rec.producto" => $vDet->producto
        ])
        ->select(
          DB::raw("SUM(rec.cantidad_recibida) as cantidad_recibida")
        )
        ->groupBy('rec.detalle_compra')
        ->first();
        $total_articulos_recibidos += $queryRecepcionPRD ? $queryRecepcionPRD->cantidad_recibida : 0;
      }

      if ($total_articulos_comprados > 0) {
        // 1. Semáforo para artículos en ESPERA
        if ($total_articulos_en_espera == $total_articulos_comprados) {
          $semaforo_espera = 'bg-red-100 text-red-700 font-bold'; // Rojo: Todos en espera
        } elseif ($total_articulos_en_espera > 0) {
          $semaforo_espera = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Una parte en espera
        } else {
          $semaforo_espera = 'bg-green-100 text-green-700'; // Verde: Ya nada está en espera
        }

        // 2. Semáforo para artículos en TRÁNSITO
        if ($total_articulos_en_transito > 0) {
          $semaforo_transito = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Hay mercancía viajando
        } else {
          $semaforo_transito = 'text-gray-400'; // Neutral: No hay nada en ruta activa
        }

        // 3. Semáforo para artículos ENTREGADOS
        if ($total_articulos_entregados == $total_articulos_comprados) {
          $semaforo_entregados = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
        } elseif ($total_articulos_entregados > 0) {
          $semaforo_entregados = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
        } else {
          $semaforo_entregados = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
        }

        // 3. Semáforo para artículos RECIBIDOS
        if ($total_articulos_recibidos == $total_articulos_comprados) {
          $semaforo_recibidos = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
        } elseif ($total_articulos_recibidos > 0) {
          $semaforo_recibidos = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
        } else {
          $semaforo_recibidos = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
        }
      }

      if ($total_articulos_comprados > 0) {
        $avance_entrega = round(($total_articulos_entregados / $total_articulos_comprados) * 100);
        if ($avance_entrega > 100) {
          $avance_entrega = 100;
        }

        $avance_recepcion = round(($total_articulos_recibidos / $total_articulos_comprados) * 100);
        if ($avance_recepcion > 100) {
          $avance_recepcion = 100;
        }
      }

      $transitos_sin_fecha_llegada = $transitoSinDateLlegadaMap->get($vLogTrans->id_transito) ?? 0; 
      $transitos_llegada_sin_auth = $transitoLlegadaSinAuthMap->get($vLogTrans->id_transito) ?? 0;

      $arrayLogisticaTransito[] = [
        "token_seguimiento_transito" => $vLogTrans->token_seguimiento_transito,
        "folio_seguimiento_transito" => "TRANSITO-".$JwtAuth->generarFolio($vLogTrans->folio_seguimiento_transito),
        "estado_alcanzado" => $vLogTrans->estado_alcanzado,

        "fecha_real_salida" => $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->fecha_real_salida),
        "observaciones_salida" => $JwtAuth->desencriptar($vLogTrans->observaciones_salida),
        "arribo_final_fecha_tentativa" => $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_tentativa),
        "arribo_final_fecha_real" => $vLogTrans->arribo_final_fecha_real ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_real) : '',
        "arribo_final_observaciones" => $vLogTrans->arribo_final_observaciones ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_observaciones) : '',
        "arribo_final_autorizado" => (bool)$vLogTrans->arribo_final_autorizado,
        "arribo_final_fecha_auth" => $vLogTrans->arribo_final_fecha_auth ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_auth) : '',
        "usuario_registra" => $vLogTrans->usuario_registra,

        "clase_espera" => $semaforo_espera,
        "clase_transito" => $semaforo_transito,
        "clase_entregados" => $semaforo_entregados,
        "avance_entrega" => $avance_entrega,
        "clase_recibidos" => $semaforo_recibidos,
        "avance_recepcion" => $avance_recepcion,

        "articulos_en_espera" => "$total_articulos_en_espera / $total_articulos_comprados", 
        "habilita_reg_new_salidas" => (bool)($total_articulos_en_espera > 0),//$total_articulos_entregados < $total_articulos_comprados ? true : false,
        "articulos_en_transito" => "$total_articulos_en_transito / $total_articulos_comprados", 
        "habiltar_continua_rutas" => (bool)($total_articulos_entregados < $total_articulos_comprados),//(bool)($total_articulos_en_transito > 0),
        "articulos_entregados" => "$total_articulos_entregados / $total_articulos_comprados", 
        "transitos_sin_fecha_llegada" => $transitos_sin_fecha_llegada,
        "transitos_llegada_sin_auth" => $transitos_llegada_sin_auth,
        "articulos_recibidos" => "$total_articulos_recibidos / $total_articulos_comprados",
      ];
    }


    return $arrayLogisticaTransito;
  }

  public function listaLogisticaTransitosIniciados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }
      
      $queryLogisticaTransito = DB::table("logistica_transito_main AS logis")
      ->join("main_empresas AS emp", "logis.empresa_vinculada", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])
      ->whereIn('logis.id', function ($query) {
        $query->select('transito_main')->from('logistica_transito_unidades');
      })
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("logis.logistica_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->select("logis.id AS id_transito","logis.*","emp.*")
      ->orderBy("logis.id","DESC")
      ->get();

      if ($queryLogisticaTransito->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron salidas de logística registradas'
        );
      } else {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'compras' => $this->eachListaComprasTransito($queryLogisticaTransito,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizarLogisticaTransito(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');
      
      $vLogTrans = DB::table("logistica_transito_main AS logis")
      ->join("main_empresas AS emp", "logis.empresa_vinculada", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "logis.token_seguimiento_transito" => $logistica_seguimiento_token,
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])
      ->whereIn('logis.id', function ($query) {
        $query->select('transito_main')->from('logistica_transito_unidades');
      })
      ->select("logis.id AS id_transito","logis.*","emp.*")
      ->first();

      if (!$vLogTrans) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron salidas de logística registradas'
        );
      } else {
        $idTransito = $vLogTrans->id_transito;
        //echo $idTransito;
    
        $detailCompraMap = DB::table("eegr_compras_detalle AS detBuy")
        ->join("eegr_compras AS buy", "detBuy.numero_compra", "=", "buy.id")
        ->join("logistica_transito_compras_relacionada AS logBuy", "buy.id", "=", "logBuy.compra_relacionada")
        ->whereNull('detBuy.servicio')
        ->whereNull('detBuy.activo_intangible')
        ->where('logBuy.transito_main', $idTransito)
        ->select(
          'logBuy.transito_main AS id_compras',
          'detBuy.id AS id_det_compras',
          'detBuy.*'
        )
        ->get()->groupBy('id_compras');
    
        $allDetailIds = $detailCompraMap->collapse()->pluck('id_det_compras')->unique()->toArray();
        $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
        ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
        ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
        ->where("l_uni.tipo_trayecto", "inicio")
        ->whereIn("art.articulo_detcompra", $allDetailIds)
        ->select(
          'art.articulo_detcompra AS id_det_compras',
          'l_uni.tipo_trayecto',
          'art.cantidad_asignada'
        )
        ->get()
        ->groupBy('id_det_compras');
    
        $transitoEntregadosMap = DB::table("logistica_transito_articulos AS art")
        ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
        ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
        ->where("l_uni.tipo_trayecto", "entrega")
        ->where("l_uni.unidad_arribo_autorizado", TRUE)
        ->whereIn("art.articulo_detcompra", $allDetailIds)
        ->select(
          'art.articulo_detcompra AS id_det_compras',
          'l_uni.tipo_trayecto',
          'art.cantidad_asignada'
        )
        ->get()
        ->groupBy('id_det_compras');
    
        $transitoSinDateLlegadaMap = DB::table("logistica_transito_unidades AS l_uni")
        ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
        ->where("l_comp.id", $idTransito)
        ->whereNull("l_uni.unidad_fecha_real_arribo") // Filtramos solo los pendientes
        ->select('l_comp.id AS id_transito', 'l_comp.id')
        ->get() // 1. Traemos la lista de pendientes a PHP
        ->groupBy('id_transito') // 2. Los agrupamos por compra creando el Map
        ->map(function ($puntos) {
          return $puntos->count(); // 3. ¡AQUÍ usamos el count() para contar cada grupo!
        });
        
        $transitoLlegadaSinAuthMap = DB::table("logistica_transito_unidades AS l_uni")
        ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
        ->where("l_comp.id", $idTransito)
        ->whereNotNull("l_uni.unidad_fecha_real_arribo")
        ->where("l_uni.unidad_arribo_autorizado",FALSE)
        ->select('l_comp.id AS id_transito', 'l_comp.id')
        ->get() // 1. Traemos la lista de pendientes a PHP
        ->groupBy('id_transito') // 2. Los agrupamos por compra creando el Map
        ->map(function ($puntos) {
          return $puntos->count(); // 3. ¡AQUÍ usamos el count() para contar cada grupo!
        });

        $semaforo_espera = 'text-gray-500'; 
        $semaforo_transito = 'text-gray-500';
        $semaforo_entregados = 'text-gray-500';
        $semaforo_recibidos = 'text-gray-500';
  
        $total_articulos_comprados = 0;
        $total_articulos_en_espera = 0;
        $total_articulos_en_transito = 0;
        $total_articulos_entregados = 0;
        $total_articulos_recibidos = 0;
        $avance_entrega = 0;
        $avance_recepcion = 0;
  
        $queryDetBuyProd = $detailCompraMap->get($vLogTrans->id_transito) ?? collect([]);
        foreach ($queryDetBuyProd as $vDet) {
          $total_articulos_comprados += $vDet->cantidad;
          $movimientos = $transitoEstadosMap->get($vDet->id_det_compras) ?? collect([]);
          // Calculamos salidas vs entregas usando las colecciones en memoria
          $salieron = $movimientos->sum('cantidad_asignada');
          $total_articulos_en_espera = $vDet->cantidad - $salieron;
  
          $mov_entregados = $transitoEntregadosMap->get($vDet->id_det_compras) ?? collect([]);
          $entregados = $mov_entregados->sum('cantidad_asignada'); // Cambia 'entregado' por tu estado final
          
          // Acumulamos para el nodo de la compra
          $total_articulos_en_transito += ($salieron - $entregados); // Lo que salió pero no ha llegado
          $total_articulos_entregados += $entregados;
  
          $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
          ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
          ->where([
            "detBuy.token_detcompra" => $vDet->token_detcompra,
            "rec.producto" => $vDet->producto
          ])
          ->select(
            DB::raw("SUM(rec.cantidad_recibida) as cantidad_recibida")
          )
          ->groupBy('rec.detalle_compra')
          ->first();
          $total_articulos_recibidos += $queryRecepcionPRD ? $queryRecepcionPRD->cantidad_recibida : 0;
        }
  
        if ($total_articulos_comprados > 0) {
          // 1. Semáforo para artículos en ESPERA
          if ($total_articulos_en_espera == $total_articulos_comprados) {
            $semaforo_espera = 'bg-red-100 text-red-700 font-bold'; // Rojo: Todos en espera
          } elseif ($total_articulos_en_espera > 0) {
            $semaforo_espera = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Una parte en espera
          } else {
            $semaforo_espera = 'bg-green-100 text-green-700'; // Verde: Ya nada está en espera
          }
  
          // 2. Semáforo para artículos en TRÁNSITO
          if ($total_articulos_en_transito > 0) {
            $semaforo_transito = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Hay mercancía viajando
          } else {
            $semaforo_transito = 'text-gray-400'; // Neutral: No hay nada en ruta activa
          }
  
          // 3. Semáforo para artículos ENTREGADOS
          if ($total_articulos_entregados == $total_articulos_comprados) {
            $semaforo_entregados = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
          } elseif ($total_articulos_entregados > 0) {
            $semaforo_entregados = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
          } else {
            $semaforo_entregados = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
          }
  
          // 3. Semáforo para artículos RECIBIDOS
          if ($total_articulos_recibidos == $total_articulos_comprados) {
            $semaforo_recibidos = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
          } elseif ($total_articulos_recibidos > 0) {
            $semaforo_recibidos = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
          } else {
            $semaforo_recibidos = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
          }
        }
  
        if ($total_articulos_comprados > 0) {
          $avance_entrega = round(($total_articulos_entregados / $total_articulos_comprados) * 100);
          if ($avance_entrega > 100) {
            $avance_entrega = 100;
          }
  
          $avance_recepcion = round(($total_articulos_recibidos / $total_articulos_comprados) * 100);
          if ($avance_recepcion > 100) {
            $avance_recepcion = 100;
          }
        }
  
        $transitos_sin_fecha_llegada = $transitoSinDateLlegadaMap->get($vLogTrans->id_transito) ?? 0; 
        $transitos_llegada_sin_auth = $transitoLlegadaSinAuthMap->get($vLogTrans->id_transito) ?? 0;
        
        $dataMensaje = array(
          "code" => 200,
          "status" => 'success',
          "token_seguimiento_transito" => $vLogTrans->token_seguimiento_transito,
          "folio_seguimiento_transito" => "TRANSITO-".$JwtAuth->generarFolio($vLogTrans->folio_seguimiento_transito),
          "estado_alcanzado" => $vLogTrans->estado_alcanzado,

          "fecha_real_salida" => $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->fecha_real_salida),
          "observaciones_salida" => $JwtAuth->desencriptar($vLogTrans->observaciones_salida),
          "arribo_final_fecha_tentativa" => $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_tentativa),
          "arribo_final_fecha_real" => $vLogTrans->arribo_final_fecha_real ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_real) : '',
          "arribo_final_observaciones" => $vLogTrans->arribo_final_observaciones ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_observaciones) : '',
          "arribo_final_autorizado" => (bool)$vLogTrans->arribo_final_autorizado,
          "arribo_final_fecha_auth" => $vLogTrans->arribo_final_fecha_auth ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_auth) : '',
          "usuario_registra" => $vLogTrans->usuario_registra,

          "clase_espera" => $semaforo_espera,
          "clase_transito" => $semaforo_transito,
          "clase_entregados" => $semaforo_entregados,
          "avance_entrega" => $avance_entrega,
          "clase_recibidos" => $semaforo_recibidos,
          "avance_recepcion" => $avance_recepcion,

          "articulos_en_espera" => "$total_articulos_en_espera / $total_articulos_comprados", 
          "habilita_reg_new_salidas" => (bool)($total_articulos_en_espera > 0),//$total_articulos_entregados < $total_articulos_comprados ? true : false,
          "articulos_en_transito" => "$total_articulos_en_transito / $total_articulos_comprados", 
          "habiltar_continua_rutas" => (bool)($total_articulos_entregados < $total_articulos_comprados),//(bool)($total_articulos_en_transito > 0),
          "articulos_entregados" => "$total_articulos_entregados / $total_articulos_comprados", 
          "transitos_sin_fecha_llegada" => $transitos_sin_fecha_llegada,
          "transitos_llegada_sin_auth" => $transitos_llegada_sin_auth,
          "articulos_recibidos" => "$total_articulos_recibidos / $total_articulos_comprados",
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function obtenerArribosSinFecha(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');

      try {
        $queryUnidades = DB::table("logistica_transito_unidades AS logUni")
        ->join("logistica_transito_main As logMain", "logUni.transito_main", "=", "logMain.id")
        ->whereNull("logUni.unidad_fecha_real_arribo")
        ->where("logMain.token_seguimiento_transito", $logistica_seguimiento_token)
        ->orderBy("logUni.id", "ASC")
        ->select(
          'logMain.id AS id_hito',
          'logMain.token_seguimiento_transito',
          'logMain.estado_alcanzado',
          'logUni.id AS id_unidad',
          'logUni.token_seguimiento_unidad',
          'logUni.folio_seguimiento_unidad',
          'logUni.transito_main',
          'logUni.tipo_trayecto',
          'logUni.tipo_transporte',
          'logUni.operador_nombre',
          'logUni.operador_telefono',
          'logUni.identificador_principal',
          'logUni.identificador_secundario',
          'logUni.permiso_autorizacion',
          'logUni.direccion_origen',
          'logUni.direccion_destino_es_bodega_entrega',
          'logUni.direccion_destino_bodega_entrega',
          'logUni.direccion_destino_especifica',
          'logUni.cfdi_relacionado',
          'logUni.cfdi_pdf_url',
          'logUni.estado_consumo',
          'logUni.unidad_fecha_salida',
          'logUni.unidad_fecha_tentativa_arribo',
          'logUni.unidad_fecha_real_arribo',
          'logUni.unidad_arribo_autorizado',
          'logUni.unidad_fecha_auth_arribo',
          'logUni.unidad_observaciones_arribo',
        )
        ->get();

        if ($queryUnidades->isEmpty()) {
          return response()->json([
            'status' => 'success',
            'message' => 'La compra no cuenta con tránsitos o unidades despachadas aún.',
            'data' => null
          ], 200);
        } else {
          $JwtAuth = new \App\Helpers\JwtAuth();
          $unidadesRegistradas = [];

          foreach ($queryUnidades as $vUnidad) {
            $articulosUnidad = DB::table("logistica_transito_articulos AS art")
            ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
            ->where("art.transito_unidad_id", $vUnidad->id_unidad)
            ->select(
              "det.token_detcompra",
              "art.articulo_descripcion AS articulo",
              "art.cantidad_asignada AS cantidad",
              "art.unidad_medida",
            )
            ->get();

            $tipo_transporte_extend = "";
            switch ($vUnidad->tipo_transporte) {
              case 'terrestre':
                $tipo_transporte_extend = "Terrestre (Camión/Tráiler)";
                break;
              case 'maritimo':
                $tipo_transporte_extend = "Marítimo (Buque/Contenedor)";
                break;
              case 'aereo':
                $tipo_transporte_extend = "Aéreo (Avión)";
                break;
              default:
                $tipo_transporte_extend = "";
                break;
            }

            $unidadesRegistradas[] = [
              "token_seguimiento_unidad"       => $vUnidad->token_seguimiento_unidad,
              "folio_seguimiento_unidad"       => 'UNIDAD-'.$JwtAuth->generarFolio($vUnidad->folio_seguimiento_unidad),
              "id_unidad_anterior"             => $vUnidad->id_unidad,
              "tipo_transporte"                => $vUnidad->tipo_transporte,
              "tipo_transporte_extend"         => $tipo_transporte_extend,
              
              // Datos del Operador (Desencriptados)
              "operador_nombre"                => $JwtAuth->desencriptar($vUnidad->operador_nombre),
              "operador_telefono"              => $JwtAuth->desencriptar($vUnidad->operador_telefono),
              
              // Identificadores Logísticos (Desencriptados)
              "identificador_principal"        => $JwtAuth->desencriptar($vUnidad->identificador_principal), // Placas/Contenedor/AWB
              "identificador_secundario"       => $JwtAuth->desencriptar($vUnidad->identificador_secundario), // Remolque/Booking/Vuelo
              "permiso_autorizacion"           => !is_null($vUnidad->permiso_autorizacion) ? $JwtAuth->desencriptar($vUnidad->permiso_autorizacion) : "",
              
              // Direcciones de Ruta (Desencriptadas)
              "direccion_origen"               => $JwtAuth->desencriptar($vUnidad->direccion_origen),
              // 📍 Este destino se convierte en el Origen Sugerido para el nuevo tramo de esta unidad
              "direccion_destino_especifica"   => $JwtAuth->desencriptar($vUnidad->direccion_destino_especifica), 
              
              // Listado completo de mercancía amparada
              "articulos"                      => $articulosUnidad,
              "articulos_seleccionados"        => [], // Inicializado vacío para el manejo de selección en PrimeNG
              "unidad_fecha_salida"            => !is_null($vUnidad->unidad_fecha_salida) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_salida) : '',
              "unidad_fecha_tentativa_arribo"  => !is_null($vUnidad->unidad_fecha_tentativa_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_tentativa_arribo) : '',
              "unidad_fecha_real_arribo_reg"   => !is_null($vUnidad->unidad_fecha_real_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_real_arribo) : '',
              "observaciones_arribo_reg"       => !is_null($vUnidad->unidad_observaciones_arribo) ? $JwtAuth->desencriptar($vUnidad->unidad_observaciones_arribo) : '',
              "new_fecha_real_arribo"          => '',
              "new_observaciones_arribo"       => '',
            ];
            
            //$yaTieneArribo = !is_null($vHit->fecha_real_arribo);
            //$registro_activo = (!$yaTieneArribo && $hitoAnteriorLlego);
            //$unidadesRegistradas[] = [
            //  "token_seguimiento_transito" => $vHit->token_seguimiento_transito,
            //  "registro_activo"          => $registro_activo,//!is_null($vHit->fecha_real_arribo) && $vHit->id_hito == $id_primer_hito,
            //  "etapa_anterior"           => $vHit->estado_alcanzado,
            //  "fecha_real_salida"        => !is_null($vHit->fecha_real_salida) ? gmdate('Y-m-d H:i:s',$vHit->fecha_real_salida) : '',
            //  "observaciones_salida"     => !is_null($vHit->observaciones_salida) ? $JwtAuth->desencriptar($vHit->observaciones_salida) : '',
            //  "fecha_tentativa_arribo"   => !is_null($vHit->fecha_tentativa_arribo) ? gmdate('Y-m-d H:i:s',$vHit->fecha_tentativa_arribo) : '',
            //  "fecha_real_arribo_reg"        => !is_null($vHit->fecha_real_arribo) ? gmdate('Y-m-d H:i:s',$vHit->fecha_real_arribo) : '',
            //  "observaciones_arribo_reg"     => !is_null($vHit->observaciones_arribo) ? $JwtAuth->desencriptar($vHit->observaciones_arribo) : '',
            //  "new_fecha_real_arribo"        => '',
            //  "new_observaciones_arribo"     => '',
            //  "unidades_anteriores"      => $unidadesProcesadas,
            //];
            //$hitoAnteriorLlego = $yaTieneArribo;
          }
          
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Estructura de datos completa recuperada exitosamente.',
            'logistica_seguimiento_token' => $logistica_seguimiento_token,
            'unidadesRegistradas' => $unidadesRegistradas,
          );
        }
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarArribo(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string',
      'token_seguimiento_unidad' => 'required|string',
      'fecha_real_arribo' => 'required|string',
      'observaciones_arribo' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');
      $token_seguimiento_unidad = $request->input('token_seguimiento_unidad');
      $fecha_real_arribo = $request->input('fecha_real_arribo');
      $observaciones_arribo = $request->input('observaciones_arribo');

      $OKTransitoMain = isset($logistica_seguimiento_token) && !empty($logistica_seguimiento_token);
      $OKTransitoUnidad = isset($token_seguimiento_unidad) && !empty($token_seguimiento_unidad);

      $OKFechaRealArribo = isset($fecha_real_arribo) && !empty($fecha_real_arribo) && preg_match($JwtAuth->filtroFecha(),$fecha_real_arribo);
      $OKObservacionesArribo = isset($observaciones_arribo) && !empty($observaciones_arribo) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones_arribo);

      if ($OKTransitoMain && $OKTransitoUnidad && $OKFechaRealArribo && $OKObservacionesArribo) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
        ->first();

        $transitoData = DB::table("logistica_transito_main")
        ->where("token_seguimiento_transito",$logistica_seguimiento_token)
        ->select("id","logistica_fecha_contabilizacion","folio_seguimiento_transito")
        ->first();
        $folio_seguimiento_transito = 'TRANSITO-'.$JwtAuth->generarFolio($transitoData->folio_seguimiento_transito);
        $logistica_fecha_contabilizacion = $transitoData->logistica_fecha_contabilizacion;
        $nombreDocs = $logistica_fecha_contabilizacion."-".$folio_seguimiento_transito;

        $unidadData = DB::table("logistica_transito_unidades")
        ->where(["transito_main" => $transitoData->id,"token_seguimiento_unidad" => $token_seguimiento_unidad])
        ->select("id","folio_seguimiento_unidad")
        ->first(); 
        $folio_seguimiento_unidad = 'UNIDAD-'.$JwtAuth->generarFolio($unidadData->folio_seguimiento_unidad);

        $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/logistica/unidades/$folio_seguimiento_unidad/arribo/";
        
        DB::beginTransaction();
        try {
          DB::table("logistica_transito_unidades")->where("id",$unidadData->id)
          ->limit(1)->update([
            "unidad_fecha_real_arribo" => $JwtAuth->convierteFechaEpoc($fecha_real_arribo),
            "unidad_observaciones_arribo" => $JwtAuth->encriptar($observaciones_arribo)
          ]);
          
          if ($request->hasFile('anexos_llegada')) {
            $anexos = $request->file('anexos_llegada');
          
            // 1. Rendimiento: Consultamos el folio una sola vez fuera del ciclo
            $conteoActual = DB::table("logistica_transito_documentos")->where('folio_modulo', 'LIKE', 'ARRIBO-ANEX%')->lockForUpdate()->count();
            $folioSiguiente = $conteoActual + 1;
            
            foreach ($anexos as $archivo) {
              if ($archivo && $archivo->isValid()) {
                // 2. Definición de nombre original
                $nombreOriginal = $archivo->getClientOriginalName();
                  
                // Usamos el nombre original directamente ya que $filepath es único por compra
                $nombreFisico = $nombreOriginal;
      
                // 3. Guardado físico en el storage
                $storagePath = "/public/root/" . $filepath;
                $saveFile = Storage::putFileAs($storagePath, $archivo, $nombreFisico);
      
                if (!$saveFile) {
                  throw new \Exception("Error al guardar el archivo físico: $nombreOriginal");
                }
      
                // 4. Preparar datos y generar Token
                $folioModulo = "ARRIBO-ANEX" . $folioSiguiente;
  
                // 5. Inserción en base de datos
                $insertDoc = DB::table("logistica_transito_documentos")->insert([
                  "token_documento"     => Str::uuid()->toString(),
                  "fecha_carga"         => time(),
                  "modulo"              => "pagos",
                  "folio_modulo"        => $folioModulo,
                  "tipo_documento"      => "an",
                  "nombre_documento"    => $JwtAuth->encriptar($nombreOriginal),
                  "transito_main"       => $transitoData->id,
                  "transito_unidad"     => $unidadData->id,
                  "status_documento"    => true,
                ]);
      
                if (!$insertDoc) {
                  throw new \Exception("Error al registrar el anexo $nombreOriginal en la base de datos.");
                }
    
                // Incrementamos para el siguiente archivo
                $folioSiguiente++;
              }
            }
          }

          DB::commit();
          $dataMensaje = array(
            'message' => 'La fecha de llegada ha sido registrada exitosamente.',//.$folio_buy.($validate_insert_ord_pago ? ', revise ordenes de pago' : '')
            'code' => 200,
            'status' => 'success',
          );
        } catch (\Exception $e) {
          DB::rollBack();
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en el registro: ',
            'line' => $e->getLine()
          );
        }
      } else {
        $mensaje_error = '';
        if (!$OKTransitoMain) { $mensaje_error = 'Error en traslado relacionado, verifique su información o comuniquese a soporte'; }
        if (!$OKTransitoUnidad) { $mensaje_error = 'Error en unidad relacionada, verifique su información o comuniquese a soporte'; }
        if (!$OKFechaRealArribo) { $mensaje_error = 'Error en fecha de llegada, verifique su información o comuniquese a soporte'; }
        if (!$OKObservacionesArribo) { $mensaje_error = 'Error en observaciones, verifique su información o comuniquese a soporte'; }

        $dataMensaje = array('status' => 'error','code' => 400,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function obtenerArribosNoAutorizados(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');

      try {
        $queryUnidades = DB::table("logistica_transito_unidades AS logUni")
        ->join("logistica_transito_main As logMain", "logUni.transito_main", "=", "logMain.id")
        ->whereNotNull("logUni.unidad_fecha_real_arribo")
        ->where("logUni.unidad_arribo_autorizado",FALSE)
        ->where("logMain.token_seguimiento_transito", $logistica_seguimiento_token)
        ->orderBy("logUni.id", "ASC")
        ->select(
          'logMain.id AS id_hito',
          'logMain.token_seguimiento_transito',
          'logMain.estado_alcanzado',
          'logUni.id AS id_unidad',
          'logUni.token_seguimiento_unidad',
          'logUni.folio_seguimiento_unidad',
          'logUni.transito_main',
          'logUni.tipo_trayecto',
          'logUni.tipo_transporte',
          'logUni.operador_nombre',
          'logUni.operador_telefono',
          'logUni.identificador_principal',
          'logUni.identificador_secundario',
          'logUni.permiso_autorizacion',
          'logUni.direccion_origen',
          'logUni.direccion_destino_es_bodega_entrega',
          'logUni.direccion_destino_bodega_entrega',
          'logUni.direccion_destino_especifica',
          'logUni.cfdi_relacionado',
          'logUni.cfdi_pdf_url',
          'logUni.estado_consumo',
          'logUni.unidad_fecha_salida',
          'logUni.unidad_fecha_tentativa_arribo',
          'logUni.unidad_fecha_real_arribo',
          'logUni.unidad_arribo_autorizado',
          'logUni.unidad_fecha_auth_arribo',
          'logUni.unidad_observaciones_arribo',
        )
        ->get();

        if ($queryUnidades->isEmpty()) {
          return response()->json([
            'status' => 'success',
            'message' => 'La compra no cuenta con tránsitos o unidades despachadas aún.',
            'data' => null
          ], 200);
        } else {
          $JwtAuth = new \App\Helpers\JwtAuth();
          $unidadesRegistradas = [];

          foreach ($queryUnidades as $vUnidad) {
            $articulosUnidad = DB::table("logistica_transito_articulos AS art")
            ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
            ->where("art.transito_unidad_id", $vUnidad->id_unidad)
            ->select(
              "det.token_detcompra",
              "art.articulo_descripcion AS articulo",
              "art.cantidad_asignada AS cantidad",
              "art.unidad_medida",
            )
            ->get();

            $tipo_transporte_extend = "";
            switch ($vUnidad->tipo_transporte) {
              case 'terrestre':
                $tipo_transporte_extend = "Terrestre (Camión/Tráiler)";
                break;
              case 'maritimo':
                $tipo_transporte_extend = "Marítimo (Buque/Contenedor)";
                break;
              case 'aereo':
                $tipo_transporte_extend = "Aéreo (Avión)";
                break;
              
              default:
                $tipo_transporte_extend = "";
                break;
            }

            $unidadesRegistradas[] = [
              "token_seguimiento_unidad"       => $vUnidad->token_seguimiento_unidad,
              "folio_seguimiento_unidad"       => 'UNIDAD-'.$JwtAuth->generarFolio($vUnidad->folio_seguimiento_unidad),
              "id_unidad_anterior"             => $vUnidad->id_unidad,
              "tipo_transporte"                => $vUnidad->tipo_transporte,
              "tipo_transporte_extend"         => $tipo_transporte_extend,
              
              // Datos del Operador (Desencriptados)
              "operador_nombre"                => $JwtAuth->desencriptar($vUnidad->operador_nombre),
              "operador_telefono"              => $JwtAuth->desencriptar($vUnidad->operador_telefono),
              
              // Identificadores Logísticos (Desencriptados)
              "identificador_principal"        => $JwtAuth->desencriptar($vUnidad->identificador_principal), // Placas/Contenedor/AWB
              "identificador_secundario"       => $JwtAuth->desencriptar($vUnidad->identificador_secundario), // Remolque/Booking/Vuelo
              "permiso_autorizacion"           => !is_null($vUnidad->permiso_autorizacion) ? $JwtAuth->desencriptar($vUnidad->permiso_autorizacion) : "",
              
              // Direcciones de Ruta (Desencriptadas)
              "direccion_origen"               => $JwtAuth->desencriptar($vUnidad->direccion_origen),
              // 📍 Este destino se convierte en el Origen Sugerido para el nuevo tramo de esta unidad
              "direccion_destino_especifica"   => $JwtAuth->desencriptar($vUnidad->direccion_destino_especifica), 
              
              // Listado completo de mercancía amparada
              "articulos"                      => $articulosUnidad,
              "articulos_seleccionados"        => [], // Inicializado vacío para el manejo de selección en PrimeNG
              "unidad_fecha_salida"            => !is_null($vUnidad->unidad_fecha_salida) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_salida) : '',
              "unidad_fecha_tentativa_arribo"  => !is_null($vUnidad->unidad_fecha_tentativa_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_tentativa_arribo) : '',
              "unidad_fecha_real_arribo_reg"   => !is_null($vUnidad->unidad_fecha_real_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_real_arribo) : '',
              "observaciones_arribo_reg"       => !is_null($vUnidad->unidad_observaciones_arribo) ? $JwtAuth->desencriptar($vUnidad->unidad_observaciones_arribo) : '',
              "unidad_arribo_autorizado"       => (bool)$vUnidad->unidad_arribo_autorizado,
              "new_auth_es_bodega_entrega"     => (bool)$vUnidad->direccion_destino_es_bodega_entrega,
              "new_auth_arribo_fecha"          => "", 
              "new_auth_arribo_tipo"           => !$vUnidad->direccion_destino_es_bodega_entrega ? 'liberacionaduana' : 'arribo', 
              "new_auth_arribo_origen"         => !$vUnidad->direccion_destino_es_bodega_entrega ? 'externo' : 'interno',
              "new_auth_arribo_autorizador"    => "", 
              "new_auth_arribo_observaciones"  => "", 
            ];

            
            //$yaEstaAutorizado = ((int)$vHit->arribo_autorizado === 1 || $vHit->arribo_autorizado === true);
            ////$yaTieneArribo = !is_null($vHit->fecha_real_arribo);
            ////$registro_activo = $yaTieneArribo || (!$yaTieneArribo && $hitoAnteriorLlego);
            //$registro_activo = ((int)$vHit->es_el_activo === 1);
            //$unidadesRegistradas[] = [
            //  "token_seguimiento_transito"    => $vHit->token_seguimiento_transito,
            //  "arribo_autorizado"             => $yaEstaAutorizado,
            //  "registro_activo"               => $registro_activo,//!is_null($vHit->fecha_real_arribo) && $vHit->id_hito == $id_primer_hito,
            //  "etapa_anterior"                => $vHit->estado_alcanzado,
            //  "fecha_real_salida"             => !is_null($vHit->fecha_real_salida) ? gmdate('Y-m-d H:i:s',$vHit->fecha_real_salida) : '',
            //  "observaciones_salida"          => !is_null($vHit->observaciones_salida) ? $JwtAuth->desencriptar($vHit->observaciones_salida) : '',
            //  "fecha_tentativa_arribo"        => !is_null($vHit->fecha_tentativa_arribo) ? gmdate('Y-m-d H:i:s',$vHit->fecha_tentativa_arribo) : '',
            //  "fecha_real_arribo_reg"         => !is_null($vHit->fecha_real_arribo) ? gmdate('Y-m-d H:i:s',$vHit->fecha_real_arribo) : '',
            //  "observaciones_arribo_reg"      => !is_null($vHit->observaciones_arribo) ? $JwtAuth->desencriptar($vHit->observaciones_arribo) : '',
            //  "new_auth_arribo_fecha"         => "", 
            //  "new_auth_arribo_tipo"          => "", 
            //  "new_auth_arribo_origen"        => "", 
            //  "new_auth_arribo_autorizador"   => "", 
            //  "new_auth_arribo_observaciones" => "", 
            //  "unidades_anteriores"           => $unidadesProcesadas,
            //];
          }
          
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Estructura de datos completa recuperada exitosamente.',
            'logistica_seguimiento_token' => $logistica_seguimiento_token,
            'unidadesRegistradas' => $unidadesRegistradas,
          );
        }
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function autorizarArribo(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string',
      'token_seguimiento_unidad' => 'required|string',
      'auth_arribo_fecha' => 'required|string',
      'auth_arribo_tipo' => 'required|string',
      'auth_arribo_origen' => 'required|string',
      'auth_arribo_autorizador' => 'nullable|string',
      'auth_arribo_observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');
      $token_seguimiento_unidad = $request->input('token_seguimiento_unidad');
      $auth_arribo_fecha = $request->input('auth_arribo_fecha');
      $auth_arribo_tipo = $request->input('auth_arribo_tipo');
      $auth_arribo_origen = $request->input('auth_arribo_origen');
      $auth_arribo_autorizador = $request->input('auth_arribo_autorizador');
      $auth_arribo_observaciones = $request->input('auth_arribo_observaciones');

      $OKTransitoMain = isset($logistica_seguimiento_token) && !empty($logistica_seguimiento_token);
      $OKTransitoUnidad = isset($token_seguimiento_unidad) && !empty($token_seguimiento_unidad);
      $OKAuthArriboFecha = isset($auth_arribo_fecha) && !empty($auth_arribo_fecha) && preg_match($JwtAuth->filtroFecha(),$auth_arribo_fecha);
      $OKAuthArriboTipo = isset($auth_arribo_tipo) && !empty($auth_arribo_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$auth_arribo_tipo);
      $OKAuthArriboOrigen = isset($auth_arribo_origen) && !empty($auth_arribo_origen) && preg_match($JwtAuth->filtroAlfaNumerico(),$auth_arribo_origen);
      $OKAuthArriboAutorizador = isset($auth_arribo_autorizador) && !empty($auth_arribo_autorizador) && preg_match($JwtAuth->filtroAlfaNumerico(),$auth_arribo_autorizador);
      $OKAuthArriboObservaciones = isset($auth_arribo_observaciones) && !empty($auth_arribo_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$auth_arribo_observaciones);

      if ($OKTransitoMain && $OKTransitoUnidad && $OKAuthArriboFecha && $OKAuthArriboTipo && $OKAuthArriboOrigen && $OKAuthArriboObservaciones) {
        $transitoMainData = LogisticaTransitoMain::where("token_seguimiento_transito", $logistica_seguimiento_token)
        ->select("id","logistica_fecha_contabilizacion","folio_seguimiento_transito")
        ->first();

        if (!$transitoMainData) {
          return response()->json([
            'status' => 'error',
            'message' => 'No se encontró el hito de tránsito específico para autorizar.'
          ], 404);
        }

        $transitoUnidadData = DB::table("logistica_transito_unidades")
        ->where("token_seguimiento_unidad", $token_seguimiento_unidad)
        ->select("id","folio_seguimiento_unidad","unidad_arribo_autorizado")
        ->first();

        if (!$transitoUnidadData) {
          return response()->json([
            'status' => 'error',
            'message' => 'No se encontró el unidad de tránsito específico para autorizar.'
          ], 404);
        }

        if ((int)$transitoUnidadData->unidad_arribo_autorizado === 1) {
          return response()->json([
            'status' => 'error',
            'message' => 'Este arribo ya ha sido autorizado previamente.'
          ], 422);
        }

        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
        ->first();

        if (!$vEmp) {
          return response()->json([
            'status' => 'error',
            'message' => 'No se encontraron datos relacionados a la empresa activa.'
          ], 404);
        }

        DB::beginTransaction();
        try {
          $folio_seguimiento_transito = 'TRANSITO-'.$JwtAuth->generarFolio($transitoMainData->folio_seguimiento_transito);
          $logistica_fecha_contabilizacion = $transitoMainData->logistica_fecha_contabilizacion;
          $nombreDocs = $logistica_fecha_contabilizacion."-".$folio_seguimiento_transito;
          $folio_seguimiento_unidad = 'UNIDAD-'.$JwtAuth->generarFolio($transitoUnidadData->folio_seguimiento_unidad);
          $observaciones_encriptadas = $JwtAuth->encriptar($auth_arribo_observaciones);
          $fecha_unix_auth_arribo = $JwtAuth->convierteFechaEpoc($auth_arribo_fecha);

          $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/logistica/unidades/$folio_seguimiento_unidad/autorizaciones/";

          $tokenAutorizacion = Str::uuid()->toString();
          $transitoMainData->autorizar()->create([
            "transito_autorizacion_token" => $tokenAutorizacion,
            "transito_main"               => $transitoMainData->id, // Llave foránea al hito
            "transito_unidad_id"          => $transitoUnidadData->id, // Llave foránea al hito
            "tipo_autorizacion"           => $auth_arribo_tipo,       // 'arribo' o 'liberacionaduana'
            "origen_autorizacion"         => $auth_arribo_origen,     // 'interno' o 'externo'
            "autorizador_nombre"          => $auth_arribo_origen == 'externo' && $OKAuthArriboAutorizador ? $JwtAuth->encriptar($auth_arribo_autorizador) : NULL,
            "usuario_id"                  => $auth_arribo_origen == 'interno' ? $vEmp->userr : NULL, // ID del usuario autenticado
            "observaciones"               => $observaciones_encriptadas,
            //"created_at"                  => now(),
            //"updated_at"                  => now()
          ]);

          $obtenTransitoCompra = $transitoMainData->id;
          
          if ($request->hasFile('autorizacion_anexos')) {
            $anexos = $request->file('autorizacion_anexos');
          
            // 1. Rendimiento: Consultamos el folio una sola vez fuera del ciclo
            $conteoActual = DB::table("logistica_transito_documentos")->where('folio_modulo', 'LIKE', 'AUTH-ANEX%')->lockForUpdate()->count();
            $folioSiguiente = $conteoActual + 1;
            
            foreach ($anexos as $archivo) {
              if ($archivo && $archivo->isValid()) {
                // 2. Definición de nombre original
                $nombreOriginal = $archivo->getClientOriginalName();
                  
                // Usamos el nombre original directamente ya que $filepath es único por compra
                $nombreFisico = $nombreOriginal;
      
                // 3. Guardado físico en el storage
                $storagePath = "/public/root/" . $filepath;
                $saveFile = Storage::putFileAs($storagePath, $archivo, $nombreFisico);
      
                if (!$saveFile) {
                  throw new \Exception("Error al guardar el archivo físico: $nombreOriginal");
                }
      
                // 4. Preparar datos y generar Token
                $folioModulo = "AUTH-ANEX" . $folioSiguiente;
                $tokenDoc = $JwtAuth->encriptarToken($obtenTransitoCompra, $nombreOriginal, $folioSiguiente);
      
                // 5. Inserción en base de datos
                $insertDoc = DB::table("logistica_transito_documentos")->insert([
                  "token_documento"     => $tokenDoc,
                  "fecha_carga"         => time(),
                  "modulo"              => "pagos",
                  "folio_modulo"        => $folioModulo,
                  "tipo_documento"      => "an",
                  "nombre_documento"    => $JwtAuth->encriptar($nombreOriginal),
                  "transito_main"       => $obtenTransitoCompra,
                  "status_documento"    => true,
                ]);
      
                if (!$insertDoc) {
                  throw new \Exception("Error al registrar el anexo $nombreOriginal en la base de datos.");
                }
    
                // Incrementamos para el siguiente archivo
                $folioSiguiente++;
              }
            }
          }

          //logistica_transito_transbordo_unidades $transitoUnidadData->id 
          //logistica_transito_transbordo_articulos
          $transbordo_main_id = DB::table('logistica_transito_transbordo_unidades')->where('transito_unidad_id',$transitoUnidadData->id)->value('transbordo_id');

          DB::table("logistica_transito_transbordo_unidades")
          ->where('transito_unidad_id',$transitoUnidadData->id)
          ->limit(1)->update([
            "unidad_llego" => TRUE,
            "unidad_fecha_llegada" => $fecha_unix_auth_arribo
          ]);

          $queryUnidadArticulos = DB::table("logistica_transito_articulos")
          ->where("transito_unidad_id",$transitoUnidadData->id)
          ->get();

          foreach ($queryUnidadArticulos as $vUniArt) {
            DB::table("logistica_transito_transbordo_articulos")->insert([
              "transbordo_id"         => $transbordo_main_id,
              "articulo_detcompra"    => $vUniArt->articulo_detcompra,
              "transito_unidad_id"    => $transitoUnidadData->id,
              "transito_articulo_id"  => $vUniArt->id,
              "articulo_descripcion"  => $vUniArt->articulo_descripcion,
              "cantidad_llego"        => $vUniArt->cantidad_asignada,
              "cantidad_disponible"   => $vUniArt->cantidad_asignada,
              "unidad_medida"         => $vUniArt->unidad_medida,
            ]);
          }

          DB::table("logistica_transito_transbordos")
          ->where('id',$transbordo_main_id)
          ->limit(1)->update([
            "arribo_autorizado" => TRUE,
            "fecha_arribo_punto" => $fecha_unix_auth_arribo,
            "usuario_autoriza" => $vEmp->userr,
            "observaciones_arribo" => $observaciones_encriptadas
          ]);

          DB::table("logistica_transito_unidades")->where("id",$transitoUnidadData->id)
          ->limit(1)->update([
            "unidad_arribo_autorizado" => TRUE,
            "unidad_fecha_auth_arribo" => $fecha_unix_auth_arribo
          ]);

          if ($auth_arribo_origen == 'interno') {
            DB::table("eegr_compras AS buy")
            ->join("eegr_compras_detalle AS detBuy", "buy.id", "detBuy.numero_compra")
            ->join("logistica_transito_articulos AS logArt", "detBuy.id", "logArt.articulo_detcompra")
            //->join("logistica_transito_unidades AS logUni", "logArt.transito_unidad_id", "logUni.id")
            ->where("logArt.transito_unidad_id",$transitoUnidadData->id)
            ->update([
              "buy.fecha_real_recepcion" => $fecha_unix_auth_arribo
            ]);

            //DB::table("eegr_compras")->where("id",$compraData->id)
            //->limit(1)
            //->update(["buy.fecha_real_recepcion" => $fecha_unix_auth_arribo]);
          }

          DB::commit();

          // Modificamos la respuesta final para que devuelva éxito tras guardar
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Autorización de arribo registrada y procesada exitosamente.',
            'token_autorizacion' => $tokenAutorizacion
          );
        } catch (\Exception $e) {
          DB::rollBack();
          return response()->json([
            'status' => 'error',
            'message' => 'Fallo al salvar la autorización en el sistema financiero/logístico.',
            'details' => $e->getMessage()
          ], 500);
        }
      } else {
        $mensaje_error = '';
        if (!$OKTransitoMain) { $mensaje_error = 'Error en traslado relacionado, verifique su información o comuniquese a soporte'; }
        if (!$OKTransitoUnidad) { $mensaje_error = 'Error en unidad relacionada, verifique su información o comuniquese a soporte'; }
        if (!$OKAuthArriboFecha) {$mensaje_error = 'Error en fecha de arribo, verifique su información o comuniquese a soporte';}
        if (!$OKAuthArriboTipo) {$mensaje_error = 'Error en tipo de arribo, verifique su información o comuniquese a soporte';}
        if (!$OKAuthArriboOrigen) {$mensaje_error = 'Error en origen de arribo, verifique su información o comuniquese a soporte';}
        //if (!$OKAuthArriboAutorizador) {$mensaje_error = 'Error en persona que autoriza, verifique su información o comuniquese a soporte';}
        if (!$OKAuthArriboObservaciones) {$mensaje_error = 'Error en las observaciones de la autorización, verifique su información o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 400,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function obtenerUbicacionesSinEntrega(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');

      try {
        $queryLogisticaTransito = DB::table("logistica_transito_main AS logis")
        ->join("main_empresas AS emp", "logis.empresa_vinculada", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "logis.token_seguimiento_transito" => $logistica_seguimiento_token,
          "emp.empresa_token" => $empresa, 
          "users.usuario_token" => $usuario
        ])
        ->whereIn('logis.id', function ($query) {
          $query->select('transito_main')->from('logistica_transito_unidades');
        })
        ->select("logis.id AS id_transito","logis.*","emp.*")
        ->orderBy("logis.id","DESC")
        ->get();
        
        if ($queryLogisticaTransito->isEmpty()) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontraron salidas de logística registradas'
          );
        } else {
          $infoLogisticaTransito = [];

          foreach ($queryLogisticaTransito as $vLogTrans) {
            $infoLogisticaTransito[] = [
              "token_seguimiento_transito" => $vLogTrans->token_seguimiento_transito,
              "folio_seguimiento_transito" => "TRANSITO-".$JwtAuth->generarFolio($vLogTrans->folio_seguimiento_transito),
              "estado_alcanzado" => $vLogTrans->estado_alcanzado,
              "fecha_real_salida" => $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->fecha_real_salida),
              "observaciones_salida" => $JwtAuth->desencriptar($vLogTrans->observaciones_salida),
              "arribo_final_fecha_tentativa" => $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_tentativa),
              "arribo_final_fecha_real" => $vLogTrans->arribo_final_fecha_real ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_real) : '',
              "arribo_final_observaciones" => $vLogTrans->arribo_final_observaciones ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_observaciones) : '',
              "arribo_final_autorizado" => (bool)$vLogTrans->arribo_final_autorizado,
              "arribo_final_fecha_auth" => $vLogTrans->arribo_final_fecha_auth ? $JwtAuth->mostrarUnixAFechaMexico($vLogTrans->arribo_final_fecha_auth) : '',
              "usuario_registra" => $vLogTrans->usuario_registra,
            ];
          }
          
          $puntosRegistrados = [];
          $puntoTransbordo = DB::table('logistica_transito_transbordos as ltransb')
          //lugar_transbordo 	arribo_autorizado 	fecha_arribo_punto 	usuario_autoriza 	observaciones_arribo
          ->join('logistica_transito_main as logis', 'ltransb.transito_main', '=', 'logis.id') // <- Ajustar aquí el nombre de la columna de unión si es necesario
          ->select(
            'ltransb.id AS id_punto_transbordo',
            'ltransb.token_transito_transbordo',
            'ltransb.folio_transito_transbordo',
            'ltransb.lugar_transbordo',
            'ltransb.observaciones_arribo'
          )
          ->where('logis.token_seguimiento_transito', $logistica_seguimiento_token) // <- Filtro corregido apuntando a la tabla main
          ->whereNotNull('ltransb.fecha_arribo_punto')
          ->where('ltransb.arribo_autorizado',TRUE)
          ->get();
          
          foreach ($puntoTransbordo as $vPunto) {
            $articulosUnidad = [];
            $queryUnidadArticulos = DB::table("logistica_transito_transbordo_articulos AS transbArt")
            ->join("logistica_transito_articulos AS logArt", "transbArt.transito_articulo_id", "=", "logArt.id")
            ->join("eegr_compras_detalle AS detBuy", "transbArt.articulo_detcompra", "=", "detBuy.id")
            ->join("in_egr_catalogo_productos AS catprod", "detBuy.producto", "=", "catprod.id")
            ->join("eegr_compras AS buy", "detBuy.numero_compra", "=", "buy.id")
            ->whereNotNull('detBuy.producto')
            ->where("transbArt.transbordo_id", $vPunto->id_punto_transbordo)
            ->select(
              "buy.token_compras",
              "buy.folio_compra",
              "buy.post_folio AS post_folio_compra",
              //detBuy
              "detBuy.token_detcompra",
              "detBuy.efecto_fiscal",
              "detBuy.producto",
              "detBuy.concepto_cfdi",
              "detBuy.moneda_detalle_compra",
              "detBuy.tipo_de_cambio_detalle_compra",
              "detBuy.precio_unitario",
              "detBuy.descuento",
              "detBuy.retenciones_total",
              "detBuy.traslados_total",
              "detBuy.destino",
              //logistica_transito_articulos
              "transbArt.transito_unidad_id AS unidad_vinculada",
              "logArt.articulo_descripcion AS articulo",
              "transbArt.cantidad_llego",
              "transbArt.cantidad_disponible",
              "logArt.unidad_medida",
              //catprod
              "catprod.token_cat_productos",
              "catprod.folio_sistema AS folio_prod",
              "catprod.post_folio AS post_folio_prod",
              "catprod.producto AS prod_name",
              "catprod.codigo_sku",
              "catprod.codigo_gtin",
              "catprod.codigo_giai",
              "catprod.tipo_llave_gs1"
            )
            ->get();

            $idUnidadVinculada = $queryUnidadArticulos->pluck('unidad_vinculada')->filter()->unique()->toArray();
            $transitoUnidadesMap = DB::table('logistica_transito_unidades')->whereIn('id', $idUnidadVinculada)->get()->keyBy('id');

            foreach ($queryUnidadArticulos as $vUniArt) {
              $token_producto = "";
              $reg_actf_codigo_sku = "";
              $reg_actf_codigo_gs1 = "";
              $reg_actf_tipo_llave_gs1 = "";
              $articulo_activo = 'PROD-'.$JwtAuth->generarFolio($vUniArt->folio_prod). (!is_null($vUniArt->post_folio_prod) ? '-'.$vUniArt->post_folio_prod : '')." ".$JwtAuth->desencriptar($vUniArt->prod_name);
              
              $token_producto = $vUniArt->token_cat_productos;
              $reg_actf_codigo_sku = !is_null($vUniArt->codigo_sku) ? $vUniArt->codigo_sku : '';
              $reg_actf_codigo_gs1 = !is_null($vUniArt->codigo_giai) ? $vUniArt->codigo_giai : '';
              $reg_actf_tipo_llave_gs1 = !is_null($vUniArt->tipo_llave_gs1) ? 'Activo Fijo (GIAI)' : '';
              
              $efecto_fiscal = "";
      
              switch ($vUniArt->efecto_fiscal) {
                case 'ded_inm_apl_mes':
                  $efecto_fiscal = "Deducciones Inmediata aplicables al mes";
                  break;
                case 'ded_pers_anual':
                  $efecto_fiscal = "Deducción Personal (Anual)";
                  break;
                case 'ded_inversion':
                  $efecto_fiscal = "Deducción de Inversión";
                  break;
                case 'no_deducible':
                  $efecto_fiscal = "No deducible";
                  break;
                default:
                  $efecto_fiscal = "";
                  break;
              }

              $activos_en_espera = $vUniArt->cantidad_disponible;
              $vUnidad = $transitoUnidadesMap->get($vUniArt->unidad_vinculada);
              $tipo_transporte_extend = match ($vUnidad->tipo_transporte) {
                'terrestre' => "Terrestre (Camión/Tráiler)",
                'maritimo'  => "Marítimo (Buque/Contenedor)",
                'aereo'     => "Aéreo (Avión)",
                default     => "",
              };

              $articulosUnidad[] = [
                "compra_relacionada_token" => $vUniArt->token_compras,
                "compra_relacionada_folio" => "COMP-".$JwtAuth->generarFolio($vUniArt->folio_compra).($vUniArt->post_folio_compra != NULL ? '-'.$vUniArt->post_folio_compra : ''),
                "token_detcompra" => $vUniArt->token_detcompra,
                "token_cat_productos" => $token_producto,
                "concepto_cfdi" => $vUniArt->concepto_cfdi ? $JwtAuth->desencriptar($vUniArt->concepto_cfdi) : '',
                "articulo" => $articulo_activo,
                "moneda_detalle_compra" => $vUniArt->moneda_detalle_compra,
                "tipo_de_cambio_detalle_compra" => $vUniArt->tipo_de_cambio_detalle_compra,
                "precio_unitario" => $vUniArt->precio_unitario,
                "cantidad_llego" => (int)$vUniArt->cantidad_llego,
                "cantidad_pendiente_transito" => (int)$activos_en_espera,
                "cantidad_transitar" => 0,
                "unidad_medida" => $vUniArt->unidad_medida,
                "descuento" => $vUniArt->descuento,
                "retenciones_total" => $vUniArt->retenciones_total,
                "traslados_total" => $vUniArt->traslados_total,
                "destino" => $vUniArt->destino,
                "efecto_fiscal" => $efecto_fiscal,
                "serie" => $vActDet->serie ?? '',
                "lote" => $vActDet->lote ?? '',
                "pedimento_aduanal" => $vActDet->pedimento_aduanal ?? '',
                "reg_sku" => $reg_actf_codigo_sku,
                "reg_tipo_llave_gs1" => $reg_actf_tipo_llave_gs1,
                "reg_codigo_gs1" => $reg_actf_codigo_gs1,
                //ubnidad_vinculada
                "ver_articulo_unidad" => FALSE,
                "unidad_vinculada" => $vUniArt->unidad_vinculada,
                "token_unidad_vinculada"       => $vUnidad->token_seguimiento_unidad,
                "folio_seguimiento_unidad"       => 'UNIDAD-'.$JwtAuth->generarFolio($vUnidad->folio_seguimiento_unidad),
                //"id_unidad_anterior"             => $vUnidad->id_unidad,
                "tipo_transporte"                => $vUnidad->tipo_transporte,
                "tipo_transporte_extend"         => $tipo_transporte_extend,
                
                // Datos del Operador (Desencriptados)
                "operador_nombre"                => $JwtAuth->desencriptar($vUnidad->operador_nombre),
                "operador_telefono"              => $JwtAuth->desencriptar($vUnidad->operador_telefono),
                
                // Identificadores Logísticos (Desencriptados)
                "identificador_principal"        => $JwtAuth->desencriptar($vUnidad->identificador_principal), // Placas/Contenedor/AWB
                "identificador_secundario"       => $JwtAuth->desencriptar($vUnidad->identificador_secundario), // Remolque/Booking/Vuelo
                "permiso_autorizacion"           => !is_null($vUnidad->permiso_autorizacion) ? $JwtAuth->desencriptar($vUnidad->permiso_autorizacion) : "",
                
                // Direcciones de Ruta (Desencriptadas)
                "direccion_origen"               => $JwtAuth->desencriptar($vUnidad->direccion_origen),
                // 📍 Este destino se convierte en el Origen Sugerido para el nuevo tramo de esta unidad
                "direccion_destino_especifica"   => $JwtAuth->desencriptar($vUnidad->direccion_destino_especifica), 
                
                // Listado completo de mercancía amparada
                "articulos_seleccionados"        => [], // Inicializado vacío para el manejo de selección en PrimeNG
                "unidad_fecha_salida"            => !is_null($vUnidad->unidad_fecha_salida) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_salida) : '',
                "unidad_fecha_tentativa_arribo"  => !is_null($vUnidad->unidad_fecha_tentativa_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_tentativa_arribo) : '',
                "unidad_fecha_real_arribo_reg"   => !is_null($vUnidad->unidad_fecha_real_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($vUnidad->unidad_fecha_real_arribo) : '',
                "observaciones_arribo_reg"       => !is_null($vUnidad->unidad_observaciones_arribo) ? $JwtAuth->desencriptar($vUnidad->unidad_observaciones_arribo) : '',
                "unidad_arribo_autorizado"       => (bool)$vUnidad->unidad_arribo_autorizado,
                "new_auth_es_bodega_entrega"     => (bool)$vUnidad->direccion_destino_es_bodega_entrega,
                "new_auth_arribo_fecha"          => "", 
                "new_auth_arribo_tipo"           => !$vUnidad->direccion_destino_es_bodega_entrega ? 'liberacionaduana' : 'arribo', 
                "new_auth_arribo_origen"         => !$vUnidad->direccion_destino_es_bodega_entrega ? 'externo' : 'interno',
              ];
            }

            $puntosRegistrados[] = [
              "punto_seleccionado"           => FALSE,
              "token_transito_transbordo"    => $vPunto->token_transito_transbordo,
              "folio_transito_transbordo"    => 'ESCALA-'.$JwtAuth->generarFolio($vPunto->folio_transito_transbordo),
              "lugar_transbordo"             => $JwtAuth->desencriptarDireccion($vPunto->lugar_transbordo),
              "observaciones_arribo"         => $JwtAuth->desencriptar($vPunto->observaciones_arribo),
              "articulos"                    => $articulosUnidad,
            ];
          }

          $dataMensaje = array(
            'code' => 200,
            'status' => 'success',
            'logisticaTransito' => $infoLogisticaTransito,
            "puntosRegistrados" => $puntosRegistrados,
          );
        }
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function continuarRuta(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string',
      'punto_seleccionado' => 'required|string',
      'transportes' => 'required|json',
      'observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');
      $punto_seleccionado = $request->input('punto_seleccionado');
      $transportes = json_decode($request->input('transportes'), true);//$request->input('transportes');
      $observaciones = $request->input('observaciones');

      $OKTransitoMain = isset($logistica_seguimiento_token) && !empty($logistica_seguimiento_token);
      $OKPuntoSeleccionado = isset($punto_seleccionado) && !empty($punto_seleccionado);
      $OKTransportes = isset($transportes) && !empty($transportes) && is_array($transportes);
      $OKObservaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      foreach ($transportes as $index => $transp) {
        $subValidator = \Validator::make($transp, [
          'tipo_transporte'                 => 'required|string',
          'operador_nombre'                 => 'required|string',
          'operador_telefono'               => 'required|string',
          'tentativa_llegada_destino'       => 'required|string',
          'identificador_principal'         => 'required|string',
          'identificador_secundario'        => 'required|string', // Estrictamente obligatorio según tus reglas
          'direccion_origen'                => 'required|string',
          'direccion_destino_especifica'    => 'required|string',
          'carta_porte_relacionada'         => 'nullable|string',
          'articulos'                       => 'required|array|min:1',
          'articulos.*.articulo'            => 'required|string',
          'articulos.*.cantidad_transitar'  => 'required|integer',
          'articulos.*.sku'                 => 'nullable|string',
        ]);

        if ($subValidator->fails()) {
          return response()->json([
            'status'  => 'error',
            'message' => "Error de validación en la Unidad " . ($index + 1),
            'errors'  => $subValidator->errors()
          ], 422);
        }
      }

      if ($OKTransitoMain && $OKPuntoSeleccionado && $OKTransportes && $OKObservaciones) {
        $transitoMainData = LogisticaTransitoMain::where("token_seguimiento_transito", $logistica_seguimiento_token)
        ->select("id","logistica_fecha_contabilizacion","folio_seguimiento_transito")
        ->first();

        if (!$transitoMainData) {
          return response()->json([
            'status' => 'error',
            'message' => 'No se encontró el hito de tránsito específico para autorizar.'
          ], 404);
        }

        $transitoTransbordoData = DB::table("logistica_transito_transbordos")
        ->where("token_transito_transbordo", $punto_seleccionado)
        ->select("id","arribo_autorizado","folio_transito_transbordo")
        ->first();

        if (!$transitoTransbordoData) {
          return response()->json([
            'status' => 'error',
            'message' => 'No se encontró el punto de transbordo para continuar ruta.'
          ], 404);
        }

        //if ((int)$transitoTransbordoData->arribo_autorizado === 1) {
        //  return response()->json([
        //    'status' => 'error',
        //    'message' => 'Este arribo ya ha sido autorizado previamente.'
        //  ], 422);
        //}

        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
        ->first();

        if (!$vEmp) {
          return response()->json([
            'status' => 'error',
            'message' => 'No se encontraron datos relacionados a la empresa activa.'
          ], 404);
        }

        DB::beginTransaction();
        try {
          $folio_seguimiento_transito = 'TRANSITO-'.$JwtAuth->generarFolio($transitoMainData->folio_seguimiento_transito);
          $logistica_fecha_contabilizacion = $transitoMainData->logistica_fecha_contabilizacion;
          $nombreDocs = $logistica_fecha_contabilizacion."-".$folio_seguimiento_transito;
          $folio_transito_transbordo = 'ESCALA-'.$JwtAuth->generarFolio($transitoTransbordoData->folio_transito_transbordo);

          $direccionesAgrupadas = [];
          $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/logistica/transbordos/$folio_transito_transbordo/";
          $obtenTransitoCompra = $transitoMainData->id;
          
          foreach ($transportes as $transpData) {
            $direccion_destino_limpia = trim($transpData['direccion_destino_especifica']);
            $direccion_destino = $JwtAuth->encriptar($direccion_destino_limpia);
            $maxFolioTranUnidad = DB::table('logistica_transito_unidades')->where('transito_main',$obtenTransitoCompra)->lockForUpdate()->max('folio_seguimiento_unidad');
            $folioTranUnidad = $maxFolioTranUnidad ? $maxFolioTranUnidad + 1 : 1;
            $cfdi_c_porte = $transpData['carta_porte_relacionada'] != '' ? DB::table("comprobante_carta_porte")->where("id_ccp",$transpData['carta_porte_relacionada'])->value("comprobante_fiscal") : NULL;

            //ALTER TABLE `logistica_transito_unidades` ADD `punto_transbordo_salida` BIGINT(20) UNSIGNED NULL AFTER `unidad_fecha_auth_arribo`;
            $unidad = $transitoMainData->unidades()->create([
              'token_seguimiento_unidad'       => Str::uuid()->toString(),
              'folio_seguimiento_unidad'       => $folioTranUnidad,
              'tipo_trayecto'                  => 'recorrido',
              'tipo_transporte'                => $transpData['tipo_transporte'], // 'terrestre', 'maritimo', 'aereo',
              'operador_nombre'                => $JwtAuth->encriptar($transpData['operador_nombre']),
              'operador_telefono'              => $JwtAuth->encriptar($transpData['operador_telefono']),
              'identificador_principal'        => $JwtAuth->encriptar($transpData['identificador_principal']), //-- Placas, Contenedor o Guía AWB
              'identificador_secundario'       => $JwtAuth->encriptar($transpData['identificador_secundario']),     //-- Remolque, Booking o Vuelo
              'permiso_autorizacion'           => !is_null($transpData['permiso_autorizacion']) ? $JwtAuth->encriptar($transpData['permiso_autorizacion']) : NULL,         //-- Permiso SCT / SICT
              'direccion_origen'               => $JwtAuth->encriptar($transpData['direccion_origen']),
              'direccion_destino_especifica'   => $direccion_destino,
              'cfdi_relacionado'               => $cfdi_c_porte,
              'estado_consumo'                 => 'disponible',
              'unidad_fecha_salida'            => $JwtAuth->convierteFechaEpoc($transpData['salida_destino']),
              'unidad_fecha_tentativa_arribo'  => $JwtAuth->convierteFechaEpoc($transpData['tentativa_llegada_destino']),
              'punto_transbordo_salida'        => $transitoTransbordoData->id,
            ]);
            
            if (!isset($direccionesAgrupadas[$direccion_destino_limpia])) {
              $direccionesAgrupadas[$direccion_destino_limpia] = [
                'direccion'   => $direccion_destino_limpia,
                'unidades_id' => [] // Arreglo dinámico que guardará los IDs
              ];
            }
            // Añadimos el ID de la unidad recién creada al grupo de esta dirección
            $direccionesAgrupadas[$direccion_destino_limpia]['unidades_id'][] = $unidad->id;

            foreach ($transpData['articulos'] as $artData) {
              $data_det_compra = DB::table("eegr_compras_detalle")
              ->where("token_detcompra",$artData['token_detcompra'])
              ->select('id AS id_det_compra','precio_unitario')
              ->first();
              
              $unidad->articulos()->create([
                'articulo_detcompra'   => $data_det_compra->id_det_compra,
                'articulo_descripcion' => $artData['articulo'],
                'cantidad_asignada'    => $artData['cantidad_transitar'],
                'unidad_medida'        => $artData['unidad_medida'] ?? null,
              ]);

              $transitoUnidadID = DB::table('logistica_transito_unidades')->where('token_seguimiento_unidad', $artData['token_unidad_vinculada'])->value('id');
              DB::table("logistica_transito_unidades_union_puntos")->insert([
                "unidad_punto_anterior"  => $transitoUnidadID,
                "unidad_nuevo_punto"     => $unidad->id
              ]);
            }
          }

          $transbordos_ligados = array_values($direccionesAgrupadas);
          
          foreach ($transbordos_ligados as $grupo) {
            // Obtenemos el folio que le toca a esta escala
            $maxFolioTransbordo = DB::table('logistica_transito_transbordos')->where('transito_main',$obtenTransitoCompra)->lockForUpdate()->max('folio_transito_transbordo');
            $folioTransbordo = $maxFolioTransbordo ? $maxFolioTransbordo + 1 : 1;
            $token_transito_transbordo = Str::uuid()->toString();
            // Encriptamos la dirección usando el método determinista (o el que uses para transbordos)
            $direccion_destino_encrypt = $JwtAuth->encriptarDireccion($grupo['direccion']);
            $idTransbordo = DB::table("logistica_transito_transbordos")->insertGetId([
              "token_transito_transbordo" => $token_transito_transbordo,
              "folio_transito_transbordo" => $folioTransbordo,
              "transito_main"             => $obtenTransitoCompra,
              "lugar_transbordo"          => $direccion_destino_encrypt,//$transpData['direccion_destino_especifica'],
              "arribo_autorizado"         => FALSE,
            ]);

            // Vinculamos todas las unidades que pertenecen a esta misma dirección
            foreach ($grupo['unidades_id'] as $unidadId) {
              DB::table("logistica_transito_transbordo_unidades")->insert([
                "transbordo_id"      => $idTransbordo,
                "transito_unidad_id" => $unidadId
              ]);
            }
          }

          if ($request->hasFile('autorizacion_anexos')) {
            $anexos = $request->file('autorizacion_anexos');
          
            // 1. Rendimiento: Consultamos el folio una sola vez fuera del ciclo
            $conteoActual = DB::table("logistica_transito_documentos")->where('folio_modulo', 'LIKE', 'AUTH-ANEX%')->lockForUpdate()->count();
            $folioSiguiente = $conteoActual + 1;
            
            foreach ($anexos as $archivo) {
              if ($archivo && $archivo->isValid()) {
                // 2. Definición de nombre original
                $nombreOriginal = $archivo->getClientOriginalName();
                  
                // Usamos el nombre original directamente ya que $filepath es único por compra
                $nombreFisico = $nombreOriginal;
      
                // 3. Guardado físico en el storage
                $storagePath = "/public/root/" . $filepath;
                $saveFile = Storage::putFileAs($storagePath, $archivo, $nombreFisico);
      
                if (!$saveFile) {
                  throw new \Exception("Error al guardar el archivo físico: $nombreOriginal");
                }
      
                // 4. Preparar datos y generar Token
                $folioModulo = "AUTH-ANEX" . $folioSiguiente;
                $tokenDoc = $JwtAuth->encriptarToken($obtenTransitoCompra, $nombreOriginal, $folioSiguiente);
      
                // 5. Inserción en base de datos
                $insertDoc = DB::table("logistica_transito_documentos")->insert([
                  "token_documento"     => $tokenDoc,
                  "fecha_carga"         => time(),
                  "modulo"              => "pagos",
                  "folio_modulo"        => $folioModulo,
                  "tipo_documento"      => "an",
                  "nombre_documento"    => $JwtAuth->encriptar($nombreOriginal),
                  "transito_main"       => $obtenTransitoCompra,
                  "status_documento"    => true,
                ]);
      
                if (!$insertDoc) {
                  throw new \Exception("Error al registrar el anexo $nombreOriginal en la base de datos.");
                }
    
                // Incrementamos para el siguiente archivo
                $folioSiguiente++;
              }
            }
          }

          DB::commit();

          // Modificamos la respuesta final para que devuelva éxito tras guardar
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Nueva ruta registrada.',
          );
        } catch (\Exception $e) {
          DB::rollBack();
          return response()->json([
            'status' => 'error',
            'message' => 'Fallo al salvar la autorización en el sistema financiero/logístico.',
            'details' => $e->getMessage()
          ], 500);
        }
      } else {
        $mensaje_error = '';
        if (!$OKTransitoMain) { $mensaje_error = 'Error en traslado relacionado, verifique su información o comuniquese a soporte'; }
        if (!$OKPuntoSeleccionado) { $mensaje_error = 'Error en puntos de transbordo relacionados, verifique su información o comuniquese a soporte'; }
        if (!$OKTransportes) { $mensaje_error = 'Error en Unidades / Medios de Transporte Despachados, verifique su información o comuniquese a soporte'; }
        if (!$OKObservaciones) {$mensaje_error = 'Error en las observaciones de la nueva ruta, verifique su información o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 400,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function monitorRutas_Logistica(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');
      try {
        $salidaTransito = DB::table("logistica_transito_main AS logis")
        ->join("main_empresas AS emp", "logis.empresa_vinculada", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "logis.token_seguimiento_transito" => $logistica_seguimiento_token,
          "emp.empresa_token" => $empresa, 
          "users.usuario_token" => $usuario
        ])
        ->whereIn('logis.id', function ($query) {
          $query->select('transito_main')->from('logistica_transito_unidades');
        })
        ->select("logis.id AS id_transito","logis.*","emp.*")
        ->orderBy("logis.id","DESC")
        ->first();

        if (!$salidaTransito) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontraron salidas de logística registradas'
          );
        } else {
          $infoLogisticaTransito = [];
          $caminoSecuencial = [];
          $unidadesRaiz = DB::table("logistica_transito_unidades")
          ->where("transito_main", $salidaTransito->id_transito)
          ->where("tipo_trayecto", "inicio")
          ->get();

          foreach ($unidadesRaiz as $vUniRaiz) {
            $unidadActualId = $vUniRaiz->id;
            $visitados = [];

            while ($unidadActualId && !in_array($unidadActualId, $visitados)) {
              $visitados[] = $unidadActualId;
              $unidad = DB::table("logistica_transito_unidades")->where("id",$unidadActualId)->first();

              $tipo_transporte_extend = match ($unidad->tipo_transporte) {
                'terrestre' => "Terrestre (Camión/Tráiler)",
                'maritimo'  => "Marítimo (Buque/Contenedor)",
                'aereo'     => "Aéreo (Avión)",
                default     => "",
              };
              
              $articulosUnidad = DB::table("logistica_transito_articulos AS art")
              ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
              ->where("art.transito_unidad_id", $unidad->id)
              ->select(
                "det.token_detcompra",
                "art.articulo_descripcion AS articulo",
                "art.cantidad_asignada AS cantidad",
                "art.unidad_medida",
              )
              ->get();

              $caminoSecuencial[] = [
                "tipo_nodo"                      => "TRANSPORTE",
                "abrev_nodo"                     => "TR",
                "id_unidad"                      => $unidad->id,
                "folio_seguimiento_timeline"     => 'UNIDAD-'.$JwtAuth->generarFolio($unidad->folio_seguimiento_unidad),
                "tipo_transporte_extend"         => $tipo_transporte_extend,
                // Datos del Operador (Desencriptados)
                "operador_nombre"                => $JwtAuth->desencriptar($unidad->operador_nombre),
                "operador_telefono"              => $JwtAuth->desencriptar($unidad->operador_telefono),
                // Identificadores Logísticos (Desencriptados)
                "identificador_principal"        => $JwtAuth->desencriptar($unidad->identificador_principal), // Placas/Contenedor/AWB
                "identificador_secundario"       => $JwtAuth->desencriptar($unidad->identificador_secundario), // Remolque/Booking/Vuelo
                "permiso_autorizacion"           => !is_null($unidad->permiso_autorizacion) ? $JwtAuth->desencriptar($unidad->permiso_autorizacion) : "",
                // Direcciones de Ruta (Desencriptadas)
                "direccion_origen"               => $JwtAuth->desencriptar($unidad->direccion_origen),
                // 📍 Este destino se convierte en el Origen Sugerido para el nuevo tramo de esta unidad
                "direccion_destino_especifica"   => $JwtAuth->desencriptar($unidad->direccion_destino_especifica), 
                "unidad_fecha_salida"            => !is_null($unidad->unidad_fecha_salida) ? $JwtAuth->mostrarUnixAFechaMexico($unidad->unidad_fecha_salida) : '',
                "unidad_fecha_tentativa_arribo"  => !is_null($unidad->unidad_fecha_tentativa_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($unidad->unidad_fecha_tentativa_arribo) : '',
                "unidad_fecha_real_arribo_reg"   => !is_null($unidad->unidad_fecha_real_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($unidad->unidad_fecha_real_arribo) : 'En ruta...',
                "observaciones_arribo_reg"       => !is_null($unidad->unidad_observaciones_arribo) ? $JwtAuth->desencriptar($unidad->unidad_observaciones_arribo) : '',
                "unidad_arribo_autorizado"       => (bool)$unidad->unidad_arribo_autorizado,
                "estatus"                        => $unidad->unidad_fecha_real_arribo ? ($unidad->unidad_arribo_autorizado ? 'completado' : 'arribado_pendiente_auth') : 'en_transito',
                "articulos_unidad"               => $articulosUnidad,
              ];

              $transbordo = DB::table("logistica_transito_transbordo_unidades AS tu")
              ->join("logistica_transito_transbordos AS t", "tu.transbordo_id", "=", "t.id")
              ->where("tu.transito_unidad_id", $unidadActualId)
              ->select("t.*")
              ->first();

              if ($transbordo) {
                $articulosTransbordo = DB::table("logistica_transito_transbordo_articulos AS art")
                ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
                ->where("art.transbordo_id", $transbordo->id)
                ->select(
                  "det.token_detcompra",
                  "art.articulo_descripcion AS articulo",
                  "art.cantidad_llego",
                  "art.cantidad_disponible AS cantidad_pendiente_transito",
                  "art.unidad_medida",
                )
                ->get();

                $caminoSecuencial[] = [
                  "tipo_nodo"                    => "PUNTO DE TRANSBORDO",
                  "abrev_nodo"                   => "PT",
                  "id_transbordo"                => $transbordo->id,
                  "folio_seguimiento_timeline"   => 'ESCALA-'.$JwtAuth->generarFolio($transbordo->folio_transito_transbordo),
                  "lugar"                        => $JwtAuth->desencriptarDireccion($transbordo->lugar_transbordo),
                  "autorizado"                   => (bool)$transbordo->arribo_autorizado,
                  "fecha_arribo"                 => $transbordo->fecha_arribo_punto ? $JwtAuth->mostrarUnixAFechaMexico($transbordo->fecha_arribo_punto) : '',
                  "observaciones"                => $transbordo->observaciones_arribo ? $JwtAuth->desencriptar($transbordo->observaciones_arribo) : '',
                  "articulos_transbordo"         => $articulosTransbordo,
                ];
              }

              $siguienteUnion = DB::table("logistica_transito_unidades_union_puntos")
              ->where("unidad_punto_anterior", $unidadActualId)
              ->first();

              // Si hay un relevo, actualizamos el ID para continuar el ciclo; si no, el camino terminó
              $unidadActualId = $siguienteUnion ? $siguienteUnion->unidad_nuevo_punto : null;
            }
          }

          //$a = 1;
          //while ($a <= 10) {
          //  $infoLogisticaTransito[] = [
          //    "capacidad_actual" => "$a litros"
          //  ];
          //  $a++;
          //}

          $dataMensaje = array(
            'code' => 200,
            'status' => 'success',
            'camino_recorrido' => $caminoSecuencial
          );
        }
        
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  private function formatearDataUnidad($unidad, $JwtAuth) {
    $tipo_transporte_extend = match ($unidad->tipo_transporte) {
      'terrestre' => "Terrestre (Camión/Tráiler)",
      'maritimo'  => "Marítimo (Buque/Contenedor)",
      'aereo'     => "Aéreo (Avión)",
      default     => "",
    };
  
    $articulosUnidad = DB::table("logistica_transito_articulos AS art")
      ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
      ->where("art.transito_unidad_id", $unidad->id)
      ->select("det.token_detcompra", "art.articulo_descripcion AS articulo", "art.cantidad_asignada AS cantidad", "art.unidad_medida")
      ->get();
  
    return [
      "id_unidad"                     => $unidad->id,
      "folio_seguimiento_timeline"    => 'UNIDAD-'.$JwtAuth->generarFolio($unidad->folio_seguimiento_unidad),
      "tipo_transporte_extend"        => $tipo_transporte_extend,
      "operador_nombre"               => $JwtAuth->desencriptar($unidad->operador_nombre),
      "operador_telefono"             => $JwtAuth->desencriptar($unidad->operador_telefono),
      "identificador_principal"       => $JwtAuth->desencriptar($unidad->identificador_principal), 
      "identificador_secundario"      => $JwtAuth->desencriptar($unidad->identificador_secundario), 
      "direccion_origen"              => $JwtAuth->desencriptar($unidad->direccion_origen),
      "direccion_destino_especifica"  => $JwtAuth->desencriptar($unidad->direccion_destino_especifica), 
      "unidad_fecha_salida"           => !is_null($unidad->unidad_fecha_salida) ? $JwtAuth->mostrarUnixAFechaMexico($unidad->unidad_fecha_salida) : '',
      "unidad_fecha_real_arribo_reg"  => !is_null($unidad->unidad_fecha_real_arribo) ? $JwtAuth->mostrarUnixAFechaMexico($unidad->unidad_fecha_real_arribo) : 'En ruta...',
      "estatus"                       => $unidad->unidad_fecha_real_arribo ? ($unidad->unidad_arribo_autorizado ? 'completado' : 'arribado_pendiente_auth') : 'en_transito',
      "articulos_unidad"              => $articulosUnidad
    ];
  }

  public function monitorRutasLogistica(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'logistica_seguimiento_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $logistica_seguimiento_token = $request->input('logistica_seguimiento_token');
      //echo $JwtAuth->encriptarDireccion("Aeropuerto Internacional de la Ciudad de México (MEX) (Av. Capitán Carlos León S/N, Col. Peñón de los Baños, Alcaldía Venustiano Carranza, CDMX, C.P. 15620)");exit;
      try {
        $salidaTransito = DB::table("logistica_transito_main AS logis")
        ->join("main_empresas AS emp", "logis.empresa_vinculada", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          "logis.token_seguimiento_transito" => $logistica_seguimiento_token,
          "emp.empresa_token" => $empresa, 
          "users.usuario_token" => $usuario
        ])
        ->whereIn('logis.id', function ($query) {
          $query->select('transito_main')->from('logistica_transito_unidades');
        })
        ->select("logis.id AS id_transito","logis.*","emp.*")
        ->orderBy("logis.id","DESC")
        ->first();

        if (!$salidaTransito) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontraron salidas de logística registradas'
          );
        } else {
          $secuenciaLogistica = [];
          
          // 1. Obtenemos todos los puntos de transbordo involucrados en este tránsito principal
          // Nota: Ajusta los joins según cómo se vinculen tus tablas, asumo que transbordo_unidades conecta ambos mundos.
          $transbordos = DB::table("logistica_transito_transbordos AS t")
          ->join("logistica_transito_transbordo_unidades AS tu", "t.id", "=", "tu.transbordo_id")
          ->join("logistica_transito_unidades AS u", "tu.transito_unidad_id", "=", "u.id")
          ->where("u.transito_main", $salidaTransito->id_transito)
          ->select("t.*")
          ->distinct()
          ->get();

          foreach ($transbordos as $transbordo) {
            $articulosTransbordo = DB::table("logistica_transito_transbordo_articulos AS art")
            ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
            ->where("art.transbordo_id", $transbordo->id)
            ->select(
              "det.token_detcompra",
              "art.articulo_descripcion AS articulo",
              "art.cantidad_llego",
              "art.cantidad_disponible AS cantidad_pendiente_transito",
              "art.unidad_medida",
            )
            ->get();
            
            // ─── 2. RELACIÓN HACIA ATRÁS (Unidades Entrantes / Destino = Este Transbordo) ───
            // Buscamos las unidades que apuntan a este transbordo como su "punto anterior" mediante la tabla de unión
            $unidadesEntrantesRaw = DB::table("logistica_transito_unidades AS u")
            ->join("logistica_transito_transbordo_unidades AS trb_uni", "u.id", "=", "trb_uni.transito_unidad_id")
            ->where("trb_uni.transbordo_id", $transbordo->id)
            // Si no usan tabla unión para el inicio, también puedes buscar por coincidencia de dirección destino si aplica
            ->select("u.*")
            ->get();

            // Formateamos las unidades entrantes
            $unidadesEntrantes = [];
            foreach ($unidadesEntrantesRaw as $uEntrante) {
              $unidadesEntrantes[] = $this->formatearDataUnidad($uEntrante, $JwtAuth);
            }

            // ─── 3. RELACIÓN HACIA ADELANTE (Unidades Salientes / Origen = Este Transbordo) ───
            // Buscamos las unidades que nacen de este punto de transbordo (unidades posteriores)
            $unidadesSalientesRaw = DB::table("logistica_transito_unidades AS u")
            ->where('u.punto_transbordo_salida', $transbordo->id)
            //->join("logistica_transito_unidades AS u", "union.unidad_nuevo_punto", "=", "u.id")
            //->whereIn("union.unidad_punto_anterior", function($query) use ($transbordo) {
            //  $query->select('transito_unidad_id')
            //    ->from('logistica_transito_transbordo_unidades')
            //    ->where('punto_transbordo_salida', $transbordo->id);
            //})
            ->select("u.*")
            ->get();

            // Formateamos las unidades salientes
            $unidadesSalientes = [];
            foreach ($unidadesSalientesRaw as $uSaliente) {
              $unidadesSalientes[] = $this->formatearDataUnidad($uSaliente, $JwtAuth);
            }

            // ─── 4. ARMAMOS EL NODO ENCONTRADO ───
            $secuenciaLogistica[] = [
              "tipo_nodo"                  => "PUNTO DE TRANSBORDO",
              "abrev_nodo"                 => "PT",
              "id_transbordo"              => $transbordo->id,
              "folio_seguimiento_timeline" => 'ESCALA-'.$JwtAuth->generarFolio($transbordo->folio_transito_transbordo),
              "lugar"                      => $JwtAuth->desencriptarDireccion($transbordo->lugar_transbordo),
              "autorizado"                 => (bool)$transbordo->arribo_autorizado,
              "fecha_arribo"               => $transbordo->fecha_arribo_punto ? $JwtAuth->mostrarUnixAFechaMexico($transbordo->fecha_arribo_punto) : '',
              "observaciones"              => $transbordo->observaciones_arribo ? $JwtAuth->desencriptar($transbordo->observaciones_arribo) : '',
              "articulos_transbordo"       => $articulosTransbordo,
              
              // Aquí quedan tus relaciones semánticas requeridas:
              "unidades_entrantes_atras"   => $unidadesEntrantes, 
              "unidades_salientes_adelante"=> $unidadesSalientes
            ];
          }

          //$a = 1;
          //while ($a <= 10) {
          //  $infoLogisticaTransito[] = [
          //    "capacidad_actual" => "$a litros"
          //  ];
          //  $a++;
          //}

          $dataMensaje = array(
            'code' => 200,
            'status' => 'success',
            'camino_recorrido' => $secuenciaLogistica
          );
        }
        
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //logísticas para registrar
  private function eachListaComprasGeneral($listaCompras,$JwtAuth){
    $arrayCompras = array();
    $idCompra = $listaCompras->pluck('token_compras')->filter()->unique()->toArray();
    $idProveedor = $listaCompras->pluck('proveedor')->filter()->unique()->toArray();
    
    $compraProveedorMap = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
    ->whereIn("catprov.id",$idProveedor)
    ->select(
      'catprov.id AS id_catalogo',
      'catprov.token_cat_proveedores',
      'catprov.folio','catprov.post_folio',
      'people.nombre_extendido',
      'people.nombre_com'
    )
    ->get()->keyBy('id_catalogo');
    
    $transitoSinDateLlegadaMap = DB::table("logistica_transito_main AS l_comp")
    ->join("logistica_transito_compras_relacionada AS logBuy", "l_comp.id", "=", "logBuy.transito_main")
    ->join("eegr_compras AS buy", "logBuy.compra_relacionada", "=", "buy.id")
    ->whereIn("buy.token_compras", $idCompra)
    ->whereNull("l_comp.arribo_final_fecha_real") // Filtramos solo los pendientes
    ->select('buy.token_compras AS id_compras', 'l_comp.id')
    ->get() // 1. Traemos la lista de pendientes a PHP
    ->groupBy('id_compras') // 2. Los agrupamos por compra creando el Map
    ->map(function ($puntos) {
      return $puntos->count(); // 3. ¡AQUÍ usamos el count() para contar cada grupo!
    });
    
    $transitoLlegadaSinAuthMap = DB::table("logistica_transito_main AS l_comp")
    ->join("logistica_transito_compras_relacionada AS logBuy", "l_comp.id", "=", "logBuy.transito_main")
    ->join("eegr_compras AS buy", "logBuy.compra_relacionada", "=", "buy.id")
    ->whereIn("buy.token_compras", $idCompra)
    ->whereNotNull("l_comp.arribo_final_fecha_real")
    ->where("l_comp.arribo_final_autorizado",FALSE)
    ->select('buy.token_compras AS id_compras', 'l_comp.id')
    ->get() // 1. Traemos la lista de pendientes a PHP
    ->groupBy('id_compras') // 2. Los agrupamos por compra creando el Map
    ->map(function ($puntos) {
      return $puntos->count(); // 3. ¡AQUÍ usamos el count() para contar cada grupo!
    });

    $detailsProductosMap = DB::table("eegr_compras AS buy")
    ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
    ->whereNull('detBuy.servicio')
    ->whereNull('detBuy.activo_fijo')
    ->whereNull('detBuy.activo_intangible')
    ->whereIn('buy.token_compras',$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*'
    )
    ->get()->groupBy('id_compras');

    //$allDetailIds = $detailsProductosMap->collapse()->pluck('id_det_compras')->unique()->toArray();
    
    $detailsActivosFijosMap = DB::table("eegr_compras AS buy")
    ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
    ->whereNotNull('detBuy.activo_fijo')
    ->whereIn('buy.token_compras',$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*'
    )
    ->get()->groupBy('id_compras');

    //$allDetailIds = $detailsActivosFijosMap->collapse()->pluck('id_det_compras')->unique()->toArray();
    $allDetailIds = $detailsProductosMap
    ->concat($detailsActivosFijosMap)
    ->collapse()
    ->pluck('id_det_compras')
    ->unique()
    ->toArray();

    $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->whereIn("art.articulo_detcompra", $allDetailIds)
    ->select(
      'art.articulo_detcompra AS id_det_compras',
      'l_comp.estado_alcanzado',
      'art.cantidad_asignada'
    )
    ->get()
    ->groupBy('id_det_compras');

    $transitoEntregadosMap = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->where("l_comp.estado_alcanzado", "entregado")
    ->where("l_comp.arribo_final_autorizado", TRUE)
    ->whereIn("art.articulo_detcompra", $allDetailIds)
    ->select(
      'art.articulo_detcompra AS id_det_compras',
      'l_comp.estado_alcanzado',
      'art.cantidad_asignada'
    )
    ->get()
    ->groupBy('id_det_compras');

    $mapDirSalidaProveedor = DB::table("eegr_compras AS buy")
    ->join("teci_direcciones AS ubica", "buy.direccion_salida_prov", "ubica.id")
    ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
    ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
    ->whereIn("buy.token_compras",$idCompra)
    ->whereIn("catprov.token_cat_proveedores",$compraProveedorMap->pluck('token_cat_proveedores')->unique())
    ->select(
      'buy.token_compras AS id_compras',
      'ubica.token_direccion','ubica.pais_code','ubica.colonia_edit','ubica.c_postal_edit',
      'ubica.municipio_edit','ubica.estado_edit','ubica.cod_postalext',
      'catprov.token_cat_proveedores AS token_catalogo',
    )
    ->get()
    ->groupBy(function ($item) {
      return $item->id_compras.'_'.$item->token_catalogo;
    });
    
    $mapDirOurEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
    ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
    ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
    ->where("estab.status_establecimiento",TRUE)
    ->whereIn("buy.token_compras",$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento'
    )
    ->get()->groupBy('id_compras');
  
    foreach ($listaCompras as $vBuy) {
      //da_te_default_timezone_set('UTC');
      $queryBuyProv = $compraProveedorMap->get($vBuy->proveedor);
      $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
      $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
      $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
      $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';
    
      $semaforo_espera = 'text-gray-500'; 
      $semaforo_transito = 'text-gray-500';
      $semaforo_entregados = 'text-gray-500';
      $semaforo_recibidos = 'text-gray-500';

      $total_articulos_comprados = 0;
      $total_articulos_en_espera = 0;
      $total_articulos_en_transito = 0;
      $total_articulos_entregados = 0;
      $total_articulos_recibidos = 0;
      $avance_entrega = 0;
      $avance_recepcion = 0;

      $transitos_sin_fecha_llegada = $transitoSinDateLlegadaMap->get($vBuy->token_compras) ?? 0; 
      $transitos_llegada_sin_auth = $transitoLlegadaSinAuthMap->get($vBuy->token_compras) ?? 0;
    
      $queryDetBuyProd = $detailsProductosMap->get($vBuy->token_compras) ?? collect([]);
      foreach ($queryDetBuyProd as $vDet) {
        $total_articulos_comprados += $vDet->cantidad;
        $movimientos = $transitoEstadosMap->get($vDet->id_det_compras) ?? collect([]);
        // Calculamos salidas vs entregas usando las colecciones en memoria
        $salieron = $movimientos->where('estado_alcanzado', 'recolectado')->sum('cantidad_asignada');
        $total_articulos_en_espera = $vDet->cantidad - $salieron;


        $mov_entregados = $transitoEntregadosMap->get($vDet->id_det_compras) ?? collect([]);
        $entregados = $mov_entregados->sum('cantidad_asignada'); // Cambia 'entregado' por tu estado final
        
        // Acumulamos para el nodo de la compra
        $total_articulos_en_transito += ($salieron - $entregados); // Lo que salió pero no ha llegado
        $total_articulos_entregados += $entregados;

        $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
        ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
        ->where([
          "detBuy.token_detcompra" => $vDet->token_detcompra,
          "rec.producto" => $vDet->producto
        ])
        ->select(
          DB::raw("SUM(rec.cantidad_recibida) as cantidad_recibida")
        )
        ->groupBy('rec.detalle_compra')
        ->first();
        $total_articulos_recibidos += $queryRecepcionPRD ? $queryRecepcionPRD->cantidad_recibida : 0;
      }
    
      $queryDetBuyActFijo = $detailsActivosFijosMap->get($vBuy->token_compras) ?? collect([]);
      //var_dump($queryDetBuyActFijo);
      foreach ($queryDetBuyActFijo as $vActDet) {
        $total_articulos_comprados += $vActDet->cantidad;
        $movimientos = $transitoEstadosMap->get($vActDet->id_det_compras) ?? collect([]);
        
        // Calculamos salidas vs entregas usando las colecciones en memoria
        $salieron = $movimientos->where('estado_alcanzado', 'recolectado')->sum('cantidad_asignada');
        $total_articulos_en_espera = $vActDet->cantidad - $salieron;
        $entregados = $movimientos->where('estado_alcanzado', 'entregado')->sum('cantidad_asignada'); // Cambia 'entregado' por tu estado final
        
        // Acumulamos para el nodo de la compra
        $total_articulos_en_transito += ($salieron - $entregados); // Lo que salió pero no ha llegado
        $total_articulos_entregados += $entregados;

        $queryRecepcionPRD = DB::table("eegr_compras_recepcion AS rec")
        ->join("eegr_compras_detalle AS detBuy", "rec.detalle_compra","=","detBuy.id")
        ->where([
          "detBuy.token_detcompra" => $vActDet->token_detcompra,
          "rec.activo_fijo" => $vActDet->activo_fijo
        ])
        ->select(
          DB::raw("SUM(rec.cantidad_recibida) as cantidad_recibida")
        )
        ->groupBy('rec.detalle_compra')
        ->first();
        $total_articulos_recibidos += $queryRecepcionPRD ? $queryRecepcionPRD->cantidad_recibida : 0;
      }
          
      if ($total_articulos_comprados > 0) {
        // 1. Semáforo para artículos en ESPERA
        if ($total_articulos_en_espera == $total_articulos_comprados) {
          $semaforo_espera = 'bg-red-100 text-red-700 font-bold'; // Rojo: Todos en espera
        } elseif ($total_articulos_en_espera > 0) {
          $semaforo_espera = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Una parte en espera
        } else {
          $semaforo_espera = 'bg-green-100 text-green-700'; // Verde: Ya nada está en espera
        }

        // 2. Semáforo para artículos en TRÁNSITO
        if ($total_articulos_en_transito > 0) {
          $semaforo_transito = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Hay mercancía viajando
        } else {
          $semaforo_transito = 'text-gray-400'; // Neutral: No hay nada en ruta activa
        }

        // 3. Semáforo para artículos ENTREGADOS
        if ($total_articulos_entregados == $total_articulos_comprados) {
          $semaforo_entregados = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
        } elseif ($total_articulos_entregados > 0) {
          $semaforo_entregados = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
        } else {
          $semaforo_entregados = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
        }

        // 3. Semáforo para artículos RECIBIDOS
        if ($total_articulos_recibidos == $total_articulos_comprados) {
          $semaforo_recibidos = 'bg-green-100 text-green-700 font-bold'; // Verde: Todo completado
        } elseif ($total_articulos_recibidos > 0) {
          $semaforo_recibidos = 'bg-yellow-100 text-yellow-700 font-bold'; // Amarillo: Entregas parciales
        } else {
          $semaforo_recibidos = 'bg-red-100 text-red-700'; // Rojo: No se ha entregado nada aún
        }
      }

      if ($total_articulos_comprados > 0) {
        $avance_entrega = round(($total_articulos_entregados / $total_articulos_comprados) * 100);
        if ($avance_entrega > 100) {
          $avance_entrega = 100;
        }

        $avance_recepcion = round(($total_articulos_recibidos / $total_articulos_comprados) * 100);
        if ($avance_recepcion > 100) {
          $avance_recepcion = 100;
        }
      }

      //Punto de entrega o recepción
      $lugarSalidaTipo = "N/A";
      $lugar_salida_prov_token = "";
      $lugar_salida_prov_direccion = "";

      if (!is_null($vBuy->direccion_salida_prov)) {
        $lugarSalidaTipo = "proveedor";
        $keyKompraProv = $vBuy->token_compras.'_'.$proveedor_token;
        $listaDirProvEstab = $mapDirSalidaProveedor->get($keyKompraProv) ?? collect([]);
        foreach ($listaDirProvEstab as $vUbica) {
          $lugar_salida_prov_token = $vUbica->token_direccion;
          $lugar_salida_prov_direccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
            $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
        }
      }
      $fecha_tentativa_salida = !is_null($vBuy->fecha_tentativa_salida) ? date('Y-m-d H:i:s', $vBuy->fecha_tentativa_salida) : '';
      $fecha_real_salida = !is_null($vBuy->fecha_real_salida) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_real_salida) : '';
      
      $lugarRecepcionTipo = "N/A";
      $recepcion_estab_token = "";
      $recepcion_estab_direccion = "";
      if (!is_null($vBuy->recepcion_estab)) {
        $lugarRecepcionTipo = "Establecimiento";
        $listaDirOurEstab = $mapDirOurEstab->get($vBuy->token_compras) ?? collect([]);
        foreach ($listaDirOurEstab as $vEstab) {
          $recepcion_estab_token = $vEstab->token_establecimiento;
          $recepcion_estab_direccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
        }
      }
      $fecha_tentativa_recepcion = !is_null($vBuy->fecha_tentativa_recepcion) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_tentativa_recepcion) : '';
      $fecha_real_recepcion = !is_null($vBuy->fecha_real_recepcion) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_real_recepcion) : '';

      $arrayForeach = array(
        "token_compras" => $vBuy->token_compras,
        "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vBuy->fecha_contabilizacion),
        //proveedor
        "proveedor_token" => $proveedor_token,
        "proveedor_folio" => $proveedor_folio,
        "proveedor_nombre" => $proveedor_nombre,
        "proveedor_nombre_comercial" => $proveedor_nombre_comercial,

        "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
        "recibeFactura" => $vBuy->recibeFactura ? true : false,
        "articulos_en_espera" => "$total_articulos_en_espera / $total_articulos_comprados", 
        "habilita_reg_new_salidas" => (bool)($total_articulos_en_espera > 0),//$total_articulos_entregados < $total_articulos_comprados ? true : false,
        "articulos_en_transito" => "$total_articulos_en_transito / $total_articulos_comprados", 
        "habiltar_continua_rutas" => (bool)($total_articulos_entregados < $total_articulos_comprados),//(bool)($total_articulos_en_transito > 0),
        "articulos_entregados" => "$total_articulos_entregados / $total_articulos_comprados", 
        "transitos_sin_fecha_llegada" => $transitos_sin_fecha_llegada,
        "transitos_llegada_sin_auth" => $transitos_llegada_sin_auth,
        "articulos_recibidos" => "$total_articulos_recibidos / $total_articulos_comprados",
        
        "clase_espera" => $semaforo_espera,
        "clase_transito" => $semaforo_transito,
        "clase_entregados" => $semaforo_entregados,
        "avance_entrega" => $avance_entrega,
        "clase_recibidos" => $semaforo_recibidos,
        "avance_recepcion" => $avance_recepcion,

        "lugarRecepcionTipo" => $lugarRecepcionTipo,
        "lugarSalidaTipo" => $lugarSalidaTipo,
        "lugar_salida_prov_token" => $lugar_salida_prov_token,
        "lugar_salida_prov_direccion" => $lugar_salida_prov_direccion,
        "fecha_tentativa_salida" => $fecha_tentativa_salida,
        "fecha_real_salida" => $fecha_real_salida,

        "recepcion_estab_token" => $recepcion_estab_token,
        "recepcion_estab_direccion" => $recepcion_estab_direccion,
        "fecha_tentativa_recepcion" => $fecha_tentativa_recepcion,
        "fecha_real_recepcion" => $fecha_real_recepcion
      );
      $arrayCompras[] = $arrayForeach;
    }

    return $arrayCompras;
  }

  public function listaLogisticaCompras(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }
      
      $queryCompras = DB::table("eegr_compras AS buy")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "buy.status_compra" => TRUE, 
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])
      ->whereNotNull(['buy.fecha_tentativa_salida','buy.recepcion_estab','buy.fecha_tentativa_recepcion'])
      ->whereIn('buy.id', function ($query) {
        $query->select('numero_compra')->from('eegr_compras_detalle')
        ->whereNotNull('producto')
        ->orWhereNotNull('activo_fijo');
      })
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("buy.fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->select("buy.status_cancelacion AS cancel_buy","buy.*","emp.*")
      ->orderBy("buy.id","DESC")
      ->get();

      if ($queryCompras->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron compras registradas'
        );
      } else {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'compras' => $this->eachListaComprasGeneral($queryCompras,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function eachCompraDetail($listaCompras,$JwtAuth){
    $arrayCompras = array();
    $idCompra = $listaCompras->pluck('token_compras')->filter()->unique()->toArray();
    $idProveedor = $listaCompras->pluck('proveedor')->filter()->unique()->toArray();
    
    $compraProveedorMap = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
    ->whereIn("catprov.id",$idProveedor)
    ->select(
      'catprov.id AS id_catalogo',
      'catprov.token_cat_proveedores',
      'catprov.folio','catprov.post_folio',
      'people.nombre_extendido',
      'people.nombre_com'
    )
    ->get()->keyBy('id_catalogo');
    
    $partidasCompraProdMap = DB::table("eegr_compras AS buy")
    ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
    ->join("in_egr_catalogo_productos AS catprod", "detBuy.producto", "=", "catprod.id")
    ->whereNotNull('detBuy.producto')
    ->whereNull('detBuy.servicio')
    ->whereNull('detBuy.activo_fijo')
    ->whereNull('detBuy.activo_intangible')
    ->whereIn('buy.token_compras',$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*',
      'catprod.token_cat_productos',
      'catprod.folio_sistema AS folio_prod',
      'catprod.post_folio',
      'catprod.producto AS prod_name',
      'catprod.codigo_sku',
      'catprod.codigo_gtin',
      'catprod.codigo_giai',
      'catprod.tipo_llave_gs1'
    )
    ->get()->groupBy('id_compras');

    $partidasCompraActivoFijoMap = DB::table("eegr_compras AS buy")
    ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
    ->join("in_egr_catalogo_productos AS catprod", "detBuy.producto", "=", "catprod.id")
    ->whereNotNull('detBuy.activo_fijo')
    ->whereNull('detBuy.servicio')
    ->whereNull('detBuy.activo_intangible')
    ->whereIn('buy.token_compras',$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'detBuy.id AS id_det_compras',
      'detBuy.*',
      'catprod.token_cat_productos',
      'catprod.folio_sistema AS folio_prod',
      'catprod.post_folio',
      'catprod.producto AS prod_name',
      'catprod.codigo_sku',
      'catprod.codigo_gtin',
      'catprod.codigo_giai',
      'catprod.tipo_llave_gs1'
    )
    ->get()->groupBy('id_compras');

    $allDetailIds = $partidasCompraProdMap
    ->concat($partidasCompraActivoFijoMap)
    ->collapse()
    ->pluck('id_det_compras')
    ->unique()
    ->toArray();

    $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->whereIn("art.articulo_detcompra", $allDetailIds)
    ->select(
      'art.articulo_detcompra AS id_det_compras',
      'l_comp.estado_alcanzado',
      'art.cantidad_asignada'
    )
    ->get()
    ->groupBy('id_det_compras');
    
    $mapDirSalidaProveedor = DB::table("eegr_compras AS buy")
    ->join("teci_direcciones AS ubica", "buy.direccion_salida_prov", "ubica.id")
    ->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
    ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
    ->whereIn("buy.token_compras",$idCompra)
    ->whereIn("catprov.token_cat_proveedores",$compraProveedorMap->pluck('token_cat_proveedores')->unique())
    ->select(
      'buy.token_compras AS id_compras',
      'ubica.token_direccion','ubica.pais_code','ubica.colonia_edit','ubica.c_postal_edit',
      'ubica.municipio_edit','ubica.estado_edit','ubica.cod_postalext',
      'catprov.token_cat_proveedores AS token_catalogo',
    )
    ->get()
    ->groupBy(function ($item) {
      return $item->id_compras.'_'.$item->token_catalogo;
    });
    
    $mapDirOurEstab = DB::table("in_egr_establecimientos_catalogo AS estab")
    ->join("eegr_compras AS buy", "estab.id", "buy.recepcion_estab")
    ->join('teci_direcciones AS dirAlm', 'estab.id', 'dirAlm.establecimiento')
    ->where("estab.status_establecimiento",TRUE)
    ->whereIn("buy.token_compras",$idCompra)
    ->select(
      'buy.token_compras AS id_compras',
      'estab.token_establecimiento','estab.folio_establecimiento','estab.post_folio','estab.alias_establecimiento'
    )
    ->get()->groupBy('id_compras');

    foreach ($listaCompras as $vBuy) {
      //da_te_default_timezone_set('UTC');
      $moneda_decimales = $JwtAuth->getMonedaAPI($vBuy->moneda);
    
      $queryBuyProv = $compraProveedorMap->get($vBuy->proveedor);
      $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
      $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
      $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
      $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';
    
      $total_art_recibidos = 0;
      $compra_partidas = array();
      $queryDetailProd = $partidasCompraProdMap->get($vBuy->token_compras) ?? collect([]);
      foreach ($queryDetailProd as $vPrdDet) {
        $articulo_folio = 'PROD-'.$JwtAuth->generarFolio($vPrdDet->folio_prod). (!is_null($vPrdDet->post_folio) ? '-'.$vPrdDet->post_folio : '');
        $articulo_nombre = $JwtAuth->desencriptar($vPrdDet->prod_name);

        $efecto_fiscal = match ($vPrdDet->efecto_fiscal) {
          'ded_inm_apl_mes' => "Deducciones Inmediata aplicables al mes",
          'ded_pers_anual'  => "Deducción Personal (Anual)",
          'ded_inversion'   => "Deducción de Inversión",
          'no_deducible'    => "No deducible",
          default           => "",
        };

        $reg_tipo_llave_gs1 = match ($vPrdDet->tipo_llave_gs1) {
          'GTIN-12' => "UPC (GTIN-12)",
          'GTIN-13' => "EAN (GTIN-13)",
          'GTIN-14' => "Caja (GTIN-14)",
          default   => "",
        };

        $movim_prod = $transitoEstadosMap->get($vPrdDet->id_det_compras) ?? collect([]);
        $prod_salieron = $movim_prod->where('estado_alcanzado', 'recolectado')->sum('cantidad_asignada');
        $productos_en_espera = $vPrdDet->cantidad - $prod_salieron;

        $rowPartida = array(
          "token_compras" => $vBuy->token_compras,
          "folio_compra" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
          "token_detcompra" => $vPrdDet->token_detcompra,
          "token_cat_productos" => $vPrdDet->token_cat_productos,
          "concepto_cfdi" => $vPrdDet->concepto_cfdi ? $JwtAuth->desencriptar($vPrdDet->concepto_cfdi) : '',
          "articulo_folio" => $articulo_folio,
          "articulo_nombre" => $articulo_nombre,
          "moneda_detalle_compra" => $vPrdDet->moneda_detalle_compra,
          "tipo_de_cambio_detalle_compra" => $vPrdDet->tipo_de_cambio_detalle_compra,
          "precio_unitario" => $vPrdDet->precio_unitario,
          "cantidad_comprada" => (int)$vPrdDet->cantidad,
          "cantidad_pendiente_transito" => (int)$productos_en_espera,
          "cantidad_transitar" => 0,
          "unidad_medida" => $vPrdDet->unidad_medida,
          "descuento" => $vPrdDet->descuento,
          "retenciones_total" => $vPrdDet->retenciones_total,
          "traslados_total" => $vPrdDet->traslados_total,
          "destino" => $vPrdDet->destino,
          "efecto_fiscal" => $efecto_fiscal,
          "serie" => $vPrdDet->serie ?? '',
          "lote" => $vPrdDet->lote ?? '',
          "pedimento_aduanal" => $vPrdDet->pedimento_aduanal ?? '',
          "reg_sku" => !is_null($vPrdDet->codigo_sku) ? $vPrdDet->codigo_sku : '',
          "new_sku" => "",
          "reg_tipo_llave_gs1" => $reg_tipo_llave_gs1,
          "reg_codigo_gs1" => !is_null($vPrdDet->codigo_gtin) ? $vPrdDet->codigo_gtin : '',
          "new_tipo_llave_gs1" => "",
          "new_codigo_gs1" => "",
        );
        $compra_partidas[] = $rowPartida;
        ++$total_art_recibidos;
      }

      $queryDetailFijoActivo = $partidasCompraActivoFijoMap->get($vBuy->token_compras) ?? collect([]);
      foreach ($queryDetailFijoActivo as $vActDet) {
        $articulo_folio = 'PROD-'.$JwtAuth->generarFolio($vActDet->folio_prod). (!is_null($vActDet->post_folio) ? '-'.$vActDet->post_folio : '');
        $articulo_nombre = $JwtAuth->desencriptar($vActDet->prod_name);

        $efecto_fiscal = match ($vPrdDet->efecto_fiscal) {
          'ded_inm_apl_mes' => "Deducciones Inmediata aplicables al mes",
          'ded_pers_anual'  => "Deducción Personal (Anual)",
          'ded_inversion'   => "Deducción de Inversión",
          'no_deducible'    => "No deducible",
          default           => "",
        };

        $movim_act = $transitoEstadosMap->get($vActDet->id_det_compras) ?? collect([]);
        $act_salieron = $movim_act->where('estado_alcanzado', 'recolectado')->sum('cantidad_asignada');
        $activos_en_espera = $vActDet->cantidad - $act_salieron;

        $rowPartida = array(
          "compra_relacionada_token" => $vBuy->token_compras,
          "compra_relacionada_folio" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
          "token_detcompra" => $vActDet->token_detcompra,
          "token_cat_productos" => $vActDet->token_cat_productos,
          "concepto_cfdi" => $vActDet->concepto_cfdi ? $JwtAuth->desencriptar($vActDet->concepto_cfdi) : '',
          "articulo_folio" => $articulo_folio,
          "articulo_nombre" => $articulo_nombre,
          "moneda_detalle_compra" => $vActDet->moneda_detalle_compra,
          "tipo_de_cambio_detalle_compra" => $vActDet->tipo_de_cambio_detalle_compra,
          "precio_unitario" => $vActDet->precio_unitario,
          "cantidad_comprada" => (int)$vActDet->cantidad,
          "cantidad_pendiente_transito" => (int)$activos_en_espera,
          "cantidad_transitar" => 0,
          "unidad_medida" => $vActDet->unidad_medida,
          "descuento" => $vActDet->descuento,
          "retenciones_total" => $vActDet->retenciones_total,
          "traslados_total" => $vActDet->traslados_total,
          "destino" => $vActDet->destino,
          "efecto_fiscal" => $efecto_fiscal,
          "serie" => $vActDet->serie ?? '',
          "lote" => $vActDet->lote ?? '',
          "pedimento_aduanal" => $vActDet->pedimento_aduanal ?? '',
          "reg_sku" => !is_null($vActDet->codigo_sku) ? $vActDet->codigo_sku : '',
          "new_sku" => "",
          "reg_tipo_llave_gs1" => !is_null($vActDet->tipo_llave_gs1) ? 'Activo Fijo (GIAI)' : '',
          "reg_codigo_gs1" => !is_null($vActDet->codigo_giai) ? $vActDet->codigo_giai : '',
          "new_tipo_llave_gs1" => "",
          "new_codigo_gs1" => "",
          "destino_entrega_final_partida" => "",
        );
        $compra_partidas[] = $rowPartida;
        ++$total_art_recibidos;
      }
          
      //Punto de entrega o recepción
      $lugarSalidaTipo = "N/A";
      $lugar_salida_prov_token = "";
      $lugar_salida_prov_direccion = "";

      if (!is_null($vBuy->direccion_salida_prov)) {
        $lugarSalidaTipo = "proveedor";
        $keyKompraProv = $vBuy->token_compras.'_'.$proveedor_token;
        $listaDirProvEstab = $mapDirSalidaProveedor->get($keyKompraProv) ?? collect([]);
        foreach ($listaDirProvEstab as $vUbica) {
          $lugar_salida_prov_token = $vUbica->token_direccion;
          $lugar_salida_prov_direccion = $vUbica->pais_code == "MEX" ? "Colonia ".$JwtAuth->desencriptar($vUbica->colonia_edit).", CP: ".$vUbica->c_postal_edit.", ".
            $JwtAuth->desencriptar($vUbica->municipio_edit).", ".$JwtAuth->desencriptar($vUbica->estado_edit) : $JwtAuth->desencriptar($vUbica->cod_postalext);
        }
      }
      $fecha_tentativa_salida = !is_null($vBuy->fecha_tentativa_salida) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_tentativa_salida) : '';
      $fecha_real_salida = !is_null($vBuy->fecha_real_salida) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_real_salida) : '';
      
      $lugarRecepcionTipo = "N/A";
      $recepcion_estab_token = "";
      $recepcion_estab_direccion = "";
      if (!is_null($vBuy->recepcion_estab)) {
        $lugarRecepcionTipo = "Establecimiento";
        $listaDirOurEstab = $mapDirOurEstab->get($vBuy->token_compras) ?? collect([]);
        foreach ($listaDirOurEstab as $vEstab) {
          $recepcion_estab_token = $vEstab->token_establecimiento;
          $recepcion_estab_direccion = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '')." ".$JwtAuth->desencriptar($vEstab->alias_establecimiento);
        }
      }
      $fecha_tentativa_recepcion = !is_null($vBuy->fecha_tentativa_recepcion) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_tentativa_recepcion) : '';
      $fecha_real_recepcion = !is_null($vBuy->fecha_real_recepcion) ? gmdate('Y-m-d H:i:s', $vBuy->fecha_real_recepcion) : '';

      $arrayForeach = array(
        "compra_relacionada_token" => $vBuy->token_compras,
        "compra_relacionada_folio" => "COMP-".$JwtAuth->generarFolio($vBuy->folio_compra).($vBuy->post_folio != NULL ? '-'.$vBuy->post_folio : ''),
        "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vBuy->fecha_contabilizacion),
        //proveedor
        "proveedor_token" => $proveedor_token,
        "proveedor_folio" => $proveedor_folio,
        "proveedor_nombre" => $proveedor_nombre,
        "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
        "compra_a_credito" => !empty($vBuy->compra_a_credito) ? ($vBuy->compra_a_credito == "cred" ? "Crédito" : "contado") : "",
        "fecha_vencimiento" => gmdate('Y-m-d H:i:s', $vBuy->fecha_vencimiento),
        "compra_moneda" => $vBuy->moneda,//*
        "compra_moneda_decimales" => $moneda_decimales,//*
        "aplica_recepcion_facturas" => $vBuy->aplica_recepcion_facturas,
        "recibeFactura" => $vBuy->recibeFactura ? true : false,
        "compra_partidas" => $compra_partidas,
        "articulos_recibidos" => $total_art_recibidos,
        //"total_articulos" => count($queryDetailsTotal),
        //"articulos_recibidos_comparativa" => "$total_art_recibidos / ".count($queryDetailsTotal),
        "lugarRecepcionTipo" => $lugarRecepcionTipo,
        "lugarSalidaTipo" => $lugarSalidaTipo,
        "lugar_salida_prov_token" => $lugar_salida_prov_token,
        "lugar_salida_prov_direccion" => $lugar_salida_prov_direccion,
        "fecha_tentativa_salida" => $fecha_tentativa_salida,
        "fecha_real_salida" => $fecha_real_salida,

        "recepcion_estab_token" => $recepcion_estab_token,
        "recepcion_estab_direccion" => $recepcion_estab_direccion,
        "fecha_tentativa_recepcion" => $fecha_tentativa_recepcion,
        "fecha_real_recepcion" => $fecha_real_recepcion
      );
      $arrayCompras[] = $arrayForeach;
    }

    return $arrayCompras;
  }

  public function logisticaCompraSeleccionada(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'compras_vinculadas' => 'required|array'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $compras_vinculadas = $request->input('compras_vinculadas');
      
      $tokensPlanos = is_array(end($compras_vinculadas)) ? collect($compras_vinculadas)->pluck('token_compras')->all() : $compras_vinculadas;
      
      $queryCompras = DB::table("eegr_compras AS buy")
      ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "buy.status_compra" => TRUE,
        //"buy.token_compras" => $compras_vinculadas, 
        "emp.empresa_token" => $empresa, 
        "users.usuario_token" => $usuario
      ])
      ->whereIn("buy.token_compras", $tokensPlanos)
      ->select("buy.status_cancelacion AS cancel_buy","buy.*","emp.*")
      ->orderBy("buy.id","DESC")
      ->get();

      if ($queryCompras->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron compras registradas'
        );
      } else {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'compra' => $this->eachCompraDetail($queryCompras,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function guardarTransitoCompra(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'compras_vinculadas' => 'required|json',//'required|array',
      'estado_alcanzado' => 'required|string',
      'fecha_real_salida' => 'required|string',
      'tentativaLlegadaLugarDestino' => 'required|string',
      'observaciones' => 'required|string',
      'transportes' => 'required|json',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea registrar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //echo bin2hex(random_bytes(32));
      $compras_vinculadas = json_decode($request->input('compras_vinculadas'), true);//$request->input('compras_vinculadas');
      $estado_alcanzado = $request->input('estado_alcanzado');
      $fecha_real_salida = $request->input('fecha_real_salida');
      $tentativaLlegadaLugarDestino = $request->input('tentativaLlegadaLugarDestino');
      $observaciones = $request->input('observaciones');
      $transportes = json_decode($request->input('transportes'), true);

      $OKCompraRelacionada = isset($compras_vinculadas) && !empty($compras_vinculadas) && is_array($compras_vinculadas);
      $OKEstadoAlcanzado = isset($estado_alcanzado) && !empty($estado_alcanzado) && preg_match($JwtAuth->filtroAlfaNumerico(),$estado_alcanzado);
      $OKFechaRealSalida = isset($fecha_real_salida) && !empty($fecha_real_salida) && preg_match($JwtAuth->filtroFecha(),$fecha_real_salida);
      $OKTentativaLlegadaLugarDestino = isset($tentativaLlegadaLugarDestino) && !empty($tentativaLlegadaLugarDestino) && preg_match($JwtAuth->filtroFecha(),$tentativaLlegadaLugarDestino);
      $OKObservaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);
      $OKTransportes = isset($transportes) && !empty($transportes) && is_array($transportes);
      
      foreach ($transportes as $index => $transp) {
        $subValidator = \Validator::make($transp, [
          'tipo_transporte'                 => 'required|string',
          'operador_nombre'                 => 'required|string',
          'operador_telefono'               => 'required|string',
          'tentativa_llegada_destino'       => 'required|string',
          'identificador_principal'         => 'required|string',
          'identificador_secundario'        => 'required|string', // Estrictamente obligatorio según tus reglas
          'direccion_origen'                => 'required|string',
          'direccion_destino_especifica'    => 'required|string',
          'carta_porte_relacionada'         => 'nullable|string',
          'articulos'                       => 'required|array|min:1',
          'articulos.*.articulo_nombre'     => 'required|string',
          'articulos.*.cantidad_transitar'  => 'required|integer',
          'articulos.*.sku'                 => 'nullable|string',
        ]);

        if ($subValidator->fails()) {
          return response()->json([
            'status'  => 'error',
            'message' => "Error de validación en la Unidad " . ($index + 1),
            'errors'  => $subValidator->errors()
          ], 422);
        }
      }

      if ($OKCompraRelacionada && $OKEstadoAlcanzado && $OKFechaRealSalida && $OKTentativaLlegadaLugarDestino && $OKObservaciones && $OKTransportes) {
        $tokensPlanos = is_array(end($compras_vinculadas)) ? collect($compras_vinculadas)->pluck('token_compras')->all() : $compras_vinculadas;
        
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
        ->first();

        if ($vEmp) {
          DB::beginTransaction();
          try {

            $tokensConTransito = DB::table("logistica_transito_main AS logMain")
            ->join("logistica_transito_compras_relacionada AS logBuy", "logMain.id", "=", "logBuy.transito_main")
            ->join("eegr_compras AS buy", "logBuy.compra_relacionada", "=", "buy.id")
            ->whereIn("buy.token_compras", $tokensPlanos)
            ->pluck("buy.token_compras") // Obtenemos una lista plana de los tokens ocupados
            ->toArray();
            
            // 2. Filtramos nuestro arreglo original para quedarnos SOLO con los tokens libres
            // (Es decir, los que están en $tokensPlanos pero NO en $tokensConTransito)
            $tokensLibres = array_diff($tokensPlanos, $tokensConTransito);

            if (!empty($tokensLibres)) {
              DB::table("eegr_compras")->whereIn("token_compras", $tokensLibres)
              ->update(["fecha_real_salida" => $JwtAuth->convierteFechaEpoc($fecha_real_salida)]);
            }
            
            $maxFolioTranLogis = DB::table('logistica_transito_main')->where('empresa_vinculada', $vEmp->id)->lockForUpdate()->max('folio_seguimiento_transito');
            $folioTranLogis = $maxFolioTranLogis ? $maxFolioTranLogis + 1 : 1;

            $folio_seguimiento_transito = "TRANSITO-".$JwtAuth->generarFolio($folioTranLogis);
            $logistica_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_real_salida);
            $nombreDocs = $logistica_fecha_contabilizacion."-".$folio_seguimiento_transito;

            $token_seguimiento_transito = Str::uuid()->toString();
            $transitoMain = LogisticaTransitoMain::create([
              'token_seguimiento_transito'       => $token_seguimiento_transito,
              'folio_seguimiento_transito'       => $folioTranLogis,
              'logistica_fecha_contabilizacion'  => $logistica_fecha_contabilizacion,
              //'compra_relacionada'             => $compraData->id,
              'estado_alcanzado'                 => $estado_alcanzado,
              'fecha_real_salida'                => $JwtAuth->convierteFechaEpoc($fecha_real_salida),
              'observaciones_salida'             => $JwtAuth->encriptar($observaciones),
              'arribo_final_fecha_tentativa'     => $JwtAuth->convierteFechaEpoc($tentativaLlegadaLugarDestino),
              'empresa_vinculada'                => $vEmp->id,
              'usuario_registra'                 => $vEmp->userr,
            ]);

            $obtenTransitoMain = $transitoMain->id;
            foreach ($compras_vinculadas as $buyVinc) {
              $buy_id = DB::table("eegr_compras")->where("token_compras",$buyVinc['token_compras'])->value("id");
              $unidad = $transitoMain->compras()->create([
                'compra_relacionada' => $buy_id,
              ]);
            }

            $direccionesAgrupadas = [];
            $folio_transito_unidad = 1;
            foreach ($transportes as $transpData) {
              $direccion_destino_limpia = trim($transpData['direccion_destino_especifica']);
              $direccion_destino = $JwtAuth->encriptar($direccion_destino_limpia);
              $cfdi_c_porte = $transpData['carta_porte_relacionada'] != '' ? DB::table("comprobante_carta_porte")->where("id_ccp",$transpData['carta_porte_relacionada'])->value("comprobante_fiscal") : NULL;

              $unidad = $transitoMain->unidades()->create([
                'token_seguimiento_unidad'       => Str::uuid()->toString(),
                'folio_seguimiento_unidad'       => $folio_transito_unidad,
                //'transito_main', carta_porte_relacionada
                'tipo_transporte'                => $transpData['tipo_transporte'], // 'terrestre', 'maritimo', 'aereo',
                'operador_nombre'                => $JwtAuth->encriptar($transpData['operador_nombre']),
                'operador_telefono'              => $JwtAuth->encriptar($transpData['operador_telefono']),
                'identificador_principal'        => $JwtAuth->encriptar($transpData['identificador_principal']), //-- Placas, Contenedor o Guía AWB
                'identificador_secundario'       => $JwtAuth->encriptar($transpData['identificador_secundario']),     //-- Remolque, Booking o Vuelo
                'permiso_autorizacion'           => !is_null($transpData['permiso_autorizacion']) ? $JwtAuth->encriptar($transpData['permiso_autorizacion']) : NULL,         //-- Permiso SCT / SICT
                'direccion_origen'               => $JwtAuth->encriptar($transpData['direccion_origen']),
                'direccion_destino_especifica'   => $direccion_destino,
                'cfdi_relacionado'               => $cfdi_c_porte,
                'estado_consumo'                 => 'disponible',
                'unidad_fecha_salida'            => $JwtAuth->convierteFechaEpoc($fecha_real_salida),
                'unidad_fecha_tentativa_arribo'  => $JwtAuth->convierteFechaEpoc($transpData['tentativa_llegada_destino']),
                'punto_transbordo_salida'        => NULL,
              ]);
              
              if (!isset($direccionesAgrupadas[$direccion_destino_limpia])) {
                $direccionesAgrupadas[$direccion_destino_limpia] = [
                  'direccion'   => $direccion_destino_limpia,
                  'unidades_id' => [] // Arreglo dinámico que guardará los IDs
                ];
              }
              // Añadimos el ID de la unidad recién creada al grupo de esta dirección
              $direccionesAgrupadas[$direccion_destino_limpia]['unidades_id'][] = $unidad->id;

              foreach ($transpData['articulos'] as $artData) {
                $id_producto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$artData['token_cat_productos'])->value("id");
                $compra_relacionada = DB::table("eegr_compras")->where("token_compras",$artData['token_compras'])->value("id");
                $data_det_compra = DB::table("eegr_compras_detalle")
                ->where("token_detcompra",$artData['token_detcompra'])
                ->select('id AS id_det_compra','precio_unitario')
                ->first();
                //$precio_det_compra = DB::table("eegr_compras_detalle")->where("token_detcompra",$artData['token_detcompra'])->value("precio_unitario");

                $artGS1Code = DB::table("in_egr_catalogo_productos")
                ->where("id",$id_producto)
                ->whereNull('codigo_gtin')
                ->whereNull('codigo_giai')
                ->whereNull('tipo_llave_gs1')
                ->first(); 
                if ($artGS1Code && $artData['new_tipo_llave_gs1'] != '' && $artData['new_codigo_gs1'] != '') {
                  DB::table("in_egr_catalogo_productos")->where("id",$id_producto)
                  ->limit(1)->update([
                    "codigo_gtin" => $artData['new_tipo_llave_gs1'] != 'GIAI' ? $artData['new_codigo_gs1'] : NULL,
                    "codigo_giai" => $artData['new_tipo_llave_gs1'] == 'GIAI' ? $artData['new_codigo_gs1'] : NULL,
                    "tipo_llave_gs1" => $artData['new_tipo_llave_gs1']
                  ]);
                }
                
                $artSKU = DB::table("in_egr_catalogo_productos")
                ->where("id",$id_producto)
                ->whereNull('codigo_sku')
                ->first(); 
                if ($artSKU && $artData['new_sku'] != '') {
                  DB::table("in_egr_catalogo_productos")->where("id",$id_producto)
                  ->limit(1)->update(["codigo_sku" => $artData['new_sku']]);
                }

                $unidad->articulos()->create([
                  'articulo_detcompra'   => $data_det_compra->id_det_compra,
                  'articulo_descripcion' => $artData['articulo_nombre'],
                  'cantidad_asignada'    => $artData['cantidad_transitar'],
                  'unidad_medida'        => $artData['unidad_medida'] ?? null,
                ]);

                if ($estado_alcanzado == 'recolectado') {
                  $this->kardexService->transicionarKardexTransitoCompra(
                    $id_producto,
                    $artData['cantidad_transitar'],
                    $data_det_compra->precio_unitario,
                    'por_recibir',/*Status Origen*/
                    'en_transito_compra',/*Status Destino*/
                    'Salida de camión del proveedor en ruta a Cedis',
                    'LOGISTICA',
                    $compra_relacionada,
                    $data_det_compra->id_det_compra
                  );
                }
              }
              ++$folio_transito_unidad;
            }

            $transbordos_ligados = array_values($direccionesAgrupadas);

            foreach ($transbordos_ligados as $grupo) {
              // Obtenemos el folio que le toca a esta escala
              $maxFolioTransbordo = DB::table('logistica_transito_transbordos')->where('transito_main',$obtenTransitoMain)->lockForUpdate()->max('folio_transito_transbordo');
              $folioTransbordo = $maxFolioTransbordo ? $maxFolioTransbordo + 1 : 1;
              $token_transito_transbordo = Str::uuid()->toString();
              // Encriptamos la dirección usando el método determinista (o el que uses para transbordos)
              $direccion_destino_encrypt = $JwtAuth->encriptarDireccion($grupo['direccion']);
              $idTransbordo = DB::table("logistica_transito_transbordos")->insertGetId([
                "token_transito_transbordo" => $token_transito_transbordo,
                "folio_transito_transbordo" => $folioTransbordo,
                "transito_main"             => $obtenTransitoMain,
                "lugar_transbordo"          => $direccion_destino_encrypt,//$transpData['direccion_destino_especifica'],
                "arribo_autorizado"         => FALSE,
              ]);

              // Vinculamos todas las unidades que pertenecen a esta misma dirección
              foreach ($grupo['unidades_id'] as $unidadId) {
                DB::table("logistica_transito_transbordo_unidades")->insert([
                  "transbordo_id"      => $idTransbordo,
                  "transito_unidad_id" => $unidadId
                ]);
              }
            }
            
            $filepath = $vEmp->root_tkn . "/0002-cpp/logistica/transito/$nombreDocs";
            
            if ($request->hasFile('transito_anexos')) {
              $anexos = $request->file('transito_anexos');
            
              // 1. Rendimiento: Consultamos el folio una sola vez fuera del ciclo
              $conteoActual = DB::table("logistica_transito_documentos")->where('folio_modulo', 'LIKE', 'TRANSITO-ANEX%')->lockForUpdate()->count();
              $folioSiguiente = $conteoActual + 1;
              
              foreach ($anexos as $archivo) {
                if ($archivo && $archivo->isValid()) {
                  // 2. Definición de nombre original
                  $nombreOriginal = $archivo->getClientOriginalName();
                    
                  // Usamos el nombre original directamente ya que $filepath es único por compra
                  $nombreFisico = $nombreOriginal;
        
                  // 3. Guardado físico en el storage
                  $storagePath = "/public/root/" . $filepath;
                  $saveFile = Storage::putFileAs($storagePath, $archivo, $nombreFisico);
        
                  if (!$saveFile) {
                    throw new \Exception("Error al guardar el archivo físico: $nombreOriginal");
                  }
        
                  // 4. Preparar datos y generar Token
                  $folioModulo = "TRANSITO-ANEX" . $folioSiguiente;
                  $tokenDoc = $JwtAuth->encriptarToken($obtenTransitoMain, $nombreOriginal, $folioSiguiente);
        
                  // 5. Inserción en base de datos
                  $insertDoc = DB::table("logistica_transito_documentos")->insert([
                    "token_documento"     => $tokenDoc,
                    "fecha_carga"         => time(),
                    "modulo"              => "pagos",
                    "folio_modulo"        => $folioModulo,
                    "tipo_documento"      => "an",
                    "nombre_documento"    => $JwtAuth->encriptar($nombreOriginal),
                    "transito_main"       => $obtenTransitoMain,
                    "status_documento"    => true,
                  ]);
        
                  if (!$insertDoc) {
                    throw new \Exception("Error al registrar el anexo $nombreOriginal en la base de datos.");
                  }
      
                  // Incrementamos para el siguiente archivo
                  $folioSiguiente++;
                }
              }
            }

            DB::commit();
            $dataMensaje = array(
              'message' => 'El tránsito de la compra y sus unidades de carga han sido registrados exitosamente.',//.$folio_buy.($validate_insert_ord_pago ? ', revise ordenes de pago' : '')
              'code' => 200,
              'status' => 'success',
            );
          } catch (\Exception $e) {
            DB::rollBack();
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en el registro: '. $e->getMessage(),
              'line' => $e->getLine()
            );
          }
        }

      } else {
        $mensaje_error = '';
        if (!$OKCompraRelacionada) { $mensaje_error = 'Error en compra relacionada, verifique su información o comuniquese a soporte'; }
        if (!$OKEstadoAlcanzado) { $mensaje_error = 'Error en etapa del Traslado, verifique su información o comuniquese a soporte'; }
        if (!$OKFechaRealSalida) { $mensaje_error = 'Error en fecha de salida, verifique su información o comuniquese a soporte'; }
        if (!$OKTentativaLlegadaLugarDestino) { $mensaje_error = 'Error en fecha tentativa de arribo final, verifique su información o comuniquese a soporte'; }
        if (!$OKObservaciones) { $mensaje_error = 'Error en observaciones, verifique su información o comuniquese a soporte'; }
        if (!$OKTransportes) { $mensaje_error = 'Error en Unidades / Medios de Transporte Despachados, verifique su información o comuniquese a soporte'; }

        $dataMensaje = array('status' => 'error','code' => 400,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function cantidadTransitoConsumida($transito_anterior) {
    $cantidad_consumida = DB::table("logistica_transito_articulos AS art")
    ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
    ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
    ->where("l_comp.transito_anterior_id", $transito_anterior)
    ->select(
      //'art.articulo_detcompra AS id_det_compras',
      'art.transito_unidad_id AS id_det_compras',
      DB::raw("SUM(art.cantidad_asignada) as cantidad_total_disponible")
    )
    ->sum('art.cantidad_asignada');
    return $cantidad_consumida;
  }

  public function obtenerUbicacionSinEntregaByHito(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_compras' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_compras = $request->input('token_compras');

      try {
        // 1. Buscamos el ID real de la compra a través de su Token
        $compra = DB::table("eegr_compras")
        ->where("token_compras", $token_compras)
        ->select("id")
        ->first();

        if (!$compra) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontró la compra relacionada especificada.'
          );
        }

        //2. Buscamos el ÚLTIMO hito logístico registrado para esta compra.
        // Ordenamos por 'id' de la tabla padre para asegurar orden cronológico secuencial estricto.
        $queryHitosNoConsumidos = DB::table("logistica_transito_main AS hitoMain")
        ->where("hitoMain.compra_relacionada", $compra->id)
        ->where("hitoMain.estado_consumo", "<>", "consumido")
        ->select("hitoMain.id AS id_hito", "hitoMain.*")
        ->get();

        if ($queryHitosNoConsumidos->isEmpty()) {
          return response()->json([
            'status' => 'success',
            'message' => 'La compra no cuenta con tránsitos o unidades despachadas aún.',
            'data' => null
          ], 200);
        }

        $listaHitosNoConsumidos = array();
        foreach ($queryHitosNoConsumidos as $vHito) {
          # code...
          // 3. Obtener TODAS las unidades asociadas a ese último hito
          $unidadesQueViajan = DB::table("logistica_transito_unidades")
          ->where("transito_main", $vHito->id)
          ->orderBy("id", "DESC") // Trae el último ID insertado primero
          ->get();
  
          if ($unidadesQueViajan->isEmpty()) {
            return response()->json([
              'status' => 'success',
              'message' => 'Hito localizado, pero no cuenta con unidades asignadas.',
              'data' => null
            ], 200);
          }
  
          // 4. Mapear y desencriptar absolutamente TODOS los campos para el formulario
          $unidadesProcesadas = $unidadesQueViajan->map(function($unidad) use ($JwtAuth) {
            $articulosUnidad = DB::table("logistica_transito_articulos AS art")
              ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
              ->where("art.transito_unidad_id", $unidad->id)
              ->select(
                "det.token_detcompra",
                "art.articulo_descripcion AS articulo",
                "det.cantidad AS cantidad_comprada",
                "art.cantidad_asignada AS cantidad",
                "art.unidad_medida",
              )
              ->get();
      
            return [
              // Datos de control interno
              'id_unidad_anterior'           => $unidad->id,
              'tipo_transporte'              => $unidad->tipo_transporte,
              
              // Datos del Operador (Desencriptados)
              'operador_nombre'              => $JwtAuth->desencriptar($unidad->operador_nombre),
              'operador_telefono'            => $JwtAuth->desencriptar($unidad->operador_telefono),
              
              // Identificadores Logísticos (Desencriptados)
              'identificador_principal'      => $JwtAuth->desencriptar($unidad->identificador_principal), // Placas/Contenedor/AWB
              'identificador_secundario'     => $JwtAuth->desencriptar($unidad->identificador_secundario), // Remolque/Booking/Vuelo
              'permiso_autorizacion'         => !is_null($unidad->permiso_autorizacion) ? $JwtAuth->desencriptar($unidad->permiso_autorizacion) : '',
              
              // Direcciones de Ruta (Desencriptadas)
              'direccion_origen'             => $JwtAuth->desencriptar($unidad->direccion_origen),
              // 📍 Este destino se convierte en el Origen Sugerido para el nuevo tramo de esta unidad
              'direccion_destino_especifica' => $JwtAuth->desencriptar($unidad->direccion_destino_especifica), 
              
              // Listado completo de mercancía amparada
              'articulos'                    => $articulosUnidad,
              'articulos_seleccionados'      => [] // Inicializado vacío para el manejo de selección en PrimeNG
            ];
          });
          
          $unidadQueViajaID = DB::table("logistica_transito_unidades")
          ->where("transito_main", $vHito->id)
          ->pluck("id"); // Obtenemos solo los IDs de las unidades del tramo anterior
  
          $queryMercEnTransito = DB::table("logistica_transito_articulos AS art")
          /*->leftJoin*/->join("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
          /*->leftJoin*/->join("in_egr_catalogo_productos AS catprod", "det.producto", "=", "catprod.id")
          ->whereIn("art.transito_unidad_id", $unidadQueViajaID)
          ->select(
            "det.id AS id_det_compras",
            "art.transito_unidad_id",
            "det.token_detcompra",
            "art.articulo_descripcion AS articulo",
            "art.unidad_medida",
            "det.cantidad AS cantidad_original_comprada",
            "det.efecto_fiscal",
            "catprod.token_cat_productos",
            "catprod.folio_sistema AS folio_prod",
            "catprod.post_folio",
            "catprod.producto AS prod_name",
            "catprod.codigo_sku",
            "catprod.codigo_gtin",
            "catprod.codigo_giai",
            "catprod.tipo_llave_gs1",
            DB::raw("MAX(art.cantidad_asignada) as cantidad_en_transito")//DB::raw("SUM(art.cantidad_asignada) as cantidad_en_transito")
          )
          ->groupBy(
            "det.id",
            "art.transito_unidad_id",
            "det.token_detcompra", 
            "art.articulo_descripcion", 
            "art.unidad_medida", 
            "det.cantidad",
            "det.efecto_fiscal",
            "catprod.token_cat_productos",
            "catprod.folio_sistema",
            "catprod.post_folio",
            "catprod.producto",
            "catprod.codigo_sku",
            "catprod.codigo_gtin",
            "catprod.codigo_giai",
            "catprod.tipo_llave_gs1",
          )
          ->get();
          $mercanciaEnTransito = [];
          
          //$allDetailIds = $queryMercEnTransito->pluck('id_det_compras')->filter()->unique()->toArray();
          $allDetailIds = $queryMercEnTransito->pluck('transito_unidad_id')->filter()->unique()->toArray();
  
          $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
          ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
          ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
          ->where("l_comp.estado_alcanzado", "<>", "entregado")
          ->where("l_comp.arribo_final_autorizado", true)
          //->whereIn("art.articulo_detcompra", $allDetailIds)
          ->whereIn("art.transito_unidad_id", $allDetailIds)
          ->select(
            //'art.articulo_detcompra AS id_det_compras',
            'art.transito_unidad_id AS id_det_compras',
            DB::raw("SUM(art.cantidad_asignada) as cantidad_total_disponible")
          )
          ->groupBy('art.transito_unidad_id')//'art.articulo_detcompra')
          ->get()
          ->keyBy('id_det_compras');
  
          foreach ($queryMercEnTransito as $merc) {
            //echo $merc->transito_unidad_id;
            //$movim_prod = $transitoEstadosMap->get($merc->id_det_compras) ?? collect([]);
            //$prod_salieron = $movim_prod->where('estado_alcanzado', 'recolectado')->sum('cantidad_asignada');
            $registroEstado = $transitoEstadosMap->get($merc->transito_unidad_id);
            //$productos_en_espera = $prod_salieron;//$merc->cantidad_en_transito - $prod_salieron;
            $cantidad_disponible = $registroEstado ? (int)$registroEstado->cantidad_total_disponible : 0;
  
            //$det_compra = DB::table("eegr_compras_detalle")
            //->where("token_detcompra", $merc->token_detcompra)
            //->select("cantidad")
            //->first();
            
            $efecto_fiscal = "";
    
            switch ($merc->efecto_fiscal) {
              case 'ded_inm_apl_mes':
                $efecto_fiscal = "Deducciones Inmediata aplicables al mes";
                break;
              case 'ded_pers_anual':
                $efecto_fiscal = "Deducción Personal (Anual)";
                break;
              case 'ded_inversion':
                $efecto_fiscal = "Deducción de Inversión";
                break;
              case 'no_deducible':
                $efecto_fiscal = "No deducible";
                break;
              default:
                $efecto_fiscal = "sin efecto";
                break;
            }
    
            $reg_tipo_llave_gs1 = "";
            switch ($merc->tipo_llave_gs1) {
              case 'GTIN-12':
                $reg_tipo_llave_gs1 = "UPC (GTIN-12)";
                break;
              case 'GTIN-13':
                $reg_tipo_llave_gs1 = "EAN (GTIN-13)";
                break;
              case 'GTIN-14':
                $reg_tipo_llave_gs1 = "Caja (GTIN-14)";
                break;
              default:
                $reg_tipo_llave_gs1 = "";
                break;
            }
  
            $mercanciaEnTransito[] = [
              "token_detcompra" => $merc->token_detcompra,
              "token_cat_productos" => $merc->token_cat_productos,
              "articulo" => $merc->articulo,
              "cantidad_comprada" => (int)$merc->cantidad_original_comprada,
              "cantidad_pendiente_transito" => $cantidad_disponible,
              "cantidad" => $merc->cantidad_en_transito,
              "cantidad_transitar" => 0,
              "unidad_medida" => $merc->unidad_medida,
              "efecto_fiscal" => $efecto_fiscal,
              
              "reg_sku" => !is_null($merc->codigo_sku) ? $merc->codigo_sku : '',
              "new_sku" => "",
              "reg_tipo_llave_gs1" => $reg_tipo_llave_gs1,
              "reg_codigo_gs1" => !is_null($merc->codigo_gtin) ? $merc->codigo_gtin : '',
              "new_tipo_llave_gs1" => "",
              "new_codigo_gs1" => "",
            ];
          }

          $row_hito = array(
            "id_hito"                         => $vHito->id_hito,
            "etapa_anterior"                  => $vHito->estado_alcanzado,
            "observaciones_salida_anteriores" => $vHito->observaciones_salida ? $JwtAuth->desencriptar($vHito->observaciones_salida) : '',
            "observaciones_arribo_anteriores" => $vHito->observaciones_arribo ? $JwtAuth->desencriptar($vHito->observaciones_arribo) : '',
            "unidades_anteriores"             => $unidadesProcesadas,
            "mercanciaEnTransito"             => $mercanciaEnTransito,
          );
          $listaHitosNoConsumidos[] = $row_hito;
        }

        //listaHitosNoConsumidos
        return response()->json([
          'status' => 'success',
          'message' => 'Estructura de datos completa recuperada exitosamente.',
          'hitosNoConsumidos' => $listaHitosNoConsumidos
        ], 200);

      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function obtenerUltimaUbicacion(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_compras' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parámetros de consulta son inválidos.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_compras = $request->input('token_compras');

      try {
        // 1. Buscamos el ID real de la compra a través de su Token
        $compra = DB::table("eegr_compras")
        ->where("token_compras", $token_compras)
        ->select("id")
        ->first();

        if (!$compra) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'No se encontró la compra relacionada especificada.'
          );
        }

        //2. Buscamos el ÚLTIMO hito logístico registrado para esta compra.
        // Ordenamos por 'id' de la tabla padre para asegurar orden cronológico secuencial estricto.
        $ultimoHito = DB::table("logistica_transito_main")
        ->where("compra_relacionada", $compra->id)
        ->orderBy("id", "DESC")
        ->first();

        if (!$ultimoHito) {
          return response()->json([
            'status' => 'success',
            'message' => 'La compra no cuenta con tránsitos o unidades despachadas aún.',
            'data' => null
          ], 200);
        }

        // 3. Obtener TODAS las unidades asociadas a ese último hito
        $unidadesQueViajan = DB::table("logistica_transito_unidades")
        ->where("transito_main", $ultimoHito->id)
        ->orderBy("id", "DESC") // Trae el último ID insertado primero
        ->get();

        if ($unidadesQueViajan->isEmpty()) {
          return response()->json([
            'status' => 'success',
            'message' => 'Hito localizado, pero no cuenta con unidades asignadas.',
            'data' => null
          ], 200);
        }

        // 4. Mapear y desencriptar absolutamente TODOS los campos para el formulario
        $unidadesProcesadas = $unidadesQueViajan->map(function($unidad) use ($JwtAuth) {
          $articulosUnidad = DB::table("logistica_transito_articulos AS art")
            ->leftJoin("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
            ->where("art.transito_unidad_id", $unidad->id)
            ->select(
              "det.token_detcompra",
              "art.articulo_descripcion AS articulo",
              "det.cantidad AS cantidad_comprada",
              "art.cantidad_asignada AS cantidad",
              "art.unidad_medida",
            )
            ->get();
    
          return [
            // Datos de control interno
            'id_unidad_anterior'           => $unidad->id,
            'tipo_transporte'              => $unidad->tipo_transporte,
            
            // Datos del Operador (Desencriptados)
            'operador_nombre'              => $JwtAuth->desencriptar($unidad->operador_nombre),
            'operador_telefono'            => $JwtAuth->desencriptar($unidad->operador_telefono),
            
            // Identificadores Logísticos (Desencriptados)
            'identificador_principal'      => $JwtAuth->desencriptar($unidad->identificador_principal), // Placas/Contenedor/AWB
            'identificador_secundario'     => $JwtAuth->desencriptar($unidad->identificador_secundario), // Remolque/Booking/Vuelo
            'permiso_autorizacion'         => !is_null($unidad->permiso_autorizacion) ? $JwtAuth->desencriptar($unidad->permiso_autorizacion) : '',
            
            // Direcciones de Ruta (Desencriptadas)
            'direccion_origen'             => $JwtAuth->desencriptar($unidad->direccion_origen),
            // 📍 Este destino se convierte en el Origen Sugerido para el nuevo tramo de esta unidad
            'direccion_destino_especifica' => $JwtAuth->desencriptar($unidad->direccion_destino_especifica), 
            
            // Listado completo de mercancía amparada
            'articulos'                    => $articulosUnidad,
            'articulos_seleccionados'      => [] // Inicializado vacío para el manejo de selección en PrimeNG
          ];
        });
        
        $unidadQueViajaID = DB::table("logistica_transito_unidades")
        ->where("transito_main", $ultimoHito->id)
        ->pluck("id"); // Obtenemos solo los IDs de las unidades del tramo anterior

        $queryMercEnTransito = DB::table("logistica_transito_articulos AS art")
        /*->leftJoin*/->join("eegr_compras_detalle AS det", "art.articulo_detcompra", "=", "det.id")
        /*->leftJoin*/->join("in_egr_catalogo_productos AS catprod", "det.producto", "=", "catprod.id")
        ->whereIn("art.transito_unidad_id", $unidadQueViajaID)
        ->select(
          "det.id AS id_det_compras",
          "art.transito_unidad_id",
          "det.token_detcompra",
          "art.articulo_descripcion AS articulo",
          "art.unidad_medida",
          "det.cantidad AS cantidad_original_comprada",
          "det.efecto_fiscal",
          "catprod.token_cat_productos",
          "catprod.folio_sistema AS folio_prod",
          "catprod.post_folio",
          "catprod.producto AS prod_name",
          "catprod.codigo_sku",
          "catprod.codigo_gtin",
          "catprod.codigo_giai",
          "catprod.tipo_llave_gs1",
          DB::raw("MAX(art.cantidad_asignada) as cantidad_en_transito")//DB::raw("SUM(art.cantidad_asignada) as cantidad_en_transito")
        )
        ->groupBy(
          "det.id",
          "art.transito_unidad_id",
          "det.token_detcompra", 
          "art.articulo_descripcion", 
          "art.unidad_medida", 
          "det.cantidad",
          "det.efecto_fiscal",
          "catprod.token_cat_productos",
          "catprod.folio_sistema",
          "catprod.post_folio",
          "catprod.producto",
          "catprod.codigo_sku",
          "catprod.codigo_gtin",
          "catprod.codigo_giai",
          "catprod.tipo_llave_gs1",
        )
        ->get();
        $mercanciaEnTransito = [];
        
        //$allDetailIds = $queryMercEnTransito->pluck('id_det_compras')->filter()->unique()->toArray();
        $allDetailIds = $queryMercEnTransito->pluck('transito_unidad_id')->filter()->unique()->toArray();

        $transitoEstadosMap = DB::table("logistica_transito_articulos AS art")
        ->join("logistica_transito_unidades AS l_uni", "art.transito_unidad_id", "=", "l_uni.id")
        ->join("logistica_transito_main AS l_comp", "l_uni.transito_main", "=", "l_comp.id")
        ->where("l_comp.estado_alcanzado", "<>", "entregado")
        ->where("l_comp.arribo_final_autorizado", true)
        //->whereIn("art.articulo_detcompra", $allDetailIds)
        ->whereIn("art.transito_unidad_id", $allDetailIds)
        ->select(
          //'art.articulo_detcompra AS id_det_compras',
          'art.transito_unidad_id AS id_det_compras',
          DB::raw("SUM(art.cantidad_asignada) as cantidad_total_disponible")
        )
        ->groupBy('art.transito_unidad_id')//'art.articulo_detcompra')
        ->get()
        ->keyBy('id_det_compras');

        foreach ($queryMercEnTransito as $merc) {
          //echo $merc->transito_unidad_id;
          //$movim_prod = $transitoEstadosMap->get($merc->id_det_compras) ?? collect([]);
          //$prod_salieron = $movim_prod->where('estado_alcanzado', 'recolectado')->sum('cantidad_asignada');
          $registroEstado = $transitoEstadosMap->get($merc->transito_unidad_id);
          //$productos_en_espera = $prod_salieron;//$merc->cantidad_en_transito - $prod_salieron;
          $cantidad_disponible = $registroEstado ? (int)$registroEstado->cantidad_total_disponible : 0;

          //$det_compra = DB::table("eegr_compras_detalle")
          //->where("token_detcompra", $merc->token_detcompra)
          //->select("cantidad")
          //->first();
          
          $efecto_fiscal = "";
  
          switch ($merc->efecto_fiscal) {
            case 'ded_inm_apl_mes':
              $efecto_fiscal = "Deducciones Inmediata aplicables al mes";
              break;
            case 'ded_pers_anual':
              $efecto_fiscal = "Deducción Personal (Anual)";
              break;
            case 'ded_inversion':
              $efecto_fiscal = "Deducción de Inversión";
              break;
            case 'no_deducible':
              $efecto_fiscal = "No deducible";
              break;
            default:
              $efecto_fiscal = "sin efecto";
              break;
          }
  
          $reg_tipo_llave_gs1 = "";
          switch ($merc->tipo_llave_gs1) {
            case 'GTIN-12':
              $reg_tipo_llave_gs1 = "UPC (GTIN-12)";
              break;
            case 'GTIN-13':
              $reg_tipo_llave_gs1 = "EAN (GTIN-13)";
              break;
            case 'GTIN-14':
              $reg_tipo_llave_gs1 = "Caja (GTIN-14)";
              break;
            default:
              $reg_tipo_llave_gs1 = "";
              break;
          }

          $mercanciaEnTransito[] = [
            "token_detcompra" => $merc->token_detcompra,
            "token_cat_productos" => $merc->token_cat_productos,
            "articulo" => $merc->articulo,
            "cantidad_comprada" => (int)$merc->cantidad_original_comprada,
            "cantidad_pendiente_transito" => $cantidad_disponible,
            "cantidad" => $merc->cantidad_en_transito,
            "cantidad_transitar" => 0,
            "unidad_medida" => $merc->unidad_medida,
            "efecto_fiscal" => $efecto_fiscal,
            
            "reg_sku" => !is_null($merc->codigo_sku) ? $merc->codigo_sku : '',
            "new_sku" => "",
            "reg_tipo_llave_gs1" => $reg_tipo_llave_gs1,
            "reg_codigo_gs1" => !is_null($merc->codigo_gtin) ? $merc->codigo_gtin : '',
            "new_tipo_llave_gs1" => "",
            "new_codigo_gs1" => "",
          ];
        }

        return response()->json([
          'status' => 'success',
          'message' => 'Estructura de datos completa recuperada exitosamente.',
          'data' => [
            'compra_relacionada_token'        => $token_compras,
            'etapa_anterior'                  => $ultimoHito->estado_alcanzado,
            'observaciones_salida_anteriores' => $ultimoHito->observaciones_salida ? $JwtAuth->desencriptar($ultimoHito->observaciones_salida) : '',
            'observaciones_arribo_anteriores' => $ultimoHito->observaciones_arribo ? $JwtAuth->desencriptar($ultimoHito->observaciones_arribo) : '',
            'unidades_anteriores'             => $unidadesProcesadas,
            'mercanciaEnTransito'             => $mercanciaEnTransito,
          ]
        ], 200);
      } catch (\Throwable $e) {
        return response()->json([
          'status' => 'error',
          'message' => 'Error al consultar el histórico de ubicaciones.',
          'details' => $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}