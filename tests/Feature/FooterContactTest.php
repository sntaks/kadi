<?php

use App\Support\Contact;

test('the support number is shown in local form and dialled in international form', function () {
    config()->set('kadi.support.phone', '0182473265');

    expect(Contact::phone())->toBe('0182 473 265')
        ->and(Contact::phoneInternational())->toBe('+254182473265')
        ->and(Contact::phoneHref())->toBe('tel:+254182473265');
});

test('an international support number is kept as it is', function () {
    config()->set('kadi.support.phone', '+254 182 473 265');

    expect(Contact::phoneInternational())->toBe('+254182473265')
        ->and(Contact::phone())->toBe('+254 182 473 265');
});

test('a blank support number is hidden rather than shown empty', function () {
    config()->set('kadi.support.phone', '');

    expect(Contact::phone())->toBeNull()->and(Contact::phoneHref())->toBeNull();
});

test('the footer links every social profile with its icon and opens them in a new tab', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    foreach (Contact::socials() as $social) {
        expect($html)->toContain('href="'.$social['url'].'"')
            ->toContain('Kadi on '.$social['label'].' (opens in a new tab)')
            ->toContain(asset($social['icon']));
        expect(public_path($social['icon']))->toBeFile();
    }
});

test('the footer gives one-tap call, whatsapp and email help', function () {
    config()->set('kadi.support.phone', '0182473265');

    $this->get(route('faq'))
        ->assertOk()
        ->assertSee('href="tel:+254182473265"', false)
        ->assertSee('0182 473 265')
        ->assertSee('href="'.Contact::whatsapp().'"', false)
        ->assertSee('href="mailto:'.Contact::email().'"', false)
        ->assertDontSee('790417280');
});

test('the organization structured data lists the social profiles and support phone', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('"telephone":"+254182473265"')
        ->toContain('https://www.instagram.com/kadi.online')
        ->not->toContain('"sameAs":[]');
});
