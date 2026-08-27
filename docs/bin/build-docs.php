#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Builds docs/build/*.html from the Markdown chapters in docs/.
 *
 * Usage: php bin/build-docs.php (or: composer build, from the docs/ directory)
 *
 * Code blocks are highlighted via Torchlight (https://torchlight.dev). Set
 * TORCHLIGHT_TOKEN in docs/.env (copy .env.example) or in the environment
 * to enable it; without a token, code blocks render as plain, unhighlighted
 * text.
 */

require __DIR__.'/../vendor/autoload.php';

/**
 * A minimal `.env` loader: KEY=VALUE per line, `#` comments, blank lines
 * ignored. A real environment variable of the same name always wins, so
 * `.env` only ever supplies a default.
 */
function load_dotenv(string $path): void
{
    if (! is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

load_dotenv(__DIR__.'/../.env');

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

$docsDir = __DIR__.'/..';
$assetsDir = __DIR__.'/../templates/assets';
$siteDir = __DIR__.'/../build';

$siteName = 'Double';
$siteDescription = 'Documentation for Double, a modern, human-friendly PHP double library.';
$repoUrl = 'https://github.com/jasonmccreary/double';
$twitterUrl = 'https://x.com/gonedark';
$siteUrl = 'https://testdoublephp.com';

$torchlightToken = getenv('TORCHLIGHT_TOKEN') ?: null;

// Fixed, not environment-configurable: the site only ships a dark code
// theme, and line numbers aren't part of this design.
$torchlightTheme = 'night-owl';
$torchlightOptions = ['lineNumbers' => false];

// Search, via a hand-rolled UI (templates/search-button.html, assets/site.js)
// querying Algolia directly. The API key here is a search-only key, meant to
// be public and shipped to the browser — not a secret like TORCHLIGHT_TOKEN.
$algoliaAppId = getenv('ALGOLIA_APP_ID') ?: null;
$algoliaApiKey = getenv('ALGOLIA_SEARCH_API_KEY') ?: null;
$algoliaIndexName = getenv('ALGOLIA_INDEX_NAME') ?: null;
$algoliaConfigured = $algoliaAppId && $algoliaApiKey && $algoliaIndexName;

if (! $algoliaConfigured) {
    fwrite(STDERR, "ALGOLIA_APP_ID, ALGOLIA_SEARCH_API_KEY, and/or ALGOLIA_INDEX_NAME not set — search will be disabled.\n");
}

/**
 * Collects raw code blocks discovered while rendering, keyed by a
 * placeholder id, so they can all be sent to Torchlight in one batch
 * after every chapter has been rendered.
 */
final class CodeBlockCollector
{
    /** @var array<string, array{language: string, code: string}> */
    public array $blocks = [];

    private int $next = 0;

    public function add(string $language, string $code): string
    {
        $id = 'b'.$this->next++;
        $this->blocks[$id] = ['language' => $language, 'code' => $code];

        return $id;
    }
}

/**
 * Renders fenced code blocks as `.code-block` elements and marks each one
 * with an HTML comment so Torchlight's response can be spliced in later.
 * Blocks with no language (plain console/output samples) are left as
 * plain escaped text — there's nothing for Torchlight to highlight.
 */
final class DocsCodeRenderer implements NodeRendererInterface
{
    public function __construct(private CodeBlockCollector $collector) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        FencedCode::assertInstanceOf($node);

        $infoWords = $node->getInfoWords();
        $language = $infoWords[0] ?? '';
        $code = $node->getLiteral();
        $label = $language !== '' ? $language : 'text';

        $codeAttrs = $language !== '' ? ['class' => 'language-'.$language] : [];
        $codeElement = new HtmlElement('code', $codeAttrs, Xml::escape($code));

        $preContents = [$codeElement];
        if ($language !== '') {
            $id = $this->collector->add($language, $code);
            $preContents = ["<!--torchlight:{$id}-->", $codeElement];
        }

        return new HtmlElement('div', ['class' => 'code-block'], [
            new HtmlElement('div', ['class' => 'code-block-header'], [
                new HtmlElement('span', ['class' => 'code-lang'], Xml::escape($label)),
                new HtmlElement('button', ['type' => 'button', 'class' => 'copy-button', 'aria-label' => 'Copy code'], [
                    '<svg class="copy-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M13 7V4.5A1.5 1.5 0 0 0 11.5 3h-7A1.5 1.5 0 0 0 3 4.5v7A1.5 1.5 0 0 0 4.5 13H7" stroke="currentColor" stroke-width="1.5"/></svg>',
                    '<svg class="check-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5l4 4 8-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                ]),
            ]),
            new HtmlElement('pre', [], $preContents),
        ]);
    }
}

/**
 * A minimal client for Torchlight's HTTP API (https://api.torchlight.dev).
 * Requests are batched in groups of 30, matching Torchlight's own clients.
 */
function torchlight_highlight(array $blocks, string $token, string $theme, array $options): array
{
    $results = [];
    $ids = array_keys($blocks);

    foreach (array_chunk($ids, 30) as $chunkIds) {
        $payload = [
            'blocks' => array_map(
                static fn (string $id) => [
                    'id' => $id,
                    'language' => $blocks[$id]['language'],
                    'theme' => $theme,
                    'code' => $blocks[$id]['code'],
                ],
                $chunkIds
            ),
            'options' => $options,
        ];

        $ch = curl_init('https://api.torchlight.dev/highlight');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.$token,
                'X-Torchlight-Client: double docs build',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status >= 300) {
            fwrite(STDERR, "Torchlight request failed (HTTP {$status}): {$error}\n");

            continue;
        }

        $decoded = json_decode($response, true);
        foreach ($decoded['blocks'] ?? [] as $block) {
            $results[$block['id']] = $block;
        }
    }

    return $results;
}

/**
 * Splices Torchlight's highlighted markup into a rendered chapter body,
 * matching each `<!--torchlight:ID-->` marker left by DocsCodeRenderer.
 * Blocks Torchlight didn't return for (no token, or a failed request)
 * are left as the plain escaped code the renderer already produced.
 */
function apply_torchlight(string $html, array $blocks, array $results): string
{
    return preg_replace_callback(
        '/<!--torchlight:([a-z0-9]+)-->(<code[^>]*>)(.*?)(<\/code>)/s',
        static function (array $m) use ($blocks, $results) {
            $id = $m[1];

            if (! isset($results[$id]['highlighted'])) {
                return $m[2].$m[3].$m[4];
            }

            $language = $blocks[$id]['language'];
            $classes = trim('language-'.$language.' '.($results[$id]['classes'] ?? ''));

            return '<code class="'.htmlspecialchars($classes, ENT_QUOTES).'">'
                .$results[$id]['highlighted']
                .'</code>';
        },
        $html
    );
}

function slugify_basename(string $basename): string
{
    return (string) preg_replace('/^\d+-/', '', $basename);
}

/**
 * Splits optional YAML-ish frontmatter (a `---`-delimited block of flat
 * `key: value` lines) off the front of a Markdown file. Numbered chapters
 * carry none of this, so an absent block just returns an empty frontmatter
 * array and the untouched markdown — this only really applies to blog posts.
 */
function parse_frontmatter(string $markdown): array
{
    if (! preg_match('/^---\n(.*?)\n---\n+/s', $markdown, $match)) {
        return ['frontmatter' => [], 'body' => $markdown];
    }

    $frontmatter = [];
    foreach (explode("\n", $match[1]) as $line) {
        if (preg_match('/^([\w-]+):\s*(.*)$/', $line, $pair)) {
            $frontmatter[$pair[1]] = trim($pair[2]);
        }
    }

    return ['frontmatter' => $frontmatter, 'body' => substr($markdown, strlen($match[0]))];
}

/**
 * Pulls the plain-text content of a chapter's first "complete" `<p>` for
 * use as a page-specific meta/OG/Twitter description, instead of every
 * chapter sharing one site-wide description. Paragraphs ending in ':'
 * (introducing a list or code block) are skipped, since they read as an
 * incomplete sentence out of context — the scan moves on to the next
 * paragraph instead. Returns null if the body has no usable paragraph
 * (callers fall back to the site description).
 */
function first_paragraph_text(string $bodyHtml): ?string
{
    if (! preg_match_all('/<p>(.*?)<\/p>/s', $bodyHtml, $matches)) {
        return null;
    }

    foreach ($matches[1] as $raw) {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5);
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        if ($text !== '' && ! str_ends_with($text, ':')) {
            return $text;
        }
    }

    return null;
}

/**
 * Truncates description text to a search-snippet-friendly length, breaking
 * on a word boundary rather than mid-word.
 */
function truncate_description(string $text, int $limit = 155): string
{
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    $truncated = mb_substr($text, 0, $limit);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return rtrim($truncated, ' ,.;:').'…';
}

/**
 * Turns a chapter's output filename into the clean URL path Cloudflare
 * Pages actually serves it at (it drops the `.html` extension, and serves
 * `index.html` at the site root). Internal links are built from this, not
 * from the filename directly, so hovering a link shows the same URL the
 * browser ends up at — no `.html`-to-clean-URL redirect hop in between.
 */
function clean_url(string $htmlFile): string
{
    if ($htmlFile === 'index.html') {
        return '/';
    }

    // A section's own index.html (e.g. blog/index.html) serves at the
    // section path itself, the same way the site root does.
    if (str_ends_with($htmlFile, '/index.html')) {
        return '/'.substr($htmlFile, 0, -strlen('/index.html'));
    }

    return '/'.substr($htmlFile, 0, -strlen('.html'));
}

/**
 * Loads a template from docs/templates/{name}.html, caching it in memory
 * since every chapter re-renders the same handful of templates.
 */
function load_template(string $name): string
{
    static $cache = [];

    if (! isset($cache[$name])) {
        $cache[$name] = rtrim(file_get_contents(__DIR__.'/../templates/'.$name.'.html'), "\n");
    }

    return $cache[$name];
}

/**
 * Fills in a template's `{{PLACEHOLDER}}` tokens. Templates are plain HTML
 * files meant to be hand-edited — this is intentionally just string
 * substitution, not a templating engine.
 */
function render_template(string $name, array $vars): string
{
    $replacements = [];
    foreach ($vars as $key => $value) {
        $replacements['{{'.$key.'}}'] = $value;
    }

    return strtr(load_template($name), $replacements);
}

// --- discover chapters, in file order ---

$files = glob($docsDir.'/[0-9][0-9]-*.md');
sort($files, SORT_STRING);

if (! $files) {
    fwrite(STDERR, "No numbered docs found in {$docsDir}\n");
    exit(1);
}

$chapters = [];
foreach ($files as $index => $file) {
    $basename = basename($file, '.md');
    $slug = slugify_basename($basename);

    $chapters[] = [
        'file' => $file,
        'basename' => $basename,
        'htmlFile' => $index === 0 ? 'index.html' : $slug.'.html',
        'number' => $index + 1,
        'section' => 'Documentation',
        'title' => null,
        'bodyHtml' => null,
        'toc' => [],
    ];
}

// --- discover blog posts, newest published first ---

$postFiles = glob($docsDir.'/blog/*.md');

$posts = [];
foreach ($postFiles as $file) {
    $markdown = file_get_contents($file);
    ['frontmatter' => $frontmatter] = parse_frontmatter($markdown);

    if (empty($frontmatter['published'])) {
        fwrite(STDERR, "{$file} is missing a `published` date in its frontmatter\n");
        exit(1);
    }

    $posts[] = [
        'file' => $file,
        'basename' => basename($file, '.md'),
        'htmlFile' => 'blog/'.basename($file, '.md').'.html',
        'section' => 'Blog',
        'published' => $frontmatter['published'],
        'title' => null,
        'bodyHtml' => null,
        'toc' => [],
    ];
}

usort($posts, static fn (array $a, array $b) => $b['published'] <=> $a['published']);

$linkMap = [];
foreach ($chapters as $chapter) {
    $linkMap[$chapter['basename'].'.md'] = clean_url($chapter['htmlFile']);
}
foreach ($posts as $post) {
    $linkMap[$post['basename'].'.md'] = clean_url($post['htmlFile']);
}

// --- markdown environment ---

$collector = new CodeBlockCollector;

$environment = new Environment([
    'heading_permalink' => [
        'insert' => HeadingPermalinkProcessor::INSERT_NONE,
        'apply_id_to_heading' => true,
        'id_prefix' => '',
    ],
]);
$environment->addExtension(new CommonMarkCoreExtension);
$environment->addExtension(new GithubFlavoredMarkdownExtension);
$environment->addExtension(new HeadingPermalinkExtension);
$environment->addRenderer(FencedCode::class, new DocsCodeRenderer($collector), 10);

$converter = new MarkdownConverter($environment);

// --- render a chapter or post body, rewriting internal links as we go ---

/**
 * Renders one Markdown source file (a numbered chapter or a blog post) into
 * the page array's 'title', 'bodyHtml', 'toc', and 'description' fields.
 * Shared by both, since a blog post is just a chapter with frontmatter and
 * no place in the chapter sequence.
 */
function process_page(array $page, MarkdownConverter $converter, array $linkMap, string $siteDescription): array
{
    $markdown = file_get_contents($page['file']);
    ['frontmatter' => $frontmatter, 'body' => $markdown] = parse_frontmatter($markdown);

    if (! preg_match('/^#\s+(.+)\n+/', $markdown, $titleMatch)) {
        fwrite(STDERR, "{$page['file']} doesn't start with a top-level heading\n");
        exit(1);
    }

    $page['title'] = trim($titleMatch[1]);

    // The plain title above is reused as-is in raw-text contexts (browser
    // tab title, meta/OG tags, JSON-LD, sidebar links) where Markdown
    // syntax would just show as literal characters. The on-page <h1> is
    // the one place a title actually gets displayed as content, so it
    // alone gets a real Markdown pass — letting `backticks` in a title
    // render as code the same way they already do in an h2/h3.
    $titleHtml = (string) $converter->convert($page['title']);
    $page['titleHtml'] = trim((string) preg_replace('#^<p>(.*)</p>$#s', '$1', trim($titleHtml)));

    $body = substr($markdown, strlen($titleMatch[0]));

    $html = (string) $converter->convert($body);

    // Point links at other chapters/posts to the generated .html files
    // instead of the source .md files. Posts live one directory down, so
    // their links back up to a chapter carry a `../` prefix.
    $html = preg_replace_callback(
        '/href="(?:\.\.\/)?([\w-]+\.md)(#[\w-]+)?"/',
        static function (array $m) use ($linkMap) {
            $target = $linkMap[$m[1]] ?? $m[1];

            return 'href="'.$target.($m[2] ?? '').'"';
        },
        $html
    );

    // GFM tables need a scrollable wrapper for narrow viewports.
    $html = str_replace('<table>', '<div class="table-wrap"><table>', $html);
    $html = str_replace('</table>', '</table></div>', $html);

    preg_match_all('/<h2 id="([\w-]+)">(.*?)<\/h2>/', $html, $tocMatches, PREG_SET_ORDER);
    foreach ($tocMatches as $match) {
        $page['toc'][] = ['id' => $match[1], 'text' => $match[2]];
    }

    $page['bodyHtml'] = $html;

    if (! empty($frontmatter['description'])) {
        $page['description'] = $frontmatter['description'];
    } else {
        $paragraph = first_paragraph_text($html);
        $page['description'] = $paragraph !== null ? truncate_description($paragraph) : $siteDescription;
    }

    return $page;
}

foreach ($chapters as &$chapter) {
    $chapter = process_page($chapter, $converter, $linkMap, $siteDescription);
}
unset($chapter);

foreach ($posts as &$post) {
    $post = process_page($post, $converter, $linkMap, $siteDescription);
}
unset($post);

// --- highlight every collected code block in one batch ---

$torchlightResults = [];
if ($collector->blocks) {
    if ($torchlightToken) {
        $torchlightResults = torchlight_highlight($collector->blocks, $torchlightToken, $torchlightTheme, $torchlightOptions);
        $missing = count($collector->blocks) - count($torchlightResults);
        if ($missing > 0) {
            fwrite(STDERR, "{$missing} code block(s) were not highlighted and will render as plain text.\n");
        }
    } else {
        fwrite(STDERR, "TORCHLIGHT_TOKEN not set — code blocks will render as plain, unhighlighted text.\n");
    }
}

// --- copy hand-maintained assets (CSS, images, etc.) into the build output ---

/**
 * Recursively copies a file or directory from $from to $to.
 */
function copy_asset(string $from, string $to): void
{
    if (is_dir($from)) {
        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }

        foreach (scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            copy_asset($from.'/'.$entry, $to.'/'.$entry);
        }

        return;
    }

    copy($from, $to);
}

/**
 * Recursively deletes a directory, so stale output doesn't outlive files
 * removed from the source since the last build.
 */
function remove_directory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir.'/'.$entry;
        is_dir($path) ? remove_directory($path) : unlink($path);
    }

    rmdir($dir);
}

