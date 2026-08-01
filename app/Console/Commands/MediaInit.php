<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Throwable;

class MediaInit extends Command
{
    protected $signature = 'media:init';

    protected $description = 'Create the media bucket on the configured S3 endpoint and apply CORS if supported';

    public function handle(): int
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $endpoint = config('filesystems.disks.s3.endpoint');

        if (! $bucket) {
            $this->error('AWS_BUCKET is not set.');

            return self::FAILURE;
        }

        $this->line("Endpoint: {$endpoint}");
        $this->line("Bucket:   {$bucket}");

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
        } catch (Throwable $e) {
            $this->error('Could not build the S3 client: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            if ($client->doesBucketExist($bucket)) {
                $this->info('Bucket already exists.');
            } else {
                $client->createBucket(['Bucket' => $bucket]);
                $this->info('Bucket created.');
            }
        } catch (Throwable $e) {
            $this->error('Could not reach the storage service: '.$e->getMessage());
            $this->line('Is it running?  docker compose up -d');

            return self::FAILURE;
        }

        try {
            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => [
                    'CORSRules' => [[
                        'AllowedHeaders' => ['*'],
                        'AllowedMethods' => ['GET', 'PUT', 'HEAD'],
                        'AllowedOrigins' => ['*'],
                        'ExposeHeaders' => ['ETag'],
                        'MaxAgeSeconds' => 3000,
                    ]],
                ],
            ]);
            $this->info('CORS applied — presigned direct uploads are possible.');
        } catch (Throwable $e) {
            $this->warn('CORS not supported here: '.class_basename($e));
            $this->line('Uploads stream through Laravel instead, which needs no CORS.');
        }

        return self::SUCCESS;
    }
}
