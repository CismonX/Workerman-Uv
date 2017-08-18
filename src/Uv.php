<?php
/**
 * Workerman-Uv
 * 2017 CismonX <admin@cismon.net>
 */
namespace Workerman\Events;
use Workerman\Worker;

class Uv implements EventInterface {

    /**
     * Libuv event loop.
     * @var resource
     */
    protected $_loop;

    /**
     * Read & write events.
     * @var array
     */
    protected $_allEvents = [];

    /**
     * Signals.
     * @var array
     */
    protected $_eventSignal = [];

    /**
     * Timers.
     * @var array
     */
    protected $_eventTimer = [];

    /**
     * Timer id counter.
     * @var int
     */
    protected static $_timerId = 1;

    /**
     * Identifies a socket with both read and write events registered.
     */
    const EV_RW = 3;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->_loop = uv_default_loop();
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = null) {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
                $fd_key = intval($fd);
                //uv_poll_init() can only be called once for a same file descriptor.
                if (!isset($this->_allEvents[$fd_key]))
                    $this->_allEvents[$fd_key][0] = uv_poll_init($this->_loop, $fd);
                $event = $this->_allEvents[$fd_key][0];
                $this->_allEvents[$fd_key][$flag] = $func;
                if (isset($this->_allEvents[$fd_key][self::EV_RW - $flag]))
                    $flag = self::EV_RW;
                //Call uv_poll_start() with both existing flags.
                uv_poll_start($event, $flag, function ($poll, $stat, $ev, $conn) use ($func) {
                    $func($conn);
                });
                break;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                $event = uv_signal_init();
                uv_signal_start($event, function ($ev, $signal) use ($func) {
                    $func($signal);
                }, $fd_key);
                $this->_eventSignal[$fd_key] = $event;
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $event = uv_timer_init();
                $param = [$func, (array)$args, $flag, self::$_timerId];
                $interval = $fd * 1000;
                uv_timer_start($event, $interval, $interval, \Closure::bind(function () use ($param) {
                    $timer_id = $param[3];
                    if ($param[2] === self::EV_TIMER_ONCE) {
                        uv_timer_stop($this->_eventTimer[$timer_id]);
                        unset($this->_eventTimer[$timer_id]);
                    }
                    try {
                        call_user_func_array($param[0], $param[1]);
                    } catch (\Exception $e) {
                        Worker::log($e);
                        exit(250);
                    } catch (\Error $e) {
                        Worker::log($e);
                        exit(250);
                    }
                }, $this, __CLASS__));
                $this->_eventTimer[self::$_timerId] = $event;
                return self::$_timerId++;
            default:
                break;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function del($fd, $flag) {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
                $fd_key = intval($fd);
                if (isset($this->_allEvents[$fd_key][$flag])) {
                    unset($this->_allEvents[$fd_key][$flag]);
                    if (isset($this->_allEvents[$fd_key][self::EV_RW - $flag])) {
                        $func = $this->_allEvents[$fd_key][self::EV_RW - $flag];
                        //Call uv_poll_start() with the remaining flag instead of call uv_poll_stop().
                        uv_poll_start($this->_allEvents[$fd_key][0], self::EV_RW - $flag,
                            function ($poll, $stat, $ev, $conn) use ($func) {
                                $func($conn);
                            }
                        );
                    } else
                        uv_poll_stop($this->_allEvents[$fd_key][0]);
                }
                break;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                if (isset($this->_eventSignal[$fd_key])) {
                    uv_signal_stop($this->_eventSignal[$fd_key]);
                    unset($this->_eventSignal[$fd_key]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                if (isset($this->_eventTimer[$fd])) {
                    uv_timer_stop($this->_eventTimer[$fd]);
                    unset($this->_eventTimer[$fd]);
                }
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loop() {
        uv_run();
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer() {
        foreach ($this->_eventTimer as $event)
            uv_timer_stop($event);
        $this->_eventTimer = [];
    }

    /**
     * {@inheritdoc}
     */
    public function destroy() {
        foreach ($this->_eventSignal as $event)
            uv_signal_stop($event);
    }

    public function getTimerCount() {
        return count($this->_eventTimer);
    }
}