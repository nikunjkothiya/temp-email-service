<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
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

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
        }

        .btn-container {
            text-align: center;
            margin: 30px 0;
        }

        .link-fallback {
            background: #0f172a;
            padding: 15px;
            border-radius: 8px;
            word-break: break-all;
            font-size: 0.85rem;
            color: #818cf8;
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
        <h1>Verify Your Email Address</h1>
        <p>Hi {{ $user->name }},</p>
        <p>Thanks for registering! Please click the button below to verify your email address and activate your account.
        </p>

        <div class="btn-container">
            <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
        </div>

        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <div class="link-fallback">{{ $verificationUrl }}</div>

        <p style="margin-top: 20px;">This link will expire in 24 hours.</p>

        <div class="footer">
            <p>If you didn't create an account, you can safely ignore this email.</p>
        </div>
    </div>
</body>

</html>