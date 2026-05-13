<?php

namespace App\Mail;

use App\Models\CashDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CashDeliveryNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $cashDelivery;
    public $type;

    public function __construct(CashDelivery $cashDelivery, $type)
    {
        $this->cashDelivery = $cashDelivery;
        $this->type = $type; // nouvelle_demande, demande_acceptee, demande_refusee, demande_annulee
    }

    public function build()
    {
        $subject = $this->getSubject();
        return $this->subject($subject)
                    ->view('emails.cash-delivery-notification')
                    ->with([
                        'cashDelivery' => $this->cashDelivery,
                        'type' => $this->type
                    ]);
    }

    private function getSubject()
    {
        $montant = number_format($this->cashDelivery->montant, 0, ',', ' ');

        return match($this->type) {
            'nouvelle_demande' => "💰 Nouvelle demande COD - {$montant} DA",
            'demande_acceptee' => "✅ Demande COD acceptée - {$montant} DA",
            'demande_refusee' => "❌ Demande COD refusée - {$montant} DA",
            'demande_annulee' => "🚫 Demande COD annulée - {$montant} DA",
            default => "📝 Mise à jour demande COD"
        };
    }
}
