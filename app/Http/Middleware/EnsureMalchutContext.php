<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class EnsureMalchutContext{
  public function handle(Request $request, Closure $next): Response{
    $userAuth = $request->get('user_auth');
  
    if (!$userAuth || empty($userAuth->keter_davidic)) {
      return response()->json(['error' => 'Usuario no autenticado'], 401);
    }
  
    $userToken = $userAuth->keter_davidic;
  
    // 2️⃣ Contexto empresa desde JWT (cookie)
    $ctxJwt = $request->cookie('moriah_key');
  
    if (!$ctxJwt) {
      return response()->json(['code' => 'EMPRESA_NO_SELECCIONADA','message' => 'Debe seleccionar una empresa'], 428);
    }
  
    try {
      $decoded = JWT::decode($ctxJwt,new Key(config('services.jwt.secret'), 'HS256'));
  
      if (empty($decoded->empresa_token) || empty($decoded->user_token) || $decoded->user_token !== $userToken) {
        throw new \Exception('Contexto inválido');
      }
  
      $empresaToken = $decoded->empresa_token;
    } catch (\Exception $e) {
      return response()->json(['error' => 'Contexto de empresa inválido o expirado'], 401);
    }
  
    // 3️⃣ Cache key fuerte
    $cacheKey = "malchut_ctx:{$userToken}:{$empresaToken}";
  
    // 4️⃣ Validar vínculo usuario–empresa
    $empresa = Cache::remember(
      $cacheKey,
      now()->addMinutes(10),
      function () use ($userToken, $empresaToken) {
        return DB::table('main_empresa_usuario AS eu')
          ->join('main_empresas AS emp', 'eu.empresa', '=', 'emp.id')
          ->join('teci_usuarios_catalogo AS usr', 'eu.usuario', '=', 'usr.id')
          ->where('emp.empresa_token', $empresaToken)
          ->where('usr.usuario_token', $userToken)
          ->where('emp.status_empresa', true)
          ->select('emp.id','emp.empresa_token')
          ->first();
      }
    );
  
    if (!$empresa) {
      return response()->json(['error' => 'Empresa no vinculada al usuario o desactivada'], 403);
    }
  
    // 5️⃣ Inyectar contexto
    $request->attributes->set('malchut_ctx', (object) [
      'empresa_id' => $empresa->id,
      'malchut_hotam' => $empresa->empresa_token
    ]);
  
    return $next($request);
  }
}