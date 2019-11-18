<?php

require '../bootstrap.php';

$worker->listen($queueUrl, function($message){
    echo "\n do something with the message\n";
    var_dump($message);

    //true will delete de message, false will unlock the message to be reprocessed
    return true;
});
