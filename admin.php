<?php
// ローカルアクセスのみ許可
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('管理者機能はローカルからのみアクセス可能です');
}

$data_file = 'quiz_data.json';
$data = json_decode(file_get_contents($data_file), true) ?? ['events' => [], 'results' => []];

// イベント追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $data['events'][] = [
        'name' => $_POST['event_name'],
        'quizzes' => []
    ];
    file_put_contents($data_file, json_encode($data));
    $success = "イベント「{$_POST['event_name']}」を追加しました";
}

// クイズ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quiz'])) {
    $event_index = $_POST['event_index'];
    $quiz_id = 'quiz_' . uniqid();
    
    $image_data = '';
    if (!empty($_FILES['quiz_image']['tmp_name'])) {
        $image_info = getimagesize($_FILES['quiz_image']['tmp_name']);
        if ($image_info && in_array($image_info['mime'], ['image/jpeg', 'image/png', 'image/gif'])) {
            $image_data = base64_encode(file_get_contents($_FILES['quiz_image']['tmp_name']));
        }
    }
    
    $data['events'][$event_index]['quizzes'][$quiz_id] = [
        'question' => $image_data,
        'answer' => $_POST['answer'],
        'hints' => array_values(array_filter([
            $_POST['hint1'],
            $_POST['hint2'],
            $_POST['hint3']
        ]))
    ];
    
    file_put_contents($data_file, json_encode($data));
    $success = "クイズを追加しました！";
}

// 結果クリア
if (isset($_GET['clear_results'])) {
    $data['results'] = [];
    file_put_contents($data_file, json_encode($data));
    $success = "回答結果をクリアしました";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRクイズ管理システム</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 20px; cursor: pointer; border: 1px solid #ddd; }
        .tab.active { background: #f0f0f0; }
        .tab-content { display: none; padding: 20px; border: 1px solid #ddd; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .btn { background: #4CAF50; color: white; border: none; padding: 10px 15px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>QRクイズ管理システム</h1>
    
    <?php if (isset($success)): ?>
        <div style="padding: 10px; background: #d4edda; margin-bottom: 20px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <div class="tabs">
        <div class="tab active" onclick="showTab('events')">イベント管理</div>
        <div class="tab" onclick="showTab('quizzes')">クイズ追加</div>
        <div class="tab" onclick="showTab('results')">回答結果</div>
    </div>
    
    <div id="events" class="tab-content active">
        <h2>新しいイベントを作成</h2>
        <form method="POST">
            <div class="form-group">
                <label>イベント名</label>
                <input type="text" name="event_name" required>
            </div>
            <button type="submit" name="add_event" class="btn">作成</button>
        </form>
        
        <h2>イベント一覧</h2>
        <?php foreach ($data['events'] as $index => $event): ?>
            <div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h3><?= htmlspecialchars($event['name']) ?></h3>
                
                <?php if (!empty($event['quizzes'])): ?>
                    <h4>クイズ一覧</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>QRコード</th>
                                <th>正解</th>
                                <th>ヒント</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($event['quizzes'] as $quiz_id => $quiz): ?>
                                <tr>
                                    <td>
                                        <div id="qr_<?= $quiz_id ?>"></div>
                                        <script>
                                            new QRCode(document.getElementById("qr_<?= $quiz_id ?>"), {
                                                text: "<?= $quiz_id ?>",
                                                width: 100,
                                                height: 100
                                            });
                                        </script>
                                    </td>
                                    <td><?= htmlspecialchars($quiz['answer']) ?></td>
                                    <td>
                                        <ul>
                                            <?php foreach ($quiz['hints'] as $hint): ?>
                                                <li><?= htmlspecialchars($hint) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
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
    </div>
    
    <div id="quizzes" class="tab-content">
        <h2>新しいクイズを追加</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>イベント選択</label>
                <select name="event_index" required>
                    <?php foreach ($data['events'] as $index => $event): ?>
                        <option value="<?= $index ?>"><?= htmlspecialchars($event['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>問題画像</label>
                <input type="file" name="quiz_image" accept="image/*" required>
            </div>
            
            <div class="form-group">
                <label>正解</label>
                <input type="text" name="answer" required>
            </div>
            
            <div class="form-group">
                <label>ヒント1</label>
                <input type="text" name="hint1">
            </div>
            
            <div class="form-group">
                <label>ヒント2</label>
                <input type="text" name="hint2">
            </div>
            
            <div class="form-group">
                <label>ヒント3</label>
                <input type="text" name="hint3">
            </div>
            
            <button type="submit" name="add_quiz" class="btn">クイズを追加</button>
        </form>
    </div>
    
    <div id="results" class="tab-content">
        <h2>回答結果 
            <a href="admin.php?clear_results=1" class="btn" style="background: #f44336;">結果をクリア</a>
        </h2>
        
        <table>
            <thead>
                <tr>
                    <th>クイズID</th>
                    <th>ニックネーム</th>
                    <th>イベント名</th>
                    <th>結果</th>
                    <th>回答日時</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['results'] as $result): ?>
                    <tr>
                        <td><?= substr($result['quiz_id'], 0, 8) ?>...</td>
                        <td><?= htmlspecialchars($result['nickname']) ?></td>
                        <td><?= htmlspecialchars($result['event_name']) ?></td>
                        <td style="color: <?= $result['is_correct'] ? 'green' : 'red' ?>">
                            <?= $result['is_correct'] ? '正解' : '不正解' ?>
                        </td>
                        <td><?= $result['timestamp'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
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
