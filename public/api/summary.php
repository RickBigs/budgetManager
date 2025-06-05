<?php
require 'db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $month = isset($_GET['month']) ? $_GET['month'] : null;
    
    if ($month !== null) {
        // Sanifica e valida il mese (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Formato mese non valido. Usa YYYY-MM']);
            exit;
        }
    }

    if ($month) {
        // Se il mese è specificato → riepilogo spese per categoria per quel mese
        $stmt = $pdo->prepare("
            SELECT 
                category, 
                SUM(amount) as total,
                COUNT(*) as count,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount,
                AVG(amount) as avg_amount
            FROM expenses 
            WHERE user_id = 1 
            AND DATE_FORMAT(expense_date, '%Y-%m') = ? 
            GROUP BY category
            ORDER BY total DESC
        ");
        $stmt->execute([$month]);
        
        // Aggiungi anche il totale generale del mese
        $totalStmt = $pdo->prepare("
            SELECT 
                SUM(amount) as grand_total,
                COUNT(*) as total_count
            FROM expenses 
            WHERE user_id = 1 
            AND DATE_FORMAT(expense_date, '%Y-%m') = ?
        ");
        $totalStmt->execute([$month]);
        $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);
        
    } else {
        // Se nessun mese specificato → riepilogo spese per categoria su tutto lo storico
        $stmt = $pdo->prepare("
            SELECT 
                category, 
                SUM(amount) as total,
                COUNT(*) as count,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount,
                AVG(amount) as avg_amount
            FROM expenses 
            WHERE user_id = 1 
            GROUP BY category
            ORDER BY total DESC
        ");
        $stmt->execute();
        
        // Aggiungi anche il totale generale
        $totalStmt = $pdo->prepare("
            SELECT 
                SUM(amount) as grand_total,
                COUNT(*) as total_count
            FROM expenses 
            WHERE user_id = 1
        ");
        $totalStmt->execute();
        $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);
    }

    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatta i dati numerici
    foreach ($summary as &$item) {
        $item['total'] = floatval($item['total']);
        $item['count'] = intval($item['count']);
        $item['min_amount'] = floatval($item['min_amount']);
        $item['max_amount'] = floatval($item['max_amount']);
        $item['avg_amount'] = floatval($item['avg_amount']);
    }
    
    // Restituisci sia il riepilogo che i totali
    $response = [
        'summary' => $summary,
        'totals' => [
            'grand_total' => floatval($totals['grand_total'] ?? 0),
            'total_count' => intval($totals['total_count'] ?? 0)
        ],
        'period' => $month ? $month : 'all'
    ];
    
    // Per mantenere la compatibilità con il frontend esistente,
    // restituisci solo l'array summary se non ci sono parametri speciali
    if (!isset($_GET['detailed'])) {
        echo json_encode($summary);
    } else {
        echo json_encode($response);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore nel recupero del riepilogo']);
}
?>