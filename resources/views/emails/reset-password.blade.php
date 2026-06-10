<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 24px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 12px;
        }
        .text {
            font-size: 15px;
            color: #6B7280;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .button {
            display: inline-block;
            background-color: #4F46E5;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
        }
        .footer {
            margin-top: 32px;
            font-size: 13px;
            color: #9CA3AF;
        }
        .expire {
            margin-top: 24px;
            font-size: 13px;
            color: #EF4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🧵 Threadly</div>
        <div class="title">Reset Password</div>
        <p class="text">
            Halo <strong>{{ $username }}</strong>,<br><br>
            Kami menerima permintaan untuk mereset password akun kamu.
            Klik tombol di bawah untuk membuat password baru.
        </p>
        <a href="{{ $resetUrl }}" class="button">Reset Password</a>
        <p class="expire">Link ini akan kadaluarsa dalam 60 menit.</p>
        <p class="text" style="margin-top: 24px;">
            Jika kamu tidak merasa meminta reset password, abaikan email ini.
            Password kamu tidak akan berubah.
        </p>
        <div class="footer">
            © {{ date('Y') }} Threadly. All rights reserved.
        </div>
    </div>
</body>
</html>
