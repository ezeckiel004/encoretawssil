<?php
// app/Exports/BilanGestionnaireExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BilanGestionnaireExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        // Vérifier si les données du gestionnaire existent dans 'gestionnaire' ou directement
        $wilayaNom = isset($this->data['gestionnaire']['wilaya_nom'])
            ? $this->data['gestionnaire']['wilaya_nom']
            : ($this->data['wilaya_nom'] ?? 'Inconnue');

        $wilayaId = isset($this->data['gestionnaire']['wilaya_id'])
            ? $this->data['gestionnaire']['wilaya_id']
            : ($this->data['wilaya_id'] ?? '?');

        $gestionnaireNom = isset($this->data['gestionnaire']['prenom'])
            ? $this->data['gestionnaire']['prenom'] . ' ' . ($this->data['gestionnaire']['nom'] ?? '')
            : '';

        $rows = [];

        // En-tête avec informations du gestionnaire
        if (!empty($gestionnaireNom)) {
            $rows[] = ['Gestionnaire', $gestionnaireNom];
        }
        $rows[] = ['Wilaya', $wilayaNom . ' (' . $wilayaId . ')'];
        $rows[] = ['Période', $this->data['periode']['libelle'] ?? 'Non définie'];
        $rows[] = ['Date de génération', $this->data['date_generation'] ?? now()->format('d/m/Y H:i:s')];
        $rows[] = [];

        // Statistiques des colis
        $rows[] = ['STATISTIQUES DES COLIS'];
        $rows[] = ['Total colis', $this->data['colis']['total'] ?? 0];
        $rows[] = ['Valeur totale', number_format($this->data['colis']['valeur_totale'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Valeur moyenne', number_format($this->data['colis']['valeur_moyenne'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Poids total', ($this->data['colis']['poids_total'] ?? 0) . ' kg'];
        $rows[] = [];

        // Statistiques des livraisons
        $rows[] = ['STATISTIQUES DES LIVRAISONS'];
        $rows[] = ['Total livraisons', $this->data['livraisons']['total'] ?? 0];
        $rows[] = ['Terminées', $this->data['livraisons']['terminees'] ?? 0];
        $rows[] = ['En cours', $this->data['livraisons']['en_cours'] ?? 0];
        $rows[] = ['En attente', $this->data['livraisons']['en_attente'] ?? 0];
        $rows[] = ['Annulées', $this->data['livraisons']['annulees'] ?? 0];
        $rows[] = ['Prix total', number_format($this->data['livraisons']['prix_total'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Prix moyen', number_format($this->data['livraisons']['prix_moyen'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Taux de succès', ($this->data['livraisons']['taux_succes'] ?? 0) . '%'];
        $rows[] = [];

        // Statistiques des navettes
        $rows[] = ['STATISTIQUES DES NAVETTES'];
        $rows[] = ['Total navettes', $this->data['navettes']['total'] ?? 0];
        $rows[] = ['Terminées', $this->data['navettes']['terminees'] ?? 0];
        $rows[] = ['En cours', $this->data['navettes']['en_cours'] ?? 0];
        $rows[] = ['Revenus navettes', number_format($this->data['navettes']['revenus'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Colis transportés', $this->data['navettes']['colis_transportes'] ?? 0];
        $rows[] = ['Distance totale', ($this->data['navettes']['distance_totale'] ?? 0) . ' km'];
        $rows[] = [];

        // Bilan financier
        $rows[] = ['BILAN FINANCIER'];
        $rows[] = ['Valeur des colis', number_format($this->data['finances']['valeur_colis'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Revenus livraisons', number_format($this->data['finances']['revenus_livraisons'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = ['Revenus navettes', number_format($this->data['finances']['revenus_navettes'] ?? 0, 0, ',', ' ') . ' DA'];
        $rows[] = [];

        // Ligne du total en gras
        $rows[] = ['CHIFFRE D\'AFFAIRES TOTAL', number_format($this->data['finances']['chiffre_affaires_total'] ?? 0, 0, ',', ' ') . ' DA'];

        // Répartition des revenus
        if (isset($this->data['finances']['repartition'])) {
            $rows[] = [];
            $rows[] = ['RÉPARTITION DES REVENUS'];
            $rows[] = ['Colis', ($this->data['finances']['repartition']['colis']['pourcentage'] ?? 0) . '%'];
            $rows[] = ['Livraisons', ($this->data['finances']['repartition']['livraisons']['pourcentage'] ?? 0) . '%'];
            $rows[] = ['Navettes', ($this->data['finances']['repartition']['navettes']['pourcentage'] ?? 0) . '%'];
        }

        // Répartition des statuts
        if (isset($this->data['statuts_livraisons'])) {
            $rows[] = [];
            $rows[] = ['RÉPARTITION DES STATUTS'];
            foreach ($this->data['statuts_livraisons'] as $statut => $count) {
                $statutLabel = str_replace('_', ' ', ucfirst($statut));
                $rows[] = [$statutLabel, $count];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        $wilayaNom = isset($this->data['gestionnaire']['wilaya_nom'])
            ? $this->data['gestionnaire']['wilaya_nom']
            : ($this->data['wilaya_nom'] ?? 'Bilan Gestionnaire');

        return [
            ['BILAN FINANCIER - ' . strtoupper($wilayaNom)],
        ];
    }

    public function title(): string
    {
        $wilayaId = isset($this->data['gestionnaire']['wilaya_id'])
            ? $this->data['gestionnaire']['wilaya_id']
            : ($this->data['wilaya_id'] ?? 'Bilan');

        return 'Bilan ' . $wilayaId;
    }

    public function styles(Worksheet $sheet)
    {
        // Style pour le titre principal
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style pour les titres de sections
        $sectionRows = [6, 13, 21, 28]; // À ajuster selon le nombre de lignes
        foreach ($sectionRows as $row) {
            if ($sheet->getCell('A' . $row)->getValue() && strpos($sheet->getCell('A' . $row)->getValue(), 'STATISTIQUES') !== false) {
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0F0F0');
            }
        }

        // Style pour la ligne du total
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A' . $lastRow . ':B' . $lastRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $lastRow . ':B' . $lastRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F0FE');

        // Ajuster la largeur des colonnes
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(25);

        // Alignement pour la colonne B (valeurs)
        $sheet->getStyle('B:B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
