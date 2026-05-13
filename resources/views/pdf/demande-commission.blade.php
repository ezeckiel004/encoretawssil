<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle demande de commission</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9fafb; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f3f4f6; }
        .montant { font-weight: bold; color: #10b981; }
        .total { font-size: 18px; font-weight: bold; color: #4F46E5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouvelle demande de commission</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            <p>Le gestionnaire <strong>{{ $gestionnaire->user->prenom }} {{ $gestionnaire->user->nom }}</strong> (Wilaya {{ $gestionnaire->wilaya_id }}) a effectué une demande de paiement pour ses commissions.</p>

            @if($multiple)
                <p><strong>Demande groupée de {{ $nbGains }} commissions</strong></p>
                <table>
                    <thead>
                        <tr>
                            <th>ID Livraison</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gains as $gain)
                        <tr>
                            <td>{{ substr($gain->livraison_id, 0, 8) }}...</td>
                            <td>{{ $gain->wilaya_type == 'depart' ? 'Départ' : 'Arrivée' }}</td>
                            <td class="montant">{{ number_format($gain->montant_commission, 0, ',', ' ') }} DA</td>
                            <td>{{ $gain->created_at->format('d/m/Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="total">Total : {{ number_format($montantTotal, 0, ',', ' ') }} DA</p>
            @else
                <p><strong>Détail de la commission :</strong></p>
                <ul>
                    <li>Livraison : #{{ substr($gains->first()->livraison_id, 0, 8) }}</li>
                    <li>Type : {{ $gains->first()->wilaya_type == 'depart' ? 'Départ' : 'Arrivée' }}</li>
                    <li>Montant : <span class="montant">{{ number_format($gains->first()->montant_commission, 0, ',', ' ') }} DA</span></li>
                    <li>Date : {{ $gains->first()->created_at->format('d/m/Y H:i') }}</li>
                </ul>
            @endif

            <p>Connectez-vous à votre espace admin pour traiter cette demande.</p>
            <p style="text-align: center; margin-top: 30px;">
                <a href="{{ url('/admin/traitement-commissions') }}" style="background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;">Traiter les demandes</a>
            </p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement par l'application Tawssil.</p>
        </div>
    </div>
</body>
</html>
