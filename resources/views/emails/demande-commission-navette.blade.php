<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de paiement - Gains navettes</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0;
        }
        .info-section {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2563eb;
        }
        .info-section h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 16px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px;
            font-size: 14px;
        }
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        .info-value {
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background-color: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }
        .total-row {
            background-color: #fef3c7;
            font-weight: 600;
        }
        .total-row td {
            border-top: 2px solid #f59e0b;
            border-bottom: none;
        }
        .montant {
            font-weight: 600;
            color: #059669;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-en-attente {
            background-color: #fef3c7;
            color: #d97706;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
            font-weight: 500;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .alert {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .alert p {
            margin: 0;
            color: #991b1b;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            th, td {
                padding: 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Demande de paiement - Gains navettes</h1>
            <p>Nouvelle demande de commission de la part d'un gestionnaire</p>
        </div>

        <!-- Section informations gestionnaire -->
        <div class="info-section">
            <h3>📋 Informations du gestionnaire</h3>
            <div class="info-grid">
                <span class="info-label">Nom complet :</span>
                <span class="info-value">{{ $gestionnaire->user->prenom }} {{ $gestionnaire->user->nom }}</span>

                <span class="info-label">Email :</span>
                <span class="info-value">{{ $gestionnaire->user->email }}</span>

                <span class="info-label">Wilaya :</span>
                <span class="info-value">{{ $gestionnaire->wilaya_id }} - {{ $gestionnaire->wilaya_nom ?? 'Non définie' }}</span>

                <span class="info-label">Date de la demande :</span>
                <span class="info-value">{{ now()->format('d/m/Y à H:i') }}</span>
            </div>
        </div>

        @if($isMultiple)
            <div class="alert">
                <p>⚠️ Cette demande concerne <strong>{{ $gains->count() }} gains navette</strong> pour un montant total de <strong class="montant">{{ number_format($montantTotal, 0, ',', ' ') }} DA</strong></p>
            </div>
        @endif

        <!-- Tableau des gains -->
        <h3 style="margin-bottom: 10px; color: #1f2937;">📊 Détail des gains navettes</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Navette</th>
                    <th>Livraison</th>
                    <th>Wilaya</th>
                    <th>Montant</th>
                    <th>%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gains as $gain)
                <tr>
                    <td>{{ $gain->created_at ? $gain->created_at->format('d/m/Y') : 'N/A' }}</td>
                    <td>
                        @if($gain->navette)
                            #{{ substr($gain->navette->id, 0, 8) }}
                        @else
                            #{{ substr($gain->navette_id, 0, 8) }}
                        @endif
                    </td>
                    <td>#{{ substr($gain->livraison_id, 0, 8) }}</td>
                    <td>
                        <span style="background-color: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                            {{ $gain->wilaya_type === 'depart' ? 'Départ' : 'Arrivée' }}
                        </span>
                    </td>
                    <td class="montant">{{ number_format($gain->montant_commission, 0, ',', ' ') }} DA</td>
                    <td>{{ $gain->pourcentage_applique }}%</td>
                </tr>
                @endforeach
                @if($isMultiple)
                <tr class="total-row">
                    <td colspan="4" style="text-align: right; font-weight: 600;">Total :</td>
                    <td colspan="2" class="montant">{{ number_format($montantTotal, 0, ',', ' ') }} DA</td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Résumé des statuts -->
        <div class="info-section" style="margin-top: 20px;">
            <h3>📈 Résumé des gains</h3>
            <div class="info-grid">
                <span class="info-label">Nombre de gains :</span>
                <span class="info-value">{{ $gains->count() }}</span>

                <span class="info-label">Montant total :</span>
                <span class="info-value montant">{{ number_format($montantTotal, 0, ',', ' ') }} DA</span>

                <span class="info-label">Statut actuel :</span>
                <span class="info-value">
                    <span class="status-badge status-en-attente">Demande envoyée</span>
                </span>
            </div>
        </div>

        <!-- Actions pour l'admin -->
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/admin/comptabilite?tab=impayes" class="button">
                📋 Voir dans l'administration
            </a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Cet email a été généré automatiquement par le système de gestion des commissions.</p>
            <p>Merci de traiter cette demande dans les plus brefs délais.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }} - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
