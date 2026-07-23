$(document).ready(function(){
    $('.tabs').tabs();
    const menuBancos = new Vue({
        mounted(){
            $('.tooltipped').tooltip();
        },
        el: "#menuBanc",
        data:{
            tesoreriaMenu:[{imagen:'vista/media/adm/usuarios/tesoreria/bancos/bancos.jpg',
                            btn1:{id:'btnAbreCatBancos',letrero:'Catalogo de bancos y cuentas de ahorro',icon:'fas fa-clipboard-list'},
                            btn2:{id:'btnAbreAltaBancos',letrero:'Alta de bancos y cuentas de ahorro',icon:'fas fa-tasks'}},

                            {imagen:'vista/media/adm/usuarios/tesoreria/monedero/monedero.jpg',
                            btn1:{id:'btnAbreCatMon',letrero:'Catalogo de monederos electronicos y otros',icon:'fas fa-clipboard-list'},
                            btn2:{id:'btnAbreAltaMon',letrero:'Alta de monederos electronicos y otros',icon:'fas fa-tasks'}},

                            {imagen:'vista/media/adm/usuarios/tesoreria/caja/caja.jpg',
                            btn1:{id:'btnAbreCatCaja',letrero:'Catalogo de caja y efectivo',icon:'fas fa-clipboard-list'},
                            btn2:{id:'btnAbreAltaCaja',letrero:'Alta de caja y efectivo',icon:'fas fa-tasks'}},
                            
                            {imagen:'vista/media/adm/usuarios/tesoreria/dispositivos/dispositivos.jpg',
                            btn1:{id:'btnAbreCatDisp',letrero:'Catalogo de dispositivos',icon:'fas fa-clipboard-list'},
                            btn2:{id:'btnAbreAltaDisp',letrero:'Alta de dispositivos',icon:'fas fa-tasks'}},
            ]
        }
    });

    //menu catalogo
    var menuBanc = document.getElementById("menuBanc");
    var listas_ps = document.getElementById("listas_ps");
    var listaBancos = document.getElementById("listaBancos");
    var formBancos = document.getElementById("formBancos");
    var listaMon = document.getElementById("listaMon");
    var formMon = document.getElementById("formMon");
    var listaCaja = document.getElementById("listaCaja");
    var formCaja = document.getElementById("formCaja");
    var listaDisp = document.getElementById("listaDisp");
    var formDisp = document.getElementById("formDisp");

    //bancos
        //catalogo
            var btnAbreCatBancos = document.getElementById("btnAbreCatBancos");
            $(btnAbreCatBancos).click(function(){
                abreCatalogoBancos();
                listaBancos.classList.remove("noneView");
            });

        //alta
            var btnAbreAltaBancos = document.getElementById("btnAbreAltaBancos");
            $(btnAbreAltaBancos).click(function(){
                abreCatalogoBancos();
                formBancos.classList.remove("noneView");
            });

    //monedero electronico
        //catalogo
            var btnAbreCatMon = document.getElementById("btnAbreCatMon");
            $(btnAbreCatMon).click(function(){
                abreCatalogoBancos();
                listaMon.classList.remove("noneView");
            });

        //alta
            var btnAbreAltaMon = document.getElementById("btnAbreAltaMon");
            $(btnAbreAltaMon).click(function(){
                abreCatalogoBancos();
                formMon.classList.remove("noneView");
            });
        
    //caja y efectivo
        //catalogo
            var btnAbreCatCaja = document.getElementById("btnAbreCatCaja");
            $(btnAbreCatCaja).click(function(){
                abreCatalogoBancos();
                listaCaja.classList.remove("noneView");
            });

        //alta
            var btnAbreAltaCaja = document.getElementById("btnAbreAltaCaja");
            $(btnAbreAltaCaja).click(function(){
                abreCatalogoBancos();
                formCaja.classList.remove("noneView");
            });
    //dispositivos 
        //catalogo
            var btnAbreCatDisp = document.getElementById("btnAbreCatDisp");
            $(btnAbreCatDisp).click(function(){
                abreCatalogoBancos();
                listaDisp.classList.remove("noneView");
            });
        
        //alta
            var btnAbreAltaDisp = document.getElementById("btnAbreAltaDisp");
            $(btnAbreAltaDisp).click(function(){
                abreCatalogoBancos();
                formDisp.classList.remove("noneView");
            });
        


    function abreCatalogoBancos(){
        menuBanc.classList.add("menuCatReducido");
        listas_ps.classList.remove("noneView");
        listaBancos.classList.add("noneView");
        formBancos.classList.add("noneView");
        listaMon.classList.add("noneView");
        formMon.classList.add("noneView");
        listaCaja.classList.add("noneView");
        formCaja.classList.add("noneView");
        listaDisp.classList.add("noneView");
        formDisp.classList.add("noneView");
    }


    //alta de cuentas bancarias
        (buscarBanco());
        function buscarBanco(busqueda){
            $.ajax({
                type: "post",
                url: "tesoreria-control-buscaBanc",
                data: {clave:busqueda},
                dataType: "html"
            })
            .done(function (response) {
                $("#tbodyListabancos").html(response);
            });
        };

        var txtbuscaBanco = document.getElementById("txtbuscaBanco");
        var lblbuscaBanco = document.getElementById("lblbuscaBanco");
        $(txtbuscaBanco).keyup(function(){
            if (this.value != '') {
                if (this.value.length >= 3) {
                    buscarBanco(this.value);
                    lblbuscaBanco.classList.remove("errorlabel");
                } else {
                    lblbuscaBanco.classList.add("errorlabel");
                }
            } else {
                buscarBanco();
                lblbuscaBanco.classList.remove("errorlabel");
            }
        });

    //alta de bancos
        var filtroCuenta = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ-]*$/; 
        //traer lista de cuentas
        (verCuentas());

        function verCuentas(){
            $.ajax({
                url:'tesoreria-vcuentas',
                datatype:'html',
                type:'post',
            }).done(function(respuesta){
                $("#verCuentas").html(respuesta);
            }).fail(function(respuesta){});
        }
       
        //validacion
            //n. de contrato
                var n_contrato = document.getElementById("n_contrato");
                $(n_contrato).keyup(function(){
                    var valor = n_contrato.value.length;
                    if (this.value === '' || !filtroCuenta.test(this.value) || valor >24) {
                       errorInput(this,'No. de contrato invalido'); 
                    } else {
                        correctoInput(this,'No. de contrato');
                        validacionGeneral();
                    }
                });

            //n. de cuenta
                var n_cuenta = document.getElementById("n_cuenta");
                $(n_cuenta).keyup(function(){
                    var valor = n_cuenta.value.length;
                    if (this.value === '' || !filtroCuenta.test(this.value) || valor>34) {
                        errorInput(this,'No. de cuenta invalida');
                    } else {
                        correctoInput(this,'No. de cuenta');
                        validacionGeneral();
                    }
                });

            //clave interbancaria
                var trHeadBancos = document.getElementById("trHeadBancos");
                var clabe_inter = document.getElementById("clabe_inter")
                $("#tablaListabanco").on("click","td input#selectBanco",function(){
                    trHeadBancos.classList.remove("error");
                    var clabe = $(this).parents("tr").find("td").eq(1).text();
                    clabe_inter.value = clabe+"-";
                    validacionGeneral();
                });

                $(clabe_inter).keyup(function(){
                    var valor = clabe_inter.value.length;
                    if (this.value === '' || !(/^[0-9-]+$/.test(this.value)) || valor>21) {
                        errorInput(this,'Clabe interbancaria invalida');
                    } else {
                        correctoInput(this,'Clabe interbancaria');
                        if (valor == 3 || valor == 7 || valor == 19) {
                            clabe_inter.value = clabe_inter.value + "-";
                        }
                        validacionGeneral();
                    }
                });

                $(clabe_inter).keypress(function(e){
                    if (!soloNumeros(e)) {
                        e.preventDefault();
                    }
                }); 

            //n. de cliente
                $(n_cliente).keyup(function(){
                    var valor = n_cliente.value.length;
                    if (this.value === '' || !(/^[0-9]+$/.test(this.value)) || valor>20) {
                        errorInput(this,'Cliente invalido');
                    } else {
                        correctoInput(this,'Cliente');
                        validacionGeneral();
                    }
                });

                $(n_cliente).keypress(function(e){
                    if (!soloNumeros(e)) {
                        e.preventDefault();
                    }
                });

            //sucursal
                $(id_sucursal).keyup(function(){
                    var valor = id_sucursal.value.length;
                    if (this.value === '' || !(/^[0-9]+$/.test(this.value)) || valor>5) {
                        errorInput(this,'Surursal invalida');
                    } else {
                        correctoInput(this,'Sucursal');
                        validacionGeneral();
                    }
                });

                $(id_sucursal).keypress(function(e){
                    if (!soloNumeros(e)) {
                        e.preventDefault();
                    }
                });

            //titular

            //destino
                var tipo_cuenta = document.getElementById("tipo_cuenta");
                var lbltipo_cuenta = document.getElementById("lbltipo_cuenta");
                $(tipo_cuenta).change(function(){
                    if(tipo_cuenta.value != ''){
                        lbltipo_cuenta.classList.remove("errorlabel");
                        validacionGeneral();
                    } else {
                        lbltipo_cuenta.classList.add("errorlabel");
                    }
                });
            
        //verClabe
            $("#checkPassClabe").click(function(){
               if (clabe_inter.value != '') {
                    if (clabe_inter.type === "password") {
                        clabe_inter.type = "text";
                    } else {
                        clabe_inter.type = "password";
                    }
               } else {
                errorInput(clabe_inter,"registra clabe");
               }
            });

        //inputs cheques, credito y debito
            var addManejos = document.getElementById("addManejos");
            var selectManejo = document.getElementById("selectManejo");
            var txtReferencia = document.getElementById("txtReferencia");
            var tbodyManejo = document.getElementById("tbodyManejo");
            var trVacio = document.getElementById("trVacio");

            $(addManejos).click(function(){
                if (selectManejo.value != '' && txtReferencia.value != '') {
                    trVacio.remove();
                    var nuevotr = document.createElement("tr");
                    var datos = '<input type="hidden" name="txtManejo[]" value="'+selectManejo.value+'">'+
                    '<input type="hidden" name="txtReferencia[]" value="'+txtReferencia.value+'">'+
                    '<td>'+selectManejo.value+'</td><td>'+txtReferencia.value+'</td>';
                    nuevotr.innerHTML = datos;
                    tbodyManejo.appendChild(nuevotr);

                    selectManejo.selectedIndex = 0;
                    selectManejo.value = '';
                    $('#selectManejo').prop('readonly', false);
                    $('select').material_select();

                    txtReferencia.value = '';
                } else {
                    alert("vacios");
                }
            });   

        //action=""
        function validacionGeneral(){
            var banco_clave = document.querySelector('input[name="clave_banco"]:checked');
            if  (banco_clave && n_contrato.value !='' && n_cuenta.value !='' && clabe_inter.value !='' 
            && n_cliente.value !='' && id_sucursal.value !='' && tipo_cuenta.value != '' ) {
                $("#btnGuardaBancarias").removeClass("noneView");
            } else {
                $("#btnGuardaBancarias").addClass("noneView");
            }
        };

        $("#btnGuardaBancarias").click(function(){
            var banco_clave = document.querySelector('input[name="clave_banco"]:checked');
            if  (banco_clave && n_contrato.value !='' && n_cuenta.value !='' && clabe_inter.value !='' 
            && n_cliente.value !='' && id_sucursal.value !='' && tipo_cuenta.value != '' ) {
                envio_fMon();
            } else {
                if (!banco_clave) {
                    trHeadBancos.classList.add("error");
                }
                if (tipo_cuenta.value == '') {
                    lbltipo_cuenta.classList.add("errorlabel");
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
            document.formon.target = "_self";
            document.formon.action = "tesoreria-registra-cuentas";
            document.formon.submit();
        }

});
