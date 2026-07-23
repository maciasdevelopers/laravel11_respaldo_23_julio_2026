<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB; // También te falta importar DB y Str probablemente
use Illuminate\Support\Str;
use App\Models\User; // <--- AGREGA ESTO (o App\User si usas Laravel antiguo)

class Kernel extends ConsoleKernel{
  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule){
    $schedule->call(function () {
      // Instanciamos tu Helper para usar las funciones de envío y desencriptación
      $jwtAuth = new \App\Helpers\JwtAuth();
      $ahora = time();
      $empresas = DB::table('main_empresas AS emp')
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->get();
      foreach($empresas as $emp) {
        $pendientes = DB::table('eegr_activos_fijos_unidades')
        ->where('empresa', $emp->id)
        ->where(function($query) use ($ahora) {
          $query->where("fecha_proximo_corte_contable", '<=', time())
            ->orWhere("fecha_proximo_corte_fiscal", '<=', time());
        })
        ->count();

        if ($pendientes > 0) {
          $contador = DB::table('main_empresa_usuario')
          ->where('empresa', $emp->id)
          //->where('rol_contabilidad', true)
          ->first();
          if ($contador) {
            $titulo_alerta = "Existen {$pendientes} activos pendientes de depreciar en {$emp->abrev_nombre}.";
            $yaExiste = DB::table('teci_notificaciones')
            ->where(['notifiable_id' => $contador->usuario, 'status_recibe' => 0, 'type' => 'Activos Fijos'])
            ->exists();
            if (!$yaExiste) {
              $token_notificacion = $jwtAuth->encriptarToken($titulo_alerta,$emp->id, 3);
              DB::table('teci_notificaciones')
              ->insert(
                array(
                  "id" => Str::uuid()->toString(),
                  "token_notificacion" => $token_notificacion,
                  "fecha_notificacion" => time(),
                  "type" => "Activos Fijos",
                  'notifiable_type' => User::class,
                  "asunto" => $titulo_alerta,
                  "data" => json_encode([
                    "titulo" => $titulo_alerta,
                    "accion" => "activos_fijos_pendientes_depreciar"
                  ]),
                  "empresa" => $emp->id,
                  "notifiable_id" => 3,
                  "visto" => FALSE,
                  "status_recibe" => FALSE,
                  "status_delete" => TRUE,
                  "fecha_delete" => NULL,
                )
              );
            }
          }
        }
      }
    })->dailyAt('08:00'); // El cartero pasa cada minuto a revisar el buzón

    $schedule->call(function () {
      $jwtAuth->notificacionPushDevices("ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY", "SOS-México informa: ", "Alerta 1");
      // 1. Buscamos las notificaciones pendientes de envío físico
      // Usamos status_recibe = 0 según la estructura de tu tabla
      $pendientes = DB::table('teci_notificaciones')
      ->where('status_recibe', 0)
      ->where('status_delete', 1) // Solo las que no han sido borradas
      ->limit(20) // Procesamos por bloques para evitar saturar Vonage/Firebase
      ->get();

      foreach ($pendientes as $notif) {
        try {
          // A. OBTENER TELÉFONO Y ENVIAR SMS
          $telQuery = DB::table("sos_personas_telefonos AS tels")
          ->where("tels.personal", $notif->notifiable_id)
          ->where("tels.habilitado", true)
          ->select("tels.telefono")
          ->first();

          if ($telQuery) {
            $phone_numero = $jwtAuth->desencriptar($telQuery->telefono);
            // Usamos el asunto de la tabla como cuerpo del mensaje
            $jwtAuth->enviaSMS($phone_numero, "SOS-México: " . $notif->asunto);
          }

          // B. OBTENER TOKEN Y ENVIAR PUSH (FIREBASE)
          $userDevice = DB::table("teci_usuarios_catalogo")
          ->where("id", $notif->notifiable_id)
          ->value("usuario_token");

          if ($userDevice) {
            $jwtAuth->notificacionPushDevices($userDevice, "SOS-México informa: ", $notif->asunto);
          }

          // C. ACTUALIZAR ESTADO (MARCAR COMO ENTREGADO)
          // Esto evita que el cartero lo vuelva a enviar en el siguiente minuto
          DB::table('teci_notificaciones')
          ->where('id', $notif->id)
          ->update([
            'status_recibe' => 1,
            'updated_at' => date('Y-m-d H:i:s')
          ]);
        } catch (\Exception $e) {
          // Si algo falla con Vonage o Firebase, lo registramos pero no detenemos el Cron
          \Log::error("Error enviando notificación ID {$notif->id}: " . $e->getMessage());
        }
      }
    })->everyMinute(); // El cartero pasa cada minuto a revisar el buzón
  }

  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands(){
    $this->load(__DIR__.'/Commands');
    require base_path('routes/console.php');
  }
}
