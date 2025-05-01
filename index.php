<?php
session_start();
$data_file = 'quiz_data.json';

// データ読み込み
$data = json_decode(file_get_contents($data_file), true) ?? ['events' => [], 'results' => []];

// クイズ回答処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $quiz_id = $_POST['quiz_id'];
    $user_answer = $_POST['answer'];
    $nickname = $_POST['nickname'] ?? '匿名';
    
    foreach ($data['events'] as $event) {
        if (isset($event['quizzes'][$quiz_id])) {
            $is_correct = (strtolower($user_answer) === strtolower($event['quizzes'][$quiz_id]['answer']));
            
            $result = [
                'quiz_id' => $quiz_id,
                'is_correct' => $is_correct,
                'timestamp' => date('Y-m-d H:i:s'),
                'nickname' => $nickname,
                'event_name' => $event['name']
            ];
            
            $data['results'][] = $result;
            file_put_contents($data_file, json_encode($data));
            
            header("Location: index.php?quiz_id=".$quiz_id."&result=".($is_correct ? '1' : '0'));
            exit;
        }
    }
}

// 現在のクイズ情報取得
$current_quiz = null;
$hint_level = isset($_GET['hint']) ? (int)$_GET['hint'] : 0;
if (isset($_GET['quiz_id'])) {
    foreach ($data['events'] as $event) {
        if (isset($event['quizzes'][$_GET['quiz_id']])) {
            $current_quiz = $event['quizzes'][$_GET['quiz_id']];
            $current_quiz['id'] = $_GET['quiz_id'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRクイズシステム</title>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .quiz-container { display: flex; gap: 20px; margin-top: 20px; }
        .quiz-image { max-width: 100%; height: auto; max-height: 400px; }
        .qr-code { margin-top: 20px; text-align: center; }
        .hint-box { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; }
        .answer-form { margin-top: 20px; }
        .answer-form input { width: 100%; padding: 10px; margin-bottom: 10px; }
        .btn { background: #4CAF50; color: white; border: none; padding: 10px 15px; cursor: pointer; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 5px; max-width: 80%; }
    </style>
</head>
<body>
    <h1>QRクイズシステム</h1>
    
    <?php if ($current_quiz): ?>
        <div class="quiz-container">
            <div style="flex: 1;">
                <img src="data:image/png;base64,<?= $current_quiz['question'] ?>" class="quiz-image">
                
                <?php if (isset($_GET['result'])): ?>
                    <div style="margin-top: 20px; padding: 15px; background: <?= $_GET['result'] === '1' ? '#d4edda' : '#f8d7da' ?>;">
                        <h2><?= $_GET['result'] === '1' ? '正解！' : '不正解' ?></h2>
                        <a href="index.php" class="btn">他のクイズに挑戦</a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="answer-form">
                        <input type="hidden" name="quiz_id" value="<?= $current_quiz['id'] ?>">
                        <input type="text" name="nickname" placeholder="ニックネーム（任意）">
                        <input type="text" name="answer" placeholder="答えを入力" required>
                        <button type="submit" name="submit_answer" class="btn">回答する</button>
                    </form>
                    
                    <?php if ($hint_level > 0 && !empty($current_quiz['hints'])): ?>
                        <div class="hint-box">
                            <h3>ヒント</h3>
                            <?php for ($i = 0; $i < min($hint_level, count($current_quiz['hints'])); $i++): ?>
                                <p><?= ($i+1).'. '.htmlspecialchars($current_quiz['hints'][$i]) ?></p>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div style="width: 200px;">
                <div class="qr-code" id="quizQR"></div>
                <button onclick="startScanner()" class="btn" style="width: 100%; margin-top: 10px;">QRコードをスキャン</button>
                <button onclick="location.href='index.php?quiz_id=<?= $current_quiz['id'] ?>&hint=<?= $hint_level + 1 ?>'" 
                        class="btn" style="width: 100%; margin-top: 10px; background: #ff9800;">
                    ヒントを見る
                </button>
            </div>
        </div>
        
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <span onclick="stopScanner()" style="float: right; cursor: pointer; font-size: 24px;">&times;</span>
                <h2>QRコードスキャナー</h2>
                <video id="scannerVideo" style="width: 100%;" playsinline></video>
                <p>QRコードをカメラで読み取ってください</p>
            </div>
        </div>
        
        <script>
            // QRコード生成
            new QRCode(document.getElementById("quizQR"), {
                text: "<?= $current_quiz['id'] ?>",
                width: 200,
                height: 200
            });
            
            // QRスキャナー機能
            let scannerStream = null;
            
            function startScanner() {
                const modal = document.getElementById('scannerModal');
                modal.style.display = 'flex';
                
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(stream => {
                        scannerStream = stream;
                        const video = document.getElementById('scannerVideo');
                        video.srcObject = stream;
                        video.play();
                        
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        setInterval(() => {
                            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const code = jsQR(imageData.data, imageData.width, imageData.height);
                                
                                if (code) {
                                    window.location.href = 'index.php?quiz_id=' + code.data;
                                    stopScanner();
                                }
                            }
                        }, 300);
                    })
                    .catch(err => {
                        alert('カメラへのアクセスに失敗しました: ' + err);
                        stopScanner();
                    });
            }
            
            function stopScanner() {
                if (scannerStream) {
                    scannerStream.getTracks().forEach(track => track.stop());
                    scannerStream = null;
                }
                document.getElementById('scannerModal').style.display = 'none';
            }
        </script>
    <?php else: ?>
        <h2>QRコードを読み取ってクイズに参加</h2>
        <p>校内のQRコードをスマートフォンで読み取ってください</p>
        
        <form method="GET" style="margin-top: 20px;">
            <input type="text" name="quiz_id" placeholder="またはクイズIDを直接入力" required>
            <button type="submit" class="btn">クイズ開始</button>
        </form>
    <?php endif; ?>
</body>
</html>
