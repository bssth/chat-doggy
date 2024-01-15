<?php

/**
 * Class BotCommander
 */
class BotCommander {

    /**
     * @param $userId
     * @param $message
     * @return mixed
     */
    function sendPm($userId, $message) {
        return $this->vkGroupRequest('messages.send', 'user_id=' . urlencode($userId) . "&message=".urlencode($message));
    }

    /**
     * @param $chatId
     * @param $message
     * @return mixed
     */
    function chatMessage($chatId, $message) {
        return $this->vkGroupRequest('messages.send', 'peer_id=' .($chatId+2000000000) . "&message=".urlencode($message));
    }

    /**
     * @param $chatId
     * @param $message
     * @return mixed
     */
    function groupChatMessage($chatId, $message) {
        $this->chatMessage($chatId, $message);
    }

    /**
     * @param $userId
     * @param $chatId
     * @return mixed
     */
    function chatBan($userId, $chatId) {
        return $this->vkGroupRequest('messages.removeChatUser', 'chat_id=' . $chatId . '&user_id=' . $userId);
    }

    /**
     * @param $chatId
     * @param $title
     * @return mixed
     */
    public function setChatTitle($chatId, $title) {
        return $this->vkGroupRequest('messages.editChat', 'chat_id=' . $chatId . '&title=' . urlencode($title));
    }

    /**
     * @param $chatId
     * @param $message
     * @return mixed
     */
    public function pinMessage($chatId, $message) {
        return $this->vkGroupRequest('messages.pin', 'peer_id=' . (2000000000+$chatId) . '&message_id=' . $message);
    }

    /**
     * @param $userId
     * @param $message
     * @param $attachment
     */
    function sendPmAttachment($userId, $message, $attachment) {
        $this->vkGroupRequest('messages.send', 'user_id=' . urlencode($userId) . "&message=".urlencode($message) . '&attachment=' . urlencode($attachment));
    }

    /**
     * @param $chatId
     * @param $message
     * @param $attachment
     * @return mixed
     */
    public function sendChatAttachment($chatId, $message, $attachment) {
        return $this->vkGroupRequest('messages.send', 'chat_id=' .$chatId . "&message=".urlencode($message) . '&attachment=' . urlencode($attachment));
    }

    /**
     * @param $chatId
     * @param string $fields
     * @return mixed
     */
    public function getChatUsers($chatId, $fields = 'nickname, domain, screen_name, sex, bdate, city, country, timezone, photo_50, photo_100, photo_200_orig, has_mobile, online, counters') {
        $res = $this->vkGroupRequest('messages.getConversationMembers', 'peer_id=' . (2000000000+$chatId) . '&fields=' . $fields);
        return $res['response'];
    }

    /**
     * @return mixed
     */
    public function getChats() {
        $res = $this->vkGroupRequest('messages.getConversations', 'count=200');
        return $res['response'];
    }

    /**
     * @param $chatId
     * @param $userId
     * @return mixed
     */
    public function removeChatUsers($chatId, $userId) {
        return $this->vkGroupRequest('messages.removeChatUser', 'chat_id=' . $chatId . '&user_id=' . $userId);
    }

    /**
     * @param $chatId
     * @param $userId
     * @return mixed
     */
    public function addChatUser($chatId, $userId) {
        static $convert = [
            2 => 1,
            3 => 2,
            4 => 184
        ];

        if(!isset($convert[$chatId]))
            return false;

        return $this->vkRequest('messages.addChatUser', 'chat_id=' . $convert[$chatId] . '&user_id=' . $userId);
    }

    /**
     * @param $chatId
     * @param $offset
     * @param int $count
     * @return mixed
     */
    public function getChatHistory($chatId, $offset, $count = 200) {
        $res = $this->vkGroupRequest('messages.getHistory', 'user_id=' . (2000000000 + $chatId) . '&offset=' . $offset . '&count=' . $count);
        return $res['response'];
    }

    /**
     * @param $chatId
     * @return mixed
     */
    public function leaveChat($chatId) {
        return $this->removeChatUsers($chatId, BOT_ID);
    }

    /**
     * @param $method
     * @param $body
     * @return mixed
     */
    protected function vkGroupRequest($method, $body) {
        return curl("https://api.vk.com/method/" . $method, $body . "&v=" . VK_API_VERSION . "&access_token=" . Token::$val_group);
    }

    /**
     * @param $method
     * @param $body
     * @return mixed
     */
    protected function vkRequest($method, $body) {
        return curl("https://api.vk.com/method/" . $method, $body . "&v=" . VK_API_VERSION . "&access_token=" . Token::$val);
    }

    /**
     * @param $users
     * @param $fields
     * @return mixed
     */
    public function getUsersInfo($users, $fields = "nickname, domain, screen_name, sex, bdate, city, country, timezone, photo_50, photo_100, photo_200_orig, has_mobile, online, counters") {
        $res = $this->vkGroupRequest('users.get', 'user_ids=' . implode(',', $users) . '&fields=' . $fields);
        return isset($res['response']) ? $res['response'] : null;
    }

    /**
     * @param $messageIds
     * @return mixed
     */
    public function getMessagesByIds($messageIds) {
        $res = $this->vkRequest('messages.getById', 'message_ids=' . implode(',', $messageIds));
        return $res['response'];
    }

}

/**
 * @param $url
 * @param null $data
 * @return mixed
 */
function curl($url, $data = null) {
    $cUrl = curl_init( $url );
    curl_setopt($cUrl, CURLOPT_URL, $url);
    curl_setopt($cUrl,CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($cUrl,CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($cUrl, CURLOPT_USERAGENT, 'Opera/9.80 (Windows NT 5.1; U; ru) Presto/2.7.62 Version/11.01');
    curl_setopt($cUrl,CURLOPT_FOLLOWLOCATION, true);
    if (isset($data)) {
        curl_setopt($cUrl, CURLOPT_POST, 1);
        if (is_array($data))
            $data = http_build_query($data);
        curl_setopt($cUrl, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec( $cUrl );
    curl_close( $cUrl );
    print("{$url} => " . $response);
    return json_decode($response , true);
}