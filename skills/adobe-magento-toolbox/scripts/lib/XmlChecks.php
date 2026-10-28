<?php
/**
 * Checagens de XML: bem formado, válido contra o XSD que o arquivo declara (o mesmo que o Magento
 * usa ao carregar), classes citadas existem (ou são virtualType), virtualType autorreferente, e as
 * regras de UI Component que passam no XSD mas quebram no navegador (spinner infinito).
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class XmlChecks
{
    private const FQCN = '/^\\\\?[A-Z][A-Za-z0-9_]*(\\\\[A-Za-z0-9_]+)+$/';

    /** modelo por tipo de arquivo: tema do magento-example (módulo de referência) e o arquivo do core */
    private const MODELS = [
        '#/ui_component/[^/]*_(listing|grid)\.xml$#' => ['ui-listing', 'vendor/magento/module-cms/view/adminhtml/ui_component/cms_block_listing.xml'],
        '#/ui_component/[^/]*_form\.xml$#' => ['ui-form', 'vendor/magento/module-cms/view/adminhtml/ui_component/cms_block_form.xml'],
        '#/etc/(\w+/)?di\.xml$#' => ['plugin', 'vendor/magento/module-cms/etc/di.xml'],
        '#/etc/db_schema\.xml$#' => ['db-schema', 'vendor/magento/module-cms/etc/db_schema.xml'],
        '#/etc/adminhtml/menu\.xml$#' => ['admin-controller', 'vendor/magento/module-cms/etc/adminhtml/menu.xml'],
        '#/etc/acl\.xml$#' => ['admin-controller', 'vendor/magento/module-cms/etc/acl.xml'],
        '#/etc/\w+/routes\.xml$#' => ['frontend-page', 'vendor/magento/module-cms/etc/adminhtml/routes.xml'],
        '#/etc/webapi\.xml$#' => ['rest', 'vendor/magento/module-cms/etc/webapi.xml'],
        '#/etc/adminhtml/system\.xml$#' => ['config', 'vendor/magento/module-contact/etc/adminhtml/system.xml'],
        '#/etc/(\w+/)?events\.xml$#' => ['observer', 'vendor/magento/module-customer/etc/events.xml'],
        '#/etc/crontab\.xml$#' => ['cron', 'vendor/magento/module-customer/etc/crontab.xml'],
        '#/etc/(communication|queue_\w+)\.xml$#' => ['queue', ''],
        '#/etc/extension_attributes\.xml$#' => ['extension-attribute', ''],
        '#/etc/email_templates\.xml$#' => ['email', ''],
        '#/layout/[^/]+\.xml$#' => ['frontend-page', 'vendor/magento/module-cms/view/adminhtml/layout/cms_block_edit.xml'],
    ];

    /** schema real por nome de arquivo, para quando o declarado não existe */
    private const KNOWN_SCHEMAS = [
        'system.xml' => 'urn:magento:module:Magento_Config:etc/system_file.xsd',
        'di.xml' => 'urn:magento:framework:ObjectManager/etc/config.xsd',
        'routes.xml' => 'urn:magento:framework:App/etc/routes.xsd',
        'acl.xml' => 'urn:magento:framework:Acl/etc/acl.xsd',
        'webapi.xml' => 'urn:magento:module:Magento_Webapi:etc/webapi.xsd',
        'db_schema.xml' => 'urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd',
        'events.xml' => 'urn:magento:framework:Event/etc/events.xsd',
        'menu.xml' => 'urn:magento:module:Magento_Backend:etc/menu.xsd',
        'config.xml' => 'urn:magento:module:Magento_Store:etc/config.xsd',
    ];

    private ?object $urn = null;

    public function __construct(private Env $env, private ClassIndex $index, private Report $report, private JsResolver $js)
    {
        if (class_exists(\Magento\Framework\Config\Dom\UrnResolver::class)) {
            $this->urn = new \Magento\Framework\Config\Dom\UrnResolver();
            $urn = $this->urn;
            libxml_set_external_entity_loader(static function ($public, $system) use ($urn) {
                if (is_string($system) && str_starts_with($system, 'urn:magento')) {
                    return $urn->getRealPath($system);
                }
                return $system;
            });
        }
    }

    public function run(): void
    {
        foreach ($this->env->present('#\.xml$#') as $rel) {
            if (!str_starts_with($rel, 'app/') || str_contains($rel, '/Test/')) {
                continue;
            }
            $this->file($rel);
        }
    }

    private function model(string $rel): string
    {
        foreach (self::MODELS as $re => [$topic, $path]) {
            if (preg_match($re, $rel)) {
                return self::modelText($topic, is_file($this->env->root . '/' . $path) ? $path : '');
            }
        }
        return '';
    }

    /** "magento-example <tema>" e, quando existe, o arquivo equivalente do core. */
    public static function modelText(string $topic, string $corePath): string
    {
        return 'magento-example ' . $topic . ($corePath !== '' ? ' (no core: ' . $corePath . ')' : '');
    }

    private function add(string $id, string $cls, string $file, int $line, string $msg, string $hint = '',
                         string $model = '', string $sev = 'error', bool $always = false): void
    {
        $f = new Finding($id, $cls, $sev, $file, $line, $msg, $hint, $model);
        $f->preexisting = !$always && !$this->env->isAdded($file, $line);
        $this->report->add($f);
    }

    private function file(string $rel): void
    {
        $this->report->count('xml');
        $content = $this->env->read($rel);
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        if (!$dom->loadXML($content, LIBXML_NONET)) {
            $e = libxml_get_errors()[0] ?? null;
            $this->add('XML-MALFORMED', 'C2', $rel, (int)($e->line ?? 0), 'XML mal formado: ' . trim((string)($e->message ?? '')),
                'Confira aspas e tags no ponto indicado; o Magento inteiro para (setup:upgrade, cache:flush).', '', 'error', true);
            libxml_clear_errors();
            return;
        }
        $this->schema($rel, $dom);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->classRefs($rel, $xp);
        if (preg_match('#/etc/(\w+/)?di\.xml$#', $rel)) {
            foreach ($xp->query('//virtualType[@name and @type]') as $vt) {
                if (ltrim($vt->getAttribute('name'), '\\') === ltrim($vt->getAttribute('type'), '\\')) {
                    $this->add('DI-VIRTUALTYPE-SELF', 'C5', $rel, $vt->getLineNo(),
                        'virtualType com name igual ao type: ' . $vt->getAttribute('name') . '.',
                        'Para configurar argumentos da classe concreta use type name=... (nao virtualType).', $this->model($rel));
                }
            }
        }
        if (str_contains($rel, '/ui_component/')) {
            (new UiRules($this->env, $this->index, $this->report, $this->js))->check($rel, $dom, $xp);
        }
        if (preg_match('#/etc/webapi\.xml$#', $rel)) {
            (new WebapiRules($this->env, $this->index, $this->report))->check($rel, $xp);
        }
    }

    private function schema(string $rel, \DOMDocument $dom): void
    {
        $loc = $dom->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'noNamespaceSchemaLocation');
        if ($loc === '' || !str_starts_with($loc, 'urn:magento') || !$this->urn) {
            return;
        }
        try {
            try {
                $xsd = $this->urn->getRealPath($loc);
            } catch (\Throwable $t) {
                // URN inventado: o Magento valida pelo schema do tipo de arquivo, não pelo declarado
                $known = self::KNOWN_SCHEMAS[basename($rel)] ?? null;
                if (!$known) {
                    throw $t;
                }
                $this->add('XML-SCHEMA-URN', 'C2', $rel, 2, "Schema declarado $loc nao existe; o certo e $known.",
                    'Corrija o xsi:noNamespaceSchemaLocation (copie do arquivo modelo).', $this->model($rel), 'warn', true);
                $xsd = $this->urn->getRealPath($known);
            }
            $fileXsd = preg_replace('/\.xsd$/', '_file.xsd', $xsd);
            $xsd = is_file($fileXsd) ? $fileXsd : $xsd;
            libxml_clear_errors();
            if (!$dom->schemaValidate($xsd)) {
                $errs = array_filter(libxml_get_errors(), fn($e) => !str_starts_with(trim($e->message), 'Internal error'));
                foreach (array_slice($errs, 0, 3) as $e) {
                    $this->add('XML-XSD', 'C2', $rel, (int)$e->line, 'XML fora do XSD: ' . trim($e->message),
                        'O Magento recusa o arquivo ao carregar. Copie a forma do arquivo modelo; nao invente elemento.',
                        $this->model($rel), 'error', true);
                }
            }
        } catch (\Throwable $t) {
            $this->add('XML-XSD', 'C2', $rel, 0, "Nao resolvi o schema $loc: " . $t->getMessage(), '', '', 'warn', true);
        }
        libxml_clear_errors();
    }

    /** Classes citadas em atributos e textos do XML (di, events, webapi, layout, ui_component, system). */
    private function classRefs(string $rel, \DOMXPath $xp): void
    {
        $refs = [];
        foreach ($xp->query('//@class | //@instance | //@type | //@for | //@source_model | //@backend_model | //@frontend_model') as $a) {
            $owner = $a->ownerElement;
            // em di.xml, type= de argument/item é o tipo do argumento (object, string...), não classe
            if ($a->nodeName === 'type' && in_array($owner->nodeName, ['argument', 'item', 'param'], true)) {
                continue;
            }
            $refs[] = [$a->value, $owner->getLineNo(), $owner->nodeName . ' ' . $a->nodeName];
        }
        if (preg_match('#/etc/(\w+/)?di\.xml$#', $rel)) {
            foreach ($xp->query('/config/type/@name') as $a) {
                $refs[] = [$a->value, $a->ownerElement->getLineNo(), 'type name'];
            }
        }
        foreach ($xp->query('//*[@xsi:type="object"] | //source_model | //backend_model | //frontend_model'
            . ' | //item[(@name="class" or @name="instance") and @xsi:type="string"]') as $el) {
            $refs[] = [trim($el->textContent), $el->getLineNo(), $el->nodeName . ' ' . ($el->getAttribute('name') ?: 'texto')];
        }
        foreach ($refs as [$value, $line, $where]) {
            $value = trim($value);
            if (!preg_match(self::FQCN, $value)) {
                continue;
            }
            $this->report->count('classes citadas em xml');
            $name = ltrim($value, '\\');
            if ($this->index->exists($name) || $this->index->isVirtualType($name)) {
                continue;
            }
            $sug = $this->index->suggest($name);
            $this->add('CLASS-MISSING', 'C1', $rel, $line, "$name nao existe nem como classe nem como virtualType ($where).",
                $sug ? 'Voce quis dizer: ' . implode(' ou ', $sug)
                    : 'Procure a classe real com magento-api --search; nao invente nome de classe do Magento.');
        }
    }
}

