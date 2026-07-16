<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateBroadcastToken extends Command
{
    protected $signature = 'broadcaster:token {email : Email del usuario admin}';

    protected $description = 'Genera un token para la app emisora local';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuario no encontrado.');

            return self::FAILURE;
        }

        $token = Str::random(48);
        $user->forceFill(['broadcast_token' => hash('sha256', $token)])->save();

        $this->info('Token generado para '.$user->email);
        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->comment('Guárdalo en broadcaster/config.json — no se puede recuperar después.');

        return self::SUCCESS;
    }
}
