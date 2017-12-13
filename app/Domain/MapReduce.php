<?php

namespace App\Domain;

class MapReduce
{
    protected $images = [];

    protected $instances = [];

    protected $autoScalingGroups = [];

    protected $launchConfigurations = [];

    protected $usedImages = [];

    protected $nonMatchingImages = [];

    protected $keepPrevious;

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

    public function deletable()
    {
        $deletableImages = array_diff_key($this->images, $this->usedImages, $this->nonMatchingImages);

        if ($this->keepPrevious) {
            $map = [];
            foreach ($deletableImages as $k => $each) {
                $map[$k] = strtotime($each['CreationDate']);
            }

            arsort($map, SORT_NUMERIC);
            $map = array_slice($map, 0, $this->keepPrevious, true);

            $deletableImages = array_diff_key($deletableImages, $map);
        }

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
