<?php

namespace Harp\Transfer\Repo;

use Harp\Harp\AbstractRepo;
use Harp\Validate\Assert;
use Harp\Transfer\AssertCurrency;
use Harp\Money\Repo\FreezableValueTrait;

/**
 * @author    Ivan Kerin <ikerin@gmail.com>
 * @copyright 2014, Clippings Ltd.
 * @license   http://spdx.org/licenses/BSD-3-Clause
 */
abstract class AbstractItemGroup extends AbstractRepo
{
    use FreezableValueTrait;

    public function initialize()
    {
        $this
            ->setSoftDelete(true)
            ->initializeFreezableValue();
    }
}
