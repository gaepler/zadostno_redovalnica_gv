<?php
// Start the session and protect the page.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$errors = [];
$success_message = '';
$user_id = $_SESSION['user_id'];

// Handle form submission for updating profile information.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = trim($_POST['ime'] ?? '');
    $priimek = trim($_POST['priimek'] ?? '');
    $telefonska_stevilka = trim($_POST['telefonska_stevilka'] ?? '');

    // Validation.
    if (empty($ime)) $errors[] = 'Ime je obvezno.';
    if (empty($priimek)) $errors[] = 'Priimek je obvezen.';

    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $sql = "UPDATE uporabniki SET ime = ?, priimek = ?, telefonska_stevilka = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$ime, $priimek, $telefonska_stevilka, $user_id])) {
                $success_message = 'Profil uspešno posodobljen.';
            } else {
                $errors[] = 'Napaka pri posodabljanju profila.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Always fetch the latest user data to display in the form.
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT ime, priimek, email, telefonska_stevilka FROM uporabniki WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        // This is a safeguard. If the user doesn't exist, log them out.
        header('Location: logout.php');
        exit();
    }
} catch (PDOException $e) {
    // A critical error fetching user data.
    die('Napaka pri pridobivanju podatkov o uporabniku.');
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Uredi Profil</h3>
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

                <form action="profile.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email naslov</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small class="form-text text-muted">Email naslova ni mogoče spreminjati.</small>
                    </div>
                    <div class="mb-3">
                        <label for="ime" class="form-label">Ime</label>
                        <input type="text" class="form-control" id="ime" name="ime" value="<?php echo htmlspecialchars($user['ime']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="priimek" class="form-label">Priimek</label>
                        <input type="text" class="form-control" id="priimek" name="priimek" value="<?php echo htmlspecialchars($user['priimek']); ?>" required>
                    </div>
                     <div class="mb-3">
                        <label for="telefonska_stevilka" class="form-label">Telefonska številka</label>
                        <input type="text" class="form-control" id="telefonska_stevilka" name="telefonska_stevilka" value="<?php echo htmlspecialchars($user['telefonska_stevilka'] ?? ''); ?>">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Shrani spremembe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>