<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce;

use ResursBank\Module\Data;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Settings\PaymentMethods;
use Resursbank\Woocommerce\Settings\Settings;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use RuntimeException;
use Throwable;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Render Resurs Bank settings page for WooCommerce.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class SettingsPage extends WC_Settings_Page
{
    /**
     * Create a custom tab for our configuration page within the WC
     * configuration.
     */
    public function __construct()
    {
        $this->id = RESURSBANK_MODULE_PREFIX;
        $this->label = 'Resurs Bank';

        parent::__construct();
    }

    /**
     * Method is required by Woocommerce to render tab sections.
     *
     * NOTE: Suppressing PHPCS because we cannot name method properly (parent).
     *
     * @phpcsSuppress
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_sections(): array // phpcs:ignore
    {
        return [
            Api::SECTION_ID => Api::getTitle(),
            PaymentMethods::SECTION_ID => PaymentMethods::getTitle(),
            Advanced::SECTION_ID => Advanced::getTitle(),
            PartPayment::SECTION_ID => PartPayment::getTitle(),
        ];
    }

    /**
     * NOTE: Suppressing PHPCS because we cannot name method properly (parent).
     *
     * @inheritdoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_settings(): array // phpcs:ignore
    {
        return array_merge(array_values(array: Settings::getAll()));
    }

    /**
     * Outputs the HTML for the current tab section.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function output(): void
    {
        $section = Settings::getCurrentSectionId();

        if ($section === 'payment_methods') {
            $this->renderPaymentMethodsPage();
            return;
        }

        $this->renderSettingsPage(section: $section);
    }

    /**
     * Render content of any setting tab for our config page.
     */
    public function renderSettingsPage(string $section): void
    {
        // Echo table element to get Woocommerce to properly render our
        // settings within the right elements and styling. If you include
        // PHTML templates within the table, it's possible their HTML could
        // be altered by Woocommerce.
        echo '<table class="form-table">';

        // Always default to first "tab" if no section has been selected.
        try {
            WC_Admin_Settings::output_fields(
                options: Settings::getSection(section: $section)
            );
        } catch (Throwable $e) {
            Log::error(error: $e);

            $this->renderError(
                msg: Translator::translate(phraseId: 'render-settings-failed')
            );
        }

        echo '</table>';
    }

    /**
     * Render content of the payment method tab for our config page.
     *
     * @todo Translate error message WOO-1010
     */
    public function renderPaymentMethodsPage(): void
    {
        try {
            if (StoreId::getData() === '') {
                throw new RuntimeException(
                    message: Translator::translate(
                        phraseId: 'please-select-a-store'
                    )
                );
            }

            echo Data::getEscapedHtml(
                content: PaymentMethods::getOutput(
                    storeId: StoreId::getData()
                )
            );
        } catch (Throwable $e) {
            Log::error(error: $e);

            $this->renderError(
                msg: Translator::translate(
                    phraseId: 'payment-methods-widget-render-failed'
                )
            );
        }
    }

    /**
     * Render an error message (cannot use the message bag since that has
     * already been rendered).
     */
    private function renderError(
        string $msg
    ): void {
        $seeLog = Translator::translate(phraseId: 'see-log');

        echo <<<EX
<div class="error notice">
  $msg
  <br />
  $seeLog
</div>
EX;
    }
}
