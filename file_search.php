<?php
// ======================================================
//  セッションのセキュリティ設定
// ======================================================
session_set_cookie_params([
    'httponly' => true, //JavaScriptからCookieを読めなくする
    'secure' => true, //HTTPS通信でしかCookieを送らない
    'samesite' => 'Strict' //他サイトからのアクセスではCookieを送らない
]);
session_start(); //セッションを開始し、セッションIDを発行

$ip = $_SERVER['REMOTE_ADDR']; //クライアントのIPを取得(ログ用)
$ua = $_SERVER['HTTP_USER_AGENT']; //ブラウザ情報（UA、端末情報）を取得
$userid = $_SESSION['userid'] ?? 'unknown'; //セッション内のユーザー識別子を取得

// ======================================================
//  DB接続
// ======================================================
$dbname   = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

$conn = new mysqli('127.0.0.1', $username, $password, $dbname, 3306); //127.0.0.1、3306ポートは固定

if ($conn->connect_error) { //エラーやミスで変数が変わった時、CloudSQL停止時用
    error_log("DB CONNECT FAILED: ip=$ip ua=$ua error=" . $conn->connect_error);
    die("CloudRun DB接続失敗: " . $conn->connect_error);
}

error_log("DB CONNECT SUCCESS: ip=$ip ua=$ua"); //仕様でerror_logという名前だが、SQL接続成功時にユーザIPとUAを保存

// ======================================================
//  強制ログアウト
// ======================================================
if (isset($_SESSION['initialized'])) {

    // UA(端末)不一致時に強制ログアウト
    //if ($_SESSION['ua'] !== $_SERVER['HTTP_USER_AGENT']) {
        //session_unset();
        //session_destroy();
        //header("Location: https://web-app-787036707508.us-east1.run.app/_gcp_iap/clear_login_cookie");
        //exit;
    //}

    // 10分以上放置で自動ログアウト
    if (time() - $_SESSION['last_access'] > 600) { // 10分
        session_unset();
        session_destroy();
        header("Location: https://web-app-787036707508.us-east1.run.app/_gcp_iap/clear_login_cookie");
        exit;
    }
    $_SESSION['last_access'] = time();
}

// ======================================================
//  IAP ログアウトボタン用処理、この位置に書くことで余計な処理を挟まずログアウト
// ======================================================
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: https://web-app-787036707508.us-east1.run.app/_gcp_iap/clear_login_cookie");
    exit;
}

// ======================================================
//  PHPセッションIDの初期化
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
    $time   = date('Y-m-d H:i:s');
    $stmt2->bind_param("ssss", $userid, $ip, $ua, $time);
    $stmt2->execute();

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
    <link rel="stylesheet" href="style.css">
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
  <form action="file_search.php" method="get">
    <input type="text" name="query" placeholder="ファイル名を入力（例: cisco, config）">
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
