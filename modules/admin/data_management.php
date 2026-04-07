<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        Session::setFlash('danger', 'Invalid CSRF token.');
        header('Location: ' . BASE_URL . 'modules/admin/data_management.php');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'create_fee') {
            $programName = trim((string)($_POST['program_name'] ?? ''));
            $faculty = trim((string)($_POST['faculty'] ?? ''));
            $studentType = trim((string)($_POST['student_type'] ?? 'undergraduate'));
            $studyMode = trim((string)($_POST['study_mode'] ?? 'full_time'));
            $academicYear = trim((string)($_POST['academic_year'] ?? ''));
            $semester = trim((string)($_POST['semester'] ?? 'semester_1'));
            $tuition = (float)($_POST['tuition_amount'] ?? 0);
            $functional = (float)($_POST['functional_fees'] ?? 0);
            $other = (float)($_POST['other_fees'] ?? 0);
            $minimum = (float)($_POST['minimum_payment'] ?? 0);
            $effectiveFrom = trim((string)($_POST['effective_from'] ?? ''));

            if ($programName === '' || $faculty === '' || $academicYear === '' || $effectiveFrom === '') {
                throw new Exception('Program, faculty, academic year and effective from are required.');
            }

            $stmt = $db->prepare(
                "INSERT INTO fee_structures (
                    program_name, faculty, student_type, study_mode, academic_year, semester,
                    tuition_amount, functional_fees, other_fees, minimum_payment,
                    currency, payment_deadline, late_payment_penalty, is_active,
                    effective_from, effective_to, created_by
                ) VALUES (
                    :program_name, :faculty, :student_type, :study_mode, :academic_year, :semester,
                    :tuition_amount, :functional_fees, :other_fees, :minimum_payment,
                    :currency, :payment_deadline, :late_payment_penalty, 1,
                    :effective_from, :effective_to, :created_by
                )"
            );
            $stmt->execute([
                'program_name' => $programName,
                'faculty' => $faculty,
                'student_type' => $studentType,
                'study_mode' => $studyMode,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'tuition_amount' => $tuition,
                'functional_fees' => $functional,
                'other_fees' => $other,
                'minimum_payment' => $minimum > 0 ? $minimum : (($tuition + $functional + $other) * 0.5),
                'currency' => trim((string)($_POST['currency'] ?? 'UGX')),
                'payment_deadline' => trim((string)($_POST['payment_deadline'] ?? '')) ?: null,
                'late_payment_penalty' => (float)($_POST['late_payment_penalty'] ?? 0),
                'effective_from' => $effectiveFrom,
                'effective_to' => trim((string)($_POST['effective_to'] ?? '')) ?: null,
                'created_by' => (int)($_SESSION['user_id'] ?? 0)
            ]);

            log_activity('ADMIN_DATA_CREATE_FEE', 'Created fee structure for ' . $programName . ' / ' . $academicYear);
            Session::setFlash('success', 'Fee structure created successfully.');
        } elseif ($action === 'update_fee') {
            $feeId = (int)($_POST['fee_id'] ?? 0);
            if ($feeId <= 0) {
                throw new Exception('Invalid fee record selected.');
            }

            $stmt = $db->prepare(
                'UPDATE fee_structures
                 SET tuition_amount = :tuition_amount,
                     functional_fees = :functional_fees,
                     other_fees = :other_fees,
                     minimum_payment = :minimum_payment,
                     is_active = :is_active
                 WHERE fee_id = :fee_id'
            );
            $stmt->execute([
                'tuition_amount' => (float)($_POST['tuition_amount'] ?? 0),
                'functional_fees' => (float)($_POST['functional_fees'] ?? 0),
                'other_fees' => (float)($_POST['other_fees'] ?? 0),
                'minimum_payment' => (float)($_POST['minimum_payment'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'fee_id' => $feeId
            ]);

            log_activity('ADMIN_DATA_UPDATE_FEE', 'Updated fee structure fee_id=' . $feeId);
            Session::setFlash('success', 'Fee structure updated.');
        } elseif ($action === 'delete_fee') {
            $feeId = (int)($_POST['fee_id'] ?? 0);
            if ($feeId <= 0) {
                throw new Exception('Invalid fee record selected.');
            }

            $stmt = $db->prepare('DELETE FROM fee_structures WHERE fee_id = :fee_id');
            $stmt->execute(['fee_id' => $feeId]);

            log_activity('ADMIN_DATA_DELETE_FEE', 'Deleted fee structure fee_id=' . $feeId);
            Session::setFlash('success', 'Fee structure deleted.');
        }
    } catch (Exception $e) {
        Session::setFlash('danger', $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'modules/admin/data_management.php');
    exit;
}

$fees = $db->query('SELECT * FROM fee_structures ORDER BY fee_id DESC LIMIT 200')->fetchAll();

$page_title = 'Data Management';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Data Management</h1>
        <p>Create, update, and delete core system records.</p>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <div class="card-header"><h3>Create Fee Structure</h3></div>
        <div class="card-body">
            <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;" onsubmit="return confirm('Create this fee structure record?');">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="create_fee">

                <input class="form-control" type="text" name="program_name" placeholder="Program name" required>
                <input class="form-control" type="text" name="faculty" placeholder="Faculty" required>
                <input class="form-control" type="text" name="academic_year" placeholder="2026/2027" required>
                <input class="form-control" type="date" name="effective_from" required>
                <input class="form-control" type="number" step="0.01" min="0" name="tuition_amount" placeholder="Tuition" required>
                <input class="form-control" type="number" step="0.01" min="0" name="functional_fees" placeholder="Functional fees" value="0">
                <input class="form-control" type="number" step="0.01" min="0" name="other_fees" placeholder="Other fees" value="0">
                <input class="form-control" type="number" step="0.01" min="0" name="minimum_payment" placeholder="Minimum payment">

                <select class="form-control" name="student_type">
                    <option value="undergraduate">undergraduate</option>
                    <option value="postgraduate">postgraduate</option>
                    <option value="diploma">diploma</option>
                    <option value="certificate">certificate</option>
                </select>
                <select class="form-control" name="study_mode">
                    <option value="full_time">full_time</option>
                    <option value="part_time">part_time</option>
                    <option value="distance">distance</option>
                    <option value="evening">evening</option>
                </select>
                <select class="form-control" name="semester">
                    <option value="semester_1">semester_1</option>
                    <option value="semester_2">semester_2</option>
                    <option value="semester_3">semester_3</option>
                </select>
                <input class="form-control" type="text" name="currency" value="UGX" placeholder="Currency">
                <input class="form-control" type="date" name="payment_deadline" placeholder="Payment deadline">
                <input class="form-control" type="number" step="0.01" min="0" name="late_payment_penalty" placeholder="Late penalty" value="0">
                <input class="form-control" type="date" name="effective_to" placeholder="Effective to">

                <div style="display:flex; align-items:flex-end;">
                    <button class="btn btn-primary" type="submit">Create Record</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Fee Structure Records</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Program</th>
                            <th>Academic Year</th>
                            <th>Tuition</th>
                            <th>Functional</th>
                            <th>Other</th>
                            <th>Minimum</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fees as $fee): ?>
                            <tr>
                                <td><?php echo (int)$fee['fee_id']; ?></td>
                                <td><?php echo htmlspecialchars((string)$fee['program_name']); ?></td>
                                <td><?php echo htmlspecialchars((string)$fee['academic_year']); ?></td>
                                <td><?php echo htmlspecialchars((string)$fee['tuition_amount']); ?></td>
                                <td><?php echo htmlspecialchars((string)$fee['functional_fees']); ?></td>
                                <td><?php echo htmlspecialchars((string)$fee['other_fees']); ?></td>
                                <td><?php echo htmlspecialchars((string)$fee['minimum_payment']); ?></td>
                                <td><?php echo ((int)$fee['is_active'] === 1) ? 'Yes' : 'No'; ?></td>
                                <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;" onsubmit="return confirm('Update this record?');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="update_fee">
                                        <input type="hidden" name="fee_id" value="<?php echo (int)$fee['fee_id']; ?>">
                                        <input class="form-control" style="width:95px;" type="number" step="0.01" min="0" name="tuition_amount" value="<?php echo htmlspecialchars((string)$fee['tuition_amount']); ?>">
                                        <input class="form-control" style="width:95px;" type="number" step="0.01" min="0" name="functional_fees" value="<?php echo htmlspecialchars((string)$fee['functional_fees']); ?>">
                                        <input class="form-control" style="width:95px;" type="number" step="0.01" min="0" name="other_fees" value="<?php echo htmlspecialchars((string)$fee['other_fees']); ?>">
                                        <input class="form-control" style="width:95px;" type="number" step="0.01" min="0" name="minimum_payment" value="<?php echo htmlspecialchars((string)$fee['minimum_payment']); ?>">
                                        <label style="display:flex; align-items:center; gap:4px; margin:0;"><input type="checkbox" name="is_active" value="1" <?php echo ((int)$fee['is_active'] === 1) ? 'checked' : ''; ?>> Active</label>
                                        <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this record permanently?');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="delete_fee">
                                        <input type="hidden" name="fee_id" value="<?php echo (int)$fee['fee_id']; ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
