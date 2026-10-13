<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A legal page (/terms, /privacy) read from resources/markdown/{name}.md.
 *
 * The Markdown is the single source of the wording: edit the file, not the views. Each "## N. Title"
 * heading starts a section (anchor #section-N) and each paragraph starting "N.M." becomes a clause
 * (anchor #clause-N-M), so support can link a player straight to e.g. /terms#clause-9-4.
 * "Section N" in the text links to that section. A paragraph that is exactly [contact-details] is
 * where the view puts the operator and support details from config('kadi.legal').
 */
final class LegalDocument
{
    public const CONTACT_PLACEHOLDER = '<p>[contact-details]</p>';

    private const WORDS_PER_MINUTE = 200;

    /**
     * @param  array<string, string>  $meta  front matter (title, effective, updated)
     * @param  array<int, array{id: string, number: int, title: string, html: string}>  $sections
     */
    private function __construct(
        public readonly array $meta,
        public readonly string $introHtml,
        public readonly array $sections,
        public readonly int $readingMinutes,
    ) {}

    public static function load(string $name): self
    {
        $path = resource_path("markdown/{$name}.md");

        if (! preg_match('/^[a-z-]+$/', $name) || ! is_file($path)) {
            throw new InvalidArgumentException("Unknown legal document [{$name}].");
        }

        return self::parse((string) file_get_contents($path));
    }

    public static function parse(string $markdown): self
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $meta = [];

        if (preg_match('/\A---\n(.*?)\n---\n/s', $markdown, $frontMatter)) {
            foreach (explode("\n", $frontMatter[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = array_map('trim', explode(':', $line, 2));
                    $meta[$key] = $value;
                }
            }

            $markdown = substr($markdown, strlen($frontMatter[0]));
        }

        $parts = preg_split('/^## (\d+)\.\s+(.+)$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        $intro = trim((string) array_shift($parts));

        $sections = [];
        foreach (array_chunk($parts, 3) as [$number, $title, $body]) {
            $sections[] = ['number' => (int) $number, 'title' => trim($title), 'body' => $body];
        }

        $numbers = array_column($sections, 'number');
        $sections = array_map(fn (array $section) => [
            'id' => "section-{$section['number']}",
            'number' => $section['number'],
            'title' => $section['title'],
            'html' => self::render($section['body'], $numbers),
        ], $sections);

        $introHtml = $intro === '' ? '' : self::render($intro, $numbers);
        $words = str_word_count(strip_tags($introHtml.implode(' ', array_column($sections, 'html'))));

        return new self($meta, $introHtml, $sections, max(1, (int) ceil($words / self::WORDS_PER_MINUTE)));
    }

    public function title(): string
    {
        return $this->meta['title'] ?? '';
    }

    public function effective(): ?string
    {
        return $this->meta['effective'] ?? null;
    }

    public function updated(): ?string
    {
        return $this->meta['updated'] ?? null;
    }

    /**
     * @param  array<int, int>  $sectionNumbers
     */
    private static function render(string $markdown, array $sectionNumbers): string
    {
        $html = Str::markdown(trim($markdown), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // "<p>9.4. Text" -> clause with its own anchor and a number the CSS can hang in the margin.
        $html = preg_replace_callback(
            '/<p>(\d+)\.(\d+)\.\s*/',
            fn (array $m) => sprintf(
                '<p id="clause-%1$d-%2$d" class="legal-clause"><a href="#clause-%1$d-%2$d" class="legal-clause-no">%1$d.%2$d</a> ',
                $m[1],
                $m[2],
            ),
            $html,
        );

        // Cross-references to sections that exist in this document become in-page links.
        return preg_replace_callback(
            '/\bSection (\d+)\b/',
            fn (array $m) => in_array((int) $m[1], $sectionNumbers, true)
                ? sprintf('<a href="#section-%d" class="legal-xref">Section %d</a>', $m[1], $m[1])
                : $m[0],
            $html,
        );
    }
}
