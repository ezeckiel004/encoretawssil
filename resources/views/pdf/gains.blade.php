{{-- resources/views/pdf/gains.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Rapport des gains</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.4;
            margin: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #4472C4;
        }

        .header h1 {
            color: #1F4E79;
            font-size: 16px;
            margin: 0 0 3px 0;
        }

        .header p {
            color: #666;
            font-size: 10px;
            margin: 0;
        }

        .periode-box {
            background-color: #e8f0fe;
            padding: 8px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #4472C4;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8px;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            padding: 6px 2px;
            text-align: center;
            border: 1px solid #1F4E79;
        }

        td {
            padding: 4px 2px;
            border: 1px solid #ccc;
            text-align: right;
        }

        td:first-child,
        td:nth-child(2),
        td:nth-child(3),
        td:nth-child(4) {
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .total-row {
            background-color: #D9E1F2 !important;
            font-weight: bold;
        }

        .status-paye {
            background-color: #D4EDDA;
            color: #155724;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7px;
        }

        .status-attente {
            background-color: #FFF3CD;
            color: #856404;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7px;
        }

        .summary-box {
            border: 1px solid #4472C4;
            margin-top: 15px;
            border-radius: 4px;
        }

        .summary-title {
            background-color: #4472C4;
            color: white;
            padding: 6px;
            font-weight: bold;
            text-align: center;
        }

        .summary-content {
            padding: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 3px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>RAPPORT DES GAINS</h1>
        <p>Généré le {{ $date_generation }}</p>
    </div>

    <div class="periode-box">
        Période: {{ $periode }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Livreur</th>
                <th>Livraison</th>
                <th>Colis</th>
                <th>Brut</th>
                <th>Navette</th>
                <th>Hub</th>
                <th>Pt Relais</th>
                <th>Part. 1</th>
                <th>Part. 2</th>
                <th>Société</th>
                <th>Net Livreur</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gains as $gain)
            <tr>
                <td>{{ Carbon\Carbon::parse($gain->date)->format('d/m/Y') }}</td>
                <td>{{ $gain->livreur?->user?->nom . ' ' . $gain->livreur?->user?->prenom ?? 'N/A' }}</td>
                <td>{{ substr($gain->livraison_id, 0, 8) }}...</td>
                <td>{{ $gain->livraison?->demandeLivraison?->colis?->colis_label ?? 'N/A' }}</td>
                <td>{{ number_format($gain->montant_brut, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->frais_navette, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->frais_hub, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->frais_point_relais, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->commission_partenaire1, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->commission_partenaire2, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->montant_societe_mere, 2, ',', ' ') }}</td>
                <td>{{ number_format($gain->montant_net_livreur, 2, ',', ' ') }}</td>
                <td>
                    @if($gain->statut_paiement == 'paye')
                    <span class="status-paye">Payé</span>
                    @elseif($gain->statut_paiement == 'en_attente')
                    <span class="status-attente">En attente</span>
                    @else
                    {{ $gain->statut_paiement }}
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="13" style="text-align: center; padding: 20px;">
                    Aucun gain trouvé pour cette période
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($gains->count() > 0)
    <div style="margin-top: 20px;">
        <table style="width: 100%;">
            <tr class="total-row">
                <td colspan="4" style="text-align: right; font-size: 9px;"><strong>TOTAUX:</strong></td>
                <td><strong>{{ number_format($totaux['brut'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['navette'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['hub'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['point_relais'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['partenaire1'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['partenaire2'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['societe'], 2, ',', ' ') }}</strong></td>
                <td><strong>{{ number_format($totaux['livreurs'], 2, ',', ' ') }}</strong></td>
                <td></td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <div class="summary-title">RÉCAPITULATIF</div>
        <div class="summary-content">
            <div class="summary-row">
                <span>Nombre de livraisons:</span>
                <span><strong>{{ $gains->count() }}</strong></span>
            </div>
            <div class="summary-row">
                <span>Montant brut total:</span>
                <span><strong>{{ number_format($totaux['brut'], 2, ',', ' ') }} DA</strong></span>
            </div>
            <div class="summary-row">
                <span>Total frais navette:</span>
                <span>{{ number_format($totaux['navette'], 2, ',', ' ') }} DA</span>
            </div>
            <div class="summary-row">
                <span>Total frais hub:</span>
                <span>{{ number_format($totaux['hub'], 2, ',', ' ') }} DA</span>
            </div>
            <div class="summary-row">
                <span>Total frais point relais:</span>
                <span>{{ number_format($totaux['point_relais'], 2, ',', ' ') }} DA</span>
            </div>
            <div class="summary-row">
                <span>Total commissions partenaires:</span>
                <span>{{ number_format($totaux['partenaire1'] + $totaux['partenaire2'], 2, ',', ' ') }} DA</span>
            </div>
            <div class="summary-row"
                style="font-weight: bold; border-top: 2px solid #4472C4; margin-top: 5px; padding-top: 5px;">
                <span>Part société mère:</span>
                <span>{{ number_format($totaux['societe'], 2, ',', ' ') }} DA</span>
            </div>
            <div class="summary-row" style="font-weight: bold; color: #28a745;">
                <span>Gains nets livreurs:</span>
                <span>{{ number_format($totaux['livreurs'], 2, ',', ' ') }} DA</span>
            </div>
        </div>
    </div>
    @endif

    <div class="footer">
        <span class="page-number"></span> - Rapport généré le {{ $date_generation }}
    </div>
</body>

</html>
