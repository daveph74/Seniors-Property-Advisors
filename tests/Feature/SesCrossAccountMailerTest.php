<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class SesCrossAccountMailerTest extends TestCase
{
    public function test_the_cross_account_mailer_resolves_to_ses_in_the_configured_region(): void
    {
        config([
            'mail.mailers.ses_cross_account.region' => 'ap-southeast-2',
            'mail.mailers.ses_cross_account.role_arn' => 'arn:aws:iam::471112807687:role/AssumeSESSenderRole',
        ]);

        $transport = Mail::mailer('ses_cross_account')->getSymfonyTransport();

        $this->assertInstanceOf(SesV2Transport::class, $transport);
        $this->assertSame('ap-southeast-2', $transport->ses()->getRegion());
    }

    public function test_it_says_what_is_missing_rather_than_sending_nowhere(): void
    {
        config([
            'mail.mailers.ses_cross_account.region' => 'ap-southeast-2',
            'mail.mailers.ses_cross_account.role_arn' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SES_ROLE_ARN');

        Mail::mailer('ses_cross_account')->getSymfonyTransport();
    }
}
