<?php
session_start();

// --- 1. ADMIN ACCESS CONTROL ---
// First, check if the user is logged in. If not, redirect.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Second, check if the logged-in user has the 'admin' role. If not, redirect.
if (!isset($_SESSION['user_vloga']) || $_SESSION['user_vloga'] !== 'admin') {
    // Redirect to the main dashboard with an error message.
    $_SESSION['error_message'] = 'Nimate dovoljenja za dostop do te strani.';
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$errors = [];
$success_message = '';

// --- 2. HANDLE NEW USER CREATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = trim($_POST['ime'] ?? '');
    $priimek = trim($_POST['priimek'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $geslo = $_POST['geslo'] ?? '';
    $vloga = $_POST['vloga'] ?? '';

    // Validation
    if (empty($ime)) $errors[] = 'Ime je obvezno.';
    if (empty($priimek)) $errors[] = 'Priimek je obvezen.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Vnesite veljaven email naslov.';
    if (empty($geslo) || strlen($geslo) < 8) $errors[] = 'Geslo mora vsebovati vsaj 8 znakov.';
    if (!in_array($vloga, ['student', 'teacher'])) $errors[] = 'Izberite veljavno vlogo.';
    
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            
            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM uporabniki WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Uporabnik s tem email naslovom že obstaja.';
            } else {
                // Create user
                $geslo_hash = password_hash($geslo, PASSWORD_BCRYPT);
                $sql = "INSERT INTO uporabniki (ime, priimek, email, geslo_hash, vloga) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$ime, $priimek, $email, $geslo_hash, $vloga])) {
                    $success_message = 'Uporabnik uspešno ustvarjen.';
                } else {
                    $errors[] = 'Napaka pri ustvarjanju uporabnika.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}


// --- 3. FETCH ALL USERS FOR DISPLAY ---
try {
    $pdo = Database::getInstance()->getConnection();
    // Fetch all users except the admin him/herself.
    $stmt = $pdo->prepare('SELECT id, ime, priimek, email, vloga FROM uporabniki WHERE vloga != ? ORDER BY priimek, ime');
    $stmt->execute(['admin']);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Napaka pri pridobivanju uporabnikov: ' . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <h1 class="mb-4">Upravljanje uporabnikov</h1>

    <!-- Form for adding a new user -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Dodaj novega uporabnika</h3>
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

            <form action="admin_users.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="ime" class="form-label">Ime</label>
                        <input type="text" class="form-control" id="ime" name="ime" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="priimek" class="form-label">Priimek</label>
                        <input type="text" class="form-control" id="priimek" name="priimek" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email naslov</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="geslo" class="form-label">Začasno geslo</label>
                        <input type="password" class="form-control" id="geslo" name="geslo" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vloga" class="form-label">Vloga</label>
                        <select class="form-select" id="vloga" name="vloga" required>
                            <option value="" selected disabled>Izberi vlogo...</option>
                            <option value="student">Student</option>
                            <option value="teacher">Učitelj</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Dodaj uporabnika</button>
            </form>
        </div>
    </div>

    <!-- Table of existing users -->
    <div class="card">
        <div class="card-header">
            <h3>Obstoječi uporabniki</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ime</th>
                        <th>Priimek</th>
                        <th>Email</th>
                        <th>Vloga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['ime']); ?></td>
                            <td><?php echo htmlspecialchars($user['priimek']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['vloga']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>```