<?php
require 'db.php';

header('Content-Type: application/json; charset=UTF-8');

$month = isset($_GET['month']) ? $_GET['month'] : null;
if ($month !== null) {
    // Sanifica e valida il mese (YYYY-MM)
    if (!preg_match('/^\\d{4}-\\d{2}$/', $month)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Mese non valido']);
        exit;
    }
}

if ($month) {
    // Se il mese è specificato → riepilogo spese per categoria per quel mese
    $stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = 1 AND DATE_FORMAT(expense_date, '%Y-%m') = ? GROUP BY category");
    $stmt->execute([$month]);
} else {
    // Se nessun mese specificato → riepilogo spese per categoria su tutto lo storico
    $stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = 1 GROUP BY category");
    $stmt->execute();
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
