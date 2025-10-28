<?php
session_start();

// --- 1. KONTROLA DOSTOPA ---
// Stran je dostopna vsem prijavljenim uporabnikom, a vpisovanje je za študente
if (!isset($_SESSION['user_id'])) { // POPRAVLJENA VRSTICA: DODAN !
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$student_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

try {
    $pdo = Database::getInstance()->getConnection();

    // --- 2. OBDELAVA AKCIJ (VPIS/IZPIS) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_vloga'] === 'student') {
        $course_id = $_POST['course_id'] ?? null;

        if (isset($_POST['enroll'])) {
            $sql = "INSERT INTO vpisi (id_studenta, id_tecaja) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id, $course_id]);
            $_SESSION['success_message'] = 'Uspešno ste se vpisali v tečaj.';
        } elseif (isset($_POST['unenroll'])) {
            $sql = "DELETE FROM vpisi WHERE id_studenta = ? AND id_tecaja = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id, $course_id]);
            $_SESSION['success_message'] = 'Uspešno ste se izpisali iz tečaja.';
        }
        
        // Preusmeri, da se prepreči ponovna oddaja obrazca ob osvežitvi
        header('Location: courses.php');
        exit();
    }
    
    // Sporočilo o uspehu iz seje
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }

    // --- 3. PRIDOBIVANJE PODATKOV ZA PRIKAZ ---
    
    // Pridobi vse tečaje
    $all_courses = $pdo->query('SELECT * FROM tecaji ORDER BY naziv')->fetchAll();

    // Pridobi ID-je tečajev, v katere je študent že vpisan
    $enrolled_course_ids = [];
    if ($_SESSION['user_vloga'] === 'student') {
        $stmt = $pdo->prepare("SELECT id_tecaja FROM vpisi WHERE id_studenta = ?");
        $stmt->execute([$student_id]);
        $enrolled_course_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (PDOException $e) {
    die('Napaka v bazi podatkov: ' . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <h1 class="mb-4">Katalog tečajev</h1>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($all_courses as $course): ?>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($course['naziv']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($course['opis']); ?></p>
                    </div>
                    <?php if ($_SESSION['user_vloga'] === 'student'): ?>
                    <div class="card-footer">
                        <form action="courses.php" method="POST">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <?php if (in_array($course['id'], $enrolled_course_ids)): ?>
                                <button type="submit" name="unenroll" class="btn btn-danger w-100">Izpiši se</button>
                            <?php else: ?>
                                <button type="submit" name="enroll" class="btn btn-success w-100">Vpiši se</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>