<?php
// Load Parsedown
require_once 'includes/Parsedown.php';

// Create instance
$Parsedown = new Parsedown();

// Markdown sample
$markdown = "
# Parsedown Test

This is **bold**, *italic*, and a list:

- One
- Two
- Three


[Visit Google](https://google.com)
";

// Render Markdown → HTML
$html = $Parsedown->text($markdown);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Parsedown Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        pre { background: #f4f4f4; padding: 10px; }
    </style>
</head>
<body>

<h1>Parsedown Test Page</h1>

<p>If Parsedown is working, you will see formatted HTML below.</p>

<hr>

<?php echo $html; ?>

</body>
</html>
