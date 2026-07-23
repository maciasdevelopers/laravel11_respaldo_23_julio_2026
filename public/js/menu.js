$(document).ready(function () {

    var contenidoCuerpo = document.getElementById("contenidoCuerpo");

    //sección nosotros
        var btnmenuNosotros = document.getElementById("btnMenuNosotros");
        var divNosotros = document.getElementById("divNosotros");
        var h5Nosotros = document.getElementById("h5Nosotros");

        $(btnmenuNosotros).click(function(){
            h5Nosotros.classList.remove("noneView");
            contenidoCuerpo.classList.add("noneView");
            divNosotros.classList.remove("noneView");
        });

    //sección nuestras soluciones
        var btnmenuNueSoluciones = document.getElementById("btnMenuNueSoluciones");
        var divNueSoluciones = document.getElementById("divNueSoluciones");
        var h5NueSoluciones = document.getElementById("h5NueSoluciones");

        $(btnmenuNueSoluciones).click(function(){
            h5NueSoluciones.classList.remove("noneView");
            contenidoCuerpo.classList.add("noneView");
            contenidoCuerpo.classList.add("noneView");
            divNueSoluciones.classList.remove("noneView");
        });

    //sección contactanos
        var btnmenuContactanos = document.getElementById("btnMenuContactanos");
        var divContactanos = document.getElementById("divContactanos");
        var h5Contactanos = document.getElementById("h5Contactanos");

        $(btnmenuContactanos).click(function(){
            h5Contactanos.classList.remove("noneView");
            contenidoCuerpo.classList.add("noneView");
            divContactanos.classList.remove("noneView");
        });

    //sección Descargas
        var btnmenuDescargas = document.getElementById("btnMenuDescargas");
        var divDescargas = document.getElementById("divDescargas");
        var h5Descargas = document.getElementById("h5Descargas");

        $(btnmenuDescargas).click(function(){
            h5Descargas.classList.remove("noneView");
            contenidoCuerpo.classList.add("noneView");
            divDescargas.classList.remove("noneView");
        });

    //sección ClientAccess 
        var btnmenuClientAccess = document.getElementById("btnMenuClientAccess");
        var divClientAccess = document.getElementById("divClientAccess");
        var h5ClientAccess = document.getElementById("h5ClientAccess");

        $(btnmenuClientAccess).click(function(){
            h5ClientAccess.classList.remove("noneView");
            contenidoCuerpo.classList.add("noneView");
            divClientAccess.classList.remove("noneView");
        });

    //sección contactanos
        var btnmenuProvvAccess = document.getElementById("btnMenuProvvAccess");
        var divProvvAccess = document.getElementById("divProvvAccess");
        var h5ProvvAccess = document.getElementById("h5ProvvAccess");

        $(btnmenuProvvAccess).click(function(){
            h5ProvvAccess.classList.remove("noneView");
            contenidoCuerpo.classList.add("noneView");
            divProvvAccess.classList.remove("noneView");
        });

});


