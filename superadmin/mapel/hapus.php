<?php
include '../../koneksi.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Get the photo filename before deleting the record
        $stmt = $pdo->prepare("SELECT photo FROM mapel WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['photo'])) {
            $photoPath = '../../uploads/mapel/' . $row['photo']; // Adjust path as needed
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }

        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM mapel WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "Error deleting record: " . htmlspecialchars($e->getMessage());
    }
} else {
    header("Location: index.php?error=invalid_id");
    exit;
}
?>