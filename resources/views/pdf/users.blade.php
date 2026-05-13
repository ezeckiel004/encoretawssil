<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Liste des Utilisateurs - {{ config('app.name') }}</title>
    <style>
        /* Styles pour le PDF */
        @page {
            margin: 20px;
            font-family: Arial, sans-serif;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #4F46E5;
            font-size: 24px;
            margin: 0;
        }

        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .header .date {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
        }

        .stats {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            flex: 1;
            min-width: 150px;
            margin: 5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        .filters {
            background-color: #f1f5f9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .filters strong {
            color: #4F46E5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }

        table thead {
            background-color: #4F46E5;
            color: white;
        }

        table th {
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status-active {
            color: #10b981;
            font-weight: bold;
        }

        .status-inactive {
            color: #ef4444;
            font-weight: bold;
        }

        .role-admin {
            color: #8b5cf6;
            font-weight: bold;
        }

        .role-livreur {
            color: #3b82f6;
            font-weight: bold;
        }

        .role-client {
            color: #10b981;
            font-weight: bold;
        }

        .role-gestionnaire {
            color: #f97316;
            /* Orange pour les gestionnaires */
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .page-number:before {
            content: "Page " counter(page);
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        /* Colonnes spécifiques */
        .col-id {
            width: 5%;
        }

        .col-nom {
            width: 15%;
        }

        .col-prenom {
            width: 15%;
        }

        .col-email {
            width: 20%;
        }

        .col-telephone {
            width: 12%;
        }

        .col-role {
            width: 10%;
        }

        .col-status {
            width: 10%;
        }

        .col-date {
            width: 13%;
        }
    </style>
</head>

<body>
    <!-- En-tête -->
    <div class="header">
        <h1>Liste des Utilisateurs</h1>
        <div class="subtitle">Système de Gestion - {{ config('app.name') }}</div>
        <div class="date">Généré le : {{ $date ?? date('d/m/Y à H:i') }}</div>
    </div>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_users'] ?? 0 }}</div>
            <div class="stat-label">Total utilisateurs</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['active_users'] ?? 0 }}</div>
            <div class="stat-label">Utilisateurs actifs</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['inactive_users'] ?? 0 }}</div>
            <div class="stat-label">Utilisateurs inactifs</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_clients'] ?? 0 }}</div>
            <div class="stat-label">Clients</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_livreurs'] ?? 0 }}</div>
            <div class="stat-label">Livreurs</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_gestionnaires'] ?? 0 }}</div>
            <div class="stat-label">Gestionnaires</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $stats['total_admins'] ?? 0 }}</div>
            <div class="stat-label">Administrateurs</div>
        </div>
    </div>

    <!-- Filtres appliqués -->
    @if(!empty($filters['search']) || !empty($filters['role']))
    <div class="filters">
        <strong>Filtres appliqués :</strong><br>
        @if(!empty($filters['search']))
        • Recherche : "{{ $filters['search'] }}"<br>
        @endif
        @if(!empty($filters['role']))
        • Rôle :
        @if($filters['role'] == 'admin')
        Administrateur
        @elseif($filters['role'] == 'livreur')
        Livreur
        @elseif($filters['role'] == 'client')
        Client
        @elseif($filters['role'] == 'gestionnaire')
        Gestionnaire
        @else
        {{ $filters['role'] }}
        @endif
        <br>
        @endif
        • Nombre d'utilisateurs : {{ count($users) }}
    </div>
    @endif

    <!-- Tableau des utilisateurs -->
    @if(count($users) > 0)
    <table>
        <thead>
            <tr>
                @if(in_array('id', $columns))<th class="col-id">ID</th>@endif
                @if(in_array('nom', $columns))<th class="col-nom">Nom</th>@endif
                @if(in_array('prenom', $columns))<th class="col-prenom">Prénom</th>@endif
                @if(in_array('email', $columns))<th class="col-email">Email</th>@endif
                @if(in_array('telephone', $columns))<th class="col-telephone">Téléphone</th>@endif
                @if(in_array('role', $columns))<th class="col-role">Rôle</th>@endif
                @if(in_array('actif', $columns))<th class="col-status">Statut</th>@endif
                @if(in_array('created_at', $columns))<th class="col-date">Date création</th>@endif
                @if(in_array('updated_at', $columns))<th class="col-date">Dernière mise à jour</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                @if(in_array('id', $columns))<td>{{ $user->id }}</td>@endif
                @if(in_array('nom', $columns))<td>{{ $user->nom }}</td>@endif
                @if(in_array('prenom', $columns))<td>{{ $user->prenom }}</td>@endif
                @if(in_array('email', $columns))<td>{{ $user->email }}</td>@endif
                @if(in_array('telephone', $columns))<td>{{ $user->telephone }}</td>@endif

                @if(in_array('role', $columns))
                <td>
                    @if($user->role == 'admin')
                    <span class="role-admin">Administrateur</span>
                    @elseif($user->role == 'livreur')
                    <span class="role-livreur">Livreur</span>
                    @elseif($user->role == 'gestionnaire')
                    <span class="role-gestionnaire">Gestionnaire</span>
                    @elseif($user->role == 'client')
                    <span class="role-client">Client</span>
                    @else
                    {{ $user->role }}
                    @endif
                </td>
                @endif

                @if(in_array('actif', $columns))
                <td>
                    @if($user->actif)
                    <span class="status-active">Actif</span>
                    @else
                    <span class="status-inactive">Inactif</span>
                    @endif
                </td>
                @endif

                @if(in_array('created_at', $columns))
                <td>{{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}</td>
                @endif

                @if(in_array('updated_at', $columns))
                <td>{{ $user->updated_at ? $user->updated_at->format('d/m/Y H:i') : '-' }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        Aucun utilisateur à afficher avec les filtres sélectionnés.
    </div>
    @endif

    <!-- Pied de page -->
    <div class="footer">
        Document généré par {{ config('app.name') }} •
        {{ $date ?? date('d/m/Y à H:i') }} •
        <span class="page-number"></span>
    </div>
</body>

</html>
