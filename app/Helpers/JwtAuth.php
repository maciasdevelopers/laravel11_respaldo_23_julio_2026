<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Models\PermisosModelo;
use App\Models\ApiFormasPagoModelo;
use App\Models\ApiMonedasModelo;
use App\Models\ApiUnidadesDeMedidaSATModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use Firebase\JWT\Key;
use PhpParser\Node\NullableType;
use Carbon\Carbon;

session_start();

use Session;

class JwtAuth{
  public $key;
  public function __construct(){
    $this->key = 'dtclavessecreto-9876986986986986s';
  }

  public function stilosPdf(){
    $stylosCss = "
            nav {
                width: 100%;
                margin: 0;
                background-color: #e90000;
                border-radius: 4px;
                height: 60px;
            }

        footer {
                background-color: #e90000;
                position: fixed;
                bottom: 0cm;
                left: 0cm;
                right: 0cm;
                height: 2cm;
            }

        nav ul{width: 100%;height:60px;list-style-type: none; padding: 0;}

        nav ul li::marker{
                display: none;
            }

        nav ul li h6{height: 60px;width: 100%;text-align: center;color: #fff;
                    font-family: sans-serif;line-height: 60px;margin: 0;text-align: center;font-size: 2vh;}

        .tituloPdf{width: 100%;color: #353535;text-align: center;text-transform: uppercase;font-family: sans-serif;}

        div.contenidoPdf{width: 100%;color: #353535;text-align: center;text-transform: uppercase;
                font-family: sans-serif;
            }

        div.logo img{
                width: 100px;
                height: 100px;
                border-radius: 50%;
                border: 1px outset #353535;
            }

        table{
                width: 100%;
                color: #353535;
                background-color: #d3d3d3!important;
                margin-top: 5px;
                margin-bottom: 1%;
                border-radius: 8px;
                box-shadow: 1px 1px 1px;
            }

        table thead{
                background-color: #404040;
            }

        table thead tr th{
                color: #fff!important;
                text-transform: uppercase;
                text-align: center;
                font-size: 13px;
                height: 25px;
                margin: 0;
                padding: 0;
                border-radius: 0px;
            }

        table.disabled thead th{
                color: #e7e7ea!important;
            }

        table thead th:first-child{
                border-radius: 8px 0 0 0;
            }

        table thead th:last-child{
                border-radius: 0 8px 0 0;
            }

        table tbody tr td{
                text-transform: lowercase;
                text-align: center;
            }

        table thead th.ultimo,
            table tbody tr td.ultimo{
                width: 40px;
            }

        table p{
                width: 100%!important;
                margin: 0;
                text-align: center;
            }
        ";
    return $stylosCss;
  }

  public function tableCss(){
    $cargaPDFAuth = '
            table{
                width: 100%;
                color: #353535;
                background-color: rgba(0, 0, 0, 0.7)!important;
                margin-top: 5px;
                margin-bottom: 1%;
                border-radius: 8px;
                box-shadow: 2px 2px 10px #353535!important; 
            }

            table.transparent_table{
                background-color: rgba(53,53,53,0.2)!important;
            }

            /*thead*/
            table thead tr th{
                text-align: center;
                font-size: 13px;
                height: 40px;
                line-height: 40px;
                margin: 0;
                padding: 0;
                background-color: #e7e7ea!important;
                color: #353535!important;
                text-transform: uppercase;
                border-radius: 0px;
            }
            
            table.black thead tr th{
                background-color: #353535!important;
                color: #e7e7ea!important;
            }

            table thead tr th.ultimo{
                width: 40px;
            	padding: 0;
                text-align: -webkit-center!important;
            }

            table thead tr th.ultimo a{
                width: 25px;
            	height: 25px;
            	border-radius:50%!important;
            }

            table thead tr th.ultimo a i{
                width: 25px!important;
            	height:25px!important;
            	line-height:25px!important;
            	font-size: 15px;
            	text-align: center;
            }

            table thead tr th:first-child{
                border-radius: 8px 0 0 0;
            }
            
            table thead tr th:last-child{
                border-radius: 0 8px 0 0;
            }
            
            /*tbody*/
            table tbody tr{
                border-bottom: 1px solid #353535;
            }
            
            table tbody tr.tr-selected-true{
                background-color: #353553;
                color: #fff;
            }
            
            table tbody tr.trlink{
                cursor: pointer!important;
            }

            table tbody tr.trCliente{
                background-color: #1A347C;
                color: #fff;
            }
            
            table tbody tr:last-child{
                border-bottom: none;
            }
            
            table tbody tr:hover{
                background-color: rgba(211,211,211,0.2); /*!important*/
                background-color: rgba(0,93,255,0.5); /*!important*/
                color: #353535;
            }
            
            table tbody tr.second_action,
            table tbody tr.second_action:hover{
                background-color: rgba(211,211,211,0.2);
                background-color: rgba(2,153,255,0.7);
                background-color: rgba(233,233,233,0.9);
                background-color: rgba(255,255,255,0.7);
                background-color: rgba(255,235,99,0.7);
                background-color: rgba(231,231,234);
                color: #353535;
            }

            table.transparent_table tbody tr td{
                color: #e7e7ea!important;
            }
            
            table tbody tr td{
                color: #e7e7ea;
                height: 40px!important;
                line-height: 40px!important;
                text-align: center;
                padding: 0px 5px;
                margin-bottom: 0;
            }
            
            table.transparent_table tbody tr td{
                color: #353535!important;
            }
            
            table thead tr th.ultimo,
            table tbody tr td.ultimo{
                width: 40px;
            }
            
            table thead tr th.ultimo{
                padding: 0;
            }
            
            table tbody tr td.ultimo{
                padding: 0;
                text-align: -webkit-center!important;
            }
            
            table tbody tr td p.btn,
            table tbody tr td.ultimo a{
                width: 25px!important;
            	height: 25px!important;
            	border-radius:50%!important;
            }

            table tbody tr td.ultimo a.btnDelete{
                background-color:#d32f2f!important;
            }
            
            table tbody tr td p.btn i,
            table tbody tr td p.btn span,
            table tbody tr td.ultimo a i{
                width: 25px!important;
            	height:25px!important;
            	line-height:25px!important;
            	font-size: 15px;
            	text-align: center;
            }
            
            table tbody tr td.name{
                text-transform: capitalize!important;
            }
            
            table tbody tr td.ovFlow{
                max-width: 100px;
                overflow: overlay;
                text-overflow: unset;
                white-space: nowrap;
            }
            
            table tbody tr td.select{
                max-width: 300px;
            }
            
            table tbody tr td.fechas{
                max-width: 100px;
            }
            
            table tbody tr td.concepto{
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            table tbody tr td.img{
                display: flex;
                align-items: center;
            }

            table tbody tr td.img img{
                height: 32px!important;
                width: 32px!important;
                border-radius: 50%;
            }
            
            table tbody tr td div.text_area{
                display: flex;
                align-items: center;
                align-content: center;
                justify-content: center;
                margin: 0;
            }
            
            table tbody tr td p{
                width: 100%!important;
                margin: 0;
                text-align: center;
            }
            
            table tbody tr td a,
            table tbody tr td a i{
                font-size: 2.5vh;
                font-size: 2vh;
            }
            
            table tbody tr td p input.select{
                opacity: 1;margin: 0;
                position: relative;
                width: 10px!important;
                height: 10px!important;
            } 
            
            table tbody tr td div.select-wrapper input.select-dropdown{
                margin: 0px!important;
            }
            
            table tbody tr td p input.select:checked{
                background-color: aqua;
            }
            
            table tbody tr td img{
                max-width: 40px;
            }

            table tbody tr td input,
            table tbody tr td select,
            table tbody tr td textarea{
                width: 98%;
            }
            
            table tbody tr td input,
            table tbody tr td select{
                height: 30px!important;
            }
            
            table tbody tr td textarea{
                min-height: 30px!important;
            }
            
            table tbody tr td.ultimo input,
            table tbody tr td.ultimo select,
            table tbody tr td.ultimo textarea{
                width: 100%;
            }
            
            table tbody tr td input.error,
            table tbody tr td textarea.error{
                background: #FBCDC3!important;
                border: solid 1px #6d1300!important;
            }
            
            table tbody tr td input.error_black,
            table tbody tr td textarea.error_black{
                color: #FBCDC3!important;
                border: solid 2px #6d1300!important;
            }
            
            table tbody tr td input.correcto,
            table tbody tr td textarea.correcto{
                background: #C6FBC3!important;
                border: solid 1px #388E3C!important;
            }

            table tbody tr td input.correcto_black,
            table tbody tr td textarea.correcto_black{
                color: #C6FBC3 !important;
                border: solid 2px #388E3C !important;
            }
            
            table tbody tr td div.file-field{
                width: 100%;
                margin: 0;
                display: flex;
                justify-content: center;
            }
            
            table tbody tr td.span div{
                display: flex;
            }
            
            table tbody tr td.span div span{
                height: 30px;
                line-height: 30px;
                background-color: blue;
                width: 10%;
                color: #fff;
                border-radius: 8px 0 0 8px;
                border-bottom: 1px solid #353535;
                font-weight: bold;
            }
            
            table.disabled tbody tr td.span div span{
                background-color: #353535;
            }
            
            table tbody tr td.span div input{
                border-radius: 0 8px 8px 0!important;
                width: 90%!important;
            }

            table tbody tr td a{
                font-family: ubuntu, FontAwesome;
                height: 25px;
                line-height: 25px;
                width: 25px;
                background-color: #353535;
                color: #fff;
                padding: 0;
            }
            
            table tbody tr td a,
            table tbody tr td a i{
                font-size: 2.5vh;
                font-size: 2vh;
            }
            
            table tbody tr:last-child td:first-child{
                border-radius: 0 0 0 8px;
            }
            
            table tbody tr:last-child td:last-child{
                border-radius: 0 0 8px 0;
            }
            
            table tbody tr.second_action:last-child td:last-child{
                border-radius: 0!important;
            }
            
            table tfoot tr th,
            table tfoot tr td{
            	color: #e7e7ea;
            }
            
            table tfoot tr th,
            table tfoot tr td{
            	text-align: center;
            	padding: 5px!important;
            }

            .tablaInversa{
                width: 100%!important;
                display: flex;
                flex-wrap: wrap;
                flex-direction: row;
                margin-top: 0!important;
                justify-content: space-between;
                align-items: center;
            }
            
            table.table_show_register tbody tr td input.register,
            table.table_show_register tbody tr td select.register{
                height: 80px!important;
            }
            
            table.table_show_register tbody tr td textarea.register{
                min-height: 80px!important;
            }
            
            /*error*/
            table thead tr.error{
                background-color: #6d1300!important;
            }
            
            /*disabled*/
            table.disabled{
                background-color: #686868!important;
            }
            table.disabled thead tr th{
                color: #686868!important;
            }
            
            table tfoot{
                display:none;
            }

            @media screen and (max-width: 600px) {
                table thead tr th:last-child{
                    border-radius: 0 8px 0 0;
                }
                
                table.responsive-table thead tr th:last-child{
                    border-radius: 0 0 0 8px!important;
                }
                
                /*table thead tr th.ultimo{
                    width: 40px;
                	padding: 0;
                }
                
                table thead tr th.ultimo,
                table tbody tr td.ultimo{
                    width: 100%!important;
                }
                
                table thead th.ultimo-buttons,
                table tbody tr td.ultimo-buttons{
                    height: 40px!important;
                }
                
                table tbody tr td.ultimo{
                    height: 40px!important;
                    display: flex!important;
                    flex-wrap: wrap!important;
                    justify-content: center!important;
                }*/
                
                table.responsive-table thead tr th.ultimo,
                table.responsive-table tbody tr td.ultimo{
                    width: 100%!important;
                }
                
                table tbody tr.trTfoot{
                    display:none!important;
                }

                table tbody tr td{
                    height: 40px!important;
                }
                
                table tbody tr:last-child td:first-child{
                    border-radius: 0 8px 0 0;
                }
                
                table tbody tr:last-child td:last-child{
                    border-radius: 0 0 8px 0;
                }
                
                table tfoot{
                    display:contents!important;
                }   
            }';
    return $cargaPDFAuth;
  }

  public function generaPdf($cssArea, $area, $submarea, $nombreDoc, $logotipo, $nomEmpr, $urlEmp, $dirEmp, $fechaAlta, $contenidoPdf){
    $cargaPDFAuth = '<!doctype html>
        <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Invoice - #123</title>
                <style type="text/css">
                    @page {
                        margin: 0px;
                    }
                    body {
                        margin: 0px;
                    }
                    * {
                        font-family: Verdana, Arial, sans-serif;
                    }
                    a {
                        color: #fff;
                        text-decoration: none;
                    }
                    table {
                        font-size: x-small;
                    }
                    table.contenido{
                        color: #353535;
                    }
                    table.contenido thead{
                        background-color: lightblue;
                    }
                    table.contenido tbody{
                        background-color: #e7e7ea;
                    }
                    table.contenido thead tr th,
                    table.contenido tbody tr td{
                        text-align: center;
                    }
                    table.contenido tbody tr td{
                        text-transform: lowercase;
                    }
                    table.contenido tbody tr:last-child td:first-child{
                        border-radius: 0 0 0 4px;
                    }
                    table.contenido tbody tr:last-child td:last-child{
                        border-radius: 0 0 4px 0;
                    }
                    tfoot tr td {
                        font-weight: bold;
                        font-size: x-small;
                    }
                    .invoice table {
                        margin: 15px;
                    }
                    .invoice h3 {
                        margin-left: 15px;
                    }
                    .information {
                        color: #FFF;
                    }
                    .information-cpc{
                        background-color: #2D7D17;
                    }
                    .information-cpp{
                        background-color: #e90000;
                    }
                    .information-fnz{
                        background-color: #353535;
                    }
                    .information-vhm{
                        background-color: #f1d600;
                    }
                    .information-cnt{
                        background-color: #0044ff;
                    }
                    .information-tnf{
                        background-color: #c700ff;
                    }
                    .information .logo {
                        margin: 5px;
                    }
                    .information table {
                        padding: 10px;
                    }
                    .divLogo img{
                        width: 100px;
                        height: 100px;
                    }
                    .divLogo img.logotipo{
                        border-radius: 50%;
                        border: 1px ouset #353535;
                    }
                </style>
            </head>
            <body>
                <div class="information ' . $cssArea . '">
                    <table width="100%">
                        <tr>
                            <td align="left" style="width: 40%;">
                                <h3>' . $area . '</h3>
                                <pre>' . $submarea . '<br/><br/>' . $nombreDoc . '
                                </pre>
                            </td>
                            <td align="center">
                                <img src="' . $logotipo . '" alt="Logo" width="64" class="logo"/>
                            </td>
                            <td align="right" style="width: 40%;">
                                <h3>' . $nomEmpr . '</h3>
                                <pre>
                                    ' . $urlEmp . '
                                    ' . $dirEmp . '
                                    ' . $fechaAlta . '
                                </pre>
                            </td>
                        </tr>
                    </table>
                </div>
                <br/>
                <div class="invoice">' . $contenidoPdf . '</div>
                <div class="information ' . $cssArea . '" style="position: absolute; bottom: 0;">
                    www.sos-mexico.com.mx
                </div>
            </body>
        </html>';
    return $cargaPDFAuth;
  }

  public function css_pdf(){
    $stylo_css = "
            @page {margin: 20px 20px;}
            body{font-family: sans-serif;margin: 0px;}
            header {border-radius: 8px;position: fixed;left: 0px;height: 105px;top: 0px;right: 0px;text-align: center;}
            header h1{margin: 10px 0;}
            header h2{margin: 0 0 10px 0;}
            header table tr td img.logotipo{border-radius: 50%;border: 1px ouset #353535;}
            a {color: #fff;text-decoration: none;}
            table{font-size: x-small;}
            main table{width: 100%;color: #353535;margin-top: 5px;margin-bottom: 1%;border-radius: 8px;box-shadow: 2px 2px 10px #353535!important;}
            main table.transparent_table{background-color: rgba(53,53,53,0.2)!important;}
            /*thead F1F1F1*/
            main table thead tr th{text-align: center;font-size: 13px;height: 30px;margin: 0;padding: 0;background-color: #F6F6F6!important;color: #353535!important;text-transform: uppercase;border-radius: 0px;}
            main table thead tr th:first-child{border-radius: 8px 0 0 0;}
            main table thead tr th:last-child{border-radius: 0 8px 0 0;}
            /*tbody*/
            //div.card{border: 2px solid #D3D3D3;border-radius: 8px;padding:5px;margin-bottom: 5px;}
            div.card{border-radius: 8px;padding:5px;margin-bottom: 5px;}
            main table tbody tr{border-bottom: 1px solid #353535;}
            main table tbody tr:last-child{border-bottom: none;}
            main table tbody tr td{color: #353535;min-height: 30px!important;text-align: center;padding: 0px 5px;margin-bottom: 0;font-size: 15px;}
            main table tbody tr td p{width: 100%!important;margin: 0;text-align: center;}
            main table tbody tr:last-child td:first-child{border-radius: 0 0 0 8px;}  
            main table tbody tr:last-child td:last-child{border-radius: 0 0 8px 0;}
            
            main table tfoot tr th,
            main table tfoot tr td{color: #e7e7ea;}
            main table tfoot tr th,
            main table tfoot tr td{text-align: center;padding: 5px!important;}
            
            table.contenido{color: #353535;}
            table.contenido thead{background-color: lightblue;}
            table.contenido tbody{background-color: #e7e7ea;}
            table.contenido thead tr th,
            table.contenido tbody tr td{text-align: center;}
            table.contenido tbody tr td{text-transform: lowercase;}
            table.contenido tbody tr:last-child td:first-child{border-radius: 0 0 0 4px;}
            table.contenido tbody tr:last-child td:last-child{border-radius: 0 0 4px 0;}
            .invoice table {margin: 15px;}
            .invoice h3 {margin-left: 15px;}
            //.information {color: #FFF;}
            //.information-cpp{background-color: #353535;}
            .information .logo {margin: 5px;}
            .information table {padding: 10px;}
            .divLogo img{width: 100px;height: 100px;}
            .divLogo img.logotipo{border-radius: 50%;border: 1px ouset #353535;}
            main {position: absolute;left: 0px;top: 105px;right: 0px;}
            
            main article h1,
            main article h2,
            main article h3,
            main article h4,
            main article h5,
            main article h6 {width: 100%;color: #353535;text-align: center;margin: 3px;height: auto;text-transform: uppercase;font-family: ubuntu, FontAwesome;font-family: sans-serif, FontAwesome;}
            
            //main article table{border: 1px solid #353535!important;}
            
            footer {position: fixed;left: 0px;bottom: 0px;right: 0px;border-bottom: 2px solid #ddd;}
            footer .page:after {content: counter(page);}
            footer table {width: 100%;font-size: 15px;}
            footer p {text-align: right;}
            footer .izq {text-align: left;}
        ";
    return $stylo_css;
  }

  public function base64url_encode($data){
    // First of all you should encode $data to Base64 string
    $b64 = base64_encode($data);

    // Make sure you get a valid result, otherwise, return FALSE, as the base64_encode() function do
    if ($b64 === false) {
      return false;
    }

    // Convert Base64 to Base64URL by replacing “+” with “-” and “/” with “_”
    $url = strtr($b64, '+/', '-_');

    // Remove padding character from the end of line and return the Base64URL result
    return rtrim($url, '=');
  }

  /**
   * Decode data from Base64URL
   * @param string $data
   * @param boolean $strict
   * @return boolean|string
   */
  public function base64url_decode($data, $strict = false){
    // Convert Base64URL to Base64 by replacing “-” with “+” and “_” with “/”
    $b64 = strtr($data, '-_', '+/');
    // Decode Base64 string and return the original data
    return base64_decode($b64, $strict);
  }

  public function encriptaBase64($path){
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
  }

  public function encriptaBase64Pdf($path){
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    return 'data:application/' . $type . ';base64,' . base64_encode($data);
  }

  public function encriptaBase64Xml($path){
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    return 'data:application/' . $type . ';base64,' . base64_encode($data);
  }

  public static function encriptarToken($texto){
    $key = openssl_random_pseudo_bytes(64) . rand(1, 1000);
    $iv = "1234567812345678";
    $encripta = openssl_encrypt($texto, "aes-256-cbc", $key, 0, $iv);
    return base64_encode($encripta . "::" . $iv);
  }

  public static function encriptarCredenciales($texto){
    $key = 'textoencriptado';
    $iv = "1234567812345678";
    $encripta = openssl_encrypt($texto, "aes-256-cbc", $key, 0, $iv);
    return Hash::make(base64_encode($encripta . "::" . $iv), ['rounds' => 12]);
  }

  public static function encriptarAccessClaves($texto){
    $key = 'textoencriptado';
    $iv = "1234567812345678";
    $encripta = openssl_encrypt($texto, "aes-256-cbc", $key, 0, $iv);
    return base64_encode($encripta . "::" . $iv);
  }

  public static function encriptar($texto){
    $key = 'textoencriptado';
    // Generamos IV de 16 bytes con tu toque personalizado
    $base = 'macher10#@!_' . bin2hex(random_bytes(6));
    $iv = substr(hash('sha256', $base), 0, 16);
    // Cifrado AES-256-CBC
    $cifrado = openssl_encrypt($texto, 'aes-256-cbc', $key, 0, $iv);
    // Guardamos todo en un formato estándar (JSON base64)
    return base64_encode(json_encode([
      'data' => $cifrado,
      'iv' => base64_encode($iv)
    ]));
  }

  public static function encriptar_direccion($texto){
    $key = 'cc5c83b7b57ff913c3bb882d847_her_f402cf60a6b7e60d4299d8fc04d5_mac_1674f1c3a';
    // Generamos IV de 16 bytes con tu toque personalizado
    $base = 'macher10#@!_' . bin2hex(random_bytes(6));
    $iv = substr(hash('sha256', $base), 0, 16);
    // Cifrado AES-256-CBC
    $cifrado = openssl_encrypt($texto, 'aes-256-cbc', $key, 0, $iv);
    // Guardamos todo en un formato estándar (JSON base64)
    return base64_encode(json_encode([
      'data' => $cifrado,
      'iv' => base64_encode($iv)
    ]));
  }

  public static function encriptarDireccion($texto) {
    // 1. Forzar una llave limpia de 32 bytes (256 bits) para cumplir el estándar AES-256
    $key = substr(hash('sha256', 'cc5c83b7b57ff913c3bb882d847_her_f402cf60a6b7e60d4299d8fc04d5_mac_1674f1c3a'), 0, 32);

    // 2. Limpiar el texto (quitar espacios extras y pasarlo a minúsculas)
    // Esto garantiza que "Cedis" y "cedis " generen exactamente el mismo hash
    $textoOriginal = trim($texto);

    // Usamos el texto en minúsculas SOLO para generar el IV.
    // Esto garantiza que "CEDIS" y "cedis" generen el mismo IV y el mismo hash final.
    $textoParaIV = strtolower($textoOriginal);
    $iv = substr(hash('sha256', $textoParaIV . '51fffebef1f76fb70f0ef6760ad1607f806e3c3de296caa73e271029dfb399ee'), 0, 16);

    // Ciframos el texto ORIGINAL (con sus mayúsculas intactas)
    $cifrado = openssl_encrypt($textoOriginal, 'aes-256-cbc', $key, 0, $iv);

    return base64_encode(json_encode([
      'data' => $cifrado,
      'iv'   => base64_encode($iv)
    ]));
  }

  public static function desencriptarDireccion($textoCifrado) {
    $masterKey = 'cc5c83b7b57ff913c3bb882d847_her_f402cf60a6b7e60d4299d8fc04d5_mac_1674f1c3a';
    $key = substr(hash('sha256', $masterKey), 0, 32);
    
    $package = json_decode(base64_decode($textoCifrado), true);
    if (!isset($package['data']) || !isset($package['iv'])) {
      return $textoCifrado;
    }

    $iv = base64_decode($package['iv']);
    
    // Devuelve el texto original tal cual se escribió (Ej: "CEDIS Monterrey Central")
    return openssl_decrypt($package['data'], 'aes-256-cbc', $key, 0, $iv);
  }

  public static function desencriptar($texto){
    //$key = 'textoencriptado';
    ////$iv = "1234567812345678";
    //$base = 'macher10#@!_' . bin2hex(random_bytes(6));
    //$iv = substr(hash('sha256', $base), 0, 16);
    //list($d_cifrado, $iv) = explode('::', base64_decode($texto), 2);
    //return openssl_decrypt($d_cifrado, 'aes-256-cbc', $key, 0, $iv);


    $key = 'textoencriptado';
    $decodedBase64 = base64_decode($texto);

    // Detecta si el texto es formato JSON (nuevo método)
    $jsonTest = json_decode($decodedBase64, true);
    if (is_array($jsonTest) && isset($jsonTest['data']) && isset($jsonTest['iv'])) {
      // === NUEVO FORMATO ===
      $iv = base64_decode($jsonTest['iv']);
      if (strlen($iv) < 16) $iv = str_pad($iv, 16, "\0");
      else $iv = substr($iv, 0, 16);
      return openssl_decrypt($jsonTest['data'], 'aes-256-cbc', $key, 0, $iv);
    }

    // === FORMATO ANTIGUO ===
    // Separar el texto cifrado y el IV del formato antiguo "cifrado::iv"
    if (strpos($decodedBase64, '::') !== false) {
      list($encrypted_data, $old_iv) = explode('::', $decodedBase64, 2);

      // Asegurar que el IV tenga 16 bytes
      $old_iv = substr($old_iv, 0, 16);
      return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $old_iv);
    }

    // Si no entra en ninguno, devolver nulo
    return null;
  }

  public function desencriptarNombres($paterno, $materno, $nombres){
    $key = 'textoencriptado';
    $iv = "1234567812345678";

    //list($pat_cifrado, $iv) = explode('::', base64_decode($paterno), 2);
    //$txt_pat = openssl_decrypt($pat_cifrado, 'aes-256-cbc', $key, 0, $iv);
    //list($mat_cifrado, $iv) = explode('::', base64_decode($materno), 2);
    //$txt_mat = openssl_decrypt($mat_cifrado, 'aes-256-cbc', $key, 0, $iv);
    //list($names_cifrado, $iv) = explode('::', base64_decode($nombres), 2);
    //$txt_names = openssl_decrypt($names_cifrado, 'aes-256-cbc', $key, 0, $iv);
    $txt_pat = $this->desencriptar($paterno);
    $txt_mat = $this->desencriptar($materno);
    $txt_names = $this->desencriptar($nombres);
    return ucfirst($txt_pat) . " " . ucfirst($txt_mat) . " " . ucwords($txt_names);
  }

  public function encryptBankAccount($plainText){
    $key = base64_decode(env('sosencriptadordetextos'));
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
  }

  /*public function decryptBankAccount($cipherText){
      $key = base64_decode(env('sosencriptadordetextos'));
      $data = base64_decode($cipherText);
      $iv = substr($data, 0, 16);
      $encrypted = substr($data, 16);
      return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }*/

  public function decryptBankAccount($cipherText){
    $key = base64_decode(env('sosencriptadordetextos'));
    $data = base64_decode($cipherText);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    // Asegurar que la cadena sea UTF-8 válida
    if ($decrypted !== false && !mb_check_encoding($decrypted, 'UTF-8')) {
      $decrypted = mb_convert_encoding($decrypted, 'UTF-8', 'ISO-8859-1'); // o Latin1
    }
    return $decrypted;
  }

  public static function utf8Validate($texto){
    if (mb_detect_encoding(utf8_decode($texto), 'UTF-8', true) === false) {
      $text_utf8 = ucfirst(strtolower($texto));
    } else {
      $text_utf8 = ucfirst(strtolower(utf8_decode($texto)));
    }
    return $text_utf8;
  }

  public static function encSecureData($texto){
    $key = 'textoencriptado';
    $iv = "1234567812345678";
    $encripta = base64_encode($texto . "::" . $iv);
    return openssl_encrypt($encripta, 'AES-128-CBC', $key, 0, $iv);
  }

  public static function encriptarRegistro($texto, $length = 40){
    $key = 'textoencriptado';
    $iv = "1234567812345678";
    $encripta = openssl_encrypt($texto, "aes-256-cbc", $key, 0, $iv);
    return substr(base64_encode($encripta . "::" . $iv), 0, $length);
  }

  public static function generarFolio($texto){
    $resultado = "";
    if (strlen($texto) == 1) {
      $resultado = '00000000' . $texto;
    } else if (strlen($texto) == 2) {
      $resultado = '0000000' . $texto;
    } else if (strlen($texto) == 3) {
      $resultado = '000000' . $texto;
    } else if (strlen($texto) == 4) {
      $resultado = '00000' . $texto;
    } else if (strlen($texto) == 5) {
      $resultado = '0000' . $texto;
    } else if (strlen($texto) == 6) {
      $resultado = '000' . $texto;
    } else if (strlen($texto) == 7) {
      $resultado = '00' . $texto;
    } else if (strlen($texto) == 8) {
      $resultado = '0' . $texto;
    } else {
      $resultado = $texto;
    }
    return $resultado;
  }

  public static function generarPostFolio($texto){
    $resultado = "";
    if ($texto == '') {
      $resultado = chr(97);
    } else if (strlen($texto) == 1) {
      if ($texto == 'z') {
        $resultado = chr(97) . chr(97);
      } else {
        $resultado = chr(ord($texto) + 1);
      }
    } else if (strlen($texto) > 1) {
      $text_texto = $texto[strlen($texto) - 1];
      //$resultado = chr(ord($text_texto)+1);
      if ($text_texto == 'z') {
        $compila_text = '';
        for ($i = 0; $i <= strlen($texto); $i++) {
          $compila_text = $compila_text . chr(97);
        }
        $resultado = $compila_text;
      }
    }
    return $resultado;
  }

  public static function generar($texto){
    $resultado = "";
    if ($texto < 10) {
      $resultado = '000' . $texto;
    } else if ($texto < 100 && $texto >= 10) {
      $resultado = '00' . $texto;
    } else if ($texto < 1000 && $texto >= 100) {
      $resultado = '0' . $texto;
    } else {
      $resultado = $texto;
    }
    return $resultado;
  }

  public function selectBitacoraActividad($area, $subarea1, $subarea2, $comp_token, $token_usuario){
    $arrayActividad = array();
    $select_bitacora = DB::select(
      "SELECT bit_act.token_bitacora,bit_act.fecha_bitacora,
            bit_act.folio_bitacora,bit_act.area,bit_act.subarea1,bit_act.subarea2,bit_act.folio_relacionado,
            bit_act.actividad,people.paterno,people.materno,people.nombre FROM teci_bitacora_actividad AS bit_act
            JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
            WHERE bit_act.area = ? AND bit_act.subarea1 = ? AND bit_act.subarea2 = ?
            AND bit_act.empresa = emp.id AND emp.empresa_token = ? AND bit_act.usuario = users.id
            AND users.usuario_token = ? AND users.empleado = pers.id AND pers.empleado_name = people.id",
      [$area, $subarea1, $subarea2, $comp_token, $token_usuario]
    );
    if (count($select_bitacora) != 0) {
      foreach ($select_bitacora as $valBit) {
        $aeachFor = array(
          "token_bitacora" => $valBit->token_bitacora,
          "fecha_bitacora" => $this->mostrarUnixAFechaMexico($valBit->fecha_bitacora),
          "folio_bitacora" => $this->generarFolio($valBit->folio_bitacora),
          "folio_relacionado" => $valBit->folio_relacionado,
          "actividad" => $valBit->actividad,
          "usuario_relacionado" => $this->desencriptar($valBit->paterno) . ' ' .
            $this->desencriptar($valBit->materno) . ' ' . $this->desencriptar($valBit->nombre),
        );
        $arrayActividad[] = $aeachFor;
      }
      return $arrayActividad;
    } else {
      return $arrayActividad;
    }
  }

  public function insertBitacoraActividad($area, $subarea1, $subarea2, $folio_relacionado, $actividad, $comp_token, $token_usuario){
    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,users.id AS userId FROM main_empresas AS emp
			JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
			AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$comp_token, $token_usuario]);

    date_default_timezone_set($selectEmp[0]->zona_horaria);

    $folioBitacora = DB::select("SELECT IF (max(bit_act.folio_bitacora) IS NOT NULL,
			(max(bit_act.folio_bitacora)+1),1) AS folio FROM teci_bitacora_actividad AS bit_act
			JOIN main_empresas AS emp WHERE bit_act.empresa = emp.id AND emp.empresa_token = ?", [$comp_token]);

    $fecha = time();

    $tokenBiracora = $this->encriptarToken($folioBitacora[0]->folio . $fecha . $area .
      $subarea1 . $subarea2 . $folio_relacionado . $actividad . $comp_token . $token_usuario);

    $insertBitacora = DB::table('teci_bitacora_actividad')
      ->insert(array(
        "token_bitacora" => $tokenBiracora,
        "folio_bitacora" => $folioBitacora[0]->folio,
        "fecha_bitacora" => $fecha,
        "area" => $area,
        "subarea1" => $subarea1,
        "subarea2" => $subarea2,
        "folio_relacionado" => $folio_relacionado,
        "actividad" => $actividad,
        "usuario" => $selectEmp[0]->userId,
        "empresa" => $selectEmp[0]->id,
      ));
  }

  public function insertBitacoraProv($token_proveedor, $actividad, $comp_token, $token_usuario){
    $selectProvCat = DB::select("SELECT id,folio,post_folio FROM catalogo_proveedores
            WHERE token_cat_proveedores = ?", [$token_proveedor]);
    $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,users.id AS userId FROM main_empresas AS emp
			JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
			AND emp.id = empuser.empresa AND empuser.empleado_name = pers.id
			AND pers.id = users.empleado AND users.usuario_token= ?", [$comp_token, $token_usuario]);

    date_default_timezone_set($selectEmp[0]->zona_horaria);

    $folioBitacora = DB::select("SELECT IF (max(bit_prv.folio_bitacora_prov) IS NOT NULL,
		    (max(bit_prv.folio_bitacora_prov)+1),1) AS folio FROM catalogo_proveedores_bitacora AS bit_prv
		    JOIN main_empresas AS emp WHERE bit_prv.empresa = emp.id AND emp.empresa_token = ?", [$comp_token]);

    $fecha = time();

    $tokenBiracora = $this->encriptarToken($folioBitacora[0]->folio . $fecha . $actividad . $comp_token . $token_usuario);

    $insertBitacora = DB::table('catalogo_proveedores_bitacora')
      ->insert(array(
        "token_bitacora_prov" => $tokenBiracora,
        "folio_bitacora_prov" => $folioBitacora[0]->folio,
        "fecha_bitacora_prov" => $fecha,
        "proveedor" => $selectProvCat[0]->id,
        "actividad" => $actividad,
        "usuario" => $selectEmp[0]->userId,
        "empresa" => $selectEmp[0]->id,
      ));
  }

  public static function fechaEpoc(){
    $fecha = time();
    return $fecha;
  }

  public static function convierteFechaCFDIUnix($fecha){
    if (empty($fecha)) {
      return null;
    }

    $objetoFecha = Carbon::parse($fecha, 'America/Mexico_City');

    return $objetoFecha->startOfDay()->timestamp;
  }

  public static function convierteFechaEpoc($fecha){
    //echo $fecha;
    //return strtotime($fecha);

    if (empty($fecha)) {
      return null;
    }

    // Fuerza a que la medianoche calculada sea la de México
    return Carbon::createFromFormat('Y-m-d', $fecha, 'UTC')
      ->startOfDay()
      ->timestamp;
  }

  public function corregirTimestampUnix_Historico(?string $unixInput): ?int{
    //$fecha_salida = "";
    if (empty($unixInput)) {
      return null;
    }

    // 1. Creamos la fecha leyendo el timestamp desde UTC
    $fecha = Carbon::createFromTimestamp((int)$unixInput, 'UTC');
    //return $fecha->hour;

    // 2. Si la hora es las 06:00:00, significa que se le sumó el desfase de la CDMX (UTC-6)
    if ($fecha->hour == 6) {
      // Extraemos solo el día (Y-m-d) ignorando esas 6 horas sobrantes,
      // y generamos el timestamp Unix real de la medianoche de México
      //$fecha_salida = Carbon::parse($fecha->toDateString(), 'America/Mexico_City')->startOfDay()->timestamp;
      //return "horas ".$fecha->hour." timestamp ".$fecha_salida;
      return Carbon::parse($fecha->toDateString(), 'America/Mexico_City')->startOfDay()->timestamp;
    }

    // 3. Si la hora es 00:00:00 (u otra), garantizamos que se convierta 
    // al equivalente exacto de la medianoche en la fecha local de México
    return Carbon::parse($fecha->toDateString(), 'America/Mexico_City')->startOfDay()->timestamp;
  }

  public function corregirTimestampUnixHistorico(?string $unixInput): ?int{
    if (empty($unixInput)) {
      return null;
    }

    // 1. Creamos la fecha leyendo el timestamp actual desde UTC plano
    $fecha = Carbon::createFromTimestamp((int)$unixInput, 'UTC');

    // 2. Si la hora es las 06:00:00, significa que tenía el desfase de CDMX.
    // Aislamos el día limpio (ej: "2025-04-09") y calculamos la medianoche ABSOLUTA en UTC.
    if ($fecha->hour == 6) {
      return Carbon::parse($fecha->toDateString(), 'UTC')
        ->startOfDay()
        ->timestamp; // Esto generará el entero limpio terminado en 4400 (ej: 1744154400)
    }

    // 3. Si la hora es 00:00:00 u otra, garantizamos el mismo criterio plano en UTC.
    return Carbon::parse($fecha->toDateString(), 'UTC')
      ->startOfDay()
      ->timestamp;
  }

  public static function mostrarUnixAFechaMexico(?string $unixInput): string{
    if (empty($unixInput)) {
      return '';
    }

    // Leemos el entero en UTC de forma estricta para que el formateador format('Y-m-d')
    // extraiga el día exacto guardado, bloqueando alteraciones por zonas horarias del servidor.
    return \Carbon\Carbon::createFromTimestamp((int)$unixInput, 'UTC')->format('Y-m-d');
  }

  public static function convierteEpocFechaHtml($zona_horaria, $fecha){
    $resultado = "";
    date_default_timezone_set($zona_horaria);
    $resultado = date('Y-m-d', $fecha);
    //$resultado = strtotime($dataFecha);
    return $resultado;
  }

  public static function convierteEpocFechaHtmlMY($zona_horaria, $fecha){
    $resultado = "";
    date_default_timezone_set($zona_horaria);
    $resultado = date('Y-m', $fecha);
    //$resultado = strtotime($dataFecha);
    return $resultado;
  }

  public static function convierteEpocFecha($zona_horaria, $fecha){
    $resultado = "";
    date_default_timezone_set($zona_horaria);
    $resultado = date('d-m-Y', $fecha);
    //$resultado = strtotime($dataFecha);
    return $resultado;
  }

  public static function convierteEpocFechaHrs($fecha){
    $resultado = "";
    $replaceFecha = str_replace("/", "-", $fecha);
    $resultado = strtotime($replaceFecha);
    return $resultado;
  }

  public static function conversionCuotaPorcentaje($precioBase, $cuoPorc, $importe){
    $totalConversion = '';
    if ($cuoPorc == TRUE) {
      $importeBase = explode("%", $precioBase);
      $multi = '';
      if ($importeBase[0] > 0 && $importeBase[0] < 1) {
        $importeBase2 = explode(".", $importeBase[0]);
        $multi = '0.00' . $importeBase2[1];
      } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
        $multi = '0.0' . $importeBase[0];
      } else if ($importeBase[0] > 10 && $importeBase[0] < 100) {
        $multi = '0.' . $importeBase[0];
      } else if ($importeBase[0] == 100) {
        $multi = 1;
      }
      $totalConversion =  $importe * floatval($multi);
    } else {
      $importeBase = str_replace("$", "", $precioBase);
      $importeBase = str_replace(",", "", $importeBase);
      $totalConversion = floatval($importeBase);
    }
    return $totalConversion;
  }

  public static function conversionPositivos($texto){
    $totalConversion = '';
    $totalConversion = str_replace("-", "", $texto);
    return number_format($totalConversion, 2, '.', ',');
  }

  public function checkToken($jwt, $getIdentify = false){
    //$getIdentify informacion decodificada del teci_usuarios
    //echo $jwt;
    //$auth = false;
    //try {
    //  $jwt = str_replace('"', '', $jwt);
    //  $decoded = JWT::decode($jwt, new Key($this->key, 'HS256'));
    //} catch (\UnexpectedValueException $e) {
    //  $auth = false;
    //} catch (\DomainException $e) {
    //  $auth = false;
    //}
    //if (!empty($decoded) && is_object($decoded) && isset($decoded->sub)) {
    //  $auth = true;
    //} else {
    //  $auth = false;
    //}
    //if ($getIdentify) {
    //  return $decoded;
    //}
    //return $auth;
    $auth = false;
    $decoded = null; // Inicializamos para evitar el error "Undefined variable"

    try {
      $jwt = str_replace('"', '', $jwt);
      // Usamos la llave de la clase
      $key = config('services.jwt.secret');
      //$jwt = JWT::encode($token_payload, $key, 'HS256');

      $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
    } catch (\Exception $e) {
      // Si hay cualquier error (expirado, mal formado), auth sigue siendo false
      $auth = false;
    }

    // Verificamos que se haya decodificado algo y que tenga datos
    // Nota: He quitado 'isset($decoded->sub)' porque en tu nuevo token usas 'user_token'
    if (!empty($decoded) && is_object($decoded)) {
      $auth = true;
    }

    if ($getIdentify) {
      return $decoded; // Si falló, devolverá null de forma segura
    }

    return $auth;
  }

  public function permisosCreacion($menu, $company, $usuario){
    $queryPermMenu = PermisosModelo::join("main_empresas AS emp", "teci_permisos_usuario.empresa", "emp.id")
      ->join("teci_permisos_menu AS permenu", "teci_permisos_usuario.menu", "permenu.id")
      ->leftjoin("teci_usuarios_catalogo AS users", "teci_permisos_usuario.uprincipal", "users.id")
      ->where([
        "permenu.token_permisos_menu" => $menu,
        "teci_permisos_usuario.empleado" => NULL,
        "teci_permisos_usuario.uprincipal" => !NULL,
        "emp.empresa_token" => $company,
        "users.usuario_token" => $usuario
      ])
      ->orwhere([
        "permenu.token_permisos_menu" => $menu,
        "teci_permisos_usuario.empleado" => !NULL,
        "teci_permisos_usuario.uprincipal" => NULL
      ])
      ->leftjoin("vhum_empleados_catalogo AS pers", "teci_permisos_usuario.empleado", "pers.id")
      ->leftjoin("teci_usuarios_catalogo AS users2", "pers.id", "users2.empleado")
      ->where([
        "emp.empresa_token" => $company,
        "users2.usuario_token" => $usuario
      ])->first();

    return $queryPermMenu && $queryPermMenu->f_crear ? true : false;
  }

  public function rellenaImportesCompras($texto){
    return number_format((float)$texto, 6, '.', '');
    //$explodeImporte = explode('.',$texto);
    //$resultado = "";
    //if (strlen($explodeImporte[1]) == 1) {
    //    $resultado = $texto.'00000';
    //} else if (strlen($explodeImporte[1]) == 2) {
    //    $resultado = $texto.'0000';
    //} else if (strlen($explodeImporte[1]) == 3) {
    //    $resultado = $texto.'000';
    //} else if (strlen($explodeImporte[1]) == 4) {
    //    $resultado = $texto.'00';
    //} else if (strlen($explodeImporte[1]) == 5) {
    //    $resultado = $texto.'0';
    //} else if (strlen($explodeImporte[1]) == 6) {
    //    $resultado = $texto;
    //}
    //return $resultado;
  }

  public function enviaSMS($numero_telefono, $content_mensaje){
    $configuracion_sms = new \Vonage\Client\Credentials\Basic("c40297ee", "I2V1J9UIJNILur1Z");
    $client_sms = new \Vonage\Client($configuracion_sms);
    $envia_sms = $client_sms->sms()->send(new \Vonage\SMS\Message\SMS($numero_telefono, "SOS-Mexico", $content_mensaje));
    $message = $envia_sms->current();
  }

  public function insertGeneralNotif($asunt, $titulo_alerta, $typo, $area, $subarea, $empresa, $emisor, $receptor){
    $queryReceptor = DB::select("SELECT users.usuario_token,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado
        FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN teci_usuarios_catalogo AS users WHERE pers.id = ? 
        AND pers.id = tel_pers_resp.personal AND pers.id = users.empleado", [$receptor]);

    foreach ($queryReceptor as $vRecibe) {
      $token_notificacion = $this->encriptarToken($titulo_alerta, $area, $subarea, $empresa, $emisor, $receptor);
      $insertAlertaProyecto = DB::table('teci_notificaciones')
        ->insert(array(
          "token_notificacion" => $token_notificacion,
          "fecha_notificacion" => time(),
          "type" => $typo,
          "asunto" => $asunt,
          "titulo" => $this->encriptar($titulo_alerta),
          "area" => $area,
          "subarea" => $subarea,
          "empresa" => $empresa,
          "emisor" => $emisor,
          "receptor" => $receptor,
          "visto" => FALSE,
          "status_recibe" => FALSE,
          "status_delete" => TRUE,
          "fecha_delete" => NULL,
        ));

      $queryDevices = DB::table('teci_usuarios_dispositivos AS device')
        ->join("teci_usuarios_catalogo AS users", "device.usuario", "=", "users.id")
        ->where("users.usuario_token", $vRecibe->usuario_token)->get();
      foreach ($queryDevices as $vDev) {
        $this->notificacionPushDevices($vDev->dispositivo_token, $asunt, $titulo_alerta);
      }

      if ($vRecibe->habilitado == TRUE) {
        $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.titulo,
                emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre 
                FROM teci_notificaciones AS alert JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS emisor 
                WHERE alert.token_notificacion = ? AND alert.emisor = emisor.id
                AND emisor.personal = pers_people.id", [$token_notificacion]);
        foreach ($alertaList as $valAlert) {
          $mensaje = $this->desencriptar($valAlert->titulo);
          $token_emisor = $valAlert->pers_token;
          $emisor = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " . $this->desencriptar($valAlert->nombre);
          $phone_numero = "+" . $vRecibe->pais_code . $this->desencriptar($vRecibe->phone);
          $this->enviaSMS($phone_numero, $emisor . ': ' . $mensaje);
        }
      }
    }
  }

  //apps externas
  public function insertNotificacionSistema($typo, $asunt, $titulo_alerta, $reembolso_main, $reembolso_solicitud, $empresa, $emisor, $receptor){
    //echo $titulo_alerta;
    $token_notificacion = $this->encriptarToken($titulo_alerta, $reembolso_main, $reembolso_solicitud, $empresa, $emisor, $receptor);
    DB::table('teci_notificaciones')
      ->insert(
        array(
          "id" => Str::uuid()->toString(),
          "token_notificacion" => $token_notificacion,
          "fecha_notificacion" => time(),
          "type" => $typo,
          'notifiable_type' => User::class,
          "asunto" => $titulo_alerta,
          "data" => json_encode([
            "titulo" => $titulo_alerta,
            "accion" => $asunt,
            "reembolso_main" => $reembolso_main,
            "reembolso_solicitud" => $reembolso_solicitud,
          ]),
          "empresa" => $empresa,
          "notifiable_id" => $receptor,
          "visto" => FALSE,
          "status_recibe" => FALSE,
          "status_delete" => TRUE,
          "fecha_delete" => NULL,
        )
      );

    /*$queryReceptor = DB::table("vhum_empleados_catalogo AS pers")
      ->join("sos_personas AS people", "pers.empleado_name", "people.id")
      ->where("pers.id", $receptor)->get();
    foreach ($queryReceptor as $vRec) {

      $telQuery = DB::table("sos_personas_telefonos AS tels")
        ->join("vhum_empleados_catalogo AS pers", "tels.personal", "pers.id")
        ->where("pers.empleado_token", $vRec->empleado_token)
        ->select("tels.telefono", "tels.habilitado")
        ->first();

      if ($telQuery && $telQuery->habilitado) {
        $emisor = $this->desencriptarNombres($vRec->paterno, $vRec->materno, $vRec->nombre);
        $phone_numero = $this->desencriptar($telQuery->telefono);
        try {
          $this->enviaSMS($phone_numero, $emisor . ': ' . $titulo_alerta);
        } catch (\Vonage\Client\Exception\Request $e) {
          // Si el error es "Quota Exceeded", no hacemos nada
          if (strpos($e->getMessage(), 'Quota Exceeded') !== false) {
            return; // Silencio absoluto
          }

          // Otros errores sí se pueden loguear
          \Illuminate\Support\Facades\Log::error('Error Vonage: ' . $e->getMessage());
        }
      }
    }*/
  }

  public function insertNotifCr($asunt, $token_proyecto, $titulo_alerta, $tarea, $informe, $empresa, $emisor, $control){
    $creador = DB::select(
      "SELECT proy.id AS proyecto,proy.proyecto_name,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,
            tel_pers_resp.habilitado,users.token_dispositivo_movil,users.token_dispositivo_web
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy
            JOIN teci_usuarios_catalogo AS users
            WHERE pers.id = tel_pers_resp.personal AND tel_pers_resp.principal = TRUE AND pers.id = users.empleado AND pers.id = resp.personal AND resp.tipo_pp = 'cr'
            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
      [$token_proyecto]
    );
    if (count($creador) == 1) {
      $token_notificacion = $this->encriptarToken($titulo_alerta, $tarea, $informe, $empresa, $emisor, $creador[0]->id);
      $insertAlertaProyecto = DB::table('teci_notificaciones')
        ->insert(
          array(
            "token_notificacion" => $token_notificacion,
            "fecha_notificacion" => time(),
            "type" => "gespr",
            "asunto" => $asunt,
            "titulo" => $this->encriptar($titulo_alerta),
            "proyecto" => $creador[0]->proyecto,
            "tarea" => $tarea,
            "informe" => $informe,
            "control" => $control,
            "empresa" => $empresa,
            "emisor" => $emisor,
            "receptor" => $creador[0]->id,
            "status_recibe" => FALSE,
            "status_delete" => TRUE,
            "visto" => FALSE,
          )
        );

      if ($insertAlertaProyecto) {
        $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.asunto,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                    emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                    JOIN sos_personas AS pers_people JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                    AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                    AND emisor.personal = pers_people.id", [$token_notificacion]);
        foreach ($alertaList as $valAlert) {
          $asunto = "SOS-México - Gestión de proyectos " . $valAlert->asunto;
          $mensaje = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " .
            $this->desencriptar($valAlert->nombre) . " " . $this->desencriptar($valAlert->titulo);
          $phone_numero = "+" . $creador[0]->pais_code . $this->desencriptar($creador[0]->phone);
          $token_emisor = $valAlert->pers_token;

          if ($creador[0]->token_dispositivo_movil != null && $creador[0]->token_dispositivo_movil != "") {
            $this->notificacionPushDevices($creador[0]->token_dispositivo_movil, $asunto, $mensaje);
          }

          if ($creador[0]->token_dispositivo_web != null && $creador[0]->token_dispositivo_web != "") {
            $this->notificacionPushDevices($creador[0]->token_dispositivo_web, $asunto, $mensaje);
          }

          if ($creador[0]->habilitado == TRUE) {
            $this->enviaSMS($phone_numero, $mensaje);
          }
        }
      }
    }
  }

  public function insertNotifLi($asunt, $token_proyecto, $titulo_alerta, $tarea, $informe, $empresa, $emisor, $control){
    $liderToken = DB::select(
      "SELECT proy.id AS proyecto,proy.proyecto_name,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,
            tel_pers_resp.habilitado,users.usuario_token
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy
            JOIN teci_usuarios_catalogo AS users
            WHERE pers.id = tel_pers_resp.personal AND tel_pers_resp.principal = TRUE AND pers.id = users.empleado AND pers.id = resp.personal AND resp.tipo_pp = 'li'
            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
      [$token_proyecto]
    );
    if (count($liderToken) == 1) {
      $token_notificacion = $this->encriptarToken($titulo_alerta, $tarea, $informe, $empresa, $emisor, $liderToken[0]->id);
      $insertAlertaProyecto = DB::table('teci_notificaciones')
        ->insert(
          array(
            "token_notificacion" => $token_notificacion,
            "fecha_notificacion" => time(),
            "type" => "gespr",
            "asunto" => $asunt,
            "titulo" => $this->encriptar($titulo_alerta),
            "proyecto" => $liderToken[0]->proyecto,
            "tarea" => $tarea,
            "informe" => $informe,
            "control" => $control,
            "empresa" => $empresa,
            "emisor" => $emisor,
            "receptor" => $liderToken[0]->id,
            "status_recibe" => FALSE,
            "status_delete" => TRUE,
            "visto" => FALSE,
          )
        );

      if ($insertAlertaProyecto) {
        $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.asunto,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                    emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                    JOIN sos_personas AS pers_people JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                    AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                    AND emisor.personal = pers_people.id", [$token_notificacion]);
        foreach ($alertaList as $valAlert) {
          $asunto = "SOS-México - Gestión de proyectos " . $valAlert->asunto;
          $mensaje = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " .
            $this->desencriptar($valAlert->nombre) . " " . $this->desencriptar($valAlert->titulo);
          $phone_numero = "+" . $liderToken[0]->pais_code . $this->desencriptar($liderToken[0]->phone);
          $token_emisor = $valAlert->pers_token;

          $this->notificacionPushDevices($liderToken[0]->usuario_token, $asunto, $mensaje);

          if ($liderToken[0]->habilitado == TRUE) {
            $this->enviaSMS($phone_numero, $mensaje);
          }
        }
      }
    }
  }

  public function insertNotifEqAll($asunt, $token_proyecto, $titulo_alerta, $tarea, $informe, $empresa, $emisor, $control){
    $eqList = DB::select(
      "SELECT proy.id AS proyecto,proy.proyecto_name,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,
            tel_pers_resp.habilitado,users.usuario_token
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy
            JOIN teci_usuarios_catalogo AS users
            WHERE pers.id = tel_pers_resp.personal AND tel_pers_resp.principal = TRUE AND pers.id = users.empleado AND pers.id = resp.personal AND resp.tipo_pp = 'eq'
            AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
      [$token_proyecto]
    );
    if (count($eqList) != 0) {
      foreach ($eqList as $eqval) {
        $token_notificacion = $this->encriptarToken($token_proyecto, $titulo_alerta, $empresa, $emisor, $eqval->id);
        $insertAlertaProyecto = DB::table('teci_notificaciones')
          ->insert(
            array(
              "token_notificacion" => $token_notificacion,
              "fecha_notificacion" => time(),
              "type" => "gespr",
              "asunto" => $asunt,
              "titulo" => $this->encriptar($titulo_alerta),
              "proyecto" => $eqval->proyecto,
              "tarea" => $tarea,
              "informe" => $informe,
              "control" => $control,
              "empresa" => $empresa,
              "emisor" => $emisor,
              "receptor" => $eqval->id,
              "status_recibe" => FALSE,
              "status_delete" => TRUE,
              "visto" => FALSE,
            )
          );

        if ($insertAlertaProyecto) {
          $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.asunto,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                        emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                        JOIN sos_personas AS pers_people JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                        AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                        AND emisor.personal = pers_people.id", [$token_notificacion]);
          foreach ($alertaList as $valAlert) {
            $asunto = "SOS-México - Gestión de proyectos " . $valAlert->asunto;
            $mensaje = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " .
              $this->desencriptar($valAlert->nombre) . " " . $this->desencriptar($valAlert->titulo);
            $phone_numero = "+" . $eqval->pais_code . $this->desencriptar($eqval->phone);
            $token_emisor = $valAlert->pers_token;

            $this->notificacionPushDevices($eqval->usuario_token, $asunto, $mensaje);

            if ($eqval->habilitado == TRUE) {
              $this->enviaSMS($phone_numero, $mensaje);
            }
          }
        }
      }
    }
  }

  public function insertNotifEqPersonal($asunt, $token_personal, $token_proyecto, $titulo_alerta, $tarea, $informe, $empresa, $emisor, $control){
    $eqList = DB::select(
      "SELECT proy.id AS proyecto,proy.proyecto_name,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,
            tel_pers_resp.habilitado,users.token_dispositivo_movil,users.token_dispositivo_web
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy
            JOIN teci_usuarios_catalogo AS users
            WHERE pers.pers_token = ? AND pers.id = tel_pers_resp.personal AND tel_pers_resp.principal = TRUE AND pers.id = users.empleado AND pers.id = resp.personal 
            AND resp.tipo_pp = 'eq' AND resp.proyecto = proy.id AND proy.token_proyecto = ?",
      [$token_personal, $token_proyecto]
    );
    if (count($eqList) != 0) {
      foreach ($eqList as $eqval) {
        $token_notificacion = $this->encriptarToken($token_proyecto, $titulo_alerta, $empresa, $emisor, $eqval->id);
        $insertAlertaProyecto = DB::table('teci_notificaciones')
          ->insert(
            array(
              "token_notificacion" => $token_notificacion,
              "fecha_notificacion" => time(),
              "type" => "gespr",
              "asunto" => $asunt,
              "titulo" => $this->encriptar($titulo_alerta),
              "proyecto" => $eqval->proyecto,
              "tarea" => $tarea,
              "informe" => $informe,
              "control" => $control,
              "empresa" => $empresa,
              "emisor" => $emisor,
              "receptor" => $eqval->id,
              "status_recibe" => FALSE,
              "status_delete" => TRUE,
              "visto" => FALSE,
            )
          );

        if ($insertAlertaProyecto) {
          $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.asunto,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                        emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                        JOIN sos_personas AS pers_people JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                        AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                        AND emisor.personal = pers_people.id", [$token_notificacion]);
          foreach ($alertaList as $valAlert) {
            $asunto = "SOS-México - Gestión de proyectos " . $valAlert->asunto;
            $mensaje = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " .
              $this->desencriptar($valAlert->nombre) . " " . $this->desencriptar($valAlert->titulo);
            $phone_numero = "+" . $eqval->pais_code . $this->desencriptar($eqval->phone);
            $token_emisor = $valAlert->pers_token;

            if ($eqval->token_dispositivo_movil != null && $eqval->token_dispositivo_movil != "") {
              $this->notificacionPushDevices($eqval->token_dispositivo_movil, $asunto, $mensaje);
            }

            if ($eqval->token_dispositivo_web != null && $eqval->token_dispositivo_web != "") {
              $this->notificacionPushDevices($eqval->token_dispositivo_web, $asunto, $mensaje);
            }

            if ($eqval->habilitado == TRUE) {
              $this->enviaSMS($phone_numero, $mensaje);
            }
          }
        }
      }
    }
  }

  public function insertNotifEqTar($asunt, $token_proyecto, $token_tarea, $titulo_alerta, $tarea, $informe, $empresa, $emisor, $control){
    $eqList = DB::select(
      "SELECT proy.id AS proyecto,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado,users.token_dispositivo_movil,users.token_dispositivo_web
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_tarea_responsable AS resp JOIN module_proyectos AS proy
            JOIN module_proyectos_tareas AS subtar JOIN teci_usuarios_catalogo AS users WHERE pers.id = tel_pers_resp.personal  AND tel_pers_resp.principal = TRUE AND pers.id = users.empleado 
            AND pers.id = resp.personal
            AND resp.proyecto = proy.id AND proy.token_proyecto = ? AND resp.tarea = subtar.id AND subtar.token_tarea = ?",
      [$token_proyecto, $token_tarea]
    );
    if (count($eqList) != 0) {
      foreach ($eqList as $eqval) {
        $token_notificacion = $this->encriptarToken($token_proyecto, $titulo_alerta, $empresa, $emisor, $eqval->id);
        $insertAlertaProyecto = DB::table('teci_notificaciones')
          ->insert(
            array(
              "token_notificacion" => $token_notificacion,
              "fecha_notificacion" => time(),
              "type" => "gespr",
              "asunto" => $asunt,
              "titulo" => $this->encriptar($titulo_alerta),
              "proyecto" => $eqval->proyecto,
              "tarea" => $tarea,
              "informe" => $informe,
              "control" => $control,
              "empresa" => $empresa,
              "emisor" => $emisor,
              "receptor" => $eqval->id,
              "status_recibe" => FALSE,
              "status_delete" => TRUE,
              "visto" => FALSE,
            )
          );
        if ($insertAlertaProyecto) {
          $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.asunto,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                        emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                        JOIN sos_personas AS pers_people JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                        AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                        AND emisor.personal = pers_people.id", [$token_notificacion]);
          foreach ($alertaList as $valAlert) {
            $asunto = "SOS-México - Gestión de proyectos " . $valAlert->asunto;
            $mensaje = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " .
              $this->desencriptar($valAlert->nombre) . " " . $this->desencriptar($valAlert->titulo);
            $phone_numero = "+" . $eqval->pais_code . $this->desencriptar($eqval->phone);
            $token_emisor = $valAlert->pers_token;

            if ($eqval->token_dispositivo_movil != null && $eqval->token_dispositivo_movil != "") {
              $this->notificacionPushDevices($eqval->token_dispositivo_movil, $asunto, $mensaje);
            }

            if ($eqval->token_dispositivo_web != null && $eqval->token_dispositivo_web != "") {
              $this->notificacionPushDevices($eqval->token_dispositivo_web, $asunto, $mensaje);
            }

            if ($eqval->habilitado == TRUE) {
              $this->enviaSMS($phone_numero, $mensaje);
            }
          }
        }
      }
    }
  }

  public function insertNotifEq($token_proyecto, $token_tarea, $titulo_alerta, $tarea, $informe, $empresa, $emisor, $control){
    if ($token_tarea != NULL) {
      $eqList = DB::select(
        "SELECT proy.id AS proyecto,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_tarea_responsable AS resp JOIN module_proyectos AS proy
            JOIN module_proyectos_tareas AS subtar WHERE pers.id = tel_pers_resp.personal AND pers.id = resp.personal
            AND resp.proyecto = proy.id AND proy.token_proyecto = ? AND resp.tarea = subtar.id AND subtar.token_tarea = ?",
        [$token_proyecto, $token_tarea]
      );
    } else {
      $eqList = DB::select(
        "SELECT proy.id AS proyecto,pers.id,tel_pers_resp.cod_pais AS pais_code,tel_pers_resp.telefono AS phone,tel_pers_resp.habilitado
            FROM vhum_empleados_catalogo AS pers JOIN sos_personas_telefonos AS tel_pers_resp JOIN module_proyectos_responsable AS resp JOIN module_proyectos AS proy
            JOIN module_proyectos_tareas AS subtar WHERE pers.id = tel_pers_resp.personal AND pers.id = resp.personal
            AND resp.tipo_pp = 'eq' AND resp.proyecto = proy.id AND proy.token_proyecto = ? AND resp.tarea = subtar.id",
        [$token_proyecto]
      );
    }
    if (count($eqList) != 0) {
      foreach ($eqList as $eqval) {
        $token_notificacion = $this->encriptarToken($token_proyecto, $titulo_alerta, $empresa, $emisor, $eqval->id);
        $insertAlertaProyecto = DB::table('teci_notificaciones')
          ->insert(
            array(
              "token_notificacion" => $token_notificacion,
              "fecha_notificacion" => time(),
              "type" => "gespr",
              "titulo" => $this->encriptar($titulo_alerta),
              "proyecto" => $eqval->proyecto,
              "tarea" => $tarea,
              "informe" => $informe,
              "control" => $control,
              "empresa" => $empresa,
              "emisor" => $emisor,
              "receptor" => $eqval->id,
              "status_recibe" => FALSE,
              "status_delete" => TRUE,
              "visto" => FALSE,
            )
          );
        if ($eqval->habilitado == TRUE) {
          if ($titulo_alerta == "Ha registrado un nuevo proyecto") {
            $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                            emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                            JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                            AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                            AND emisor.personal = pers_people.id", [$token_notificacion]);
            foreach ($alertaList as $valAlert) {
              $mensaje = "Nuevo proyecto asignado: " . $this->desencriptar($valAlert->proyecto_name);
              $token_emisor = $valAlert->pers_token;
              $emisor = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " . $this->desencriptar($valAlert->nombre);
              $phone_numero = "+" . $liderToken[0]->pais_code . $this->desencriptar($liderToken[0]->phone);
              $this->enviaSMS($phone_numero, $emisor . ': ' . $mensaje);
            }
          } else if ($titulo_alerta == "Ha registrado un nuevo proyecto") {
            $alertaList = DB::select("SELECT alert.id,alert.token_notificacion,alert.titulo,alert.proyecto,alert.tarea,alert.informe,
                            emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre,proy.proyecto_name FROM teci_notificaciones AS alert
                            JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS emisor JOIN module_proyectos AS proy WHERE alert.token_notificacion = ?
                            AND alert.proyecto = proy.id AND alert.emisor = emisor.id
                            AND emisor.personal = pers_people.id", [$token_notificacion]);
            foreach ($alertaList as $valAlert) {
              $mensaje = "Nuevo proyecto asignado: " . $this->desencriptar($valAlert->proyecto_name);
              $token_emisor = $valAlert->pers_token;
              $emisor = $this->desencriptar($valAlert->paterno) . " " . $this->desencriptar($valAlert->materno) . " " . $this->desencriptar($valAlert->nombre);
              $phone_numero = "+" . $liderToken[0]->pais_code . $this->desencriptar($liderToken[0]->phone);
              $this->enviaSMS($phone_numero, $emisor . ': ' . $mensaje);
            }
          } else {
            $phone_numero = "+" . $liderToken[0]->pais_code . $this->desencriptar($liderToken[0]->phone);
            $this->enviaSMS($phone_numero, $titulo_alerta);
          }
        }
      }
    }
  }

  public function deleteNotifTar($token_tarea){
    $deleteNotif = DB::table("teci_notificaciones AS not")
      ->join("module_proyectos_tareas AS subtar", "not.tarea", "=", "subtar.id")
      ->where([
        'subtar.token_tarea' => $token_tarea,
      ])->limit(1)->delete();
  }

  public function deleteNotifInf($token_informe){
    $deleteNotif = DB::table("teci_notificaciones AS not")
      ->join("module_proyectos_informes AS inf", "not.informe", "=", "inf.id")
      ->where([
        'inf.token_informe' => $token_informe,
      ])->limit(1)->delete();
  }

  public function notificacionPushSSIC($dispositivo_token, $titulo, $notificacion){
    $data = [
      "to" => $dispositivo_token,
      "notification" => array(
        "title" => $titulo,
        "body" => $notificacion,
      )
    ];

    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Authorization: key=AAAAoAD51KQ:APA91bGPRt0yEfqzZBrXhxfshisnbbUU5zqDf8EXLnsQCnDRlPotdCPJXrmBjeaMMN7HusyCEPJWlJN6har1TJgueUc52Tcu2OU5eeVv0HIRqEx04Yz7pIJgwwbiLHAmU5F6z27ATtv1"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    //if ($err) {
    //    echo "cURL Error #:" . $err;
    //} else {
    //    print_r(json_decode($response));
    //}
    return $response;
  }

  public function notificacionPushTercAsociados($dispositivo_token, $titulo, $notificacion){
    $data = [
      "to" => $dispositivo_token,
      "notification" => array(
        "title" => $titulo,
        "body" => $notificacion,
      )
    ];

    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Authorization: key=AAAAoAD51KQ:APA91bGPRt0yEfqzZBrXhxfshisnbbUU5zqDf8EXLnsQCnDRlPotdCPJXrmBjeaMMN7HusyCEPJWlJN6har1TJgueUc52Tcu2OU5eeVv0HIRqEx04Yz7pIJgwwbiLHAmU5F6z27ATtv1"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);
    return $response;
  }

  public function notificacionPushTercClientes($dispositivo_token, $titulo, $notificacion){
    $data = [
      "to" => $dispositivo_token,
      "notification" => array(
        "title" => $titulo,
        "body" => $notificacion,
      )
    ];

    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Authorization: key=AAAAoAD51KQ:APA91bGPRt0yEfqzZBrXhxfshisnbbUU5zqDf8EXLnsQCnDRlPotdCPJXrmBjeaMMN7HusyCEPJWlJN6har1TJgueUc52Tcu2OU5eeVv0HIRqEx04Yz7pIJgwwbiLHAmU5F6z27ATtv1"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);
    return $response;
  }

  public function notificacionPushTercProveedores($dispositivo_token, $titulo, $notificacion){
    $data = [
      "to" => $dispositivo_token,
      "notification" => array(
        "title" => $titulo,
        "body" => $notificacion,
      )
    ];

    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Authorization: key=AAAAoAD51KQ:APA91bGPRt0yEfqzZBrXhxfshisnbbUU5zqDf8EXLnsQCnDRlPotdCPJXrmBjeaMMN7HusyCEPJWlJN6har1TJgueUc52Tcu2OU5eeVv0HIRqEx04Yz7pIJgwwbiLHAmU5F6z27ATtv1"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);
    return $response;
  }

  public function notificacionPushDevices($usuario_token, $titulo, $notificacion){
    $userDevices = DB::table("teci_usuarios_dispositivos AS device")
      ->join("teci_usuarios_catalogo AS users", "device.usuario", "users.id")
      ->where("users.usuario_token", $usuario_token)->get();

    if (count($userDevices) > 0) {
      foreach ($userDevices as $dev) {
        $deviceToken = $dev->dispositivo_token; // el que obtienes en Angular
        //$deviceToken = "fJHfbzPf5MfLFrNkRge3up:APA91bEyjEm63J7tmjX-gWyGsABq2WKzWn3XOUSGDRvqsSAZMHhF3dod1wfaZXc9_aC3Om2-klcToCYHEkMue42q4C3yWJOzYEDse7amwwT_qB5mzpyG5no";
        //$firebase = new FirebaseService();
        //$response = $firebase->sendNotification($deviceToken, "Hola!", "Esto es una notificación desde Laravel 🚀");
        //return response()->json($response);
        $creds = json_decode(file_get_contents(storage_path('app/firebase/sosmexico-b2eb5-68d0c7d82768.json')), true);
        $jwt = \App\Services\FirebaseJWT::createJwt($creds['client_email'], $creds['private_key']);
        // Obtener access_token
        $ch = curl_init("https://oauth2.googleapis.com/token");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
          "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
          "assertion" => $jwt,
        ]));
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        $accessToken = $data['access_token'];

        $payload = [
          "message" => [
            "token" => $deviceToken,
            "notification" => [
              //"title" => $titulo,
              "title" => "SOS-México informa: ",
              "body"  => $notificacion,
            ],
          ],
        ];

        $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$creds['project_id']}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          "Authorization: Bearer $accessToken",
          "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $result = curl_exec($ch);
        curl_close($ch);

        return $result; // devuelve la respuesta de FCM
      }
    }
  }

  public function notificacionPushDevices_old($dispositivo_token, $titulo, $notificacion){
    $data = [
      "to" => $dispositivo_token,
      "notification" => array(
        "title" => $titulo,
        "body" => $notificacion,
      )
    ];

    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Authorization: key=AAAAoAD51KQ:APA91bGPRt0yEfqzZBrXhxfshisnbbUU5zqDf8EXLnsQCnDRlPotdCPJXrmBjeaMMN7HusyCEPJWlJN6har1TJgueUc52Tcu2OU5eeVv0HIRqEx04Yz7pIJgwwbiLHAmU5F6z27ATtv1"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    return $response;
  }

  public function notificacionPushDevicesLink($dispositivo_token, $titulo, $link, $notificacion){
    $data = [
      "to" => $dispositivo_token,
      "notification" => array(
        "title" => $titulo,
        "body" => $notificacion,
        "click_action" => $link,
      )
    ];

    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Authorization: key=AAAAoAD51KQ:APA91bGPRt0yEfqzZBrXhxfshisnbbUU5zqDf8EXLnsQCnDRlPotdCPJXrmBjeaMMN7HusyCEPJWlJN6har1TJgueUc52Tcu2OU5eeVv0HIRqEx04Yz7pIJgwwbiLHAmU5F6z27ATtv1"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);
    return $response;
  }

  public function registra_permisos_nueva_empresa($empresa, $usuario){
    $insert_configuracion_systema_cont = DB::table("configuracion_systema_cont")
      ->insert(
        array(
          "catalogos" => TRUE,
          "cat_cuentas" => TRUE,
          "estados_fin" => TRUE,
          "reportes" => TRUE,
          "jerarquia" => "P",
          "privilegio_crear" => TRUE,
          "privilegio_editar" => TRUE,
          "privilegio_consulta" => TRUE,
          "privilegio_elimina" => TRUE,
          "privilegio_ver_docs" => TRUE,
          "empresa" => $empresa,
          "usuario" => $usuario
        )
      );

    $insert_configuracion_systema_eegr = DB::table("configuracion_systema_eegr")
      ->insert(
        array(
          "catalogos" => TRUE,
          "cat_prod" => TRUE,
          "cat_serv" => TRUE,
          "cat_actf" => TRUE,
          "cat_acti" => TRUE,
          "cat_prov" => TRUE,
          "cat_esta" => TRUE,
          "compras" => TRUE,
          "comp_req" => TRUE,
          "comp_cot" => TRUE,
          "comp_dir" => TRUE,
          "comp_seg" => TRUE,
          "reembolsos" => TRUE,
          "justificaciones" => TRUE,
          "reportes" => TRUE,
          "jerarquia" => "P",
          "privilegio_crear" => TRUE,
          "privilegio_editar" => TRUE,
          "privilegio_consulta" => TRUE,
          "privilegio_elimina" => TRUE,
          "privilegio_ver_docs" => TRUE,
          "empresa" => $empresa,
          "usuario" => $usuario
        )
      );

    $insert_configuracion_systema_fnzs = DB::table("configuracion_systema_fnzs")
      ->insert(
        array(
          "catalogos" => TRUE,
          "cat_cban" => TRUE,
          "cat_caja" => TRUE,
          "cat_moel" => TRUE,
          "cat_disp" => TRUE,
          "cmov_ban" => TRUE,
          "cmov_efe" => TRUE,
          "paym_ord" => TRUE,
          "cuen_aju" => TRUE,
          "info_ban" => TRUE,
          "jerarquia" => "P",
          "privilegio_crear" => TRUE,
          "privilegio_editar" => TRUE,
          "privilegio_consulta" => TRUE,
          "privilegio_elimina" => TRUE,
          "privilegio_ver_docs" => TRUE,
          "empresa" => $empresa,
          "usuario" => $usuario
        )
      );

    $insert_configuracion_systema_ingr = DB::table("configuracion_systema_ingr")
      ->insert(
        array(
          "catalogos" => TRUE,
          "cat_merc" => TRUE,
          "cat_serv" => TRUE,
          "cat_prec" => TRUE,
          "cat_desc" => TRUE,
          "cat_prom" => TRUE,
          "cat_impu" => TRUE,
          "cat_clie" => TRUE,
          "ventas" => TRUE,
          "vent_ped" => TRUE,
          "vent_dir" => TRUE,
          "vent_seg" => TRUE,
          "vent_dev" => TRUE,
          "vent_fac" => TRUE,
          "jerarquia" => "P",
          "privilegio_crear" => TRUE,
          "privilegio_editar" => TRUE,
          "privilegio_consulta" => TRUE,
          "privilegio_elimina" => TRUE,
          "privilegio_ver_docs" => TRUE,
          "empresa" => $empresa,
          "usuario" => $usuario
        )
      );

    $insert_configuracion_systema_teci = DB::table("configuracion_systema_teci")
      ->insert(
        array(
          "apps_complement" => TRUE,
          "soporte" => TRUE,
          "comunicacion" => TRUE,
          "publicaciones" => TRUE,
          "jerarquia" => "P",
          "privilegio_crear" => TRUE,
          "privilegio_editar" => TRUE,
          "privilegio_consulta" => TRUE,
          "privilegio_elimina" => TRUE,
          "privilegio_ver_docs" => TRUE,
          "empresa" => $empresa,
          "usuario" => $usuario
        )
      );

    $insert_configuracion_systema_vhum = DB::table("configuracion_systema_vhum")
      ->insert(
        array(
          "catalogos" => TRUE,
          "reembolsos" => TRUE,
          "justificaciones" => TRUE,
          "reportes" => TRUE,
          "jerarquia" => "P",
          "privilegio_crear" => TRUE,
          "privilegio_editar" => TRUE,
          "privilegio_consulta" => TRUE,
          "privilegio_elimina" => TRUE,
          "privilegio_ver_docs" => TRUE,
          "empresa" => $empresa,
          "usuario" => $usuario
        )
      );
  }

  public function registra_permisos_usuario_old($empresa){
    $permisos_company_uno = DB::select("SELECT ingr_cpc,eegr_cpp,fnzs,vhum,cont,teci,juri,usuario FROM teci_permisos_usuario_old WHERE empresa = 1");
    foreach ($permisos_company_uno as $vUno) {
      $insert_teci_permisos_usuario_old = DB::table("teci_permisos_usuario_old")
        ->insert(
          array(
            "ingr_cpc" => $vUno->ingr_cpc,
            "eegr_cpp" => $vUno->eegr_cpp,
            "fnzs" => $vUno->fnzs,
            "vhum" => $vUno->vhum,
            "cont" => $vUno->cont,
            "teci" => $vUno->teci,
            "juri" => $vUno->juri,
            "empresa" => $empresa,
            "usuario" => $vUno->usuario
          )
        );
    }
  }

  public function bool_rfc($rfc_text){
    if ($rfc_text == "" || $rfc_text == "xaxx010101000" || $rfc_text == "xax010101000" || $rfc_text == "xexx010101000") {
      return false;
    } else {
      return true;
    }
  }

  public function filtroAlfaNumerico(){
    return '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
  }

  public function filtroAlfabetico(){
    return '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
  }

  public function filtroRfc(){
    return '/[aA0-zZ9]/';
  }

  public function filtroMail(){
    return '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
  }

  public function filtroFecha(){
    return '/^[0-9-]*$/';
  }

  public function filtroCostoPrecio(){
    return '/^[0-9$,.-]*$/';
  }

  public function filtroPorcentaje(){
    return '/^[%0-9]*$/';
  }

  public function filtroNumericoSimple(){
    return '/^[0-9.]*$/';
  }

  public function filtroNumerico(){
    return '/^[1-9][0-9]*$/';
  }

  public function filtroTelefonico(){
    return '/[0-9+]/';
  }

  public function filtroCpostal(){
    return '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
  }

  public function filtroLote(){
    return '/[aA0-zZ9-]/';
  }

  public function getMetodoPago($metodo_pago){
    $idMetodoPago = DB::select("SELECT id FROM teci_metodo_pago WHERE token_metodoPago = ?", [$metodo_pago]);
    return $idMetodoPago[0]->id;
  }

  public function getFormaPago($forma_pago){
    $idFormaPago = DB::select("SELECT id FROM teci_forma_pago WHERE token_formapago = ?", [$forma_pago]);
    return $idFormaPago[0]->id;
  }

  public function getMoneda($moneda_token){
    $idMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$moneda_token]);
    return $idMoneda[0]->id;
  }

  public function getFormasPagoAPI($clave){
    $fp_forma = "";
    $fp_query = ApiFormasPagoModelo::all();
    if ($fp_query->isNotEmpty()) {
      $fp_encontrada = $fp_query->firstWhere('clave', $clave);
      if ($fp_encontrada) {
        $fp_forma = $fp_encontrada->descripcion ?? '';
      }
    }
    return $fp_forma;
  }

  public function getFormasPagoAPIByDescripcion($descripcion){
    $fp_forma = "";
    $fp_query = ApiFormasPagoModelo::all();
    if ($fp_query->isNotEmpty()) {
      $fp_encontrada = $fp_query->firstWhere('descripcion', $descripcion);
      if ($fp_encontrada) {
        $fp_forma = $fp_encontrada->clave ?? '';
      }
    }
    return $fp_forma;
  }

  public function getMonedaAPI($moneda){
    $moneda_decimales = 0;
    $monedas = ApiMonedasModelo::all();
    if ($monedas->isNotEmpty()) {
      $moneda_encontrada = $monedas->firstWhere('code', $moneda);
      if ($moneda_encontrada) {
        $moneda_decimales = $moneda_encontrada->decimales ?? 0;
      }
    }
    return $moneda_decimales;
  }

  public function getUnidadesDeMedidaSATApi($sat_clave){
    $unidad_medida = "";
    $u_medida = ApiUnidadesDeMedidaSATModelo::all();
    if ($u_medida->isNotEmpty()) {
      $u_medida_found = $u_medida->firstWhere('sat_clave', $sat_clave);
      if ($u_medida_found) {
        $unidad_medida = $u_medida_found->unidad_medida ?? "";
      }
    }
    return $unidad_medida;
  }

  public function userAdminMain(){
    $user_admin = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY";
    return $user_admin;
  }

  public function usersAdmins($user_token){
    $user_admin = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY";
    $user_jc = "WXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjYTlNMEtyUW01ZkQ2ckEyWWJTaThxNTZYQkFVTGJVakFVWDFPdVk9OjoxMjM0NTY3ODEyMzQ1Njc4";
    $resultado = $user_token == $user_admin || $user_token == $user_jc ? true : false;
    return $resultado;
  }

  public function getExtensionDoc($tipo_documento){
    if ($tipo_documento == "application/pdf") {
      $type_result = "pdf";
    } else if ($tipo_documento == "text/xml") {
      $type_result = "xml";
    } else if ($tipo_documento == "image/jpeg") {
      $type_result = "jpg";
    } else if ($tipo_documento == "image/jpg") {
      $type_result = "jpg";
    } else if ($tipo_documento == "image/png") {
      $type_result = "png";
    }
    return $type_result;
  }

  public function registraDocsCliente($token_cliente, $documento_tkn, $tipo_documento, $documento_nombre, $documento_tipo){
    $selectClientCat = DB::select("SELECT id FROM ingr_catalogo_clientes WHERE token_cat_clientes = ?", [$token_cliente]);
    foreach ($selectClientCat as $dClient) {
      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%CLIENT-EVID%'");

      $insertEvidenceInf = DB::table('sos_documentos')->insert(
        array(
          "token_documento" => $documento_tkn,
          "fecha_carga" => time(),
          "modulo" => "clientes",
          "folio_modulo" => "CLIENT-EVID" . $select_folio_doc[0]->folio,
          "tipo_documento" => $tipo_documento,
          "nombre_documento" => $this->encriptar($documento_nombre),
          "extension_documento" => $documento_tipo,
          "cliente" => $dClient->id,
          "status_documento" => TRUE,
        )
      );
      return "response_ec" . $dClient->id;
    }
  }

  public function registraDocsProveedor($token_proveedor, $documento_tkn, $tipo_documento, $documento_nombre, $documento_tipo){
    $selectProvCat = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$token_proveedor]);
    foreach ($selectProvCat as $dProv) {
      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PROV-EVID%'");

      $insertEvidenceInf = DB::table('sos_documentos')->insert(
        array(
          "token_documento" => $documento_tkn,
          "fecha_carga" => time(),
          "modulo" => "proveedores",
          "folio_modulo" => "PROV-EVID" . $select_folio_doc[0]->folio,
          "tipo_documento" => $tipo_documento,
          "nombre_documento" => $this->encriptar($documento_nombre),
          "extension_documento" => $documento_tipo,
          "proveedor" => $dProv->id,
          "status_documento" => TRUE,
        )
      );
      return "response_ec" . $dProv->id;
    }
  }

  public function loginEmpresaSeleccionada($empresa, $user_token){
    $queryEmpresa = DB::select("SELECT emp.empresa_token,emp.root_tkn,emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais,people.materno,
            people.abrev_nombre,people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico,people.rfc,people.tax_id,people.img_perfil,ar.areaemp,
            car.cargo,money.token_monedas,money.codigo,money.moneda,money.decimales FROM main_empresas AS emp JOIN teci_catalogo_monedas AS money 
            JOIN sos_personas AS people JOIN teci_pais AS ispa JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN vhum_empleados_catalogo_area AS ar JOIN vhum_empleados_catalogo_cargo AS car JOIN teci_user_settings AS conf JOIN teci_usuarios_catalogo AS users 
            WHERE emp.status_empresa = TRUE AND emp.empresa_token = ? AND emp.e_moneda = money.id AND people.nacionalidad = ispa.id AND people.id = emp.persona 
            AND emp.id = empuser.empresa AND empuser.vinculacion_estado = TRUE AND empuser.usuario = users.id AND users.empleado = pers.id AND pers.area = ar.id AND pers.cargo = car.id 
            AND users.usuario_token = ? AND users.id = conf.usuario", [$empresa, $user_token]);

    if (count($queryEmpresa) > 0) {
      foreach ($queryEmpresa as $dEmp) {
        $nombreEmpresa = $dEmp->denominacion_rs == '' ? $this->desencriptarNombres($dEmp->paterno, $dEmp->materno, $dEmp->nombre) : $this->desencriptar($dEmp->denominacion_rs);
        $rfc_generico = $dEmp->rfc_generico;
        $rfc_emp = $dEmp->rfc != NULL ? $this->desencriptar($dEmp->rfc) : "---";
        $tax_id_emp = $dEmp->tax_id != NULL ? $this->desencriptar($dEmp->tax_id) : "---";
        $logo_path = 'public/root/' . $dEmp->root_tkn . '/0007-core/';
        $logo_perfil_decrypt = $this->desencriptar($dEmp->img_perfil);
        $logoTipo = $this->encriptaBase64(Storage::path($logo_perfil_decrypt != "empresa_desconocida.png" ? $logo_path . $logo_perfil_decrypt : 'public/settings/empresa_desconocida.png'));

        $areadb = $this->desencriptar($dEmp->areaemp);
        if ($dEmp->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
          $areasettings = 'airneg';
        } else if ($dEmp->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
          $areasettings = 'aerger';
        } else if ($dEmp->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
          $areasettings = 'atseer';
        } else if ($dEmp->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
          $areasettings = 'avsleh';
        } else if ($dEmp->areaemp == 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
          $areasettings = 'acsleo';
        } else if ($dEmp->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
          if ($dEmp->empresa_token == 'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
            $areasettings = 'aprtsieif';
          } else {
            $areasettings = 'asctsieif';
          }
        } else if ($dEmp->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
          $areasettings = 'aasdemg';
        }

        $permisos_ingresos = array();
        $queryConfigIngr = DB::table("configuracion_systema_ingr AS conf_ingr")
          ->join("main_empresas AS emp", "conf_ingr.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "=", "users.id")
          ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
        foreach ($queryConfigIngr as $cINGR) {
          $ingr_jerarquia = $cINGR->jerarquia;
          $ingr_privilegio_crear = $cINGR->privilegio_crear == TRUE ? true : false;
          $ingr_privilegio_editar = $cINGR->privilegio_editar == TRUE ? true : false;
          $ingr_privilegio_consulta = $cINGR->privilegio_consulta == TRUE ? true : false;
          $ingr_privilegio_elimina = $cINGR->privilegio_elimina == TRUE ? true : false;
          $ingr_privilegio_ver_docs = $cINGR->privilegio_ver_docs == TRUE ? true : false;

          $row_in_conf = array(
            "jerarquia" => $ingr_jerarquia,
            "bool_ingr_perm_crear" => $ingr_privilegio_crear,
            "bool_ingr_perm_editar" => $ingr_privilegio_editar,
            "bool_ingr_perm_consulta" => $ingr_privilegio_consulta,
            "bool_ingr_perm_elimina" => $ingr_privilegio_elimina,
            "bool_ingr_perm_ver_docs" => $ingr_privilegio_ver_docs,
          );
          $permisos_ingresos[] = $row_in_conf;
        }

        $permisos_egresos = array();
        $queryConfigEegr = DB::table("configuracion_systema_eegr AS eegr_conf")
          ->join("main_empresas AS emp", "eegr_conf.empresa", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "eegr_conf.usuario", "users.id")
          ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
        foreach ($queryConfigEegr as $vCegr) {
          $bool_eegr_catalogos = $vCegr->catalogos == TRUE ? true : false;
          $bool_eegr_cat_prod = $vCegr->cat_prod == TRUE ? true : false;
          $bool_eegr_cat_serv = $vCegr->cat_serv == TRUE ? true : false;
          $bool_eegr_cat_actf = $vCegr->cat_actf == TRUE ? true : false;
          $bool_eegr_cat_acti = $vCegr->cat_acti == TRUE ? true : false;
          $bool_eegr_cat_prov = $vCegr->cat_prov == TRUE ? true : false;
          $bool_eegr_cat_esta = $vCegr->cat_esta == TRUE ? true : false;
          $bool_eegr_compras = $vCegr->compras == TRUE ? true : false;
          $bool_eegr_comp_req = $vCegr->comp_req == TRUE ? true : false;
          $bool_eegr_comp_cot = $vCegr->comp_cot == TRUE ? true : false;
          $bool_eegr_comp_dir = $vCegr->comp_dir == TRUE ? true : false;
          $bool_eegr_comp_seg = $vCegr->comp_seg == TRUE ? true : false;
          $bool_eegr_perm_crear = $vCegr->privilegio_crear == TRUE ? true : false;
          $bool_eegr_perm_editar = $vCegr->privilegio_editar == TRUE ? true : false;
          $bool_eegr_perm_consulta = $vCegr->privilegio_consulta == TRUE ? true : false;
          $bool_eegr_perm_elimina = $vCegr->privilegio_elimina == TRUE ? true : false;
          $bool_eegr_perm_ver_docs = $vCegr->privilegio_ver_docs == TRUE ? true : false;

          $row_ee_conf = array(
            "jerarquia" => $vCegr->jerarquia,
            "bool_eegr_catalogos" => $bool_eegr_catalogos,
            "bool_eegr_cat_prod" => $bool_eegr_cat_prod,
            "bool_eegr_cat_serv" => $bool_eegr_cat_serv,
            "bool_eegr_cat_actf" => $bool_eegr_cat_actf,
            "bool_eegr_cat_acti" => $bool_eegr_cat_acti,
            "bool_eegr_cat_prov" => $bool_eegr_cat_prov,
            "bool_eegr_cat_esta" => $bool_eegr_cat_esta,
            "bool_eegr_compras" => $bool_eegr_compras,
            "bool_eegr_comp_req" => $bool_eegr_comp_req,
            "bool_eegr_comp_cot" => $bool_eegr_comp_cot,
            "bool_eegr_comp_dir" => $bool_eegr_comp_dir,
            "bool_eegr_comp_seg" => $bool_eegr_comp_seg,
            "bool_eegr_perm_crear" => $bool_eegr_perm_crear,
            "bool_eegr_perm_editar" => $bool_eegr_perm_editar,
            "bool_eegr_perm_consulta" => $bool_eegr_perm_consulta,
            "bool_eegr_perm_elimina" => $bool_eegr_perm_elimina,
            "bool_eegr_perm_ver_docs" => $bool_eegr_perm_ver_docs,
          );
          $permisos_egresos[] = $row_ee_conf;
        }

        $permisos_finanzas = array();
        $queryConfigFnzs = DB::table("configuracion_systema_fnzs AS conf_fnzs")
          ->join("main_empresas AS emp", "conf_fnzs.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "=", "users.id")
          ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
        foreach ($queryConfigFnzs as $cFNZS) {
          $fnzs_jerarquia = $cFNZS->jerarquia;
          $fnzs_privilegio_crear = $cFNZS->privilegio_crear == TRUE ? true : false;
          $fnzs_privilegio_editar = $cFNZS->privilegio_editar == TRUE ? true : false;
          $fnzs_privilegio_consulta = $cFNZS->privilegio_consulta == TRUE ? true : false;
          $fnzs_privilegio_elimina = $cFNZS->privilegio_elimina == TRUE ? true : false;
          $fnzs_privilegio_ver_docs = $cFNZS->privilegio_ver_docs == TRUE ? true : false;

          $row_fnzs_conf = array(
            "jerarquia" => $fnzs_jerarquia,
            "bool_fnzs_perm_crear" => $fnzs_privilegio_crear,
            "bool_fnzs_perm_editar" => $fnzs_privilegio_editar,
            "bool_fnzs_perm_consulta" => $fnzs_privilegio_consulta,
            "bool_fnzs_perm_elimina" => $fnzs_privilegio_elimina,
            "bool_fnzs_perm_ver_docs" => $fnzs_privilegio_ver_docs,
          );
          $permisos_finanzas[] = $row_fnzs_conf;
        }

        $permisos_vhum = array();
        $queryConfigVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
          ->join("main_empresas AS emp", "conf_vhum.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "=", "users.id")
          ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
        foreach ($queryConfigVhum as $cVHUM) {
          $vhum_jerarquia = $cVHUM->jerarquia;
          $vhum_privilegio_crear = $cVHUM->privilegio_crear == TRUE ? true : false;
          $vhum_privilegio_editar = $cVHUM->privilegio_editar == TRUE ? true : false;
          $vhum_privilegio_consulta = $cVHUM->privilegio_consulta == TRUE ? true : false;
          $vhum_privilegio_elimina = $cVHUM->privilegio_elimina == TRUE ? true : false;
          $vhum_privilegio_ver_docs = $cVHUM->privilegio_ver_docs == TRUE ? true : false;

          $row_vhum_conf = array(
            "jerarquia" => $vhum_jerarquia,
            "bool_vhum_perm_crear" => $vhum_privilegio_crear,
            "bool_vhum_perm_editar" => $vhum_privilegio_editar,
            "bool_vhum_perm_consulta" => $vhum_privilegio_consulta,
            "bool_vhum_perm_elimina" => $vhum_privilegio_elimina,
            "bool_vhum_perm_ver_docs" => $vhum_privilegio_ver_docs,
          );
          $permisos_vhum[] = $row_vhum_conf;
        }

        $permisos_contabilidad = array();
        $queryConfigCont = DB::table("configuracion_systema_cont AS conf_cont")
          ->join("main_empresas AS emp", "conf_cont.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "=", "users.id")
          ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
        foreach ($queryConfigCont as $cCONT) {
          $cont_jerarquia = $cCONT->jerarquia;
          $cont_privilegio_crear = $cCONT->privilegio_crear == TRUE ? true : false;
          $cont_privilegio_editar = $cCONT->privilegio_editar == TRUE ? true : false;
          $cont_privilegio_consulta = $cCONT->privilegio_consulta == TRUE ? true : false;
          $cont_privilegio_elimina = $cCONT->privilegio_elimina == TRUE ? true : false;
          $cont_privilegio_ver_docs = $cCONT->privilegio_ver_docs == TRUE ? true : false;

          $row_cont_conf = array(
            "jerarquia" => $cont_jerarquia,
            "bool_cont_perm_crear" => $cont_privilegio_crear,
            "bool_cont_perm_editar" => $cont_privilegio_editar,
            "bool_cont_perm_consulta" => $cont_privilegio_consulta,
            "bool_cont_perm_elimina" => $cont_privilegio_elimina,
            "bool_cont_perm_ver_docs" => $cont_privilegio_ver_docs,
          );
          $permisos_contabilidad[] = $row_cont_conf;
        }

        $permisos_teci = array();
        $queryConfigTeci = DB::table("configuracion_systema_teci AS conf_teci")
          ->join("main_empresas AS emp", "conf_teci.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "=", "users.id")
          ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
        foreach ($queryConfigTeci as $cTECI) {
          $teci_jerarquia = $cTECI->jerarquia;
          $teci_privilegio_crear = $cTECI->privilegio_crear == TRUE ? true : false;
          $teci_privilegio_editar = $cTECI->privilegio_editar == TRUE ? true : false;
          $teci_privilegio_consulta = $cTECI->privilegio_consulta == TRUE ? true : false;
          $teci_privilegio_elimina = $cTECI->privilegio_elimina == TRUE ? true : false;
          $teci_privilegio_ver_docs = $cTECI->privilegio_ver_docs == TRUE ? true : false;

          $row_teci_conf = array(
            "jerarquia" => $teci_jerarquia,
            "bool_teci_perm_crear" => $teci_privilegio_crear,
            "bool_teci_perm_editar" => $teci_privilegio_editar,
            "bool_teci_perm_consulta" => $teci_privilegio_consulta,
            "bool_teci_perm_elimina" => $teci_privilegio_elimina,
            "bool_teci_perm_ver_docs" => $teci_privilegio_ver_docs,
          );
          $permisos_teci[] = $row_teci_conf;
        }

        $selectCompanies = DB::select("SELECT emp.id AS workingCompanies FROM main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                    WHERE emp.status_emp = TRUE AND emp.empresa_token != ? AND emp.id = empuser.empresa AND empuser.empleado_name = pers.id 
                    AND pers.id = users.empleado AND users.usuario_token = ?", [$dEmp->empresa_token, $user_token]);

        $alertaList = DB::select(
          "SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp 
                    ON alert.empresa = emp.id INNER JOIN vhum_empleados_catalogo AS receptor ON alert.receptor = receptor.id 
                    INNER JOIN teci_usuarios_catalogo AS users ON receptor.usuario = users.id 
                    WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.empresa_token = ? 
                    AND users.usuario_token = ? AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                        AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                        AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                        AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                        OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                        AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                        AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                        AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
          [$dEmp->empresa_token, $user_token]
        );

        $rowEmpSelected = array(
          "empresa_token" => $dEmp->empresa_token,
          "company_name" => $dEmp->abrev_nombre . " - " . $nombreEmpresa,
          "zona_horaria" => $dEmp->zona_horaria,
          "zona_horaria_utc" => $dEmp->zona_horaria_utc,
          "codigo_pais" => $dEmp->codigo_pais,
          "rfc_generico" => $rfc_generico,
          "rfc_emp" => $rfc_emp,
          "tax_id_emp" => $tax_id_emp,
          "area" => ucfirst(strtolower($areadb)),
          "areasettings" => $areasettings,
          "cargo" => ucfirst(strtolower($this->desencriptar($dEmp->cargo))),
          "logotypo" => $logoTipo,
          "companies_vinc" => count($selectCompanies),
          "conf_ingresos" => $permisos_ingresos,
          "conf_egresos" => $permisos_egresos,
          "conf_finanzas" => $permisos_finanzas,
          "conf_vhumano" => $permisos_vhum,
          "conf_contabilidad" => $permisos_contabilidad,
          "conf_teci" => $permisos_teci,
          "moneda_ktn" => $dEmp->token_monedas,
          "moneda_code" => $dEmp->codigo,
          "moneda_name" => $dEmp->moneda,
          "moneda_decimales" => $dEmp->decimales,
          "total_notificaciones" => count($alertaList),
        );
      }
      return $rowEmpSelected;
    } else {
      return [];
    }
  }

  public function primeraEmpresaVinc($user_token){
    $queryEmpresa = DB::select("SELECT emp.empresa_token,emp.root_tkn,emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais,people.materno,
            people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico,people.rfc,people.tax_id,people.img_perfil,ar.areaemp,
            car.cargo,money.token_monedas,money.codigo,money.moneda,money.decimales FROM main_empresas AS emp JOIN teci_catalogo_monedas AS money 
            JOIN sos_personas AS people JOIN teci_pais AS ispa JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN vhum_empleados_catalogo_area AS ar JOIN vhum_empleados_catalogo_cargo AS car JOIN teci_user_settings AS conf JOIN teci_usuarios_catalogo AS users 
            WHERE emp.status_emp = TRUE AND emp.e_moneda = money.id AND people.nacionalidad = ispa.id AND people.id = emp.persona 
            AND emp.id = empuser.empresa AND empuser.empleado_name = pers.id AND pers.area = ar.id AND pers.cargo = car.id AND pers.id = users.empleado 
            AND users.usuario_token = ? AND users.id = conf.usuario order by empuser.id ASC limit 1", [$user_token]);

    foreach ($queryEmpresa as $dEmp) {
      $nombreEmpresa = $dEmp->denominacion_rs == '' ? $this->desencriptarNombres($dEmp->paterno, $dEmp->materno, $dEmp->nombre) : $this->desencriptar($dEmp->denominacion_rs);
      $rfc_generico = $dEmp->rfc_generico;
      $rfc_emp = $dEmp->rfc != NULL ? $this->desencriptar($dEmp->rfc) : "---";
      $tax_id_emp = $dEmp->tax_id != NULL ? $this->desencriptar($dEmp->tax_id) : "---";
      $logoTipo = $this->encriptaBase64(Storage::path('public/root/' . $dEmp->root_tkn . '/0007-core/' . $this->desencriptar($dEmp->img_perfil)));
      $areadb = $this->desencriptar($dEmp->areaemp);
      if ($dEmp->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
        $areasettings = 'airneg';
      } else if ($dEmp->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
        $areasettings = 'aerger';
      } else if ($dEmp->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
        $areasettings = 'atseer';
      } else if ($dEmp->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
        $areasettings = 'avsleh';
      } else if ($dEmp->areaemp == 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
        $areasettings = 'acsleo';
      } else if ($dEmp->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
        if ($dEmp->empresa_token == 'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
          $areasettings = 'aprtsieif';
        } else {
          $areasettings = 'asctsieif';
        }
      } else if ($dEmp->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
        $areasettings = 'aasdemg';
      }

      $permisos_ingresos = array();
      $queryConfigIngr = DB::table("configuracion_systema_ingr AS conf_ingr")
        ->join("main_empresas AS emp", "conf_ingr.empresa", "=", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "conf_ingr.usuario", "=", "users.id")
        ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
      foreach ($queryConfigIngr as $cINGR) {
        $ingr_jerarquia = $cINGR->jerarquia;
        $ingr_privilegio_crear = $cINGR->privilegio_crear == TRUE ? true : false;
        $ingr_privilegio_editar = $cINGR->privilegio_editar == TRUE ? true : false;
        $ingr_privilegio_consulta = $cINGR->privilegio_consulta == TRUE ? true : false;
        $ingr_privilegio_elimina = $cINGR->privilegio_elimina == TRUE ? true : false;
        $ingr_privilegio_ver_docs = $cINGR->privilegio_ver_docs == TRUE ? true : false;

        $row_in_conf = array(
          "jerarquia" => $ingr_jerarquia,
          "bool_ingr_perm_crear" => $ingr_privilegio_crear,
          "bool_ingr_perm_editar" => $ingr_privilegio_editar,
          "bool_ingr_perm_consulta" => $ingr_privilegio_consulta,
          "bool_ingr_perm_elimina" => $ingr_privilegio_elimina,
          "bool_ingr_perm_ver_docs" => $ingr_privilegio_ver_docs,
        );
        $permisos_ingresos[] = $row_in_conf;
      }

      $permisos_egresos = array();
      $queryConfigEegr = DB::table("configuracion_systema_eegr AS eegr_conf")
        ->join("main_empresas AS emp", "eegr_conf.empresa", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "eegr_conf.usuario", "users.id")
        ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
      foreach ($queryConfigEegr as $vCegr) {
        $bool_eegr_catalogos = $vCegr->catalogos == TRUE ? true : false;
        $bool_eegr_cat_prod = $vCegr->cat_prod == TRUE ? true : false;
        $bool_eegr_cat_serv = $vCegr->cat_serv == TRUE ? true : false;
        $bool_eegr_cat_actf = $vCegr->cat_actf == TRUE ? true : false;
        $bool_eegr_cat_acti = $vCegr->cat_acti == TRUE ? true : false;
        $bool_eegr_cat_prov = $vCegr->cat_prov == TRUE ? true : false;
        $bool_eegr_cat_esta = $vCegr->cat_esta == TRUE ? true : false;
        $bool_eegr_compras = $vCegr->compras == TRUE ? true : false;
        $bool_eegr_comp_req = $vCegr->comp_req == TRUE ? true : false;
        $bool_eegr_comp_cot = $vCegr->comp_cot == TRUE ? true : false;
        $bool_eegr_comp_dir = $vCegr->comp_dir == TRUE ? true : false;
        $bool_eegr_comp_seg = $vCegr->comp_seg == TRUE ? true : false;
        $bool_eegr_perm_crear = $vCegr->privilegio_crear == TRUE ? true : false;
        $bool_eegr_perm_editar = $vCegr->privilegio_editar == TRUE ? true : false;
        $bool_eegr_perm_consulta = $vCegr->privilegio_consulta == TRUE ? true : false;
        $bool_eegr_perm_elimina = $vCegr->privilegio_elimina == TRUE ? true : false;
        $bool_eegr_perm_ver_docs = $vCegr->privilegio_ver_docs == TRUE ? true : false;

        $row_ee_conf = array(
          "jerarquia" => $vCegr->jerarquia,
          "bool_eegr_catalogos" => $bool_eegr_catalogos,
          "bool_eegr_cat_prod" => $bool_eegr_cat_prod,
          "bool_eegr_cat_serv" => $bool_eegr_cat_serv,
          "bool_eegr_cat_actf" => $bool_eegr_cat_actf,
          "bool_eegr_cat_acti" => $bool_eegr_cat_acti,
          "bool_eegr_cat_prov" => $bool_eegr_cat_prov,
          "bool_eegr_cat_esta" => $bool_eegr_cat_esta,
          "bool_eegr_compras" => $bool_eegr_compras,
          "bool_eegr_comp_req" => $bool_eegr_comp_req,
          "bool_eegr_comp_cot" => $bool_eegr_comp_cot,
          "bool_eegr_comp_dir" => $bool_eegr_comp_dir,
          "bool_eegr_comp_seg" => $bool_eegr_comp_seg,
          "bool_eegr_perm_crear" => $bool_eegr_perm_crear,
          "bool_eegr_perm_editar" => $bool_eegr_perm_editar,
          "bool_eegr_perm_consulta" => $bool_eegr_perm_consulta,
          "bool_eegr_perm_elimina" => $bool_eegr_perm_elimina,
          "bool_eegr_perm_ver_docs" => $bool_eegr_perm_ver_docs,
        );
        $permisos_egresos[] = $row_ee_conf;
      }

      $permisos_finanzas = array();
      $queryConfigFnzs = DB::table("configuracion_systema_fnzs AS conf_fnzs")
        ->join("main_empresas AS emp", "conf_fnzs.empresa", "=", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "conf_fnzs.usuario", "=", "users.id")
        ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
      foreach ($queryConfigFnzs as $cFNZS) {
        $fnzs_jerarquia = $cFNZS->jerarquia;
        $fnzs_privilegio_crear = $cFNZS->privilegio_crear == TRUE ? true : false;
        $fnzs_privilegio_editar = $cFNZS->privilegio_editar == TRUE ? true : false;
        $fnzs_privilegio_consulta = $cFNZS->privilegio_consulta == TRUE ? true : false;
        $fnzs_privilegio_elimina = $cFNZS->privilegio_elimina == TRUE ? true : false;
        $fnzs_privilegio_ver_docs = $cFNZS->privilegio_ver_docs == TRUE ? true : false;

        $row_fnzs_conf = array(
          "jerarquia" => $fnzs_jerarquia,
          "bool_fnzs_perm_crear" => $fnzs_privilegio_crear,
          "bool_fnzs_perm_editar" => $fnzs_privilegio_editar,
          "bool_fnzs_perm_consulta" => $fnzs_privilegio_consulta,
          "bool_fnzs_perm_elimina" => $fnzs_privilegio_elimina,
          "bool_fnzs_perm_ver_docs" => $fnzs_privilegio_ver_docs,
        );
        $permisos_finanzas[] = $row_fnzs_conf;
      }

      $permisos_vhum = array();
      $queryConfigVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
        ->join("main_empresas AS emp", "conf_vhum.empresa", "=", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "conf_vhum.usuario", "=", "users.id")
        ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
      foreach ($queryConfigVhum as $cVHUM) {
        $vhum_jerarquia = $cVHUM->jerarquia;
        $vhum_privilegio_crear = $cVHUM->privilegio_crear == TRUE ? true : false;
        $vhum_privilegio_editar = $cVHUM->privilegio_editar == TRUE ? true : false;
        $vhum_privilegio_consulta = $cVHUM->privilegio_consulta == TRUE ? true : false;
        $vhum_privilegio_elimina = $cVHUM->privilegio_elimina == TRUE ? true : false;
        $vhum_privilegio_ver_docs = $cVHUM->privilegio_ver_docs == TRUE ? true : false;

        $row_vhum_conf = array(
          "jerarquia" => $vhum_jerarquia,
          "bool_vhum_perm_crear" => $vhum_privilegio_crear,
          "bool_vhum_perm_editar" => $vhum_privilegio_editar,
          "bool_vhum_perm_consulta" => $vhum_privilegio_consulta,
          "bool_vhum_perm_elimina" => $vhum_privilegio_elimina,
          "bool_vhum_perm_ver_docs" => $vhum_privilegio_ver_docs,
        );
        $permisos_vhum[] = $row_vhum_conf;
      }

      $permisos_contabilidad = array();
      $queryConfigCont = DB::table("configuracion_systema_cont AS conf_cont")
        ->join("main_empresas AS emp", "conf_cont.empresa", "=", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "conf_cont.usuario", "=", "users.id")
        ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
      foreach ($queryConfigCont as $cCONT) {
        $cont_jerarquia = $cCONT->jerarquia;
        $cont_privilegio_crear = $cCONT->privilegio_crear == TRUE ? true : false;
        $cont_privilegio_editar = $cCONT->privilegio_editar == TRUE ? true : false;
        $cont_privilegio_consulta = $cCONT->privilegio_consulta == TRUE ? true : false;
        $cont_privilegio_elimina = $cCONT->privilegio_elimina == TRUE ? true : false;
        $cont_privilegio_ver_docs = $cCONT->privilegio_ver_docs == TRUE ? true : false;

        $row_cont_conf = array(
          "jerarquia" => $cont_jerarquia,
          "bool_cont_perm_crear" => $cont_privilegio_crear,
          "bool_cont_perm_editar" => $cont_privilegio_editar,
          "bool_cont_perm_consulta" => $cont_privilegio_consulta,
          "bool_cont_perm_elimina" => $cont_privilegio_elimina,
          "bool_cont_perm_ver_docs" => $cont_privilegio_ver_docs,
        );
        $permisos_contabilidad[] = $row_cont_conf;
      }

      $permisos_teci = array();
      $queryConfigTeci = DB::table("configuracion_systema_teci AS conf_teci")
        ->join("main_empresas AS emp", "conf_teci.empresa", "=", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "conf_teci.usuario", "=", "users.id")
        ->where(["emp.empresa_token" => $dEmp->empresa_token, "users.usuario_token" => $user_token])->get();
      foreach ($queryConfigTeci as $cTECI) {
        $teci_jerarquia = $cTECI->jerarquia;
        $teci_privilegio_crear = $cTECI->privilegio_crear == TRUE ? true : false;
        $teci_privilegio_editar = $cTECI->privilegio_editar == TRUE ? true : false;
        $teci_privilegio_consulta = $cTECI->privilegio_consulta == TRUE ? true : false;
        $teci_privilegio_elimina = $cTECI->privilegio_elimina == TRUE ? true : false;
        $teci_privilegio_ver_docs = $cTECI->privilegio_ver_docs == TRUE ? true : false;

        $row_teci_conf = array(
          "jerarquia" => $teci_jerarquia,
          "bool_teci_perm_crear" => $teci_privilegio_crear,
          "bool_teci_perm_editar" => $teci_privilegio_editar,
          "bool_teci_perm_consulta" => $teci_privilegio_consulta,
          "bool_teci_perm_elimina" => $teci_privilegio_elimina,
          "bool_teci_perm_ver_docs" => $teci_privilegio_ver_docs,
        );
        $permisos_teci[] = $row_teci_conf;
      }

      $selectCompanies = DB::select("SELECT emp.id AS workingCompanies FROM main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                WHERE emp.status_emp = TRUE AND emp.empresa_token != ? AND emp.id = empuser.empresa AND empuser.empleado_name = pers.id 
                AND pers.id = users.empleado AND users.usuario_token = ?", [$dEmp->empresa_token, $user_token]);

      $alertaList = DB::select(
        "SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp 
                ON alert.empresa = emp.id INNER JOIN vhum_empleados_catalogo AS receptor ON alert.receptor = receptor.id 
                INNER JOIN teci_usuarios_catalogo AS users ON receptor.usuario = users.id 
                WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.empresa_token = ? 
                AND users.usuario_token = ? AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                    AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                    AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                    AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                    OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                    AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                    AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                    AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
        [$dEmp->empresa_token, $user_token]
      );

      $row = array(
        "empresa_token" => $dEmp->empresa_token,
        "company_name" => $nombreEmpresa,
        "zona_horaria" => $dEmp->zona_horaria,
        "zona_horaria_utc" => $dEmp->zona_horaria_utc,
        "codigo_pais" => $dEmp->codigo_pais,
        "rfc_generico" => $rfc_generico,
        "rfc_emp" => $rfc_emp,
        "tax_id_emp" => $tax_id_emp,
        "area" => ucfirst(strtolower($areadb)),
        "areasettings" => $areasettings,
        "cargo" => ucfirst(strtolower($this->desencriptar($dEmp->cargo))),
        "logotypo" => $logoTipo,
        "companies_vinc" => count($selectCompanies),
        "conf_ingresos" => $permisos_ingresos,
        "conf_egresos" => $permisos_egresos,
        "conf_finanzas" => $permisos_finanzas,
        "conf_vhumano" => $permisos_vhum,
        "conf_contabilidad" => $permisos_contabilidad,
        "conf_teci" => $permisos_teci,
        "moneda_ktn" => $dEmp->token_monedas,
        "moneda_code" => $dEmp->codigo,
        "moneda_name" => $dEmp->moneda,
        "moneda_decimales" => $dEmp->decimales,
        "total_notificaciones" => count($alertaList),
      );
      return $row;
    }
  }

  public function muestraCantidadesConMoneda($orden_importe, $orden_moneda_code, $orden_moneda_decimales){
    return $orden_importe > 0 && $orden_moneda_code != '---' ? "$" . number_format($orden_importe, $orden_moneda_decimales, '.', ',') . " $orden_moneda_code" : '$0.00 MXN';
  }

  public function pagosDoneBYOrden($orden_de_pago){
    $lista_pagos_realizados = array();
    $queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
      ->where([
        "vinc.vinculo_cancelado" => FALSE,
        "order.token_ordenPago" => $orden_de_pago
      ])->get();

    foreach ($queryPagosDone as $vPayDone) {
      $payment_observaciones = !is_null($vPayDone->observacionesPago) ? $this->desencriptar($vPayDone->observacionesPago) : '';

      if (!is_null($vPayDone->vinc_proveedor)) {
        $destino = "proveedor";
      } elseif (!is_null($vPayDone->vinc_cliente)) {
        $destino = "cliente";
      } elseif (!is_null($vPayDone->vinc_empleado)) {
        $destino = "empleado";
      } elseif (!is_null($vPayDone->vinc_acreedor)) {
        $destino = "acreedor";
      } elseif (!is_null($vPayDone->vinc_deudor)) {
        $destino = "deudor";
      } elseif (!is_null($vPayDone->vinc_nomina)) {
        $destino = "nómina en efectivo";
      } elseif (!is_null($vPayDone->vinc_nomina_especie)) {
        $destino = "nómina en especie";
      } elseif (!is_null($vPayDone->impuesto_sobre_nomina)) {
        $destino = "impuestos sobre nómina";
      } elseif (!is_null($vPayDone->aportacion_seguridad_social)) {
        $destino = "aportaciones de seguridad social";
      } elseif (!is_null($vPayDone->declaracion_imp_federales)) {
        $destino = "declaraciones de impuestos federales";
      }

      $forma_pago_registrada = $vPayDone->forma_pago_pago;

      $queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
        ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
        ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
        ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
        ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->get();
      //echo count($queryOrvVincReembolsosPago);
      if (count($queryOrvVincReembolsosPago) > 0) {
        $forma_pago_registrada = $vPayDone->forma_pago_pago;
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
          ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $this->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $this->desencriptar($queryProveedor->nombre_extendido) : "";
      } else {
        $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
          ->join("sos_personas AS people", "catprov.proveedor", "people.id")
          ->where(["payment.token_pagos" => $vPayDone->token_pagos])
          ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido')
          ->first();
        $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
        $proveedor_folio = $queryProveedor ? ('PRV-' . $this->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
        $proveedor_name = $queryProveedor ? $this->desencriptar($queryProveedor->nombre_extendido) : "";
      }

      //cliente
      $queryCliente = DB::table("fnzs_pagos_pago AS payment")
        ->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
        ->join("sos_personas AS people", "catclient.cliente", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('catclient.token_cat_clientes', 'catclient.folio', 'catclient.post_folio', 'people.nombre_extendido')
        ->first();
      $cliente_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
      $cliente_folio = $queryCliente ? ('CLI-' . $this->generarFolio($queryCliente->folio) . (!is_null($queryCliente->post_folio) ? '-' . $queryCliente->post_folio : '')) : "";
      $cliente_name = $queryCliente ? $this->desencriptar($queryCliente->nombre_extendido) : "";
      //empleado
      $queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $empleado_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
      $empleado_folio = $queryEmpleado ? "TRB-" . $this->generarFolio($queryEmpleado->folio_pers) : "";
      $empleado_name = $queryEmpleado ? $this->desencriptarNombres($queryEmpleado->paterno, $queryEmpleado->materno, $queryEmpleado->nombre) : "";
      //acreedor
      $queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
        //->join("sos_personas AS people", "acr.acreedor", "people.id")
        ->where(["payment.token_pagos" => $vPayDone->token_pagos])
        ->select('acr.token_cat_acreedores', 'acr.acr_folio', 'acr.acr_post_folio', 'acr.acr_titular')
        ->first();
      $acreedor_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
      $acreedor_folio = $queryAcreedor ? ('ACREE-' . $this->generarFolio($queryAcreedor->acr_folio) . (!is_null($queryAcreedor->acr_post_folio) ? '-' . $queryAcreedor->acr_post_folio : '')) : "";
      $acreedor_name = $queryAcreedor ? $this->desencriptar($queryAcreedor->acr_titular) : "";

      //personal_pago
      $queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
      $p_paga_folio = $queryPersPaga ? "TRB-" . $this->generarFolio($queryPersPaga->folio_pers) : "";
      $p_paga_paterno = $queryPersPaga ? ucwords($this->desencriptar($queryPersPaga->paterno)) : "";
      $p_paga_materno = $queryPersPaga ? ucwords($this->desencriptar($queryPersPaga->materno)) : "";
      $p_paga_nombre = $queryPersPaga ? ucwords($this->desencriptar($queryPersPaga->nombre)) : "";
      $p_paga_name = $queryPersPaga ? "$p_paga_paterno $p_paga_materno $p_paga_nombre" : "";

      $queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $vPayDone->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
      $p_autoriza_folio = $queryPersAuth ? "TRB-" . $this->generarFolio($queryPersAuth->folio_pers) : "";
      $p_autoriza_paterno = $queryPersAuth ? ucwords($this->desencriptar($queryPersAuth->paterno)) : "";
      $p_autoriza_materno = $queryPersAuth ? ucwords($this->desencriptar($queryPersAuth->materno)) : "";
      $p_autoriza_nombre = $queryPersAuth ? ucwords($this->desencriptar($queryPersAuth->nombre)) : "";
      $p_autoriza_name = $queryPersAuth ? "$p_autoriza_paterno $p_autoriza_materno $p_autoriza_nombre" : "";

      $forma_pago_vinculada = "";
      $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("r_caj.*", "r_cuent.*", "r_moned.*")->get();
      //->select("r_caj.token_caja","r_cuent.token_cuenta","r_moned.token_cuentamonedero")->get();

      foreach ($queryFormasDePago as $vFPagoVinc) {
        if ($vFPagoVinc->token_caja !== null) {
          $forma_pago_vinculada = "Caja CAJ-" . $this->generarFolio($vFPagoVinc->no_caja);
        } elseif ($vFPagoVinc->token_cuenta !== null) {
          $forma_pago_vinculada = "Banco CUENT-" . $this->generarFolio($vFPagoVinc->folio_cuenta);
        } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
          $forma_pago_vinculada = "Monedero CUENTM-" . $this->generarFolio($vFPagoVinc->folio_cuentmon);
        }
      }

      $cfdi_comprobante_metodo_de_pago = "";
      $queryMetodoPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
        ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
        ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->where("payment.token_pagos", $vPayDone->token_pagos)
        ->select("cfdi.cfdi_comprobante_metodo_de_pago")->first();

      $cfdi_comprobante_metodo_de_pago = $queryMetodoPago ? $queryMetodoPago->cfdi_comprobante_metodo_de_pago : "";

      $row_pagos_realizados = array(
        "token_pagos" => $vPayDone->token_pagos,
        "folio_pagos" => "PAGO-" . $this->generarFolio($vPayDone->folio_pagos),
        "status_pago" => $vPayDone->status_pagos ? true : false,
        "folio_operacion" => $vPayDone->folio_operacion,
        "fecha_pago" => $this->mostrarUnixAFechaMexico($vPayDone->fecha_pago),
        "fecha_contabilizacion" => !empty($vPayDone->fecha_contabilizacion) ? $this->mostrarUnixAFechaMexico($vPayDone->fecha_contabilizacion) : "",
        "monto_pago" => "$" . number_format($vPayDone->monto_pago, $this->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "observacionesPago" => $payment_observaciones,
        "tipo_cambio" => "$" . number_format($vPayDone->tipo_cambio, $this->getMonedaAPI($vPayDone->p_moneda), '.', ',') . " $vPayDone->p_moneda",
        "p_moneda" => $vPayDone->p_moneda,
        "destino" => $destino,
        "concepto" => !empty($vPayDone->concepto) ? $this->desencriptar($vPayDone->concepto) : '',
        //forma_pago
        "forma_pago_vinculada" => $forma_pago_vinculada,
        "forma_pago_cfdi" => !is_null($forma_pago_registrada) && $forma_pago_registrada != '' ? $forma_pago_registrada . " - " . $this->getFormasPagoAPI($forma_pago_registrada) : '',
        "metodo_pago_cfdi" => $cfdi_comprobante_metodo_de_pago,
        //proveedor
        "proveedor_token" => $proveedor_token != '' ? $proveedor_token : '',
        "proveedor_name" => $proveedor_folio != '' && $proveedor_name ? "$proveedor_folio - $proveedor_name" : '',
        //cliente
        "cliente_token" => $cliente_token != '' ? $cliente_token : '',
        "cliente_name" => $cliente_folio != '' && $cliente_folio != '' ? "$cliente_folio - $cliente_name" : '',
        //empleado
        "empleado_token" => $empleado_token != '' ? $empleado_token : '',
        "empleado_name" => $empleado_folio != '' && $acreedor_name != '' ? "$empleado_folio - $empleado_name" : '',
        //acreedor
        "acreedor_token" => $acreedor_token != '' ? $acreedor_token : '',
        "acreedor_name" => $acreedor_folio != '' && $acreedor_name != '' ? "PXT $acreedor_folio - $acreedor_name" : '',
        //personal_pago
        "personal_pago_token" => $p_paga_token,
        "personal_pago_folio" => $p_paga_folio,
        "personal_pago_name" => $p_paga_name,
        "pago_autorizado" => $vPayDone->pago_autorizado ? true : false,
        "fecha_pago_auth" => $this->mostrarUnixAFechaMexico($vPayDone->fecha_pago_auth),
        //personal_autoriza
        "personal_autoriza_token" => $p_autoriza_token,
        "personal_autoriza_folio" => $p_autoriza_folio,
        "personal_autoriza_name" => $p_autoriza_name,
      );
      $lista_pagos_realizados[] = $row_pagos_realizados;
    }

    return $lista_pagos_realizados;
  }

  public function pagosDoneBYOrdenDesglose($orden_de_pago, $empresa_token, $usuario_token){
    $lista_pagos_realizados = array();
    $queryNominaPagos = DB::table("fnzs_pagos_pago AS payment")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "=", "vinc.pago_realizado")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
      ->join("main_empresas AS emp", "payment.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where("payment.pago_autorizado", TRUE)
      ->where("payment.status_pagos", TRUE)
      ->where("order.token_ordenPago", $orden_de_pago)
      ->where("emp.empresa_token", $empresa_token)
      ->where("users.usuario_token", $usuario_token)
      ->orderBy("payment.folio_pagos", "DESC")->get();

    foreach ($queryNominaPagos as $pay) {
      $queryDocAnterior = DB::table("fnzs_pagos_orden AS order")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
        ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
        ->where("payment.token_pagos", $pay->token_pagos)
        ->select("order.folio_ordenPago", "order.fecha_contabilizacion_ordenPago")
        ->first();
      $doc_anterior_folio = $queryDocAnterior ? "ORDP-" . $this->generarFolio($queryDocAnterior->folio_ordenPago) : '';
      $doc_anterior_fecha_contabilizacion = $queryDocAnterior ? $this->mostrarUnixAFechaMexico($queryDocAnterior->fecha_contabilizacion_ordenPago) : '';

      $destino = "nomina";

      $tercero_token = "";
      $tercero_folio = "";
      $tercero_name = "";
      $tercero_comercial_name = "";

      $prov_token = "";
      $prov_folio = "";
      $prov_name = "";
      $prov_comercial_name = "";

      $financeadoa_token = "";
      $financeadoa_folio = "";
      $financeadoa_name = "";
      $financeadoa_comercial_name = "";
      if (!is_null($pay->vinc_proveedor)) {
        //proveedor
        $queryOrvVincReembolsosPago = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
          ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
          ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
          ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
          ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->get();
        //echo count($queryOrvVincReembolsosPago);
        if (count($queryOrvVincReembolsosPago) > 0) {
          $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
            ->join("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
            ->join("terc_reembolso_main AS reem", "buy.reembolso_vinculado_main", "=", "reem.id")
            ->join("terc_reembolso_solicitud AS soli", "buy.reembolso_vinculado_soli", "=", "soli.id")
            ->join("eegr_catalogo_proveedores AS catprov", "soli.proveedor", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "people.id")
            ->where("payment.token_pagos", $pay->token_pagos)
            ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido', 'people.nombre_com')
            ->first();
          $tercero_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
          $tercero_folio = $queryProveedor ? ('PRV-' . $this->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
          $tercero_name = $queryProveedor ? $this->desencriptar($queryProveedor->nombre_extendido) : "";
          $tercero_comercial_name = !is_null($queryProveedor->nombre_com) ? $this->desencriptar($queryProveedor->nombre_com) : '';
        } else {
          $queryProveedor = DB::table("fnzs_pagos_pago AS payment")
            ->join("eegr_catalogo_proveedores AS catprov", "payment.vinc_proveedor", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "people.id")
            ->where("payment.token_pagos", $pay->token_pagos)
            ->select('catprov.token_cat_proveedores', 'catprov.folio', 'catprov.post_folio', 'people.nombre_extendido', 'people.nombre_com')
            ->first();
          $tercero_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
          $tercero_folio = $queryProveedor ? ('PRV-' . $this->generarFolio($queryProveedor->folio) . (!is_null($queryProveedor->post_folio) ? '-' . $queryProveedor->post_folio : '')) : "";
          $tercero_name = $queryProveedor ? $this->desencriptar($queryProveedor->nombre_extendido) : "";
          $tercero_comercial_name = !is_null($queryProveedor->nombre_com) ? $this->desencriptar($queryProveedor->nombre_com) : '';
        }
      } elseif (!is_null($pay->vinc_cliente)) {
        //cliente
        $queryCliente = DB::table("fnzs_pagos_pago AS payment")
          ->join("ingr_catalogo_clientes AS catclient", "payment.vinc_cliente", "catclient.id")
          ->join("sos_personas AS people", "catclient.cliente", "people.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select('catclient.token_cat_clientes', 'catclient.folio', 'catclient.post_folio', 'people.nombre_extendido', 'people.nombre_com')
          ->first();
        $tercero_token = $queryCliente ? $queryCliente->token_cat_clientes : "";
        $tercero_folio = $queryCliente ? ('CLI-' . $this->generarFolio($queryCliente->folio) . (!is_null($queryCliente->post_folio) ? '-' . $queryCliente->post_folio : '')) : "";
        $tercero_name = $queryCliente ? $this->desencriptar($queryCliente->nombre_extendido) : "";
        $tercero_comercial_name = !is_null($queryCliente->nombre_com) ? $this->desencriptar($queryCliente->nombre_com) : '';
      } elseif (!is_null($pay->vinc_empleado)) {
        //empleado
        $queryEmpleado = DB::table("fnzs_pagos_pago AS payment")
          ->join("vhum_empleados_catalogo AS pers", "payment.vinc_empleado", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "people.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
          ->first();
        $tercero_token = $queryEmpleado ? $queryEmpleado->empleado_token : "";
        $tercero_folio = $queryEmpleado ? "TRB-" . $this->generarFolio($queryEmpleado->folio_pers) : "";
        $tercero_name = $queryEmpleado ? $this->desencriptarNombres($queryEmpleado->paterno, $queryEmpleado->materno, $queryEmpleado->nombre) : "";
      } elseif (!is_null($pay->vinc_acreedor)) {
        //acreedor
        $queryAcreedor = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_catalogo_acreedores AS acr", "payment.vinc_acreedor", "acr.id")
          //->join("sos_personas AS people", "acr.acreedor", "people.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select('acr.token_cat_acreedores', 'acr.acr_folio', 'acr.acr_post_folio', 'acr.acr_titular')
          ->first();
        $tercero_token = $queryAcreedor ? $queryAcreedor->token_cat_acreedores : "";
        $tercero_folio = $queryAcreedor ? ('ACREE-' . $this->generarFolio($queryAcreedor->acr_folio) . (!is_null($queryAcreedor->acr_post_folio) ? '-' . $queryAcreedor->acr_post_folio : '')) : "";
        $tercero_name = $queryAcreedor ? $this->desencriptar($queryAcreedor->acr_titular) : "";
      } elseif (!is_null($pay->vinc_deudor)) {
        $queryDeudor = DB::table("fnzs_pagos_pago AS payment")
          ->join("fnzs_catalogo_deudores AS deu", "payment.vinc_deudor", "deu.id")
          ->where("payment.token_pagos", $pay->token_pagos)
          ->select('deu.token_cat_deudores', 'deu.deu_folio', 'deu.deu_post_folio', 'deu.deu_titular', 'deu.deu_nombre_comercial')
          ->get();
        foreach ($queryDeudor as $vDeuP) {
          $tercero_token = $vDeuP->token_cat_deudores;
          $tercero_folio = 'DEU-' . $this->generarFolio($vDeuP->deu_folio) . (!is_null($vDeuP->deu_post_folio) ? '-' . $vDeuP->deu_post_folio : '');
          $tercero_name = !is_null($vDeuP->deu_titular) && $vDeuP->deu_titular != '' ? $this->desencriptar($vDeuP->deu_titular) : 'N/A';
          $tercero_comercial_name = !is_null($vDeuP->deu_nombre_comercial) && $vDeuP->deu_nombre_comercial != '' ? $this->desencriptar($vDeuP->deu_nombre_comercial) : 'N/A';

          $financeadoa_token = $vDeuP->token_cat_deudores;
          $financeadoa_folio = 'DEU-' . $this->generarFolio($vDeuP->deu_folio) . (!is_null($vDeuP->deu_post_folio) ? '-' . $vDeuP->deu_post_folio : '');
          $financeadoa_name = !is_null($vDeuP->deu_titular) && $vDeuP->deu_titular != '' ? $this->desencriptar($vDeuP->deu_titular) : 'N/A';
          $financeadoa_comercial_name = !is_null($vDeuP->deu_nombre_comercial) && $vDeuP->deu_nombre_comercial != '' ? $this->desencriptar($vDeuP->deu_nombre_comercial) : 'N/A';
        }
      }

      //personal_pago
      $queryPersPaga = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_pago", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $pay->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_paga_token = $queryPersPaga ? $queryPersPaga->empleado_token : "";
      $p_paga_folio = $queryPersPaga ? "TRB-" . $this->generarFolio($queryPersPaga->folio_pers) : "";
      $p_paga_paterno = $queryPersPaga ? ucwords($this->desencriptar($queryPersPaga->paterno)) : "";
      $p_paga_materno = $queryPersPaga ? ucwords($this->desencriptar($queryPersPaga->materno)) : "";
      $p_paga_nombre = $queryPersPaga ? ucwords($this->desencriptar($queryPersPaga->nombre)) : "";
      $p_paga_name = $queryPersPaga ? "$p_paga_paterno $p_paga_materno $p_paga_nombre" : "";

      $queryPersAuth = DB::table("fnzs_pagos_pago AS payment")
        ->join("vhum_empleados_catalogo AS pers", "payment.personal_autoriza", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where('payment.token_pagos', $pay->token_pagos)
        ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
        ->first();
      $p_autoriza_token = $queryPersAuth ? $queryPersAuth->empleado_token : "";
      $p_autoriza_folio = $queryPersAuth ? "TRB-" . $this->generarFolio($queryPersAuth->folio_pers) : "";
      $p_autoriza_paterno = $queryPersAuth ? ucwords($this->desencriptar($queryPersAuth->paterno)) : "";
      $p_autoriza_materno = $queryPersAuth ? ucwords($this->desencriptar($queryPersAuth->materno)) : "";
      $p_autoriza_nombre = $queryPersAuth ? ucwords($this->desencriptar($queryPersAuth->nombre)) : "";
      $p_autoriza_name = $queryPersAuth ? "$p_autoriza_paterno $p_autoriza_materno $p_autoriza_nombre" : "";

      $ordenes_relacionadas_lista = array();
      $factura_relacionada_typo = "---";
      $factura_relacionada_token = "---";
      $factura_relacionada_string = "---";
      $pago_rr_forma_metodo_pago_cfdi = "";
      $queryOrdenesPago = DB::table("fnzs_pagos_pago AS payment")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "payment.id", "vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
        ->leftJoin("eegr_compras AS buy", "order.factura_compra", "=", "buy.id")
        ->leftJoin("ingr_ventas AS sell", "order.factura_venta", "=", "sell.id")
        ->leftJoin("terc_reembolso_main AS reem", "order.reembolso_main", "=", "reem.id")
        ->leftJoin("eegr_catalogo_proveedores_anticipo AS ant", "order.ord_anticipo", "=", "ant.uuid_anticipo")
        ->where("payment.token_pagos", $pay->token_pagos)
        ->select(
          "order.*",
          "vinc.*",
          "buy.token_compras",
          "buy.folio_compra",
          "sell.token_ventas",
          "sell.folio_venta",
          "reem.token_reem",
          "reem.folio_reem",
          "reem.post_folio_reem",
          "ant.uuid_anticipo",
          "ant.folio_anticipo"
        )->get();

      foreach ($queryOrdenesPago as $vOrdp) {
        $orden_pago_monto = $vOrdp->orden_pago_monto;

        if ($vOrdp->token_compras !== null) {
          $queryFormaPago = DB::table("cfdi_comprobantes_fiscales AS cfdi")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "cfdi.id", "vinc_buy.comprobante_fiscal")
            ->join("eegr_compras AS buy", "vinc_buy.compra_vinculada", "buy.id")
            ->join("fnzs_pagos_orden AS order", "buy.id", "order.factura_compra")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "order.id", "=", "vinc.orden_pago_vinculada")
            ->join("fnzs_pagos_pago AS payment", "vinc.pago_realizado", "=", "payment.id")
            ->where("payment.token_pagos", $pay->token_pagos)
            ->select("cfdi.cfdi_comprobante_forma_de_pago", "cfdi.cfdi_comprobante_metodo_de_pago")->first();
          $pago_rr_forma_metodo_pago_cfdi = $queryFormaPago ? $queryFormaPago->cfdi_comprobante_forma_de_pago . " - " . $this->getFormasPagoAPI($queryFormaPago->cfdi_comprobante_forma_de_pago) . " / " . $queryFormaPago->cfdi_comprobante_metodo_de_pago : '';

          $factura_relacionada_typo = "compras";
          $factura_relacionada_token = $vOrdp->token_compras;
          $factura_relacionada_string = "COMP-" . $this->generarFolio($vOrdp->folio_compra);
        } elseif ($vOrdp->token_ventas !== null) {
          $factura_relacionada_typo = "ventas";
          $factura_relacionada_token = $vOrdp->token_ventas;
          $factura_relacionada_string = "VENT-" . $this->generarFolio($vOrdp->numero_venta);
        } elseif ($vOrdp->token_reem !== null) {
          $factura_relacionada_typo = "reembolsos";
          $factura_relacionada_token = $vOrdp->token_reem;
          $factura_relacionada_string = 'REEM-' . $this->generarFolio($vOrdp->folio_reem) . ($vOrdp->post_folio_reem == NULL ? '-' . $vOrdp->post_folio_reem : '');
        } elseif ($vOrdp->ord_anticipo != NULL) {
          $factura_relacionada_typo = "anticipos";
          $factura_relacionada_token = $vOrdp->uuid_anticipo;
          $factura_relacionada_string = 'ANT-' . $this->generarFolio($vOrdp->folio_anticipo);

          $query_deu_anticipo = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
            ->join("eegr_catalogo_proveedores AS catprov", "ant.proveedor", "catprov.id")
            ->join("sos_personas AS people", "catprov.proveedor", "people.id")
            ->where("ant.uuid_anticipo", $vOrdp->ord_anticipo)->get();

          foreach ($query_deu_anticipo as $oDeu) {
            $prov_token = $oDeu->token_cat_proveedores;
            $prov_folio = 'PRV-' . $this->generarFolio($oDeu->folio) . (!is_null($oDeu->post_folio) ? '-' . $oDeu->post_folio : '');
            $prov_name = $this->desencriptar($oDeu->nombre_extendido);
            $prov_comercial_name = !is_null($oDeu->nombre_com) ? $this->desencriptar($oDeu->nombre_com) : '';
          }
        }

        $row_ord = array(
          "token_ordenPago" => $vOrdp->token_ordenPago,
          "orden_pago_monto" => "$" . number_format($orden_pago_monto * $pay->tipo_cambio, $this->getMonedaAPI($pay->p_moneda), '.', ','),
          "folio_ordenPago" => "ORDP-" . $this->generarFolio($vOrdp->folio_ordenPago),
          "fecha_contabilizacion_ordenPago" => $this->mostrarUnixAFechaMexico($vOrdp->fecha_contabilizacion_ordenPago),
          "fecha_registro" => $this->mostrarUnixAFechaMexico($vOrdp->fecha_sistema_ordenp),
          "autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,
          "pago_cancelado" => $pay->pago_cancelado ? true : false,
          //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_folio_cancelacion
          //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_fecha_cancelacion
          //"autorizacion_pay" => $vOrdp->autorizacion_pay ? true : false,pago_fecha_contabilizacion_cancelacion

          "fecha_autorizacion_pay" => $vOrdp->autorizacion_pay ? $this->mostrarUnixAFechaMexico($vOrdp->fecha_autorizacion_pay) : "---",
          "factura_relacionada_typo" => $factura_relacionada_typo,
          "factura_relacionada_token" => $factura_relacionada_token,
          "factura_relacionada_string" => $factura_relacionada_string,
        );
        $ordenes_relacionadas_lista[] = $row_ord;
      }

      $desglose_pagos_medio = array();
      $queryPagoMovimiento = DB::table("fnzs_actividad_movimientos AS movim")
        ->join("fnzs_pagos_pago AS payment", "movim.pago", "payment.id")
        ->where("payment.token_pagos", $pay->token_pagos)
        ->get();
      foreach ($queryPagoMovimiento as $vMov) {

        $queryPersResponsable = DB::table("fnzs_actividad_movimientos AS movim")
          ->join("vhum_empleados_catalogo AS pers", "movim.responsable", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "people.id")
          ->where('movim.token_movimiento', $vMov->token_movimiento)
          ->select('pers.empleado_token', 'pers.folio_pers', 'people.paterno', 'people.materno', 'people.nombre')
          ->first();
        //$pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
        //$pers_responsmov_folio = $queryPersResponsable ? "TRB-".$this->generarFolio($queryPersResponsable->folio_pers) : "";
        //$pers_responsmov_name = $queryPersResponsable ? $this->desencriptarNombres($queryPersResponsable->paterno,$queryPersResponsable->materno,$queryPersResponsable->nombre) : "";

        $pers_responsmov_token = $queryPersResponsable ? $queryPersResponsable->empleado_token : "";
        $pers_responsmov_folio = $queryPersResponsable ? "TRB-" . $this->generarFolio($queryPersResponsable->folio_pers) : "";
        $pers_responsmov_paterno = $queryPersResponsable ? ucwords($this->desencriptar($queryPersResponsable->paterno)) : "";
        $pers_responsmov_materno = $queryPersResponsable ? ucwords($this->desencriptar($queryPersResponsable->materno)) : "";
        $pers_responsmov_nombre = $queryPersResponsable ? ucwords($this->desencriptar($queryPersResponsable->nombre)) : "";
        $pers_responsmov_name = $queryPersResponsable ? "$pers_responsmov_paterno $pers_responsmov_materno $pers_responsmov_nombre" : "";

        $queryCaja = CajaModelo::join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_caja.id", "movim.caja")
          ->select('fnzs_catalogos_caja.token_caja', 'fnzs_catalogos_caja.no_caja', 'fnzs_catalogos_caja.alias_caja')
          ->where('movim.token_movimiento', $vMov->token_movimiento)
          ->first();

        $queryCuenta = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
          ->join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_cuentas.id", "movim.cuenta_bancaria")
          ->select('fnzs_catalogos_cuentas.token_cuenta', 'fnzs_catalogos_cuentas.folio_cuenta', 'fnzs_catalogos_cuentas.cuenta')
          ->where('movim.token_movimiento', $vMov->token_movimiento)
          ->first();

        $queryMonedero = CuentaMonederoModelo::join("fnzs_actividad_movimientos AS movim", "fnzs_catalogos_cuentas_monedero.id", "movim.cuenta_monedero")
          ->select('fnzs_catalogos_cuentas_monedero.token_cuentamonedero', 'fnzs_catalogos_cuentas_monedero.folio_cuentmon', 'fnzs_catalogos_cuentas_monedero.cuenta')
          ->where('movim.token_movimiento', $vMov->token_movimiento)
          ->first();

        if ($queryCaja) {
          $movimiento_tipo = "caja";
          $movimiento_token = $queryCaja->token_caja;
          $movimiento_folio = "CAJ-" . $this->generarFolio($queryCaja->no_caja);
          $movimiento_name = $this->desencriptar($queryCaja->alias_caja);
        } elseif ($queryCuenta) {
          $movimiento_tipo = "banco";
          $movimiento_token = $queryCuenta->token_cuenta;
          $movimiento_folio = 'CUENT-' . $this->generarFolio($queryCuenta->folio_cuenta);
          $cuenta_descifrada = $this->decryptBankAccount($queryCuenta->cuenta);
          $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
          $movimiento_name = "**** **** **** $cuenta_descifrada_substr";
        } elseif ($queryMonedero) {
          $movimiento_tipo = "monedero";
          $movimiento_token = $queryMonedero->token_cuentamonedero;
          $movimiento_folio = "CUENTM-" . $this->generarFolio($queryMonedero->folio_cuentmon);
          $movimiento_name = $queryMonedero->cuenta;
        } else {
          $movimiento_tipo = "N/A";
          $movimiento_token = "N/A";
          $movimiento_folio = "N/A";
          $movimiento_name = "N/A";
        }

        $row_mov = array(
          "token_movimiento" => $vMov->token_movimiento,
          "folio_movimiento" => $this->generarFolio($vMov->folio_movimiento),
          "fecha_sistema" => $this->mostrarUnixAFechaMexico($vMov->fecha_sistema),
          "tipo_movimiento" => $vMov->tipo_movimiento,
          "subtipo_movimiento" => $vMov->subtipo_movimiento,
          //"responsable" => $vEmp->userr,
          "responsable_token" => $pers_responsmov_token,
          "responsable_folio" => $pers_responsmov_folio,
          "responsable_name" => $pers_responsmov_name,
          //"cuenta_monedero" => $sql_cuenta_monedero,
          "movimiento_tipo" => $movimiento_tipo,
          "movimiento_token" => $movimiento_token,
          "movimiento_folio" => $movimiento_folio,
          "movimiento_name" => $movimiento_name,
          "monto_aplicado" => "$" . number_format($vMov->monto_aplicado, $this->getMonedaAPI($pay->p_moneda), '.', ',') . " $pay->p_moneda",
        );
        $desglose_pagos_medio[] = $row_mov;
      }

      $medio_pago_vinculado = "";
      $queryFormasDePago = DB::table("fnzs_pagos_pago AS payment")
        ->leftJoin("fnzs_pagos_cajas_pago AS p_caj", "payment.id", "=", "p_caj.pago_realizado")
        ->leftJoin("fnzs_catalogos_caja AS r_caj", "p_caj.caja_relacionada", "=", "r_caj.id")
        ->leftJoin("fnzs_pagos_cuentas_pago AS p_cuent", "payment.id", "=", "p_cuent.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas AS r_cuent", "p_cuent.cuenta_relacionada", "=", "r_cuent.id")
        ->leftJoin("fnzs_pagos_monederos_pago AS p_moned", "payment.id", "=", "p_moned.pago_realizado")
        ->leftJoin("fnzs_catalogos_cuentas_monedero AS r_moned", "p_moned.cuenta_relacionada", "=", "r_moned.id")
        ->where("payment.token_pagos", $pay->token_pagos)
        ->select("r_caj.*", "r_cuent.*", "r_moned.*")->get();
      //echo count($queryFormasDePago);
      //var_dump($queryFormasDePago);
      foreach ($queryFormasDePago as $vFPagoVinc) {
        if ($vFPagoVinc->token_caja !== null) {
          $medio_pago_vinculado = "Caja CAJ-" . $this->generarFolio($vFPagoVinc->no_caja);
        } elseif ($vFPagoVinc->token_cuenta !== null) {
          $medio_pago_vinculado = "Banco CUENT-" . $this->generarFolio($vFPagoVinc->folio_cuenta);
          //echo "Banco CUENT-".$this->generarFolio($vFPagoVinc->folio_cuenta);
        } elseif ($vFPagoVinc->token_cuentamonedero !== null) {
          $medio_pago_vinculado = "Monedero CUENTM-" . $this->generarFolio($vFPagoVinc->folio_cuentmon);
        }
      }
      //echo $medio_pago_vinculado;

      $row = array(
        "token_pagos" => $pay->token_pagos,
        "folio_pagos" => "PAGO-" . $this->generarFolio($pay->folio_pagos),
        //"folio_operacion" => $pay->folio_operacion,
        //"fecha_pago" => $this->mostrarUnixAFechaMexico($pay->fecha_pago),
        "fecha_contabilizacion" => !empty($pay->fecha_contabilizacion) ? $this->mostrarUnixAFechaMexico($pay->fecha_contabilizacion) : "",
        //cancelado
        "pago_cancelado" => $pay->pago_cancelado ? true : false,
        "pago_cancelado_translate" => $pay->pago_cancelado ? 'canceled_reg' : 'approved_reg',
        "pago_folio_cancelacion" => $pay->pago_cancelado ? "PCAN-" . $this->generarFolio($pay->pago_folio_cancelacion) : "",
        "pago_fecha_cancelacion" => $pay->pago_cancelado ? $this->mostrarUnixAFechaMexico($pay->pago_fecha_cancelacion) : "",
        "pago_fecha_contabilizacion_cancelacion" => $pay->pago_cancelado ? $this->mostrarUnixAFechaMexico($pay->pago_fecha_contabilizacion_cancelacion) : "",
        "monto_pago" => $pay->monto_pago,
        "monto_pago_format" => "$" . number_format($pay->monto_pago, $this->getMonedaAPI($pay->p_moneda), '.', ',') . " $pay->p_moneda",
        "monto_pago_resultant" => "$" . number_format($pay->monto_pago * $pay->tipo_cambio, $this->getMonedaAPI($pay->p_moneda), '.', ',') . " $pay->p_moneda",
        "observacionesPago" => !is_null($pay->observacionesPago) ? $this->desencriptar($pay->observacionesPago) : '',
        "tipo_cambio" => $pay->tipo_cambio,
        "tipo_cambio_format" => "$" . number_format($pay->tipo_cambio, $this->getMonedaAPI($pay->p_moneda), '.', ',') . " $pay->p_moneda",
        "p_moneda" => $pay->p_moneda,
        //forma_pago
        "forma_pago_pago" => !is_null($pay->forma_pago_pago) ? $pay->forma_pago_pago . " - " . $this->getFormasPagoAPI($pay->forma_pago_pago) : '',
        "forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
        ////tercero
        "destino" => $destino,

        "tercero_token" => $factura_relacionada_typo == 'anticipos' ? $prov_token : $tercero_token,
        "tercero_folio" => $factura_relacionada_typo == 'anticipos' ? $prov_folio : $tercero_folio,
        "tercero_name" => $factura_relacionada_typo == 'anticipos' ? $prov_name : $tercero_name,
        "tercero_comercial_name" => $factura_relacionada_typo == 'anticipos' ? $prov_comercial_name : $tercero_comercial_name,

        //"ant_prov_folio" => $prov_folio,
        //"ant_prov_token" => $prov_token,
        //"ant_prov_name" => $prov_name,
        //"ant_prov_comercial_name" => $prov_comercial_name,

        "financeadoa_token" => $financeadoa_token,
        "financeadoa_folio" => $financeadoa_folio,
        "financeadoa_name" => $financeadoa_name,
        "financeadoa_comercial_name" => $financeadoa_comercial_name,

        "concepto" => !empty($pay->concepto) ? $this->desencriptar($pay->concepto) : '',
        //personal_pago
        "personal_pago_token" => $p_paga_token,
        "personal_pago_folio" => $p_paga_folio,
        "personal_pago_name" => $p_paga_name,
        "pago_autorizado" => $pay->pago_autorizado ? true : false,
        "fecha_pago_auth" => $this->mostrarUnixAFechaMexico($pay->fecha_pago_auth),
        //personal_autoriza
        "personal_autoriza_token" => $p_autoriza_token,
        "personal_autoriza_folio" => $p_autoriza_folio,
        "personal_autoriza_name" => $p_autoriza_name,
        //ordenes_relacionadas
        "ordenes_relacionadas_lista" => $ordenes_relacionadas_lista,
        //desglose_pagos_medio

        "orden_factura_relacionada_typo" => $factura_relacionada_typo,
        "orden_factura_relacionada_token" => $factura_relacionada_token,
        "orden_factura_relacionada_string" => $factura_relacionada_string,

        "desglose_pagos_medio" => $desglose_pagos_medio,
        "medio_pago_vinculado" => $medio_pago_vinculado,
        "doc_anterior_folio" => $doc_anterior_folio,
        "doc_anterior_fecha_contabilizacion" => $doc_anterior_fecha_contabilizacion,
      );
      $lista_pagos_realizados[] = $row;
    }

    return $lista_pagos_realizados;
  }

  public function trabSueldosUltimo($empleado_token){
    return DB::table("vhum_empleados_registro_salarial AS sal_actual")
      ->join("vhum_empleados_catalogo AS trab", "sal_actual.trabajador", "trab.id")
      ->where("trab.empleado_token", $empleado_token)
      ->orderByDesc("sal_actual.id")
      ->select('sal_actual.salario_diario', 'sal_actual.salario_diario_integrado', 'sal_actual.entra_en_vigor')
      ->first();
  }

  public function trabSueldosHistorial($empleado_token){
    $sueldos_historial = array();
    $sueldosQuery = DB::table("vhum_empleados_registro_salarial AS sal_actual")
      ->join("vhum_empleados_catalogo AS trab", "sal_actual.trabajador", "trab.id")
      ->where("trab.empleado_token", $empleado_token)
      ->orderByDesc("sal_actual.id")
      ->get();

    foreach ($sueldosQuery as $vSueld) {
      $nomina_moneda_decimales = !is_null($vSueld->nomina_moneda) && $vSueld->nomina_moneda != '' ? $this->getMonedaAPI($vSueld->nomina_moneda) : 0;
      $row_sueldos = array(
        "salario_diario" => (!is_null($vSueld->salario_diario) ? number_format($vSueld->salario_diario, $nomina_moneda_decimales, '.', '') : '0.00') . " $vSueld->nomina_moneda",
        "salario_diario_integrado" => (!is_null($vSueld->salario_diario_integrado) ? number_format($vSueld->salario_diario_integrado, $nomina_moneda_decimales, '.', '') : '0.00') . " $vSueld->nomina_moneda",
        "entra_en_vigor" => !is_null($vSueld->entra_en_vigor) && $vSueld->entra_en_vigor != '' ? $this->mostrarUnixAFechaMexico($vSueld->entra_en_vigor) : '',
        "expira" => !is_null($vSueld->expira) && $vSueld->expira != '' ? $this->mostrarUnixAFechaMexico($vSueld->expira) : '',
        "motivo" => !is_null($vSueld->motivo) && $vSueld->motivo != '' ? $this->desencriptar($vSueld->motivo) : '',
      );
      $sueldos_historial[] = $row_sueldos;
    }
    return $sueldos_historial;
  }

  public function loginMainInside($codigo_acceso, $password, $token_device){
    $queryLogin = DB::select("SELECT users.usuario_token,emp.empresa_token,emp.root_tkn FROM teci_usuarios_catalogo AS users JOIN main_empresas AS emp 
        WHERE emp.id = users.empresa AND (users.acceso_codigo = ? OR users.acceso_email = ?) 
        AND users.acceso_password = ?", [$codigo_acceso, $codigo_acceso, $password]);
    $signup = is_object($queryLogin) || count($queryLogin) == 1 ? true : false;
    if ($signup) {
      foreach ($queryLogin as $user) {
        $permissionLogin = DB::table("sos_modulos_sistemas AS module")
          ->join("teci_usuarios_modulos_acceso AS mAccess", "module.id", "mAccess.modulo_vinculado")
          ->join("teci_usuarios_catalogo AS users", "mAccess.usuario", "users.id")
          ->where(["users.usuario_token" => $user->usuario_token])->get();
        if ($permissionLogin[0]->login_permission == TRUE && ($permissionLogin[0]->acceso == TRUE ||
          $permissionLogin[0]->outside_descarga_xml == TRUE || $permissionLogin[0]->outside_logistica == TRUE ||
          $permissionLogin[0]->outside_compras == TRUE || $permissionLogin[0]->outside_proyectos == TRUE ||
          $permissionLogin[0]->outside_terceros == TRUE || $permissionLogin[0]->outside_terceros_associates == TRUE ||
          $permissionLogin[0]->outside_terceros_clientes == TRUE || $permissionLogin[0]->outside_terceros_proveedores == TRUE ||
          $permissionLogin[0]->outside_terceros_empleados == TRUE)) {
          $queryModulo = DB::select("SELECT modulo FROM sos_modulos_sistemas WHERE acceso = TRUE");
          if (count($queryModulo) > 0) {
            $listadoModulos = array();
            $queryModulosList = DB::table("sos_modulos_sistemas")->orderBy("orden_listado", "ASC")->get();
            foreach ($queryModulosList as $vMod) {
              $rowMod = array(
                "modulo_token" => $vMod->token_modulo,
                "modulo_nombre" => $vMod->modulo,
                "modulo_mantenimiento" => $vMod->mantenimiento == TRUE ? true : false,
                "modulo_acceso" => $vMod->acceso == TRUE ? true : false,
              );
              $listadoModulos[] = $rowMod;
            }

            //$infoUser = User::join("vhum_empleados_catalogo AS pers","teci_usuarios_catalogo.empleado","=","pers.id") 
            //->join("teci_user_settings AS sett","teci_usuarios_catalogo.id","=","sett.usuario") 
            //->join("sos_personas AS people","pers.empleado_name","=","people.id") 
            //->where([ 'teci_usuarios_catalogo.acceso_codigo' => $codigo_acceso, 'teci_usuarios_catalogo.acceso_password' => $password]) 
            //->orwhere([ 'teci_usuarios_catalogo.acceso_email' => $codigo_acceso, 'teci_usuarios_catalogo.acceso_password' => $password])->get();
            //ALTER TABLE `teci_usuarios_catalogo` ADD  foreign key (acreedor) references fnzs_catalogo_acreedores (id);

            //$infoUser = User::join("fnzs_catalogo_acreedores AS acree","teci_usuarios_catalogo.acreedor","=","acree.id") 
            //->join("teci_user_settings AS sett","teci_usuarios_catalogo.id","=","sett.usuario") 
            //->join("sos_personas AS people","pers.empleado_name","=","people.id") 
            //->where([ 'teci_usuarios_catalogo.acceso_codigo' => $codigo_acceso, 'teci_usuarios_catalogo.acceso_password' => $password]) 
            //->orwhere([ 'teci_usuarios_catalogo.acceso_email' => $codigo_acceso, 'teci_usuarios_catalogo.acceso_password' => $password])->get();

            $infoUser = User::leftJoin("vhum_empleados_catalogo AS pers", "teci_usuarios_catalogo.empleado", "=", "pers.id")
              ->leftJoin("fnzs_catalogo_acreedores AS acree", "teci_usuarios_catalogo.acreedor", "=", "acree.id")
              ->leftJoin("sos_personas AS persona_empleado", "pers.empleado_name", "=", "persona_empleado.id")
              //->leftJoin("sos_personas AS persona_acreedor", "acree.acreedor", "=", "persona_acreedor.id")
              ->leftJoin("teci_user_settings AS sett", "teci_usuarios_catalogo.id", "=", "sett.usuario")
              ->where(function ($query) use ($codigo_acceso, $password) {
                $query->where([
                  ['teci_usuarios_catalogo.acceso_codigo', '=', $codigo_acceso],
                  ['teci_usuarios_catalogo.acceso_password', '=', $password],
                ])->orWhere([
                  ['teci_usuarios_catalogo.acceso_email', '=', $codigo_acceso],
                  ['teci_usuarios_catalogo.acceso_password', '=', $password],
                ]);
              })
              ->select(
                'teci_usuarios_catalogo.jerarquia_main',
                'teci_usuarios_catalogo.*',
                'sett.*',
                // Datos del empleado
                'pers.id AS id_empleado',
                'pers.empleado_token',
                'pers.nivel_empleado',
                'pers.folio_pers',
                'pers.fecha_alta_pers',
                'persona_empleado.paterno AS paterno_empleado',
                'persona_empleado.materno AS materno_empleado',
                'persona_empleado.nombre AS nombre_empleado',
                'persona_empleado.img_perfil',
                // Datos del acreedor
                'acree.token_cat_acreedores',
                'acree.acr_habilita_reembolsos',
                'acree.acr_titular AS nombre_acreedor'
              )
              //->first(); // o ->get() si esperas varios
              //->get()->toArray();
              ->get();

            foreach ($infoUser as $rUser) {
              $main_jerarquia = $rUser->jerarquia_main;
              $privilegio_crear = $rUser->privilegio_crear == TRUE ? true : false;
              $privilegio_editar = $rUser->privilegio_editar == TRUE ? true : false;
              $privilegio_consulta = $rUser->privilegio_consulta == TRUE ? true : false;
              $privilegio_elimina = $rUser->privilegio_elimina == TRUE ? true : false;
              $privilegio_ver_docs = $rUser->privilegio_ver_docs == TRUE ? true : false;

              //echo $rUser->empleado_token;
              //echo $rUser->token_cat_acreedores;

              if (isset($rUser->empleado_token) && isset($rUser->token_cat_acreedores)) {
                $name_user_data = $this->desencriptarNombres($rUser->paterno_empleado, $rUser->materno_empleado, $rUser->nombre_empleado);
              } elseif (isset($rUser->empleado_token) && !isset($rUser->token_cat_acreedores)) {
                $name_user_data = $this->desencriptarNombres($rUser->paterno_empleado, $rUser->materno_empleado, $rUser->nombre_empleado);
              } else {
                $name_user_data = $this->desencriptar($rUser->nombre_acreedor);
              }

              //$name_user_data = $this->desencriptarNombres($rUser->paterno,$rUser->materno,$rUser->nombre);
              Session::put('name_user_data', $name_user_data);

              if ($token_device != "") {
                $token_registro_sesion = $this->encriptarToken(time(), "firebase", rand(10, 100));
                $insertNewSessionUser = DB::select("INSERT INTO teci_usuarios_bitacora_sesiones (token_sesiones_registro,fecha_sesiones_registro,usuario,dispositivo_tipo,dispositivo_token) 
                                    VALUES (?,?,(SELECT id FROM teci_usuarios_catalogo WHERE usuario_token = ?),?,?)", [$token_registro_sesion, time(), $user->usuario_token, "web/movil", $token_device]);

                $searchDeviceWeb = User::join("teci_usuarios_dispositivos AS device", "teci_usuarios_catalogo.id", "device.usuario")
                  ->where(["device.dispositivo_token" => $token_device, "teci_usuarios_catalogo.usuario_token" => $user->usuario_token])->get();

                if (count($searchDeviceWeb) == 0) {
                  $token_registro_fire = $this->encriptarToken(time(), "firebase", rand(10, 100));
                  $updateWebTokenFirebase = DB::select("INSERT INTO teci_usuarios_dispositivos (token_dispositivo,usuario,dispositivo_token) 
                                        VALUES (?,(SELECT id FROM teci_usuarios_catalogo WHERE usuario_token = ?),?)", [$token_registro_fire, $user->usuario_token, $token_device]);
                }
              }

              $user_logo_text = $rUser->img_perfil ? $this->desencriptar($rUser->img_perfil) : 'default-profile.png';
              $user_logo_path = 'public/root/main_users/' . $this->generar($rUser->folio_pers) . '-' . $rUser->fecha_alta_pers . '/';
              $avatar = $this->encriptaBase64(Storage::path($user_logo_text != 'default-profile.png' ? $user_logo_path . $user_logo_text . '-profile.png' : 'public/settings/default-profile.png'));

              $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora")
                ->join("teci_usuarios_catalogo AS users", "histBitacora.usuario", "=", "users.id")
                ->where(["users.usuario_token" => $user->usuario_token])->get();

              $token = array("user_token" => $user->usuario_token, "empresa_token" => "");
              $update_pass = count($histBitacora) == 0 ? true : false;

              $data_user = array(
                "user_token" => $user->usuario_token,
                "name" => $name_user_data,
                "empleado_token" => $rUser->empleado_token ? $rUser->empleado_token : '',
                "nivel_empleado" => $rUser->nivel_empleado ? $rUser->nivel_empleado : '',
                "settings_lenguaje" => $rUser->lenguaje,
                "main_jerarquia" => $main_jerarquia,
                "main_privilegio_crear" => $privilegio_crear,
                "main_privilegio_editar" => $privilegio_editar,
                "main_privilegio_consulta" => $privilegio_consulta,
                "main_privilegio_elimina" => $privilegio_elimina,
                "main_privilegio_ver_docs" => $privilegio_ver_docs,
                "habilita_reembolsos" => $rUser->acr_habilita_reembolsos ? true : false,
                "token_cat_acreedores" => $rUser->token_cat_acreedores ? $rUser->token_cat_acreedores : '',
                "avatar" => $avatar,
                "company" => [],
                "listadoModulos" => $listadoModulos,
                "update_pass" => $update_pass,
                "iat" => time(),
                "exp" => time() + (7 * 24 * 60 * 60),
              );

              $jwt = JWT::encode($token, $this->key, 'HS256');
              $jwt_data_user = JWT::encode($data_user, $this->key, 'HS256');
              $decodeTkn = JWT::decode($jwt_data_user, new Key($this->key, 'HS256'));

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "modulo_destino" => "./plataformas/home",
                //"modulo_destino" => "./plataformas/selecciona_empresa",
                "modulo_title" => "",
                "large_token_access" => $jwt,
                "modulo_code" => "bEIxeFFKY2k4RnFEbWtnWDE5c1dKMGN5TFUwSW5EY0pTditvM3drV3FzTnFCZVhZN3A5aDREM3ZLRHF1YjFGUmNhY1pacDJDS3JsTm9RSXF6SkVTS2c9PTo6MTIzNDU2NzgxMjM0NTY3OA==",
                "main_privilegio_crear" => $privilegio_crear,
                "main_privilegio_editar" => $privilegio_editar,
                "main_privilegio_consulta" => $privilegio_consulta,
                "main_privilegio_elimina" => $privilegio_elimina,
                "main_privilegio_ver_docs" => $privilegio_ver_docs,
                "dataUsers" => $decodeTkn,
                "lenguaje" => $rUser->lenguaje,
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Acceso no permitido, módulos en construcción o en mantenimiento',
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 400,
            "message" => "Acceso no permitido, usuario bloqueado"
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'Código de acceso o contraseña incorrectos',
        'codigo_acceso' => $codigo_acceso,
        'password' => $password,
      );
    }
    return $dataMensaje;
  }
}
