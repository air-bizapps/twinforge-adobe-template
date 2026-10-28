<?php
/**
 * Checagens de PHP: sintaxe (php -l), toda classe citada existe (nomes resolvidos pelo php-parser,
 * como o PHP resolve), a classe carrega sem fatal de herança, parent::__construct bate com o pai,
 * e padrões proibidos nas linhas novas (ObjectManager direto, domínio fixo).
 *
 * Carregado depois do autoload do vendor (usa o nikic/php-parser do próprio projeto).
 */
declare(strict_types=1);

namespace MagentoToolbox;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final class RefCollector extends NodeVisitorAbstract
{
    /** @var array<int,array{0:string,1:int,2:string,3:string}> nome, linha, tipo de uso, nome original */
    public array $refs = [];
    /** @var array<int,array<string,mixed>> classes declaradas no arquivo */
    public array $classes = [];
    /** @var array<int,array{0:string,1:int}> */
    public array $objectManager = [];
    /** @var array<int,array{0:string,1:int}> */
    public array $urls = [];
    public string $namespace = '';
    private ?int $current = null;
    private bool $inCtor = false;

    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name ? $node->name->toString() : '';
        } elseif ($node instanceof Stmt\Use_ && $node->type === Stmt\Use_::TYPE_NORMAL) {
            foreach ($node->uses as $u) {
                $this->ref($u->name, 'use');
            }
        } elseif ($node instanceof Stmt\GroupUse) {
            foreach ($node->uses as $u) {
                if (($u->type ?: $node->type) === Stmt\Use_::TYPE_NORMAL) {
                    $this->refs[] = [$node->prefix->toString() . '\\' . $u->name->toString(), $u->getStartLine(), 'use', ''];
                }
            }
        } elseif ($node instanceof Stmt\ClassLike && isset($node->namespacedName)) {
            $kind = match (true) {
                $node instanceof Stmt\Interface_ => 'interface',
                $node instanceof Stmt\Trait_ => 'trait',
                $node instanceof Stmt\Enum_ => 'enum',
                default => 'class',
            };
            $this->classes[] = ['name' => $node->namespacedName->toString(), 'line' => $node->getStartLine(),
                'kind' => $kind, 'ctor' => [], 'parentCall' => null,
                'extends' => $node instanceof Stmt\Class_ && $node->extends ? $node->extends->toString() : null];
            $this->current = count($this->classes) - 1;
            if ($node instanceof Stmt\Class_) {
                if ($node->extends) {
                    $this->ref($node->extends, 'extends');
                }
                foreach ($node->implements as $i) {
                    $this->ref($i, 'implements');
                }
            } elseif ($node instanceof Stmt\Interface_) {
                foreach ($node->extends as $i) {
                    $this->ref($i, 'extends');
                }
            } elseif ($node instanceof Stmt\Enum_) {
                foreach ($node->implements as $i) {
                    $this->ref($i, 'implements');
                }
            }
        } elseif ($node instanceof Stmt\TraitUse) {
            foreach ($node->traits as $t) {
                $this->ref($t, 'trait');
            }
        } elseif ($node instanceof Stmt\Catch_) {
            foreach ($node->types as $t) {
                $this->ref($t, 'catch');
            }
        } elseif ($node instanceof Stmt\Property && $node->type) {
            $this->types($node->type, 'propriedade');
        } elseif ($node instanceof Node\Attribute) {
            $this->ref($node->name, 'atributo');
        }

        if ($node instanceof Expr\New_ && $node->class instanceof Name) {
            $this->ref($node->class, 'new');
        } elseif (($node instanceof Expr\StaticCall || $node instanceof Expr\ClassConstFetch
                || $node instanceof Expr\StaticPropertyFetch) && $node->class instanceof Name) {
            $this->ref($node->class, 'chamada estatica');
            if ($node instanceof Expr\StaticCall) {
                $this->staticCall($node);
            }
        } elseif ($node instanceof Expr\Instanceof_ && $node->class instanceof Name) {
            $this->ref($node->class, 'instanceof');
        } elseif ($node instanceof Node\Scalar\String_ && preg_match('~https?://~i', $node->value)) {
            $this->urls[] = [$node->value, $node->getStartLine()];
        }

        if ($node instanceof Node\FunctionLike) {
            foreach ($node->getParams() as $p) {
                if ($p->type) {
                    $this->types($p->type, 'parametro');
                }
            }
            if ($node->getReturnType()) {
                $this->types($node->getReturnType(), 'retorno');
            }
        }
        if ($node instanceof Stmt\ClassMethod && $this->current !== null
            && strtolower($node->name->toString()) === '__construct') {
            $this->inCtor = true;
            foreach ($node->params as $p) {
                if ($p->var instanceof Expr\Variable && is_string($p->var->name)) {
                    $this->classes[$this->current]['ctor'][$p->var->name] = $p->type instanceof Name ? $p->type->toString() : null;
                }
            }
        }
        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\ClassMethod) {
            $this->inCtor = false;
        } elseif ($node instanceof Stmt\ClassLike) {
            $this->current = null;
        }
        return null;
    }

    private function staticCall(Expr\StaticCall $node): void
    {
        $class = $node->class->toString();
        $method = $node->name instanceof Node\Identifier ? strtolower($node->name->toString()) : '';
        if ($class === 'Magento\Framework\App\ObjectManager' && $method === 'getinstance') {
            $this->objectManager[] = [$class, $node->getStartLine()];
        }
        if ($this->inCtor && $this->current !== null && strtolower($class) === 'parent' && $method === '__construct') {
            $args = 0;
            $named = false;
            $unpack = false;
            $vars = [];
            foreach ($node->args as $i => $a) {
                if (!$a instanceof Node\Arg) {
                    continue;
                }
                $named = $named || $a->name !== null;
                $unpack = $unpack || $a->unpack;
                $args++;
                if ($a->value instanceof Expr\Variable && is_string($a->value->name)) {
                    $vars[$i] = $this->classes[$this->current]['ctor'][$a->value->name] ?? null;
                }
            }
            $this->classes[$this->current]['parentCall'] = ['line' => $node->getStartLine(), 'args' => $args,
                'named' => $named, 'unpack' => $unpack, 'vars' => $vars];
        }
    }

    private function ref(Name $n, string $kind): void
    {
        if ($n->isSpecialClassName()) {
            return;
        }
        $orig = $n->getAttribute('originalName');
        $this->refs[] = [$n->toString(), $n->getStartLine(), $kind, $orig instanceof Name ? $orig->toString() : ''];
    }

    private function types(Node $t, string $kind): void
    {
        if ($t instanceof Name) {
            $this->ref($t, $kind);
        } elseif ($t instanceof Node\NullableType) {
            $this->types($t->type, $kind);
        } elseif ($t instanceof Node\UnionType || $t instanceof Node\IntersectionType) {
            foreach ($t->types as $sub) {
                $this->types($sub, $kind);
            }
        }
    }
}

