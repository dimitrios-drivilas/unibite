<?php
// Προσωρινό script για επαναφορά της βάσης σε κατάσταση δοκιμών (Reset)
require_once 'config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Καθαρισμός εξαρτώμενων πινάκων (Αξιολογήσεις και Αιτήματα)
    $pdo->exec("DELETE FROM ratings");
    $pdo->exec("DELETE FROM requests");
    
    // 2. Καθαρισμός Αγγελιών
    $pdo->exec("DELETE FROM offers");
    
    // 3. Επαναφορά πόντων σε όλους τους χρήστες (στους 5 πόντους)
    $pdo->exec("UPDATE users SET points = 5");

    $pdo->commit();
    echo "<h1>Η βάση δεδομένων καθαρίστηκε επιτυχώς!</h1>";
    echo "<p>Όλες οι αγγελίες, τα αιτήματα και οι αξιολογήσεις διεγράφησαν.</p>";
    echo "<p>Οι πόντοι όλων των χρηστών επανήλθαν στο 5.</p>";
    echo "<a href='index.php'>Επιστροφή στην αρχική</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>Σφάλμα κατά την επαναφορά:</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>