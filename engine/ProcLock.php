<?php

/**
 * Class ProcLock
 */
class ProcLock
{
    /**
     * @var string
     */
    protected $file = __DIR__ . '/_lock.txt';

    /**
     * @var int
     */
    protected $need = 0;

    /**
     * @return bool
     */
    public function unLock() {
        $this->need = time();
        return unlink($this->file);
    }

    /**
     * @param $procname
     * @return bool
     */
    public function running($procname) {
        exec("pgrep {$procname}", $pids);
        return !empty($pids);
    }

    /**
     * @return bool
     */
    public function isLocked() {
        if(!file_exists($this->file))
            return false;

        $content = file_get_contents($this->file);
        return !(posix_getsid($content) === false);
    }

    /**
     * @return int
     */
    public function setLock() {
        $this->need = time()+100;
        file_put_contents($this->file, $pid = getmypid());
        return $pid;
    }

    /**
     * @return bool
     */
    public function needLock() {
        return $this->need < time();
    }
}