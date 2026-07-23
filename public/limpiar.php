<?php
// Evitamos cualquier salida previa que bloquee las cabeceras
ob_start();

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

try {
    // Ejecutamos la limpieza antes de imprimir nada
    $kernel->call('config:clear');
    $kernel->call('cache:clear');
    $kernel->call('route:clear');
    
    $cachePath = __DIR__.'/../bootstrap/cache/config.php';
    $deletedFile = false;
    if (file_exists($cachePath)) {
        unlink($cachePath);
        $deletedFile = true;
    }

    // Ahora sí podemos imprimir el resultado
    echo "<h2>Limpieza del sistema contable finalizada</h2>";
    echo "Comandos Artisan ejecutados correctamente 1.<br>";
    if ($deletedFile) echo "Archivo bootstrap/cache/config.php eliminado físicamente.<br>";
    echo "<br><strong>Ya puedes probar el login en Angular.</strong>";

} catch (Exception $e) {
    echo "Error durante la limpieza: " . $e->getMessage();
}

ob_end_flush();