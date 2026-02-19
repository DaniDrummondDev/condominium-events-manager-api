<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação de Cadastro</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 30px; margin-bottom: 20px;">
        <h1 style="color: #2d3748; margin-top: 0;">Confirme seu cadastro</h1>

        <p>Olá, <strong>{{ $adminName }}</strong>!</p>

        <p>Recebemos sua solicitação de cadastro para o <strong>{{ $condominiumName }}</strong>.</p>

        <p>Para confirmar seu cadastro e iniciar o provisionamento do seu condomínio, clique no botão abaixo:</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}"
               style="display: inline-block; background-color: #3182ce; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: bold;">
                Confirmar Cadastro
            </a>
        </div>

        <p style="color: #718096; font-size: 14px;">
            Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
        </p>
        <div style="background-color: #e2e8f0; border-radius: 4px; padding: 15px; text-align: center; margin: 10px 0;">
            <code style="font-size: 12px; word-break: break-all;">{{ $verificationUrl }}</code>
        </div>

        <p style="color: #718096; font-size: 14px;">
            Este link expira em <strong>24 horas</strong> ({{ $expiresAt }}).
        </p>
    </div>

    <p style="color: #a0aec0; font-size: 12px; text-align: center;">
        Se você não solicitou este cadastro, por favor ignore este email.
    </p>
</body>
</html>
