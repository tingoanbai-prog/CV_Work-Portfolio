<?php
/* index.php：唯一的遊戲頁面
 * 只負責「處理請求」與「輸出畫面」；規則的數字在 includes/game_config.php，
 * 規則的做法在 includes/game_logic.php。 */

/* ==================== 1. 登入檢查與引入 ==================== */
// Avoid users getting into index.php before login
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit(); // Ensure the script stops executing after the redirect
}

require_once "database.php";                    // 提供 $conn（mysqli 連線）
require_once __DIR__ . "/includes/game_config.php";  // 遊戲規則的數字（常數）
require_once __DIR__ . "/includes/game_logic.php";   // 遊戲規則的做法（函式）

/* ==================== 2. 處理請求（POST → 重新導向） ==================== */
// 「開新局」：選定難度後才重抽能力欄位與順序（Post/Redirect/Get，避免重新整理時重送表單）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_round'])) {
    $difficulty = $_POST['difficulty'] ?? '';
    // 白名單驗證：只接受 DIFFICULTIES 裡的 key，其他值一律不建立新局
    if (array_key_exists($difficulty, DIFFICULTIES)) {
        // 等級在開新局時決定：局中加入／退出 VIP 從下一局生效
        $_SESSION['round'] = newRound($conn, $difficulty, userTier($conn, (int)$_SESSION['user_id']));
    }
    header("Location: index.php");
    exit();
}

// 「確定陣容」：伺服器端驗證 → 對戰 → 存結果 → 寫入戰績 → 重新導向（PRG）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play'])) {
    $round = $_SESSION['round'] ?? null;

    if ($round === null || !isset(DIFFICULTIES[$round['difficulty'] ?? ''])) {
        $_SESSION['flash'] = '請先選擇難度';
    } elseif (isset($round['result'])) {
        // 一局只能打一次：重新整理或重複送出都會在這裡被擋下（原專案待修第 6 項）
        $_SESSION['flash'] = '這一局已經結束，請開始下一局';
    } else {
        $pool = loadMatchPool($conn);
        $check = validatePicks($round, $_POST['picks'] ?? null, $pool);

        if (!$check['ok']) {
            $_SESSION['flash'] = $check['error'];
        } else {
            $result = playMatch($pool, $check['ids'], $round['difficulty']);

            // 先標記本局已完成，再寫資料庫：
            // 若寫入失敗也不能讓玩家重打（否則可以一直重打到贏）
            $_SESSION['round']['result'] = publicResult($result);
            if (!recordMatch($conn, (int)$_SESSION['user_id'], $result)) {
                $_SESSION['round']['result']['saveFailed'] = true;
            }
        }
    }
    header("Location: index.php");
    exit();
}

/* ==================== 3. 準備畫面資料 ==================== */
// 還沒有本局（登入後第一次進入）：不自動建立，頁面會跳出難度視窗要求選擇
// 加入難度功能前建立的舊局沒有 difficulty，也視為需要選擇
$round = $_SESSION['round'] ?? null;
// 加入等級功能前的舊局沒有 tier，同樣視為需要重新開局
$needDifficulty = $round === null || !isset(DIFFICULTIES[$round['difficulty'] ?? ''])
    || !isset(TIERS[$round['tier'] ?? '']);

// 本局已打完時的結果（只含可公開的資料，見 publicResult()）
$matchResult = $needDifficulty ? null : ($round['result'] ?? null);

// 一次性的提示訊息（例如選人驗證失敗），顯示一次後就清除
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// 準備兩個 board 的資料（還沒選難度時為空）
$boards = $needDifficulty ? ['curr' => [], 'class' => []] : loadBoards($conn, $round);

