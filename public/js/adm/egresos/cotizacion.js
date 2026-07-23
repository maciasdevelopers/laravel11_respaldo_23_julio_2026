//const operacion = require("./requisicion.js");
$(document).ready(function(){
    //divs
        var btn_enviaCot = document.getElementById("btn-enviaCot");
        var btncot_imgconcepto = document.getElementById("");
        var cotReq = document.getElementById("cotReq");
        var cotDir = document.getElementById("cotDir");
        var dataRequisicion = document.getElementById("dataRequisicion");
        var describeCotizacion = document.getElementById("describeCotizacion");
        var tabla = document.getElementById("tabla");
        var proyectoReq = document.getElementById("proyectoReq"); 
        var detalleReq = document.getElementById("detalleReq");

    //inputs
        var cot_concepto = document.getElementById("cot_concepto");
        var cot_cantidad = document.getElementById("cot_cantidad");
        var cot_uMedida = document.getElementById("cot_uMedida");
        var cot_marca = document.getElementById("cot_marca");
        var cot_cambio = document.getElementById("cot_cambio");
        var cot_precio = document.getElementById("cot_precio");
        var cot_proveedor = document.getElementById("cot_proveedor");
        var cot_imgconcepto = document.getElementById("cot_imgconcepto")

    // botones
        var addDataCotizacion = document.getElementById("addDataCotizacion");
        var deleteDataCotizacion = document.getElementById("deleteDataCotizacion");

    //table tbody
        var tableConcepto = document.getElementById("tableConcepto");
        var tableCotix = document.getElementById("tableCotix");
        var camposVaciosConcpt = document.getElementById("camposVaciosConcpt");
        var bodyTableConcepto = document.getElementById("bodyTableConcepto");
        var bodyTableCotix = document.getElementById("bodyTableCotix");
        var camposVaciosCotiza = document.getElementById("camposVaciosCotiza");
    
    //select
        var tipoCotizacion = document.getElementById("tipoCotizacion");

    $(".viewCotizacion").click(function(){
        var requisicion = $(this).parent("div").find("#idHiddenCot").val();
        $.ajax({
            url:'egresos-buscaCotizacion',
            type: 'POST',
            datatype: 'html',
            data: {requisicion:requisicion}
        }).done(function(respuesta){
            $("#dataCotizacionView").html(respuesta);
        }).fail(function(respuesta){
            console.log("error")
        });
    });


    function revisaStatus(){
        $("#cotRequisicionesTeble tr").click(function(){
            
            if (this.classList.contains("revisado")) {
                this.classList.remove("revisado");
                this.classList.add("cotizado");
            }
        });
        
    }

    (buscaRpCot());
    function buscaRpCot(requisicion) {   
        $.ajax({
            url:'egresos-buscarequisicioncot',
            type: 'POST',
            datatype: 'html',
            data: {requisicion:requisicion}
        }).done(function(respuesta){
            $(detalleReq).html(respuesta);
        }).fail(function(respuesta){
            console.log("error")
        });
    }

    $(".cotRequisicionesTable").click(function(){
        var requisicion = $(this).find("#idHiddenReq").val();
        if (this.classList.contains("pendiente")) {
            this.classList.remove("pendiente");
            this.classList.add("revisado");
        }
        buscaRpCot(requisicion);
    });

    $("#table-cotizacionDirecta tr").click(function(){
        var cotizacion = $(this).find("td").eq(0).html();
        $.ajax({
            url:'egresos-buscaCotdirecta',
            type: 'POST',
            datatype: 'html',
            data: {cotizacion:cotizacion}
        }).done(function(respuesta){
            $("#dataCotizacionDirectaView").html(respuesta);
        }).fail(function(respuesta){
            console.log("error")
        });
    });

    $("#tableCotDirecta").on("change","td #cot_imgnecesidad",function(e){
        var valor = this.value;
        alert(valor);
        //var destino = $(this).parent("div").find("#divImgReq");
        //if (valor != '') {
        //    destino.removeClass("btnError");
        //    //operacion.llenarPdfImg(e,valor,destino);
        //} else {
        //    destino.addClass("btnError");
        //}
    });

    

    /*$(req_imgnecesidadCot).change(function(e){
        //objet de la clase FileReader
        let reader = new FileReader();
        //lectura de archivo subido y se lo pasamos al reader
        reader.readAsDataURL(e.target.files[0]);

        //cargar el archivo y mostrarlo en el html
        reader.onload = function(){
            let imaNecesidad = '<img class="circle responsive-img " src="'+reader.result+'">';
            //imaNecesidad.src = reader.result;
            divImgReq.innerHTML = imaNecesidad;
            //divImgReq.append(imaNecesidad);
        };
    });*/

    $(addDataCotizacion).click(function () {
        /*cot_concepto.value != '' &&*/ 
        if (cot_cantidad.value != '' && cot_uMedida.value != '' && cot_marca.value != '' &&
            cot_cambio.value != '' && cot_precio.value != '' && cot_proveedor.value != '') {
            
            var proveedor = cot_proveedor.value;
            array_proveedor = proveedor.split("-");

            camposVaciosCotiza.remove();
            let renglon = document.createElement('tr');
            bodyTableCotix.appendChild(renglon);
            //inputs
            var fila =  '<input class="" type="hidden" name="cotconcepto[]" value="'+cot_concepto.value+'">'+
                        '<input class="" type="hidden" name="cotcantidad[]" value="'+cot_cantidad.value+'">'+
                        '<input class="" type="hidden" name="cotmarca[]" value="'+cot_marca.value+'">'+
                        '<input class="" type="hidden" name="cotprecio[]" value="'+cot_precio.value+'">'+
                        '<input class="" type="hidden" name="cotproveedor[]" value="'+array_proveedor[0]+'">'+
                        //lista td
                        '<td>'+cot_concepto.value+'</td>'+'<td>'+cot_cantidad.value+'</td>'+
                        '<td>'+cot_marca.value+'</td>'+'<td>'+cot_precio.value+'</td>'+'<td>'+cot_proveedor.value+'</td>';
            renglon.innerHTML = fila;
            btn_enviaCot.classList.remove("noneView");
        } else {
            alert("no");
        }
    });

    $(tipoCotizacion).change(function(){
        if (tipoCotizacion.value == 1) {
            if (!dataRequisicion.classList.contains("noneView")) {
                dataRequisicion.classList.add("noneView");
                describeCotizacion.classList.add("noneView");
                tabla.classList.add("noneView");
                cotDir.classList.add("reduccion");

                cotReq.classList.remove("reduccion");
                proyectoReq.classList.remove("noneView");
                detalleReq.classList.remove("noneView");
            }
        }

        if (tipoCotizacion.value == 2) {
            if (dataRequisicion.classList.contains("noneView")) {
                proyectoReq.classList.add("noneView");
                detalleReq.classList.add("noneView");
                cotReq.classList.add("reduccion");
                cotDir.classList.remove("reduccion");
                dataRequisicion.classList.remove("noneView");
                describeCotizacion.classList.remove("noneView");
                tabla.classList.remove("noneView");
            }
        }
    });

});