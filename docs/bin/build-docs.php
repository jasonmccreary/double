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
 * Turns a chapter's output filename into the clean URL path Cloudflare
 * Pages actually serves it at (it drops the `.html` extension, and serves
 * `index.html` at the site root). Internal links are built from this, not
 * from the filename directly, so hovering a link shows the same URL the
 * browser ends up at — no `.html`-to-clean-URL redirect hop in between.
 */
function clean_url(string $htmlFile): string
{
    return $htmlFile === 'index.html' ? '/' : '/'.substr($htmlFile, 0, -strlen('.html'));
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
        'title' => null,
        'bodyHtml' => null,
        'toc' => [],
    ];
}

$linkMap = [];
foreach ($chapters as $chapter) {
    $linkMap[$chapter['basename'].'.md'] = clean_url($chapter['htmlFile']);
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

// --- render each chapter body, rewriting internal links as we go ---

foreach ($chapters as &$chapter) {
    $markdown = file_get_contents($chapter['file']);

    if (! preg_match('/^#\s+(.+)\n+/', $markdown, $titleMatch)) {
        fwrite(STDERR, "{$chapter['file']} doesn't start with a top-level heading\n");
        exit(1);
    }

    $chapter['title'] = trim($titleMatch[1]);
    $body = substr($markdown, strlen($titleMatch[0]));

    $html = (string) $converter->convert($body);

    // Point links at other chapters to the generated .html files instead
    // of the source .md files.
    $html = preg_replace_callback(
        '/href="([\w-]+\.md)(#[\w-]+)?"/',
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
        $chapter['toc'][] = ['id' => $match[1], 'text' => $match[2]];
    }

    $chapter['bodyHtml'] = $html;
}
unset($chapter);

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

function render_page(array $chapter, array $chapters, string $bodyHtml, string $siteName, string $siteDescription, string $repoUrl, string $siteUrl, string $ogImageUrl, bool $algoliaConfigured, ?string $algoliaAppId, ?string $algoliaApiKey, ?string $algoliaIndexName): string
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

    return render_template('page', [
        'TITLE' => htmlspecialchars($chapter['title'], ENT_QUOTES),
        'SITE_NAME' => $siteName,
        'DESCRIPTION' => htmlspecialchars($siteDescription, ENT_QUOTES),
        'REPO_URL' => $repoUrl,
        'PAGE_URL' => htmlspecialchars($pageUrl, ENT_QUOTES),
        'OG_IMAGE_URL' => htmlspecialchars($ogImageUrl, ENT_QUOTES),
        'SIDEBAR_ITEMS' => $sidebarItems,
        'BODY' => $bodyHtml,
        'PAGER' => $prevLink.$nextLink,
        'TOC_SECTION' => $tocSection,
        'SEARCH_BUTTON' => $searchButton,
        'SEARCH_SCRIPT' => $searchScript,
    ])."\n";
}

foreach ($chapters as $chapter) {
    $bodyHtml = apply_torchlight($chapter['bodyHtml'], $collector->blocks, $torchlightResults);
    $page = render_page($chapter, $chapters, $bodyHtml, $siteName, $siteDescription, $repoUrl, $siteUrl, $ogImageUrl, $algoliaConfigured, $algoliaAppId, $algoliaApiKey, $algoliaIndexName);

    file_put_contents($siteDir.'/'.$chapter['htmlFile'], $page);
    echo "wrote {$chapter['htmlFile']}\n";
}
