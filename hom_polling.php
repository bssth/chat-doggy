<?php
error_reporting(0);

$GLOBALS['vk_api_key'] = '';
$vk_group_id = 12345;

$admins = [];

/**
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 */
function error_handler($errno, $errstr, $errfile, $errline)
{
    global $admins;

    foreach($admins as $k => $v)
        send_vk_message($v, 'Тревога! ' . $errstr . ' в '.$errfile.', строка ' . $errline);
}
set_error_handler('error_handler');

/**
 * @param $user
 * @param $text
 * @return mixed
 */
function send_vk_message($user, $text)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL            => 'https://api.vk.com/api.php'
    ]);

    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS,
        'oauth=1&v=5.71&access_token='.$GLOBALS['vk_api_key'].'&method=messages.send&user_id='.$user.
        '&message='.urlencode($text));

    curl_setopt ($curl , CURLOPT_USERAGENT , "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru-RU; rv:1.7.12) Gecko/20050919 Firefox/1.0.7");
    curl_setopt ($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt ($curl, CURLOPT_FAILONERROR, true);
    curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, 1);

    $return = json_decode(curl_exec($curl), true);
    curl_close($curl);
    return $return;
}

// ----------------------------------------------------------------------------------------------------------
if(isset($_GET['debug']) and $_GET['key'] == 'fbeffb7615b2294129fc4f71d716079bfef2bf4a9bfc038054c9f56f9e7c8faa3e1b39a33654f559afdf3') {
    $query = $_GET['debug'];
    $debug = true;
}
elseif(isset($_REQUEST))
    $query = json_decode(file_get_contents('php://input'), true);
else
    die('no_params');

// if($query['secret'] != '45SHJ45HA4WLKJ5VAH4WKLV5HKL')
//    die('bad_secret');

if($query['type'] == 'confirmation')
    die('123123123');
elseif($query['type'] == 'message_new') {
    if($query['object']['peer_id'] > 2000000000)
        die('ok');

    $command = $query['object']['text'];
    $vk_id = $query['object']['from_id'];

    switch($command)
    {
        case 'правила':
            $answer = file_get_contents('./hom/rules.txt');
            break;

        case 'методичка':
            $answer = file_get_contents('./hom/ahelp.txt');
            break;

        case 'админ-команды':
        case 'админ команды':
            $answer = file_get_contents('./hom/acmd.txt');
            break;

        default:
            $answer = file_get_contents('./hom/help.txt');
    }
}
else
    die('ok');

if(isset($debug))
    die($answer);
elseif(isset($answer))
    send_vk_message($vk_id, $answer);

die('ok');