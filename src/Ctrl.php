<?php
/**
+----------------------------------------------------------------------
| Daemon and Cli model about php process worker
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
 */

define('START', 'start');
define('STOP', 'stop');
define('RELOAD', 'reload');
define('RESTART','restart');
define('STATUS', 'status');
define('PIPE', 'pipe');
define('ADD','add');
define('REMOVE', 'remove');

define('WORKERFY_VERSION', '1.0.0');

if(!version_compare(phpversion(),'7.1.0', '>=')) {
    write_info("--------------【Warning】php version require > php7.1+ --------------");
    exit(0);
}

if(!version_compare(swoole_version(),'4.4.5','>=')) {
    write_info("--------------【Warning】swoole version require > 4.4.5 --------------");
    exit(0);
}

if(!defined('START_SCRIPT_FILE')) {
    write_info("--------------【Warning】Please define Constans START_SCRIPT_FILE --------------");
    exit(0);
}

if(!defined('PID_FILE')) {
    write_info("--------------【Warning】Please define Constans PID_FILE --------------");
    exit(0);
}

if(!defined('CTL_LOG_FILE')) {
    define('CTL_LOG_FILE', str_replace('.pid', '.log', PID_FILE));
    if(!file_exists(CTL_LOG_FILE)) {
        touch(CTL_LOG_FILE);
        chmod(CTL_LOG_FILE, 0666);
    }
}

if(!defined('STATUS_FILE')) {
    define('STATUS_FILE', str_replace('.pid', '.status', PID_FILE));
    if(!file_exists(STATUS_FILE)) {
        touch(STATUS_FILE);
        chmod(STATUS_FILE, 0666);
    }
}

$command = $_SERVER['argv'][1] ?? START;

$new_argv = $_SERVER['argv'];

$argv_arr = array_splice($new_argv, 2);
unset($new_argv);

array_reduce($argv_arr, function($result, $item) {
    if(in_array($item, ['-d', '-D'])) {
        putenv('daemon=1');
    }else {
        $item = ltrim($item, '-');
        putenv($item);
    }
});

$is_daemon = getenv('daemon') ? true : false;

// 定义是否守护进程模式
defined('IS_DAEMON') or define('IS_DAEMON', $is_daemon);

switch($command) {
    case START :
        start();
        break;
    case STOP :
        stop();
        break;
    case RELOAD :
        reload();
        break;
    case RESTART:
        restart();
        break;
    case STATUS :
        status();
        break;
    case PIPE :
        pipe();
        break;
    case ADD :
        add();
        break;
    case REMOVE :
        remove();
        break;
    default :
        write_info("--------------【Warning】you must use 【start, stop, reload, status, pipe, add, remove】command --------------");
        exit(0);
}

function start() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            unlink(PID_FILE);
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
        if(\Swoole\Process::kill($master_pid, 0)) {
            write_info("--------------【Warning】master process has started, you can not start again --------------");
            exit(0);
        }
    }
    // 通过cli命令行设置worker_num
    $worker_num = (int)getenv('worker_num');
    if(isset($worker_num) && $worker_num > 0) {
        define("WORKER_NUM", $worker_num);
    }

    write_info("--------------【Info】Master && Children process ready to start, please wait a time ...... --------------",'green');

}

function stop() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("--------------【Info】master and children process start to stop, please wait a time --------------",'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 30) {
                break;
            }
            sleep(1);
        }
        write_info("--------------【Info】master and children process has stopped --------------",'green');
    }else {
        write_info("--------------【Warning】pid={$master_pid} 的进程不存在 --------------");
    }
    exit(0);
}

function reload() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGUSR2);
        if($res) {
            write_info("--------------【Info】children process start to reload, please wait a time --------------", 'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 10) {
                break;
            }
            sleep(1);
        }
        write_info("--------------【Info】children process has reloaded --------------", 'green');
    }else {
        write_info("--------------【Warning】pid={$master_pid} 的进程不存在，没法自动reload子进程 --------------");
    }
    exit(0);

}

function restart() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("--------------【Info】master and children process start to stop, please wait a time --------------",'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 30) {
                break;
            }
            sleep(1);
        }
        write_info("--------------【Info】master and children process has stopped --------------",'green');
    }

    write_info("--------------【Info】master and children ready to restart, please wait a time --------------",'green');

}

