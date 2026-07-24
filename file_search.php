<?php
$dbname = 'test_db';
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

$search_word = isset($_GET['search']) ? $_GET['search'] : '';

if ($search_word !== '') {
    $stmt = $conn->prepare("SELECT id, file_name, category, created_at FROM files WHERE file_name LIKE ? OR category LIKE ?");
    $like_word = "%" . $search_word . "%";
    $stmt->bind_param("ss", $like_word, $like_word);
    $stmt->execute();
    $result = $stmt->get_result();
}
else {
    $sql = "SELECT id, file_name, category, created_at FROM files";
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

<h2>📁 ファイル一覧・検索システム (CloudRun)</h2>
<p>Cloud SQL 接続検証用</p>

<div class="search-box">
    <form action="" method="GET">
        <input type="text" name="search" placeholder="ファイル名を入力" value="<?php echo htmlspecialchars($search_word, ENT_QUOTES, 'UTF-8'); ?>">
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
            <?php while($row = $result->fetch_assoc()): ?>
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
if (isset($stmt)) { $stmt->close(); }
$conn->close();
?>
