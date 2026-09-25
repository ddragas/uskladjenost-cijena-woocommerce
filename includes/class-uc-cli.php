<?php

declare(strict_types=1);

/** `wp uskladjenost sync` sends the whole catalogue; `wp uskladjenost ping` checks the token. */
final class UC_CLI
{
    public function sync(): void
    {
        WP_CLI::log(UC_Sync::syncAll());
    }

    public function ping(): void
    {
        $client = UC_Client::fromSettings();
        if ($client === null) {
            WP_CLI::error('No token.');
        }
        $ping = $client->ping();
        isset($ping['error']) ? WP_CLI::error($ping['error']['message']) : WP_CLI::success('Connected: '.($ping['data']['tenant'] ?? ''));
    }
}

WP_CLI::add_command('uskladjenost', UC_CLI::class);
