<?php

namespace App\Enums;

enum NotificationType: string
{
    case NOUVEAU_COLIS = 'nouveau_colis';
    case LIVREUR_ARRIVE = 'livreur_arrive';
    case COLIS_LIVRE = 'colis_livre';
    case COLIS_RETARDE = 'colis_retarde';
    case NEW_AVIS = 'nouveau_avis';
    case AVIS_LU = 'avis_recu';
    case AVIS_RESPONSE = 'avis_repondu';

    case MESSAGE_CLIENT = 'message_client';
    case DemandeAdhesionA = 'demande_adhesion_accepter';
    case DemandeAdhesion = 'demande_adhesion_client';

    case DemandeAdhesionR = 'demande_adhesion_refuser';
    case DemandeLivraisonV = 'demande_livraison_valider';
    case NEW_DELIVERY_REQUEST = 'demande_livraison_en_attente_validation';

    case LIVRAISON_CONFIRMER = 'livraison_confirmer';
    case LIVRAISON_ATTRIBUER = 'livraison_attribuer';
    case LIVRAISON_ANNULER = 'livraison_annuler';
    case LIVRAISON_STATUT_MISE_A_JOUR = 'livraison_status_mis_a_jour';
    case BORDEREAU_CREATED = 'bordereau_creer';
    case UPDATE_PHOTO = 'update_photo';
}
