<?php
session_start();
$data_file = 'quiz_data.json';

// 管理者ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === 'admin123') {
        $_SESSION['admin'] = true;
        $_SESSION['admin_qr'] = 'admin_' . bin2hex(random_bytes(8));
        header("Location: admin.php");
        exit;
    } else {
        $error = "パスワードが間違っています";
    }
}

// 管理者でない場合はログイン画面表示
if (!isset($_SESSION['admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理者ログイン</title>
        <style>
            body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
            .login-container { width: 100%; max-width: 400px; }
            .login-form { padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
            .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
            .btn { background: #4CAF50; color: white; border: none; padding: 12px; width: 100%; cursor: pointer; border-radius: 4px; font-size: 16px; }
            .error { color: #dc3545; margin-bottom: 20px; padding: 10px; background: #f8d7da; border-radius: 4px; }
            .login-header { text-align: center; margin-bottom: 30px; }
            .login-header h2 { margin: 0; color: #333; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-form">
                <div class="login-header">
                    <h2>管理者ログイン</h2>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="password">パスワード</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn">ログイン</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// データ読み込み
$data = json_decode(file_get_contents($data_file), true) ?? ['events' => [], 'results' => []];

// イベント追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $event_name = trim($_POST['event_name']);
    if (!empty($event_name)) {
        $data['events'][] = [
            'name' => $event_name,
            'quizzes' => []
        ];
        file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $success = "イベント「{$event_name}」を追加しました";
    } else {
        $error = "イベント名を入力してください";
    }
}

// クイズ追加/編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_quiz']) || isset($_POST['update_quiz']))) {
    $event_index = (int)$_POST['event_index'];
    $quiz_id = $_POST['quiz_id'] ?? 'quiz_' . uniqid();
    $answer = trim($_POST['answer']);
    
    // 画像処理
    $image_data = $_POST['current_image'] ?? '';
    if (isset($_FILES['quiz_image']) && $_FILES['quiz_image']['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['quiz_image']['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($mime, $allowed)) {
            $image_data = base64_encode(file_get_contents($_FILES['quiz_image']['tmp_name']));
        } else {
            $error = "無効な画像形式です。JPEG, PNG, GIFのみ許可されています。";
        }
    }
    
    if (!isset($error) && !empty($answer)) {
        $data['events'][$event_index]['quizzes'][$quiz_id] = [
            'question' => $image_data,
            'answer' => $answer,
            'hints' => array_values(array_filter([
                trim($_POST['hint1'] ?? ''),
                trim($_POST['hint2'] ?? ''),
                trim($_POST['hint3'] ?? '')
            ]))
        ];
        
        file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $success = isset($_POST['add_quiz']) ? "クイズを追加しました！" : "クイズを更新しました！";
    } elseif (empty($answer)) {
        $error = "正解を入力してください";
    }
}

// イベント削除
if (isset($_GET['delete_event'])) {
    $event_index = (int)$_GET['delete_event'];
    if (isset($data['events'][$event_index])) {
        $event_name = $data['events'][$event_index]['name'];
        array_splice($data['events'], $event_index, 1);
        file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $success = "イベント「{$event_name}」を削除しました";
    }
}

// クイズ削除
if (isset($_GET['delete_quiz'])) {
    $event_index = (int)$_GET['event_index'];
    $quiz_id = $_GET['quiz_id'];
    
    if (isset($data['events'][$event_index]['quizzes'][$quiz_id])) {
        unset($data['events'][$event_index]['quizzes'][$quiz_id]);
        file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $success = "クイズを削除しました";
    }
}

// ユーザー情報取得
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $user_results = array_filter($data['results'], function($r) use ($user_id) {
        return $r['user_id'] === $user_id;
    });
}

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRクイズ管理システム</title>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
        .tab { padding: 12px 20px; cursor: pointer; border: 1px solid transparent; border-bottom: none; }
        .tab.active { background: white; border-color: #ddd; border-bottom: 1px solid white; margin-bottom: -1px; border-radius: 4px 4px 0 0; }
        .tab-content { display: none; padding: 20px; background: white; border-radius: 0 4px 4px 4px; border: 1px solid #ddd; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-group input[type="text"], .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-group textarea { min-height: 100px; }
        .btn { display: inline-block; padding: 10px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #4CAF50; color: white; border: none; }
        .btn-secondary { background: #6c757d; color: white; border: none; }
        .btn-danger { background: #dc3545; color: white; border: none; }
        .btn-sm { padding: 5px 10px; font-size: 14px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .qr-container { text-align: center; margin: 15px 0; }
        .action-btns { display: flex; gap: 5px; }
        .logout { float: right; }
        .user-info-card { background: white; padding: 20px; border-radius: 4px; border: 1px solid #ddd; margin-bottom: 20px; }
        .user-info-card h2 { margin-top: 0; }
        .back-link { display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php if (isset($_GET['user_id'])): ?>
        <div class="user-info-card">
            <a href="admin.php" class="back-link btn btn-secondary">&laquo; 管理者画面に戻る</a>
            <h2>ユーザー情報: <?= htmlspecialchars(substr($_GET['user_id'], 0, 12)) ?>...</h2>
            
            <h3>回答履歴</h3>
            <?php if (!empty($user_results)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>イベント名</th>
                            <th>クイズID</th>
                            <th>結果</th>
                            <th>日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_results as $result): ?>
                            <tr>
                                <td><?= htmlspecialchars($result['event_name']) ?></td>
                                <td><?= substr($result['quiz_id'], 0, 8) ?>...</td>
                                <td style="color: <?= $result['is_correct'] ? 'green' : 'red' ?>">
                                    <?= $result['is_correct'] ? '正解' : '不正解' ?>
                                </td>
                                <td><?= $result['timestamp'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>回答履歴がありません</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="header">
            <h1>QRクイズ管理システム</h1>
            <a href="?logout=1" class="logout btn btn-secondary">ログアウト</a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('events')">イベント管理</div>
            <div class="tab" onclick="showTab('quizzes')">クイズ追加</div>
            <div class="tab" onclick="showTab('adminQR')">管理者QR</div>
        </div>
        
        <div id="events" class="tab-content active">
            <div style="margin-bottom: 30px;">
                <h2>新しいイベントを作成</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="event_name">イベント名</label>
                        <input type="text" id="event_name" name="event_name" required>
                    </div>
                    <button type="submit" name="add_event" class="btn btn-primary">作成</button>
                </form>
            </div>
            
            <h2>イベント一覧</h2>
            <?php if (!empty($data['events'])): ?>
                <?php foreach ($data['events'] as $index => $event): ?>
                    <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px; padding: 20px; background: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;"><?= htmlspecialchars($event['name']) ?></h3>
                            <a href="?delete_event=<?= $index ?>" class="btn btn-danger btn-sm" onclick="return confirm('本当に削除しますか？このイベントのクイズも全て削除されます。')">削除</a>
                        </div>
                        
                        <?php if (!empty($event['quizzes'])): ?>
                            <h4>クイズ一覧</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>QRコード</th>
                                        <th>クイズID</th>
                                        <th>正解</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($event['quizzes'] as $quiz_id => $quiz): ?>
                                        <tr>
                                            <td>
                                                <div class="qr-container" id="qr_<?= $quiz_id ?>"></div>
                                                <script>
                                                    new QRCode(document.getElementById("qr_<?= $quiz_id ?>"), {
                                                        text: "<?= $quiz_id ?>",
                                                        width: 100,
                                                        height: 100
                                                    });
                                                </script>
                                            </td>
                                            <td><?= $quiz_id ?></td>
                                            <td><?= htmlspecialchars($quiz['answer']) ?></td>
                                            <td class="action-btns">
                                                <a href="?event_index=<?= $index ?>&quiz_id=<?= $quiz_id ?>&delete_quiz=1" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('本当に削除しますか？')">
                                                    削除
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>このイベントにはまだクイズがありません</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>イベントがありません</p>
            <?php endif; ?>
        </div>
        
        <div id="quizzes" class="tab-content">
            <h2>新しいクイズを追加</h2>
            <form method="POST" enctype="multipart/form-data" id="quizForm">
                <div class="form-group">
                    <label for="event_index">イベント選択</label>
                    <select name="event_index" id="event_index" required>
                        <?php foreach ($data['events'] as $index => $event): ?>
                            <option value="<?= $index ?>"><?= htmlspecialchars($event['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quiz_image">問題画像 (必須)</label>
                    <input type="file" name="quiz_image" id="quiz_image" accept="image/*" required>
                    <small>JPEG, PNG, GIF形式 (最大2MB)</small>
                </div>
                
                <div class="form-group">
                    <label for="answer">正解 (必須)</label>
                    <input type="text" name="answer" id="answer" required>
                </div>
                
                <div class="form-group">
                    <label for="hint1">ヒント1 (任意)</label>
                    <input type="text" name="hint1" id="hint1">
                </div>
                
                <div class="form-group">
                    <label for="hint2">ヒント2 (任意)</label>
                    <input type="text" name="hint2" id="hint2">
                </div>
                
                <div class="form-group">
                    <label for="hint3">ヒント3 (任意)</label>
                    <input type="text" name="hint3" id="hint3">
                </div>
                
                <button type="submit" name="add_quiz" class="btn btn-primary">クイズを追加</button>
            </form>
            
            <script>
                document.getElementById('quizForm').addEventListener('submit', function(e) {
                    const fileInput = document.getElementById('quiz_image');
                    const answerInput = document.getElementById('answer');
                    let isValid = true;
                    
                    // ファイルサイズチェック (2MBまで)
                    if (fileInput.files[0] && fileInput.files[0].size > 2 * 1024 * 1024) {
                        alert('画像サイズは2MB以下にしてください');
                        isValid = false;
                    }
                    
                    // 正解入力チェック
                    if (answerInput.value.trim() === '') {
                        alert('正解を入力してください');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            </script>
        </div>
        
        <div id="adminQR" class="tab-content">
            <h2>管理者QRコード</h2>
            <p>このQRコードをクリアしたユーザーに読み取らせてください</p>
            
            <div class="qr-container" id="adminQRCode"></div>
            <p style="text-align: center;">QRコードID: <?= $_SESSION['admin_qr'] ?></p>
            
            <script>
                new QRCode(document.getElementById("adminQRCode"), {
                    text: "<?= $_SESSION['admin_qr'] ?>",
                    width: 200,
                    height: 200
                });
            </script>
        </div>
    <?php endif; ?>
    
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab[onclick="showTab('${tabId}')"]`).classList.add('active');
        }
    </script>
</body>
</html>
