<?php
/**
 * ============================================================
 * Education Hub - Download Note Handler (download_notes.php)
 * ============================================================
 * 
 * PURPOSE:
 *   Handles file downloads when user clicks "Download" on a note.
 *   Increments the download counter and serves the file.
 * 
 * HOW IT WORKS:
 *   1. Gets note ID from URL (?id=5)
 *   2. Validates the note exists in database
 *   3. Increments the downloads counter (UPDATE SET downloads + 1)
 *   4. If physical file exists → sends it as a download with proper headers
 *   5. If no file → generates a .txt file from the note's content field
 * 
 * HTTP HEADERS:
 *   - Content-Type: application/octet-stream (forces download)
 *   - Content-Disposition: attachment (tells browser to download, not display)
 *   - Content-Length: file size in bytes
 * 
 * SECURITY: requireLogin() ensures only logged-in users can download
 * ============================================================
 */

require_once 'config/functions.php';
requireLogin();

/* Get note ID from URL query parameter */
$noteId = (int)($_GET['id'] ?? 0);

/* If no ID provided, redirect back to search page */
if (!$noteId) {
    redirect('search_notes.php');
}

/* Query note details from database (prepared statement for safety) */
$stmt = $conn->prepare("SELECT * FROM notes WHERE id = ?");
$stmt->bind_param("i", $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();

/* If note doesn't exist, redirect with error */
if (!$note) {
    redirect('search_notes.php?error=not_found');
}

/* Increment download counter */
$conn->query("UPDATE notes SET downloads = downloads + 1 WHERE id = $noteId");

/* Serve the file for download */
if ($note['file_path'] && file_exists($note['file_path'])) {
    /* Physical file exists → serve it directly */
    $filePath = $note['file_path'];
    $fileName = basename($filePath);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath); // Output file contents
    exit();
} else {
    /* No physical file → generate text file from content field */
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . sanitize($note['title']) . '.txt"');

    echo "Title: " . $note['title'] . "\n";
    echo "================================\n\n";
    echo $note['content'];
    exit();
}
?>
