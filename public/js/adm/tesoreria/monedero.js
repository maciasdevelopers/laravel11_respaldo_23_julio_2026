$(document).ready(function() {
    var verTabVinculAcount = document.getElementById("verTabVinculAcount");
    //$(".switch").find("input[data-id=checkVinCuenta]").on("change",function() {
    //    let checked = $(this).prop('checked');
    //    if (checked == true) {
    //        verTabVinculAcount.classList.remove("noneView");
    //    } else {
    //        verTabVinculAcount.classList.add("noneView");
    //    }
    //})

    //traer lista de monedero
        (verMonedero());

        function verMonedero(referencia){
            $.ajax({
                url: "tesoreria-control-vmonedero",
                dataType: "html",
                type: "post",
                data: {referencia: referencia}
            }).done(function(response){
                $("#verCuentasMonedero").html(response);       
            }).fail(function(response){});
        }

        var txtbuscaCuentaMonedero = document.getElementById("txtbuscaCuentaMonedero");
        $(txtbuscaCuentaMonedero).keyup(function(){
                verMonedero(this.value);
        });

    //alta de monedero
        var filtroMonedero = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ-]*$/; 
        //buscador
            (buscaMonedero());
            function buscaMonedero(monedero){
                $.ajax({
                    type: "post",
                    url: "tesoreria-control-buscaMon",
                    data: {monedero: monedero},
                    dataType: "html"
                }).done(function(response){
                    $("#tbodyListaMonederos").html(response);
                })
                .fail(function(response){
                    $("#tbodyListaMonederos").html(response);
                });
            }

            var txtbuscaMonedero = document.getElementById("txtbuscaMonedero");
            $(txtbuscaMonedero).keyup(function(){
                var monedero = this.value;
                if (this.value != '') {
                    buscaMonedero(monedero);
                } else {
                    buscaMonedero();
                }
            });

        //validacion
            
            //No. de referencia
                var n_referenciaMon = document.getElementById("n_referenciaMon");
                $(n_referenciaMon).keyup(function(){
                    var valor = n_referenciaMon.value.length;
                    if (this.value === '' || !filtroMonedero.test(this.value) || valor >24) {
                       errorInput(this,'No. de referencia invalido'); 
                    } else {
                        correctoInput(this,'No. de referencia');
                        validacionGeneral();
                    }
                });

            //N. de cuenta
                var n_cuentaMon = document.getElementById("n_cuentaMon");
                $(n_cuentaMon).keyup(function(){
                    var valor = n_cuentaMon.value.length;
                    if (this.value === '' || !filtroMonedero.test(this.value) || valor>34) {
                        errorInput(this,'No. de cuenta invalida');
                    } else {
                        correctoInput(this,'No. de cuenta');
                        validacionGeneral();
                    }
                });

            //clave interbancaria
                var trHeadMonedero = document.getElementById("trHeadMonedero");
                var clabe_interMon = document.getElementById("clabe_interMon")
                $("#tablaListaMonedero").on("click","td input#selectBanco",function(){
                    trHeadMonedero.classList.remove("error");
                    var clabe = $(this).parents("tr").find("td").eq(1).text();
                    clabe_interMon.value = clabe+"-";
                    validacionGeneral();
                });

                //tabla 
                $("#tablaListaMonedero").on("click","td input#selectMonedero",function(){
                    trHeadMonedero.classList.remove("error");
                    clabe_interMon.value = "646-";
                    validacionGeneral();
                });

                $(clabe_interMon).keyup(function(){
                    var valor = clabe_interMon.value.length;
                    if (this.value === '' || !(/^[0-9-]+$/.test(this.value)) || valor>21) {
                        errorInput(this,'Clabe interbancaria invalida');
                    } else {
                        correctoInput(this,'Clabe interbancaria');
                        if (valor == 3 || valor == 7 || valor == 19) {
                            clabe_interMon.value = clabe_interMon.value +"-";
                        }
                        validacionGeneral();
                    }
                });

                $(clabe_interMon).keypress(function(e){
                    if (!soloNumeros(e)) {
                        e.preventDefault();
                    }
                });
            
            //Cliente
                $(n_clienteMon).keyup(function(){
                    var valor = n_clienteMon.value.length;
                    if (this.value === '' || !(/^[0-9]+$/.test(this.value)) || valor>20) {
                        errorInput(this,'Cliente invalido');
                    } else {
                        correctoInput(this,'Cliente');
                        validacionGeneral();
                    }
                });

                $(n_clienteMon).keypress(function(e){
                    if (!soloNumeros(e)) {
                        e.preventDefault();
                    }
                });

            //destino
                var tipo_cuentaMon = document.getElementById("tipo_cuentaMon");
                var lbltipo_cuentaMon = document.getElementById("lbltipo_cuentaMon");
                $(tipo_cuentaMon).change(function(){
                    if(tipo_cuentaMon.value != ''){
                        lbltipo_cuentaMon.classList.remove("errorlabel");
                        validacionGeneral();
                    } else {
                        lbltipo_cuentaMon.classList.add("errorlabel");
                    }
                });
            
            //verClabe
                $("#checkPassClabeMon").click(function(){
                    if (clabe_interMon.value != '') {
                         if (clabe_interMon.type === "password") {
                             clabe_interMon.type = "text";
                         } else {
                             clabe_interMon.type = "password";
                         }
                    } else {
                     errorInput(clabe_interMon,"registra clabe");
                    }
                });

            //inputs credito y debito
                var addManejosMon = document.getElementById("addManejosMon");
                var selectManejoMon = document.getElementById("selectManejoMon");
                var txtReferenciaMon = document.getElementById("txtReferenciaMon");
                var tbodyManejoMon = document.getElementById("tbodyManejoMon");
                var trVacioMon = document.getElementById("trVacioMon");

                $(addManejosMon).click(function(){
                    var responsableManejo = document.querySelector('input[name="responsableManejo"]:checked');
                    if (selectManejoMon.value != '' && txtReferenciaMon.value != '' && responsableManejo) {
                        var responsable = $(responsableManejo).parents("tr").find("td").eq(1).html();
                        trVacioMon.remove();
                        var nuevoMon  = document.createElement("tr");
                        var datosMon = '<input type="hidden" name="txtManejoMon[]" value="'+selectManejoMon.value+'">'+
                        '<input type="hidden" name="txtRerenciaMon[]" value="'+txtReferenciaMon.value+'">'+
                        '<input type="hidden" name="responsableManejo[]" value="'+responsableManejo.value+'">'+
                        '<td>'+selectManejoMon.value+'</td><td>'+txtReferenciaMon.value+'</td><td>'+responsable+'</td>';
                        nuevoMon.innerHTML = datosMon;
                        tbodyManejoMon.appendChild(nuevoMon);

                        selectManejoMon.selectedIndex = 0;
                        selectManejoMon.value = '';
                        $('#selectManejoMon').prop('readonly', false);
                        $('select').material_select();
                        txtReferenciaMon.value = '';
                    } else {
                        alert("vacios");
                    }
                });

            //cuentas bancarias
            (buscarCuentaBanco());
            function buscarCuentaBanco(busqueda){
                $.ajax({
                    type: "post",
                    url: "tesoreria-control-buscarcbank",
                    data: {cuenta:busqueda},
                    dataType: "html"
                })
                .done(function (response) {
                    $("#tbodyListaCuentBankM").html(response);
                });
            };
    
            var txtbusCuentaBancoM = document.getElementById("txtbusCuentaBancoM");
            $(txtbusCuentaBancoM).keyup(function(){
                if (this.value != '') {
                    if (this.value.length >= 3) {
                        buscarCuentaBanco(this.value);
                        lblbuscaBancoM.classList.remove("errorlabel");
                    } else {
                        lblbuscaBancoM.classList.add("errorlabel");
                    }
                } else {
                    buscarCuentaBanco();
                    lblbuscaBancoM.classList.remove("errorlabel");
                }
            });

            //action=""
                function validacionGeneral(){
                    var txtMonedero = document.querySelector('input[name="txtMonedero"]:checked');
                    if  (txtMonedero && n_referenciaMon.value !='' && n_cuentaMon.value !='' && clabe_interMon.value !='' 
                    && n_clienteMon.value !='' && tipo_cuentaMon.value != '' ) {
                        $("#btnGuardaMonedero").removeClass("noneView");
                    } else {
                        $("#btnGuardaMonedero").addClass("noneView");
                    }
                }; 

                $("#btnGuardaMonedero").click(function(){
                    var txtMonedero = document.querySelector('input[name="txtMonedero"]:checked');
                    if  (txtMonedero && n_referenciaMon.value !='' && n_cuentaMon.value !='' && clabe_interMon.value !='' 
                    && n_clienteMon.value !='' && tipo_cuentaMon.value != '' ) {
                        envio_fMon();
                    } else {
                        if (!txtMonedero) {
                            trHeadMonedero.classList.add("error");
                        }
                        if (tipo_cuentaMon.value == '') {
                            lbltipo_cuentaMon.classList.add("errorlabel");
                        }
                    }
                });

                function soloNumeros(e){
                    var key = e.charCode;
                    console.log(key);
                    return key >= 48 && key <= 57;
                };

                function errorInput(valor,mensaje){
                    var divParent = valor.parentElement;
                    var errorlbl = divParent.querySelector('label');
                        errorlbl.className = "activeInput errorlabel";
                        errorlbl.innerText = mensaje;
                };
            
                function correctoInput(valor,mensaje){
                    var divParent = valor.parentElement;
                    var correctlbl = divParent.querySelector('label');
                        correctlbl.className = "activeInput";
                        correctlbl.innerText = mensaje;
                };

                function envio_fMon(){
                    document.frm_monedero.target = "_self";
                    document.frm_monedero.action = "tesoreria-registra-monedero";
                    document.frm_monedero.submit();
                }

});