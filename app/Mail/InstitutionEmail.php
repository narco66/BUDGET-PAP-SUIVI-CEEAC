<?php



namespace App\Mail;



use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Mail\Mailables\Address;

use Illuminate\Mail\Mailables\Content;

use Illuminate\Mail\Mailables\Envelope;

use Illuminate\Queue\SerializesModels;



/** Courriel institutionnel CEEAC (N-P2). */

class InstitutionEmail extends Mailable

{

    use Queueable, SerializesModels;



    public function __construct(

        public readonly string $sujetMail,

        public readonly string $corpsHtml,

        public readonly ?string $corpsTexte = null,

        public readonly ?string $url = null,

    ) {}



    public function envelope(): Envelope

    {

        return new Envelope(

            from: new Address(

                config('notifications.email.from_address'),

                config('notifications.email.from_name'),

            ),

            subject: $this->sujetMail,

        );

    }



    public function content(): Content

    {

        return new Content(

            html: 'emails.institution',

            text: 'emails.institution-text',

            with: [

                'corpsHtml' => $this->corpsHtml,

                'corpsTexte' => $this->corpsTexte,

                'url' => $this->url,

            ],

        );

    }

}

