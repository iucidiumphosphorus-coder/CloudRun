<?php
// ======================================================
//  セッションのセキュリティ設定（追加）
// ======================================================
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
]);
session_start();

// ======================================================
//  セッション盗難チェック（追加）
// ======================================================
if (isset($_SESSION['initialized'])) {

    // IPチェック
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        header("Location: file_search.php?logout=1");
        exit;
    }

    // UAチェック
    if ($_SESSION['ua'] !== $_SERVER['HTTP_USER_AGENT']) {
        header("Location: file_search.php?logout=1");
        exit;
    }

    // セッション有効期限チェック（30分）
    if (time() - $_SESSION['last_access'] > 1800) {
        header("Location: file_search.php?logout=1");
        exit;
    }

    $_SESSION['last_access'] = time();
}

// ======================================================
//  IAP ログアウト処理（元のコード）
// ======================================================
if (isset($_GET['logout'])) {
    header("Location: https://web-app-787036707508.us-east1.run.app/_gcp_iap/clear_login_cookie");
    exit;
}

// ======================================================
//  DB接続（元のコード）
// ======================================================
$dbname   = 'test_db';
$username = 'testsql1';
$password = 'testsql1';

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(
    '127.0.0.1',
    'testsql1',
    'testsql1',
    'test_db',
    3306
);

if ($conn->connect_error) {
    die("CloudRun DB接続失敗: " . $conn->connect_error);
}

// ======================================================
//  初回ログイン時のセッション初期化
// ======================================================
if (!isset($_SESSION['initialized'])) {

    session_regenerate_id(true);
    $_SESSION['ip']          = $_SERVER['REMOTE_ADDR'];
    $_SESSION['ua']          = $_SERVER['HTTP_USER_AGENT'];
    $_SESSION['last_access'] = time();
    $_SESSION['initialized'] = true;

    // ログイン履歴をDBに記録
    $stmt2 = $conn->prepare(
        "INSERT INTO login_logs (userid, ip, ua, time) VALUES (?, ?, ?, ?)"
    );
    $userid = $_SESSION['userid'] ?? 'unknown';
    $ip     = $_SERVER['REMOTE_ADDR'];
    $ua     = $_SERVER['HTTP_USER_AGENT'];
    $time   = date('Y-m-d H:i:s');
    $stmt2->bind_param("ssss", $userid, $ip, $ua, $time);
    $stmt2->execute();

    // Cloud Logging にも出力
    error_log("LOGIN: user=$userid ip=$ip ua=$ua");
}

// ======================================================
//  検索処理（元のコード）
// ======================================================
$search_word = isset($_GET['search']) ? $_GET['search'] : '';

if ($search_word !== '') {
    $stmt = $conn->prepare(
        "SELECT id, file_name, category, created_at
         FROM files
         WHERE file_name LIKE ? OR category LIKE ?"
    );
    $like_word = "%" . $search_word . "%";
    $stmt->bind_param("ss", $like_word, $like_word);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql    = "SELECT id, file_name, category, created_at FROM files";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>📁 ファイル検索システム (CloudRun)</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #e0e0e0; }
        .search-box { margin-bottom: 20px; padding: 15px; background: #eee; border-radius: 5px; }
        input[type="text"] { padding: 8px; width: 300px; font-size: 16px; }
        input[type="submit"] { padding: 8px 15px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="margin:0;">📁 ファイル一覧・検索システム (CloudRun)</h2>

    <a href="/file_search.php?logout=1"
       style="padding:6px 10px; background:#fff; border:1px solid #ccc; border-radius:3px;
              text-decoration:none; font-size:14px; font-weight:normal;">
        ログアウト（IAP の内部セッション削除）
    </a>
</div>

<div class="search-box">
    <form action="" method="GET">
        <input type="text" name="search" placeholder="ファイル名を入力"
               value="<?php echo htmlspecialchars($search_word, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="submit" value="検索">
        <?php if ($search_word !== ''): ?>
            <a href="file_search.php">クリア</a>
        <?php endif; ?>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>ファイル名</th>
            <th>カテゴリ</th>
            <th>登録日時</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['file_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">該当するファイルが見つかりません。</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
