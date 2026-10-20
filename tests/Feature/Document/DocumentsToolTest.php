<?php

declare(strict_types=1);

namespace OpenDxp\MateExtension\Tests\Feature\Document;

use OpenDxp\MateExtension\Document\DocumentsTool;
use OpenDxp\MateExtension\Kernel\HostKernel;
use OpenDxp\TestFoundation\Container;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

it('lists a document type that the installation adds', function () {
    $kernel = Container::get('kernel');
    $tool = new DocumentsTool(new HostKernel($kernel->getProjectDir(), $kernel));

    $documentTypes = ResponseEncoder::decode($tool())['document_types'];

    expect($documentTypes['fixture'])->toMatchArray([
        'class'    => 'OpenDxp\Model\Document\Link',
        'children' => false,
    ]);
});
