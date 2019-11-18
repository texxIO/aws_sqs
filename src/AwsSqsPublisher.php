<?php
declare(strict_types=1);

namespace AwsSqs;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use AwsSqsClientException;
use InvalidArgumentException;

class AwsSqsPublisher
{
    /**
     * @var AwsSqsClient $sqsClient
     */
    private $sqsClient;

    /**
     * @var int $publishMaxRetries
     */
    private $publishMaxRetries;

    /**
     * @var int $publishRetryDelay
     */
    private $publishRetryDelay;

    /**
     * AwsSqsPublisher constructor.
     * @param SqsClient $sqsClient
     */
    public function __construct(SqsClient $sqsClient)
    {
        $this->sqsClient = $sqsClient;
        $this->publishMaxRetries = getenv('AWS_SQS_PUBLISH_RETRIES');
        $this->publishRetryDelay = getenv('AWS_SQS_PUBLISH_RETRY_DELAY');
    }

    /**
     * @param string $queueUrl
     * @param string $message
     * @param array $messageAttributes
     * @param int $delaySeconds
     * @param int $messageGroupId
     * @return array|null
     * @throws AwsSqsClientException
     */
    public function publish($queueUrl, $message, $messageAttributes = [], $delaySeconds = 0, $messageGroupId = 0)
    {

        if ($this->sqsClient == null) {
            throw new AwsSqsClientException("AwsSqsClient is null");
        }

        //TODO - this should be moved I guess ..
        $params = [
            'QueueUrl' => $queueUrl,
            'MessageBody' => $message,
            'MessageAttributes' => $messageAttributes,
            'DelaySeconds' => $delaySeconds,
            'MessageGroupId' => $messageGroupId
        ];

        $retryFailed = true;
        $failedRetries = 0;

        $result = null;

        while ($retryFailed) {

            try {
                $result = $this->sqsClient->sendMessage($params);
                $retryFailed = false;
            } catch (AwsException $e) {

                if ($this->publishMaxRetries > 0 && $this->publishMaxRetries < $failedRetries) {
                    $retryFailed = true;

                    if ($failedRetries >= 2 && $this->publishRetryDelay > 0) {
                        sleep($this->publishRetryDelay);
                    }
                    error_log($e->getMessage());
                    $failedRetries++;
                } else {
                    break;
                }
            } catch (InvalidArgumentException $e) {
                error_log($e->getMessage());
                break;
            }
        }
        return $result;
    }
}