<?php 

/**
 * 
 * 接口成功率统计worker
 * 定时写入磁盘，用来统计请求量、延迟、波动等信息
 * @author liangl
 *
 */
class StatisticWorker extends PHPServerWorker
{
    // 最大buffer长度
    const MAX_BUFFER_SIZE = 524288;
    //实际最大UDP 内容长度为65507
    const MAX_UDP_DATA_SIZE = 65507;
    // 上次写数据到磁盘的时间
    protected $logLastWriteTime = 0;
    protected $stLastWriteTime = 0;
    protected $lastClearTime = 0;
    // log数据
    protected $logBuffer = '';
    // modid=>interface=>['code'=>[xx=>count,xx=>count],'suc_cost_time'=>xx,'fail_cost_time'=>xx, 'suc_count'=>xx, 'fail_count'=>xx, 'time'=>xxx]
    protected $statisticData = array();
    // modid=>interface=>['ip'=>[suc_count, fail_count],'ip'=>[suc_count, fail_count], ...]
    protected $statisticDataDetail = array();
    // modid=>interface=>['usr1'=>[suc_count, fail_count],'usr2'=>[suc_count, fail_count], ...]
    protected $statisticDataUserDetail = array();
    // 与统计中心通信所用的协议
    protected $protocolToCenter = 'udp';
    
    // 多长时间写一次log数据
    protected $logSendTimeLong = 300;
    // 多长时间写一次统计数据
    protected $stSendTimeLong = 300;
    // 多长时间清除一次统计数据
    protected $clearTimeLong = 86400;
    // 日志过期时间 14days
    protected $logExpTimeLong = 1296000;
    // 统计结果过期时间 14days
    protected $stExpTimeLong = 1296000;
    // 固定包长
    const PACKEGE_FIXED_LENGTH = 25;
    // phpserver全局统计
    const G_MODULE = 'PHPServer';
    // phpserver全局统计
    const G_INTERFACE = 'Statistics';
    // [cart-service=>[class=>[method=>count, method2=>..], class2=>[method=>count, ..], ..]
    protected $qpsData = array();
    // failed qps data
    protected $failedQpsData = array();
    //failed log data
    protected $log_failed_buffer = array();
    
    protected $qpsServiceUri = 'udp://10.0.231.116:3000';

    protected $failedQpsServiceUri = 'udp://10.0.231.116:3010';

    protected $logFailedServiceUri = 'udp://10.0.231.116:3020';
    
    protected $timeIntervalMS = 1000;
    
    
    
    /**
     * 默认只收1个包
     * 上报包的格式如下
     * struct{
     *     int                                    code,                 // 返回码
     *     unsigned int                           time,                 // 时间
     *     float                                  cost_time,            // 消耗时间 单位秒 例如1.xxx
     *     unsigned int                           source_ip,            // 来源ip
     *     unsigned int                           target_ip,            // 目标ip
     *     unsigned char                          success,              // 是否成功
     *     unsigned char                          module_name_length,   // 模块名字长度
     *     unsigned char                          interface_name_length,//接口名字长度
     *     unsigned short                         msg_length,           // 日志信息长度
     *     unsigned char[module_name_length]      module,               // 模块名字
     *     unsigned char[interface_name_length]   interface,            // 接口名字
     *     char[msg_length]                       msg                   // 日志内容
     *  }
     * @see PHPServerWorker::dealInput()
     */
    public function dealInput($recv_str)
    {
        return 0;
    }
    
