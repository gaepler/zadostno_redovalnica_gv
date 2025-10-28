<?php
// Start session on every page that includes the header.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zadostno</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar-custom { background-color: #003366; } /* Navy Blue */
        .navbar-custom .navbar-brand, .navbar-custom .nav-link { color: #ffffff; }
        .btn-primary { background-color: #003366; border-color: #003366; }
        .btn-primary:hover { background-color: #004488; border-color: #004488; }
        .card { border: 1px solid #silver; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Zadostno</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Nadzorna plošča</a>
                    </li>
                    
                    <?php if ($_SESSION['user_vloga'] === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Upravljanje
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownAdmin">
                            <li><a class="dropdown-item" href="admin_users.php">Uporabniki</a></li>
                            <li><a class="dropdown-item" href="admin_courses.php">Tečaji</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Links for logged-in users -->
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Odjava</a>
                    </li>
                <?php else: ?>
                    <!-- Links for guests -->
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Prijava</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Registracija</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container mt-5">