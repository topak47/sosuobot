<?php
// message.php
// 发送文本消息的通用函数
function sendTextMessage($chatId, $text) {
    $url = API_URL . "sendMessage?chat_id=$chatId&text=" . urlencode($text);
    
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
