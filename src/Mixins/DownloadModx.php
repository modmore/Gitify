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
        if (empty($version) || $version == 'latest') {
            $url = 'http://modx.com/download/latest/';
        } else {
            $url = 'http://modx.com/download/direct/modx-' . $version . '.zip';
        }

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

    private function removeOutdatedArchive()
    {
        if (!unlink('modx.zip')) {
            $this->output->writeln("<info>Note: unable to clean up modx.zip file.</info>");
        }
    }
}
