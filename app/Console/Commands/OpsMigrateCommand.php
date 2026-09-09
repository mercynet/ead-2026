<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

class OpsMigrateCommand extends Command
{
    protected $signature = 'ops:migrate
        {--force : Execute migrations without an interactive confirmation}
        {--check-only : Validate the manifest and applied migrations without running migrations}
        {--manifest-only : Validate only the release manifest and discovered migration files}
        {--write-manifest : Write a release manifest from the framework-discovered migration files}
        {--manifest=release/migrations.manifest.json : Release migration manifest path}';

    protected $description = 'Validate the release migration manifest and run all discovered migrations';

    public function handle(Migrator $migrator): int
    {
        $paths = $this->migrationPaths($migrator);

        if ($this->missingModuleMigrationPaths($migrator) !== []) {
            $this->error('MIGRATION_DISCOVERY_UNSAFE: a module migration path is not registered by its service provider.');

            return self::FAILURE;
        }

        $discovered = array_keys($migrator->getMigrationFiles($paths));

        if ($this->option('write-manifest')) {
            $this->writeManifest($discovered);

            return self::SUCCESS;
        }

        $manifest = $this->readManifest();

        if ($manifest === null) {
            return self::FAILURE;
        }

        $expected = $manifest['migrations'];
        $discovered = $this->sortMigrationNames($discovered);

        $this->line('expected='.count($expected));
        $this->line('discovered='.count($discovered));

        if ($expected !== $discovered) {
            $this->error('MIGRATION_DISCOVERY_UNSAFE: manifest and discovered migrations differ.');

            return self::FAILURE;
        }

        if ($this->option('manifest-only')) {
            $this->info('migration_manifest=PASS');

            return self::SUCCESS;
        }

        $repository = $migrator->getRepository();
        $appliedBefore = $repository->repositoryExists() ? $this->sortMigrationNames($repository->getRan()) : [];

        $this->line('applied_before='.count($appliedBefore));

        if ($this->hasUnexpectedAppliedMigrations($expected, $appliedBefore)) {
            $this->error('MIGRATION_SCHEMA_UNSAFE: applied migrations are not in the release manifest.');

            return self::FAILURE;
        }

        if ($this->option('check-only')) {
            if ($appliedBefore !== $expected) {
                $this->error('MIGRATION_PENDING: applied migrations do not match the release manifest.');

                return self::FAILURE;
            }

            $this->line('applied_after='.count($appliedBefore));
            $this->info('migration=PASS');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error('ops:migrate requires --force when it is allowed to change the database.');

            return self::FAILURE;
        }

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        $migrator->run($paths, ['force' => true]);
        $appliedAfter = $this->sortMigrationNames($repository->getRan());

        $this->line('applied_after='.count($appliedAfter));

        if ($appliedAfter !== $expected) {
            $this->error('MIGRATION_RESULT_UNSAFE: applied migrations do not match the release manifest.');

            return self::FAILURE;
        }

        $this->info('migration=PASS');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function migrationPaths(Migrator $migrator): array
    {
        return array_values(array_unique([
            ...$migrator->paths(),
            database_path('migrations'),
        ]));
    }

    /**
     * @return list<string>
     */
    private function missingModuleMigrationPaths(Migrator $migrator): array
    {
        $registeredPaths = array_filter(array_map(realpath(...), $migrator->paths()));
        $modulePaths = glob(base_path('app/Modules/*/Database/Migrations'), GLOB_ONLYDIR) ?: [];

        return array_values(array_filter($modulePaths, function (string $path) use ($registeredPaths): bool {
            $resolvedPath = realpath($path);

            return $resolvedPath === false || ! in_array($resolvedPath, $registeredPaths, true);
        }));
    }

    /**
     * @param  list<string>  $discovered
     */
    private function writeManifest(array $discovered): void
    {
        $path = $this->manifestPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, json_encode([
            'format' => 1,
            'migration_count' => count($discovered),
            'migrations' => $discovered,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        $this->line('manifest='.$path);
        $this->line('expected='.count($discovered));
        $this->line('discovered='.count($discovered));
        $this->info('migration_manifest=WRITTEN');
    }

    /**
     * @return array{format: int, migration_count: int, migrations: list<string>}|null
     */
    private function readManifest(): ?array
    {
        $path = $this->manifestPath();

        if (! is_file($path)) {
            $this->error('MIGRATION_MANIFEST_MISSING: '.$path);

            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error('MIGRATION_MANIFEST_UNREADABLE: '.$path);

            return null;
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->error('MIGRATION_MANIFEST_INVALID: '.$exception->getMessage());

            return null;
        }

        if (! is_array($manifest)
            || ($manifest['format'] ?? null) !== 1
            || ! is_int($manifest['migration_count'] ?? null)
            || ! is_array($manifest['migrations'] ?? null)
            || ! array_is_list($manifest['migrations'])
            || count($manifest['migrations']) !== $manifest['migration_count']
            || array_filter($manifest['migrations'], is_string(...)) !== $manifest['migrations']
        ) {
            $this->error('MIGRATION_MANIFEST_INVALID: expected format 1 with a migration list.');

            return null;
        }

        $migrations = $manifest['migrations'];
        sort($migrations, SORT_STRING);

        if (array_values(array_unique($migrations)) !== $migrations) {
            $this->error('MIGRATION_MANIFEST_INVALID: migration names must be unique and sorted.');

            return null;
        }

        return [
            'format' => 1,
            'migration_count' => $manifest['migration_count'],
            'migrations' => $migrations,
        ];
    }

    private function manifestPath(): string
    {
        $path = (string) $this->option('manifest');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @param  list<string>  $migrations
     * @return list<string>
     */
    private function sortMigrationNames(array $migrations): array
    {
        sort($migrations, SORT_STRING);

        return $migrations;
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $applied
     */
    private function hasUnexpectedAppliedMigrations(array $expected, array $applied): bool
    {
        return array_values(array_diff($applied, $expected)) !== [];
    }
}
