<?php

use App\Support\LegalDocument;

dataset('legal documents', [
    'terms' => ['terms', 'legal.terms', 26],
    'privacy' => ['privacy', 'legal.privacy', 14],
]);

test('each legal document has its sections numbered 1 to N with no gaps', function (string $name, string $route, int $count) {
    $document = LegalDocument::load($name);

    expect(array_column($document->sections, 'number'))->toBe(range(1, $count))
        ->and($document->effective())->not->toBeEmpty();

    foreach ($document->sections as $section) {
        expect($section['title'])->not->toBeEmpty()
            ->and($section['html'])->not->toBeEmpty();
    }
})->with('legal documents');

test('every clause belongs to the section it sits in and has a unique anchor', function (string $name) {
    $document = LegalDocument::load($name);
    $seen = [];

    foreach ($document->sections as $section) {
        preg_match_all('/id="clause-(\d+)-(\d+)"/', $section['html'], $clauses, PREG_SET_ORDER);

        foreach ($clauses as [$id, $sectionNumber]) {
            expect((int) $sectionNumber)->toBe($section['number'], "{$id} is inside section {$section['number']}");
            expect($seen)->not->toContain($id);
            $seen[] = $id;
        }

        // Clauses run N.1, N.2, ... with no gaps, so a typo such as "9.19.1." (read as 9.19) fails here.
        $clauseNumbers = array_map(fn (array $clause) => (int) $clause[2], $clauses);
        if ($clauseNumbers !== []) {
            expect($clauseNumbers)->toBe(range(1, count($clauseNumbers)), "clauses of section {$section['number']}");
        }
    }
})->with('legal documents');

test('the legal pages render the whole document with working in-page links', function (string $name, string $route, int $count) {
    $html = $this->get(route($route))->assertOk()->getContent();
    $document = LegalDocument::load($name);

    foreach ($document->sections as $section) {
        expect($html)->toContain('id="'.$section['id'].'"');
    }

    // Contents, key points and "Section N" references must all point at anchors that exist.
    preg_match_all('/href="#((?:section|clause)-[\d-]+)"/', $html, $links);
    expect($links[1])->not->toBeEmpty();
    foreach (array_unique($links[1]) as $anchor) {
        expect($html)->toContain('id="'.$anchor.'"');
    }

    expect($html)->not->toContain('[contact-details]');
})->with('legal documents');

test('section cross-references become links', function () {
    $html = LegalDocument::load('terms')->sections[6]['html']; // 7.5 refers to Section 9

    expect($html)->toContain('<a href="#section-9" class="legal-xref">Section 9</a>');
});

test('operator and support details come from config and blank ones are hidden', function () {
    config()->set('kadi.legal', [
        'operator' => 'Kizuka Na Michezo Limited',
        'website' => 'https://kadi.online',
        'registered_address' => null,
        'licence_number' => '',
    ]);
    config()->set('kadi.support.email', 'help@example.test');
    config()->set('kadi.support.phone', '0700000000');

    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('Kizuka Na Michezo Limited')
        ->assertSee('mailto:help@example.test', false)
        ->assertSee('tel:+254700000000', false)
        ->assertSee('0700 000 000')
        ->assertDontSee('Licence number')
        ->assertDontSee('Registered address');

    config()->set('kadi.legal.licence_number', 'GRA-0001');

    $this->get(route('legal.privacy'))->assertOk()->assertSee('Licence number')->assertSee('GRA-0001');
});

test('the terms and privacy pages link to each other', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('href="'.route('legal.privacy').'"', false)
        ->assertSee('aria-current="page"', false);

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('href="'.route('legal.terms').'"', false)
        ->assertSee('Office of the Data Protection Commissioner of Kenya');
});

test('an unknown legal document is refused', function () {
    LegalDocument::load('../.env');
})->throws(InvalidArgumentException::class);
