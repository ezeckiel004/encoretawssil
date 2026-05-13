<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Étiquette 10x15cm - Livraison #{{ $livraison->id }}</title>
    <style>
        @page {
            size: 100mm 150mm;
            margin: 0;
            padding: 0;
        }

        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            width: 100mm;
            height: 150mm;
            padding: 2.5mm;
            margin: 0;
            background: #fff;
            font-size: 10px;
            line-height: 1.25;
            overflow: hidden;
        }

        .etiquette {
            width: 95mm;
            height: 145mm;
            border: 1.5px solid #000;
            padding: 2.5mm;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* HEADER */
        .header-print {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5mm;
            flex-shrink: 0;
        }

        .logo-print {
            width: 16mm;
            height: 16mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-print img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .title-print {
            font-size: 18px;
            font-weight: 900;
            text-align: right;
            line-height: 1.1;
        }

        .title-print span:first-child {
            color: #000;
        }

        .title-print span:last-child {
            color: #3b82f6;
        }

        /* MAIN INFO */
        .main-info-print {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2.5mm;
            flex-shrink: 0;
        }

        .left-info-print {
            font-size: 9.5px;
            line-height: 1.25;
            width: 58%;
            word-wrap: break-word;
        }

        .left-info-print b {
            font-weight: 700;
            font-size: 10px;
        }

        .info-block-print {
            margin-bottom: 3px;
        }

        .contact-info-print {
            margin-left: 0;
            margin-bottom: 1px;
        }

        .right-info-print {
            text-align: center;
            width: 40%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .wilaya-print {
            font-size: 40px;
            font-weight: 900;
            line-height: 0.9;
            color: #1e40af;
            margin-bottom: 1px;
        }

        .city-print {
            font-size: 13px;
            font-weight: 900;
            color: #000;
            word-break: break-word;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .phone-print {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
            background-color: #e6f0ff;
            padding: 1.5mm 1mm;
            border-radius: 2mm;
            margin-top: 2px;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        /* BARCODE */
        .barcode-print {
            text-align: center;
            margin: 2mm 0;
            padding: 1.5mm 0;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            flex-shrink: 0;
        }

        .barcode-text-print {
            font-weight: 700;
            margin-bottom: 2px;
            font-size: 10px;
            word-break: break-all;
        }

        .barcode-img-print {
            width: 100%;
            height: 9mm;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .pin-code-print {
            font-size: 11px;
            font-weight: 900;
            color: #dc2626;
            margin-top: 2px;
        }

        /* TABLE */
        .table-print {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2mm;
            margin-bottom: auto;
            font-size: 9px;
            table-layout: fixed;
            flex-shrink: 0;
        }

        .table-print,
        .table-print th,
        .table-print td {
            border: 1px solid #000;
        }

        .table-print th,
        .table-print td {
            padding: 1.2mm;
            text-align: center;
            font-weight: 700;
            word-break: break-word;
            vertical-align: middle;
        }

        .table-print th {
            background: #f3f4f6;
            font-size: 9.5px;
        }

        .table-print td:first-child {
            text-align: left;
            width: 68%;
        }

        .table-print td:last-child {
            width: 32%;
        }

        .livraison-price-row {
            background-color: #f0f9ff;
        }

        .livraison-price-row td {
            color: #0369a1;
            text-align: left;
        }

        /* FOOTER */
        .footer-print {
            margin-top: auto;
            font-size: 7.5px;
            text-align: center;
            line-height: 1.2;
            padding-top: 1.5mm;
            border-top: 1px solid #ccc;
            word-wrap: break-word;
            flex-shrink: 0;
        }

        .date-ref-print {
            font-size: 7px;
            color: #666;
            margin-top: 1mm;
        }

        /* CLASSES UTILITAIRES */
        .break-word-print {
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .mt-print-1 {
            margin-top: 1mm;
        }

        .mt-print-2 {
            margin-top: 2mm;
        }

        .commune-print {
            font-size: 10px;
            margin-top: 1px;
        }

        .commune-label-print {
            font-weight: bold;
            color: #1e40af;
        }

        .commune-value-print {
            font-weight: normal;
        }

        /* Pour l'impression */
        @media print {
            body {
                padding: 0 !important;
                margin: 0 !important;
                width: 100mm !important;
                height: 150mm !important;
                background: white !important;
            }

            .etiquette {
                border: 1.5px solid #000;
                padding: 2.5mm;
                width: 100mm !important;
                height: 150mm !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            * {
                max-height: 150mm !important;
                overflow: hidden !important;
            }
        }
    </style>
</head>

<body>
    <div class="etiquette">
        <!-- HEADER -->
        <div class="header-print">
            <div class="logo-print">
                @php
                $logoPath = public_path('Tawsillogo.png');
                $logoBase64 = '';
                if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                } else {
                $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"
                    width="72" height="72" viewBox="0 0 72 72">
                    <rect width="72" height="72" fill="#3b82f6" /><text x="36" y="36" text-anchor="middle" dy=".3em"
                        fill="white" font-size="16" font-family="Arial">TG</text>
                </svg>');
                }
                @endphp
                <img src="{{ $logoBase64 }}" alt="Logo TAWSSIL GO" />
            </div>

            <div class="title-print">
                <span>TAWSSIL</span><br><span>GO</span>
            </div>
        </div>

        <!-- INFOS -->
        <div class="main-info-print">
            <div class="left-info-print break-word-print">
                <div class="info-block-print">
                    <b>Expéditeur :</b>
                    <div class="contact-info-print">{{ Str::limit($client->prenom ?? '', 10) }} {{
                        Str::limit($client->nom ?? '', 10) }}</div>
                    <div class="contact-info-print"><b>Tél:</b> {{ $client->telephone ?? 'N/A' }}</div>
                    <div class="contact-info-print"><b>Adr:</b> {{ Str::limit($demande->addresse_depot ?? 'Non
                        spécifiée', 25) }}</div>
                </div>

                <div class="info-block-print mt-print-2">
                    <b>Destinataire :</b>
                    <div class="contact-info-print">{{ Str::limit($destinataire->prenom ?? '', 10) }} {{
                        Str::limit($destinataire->nom ?? '', 10) }}</div>
                    <div class="contact-info-print"><b>Tél:</b> {{ $destinataire->telephone ?? 'N/A' }}</div>
                    <div class="contact-info-print"><b>Adr:</b> {{ Str::limit($demande->addresse_delivery ?? 'Non
                        spécifiée', 25) }}</div>
                    @if(!empty($demande->commune))
                    <div class="commune-print">
                        <span class="commune-label-print">Commune :</span>
                        <span class="commune-value-print">{{ Str::limit($demande->commune, 15) }}</span>
                    </div>
                    @endif
                </div>

                <div class="info-block-print mt-print-2">
                    <b>Date :</b> {{ $printDate }}
                </div>
                @if($livraison->date_ramassage)
                <div class="info-block-print">
                    <b>Ramassage :</b> {{ date('d-m', strtotime($livraison->date_ramassage)) }}
                </div>
                @endif
            </div>

            <div class="right-info-print">
                @php
                $wilayaText = $demande->wilaya ?? '';
                $wilayaNumber = '';
                $wilayaName = '';

                if (preg_match('/^(\d+)/', $wilayaText, $matches)) {
                $wilayaNumber = $matches[1];
                } else {
                $wilayaMap = [
                'Adrar' => '01', 'Chlef' => '02', 'Laghouat' => '03', 'Oum El Bouaghi' => '04',
                'Batna' => '05', 'Béjaïa' => '06', 'Biskra' => '07', 'Béchar' => '08',
                'Blida' => '09', 'Bouira' => '10', 'Tamanrasset' => '11', 'Tébessa' => '12',
                'Tlemcen' => '13', 'Tiaret' => '14', 'Tizi Ouzou' => '15', 'Alger' => '16',
                'Djelfa' => '17', 'Jijel' => '18', 'Sétif' => '19', 'Saïda' => '20',
                'Skikda' => '21', 'Sidi Bel Abbès' => '22', 'Annaba' => '23', 'Guelma' => '24',
                'Constantine' => '25', 'Médéa' => '26', 'Mostaganem' => '27', 'M\'Sila' => '28',
                'Mascara' => '29', 'Ouargla' => '30', 'Oran' => '31', 'El Bayadh' => '32',
                'Illizi' => '33', 'Bordj Bou Arréridj' => '34', 'Boumerdès' => '35', 'El Tarf' => '36',
                'Tindouf' => '37', 'Tissemsilt' => '38', 'El Oued' => '39', 'Khenchela' => '40',
                'Souk Ahras' => '41', 'Tipaza' => '42', 'Mila' => '43', 'Aïn Defla' => '44',
                'Naâma' => '45', 'Aïn Témouchent' => '46', 'Ghardaïa' => '47', 'Relizane' => '48',
                ];

                foreach ($wilayaMap as $nom => $code) {
                if (stripos($wilayaText, $nom) !== false) {
                $wilayaNumber = $code;
                $wilayaName = $nom;
                break;
                }
                }
                }

                if (empty($wilayaNumber)) {
                $wilayaNumber = substr($livraison->id, 0, 2);
                if (!is_numeric($wilayaNumber)) {
                $wilayaNumber = '16';
                }
                }

                if (empty($wilayaName)) {
                $wilayaName = $demande->wilaya ?? '';
                }

                $cityText = $demande->commune ?? '';
                @endphp

                <div class="wilaya-print">{{ $wilayaNumber }}</div>
                @if($cityText)
                <div class="city-print">{{ strtoupper(Str::limit($cityText, 10)) }}</div>
                @endif
                <div class="phone-print">{{ $destinataire->telephone ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- BARCODE -->
        <div class="barcode-print">
            <div class="barcode-text-print">{{ Str::limit($colisLabel, 18) }}</div>
            <img src="{{ $barcode }}" alt="Code-barres" class="barcode-img-print" />
            <div class="pin-code-print">PIN: {{ $livraison->code_pin }}</div>
        </div>

        <!-- TABLE -->
        <table class="table-print">
            <tr>
                <th>Description</th>
                <th>Prix</th>
            </tr>
            <tr>
                <td class="break-word-print">
                    {{ Str::limit($colis->colis_type ?? 'Colis', 12) }}
                    @if($colis->poids ?? false) ({{ $colis->poids }} kg) @endif
                </td>
                <td>{{ number_format($colis->colis_prix ?? 0, 0, ',', ' ') }} DA</td>
            </tr>
            @if($demande->prix ?? false)
            <tr class="livraison-price-row">
                <td colspan="2" style="font-size: 9px;">
                    <strong>Frais:</strong> {{ number_format($demande->prix, 0, ',', ' ') }} DA
                </td>
            </tr>
            @endif
            @if($demande->info_additionnel ?? false)
            <tr>
                <td colspan="2" style="font-size: 8.5px;">
                    <strong>Note:</strong> {{ Str::limit($demande->info_additionnel, 28) }}
                </td>
            </tr>
            @endif
        </table>

        <!-- FOOTER -->
        <div class="footer-print break-word-print">
            Déclare {{ Str::limit($client->prenom ?? '', 8) }} {{ Str::limit($client->nom ?? '', 8) }} infos correctes.
            <div class="date-ref-print">
                #{{ $livraison->id }} | {{ $statusLabel }}
            </div>
        </div>
    </div>
</body>

</html>
