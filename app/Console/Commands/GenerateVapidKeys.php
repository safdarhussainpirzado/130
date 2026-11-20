<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:generate-vapid {--force : Overwrite existing secrets/vapid_private_key if it exists}';

    protected $description = 'Generate RFC 8292 VAPID key pair for Web Push notifications';

    public function handle(): int
    {
        $secretsDir = base_path('secrets');
        if (! is_dir($secretsDir)) {
            mkdir($secretsDir, 0700, true);
            chmod($secretsDir, 0700);
        }

        $privateKeyPath = $secretsDir.'/vapid_private_key';

        if (file_exists($privateKeyPath) && ! $this->option('force')) {
            $this->error("VAPID private key already exists at [{$privateKeyPath}].");
            $this->line('Use --force if you intentionally want to regenerate it (existing browser subscriptions will become invalid).');

            return 1;
        }

        $keys = VAPID::createVapidKeys();

        // Write private key with strict 0600 permissions
        file_put_contents($privateKeyPath, $keys['privateKey']);
        chmod($privateKeyPath, 0600);

        $this->info('✔ VAPID keypair successfully generated.');
        $this->line('');
        $this->comment('Public Key (Safe to place in .env as VAPID_PUBLIC_KEY):');
        $this->line($keys['publicKey']);
        $this->line('');
        $this->comment("Private key written securely to [{$privateKeyPath}] (mode 0600).");
        $this->warn('The private key was NOT printed to console to prevent shoulder-surfing and log leakage.');

        return 0;
    }
}
