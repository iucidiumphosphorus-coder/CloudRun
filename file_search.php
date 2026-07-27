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
//  ログイン試行ログ（成功・失敗に関わらず毎回出す）
// ======================================================
$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'];
$userid = $_SESSION['userid'] ?? 'unknown';
error_log("LOGIN ATTEMPT: user=$userid ip=$ip ua=$ua");

// ======================================================
//  セッション盗難チェック
// ======================================================
if (isset($_SESSION['initialized'])) {

    // IPチェック
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        error_log("LOGIN FAILED: user=$userid ip=$ip ua=$ua reason=ip_mismatch");
        header("Location: file_search.php?logout=1");
        exit;
    }

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
if (isset($_GET['logout'])) {
    error_log("SESSION END: user=$userid ip=$ip ua=$ua");
    header("Location: https://web-app-787036707508.us-east1.run.app/_gcp_iap/clear_login_cookie");
    exit;
}

// ======================================================
//  DB接続
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

if ($search_word !== '') {
    error_log("FILE SEARCH: user=$userid ip=$ip query=\"$search_word\"");
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
