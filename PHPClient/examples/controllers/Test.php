<?php
require __DIR__.'/../../Vendor/Bootstrap/Autoloader.php';
\Bootstrap\Autoloader::instance()->addRoot(__DIR__.'/../../../')->init();
require __DIR__.'/../config/PHPClient.php';
\PHPClient\Text::config((array)new \Config\PHPClient);

//同步方式。
$result=\PHPClient\Text::inst('Example')->setClass('Example')->sayHello('Dashen 1');
var_dump($result);
// 兼容测试
$client=\PHPClient\Text::inst('Example');
$client->setClass('Example');
$result = $client->sayHello('Dashen 0');
var_dump($result);

$n = 3;
while($n--) {
//异步方式
    $class = \PHPClient\Text::inst('Example')->setAsyncClass('Example');
    $req1 = $class->sayHello('Dashen 2');
    $req2 = $class->sayHello('Dashen 3');
    var_dump($req1->result, $req2->result);
}

//旧调用方式测试: 旧方式种，class名与phpclient配置中的名称一致。
$result = RpcClient_Example::instance()->sayHello("DaShen 4");
var_dump($result);
//此方式将调用失败： send方法和rpcclient自身的方式冲突
$result = RpcClient_Broadcast::instance()->send('test','test_password','test','hhh');
var_dump($data);
