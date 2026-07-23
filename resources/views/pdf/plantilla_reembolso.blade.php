  public function verPdfHtmlReembolso(Request $request){
    $JwtAuth = new \JwtAuth();
    $tokenReem = $request->tokenReem;
    if (!empty($tokenReem) && !empty($tokenReem)) {

      $reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
        ->join("sos_reembolsos_comisiones_rel AS comi_reem", "reem_main.id", "=", "comi_reem.reembolso_main")
        ->join("terc_comisiones_main AS comi_main", "comi_reem.comision", "=", "comi_main.id")
        ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
        //->join("teci_catalogo_monedas AS catmon","emp.e_moneda","=","catmon.id")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
        ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
        ->where(["reem_main.token_reem" => $tokenReem, "reem_main.status_reem" => TRUE])->get();

      foreach ($reembolso_main_selected as $vremb) {
        $nameEmp = "SOLUCIONES OPORTUNAS SIMPLES";
        $logoEmp = "";

        date_default_timezone_set($vremb->zona_horaria);
        $fecha_solicitud = date('d-m-Y H:i:s', $vremb->fecha_sistema);
        $token_reem = $vremb->token_reem;

        if ($vremb->post_folio_reem == NULL) {
          $folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
        } else {
          $folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;
        }

        //emisor 
        $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
          ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        foreach ($selectNameEmpEmi as $vEmisor) {
          $name_emisor = $vEmisor->abrev_nombre;

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

          $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vEmisor->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($vEmisor->img_perfil)));
        }

        $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
          ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        foreach ($selectPersEmpEmi as $vPemi) {
          $paterno_emi = ucfirst($JwtAuth->desencriptar($vPemi->paterno));
          $materno_emi = ucfirst($JwtAuth->desencriptar($vPemi->materno));
          $nombres_emi = ucwords($JwtAuth->desencriptar($vPemi->nombre));
          $name_pers_emisor = $paterno_emi . " " . $materno_emi . " " . $nombres_emi;
        }

        //receptor                
        $selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
          ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
          ->join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        $txt_folio_solicitud = "0";

        foreach ($selectNameEmpRec as $vReceptor) {
          $tkn_receptor = $vReceptor->empresa_token;
          $name_receptor = $vReceptor->abrev_nombre;

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

        $name_pers_receptor_vh = "N/A";
        $selectPersEmpReceptorVH = DB::table("terc_reembolso_main AS reem_main")
          ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        if (count($selectPersEmpReceptorVH) == 1) {
          foreach ($selectPersEmpReceptorVH as $vPVH) {
            $desif_paterno_vh = ucfirst($JwtAuth->desencriptar($vPVH->paterno));
            $desif_materno_vh = ucfirst($JwtAuth->desencriptar($vPVH->materno));
            $desif_nombres_vh = ucwords($JwtAuth->desencriptar($vPVH->nombre));
            $name_pers_receptor_vh = $desif_paterno_vh . " " . $desif_materno_vh . " " . $desif_nombres_vh;
          }
        }

        $selectPersEmpReceptorEGR = DB::table("terc_reembolso_main AS reem_main")
          ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
          ->where(["reem_main.token_reem" => $vremb->token_reem])->get();

        foreach ($selectPersEmpReceptorEGR as $vPEgr) {
          $desif_paterno_egr = ucfirst($JwtAuth->desencriptar($vPEgr->paterno));
          $desif_materno_egr = ucfirst($JwtAuth->desencriptar($vPEgr->materno));
          $desif_nombres_egr = ucwords($JwtAuth->desencriptar($vPEgr->nombre));
          $name_pers_receptor_egr = $desif_paterno_egr . " " . $desif_materno_egr . " " . $desif_nombres_egr;
        }

        $soli_reem = DB::table("terc_reembolso_main AS reem_main")
          ->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
          ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
          ->where(["reem_main.token_reem" => $token_reem])
          ->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

        $desg_reem = '';

        $importe_total = 0;
        $importe_total_conv = 0;
        $total_tipo_cambio = 0;
        $total_reem_auth = 0;
        $total_reem_auth_conv = 0;

        $moneda_ent_string = "";
        $moneda_ent_dec = 0;

        $moneda_sal_string = "";
        $moneda_sal_dec = 0;
        $total_reem_saliente = 0;

        foreach ($soli_reem as $vSoliR) {
          $soli_mon_entrante = DB::table("teci_catalogo_monedas AS mon_in")
            ->join("terc_reembolso_solicitud AS reem_soli", "mon_in.id", "=", "reem_soli.moneda_entrante")
            ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
          foreach ($soli_mon_entrante as $mon_in) {
            $moneda_ent_string = $mon_in->codigo;
            $moneda_ent_dec = $mon_in->decimales;
            $moneda_solie_string = $mon_in->codigo;
            $moneda_solie_dec = $mon_in->decimales;
          }

          $total_tipo_cambio = $vSoliR->tipo_cambio;
          $importe_total = $importe_total + $vSoliR->importe_entrante;
          $importe_total_conv = $importe_total_conv + ($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
          if (($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") && $vSoliR->autorizacion_egr == "A") {
            $total_reem_auth = $total_reem_auth + $vSoliR->importe_entrante;
            $total_reem_auth_conv = $total_reem_auth_conv + ($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
          }

          $soli_mon_saliente = DB::table("teci_catalogo_monedas AS mon_out")
            ->join("terc_reembolso_solicitud AS reem_soli", "mon_out.id", "=", "reem_soli.moneda_saliente")
            ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
          foreach ($soli_mon_saliente as $mon_out) {
            $moneda_sal_string = $mon_out->codigo;
            $moneda_sal_dec = $mon_out->decimales;
            $moneda_soliO_string = $mon_out->codigo;
            $moneda_soliO_dec = $mon_out->decimales;
          }

          $importe_entr = "$" . number_format($vSoliR->importe_entrante, $moneda_solie_dec, '.', ',') . " " . $moneda_solie_string;
          $importe_sali = "$" . number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio, $moneda_soliO_dec, '.', ',') . " " . $moneda_soliO_string;

          //proveedor
          $tkn_prov = "";
          $name_prov = "";
          $rfc_generico_prov = "";
          $rfc_prov = "";
          $taxid_prov = "";
          if ($vSoliR->proveedor != NULL) {
            $soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
              ->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
              ->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
              ->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
              ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
              ->where([
                "reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
                "rmain.token_reem" => $token_reem
              ])->get();

            foreach ($soli_r_prov as $sr_prov) {
              $tkn_prov = $sr_prov->token_cat_proveedores;
              $name_prov = $JwtAuth->desencriptar($sr_prov->nombre_extendido);
              $rfc_generico_prov = $sr_prov->rfc_generico;

              if ($sr_prov->rfc != NULL) {
                $rfc_prov = $JwtAuth->desencriptar($sr_prov->rfc);
              } else {
                $rfc_prov = "---";
              }

              if ($sr_prov->tax_id != NULL) {
                $taxid_prov = $JwtAuth->desencriptar($sr_prov->tax_id);
              } else {
                $taxid_prov = "---";
              }
            }
          }

          if ($vSoliR->pagado_a == "pubgeneral") {
            $pagado_a = "público general";
          } else {
            $pagado_a = "proveedor (" . $rfc_prov . " " . $name_prov . ")";
          }

          $select_folio_auth_vh = DB::select(
            "SELECT r_auth.id FROM terc_reembolso_autorizacion_vh AS r_auth 
                        JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                        WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                        AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
            [$token_reem, $vSoliR->token_solicitud_reem]
          );

          if (count($select_folio_auth_vh) == 0) {
            $max_auth_vh = false;
            $time_registro_auth_vh = "";
            $comments_auth_vh = "";
          } else {
            $select_max_auth_vh = DB::select(
              "SELECT fecha_registro,autorizacion_vh,comentarios 
                            FROM terc_reembolso_autorizacion_vh WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_vh AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
              [$token_reem, $vSoliR->token_solicitud_reem]
            );
            if ($select_max_auth_vh[0]->autorizacion_vh == "A") {
              $max_auth_vh = true;
            } else {
              $max_auth_vh = false;
            }
            $time_registro_auth_vh = date('d-m-Y - H:i:s', $select_max_auth_vh[0]->fecha_registro);
            $comments_auth_vh = $JwtAuth->desencriptar($select_max_auth_vh[0]->comentarios);
          }
          //echo $vSoliR->autorizacion_vh;
          if ($vSoliR->autorizacion_vh == "A") {
            $autorizacion_vh = "si (" . $time_registro_auth_vh . ")";
          } else if ($vSoliR->autorizacion_vh == "N") {
            $autorizacion_vh = "N/A";
          } else {
            $autorizacion_vh = "no";
          }

          $select_folio_auth_egr = DB::select(
            "SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth 
                        JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                        WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                        AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
            [$token_reem, $vSoliR->token_solicitud_reem]
          );

          if (count($select_folio_auth_egr) == 0) {
            $max_auth_egr = false;
            $time_registro_auth_egr = "";
            $comments_auth_egr = "";
          } else {
            $select_max_auth_egr = DB::select(
              "SELECT fecha_registro,autorizacion_egr,comentarios 
                            FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
              [$token_reem, $vSoliR->token_solicitud_reem]
            );
            if ($select_max_auth_egr[0]->autorizacion_egr == "A") {
              $max_auth_egr = true;
            } else {
              $max_auth_egr = false;
            }

            $time_registro_auth_egr = date('d-m-Y - H:i:s', $select_max_auth_egr[0]->fecha_registro);
            $comments_auth_egr = $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios);
          }

          if ($vSoliR->autorizacion_egr == "A") {
            $autorizacion_egr = "si (" . $time_registro_auth_egr . ")";
          } else {
            $autorizacion_egr = "no";
          }
          //date('d-m-Y H:i:s',$vSoliR->fecha_gasto)
          $desg_reem = $desg_reem . '<tr><td>' . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . '</td><td>' . date('d-m-Y H:i:s', $vSoliR->fecha_solicitud) . '</td>
                        <td>' . date('d-m-Y', $vSoliR->fecha_gasto) . '</td><td>' . $JwtAuth->desencriptar($vSoliR->ticket_gasto) . '</td><td>' . $pagado_a . '</td>
                        <td>' . $vSoliR->clave . ' ' . $vSoliR->forma . '</td><td>' . $importe_entr . ' / ' . $importe_sali . '</td><td>$' . $vSoliR->tipo_cambio . '</td>
                        <td>' . $JwtAuth->desencriptar($vSoliR->motivo_reem) . '</td><td>valor humano: ' . $autorizacion_vh . '</td><td>Egresos: ' . $autorizacion_egr . '</td></tr>';
        }

        $pagos_reem = '';
        $listaPagos = DB::select("SELECT payment.token_pagos,payment.folio_pagos,payment.fecha_sistema,payment.fecha_pago,
                    payment.cuenta_bancaria,payment.cuenta_monedero,payment.caja,payment.monto_pago,payment.tipo_cambio,
                    payment.forma_pago,payment.metodo_pago,payment.p_moneda,payment.concepto,payment.almacen,payment.personal_pago,
                    payment.personal_autoriza,payment.empresa,payment.status_pagos,payment.fecha_deletePagos,payment.pago_autorizado,
                    ordenp.fecha_sistema_ordenp,ordenp.folio_ordenPago FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_orden AS ordenp 
                    JOIN terc_reembolso_main AS reem_main WHERE payment.orden_pago = ordenp.id AND ordenp.reembolso_main = reem_main.id 
                    AND reem_main.token_reem = ?", [$token_reem]);

        if (count($listaPagos) > 0) {
          $num_lista_pagos = 1;
          $total_pagado = 0;
          foreach ($listaPagos as $resListaPagos) {
            $total_pagado = $total_pagado + $resListaPagos->monto_pago;

            $forma_pago_text = "-";
            if ($resListaPagos->forma_pago != NULL) {
              $pagosformaPago = DB::select("SELECT token_formapago,clave,forma FROM teci_forma_pago WHERE id = ?", [$resListaPagos->forma_pago]);
              $forma_pago_text = $pagosformaPago[0]->clave . ' - ' . $pagosformaPago[0]->forma;
            }

            $metodo_pago_text = "-";
            if ($resListaPagos->metodo_pago != NULL) {
              $pagosmetodoPago = DB::select("SELECT token_metodopago,abrev,metodo FROM teci_metodo_pago WHERE id = ?", [$resListaPagos->metodo_pago]);
              $metodo_pago_text = $pagosmetodoPago[0]->abrev . ' - ' . $pagosmetodoPago[0]->metodo;
            }
            $pagosmoneda = DB::select("SELECT token_monedas,codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resListaPagos->p_moneda]);

            $medio_de_pago = "indefinido";
            $name_caja = "---";
            $name_cuenta_banc = "---";
            $name_cuenta_mone = "---";

            if ($resListaPagos->caja != NULL) {
              $medio_de_pago = "caja";
              $cajaPago = DB::table("fnzs_catalogos_caja")->where(["id" => $resListaPagos->caja])->get();
              foreach ($cajaPago as $resultCaja) {
                $name_caja = $JwtAuth->generar($resultCaja->no_caja) . " (" . $JwtAuth->desencriptar($resultCaja->alias_caja) . ")";
              }
            }

            if ($resListaPagos->cuenta_bancaria != NULL) {
              $medio_de_pago = "cuenta bancaria";
              $tknCuenta = DB::select("SELECT token_cuenta FROM fnzs_catalogos_cuentas WHERE id = ?", [$resListaPagos->cuenta_bancaria]);

              $respCuenta = DB::table("fnzs_catalogos_cuentas AS account")
                ->join("teci_bancos AS bank", "account.banco", "bank.id")
                ->where(["account.id" => $resListaPagos->cuenta_bancaria])->get();

              if (count($respCuenta) != 0) {
                foreach ($respCuenta as $resCuentas) {
                  $name_cuenta_banc = $JwtAuth->generar($resCuentas->folio_cuenta) . " (" . $resCuentas->clave . " - " . $resCuentas->nombre_comercial . ")";
                }
              }
            }

            if ($resListaPagos->cuenta_monedero != NULL) {
              $medio_de_pago = "cuenta de monedero electrónico";
              $arrayOpcionAdicionalMon = array();
              $idCuentaMonedero = DB::select("SELECT token_cuentamonedero FROM fnzs_catalogos_cuentas_monedero WHERE id = ?", [$resListaPagos->cuenta_monedero]);

              $respMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS accMon")
                ->join("teci_plataformas_digitales AS pdig", "accMon.monedero", "pdig.id")
                ->where(["accMon.id" => $resListaPagos->cuenta_monedero])->get();

              foreach ($respMonedero as $resMonedero) {
                $name_cuenta_mone = $JwtAuth->generar($resMonedero->folio_cuentmon) . " (" . $resMonedero->nombre . ")";
              }
            }

            $pagos_reem = $pagos_reem . '<tr>
                            <td>' . $num_lista_pagos . '</td>
                            <td>' . $medio_de_pago . '</td>
                            <td>' . $name_caja . '</td>
                            <td>' . $name_cuenta_banc . '</td>
                            <td>' . $name_cuenta_mone . '</td>
                            <td>' . $forma_pago_text . '</td>
                            <td>' . $metodo_pago_text . '</td>
                            <td>' . $pagosmoneda[0]->codigo . ' - ' . $pagosmoneda[0]->moneda . '</td>
                            <td>$' . number_format($resListaPagos->tipo_cambio, $vremb->decimales, '.', ',') . '</td>
                            <td>$' . number_format($resListaPagos->monto_pago, $vremb->decimales, '.', ',') . '</td>
                        </tr>';

            ++$num_lista_pagos;
          }
          $pagos_reem = $pagos_reem . '<tr><td colspan="8"></td><td>Total:</td><td>$' . number_format($total_pagado, $vremb->decimales, '.', ',') . '</td></tr>';
        } else {
          $pagos_reem = $pagos_reem . '<tr><td colspan="10">!NO HAY REGISTROS¡</td></tr>';
        }

        $table_docs_asoc = "";
        $selectAnexosReem = DB::table("sos_documentos AS docs")
          ->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
          ->where(["docs.tipo_documento" => "an", "main.token_reem" => $token_reem])->get();

        if (count($selectAnexosReem) > 0) {
          $name_docs_asoc = "";
          foreach ($selectAnexosReem as $vDoc) {
            $name_docs_asoc = $name_docs_asoc . $JwtAuth->desencriptar($vDoc->nombre_documento) . ", ";
          }
          $thml_docs_asoc = '<tr><td colspan="2">Documentos asociados: ' . substr($name_docs_asoc, 0, -2) . '</td></tr>';
        } else {
          $thml_docs_asoc = $thml_docs_asoc . '<tr><td colspan="2">Sin documentos asociados</td></tr>';
        }

        //echo $desg_reem;
        $monto_total_entr = "$" . number_format($importe_total, $moneda_ent_dec, '.', ',') . " " . $moneda_ent_string;
        $monto_total_sali = "$" . number_format($importe_total_conv, $moneda_sal_dec, '.', ',') . " " . $moneda_sal_string;
        $monto_auth_entr = "$" . number_format($total_reem_auth, $moneda_ent_dec, '.', ',') . " " . $moneda_ent_string;
        $monto_auth_sali = "$" . number_format($total_reem_auth_conv, $moneda_sal_dec, '.', ',') . " " . $moneda_sal_string;

        $cargaPDFAuth = '<!doctype html>
                    <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <title>Reembolsos</title>
                            <style type="text/css">' . $JwtAuth->css_pdf() . '</style>
                        </head>
                        <body>
                            <header class="information information-cpp">
                                <table width="100%" style="margin:0!important;padding:0!important;">
                                    <tr><td colspan="3" style="margin:0!important;padding:0!important;" align="center">
                                        <img src="' . $logoEmp . '" alt="Logo" height="50" class="logotipo"/>
                                        <h4 style="margin:0!important;padding:0!important;">' . $nameEmp . '</h4>
                                    </td></tr>
                                    <tr>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Módulo de empleados</h3></td>
                                        <td align="center" style="width: 60%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Reporte de reembolsos</h3></td>
                                        <td align="center" style="width: 20%;"><h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">' . date('d M, Y H:i:s', time()) . '</h3></td>
                                    </tr>
                                </table>
                            </header>
                            <main>
                                <h3 style="text-align:center;">' . $folio_reem . '</h3>
                                <article style="margin-top:20px;">
                                    <table>  
                                        <thead>
                                            <tr>
                                                <th>FECHA DE REGISTRO</th>
                                                <th>Comisión</th>
                                                <th>EMISOR</th>
                                                <th colspan="2">RECEPTOR</th>
                                                <th>Total</th>
                                                <th>Autorizado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>' . $fecha_solicitud . '</td>
                                                <td>COMI-' . $JwtAuth->generarFolio($vremb->folio_comision) . '</td>
                                                <td>' . $name_pers_emisor . ' (' . $name_emisor . ')</td>
                                                <td>' . $name_pers_receptor_vh . ' (Valor Humano ' . $name_receptor . ')</td>
                                                <td>' . $name_pers_receptor_egr . ' (Egresos ' . $name_receptor . ')</td>
                                                <td>' . $monto_total_entr . ' / ' . $monto_total_sali . '</td>
                                                <td>' . $monto_auth_entr . ' / ' . $monto_auth_sali . '</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </article>
                                <article style="margin-top:20px;">
                                    <h4>Listado de solicitudes</h4>
                                    <div class="card">
                                        <table>
               					    		<thead>
            					    	    	<tr>
            					    	    		<th>folio</th>
            					    	    		<th>fecha de solicitud</th>
            					    	    		<th>Fecha de gasto</th>
            					    	    		<th>Ticket</th>
            					    	    		<th>Pagado a:</th>
            					    	    		<th>forma de pago</th>
            					    	    		<th>importe</th>
            					    	    		<th>tipo de cambio</th>
            					    	    		<th>observaciones</th>
            					    	    		<th colspan="2">autorizado por</th>
            					    	    	</tr>
            					    	    </thead>
            					    		<tbody>' . $desg_reem . '</tbody>
                                        </table>
                                    </div>
                                </article>
                                <article style="margin-top:20px;">
                                    <h4>PAGOS REALIZADOS</h4>
                                    <table>
   							            <thead>
						                	<tr>
                                                <th class="ultimo"></th>
                                                <th>medio de pago</th>
                                                <th>caja (folio)</th>
                                                <th>cuenta (folio)</th>
                                                <th>monedero (folio)</th>
                                                <th>forma de pago</th>
                                                <th>metodo de pago</th>
                                                <th>moneda</th>
                                                <th>tipo de cambio</th>
                                                <th>pago recibido</th>
						                	</tr>
						                </thead>
							            <tbody>' . $pagos_reem . '</tbody>
                                    </table>
                                </article>
                            </main>
                            
                            <footer style="display:flex;">
                                <table width="100%">' . $thml_docs_asoc . '<tr>
                                        <td align="left" style="width: 50%;">sos-mexico.com.mx</td>
                                        <td align="right" style="width: 50%;">página <span class="page"></span></td>
                                    </tr>
                                </table>
                            </footer>
                        </body>
                    </html>';

        $dompdf = \PDF::loadHtml($cargaPDFAuth); //Se define el objeto DomPdf con el contenido HTML.
        $dompdf->setPaper('A2', 'landscape'); //Se define tamaño y orientación del papel
        $dompdf->render(); // Renderizamos el documento PDF.
        $contenidoPDF = $dompdf->stream($folio_reem . ".pdf"); // Enviamos el fichero PDF al navegador.
        return $contenidoPDF;
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