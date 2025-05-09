<?php
session_start();

// データ保存ディレクトリ
$data_dir = __DIR__ . '/data';
if (!file_exists($data_dir)) {
    mkdir($data_dir, 0777, true);
}

// データファイルパス
$user_file = $data_dir . '/user.json';
$quiz_file = $data_dir . '/quiz.json';

// データ初期化
if (!file_exists($user_file)) {
    file_put_contents($user_file, json_encode(['results' => []]));
}
if (!file_exists($quiz_file)) {
    file_put_contents($quiz_file, json_encode(['events' => []]));
}

// データ読み込み
$user_data = json_decode(file_get_contents($user_file), true);
$quiz_data = json_decode(file_get_contents($quiz_file), true);

// ユーザー識別用ID生成
if (!isset($_COOKIE['user_id'])) {
    $user_id = 'user_' . bin2hex(random_bytes(8));
    setcookie('user_id', $user_id, time() + (86400 * 30), "/");
} else {
    $user_id = $_COOKIE['user_id'];
}

// クリア報告処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_clear'])) {
    $admin_code = trim($_POST['admin_code']);
    if (preg_match('/^admin_[a-zA-Z0-9]{8,}$/', $admin_code)) {
        $_SESSION['clear_reported'] = true;
        
        // ユーザーデータにクリア報告を記録
        $user_data['results'][] = [
            'user_id' => $user_id,
            'event_name' => 'クリア報告',
            'quiz_id' => 'admin_report',
            'is_correct' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($user_file, json_encode($user_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $success = "クリア報告が完了しました！運営からの確認をお待ちください。";
    } else {
        $error = "無効な管理者コードです。'admin_'で始まる正しいコードを入力してください。";
    }
}

// クイズ回答処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $quiz_id = $_POST['quiz_id'];
    $user_answer = trim($_POST['answer']);
    $hint_level = (int)($_POST['hint_level'] ?? 0);
    
    foreach ($quiz_data['events'] as $event) {
        if (isset($event['quizzes'][$quiz_id])) {
            $is_correct = (strtolower($user_answer) === strtolower($event['quizzes'][$quiz_id]['answer']));
            
            // 回答結果を記録
            $user_data['results'][] = [
                'user_id' => $user_id,
                'event_name' => $event['name'],
                'quiz_id' => $quiz_id,
                'is_correct' => $is_correct,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            file_put_contents($user_file, json_encode($user_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            if ($is_correct) {
                header("Location: index.php?quiz_id=".$quiz_id."&result=1");
                exit;
            } else {
                $hint_level = min($hint_level + 1, 3);
                header("Location: index.php?quiz_id=".$quiz_id."&hint=".$hint_level);
                exit;
            }
        }
    }
}

// 現在のクイズ情報取得
$current_quiz = null;
$hint_level = isset($_GET['hint']) ? min((int)$_GET['hint'], 3) : 0;
$show_result = isset($_GET['result']);
if (isset($_GET['quiz_id'])) {
    foreach ($quiz_data['events'] as $event) {
        if (isset($event['quizzes'][$_GET['quiz_id']])) {
            $current_quiz = $event['quizzes'][$_GET['quiz_id']];
            $current_quiz['id'] = $_GET['quiz_id'];
            $current_event = $event;
            break;
        }
    }
}

// イベントごとの進捗計算
$event_progress = [];
foreach ($quiz_data['events'] as $event) {
    $total = count($event['quizzes']);
    $completed = 0;
    
    foreach ($event['quizzes'] as $quiz_id => $quiz) {
        foreach ($user_data['results'] as $result) {
            if ($result['user_id'] === $user_id && $result['quiz_id'] === $quiz_id && $result['is_correct']) {
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

// クリア報告済みかチェック
$clear_reported = $_SESSION['clear_reported'] ?? false;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>QRクイズシステム</title>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    <style>
        :root {
            --primary-color: #4285f4;
            --success-color: #34a853;
            --warning-color: #fbbc05;
            --danger-color: #ea4335;
            --light-bg: #f8f9fa;
            --dark-text: #202124;
            --light-text: #5f6368;
        }
        
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
            color: var(--dark-text);
            line-height: 1.6;
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #3367d6;
        }
        
        .btn-secondary {
            background: var(--light-text);
        }
        
        .btn-secondary:hover {
            background: #4e555b;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: var(--dark-text);
        }
        
        .btn-warning:hover {
            background: #e9b000;
        }
        
        .btn-danger {
            background: var(--danger-color);
        }
        
        .btn-danger:hover {
            background: #d33426;
        }
        
        .success-message {
            padding: 15px;
            background: #e6f4ea;
            color: var(--success-color);
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            padding: 15px;
            background: #f8d7da;
            color: var(--danger-color);
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .quiz-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .quiz-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .answer-form input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        
        .result-message {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .result-message.correct {
            background: #e6f4ea;
            color: var(--success-color);
            border: 1px solid #c3e6cb;
        }
        
        .result-message.incorrect {
            background: #f8d7da;
            color: var(--danger-color);
            border: 1px solid #f5c6cb;
        }
        
        .hint-box {
            padding: 15px;
            background: #fff8e1;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffe082;
        }
        
        .progress-container {
            margin-bottom: 25px;
        }
        
        .progress-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s;
        }
        
        .progress-text {
            font-size: 14px;
            color: var(--light-text);
        }
        
        .clear-report-form {
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 30px;
        }
        
        .clear-report-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--light-text);
        }
        
        .scanner-instruction {
            text-align: center;
            margin-bottom: 15px;
            color: var(--light-text);
        }
        
        #scannerVideo, #adminScannerVideo {
            width: 100%;
            border-radius: 8px;
            background: black;
        }
        
        #adminScanResult {
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
        }
        
        .ad-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .ad-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            max-width: 90%;
            text-align: center;
        }
        
        .ad-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
        }
        
        .image-fallback {
            padding: 20px;
            background: #f0f0f0;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="nav">
        <h1 style="margin: 0; color: var(--primary-color);">QRクイズシステム</h1>
        <?php if (!isset($_GET['page']) || $_GET['page'] !== 'mypage'): ?>
            <a href="index.php?page=mypage" class="btn btn-secondary">マイページ</a>
        <?php else: ?>
            <a href="index.php" class="btn">クイズに戻る</a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
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
        
        <?php if ($all_cleared && !$clear_reported): ?>
            <div class="clear-report-form">
                <h3 style="margin-top: 0; color: var(--primary-color);">🎉 すべてのクイズをクリアしました！ 🎉</h3>
                <p>運営にクリアを報告してください</p>
                
                <form method="POST" id="clearReportForm">
                    <input type="text" name="admin_code" placeholder="admin_xxxxxxxx" required
                           pattern="admin_[a-zA-Z0-9]{8,}" 
                           title="運営から提供されたadmin_で始まるコードを入力">
                    <button type="submit" name="report_clear" class="btn">クリア報告を送信</button>
                </form>
                
                <p style="text-align: center; margin: 15px 0;">または</p>
                
                <button onclick="showCameraGuide('admin')" class="btn btn-warning">運営QRコードをスキャン</button>
            </div>
        <?php elseif ($all_cleared && $clear_reported): ?>
            <div style="margin-top: 30px; padding: 20px; background: #e6f4ea; border-radius: 12px; text-align: center;">
                <h3 style="margin-top: 0; color: var(--success-color);">🎉 クリア報告済み 🎉</h3>
                <p>運営からの確認をお待ちください</p>
            </div>
        <?php endif; ?>
        
        <!-- 管理者QRスキャナーモーダル -->
        <div id="adminScannerModal" class="modal">
            <div class="modal-content">
                <button class="close-btn" onclick="hideAdminScanner()">&times;</button>
                <h2 style="text-align: center; margin-top: 0;">運営QRコードをスキャン</h2>
                <p class="scanner-instruction">カメラを運営のQRコードに向けてください</p>
                <video id="adminScannerVideo" playsinline></video>
                <div id="adminScanResult"></div>
                <div style="text-align: center; margin-top: 15px;">
                    <button onclick="hideAdminScanner()" class="btn btn-danger">キャンセル</button>
                </div>
            </div>
        </div>
        
    <?php elseif ($current_quiz): ?>
        <div class="quiz-container">
            <div style="flex: 1;">
                <?php if ($current_quiz['question']): ?>
                    <img src="<?= htmlspecialchars($current_quiz['question']) ?>" class="quiz-image" alt="クイズ画像" onerror="handleImageError(this)">
                <?php else: ?>
                    <div class="image-fallback">
                        <p>画像がありません</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_result): ?>
                    <div class="result-message <?= $_GET['result'] === '1' ? 'correct' : 'incorrect' ?>">
                        <h2 style="margin-top: 0;"><?= $_GET['result'] === '1' ? '正解！' : '不正解' ?></h2>
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
                            <h3 style="margin-top: 0;">ヒント #<?= $hint_level ?></h3>
                            <?php for ($i = 0; $i < min($hint_level, count($current_quiz['hints'])); $i++): ?>
                                <p><?= ($i+1).'. '.htmlspecialchars($current_quiz['hints'][$i]) ?></p>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$show_result && !empty($current_quiz['hints']) && $hint_level < 3): ?>
                        <button onclick="location.href='index.php?quiz_id=<?= $current_quiz['id'] ?>&hint=<?= $hint_level + 1 ?>'" 
                                class="btn btn-warning">
                            ヒントを見る (<?= $hint_level + 1 ?>回目)
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($show_result && $_GET['result'] === '1'): ?>
                <div style="min-width: 200px;">
                    <div class="qr-code" id="quizQR" style="display: block;"></div>
                    <p style="text-align: center; font-size: 14px; color: var(--light-text); margin-top: 5px;">
                        クイズID: <?= $current_quiz['id'] ?>
                    </p>
                    <button onclick="showCameraGuide('normal')" class="btn" style="margin-top: 10px;">QRコードをスキャン</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- QRスキャナーモーダル -->
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <button class="close-btn" onclick="stopScanner()">&times;</button>
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
            
            // 画像読み込みエラー処理
            function handleImageError(img) {
                const container = img.parentNode;
                const fallback = document.createElement('div');
                fallback.className = 'image-fallback';
                fallback.textContent = '画像を読み込めませんでした';
                container.replaceChild(fallback, img);
            }
            
            // カメラ使用前のガイド表示
            function showCameraGuide(type) {
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const message = type === 'admin' 
                    ? '運営QRコードをスキャンするにはカメラの使用許可が必要です。許可しますか？' 
                    : 'QRコードをスキャンするにはカメラの使用許可が必要です。許可しますか？';
                
                if (isMobile) {
                    if (confirm(message)) {
                        type === 'admin' ? startAdminScanner() : startScanner();
                    }
                } else {
                    type === 'admin' ? startAdminScanner() : startScanner();
                }
            }
            
            // QRスキャナー機能
            const qrVideo = document.getElementById('scannerVideo');
            let scannerStream = null;
            let animationFrame = null;
            
            function startScanner() {
                const modal = document.getElementById('scannerModal');
                modal.style.display = 'flex';
                
                // 互換性チェック
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('このブラウザではカメラ機能を利用できません。最新版のChromeまたはFirefoxをお試しください。');
                    modal.style.display = 'none';
                    return;
                }
                
                // HTTPSチェック（ローカル環境を除く）
                if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                    alert('カメラを使用するにはHTTPS接続が必要です。安全な接続に切り替えてください。');
                    modal.style.display = 'none';
                    return;
                }
                
                async function startCamera() {
                    try {
                        // 背面カメラを強制
                        let constraints = { 
                            video: { 
                                facingMode: { exact: "environment" }, // 外カメラを強制
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        };
                        
                        // 背面カメラが使えない場合のフォールバック
                        try {
                            scannerStream = await navigator.mediaDevices.getUserMedia(constraints);
                        } catch (err) {
                            console.log('背面カメラにアクセスできませんでした。前面カメラを試します...');
                            constraints.video.facingMode = 'user';
                            scannerStream = await navigator.mediaDevices.getUserMedia(constraints);
                        }
                        
                        qrVideo.srcObject = scannerStream;
                        await qrVideo.play();
                        
                        startScanning();
                        
                    } catch (err) {
                        console.error('カメラエラー:', err);
                        let errorMessage = 'カメラにアクセスできませんでした';
                        
                        if (err.name === 'NotAllowedError') {
                            errorMessage = 'カメラの使用許可が必要です。ブラウザの設定を確認してください。';
                        } else if (err.name === 'NotFoundError') {
                            errorMessage = '利用可能なカメラが見つかりませんでした。';
                        } else if (err.name === 'NotReadableError') {
                            errorMessage = 'カメラが他のアプリで使用中かもしれません。';
                        }
                        
                        alert(errorMessage);
                        modal.style.display = 'none';
                    }
                }
                
                function startScanning() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    function scanFrame() {
                        if (qrVideo.readyState === qrVideo.HAVE_ENOUGH_DATA) {
                            canvas.width = qrVideo.videoWidth;
                            canvas.height = qrVideo.videoHeight;
                            
                            try {
                                ctx.drawImage(qrVideo, 0, 0, canvas.width, canvas.height);
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                                    inversionAttempts: 'dontInvert',
                                });
                                
                                if (code) {
                                    stopScanner();
                                    showAdAfterScan(); // QRコード読み取り後に広告表示
                                    window.location.href = 'index.php?quiz_id=' + code.data;
                                }
                            } catch (e) {
                                console.error('スキャンエラー:', e);
                            }
                        }
                        
                        animationFrame = requestAnimationFrame(scanFrame);
                    }
                    
                    scanFrame();
                }
                
                startCamera();
            }
            
            function stopScanner() {
                if (animationFrame) {
                    cancelAnimationFrame(animationFrame);
                    animationFrame = null;
                }
                
                if (scannerStream) {
                    scannerStream.getTracks().forEach(track => track.stop());
                    qrVideo.srcObject = null;
                    scannerStream = null;
                }
                
                document.getElementById('scannerModal').style.display = 'none';
            }
            
            // 管理者QRスキャナー機能
            const adminVideo = document.getElementById('adminScannerVideo');
            let adminScannerStream = null;
            let adminAnimationFrame = null;
            
            function startAdminScanner() {
                const modal = document.getElementById('adminScannerModal');
                modal.style.display = 'flex';
                document.getElementById('adminScanResult').textContent = '';
                
                // 互換性チェック
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('このブラウザではカメラ機能を利用できません。最新版のChromeまたはFirefoxをお試しください。');
                    modal.style.display = 'none';
                    return;
                }
                
                // HTTPSチェック（ローカル環境を除く）
                if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                    alert('カメラを使用するにはHTTPS接続が必要です。安全な接続に切り替えてください。');
                    modal.style.display = 'none';
                    return;
                }
                
                async function startCamera() {
                    try {
                        // 背面カメラを強制
                        let constraints = { 
                            video: { 
                                facingMode: { exact: "environment" }, // 外カメラを強制
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        };
                        
                        // 背面カメラが使えない場合のフォールバック
                        try {
                            adminScannerStream = await navigator.mediaDevices.getUserMedia(constraints);
                        } catch (err) {
                            console.log('背面カメラにアクセスできませんでした。前面カメラを試します...');
                            constraints.video.facingMode = 'user';
                            adminScannerStream = await navigator.mediaDevices.getUserMedia(constraints);
                        }
                        
                        adminVideo.srcObject = adminScannerStream;
                        await adminVideo.play();
                        
                        startAdminScanning();
                        
                    } catch (err) {
                        console.error('カメラエラー:', err);
                        let errorMessage = 'カメラにアクセスできませんでした';
                        
                        if (err.name === 'NotAllowedError') {
                            errorMessage = 'カメラの使用許可が必要です。ブラウザの設定を確認してください。';
                        } else if (err.name === 'NotFoundError') {
                            errorMessage = '利用可能なカメラが見つかりませんでした。';
                        } else if (err.name === 'NotReadableError') {
                            errorMessage = 'カメラが他のアプリで使用中かもしれません。';
                        }
                        
                        alert(errorMessage);
                        modal.style.display = 'none';
                    }
                }
                
                function startAdminScanning() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    function scanFrame() {
                        if (adminVideo.readyState === adminVideo.HAVE_ENOUGH_DATA) {
                            canvas.width = adminVideo.videoWidth;
                            canvas.height = adminVideo.videoHeight;
                            
                            try {
                                ctx.drawImage(adminVideo, 0, 0, canvas.width, canvas.height);
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                                    inversionAttempts: 'dontInvert',
                                });
                                
                                if (code) {
                                    if (code.data.startsWith('admin_')) {
                                        document.getElementById('adminScanResult').textContent = '有効なQRコードを検出しました...';
                                        document.getElementById('adminScanResult').style.color = 'var(--success-color)';
                                        
                                        // フォームに値を設定して送信
                                        setTimeout(() => {
                                            document.getElementById('clearReportForm').querySelector('input[name="admin_code"]').value = code.data;
                                            document.getElementById('clearReportForm').submit();
                                        }, 1000);
                                    } else {
                                        document.getElementById('adminScanResult').textContent = '無効なQRコードです';
                                        document.getElementById('adminScanResult').style.color = 'var(--danger-color)';
                                    }
                                }
                            } catch (e) {
                                console.error('QRスキャンエラー:', e);
                            }
                        }
                        
                        adminAnimationFrame = requestAnimationFrame(scanFrame);
                    }
                    
                    scanFrame();
                }
                
                startCamera();
            }
            
            function hideAdminScanner() {
                if (adminAnimationFrame) {
                    cancelAnimationFrame(adminAnimationFrame);
                    adminAnimationFrame = null;
                }
                
                if (adminScannerStream) {
                    adminScannerStream.getTracks().forEach(track => track.stop());
                    adminVideo.srcObject = null;
                    adminScannerStream = null;
                }
                
                document.getElementById('adminScannerModal').style.display = 'none';
            }
            
            // 広告表示関数
            function showAdAfterScan() {
                document.getElementById('adModal').style.display = 'flex';
                
                // 必要に応じてユーザーデータをリセット
                // fetch('reset_user_data.php', { method: 'POST' });
            }
            
            function closeAdModal() {
                document.getElementById('adModal').style.display = 'none';
            }
            
            // モーダル外をクリックで閉じる
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('scannerModal')) {
                    stopScanner();
                }
                if (event.target === document.getElementById('adminScannerModal')) {
                    hideAdminScanner();
                }
                if (event.target === document.getElementById('adModal')) {
                    closeAdModal();
                }
            });
            
            // フォームバリデーション
            document.getElementById('clearReportForm')?.addEventListener('submit', function(e) {
                const adminCode = this.querySelector('input[name="admin_code"]').value.trim();
                if (!/^admin_[a-zA-Z0-9]{8,}$/.test(adminCode)) {
                    e.preventDefault();
                    alert('admin_で始まる有効なコードを入力してください（8文字以上）');
                }
            });
        </script>
    <?php else: ?>
        <div style="max-width: 500px; margin: 0 auto; text-align: center;">
            <h2>QRクイズに参加</h2>
            <p>QRコードをスキャンするか、クイズIDを入力してください</p>
            
            <button onclick="showCameraGuide('normal')" class="btn" style="margin: 20px 0;">QRコードをスキャン</button>
            
            <form method="GET" style="margin-top: 20px;">
                <input type="text" name="quiz_id" placeholder="クイズIDを入力" required 
                       style="width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box;">
                <button type="submit" class="btn" style="margin-top: 10px;">クイズ開始</button>
            </form>
        </div>
        
        <!-- ホーム用QRスキャナーモーダル -->
        <div id="scannerModal" class="modal">
            <div class="modal-content">
                <button class="close-btn" onclick="stopScanner()">&times;</button>
                <h2 style="text-align: center; margin-top: 0;">QRコードをスキャン</h2>
                <p class="scanner-instruction">カメラをQRコードに向けてください</p>
                <video id="scannerVideo" playsinline></video>
                <div style="text-align: center; margin-top: 15px;">
                    <button onclick="stopScanner()" class="btn btn-danger">キャンセル</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div id="adModal" class="ad-modal">
        <button class="ad-close" onclick="closeAdModal()">×</button>
        <div class="ad-content">
            <h2>スペシャルオファー！</h2>
            <p>クイズクリアおめでとうございます！</p>
            <p>限定特典をご利用ください</p>
            <img src="https://via.placeholder.com/300x200" alt="広告画像" style="max-width:100%;" onerror="this.style.display='none'">
            <button onclick="closeAdModal()" style="margin-top:15px; padding:10px 20px; background:#4CAF50; color:white; border:none; border-radius:5px; cursor:pointer;">
                閉じる
            </button>
        </div>
    </div>
</body>
</html>
