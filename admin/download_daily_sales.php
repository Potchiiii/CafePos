<?php
// download_daily_sales_image.php

// URL of the page you want to convert (adjust if needed)
$url = 'http://localhost/kuyaVan/admin/daily_sales.php';

// Output file name (temporary file)
$outputFile = 'daily_sales.jpg';

// Set quality (0-100)
$quality = 80;

// Full path to wkhtmltoimage (wrap in quotes since the path contains spaces)
$wkhtmltoimagePath = '"C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltoimage.exe"';

// Optionally, if your charts take time to render, add a delay (in milliseconds)
// e.g., --javascript-delay 5000 will wait 2 seconds
$cmd = "$wkhtmltoimagePath --javascript-delay 5000 --quality $quality \"$url\" \"$outputFile\"";

// Execute the command
exec($cmd, $outputLines, $returnVar);

// Debug: Uncomment the following lines to log command output if needed
// file_put_contents('wkhtmltoimage_debug.txt', print_r($outputLines, true) . "\nReturn code: " . $returnVar);

if ($returnVar === 0 && file_exists($outputFile)) {
    // Send headers to force a download of the JPEG image
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="daily_sales.jpg"');
    header('Content-Length: ' . filesize($outputFile));
    
    // Output the image file
    readfile($outputFile);
    
    // Remove the temporary file
    unlink($outputFile);
    exit;
} else {
    echo "An error occurred while generating the image. Return code: $returnVar. Output: " . implode("\n", $outputLines);
}
?>
