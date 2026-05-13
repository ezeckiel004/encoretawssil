<?php
// app/Mail/DemandeCommissionNavetteMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemandeCommissionNavetteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $gains;
    public $gestionnaire;
    public $isMultiple;

    public function __construct($gains, $gestionnaire, $isMultiple = false)
    {
        $this->gains = $isMultiple ? $gains : collect([$gains]);
        $this->gestionnaire = $gestionnaire;
        $this->isMultiple = $isMultiple;
    }

    public function build()
    {
        $subject = $this->isMultiple
            ? 'Demande de paiement multiple - Gains navettes'
            : 'Demande de paiement - Gain navette';

        return $this->subject($subject)
                    ->view('emails.demande-commission-navette')
                    ->with([
                        'gains' => $this->gains,
                        'gestionnaire' => $this->gestionnaire,
                        'isMultiple' => $this->isMultiple,
                        'montantTotal' => $this->gains->sum('montant_commission')
                    ]);
    }
}
