<?php
// app/Mail/DemandeCommissionMail.php

namespace App\Mail;

use App\Models\GestionnaireGain;
use App\Models\Gestionnaire;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemandeCommissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $gains;
    public $gestionnaire;
    public $multiple;

    public function __construct($gains, Gestionnaire $gestionnaire, $multiple = false)
    {
        $this->gains = $multiple ? $gains : collect([$gains]);
        $this->gestionnaire = $gestionnaire;
        $this->multiple = $multiple;
    }

    public function build()
    {
        $montantTotal = $this->gains->sum('montant_commission');
        $nbGains = $this->gains->count();

        return $this->subject('Nouvelle demande de commission - Tawssil')
                    ->view('emails.demande-commission')
                    ->with([
                        'gestionnaire' => $this->gestionnaire,
                        'gains' => $this->gains,
                        'montantTotal' => $montantTotal,
                        'nbGains' => $nbGains,
                        'multiple' => $this->multiple
                    ]);
    }
}
