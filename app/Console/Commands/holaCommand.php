<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class holaCommand extends Command{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hola-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(){
        /*$data = [
            "to" => "f8wGzYVqTSG2n7Mee5v_4r:APA91bHZhjrsFa5hQtKw07iybVcrpUm_uDwALmy4Y49MMn3dBJsGwz-RU7-X-Zvklm3CtgtsXn-AeAo6kHT3srwHYtKTHLaX6tO-6aOVyHIJPBa7NbLfqNVDQIrjhv6SMTNniUU2sAUk",
            "notification" => array ("title" => "prueba","body" => "es solo un test")
        ];

        $curl = curl_init();
        curl_setopt_array($curl,
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
        return $response;*/
        Log::info("envio de datos");
        return 0;
    }
}
