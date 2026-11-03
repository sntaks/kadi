<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the XML sitemap to public/sitemap.xml';

    public function handle(): int
    {
        Sitemap::create()
            ->add(Url::create(route('home'))->setPriority(1.0)->setChangeFrequency('daily'))
            ->add(Url::create(route('rules'))->setPriority(0.8)->setChangeFrequency('monthly'))
            ->add(Url::create(route('game-guide'))->setPriority(0.8)->setChangeFrequency('monthly'))
            ->add(Url::create(route('faq'))->setPriority(0.7)->setChangeFrequency('monthly'))
            ->add(Url::create(route('legal.terms'))->setPriority(0.3)->setChangeFrequency('yearly'))
            ->add(Url::create(route('legal.privacy'))->setPriority(0.3)->setChangeFrequency('yearly'))
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap written to public/sitemap.xml');

        return self::SUCCESS;
    }
}
