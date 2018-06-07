<?php

namespace apps\crontab\commands;

use mix\console\ExitCode;
use mix\facades\Input;
use mix\task\CenterProcess;
use mix\task\RightProcess;
use mix\task\TaskExecutor;

/**
 * 采集模式范例
 * @author 刘健 <coder.liu@qq.com>
 */
class AcquisitionCommand extends BaseCommand
{

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 获取程序名称
        $this->programName = Input::getCommandName();
    }

    /**
     * 获取服务
     * @return TaskExecutor
     */
    public function getTaskService()
    {
        return create_object(
            [
                // 类路径
                'class'         => 'mix\task\TaskExecutor',
                // 服务名称
                'name'          => "mix-crontab: {$this->programName}",
                // 执行模式
                'mode'          => \mix\task\TaskExecutor::MODE_ACQUISITION,
                // 中进程数
                'centerProcess' => 5,
                // 右进程数
                'rightProcess'  => 1,
                // POP退出等待时间 (秒)
                'popExitWait'   => 3,
            ]
        );
    }

    // 执行任务
    public function actionExec()
    {
        // 预处理
        parent::actionExec();
        // 启动服务
        $service = $this->getTaskService();
        $service->on('CenterStart', [$this, 'onCenterStart']);
        $service->on('RightStart', [$this, 'onRightStart']);
        $service->start();
        // 返回退出码
        return ExitCode::OK;
    }

    // 中进程启动事件回调函数
    public function onCenterStart(CenterProcess $worker)
    {
        // 进程编号，可根据该编号为进程设置不同的任务
        $number = $worker->number;
        // 循环执行任务
        for ($j = 0; $j < 16000; $j++) {
            // 采集消息
            // ...
            $data = '';
            // 将消息推送给右进程去入库，push有长度限制 (https://wiki.swoole.com/wiki/page/290.html)
            $worker->push($data);
        }
        
    }

    // 右进程启动事件回调函数
    public function onRightStart(RightProcess $worker)
    {
        // 模型内使用长连接版本的数据库组件，这样组件会自动帮你维护连接不断线
        $tableModel = new \apps\common\models\TableModel();
        // 循环执行任务
        for ($j = 0; $j < 16000; $j++) {
            // 从进程队列中抢占一条消息
            $data = $worker->pop();
            if (empty($data)) {
                continue;
            }
            try {
                // 将采集的消息存入数据库
                // ...
            } catch (\Exception $e) {
                // 回退数据到消息队列
                $worker->fallback($data);
                // 休息一会，避免 cpu 出现 100%
                sleep(1);
                // 抛出错误
                throw $e;
            }
        }
    }

}
