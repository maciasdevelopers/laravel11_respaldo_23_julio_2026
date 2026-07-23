<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class JwtMiddleware{
  public function handle(Request $request, Closure $next): Response{
    // 1️⃣ OBTENER TOKEN SOLO DESDE COOKIE
    $token = $request->cookie('code_inside');

    if (!$token) {
      return response()->json(['error' => 'No autenticado'], 401);
    }

    try {
      // 2️⃣ DECODIFICAR TOKEN
      $key = config('services.jwt.secret');
      $decoded = JWT::decode($token, new Key($key, 'HS256'));

      // 3️⃣ VALIDAR USUARIO
      $usuario = User::where('usuario_token', $decoded->user_token ?? null)->first();
      if (!$usuario) {
        return response()->json(['error' => 'Usuario inválido'], 401);
      }

      /**
       * OPCIONAL PERO RECOMENDADO:
       * Autenticar formalmente al usuario en Laravel para que Auth::user() funcione.
       */
      //Auth::login($usuario);

      // 4️⃣ INYECTAR CONTEXTO SEGURO
      $request->attributes->set('user_auth', (object) [
        'user_id' => $usuario->id,
        'keter_davidic' => $decoded->user_token, //“He hallado a David mi siervo; lo ungí con mi santa unción.” — Salmos 89:20
        'herald_royal' => $usuario,//“Clama a voz en cuello, no te detengas.” — Isaías 58:1
      ]);
			
      return $next($request);
    } catch (Exception $e) {
      return response()->json(['error' => 'Sesión expirada o inválida'], 401);
    }
  }
}