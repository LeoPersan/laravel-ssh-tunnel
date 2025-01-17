<?php namespace LeoPersan\Tunneler\Console;

use Illuminate\Console\Command;
use LeoPersan\Tunneler\Daemon\Listener;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DaemonCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:daemon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listens to a given queue and gracefully handles PCNTL Signals';

    /**
     * The queue listener instance.
     *
     * @var Listener
     */
    protected Listener $listener;

    /**
     * DaemonCommand constructor.
     *
     * @param Listener $listener
     */
    public function __construct(Listener $listener)
    {
        parent::__construct();

        $this->listener = $listener;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire(): void
    {
        $this->setListenerOptions();

        $delay = $this->input->getOption('delay');

        // The memory limit is the amount of memory we will allow the script to occupy
        // before killing it and letting a process manager restart it for us, which
        // is to protect us against any memory leaks that will be in the scripts.
        $memory = $this->input->getOption('memory');

        $connection = $this->input->getArgument('connection');

        $timeout = $this->input->getOption('timeout');

        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queue = $this->getQueue($connection);

        $this->listener->listen(
            $connection, $queue, $delay, $memory, $timeout
        );
    }

    /**
     * Get the name of the queue connection to listen on.
     *
     * @param string|null $connection
     * @return string
     */
    protected function getQueue(?string $connection): string
    {
        if (is_null($connection)) {
            $connection = $this->laravel['config']['queue.default'];
        }

        $queue = $this->laravel['config']->get("queue.connections.{$connection}.queue", 'default');

        return $this->input->getOption('queue') ?: $queue;
    }

    /**
     * Set the options on the queue listener.
     *
     * @return void
     */
    protected function setListenerOptions(): void
    {
        $this->listener->setEnvironment($this->laravel->environment());

        $this->listener->setSleep($this->option('sleep'));

        $this->listener->setMaxTries($this->option('tries'));

        $this->listener->setOutputHandler(function ($type, $line) {
            switch ($type){
                case 'warn':
                    $this->warn($line);
                    break;
                case 'info':
                    $this->info($line);
                    break;
                case 'out':
                default:
                    $this->output->write($line);
            }

        });
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [
            ['connection', InputArgument::OPTIONAL, 'The name of connection'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['queue', null, InputOption::VALUE_OPTIONAL, 'The queue to listen on', null],

            ['delay', null, InputOption::VALUE_OPTIONAL, 'Amount of time to delay failed jobs', 0],

            ['memory', null, InputOption::VALUE_OPTIONAL, 'The memory limit in megabytes', 128],

            ['timeout', null, InputOption::VALUE_OPTIONAL, 'Seconds a job may run before timing out', 60],

            ['sleep', null, InputOption::VALUE_OPTIONAL, 'Seconds to wait before checking queue for jobs', 3],

            ['tries', null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 0],
        ];
    }
}
