<?php
// app/Exports/GainsExport.php

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

class GainsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $gains;
    protected $debut;
    protected $fin;
    protected $periodeLibelle;
    protected $totaux;

    public function __construct($gains, $debut, $fin, $periodeLibelle, $totaux)
    {
        $this->gains = $gains;
        $this->debut = $debut;
        $this->fin = $fin;
        $this->periodeLibelle = $periodeLibelle;
        $this->totaux = $totaux;
    }

    public function collection()
    {
        return $this->gains;
    }

    public function headings(): array
    {
        return [
            ['RAPPORT DES GAINS'],
            ['Période: ' . $this->periodeLibelle],
            ['Généré le: ' . Carbon::now()->format('d/m/Y H:i')],
            [], // Ligne vide
            [
                'Date',
                'Livreur',
                'Livraison ID',
                'Colis',
                'Montant Brut',
                'Frais Navette',
                'Frais Hub',
                'Point Relais',
                'Partenaire 1',
                'Partenaire 2',
                'Société Mère',
                'Net Livreur',
                'Statut'
            ]
        ];
    }

    public function map($gain): array
    {
        return [
            Carbon::parse($gain->date)->format('d/m/Y'),
            $gain->livreur?->user?->nom . ' ' . $gain->livreur?->user?->prenom ?? 'N/A',
            $gain->livraison_id,
            $gain->livraison?->demandeLivraison?->colis?->colis_label ?? 'N/A',
            number_format($gain->montant_brut, 2, ',', ' ') . ' DA',
            number_format($gain->frais_navette, 2, ',', ' ') . ' DA',
            number_format($gain->frais_hub, 2, ',', ' ') . ' DA',
            number_format($gain->frais_point_relais, 2, ',', ' ') . ' DA',
            number_format($gain->commission_partenaire1, 2, ',', ' ') . ' DA',
            number_format($gain->commission_partenaire2, 2, ',', ' ') . ' DA',
            number_format($gain->montant_societe_mere, 2, ',', ' ') . ' DA',
            number_format($gain->montant_net_livreur, 2, ',', ' ') . ' DA',
            $gain->statut_paiement === 'paye' ? 'Payé' : ($gain->statut_paiement === 'en_attente' ? 'En attente' : 'Annulé')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style pour le titre principal
        $sheet->mergeCells('A1:M1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '1F4E79']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Style pour la période
        $sheet->mergeCells('A2:M2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '2E75B5']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Style pour la date de génération
        $sheet->mergeCells('A3:M3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 10,
                'color' => ['rgb' => '7F7F7F']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Style pour l'en-tête du tableau
        $sheet->getStyle('A5:M5')->applyFromArray([
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
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Ajouter les totaux en bas
        $lastRow = $sheet->getHighestRow() + 2;
        $sheet->setCellValue('A' . $lastRow, 'TOTAUX');
        $sheet->mergeCells('A' . $lastRow . ':D' . $lastRow);

        $sheet->setCellValue('E' . $lastRow, number_format($this->totaux['brut'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('F' . $lastRow, number_format($this->totaux['navette'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('G' . $lastRow, number_format($this->totaux['hub'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('H' . $lastRow, number_format($this->totaux['point_relais'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('I' . $lastRow, number_format($this->totaux['partenaire1'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('J' . $lastRow, number_format($this->totaux['partenaire2'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('K' . $lastRow, number_format($this->totaux['societe'], 2, ',', ' ') . ' DA');
        $sheet->setCellValue('L' . $lastRow, number_format($this->totaux['livreurs'], 2, ',', ' ') . ' DA');

        $sheet->getStyle('A' . $lastRow . ':M' . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2']
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
        return 'Rapport des gains';
    }
}
