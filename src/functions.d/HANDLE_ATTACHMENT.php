<?php
echo ("HANDLE_ATTACHMENT\n");
$uploads_inbox = __DIR__ . "/../uploads.d/inbox";
$user_id = $message["author"]["id"];
$file_name = $message["file_name"];
$file_url = $message["file_url"];
echo (1);
$file_extension = strtolower(substr($file_name, strrpos($file_name, ".") + 1));
$random_file_name = $user_id . "_" . md5($file_name) . "." . $file_extension;
$random_file_path = $uploads_inbox . "/" . $random_file_name;
echo (2);
if (!file_exists($uploads_inbox)) mkdir($uploads_inbox);
echo (3);
if (file_exists($random_file_path)) unlink($random_file_path);
echo (4);
file_put_contents($random_file_path, file_get_contents($file_url));
echo (5);
// get the sha256 hash of the file
$sha256 = hash_file("sha256", $random_file_path);
echo (6);
$sha256_prefix1 = substr($sha256, 0, 2);
$sha256_prefix2 = "/" . substr($sha256, 2, 2);
$stored_path = __DIR__ . "/../uploads.d/stored/$sha256_prefix1/$sha256_prefix2/$sha256.$file_extension";
if (!file_exists(dirname($stored_path))) mkdir(dirname($stored_path), 0777, true);
echo (7);
if (!file_exists($stored_path)) copy($random_file_path, $stored_path);
echo (8);
// get the stored files sha256 hash and compare to validate the file was uploaded correctly
$stored_sha256 = hash_file("sha256", $stored_path);
if ($stored_sha256 != $sha256) {
    echo (9);
    unlink($stored_path);
    unlink($random_file_path);
    $this->sendMessage($message, ["content" => "❌ I'm sorry, but an error occurred while uploading your file $file_name Please try again."]);
    return true;
}
unlink($random_file_path);
if ($file_extension == "pdf") {
    echo ("PDF");
    $text = shell_exec("pdftotext -layout {$stored_path} -");
    return true;
} else {
    echo ("IMG");
    $text = shell_exec("tesseract {$stored_path} stdout");
}
echo (10);
$token_count = $this->promptwriter->token_count($text);
$file_type = mime_content_type($stored_path);
$file_size = filesize($stored_path);
$has_thumb = 0;
$file_content = $this->promptwriter->escape($text);
echo (11);
$this->promptwriter->query("INSERT INTO `attachments` (`sha256`, `user_id`, `file_name`, `file_type`, `file_path`, `file_size`, `has_thumb`, `file_content`, `token_count`) VALUES ('$sha256', '$user_id', '$file_name', '$file_type', '$stored_path', '$file_size', '$has_thumb', '$file_content', '$token_count')");
echo (12);
$this->sendMessage($message, ["content" => "✅ $file_name Uploaded!"]);
echo (13);
return true;
