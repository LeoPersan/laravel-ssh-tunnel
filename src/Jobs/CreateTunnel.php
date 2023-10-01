<?php

namespace LeoPersan\Tunneler\Jobs;

class CreateTunnel
{
    /**
     * The Command for checking if the tunnel is open
     * @var string
     */
    protected string $ncCommand;

    /**
     * The command for creating the tunnel
     * @var string
     */
    protected string $sshCommand;

    /**
     * The Command for checking if the tunnel is open
     * @var string
     */
    private string $bashCommand;

    /**
     * Simple place to keep all output.
     * @var array
     */
    protected array $output = [];

    public function __construct(protected string $connection)
    {
        $this->ncCommand = sprintf('%s -vz %s %d  > /dev/null 2>&1',
            config('tunneler.connections.' . $connection . '.nc_path'),
            config('tunneler.connections.' . $connection . '.local_address'),
            config('tunneler.connections.' . $connection . '.local_port')
        );

        $this->bashCommand = sprintf('timeout 1 %s -c \'cat < /dev/null > /dev/tcp/%s/%d\' > /dev/null 2>&1',
            config('tunneler.connections.' . $connection . '.bash_path'),
            config('tunneler.connections.' . $connection . '.local_address'),
            config('tunneler.connections.' . $connection . '.local_port')
        );

        $this->sshCommand = sprintf('%s %s %s -N -i %s -L %d:%s:%d -p %d %s@%s',
            config('tunneler.connections.' . $connection . '.ssh_path'),
            config('tunneler.connections.' . $connection . '.ssh_options'),
            config('tunneler.connections.' . $connection . '.ssh_verbosity'),
            config('tunneler.connections.' . $connection . '.identity_file'),
            config('tunneler.connections.' . $connection . '.local_port'),
            config('tunneler.connections.' . $connection . '.bind_address'),
            config('tunneler.connections.' . $connection . '.bind_port'),
            config('tunneler.connections.' . $connection . '.port'),
            config('tunneler.connections.' . $connection . '.user'),
            config('tunneler.connections.' . $connection . '.hostname')
        );
    }


    public function handle(): int
    {
        if ($this->verifyTunnel()) {
            return 1;
        }

        $this->createTunnel();

        $tries = config('tunneler.connections.' . $this->connection . '.tries');
        for ($i = 0; $i < $tries; $i++) {
            if ($this->verifyTunnel()) {
                return 2;
            }

            // Wait a bit until next iteration
            usleep(config('tunneler.connections.' . $this->connection . '.wait'));
        }

        throw new \ErrorException(sprintf("Could Not Create SSH Tunnel with command:\n\t%s\nCheck your configuration.",
            $this->sshCommand));
    }


    /**
     * Creates the SSH Tunnel for us.
     */
    protected function createTunnel(): void
    {
        $this->runCommand(sprintf('%s %s >> %s 2>&1 &',
            config('tunneler.connections.' . $this->connection . '.nohup_path'),
            $this->sshCommand,
            config('tunneler.connections.' . $this->connection . '.nohup_log')
        ));
        // Ensure we wait long enough for it to actually connect.
        usleep(config('tunneler.connections.' . $this->connection . '.wait'));
    }

    /**
     * Verifies whether the tunnel is active or not.
     * @return bool
     */
    protected function verifyTunnel(): bool
    {
        if (config('tunneler.connections.' . $this->connection . '.verify_process') == 'bash') {
            return $this->runCommand($this->bashCommand);
        }

        return $this->runCommand($this->ncCommand);
    }

    /*
     * Use pkill to kill the SSH tunnel
     */

    public function destroyTunnel(): bool
    {
        $ssh_command = preg_replace('/[\s]{2}[\s]*/', ' ', $this->sshCommand);
        return $this->runCommand('pkill -f "' . $ssh_command . '"');
    }

    /**
     * Runs a command and converts the exit code to a boolean
     * @param $command
     * @return bool
     */
    protected function runCommand($command): bool
    {
        $return_var = 1;
        exec($command, $this->output, $return_var);
        return (bool)($return_var === 0);
    }


}