// Wipe the whole output directory, not just assets/, so a chapter that's
// renamed or removed doesn't leave its old .html file behind as a stale,
// still-deployed orphan (which is exactly how creating-test-doubles.html
// survived the rename to creating-doubles.html and stayed live for months).
remove_directory($siteDir);
mkdir($siteDir.'/assets', 0755, true);

foreach (glob($assetsDir.'/*') as $asset) {
    copy_asset($asset, $siteDir.'/assets/'.basename($asset));
}

// These wordmark SVGs exist only for README.md's GitHub-rendered logo —
// the deployed site builds its own inline SVG wordmark (see the
// `wordmark-logo` markup in templates/page.html) — so drop them from the
// build rather than shipping assets the site never references.
foreach (['double-logo-wordmark.svg', 'double-logo-wordmark-dark.svg'] as $readmeOnlyImage) {
    unlink($siteDir.'/assets/images/'.$readmeOnlyImage);
}

// The OG image's deployed filename carries a short hash of its own bytes
// instead of a fixed name, so publishing a new image (source file keeps its
// stable, keyword-bearing name) changes the URL and busts caches on its
// own — no separate cache-control scheme needed for a file this rarely
// updated.
$ogImageSource = $siteDir.'/assets/images/test-double-php.png';
$ogImageFile = 'test-double-php-'.substr(hash('crc32b', file_get_contents($ogImageSource)), 0, 8).'.png';
rename($ogImageSource, $siteDir.'/assets/images/'.$ogImageFile);
$ogImageUrl = $siteUrl.'/assets/images/'.$ogImageFile;

