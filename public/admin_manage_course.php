<?php
session_start();

// --- 1. KONTROLA DOSTOPA ZA ADMINA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_vloga']) || $_SESSION['user_vloga'] !== 'admin') {
    $_SESSION['error_message'] = 'Nimate dovoljenja za dostop do te strani.';
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$errors = [];
$success_message = '';
$course_id = $_GET['id'] ?? null;

// Če ID tečaja ni podan, preusmeri nazaj
if (!$course_id) {
    header('Location: admin_courses.php');
    exit();
}

try {
    $pdo = Database::getInstance()->getConnection();

    // --- 2. OBDELAVA AKCIJ (DODAJANJE, ODSTRANJEVANJE) ---

    // Obdelava POST zahtevkov (dodajanje učitelja ali študenta)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_teacher'])) {
            $teacher_id = $_POST['teacher_id'] ?? null;
            if ($teacher_id) {
                $sql = "INSERT INTO ucitelji_tecajev (id_tecaja, id_ucitelja) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$course_id, $teacher_id]);
                $success_message = 'Učitelj uspešno dodan.';
            }
        } elseif (isset($_POST['add_student'])) {
            $student_id = $_POST['student_id'] ?? null;
            if ($student_id) {
                $sql = "INSERT INTO vpisi (id_tecaja, id_studenta) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$course_id, $student_id]);
                $success_message = 'Študent uspešno vpisan.';
            }
        }
    }

    // Obdelava GET zahtevkov (odstranjevanje učitelja ali študenta)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        if ($_GET['action'] === 'remove_teacher' && isset($_GET['user_id'])) {
            $teacher_id = $_GET['user_id'];
            $sql = "DELETE FROM ucitelji_tecajev WHERE id_tecaja = ? AND id_ucitelja = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $teacher_id]);
            $success_message = 'Učitelj uspešno odstranjen.';
        } elseif ($_GET['action'] === 'remove_student' && isset($_GET['user_id'])) {
            $student_id = $_GET['user_id'];
            $sql = "DELETE FROM vpisi WHERE id_tecaja = ? AND id_studenta = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $student_id]);
            $success_message = 'Študent uspešno izpisan.';
        }
    }


    // --- 3. PRIDOBIVANJE PODATKOV ZA PRIKAZ ---

    // Podatki o tečaju
    $stmt = $pdo->prepare('SELECT * FROM tecaji WHERE id = ?');
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) {
        header('Location: admin_courses.php');
        exit();
    }

    // Dodeljeni učitelji
    $sql_teachers = "SELECT u.id, u.ime, u.priimek FROM uporabniki u JOIN ucitelji_tecajev ut ON u.id = ut.id_ucitelja WHERE ut.id_tecaja = ?";
    $stmt_teachers = $pdo->prepare($sql_teachers);
    $stmt_teachers->execute([$course_id]);
    $assigned_teachers = $stmt_teachers->fetchAll();

    // Vpisani študenti
    $sql_students = "SELECT u.id, u.ime, u.priimek FROM uporabniki u JOIN vpisi v ON u.id = v.id_studenta WHERE v.id_tecaja = ?";
    $stmt_students = $pdo->prepare($sql_students);
    $stmt_students->execute([$course_id]);
    $enrolled_students = $stmt_students->fetchAll();

    // Učitelji, ki še NISO dodeljeni
    $sql_avail_teachers = "SELECT id, ime, priimek FROM uporabniki WHERE vloga = 'teacher' AND id NOT IN (SELECT id_ucitelja FROM ucitelji_tecajev WHERE id_tecaja = ?)";
    $stmt_avail_teachers = $pdo->prepare($sql_avail_teachers);
    $stmt_avail_teachers->execute([$course_id]);
    $available_teachers = $stmt_avail_teachers->fetchAll();

    // Študenti, ki še NISO vpisani
    $sql_avail_students = "SELECT id, ime, priimek FROM uporabniki WHERE vloga = 'student' AND id NOT IN (SELECT id_studenta FROM vpisi WHERE id_tecaja = ?)";
    $stmt_avail_students = $pdo->prepare($sql_avail_students);
    $stmt_avail_students->execute([$course_id]);
    $available_students = $stmt_avail_students->fetchAll();


} catch (PDOException $e) {
    die('Napaka v bazi podatkov: ' . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <h1 class="mb-2">Upravljanje tečaja</h1>
    <h3 class="text-muted mb-4"><?php echo htmlspecialchars($course['naziv']); ?></h3>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- UČITELJI -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h4>Učitelji</h4></div>
                <div class="card-body">
                    <form action="admin_manage_course.php?id=<?php echo $course_id; ?>" method="POST" class="mb-3">
                        <div class="input-group">
                            <select name="teacher_id" class="form-select">
                                <option value="">Izberi učitelja...</option>
                                <?php foreach ($available_teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['ime'] . ' ' . $teacher['priimek']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit" name="add_teacher">Dodaj</button>
                        </div>
                    </form>
                    <ul class="list-group">
                        <?php foreach ($assigned_teachers as $teacher): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($teacher['ime'] . ' ' . $teacher['priimek']); ?>
                                <a href="admin_manage_course.php?id=<?php echo $course_id; ?>&action=remove_teacher&user_id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-danger">Odstrani</a>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($assigned_teachers)): ?>
                            <li class="list-group-item">Noben učitelj ni dodeljen.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ŠTUDENTI -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h4>Študenti</h4></div>
                <div class="card-body">
                    <form action="admin_manage_course.php?id=<?php echo $course_id; ?>" method="POST" class="mb-3">
                        <div class="input-group">
                            <select name="student_id" class="form-select">
                                <option value="">Izberi študenta...</option>
                                <?php foreach ($available_students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['ime'] . ' ' . $student['priimek']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit" name="add_student">Vpiši</button>
                        </div>
                    </form>
                    <ul class="list-group">
                        <?php foreach ($enrolled_students as $student): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($student['ime'] . ' ' . $student['priimek']); ?>
                                <a href="admin_manage_course.php?id=<?php echo $course_id; ?>&action=remove_student&user_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger">Izpiši</a>
                            </li>
                        <?php endforeach; ?>
                         <?php if (empty($enrolled_students)): ?>
                            <li class="list-group-item">Noben študent ni vpisan.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <a href="admin_courses.php" class="btn btn-secondary">Nazaj na seznam tečajev</a>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>