    /**
     * 处理上报的数据 log buffer满的时候写入磁盘
     * @see PHPServerWorker::dealProcess()
     */
    public function dealProcess($recv_str)
    {
        // 解包
        $time_now = time();
        $unpack_data = unpack("icode/Itime/fcost_time/Isource_ip/Itarget_ip/Csuccess/Cmodule_name_length/Cinterface_name_length/Smsg_length", $recv_str);
        $source_ip = long2ip($unpack_data['source_ip']);
        $module = substr($recv_str, self::PACKEGE_FIXED_LENGTH, $unpack_data['module_name_length']);
        $interface = substr($recv_str, self::PACKEGE_FIXED_LENGTH + $unpack_data['module_name_length'], $unpack_data['interface_name_length']);
        $msg = substr($recv_str, self::PACKEGE_FIXED_LENGTH + $unpack_data['module_name_length'] + $unpack_data['interface_name_length'], $unpack_data['msg_length']);
        $msg = str_replace("\n", '<br>', $msg);
        $user = substr($recv_str, self::PACKEGE_FIXED_LENGTH + $unpack_data['module_name_length'] + $unpack_data['interface_name_length'] + $unpack_data['msg_length']);
        if(empty($user))
        {
        	$user = 'not_set';
        }
        $code = $unpack_data['code'];
        
        // 统计调用量、延迟、成功率等信息
        if(!isset($this->statisticData[$module]))
        {
            $this->statisticData[$module] = array();
            $this->statisticDataDetail[$module] = array();
            $this->statisticDataUserDetail[$module] = array();
        }
        if(!isset( $this->statisticData[self::G_MODULE]))
        {
            $this->statisticData[self::G_MODULE] = array();
            $this->statisticDataDetail[self::G_MODULE] = array();
            $this->statisticDataUserDetail[self::G_MODULE] = array();
        }
        if(!isset($this->statisticData[$module][$interface]))
        {
            $this->statisticData[$module][$interface] = array('code'=>array(), 'suc_cost_time'=>0, 'fail_cost_time'=>0, 'suc_count'=>0, 'fail_count'=>0, 'time'=>$this->stLastWriteTime + 300);
            $this->statisticDataDetail[$module][$interface] = array();
            $this->statisticDataUserDetail[$module][$interface] = array();
        }
        if(!isset($this->statisticDataDetail[$module][$interface][$source_ip]))
        {
        	$this->statisticDataDetail[$module][$interface][$source_ip] = array(0,0);
        }
        if(!isset($this->statisticDataUserDetail[$module][$interface][$user]))
        {
        	$this->statisticDataUserDetail[$module][$interface][$user] = array(0,0);
        }
        if(!isset($this->statisticData[self::G_MODULE][self::G_INTERFACE]))
        {
            $this->statisticData[self::G_MODULE][self::G_INTERFACE] = array('code'=>array(), 'suc_cost_time'=>0, 'fail_cost_time'=>0, 'suc_count'=>0, 'fail_count'=>0, 'time'=>$this->stLastWriteTime + 300);
            $this->statisticDataDetail[self::G_MODULE][self::G_INTERFACE] = array();
            $this->statisticDataUserDetail[self::G_MODULE][self::G_INTERFACE] = array();
        }
        if(!isset($this->statisticDataDetail[self::G_MODULE][self::G_INTERFACE][$source_ip]))
        {
        	$this->statisticDataDetail[self::G_MODULE][self::G_INTERFACE][$source_ip]=array(0,0);
        }
        if(!isset($this->statisticDataUserDetail[self::G_MODULE][self::G_INTERFACE][$user]))
        {
        	$this->statisticDataUserDetail[self::G_MODULE][self::G_INTERFACE][$user]=array(0,0);
        }
        if(!isset($this->statisticData[$module][$interface]['code'][$code]))
        {
            $this->statisticData[$module][$interface]['code'][$code] = 0;
        }
        if(!isset($this->statisticData[self::G_MODULE][self::G_INTERFACE][$code]))
        {
            $this->statisticData[self::G_MODULE][self::G_INTERFACE][$code] = 0;
        }
        $this->statisticData[$module][$interface]['code'][$code]++;
        $this->statisticData[self::G_MODULE][self::G_INTERFACE][$code]++;
        if($unpack_data['success'])
        {
            $this->statisticData[$module][$interface]['suc_cost_time'] += $unpack_data['cost_time'];
            $this->statisticData[$module][$interface]['suc_count'] ++;
            $this->statisticData[self::G_MODULE][self::G_INTERFACE]['suc_cost_time'] += $unpack_data['cost_time'];
            $this->statisticData[self::G_MODULE][self::G_INTERFACE]['suc_count'] ++;
            $this->statisticDataDetail[$module][$interface][$source_ip][0]++;
            $this->statisticDataUserDetail[$module][$interface][$user][0]++;
            $this->statisticDataDetail[self::G_MODULE][self::G_INTERFACE][$source_ip][0]++;
            $this->statisticDataUserDetail[self::G_MODULE][self::G_INTERFACE][$user][0]++;
        }
        else
       {
            $this->statisticData[$module][$interface]['fail_cost_time'] += $unpack_data['cost_time'];
            $this->statisticData[$module][$interface]['fail_count'] ++;
            $this->statisticData[self::G_MODULE][self::G_INTERFACE]['fail_cost_time'] += $unpack_data['cost_time'];
            $this->statisticData[self::G_MODULE][self::G_INTERFACE]['fail_count'] ++;
            $this->statisticDataDetail[$module][$interface][$source_ip][1]++;
            $this->statisticDataUserDetail[$module][$interface][$user][1]++;
            $this->statisticDataDetail[self::G_MODULE][self::G_INTERFACE][$source_ip][1]++;
            $this->statisticDataUserDetail[self::G_MODULE][self::G_INTERFACE][$user][1]++;
        }
        
        // qps 统计
        if($this->projectName)
        {
            //全局qps
            if(!isset($this->qpsData[$this->projectName][$module][$interface][$user])) {
                 $this->qpsData[$this->projectName][$module][$interface][$user] = 0;
             }
	      $this->qpsData[$this->projectName][$module][$interface][$user]++;
             
             if(!$unpack_data['success']) {
                  $time = $unpack_data['time'];
                  //失败qps
	            if(!isset($this->failedQpsData[$this->projectName][$module][$interface][$user][$time]))
	            {
                        $this->failedQpsData[$this->projectName][$module][$interface][$user][$time] = 0;
	             }
	             $this->failedQpsData[$this->projectName][$module][$interface][$user][$time]++;

                    //失败log
                    $module_interface = $this->projectName."::".$module."::".$interface;
                    $code_msg = $unpack_data['code'] . substr($msg, 0, strpos($msg, "<br>"));
                    $log_str1 = date('Y-m-d H:i:s',$unpack_data['time'])."\t{$this->projectName}::{$module}::{$interface}\tCODE:{$unpack_data['code']}\tMSG:{$msg}\tsource_ip:".long2ip($unpack_data['source_ip'])."\ttarget_ip:".long2ip($unpack_data['target_ip']);

                    if (isset($this->log_failed_buffer[$time][$module_interface][$code_msg])) {
                        $this->log_failed_buffer[$time][$module_interface][$code_msg]['count']++;
                    } else {
                        $this->log_failed_buffer[$time][$module_interface][$code_msg]['log'] = $log_str1;
                        $this->log_failed_buffer[$time][$module_interface][$code_msg]['count'] = 1;
                    }
	       }
        }
        
        // 如果不成功,写入日志
        if(!$unpack_data['success'])
        {
            $log_str2 = date('Y-m-d H:i:s',$unpack_data['time'])."\t{$module}::{$interface}\tCODE:{$unpack_data['code']}\tMSG:{$msg}\tsource_ip:".long2ip($unpack_data['source_ip'])."\ttarget_ip:".long2ip($unpack_data['target_ip'])."\n";
            // 如果buffer满了，则写磁盘,并清空buffer
            if(strlen($this->logBuffer) + strlen($recv_str) > self::MAX_BUFFER_SIZE)
            {
                // 写入log数据到磁盘
                $this->wirteLogToDisk();
                $this->logBuffer = $log_str2;
            }
            else 
           {
                $this->logBuffer .= $log_str2;
            }
        }
    }
    
