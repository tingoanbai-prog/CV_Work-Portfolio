<?php

$host_name = "localhost"; // 預設為localhost
$db_user = "root"; // 預設為root
$db_password = ""; // 預設為空密碼
$db_name = "nba_fantasy"; // 你的資料庫名稱

// mysqli_connect() 用於連接到 MySQL 伺服器。它需要四個參數。
// mysqli_connect($host_name, $db_user, $db_password, $db_name)
$conn = mysqli_connect($host_name, $db_user, $db_password, $db_name);

// 檢查連接是否成功
if (!$conn) {
    die("喔喔...看來資料庫有些蟲子...");
}

return $conn;

?>
