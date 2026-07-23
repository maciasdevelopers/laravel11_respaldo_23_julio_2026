<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Symfony\Component\HttpFoundation\Response;

class RefreshUserTokenMiddleware{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function handle(Request $request, Closure $next): Response{
    $token = $request->cookie('code_inside');
  
    if (!$token) {
      return $next($request);
    }
  
    $userToken = null;
    $shouldRefresh = false;

    try {
      $key = config('services.jwt.secret');
      $decoded = JWT::decode($token, new Key($key, 'HS256'));
      $userToken = $decoded->user_token ?? null;
      $shouldRefresh = ($decoded->exp - time()) <= 1200; // Quedan 20 min o menos (se refresca cada 10 min si exp es 30 min)
    } catch (ExpiredException $e) {
      /**
       * 🚨 EL SALVAVIDAS:
       * Si el JWT expiró, lo decodificamos manualmente (sin validar tiempo) 
       * para recuperar el 'user_token' y generar uno nuevo, ya que la cookie 
       * de 4 horas aún es válida ante el navegador.
       */
      $tks = explode('.', $token);
      if (count($tks) === 3) {
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($tks[1]));
        $userToken = $payload->user_token ?? null;
        $shouldRefresh = true;
      }
    } catch (\Throwable $e) {
      // Error de firma o formato: mejor dejar que el JwtMiddleware lo maneje
      return $next($request);
    }
  
    if ($userToken && $shouldRefresh) {
      $newPayload = [
        'user_token' => $userToken,
        'iat'        => time(),
        'exp'        => time() + (30 * 60), // Nuevo aire de 30 minutos
      ];
  
      $newJwt = JWT::encode($newPayload, config('services.jwt.secret'), 'HS256');
  
      /**
       * 🔥 CORRECCIÓN CLAVE:
       * Actualizamos la cookie en el objeto $request para que los siguientes 
       * middlewares (como JwtMiddleware) vean el token nuevo y no el viejo/expirado.
       */
      $request->cookies->set('code_inside', $newJwt);

      // Importante: Usar el mismo dominio que en el Login
      $cookie = cookie(
        'code_inside',
        $newJwt,
        240, // 4 horas
        '/',
        '.sos-mexico.com.mx', // El punto inicial ayuda a la compatibilidad entre subdominios
        true,
        true,
        false,
        'None'
      );

      $response = $next($request);
      return $response->withCookie($cookie);
    }
  
    return $next($request);
  }
}