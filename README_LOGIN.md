# Login básico seguro para Laravel

Archivos añadidos:

- `app/Http/Requests/Auth/LoginRequest.php` — validación de inputs.
- `app/Http/Controllers/Auth/LoginController.php` — mostrar, procesar login y logout con throttle.
- `resources/views/auth/login.blade.php` — vista simple con Tailwind.
- `resources/views/dashboard.blade.php` — vista protegida de ejemplo.
- `routes/web.php` — rutas `login`, `logout` y `dashboard`.
- `tests/Feature/AuthLoginTest.php` — pruebas de integración (opcional).

Pasos para probar localmente:

1. Asegúrate de tener las dependencias instaladas:

```powershell
composer install
php artisan migrate
```

2. Crear un usuario (tinker o factory):

```powershell
php artisan tinker
\App\Models\User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('secret123')]);
exit
```

3. Servir la app y acceder a `/login`:

```powershell
php artisan serve
# Abrir http://127.0.0.1:8000/login
```

4. Ejecutar pruebas (opcional):

```powershell
php artisan test --filter=AuthLoginTest
```

Notas:
- El controlador usa `RateLimiter` para bloquear tras `5` intentos por minuto.
- Ajusta `maxAttempts` y `decaySeconds` en `LoginController` según tu política de seguridad.
