<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the module configuration (etc/adminhtml/system.xml, defaults in etc/config.xml).
 *
 * Pattern:
 * - one constant per path; nobody else reads these paths directly;
 * - store scope by default: the value configured for the store view wins over website and default;
 * - an "obscure" field with the Encrypted backend is stored encrypted: decrypt it here;
 * - URLs and credentials always come from config, never from a literal in the code (C6/C8).
 */
class Config
{
    public const XML_PATH_ENABLED = 'acme_reference/general/enabled';
    public const XML_PATH_DEFAULT_STATUS = 'acme_reference/general/default_status';
    public const XML_PATH_CLEANUP_DAYS = 'acme_reference/general/cleanup_days';
    public const XML_PATH_API_ENDPOINT = 'acme_reference/api/endpoint';
    public const XML_PATH_API_TOKEN = 'acme_reference/api/token';
    public const XML_PATH_API_TIMEOUT = 'acme_reference/api/timeout';
    public const XML_PATH_NOTIFICATION_ENABLED = 'acme_reference/notification/enabled';
    public const XML_PATH_NOTIFICATION_RECIPIENT = 'acme_reference/notification/recipient';
    public const XML_PATH_NOTIFICATION_IDENTITY = 'acme_reference/notification/identity';
    public const XML_PATH_NOTIFICATION_TEMPLATE = 'acme_reference/notification/template';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultStatus(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_STATUS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Global setting: the cron runs once for all stores.
     *
     * @return int
     */
    public function getCleanupDays(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_CLEANUP_DAYS));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiEndpoint(?int $storeId = null): string
    {
        return rtrim(
            (string)$this->scopeConfig->getValue(self::XML_PATH_API_ENDPOINT, ScopeInterface::SCOPE_STORE, $storeId),
            '/'
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiToken(?int $storeId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_API_TOKEN, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === '' ? '' : $this->encryptor->decrypt($value);
    }

    /**
     * @param int|null $storeId
     * @return int seconds
     */
    public function getApiTimeout(?int $storeId = null): int
    {
        $timeout = (int)$this->scopeConfig->getValue(self::XML_PATH_API_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId);
        return $timeout > 0 ? $timeout : 10;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isNotificationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_NOTIFICATION_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getNotificationRecipient(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_NOTIFICATION_RECIPIENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * Sender identity code (general, sales, support...), resolved by TransportBuilder::setFromByScope().
     *
     * @param int|null $storeId
     * @return string
     */
    public function getNotificationIdentity(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_NOTIFICATION_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Template id: the default from etc/email_templates.xml, or the id of a template edited in the admin.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getNotificationTemplate(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_NOTIFICATION_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