// --- assemble and write each page ---

/**
 * Builds the page's JSON-LD: a TechArticle describing the chapter, plus a
 * BreadcrumbList back to the docs index. The homepage additionally
 * describes the library itself (SoftwareSourceCode) and the site
 * (WebSite), since that's the one page search engines are most likely to
 * treat as the entity's canonical description.
 *
 * Deliberately not `JSON_UNESCAPED_SLASHES`: escaped slashes keep a
 * `</script>`-shaped substring from ever appearing literally inside the
 * script tag this gets embedded in.
 */
function build_structured_data(array $chapter, string $pageUrl, string $siteName, string $siteDescription, string $siteUrl, string $repoUrl, string $ogImageUrl): string
{
    $section = $chapter['section'] ?? 'Documentation';

    $graph = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'TechArticle',
            'headline' => $chapter['title'],
            'description' => $chapter['description'],
            'url' => $pageUrl,
            'image' => $ogImageUrl,
            ...(empty($chapter['published']) ? [] : ['datePublished' => $chapter['published']]),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $siteUrl.'/',
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            // A section's own index page (e.g. the blog archive) has the
            // section name as its title too — a two-level trail would read
            // "Blog > Blog", so it collapses to one.
            'itemListElement' => $chapter['title'] === $section
                ? [['@type' => 'ListItem', 'position' => 1, 'name' => $chapter['title'], 'item' => $pageUrl]]
                : [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $section, 'item' => $siteUrl.'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $chapter['title'], 'item' => $pageUrl],
                ],
        ],
    ];

    if ($chapter['htmlFile'] === 'index.html') {
        $graph[] = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareSourceCode',
            'name' => $siteName,
            'description' => $siteDescription,
            'codeRepository' => $repoUrl,
            'programmingLanguage' => 'PHP',
            'url' => $siteUrl.'/',
        ];
    }

    $json = json_encode($graph, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    return '<script type="application/ld+json">'.$json.'</script>';
}

