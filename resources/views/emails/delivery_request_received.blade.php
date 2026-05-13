<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de votre demande de livraison</title>
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

        .status-badge {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin-top: 15px;
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

        .success-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .timeline {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #f44d0b;
        }

        .timeline-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .timeline-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .timeline-step {
            font-weight: bold;
            color: #246475;
            margin-bottom: 5px;
        }

        .timeline-desc {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>✅ Demande Reçue et En Traitement</h1>
        </div>

        <div class="content">
            <p>Bonjour {{ $destinataire->user->nom ?? 'Utilisateur' }},</p>

            <div class="success-box">
                <strong>✓ Votre demande de livraison a été reçue et mise en traitement</strong><br>
                Merci de faire confiance à Tawssil. Nous traitons votre demande au plus vite.
            </div>

            <div class="section">
                <h2>📋 Numéro de Suivi</h2>
                <div style="text-align: center; padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 10px;">Conservez ce numéro pour vos
                        contacts</div>
                    <div style="font-size: 28px; font-weight: bold; color: #246475; font-family: monospace;">
                        #{{ $demande->id }}
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>📍 Détails de la Livraison</h2>
                <div class="info-row">
                    <div class="info-label">Point de départ:</div>
                    <div class="info-value">{{ $demande->addresse_depot }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Destination:</div>
                    <div class="info-value">{{ $demande->addresse_delivery ?? $demande->commune }}</div>
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
                <h2>📦 Informations du Colis</h2>
                <div class="info-row">
                    <div class="info-label">Type:</div>
                    <div class="info-value">{{ $demande->colis->colis_type ?? 'Non spécifié' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Poids:</div>
                    <div class="info-value">{{ $demande->colis->poids ?? 0 }} kg</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Label colis:</div>
                    <div class="info-value">{{ $demande->colis->colis_label ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="section">
                <h2>💰 Tarification</h2>
                <div class="info-row">
                    <div class="info-label">Frais de livraison:</div>
                    <div class="info-value"><strong>{{ $demande->prix }} DA</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Valeur du colis:</div>
                    <div class="info-value">{{ $demande->colis->colis_prix ?? 0 }} DA</div>
                </div>
            </div>

            <div class="section">
                <h2>⏱️ Statut du Traitement</h2>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-step">✅ Demande Reçue</div>
                        <div class="timeline-desc">{{ $demande->created_at->format('d/m/Y à H:i') }}</div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-step">⏳ En Traitement</div>
                        <div class="timeline-desc">L'équipe Tawssil examine et traite votre demande</div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-step">🚚 Attribution du Livreur</div>
                        <div class="timeline-desc">Un livreur sera assigné à votre demande. Vous recevrez un email de
                            confirmation.</div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-step">📍 Ramassage & Livraison</div>
                        <div class="timeline-desc">Le livreur récupèrera votre colis et le livrera à la destination
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>📞 Besoin d'Aide?</h2>
                <p>Si vous avez des questions concernant votre livraison, vous pouvez:</p>
                <ul>
                    <li>Consulter votre demande en ligne en utilisant le numéro de suivi <strong>#{{ $demande->id
                            }}</strong></li>
                    <li>Contacter notre équipe support</li>
                    <li>Suivre l'évolution de votre demande en temps réel</li>
                </ul>
            </div>

            <!-- 📄 Lien de téléchargement du bordereau PDF -->
            <p style="text-align: center; margin-top: 30px;">
                <a href="{{ url('api/livraisons/' . $demande->livraison->id . '/bordereau-pdf') }}" class="cta-button"
                    target="_blank">
                    📄 Télécharger votre bordereau PDF
                </a>
            </p>
        </div>

        <div class="footer">
            <p><strong>Merci d'avoir choisi Tawssil!</strong></p>
            <p>Cet email a été généré automatiquement. Veuillez ne pas répondre à cet email.</p>
            <p>© {{ date('Y') }} Tawssil - Tous droits réservés.</p>
        </div>
    </div>
</body>

</html>
