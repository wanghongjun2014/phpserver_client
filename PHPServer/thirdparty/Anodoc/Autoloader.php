<?php

    /**
     * 按命名空间自动加载相应的类.
     *
     * @param string $name 命名空间及类名
     * @return boolean
     */
    function loadByNamespace($name)
    {
        $classPath = str_replace('\\', DIRECTORY_SEPARATOR ,$name);
        
        $classFile = __DIR__ . '/' . $classPath . '.php';

            if(is_file($classFile))
            {
                require($classFile);
                return true;
            }

        return false;
    }

       spl_autoload_register('loadByNamespace');
