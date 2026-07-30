# Pré-voo da tabela antes de gerar

**Data:** 29/07/2026
**Versão alvo:** 3.3.0 (feature nova; muda a saída de console)
**Branch:** `preflight-tabela`

## O problema

Hoje o `getic:install` pergunta uma única coisa sobre a tabela antes de escrever arquivo:
se ela existe (`GeneratorCommand::tableExists()` → `Schema::hasTable()`). Nada mais é
verificado, apesar de o gerador já introspectar o schema inteiro para gerar.

O custo disso apareceu em 29/07/2026, gerando na tabela `clientes` do banco de teste. A
geração completou, imprimiu "✅ CRUD criado com sucesso!", e a primeira visita à página
devolveu 500:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'created_at' in 'order clause'
(SQL: select * from `clientes` order by `created_at` desc limit 10 offset 0)
```

A tabela é legada, anterior ao Laravel: sem `created_at`, sem `updated_at` e sem chave
primária declarada. O `InertiaController.stub` gerado assume as três coisas. Corrigido o
`orderBy`, o passo seguinte seria fatal em `->created_at->format()` sobre nulo.

O que existe de parecido com inspeção é o `debugColumns()`, e ele não serve: despeja o
JSON de toda coluna no terminal em toda geração — a pendência conhecida do `CLAUDE.md` — e
roda **depois** de `buildRouter()`, ou seja, depois de os arquivos já estarem no disco.
Ele narra, não avisa, e chega tarde.

## O que este trabalho é e o que não é

**É:** avisar, antes de escrever qualquer arquivo, que a tabela tem características que o
código gerado não suporta; deixar a pessoa decidir; e registrar no terminal que ela
decidiu seguir.

**Não é:** deixar o código gerado robusto a schema fora da convenção. Essa correção (o
`orderBy`, os campos de timestamp no `through()`, `$timestamps`/`$primaryKey` no Model)
foi levantada, reproduzida e **cortada do escopo pelo dono** em 29/07/2026, por ser tabela
anterior ao Laravel. O pré-voo não conserta a geração: ele avisa que ela vai sair torta.

**Postura, decidida pelo dono:** avisa, **não** bloqueia. A tabela é do usuário, e gerar
em cima de tabela torta para ajustar o Model à mão depois é caso legítimo.

## Arquitetura

Uma classe nova, `src/TableInspection.php`, no molde do `src/NavigationRegion.php` que esta
release estabeleceu: `final`, sem dependência de framework, sem I/O. Dados entram, achados
saem.

É o último ponto em que nenhum arquivo do CRUD foi escrito ainda. Uma exceção: se você
aceitar instalar o sistema de temas no prompt inicial, ele é instalado antes, porque roda
na fase de perguntas do próprio Artisan — cancelar no pré-voo não desfaz isso.

```php
final class TableInspection
{
    /**
     * @param array<int, object> $columns Formato de `SHOW COLUMNS`: ->Field, ->Type,
     *                                    ->Null, ->Key, ->Default, ->Extra.
     * @return array<int, array{code: string, columns: array<int, string>}>
     */
    public function inspect(array $columns): array;
}
```

A classe **não produz frase nenhuma**. Devolve códigos; quem traduz para português é o
`InstallCommand`. Duas consequências desejadas: a prosa muda sem tocar em teste, e os
testes asseveram comportamento (`code`) em vez de texto.

Tabela convencional devolve `[]`.

## As checagens

| `code` | Dispara quando | O que quebra no código gerado |
|---|---|---|
| `timestamps` | falta `created_at` **ou** falta `updated_at` | `orderBy('created_at', 'desc')` → SQLSTATE 42S22; e `->created_at->format(...)` → fatal sobre nulo |
| `primary-key-missing` | nenhuma coluna com `Key === 'PRI'` | `$model->id` é nulo → `key={x.id}` do `Index` e os links de show/edit/destroy |
| `primary-key-not-id` | existe `PRI`, mas o `Field` não é `id` | mesmo efeito: o Model gerado não declara `$primaryKey`, então Eloquent procura `id` |
| `column-identifier` | coluna cujo nome não casa `/^[A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*$/` | `$model->2fa_secret` é PHP inválido, e `2fa_secret: string;` é TS inválido — quebra no parse |

Notas sobre `column-identifier`, para não gerar falso positivo:

- A regex é exatamente a regra de identificador do PHP, então `endereço` **não** dispara.
- Palavra reservada **não** entra na checagem: `$model->class` e `class: string;` são
  ambos legais.
- `columns` traz os nomes ofensores; nos outros três códigos, `primary-key-not-id` traz o
  nome da PK encontrada e os demais vêm com `[]`.

Contrato de multiplicidade e ordem, para não deixar ambíguo:

- `timestamps` dispara **uma vez** mesmo que faltem as duas colunas — é um achado, não dois.
- `primary-key-missing` e `primary-key-not-id` são **mutuamente exclusivos**: ou não há
  `PRI`, ou há e ela tem um nome.
- `column-identifier` é **um único achado** com todos os nomes ofensores em `columns`, não
  um achado por coluna.
- A ordem do array devolvido é fixa: `timestamps`, depois o de chave primária, depois
  `column-identifier`. Ordem fixa porque a saída no terminal é lida de cima para baixo e
  vale começar pelo que quebra mais cedo.

## Integração no comando

O pré-voo entra no `InstallCommand::handle()` **depois** do `tableExists()` e do
`isLaravel12OrHigher()`, e **antes** de `$this->name = $this->_buildClassName()`. É o
último ponto em que nenhum arquivo foi escrito ainda.

`getColumns()` memoiza em `$this->tableColumns`, então chamá-lo aqui não custa consulta
extra depois.

Com achados, no modo interativo:

```
$ php artisan getic:install clientes --stack=react

⚠  2 avisos sobre a tabela `clientes`:
   • Sem `created_at`/`updated_at`: a listagem gerada ordena por `created_at` e vai
     falhar no banco.
   • Sem chave primária declarada: o `Index` usa `id` para a key da linha e para os
     links de ver/editar/excluir, e ele vai vir nulo.

 Gerar mesmo assim? (yes/no) [yes]
```

São dois porque é o que a `clientes` real dispara. A frase do terceiro código, para
referência da implementação:

```
   • Coluna `2fa_secret` não é um identificador válido: o Controller e o tipo
     TypeScript gerados não vão compilar.
```

- `confirm('Gerar mesmo assim?', default: true)` — coerente com "avisa, não bloqueia".
- Respondendo **não**: `info('Geração cancelada.')` e `return self::FAILURE`. Nenhum
  arquivo escrito.
- Respondendo **sim**: segue, e no fim, depois da lista "Arquivos gerados", uma linha de
  resumo com a mesma contagem: ``⚠ Gerado com 2 avisos sobre `clientes` (acima). Revise
  antes de usar.``

Fora do modo interativo (`!$this->input->isInteractive()`), o `confirm()` do
`laravel/prompts` cai no default `true` e a geração segue. Nesse caminho o comando
imprime, junto dos avisos, a linha:

```
   Modo não interativo: seguindo por sua conta e risco.
```

O resumo do fim aparece nos dois modos. Ele existe porque em geração de vários CRUDs os
avisos rolam tela acima; ser a última coisa impressa é o que faz alguém ler.

Mensagens em português via `laravel/prompts`, conforme as convenções do `CLAUDE.md`. Os
helpers necessários (`warning`, `confirm`, `info`) já estão importados no `InstallCommand`.

## `debugColumns()` sai

O método e a sua chamada em `handle()` são removidos. Justificativa: é a pendência
conhecida do JSON no terminal, roda depois das escritas, e o pré-voo entrega o que ele
finge entregar. Saída de console não está na lista de API pública do `CLAUDE.md`
(comandos e flags, chaves de config, tags de publish, placeholders, estrutura dos arquivos
gerados), então remover não é breaking.

O dublê `SidebarSpyInstallCommand` e os outros dublês de teste sobrescrevem
`debugColumns()` para não tocar o banco; essas sobrescritas saem junto.

## Testes

`tests/Unit/TableInspectionTest.php`, PHPUnit puro — sem Testbench, sem banco, colunas
fabricadas à mão, no molde do `NavigationRegionTest`:

- tabela convencional (`id` PRI, `created_at`, `updated_at`) → `[]`
- sem os dois timestamps → um achado `timestamps`
- faltando só `updated_at` → um achado `timestamps`
- sem `PRI` → `primary-key-missing`
- `PRI` chamada `idClientes` → `primary-key-not-id` com `columns => ['idClientes']`
- coluna `2fa_secret` → `column-identifier` com o nome ofensor
- coluna `endereço` → **nenhum** achado (a regra é a do PHP)
- a `clientes` real (sem timestamps, sem PRI) → dois achados, na ordem declarada

Mais **um** teste de comando com Testbench e dublê que sobrescreve `getColumns()`,
cobrindo a emenda entre classe e comando — que é onde o defeito crítico desta release
morava:

- schema legado + resposta "não" → exit code de falha e **nenhum arquivo escrito**
- schema legado + resposta "sim" → geração acontece
- schema convencional → nenhum prompt (o `expectsConfirmation` não é registrado, e o
  teste falha se o comando perguntar)

## Decisões tomadas, e o que foi recusado

| Decisão | Alternativa recusada | Por quê |
|---|---|---|
| Avisa, não bloqueia | Abortar sempre em tabela torta | A tabela é do usuário; ajustar o Model à mão depois é caso legítimo |
| Em modo não interativo, segue com aviso no fim | Abortar exigindo uma flag nova (`--ignore-warnings`) | `--no-interaction` no Laravel aceita os defaults; e a flag nova entraria na API pública do comando |
| Classe própria `TableInspection` | Método protegido no `InstallCommand` | O comando já tem ~1700 linhas, e testar via dublê exige Testbench; a classe testa em milissegundos |
| Classe própria `TableInspection` | Uma classe por checagem, plugável por config | Estrutura demais para três checagens, e a chave de config viraria API pública |
| Três checagens | Avisar sobre tipo de coluna não mapeado (json, enum, blob, longtext) | Numa tabela como `items` dispararia sempre; aviso que sempre dispara é aviso que se aprende a ignorar |
| Sem chave de config para desligar | `crud.preflight.enabled` | Sem achados o pré-voo é silencioso, então não há atrito a desligar — e a chave seria API pública nova |

## Semver

3.3.0. É feature nova e muda a saída de console (o `debugColumns()` deixa de imprimir),
mas não muda nome de comando, flag, chave de config, tag de publish, placeholder nem a
estrutura dos arquivos gerados — nada da lista de API pública do `CLAUDE.md`.
