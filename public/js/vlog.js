$(document).ready(function(){
    //variables
        var height = $(window).height();
    //divs 
        var parallaxCont = document.getElementById("parallax-container");
    //inputs
        var cod_log = document.getElementById("cod_log");
        var pass_log = document.getElementById("pass_log");
    //labels
        var lblcod_log = document.getElementById("lblcod_log");
        var lblpass_log = document.getElementById("lblpass_log");

    $("#parallax-container").height(height);
    $("#lguser").click(function(){
       if (cod_log.value != '' /*&& cod_log.value.length == 8*/ && pass_log.value != '') {
       } else {
           camposVacios();
           if (cod_log.value == '') {
                lblcod_log.classList.add("errorlabel");
           }

           if (pass_log.value == '') {
                lblpass_log.classList.add("errorlabel");
            }
       }
    });

    $(cod_log).keyup(function(){
        lblcod_log.classList.remove("errorlabel");
    });

    $(pass_log).keyup(function(){
        lblpass_log.classList.remove("errorlabel");
    });

});

function camposVacios(){
    Push.create("COMPLETA LOS CAMPOS VACIOS",{
        body:"SOS-México",
        icon:"vista/media/landing/logotipo/314g.jpg",
        timeout:3000
    });
};