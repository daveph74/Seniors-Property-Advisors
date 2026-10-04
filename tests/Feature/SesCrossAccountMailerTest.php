<?php

namespace Tests\Feature;

use App\Logging\AwsCalls;
use ArrayObject;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Aws\Sts\StsClient;
use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Mockery;
use Psr\Log\LoggerInterface;
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

    public function test_both_aws_clients_it_builds_leave_a_trail(): void
    {
        config([
            'mail.mailers.ses_cross_account.region' => 'ap-southeast-2',
            'mail.mailers.ses_cross_account.role_arn' => 'arn:aws:iam::471112807687:role/AssumeSESSenderRole',
        ]);

        $ses = Mail::mailer('ses_cross_account')->getSymfonyTransport()->ses();

        $this->assertStringContainsString(AwsCalls::MIDDLEWARE, (string) $ses->getHandlerList());
    }

    public function test_a_send_logs_where_it_went_and_the_ses_message_id_but_not_the_message(): void
    {
        $logged = $this->captureTrail();
        $ses = new SesV2Client(['region' => 'ap-southeast-2', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's']]);
        AwsCalls::attach($ses);
        $ses->getHandlerList()->setHandler(new MockHandler([
            new Result(['MessageId' => 'ses-0001', '@metadata' => ['statusCode' => 200, 'headers' => ['x-amzn-requestid' => 'req-1']]]),
        ]));

        $ses->sendEmail([
            'FromEmailAddress' => 'seniorspropertyadvisors@syncid.com.au',
            'Destination' => ['ToAddresses' => ['advisor@example.com']],
            'Content' => ['Raw' => ['Data' => 'Subject: New enquiry — Jane Wilson']],
        ]);

        $this->assertSame(['advisor@example.com'], $logged['AWS SendEmail calling']['to']);
        $this->assertSame('seniorspropertyadvisors@syncid.com.au', $logged['AWS SendEmail calling']['source']);
        $this->assertSame('ses-0001', $logged['AWS SendEmail succeeded']['ses_message_id']);
        $this->assertSame('req-1', $logged['AWS SendEmail succeeded']['aws_request_id']);
        $this->assertStringNotContainsString('Jane Wilson', json_encode($logged));
    }

    public function test_a_role_that_cannot_be_assumed_is_logged_with_what_aws_said(): void
    {
        $logged = $this->captureTrail();
        $sts = new StsClient(['region' => 'ap-southeast-2', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's']]);
        AwsCalls::attach($sts);
        $sts->getHandlerList()->setHandler(new MockHandler([
            new AwsException('denied', new Command('AssumeRole'), ['code' => 'AccessDenied', 'message' => 'Not authorized to perform sts:AssumeRole', 'request_id' => 'req-2']),
        ]));

        try {
            $sts->assumeRole(['RoleArn' => 'arn:aws:iam::471112807687:role/AssumeSESSenderRole', 'RoleSessionName' => 'test']);
            $this->fail('The failure should still reach the caller.');
        } catch (AwsException) {
        }

        $this->assertSame('arn:aws:iam::471112807687:role/AssumeSESSenderRole', $logged['AWS AssumeRole calling']['role_arn']);
        $this->assertSame('AccessDenied', $logged['AWS AssumeRole failed']['aws_error_code']);
        $this->assertSame('req-2', $logged['AWS AssumeRole failed']['aws_request_id']);
    }

    private function captureTrail(): ArrayObject
    {
        $logged = new ArrayObject;
        $logger = Mockery::mock(LoggerInterface::class);
        foreach (['info', 'error'] as $level) {
            $logger->shouldReceive($level)->andReturnUsing(function ($message, $context = []) use ($logged) {
                $logged[$message] = $context;
            });
        }
        Log::shouldReceive('stack')->andReturn($logger);

        return $logged;
    }
}
