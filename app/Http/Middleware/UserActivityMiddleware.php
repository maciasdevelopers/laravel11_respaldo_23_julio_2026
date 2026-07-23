<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserActivityMiddleware{
  public function handle(Request $request, Closure $next){
      
    if ($request->isMethod('get')) {
        return $next($request);
    }
      
    $response = $next($request);

    $userAuth = $request->get('user_auth');
    //var_dump($usuario);
    if (!$userAuth) {
      return $response;
    }

    $empresaCtx = $request->get('malchut_ctx');

    DB::table('teci_usuarios_actividad_logs')->insert([
      'usuario'     => $userAuth->user_id,
      'empresa'  => $empresaCtx->empresa_id ?? null,
      'method'      => $request->method(),
      'endpoint'    => $request->path(),
      'ip_address'  => $request->ip(),
      'user_agent'  => substr($request->userAgent(), 0, 255),
      'status_code' => $response->getStatusCode(),
      'created_at'  => now(),
    ]);

    return $response;
  }
}