// 依 session 中的 user_id 查詢使用者名稱（login.php 只存了 user_id，沒有存 username）
$username = "User";
$isVip = false;   // 目前帳號是否為 VIP（決定選單顯示加入或退出）
if (isset($_SESSION["user_id"])) {
    // 使用 prepared statement 避免 SQL injection
    $stmt = mysqli_prepare($conn, "SELECT username, VIP FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $dbUsername, $dbVip);
    if (mysqli_stmt_fetch($stmt)) {
        $username = $dbUsername;
        $isVip = (int)$dbVip === 1;
    }
    mysqli_stmt_close($stmt);
}

// 本局等級的設定（預算、是否顯示傳奇 board）；尚未開局時依帳號目前等級
$tierCfg = TIERS[$needDifficulty ? ($isVip ? 'vip' : 'default') : $round['tier']];

/* ==================== 4. 畫面輸出函式 ==================== */
// 能力值分級顏色（只依畫面上已顯示的數值，不洩漏隱藏資訊）
function levelClass($value): string
{
    if ($value >= 90) return 'lv5';
    if ($value >= 80) return 'lv4';
    if ($value >= 70) return 'lv3';
    if ($value >= 60) return 'lv2';
    return 'lv1';
}

// 輸出一個 board 的表格
function renderBoard(string $title, string $boardKey, array $rows, array $attrs): void
{
    $total = count($rows);
    echo "<section class='board' data-board='$boardKey'>";

    // ---- 標題列：左邊標題＋人數，右邊篩選條件 ----
    echo "<div class='board-head'>";
    echo "<h2 class='board-title'>" . htmlspecialchars($title)
        . "<span class='board-count'>（顯示 <span class='shown'>$total</span> / $total 人）</span></h2>";

    // 篩選選項：價格 + 本局抽中的能力（與表頭同一份清單，不會出現隱藏能力）
    $options = "<option value=''>選項</option><option value='Price'>價格</option>";
    foreach ($attrs as $attr) {
        $options .= "<option value='$attr'>" . htmlspecialchars(ATTRIBUTES[$attr][0]) . "</option>";
    }
    $ops = "<option value='gte'>≥</option><option value='lte'>≤</option><option value='eq'>=</option>";

    echo "<div class='filter'>";
    for ($i = 1; $i <= 2; $i++) {
        echo "<div class='cond'>";
        echo "<select class='f-key' aria-label='篩選選項 $i'>$options</select>";
        echo "<select class='f-op' aria-label='比較方式 $i'>$ops</select>";
        echo "<input type='number' class='f-val' placeholder='數值' aria-label='數值 $i'>";
        echo "</div>";
    }
    echo "<button type='button' class='btn f-apply'>套用</button>";
    echo "<button type='button' class='btn f-clear'>清除</button>";
    echo "<div class='f-msg'></div>";
    echo "</div>";   // .filter
    echo "</div>";   // .board-head

    echo "<div class='table-wrap'>";
    echo "<table class='player-table'>";
    echo "<thead><tr>";
    echo "<th>選取</th>";
    echo "<th class='sortable' data-key='Price'>價格</th>";
    foreach ($attrs as $attr) {
        [$short, $full] = ATTRIBUTES[$attr];
        echo "<th class='sortable' data-key='$attr' title='" . htmlspecialchars($full) . "'>"
            . htmlspecialchars($short) . "</th>";
    }
    echo "</tr></thead><tbody>";

    $typeLabel = $boardKey === 'curr' ? '現役' : '傳奇';

    foreach ($rows as $slot => $p) {
        $price = (int)$p['Price'];

        // 找出本局 12 項能力中的最高值（給左側已選清單用）
        // 用 > 而非 >=：同分時保留先出現的，也就是表格中排在前面的欄位
        $topAttr = null;
        foreach ($attrs as $attr) {
            if ($topAttr === null || $p[$attr] > $p[$topAttr]) {
                $topAttr = $attr;
            }
        }
        $topLabel = htmlspecialchars(ATTRIBUTES[$topAttr][0], ENT_QUOTES);
        $topVal = $p[$topAttr] + 0;

        echo "<tr>";
        echo "<td><input type='checkbox' class='pick' name='picks[]' value='$slot' data-price='$price'"
            . " data-type='$typeLabel' data-top-label='$topLabel' data-top-val='$topVal'></td>";
        echo "<td class='price'>\$$price</td>";
        foreach ($attrs as $attr) {
            // Duncking 是 DECIMAL（例如 87.5），其餘為整數；+0 會去掉多餘的小數 0
            $value = $p[$attr] + 0;
            echo "<td class='" . levelClass($value) . "'>" . htmlspecialchars((string)$value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
    // 全部列都被篩掉時顯示（預設隱藏）
    echo "<p class='empty-msg' hidden>沒有符合條件的球員</p>";
    echo "</div></section>";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NBA FANTASY</title>
    <!-- ?v=檔案修改時間：CSS 一改網址就變，強制瀏覽器重新下載，避免吃到快取的舊版 -->
    <link rel="stylesheet" href="index_style.css?v=<?php echo filemtime(__DIR__ . '/index_style.css'); ?>">
</head>

<body>
    <header class="header">
        <h1> NBA FANTASY </h1>
        <div class="btn-container">
            <!-- 規則：開啟規則視窗；type="button" 避免被當成表單送出鈕 -->
            <button type="button" id="rulesBtn" class="btn btn-warning rules-btn">規則</button>
            <a href="ranking list.php" class="btn btn-warning">排行榜</a>

            <!-- 使用者名稱按鈕 + 下拉選單 -->
            <div class="user-menu">
                <button type="button" class="btn user-btn" id="userMenuBtn"
                        aria-haspopup="true" aria-expanded="false">
                    <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?> ▾
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <?php if ($isVip): ?>
                        <!-- 退出會改變資料，用 POST 表單而不是連結 -->
                        <form method="post" action="vip.php"
                              onsubmit="return confirm('確定要退出 VIP 嗎？將從下一局開始生效。');">
                            <button type="submit" name="action" value="leave" class="dropdown-btn">退出 VIP</button>
                        </form>
                    <?php else: ?>
                        <a href="vip.php">加入 VIP</a>
                    <?php endif; ?>
                    <a href="change-password.php">更改密碼</a>
                    <a href="logout.php">登出</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 標題下方分左右兩欄：左側工具列、右側 board -->
    <div class="layout">
        <!-- 左側工具列（捲動時固定）：剩餘預算、已選人數、開新局 -->
        <aside class="sidebar">
            <div class="side-item">
                <div class="side-label">難度</div>
                <div class="side-value diff-value">
                    <?php echo $needDifficulty ? '—' : htmlspecialchars(DIFFICULTIES[$round['difficulty']][0]); ?>
                </div>
            </div>
            <div class="side-item">
                <div class="side-label">剩餘預算</div>
                <div class="side-value"><span id="budgetLeft">$<?php echo $tierCfg['budget']; ?></span>
                    <small>/ $<?php echo $tierCfg['budget']; ?></small></div>
            </div>
            <div class="side-item">
                <div class="side-label">已選球員</div>
                <div class="side-value"><span id="pickedCount">0</span>
                    <small>/ <?php echo PICK_COUNT; ?></small></div>
            </div>
            <!-- 已選球員清單：固定 PICK_COUNT 格，內容由 JS 依勾選順序填入 -->
            <ol id="pickedList" class="picked-list">
                <?php for ($i = 0; $i < PICK_COUNT; $i++): ?>
                    <li class="slot empty">（空位）</li>
                <?php endfor; ?>
            </ol>
            <!-- 伺服器傳回的提示（例如選人驗證失敗）也顯示在這裡 -->
            <p id="pickMsg" class="pick-msg"><?php echo htmlspecialchars($flash); ?></p>
            <!-- 開始比賽：剛好選滿 PICK_COUNT 人才能按；form 屬性讓表單外的按鈕也能送出 pickForm -->
            <button type="submit" id="startMatchBtn" class="btn start-match-btn"
                    form="pickForm" disabled>開始比賽</button>
            <!-- 開新局：不直接送出，先開啟難度視窗 -->
            <button type="button" id="newRoundBtn" class="btn new-round-btn">開新局</button>
        </aside>

        <main class="game">
            <?php if ($needDifficulty): ?>
                <p class="no-round">請先選擇難度</p>
            <?php else: ?>
                <form id="pickForm" method="post">
                    <!-- 送出時按鈕會被停用，停用的按鈕不會被送出，所以用隱藏欄位標示這是「開始比賽」 -->
                    <input type="hidden" name="play" value="1">
                    <?php
                    renderBoard('現役球員 Current', 'curr', $boards['curr'], $round['attrs']);
                    if ($tierCfg['classic']) {   // 一般玩家不顯示傳奇 board
                        renderBoard('傳奇球員 Classic', 'class', $boards['class'], $round['attrs']);
                    }
                    ?>
                </form>
            <?php endif; ?>
        </main>
    </div>

    <!-- 難度視窗：半透明黑底，中央選擇難度
         data-open：載入時就顯示（還沒有本局）
         data-required：不能取消（還沒有本局，或本局已打完、從「下一局」進來）
         一般情況（按「開新局」）：載入時隱藏，可取消 -->
    <?php $diffRequired = $needDifficulty || $matchResult !== null; ?>
    <div id="diffModal" class="modal-overlay"
         data-open="<?php echo $needDifficulty ? '1' : '0'; ?>"
         data-required="<?php echo $diffRequired ? '1' : '0'; ?>"
         <?php echo $needDifficulty ? '' : 'hidden'; ?>>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="diffTitle">
            <h2 id="diffTitle"><?php echo $matchResult !== null ? '選擇下一局的難度' : '選擇難度'; ?></h2>
            <?php if (!$diffRequired): ?>
                <p class="modal-note">開新局會重抽能力項目並清空已選球員</p>
            <?php endif; ?>
            <!-- 三個難度都是送出鈕：按哪一個就送出該 value，不靠 JS 也能運作 -->
            <form method="post" class="diff-form">
                <input type="hidden" name="new_round" value="1">
                <?php foreach (DIFFICULTIES as $key => [$label, $cpuBudget]): ?>
                    <button type="submit" name="difficulty" value="<?php echo $key; ?>"
                            class="btn diff-btn diff-<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></button>
                <?php endforeach; ?>
            </form>
            <?php if (!$diffRequired): ?>
                <button type="button" id="diffCancel" class="btn diff-cancel">取消</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($matchResult !== null): ?>
        <!-- 結果視窗：本局已打完時顯示，不能關閉，只能按「下一局」
             依規則不公布總評與總分，只顯示位置、姓名、球隊、狀態、陣容加分與比賽過程 -->
        <div id="resultModal" class="modal-overlay">
            <div class="modal result-modal" role="dialog" aria-modal="true" aria-labelledby="resultTitle">
                <h2 id="resultTitle">比賽結果
                    <span class="result-diff">難度：<?php echo htmlspecialchars($matchResult['difficulty']); ?></span>
                </h2>

                <div class="lineups">
                    <?php foreach (['player' => '你的陣容', 'cpu' => '電腦陣容'] as $side => $title): ?>
                        <section class="lineup lineup-<?php echo $side; ?>">
                            <h3><?php echo $title; ?></h3>
                            <ul>
                                <?php foreach ($matchResult[$side]['players'] as $p): ?>
                                    <li>
                                        <span class="lu-pos"><?php echo htmlspecialchars($p['pos']); ?></span>
                                        <span class="lu-name"><?php echo htmlspecialchars($p['name']); ?></span>
                                        <span class="lu-team"><?php echo htmlspecialchars($p['team']); ?></span>
                                        <span class="lu-status st-<?php echo htmlspecialchars($p['hot']); ?>"><?php echo htmlspecialchars($p['status']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php $b = $matchResult[$side]['bonus']; ?>
                            <div class="lu-bonus">陣容加分 <strong><?php echo $b > 0 ? "+$b" : $b; ?></strong></div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <!-- 計分板：節次與時鐘、即時比分、四節比分表（由 JS 播放時逐步填入） -->
                <div class="scoreboard">
                    <div class="sb-clock"><span id="sbQuarter">第 1 節</span> <span id="sbClock">12:00</span></div>
                    <div class="sb-score">
                        <span class="sb-team">你</span>
                        <span id="sbPlayer" class="sb-num">0</span>
                        <span class="sb-sep">:</span>
                        <span id="sbCpu" class="sb-num">0</span>
                        <span class="sb-team">電腦</span>
                    </div>
                    <table class="q-table">
                        <thead>
                            <tr><th></th><?php for ($q = 1; $q <= QUARTER_COUNT; $q++) echo "<th>Q$q</th>"; ?><th>總分</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (['player' => '你', 'cpu' => '電腦'] as $side => $label): ?>
                                <tr data-side="<?php echo $side; ?>">
                                    <th><?php echo $label; ?></th>
                                    <?php for ($q = 1; $q <= QUARTER_COUNT; $q++) echo "<td class='q-cell'>-</td>"; ?>
                                    <td class="q-total">-</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 轉播：得分事件依時間出現，最新的在最上面 -->
                <ol id="pbpFeed" class="pbp-feed" aria-live="off"></ol>

                <div id="finalBanner" class="final-banner" hidden></div>

                <div class="result-actions">
                    <button type="button" id="skipBtn" class="btn result-btn skip-btn">跳過</button>
                    <button type="button" id="nextRoundBtn" class="btn result-btn next-btn" hidden>下一局</button>
                </div>
            </div>
        </div>
        <!-- 比賽過程資料（JSON）。JSON_HEX_TAG 會把 < > 轉成 \u003C \u003E，
             即使名稱含有 </script> 也不會提早結束標籤 -->
        <script type="application/json" id="resultData"><?php
            echo json_encode($matchResult, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        ?></script>
    <?php endif; ?>

    <!-- 規則視窗：可用「關閉」、Esc 或點視窗外關閉。
         所有數字都直接讀 game_config.php，改設定時規則說明會一起更新 -->
    <?php $sign = fn($n) => $n > 0 ? "+$n" : (string)$n; ?>
    <div id="rulesModal" class="modal-overlay" hidden>
        <div class="modal rules-modal" role="dialog" aria-modal="true" aria-labelledby="rulesTitle">
            <h2 id="rulesTitle">遊戲規則</h2>

            <h3>遊戲流程</h3>
            <ol>
                <li>選擇難度（簡單／困難／名人堂）。</li>
                <li>從匿名球員表中選 <?php echo PICK_COUNT; ?> 位球員，總價不能超過預算。</li>
                <li>按「開始比賽」，與電腦對戰並觀看比賽轉播。</li>
                <li>依結果獲得積分，再按「下一局」重新選難度。</li>
            </ol>

            <h3>選人</h3>
            <ul>
                <li>球員表只顯示價格與本局隨機抽出的能力值；姓名、球隊、位置與總評都隱藏。</li>
                <li>不限位置，現役與傳奇版本的同一位球員可以同時選。</li>
                <li>能力項目與球員順序每局隨機，只有「開新局」才會重抽。</li>
            </ul>
            <table class="rules-table">
                <thead><tr><th>會員等級</th><th>預算</th><th>顯示能力項數</th><th>傳奇球員</th></tr></thead>
                <tbody>
                    <?php foreach (TIERS as $t): ?>
                        <tr><td><?php echo htmlspecialchars($t['label']); ?></td><td>$<?php echo $t['budget']; ?></td>
                            <td><?php echo $t['attrs']; ?> 項</td><td><?php echo $t['classic'] ? '可選' : '不可選'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="rules-note">加入或退出 VIP 從下一局開始生效。</p>

            <h3>特殊加分</h3>
            <ul>
                <li><strong>陣容加分</strong>：後衛（PG+SG）2 人、前鋒（SF+PF）2 人、中鋒 1 人 → <?php echo $sign(POSITION_BONUS); ?>；
                    沒有後衛或沒有前鋒 → <?php echo $sign(POSITION_PENALTY); ?>；其他 → 0。</li>
                <li><strong>球員狀態</strong>：每場隨機，火熱 +<?php echo STATUS_DELTA; ?>、正常 0、冰冷 −<?php echo STATUS_DELTA; ?>，機率依價格：</li>
            </ul>
            <table class="rules-table">
                <thead><tr><th>價格</th><th>火熱</th><th>正常</th><th>冰冷</th></tr></thead>
                <tbody>
                    <?php foreach (STATUS_ODDS as [$min, $max, $hot, $normal, $cold]): ?>
                        <tr><td>$<?php echo $min === $max ? $min : "{$min}–{$max}"; ?></td>
                            <td><?php echo $hot; ?>%</td><td><?php echo $normal; ?>%</td><td><?php echo $cold; ?>%</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>電腦與積分</h3>
            <p>電腦每個位置各選 1 人、剛好用滿預算，可能派出傳奇球員。比賽結果只公布雙方位置、姓名、球隊與狀態，不公布總評。</p>
            <table class="rules-table">
                <thead><tr><th>難度</th><th>勝</th><th>和</th><th>敗</th></tr></thead>
                <tbody>
                    <?php foreach (DIFFICULTIES as $key => [$label]): ?>
                        <tr><td><?php echo htmlspecialchars($label); ?></td>
                            <?php foreach (['win', 'draw', 'loss'] as $o): ?>
                                <td><?php echo $sign(MATCH_POINTS[$key][$o]); ?></td>
                            <?php endforeach; ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="rules-note">積分可為負數。排行榜依積分 → 勝場 → 場數少排序；勝率 = 勝場 ÷ 總場數。</p>

            <button type="button" id="rulesClose" class="btn diff-cancel">關閉</button>
        </div>
    </div>

    <script>
        // 點擊使用者按鈕：切換下拉選單顯示
        const userBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');

        userBtn.addEventListener('click', function (e) {
            e.stopPropagation(); // 避免冒泡到 document 而被立刻關閉
            const isOpen = userDropdown.classList.toggle('show');
            userBtn.setAttribute('aria-expanded', isOpen);
        });

        // 點擊頁面其他地方：關閉下拉選單
        document.addEventListener('click', function (e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });

        /* ---------- 難度視窗 ----------
         * 必選（登入後第一次）：沒有取消鍵，Esc 也關不掉。
         * 開新局：可用「取消」或 Esc 關閉，維持原本這一局。 */
        const diffModal = document.getElementById('diffModal');
        const diffRequired = diffModal.dataset.required === '1';

        function openDiffModal() {
            diffModal.hidden = false;
            document.body.classList.add('modal-open');   // 視窗開啟時鎖住背景捲動
            diffModal.querySelector('.diff-btn').focus();
        }

        function closeDiffModal() {
            if (diffRequired) return;                    // 必選時不能關閉
            diffModal.hidden = true;
            document.body.classList.remove('modal-open');
            document.getElementById('newRoundBtn').focus();
        }

        document.getElementById('newRoundBtn').addEventListener('click', openDiffModal);
        document.getElementById('diffCancel')?.addEventListener('click', closeDiffModal);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !diffModal.hidden) closeDiffModal();
        });
        if (diffModal.dataset.open === '1') openDiffModal();

        /* ---------- 選取：即時計算已選人數與剩餘預算 ----------
         * 這裡只是即時提示；真正的規則檢查之後會在伺服器端再做一次，
         * 因為前端檢查可以被繞過。 */
        const BUDGET = <?php echo $tierCfg['budget']; ?>;   // 依本局等級
        const PICK_COUNT = <?php echo PICK_COUNT; ?>;
        const pickMsg = document.getElementById('pickMsg');

        function updateStatus() {
            const picked = document.querySelectorAll('.pick:checked');
            let spent = 0;
            picked.forEach(cb => spent += Number(cb.dataset.price));
            document.getElementById('pickedCount').textContent = picked.length;
            document.getElementById('budgetLeft').textContent = '$' + (BUDGET - spent);
            // 剛好選滿才能開始比賽（預算已在勾選時擋下，這裡不會超支）
            document.getElementById('startMatchBtn').disabled = picked.length !== PICK_COUNT;
            return { count: picked.length, spent: spent };
        }

        // 開始比賽：送出 pickForm，由伺服器驗證並計算結果。
        // 送出後立刻停用按鈕，避免連按重複送出（伺服器端也會擋下同一局的第二次送出）
        document.getElementById('pickForm')?.addEventListener('submit', () => {
            document.getElementById('startMatchBtn').disabled = true;
            pickMsg.textContent = '比賽進行中…';
        });

        document.querySelectorAll('.pick').forEach(cb => {
            cb.addEventListener('change', function () {
                const { count, spent } = updateStatus();
                if (count > PICK_COUNT || spent > BUDGET) {
                    // 超過人數或預算：取消這次勾選
                    this.checked = false;
                    pickMsg.textContent = count > PICK_COUNT
                        ? `最多只能選 ${PICK_COUNT} 位球員`
                        : `預算不足，剩餘 $${BUDGET - (spent - Number(this.dataset.price))}`;
                    updateStatus();
                } else {
                    pickMsg.textContent = '';
                }
                // 勾選的列加上底色
                this.closest('tr').classList.toggle('picked', this.checked);

                // 同步左側已選清單（放在最後：被預算／人數擋下的勾選此時已是 false，不會加入）
                const pos = pickOrder.indexOf(this);
                if (this.checked && pos === -1) pickOrder.push(this);
                if (!this.checked && pos !== -1) pickOrder.splice(pos, 1);
                renderPicked();
            });
        });

        /* ---------- 左側已選球員清單 ----------
         * pickOrder 依勾選先後記錄勾選框；清單每次依它重畫。
         * 被篩選隱藏的球員仍在清單上，不會忘記選了誰。 */
        const pickOrder = [];
        const pickedList = document.getElementById('pickedList');

        function renderPicked() {
            pickedList.querySelectorAll('.slot').forEach((li, i) => {
                const cb = pickOrder[i];
                li.replaceChildren();               // 清空格子
                li.classList.toggle('empty', !cb);
                if (!cb) {
                    li.textContent = '（空位）';
                    return;
                }
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'slot-del';
                del.textContent = '✕';
                del.title = '移除這位球員';
                // 取消勾選後觸發 change，走與「在表格取消勾選」相同的流程
                del.addEventListener('click', () => {
                    cb.checked = false;
                    cb.dispatchEvent(new Event('change'));
                });

                // 用 textContent 填入文字，不用 innerHTML，避免 XSS
                const type = document.createElement('span');
                type.className = 'slot-type ' + (cb.dataset.type === '傳奇' ? 'classic' : 'current');
                type.textContent = cb.dataset.type;
                const price = document.createElement('span');
                price.className = 'slot-price';
                price.textContent = '$' + cb.dataset.price;
                const top = document.createElement('span');
                top.className = 'slot-top';
                top.textContent = `${cb.dataset.topLabel} ${cb.dataset.topVal}`;

                li.append(del, type, price, top);
            });
        }

        /* ---------- 排序：點表頭切換遞增 / 遞減 ---------- */
        document.querySelectorAll('th.sortable').forEach(th => {
            th.addEventListener('click', function () {
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                const colIndex = Array.from(th.parentNode.children).indexOf(th);
                const asc = !th.classList.contains('asc');   // 第一次點擊預設遞增

                // 清除同一張表其他欄位的排序標記
                table.querySelectorAll('th.sortable').forEach(h => h.classList.remove('asc', 'desc'));
                th.classList.add(asc ? 'asc' : 'desc');

                // parseFloat 可處理 '$5' 以外的小數（如灌籃 87.5）；價格先去掉 $ 符號
                const value = row => parseFloat(row.children[colIndex].textContent.replace('$', ''));
                const rows = Array.from(tbody.rows);
                rows.sort((a, b) => asc ? value(a) - value(b) : value(b) - value(a));
                rows.forEach(r => tbody.appendChild(r));   // appendChild 會把既有元素移到最後
            });
        });

        /* ---------- 每個 board 一次顯示 7 位球員 ----------
         * 量測實際的表頭與列高再計算，不在 CSS 寫死像素：
         * 不同作業系統的字型高度不同，寫死容易變成 6.5 或 7.5 列。 */
        const VISIBLE_ROWS = 7;

        function fitBoardHeight() {
            document.querySelectorAll('.table-wrap').forEach(wrap => {
                const head = wrap.querySelector('thead');
                // 被篩選隱藏的列高度為 0，所以取第一個可見的列來量
                const row = Array.from(wrap.querySelectorAll('tbody tr')).find(r => !r.hidden);
                if (!head || !row) return;
                wrap.style.maxHeight = (head.offsetHeight + row.offsetHeight * VISIBLE_ROWS) + 'px';
            });
        }
        fitBoardHeight();
        window.addEventListener('resize', fitBoardHeight);

        /* ---------- 篩選：兩組條件（AND），按「套用」才執行 ----------
         * 只在瀏覽器端隱藏列，不重新查資料庫；資料本來就在畫面上，沒有安全疑慮。 */
        const RANGE = {
            Price:   { min: 1, max: 5,  int: true,  hint: '1–5 的整數' },
            default: { min: 0, max: 99, int: false, hint: '0–99' },
        };
        const rangeOf = key => RANGE[key] || RANGE.default;

        // 讀取一組條件並做防呆。回傳 state：'empty'（略過）/ 'error'（不套用）/ 'ok'
        function readCond(condEl, n) {
            const key = condEl.querySelector('.f-key').value;
            const op = condEl.querySelector('.f-op').value;
            const input = condEl.querySelector('.f-val');
            const raw = input.value.trim();

            // type=number 輸入非數字時，value 會是空字串，但 validity.badInput 為 true
            const hasInput = raw !== '' || input.validity.badInput;

            if (!key && !hasInput) return { state: 'empty' };
            if (!key) return { state: 'error', input, msg: `條件 ${n}：請先選擇選項` };
            if (!hasInput) return { state: 'empty', msg: `條件 ${n}：未填數值，已略過` };

            const r = rangeOf(key);
            const val = Number(raw);
            if (input.validity.badInput || !Number.isFinite(val)) {
                return { state: 'error', input, msg: `條件 ${n}：請輸入數字` };
            }
            if (val < r.min || val > r.max || (r.int && !Number.isInteger(val))) {
                return { state: 'error', input, msg: `條件 ${n}：數值需為 ${r.hint}` };
            }
            return { state: 'ok', key, op, val };
        }

        // 依表頭的 data-key 找欄位位置（排序只移動列，不會改變欄位順序）
        function colIndex(table, key) {
            const ths = Array.from(table.querySelectorAll('thead th'));
            return ths.findIndex(th => th.dataset.key === key);
        }

        const cellValue = (row, idx) => parseFloat(row.children[idx].textContent.replace('$', ''));

        function compare(a, op, b) {
            if (op === 'gte') return a >= b;
            if (op === 'lte') return a <= b;
            return a === b;   // eq
        }

        // 更新「顯示 x / 總數」與「沒有符合條件」提示
        function updateCount(section) {
            const rows = Array.from(section.querySelectorAll('tbody tr'));
            const shown = rows.filter(r => !r.hidden).length;
            section.querySelector('.shown').textContent = shown;
            section.querySelector('.empty-msg').hidden = shown > 0;
        }

        function applyFilter(section) {
            const table = section.querySelector('table');
            const msgEl = section.querySelector('.f-msg');
            section.querySelectorAll('.f-val').forEach(i => i.classList.remove('invalid'));

            const conds = [];
            const msgs = [];
            let hasError = false;
            section.querySelectorAll('.cond').forEach((condEl, i) => {
                const c = readCond(condEl, i + 1);
                if (c.msg) msgs.push(c.msg);
                if (c.state === 'error') {
                    hasError = true;
                    c.input.classList.add('invalid');
                }
                if (c.state === 'ok') conds.push({ ...c, idx: colIndex(table, c.key) });
            });

            // 有錯誤時整次不套用，避免使用者以為兩個條件都生效
            msgEl.textContent = msgs.join('；');
            msgEl.classList.toggle('error', hasError);
            if (hasError) return;

            // AND：每個有效條件都符合才顯示；沒有有效條件就全部顯示
            table.querySelectorAll('tbody tr').forEach(row => {
                row.hidden = !conds.every(c => compare(cellValue(row, c.idx), c.op, c.val));
            });
            updateCount(section);
        }

        function clearFilter(section) {
            section.querySelectorAll('.f-key').forEach(s => s.value = '');
            section.querySelectorAll('.f-op').forEach(s => s.value = 'gte');
            section.querySelectorAll('.f-val').forEach(i => {
                i.value = '';
                i.classList.remove('invalid');
                setInputRange(i, '');
            });
            const msgEl = section.querySelector('.f-msg');
            msgEl.textContent = '';
            msgEl.classList.remove('error');
            section.querySelectorAll('tbody tr').forEach(row => row.hidden = false);
            updateCount(section);
        }

        // 依選項設定數值框的 min / max / step / 提示文字（瀏覽器的上下箭頭也會受限）
        function setInputRange(input, key) {
            const r = rangeOf(key);
            input.min = r.min;
            input.max = r.max;
            input.step = r.int ? 1 : 'any';
            input.placeholder = key ? r.hint : '數值';
        }

        document.querySelectorAll('section.board').forEach(section => {
            section.querySelector('.f-apply').addEventListener('click', () => applyFilter(section));
            section.querySelector('.f-clear').addEventListener('click', () => clearFilter(section));

            section.querySelectorAll('.cond').forEach(condEl => {
                const input = condEl.querySelector('.f-val');
                // 換選項時，更新數值範圍並清除錯誤標記
                condEl.querySelector('.f-key').addEventListener('change', function () {
                    setInputRange(input, this.value);
                    input.classList.remove('invalid');
                });
                // 篩選列在 pickForm 裡，按 Enter 會送出整個表單；改成執行「套用」
                input.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyFilter(section);
                    }
                });
            });
        });

        /* ---------- 結果視窗：模擬轉播 ----------
         * 伺服器已決定比分並產生全部得分事件；這裡只依時間「播放」，不會改變結果。 */
        const resultModal = document.getElementById('resultModal');
        if (resultModal) {
            const R = JSON.parse(document.getElementById('resultData').textContent);
            const QS = R.quarterSeconds;                 // 一節的比賽秒數（720）
            const QUARTERS = R.quarters.player.length;   // 4
            const REAL_MS_PER_QUARTER = 6000;            // 一節在畫面上播 6 秒
            const rate = QS / REAL_MS_PER_QUARTER;       // 每毫秒前進幾秒比賽時間
            const endTime = QS * QUARTERS;

            // 事件換算成「全場經過秒數」
            const events = R.events.map(e => ({ ...e, abs: (e.q - 1) * QS + e.t }));
            const feed = document.getElementById('pbpFeed');
            const rows = {
                player: document.querySelector('.q-table tr[data-side=player]'),
                cpu: document.querySelector('.q-table tr[data-side=cpu]'),
            };
            let idx = 0;
            let start = null;
            let finished = false;
            const live = { player: [0, 0, 0, 0], cpu: [0, 0, 0, 0] };   // 每節目前得分

            document.body.classList.add('modal-open');

            const fmtClock = sec => {
                const s = Math.max(0, Math.ceil(sec));
                return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
            };

            function applyEvent(e) {
                live[e.side][e.q - 1] += e.pts;
                document.getElementById('sbPlayer').textContent = e.score[0];
                document.getElementById('sbCpu').textContent = e.score[1];
                rows[e.side].querySelectorAll('.q-cell')[e.q - 1].textContent = live[e.side][e.q - 1];

                // 轉播一行：Q1 10:32 球員 三分球 +3　比分
                const li = document.createElement('li');
                li.className = 'pbp-' + e.side;
                const time = document.createElement('span');
                time.className = 'pbp-time';
                time.textContent = `Q${e.q} ${fmtClock(QS - e.t)}`;
                const text = document.createElement('span');
                text.className = 'pbp-text';
                text.textContent = `${e.name} ${e.shot} +${e.pts}`;     // textContent，不用 innerHTML
                const sc = document.createElement('span');
                sc.className = 'pbp-score';
                sc.textContent = `${e.score[0]} : ${e.score[1]}`;
                li.append(time, text, sc);
                feed.prepend(li);
            }

            function setClock(gameTime) {
                const q = Math.min(QUARTERS, Math.floor(gameTime / QS) + 1);
                document.getElementById('sbQuarter').textContent = `第 ${q} 節`;
                document.getElementById('sbClock').textContent = fmtClock(q * QS - gameTime);
                // 尚未開打的節次顯示 -，已開打的顯示目前得分
                ['player', 'cpu'].forEach(side => {
                    rows[side].querySelectorAll('.q-cell').forEach((td, i) => {
                        td.textContent = i < q ? live[side][i] : '-';
                        td.classList.toggle('q-current', i === q - 1 && gameTime < endTime);
                    });
                });
            }

            function finish() {
                if (finished) return;
                finished = true;
                while (idx < events.length) applyEvent(events[idx++]);
                setClock(endTime);
                document.getElementById('sbQuarter').textContent = '比賽結束';
                document.getElementById('sbClock').textContent = '0:00';
                // 四節比分以伺服器資料為準
                ['player', 'cpu'].forEach(side => {
                    rows[side].querySelectorAll('.q-cell').forEach((td, i) => td.textContent = R.quarters[side][i]);
                });
                rows.player.querySelector('.q-total').textContent = R.score[0];
                rows.cpu.querySelector('.q-total').textContent = R.score[1];
                document.getElementById('sbPlayer').textContent = R.score[0];
                document.getElementById('sbCpu').textContent = R.score[1];

                const label = { win: '勝利', draw: '和局', loss: '落敗' }[R.outcome];
                const pts = R.points > 0 ? '+' + R.points : String(R.points);
                const banner = document.getElementById('finalBanner');
                banner.className = 'final-banner final-' + R.outcome;
                banner.textContent = `${label}　${R.score[0]} : ${R.score[1]}　積分 ${pts}`;
                if (R.saveFailed) {
                    const warn = document.createElement('div');
                    warn.className = 'save-failed';
                    warn.textContent = '戰績寫入失敗，這場不會計入排行榜';
                    banner.append(warn);
                }
                banner.hidden = false;
                document.getElementById('skipBtn').hidden = true;
                const next = document.getElementById('nextRoundBtn');
                next.hidden = false;
                next.focus();
            }

            function tick(now) {
                if (finished) return;
                if (start === null) start = now;
                const gameTime = Math.min(endTime, (now - start) * rate);
                while (idx < events.length && events[idx].abs <= gameTime) applyEvent(events[idx++]);
                setClock(gameTime);
                if (gameTime >= endTime) finish();
                else requestAnimationFrame(tick);
            }

            document.getElementById('skipBtn').addEventListener('click', finish);
            // 下一局：關閉結果視窗，開啟難度視窗（必選，不能取消）
            document.getElementById('nextRoundBtn').addEventListener('click', () => {
                resultModal.hidden = true;
                openDiffModal();
            });

            // 系統設定「減少動態效果」時直接顯示結果
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) finish();
            else requestAnimationFrame(tick);
        }

        /* ---------- 規則視窗：關閉鈕、Esc、點視窗外都可關閉 ---------- */
        const rulesModal = document.getElementById('rulesModal');
        const openRules = () => {
            rulesModal.hidden = false;
            document.body.classList.add('modal-open');
            // preventScroll：移動焦點但不捲動，否則視窗會直接捲到最下面的「關閉」
            document.getElementById('rulesClose').focus({ preventScroll: true });
        };
        const closeRules = () => {
            rulesModal.hidden = true;
            document.body.classList.remove('modal-open');
            document.getElementById('rulesBtn').focus();
        };
        document.getElementById('rulesBtn').addEventListener('click', openRules);
        document.getElementById('rulesClose').addEventListener('click', closeRules);
        rulesModal.addEventListener('click', e => { if (e.target === rulesModal) closeRules(); });   // 點到黑色背景才關
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !rulesModal.hidden) closeRules(); });
    </script>
</body>

</html>
