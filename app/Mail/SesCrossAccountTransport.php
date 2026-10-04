<?php

namespace App\Mail;

use App\Logging\AwsCalls;
use Aws\Credentials\AssumeRoleCredentialProvider;
use Aws\Credentials\CredentialProvider;
use Aws\SesV2\SesV2Client;
use Aws\Sts\StsClient;
use Illuminate\Mail\Transport\SesV2Transport;
use InvalidArgumentException;

/**
 * SES in another AWS account, reached by assuming a role there.
 *
 * Laravel's own `ses` mailer takes a static key and secret, which is the wrong shape here: the sending
 * identity lives in SyncID's account, and this site is given a role to assume rather than keys to hold.
 * The role is assumed with whatever credentials this host already has — the instance profile on a
 * server, or `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` where there is no profile — and the temporary
 * credentials are memoised, so they are refreshed when they expire rather than on every email.
 */
class SesCrossAccountTransport
{
    public static function make(array $config): SesV2Transport
    {
        $region = $config['region'] ?? null;
        $roleArn = $config['role_arn'] ?? null;

        if (blank($region) || blank($roleArn)) {
            throw new InvalidArgumentException('The ses_cross_account mailer needs SES_REGION and SES_ROLE_ARN.');
        }

        $sts = new StsClient([
            'region' => $region,
            'version' => 'latest',
            'credentials' => CredentialProvider::defaultProvider(),
        ]);
        AwsCalls::attach($sts);

        $assumed = new AssumeRoleCredentialProvider([
            'client' => $sts,
            'assume_role_params' => [
                'RoleArn' => $roleArn,
                'RoleSessionName' => $config['session_name'] ?? 'seniors-property-advisors-mail',
            ],
        ]);

        $ses = new SesV2Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => CredentialProvider::memoize($assumed),
        ]);
        AwsCalls::attach($ses);

        return new SesV2Transport($ses, $config['options'] ?? []);
    }
}
