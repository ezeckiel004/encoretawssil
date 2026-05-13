<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Liste des Livraisons - {{ config('app.name') }}</title>
    <style>
        /* Styles pour le PDF */
        @page {
            margin: 20px;
            font-family: Arial, sans-serif;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.3;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 15px;
        }

        .header h1 {
            color: #4F46E5;
            font-size: 20px;
            margin: 0;
        }

        .header .subtitle {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }

        .header .date {
            color: #999;
            font-size: 10px;
            margin-top: 8px;
        }

        .filters-info {
            background-color: #f1f5f9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 9px;
        }

        .filters-info strong {
            color: #4F46E5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 8px;
            table-layout: fixed;
        }

        table thead {
            background-color: #4F46E5;
            color: white;
        }

        table th {
            padding: 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            word-wrap: break-word;
        }

        table td {
            padding: 5px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status {
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            display: inline-block;
        }

        .status-en_attente {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-prise_en_charge_ramassage {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-ramasse {
            background-color: #ede9fe;
            color: #5b21b6;
        }

        .status-en_transit {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status-prise_en_charge_livraison {
            background-color: #ffedd5;
            color: #9a3412;
        }

        .status-livre {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-annule {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #666;
        }

        .page-number:before {
            content: "Page " counter(page);
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
            font-size: 12px;
        }

        /* Largeur des colonnes */
        .col-id {
            width: 7%;
        }

        .col-client {
            width: 10%;
        }

        .col-dest {
            width: 10%;
        }

        .col-label {
            width: 8%;
        }

        .col-status {
            width: 8%;
        }

        .col-dates {
            width: 12%;
        }

        .col-livreurs {
            width: 12%;
        }

        .col-wilaya {
            width: 8%;
        }

        .col-poids {
            width: 6%;
        }

        .col-prix {
            width: 6%;
        }
    </style>
</head>

<body>
    <!-- En-tête -->
    <div class="header">
        <h1>Liste des Livraisons</h1>
        <div class="subtitle">Système de Gestion - {{ config('app.name') }}</div>
        <div class="date">Généré le : {{ date('d/m/Y à H:i') }}</div>
    </div>

    <!-- Informations sur les filtres -->
    @if($filters['search'] || $filters['status'] != 'Tous' || $filters['startDate'] || $filters['endDate'])
    <div class="filters-info">
        <strong>Filtres appliqués :</strong><br>
        @if($filters['search'])
        • Recherche : "{{ $filters['search'] }}"<br>
        @endif
        @if($filters['status'] != 'Tous')
        • Statut : {{ $filters['status'] }}<br>
        @endif
        @if($filters['startDate'])
        • Date début : {{ date('d/m/Y', strtotime($filters['startDate'])) }}<br>
        @endif
        @if($filters['endDate'])
        • Date fin : {{ date('d/m/Y', strtotime($filters['endDate'])) }}<br>
        @endif
        • Nombre de livraisons : {{ count($livraisons) }}
    </div>
    @endif

    <!-- Tableau des livraisons -->
    @if(count($livraisons) > 0)
    <table>
        <thead>
            <tr>
                <th class="col-id">ID</th>
                <th class="col-client">Client</th>
                <th class="col-dest">Destinataire</th>
                <th class="col-label">Label Colis</th>
                <th class="col-status">Statut</th>
                <th class="col-dates">Date Création</th>
                <th class="col-dates">Date Ramassage</th>
                <th class="col-dates">Date Livraison</th>
                <th class="col-livreurs">Ramassé par</th>
                <th class="col-livreurs">Distribué par</th>
                <th class="col-wilaya">Wilaya Départ</th>
                <th class="col-wilaya">Wilaya Arrivé</th>
                <th class="col-poids">Poids (kg)</th>
                <th class="col-prix">Prix Colis</th>
                <th class="col-prix">Prix Livraison</th>
            </tr>
        </thead>
        <tbody>
            @php
            // Fonction pour extraire la wilaya (déclarée UNE seule fois en dehors de la boucle)
            function extractWilayaFromAddress($address) {
            $wilayas = ['Alger', 'Oran', 'Constantine', 'Annaba', 'Blida', 'Batna'];
            foreach ($wilayas as $wilaya) {
            if (stripos($address, $wilaya) !== false) {
            return $wilaya;
            }
            }
            return substr($address, 0, 20) . (strlen($address) > 20 ? '...' : '');
            }
            @endphp

            @foreach($livraisons as $livraison)
            @php
            $demandeLivraison = $livraison->demandeLivraison ?? null;
            $colis = $demandeLivraison->colis ?? null;
            $client = $livraison->client->user ?? null;
            $destinataire = $demandeLivraison->destinataire->user ?? null;
            $livreurRamasseur = $livraison->livreurRamasseur->user ?? null;
            $livreurDistributeur = $livraison->livreurDistributeur->user ?? null;

            $statusClass = 'status-' . $livraison->status;
            $statusLabel = $statusLabels[$livraison->status] ?? $livraison->status;

            $wilayaArrive = extractWilayaFromAddress($demandeLivraison->addresse_delivery ?? '');
            @endphp
            <tr>
                <td>{{ substr($livraison->id, 0, 8) }}...</td>
                <td>
                    @if($client)
                    {{ $client->prenom }} {{ $client->nom }}
                    @else
                    N/A
                    @endif
                </td>
                <td>
                    @if($destinataire)
                    {{ $destinataire->prenom }} {{ $destinataire->nom }}
                    @else
                    N/A
                    @endif
                </td>
                <td>{{ $colis->colis_label ?? 'N/A' }}</td>
                <td><span class="status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                <td>{{ $livraison->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $livraison->date_ramassage ? $livraison->date_ramassage->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ $livraison->date_livraison ? $livraison->date_livraison->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>
                    @if($livreurRamasseur)
                    {{ $livreurRamasseur->prenom }} {{ $livreurRamasseur->nom }}
                    @else
                    Non attribué
                    @endif
                </td>
                <td>
                    @if($livreurDistributeur)
                    {{ $livreurDistributeur->prenom }} {{ $livreurDistributeur->nom }}
                    @else
                    Non attribué
                    @endif
                </td>
                <td>{{ $demandeLivraison->wilaya ?? 'N/A' }}</td>
                <td>{{ $wilayaArrive }}</td>
                <td style="text-align: right;">{{ number_format($colis->poids ?? 0, 2, ',', ' ') }}</td>
                <td style="text-align: right;">{{ number_format($colis->colis_prix ?? 0, 2, ',', ' ') }} DA</td>
                <td style="text-align: right;">{{ number_format($demandeLivraison->prix ?? 0, 2, ',', ' ') }} DA</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        Aucune livraison à afficher avec les filtres sélectionnés.
    </div>
    @endif

    <!-- Pied de page -->
    <div class="footer">
        Document généré par {{ config('app.name') }} •
        {{ date('d/m/Y à H:i') }} •
        <span class="page-number"></span>
    </div>
</body>

</html>