function render_page(array $chapter, array $chapters, string $bodyHtml, string $siteName, string $siteDescription, string $repoUrl, string $twitterUrl, string $siteUrl, string $ogImageUrl, bool $algoliaConfigured, ?string $algoliaAppId, ?string $algoliaApiKey, ?string $algoliaIndexName, string $robotsMeta = ''): string
{
    $total = count($chapters);

    $sidebarItems = '';
    foreach ($chapters as $c) {
        $sidebarItems .= render_template('sidebar-item', [
            'HREF' => clean_url($c['htmlFile']),
            'CURRENT' => $c['htmlFile'] === $chapter['htmlFile'] ? ' aria-current="page"' : '',
            'TITLE' => htmlspecialchars($c['title'], ENT_QUOTES),
        ])."\n";
    }

    // The blog's own sidebar entry, not a per-post listing that would only
    // grow — it's just current whenever we're anywhere in that section.
    $section = $chapter['section'] ?? 'Documentation';
    $blogCurrent = $section === 'Blog' ? ' aria-current="page"' : '';

    // A section's own index page (e.g. the blog archive) isn't itself a
    // dated article — only its posts are.
    $isBlogPost = $section === 'Blog' && $chapter['title'] !== $section;
    $ogType = $isBlogPost ? 'article' : 'website';
    $articleMeta = $isBlogPost && ! empty($chapter['published'])
        ? '<meta property="article:published_time" content="'.htmlspecialchars($chapter['published'], ENT_QUOTES).'">'
        : '';

    // Synthetic pages (e.g. 404) aren't part of the chapter sequence and
    // carry no 'number', so they get no pager.
    $prevLink = '<span></span>';
    $nextLink = '';

    if (isset($chapter['number'])) {
        $prevIndex = $chapter['number'] - 2;
        $nextIndex = $chapter['number'];

        $prevLink = $prevIndex >= 0
            ? render_template('pager-link', [
                'HREF' => clean_url($chapters[$prevIndex]['htmlFile']),
                'DIRECTION_CLASS' => 'prev',
                'DIRECTION_LABEL' => '&larr; Previous',
                'TITLE' => htmlspecialchars($chapters[$prevIndex]['title'], ENT_QUOTES),
            ])
            : '<span></span>';

        $nextLink = $nextIndex < $total
            ? render_template('pager-link', [
                'HREF' => clean_url($chapters[$nextIndex]['htmlFile']),
                'DIRECTION_CLASS' => 'next',
                'DIRECTION_LABEL' => 'Next &rarr;',
                'TITLE' => htmlspecialchars($chapters[$nextIndex]['title'], ENT_QUOTES),
            ])
            : '';
    }

    $tocItems = '';
    foreach ($chapter['toc'] as $entry) {
        $tocItems .= render_template('toc-item', [
            'ID' => $entry['id'],
            'TEXT' => $entry['text'],
        ])."\n";
    }

    $tocSection = $chapter['toc']
        ? render_template('toc', ['ITEMS' => $tocItems])."\n"
        : '';

    $searchButton = $algoliaConfigured
        ? render_template('search-button', [])
        : '';

    $searchScript = $algoliaConfigured
        ? render_template('search-script', [
            'ALGOLIA_APP_ID' => json_encode($algoliaAppId, JSON_THROW_ON_ERROR),
            'ALGOLIA_SEARCH_API_KEY' => json_encode($algoliaApiKey, JSON_THROW_ON_ERROR),
            'ALGOLIA_INDEX_NAME' => json_encode($algoliaIndexName, JSON_THROW_ON_ERROR),
        ])."\n"
        : '';

    $pageUrl = rtrim($siteUrl, '/').clean_url($chapter['htmlFile']);
    $description = $chapter['description'] ?? $siteDescription;

    // Noindex pages (e.g. 404) aren't a real doc entity — skip describing
    // them to search engines rather than emitting structured data no one
    // should be crawling anyway.
    $structuredData = str_contains($robotsMeta, 'noindex')
        ? ''
        : build_structured_data($chapter, $pageUrl, $siteName, $siteDescription, $siteUrl, $repoUrl, $ogImageUrl);

    return render_template('page', [
        'TITLE' => htmlspecialchars($chapter['title'], ENT_QUOTES),
        'TITLE_HTML' => $chapter['titleHtml'] ?? htmlspecialchars($chapter['title'], ENT_QUOTES),
        'SITE_NAME' => $siteName,
        'DESCRIPTION' => htmlspecialchars($description, ENT_QUOTES),
        'REPO_URL' => $repoUrl,
        'PAGE_URL' => htmlspecialchars($pageUrl, ENT_QUOTES),
        'OG_IMAGE_URL' => htmlspecialchars($ogImageUrl, ENT_QUOTES),
        'OG_TYPE' => $ogType,
        'ARTICLE_META' => $articleMeta,
        'ROBOTS_META' => $robotsMeta,
        'STRUCTURED_DATA' => $structuredData,
        'SIDEBAR_ITEMS' => $sidebarItems,
        'BLOG_CURRENT' => $blogCurrent,
        'TWITTER_URL' => htmlspecialchars($twitterUrl, ENT_QUOTES),
        'BODY' => $bodyHtml,
        'PAGER' => $prevLink.$nextLink,
        'TOC_SECTION' => $tocSection,
        'SEARCH_BUTTON' => $searchButton,
        'SEARCH_SCRIPT' => $searchScript,
    ])."\n";
}

