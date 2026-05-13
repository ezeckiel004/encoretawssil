<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    private $search;
    private $role;
    private $status;
    private $startDate;
    private $endDate;
    private $columns;

    public function __construct(
        $search = '',
        $role = '',
        $status = '',
        $startDate = '',
        $endDate = '',
        $columns = []
    ) {
        $this->search = $search;
        $this->role = $role;
        $this->status = $status;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->columns = $columns ?: [
            'ID',
            'Nom',
            'Prénom',
            'Email',
            'Téléphone',
            'Rôle',
            'Statut',
            'Date de création',
            'Dernière mise à jour'
        ];
    }

    public function collection()
    {
        $query = User::with(['client', 'livreur', 'gestionnaire']) // Ajout de 'gestionnaire'
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('nom', 'like', "%{$this->search}%")
                        ->orWhere('prenom', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('telephone', 'like', "%{$this->search}%");
                });
            })
            ->when($this->role, function ($q) {
                $q->where('role', $this->role);
            })
            ->when($this->status, function ($q) {
                $q->where('actif', $this->status === 'active');
            })
            ->when($this->startDate, function ($q) {
                $q->whereDate('created_at', '>=', $this->startDate);
            })
            ->when($this->endDate, function ($q) {
                $q->whereDate('created_at', '<=', $this->endDate);
            })
            ->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->nom,
            $user->prenom,
            $user->email,
            $user->telephone,
            $this->getFormattedRole($user->role),
            $user->actif ? 'Actif' : 'Inactif',
            $user->created_at ? $user->created_at->format('d/m/Y H:i') : '',
            $user->updated_at ? $user->updated_at->format('d/m/Y H:i') : '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style pour l'en-tête
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '4F46E5']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ]
            ],
            // Style pour les lignes
            'A2:I1000' => [
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // ID
            'B' => 20, // Nom
            'C' => 20, // Prénom
            'D' => 30, // Email
            'E' => 20, // Téléphone
            'F' => 15, // Rôle
            'G' => 15, // Statut
            'H' => 20, // Date création
            'I' => 20, // Date mise à jour
        ];
    }

    public function title(): string
    {
        return 'Utilisateurs';
    }

    /**
     * Formater le rôle pour l'affichage
     */
    private function getFormattedRole($role)
    {
        $roles = [
            'admin' => 'Administrateur',
            'client' => 'Client',
            'livreur' => 'Livreur',
            'gestionnaire' => 'Gestionnaire', // AJOUT DU RÔLE GESTIONNAIRE
        ];

        return $roles[$role] ?? $role;
    }
}
