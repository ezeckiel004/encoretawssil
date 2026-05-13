<?php
// app/Exports/RapportGestionnairesExport.php

namespace App\Exports;

use App\Models\GestionnaireGain;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class RapportGestionnairesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $gains;
    protected $periode;
    protected $dateGeneration;

    public function __construct($gains, $periode)
    {
        $this->gains = $gains;
        $this->periode = $periode;
        $this->dateGeneration = Carbon::now();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->gains;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            ['RAPPORT DES GAINS DES GESTIONNAIRES'],
            ['Période : ' . $this->periode['libelle']],
            ['Généré le : ' . $this->dateGeneration->format('d/m/Y H:i:s')],
            [], // Ligne vide
            [
                'Date',
                'Gestionnaire',
                'Wilaya',
                'Type',
                'Livraison',
                'Montant Commission',
                'Pourcentage',
                'Statut',
                'Date Demande',
                'Date Paiement',
                'Note'
            ]
        ];
    }

    /**
     * @param mixed $gain
     * @return array
     */
    public function map($gain): array
    {
        $gestionnaire = $gain->gestionnaire;
        $user = $gestionnaire?->user;
        $nomGestionnaire = $user ? ($user->prenom . ' ' . $user->nom) : 'Inconnu';

        $statutLabels = [
            'en_attente' => 'En attente',
            'demande_envoyee' => 'Demande envoyée',
            'paye' => 'Payé',
            'annule' => 'Annulé'
        ];

        return [
            $gain->created_at ? $gain->created_at->format('d/m/Y') : '-',
            $nomGestionnaire,
            $gestionnaire->wilaya_id ?? '-',
            $gain->wilaya_type === 'depart' ? 'Départ' : 'Arrivée',
            substr($gain->livraison_id, 0, 8) . '...',
            number_format($gain->montant_commission, 2, ',', ' ') . ' DA',
            $gain->pourcentage_applique . '%',
            $statutLabels[$gain->status] ?? $gain->status,
            $gain->date_demande ? $gain->date_demande->format('d/m/Y H:i') : '-',
            $gain->date_paiement ? $gain->date_paiement->format('d/m/Y H:i') : '-',
            $gain->note_admin ?? '-'
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        // Style pour le titre
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style pour la période
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style pour la date de génération
        $sheet->mergeCells('A3:K3');
        $sheet->getStyle('A3')->getFont()->setItalic(true);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style pour les en-têtes de colonnes (ligne 5)
        $sheet->getStyle('A5:K5')->getFont()->setBold(true);
        $sheet->getStyle('A5:K5')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        $sheet->getStyle('A5:K5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Alignement pour les colonnes de montant
        $sheet->getStyle('F:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Bordures pour tout le tableau
        $sheet->getStyle('A5:K' . (5 + $this->gains->count()))
            ->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Rapport des gains';
    }
}
