<?php
$ip='127.0.0.1';
if(!empty($argv[1]))
{
    $ip = $argv[1];
}
$client=stream_socket_client("tcp://$ip:20305", $errno, $errmsg, 1);
if(!$client)
{
    exit($errmsg);
}

$data = array(
    'type'   => 'update_alarm_phone',
    'phones' => '12345678912, 98765432101'
);

stream_set_timeout($client, 1);

fwrite($client, json_encode($data));

var_dump(fread($client, 1024));
