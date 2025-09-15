<?php
$input = 'admin123';
$hash = password_hash($input, PASSWORD_DEFAULT);
echo "Hash: " . $hash;

echo "<br>";

if (password_verify($input, $hash)) {
    echo "Cocok!";
} else {
    echo "Tidak cocok!";
}

echo "<br>";
echo password_hash('super123', PASSWORD_DEFAULT);
?>