final class PhpChecks
{
    /** hosts que podem aparecer fixos sem problema */
    private const URL_OK = '/^(localhost|127\.0\.0\.1|0\.0\.0\.0|(www\.)?example\.(com|org|net)|[^.]+\.example|(www\.)?w3\.org|schemas\.xmlsoap\.org|json-schema\.org)(:\d+)?$/i';

    private \PhpParser\Parser $parser;

    public function __construct(private Env $env, private ClassIndex $index, private Report $report)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function run(): void
    {
        foreach ($this->env->present('#\.(php|phtml)$#') as $rel) {
            $this->file($rel);
        }
    }

    private function add(string $id, string $cls, string $sev, string $file, int $line, string $msg,
                         string $hint = '', string $model = '', bool $always = false): void
    {
        $f = new Finding($id, $cls, $sev, $file, $line, $msg, $hint, $model);
        $f->preexisting = !$always && !$this->env->isAdded($file, $line);
        $this->report->add($f);
    }

    private function file(string $rel): void
    {
        $abs = $this->env->abs($rel);
        $this->report->count('php');
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1');
        if (!str_contains($out, 'No syntax errors')) {
            preg_match('/on line (\d+)/', $out, $m);
            $msg = trim((string)preg_replace(['/^PHP /m', '/ in \S+ on line \d+/', '/Errors parsing.*/s'], '', $out));
            $this->add('PHP-SYNTAX', 'C4', 'error', $rel, (int)($m[1] ?? 0), "PHP nao compila: $msg", '', '', true);
            return;
        }
        try {
            $ast = $this->parser->parse((string)file_get_contents($abs)) ?? [];
        } catch (\PhpParser\Error) {
            return;
        }
        $ast = (new NodeTraverser(new NameResolver(null, ['preserveOriginalNames' => true])))->traverse($ast);
        $c = new RefCollector();
        (new NodeTraverser($c))->traverse($ast);
        $isTest = (bool)preg_match('#/Test/#', $rel);

        foreach ($c->refs as [$name, $line, $kind, $orig]) {
            if ($this->index->exists($name)) {
                continue;
            }
            if ($this->index->isVirtualType($name)) {
                $this->add('CLASS-VIRTUALTYPE-IN-PHP', 'C1', 'error', $rel, $line,
                    "$name e um virtualType do di.xml, nao uma classe: PHP nao enxerga virtualType ($kind).",
                    'Use no PHP a classe real (o type do virtualType) e injete o virtualType pelo di.xml.');
                continue;
            }
            $hint = '';
            if ($orig !== '' && !str_contains($orig, '\\') && $c->namespace !== '' && $name === $c->namespace . '\\' . $orig) {
                $hint = "Falta o use: '$orig' sem import vira $name (namespace atual).";
            }
            $sug = $this->index->suggest($name);
            $hint .= ($hint !== '' ? ' ' : '') . ($sug ? 'Voce quis dizer: ' . implode(' ou ', $sug)
                : 'Procure a classe real: magento-api --search "' . self::words($name) . '"');
            $this->add('CLASS-MISSING', 'C1', 'error', $rel, $line, "$name nao existe ($kind).", $hint);
        }

        if (!$isTest && str_starts_with($rel, 'app/code/')) {
            foreach ($c->objectManager as [, $line]) {
                $this->add('PHP-OBJECTMANAGER', 'C5', 'error', $rel, $line,
                    'ObjectManager::getInstance() direto no codigo do modulo.',
                    'Receba a dependencia pelo construtor; o di.xml configura o resto.');
            }
            foreach ($c->urls as [$value, $line]) {
                if (!preg_match('~https?://([^/\s\'"?#]+)~i', $value, $m) || preg_match('/[%{$]/', $m[1])
                    || preg_match(self::URL_OK, $m[1])) {
                    continue;
                }
                $store = (bool)preg_match('/bunge|webjump|\.local(:\d+)?$/i', $m[1]);
                $this->add('PHP-HARDCODED-URL', $store ? 'C6' : 'C8', $store ? 'error' : 'warn', $rel, $line,
                    "URL fixa no codigo ({$m[1]}).",
                    $store ? 'Monte a URL com a base da loja (UrlInterface ou StoreManager), nunca com o dominio fixo.'
                        : 'Leve a URL para config (config.xml com system.xml) para trocar por ambiente.');
            }
        }
        if (!$isTest && $c->classes) {
            $this->probe($rel, $c);
        }
    }

