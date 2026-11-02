<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use ZipArchive;

class ProjectBackup extends Command
{
    protected $signature = 'project:backup';

    protected $description = 'Back up source and the local database outside public (excludes environment secrets)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command is local-only.');

            return self::FAILURE;
        }
        $directory = base_path('backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $stem = $directory.'/project-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $dump = $stem.($driver === 'sqlite' ? '.sqlite' : '.sql');
        if ($driver === 'sqlite') {
            $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($dump));
        } elseif ($driver === 'mysql') {
            $configuration = $connection->getConfig();
            $process = new Process(['mysqldump', '--single-transaction', '--skip-lock-tables', '--host='.$configuration['host'], '--port='.$configuration['port'], '--user='.$configuration['username'], $configuration['database']], null, ['MYSQL_PWD' => $configuration['password']]);
            $process->setTimeout(120);
            $handle = fopen($dump, 'wb');
            if ($handle === false) {
                return self::FAILURE;
            }
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
            fclose($handle);
            if (! $process->isSuccessful()) {
                unlink($dump);
                $this->error('Database dump failed. Check MySQL client and local configuration.');

                return self::FAILURE;
            }
        } else {
            $this->error('Only SQLite and MySQL backups are supported.');

            return self::FAILURE;
        }
        $zip = new ZipArchive;
        if ($zip->open($stem.'.zip', ZipArchive::CREATE) !== true) {
            return self::FAILURE;
        }
        $files = (new Finder)->files()->in(base_path())->ignoreDotFiles(false)->exclude(['.git', 'vendor', 'node_modules', 'backups', 'storage', 'bootstrap/cache'])->notName('.env')->notName('.env.*')->notName('*.sqlite*')->notName('*.key');
        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), 'source/'.$file->getRelativePathname());
        }
        $zip->addFile(base_path('.env.example'), 'source/.env.example');
        $zip->addFile($dump, 'database/'.basename($dump));
        $zip->close();
        unlink($dump);
        chmod($stem.'.zip', 0600);
        $this->info('Backup created: '.$stem.'.zip');
        $this->warn('Contains private database data. Copy securely to external storage; environment secrets are excluded.');

        return self::SUCCESS;
    }
}
