<?php
/**
 * Renders a <li> for a tree node (folder or file), recursively.
 */
function render_tree_node(array $node, string $currentPage): void
{
    if ($node['type'] === 'folder') {
        $containsActive = wiki_folder_contains_active($node, $currentPage);
        echo '<li>';
        echo '<details class="tree-folder"' . ($containsActive ? ' open' : '') . '>';
        echo '<summary>' . htmlspecialchars($node['name']) . '</summary>';
        echo '<ul>';
        foreach ($node['children'] as $child) {
            render_tree_node($child, $currentPage);
        }
        echo '</ul>';
        echo '</details>';
        echo '</li>';
    } else {
        // $node['path'] already ends in .md (see wiki_build_tree()) - don't append it again.
        $isActive = $node['path'] === $currentPage;
        echo '<li class="tree-file' . ($isActive ? ' active' : '') . '">';
        echo '<a href="index.php?page=' . urlencode($node['path']) . '">' . htmlspecialchars($node['name']) . '</a>';
        echo '</li>';
    }
}

function wiki_folder_contains_active(array $folder, string $currentPage): bool
{
    if ($currentPage === '') {
        return false;
    }
    foreach ($folder['children'] as $child) {
        if ($child['type'] === 'file' && $child['path'] === $currentPage) {
            return true;
        }
        if ($child['type'] === 'folder' && wiki_folder_contains_active($child, $currentPage)) {
            return true;
        }
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $articleTitle ? htmlspecialchars($articleTitle) . ' · ' : '' ?><?= htmlspecialchars(WIKI_TITLE) ?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
</head>
<body>
<div class="wiki-shell">
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo-dot"></div>
            <h1><?= htmlspecialchars(WIKI_TITLE) ?></h1>
        </div>
        <ul class="tree">
            <?php foreach ($tree as $node): ?>
                <?php render_tree_node($node, $page); ?>
            <?php endforeach; ?>
            <?php if (empty($tree)): ?>
                <li class="empty-state" style="padding:8px;">No content yet.</li>
            <?php endif; ?>
        </ul>
    </nav>

    <main class="main">
        <div class="topbar">
            <form class="search-form" method="get" action="index.php">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input type="text" name="q" placeholder="Search the wiki…" value="<?= htmlspecialchars($query) ?>" autocomplete="off">
            </form>
            <div class="user-box">
                <span>Signed in as <span class="username"><?= htmlspecialchars(wiki_current_user()) ?></span></span>
                <a class="btn-logout" href="logout.php">Sign out</a>
            </div>
        </div>

        <?php if ($mode === 'search'): ?>
            <h2 style="margin-top:0;font-size:18px;color:#fff;">
                Search results for “<?= htmlspecialchars($query) ?>”
            </h2>

            <?php if (empty($searchResults)): ?>
                <div class="empty-state">No pages matched your search.</div>
            <?php else: ?>
                <ul class="search-results-list">
                    <?php foreach ($searchResults as $result): ?>
                        <li class="search-result">
                            <a href="index.php?page=<?= urlencode($result['path']) ?>" style="text-decoration:none;">
                                <span class="result-title"><?= wiki_highlight($result['title'], $query) ?></span>
                            </a>
                            <div class="result-path"><?= htmlspecialchars($result['path']) ?></div>
                            <div class="result-snippet"><?= wiki_highlight($result['snippet'], $query) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php elseif ($mode === 'article'): ?>
            <div class="breadcrumbs">
                <a href="index.php">Home</a>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <span class="sep">/</span>
                    <?= htmlspecialchars($crumb['label']) ?>
                <?php endforeach; ?>
            </div>
            <article class="article">
                <?= $articleHtml ?>
            </article>

        <?php elseif ($mode === 'not_found'): ?>
            <div class="empty-state">
                <p>Page not found.</p>
                <p><a href="index.php">Go back home</a></p>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <p style="font-size:16px;color:var(--text-dim);">Select a page from the sidebar, or search to get started.</p>
            </div>
        <?php endif; ?>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.hljs) {
        document.querySelectorAll('.article pre.code-block code').forEach(function (block) {
            hljs.highlightElement(block);
        });
    }

    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrapper = btn.closest('.code-block-wrapper');
            var codeEl = wrapper ? wrapper.querySelector('code') : null;
            if (!codeEl) return;

            var text = codeEl.innerText;

            var markCopied = function () {
                var label = btn.querySelector('.copy-btn-label');
                var original = label.textContent;
                btn.classList.add('copied');
                label.textContent = 'Copied!';
                setTimeout(function () {
                    btn.classList.remove('copied');
                    label.textContent = original;
                }, 1600);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(markCopied).catch(function () {
                    fallbackCopy(text, markCopied);
                });
            } else {
                fallbackCopy(text, markCopied);
            }
        });
    });

    function fallbackCopy(text, onDone) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        onDone();
    }
});
</script>
</body>
</html>
