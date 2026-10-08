<?php
// Ενεργοποίηση εμφάνισης σφαλμάτων για να δούμε τι πάει λάθος
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Εκκίνηση διαδικασίας Reset...</h2>";

// Φόρτωση της σύνδεσης με τη βάση
$db_path = __DIR__ . '/config/db.php';
if (!file_exists($db_path)) {
    die("Σφάλμα: Δεν βρέθηκε το αρχείο σύνδεσης στη βάση στο: " . $db_path);
}
require_once $db_path;

try {
    // Απενεργοποίηση ελέγχων για τα Foreign Keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    echo "Τα Foreign Keys απενεργοποιήθηκαν.<br>";
    
    // Άδειασμα των πινάκων
    $pdo->exec("TRUNCATE TABLE ratings;");
    echo "Ο πίνακας ratings καθάρισε.<br>";
    
    $pdo->exec("TRUNCATE TABLE requests;");
    echo "Ο πίνακας requests καθάρισε.<br>";
    
    $pdo->exec("TRUNCATE TABLE offers;");
    echo "Ο πίνακας offers καθάρισε.<br>";
    
    // Επαναφορά πόντων χρηστών
    $pdo->exec("UPDATE users SET points = 5;");
    echo "Οι πόντοι των χρηστών επανήλθαν στο 5.<br>";
    
    // Επαναφορά ελέγχων
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h1 style='color: green;'>Το Reset ολοκληρώθηκε με απόλυτη επιτυχία!</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: red;'>Σφάλμα Βάσης Δεδομένων (PDO):</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Γενικό Σφάλμα:</h1>
";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>