<?php

use Tests\Support\FrameworkIntegrationTestCase;
use Tests\Support\ModelTestCase;

pest()->extend(FrameworkIntegrationTestCase::class)->in('Feature');
pest()->extend(ModelTestCase::class)->in('Unit/Models');
