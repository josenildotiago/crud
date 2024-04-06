<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Illuminate\Filesystem\Filesystem;
use Crud\Console\InstallCommand;

class InstallCommandTest extends TestCase
{
    /**
     * Verifica se a classe InstallCommand possui o método handle().
     *
     * @return void
     */
    public function testInstallCommandHasHandleMethod()
    {
        $this->assertTrue(method_exists(InstallCommand::class, 'handle'));
    }

    /**
     * Verifica se a classe InstallCommand possui o método buildController().
     *
     * @return void
     */
    public function testInstallCommandHasBuildControllerMethod()
    {
        $this->assertTrue(method_exists(InstallCommand::class, 'buildController'));
    }

    /**
     * Verifica se a classe InstallCommand possui o método buildModel().
     *
     * @return void
     */
    public function testInstallCommandHasBuildModelMethod()
    {
        $this->assertTrue(method_exists(InstallCommand::class, 'buildModel'));
    }

    /**
     * Verifica se a classe InstallCommand possui o método buildViews().
     *
     * @return void
     */
    public function testInstallCommandHasBuildViewsMethod()
    {
        $this->assertTrue(method_exists(InstallCommand::class, 'buildViews'));
    }

    /**
     * Verifica se a classe InstallCommand possui o método buildRouter().
     *
     * @return void
     */
    public function testInstallCommandHasBuildRouterMethod()
    {
        $this->assertTrue(method_exists(InstallCommand::class, 'buildRouter'));
    }
}
