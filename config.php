
<?php
// Telegram Bot API Token
define("BOT_TOKEN", ""); // 你的Bot Token
define("API_URL", "https://api.telegram.org/bot" . BOT_TOKEN . "/");
define("BOT_USERNAME", ""); // 替换为您的机器人用户名

// 需要检查的频道 ID 或者频道用户名（不包含 @ 开头）
define("CHANNEL_ID", "@12311111"); // 请填入你需要检查的频道 ID 或用户名

// 设置访问API的token
$API_TOKEN = '8888888888888'; // 需要和资源搜索API接口的token对上

// 每页显示的条目数
define("ITEMS_PER_PAGE", 10); 

// 搜索失败下方广告位
define("SHOW_AD1", true); // 设置为 true 显示 AD1，false 不显示
define("SHOW_AD2", true); // 设置为 true 显示 AD2，false 不显示
define("SHOW_AD3", true); // 设置为 true 显示 AD3，false 不显示
define('AD1', ["text" => "显示广告位置1", "url" => "网址"]);
define('AD2', ["text" => "显示广告位置2", "url" => "网址"]);
define('AD3', ["text" => "显示广告位置3", "url" => "网址"]);

// 搜索成功下方广告位
define("SHOW_ADVERTISEMENTS", true); // 设置为 true 显示广告，false 不显示广告
define("ADVERTISEMENTS", [
    "显示广告位置1<a href='网址'>【点击领取】</a>",
    "显示广告位置1<a href='网址'>【快速收藏】</a>",
    "显示广告位置1<a href='网址'>【立即加入】</a>"
]);

// 数据库连接信息
define("DB_HOST", "localhost");
define("DB_USER", "");
define("DB_PASS", "");
define("DB_NAME", "");

// 自定义命令返回消息
define("START_COMMAND_RESPONSE", "欢迎使用，快搜🔍网盘资源搜索机器人！\n\n搜索资源，请发送：搜XXXXXX\n\n👇更多功能，请点菜单或按下方说明操作👇\n\n1. 点击👉 【 /link 】 设置你的订阅链接。\n2. 点击👉 【 /sou 】 怎么搜索资源。\n3. 点击👉 【 /info 】 资源失效与侵权反馈。\n4. 点击👉 【 /tou  】分享你的网盘资源。");  // 这是 /start 命令的响应信息。
define("LINK_COMMAND_RESPONSE", "请先把机器人添加到群组中，并设置为管理员，然后在群组里发送：订阅设置，点击“设置自定义订阅链接，最后提交订阅链接即可，设置好以后请测试。"); // 这是 /link 命令的响应信息。
define("SOU_COMMAND_RESPONSE", "搜索资源，请发送：搜XXXX"); // 这是 /sou 命令的响应信息。
define("INFO_COMMAND_RESPONSE", "资源失效与侵权，请联系：xxxx@outlook.com，请详细描述问题，3-7个工作日会做回应。"); // 这是 /info 命令的响应信息。
define("INVITATION_CODE_RESPONSE", "🎁 您的专属邀请码：PY8888\n\n此邀请码可用于邀请好友加入我们的资源分享社区，每成功邀请一位新用户，您将获得额外的搜索权限和特殊资源！\n\n邀请方式：让好友添加机器人时输入您的邀请码即可。"); // 这是邀请码的响应信息
define("TOU_COMMAND_RESPONSE", "请按以下格式发送投稿内容：

资源类型==资源名称==资源链接
📌 示例：

电视剧==狂飙==https://pan.baidu.com/xxx


⚠️ 注意事项：

1. 使用双等号（==）分隔，三个内容缺一不可，一次只能投一篇稿子。

2. 资源类型：电视剧,电影,动漫,动画片,纪录片,综艺,教程,短剧,音乐,音频,电子书,系统,软件,游戏,其他！

3. 支持网盘：百度网盘，阿里云盘，夸克网盘，迅雷云盘，天翼网盘等！

4. 投稿成功后将进入审核状态，通过后就可以搜索到投稿内容。"); // 这是/tou 命令的响应信息。

?>
