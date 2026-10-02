<?php

function sanitize_allowed_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = [
        'a', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'span', 'strong', 'table', 'tbody', 'td',
        'th', 'thead', 'tr', 'u', 'ul'
    ];

    $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title'],
        'td' => ['colspan'],
        'th' => ['colspan'],
    ];

    libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $elements = [];
    foreach ($dom->getElementsByTagName('*') as $node) {
        $elements[] = $node;
    }

    foreach ($elements as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($node->tagName);

        if (!in_array($tag, $allowedTags, true)) {
            $parent = $node->parentNode;
            if ($parent !== null) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }
            continue;
        }

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (str_starts_with($name, 'on')) {
                $node->removeAttribute($name);
                continue;
            }

            $allowedForTag = $allowedAttributes[$tag] ?? [];
            if (!in_array($name, $allowedForTag, true)) {
                $node->removeAttribute($name);
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $value = trim($attribute->value);
                if ($value === '' || preg_match('/^(javascript:|vbscript:|data:)/i', $value)) {
                    $node->removeAttribute($name);
                }
            }
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);

    if ($body instanceof DOMElement) {
        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }
        return $output;
    }

    return $dom->saveHTML();
}
