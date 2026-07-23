<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $folio_compra }}</title>
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
        text-align:center;
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
        text-align: right!important; 
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
                  <div style="font-size: 14pt; font-weight: bold; color: #1a202c;">SOLUCIONES OPORTUNAS SIMPLES G&A </div>
                  <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Sinergia Administrativa y Protecci&oacute;n Patrimonial </div>
                </td>
              </tr>
            </table>
          </td>
          <td width="35%" align="right" style="vertical-align: middle;">
            <div style="padding: 10px; border-radius: 4px;">
              <div style="font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">REPORTE DE COMPRA </div>
              <div style="font-size: 14pt; font-weight: bold; color: #1a202c; margin-top: 2px;">{{ $folio_compra }} </div>
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
            <div class="title">Fecha de contabilizaci&oacute;n</div>
            <div class="content">{{$fecha_contabilizacion}}</div>
          </td>
          <td colspan="2">
            <div class="title">Proveedor</div>
            <div class="content">{{$proveedor_folio}} {{$proveedor_nombre}}</div>
          </td>
          <td>
            <div class="title">Compra a cr&eacute;dito</div>
            <div class="content">{{$compra_a_credito}} Fecha de vencimiento: {{$fecha_vencimiento}}</div>
          </td>
          <td>
            <div class="title">Moneda</div>
            <div class="content">{{$compra_moneda}}</div>
          </td>
        </tr>
      </table>

      <table class="main-table">
   			<thead>
					<tr>
						<th>Subtotal</th>
						<th>Descuento</th>
						<th>Retenciones</th>
						<th>Traslados</th>
						<th>Total</th>
					</tr>
				</thead>
				<tbody>
					<tr>
					  <td class="amount">{{$compra_subtotal}}</td>
					  <td class="amount">{{$compra_descuento}}</td>
					  <td class="amount">{{$compra_retenciones}}</td>
					  <td class="amount">{{$compra_traslados}}</td>
					  <td class="amount">{{$importe_total_compra}}</td>
					</tr>
				</tbody>
      </table>

      <div class="explain">
        <h6 class="title">CFDI</h6>
      </div>

      <table class="main-table">
   			<thead>
					<tr>
						<th>Aplica recepci&oacute;n de facturas</th>
            <th>recibi&oacute; Factura</th>
            <th>Versi&oacute;n</th>
            <th>Serie</th>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Forma de pago</th>
            <th>M&eacute;todo de pago</th>
            <th>Subtotal</th>
            <th>Moneda</th>
            <th>Tipo de cambio</th>
            <th>Total</th>
            <th>Confirmaci&oacute;n</th>
            <th>Tipo de comprobante</th>
            <th>Fecha de timbrado</th>
            <th>UUID</th>
					</tr>
				</thead>
				<tbody>
					<tr>
					  <td>{{$aplica_recepcion_facturas}}</td>
					  <td>{{$recibeFactura}}</td>
					  <td>{{$cfdi_comprobante_version}}</td>
					  <td>{{$cfdi_comprobante_serie}}</td>
					  <td>{{$cfdi_comprobante_folio}}</td>
					  <td>{{$cfdi_comprobante_fecha}}</td>
					  <td>{{$cfdi_comprobante_forma_de_pago}}</td>
					  <td>{{$cfdi_comprobante_metodo_de_pago}}</td>
					  <td class="amount">{{$cfdi_comprobante_subtotal}}</td>
					  <td>{{$cfdi_comprobante_moneda}}</td>
					  <td class="amount">{{$cfdi_comprobante_tipo_de_cambio}}</td>
					  <td class="amount">{{$cfdi_comprobante_total}}</td>
            <td>{{$cfdi_comprobante_confirmacion}}</td>
					  <td>{{$cfdi_comprobante_tipo_de_comprobante}}</td>
					  <td>{{$cfdi_complementoFechaTimbrado}}</td>
					  <td>{{$cfdi_complementoUUID}}</td>
					</tr>
				</tbody>
      </table>

      <div class="explain">
        <h6 class="title">Orden de recepci&oacute;n</h6>
      </div>

      <table class="main-table">
   			<thead>
					<tr>
						<th>Articulos recibidos</th>
            <th>Lugar de recepci&oacute;n</th>
            <th>Orden de recepci&oacute;n</th>
					</tr>
				</thead>
				<tbody>
					<tr>
					  <td>{{$articulos_recibidos}}</td>
					  <td>{{$lugarRecepcionComplete}}</td>
					  <td>{{$folio_orden_recepcion}}</td>
					</tr>
				</tbody>
      </table>

      <div class="explain">
        <h6 class="title">Orden de pago</h6>
      </div>

      <table class="main-table">
   			<thead>
					<tr>
						<th>Orden de pago</th>
            <th>Pago realizado</th>
					</tr>
				</thead>
				<tbody>
					<tr>
					  <td>{{$folio_orden_pago == '---' ? '---' : $folio_orden_pago." ".$fecha_contabilizacion_orden_pago}}</td>
					  <td>{{$pagos_realizados_folio == '---' ? '---' : $pagos_realizados_folio." ".$pagos_realizados_fecha_contabilizacion}}</td>
					</tr>
				</tbody>
      </table>

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