<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md bg-white p-8 rounded shadow">
        <h1 class="text-2xl font-bold mb-6">Iniciar sesión</h1>

        @if($errors->any())
            <div class="mb-4 text-red-700">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label class="block mb-2">Correo</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full mb-4 p-2 border rounded">

            <label class="block mb-2">Contraseña</label>
            <input type="password" name="password" required class="w-full mb-4 p-2 border rounded">

            <div class="flex items-center mb-4">
                <input type="checkbox" name="remember" id="remember" class="mr-2" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">Recuérdame</label>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded">Entrar</button>
        </form>
    </div>
</body>
</html>
