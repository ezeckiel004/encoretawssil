<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Étiquette TAWSSIL GO - Livraison #{{ $livraison->id }}</title>

    <style>
        @page {
            size: 100mm 150mm;
            margin: 0mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100mm !important;
            height: 150mm !important;
            overflow: hidden !important;
            font-family: DejaVuSans, Arial, Helvetica, sans-serif !important;
            font-size: 10.5px;
            color: #000;
            background: #fff;
        }

        .label {
            width: 100mm;
            height: 148mm;
            padding: 3mm 3mm 2mm 3mm;
            border: 1px solid #000;
            background: white;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5mm;
            flex-shrink: 0;
        }

        .logo {
            width: 16mm;
            height: 16mm;
            flex-shrink: 0;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-align: left;
            line-height: 1.1;
            margin-left: 70mm; /* CORRIGÉ : marge gauche négative pour décaler vers la gauche */
            /* Ajustez cette valeur selon vos besoins (-10mm, -20mm, etc.) */
        }

        .title span:first-child {
            color: #000;
        }

        .title span:last-child {
            color: #3b82f6;
        }

        /* MAIN INFO */
        .main-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2.5mm;
            font-size: 10.5px;
            line-height: 1.3;
            flex-shrink: 0;
        }

        .left-info {
            width: 60%;
        }

        .left-info b {
            font-weight: bold;
            font-size: 11px;
        }

        .left-info>div {
            margin-bottom: 2px;
        }

        .expediteur-block,
        .destinataire-block {
            margin-bottom: 4px;
        }

        .right-info {
            width: 38%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .wilaya {
            font-size: 40px;
            font-weight: 900;
            line-height: 0.9;
            color: #1e40af;
            margin-bottom: 2px;
        }

        .city {
            font-size: 13px;
            font-weight: bold;
            margin-top: 1px;
            margin-bottom: 2px;
            text-transform: uppercase;
            word-break: break-word;
        }

        .phone-right {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
            background-color: #e6f0ff;
            padding: 1.5mm 1.5mm;
            border-radius: 2mm;
            margin-top: 2px;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        /* BARCODE */
        .barcode {
            text-align: center;
            margin: 2.5mm 0 2.5mm 0;
            padding: 2mm 0;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            flex-shrink: 0;
        }

        .barcode-text {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 2px;
            word-break: break-all;
        }

        .barcode img {
            width: 100%;
            height: 9mm;
            object-fit: contain;
        }

        .pin-code {
            font-size: 12px;
            font-weight: bold;
            color: #dc2626;
            margin-top: 2px;
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2mm;
            margin-bottom: auto;
            font-size: 10px;
            flex-shrink: 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 1.5mm 2mm;
            text-align: center;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
            font-size: 10.5px;
        }

        td:first-child {
            text-align: left;
        }

        .livraison-price-row td {
            text-align: left;
            background-color: #f0f9ff;
            color: #0369a1;
            font-weight: bold;
        }

        /* FOOTER */
        .footer {
            margin-top: auto;
            font-size: 8px;
            text-align: center;
            line-height: 1.2;
            color: #444;
            padding-top: 2mm;
            border-top: 1px solid #ccc;
            flex-shrink: 0;
        }

        .date-ref {
            font-size: 7.5px;
            color: #666;
            margin-top: 1mm;
        }

        img {
            max-width: 100% !important;
            height: auto !important;
        }

        .address-line {
            line-height: 1.2;
            word-break: break-word;
            font-size: 10px;
        }

        .client-name {
            font-weight: bold;
        }

        .commune-line {
            font-size: 10.5px;
            margin-top: 1px;
        }

        .commune-label {
            font-weight: bold;
            color: #1e40af;
        }

        .commune-value {
            font-weight: normal;
        }
    </style>
</head>

<body>
    <div class="label">
        <!-- HEADER -->
        <div class="header">
            <div class="logo">
                @php
                $logoPath = public_path('Tawsillogo.png');
                $logoBase64 = '';
                if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                } else {
                $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"
                    width="55" height="55" viewBox="0 0 55 55">
                    <rect width="55" height="55" fill="#3b82f6" /><text x="27.5" y="27.5" text-anchor="middle" dy=".3em"
                        fill="white" font-size="14" font-family="Arial">TG</text>
                </svg>');
                }
                @endphp
                <img src="{{ $logoBase64 }}" alt="Logo TAWSSIL GO" />
            </div>

            <div class="title">
                <span>TAWSSIL</span><br><span>GO</span>
            </div>
        </div>

        <!-- MAIN INFO -->
        <div class="main-info">
            <div class="left-info">
                <div class="expediteur-block">
                    <div><b>Expéditeur :</b> {{ $client->prenom ?? '' }} {{ $client->nom ?? '' }}</div>
                    <div><b>Tél :</b> {{ $client->telephone ?? 'N/A' }}</div>
                    <div class="address-line"><b>Adresse :</b> {{ Str::limit($demande->addresse_depot ?? 'Non
                        spécifiée', 30) }}</div>
                </div>

                <div class="destinataire-block">
                    <div><b>Destinataire :</b> {{ $destinataire->prenom ?? '' }} {{ $destinataire->nom ?? '' }}</div>
                    <div><b>Tél :</b> {{ $destinataire->telephone ?? 'N/A' }}</div>
                    <div class="address-line"><b>Adresse :</b> {{ Str::limit($demande->addresse_delivery ?? 'Non
                        spécifiée', 30) }}</div>
                    @if(!empty($demande->commune))
                    <div class="commune-line">
                        <span class="commune-label">Commune :</span>
                        <span class="commune-value">{{ Str::limit($demande->commune, 20) }}</span>
                    </div>
                    @endif
                </div>

                <div><b>Date :</b> {{ $printDate }}</div>
                @if($livraison->date_ramassage)
                <div><b>Ramassage :</b> {{ date('d-m', strtotime($livraison->date_ramassage)) }}</div>
                @endif
            </div>

            <div class="right-info">
                @if(!empty($wilayaNumber))
                <div class="wilaya">{{ $wilayaNumber }}</div>
                @endif
                @if(!empty($wilayaName))
                <div class="city">{{ Str::limit(ucwords(strtolower($wilayaName)), 12) }}</div>
                @endif
                {{-- <div class="phone-right">
                    {{ $destinataire->telephone ?? 'N/A' }}
                </div> --}}
            </div>
        </div>

        <!-- BARCODE -->
        <div class="barcode">
            <div class="barcode-text">{{ Str::limit($colisLabel, 20) }}</div>
            <img src="{{ $barcode }}" alt="Code-barres" />
            <div class="pin-code">PIN: {{ $livraison->code_pin }}</div>
        </div>

        <!-- TABLE -->
        <table>
            <tr>
                <th>Description</th>
                <th>Prix</th>
            </tr>
            <tr>
                <td>
                    {{ Str::limit($colis->colis_type ?? 'Colis', 15) }}
                    @if($colis->poids ?? false)
                    ({{ $colis->poids }} kg)
                    @endif
                </td>
                <td>{{ number_format($colis->colis_prix ?? 0, 0, ',', ' ') }} DA</td>
            </tr>
            @if($demande->prix ?? false)
            <tr class="livraison-price-row">
                <td colspan="2" style="text-align: left; font-size: 9.5px;">
                    <strong>Frais :</strong> {{ number_format($demande->prix, 0, ',', ' ') }} DA
                </td>
            </tr>
            @endif
            @if($demande->info_additionnel ?? false)
            <tr>
                <td colspan="2" style="text-align: left; font-size: 9px;">
                    <strong>Note :</strong> {{ Str::limit($demande->info_additionnel, 30) }}
                </td>
            </tr>
            @endif
        </table>

        <!-- FOOTER -->
        <div class="footer">
            Je déclare {{ $client->prenom ?? '' }} {{ $client->nom ?? '' }} que les détails déclarés sur ce bordereau sont corrects et que <br> le colis ne contient
            aucun produit dangereux ou interdit par la loi.

            <div class="date-ref">
                #{{ $livraison->id }} | {{ $statusLabel }}
            </div>
        </div>
    </div>
</body>

</html>
