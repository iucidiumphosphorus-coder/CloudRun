<?php
// ======================================================
//  セッションのセキュリティ設定
// ======================================================
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
]);
session_start();

// ======================================================
//  ログイン試行ログ（成功・失敗）
// ======================================================
$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'];
$userid = $_SESSION['userid'] ?? 'unknown';
error_log("LOGIN ATTEMPT: user=$userid ip=$ip ua=$ua");

// ======================================================
//  セッション盗難チェック
// ======================================================
if (isset($_SESSION['initialized'])) {
    // UAチェック
    if ($_SESSION['ua'] !== $_SERVER['HTTP_USER_AGENT']) {
        error_log("LOGIN FAILED: user=$userid ip=$ip ua=$ua reason=ua_mismatch");
        header("Location: file_search.php?logout=1");
        exit;
    }
    // セッション有効期限チェック（30分）
    if (time() - $_SESSION['last_access'] > 1800) {
        error_log("LOGIN FAILED: user=$userid ip=$ip ua=$ua reason=session_timeout");
        header("Location: file_search.php?logout=1");
        exit;
    }
    $_SESSION['last_access'] = time();
}

// ======================================================
//  IAP ログアウト処理
// ======================================================
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: https://web-app-787036707508.us-east1.run.app/_gcp_iap/clear_login_cookie");
    exit;
}

// ======================================================
//  DB接続
// ======================================================
$dbname   = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

$conn = new mysqli('127.0.0.1', $username, $password, $dbname, 3306);

if ($conn->connect_error) {
    error_log("DB CONNECT FAILED: ip=$ip ua=$ua error=" . $conn->connect_error);
    die("CloudRun DB接続失敗: " . $conn->connect_error);
}

error_log("DB CONNECT SUCCESS: ip=$ip ua=$ua");

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

    // Cloud Logging にも出力（成功ログ）
    error_log("LOGIN SUCCESS: user=$userid ip=$ip ua=$ua");
}

// ======================================================
//  検索処理
// ======================================================
$search_word = isset($_GET['search']) ? $_GET['search'] : '';
// 検索語の長さ制限（DoS対策）
if (strlen($search_word) > 100) {
    die("検索ワードが長すぎます");
}
if ($search_word !== '') {
    $search_log = str_replace(["\n", "\r"], '', $search_word);
    error_log("FILE SEARCH: user=$userid ip=$ip query=\"$search_log\"");

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
    $stmt = $conn->prepare(
        "SELECT id, file_name, category, created_at FROM files"
    );
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>📁 ファイル一覧・検索システム (CloudRun用)</title>
    <style>
        body {
            font-family: "Segoe UI", "Hiragino Sans", sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 40px;
        }
        h1 {
            font-size: 1.6em;
            margin-bottom: 10px;
        }
        p.desc {
            color: #333;
            margin-bottom: 25px;
        }
        .logout {
            position: absolute;
            top: 20px;
            right: 40px;
        }
        .logout a {
            background: #f5f5f5;
            border: 1px solid #ccc;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .search-box {
            background: #eee;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 300px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            padding: 8px 16px;
            border: none;
            background: #666;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
        }
    </style>
</head>
<body>

<div class="logout">
    <form method="POST" action="file_search.php">
        <button name="logout" value="1">ログアウト（IAPの内部セッション削除）</button>
    </form>
</div>

<h1>📁 ファイル一覧・検索システム (CloudRun用)</h1>
<p class="desc">CloudRunよる再現</p>

<div class="search-box">
    <form method="GET" action="file_search.php">
        <input type="text" name="search" placeholder="ファイル名を入力（例: cisco, config）"
       value="<?= htmlspecialchars($search_word, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">検索</button>
    </form>
</div>

<table>
    <tr>
        <th>ID</th>
        <th>ファイル名</th>
        <th>カテゴリ</th>
        <th>登録日時</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['file_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
