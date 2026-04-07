<?php
require_once __DIR__ . '/config/init.php';

echo "=== Green Card Verification Debug ===\n\n";

// Check database connection
try {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM green_cards");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Database connected. Total green cards: " . $row['cnt'] . "\n\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit;
}

// Get a sample card
echo "Sample cards in database:\n";
$stmt = $db->prepare("SELECT card_number, registration_number, full_name, is_active FROM green_cards LIMIT 3");
$stmt->execute();
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cards)) {
    echo "No cards found. Run seed.sql first.\n";
} else {
    foreach ($cards as $idx => $card) {
        echo ($idx + 1) . ". Card: " . $card['card_number'] . "\n";
        echo "   Reg: " . $card['registration_number'] . "\n";
        echo "   Name: " . $card['full_name'] . "\n";
        echo "   Active: " . ($card['is_active'] ? 'YES' : 'NO') . "\n\n";
        
        // Test the query like verify_card.php does
        echo "   Testing query for this card...\n";
        $test_stmt = $db->prepare("
            SELECT gc.card_id, gc.card_number, gc.registration_number, 
                   gc.full_name, gc.program, gc.faculty, gc.student_photo_path,
                   gc.issue_date, gc.expiry_date, gc.academic_year, gc.semester,
                   gc.is_active,
                   u.user_id, u.admission_number, u.email
            FROM green_cards gc
            LEFT JOIN users u ON gc.user_id = u.user_id
            WHERE gc.card_number = :card OR gc.registration_number = :reg
            LIMIT 1
        ");
        $test_result = $test_stmt->execute([
            ':card' => $card['card_number'],
            ':reg' => $card['registration_number']
        ]);
        $test_record = $test_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_record) {
            echo "   ✓ Query returned: " . $test_record['full_name'] . "\n";
        } else {
            echo "   ✗ Query returned no results\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
?>
