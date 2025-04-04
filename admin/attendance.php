<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    Staff Attendance Report
    <div class="container">
        <h1>Staff Attendance Report</h1>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Staff ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Check-in Time</th>
                    <th>Check-out Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch attendance data from the database
                include '../db.php'; // Include your database connection file
                $stmt = $pdo->query("SELECT * FROM attendance");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['staff_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['check_in_time']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['check_out_time']) . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
</body>
</html>