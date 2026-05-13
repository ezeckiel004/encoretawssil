# 📚 Documentation API TawssilGo

**Base URL:** `http://192.168.100.43:8000/api`

---

## 🔐 Authentification

### 1. Inscription
**Endpoint:** `POST /auth/register`

**Body:**
```json
{
  "nom": "Dupont",
  "prenom": "Jean",
  "email": "jean.dupont@example.com",
  "telephone": "0612345678",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}
```

**Réponse (201):**
```json
{
  "success": true,
  "message": "Utilisateur créé avec succès",
  "data": {
    "user": {...},
    "token": "1|xxxxxxxxxxxxx"
  }
}
```

---

### 2. Connexion
**Endpoint:** `POST /auth/login`

**Body:**
```json
{
  "email": "jean.dupont@example.com",
  "password": "password123"
}
```

**Réponse (200):**
```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "user": {...},
    "token": "2|xxxxxxxxxxxxx"
  }
}
```

---

### 3. Mot de passe oublié
**Endpoint:** `POST /auth/forgot-password`

**Body:**
```json
{
  "email": "jean.dupont@example.com"
}
```

---

## 🔒 Routes Protégées (Authentification requise)

**Headers requis pour toutes les routes suivantes:**
```
Authorization: Bearer {votre_token}
Content-Type: application/json
```

---

### 4. Déconnexion
**Endpoint:** `POST /auth/logout`

**Headers uniquement (pas de body)**

---

### 5. Déconnexion de tous les appareils
**Endpoint:** `POST /auth/logout-all`

---

### 6. Mettre à jour le profil
**Endpoint:** `PUT /auth/profile`

**Body:**
```json
{
  "nom": "Dupont",
  "prenom": "Jean",
  "email": "jean.new@example.com",
  "telephone": "0698765432"
}
```

---

### 7. Mettre à jour la photo de profil
**Endpoint:** `PUT /auth/profile/photo`

**Body (multipart/form-data):**
```
photo: [fichier image]
```

---

### 8. Changer le mot de passe
**Endpoint:** `PUT /auth/change-password`

