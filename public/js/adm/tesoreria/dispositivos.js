$(document).ready(function(){
    //dispositivos vinculados a una cuenta bancaria
        (listaDispBanc());
        function listaDispBanc(){
            $.ajax({
                type: "post",
                url: "tesoreria-control-listdisbank",
                dataType: "html"
            }).done(function(respuesta){
                $("#CatDisBanc").html(respuesta);
            }).fail();
        }

    //dispositivos vinculados a un monedero electrónico
        (listadispMon());
        function listadispMon(){
            $.ajax({
                type: "post",
                url: "tesoreria-control-listdismon",
                dataType: "html"
            }).done(function(respuesta){
                $("#CatDisMon").html(respuesta);
            }).fail(function(respuesta){
                $("#CatDisMon").html(respuesta);
            });
        }

        var vinculado_a = document.getElementById("vinculado_a");
        var vinculacionCuenta = document.getElementById("vinculacionCuenta");
        var tablistaCuenta = document.getElementById("tablistaCuenta");
        var tablistaMonedero = document.getElementById("tablistaMonedero");
        $(vinculado_a).change(function(){
            if (this.value !='') {
                vinculacionCuenta.classList.remove("noneView");
                vinculacionCuenta.classList.add("table");
                if (this.value == 'c_bancarias') {
                    tablistaCuenta.classList.remove("noneView");
                    tablistaMonedero.classList.add("noneView");
                } else if(this.value == 'monederos'){
                    tablistaMonedero.classList.remove("noneView");
                    tablistaCuenta.classList.add("noneView");
                }
            } else {

            }        
        });

    
});
    
