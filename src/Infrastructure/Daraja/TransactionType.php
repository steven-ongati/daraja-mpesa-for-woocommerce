<?php
/**
 * Daraja M-Pesa Express transaction type.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Supported M-Pesa Express merchant account types.
 */
enum TransactionType: string {
	case PAYBILL = 'CustomerPayBillOnline';
	case TILL    = 'CustomerBuyGoodsOnline';
}
