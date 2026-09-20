<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;

function s3_client(): S3Client
{
    static $client = null;

    if ($client === null) {
        $client = new S3Client([
            'version' => 'latest',
            'region'  => getenv('AWS_REGION') ?: 'ap-south-1',
        ]);
    }

    return $client;
}

function s3_bucket(): string
{
    $bucket = getenv('S3_BUCKET');

    if (!$bucket) {
        throw new RuntimeException('S3_BUCKET environment variable is not set.');
    }

    return $bucket;
}

function s3_upload(string $key, string $filePath, string $contentType): void
{
    s3_client()->putObject([
        'Bucket'      => s3_bucket(),
        'Key'         => $key,
        'SourceFile'  => $filePath,
        'ContentType' => $contentType,
    ]);
}

function s3_delete(string $key): void
{
    s3_client()->deleteObject([
        'Bucket' => s3_bucket(),
        'Key'    => $key,
    ]);
}

function s3_url(string $key): string
{
    $result = s3_client()->createPresignedRequest(
        s3_client()->getCommand('GetObject', [
            'Bucket' => s3_bucket(),
            'Key'    => $key,
        ]),
        '+1 hour'
    );

    return (string) $result->getUri();
}