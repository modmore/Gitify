<?php

namespace modmore\Gitify\Mixins;

trait DownloadModx
{
    /**
     * Downloads the latest version of MODX, and puts it into place in the working directory.
     *
     * @param string $version
     * @return bool
     */
    protected function download($version = 'latest')
    {
        $url = empty($version) || $version == 'latest'
            ? 'http://modx.com/download/latest/'
            : 'http://modx.com/download/direct/modx-' . $version . '.zip';

        $this->output->writeln("Downloading MODX from {$url}...");
        exec('curl -Lo modx.zip ' . $url . ' -#');

        if (!file_exists('modx.zip')) {
            $this->output->writeln('<error>Error: Could not download the MODX zip</error>');

            return false;
        }

        $this->output->writeln("Extracting zip...");
        exec('unzip modx.zip -x "*/./"');

        $insideFolder = exec('ls -F | grep "modx-" | head -1');
        if (empty($insideFolder) || $insideFolder == '/') {
            $this->output->writeln("<error>Error: Could not locate unzipped MODX folder; perhaps the download failed or unzip is not available on your system.</error>");

            return false;
        }

        $this->output->writeln("Moving unzipped files out of temporary directory...");

        exec("cp -r ./{$insideFolder}* ./");
        exec("rm -r ./{$insideFolder}");

        $this->removeOutdatedArchive();

        return true;
    }

    private function unzip()
    {

    }

    private function fetchUrl($url)
    {
        // пробуем получить прямую линку на файл. если такая версия уже есть, то не скачиваем,
        // а отдаем из кеша
        // можно указать флаг и принудительно скачать версию + заменить в кеше

        $ch = curl_init($url);
        $result = curl_exec($ch);

        print_r($result);
    }

    private function retriveFromCache($version)
    {

    }

    private function saveToCache()
    {

    }

    private function removeOutdatedArchive()
    {
        if (!unlink('modx.zip')) {
            $this->output->writeln("<info>Note: unable to clean up modx.zip file.</info>");
        }
    }
}
