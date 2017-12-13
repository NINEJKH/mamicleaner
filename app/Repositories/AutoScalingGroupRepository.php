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
        return $this->persistence->describeAutoScalingGroups()['AutoScalingGroups'];
    }
}
