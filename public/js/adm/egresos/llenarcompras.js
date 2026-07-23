$(document).ready(function () {
    $('.modal').modal();
    $('.tooltipped').tooltip();

        //Compras
            //Por previa requisicion
            //Compra por previa cotizacion
            //Compra directa
                var collectionCDirecta = document.getElementById("collectionCDirecta");
                $("#table-listaprod").on("click","th input#selectAllCompraDirecta",function(){
                    var mensaje = confirm("¿Desea seleccionar todas las partidas de compra?");
                    if (mensaje) {
                        $("#collectionCDirecta tr").remove();
                        this.setAttribute('disabled','disabled');
                        $("#table-listaprod tbody #selected").each(function(){
                            var selectFilaProdCompraDirecta = $(this).find("#selectFilaProdCompraDirecta");
                            selectFilaProdCompraDirecta.attr('disabled','disabled');
                            tipo =  $(this).find("td").eq(0).text();
                            unidad = $(this).find("td").eq(1).text();
                            unidadSAT = $(this).find("td").eq(2).text();
                            claveProd = $(this).find("td").eq(3).text();
                            concepto = $(this).find("td").eq(4).text();
                            valor = $(this).find("td").eq(5).text();
                            rellenaUl(collectionCDirecta);
                        });
                    }
                });
            
                $("#table-listaprod").on("click","td input#selectFilaProdCompraDirecta",function(){
                    var selectFilaProdCDir = $(this).parent().find('#selectFilaProdCompraDirecta');
                    if (selectFilaProdCDir.attr('disabled','disabled')) {
                        //ul
                            liprodInfo.classList.remove("active");
                            //headProdInfo.classList.remove("active");
                            //headProdInfo.classList.remove("headerError");
                            //bodyProdInfo.classList.remove("activeViewError");
                    
                        //compra
                            //licompraDirecta.classList.remove("active");
                            //headCompraDirecta.classList.remove("active");
                            headCompraDirecta.classList.remove("headerError");
                            bodyCompraDirecta.classList.remove("activeViewError");
                            selectFilaProdCDir.attr('disabled','disabled');
                    
                        //table
                            tipo =  $(this).parents("tr").find("td").eq(0).text();
                            unidad = $(this).parents("tr").find("td").eq(1).text();
                            unidadSAT = $(this).parents("tr").find("td").eq(2).text();
                            claveProd = $(this).parents("tr").find("td").eq(3).text();
                            concepto = $(this).parents("tr").find("td").eq(4).text();
                            valor = $(this).parents("tr").find("td").eq(5).text();
                        rellenaUl(collectionCDirecta);
                    }
                });

            //Llenado ce compras 
                //variables 
                    var tipo = '';
                    var unidad = '';
                    var unidadSAT = '';
                    var claveProd = '';
                    var concepto = '';
                    var valor = '';
                    var cont = 0;
                
                    var formProdCompra = document.getElementById("formProdCompra");
                    var encIput = $(this).parent().find("label[for=txtcImpuestosBuy]");
                    var subtotal = document.getElementById("subtotal");
                    var totalDescuento = document.getElementById("totalDescuento");
                    var iva = document.getElementById("iva");
                    var ivaRetenido = document.getElementById("ivaRetenido");
                    var impEspeciales = document.getElementById("impEspeciales");
                    var total = document.getElementById("total");

                //tipoCompra
                    var liprodInfo = document.getElementById("liprodInfo");
                    var headProdInfo = document.getElementById("headProdInfo");
                    var bodyProdInfo = document.getElementById("bodyProdInfo");
                    var ulProdCompras = document.getElementById("ulProdCompras");

                //directa
                    var licompraDirecta = document.getElementById("licompraDirecta");
                    var headCompraDirecta = document.getElementById("headCompraDirecta");
                    var bodyCompraDirecta = document.getElementById("bodyCompraDirecta");

                //arreglos
                    var arrayUMedida = ["Pieza","Elemento","Unidad de Servicio","Actividad","Kilogramo","Trabajo"
                        ,"Tarifa","Metro","Paquete a granel","Caja base","Kit","Conjunto"
                        ,"Litro","Caja","Mes","Hora","Metro cuadrado","Equipos","Miligramo"
                        ,"Paquete","Kit (Conjunto de piezas)","Variedad","Gramo","Par"
                        ,"Docenas de piezas","Unidad","Día","Lote","Grupos","Mililitro","Viaje"];

                    var arrayListaIva = ["NA","16%","11%","8%","0%","EXCENTO"];
                    var arrayIva = [];
                    var arrayRetenciones = [];
                    var arrayTotalRet = [];
                    var arrayTotalIesp = [];
                    var arrayTableImpEsp = ['concepto','importe'];

                
                function rellenaUl(tbodyRelleno){
                    //table 
                        $("#noRegistros").remove();
                        let renglon = document.createElement('tr');
                        tbodyRelleno.appendChild(renglon); 
                        for (let i = 0; i < 12; i++) {
                            if (i == 0) {
                                let celda = document.createElement('td');
                                var inputUnidad = document.createElement("input");
                                inputUnidad.classList.add("validate"); 
                                inputUnidad.setAttribute("id","txtcTipo"); 
                                inputUnidad.setAttribute("readonly","readonly"); 
                                inputUnidad.setAttribute("name","txtcTipoBuy[]"); 
                                inputUnidad.setAttribute("type","text"); 
                                inputUnidad.setAttribute("value",tipo);
                                celda.appendChild(inputUnidad);
                                renglon.appendChild(celda);
                            }
                            if (i == 1) {
                                let celda = document.createElement('td');
                                var inputUnidad = document.createElement("input");
                                inputUnidad.classList.add("validate"); 
                                inputUnidad.setAttribute("id","txtcUnidad"); 
                                inputUnidad.setAttribute("readonly","readonly"); 
                                inputUnidad.setAttribute("name","txtcUnidadBuy[]"); 
                                inputUnidad.setAttribute("type","text"); 
                                inputUnidad.setAttribute("value",unidad);
                                celda.appendChild(inputUnidad);
                                renglon.appendChild(celda);
                            }
                            if (i == 2) {
                                let celda = document.createElement('td');
                                var datalistMed = document.createElement("datalist");
                                datalistMed.setAttribute("id","dtlistUMed");
                                celda.appendChild(datalistMed);
            
                                for (let i = 0; i < arrayUMedida.length; i++) {
                                    var optionMed = document.createElement("option");
                                    optionMed.value = arrayUMedida[i];
                                    datalistMed.appendChild(optionMed);
                                } 
                                
                                var inputUnidadMed = document.createElement("input");
                                inputUnidadMed.classList.add("validate"); 
                                inputUnidadMed.setAttribute("list","dtlistUMed"); 
                                inputUnidadMed.setAttribute("name","txtcUnidadSATBuy[]"); 
                                inputUnidadMed.setAttribute("type","text"); 
                                inputUnidadMed.setAttribute("value",unidadSAT);
                                celda.appendChild(inputUnidadMed);
                                renglon.appendChild(celda);
                            }
                            if (i == 3) {
                                let celda = document.createElement('td');
                                var inputClaveProd = document.createElement("input");
                                inputClaveProd.classList.add("validate"); 
                                inputClaveProd.setAttribute("name","txtcClaveProdBuy[]"); 
                                inputClaveProd.setAttribute("type","text"); 
                                inputClaveProd.setAttribute("id","txtcClaveProd"); 
                                inputClaveProd.setAttribute("readonly","readonly"); 
                                inputClaveProd.setAttribute("value",claveProd);
                                celda.appendChild(inputClaveProd);
                                renglon.appendChild(celda);
                            }
                            if (i == 4) {
                                let celda = document.createElement('td');
                                var inputConceptoDir = document.createElement("input");
                                inputConceptoDir.classList.add("validate"); 
                                inputConceptoDir.setAttribute("name","txtcConceptoBuy[]"); 
                                inputConceptoDir.setAttribute("type","text"); 
                                inputConceptoDir.setAttribute("id","txtcConcepto"); 
                                inputConceptoDir.setAttribute("readonly","readonly"); 
                                inputConceptoDir.setAttribute("value",concepto);
                                celda.appendChild(inputConceptoDir);
                                renglon.appendChild(celda);
                            }
                            if (i == 5) {
                                let celda = document.createElement('td');
                                var inputValor = document.createElement("input");
                                inputValor.classList.add("validate"); 
                                inputValor.classList.add("txtcValorBuy");
                                inputValor.setAttribute("id","txtcValorBuy"+cont);
                                inputValor.setAttribute("name","txtcValorBuy[]"); 
                                inputValor.setAttribute("type","number"); 
                                inputValor.setAttribute("value",valor);
                                celda.appendChild(inputValor);
                                renglon.appendChild(celda);
                            }
                            if (i == 6) {
                                let celda = document.createElement('td');
                                var input = '<input class="txtcDescuentoBuy validate" type="number" name="txtcDescuentoBuy[]" id="txtcDescuento'+cont+'" value="0.00" required>';
                                celda.innerHTML = input;
        
                                renglon.appendChild(celda);
                            }
                            if (i == 7) {
                                let celda = document.createElement('td');
                                var inputCantidad = document.createElement("input");
                                inputCantidad.classList.add("txtcCantidadDir");
                                inputCantidad.classList.add("validate"); 
                                inputCantidad.setAttribute("name","txtcCantidadBuy[]"); 
                                inputCantidad.setAttribute("type","number"); 
                                inputCantidad.setAttribute("id","txtcCantidadDir"+cont); 
                                inputCantidad.setAttribute("value",0);
                                celda.appendChild(inputCantidad);
        
                                //var input = '<input class="txtcCantidadDir validate" type="number" name="txtcCantidadBuy[]" id="txtcCantidadDir'+cont+'" value="0" required>';
                                //celda.innerHTML = input;
        
                                renglon.appendChild(celda);
                            }
                            if (i == 8) {
                                let celda = document.createElement('td');
                                var datalistIva = document.createElement("datalist");
                                datalistIva.setAttribute("id","dtlistImp");
                                celda.appendChild(datalistIva);
                                for (let i = 0; i < arrayListaIva.length; i++) {
                                    var optionIva = document.createElement("option");
                                    optionIva.value = arrayListaIva[i];
                                    datalistIva.appendChild(optionIva);
                                } 
                                arrayIva.push(0);
                                var inputIva = document.createElement("input");
                                inputIva.classList.add("validate"); 
                                inputIva.classList.add("txtcImpuestosBuy"); 
                                inputIva.setAttribute("id","txtcImpuestosBuy"+cont);
                                inputIva.setAttribute("list","dtlistImp"); 
                                inputIva.setAttribute("name","txtcImpuestosBuy[]"); 
                                inputIva.setAttribute("type","text"); 
                                celda.appendChild(inputIva);
                                renglon.appendChild(celda);
                            }
                            if (i == 9) {
                                let celda = document.createElement('td');
                                var descMas = document.createElement("a"); 
                                descMas.classList.add("btn"); 
                                descMas.classList.add("btn-floating");
                                descMas.classList.add("tooltipped"); 
                                descMas.classList.add("descMas");
                                descMas.classList.add("modal-trigger"); 
                                descMas.setAttribute("id","descMas"+cont);  
                                descMas.setAttribute("href","#modalDescMas"+cont);
                                descMas.setAttribute("data-position","left"); 
                                descMas.setAttribute("data-tooltip","RETENCIONES");
                                descMas.innerHTML = '&#xf090;';
                                celda.appendChild(descMas);
        
                                var modalRetenciones = document.createElement("div");
                                modalRetenciones.classList.add("modal"); 
                                modalRetenciones.classList.add("modalDialog");
                                modalRetenciones.setAttribute("id","modalDescMas"+cont);
                                modalRetenciones.setAttribute("style","z-index: 1055;"); 
                                celda.appendChild(modalRetenciones);
        
                                var contenido = document.createElement("div");
                                contenido.classList.add("contenido"); 
                                modalRetenciones.appendChild(contenido);
        
                                var header = document.createElement("div");
                                header.classList.add("header");
                                contenido.appendChild(header);
        
                                var contHeader = '<a class="modal-close btn btn-floating waves-effect waves-light red darken-2">&#xf011;</a>'+
                                            '<h4>&#xf07a; RETENCIONES</h4>';
                                header.innerHTML = contHeader;
        
                                var modal_content = document.createElement("div");
                                modal_content.classList.add("modal-content");
                                contenido.appendChild(modal_content);
        
                                var tabla = document.createElement("table");
                                tabla.classList.add("striped");
                                tabla.classList.add("centered");
        
                                var contenTabla = '<thead><tr><th>SUBTOTAL</th><th>IVA TOTAL</th></tr></thead><tbody>'+
                                    '<tr><td><input type="hidden" id="txtContModalCont'+cont+'" value="'+cont+'">'+
                                    '<input class="validate txtSubtotalModal" type="number" name="" id="txtSubtotalModal'+cont+'" readonly></td>'+
                                    '<td><input class="validate txtSubtotalModal" type="number" name="" id="txtIvaModal'+cont+'" readonly></td></tr></tbody>';
                                tabla.innerHTML = contenTabla;
                                modal_content.appendChild(tabla);
        
                                var tablaRet = document.createElement("table");
                                tablaRet.classList.add("striped");
                                tablaRet.classList.add("centered");
        
                                var thead = document.createElement("thead");
                                var cthead = '<tr class="ultimo"><th></th><th>RETENCION</th><th>VALOR</th></tr></thead>';
                                thead.innerHTML = cthead;
                                tablaRet.appendChild(thead);
                                modal_content.appendChild(tablaRet);
        
                                var tbody = document.createElement("tbody");
                                tbody.setAttribute("id","tbRet"+cont);
                                tablaRet.appendChild(tbody);
                                
                                var trRet = document.createElement("tr");
                                trRet.setAttribute("v-for","mRet of amodalRet");
        
        
                                for (let j = 0; j < 3; j++) {
                                    if(j == 0){
                                        var tdRet1 = document.createElement("td");
        
                                        var btnIsrRetservProf = document.createElement("a");
                                        btnIsrRetservProf.classList.add("btn");
                                        btnIsrRetservProf.classList.add("btnRetencion");
                                        btnIsrRetservProf.classList.add("btnDisabled");
                                        btnIsrRetservProf.setAttribute(":id","mRet.btn1");
                                        btnIsrRetservProf.innerHTML = "+";
                                        tdRet1.appendChild(btnIsrRetservProf);
            
                                        var btnDelIsrRetservProf = document.createElement("a"); 
                                        btnDelIsrRetservProf.classList.add("noneView");
                                        btnDelIsrRetservProf.classList.add("btn");
                                        btnDelIsrRetservProf.classList.add("delete");
                                        btnDelIsrRetservProf.classList.add("btnDisabled");
                                        btnDelIsrRetservProf.setAttribute(":id","mRet.btn2");
                                        btnDelIsrRetservProf.innerHTML = "-";
                                        tdRet1.appendChild(btnDelIsrRetservProf);
            
                                        trRet.appendChild(tdRet1);
                                    }
                                    if(j == 1){
                                        var tdRet2 = document.createElement("td");
                                        var tdTexto = document.createTextNode("{{mRet.texto}}");
                                        tdRet2.appendChild(tdTexto);
                                        trRet.appendChild(tdRet2);
        
                                    }
                                    if(j == 2){
                                        var tdRet3 = document.createElement("td");
        
                                        var txtImporteIsrRetservProf = document.createElement("input");
                                        txtImporteIsrRetservProf.classList.add("validate"); 
                                        txtImporteIsrRetservProf.setAttribute(":id","mRet.input"); 
                                        txtImporteIsrRetservProf.setAttribute("readonly","readonly"); 
                                        txtImporteIsrRetservProf.setAttribute("type","number"); 
                                        tdRet3.appendChild(txtImporteIsrRetservProf);
        
                                        trRet.appendChild(tdRet3);
                                    } 
                                }
                            
                                for (let ret = 0; ret < 7; ret++) {
                                    arrayRetenciones[ret] = 0;
                                }
        
                                var trRet2 = document.createElement("tr");
                                var lineaTotal = '<td></td><td class="tdGuardar"><a id="btnGuardarRet'+cont+'" class="btn btnGuardar btnGuardarRet">Guardar</a></td><td><input id="importeTotalRet'+cont+'" readonly="readonly" type="number" class="validate"></td>';
                                trRet2.innerHTML = lineaTotal;
                                tbody.appendChild(trRet);
                                tbody.appendChild(trRet2);
        
                                $(modalRetenciones).modal();
                                renglon.appendChild(celda);
                            }
                            if (i == 10) {
                                let celda = document.createElement('td');
                                var descMenos = document.createElement("a"); 
                                descMenos.classList.add("btn"); 
                                descMenos.classList.add("btn-floating");
                                descMenos.classList.add("tooltipped"); 
                                descMenos.classList.add("descMas");
                                descMenos.classList.add("modal-trigger"); 
                                descMenos.setAttribute("id","descMenos"+cont);  
                                descMenos.setAttribute("href","#modalDescMenos"+cont);
                                descMenos.setAttribute("data-position","right"); 
                                descMenos.setAttribute("data-tooltip","IMPUESTOS ESPECIALES");
                                descMenos.innerHTML = '&#xf090;';
                                celda.appendChild(descMenos);
        
                                var modalEspeciales = document.createElement("div");
                                modalEspeciales.classList.add("modal"); 
                                modalEspeciales.classList.add("modalDialog");
                                modalEspeciales.setAttribute("id","modalDescMenos"+cont);
                                modalEspeciales.setAttribute("style","z-index: 1055;"); 
                                celda.appendChild(modalEspeciales);
        
                                var contenido = document.createElement("div");
                                contenido.classList.add("contenido"); 
                                contenido.setAttribute("style","width: 50%;");
                                modalEspeciales.appendChild(contenido);
        
                                var header = document.createElement("div");
                                header.classList.add("header");
                                contenido.appendChild(header);
        
                                var contHeader = '<a class="modal-close btn btn-floating waves-effect waves-light red darken-2">&#xf011;</a>'+
                                            '<h4>&#xf07a; IMPUESTOS ADICIONALES</h4>';
                                header.innerHTML = contHeader;
        
                                var modal_content = document.createElement("div");
                                modal_content.classList.add("modal-content");
                                contenido.appendChild(modal_content);
        
                                var tablaIesp = document.createElement("table");
                                tablaIesp.classList.add("striped");
                                tablaIesp.classList.add("centered");
                                tablaIesp.setAttribute("id","tableImpuestosAdicionales"+cont);
        
                                var thead = document.createElement("thead");
                                var cthead = '<tr><th><input type="hidden" id="txtModalMenCont'+cont+'" value="'+cont+'">CONCEPTO</th><th>IMPORTE</th><th class="ultimo"></th></tr></thead>';
                                thead.innerHTML = cthead;
                                tablaIesp.appendChild(thead);
                                modal_content.appendChild(tablaIesp);
        
                                var tbody = document.createElement("tbody");
                                tbody.setAttribute("id","tbIesp"+cont);
                                tablaIesp.appendChild(tbody);
                                
                                var trIesp = document.createElement("tr");
                                tbody.appendChild(trIesp);
        
                                for (let j = 0; j < 3; j++) {
                                    var td = document.createElement("td");
                                    var inputIEsp = document.createElement("input");
                                    inputIEsp.classList.add("validate");  
                                    if(j == 0){
                                        inputIEsp.setAttribute("id","itxtIConcepto"); 
                                        inputIEsp.setAttribute("name","itxtIConcepto[]"); 
                                        inputIEsp.setAttribute("type","text");
                                        td.appendChild(inputIEsp);
                                        trIesp.appendChild(td);
                                    }
                                    if(j == 1){
                                        inputIEsp.classList.add("txtImporteIesp");  
                                        inputIEsp.setAttribute("id","txtIeImporteIesp"); 
                                        inputIEsp.setAttribute("name","txtIeImporteIesp[]"); 
                                        inputIEsp.setAttribute("type","number");
                                        td.appendChild(inputIEsp);
                                        trIesp.appendChild(td);
                                    }
                                    if(j == 2){
                                        var btnPrimerImp = document.createElement("a");   
                                        btnPrimerImp.classList.add("waves-effect"); 
                                        btnPrimerImp.classList.add("btn");
                                        btnPrimerImp.classList.add("cargaImpAdd");
                                        btnPrimerImp.innerHTML = "+";
                                        td.appendChild(btnPrimerImp);
                                        trIesp.appendChild(td);
                                    }
                                }
        
                                var tbody2 = document.createElement("tbody");
                                tablaIesp.appendChild(tbody2);
        
                                var contenttb2 = '<tr><td>IMPORTE TOTAL</td><td><input id="txtTotalImpEsp'+cont+'" readonly="readonly" type="number" class="txtTotalImpEsp'+cont+' validate"></td><td><a class="btn btnGuardarIesp">&#xf0c7;</a></td></tr>';
                                tbody2.innerHTML = contenttb2;
        
                                $(modalEspeciales).modal();
                                renglon.appendChild(celda);
        
                            }
                            if (i == 11) {
                                let celda = document.createElement('td');
                                var inputImporte = document.createElement("input");
                                inputImporte.classList.add("validate"); 
                                inputImporte.classList.add("txtcImporteBuy");
                                inputImporte.setAttribute("type","number"); 
                                inputImporte.setAttribute("id","txtcImporteBuy"+cont);
                                celda.appendChild(inputImporte);
                                renglon.appendChild(celda);
                            }
                        }
        
                        const app= new Vue({
                            el:"#tbRet"+cont,
                            data:{
                                amodalRet:[
                                    {btn1:'btnIsrRetservProf'+cont+'',btn2:'btnDelIsrRetservProf'+cont+'',texto:'isr retenido para servicios profesionales (10% del subtotal)',input:'importeIsrRetservProf'+cont+''},
                                    {btn1:'btnIsrRetarrendamiento'+cont+'',btn2:'btnDelIsrRetarrendamiento'+cont+'',texto:'isr retenido arrandamiento (10% del subtotal)',input:'importeIsrRetarrendamiento'+cont+''},
                                    {btn1:'btnIvaRetServProf'+cont+'',btn2:'btnDelIvaRetServProf'+cont+'',texto:'iva retenido servicios profesionales (2/3 del iva)',input:'importeIvaRetServProf'+cont+''},
                                    {btn1:'btnIvaRetarrendamiento'+cont+'',btn2:'btnDelIvaRetarrendamiento'+cont+'',texto:'iva retenido por arrendamiento (2/3 del iva)',input:'importeIvaRetarrendamiento'+cont+''},
                                    {btn1:'btnIvaRetfletes'+cont+'',btn2:'btnDelIvaRetfletes'+cont+'',texto:'iva retenido por fletes (4% del subtotal)',input:'importeIvaRetfletes'+cont+''},
                                    {btn1:'btnIvaRetcomisiones'+cont+'',btn2:'btnDelIvaRetcomisiones'+cont+'',texto:'iva retenido por comisiones (6% del subtotal)',input:'importeIvaRetcomisiones'+cont+''},
                                    {btn1:'btnIvaRetoutsoursing'+cont+'',btn2:'btnDelIvaRetoutsoursing'+cont+'',texto:'iva retenido por outsoursing, empleados, terceros (8% del subtotal)',input:'importeIvaRetoutsoursing'+cont+''},
                                ]
                            }
                        });
                        cont++;
        
                    //funcionalidad
        
                        var findtxtCont = $("#table-listCompraDireca").find("#txtContModalCont"+(cont-1)).val();
                        var txtcontador = findtxtCont;
                        var findtxtcValorBuy = $("#table-listCompraDireca").find("#txtcValorBuy"+txtcontador);
                        var findtxtcDescuentoBuy = $("#table-listCompraDireca").find("#txtcDescuento"+txtcontador);
                        var txtcCantidadDir = $("#table-listCompraDireca").find("#txtcCantidadDir"+txtcontador);
                        var findtxtcImpuestosBuy = $("#table-listCompraDireca").find("#txtcImpuestosBuy"+txtcontador);
                        var registroSub = $("#table-listCompraDireca").find("#txtSubtotalModal"+txtcontador);
        
                        var descMas = $("#table-listCompraDireca").find("#descMas"+txtcontador);
                        var btnIsrRetservProf = $("#table-listCompraDireca").find("#btnIsrRetservProf"+txtcontador);
                        var btnDelIsrRetservProf = $("#table-listCompraDireca").find("#btnDelIsrRetservProf"+txtcontador);
                        var importeIsrRetservProf = $("#table-listCompraDireca").find("#importeIsrRetservProf"+txtcontador);
        
                        var btnIsrRetarrendamiento = $("#table-listCompraDireca").find("#btnIsrRetarrendamiento"+txtcontador);
                        var btnDelIsrRetarrendamiento = $("#table-listCompraDireca").find("#btnDelIsrRetarrendamiento"+txtcontador);
                        var importeIsrRetarrendamiento = $("#table-listCompraDireca").find("#importeIsrRetarrendamiento"+txtcontador);
        
                        var btnIvaRetServProf = $("#table-listCompraDireca").find("#btnIvaRetServProf"+txtcontador);
                        var btnDelIvaRetServProf = $("#table-listCompraDireca").find("#btnDelIvaRetServProf"+txtcontador);
                        var importeIvaRetServProf = $("#table-listCompraDireca").find("#importeIvaRetServProf"+txtcontador);
                        
                        var btnIvaRetarrendamiento = $("#table-listCompraDireca").find("#btnIvaRetarrendamiento"+txtcontador);
                        var btnDelIvaRetarrendamiento = $("#table-listCompraDireca").find("#btnDelIvaRetarrendamiento"+txtcontador);
                        var importeIvaRetarrendamiento = $("#table-listCompraDireca").find("#importeIvaRetarrendamiento"+txtcontador);
        
                        var btnIvaRetfletes = $("#table-listCompraDireca").find("#btnIvaRetfletes"+txtcontador);
                        var btnDelIvaRetfletes = $("#table-listCompraDireca").find("#btnDelIvaRetfletes"+txtcontador);
                        var importeIvaRetfletes = $("#table-listCompraDireca").find("#importeIvaRetfletes"+txtcontador);
        
                        var btnIvaRetcomisiones = $("#table-listCompraDireca").find("#btnIvaRetcomisiones"+txtcontador);
                        var btnDelIvaRetcomisiones = $("#table-listCompraDireca").find("#btnDelIvaRetcomisiones"+txtcontador);
                        var importeIvaRetcomisiones = $("#table-listCompraDireca").find("#importeIvaRetcomisiones"+txtcontador);
        
                        var btnIvaRetoutsoursing = $("#table-listCompraDireca").find("#btnIvaRetoutsoursing"+txtcontador);
                        var btnDelIvaRetoutsoursing = $("#table-listCompraDireca").find("#btnDelIvaRetoutsoursing"+txtcontador);
                        var importeIvaRetoutsoursing = $("#table-listCompraDireca").find("#importeIvaRetoutsoursing"+txtcontador);
        
                        var findimporteTotalRet = $("#table-listCompraDireca").find("#importeTotalRet"+txtcontador);
                        var registroIva = $("#table-listCompraDireca").find("#txtIvaModal"+txtcontador);
                        
                        var descMenos = $("#table-listCompraDireca").find("#descMenos"+txtcontador); 
                        var tbodyIesp = $("#table-listCompraDireca").find("#tbIesp"+txtcontador);
                        var findtxtTotalImpEsp = $("#table-listCompraDireca").find("#txtTotalImpEsp"+txtcontador);
                                                                                     
                        var findtxtcImporte = $("#table-listCompraDireca").find("#txtcImporteBuy"+txtcontador);
        
                        //$("#table-listCompraDireca").on("keyup","td input#txtcCantidadDir"+txtcontador,function(){
                        //    if (this.value != '' && this.value != 0) {
                        //        this.classList.remove("error");
                        //        llenaTodo();
                        //    } else {
                        //        this.classList.add("error");
                        //    }
                        //});
        
                        $("#table-listCompraDireca").on("keyup","td input",function(){
                            if (this.value != '' && this.value != 0) {
                                this.classList.remove("error");
                                llenaTodo();
                            } else {
                                this.classList.add("error");
                            }
                        });
        
                        //modal retenciones
                            //btnIsrRetservProf         
                                $("#table-listCompraDireca").on("click","td a#btnIsrRetservProf"+txtcontador,function(){
                                    var resIsrRetservProf = registroSub.val() * parseFloat(0.10);
                                    importeIsrRetservProf.val(resIsrRetservProf.toFixed(2));
                                    arrayRetenciones[0] = resIsrRetservProf;
                                    btnDelIsrRetservProf.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIsrRetservProf"+txtcontador,function(){
                                    arrayRetenciones[0] = 0;
                                    importeIsrRetservProf.val("");
                                    btnIsrRetservProf.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                            //btnIsrRetarrendamiento
                                $("#table-listCompraDireca").on("click","td a#btnIsrRetarrendamiento"+txtcontador,function(){
                                    var resIsrRetarrendamiento = registroSub.val() * parseFloat(0.10);
                                    importeIsrRetarrendamiento.val(resIsrRetarrendamiento.toFixed(2));
                                    arrayRetenciones[1] = resIsrRetarrendamiento;
                                    btnDelIsrRetarrendamiento.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIsrRetarrendamiento"+txtcontador,function(){
                                    arrayRetenciones[1] = 0;
                                    importeIsrRetarrendamiento.val("");
                                    btnIsrRetarrendamiento.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                            //btnIvaRetServProf
                                $("#table-listCompraDireca").on("click","td a#btnIvaRetServProf"+txtcontador,function(){
                                    var resIvaRetServProf = registroIva.val() * parseFloat((2/3));
                                    importeIvaRetServProf.val(resIvaRetServProf.toFixed(2));
                                    arrayRetenciones[2] = resIvaRetServProf;
                                    btnDelIvaRetServProf.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIvaRetServProf"+txtcontador,function(){
                                    arrayRetenciones[2] = 0;
                                    importeIvaRetServProf.val("");
                                    btnIvaRetServProf.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                }); 
            
                            //btnIvaRetarrendamiento
                                $("#table-listCompraDireca").on("click","td a#btnIvaRetarrendamiento"+txtcontador,function(){
                                    var resIvaRetarrendamiento = registroIva.val() * parseFloat((2/3));
                                    importeIvaRetarrendamiento.val(resIvaRetarrendamiento.toFixed(2));
                                    arrayRetenciones[3] = resIvaRetarrendamiento;
                                    btnDelIvaRetarrendamiento.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIvaRetarrendamiento"+txtcontador,function(){
                                    arrayRetenciones[3] = 0;
                                    importeIvaRetarrendamiento.val("");
                                    btnIvaRetarrendamiento.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                            //btnIvaRetfletes
                                $("#table-listCompraDireca").on("click","td a#btnIvaRetfletes"+txtcontador,function(){
                                    var resIvaRetfletes = registroSub.val() * parseFloat(0.04);
                                    importeIvaRetfletes.val(resIvaRetfletes.toFixed(2));
                                    arrayRetenciones[4] = resIvaRetfletes;
                                    btnDelIvaRetfletes.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIvaRetfletes"+txtcontador,function(){
                                    arrayRetenciones[4] = 0;
                                    importeIvaRetfletes.val("");
                                    btnIvaRetfletes.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                            //btnIvaRetcomisiones
                                $("#table-listCompraDireca").on("click","td a#btnIvaRetcomisiones"+txtcontador,function(){
                                    var resIvaRetcomisiones = registroSub.val() * parseFloat(0.06);
                                    arrayRetenciones[5] = resIvaRetcomisiones;
                                    importeIvaRetcomisiones.val(resIvaRetcomisiones.toFixed(2));
                                    btnDelIvaRetcomisiones.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIvaRetcomisiones"+txtcontador,function(){
                                    arrayRetenciones[5] = 0;
                                    importeIvaRetcomisiones.val("");
                                    btnIvaRetcomisiones.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                            //btnIvaRetoutsoursing
                                $("#table-listCompraDireca").on("click","td a#btnIvaRetoutsoursing"+txtcontador,function(){
                                    var resIvaRetoutsoursing = registroSub.val() * parseFloat(0.08);
                                    arrayRetenciones[6] = resIvaRetoutsoursing;
                                    importeIvaRetoutsoursing.val(resIvaRetoutsoursing.toFixed(2));
                                    btnDelIvaRetoutsoursing.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                $("#table-listCompraDireca").on("click","td a#btnDelIvaRetoutsoursing"+txtcontador,function(){
                                    arrayRetenciones[6] = 0;
                                    importeIvaRetoutsoursing.val("");
                                    btnIvaRetoutsoursing.removeClass("noneView");
                                    $(this).addClass("noneView");
                                    recorreArrayRet();
                                });
        
                                function recorreArrayRet(){
                                    var sumatotalRet = 0;
                                    for (let i = 0; i < arrayRetenciones.length; i++) {
                                        sumatotalRet = parseFloat(sumatotalRet) + parseFloat(arrayRetenciones[i]);
                                    }
                                    findimporteTotalRet.val(sumatotalRet.toFixed(2));
                                    llenaTodo();
                                }
                            //btnGuardarRet
                                $("#table-listCompraDireca").on("click","td a#btnGuardarRet"+txtcontador,function(){
                                    var datImporte = parseFloat(registroSub.val()) + parseFloat(registroIva.val());
                                    if (findtxtTotalImpEsp.val() != '') {
                                        var restaretenciones = parseFloat(datImporte) - parseFloat(findimporteTotalRet.val()) + parseFloat(findtxtTotalImpEsp.val()); 
                                    } else {
                                        var restaretenciones = parseFloat(datImporte) - parseFloat(findimporteTotalRet.val()) + 0; 
                                    }
                                    findtxtcImporte.val(restaretenciones.toFixed(2));
                                
                                    arrayTotalRet[txtcontador] = findimporteTotalRet.val();
                                    llenaTodo();
                                    descMas.addClass("btnCorrecto");
                                });
        
                        //modal Adicionales
                            $("#tableImpuestosAdicionales"+txtcontador).on("click","td a.cargaImpAdd",function(){
                                var findItxtIConcepto = $(this).parent("td").parent("tr").find("#itxtIConcepto");
                                var findIeImporteIesp = $(this).parent("td").parent("tr").find("#txtIeImporteIesp");   
        
                                if (findItxtIConcepto.val() != '' && findIeImporteIesp.val() != '') {
                                    var sumaImporteIesp = 0;
                                    $(tbodyIesp).find("tr td input.txtImporteIesp").each(function(){
                                        sumaImporteIesp += parseFloat(this.value);
                                    });
                                    sumaImporteIesp = sumaImporteIesp.toFixed(2);
                                    findtxtTotalImpEsp.val(sumaImporteIesp);
                                    var mensaje = confirm("¿Desea agregar otro concepto para Impuesto?");
                                    if (mensaje) {
                                        var nuevoTr = document.createElement("tr");
                                        tbodyIesp.append(nuevoTr);
                                        for (let j = 0; j < 3; j++) {
                                            var td = document.createElement("td");
                                            var inputIEsp = document.createElement("input");
                                            inputIEsp.classList.add("validate");  
                                            if(j == 0){
                                                inputIEsp.setAttribute("id","itxtIConcepto"); 
                                                inputIEsp.setAttribute("name","itxtIConcepto[]"); 
                                                inputIEsp.setAttribute("type","text");
                                                td.appendChild(inputIEsp);
                                                nuevoTr.appendChild(td);
                                            }
                                            if(j == 1){
                                                inputIEsp.classList.add("txtImporteIesp");  
                                                inputIEsp.setAttribute("id","txtIeImporteIesp"); 
                                                inputIEsp.setAttribute("name","txtIeImporteIesp[]"); 
                                                inputIEsp.setAttribute("type","number");
                                                td.appendChild(inputIEsp);
                                                nuevoTr.appendChild(td);
                                            }
                                            if(j == 2){
                                            var btnPrimerImp = document.createElement("a");   
                                            btnPrimerImp.classList.add("waves-effect"); 
                                            btnPrimerImp.classList.add("btn");
                                            btnPrimerImp.classList.add("cargaImpAdd");
                                            btnPrimerImp.innerHTML = "+";
                                            td.appendChild(btnPrimerImp);
                                            nuevoTr.appendChild(td);
                                            }
                                        }
                                    }
                                }else{
                                    if (findItxtIConcepto.val() == '') {
                                        findItxtIConcepto.addClass("error");
                                    }
                                    if (findIeImporteIesp.val() == '') {
                                        findIeImporteIesp.addClass("error");
                                    }
                                }
                            });
                        
                            $("#tableImpuestosAdicionales"+txtcontador).on("click","td a.btnGuardarIesp",function(){
                                if (findtxtcValorBuy.val() != '' && findtxtcDescuentoBuy.val() != '' && txtcCantidadDir.val() != '' && 
                                    findtxtcImpuestosBuy.val() != '' && findtxtTotalImpEsp.val() != '') {
                                    alert(findtxtTotalImpEsp.val());
                                    arrayTotalIesp[txtcontador] = parseFloat(findtxtTotalImpEsp.val());
                                    descMenos.addClass("btnCorrecto");
                                    llenaTodo();  
                                } else {
                                    labelcDescuentoBuy.addClass("errorlabel");   
                                }
                            
                            });
        
                        function llenaTodo(){
                            var impuesto = 0;
                            let sumaTotal = 0;  
        
                            if (findtxtcValorBuy.val() != '' && findtxtcDescuentoBuy.val() != '' && txtcCantidadDir.val() != '' ) {
                                var subtotal = parseFloat(parseFloat(findtxtcValorBuy.val())-parseFloat(findtxtcDescuentoBuy.val()))*parseFloat(txtcCantidadDir.val());
                                subtotal.toFixed(2);
                                registroSub.val(subtotal);
        
                                //btnIsrRetservProf
                                    if (importeIsrRetservProf.val() != '') {
                                        var resIsrRetservProf = subtotal * parseFloat(0.10);
                                        arrayRetenciones[0] = resIsrRetservProf;
                                        importeIsrRetservProf.val(resIsrRetservProf.toFixed(2));
                                        var sumatotalRet = 0;
                                        for (let i = 0; i < arrayRetenciones.length; i++) {
                                            sumatotalRet = parseFloat(sumatotalRet) + parseFloat(arrayRetenciones[i]);
                                        }
                                        findimporteTotalRet.val(sumatotalRet.toFixed(2));
                                    }
        
                                //btnIsrRetarrendamiento
                                    if (importeIsrRetarrendamiento.val() != '') {
                                        var resIsrRetarrendamiento = subtotal * parseFloat(0.10);
                                        arrayRetenciones[1] = resIsrRetarrendamiento;
                                    
                                        importeIsrRetarrendamiento.val(resIsrRetarrendamiento.toFixed(2));
                                        var sumatotalRet = 0;
                                        for (let i = 0; i < arrayRetenciones.length; i++) {
                                            sumatotalRet = parseFloat(sumatotalRet) + parseFloat(arrayRetenciones[i]);
                                        }
                                        findimporteTotalRet.val(sumatotalRet.toFixed(2));
                                    }
        
                                //btnIvaRetfletes
                                    if (importeIvaRetfletes.val() != '') {
                                        var resIvaRetfletes = subtotal * parseFloat(0.04);
                                        arrayRetenciones[4] = resIvaRetfletes;
                                        importeIvaRetfletes.val(resIvaRetfletes.toFixed(2));
                                        var sumatotalRet = 0;
                                        for (let i = 0; i < arrayRetenciones.length; i++) {
                                            sumatotalRet = parseFloat(sumatotalRet) + parseFloat(arrayRetenciones[i]);
                                        }
                                            findimporteTotalRet.val(sumatotalRet.toFixed(2));
                                        }
                                    
                                //btnIvaRetcomisiones
                                    if (importeIvaRetcomisiones.val() != '') {
                                        var resIvaRetcomisiones = subtotal * parseFloat(0.06);
                                        arrayRetenciones[5] = resIvaRetcomisiones;
                                    
                                        importeIvaRetcomisiones.val(resIvaRetcomisiones.toFixed(2));
                                        var sumatotalRet = 0;
                                        for (let i = 0; i < arrayRetenciones.length; i++) {
                                            sumatotalRet = parseFloat(sumatotalRet) + parseFloat(arrayRetenciones[i]);
                                        }
                                    
                                        findimporteTotalRet.val(sumatotalRet.toFixed(2));
                                    }
        
                                //btnIvaRetoutsoursing
                                    if (importeIvaRetoutsoursing.val() != '') {
                                        var resIvaRetoutsoursing = subtotal * parseFloat(0.08);
                                        arrayRetenciones[6] = resIvaRetoutsoursing;
                                        importeIvaRetoutsoursing.val(resIvaRetoutsoursing.toFixed(2));
                                        var sumatotalRet = 0;
                                        for (let i = 0; i < arrayRetenciones.length; i++) {
                                            sumatotalRet = parseFloat(sumatotalRet) + parseFloat(arrayRetenciones[i]);
                                        }
                                    
                                        findimporteTotalRet.val(sumatotalRet.toFixed(2));
                                    }
        
                                if (findtxtcImpuestosBuy.val() == "NA" || findtxtcImpuestosBuy.val() == "EXCENTO" || findtxtcImpuestosBuy.val() == "") {
                                    impuesto = subtotal * parseFloat(0);
                                    sumaTotal = subtotal + impuesto;
                                    sumaTotal = sumaTotal - findimporteTotalRet.val();
                                }
        
                                if (findtxtcImpuestosBuy.val() == "16%") {
                                    impuesto = subtotal * parseFloat(0.16);
                                    sumaTotal = subtotal + impuesto;
                                    sumaTotal = sumaTotal - findimporteTotalRet.val();
                                }
        
                                if (findtxtcImpuestosBuy.val() == "11%") {
                                    impuesto = subtotal * parseFloat(0.11);
                                    sumaTotal = subtotal + impuesto;
                                    sumaTotal = sumaTotal - findimporteTotalRet.val();
                                }
                                
                                if (findtxtcImpuestosBuy.val() == "8%") {
                                    impuesto = subtotal * parseFloat(0.08);
                                    sumaTotal = subtotal + impuesto;
                                    sumaTotal = sumaTotal - findimporteTotalRet.val();
                                }
                                
                                if (findtxtcImpuestosBuy.val() == "0%") {
                                    impuesto = subtotal * parseFloat(0.0);
                                    sumaTotal = subtotal + impuesto;
                                    sumaTotal = sumaTotal - findimporteTotalRet.val();
                                }   
        
                                arrayIva[txtcontador] = impuesto.toFixed(2);
                                registroIva.val(impuesto.toFixed(2));
        
                                if (findtxtTotalImpEsp.val() == '') {
                                    findtxtcImporte.val(sumaTotal.toFixed(2));
                                } else {
                                    sumaTotal = parseFloat(sumaTotal) + parseFloat(findtxtTotalImpEsp.val()); 
                                    findtxtcImporte.val(sumaTotal.toFixed(2));
                                }
                                sumaImporteFunct();    
                            } else {
                                labelcDescuentoBuy.addClass("errorlabel");   
                            }
                        }
        
        
                        function soloLetras(e){
                            window.console.log(e.charCode)
                            if ((e.charCode < 97 || e.charCode > 122)//letras mayusculas
                                && (e.charCode < 65 || e.charCode > 90) //letras minusculas
                                && (e.charCode != 45) //retroceso
                                && (e.charCode != 241) //ñ
                                 && (e.charCode != 209) //Ñ
                                 && (e.charCode != 32) //espacio
                                 && (e.charCode != 225) //á
                                 && (e.charCode != 233) //é
                                 && (e.charCode != 237) //í
                                 && (e.charCode != 243) //ó
                                 && (e.charCode != 250) //ú
                                 && (e.charCode != 193) //Á
                                 && (e.charCode != 201) //É
                                 && (e.charCode != 205) //Í
                                 && (e.charCode != 211) //Ó
                                 && (e.charCode != 218) //Ú
                
                                )
                            return false;
                        }
                
                        function soloNumeros(e){
                            var key = e.charCode;
                            console.log(key);
                            return key >= 48 && key <= 57;
                        };
                
                        function actualizaTotalesRetSub(findtxtCont){
                            alert(findtxtCont);
                        }
                
                        function sumaImporteFunct(){
                            //subtotal
                                var arraytxtcValorBuy = document.getElementsByClassName("txtcValorBuy");  
                                var arraytxtcCantidadDir = document.getElementsByClassName("txtcCantidadDir");  
                                var sumaSubtotal = 0; 
                
                            //descuento
                                var arraytxtcDescuentoBuy = document.getElementsByClassName("txtcDescuentoBuy");
                                var sumaDescuento = 0;
                            //iva
                                var sumaIva = 0;
                
                            //retenciones
                                var sumaRetenciones = 0;
                
                            //impuestos especiales
                                var sumaImpEsp = 0;
                
                            //importe
                                var arraytxtcImporteBuy = document.getElementsByClassName("txtcImporteBuy"); 
                                var importe = 0;
                            
                            for (let i = 0; i < arraytxtcValorBuy.length; i++) {
                                //subtotal
                                    if (!isNaN(parseFloat(arraytxtcValorBuy[i].value)) && !isNaN(parseFloat(arraytxtcCantidadDir[i].value))) {
                                        var valsubtotal = parseFloat(arraytxtcValorBuy[i].value) * parseFloat(arraytxtcCantidadDir[i].value);
                                    } else {
                                        var valsubtotal = 0;
                                    }
                                    sumaSubtotal += valsubtotal;
                
                                //descuento
                                    if (!isNaN(parseFloat(arraytxtcDescuentoBuy[i].value)) && !isNaN(parseFloat(arraytxtcCantidadDir[i].value))) {
                                        var valDescuento = parseFloat(arraytxtcDescuentoBuy[i].value) * parseFloat(arraytxtcCantidadDir[i].value);
                                    } else {
                                        var valDescuento = 0;
                                    }
                                    sumaDescuento += valDescuento;
                
                                //iva
                                    var valIva = parseFloat(arrayIva[i]);
                                    sumaIva += valIva; 
                
                                //retenciones
                                    if (!isNaN(parseFloat(arrayTotalRet[i]))){
                                        var valRetenciones = parseFloat(arrayTotalRet[i]);
                                    } else {
                                        var valRetenciones = parseFloat(0);
                                    }
                                    sumaRetenciones += valRetenciones;
        
                                //impuestos especiales
                                    if (!isNaN(parseFloat(arrayTotalIesp[i]))){
                                        var valImpEsp = parseFloat(arrayTotalIesp[i]);
                                    } else {
                                        var valImpEsp = parseFloat(0);
                                    }
                                    sumaImpEsp += valImpEsp;
                
                                //importe
                                    if (!isNaN(parseFloat(arraytxtcImporteBuy[i].value))) {
                                        var valimporte = parseFloat(arraytxtcImporteBuy[i].value);
                                    } else {
                                        var valimporte = 0;
                                    }
                                    importe += valimporte;
                            }
                            //mostrar resultados}
                                //subtotal
                                    sumaSubtotal = sumaSubtotal.toFixed(2);
                                    subtotal.innerHTML = sumaSubtotal;
                
                                //descuento
                                    sumaDescuento = sumaDescuento.toFixed(2);
                                    totalDescuento.innerHTML = sumaDescuento;
                
                                //iva
                                    sumaIva = sumaIva.toFixed(2);
                                    iva.innerHTML = sumaIva;
                
                                //retenciones
                                    sumaRetenciones = sumaRetenciones.toFixed(2);
                                    ivaRetenido.innerHTML = sumaRetenciones;
                
                                //impuestos especiales
                                    sumaImpEsp = sumaImpEsp.toFixed(2);
                                    impEspeciales.innerHTML = sumaImpEsp; 
                                    
                                //importe
                                    importe = importe.toFixed(2);
                                    total.innerHTML = importe;
                        }
                }
 
});