function status() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }

    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("--------------【Warning】pid={$master_pid} 的主进程不存在，无法进行管道通信 --------------");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    $ctl_pipe_file = getCtlPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe, can not show status --------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'r+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $pipe_msg = json_encode(['status', $ctl_pipe_file, ''], JSON_UNESCAPED_UNICODE);
    if(file_exists($ctl_pipe_file)) {
        unlink($ctl_pipe_file);
    }
    posix_mkfifo($ctl_pipe_file, 0777);
    $ctl_pipe = fopen($ctl_pipe_file, 'w+');
    if(!flock($ctl_pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    stream_set_blocking($ctl_pipe, false);
    \Swoole\Timer::after(3000, function() {
        \Swoole\Event::exit();
    });
    \Swoole\Event::add($ctl_pipe, function() use($ctl_pipe) {
        $msg = fread($ctl_pipe, 8192);
        write_info($msg,'green');
    });
    sleep(1);
    fwrite($pipe, $pipe_msg);
    \Swoole\Event::wait();
    flock($ctl_pipe, LOCK_UN);
    flock($pipe,LOCK_UN);
    fclose($ctl_pipe);
    fclose($pipe);
    unlink($ctl_pipe_file);
    exit(0);
}

function pipe() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }
    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("--------------【Warning】pid={$master_pid} 的主进程不存在，无法进行管道通信 --------------");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe--------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $msg = getenv("msg");
    if($msg) {
        write_info("--------------【Info】start write mseesge to master --------------",'green');
        fwrite($pipe, $msg);
    }else {
        write_info("--------------【Warning】please use pipe -msg=xxxxx --------------");
    }
    fclose($pipe);
    exit(0);
}

function add(int $wait_time = 5) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】 master pid is invalid --------------");
            exit(0);
        }
    }
    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("--------------【Warning】 pid={$master_pid} 的主进程不存在，无法进行管道通信 --------------");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe, can not add process --------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $name = getenv("name");
    $num = getenv('num') ? getenv('num') : 1;
    $pipe_msg = json_encode(['add' , $name, $num], JSON_UNESCAPED_UNICODE);
    if(isset($name)) {
        write_info("--------------【Info】 master process start to create dynamic process, please wait a time(about {$wait_time}s) --------------",'green');
        fwrite($pipe, $pipe_msg);
    }else {
        write_info("--------------【Warning】 please use pipe -name=xxxxx -num=1 --------------");
    }
    flock($pipe, LOCK_UN);
    fclose($pipe);
    sleep($wait_time);
    write_info("--------------【Info】 Dynamic process add successful, you can show status to see --------------", 'green');
    exit(0);
}

function remove(int $wait_time = 5) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】 master pid is invalid --------------");
            exit(0);
        }
    }
    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("--------------【Warning】 pid={$master_pid} 的主进程不存在，无法进行管道通信 --------------");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe, can not remove process --------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $name = getenv("name");
    $num = getenv('num') ?? 1;
    $pipe_msg = json_encode(['remove' , $name, $num], JSON_UNESCAPED_UNICODE);
    if(isset($name)) {
        write_info("--------------【Info】 master process start to remova all dynamic process, please wait a time(about {$wait_time}s) --------------",'green');
        fwrite($pipe, $pipe_msg);
    }else {
        write_info("--------------【Warning】 please use pipe -name=xxxxx --------------");
    }
    fclose($pipe);
    sleep($wait_time);
    write_info("--------------【Info】 All process_name={$name} of dynamic process be removed, you can show status to see --------------", 'green');
    exit(0);
}

function write_info($msg, $foreground = "red", $background = "black") {
    include_once __DIR__.'/EachColor.php';
    // Create new Colors class
    static $colors;
    if(!isset($colors)) {
        $colors = new \Workerfy\EachColor();
    }
    echo $colors->getColoredString($msg, $foreground, $background) . "\n\n";
    if(defined("CTL_LOG_FILE")) {
        if(defined('MAX_LOG_FILE_SIZE')) {
             $max_log_file_size = MAX_LOG_FILE_SIZE;
        }else {
            $max_log_file_size = 2 * 1024 * 1024;
        }
        if(is_file(CTL_LOG_FILE) && filesize(CTL_LOG_FILE) > $max_log_file_size) {
            unlink(CTL_LOG_FILE);
        }
        $log_fd = fopen(CTL_LOG_FILE,'a+');
        $date = date("Y-m-d H:i:s");
        $write_msg = "【{$date}】".$msg."\n\r";
        fwrite($log_fd, $write_msg);
        fclose($log_fd);
    }
}

function getCliPipeFile() {
    $path_info = pathinfo(PID_FILE);
    $path_dir = $path_info['dirname'];
    $file_name = $path_info['basename'];
    $ext = $path_info['extension'];
    $pipe_file_name = str_replace($ext,'pipe', $file_name);
    $pipe_file = $path_dir.'/'.$pipe_file_name;
    return $pipe_file;
}

function getCtlPipeFile() {
    $path_info = pathinfo(PID_FILE);
    $path_dir = $path_info['dirname'];
    $pipe_file_name = 'ctl.pipe';
    $pipe_file = $path_dir.'/'.$pipe_file_name;
    return $pipe_file;
}

/**
 * 是否是在主进程环境中
 * @return bool
 */
function inMasterProcessEnv() {
    $pid = posix_getpid();
    if($pid == MASTER_PID) {
        return true;
    }
    return false;
}

/**
 * 是否是在子进程环境中
 * @return bool
 */
function inChildrenProcessEnv() {
    return !inMasterProcessEnv();
}

/**
 * @return string
 */
function workerfy_version() {
    return WORKERFY_VERSION;
}


