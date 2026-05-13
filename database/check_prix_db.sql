-- Vérifier combien de demandes ont un prix
SELECT 
    COUNT(*) as total_demandes,
    COUNT(CASE WHEN prix IS NOT NULL THEN 1 END) as avec_prix,
    COUNT(CASE WHEN prix IS NULL THEN 1 END) as sans_prix,
    COUNT(CASE WHEN prix = 0 THEN 1 END) as prix_zero,
    MIN(prix) as prix_min,
    MAX(prix) as prix_max,
    AVG(prix) as prix_moyen
FROM demande_livraisons;

-- Voir les 10 premières demandes et leurs prix
SELECT id, client_id, prix, created_at FROM demande_livraisons LIMIT 10;
