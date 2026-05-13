<?php

namespace App\Mail;

use App\Models\DemandeLivraison;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DeliveryRequestReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $demande;
    public $destinataire;

    public function __construct(DemandeLivraison $demande)
    {
        $this->demande = $demande;
        $this->destinataire = $demande->destinataire;
        Log::info('📧 [MAIL] Constructeur - Demande #' . $demande->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de réception de votre demande de livraison - #' . $this->demande->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.delivery_request_received',
            with: [
                'demande' => $this->demande,
                'destinataire' => $this->destinataire,
            ],
        );
    }

    public function attachments(): array
    {
        try {
            set_time_limit(120);

            Log::info('📎 [MAIL] Début attachments() pour la demande #' . $this->demande->id);

            $livraison = $this->demande->livraison;

            if (!$livraison) {
                Log::warning('⚠️ [MAIL] Aucune livraison trouvée pour la demande #' . $this->demande->id);
                return [];
            }

            Log::info('📦 [MAIL] Livraison #' . $livraison->id . ' trouvée');

            $livraison->load([
                'demandeLivraison.client.user',
                'demandeLivraison.destinataire.user',
                'demandeLivraison.colis',
                'livreurRamasseur.user',
                'livreurDistributeur.user',
            ]);

            Log::info('🖨️ [MAIL] Préparation des données pour le PDF...');
            $data = $this->preparePrintData($livraison);

            // Vérifier que le logo est bien généré
            Log::info('🖼️ [MAIL] Logo base64 généré, longueur: ' . strlen($data['logoBase64']) . ' caractères');
            Log::info('🖼️ [MAIL] Aperçu logo: ' . substr($data['logoBase64'], 0, 50) . '...');

            Log::info('📄 [MAIL] Génération du PDF avec vue: pdf.bordereau-mail');

            $pdf = Pdf::loadView('pdf.bordereau-mail', $data);

            // Configuration DOM PDF améliorée pour les images
            $pdf->setPaper([0, 0, 283.464, 425.197], 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => false,
                'dpi' => 96,
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'enable_remote' => true,
                'chroot' => public_path(),
                'logOutputFile' => storage_path('logs/dompdf.log'),
                'debugPng' => true,
                'debugKeepTemp' => true,
                'debugCss' => false,
                'debugLayout' => false,
            ]);

            $filename = 'bordereau-' . $livraison->id . '.pdf';

            Log::info('✅ [MAIL] PDF généré avec succès, taille: ' . strlen($pdf->output()) . ' octets');

            return [
                Attachment::fromData(fn() => $pdf->output(), $filename)
                    ->withMime('application/pdf'),
            ];

        } catch (\Exception $e) {
            Log::error('❌ [MAIL] ERREUR: ' . $e->getMessage());
            Log::error('❌ [MAIL] Fichier: ' . $e->getFile() . ' Ligne: ' . $e->getLine());
            return [];
        }
    }

    private function preparePrintData($livraison): array
    {
        try {
            Log::info('🔄 [MAIL] preparePrintData - Début');

            $livraison->load([
                'demandeLivraison.client.user',
                'demandeLivraison.destinataire.user',
                'demandeLivraison.colis',
                'livreurRamasseur.user',
                'livreurDistributeur.user',
            ]);

            $demande = $livraison->demandeLivraison;
            $colis = $demande->colis ?? null;
            $client = $demande->client->user ?? null;

            // Récupérer le destinataire
            $destinataire = null;
            if ($demande->destinataire && $demande->destinataire->user) {
                $destinataire = $demande->destinataire->user;
            } else {
                $destinataire = new \stdClass();
                $destinataire->prenom = $demande->destinataire_prenom ?? '';
                $destinataire->nom = $demande->destinataire_nom ?? '';
                $destinataire->telephone = $demande->destinataire_telephone ?? '';
            }

            $livreurRamasseur = $livraison->livreurRamasseur->user ?? null;
            $livreurDistributeur = $livraison->livreurDistributeur->user ?? null;

            // Nettoyage des textes
            $clean = fn($t) => $this->cleanText($t);
            if ($client) {
                $client->prenom = $clean($client->prenom ?? '');
                $client->nom = $clean($client->nom ?? '');
                $client->telephone = $clean($client->telephone ?? '');
            }
            if ($destinataire) {
                $destinataire->prenom = $clean($destinataire->prenom ?? '');
                $destinataire->nom = $clean($destinataire->nom ?? '');
                $destinataire->telephone = $clean($destinataire->telephone ?? '');
            }
            if ($demande) {
                $demande->addresse_depot = $clean($demande->addresse_depot ?? '');
                $demande->addresse_delivery = $clean($demande->addresse_delivery ?? '');
                $demande->wilaya = $clean($demande->wilaya ?? '');
                $demande->commune = $clean($demande->commune ?? '');
            }

            // LOGO en base64 - Version améliorée avec logs
            Log::info('🖼️ [MAIL] Recherche du logo...');
            $logoPath = public_path('Tawsillogo.png');
            Log::info('🖼️ [MAIL] Chemin du logo: ' . $logoPath);

            $logoBase64 = '';
            if (file_exists($logoPath)) {
                Log::info('🖼️ [MAIL] Fichier logo trouvé');
                $logoData = file_get_contents($logoPath);
                if ($logoData !== false) {
                    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                    Log::info('🖼️ [MAIL] Logo converti en base64, taille: ' . strlen($logoData) . ' octets');
                } else {
                    Log::error('🖼️ [MAIL] Impossible de lire le fichier logo');
                }
            } else {
                Log::warning('🖼️ [MAIL] Fichier logo non trouvé, utilisation du fallback');
                // Fallback avec un SVG plus robuste
                $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 72 72"><rect width="72" height="72" fill="#3b82f6"/><text x="36" y="36" text-anchor="middle" dy=".3em" fill="white" font-size="16" font-family="Arial">TAWSSIL</text></svg>');
            }

            // QR code et code-barres
            $qrCode = $this->generateSimpleQRCode($livraison);
            $barcodeValue = $colis->colis_label ?? 'COLIS-' . $livraison->id;
            $barcode = $this->generateSimpleBarcode($barcodeValue);

            // Wilaya
            $wilayaNumber = '';
            $wilayaName = '';

            $wilayaMap = [
                'Adrar' => '01', 'Chlef' => '02', 'Laghouat' => '03', 'Oum El Bouaghi' => '04',
                'Batna' => '05', 'Béjaïa' => '06', 'Biskra' => '07', 'Béchar' => '08',
                'Blida' => '09', 'Bouira' => '10', 'Tamanrasset' => '11', 'Tébessa' => '12',
                'Tlemcen' => '13', 'Tiaret' => '14', 'Tizi Ouzou' => '15', 'Alger' => '16',
                'Djelfa' => '17', 'Jijel' => '18', 'Sétif' => '19', 'Saïda' => '20',
                'Skikda' => '21', 'Sidi Bel Abbès' => '22', 'Annaba' => '23', 'Guelma' => '24',
                'Constantine' => '25', 'Médéa' => '26', 'Mostaganem' => '27', 'M\'sila' => '28',
                'Mascara' => '29', 'Ouargla' => '30', 'Oran' => '31', 'El Bayadh' => '32',
                'Illizi' => '33', 'Bordj Bou Arreridj' => '34', 'Boumerdès' => '35',
                'El Tarf' => '36', 'Tindouf' => '37', 'Tissemsilt' => '38', 'El Oued' => '39',
                'Khenchela' => '40', 'Souk Ahras' => '41', 'Tipaza' => '42', 'Mila' => '43',
                'Aïn Defla' => '44', 'Naâma' => '45', 'Aïn Témouchent' => '46', 'Ghardaïa' => '47',
                'Relizane' => '48', 'Timimoun' => '49', 'Bordj Badji Mokhtar' => '50',
                'Ouled Djellal' => '51', 'Béni Abbès' => '52', 'In Salah' => '53',
                'In Guezzam' => '54', 'Touggourt' => '55', 'Djanet' => '56', 'El M\'ghair' => '57',
                'El Menia' => '58'
            ];

            $wilayaValue = trim($demande->wilaya ?? '');
            if (!empty($wilayaValue)) {
                if (is_numeric($wilayaValue)) {
                    $wilayaNumber = str_pad($wilayaValue, 2, '0', STR_PAD_LEFT);
                    foreach ($wilayaMap as $nom => $num) {
                        if ($num === $wilayaNumber) {
                            $wilayaName = $nom;
                            break;
                        }
                    }
                } else {
                    foreach ($wilayaMap as $nom => $num) {
                        if (stripos($wilayaValue, $nom) !== false) {
                            $wilayaName = $nom;
                            $wilayaNumber = $num;
                            break;
                        }
                    }
                }
            }

            $printDate = $livraison->created_at
                ? Carbon::parse($livraison->created_at)->locale('fr_FR')->isoFormat('DD/MM/YYYY')
                : now()->locale('fr_FR')->isoFormat('DD/MM/YYYY');

            $statusLabels = [
                'en_attente' => 'En attente',
                'prise_en_charge_ramassage' => 'Prise en charge',
                'ramasse' => 'Ramasse',
                'en_transit' => 'En transit',
                'prise_en_charge_livraison' => 'En livraison',
                'livre' => 'Livré',
                'annule' => 'Annulé',
            ];
            $statusLabel = $statusLabels[$livraison->status] ?? $livraison->status;

            Log::info('✅ [MAIL] preparePrintData - Terminé avec succès');

            return [
                'livraison'          => $livraison,
                'demande'            => $demande,
                'colis'              => $colis,
                'client'             => $client,
                'destinataire'       => $destinataire,
                'livreurRamasseur'   => $livreurRamasseur,
                'livreurDistributeur' => $livreurDistributeur,
                'qrCode'             => $qrCode,
                'barcode'            => $barcode,
                'colisLabel'         => $barcodeValue,
                'printDate'          => $printDate,
                'statusLabel'        => $statusLabel,
                'wilayaNumber'       => $wilayaNumber,
                'wilayaName'         => $wilayaName,
                'logoBase64'         => $logoBase64,
            ];
        } catch (\Exception $e) {
            Log::error('❌ [MAIL] Erreur preparePrintData: ' . $e->getMessage());
            throw $e;
        }
    }

    private function cleanText($text)
    {
        if (empty($text)) return '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(
            ['&comma;', '&#44;', '&amp;', '&quot;', '&lt;', '&gt;', '&nbsp;'],
            [',', ',', '&', '"', '<', '>', ' '],
            $text
        );
        return trim($text);
    }

    private function generateSimpleQRCode($livraison): string
    {
        try {
            $data = urlencode("ID:{$livraison->id}|PIN:{$livraison->code_pin}");
            return "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={$data}&format=png&margin=0";
        } catch (\Exception $e) {
            return "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=livraison&format=png";
        }
    }

    private function generateSimpleBarcode($value): string
    {
        try {
            $encoded = urlencode($value);
            return "https://barcode.tec-it.com/barcode.ashx?data={$encoded}&code=Code128&dpi=96&dataseparator=";
        } catch (\Exception $e) {
            return "https://barcode.tec-it.com/barcode.ashx?data=123456&code=Code128";
        }
    }
}