    private static function words(string $fqcn): string
    {
        $last = substr($fqcn, (int)strrpos($fqcn, '\\') + 1);
        return strtolower(trim((string)preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $last)));
    }

    /**
     * Carrega cada classe num processo separado: fatal de herança (visibilidade, tipo de propriedade,
     * método abstrato, assinatura) só aparece ao carregar, e derrubaria este processo.
     */
    private function probe(string $rel, RefCollector $c): void
    {
        $probe = __DIR__ . '/probe.php';
        foreach ($c->classes as $cl) {
            $this->report->count('classes carregadas');
            $input = json_encode(['root' => $this->env->root, 'codeRoot' => $this->env->codeRoot,
                'class' => $cl['name'], 'call' => $cl['parentCall']]);
            $cmd = 'MAGENTO_VERIFY_PROBE=' . escapeshellarg((string)$input) . ' ' . escapeshellarg(PHP_BINARY)
                . ' -d display_errors=stderr -d log_errors=0 ' . escapeshellarg($probe) . ' 2>&1';
            $out = (string)shell_exec($cmd);
            $json = null;
            foreach (array_reverse(explode("\n", trim($out))) as $l) {
                if (str_starts_with($l, '{')) {
                    $json = json_decode($l, true);
                    break;
                }
            }
            if (!is_array($json)) {
                preg_match('/(?:PHP )?Fatal error:\s+(.+?) in (\S+?)(?: on line |:)(\d+)/', $out, $m);
                $msg = $m ? $m[1] : trim(substr($out, 0, 300));
                [$file, $line] = $m ? [$this->relative($m[2]), (int)$m[3]] : [$rel, $cl['line']];
                $this->add('PHP-LOAD', 'C4', 'error', $file, $line, "Classe {$cl['name']} nao carrega: " . self::clean($msg),
                    'O PHP recusa a declaracao (heranca, visibilidade, tipo ou assinatura). Compare com a classe pai.', '', true);
                continue;
            }
            if (!$json['ok']) {
                $file = isset($json['file']) ? $this->relative($json['file']) : $rel;
                $this->add('PHP-LOAD', 'C4', 'error', $file, (int)($json['line'] ?? $cl['line']),
                    "Classe {$cl['name']} nao carrega: " . self::clean((string)$json['error']), '', '', true);
            }
            foreach ($json['parent'] ?? [] as $p) {
                $this->add('PHP-PARENT-CTOR', 'C4', 'error', $rel, (int)$cl['parentCall']['line'], (string)$p,
                    'Passe ao parent::__construct exatamente os parametros do construtor do pai, na ordem dele.');
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
        $vendor = (string)realpath($this->env->root . '/vendor');
        return ($vendor !== '' && str_starts_with($path, $vendor . '/')) ? 'vendor/' . substr($path, strlen($vendor) + 1) : $path;
    }

    private static function clean(string $msg): string
    {
        return trim((string)preg_replace('/^Uncaught \w+: /', '', $msg));
    }
}
