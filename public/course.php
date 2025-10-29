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
$is_teacher_of_course = false; // Dodatna spremenljivka za preverjanje učitelja

$errors = [];
$success_message = '';

try {
    $pdo = Database::getInstance()->getConnection();

    // Preveri, ali ima uporabnik dostop do tečaja
    if ($user_vloga === 'admin') {
        $has_access = true;
    } elseif ($user_vloga === 'teacher') {
        $stmt = $pdo->prepare("SELECT 1 FROM ucitelji_tecajev WHERE id_ucitelja = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) {
            $has_access = true;
            $is_teacher_of_course = true; // Uporabnik je učitelj tega tečaja
        }
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

    // --- 2. OBDELAVA AKCIJ UČITELJA (DODAJANJE/BRISANJE GRADIV) ---
    if ($is_teacher_of_course && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Dodajanje povezave
        if (isset($_POST['add_link'])) {
            $naslov = trim($_POST['naslov'] ?? '');
            $url = trim($_POST['url'] ?? '');
            if (!empty($naslov) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $sql = "INSERT INTO gradiva (id_tecaja, naslov, tip, vsebina) VALUES (?, ?, 'link', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$course_id, $naslov, $url]);
                $success_message = 'Povezava uspešno dodana.';
            } else {
                $errors[] = 'Vnesite veljaven naslov in URL.';
            }
        }
        
        // Nalaganje datoteke
        if (isset($_POST['upload_file'])) {
            $naslov = trim($_POST['naslov_datoteke'] ?? '');
            if (!empty($naslov) && isset($_FILES['datoteka']) && $_FILES['datoteka']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['datoteka'];
                // Omejitev velikosti na 15MB
                if ($file['size'] > 15 * 1024 * 1024) {
                    $errors[] = 'Datoteka je prevelika. Največja dovoljena velikost je 15MB.';
                } else {
                    $upload_dir = __DIR__ . '/../uploads/';
                    $filename = uniqid() . '-' . basename($file['name']);
                    $destination = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $sql = "INSERT INTO gradiva (id_tecaja, naslov, tip, vsebina) VALUES (?, ?, 'file', ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$course_id, $naslov, $filename]); // V bazo shranimo samo ime datoteke
                        $success_message = 'Datoteka uspešno naložena.';
                    } else {
                        $errors[] = 'Napaka pri premikanju naložene datoteke.';
                    }
                }
            } else {
                $errors[] = 'Izberite datoteko in vnesite naslov.';
            }
        }
    }
    
    // Brisanje gradiva
    if ($is_teacher_of_course && isset($_GET['action']) && $_GET['action'] === 'delete_material' && isset($_GET['material_id'])) {
        $material_id = $_GET['material_id'];
        $stmt_mat = $pdo->prepare("SELECT * FROM gradiva WHERE id = ? AND id_tecaja = ?");
        $stmt_mat->execute([$material_id, $course_id]);
        $material_to_delete = $stmt_mat->fetch();
        
        if ($material_to_delete) {
            if ($material_to_delete['tip'] === 'file') {
                // Fizično izbriši datoteko s strežnika
                $file_path = __DIR__ . '/../uploads/' . $material_to_delete['vsebina'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            // Izbriši zapis iz baze
            $stmt_del = $pdo->prepare("DELETE FROM gradiva WHERE id = ?");
            $stmt_del->execute([$material_id]);
            $success_message = 'Gradivo uspešno izbrisano.';
        }
    }


    // --- 3. PRIDOBIVANJE PODATKOV ZA PRIKAZ ---
    $stmt_course = $pdo->prepare("SELECT * FROM tecaji WHERE id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch();

    if (!$course) { header('Location: dashboard.php'); exit(); }

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

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach($errors as $error) echo "<p class='mb-0'>" . htmlspecialchars($error) . "</p>"; ?></div>
    <?php endif; ?>

    <!-- OBRAZCI ZA UČITELJA -->
    <?php if ($is_teacher_of_course): ?>
    <div class="row mb-4">
        <!-- Obrazec za dodajanje datoteke -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h4>Naloži datoteko</h4></div>
                <div class="card-body">
                    <form action="course.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="naslov_datoteke" class="form-label">Naslov gradiva</label>
                            <input type="text" name="naslov_datoteke" id="naslov_datoteke" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="datoteka" class="form-label">Izberi datoteko (max 15MB)</label>
                            <input type="file" name="datoteka" id="datoteka" class="form-control" required>
                        </div>
                        <button type="submit" name="upload_file" class="btn btn-primary">Naloži</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- Obrazec za dodajanje povezave -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h4>Dodaj povezavo</h4></div>
                <div class="card-body">
                    <form action="course.php?id=<?php echo $course_id; ?>" method="POST">
                        <div class="mb-3">
                            <label for="naslov" class="form-label">Naslov povezave</label>
                            <input type="text" name="naslov" id="naslov" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="url" class="form-label">URL povezave</label>
                            <input type="url" name="url" id="url" class="form-control" placeholder="https://www.primer.com" required>
                        </div>
                        <button type="submit" name="add_link" class="btn btn-primary">Dodaj</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <?php if ($material['tip'] === 'link'): ?>
                                    <a href="<?php echo htmlspecialchars($material['vsebina']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($material['naslov']); ?>
                                    </a>
                                    <span class="badge bg-secondary ms-2">Povezava</span>
                                <?php else: ?>
                                    <a href="download.php?id=<?php echo $material['id']; ?>">
                                        <?php echo htmlspecialchars($material['naslov']); ?>
                                    </a>
                                    <span class="badge bg-info ms-2">Datoteka</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_teacher_of_course): ?>
                                <a href="course.php?id=<?php echo $course_id; ?>&action=delete_material&material_id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ste prepričani, da želite izbrisati to gradivo?');">Izbriši</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>