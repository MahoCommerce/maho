<?php

$start_time = microtime(true);
register_shutdown_function(function() use ($start_time) {
    $time = number_format(microtime(true) - $start_time, 4);
    $path = $_SERVER['REQUEST_URI'];
    $msg = "took $time seconds for $path";
    Mage::log($msg, null, 'performance.log');
});
