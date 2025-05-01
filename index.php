<?php
session_start();
$data_file = 'quiz_data.json';

// データ読み込み
$data = json_decode(file_get_contents($data_file), true) ?? ['events' => [], 'results' => []];

// ユーザー識別用ID生成
if (!isset($_COOKIE['user_id'])) {
    $user_id = 'user_' . bin2hex(random_bytes(8));
    setcookie('user_id', $user_id, time() + (86400 * 30), "/");
} else {
    $user_id = $_COOKIE['user_id'];
}

// クイズ回答処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $quiz_id = $_POST['quiz_id'];
    $user_answer = $_POST['answer'];
    
    foreach ($data['events'] as $event) {
        if (isset($event['quizzes'][$quiz_id])) {
            $is_correct = (strtolower($user_answer) === strtolower($event['quizzes'][$quiz_id]['answer']));
            
            $result = [
                'quiz_id' => $quiz_id,
                'is_correct' => $is_correct,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $user_id,
                'event_name' => $event['name']
            ];
            
            $data['results'][] = $result;
            file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE));
            
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
            $current_event = $event;
            break;
        }
    }
}

// マイページ用データ取得
$my_results = array_filter($data['results'], function($r) use ($user_id) {
    return $r['user_id'] === $user_id;
});

// イベントごとの進捗計算
$event_progress = [];
foreach ($data['events'] as $event) {
    $total = count($event['quizzes']);
    $completed = 0;
    
    foreach ($event['quizzes'] as $quiz_id => $quiz) {
        foreach ($my_results as $result) {
            if ($result['quiz_id'] === $quiz_id && $result['is_correct']) {
                $completed++;
                break;
            }
        }
    }
    
    $event_progress[$event['name']] = [
        'completed' => $completed,
        'total' => $total
    ];
}

