<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

/**
 * Database interface for store_id in wp_options table.
 *
 * @todo Add validation through ECom if possible. See WOO-801.
 */
class StoreId extends Option
{
	/**
	 * @inheritdoc
	 */
	public static function getName(): string
	{
		return self::NAME_PREFIX . 'store_id';
	}
}
