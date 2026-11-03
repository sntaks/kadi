<?php

use App\Support\GitRevision;
use App\Support\SentryScrubber;
use Illuminate\Support\Facades\File;
use Sentry\Breadcrumb;
use Sentry\Event;

test('password-reset tokens and verification hashes are masked in URLs', function (string $url, string $expected) {
    expect(SentryScrubber::url($url))->toBe($expected);
})->with([
    'reset link' => ['https://kadi.online/reset-password/abc123def?email=a%40b.c', 'https://kadi.online/reset-password/[Filtered]?email=[Filtered]'],
    'verify link' => ['/email/verify/42/9f86d08?expires=1&signature=xyz', '/email/verify/[Filtered]?expires=1&signature=[Filtered]'],
    'referral' => ['/register?ref=AGENT7&utm_source=x', '/register?ref=[Filtered]&utm_source=x'],
    'promo and phone' => ['/?promo_code=WELCOME&phone=254712345678', '/?promo_code=[Filtered]&phone=[Filtered]'],
    'harmless' => ['/wallet?tab=history', '/wallet?tab=history'],
]);

test('events, transactions and breadcrumbs leave without secrets', function () {
    $event = Event::createTransaction();
    $event->setRequest(['url' => 'https://kadi.online/reset-password/tok3n', 'query_string' => 'email=a@b.c']);
    $event->setTransaction('/reset-password/tok3n');
    $event->setBreadcrumb([new Breadcrumb('info', 'navigation', 'navigation', null, ['to' => '/register?ref=AGENT7'])]);

    $event = SentryScrubber::beforeSendTransaction($event);

    expect($event->getRequest()['url'])->toBe('https://kadi.online/reset-password/[Filtered]')
        ->and($event->getRequest()['query_string'])->toBe('email=[Filtered]')
        ->and($event->getTransaction())->toBe('/reset-password/[Filtered]')
        ->and($event->getBreadcrumbs()[0]->getMetadata()['to'])->toBe('/register?ref=[Filtered]');
});

test('Livewire and cache breadcrumbs and cache spans are off by default', function () {
    expect(config('sentry.breadcrumbs.livewire'))->toBeFalse()
        ->and(config('sentry.breadcrumbs.cache'))->toBeFalse()
        ->and(config('sentry.tracing.cache'))->toBeFalse();
});

test('the release is read from .git without starting a process', function () {
    $git = storage_path('framework/testing/git-'.uniqid());
    $sha = str_repeat('a1', 20);

    mkdir($git.'/refs/heads', 0777, true);
    file_put_contents($git.'/HEAD', "ref: refs/heads/main\n");
    file_put_contents($git.'/refs/heads/main', $sha."\n");
    expect(GitRevision::head($git))->toBe($sha);

    unlink($git.'/refs/heads/main');
    file_put_contents($git.'/packed-refs', "# pack-refs\n{$sha} refs/heads/main\n");
    expect(GitRevision::head($git))->toBe($sha);

    file_put_contents($git.'/HEAD', 'not a sha');
    expect(GitRevision::head($git))->toBeNull()
        ->and(GitRevision::head($git.'/missing'))->toBeNull();

    File::deleteDirectory($git);
});
