<?php
// Always start the session at the top of the file.
session_start();

// Include the database connection.
require_once __DIR__ . '/../src/Database.php';

$errors = [];
$email = '';

// Check if there's a success message from the registration page.
$success_message = $_SESSION['success_message'] ?? null;
if ($success_message) {
    // Unset the message so it doesn't show again on refresh.
    unset($_SESSION['success_message']);
}

// Check if the form has been submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $geslo = $_POST['geslo'] ?? '';

    // Basic validation.
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Vnesite veljaven email naslov.';
    }
    if (empty($geslo)) {
        $errors[] = 'Geslo je obvezno.';
    }

    // If there are no validation errors, proceed with authentication.
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();

            // Find the user by their email address.
            $stmt = $pdo->prepare('SELECT id, geslo_hash, vloga FROM uporabniki WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Verify the user was found AND the password matches the hash in the database.
            if ($user && password_verify($geslo, $user['geslo_hash'])) {
                // Password is correct. Log the user in.
                
                // Regenerate the session ID for security to prevent session fixation.
                session_regenerate_id(true);

                // Store user data in the session.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_vloga'] = $user['vloga'];
                
                // Redirect to a new dashboard page.
                header('Location: dashboard.php');
                exit();
            } else {
                // Use a generic error message for security.
                // This prevents attackers from knowing if an email address is registered.
                $errors[] = 'Neveljaven email ali geslo.';
            }

        } catch (PDOException $e) {
            // Display a generic error and log the specific one for the developer.
            $errors[] = 'Prišlo je do napake. Poskusite znova.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

// Include the page header.
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Prijava</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email naslov</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="geslo" class="form-label">Geslo</label>
                        <input type="password" class="form-control" id="geslo" name="geslo" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Prijava</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>