    /**
     * 该worker进程开始服务的时候会触发一次
     * @return bool
     */
    protected function onServe()
    {
    	$qps_service_uri = PHPServerConfig::get('workers.'.$this->serviceName.'.qps_service_uri');
             $failed_qps_service_uri = PHPServerConfig::get('workers.'.$this->serviceName.'.failed_qps_service_uri');
             $log_failed_service_uri = PHPServerConfig::get('workers.'.$this->serviceName.'.log_service_uri');
    	if($qps_service_uri)
    	{
    		$this->qpsServiceUri = $qps_service_uri;
    	}
             if($failed_qps_service_uri)
             {
                          $this->failedQpsServiceUri = $failed_qps_service_uri;
             }
             if($log_failed_service_uri)
             {
                $this->logFailedServiceUri = $log_failed_service_uri;
             }
    	
        $this->eventLoopName = 'Select';
        
        $this->installSignal();
        
        $this->event = new $this->eventLoopName();
        
        // 添加管道可读事件
        $this->event->add($this->channel,  BaseEvent::EV_READ, array($this, 'dealCmd'), null, 0, 0);
        
        // 增加select超时事件
        $this->event->add(0, Select::EV_SELECT_TIMEOUT, array($this, 'onTimeCheck'), array() , $this->timeIntervalMS);
        
        // 添加accept事件
        if($this->protocol == 'udp')
        {
            // 添加读udp事件
            $this->event->add($this->mainSocket,  BaseEvent::EV_ACCEPT, array($this, 'recvUdp'));
        }
        else
       {
            // 添加accept事件
            $this->event->add($this->mainSocket,  BaseEvent::EV_ACCEPT, array($this, 'accept'));
        }
        
        $this->lastCallOnTime = microtime(true);
        
        $this->staticOnServe();
        
        // 主体循环
        while(1)
        {
            $ret = $this->event->loop();
            $this->notice("evet->loop returned " . var_export($ret, true));
        }
    }
    
