<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit(); // header() 只是告訴瀏覽器要跳轉，PHP 會繼續執行，所以要立刻停止
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>註冊</title>
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
        if (isset($_POST['submit'])) {
            // checks if the submit key exists in the $_POST array, when the form is submitted.
            $email = $_POST['email'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            $repeat_password = $_POST['repeat_password'];

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // check the validity of each required field
            // if $errors remain empty then valid; otherwise invalid
            $errors = array();

            if (empty($email)) {
                array_push($errors, '請輸入您的電子郵件');
            }

            // check the validity of email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                array_push($errors, '請確認電子郵件格式');
            }

            if (empty($username)) {
                array_push($errors, '請輸入您的帳號');
            }
            if (empty($password)) {
                array_push($errors, '請輸入您的密碼');
            }

            if ($password !== $repeat_password) {
                array_push($errors, '請確認密碼是否輸入正確');
            }

            require_once "database.php";

            // prepared statement：防止 SQL Injection；只需確認 email 是否存在，因此只取 id
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = mysqli_stmt_init($conn);
            if (!mysqli_stmt_prepare($stmt, $sql)) {
                die("喔喔...我們正在除蟲");
            }
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);              // 取回結果後才能計算筆數
            $row_count = mysqli_stmt_num_rows($stmt);
            mysqli_stmt_close($stmt);                     // 用完即釋放，下方 INSERT 再開新的 statement
            if ($row_count > 0) {
                array_push($errors, "電子郵件已經存在！請用其他電子郵件註冊");
            }
            // 做忘記密碼

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    echo "<div class='alert alert-danger'>$error</div>";
                }
            } else {
                $sql = "INSERT INTO users (username, email, password) VALUES ( ?, ?, ? )";

                // initialize a statement object, below is the code from database.php
                // $conn = mysqli_connect($host_name, $db_user, $db_password, $db_name);
                $stmt = mysqli_stmt_init($conn);

                // prepare and execute a sql query, the output is either true or false
                $prepare_stmt = mysqli_stmt_prepare($stmt, $sql);

                if ($prepare_stmt) {
                    // $mysqli_stmt_bind_param($stmt, $types, ...), "sss" indicates 3 strings
                    mysqli_stmt_bind_param($stmt, "sss", $username, $email, $password_hash);
                    // users.email 有 UNIQUE 限制：兩人同時註冊同一個 email 時（race condition），
                    // 上面的 SELECT 都會通過，由資料庫在寫入時擋下，錯誤代碼 1062 = 重複的值
                    $duplicate = false;
                    try {
                        $ok = mysqli_stmt_execute($stmt);
                    } catch (mysqli_sql_exception $e) {
                        // PHP 8.1 起 mysqli 出錯會拋出例外；只處理重複 email，其他錯誤照常拋出
                        if ($e->getCode() !== 1062) {
                            throw $e;
                        }
                        $ok = false;
                        $duplicate = true;
                    }
                    // PHP 8.1 之前出錯只回傳 false、不拋例外，改用錯誤代碼判斷
                    if (!$ok && mysqli_stmt_errno($stmt) === 1062) {
                        $duplicate = true;
                    }

                    if ($ok) {
                        echo "<div class='alert alert-success'>成功註冊！登入即可遊玩</div>";
                    } elseif ($duplicate) {
                        echo "<div class='alert alert-danger'>電子郵件已經存在！請用其他電子郵件註冊</div>";
                    } else {
                        die("喔喔...我們正在除蟲");
                    }
                } else {
                    die("喔喔...我們正在除蟲");
                }
            }
        }
        ?>
        <form action="registration.php" method="post">
            <div class="form-group">
                <input type="email" class="form-control" name="email" placeholder="電子郵件">
            </div>
            <div class="form-group">
                <input type="text" class="form-control" name="username" placeholder="帳號">
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="password" placeholder="密碼">
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="repeat_password" placeholder="確認密碼">
            </div>
            <div class="form-btn">
                <input type="submit" class="btn btn-primary" name="submit" value="註冊">
                <p class="float-end">已經註冊？<a href="login.php">登入</a></p>
            </div>
        </form>
    </div>
</body>

</html>
