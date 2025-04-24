<?php
// 移除顶部的常量缓存，因为可能导致冲突
// $API_URL = API_URL;
// $CHANNEL_ID = CHANNEL_ID;

require_once 'config.php';  // 引入配置文件
require_once 'modules/sousuo.php';  // 引入搜索模块
require_once 'modules/message.php';  // 引入消息模块
require_once 'modules/dingyue.php';  // 引入订阅模块
require_once 'modules/tougao.php';  // 确保引入投稿模块

// 移除输出缓冲，因为位置不正确
// ob_start();

$content = file_get_contents("php://input");

$update = json_decode($content, true, 512, JSON_BIGINT_AS_STRING);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit;
}

// 数据库连接
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        throw new Exception("数据库连接失败: " . $mysqli->connect_error);
    }
} catch (Exception $e) {
    exit;
}

// 添加搜索结果缓存
$searchCache = [];
// 设置缓存过期时间（秒）
$cacheExpiry = 3600; // 1小时

/**
 * 获取群组创建者ID
 * 
 * @param int $chatId 群组ID
 * @return int|null 返回创建者ID，如果未找到返回 null
 */
function getGroupCreatorId($chatId) {
    // 使用静态变量缓存结果，避免重复请求
    static $creatorCache = [];
    if (isset($creatorCache[$chatId])) {
        return $creatorCache[$chatId];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . "getChatAdministrators?chat_id=$chatId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return null; // 请求失败
    }
    
    $admins = json_decode($response, true);
    
    if ($admins["ok"]) {
        foreach ($admins["result"] as $admin) {
            if ($admin["status"] == "creator") {
                // 缓存结果
                $creatorCache[$chatId] = $admin["user"]["id"];
                return $admin["user"]["id"];
            }
        }
    }
    
    // 缓存空结果
    $creatorCache[$chatId] = null;
    return null;
}

