{{-- resources/views/pdf/rapport-gestionnaires.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport des gains des gestionnaires</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4F46E5;
        }
        .header h1 {
            font-size: 18px;
            color: #4F46E5;
            margin: 0 0 5px 0;
        }
        .header p {
            margin: 2px 0;
            color: #666;
        }
        .stats {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .stats-grid {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 120px;
            margin: 5px;
            padding: 8px;
            background-color: white;
            border-radius: 4px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-label {
            font-size: 9px;
            color: #666;
            margin-bottom: 3px;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #4F46E5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #4F46E5;
            color: white;
            font-weight: bold;
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
        }
        td {
            padding: 5px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-en_attente {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-demande_envoyee {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-paye {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-annule {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 10px 0;
            border-top: 1px solid #e0e0e0;
        }
        .page-number:before {
            content: "Page " counter(page);
        }
        .montant {
            text-align: right;
            font-weight: bold;
        }
        .total-row {
            background-color: #e0e7ff !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RAPPORT DES GAINS DES GESTIONNAIRES</h1>
        <p>Période : {{ $data['periode']['libelle'] }}</p>
        <p>Généré le : {{ $data['date_generation'] }}</p>
    </div>

    <div class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Total gains</div>
                <div class="stat-value">{{ number_format($data['total_gains'], 0, ',', ' ') }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Montant total</div>
                <div class="stat-value">{{ number_format($data['total_montant'], 0, ',', ' ') }} DA</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Moyenne par gain</div>
                <div class="stat-value">
                    @if($data['total_gains'] > 0)
                        {{ number_format($data['total_montant'] / $data['total_gains'], 0, ',', ' ') }} DA
                    @else
                        0 DA
                    @endif
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Gestionnaire</th>
                <th>Wilaya</th>
                <th>Type</th>
                <th>Livraison</th>
                <th>Montant</th>
                <th>%</th>
                <th>Statut</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['gains'] as $gain)
                @php
                    $gestionnaire = $gain->gestionnaire;
                    $user = $gestionnaire?->user;
                    $nomGestionnaire = $user ? ($user->prenom . ' ' . $user->nom) : 'Inconnu';

                    $statutLabels = [
                        'en_attente' => 'En attente',
                        'demande_envoyee' => 'Demande envoyée',
                        'paye' => 'Payé',
                        'annule' => 'Annulé'
                    ];
                @endphp
                <tr>
                    <td>{{ $gain->created_at ? $gain->created_at->format('d/m/Y') : '-' }}</td>
                    <td>{{ $nomGestionnaire }}</td>
                    <td>{{ $gestionnaire->wilaya_id ?? '-' }}</td>
                    <td>{{ $gain->wilaya_type === 'depart' ? 'Départ' : 'Arrivée' }}</td>
                    <td>{{ substr($gain->livraison_id, 0, 8) }}...</td>
                    <td class="montant">{{ number_format($gain->montant_commission, 0, ',', ' ') }} DA</td>
                    <td>{{ $gain->pourcentage_applique }}%</td>
                    <td>
                        <span class="status-badge status-{{ $gain->status }}">
                            {{ $statutLabels[$gain->status] ?? $gain->status }}
                        </span>
                    </td>
                    <td>{{ $gain->note_admin ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 20px;">
                        Aucune donnée disponible pour cette période
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($data['gains']->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" style="text-align: right; font-weight: bold;">TOTAL :</td>
                    <td class="montant" style="font-weight: bold;">{{ number_format($data['total_montant'], 0, ',', ' ') }} DA</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        <span class="page-number"></span> - Tawssil - Rapport généré le {{ $data['date_generation'] }}
    </div>
</body>
</html>
