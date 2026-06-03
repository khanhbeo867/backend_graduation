<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;



class MakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {serviceName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Service class and its corresponding Interface';

    /**
     * Execute the console command.
     * @throws FileNotFoundException
     */
    public function handle(): void
    {
        $serviceName = $this->argument('serviceName');

        if (!str_ends_with($serviceName, 'Service')) {
            $serviceName = $serviceName . 'Service';
            if (!preg_match('/^[A-Z]/', $serviceName)) {
                $serviceName = ucfirst($serviceName);
            }
        }

        $interfaceName = $serviceName . 'Interface';

        $servicePath = app_path('Http/Services/' . $serviceName . '.php');
        $interfacePath = app_path('Http/Interfaces/' . $interfaceName . '.php');

        $this->makeDirectory(dirname($interfacePath));
        $this->makeDirectory(dirname($servicePath));

        $this->createFile($interfacePath, 'service.interface', $serviceName, $interfaceName);
        $this->createFile($servicePath, 'service', $serviceName, $interfaceName);

        $this->info("✨ Service [{$serviceName}] and Interface [{$interfaceName}] created successfully!");
    }

    /**
     * @throws FileNotFoundException
     */
    protected function createFile($path, $stubName, $className, $interfaceName): void {
        if(File::exists($path)) {
            $this->warn("File already exists: $path");
            return;
        }

        $stub = File::get(app_path('Support/stubs/' . $stubName . '.stub'));

        $stub = str_replace(
            ['{{serviceName}}', '{{interfaceName}}'],
            [$className, $interfaceName],
            $stub
        );

        File::put($path, $stub);
    }

    protected function makeDirectory($path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }
}
