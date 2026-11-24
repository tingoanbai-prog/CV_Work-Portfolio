<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit(); // header() 只是告訴瀏覽器要跳轉，PHP 會繼續執行，所以要立刻停止
}

/* 處理登入表單：必須放在任何 HTML 輸出之前，
 * header() 與 session_regenerate_id() 才能正常送出 HTTP 標頭（標頭一定要比內容先送）。 */
$loginError = "";   // 登入失敗時的訊息，在下方表單上方的原位置顯示
if (isset($_POST["login"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    require_once "database.php";

    // prepared statement：SQL 結構與使用者輸入分開送出，輸入只會被當成值，防止 SQL Injection
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        die("喔喔...我們正在除蟲");
    }
    mysqli_stmt_bind_param($stmt, "s", $email);   // "s" = 一個字串參數
    mysqli_stmt_execute($stmt);
    //taking the email values from sql into the array
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_array($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if ($user) {
        // compare if the passwords match
        if (password_verify($password, $user["password"])) {
            // 登入成功：換一組新的 session ID，並作廢舊的（true = 刪除舊 session 檔）
            // 防止 Session Fixation：攻擊者若事先知道舊 ID，登入後也無法沿用
            session_regenerate_id(true);
            $_SESSION["user"] = "yes";
            $_SESSION["user_id"] = $user["id"];
            // 清除舊的本局資料，登入後一定會重新選難度（避免沿用前一位使用者的局）
            unset($_SESSION["round"]);
            header("Location: index.php");
            die();
        } else {
            $loginError = "<div class='alert alert-danger'>請確認密碼是否輸入正確</div>";
        }
    } else {
        $loginError = "<div class='alert alert-danger'>此電子郵件尚未註冊</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>登入</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: url('asset/wp1827444.jpg') no-repeat center center fixed;
            /* 添加背景图片 */
            background-size: cover;
            /* 背景图片覆盖整个页面 */
            margin: 0;
            font-family: 'Arial', sans-serif;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 50px;
            box-shadow: rgba(100, 100, 100, 0.1) 0px 5px 25px 10px;
        }

        .form-group {
            margin-top: 15px;
            margin-bottom: 15px;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
            margin: 0;
            font-family: 'Arial', sans-serif;
        }

        /* 容器樣式 */
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 50px;
            background-color: rgba(0, 0, 0, 0.87);
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        /* 標題樣式 */
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: white;
            font-size: 2em;
            font-weight: bold;
        }

        /* 表單組樣式 */
        .form-group {
            margin-top: 15px;
            margin-bottom: 15px;
            
        }

        /* 輸入框樣式 */
        .form-group input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;/
        }
        .gamename{
            color: blue;
            font-size: 40px;
            text-align: center;
        }
        /* 按鈕和連結樣式 */
        .form-btn {
            display: flex;
            flex-direction: column;
            margin-top: 20px;
        }

        .form-btn input[type="submit"] {
            padding: 10px;
            font-size: 16px;
            color: #fff;
            background-color: #1d428a;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .form-btn input[type="submit"]:hover {
            background-color: #c8102e;
        }

        .form-btn a {
            color: white;
            text-decoration: none;
            margin-top: 10px;
            text-align: center;
            font-weight: bold;
        }

        .form-btn a:hover {
            text-decoration: underline;
        }

        /* 警告訊息樣式 */
        .alert {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            color: white;
            background-color: #f74e5c;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }

        /* 其他樣式 */
        .float-end {
            color: white;
            text-align: right;
            display: block;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php echo $loginError; // 登入失敗訊息（在檔案最上方產生） ?>
        <form action="login.php" method="post">
            <div class="gamename">NBA Fantasy</div>
            <div class="form-group">
                <input type="email" placeholder="電子郵件" name="email" class="form-control">
            </div>
            <div class="form-group">
                <input type="password" placeholder="密碼" name="password" class="form-control">
            </div>
            <div class="form-btn">
                <input type="submit" value="登入" name="login" class="btn btn-primary">
                <p class="float-end">尚未註冊？<a href="registration.php">註冊</a></p>
            </div>
        </form>
    </div>
</body>

</html>
