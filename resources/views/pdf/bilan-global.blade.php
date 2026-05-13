{{-- resources/views/pdf/bilan-global.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Bilan financier global</title>
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
            margin: 0 0 5px 0;
        }

        .info {
            background-color: #f0f0f0;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        h2 {
            color: #2E75B5;
            font-size: 14px;
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
    <div class="header">
        <h1>BILAN FINANCIER GLOBAL</h1>
    </div>

    <div class="info">
        <p><strong>Période :</strong> {{ $data['periode']['libelle'] }}</p>
        <p><strong>Généré le :</strong> {{ $data['date_generation'] }}</p>
    </div>

    <h2>COLIS</h2>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Total colis</td>
            <td class="montant">{{ number_format($data['colis']['total'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Valeur totale</td>
            <td class="montant">{{ number_format($data['colis']['valeur_totale'], 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Valeur moyenne</td>
            <td class="montant">{{ number_format($data['colis']['valeur_moyenne'], 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Poids total</td>
            <td class="montant">{{ number_format($data['colis']['poids_total'], 2, ',', ' ') }} kg</td>
        </tr>
    </table>

    <h2>LIVRAISONS</h2>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Total livraisons</td>
            <td class="montant">{{ number_format($data['livraisons']['total'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Terminées</td>
            <td class="montant">{{ number_format($data['livraisons']['terminees'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>En cours</td>
            <td class="montant">{{ number_format($data['livraisons']['en_cours'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Annulées</td>
            <td class="montant">{{ number_format($data['livraisons']['annulees'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Revenus livraisons</td>
            <td class="montant">{{ number_format($data['livraisons']['prix_total'], 0, ',', ' ') }} DA</td>
        </tr>
    </table>

    <h2>NAVETTES</h2>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Total navettes</td>
            <td class="montant">{{ number_format($data['navettes']['total'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Terminées</td>
            <td class="montant">{{ number_format($data['navettes']['terminees'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Revenus navettes</td>
            <td class="montant">{{ number_format($data['navettes']['revenus'], 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Colis transportés</td>
            <td class="montant">{{ number_format($data['navettes']['colis_transportes'], 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Distance totale</td>
            <td class="montant">{{ number_format($data['navettes']['distance_totale'], 2, ',', ' ') }} km</td>
        </tr>
    </table>

    <h2>BILAN FINANCIER</h2>
    <table>
        <tr>
            <th>Indicateur</th>
            <th class="montant">Valeur</th>
        </tr>
        <tr>
            <td>Valeur des colis</td>
            <td class="montant">{{ number_format($data['finances']['valeur_colis'], 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Revenus livraisons</td>
            <td class="montant">{{ number_format($data['finances']['revenus_livraisons'], 0, ',', ' ') }} DA</td>
        </tr>
        <tr>
            <td>Revenus navettes</td>
            <td class="montant">{{ number_format($data['finances']['revenus_navettes'], 0, ',', ' ') }} DA</td>
        </tr>
        <tr class="total-row">
            <td>CHIFFRE D'AFFAIRES TOTAL</td>
            <td class="montant">{{ number_format($data['finances']['chiffre_affaires_total'], 0, ',', ' ') }} DA</td>
        </tr>
    </table>

    <div class="footer">
        Document généré le {{ $data['date_generation'] }}
    </div>
</body>

</html>
