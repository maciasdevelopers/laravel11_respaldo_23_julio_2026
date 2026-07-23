$(document).ready(function(){
    $('.modal').modal();
    // funciones 
        function errorbtn(alerta){
            alerta.classList.remove("btnCorrecto");
            alerta.classList.add("btnError");
        }

        function correctobtn(alerta){
            alerta.classList.remove("btnError");
            alerta.classList.add("btnCorrecto");
        }

        function disabledbtn(alerta){
            alerta.classList.remove("btnError");
            alerta.classList.remove("btnCorrecto");
        }

    //expresiones regulares 
        var strFilterPass = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ0-9,;.:-_/*+]*$/;

    //inputs
        const pass_act = document.getElementById("pass_act");
        const pass_conf = document.getElementById("pass_conf");
    
    //labels
        var lblpass_act = document.getElementById("lblpass_act"); 
        var lblpass_conf = document.getElementById("lblpass_conf");

    //btnsAlert
        var strengthPrimera = document.getElementById("strengthPrimera");
        var mayusPrimera = document.getElementById("mayusPrimera");
        var numberPrimera = document.getElementById("numberPrimera");
        var symbolPrimera = document.getElementById("symbolPrimera");

        var mayusConfirmacion = document.getElementById("mayusConfirmacion");
        var numberConfirmacion = document.getElementById("numberConfirmacion");
        var symbolConfirmacion = document.getElementById("symbolConfirmacion");
        var strengthConfirmacion = document.getElementById("strengthConfirmacion");

    //paragraph
        var eqpass = document.getElementById("eqpass");
    //botones
        var btnModalActPass = document.getElementById("btnModalActPass");
        var btnActPass = document.getElementById("btnActPass");

        //pass_act.addEventListener("keyup",function(){});

        $(pass_act).keyup(function(){
            if (this.value != '') {
                if (strFilterPass.test(this.value) && this.value.length >= 8) {
                    lblpass_act.classList.remove("errorlabel");
                    correctobtn(mayusPrimera);
                    correctobtn(numberPrimera);
                    correctobtn(symbolPrimera);
                    const pdwVal = pass_act.value;
                    let result = zxcvbn(pdwVal);
                    strengthPrimera.className = " strength-" + result.score; 
                    if (strengthPrimera.classList.contains("strength-3") || strengthPrimera.classList.contains("strength-4")) {
                        console.log("bien");
                        pIgualesveirf();
                    }
                } else {
                    lblpass_act.classList.add("errorlabel");
                    errorbtn(mayusPrimera);
                    errorbtn(numberPrimera);
                    errorbtn(symbolPrimera);
                }
            } else {
                lblpass_act.classList.add("errorlabel");
                disabledbtn(mayusPrimera);
                disabledbtn(numberPrimera);
                disabledbtn(symbolPrimera);
                const pdwVal = pass_act.value;
                let result = zxcvbn(pdwVal);
                strengthPrimera.className = "strength-" + result.score; 
            }
        });

        $(pass_conf).keyup(function(){
            if (this.value != '') {
                if (strFilterPass.test(this.value) && this.value.length >= 8) {
                    lblpass_conf.classList.remove("errorlabel");
                    correctobtn(mayusConfirmacion);
                    correctobtn(numberConfirmacion);
                    correctobtn(symbolConfirmacion);
                    const pdwVal = pass_act.value;
                    let result = zxcvbn(pdwVal);
                    strengthConfirmacion.className = " strength-" + result.score; 
                    if (strengthConfirmacion.classList.contains("strength-3") || strengthConfirmacion.classList.contains("strength-4")) {
                        console.log("bien");
                        pIgualesveirf();
                    }
                } else {
                    lblpass_conf.classList.add("errorlabel");
                    errorbtn(mayusConfirmacion);
                    errorbtn(numberConfirmacion);
                    errorbtn(symbolConfirmacion);
                }
            } else {
                lblpass_conf.classList.add("errorlabel");
                disabledbtn(mayusConfirmacion);
                disabledbtn(numberConfirmacion);
                disabledbtn(symbolConfirmacion);
                const pdwVal = pass_act.value;
                let result = zxcvbn(pdwVal);
                strengthConfirmacion.className = "strength-" + result.score; 
            }
        });

        function pIgualesveirf(){
            if (pass_act.value != '' && pass_conf.value != '') {
                if (pass_act.value == pass_conf.value) {
                    correctobtn(eqpass);
                    $(eqpass).html(" &#xf058; Contraseñas iguales");
                    $(btnModalActPass).removeAttr('disabled');
                } else {
                    errorbtn(eqpass);
                    $(eqpass).html(" &#xf057; Contraseñas diferentes");
                    $(btnModalActPass).attr('disabled',true);
                }
            } else {
                if (pass_act.value == '') {
                    lblpass_act.classList.add("errorlabel");
                }

                if (pass_conf.value == '') {
                    lblpass_conf.classList.add("errorlabel");
                }
            }
        }

        $(btnActPass).click(function(){
            if (pass_act.value != '' && pass_conf.value != '') {
                if (pass_act.value == pass_conf.value) {
                    eqpass.classList.add("noneView");
                    $("#divCargaActPass").removeClass("noneView");
                    var porcentajeCarga = 0;
                    var intervalo = setInterval(() => {
                        porcentajeCarga = porcentajeCarga+1;
                        var porcenDiv = porcentajeCarga+'%';
                        $("#cargaActPass").css('width',porcenDiv);
                        if (porcentajeCarga == 100) {
                            clearInterval(intervalo);
                            setTimeout(function() {
                                $.ajax({
                                    url: 'sos-actualiza-contrasena',
                                    type: 'POST',
                                    datatype: 'html',
                                    data: {
                                        txtpassword:pass_act.value,
                                        txtpasswordconf:pass_conf.value
                                    }
                                })
                                .done(function(respuesta){
                                    alert(respuesta);
                                    if (respuesta == 'passHasBeingUpdated') {
                                        var $toastContent = $('<div class="btnCorrecto">Contraseña actualizada</div>');
                                        Materialize.toast($toastContent,5000); 
                                        window.location = "lgout"; 
                                    }

                                    if (respuesta == 'passHasNotBeingUpdated') {
                                        var $toastContent = $('<div class="btnError">Contraseña no actualizada, ¡Intentelo nuevamente o comuniquese a soporte!</div>');
                                        Materialize.toast($toastContent,5000); 
                                    }

                                    if (respuesta == 'errorEqualPass' || respuesta == 'errorEqualPassModelo') {
                                        var $toastContent = $('<div class="btnError">Las contraseñas no coinciden, ¡Intentelo nuevamente o comuniquese a soporte!</div>');
                                        Materialize.toast($toastContent,5000); 
                                        lblpass_act.classList.add("errorlabel");
                                        lblpass_conf.classList.add("errorlabel");
                                        errorbtn(eqpass);
                                        $(eqpass).html(" &#xf057; Contraseñas diferentes");
                                        $(btnModalActPass).attr('disabled',true);
                                    }

                                    if (respuesta == 'errortxtpassword') {
                                        var $toastContent = $('<div class="btnError">Contraseña invalida, ¡Intentelo nuevamente o comuniquese a soporte!</div>');
                                        Materialize.toast($toastContent,5000); 
                                        lblpass_conf.classList.add("errorlabel");
                                        $(btnModalActPass).attr('disabled',true);
                                    }

                                    if (respuesta == 'errortxtpasswordconf') {
                                        var $toastContent = $('<div class="btnError">Contraseña invalida, ¡Intentelo nuevamente o comuniquese a soporte!</div>');
                                        Materialize.toast($toastContent,5000); 
                                        lblpass_conf.classList.add("errorlabel");
                                        $(btnModalActPass).attr('disabled',true);
                                    }
                                })
                                .fail(function(){
                                    console.log("error")
                                }); 
                            }, 1000); // WAIT 5 milliseconds
                        }
                    }, 30);
                } else {
                    var $toastContent = $('<div class="btnError">Las contraseñas no coinciden</div>');
                    Materialize.toast($toastContent,5000);    
                }
            } else {
                if (pass_act.value == '') {
                    lblpass_act.classList.add("errorlabel");
                }
                if (pass_conf.value == '') {
                    lblpass_conf.classList.add("errorlabel");
                }
            }
        });
});
