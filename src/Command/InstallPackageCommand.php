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
 * @package modmore\Gitify\Command
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
            ->setAliases(array('install:package'))
            ->setDescription('Downloads and installs MODX packages. [Note: <info>install:package</info> will be removed in 1.0, use <info>package:install</info> instead]')
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
            $packages = isset($this->config['packages']) ? $this->config['packages'] : array();
            foreach ($packages as $provider_name => $provider_data) {
                // Try to load the provider from the database
                $provider = $this->modx->getObject('transport.modTransportProvider', array("name" => $provider_name));

                // If no provider found, then we'll create it
                if (!$provider) {
                    $credentials = array(
                        'username' => isset($provider_data['username']) ? $provider_data['username'] : '',
                        'api_key' => ''
                    );

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
                    $provider->fromArray(array(
                        'name' => $provider_name,
                        'service_url' => $provider_data['service_url'],
                        'description' => isset($provider_data['description']) ? $provider_data['description'] : '',
                        'username' => $credentials['username'],
                        'api_key' => $credentials['api_key'],
                    ));
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
    private function install($package, $provider = 0, array $installOptions = array())
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
    private function download($packageName, $provider, $options = array()) {
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

        $packages = array();

        // first try to find an exact match via signature from the chosen package provider
        $response = $provider->request('package', 'GET', array(
            'supports' => $product_version,
            'signature' => $packageName,
        ));
        
        // when we got a match (non 404), extract package information
        if (!$response->isError()) {

            $foundPkg = simplexml_load_string ( $response->response );
            
            $packages [strtolower((string) $foundPkg->name)] = array (
                'name' => (string) $foundPkg->name,
                'version' => (string) $foundPkg->version,
                'location' => (string) $foundPkg->location,
                'signature' => (string) $foundPkg->signature
            );
        }

        // if no exact match, try it with via query
        if (empty($packages)) {
            $response = $provider->request('package', 'GET', array(
                'supports' => $product_version,
                'query' => $packageName,
            ));
            
            // Check for a proper response
            if (!empty($response)) {
                 
                $foundPackages = simplexml_load_string($response->response);
                // no matches, simply return
                if ($foundPackages['total'] == 0) {
                    return true;
                }
                
                foreach ($foundPackages as $foundPkg) {
                    $packages[strtolower((string)$foundPkg->name)] = array(
                        'name' => (string)$foundPkg->name,
                        'version' => (string)$foundPkg->version,
                        'location' => (string)$foundPkg->location,
                        'signature' => (string)$foundPkg->signature,
                    );
                }
            }
        }

        // process found packages
        if (!empty($packages)) {

            $this->output->writeln('Found ' . count($packages) . ' package(s).');

            $helper = $this->getHelper('question');            
            
            // Ensure the exact match is always first
            if (isset($packages[strtolower($packageName)])) {
                $packages = array($packageName => $packages[strtolower($packageName)]) + $packages;
            }

            foreach ($packages as $package) {
                if ($this->modx->getCount('transport.modTransportPackage', array('signature' => $package['signature']))) {
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
                $response = $this->modx->runProcessor('workspace/packages/rest/download', array(
                    'provider' => $provider->get('id'),
                    'info' => join('::', array($package['location'], $package['signature']))
                ));

                // If we have an error, show it and cancel.
                if ($response->isError()) {
                    $this->output->writeln("<error>Could not download package {$package['name']}. Reason: {$response->getMessage()}</error>");
                    return false;
                }

                $this->output->writeln("<comment>Installing {$package['name']}...</comment>");

                // Grab the package object
                $obj = $response->getObject();
                if ($package = $this->modx->getObject('transport.modTransportPackage', array('signature' => $obj['signature']))) {
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
