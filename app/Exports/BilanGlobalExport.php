<?php
// app/Exports/BilanGlobalExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BilanGlobalExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return [
            ['Période', $this->data['periode']['libelle']],
            ['Date de génération', $this->data['date_generation']],
            [],
            ['STATISTIQUES DES COLIS'],
            ['Total colis', $this->data['colis']['total']],
            ['Valeur totale', number_format($this->data['colis']['valeur_totale'], 0, ',', ' ') . ' DA'],
            ['Valeur moyenne', number_format($this->data['colis']['valeur_moyenne'], 0, ',', ' ') . ' DA'],
            ['Poids total', $this->data['colis']['poids_total'] . ' kg'],
            [],
            ['STATISTIQUES DES LIVRAISONS'],
            ['Total livraisons', $this->data['livraisons']['total']],
            ['Terminées', $this->data['livraisons']['terminees']],
            ['En cours', $this->data['livraisons']['en_cours']],
            ['Annulées', $this->data['livraisons']['annulees']],
            ['Revenus livraisons', number_format($this->data['livraisons']['prix_total'], 0, ',', ' ') . ' DA'],
            [],
            ['STATISTIQUES DES NAVETTES'],
            ['Total navettes', $this->data['navettes']['total']],
            ['Terminées', $this->data['navettes']['terminees']],
            ['Revenus navettes', number_format($this->data['navettes']['revenus'], 0, ',', ' ') . ' DA'],
            ['Colis transportés', $this->data['navettes']['colis_transportes']],
            ['Distance totale', $this->data['navettes']['distance_totale'] . ' km'],
            [],
            ['BILAN FINANCIER'],
            ['Valeur des colis', number_format($this->data['finances']['valeur_colis'], 0, ',', ' ') . ' DA'],
            ['Revenus livraisons', number_format($this->data['finances']['revenus_livraisons'], 0, ',', ' ') . ' DA'],
            ['Revenus navettes', number_format($this->data['finances']['revenus_navettes'], 0, ',', ' ') . ' DA'],
            ['CHIFFRE D\'AFFAIRES TOTAL', number_format($this->data['finances']['chiffre_affaires_total'], 0, ',', ' ') . ' DA'],
        ];
    }

    public function headings(): array
    {
        return [
            ['BILAN FINANCIER GLOBAL'],
        ];
    }

    public function title(): string
    {
        return 'Bilan Global';
    }

    public function styles(Worksheet $sheet)
    {
        // Style pour le titre
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style pour les en-têtes de sections
        $sheet->getStyle('A4')->getFont()->setBold(true);
        $sheet->getStyle('A9')->getFont()->setBold(true);
        $sheet->getStyle('A16')->getFont()->setBold(true);
        $sheet->getStyle('A23')->getFont()->setBold(true);

        // Style pour la ligne totale
        $sheet->getStyle('A27:B27')->getFont()->setBold(true);
        $sheet->getStyle('A27:B27')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F0FE');

        return [];
    }
}
