<?php
session_start();

// --- 1. KONTROLA DOSTOPA ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Prepovedan dostop.');
}

require_once __DIR__ . '/../src/Database.php';

$submission_id = $_GET['id'] ?? null;
if (!$submission_id) {
    http_response_code(400);
    exit('ID oddaje ni podan.');
}

$user_id = $_SESSION['user_id'];
$user_vloga = $_SESSION['user_vloga'];
$has_access = false;

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Pridobi podatke o oddaji, vključno z ID-jem tečaja
    $sql = "SELECT o.id, o.id_studenta, o.pot_do_datoteke, n.id_tecaja 
            FROM oddaje o 
            JOIN naloge n ON o.id_naloge = n.id 
            WHERE o.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch();

    if (!$submission) {
        http_response_code(404);
        exit('Oddaja ne obstaja.');
    }

    $course_id = $submission['id_tecaja'];

    // Preveri, ali ima uporabnik dovoljenje za prenos
    if ($user_vloga === 'admin' || $user_id == $submission['id_studenta']) {
        $has_access = true;
    } elseif ($user_vloga === 'teacher') {
        $stmt_teacher = $pdo->prepare("SELECT 1 FROM ucitelji_tecajev WHERE id_ucitelja = ? AND id_tecaja = ?");
        $stmt_teacher->execute([$user_id, $course_id]);
        if ($stmt_teacher->fetch()) {
            $has_access = true;
        }
    }

    if (!$has_access) {
        http_response_code(403);
        exit('Nimate dovoljenja za prenos te datoteke.');
    }

   
    $file_path = __DIR__ . '/../uploads/submissions/' . $submission['pot_do_datoteke'];

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('Datoteka ne obstaja na strežniku.');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($submission['pot_do_datoteke']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    flush();
    readfile($file_path);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    die("Napaka v bazi podatkov: " . $e->getMessage());
}