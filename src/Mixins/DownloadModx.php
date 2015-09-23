<?php

namespace modmore\Gitify\Mixins;

trait DownloadModx
{
    /**
     * Downloads the latest version of MODX or takes from local cache, and puts it into place in the working directory.
     *
     * @param string $version
     * @return bool
     */
    protected function getMODX($version = 'latest', $download = false)
    {
        $link = $this->fetchUrl($version);
        $realVersion = basename($link, '.zip');

        if ($download) { // forced download
            if (!$this->download($link)) {
                return false;
            }
        }

        $this->retriveFromCache($realVersion);

        return true;
    }

    /**
     * Downloads specified package to local storage
     *
     * @param $link
     * @return bool
     */
    protected function download($link)
    {
        $version = basename($link, '.zip');

        if (!file_exists(GITIFY_CACHE_DIR)) {
            mkdir(GITIFY_CACHE_DIR);
        }

        $this->removeOutdatedArchive($version); // remove old files

        $to = GITIFY_CACHE_DIR . basename($link);
        $this->output->writeln("Downloading MODX {$version}...");
        exec("curl -Lo $to $link -#");

        if (!file_exists($to)) {
            $this->output->writeln('<error>Error: Could not download the MODX zip</error>');

            return false;
        }

        $this->unzip($to);

        return true;
    }

    /**
     * Unzips package
     *
     * @param $package
     */
    protected function unzip($package)
    {
        $this->output->writeln("Extracting package... ");

        $destination = dirname($package);
        exec("unzip $package -d $destination");
    }

    /**
     * Fetching direct link to file with modx sources
     *
     * @param $version
     * @return mixed
     */
    private function fetchUrl($version)
    {
        $version = str_replace('modx-', '', $version);
        $url = empty($version) || $version == 'latest'
            ? 'http://modx.com/download/latest/'
            : 'http://modx.com/download/direct/modx-' . $version . '.zip';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_exec($ch);
        $direct = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $direct;
    }

    protected function retriveFromCache($version)
    {
        $path = GITIFY_CACHE_DIR . $version;

        if (!file_exists($path) || !is_dir($path)) {
            $link = $this->fetchUrl($version);
            $this->download($link);
        }

        exec("cp -r $path/* ./");
    }

    protected function removeOutdatedArchive($version)
    {
        $folder = GITIFY_CACHE_DIR . $version;
        $package = $folder . '.zip';

        exec("rm -rf $folder");
        exec("rm -rf $package");
    }
}
