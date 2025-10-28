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
        <a class="navbar-brand" href="#">Zadostno</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/login.php">Prijava</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/register.php">Registracija</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container mt-5">