<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransactionalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;
    public string $emailBodyHtml;

    public function __construct(string $subject, string $bodyHtml)
    {
        $this->emailSubject = $subject;
        $this->emailBodyHtml = $bodyHtml;
    }

    public function build()
    {
        return $this->subject($this->emailSubject)
                    ->html($this->emailBodyHtml);
    }
}
