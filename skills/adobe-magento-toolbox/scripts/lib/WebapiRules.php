<?php
/**
 * webapi.xml: o método do service existe, rota anonymous nova vira aviso de segurança e as
 * interfaces expostas têm o docblock que o TypeProcessor do Magento exige em tempo de execução
 * ("Each method must have a doc block", ao montar a resposta REST).
 */
declare(strict_types=1);

namespace MagentoToolbox;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class WebapiRules
{
    /** @var array<string,true> interfaces já conferidas */
    private array $seen = [];

    public function __construct(private Env $env, private ClassIndex $index, private Report $report)
    {
    }

    private function add(string $id, string $cls, string $sev, string $file, int $line, string $msg, string $hint = '', bool $always = false): void
    {
        $f = new Finding($id, $cls, $sev, $file, $line, $msg, $hint,
            XmlChecks::modelText('rest', 'vendor/magento/module-cms/etc/webapi.xml'));
        $f->preexisting = !$always && !$this->env->isAdded($file, $line);
        $this->report->add($f);
    }

    public function check(string $rel, \DOMXPath $xp): void
    {
        foreach ($xp->query('//route') as $route) {
            $service = $xp->query('./service', $route)->item(0);
            $label = $route->getAttribute('method') . ' ' . $route->getAttribute('url');
            foreach ($xp->query('./resources/resource[@ref="anonymous"]', $route) as $res) {
                $this->add('WEBAPI-ANONYMOUS', 'C8', 'warn', $rel, $res->getLineNo(),
                    "Rota $label sem autenticacao (anonymous).",
                    'So se a regra de negocio exigir acesso publico; senao use um resource do acl.xml (ou self para cliente logado).');
            }
            if (!$service instanceof \DOMElement) {
                continue;
            }
            $class = ltrim($service->getAttribute('class'), '\\');
            $method = $service->getAttribute('method');
            $file = $this->index->file($class);
            if (!$file || !$this->index->exists($class)) {
                continue; // CLASS-MISSING já reporta
            }
            $methods = $this->methods($class);
            if (!isset($methods[strtolower($method)])) {
                $this->add('WEBAPI-METHOD-MISSING', 'C1', 'error', $rel, $service->getLineNo(),
                    "Rota $label chama $class::$method(), que nao existe na interface.",
                    'Metodos da interface: ' . implode(', ', array_slice(array_map(fn($m) => $m['name'], $methods), 0, 12)) . '.');
                continue;
            }
            $this->docblocks($class, $methods[strtolower($method)], 0);
        }
    }

    /**
     * Métodos da interface (e das que ela estende), lidos do código, sem carregar classe.
     *
     * @return array<string,array{name:string,line:int,doc:string,params:int,file:string,uses:array<string,string>,ns:string}>
     */
    private function methods(string $class, int $depth = 0): array
    {
        $file = $this->index->file($class);
        if (!$file || $depth > 3) {
            return [];
        }
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        try {
            $ast = $parser->parse((string)file_get_contents($file)) ?? [];
        } catch (\PhpParser\Error) {
            return [];
        }
        $finder = new NodeFinder();
        $ns = $finder->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);
        $nsName = $ns && $ns->name ? $ns->name->toString() : '';
        $uses = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $u) {
                $uses[strtolower($u->getAlias()->toString())] = $u->name->toString();
            }
        }
        $out = [];
        $like = $finder->findFirstInstanceOf($ast, Node\Stmt\ClassLike::class);
        if ($like instanceof Node\Stmt\Interface_) {
            foreach ($like->extends as $parent) {
                $out += $this->methods($this->resolve($parent->toString(), $nsName, $uses), $depth + 1);
            }
        }
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class) as $m) {
            $out[strtolower($m->name->toString())] = ['name' => $m->name->toString(), 'line' => $m->getStartLine(),
                'doc' => (string)$m->getDocComment()?->getText(), 'params' => count($m->params),
                'file' => $file, 'uses' => $uses, 'ns' => $nsName];
        }
        return $out;
    }

    /** @param array<string,string> $uses */
    private function resolve(string $name, string $ns, array $uses): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        $first = strtolower(explode('\\', $name)[0]);
        if (isset($uses[$first])) {
            return $uses[$first] . substr($name, strlen($first));
        }
        return ltrim($ns . '\\' . $name, '\\');
    }

    /**
     * O método exposto precisa de @param para cada parâmetro e de @return; os tipos de dado que ele
     * devolve ou recebe (interfaces Api\Data do projeto) precisam do mesmo em cada getter e setter.
     *
     * @param array{name:string,line:int,doc:string,params:int,file:string,uses:array<string,string>,ns:string} $m
     */
    private function docblocks(string $class, array $m, int $depth): void
    {
        $rel = $this->relative($m['file']);
        if (!str_starts_with($rel, 'app/code/')) {
            return; // interface do vendor: não é do projeto
        }
        $doc = $m['doc'];
        $isGetter = (bool)preg_match('/^(get|is|has)[A-Z]/', $m['name']);
        $missing = [];
        if ($doc === '') {
            $missing[] = 'docblock';
        } else {
            if (preg_match_all('/@param\s/', $doc) < $m['params']) {
                $missing[] = '@param de cada parametro';
            }
            if (($isGetter || $depth === 0) && !preg_match('/@return\s/', $doc)) {
                $missing[] = '@return';
            }
        }
        if ($missing) {
            $this->add('WEBAPI-DOCBLOCK', 'C9', 'error', $rel, $m['line'],
                "$class::{$m['name']}() sem " . implode(' e ', $missing) . ': a Web API do Magento le os tipos do docblock e falha (Each method must have a doc block).',
                'Escreva o docblock com o tipo completo, ex.: @return \\Vendor\\Module\\Api\\Data\\FooInterface[]', true);
        }
        if ($depth >= 2 || $doc === '') {
            return;
        }
        preg_match_all('/@(?:param|return)\s+([\\\\A-Za-z0-9_|\[\]]+)/', $doc, $types);
        foreach ($types[1] as $t) {
            foreach (explode('|', str_replace('[]', '', $t)) as $type) {
                if (!preg_match('/Interface$/', $type)) {
                    continue;
                }
                $fq = $this->resolve($type, $m['ns'], $m['uses']);
                if (isset($this->seen[$fq]) || !str_contains($fq, '\\Api\\Data\\')) {
                    continue;
                }
                $this->seen[$fq] = true;
                foreach ($this->methods($fq) as $dm) {
                    if (preg_match('/^(get|is|has|set)[A-Z]/', $dm['name']) && !str_starts_with($dm['name'], 'getExtensionAttributes')) {
                        $this->docblocks($fq, $dm, $depth + 1);
                    }
                }
            }
        }
    }

    private function relative(string $path): string
    {
        foreach ([$this->env->codeRoot => 'app/code', $this->env->root => ''] as $prefix => $to) {
            if (str_starts_with($path, $prefix . '/')) {
                return ltrim($to . '/' . substr($path, strlen($prefix) + 1), '/');
            }
        }
        return $path;
    }
}
