<?php

class Controller_Query
{

    public function action_Test()
    {
        header('Content-Type: text/plain; charset=utf-8');

        // {{ 示例代码:
        // 方式一(推荐)。如果是老的客户端(不使用handler规范),需在配置中加上小于2的版本号，如：'ver'=>1.0, 参考example中的config文件。
        $data = \PHPClient\Text::inst('User')->setClass('Info')->byUid(5100);


        // 方式二(兼容老版本)不支持异步调用
        $userInfo = RpcClient_User_Info::instance();

        $result = $userInfo->byUid(5100);
        var_dump($result);

        $result = $userInfo->getInfoByUid(1373);
        var_dump($result);

        // }}


        //异步请求方式
        $request1 = \PHPClient\Text::inst('User')->setAsyncClass('Info')->byUid(5100);
        $request2 = \PHPClient\Text::inst('User')->setAsyncClass('Info')->byName('Nickle');
        //服务端已在同时处理request1 和request2的请求。

        /*..... 其他业务逻辑.....*/

        //获取异步调用的结果。
        $result1 = $request1->result; //阻塞，直至服务端返回request1的结果。
        $result2 = $request2->result;
    }
}