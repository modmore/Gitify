<?php

class GitifyInstallMODX extends Gitify {
    public function run(array $options = array()) {
        if (file_exists($options['cwd'].'config.core.php')) {
            $this->echoInfo('* Error: a MODX installation already seems present here.');
            exit(1);
        }
        $this->installMODX($options['cwd']);
    }

    /**
     * Fetches the latest MODX version and installs it
     *
     * @param $directory
     */
    public function installMODX($directory)
    {
        $this->echoInfo("Downloading latest MODX version...");
        exec('curl -Lo modx.zip http://modx.com/download/latest/ -#');

        $this->echoInfo("Extracting zip...");
        exec('unzip modx.zip');

        $zipdir = exec('ls -F | grep "modx-" | head -1');
        if($zipdir == '/') {
            $this->echoInfo("* Error: Could not find unzipped MODX folder; perhaps the download failed or unzip is not available on your system.");
            exit(1);
        }
        else {
            $this->echoInfo("Moving unzipped files out of temporary directory...");
            exec("mv ./{$zipdir}* .; rm -r ./{$zipdir}");

            if (!unlink('modx.zip')) {
                $this->echoInfo("* Warning: could not clean up download.");
            }

            $this->createMODXConfig($directory);

            $tz = date_default_timezone_get();
            $this->echoInfo("Running MODX Setup...");
            exec("php -d date.timezone={$tz} {$directory}setup/index.php --installmode=new --config={$directory}config.xml");

            if(!unlink('config.xml')) {
                $this->echoInfo("* Warning: could not clean up setup config file, please remove this manually.");
            }

            $this->echoInfo("Done installing MODX!");
        }
    }

    /**
     * Asks the user to complete a bunch of details and creates a MODX CLI config xml file
     *
     * @param $directory
     */
    public function createMODXConfig($directory)
    {
        // Creating config xml to install MODX with
        $this->echoInfo("Please complete following details to install MODX. Leave empty to use the [default].");

        $dbName = $this->askQuestion('Database Name [modx]: ', 'modx', true);
        $dbUser = $this->askQuestion('Database User [root]: ', 'root', true);
        $dbPass = $this->askQuestion('Database Password: ', '', true);
        $host = $this->askQuestion('Web Hostname [' . gethostname() . ']: ', gethostname(), true);
        $host = rtrim(trim($host), '/');
        $subdir = $this->askQuestion('Web Base Url/Subdirectory: ', '', true);
        $subdir = '/' . trim($subdir, '/') . '/';
        $subdir = str_replace('//','/', $subdir);

        $language = $this->askQuestion('Manager Language [en]: ', 'en', true);
        $managerUser = $this->askQuestion('Manager Username [admin]: ', 'admin', true);
        $managerPass = $this->askQuestion('Manager Password [generated]: ', 'generate', true);
        if ($managerPass == 'generate') {
            $managerPass = substr(str_shuffle(md5(microtime(true))), 0, rand(8, 15));
            $this->echoInfo("Generated Manager Password: $managerPass");
        }
        $managerEmail = $this->askQuestion('Manager Email: ', '', true);


        $configXMLContents = "<modx>
            <database_type>mysql</database_type>
            <database_server>localhost</database_server>
            <database>{$dbName}</database>
            <database_user>{$dbUser}</database_user>
            <database_password>{$dbPass}</database_password>
            <database_connection_charset>utf8</database_connection_charset>
            <database_charset>utf8</database_charset>
            <database_collation>utf8_general_ci</database_collation>
            <table_prefix>modx_</table_prefix>
            <https_port>443</https_port>
            <http_host>{$host}</http_host>
            <cache_disabled>0</cache_disabled>
            <inplace>1</inplace>
            <unpacked>0</unpacked>
            <language>{$language}</language>
            <cmsadmin>{$managerUser}</cmsadmin>
            <cmspassword>{$managerPass}</cmspassword>
            <cmsadminemail>{$managerEmail}</cmsadminemail>
            <core_path>{$directory}core/</core_path>
            <context_mgr_path>{$directory}manager/</context_mgr_path>
            <context_mgr_url>{$subdir}manager/</context_mgr_url>
            <context_connectors_path>{$directory}connectors/</context_connectors_path>
            <context_connectors_url>{$subdir}connectors/</context_connectors_url>
            <context_web_path>{$directory}</context_web_path>
            <context_web_url>{$subdir}</context_web_url>
            <remove_setup_directory>1</remove_setup_directory>
        </modx>";

        $fh = fopen($directory . 'config.xml', "w+");
        fwrite($fh, $configXMLContents);
        fclose($fh);
    }
}