mkdir($siteDir.'/blog', 0755, true);

foreach ($chapters as $chapter) {
    $bodyHtml = apply_torchlight($chapter['bodyHtml'], $collector->blocks, $torchlightResults);
    $page = render_page($chapter, $chapters, $bodyHtml, $siteName, $siteDescription, $repoUrl, $twitterUrl, $siteUrl, $ogImageUrl, $algoliaConfigured, $algoliaAppId, $algoliaApiKey, $algoliaIndexName);

    file_put_contents($siteDir.'/'.$chapter['htmlFile'], $page);
    echo "wrote {$chapter['htmlFile']}\n";
}

foreach ($posts as $post) {
    $bodyHtml = apply_torchlight($post['bodyHtml'], $collector->blocks, $torchlightResults);
    $page = render_page($post, $chapters, $bodyHtml, $siteName, $siteDescription, $repoUrl, $twitterUrl, $siteUrl, $ogImageUrl, $algoliaConfigured, $algoliaAppId, $algoliaApiKey, $algoliaIndexName);

    file_put_contents($siteDir.'/'.$post['htmlFile'], $page);
    echo "wrote {$post['htmlFile']}\n";
}

// --- blog archive page: every post, newest first, no dates shown ---

$archiveItems = '';
$archiveToc = [];
foreach ($posts as $post) {
    $slug = basename($post['htmlFile'], '.html');
    $archiveItems .= render_template('archive-item', [
        'ID' => $slug,
        'HREF' => clean_url($post['htmlFile']),
        'TITLE' => htmlspecialchars($post['title'], ENT_QUOTES),
        'DESCRIPTION' => htmlspecialchars($post['description'], ENT_QUOTES),
    ])."\n";
    $archiveToc[] = ['id' => $slug, 'text' => htmlspecialchars($post['title'], ENT_QUOTES)];
}

