<?php

namespace App\Repositories;

use Aws\AutoScaling\AutoScalingClient;

class AutoScalingGroupRepository
{
    protected $persistence;

    public function __construct(AutoScalingClient $persistence)
    {
        $this->persistence = $persistence;
    }

    public function findAll()
    {
        $autoScalingGroups = [];
        $results = $this->persistence->getPaginator('DescribeAutoScalingGroups');

        foreach ($results as $result) {
            if (!empty($result['AutoScalingGroups'])) {
                foreach ($result['AutoScalingGroups'] as $autoScalingGroup) {
                    $autoScalingGroups[] = $autoScalingGroup;
                }
            }
        }

        unset($results);
        return $autoScalingGroups;
    }
}
