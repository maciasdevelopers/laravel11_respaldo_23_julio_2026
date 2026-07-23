$(document).ready(function(){
    //inputs

    //proveedor extranjero
    $("#verifProvExt").click(function(){
        if (rfcProv.value == '') {
            Push.create("Completa todos los campos", {
                body: "SOS-México",
                icon: "vista/media/adm/errores/logoSOS.png",
                timeout: 3000,
            });
            lbl_provv.classList.add("active");
            lbl_provv.classList.add("errorlabel");
        } else {

        }
    });

});

function rfcVacio() {
    Push.create("DEBE REGISTRAR RFC", {
        body: "SOS-México",
        icon: "vista/media/adm/errores/logoSOS.png",
        timeout: 3000,
    });
};

function rfcInvalido(){
    Push.create("RFC INVALIDO", {
        body: "SOS-México",
        icon: "vista/media/adm/errores/logoSOS.png",
        timeout: 3000,
    });
};