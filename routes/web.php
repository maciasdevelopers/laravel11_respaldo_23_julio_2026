<?php

/*header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ImagesController;

//Route::get("","");
Route::get("view_post_images/{folio}/{nombre_imagen}","App\Http\Controllers\MAIN_PdfsController@visorImagenes");
//reembolsos
    Route::get("reembolso_pdf/{tokenReem}","App\Http\Controllers\MAIN_PdfsController@verPdfHtmlReembolso");
    Route::get("reembolsos_anexos/{reemFolio}/{tknAnexo}","App\Http\Controllers\MAIN_PdfsController@egr_reembolso_visor_anexos");
    Route::get("pago_realizado_docs/{folioPago}/{tokenDocumento}","App\Http\Controllers\MAIN_PdfsController@visorDocsPagos");
//compras
    Route::get("compras_pdf/{token_compras}","App\Http\Controllers\MAIN_PdfsController@verCompraPdfHtml");
    Route::get("compras/requisiciones/{folioReq}/{tokenAnexo}","App\Http\Controllers\MAIN_PdfsController@verDocRequiAnexo");
    Route::get("requisicion_pdf/{tokenRequi}","App\Http\Controllers\MAIN_PdfsController@verRequisicionPdfHtml");
    Route::get("cotizacion_pdf/{tokenRequi}/{tokenCoti}","App\Http\Controllers\MAIN_PdfsController@verCotizacionPdfHtml");
    Route::get("compras/{token_compras}/factura_xml","App\Http\Controllers\MAIN_PdfsController@verCompraFacturaXML");
    Route::get("compras/{token_compras}/factura_pdf","App\Http\Controllers\MAIN_PdfsController@verCompraFacturaPDF");
    Route::get("compras/{token_compras}/evidencia_sat","App\Http\Controllers\MAIN_PdfsController@verCompraEvidenciaSAT");
//nominas
    Route::get("nomina_en_especie_pdf/{token_nominas_especie}","App\Http\Controllers\MAIN_PdfsController@verNominaEnEspeciePdfHtml");
    Route::get("nomina_en_efectivo_pdf/{token_nominas_periodos}","App\Http\Controllers\MAIN_PdfsController@verNominaEnEfectivoPdfHtml");
    Route::get("impuestos_sobre_nomina_pdf/{nomi_imp_token}","App\Http\Controllers\MAIN_PdfsController@verImpuestosSobreNominaPdfHtml");
    Route::get("impuestos_sobre_nomina_fact_xml/{nomi_imp_token}","App\Http\Controllers\MAIN_PdfsController@verImpuestosSobreNominaFactXML");
    Route::get("impuestos_sobre_nomina_fact_pdf/{nomi_imp_token}","App\Http\Controllers\MAIN_PdfsController@verImpuestosSobreNominaFactPDF");
    Route::get("impuestos_sobre_nomina/{folio_isn}/{token_documento}","App\Http\Controllers\MAIN_PdfsController@verImpuestosSobreNominaDocsAdjuntos");
//IMSS
    Route::get("aportaciones_de_seguridad_social_pdf/{aport_ssocial_token}","App\Http\Controllers\MAIN_PdfsController@verAportacionesSeSeguridadSocialPdfHtml");
    Route::get("aportaciones_de_seguridad_social_imss_fact_xml/{aport_ssocial_token}","App\Http\Controllers\MAIN_PdfsController@verAportSegSocialImssFactXML");
    Route::get("aportaciones_de_seguridad_social_imss_fact_pdf/{aport_ssocial_token}","App\Http\Controllers\MAIN_PdfsController@verAportSegSocialImssFactPDF");
    Route::get("aportaciones_de_seguridad_social_infonavit_fact_xml/{aport_ssocial_token}","App\Http\Controllers\MAIN_PdfsController@verAportSegSocialInfonavitFactXML");
    Route::get("aportaciones_de_seguridad_social_infonavit_fact_pdf/{aport_ssocial_token}","App\Http\Controllers\MAIN_PdfsController@verAportSegSocialInfonavitFactPDF");
    Route::get("aportaciones_de_seguridad_social/{folio_aport}/{token_documento}","App\Http\Controllers\MAIN_PdfsController@verAportSegSocialDocsAdjuntos");
//declaraciones de impuestos federales
    Route::get("declaraciones_de_impuestos_federales_pdf/{declaracion_token}","App\Http\Controllers\MAIN_PdfsController@verDeclaracionesDeImpuestosFederalesPdfHtml");
//proyectos
    Route::get("proyectos/{folioProy}/{folioTar}/{folioInf}/{tokenInf}","App\Http\Controllers\ModuleProyectosController@verDocInforme");
    Route::get("informe_proyecto/{json}/{archivo}","App\Http\Controllers\ModuleProyectosController@visorEvidencias");
//empresas
    Route::get("empresa_img/{tokenEmpresa}","App\Http\Controllers\MAIN_EmpresasController@empresaLogotypo");