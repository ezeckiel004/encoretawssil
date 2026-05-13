<?php
// app/Mail/StatutCommissionMail.php

namespace App\Mail;

use App\Models\GestionnaireGain;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StatutCommissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $gain;
    public $statut;
    public $note;

    public function __construct(GestionnaireGain $gain, string $statut, ?string $note = null)
    {
        $this->gain = $gain;
        $this->statut = $statut;
        $this->note = $note;
    }

    public function build()
    {
        $statutTexte = [
            'paye' => 'paiement effectué',
            'annule' => 'annulation',
            'en_attente' => 'mise en attente'
        ][$this->statut] ?? $this->statut;

        return $this->subject('Mise à jour de votre commission - Tawssil')
                    ->view('emails.statut-commission')
                    ->with([
                        'gain' => $this->gain,
                        'statut' => $this->statut,
                        'statutTexte' => $statutTexte,
                        'note' => $this->note,
                        'montant' => $this->gain->montant_commission,
                        'livraison' => $this->gain->livraison
                    ]);
    }
}
