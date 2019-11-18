<?php


namespace AwsSqs;

use Aws\Credentials\Credentials;
use Aws\Sdk;
use Aws\Sqs\SqsClient;

class AwsSqsClient
{
    /**
     * @return SqsClient
     */
    public static function getSqsClient()
    {
        $credentials = new Credentials(getenv('AWS_KEY'), getenv('AWS_SECRET_KEY'));
        $sqsConfig = [
            'credentials' => $credentials,
            'region' => getenv('AWS_REGION'),
            'version' => getenv('API_VERSION'),
        ];

        $sdk = new Sdk($sqsConfig);

        return  $sdk->createSqs();
    }

}