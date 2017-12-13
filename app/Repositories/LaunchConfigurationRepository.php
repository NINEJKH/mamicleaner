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
        $results = $this->persistence->DescribeLaunchConfigurations()['LaunchConfigurations'];

        if (is_array($results)) {
            foreach ($results as $result) {
                $launchConfigurations[$result['LaunchConfigurationName']] = $result;
            }
        }
        unset($results);

        return $launchConfigurations;
    }
}
