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
    $hint_level = $_POST['hint_level'] ?? 0;
    
    foreach ($data['events'] as $event) {
        if (isset($event['quizzes'][$quiz_id])) {
            $is_correct = (strtolower($user_answer) === strtolower($event['quizzes'][$quiz_id]['answer']));
            
            $result = [
                'quiz_id' => $quiz_id,
                'is_correct' => $is_correct,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $user_id,
                'event_name' => $event['name'],
                'hint_used' => $hint_level
            ];
            
            $data['results'][] = $result;
            file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE));
            
            if ($is_correct) {
                header("Location: index.php?quiz_id=".$quiz_id."&result=1");
            } else {
                $hint_level++;
                header("Location: index.php?quiz_id=".$quiz_id."&hint=".$hint_level);
            }
            exit;
        }
    }
}

// 現在のクイズ情報取得
$current_quiz = null;
$hint_level = isset($_GET['hint']) ? (int)$_GET['hint'] : 0;
$show_result = isset($_GET['result']);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>QRクイズシステム</title>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
        .quiz-container { display: flex; flex-direction: column; gap: 20px; margin-top: 20px; }
        @media (min-width: 768px) {
            .quiz-container { flex-direction: row; }
        }
        .quiz-image { max-width: 100%; height: auto; max-height: 60vh; border: 1px solid #ddd; border-radius: 8px; }
        .qr-code { margin-top: 20px; text-align: center; display: none; } /* 最初は非表示 */
        .hint-box { margin-top: 20px; padding: 15px; background: #fff8e1; border-radius: 8px; border-left: 4px solid #ffc107; }
        .answer-form { margin-top: 20px; }
        .answer-form input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; }
        .btn { background: #4285f4; color: white; border: none; padding: 12px 15px; cursor: pointer; border-radius: 8px; font-size: 16px; text-align: center; display: block; width: 100%; }
        .btn-secondary { background: #34a853; }
        .btn-warning { background: #fbbc05; }
        .btn-danger { background: #ea4335; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .nav { display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center; }
        .progress-container { margin: 20px 0; }
        .progress-bar { height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress { height: 100%; background: #34a853; transition: width 0.3s; }
        .progress-text { margin-top: 5px; text-align: center; color: #666; }
        .close-btn { position: absolute; top: 10px; right: 10px; cursor: pointer; font-size: 24px; color: #333; }
        #scannerVideo, #adminScannerVideo { width: 100%; border-radius: 8px; background: #000; }
        .result-message { padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; }
        .correct { background: #e6f4ea; color: #34a853; border-left: 4px solid #34a853; }
        .incorrect { background: #fce8e6; color: #ea4335; border-left: 4px solid #ea4335; }
        .hidden { display: none; }
        .scanner-instruction { text-align: center; margin: 10px 0; color: white; }
        @media (max-width: 480px) {
            body { padding: 15px; }
            .btn { padding: 14px 15px; }
            .quiz-image { max-height: 50vh; }
        }
    </style>
</head>
<body>
    <div class="nav">
        <h1 style="margin: 0; color: #4285f4;">QRクイズシステム</h1>
        <?php if (!isset($_GET['page']) || $_GET['page'] !== 'mypage'): ?>
            <a href="index.php?page=mypage" class="btn btn-secondary">マイページ</a>
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
            <div style="margin-top: 30px; text-align: center; padding: 20px; background: #e8f0fe; border-radius: 8px;">
                <h3 style="color: #4285f4;">🎉 すべてのクイズをクリアしました！ 🎉</h3>
                <p>おめでとうございます！</p>
            </div>
        <?php endif; ?>
        
        <h3>回答履歴</h3>
        <?php if (!empty($my_results)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f1f3f4;">
                            <th style="padding: 12px; border: 1px solid #ddd;">イベント名</th>
                            <th style="padding: 12px; border: 1px solid #ddd;">結果</th>
                            <th style="padding: 12px; border: 1px solid #ddd;">ヒント使用</th>
                            <th style="padding: 12px; border: 1px solid #ddd;">日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_results as $result): ?>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($result['event_name']) ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; color: <?= $result['is_correct'] ? '#34a853' : '#ea4335' ?>">
                                    <?= $result['is_correct'] ? '正解' : '不正解' ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?= $result['hint_used'] ?>回</td>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?= $result['timestamp'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="padding: 15px; background: #f1f3f4; border-radius: 8px; text-align: center;">まだ回答履歴がありません</p>
        <?php endif; ?>
        
    <?php elseif ($current_quiz): ?>
        <div class="quiz-container">
            <div style="flex: 1;">
                <img src="data:image/png;base64,<?= $current_quiz['question'] ?>" class="quiz-image" alt="クイズ画像">
                
                <?php if ($show_result): ?>
                    <div class="result-message <?= $_GET['result'] === '1' ? 'correct' : 'incorrect' ?>">
                        <h2><?= $_GET['result'] === '1' ? '正解！' : '不正解' ?></h2>
                        <?php if ($_GET['result'] === '1'): ?>
                            <p>おめでとうございます！</p>
                            <a href="index.php" class="btn">他のクイズに挑戦</a>
                        <?php else: ?>
                            <p>もう一度挑戦してみましょう</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST" class="answer-form">
                        <input type="hidden" name="quiz_id" value="<?= $current_quiz['id'] ?>">
                        <input type="hidden" name="hint_level" value="<?= $hint_level ?>">
                        <input type="text" name="answer" placeholder="答えを入力" required autofocus>
                        <button type="submit" name="submit_answer" class="btn">回答する</button>
                    </form>
                    
                    <?php if ($hint_level > 0 && !empty($current_quiz['hints'])): ?>
                        <div class="hint-box">
                            <h3>ヒント #<?= $hint_level ?></h3>
                            <?php for ($i = 0; $i < min($hint_level, count($current_quiz['hints'])); $i++): ?>
                                <p><?= ($i+1).'. '.htmlspecialchars($current_quiz['hints'][$i]) ?></p>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$show_result): ?>
                        <button onclick="location.href='index.php?quiz_id=<?= $current_quiz['id'] ?>&hint=<?= $hint_level + 1 ?>'" 
                                class="btn btn-warning" style="margin-top: 10px;">
                            ヒントを見る (<?= $hint_level + 1 ?>回目)
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- 正解時のみQRコードを表示 -->
            <?php if ($show_result && $_GET['result'] === '1'): ?>
                <div style="min-width: 200px;">
                    <div class="qr-code" id="quizQR" style="display: block;"></div>
                    <p style="text-align: center; font-size: 14px; color: #666; margin-top: 5px;">クイズID: <?= $current_quiz['id'] ?></p>
                    <button onclick="startScanner()" class="btn" style="margin-top: 10px;">QRコードをスキャン</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- QRスキャナーモーダル -->
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="stopScanner()">&times;</span>
                <h2 style="text-align: center; margin-top: 0;">QRコードをスキャン</h2>
                <p class="scanner-instruction">カメラをQRコードに向けてください</p>
                <video id="scannerVideo" playsinline></video>
                <div style="text-align: center; margin-top: 15px;">
                    <button onclick="stopScanner()" class="btn btn-danger">キャンセル</button>
                </div>
            </div>
        </div>
        
        <script>
            // 正解時のみQRコードを生成
            <?php if ($show_result && $_GET['result'] === '1'): ?>
                new QRCode(document.getElementById("quizQR"), {
                    text: "<?= $current_quiz['id'] ?>",
                    width: 200,
                    height: 200,
                    colorDark: "#4285f4",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            <?php endif; ?>
            
            // QRスキャナー機能 (モバイル対応版)
            let scannerStream = null;
            let scanInterval = null;
            
            function startScanner() {
                const modal = document.getElementById('scannerModal');
                modal.style.display = 'flex';
                
                // カメラデバイスの選択 (環境カメラ優先)
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                // モバイルデバイスで環境カメラが利用できない場合のフォールバック
                const fallbackConstraints = {
                    video: {
                        facingMode: { exact: 'user' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                function startWithConstraints(constraints) {
                    navigator.mediaDevices.getUserMedia(constraints)
                        .then(stream => {
                            scannerStream = stream;
                            const video = document.getElementById('scannerVideo');
                            video.srcObject = stream;
                            
                            video.onloadedmetadata = () => {
                                video.play().catch(e => {
                                    console.error('Video play error:', e);
                                    alert('カメラの起動に失敗しました。ページを再読み込みしてください。');
                                    stopScanner();
                                });
                                
                                // スキャン処理開始
                                const canvas = document.createElement('canvas');
                                const ctx = canvas.getContext('2d');
                                
                                scanInterval = setInterval(() => {
                                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                                        try {
                                            canvas.width = video.videoWidth;
                                            canvas.height = video.videoHeight;
                                            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                            
                                            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
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
                        })
                        .catch(err => {
                            console.error('カメラエラー:', err);
                            // 環境カメラが失敗したらフロントカメラを試す
                            if (JSON.stringify(constraints) !== JSON.stringify(fallbackConstraints)) {
                                startWithConstraints(fallbackConstraints);
                            } else {
                                alert('カメラへのアクセスがブロックされました。以下の方法をお試しください:\n\n1. ブラウザの設定でカメラ権限を許可\n2. クイズIDを直接入力\n3. 別のブラウザをお試しください');
                                stopScanner();
                            }
                        });
                }
                
                startWithConstraints(constraints);
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
            
            // モーダル外をクリックで閉じる
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('scannerModal')) {
                    stopScanner();
                }
            });
        </script>
    <?php else: ?>
        <div style="max-width: 500px; margin: 0 auto; text-align: center;">
            <h2>QRクイズに参加</h2>
            <p>QRコードをスキャンするか、クイズIDを入力してください</p>
            
            <button onclick="startScanner()" class="btn" style="margin: 20px 0;">QRコードをスキャン</button>
            
            <form method="GET" style="margin-top: 20px;">
                <input type="text" name="quiz_id" placeholder="クイズIDを入力" required 
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
                <button type="submit" class="btn" style="margin-top: 10px;">クイズ開始</button>
            </form>
        </div>
        
        <!-- ホーム用QRスキャナーモーダル -->
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="stopScanner()">&times;</span>
                <h2 style="text-align: center; margin-top: 0;">QRコードをスキャン</h2>
                <p class="scanner-instruction">カメラをQRコードに向けてください</p>
                <video id="scannerVideo" playsinline></video>
                <div style="text-align: center; margin-top: 15px;">
                    <button onclick="stopScanner()" class="btn btn-danger">キャンセル</button>
                </div>
            </div>
        </div>
        
        <script>
            // ホーム画面用QRスキャナー
            let scannerStream = null;
            let scanInterval = null;
            
            function startScanner() {
                const modal = document.getElementById('scannerModal');
                modal.style.display = 'flex';
                
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                const fallbackConstraints = {
                    video: {
                        facingMode: { exact: 'user' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                function startWithConstraints(constraints) {
                    navigator.mediaDevices.getUserMedia(constraints)
                        .then(stream => {
                            scannerStream = stream;
                            const video = document.getElementById('scannerVideo');
                            video.srcObject = stream;
                            
                            video.onloadedmetadata = () => {
                                video.play().catch(e => {
                                    console.error('Video play error:', e);
                                    alert('カメラの起動に失敗しました。ページを再読み込みしてください。');
                                    stopScanner();
                                });
                                
                                const canvas = document.createElement('canvas');
                                const ctx = canvas.getContext('2d');
                                
                                scanInterval = setInterval(() => {
                                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                                        try {
                                            canvas.width = video.videoWidth;
                                            canvas.height = video.videoHeight;
                                            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                            
                                            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
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
                        })
                        .catch(err => {
                            console.error('カメラエラー:', err);
                            if (JSON.stringify(constraints) !== JSON.stringify(fallbackConstraints)) {
                                startWithConstraints(fallbackConstraints);
                            } else {
                                alert('カメラへのアクセスがブロックされました。以下の方法をお試しください:\n\n1. ブラウザの設定でカメラ権限を許可\n2. クイズIDを直接入力\n3. 別のブラウザをお試しください');
                                stopScanner();
                            }
                        });
                }
                
                startWithConstraints(constraints);
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
            
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('scannerModal')) {
                    stopScanner();
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
