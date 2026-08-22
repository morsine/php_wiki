<?php

/**
 * Resolve a wiki-relative path (e.g. "folder/file.md") to an absolute path,
 * guaranteeing it stays inside WIKI_CONTENT_DIR (blocks ../ traversal).
 * Returns null if invalid or outside the content dir.
 */
function wiki_resolve_path(string $relative): ?string
{
    $relative = trim($relative, "/\\");
    if ($relative === '') {
        return null;
    }

    // Reject anything with null bytes or backslashes outright.
    if (strpos($relative, "\0") !== false) {
        return null;
    }

    $base = realpath(WIKI_CONTENT_DIR);
    $full = realpath(WIKI_CONTENT_DIR . '/' . $relative);

    if ($full === false || $base === false) {
        return null;
    }

    // Must be inside the content dir and be a .md file.
    if (strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    if (substr($full, -3) !== '.md') {
        return null;
    }

    return $full;
}

/**
 * Recursively build a tree of the content directory.
 * Returns a nested array: ['type' => 'folder', 'name' => ..., 'children' => [...]]
 */
function wiki_build_tree(string $dir = null, string $relative = ''): array
{
    $dir = $dir ?? WIKI_CONTENT_DIR;
    $items = @scandir($dir);
    $children = [];

    if ($items === false) {
        return $children;
    }

    natcasesort($items);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        $relPath = ltrim($relative . '/' . $item, '/');

        if (is_dir($path)) {
            $children[] = [
                'type' => 'folder',
                'name' => $item,
                'path' => $relPath,
                'children' => wiki_build_tree($path, $relPath),
            ];
        } elseif (is_file($path) && substr($item, -3) === '.md') {
            $children[] = [
                'type' => 'file',
                'name' => substr($item, 0, -3),
                'path' => $relPath,
            ];
        }
    }

    // Folders first, then files, both alphabetical.
    usort($children, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'folder' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    return $children;
}

/**
 * Flat list of every .md file under the content dir, with relative path + title.
 */
function wiki_flat_file_list(): array
{
    $files = [];
    $dirIterator = new RecursiveDirectoryIterator(WIKI_CONTENT_DIR, FilesystemIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dirIterator);
    $base = realpath(WIKI_CONTENT_DIR);

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'md') {
            $full = $fileInfo->getPathname();
            $rel = ltrim(str_replace($base, '', $full), '/\\');
            $rel = str_replace('\\', '/', $rel);
            $files[] = [
                'path' => $rel,
                'title' => basename($rel, '.md'),
            ];
        }
    }

    return $files;
}

/**
 * Very simple full-text search across all markdown files.
 * Returns results with a small snippet of surrounding context.
 */
function wiki_search(string $query, int $limit = 50): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $results = [];
    foreach (wiki_flat_file_list() as $file) {
        $full = WIKI_CONTENT_DIR . '/' . $file['path'];
        $content = @file_get_contents($full);
        if ($content === false) {
            continue;
        }

        $titleMatch = stripos($file['title'], $query) !== false;
        $pos = stripos($content, $query);

        if ($titleMatch || $pos !== false) {
            $snippet = '';
            if ($pos !== false) {
                $start = max(0, $pos - 60);
                $length = strlen($query) + 120;
                $snippet = substr($content, $start, $length);
                $snippet = ($start > 0 ? '…' : '') . trim($snippet) . '…';
            } else {
                $snippet = substr(trim($content), 0, 140) . '…';
            }

            $results[] = [
                'path' => $file['path'],
                'title' => $file['title'],
                'snippet' => $snippet,
                'title_match' => $titleMatch,
            ];
        }

        if (count($results) >= $limit) {
            break;
        }
    }

    // Title matches first.
    usort($results, function ($a, $b) {
        return $b['title_match'] <=> $a['title_match'];
    });

    return $results;
}

function wiki_highlight(string $text, string $query): string
{
    if ($query === '') {
        return htmlspecialchars($text, ENT_QUOTES);
    }
    $escaped = htmlspecialchars($text, ENT_QUOTES);
    $pattern = '/' . preg_quote(htmlspecialchars($query, ENT_QUOTES), '/') . '/i';
    return preg_replace($pattern, '<mark>$0</mark>', $escaped);
}

function wiki_breadcrumbs(string $relativePath): array
{
    $parts = explode('/', trim($relativePath, '/'));
    $crumbs = [];
    $accum = '';
    $last = count($parts) - 1;

    foreach ($parts as $idx => $part) {
        $accum .= ($accum ? '/' : '') . $part;
        $label = $idx === $last ? preg_replace('/\.md$/', '', $part) : $part;
        $crumbs[] = ['label' => $label, 'path' => $accum, 'is_file' => $idx === $last];
    }

    return $crumbs;
}
