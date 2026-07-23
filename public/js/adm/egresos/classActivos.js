$(document).ready(function(){
    //catalogo
    //formulario
        //lista de clasificacion de activos
            var tableClassAct = document.getElementById("tableClassAct");

            //inputs
                var classActivo = document.getElementById("classActivo"); 
                var deprecContable = document.getElementById("deprecContable");
                var deprecFiscal = document.getElementById("deprecFiscal");
            //labels
                var lblDeprecContable = document.getElementById("lblDeprecContable");
                var lblDeprecFiscal = document.getElementById("lblDeprecFiscal");
                var lblClassActivo = document.getElementById("lblClassActivo");

            $(".selectClassActivo").click(function(){
                var tdClass = $(this).parent("td").parent("tr").find("td").eq(1).html();
                var tdContable = $(this).parent("td").parent("tr").find("td").eq(2).html();
                var tdFiscal = $(this).parent("td").parent("tr").find("td").eq(3).html();
                
                if (lblClassActivo.classList.contains("errorlabel")) {
                    lblClassActivo.classList.remove("errorlabel");
                    lblClassActivo.innerHTML = "Clasificaci&oacute;n";
                }
                classActivo.value = tdClass;

                if (lblDeprecContable.classList.contains("errorlabel")) {
                    lblDeprecContable.classList.remove("errorlabel");
                    lblDeprecContable.innerHTML = "Depreciaci&oacute;n contable";
                }
                deprecContable.value = tdContable;
                
                if (lblDeprecFiscal.classList.contains("errorlabel")) {
                    lblDeprecFiscal.classList.remove("errorlabel");
                    lblDeprecFiscal.innerHTML = "Depreciaci&oacute;n fiscal incorrecta";
                }
                deprecFiscal.value = tdFiscal;
                
            });
})
