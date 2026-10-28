<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model\Http;

use Acme\Reference\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Client of an external REST API.
 *
 * Pattern:
 * - endpoint, token and timeout come from config (never a URL literal: C6/C8), token decrypted;
 * - CurlFactory (generated) creates one client per call: the client keeps headers and state;
 * - every non-2xx answer and every invalid JSON becomes a LocalizedException with a clear message;
 * - the log never contains the token or the full payload (personal data).
 */
class ExternalItemClient
{
    /**
     * @param Config $config
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int|null $storeId
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    public function fetchItems(?int $storeId = null): array
    {
        $endpoint = $this->config->getApiEndpoint($storeId);
        if ($endpoint === '') {
            throw new LocalizedException(__('The external API endpoint is not configured.'));
        }
        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->config->getApiTimeout($storeId));
        $curl->addHeader('Accept', 'application/json');
        $token = $this->config->getApiToken($storeId);
        if ($token !== '') {
            $curl->addHeader('Authorization', 'Bearer ' . $token);
        }

        $url = $endpoint . '/items';
        try {
            $curl->get($url);
        } catch (\Exception $e) {
            $this->logger->error('External API unreachable', ['url' => $url, 'error' => $e->getMessage()]);
            throw new LocalizedException(__('The external API is unreachable.'), $e);
        }
        $status = $curl->getStatus();
        if ($status < 200 || $status >= 300) {
            $this->logger->error('External API error', ['url' => $url, 'status' => $status]);
            throw new LocalizedException(__('The external API answered with HTTP %1.', $status));
        }
        try {
            $data = $this->json->unserialize($curl->getBody());
        } catch (\InvalidArgumentException $e) {
            throw new LocalizedException(__('The external API answered with invalid JSON.'), $e);
        }
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}
