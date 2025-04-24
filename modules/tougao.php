<?php

/**
 * 自动识别网盘类型
 * 
 * @param string $url 资源链接
 * @return string 返回网盘类型
 */
function detectDriveType($url) {
    if (strpos($url, 'pan.baidu.com') !== false) {
        return '百度网盘';
    } elseif (strpos($url, 'aliyundrive.com') !== false) {
        return '阿里云盘';
    } elseif (strpos($url, 'quark.cn') !== false) {
        return '夸克网盘';
    } elseif (strpos($url, 'xunlei.com') !== false) {
        return '迅雷云盘';
    } elseif (strpos($url, 'cloud.189.cn') !== false) {
        return '天翼网盘';
    }
    return '未知网盘';
}

/**
 * 存储投稿信息到数据库
 * 
 * @param string $playType 资源类型
 * @param string $playName 资源名称
 * @param string $playUrl 资源链接
 * @param int $user 用户ID
 * @param string $drive 网盘类型
 * @return bool 返回存储结果
 */
function storeSubmission($playType, $playName, $playUrl, $user, $drive) {
    global $mysqli;

    // 验证资源类型是否有效
    $validTypes = ['电视剧', '电影', '动漫', '动画片', '纪录片', '综艺',  '教程', '短剧', '音乐',  '音频', '电子书', '系统',  '软件', '游戏', '其他'];
    if (!in_array($playType, $validTypes)) {
        // file_put_contents('submission_log.txt', "无效资源类型: $playType\n", FILE_APPEND);
        return false;
    }

    // 插入投稿信息到数据库，增加 grade 字段，默认值为 0
    $stmt = $mysqli->prepare("INSERT INTO short_plays (play_type, play_name, play_url, user, drive, state, grade) VALUES (?, ?, ?, ?, ?, 0, 0)");
    if (!$stmt) {
        // file_put_contents('submission_log.txt', "数据库准备失败: " . $mysqli->error . "\n", FILE_APPEND);
        return false;
    }
    
    // 记录绑定参数的日志
    // file_put_contents('submission_log.txt', "绑定参数: 类型=$playType, 名称=$playName, 链接=$playUrl, 用户=$user, 网盘类型=$drive\n", FILE_APPEND);
    
    $stmt->bind_param("sssss", $playType, $playName, $playUrl, $user, $drive);
    $result = $stmt->execute();
    
    if (!$result) {
        // file_put_contents('submission_log.txt', "数据库执行失败: " . $stmt->error . "\n", FILE_APPEND);
    } else {
        // file_put_contents('submission_log.txt', "数据库执行成功: 类型=$playType, 名称=$playName, 链接=$playUrl, 用户=$user, 网盘类型=$drive\n", FILE_APPEND);
    }
    $stmt->close();

    // 调用中间件 API 将数据发送到 so.pan-you.com
    if ($result) {
        sendToMiddleware($playType, $playName, $playUrl, $user, $drive);
    }

    return $result;
}

/**
 * 发送数据到中间件 API
 */
function sendToMiddleware($playType, $playName, $playUrl, $user, $drive) {
    $url = 'https://xxxxx/submit.php'; // 中间件 API 的 URL
    $data = [
        'play_type' => $playType,
        'play_name' => $playName,
        'play_url' => $playUrl,
        'user' => $user,
        'drive' => $drive
    ];

    // 使用 cURL 代替 file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}
?>