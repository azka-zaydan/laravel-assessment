<?php

namespace App\Services\Telegram\Commands;

use Illuminate\Contracts\Container\Container;

class CommandRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve a command and its arguments from message text.
     *
     * @return array{0: Command, 1: string[]}|null
     */
    public function resolve(string $text): ?array
    {
        if (! preg_match('/^\/(start|search|link|help|settings|cancel|nearby)(?:@\w+)?(?:\s+(.*))?$/s', trim($text), $matches)) {
            return null;
        }

        $commandName = $matches[1];
        $rawArgs = isset($matches[2]) ? trim($matches[2]) : '';
        $args = $rawArgs !== '' ? [$rawArgs] : [];

        /** @var Command $command */
        $command = match ($commandName) {
            'start' => $this->container->make(StartCommand::class),
            'help' => $this->container->make(HelpCommand::class),
            'search' => $this->container->make(SearchCommand::class),
            'link' => $this->container->make(LinkCommand::class),
            'settings' => $this->container->make(SettingsCommand::class),
            'cancel' => $this->container->make(CancelCommand::class),
            'nearby' => $this->container->make(NearbyCommand::class),
        };

        return [$command, $args];
    }
}
