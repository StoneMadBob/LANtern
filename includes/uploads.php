<?php
function upload_display_name(string $name, string $extension): string
{
    $name = str_replace('\\', '/', $name);
    $baseName = pathinfo(basename($name), PATHINFO_FILENAME);
    $baseName = preg_replace('/[^\pL\pN._ -]/u', '_', $baseName) ?? '';
    $baseName = trim($baseName, " .-_");
    $baseName = preg_replace('/^(.{0,180}).*$/us', '$1', $baseName) ?? '';

    if ($baseName === '') {
        $baseName = 'upload';
    }

    return $baseName . '.' . $extension;
}

function upload_plain_text_description(string $description): string
{
    $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $description = preg_replace('/<\s*\/?\s*(p|div|br|li|h[1-6]|blockquote)\b[^>]*>/i', ' ', $description) ?? $description;
    $description = strip_tags($description);
    $description = preg_replace('/\s+/u', ' ', $description) ?? $description;

    return trim($description);
}