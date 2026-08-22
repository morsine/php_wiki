<?php
/**
 * A small, dependency-free Markdown -> HTML converter.
 * Covers the common subset: headings, bold/italic, inline code, fenced
 * code blocks, blockquotes, ordered/unordered lists (including "loose"
 * lists with multi-paragraph items), links, images, horizontal rules,
 * simple tables, and paragraphs.
 *
 * Not a full CommonMark implementation, but enough for wiki-style docs.
 */

function wiki_markdown_to_html(string $md): string
{
    $md = str_replace(["\r\n", "\r"], "\n", $md);

    // --- Extract fenced code blocks first so nothing inside them gets touched ---
    $codeBlocks = [];
    $md = preg_replace_callback('/```(\S*)\n(.*?)```/s', function ($m) use (&$codeBlocks) {
        $lang = strtolower(trim($m[1]));
        $safeLang = htmlspecialchars($lang, ENT_QUOTES);
        $code = htmlspecialchars(rtrim($m[2], "\n"), ENT_QUOTES);
        $langClass = $safeLang !== '' ? "language-$safeLang" : 'language-plaintext';
        $label = $safeLang !== '' ? $safeLang : 'text';
        $token = "\x01CODEBLOCK" . count($codeBlocks) . "\x01";
        $codeBlocks[] = '<div class="code-block-wrapper">'
            . '<div class="code-block-header">'
            . '<span class="code-block-lang">' . $label . '</span>'
            . '<button type="button" class="copy-btn" aria-label="Copy code">'
            . '<span class="copy-btn-label">Copy</span>'
            . '</button>'
            . '</div>'
            . '<pre class="code-block"><code class="' . $langClass . '">' . $code . '</code></pre>'
            . '</div>';
        return $token;
    }, $md);

    $lines = explode("\n", $md);
    $count = count($lines);
    $html = [];

    $inList = null;      // 'ul' | 'ol' | null
    $liParts = [];        // paragraphs belonging to the currently-open <li>
    $liOpen = false;
    $inBlockquote = false;
    $paragraphBuffer = [];

    $isBlank = fn($l) => trim($l) === '';
    $isCodeToken = fn($l) => (bool) preg_match('/^\x01CODEBLOCK(\d+)\x01$/', trim($l));
    $isHeading = fn($l) => (bool) preg_match('/^#{1,6}\s+/', $l);
    $isHr = fn($l) => (bool) preg_match('/^\s*([-*_])\s*(\1\s*){2,}$/', $l);
    $isBlockquote = fn($l) => (bool) preg_match('/^>\s?/', $l);
    $isUl = fn($l) => (bool) preg_match('/^\s*[-*+]\s+(.*)$/', $l);
    $isOl = fn($l) => (bool) preg_match('/^\s*(\d+)\.\s+(.*)$/', $l);
    $isListItem = fn($l) => $isUl($l) || $isOl($l);
    $isIndented = fn($l) => (bool) preg_match('/^\s+\S/', $l);
    $isTableStart = function ($idx) use ($lines, $count) {
        return $idx + 1 < $count
            && strpos($lines[$idx], '|') !== false
            && preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)+\|?\s*$/', $lines[$idx + 1]);
    };

    $flushParagraph = function () use (&$paragraphBuffer, &$html) {
        if (!empty($paragraphBuffer)) {
            $text = implode(' ', $paragraphBuffer);
            $html[] = '<p>' . wiki_inline_markdown($text) . '</p>';
            $paragraphBuffer = [];
        }
    };

    $closeLi = function () use (&$liOpen, &$liParts, &$html) {
        if ($liOpen) {
            if (count($liParts) <= 1) {
                $html[] = '<li>' . ($liParts[0] ?? '') . '</li>';
            } else {
                $html[] = '<li>' . implode('', array_map(fn($p) => "<p>$p</p>", $liParts)) . '</li>';
            }
            $liParts = [];
            $liOpen = false;
        }
    };

    $closeList = function () use (&$inList, &$html, $closeLi) {
        $closeLi();
        if ($inList) {
            $html[] = "</$inList>";
            $inList = null;
        }
    };

    $closeBlockquote = function () use (&$inBlockquote, &$html) {
        if ($inBlockquote) {
            $html[] = '</blockquote>';
            $inBlockquote = false;
        }
    };

    $i = 0;

    while ($i < $count) {
        $line = $lines[$i];

        // Code block token line
        if ($isCodeToken($line)) {
            preg_match('/^\x01CODEBLOCK(\d+)\x01$/', trim($line), $m);
            $flushParagraph();
            $closeList();
            $closeBlockquote();
            $html[] = $codeBlocks[(int) $m[1]];
            $i++;
            continue;
        }

        // Blank line
        if ($isBlank($line)) {
            $flushParagraph();

            if ($inList) {
                // Look ahead past any further blank lines.
                $j = $i + 1;
                while ($j < $count && $isBlank($lines[$j])) {
                    $j++;
                }

                if ($j >= $count) {
                    $closeList();
                    $i = $j;
                    continue;
                }

                $next = $lines[$j];

                if ($isListItem($next) || $isCodeToken($next)) {
                    // List continues (or is immediately followed by a code block
                    // belonging to the current item) - just skip the blank line(s).
                    $i = $j;
                    continue;
                }

                if ($isIndented($next) && !$isHeading($next) && !$isHr($next)
                    && !$isBlockquote($next) && !$isTableStart($j)) {
                    // Indented paragraph directly continues the current list item.
                    $para = [];
                    while ($j < $count && !$isBlank($lines[$j]) && $isIndented($lines[$j])
                        && !$isListItem($lines[$j]) && !$isCodeToken($lines[$j])) {
                        $para[] = trim($lines[$j]);
                        $j++;
                    }
                    if ($liOpen) {
                        $liParts[] = wiki_inline_markdown(implode(' ', $para));
                    }
                    $i = $j;
                    continue;
                }

                // Anything else (unindented paragraph, heading, hr, etc.) ends the list.
                $closeList();
                $i = $j;
                continue;
            }

            $closeBlockquote();
            $i++;
            continue;
        }

        // Horizontal rule
        if ($isHr($line)) {
            $flushParagraph();
            $closeList();
            $closeBlockquote();
            $html[] = '<hr>';
            $i++;
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $flushParagraph();
            $closeList();
            $closeBlockquote();
            $level = strlen($m[1]);
            $html[] = "<h$level>" . wiki_inline_markdown(trim($m[2])) . "</h$level>";
            $i++;
            continue;
        }

        // Blockquote
        if ($isBlockquote($line)) {
            preg_match('/^>\s?(.*)$/', $line, $m);
            $flushParagraph();
            $closeList();
            if (!$inBlockquote) {
                $html[] = '<blockquote>';
                $inBlockquote = true;
            }
            $html[] = '<p>' . wiki_inline_markdown($m[1]) . '</p>';
            $i++;
            continue;
        }

        // Unordered list
        if ($isUl($line)) {
            preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m);
            $flushParagraph();
            $closeBlockquote();
            if ($inList !== 'ul') {
                $closeList();
                $html[] = '<ul>';
                $inList = 'ul';
            } else {
                $closeLi();
            }
            $liOpen = true;
            $liParts = [wiki_inline_markdown($m[1])];
            $i++;
            continue;
        }

        // Ordered list
        if ($isOl($line)) {
            preg_match('/^\s*(\d+)\.\s+(.*)$/', $line, $m);
            $flushParagraph();
            $closeBlockquote();
            if ($inList !== 'ol') {
                $closeList();
                $startNum = (int) $m[1];
                $html[] = $startNum !== 1 ? "<ol start=\"$startNum\">" : '<ol>';
                $inList = 'ol';
            } else {
                $closeLi();
            }
            $liOpen = true;
            $liParts = [wiki_inline_markdown($m[2])];
            $i++;
            continue;
        }

        // Simple table: header row, separator row, then data rows
        if ($isTableStart($i)) {
            $flushParagraph();
            $closeList();
            $closeBlockquote();

            $headerCells = array_map('trim', explode('|', trim($line, "| \t")));
            $html[] = '<table><thead><tr>';
            foreach ($headerCells as $cell) {
                $html[] = '<th>' . wiki_inline_markdown($cell) . '</th>';
            }
            $html[] = '</tr></thead><tbody>';

            $i += 2;
            while ($i < $count && strpos($lines[$i], '|') !== false && trim($lines[$i]) !== '') {
                $cells = array_map('trim', explode('|', trim($lines[$i], "| \t")));
                $html[] = '<tr>';
                foreach ($cells as $cell) {
                    $html[] = '<td>' . wiki_inline_markdown($cell) . '</td>';
                }
                $html[] = '</tr>';
                $i++;
            }
            $html[] = '</tbody></table>';
            continue;
        }

        // Lazy continuation: a plain text line immediately following a list
        // item (no blank line in between) extends that item's last paragraph.
        if ($liOpen) {
            $lastIdx = count($liParts) - 1;
            $liParts[$lastIdx] = rtrim($liParts[$lastIdx]) . ' ' . wiki_inline_markdown(trim($line));
            $i++;
            continue;
        }

        // Otherwise: paragraph text
        $closeList();
        $closeBlockquote();
        $paragraphBuffer[] = trim($line);
        $i++;
    }

    $flushParagraph();
    $closeList();
    $closeBlockquote();

    $result = implode("\n", $html);

    // Restore code blocks. Doing this as a final global replace (rather than
    // requiring the token to sit alone on its own line) means a token can
    // never leak through as literal text, even if earlier processing merged
    // it into a paragraph or list item.
    foreach ($codeBlocks as $idx => $blockHtml) {
        $result = str_replace("\x01CODEBLOCK{$idx}\x01", $blockHtml, $result);
    }

    return $result;
}

function wiki_inline_markdown(string $text): string
{
    // Escape HTML first so raw tags in source docs don't leak through.
    $text = htmlspecialchars($text, ENT_QUOTES);

    // Inline code
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    // Images: ![alt](src)
    $text = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', '<img alt="$1" src="$2">', $text);

    // Links: [text](href)
    $text = preg_replace('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    // Bold
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);

    // Italic
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!\w)_([^_]+)_(?!\w)/', '<em>$1</em>', $text);

    // Strikethrough
    $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);

    return $text;
}
