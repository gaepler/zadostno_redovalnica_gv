<?php
// We need to start a session to be able to store messages for the user.
session_start();

// Include our database connection file.
require_once __DIR__ . '/../src/Database.php';

// An array to hold any error messages.
$errors = [];
$ime = '';
$priimek = '';
$email = '';
$telefonska_stevilka = '';

// Check if the form has been submitted by checking the request method.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the data from the form. Using trim() removes any extra whitespace.
    $ime = trim($_POST['ime'] ?? '');
    $priimek = trim($_POST['priimek'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $geslo = $_POST['geslo'] ?? '';
    $potrdi_geslo = $_POST['potrdi_geslo'] ?? '';
    $telefonska_stevilka = trim($_POST['telefonska_stevilka'] ?? '');

    // --- 1. VALIDATION ---
    // Check if the required fields are empty.
    if (empty($ime)) $errors[] = 'Ime je obvezno.';
    if (empty($priimek)) $errors[] = 'Priimek je obvezen.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Vnesite veljaven email naslov.';
    if (strlen($geslo) < 8) $errors[] = 'Geslo mora vsebovati vsaj 8 znakov.';
    if ($geslo !== $potrdi_geslo) $errors[] = 'Gesli se ne ujemata.';

    // --- 2. DATABASE OPERATIONS ---
    // Only proceed if there were no validation errors.
    if (empty($errors)) {
        // Get the single database connection instance.
        $pdo = Database::getInstance()->getConnection();

        // Check if a user with this email already exists to prevent duplicates.
        $stmt = $pdo->prepare('SELECT id FROM uporabniki WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Uporabnik s tem email naslovom že obstaja.';
        } else {
            // The user does not exist, so we can create them.
            // SECURITY: Never store plain-text passwords. Always hash them.
            $geslo_hash = password_hash($geslo, PASSWORD_BCRYPT);

            // Prepare the SQL INSERT statement.
            $sql = "INSERT INTO uporabniki (ime, priimek, email, telefonska_stevilka, geslo_hash, vloga) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Self-registered users are always 'student'.
            $vloga = 'student';

            // Execute the statement with the user's data.
            if ($stmt->execute([$ime, $priimek, $email, $telefonska_stevilka, $geslo_hash, $vloga])) {
                // Success! Store a message and redirect them to the login page.
                $_SESSION['success_message'] = 'Registracija uspešna! Sedaj se lahko prijavite.';
                header('Location: login.php');
                exit(); // Always exit after a redirect.
            } else {
                // This would happen if there was a database error.
                $errors[] = 'Prišlo je do napake pri registraciji. Poskusite znova.';
            }
        }
    }
}

// --- 3. DISPLAY THE PAGE ---
// Include the website header.
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Registracija</h3>
            </div>
            <div class="card-body">
                <?php // If there are any errors, display them in an alert box.
                if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label for="ime" class="form-label">Ime</label>
                        <input type="text" class="form-control" id="ime" name="ime" value="<?php echo htmlspecialchars($ime); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="priimek" class="form-label">Priimek</label>
                        <input type="text" class="form-control" id="priimek" name="priimek" value="<?php echo htmlspecialchars($priimek); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email naslov</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                     <div class="mb-3">
                        <label for="telefonska_stevilka" class="form-label">Telefonska številka (neobvezno)</label>
                        <input type="text" class="form-control" id="telefonska_stevilka" name="telefonska_stevilka" value="<?php echo htmlspecialchars($telefonska_stevilka); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="geslo" class="form-label">Geslo</label>
                        <input type="password" class="form-control" id="geslo" name="geslo" required>
                    </div>
                    <div class="mb-3">
                        <label for="potrdi_geslo" class="form-label">Potrdi geslo</label>
                        <input type="password" class="form-control" id="potrdi_geslo" name="potrdi_geslo" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Registracija</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php // Include the website footer.
require_once __DIR__ . '/../templates/layouts/footer.php'; ?>