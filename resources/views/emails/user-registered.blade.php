<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Registration Complete' }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&display=swap&subset=cyrillic-ext');
        body { font-family: 'Noto Sans', Arial, sans-serif; background: #f3f4f9; color: #232743; margin: 0; padding: 0; line-height: 1.6; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(47,52,89,0.08); overflow: hidden; }
        .header { background: #2f3459; color: #fff; padding: 1.25rem 2rem; text-align: center; }
        .header h1 { font-size: 1.25rem; font-weight: 700; letter-spacing: -0.5px; margin: 0 0 0.25rem 0; color: #fff; }
        .header p { margin: 0; font-size: 0.9rem; opacity: 0.9; }
        .content { padding: 2rem; }
        .content p { margin: 0 0 1rem 0; }
        .mail-footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f9; font-size: 0.95em; color: #888; text-align: center; }
        .mail-footer p { margin: 0.25rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title ?? 'Registration Complete' }}</h1>
            <p>{{ $systemName ?? 'Dormitory Management System' }}</p>
        </div>
        <div class="content">
            <p>{{ $greeting }}</p>
            <p>{{ $body }}</p>
            <p>{{ $contactHint }}</p>
        </div>
        <div class="mail-footer">
            <p>{{ $footerAutomated }}</p>
            <p>{{ $footerCopyright }}</p>
        </div>
    </div>
</body>
</html>
