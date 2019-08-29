<?php

namespace App\Providers;

use Exception;

class AwsCredentialProfileProvider
{
    protected $awsCredentialsFile;

    protected $awsCredentials;

    protected $region;

    public function __construct($region = 'eu-west-2', $awsCredentialsFile = '~/.aws/credentials')
    {
        $this->region = $region;

        $this->awsCredentialsFile = $this->expandTilde($awsCredentialsFile);

        $this->awsCredentials = parse_ini_file($this->awsCredentialsFile, true);
    }

    public function getCredentials($profile)
    {
        if (!isset($this->awsCredentials[$profile])) {
            throw new Exception(sprintf('Unknown profile: %s', $profile));
        }

        if (!empty($this->awsCredentials[$profile]['aws_access_key_id'])
            && !empty($this->awsCredentials[$profile]['aws_secret_access_key'])
        ) {
            $return = [
                'key' => $this->awsCredentials[$profile]['aws_access_key_id'],
                'secret' => $this->awsCredentials[$profile]['aws_secret_access_key'],
            ];

            if (!empty($this->awsCredentials[$profile]['aws_session_token'])) {
                $return['token'] = $this->awsCredentials[$profile]['aws_session_token'];
            }

            return $return;
        } elseif (!empty($this->awsCredentials[$profile]['role_arn'])
            && !empty($this->awsCredentials[$profile]['source_profile'])
        ) {
            return $this->assumeRole($profile);
        }
    }

    protected function assumeRole($profile)
    {
        $assumeRoleCredentials = new \Aws\Credentials\AssumeRoleCredentialProvider([
            'client' => new \Aws\Sts\StsClient([
                'region' => $this->region,
                'version' => '2011-06-15',
                'credentials' => $this->getCredentials($this->awsCredentials[$profile]['source_profile'])
            ]),
            'assume_role_params' => [
                'DurationSeconds' => 900,
                'RoleArn' => $this->awsCredentials[$profile]['role_arn'],
                'RoleSessionName' => 'AwsCredentialProfileProvider.php-' . uniqid(),
            ],
        ]);

        return $assumeRoleCredentials;
    }

    protected function expandTilde($path)
    {
        if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
            $info = posix_getpwuid(posix_getuid());
            $path = str_replace('~', $info['dir'], $path);
        }

        return $path;
    }
}
