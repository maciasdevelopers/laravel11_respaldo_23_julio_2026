<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $folio_nomina }}</title>
    <style>
      /* Base de página */
      @page { 
        margin: 30px 25px; 
      }

      body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        color: #1e293b; /* Un gris muy oscuro para mejor contraste */
        line-height: 1.5;
        margin: 0;
      }

      /* Títulos de sección (ej. Fecha Contabilización) */
      .title { 
        font-size: 13px; /* Aumentado ligeramente */
        text-transform: uppercase; 
        color: #475569; /* Gris medio para que el dato resalte más */
        margin-bottom: 4px; 
        font-weight: bold;
        letter-spacing: 0.3px;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }
      
      header table tr td img{border-radius: 50%;border: 1px ouset #353535;margin-right:10px!important;}

      /* Contenido de los datos (ej. 21-04-2026) */
      .content { 
        font-size: 12px; /* Tamaño mucho más legible para PDF */
        color: #000000;
        margin: 0; 
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }

      /* Tabla de Información General */
      .info-table { 
        width: 100%; 
        margin-bottom: 20px; 
        border-collapse: collapse;
      }

      .info-table td { 
        padding: 10px 8px; /* Más aire entre datos */
        border-bottom: 1px solid #cbd5e1; 
        vertical-align: middle; 
      }

      /* Tabla de Impuestos (Cuerpo) */
      .main-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 10px;
      }

      .main-table thead th {
        background-color: #334155 !important;
        color: #ffffff !important;
        font-size: 13px;
        padding: 8px 4px;
        text-align: center;
        border: 1px solid #1e293b;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }

      .main-table thead th .title { 
        font-size: 12px; /* Aumentado ligeramente */
        color: white!important; /* Gris medio para que el dato resalte más */
        width: max-content!important; /* Gris medio para que el dato resalte más */
        margin-bottom: 4px; 
        font-weight: bold;
        letter-spacing: 0.3px;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }

      .main-table td { 
        font-size: 12px;
        padding: 7px 5px; 
        border: 1px solid #e2e8f0; 
        font-size: 11px; /* Letra legible para el desglose */
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }

      /* Clases de texto específicas */
      .determ_imp_title { 
        text-align: left; 
        color: #1e293b;
      }
      .amount { 
        font-size: 12px;
        text-align: right; 
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }
      .determ_imp_totales { 
        text-align: right; 
        font-weight: bold;
        font-size: 11px;
        background-color: #f8fafc;
      }

      /* Footer de la página */
      footer {
        font-size: 9px;
        color: #64748b;
      }

      .pagenum:before {
        content: counter(page);
      }
    </style>
  </head>
  <body>
    <header>
      <table width="100%" style="border-bottom: 2px solid #1a202c; padding-bottom: 10px;">
        <tr>
          <td width="65%" style="vertical-align: middle;">
            <table>
              <tr>
                <td><img src="{{ $logo_emp }}" height="50"> </td>
                <td style="padding-left: 15px; border-left: 1px solid #e2e8f0;">
                  <div style="font-size: 14pt; font-weight: bold; color: #1a202c;">SOLUCIONES OPORTUNAS SIMPLES G&A</div>
                  <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Sinergia Administrativa y Protecci&oacute;n Patrimonial</div>
                </td>
              </tr>
            </table>
          </td>
          <td width="35%" align="right" style="vertical-align: middle;">
            <div style="padding: 10px; border-radius: 4px;">
              <div style="font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">REPORTE DE N&Oacute;MINA </div>
              <div style="font-size: 14pt; font-weight: bold; color: #1a202c; margin-top: 2px;">{{ $folio_nomina }} </div>
              <div style="font-size: 7pt; color: #94a3b8; margin-top: 5px;">EMITIDO EL: {{ date('d M, Y H:i') }}</div>
            </div>
          </td>
        </tr>
      </table>
    </header>
    <br>
    <main>
      <div class="explain">
        <h6 class="title">Fecha de contabilizaci&oacute;n: <strong>{{$nomina_fecha_contabilizacion}}</strong></h6>
        <h6 class="title" style="margin:0!important;">N&uacute;mero de n&oacute;mina: <strong>{{$nomina_numero}}</strong></h6>
        <h6 class="title" style="margin:0!important;">Observaciones: <strong>{{$nomina_observaciones}}</strong></h6>
        <h6 class="title" style="margin:0!important;">Moneda: <strong>{{$moneda_nomina}}</strong></h6>
      </div>

      <div class="explain">
        <h6 class="title">Orden de pago (Efectivo)</h6>
      </div>

      <table>
   			<thead>
					<tr>
            <th>Reporte total</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Orden de pago</th>
					</tr>
				</thead>
				<tbody>
					<tr>
            <td>{{$nomina_reporte_efectivo}}</td>
            <td>{{$nomina_pago_efectivo}}</td>
            <td>{{$nomina_saldo_efectivo}}</td>
            <td>{{$nomina_efectivo_ord_pago_folio}}</td>
					</tr>
				</tbody>
      </table>

      <div class="explain">
        <h6 class="title">Orden de pago (Especie)</h6>
      </div>

      <table>
   			<thead>
					<tr>
            <th>Reporte total</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Orden de pago</th>
					</tr>
				</thead>
				<tbody>
					<tr>
            <td>{{$nomina_reporte_especie}}</td>
            <td>{{$nomina_pago_especie}}</td>
            <td>{{$nomina_saldo_especie}}</td>
            <td>{{$nomina_especie_ord_pago_folio}}</td>
					</tr>
				</tbody>
      </table>

      <div class="explain">
        <h6 class="title">Desglose</h6>
      </div>

      <table class="info-table">
        <tr>
          <td>
            <div class="title">Fecha Contabilizaci&oacute;n</div>
            <div class="title">Fecha Contabilizaci&oacute;n</div>
            <div class="content">---</div>
          </td>
          <td colspan="2">
            <div class="title">Fecha de presentaci&oacute;n</div>
            <div class="title">Fecha de presentaci&oacute;n</div>
            <div class="content">---</div>
          </td>
          <td>
            <div class="title">Fecha de vencimiento</div>
            <div class="title">Fecha de vencimiento</div>
            <div class="content">---</div>
          </td>
        </tr>
      </table>

          <!--
          <tr>
            <th pSortableColumn="nomina_isr_ajustado_por_subsidio_format">ISR AJUSTADO POR SUBSIDIO&nbsp;<p-sortIcon field="nomina_isr_ajustado_por_subsidio_format" /></th>
            <th pSortableColumn="nomina_total_isr_format">ISR&nbsp;<p-sortIcon field="nomina_total_isr_format" /></th>
            <th pSortableColumn="nomina_total_imss_format">IMSS&nbsp;<p-sortIcon field="nomina_total_imss_format" /></th>
            <th pSortableColumn="nomina_credito_fonacot_format">CREDITO FONACOT&nbsp;<p-sortIcon field="nomina_credito_fonacot_format" /></th>
            <th pSortableColumn="nomina_credito_infonavit_format">CREDITO INFONAVIT&nbsp;<p-sortIcon field="nomina_credito_infonavit_format" /></th>
            <th pSortableColumn="nomina_subsidio_empleo_format">SUBSIDIO PARA EL EMPLEO&nbsp;<p-sortIcon field="nomina_subsidio_empleo_format" /></th>
            <th pSortableColumn="nomina_otras_deducciones_format">OTRAS DEDUCCIONES&nbsp;<p-sortIcon field="nomina_otras_deducciones_format" /></th>
            <th pSortableColumn="nomina_total_deducciones_format">TOTAL DEDUCCIONES&nbsp;<p-sortIcon field="nomina_total_deducciones_format" /></th>
            <th pSortableColumn="nomina_total_efectivo_format">TOTAL EFECTIVO&nbsp;<p-sortIcon field="nomina_total_efectivo_format" /></th>
            <th pSortableColumn="nomina_total_en_especie_format">TOTAL EN ESPECIE&nbsp;<p-sortIcon field="nomina_total_en_especie_format" /></th>
            <th pSortableColumn="nomina_neto_pagado_format">NETO PAGADO&nbsp;<p-sortIcon field="nomina_neto_pagado_format" /></th>
            <th pSortableColumn="nomina_horas_por_dia">HORAS POR DIA&nbsp;<p-sortIcon field="nomina_horas_por_dia" /></th>
            <th pSortableColumn="nomina_salario_por_hora_format">SALARIO POR HORA&nbsp;<p-sortIcon field="nomina_salario_por_hora_format" /></th>
          </tr>-->

      <table class="main-table">
        <thead>
          <tr>
            <th>
              <div class="title">CLAVE</div>
              <div class="title">D&Iacute;AS TRABAJADOS</div>
              <div class="title">OTRAS PERCEPCIONES</div>
              <div class="title">JORNADA</div>
            </th>
            <th>
              <div class="title">REGISTRO PATRONAL DEL IMSS</div>
              <div class="title">FALTAS</div>
              <div class="title">OTROS PAGOS</div>
            </th>
            <th>
              <div class="title">NOMBRE DEL TRABAJADOR</div>
              <div class="title">SUELDO</div>
              <div class="title">TOTAL PERCEPCIONES</div>
            </th>
            <th>
              <div class="title">PERIODICIDAD</div>
              <div class="title">HORAS EXTRAS DOBLES</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">PERIODO DE PAGO</div>
              <div class="title">AGUINALDO</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">Moneda</div>
              <div class="title">HORAS EXTRAS TRIPLES</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">No. de cuenta</div>
              <div class="title">VACACIONES</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">NSS</div>
              <div class="title">PRIMA VACACIONAL</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">RFC</div>
              <div class="title">REPARTO DE UTILIDADES</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">CURP</div>
              <div class="title">DESPENSA</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">FECHA DE ALTA</div>
              <div class="title">PREMIOS DE ASISTENCIA</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">DEPARTAMENTO</div>
              <div class="title">PREMIOS DE PUNTUALIDAD</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">PUESTO</div>
              <div class="title">PRIMA DOMINICAL</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">TIPO DE SALARIO</div>
              <div class="title">BNO EXTRA X COMISION OTRO EDO</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">SALARIO DIARIO</div>
              <div class="title">INDEMNIZACION</div>
              <div class="title">---</div>
            </th>
            <th>
              <div class="title">SDI</div>
              <div class="title">PRIMA DE ANTIGUEDAD</div>
              <div class="title">---</div>
            </th>
          </tr>
        </thead>
				<tbody>
          @foreach($desglose as $item)
          <tr>
            <td>{{$item['nomina_clave']}}</td>
            <td class="name">{{$item['centrotrab_registro_patronal_imss']}}</td>
            <td class="name">{{$item['nomina_empleado_nombre']}}</td>
            <td class="name">{{$item['nomina_periodicidad']}}</td>
            <td class="name">{{$item['nomina_periodo_pago']}}</td>
            <td class="name">{{$item['nomina_moneda']}}</td>
            <td class="name">{{$item['nomina_empleado_cbankBancoNombre']." ".$item['nomina_empleado_cbankCuenta']}}</td>
            <td class="name">{{$item['nomina_empleado_nss']}}</td>
            <td class="name">{{$item['nomina_empleado_rfc']}}</td>
            <td class="name">{{$item['nomina_empleado_curp']}}</td>
            <td class="name">{{$item['nomina_empleado_fecha_alta']}}</td>
            <td class="name">{{$item['nomina_empleado_departamento']}}</td>
            <td class="name">{{$item['nomina_empleado_puesto']}}</td>
            <td class="name">{{$item['nomina_empleado_tipo_salario']}}</td>
            <td class="td_importes">{{$item['nomina_salario_diario_format']}}</td>
            <td class="td_importes">{{$item['nomina_salario_integrado_format']}}</td>
          </tr>
          <tr>
            <td class="td_importes">{{$item['nomina_dias_trabajados']}}</td>
            <td class="td_importes">{{$item['nomina_faltas']}}</td>
            <td class="td_importes">{{$item['nomina_sueldo_format']}}</td>
            <td class="td_importes">{{$item['nomina_horas_extras_dobles_format']}}</td>
            <td class="td_importes">{{$item['nomina_aguinaldo_format']}}</td>
            <td class="td_importes">{{$item['nomina_horas_extras_triples_format']}}</td>
            <td class="td_importes">{{$item['nomina_vacaciones_format']}}</td>
            <td class="td_importes">{{$item['nomina_prima_vacacional_format']}}</td>
            <td class="td_importes">{{$item['nomina_reparto_de_utilidades_format']}}</td>
            <td class="td_importes">{{$item['nomina_despensa_format']}}</td>
            <td class="td_importes">{{$item['nomina_premios_de_asistencia_format']}}</td>
            <td class="td_importes">{{$item['nomina_premios_de_puntualidad_format']}}</td>
            <td class="td_importes">{{$item['nomina_prima_dominical_format']}}</td>
            <td class="td_importes">{{$item['nomina_bno_extra_x_comision_otro_edo_format']}}</td>
            <td class="td_importes">{{$item['nomina_indemnizacion_format']}}</td>
            <td class="td_importes">{{$item['nomina_prima_de_antiguedad_format']}}</td>
          </tr>
          <tr>
            <td class="td_importes">{{$item['nomina_otras_percepciones_format']}}</td>
            <td class="td_importes">{{$item['nomina_otros_pagos_format']}}</td>
            <td class="td_importes">{{$item['nomina_total_percepciones_format']}}</td>
            <td class="td_importes">{{$item['nomina_isr_ajustado_por_subsidio_format']}}</td>
            <td class="td_importes">{{$item['nomina_total_isr_format']}}</td>
            <td class="td_importes">{{$item['nomina_total_imss_format']}}</td>
            <td class="td_importes">{{$item['nomina_credito_fonacot_format']}}</td>
            <td class="td_importes">{{$item['nomina_credito_infonavit_format']}}</td>
            <td class="td_importes">{{$item['nomina_subsidio_empleo_format']}}</td>
            <td class="td_importes">{{$item['nomina_otras_deducciones_format']}}</td>
            <td class="td_importes">{{$item['nomina_total_deducciones_format']}}</td>
            <td class="td_importes">{{$item['nomina_total_efectivo_format']}}</td>
            <td class="td_importes">{{$item['nomina_total_en_especie_format']}}</td>
            <td class="td_importes">{{$item['nomina_neto_pagado_format']}}</td>
            <td class="td_importes">{{$item['nomina_horas_por_dia']}}</td>
            <td class="td_importes">{{$item['nomina_salario_por_hora_format']}}</td>
            <td class="name">{{$item['nomina_clave']}}</td>
          </tr>
          @endforeach
        </tbody>

        <tfoot>
          <tr class="row-totals">
            <td class="td_importes" colspan="14"><strong>Totales</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_salario_diario}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_salario_integrado}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_dias_trabajados}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_faltas}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_sueldo}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_horas_extras_dobles}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_aguinaldo}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_horas_extras_triples}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_vacaciones}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_prima_vacacional}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_reparto_de_utilidades}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_despensa}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_premios_de_asistencia}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_premios_de_puntualidad}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_prima_dominical}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_bno_extra_x_comision_otro_edo}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_indemnizacion}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_prima_de_antiguedad}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_otras_percepciones}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_otros_pagos}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_total_percepciones}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_isr_ajustado_por_subsidio}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_total_isr}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_total_imss}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_credito_fonacot}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_credito_infonavit}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_subsidio_empleo}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_otras_deducciones}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_total_deducciones}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_total_efectivo}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_total_en_especie}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_neto_pagado}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_horas_por_dia}}</strong></td>
            <td class="td_importes"><strong>{{$nomina_totales_salario_por_hora}}</strong></td>
            <td class="td_importes" colspan="5"></td>
          </tr>
        </tfoot>
      </table>

      <!--<table>
   			<thead>
					<tr>
						<th>No.</th>
            <th>Trabajador</th>
            <th>Moneda</th>
            <th>Banco</th>
            <th>No. de cuenta</th>
            <th>NSS</th>
            <th>Rfc</th>
            <th>Curp</th>
            <th>FECHA DE ALTA</th>
            <th>DEPARTAMENTO</th>
            <th>PUESTO</th>
            <th>TIPO DE SALARIO</th>
            <th>TOTAL EN ESPECIE</th>
					</tr>
				</thead>
				<tbody>
          @ foreach($nomina_desglose as $item)
					<tr>
					  <td>{--{$item['nomina_clave']}}</td>
					  <td>{--{$item['nomina_empleado']}}</td>
					  <td>{--{$item['nomina_moneda']}}</td>
					  <td>{--{$item['nomina_empleado_cbankBancoNombre']}}</td>
					  <td>{--{$item['nomina_empleado_cbankCuenta']}}</td>
					  <td>{--{$item['nomina_empleado_nss']}}</td>
					  <td>{--{$item['nomina_empleado_rfc']}}</td>
					  <td>{--{$item['nomina_empleado_curp']}}</td>
					  <td>{--{$item['nomina_empleado_fecha_alta']}}</td>
					  <td>{--{$item['nomina_empleado_departamento']}}</td>
					  <td>{--{$item['nomina_empleado_puesto']}}</td>
					  <td>{--{$item['nomina_empleado_tipo_salario']}}</td>
            <td style="text-align: right!important;">{--{$item['total_en_especie_format']}}</td>
					</tr>
          @ endforeach
				</tbody>
        <tfoot>
          <tr class="row-totals">
            <td colspan="12" class="text-right label-total">TOTAL EN ESPECIE:</td>
            <td class="amount-total">{--{ $importe_especie_total }}</td>
          </tr>
        </tfoot>
      </table>-->

    </main>
    <footer style="position: fixed; bottom: -30px; left: 0px; right: 0px; height: 50px;">
      <table width="100%" style="border-top: 1px solid #e2e8f0; padding-top: 10px;">
        <tr>
          <td width="33%" style="font-size: 8pt; color: #94a3b8;">sos-mexico.com.mx </td>
          <td width="33%" align="center" style="font-size: 8pt; color: #64748b; font-weight: bold;">CONFIDENCIAL </td>
          <td width="33%" align="right" style="font-size: 8pt; color: #94a3b8;">P&aacute;gina <span class="pagenum"></span> </td>
        </tr>
      </table>
    </footer>
  </body>
</html>