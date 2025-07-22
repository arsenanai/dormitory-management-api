<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="/assets/wanyrak-logo.png">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Password Reset Request') }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&display=swap&subset=cyrillic-ext');
        body {
            font-family: 'Noto Sans', Arial, sans-serif;
            background: #f3f4f9;
            color: #232743;
            margin: 0;
            padding: 0;
        }
        .container {
            margin: 40px auto;
            max-width: 480px;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 8px rgba(47,52,89,0.08);
            padding: 2rem;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 1.2em;
            color: #2F3459;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .button {
            display: inline-block;
            background: #2F3459;
            color: #fff !important;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin: 2rem 0 1.5rem 0;
            box-shadow: 0 1px 2px rgba(47,52,89,0.08);
            transition: background 0.2s;
        }
        .button:hover, .button:focus {
            background: #232743;
        }
        .mail-footer {
            margin-top: 2.5rem;
            font-size: 0.95em;
            color: #888;
            text-align: left;
            padding-top: 1.5rem;
            border-top: 1px solid #f3f4f9;
        }
        .mail-footer a {
            color: #2F3459;
            text-decoration: none;
            font-weight: 700;
        }
        .logo {
            display: block;
            margin: 0 auto 1.5rem auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/assets/sdu-logo.png" alt="SDU Logo" class="logo" style="display:block; margin:0 auto 1.5rem auto; height:90px; width: 160px;" onerror="this.style.display='none'">
        <h1>{{ __('Password Reset Request') }}</h1>
        <p style="margin-bottom: 1.5rem;">{{ __('Click the button below to reset your password:') }}</p>
        <div style="text-align:center;">
            <a href="{{ $resetUrl }}" class="button">{{ __('Reset Password') }}</a>
        </div>
        <p style="margin-top: 2rem;">{{ __('If you did not request a password reset, no further action is required.') }}</p>
        <div class="mail-footer">
            {{ __('Sincerely yours,') }}<br>
            <a href="{{ config('app.url') }}">{{ config('app.name') }}</a>
        </div>
    </div>
</body>
</html>