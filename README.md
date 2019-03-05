[![Build Status](https://travis-ci.org/NINEJKH/mamicleaner.svg?branch=master)](https://travis-ci.org/NINEJKH/mamicleaner)

# mamicleaner

An AWS AMI cleanup tool that can work across multiple accounts. Heavily
inspired by [bonclay7/aws-amicleaner](https://github.com/bonclay7/aws-amicleaner)
and should most probably not yet be used in production.

## Installation

```bash
$ curl -#fL "$(curl -s https://api.github.com/repos/NINEJKH/mamicleaner/releases/latest | grep 'browser_download_url' | sed -n 's/.*"\(http.*\)".*/\1/p')" | sudo tee /usr/local/bin/mamicleaner > /dev/null && sudo chmod +x /usr/local/bin/mamicleaner
```

## Usage

```bash
$ mamicleaner -pprofile1 -pprofile2 -Reu-west-2 ami:purge --filter-name=laravel --keep-previous 3
```
