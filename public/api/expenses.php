<?php
require 'db.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
        // Se il parametro month è presente, filtra per mese
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = 1 AND DATE_FORMAT(expense_date, '%Y-%m') = ? ORDER BY expense_date DESC");
        $stmt->execute([$month]);
    } else {
        // Altrimenti mostra sempre le ultime 10 spese
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = 1 ORDER BY expense_date DESC LIMIT 10");
        $stmt->execute();
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validazione e sanificazione
    $allowed_categories = ['Alimentari', 'Bollette', 'Svago', 'Trasporti', 'Altro'];
    $category = isset($data['category']) ? trim($data['category']) : '';
    $amount = isset($data['amount']) ? filter_var($data['amount'], FILTER_VALIDATE_FLOAT) : false;
    $description = isset($data['description']) ? htmlspecialchars(trim($data['description'])) : '';
    $expense_date = isset($data['expense_date']) ? $data['expense_date'] : '';

    $date_valid = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $expense_date);

    if (
        in_array($category, $allowed_categories, true) &&
        $amount !== false && $amount > 0 &&
        !empty($description) && strlen($description) <= 255 &&
        $date_valid
    ) {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category, amount, description, expense_date) VALUES (1, ?, ?, ?, ?)");
        $stmt->execute([$category, $amount, $description, $expense_date]);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dati non validi']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = $_DELETE['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = 1");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'deleted']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    $allowed_categories = ['Alimentari', 'Bollette', 'Svago', 'Trasporti', 'Altro'];
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $category = isset($data['category']) ? trim($data['category']) : '';
    $amount = isset($data['amount']) ? filter_var($data['amount'], FILTER_VALIDATE_FLOAT) : false;
    $description = isset($data['description']) ? htmlspecialchars(trim($data['description'])) : '';
    $expense_date = isset($data['expense_date']) ? $data['expense_date'] : '';
    $date_valid = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $expense_date);

    if (
        $id > 0 &&
        in_array($category, $allowed_categories, true) &&
        $amount !== false && $amount > 0 &&
        !empty($description) && strlen($description) <= 255 &&
        $date_valid
    ) {
        $stmt = $pdo->prepare("UPDATE expenses SET category = ?, amount = ?, description = ?, expense_date = ? WHERE id = ? AND user_id = 1");
        $stmt->execute([$category, $amount, $description, $expense_date, $id]);
        echo json_encode(['status' => 'updated']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dati non validi per modifica']);
    }
}
?>
