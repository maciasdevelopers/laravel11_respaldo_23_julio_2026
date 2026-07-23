$(document).ready(function(){
    function soloNumeros(e){
        var key = e.charCode;
        console.log(key);
        return key >= 48 && key <= 57;
    };

    var media = window.matchMedia("(max-width: 400px)");
    var strFilter = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]*$/;
    var filtroPorc= /^[0-9.%]*$/;
    var filtroCuota  = /^[0-9.,$]*$/;

    var regisImpTab = document.getElementById("regisImpTab");
    var tokenImpuesto = document.getElementById("tokenImpuesto");
    var inputImpuesto = document.getElementById("inputImpuesto"); 
    var lisTipoImpuesto = document.getElementById("lisTipoImpuesto");
    var divImpuestos = document.getElementById("divImpuestos");
    var aliasImp = document.getElementById("aliasImp");
    var tipoSelectImp = document.getElementById("tipoSelectImp");
    var subTipoSelectImp = document.getElementById("subTipoSelectImp");
    var importCatImp = document.getElementById("importCatImp");
    var btnRegImpu = document.getElementById("btnRegImpu");
    var btnDeleteImpu = document.getElementById("btnDeleteImpu");

    $(lisTipoImpuesto).click(function(){
        if (divImpuestos.classList.contains("noneView")) {
            divImpuestos.classList.remove("noneView");
            regisImpTab.classList.add("regisImpTab");
        } else {
            divImpuestos.classList.add("noneView");
            regisImpTab.classList.remove("regisImpTab");
        }
    });

    $("#impuFedTabla").on("click","td input#selectFilaImpuFed", function(){
        tokenImpuesto.value = this.value;
        var parentImp = $(this).parents("tr").find("td").eq(0).html();
        inputImpuesto.value = parentImp;
        $(aliasImp).removeAttr('disabled');  
        $(btnDeleteImpu).removeAttr('disabled'); 
    });

    $("#impuEstatalesTabla").on("click", "td input#selectFilaEstatales", function() {
        tokenImpuesto.value = this.value;
        var parentImpEst = $(this).parents("tr").find("td").eq(0).html();
        inputImpuesto.value = parentImpEst;
        $(aliasImp).removeAttr('disabled');
        $(btnDeleteImpu).removeAttr('disabled');
    });

    $("#impuLocalesTabla").on("click", "td input#selectFilaLocales", function() {
        tokenImpuesto.value = this.value;
        var parentImpLoc = $(this).parents("tr").find("td").eq(0).html();
        inputImpuesto.value = parentImpLoc;
        $(aliasImp).removeAttr('disabled');
        $(btnDeleteImpu).removeAttr('disabled');
    });

    $(aliasImp).keyup(function(){
        if (this.value === '' || !strFilter.test(this.value) || this.value.length < 4) {
            this.classList.add("error");
            $(tipoSelectImp).attr("disabled",true);
            $('select').material_select();
        } else {
            $(tipoSelectImp).removeAttr('disabled');
            $('select').material_select();
            this.classList.remove("error");
        }
    });

    $(tipoSelectImp).change(function(){
        var inpSlect = $(this).parent("div").find("input.select-dropdown");
        if (this.value == '' || !strFilter.test(this.value)) {
            inpSlect.addClass("error");
            $(subTipoSelectImp).attr("disabled",true);
            $('select').material_select();
        } else {
            inpSlect.removeClass("error");
            $(subTipoSelectImp).removeAttr('disabled');
            $('select').material_select();
        }
    });

    $(subTipoSelectImp).change(function(){
        var inpSlect = $(this).parent("div").find("input.select-dropdown");
        if (this.value == '' || !strFilter.test(this.value)) {
            inpSlect.addClass("error");
            $(importCatImp).attr("disabled",true);
        } else {
            inpSlect.removeClass("error");
            $(importCatImp).removeAttr('disabled');
        }
    });
    
    $(importCatImp).keyup(function(){
        if (subTipoSelectImp.value == 'cuota') {
            if (this.value === '' || !filtroCuota.test(this.value)) {
                this.classList.add("error");
                $(btnRegImpu).attr("disabled",true);
            } else {
                this.classList.remove("error");
                $(btnRegImpu).removeAttr('disabled');
            }
        }

        if (subTipoSelectImp.value == 'porcentaje') {
            if (this.value === '' || !filtroPorc.test(this.value)) {
                this.classList.add("error");
                $(btnRegImpu).attr("disabled",true);
            } else {
                this.classList.remove("error");
                $(btnRegImpu).removeAttr('disabled');
            }
        }
    });

    $(importCatImp).change(function(){
        if (subTipoSelectImp.value == 'porcentaje') {
            if (this.value === '' || !filtroPorc.test(this.value)) {
                this.classList.add("error");
            } else {
                this.classList.remove("error");
                this.value = this.value+"%";
            }
        }

        if (subTipoSelectImp.value == 'cuota') {
            if (this.value === '' || !filtroCuota.test(this.value)) {
                this.classList.add("error");
            } else {
                this.classList.remove("error");
                var nuMoneda = numeral(this.value);
                this.value = nuMoneda.format('$0,0.00');
                console.log(nuMoneda.format('$0,0.00'));
            }
        }
    });

    $(importCatImp).bind('keypress',function(event){
        var clave = String.fromCharCode(!event.charCode ? event.which : event.charCode);
        if (!filtroNum.test(clave)) {
            event.preventDefault();
            return false;
        }
    });

    $(btnRegImpu).click(function(){
        if ((tokenImpuesto.value != '') &&
            (aliasImp.value != '' && strFilter.test(aliasImp.value) && aliasImp.value.length >= 4) &&
            (tipoSelectImp.value != '' && strFilter.test(tipoSelectImp.value)) &&
            (subTipoSelectImp.value != '' && strFilter.test(subTipoSelectImp.value)) && 
            (importCatImp.value != '')) {
            
                if (subTipoSelectImp.value == 'cuota') {
                    if (filtroCuota.test(importCatImp.value)) {
                        enviaDataImp(tokenImpuesto,aliasImp,tipoSelectImp,subTipoSelectImp,importCatImp);
                    } else {
                        importCatImp.classList.add("error");
                    }
                }
        
                if (subTipoSelectImp.value == 'porcentaje') {
                    if (filtroPorc.test(importCatImp.value)) {
                        enviaDataImp(tokenImpuesto,aliasImp,tipoSelectImp,subTipoSelectImp,importCatImp);
                    } else {
                        importCatImp.classList.add("error");
                    }
                }

                function enviaDataImp(impuestoTab,aliasImp,tipoSelectImp,subTipoSelectImp,importeImpu){
                    var mensajeAct = confirm("¿Desea registrar este impuesto?");
                        if (mensajeAct) {
                            var partData = $(this).closest('div').serialize();
                            var data = new FormData();
                            data.append('data',partData);
                            data.append('impuestoTab',impuestoTab.value);
                            data.append('aliasImp',aliasImp.value);
                            data.append('tipoSelectImp',tipoSelectImp.value);
                            data.append('subTipoSelectImp',subTipoSelectImp.value);
                            data.append('importeImpu',importeImpu.value);
                            $.ajax({
                                url: 'ingresos-registraimpuestos', //clientessos-ingresos-actualizaCliente
                                type: "post",
                                data: data,
                                datatype: 'json',
                                processData: false,
                                contentType: false,
                                success: function (respuesta) {
                                    console.log(respuesta)
                                    if (respuesta == 'registrado') {
                                        var $toastContent = $('<div class="btnCorrecto">impuesto registrado</div>');
                                        Materialize.toast($toastContent,5000);
                                        location.reload();
                                    } else {
                                        var $toastContent = $('<div class="btnError">operacion no realizada, intente nuevamente o comuniquese con soporte</div>');
                                        Materialize.toast($toastContent,5000);
                                    }
                                }
                            });
                        }
                }

        } else {
            if (tokenImpuesto == ''){
            }

            if (aliasImp.value === '' || !strFilter.test(aliasImp.value) || aliasImp.value.length < 4) {
                aliasImp.classList.add("error");
            }

            if (tipoSelectImp.value == '' || !strFilter.test(tipoSelectImp.value)) {
                var inpSlect = $(tipoSelectImp).parent("div").find("input.select-dropdown");
                inpSlect.addClass("error");
            }

            if (subTipoSelectImp.value == '' || !strFilter.test(subTipoSelectImp.value)) {
                var inpSlect = $(subTipoSelectImp).parent("div").find("input.select-dropdown");
                inpSlect.addClass("error");
            }

            if (importCatImp.value == '') {
                importCatImp.classList.add("error");
            } else {
                if (subTipoSelectImp.value == 'cuota') {
                    if (!filtroCuota.test(importCatImp.value)) {
                        importCatImp.classList.add("error");
                    }
                }
        
                if (subTipoSelectImp.value == 'tarifa') {
                    if (!filtroPorc.test(importCatImp.value)) {
                        importCatImp.classList.add("error");
                    }
                }
            }
        }
    });


    /*modal*/
    $("#modImpTab").on("click", "td a#btnViewImpu",function(){
        var vToken = $(this).parents("tr").find("td").eq(0).html();
        $.ajax({
            url: 'ingresos-modalviewimpuestos',
            type: 'POST',
            datatype: 'html',
            data: {tokenImpuesto: vToken},
        })
        .done(function(respuesta){
            $("#dataImpView").html(respuesta);
        })
        .fail(function(){
        console.log("error");
        })
    });


    

});
