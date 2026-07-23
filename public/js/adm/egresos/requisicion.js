$(document).ready(function(){
    //input
        var req_documento = document.getElementById("req_documento");
    
    //labels
        var lbl_proyecto = document.getElementById("lbl_proyecto");
    
    //div
        var divDocReq = document.getElementById("divDocReq");
        var divBotonRequisicion = document.getElementById("divBotonRequisicion");
    
    //tablas
        var trVacios = document.getElementById("camposVacios");
        var lista = document.getElementById("regReq");
        var dlista = document.getElementById("regNReq");

    //
        var media = window.matchMedia("(max-width: 400px)");

        const caractReq = new Vue({
            el:"#detCaractReq",
            data:{
                listaCaract:[
                    {type:"number",class:"inputCaract0",name:"reqCaractPrecio[]",id:"reqCaractPrecio",valor:"Precio"},
                    {type:"text",class:"inputCaract1",name:"reqCaractColor[]",id:"reqCaractColor",valor:"Color"},
                    {type:"text",class:"inputCaract2",name:"reqCaractTamaño[]",id:"reqCaractTamaño",valor:"Tamaño"}, 
                    {type:"text",class:"inputCaract3",name:"reqCaractTalla[]",id:"reqCaractTalla",valor:"Talla"},
                    {type:"text",class:"inputCaract4",name:"reqCaractMaterial[]",id:"reqCaractMaterial",valor:"Material"},
                    {type:"text",class:"inputCaract5",name:"reqCaractTipo[]",id:"reqCaractTipo",valor:"Tipo"},
                    {type:"text",class:"inputCaract6",name:"reqCaractForma[]",id:"reqCaractForma",valor:"Forma"},
                    {type:"number",class:"inputCaract7",name:"reqCaractPeso[]",id:"reqCaractPeso",valor:"Peso (Kg)"},
                    {type:"number",class:"inputCaract8",name:"reqCaractAltura[]",id:"reqCaractAltura",valor:"Altura (Mts)"},
                    {type:"text",class:"inputCaract9",name:"reqCaractTextura[]",id:"reqCaractTextura",valor:"Textura"}
                ]
            }
        });

    //modalVerRequisicion

    $(".viewRequisicion").click(function(){
        var requisicion = $(this).parent("div").find("#idHiddenReq").val();
        $.ajax({
            url: 'egresos-buscaRequisicion',
            type: 'POST',
            datatype: 'html',
            data: {requisicion : requisicion}
        }).done(function(respuesta){
            $("#dataRequisicionView").html(respuesta);
        }).fail(function(){
            console.log("error");
        }); 
    });
    //modalRegistroRequisicion
        
        //pdf doc
            $(req_documento).change(function(e){

                //objeto de la clase FileReader
                let reader = new FileReader();
                reader.readAsDataURL(e.target.files[0]);
                
                //cargar pdf y alacenarlos en html
                var ext = this.value.split('.').pop();

                reader.onload = function(){
                    switch(ext){
                        case 'pdf':
                            let docPdf = '<embed src='+reader.result+' type="application/pdf"/>';
                            divDocReq.innerHTML = docPdf;
                        break;
                        case 'jpg':
                            let imgJpg = '<img class="circle responsive-img " src="'+reader.result+'">';
                            divDocReq.innerHTML = imgJpg;
                        break;
                        case 'png':
                            let imgPng = '<img class="circle responsive-img " src="'+reader.result+'">';
                            divDocReq.innerHTML = imgPng;
                        break;
                        default:
                            if (media.matches) {
                                alert('la imagen debe estar en formato JPG 0 PNG');
                                this.value = '';
                                this.files[0].name = '';
                            } else {
                                Error_ext();
                                this.value = '';
                                this.files[0].name = '';                
                            }


                    }
                }
            });

        //imagen
            $("#tablaReq ").on("change", "td #req_imgnecesidad",function(e){
                var valor = this.value;
                var destino = $(this).parent("div").find("#divImgReq");
                if (valor != '') {
                    destino.removeClass("btnError");
                    llenarPdfImg(e,valor,destino);
                } else {
                    destino.addClass("btnError");
                }
            });   

            function llenarPdfImg(e,valor,destino){
                //objeto de la clase FileReader
                let reader = new FileReader();
                reader.readAsDataURL(e.target.files[0]);
                var typeElemento = e.target.files[0].type;
                var tamano = e.target.files[0].size;
                if (typeElemento == "image/jpeg" || typeElemento == "image/png") {
                    if (tamano < 2000000) {
                        reader.onload = function(){
                            var imaNecesidad = document.createElement("img"); 
                            imaNecesidad.classList.add("circle"); 
                            imaNecesidad.classList.add("responsive-img"); 
                            imaNecesidad.setAttribute("src",reader.result);
                            //divImgReq.innerHTML = imaNecesidad;
                            destino.html(imaNecesidad);
                        }
                    } else {
                        destino.addClass("btnError");
                        if (media.matches) {
                            alert('La imagen no debe superar los 2MB');
                            valor = '';
                            //this.files[0].name = '';
                        } else {
                            Pesado();
                            valor = '';
                            //this.files[0].name = '';      
                        }
                    }
                } else {
                    destino.addClass("btnError");
                    if (media.matches) {
                        alert('El archivo debe estar en formato .jpg, .png');
                        valor = '';
                        //this.files[0].name = '';
                    } else {
                        error_ext();
                        valor = '';
                        //this.files[0].name = '';                
                    }
                }                    
            }
            
});

function Pesado() {
    Push.create("El archivo no debe superar los 2MB", {
        body: "SOS-México",
        icon: "vista/media/adm/usuarios/perfiles/logoSOS.png",
        timeout: 3000,
    });
};

function Error_ext() {
    Push.create("la imagen debe estar en formato JPG 0 PNG", {
        body: "SOS-México",
        icon: "vista/media/landing/logotipo/314g.jpg",
        timeout: 3000,
    });
};

