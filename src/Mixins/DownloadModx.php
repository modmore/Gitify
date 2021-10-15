<?php

namespace modmore\Gitify\Mixins;

/**
 * Class DownloadModx
 * @package modmore\Gitify\Mixins
 */
trait DownloadModx
{
    /**
     * Downloads the latest version of MODX or takes from local cache, and puts it into place in the working directory.
     *
     * @param string $version
     * @param bool $download
     * @return bool
     */
    protected function getMODX($version = 'latest', $download = false)
    {
        if ($version === 'latest') {
            $this->output->writeln('Looking up latest MODX version...');
            $link = $this->fetchUrl($version);
            $version = basename($link, '.zip');
            $this->output->writeln('<comment>Latest version: ' . $version . '</comment>');
        }

        // Force download the MODX package
        if ($download) {
            $this->output->writeln('<comment>Ignoring local cache, downloading MODX package...</comment>');
            if (!$this->download($version)) {
                return false;
            }
        }

        // Copy the files from the local cache (and download version if necessary)
        $this->retrieveFromCache($version);

        return true;
    }

    /**
     * Downloads specified package to local storage
     *
     * @param $version
     * @return bool
     */
    protected function download($version)
    {
        $link = $this->fetchUrl($version);

        if (!file_exists(GITIFY_CACHE_DIR)) {
            mkdir(GITIFY_CACHE_DIR);
        }

        $this->removeOutdatedArchive($version); // remove old files

        $zip = GITIFY_CACHE_DIR . $version . '.zip';
        $this->output->writeln("Downloading {$version} from {$link}...");
        exec("curl -Lo $zip $link -#");

        if (!file_exists($zip)) {
            $this->output->writeln('<error>Error: Could not download the MODX zip</error>');

            return false;
        }

        $this->unzip($zip);

        return true;
    }

    /**
     * Unzips the package zip to the current directory
     *
     * @param $package
     * @throws \Exception
     */
    protected function unzip($package)
    {
        $this->output->writeln("Extracting package... ");

        $destination = dirname($package);
        $zipArchive = new \ZipArchive();
        if ($zipArchive->open($package)) {
            $zipArchive->extractTo($destination);
            $zipArchive->close();
            $this->output->writeln("Package extracted successfully.");
        } else {
            throw new \Exception("Error opening the package: $package");
        }
    }

    /**
     * Fetches the real download link for a MODX package version
     *
     * @param $version
     * @return mixed
     */
    protected function fetchUrl($version)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln('Fetching download URL for MODX ' . $version);
        }
        $version = str_replace('modx-', '', $version);
        $url = empty($version) || $version == 'latest'
            ? 'http://modx.com/download/latest/'
            : 'http://modx.com/download/direct/modx-' . $version . '.zip';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_exec($ch);
        $direct = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($this->output->isVerbose()) {
            $this->output->writeln('Download URL found: ' . $direct);
        }
        return $direct;
    }

    /**
     * Copies the MODX files from the local cache into the current directory, or downloads the specified version
     * if it isn't cached yet.
     *
     * @param $version
     */
    protected function retrieveFromCache($version)
    {
        $version = 'modx-' . str_replace('modx-', '', $version);

        $path = GITIFY_CACHE_DIR . $version;

        if (!file_exists($path) || !is_dir($path)) {
            $this->download($version);
        }

        // Handle potential custom paths on upgrade
        if ($this->isUpgrade) {
            require_once './config.core.php';
            if (MODX_CORE_PATH) {
                require_once MODX_CORE_PATH . 'config/config.inc.php';
                // Need to avoid the easier route with rsync as it may not be installed
                $paths = [
                    'core' => MODX_CORE_PATH,
                    'connectors' => MODX_CONNECTORS_PATH,
                    'manager' => MODX_MANAGER_PATH,
                    'assets' => MODX_ASSETS_PATH
                ];
                foreach ($paths as $k => $customPath) {
                    // Copy each dir to path specified in config file then remove that dir from source
                    exec("cp -r $path/$k/* $customPath");
                    exec("rm -rf $path/$k");
                }
                // Now copy remaining contents
                exec("cp -r $path/* ./");

                // Re-extract package to source dir, so it's ready for another run.
                $this->unzip($path . '.zip');
            }
        }
        else {
            exec("cp -r $path/* ./");
        }
    }

    /**
     * Removes old cache folders
     *
     * @param $version
     */
    protected function removeOutdatedArchive($version)
    {
        $folder = GITIFY_CACHE_DIR . $version;
        $package = $folder . '.zip';

        exec("rm -rf $folder");
        exec("rm -rf $package");
    }
}
