-- Vérifier quels colis ont des prix
SELECT 
    d.id,
    d.client_id,
    d.prix,
    d.created_at,
    c.colis_prix,
    l.id as livraison_id
FROM demande_livraisons d
LEFT JOIN colis c ON d.colis_id = c.id
LEFT JOIN livraisons l ON d.id = l.demande_livraisons_id
ORDER BY d.created_at DESC
LIMIT 20;

-- Compter les demandes par prix
SELECT 
    CASE 
        WHEN prix IS NULL THEN 'NULL'
        WHEN prix = 0 THEN 'ZERO'
        ELSE 'HAS_PRICE'
    END as price_status,
    COUNT(*) as count
FROM demande_livraisons
GROUP BY CASE 
        WHEN prix IS NULL THEN 'NULL'
        WHEN prix = 0 THEN 'ZERO'
        ELSE 'HAS_PRICE'
    END;
