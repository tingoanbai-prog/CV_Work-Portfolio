<?php
// 連接到資料庫
$mysqli = require __DIR__ . "/database.php";

// 依積分排行：積分高 → 勝場多 → 場數少（同積分時打得少的較強）→ id（讓順序固定）
$sql = "SELECT username, Points, GP, W, D, L, WinPercent
        FROM users
        ORDER BY Points DESC, W DESC, GP ASC, id ASC";
$result = $mysqli->query($sql);

if (!$result) {
    die("查詢失敗: " . $mysqli->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>排行榜</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="index_style.css">
    <style>
        body {
            background-image: url('asset/f007a1ce8434a02505196f58ec52db5b.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #fff;
        }
        .container {
            background: rgba(0, 0, 0, 0.7);
            padding: 20px;
            border-radius: 10px;
            box-shadow: none !important; /* Ensure shadow is removed */
        }
        h1 {
            color: #FFD700;
        }
        table {
            color: #fff;
        }
        .btn-primary {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #000;
        }
        .btn-primary:hover {
            background-color: #FFA500;
            border-color: #FFA500;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>排行榜</h1>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>帳號</th>
                    <th>積分</th>
                    <th>場數</th>
                    <th>勝</th>
                    <th>和</th>
                    <th>敗</th>
                    <th>勝率</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 0; ?>
                <?php while ($row = $result->fetch_assoc()) : ?>
                    <?php $points = (int)$row['Points']; ?>
                    <tr>
                        <td><?php echo ++$rank; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <!-- 積分可為負數；正數加上 + 號 -->
                        <td><?php echo $points > 0 ? "+$points" : $points; ?></td>
                        <td><?php echo (int)$row['GP']; ?></td>
                        <td><?php echo (int)$row['W']; ?></td>
                        <td><?php echo (int)$row['D']; ?></td>
                        <td><?php echo (int)$row['L']; ?></td>
                        <!-- 勝率 = 勝場 ÷ 總場數（平手算在分母），由 recordMatch() 寫入 -->
                        <td><?php echo number_format($row['WinPercent'] * 100, 2) . '%'; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <form action="index.php" method="post">
            <button type="submit" class="btn btn-primary">返回首頁</button>
        </form>
    </div>
</body>

</html>
