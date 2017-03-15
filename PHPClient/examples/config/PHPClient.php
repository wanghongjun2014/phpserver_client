<?php
namespace Config;


class PHPClient {
    public $rpc_secret_key = '769af463a39f077a0340a189e9c1ec28';
    public $monitor_log_dir = '/tmp/monitor-log';
    public $trace_log_path = '/tmp/monitor-log';
    public $recv_time_out = 5;
    public $User = array(
                    'uri' => 'tcp://192.168.20.95:2201',
                    'user' => 'Optool',
                    'secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
                        //'compressor' => 'GZ',
                    );
    public $Payment = array(
                    'uri' => 'tcp://127.0.0.1:2201',
                    'user' => 'Payment',
                    'secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
                    'ver' => 1.0
                        //'compressor' => 'GZ',
                    );
    public $inventoryService = array(
                    'uri' => 'tcp://192.168.20.95:5203',
                    'user' => 'test',
                    'secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
                        //'compressor' => 'GZ',
                    );
    public $MECService = array(
        'uri' => 'tcp://192.168.25.9:2203',
        'user' => 'test',
        'secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
    );
    public $Broadcast = array(
        'uri' => 'tcp://192.168.25.9:2203',
        'user' => 'test',
        'secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
    );
    public $Example = array(
        'uri' => 'tcp://192.168.25.9:22005',
        'user' => 'test',
        'secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
    );
    /**
     * 旧的HTTP rpcserver服务配置.
     * @var array
     */
    public $Rpc = array('default' => array (
                                'Url' => 'http://ws.jumeird.com/rpc.php',
                                'User' => 'Koubei',
                                'Secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}'
                                ),
                        'koubei' => array(
                            'Url' => 'http://rpc.koubei.jumeicd.com/rpc.php',
                            'User' => 'Jumei',
                            'Secret' => '{1BA09530-F9E6-478D-9965-7EB31A59537E}',
                        )
    );

} 
