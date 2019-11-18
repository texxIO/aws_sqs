<?php
include 'vendor/autoload.php';
use AwsSqs\AwsSqsClient;
use AwsSqs\AwsSqsPublisher;
use AwsSqs\AwsSqsWorker;

$dotenv = Dotenv\Dotenv::create(__DIR__, '.env');
$dotenv->load();

$awsSqsClient =  AwsSqsClient::getSqsClient();
$publisher = new AwsSqsPublisher($awsSqsClient);
$worker = new AwsSqsWorker($awsSqsClient);

$queueUrl = getenv('AWS_SQS_MESSAGES_QUEUE');
