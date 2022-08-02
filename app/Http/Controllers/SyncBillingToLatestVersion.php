<?php

namespace App\Http\Controllers\Update;

use App\Model\Common\Setting;
use Artisan;
use Config;
use Exception;

class SyncBillingToLatestVersion
{
    private $log = '';

    public function sync()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '-1');
        set_time_limit(0);

        // in case where isInstall is false(in case of new install) version number should be zero
        $latestVersion = $this->getPHPCompatibleVersionString(Config::get('app.version'));
        $olderVersion = $this->getOlderVersion();

        try {
            if (version_compare($latestVersion, $olderVersion) == 1) {
                $this->updateToLatestVersion($latestVersion, $olderVersion);
                $this->clearViewCache();
                $this->clearConfig();
            }

            // Setting::first()->update(['version'=> 'v'.$latestVersion]);
            \DB::table('settings')->update(['version' => 'v'.$latestVersion]);

            $this->cacheDbVersion();
        } catch (Exception $ex) {
            if (! isInstall()) {
                //if system is not installed chances are logs tables are not present
                throw $ex;
            }

            $this->log = $this->log."\n".$ex->getMessage();
        }

        return $this->log;
    }

    private function cacheDbVersion()
    {
        $filesystemVersion = \Config::get('app.version');
        \Cache::forget($filesystemVersion);
        $dbversion = \Cache::remember($filesystemVersion, 3600, function () {//Caching version for 1 hr
            return Setting::first()->value('version');
        });
    }

    private function getPHPCompatibleVersionString(string $version): string
    {
        return preg_replace('#v\.|v#', '', str_replace('_', '.', $version));
    }

    private function getOlderVersion(): string
    {
        if (! isInstall()) {
            return $this->getPHPCompatibleVersionString('v0.0.0');
        }

        $olderVersion = Setting::first()->version;
        $olderVersion = $olderVersion ? $olderVersion : 'v0.0.0';

        return $this->getPHPCompatibleVersionString($olderVersion);
    }

    public function updateToLatestVersion(string $latestVersion, string $olderVersion)
    {
        $this->updateMigrationTable($olderVersion);

        // after older version is updated, update to the latest version in which seeder versioning is implemented
        Artisan::call('migrate', ['--force' => true]);

        $this->handleArtisanLogs();

        // getting seeder base path
        $seederBasePath = base_path().DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders';

        // get all directories inside seeder folder
        // sort versions from oldest to latest
        if (file_exists($seederBasePath)) {
            $seederVersions = scandir($seederBasePath);

            natsort($seederVersions);
            // convert older and newer version into underscore format
            $formattedOlderVersion = $olderVersion;
            foreach ($seederVersions as $version) {
                if (version_compare($this->getPHPCompatibleVersionString($version), $formattedOlderVersion) == 1) {
                    // scan for $version directory and get file names
                    $this->log = $this->log."\n"."Running Seeder for version $version";

                    Artisan::call('db:seed', ['--class' => "database\seeders", '--force' => true]);
                    $this->handleArtisanLogs();
                }
            }
        }
    }

    private function updateMigrationTable(string $olderVersion)
    {
        if ($olderVersion != '0.0.0') {
            \Artisan::call('migrate', ['--path' => 'database/migrations', '--force' => true]);
        }
    }

    private function handleArtisanLogs()
    {
        $this->log = $this->log."\n\n".Artisan::output();
    }

    private function clearViewCache()
    {
        Artisan::call('view:clear');
        $this->handleArtisanLogs();
    }

    private function clearConfig()
    {
        Artisan::call('config:clear');
        $this->handleArtisanLogs();
    }
}