<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Created - Dormitory Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #2F3459;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        .payment-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .payment-info h3 {
            margin: 0 0 10px 0;
            color: #2F3459;
        }
        .payment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .payment-detail {
            padding: 5px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 Payment Created</h1>
        <p>Dormitory Management System</p>
    </div>

    <div class="content">
        <p>Dear {{ $user->first_name }} {{ $user->last_name }},</p>
        
        <p>A new payment has been created for your dormitory accommodation. Please find the payment details below:</p>
        
        <div class="payment-info">
            <h3>Payment Information</h3>
            <div class="payment-details">
                <div class="payment-detail">
                    <strong>Payment ID:</strong><br>
                    {{ $payment->id }}
                </div>
                <div class="payment-detail">
                    <strong>Deal Number:</strong><br>
                    {{ $payment->deal_number }}
                </div>
                <div class="payment-detail">
                    <strong>Amount:</strong><br>
                    {{ number_format($payment->amount, 2) }} KZT
                </div>
                <div class="payment-detail">
                    <strong>Payment Type:</strong><br>
                    {{ ucfirst($payment->payment_type) }}
                </div>
                <div class="payment-detail">
                    <strong>Status:</strong><br>
                    {{ ucfirst($payment->status) }}
                </div>
                <div class="payment-detail">
                    <strong>Payment Period:</strong><br>
                    {{ $payment->date_from->format('M d, Y') }} - {{ $payment->date_to->format('M d, Y') }}
                </div>
                <div class="payment-detail">
                    <strong>Due Date:</strong><br>
                    {{ $payment->deal_date->format('M d, Y') }}
                </div>
            </div>
        </div>
        
        <p>Please ensure payment is made before the due date to avoid any service interruptions.</p>
    </div>

    <div class="footer">
        <p>This is an automated message from the Dormitory Management System. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} Dormitory Management System. All rights reserved.</p>
    </div>
</body>
</html>
