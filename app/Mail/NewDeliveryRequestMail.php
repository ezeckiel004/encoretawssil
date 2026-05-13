<?php

namespace App\Mail;

use App\Models\DemandeLivraison;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewDeliveryRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $demande;
    public $client;

    /**
     * Create a new message instance.
     */
    public function __construct(DemandeLivraison $demande)
    {
        $this->demande = $demande;
        $this->client = $demande->client;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande de livraison - ' . $this->demande->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new_delivery_request',
            with: [
                'demande' => $this->demande,
                'client' => $this->client,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
