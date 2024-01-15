<?php
    define('R_DIR', __DIR__ . '/');
    define('POLLING_FILE', __FILE__);

    require_once(R_DIR . "/engine/engine.php");

    $lock = new ProcLock;

    if($lock->isLocked() && !isset($argv[1]))
        die;

    if(!$lock->setLock())
        die('Cannot lock. Aborting');

    Token::$val = '';
    Token::$val_group = '';

    define('GROUP_ID', 12345);
    define('BOT_ID', -GROUP_ID);
    define('OWNER_ID', 12345);

    $TOP_ADMINS = $ADMINS = [OWNER_ID, BOT_ID];

    $commander = new BotCommander();
    //$banManager = new BanManager();
    $userManager = new UserManager();
    $mafiaGame = new MafiaGameLogic();
    //$chatHandler = new ChatHandler();
    $pmHandler = new PmHandler();
    //$likeMgr = new ChatLikeManager();
    //$moderManager = new ModeratorManager();

    $workStatus = true;
    $server = '';

    $LongPoll = curl("https://api.vk.com/method/groups.getLongPollServer?v=" . VK_API_VERSION . "&group_id=" . GROUP_ID . "&access_token=" . Token::$val_group);

    if (!$LongPoll) {
        echo 'Cannot get long polling server';
        $workStatus = false;
    }

    if(!isset($LongPoll['response'])) {
        var_dump($LongPoll);
    }

    $LongPoll = $LongPoll['response'];

    if (!isset($LongPoll['server'])) {
        echo 'No start response';
        var_dump($LongPoll);
        sleep(5);
        restartBot();
    }

    $server = $LongPoll["server"];
    $key = $LongPoll["key"];
    $lastTs = $LongPoll["ts"];
    $lastMessageId = 0;
    $i = 0;

    $mafiaGame->loadGame(MAIN_MAFIA_CHAT, true);

    while($workStatus)  {
        if($lock->needLock()) {
            $lock->setLock();
            //$banManager->checkReturnUnbans();
            //$likeMgr->clearExpired();
        }

        $LongPoll = json_decode($text = file_get_contents($server."?act=a_check&key=".$key."&ts=". $lastTs ."&wait=10&mode=" . (2+8)), true);

        if (!$LongPoll) {
            sleep(3);
            continue;
        }

        if (!isset($LongPoll['updates'])) {
            if (isset($LongPoll['failed'])) {
                if ($LongPoll['failed'] == 1) {
                    $lastTs = $LongPoll['ts'];
                    continue;
                } else
                    restartBot();
            } else {
                var_dump($LongPoll);
                sleep(3);
                restartBot();
            }
        }

        $lastTs = $LongPoll["ts"];

        foreach ($LongPoll['updates'] as $update) {
            if($update['type'] != 'message_new')
                continue;

            $message = $update['object'];

            if($message['from_id'] == BOT_ID || $message['out'] == 1)
                continue;

            /*if(isset($message['action']) && isset($message['action']['member_id']) && in_array($message['action']['type'], ['chat_invite_user', 'chat_invite_user_by_link'])) {
                $ban = $banManager->getBanned($message['action']['member_id'], normalizeChatId($message['peer_id']));

                if($ban)
                    $commander->removeChatUsers(normalizeChatId($message['peer_id']), $message['action']['member_id']);

                continue;
            }*/

            if($message['peer_id'] > 2000000000)
                //$chatHandler->handle($message);
                $mafiaGame->handleMafiaChat(normalizeChatId($message['peer_id']), $message['from_id'], $message, $message['from_id']==OWNER_ID);
            else
                $pmHandler->handle($message);
        }
    }

    restartBot();
