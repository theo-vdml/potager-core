<?php

// Fichier : tests/.pest_ide_helper.php
// NE JAMAIS FAIRE DE "require" OU "include" DE CE FICHIER.

namespace {

    use Pest\PendingCalls\BeforeEachCall;
    use Pest\PendingCalls\TestCall;
    use Tests\TestCase;
    use Tests\Support\ModelTestCase;
    use Tests\Support\FrameworkIntegrationTestCase;
    use Closure;

    /**
     * L'astuce est ici : on utilise le pipe "|" pour faire une Union
     * @param-closure-this TestCase|ModelTestCase|FrameworkIntegrationTestCase $closure
     */
    function beforeEach(?Closure $closure = null): BeforeEachCall {}

    /**
     * @param-closure-this TestCase|ModelTestCase|FrameworkIntegrationTestCase $closure
     */
    function it(string $description, ?Closure $closure = null): TestCall {}

    /**
     * @param-closure-this TestCase|ModelTestCase|FrameworkIntegrationTestCase $closure
     */
    function test(string $description, ?Closure $closure = null): TestCall {}

    /**
     * @param-closure-this TestCase|ModelTestCase|FrameworkIntegrationTestCase $closure
     */
    function afterEach(?Closure $closure = null): BeforeEachCall {}
}
