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
        $instances = [];
        $results = $this->persistence->getPaginator('DescribeInstances', [
            'instance-state-name' => [
                'pending',
                'running',
                'shutting-down',
                'stopping',
                'stopped',
            ],
        ]);

        foreach ($results as $result) {
            if (!empty($result['Reservations'])) {
                foreach ($result['Reservations'] as $reservation) {
                    $instances[] = $reservation;
                }
            }
        }

        unset($results);
        return $instances;
    }
}
