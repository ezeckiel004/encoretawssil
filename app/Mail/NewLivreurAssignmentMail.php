<?php

namespace App\Mail;

use App\Models\Livraison;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLivreurAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $livraison;
    public $livreur;
    public $type; // 1 pour ramasseur, 2 pour distributeur
    public $typeLabel;

    /**
     * Create a new message instance.
     */
    public function __construct(Livraison $livraison, User $livreur, int $type)
    {
        $this->livraison = $livraison;
        $this->livreur = $livreur;
        $this->type = $type;
        $this->typeLabel = $type === 1 ? 'Ramasseur' : 'Distributeur';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle mission assignée - ' . $this->typeLabel,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.livreur_assignment',
            with: [
                'livraison' => $this->livraison,
                'demande' => $this->livraison->demandeLivraison,
                'livreur' => $this->livreur,
                'type' => $this->type,
                'typeLabel' => $this->typeLabel,
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
