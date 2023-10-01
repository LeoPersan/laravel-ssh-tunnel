<?php namespace LeoPersan\Tunneler\Console;

use Illuminate\Console\Command;
use LeoPersan\Tunneler\Jobs\CreateTunnel;

class TunnelerCommand extends Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'tunneler:activate {connections?*}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and Maintains an SSH Tunnel';

    public function handle(): int
    {
        $connections = $this->hasArgument('connections') ? $this->argument('connections') : array_keys(config('tunneler.connections'));
        $return = 0;
        foreach ($connections as $connection) {
            $return |= $this->handleConnection($connection);
        }
        return $return;
    }

    /**
     * @param string $connection
     * @return int
     */
    public function handleConnection(string $connection): int
    {
        try {
            $result = dispatch_sync(new CreateTunnel($connection));
        } catch (\ErrorException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($result === 1) {
            $this->info('The Tunnel is already Activated.');
            return 0;
        }

        if ($result === 2) {
            $this->info('The Tunnel has been Activated.');
            return 0;
        }

        $this->warn('I have no idea how this happened. Let me know if you figure it out.');
        return 1;
    }
}
