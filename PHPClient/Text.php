<?php
namespace PHPClient;

use \Exception;

class Text extends JMTextRpcClient{
    protected static $instances = array();
    protected $configName;

    /**
     * @param $config
     * @return static
     */
    public static function inst($config)
    {
        if(is_array($config))
        {
            $configName = md5(serialize($config));
            $allConfig = parent::config();
            if(!isset($allConfig[$configName]))
            {
                parent::config(array_merge($allConfig, $config));
            }
        }
        else
      {
            $configName = $config;
        }
        if(!isset(static::$instances[$configName]) || PHP_SAPI === 'cli')
        {
            static::$instances[$configName] = new static($configName);
        }
        return static::$instances[$configName];
    }


    protected function __construct($configName)
    {
        self::$badAddressListKey = fileinode(__DIR__.'/JMTextRpcClient.php');
        if(!self::$badAddressListKey)
        {
            self::$badAddressListKey = 500;
        }
        
        $config = parent::config();
        if(!self::$hasLoadConfig && class_exists('\Config\PHPClient'))
        {
        	self::$hasLoadConfig = true;
            $config = parent::config((array) new \Config\PHPClient);
        }

        if (empty($config)) {
            throw new Exception('JMTextRpcClient: Missing configurations');
        }

        
        if(!empty($config['recv_time_out']) && (int)$config['recv_time_out'] > 0)
        {
            self::$recvTimeOut = (int)$config['recv_time_out'];
        }
        
        if(!empty($config['connection_time_out']) && (int)$config['connection_time_out'] > 0)
        {
            self::$connectionTimeOut = (int)$config['connection_time_out'];
        }
        

        if (empty($config[$configName]))
        {
            throw new Exception(sprintf('JMTextRpcClient: Missing configuration for `%s`', $configName));
        } else {
            $this->configName = $this->appName = $configName;
            $this->init($config[$configName]);
        }
    }

    /**
     * @param string $name Service class name.
     * @return RequestWrapper
     */
    public function setClass($name)
    {
        $config = parent::config();
        if(isset($config[$this->configName]['ver']) && version_compare($config[$this->configName]['ver'], '2.0', '<'))
        {
            $className = 'RpcClient_'.$this->configName.'_'.$name;
        }
        else
        {
            $className = 'RpcClient_'.$name;
        }
        $this->rpcClass = $className;
        return New ServiceClassWrapper($this, $className);
    }

    /**
     * @param string $name Service classname to use.
     * @return RequestWrapper
     */
    public function setAsyncClass($name)
    {
        $config = parent::config();
        if(isset($config[$this->configName]['ver']) && version_compare($config[$this->configName]['ver'], '2.0', '<'))
        {
            $className = 'RpcClient_'.$this->configName.'_'.$name;
        }
        else
        {
            $className = 'RpcClient_'.$name;
        }
        $this->rpcClass = $className;
        return New ServiceClassWrapper($this, $className, true);
    }
}
