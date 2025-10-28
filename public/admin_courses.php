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
$edit_course = null; // Spremenljivka za shranjevanje podatkov o tečaju pri urejanju

try {
    $pdo = Database::getInstance()->getConnection();

    // --- 2. OBDELAVA AKCIJ (BRISANJE, USTVARJANJE, POSODABLJANJE) ---

    // Obdelava akcije BRISANJA
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $course_id_to_delete = $_GET['id'];
        $sql = "DELETE FROM tecaji WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$course_id_to_delete])) {
            $success_message = 'Tečaj je bil uspešno izbrisan.';
        } else {
            $errors[] = 'Napaka pri brisanju tečaja.';
        }
    }

    // Obdelava akcij USTVARJANJA in POSODABLJANJA
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $naziv = trim($_POST['naziv'] ?? '');
        $opis = trim($_POST['opis'] ?? '');
        $course_id = $_POST['course_id'] ?? null; // Skrito polje za posodobitve

        if (empty($naziv)) {
            $errors[] = 'Naziv tečaja je obvezen.';
        }

        if (empty($errors)) {
            if ($course_id) {
                // POSODOBI obstoječi tečaj
                $sql = "UPDATE tecaji SET naziv = ?, opis = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$naziv, $opis, $course_id])) {
                    $success_message = 'Tečaj uspešno posodobljen.';
                } else {
                    $errors[] = 'Napaka pri posodabljanju tečaja.';
                }
            } else {
                // USTVARI nov tečaj
                $sql = "INSERT INTO tecaji (naziv, opis) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$naziv, $opis])) {
                    $success_message = 'Tečaj uspešno ustvarjen.';
                } else {
                    $errors[] = 'Napaka pri ustvarjanju tečaja.';
                }
            }
        }
    }

    // --- 3. PRIPRAVA ZA PRIKAZ ---

    // Obdelava akcije UREJANJA (pridobi podatke za predizpolnitev obrazca)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $course_id_to_edit = $_GET['id'];
        $stmt = $pdo->prepare('SELECT * FROM tecaji WHERE id = ?');
        $stmt->execute([$course_id_to_edit]);
        $edit_course = $stmt->fetch();
    }

    // Pridobi vse tečaje za prikaz v seznamu
    $courses = $pdo->query('SELECT * FROM tecaji ORDER BY naziv')->fetchAll();

} catch (PDOException $e) {
    die('Napaka v bazi podatkov: ' . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <h1 class="mb-4">Upravljanje tečajev</h1>

    <!-- Obrazec za dodajanje ali urejanje tečaja -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><?php echo $edit_course ? 'Uredi tečaj' : 'Dodaj nov tečaj'; ?></h3>
        </div>
        <div class="card-body">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="admin_courses.php" method="POST">
                <!-- Skrito polje za ID pri posodabljanju -->
                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($edit_course['id'] ?? ''); ?>">
                
                <div class="mb-3">
                    <label for="naziv" class="form-label">Naziv tečaja</label>
                    <input type="text" class="form-control" id="naziv" name="naziv" value="<?php echo htmlspecialchars($edit_course['naziv'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="opis" class="form-label">Opis</label>
                    <textarea class="form-control" id="opis" name="opis" rows="3"><?php echo htmlspecialchars($edit_course['opis'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary"><?php echo $edit_course ? 'Shrani spremembe' : 'Dodaj tečaj'; ?></button>
                <?php if ($edit_course): ?>
                    <a href="admin_courses.php" class="btn btn-secondary">Prekliči urejanje</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabela obstoječih tečajev -->
    <div class="card">
        <div class="card-header">
            <h3>Obstoječi tečaji</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Naziv</th>
                        <th>Opis</th>
                        <th>Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['id']); ?></td>
                            <td><?php echo htmlspecialchars($course['naziv']); ?></td>
                            <td><?php echo htmlspecialchars($course['opis']); ?></td>
                            <td>
                                <!-- DODAN NOV GUMB -->
                                <a href="admin_manage_course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-info">Upravljaj</a>
                                <a href="admin_courses.php?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-secondary">Uredi</a>
                                <a href="admin_courses.php?action=delete&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ste prepričani, da želite izbrisati ta tečaj? Vsi povezani podatki bodo trajno odstranjeni!');">Izbriši</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>