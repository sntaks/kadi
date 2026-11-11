<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The single-use "sign out my other device" link (App\Http\Controllers\Auth\ReleaseDeviceController). */
class ReleaseDeviceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Sign out your other device'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.release-device',
            with: [
                'user' => $this->user,
                'url' => $this->url,
                'minutes' => $this->minutes,
                'appName' => config('app.name'),
            ],
        );
    }
}
