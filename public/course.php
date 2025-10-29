<?php
session_start();

// --- 1. ZAŠČITA STRANI IN KONTROLA DOSTOPA ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$course_id = $_GET['id'] ?? null;
if (!$course_id) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_vloga = $_SESSION['user_vloga'];
$has_access = false;

try {
    $pdo = Database::getInstance()->getConnection();

    // Preveri, ali ima uporabnik dostop do tečaja
    if ($user_vloga === 'admin') {
        $has_access = true;
    } elseif ($user_vloga === 'teacher') {
        $stmt = $pdo->prepare("SELECT 1 FROM ucitelji_tecajev WHERE id_ucitelja = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) $has_access = true;
    } elseif ($user_vloga === 'student') {
        $stmt = $pdo->prepare("SELECT 1 FROM vpisi WHERE id_studenta = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) $has_access = true;
    }

    if (!$has_access) {
        $_SESSION['error_message'] = 'Do tega tečaja nimate dostopa.';
        header('Location: dashboard.php');
        exit();
    }

    // --- 2. PRIDOBIVANJE PODATKOV O TEČAJU IN GRADIVIH ---
    $stmt_course = $pdo->prepare("SELECT * FROM tecaji WHERE id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch();

    if (!$course) {
        // Če tečaj ne obstaja, preusmeri
        header('Location: dashboard.php');
        exit();
    }

    // Pridobi gradiva
    $stmt_materials = $pdo->prepare("SELECT * FROM gradiva WHERE id_tecaja = ? ORDER BY nalozeno_ob DESC");
    $stmt_materials->execute([$course_id]);
    $materials = $stmt_materials->fetchAll();

} catch (PDOException $e) {
    die("Napaka v bazi podatkov: " . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0"><?php echo htmlspecialchars($course['naziv']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($course['opis']); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Nazaj na nadzorno ploščo</a>
    </div>

    <!-- Prikaz gradiv -->
    <div class="card">
        <div class="card-header">
            <h4>Gradiva</h4>
        </div>
        <div class="card-body">
            <?php if (empty($materials)): ?>
                <p>Za ta tečaj še ni bilo dodanega nobenega gradiva.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($materials as $material): ?>
                        <li class="list-group-item">
                            <?php if ($material['tip'] === 'link'): ?>
                                <a href="<?php echo htmlspecialchars($material['vsebina']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($material['naslov']); ?> (Povezava)
                                </a>
                            <?php else: ?>
                                <!-- Povezava bo delovala v naslednjem koraku -->
                                <a href="download.php?id=<?php echo $material['id']; ?>">
                                    <?php echo htmlspecialchars($material['naslov']); ?> (Datoteka)
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>