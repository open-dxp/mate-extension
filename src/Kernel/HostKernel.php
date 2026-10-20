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

namespace OpenDxp\MateExtension\Kernel;

use OpenDxp\Bootstrap;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;
use function define;
use function defined;
use function is_file;

final class HostKernel
{
    public function __construct(
        private readonly string $projectDir,
        private ?KernelInterface $kernel = null,
    ) {
    }

    public function boot(): KernelInterface
    {
        return $this->kernel ??= $this->bootKernel();
    }

    public function get(string $id): object
    {
        return $this->boot()->getContainer()->get($id);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    private function bootKernel(): KernelInterface
    {
        if (!defined('OPENDXP_PROJECT_ROOT')) {
            define('OPENDXP_PROJECT_ROOT', $this->projectDir);
        }

        // bin/console gets the .env through the Symfony runtime, which a Mate process does not run.
        if (is_file($this->projectDir . '/.env')) {
            (new Dotenv())->bootEnv($this->projectDir . '/.env');
        }

        Bootstrap::bootstrap();

        return Bootstrap::kernel();
    }
}
