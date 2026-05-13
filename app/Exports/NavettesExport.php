<?php
// app/Exports/NavettesExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class NavettesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $navettes;
    protected $filters;

    public function __construct($navettes, $filters)
    {
        $this->navettes = $navettes;
        $this->filters = $filters;
    }

    public function collection()
    {
        return $this->navettes;
    }

    public function headings(): array
    {
        return [
            ['LISTE DES NAVETTES'],
            ['Généré le: ' . Carbon::now()->format('d/m/Y H:i')],
            $this->getFiltersDescription(),
            [], // Ligne vide
            [
                'Référence',
                'Départ',
                'Arrivée',
                'Transit',
                'Date départ',
                'Heure départ',
                'Statut',
                'Chauffeur',
                'Nb Colis',
                'Capacité',
                'Taux rempl.',
                'Distance (km)',
                'Prix base',
                'Prix/colis'
            ]
        ];
    }

    protected function getFiltersDescription(): array
    {
        $description = [];
        if (!empty($this->filters['status'])) {
            $description[] = 'Statut: ' . $this->filters['status'];
        }
        if (!empty($this->filters['wilaya_depart'])) {
            $description[] = 'Départ: ' . $this->filters['wilaya_depart'];
        }
        if (!empty($this->filters['date_debut']) && !empty($this->filters['date_fin'])) {
            $description[] = 'Du ' . $this->filters['date_debut'] . ' au ' . $this->filters['date_fin'];
        }

        $descStr = empty($description) ? 'Toutes les navettes' : 'Filtres: ' . implode(' | ', $description);

        return [$descStr];
    }

    public function map($navette): array
    {
        return [
            $navette->reference,
            $navette->wilayaDepart?->nom ?? $navette->wilaya_depart_id,
            $navette->wilayaArrivee?->nom ?? $navette->wilaya_arrivee_id,
            $navette->wilayaTransit?->nom ?? ($navette->wilaya_transit_id ?? '-'),
            $navette->date_depart ? $navette->date_depart->format('d/m/Y') : '-',
            $navette->heure_depart,
            $this->getStatusLabel($navette->status),
            $navette->chauffeur?->user?->nom . ' ' . $navette->chauffeur?->user?->prenom ?? 'Non assigné',
            $navette->nb_colis,
            $navette->capacite_max,
            $navette->taux_remplissage . '%',
            number_format($navette->distance_km, 2, ',', ' '),
            number_format($navette->prix_base, 2, ',', ' ') . ' DA',
            number_format($navette->prix_par_colis, 2, ',', ' ') . ' DA'
        ];
    }

    protected function getStatusLabel($status)
    {
        $labels = [
            'planifiee' => 'Planifiée',
            'en_cours' => 'En cours',
            'terminee' => 'Terminée',
            'annulee' => 'Annulée'
        ];
        return $labels[$status] ?? $status;
    }

    public function styles(Worksheet $sheet)
    {
        // Style pour le titre
        $sheet->mergeCells('A1:N1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '1F4E79']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Style pour la date
        $sheet->mergeCells('A2:N2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 10,
                'color' => ['rgb' => '7F7F7F']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Style pour les filtres
        $sheet->mergeCells('A3:N3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '2E75B5']
            ]
        ]);

        // Style pour l'en-tête
        $sheet->getStyle('A5:N5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Navettes';
    }
}
