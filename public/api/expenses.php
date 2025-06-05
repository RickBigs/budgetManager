<?php
require 'db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GET - Recupera le spese
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
            // Se il parametro month è presente, filtra per mese
            $stmt = $pdo->prepare("
                SELECT * FROM expenses 
                WHERE user_id = 1 
                AND DATE_FORMAT(expense_date, '%Y-%m') = ? 
                ORDER BY expense_date DESC, id DESC
            ");
            $stmt->execute([$month]);
        } else {
            // Altrimenti mostra tutte le spese (non solo le ultime 10)
            $stmt = $pdo->prepare("
                SELECT * FROM expenses 
                WHERE user_id = 1 
                ORDER BY expense_date DESC, id DESC
            ");
            $stmt->execute();
        }

        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatta i dati per assicurarsi che amount sia numerico
        foreach ($expenses as &$expense) {
            $expense['amount'] = floatval($expense['amount']);
        }
        
        echo json_encode($expenses);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore nel recupero dei dati']);
    }
}

// POST - Crea una nuova spesa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validazione e sanificazione
        $allowed_categories = ['Alimentari', 'Bollette', 'Svago', 'Trasporti', 'Altro'];
        
        $category = isset($data['category']) ? trim($data['category']) : '';
        $amount = isset($data['amount']) ? filter_var($data['amount'], FILTER_VALIDATE_FLOAT) : false;
        $description = isset($data['description']) ? trim($data['description']) : '';
        $expense_date = isset($data['expense_date']) ? $data['expense_date'] : '';

        // Validazioni
        $errors = [];
        
        if (!in_array($category, $allowed_categories, true)) {
            $errors[] = 'Categoria non valida';
        }
        
        if ($amount === false || $amount <= 0) {
            $errors[] = 'Importo deve essere maggiore di zero';
        }
        
        if (empty($description)) {
            $errors[] = 'Descrizione richiesta';
        } elseif (strlen($description) > 255) {
            $errors[] = 'Descrizione troppo lunga (max 255 caratteri)';
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
            $errors[] = 'Formato data non valido';
        }

        if (empty($errors)) {
            // Sanifica la descrizione per prevenire XSS
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            
            $stmt = $pdo->prepare("
                INSERT INTO expenses (user_id, category, amount, description, expense_date, created_at) 
                VALUES (1, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$category, $amount, $description, $expense_date]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => 'Spesa salvata con successo',
                'id' => $pdo->lastInsertId()
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error', 
                'message' => 'Dati non validi',
                'errors' => $errors
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore nel salvataggio']);
    }
}

// DELETE - Elimina una spesa
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        parse_str(file_get_contents("php://input"), $_DELETE);
        $id = isset($_DELETE['id']) ? intval($_DELETE['id']) : 0;

        if ($id > 0) {
            // Verifica che la spesa esista e appartenga all'utente
            $stmt = $pdo->prepare("SELECT id FROM expenses WHERE id = ? AND user_id = 1");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = 1");
                $stmt->execute([$id]);
                
                echo json_encode([
                    'status' => 'deleted',
                    'message' => 'Spesa eliminata con successo'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Spesa non trovata']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID non valido']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore nell\'eliminazione']);
    }
}

// PUT - Modifica una spesa
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        $allowed_categories = ['Alimentari', 'Bollette', 'Svago', 'Trasporti', 'Altro'];
        
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $category = isset($data['category']) ? trim($data['category']) : '';
        $amount = isset($data['amount']) ? filter_var($data['amount'], FILTER_VALIDATE_FLOAT) : false;
        $description = isset($data['description']) ? trim($data['description']) : '';
        $expense_date = isset($data['expense_date']) ? $data['expense_date'] : '';

        // Validazioni
        $errors = [];
        
        if ($id <= 0) {
            $errors[] = 'ID non valido';
        }
        
        if (!in_array($category, $allowed_categories, true)) {
            $errors[] = 'Categoria non valida';
        }
        
        if ($amount === false || $amount <= 0) {
            $errors[] = 'Importo deve essere maggiore di zero';
        }
        
        if (empty($description)) {
            $errors[] = 'Descrizione richiesta';
        } elseif (strlen($description) > 255) {
            $errors[] = 'Descrizione troppo lunga (max 255 caratteri)';
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
            $errors[] = 'Formato data non valido';
        }

        if (empty($errors)) {
            // Verifica che la spesa esista e appartenga all'utente
            $stmt = $pdo->prepare("SELECT id FROM expenses WHERE id = ? AND user_id = 1");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                // Sanifica la descrizione
                $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                
                $stmt = $pdo->prepare("
                    UPDATE expenses 
                    SET category = ?, amount = ?, description = ?, expense_date = ? 
                    WHERE id = ? AND user_id = 1
                ");
                
                $stmt->execute([$category, $amount, $description, $expense_date, $id]);
                
                echo json_encode([
                    'status' => 'updated',
                    'message' => 'Spesa aggiornata con successo'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Spesa non trovata']);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error', 
                'message' => 'Dati non validi per modifica',
                'errors' => $errors
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore nell\'aggiornamento']);
    }
}
?>