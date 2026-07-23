<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        //\App\Http\Middleware\SecurityHeaders::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            //\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            'refresh.user',     // 👤 primero
            'jwt.auth',         // 👤 identidad
        
            'refresh.moriah',   // 🏢 luego
            'moriah.context',   // 🏢 contexto empresa
            
            'activity.log', // 👈 AQUÍ
            
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
        'moriah.context' => \App\Http\Middleware\EnsureMalchutContext::class,
    
        'refresh.user' => \App\Http\Middleware\RefreshUserTokenMiddleware::class,
        'refresh.moriah' => \App\Http\Middleware\RefreshEmpresaTokenMiddleware::class,
        'activity.log' => \App\Http\Middleware\UserActivityMiddleware::class,
        
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        //'auth' => \App\Http\Middleware\Authenticate::class,
        //'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        //'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        //'can' => \Illuminate\Auth\Middleware\Authorize::class,
        //'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        //'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        //'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        //'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        //'cors' => \App\Http\Middleware\Cors::class,
    ];
}