$blogIndex = [
    'htmlFile' => 'blog/index.html',
    'section' => 'Blog',
    'published' => $posts ? $posts[0]['published'] : date('Y-m-d'),
    'title' => 'Blog',
    'description' => 'Long-form answers to real questions about Double.',
    'bodyHtml' => render_template('archive-list', ['ITEMS' => $archiveItems]),
    'toc' => $archiveToc,
];

$blogIndexPage = render_page($blogIndex, $chapters, $blogIndex['bodyHtml'], $siteName, $siteDescription, $repoUrl, $twitterUrl, $siteUrl, $ogImageUrl, $algoliaConfigured, $algoliaAppId, $algoliaApiKey, $algoliaIndexName);
file_put_contents($siteDir.'/'.$blogIndex['htmlFile'], $blogIndexPage);
echo "wrote {$blogIndex['htmlFile']}\n";

// --- robots.txt, sitemap.xml, llms.txt, llms-full.txt ---

file_put_contents($siteDir.'/robots.txt', <<<TXT
User-agent: *
Allow: /

Sitemap: {$siteUrl}/sitemap.xml

TXT);
echo "wrote robots.txt\n";

$sitemapUrls = '';
foreach ([...$chapters, ...$posts, $blogIndex] as $item) {
    $pageUrl = rtrim($siteUrl, '/').clean_url($item['htmlFile']);
    $lastmod = $item['published'] ?? date('Y-m-d', filemtime($item['file']));
    $sitemapUrls .= "  <url>\n    <loc>{$pageUrl}</loc>\n    <lastmod>{$lastmod}</lastmod>\n  </url>\n";
}
$sitemapXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$sitemapUrls}</urlset>

