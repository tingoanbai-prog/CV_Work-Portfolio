<?php
/* 登出：session 有兩部分，兩邊都要清掉
 *   伺服器端：存著 user、user_id、round 等資料 → session_destroy() 刪除
 *   瀏覽器端：PHPSESSID cookie（只存一組 ID） → setcookie() 設成過期來刪除 */
session_start();

$_SESSION = [];   // 1. 清空本次執行中的 session 變數

// 2. 刪除瀏覽器的 session cookie：用相同名稱與參數，把到期時間設在過去
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

session_destroy();   // 3. 刪除伺服器上的 session 資料

header("Location: login.php");
exit(); // 跳轉後立刻停止執行
