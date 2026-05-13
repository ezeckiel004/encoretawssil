<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour de votre commission</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9fafb; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { background-color: #e0f2fe; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mise à jour de votre commission</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $gain->gestionnaire->user->prenom }} {{ $gain->gestionnaire->user->nom }}</strong>,</p>

            @if($statut == 'paye')
                <div class="info" style="background-color: #d1fae5;">
                    <h2 class="success">✓ Paiement effectué</h2>
                    <p>Votre commission pour la livraison <strong>#{{ substr($gain->livraison_id, 0, 8) }}</strong> a été payée.</p>
                    <p><strong>Montant :</strong> {{ number_format($montant, 0, ',', ' ') }} DA</p>
                </div>
            @elseif($statut == 'annule')
                <div class="info" style="background-color: #fee2e2;">
                    <h2 class="error">✗ Commission annulée</h2>
                    <p>Votre commission pour la livraison <strong>#{{ substr($gain->livraison_id, 0, 8) }}</strong> a été annulée.</p>
                    @if($note)
                        <p><strong>Motif :</strong> {{ $note }}</p>
                    @endif
                </div>
            @endif

            <p>Connectez-vous à votre espace gestionnaire pour voir le détail de vos gains.</p>
            <p style="text-align: center; margin-top: 30px;">
                <a href="{{ url('/manager/gains') }}" style="background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;">Voir mes gains</a>
            </p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement par l'application Tawssil.</p>
        </div>
    </div>
</body>
</html>
