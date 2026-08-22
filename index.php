<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/markdown.php';

wiki_require_login();

$tree = wiki_build_tree();
$query = trim($_GET['q'] ?? '');
$page = trim($_GET['page'] ?? '');

$mode = 'empty';
$articleHtml = '';
$articleTitle = '';
$breadcrumbs = [];
$searchResults = [];
$folderView = null;

if ($query !== '') {
    $mode = 'search';
    $searchResults = wiki_search($query);
} elseif ($page !== '') {
    $resolved = wiki_resolve_path($page);
    if ($resolved !== null && is_file($resolved)) {
        $mode = 'article';
        $raw = file_get_contents($resolved);
        $articleHtml = wiki_markdown_to_html($raw);
        $articleTitle = basename($page, '.md');
        $breadcrumbs = wiki_breadcrumbs($page);
    } else {
        $mode = 'not_found';
    }
}

require __DIR__ . '/views/wiki.php';
