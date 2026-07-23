<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $aport_ssocial_folio }}</title>
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
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-weight: bold;
        color: #1a1a1a; /* Un negro casi total pero no puro para dar elegancia */
        text-transform: none; /* Mantener mayusculas y minusculas como en la imagen */
        letter-spacing: 0.02em; /* Un ligero espaciado para mejorar la legibilidad */
      }
      .amount { 
        font-size: 12px;
        text-align: right; 
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      }
      .amount_total { text-align: right; font-weight: bold; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
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
              <div style="font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">REPORTE DE APORTACIONES DE SEGURIDAD SOCIAL</div>
              <div style="font-size: 14pt; font-weight: bold; color: #1a202c; margin-top: 2px;">{{ $aport_ssocial_folio }} </div>
              <div style="font-size: 7pt; color: #94a3b8; margin-top: 5px;">EMITIDO EL: {{ date('d M, Y H:i') }} </div>
            </div>
          </td>
        </tr>
      </table>
    </header>
    <br>
    <main>
      <table class="info-table">
    		<thead>
    			<tr>
    				<th colspan="4">Datos del patr&oacute;n o sujeto obligado registrados en el IMSS</th>
    			</tr>
    		</thead>
        <tr>
          <td>
            <div class="title">Fecha Contabilizaci&oacute;n</div>
            <div class="content">{{$aport_ssocial_fecha_contabilizacion}}</div>
          </td>
          <td colspan="2">
            <div class="title">Fecha de presentaci&oacute;n</div>
            <div class="content">{{$aport_ssocial_fecha_presentacion}}</div>
          </td>
          <td>
            <div class="title">Registro Patronal</div>
            <div class="content">{{$aport_ssocial_registro_patronal}}</div>
          </td>
        </tr>
        <tr>
          <td colspan="1">
            <div class="title">Per&iacute;odo que comprende el pago de seguros imss (Mes-Año)</div>
            <div class="content">{{$periodo_pago_seguros_imss}}</div>
          </td>
          <td>
            <div class="title">Bimestre que comprende el pago rcv e infonavit</div>
            <div class="content">{{$pago_rcv_infonavit}}</div>
          </td>
          <td>
            <div class="title">Folio sua</div>
            <div class="content">{{$folio_sua}}</div>
          </td>
          <td>
            <div class="title">Clave de recepci&oacute;n de archivo de pago</div>
            <div class="content">{{$clave_recepcion_archivo_pago}}</div>
          </td>
        </tr>
      </table>

      <table class="info-table">
    		<thead>
    			<tr>
    				<th colspan="3">Informaci&oacute;n general de la propuesta</th>
    			</tr>
    		</thead>
        <tr>
          <td>
            <div class="title">Fecha L&iacute;mite de Pago</div>
            <div class="content">{{$propuesta_fecha_limite_pago}}</div>
          </td>
          <td colspan="2">
            <div class="title">Referencia de pago (L&iacute;nea de Captura SIPARE)</div>
            <div class="content">{{$linea_captura_sipare}}</div>
            <div class="content">Puedes pagar en tu portal bancario o descargarla desde SIPARE.</div>
          </td>
        </tr>
        <tr>
          <td colspan="1">
            <div class="title">S.M.G.D.F</div>
            <div class="content">{{$propuesta_s_m_g_d_f}}</div>
          </td>
          <td>
            <div class="title">Fecha SAL. MIN.</div>
            <div class="content">{{$propuesta_fecha_salario_minimo_pago}}</div>
          </td>
          <td>
            <div class="title">Valor UMA</div>
            <div class="content">{{$propuesta_valor_uma}}</div>
          </td>
        </tr>
        <tr>
          <td colspan="1">
            <div class="title">No. de cotizantes</div>
            <div class="content">{{$propuesta_num_de_cotizantes}}</div>
          </td>
          <td>
            <div class="title">No. de d&iacute;as a cotizar</div>
            <div class="content">{{$propuesta_num_dias_a_cotizar}}</div>
          </td>
          <td>
            <div class="title">No. de acreditados</div>
            <div class="content">{{$propuesta_num_de_acreditados}}</div>
          </td>
        </tr>
      </table>

    	<table class="main-table">
    		<thead>
    			<tr>
    				<th colspan="4">Informaci&oacute;n detallada del importe total de cuotas</th>
    			</tr>
          <tr>
            <th>Conceptos</th>
            <th>Cuotas Patronales ($)</th>
            <th>Cuotas Obreras ($)</th>
            <th>Suma Total ($)</th>
          </tr>
    		</thead>
    		<tbody>
          @foreach($cuotasDesglose as $item)
            <?php if($item['type'] === 'section'): ?>
              <tr class="bg-gray">
                <td colspan="4" class="determ_imp_title"><strong>{{ $item['label'] }}</strong></td>
              </tr>
            <?php endif; ?>
            <?php if($item['type'] === 'label'): ?>
              <tr class="bg-gray">
                <td colspan="3" class="determ_imp_title"><strong>{{ $item['label'] }}</strong></td>
              </tr>
            <?php endif; ?>
            <?php if($item['type'] === 'label_aport'): ?>
              <tr class="bg-gray">
                <td></td>
                <td class="amount">APORTACIONES PATRONALES</td>
                <td class="amount">AMORTIZACI&Oacute;N DE CR&Eacute;DITO</td>
                <td></td>
              </tr>
            <?php endif; ?>

            <?php if($item['type'] === 'input'): ?>
              <tr class="bg-gray">
                <td class="determ_imp_label">{{ $item['label'] }}</td>
                <td class="amount">{{ $item['patronal'] }}</td>
                <td class="amount">{{ $item['obrera'] }}</td>
                <td class="amount">{{ $item['total'] }}</td>
              </tr>
            <?php endif; ?>

            <?php if($item['type'] === 'subtotal'): ?>
              <tr class="bg-gray">
                <td class="determ_imp_title">{{ $item['label'] }}</td>
                <td class="amount_total">{{ $item['patronal'] }}</td>
                <td class="amount_total">{{ $item['obrera'] }}</td>
                <td class="amount_total">{{ $item['total'] }}</td>
              </tr>
            <?php endif; ?>
          @endforeach
    		</tbody>

        <tfoot>
          <tr class="row-totals">
            <td class="determ_imp_title"><strong>TOTAL A PAGAR</strong></td>
            <td class="determ_imp_totales"><strong>{{$cuotas_patronales}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$cuotas_obreras}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$cuotas_totales}}</strong></td>
          </tr>
        </tfoot>

    	</table>

      <div class="title">Observaciones / Comentarios</div>
      <div class="observaciones">
        {{$observaciones}}
      </div>

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