<?php

namespace App\Exports;

use App\Models\Livraison;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LivraisonsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $search;
    protected $status;
    protected $startDate;
    protected $endDate;

    public function __construct($search = '', $status = '', $startDate = '', $endDate = '')
    {
        $this->search = $search;
        $this->status = $status;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Optimisation mémoire pour les exports Excel
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

        // Sélectionner uniquement les colonnes nécessaires
        $query = Livraison::query()
            ->with([
                'client:id,user_id',
                'client.user:id,nom,prenom',
                'demandeLivraison:id,client_id,destinataire_id,colis_id,wilaya,prix,addresse_delivery',
                'demandeLivraison.colis:id,colis_label,poids,colis_prix',
                'demandeLivraison.destinataire:id,user_id',
                'demandeLivraison.destinataire.user:id,nom,prenom',
                'livreurRamasseur:id,user_id',
                'livreurRamasseur.user:id,nom,prenom',
                'livreurDistributeur:id,user_id',
                'livreurDistributeur.user:id,nom,prenom',
            ]);

        // Appliquer les filtres de recherche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code_pin', 'like', '%' . $this->search . '%')
                    ->orWhereHas('client.user', function ($q) {
                        $q->where('nom', 'like', '%' . $this->search . '%')
                            ->orWhere('prenom', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('demandeLivraison.colis', function ($q) {
                        $q->where('colis_label', 'like', '%' . $this->search . '%');
                    });
            });
        }

        // Filtrer par statut
        if ($this->status) {
            $query->where('status', $this->status);
        }

        // Filtrer par date de création
        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        // Limiter le nombre de résultats pour éviter la surcharge
        // Vous pouvez ajuster cette limite selon vos besoins
        return $query->orderBy('created_at', 'desc')
            ->limit(10000) // Limite à 10,000 lignes maximum
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Client',
            'Destinataire',
            'Label Colis',
            'Statut',
            'Date Création',
            'Date Ramassage',
            'Date Livraison',
            'Ramassé par',
            'Distribué par',
            'Wilaya Départ',
            'Wilaya Arrivé',
            'Poids (kg)',
            'Prix Colis',
            'Prix Livraison'
        ];
    }

    public function map($livraison): array
    {
        // Traduire les statuts
        $statusLabels = [
            'en_attente' => 'En attente',
            'prise_en_charge_ramassage' => 'Prise en charge ramassage',
            'ramasse' => 'Ramasse',
            'en_transit' => 'En transit',
            'prise_en_charge_livraison' => 'Prise en charge livraison',
            'livre' => 'Livré',
            'annule' => 'Annulé',
        ];

        // Récupérer les données avec des vérifications de null
        $demandeLivraison = $livraison->demandeLivraison ?? null;
        $colis = $demandeLivraison->colis ?? null;
        $client = $livraison->client->user ?? null;
        $destinataire = $demandeLivraison->destinataire->user ?? null;
        $livreurRamasseur = $livraison->livreurRamasseur->user ?? null;
        $livreurDistributeur = $livraison->livreurDistributeur->user ?? null;

        // Formater les dates
        $dateCreation = $livraison->created_at ? Carbon::parse($livraison->created_at)->format('d/m/Y H:i') : 'N/A';
        $dateRamassage = $livraison->date_ramassage ? Carbon::parse($livraison->date_ramassage)->format('d/m/Y H:i') : 'N/A';
        $dateLivraison = $livraison->date_livraison ? Carbon::parse($livraison->date_livraison)->format('d/m/Y H:i') : 'N/A';

        // Récupérer les wilayas
        $wilayaDepart = $demandeLivraison->wilaya ?? 'N/A';

        // Extraire wilaya arrivée
        $wilayaArrive = $this->extractWilayaFromAddress($demandeLivraison->addresse_delivery ?? '') ?: 'N/A';

        // Prix
        $prixColis = $colis->colis_prix ?? 0;
        $prixLivraison = $demandeLivraison->prix ?? 0;

        // Tronquer l'ID si trop long
        $livraisonId = strlen($livraison->id) > 20 ? substr($livraison->id, 0, 20) . '...' : $livraison->id;

        return [
            $livraisonId,
            $client ? ($client->prenom . ' ' . $client->nom) : 'N/A',
            $destinataire ? ($destinataire->prenom . ' ' . $destinataire->nom) : 'N/A',
            $colis->colis_label ?? 'N/A',
            $statusLabels[$livraison->status] ?? $livraison->status,
            $dateCreation,
            $dateRamassage,
            $dateLivraison,
            $livreurRamasseur ? ($livreurRamasseur->prenom . ' ' . $livreurRamasseur->nom) : 'Non attribué',
            $livreurDistributeur ? ($livreurDistributeur->prenom . ' ' . $livreurDistributeur->nom) : 'Non attribué',
            $wilayaDepart,
            $wilayaArrive,
            number_format($colis->poids ?? 0, 2, '.', ''),
            number_format($prixColis, 2, '.', ''),
            number_format($prixLivraison, 2, '.', '')
        ];
    }

    // Méthode pour extraire la wilaya de l'adresse
    private function extractWilayaFromAddress($address)
    {
        if (empty($address)) {
            return null;
        }

        // Liste des wilayas d'Algérie (abrégée pour l'exemple)
        $wilayas = [
            'Alger',
            'Oran',
            'Constantine',
            'Annaba',
            'Blida',
            'Batna',
            'Béjaïa',
            'Biskra',
            'Sétif',
            'Sidi Bel Abbès',
            'Tiaret',
            'Tlemcen',
            'Ghardaïa',
            'Mostaganem'
        ];

        $addressLower = mb_strtolower($address, 'UTF-8');

        foreach ($wilayas as $wilaya) {
            $wilayaLower = mb_strtolower($wilaya, 'UTF-8');
            if (strpos($addressLower, $wilayaLower) !== false) {
                return $wilaya;
            }
        }

        return null;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style pour l'en-tête
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            // Style pour toutes les cellules
            'A:O' => [
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            // Style pour les colonnes numériques
            'M:O' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ],
                'numberFormat' => [
                    'formatCode' => '#,##0.00'
                ]
            ],
        ];
    }
}
