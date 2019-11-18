<?php

namespace AwsSqs;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use AwsSqsClientException;
use InvalidArgumentException;

class AwsSqsWorker
{
    /**
     * @var SqsClient $sqsClient
     */
    private $sqsClient;
    /**
     * @var int $sleepIfNoMessages
     */
    public $sleepIfNoMessages = 1;

    /**
     * @var int $WaitTimeSeconds
     */
    public $WaitTimeSeconds = 20;

    /**
     * @var int $MaxNumberOfMessages
     */
    public $MaxNumberOfMessages = 1;

    /**
     * @var int $VisibilityTimeout
     */
    public $VisibilityTimeout = 3600;

    /**
     * @var string $queueName;
     */
    public $queueUrl;

    /**
     * AwsSqsWorker constructor.
     * @param SqsClient $sqsClient
     */
    public function __construct(SqsClient $sqsClient)
    {
        $this->sqsClient = $sqsClient;
    }

    /**
     * @param $queueUrl
     * @param $messageHandler
     * @param null $errorHandlerCallback
     * @throws \Exception
     */
    public function listen($queueUrl, $messageHandler, $errorHandlerCallback = null)
    {

        $this->queueUrl = $queueUrl;

        if (!is_callable($messageHandler)) {
            throw new InvalidArgumentException("Message handler is not callable or is missing");
        }

        if ($errorHandlerCallback != null && !is_callable($errorHandlerCallback)) {
            throw new InvalidArgumentException("errorHandlerCallback is not a callable function");
        }

        try {
            $this->runHeader();
        } catch (\Exception $e) {
            //do nothing
        }


        $checkForMessages = true;
        $counterCheck = 0;
        $errorCounter = 0;

        while ($checkForMessages) {

            $this->output("Check round (" . $counterCheck . ") @ " . date("Y-m-d H:i:s"));
            $this->output("Memory usage:" . $this->memoryUsage());
            try {

                $this->output("Waiting for messages ...");

                $this->getMessages(function ($messages) use ($messageHandler) {

                    $this->lockMessages($messages);

                    for ($i = 0; $i < count($messages); $i++) {
                        $completed = $messageHandler($messages[$i]);

                        if ($completed) {
                            $this->ackMessage($messages[$i]);
                        } else {
                            //Step 4.2: If we can't elaborate the message then we should MAKE MESSAGE AVAILABLE to other workers who can
                            $this->unlockMessage($messages[$i]);
                        }

                    }

                });

                $errorCounter = 0;

            } catch (AwsException $e) {

                if ($errorCounter >= 5) {
                    $checkForMessages = false;
                }
                $errorCounter++;

                // output error message if fails
                error_log($e->getMessage());

                if ($errorHandlerCallback != null) {
                    $errorHandlerCallback($e->getMessage(), $errorCounter);
                }
            } catch (AwsSqsClientException $e) {
                if ($errorCounter >= 5) {
                    $checkForMessages = false;
                }
                $errorCounter++;

                // output error message if fails
                error_log($e->getMessage());

                if ($errorHandlerCallback != null) {
                    $errorHandlerCallback($e->getMessage(), $errorCounter);
                }
            }

            $counterCheck++;

        }

        $this->runFooter();

    }

    private function getMessages($callback)
    {

        if ($this->sqsClient == null) {
            throw new AwsSqsClientException("AwsSqsClient is null");
        }

        $result = $this->sqsClient->receiveMessage([
            'AttributeNames' => ['SentTimestamp'],
            'MaxNumberOfMessages' => $this->MaxNumberOfMessages,
            'MessageAttributeNames' => ['All'],
            'QueueUrl' => $this->queueUrl, // REQUIRED
            'WaitTimeSeconds' => $this->WaitTimeSeconds,
        ]);

        //Step 1: GET MESSAGES:
        $messages = $result->get('Messages');
        if ($messages != null) {
            $this->output("Messages found");
            $callback($messages);
        } else {
            $this->output("No messages found");
            $sleep = $this->sleepIfNoMessages;
            $this->output("Sleeping for $sleep seconds");
            sleep($sleep);
        }

    }

    /**
     * Make messages unavailable for other workers
     * @param $messages
     * @throws \Exception
     */
    private function lockMessages($messages)
    {
        if ($this->sqsClient == null) {
            throw new AwsSqsClientException("AwsSqsClient is null");
        }

        $entries = [];
        for ($i = 0; $i < count($messages); $i++) {
            array_push($entries, [
                'Id' => 'unique_is_msg' . $i, // REQUIRED
                'ReceiptHandle' => $messages[$i]['ReceiptHandle'], // REQUIRED
                'VisibilityTimeout' => $this->VisibilityTimeout
            ]);
        }
        $result = $this->sqsClient->changeMessageVisibilityBatch([
            'Entries' => $entries,
            'QueueUrl' => $this->queueUrl
        ]);
    }

    /**
     * @param $message
     * @throws AwsSqsClientException
     */
    private function ackMessage($message)
    {
        if ($this->sqsClient == null) {
            throw new AwsSqsClientException("AwsSqsClient is null");
        }

        $result = $this->sqsClient->deleteMessage([
            'QueueUrl' => $this->queueUrl, // REQUIRED
            'ReceiptHandle' => $message['ReceiptHandle'], // REQUIRED
        ]);
    }

    /**
     * @param $message
     * @throws AwsSqsClientException
     */
    private function unlockMessage($message)
    {
        if ($this->sqsClient == null) {
            throw new AwsSqsClientException("AwsSqsClient is null");
        }

        $result = $this->sqsClient->changeMessageVisibility([
            
            'VisibilityTimeout' => 0,
            'QueueUrl' => $this->queueUrl, // REQUIRED
            'ReceiptHandle' => $message['ReceiptHandle'], // REQUIRED
        ]);
    }

    /**
     * @throws \Exception
     */
    private function runHeader()
    {
        $date = (new \DateTime())->format("Y-m-d H:i:s");
        echo "\n\n";
        echo "\n*****************************************************************";
        echo "\n**** Worker started at $date";
        echo "\n*****************************************************************";
    }

    /**
     * @throws \Exception
     */
    private function runFooter()
    {
        $date = (new \DateTime())->format("Y-m-d H:i:s");
        echo "\n\n";
        echo "\n*****************************************************************";
        echo "\n**** Worker finished at $date";
        echo "\n*****************************************************************";
        echo "\n\n";
    }

    /**
     * @param $message
     */
    private function output($message)
    {
        echo "\n" . $message;
    }

    /**
     * @return string
     */
    private function memoryUsage()
    {
        return $this->convert(memory_get_usage(true));
    }

    /**
     * @param $size
     * @return string
     */
    private function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

}