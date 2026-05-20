<?php
/**
 * 验证码生成接口（必须正确存储到Session）
 */
session_start(); // 与login.php共享Session
header("Content-Type: image/png");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// 生成4位随机验证码
$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$code = '';
for ($i = 0; $i < 4; $i++) {
    $code .= $chars[mt_rand(0, strlen($chars) - 1)];
}

// 存储到Session（键必须是'captcha'，与login.php中一致）
$_SESSION['captcha'] = $code;

// 创建图片（简化版，确保能显示）
$image = imagecreate(120, 44);
$bgColor = imagecolorallocate($image, 255, 255, 255); // 白色背景
$textColor = imagecolorallocate($image, 50, 50, 50); // 深灰色文字

$fontSize = 32; 

// 绘制干扰线
for ($i = 0; $i < 3; $i++) {
    $lineColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imageline($image, 0, mt_rand(0, 44), 120, mt_rand(0, 44), $lineColor);
}

// 绘制验证码（不依赖字体，避免路径问题）
imagestring($image, 5, 20, 12, $code, $textColor);

// 输出并销毁图片
imagepng($image);
imagedestroy($image);