<?php

namespace App\Logging;

use Aws\AwsClientInterface;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use DateTimeInterface;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Writes every AWS call the notification email makes into the delivery trail — the role it assumed in
 * SyncID's account, and what SES was asked to send and answered.
 *
 * "Sent" in the job only means the transport did not throw; SES returning a MessageId is the most this
 * application can ever know, and that MessageId is what SES's own suppression list and CloudWatch are
 * searched by. The raw message is never logged: it carries the enquirer's name, phone and address.
 */
class AwsCalls
{
    public const MIDDLEWARE = 'delivery-log';

    public static function attach(AwsClientInterface $client): void
    {
        $region = $client->getRegion();

        $client->getHandlerList()->appendSign(
            fn (callable $handler) => function (CommandInterface $command, ?RequestInterface $request = null) use ($handler, $region) {
                $name = $command->getName();

                Delivery::log()->info("AWS {$name} calling", ['region' => $region] + self::requestContext($command));

                return $handler($command, $request)->then(
                    function (ResultInterface $result) use ($name, $region) {
                        Delivery::log()->info("AWS {$name} succeeded", ['region' => $region] + self::resultContext($name, $result));

                        return $result;
                    },
                    function ($reason) use ($name, $region) {
                        Delivery::log()->error("AWS {$name} failed", ['region' => $region] + self::failureContext($reason));

                        return Create::rejectionFor($reason);
                    },
                );
            },
            self::MIDDLEWARE,
        );
    }

    private static function requestContext(CommandInterface $command): array
    {
        return match ($command->getName()) {
            'AssumeRole' => [
                'role_arn' => $command['RoleArn'],
                'session_name' => $command['RoleSessionName'],
            ],
            'SendEmail' => array_filter([
                'source' => $command['Source'] ?? $command['FromEmailAddress'],
                'to' => $command['Destination']['ToAddresses'] ?? [],
                'configuration_set' => $command['ConfigurationSetName'],
                'email_tags' => $command['EmailTags'],
                'raw_bytes' => strlen((string) ($command['Content']['Raw']['Data'] ?? '')),
            ], fn ($value) => $value !== null),
            default => [],
        };
    }

    private static function resultContext(string $name, ResultInterface $result): array
    {
        $context = match ($name) {
            'AssumeRole' => [
                'assumed_role_arn' => $result['AssumedRoleUser']['Arn'] ?? null,
                'expiration' => self::time($result['Credentials']['Expiration'] ?? null),
            ],
            'SendEmail' => ['ses_message_id' => $result['MessageId']],
            default => [],
        };

        return $context + [
            'status' => $result['@metadata']['statusCode'] ?? null,
            'aws_request_id' => $result['@metadata']['headers']['x-amzn-requestid'] ?? null,
        ];
    }

    private static function failureContext($reason): array
    {
        if ($reason instanceof AwsException) {
            return [
                'aws_error_code' => $reason->getAwsErrorCode(),
                'aws_error_message' => $reason->getAwsErrorMessage(),
                'status' => $reason->getStatusCode(),
                'aws_request_id' => $reason->getAwsRequestId(),
            ];
        }

        return [
            'exception' => is_object($reason) ? $reason::class : gettype($reason),
            'message' => $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
        ];
    }

    private static function time($value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }
}