XML;
file_put_contents($siteDir.'/sitemap.xml', $sitemapXml);
echo "wrote sitemap.xml\n";

$llmsLinks = '';
foreach ($chapters as $chapter) {
    $pageUrl = rtrim($siteUrl, '/').clean_url($chapter['htmlFile']);
    $llmsLinks .= "- [{$chapter['title']}]({$pageUrl}): {$chapter['description']}\n";
}

$llmsPostLinks = '';
foreach ($posts as $post) {
    $pageUrl = rtrim($siteUrl, '/').clean_url($post['htmlFile']);
    $llmsPostLinks .= "- [{$post['title']}]({$pageUrl}): {$post['description']}\n";
}

$llmsTxt = <<<TXT
# {$siteName}

> {$siteDescription}

## Docs

{$llmsLinks}
## Blog

{$llmsPostLinks}
TXT;
file_put_contents($siteDir.'/llms.txt', $llmsTxt);
echo "wrote llms.txt\n";

$llmsFullSections = [];
foreach ([...$chapters, ...$posts] as $item) {
    $markdown = trim(file_get_contents($item['file']));
    $pageUrl = rtrim($siteUrl, '/').clean_url($item['htmlFile']);
    $llmsFullSections[] = "<!-- {$pageUrl} -->\n\n{$markdown}";
}
$llmsFullTxt = "# {$siteName}\n\n> {$siteDescription}\n\n".implode("\n\n---\n\n", $llmsFullSections)."\n";
file_put_contents($siteDir.'/llms-full.txt', $llmsFullTxt);
echo "wrote llms-full.txt\n";

// --- 404 page (Cloudflare Pages serves build/404.html for unmatched paths) ---

$notFoundChapter = [
    'htmlFile' => '404.html',
    'title' => 'Page Not Found',
    'description' => "The page you're looking for doesn't exist or has moved.",
    'toc' => [],
];
$notFoundBody = '<p>The page you\'re looking for doesn\'t exist or has moved. Try the '
    .'<a href="/">introduction</a> or use search to find what you need.</p>';
$notFoundPage = render_page($notFoundChapter, $chapters, $notFoundBody, $siteName, $siteDescription, $repoUrl, $twitterUrl, $siteUrl, $ogImageUrl, $algoliaConfigured, $algoliaAppId, $algoliaApiKey, $algoliaIndexName, '<meta name="robots" content="noindex">');
file_put_contents($siteDir.'/404.html', $notFoundPage);
echo "wrote 404.html\n";
