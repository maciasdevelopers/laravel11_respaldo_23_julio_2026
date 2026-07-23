$(document).ready(function(){
    //formulario
        $("#btnMenupersFisica").click(function(){
            $("#verifica_rfc").addClass("noneView");
            $("#frm-registro-pf").removeClass("noneView");
        });

        $("#btnMenupersMoral").click(function(){
            $("#verifica_rfc").addClass("noneView");
            $("#frm-registro-pm").removeClass("noneView");
        });
});

