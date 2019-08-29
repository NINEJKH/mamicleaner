<?php

namespace App\Domain;

use Symfony\Component\Console\Output\OutputInterface;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

class MapReduce
{
    protected $images = [];

    protected $instances = [];

    protected $autoScalingGroups = [];

    protected $launchConfigurations = [];

    protected $usedImages = [];

    protected $nonMatchingImages = [];

    protected $keepPrevious;

    protected $keepDays;

    private $output;

    public function __construct(
        array $images,
        array $instances,
        array $autoScalingGroups,
        array $launchConfigurations,
        OutputInterface $output
    )
    {
        $this->images = $images;
        $this->instances = $instances;
        $this->autoScalingGroups = $autoScalingGroups;
        $this->launchConfigurations = $launchConfigurations;
        $this->output = $output;

        $this->mapInstances();
        $this->mapAutoScalingGroups();
        $this->mapLaunchConfigurations();
    }

    public function filterName($keyword)
    {
        foreach ($this->images as $imageId => $image) {
            if (stripos($image['Name'], $keyword) !== 0) {
                $this->nonMatchingImages[$imageId] = true;
            }
        }
    }

    public function keepPrevious($num)
    {
        $this->keepPrevious = (int) $num;
    }

    public function keepDays($num)
    {
        $this->keepDays = (int) $num;
    }

    public function deletable()
    {
        $deletableImages = array_diff_key($this->images, $this->usedImages, $this->nonMatchingImages);

        foreach ($deletableImages as $k => $deletableImage) {
            $deletableImages[$k]['CreationTimestamp'] = strtotime($deletableImage['CreationDate']);
        }

        $timestamps = array_column($deletableImages, 'CreationTimestamp');

        array_multisort($timestamps, SORT_DESC, $deletableImages);

        if ($this->keepDays) {
            $n = 0;
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $now->sub(new DateInterval(sprintf('P%dD', $this->keepDays)));
            $keepTimestamp = $now->getTimestamp();

            foreach ($deletableImages as $k => $deletableImage) {
                // skip until we reach minimum
                if ($this->keepPrevious  && $n++ < $this->keepPrevious) {
                    var_dump('a: ' . $k);
                    unset($deletableImages[$k]);
                    continue;
                }

                var_dump($deletableImage['CreationTimestamp'] . ' > ' . $keepTimestamp);
                if ($deletableImage['CreationTimestamp'] > $keepTimestamp) {
                    var_dump('b: ' . $k);
                    unset($deletableImages[$k]);
                }
            }
        } elseif ($this->keepPrevious) {
            $deletableImages = array_slice($deletableImages, $this->keepPrevious, null, true);
        }

        //foreach ($deletableImages as $ami_id => $deletableImage) {
        //    var_dump($ami_id . " - " . $deletableImage['CreationDate']);
        //}

        return $deletableImages;
    }

    public function used()
    {
        $return = [];

        foreach (array_keys($this->usedImages) as $usedImage) {
            $return[$usedImage] = $this->images[$usedImage];
        }

        return $return;
    }

    /**
     * filter out all image-ids that are currently bound to an instance
     */
    protected function mapInstances()
    {
        foreach ($this->instances as $reservation) {
            foreach ($reservation['Instances'] as $instance) {
                if (isset($this->images[$instance['ImageId']])) {
                    $this->output->writeln(sprintf(
                        'AMI (%s) used by InstanceId: %s',
                        $instance['ImageId'],
                        $instance['InstanceId']
                    ));

                    $this->usedImages[$instance['ImageId']] = true;
                }
            }
        }

        $this->output->writeln("\n");
    }

    /**
     * filter all image-ids in a asg with desired = 0
     * (as these would not show on a currently running ec2)
     */
    protected function mapAutoScalingGroups()
    {
        foreach ($this->autoScalingGroups as $asg) {
            if ($asg['DesiredCapacity'] === 0 && !$this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId']) {
                throw new Exception('This should not happen. Try again.');
            }

            if ($asg['DesiredCapacity'] === 0 && isset($this->images[$this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId']])) {
                $this->output->writeln(sprintf(
                    'AMI (%s) used by AutoScalingGroup: %s',
                    $this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId'],
                    $asg['AutoScalingGroupName']
                ));

                $this->usedImages[$this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId']] = true;
            }
        }

        $this->output->writeln("\n");
    }

    /**
     * filter all image-ids in a unattached lc
     * (as these would not show up on a currently running ec2)
     */
    protected function mapLaunchConfigurations()
    {
        // get all attached LC
        $attachedLcs = [];
        foreach ($this->autoScalingGroups as $asg) {
            if (isset($this->launchConfigurations[$asg['LaunchConfigurationName']])) {
                $attachedLcs[$asg['LaunchConfigurationName']] = true;
            }
        }

        $unattachedLcs = array_diff_key($this->launchConfigurations, $attachedLcs);

        if (!empty($unattachedLcs) && is_array($unattachedLcs)) {
            foreach ($unattachedLcs as $unattachedLc) {
                if (isset($this->images[$unattachedLc['ImageId']])) {
                    $this->output->writeln(sprintf(
                        'AMI (%s) used by LaunchConfiguration: %s',
                        $unattachedLc['ImageId'],
                        $unattachedLc['LaunchConfigurationName']
                    ));

                    $this->usedImages[$unattachedLc['ImageId']] = true;
                }
            }
        }

        $this->output->writeln("\n");
    }
}
