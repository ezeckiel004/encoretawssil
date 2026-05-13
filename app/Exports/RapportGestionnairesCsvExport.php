<?php
// app/Exports/RapportGestionnairesCsvExport.php

namespace App\Exports;

use App\Models\GestionnaireGain;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Carbon\Carbon;

class RapportGestionnairesCsvExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected $gains;
    protected $periode;

    public function __construct($gains, $periode)
    {
        $this->gains = $gains;
        $this->periode = $periode;
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
            'Date',
            'Gestionnaire',
            'Wilaya',
            'Type',
            'Livraison',
            'Montant Commission (DA)',
            'Pourcentage',
            'Statut',
            'Date Demande',
            'Date Paiement',
            'Note'
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
            $gain->created_at ? $gain->created_at->format('Y-m-d') : '',
            $nomGestionnaire,
            $gestionnaire->wilaya_id ?? '',
            $gain->wilaya_type === 'depart' ? 'Départ' : 'Arrivée',
            $gain->livraison_id,
            number_format($gain->montant_commission, 2, '.', ''),
            $gain->pourcentage_applique,
            $statutLabels[$gain->status] ?? $gain->status,
            $gain->date_demande ? $gain->date_demande->format('Y-m-d H:i') : '',
            $gain->date_paiement ? $gain->date_paiement->format('Y-m-d H:i') : '',
            $gain->note_admin ?? ''
        ];
    }

    /**
     * @return array
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '"',
            'line_ending' => "\r\n",
            'use_bom' => true,
            'include_separator_line' => false,
            'excel_compatibility' => true,
        ];
    }
}
