<?php
namespace LeoPersan\Tunneler\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LeoPersan\Tunneler\Jobs\CreateTunnel;

class TunnelerReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tunneler:reset {connections?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Destroy and reconnect the SSH tunnel';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $connections = $this->argument('connections') ?? array_keys(config('tunneler.connections'));
        foreach ($connections as $connection) {
            $this->handleConnection($connection);
        }
        return Artisan::call('tunneler:activate', $connections);
    }

    private function handleConnection(string $connection): void
    {
        $tunnel = new CreateTunnel($connection);
        $tunnel->destroyTunnel();
    }
}