    /**
     * 检查是否到达设定时间
     */
    public function onTimeCheck()
    {
        $time_now = microtime(true);
        if(($time_now - $this->lastCallOnTime)*1000 >= $this->timeIntervalMS)
        {
            $this->lastCallOnTime = $time_now;
            $this->onTime();
        }
        $time_diff = ($this->lastCallOnTime*1000 + $this->timeIntervalMS) - microtime(true)*1000;
        if($time_diff <= 0)
        {
            call_user_func(array($this, 'onTimeCheck'));
        }
        else
       {
            $this->event->setReadTimeOut($time_diff);
        }
    }
    
    public function onTime()
    {
       if(!$this->qpsData && !$this->failedQpsData)
       {
    	    return;
    	}
    	if($this->qpsServiceUri)
    	{
    	    $client = @stream_socket_client($this->qpsServiceUri);
    	    @stream_socket_sendto($client, json_encode($this->qpsData)."\n");
    	}
    	$this->qpsData = array();

       if($this->failedQpsServiceUri) {
           $client = @stream_socket_client($this->failedQpsServiceUri);
           @stream_socket_sendto($client, json_encode($this->failedQpsData));
      }
      $this->failedQpsData = array();

      if($this->logFailedServiceUri && !empty($this->log_failed_buffer) ) {
          $client = @stream_socket_client($this->logFailedServiceUri);
          foreach ($this->log_failed_buffer as $time => $module_interface_array) {
              $module_interface_key_array = array_keys($module_interface_array);

              //最大循环次数
              $max_len = 0;
              foreach ($module_interface_array as $key => $log_array) {
                  $max_len = count($log_array) > $max_len ? count($log_array) : $max_len;
              }

              $str = '';
              do {
                   foreach ($module_interface_key_array as $module_interface ) {
                        if (empty($module_interface_array[$module_interface]))
                            continue;
                        $tmp = array_shift($module_interface_array[$module_interface]);
                        $log = $tmp['log']."\tCOUNT:".$tmp['count']."\n";
                        if (strlen($str) + strlen($log) <= self::MAX_UDP_DATA_SIZE)
                            $str .= $log;
                        else
                            break 2;
                    }
              }while(--$max_len > 0);
              @stream_socket_sendto($client, $str);
              unset($this->log_failed_buffer[$time]);
          }
       }
    }
    
    /**
     * 发送统计数据到统计中心
     */
    protected function wirteLogToDisk()
    {
        // 初始化下一波统计数据
        $this->logLastWriteTime = time();
        
        // 有数据才写
        if(empty($this->logBuffer))
        {
            return true;
        }
        
        file_put_contents(SERVER_BASE.'logs/statistic/log/'.date('Y-m-d', $this->logLastWriteTime), $this->logBuffer, FILE_APPEND | LOCK_EX);
        
        $this->logBuffer = '';
    }
    
    
    protected function wirteStToDisk()
    {
        // 记录
        $this->stLastWriteTime = $this->stLastWriteTime + $this->stSendTimeLong;
        
        // 有数据才写磁盘
        if(empty($this->statisticData) && empty($this->statisticDataDetail))
        {
            return true;
        }
        
        $ip = $this->getClientIp();
        
        foreach($this->statisticData as $module=>$items)
        {
            if(!is_dir(SERVER_BASE.'logs/statistic/st/'.$module))
            {
                umask(0);
                mkdir(SERVER_BASE.'logs/statistic/st/'.$module, 0777, true);
            }
            foreach($items as $interface=>$data)
            {
                // modid=>['code'=>[xx=>count,xx=>count],'suc_cost_time'=>xx,'fail_cost_time'=>xx, 'suc_count'=>xx, 'fail_count'=>xx, 'time'=>xxx]
                file_put_contents(SERVER_BASE."logs/statistic/st/{$module}/{$interface}|".date('Y-m-d',$data['time']-1), "$ip\t{$data['time']}\t{$data['suc_count']}\t{$data['suc_cost_time']}\t{$data['fail_count']}\t{$data['fail_cost_time']}\t".json_encode($data['code'])."\n", FILE_APPEND | LOCK_EX);
            }
        }
        
        foreach($this->statisticDataDetail as $module=>$items)
        {
        	if(!is_dir(SERVER_BASE.'logs/statistic/detail/'.$module))
        	{
        		umask(0);
        		mkdir(SERVER_BASE.'logs/statistic/detail/'.$module, 0777, true);
        	}
        	foreach($items as $interface=>$data)
        	{
        		file_put_contents(SERVER_BASE."logs/statistic/detail/{$module}/{$interface}-detail|".date('Y-m-d',$this->stLastWriteTime + 299), ($this->stLastWriteTime + 300)."\t".json_encode($data)."\n", FILE_APPEND | LOCK_EX);
        	}
        }
        
       foreach($this->statisticDataUserDetail as $module=>$items)
        {
        	if(!is_dir(SERVER_BASE.'logs/statistic/userdetail/'.$module))
        	{
        		umask(0);
        		mkdir(SERVER_BASE.'logs/statistic/userdetail/'.$module, 0777, true);
        	}
        	foreach($items as $interface=>$data)
        	{
        		file_put_contents(SERVER_BASE."logs/statistic/userdetail/{$module}/{$interface}-detail|".date('Y-m-d',$this->stLastWriteTime + 299), ($this->stLastWriteTime + 300)."\t".json_encode($data)."\n", FILE_APPEND | LOCK_EX);
        	}
        }
        
        $this->statisticData = array();
        $this->statisticDataDetail = array();
        $this->statisticDataUserDetail = array();
    }
    
   
    
