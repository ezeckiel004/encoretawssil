{{-- resources/views/emails/cash-delivery-notification.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Notification COD</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #2563eb;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
        }
        .content {
            padding: 20px 0;
        }
        .info {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .montant {
            font-size: 24px;
            font-weight: bold;
            color: #059669;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }
        .status-en_attente { background-color: #fef3c7; color: #d97706; }
        .status-accepte { background-color: #d1fae5; color: #059669; }
        .status-refuse { background-color: #fee2e2; color: #dc2626; }
        .status-annule { background-color: #e5e7eb; color: #6b7280; }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #6b7280;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Cash On Delivery (COD)</h1>
        </div>

        <div class="content">
            @if($type === 'nouvelle_demande')
                <h2>Nouvelle demande de transfert</h2>
                <p>Bonjour <strong>{{ $cashDelivery->destinataire->user->prenom }} {{ $cashDelivery->destinataire->user->nom }}</strong>,</p>
                <p><strong>{{ $cashDelivery->expediteur->user->prenom }} {{ $cashDelivery->expediteur->user->nom }}</strong>
                (Wilaya {{ $cashDelivery->expediteur->wilaya_nom }}) vous a envoyé une demande de transfert.</p>

                <div class="info">
                    <p><strong>Montant :</strong> <span class="montant">{{ number_format($cashDelivery->montant, 0, ',', ' ') }} DA</span></p>
                    <p><strong>Référence :</strong> {{ $cashDelivery->reference }}</p>
                    @if($cashDelivery->motif)
                        <p><strong>Motif :</strong> {{ $cashDelivery->motif }}</p>
                    @endif
                    <p><strong>Date d'envoi :</strong> {{ $cashDelivery->date_envoi->format('d/m/Y à H:i') }}</p>
                </div>

                <p>Veuillez vous connecter à votre espace gestionnaire pour accepter ou refuser cette demande.</p>

                <div style="text-align: center;">
                    <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/cash-delivery" class="button">
                        Voir la demande
                    </a>
                </div>

            @elseif($type === 'demande_acceptee')
                <h2>✅ Demande acceptée</h2>
                <p>Bonjour <strong>{{ $cashDelivery->expediteur->user->prenom }} {{ $cashDelivery->expediteur->user->nom }}</strong>,</p>
                <p>Votre demande de transfert a été <strong style="color: #059669;">acceptée</strong> par
                <strong>{{ $cashDelivery->destinataire->user->prenom }} {{ $cashDelivery->destinataire->user->nom }}</strong>
                (Wilaya {{ $cashDelivery->destinataire->wilaya_nom }}).</p>

                <div class="info">
                    <p><strong>Montant :</strong> <span class="montant">{{ number_format($cashDelivery->montant, 0, ',', ' ') }} DA</span></p>
                    <p><strong>Référence :</strong> {{ $cashDelivery->reference }}</p>
                    <p><strong>Date d'acceptation :</strong> {{ $cashDelivery->date_reponse->format('d/m/Y à H:i') }}</p>
                </div>

                <p>La transaction est maintenant terminée.</p>

            @elseif($type === 'demande_refusee')
                <h2>❌ Demande refusée</h2>
                <p>Bonjour <strong>{{ $cashDelivery->expediteur->user->prenom }} {{ $cashDelivery->expediteur->user->nom }}</strong>,</p>
                <p>Votre demande de transfert a été <strong style="color: #dc2626;">refusée</strong> par
                <strong>{{ $cashDelivery->destinataire->user->prenom }} {{ $cashDelivery->destinataire->user->nom }}</strong>
                (Wilaya {{ $cashDelivery->destinataire->wilaya_nom }}).</p>

                <div class="info">
                    <p><strong>Montant :</strong> {{ number_format($cashDelivery->montant, 0, ',', ' ') }} DA</p>
                    <p><strong>Référence :</strong> {{ $cashDelivery->reference }}</p>
                </div>

            @elseif($type === 'demande_annulee')
                <h2>🚫 Demande annulée</h2>
                <p>Bonjour <strong>{{ $cashDelivery->destinataire->user->prenom }} {{ $cashDelivery->destinataire->user->nom }}</strong>,</p>
                <p>La demande de transfert de
                <strong>{{ $cashDelivery->expediteur->user->prenom }} {{ $cashDelivery->expediteur->user->nom }}</strong>
                a été <strong style="color: #6b7280;">annulée</strong>.</p>

                <div class="info">
                    <p><strong>Montant :</strong> {{ number_format($cashDelivery->montant, 0, ',', ' ') }} DA</p>
                    <p><strong>Référence :</strong> {{ $cashDelivery->reference }}</p>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>Cet email est automatique, merci de ne pas y répondre.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }} - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
