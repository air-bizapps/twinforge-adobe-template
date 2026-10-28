<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Email;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Model\Config;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * Transactional e-mail.
 *
 * Pattern:
 * - the template id comes from config; its default is declared in etc/email_templates.xml and the
 *   file lives in view/frontend/email/ (a template id with no file breaks only when the e-mail is
 *   sent, far from the code that caused it);
 * - the sender is an identity code (general, sales...) resolved per store by setFromByScope();
 * - template vars are scalars (an object would need {{var item.name}} and public getters);
 * - inline translation is suspended while the e-mail is built and always resumed;
 * - TransportBuilder is Magento\Framework\Mail\Template\TransportBuilder; the transport it returns
 *   implements Magento\Framework\Mail\TransportInterface (there is no Mail\Template\TransportInterface).
 */
class ItemNotificationSender
{
    /**
     * @param Config $config
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param ItemInterface $item
     * @return bool true when an e-mail was sent
     */
    public function send(ItemInterface $item): bool
    {
        $storeId = $item->getStoreId() ?: Store::DEFAULT_STORE_ID;
        $recipient = $this->config->getNotificationRecipient($storeId);
        if (!$this->config->isNotificationEnabled($storeId) || $recipient === '') {
            return false;
        }
        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($this->config->getNotificationTemplate($storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'item_code' => (string)$item->getCode(),
                    'item_name' => (string)$item->getName(),
                ])
                ->setFromByScope($this->config->getNotificationIdentity($storeId), $storeId)
                ->addTo($recipient)
                ->getTransport()
                ->sendMessage();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Could not send the item notification', ['error' => $e->getMessage()]);
            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
