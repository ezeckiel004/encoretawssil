<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle demande de livraison</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #246475 0%, #f44d0b 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            color: #246475;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 2px solid #f44d0b;
            padding-bottom: 10px;
            margin: 0 0 15px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-label {
            font-weight: bold;
            color: #333;
            width: 40%;
        }
        .info-value {
            color: #666;
            width: 60%;
            text-align: right;
        }
        .cta-button {
            display: inline-block;
            background-color: #f44d0b;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
        }
        .footer {
            background-color: #f9f9f9;
            color: #666;
            text-align: center;
            padding: 20px;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
        }
        .alert {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Nouvelle Demande de Livraison</h1>
        </div>

        <div class="content">
            <p>Bonjour,</p>
            <p>Une nouvelle demande de livraison a été créée sur la plateforme Tawssil.</p>

            <div class="section">
                <h2>📋 Détails de la Demande</h2>
                <div class="info-row">
                    <div class="info-label">Numéro de demande:</div>
                    <div class="info-value"><strong>{{ $demande->id }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date création:</div>
                    <div class="info-value">{{ $demande->created_at->format('d/m/Y H:i') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Statut:</div>
                    <div class="info-value"><strong>{{ $demande->status ?? 'En attente' }}</strong></div>
                </div>
            </div>

            <div class="section">
                <h2>👤 Informations Client</h2>
                <div class="info-row">
                    <div class="info-label">Client:</div>
                    <div class="info-value">{{ $client->user->nom ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value">{{ $client->user->email ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Téléphone:</div>
                    <div class="info-value">{{ $client->user->telephone ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="section">
                <h2>🏠 Adresses</h2>
                <div class="info-row">
                    <div class="info-label">Adresse de départ:</div>
                    <div class="info-value">{{ $demande->addresse_depot }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Lieu de livraison:</div>
                    <div class="info-value">{{ $demande->addresse_delivery ?? $demande->commune ?? 'Non spécifiée' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Wilaya:</div>
                    <div class="info-value">{{ $demande->wilaya }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Commune:</div>
                    <div class="info-value">{{ $demande->commune }}</div>
                </div>
            </div>

            <div class="section">
                <h2>📦 Détails du Colis</h2>
                <div class="info-row">
                    <div class="info-label">Type:</div>
                    <div class="info-value">{{ $demande->colis->colis_type ?? 'Non spécifié' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Poids:</div>
                    <div class="info-value">{{ $demande->colis->poids ?? 0 }} kg</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Label:</div>
                    <div class="info-value">{{ $demande->colis->colis_label ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="section">
                <h2>💰 Tarification</h2>
                <div class="info-row">
                    <div class="info-label">Prix livraison:</div>
                    <div class="info-value"><strong>{{ $demande->prix }} DA</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Prix colis:</div>
                    <div class="info-value">{{ $demande->colis->colis_prix ?? 0 }} DA</div>
                </div>
            </div>

            <div class="alert">
                ⚠️ <strong>Action requise:</strong> Veuillez traiter cette demande dès que possible.
            </div>

            <p style="text-align: center; margin-top: 30px;">
                <a href="#" class="cta-button">Afficher la demande complète</a>
            </p>
        </div>

        <div class="footer">
            <p>Cet email a été généré automatiquement par le système Tawssil.</p>
            <p>© {{ date('Y') }} Tawssil - Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