**Body:**
```json
{
  "current_password": "oldpassword123",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

---

### 9. Vérifier le token
**Endpoint:** `GET /auth/verify-token`

---

### 10. Obtenir tous les utilisateurs
**Endpoint:** `GET /auth/all-users`

---

### 11. Supprimer un utilisateur
**Endpoint:** `DELETE /auth/delete/{id}`

**Exemple:** `DELETE /auth/delete/9c8936ac-bae5-43a5-90dd-1d3b55438d89`

---

## 📦 Demandes de Livraison

### 12. Créer une demande de livraison
**Endpoint:** `POST /demandes-livraison`

**Body (multipart/form-data):**
```
client_id: 9c8936ac-bae5-43a5-90dd-1d3b55438d89
addresse_depot: Alger, Algérie
addresse_delivery: Oran, Algérie
info_additionnel: Colis fragile
lat_depot: 36.7729333
lng_depot: 3.0588445
lat_delivery: 35.6911
lng_delivery: -0.6417
destinataire_nom: Benali Ahmed
destinataire_email: benali@example.com
destinataire_telephone: 213 556789012
colis_poids: 2.5
prix: 400.0
colis_type: Électronique
colis_photo: [fichier image - JPEG/PNG, max 10MB]
```

**Réponse (201):**
```json
{
  "id": "xxx",
  "client_id": "xxx",
  "addresse_depot": "Alger, Algérie",
  "addresse_delivery": "Oran, Algérie",
  "colis": {
    "id": "xxx",
    "poids": 2.5,
    "colis_type": "Électronique",
    "colis_label": "COLIS-ABC123",
    "colis_photo": "photos/xxx.jpg",
    "colis_photo_url": "http://..."
  },
  "destinataire": {...}
}
```

---

### 13. Obtenir toutes les demandes de livraison
**Endpoint:** `GET /demandes-livraison`

---

### 14. Obtenir une demande de livraison
**Endpoint:** `GET /demandes-livraison/{id}`

**Exemple:** `GET /demandes-livraison/9c8936ac-bae5-43a5-90dd-1d3b55438d89`

---

### 15. Mettre à jour une demande de livraison
**Endpoint:** `PUT /demandes-livraison/{id}`

**Body:**
```json
{
  "statut": "en_cours",
  "info_additionnel": "Nouvelle information"
}
```

---

### 16. Supprimer une demande de livraison
**Endpoint:** `DELETE /demandes-livraison/{id}`

---

## 🚚 Livraisons

### 17. Obtenir toutes les livraisons
**Endpoint:** `GET /livraisons`

---

### 18. Obtenir une livraison
**Endpoint:** `GET /livraisons/{id}`

---

### 19. Créer une livraison
**Endpoint:** `POST /livraisons`

**Body:**
```json
{
  "client_id": "9c8936ac-bae5-43a5-90dd-1d3b55438d89",
  "demande_livraisons_id": "xxx",
  "livreur_id": "xxx",
  "code_pin": "12345"
}
```

---

### 20. Mettre à jour le statut d'une livraison
**Endpoint:** `PATCH /livraisons/{id}/status`

**Body:**
```json
{
  "status": "en_transit"
}
```

**Statuts possibles:**
- `en_attente`
- `en_transit`
- `arrive`
- `livre`
- `annule`

---

### 21. Assigner un livreur à une livraison
**Endpoint:** `PATCH /livraisons/{id}/assign-livreur`

**Body:**
```json
{
  "livreur_id": "9c8936ac-bae5-43a5-90dd-1d3b55438d89"
}
```

---

### 22. Supprimer une livraison (client)
**Endpoint:** `PATCH /livraisons/{id}/destroy_by_client`

---

### 23. Statistiques client
**Endpoint:** `GET /livraisons/{client_id}/statistiques`

---

### 24. Statistiques livreur
**Endpoint:** `GET /livraisons/{livreur_id}/statistiques/livreur`

---

### 25. Livraisons par client
**Endpoint:** `GET /livraisons/getByClient/{client_id}`

---

### 26. Livraisons par livreur
**Endpoint:** `GET /livraisons/getByLivreur/{livreur_id}`

---

### 27. Livraisons en cours (client)
**Endpoint:** `GET /livraisons/client/{client_id}/en-cours`

---

### 28. Livraisons en cours (livreur)
**Endpoint:** `GET /livraisons/livreur/{livreur_id}/en-cours`

---

### 29. Toutes les livraisons en cours
**Endpoint:** `GET /livraisons/en-cours`

---

## 👤 Clients

### 30. Obtenir tous les clients
**Endpoint:** `GET /clients`

---

### 31. Obtenir un client
**Endpoint:** `GET /clients/{id}`

---

### 32. Créer un client
**Endpoint:** `POST /clients`

**Body:**
```json
{
  "user_id": "9c8936ac-bae5-43a5-90dd-1d3b55438d89",
  "status": "active"
}
```

---

### 33. Mettre à jour un client
**Endpoint:** `PUT /clients/{id}`

---

### 34. Supprimer un client
**Endpoint:** `DELETE /clients/{id}`

---

## 🚴 Livreurs

### 35. Obtenir tous les livreurs
**Endpoint:** `GET /livreurs`

---

### 36. Obtenir un livreur
**Endpoint:** `GET /livreurs/{id}`

---

### 37. Créer un livreur
**Endpoint:** `POST /livreurs`

**Body:**
```json
{
  "user_id": "9c8936ac-bae5-43a5-90dd-1d3b55438d89",
  "numero_plaque": "16-ABC-1234",
  "type_vehicule": "Moto",
  "numero_permis": "123456789",
  "numero_piece_identite": "987654321",
  "photo_vehicule": "url_ou_chemin",
  "photo_permis": "url_ou_chemin",
  "photo_piece_identite": "url_ou_chemin",
  "actif": true
}
```

---

### 38. Mettre à jour un livreur
**Endpoint:** `PUT /livreurs/{id}`

---

### 39. Activer/Désactiver un livreur
**Endpoint:** `PATCH /livreurs/{id}/toggle-activation`

---

### 40. Supprimer un livreur
**Endpoint:** `DELETE /livreurs/{id}`

---

## 📋 Demandes d'Adhésion

### 41. Obtenir toutes les demandes d'adhésion
**Endpoint:** `GET /demandes-adhesion`

---

### 42. Obtenir une demande d'adhésion
**Endpoint:** `GET /demandes-adhesion/{id}`

---

### 43. Créer une demande d'adhésion
**Endpoint:** `POST /demandes-adhesion`

**Body (multipart/form-data):**
```
user_id: 9c8936ac-bae5-43a5-90dd-1d3b55438d89
numero_plaque: 16-ABC-1234
type_vehicule: Moto
numero_permis: 123456789
numero_piece_identite: 987654321
type_piece_identite: Carte d'identité
photo_vehicule: [fichier]
photo_permis: [fichier]
photo_piece_identite: [fichier]
```

---

### 44. Mettre à jour le statut d'une demande d'adhésion
**Endpoint:** `PATCH /demandes-adhesion/{id}/status`

**Body:**
```json
{
  "status": "approuvee"
}
```

**Statuts possibles:**
- `en_attente`
- `approuvee`
- `rejetee`

---

### 45. Obtenir les demandes par statut
**Endpoint:** `GET /demandes-adhesion/by-status/{status}`

**Exemple:** `GET /demandes-adhesion/by-status/en_attente`

---

### 46. Supprimer une demande d'adhésion
**Endpoint:** `DELETE /demandes-adhesion/{id}`

---

## ⭐ Avis

### 47. Obtenir tous les avis
**Endpoint:** `GET /avis`

---

### 48. Obtenir un avis
**Endpoint:** `GET /avis/{id}`

---

### 49. Créer un avis
**Endpoint:** `POST /avis`

**Body:**
```json
{
  "livraison_id": "xxx",
  "client_id": "xxx",
  "note": 5,
  "commentaire": "Excellent service !",
  "photo_livraison": "url_ou_chemin"
}
```

---

### 50. Mettre à jour un avis
**Endpoint:** `PUT /avis/{id}`

---

### 51. Supprimer un avis
**Endpoint:** `DELETE /avis/{id}`

---

## 💬 Réponses aux Avis

### 52. Obtenir toutes les réponses aux avis
**Endpoint:** `GET /reponses-avis`

---

### 53. Créer une réponse à un avis
**Endpoint:** `POST /reponses-avis`

**Body:**
```json
{
  "avis_id": "xxx",
  "livreur_id": "xxx",
  "reponse": "Merci pour votre retour !"
}
```

---

## 💭 Commentaires

### 54. Obtenir tous les commentaires
**Endpoint:** `GET /commentaires`

---

### 55. Créer un commentaire
**Endpoint:** `POST /commentaires`

**Body:**
```json
{
  "livraison_id": "xxx",
  "user_id": "xxx",
  "commentaire": "Colis bien reçu"
}
```

---

## 📄 Bordereaux

### 56. Obtenir tous les bordereaux
**Endpoint:** `GET /bordereaux`

---

### 57. Obtenir un bordereau
**Endpoint:** `GET /bordereaux/{id}`

---

### 58. Créer un bordereau
**Endpoint:** `POST /bordereaux`

**Body:**
```json
{
  "livreur_id": "xxx",
  "date": "2025-12-01",
  "montant_total": 5000.0,
  "nombre_livraisons": 10
}
```

---

## 📍 Position & Photo

### 59. Mettre à jour la position de l'utilisateur
**Endpoint:** `PATCH /user/position`

**Body:**
```json
{
  "latitude": 36.7729333,
  "longitude": 3.0588445
}
```

---

### 60. Mettre à jour la photo
**Endpoint:** `PATCH /update/photo`

**Body (multipart/form-data):**
```
photo: [fichier image]
```

---

## 🔔 Notifications

### 61. Enregistrer un token FCM
**Endpoint:** `POST /users/{userId}/fcm-token`

**Body:**
```json
{
  "fcm_token": "xxxxxxxxxxxxx",
  "device_type": "android"
}
```

---

### 62. Obtenir les notifications d'un utilisateur
**Endpoint:** `GET /users/{user_id}/notifications`

---

### 63. Marquer une notification comme lue
**Endpoint:** `POST /users/{user_id}/notifications/{notification_id}/read`

---

## 🚴‍♂️ Routes Livreur

### 64. Obtenir toutes les courses du livreur
**Endpoint:** `GET /livreur/courses`

---

### 65. Compter les courses
**Endpoint:** `GET /livreur/courses/count`

---

### 66. Courses par statut
**Endpoint:** `GET /livreur/courses/status/{status}`

**Exemple:** `GET /livreur/courses/status/en_transit`

---

### 67. Obtenir les colis
**Endpoint:** `GET /livreur/courses/colis`

---

### 68. Obtenir une course
**Endpoint:** `GET /livreur/courses/{id}`

---

### 69. Compléter une course
**Endpoint:** `POST /livreur/courses/{id}/complete`

**Body:**
```json
{
  "code_pin": "12345",
  "photo_livraison": "url_ou_chemin",
  "commentaire": "Livraison effectuée avec succès"
}
```

---

### 70. Dashboard livreur (statistiques)
**Endpoint:** `GET /livreur/stats/dashboard`

**Réponse:**
```json
{
  "livraisons_total": 150,
  "livraisons_en_cours": 5,
  "livraisons_completees": 140,
  "revenus_total": 42000.0,
  "note_moyenne": 4.8
}
```

---

### 71. Statistiques détaillées livreur
**Endpoint:** `GET /livreur/stats/detailed`

---

## 📊 Codes de Réponse HTTP

| Code | Description |
|------|-------------|
| 200  | Succès |
| 201  | Créé avec succès |
| 400  | Requête incorrecte |
| 401  | Non authentifié |
| 403  | Accès refusé |
| 404  | Ressource introuvable |
| 422  | Erreur de validation |
| 500  | Erreur serveur |

---

## 🛠️ Exemples avec cURL

### Inscription
```bash
curl -X POST http://192.168.100.43:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nom": "Dupont",
    "prenom": "Jean",
    "email": "jean@example.com",
    "telephone": "0612345678",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "client"
  }'
