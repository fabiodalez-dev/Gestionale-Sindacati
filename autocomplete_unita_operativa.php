<?php
// autocomplete_unita_operativa.php
require_once 'config.php'; // Usa require_once per evitare inclusioni multiple
checkLogin();
// Recupera i parametri
$term = $_GET['term'] ?? '';
$azienda_id = isset($_GET['azienda_id']) ? intval($_GET['azienda_id']) : 0;

if ($azienda_id > 0) {
    $query = "SELECT id, nome_unita_operativa FROM unita_operativa WHERE azienda_id = ? AND nome_unita_operativa LIKE ? ORDER BY nome_unita_operativa ASC LIMIT 10";
    $like_term = '%' . $term . '%';
    $stmt = executeQuery($query, [$azienda_id, $like_term], 'is');

    if ($stmt) {
        $result = $stmt->get_result();
        $unita = [];
        while ($row = $result->fetch_assoc()) {
            $unita[] = [
                'label' => $row['nome_unita_operativa'],
                'id' => $row['id']
            ];
        }
        echo json_encode($unita);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
