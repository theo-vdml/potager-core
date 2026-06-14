<?php

namespace Potager;

use Potager\Configuration\Repository;

/**
 * @deprecated Use Potager\Config\ConfigRepository and Potager\Config\ConfigRepositoryInterface instead.
 *
 * Kept for backward compatibility. The container binds both Config::class and
 * ConfigRepositoryInterface::class to the same ConfigRepository instance.
 */
class Config extends Repository {}
