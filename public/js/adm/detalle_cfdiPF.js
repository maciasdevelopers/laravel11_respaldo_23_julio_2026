$(document).ready(function () {
    var tp1 = document.getElementById("tipo1");
    var tp2 = document.getElementById("tipo2");
    var btnsalida = document.getElementById("enviaSol"); 
    //datos de personas
    var personas = document.getElementById("info_receptor_persona");
    var rsocial = document.getElementById("info_receptor_razon");
    var linkp = document.getElementById("abrecierrap");
    var linkr = document.getElementById("abrecierrars");
    //tabla de cfdi
    var lista = document.getElementById('reg_tabla');
    linkp.innerHTML = "&#xf2d0;";
    linkr.innerHTML = "&#xf2d0;";
    var array_importe = [];

    

    //ventana de persona fisica
    $("#abrecierrap").click(function(){
        if (personas.classList.contains("remove_pfis")) {
            personas.classList.add("info_receptor");
            personas.classList.remove("remove_pfis");
            btnsalida.classList.add("enviaSolP");
            //btnsalida.setAttribute("id","enviaSolP");
            //alert($("#abrecierrap").text())
            linkp.classList.add("red");
            linkp.innerHTML = "&#xf2d1;";
            linkp.classList.remove("green");
            tp1.setAttribute("value", "persona");
            linkr.classList.add("disabled");
        } 
        else {
            btnsalida.classList.remove("enviaSolP");
            personas.classList.add("remove_pfis");
            personas.classList.remove("info_receptor");
            linkp.classList.add("green");
            linkp.innerHTML = "&#xf2d0;";
            linkp.classList.remove("red");
            tp1.setAttribute("value", "");
            linkr.classList.remove("disabled");
        }
    });

    //ventana de personas morales 
    $("#abrecierrars").click(function(){
        if (rsocial.classList.contains("remove_rsoc")) {
            rsocial.classList.add("info_receptor");
            rsocial.classList.remove("remove_rsoc");
            btnsalida.classList.add("enviaSolR");
            //alert($("#abrecierrap").text())
            tp2.setAttribute("value", "razon");
            linkr.classList.add("red");
            linkr.innerHTML = "&#xf2d1;";
            linkr.classList.remove("green");
            linkp.classList.add("disabled");
        } 
        else {
            tp2.setAttribute("value", "razon");
            rsocial.classList.add("remove_rsoc");
            rsocial.classList.remove("info_receptor");
            btnsalida.classList.remove("enviaSolR");
            linkr.classList.add("green");
            linkr.innerHTML = "&#xf2d0;";
            linkr.classList.remove("red");
            linkp.classList.remove("disabled");
        }
    });

    $("#addCfdi").click(function () {
        //variables
        var clave = document.getElementById("clave_sat");
        var unidad = document.getElementById("unidad_sat");
        var cantidad = document.getElementById("cantidad");
        var desc = document.getElementById("descripcion");
        var precio = document.getElementById("precio");
        //var importe0 = document.getElementById("importe").value;
        alert(cantidad.value);
        var importe = cantidad.value * precio.value;
        var subtotal = document.getElementById("subtotal");
        var iva = document.getElementById("iva");
        var subtotaliva = document.getElementById("subtotal_iva");

        if (clave.value == '' || unidad.value == '' || 
                cantidad.value == '' || desc.value == '' || 
                precio.value == '') {
            //alert("campo vacio");
            //var media = window.matchMedia("(max-width: 400px)");
            $("#info_lgmail").css('display', 'none');
            $("#info_lgpass").css('display', 'none');
            Nota();
            $("#tabCfdi").validate({
                rules:{
                    clave_sat:{
                        required : true,
                        minlength: 5
                    },
                    unidad_sat:{
                        required : true,
                        minlength: 5
                    },
                    cantidad:{
                        required : true,
                        minlength: 5
                    },
                    descripcion:{
                        required : true,
                        minlength: 5
                    },
                    precio:{
                        required : true,
                        minlength: 5
                    }
                },
                messages:{
                    clave_sat: {
                        required: "Introduce tu correo electronico",
                        minlength: "tu correo es muy corto"
                    },
                    unidad_sat: {
                        required: "Introduce tu contraseña",
                        minlength: "tu ontraseña es muy corta"
                    },
                    cantidad: {
                        required: "Introduce tu correo electronico",
                        minlength: "tu correo es muy corto"
                    },
                    descripcion: {
                        required: "Introduce tu contraseña",
                        minlength: "tu ontraseña es muy corta"
                    },
                    precio: {
                        required: "Introduce tu correo electronico",
                        minlength: "tu correo es muy corto"
                    }
                },
                errorElement: 'label',
                errorPlacement: function(error, element){
                    var placement = $(element).data('error');
                    if (placement) {
                        $(placement).append(error)
                    } else {
                        error.insertAfter(element);                       
                    }
                }
            });


        } else {
         
            array_importe.push(importe);
            let renglon = document.createElement('tr');
            /*creando celdas*/
            let celda1 = document.createElement('td');
            let celda2 = document.createElement('td');
            let celda3 = document.createElement('td');
            let celda4 = document.createElement('td');
            let celda5 = document.createElement('td');
            let celda6 = document.createElement('td');
            let celda7 = document.createElement('td');

            let registro1 = document.createElement('input');
            let registro2 = document.createElement('input');
            let registro3 = document.createElement('input');
            let registro4 = document.createElement('input');
            let registro5 = document.createElement('input');
            let registro6 = document.createElement('input');

            let btnadd = document.createElement('a');
            let btn_delete = document.createElement('a');

            lista.appendChild(renglon);
            renglon.classList.add('registros');
            renglon.appendChild(celda1);
            renglon.appendChild(celda2);
            renglon.appendChild(celda3);
            renglon.appendChild(celda4);
            renglon.appendChild(celda5);
            renglon.appendChild(celda6);
            renglon.appendChild(celda7);
                    
            celda1.appendChild(registro1);
            celda2.appendChild(registro2);
            celda3.appendChild(registro3);
            celda4.appendChild(registro4);
            celda5.appendChild(registro5);
            celda6.appendChild(registro6);
            celda7.appendChild(btnadd);
            celda7.appendChild(btn_delete);
            //registro1.setAttribute("type", "text");
            registro1.setAttribute("name", "l_clave_sat[]");
            registro1.setAttribute("value", clave.value);
            registro2.setAttribute("name", "l_unidad_sat[]");
            registro2.setAttribute("value", unidad.value);
            registro3.setAttribute("name", "l_cantidad[]");
            registro3.setAttribute("value", cantidad.value);
            registro4.setAttribute("name", "l_descripcion[]");
            registro4.setAttribute("value", desc.value);
            registro5.setAttribute("name", "l_precio[]");
            registro5.setAttribute("value", precio.value);
            registro6.setAttribute("name", "l_importe[]");
            registro6.setAttribute("value", importe);

            var r_subtotal = document.getElementsByClassName("l_importe").value;
            let suma = 0;

            for (let i = 0; i < array_importe.length; i++) {
                suma = parseInt(suma) + parseInt(array_importe[i]);   
            }
            subtotal.innerHTML = "$" + suma;
            iva.innerHTML = "$" + (suma*0.16);
            subtotaliva.innerHTML = "$" + (suma + (suma*0.16));

            //vaciando campos
            //clave.innerHTML = "";
            //unidad.innerHTML = "";
            //cantidad.innerHTML = "";
            //desc.innerHTML = "";
            //precio.innerHTML = "";
            //importe0.innerHTML = "";
            //$('input[type="text"]').val('');
            clave.val('');
            unidad.val('');
            cantidad.val('');
            desc.val('');
            precio.val('');
            importe0.val('');

            btnadd.setAttribute("class","btn waves-effect waves-light green darken-2");
            btnadd.setAttribute("id","edita_fila");
            btnadd.innerHTML = "&#xf067;";
            btn_delete.setAttribute("class","btn waves-effect waves-light red darken-2");
            btn_delete.setAttribute("id","elmina_fila");
            btn_delete.innerHTML = "&#xf1f8;";
        }
       
    });

    $("#enviaSol").click(function () {
        if (btnsalida.classList.contains("enviaSolP")) {
            //si es que esta esta clase validar form de personas fisicas
            if ($("#paterno").val() == '' || $("#materno").val() == '' || $("#nombreP").val() == '' ||
                $("#telefonoP").val() == '' || $("#rfcP").val() == '' || $("#ncuentaP").val() == '' ||
                $("#emailP").val() == '' || 
                //domicilio
                $("#calleP").val() == '' || $("#nExtP").val() == '' || /*$("#nIntP").val() == '' ||*/
                $("#coloniaP").val() == '' || $("#delegP").val() == '' || 
                //Metdos de pago y uso de cfdi
                $("#metpago").trim().val() == '' || $("#formapago").val() == '' || $("#uso_cfdi").val() == '') {
                Nota();
                $("#frm-cfdi").validate({
                    rules:{
                        paterno:{
                            required:true,
                            minlength: 5
                        },
                        materno:{
                            required:true,
                            minlength: 5
                        },
                        nombreP:{
                            required:true,
                            minlength: 5
                        },
                        telefonoP:{
                            required:true,
                            minlength: 10,
                            maxlength:10
                        },
                        rfcP:{
                            required:true,
                            minlength: 13,
                            maxlength:15
                        },
                        ncuentaP:{
                            required:true,
                            minlength: 16,
                            maxlength:16
                        },
                        emailP:{
                            required:true,
                            minlength: 5
                        },
                        //domicilio
                        calleP:{
                            required:true,
                            minlength: 5
                        },
                        nExtP:{
                            required:true,
                        },
                        //nIntP:{
                        //    required:true,
                        //    minlength: 5
                        //},
                        coloniaP:{
                            required:true,
                            minlength: 3
                        },
                        delegP:{
                            required:true,
                            minlength: 1
                        },
                        //metodo y uso
                        metpago:{
                            required:true,
                            minlength: 1
                        },
                        formapago:{
                            required:true,
                            minlength: 1
                        },
                        uso_cfdi:{
                            required:true,
                            minlength: 1
                        }
                    },
                    messages:{
                        paterno: {
                            required: "Introduce el apellido paterno del receptor",
                            minlength: "El apellido paterno debe abarcar 5 caracteres como minimo"
                        },
                        materno: {
                            required: "Introduce el apellido materno del receptor",
                            minlength: "El apellido materno debe abarcar 5 caracteres como minimo"
                        },
                        nombreP: {
                            required: "Introduce el nombre/nombres del receptor",
                            minlength: "El nombre debe abarcar 5 caracteres como minimo"
                        },
                        
                        telefonoP:{
                            required: "Introduce el número de telefono del receptor",
                            minlength: "El telefono debe abarcar 10 caracteres",
                            maxlength: "El telefono debe abarcar 10 caracteres"
                        },
                        rfcP:{
                            required: "Introduce el rfc del receptor",
                            minlength: "El telefono debe abarcar 13 caracteres como minimo",
                            maxlength: "El telefono debe abarcar 15 caracteres como maximo"
                        },
                        ncuentaP:{
                            required: "Introduce el número de cuenta del receptor",
                            minlength: "El número de cuenta debe abarcar 16 caracteres",
                            maxlength: "El número de cuenta debe abarcar 16 caracteres"
                        },
                        emailP:{
                            required: "Introduce el apellido paterno del receptor",
                            minlength: "El apellido paterno debe abarcar 5 caracteres como minimo"
                        },
                        //domicilio
                        calleP:{
                            required: "Introduce la Calle que corresponde al domicilio",
                            minlength: "La Calle debe abarcar 5 caracteres como minimo"
                        },
                        nExtP:{
                            required: "Introduce el número exterior",
                        },
                        //nIntP:{
                        //    required: "Introduce el apellido paterno del receptor",
                        //    minlength: "El apellido paterno debe abarcar 5 caracteres como minimo"
                        //},
                        coloniaP:{
                            required: "Introduce la colonia",
                            minlength: "La colonia debe abarcar 3 caracteres como minimo"
                        },
                        delegP:{
                            required: "Selecciona la delegacion a la que pertenece"
                        },
                        //metodo y uso
                        metpago:{
                            required: "Selecciona el Método de pago"
                        },
                        formapago:{
                            required: "Selecciona la forma de pago que requiere"
                        },
                        uso_cfdi:{
                            required: "Selecciona el uso de cfdi necesario"
                        }
                    },
                    errorElement: 'div',
                    errorPlacement: function (error,element){
                        var placement = $(element).data('error');
                        if (placement) {
                            $(placement).append(error);
                        } else {
                            error.insertAfter(element);
                        }
                    }
                });
            } else {
                
            }
        } else if (btnsalida.classList.contains("enviaSolR")) {
            if ($("#nombreR").val() == '' || $("#rfcR").val() == '' || $("#ncuentaR").val() == '' || 
                $("#telefonoR").val() == '' || $("#emailR").val() == '' || 
                //domicilio
                $("#calleR").val() == '' || $("#nExtR").val() == '' || /*$("#nIntR").val() == '' ||*/
                $("#coloniaR").val() == '' || $("#delegR").val() == '' ||
                //Metdos de pago y uso de cfdi
                $("#metpago").val() == '' || $("#formapago").val() == '' || $("#uso_cfdi").val() == '') {
                Nota();
                $("#frm-cfdi").validate({
                    rules:{
                        nombreR:{
                            required:true,
                            minlength: 5
                        },
                        telefonoR:{
                            required:true,
                            minlength: 5
                        },
                        rfcR:{
                            required:true,
                            minlength: 5
                        },
                        ncuentaR:{
                            required:true,
                            minlength: 5
                        },
                        emailR:{
                            required:true,
                            minlength: 5
                        },
                        //domicilio
                        calleR:{
                            required:true,
                            minlength: 5
                        },
                        nExtR:{
                            required:true,
                            minlength: 5
                        },
                        //nIntR:{
                        //    required:true,
                        //    minlength: 5
                        //},
                        coloniaR:{
                            required:true,
                            minlength: 5
                        },
                        delegR:{
                            required:true,
                            minlength: 5
                        },
                        //metodo y uso
                        metpago:{
                            required:true,
                            minlength: 5
                        },
                        formapago:{
                            required:true,
                            minlength: 5
                        },
                        uso_cfdi:{
                            required:true,
                            minlength: 5
                        }
                    },
                    messages:{
                        nombreR: {
                            required: "Introduce el nombre/nombres del receptor",
                            minlength: "El nombre debe abarcar 5 caracteres como minimo"
                        },
                        
                        telefonoR:{
                            required: "Introduce el número de telefono del receptor",
                            minlength: "El telefono debe abarcar 10 caracteres",
                            maxlength: "El telefono debe abarcar 10 caracteres"
                        },
                        rfcR:{
                            required: "Introduce el rfc del receptor",
                            minlength: "El telefono debe abarcar 13 caracteres como minimo",
                            maxlength: "El telefono debe abarcar 15 caracteres como maximo"
                        },
                        ncuentaR:{
                            required: "Introduce el número de cuenta del receptor",
                            minlength: "El número de cuenta debe abarcar 16 caracteres",
                            maxlength: "El número de cuenta debe abarcar 16 caracteres"
                        },
                        emailR:{
                            required: "Introduce el apellido paterno del receptor",
                            minlength: "El apellido paterno debe abarcar 5 caracteres como minimo"
                        },
                        //domicilio
                        calleR:{
                            required: "Introduce la Calle que corresponde al domicilio",
                            minlength: "La Calle debe abarcar 5 caracteres como minimo"
                        },
                        nExtR:{
                            required: "Introduce el número exterior",
                        },
                        //nIntR:{
                        //    required: "Introduce el apellido paterno del receptor",
                        //    minlength: "El apellido paterno debe abarcar 5 caracteres como minimo"
                        //},
                        coloniaR:{
                            required: "Introduce la colonia",
                            minlength: "La colonia debe abarcar 3 caracteres como minimo"
                        },
                        delegR:{
                            required: "Selecciona la delegacion a la que pertenece"
                        },
                        //metodo y uso
                        metpago:{
                            required: "Selecciona el Método de pago"
                        },
                        formapago:{
                            required: "Selecciona la forma de pago que requiere"
                        },
                        uso_cfdi:{
                            required: "Selecciona el uso de cfdi necesario"
                        }
                    },
                    errorElement: 'div',
                    errorPlacement: function (error,element){
                        var placement = $(element).data('error');
                        if (placement) {
                            $(placement).append(error);
                        } else {
                            error.insertAfter(element);
                        }
                    }
                });
            } else {
                
            }
        }
        else {
            EligeReceptor();
        }
       
    });
   
});

function Nota() {
    Push.create("Completa todos los campos", {
        body: "SOS-MEXICO",
        icon: "vista/media/adm/errores/logoSOS.png",
        timeout: 3000,
    });
};

function EligeReceptor() {
    Push.create("Selecciona un receptor", {
        body: "SOS-MEXICO",
        icon: "vista/media/adm/errores/logoSOS.png",
        timeout: 3000,
    });
};

//document.addEventListener('DOMContentLoaded', function() {
//    var elems = document.querySelectorAll('.sidenav');
//    var instances = M.Sidenav.init(elems, options);
//  });

