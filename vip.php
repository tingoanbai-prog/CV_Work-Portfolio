<?php
// 避免使用者在未登入的情況下進入 vip.php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit(); // 確保重定向後腳本停止執行
}

// 連線資料庫
$mysqli = require __DIR__ . "/database.php";

// 會員等級的規則（預算、能力值數量、傳奇球員）統一寫在 game_config.php 的 TIERS，
// 這裡直接讀取，頁面上的特權說明就會和實際遊戲規則保持一致。
require_once __DIR__ . "/includes/game_config.php";

// 取得使用者的 ID
$user_id = $_SESSION["user_id"];

// 檢查是否為訂閱請求
// 退出 VIP（從 index.php 的選單送出）：從下一局開始生效
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'leave') {
    $stmt = $mysqli->prepare("UPDATE users SET VIP = 0 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 檢查使用者的 VIP 狀態
    $query = "SELECT VIP FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user['VIP'] == 1) {
        // 如果使用者已經是 VIP，顯示提示訊息並重定向回首頁
        echo "<script>alert('您已是VIP!'); window.location.href='index.php';</script>";
        exit();
    }

    // 更新使用者的 VIP 狀態
    $query = "UPDATE users SET VIP = 1 WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 檢查是否更新成功
    if ($stmt->affected_rows === 1) {
        // 更新成功，重定向到首頁
        header("Location: index.php");
    } else {
        // 更新失敗，顯示錯誤訊息
        echo "更新 VIP 狀態失敗。請重試。";
    }
    $stmt->close();
    $mysqli->close();
    exit(); // 確保重定向後腳本停止執行
}

/* 依 TIERS 產生「特權比較」清單：每一項 = [有沒有這項優勢, 顯示文字]
 * 有優勢的項目打 ✔，沒有的打 ✘，讓一般會員與 VIP 的差別一目了然。 */
function tier_features(array $tier): array
{
    return [
        [true,              '每局選 ' . PICK_COUNT . ' 位球員'],
        [true,              '可選現役球員'],
        [$tier['classic'],  $tier['classic'] ? '可選傳奇球員' : '不可選傳奇球員'],
        [true,              '預算 $' . $tier['budget']],
        [true,              '每局顯示 ' . $tier['attrs'] . ' 項能力值（共 ' . count(ATTRIBUTES) . ' 項）'],
    ];
}
$normal = TIERS['default'];
$vip    = TIERS['vip'];
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>加入 VIP</title>
    <style>
        body {
            height: 100vh;
            overflow: hidden;
            font-family: 'Book Antiqua', Palatino, 'Microsoft JhengHei', '微軟正黑體', serif;
            text-align: center;
            margin: 0;
            padding: 0;
            background: url('asset/Basketball_MC10.webp') no-repeat center center fixed;
            background-size: cover;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 90vh;
            color: gold;
            position: relative;
            justify-content: space-between;
            padding-bottom: 60px;
        }

        h1 {
            color: #ffffff;
            font-size: 52px;
            margin-bottom: 40px;
            margin-top: 45px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .features {
            display: flex;
            justify-content: center;
            gap: 20px;
            width: 100%;
        }

        .feature-box {
            width: 360px;
            height: 360px;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.8);
            border: 2px solid;
            border-radius: 20px;
            margin: 30px;
            margin-bottom: 15px;
            margin-top: 5px;
        }

        .feature-box.left {
            border-color: red;
            color: red;
        }

        .feature-box.right {
            border-color: rgba(0, 0, 255, 0.5);
            color: blue;
        }

        .feature-box h2 {
            margin-top: 0;
        }

        .feature-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
            font-size: 20px;
        }

        .feature-box li {
            margin: 10px 0;
        }

        /* VIP 比一般會員多出來的優勢，用粗體標示 */
        .feature-box li.better {
            font-weight: bold;
        }

        .feature-box li.no {
            color: #888;
        }

        .note {
            color: #fff;
            font-size: 16px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
            margin: 0 0 10px;
        }

        .subscribe-form {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .subscribe-form button {
            padding: 15px 15px;
            background-color: #28a745;
            color: #fff;
            font-style: italic;
            font-size: 20px;
            margin-top: 0px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            box-shadow: 0 0 10px 3px;
            animation: glow 1.5s infinite alternate;
        }

        .subscribe-form button:hover {
            animation: none;
            background-color: #28a745;
            box-shadow: 0 0 20px 5px #e1f505;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 10px 3px #28a745;
            }

            to {
                box-shadow: 0 0 20px 5px #e1f505;
            }
        }

        .vip-badge {
            background-color: #ffbb00;
            color: #fff;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
            margin-top: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        h2 {
            font-size: 30px;
        }

        p {
            font-size: 20px;
        }

        .no-thanks-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 8px 16px;
            background-color: red;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>VIP 專屬禮遇！</h1>
        <div class="features">
            <div class="feature-box left">
                <h2><?= htmlspecialchars($normal['label']) ?>會員</h2>
                <ul>
                    <?php foreach (tier_features($normal) as [$has, $text]): ?>
                        <li class="<?= $has ? '' : 'no' ?>"><?= $has ? '✔' : '✘' ?> <?= htmlspecialchars($text) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="feature-box right">
                <h2><?= htmlspecialchars($vip['label']) ?>會員</h2>
                <ul>
                    <?php
                    // 與一般會員逐項比較：文字不同的項目就是 VIP 的額外優勢
                    $normalFeatures = tier_features($normal);
                    foreach (tier_features($vip) as $i => [$has, $text]):
                        $better = $text !== $normalFeatures[$i][1];
                    ?>
                        <li class="<?= $better ? 'better' : '' ?>"><?= $has ? '✔' : '✘' ?> <?= htmlspecialchars($text) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="subscribe-form">
            <form action="vip.php" method="post">
                <p class="note">※ 加入或退出 VIP 都從下一局開始生效</p>
                <button type="submit">>> 立即加入 VIP！ >></button>
            </form>
        </div>
    </div>
    <button id="noThanksBtn" class="no-thanks-btn">不用了，謝謝</button>

    <script>
        var noThanksBtn = document.getElementById("noThanksBtn");

        noThanksBtn.addEventListener("click", function () {
            var confirmation = confirm("確定不加入 VIP 嗎？");
            if (confirmation) {
                window.location.href = "index.php";
            }
        });
    </script>
</body>

</html>