// 全クイズクリアチェック
$all_cleared = false;
foreach ($event_progress as $progress) {
    if ($progress['completed'] === $progress['total'] && $progress['total'] > 0) {
        $all_cleared = true;
        break;
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
        .quiz-container { display: flex; flex-direction: column; gap: 20px; margin-top: 20px; }
        @media (min-width: 768px) {
            .quiz-container { flex-direction: row; }
        }
        .quiz-image { max-width: 100%; height: auto; max-height: 400px; border: 1px solid #ddd; }
        .qr-code { margin-top: 20px; text-align: center; }
        .hint-box { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; }
        .answer-form { margin-top: 20px; }
        .answer-form input { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #4CAF50; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; }
        .btn-secondary { background: #6c757d; }
        .btn-warning { background: #ffc107; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; }
        .nav { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; }
        .progress-container { margin: 20px 0; }
        .progress-bar { height: 20px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .progress { height: 100%; background: #28a745; transition: width 0.3s; }
        .progress-text { margin-top: 5px; text-align: center; }
        .close-btn { float: right; cursor: pointer; font-size: 24px; }
        #scannerVideo, #adminScannerVideo { width: 100%; border: 2px solid #4CAF50; }
    </style>
</head>
<body>
    <div class="nav">
        <h1>QRクイズシステム</h1>
        <?php if (!isset($_GET['page']) || $_GET['page'] !== 'mypage'): ?>
            <a href="index.php?page=mypage" class="btn">マイページ</a>
        <?php else: ?>
            <a href="index.php" class="btn">クイズに戻る</a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_GET['page']) && $_GET['page'] === 'mypage'): ?>
        <h2>マイページ</h2>
        <p>あなたのユーザーID: <strong><?= htmlspecialchars($user_id) ?></strong></p>
        
        <h3>攻略状況</h3>
        <?php foreach ($event_progress as $event_name => $progress): ?>
            <?php if ($progress['total'] > 0): ?>
                <div class="progress-container">
                    <h4><?= htmlspecialchars($event_name) ?></h4>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?= ($progress['completed'] / $progress['total']) * 100 ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <?= $progress['completed'] ?> / <?= $progress['total'] ?> クイズ攻略済み
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if ($all_cleared): ?>
            <div style="margin-top: 30px; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>🎉 すべてのクイズをクリアしました！ 🎉</h3>
                <button onclick="showAdminScanner()" class="btn-warning" style="margin-top: 15px;">運営に報告</button>
            </div>
            
            <div id="adminScannerModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" onclick="hideAdminScanner()">&times;</span>
                    <h2>運営QRコードをスキャン</h2>
                    <video id="adminScannerVideo" playsinline></video>
                    <p style="text-align: center;">運営のQRコードをカメラで読み取ってください</p>
                </div>
            </div>
        <?php endif; ?>
        
        <h3>回答履歴</h3>
        <?php if (!empty($my_results)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="padding: 10px; border: 1px solid #ddd;">イベント名</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">クイズID</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">結果</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_results as $result): ?>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($result['event_name']) ?></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?= substr($result['quiz_id'], 0, 8) ?>...</td>
                                <td style="padding: 10px; border: 1px solid #ddd; color: <?= $result['is_correct'] ? 'green' : 'red' ?>">
                                    <?= $result['is_correct'] ? '正解' : '不正解' ?>
                                </td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?= $result['timestamp'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="padding: 15px; background: #f8f9fa; border-radius: 4px;">まだ回答履歴がありません</p>
        <?php endif; ?>
        
    <?php elseif ($current_quiz): ?>
        <div class="quiz-container">
            <div style="flex: 1;">
                <img src="data:image/png;base64,<?= $current_quiz['question'] ?>" class="quiz-image" alt="クイズ画像">
                
                <?php if (isset($_GET['result'])): ?>
                    <div style="margin-top: 20px; padding: 15px; background: <?= $_GET['result'] === '1' ? '#d4edda' : '#f8d7da' ?>; border-radius: 4px;">
                        <h2><?= $_GET['result'] === '1' ? '正解！' : '不正解' ?></h2>
                        <a href="index.php" class="btn">他のクイズに挑戦</a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="answer-form">
                        <input type="hidden" name="quiz_id" value="<?= $current_quiz['id'] ?>">
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
            
            <div style="min-width: 200px;">
                <div class="qr-code" id="quizQR"></div>
                <p style="text-align: center; font-size: 12px; margin-top: -10px;">クイズID: <?= $current_quiz['id'] ?></p>
                <button onclick="startScanner()" class="btn" style="width: 100%; margin-top: 10px;">QRコードをスキャン</button>
                <button onclick="location.href='index.php?quiz_id=<?= $current_quiz['id'] ?>&hint=<?= $hint_level + 1 ?>'" 
                        class="btn-secondary" style="width: 100%; margin-top: 10px;">
                    ヒントを見る
                </button>
            </div>
        </div>
        
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="stopScanner()">&times;</span>
                <h2>QRコードを読み取ってクイズに参加</h2>
                <p>以下のいずれかの方法で参加してください:</p>
                <ol>
                    <li>QRコードをスキャン</li>
                    <li>クイズIDを直接入力</li>
                </ol>
                <video id="scannerVideo" playsinline></video>
                <form method="GET" style="margin-top: 20px;">
                    <input type="text" name="quiz_id" placeholder="クイズIDを入力" required style="width: 100%; padding: 10px;">
                    <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">クイズ開始</button>
                </form>
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
            let scanInterval = null;
            
            function startScanner() {
                const modal = document.getElementById('scannerModal');
                modal.style.display = 'flex';
                
                // カメラアクセス許可を求める
                navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                }).then(stream => {
                    scannerStream = stream;
                    const video = document.getElementById('scannerVideo');
                    video.srcObject = stream;
                    
                    // メタデータが読み込まれたら再生開始
                    video.onloadedmetadata = () => {
                        video.play();
                        
                        // スキャン処理開始
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        scanInterval = setInterval(() => {
                            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                try {
                                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                                        inversionAttempts: 'dontInvert',
                                    });
                                    
                                    if (code) {
                                        clearInterval(scanInterval);
                                        window.location.href = 'index.php?quiz_id=' + code.data;
                                        stopScanner();
                                    }
                                } catch (e) {
                                    console.error('QRスキャンエラー:', e);
                                }
                            }
                        }, 300);
                    };
                    
                }).catch(err => {
                    console.error('カメラエラー:', err);
                    alert('カメラへのアクセスに失敗しました。以下の方法をお試しください:\n\n1. カメラの権限を確認\n2. 別のブラウザをお試し\n3. クイズIDを直接入力');
                    stopScanner();
                });
            }
            
            function stopScanner() {
                if (scanInterval) {
                    clearInterval(scanInterval);
                    scanInterval = null;
                }
                if (scannerStream) {
                    scannerStream.getTracks().forEach(track => track.stop());
                    scannerStream = null;
                }
                document.getElementById('scannerModal').style.display = 'none';
            }
            
            // 管理者QRスキャナー
            let adminScannerStream = null;
            let adminScanInterval = null;
            
            function showAdminScanner() {
                const modal = document.getElementById('adminScannerModal');
                modal.style.display = 'flex';
                
                navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                }).then(stream => {
                    adminScannerStream = stream;
                    const video = document.getElementById('adminScannerVideo');
                    video.srcObject = stream;
                    
                    video.onloadedmetadata = () => {
                        video.play();
                        
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        adminScanInterval = setInterval(() => {
                            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                try {
                                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                                        inversionAttempts: 'dontInvert',
                                    });
                                    
                                    if (code && code.data.startsWith('admin_')) {
                                        clearInterval(adminScanInterval);
                                        alert('クリア報告が完了しました！');
                                        hideAdminScanner();
                                    }
                                } catch (e) {
                                    console.error('QRスキャンエラー:', e);
                                }
                            }
                        }, 300);
                    };
                    
                }).catch(err => {
                    console.error('カメラエラー:', err);
                    alert('カメラへのアクセスに失敗しました。');
                    hideAdminScanner();
                });
            }
            
            function hideAdminScanner() {
                if (adminScanInterval) {
                    clearInterval(adminScanInterval);
                    adminScanInterval = null;
                }
                if (adminScannerStream) {
                    adminScannerStream.getTracks().forEach(track => track.stop());
                    adminScannerStream = null;
                }
                document.getElementById('adminScannerModal').style.display = 'none';
            }
        </script>
    <?php else: ?>
        <div style="max-width: 500px; margin: 0 auto;">
            <h2>QRコードを読み取ってクイズに参加</h2>
            <p>以下のいずれかの方法で参加してください:</p>
            <ol>
                <li>QRコードをスキャン</li>
                <li>クイズIDを直接入力</li>
            </ol>
            
            <button onclick="startScanner()" class="btn" style="width: 100%; margin: 20px 0;">QRコードをスキャン</button>
            
            <form method="GET">
                <input type="text" name="quiz_id" placeholder="クイズIDを入力" required style="width: 100%; padding: 10px; box-sizing: border-box;">
                <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">クイズ開始</button>
            </form>
        </div>
        
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="stopScanner()">&times;</span>
                <h2>QRコードを読み取ってクイズに参加</h2>
                <p>以下のいずれかの方法で参加してください:</p>
                <ol>
                    <li>QRコードをスキャン</li>
                    <li>クイズIDを直接入力</li>
                </ol>
                <video id="scannerVideo" playsinline></video>
                <form method="GET" style="margin-top: 20px;">
                    <input type="text" name="quiz_id" placeholder="クイズIDを入力" required style="width: 100%; padding: 10px;">
                    <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">クイズ開始</button>
                </form>
            </div>
        </div>
        
        <script>
            // QRスキャナー機能 (ホーム画面用)
            let scannerStream = null;
            let scanInterval = null;
            
            function startScanner() {
                const modal = document.getElementById('scannerModal');
                modal.style.display = 'flex';
                
                navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                }).then(stream => {
                    scannerStream = stream;
                    const video = document.getElementById('scannerVideo');
                    video.srcObject = stream;
                    
                    video.onloadedmetadata = () => {
                        video.play();
                        
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        scanInterval = setInterval(() => {
                            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                try {
                                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                                        inversionAttempts: 'dontInvert',
                                    });
                                    
                                    if (code) {
                                        clearInterval(scanInterval);
                                        window.location.href = 'index.php?quiz_id=' + code.data;
                                        stopScanner();
                                    }
                                } catch (e) {
                                    console.error('QRスキャンエラー:', e);
                                }
                            }
                        }, 300);
                    };
                    
                }).catch(err => {
                    console.error('カメラエラー:', err);
                    alert('カメラへのアクセスに失敗しました。以下の方法をお試しください:\n\n1. カメラの権限を確認\n2. 別のブラウザをお試し\n3. クイズIDを直接入力');
                    stopScanner();
                });
            }
            
            function stopScanner() {
                if (scanInterval) {
                    clearInterval(scanInterval);
                    scanInterval = null;
                }
                if (scannerStream) {
                    scannerStream.getTracks().forEach(track => track.stop());
                    scannerStream = null;
                }
                document.getElementById('scannerModal').style.display = 'none';
            }
        </script>
    <?php endif; ?>
</body>
</html>
