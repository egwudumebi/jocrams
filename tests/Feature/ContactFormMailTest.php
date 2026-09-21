<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use App\Support\Org\ContactInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_emails_jocrams_inbox_using_branded_mailable(): void
    {
        Mail::fake();

        $this->assertSame('jocrams2026@gmail.com', ContactInbox::email());

        $response = $this->postJson('/api/v1/public/contact', [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.com',
            'phone' => '+2348000000000',
            'subject' => 'Membership inquiry',
            'message' => 'I would like information about joining SICAMA.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Message received.');

        Mail::assertSent(ContactInquiryMail::class, function (ContactInquiryMail $mail): bool {
            return $mail->hasTo('jocrams2026@gmail.com')
                && $mail->inquiry->email === 'visitor@example.com'
                && $mail->inquiry->subject === 'Membership inquiry'
                && $mail->hasReplyTo('visitor@example.com');
        });
    }
}
