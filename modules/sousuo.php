<?php
require 'config.php'; // 确保包含配置文件

// sousuo.php
// 搜索函数
function search($query) {
    global $API_TOKEN; // 使用全局变量访问token
    $searchUrl = "" . urlencode($API_TOKEN) . "&text=" . urlencode($query);
    
    // 使用 cURL 代替 file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $searchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 检查响应是否为空或发生错误
    if ($response === false || empty($response) || $error) {
        return ["error" => "服务器出现故障，正在抢修中，请稍后再试！" . ($error ? " 错误信息: $error" : "")];
    }
    
    // 检查HTTP状态码
    if ($httpCode != 200) {
        return ["error" => "服务器返回错误状态码: $httpCode"];
    }
    
    return json_decode($response, true);
}

// 分页生成函数
function generatePage($results, $page, $keyword) {
    $totalItems = count($results);
    $totalPages = ceil($totalItems / ITEMS_PER_PAGE);
    $startIndex = ($page - 1) * ITEMS_PER_PAGE;
    $endIndex = min($startIndex + ITEMS_PER_PAGE, $totalItems);

    $output = "✌搜索结果（第 $page 页，共 $totalPages 页）：\n";
    for ($i = $startIndex; $i < $endIndex; $i++) {
        $item = $results[$i];
         $output .= "<a href=\"" . $item['play_url'] . "\">" . ($i + 1) . ". " . $item['play_type'] . " - " . $item['play_name'] . "</a>\n";
    }

    // 添加广告位
    $output .= "🔔 温馨提示：
【内容搜集于网络，注意分辨真假，资源有效时间短，请及时保存！】";
    if (SHOW_ADVERTISEMENTS) { // 检查是否显示广告
        foreach (ADVERTISEMENTS as $ad) {
            $output .= $ad . "\n";
        }
    }

    $keyboard = [];
    if ($page > 1) {
        $keyboard[] = ["text" => "上一页", "callback_data" => "page_" . ($page - 1) . "|" . $keyword];
    }
    if ($page < $totalPages) {
        $keyboard[] = ["text" => "下一页", "callback_data" => "page_" . ($page + 1) . "|" . $keyword];
    }

    return ["text" => $output, "keyboard" => $keyboard];
}
?>
