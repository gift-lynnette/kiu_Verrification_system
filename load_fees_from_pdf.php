<?php
/**
 * INSERT FEE STRUCTURES FROM KIU_Programmes in usd.pdf
 * 
 * Run this script to load fees into the fee_structures table
 * Fill in the data below from the PDF document
 */

require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fee structure data extracted from KIU_Programmes in usd.pdf
// Format: program_name, faculty, student_type, study_mode, academic_year, semester, tuition_amount, functional_fees, other_fees, minimum_payment

$feeStructures = [
    // PASTE FEE DATA HERE - Example format:
    // [
    //     'program_name' => 'Bachelor of Science in Computer Science',
    //     'faculty' => 'Science and Technology',
    //     'student_type' => 'undergraduate',
    //     'study_mode' => 'full_time',
    //     'academic_year' => '2025/2026',
    //     'semester' => 'semester_1',
    //     'tuition_amount' => 2500.00,  // USD
    //     'functional_fees' => 150.00,
    //     'other_fees' => 50.00,
    //     'minimum_payment' => 1375.00,  // 50% of total
    //     'payment_deadline' => '2025-03-31',
    //     'late_payment_penalty' => 100.00,
    //     'effective_from' => '2025-01-01',
    //     'effective_to' => '2025-12-31',
    // ],
];

try {
    $count = 0;
    
    foreach ($feeStructures as $fee) {
        // Calculate if not provided
        $total = $fee['tuition_amount'] + ($fee['functional_fees'] ?? 0) + ($fee['other_fees'] ?? 0);
        $minimum = $fee['minimum_payment'] ?? ($total * 0.5);
        
        $sql = "INSERT INTO fee_structures 
                (program_name, faculty, student_type, study_mode, academic_year, semester, 
                 tuition_amount, functional_fees, other_fees, minimum_payment, 
                 currency, payment_deadline, late_payment_penalty, effective_from, effective_to, is_active)
                VALUES 
                (:program_name, :faculty, :student_type, :study_mode, :academic_year, :semester,
                 :tuition_amount, :functional_fees, :other_fees, :minimum_payment,
                 'USD', :payment_deadline, :late_payment_penalty, :effective_from, :effective_to, 1)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':program_name' => $fee['program_name'],
            ':faculty' => $fee['faculty'],
            ':student_type' => $fee['student_type'],
            ':study_mode' => $fee['study_mode'],
            ':academic_year' => $fee['academic_year'],
            ':semester' => $fee['semester'],
            ':tuition_amount' => $fee['tuition_amount'],
            ':functional_fees' => $fee['functional_fees'] ?? 0.00,
            ':other_fees' => $fee['other_fees'] ?? 0.00,
            ':minimum_payment' => $minimum,
            ':payment_deadline' => $fee['payment_deadline'] ?? null,
            ':late_payment_penalty' => $fee['late_payment_penalty'] ?? 0.00,
            ':effective_from' => $fee['effective_from'],
            ':effective_to' => $fee['effective_to'] ?? null,
        ]);
        
        $count++;
        echo "✓ Inserted: " . $fee['program_name'] . " (" . $fee['student_type'] . ")\n";
    }
    
    echo "\n SUCCESS: {$count} fee structures inserted into the database.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $db->closeConnection();
}
