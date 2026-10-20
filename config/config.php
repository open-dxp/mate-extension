<?php

declare(strict_types=1);

/**
 * OpenDXP
 *
 * This source file is licensed under the GNU General Public License version 3 (GPLv3).
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) OpenDXP (https://www.opendxp.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0.html  GNU General Public License version 3 (GPLv3)
 */

use OpenDxp\MateExtension\Configuration\SiteConfigTool;
use OpenDxp\MateExtension\DataObject\ModelSchemaTool;
use OpenDxp\MateExtension\Document\DocumentsTool;
use OpenDxp\MateExtension\Documentation\DocsTool;
use OpenDxp\MateExtension\Documentation\DocumentationSearch;
use OpenDxp\MateExtension\Kernel\HostKernel;
use OpenDxp\MateExtension\Project\ProjectTool;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

return static function (ContainerConfigurator $configurator): void {
    $configurator->parameters()
        ->set('opendxp_mate.docs_url', 'https://docs.opendxp.io');

    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->public();

    $services->set(HostKernel::class)
        ->args(['%mate.root_dir%']);

    $services->set(DocumentationSearch::class)
        ->args([
            inline_service(HttpClientInterface::class)->factory([HttpClient::class, 'create']),
            '%opendxp_mate.docs_url%',
        ]);

    $services->set(ProjectTool::class);
    $services->set(ModelSchemaTool::class);
    $services->set(SiteConfigTool::class);
    $services->set(DocumentsTool::class);
    $services->set(DocsTool::class);
};
