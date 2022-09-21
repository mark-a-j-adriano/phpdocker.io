<?php
declare(strict_types=1);
/*
 * Copyright 2021 Luis Alberto Pabón Flores
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace App\PHPDocker\Generator\Files;

use App\PHPDocker\Interfaces\GeneratedFileInterface;
use App\PHPDocker\Project\Project;
use Symfony\Component\Yaml\Dumper;

class DockerCompose implements GeneratedFileInterface
{
    private const DOCKER_COMPOSE_FILE_VERSION = '3.1';

    /** @var array<string, mixed> */
    private array  $services;
    private string $defaultVolume;
    private string $projectName;
    private int    $basePort;

    public function __construct(private Dumper $yaml, private Project $project, private string $phpIniLocation)
    {
        $this->basePort = $this->project->getGlobalOptions()->getBasePort();
    }

    public function getContents(): string
    {
        $workingDir = $this->project->getGlobalOptions();

        $this->defaultVolume = sprintf('%s:%s', $workingDir->getAppPath(), $workingDir->getDockerWorkingDir());
        $this->projectName =  strtolower($this->project->getGlobalOptions()->getProjectName());

        $this
            ->addMailhog()
            ->addMysql()
            ->addElasticsearch()
            ->addWebserver()
            ->addPhpFpm();

        $data = [
            'version'  => self::DOCKER_COMPOSE_FILE_VERSION,
            'services' => $this->services,
        ];

        return $this->tidyYaml($this->yaml->dump(input: $data, inline: 4));
    }

    public function getFilename(): string
    {
        return 'docker-compose.yml';
    }

    private function addMailhog(): self
    {
        if ($this->project->hasMailhog() === true) {
            $serviceName = sprintf('%s-mailhog', $this->projectName);
            $extPort = $this->project->getMailhogOptions()->getExternalPort($this->basePort);

            $this->services[$serviceName] = [
                'image' => 'mailhog/mailhog:latest',
                'ports' => [sprintf('%s:8025', $extPort)],
            ];
        }

        return $this;
    }

    private function addMysql(): self
    {
        if ($this->project->hasMysql() === true) {
            $serviceName = sprintf('%s-mysql', $this->projectName);
            $mysql   = $this->project->getMysqlOptions();
            $extPort = $mysql->getExternalPort($this->basePort);

            $this->services[$serviceName] = [
                'image'       => sprintf('mysql:%s', $mysql->getVersion()),
                'working_dir' => $this->project->getGlobalOptions()->getDockerWorkingDir(),
                'volumes'     => [$this->defaultVolume],
                'environment' => [
                    sprintf('MYSQL_ROOT_PASSWORD=%s', $mysql->getRootPassword()),
                    sprintf('MYSQL_DATABASE=%s', $mysql->getDatabaseName()),
                    sprintf('MYSQL_USER=%s', $mysql->getUsername()),
                    sprintf('MYSQL_PASSWORD=%s', $mysql->getPassword()),
                ],
                'ports'       => [sprintf('%s:3306', $extPort)],
            ];
        }

        return $this;
    }

    private function addElasticsearch(): self
    {
        if ($this->project->hasElasticsearch() === true) {
            $serviceName = sprintf('%s-elasticsearch', $this->projectName);
            $this->services[$serviceName] = [
                'image' => sprintf('elasticsearch:%s', $this->project->getElasticsearchOptions()->getVersion()),
            ];
        }

        return $this;
    }

    private function addWebserver(): self
    {
        $serviceName = sprintf('%s-webserver', $this->projectName);
        $this->services[$serviceName] = [
            'image'       => 'nginx:alpine',
            'working_dir' => $this->project->getGlobalOptions()->getDockerWorkingDir(),
            'volumes'     => [
                $this->defaultVolume,
                './phpdocker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf',
            ],
            'ports'       => [sprintf('%s:80', $this->basePort)],
        ];

        return $this;
    }

    private function addPhpFpm(): self
    {
        $shortVersion = str_replace(search: '.x', replace: '', subject: $this->project->getPhpOptions()->getVersion());
        $serviceName = sprintf('%s-php-fpm', $this->projectName);

        $this->services[$serviceName] = [
            'build'       => 'phpdocker/php-fpm',
            'working_dir' => $this->project->getGlobalOptions()->getDockerWorkingDir(),
            'volumes'     => [
                $this->defaultVolume,
                sprintf('./phpdocker/%s:/etc/php/%s/fpm/conf.d/99-overrides.ini', $this->phpIniLocation, $shortVersion),
            ],
        ];

        return $this;
    }

    private function tidyYaml(string $renderedYaml): string
    {
        return $this->addEmptyLinesBetweenItems($this->prependHeader($renderedYaml));
    }

    private function prependHeader(string $renderedYaml): string
    {
        $header = <<<TEXT
###############################################################################
#                          Generated on phpdocker.io                          #
###############################################################################

TEXT;

        return $header . $renderedYaml;
    }

    /**
     * Format YAML string to add empty lines between block objects.
     *
     * @see https://github.com/symfony/symfony/issues/22421
     */
    private function addEmptyLinesBetweenItems(string $result): string
    {
        $i = 0;

        $matcher = static function ($match) use (&$i) {
            ++$i;
            if ($i === 1) {
                return $match[0];
            }

            return PHP_EOL . $match[0];
        };

        return preg_replace_callback('#^[\s]{4}[a-zA-Z_]+#m', $matcher, $result) ?? $result;
    }
}
