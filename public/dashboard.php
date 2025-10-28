<?php
// Start the session to access session variables.
session_start();

// --- 1. PROTECT THE PAGE ---
// Check if the user is logged in. If not, redirect them to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include the database connection.
require_once __DIR__ . '/../src/Database.php';

// --- 2. GET USER INFORMATION ---
// Fetch the user's details from the database to display a personalized welcome message.
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT ime, priimek, vloga FROM uporabniki WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    // If there's a database error, it's best to end the session and redirect.
    error_log('Dashboard error: ' . $e->getMessage());
    header('Location: logout.php');
    exit();
}

// If the user was not found in the database (e.g., deleted), log them out.
if (!$user) {
    header('Location: logout.php');
    exit();
}

// --- 3. DISPLAY THE PAGE ---
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <div class="p-5 mb-4 bg-light rounded-3">
        <div class="container-fluid py-5">
            <h1 class="display-5 fw-bold">Pozdravljeni, <?php echo htmlspecialchars($user['ime']); ?>!</h1>
            <p class="col-md-8 fs-4">
                Uspešno ste prijavljeni v sistem Zadostno. Vaša vloga je: <strong><?php echo htmlspecialchars($user['vloga']); ?></strong>.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h2>Nadzorna plošča</h2>
            
            <?php // --- 4. ROLE-SPECIFIC CONTENT --- ?>
            <?php if ($user['vloga'] === 'admin'): ?>
                <p>Tukaj boste lahko upravljali uporabnike in tečaje.</p>
                <!-- Admin links will go here -->
            <?php elseif ($user['vloga'] === 'teacher'): ?>
                <p>Tukaj boste lahko upravljali svoje tečaje, gradiva in naloge.</p>
                <!-- Teacher links will go here -->
            <?php elseif ($user['vloga'] === 'student'): ?>
                <p>Tukaj boste videli pregled svojih tečajev, ocen in prihajajočih nalog.</p>
                <!-- Student links will go here -->
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>