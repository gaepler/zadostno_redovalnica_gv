<?php
session_start();

// --- 1. KONTROLA DOSTOPA (samo za učitelje tečaja in admine) ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_vloga = $_SESSION['user_vloga'];
$is_teacher_of_course = false;
$course_id = null;
$errors = [];
$success_message = '';

try {
    $pdo = Database::getInstance()->getConnection();

    // Pridobi ID tečaja iz naloge, da lahko preverimo dovoljenja
    $stmt_course = $pdo->prepare("SELECT id_tecaja FROM naloge WHERE id = ?");
    $stmt_course->execute([$assignment_id]);
    $course_id = $stmt_course->fetchColumn();

    if (!$course_id) { header('Location: dashboard.php'); exit(); }

    // Preveri, ali je uporabnik učitelj tečaja ali admin
    if ($user_vloga === 'admin') {
        $is_teacher_of_course = true;
    } elseif ($user_vloga === 'teacher') {
        $stmt_check = $pdo->prepare("SELECT 1 FROM ucitelji_tecajev WHERE id_ucitelja = ? AND id_tecaja = ?");
        $stmt_check->execute([$user_id, $course_id]);
        if ($stmt_check->fetch()) {
            $is_teacher_of_course = true;
        }
    }

    if (!$is_teacher_of_course) {
        $_SESSION['error_message'] = 'Nimate dovoljenja za dostop do te strani.';
        header('Location: dashboard.php');
        exit();
    }

    // --- 2. OBDELAVA SHRANJEVANJA OCENE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
        $submission_id = $_POST['submission_id'];
        $grade = trim($_POST['grade']);
        $feedback = trim($_POST['feedback']);

        if (empty($grade)) {
            $errors[] = 'Ocena ne sme biti prazna.';
        } else {
            // Preveri, ali ocena že obstaja
            $stmt_check_grade = $pdo->prepare("SELECT id FROM ocene WHERE id_oddaje = ?");
            $stmt_check_grade->execute([$submission_id]);
            $existing_grade = $stmt_check_grade->fetch();

            if ($existing_grade) {
                // Posodobi obstoječo oceno
                $sql = "UPDATE ocene SET id_ucitelja = ?, ocena = ?, povratna_informacija = ?, ocenjeno_ob = NOW() WHERE id = ?";
                $stmt_save = $pdo->prepare($sql);
                $stmt_save->execute([$user_id, $grade, $feedback, $existing_grade['id']]);
            } else {
                // Vstavi novo oceno
                $sql = "INSERT INTO ocene (id_oddaje, id_ucitelja, ocena, povratna_informacija) VALUES (?, ?, ?, ?)";
                $stmt_save = $pdo->prepare($sql);
                $stmt_save->execute([$submission_id, $user_id, $grade, $feedback]);
            }
            $success_message = 'Ocena uspešno shranjena.';
        }
    }

    // --- 3. PRIDOBIVANJE PODATKOV ZA PRIKAZ ---
    // Podatki o nalogi
    $stmt_ass = $pdo->prepare("SELECT * FROM naloge WHERE id = ?");
    $stmt_ass->execute([$assignment_id]);
    $assignment = $stmt_ass->fetch();

    // Vsi vpisani študenti v tečaj, skupaj z njihovimi oddajami in ocenami za to nalogo
    $sql_students = "
        SELECT 
            u.id as student_id, u.ime, u.priimek,
            o.id as submission_id, o.pot_do_datoteke, o.oddano_ob,
            oc.ocena, oc.povratna_informacija
        FROM vpisi v
        JOIN uporabniki u ON v.id_studenta = u.id
        LEFT JOIN oddaje o ON u.id = o.id_studenta AND o.id_naloge = ?
        LEFT JOIN ocene oc ON o.id = oc.id_oddaje
        WHERE v.id_tecaja = ? AND u.vloga = 'student'
        ORDER BY u.priimek, u.ime
    ";
    $stmt_students = $pdo->prepare($sql_students);
    $stmt_students->execute([$assignment_id, $course_id]);
    $students_with_submissions = $stmt_students->fetchAll();

} catch (PDOException $e) {
    die("Napaka v bazi podatkov: " . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Oddaje za nalogo</h1>
            <h3 class="text-muted"><?php echo htmlspecialchars($assignment['naslov']); ?></h3>
        </div>
        <a href="course.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">Nazaj na tečaj</a>
    </div>
    
    <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Študent</th>
                        <th>Status oddaje</th>
                        <th>Ocena in povratna informacija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students_with_submissions as $student): ?>
                    <tr>
                        <td class="align-middle"><strong><?php echo htmlspecialchars($student['priimek'] . ' ' . $student['ime']); ?></strong></td>
                        <td class="align-middle">
                            <?php if ($student['submission_id']): ?>
                                <a href="download_submission.php?id=<?php echo $student['submission_id']; ?>" class="btn btn-sm btn-info">Prenesi oddajo</a>
                                <small class="d-block text-muted">Oddano: <?php echo date('d.m.Y H:i', strtotime($student['oddano_ob'])); ?></small>
                            <?php else: ?>
                                <span class="badge bg-danger">Ni oddal/a</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['submission_id']): ?>
                                <form action="assignment_submissions.php?id=<?php echo $assignment_id; ?>" method="POST">
                                    <input type="hidden" name="submission_id" value="<?php echo $student['submission_id']; ?>">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">Ocena</span>
                                        <input type="text" name="grade" class="form-control" placeholder="npr. 5, 85%" value="<?php echo htmlspecialchars($student['ocena'] ?? ''); ?>">
                                    </div>
                                    <textarea name="feedback" class="form-control mb-2" rows="2" placeholder="Vnesite povratno informacijo..."><?php echo htmlspecialchars($student['povratna_informacija'] ?? ''); ?></textarea>
                                    <button type="submit" name="save_grade" class="btn btn-sm btn-primary">Shrani</button>
                                </form>
                            <?php else: ?>
                                <small class="text-muted">Študent še ni oddal naloge.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>