<script type="text/javascript">
    function enviarSelect(e){
    var sel = document.getElementById("entidad_check");
    var mun = document.getElementById("seldel");
    var val = 32;
    var array_mun = [];
    
    //var val = 32;
        alert(val);
    $.post( " ", { id_entidad: val}, function(e){

        <?php
        if (isset($_POST['id_entidad'])&& !empty($_POST['id_entidad'])) {
            echo $_POST['id_entidad'];
        } else {
            # code...
        }
        
          
        ?>

        alert(e);
        array_mun.push(e);
    
    });
    console.log(array_mun)
    mun.innerHTML = array_mun;
}

</script>