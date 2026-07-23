<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Symfony\Component\HttpFoundation\Response;

class RefreshEmpresaTokenMiddleware{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function handle(Request $request, Closure $next): Response{
    $token = $request->cookie('moriah_key');
  
    if (!$token) {
      return $next($request);
    }

    $payloadData = null;
    $shouldRefresh = false;
  
    try {
      $key = config('services.jwt.secret');
      $decoded = JWT::decode($token, new Key($key, 'HS256'));
      $payloadData = $decoded;
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
        $payloadData = JWT::jsonDecode(JWT::urlsafeB64Decode($tks[1]));
        $shouldRefresh = true;
      }
    } catch (\Throwable $e) {
      return $next($request);
    }
    
    // Validar que tengamos los datos necesarios para el contexto
    if ($payloadData && $shouldRefresh && !empty($payloadData->empresa_token)) {
      $newPayload = [
        'ctx'           => 'moriah',
        'user_token'    => $payloadData->user_token ?? null,
        'empresa_token' => $payloadData->empresa_token,
        'iat'           => time(),
        'exp'           => time() + (30 * 60) // 30 minutos más
      ];

      $newJwt = JWT::encode($newPayload, config('services.jwt.secret'), 'HS256');
      $request->cookies->set('moriah_key', $newJwt);

      // Mantener consistencia con el dominio (.sos-mexico.com.mx)
      $cookie = cookie(
        'moriah_key',
        $newJwt,
        240, // 4 horas
        '/',
        '.sos-mexico.com.mx', 
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