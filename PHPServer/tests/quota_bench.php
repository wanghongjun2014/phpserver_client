<?php
$data = array(
    'class' => 'User',
    'method'=> 'getUserInfo',
    'user'  => 'Optool',
);
$buffer = json_encode($data)."\n";
$j=$i = isset($argv[1]) ? intval($argv[1]) : 100000;

$time_start = microtime(true);
$sample = 0;
while($j--)
{
    if($j == 10000)
    {
        $sample = 1;
        $time_s = microtime(true);
    }
        $client = stream_socket_client('tcp://127.0.0.1:1984', $errno, $errmsg, 0.1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT);
        fwrite($client, $buffer);
        fread($client, 1024);
    if($sample == 1) 
    {
        $sample = 0;
        echo (microtime(true) - $time_s)*1000, "\n" ;
    }
}

echo ceil($i/(microtime(true)-$time_start))."QPS\n";

echo (microtime(true)-$time_start)/$i, "\n";
