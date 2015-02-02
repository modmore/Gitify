<?php namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
                'Name of package for search and install. By default will be installed latest available version.'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'When specified, will be installed packages, defined in .gitify config.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('all')) {
            // check list and run install for each
            $packages = $this->config['data']['packages'];
            foreach ($packages as $provider_name => $provider_data) {
                $provider = $this->modx->getObject('transport.modTransportProvider', array("name" => $provider_name));
                // TODO [REF]: create provider function

                foreach ($provider_data['packages'] as $package) {
                    $this->install($package, $provider->toArray(), true);
                }
            }

            return true;
        }

        // install defined package
        $package_name = $this->input->getArgument('package_name');
        $this->install($package_name);

        return true;
    }

    private function install($package, $provider = 1, $quiet = false)
    {
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

        $this->modx->addPackage('modx.transport', GITIFY_WORKING_DIR . '/core/model/');

        if (!is_array($provider)) {
            $provider = $this->modx->getObject('transport.modTransportProvider', $provider);
        } else {
            $provider = $this->modx->getObject('transport.modTransportProvider', $provider['id']);
        }

        $completed = $this->download($package, $provider, array());
        if (!$completed) {
            $this->output->writeln("<error>Error: Cannot install package $package.</error>");

            return false;
        }

        return true;
    }


    private function download($package, $provider, $options = array()) {
        $this->modx->getVersionData();
        $product_version = $this->modx->version['code_name'] . '-' . $this->modx->version['full_version'];

        if ($provider->verify() !== true) {
            $this->output->writeln("<error>Cannot continue adding package $package! Reason: provider cannot be verified...</error>");
            $error = $provider->verify();
            if (!empty($error) && is_string($error)) {
                $this->output->writeln("PROVIDER SAYS: $error");
            }

            return false;
        }
        $provider->getClient();

        $this->output->writeln("<comment>Heading to install $package...</comment>");

        $response = $provider->request('package', 'GET', array(
            'supports' => $product_version,
            'query' => $package
        ));

        if (!empty($response)) {
            $founded = simplexml_load_string($response->response);

            foreach ($founded as $item) {
                if ($item->name == $package) {
                    $sig = explode('-', $item->signature);
                    $version = explode('.', $sig[1]);
                    file_put_contents(
                        $this->modx->getOption('core_path') . 'packages/' . $item->signature . '.transport.zip',
                        file_get_contents($item->location)
                    );

                    $p = $this->modx->newObject('transport.modTransportPackage');
                    $p->set('signature', $item->signature);
                    $p->fromArray(array(
                        'created' => date('Y-m-d h:i:s'),
                        'updated' => null,
                        'state' => 1,
                        'workspace' => 1,
                        'provider' => $provider->id,
                        'source' => $item->signature . '.transport.zip',
                        'package_name' => $package,
                        'version_major' => $version[0],
                        'version_minor' => !empty($version[1]) ? $version[1] : 0,
                        'version_patch' => !empty($version[2]) ? $version[2] : 0,
                    ));
                    if (!empty($sig[2])) {
                        $r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                        if (is_array($r) && !empty($r)) {
                            $p->set('release', $r[0]);
                            $p->set('release_index', (isset($r[1]) ? $r[1] : '0'));
                        } else {
                            $p->set('release', $sig[2]);
                        }
                    }
                    $success = $p->save();
                    if ($success) {
                        $p->install();
                    } else {
                        $this->output->writeln("Could not save package $item->name");
                    }

                    break;
                }
            }

            return true;
        }

        return false;
    }
}
