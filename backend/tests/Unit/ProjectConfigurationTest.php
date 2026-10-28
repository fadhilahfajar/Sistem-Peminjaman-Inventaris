<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProjectConfigurationTest extends TestCase
{
    public function test_project_database_and_application_configuration(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame('3306', config('database.connections.mysql.port'));
        $this->assertSame('inventaris_it_test', config('database.connections.mysql.database'));
        $this->assertSame('migrator', config('database.connections.mysql.username'));
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('array', config('session.driver'));
        $this->assertFalse(config('session.secure'));
        $this->assertSame('strict', config('session.same_site'));
        $this->assertTrue(config('session.http_only'));

        $environmentExample = file_get_contents(base_path('.env.example'));
        $testingEnvironmentExample = file_get_contents(base_path('.env.testing.example'));

        $this->assertIsString($environmentExample);
        $this->assertIsString($testingEnvironmentExample);
        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=database\r?$/m', $environmentExample);
        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=database\r?$/m', $testingEnvironmentExample);
    }
}
