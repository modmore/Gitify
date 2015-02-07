<?php namespace modmore\Gitify\Command;

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

    protected function configure()
    {
        $this
            ->setName('install:package')
            ->setDescription('Downloads and installs a MODX packages.')
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
            );
        // TODO: add option `--update` for update installed packages, by default skip installed
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
        if ($input->getOption('all')) {
            // check list and run install for each
            $packages = isset($this->config['data']['packages']) ? $this->config['data']['packages'] : array();
            foreach ($packages as $provider_name => $provider_data) {
                // Try to load the provider from the database
                $provider = $this->modx->getObject('transport.modTransportProvider', array("name" => $provider_name));

                // If no provider found, then we'll create it
                if (!$provider) {
                    $api_key = '';

                    // Try to look for a file with the API Key from a file within the gitify working directory
                    if (!empty($provider_data['api_key']) && file_exists(GITIFY_WORKING_DIR . '/' . $provider_data['api_key'])) {
                        $api_key = trim(file_get_contents(GITIFY_WORKING_DIR . '/' .$provider_data['api_key']));
                    }

                    /** @var \modTransportProvider $provider */
                    $provider = $this->modx->newObject('transport.modTransportProvider');
                    $provider->fromArray(array(
                        'name' => $provider_name,
                        'service_url' => $provider_data['service_url'],
                        'description' => isset($provider_data['description']) ? $provider_data['description'] : '',
                        'username' => isset($provider_data['username']) ? $provider_data['username'] : '',
                        'api_key' => $api_key,
                    ));
                    $provider->save();
                }

                foreach ($provider_data['packages'] as $package) {
                    $this->install($package, $provider, true);
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
     * @param bool $quiet
     * @param array $installOptions
     * @return bool
     */
    private function install($package, $provider = 1, $quiet = false, array $installOptions = array())
    {
        $this->modx->addPackage('modx.transport', GITIFY_WORKING_DIR . '/core/model/');

        if ($this->modx->getCount('transport.modTransportPackage', array('package_name' => $package))) {
            $this->output->writeln("Package $package already installed. Skipping...");
            return true;
        }

        $helper = $this->getHelper('question');

        if (!$quiet) {
            if (!$helper->ask(
                $this->input,
                $this->output,
                new ConfirmationQuestion(
                    "Do you want to install <info>$package</info>? <comment>[Y/n]</comment>: ",
                    true
                )
            )) {
                return true;
            }
        }

        if (!is_object($provider)) {
            $provider = $this->modx->getObject('transport.modTransportProvider', $provider);
        }

        // Download and install the package from the chosen provider
        $completed = $this->download($package, $provider, $installOptions);
        if (!$completed) {
            $this->output->writeln("<error>Cannot install package $package.</error>");

            return false;
        }

        $this->output->writeln("<info>Package $package installed</info>");

        return true;
    }


    /**
     * Download and install the package from the provider
     *
     * @param string $package
     * @param \modTransportProvider $provider
     * @param array $options
     * @return bool
     */
    private function download($package, $provider, $options = array()) {
        $this->modx->getVersionData();
        $product_version = $this->modx->version['code_name'] . '-' . $this->modx->version['full_version'];

        $response = $provider->verify();
        if ($response !== true) {
            $this->output->writeln("<error>Could not download $package because the provider cannot be verified.</error>");
            $error = $response;
            if (!empty($error) && is_string($error)) {
                $this->output->writeln("Message from Provider: $error");
            }

            return false;
        }

        $provider->getClient();
        $this->output->writeln("<comment>Downloading $package...</comment>");

        // Request package information from the chosen provider
        $response = $provider->request('package', 'GET', array(
            'supports' => $product_version,
            'query' => $package
        ));

        // Check for a proper response
        if (!empty($response)) {
            $found = simplexml_load_string($response->response);

            foreach ($found as $item) {
                if ($item->name == $package) {
                    // Run the core processor to download the package from the provider
                    $response = $this->modx->runProcessor('workspace/packages/rest/download', array(
                        'provider' => $provider->get('id'),
                        'info' => join('::', array($item->location, $item->signature))
                    ));

                    $this->output->writeln("<comment>Installing $package...</comment>");

                    // If we have an error, show it and cancel.
                    if ($response->isError()) {
                        $this->output->writeln("<error>Could not download package $item->name. Reason: {$response->getMessage()}</error>");
                        return false;
                    }

                    // Grab the package object
                    $obj = $response->getObject();
                    if ($pkg = $this->modx->getObject('transport.modTransportPackage', array('signature' => $obj['signature']))) {
                        // Install the package
                        return $pkg->install($options);
                    }
                }
            }
        }

        return false;
    }
}
