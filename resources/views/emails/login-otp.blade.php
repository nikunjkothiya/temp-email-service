<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Login OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f172a;
            color: #f1f5f9;
            margin: 0;
            padding: 40px 20px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            background: #1e293b;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .logo {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 30px;
        }

        h1 {
            color: #f1f5f9;
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }

        p {
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .otp-code {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 12px;
            text-align: center;
            padding: 20px 30px;
            border-radius: 12px;
            margin: 30px 0;
        }

        .warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            padding: 15px;
            color: #fbbf24;
            font-size: 0.9rem;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #334155;
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">📧 Temp Mail</div>
        <h1>Your Login Verification Code</h1>
        <p>Hi {{ $user->name }},</p>
        <p>Use the following code to complete your login:</p>

        <div class="otp-code">{{ $otpCode }}</div>

        <div class="warning">
            ⚠️ This code expires in 10 minutes. Do not share this code with anyone.
        </div>

        <div class="footer">
            <p>If you didn't attempt to log in, please ignore this email and consider changing your password.</p>
        </div>
    </div>
</body>

</html>