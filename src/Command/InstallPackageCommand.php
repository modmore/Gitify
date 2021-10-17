<?php namespace modmore\Gitify\Command;

use modmore\Gitify\Gitify;
use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class InstallPackageCommand
 *
 * Installs a package
 *
 * @package modmore\gitify\Command
 */
class InstallPackageCommand extends BaseCommand
{
    public $loadConfig = true;
    public $loadMODX = true;

    public $interactive = true;

    protected function configure()
    {
        $this
            ->setName('package:install')
            ->setDescription('Downloads and installs MODX packages.')
            ->addArgument(
                'package_name',
                InputArgument::OPTIONAL,
                'Name of package to search and install. By default the latest available version will be installed.'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'When specified, all packages defined in the .gitify config will be installed.'
            )
            ->addOption(
                'local',
                'l',
                InputOption::VALUE_NONE,
                'When specified, any packages inside the /core/packages folder will be installed.'
            )
            ->addOption(
                'interactive',
                'i',
                InputOption::VALUE_NONE,
                'When --all and --interactive are specified, all packages defined in the .gitify config will be installed interactively.'
            );
    }

    /**
     * Runs the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->modx->setLogTarget('ECHO');
        $this->modx->setLogLevel(\modX::LOG_LEVEL_INFO);

        if ($input->getOption('all')) {
            // check list and run install for each
            $packages = isset($this->config['packages']) ? $this->config['packages'] : [];
            foreach ($packages as $provider_name => $provider_data) {
                // Try to load the provider from the database
                $provider = $this->modx->getObject('transport.modTransportProvider', ["name" => $provider_name]);

                // If no provider found, then we'll create it
                if (!$provider) {
                    $credentials = [
                        'username' => isset($provider_data['username']) ? $provider_data['username'] : '',
                        'api_key' => ''
                    ];

                    // Try to look for a file with the API Key from a file within the gitify working directory
                    if (!empty($provider_data['api_key']) && file_exists(GITIFY_WORKING_DIR . '/' . $provider_data['api_key'])) {
                        $credentials['api_key'] = trim(file_get_contents(GITIFY_WORKING_DIR . '/' . $provider_data['api_key']));
                    }

                    // load provider credentials from file
                    if (!empty($provider_data['credential_file']) && file_exists(GITIFY_WORKING_DIR . '/' . $provider_data['credential_file'])) {
                        $credentials_content = trim(file_get_contents(GITIFY_WORKING_DIR . '/' . $provider_data['credential_file']));
                        $credentials = Gitify::fromYAML($credentials_content);
                    }

                    /** @var \modTransportProvider $provider */
                    $provider = $this->modx->newObject('transport.modTransportProvider');
                    $provider->fromArray([
                        'name' => $provider_name,
                        'service_url' => $provider_data['service_url'],
                        'description' => isset($provider_data['description']) ? $provider_data['description'] : '',
                        'username' => $credentials['username'],
                        'api_key' => $credentials['api_key'],
                    ]);
                    $provider->save();
                }

                foreach ($provider_data['packages'] as $package) {
                    if (!$input->getOption('interactive')) {
                        $this->setInteractive(false);
                    }
                    $this->install($package, $provider);
                }
            }

            $this->output->writeln("<info>Done!</info>");
            return 0;
        }

        // check for packages that were manually added to the core/packages folder
        if ($input->getOption('local')) {
            // most of this code is copied from:
            // core/model/modx/processors/workspace/packages/scanlocal.class.php
            $corePackagesDirectory = $this->modx->getOption('core_path').'packages/';
            $corePackagesDirectoryObject = dir($corePackagesDirectory);

            while (false !== ($name = $corePackagesDirectoryObject->read())) {
                if (in_array($name,['.','..','.svn','.git','_notes'])) continue;
                $packageFilename = $corePackagesDirectory.'/'.$name;

                // dont add in unreadable files or directories
                if (!is_readable($packageFilename) || is_dir($packageFilename)) continue;

                // must be a .transport.zip file
                if (strlen($name) < 14 || substr($name,strlen($name)-14,strlen($name)) != '.transport.zip') continue;
                $packageSignature = substr($name,0,strlen($name)-14);

                // must have a name and version at least
                $p = explode('-',$packageSignature);
                if (count($p) < 2) continue;

                // install if package was not found in database
                if ($this->modx->getCount('transport.modTransportPackage', ['signature' => $packageSignature])) {
                    $this->output->writeln("Package $packageSignature is already installed.");

                } else {
                    $this->output->writeln("<comment>Installing $packageSignature...</comment>");

                    $package = $this->modx->newObject('transport.modTransportPackage');
                    $package->set('signature', $packageSignature);
                    $package->set('state', 1);
                    $package->set('created',strftime('%Y-%m-%d %H:%M:%S'));
                    $package->set('workspace', 1);

                    // set package version data
                    $sig = explode('-',$packageSignature);
                    if (is_array($sig)) {
                        $package->set('package_name',$sig[0]);
                        if (!empty($sig[1])) {
                            $v = explode('.',$sig[1]);
                            if (isset($v[0])) $package->set('version_major',$v[0]);
                            if (isset($v[1])) $package->set('version_minor',$v[1]);
                            if (isset($v[2])) $package->set('version_patch',$v[2]);
                        }
                        if (!empty($sig[2])) {
                            $r = preg_split('/([0-9]+)/',$sig[2],-1,PREG_SPLIT_DELIM_CAPTURE);
                            if (is_array($r) && !empty($r)) {
                                $package->set('release',$r[0]);
                                $package->set('release_index',(isset($r[1]) ? $r[1] : '0'));
                            } else {
                                $package->set('release',$sig[2]);
                            }
                        }
                    }

                    // Determine if there are any package dependencies
                    $package->getTransport();
                    $package->getOne('Workspace');
                    $wc = isset($package->Workspace->config) && is_array($package->Workspace->config) ? $package->Workspace->config : [];
                    $at = is_array($package->get('attributes')) ? $package->get('attributes') : [];
                    $attributes = array_merge($wc, $at);
                    $requires = isset($attributes['requires']) && is_array($attributes['requires'])
                        ? $attributes['requires']
                        : [];
                    $unsatisfied = $package->checkDependencies($requires);

                    if (empty($unsatisfied)) {
                        $package->save();
                        $package->install();
                        $this->output->writeln("<info>Package $packageSignature successfully installed.</info>");
                    }
                    // If dependencies exist, output an error message and list the packages needed.
                    else {
                        $this->output->writeln("\n<info>Unable to install $packageSignature! There are currently unmet dependencies:</info>");
                        foreach ($unsatisfied as $dependency => $v) {
                            $this->output->writeln("<info> - $dependency</info>");
                        }
                        $this->output->writeln("\n<info>$packageSignature has been added to the MODX package management grid, but is not yet installed.</info>\n");
                    }
                }
            }

            return 0;
        }

        // install defined package
        $this->install($this->input->getArgument('package_name'));

        return 0;
    }

    /**
     * @param $package
     * @param int|\modTransportProvider $provider
     * @param array $installOptions
     * @return bool
     */
    private function install($package, $provider = 0, array $installOptions = [])
    {
        $this->modx->addPackage('modx.transport', MODX_CORE_PATH . 'model/');

        if (!($provider instanceof \modTransportProvider) && is_numeric($provider) && $provider > 0)
        {
            $provider = $this->modx->getObject('transport.modTransportProvider', $provider);
        }
        if (!($provider instanceof \modTransportProvider))
        {
            $c = $this->modx->newQuery('transport.modTransportProvider');
            $c->sortby('id', 'ASC');
            $provider = $this->modx->getObject('transport.modTransportProvider', $c);
        }
        if (!($provider instanceof \modTransportProvider))
        {
            $this->output->writeln("<error>Cannot load Provider to install $package</error>");
            return false;
        }

        // Download and install the package from the chosen provider
        $completed = $this->download($package, $provider, $installOptions);
        if (!$completed) {
            $this->output->writeln("<error>Cannot install package $package.</error>");

            return false;
        }

        return true;
    }


    /**
     * Download and install the package from the provider
     *
     * @param string $packageName
     * @param \modTransportProvider $provider
     * @param array $options
     * @return bool
     */
    private function download($packageName, $provider, $options = []) {
        $this->modx->getVersionData();
        $product_version = $this->modx->version['code_name'] . '-' . $this->modx->version['full_version'];

        $response = $provider->verify();
        if ($response !== true) {
            $this->output->writeln("<error>Could not download $packageName because the provider cannot be verified.</error>");
            $error = $response;
            if (!empty($error) && is_string($error)) {
                $this->output->writeln("Message from Provider: $error");
            }

            return false;
        }

        $provider->getClient();
        $this->output->writeln("Searching <comment>{$provider->get('name')}</comment> for <comment>$packageName</comment>...");

        // The droid we are looking for
        $packageName = strtolower($packageName);

        // Collect potential matches in array
        $packages = [];

        // First try to find an exact match via signature from the chosen package provider
        $response = $provider->request('package', 'GET', [
            'supports' => $product_version,
            'signature' => $packageName,
        ]);

        // When we got a match (non 404), extract package information
        if (!$response->isError()) {

            $foundPkg = simplexml_load_string($response->response);

            // Verify that signature matches (mismatches are known to occur!)
            if ($foundPkg->signature == $packageName) {
                $packages[strtolower((string) $foundPkg->name)] = [
                    'name' => (string) $foundPkg->name,
                    'version' => (string) $foundPkg->version,
                    'location' => (string) $foundPkg->location,
                    'signature' => (string) $foundPkg->signature
                ];
            }
            // Try again from a different angle
            else {
                $this->output->writeln("<error>Returned signature {$foundPkg->signature} doesn't match the package name.</error>");
                $this->output->writeln("Trying again from a different angle...");

                // Query for name instead, without version number
                $name = explode('-', $packageName);
                $response = $provider->request('package', 'GET', [
                    'supports' => $product_version,
                    'query' => $name[0],
                ]);

                if (!empty($response)) {
                    $foundPackages = simplexml_load_string($response->response);

                    foreach ($foundPackages as $foundPkg) {
                        // Only accept exact match on signature
                        if ($foundPkg->signature == $packageName) {
                            $packages[strtolower((string) $foundPkg->name)] = [
                                'name' => (string) $foundPkg->name,
                                'version' => (string) $foundPkg->version,
                                'location' => (string) $foundPkg->location,
                                'signature' => (string) $foundPkg->signature
                            ];
                        }
                    }
                }
            }
        }

        // If no exact match, try it via query
        if (empty($packages)) {
            $response = $provider->request('package', 'GET', [
                'supports' => $product_version,
                'query' => $packageName,
            ]);

            // Check for a proper response
            if (!empty($response)) {

                $foundPackages = simplexml_load_string($response->response);

                // No matches, simply return
                if ($foundPackages['total'] == 0) {
                    return true;
                }

                // Collect multiple versions of the same package in array
                $packageVersions = [];

                foreach ($foundPackages as $foundPkg) {
                    $name = strtolower((string)$foundPkg->name);

                    // Only accept exact match on name
                    if ($name == $packageName) {
                        $packages[$name] = array (
                            'name' => (string) $foundPkg->name,
                            'version' => (string) $foundPkg->version,
                            'location' => (string) $foundPkg->location,
                            'signature' => (string) $foundPkg->signature
                        );
                        $packageVersions[(string)$foundPkg->signature] = [
                            'name' => (string) $foundPkg->name,
                            'version' => (string) $foundPkg->version,
                            'release' => (string) $foundPkg->release,
                            'location' => (string) $foundPkg->location,
                            'signature' => (string) $foundPkg->signature
                        ];
                    }
                }

                // If there are multiple versions of the same package, use the latest
                if (count($packageVersions) > 1) {
                    $i = 0;
                    $latest = '';

                    // Compare versions
                    foreach (array_keys($packageVersions) as $version) {
                        if ($i == 0) {
                            // First iteration
                            $latest = $version;
                        } else {
                            // Replace latest version with current one if it's higher
                            if (version_compare($version, $latest, '>=')) {
                                $latest = $version;
                            }
                        }
                        $i++;
                    }

                    // Use latest
                    $packages[$packageName] = $packageVersions[$latest];
                }

                // If there's still no match, revisit the response and just grab all hits...
                if (empty($packages)) {
                    foreach ($foundPackages as $foundPkg) {
                        $packages[strtolower((string)$foundPkg->name)] = [
                            'name' => (string)$foundPkg->name,
                            'version' => (string)$foundPkg->version,
                            'location' => (string)$foundPkg->location,
                            'signature' => (string)$foundPkg->signature,
                        ];
                    }
                }
            }
        }

        // Process found packages
        if (!empty($packages)) {

            $this->output->writeln('Found ' . count($packages) . ' package(s).');

            $helper = $this->getHelper('question');

            foreach ($packages as $package) {
                if ($this->modx->getCount('transport.modTransportPackage', ['signature' => $package['signature']])) {
                    $this->output->writeln("<info>Package {$package['name']} {$package['version']} is already installed.</info>");

                    if ($this->interactive) {
                        continue;
                    }
                    else {
                        return true;
                    }
                }

                if ($this->interactive) {
                    if (!$helper->ask(
                        $this->input,
                        $this->output,
                        new ConfirmationQuestion(
                            "Do you want to install <info>{$package['name']} ({$package['version']})</info>? <comment>[Y/n]</comment>: ",
                            true
                        )
                    )
                    ) {
                        continue;
                    }
                }

                // Run the core processor to download the package from the provider
                $this->output->writeln("<comment>Downloading {$package['name']} ({$package['version']})...</comment>");
                $response = $this->modx->runProcessor('workspace/packages/rest/download', [
                    'provider' => $provider->get('id'),
                    'info' => join('::', [$package['location'], $package['signature']])
                ]);

                // If we have an error, show it and cancel.
                if ($response->isError()) {
                    $this->output->writeln("<error>Could not download package {$package['name']}. Reason: {$response->getMessage()}</error>");
                    return false;
                }

                $this->output->writeln("<comment>Installing {$package['name']}...</comment>");

                // Grab the package object
                $obj = $response->getObject();
                if ($package = $this->modx->getObject('transport.modTransportPackage', ['signature' => $obj['signature']])) {
                    // Install the package
                    return $package->install($options);
                }
            }
        }

        return true;
    }

    /**
     * Sets the internal interactive flag
     *
     * @param $value
     */
    public function setInteractive($value)
    {
        $this->interactive = $value;
    }
}