    /**
     * 该worker进程开始服务的时候会触发一次，初始化$logLastWriteTime
     * @return bool
     */
    protected function staticOnServe()
    {
        // 创建LOG目录
        if(!is_dir(SERVER_BASE.'logs/statistic/log'))
        {
            umask(0);
            @mkdir(SERVER_BASE.'logs/statistic/log', 0777, true);
        }
        
        $time_now = time();
        $this->logLastWriteTime = $time_now;
        $this->stLastWriteTime = $time_now - $time_now%$this->stSendTimeLong;
    }
    
    /**
     * 该worker进程停止服务的时候会触发一次，发送buffer
     * @return bool
     */
    protected function onStopServe()
    {
        // 发送数据到统计中心
        $this->wirteLogToDisk();
        $this->wirteStToDisk();
        return false;
    }
    
    /**
     * 每隔一定时间触发一次 
     * @see PHPServerWorker::onAlarm()
     */
    protected function onAlarm()
    {
        $time_now = time();
        // 检查距离最后一次发送数据到统计中心的时间是否超过设定时间
        if($time_now - $this->logLastWriteTime >= $this->logSendTimeLong)
        {
            // 发送数据到统计中心
            $this->wirteLogToDisk();
        }
        // 检查是否到了该发送统计数据的时间
        if($time_now - $this->stLastWriteTime >= $this->stSendTimeLong)
        {
            $this->wirteStToDisk();
        }
        
        // 检查是否到了清理数据的时间
        if($time_now - $this->lastClearTime >= $this->clearTimeLong)
        {
            $this->lastClearTime = $time_now;
            $this->clearDisk(SERVER_BASE.'logs/statistic/log/', $this->logExpTimeLong);
            $this->clearDisk(SERVER_BASE.'logs/statistic/st/', $this->stExpTimeLong);
            $this->clearDisk(SERVER_BASE.'logs/statistic/detail/', $this->stExpTimeLong);
            $this->clearDisk(SERVER_BASE.'logs/statistic/userdetail/', $this->stExpTimeLong);
        }
    }
    
    /**
     * 获得客户端ip
     */
    protected function getClientIp()
    {
        if($this->protocol == 'tcp')
        {
            $sock_name = stream_socket_get_name($this->connections[$this->currentDealFd], true);
        }
        else
        {
            $sock_name = $this->currentClientAddress;
        }
        $tmp = explode(':' ,$sock_name);
        $ip = $tmp[0];
        return $ip;
    }
    
    /**
     * 清除磁盘数据
     * @param string $file
     * @param int $exp_time
     */
    protected function clearDisk($file = null, $exp_time = 86400)
    {
        clearstatcache();
        if(!is_file($file) && !is_dir($file))
        {
        	return;
        }
        $time_now = time();
        if(is_file($file)) 
        {
            $stat = stat($file);
            $mtime = $stat['mtime'];
            if($time_now - $mtime > $exp_time)
            {
                unlink($file);
            }
            return;
        }
        
        $all_files = glob($file."/*");
        if($all_files)
        {
	        foreach ($all_files as $file_name) {
	            $this->clearDisk($file_name, $exp_time);
	        }
        }
        else 
        {
        	if($stat = stat($file))
        	{
	        	$mtime = $stat['mtime'];
	        	if($time_now - $mtime > $exp_time)
	        	{
	        		rmdir($file);
	        	}
        	}
        }
    }
    
} 
