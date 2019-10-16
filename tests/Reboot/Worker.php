<?php
namespace Workerfy\Tests\Reboot;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {

        // 模拟处理业务
        sleep(3);
        var_dump("子进程 开始 reboot start");
        $this->reboot(); //可以观察到子进程pid在变化

    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        var_dump("子进程 shutdown--");
    }

//    public function __destruct()
//    {
//        var_dump("destruct");
//    }
}