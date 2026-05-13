{{-- resources/views/pdf/navettes.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Liste des navettes</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4472C4;
        }

        .header h1 {
            color: #1F4E79;
            font-size: 18px;
            margin: 0 0 5px 0;
        }

        .header p {
            color: #666;
            font-size: 11px;
            margin: 0;
        }

        .filters {
            background-color: #f0f0f0;
            padding: 8px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid #1F4E79;
            font-size: 9px;
        }

        td {
            padding: 6px 4px;
            border: 1px solid #ccc;
            text-align: center;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status-planifiee {
            background-color: #FFF3CD;
            color: #856404;
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .status-en_cours {
            background-color: #D4EDDA;
            color: #155724;
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .status-terminee {
            background-color: #D1ECF1;
            color: #0C5460;
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .status-annulee {
            background-color: #F8D7DA;
            color: #721C24;
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }

        .page-number:before {
            content: "Page " counter(page);
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>LISTE DES NAVETTES</h1>
        <p>Généré le {{ $date_generation }}</p>
    </div>

    @if(!empty($filters))
    <div class="filters">
        <strong>Filtres appliqués:</strong>
        @foreach($filters as $key => $value)
        @if($value && !in_array($key, ['page', 'per_page']))
        <span style="margin-right: 10px;">{{ ucfirst($key) }}: {{ $value }}</span>
        @endif
        @endforeach
        @if(empty(array_filter($filters)))
        <span>Toutes les navettes</span>
        @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Réf.</th>
                <th>Départ</th>
                <th>Arrivée</th>
                <th>Transit</th>
                <th>Date départ</th>
                <th>Heure</th>
                <th>Statut</th>
                <th>Chauffeur</th>
                <th>Colis</th>
                <th>Capacité</th>
                <th>Taux</th>
                <th>Distance</th>
                <th>Prix base</th>
                <th>Prix/colis</th>
            </tr>
        </thead>
        <tbody>
            @forelse($navettes as $navette)
            <tr>
                <td>{{ $navette->reference }}</td>
                <td>{{ $navette->wilayaDepart?->nom ?? $navette->wilaya_depart_id }}</td>
                <td>{{ $navette->wilayaArrivee?->nom ?? $navette->wilaya_arrivee_id }}</td>
                <td>{{ $navette->wilayaTransit?->nom ?? ($navette->wilaya_transit_id ?? '-') }}</td>
                <td>{{ $navette->date_depart ? $navette->date_depart->format('d/m/Y') : '-' }}</td>
                <td>{{ $navette->heure_depart }}</td>
                <td>
                    @switch($navette->status)
                    @case('planifiee')
                    <span class="status-planifiee">Planifiée</span>
                    @break
                    @case('en_cours')
                    <span class="status-en_cours">En cours</span>
                    @break
                    @case('terminee')
                    <span class="status-terminee">Terminée</span>
                    @break
                    @case('annulee')
                    <span class="status-annulee">Annulée</span>
                    @break
                    @default
                    {{ $navette->status }}
                    @endswitch
                </td>
                <td>{{ $navette->chauffeur?->user?->nom . ' ' . $navette->chauffeur?->user?->prenom ?? 'Non assigné' }}
                </td>
                <td>{{ $navette->nb_colis }}</td>
                <td>{{ $navette->capacite_max }}</td>
                <td>{{ $navette->taux_remplissage }}%</td>
                <td>{{ number_format($navette->distance_km, 2, ',', ' ') }} km</td>
                <td>{{ number_format($navette->prix_base, 2, ',', ' ') }} DA</td>
                <td>{{ number_format($navette->prix_par_colis, 2, ',', ' ') }} DA</td>
            </tr>
            @empty
            <tr>
                <td colspan="14" style="text-align: center; padding: 20px;">
                    Aucune navette trouvée
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($navettes->count() > 0)
    <div style="margin-top: 20px;">
        <table style="width: 40%; float: right;">
            <tr style="background-color: #D9E1F2;">
                <th style="text-align: left;">Total navettes</th>
                <td style="text-align: right;">{{ $navettes->count() }}</td>
            </tr>
            <tr>
                <th style="text-align: left;">Total colis</th>
                <td style="text-align: right;">{{ $navettes->sum('nb_colis') }}</td>
            </tr>
            <tr style="background-color: #f9f9f9;">
                <th style="text-align: left;">Distance totale</th>
                <td style="text-align: right;">{{ number_format($navettes->sum('distance_km'), 2, ',', ' ') }} km</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="footer">
        <span class="page-number"></span> - Généré le {{ $date_generation }}
    </div>
</body>

</html>
