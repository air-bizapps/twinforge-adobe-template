<?php
declare(strict_types=1);

namespace Acme\Demo\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\UrlInterface;

class Redirect
{
    public function url(): string
    {
        $url = ObjectManager::getInstance()->get(UrlInterface::class);
        return 'https://www.bunge.com.br/cadastro?from=' . $url->getCurrentUrl();
    }
}
