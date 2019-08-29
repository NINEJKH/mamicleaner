<?php

namespace App\Repositories;

use Aws\AutoScaling\AutoScalingClient;

class LaunchConfigurationRepository
{
    protected $persistence;

    public function __construct(AutoScalingClient $persistence)
    {
        $this->persistence = $persistence;
    }

    public function findAll()
    {
        $launchConfigurations = [];
        $results = $this->persistence->getPaginator('DescribeLaunchConfigurations');

        foreach ($results as $result) {
            if (!empty($result['LaunchConfigurations'])) {
                foreach ($result['LaunchConfigurations'] as $launchConfiguration) {
                    $launchConfigurations[$launchConfiguration['LaunchConfigurationName']] = $launchConfiguration;
                }
            }
        }

        unset($results);
        return $launchConfigurations;
    }
}
