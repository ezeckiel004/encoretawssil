{{-- resources/views/pdf/bilan-gestionnaire.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Bilan - {{ $data['gestionnaire']['wilaya_nom'] ?? $data['wilaya_nom'] ?? 'Gestionnaire' }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
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
            margin: 0;
        }

        .header h2 {
            color: #2E75B5;
            font-size: 14px;
            margin: 5px 0 0 0;
        }

        .info {
            background-color: #f0f0f0;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        h3 {
            color: #2E75B5;
            font-size: 13px;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ccc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            padding: 8px;
            text-align: left;
        }

        td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }

        .total-row {
            background-color: #E8F0FE;
            font-weight: bold;
        }

        .montant {
            text-align: right;
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
    </style>
</head>

<body>
    @php
        // Déterminer les valeurs en fonction de la structure des données
        $wilayaNom = $data['gestionnaire']['wilaya_nom'] ?? $data['wilaya_nom'] ?? 'Wilaya inconnue';
        $wilayaId = $data['gestionnaire']['wilaya_id'] ?? $data['wilaya_id'] ?? '?';
        $gestionnaireNom = '';

        if (isset($data['gestionnaire']['prenom']) || isset($data['gestionnaire']['nom'])) {
            $gestionnaireNom = trim(($data['gestionnaire']['prenom'] ?? '') . ' ' . ($data['gestionnaire']['nom'] ?? ''));
        }

        $periodeLibelle = $data['periode']['libelle'] ?? 'Période non définie';
        $dateGeneration = $data['date_generation'] ?? now()->format('d/m/Y H:i:s');

        // Données des colis
        $colisTotal = $data['colis']['total'] ?? 0;
        $colisValeurTotale = $data['colis']['valeur_totale'] ?? 0;
        $colisPoidsTotal = $data['colis']['poids_total'] ?? 0;

        // Données des livraisons
        $livraisonsTotal = $data['livraisons']['total'] ?? 0;
        $livraisonsTerminees = $data['livraisons']['terminees'] ?? 0;
        $livraisonsPrixTotal = $data['livraisons']['prix_total'] ?? 0;

        // Données des navettes
        $navettesTotal = $data['navettes']['total'] ?? 0;
        $navettesRevenus = $data['navettes']['revenus'] ?? 0;

        // Données financières
        $financesValeurColis = $data['finances']['valeur_colis'] ?? 0;
        $financesRevenusLivraisons = $data['finances']['revenus_livraisons'] ?? 0;
        $financesRevenusNavettes = $data['finances']['revenus_navettes'] ?? 0;
        $financesTotal = $data['finances']['chiffre_affaires_total'] ?? 0;
    @endphp

    <div class="header">
        <h1>BILAN FINANCIER</h1>
        <h2>{{ $wilayaNom }} ({{ $wilayaId }})</h2>
        @if(!empty($gestionnaireNom))
            <p style="margin:5px 0 0 0; font-size:12px; color:#666;">Gestionnaire: {{ $gestionnaireNom }}</p>
        @endif
    </div>

    <div class="info">
        <p><strong>Période :</strong> {{ $periodeLibelle }}</p>
        <p><strong>Généré le :</strong> {{ $dateGeneration }}</p>
    </div>

    <h3>COLIS</h3>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Total colis</td>
            <td class="montant">{{ number_format($colisTotal, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Valeur totale</td>
            <td class="montant">{{ number_format($colisValeurTotale, 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Poids total</td>
            <td class="montant">{{ number_format($colisPoidsTotal, 2, ',', ' ') }} kg</td>
        </tr>
    </table>

    <h3>LIVRAISONS</h3>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Total livraisons</td>
            <td class="montant">{{ number_format($livraisonsTotal, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Terminées</td>
            <td class="montant">{{ number_format($livraisonsTerminees, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>En cours</td>
            <td class="montant">{{ number_format($data['livraisons']['en_cours'] ?? 0, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Annulées</td>
            <td class="montant">{{ number_format($data['livraisons']['annulees'] ?? 0, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Revenus livraisons</td>
            <td class="montant">{{ number_format($livraisonsPrixTotal, 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Taux de succès</td>
            <td class="montant">{{ number_format($data['livraisons']['taux_succes'] ?? 0, 1, ',', ' ') }}%</td>
        </tr>
    </table>

    <h3>NAVETTES</h3>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Total navettes</td>
            <td class="montant">{{ number_format($navettesTotal, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Terminées</td>
            <td class="montant">{{ number_format($data['navettes']['terminees'] ?? 0, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>En cours</td>
            <td class="montant">{{ number_format($data['navettes']['en_cours'] ?? 0, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Colis transportés</td>
            <td class="montant">{{ number_format($data['navettes']['colis_transportes'] ?? 0, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Revenus navettes</td>
            <td class="montant">{{ number_format($navettesRevenus, 0, ',', ' ') }} DA</td>
        </tr>
    </table>

    <h3>BILAN FINANCIER</h3>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Valeur des colis</td>
            <td class="montant">{{ number_format($financesValeurColis, 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Revenus livraisons</td>
            <td class="montant">{{ number_format($financesRevenusLivraisons, 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Revenus navettes</td>
            <td class="montant">{{ number_format($financesRevenusNavettes, 0, ',', ' ') }} DA</td>
        </tr>
        <tr class="total-row">
            <td>CHIFFRE D'AFFAIRES TOTAL</td>
            <td class="montant">{{ number_format($financesTotal, 0, ',', ' ') }} DA</td>
        </tr>
    </table>

    @if(isset($data['finances']['repartition']))
    <h3>RÉPARTITION DES REVENUS</h3>
    <table>
        <tr>
            <th>Source</th>
            <th class="montant">Pourcentage</th>
        </tr>
        <tr>
            <td>Colis</td>
            <td class="montant">{{ number_format($data['finances']['repartition']['colis']['pourcentage'] ?? 0, 1, ',', ' ') }}%</td>
        </tr>
        <tr>
            <td>Livraisons</td>
            <td class="montant">{{ number_format($data['finances']['repartition']['livraisons']['pourcentage'] ?? 0, 1, ',', ' ') }}%</td>
        </tr>
        <tr>
            <td>Navettes</td>
            <td class="montant">{{ number_format($data['finances']['repartition']['navettes']['pourcentage'] ?? 0, 1, ',', ' ') }}%</td>
        </tr>
    </table>
    @endif

    @if(isset($data['statuts_livraisons']))
    <h3>RÉPARTITION PAR STATUT</h3>
    <table>
        <tr>
            <th>Statut</th>
            <th class="montant">Nombre</th>
        </tr>
        @foreach($data['statuts_livraisons'] as $statut => $count)
        <tr>
            <td>{{ ucfirst(str_replace('_', ' ', $statut)) }}</td>
            <td class="montant">{{ number_format($count, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="footer">
        Document généré le {{ $dateGeneration }}
    </div>
</body>

</html>
