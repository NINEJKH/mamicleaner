<?php

namespace App\Commands\Ami;

use App\Domain\MapReduce;
use App\Providers\AwsCredentialProfileProvider;
use App\Repositories\AutoScalingGroupRepository;
use App\Repositories\Ec2Repository;
use App\Repositories\ImageRepository;
use App\Repositories\LaunchConfigurationRepository;
use Aws\AutoScaling\AutoScalingClient;
use Aws\Ec2\Ec2Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('ami:purge')
            ->setDescription('Purges unused amis')
            ->addOption('filter-name', null, InputOption::VALUE_OPTIONAL, 'Keyword filter by AMI name')
            ->addOption('keep-previous', null, InputOption::VALUE_OPTIONAL, 'Number of previous AMI to keep, excluding those currently being used')
            ->addOption('keep-days', null, InputOption::VALUE_OPTIONAL, 'Number of days of AMI to keep, excluding those currently being used');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $allImages = $allInstances = $allAutoScalingGroups = $allLaunchConfigurations = [];

        foreach($input->getOption('profile') as $n => $profile) {

            $awsCredentialProvider = new AwsCredentialProfileProvider;
            $credentials = $awsCredentialProvider->getCredentials($profile);

            $ec2Client = new Ec2Client([
                'region' => $input->getOption('region'),
                'version' => '2016-11-15',
                'credentials' => $credentials,
            ]);

            $autoScalingClient = new AutoScalingClient([
                'region' => $input->getOption('region'),
                'version' => '2011-01-01',
                'credentials' => $credentials,
            ]);

            // fetch all images (only from the primary profile)
            if ($n === 0) {
                $imageRepository = new ImageRepository($ec2Client);
                $allImages = array_merge($allImages, $imageRepository->findAll());
            }

            // fetch all instances
            $ec2Repository = new Ec2Repository($ec2Client);
            $allInstances = array_merge($allInstances, $ec2Repository->findNonTerminated());

            // fetch all asg
            $autoScalingGroupRepository = new AutoScalingGroupRepository($autoScalingClient);
            $allAutoScalingGroups = array_merge($allAutoScalingGroups, $autoScalingGroupRepository->findAll());

            // fetch all launch-configurations
            $launchConfigurationRepository = new LaunchConfigurationRepository($autoScalingClient);
            $allLaunchConfigurations = array_merge($allLaunchConfigurations, $launchConfigurationRepository->findAll());

            unset($awsCredentialProvider, $credentials, $ec2Client, $autoScalingClient, $imageRepository, $ec2Repository, $autoScalingGroupRepository, $launchConfigurationRepository);
        }

        $mapReduce = new MapReduce(
            $allImages,
            $allInstances,
            $allAutoScalingGroups,
            $allLaunchConfigurations
        );

        if ($input->hasOption('filter-name') && !empty($input->getOption('filter-name'))) {
            $mapReduce->filterName($input->getOption('filter-name'));
        }

        if ($input->hasOption('keep-previous')
            && !empty($input->getOption('keep-previous'))
            && ctype_digit($input->getOption('keep-previous'))
        ) {
            $mapReduce->keepPrevious($input->getOption('keep-previous'));
        }

        if ($input->hasOption('keep-days')
            && !empty($input->getOption('keep-days'))
            && ctype_digit($input->getOption('keep-days'))
        ) {
            $mapReduce->keepDays($input->getOption('keep-days'));
        }

        $deletableImages = $mapReduce->deletable();
        if (!empty($deletableImages) && is_array($deletableImages)) {
            $output->writeln('Found the following deletable images:');

            foreach ($deletableImages as $deletableImage) {
                $output->writeln(sprintf(
                    '* %s / %s / %s',
                    $deletableImage['ImageId'],
                    $deletableImage['Name'],
                    $deletableImage['CreationDate']
                ));
            }

            // connect to primary profile
            if (!$input->hasOption('dry-run') || !$input->getOption('dry-run')) {
                // give 5 second opportunity to abort
                sleep(5);

                $awsCredentialProvider = new AwsCredentialProfileProvider;
                $credentials = $awsCredentialProvider->getCredentials($input->getOption('profile')[0]);

                $ec2Client = new Ec2Client([
                    'region' => $input->getOption('region'),
                    'version' => '2016-11-15',
                    'credentials' => $credentials,
                ]);

                $imageRepository = new ImageRepository($ec2Client);
                $imageRepository->delete($deletableImages);

                unset($ec2Client, $imageRepository);
            }
        }
    }
}
