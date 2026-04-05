<?php
    session_start();
    require_once 'stripe/init.php';

    // Database connection
    $host = 'localhost';
    $dbname = 'jalanyukproject';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    \Stripe\Stripe::setApiKey(getenv('YOUR_SECRET_KEY'));

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $transaction_id = $_GET['transaction_id'] ?? null;

    if (!$transaction_id) {
        header('Location: checkout.php');
        exit;
    }

    try {
        // Get transaction details
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$transaction_id, $user_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Transaction not found");
        }

        // If transaction is already completed, just show success page
        if ($transaction['status'] === 'completed') {
            $success_message = "Pembayaran berhasil! Transaksi sudah diproses sebelumnya.";
        } else {
            // Verify Stripe payment
            $session = \Stripe\Checkout\Session::retrieve($transaction['stripe_session_id']);
            
            if ($session->payment_status === 'paid') {
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Update transaction status to completed
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$transaction_id]);
                    
                    // Add balance to user account
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$transaction['amount'], $user_id]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $success_message = "Pembayaran berhasil! Saldo Anda telah ditambahkan.";
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
            } else {
                throw new Exception("Payment not completed");
            }
        }

        // Get updated user balance
        $stmt = $pdo->prepare("SELECT balance, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $balance = $user['balance'] ?? 0;
        $full_name = $user['full_name'] ?? 'User';

    } catch (Exception $e) {
        // Update transaction status to failed if there's an error
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed', description = CONCAT(description, ' - Error: ', ?) WHERE id = ?");
        $stmt->execute([$e->getMessage(), $transaction_id]);
        
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
    }
    ?>

    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pembayaran Berhasil</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                background: white;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            
            .container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 500px;
                overflow: hidden;
                text-align: center;
            }
            
            .header {
                background: linear-gradient(135deg, #333 0%, #000 100%);
                color: white;
                padding: 40px 20px;
                position: relative;
            }
            
            .header.error {
                background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                animation: scaleIn 0.5s ease-out;
            }
            
            .error-icon {
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                animation: scaleIn 0.5s ease-out;
            }
            
            @keyframes scaleIn {
                from {
                    transform: scale(0);
                    opacity: 0;
                }
                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            
            .title {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 10px;
            }
            
            .subtitle {
                font-size: 16px;
                opacity: 0.9;
                font-weight: 400;
            }
            
            .content {
                padding: 40px 30px;
            }
            
            .transaction-details {
                background: #f8f9fa;
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 30px;
                text-align: left;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #e9ecef;
            }
            
            .detail-row:last-child {
                margin-bottom: 0;
                padding-bottom: 0;
                border-bottom: none;
            }
            
            .detail-label {
                font-weight: 600;
                color: #666;
                font-size: 14px;
            }
            
            .detail-value {
                font-weight: 700;
                color: #333;
                font-size: 16px;
            }
            
            .amount {
                color: #4CAF50;
                font-size: 18px;
            }
            
            .balance {
                color: #2196F3;
                font-size: 18px;
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            
            .btn {
                flex: 1;
                padding: 15px 20px;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
                text-align: center;
                min-width: 120px;
            }
            
            .btn-primary {
                background: #333;
                color: white;
            }
            
            .btn-primary:hover {
                background: #555;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }
            
            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e9ecef;
            }
            
            .btn-secondary:hover {
                background: #e9ecef;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            }
            
            .message {
                font-size: 18px;
                color: #333;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            
            .error-message {
                color: #f44336;
                background: #ffebee;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 30px;
            }
            
            @media (max-width: 480px) {
                .action-buttons {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                }
                
                .content {
                    padding: 30px 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php if (isset($success_message)): ?>
                <div class="header">
                    <div class="success-icon">✓</div>
                    <div class="title">Pembayaran Berhasil!</div>
                    <div class="subtitle">Transaksi Anda telah berhasil diproses</div>
                </div>
                
                <div class="content">
                    <div class="message">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    
                    <div class="transaction-details">
                        <div class="detail-row">
                            <span class="detail-label">Nama</span>
                            <span class="detail-value"><?php echo htmlspecialchars($full_name); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Jenis Transaksi</span>
                            <span class="detail-value"><?php echo ucfirst($transaction['transaction_type']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Jumlah</span>
                            <span class="detail-value amount">Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Metode Pembayaran</span>
                            <span class="detail-value"><?php echo htmlspecialchars($transaction['payment_method']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Saldo Terkini</span>
                            <span class="detail-value balance">Rp <?php echo number_format($balance, 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Waktu Transaksi</span>
                            <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="checkout.php" class="btn btn-primary">Top Up Lagi</a>
                        <a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="header error">
                    <div class="error-icon">✗</div>
                    <div class="title">Pembayaran Gagal</div>
                    <div class="subtitle">Terjadi kesalahan saat memproses pembayaran</div>
                </div>
                
                <div class="content">
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message ?? 'Terjadi kesalahan yang tidak diketahui'); ?>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="checkout.php" class="btn btn-primary">Coba Lagi</a>
                        <a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>