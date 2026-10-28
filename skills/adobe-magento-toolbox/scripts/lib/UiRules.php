<?php
/**
 * Regras de UI Component que o XSD não pega. Todas vieram de defeito real (spinner infinito sem
 * erro nenhum, ou exceção só ao abrir a página):
 *  - dataSource sem componente JS (o provider nunca nasce, as deps nunca resolvem);
 *  - provider/deps/spinner apontando para nome que não existe no arquivo;
 *  - template/elementTmpl e component que não existem como arquivo;
 *  - dataType como atributo de column; bloco "actions" legado em argument data.
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class UiRules
{
    private const FORM_MODEL = 'vendor/magento/module-cms/view/adminhtml/ui_component/cms_block_form.xml';
    private const LISTING_MODEL = 'vendor/magento/module-cms/view/adminhtml/ui_component/cms_block_listing.xml';

    public function __construct(private Env $env, private ClassIndex $index, private Report $report, private JsResolver $js)
    {
    }

    private function add(string $id, string $cls, string $file, int $line, string $msg, string $hint, string $model = ''): void
    {
        $f = new Finding($id, $cls, 'error', $file, $line, $msg, $hint, $model);
        $f->preexisting = !$this->env->isAdded($file, $line);
        $this->report->add($f);
    }

    public function check(string $rel, \DOMDocument $dom, \DOMXPath $xp): void
    {
        $root = $dom->documentElement;
        $name = basename($rel, '.xml');
        $core = $root->nodeName === 'listing' ? self::LISTING_MODEL : self::FORM_MODEL;
        $model = XmlChecks::modelText($root->nodeName === 'listing' ? 'ui-listing' : 'ui-form',
            is_file($this->env->root . '/' . $core) ? $core : '');

        // 1. dataSource precisa declarar o componente JS (form/provider ou grid/provider)
        $sources = [];
        foreach ($xp->query('//dataSource') as $ds) {
            $sources[] = $ds->getAttribute('name');
            $hasComponent = $ds->getAttribute('component') !== ''
                || $xp->query('.//item[@name="js_config" or @name="config"]/item[@name="component"]', $ds)->length > 0
                || $xp->query('./settings/component', $ds)->length > 0;
            if (!$hasComponent) {
                $provider = $root->nodeName === 'listing' ? 'Magento_Ui/js/grid/provider' : 'Magento_Ui/js/form/provider';
                $this->add('UI-DS-NO-COMPONENT', 'C3', $rel, $ds->getLineNo(),
                    'dataSource ' . $ds->getAttribute('name') . ' sem componente JS: as deps nunca resolvem (spinner infinito, sem erro).',
                    "Declare component=\"$provider\" no dataSource, como no arquivo modelo.", $model);
            }
        }

        // 2. provider, deps e spinner apontam para nomes que existem neste arquivo
        $children = [$name => true];
        foreach ($xp->query('//*[@name]') as $el) {
            $children[$el->getAttribute('name')] = true;
        }
        $columns = [];
        foreach ($xp->query('//columns[@name]') as $c) {
            $columns[] = $c->getAttribute('name');
        }
        $refs = [];
        foreach ($xp->query('//item[@name="provider" or @name="deps"] | //item[@name="deps"]/item | //settings/deps/dep | //settings/provider') as $el) {
            if ($el->getElementsByTagName('item')->length === 0) {
                $refs[] = [trim($el->textContent), $el->getLineNo()];
            }
        }
        foreach ($refs as [$ref, $line]) {
            if ($ref === '' || preg_match('/[${}]/', $ref) || !str_contains($ref, '.')) {
                continue;
            }
            $parts = explode('.', $ref);
            if ($parts[0] !== $name) {
                $this->add('UI-DEPS-UNRESOLVED', 'C3', $rel, $line,
                    "Referencia $ref comeca por {$parts[0]}, mas o componente se chama $name (nome do arquivo).",
                    'provider e deps usam o nome do arquivo: ' . $name . '.' . ($sources[0] ?? $name . '_data_source') . '.', $model);
                continue;
            }
            if (!isset($children[$parts[1]])) {
                $this->add('UI-DEPS-UNRESOLVED', 'C3', $rel, $line,
                    "Referencia $ref aponta para {$parts[1]}, que nao existe neste arquivo: a dep nunca resolve (spinner infinito).",
                    'Use o nome real do dataSource: ' . ($sources ? implode(' ou ', $sources) : 'declare um dataSource') . '.', $model);
            }
        }
        foreach ($xp->query('//item[@name="spinner"] | //settings/spinner') as $sp) {
            $v = trim($sp->textContent);
            if ($v !== '' && $columns && !in_array($v, $columns, true)) {
                $this->add('UI-DEPS-UNRESOLVED', 'C3', $rel, $sp->getLineNo(),
                    "spinner $v nao e o nome de nenhum columns (" . implode(', ', $columns) . '): a grid fica carregando para sempre.',
                    'O spinner tem que ser o name do bloco columns.', $model);
            }
        }

        // 3. templates Knockout e componentes JS existem como arquivo
        foreach ($xp->query('//item[@name="template" or @name="elementTmpl" or @name="bodyTmpl" or @name="headerTmpl"'
            . ' or @name="fieldTmpl" or @name="previewTmpl"] | //settings/template | //settings/elementTmpl'
            . ' | //formElements//settings/elementTmpl') as $t) {
            $v = trim($t->textContent);
            if ($v === '' || preg_match('/[${}]/', $v) || $t->getElementsByTagName('item')->length > 0) {
                continue;
            }
            if ($this->js->templateFile($v) === '') {
                $this->add('UI-TEMPLATE-MISSING', 'C3', $rel, $t->getLineNo(),
                    "Template $v nao existe: o Knockout fica esperando o template e a tela nao termina de carregar.",
                    'Remova o item para usar o padrao do componente ou copie um template real do vendor (ex.: ui/form/element/date).', $model);
            }
        }
        foreach ($xp->query('//@component | //item[@name="component"]') as $c) {
            $v = trim($c instanceof \DOMAttr ? $c->value : $c->textContent);
            $line = $c instanceof \DOMAttr ? $c->ownerElement->getLineNo() : $c->getLineNo();
            if ($v === '' || preg_match('/[${}]/', $v)) {
                continue;
            }
            if ($this->js->jsFile($v) === '') {
                $this->add('JS-COMPONENT-MISSING', 'C7', $rel, $line,
                    "Componente JS $v nao existe (nenhum view/*/web/" . preg_replace('#^[^/]+/#', '', $v) . '.js no modulo).',
                    'Use um componente real do Magento_Ui ou crie o arquivo JS no modulo.', $model);
            }
        }

        // 4. form raiz com o template padrao (abas) sem layout: o spinner da area de abas nunca some
        if ($root->nodeName === 'form' && $root->getAttribute('extends') === '' && $sources
            && $xp->query('/form/argument[@name="data"]/item[@name="template"] | /form/settings/layout')->length === 0) {
            $this->add('UI-FORM-NO-LAYOUT', 'C3', $rel, $root->getLineNo(),
                'form sem template nem layout: o template padrao espera a area de abas (' . $name . '.areas), que nao existe, e o spinner nunca some.',
                'Declare no argument data o item template = templates/form/collapsible (form simples) ou um layout de abas em settings.', $model);
        }

        // 5. formas que passam no XSD mas quebram ao criar o componente
        foreach ($xp->query('//column[@dataType]') as $col) {
            $this->add('UI-COLUMN-DATATYPE-ATTR', 'C3', $rel, $col->getLineNo(),
                'column ' . $col->getAttribute('name') . ' com dataType como atributo: o Magento lanca excecao ao montar a grid.',
                'Tire o atributo e declare dentro de settings: dataType (ex.: select) e options, como no modelo.', $model);
        }
        foreach ($xp->query('//argument[@name="data"]/item[@name="actions"]') as $a) {
            $this->add('UI-LEGACY-ACTIONS', 'C3', $rel, $a->getLineNo(),
                'item actions direto no argument data: o Magento chama Context::addActions(), que nao existe nesta versao.',
                'Remova o bloco; as acoes da linha vem do prepareDataSource da classe da actionsColumn.', $model);
        }
    }
}
