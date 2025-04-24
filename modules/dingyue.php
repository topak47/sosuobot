<?php
// dingyue.php

/**
 * 检查用户是否关注指定频道
 * 
 * @param int $userId 用户ID
 * @param string $chatType 聊天类型
 * @param int $chatId 群组ID
 * @return bool 如果用户已订阅返回 true，否则返回 false
 */
function isUserSubscribed($userId, $chatType, $chatId) {
    global $mysqli;

    // 检查是否有自定义订阅链接
    $stmt = $mysqli->prepare("SELECT custom_link FROM group_subscriptions WHERE group_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $stmt->bind_result($customLink);
    $stmt->fetch();
    $stmt->close();

    // 如果有自定义链接，提取频道用户名
    if (!empty($customLink)) {
        $channelUsername = parseChannelUsername($customLink);
    } else {
        // 使用默认频道
        $channelUsername = CHANNEL_ID;  // 从 config.php 中获取默认频道用户名
    }

    // 确保频道用户名以 @ 开头
    if (strpos($channelUsername, '@') !== 0) {
        $channelUsername = '@' . $channelUsername;
    }

    // 记录调试信息
    // file_put_contents('debug_log.txt', "Checking subscription for user $userId in channel $channelUsername\n", FILE_APPEND);

    $url = API_URL . "getChatMember?chat_id=$channelUsername&user_id=$userId";

    // 使用 cURL 代替 file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        // file_put_contents('debug_log.txt', "Failed to get response from API for user $userId\n", FILE_APPEND);
        return false;
    }

    $response = json_decode($response, true);

    // 记录API响应
    // file_put_contents('debug_log.txt', "API response for user $userId: " . print_r($response, true) . "\n", FILE_APPEND);

    if ($response["ok"]) {
        $status = $response["result"]["status"];
        // 检查用户是否是成员、管理员或创建者
        if (in_array($status, ['member', 'administrator', 'creator'])) {
            return true; // 用户已订阅频道
        }
    }

    return false; // 用户未订阅频道
}

/**
 * 从自定义链接中解析频道用户名
 * 
 * @param string $customLink 自定义链接
 * @return string 频道用户名
 */
function parseChannelUsername($customLink) {
    // 假设链接格式为 https://t.me/username
    $parts = parse_url($customLink);
    return ltrim($parts['path'], '/');
}

// 检查用户是否为普通成员且是否订阅频道
function isUserAllowedInGroup($userId, $chatId) {
    $url = API_URL . "getChatMember?chat_id=$chatId&user_id=$userId";
    
    // 使用 cURL 代替 file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        return false;
    }
    
    $response = json_decode($response, true);

    if ($response["ok"]) {
        $member = $response["result"];
        $status = $member["status"];

        // 如果是匿名创建者，直接允许发消息
        if ($status == 'creator' && isset($member["is_anonymous"]) && $member["is_anonymous"] === true) {
            return true;
        }

        // 如果是普通成员，需要检测订阅状态
        if ($status == 'member') {
            return isUserSubscribed($userId, '', $chatId);
        }

        // 非普通成员（管理员、创建者等）直接允许发消息
        return true;
    }

    return false; // 未能获取用户信息，不允许发消息
}

// 删除消息
function deleteMessage($chatId, $messageId) {
    $url = API_URL . "deleteMessage?chat_id=$chatId&message_id=$messageId";
    
    // 使用 cURL 代替 file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    curl_exec($ch);
    curl_close($ch);
}
?>
