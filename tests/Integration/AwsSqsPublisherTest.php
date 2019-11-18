<?php
declare(strict_types=1);

use \PHPUnit\Framework\TestCase;
use AwsSqs\AwsSqsClient;

class AwsSqsPublisherTest extends TestCase
{
    private $awsSqsClient;

    public function setUp(): void
    {
        $this->awsSqsClient = new AwsSqsClient();
    }

    public function testPublishSuccessMessage()
    {

    }
}