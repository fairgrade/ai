<?php

namespace fairgrade\ai;

require_once(__DIR__ . "/ThreadManager.php");

class ProcessManager
{
    function __construct($command = "")
    {
        if ($command === "") return;
        switch ($command) {
            case "status":
                $this->status();
            case "start":
                $this->start();
            case "restart":
                $this->restart();
            case "stop":
                $this->stop();
            case "kill":
                $this->kill();
            case "wrapper":
                $this->wrapper();
            case "main":
                new ThreadManager;
            default:
                die("ERROR: Invalid Command\n");
        }
    }

    private function getPids()
    {
        $ps = array();
        $ps2 = array();
        $ps3 = array();
        exec("ps aux | grep \"php fairgrade wrapper\"", $ps);
        exec("ps aux | grep \"php fairgrade main\"", $ps);
        foreach ($ps as $line) if (!strpos($line, "grep")) $ps2[] = $line;
        foreach ($ps2 as $line) {
            $line = $this->replace("  ", " ", $line);
            $line = explode(" ", $line);
            $ps3[] = $line[1];
        }
        return $ps3;
    }

    private function replace($search, $replace, $mixed)
    {
        while (strpos($mixed, $search) !== false) $mixed = str_replace($search, $replace, $mixed);
        return $mixed;
    }

    private function status()
    {
        $pids = $this->getPids();
        if (sizeof($pids) === 2) echo ("fairgrade is running... (pids " . implode(" ", $pids) . ")\n");
        elseif (sizeof($pids)) echo ("WARNING; fairgrade is HALF running... (pids " . implode(" ", $pids) . ")\n");
        else echo ("fairgrade is stopped.\n");
    }

    private function start()
    {
        $pids = $this->getPids();
        if (sizeof($pids)) die("ERROR: fairgrade is already running.  Not starting.\n");
        exec("nohup php fairgrade wrapper </dev/null >> " . __DIR__ . "/logs.d/wrapper.log 2>&1 &");
        usleep(10000);
        $this->status();
    }

    private function stop()
    {
        $pids = $this->getPids();
        foreach ($pids as $pid) posix_kill($pid, SIGTERM);
        $this->status();
    }

    private function kill()
    {
        $pids = $this->getPids();
        foreach ($pids as $pid) posix_kill($pid, SIGKILL);
        $this->status();
    }

    private function restart()
    {
        $this->status();
        $this->stop();
        $this->start();
    }

    private function wrapper()
    {
        while (true) {
            passthru("php fairgrade main");
            sleep(1);
        }
    }
}
