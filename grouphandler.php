<?php
// Helper functions untuk mengelola group balance dan bill

/**
 * Menambahkan kontribusi manual ke group balance
 */
function addContribution($pdo, $group_id, $user_id, $amount, $notes = null, $payment_method = 'manual') {
    try {
        $pdo->beginTransaction();
        
        // Validasi input
        if ($amount <= 0) {
            throw new Exception("Jumlah kontribusi harus lebih dari 0");
        }
        
        // Cek apakah user adalah member aktif
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM group_members 
            WHERE group_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$group_id, $user_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("User bukan member aktif dari group ini");
        }
        
        // Insert ke payment history
        $stmt = $pdo->prepare("
            INSERT INTO group_payment_history 
            (group_id, user_id, amount, payment_method, status, notes, payment_date) 
            VALUES (?, ?, ?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([$group_id, $user_id, $amount, $payment_method, $notes]);
        
        // Trigger akan otomatis update group_balance
        
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Membuat bill untuk member tertentu
 */
function createBill($pdo, $group_id, $user_id, $bill_amount, $due_date = null, $notes = null, $created_by) {
    try {
        $pdo->beginTransaction();
        
        // Validasi
        if ($bill_amount <= 0) {
            throw new Exception("Jumlah tagihan harus lebih dari 0");
        }
        
        // Insert bill
        $stmt = $pdo->prepare("
            INSERT INTO group_bill 
            (group_id, user_id, bill_amount, remaining_amount, due_date, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$group_id, $user_id, $bill_amount, $bill_amount, $due_date, $notes, $created_by]);
        
        $bill_id = $pdo->lastInsertId();
        
        $pdo->commit();
        return $bill_id;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Membuat bill untuk semua member dalam group
 */
function createBillForAllMembers($pdo, $group_id, $bill_amount, $due_date = null, $notes = null, $created_by) {
    try {
        $pdo->beginTransaction();
        
        // Get all active members
        $stmt = $pdo->prepare("
            SELECT user_id FROM group_members 
            WHERE group_id = ? AND status = 'active'
        ");
        $stmt->execute([$group_id]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $bill_ids = [];
        foreach ($members as $user_id) {
            $bill_id = createBill($pdo, $group_id, $user_id, $bill_amount, $due_date, $notes, $created_by);
            $bill_ids[] = $bill_id;
        }
        
        $pdo->commit();
        return $bill_ids;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Pembayaran bill
 */
function payBill($pdo, $bill_id, $user_id, $amount, $payment_method = 'manual', $notes = null) {
    try {
        $pdo->beginTransaction();
        
        // Get bill info
        $stmt = $pdo->prepare("
            SELECT * FROM group_bill 
            WHERE id = ? AND user_id = ? AND status != 'paid'
        ");
        $stmt->execute([$bill_id, $user_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill) {
            throw new Exception("Bill tidak ditemukan atau sudah dibayar");
        }
        
        // Validasi amount
        if ($amount <= 0) {
            throw new Exception("Jumlah pembayaran harus lebih dari 0");
        }
        
        if ($amount > $bill['remaining_amount']) {
            throw new Exception("Jumlah pembayaran melebihi sisa tagihan");
        }
        
        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO group_payment_history 
            (group_id, user_id, amount, payment_method, status, notes, payment_date, bill_id) 
            VALUES (?, ?, ?, ?, 'completed', ?, NOW(), ?)
        ");
        $stmt->execute([$bill['group_id'], $user_id, $amount, $payment_method, $notes, $bill_id]);
        
        // Update remaining amount
        $new_remaining = $bill['remaining_amount'] - $amount;
        $status = ($new_remaining == 0) ? 'paid' : 'partial';
        
        $stmt = $pdo->prepare("
            UPDATE group_bill 
            SET remaining_amount = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_remaining, $status, $bill_id]);
        
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Mendapatkan ringkasan balance group
 */
function getGroupBalanceSummary($pdo, $group_id) {
    try {
        // Get group balance
        $stmt = $pdo->prepare("
            SELECT balance FROM group_balance 
            WHERE group_id = ?
        ");
        $stmt->execute([$group_id]);
        $balance = $stmt->fetchColumn() ?: 0;
        
        // Get total outstanding bills
        $stmt = $pdo->prepare("
            SELECT SUM(remaining_amount) FROM group_bill 
            WHERE group_id = ? AND status != 'paid'
        ");
        $stmt->execute([$group_id]);
        $outstanding_bills = $stmt->fetchColumn() ?: 0;
        
        // Get total paid this month
        $stmt = $pdo->prepare("
            SELECT SUM(amount) FROM group_payment_history 
            WHERE group_id = ? AND status = 'completed' 
            AND MONTH(payment_date) = MONTH(NOW()) 
            AND YEAR(payment_date) = YEAR(NOW())
        ");
        $stmt->execute([$group_id]);
        $monthly_paid = $stmt->fetchColumn() ?: 0;
        
        return [
            'balance' => $balance,
            'outstanding_bills' => $outstanding_bills,
            'monthly_paid' => $monthly_paid,
            'projected_balance' => $balance + $outstanding_bills
        ];
    } catch(Exception $e) {
        throw $e;
    }
}

/**
 * Mendapatkan history pembayaran user
 */
function getUserPaymentHistory($pdo, $group_id, $user_id, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                gph.*,
                gb.bill_amount,
                gb.notes as bill_notes
            FROM group_payment_history gph
            LEFT JOIN group_bill gb ON gph.bill_id = gb.id
            WHERE gph.group_id = ? AND gph.user_id = ?
            ORDER BY gph.payment_date DESC
            LIMIT ?
        ");
        $stmt->execute([$group_id, $user_id, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        throw $e;
    }
}

/**
 * Mendapatkan bill yang belum dibayar untuk user
 */
function getUnpaidBills($pdo, $group_id, $user_id = null) {
    try {
        $sql = "
            SELECT 
                gb.*,
                u.name as user_name,
                u.email as user_email
            FROM group_bill gb
            JOIN users u ON gb.user_id = u.id
            WHERE gb.group_id = ? AND gb.status != 'paid'
        ";
        
        $params = [$group_id];
        
        if ($user_id) {
            $sql .= " AND gb.user_id = ?";
            $params[] = $user_id;
        }
        
        $sql .= " ORDER BY gb.due_date ASC, gb.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        throw $e;
    }
}

/**
 * Mendapatkan member dengan balance tertinggi/terendah
 */
function getMemberBalanceRanking($pdo, $group_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                COALESCE(SUM(gph.amount), 0) as total_paid,
                COALESCE(SUM(gb.remaining_amount), 0) as outstanding_bills,
                (COALESCE(SUM(gph.amount), 0) - COALESCE(SUM(gb.remaining_amount), 0)) as net_balance
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            LEFT JOIN group_payment_history gph ON gm.user_id = gph.user_id AND gm.group_id = gph.group_id
            LEFT JOIN group_bill gb ON gm.user_id = gb.user_id AND gm.group_id = gb.group_id AND gb.status != 'paid'
            WHERE gm.group_id = ? AND gm.status = 'active'
            GROUP BY u.id, u.name, u.email
            ORDER BY net_balance DESC
        ");
        $stmt->execute([$group_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        throw $e;
    }
}

/**
 * Membuat expense untuk group
 */
function createExpense($pdo, $group_id, $amount, $description, $created_by, $split_method = 'equal') {
    try {
        $pdo->beginTransaction();
        
        // Validasi
        if ($amount <= 0) {
            throw new Exception("Jumlah expense harus lebih dari 0");
        }
        
        // Cek balance group
        $stmt = $pdo->prepare("SELECT balance FROM group_balance WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $current_balance = $stmt->fetchColumn() ?: 0;
        
        if ($current_balance < $amount) {
            throw new Exception("Saldo group tidak mencukupi untuk expense ini");
        }
        
        // Insert expense
        $stmt = $pdo->prepare("
            INSERT INTO group_expenses 
            (group_id, amount, description, created_by, split_method) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$group_id, $amount, $description, $created_by, $split_method]);
        
        $expense_id = $pdo->lastInsertId();
        
        // Update group balance
        $stmt = $pdo->prepare("
            UPDATE group_balance 
            SET balance = balance - ?, updated_at = NOW() 
            WHERE group_id = ?
        ");
        $stmt->execute([$amount, $group_id]);
        
        $pdo->commit();
        return $expense_id;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Mendapatkan laporan keuangan group
 */
function getGroupFinancialReport($pdo, $group_id, $start_date = null, $end_date = null) {
    try {
        $date_filter = "";
        $params = [$group_id];
        
        if ($start_date && $end_date) {
            $date_filter = " AND DATE(payment_date) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        // Total income
        $stmt = $pdo->prepare("
            SELECT SUM(amount) FROM group_payment_history 
            WHERE group_id = ? AND status = 'completed' $date_filter
        ");
        $stmt->execute($params);
        $total_income = $stmt->fetchColumn() ?: 0;
        
        // Total expenses
        $stmt = $pdo->prepare("
            SELECT SUM(amount) FROM group_expenses 
            WHERE group_id = ? $date_filter
        ");
        $stmt->execute($params);
        $total_expenses = $stmt->fetchColumn() ?: 0;
        
        // Current balance
        $stmt = $pdo->prepare("SELECT balance FROM group_balance WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $current_balance = $stmt->fetchColumn() ?: 0;
        
        return [
            'total_income' => $total_income,
            'total_expenses' => $total_expenses,
            'current_balance' => $current_balance,
            'net_income' => $total_income - $total_expenses,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
    } catch(Exception $e) {
        throw $e;
    }
}

/**
 * Mengirim reminder untuk bill yang hampir jatuh tempo
 */
function sendBillReminders($pdo, $days_before = 3) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                gb.*,
                u.name as user_name,
                u.email as user_email,
                g.name as group_name
            FROM group_bill gb
            JOIN users u ON gb.user_id = u.id
            JOIN groups g ON gb.group_id = g.id
            WHERE gb.status != 'paid' 
            AND gb.due_date IS NOT NULL
            AND DATE(gb.due_date) = DATE(DATE_ADD(NOW(), INTERVAL ? DAY))
        ");
        $stmt->execute([$days_before]);
        
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bills as $bill) {
            // Di sini bisa ditambahkan logic untuk mengirim email/notifikasi
            // sendEmailReminder($bill);
            
            // Log reminder
            error_log("Reminder sent for bill ID: {$bill['id']} to user: {$bill['user_name']}");
        }
        
        return count($bills);
    } catch(Exception $e) {
        throw $e;
    }
}

/**
 * Utility function untuk format currency
 */
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Utility function untuk mendapatkan status warna bill
 */
function getBillStatusColor($status, $due_date = null) {
    switch($status) {
        case 'paid':
            return 'success';
        case 'partial':
            return 'warning';
        case 'unpaid':
            if ($due_date && strtotime($due_date) < time()) {
                return 'danger'; // overdue
            }
            return 'info';
        default:
            return 'secondary';
    }
}

?>