```

### Connexion
```bash
curl -X POST http://192.168.100.43:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jean@example.com",
    "password": "password123"
  }'
```

### Créer une demande de livraison (avec photo)
```bash
curl -X POST http://192.168.100.43:8000/api/demandes-livraison \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "client_id=9c8936ac-bae5-43a5-90dd-1d3b55438d89" \
  -F "addresse_depot=Alger, Algérie" \
  -F "addresse_delivery=Oran, Algérie" \
  -F "info_additionnel=Colis fragile" \
  -F "lat_depot=36.7729333" \
  -F "lng_depot=3.0588445" \
  -F "lat_delivery=35.6911" \
  -F "lng_delivery=-0.6417" \
  -F "destinataire_nom=Benali Ahmed" \
  -F "destinataire_email=benali@example.com" \
  -F "destinataire_telephone=213 556789012" \
  -F "colis_poids=2.5" \
  -F "prix=400.0" \
  -F "colis_type=Électronique" \
  -F "colis_photo=@/chemin/vers/photo.jpg"
```

### Obtenir les livraisons d'un client
```bash
curl -X GET http://192.168.100.43:8000/api/livraisons/getByClient/9c8936ac-bae5-43a5-90dd-1d3b55438d89 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📝 Notes Importantes

1. **Authentification:** Toutes les routes sauf `/auth/register`, `/auth/login` et `/auth/forgot-password` nécessitent un token Bearer.

2. **Upload de fichiers:** Utilisez `multipart/form-data` pour les endpoints avec des fichiers (photos).

3. **Taille maximale des fichiers:**
   - Photos: 10 MB
   - Documents: 5 MB

4. **Format des images acceptées:** JPEG, PNG, JPG, GIF

5. **IDs:** Tous les IDs sont des UUID au format `9c8936ac-bae5-43a5-90dd-1d3b55438d89`

---

**Date de dernière mise à jour:** 01 Décembre 2025
