<?php

ini_set("display_errors" , 1);
error_reporting(E_ALL/* & ~E_DEPRECATED*/);
ini_set('display_startup_errors', 1);
set_time_limit(0);

/**
 * Class Token
 */
class Token {
    static $val;
    static $val_group;
}

define("VK_API_VERSION", "5.81");
define("TOP_ADMIN_PRIOROTY", 3);

define("ROOT_DIR", 				__DIR__ . "/../");
define("CLASSES_PATH", 			ROOT_DIR . "classes/");

function restartBot()
{
    global $workStatus;
    print('Got restart signal');
    $workStatus = false;
    $lock = new ProcLock;
    $lock->unLock();
    exec('php5.6 ' . POLLING_FILE . ' > /dev/null &');
    die;
}

/**
 * @param $class
 */
function gAutoload($class)
{
    require_once(__DIR__ . '/' . $class . '.php');
}
spl_autoload_register('gAutoload');

/**
 * @param $id
 * @return int
 */
function normalizeChatId($id) {
    if($id > 2000000000)
        return $id-2000000000;

    return $id;
}

/**
 * @param $link
 * @return bool|null|string
 */
function getUserLink($link) {
    $ind = strrpos($link, '/');
    if ($ind === false)
        return null;
    return substr($link, $ind + 1);
}

/**
 * @param $text
 * @param $part
 * @return bool
 */
function startsWith($text, $part) {
    return (strpos($text, $part) === 0);
}

/**
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 */
function gErrorHandler($errno, $errstr, $errfile, $errline)
{
    global $commander;
    print("{$errstr} in {$errfile}, line {$errline}");

    if($errno == E_DEPRECATED)
        return;

    if(!in_array($errno, [E_WARNING, E_NOTICE, E_DEPRECATED]))
        restartBot();

    if(is_object($commander))
        $commander->sendPm(OWNER_ID, "{$errstr} in {$errfile}, line {$errline}");
}
set_error_handler("gErrorHandler");