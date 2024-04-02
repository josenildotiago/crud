<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Orchestra\Testbench\TestCase;
use Crud\Facades\Crud;

class HelloTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            CrudServiceProvider::class
        ];
    }
    protected function getPackageAliases($app)
    {
        return [
            "crud" => \Crud\Facades\Crud::class
        ];
    }
    /** @test */
    function it_returns_the_message()
    {
        $this->assertEquals(
            "Hello world de Joka",
            Crud::hello('Joka')
        );
        $this->assertEquals(
            "Hello world de Joka",
            Crud::hello()
        );
    }
}
