<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · GenAI Ratel CMS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
<main class="login-card card">
    <h1>GenAI Ratel CMS</h1>
    <p class="muted">Proof of concept for Aquarium Park Dhaka. Sign in with the administrator account from <code>.env</code>.</p>

    <form method="POST" action="{{ route('login.attempt') }}" class="form">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        @error('email')
            <p class="field-error" role="alert">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn btn-primary">Log in</button>
    </form>
</main>
</body>
</html>
