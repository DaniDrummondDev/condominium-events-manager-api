<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convite para {{ $condominiumName }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 30px; margin-bottom: 20px;">
        <h1 style="color: #2d3748; margin-top: 0;">Bem-vindo ao {{ $condominiumName }}</h1>

        <p>Olá, <strong>{{ $name }}</strong>!</p>

        <p>Você foi convidado(a) para fazer parte do <strong>{{ $condominiumName }}</strong>.</p>

        <p>Para ativar sua conta, utilize o token abaixo:</p>

        <div style="background-color: #e2e8f0; border-radius: 4px; padding: 15px; text-align: center; margin: 20px 0;">
            <code style="font-size: 14px; word-break: break-all;">{{ $token }}</code>
        </div>

        <p style="color: #718096; font-size: 14px;">
            Este convite expira em <strong>{{ $expiresAt }}</strong>.
        </p>
    </div>

    <p style="color: #a0aec0; font-size: 12px; text-align: center;">
        Se você não solicitou este convite, por favor ignore este email.
    </p>
</body>
</html>
