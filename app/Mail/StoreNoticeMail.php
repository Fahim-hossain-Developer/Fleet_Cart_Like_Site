<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

final class StoreNoticeMail extends Mailable
{
    public function __construct(public string $bodyText, public string $heading) {}

    public function build(): static
    {
        return $this->subject($this->heading)->text('emails.store-notice');
    }
}
