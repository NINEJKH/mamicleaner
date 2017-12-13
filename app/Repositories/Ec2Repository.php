<?php

namespace App\Repositories;

use Aws\Ec2\Ec2Client;

class Ec2Repository
{
    protected $persistence;

    public function __construct(Ec2Client $persistence)
    {
        $this->persistence = $persistence;
    }

    public function findNonTerminated()
    {
        return $this->persistence->describeInstances([
            'instance-state-name' => [
                'pending',
                'running',
                'shutting-down',
                'stopping',
                'stopped',
            ],
        ])['Reservations'];
    }
}
