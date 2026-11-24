<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <title>修改密碼</title>
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
            background-color: rgba(0, 0, 0, 0.8);
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
            background-color: #f8d7da;
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
        <?php
        if (isset($_POST["change_password"])) {
            $email = $_POST["email"];
            $old_password = $_POST["old_password"];
            $new_password = $_POST["new_password"];
            $confirm_password = $_POST["confirm_password"];

            require_once "database.php";

            // 使用預處理語句以防止 SQL 注入
            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                // 比較舊密碼是否匹配
                if (password_verify($old_password, $user["password"])) {
                    // 檢查新密碼和確認密碼是否匹配
                    if ($new_password === $confirm_password) {
                        // 將新密碼進行處理
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        // 更新數據庫中的密碼
                        $sql = "UPDATE users SET password = ? WHERE email = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('ss', $hashed_password, $email);
                        if ($stmt->execute()) {
                            echo "<div class='alert alert-success'>密碼已成功修改</div>";
                            // 重定向到 index.php
                            header("Location: index.php");
                            exit();
                        } else {
                            echo "<div class='alert alert-danger'>更新密碼時出錯</div>";
                        }
                    } else {
                        echo "<div class='alert alert-danger'>新密碼和確認密碼不一致</div>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>舊密碼輸入錯誤</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>用戶不存在</div>";
            }
        }
        ?>
        <form action="change-password.php" method="post">
            <div class="form-group">
                <input type="email" placeholder="電子郵件" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <input type="password" placeholder="舊密碼" name="old_password" class="form-control" required>
            </div>
            <div class="form-group">
                <input type="password" placeholder="新密碼" name="new_password" class="form-control" required>
            </div>
            <div class="form-group">
                <input type="password" placeholder="確認新密碼" name="confirm_password" class="form-control" required>
            </div>
            <div class="form-btn">
                <input type="submit" value="修改密碼" name="change_password" class="btn btn-primary">
                <a href="index.php" class="float-end">返回首頁</a>
            </div>
        </form>
    </div>
</body>

</html>
