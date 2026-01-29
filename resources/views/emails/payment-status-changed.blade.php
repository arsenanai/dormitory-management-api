<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status Update</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&display=swap&subset=cyrillic-ext');
        body { font-family: 'Noto Sans', Arial, sans-serif; background: #f3f4f9; color: #232743; margin: 0; padding: 0; line-height: 1.6; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(47,52,89,0.08); overflow: hidden; }
        .header { background: #2f3459; color: #fff; padding: 1.25rem 2rem; text-align: center; }
        .header h1 { font-size: 1.25rem; font-weight: 700; letter-spacing: -0.5px; margin: 0 0 0.25rem 0; color: #fff; }
        .header p { margin: 0; font-size: 0.9rem; opacity: 0.9; }
        .content { padding: 2rem; }
        .content p { margin: 0 0 1rem 0; }
        .payment-info { background: #e1e3f0; padding: 1rem 1.25rem; border-radius: 0.5rem; margin: 1.25rem 0; }
        .payment-info p { margin: 0.4rem 0; }
        .payment-info strong { color: #2f3459; }
        .mail-footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f9; font-size: 0.95em; color: #888; text-align: center; }
        .mail-footer p { margin: 0.25rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Status Update</h1>
            <p>Dormitory Management System</p>
        </div>
        <div class="content">
            <p>Hello {{ $user->first_name ? $user->first_name . ' ' . $user->last_name : $user->name }},</p>
            <p>Your payment status has been updated.</p>
            <div class="payment-info">
                <p><strong>Deal number:</strong> {{ $payment->deal_number ?? '—' }}</p>
                <p><strong>Amount:</strong> {{ $amountFormatted }}</p>
                <p><strong>Current status:</strong> {{ $currentStatus }}</p>
                @if($payment->date_from && $payment->date_to)
                <p><strong>Period:</strong> {{ $payment->date_from->format('M d, Y') }} – {{ $payment->date_to->format('M d, Y') }}</p>
                @endif
            </div>
            <p>If you have questions, please contact your dormitory administration.</p>
        </div>
        <div class="mail-footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Dormitory Management System.</p>
        </div>
    </div>
</body>
</html>
