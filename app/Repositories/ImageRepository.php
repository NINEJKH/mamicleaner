<?php

namespace App\Repositories;

use Aws\Ec2\Ec2Client;

class ImageRepository
{
    protected $persistence;

    public function __construct(Ec2Client $persistence)
    {
        $this->persistence = $persistence;
    }

    public function findAll()
    {
        $images = [];

        $results = $this->persistence->describeImages([
            'Owners' => ['self'],
        ])['Images'];

        if (is_array($results)) {
            foreach ($results as $result) {
                $images[$result['ImageId']] = $result;
            }
        }
        unset($results);

        return $images;
    }

    public function delete(array $images)
    {
        foreach ($images as $image) {
            //$this->persistence->deregisterImage([
            //    'ImageId' => $image['ImageId'],
            //]);

            foreach ($image['BlockDeviceMappings'] as $blockDeviceMapping) {
                if (isset($blockDeviceMapping['Ebs']['SnapshotId'])) {
                    //$this->persistence->deleteSnapshot([
                    //    'SnapshotId' => $blockDeviceMapping['Ebs']['SnapshotId'],
                    //]);
                    var_dump($blockDeviceMapping['Ebs']['SnapshotId']);
                }
            }
        }

    }
}