function getSubscriptionLink($chatId, $mysqli) {
    static $linkCache = [];
    
    if (isset($linkCache[$chatId])) {
        return $linkCache[$chatId];
    }
    
    $stmt = $mysqli->prepare("SELECT custom_link FROM group_subscriptions WHERE group_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $stmt->bind_result($customLink);
    $stmt->fetch();
    $stmt->close();
    
    // 如果没有匹配到订阅链接，使用默认链接
    if (empty($customLink)) {
        $customLink = "https://t.me/" . ltrim(CHANNEL_ID, '@'); // 使用默认的频道链接
    }
    
    $linkCache[$chatId] = $customLink;
    return $customLink;
}

// 添加缓存搜索结果的函数
function getCachedSearchResults($query, &$searchCache, $cacheExpiry) {
    // 检查缓存中是否有该查询的结果
    if (isset($searchCache[$query]) && (time() - $searchCache[$query]['timestamp'] < $cacheExpiry)) {
        return $searchCache[$query]['results'];
    }
    
    // 如果缓存中没有或已过期，执行新的搜索
    try {
        $results = search($query);
        // 将结果存入缓存
        $searchCache[$query] = [
            'results' => $results,
            'timestamp' => time()
        ];
        return $results;
    } catch (Exception $e) {
        error_log("搜索错误: " . $e->getMessage());
        return ['error' => '搜索过程中出现错误，请稍后再试。'];
    }
}

// 处理接收到的消息
if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $messageText = $update["message"]["text"];
    $userId = $update["message"]["from"]["id"];
    $chatType = $update["message"]["chat"]["type"];

    // 检查用户是否允许发送消息
    if (!isUserAllowedInGroup($userId, $chatId)) {
        // 删除未订阅用户的消息
        deleteMessage($chatId, $update["message"]["message_id"]);
        
        $reply = "⚠️请先订阅下方频道，才能发送消息！找资源发送:搜XXX";
        
        if ($chatType == "private") {
            // 使用默认的订阅链接
            $defaultLink = "https://t.me/" . ltrim(CHANNEL_ID, '@');
        } else if ($chatType == "group" || $chatType == "supergroup") {
            // 从数据库中获取订阅链接
            $customLink = getSubscriptionLink($chatId, $mysqli);
        }

        $replyMarkup = json_encode([
            "inline_keyboard" => [
                [
                    ["text" => "👉 点击订阅频道 👈", "url" => $customLink ?? $defaultLink]
                ]
            ]
        ]);
        
        // 直接调用函数，不使用批量处理
        sendMessage($chatId, $reply, $replyMarkup);
        return;
    }

    // 处理自定义命令
    if ($messageText === "/link") {
        sendMessage($chatId, LINK_COMMAND_RESPONSE);
        return;
    }

    if ($messageText === "/sou") {
        sendMessage($chatId, SOU_COMMAND_RESPONSE);
        return;
    }

    if ($messageText === "/info") {
        sendMessage($chatId, INFO_COMMAND_RESPONSE);
        return;
    }

    if ($messageText === "/tou") {
        sendMessage($chatId, TOU_COMMAND_RESPONSE);
        return;
    }

    // 处理邀请码请求
    if ($messageText === "邀请码") {
        sendMessage($chatId, INVITATION_CODE_RESPONSE);
        return;
    }

    // 处理 /start 命令
    if (preg_match("/^\/start(?: (.+))?$/", $messageText, $matches)) {
        $startData = isset($matches[1]) ? $matches[1] : null;
        
        if ($startData === "link") {
            // 如果是订阅链接跳转，只发送设置订阅链接的信息
            sendMessage($chatId, "请发送需要设置的订阅链接。");
        } else {
            // 否则，发送欢迎信息
            sendMessage($chatId, START_COMMAND_RESPONSE);
        }
    }
    
    // 检查消息是否为 "订阅设置"
    if ($messageText === "订阅设置") {
        $creatorId = getGroupCreatorId($chatId);

        if ($creatorId == $userId) {
            // 使用事务处理多个数据库操作
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare("INSERT INTO group_subscriptions (group_id, creator_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE creator_id = VALUES(creator_id)");
                $stmt->bind_param("ii", $chatId, $creatorId);
                $stmt->execute();
                $stmt->close();
                
                $mysqli->commit();
                
                // 允许创建者设置自定义订阅链接
                $botUsername = BOT_USERNAME; // 在 config.php 中定义机器人的用户名
                $replyMarkup = json_encode([
                    "inline_keyboard" => [
                        [
                            ["text" => "设置自定义订阅链接", "url" => "https://t.me/$botUsername?start=link"]
                        ]
                    ]
                ]);
                sendMessage($chatId, "请点击下方按钮设置自定义订阅链接：", $replyMarkup);
            } catch (Exception $e) {
                $mysqli->rollback();
                // 处理错误...
                sendMessage($chatId, "设置过程中出现错误，请稍后再试。");
            }
        } else {
            sendMessage($chatId, "只有群组创建者可以设置自定义订阅链接。");
        }
    }

    // 检查用户是否发送了订阅链接
    if (preg_match("/^https:\/\/t\.me\/[a-zA-Z0-9_]+$/", $messageText)) {
        // 验证链接格式正确
        $stmt = $mysqli->prepare("UPDATE group_subscriptions SET custom_link = ? WHERE creator_id = ?");
        $stmt->bind_param("si", $messageText, $userId);
        $stmt->execute();
        $stmt->close();

        sendMessage($chatId, "你的订阅链接已设置好！");
    } else if (preg_match("/^https?:\/\/.+$/", $messageText)) {
        // 链接格式不正确
        sendMessage($chatId, "你输入的订阅链接不正确，格式如：https://t.me/xxxxxx");
    }

    // 处理投稿内容
    if (preg_match("/^(.+)==(.+)==(https?:\/\/.+)$/", $messageText, $matches)) {
        $playType = trim($matches[1]);
        $playName = trim($matches[2]);
        $playUrl = trim($matches[3]);
        $drive = detectDriveType($playUrl);

        // 调用投稿模块存储数据
        $submissionResult = storeSubmission($playType, $playName, $playUrl, $userId, $drive);
        if ($submissionResult) {
            sendMessage($chatId, "投稿成功！内容已进入审核状态。", null);
        } else {
            sendMessage($chatId, "投稿失败！请检查资源类型是否正确。", null);
        }
        return;
    }

    // 处理 "搜xxx" 消息
    if (strpos($messageText, "搜") === 0) {  // 检查是否以"搜"开头
        $query = substr($messageText, 3);  // 直接使用 substr 提取查询内容
        
        if (!empty($query)) {
            // 使用缓存函数获取搜索结果
            $results = getCachedSearchResults($query, $searchCache, $cacheExpiry);
            
            // 检查是否有错误消息
            if (isset($results['error'])) {
                sendMessage($chatId, $results['error']);
                return;
            }            
            
            // 判断是否返回了没有找到结果的 JSON
            if (isset($results['message']) && $results['message'] === '没有找到剧目') {
                $reply = "
                🔔：抱歉！ 暂未找到相关资源，请查看下方原因：
                
            1，请尝试其他的关键词进行搜索！
                
            2，确认搜索的不是违法违规内容！
                
            3，资源可能暂时未收录敬请关注！";
                
                // 设置广告的键盘按钮
                $advertisements = ["inline_keyboard" => []];

                if (SHOW_AD1) { // 检查是否显示 AD1
                    $advertisements["inline_keyboard"][] = [AD1];
                }
                if (SHOW_AD2) { // 检查是否显示 AD2
                    $advertisements["inline_keyboard"][] = [AD2];
                }
                if (SHOW_AD3) { // 检查是否显示 AD3
                    $advertisements["inline_keyboard"][] = [AD3];
                }

                $advertisements = json_encode($advertisements);

                sendMessage($chatId, $reply, $advertisements);
            } elseif (is_array($results) && !empty($results)) {
                $pageData = generatePage($results, 1, $query);
                $replyMarkup = json_encode(["inline_keyboard" => [$pageData["keyboard"]]]);
                sendMessage($chatId, $pageData["text"], $replyMarkup);
            } else {
                $reply = "未找到相关结果，请重新搜索。";
                sendMessage($chatId, $reply);
            }
        } else {
            $reply = "请输入有效的搜索关键词。";
            sendMessage($chatId, $reply);
        }
    }
} elseif (isset($update["callback_query"])) {
    // 处理回调查询
    $callbackQuery = $update["callback_query"];
    $callbackId = $callbackQuery["id"];
    $chatId = $callbackQuery["message"]["chat"]["id"];
    $callbackData = $callbackQuery["data"];

    if ($callbackData === "set_custom_link") {
        // 发送提示信息
        $userId = $callbackQuery["from"]["id"];
        sendMessage($userId, "请发送需要设置的订阅链接。");
        answerCallbackQuery($callbackId, "请发送需要设置的订阅链接。");
    }

    if (preg_match("/^page_(\d+)\|(.*)$/", $callbackData, $matches)) {
        $page = intval($matches[1]);
        $keyword = $matches[2];

        // 使用缓存函数获取搜索结果
        $results = getCachedSearchResults($keyword, $searchCache, $cacheExpiry);
        
        if (is_array($results) && !empty($results)) {
            $pageData = generatePage($results, $page, $keyword);
            $replyMarkup = json_encode(["inline_keyboard" => [$pageData["keyboard"]]]);
            editMessage($chatId, $callbackQuery["message"]["message_id"], $pageData["text"], $replyMarkup);
            answerCallbackQuery($callbackId);
        } else {
            answerCallbackQuery($callbackId, "未找到相关结果，请重新搜索。", true);
        }
    }
}

// 发送消息函数
function sendMessage($chatId, $text, $replyMarkup = null) {
    $ch = curl_init();
    $postFields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $postFields['reply_markup'] = $replyMarkup;
    }
    
    curl_setopt($ch, CURLOPT_URL, API_URL . "sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// 编辑消息函数
function editMessage($chatId, $messageId, $text, $replyMarkup) {
    $ch = curl_init();
    $postFields = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
        'reply_markup' => $replyMarkup
    ];
    
    curl_setopt($ch, CURLOPT_URL, API_URL . "editMessageText");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// 回答回调函数
function answerCallbackQuery($callbackQueryId, $text = "", $showAlert = false) {
    $ch = curl_init();
    $postFields = [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => $showAlert ? "true" : "false"
    ];
    
    curl_setopt($ch, CURLOPT_URL, API_URL . "answerCallbackQuery");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// 关闭数据库连接
$mysqli->close();

// ob_end_flush();
?>