/** Resolve componentes JS e templates Knockout para arquivos, como o RequireJS do Magento faz. */
final class JsResolver
{
    public function __construct(private ClassIndex $index)
    {
    }

    /** Vendor_Module/js/x -> view/{area,base}/web/js/x.js; null = alias (não dá para conferir). */
    public function jsFile(string $id): ?string
    {
        if (!preg_match('#^([A-Z][A-Za-z0-9]*_[A-Za-z0-9]+)/(.+)$#', $id, $m)) {
            return null;
        }
        $dir = $this->index->moduleDir($m[1]);
        if (!$dir) {
            return '';
        }
        $path = preg_replace('/\.js$/', '', $m[2]) . '.js';
        foreach (['adminhtml', 'base', 'frontend'] as $area) {
            if (is_file("$dir/view/$area/web/$path")) {
                return "$dir/view/$area/web/$path";
            }
        }
        return '';
    }

    /** ui/x -> Magento_Ui/templates/x.html; Vendor_Module/x -> view/{area}/web/template/x.html */
    public function templateFile(string $id): ?string
    {
        if (str_starts_with($id, 'ui/')) {
            $dir = $this->index->moduleDir('Magento_Ui');
            $rest = substr($id, 3);
            foreach (['base', 'adminhtml', 'frontend'] as $area) {
                if ($dir && is_file("$dir/view/$area/web/templates/$rest.html")) {
                    return "$dir/view/$area/web/templates/$rest.html";
                }
            }
            return '';
        }
        if (!preg_match('#^([A-Z][A-Za-z0-9]*_[A-Za-z0-9]+)/(.+)$#', $id, $m)) {
            // sem prefixo de módulo: com "/" pode ser alias do RequireJS (o core usa, ex.
            // templates/form/collapsible), não dá para afirmar; sem "/" nunca resolve (UI_form_date)
            return str_contains($id, '/') ? null : '';
        }
        $dir = $this->index->moduleDir($m[1]);
        foreach (['adminhtml', 'base', 'frontend'] as $area) {
            foreach (['template', 'templates'] as $sub) {
                if ($dir && is_file("$dir/view/$area/web/$sub/{$m[2]}.html")) {
                    return "$dir/view/$area/web/$sub/{$m[2]}.html";
                }
            }
        }
        return '';
    }
}
