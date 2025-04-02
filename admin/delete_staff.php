<?php
session_start();

// Include your database connection file
include '../db.php';

// Check if the 'id' parameter is provided in the query string
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];

    // Prepare and execute the deletion query
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$id])) {
        // Optionally, you can set a success message in session to display on the manage page
        $_SESSION['message'] = "Staff member deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete staff member.";
    }
} else {
    $_SESSION['error'] = "No staff member specified for deletion.";
}

// Redirect back to the manage staff page
header("Location: manage_staff.php");
exit;
?>
