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
$user_id_to_edit = $_GET['id'] ?? null;
$user = null;

// Preusmeri, če ID uporabnika ni podan
if (!$user_id_to_edit) {
    header('Location: admin_users.php');
    exit();
}

try {
    $pdo = Database::getInstance()->getConnection();

    // --- 2. OBDELAVA POSODOBITVE PODATKOV ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ime = trim($_POST['ime'] ?? '');
        $priimek = trim($_POST['priimek'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefonska_stevilka = trim($_POST['telefonska_stevilka'] ?? '');
        $vloga = $_POST['vloga'] ?? '';
        $geslo = $_POST['geslo'] ?? ''; // Geslo je neobvezno

        // Validacija
        if (empty($ime)) $errors[] = 'Ime je obvezno.';
        if (empty($priimek)) $errors[] = 'Priimek je obvezen.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Vnesite veljaven email naslov.';
        if (!in_array($vloga, ['student', 'teacher', 'admin'])) $errors[] = 'Izberite veljavno vlogo.';

        if (empty($errors)) {
            // Posodobi uporabnika
            if (!empty($geslo)) {
                // Če je vneseno novo geslo, ga šifriraj in posodobi
                $geslo_hash = password_hash($geslo, PASSWORD_BCRYPT);
                $sql = "UPDATE uporabniki SET ime = ?, priimek = ?, email = ?, telefonska_stevilka = ?, vloga = ?, geslo_hash = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ime, $priimek, $email, $telefonska_stevilka, $vloga, $geslo_hash, $user_id_to_edit]);
            } else {
                // Če geslo ni vneseno, posodobi vse ostalo
                $sql = "UPDATE uporabniki SET ime = ?, priimek = ?, email = ?, telefonska_stevilka = ?, vloga = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ime, $priimek, $email, $telefonska_stevilka, $vloga, $user_id_to_edit]);
            }
            $success_message = 'Podatki o uporabniku so bili uspešno posodobljeni.';
        }
    }

    // --- 3. PRIDOBIVANJE PODATKOV O UPORABNIKU ZA PRIKAZ V OBRAZCU ---
    $stmt = $pdo->prepare('SELECT * FROM uporabniki WHERE id = ?');
    $stmt->execute([$user_id_to_edit]);
    $user = $stmt->fetch();

    // Če uporabnik ne obstaja, preusmeri nazaj
    if (!$user) {
        header('Location: admin_users.php');
        exit();
    }

} catch (PDOException $e) {
    die('Napaka v bazi podatkov: ' . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <h1 class="mb-4">Uredi uporabnika: <?php echo htmlspecialchars($user['ime'] . ' ' . $user['priimek']); ?></h1>

    <div class="card">
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

            <form action="admin_edit_user.php?id=<?php echo $user_id_to_edit; ?>" method="POST">
                <div class="mb-3">
                    <label for="ime" class="form-label">Ime</label>
                    <input type="text" class="form-control" id="ime" name="ime" value="<?php echo htmlspecialchars($user['ime']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="priimek" class="form-label">Priimek</label>
                    <input type="text" class="form-control" id="priimek" name="priimek" value="<?php echo htmlspecialchars($user['priimek']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email naslov</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telefonska_stevilka" class="form-label">Telefonska številka</label>
                    <input type="text" class="form-control" id="telefonska_stevilka" name="telefonska_stevilka" value="<?php echo htmlspecialchars($user['telefonska_stevilka'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="vloga" class="form-label">Vloga</label>
                    <select class="form-select" id="vloga" name="vloga" required>
                        <option value="student" <?php if ($user['vloga'] === 'student') echo 'selected'; ?>>Student</option>
                        <option value="teacher" <?php if ($user['vloga'] === 'teacher') echo 'selected'; ?>>Učitelj</option>
                        <option value="admin" <?php if ($user['vloga'] === 'admin') echo 'selected'; ?>>Admin</option>
                    </select>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="geslo" class="form-label">Novo geslo</label>
                    <input type="password" class="form-control" id="geslo" name="geslo">
                    <small class="form-text text-muted">Pustite prazno, če ne želite spremeniti gesla.</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Shrani spremembe</button>
                <a href="admin_users.php" class="btn btn-secondary">Nazaj na seznam</a>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>