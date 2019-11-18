<?php
require '../bootstrap.php';

$message = "This is a message for SQS" . time();

$x = $publisher->publish($queueUrl, $message, [
    'MessageAttributes' => ['DataType' => "String", "StringValue" => "test"]

]);
var_dump($x);



