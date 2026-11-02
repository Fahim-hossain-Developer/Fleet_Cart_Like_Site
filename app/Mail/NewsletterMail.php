<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

final class NewsletterMail extends Mailable
{
    public function __construct(public string $bodyText, public string $heading, public ?string $unsubscribeUrl = null) {}

    public function build(): static
    {
        $mail = $this->subject($this->heading)->text('emails.store-notice');
        if ($this->unsubscribeUrl) {
            $url = $this->unsubscribeUrl;
            $mail->withSymfonyMessage(function (Email $message) use ($url) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$url.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
        }

        return $mail;
    }
}
