<?php

namespace App\Support;

/**
 * Questions shown on /faq and mirrored into its FAQPage JSON-LD.
 * Keep answers factual and in sync with the rules and games-guide pages.
 */
class Faqs
{
    /**
     * @return array<int, array{q: string, a: string}>
     */
    public static function all(): array
    {
        return [
            [
                'q' => 'What is Kadi?',
                'a' => 'Kadi is a fast-paced Kenyan card game for 2 to 4 players, played with a standard 54-card deck including Jokers. The goal is to empty your hand first and call "Kadi" before you play your last cards.',
            ],
            [
                'q' => 'How do I play Kadi online?',
                'a' => 'Create a free account, verify your email, then press Play Kadi to join a table. You can play on your phone or computer straight from the browser, or install Kadi as an app on your home screen.',
            ],
            [
                'q' => 'What are the Kadi rules?',
                'a' => 'Match the top card by suit or rank, chain cards together, and use special cards such as Aces, Jokers and question cards to change the game. The full Kadi rules, card types and chaining combos are explained step by step on our rules page.',
            ],
            [
                'q' => 'Is Kadi free to join?',
                'a' => 'Yes. Signing up is free. You can deposit with M-Pesa when you want to enter stake games, tournaments and jackpots.',
            ],
            [
                'q' => 'What game modes can I play?',
                'a' => 'Kadi offers 2, 3 and 4-player matches, multi-round tournaments and jackpots. Stakes, multipliers and prize pools for each mode are listed on the games and prizes page.',
            ],
            [
                'q' => 'Can I play Kadi on my phone?',
                'a' => 'Yes. Kadi runs in your mobile browser, and you can install it to your home screen so it opens like an app. Turn your phone to landscape when the game asks you to.',
            ],
            [
                'q' => 'How do I deposit with M-Pesa?',
                'a' => 'Use the M-Pesa Paybill number 4007279, or deposit from your wallet once you are signed in and verified.',
            ],
            [
                'q' => 'What name can I use as a player?',
                'a' => 'Player names are 4 to 12 characters: letters, numbers, and the symbols _ ! - with single spaces between words. Names that impersonate staff or are offensive are not allowed, and you can change your name once a year.',
            ],
            [
                'q' => 'How do I contact support?',
                'a' => 'Call customer care on '.Contact::phone().', chat with us on WhatsApp, or email '.Contact::email().'.',
            ],
        ];
    }
}
