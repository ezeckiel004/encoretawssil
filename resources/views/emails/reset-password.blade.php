<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .email-wrapper {
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f44d0b;
            padding-bottom: 20px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #f44d0b;
        }
        .content {
            margin: 30px 0;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        .message {
            font-size: 14px;
            line-height: 1.8;
            color: #666;
            margin-bottom: 20px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .reset-button {
            background: #f44d0b;
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            transition: background 0.3s;
        }
        .reset-button:hover {
            background: #e0450a;
        }
        .link-container {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            word-break: break-all;
        }
        .link-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }
        .reset-link {
            color: #f44d0b;
            text-decoration: none;
            font-size: 13px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 13px;
        }
        .expire-time {
            color: #f44d0b;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <!-- Header -->
            <div class="header">
                <div class="logo">🚚 Tawssil</div>
                <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">Réinitialiser votre mot de passe</p>
            </div>

            <!-- Content -->
            <div class="content">
                <p class="greeting">Bonjour,</p>

                <p class="message">
                    Vous avez demandé à réinitialiser votre mot de passe Tawssil. 
                    Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe.
                </p>

                <!-- Button -->
                <div class="button-container">
                    <a href="{{ $resetLink }}" class="reset-button">
                        Réinitialiser mon mot de passe
                    </a>
                </div>

                <!-- Fallback Link -->
                <div class="link-container">
                    <p class="link-label">Ou copiez ce lien dans votre navigateur:</p>
                    <a href="{{ $resetLink }}" class="reset-link">{{ $resetLink }}</a>
                </div>

                <!-- Warning -->
                <div class="warning">
                    ⏰ Ce lien de réinitialisation expire dans <span class="expire-time">{{ $expiresIn }}</span>.
                </div>

                <!-- Security Message -->
                <p class="message" style="font-size: 12px; color: #999;">
                    💡 Si vous n'avez pas demandé la réinitialisation de votre mot de passe, 
                    ignorez simplement cet email ou contactez notre support.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>© {{ date('Y') }} Tawssil - Tous droits réservés</p>
                <p>Cet email a été envoyé à {{ $email }}</p>
            </div>
        </div>
    </div>
</body>
</html>