<?php
session_start();

// --- 1. KONTROLA DOSTOPA ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Prepovedan dostop.');
}

require_once __DIR__ . '/../src/Database.php';

$material_id = $_GET['id'] ?? null;
if (!$material_id) {
    http_response_code(400);
    exit('ID gradiva ni podan.');
}

$user_id = $_SESSION['user_id'];
$user_vloga = $_SESSION['user_vloga'];
$has_access = false;

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Pridobi podatke o gradivu in tečaju
    $stmt = $pdo->prepare("SELECT * FROM gradiva WHERE id = ? AND tip = 'file'");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();

    if (!$material) {
        http_response_code(404);
        exit('Gradivo ne obstaja ali ni datoteka.');
    }

    $course_id = $material['id_tecaja'];

    // Preveri, ali ima uporabnik dostop do tečaja tega gradiva
    if ($user_vloga === 'admin') {
        $has_access = true;
    } elseif ($user_vloga === 'teacher') {
        $stmt = $pdo->prepare("SELECT 1 FROM ucitelji_tecajev WHERE id_ucitelja = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) $has_access = true;
    } elseif ($user_vloga === 'student') {
        $stmt = $pdo->prepare("SELECT 1 FROM vpisi WHERE id_studenta = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) $has_access = true;
    }

    if (!$has_access) {
        http_response_code(403);
        exit('Nimate dovoljenja za prenos te datoteke.');
    }

    // --- 2. VARNA POSTREŽBA DATOTEKE ---
    $file_path = __DIR__ . '/../uploads/' . $material['vsebina'];

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('Datoteka ne obstaja na strežniku.');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($material['naslov']) . '.' . pathinfo($material['vsebina'], PATHINFO_EXTENSION) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    flush(); // Počisti sistemski medpomnilnik
    readfile($file_path);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    die("Napaka v bazi podatkov: " . $e->getMessage());
}