<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle mission assignée</title>
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
        .badge {
            display: inline-block;
            background-color: #f44d0b;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        .alert {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Nouvelle Mission Assignée</h1>
        </div>

        <div class="content">
            <p>Bonjour {{ $livreur->prenom }} {{ $livreur->nom }},</p>
            <p>Une nouvelle mission vous a été assignée sur la plateforme Tawssil.</p>

            <div class="alert">
                ✓ Vous avez été assigné en tant que <strong>{{ $typeLabel }}</strong> pour cette livraison.
            </div>

            <div class="section">
                <h2>📋 Détails de la Livraison</h2>
                <div class="info-row">
                    <div class="info-label">Numéro de livraison:</div>
                    <div class="info-value"><strong>{{ $livraison->id }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Code PIN:</div>
                    <div class="info-value"><strong>{{ $livraison->code_pin }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Statut actuel:</div>
                    <div class="info-value">
                        @php
                            $statusLabels = [
                                'en_attente' => 'En attente',
                                'prise_en_charge_ramassage' => 'Prise en charge',
                                'ramasse' => 'Ramassé',
                                'en_transit' => 'En transit',
                                'prise_en_charge_livraison' => 'En livraison',
                                'livre' => 'Livré',
                                'annule' => 'Annulé',
                            ];
                            $status = $statusLabels[$livraison->status] ?? str_replace('_', ' ', $livraison->status);
                        @endphp
                        {{ $status }}
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>👥 Informations Client</h2>
                <div class="info-row">
                    <div class="info-label">Expéditeur:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->client->user->prenom ?? 'N/A' }} {{ $livraison->demandeLivraison->client->user->nom ?? '' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Destinataire:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->destinataire->user->prenom ?? 'N/A' }} {{ $livraison->demandeLivraison->destinataire->user->nom ?? '' }}</div>
                </div>
            </div>

            <div class="section">
                <h2>📦 Adresses</h2>
                <div class="info-row">
                    <div class="info-label">Adresse de départ:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->addresse_depot }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Lieu de livraison:</div>
                    <div class="info-value">
                        {{ $livraison->demandeLivraison->addresse_delivery ?? $livraison->demandeLivraison->commune ?? 'Non spécifiée' }}
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Wilaya:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->wilaya }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Commune:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->commune }}</div>
                </div>
            </div>

            <div class="section">
                <h2>📦 Détails du Colis</h2>
                <div class="info-row">
                    <div class="info-label">Label du colis:</div>
                    <div class="info-value"><strong>{{ $livraison->demandeLivraison->colis->colis_label ?? 'N/A' }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->colis->colis_type ?? 'Non spécifié' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Poids:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->colis->poids ?? 0 }} kg</div>
                </div>
            </div>

            <div class="section">
                <h2>💰 Tarification</h2>
                <div class="info-row">
                    <div class="info-label">Prix livraison:</div>
                    <div class="info-value"><strong>{{ $livraison->demandeLivraison->prix }} DA</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Prix colis:</div>
                    <div class="info-value">{{ $livraison->demandeLivraison->colis->colis_prix ?? 0 }} DA</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <p style="color: #666; font-size: 14px;">
                    <strong>Actions requises:</strong><br>
                    @if($type === 1)
                        Veuillez procéder au ramassage du colis dès que possible.
                    @else
                        Veuillez livrer le colis au destinataire dès que possible.
                    @endif
                </p>
            </div>
        </div>

        <div class="footer">
            <p>Cet email a été généré automatiquement par le système Tawssil.</p>
            <p>© {{ date('Y') }} Tawssil - Tous droits réservés.</p>
            <p>Si vous avez besoin d'assistance, contactez l'équipe support.</p>
        </div>
    </div>
</body>
</html>
