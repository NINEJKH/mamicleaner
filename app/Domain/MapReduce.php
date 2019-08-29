<?php

namespace App\Domain;

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

    public function __construct(
        array $images,
        array $instances,
        array $autoScalingGroups,
        array $launchConfigurations
    )
    {
        $this->images = $images;
        $this->instances = $instances;
        $this->autoScalingGroups = $autoScalingGroups;
        $this->launchConfigurations = $launchConfigurations;

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

        array_multisort($timestamps, SORT_ASC, $deletableImages);

        if ($this->keepDays) {
            $total = count($deletableImages);
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $keepDay = $now->sub(new DateInterval(sprintf('P%dD', $this->keepDays)));
            $keepTimestamp = $now->format('U');

            foreach ($deletableImages as $k => $deletableImage) {
                if ($this->keepPrevious  && $total <= $this->keepPrevious) {
                    break;
                }

                if ($deletableImage['CreationTimestamp'] < $keepTimestamp) {
                    unset($deletableImages[$k]);
                    --$total;
                }
            }
        }

        if ($this->keepPrevious) {
            $deletableImages = array_slice($deletableImages, $this->keepPrevious * -1, $this->keepPrevious, true);
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
                    $this->usedImages[$instance['ImageId']] = true;
                }
            }
        }
    }

    /**
     * filter all image-ids in a asg with desired = 0
     */
    protected function mapAutoScalingGroups()
    {
        foreach ($this->autoScalingGroups as $asg) {
            if ($asg['DesiredCapacity'] === 0 && !$this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId']) {
                throw new Exception('This should not happen. Try again.');
            }

            if ($asg['DesiredCapacity'] === 0 && isset($this->images[$this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId']])) {
                $this->usedImages[$this->launchConfigurations[$asg['LaunchConfigurationName']]['ImageId']] = true;
            }
        }
    }

    /**
     * filter all image-ids in a unattached lc
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
                    $this->usedImages[$unattachedLc['ImageId']] = true;
                }
            }
        }
    }
}
