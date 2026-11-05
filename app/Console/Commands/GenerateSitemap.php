<?php

namespace App\Console\Commands;

use App\Games\GameCatalogue;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the XML sitemap to public/sitemap.xml';

    public function handle(GameCatalogue $catalogue): int
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home'))->setPriority(1.0)->setChangeFrequency('daily'))
            ->add(Url::create(route('rules'))->setPriority(0.8)->setChangeFrequency('monthly'))
            ->add(Url::create(route('game-guide'))->setPriority(0.8)->setChangeFrequency('monthly'))
            ->add(Url::create(route('faq'))->setPriority(0.7)->setChangeFrequency('monthly'))
            ->add(Url::create(route('legal.terms'))->setPriority(0.3)->setChangeFrequency('yearly'))
            ->add(Url::create(route('legal.privacy'))->setPriority(0.3)->setChangeFrequency('yearly'));

        $sitemap->add(Url::create(route('promotions'))->setPriority(0.7)->setChangeFrequency('weekly'));

        // The crawlable game pages, while they are on: every title the catalogue offers.
        if (config('platform.deep_link')) {
            $sitemap->add(Url::create(route('games.slots'))->setPriority(0.8)->setChangeFrequency('weekly'))
                ->add(Url::create(route('games.scratch'))->setPriority(0.7)->setChangeFrequency('weekly'));

            foreach ($catalogue->titles() as $title) {
                if ($url = $catalogue->pageUrl($title)) {
                    $sitemap->add(Url::create($url)->setPriority(0.6)->setChangeFrequency('weekly'));
                }
            }
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap written to public/sitemap.xml');

        return self::SUCCESS;
    }
}
