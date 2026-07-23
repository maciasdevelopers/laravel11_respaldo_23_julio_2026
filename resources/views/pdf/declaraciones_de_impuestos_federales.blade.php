<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $folio_imp_fed }}</title>
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
              <div style="font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">REPORTE DE DECLARACIONES SOBRE IMPUESTOS FEDERALES</div>
              <div style="font-size: 14pt; font-weight: bold; color: #1a202c; margin-top: 2px;">{{ $folio_imp_fed }} </div>
              <div style="font-size: 7pt; color: #94a3b8; margin-top: 5px;">EMITIDO EL: {{ date('d M, Y H:i') }} </div>
            </div>
          </td>
        </tr>
      </table>
    </header>
    <br>
    <main>
      <table class="info-table">
    		<tr>
          <td>
            <div class="title">Fecha Contabilizaci&oacute;n</div>
            <div class="content">{{$declaracion_fecha_contabilizacion}}</div>
          </td>
          <td colspan="2">
            <div class="title">Tipo de declaraci&oacute;n</div>
            <div class="content">{{$declaracion_tipo}}</div>
          </td>
          <td>
            <div class="title">Periodicidad</div>
            <div class="content">{{$declaracion_periodicidad}}</div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="title">Ejercicio</div>
            <div class="content">{{$declaracion_ejercicio}}</div>
          </td>
          <td colspan="2">
            <div class="title">Periodo</div>
            <div class="content">{{$declaracion_periodo}}</div>
          </td>
          <td>
            <div class="title">Fecha de presentaci&oacute;n</div>
            <div class="content">{{$declaracion_fecha_presentacion}}</div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="title">Medio de presentaci&oacute;n</div>
            <div class="content">{{$declaracion_medio_presentacion}}</div>
          </td>
          <td>
            <div class="title">Vencimiento obligaci&oacute;n</div>
            <div class="content">{{$declaracion_fecha_vencimiento}}</div>
          </td>
          <td>
            <div class="title">Versi&oacute;n</div>
            <div class="content">{{$declaracion_version}}</div>
          </td>
          <td>
            <div class="title">Numero de operaci&oacute;n</div>
            <div class="content">{{$declaracion_numero_operacion}}</div>
          </td>
        </tr>
        <tr>
          <td colspan="4">
            <div class="title">L&iacute;nea de captura</div>
            <div class="content">{{$declaracion_linea_de_captura}}</div>
          </td>
        </tr>
      </table>

    	<table class="main-table">
    		<thead>
    			<tr>
    				<th colspan="8">Impuestos que declara</th>
    			</tr>
          <tr>
            <th>Concepto de pago</th>
            <th>Importe a favor</th>
            <th>A cargo</th>
            <th>Actualizaciones</th>
            <th>Recargos</th>
            <th>Otros cargos</th>
            <th>Otros abonos</th>
            <th>Cantidad a pagar</th>
          </tr>
    		</thead>
    		<tbody>
          @foreach($desglose_dec as $item)
            <tr class="bg-gray">
              <td class="determ_imp_title"><strong>{{ $item['concepto_pago_name'] }}</strong></td>
              <td class="amount">{{ $item['importe_a_favor'] }}</td>
              <td class="amount">{{ $item['a_cargo'] }}</td>
              <td class="amount">{{ $item['actualizaciones'] }}</td>
              <td class="amount">{{ $item['recargos'] }}</td>
              <td class="amount">{{ $item['otros_cargos'] }}</td>
              <td class="amount">{{ $item['otros_abonos'] }}</td>
              <td class="amount">{{ $item['cantidad_a_pagar'] }}</td>
            </tr>
          @endforeach
    		</tbody>

        <tfoot>
          <tr class="row-totals">
            <td class="determ_imp_title"><strong>TOTAL A PAGAR</strong></td>
            <td class="determ_imp_totales">{{$calculo_importe_a_favor}}</td>
            <td class="determ_imp_totales"><strong>{{$calculo_total_a_cargo}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$calculo_total_actualizaciones}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$calculo_total_recargos}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$calculo_total_otros_cargos}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$calculo_total_otros_abonos}}</strong></td>
            <td class="determ_imp_totales"><strong>{{$calculo_total_cantidad_a_pagar}}</strong></td>
          </tr>
        </tfoot>

    	</table>

      <div class="title">Observaciones / Comentarios</div>
      <div class="observaciones">
        {{$declaracion_observaciones}}
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