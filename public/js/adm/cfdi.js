$(document).ready(function () {
    //verificar rfc para habilitar formularios
    const verificarfc = "#verifrfc";
    $(verificarfc).click(function (){
        let divrfc = document.getElementById("verifica_rfc");
        let divpf = document.getElementById("frm-registro-pf");
        let divpm = document.getElementById("frm-registro-pm");
        let frc = document.getElementById("verif_rfc");
        let txtvrfcPf = document.getElementById("rfc_viewPf");
        let txtvrfcPm = document.getElementById("rfc_viewPm");
        var modal1 = document.getElementById("modalPF");
        var modal2 = document.getElementById("modalPM");

        if (frc.value.length == 13) {
            var mensaje = confirm("¿Deseas registrarte como Persona Fisica?");
            if (mensaje) {

                /*divrfc.classList.remove("verifica_rfc");
                divrfc.classList.add("nover-pf");
                divpf.classList.remove("nover-pf");
                divpf.classList.add("form-registro-pf");
                txtvrfcPf.setAttribute("value", frc.value);*/
                modal1.classList.add("ver_modal");

            }
        } else if (frc.value.length == 12){
            var mensaje = confirm("¿Deseas registrarte como Persona Moral?");
            if (mensaje) {
                /*divrfc.classList.remove("verifica_rfc");
                divrfc.classList.add("nover-pf");
                divpm.classList.remove("nover-pm");
                divpm.classList.add("form-registro-pm");
                txtvrfcPm.setAttribute("value", frc.value);*/
                modal2.classList.add("ver_modal");
            }
        }
        else {
            
        }
    });
});
